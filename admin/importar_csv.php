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
            $sql_user = "INSERT INTO usuarios (codigo, nombre, apellido_paterno, apellido_materno, correo, password, telefono, rol, estatus) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVO')";
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

                // Mapear columnas según el orden definido (ahora con campos separados para apellidos)
                // Orden esperado: 0: codigo, 1: nombre, 2: apellido_paterno, 3: apellido_materno, 4: correo, 5: telefono, 6: rol, 7: carrera
                $codigo = !empty($datos[0]) ? $datos[0] : null;
                $nombre = isset($datos[1]) ? $datos[1] : null;
                $apellido_paterno = isset($datos[2]) && $datos[2] !== '' ? $datos[2] : null;
                $apellido_materno = isset($datos[3]) && $datos[3] !== '' ? $datos[3] : null;
                $correo = isset($datos[4]) ? $datos[4] : null;
                $telefono = !empty($datos[5]) ? $datos[5] : null;
                $rol = isset($datos[6]) ? strtoupper($datos[6]) : '';
                $carrera = isset($datos[7]) && !empty($datos[7]) ? $datos[7] : null;

                // Validación básica: saltar la fila si falta nombre, correo o rol
                if (empty($nombre) || empty($correo) || empty($rol)) {
                    continue;
                }
                // 1. Insertar Usuario
                $stmt_user->execute([
                    $codigo, $nombre, $apellido_paterno, $apellido_materno, $correo, $default_password, $telefono, $rol
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
            $modal_error = "Error en la fila " . ($registros_exitosos + 1) . ". Verifica que los correos o códigos no estén duplicados en la base de datos.";
        }
    }
}

// Variables para pasar mensajes a la vista
$mensaje = isset($_GET['msg']) ? $_GET['msg'] : '';
$total = isset($_GET['total']) ? $_GET['total'] : 0;
$tipo_mensaje = '';

