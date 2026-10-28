<?php
require_once 'perfil_logic.php';

/**
 * Declaración de variables importadas desde perfil_logic.php
 * para evitar falsos positivos de "Undefined variable" en el IDE.
 * 
 * @var string $mensaje
 * @var string $tipo_mensaje
 * @var array $profesor
 * @var string $foto_actual
 * @var string $texto_pass
 * @var string $clase_pass
 * @var string $icono_pass
 */
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil | Portal Docente</title>
    <link rel="stylesheet" href="../css/estilos.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/profesor.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <?php include 'menu_profesor.php'; ?>

    <main class="main-content">
        
        <?php if($mensaje): ?>
            <!-- ALERTA LIMPIA, EL CSS CONTROLA LOS COLORES AHORA -->
            <div class="alert <?php echo ($tipo_mensaje == 'success') ? 'alert-success' : 'alert-error'; ?>" style="margin-bottom: 20px;">
                <i class="fas <?php echo ($tipo_mensaje == 'success') ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?>"></i> <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="perfil" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            
            <div class="teacher-header-card">
                <div class="teacher-avatar-wrapper">
                    <img src="<?php echo $foto_actual; ?>" id="previewAvatar" class="teacher-avatar-img">
                    <label for="fotoInput" class="teacher-upload-btn" title="Cambiar Foto">
                        <i class="fas fa-camera"></i>
                    </label>
                    <input type="file" id="fotoInput" name="foto" accept="image/png, image/jpeg" style="display: none;" onchange="previewImage(this)">
                </div>
                <div class="teacher-info-wrapper">
                    <h1><?php echo htmlspecialchars($profesor['nombre'] . ' ' . $profesor['apellido_paterno']); ?></h1>
                    <p>
                        <span><i class="fas fa-chalkboard-teacher"></i> Docente Activo</span> 
                        <span style="opacity: 0.5;">|</span> 
                        <span><i class="fas fa-id-badge"></i> Código: <?php echo htmlspecialchars($profesor['codigo']); ?></span>
                    </p>
                </div>
            </div>

            <div class="content-card">
                <h3 class="card-title"><i class="fas fa-user-edit"></i> Información Personal</h3>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    
                    <div class="form-group">
                        <label>Nombre(s)</label>
                        <!-- CAMPOS LIMPIOS, EL CSS HACE LA MAGIA -->
                        <input type="text" value="<?php echo htmlspecialchars($profesor['nombre']); ?>" class="readonly-data" readonly title="Solicita cambios de nombre al Administrador">
                    </div>
                    <div class="form-group">
                        <label>Apellidos</label>
                        <input type="text" value="<?php echo htmlspecialchars($profesor['apellido_paterno'] . ' ' . $profesor['apellido_materno']); ?>" class="readonly-data" readonly>
                    </div>
                    <div class="form-group">
                        <label>Correo Institucional</label>
                        <input type="text" value="<?php echo htmlspecialchars($profesor['correo']); ?>" class="readonly-data" readonly>
                    </div>
                    <div class="form-group">
                        <label>Código de Profesor</label>
                        <input type="text" value="<?php echo htmlspecialchars($profesor['codigo']); ?>" class="readonly-data" readonly>
                    </div>

                    <div style="grid-column: span 2; margin: 10px 0;"><hr style="border: none; border-top: 1px solid #eee;"></div>

                    <div class="form-group">
                        <label style="color: var(--udg-blue); font-weight: bold;">Teléfono de Contacto</label>
                        <input type="text" name="telefono" value="<?php echo htmlspecialchars($profesor['telefono'] ?? ''); ?>" placeholder="Ej. 33 1234 5678">
                    </div>
                    <div class="form-group">
                        <label style="color: var(--udg-blue); font-weight: bold;">Actualizar Contraseña</label>
                        <input type="password" name="password" placeholder="Escribe aquí solo si deseas cambiarla">
                        <small style="display: block; margin-top: 5px; <?php echo $clase_pass; ?>">
                            <i class="<?php echo $icono_pass; ?>"></i> <?php echo $texto_pass; ?>
                        </small>
                    </div>
                </div>

                <div style="text-align: right; margin-top: 20px;">
                    <button type="submit" class="btn-save" style="font-size: 1.1rem; padding: 12px 30px;"><i class="fas fa-save"></i> Guardar Cambios</button>
                </div>
            </div>

        </form>

        <?php include '../componente_biometrico.php'; ?>

    </main>

    <?php include '../main_footer.php'; ?>

    <script>
        function toggleMobileMenu() { document.getElementById('navWrapper').classList.toggle('active'); document.getElementById('menuOverlay').classList.toggle('active'); }
        
        // Vista previa de la foto antes de guardar
        function previewImage(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('previewAvatar').src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        // Limpiar URL de refresh
        if (window.history.replaceState) { window.history.replaceState(null, null, window.location.href); }
    </script>
</body>
</html>