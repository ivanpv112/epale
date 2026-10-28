/**
 * js/webauthn.js
 * Utilidad cliente para Autenticación Biométrica (Huella / Passkeys) en E-PALE.
 */

const WebAuthnClient = {
    /**
     * Verifica si el navegador y el dispositivo soportan WebAuthn
     */
    isSupported: async function() {
        if (!window.PublicKeyCredential) {
            return false;
        }
        try {
            if (PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable) {
                return await PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable();
            }
            return true;
        } catch (e) {
            return false;
        }
    },

    /**
     * Convierte ArrayBuffer a Base64URL
     */
    bufferToBase64url: function(buffer) {
        const bytes = new Uint8Array(buffer);
        let binary = '';
        for (let i = 0; i < bytes.byteLength; i++) {
            binary += String.fromCharCode(bytes[i]);
        }
        return btoa(binary)
            .replace(/\+/g, '-')
            .replace(/\//g, '_')
            .replace(/=+$/, '');
    },

    /**
     * Convierte Base64URL a Uint8Array
     */
    base64urlToBuffer: function(base64url) {
        let base64 = base64url.replace(/-/g, '+').replace(/_/g, '/');
        while (base64.length % 4) {
            base64 += '=';
        }
        const binary = atob(base64);
        const bytes = new Uint8Array(binary.length);
        for (let i = 0; i < binary.length; i++) {
            bytes[i] = binary.charCodeAt(i);
        }
        return bytes.buffer;
    },

    /**
     * Inicia el registro de una nueva huella / dispositivo para el usuario en sesión
     */
    registerDevice: async function(nombreDispositivo = 'Este dispositivo') {
        // 1. Solicitar el reto al servidor
        const challengeRes = await fetch('../api_webauthn_register_challenge.php', {
            method: 'GET',
            headers: { 'Accept': 'application/json' }
        });

        if (!challengeRes.ok) {
            const err = await challengeRes.json().catch(() => ({}));
            throw new Error(err.error || 'Error al obtener reto de registro.');
        }

        const data = await challengeRes.json();
        if (!data.success) {
            throw new Error(data.error || 'No se pudo iniciar el registro biométrico.');
        }

        const options = data.options;
        options.challenge = this.base64urlToBuffer(options.challenge);
        options.user.id = this.base64urlToBuffer(options.user.id);

        if (options.excludeCredentials && options.excludeCredentials.length > 0) {
            options.excludeCredentials = options.excludeCredentials.map(cred => ({
                type: cred.type,
                id: this.base64urlToBuffer(cred.id)
            }));
        }

        // 2. Solicitar al navegador la interacción biométrica
        let credential;
        try {
            credential = await navigator.credentials.create({ publicKey: options });
        } catch (e) {
            if (e.name === 'NotAllowedError') {
                throw new Error('Registro cancelado o expirado. Vuelve a intentarlo tocando el sensor.');
            } else if (e.name === 'InvalidStateError') {
                throw new Error('Este dispositivo ya está vinculado a tu cuenta.');
            }
            throw new Error('No se pudo completar el registro: ' + e.message);
        }

        if (!credential) {
            throw new Error('No se generó la credencial biométrica.');
        }

        // 3. Enviar la clave pública generada al servidor
        const verifyPayload = {
            clientDataJSON: this.bufferToBase64url(credential.response.clientDataJSON),
            attestationObject: this.bufferToBase64url(credential.response.attestationObject),
            nombre_dispositivo: nombreDispositivo
        };

        const verifyRes = await fetch('../api_webauthn_register_verify.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(verifyPayload)
        });

        const result = await verifyRes.json();
        if (!verifyRes.ok || !result.success) {
            throw new Error(result.error || 'Error al verificar y guardar la credencial.');
        }

        return result;
    },

    /**
     * Inicia sesión utilizando la huella dactilar o passkey
     */
    login: async function(usuarioOpcional = '') {
        // 1. Solicitar el reto de inicio de sesión
        const challengeRes = await fetch('api_webauthn_login_challenge.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ usuario: usuarioOpcional })
        });

        if (!challengeRes.ok) {
            throw new Error('Error al conectar con el servidor.');
        }

        const data = await challengeRes.json();
        if (!data.success) {
            throw new Error(data.error || 'No se pudo iniciar la autenticación biométrica.');
        }

        const options = data.options;
        options.challenge = this.base64urlToBuffer(options.challenge);

        if (options.allowCredentials && options.allowCredentials.length > 0) {
            options.allowCredentials = options.allowCredentials.map(cred => ({
                type: cred.type,
                id: this.base64urlToBuffer(cred.id)
            }));
        }

        // 2. Pedir huella dactilar / Touch ID / Windows Hello
        let assertion;
        try {
            assertion = await navigator.credentials.get({ publicKey: options });
        } catch (e) {
            if (e.name === 'NotAllowedError') {
                throw new Error('Operación cancelada por el usuario o tiempo agotado.');
            }
            throw new Error('Error en el sensor biométrico: ' + e.message);
        }

        if (!assertion) {
            throw new Error('No se recibió la validación biométrica.');
        }

        // 3. Enviar la firma generada al servidor
        const verifyPayload = {
            id: assertion.id,
            clientDataJSON: this.bufferToBase64url(assertion.response.clientDataJSON),
            authenticatorData: this.bufferToBase64url(assertion.response.authenticatorData),
            signature: this.bufferToBase64url(assertion.response.signature),
            userHandle: assertion.response.userHandle ? this.bufferToBase64url(assertion.response.userHandle) : null
        };

        const verifyRes = await fetch('api_webauthn_login_verify.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(verifyPayload)
        });

        const result = await verifyRes.json();
        return result;
    }
};
