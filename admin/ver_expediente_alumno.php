<?php
session_start();
require '../db.php';

// SEGURIDAD
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ADMIN') { 
    header("Location: ../index.php"); exit; 
}

// VERIFICAR ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: expedientes.php"); exit;
}
$usuario_id = $_GET['id'];

// 1. OBTENER DATOS DEL USUARIO (PERFIL)
$sql_perfil = "SELECT u.*, a.carrera, a.alumno_id 
               FROM usuarios u 
               LEFT JOIN alumnos a ON u.usuario_id = a.usuario_id 
               WHERE u.usuario_id = ?";
$stmt_perfil = $pdo->prepare($sql_perfil);
$stmt_perfil->execute([$usuario_id]);
$perfil = $stmt_perfil->fetch(PDO::FETCH_ASSOC);

if (!$perfil) { header("Location: expedientes.php"); exit; }

$nombre_completo = trim($perfil['nombre'] . ' ' . $perfil['apellido_paterno'] . ' ' . $perfil['apellido_materno']);
$foto_perfil = "../img/avatar-default.png"; 
if($perfil['foto_perfil'] && file_exists("../img/perfiles/" . $perfil['foto_perfil'])) {
    $foto_perfil = "../img/perfiles/" . $perfil['foto_perfil'];
}

$alumno_id = $perfil['alumno_id'];

// 2. OBTENER MATERIAS E HISTORIAL CON EL PROFESOR
$sql_materias = "SELECT i.*, m.nombre as materia, m.nivel, m.materia_id, c.nombre as ciclo, c.activo, g.estado as grupo_estado, g.nrc,
                 u.nombre as prof_nombre, u.apellido_paterno as prof_ap
                 FROM inscripciones i
                 JOIN grupos g ON i.nrc = g.nrc
                 JOIN materias m ON g.materia_id = m.materia_id
                 JOIN ciclos c ON g.ciclo_id = c.ciclo_id
                 LEFT JOIN usuarios u ON g.profesor_id = u.usuario_id
                 WHERE i.alumno_id = ?
                 ORDER BY c.nombre DESC, m.nivel DESC";
$stmt_mat = $pdo->prepare($sql_materias);
$stmt_mat->execute([$alumno_id]);
$todas_materias = $stmt_mat->fetchAll(PDO::FETCH_ASSOC);

$materias_actuales = [];
$historial = [];
$idiomas_nivel_4 = [];

foreach ($todas_materias as $mat) {
    $stmt_cal = $pdo->prepare("SELECT SUM(puntaje) FROM calificaciones WHERE inscripcion_id = ?");
    $stmt_cal->execute([$mat['inscripcion_id']]);
    $mat['calificacion_final'] = $stmt_cal->fetchColumn() ?: 0;

    if ($mat['activo'] == 1 && $mat['grupo_estado'] == 'ACTIVO' && $mat['estatus'] == 'INSCRITO') {
        $materias_actuales[] = $mat;
    } else {
        $historial[] = $mat;
    }
    
    if ($mat['nivel'] >= 4) {
        $idiomas_nivel_4[$mat['materia']] = true;
    }
}

$idiomas_nivel_4 = array_keys($idiomas_nivel_4);

// 3. OBTENER CERTIFICACIONES Y DIAGNÓSTICOS
$stmt_cert = $pdo->prepare("SELECT * FROM certificaciones WHERE alumno_id = ?");
$stmt_cert->execute([$alumno_id]);
$certificaciones_bd = [];
while($row = $stmt_cert->fetch(PDO::FETCH_ASSOC)) {
    $certificaciones_bd[mb_strtoupper(trim($row['idioma']), 'UTF-8')] = $row;
}

