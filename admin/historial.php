<?php
session_start();
require '../db.php';
require_once '../security.php';

// La validación estricta se omite aquí porque es una vista de solo lectura mediante GET.

if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ADMIN') {
    header("Location: ../index.php");
    exit;
}

// --- Lógica de Filtros y Búsqueda por Servidor ---
$filtro_categoria = $_GET['cat'] ?? 'Todos';
$busqueda = $_GET['q'] ?? '';
$filtro_fecha = $_GET['fecha'] ?? '';

$sql_base = "SELECT h.*, u.nombre as admin_nombre, u.correo as admin_correo 
             FROM historial_admin h 
             JOIN usuarios u ON h.admin_id = u.usuario_id 
             WHERE 1=1";
$params = [];

if ($filtro_categoria !== 'Todos') {
    if ($filtro_categoria === 'Grupos / Idioma') {
        $sql_base .= " AND h.categoria = 'Grupos'";
    } else {
        $sql_base .= " AND h.categoria = ?";
        $params[] = $filtro_categoria;
    }
}

if (!empty($filtro_fecha)) {
    $sql_base .= " AND DATE(h.fecha) = ?";
    $params[] = $filtro_fecha;
}

if (!empty($busqueda)) {
    $sql_base .= " AND (h.afectado LIKE ? OR h.detalle LIKE ? OR u.nombre LIKE ? OR h.titulo LIKE ?)";
    $q_like = "%$busqueda%";
    $params = array_merge($params, [$q_like, $q_like, $q_like, $q_like]);
}

$sql_base .= " ORDER BY h.fecha DESC LIMIT 150";
$stmt = $pdo->prepare($sql_base);
$stmt->execute($params);
$resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Agrupación por Fecha para la Línea de Tiempo ---
$historial_agrupado = [];
$hoy = date('Y-m-d');
$ayer = date('Y-m-d', strtotime('-1 day'));

foreach ($resultados as $row) {
    $fecha_solo = date('Y-m-d', strtotime($row['fecha']));
    if ($fecha_solo == $hoy) {
        $grupo = 'Hoy';
    } elseif ($fecha_solo == $ayer) {
        $grupo = 'Ayer';
    } else {
        $meses = ['01' => 'Ene', '02' => 'Feb', '03' => 'Mar', '04' => 'Abr', '05' => 'May', '06' => 'Jun', '07' => 'Jul', '08' => 'Ago', '09' => 'Sep', '10' => 'Oct', '11' => 'Nov', '12' => 'Dic'];
        $grupo = date('d', strtotime($row['fecha'])) . ' ' . strtolower($meses[date('m', strtotime($row['fecha']))]) . ' ' . date('Y', strtotime($row['fecha']));
    }

    if (!isset($historial_agrupado[$grupo])) {
        $historial_agrupado[$grupo] = [];
    }
    $historial_agrupado[$grupo][] = $row;
}

