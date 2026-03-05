<?php
session_start();
require '../db.php';

// SEGURIDAD
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ADMIN') {
    header("Location: ../index.php"); exit;
}

// LÓGICA PARA ELIMINAR GRUPO (Ambos: presencial y virtual)
if (isset($_GET['del_mat']) && isset($_GET['del_prof']) && isset($_GET['del_ciclo'])) {
    $pdo->prepare("DELETE FROM grupos WHERE materia_id=? AND profesor_id=? AND ciclo_id=?")
        ->execute([$_GET['del_mat'], $_GET['del_prof'], $_GET['del_ciclo']]);
    header("Location: grupos_nrc.php?success_del=1"); exit;
}

// Listas para los desplegables del formulario
$profesores = $pdo->query("SELECT usuario_id, nombre, apellido_paterno, apellido_materno FROM usuarios WHERE rol='PROFESOR' AND estatus='ACTIVO'")->fetchAll(PDO::FETCH_ASSOC);
$materias_list = $pdo->query("SELECT materia_id, clave, nombre FROM materias ORDER BY clave")->fetchAll(PDO::FETCH_ASSOC);
$ciclos = $pdo->query("SELECT ciclo_id, nombre FROM ciclos ORDER BY nombre DESC")->fetchAll(PDO::FETCH_ASSOC);

// Obtener lista de grupos "lógicos"
$sql = "SELECT c.nombre AS periodo, c.ciclo_id,
               m.clave AS curso, m.materia_id, m.nombre AS materia, m.nivel AS nivel,
               u.nombre AS profesor, u.apellido_paterno AS prof_ap, u.usuario_id AS profesor_id,
               MAX(CASE WHEN h.modalidad='PRESENCIAL' THEN g.nrc END) AS nrc_presencial,
               MAX(CASE WHEN h.modalidad='VIRTUAL' THEN g.nrc END) AS nrc_virtual,
               MAX(CASE WHEN h.modalidad='PRESENCIAL' THEN h.aula END) AS aula_presencial,
               MAX(CASE WHEN h.modalidad='VIRTUAL' THEN h.aula END) AS aula_virtual,
               MAX(CASE WHEN h.modalidad='PRESENCIAL' THEN h.dias_patron END) AS dias_presencial,
               MAX(CASE WHEN h.modalidad='VIRTUAL' THEN h.dias_patron END) AS dias_virtual,
               MAX(CASE WHEN h.modalidad='PRESENCIAL' THEN h.hora_inicio END) AS inicio_presencial,
               MAX(CASE WHEN h.modalidad='VIRTUAL' THEN h.hora_inicio END) AS inicio_virtual,
               MAX(CASE WHEN h.modalidad='PRESENCIAL' THEN h.hora_fin END) AS fin_presencial,
               MAX(CASE WHEN h.modalidad='VIRTUAL' THEN h.hora_fin END) AS fin_virtual
        FROM grupos g
        JOIN materias m ON g.materia_id = m.materia_id
        JOIN usuarios u ON g.profesor_id = u.usuario_id AND u.rol = 'PROFESOR'
        JOIN ciclos c ON g.ciclo_id = c.ciclo_id
        JOIN horarios h ON g.nrc = h.nrc
        GROUP BY g.materia_id, g.profesor_id, g.ciclo_id
        ORDER BY c.nombre DESC, m.clave, u.nombre";

$stmt = $pdo->query($sql);
$grupos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Grupos y NRC | Admin</title>
    <link rel="stylesheet" href="../css/estudiante.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .input-readonly { background-color: #e9ecef !important; color: #6c757d !important; cursor: not-allowed; }
    </style>
