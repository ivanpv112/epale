<?php
/**
 * api_historial_avisos.php
 * Endpoint asíncrono para consultar ciclos con historial y avisos de ciclos anteriores o seleccionado.
 * Compartido para alumnos y profesores.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';

try {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['rol'])) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
        exit;
    }

    $user_id = (int)$_SESSION['user_id'];
    $rol = $_SESSION['rol'];
    $action = $_GET['action'] ?? 'ciclos';

    // Función para convertir a romano
    if (!function_exists('ha_nivel_romano')) {
        function ha_nivel_romano(int|string|null $nivel): string {
            $n = (int)$nivel;
            $mapa = [1=>'I', 2=>'II', 3=>'III', 4=>'IV', 5=>'V', 6=>'VI', 7=>'VII', 8=>'VIII', 9=>'IX', 10=>'X'];
            return $mapa[$n] ?? (string)$nivel;
        }
    }

    // -------------------------------------------------------------
    // ACCIÓN 1: Obtener la lista de ciclos cursados / impartidos
    // -------------------------------------------------------------
    if ($action === 'ciclos') {
        $ciclos = [];

        if ($rol === 'ALUMNO') {
            $alumno_id = (int)($_SESSION['alumno_id'] ?? 0);
            if ($alumno_id <= 0) {
                $st_al = $pdo->prepare("SELECT alumno_id FROM alumnos WHERE usuario_id = ?");
                $st_al->execute([$user_id]);
                $alumno_id = (int)$st_al->fetchColumn();
            }

            if ($alumno_id > 0) {
                $stmt = $pdo->prepare("
                    SELECT DISTINCT c.ciclo_id, c.nombre, c.activo
                    FROM inscripciones i
                    JOIN grupos g ON i.nrc = g.nrc
                    JOIN ciclos c ON g.ciclo_id = c.ciclo_id
                    WHERE i.alumno_id = ? AND c.activo = 0
                    ORDER BY c.nombre DESC, c.ciclo_id DESC
                ");
                $stmt->execute([$alumno_id]);
                $ciclos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } elseif ($rol === 'PROFESOR') {
            $stmt = $pdo->prepare("
                SELECT DISTINCT c.ciclo_id, c.nombre, c.activo
                FROM grupos g
                JOIN ciclos c ON g.ciclo_id = c.ciclo_id
                WHERE g.profesor_id = ? AND c.activo = 0
                ORDER BY c.nombre DESC, c.ciclo_id DESC
            ");
            $stmt->execute([$user_id]);
            $ciclos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode([
            'status' => 'success',
            'data' => [
                'ciclos' => $ciclos
            ]
        ]);
        exit;
    }

    // -------------------------------------------------------------
    // ACCIÓN 2: Obtener avisos asociados a un ciclo específico
    // -------------------------------------------------------------
    if ($action === 'avisos') {
        $ciclo_id = (int)($_GET['ciclo_id'] ?? 0);
        if ($ciclo_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Ciclo no especificado']);
            exit;
        }

        $avisos = [];

        if ($rol === 'ALUMNO') {
            $alumno_id = (int)($_SESSION['alumno_id'] ?? 0);
            if ($alumno_id <= 0) {
                $st_al = $pdo->prepare("SELECT alumno_id FROM alumnos WHERE usuario_id = ?");
                $st_al->execute([$user_id]);
                $alumno_id = (int)$st_al->fetchColumn();
            }

            // Obtener NRCs, materias e idiomas del alumno en este ciclo
            $stmt_g = $pdo->prepare("
                SELECT g.nrc, m.materia_id, m.nombre
                FROM inscripciones i
                JOIN grupos g ON i.nrc = g.nrc
                JOIN materias m ON g.materia_id = m.materia_id
                WHERE i.alumno_id = ? AND g.ciclo_id = ?
            ");
            $stmt_g->execute([$alumno_id, $ciclo_id]);
            $grupos = $stmt_g->fetchAll(PDO::FETCH_ASSOC);

            $nrcs = [];
            $materias_ids = [];
            $idiomas = [];
            foreach ($grupos as $r) {
                $nrcs[] = (int)$r['nrc'];
                $materias_ids[] = (int)$r['materia_id'];
                $idm = trim(preg_replace('/[0-9]+/', '', $r['nombre']));
                if (!empty($idm) && !in_array($idm, $idiomas)) $idiomas[] = $idm;
            }

            // 1. Avisos de Docentes de sus clases en ese ciclo
            if (count($nrcs) > 0) {
                $in_nrc = implode(',', array_fill(0, count($nrcs), '?'));
                $sql_prof = "
                    SELECT a.aviso_id, a.titulo, a.cuerpo, a.tipo_comunicado, a.imagen,
                           a.fecha_inicio, a.fecha_expiracion, a.fecha_creacion,
                           m.nombre AS materia_nombre, m.nivel AS materia_nivel, g.clave_grupo,
                           CONCAT(u.nombre, ' ', u.apellido_paterno) AS autor_nombre
                    FROM avisos a
                    JOIN grupos g ON a.audiencia_ref = g.nrc
                    JOIN materias m ON g.materia_id = m.materia_id
                    JOIN usuarios u ON a.autor_id = u.usuario_id
                    WHERE a.tipo_audiencia = 'GRUPO' AND a.audiencia_ref IN ($in_nrc)
                    ORDER BY a.fecha_creacion DESC
                ";
                $st_p = $pdo->prepare($sql_prof);
                $st_p->execute($nrcs);
                while ($row = $st_p->fetch(PDO::FETCH_ASSOC)) {
                    $esTarea = ($row['tipo_comunicado'] === 'ASIGNACION');
                    $avisos[] = [
                        'key' => 'hist_prof_' . $row['aviso_id'],
                        'origen' => 'CLASE',
                        'categoria' => $esTarea ? 'Tarea de Clase' : 'Aviso Docente',
                        'tipo_badge' => $esTarea ? 'neon-sub-tarea' : 'neon-sub-aviso',
                        'borde_card' => $esTarea ? 'notif-item-tarea-clase' : 'notif-item-aviso-clase',
                        'icono' => $esTarea ? 'fas fa-tasks' : 'fas fa-bullhorn',
                        'materia' => $row['materia_nombre'] . ' ' . ha_nivel_romano($row['materia_nivel']),
                        'titulo' => $row['titulo'],
                        'cuerpo' => $row['cuerpo'],
                        'cuerpo_corto' => mb_substr(strip_tags($row['cuerpo']), 0, 95) . (mb_strlen($row['cuerpo']) > 95 ? '...' : ''),
                        'imagen' => $row['imagen'] ? '../' . ltrim($row['imagen'], '/') : null,
                        'autor' => 'Prof. ' . $row['autor_nombre'],
                        'fecha_formateada' => date('d/m/Y h:i A', strtotime($row['fecha_inicio'] ?: $row['fecha_creacion'])),
                        'fecha_inicio' => date('d/m/Y h:i A', strtotime($row['fecha_inicio'] ?: $row['fecha_creacion'])),
                        'fecha_fin' => !empty($row['fecha_expiracion']) ? date('d/m/Y h:i A', strtotime($row['fecha_expiracion'])) : 'Sin fecha límite'
                    ];
                }
            }

            // 2. Avisos Institucionales emitidos para los idiomas / materias / grupos del alumno en este ciclo
            $where_admin = ["tipo_audiencia = 'GLOBAL'"];
            $params_admin = [];

            if (count($idiomas) > 0) {
                $in_idm = implode(',', array_fill(0, count($idiomas), '?'));
                $where_admin[] = "(tipo_audiencia = 'IDIOMA' AND audiencia_ref IN ($in_idm))";
                $params_admin = array_merge($params_admin, $idiomas);
            }
            if (count($materias_ids) > 0) {
                $in_mat = implode(',', array_fill(0, count($materias_ids), '?'));
                $where_admin[] = "(tipo_audiencia = 'MATERIA' AND audiencia_ref IN ($in_mat))";
                $params_admin = array_merge($params_admin, $materias_ids);
            }
            if (count($nrcs) > 0) {
                $in_n = implode(',', array_fill(0, count($nrcs), '?'));
                $where_admin[] = "(tipo_audiencia = 'GRUPO' AND audiencia_ref IN ($in_n))";
                $params_admin = array_merge($params_admin, $nrcs);
            }

            $sql_admin = "
                SELECT aviso_id, titulo, cuerpo, tipo_audiencia, audiencia_ref, imagen, fecha_creacion, fecha_expiracion
                FROM avisos
                WHERE autor_id IS NULL AND ciclo_id = ? AND rol_destinatario IN ('TODOS', 'ALUMNO') AND (" . implode(" OR ", $where_admin) . ")
                ORDER BY fecha_creacion DESC
                LIMIT 30
            ";
            $st_adm = $pdo->prepare($sql_admin);
            $st_adm->execute(array_merge([$ciclo_id], $params_admin));
            while ($row = $st_adm->fetch(PDO::FETCH_ASSOC)) {
                $badgeClase = 'neon-sub-global';
                $bordeClase = 'notif-item-inst-global';
                $label = 'Comunicado General';

                if ($row['tipo_audiencia'] === 'IDIOMA') {
                    $badgeClase = 'neon-sub-idioma';
                    $bordeClase = 'notif-item-inst-idioma';
                    $label = 'Aviso: ' . $row['audiencia_ref'];
                } elseif ($row['tipo_audiencia'] === 'MATERIA') {
                    $badgeClase = 'neon-sub-materia';
                    $bordeClase = 'notif-item-inst-materia';
                    $label = 'Aviso de Materia';
                }

                $avisos[] = [
                    'key' => 'hist_adm_' . $row['aviso_id'],
                    'origen' => 'INSTITUCIONAL',
                    'categoria' => $label,
                    'tipo_badge' => $badgeClase,
                    'borde_card' => $bordeClase,
                    'icono' => 'fas fa-university',
                    'materia' => $label,
                    'titulo' => $row['titulo'],
                    'cuerpo' => $row['cuerpo'],
                    'cuerpo_corto' => mb_substr(strip_tags($row['cuerpo']), 0, 95) . (mb_strlen($row['cuerpo']) > 95 ? '...' : ''),
                    'imagen' => $row['imagen'] ? '../' . ltrim($row['imagen'], '/') : null,
                    'autor' => 'Coordinación E-PALE',
                    'fecha_formateada' => date('d/m/Y h:i A', strtotime($row['fecha_creacion'])),
                    'fecha_inicio' => date('d/m/Y h:i A', strtotime($row['fecha_creacion'])),
                    'fecha_fin' => !empty($row['fecha_expiracion']) ? date('d/m/Y h:i A', strtotime($row['fecha_expiracion'])) : 'Sin fecha límite'
                ];
            }
        } elseif ($rol === 'PROFESOR') {
            // Para el profesor: Comunicados administrativos de ese ciclo y avisos que el docente emitió a sus grupos de ese ciclo
            $stmt_g = $pdo->prepare("
                SELECT DISTINCT g.nrc, g.clave_grupo, m.materia_id, m.nombre AS materia_nombre, m.nivel
                FROM grupos g
                JOIN materias m ON g.materia_id = m.materia_id
                WHERE g.profesor_id = ? AND g.ciclo_id = ?
            ");
            $stmt_g->execute([$user_id, $ciclo_id]);
            $grupos = $stmt_g->fetchAll(PDO::FETCH_ASSOC);

            $nrcs = [];
            $materias_ids = [];
            $idiomas = [];
            foreach ($grupos as $r) {
                $nrcs[] = (int)$r['nrc'];
                $materias_ids[] = (int)$r['materia_id'];
                $idm = trim(preg_replace('/[0-9]+/', '', $r['materia_nombre']));
                if (!empty($idm) && !in_array($idm, $idiomas)) $idiomas[] = $idm;
            }

            // 1. Avisos emitidos por el profesor en ese ciclo
            if (count($nrcs) > 0) {
                $in_nrc = implode(',', array_fill(0, count($nrcs), '?'));
                $sql_prof = "
                    SELECT a.aviso_id, a.titulo, a.cuerpo, a.tipo_comunicado, a.imagen,
                           a.fecha_inicio, a.fecha_expiracion, a.fecha_creacion,
                           m.nombre AS materia_nombre, m.nivel AS materia_nivel, g.clave_grupo
                    FROM avisos a
                    JOIN grupos g ON a.audiencia_ref = g.nrc
                    JOIN materias m ON g.materia_id = m.materia_id
                    WHERE a.autor_id = ? AND a.tipo_audiencia = 'GRUPO' AND a.audiencia_ref IN ($in_nrc)
                    ORDER BY a.fecha_creacion DESC
                ";
                $st_p = $pdo->prepare($sql_prof);
                $st_p->execute(array_merge([$user_id], $nrcs));
                while ($row = $st_p->fetch(PDO::FETCH_ASSOC)) {
                    $esTarea = ($row['tipo_comunicado'] === 'ASIGNACION');
                    $avisos[] = [
                        'key' => 'hist_prof_' . $row['aviso_id'],
                        'origen' => 'PROPIO',
                        'categoria' => $esTarea ? 'Tarea enviada' : 'Aviso enviado',
                        'tipo_badge' => $esTarea ? 'neon-sub-tarea' : 'neon-sub-aviso',
                        'borde_card' => $esTarea ? 'notif-item-tarea-clase' : 'notif-item-aviso-clase',
                        'icono' => $esTarea ? 'fas fa-tasks' : 'fas fa-bullhorn',
                        'materia' => $row['materia_nombre'] . ' ' . ha_nivel_romano($row['materia_nivel']) . ' (' . $row['clave_grupo'] . ')',
                        'titulo' => $row['titulo'],
                        'cuerpo' => $row['cuerpo'],
                        'cuerpo_corto' => mb_substr(strip_tags($row['cuerpo']), 0, 95) . (mb_strlen($row['cuerpo']) > 95 ? '...' : ''),
                        'imagen' => $row['imagen'] ? '../' . ltrim($row['imagen'], '/') : null,
                        'autor' => 'Tú (Docente Titular)',
                        'fecha_formateada' => date('d/m/Y h:i A', strtotime($row['fecha_inicio'] ?: $row['fecha_creacion'])),
                        'fecha_inicio' => date('d/m/Y h:i A', strtotime($row['fecha_inicio'] ?: $row['fecha_creacion'])),
                        'fecha_fin' => !empty($row['fecha_expiracion']) ? date('d/m/Y h:i A', strtotime($row['fecha_expiracion'])) : 'Sin fecha límite'
                    ];
                }
            }

            // 2. Avisos institucionales para el profesor en ese periodo
            $where_admin = ["tipo_audiencia = 'GLOBAL'"];
            $params_admin = [];

            if (count($idiomas) > 0) {
                $in_idm = implode(',', array_fill(0, count($idiomas), '?'));
                $where_admin[] = "(tipo_audiencia = 'IDIOMA' AND audiencia_ref IN ($in_idm))";
                $params_admin = array_merge($params_admin, $idiomas);
            }
            if (count($materias_ids) > 0) {
                $in_mat = implode(',', array_fill(0, count($materias_ids), '?'));
                $where_admin[] = "(tipo_audiencia = 'MATERIA' AND audiencia_ref IN ($in_mat))";
                $params_admin = array_merge($params_admin, $materias_ids);
            }
            if (count($nrcs) > 0) {
                $in_n = implode(',', array_fill(0, count($nrcs), '?'));
                $where_admin[] = "(tipo_audiencia = 'GRUPO' AND audiencia_ref IN ($in_n))";
                $params_admin = array_merge($params_admin, $nrcs);
            }

            $sql_admin = "
                SELECT aviso_id, titulo, cuerpo, tipo_audiencia, audiencia_ref, imagen, fecha_creacion, fecha_expiracion
                FROM avisos
                WHERE autor_id IS NULL AND ciclo_id = ? AND rol_destinatario IN ('TODOS', 'PROFESOR') AND (" . implode(" OR ", $where_admin) . ")
                ORDER BY fecha_creacion DESC
                LIMIT 30
            ";
            $st_adm = $pdo->prepare($sql_admin);
            $st_adm->execute(array_merge([$ciclo_id], $params_admin));
            while ($row = $st_adm->fetch(PDO::FETCH_ASSOC)) {
                $badgeClase = 'neon-sub-global';
                $bordeClase = 'notif-item-inst-global';
                $label = 'Comunicado General';

                if ($row['tipo_audiencia'] === 'IDIOMA') {
                    $badgeClase = 'neon-sub-idioma';
                    $bordeClase = 'notif-item-inst-idioma';
                    $label = 'Aviso: ' . $row['audiencia_ref'];
                } elseif ($row['tipo_audiencia'] === 'MATERIA') {
                    $badgeClase = 'neon-sub-materia';
                    $bordeClase = 'notif-item-inst-materia';
                    $label = 'Aviso de Materia';
                }

                $avisos[] = [
                    'key' => 'hist_adm_' . $row['aviso_id'],
                    'origen' => 'INSTITUCIONAL',
                    'categoria' => $label,
                    'tipo_badge' => $badgeClase,
                    'borde_card' => $bordeClase,
                    'icono' => 'fas fa-university',
                    'materia' => $label,
                    'titulo' => $row['titulo'],
                    'cuerpo' => $row['cuerpo'],
                    'cuerpo_corto' => mb_substr(strip_tags($row['cuerpo']), 0, 95) . (mb_strlen($row['cuerpo']) > 95 ? '...' : ''),
                    'imagen' => $row['imagen'] ? '../' . ltrim($row['imagen'], '/') : null,
                    'autor' => 'Coordinación E-PALE',
                    'fecha_formateada' => date('d/m/Y h:i A', strtotime($row['fecha_creacion'])),
                    'fecha_inicio' => date('d/m/Y h:i A', strtotime($row['fecha_creacion'])),
                    'fecha_fin' => !empty($row['fecha_expiracion']) ? date('d/m/Y h:i A', strtotime($row['fecha_expiracion'])) : 'Sin fecha límite'
                ];
            }
        }

        echo json_encode([
            'status' => 'success',
            'data' => [
                'avisos' => $avisos
            ]
        ]);
        exit;
    }
} catch (Throwable $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
    exit;
}
