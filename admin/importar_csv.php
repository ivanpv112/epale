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

            // Contraseña por defecto para usuarios masivos
            $default_password = password_hash("12345", PASSWORD_DEFAULT); 
            
            $registros_exitosos = 0;

            // Leer línea por línea separada por comas
            while (($datos = fgetcsv($file, 10000, ",")) !== FALSE) {
                
                // Limpiamos y detectamos inteligentemente el formato
                $datos = array_map(function($valor) {
                    $valor_limpio = trim($valor);
                    // Detecta si ya viene en UTF-8 correcto
                    if (mb_detect_encoding($valor_limpio, 'UTF-8', true)) {
                        return $valor_limpio;
                    } else {
                        // Si viene en formato Excel viejo, lo convierte a UTF-8
                        return mb_convert_encoding($valor_limpio, 'UTF-8', 'ISO-8859-1');
                    }
                }, $datos);

                // Orden esperado: 0: codigo, 1: nombre, 2: ap_paterno, 3: ap_materno, 4: correo, 5: telefono, 6: rol, 7: carrera
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
                    $new_id = $pdo->lastInsertId();
                    $stmt_alum->execute([$new_id, $carrera]);
                }
                
                $registros_exitosos++;
            }
            
            $pdo->commit(); 
            fclose($file);
            
            // Redirigir a esta misma página con éxito
            header("Location: importar_csv.php?msg=import_ok&total=" . $registros_exitosos);
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack(); 
            fclose($file);
            $modal_error = "Error en la fila " . ($registros_exitosos + 1) . ". Verifica que los correos o códigos no estén duplicados.";
        }
    }
}

// Mensajes a la vista
$mensaje = isset($_GET['msg']) ? $_GET['msg'] : '';
$total = isset($_GET['total']) ? $_GET['total'] : 0;
$tipo_mensaje = '';

