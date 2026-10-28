<?php
// componente_biometrico.php
// Componente reutilizable para administrar credenciales biométricas (Huella / Passkeys) en los perfiles.

if (!isset($_SESSION['user_id'])) {
    return;
}

require_once __DIR__ . '/db.php';

$userId = (int)$_SESSION['user_id'];
$stmtBio = $pdo->prepare("
    SELECT id, nombre_dispositivo, fecha_registro, ultimo_uso
    FROM usuarios_credenciales_biometricas
    WHERE usuario_id = ?
    ORDER BY id DESC
");
$stmtBio->execute([$userId]);
$dispositivosBio = $stmtBio->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="bio-card-wrapper" id="seccionBiometria">
    <div class="bio-header">
        <div>
            <h3><i class="fas fa-fingerprint bio-title-icon"></i> Acceso Biométrico y Llaves de Seguridad</h3>
            <p class="bio-subtitle">
                Inicia sesión rápidamente usando la huella dactilar de este equipo (Windows Hello, Touch ID o sensor de huella).
            </p>
        </div>
        <button type="button" class="btn-add-bio" id="btnRegistrarDispositivoBio">
            <i class="fas fa-plus"></i> Vincular este dispositivo
        </button>
    </div>

    <div id="bioAlert" class="error-msg hidden" style="text-align: left; margin-bottom: 15px;"></div>

    <div id="bioSupportNotice" class="bio-notice-warning hidden">
        <i class="fas fa-info-circle"></i> Tu navegador o equipo actual no cuenta con sensor biométrico compatible con WebAuthn.
    </div>

    <ul class="bio-device-list" id="listaDispositivosBio">
        <?php if (empty($dispositivosBio)): ?>
            <li id="emptyBioMsg" class="bio-empty-msg">
                <i class="fas fa-shield-alt bio-empty-icon"></i>
                <span>Aún no tienes dispositivos biométricos vinculados. Haz clic en "Vincular este dispositivo" para activar el acceso con huella.</span>
            </li>
        <?php else: ?>
            <?php foreach ($dispositivosBio as $d): ?>
                <li class="bio-device-item" id="bioItem_<?php echo $d['id']; ?>">
                    <div class="bio-device-info">
                        <div class="bio-device-icon">
                            <i class="fas fa-laptop"></i>
                        </div>
                        <div class="bio-device-meta">
                            <span class="bio-device-name"><?php echo htmlspecialchars($d['nombre_dispositivo']); ?></span>
                            <div class="bio-device-dates">
                                <span class="bio-date-pill">
                                    <i class="far fa-calendar-check"></i> <?php echo date('d/m/Y H:i', strtotime($d['fecha_registro'])); ?>
                                </span>
                                <?php if (!empty($d['ultimo_uso'])): ?>
                                    <span class="bio-date-pill bio-date-last">
                                        <i class="fas fa-history"></i> Uso: <?php echo date('d/m/Y H:i', strtotime($d['ultimo_uso'])); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="bio-date-pill bio-date-unused">
                                        <i class="far fa-clock"></i> Sin usar aún
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn-delete-bio" onclick="eliminarDispositivoBio(<?php echo $d['id']; ?>, <?php echo htmlspecialchars(json_encode($d['nombre_dispositivo']), ENT_QUOTES, 'UTF-8'); ?>)" title="Eliminar dispositivo">
                        <i class="fas fa-trash-alt"></i> <span class="btn-text">Eliminar</span>
                    </button>
                </li>
            <?php endforeach; ?>
        <?php endif; ?>
    </ul>
</div>

<!-- Modal Personalizado para Registrar Dispositivo Biométrico -->
<div id="modalRegistrarBio" class="modal-overlay">
    <div class="modal-content bio-modal-content">
        <div class="bio-modal-header">
            <h3 class="bio-modal-title">
                <i class="fas fa-fingerprint bio-modal-header-icon"></i> Vincular Dispositivo
            </h3>
        </div>
        <form id="formRegistrarBio" onsubmit="confirmarRegistroBio(event)">
            <div class="bio-modal-body">
                <div class="bio-modal-intro">
                    <div class="bio-modal-fingerprint-anim">
                        <i class="fas fa-fingerprint"></i>
                    </div>
                    <p>
                        Asigna un nombre descriptivo para identificar este lector biométrico o llave de seguridad en tu cuenta.
                    </p>
                </div>

                <div class="bio-modal-field">
                    <label for="inputNombreDispositivoBio">Nombre del Dispositivo</label>
                    <input type="text" id="inputNombreDispositivoBio" class="form-control" maxlength="60" required placeholder="Ej. Mi Laptop de Trabajo, Teléfono Personal">
                    <small class="bio-modal-hint">
                        <i class="fas fa-info-circle"></i> Detectamos que estás en este equipo. Puedes cambiar el nombre sugerido si lo deseas.
                    </small>
                </div>

                <div id="bioModalNotice" class="bio-modal-status hidden">
                    <i class="fas fa-spinner fa-spin"></i>
                    <span>Toca el sensor biométrico de tu equipo o introduce tu PIN de seguridad...</span>
                </div>
            </div>
            <div class="bio-modal-footer">
                <button type="button" class="btn-cancel" onclick="cerrarModalRegistrarBio()">Cancelar</button>
                <button type="submit" class="btn-save" id="btnConfirmarRegistroBio">
                    <i class="fas fa-check"></i> Continuar
                </button>
            </div>
        </form>
    </div>
</div>

<script src="../js/webauthn.js"></script>
<script>
let sugerenciaDispositivoGlobal = 'Mi Laptop / Equipo';

function obtenerSugerenciaDispositivo() {
    if (/iPhone|iPad/i.test(navigator.userAgent)) return 'Apple Touch/Face ID';
    if (/Android/i.test(navigator.userAgent)) return 'Teléfono Android';
    if (/Windows/i.test(navigator.userAgent)) return 'Windows Hello (Laptop/PC)';
    if (/Macintosh/i.test(navigator.userAgent)) return 'Mac Touch ID';
    return 'Mi Dispositivo';
}

function abrirModalRegistrarBio() {
    const modal = document.getElementById('modalRegistrarBio');
    const input = document.getElementById('inputNombreDispositivoBio');
    const notice = document.getElementById('bioModalNotice');
    const btnSubmit = document.getElementById('btnConfirmarRegistroBio');

    sugerenciaDispositivoGlobal = obtenerSugerenciaDispositivo();
    if (input) {
        input.value = sugerenciaDispositivoGlobal;
        input.disabled = false;
    }
    if (notice) notice.classList.add('hidden');
    if (btnSubmit) {
        btnSubmit.disabled = false;
        btnSubmit.innerHTML = '<i class="fas fa-check"></i> Continuar';
    }

    if (modal) {
        modal.style.display = 'flex';
        setTimeout(() => { if (input) { input.focus(); input.select(); } }, 100);
    }
}

function cerrarModalRegistrarBio() {
    const modal = document.getElementById('modalRegistrarBio');
    if (modal) modal.style.display = 'none';
}

async function confirmarRegistroBio(e) {
    e.preventDefault();
    const input = document.getElementById('inputNombreDispositivoBio');
    const notice = document.getElementById('bioModalNotice');
    const btnSubmit = document.getElementById('btnConfirmarRegistroBio');
    const alertBox = document.getElementById('bioAlert');

    const nombreFinal = (input && input.value.trim()) ? input.value.trim() : sugerenciaDispositivoGlobal;

    if (btnSubmit) {
        btnSubmit.disabled = true;
        btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Esperando sensor...';
    }
    if (input) input.disabled = true;
    if (notice) notice.classList.remove('hidden');

    try {
        const res = await WebAuthnClient.registerDevice(nombreFinal);
        cerrarModalRegistrarBio();

        if (alertBox) {
            alertBox.className = 'alert alert-success';
            alertBox.style.display = 'block';
            alertBox.innerHTML = '<i class="fas fa-check-circle"></i> ' + (res.message || 'Dispositivo vinculado con éxito.');
            alertBox.classList.remove('hidden');
        }

        setTimeout(() => {
            window.location.reload();
        }, 900);
    } catch (err) {
        if (notice) notice.classList.add('hidden');
        if (input) input.disabled = false;
        if (btnSubmit) {
            btnSubmit.disabled = false;
            btnSubmit.innerHTML = '<i class="fas fa-check"></i> Reintentar';
        }

        if (alertBox) {
            alertBox.className = 'error-msg';
            alertBox.style.display = 'block';
            alertBox.innerHTML = '<i class="fas fa-exclamation-triangle"></i> ' + err.message;
            alertBox.classList.remove('hidden');
        }
        cerrarModalRegistrarBio();
    }
}

document.addEventListener('DOMContentLoaded', async () => {
    const btnAdd = document.getElementById('btnRegistrarDispositivoBio');
    const notice = document.getElementById('bioSupportNotice');

    // Verificar si el equipo soporta WebAuthn
    const supported = await WebAuthnClient.isSupported();
    if (!supported) {
        if (btnAdd) btnAdd.disabled = true;
        if (notice) notice.classList.remove('hidden');
    }

    if (btnAdd) {
        btnAdd.addEventListener('click', () => {
            const alertBox = document.getElementById('bioAlert');
            if (alertBox) alertBox.classList.add('hidden');
            abrirModalRegistrarBio();
        });
    }

    // Cerrar modal al hacer clic fuera del contenido
    const modal = document.getElementById('modalRegistrarBio');
    if (modal) {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                cerrarModalRegistrarBio();
            }
        });
    }
});

