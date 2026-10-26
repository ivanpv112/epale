<?php
// Importar archivo de seguridad (centraliza la generación del token)
require_once '../security.php';

// ==========================================
// MOTOR DE "VOLVER INTELIGENTE" (Versión Profesor)
// ==========================================
if (!isset($_SESSION['smart_back_profesor'])) {
    $_SESSION['smart_back_profesor'] = [];
}
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$pagina_actual = basename($_SERVER['PHP_SELF']);

if (!empty($referer)) {
    $referer_path = basename(parse_url($referer, PHP_URL_PATH));
    $scripts_excluidos = ['login.php', 'logout.php', 'procesar_perfil.php'];

    if ($referer_path !== '' && $referer_path !== $pagina_actual && !in_array($referer_path, $scripts_excluidos)) {
        $_SESSION['smart_back_profesor'][$pagina_actual] = $referer;
    }
}
$url_volver = $_SESSION['smart_back_profesor'][$pagina_actual] ?? 'index.php';
// ==========================================

// Obtenemos la foto del profesor
$stmt_foto_menu = $pdo->prepare("SELECT foto_perfil, nombre FROM usuarios WHERE usuario_id = ?");
$stmt_foto_menu->execute([$_SESSION['user_id']]);
$prof_menu = $stmt_foto_menu->fetch(PDO::FETCH_ASSOC);

$foto_menu = "../img/avatar-default.png";
if ($prof_menu['foto_perfil'] && file_exists("../img/perfiles/" . $prof_menu['foto_perfil'])) {
    $foto_menu = "../img/perfiles/" . $prof_menu['foto_perfil'];
}

// Conteo rápido de avisos y tareas vigentes del profesor para la barra superior
$menu_total_avisos = 0;
try {
    $stmt_cnt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM avisos a 
        JOIN grupos g ON CAST(a.audiencia_ref AS UNSIGNED) = g.nrc 
        JOIN ciclos c ON g.ciclo_id = c.ciclo_id 
        WHERE a.autor_id = ? AND a.tipo_audiencia = 'GRUPO' AND c.activo = 1 
          AND (a.fecha_expiracion IS NULL OR a.fecha_expiracion >= NOW())
    ");
    $stmt_cnt->execute([$_SESSION['user_id']]);
    $menu_total_avisos = (int)$stmt_cnt->fetchColumn();
} catch (Exception $e) {
    $menu_total_avisos = 0;
}
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- 1. Evitar el parpadeo blanco al recargar la página (Se ejecuta inmediatamente) -->
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
            <span class="profile-name"><?php echo htmlspecialchars(strtok($prof_menu['nombre'], " ")); ?></span>
        </a>

        <!-- Botón Dark Mode -->
        <button class="theme-toggle-btn" onclick="toggleDarkMode()" title="Cambiar Tema" aria-label="Cambiar Tema">
            <i id="theme-icon" class="fas fa-sun theme-icon-container" style="color: #ffc107;"></i>
        </button>

        <!-- Botón Mis Avisos y Tareas (Trompetita) -->
        <button type="button" class="notif-bell-btn btn-trompetita-avisos" id="btnMenuAvisosProf" onclick="abrirPanelAvisosGlobal()" title="Mis Avisos y Tareas emitidos" aria-label="Avisos y Tareas">
            <i class="fas fa-bullhorn" style="font-size: 1.05rem;"></i>
            <?php if ($menu_total_avisos > 0): ?>
                <span class="notif-badge-pill" id="badgeMenuAvisosCount"><?php echo $menu_total_avisos > 9 ? '9+' : $menu_total_avisos; ?></span>
            <?php endif; ?>
        </button>

        <!-- Centro de Notificaciones (Campanita) -->
        <?php include_once __DIR__ . '/centro_notificaciones.php'; ?>

        <button class="menu-toggle-btn" onclick="toggleMobileMenu()" title="Menú Principal" aria-label="Abrir Menú">
            <i class="fas fa-bars"></i>
        </button>
    </div>
