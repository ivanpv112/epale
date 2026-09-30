<?php
// Este script se debe ejecutar por línea de comandos (CLI)
if (php_sapi_name() !== 'cli') {
    die('Acceso denegado. Este script solo puede correr en segundo plano.');
}

$exportacion_id = isset($argv[1]) ? intval($argv[1]) : 0;
if ($exportacion_id <= 0) {
    die("ID de exportación no válido.");
}

// Subir un nivel más si tu db.php está en la raíz del proyecto
require dirname(__DIR__, 2) . '/db.php';

$stmt = $pdo->prepare("UPDATE exportaciones SET estado = 'PROCESANDO', progreso = 5 WHERE id = ?");
$stmt->execute([$exportacion_id]);

// Función para actualizar progreso en tiempo real
function actualizar_progreso(PDO $pdo, int $id, int $porcentaje): void
{
    $stmt = $pdo->prepare("UPDATE exportaciones SET progreso = ? WHERE id = ?");
    $stmt->execute([$porcentaje, $id]);
}

$temp_dir = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'temp_' . $exportacion_id;
if (!is_dir($temp_dir)) {
    mkdir($temp_dir, 0777, true);
}

// Función Helper para exportar una query a CSV
function generar_csv_desde_query(PDO $pdo, string $query, string $filename, array $headers): void
{
    $file = fopen($filename, 'w');
    // Agregar BOM para que Excel detecte los acentos y UTF-8 correctamente
    fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($file, $headers);

    $stmt = $pdo->query($query);
    if ($stmt) {
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($file, $row);
        }
    }
    fclose($file);
}

