<?php
require_once 'perfil_logic.php';

/**
 * Declaración de variables importadas desde perfil_logic.php
 * para evitar falsos positivos de "Undefined variable" en el IDE.
 * 
 * @var array $user
 * @var int $alumno_id
 * @var string $nombre_completo
 * @var string $foto_perfil
 * @var array $historial
 * @var array $idiomas_nivel_4
 * @var array $certificaciones_bd
 * @var array $examenes_diagnosticos
 * @var string $mensaje_exito
 * @var string $mensaje_error
 */
?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Perfil | E-Pale</title>
    <link rel="stylesheet" href="../css/estilos.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/estudiante.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <?php include 'menu_estudiante.php'; ?>

    <main class="main-content">
        
        <div class="profile-header-card">
            <div class="profile-photo-wrapper">
                <img src="<?php echo $foto_perfil; ?>" alt="Foto de Perfil" class="profile-photo-img">
                <label class="edit-photo-btn" onclick="abrirModalFoto()" title="Cambiar foto de perfil">
                    <i class="fas fa-camera"></i>
                </label>
            </div>
        </div>

        <div class="profile-basic-info">
            <h2 style="margin:0; color:var(--text-dark);"><?php echo htmlspecialchars($nombre_completo); ?></h2>
            <span class="profile-role-badge"><i class="fas fa-graduation-cap"></i> Alumno</span>
            <p style="color:var(--text-muted); margin-top:5px; font-size:0.9rem;"><?php echo htmlspecialchars($user['correo']); ?></p>
        </div>

        <?php if(!empty($mensaje_exito)): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($mensaje_exito); ?></div>
        <?php endif; ?>
        <?php if(!empty($mensaje_error)): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($mensaje_error); ?></div>
        <?php endif; ?>

        <?php if(count($examenes_diagnosticos) > 0): ?>
            <div class="card card-examen-diag">
                <h3 style="margin-top: 0; color: var(--text-dark);"><i class="fas fa-clipboard-check" style="color: #00bfff;"></i> Examen Diagnóstico</h3>
                <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: -10px; margin-bottom: 15px;">Resultados de tu evaluación inicial para la asignación de nivel.</p>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 15px;">
                    <?php foreach($examenes_diagnosticos as $diag): ?>
                        <div class="diag-card-profile">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
                                <h4 style="margin: 0; color: var(--text-dark); font-size: 1.15rem;"><?php echo htmlspecialchars($diag['idioma']); ?></h4>
                                <span style="background: rgba(0, 191, 255, 0.1); color: #0284c7; padding: 3px 10px; border-radius: 12px; font-size: 0.8rem; font-weight: bold; border: 1px solid rgba(0, 191, 255, 0.25);"><?php echo htmlspecialchars($diag['periodo']); ?></span>
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; font-size: 0.9rem; color: var(--text-muted);">
                                <div><i class="fas fa-layer-group"></i> Nivel asignado: <strong style="color: var(--udg-blue); font-size: 1.05rem;"><?php echo htmlspecialchars($diag['nivel_asignado']); ?></strong></div>
                                <div><i class="fas fa-star"></i> Calif: <strong style="color:var(--text-dark);"><?php echo htmlspecialchars($diag['calificacion_texto']); ?></strong></div>
                                <div style="grid-column: span 2;"><i class="far fa-calendar-alt"></i> Fecha de aplicación: <span style="color:var(--text-dark); font-weight:500;"><?php echo date('d/m/Y', strtotime($diag['fecha_realizacion'])); ?></span></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if(count($idiomas_nivel_4) > 0): ?>
            <div class="card card-certificaciones-oficiales">
                <h3 style="margin-top: 0; color: var(--text-dark);"><i class="fas fa-award" style="color: var(--udg-blue);"></i> Mis Certificaciones Oficiales</h3>
                <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: -10px; margin-bottom: 15px;">Niveles obtenidos en los idiomas en los que has cursado el Nivel 4 o superior.</p>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <?php foreach($idiomas_nivel_4 as $idioma): 
                        $cert = $certificaciones_bd[$idioma] ?? null;
                        $nivel_obt = $cert['nivel_obtenido'] ?? null;
                        
                        $clase_extra = $nivel_obt ? 'obtained' : '';
                        $onclick_event = '';
                        $titulo = '';
                        
                        if ($nivel_obt) {
                            $json_data = htmlspecialchars(json_encode($cert), ENT_QUOTES, 'UTF-8');
                            $onclick_event = "onclick='abrirModalCertDetalle($json_data)'";
                            $titulo = "title='Haz clic para ver detalles'";
                        }
                    ?>
                        <div class="cert-card <?php echo $clase_extra; ?>" <?php echo $onclick_event; ?> <?php echo $titulo; ?>>
                            <i class="fas fa-certificate" style="font-size: 2.5rem; color: <?php echo $nivel_obt ? 'var(--udg-light)' : 'var(--text-muted)'; ?>; margin-bottom: 10px;"></i>
                            <h4 style="margin: 0 0 5px 0; color: <?php echo $nivel_obt ? 'var(--text-dark)' : 'var(--text-muted)'; ?>;"><?php echo htmlspecialchars($idioma); ?></h4>
                            <span style="font-size: 1.2rem; font-weight: bold; color: <?php echo $nivel_obt ? 'var(--udg-blue)' : 'var(--text-muted)'; ?>;">
                                <?php echo $nivel_obt ? htmlspecialchars(strtoupper($nivel_obt)) : '<span style="font-size:0.8rem; font-weight:normal; font-style:italic;">Pendiente de registro</span>'; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="profile-grid">
            <div class="card profile-info-card">
                <div class="profile-card-header">
                    <div class="profile-card-title">
                        <div class="profile-title-icon"><i class="far fa-user"></i></div>
                        <div>
                            <h3>Información Personal</h3>
                            <p class="profile-card-subtitle">Datos de contacto y acceso a tu cuenta</p>
                        </div>
                    </div>
                    <button type="button" class="btn-edit-profile-card" onclick="abrirModalEditar()" title="Editar Información">
                        <i class="fas fa-pen"></i> <span>Editar</span>
                    </button>
                </div>

                <div class="profile-info-list">
                    <div class="profile-info-row">
                        <div class="profile-info-lead">
                            <i class="far fa-id-badge"></i>
                            <span class="info-label">Nombre Completo</span>
                        </div>
                        <span class="info-value"><?php echo htmlspecialchars($nombre_completo); ?></span>
                    </div>

                    <div class="profile-info-row">
                        <div class="profile-info-lead">
                            <i class="far fa-envelope"></i>
                            <span class="info-label">Correo Electrónico</span>
                        </div>
                        <span class="info-value"><?php echo htmlspecialchars($user['correo']); ?></span>
                    </div>

                    <div class="profile-info-row">
                        <div class="profile-info-lead">
                            <i class="fas fa-phone-alt"></i>
                            <span class="info-label">Teléfono</span>
                        </div>
                        <span class="info-value">
                            <?php echo $user['telefono'] ? htmlspecialchars($user['telefono']) : '<span class="text-muted-italic">No registrado</span>'; ?>
                        </span>
                    </div>

                    <div class="profile-info-row">
                        <div class="profile-info-lead">
                            <i class="fas fa-key"></i>
                            <span class="info-label">Contraseña</span>
                        </div>
                        <span class="info-value">
                            <?php 
                                if (!empty($user['fecha_cambio_password'])) {
                                    echo '<span class="pass-status-pill pass-updated"><i class="fas fa-shield-alt"></i> Modificada el ' . date('d/m/Y', strtotime($user['fecha_cambio_password'])) . '</span>';
                                } else {
                                    echo '<span class="pass-status-pill pass-default"><i class="fas fa-info-circle"></i> Original de acceso</span>';
                                }
                            ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="card profile-info-card profile-academic-card">
                <div class="profile-card-header">
                    <div class="profile-card-title">
                        <div class="profile-title-icon academic-icon"><i class="fas fa-graduation-cap"></i></div>
                        <div>
                            <h3>Información Académica</h3>
                            <p class="profile-card-subtitle">Programa vigente y estatus escolar</p>
                        </div>
                    </div>
                </div>

                <div class="profile-info-list">
                    <div class="profile-info-row">
                        <div class="profile-info-lead">
                            <i class="fas fa-hashtag"></i>
                            <span class="info-label hide-mobile">Código de Estudiante</span>
                            <span class="info-label show-mobile">Código:</span>
                        </div>
                        <span class="info-value font-mono">
                            <?php echo isset($user['codigo']) && $user['codigo'] ? htmlspecialchars($user['codigo']) : '---'; ?>
                        </span>
                    </div>

                    <div class="profile-info-row">
                        <div class="profile-info-lead">
                            <i class="fas fa-book-reader"></i>
                            <span class="info-label hide-mobile">Carrera / Programa</span>
                            <span class="info-label show-mobile">Carrera:</span>
                        </div>
                        <span class="info-value">
                            <?php echo isset($user['carrera']) && $user['carrera'] ? htmlspecialchars($user['carrera']) : '<span class="text-muted-italic">Sin asignar</span>'; ?>
                        </span>
                    </div>

                    <div class="profile-info-row">
                        <div class="profile-info-lead">
                            <i class="fas fa-user-check"></i>
                            <span class="info-label hide-mobile">Estatus en el Sistema</span>
                            <span class="info-label show-mobile">Estatus:</span>
                        </div>
                        <span class="info-value">
                            <span class="status-badge-active"><i class="fas fa-circle status-dot"></i> Estudiante Activo</span>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card profile-kardex-card">
            <div class="profile-card-header kardex-header">
                <div class="profile-card-title">
                    <div class="profile-title-icon kardex-icon"><i class="fas fa-history"></i></div>
                    <div>
                        <h3>Historial Académico (Kárdex)</h3>
                        <p class="profile-card-subtitle">Haz clic en cualquier materia para inspeccionar el desglose de tu evaluación final.</p>
                    </div>
                </div>
                <?php if(count($historial) > 0): ?>
                    <span class="kardex-count-badge">
                        <i class="fas fa-book"></i> <?php echo count($historial); ?> <?php echo count($historial) === 1 ? 'materia' : 'materias'; ?>
                    </span>
                <?php endif; ?>
            </div>

            <div class="kardex-table-container">
                <table class="history-table kardex-premium-table">
                    <thead>
                        <tr>
                            <th>Materia</th>
                            <th>Ciclo</th>
                            <th class="th-center">
                                <span class="hide-mobile">Calificación Final</span>
                                <span class="show-mobile">Calif. Final</span>
                            </th>
                            <th class="th-center">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($historial) > 0): ?>
                            <?php foreach($historial as $h): 
                                $calif = floatval($h['calificacion_final']);
                            ?>
                                <tr class="clickable-row kardex-row" onclick="window.location.href='calificaciones?ins=<?php echo $h['inscripcion_id']; ?>'">
                                    <td class="kardex-cell-materia">
                                        <div class="kardex-materia-wrap">
                                            <div class="kardex-materia-icon"><i class="fas fa-award"></i></div>
                                            <div class="kardex-materia-info">
                                                <?php if(!empty($h['materia_codigo'])): ?>
                                                    <span class="kardex-codigo-materia"><?php echo htmlspecialchars($h['materia_codigo']); ?></span>
                                                <?php endif; ?>
                                                <?php
                                                    $romanos = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X'];
                                                    $nivel_num = (int)$h['nivel'];
                                                    $nivel_romano = $romanos[$nivel_num] ?? $h['nivel'];
                                                ?>
                                                <strong class="kardex-materia-name"><?php echo htmlspecialchars($h['materia'] . ' ' . $nivel_romano); ?></strong>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="kardex-cell-ciclo">
                                        <span class="kardex-ciclo-pill"><?php echo htmlspecialchars($h['ciclo']); ?></span>
                                    </td>
                                    <td class="th-center">
                                        <span class="kardex-score-badge <?php echo $calif >= 80 ? 'score-high' : 'score-low'; ?>">
                                            <?php echo $calif; ?>
                                        </span>
                                    </td>
                                    <td class="th-center">
                                        <div class="kardex-status-wrap">
                                            <span class="kardex-pill-status kardex-pill-final"><i class="fas fa-archive"></i> Finalizada</span>
                                            <?php if($calif >= 80): ?>
                                                <span class="kardex-pill-status kardex-pill-pass"><i class="fas fa-check-circle"></i> Aprobado</span>
                                            <?php else: ?>
                                                <span class="kardex-pill-status kardex-pill-fail"><i class="fas fa-times-circle"></i> Reprobado</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="kardex-empty-cell">
                                    <i class="fas fa-folder-open kardex-empty-icon"></i>
                                    <p>Aún no tienes materias finalizadas en tu kárdex.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php include '../componente_biometrico.php'; ?>

    </main>

    <?php include '../main_footer.php'; ?>

    <div id="modalEditar" class="modal-overlay">
        <div class="modal-content profile-edit-modal">
            <div class="modal-header profile-edit-header">
                <div class="profile-edit-header-title">
                    <div class="profile-edit-icon"><i class="fas fa-user-edit"></i></div>
                    <div>
                        <h2>Editar Información</h2>
                        <p class="profile-edit-subtitle">Actualiza tus medios de contacto o tu contraseña</p>
                    </div>
                </div>
            </div>
            <form method="POST" action="perfil">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="actualizar_perfil" value="1">
                <div class="modal-body profile-edit-body">
                    <div class="form-group">
                        <label><i class="far fa-envelope"></i> Correo Electrónico</label>
                        <input type="email" name="correo" class="form-control" value="<?php echo htmlspecialchars($user['correo']); ?>" required placeholder="nombre@correo.com">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-phone-alt"></i> Teléfono</label>
                        <input type="text" name="telefono" class="form-control" value="<?php echo htmlspecialchars($user['telefono']); ?>" placeholder="Ej. 3312345678" pattern="[0-9]*" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Nueva Contraseña</label>
                        <input type="password" name="password" class="form-control" placeholder="(Opcional) Deja en blanco para no cambiar">
                        <small class="profile-modal-hint"><i class="fas fa-info-circle"></i> Solo escribe una contraseña si deseas reemplazar la actual.</small>
                    </div>
                </div>
                <div class="modal-footer profile-edit-footer">
                    <button type="button" class="btn-cancel" onclick="cerrarModalEditar()">Cancelar</button>
                    <button type="submit" class="btn-save"><i class="fas fa-save"></i> Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>

    <div id="modalFoto" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Cambiar Foto de Perfil</h2>
                <button class="close-btn" onclick="cerrarModalFoto()">&times;</button>
            </div>
            <div class="modal-body" style="padding-top: 0; overflow-y: visible;">
                <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 20px;">Por favor, selecciona una imagen cuadrada y de buena calidad (máx 5MB). Formatos permitidos: JPG, PNG, WEBP.</p>
                <form action="upload_foto" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <div class="form-group">
                        <input type="file" name="foto_perfil" id="fileFoto" accept="image/*" required style="font-size: 0.9rem; padding: 0; border: none; color: var(--text-dark);">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-cancel" onclick="cerrarModalFoto()">Cancelar</button>
                        <button type="submit" class="btn-save" style="display:flex; align-items:center; gap:8px;"><i class="fas fa-upload"></i> Subir Foto</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- DETALLES DE LA CERTIFICACIÓN -->
    <div id="modalCertDetalle" class="modal-overlay" style="display:none;">
        <div class="modal-content cert-modal">
            <div class="modal-header">
                <h2><i class="fas fa-certificate"></i> Detalles de la Certificación </h2>
                <button class="close-btn" onclick="cerrarModalCertDetalle()">&times;</button>
            </div>
            <div class="modal-body cert-modal-body">
                <i class="fas fa-award cert-icon-lg"></i>
                <h3 id="certDetalleIdioma" class="cert-title-lg"></h3>
                <div id="certDetalleNivel" class="cert-level-badge"></div>
                
                <div class="cert-detail-grid">
                    <div class="cert-detail-box">
                        <div class="cert-detail-label"><i class="fas fa-star"></i> Puntaje</div>
                        <div class="cert-detail-value" id="certDetallePuntaje"></div>
                    </div>
                    <div class="cert-detail-box">
                        <div class="cert-detail-label"><i class="far fa-calendar-alt"></i> Periodo</div>
                        <div class="cert-detail-value" id="certDetallePeriodo"></div>
                    </div>
                    <div class="cert-detail-box full-width">
                        <div class="cert-detail-label"><i class="far fa-calendar-check"></i> Fecha de Aplicación</div>
                        <div class="cert-detail-value" id="certDetalleFecha"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const modalEditar = document.getElementById('modalEditar');
        const modalFoto = document.getElementById('modalFoto');
        const modalCertDetalle = document.getElementById('modalCertDetalle');
        
        function abrirModalEditar() { modalEditar.style.display = 'flex'; }
        function cerrarModalEditar() { modalEditar.style.display = 'none'; }
        function abrirModalFoto() { modalFoto.style.display = 'flex'; }
        function cerrarModalFoto() { modalFoto.style.display = 'none'; }

        function abrirModalCertDetalle(cert) {
            document.getElementById('certDetalleIdioma').innerText = cert.idioma;
            document.getElementById('certDetalleNivel').innerText = 'Nivel ' + cert.nivel_obtenido;
            document.getElementById('certDetallePuntaje').innerText = cert.puntaje ? cert.puntaje : '---';
            document.getElementById('certDetallePeriodo').innerText = cert.periodo ? cert.periodo : '---';
            
            let fecha = 'No registrada';
            if (cert.fecha_aplicacion && cert.fecha_aplicacion !== '0000-00-00') {
                let partes = cert.fecha_aplicacion.split('-');
                fecha = partes[2] + '/' + partes[1] + '/' + partes[0]; 
            }
            document.getElementById('certDetalleFecha').innerText = fecha;
            
            modalCertDetalle.style.display = 'flex';
        }
        function cerrarModalCertDetalle() { modalCertDetalle.style.display = 'none'; }

        window.onclick = function(e) { 
            if(e.target == modalEditar) cerrarModalEditar(); 
            if(e.target == modalFoto) cerrarModalFoto(); 
            if(e.target == modalCertDetalle) cerrarModalCertDetalle();
        }
    </script>
</body>
</html>