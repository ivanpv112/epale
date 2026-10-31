<?php
// recuperar_password.php - Flujo de recuperación de contraseña en 3 pasos
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña | E-PALE</title>

    <link rel="icon" type="image/png" href="img/isotipo-pale.png">
    <link rel="apple-touch-icon" href="img/isotipo-pale.png">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#001a57">

    <link rel="stylesheet" href="css/estilos.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="login-body">

    <header class="login-header">
        <img src="img/imagotipo-pale-login.png" alt="Logo E-PALE">
    </header>

    <div class="login-card">

        <!-- Mensaje de Alerta Global -->
        <div id="statusMessage" class="msg-box hidden">
            <i class="fas fa-info-circle"></i>
            <span class="msg-text"></span>
        </div>

        <!-- =========================================================
             PASO 1: SOLICITAR CÓDIGO (Ingreso de correo)
             ========================================================= -->
        <div id="step1" class="step-panel active">
            <h2>Recuperar Acceso</h2>
            <p class="recuperar-desc">
                Ingresa tu correo institucional y te enviaremos un código de seguridad para restablecer tu contraseña.
            </p>

            <form id="formStep1">
                <div class="form-group">
                    <label for="inputCorreo" class="label-dark">Correo Institucional</label>
                    <input type="email" id="inputCorreo" placeholder="usuario@cucea.udg.mx" required autocomplete="email">
                </div>

                <button type="submit" id="btnSubmitStep1" class="btn-login">
                    <span class="btn-text">Enviar Código</span>
                </button>
            </form>

            <div class="login-back">
                <a href="index">
                    <i class="fas fa-arrow-left"></i> Volver a Iniciar Sesión
                </a>
            </div>
        </div>

        <!-- =========================================================
             PASO 2: VERIFICAR CÓDIGO (6 Cajas individuales + Temporizador)
             ========================================================= -->
        <div id="step2" class="step-panel">
            <h2>Verificar Código</h2>
            <p class="recuperar-desc">
                Enviamos un código de seguridad de 6 caracteres a <strong id="lblCorreoMask"></strong>.
            </p>

            <form id="formStep2">
                <div class="otp-wrapper">
                    <label class="label-dark">
                        Código de 6 caracteres
                    </label>

                    <!-- 6 Cajas individuales sin placeholder -->
                    <div class="otp-container" id="otpContainer">
                        <input type="text" class="otp-box" maxlength="1" data-index="0" autocomplete="off" inputmode="text">
                        <input type="text" class="otp-box" maxlength="1" data-index="1" autocomplete="off" inputmode="text">
                        <input type="text" class="otp-box" maxlength="1" data-index="2" autocomplete="off" inputmode="text">
                        <input type="text" class="otp-box" maxlength="1" data-index="3" autocomplete="off" inputmode="text">
                        <input type="text" class="otp-box" maxlength="1" data-index="4" autocomplete="off" inputmode="text">
                        <input type="text" class="otp-box" maxlength="1" data-index="5" autocomplete="off" inputmode="text">
                    </div>
                </div>

                <!-- Temporizador dinámico -->
                <div id="timerContainer" class="timer-box">
                    <i class="far fa-clock"></i>
                    <span>El código expira en: <strong id="timerDisplay">05:00</strong></span>
                </div>

                <!-- Botón de reenvío (deshabilitado hasta expirar los 5 min) -->
                <button type="button" id="btnReenviar" class="btn-resend" disabled>
                    <i class="fas fa-redo"></i>
                    <span id="btnReenviarText">Reenviar código</span>
                </button>

                <button type="submit" id="btnSubmitStep2" class="btn-login">
                    <span class="btn-text">Verificar Código</span>
                </button>
            </form>

            <div class="login-back">
                <a href="#" id="btnVolverStep1">
                    <i class="fas fa-envelope"></i> Ingresar otro correo
                </a>
            </div>
        </div>

        <!-- =========================================================
             PASO 3: NUEVA CONTRASEÑA Y CONFIRMACIÓN
             ========================================================= -->
        <div id="step3" class="step-panel">
            <h2>Nueva Contraseña</h2>
            <p class="recuperar-desc">
                Crea una nueva contraseña para tu cuenta institucional.
            </p>

            <form id="formStep3">
                <div class="form-group">
                    <label for="inputPassword" id="lblNuevaPassword" class="label-dark">Nueva Contraseña</label>
                    <div class="password-field-wrapper">
                        <input type="password" id="inputPassword" placeholder="••••••••" required minlength="8" autocomplete="new-password">
                        <i class="fas fa-eye toggle-pwd-icon" onclick="togglePasswordVisibility('inputPassword', this)" title="Mostrar/Ocultar contraseña"></i>
                    </div>

                    <!-- Barra medidora de seguridad -->
                    <div class="strength-container">
                        <div class="strength-bar-track">
                            <div id="strengthBar" class="strength-bar-fill"></div>
                        </div>
                        <div class="strength-meta">
                            <span id="strengthText" class="strength-label-text"></span>
                            <span class="strength-requirements">Mín. 8 caracteres (mayús., minús., núm. y símbolo)</span>
                        </div>
                    </div>
                </div>

                <div class="form-group form-group-spaced">
                    <label for="inputConfirmPassword" id="lblConfirmPassword" class="label-dark">Confirmar Nueva Contraseña</label>
                    <div class="password-field-wrapper">
                        <input type="password" id="inputConfirmPassword" placeholder="••••••••" required minlength="8" autocomplete="new-password">
                        <i class="fas fa-eye toggle-pwd-icon" onclick="togglePasswordVisibility('inputConfirmPassword', this)" title="Mostrar/Ocultar contraseña"></i>
                    </div>
                </div>

                <button type="submit" id="btnSubmitStep3" class="btn-login">
                    <span class="btn-text">Guardar Nueva Contraseña</span>
                </button>
            </form>

            <div class="login-back">
                <a href="index">
                    <i class="fas fa-times"></i> Cancelar
                </a>
            </div>
        </div>

    </div>

    <footer class="login-footer">© <?php echo date('Y'); ?> E-PALE - Universidad de Guadalajara - CUCEA</footer>

    <script>
        // Estado de la aplicación
        let estado = {
            correo: '',
            correoEnmascarado: '',
            tiempoRestante: 300,
            intervaloTimer: null,
            intentosUsados: 0,
            intentosMaximos: 3,
            bloqueado: false
        };

        const statusMessage = document.getElementById('statusMessage');
        const msgText = statusMessage.querySelector('.msg-text');
        const iconMsg = statusMessage.querySelector('i');

        function mostrarMensaje(texto, tipo = 'error') {
            msgText.textContent = texto;
            statusMessage.className = `msg-box ${tipo}`;
            if (tipo === 'error') {
                iconMsg.className = 'fas fa-exclamation-circle';
            } else if (tipo === 'success') {
                iconMsg.className = 'fas fa-check-circle';
            } else {
                iconMsg.className = 'fas fa-info-circle';
            }
            statusMessage.classList.remove('hidden');
        }

        function ocultarMensaje() {
            statusMessage.classList.add('hidden');
        }

        function cambiarPaso(pasoNumero) {
            ocultarMensaje();
            document.querySelectorAll('.step-panel').forEach(p => p.classList.remove('active'));
            const objetivo = document.getElementById(`step${pasoNumero}`);
            if (objetivo) {
                objetivo.classList.add('active');
            }
        }

        function formatearTiempo(segundos) {
            const m = Math.floor(segundos / 60);
            const s = segundos % 60;
            return `${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
        }

        function iniciarTimer(segundosTotales) {
            clearInterval(estado.intervaloTimer);
            estado.tiempoRestante = segundosTotales;

            const timerContainer = document.getElementById('timerContainer');
            const timerDisplay = document.getElementById('timerDisplay');
            const btnReenviar = document.getElementById('btnReenviar');
            const btnReenviarText = document.getElementById('btnReenviarText');

            timerContainer.classList.remove('expired');
            btnReenviar.disabled = true;

            function actualizarVista() {
                timerDisplay.textContent = formatearTiempo(estado.tiempoRestante);

                if (estado.tiempoRestante <= 0) {
                    clearInterval(estado.intervaloTimer);
                    timerContainer.classList.add('expired');
                    timerDisplay.textContent = '00:00 (Expirado)';

                    if (!estado.bloqueado) {
                        btnReenviar.disabled = false;
                        btnReenviarText.textContent = 'Reenviar nuevo código';
                    }
                } else {
                    estado.tiempoRestante--;
                }
            }

            actualizarVista();
            estado.intervaloTimer = setInterval(actualizarVista, 1000);
        }

        // -------------------------------------------------------------
        // Utilidades para animación de botones (Carga y Confirmación)
        // -------------------------------------------------------------
        function animarBotonCarga(btn, textoCarga) {
            btn.classList.add('btn-loading');
            btn.disabled = true;
            btn.innerHTML = `<i class="fas fa-circle-notch custom-spinner"></i> <span>${textoCarga}</span>`;
        }

        function restaurarBoton(btn, textoOriginal) {
            btn.classList.remove('btn-loading', 'btn-confirmed');
            btn.disabled = false;
            btn.innerHTML = `<span class="btn-text">${textoOriginal}</span>`;
        }

        function animarBotonConfirmado(btn, textoExito) {
            btn.classList.remove('btn-loading');
            btn.classList.add('btn-confirmed');
            btn.innerHTML = `<i class="fas fa-check scale-bounce"></i> <span>${textoExito}</span>`;
        }

        // -------------------------------------------------------------
        // Control de las 6 Cajas Individuales (OTP)
        // -------------------------------------------------------------
        const otpBoxes = Array.from(document.querySelectorAll('.otp-box'));

        function obtenerCodigoOTP() {
            return otpBoxes.map(box => box.value.trim().toUpperCase()).join('');
        }

        function limpiarOTP() {
            otpBoxes.forEach(box => {
                box.value = '';
                box.classList.remove('filled');
            });
            if (otpBoxes[0]) otpBoxes[0].focus();
        }

        otpBoxes.forEach((box, idx) => {
            box.addEventListener('input', function(e) {
                const val = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
                this.value = val ? val.slice(-1) : '';

                if (this.value) {
                    this.classList.add('filled');
                    if (idx < otpBoxes.length - 1) {
                        otpBoxes[idx + 1].focus();
                    }
                } else {
                    this.classList.remove('filled');
                }

                // Si todas están llenas, enviar automáticamente el formulario
                if (obtenerCodigoOTP().length === 6) {
                    document.getElementById('formStep2').dispatchEvent(new Event('submit', { cancelable: true }));
                }
            });

            box.addEventListener('keydown', function(e) {
                if (e.key === 'Backspace') {
                    if (!this.value && idx > 0) {
                        otpBoxes[idx - 1].focus();
                        otpBoxes[idx - 1].value = '';
                        otpBoxes[idx - 1].classList.remove('filled');
                    } else {
                        this.value = '';
                        this.classList.remove('filled');
                    }
                } else if (e.key === 'ArrowLeft' && idx > 0) {
                    otpBoxes[idx - 1].focus();
                } else if (e.key === 'ArrowRight' && idx < otpBoxes.length - 1) {
                    otpBoxes[idx + 1].focus();
                }
            });

            box.addEventListener('paste', function(e) {
                e.preventDefault();
                const textoPegado = (e.clipboardData || window.clipboardData).getData('text');
                const caracteresLimpios = textoPegado.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 6);

                if (!caracteresLimpios) return;

                caracteresLimpios.split('').forEach((caracter, i) => {
                    if (otpBoxes[i]) {
                        otpBoxes[i].value = caracter;
                        otpBoxes[i].classList.add('filled');
                    }
                });

                const proximoIndice = Math.min(caracteresLimpios.length, otpBoxes.length - 1);
                otpBoxes[proximoIndice].focus();

                if (caracteresLimpios.length === 6) {
                    document.getElementById('formStep2').dispatchEvent(new Event('submit', { cancelable: true }));
                }
            });
        });

        // -------------------------------------------------------------
        // Manejador PASO 1: Solicitar Código
        // -------------------------------------------------------------
        document.getElementById('formStep1').addEventListener('submit', async function(e) {
            e.preventDefault();
            ocultarMensaje();

            const correo = document.getElementById('inputCorreo').value.trim();
            if (!correo) return;

            const btn = document.getElementById('btnSubmitStep1');
            animarBotonCarga(btn, 'Enviando código...');

            try {
                const res = await fetch('recuperar_process', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ accion: 'solicitar_codigo', correo: correo })
                });

                const data = await res.json();

                if (data.bloqueado) {
                    estado.bloqueado = true;
                    mostrarMensaje(data.message, 'error');
                    restaurarBoton(btn, 'Enviar Código');
                    return;
                }

                if (data.success) {
                    estado.correo = correo;
                    estado.correoEnmascarado = data.correo_enmascarado || correo;
                    estado.intentosUsados = data.intentos_usados || 1;
                    estado.intentosMaximos = data.intentos_maximos || 3;

                    document.getElementById('lblCorreoMask').textContent = estado.correoEnmascarado;

                    animarBotonConfirmado(btn, '¡Código enviado!');

                    setTimeout(() => {
                        cambiarPaso(2);
                        iniciarTimer(data.tiempo_segundos || 300);
                        limpiarOTP();
                        restaurarBoton(btn, 'Enviar Código');
                    }, 650);
                } else {
                    mostrarMensaje(data.message || 'Error al procesar la solicitud.', 'error');
                    restaurarBoton(btn, 'Enviar Código');
                }
            } catch (err) {
                console.error(err);
                mostrarMensaje('Error de conexión con el servidor. Intenta de nuevo.', 'error');
                restaurarBoton(btn, 'Enviar Código');
            }
        });

        // Reenviar código desde Paso 2
        document.getElementById('btnReenviar').addEventListener('click', async function() {
            if (!estado.correo) return;

            ocultarMensaje();
            const btn = this;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-circle-notch custom-spinner"></i> <span>Reenviando...</span>';

            try {
                const res = await fetch('recuperar_process', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ accion: 'solicitar_codigo', correo: estado.correo })
                });

                const data = await res.json();

                if (data.bloqueado) {
                    estado.bloqueado = true;
                    mostrarMensaje(data.message, 'error');
                    btn.innerHTML = '<i class="fas fa-ban"></i> <span>Bloqueado temporalmente</span>';
                    return;
                }

                if (data.success) {
                    mostrarMensaje('Hemos enviado un nuevo código de seguridad.', 'info');
                    iniciarTimer(data.tiempo_segundos || 300);
                    limpiarOTP();
                } else {
                    mostrarMensaje(data.message || 'No se pudo reenviar el código.', 'error');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-redo"></i> <span>Reintentar</span>';
                }
            } catch (err) {
                console.error(err);
                mostrarMensaje('Error de conexión.', 'error');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-redo"></i> Reintentar';
            }
        });

        // Volver de paso 2 a paso 1
        document.getElementById('btnVolverStep1').addEventListener('click', function(e) {
            e.preventDefault();
            clearInterval(estado.intervaloTimer);
            cambiarPaso(1);
            document.getElementById('inputCorreo').focus();
        });

        // -------------------------------------------------------------
        // Manejador PASO 2: Verificar Código (6 Cajas)
        // -------------------------------------------------------------
        document.getElementById('formStep2').addEventListener('submit', async function(e) {
            e.preventDefault();
            ocultarMensaje();

            const codigo = obtenerCodigoOTP();
            if (codigo.length !== 6) {
                mostrarMensaje('Por favor, ingresa los 6 caracteres del código.', 'error');
                const primerVacio = otpBoxes.find(b => !b.value.trim());
                if (primerVacio) primerVacio.focus();
                return;
            }

            const btn = document.getElementById('btnSubmitStep2');
            animarBotonCarga(btn, 'Verificando código...');

            try {
                const res = await fetch('recuperar_process', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        accion: 'verificar_codigo',
                        correo: estado.correo,
                        codigo: codigo
                    })
                });

                const data = await res.json();

                if (data.success) {
                    clearInterval(estado.intervaloTimer);
                    animarBotonConfirmado(btn, '¡Código correcto!');

                    setTimeout(() => {
                        cambiarPaso(3);
                        document.getElementById('inputPassword').focus();
                        restaurarBoton(btn, 'Verificar Código');
                    }, 650);
                } else {
                    mostrarMensaje(data.message || 'Código incorrecto o expirado.', 'error');
                    restaurarBoton(btn, 'Verificar Código');
                    if (data.expirado) {
                        limpiarOTP();
                    } else {
                        // Enfocar y seleccionar para corrección rápida
                        otpBoxes[otpBoxes.length - 1].focus();
                    }
                }
            } catch (err) {
                console.error(err);
                mostrarMensaje('Error de conexión al verificar el código.', 'error');
                restaurarBoton(btn, 'Verificar Código');
            }
        });

        // -------------------------------------------------------------
        // Validación en Tiempo Real: Nueva Contraseña y Confirmación
        // -------------------------------------------------------------
        const inputPassword = document.getElementById('inputPassword');
        const inputConfirmPassword = document.getElementById('inputConfirmPassword');
        const lblNuevaPassword = document.getElementById('lblNuevaPassword');
        const lblConfirmPassword = document.getElementById('lblConfirmPassword');
        const strengthBar = document.getElementById('strengthBar');
        const strengthText = document.getElementById('strengthText');

        // Evalúa lineamientos: 8 caracteres, mayúscula, minúscula, número y especial
        function evaluarFuerzaPassword(pass) {
            const minLen = pass.length >= 8;
            const hasLower = /[a-z]/.test(pass);
            const hasUpper = /[A-Z]/.test(pass);
            const hasNumber = /[0-9]/.test(pass);
            const hasSpecial = /[^A-Za-z0-9]/.test(pass);

            let puntuacion = 0;
            if (minLen) puntuacion++;
            if (hasLower) puntuacion++;
            if (hasUpper) puntuacion++;
            if (hasNumber) puntuacion++;
            if (hasSpecial) puntuacion++;

            return {
                valida: minLen && hasLower && hasUpper && hasNumber && hasSpecial,
                puntuacion: puntuacion,
                minLen: minLen
            };
        }

        function actualizarEstadoNuevaPassword() {
            const val = inputPassword.value;

            // Resetear clases previas
            inputPassword.classList.remove('input-valid', 'input-invalid', 'input-warning');
            lblNuevaPassword.classList.remove('is-valid', 'is-invalid', 'is-warning');
            strengthBar.className = 'strength-bar-fill';
            strengthText.className = 'strength-label-text';

            if (!val) {
                lblNuevaPassword.textContent = 'Nueva Contraseña';
                strengthBar.classList.add('strength-empty');
                strengthText.textContent = '';
                return;
            }

            const res = evaluarFuerzaPassword(val);

            if (res.valida) {
                // Totalmente Segura (Verde)
                inputPassword.classList.add('input-valid');
                lblNuevaPassword.classList.add('is-valid');
                lblNuevaPassword.textContent = 'Nueva Contraseña';
                strengthBar.classList.add('strength-strong');
                strengthText.classList.add('text-strong');
                strengthText.textContent = 'Segura ✓';
            } else if (res.minLen && res.puntuacion >= 3) {
                // Cumple longitud pero falta algún tipo de carácter (Amarillo)
                inputPassword.classList.add('input-warning');
                lblNuevaPassword.classList.add('is-warning');
                lblNuevaPassword.textContent = 'Contraseña Inválida';
                strengthBar.classList.add('strength-medium');
                strengthText.classList.add('text-medium');
                strengthText.textContent = 'Media (Falta mayús., minús., núm. o símbolo)';
            } else {
                // Menos de 8 caracteres o muy incompleta (Rojo)
                inputPassword.classList.add('input-invalid');
                lblNuevaPassword.classList.add('is-invalid');
                lblNuevaPassword.textContent = 'Contraseña Inválida';
                strengthBar.classList.add('strength-weak');
                strengthText.classList.add('text-weak');
                strengthText.textContent = val.length < 8 ? 'Insegura (Menos de 8 caracteres)' : 'Insegura';
            }

            // Si el campo de confirmación ya tenía texto, sincronizar validación
            if (inputConfirmPassword.value.length > 0) {
                validarConfirmacion();
            }
        }

        inputPassword.addEventListener('input', actualizarEstadoNuevaPassword);

        // Estado base para la casilla de Confirmar Contraseña
        function restablecerConfirmBase() {
            inputConfirmPassword.classList.remove('input-valid', 'input-invalid');
            lblConfirmPassword.classList.remove('is-valid', 'is-invalid');
            lblConfirmPassword.textContent = 'Confirmar Nueva Contraseña';
        }

        function validarConfirmacion() {
            const valConfirm = inputConfirmPassword.value;
            const valPass = inputPassword.value;

            if (!valConfirm) {
                restablecerConfirmBase();
                return;
            }

            if (valConfirm === valPass && valPass.length > 0) {
                // Coincide (Verde)
                inputConfirmPassword.classList.remove('input-invalid');
                inputConfirmPassword.classList.add('input-valid');
                lblConfirmPassword.classList.remove('is-invalid');
                lblConfirmPassword.classList.add('is-valid');
                lblConfirmPassword.textContent = 'Confirmar Nueva Contraseña';
            } else {
                // Diferente (Rojo)
                inputConfirmPassword.classList.remove('input-valid');
                inputConfirmPassword.classList.add('input-invalid');
                lblConfirmPassword.classList.remove('is-valid');
                lblConfirmPassword.classList.add('is-invalid');
                lblConfirmPassword.textContent = 'Contraseña Incorrecta';
            }
        }

        // Al presionar Backspace para borrar una letra, cambia de inmediato a su estado base
        inputConfirmPassword.addEventListener('keydown', function(e) {
            if (e.key === 'Backspace') {
                restablecerConfirmBase();
            }
        });

        // Al escribir o pegar
        inputConfirmPassword.addEventListener('input', function(e) {
            if (this.value.length === 0) {
                restablecerConfirmBase();
                return;
            }
            validarConfirmacion();
        });

        // -------------------------------------------------------------
        // Manejador PASO 3: Guardar Nueva Contraseña
        // -------------------------------------------------------------
        document.getElementById('formStep3').addEventListener('submit', async function(e) {
            e.preventDefault();
            ocultarMensaje();

            const p1 = inputPassword.value;
            const p2 = inputConfirmPassword.value;

            const resFuerza = evaluarFuerzaPassword(p1);
            if (!resFuerza.valida) {
                mostrarMensaje('La contraseña debe cumplir con todos los lineamientos de seguridad (8+ caracteres, mayúscula, minúscula, número y símbolo).', 'error');
                inputPassword.focus();
                return;
            }

            if (p1 !== p2) {
                mostrarMensaje('Las contraseñas no coinciden.', 'error');
                inputConfirmPassword.focus();
                return;
            }

            const btn = document.getElementById('btnSubmitStep3');
            animarBotonCarga(btn, 'Guardando contraseña...');

            try {
                const res = await fetch('recuperar_process', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        accion: 'cambiar_password',
                        password: p1,
                        confirm_password: p2
                    })
                });

                const data = await res.json();

                if (data.success) {
                    animarBotonConfirmado(btn, '¡Contraseña restablecida!');
                    mostrarMensaje('¡Contraseña actualizada con éxito! Redirigiendo al inicio de sesión...', 'success');

                    setTimeout(() => {
                        window.location.href = data.redirect || 'index';
                    }, 1200);
                } else {
                    mostrarMensaje(data.message || 'No se pudo actualizar la contraseña.', 'error');
                    restaurarBoton(btn, 'Guardar Nueva Contraseña');
                }
            } catch (err) {
                console.error(err);
                mostrarMensaje('Error de conexión al guardar la contraseña.', 'error');
                restaurarBoton(btn, 'Guardar Nueva Contraseña');
            }
        });

        // Mostrar / Ocultar Contraseña
        function togglePasswordVisibility(fieldId, iconElem) {
            const input = document.getElementById(fieldId);
            if (!input) return;

            if (input.type === 'password') {
                input.type = 'text';
                iconElem.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                iconElem.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }
    </script>
</body>

</html>
