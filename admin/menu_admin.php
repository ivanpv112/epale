<?php
// ==========================================
// MOTOR DE "VOLVER INTELIGENTE"
// ==========================================
if (!isset($_SESSION['smart_back'])) {
    $_SESSION['smart_back'] = [];
}
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$pagina_actual = basename($_SERVER['PHP_SELF']);

if (!empty($referer)) {
    $referer_path = basename(parse_url($referer, PHP_URL_PATH));
    // Scripts silenciosos que no deben borrar la memoria del botón volver
    $scripts_excluidos = ['guardar_usuario.php', 'guardar_grupo.php', 'guardar_materia.php', 'login.php', 'logout.php'];
    
    // Si venimos de una página diferente, la memorizamos para esta sección
    if ($referer_path !== '' && $referer_path !== $pagina_actual && !in_array($referer_path, $scripts_excluidos)) {
        $_SESSION['smart_back'][$pagina_actual] = $referer;
    }
}
// Creamos la variable mágica $url_volver para usarla en todos nuestros botones
$url_volver = $_SESSION['smart_back'][$pagina_actual] ?? 'usuarios.php';
// ==========================================


// Obtenemos la foto del admin para la barra superior
$stmt_foto_menu = $pdo->prepare("SELECT foto_perfil, nombre FROM usuarios WHERE usuario_id = ?");
$stmt_foto_menu->execute([$_SESSION['user_id']]);
$admin_menu = $stmt_foto_menu->fetch(PDO::FETCH_ASSOC);

$foto_menu = "../img/avatar-default.png"; 
if($admin_menu['foto_perfil'] && file_exists("../img/perfiles/" . $admin_menu['foto_perfil'])) {
    $foto_menu = "../img/perfiles/" . $admin_menu['foto_perfil'];
}

// ==========================================
// NUEVA LÓGICA: CONTADOR DE SOLICITUDES DE BAJA
// ==========================================
$stmt_notif = $pdo->query("SELECT COUNT(*) FROM solicitudes_bajas WHERE estatus = 'PENDIENTE'");
$notif_bajas = $stmt_notif->fetchColumn();
// Creamos el circulito rojo (burbuja de notificación) solo si hay solicitudes pendientes
$badge_html = ($notif_bajas > 0) ? ' <span style="background:#dc3545; color:white; border-radius:10px; padding:2px 8px; font-size:0.7rem; margin-left:5px; font-weight:bold;">'.$notif_bajas.'</span>' : '';
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<header class="main-header" style="display: flex; justify-content: space-between; align-items: center; padding: 10px 20px; height: 65px;">
    <div class="logo-container" style="display: flex; align-items: center; width: auto; margin: 0;">
        <a href="usuarios.php" style="display: flex; align-items: center; gap: 10px; text-decoration: none; color: white;">
            <img src="../img/logo-pale.png" alt="E-PALE" class="logo-img">
            <span style="font-size: 1.2rem; font-weight: bold;">e-PALE</span>
        </a>
    </div>

    <div style="display: flex; align-items: center; gap: 15px;">
        <a href="perfil.php" style="text-decoration: none; color: white; display: flex; align-items: center; gap: 8px; background: rgba(255,255,255,0.1); padding: 5px 15px 5px 5px; border-radius: 20px;">
            <img src="<?php echo $foto_menu; ?>" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover; border: 2px solid white; background:white;">
            <span class="profile-name" style="font-weight: 500;"><?php echo strtok($admin_menu['nombre'], " "); ?></span>
        </a>
        <button onclick="toggleMobileMenu()" style="background: transparent; border: none; color: white; font-size: 1.8rem; cursor: pointer; padding: 0;">
            <i class="fas fa-bars"></i>
        </button>
    </div>
</header>

<div class="menu-overlay" id="menuOverlay" onclick="toggleMobileMenu()"></div>

<aside class="yt-sidebar" id="navWrapper">
    <div class="yt-sidebar-header">
        <span style="color: white; font-size: 1.1rem; font-weight: bold;">Panel Admin</span>
        <button onclick="toggleMobileMenu()" style="background: transparent; border: none; color: #aaa; font-size: 2rem; cursor: pointer; line-height: 1;">&times;</button>
    </div>

    <ul class="yt-sidebar-menu">
        <li><a href="usuarios.php" class="<?php echo ($pagina_actual == 'usuarios.php') ? 'active' : ''; ?>"><i class="fas fa-users"></i> Gestión de Usuarios</a></li>
        
        <li><a href="expedientes.php" class="<?php echo ($pagina_actual == 'expedientes.php' || $pagina_actual == 'ver_expediente.php') ? 'active' : ''; ?>"><i class="fas fa-folder-open"></i> Expedientes</a></li>
        
        <li><a href="materias.php" class="<?php echo ($pagina_actual == 'materias.php' || $pagina_actual == 'criterios_materia.php') ? 'active' : ''; ?>"><i class="fas fa-book"></i> Materias y Criterios</a></li>
        
        <li><a href="grupos_nrc.php" class="<?php echo ($pagina_actual == 'grupos_nrc.php' || $pagina_actual == 'gestionar_grupo.php') ? 'active' : ''; ?>"><i class="fas fa-chalkboard"></i> Grupos y NRC</a></li>
        
        <li><a href="ciclos.php" class="<?php echo ($pagina_actual == 'ciclos.php') ? 'active' : ''; ?>"><i class="fas fa-calendar-alt"></i> Ciclos Escolares</a></li>
        
        <li><a href="importar_csv.php" class="<?php echo ($pagina_actual == 'importar_csv.php') ? 'active' : ''; ?>"><i class="fas fa-file-upload"></i> Carga Masiva</a></li>
        
        <li><a href="solicitudes.php" class="<?php echo ($pagina_actual == 'solicitudes.php') ? 'active' : ''; ?>"><i class="fas fa-envelope-open-text"></i> Solicitudes <?php echo $badge_html; ?></a></li>

        <li><a href="reportes.php" class="<?php echo ($pagina_actual == 'reportes.php') ? 'active' : ''; ?>"><i class="fas fa-chart-line"></i> Reportes Generales</a></li>
    </ul>

    <div class="sidebar-divider"></div>

    <ul class="yt-sidebar-menu">
        <li><a href="perfil.php" class="<?php echo ($pagina_actual == 'perfil.php') ? 'active' : ''; ?>"><i class="far fa-user-circle"></i> Mi Perfil</a></li>
        <li><a href="#" onclick="confirmarSalida(event)" style="color: #ff6b6b;"><i class="fas fa-sign-out-alt" style="color: #ff6b6b;"></i> Cerrar Sesión</a></li>
    </ul>
</aside>

<script>
    function confirmarSalida(event) {
        event.preventDefault(); 
        document.getElementById('navWrapper').classList.remove('active');
        document.getElementById('menuOverlay').classList.remove('active');

        Swal.fire({
            title: '¿Cerrar Sesión?',
            text: "Saldrás de tu cuenta de Administrador.",
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
