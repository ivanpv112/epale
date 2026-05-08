<?php
session_start();
require '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ADMIN') { header("Location: ../index.php"); exit; }

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["archivo_csv"])) {
    $archivo = $_FILES["archivo_csv"]["tmp_name"];
    if ($_FILES["archivo_csv"]["size"] == 0) { header("Location: vista_csv_certificaciones.php?msg=error_file"); exit; }

    $file = fopen($archivo, "r");
    $fila_actual = 1;
    if (isset($_POST['ignorar_cabecera']) && $_POST['ignorar_cabecera'] == '1') { fgetcsv($file); $fila_actual = 2; }

    try {
        $pdo->beginTransaction(); 
        $registros_exitosos = 0;

        // Búsqueda del ID del alumno mediante su código
        $stmt_check_alumno = $pdo->prepare("SELECT a.alumno_id FROM alumnos a JOIN usuarios u ON a.usuario_id = u.usuario_id WHERE u.codigo = ? LIMIT 1");
        
        // Verificación si ya existe una certificación para ese idioma
        $stmt_check_cert = $pdo->prepare("SELECT certificacion_id FROM certificaciones WHERE alumno_id = ? AND idioma = ? LIMIT 1");
        
        // Insert y Update 
        $stmt_insert = $pdo->prepare("INSERT INTO certificaciones (alumno_id, periodo, idioma, puntaje, nivel_obtenido, fecha_aplicacion) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt_update = $pdo->prepare("UPDATE certificaciones SET periodo = ?, puntaje = ?, nivel_obtenido = ?, fecha_aplicacion = ? WHERE certificacion_id = ?");

        while (($datos = fgetcsv($file, 10000, ",")) !== FALSE) {
            $datos = array_map(function($valor) { $v = trim($valor); return mb_detect_encoding($v, 'UTF-8', true) ? $v : mb_convert_encoding($v, 'UTF-8', 'ISO-8859-1'); }, $datos);

            // Mapeo exacto a las 6 columnas
            $codigo_alumno = !empty($datos[0]) ? trim($datos[0]) : null;                        // A: CODIGO
            $periodo       = !empty($datos[1]) ? mb_strtoupper(trim($datos[1]), 'UTF-8') : null; // B: PERIODO
            $idioma        = !empty($datos[2]) ? mb_strtoupper(trim($datos[2]), 'UTF-8') : null; // C: TIPO (Idioma)
            $puntaje       = !empty($datos[3]) ? trim($datos[3]) : null;                         // D: PUNTAJE
            $nivel         = !empty($datos[4]) ? mb_strtoupper(trim($datos[4]), 'UTF-8') : null; // E: EQUIVALENCIA
            $fecha_raw     = !empty($datos[5]) ? trim($datos[5]) : null;                         // F: FECHA_DE_APLICACION

            if (empty($codigo_alumno) || empty($idioma)) { $fila_actual++; continue; }

            // Verifica que el alumno exista
            $stmt_check_alumno->execute([$codigo_alumno]);
            $alumno_id = $stmt_check_alumno->fetchColumn();

            if (!$alumno_id) { 
                $pdo->rollBack(); fclose($file); 
                header("Location: vista_csv_certificaciones.php?msg=error_codigo&fila=$fila_actual&codigo=" . urlencode($codigo_alumno)); 
                exit; 
            }

            // Convertir Fecha de DD/MM/AAAA a formato SQL (YYYY-MM-DD)
            $fecha_mysql = null;
            if ($fecha_raw) {
                $dateObj = DateTime::createFromFormat('d/m/Y', $fecha_raw);
                if (!$dateObj) $dateObj = DateTime::createFromFormat('Y-m-d', $fecha_raw);
                if ($dateObj) $fecha_mysql = $dateObj->format('Y-m-d');
            }

            // Verifica si ya tiene esa certificación para actualizarla o insertarla
            $stmt_check_cert->execute([$alumno_id, $idioma]);
            $cert_id_existente = $stmt_check_cert->fetchColumn();

            if ($cert_id_existente) {
                // Actualiza registro existente
                $stmt_update->execute([$periodo, $puntaje, $nivel, $fecha_mysql, $cert_id_existente]);
            } else {
                // Inserta nuevo registro
                $stmt_insert->execute([$alumno_id, $periodo, $idioma, $puntaje, $nivel, $fecha_mysql]);
            }

            $registros_exitosos++; 
            $fila_actual++;
        }
        
        $pdo->commit(); fclose($file); 
        header("Location: vista_csv_certificaciones.php?msg=ok_certificaciones&total=$registros_exitosos"); 
        exit;
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack(); 
        if (isset($file)) fclose($file); 
        header("Location: vista_csv_certificaciones.php?msg=error_db&fila=$fila_actual"); 
        exit;
    }
} else { 
    header("Location: vista_csv_certificaciones.php"); 
    exit; 
}
?>