</header>

<div class="menu-overlay" id="menuOverlay" onclick="toggleMobileMenu()"></div>

<aside class="yt-sidebar" id="navWrapper">
    <div class="yt-sidebar-header">
        <span style="color: white; font-size: 1.1rem; font-weight: bold;">Portal Docente</span>
        <button onclick="toggleMobileMenu()" style="background: transparent; border: none; color: #aaa; font-size: 2rem; cursor: pointer; line-height: 1;">&times;</button>
    </div>

    <ul class="yt-sidebar-menu">
        <li><a href="index" class="<?php echo ($pagina_actual == 'index.php') ? 'active' : ''; ?>"><i class="fas fa-home"></i> Inicio</a></li>
        <li><a href="mis_grupos" class="<?php echo ($pagina_actual == 'mis_grupos.php' || $pagina_actual == 'detalle_grupo.php' || $pagina_actual == 'asistencia.php') ? 'active' : ''; ?>"><i class="fas fa-chalkboard-teacher"></i> Mis Grupos</a></li>
        <li><a href="horario" class="<?php echo ($pagina_actual == 'horario.php') ? 'active' : ''; ?>"><i class="fas fa-calendar-alt"></i> Mi Horario</a></li>
        <li><a href="#" onclick="toggleMobileMenu(); abrirPanelAvisosGlobal();"><i class="fas fa-bullhorn"></i> Mis Avisos y Tareas</a></li>
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
        const sidebar = document.getElementById('navWrapper');
        const overlay = document.getElementById('menuOverlay');
        if (sidebar) sidebar.classList.remove('active');
        if (overlay) overlay.classList.remove('active');
        document.body.classList.remove('menu-open');

        Swal.fire({
            title: '¿Cerrar Sesión?',
            text: "Saldrás de tu cuenta.",
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
    // Función global para cambiar el tema en cualquier página
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

    // Asegurar que el icono coincida con la memoria al cambiar de pestaña
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

    // Abrir el panel de gestión de avisos desde la trompetita
    function abrirPanelAvisosGlobal() {
        if (typeof abrirPanelAvisos === 'function') {
            abrirPanelAvisos();
        } else {
            // Si el profesor está en otra sección (ej. horario o grupos), lo enviamos al inicio y abrimos el panel
            window.location.href = 'index?abrir_avisos=1';
        }
    }
</script>

<!-- MODAL FLOTANTE GLOBAL DE DETALLE DE AVISO (ESTRUCTURA Y ACOMODO EXACTO AL DEL ALUMNO) -->
<div id="modalDetalleAvisoProfesor" class="modal-overlay" style="z-index: 9999;" onclick="cerrarDetalleAvisoGlobal(event)">
    <div class="modal-card" onclick="event.stopPropagation()">
        <div class="modal-card-body">
            <!-- 1. Tag superior / categoría (Acomodo exacto al del alumno: modAdminTag) -->
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:15px;">
                <span id="detAvisoBadgeTipo" class="tag-aviso notif-sub-badge neon-sub-global" style="font-weight:bold; font-size:0.8rem; padding:4px 10px; border-radius:12px;"></span>
                <span id="detAvisoStatus" class="status-pill pill-activa" style="display:none;"></span>
            </div>

            <!-- 2. Título principal (Acomodo exacto: modAdminTitulo) -->
            <h2 id="detAvisoTitulo" style="color:var(--udg-blue); margin-top:0; margin-bottom:15px; font-size:1.4rem; padding-right: 15px; word-break: break-word;"></h2>

            <!-- 3. Descripción / cuerpo justificado (Acomodo exacto: modAdminDesc) -->
            <div id="detAvisoCuerpo" class="texto-seguro" style="color:var(--text-dark); font-size:1.05rem; line-height:1.6; margin-bottom:25px; text-align: justify; text-justify: inter-word;"></div>

            <!-- 4. Contenedor de Cartel / Imagen (Acomodo exacto: modAdminImgContainer) -->
            <div id="detAvisoImgContainer" style="display: none; text-align: center; margin-bottom: 25px;">
                <div style="position: relative; display: inline-block; cursor: pointer;" onclick="ampliarImagenAvisoProf(document.getElementById('detAvisoImg').src)">
                    <img id="detAvisoImg" src="" alt="Cartel Informativo" style="max-width: 100%; max-height: 420px; object-fit: contain; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.12); transition: transform 0.2s, opacity 0.2s;" onmouseover="this.style.opacity='0.92'" onmouseout="this.style.opacity='1'">
                    <div style="position: absolute; bottom: 10px; right: 10px; background: rgba(0,0,0,0.65); color: white; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; pointer-events: none;">
                        <i class="fas fa-search-plus"></i> Clic para ampliar
                    </div>
                </div>
            </div>

            <!-- 5. Caja de Metadatos inferior con borde azul (Acomodo exacto a la caja informativa del alumno) -->
            <div style="background:rgba(0, 26, 87, 0.05); padding:15px; border-radius:8px; border-left: 4px solid var(--udg-blue); font-size:0.9rem; color:var(--text-muted); margin-bottom: 5px;">
                <i class="fas fa-university"></i> <strong>Publicado por:</strong> <span id="detAvisoClase" style="color:var(--text-dark);"></span><br>
                <i class="far fa-calendar-alt" style="margin-top: 8px;"></i> <strong>Fecha de Publicación:</strong> <span id="detAvisoInicio" style="color:var(--text-dark);"></span><br>
                <span id="detAvisoFinRow" style="display: inline;">
                    <i class="far fa-calendar-check" style="margin-top: 8px;"></i> <strong>Fecha Límite:</strong> <span id="detAvisoFin" style="color:var(--text-dark);"></span>
                </span>
            </div>
        </div>

        <!-- 6. Footer con botón de edición condicional (solo si es aviso propio del profesor) y botón rojo neón de cierre -->
        <div id="detAvisoFooterActions" style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 15px; padding-top: 12px; border-top: 1px solid rgba(128,128,128,0.15);">
            <button type="button" id="btnDetalleEditarAviso" class="btn-detalle-edit" style="display: none;">
                <i class="fas fa-pen"></i> Editar comunicado
            </button>
            <button type="button" class="btn-neon-close" onclick="cerrarDetalleAvisoGlobal()">
                Cerrar
            </button>
        </div>
    </div>
</div>

<!-- Lightbox para ampliar imagen del aviso sin abrir nueva pestaña (Idéntico al del alumno) -->
<div id="lightboxAvisoProfesor" onclick="cerrarLightboxAvisoProf()" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.85); z-index:999999; justify-content:center; align-items:center; cursor:pointer; backdrop-filter:blur(3px);">
    <img id="lightboxImgAvisoProf" src="" alt="Cartel ampliado" style="max-width:92%; max-height:92vh; border-radius:8px; box-shadow:0 8px 30px rgba(0,0,0,0.6); object-fit:contain; border:2px solid rgba(255,255,255,0.2); cursor:default;" onclick="event.stopPropagation()">
</div>

<script>
    function ampliarImagenAvisoProf(src) {
        if (!src) return;
        const lightbox = document.getElementById('lightboxAvisoProfesor');
        const img = document.getElementById('lightboxImgAvisoProf');
        if (lightbox && img) {
            img.src = src;
            lightbox.style.display = 'flex';
        }
    }

    function cerrarLightboxAvisoProf() {
        const lightbox = document.getElementById('lightboxAvisoProfesor');
        if (lightbox) {
            lightbox.style.display = 'none';
        }
    }

    // Cerrar lightbox con la tecla Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            cerrarLightboxAvisoProf();
        }
    });
</script>