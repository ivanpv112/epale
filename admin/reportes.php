<?php
session_start();
require '../db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ADMIN') { header("Location: ../index.php"); exit; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes Generales | E-PALE Admin</title>
    
    <!-- Archivos CSS -->
    <link rel="stylesheet" href="../css/estudiante.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Librería Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    
    <?php include 'menu_admin.php'; ?>

    <main class="main-content">
        <div style="margin-bottom: 20px;">
            <h1 style="color: var(--udg-blue); margin: 0; display:flex; align-items:center; gap:10px;"><i class="fas fa-chart-bar"></i> Reportes Generales</h1>
            <p style="color: #666; margin: 5px 0 0 0;">Análisis estadístico y desempeño académico · E-PALE CUCEA</p>
        </div>

        <!-- BARRA DE FILTROS -->
        <div class="filter-bar">
            <div style="width: 100%; font-size: 0.8rem; font-weight: bold; margin-bottom: -10px;"><i class="fas fa-filter"></i> FILTROS DE ANÁLISIS</div>
            <div class="filter-group">
                <label class="filter-label">1. Ciclo Escolar</label>
                <select id="sel_ciclo" class="filter-select" onchange="cargarIdiomas()">
                    <option value="">Cargando...</option>
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">2. Idioma</label>
                <select id="sel_idioma" class="filter-select" onchange="cargarNiveles()" disabled>
                    <option value="">— Seleccionar idioma —</option>
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">3. Nivel</label>
                <select id="sel_nivel" class="filter-select" onchange="cargarDashboard()" disabled>
                    <option value="">— Seleccionar nivel —</option>
                </select>
            </div>
        </div>

        <!-- ESTADO VACÍO -->
        <div id="empty_state" class="content-card" style="text-align: center; padding: 80px 20px;">
            <div style="background: #f8f9fa; width: 60px; height: 60px; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                <i class="fas fa-filter" style="font-size: 1.8rem; color: #ccc;"></i>
            </div>
            <h3 style="color: #444; margin-bottom: 5px;">Selecciona el ciclo e idioma para comenzar</h3>
            <p style="color: #888; font-size: 0.9rem;">El dashboard se generará una vez que completes los 3 filtros</p>
            <div style="display:flex; justify-content:center; gap:10px; margin-top:20px; color:#aaa; font-weight:bold;">
                <span style="width:30px; height:30px; border-radius:50%; border:2px solid var(--udg-blue); color:var(--udg-blue); display:flex; align-items:center; justify-content:center;">1</span> <i class="fas fa-chevron-right" style="margin-top:8px; font-size:0.7rem;"></i>
                <span style="width:30px; height:30px; border-radius:50%; background:#eee; display:flex; align-items:center; justify-content:center;">2</span> <i class="fas fa-chevron-right" style="margin-top:8px; font-size:0.7rem;"></i>
                <span style="width:30px; height:30px; border-radius:50%; background:#eee; display:flex; align-items:center; justify-content:center;">3</span>
            </div>
        </div>

        <!-- CONTENEDOR DASHBOARD -->
        <div id="dashboard_container" style="display: none;">
            
            <div style="margin-bottom: 20px; display:flex; gap:10px; font-size:0.85rem; font-weight:bold; color:var(--udg-blue);">
                <span style="background:#eaf0ff; padding:5px 12px; border-radius:15px;" id="bread_ciclo"></span> <i class="fas fa-chevron-right" style="margin-top:6px; color:#ccc;"></i>
                <span style="background:#eaf0ff; padding:5px 12px; border-radius:15px;" id="bread_idioma"></span> <i class="fas fa-chevron-right" style="margin-top:6px; color:#ccc;"></i>
                <span style="background:#eaf0ff; padding:5px 12px; border-radius:15px;" id="bread_nivel"></span>
            </div>

            <!-- KPIs -->
            <div class="kpi-row">
                <div class="kpi-card">
                    <div class="kpi-title">Alumnos Inscritos <i class="fas fa-user-graduate" style="background:#f1f3f5; padding:6px; border-radius:6px; color:var(--udg-blue);"></i></div>
                    <div class="kpi-value" id="kpi_alumnos">0</div>
                    <div class="kpi-subtitle" id="kpi_sub_alumnos">0 grupos activos</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-title">Grupos / Clases <i class="fas fa-chalkboard" style="background:#f1f3f5; padding:6px; border-radius:6px; color:var(--udg-blue);"></i></div>
                    <div class="kpi-value" id="kpi_grupos" style="color: #6f42c1;">0</div>
                    <div class="kpi-subtitle" id="kpi_sub_grupos">~0 alumnos/grupo</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-title">Promedio General <i class="fas fa-chart-line" style="background:#fff8e5; padding:6px; border-radius:6px; color:#ffc107;"></i></div>
                    <div class="kpi-value" id="kpi_promedio" style="color: #ffc107;">0.0</div>
                    <div class="kpi-subtitle">Calificación media del nivel</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-title">Tasa de Aprobación <i class="fas fa-medal" style="background:#e8f8f5; padding:6px; border-radius:6px; color:#00d27a;"></i></div>
                    <div class="kpi-value" id="kpi_tasa" style="color: #00d27a;">0%</div>
                    <div class="progress-track"><div class="progress-fill" id="kpi_bar"></div></div>
                </div>
            </div>

            <div class="chart-grid">
                <div class="chart-card">
                    <h3 class="chart-title">Distribución por Género</h3>
                    <div style="height: 250px; position:relative; margin-top:20px;"><canvas id="chartGenero"></canvas></div>
                </div>
                <div class="chart-card">
                    <h3 class="chart-title">Distribución de Calificaciones</h3>
                    <div style="height: 250px; position:relative; margin-top:20px;"><canvas id="chartCalif"></canvas></div>
                </div>
            </div>

            <div class="chart-grid">
                <div class="chart-card">
                    <h3 class="chart-title" id="titulo_radar">Promedio por Nivel</h3>
                    <p style="font-size:0.8rem; color:#888; margin-top:0;">Todos los niveles del idioma en este ciclo</p>
                    <div style="height: 250px; position:relative; margin-top:10px;"><canvas id="chartRadar"></canvas></div>
                </div>
                <div class="chart-card">
                    <h3 class="chart-title">Resultados Académicos</h3>
                    <div style="display:flex; justify-content:space-between; margin-top:25px; font-size:0.9rem; color:#555;"><span>Aprobados</span> <span id="txt_aprob">0%</span></div>
                    <div class="progress-track" style="margin-top:5px; margin-bottom:15px;"><div class="progress-fill" id="bar_aprob"></div></div>
                    
                    <div style="display:flex; justify-content:space-between; font-size:0.9rem; color:#555;"><span>Reprobados</span> <span id="txt_rep">0%</span></div>
                    <div class="progress-track" style="margin-top:5px; margin-bottom:30px;"><div class="progress-fill" id="bar_rep" style="background:#fb4d5e;"></div></div>

                    <div style="display:flex; gap:20px;">
                        <div class="result-box result-ap"><i class="far fa-check-circle" style="font-size:1.5rem;"></i><div style="font-size:2rem; font-weight:bold; margin:5px 0;" id="box_aprob">0</div><div style="font-size:0.85rem;">Aprobados</div></div>
                        <div class="result-box result-rep"><i class="far fa-times-circle" style="font-size:1.5rem;"></i><div style="font-size:2rem; font-weight:bold; margin:5px 0;" id="box_rep">0</div><div style="font-size:0.85rem;">Reprobados</div></div>
                    </div>
                </div>
            </div>

            <!-- COMPARATIVO HISTÓRICO -->
            <div class="chart-card" style="margin-bottom: 25px;">
                <h3 class="chart-title">Comparativo por Ciclo</h3>
                <p style="font-size:0.8rem; color:#888; margin-top:0;" id="subtitle_historico">Histórico de matrículas y promedios</p>
                <div style="height: 280px; position:relative; margin-top:15px;"><canvas id="chartHistorico"></canvas></div>
            </div>

            <!-- TABLA DE DESGLOSE -->
            <div class="chart-card" style="margin-bottom: 40px;">
                <h3 class="chart-title">Desglose por Grupos</h3>
                <p style="font-size:0.8rem; color:#888; margin-top:0;" id="subtitle_tabla"></p>
                <div style="overflow-x:auto; margin-top:15px;">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>GRUPO</th>
                                <th>PROFESOR</th>
                                <th style="text-align:center;">ALUMNOS</th>
                                <th style="text-align:center;">PROMEDIO</th>
                                <th>RENDIMIENTO</th>
                            </tr>
                        </thead>
                        <tbody id="tablaGruposBody"></tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <script src="../js/reportes_admin.js?v=<?php echo time(); ?>"></script>
</body>
</html>