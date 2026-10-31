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
$tieneLlaves = !empty($dispositivosBio);
?>

<div class="bio-card-wrapper" id="seccionBiometria">
    <div class="bio-header">
        <div class="bio-header-text">
            <h3><i class="fas fa-fingerprint bio-title-icon"></i> Llaves de Acceso y Huella Dactilar (Passkeys)</h3>
            <p class="bio-subtitle">
                Inicia sesión al instante con tu huella dactilar, Face ID o PIN. Al vincular tu cuenta una sola vez, se sincroniza automáticamente con tus demás dispositivos (Google / Apple).
            </p>
        </div>
        <button type="button" class="btn-add-bio" id="btnRegistrarDispositivoBio">
            <i class="fas fa-key"></i> <?php echo $tieneLlaves ? 'Añadir otra llave' : 'Activar llave de acceso'; ?>
        </button>
    </div>

    <!-- Tip explicativo de sincronización multi-dispositivo -->
    <div class="bio-sync-tip">
        <div class="bio-sync-tip-icon">
            <i class="fas fa-sync-alt"></i>
        </div>
        <div class="bio-sync-tip-text">
            <strong>Sincronización multidispositivo automática:</strong>
            Si usas Google Password Manager o Llavero de iCloud, esta llave se comparte entre tu teléfono, tablet y computadora sin necesidad de registrarlos uno por uno.
        </div>
    </div>

    <div id="bioAlert" class="error-msg hidden" style="text-align: left; margin-bottom: 15px;"></div>

    <div id="bioSupportNotice" class="bio-notice-warning hidden">
        <i class="fas fa-info-circle"></i> Tu navegador o equipo actual no cuenta con sensor biométrico compatible con WebAuthn.
    </div>

    <ul class="bio-device-list" id="listaDispositivosBio">
        <?php if (!$tieneLlaves): ?>
            <li id="emptyBioMsg" class="bio-empty-msg">
                <i class="fas fa-shield-alt bio-empty-icon"></i>
                <strong>Aún no has activado tu llave de acceso biométrica.</strong>
                <span style="display:block; margin-top:4px;">Haz clic en <em>"Activar llave de acceso"</em> para entrar en segundos sin escribir tu contraseña.</span>
            </li>
        <?php else: ?>
            <?php foreach ($dispositivosBio as $d): ?>
                <li class="bio-device-item" id="bioItem_<?php echo $d['id']; ?>">
                    <div class="bio-device-info">
                        <div class="bio-device-icon">
                            <i class="fas fa-fingerprint"></i>
                        </div>
                        <div class="bio-device-meta">
                            <span class="bio-device-name"><?php echo htmlspecialchars($d['nombre_dispositivo']); ?></span>
                            <div class="bio-device-dates">
                                <span class="bio-date-pill">
                                    <i class="far fa-calendar-check"></i> Activada: <?php echo date('d/m/Y H:i', strtotime($d['fecha_registro'])); ?>
                                </span>
                                <?php if (!empty($d['ultimo_uso'])): ?>
                                    <span class="bio-date-pill bio-date-last">
                                        <i class="fas fa-history"></i> Último uso: <?php echo date('d/m/Y H:i', strtotime($d['ultimo_uso'])); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="bio-date-pill bio-date-unused">
                                        <i class="far fa-clock"></i> Lista para usarse
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn-delete-bio" onclick="eliminarDispositivoBio(<?php echo $d['id']; ?>, <?php echo htmlspecialchars(json_encode($d['nombre_dispositivo']), ENT_QUOTES, 'UTF-8'); ?>)" title="Eliminar llave de acceso">
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
                <i class="fas fa-key bio-modal-header-icon"></i> Activar Llave de Acceso
            </h3>
        </div>
        <form id="formRegistrarBio" onsubmit="confirmarRegistroBio(event)">
            <div class="bio-modal-body">
                <div class="bio-modal-intro">
                    <div class="bio-modal-fingerprint-anim">
                        <i class="fas fa-fingerprint"></i>
                    </div>
                    <p>
                        Asigna un nombre a esta llave para reconocerla (por ejemplo, el gestor o equipo desde donde la activas).
                    </p>
                </div>

                <div class="bio-modal-field">
                    <label for="inputNombreDispositivoBio">Nombre o descripción de la llave</label>
                    <input type="text" id="inputNombreDispositivoBio" class="form-control" maxlength="60" required placeholder="Ej. Mi Cuenta Google / Laptop">
                    <small class="bio-modal-hint">
                        <i class="fas fa-info-circle"></i> Sugerencia basada en este dispositivo. Puedes cambiarla si lo prefieres.
                    </small>
                </div>

                <div id="bioModalNotice" class="bio-modal-status hidden">
                    <i class="fas fa-spinner fa-spin"></i>
                    <span>Toca el sensor biométrico (huella / Face ID) o introduce tu PIN...</span>
                </div>
            </div>
            <div class="bio-modal-footer">
                <button type="button" class="btn-cancel" onclick="cerrarModalRegistrarBio()">Cancelar</button>
                <button type="submit" class="btn-save" id="btnConfirmarRegistroBio">
                    <i class="fas fa-fingerprint"></i> Vincular Huella
                </button>
            </div>
        </form>
    </div>