async function eliminarDispositivoBio(id, nombreDispositivo) {
    const nombre = nombreDispositivo || 'este dispositivo';
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark' || document.body.classList.contains('dark-theme');

    if (typeof Swal !== 'undefined') {
        const result = await Swal.fire({
            title: '¿Eliminar Dispositivo?',
            text: `Se desvinculará "${nombre}". Ya no podrás iniciar sesión con este lector biométrico.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-trash-alt"></i> Sí, eliminar',
            cancelButtonText: 'Cancelar',
            reverseButtons: true,
            backdrop: `rgba(0,0,123,0.4)`
        });

        if (!result.isConfirmed) {
            return;
        }
    } else {
        if (!confirm('¿Estás seguro de que deseas eliminar este acceso biométrico? Ya no podrás iniciar sesión con este lector.')) {
            return;
        }
    }

    try {
        const credUrl = (typeof WebAuthnClient !== 'undefined' ? WebAuthnClient.getBaseUrl() : '../') + 'api_webauthn_credentials.php';
        const res = await fetch(credUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ action: 'delete', id: id })
        });
        const data = await res.json();
        if (data.success) {
            const item = document.getElementById('bioItem_' + id);
            if (item) item.remove();
            
            const lista = document.getElementById('listaDispositivosBio');
            if (lista && lista.children.length === 0) {
                lista.innerHTML = `
                    <li id="emptyBioMsg" class="bio-empty-msg">
                        <i class="fas fa-shield-alt bio-empty-icon"></i>
                        <span>Aún no tienes dispositivos biométricos vinculados. Haz clic en "Vincular este dispositivo" para activar el acceso con huella.</span>
                    </li>
                `;
            }

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Dispositivo desvinculado con éxito',
                    showConfirmButton: false,
                    timer: 2800,
                    timerProgressBar: true,
                    background: isDark ? '#151e32' : '#ffffff',
                    color: isDark ? '#f8fafc' : '#1e293b'
                });
            }
        } else {
            const errMsg = data.error || 'No se pudo eliminar el dispositivo.';
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Error al eliminar',
                    text: errMsg,
                    confirmButtonColor: 'var(--udg-blue, #002b49)',
                    background: isDark ? '#151e32' : '#ffffff',
                    color: isDark ? '#f8fafc' : '#1e293b'
                });
            } else {
                alert(errMsg);
            }
        }
    } catch (e) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'error',
                title: 'Error de conexión',
                text: 'Ocurrió un error al procesar la solicitud.',
                confirmButtonColor: 'var(--udg-blue, #002b49)',
                background: isDark ? '#151e32' : '#ffffff',
                color: isDark ? '#f8fafc' : '#1e293b'
            });
        } else {
            alert('Error al procesar la solicitud.');
        }
    }
}
</script>
