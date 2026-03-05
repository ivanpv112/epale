<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ALUMNO') {
    header("Location: ../index.php"); exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Oferta Académica | E-Pale</title>
    <link rel="stylesheet" href="../css/estudiante.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <header class="main-header" style="display: flex; justify-content: space-between; align-items: center; padding: 10px 20px; height: 65px;">
        
        <div class="logo-container" style="display: flex; align-items: center; width: auto; margin: 0;">
            <a href="dashboard.php" style="display: flex; align-items: center; gap: 10px; text-decoration: none; color: white;">
                <img src="../img/logo-pale.png" alt="E-PALE" class="logo-img">
                <span style="font-size: 1.2rem; font-weight: bold;">e-PALE</span>
            </a>
        </div>

        <div style="display: flex; align-items: center; gap: 15px;">
            <a href="perfil.php" style="text-decoration: none; color: white; display: flex; align-items: center; gap: 8px; background: rgba(255,255,255,0.1); padding: 5px 15px 5px 5px; border-radius: 20px;">
                <?php if(isset($user['foto_perfil']) && $user['foto_perfil']): ?>
                    <img src="../img/perfiles/<?php echo $user['foto_perfil']; ?>" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover; border: 2px solid white;">
                <?php else: ?>
                    <i class="fas fa-user-circle" style="font-size: 1.8rem;"></i>
                <?php endif; ?>
                <span class="profile-name" style="font-weight: 500;"><?php echo strtok($_SESSION['nombre'], " "); ?></span>
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
            <li><a href="dashboard.php" class="active"><i class="fas fa-home"></i> Inicio</a></li>
            <li><a href="#"><i class="far fa-calendar-alt"></i> Horario</a></li>
            <li><a href="calificaciones.php"><i class="fas fa-star"></i> Calificaciones</a></li>
            <li><a href="oferta.php"><i class="fas fa-bullhorn"></i> Oferta</a></li>
        </ul>

        <div class="sidebar-divider"></div>

        <ul class="yt-sidebar-menu">
            <li><a href="perfil.php"><i class="far fa-user-circle"></i> Mi Perfil</a></li>
            <li><a href="../logout.php" style="color: #ff6b6b;"><i class="fas fa-sign-out-alt" style="color: #ff6b6b;"></i> Cerrar Sesión</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <div class="page-title-center">
            <h1><i class="fas fa-globe"></i> Oferta Académica</h1>
            <p>Semestre 2026-A</p>
        </div>

        <div class="search-filter-bar">
            <div class="search-input-wrapper">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Buscar por idioma, nivel o profesor...">
            </div>
            <select class="filter-select">
                <option value="">Todos los idiomas</option>
                <option value="ingles">Inglés</option>
                <option value="frances">Francés</option>
                <option value="aleman">Alemán</option>
                <option value="italiano">Italiano</option>
            </select>
        </div>

        <div class="oferta-grid">
            
            <div class="grupo-card">
                <div class="grupo-header">
                    <div>
                        <h3>Inglés I</h3>
                        <span class="grupo-id">Grupo D01 (NRC: 60495)</span>
                    </div>
                    <span class="cupo-badge pocos">5 lugares</span>
                </div>
                <div class="grupo-details">
                    <p><i class="fas fa-chalkboard-teacher"></i> Prof. Juan Pérez</p>
                    <p><i class="far fa-clock"></i> Lun - Mié · 08:00 - 10:00</p>
                    <p><i class="fas fa-map-marker-alt"></i> AULA 101</p>
                </div>
                <div class="inscritos-container">
                    <div class="inscritos-text"><span>Inscritos</span><span>30/35</span></div>
                    <div class="progress-oferta"><div class="fill" style="width: 85%;"></div></div>
                </div>
            </div>

            <div class="grupo-card full">
                <div class="grupo-header">
                    <div>
                        <h3>Inglés I</h3>
                        <span class="grupo-id">Grupo D02 (NRC: 60496)</span>
                    </div>
                    <span class="cupo-badge lleno">Lleno</span>
                </div>
                <div class="grupo-details">
                    <p><i class="fas fa-chalkboard-teacher"></i> Prof. Laura Martínez</p>
                    <p><i class="far fa-clock"></i> Mar - Jue · 10:00 - 12:00</p>
                    <p><i class="fas fa-map-marker-alt"></i> AULA 102</p>
                </div>
                <div class="inscritos-container">
                    <div class="inscritos-text"><span>Inscritos</span><span>35/35</span></div>
                    <div class="progress-oferta"><div class="fill" style="width: 100%;"></div></div>
                </div>
            </div>

            <div class="grupo-card">
                <div class="grupo-header">
                    <div>
                        <h3>Francés II</h3>
                        <span class="grupo-id">Grupo D01 (NRC: 60501)</span>
                    </div>
                    <span class="cupo-badge disponible">12 lugares</span>
                </div>
                <div class="grupo-details">
                    <p><i class="fas fa-chalkboard-teacher"></i> Prof. Laura Martínez</p>
                    <p><i class="far fa-clock"></i> Mar - Jue · 14:00 - 16:00</p>
                    <p><i class="fas fa-map-marker-alt"></i> AULA 203</p>
                </div>
                <div class="inscritos-container">
                    <div class="inscritos-text"><span>Inscritos</span><span>18/30</span></div>
                    <div class="progress-oferta"><div class="fill" style="width: 60%;"></div></div>
                </div>
            </div>

        </div>
    </main>

    <script>
        function toggleMobileMenu() {
            document.getElementById('navWrapper').classList.toggle('active');
            document.getElementById('menuOverlay').classList.toggle('active');
        }
    </script>

    <footer class="main-footer">
        <div class="address-bar" style="border:none; padding-top:0;">Copyright © 2026 E-PALE</div>
    </footer>
</body>
</html>
