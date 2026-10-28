<?php
// lib/WebAuthn/WebAuthnHelper.php
// Implementación autónoma de WebAuthn / Passkeys para PHP 8+ y MySQL sin dependencias externas.

class WebAuthnHelper {
    public static function getRpId(): string {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        // Quitar el puerto si existe
        $parts = explode(':', $host);
        return $parts[0];
    }

    public static function getRpName(): string {
        return "E-PALE - CUCEA";
    }

    public static function base64url_encode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function base64url_decode(string $data): string {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }

    public static function generateChallenge(int $length = 32): string {
        return self::base64url_encode(random_bytes($length));
    }

    /**
     * Procesa la respuesta de registro de WebAuthn y extrae:
     * - credentialId (base64url)
     * - publicKeyPem (formato PEM para openssl_verify)
     * - signCount
     */
    public static function parseRegistrationResponse(string $clientDataJsonB64, string $attestationObjectB64, string $expectedChallenge): array {
        $clientDataRaw = self::base64url_decode($clientDataJsonB64);
        $clientData = json_decode($clientDataRaw, true);

        if (!$clientData) {
            throw new Exception("clientDataJSON inválido.");
        }

        if (($clientData['type'] ?? '') !== 'webauthn.create') {
            throw new Exception("Tipo de operación inválida: se esperaba webauthn.create");
        }

        if (!hash_equals($expectedChallenge, $clientData['challenge'] ?? '')) {
            throw new Exception("El reto (challenge) de registro no coincide o ha expirado.");
        }

        // Decodificar attestationObject (CBOR)
        $attestationRaw = self::base64url_decode($attestationObjectB64);
        $attestation = self::decodeCbor($attestationRaw);

        if (!isset($attestation['authData'])) {
            throw new Exception("El objeto de atestación no contiene authData.");
        }

        $authData = $attestation['authData'];
        if (strlen($authData) < 37) {
            throw new Exception("authData tiene una longitud menor a la esperada.");
        }

        // Extraer componentes de authData
        $rpIdHash = substr($authData, 0, 32);
        $flags = ord($authData[32]);
        $signCount = unpack('N', substr($authData, 33, 4))[1];

        // Verificar bandera AT (Attested credential data present - bit 6)
        if (($flags & 0x40) === 0) {
            throw new Exception("No se incluyeron datos de credencial en el registro.");
        }

        // Datos de la credencial
        // 16 bytes AAGUID (offset 37..52)
        // 2 bytes Credential ID Length (offset 53..54)
        $credIdLen = unpack('n', substr($authData, 53, 2))[1];
        $credentialIdBinary = substr($authData, 55, $credIdLen);
        $credentialId = self::base64url_encode($credentialIdBinary);

        // Clave pública COSE a partir del offset 55 + credIdLen
        $coseKeyBinary = substr($authData, 55 + $credIdLen);
        $coseOffset = 0;
        $coseKey = self::decodeCbor($coseKeyBinary, $coseOffset);

        $publicKeyPem = self::coseKeyToPem($coseKey);

        return [
            'credentialId' => $credentialId,
            'publicKeyPem' => $publicKeyPem,
            'signCount'    => $signCount
        ];
    }

    /**
     * Valida la aserción de autenticación (firma de login)
     */
    public static function verifyAssertion(
        string $clientDataJsonB64,
        string $authenticatorDataB64,
        string $signatureB64,
        string $publicKeyPem,
        string $expectedChallenge,
        int $storedSignCount = 0
    ): array {
        $clientDataRaw = self::base64url_decode($clientDataJsonB64);
        $clientData = json_decode($clientDataRaw, true);

        if (!$clientData) {
            throw new Exception("clientDataJSON inválido.");
        }

        if (($clientData['type'] ?? '') !== 'webauthn.get') {
            throw new Exception("Tipo de operación inválida: se esperaba webauthn.get");
        }

        if (!hash_equals($expectedChallenge, $clientData['challenge'] ?? '')) {
            throw new Exception("El reto (challenge) de inicio de sesión no coincide o ha expirado.");
        }

        $authData = self::base64url_decode($authenticatorDataB64);
        if (strlen($authData) < 37) {
            throw new Exception("authenticatorData es demasiado corto.");
        }

        $flags = ord($authData[32]);
        // Bit 0: User Present (UP)
        if (($flags & 0x01) === 0) {
            throw new Exception("Presencia de usuario no confirmada.");
        }

        $signCount = unpack('N', substr($authData, 33, 4))[1];
        if ($storedSignCount > 0 && $signCount > 0 && $signCount <= $storedSignCount) {
            // Posible clonación de credencial o replay attack
            throw new Exception("Alerta de seguridad: Contador de firma anómalo.");
        }

        // Datos a verificar: authenticatorData + SHA256(clientDataJSON)
        $clientDataHash = hash('sha256', $clientDataRaw, true);
        $signedData = $authData . $clientDataHash;

        $signature = self::base64url_decode($signatureB64);

        $result = openssl_verify($signedData, $signature, $publicKeyPem, OPENSSL_ALGO_SHA256);
        if ($result !== 1) {
            throw new Exception("Firma biométrica no válida o clave alterada.");
        }

        return [
            'verified'  => true,
            'signCount' => $signCount
        ];
    }