if ($mensaje === 'import_ok') {
    $tipo_mensaje = 'success';
    $mensaje = "¡Excelente! Se importaron correctamente <strong>" . $total . " usuarios</strong> a la base de datos.";
} elseif ($mensaje === 'error_file') {
    $tipo_mensaje = 'error';
    $mensaje = "Error: No se seleccionó ningún archivo válido.";
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
    <link rel="stylesheet" href="../css/estudiante.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .import-section {
            max-width: 700px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        .file-input-wrapper { margin: 20px 0; }
        .file-input-label {
            display: block;
            padding: 30px 20px;
            background-color: #f8f9fa;
            border: 2px dashed #ccc;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            font-weight: 500;
            color: #555;
            transition: all 0.3s ease;
        }
        .file-input-label:hover { background-color: #e7f3ff; border-color: var(--udg-blue); color: var(--udg-blue); }
        .file-input-label i { font-size: 2.5rem; display: block; margin-bottom: 10px; color: var(--udg-light); }
        
        #archivo_csv { position: absolute; left: -9999px; }
        #fileName { display: block; text-align: center; margin-top: 10px; color: #666; font-size: 0.95rem; font-weight: bold; }
        
        .help-box { margin-top: 30px; padding: 20px; background-color: #f1f3f5; border-radius: 8px; border-left: 4px solid var(--udg-blue); }
        .help-box code { background-color: white; padding: 3px 8px; border-radius: 4px; font-family: monospace; color: #dc3545; font-size: 0.9rem; font-weight: bold; border: 1px solid #ddd; margin: 2px; display: inline-block;}
    </style>
</head>
<body>

    <?php include 'menu_admin.php'; ?>

    <main class="main-content">
        
        <a href="<?php echo htmlspecialchars($url_volver); ?>" style="display: inline-block; margin-bottom: 20px; color: var(--udg-blue); text-decoration: none; font-weight: bold;">
            <i class="fas fa-arrow-left"></i> Volver a la página anterior
        </a>

        <div class="page-title-center" style="margin-bottom: 30px;">
            <h1><i class="fas fa-file-csv"></i> Carga Masiva de Usuarios</h1>
            <p>Sube un archivo de Excel (guardado en formato CSV) para registrar múltiples alumnos y profesores a la vez.</p>
        </div>

        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?>" style="max-width: 700px; margin: 0 auto 20px auto; padding: 15px; border-radius: 8px; text-align: center; font-size: 1.1rem;">
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>

        <div class="import-section">
            <form method="POST" enctype="multipart/form-data" id="csvForm">
                
                <div class="file-input-wrapper">
                    <label for="archivo_csv" class="file-input-label">
                        <i class="fas fa-cloud-upload-alt"></i> 
                        Haz clic aquí para seleccionar tu archivo CSV
                    </label>
                    <input type="file" name="archivo_csv" id="archivo_csv" accept=".csv" required>
                    <span id="fileName">No se ha elegido ningún archivo</span>
                </div>

                <div style="background-color: #f8f9fa; padding: 15px 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid var(--udg-light);">
                    <label style="cursor: pointer; display: flex; align-items: center; gap: 10px; font-weight: 500; color: #444;">
                        <input type="checkbox" name="ignorar_cabecera" value="1" checked style="width: 18px; height: 18px; cursor: pointer;">
                        El archivo incluye fila de encabezados (saltar la fila 1)
                    </label>
                </div>

                <button type="submit" class="btn-save" id="submitBtn" disabled style="width: 100%; justify-content: center; font-size: 1.1rem; padding: 12px; background-color: #ccc; cursor: not-allowed;">
                    <i class="fas fa-upload"></i> Subir e Importar Archivo
                </button>
            </form>

            <div class="help-box">
                <h4 style="margin-top: 0; color: var(--udg-blue);"><i class="fas fa-info-circle"></i> Instrucciones de Formato</h4>
                <p style="font-size: 0.95rem; color: #555;">El archivo Excel debe tener las columnas <strong>exactamente en este orden</strong> antes de guardarlo como CSV:</p>
                
                <div style="margin-top: 15px; margin-bottom: 20px;">
                    <code>1. Código</code>
                    <code>2. Nombre</code>
                    <code>3. Apellido Paterno</code>
                    <code>4. Apellido Materno</code>
                    <code>5. Correo</code>
                    <code>6. Teléfono</code>
                    <code>7. Rol</code>
                    <code>8. Carrera</code>
                </div>
                
                <p style="margin: 0; font-weight: bold; font-size: 0.9rem; color: #333;">Ejemplos de llenado:</p>
                <ul style="font-size: 0.85rem; color: #666; background: white; padding: 15px 15px 15px 35px; border-radius: 6px; border: 1px solid #ddd; margin-top: 5px;">
                    <li style="margin-bottom: 5px;"><strong>Un Administrador:</strong> AD01, Juan, Pérez, García, juan@epale.com, 3331234567, ADMIN, <em>(Dejar vacío)</em></li>
                    <li style="margin-bottom: 5px;"><strong>Un Alumno:</strong> 219511, María, López, Martínez, maria@epale.com, 3333334567, ALUMNO, LIME</li>
                    <li><strong>Un Profesor:</strong> PR01, Carlos, Rodríguez, Sánchez, carlos@epale.com, 3334445555, PROFESOR, <em>(Dejar vacío)</em></li>
                </ul>
                <p style="font-size: 0.8rem; color: #dc3545; margin-top: 15px; margin-bottom: 0;">* Nota: La contraseña de todos los usuarios importados será <strong>12345</strong> por defecto.</p>
            </div>
        </div>
    </main>

    <footer class="main-footer"><div class="address-bar">Copyright © 2026 E-PALE | Panel de Administración</div></footer>

    <script>
        // 1. Script para el menú lateral
        function toggleMobileMenu() {
            document.getElementById('navWrapper').classList.toggle('active');
            document.getElementById('menuOverlay').classList.toggle('active');
        }

        // 2. Script para detectar cuando se sube el archivo
        const fileInput = document.getElementById('archivo_csv');
        const fileName = document.getElementById('fileName');
        const submitBtn = document.getElementById('submitBtn');

        fileInput.addEventListener('change', function() {
            if (this.files && this.files.length > 0) {
                fileName.innerHTML = '<i class="fas fa-file-excel"></i> ' + this.files[0].name;
                fileName.style.color = '#28a745';
                
                // Activar el botón
                submitBtn.disabled = false;
                submitBtn.style.backgroundColor = 'var(--udg-blue)';
                submitBtn.style.cursor = 'pointer';
            } else {
                fileName.textContent = 'No se ha elegido ningún archivo';
                fileName.style.color = '#666';
                
                // Desactivar el botón
                submitBtn.disabled = true;
                submitBtn.style.backgroundColor = '#ccc';
                submitBtn.style.cursor = 'not-allowed';
            }
        });
    </script>
</body>
</html>