</div>

<script src="../js/webauthn.js"></script>
<script>
let sugerenciaDispositivoGlobal = 'Mi Llave de Acceso';

function obtenerSugerenciaDispositivo() {
    if (/iPhone|iPad/i.test(navigator.userAgent)) return 'Llavero iCloud (Apple)';
    if (/Android/i.test(navigator.userAgent)) return 'Google Password Manager (Android)';
    if (/Windows/i.test(navigator.userAgent)) return 'Windows Hello (Laptop/PC)';
    if (/Macintosh/i.test(navigator.userAgent)) return 'Mac Touch ID (Apple)';
    return 'Mi Llave de Acceso';
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
        btnSubmit.innerHTML = '<i class="fas fa-fingerprint"></i> Vincular Huella';
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

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'success',
                title: '¡Llave activada con éxito!',
                text: res.message || 'Ya puedes iniciar sesión con tu huella dactilar en este y tus demás dispositivos sincronizados.',
                confirmButtonColor: '#001a57',
                confirmButtonText: 'Entendido'
            }).then(() => {
                window.location.reload();
            });
        } else {
            alertBox.className = 'alert alert-success';
            alertBox.style.display = 'block';
            alertBox.innerHTML = '<i class="fas fa-check-circle"></i> ' + (res.message || 'Llave de acceso activada con éxito.');
            alertBox.classList.remove('hidden');
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        }
    } catch (err) {
        if (btnSubmit) {
            btnSubmit.disabled = false;
            btnSubmit.innerHTML = '<i class="fas fa-fingerprint"></i> Vincular Huella';
        }
        if (input) input.disabled = false;
        if (notice) notice.classList.add('hidden');

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'error',
                title: 'No se pudo activar la llave',
                text: err.message,
                confirmButtonColor: '#001a57',
                confirmButtonText: 'Aceptar'
            });
        } else {
            alertBox.className = 'error-msg';
            alertBox.style.display = 'block';
            alertBox.innerHTML = '<i class="fas fa-exclamation-triangle"></i> ' + err.message;
            alertBox.classList.remove('hidden');
        }
    }
}

document.addEventListener('DOMContentLoaded', async () => {
    const btnAdd = document.getElementById('btnRegistrarDispositivoBio');
    const notice = document.getElementById('bioSupportNotice');

    // Verificar si el equipo soporta WebAuthn
    const supported = await WebAuthnClient.isSupported();
    if (!supported) {
        if (btnAdd) {
            btnAdd.disabled = true;
            btnAdd.title = 'Tu navegador o dispositivo actual no cuenta con autenticación biométrica';
        }
        if (notice) notice.classList.remove('hidden');
    }

    if (btnAdd) {
        btnAdd.addEventListener('click', () => {
            abrirModalRegistrarBio();
        });
    }

    const modal = document.getElementById('modalRegistrarBio');
    if (modal) {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) cerrarModalRegistrarBio();
        });
    }
});

async function eliminarDispositivoBio(id, nombre) {
    const nombreMostrar = nombre || 'esta llave de acceso';

    if (typeof Swal !== 'undefined') {
        const result = await Swal.fire({
            title: '¿Eliminar llave de acceso?',
            html: `Dejarás de poder iniciar sesión con huella desde los dispositivos sincronizados con <b>${nombreMostrar}</b>.`,
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
        if (!confirm(`¿Estás seguro de que deseas eliminar ${nombreMostrar}? Ya no podrás iniciar sesión con esta llave.`)) {
            return;
        }
    }

    try {
        const credUrl = (typeof WebAuthnClient !== 'undefined' ? WebAuthnClient.getBaseUrl() : '../') + 'api/webauthn/credentials.php';
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
                        <strong>Aún no has activado tu llave de acceso biométrica.</strong>
                        <span style="display:block; margin-top:4px;">Haz clic en <em>"Activar llave de acceso"</em> para entrar en segundos sin escribir tu contraseña.</span>
                    </li>
                `;
            }

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'Eliminada',
                    text: 'La llave de acceso fue removida de tu cuenta.',
                    timer: 1800,
                    showConfirmButton: false
                });
            }
        } else {
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'error', title: 'Error', text: data.error || 'No se pudo eliminar la llave.' });
            } else {
                alert(data.error || 'No se pudo eliminar la llave.');
            }
        }
    } catch (e) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({ icon: 'error', title: 'Error de red', text: 'No se pudo comunicar con el servidor.' });
        } else {
            alert('Error al procesar la solicitud.');
        }
    }
}
</script>
