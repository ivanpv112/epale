<?php
// Importar archivo de seguridad (centraliza la generación del token)
require_once '../security.php';

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
if ($est_menu['foto_perfil'] && file_exists("../img/perfiles/" . $est_menu['foto_perfil'])) {
    $foto_menu = "../img/perfiles/" . $est_menu['foto_perfil'];
}
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    if (localStorage.getItem('epale_theme') === 'dark') {
        document.documentElement.setAttribute('data-theme', 'dark');
    }
</script>

<header class="main-header">
    <div class="logo-container">
        <a href="index" class="logo-link">
            <img src="../img/logotipo-epale.png" alt="E-PALE" class="logo-img">
        </a>
    </div>

    <div class="user-actions">
        <a href="perfil" class="profile-btn" title="Mi Perfil">
            <img src="<?php echo $foto_menu; ?>" class="profile-avatar-img">
            <span class="profile-name"><?php echo htmlspecialchars(strtok($est_menu['nombre'], " ")); ?></span>
        </a>

        <!-- Botón Dark Mode -->
        <button class="theme-toggle-btn" onclick="toggleDarkMode()" title="Cambiar Tema" aria-label="Cambiar Tema">
            <i id="theme-icon" class="fas fa-sun theme-icon-container" style="color: #ffc107;"></i>
        </button>

        <!-- Centro de Notificaciones (Campanita) -->
        <?php include_once __DIR__ . '/centro_notificaciones.php'; ?>

        <!-- Botón Menú Hamburguesa -->
        <button class="menu-toggle-btn" onclick="toggleMobileMenu()" title="Menú Principal" aria-label="Abrir Menú">
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
        <li><a href="index" class="<?php echo ($pagina_actual == 'index.php') ? 'active' : ''; ?>"><i class="fas fa-home"></i> Inicio</a></li>

        <li><a href="oferta_academica" class="<?php echo ($pagina_actual == 'oferta_academica.php') ? 'active' : ''; ?>"><i class="fas fa-book-reader"></i> Oferta Académica</a></li>

        <li><a href="calificaciones" class="<?php echo ($pagina_actual == 'calificaciones.php') ? 'active' : ''; ?>"><i class="fas fa-clipboard-check"></i> Calificaciones</a></li>

        <li><a href="horario" class="<?php echo ($pagina_actual == 'horario.php' || $pagina_actual == 'detalle_materia.php') ? 'active' : ''; ?>"><i class="fas fa-calendar-alt"></i> Horario</a></li>

        <li><a href="mis_dictamenes" class="<?php echo ($pagina_actual == 'mis_dictamenes.php') ? 'active' : ''; ?>"><i class="fas fa-file-signature"></i> Mis Dictámenes</a></li>

    </ul>

    <div class="sidebar-divider"></div>

    <ul class="yt-sidebar-menu">
        <li><a href="perfil" class="<?php echo ($pagina_actual == 'perfil.php') ? 'active' : ''; ?>"><i class="far fa-user-circle"></i> Mi Perfil</a></li>
        <li><a href="#" onclick="confirmarSalida(event)" style="color: #ff6b6b;"><i class="fas fa-sign-out-alt" style="color: #ff6b6b;"></i> Cerrar Sesión</a></li>
    </ul>
</aside>

<script>
    function toggleMobileMenu() {
        const sidebar = document.getElementById('navWrapper');
        const overlay = document.getElementById('menuOverlay');
        if (!sidebar || !overlay) return;

        const isOpen = sidebar.classList.toggle('active');
        overlay.classList.toggle('active', isOpen);

        if (isOpen) {
            document.body.classList.add('menu-open');
        } else {
            document.body.classList.remove('menu-open');
        }
    }

    function confirmarSalida(event) {
        event.preventDefault();

        // Cierra el menú lateral primero si está en móvil y libera el scroll
        const sidebar = document.getElementById('navWrapper');
        const overlay = document.getElementById('menuOverlay');
        if (sidebar) sidebar.classList.remove('active');
        if (overlay) overlay.classList.remove('active');
        document.body.classList.remove('menu-open');

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
                window.location.href = '../logout.php?csrf_token=<?php echo $_SESSION["csrf_token"] ?? ""; ?>';
            }
        });
    }
</script>

<!-- EL CEREBRO GLOBAL DEL MODO OSCURO -->
<script>
    // 1. Evitar el parpadeo blanco al recargar la página (Se ejecuta inmediatamente)
    if (localStorage.getItem('epale_theme') === 'dark') {
        document.documentElement.setAttribute('data-theme', 'dark');
    }

    // 2. Función global para cambiar el tema en cualquier página
    function toggleDarkMode() {
        const root = document.documentElement;
        const icon = document.getElementById('theme-icon');
        const isDark = root.getAttribute('data-theme') === 'dark';

        icon.classList.add('spin-out');

        setTimeout(() => {
            if (isDark) {
                root.removeAttribute('data-theme');
                localStorage.setItem('epale_theme', 'light');
                icon.className = 'fas fa-sun theme-icon-container';
                icon.style.color = '#ffc107';
            } else {
                root.setAttribute('data-theme', 'dark');
                localStorage.setItem('epale_theme', 'dark');
                icon.className = 'fas fa-moon theme-icon-container';
                icon.style.color = '#f8fafc';
            }
            icon.classList.remove('spin-out');
            icon.classList.add('spin-in');
        }, 200);
    }

    // 3. Asegurar que el icono coincida con la memoria al cambiar de pestaña
    document.addEventListener('DOMContentLoaded', () => {
        const isDark = localStorage.getItem('epale_theme') === 'dark';
        const icon = document.getElementById('theme-icon');
        if (icon) {
            if (isDark) {
                icon.className = 'fas fa-moon theme-icon-container';
                icon.style.color = '#f8fafc';
            } else {
                icon.className = 'fas fa-sun theme-icon-container';
                icon.style.color = '#ffc107';
            }
        }
    });
</script>