$stmt_diag = $pdo->prepare("SELECT * FROM examenes_diagnosticos WHERE alumno_id = ? ORDER BY fecha_realizacion DESC");
$stmt_diag->execute([$alumno_id]);
$examenes_diagnosticos = $stmt_diag->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expediente Alumno | <?php echo htmlspecialchars($nombre_completo); ?></title>
    <link rel="stylesheet" href="../css/estudiante.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

    <?php include 'menu_admin.php'; ?>

    <main class="main-content">

        <?php if(isset($_GET['exito'])): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    let msg = 'Los cambios fueron guardados correctamente.';
                    <?php if($_GET['exito'] == 'foto'): ?> msg = 'La foto de perfil inapropiada ha sido eliminada.'; <?php endif; ?>
                    
                    Swal.fire({ title: '¡Éxito!', text: msg, icon: 'success', confirmButtonColor: 'var(--udg-blue)' });
                    const currentUrl = new URL(window.location.href); currentUrl.searchParams.delete('exito');
                    window.history.replaceState({}, document.title, currentUrl.pathname + currentUrl.search);
                });
            </script>
        <?php endif; ?>

        <!-- SECCIÓN PERFIL -->
        <div class="expediente-header">
            <div class="avatar-wrapper">
                <img src="<?php echo $foto_perfil; ?>" alt="Foto" class="expediente-avatar">
                <?php if($perfil['foto_perfil']): ?>
                    <!-- ESCUDO CSRF APLICADO A LA URL DE BORRADO -->
                    <a href="#" onclick="confirmarBorrarFoto('acciones_expediente.php?id=<?php echo $usuario_id; ?>&borrar_foto=1&csrf_token=<?php echo $_SESSION['csrf_token']; ?>')" class="btn-delete-avatar" title="Eliminar foto inapropiada">
                        <i class="fas fa-trash-alt"></i>
                    </a>
                <?php endif; ?>
            </div>

            <div>
                <h1 class="expediente-title"><?php echo htmlspecialchars($nombre_completo); ?></h1>
                <p class="expediente-badges">
                    <span><i class="fas fa-user-graduate"></i> Alumno</span> | 
                    <span><i class="fas fa-book"></i> <?php echo htmlspecialchars($perfil['carrera'] ?? 'Sin Carrera'); ?></span> | 
                    <span><i class="fas fa-id-badge"></i> Código: <?php echo htmlspecialchars($perfil['codigo'] ?: 'N/A'); ?></span> | 
                    <span><i class="fas fa-calendar-check"></i> Ingreso: <?php echo htmlspecialchars($perfil['periodo_ingreso'] ?: 'N/A'); ?></span> | 
                    <span>
                        <?php 
                            if ($perfil['genero'] == 'MASCULINO') echo '<i class="fas fa-mars" style="color:#60a5fa;"></i> Masculino';
                            elseif ($perfil['genero'] == 'FEMENINO') echo '<i class="fas fa-venus" style="color:#f472b6;"></i> Femenino';
                            elseif ($perfil['genero'] == 'OTRO') echo '<i class="fas fa-transgender-alt" style="color:#c084fc;"></i> Otro';
                            else echo '<i class="fas fa-genderless text-muted"></i> No especificado';
                        ?>
                    </span>
                </p>
            </div>
        </div>

        <!-- INFORMACIÓN DE CONTACTO -->
        <div class="card card-mb-20">
            <h3 class="section-title-border"><i class="fas fa-info-circle"></i> Información de Contacto</h3>
            <div class="info-contact-grid">
                <div>
                    <label class="info-label">Correo Electrónico</label>
                    <div class="info-value"><i class="fas fa-envelope info-icon"></i> <?php echo htmlspecialchars($perfil['correo']); ?></div>
                </div>
                <div>
                    <label class="info-label">Teléfono</label>
                    <div class="info-value"><i class="fas fa-phone info-icon"></i> <?php echo htmlspecialchars($perfil['telefono'] ?: 'No registrado'); ?></div>
                </div>
                <div>
                    <label class="info-label">Estado del Usuario</label>
                    <div class="info-value">
                        <?php if($perfil['estatus'] == 'ACTIVO'): ?>
                            <span class="tag-active-status"><i class="fas fa-check-circle"></i> Activo</span>
                        <?php else: ?>
                            <span class="tag-inactive-status"><i class="fas fa-times-circle"></i> Inactivo</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="expediente-grid-main">
            <!-- COLUMNA IZQUIERDA (ACADÉMICA) -->
            <div>
                <div class="card card-mt-0">
                    <h3><i class="fas fa-book-reader"></i> Cursando Actualmente</h3>
                    <?php if (count($materias_actuales) > 0): ?>
                        <?php foreach ($materias_actuales as $mat): 
                            $stmt_max = $pdo->prepare("SELECT SUM(puntos_maximos) FROM criterios_evaluacion WHERE materia_id = ?");
                            $stmt_max->execute([$mat['materia_id']]);
                            $max_puntos = $stmt_max->fetchColumn() ?: 0;
                            $puntos_actuales = $mat['calificacion_final'];
                            $porcentaje = ($max_puntos > 0) ? ($puntos_actuales / $max_puntos) * 100 : 0;
                            $color_bar = ($porcentaje >= 80) ? 'var(--udg-light)' : '#dc3545';
                        ?>
                            <div class="subject-card">
                                <div class="subject-header">
                                    <strong><?php echo htmlspecialchars($mat['materia'] . ' Nivel ' . $mat['nivel']); ?></strong>
                                    <div class="subject-actions">
                                        <span class="subject-score"><?php echo floatval($puntos_actuales); ?> / <?php echo $max_puntos; ?> pts</span>
                                        <button class="btn-save btn-sm" onclick="abrirModalCalif(<?php echo $mat['inscripcion_id']; ?>)"><i class="fas fa-edit"></i> Calificar</button>
                                    </div>
                                </div>
                                <span class="subject-meta">Ciclo: <?php echo htmlspecialchars($mat['ciclo']); ?> | NRC: <?php echo $mat['nrc']; ?> | Prof. <?php echo htmlspecialchars($mat['prof_nombre'] . ' ' . $mat['prof_ap']); ?></span>
                                <div class="progress-bar-bg">
                                    <div class="progress-bar-fill" style="background-color: <?php echo $color_bar; ?>; width: <?php echo min($porcentaje, 100); ?>%;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state"><i class="fas fa-bed"></i><p>No está inscrito en ninguna materia activa.</p></div>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <h3><i class="fas fa-history"></i> Historial Académico (Kárdex)</h3>
                    <p class="section-subtitle">Haz clic en cualquier materia para ver o editar sus calificaciones.</p>
                    <div style="overflow-x:auto;">
                        <table class="table-clean history-table">
                            <thead>
                                <tr><th>Ciclo</th><th>Materia y Detalles</th><th class="text-center">Calificación</th><th class="text-center">Estado y Resultado</th></tr>
                            </thead>
                            <tbody>
                                <?php if(count($historial) > 0): ?>
                                    <?php foreach($historial as $h): $calif = floatval($h['calificacion_final']); ?>
                                        <tr class="clickable-row" onclick="abrirModalCalif(<?php echo $h['inscripcion_id']; ?>)">
                                            <td><?php echo htmlspecialchars($h['ciclo']); ?></td>
                                            
                                            <td class="subject-score">
                                                <?php echo htmlspecialchars($h['materia'] . ' Nivel ' . $h['nivel']); ?><br>
                                                <span style="font-size: 0.8rem; color: #888; font-weight: normal;">NRC: <?php echo htmlspecialchars($h['nrc']); ?> | Prof. <?php echo htmlspecialchars($h['prof_nombre'] . ' ' . $h['prof_ap']); ?></span>
                                            </td>
                                            
                                            <td class="text-center" style="font-size:1.1rem; font-weight:bold;"><?php echo $calif; ?></td>
                                            <td class="text-center">
                                                
                                                <?php if($h['estatus'] == 'BAJA'): ?>
                                                    <span style="background:#e2e3e5; color:#383d41; padding: 4px 10px; border-radius: 12px; font-size: 0.8rem; font-weight: bold;"><i class="fas fa-arrow-down" style="font-size:0.7rem;"></i> Baja</span>
                                                <?php else: ?>
                                                    <?php if($h['grupo_estado'] == 'ACTIVO' && $h['activo'] == 1): ?>
                                                        <span style="background:#cce5ff; color:#004085; padding: 4px 10px; border-radius: 12px; font-size: 0.8rem; font-weight: bold;"><i class="fas fa-circle" style="font-size:0.5rem;"></i> Activa</span>
                                                    <?php else: ?>
                                                        <?php if($calif >= 80): ?>
                                                            <span style="background:#d4edda; color:#155724; padding: 4px 10px; border-radius: 12px; font-size: 0.8rem; font-weight: bold;">Aprobada</span>
                                                        <?php else: ?>
                                                            <span style="background:#f8d7da; color:#721c24; padding: 4px 10px; border-radius: 12px; font-size: 0.8rem; font-weight: bold;">Reprobada</span>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                <?php endif; ?>

                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-center text-muted" style="padding: 20px;">Sin registros en ciclos anteriores.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- COLUMNA DERECHA (DIAGNÓSTICOS Y CERTIFICACIONES) -->
            <div>
                <div class="card card-highlighted">
                    <div class="card-actions-top">
                        <button class="btn-save btn-sm" onclick="abrirModalDiag('', '', '', '', '', '')" title="Agregar Nuevo Diagnóstico"><i class="fas fa-plus"></i> Agregar</button>
                    </div>

                    <div class="card-header-center">
                        <i class="fas fa-clipboard-check card-icon-medium"></i>
                        <h3 style="color: var(--udg-blue); margin: 0;">Examen Diagnóstico</h3>
                        <p style="font-size: 0.85rem; color: #666; margin-top: 5px;">Resultados de ubicación inicial</p>
                    </div>
                    
                    <div style="margin-top: 15px;">
                        <?php if(count($examenes_diagnosticos) > 0): ?>
                            <?php foreach($examenes_diagnosticos as $diag): ?>
                                <div class="diag-card card-editable" onclick='editarDiagnostico(<?php echo json_encode($diag); ?>)' title="Haz clic para editar este diagnóstico">
                                    <div class="diag-header">
                                        <strong class="diag-title"><?php echo htmlspecialchars($diag['idioma']); ?></strong>
                                        <span class="diag-badge"><?php echo htmlspecialchars($diag['periodo']); ?></span>
                                    </div>
                                    <div class="diag-details">
                                        <div><i class="fas fa-layer-group text-muted"></i> Nivel: <strong><?php echo htmlspecialchars($diag['nivel_asignado']); ?></strong></div>
                                        <div><i class="fas fa-star text-muted"></i> Calif: <strong><?php echo htmlspecialchars($diag['calificacion_texto']); ?></strong></div>
                                        <div class="col-span-2"><i class="far fa-calendar-alt text-muted"></i> Fecha: <?php echo date('d/m/Y', strtotime($diag['fecha_realizacion'])); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state-box">
                                <i class="fas fa-search" style="font-size: 2rem; color: #ddd; margin-bottom: 10px; display: block;"></i>
                                Sin registro de examen diagnóstico.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if(count($idiomas_nivel_4) > 0): ?>
                    <div class="card card-highlighted">
                        <div class="card-header-center">
                            <i class="fas fa-certificate card-icon-large"></i>
                            <h3 style="color: var(--udg-blue); margin: 0;">Certificaciones</h3>
                            <p style="font-size: 0.85rem; color: #666;">Idiomas con Nivel 4 o superior</p>
                        </div>
                        
                        <div style="margin-top: 15px;">
                            <?php foreach($idiomas_nivel_4 as $idioma): 
                                $idioma_key = mb_strtoupper(trim($idioma), 'UTF-8');
                                $cert = $certificaciones_bd[$idioma_key] ?? null;
                                
                                $nivel_obt = $cert['nivel_obtenido'] ?? 'Sin registrar';
                                $puntaje_obt = $cert['puntaje'] ?? '';
                                $periodo_obt = $cert['periodo'] ?? '';
                                $fecha_obt = (!empty($cert['fecha_aplicacion']) && $cert['fecha_aplicacion'] !== '0000-00-00') ? $cert['fecha_aplicacion'] : '';
                            ?>
                                <div class="cert-card card-editable" onclick="abrirModalCert('<?php echo htmlspecialchars((string)$idioma); ?>', '<?php echo htmlspecialchars((string)($nivel_obt == 'Sin registrar' ? '' : $nivel_obt)); ?>', '<?php echo htmlspecialchars((string)$puntaje_obt); ?>', '<?php echo htmlspecialchars((string)$periodo_obt); ?>', '<?php echo htmlspecialchars((string)$fecha_obt); ?>')" title="Haz clic para actualizar nivel oficial">
                                    <div>
                                        <strong class="cert-title"><?php echo htmlspecialchars((string)$idioma); ?></strong>
                                        <span class="cert-meta">Nivel: <strong style="color:var(--udg-blue);"><?php echo htmlspecialchars((string)$nivel_obt); ?></strong></span>
                                        
                                        <?php if($cert): ?>
                                            <div class="cert-details">
                                                <div><i class="fas fa-star text-muted"></i> Pts: <strong><?php echo htmlspecialchars((string)($puntaje_obt ?: '-')); ?></strong></div>
                                                <div><i class="fas fa-calendar-alt text-muted"></i> Per: <strong><?php echo htmlspecialchars((string)($periodo_obt ?: '-')); ?></strong></div>
                                                <div class="col-span-2"><i class="far fa-calendar-check text-muted"></i> Fecha: <?php echo $fecha_obt ? date('d/m/Y', strtotime($fecha_obt)) : '-'; ?></div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card card-mt-0 cert-locked">
                        <i class="fas fa-lock" style="font-size: 2.5rem; color: #ddd; margin-bottom: 15px;"></i>
                        <h4 style="color: #666; margin: 0;">Certificación Bloqueada</h4>
                        <p style="font-size: 0.85rem; color: #999; margin-top: 10px;">Disponible al cursar Nivel 4.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ==========================================
             AQUÍ IMPORTAMOS TODOS LOS MODALES HTML
             ========================================== -->
        <?php include 'modales_expediente.php'; ?>

    </main>

    <footer class="main-footer"><div class="address-bar">Copyright © 2026 E-PALE | Panel de Administración</div></footer>

    <script>
        // Función para atrapar los datos del diagnóstico y abrir el modal
        function editarDiagnostico(diag) {
            document.getElementById('inputExamenId').value = diag.examen_id;
            document.getElementById('inputIdiomaDiag').value = diag.idioma;
            document.getElementById('inputNivelDiag').value = diag.nivel_asignado;
            document.getElementById('inputCalifDiag').value = diag.calificacion_texto;
            document.getElementById('inputPeriodoDiag').value = diag.periodo;
            document.getElementById('inputFechaDiag').value = diag.fecha_realizacion;
            document.getElementById('modalDiagTitle').innerHTML = '<i class="fas fa-edit"></i> Editar Diagnóstico';
            document.getElementById('modalDiag').style.display = 'flex';
        }

        // Alerta elegante para confirmar el borrado de la foto
        function confirmarBorrarFoto(url) {
            Swal.fire({
                title: '¿Borrar foto de perfil?',
                text: "Si la foto contiene contenido inapropiado o viola las normas, se eliminará permanentemente.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, borrar foto',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = url;
                }
            });
        }
    </script>
    <script src="../js/expediente_alumno.js?v=<?php echo time(); ?>"></script>
</body>
</html>
