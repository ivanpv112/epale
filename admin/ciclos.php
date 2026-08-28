<?php
session_start();
require '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ADMIN') { header("Location: ../index.php"); exit; }

$mensaje = ''; $tipo_mensaje = '';

// =======================================================
// ABRIR O CERRAR UN CICLO COMPLETO (REGLA DE ORO)
// =======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ESCUDO CSRF
    if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Error de Seguridad Crítico: Token CSRF inválido o ausente. Petición bloqueada.");
    }

    if (isset($_POST['cerrar_grupos'])) {
        $id_cerrar = $_POST['ciclo_id'];
        // Mueve todos los grupos activos de este ciclo a CERRADO
        $pdo->prepare("UPDATE grupos SET estado = 'CERRADO' WHERE ciclo_id = ? AND estado = 'ACTIVO'")->execute([$id_cerrar]);
        // Marca el ciclo en sí como inactivo
        $pdo->prepare("UPDATE ciclos SET activo = 0 WHERE ciclo_id = ?")->execute([$id_cerrar]);
        $mensaje = "El ciclo ha sido finalizado y mandado al archivo histórico."; $tipo_mensaje = "success";
    }
    if (isset($_POST['abrir_grupos'])) {
        $id_abrir = $_POST['ciclo_id'];
        
        // LA REGLA DE ORO: Solo puede existir un ciclo actual a la vez.
        // 1. Apagamos TODOS los ciclos
        $pdo->exec("UPDATE ciclos SET activo = 0");
        // 2. Mandamos al historial (CERRADO) todos los grupos que estuvieran activos en otros ciclos
        $pdo->exec("UPDATE grupos SET estado = 'CERRADO' WHERE estado = 'ACTIVO'");
        
        // 3. Encendemos ÚNICAMENTE el ciclo seleccionado y reactivamos sus grupos
        $pdo->prepare("UPDATE ciclos SET activo = 1 WHERE ciclo_id = ?")->execute([$id_abrir]);
        $pdo->prepare("UPDATE grupos SET estado = 'ACTIVO' WHERE ciclo_id = ?")->execute([$id_abrir]);
        
        $mensaje = "¡Listo! Este es ahora el Ciclo Actual. Los demás se han archivado automáticamente."; $tipo_mensaje = "success";
    }
}

// =======================================================
// OBTENER TODOS LOS DATOS PARA EL ÁRBOL
// =======================================================
// Agrupamos usando g.estado (ACTIVO/CERRADO)
$sql = "SELECT c.ciclo_id, c.nombre as ciclo_nombre, c.activo,
               COALESCE(g.estado, 'CERRADO') as grupo_estado,
               m.nombre as idioma, m.nivel,
               g.clave_grupo, g.nrc, g.cupo,
               u.nombre as prof_nombre, u.apellido_paterno as prof_ap,
               (SELECT COUNT(DISTINCT i.alumno_id) FROM inscripciones i JOIN grupos g2 ON i.nrc = g2.nrc WHERE g2.clave_grupo = g.clave_grupo AND i.estatus='INSCRITO') as inscritos
        FROM ciclos c
        LEFT JOIN grupos g ON c.ciclo_id = g.ciclo_id
        LEFT JOIN materias m ON g.materia_id = m.materia_id
        LEFT JOIN usuarios u ON g.profesor_id = u.usuario_id
        ORDER BY c.activo DESC, c.nombre DESC, m.nombre ASC, m.nivel ASC";

