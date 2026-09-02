<?php
session_start();
require '../db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ADMIN') {
    echo json_encode(['error' => 'No autorizado']); exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (is_array($input)) {
    $_POST = array_merge($_POST, $input);
}

// ESCUDO CSRF
if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    echo json_encode(['error' => 'Error de Seguridad: Token CSRF inválido o ausente.']); exit;
}

$action = $_POST['action'] ?? '';

// 1. Cargar Ciclos
if ($action === 'get_ciclos') {
    $stmt = $pdo->query("SELECT ciclo_id, nombre FROM ciclos ORDER BY ciclo_id DESC");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;
}

// 2. Cargar Idiomas (AGRUPACIÓN INTELIGENTE: Sin duplicados ni claves, forzando mayúsculas)
if ($action === 'get_idiomas') {
    $ciclo_id = $_POST['ciclo_id'];
    $stmt = $pdo->prepare("SELECT DISTINCT UPPER(TRIM(m.nombre)) AS nombre 
                           FROM grupos g 
                           JOIN materias m ON g.materia_id = m.materia_id 
                           WHERE g.ciclo_id = ? 
                           ORDER BY nombre ASC");
    $stmt->execute([$ciclo_id]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;
}

// 3. Cargar Niveles (TRAE NIVELES Y CLAVES ÚNICAS BUSCANDO POR EL IDIOMA AGRUPADO)
if ($action === 'get_niveles') {
    $ciclo_id = $_POST['ciclo_id'];
    $idioma = mb_strtoupper(trim($_POST['idioma']), 'UTF-8');
    
    $stmt = $pdo->prepare("SELECT DISTINCT m.materia_id, m.nivel, m.clave 
                           FROM grupos g 
                           JOIN materias m ON g.materia_id = m.materia_id 
                           WHERE g.ciclo_id = ? AND UPPER(TRIM(m.nombre)) = ? 
                           ORDER BY m.nivel ASC, m.clave ASC");
    $stmt->execute([$ciclo_id, $idioma]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;
}

// 4. Calcular el Dashboard Completo
if ($action === 'get_stats') {
    $ciclo_id = $_POST['ciclo_id'];
    $materia_id = $_POST['materia_id'];
    $idioma_base = mb_strtoupper(trim($_POST['idioma']), 'UTF-8'); // El nombre genérico para el Radar

    // Obtenemos los nombres reales para la vista
    $stmtInfo = $pdo->prepare("SELECT nombre, nivel, clave FROM materias WHERE materia_id = ?");
    $stmtInfo->execute([$materia_id]);
    $matInfo = $stmtInfo->fetch(PDO::FETCH_ASSOC);
    $nivel = $matInfo['nivel'];
    $clave = $matInfo['clave'];

    // A) Grupos Abiertos (Contamos clases lógicas)
    $stmtG = $pdo->prepare("SELECT COUNT(DISTINCT clave_grupo) FROM grupos WHERE ciclo_id = ? AND materia_id = ?");
    $stmtG->execute([$ciclo_id, $materia_id]);
    $total_grupos = $stmtG->fetchColumn();

    // B) Alumnos Inscritos y Género (Deduplicados)
    $stmtA = $pdo->prepare("
        SELECT u.genero, i.inscripcion_id, a.alumno_id
        FROM inscripciones i
        JOIN grupos g ON i.nrc = g.nrc
        JOIN alumnos a ON i.alumno_id = a.alumno_id
        JOIN usuarios u ON a.usuario_id = u.usuario_id
        WHERE g.ciclo_id = ? AND g.materia_id = ? AND i.estatus = 'INSCRITO'
    ");
    $stmtA->execute([$ciclo_id, $materia_id]);
    $alumnos_brutos = $stmtA->fetchAll(PDO::FETCH_ASSOC);

    $hombres = 0; $mujeres = 0; $otros = 0;
    $inscripciones_ids = [];
    $alumnos_unicos = [];

    foreach($alumnos_brutos as $al) {
        if(!isset($alumnos_unicos[$al['alumno_id']])) {
            $alumnos_unicos[$al['alumno_id']] = true;
            $inscripciones_ids[] = $al['inscripcion_id']; 
            
            $gen = strtoupper($al['genero'] ?? '');
            if ($gen === 'MASCULINO') $hombres++;
            elseif ($gen === 'FEMENINO') $mujeres++;
            else $otros++;
        }
    }
    $total_alumnos = count($alumnos_unicos);

    // C) Puntos Máximos
    $stmtMax = $pdo->prepare("SELECT SUM(puntos_maximos) FROM criterios_evaluacion WHERE materia_id = ?");
    $stmtMax->execute([$materia_id]);
    $max_puntos = (float)$stmtMax->fetchColumn();

    // D) Calificaciones Globales
    $desempeno_promedio = 0;
    $aprobados = 0; $reprobados = 0;
    $dist_0_69 = 0; $dist_70_79 = 0; $dist_80_85 = 0; $dist_86_95 = 0; $dist_96_100 = 0;

    if ($total_alumnos > 0 && $max_puntos > 0) {
        $in_clause = implode(',', array_fill(0, count($inscripciones_ids), '?'));
        $stmtC = $pdo->prepare("SELECT inscripcion_id, SUM(puntaje) as total_pts FROM calificaciones WHERE inscripcion_id IN ($in_clause) GROUP BY inscripcion_id");
        $stmtC->execute($inscripciones_ids);
        $calif_data = $stmtC->fetchAll(PDO::FETCH_KEY_PAIR);

        $suma_porcentajes = 0;
        foreach($inscripciones_ids as $iid) {
            $pts = isset($calif_data[$iid]) ? (float)$calif_data[$iid] : 0;
            $porcentaje = ($pts / $max_puntos) * 100;
            if($porcentaje > 100) $porcentaje = 100;
            $suma_porcentajes += $porcentaje;

            if ($porcentaje >= 80) { $aprobados++; } else { $reprobados++; }

            if ($porcentaje < 70) $dist_0_69++;
            elseif ($porcentaje < 80) $dist_70_79++;
            elseif ($porcentaje < 86) $dist_80_85++;
            elseif ($porcentaje < 96) $dist_86_95++;
            else $dist_96_100++;
        }
        $desempeno_promedio = round($suma_porcentajes / $total_alumnos, 1);
    }

    // E) Desglose por Grupos 
    $stmtGrupos = $pdo->prepare("
        SELECT g.clave_grupo, u.nombre, u.apellido_paterno,
               MAX(CASE WHEN h.modalidad='PRESENCIAL' THEN g.nrc END) AS nrc_p,
               MAX(CASE WHEN h.modalidad='VIRTUAL' THEN g.nrc END) AS nrc_v,
               GROUP_CONCAT(DISTINCT g.nrc SEPARATOR ', ') as nrc_fallback
        FROM grupos g
        JOIN usuarios u ON g.profesor_id = u.usuario_id
        LEFT JOIN horarios h ON g.nrc = h.nrc
        WHERE g.ciclo_id = ? AND g.materia_id = ?
        GROUP BY g.clave_grupo, u.nombre, u.apellido_paterno
    ");
    $stmtGrupos->execute([$ciclo_id, $materia_id]);
    $grupos_raw = $stmtGrupos->fetchAll(PDO::FETCH_ASSOC);

    $tabla_grupos = [];
    foreach($grupos_raw as $g) {
        $stmtNrcs = $pdo->prepare("SELECT nrc FROM grupos WHERE clave_grupo = ?");
        $stmtNrcs->execute([$g['clave_grupo']]);
        $nrcs_grupo = $stmtNrcs->fetchAll(PDO::FETCH_COLUMN);

        $in_nrcs = implode(',', array_fill(0, count($nrcs_grupo), '?'));
        
        $stmtInsG = $pdo->prepare("SELECT DISTINCT i.inscripcion_id, i.alumno_id FROM inscripciones i WHERE i.nrc IN ($in_nrcs) AND i.estatus = 'INSCRITO'");
        $stmtInsG->execute($nrcs_grupo);
        $ins_records = $stmtInsG->fetchAll(PDO::FETCH_ASSOC);
        
        $alum_unicos_grupo = [];
        $ins_grupo = [];
        foreach($ins_records as $rec) {
            if(!isset($alum_unicos_grupo[$rec['alumno_id']])) {
                $alum_unicos_grupo[$rec['alumno_id']] = true;
                $ins_grupo[] = $rec['inscripcion_id'];
            }
        }
        
        $cant_g = count($ins_grupo);
        $prom_g = 0;
        if($cant_g > 0 && $max_puntos > 0) {
            $in_ins_g = implode(',', array_fill(0, $cant_g, '?'));
            $stmtPtsG = $pdo->prepare("SELECT SUM(puntaje) FROM calificaciones WHERE inscripcion_id IN ($in_ins_g)");
            $stmtPtsG->execute($ins_grupo);
            $pts_g = (float)$stmtPtsG->fetchColumn();
            $prom_g = round(($pts_g / $max_puntos * 100) / $cant_g, 1);
            if($prom_g > 100) $prom_g = 100;
        }
        
        $nrc_label = '';
        if (!empty($g['nrc_p']) && !empty($g['nrc_v'])) { $nrc_label = 'P: ' . $g['nrc_p'] . ' | V: ' . $g['nrc_v']; } 
        elseif (!empty($g['nrc_p'])) { $nrc_label = $g['nrc_p']; } 
        elseif (!empty($g['nrc_v'])) { $nrc_label = $g['nrc_v'] . ' (Virtual)'; } 
        else { $nrc_label = $g['nrc_fallback']; }

        $tabla_grupos[] = [
            'nrc_label' => $nrc_label,
            'nombre' => $g['nombre'],
            'apellido_paterno' => $g['apellido_paterno'],
            'cant_alumnos' => $cant_g,
            'promedio' => $prom_g
        ];
    }

    // F) Radar por Nivel (INTELIGENTE: Busca todas las materias derivadas de ese idioma global)
    $stmtNiv = $pdo->prepare("SELECT DISTINCT m.nivel, m.materia_id, m.clave 
                              FROM grupos g JOIN materias m ON g.materia_id = m.materia_id 
                              WHERE g.ciclo_id = ? AND UPPER(TRIM(m.nombre)) = ? 
                              ORDER BY m.nivel ASC, m.clave ASC");
    $stmtNiv->execute([$ciclo_id, $idioma_base]);
    $niveles_activos = $stmtNiv->fetchAll(PDO::FETCH_ASSOC);

    $radar_labels = []; $radar_promedios = [];
    $stmtMaxAll = $pdo->query("SELECT materia_id, SUM(puntos_maximos) FROM criterios_evaluacion GROUP BY materia_id");
    $max_pts_all = $stmtMaxAll->fetchAll(PDO::FETCH_KEY_PAIR);

    foreach($niveles_activos as $niv) {
        $n = $niv['nivel']; $m_id = $niv['materia_id']; $c = $niv['clave'];
        $radar_labels[] = 'Nivel ' . $n . ' (' . $c . ')'; 
        
        $stmtAlumsNivel = $pdo->prepare("SELECT i.inscripcion_id, i.alumno_id FROM inscripciones i JOIN grupos g ON i.nrc = g.nrc WHERE g.ciclo_id = ? AND g.materia_id = ? AND i.estatus = 'INSCRITO'");
        $stmtAlumsNivel->execute([$ciclo_id, $m_id]);
        $ins_nivel_raw = $stmtAlumsNivel->fetchAll(PDO::FETCH_ASSOC);
        
        $u_niv = []; $ins_nivel = [];
        foreach($ins_nivel_raw as $ir) {
            if(!isset($u_niv[$ir['alumno_id']])) { $u_niv[$ir['alumno_id']] = true; $ins_nivel[] = $ir['inscripcion_id']; }
        }
        
        $cant_n = count($ins_nivel);
        $prom_n = 0;
        $max_p_n = isset($max_pts_all[$m_id]) ? (float)$max_pts_all[$m_id] : 0;
        
        if ($cant_n > 0 && $max_p_n > 0) {
            $in_ins_n = implode(',', array_fill(0, $cant_n, '?'));
            $stmtPtsN = $pdo->prepare("SELECT SUM(puntaje) FROM calificaciones WHERE inscripcion_id IN ($in_ins_n)");
            $stmtPtsN->execute($ins_nivel);
            $prom_n = round(((float)$stmtPtsN->fetchColumn() / $max_p_n * 100) / $cant_n, 1);
            if($prom_n > 100) $prom_n = 100;
        }
        $radar_promedios[] = $prom_n;
    }

    // G) Histórico (Deduplicado Y USANDO materia_id para respetar la variante evaluada)
    $stmtHist = $pdo->query("SELECT ciclo_id, nombre FROM ciclos ORDER BY nombre ASC");
    $todos_ciclos = $stmtHist->fetchAll(PDO::FETCH_ASSOC);
    $hist_labels = []; $hist_alumnos = []; $hist_promedios = [];
    
    foreach($todos_ciclos as $c) {
        $stmtHA = $pdo->prepare("
            SELECT i.inscripcion_id, i.alumno_id
            FROM inscripciones i JOIN grupos g ON i.nrc = g.nrc 
            WHERE g.ciclo_id = ? AND g.materia_id = ? AND i.estatus = 'INSCRITO'
        ");
        $stmtHA->execute([$c['ciclo_id'], $materia_id]);
        $ins_hist_raw = $stmtHA->fetchAll(PDO::FETCH_ASSOC);
        
        $u_hist = []; $ins_hist = [];
        foreach($ins_hist_raw as $hr) {
            if(!isset($u_hist[$hr['alumno_id']])) { $u_hist[$hr['alumno_id']] = true; $ins_hist[] = $hr['inscripcion_id']; }
        }
        
        $cant_h = count($ins_hist);
        if ($cant_h > 0) {
            $hist_labels[] = $c['nombre'];
            $hist_alumnos[] = $cant_h;
            
            $prom_h = 0;
            if ($max_puntos > 0) {
                $in_h = implode(',', array_fill(0, $cant_h, '?'));
                $stmtPtsH = $pdo->prepare("SELECT SUM(puntaje) FROM calificaciones WHERE inscripcion_id IN ($in_h)");
                $stmtPtsH->execute($ins_hist);
                $prom_h = round(((float)$stmtPtsH->fetchColumn() / $max_puntos * 100) / $cant_h, 1);
                if($prom_h > 100) $prom_h = 100;
            }
            $hist_promedios[] = $prom_h;
        }
    }

    echo json_encode([
        'idioma' => $idioma_base,
        'clave' => $clave,
        'nivel' => $nivel,
        'total_grupos' => $total_grupos,
        'total_alumnos' => $total_alumnos,
        'hombres' => $hombres, 'mujeres' => $mujeres, 'otros' => $otros,
        'desempeno' => $desempeno_promedio,
        'aprobados' => $aprobados, 'reprobados' => $reprobados,
        'tasa_aprobacion' => $total_alumnos > 0 ? round(($aprobados / $total_alumnos) * 100) : 0,
        'distribucion' => [$dist_0_69, $dist_70_79, $dist_80_85, $dist_86_95, $dist_96_100],
        'tabla_grupos' => $tabla_grupos,
        'radar' => ['labels' => $radar_labels, 'data' => $radar_promedios],
        'historico' => ['labels' => $hist_labels, 'alumnos' => $hist_alumnos, 'promedios' => $hist_promedios]
    ]); exit;
}
?>
