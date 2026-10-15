<?php
session_start();
require '../../db.php';
require_once '../../security.php';

validar_csrf_estricto('POST');
requerir_rol('ADMIN');

// Función para detección de colisión de horario de un estudiante
function detectarChoqueHorarioAlumno(int $alumno_id, string $nrc_nuevo, int $ciclo_id, PDO $pdo)
{
    $sql_horarios_nuevo = "SELECT h.dias_patron, h.hora_inicio, h.hora_fin, m.nombre AS mat_nombre
                           FROM horarios h 
                           JOIN grupos g ON h.nrc = g.nrc 
                           JOIN materias m ON g.materia_id = m.materia_id
                           WHERE g.clave_grupo = (SELECT clave_grupo FROM grupos WHERE nrc = ? LIMIT 1)";
    $stmt_nuevo = $pdo->prepare($sql_horarios_nuevo);
    $stmt_nuevo->execute([$nrc_nuevo]);
    $horarios_nuevos = $stmt_nuevo->fetchAll(PDO::FETCH_ASSOC);

    if (empty($horarios_nuevos)) return false;

    $sql_horarios_actuales = "SELECT h.dias_patron, h.hora_inicio, h.hora_fin, m.nombre as mat_nombre, h.nrc 
                              FROM inscripciones i 
                              JOIN grupos g ON i.nrc = g.nrc 
                              JOIN horarios h ON g.nrc = h.nrc 
                              JOIN materias m ON g.materia_id = m.materia_id
                              WHERE i.alumno_id = ? AND i.estatus = 'INSCRITO' AND g.ciclo_id = ? AND h.nrc != ?";
    $stmt_actuales = $pdo->prepare($sql_horarios_actuales);
    $stmt_actuales->execute([$alumno_id, $ciclo_id, $nrc_nuevo]);
    $clases_actuales = $stmt_actuales->fetchAll(PDO::FETCH_ASSOC);

    if (empty($clases_actuales)) return false;

    foreach ($horarios_nuevos as $nuevo) {
        if (empty($nuevo['hora_inicio']) || empty($nuevo['hora_fin']) || empty($nuevo['dias_patron'])) continue;
        $start_n = strtotime($nuevo['hora_inicio']);
        $end_n = strtotime($nuevo['hora_fin']);
        $dias_n = str_split(preg_replace('/[^A-Za-z]/', '', strtoupper($nuevo['dias_patron'])));

        foreach ($clases_actuales as $actual) {
            if (empty($actual['hora_inicio']) || empty($actual['hora_fin']) || empty($actual['dias_patron'])) continue;
            $start_a = strtotime($actual['hora_inicio']);
            $end_a = strtotime($actual['hora_fin']);
            $dias_a = str_split(preg_replace('/[^A-Za-z]/', '', strtoupper($actual['dias_patron'])));

            if ($start_n < $end_a && $end_n > $start_a) {
                if (count(array_intersect($dias_n, $dias_a)) > 0) {
                    $dias_str = htmlspecialchars($actual['dias_patron']);
                    $h_ini = substr($actual['hora_inicio'], 0, 5);
                    $h_fin = substr($actual['hora_fin'], 0, 5);
                    return "Choca con la clase '{$actual['mat_nombre']}' (NRC: {$actual['nrc']}), horario: {$dias_str} {$h_ini} - {$h_fin}.";
                }
            }
        }
    }
    return false;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["archivo_csv"])) {
    $archivo = $_FILES["archivo_csv"]["tmp_name"];
    if (empty($archivo) || $_FILES["archivo_csv"]["size"] == 0) {
        header("Location: vista_csv_inscripciones?msg=error_file");
        exit;
    }

    $file = fopen($archivo, "r");
    if (!$file) {
        header("Location: vista_csv_inscripciones?msg=error_file");
        exit;
    }

    $fila_actual = 1;
    if (isset($_POST['ignorar_cabecera']) && $_POST['ignorar_cabecera'] == '1') {
        fgetcsv($file);
        $fila_actual = 2;
    }

    // Estructuras para el resumen
    $inscritos_count = 0;
    $reinscritos_count = 0;
    $ya_inscritos_count = 0;
    $omitidos = [];
    $nrcs_afectados = [];

    // Preparar sentencias
    $stmt_find_alumno = $pdo->prepare("
        SELECT a.alumno_id, u.usuario_id, u.codigo, u.nombre, u.apellido_paterno, u.apellido_materno 
        FROM usuarios u 
        JOIN alumnos a ON u.usuario_id = a.usuario_id 
        WHERE u.codigo = ? AND u.rol = 'ALUMNO' AND u.estatus = 'ACTIVO'
        LIMIT 1
    ");

    $stmt_find_grupo = $pdo->prepare("
        SELECT g.nrc, g.clave_grupo, g.ciclo_id, g.cupo, m.nombre AS materia_nombre, g.materia_id
        FROM grupos g
        JOIN materias m ON g.materia_id = m.materia_id
        WHERE g.nrc = ?
        LIMIT 1
    ");

    $stmt_check_inscripcion = $pdo->prepare("
        SELECT inscripcion_id, estatus 
        FROM inscripciones 
        WHERE alumno_id = ? AND nrc = ?
        LIMIT 1
    ");

    $stmt_insert_inscripcion = $pdo->prepare("
        INSERT INTO inscripciones (alumno_id, nrc, estatus, calificacion_final)
        VALUES (?, ?, 'INSCRITO', 0.00)
    ");

    $stmt_update_reinscripcion = $pdo->prepare("
        UPDATE inscripciones 
        SET estatus = 'INSCRITO' 
        WHERE inscripcion_id = ?
    ");

    try {
        $pdo->beginTransaction();

        while (($datos = fgetcsv($file, 1000, ",")) !== FALSE) {
            // Manejo de delimitador punto y coma si viene en formato europeo/latino
            if (count($datos) == 1 && strpos($datos[0], ';') !== false) {
                $datos = str_getcsv($datos[0], ';');
            }

            if (empty(array_filter($datos))) {
                $fila_actual++;
                continue;
            }

            $codigo_raw = trim($datos[0] ?? '');
            $nrc_raw    = trim($datos[1] ?? '');

            // Validar campos básicos
            if ($codigo_raw === '' || $nrc_raw === '') {
                $omitidos[] = [
                    'fila'          => $fila_actual,
                    'codigo'        => $codigo_raw ?: 'Vacío',
                    'nombre_alumno' => 'N/A',
                    'nrc'           => $nrc_raw ?: 'Vacío',
                    'info_grupo'    => 'N/A',
                    'tipo'          => 'ERROR',
                    'detalle'       => 'Fila incompleta. Se requiere CODIGO_ESTUDIANTE y NRC.'
                ];
                $fila_actual++;
                continue;
            }

            // 1. Buscar alumno
            $stmt_find_alumno->execute([$codigo_raw]);
            $alumno = $stmt_find_alumno->fetch(PDO::FETCH_ASSOC);

            if (!$alumno) {
                $omitidos[] = [
                    'fila'          => $fila_actual,
                    'codigo'        => $codigo_raw,
                    'nombre_alumno' => 'No encontrado',
                    'nrc'           => $nrc_raw,
                    'info_grupo'    => 'N/A',
                    'tipo'          => 'ERROR',
                    'detalle'       => "No existe ningún alumno activo con el código '{$codigo_raw}' en el sistema."
                ];
                $fila_actual++;
                continue;
            }

            $nom_alumno = trim("{$alumno['nombre']} {$alumno['apellido_paterno']} " . ($alumno['apellido_materno'] ?? ''));

            // 2. Buscar grupo
            $stmt_find_grupo->execute([$nrc_raw]);
            $grupo = $stmt_find_grupo->fetch(PDO::FETCH_ASSOC);

            if (!$grupo) {
                $omitidos[] = [
                    'fila'          => $fila_actual,
                    'codigo'        => $codigo_raw,
                    'nombre_alumno' => $nom_alumno,
                    'nrc'           => $nrc_raw,
                    'info_grupo'    => 'Grupo no encontrado',
                    'tipo'          => 'ERROR',
                    'detalle'       => "No se encontró ningún grupo registrado con el NRC '{$nrc_raw}'."
                ];
                $fila_actual++;
                continue;
            }

            $info_grupo = "{$grupo['materia_nombre']} (Clave: {$grupo['clave_grupo']})";

            // 3. Validar choque de horario
            $choque = detectarChoqueHorarioAlumno((int)$alumno['alumno_id'], (string)$grupo['nrc'], (int)$grupo['ciclo_id'], $pdo);
            if ($choque) {
                $omitidos[] = [
                    'fila'          => $fila_actual,
                    'codigo'        => $codigo_raw,
                    'nombre_alumno' => $nom_alumno,
                    'nrc'           => $nrc_raw,
                    'info_grupo'    => $info_grupo,
                    'tipo'          => 'CHOQUE',
                    'detalle'       => $choque
                ];
                $fila_actual++;
                continue;
            }

            // 4. Verificar si ya está inscrito
            $stmt_check_inscripcion->execute([$alumno['alumno_id'], $grupo['nrc']]);
            $inscripcion_actual = $stmt_check_inscripcion->fetch(PDO::FETCH_ASSOC);

            if ($inscripcion_actual) {
                if ($inscripcion_actual['estatus'] === 'INSCRITO') {
                    $ya_inscritos_count++;
                    $nrcs_afectados[$grupo['nrc']] = true;
                } else {
                    // Reactivar (estaba en BAJA, SOLICITUD_ALTA, etc.)
                    $stmt_update_reinscripcion->execute([$inscripcion_actual['inscripcion_id']]);
                    $reinscritos_count++;
                    $nrcs_afectados[$grupo['nrc']] = true;
                }
            } else {
                // Nueva inscripción
                $stmt_insert_inscripcion->execute([$alumno['alumno_id'], $grupo['nrc']]);
                $inscritos_count++;
                $nrcs_afectados[$grupo['nrc']] = true;
            }

            $fila_actual++;
        }
        fclose($file);

        // 5. AJUSTE AUTOMÁTICO DE CUPO PARA ADMITIR A TODOS SIN RESTRICCIÓN
        // Para cada NRC afectado, contamos cuántos alumnos quedaron inscritos.
        // Si el total de inscritos supera el cupo configurado, aumentamos el cupo automáticamente en todos los grupos de esa clave_grupo.
        $cupos_ajustados = [];
        $stmt_total_inscritos = $pdo->prepare("SELECT COUNT(*) FROM inscripciones WHERE nrc = ? AND estatus = 'INSCRITO'");
        $stmt_update_cupo = $pdo->prepare("UPDATE grupos SET cupo = ? WHERE clave_grupo = ?");

        foreach (array_keys($nrcs_afectados) as $nrc_item) {
            $stmt_find_grupo->execute([$nrc_item]);
            $g_info = $stmt_find_grupo->fetch(PDO::FETCH_ASSOC);
            if (!$g_info) continue;

            $stmt_total_inscritos->execute([$nrc_item]);
            $total_inscritos = (int)$stmt_total_inscritos->fetchColumn();

            $cupo_actual = (int)$g_info['cupo'];
            if ($total_inscritos > $cupo_actual) {
                // Aumentar cupo para cubrir el excedente
                $stmt_update_cupo->execute([$total_inscritos, $g_info['clave_grupo']]);
                $cupos_ajustados[] = [
                    'nrc'          => $nrc_item,
                    'clave_grupo'  => $g_info['clave_grupo'],
                    'cupo_previo'  => $cupo_actual,
                    'cupo_nuevo'   => $total_inscritos
                ];
            }
        }

        $pdo->commit();

        // Registrar en historial administrativo
        if (function_exists('registrar_historial') && ($inscritos_count > 0 || $reinscritos_count > 0)) {
            $total_movs = $inscritos_count + $reinscritos_count;
            registrar_historial(
                $pdo, 
                $_SESSION['user_id'], 
                'Inscripción', 
                'Grupos', 
                'Asignación Masiva CSV', 
                "Alumnos ($total_movs procesados)", 
                "Se inscribieron $inscritos_count nuevos, $reinscritos_count re-inscritos. Omitidos: " . count($omitidos)
            );
        }

        // Guardar resultado detallado en sesión para mostrarlo en la interfaz
        $_SESSION['resumen_inscripciones'] = [
            'inscritos'       => $inscritos_count,
            're_inscritos'    => $reinscritos_count,
            'ya_inscritos'    => $ya_inscritos_count,
            'omitidos'        => $omitidos,
            'cupos_ajustados' => $cupos_ajustados
        ];

        header("Location: vista_csv_inscripciones");
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $detalle_err = urlencode("Error en base de datos: " . $e->getMessage());
        header("Location: vista_csv_inscripciones?msg=error_db&detalle={$detalle_err}");
        exit;
    }
} else {
    header("Location: vista_csv_inscripciones");
    exit;
}
