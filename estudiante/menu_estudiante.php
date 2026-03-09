<?php
// ==========================================
// MOTOR DE "VOLVER INTELIGENTE" (Versión Estudiante)
// ==========================================
if (!isset($_SESSION['smart_back_estudiante'])) {
    $_SESSION['smart_back_estudiante'] = [];
}
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$pagina_actual = basename($_SERVER['PHP_SELF']);

if (!empty($referer)) {
    $referer_path = basename(parse_url($referer, PHP_URL_PATH));
    // Scripts silenciosos que no deben borrar la memoria del botón volver
    $scripts_excluidos = ['login.php', 'logout.php', 'procesar_perfil.php'];
    
    if ($referer_path !== '' && $referer_path !== $pagina_actual && !in_array($referer_path, $scripts_excluidos)) {
        $_SESSION['smart_back_estudiante'][$pagina_actual] = $referer;
    }
}
// Variable mágica para el botón volver
$url_volver = $_SESSION['smart_back_estudiante'][$pagina_actual] ?? 'index.php';
// ==========================================

// Obtenemos la foto del estudiante para la barra superior
$stmt_foto_menu = $pdo->prepare("SELECT foto_perfil, nombre FROM usuarios WHERE usuario_id = ?");
$stmt_foto_menu->execute([$_SESSION['user_id']]);
$est_menu = $stmt_foto_menu->fetch(PDO::FETCH_ASSOC);

$foto_menu = "../img/avatar-default.png"; 
if($est_menu['foto_perfil'] && file_exists("../img/perfiles/" . $est_menu['foto_perfil'])) {
    $foto_menu = "../img/perfiles/" . $est_menu['foto_perfil'];
}
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<header class="main-header" style="display: flex; justify-content: space-between; align-items: center; padding: 10px 20px; height: 65px;">
    <div class="logo-container" style="display: flex; align-items: center; width: auto; margin: 0;">
        <a href="index.php" style="display: flex; align-items: center; gap: 10px; text-decoration: none; color: white;">
            <img src="../img/logo-pale.png" alt="E-PALE" class="logo-img">
            <span style="font-size: 1.2rem; font-weight: bold;">e-PALE</span>
        </a>
    </div>

    <div style="display: flex; align-items: center; gap: 15px;">
        <a href="perfil.php" style="text-decoration: none; color: white; display: flex; align-items: center; gap: 8px; background: rgba(255,255,255,0.1); padding: 5px 15px 5px 5px; border-radius: 20px;">
            <img src="<?php echo $foto_menu; ?>" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover; border: 2px solid white; background:white;">
            <span class="profile-name" style="font-weight: 500;"><?php echo strtok($est_menu['nombre'], " "); ?></span>
        </a>
        <button onclick="toggleMobileMenu()" style="background: transparent; border: none; color: white; font-size: 1.8rem; cursor: pointer; padding: 0;">
            <i class="fas fa-bars"></i>
        </button>
    </div>
</header>

<div class="menu-overlay" id="menuOverlay" onclick="toggleMobileMenu()"></div>

<aside class="yt-sidebar" id="navWrapper">
    <div class="yt-sidebar-header">
        <span style="color: white; font-size: 1.1rem; font-weight: bold;">Menú Principal</span>
        <button onclick="toggleMobileMenu()" style="background: transparent; border: none; color: #aaa; font-size: 2rem; cursor: pointer; line-height: 1;">&times;</button>
    </div>

    <ul class="yt-sidebar-menu">
        <li><a href="index.php" class="<?php echo ($pagina_actual == 'index.php') ? 'active' : ''; ?>"><i class="fas fa-home"></i> Inicio</a></li>

        <li><a href="calificaciones.php" class="<?php echo ($pagina_actual == 'calificaciones.php') ? 'active' : ''; ?>"><i class="fas fa-clipboard-check"></i> Calificaciones</a></li>
        
        <li><a href="horario.php" class="<?php echo ($pagina_actual == 'horario.php' || $pagina_actual == 'detalle_materia.php') ? 'active' : ''; ?>"><i class="fas fa-calendar-alt"></i> Horario</a></li>
        
        <li><a href="oferta.php" class="<?php echo ($pagina_actual == 'oferta.php') ? 'active' : ''; ?>"><i class="fas fa-book-open"></i> Oferta</a></li>
    </ul>

    <div class="sidebar-divider"></div>

    <ul class="yt-sidebar-menu">
        <li><a href="perfil.php" class="<?php echo ($pagina_actual == 'perfil.php') ? 'active' : ''; ?>"><i class="far fa-user-circle"></i> Mi Perfil</a></li>
        <li><a href="#" onclick="confirmarSalida(event)" style="color: #ff6b6b;"><i class="fas fa-sign-out-alt" style="color: #ff6b6b;"></i> Cerrar Sesión</a></li>
    </ul>
</aside>

<script>
    function toggleMobileMenu() {
        document.getElementById('navWrapper').classList.toggle('active');
        document.getElementById('menuOverlay').classList.toggle('active');
    }

    function confirmarSalida(event) {
        event.preventDefault(); 
        
        // Cierra el menú lateral primero si está en móvil
        document.getElementById('navWrapper').classList.remove('active');
        document.getElementById('menuOverlay').classList.remove('active');

        Swal.fire({
            title: '¿Cerrar Sesión?',
            text: "Saldrás de tu portal de alumno.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-sign-out-alt"></i> Sí, salir',
            cancelButtonText: 'Cancelar',
            reverseButtons: true,
            backdrop: `rgba(0,0,123,0.4)`
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = '../logout.php';
            }
        });
    }
</script>