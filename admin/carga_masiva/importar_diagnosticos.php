<?php
session_start();
require '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ADMIN') { header("Location: ../index.php"); exit; }

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["archivo_csv"])) {
    $archivo = $_FILES["archivo_csv"]["tmp_name"];
    if ($_FILES["archivo_csv"]["size"] == 0) { header("Location: vista_csv_diagnosticos.php?msg=error_file"); exit; }

    $file = fopen($archivo, "r");
    $fila_actual = 1;
    if (isset($_POST['ignorar_cabecera']) && $_POST['ignorar_cabecera'] == '1') { fgetcsv($file); $fila_actual = 2; }

    try {
        $pdo->beginTransaction(); 
        $registros_exitosos = 0;

        // Buscamos el alumno_id uniéndolo con el código de la tabla usuarios
        $stmt_check = $pdo->prepare("SELECT a.alumno_id FROM alumnos a JOIN usuarios u ON a.usuario_id = u.usuario_id WHERE u.codigo = ? LIMIT 1");
        
        // Insertamos en la tabla de examenes_diagnosticos
        $stmt_insert = $pdo->prepare("INSERT INTO examenes_diagnosticos (alumno_id, idioma, nivel_asignado, calificacion_texto, periodo, fecha_realizacion) VALUES (?, ?, ?, ?, ?, ?)");

        while (($datos = fgetcsv($file, 10000, ",")) !== FALSE) {
            $datos = array_map(function($valor) { $v = trim($valor); return mb_detect_encoding($v, 'UTF-8', true) ? $v : mb_convert_encoding($v, 'UTF-8', 'ISO-8859-1'); }, $datos);

            // Mapeo de las 6 columnas
            $codigo_alumno  = !empty($datos[0]) ? trim($datos[0]) : null;
            $idioma         = !empty($datos[1]) ? mb_strtoupper(trim($datos[1]), 'UTF-8') : null;
            $nivel_asignado = isset($datos[2]) && $datos[2] !== '' ? intval(trim($datos[2])) : null;
            $calif_texto    = !empty($datos[3]) ? mb_strtoupper(trim($datos[3]), 'UTF-8') : null;
            $periodo        = !empty($datos[4]) ? mb_strtoupper(trim($datos[4]), 'UTF-8') : null;
            $fecha_raw      = !empty($datos[5]) ? trim($datos[5]) : null;

            if (empty($codigo_alumno)) { $fila_actual++; continue; }

            // Verificamos que el código pertenezca a un alumno válido
            $stmt_check->execute([$codigo_alumno]);
            $alumno_id = $stmt_check->fetchColumn();

            if (!$alumno_id) { 
                $pdo->rollBack(); fclose($file); 
                header("Location: vista_csv_diagnosticos.php?msg=error_codigo&fila=$fila_actual&codigo=" . urlencode($codigo_alumno)); 
                exit; 
            }

            // Conversión de Fecha
            $fecha_mysql = null;
            if ($fecha_raw) {
                // Primero intentamos formato DD/MM/AAAA
                $dateObj = DateTime::createFromFormat('d/m/Y', $fecha_raw);
                // Si falla, intentamos formato YYYY-MM-DD
                if (!$dateObj) {
                    $dateObj = DateTime::createFromFormat('Y-m-d', $fecha_raw);
                }
                if ($dateObj) {
                    $fecha_mysql = $dateObj->format('Y-m-d');
                }
            }

            $stmt_insert->execute([$alumno_id, $idioma, $nivel_asignado, $calif_texto, $periodo, $fecha_mysql]);
            $registros_exitosos++; 
            $fila_actual++;
        }
        
        $pdo->commit(); fclose($file); 
        header("Location: vista_csv_diagnosticos.php?msg=ok_diagnosticos&total=$registros_exitosos"); 
        exit;
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack(); 
        if (isset($file)) fclose($file); 
        header("Location: vista_csv_diagnosticos.php?msg=error_db&fila=$fila_actual"); 
        exit;
    }
} else { 
    header("Location: vista_csv_diagnosticos.php"); 
    exit; 
}
?>