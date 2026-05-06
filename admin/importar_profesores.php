<?php
session_start();
require '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ADMIN') { header("Location: ../index.php"); exit; }

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["archivo_csv"])) {
    $archivo = $_FILES["archivo_csv"]["tmp_name"];
    
    if ($_FILES["archivo_csv"]["size"] > 0) {
        $file = fopen($archivo, "r");
        $fila_actual = 1;
        
        if (isset($_POST['ignorar_cabecera']) && $_POST['ignorar_cabecera'] == '1') {
            fgetcsv($file);
            $fila_actual = 2; 
        }

        try {
            $pdo->beginTransaction(); 

            $sql_user = "INSERT INTO usuarios (codigo, nombre, apellido_paterno, apellido_materno, correo, password, rol, estatus, genero, periodo_ingreso) VALUES (?, ?, ?, ?, ?, ?, 'PROFESOR', 'ACTIVO', ?, ?)";
            $stmt_user = $pdo->prepare($sql_user);
            
            $sql_prof = "INSERT INTO profesores (usuario_id, nacionalidad, experiencia) VALUES (?, ?, ?)";
            $stmt_prof = $pdo->prepare($sql_prof);

            $registros_exitosos = 0;
            $generos_permitidos = ['MASCULINO', 'FEMENINO', 'OTRO'];

            while (($datos = fgetcsv($file, 10000, ",")) !== FALSE) {
                // Limpiar encoding
                $datos = array_map(function($valor) {
                    $v = trim($valor); return mb_detect_encoding($v, 'UTF-8', true) ? $v : mb_convert_encoding($v, 'UTF-8', 'ISO-8859-1');
                }, $datos);

                $codigo = !empty($datos[0]) ? $datos[0] : null;
                $apellido_paterno = !empty($datos[1]) ? mb_strtoupper($datos[1], 'UTF-8') : null;
                $apellido_materno = !empty($datos[2]) ? mb_strtoupper($datos[2], 'UTF-8') : null;
                $nombre = !empty($datos[3]) ? mb_strtoupper($datos[3], 'UTF-8') : null;
                $nacionalidad = !empty($datos[4]) ? mb_strtoupper($datos[4], 'UTF-8') : null;
                $experiencia = !empty($datos[5]) ? mb_strtoupper($datos[5], 'UTF-8') : null;
                $genero = !empty($datos[6]) ? strtoupper($datos[6]) : null;
                $correo = !empty($datos[7]) ? strtolower($datos[7]) : null;
                $periodo = !empty($datos[8]) ? strtoupper($datos[8]) : null;

                if (empty($nombre) || empty($correo) || empty($codigo)) {
                    $fila_actual++; continue;
                }

                if (!in_array($genero, $generos_permitidos)) $genero = null;

                // Contraseña Profesores: PROFE + Últimos 6 dígitos
                $ultimos_6 = substr($codigo, -6);
                $hash_password = password_hash("PROFE" . $ultimos_6, PASSWORD_DEFAULT);
                
                try {
                    $stmt_user->execute([$codigo, $nombre, $apellido_paterno, $apellido_materno, $correo, $hash_password, $genero, $periodo]);
                    $new_id = $pdo->lastInsertId();
                    $stmt_prof->execute([$new_id, $nacionalidad, $experiencia]);
                } catch (PDOException $e) {
                    if ($e->errorInfo[1] == 1062) {
                        $pdo->rollBack(); fclose($file);
                        header("Location: importar_csv.php?msg=dup&codigo=" . urlencode($codigo) . "&fila=" . $fila_actual);
                        exit;
                    }
                    throw $e; 
                }
                
                $registros_exitosos++;
                $fila_actual++;
            }
            
            $pdo->commit(); fclose($file);
            header("Location: importar_csv.php?msg=ok_profesores&total=" . $registros_exitosos); exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if (isset($file)) fclose($file);
            header("Location: importar_csv.php?msg=error_db"); exit;
        }
    }
}
header("Location: importar_csv.php?msg=error_file"); exit;
?>