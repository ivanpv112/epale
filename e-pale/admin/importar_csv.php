<?php
session_start();
require '../db.php';

// Validar seguridad
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ADMIN') {
    header("Location: ../index.php"); exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["archivo_csv"])) {
    
    $archivo = $_FILES["archivo_csv"]["tmp_name"];
    
    // Validar que se haya subido un archivo válido
    if ($_FILES["archivo_csv"]["size"] > 0) {
        
        // Abrir el archivo en modo lectura
        $file = fopen($archivo, "r");
        
        // Si el usuario marcó la casilla, saltamos la primera línea (encabezados)
        if (isset($_POST['ignorar_cabecera']) && $_POST['ignorar_cabecera'] == '1') {
            fgetcsv($file);
        }

        try {
            $pdo->beginTransaction(); // Todo o nada

            // Preparamos las consultas para optimizar rendimiento (se compilan 1 sola vez)
            $sql_user = "INSERT INTO usuarios (codigo, nombre, apellidos, correo, password, telefono, rol, estatus) VALUES (?, ?, ?, ?, ?, ?, ?, 'ACTIVO')";
            $stmt_user = $pdo->prepare($sql_user);
            
            $sql_alum = "INSERT INTO alumnos (usuario_id, carrera) VALUES (?, ?)";
            $stmt_alum = $pdo->prepare($sql_alum);

            // Contraseña por defecto para usuarios masivos (Igual a tu guardar_usuario.php)
            $default_password = password_hash("12345", PASSWORD_DEFAULT); 
            
            $registros_exitosos = 0;

            // Leer línea por línea separada por comas (o punto y coma)
            // Leer línea por línea separada por comas (o punto y coma)
            while (($datos = fgetcsv($file, 10000, ",")) !== FALSE) {
                
                // NUEVO: Limpiamos y detectamos inteligentemente el formato
                $datos = array_map(function($valor) {
                    $valor_limpio = trim($valor);
                    // Detecta si ya viene en UTF-8 correcto
                    if (mb_detect_encoding($valor_limpio, 'UTF-8', true)) {
                        return $valor_limpio; // Lo deja intacto (como tu archivo actual)
                    } else {
                        // Si viene en formato Excel viejo (ISO-8859-1), lo convierte a UTF-8
                        return mb_convert_encoding($valor_limpio, 'UTF-8', 'ISO-8859-1');
                    }
                }, $datos);

                // Mapear columnas según el orden definido
                $codigo = !empty($datos[0]) ? $datos[0] : null;

                // Mapear columnas según el orden definido (ahora ya están en UTF-8)
                $codigo = !empty($datos[0]) ? $datos[0] : null;
                $nombre = $datos[1];
                $apellidos = $datos[2];
                $correo = $datos[3];
                $telefono = !empty($datos[4]) ? $datos[4] : null;
                $rol = strtoupper($datos[5]); // ALUMNO, PROFESOR, ADMIN
                $carrera = isset($datos[6]) && !empty($datos[6]) ? $datos[6] : null;

                // Validación básica: saltar la fila si falta nombre, correo o rol
                if (empty($nombre) || empty($correo) || empty($rol)) {
                    continue; 
                }
                // 1. Insertar Usuario
                $stmt_user->execute([
                    $codigo, $nombre, $apellidos, $correo, $default_password, $telefono, $rol
                ]);
                
                // 2. Si es alumno, insertar en tabla alumnos
                if ($rol === 'ALUMNO') {
                    $new_id = $pdo->lastInsertId(); // Extrae el ID que se acaba de crear
                    $stmt_alum->execute([$new_id, $carrera]);
                }
                
                $registros_exitosos++;
            }
            
            $pdo->commit(); // Confirmar cambios en la BD
            fclose($file);
            
            // Redirigir con éxito
            header("Location: usuarios.php?msg=import_ok&total=" . $registros_exitosos);
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack(); // Si hay un error (ej. un correo o código duplicado), deshacer todo
            fclose($file);
            die("Error en la fila " . ($registros_exitosos + 1) . ". Verifica que los correos o códigos no estén duplicados en la base de datos. Detalle técnico: " . $e->getMessage());
        }
    }
} else {
    header("Location: usuarios.php?msg=error_file");
}
?>