    /**
     * Convierte una clave pública COSE (ES256 o RS256) a formato PEM
     */
    public static function coseKeyToPem(array $cose): string {
        $kty = $cose[1] ?? null; // 2 = EC2, 3 = RSA

        if ($kty === 2) {
            // Curva P-256 (alg -7 = ES256)
            $crv = $cose[-1] ?? null;
            $x = $cose[-2] ?? null;
            $y = $cose[-3] ?? null;

            if ($crv !== 1 || strlen($x) !== 32 || strlen($y) !== 32) {
                throw new Exception("Parámetros de curva elíptica P-256 inválidos.");
            }

            // SubjectPublicKeyInfo DER para prime256v1:
            // 30 59 30 13 06 07 2a 86 48 ce 3d 02 01 06 08 2a 86 48 ce 3d 03 01 07 03 42 00 04
            $derHeader = hex2bin('3059301306072a8648ce3d020106082a8648ce3d03010703420004');
            $der = $derHeader . $x . $y;

            return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
        } elseif ($kty === 3) {
            // RSA (alg -257 = RS256)
            $n = $cose[-1] ?? null;
            $e = $cose[-2] ?? null;

            if (!$n || !$e) {
                throw new Exception("Parámetros RSA inválidos.");
            }

            return self::buildRsaPem($n, $e);
        }

        throw new Exception("Tipo de clave COSE no soportada (kty=$kty).");
    }

    private static function derLength(int $len): string {
        if ($len < 128) return chr($len);
        $bytes = '';
        while ($len > 0) {
            $bytes = chr($len & 0xFF) . $bytes;
            $len >>= 8;
        }
        return chr(0x80 | strlen($bytes)) . $bytes;
    }

    private static function derInteger(string $bytes): string {
        if (ord($bytes[0]) > 0x7F) {
            $bytes = "\x00" . $bytes;
        }
        return "\x02" . self::derLength(strlen($bytes)) . $bytes;
    }

    private static function buildRsaPem(string $n, string $e): string {
        $nInt = self::derInteger($n);
        $eInt = self::derInteger($e);
        $rsaKeySeq = "\x30" . self::derLength(strlen($nInt) + strlen($eInt)) . $nInt . $eInt;

        $algoSeq = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00";
        $bitString = "\x03" . self::derLength(strlen($rsaKeySeq) + 1) . "\x00" . $rsaKeySeq;
        $spki = "\x30" . self::derLength(strlen($algoSeq) + strlen($bitString)) . $algoSeq . $bitString;

        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    /**
     * Decodificador ligero y seguro de CBOR
     */
    public static function decodeCbor(string $data, int &$offset = 0) {
        if ($offset >= strlen($data)) {
            throw new Exception("Fin inesperado de datos CBOR.");
        }
        $byte = ord($data[$offset++]);
        $majorType = $byte >> 5;
        $info = $byte & 0x1F;

        $length = self::readCborLength($data, $info, $offset);

        switch ($majorType) {
            case 0: // Unsigned integer
                return $length;
            case 1: // Negative integer
                return -1 - $length;
            case 2: // Byte string
                $str = substr($data, $offset, $length);
                $offset += $length;
                return $str;
            case 3: // UTF-8 text string
                $str = substr($data, $offset, $length);
                $offset += $length;
                return $str;
            case 4: // Array
                $arr = [];
                for ($i = 0; $i < $length; $i++) {
                    $arr[] = self::decodeCbor($data, $offset);
                }
                return $arr;
            case 5: // Map
                $map = [];
                for ($i = 0; $i < $length; $i++) {
                    $key = self::decodeCbor($data, $offset);
                    $val = self::decodeCbor($data, $offset);
                    $map[$key] = $val;
                }
                return $map;
            case 6: // Tag
                return self::decodeCbor($data, $offset);
            case 7: // Simple value
                if ($info === 20) return false;
                if ($info === 21) return true;
                if ($info === 22) return null;
                return $info;
            default:
                throw new Exception("Tipo mayor CBOR no soportado: $majorType");
        }
    }

    private static function readCborLength(string $data, int $info, int &$offset): int {
        if ($info < 24) {
            return $info;
        } elseif ($info === 24) {
            return ord($data[$offset++]);
        } elseif ($info === 25) {
            $val = unpack('n', substr($data, $offset, 2))[1];
            $offset += 2;
            return $val;
        } elseif ($info === 26) {
            $val = unpack('N', substr($data, $offset, 4))[1];
            $offset += 4;
            return $val;
        } elseif ($info === 27) {
            $val = unpack('J', substr($data, $offset, 8))[1];
            $offset += 8;
            return $val;
        }
        throw new Exception("Longitud indefinida CBOR no soportada.");
    }
}