// Función auxiliar para iconos y colores según tipo de acción
function getActionStyle(string $tipo_accion)
{
    switch ($tipo_accion) {
        case 'Nivel':
            return ['icon' => 'fas fa-layer-group', 'color' => 'purple'];
        case 'Calificación':
            return ['icon' => 'far fa-star', 'color' => 'teal'];
        case 'Estado':
            return ['icon' => 'fas fa-toggle-on', 'color' => 'orange'];
        case 'Archivo':
            return ['icon' => 'fas fa-file-upload', 'color' => 'teal'];
        case 'Grupo':
            return ['icon' => 'fas fa-exchange-alt', 'color' => 'purple'];
        case 'Edición':
            return ['icon' => 'fas fa-pen', 'color' => 'blue'];
        case 'Creación':
            return ['icon' => 'fas fa-user-plus', 'color' => 'green'];
        default:
            return ['icon' => 'fas fa-info-circle', 'color' => 'blue'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Modificaciones | E-PALE Admin</title>
    <link rel="stylesheet" href="../css/estilos.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>

    <?php include 'menu_admin.php'; ?>

    <main class="main-content">
        <div style="margin-bottom: 25px;">
            <h1 style="color: var(--udg-blue); margin: 0; display:flex; align-items:center; gap:10px;">
                <i class="fas fa-history"></i> Historial de Modificaciones
            </h1>
            <p style="color: #666; margin: 5px 0 0 0;">Registro inalterable de acciones administrativas realizadas en la plataforma.</p>
        </div>

        <form method="GET" action="historial.php" class="toolbar mt-20">
            <i class="fas fa-search icon-muted" style="align-self:center;"></i>
            <input type="text" name="q" class="search-input" placeholder="Buscar y presionar Enter..." value="<?php echo htmlspecialchars($busqueda); ?>">
            
            <input type="date" name="fecha" class="filter-select" style="max-width: 150px; cursor: pointer;" title="Filtrar por fecha" value="<?php echo htmlspecialchars($filtro_fecha); ?>" onchange="this.form.submit()">
            
            <select name="cat" class="filter-select" style="max-width: 200px; cursor: pointer;" onchange="this.form.submit()">
                <?php
                $categorias = ['Todos', 'Usuarios', 'Calificaciones', 'Archivos', 'Grupos / Idioma'];
                foreach ($categorias as $c):
                    $sel = ($filtro_categoria === $c) ? 'selected' : '';
                    echo "<option value=\"$c\" $sel>$c</option>";
                endforeach; 
                ?>
            </select>
        </form>

        <p style="color: #888; font-size: 0.9rem; border-bottom: 1px solid #eee; padding-bottom: 15px;">
            Mostrando los últimos <?php echo count($resultados); ?> registros encontrados
        </p>

        <?php if (count($resultados) > 0): ?>
            <div class="historial-timeline">
                <?php foreach ($historial_agrupado as $fecha_grupo => $eventos): ?>

                    <div class="timeline-date-divider">
                        <span class="timeline-date-badge"><?php echo htmlspecialchars($fecha_grupo); ?></span>
                    </div>

                    <?php foreach ($eventos as $ev):
                        $style = getActionStyle($ev['tipo_accion']);
                        $color = $style['color'];
                        $icon = $style['icon'];
                        $hora = date('h:i a', strtotime($ev['fecha']));
                        $ev_cat = htmlspecialchars($ev['categoria']);
                        $ev_date = date('Y-m-d', strtotime($ev['fecha']));
                    ?>
                        <div class="timeline-item" data-cat="<?php echo $ev_cat; ?>" data-date="<?php echo $ev_date; ?>">
                            <div class="timeline-dot dot-<?php echo $color; ?>"></div>
                            <div class="timeline-card">
                                <div class="timeline-card-header">
                                    <h4 class="timeline-card-title">
                                        <span class="badge-historial badge-<?php echo $color; ?>">
                                            <i class="<?php echo $icon; ?>"></i> <?php echo htmlspecialchars($ev['tipo_accion']); ?>
                                        </span>
                                        <?php echo htmlspecialchars($ev['titulo']); ?>
                                    </h4>
                                    <span class="timeline-time"><i class="far fa-clock"></i> <?php echo $hora; ?></span>
                                </div>

                                <div class="timeline-info-row">
                                    <span class="timeline-info-label">Afectado:</span>
                                    <span class="timeline-info-value" style="background:#f1f3f5; padding: 2px 8px; border-radius:4px; font-family: monospace; font-size: 0.9rem;">
                                        <?php echo htmlspecialchars($ev['afectado']); ?>
                                    </span>
                                </div>
                                <div class="timeline-info-row">
                                    <span class="timeline-info-label">Detalle:</span>
                                    <span class="timeline-info-value detail-<?php echo $color; ?>">
                                        <?php 
                                            $det_html = htmlspecialchars($ev['detalle']);
                                            $det_html = preg_replace('/(?<=:\s|^)(.*?)\s*→\s*(.*?)(?=\s*•|$)/', '<span class="old-value">$1</span><i class="fas fa-arrow-right transition-arrow"></i><span class="new-value">$2</span>', $det_html);
                                            echo $det_html; 
                                        ?>
                                    </span>
                                </div>

                                <div class="timeline-footer">
                                    <div class="timeline-admin-avatar">
                                        <?php echo substr(strtoupper($ev['admin_nombre']), 0, 1); ?>
                                    </div>
                                    <span><strong><?php echo htmlspecialchars(strtok($ev['admin_nombre'], " ")); ?></strong> &middot; <?php echo htmlspecialchars($ev['admin_correo']); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-history"></i>
                <p>No se encontraron registros de modificaciones con los filtros actuales.</p>
            </div>
        <?php endif; ?>

    </main>
</body>

</html>