try {
    // 1. Alumnos (Sin IDs)
    generar_csv_desde_query(
        $pdo,
        "SELECT u.codigo, u.nombre, u.apellido_paterno, u.apellido_materno, u.correo, a.carrera, u.telefono, u.estatus 
         FROM alumnos a 
         JOIN usuarios u ON a.usuario_id = u.usuario_id",
        $temp_dir . '/01_Alumnos.csv',
        ['Código', 'Nombre', 'Apellido Paterno', 'Apellido Materno', 'Correo', 'Carrera', 'Teléfono', 'Estatus']
    );
    actualizar_progreso($pdo, $exportacion_id, 20);

    // 2. Profesores (Sin IDs)
    generar_csv_desde_query(
        $pdo,
        "SELECT u.codigo, u.nombre, u.apellido_paterno, u.apellido_materno, u.correo, p.nacionalidad, p.experiencia, u.estatus 
         FROM profesores p 
         JOIN usuarios u ON p.usuario_id = u.usuario_id",
        $temp_dir . '/02_Profesores.csv',
        ['Código', 'Nombre', 'Apellido Paterno', 'Apellido Materno', 'Correo', 'Nacionalidad', 'Experiencia', 'Estatus']
    );
    actualizar_progreso($pdo, $exportacion_id, 30);

    // 3. Grupos
    generar_csv_desde_query(
        $pdo,
        "SELECT g.nrc, g.clave_siiau, m.nombre AS materia, c.nombre AS ciclo, u.nombre AS profesor, g.cupo, g.clave_grupo, g.estado 
         FROM grupos g
         LEFT JOIN materias m ON g.materia_id = m.materia_id
         LEFT JOIN ciclos c ON g.ciclo_id = c.ciclo_id
         LEFT JOIN profesores p ON g.profesor_id = p.profesor_id
         LEFT JOIN usuarios u ON p.usuario_id = u.usuario_id",
        $temp_dir . '/03_Grupos.csv',
        ['NRC', 'Clave SIIAU', 'Materia', 'Ciclo', 'Profesor', 'Cupo', 'Clave Grupo', 'Estado']
    );
    actualizar_progreso($pdo, $exportacion_id, 45);

    // 4. Diagnósticos
    generar_csv_desde_query(
        $pdo,
        "SELECT u.codigo, u.nombre, u.apellido_paterno, e.idioma, e.calificacion_texto, e.nivel_asignado, e.fecha_realizacion, e.periodo 
         FROM examenes_diagnosticos e
         JOIN alumnos a ON e.alumno_id = a.alumno_id
         JOIN usuarios u ON a.usuario_id = u.usuario_id",
        $temp_dir . '/04_Diagnosticos.csv',
        ['Código Alumno', 'Nombre', 'Apellido', 'Idioma', 'Calificación', 'Nivel Asignado', 'Fecha Realización', 'Periodo']
    );
    actualizar_progreso($pdo, $exportacion_id, 55);

    // 5. Certificaciones
    generar_csv_desde_query(
        $pdo,
        "SELECT u.codigo, u.nombre, u.apellido_paterno, c.idioma, c.puntaje, c.nivel_obtenido, c.periodo, c.fecha_aplicacion 
         FROM certificaciones c
         JOIN alumnos a ON c.alumno_id = a.alumno_id
         JOIN usuarios u ON a.usuario_id = u.usuario_id",
        $temp_dir . '/05_Certificaciones.csv',
        ['Código Alumno', 'Nombre', 'Apellido', 'Idioma', 'Puntaje', 'Nivel', 'Periodo', 'Fecha Aplicación']
    );
    actualizar_progreso($pdo, $exportacion_id, 65);

    // 6. Dictámenes
    generar_csv_desde_query(
        $pdo,
        "SELECT codigo_alumno, nombre_alumno, carrera, ciclo_acreditacion, clave_materia, nombre_materia, num_dictamen, fecha_carga 
         FROM dictamenes_estudiantes",
        $temp_dir . '/06_Dictamenes.csv',
        ['Código Alumno', 'Nombre', 'Carrera', 'Ciclo Acreditación', 'Clave Materia', 'Nombre Materia', 'Num Dictamen', 'Fecha Carga']
    );
    actualizar_progreso($pdo, $exportacion_id, 75);

    // 7. Calificaciones (Dinámico avanzado)
    $stmt_crit = $pdo->query("SELECT DISTINCT tipo_examen FROM calificaciones ORDER BY tipo_examen");
    $criterios_raw = $stmt_crit->fetchAll(PDO::FETCH_COLUMN);

    // Normalizador de criterios
    $normalizar_criterio = function ($c) {
        $c = strtoupper(trim($c));
        if (in_array($c, ['Q1', 'Q01'])) return 'Examen 1';
        if (in_array($c, ['Q2', 'Q02'])) return 'Examen 2';
        if (in_array($c, ['Q3', 'Q03'])) return 'Examen 3';
        if (in_array($c, ['Q4', 'Q04'])) return 'Examen 4';
        if ($c === 'EX') return 'Examen Final';
        if (in_array($c, ['QO1'])) return 'Ex Oral 1';
        if (in_array($c, ['QO2'])) return 'Ex Oral 2';
        if ($c === 'PARTICIPACION') return 'Participación';
        if ($c === 'WRITING') return 'Proyecto Escrito';
        if ($c === 'PLATAFORMA') return 'Plataforma';
        if (in_array($c, ['TOEFL', 'CERT', 'CERTIFICACION'])) return 'Certificación';
        return ucfirst(strtolower($c));
    };

    $orden_criterio = function ($norm) {
        if (strpos($norm, 'Examen') !== false) return 10;
        if (strpos($norm, 'Oral') !== false) return 20;
        if ($norm === 'Participación') return 30;
        if ($norm === 'Proyecto Escrito') return 40;
        if ($norm === 'Plataforma') return 50;
        if ($norm === 'Certificación') return 100; // Siempre al final
        return 60; // Desconocidos van antes de certificación
    };

    // Extraer y normalizar los criterios únicos
    $criterios_normalizados = [];
    foreach ($criterios_raw as $raw) {
        $norm = $normalizar_criterio($raw);
        if (!in_array($norm, $criterios_normalizados)) {
            $criterios_normalizados[] = $norm;
        }
    }

    // Ordenar los criterios según la regla del usuario
    usort($criterios_normalizados, function ($a, $b) use ($orden_criterio) {
        $pesoA = $orden_criterio($a);
        $pesoB = $orden_criterio($b);
        if ($pesoA === $pesoB) return strcmp($a, $b); // Alfabetico si empatan
        return $pesoA <=> $pesoB;
    });

    $headers_calif = ['Código Alumno', 'Nombre Completo', 'Correo Electrónico', 'Carrera', 'NRC Presencial', 'NRC Virtual', 'Ciclo', 'Clave Materia', 'Materia'];
    foreach ($criterios_normalizados as $crit) {
        $headers_calif[] = $crit;
    }
    $headers_calif[] = 'Calif. Final';

    // Query base sin traer i.calificacion_final ya que la calcularemos nosotros
    $query_base = "SELECT i.inscripcion_id, u.codigo, CONCAT(u.nombre, ' ', u.apellido_paterno, ' ', u.apellido_materno) as nombre_completo, 
                          u.correo, a.carrera,
                          cl.nombre AS ciclo, m.clave AS clave_materia, m.nombre AS materia, m.nivel, 
                          c.tipo_examen, c.puntaje,
                          (SELECT MAX(g2.nrc) FROM grupos g2 JOIN horarios h2 ON g2.nrc = h2.nrc WHERE g2.clave_grupo = g.clave_grupo AND h2.modalidad = 'PRESENCIAL') as nrc_presencial,
                          (SELECT MAX(g2.nrc) FROM grupos g2 JOIN horarios h2 ON g2.nrc = h2.nrc WHERE g2.clave_grupo = g.clave_grupo AND h2.modalidad = 'VIRTUAL') as nrc_virtual
                   FROM inscripciones i
                   JOIN alumnos a ON i.alumno_id = a.alumno_id
                   JOIN usuarios u ON a.usuario_id = u.usuario_id
                   JOIN grupos g ON i.nrc = g.nrc
                   LEFT JOIN materias m ON g.materia_id = m.materia_id
                   LEFT JOIN ciclos cl ON g.ciclo_id = cl.ciclo_id
                   LEFT JOIN calificaciones c ON i.inscripcion_id = c.inscripcion_id
                   ORDER BY u.codigo, g.nrc";
    $stmt_base = $pdo->query($query_base);

    $data_agrupada = [];
    while ($row = $stmt_base->fetch(PDO::FETCH_ASSOC)) {
        $id = $row['inscripcion_id'];
        if (!isset($data_agrupada[$id])) {
            $data_agrupada[$id] = [
                'codigo' => $row['codigo'],
                'nombre_completo' => trim($row['nombre_completo']),
                'correo' => $row['correo'],
                'carrera' => $row['carrera'],
                'nrc_presencial' => $row['nrc_presencial'] ? $row['nrc_presencial'] : 'NA',
                'nrc_virtual' => $row['nrc_virtual'] ? $row['nrc_virtual'] : 'NA',
                'ciclo' => $row['ciclo'],
                'clave_materia' => $row['clave_materia'],
                'materia' => $row['materia'],
                'nivel' => $row['nivel'],
                'calificaciones' => []
            ];
        }
        if ($row['tipo_examen']) {
            $norm_crit = $normalizar_criterio($row['tipo_examen']);
            $data_agrupada[$id]['calificaciones'][$norm_crit] = (float)$row['puntaje'];
        }
    }

    $file_calif = fopen($temp_dir . '/07_Calificaciones.csv', 'w');
    fprintf($file_calif, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($file_calif, $headers_calif);

    // Función para convertir nivel a número romano
    $nivel_a_romano = function($nivel) {
        $mapa = [1=>'I', 2=>'II', 3=>'III', 4=>'IV', 5=>'V', 6=>'VI', 7=>'VII', 8=>'VIII', 9=>'IX', 10=>'X'];
        $n = (int)$nivel;
        return isset($mapa[$n]) ? $mapa[$n] : $nivel;
    };

    foreach ($data_agrupada as $ins) {
        $materia_con_nivel = trim($ins['materia'] . ' ' . $nivel_a_romano($ins['nivel']));
        $fila = [
            $ins['codigo'],
            $ins['nombre_completo'],
            $ins['correo'],
            $ins['carrera'],
            $ins['nrc_presencial'],
            $ins['nrc_virtual'],
            $ins['ciclo'],
            $ins['clave_materia'],
            $materia_con_nivel
        ];

        $suma_final = 0;

        foreach ($criterios_normalizados as $crit) {
            $es_certificacion = ($crit === 'Certificación');
            $es_nivel_4 = (strpos((string)$ins['nivel'], '4') !== false || (int)$ins['nivel'] === 4);

            if (isset($ins['calificaciones'][$crit])) {
                $puntaje = $ins['calificaciones'][$crit];
                $fila[] = $puntaje;
                $suma_final += $puntaje;
            } else {
                if ($es_certificacion && !$es_nivel_4) {
                    $fila[] = 'NR';
                } else {
                    $fila[] = 'NA';
                }
            }
        }
        // Calificación final calculada dinámicamente sumando todos los criterios
        $fila[] = $suma_final;

        fputcsv($file_calif, $fila);
    }
    fclose($file_calif);

    // 8. Crear archivo ZIP
    actualizar_progreso($pdo, $exportacion_id, 80);
    $zip_filename = 'Exportacion_Global_' . date('Ymd_His') . '_ID' . $exportacion_id . '.zip';
    $archivos_dir = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'archivos';
    if (!is_dir($archivos_dir)) {
        mkdir($archivos_dir, 0777, true);
    }
    $zip_filepath = $archivos_dir . DIRECTORY_SEPARATOR . $zip_filename;

    $zip = new ZipArchive();
    if ($zip->open($zip_filepath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
        $files = glob($temp_dir . '/*.csv');
        foreach ($files as $file) {
            $zip->addFile($file, basename($file));
        }
        $zip->close();
    }

    // 4. Limpiar (Borrar CSVs y carpeta temporal)
    $files = glob($temp_dir . '/*.csv');
    foreach ($files as $file) {
        unlink($file);
    }
    rmdir($temp_dir);

    // 5. Marcar como COMPLETADO
    // Guardamos la ruta relativa para el frontend
    $ruta_db = 'descarga_masiva/archivos/' . $zip_filename;
    $stmt = $pdo->prepare("UPDATE exportaciones SET estado = 'COMPLETADO', progreso = 100, archivo_ruta = ?, fecha_completado = NOW() WHERE id = ?");
    $stmt->execute([$ruta_db, $exportacion_id]);
} catch (Exception $e) {
    // Si algo falla, marcar como ERROR
    $stmt = $pdo->prepare("UPDATE exportaciones SET estado = 'ERROR' WHERE id = ?");
    $stmt->execute([$exportacion_id]);
}
