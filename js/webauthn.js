/**
 * js/webauthn.js
 * Utilidad cliente para Autenticación Biométrica (Huella / Passkeys) en E-PALE.
 */

const WebAuthnClient = {
    /**
     * Resuelve la URL raíz del proyecto para asegurar que las llamadas AJAX
     * funcionen sin importar si estamos en la raíz o en una subcarpeta (profesor/, estudiante/, admin/).
     */
    getBaseUrl: function() {
        if (window.E_PALE_BASE_URL) {
            return window.E_PALE_BASE_URL.endsWith('/') ? window.E_PALE_BASE_URL : window.E_PALE_BASE_URL + '/';
        }

        const scripts = document.getElementsByTagName('script');
        for (let i = 0; i < scripts.length; i++) {
            const src = scripts[i].src || '';
            if (src.includes('webauthn.js')) {
                try {
                    const url = new URL(src);
                    const path = url.pathname;
                    const idx = path.lastIndexOf('/js/');
                    if (idx !== -1) {
                        return url.origin + path.substring(0, idx + 1);
                    }
                } catch (e) {}
            }
        }
        return '../';
    },

    /**
     * Parsea respuestas HTTP con errores detallados (404, 500, HTML o JSON)
     */
    parseServerError: async function(response, defaultMsg, url) {
        let msg = `${defaultMsg} (HTTP ${response.status} ${response.statusText || ''})`;
        try {
            const raw = await response.text();
            try {
                const data = JSON.parse(raw);
                if (data && (data.error || data.message)) {
                    return data.error || data.message;
                }
            } catch (_) {
                if (response.status === 404) {
                    return `Archivo no encontrado en el servidor (HTTP 404): ${url}. Asegúrate de haber subido los archivos del módulo al hosting.`;
                } else if (response.status === 500) {
                    return `Error interno del servidor (HTTP 500) al consultar ${url}. Revisa el log de errores de PHP en tu cPanel.`;
                } else if (response.status === 403) {
                    return `Acceso bloqueado por el servidor o firewall (HTTP 403 Forbidden).`;
                } else if (raw && raw.length < 160) {
                    return `${defaultMsg}: ${raw.trim()}`;
                }
            }
        } catch (_) {}
        return msg;
    },

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
        const baseUrl = this.getBaseUrl();
        const challengeUrl = baseUrl + 'api/webauthn/register_challenge.php';

        // 1. Solicitar el reto al servidor
        const challengeRes = await fetch(challengeUrl, {
            method: 'GET',
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin'
        });

        if (!challengeRes.ok) {
            const errMsg = await this.parseServerError(challengeRes, 'Error al obtener reto de registro', challengeUrl);
            throw new Error(errMsg);
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

        const verifyUrl = baseUrl + 'api/webauthn/register_verify.php';
        const verifyRes = await fetch(verifyUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(verifyPayload)
        });

        if (!verifyRes.ok) {
            const errMsg = await this.parseServerError(verifyRes, 'Error al verificar la credencial', verifyUrl);
            throw new Error(errMsg);
        }

        const result = await verifyRes.json();
        if (!result.success) {
            throw new Error(result.error || 'Error al guardar la credencial.');
        }

        return result;
    },

    /**
     * Inicia sesión utilizando la huella dactilar o passkey
     */
    login: async function(usuarioOpcional = '') {
        const baseUrl = this.getBaseUrl();
        const challengeUrl = baseUrl + 'api/webauthn/login_challenge.php';

        // 1. Solicitar el reto de inicio de sesión
        const challengeRes = await fetch(challengeUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ usuario: usuarioOpcional })
        });

        if (!challengeRes.ok) {
            const errMsg = await this.parseServerError(challengeRes, 'Error al conectar con el servidor', challengeUrl);
            throw new Error(errMsg);
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

        const verifyUrl = baseUrl + 'api/webauthn/login_verify.php';
        const verifyRes = await fetch(verifyUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(verifyPayload)
        });

        if (!verifyRes.ok) {
            const errMsg = await this.parseServerError(verifyRes, 'Error al validar huella dactilar', verifyUrl);
            throw new Error(errMsg);
        }

        const result = await verifyRes.json();
        return result;
    }
};
