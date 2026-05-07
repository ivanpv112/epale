<?php
session_start();
require '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ADMIN') { header("Location: ../index.php"); exit; }

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["archivo_csv"])) {
    $archivo = $_FILES["archivo_csv"]["tmp_name"];
    if ($_FILES["archivo_csv"]["size"] == 0) { header("Location: interfaz_csv.php?msg=error_file"); exit; }

    $file = fopen($archivo, "r");
    $fila_actual = 1;
    if (isset($_POST['ignorar_cabecera']) && $_POST['ignorar_cabecera'] == '1') { fgetcsv($file); $fila_actual = 2; }

    try {
        $pdo->beginTransaction(); 
        $registros_exitosos = 0;

        $stmt_check = $pdo->prepare("SELECT usuario_id FROM usuarios WHERE codigo = ? LIMIT 1");
        $stmt_insert = $pdo->prepare("INSERT INTO examenes_diagnosticos (usuario_id, ciclo, nombre_examen, idioma, puntaje, puntos_maximos, nivel_asignado, fecha_realizacion) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

        while (($datos = fgetcsv($file, 10000, ",")) !== FALSE) {
            $datos = array_map(function($valor) { $v = trim($valor); return mb_detect_encoding($v, 'UTF-8', true) ? $v : mb_convert_encoding($v, 'UTF-8', 'ISO-8859-1'); }, $datos);

            $codigo_alumno = !empty($datos[0]) ? $datos[0] : null;
            $ciclo = !empty($datos[2]) ? $datos[2] : null;
            $nombre_examen = !empty($datos[3]) ? $datos[3] : null;
            $idioma = !empty($datos[4]) ? $datos[4] : null;
            $puntaje = isset($datos[5]) && $datos[5] !== '' ? intval($datos[5]) : null;
            $puntos_max = isset($datos[6]) && $datos[6] !== '' ? intval($datos[6]) : null;
            $nivel = !empty($datos[7]) ? strtoupper($datos[7]) : null;
            $fecha_raw = !empty($datos[8]) ? $datos[8] : null;

            if (empty($codigo_alumno)) { $fila_actual++; continue; }

            $stmt_check->execute([$codigo_alumno]);
            $usuario_id = $stmt_check->fetchColumn();

            if (!$usuario_id) { $pdo->rollBack(); fclose($file); header("Location: interfaz_csv.php?msg=error_codigo&fila=$fila_actual&codigo=" . urlencode($codigo_alumno)); exit; }

            $fecha_mysql = null;
            if ($fecha_raw) {
                $dateObj = DateTime::createFromFormat('d/m/Y', $fecha_raw);
                if ($dateObj) $fecha_mysql = $dateObj->format('Y-m-d');
            }

            $stmt_insert->execute([$usuario_id, $ciclo, $nombre_examen, $idioma, $puntaje, $puntos_max, $nivel, $fecha_mysql]);
            $registros_exitosos++; $fila_actual++;
        }
        $pdo->commit(); fclose($file); header("Location: interfaz_csv.php?msg=ok_diagnosticos&total=$registros_exitosos"); exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack(); if (isset($file)) fclose($file); header("Location: interfaz_csv.php?msg=error_db&fila=$fila_actual"); exit;
    }
} else { header("Location: interfaz_csv.php"); exit; }
?>