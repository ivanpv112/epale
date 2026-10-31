<?php
require_once 'oferta_academica_logic.php';

/**
 * Declaración de variables importadas desde oferta_academica_logic.php
 * para evitar falsos positivos de "Undefined variable" en el IDE.
 * 
 * @var string $nombre_ciclo
 * @var bool $altas_habilitadas
 * @var array $grupos
 * @var array $mis_nrcs
 * @var array $mis_solicitudes
 * @var array $mis_materias_inscritas
 * @var array $mis_materias_solicitadas
 * @var array $mis_materias_acreditadas
 * @var array $historial_altas
 * @var bool $hay_actualizacion_reciente_alta
 * @var string $csrf_token
 */
?>


<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Oferta Académica | E-Pale</title>
    <link rel="icon" type="image/png" href="../img/isotipo-pale.png">
    <link rel="apple-touch-icon" href="../img/isotipo-pale.png">
    <link rel="stylesheet" href="../css/estilos.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/estudiante.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

</head>

<body>

    <?php include 'menu_estudiante.php'; ?>

    <main class="main-content">
        <div class="oferta-header">
            <h1><i class="fas fa-globe"></i> Oferta Académica</h1>
            <p>Ciclo <?php echo htmlspecialchars($nombre_ciclo); ?></p>
        </div>

        <?php if (count($historial_altas) > 0): ?>
            <div style="display: flex; justify-content: flex-start; margin-bottom: 20px;">
                <button class="btn-historial" style="width: max-content;" onclick="document.getElementById('modalHistorialAltas').style.display='flex'">
                    <i class="fas fa-history"></i> Historial de Solicitudes
                    <?php if ($hay_actualizacion_reciente_alta): ?>
                        <i class="fas fa-circle" style="color: #dc3545; font-size: 0.6rem; animation: blink 2s infinite;"></i>
                    <?php endif; ?>
                </button>
                <style>
                    @keyframes blink {
                        0% {
                            opacity: 1;
                        }

                        50% {
                            opacity: 0;
                        }

                        100% {
                            opacity: 1;
                        }
                    }
                </style>
            </div>
        <?php endif; ?>

        <div class="search-container">
            <input type="text" id="searchInput" placeholder="Buscar por idioma, nivel, profesor o NRC..." onkeyup="filterCards()">
            <select id="langFilter" onchange="filterCards()">
                <option value="">Todos los idiomas</option>
                <?php
                $idiomas_unicos = [];
                foreach ($grupos as $g) {
                    $idiomas_unicos[$g['idioma']] = $g['idioma'];
                }
                foreach ($idiomas_unicos as $i) {
                    echo "<option value=\"" . htmlspecialchars($i) . "\">" . htmlspecialchars($i) . "</option>";
                }
                ?>
            </select>
        </div>

        <div class="grid-oferta" id="cardsGrid">
            <?php if (!$altas_habilitadas): ?>
                <div style="grid-column: 1 / -1; background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; font-weight: bold;">
                    <i class="fas fa-lock"></i> El periodo de altas está cerrado temporalmente. Las opciones de solicitar materia han sido deshabilitadas.
                </div>
            <?php endif; ?>
            <?php foreach ($grupos as $g): ?>
                <?php
                $lugares_disp = $g['cupo'] - $g['inscritos'];
                if ($lugares_disp < 0) $lugares_disp = 0;

                $porcentaje_disp = $g['cupo'] > 0 ? ($lugares_disp / $g['cupo']) * 100 : 0;

                $badge_class = 'badge-verde';
                $badge_text = "$lugares_disp lugares";
                if ($porcentaje_disp <= 0) {
                    $badge_class = 'badge-rojo';
                    $badge_text = "Lleno";
                } elseif ($porcentaje_disp < 20) {
                    $badge_class = 'badge-rojo';
                } elseif ($porcentaje_disp < 40) {
                    $badge_class = 'badge-amarillo';
                }

                $horas_txt = "";

                // Texto horario Presencial
                if ($g['horario_presencial']) {
                    $hp = $g['horario_presencial'];
                    $horas_txt .= "<p style='margin-bottom:8px;'><i class=\"fas fa-building\" style=\"color:#ffc107; width:20px;\"></i> {$hp['dias']} • {$hp['inicio']} - {$hp['fin']} <br><i class=\"fas fa-map-marker-alt\" style=\"color:#ffc107; width:20px;\"></i> {$hp['aula']}</p>";
                } else {
                    $horas_txt .= "<p style='margin-bottom:8px; color:#888;'><i class=\"fas fa-building\" style=\"color:#ffc107; width:20px;\"></i> NA</p>";
                }

                // Texto horario Virtual
                if ($g['horario_virtual']) {
                    $hv = $g['horario_virtual'];
                    $horas_txt .= "<p><i class=\"fas fa-laptop\" style=\"color:#17a2b8; width:20px;\"></i> {$hv['dias']} • {$hv['inicio']} - {$hv['fin']} <br><i class=\"fas fa-video\" style=\"color:#17a2b8; width:20px;\"></i> {$hv['aula']}</p>";
                } else {
                    $horas_txt .= "<p style='color:#888;'><i class=\"fas fa-laptop\" style=\"color:#17a2b8; width:20px;\"></i> NA <br><i class=\"fas fa-video\" style=\"color:#17a2b8; width:20px;\"></i> NA</p>";
                }

                $nrc_para_solicitud = $g['nrc_presencial'] !== 'NA' ? $g['nrc_presencial'] : $g['nrc_virtual'];
                ?>
                <div class="card-oferta" data-lang="<?php echo htmlspecialchars($g['idioma']); ?>" data-text="<?php echo strtolower(htmlspecialchars($g['clave_materia'] . ' ' . $g['idioma'] . ' ' . getRomanNumeral($g['nivel']) . ' ' . $g['prof_nombre'] . ' ' . $g['prof_ap'] . ' ' . $g['clave_grupo'])); ?>">
                    <div class="card-header">
                        <div class="card-title">
                            <h3><span style="font-size:0.9rem; color:#888; margin-right:5px;"><?php echo htmlspecialchars($g['clave_materia']); ?></span> <?php echo htmlspecialchars($g['idioma']); ?> <?php echo getRomanNumeral($g['nivel']); ?></h3>
                            <p style="margin-bottom:5px;">Grupo <?php echo htmlspecialchars($g['clave_grupo'] ?? 'S/N'); ?></p>
                            <p style="margin-top: 5px; font-weight: 600;">NRC:<br>
                                <span style="color: #ffc107;"><i class="fas fa-building"></i> <?php echo $g['nrc_presencial']; ?></span> -
                                <span style="color: #17a2b8;"><i class="fas fa-laptop"></i> <?php echo $g['nrc_virtual']; ?></span>
                            </p>
                        </div>
                        <div class="badges-container">
                            <span class="badge-cupo <?php echo $badge_class; ?>"><?php echo $badge_text; ?></span>
                            <?php if ($g['demanda'] > 0): ?>
                                <span class="badge-demanda"><i class="fas fa-fire"></i> <?php echo $g['demanda']; ?> peticiones</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card-info">
                        <p><i class="fas fa-chalkboard-teacher"></i> Prof. <?php echo htmlspecialchars(trim($g['prof_nombre'] . ' ' . $g['prof_ap'])); ?></p>
                        <?php echo $horas_txt; ?>
                    </div>

                    <div class="card-progress">
                        <div class="progress-text">
                            <span>Inscritos</span>
                            <span><?php echo $g['inscritos']; ?>/<?php echo $g['cupo']; ?></span>
                        </div>
                        <div class="progress-bar-container">
                            <?php
                            $fill_perc = $g['cupo'] > 0 ? ($g['inscritos'] / $g['cupo']) * 100 : 100;
                            if ($fill_perc > 100) $fill_perc = 100;
                            ?>
                            <div class="progress-bar-fill <?php echo $fill_perc >= 100 ? 'danger' : ''; ?>" style="width: <?php echo $fill_perc; ?>%"></div>
                        </div>
                    </div>

                    <div style="display: flex; justify-content: flex-end;">
                        <?php
                        $ya_inscrito_nrc = in_array($g['nrc_presencial'], $mis_nrcs) || in_array($g['nrc_virtual'], $mis_nrcs);
                        $ya_solicitado_nrc = in_array($g['nrc_presencial'], $mis_solicitudes) || in_array($g['nrc_virtual'], $mis_solicitudes);

                        $ya_cursando_materia = in_array($g['materia_id'], $mis_materias_inscritas);
                        $ya_solicitada_materia = in_array($g['materia_id'], $mis_materias_solicitadas);

                        $materia_key = mb_strtoupper(trim($g['idioma']), 'UTF-8') . '_' . $g['nivel'];
                        $ya_acreditada_materia = in_array($materia_key, $mis_materias_acreditadas);

                        if ($ya_inscrito_nrc): ?>
                            <button class="btn-solicitar" style="background:#28a745; cursor:not-allowed;" disabled>
                                Inscrito <i class="fas fa-check-circle"></i>
                            </button>
                        <?php elseif ($ya_acreditada_materia): ?>
                            <div style="display: flex; align-items: center;">
                                <span style="font-size: 0.8rem; color: #0d6efd; opacity: 0.8; margin-right: 15px; font-style: italic;">
                                    Ya has acreditado este nivel de idioma.
                                </span>
                                <button class="btn-solicitar" style="background:#0d6efd; cursor:not-allowed; white-space: nowrap;" disabled>
                                    Acreditado <i class="fas fa-check-double"></i>
                                </button>
                            </div>
                        <?php elseif ($ya_solicitado_nrc): ?>
                            <div style="display: flex; align-items: center;">
                                <span style="font-size: 0.8rem; color: #856404; margin-right: 15px; font-style: italic;">
                                    En espera a la confirmación de un administrador.
                                </span>
                                <button class="btn-solicitar" style="background:#ffc107; color:#333; cursor:pointer;" onclick="cancelarSolicitud(<?php echo $nrc_para_solicitud; ?>)">
                                    Solicitada <i class="fas fa-clock"></i>
                                </button>
                            </div>
                        <?php elseif ($ya_cursando_materia): ?>
                            <div style="display: flex; align-items: center;">
                                <span style="font-size: 0.8rem; color: #6f42c1; opacity: 0.8; margin-right: 15px; font-style: italic;">
                                    Ya estás cursando este nivel de idioma.
                                </span>
                                <button class="btn-solicitar" style="background:#6f42c1; cursor:not-allowed; white-space: nowrap;" disabled>
                                    En Curso <i class="fas fa-ban"></i>
                                </button>
                            </div>
                        <?php elseif ($ya_solicitada_materia): ?>
                            <div style="display: flex; align-items: center;">
                                <span style="font-size: 0.8rem; color: #fd7e14; opacity: 0.8; margin-right: 15px; font-style: italic;">
                                    Ya tienes una solicitud pendiente para este nivel.
                                </span>
                                <button class="btn-solicitar" style="background:#fd7e14; cursor:not-allowed; white-space: nowrap;" disabled>
                                    Solicitado <i class="fas fa-ban"></i>
                                </button>
                            </div>
                        <?php elseif ($altas_habilitadas): ?>
                            <button class="btn-solicitar" onclick="openModal(<?php echo $nrc_para_solicitud; ?>, '<?php echo addslashes(htmlspecialchars($g['clave_materia'])); ?>', '<?php echo addslashes(htmlspecialchars($g['idioma'] . ' ' . getRomanNumeral($g['nivel']))); ?>', '<?php echo htmlspecialchars($g['nrc_presencial']); ?>', '<?php echo htmlspecialchars($g['nrc_virtual']); ?>')">
                                Solicitar <i class="fas fa-paper-plane"></i>
                            </button>
                        <?php else: ?>
                            <button class="btn-solicitar" style="background:#ccc; cursor:not-allowed;" disabled>
                                No Disponible <i class="fas fa-lock"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </main>

    <?php include '../main_footer.php'; ?>

    <!-- Modal para Solicitar -->
    <div class="modal" id="modalSolicitar">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal()">&times;</span>
            <h3 style="margin-top:0; color:var(--udg-blue); border-bottom:1px solid #eee; padding-bottom:10px;">Solicitar Alta de Materia</h3>
            <p id="materiaSolicitadaTxt" style="font-weight:bold; margin-bottom: 20px;"></p>

            <div class="alert" style="background:#fff3cd; color:#856404; padding:10px; border-radius:5px; margin-bottom:20px; font-size:0.9rem;">
                <i class="fas fa-exclamation-triangle"></i> Si la materia que solicitas choca con tu horario externo adjunto, será rechazada al instante. Se validarán cruces automáticamente.
            </div>

            <form id="formSolicitar" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="nrc" id="inputNrc" value="">

                <div class="form-group">
                    <label>Motivo de la solicitud:</label>
                    <textarea name="motivo" rows="3" required maxlength="250" placeholder="Explica por qué necesitas dar de alta esta materia..."></textarea>
                    <small style="color:#666; display:block; margin-top:5px; text-align:right;">Máximo 250 caracteres.</small>
                </div>

                <div class="form-group">
                    <label>Captura de tu horario escolar (Obligatorio):</label>
                    <input type="file" name="horario_img" accept="image/png, image/jpeg, image/jpg" required>
                    <small style="color:#666; display:block; margin-top:5px;">Sube una imagen clara de tu horario (se optimizará al subir).</small>
                </div>

                <button type="submit" class="btn-solicitar" style="width:100%; justify-content:center; margin-top:10px;" id="btnSubmit">
                    Enviar Solicitud <i class="fas fa-check"></i>
                </button>
            </form>
        </div>
    </div>

    <!-- Modal de Historial de Altas -->
    <?php if (count($historial_altas) > 0): ?>
        <div id="modalHistorialAltas" class="modal-overlay" style="display:none;">
            <div class="modal-content" style="max-width: 600px;">
                <div class="modal-header">
                    <h3 style="margin:0; color:var(--udg-blue);"><i class="fas fa-history"></i> Historial de Solicitudes de Alta</h3>
                    <button class="close-btn" onclick="document.getElementById('modalHistorialAltas').style.display='none'">&times;</button>
                </div>
                <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                    <p style="font-size:0.9rem; color:var(--text-muted); margin-top:0;">Historial de peticiones de alta para el semestre actual.</p>

                    <?php foreach ($historial_altas as $h):
                        $estatus_raw = strtoupper($h['estatus']);
                        $class_card = strtolower($estatus_raw);
                        $icono_estatus = 'fa-clock';
                        $neon_badge_class = 'neon-badge-pendiente';

                        switch ($estatus_raw) {
                            case 'APROBADA':
                                $icono_estatus = 'fa-check-circle';
                                $neon_badge_class = 'neon-badge-aprobada';
                                break;
                            case 'RECHAZADA':
                                $icono_estatus = 'fa-times-circle';
                                $neon_badge_class = 'neon-badge-rechazada';
                                break;
                            case 'CANCELADA':
                                $icono_estatus = 'fa-ban';
                                $neon_badge_class = 'neon-badge-cancelada';
                                break;
                            case 'PENDIENTE':
                            default:
                                $icono_estatus = 'fa-clock';
                                $neon_badge_class = 'neon-badge-pendiente';
                                break;
                        }
                    ?>
                        <div class="history-card <?php echo $class_card; ?>">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; margin-bottom: 8px;">
                                <div>
                                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                                        <span class="notif-sub-badge neon-sub-tramite">
                                            <i class="fas fa-file-signature"></i> Solicitud de Alta
                                        </span>
                                    </div>
                                    <div style="font-weight: 700; color: var(--text-dark); font-size: 0.98rem;">
                                        <?php echo htmlspecialchars($h['materia'] . ' ' . getRomanNumeral((int)$h['nivel'])); ?>
                                    </div>
                                    <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 2px;">
                                        Grupo <?php echo htmlspecialchars($h['clave_grupo']); ?> • Clave: <?php echo htmlspecialchars($h['clave_materia']); ?>
                                    </div>
                                </div>
                                <span class="neon-badge <?php echo $neon_badge_class; ?>">
                                    <i class="fas <?php echo $icono_estatus; ?>"></i> <?php echo $estatus_raw; ?>
                                </span>
                            </div>

                            <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 10px; display: flex; align-items: center; gap: 6px;">
                                <i class="far fa-calendar-alt"></i> Solicitada el <?php echo date('d/m/Y', strtotime($h['fecha_solicitud'])); ?>
                            </div>

                            <div style="font-size: 0.86rem; color: var(--text-dark); line-height: 1.45; background: rgba(128, 128, 128, 0.05); padding: 8px 12px; border-radius: 6px; border: 1px solid rgba(128, 128, 128, 0.1);">
                                <strong style="color: var(--text-muted); font-size: 0.76rem; text-transform: uppercase; letter-spacing: 0.4px; display: block; margin-bottom: 2px;">Motivo expuesto:</strong>
                                <?php echo htmlspecialchars($h['motivo']); ?>
                            </div>

                            <?php if ($estatus_raw !== 'PENDIENTE' && $estatus_raw !== 'CANCELADA'): ?>
                                <div class="history-resp-callout <?php echo $estatus_raw === 'APROBADA' ? 'history-resp-aprobada' : 'history-resp-rechazada'; ?>">
                                    <div class="history-resp-header">
                                        <i class="fas fa-reply"></i> Respuesta de Administración • <?php echo date('d/m/Y', strtotime($h['fecha_respuesta'])); ?>
                                    </div>
                                    <div class="history-resp-text">
                                        <?php echo htmlspecialchars($h['respuesta_admin']) ?: 'Sin comentarios adicionales.'; ?>
                                    </div>
                                </div>
                            <?php elseif ($estatus_raw === 'CANCELADA'): ?>
                                <div class="history-resp-callout history-resp-cancelada">
                                    <div class="history-resp-header">
                                        <i class="fas fa-ban"></i> Resolución del Estudiante • <?php echo date('d/m/Y', strtotime($h['fecha_respuesta'])); ?>
                                    </div>
                                    <div class="history-resp-text">
                                        El estudiante canceló la solicitud.
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script>
        function filterCards() {
            const input = document.getElementById('searchInput').value.toLowerCase();
            const lang = document.getElementById('langFilter').value;
            const cards = document.querySelectorAll('.card-oferta');

            cards.forEach(card => {
                const text = card.getAttribute('data-text');
                const cardLang = card.getAttribute('data-lang');

                let matchesSearch = text.includes(input);
                let matchesLang = lang === '' || cardLang === lang;

                if (matchesSearch && matchesLang) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        const modal = document.getElementById('modalSolicitar');

        function openModal(nrc_solicitar, clave, nombre, nrc_p, nrc_v) {
            document.getElementById('inputNrc').value = nrc_solicitar;
            document.getElementById('materiaSolicitadaTxt').innerHTML =
                clave + ": " + nombre + " - NRC: <span style='color:#ffc107;'><i class='fas fa-building'></i> " + nrc_p + "</span> | <span style='color:#17a2b8;'><i class='fas fa-laptop'></i> " + nrc_v + "</span>";
            modal.classList.add('active');
        }

        function closeModal() {
            modal.classList.remove('active');
            document.getElementById('formSolicitar').reset();
        }

        document.getElementById('formSolicitar').addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = document.getElementById('btnSubmit');
            btn.disabled = true;
            btn.innerHTML = 'Enviando... <i class="fas fa-spinner fa-spin"></i>';

            let formData = new FormData(this);

            fetch('procesar_solicitud_alta.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    btn.disabled = false;
                    btn.innerHTML = 'Enviar Solicitud <i class="fas fa-check"></i>';

                    if (data.status === 'success') {
                        closeModal();
                        Swal.fire({
                            icon: 'success',
                            title: 'Solicitud Enviada',
                            text: data.message,
                            confirmButtonColor: '#28a745'
                        }).then(() => {
                            window.location.reload();
                        });
                    } else {
                        if (data.horario) {
                            // Conflicto de horario detectado
                            Swal.fire({
                                icon: 'error',
                                title: 'Cruce de Horario Detectado',
                                html: data.message + '<br><br><b>Tienes clase de:</b> ' + data.horario,
                                confirmButtonColor: '#dc3545'
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: data.message,
                                confirmButtonColor: '#dc3545'
                            });
                        }
                    }
                })
                .catch(err => {
                    btn.disabled = false;
                    btn.innerHTML = 'Enviar Solicitud <i class="fas fa-check"></i>';
                    Swal.fire({
                        icon: 'error',
                        title: 'Error de red',
                        text: 'No se pudo conectar con el servidor.'
                    });
                });
        });

        function cancelarSolicitud(nrc) {
            Swal.fire({
                title: '¿Estás seguro?',
                text: "Estás a punto de retirar tu solicitud para esta materia.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, retirar solicitud',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    let formData = new FormData();
                    formData.append('action', 'cancelar');
                    formData.append('nrc', nrc);
                    formData.append('csrf_token', '<?php echo htmlspecialchars($csrf_token); ?>');

                    fetch('procesar_solicitud_alta.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.status === 'success') {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Retirada',
                                    text: data.message,
                                    timer: 2000,
                                    showConfirmButton: false
                                }).then(() => {
                                    window.location.reload();
                                });
                            } else {
                                Swal.fire('Error', data.message, 'error');
                            }
                        })
                        .catch(err => {
                            Swal.fire('Error', 'Ocurrió un problema de conexión', 'error');
                        });
                }
            });
        }
    </script>
</body>

</html>