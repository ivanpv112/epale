<?php
// Obtenemos la foto del admin para la barra superior
$stmt_foto_menu = $pdo->prepare("SELECT foto_perfil, nombre FROM usuarios WHERE usuario_id = ?");
$stmt_foto_menu->execute([$_SESSION['user_id']]);
$admin_menu = $stmt_foto_menu->fetch(PDO::FETCH_ASSOC);

$foto_menu = "../img/avatar-default.png"; 
if($admin_menu['foto_perfil'] && file_exists("../img/perfiles/" . $admin_menu['foto_perfil'])) {
    $foto_menu = "../img/perfiles/" . $admin_menu['foto_perfil'];
}

// Detectar en qué página estamos actualmente
$pagina_actual = basename($_SERVER['PHP_SELF']);
?>

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
        
        <li><a href="grupos_nrc.php" class="<?php echo ($pagina_actual == 'grupos_nrc.php') ? 'active' : ''; ?>"><i class="fas fa-chalkboard"></i> Grupos y NRC</a></li>
        
        <li><a href="importar_csv.php" class="<?php echo ($pagina_actual == 'importar_csv.php') ? 'active' : ''; ?>"><i class="fas fa-file-upload"></i> Carga Masiva</a></li>
        
        <li><a href="reportes.php" class="<?php echo ($pagina_actual == 'reportes.php') ? 'active' : ''; ?>"><i class="fas fa-chart-line"></i> Reportes Generales</a></li>
    </ul>

    <div class="sidebar-divider"></div>

    <ul class="yt-sidebar-menu">
        <li><a href="perfil.php" class="<?php echo ($pagina_actual == 'perfil.php') ? 'active' : ''; ?>"><i class="far fa-user-circle"></i> Mi Perfil</a></li>
        <li><a href="../logout.php" style="color: #ff6b6b;"><i class="fas fa-sign-out-alt" style="color: #ff6b6b;"></i> Cerrar Sesión</a></li>
    </ul>
</aside>