if ($mensaje === 'import_ok') {
    $tipo_mensaje = 'success';
    $mensaje = "✓ Se importaron exitosamente " . $total . " usuario(s) a la base de datos.";
} elseif ($mensaje === 'error_file') {
    $tipo_mensaje = 'error';
    $mensaje = " Error: No se seleccionó ningún archivo válido.";
} elseif (isset($modal_error)) {
    $tipo_mensaje = 'error';
    $mensaje = $modal_error;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cargar CSV | Admin E-PALE</title>
    <link rel="stylesheet" href="../css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .import-section {
            max-width: 700px;
            margin: 30px auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .file-input-wrapper {
            margin: 20px 0;
        }

        .file-input-label {
            display: block;
            padding: 15px 20px;
            background-color: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 6px;
            text-align: center;
            cursor: pointer;
            font-weight: 500;
            color: #495057;
            transition: all 0.3s ease;
        }

        .file-input-label:hover {
            background-color: #e9ecef;
            border-color: #adb5bd;
        }

        #archivo_csv {
            position: absolute;
            left: -9999px;
        }

        #fileName {
            display: block;
            text-align: center;
            margin-top: 10px;
            color: #6c757d;
            font-size: 0.9rem;
        }

        .form-options {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin: 20px 0;
            border-left: 4px solid #001a57;
        }

        .form-options label {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            font-weight: 500;
        }

        .form-options input[type="checkbox"] {
            cursor: pointer;
        }

        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 25px;
        }

        .button-group .btn {
            flex: 1;
        }

        .help-box {
            margin-top: 25px;
            padding: 15px;
            background-color: #e7f3ff;
            border-radius: 6px;
            border-left: 4px solid #2196F3;
        }

        .help-box h4 {
            color: #001a57;
            margin: 0 0 10px 0;
            font-size: 1rem;
        }

        .help-box p {
            margin: 8px 0;
            color: #333;
            font-size: 0.95rem;
        }

        .help-box code {
            background-color: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            color: #d63384;
            font-size: 0.9rem;
        }

        .alert-message {
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-brand">
            <img src="../img/logo-pale.png" alt="Logo" style="height: 35px;"> e-PALE
        </div>
        <div class="navbar-menu">
            <a href="usuarios.php">USUARIOS</a>
            <a href="#">MATERIAS</a>
            <a href="#">REPORTES</a>
            <a href="importar_csv.php" class="active">CARGAR</a>
        </div>
        <div class="user-profile">
            <i class="fas fa-user-circle fa-lg"></i> PERFIL <i class="fas fa-sign-out-alt" onclick="window.location.href='../logout.php'" title="Salir" style="margin-left:10px; cursor:pointer;"></i>
        </div>
    </nav>

    <div class="main-container">
        <div class="header-title">
            <h1><i class="fas fa-file-csv"></i> Cargar Usuarios desde CSV</h1>
            <a href="usuarios.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Regresar</a>
        </div>

        <?php if (!empty($mensaje)): ?>
            <div class="alert-message <?php echo ($tipo_mensaje === 'success') ? 'alert-success' : 'alert-error'; ?>">
                <i class="fas fa-<?php echo ($tipo_mensaje === 'success') ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <span><?php echo $mensaje; ?></span>
            </div>
        <?php endif; ?>

        <div class="import-section">
            <form method="POST" enctype="multipart/form-data" id="csvForm">
                <div class="file-input-wrapper">
                    <label for="archivo_csv" class="file-input-label">
                        <i class="fas fa-cloud-upload-alt"></i> Selecciona un archivo CSV
                    </label>
                    <input type="file" name="archivo_csv" id="archivo_csv" accept=".csv" required>
                    <span id="fileName">No se eligió ningún archivo</span>
                </div>

                <div class="form-options">
                    <label>
                        <input type="checkbox" name="ignorar_cabecera" value="1" checked>
                        El archivo incluye fila de encabezados (saltar primera fila)
                    </label>
                </div>

                <div class="button-group">
                    <button type="submit" class="btn btn-save" id="submitBtn" disabled>
                        <i class="fas fa-upload"></i> Cargar Archivo
                    </button>
                </div>
            </form>

            <div class="help-box">
                <h4><i class="fas fa-info-circle"></i> Formato del archivo CSV</h4>
                <p><strong>El archivo debe contener las siguientes columnas (en este orden):</strong></p>
                <p style="margin-top: 10px;">
                    <code>1. Código</code> | 
                    <code>2. Nombre</code> | 
                    <code>3. Apellido Paterno</code> | 
                    <code>4. Apellido Materno</code> | 
                    <code>5. Correo</code> | 
                    <code>6. Teléfono</code> | 
                    <code>7. Rol</code> | 
                    <code>8. Carrera</code>
                </p>
                <p style="margin-top: 15px;"><strong>Ejemplos:</strong></p>
                <p>
                    <code>AD01,Juan,Pérez,García,juan@epale.com,3331234567,ADMIN,</code><br>
                    <code>219511,María,López,Martínez,maria@epale.com,3333334567,ALUMNO,LIME</code><br>
                    <code>PR01,Carlos,Rodríguez,Sánchez,carlos@epale.com,3334445555,PROFESOR,</code>
                </p>
            </div>
        </div>
    </div>

    <script>
        const fileInput = document.getElementById('archivo_csv');
        const fileName = document.getElementById('fileName');
        const submitBtn = document.getElementById('submitBtn');

        fileInput.addEventListener('change', function() {
            if (this.files && this.files.length > 0) {
                fileName.textContent = '✓ ' + this.files[0].name;
                fileName.style.color = '#28a745';
                fileName.style.fontWeight = 'bold';
                submitBtn.disabled = false;
            } else {
                fileName.textContent = 'No se eligió ningún archivo';
                fileName.style.color = '#6c757d';
                fileName.style.fontWeight = 'normal';
                submitBtn.disabled = true;
            }
        });
    </script>
</body>
</html>