$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Procesar los datos separando las carpetas raíz por ESTADO (Activo vs Inactivo)
$tree = [];
foreach ($rows as $row) {
    $c_name = $row['ciclo_nombre'];
    $es_activo = $row['activo'];
    
    // Generamos una clave única combinando el Ciclo y si es el actual
    $root_key = $c_name . '_' . $es_activo;

    if (!isset($tree[$root_key])) {
        $tree[$root_key] = [
            'id' => $row['ciclo_id'],
            'nombre' => $c_name,
            'es_activo' => $es_activo,
            'idiomas' => []
        ];
    }
    
    if (!$row['idioma']) continue; // Si es un ciclo vacío recién creado, lo omitimos del desglose interno
    
    $lang = $row['idioma'];
    $lvl = $row['nivel'];
    
    // Nivel Idioma
    if (!isset($tree[$root_key]['idiomas'][$lang])) $tree[$root_key]['idiomas'][$lang] = [];
    
    // Nivel Nivel (Ej. 1, 2, 3...)
    if (!isset($tree[$root_key]['idiomas'][$lang][$lvl])) $tree[$root_key]['idiomas'][$lang][$lvl] = [];

    // Guardar Clase
    $tree[$root_key]['idiomas'][$lang][$lvl][] = [
        'nrc' => $row['nrc'],
        'clave' => $row['clave_grupo'],
        'profesor' => trim($row['prof_nombre'] . ' ' . $row['prof_ap']),
        'cupo' => $row['cupo'],
        'inscritos' => $row['inscritos']
    ];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Ciclos | Admin</title>
    <link rel="stylesheet" href="../css/estudiante.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

    <?php include 'menu_admin.php'; ?>

    <main class="main-content">
        <div class="page-title-center" style="margin-bottom: 30px;">
            <h1><i class="fas fa-calendar-alt"></i> Ciclos Escolares</h1>
            <p>Administra los ciclos, finaliza los semestres terminados y prepara los próximos ciclos.</p>
        </div>

        <?php if($mensaje): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({ title: '<?php echo ($tipo_mensaje == "success") ? "¡Éxito!" : "Error"; ?>', text: '<?php echo addslashes($mensaje); ?>', icon: '<?php echo $tipo_mensaje; ?>', confirmButtonColor: 'var(--udg-blue)' });
                    window.history.replaceState({}, document.title, window.location.pathname);
                });
            </script>
        <?php endif; ?>

        <?php if (empty($tree)): ?>
            <div class="card" style="text-align: center; padding: 50px;">
                <i class="fas fa-folder-open" style="font-size: 3rem; color: #ddd; margin-bottom: 15px;"></i>
                <h3 style="color: #666;">No hay ciclos registrados</h3>
            </div>
        <?php else: ?>
            <?php foreach ($tree as $root_key => $folder): ?>
                
                <details class="tree-node" <?php echo ($folder['es_activo'] == 1) ? 'open' : ''; ?>>
                    <summary>
                        <div style="display: flex; align-items: center;">
                            <i class="fas <?php echo ($folder['es_activo'] == 1) ? 'fa-folder-open' : 'fa-folder'; ?>" style="margin-right: 10px; color: var(--udg-blue);"></i>
                            <?php echo htmlspecialchars($folder['nombre']); ?> 
                            
                            <?php if ($folder['es_activo'] == 1): ?>
                                <span style="background-color: #d1e7dd; color: #0f5132; border-radius: 20px; padding: 2px 8px; font-size: 0.75rem; font-weight: bold; margin-left: 10px;">
                                    <i class="fas fa-circle" style="font-size: 0.5rem; vertical-align: middle; margin-right: 3px;"></i>CICLO ACTUAL
                                </span>
                            <?php else: ?>
                                <span style="background-color: #e2e3e5; color: #383d41; border-radius: 20px; padding: 2px 8px; font-size: 0.75rem; font-weight: bold; margin-left: 10px;">
                                    <i class="fas fa-archive" style="font-size: 0.6rem; vertical-align: middle; margin-right: 3px;"></i>INACTIVO / HISTÓRICO
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <form method="POST" style="margin:0;" onclick="event.stopPropagation();">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="ciclo_id" value="<?php echo $folder['id']; ?>">
                            <?php if ($folder['es_activo'] == 1): ?>
                                <button type="submit" name="cerrar_grupos" style="background: white; border: 1px solid #dc3545; color: #dc3545; padding: 5px 12px; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 0.85rem; transition: 0.2s;" onmouseover="this.style.background='#dc3545'; this.style.color='white';" onmouseout="this.style.background='white'; this.style.color='#dc3545';" onclick="return confirm('¿Estás seguro de finalizar el semestre actual? Esto mandará todo al historial.');">
                                    <i class="fas fa-power-off"></i> Finalizar Ciclo
                                </button>
                            <?php else: ?>
                                <button type="submit" name="abrir_grupos" style="background: white; border: 1px solid #28a745; color: #28a745; padding: 5px 12px; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 0.85rem; transition: 0.2s;" onmouseover="this.style.background='#28a745'; this.style.color='white';" onmouseout="this.style.background='white'; this.style.color='#28a745';" onclick="return confirm('ATENCIÓN: Al establecer este ciclo como ACTUAL, todos los demás ciclos activos se cerrarán automáticamente. ¿Deseas continuar?');">
                                    <i class="fas fa-check-circle"></i> Establecer como Ciclo Actual
                                </button>
                            <?php endif; ?>
                        </form>
                    </summary>
                    
                    <div class="tree-content">
                        <?php if (empty($folder['idiomas'])): ?>
                            <p style="color: #888; font-style: italic; margin: 0;">No hay materias registradas en esta categoría.</p>
                        <?php else: ?>
                            <?php foreach ($folder['idiomas'] as $lang_name => $levels): ?>
                                <details class="tree-lang">
                                    <summary><i class="fas fa-language" style="margin-right: 5px;"></i> <?php echo htmlspecialchars($lang_name); ?></summary>
                                    <div class="tree-content" style="padding: 5px 10px;">
                                        
                                        <?php foreach ($levels as $lvl_name => $classes): ?>
                                            <details class="tree-level">
                                                <summary><i class="fas fa-layer-group" style="margin-right: 5px;"></i> Nivel <?php echo htmlspecialchars($lvl_name); ?></summary>
                                                <div class="tree-content" style="padding: 10px; background: white;">
                                                    
                                                    <?php foreach ($classes as $clase): ?>
                                                        <div class="tree-class-item">
                                                            <div>
                                                                <div style="font-weight: bold; color: var(--udg-blue); font-size: 1.05rem;">NRC: <?php echo htmlspecialchars($clase['nrc']); ?></div>
                                                                <div style="color: #555; font-size: 0.85rem;"><i class="fas fa-chalkboard-teacher"></i> <?php echo htmlspecialchars($clase['profesor'] ?: 'Sin asignar'); ?></div>
                                                            </div>
                                                            <div style="display: flex; align-items: center; gap: 20px;">
                                                                <div style="text-align: right;">
                                                                    <div style="font-size: 0.8rem; color: #888; font-weight:bold;">Alumnos</div>
                                                                    <div style="color: <?php echo ($clase['inscritos'] >= $clase['cupo']) ? '#dc3545' : '#28a745'; ?>; font-weight: bold;">
                                                                        <?php echo $clase['inscritos']; ?> / <?php echo $clase['cupo']; ?>
                                                                    </div>
                                                                </div>
                                                                <a href="gestionar_grupo.php?clave=<?php echo urlencode($clase['clave']); ?>" class="btn-save" style="padding: 8px 15px; font-size: 0.85rem; text-decoration: none; <?php if($folder['es_activo'] == 0) echo 'background:#6c757d;'; ?>">
                                                                    <i class="fas fa-eye"></i> Ver Clase
                                                                </a>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                    
                                                </div>
                                            </details>
                                        <?php endforeach; ?>
                                        
                                    </div>
                                </details>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </details>
                
            <?php endforeach; ?>
        <?php endif; ?>

    </main>

    <footer class="main-footer"><div class="address-bar">Copyright © 2026 E-PALE | Panel de Administración</div></footer>
    <script>
        function toggleMobileMenu() {
            document.getElementById('navWrapper').classList.toggle('active');
            document.getElementById('menuOverlay').classList.toggle('active');
        }
    </script>
</body>
</html>