</head>
<body>

    <?php include 'menu_admin.php'; ?>

    <main class="main-content">
        
        <div class="page-title-center" style="margin-bottom: 30px;">
            <h1><i class="fas fa-chalkboard"></i> Gestión de Grupos (NRC)</h1>
            <p>Asigna profesores a las materias y define sus horarios presenciales y virtuales.</p>
        </div>

        <?php if(isset($_GET['success'])): ?>
            <div class="alert alert-success" style="margin-bottom: 20px;"><i class="fas fa-check-circle"></i> ¡El grupo ha sido guardado correctamente!</div>
        <?php elseif(isset($_GET['success_del'])): ?>
            <div class="alert alert-success" style="margin-bottom: 20px; background-color: #f8d7da; color: #721c24; border-color:#f5c6cb;"><i class="fas fa-trash"></i> ¡El grupo fue eliminado con éxito!</div>
        <?php elseif(isset($_GET['error'])): ?>
            <div class="alert alert-error" style="background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; border: 1px solid #f5c6cb; margin-bottom: 20px;">
                <i class="fas fa-exclamation-triangle"></i> Error: <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
        <?php endif; ?>

        <div style="display: flex; justify-content: flex-end; margin-bottom: 15px;">
            <button type="button" class="btn-save" onclick="openModal()">
                <i class="fas fa-plus-circle"></i> Crear Nuevo Grupo
            </button>
        </div>

        <div class="card" style="padding: 0; overflow: hidden;">
            <div class="table-wrapper" style="overflow-x:auto;">
                <table class="history-table" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="padding: 15px; text-align: left; background-color: #f8f9fa; border-bottom: 2px solid #eee;">Ciclo</th>
                            <th style="padding: 15px; text-align: left; background-color: #f8f9fa; border-bottom: 2px solid #eee;">Materia</th>
                            <th style="padding: 15px; text-align: left; background-color: #f8f9fa; border-bottom: 2px solid #eee;">Profesor</th>
                            <th style="padding: 15px; text-align: center; background-color: #f8f9fa; border-bottom: 2px solid #eee;">NRC (P/V)</th>
                            <th style="padding: 15px; text-align: center; background-color: #f8f9fa; border-bottom: 2px solid #eee;">Horario (P/V)</th>
                            <th style="padding: 15px; text-align: center; background-color: #f8f9fa; border-bottom: 2px solid #eee;">Aula (P/V)</th>
                            <th style="padding: 15px; text-align: center; background-color: #f8f9fa; border-bottom: 2px solid #eee;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (count($grupos) > 0): ?>
                        <?php foreach ($grupos as $g): ?>
                            <tr style="border-bottom: 1px solid #eee;">
                                <td style="padding: 15px; font-weight: bold; color: #555;"><?php echo htmlspecialchars($g['periodo']); ?></td>
                                <td style="padding: 15px;">
                                    <div style="color: var(--udg-blue); font-weight: bold;"><?php echo htmlspecialchars($g['materia']); ?></div>
                                    <div style="font-size: 0.8rem; color: #888; font-family: monospace;"><?php echo htmlspecialchars($g['curso']); ?> - Nivel <?php echo htmlspecialchars($g['nivel']); ?></div>
                                </td>
                                <td style="padding: 15px;">
                                    <i class="fas fa-chalkboard-teacher" style="color: #ccc; margin-right: 5px;"></i>
                                    <?php echo htmlspecialchars($g['profesor'] . ' ' . $g['prof_ap']); ?>
                                </td>
                                <td style="padding: 15px; text-align: center; font-family: monospace; font-size: 0.95rem;">
                                    <div style="color: #28a745;" title="NRC Presencial"><strong>P:</strong> <?php echo $g['nrc_presencial'] ?: '---'; ?></div>
                                    <div style="color: #17a2b8; margin-top: 4px;" title="NRC Virtual"><strong>V:</strong> <?php echo $g['nrc_virtual'] ?: '---'; ?></div>
                                </td>
                                
                                <td style="padding: 15px; text-align: center; font-size: 0.85rem; white-space: nowrap;">
                                    <?php 
                                        $horario_p = '---';
                                        if ($g['dias_presencial'] && $g['inicio_presencial'] && $g['fin_presencial']) {
                                            $horario_p = htmlspecialchars($g['dias_presencial']) . ' ' . date('H:i', strtotime($g['inicio_presencial'])) . '-' . date('H:i', strtotime($g['fin_presencial']));
                                        }
                                        $horario_v = '---';
                                        if ($g['dias_virtual'] && $g['inicio_virtual'] && $g['fin_virtual']) {
                                            $horario_v = htmlspecialchars($g['dias_virtual']) . ' ' . date('H:i', strtotime($g['inicio_virtual'])) . '-' . date('H:i', strtotime($g['fin_virtual']));
                                        }
                                    ?>
                                    <div style="color: #28a745;" title="Horario Presencial"><strong>P:</strong> <?php echo $horario_p; ?></div>
                                    <div style="color: #17a2b8; margin-top: 4px;" title="Horario Virtual"><strong>V:</strong> <?php echo $horario_v; ?></div>
                                </td>

                                <td style="padding: 15px; text-align: center; font-size: 0.85rem;">
                                    <div style="color: #28a745;" title="Aula Presencial"><strong>P:</strong> <?php echo $g['aula_presencial'] ?: '---'; ?></div>
                                    <div style="color: #17a2b8; margin-top: 4px;" title="Aula Virtual"><strong>V:</strong> <?php echo $g['aula_virtual'] ?: '---'; ?></div>
                                </td>
                                <td style="padding: 15px; text-align: center;">
                                    <button class="action-btn" onclick='editGrupo(<?php echo json_encode($g); ?>)' style="background: none; border: none; color: var(--udg-blue); cursor: pointer; font-size: 1.1rem; margin-right: 10px;" title="Editar">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                    <a href="grupos_nrc.php?del_mat=<?php echo $g['materia_id']; ?>&del_prof=<?php echo $g['profesor_id']; ?>&del_ciclo=<?php echo $g['ciclo_id']; ?>" class="action-btn delete" onclick="return confirm('¿Estás seguro de borrar este grupo? Se perderán los registros de ambos NRC (Presencial y Virtual).');" style="color: #dc3545; font-size: 1.1rem;" title="Eliminar">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align:center; padding: 40px; color: var(--text-light);">
                                <i class="fas fa-inbox" style="font-size: 2.5rem; margin-bottom: 10px; display: block; color: #ddd;"></i>
                                No hay grupos registrados aún.
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <footer class="main-footer"><div class="address-bar">Copyright © 2026 E-PALE | Panel de Administración</div></footer>

    <div id="grupoModal" class="modal-overlay">
        <div class="modal-content" style="padding: 0;">
            
            <div class="modal-header" style="padding: 20px 30px; margin: 0; border-bottom: 1px solid #eee;">
                <h2 id="modalTitle" style="margin: 0;"><i class="fas fa-chalkboard"></i> Asignar Nuevo Grupo</h2>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            
            <form action="guardar_grupo.php" method="POST" id="formGrupo" style="margin: 0;">
                
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="old_nrc_p" id="oldNrcP" value="">
                <input type="hidden" name="old_nrc_v" id="oldNrcV" value="">

                <div style="padding: 20px 30px; max-height: 65vh; overflow-y: auto;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        
                        <div class="form-group" style="grid-column: span 2;">
                            <label>Profesor Asignado</label>
                            <select name="profesor_id" id="selProfesor" required>
                                <option value="">Selecciona al docente...</option>
                                <?php foreach($profesores as $p): ?>
                                    <option value="<?php echo $p['usuario_id']; ?>"><?php echo htmlspecialchars($p['nombre'] . ' ' . $p['apellido_paterno']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Materia a Impartir</label>
                            <select name="materia_id" id="selMateria" required>
                                <option value="">Selecciona...</option>
                                <?php foreach($materias_list as $m): ?>
                                    <option value="<?php echo $m['materia_id']; ?>"><?php echo htmlspecialchars($m['clave'] . ' - ' . $m['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Ciclo Escolar</label>
                            <select name="ciclo_id" id="selCiclo" required>
                                <option value="">Selecciona...</option>
                                <?php foreach($ciclos as $c): ?>
                                    <option value="<?php echo $c['ciclo_id']; ?>"><?php echo htmlspecialchars($c['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <h3 style="grid-column: span 2; margin-top: 15px; margin-bottom: 0; color: #28a745; border-bottom: 2px solid #d4edda; padding-bottom: 5px; font-size: 1.1rem;">
                            <i class="fas fa-building"></i> Modalidad Presencial
                        </h3>
                        <div class="form-group">
                            <label>NRC Presencial</label>
                            <input type="number" name="rnc_presencial" id="nrcPresencial" placeholder="Ej. 60495">
                        </div>
                        <div class="form-group">
                            <label>Aula Presencial</label>
                            <input type="text" name="aula_presencial" id="aulaPresencial" placeholder="Ej. A-202">
                        </div>
                        <div class="form-group">
                            <label>Días de Clase (P)</label>
                            <input type="text" name="dias_presencial" id="diasPresencial" placeholder="Ej. L-M" maxlength="5">
                        </div>
                        <div class="form-group" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                            <div><label>Hora Inicio</label><input type="time" name="inicio_presencial" id="iniPresencial"></div>
                            <div><label>Hora Fin</label><input type="time" name="fin_presencial" id="finPresencial"></div>
                        </div>

                        <h3 style="grid-column: span 2; margin-top: 15px; margin-bottom: 0; color: #17a2b8; border-bottom: 2px solid #d1ecf1; padding-bottom: 5px; font-size: 1.1rem;">
                            <i class="fas fa-laptop-house"></i> Modalidad Virtual
                        </h3>
                        <div class="form-group">
                            <label>NRC Virtual</label>
                            <input type="number" name="rnc_virtual" id="nrcVirtual" placeholder="Ej. 60501">
                        </div>
                        <div class="form-group">
                            <label>Aula Virtual / Plataforma</label>
                            <input type="text" name="aula_virtual" id="aulaVirtual" placeholder="Ej. Zoom, Meet">
                        </div>
                        <div class="form-group">
                            <label>Días de Clase (V)</label>
                            <input type="text" name="dias_virtual" id="diasVirtual" placeholder="Ej. M-J" maxlength="5">
                        </div>
                        <div class="form-group" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                            <div><label>Hora Inicio</label><input type="time" name="inicio_virtual" id="iniVirtual"></div>
                            <div><label>Hora Fin</label><input type="time" name="fin_virtual" id="finVirtual"></div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer" style="padding: 20px 30px; margin: 0; border-top: 1px solid #eee; background-color: #fcfcfc; border-bottom-left-radius: 12px; border-bottom-right-radius: 12px;">
                    <button type="button" class="btn-cancel" onclick="closeModal()">Cancelar</button>
                    <button type="submit" class="btn-save" id="btnSubmit"><i class="fas fa-save"></i> Guardar Grupo</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleMobileMenu() {
            document.getElementById('navWrapper').classList.toggle('active');
            document.getElementById('menuOverlay').classList.toggle('active');
        }

        const modal = document.getElementById('grupoModal');
        const overlayMenu = document.getElementById('menuOverlay');

        function openModal() {
            document.getElementById('formGrupo').reset();
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-chalkboard"></i> Asignar Nuevo Grupo';
            document.getElementById('formAction').value = 'create';
            document.getElementById('oldNrcP').value = '';
            document.getElementById('oldNrcV').value = '';
            document.getElementById('btnSubmit').innerHTML = '<i class="fas fa-plus"></i> Crear Grupo';
            
            document.getElementById('nrcPresencial').readOnly = false;
            document.getElementById('nrcVirtual').readOnly = false;
            document.getElementById('nrcPresencial').classList.remove('input-readonly');
            document.getElementById('nrcVirtual').classList.remove('input-readonly');

            modal.style.display = 'flex';
        }

        function editGrupo(g) {
            document.getElementById('formGrupo').reset();
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Editar Grupo';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('btnSubmit').innerHTML = '<i class="fas fa-save"></i> Guardar Cambios';
            
            document.getElementById('oldNrcP').value = g.nrc_presencial || '';
            document.getElementById('oldNrcV').value = g.nrc_virtual || '';

            document.getElementById('selProfesor').value = g.profesor_id;
            document.getElementById('selMateria').value = g.materia_id;
            document.getElementById('selCiclo').value = g.ciclo_id;

            document.getElementById('nrcPresencial').value = g.nrc_presencial || '';
            document.getElementById('aulaPresencial').value = g.aula_presencial || '';
            document.getElementById('diasPresencial').value = g.dias_presencial || '';
            document.getElementById('iniPresencial').value = g.inicio_presencial || '';
            document.getElementById('finPresencial').value = g.fin_presencial || '';

            if(g.nrc_presencial) {
                document.getElementById('nrcPresencial').readOnly = true;
                document.getElementById('nrcPresencial').classList.add('input-readonly');
            } else {
                document.getElementById('nrcPresencial').readOnly = false;
                document.getElementById('nrcPresencial').classList.remove('input-readonly');
            }

            document.getElementById('nrcVirtual').value = g.nrc_virtual || '';
            document.getElementById('aulaVirtual').value = g.aula_virtual || '';
            document.getElementById('diasVirtual').value = g.dias_virtual || '';
            document.getElementById('iniVirtual').value = g.inicio_virtual || '';
            document.getElementById('finVirtual').value = g.fin_virtual || '';

            if(g.nrc_virtual) {
                document.getElementById('nrcVirtual').readOnly = true;
                document.getElementById('nrcVirtual').classList.add('input-readonly');
            } else {
                document.getElementById('nrcVirtual').readOnly = false;
                document.getElementById('nrcVirtual').classList.remove('input-readonly');
            }

            modal.style.display = 'flex';
        }

        function closeModal() { modal.style.display = 'none'; }

        window.onclick = function(e) { 
            if(e.target == modal) closeModal(); 
            if(e.target == overlayMenu) toggleMobileMenu();
        };
    </script>
</body>
</html>
