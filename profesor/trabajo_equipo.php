<?php
session_start();
require '../db.php';
require_once '../security.php';

if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'PROFESOR') {
    header("Location: ../index");
    exit;
}

$profesor_id = $_SESSION['user_id'];
$clave_grupo = $_GET['clave'] ?? '';

if (!$clave_grupo) {
    header("Location: mis_grupos");
    exit;
}

// Obtener info del grupo
$stmt_info = $pdo->prepare("SELECT m.clave AS clave_materia, m.nombre AS materia, m.nivel FROM grupos g JOIN materias m ON g.materia_id = m.materia_id WHERE g.clave_grupo = ? AND g.profesor_id = ? LIMIT 1");
$stmt_info->execute([$clave_grupo, $profesor_id]);
$info_grupo = $stmt_info->fetch(PDO::FETCH_ASSOC);

if (!$info_grupo) {
    header("Location: mis_grupos");
    exit;
}

// Obtener alumnos
$sql_alum = "SELECT i.inscripcion_id, u.codigo, u.nombre, u.apellido_paterno, u.apellido_materno, u.foto_perfil 
             FROM inscripciones i 
             JOIN alumnos a ON i.alumno_id = a.alumno_id 
             JOIN usuarios u ON a.usuario_id = u.usuario_id 
             WHERE i.nrc IN (SELECT nrc FROM grupos WHERE clave_grupo = ?) AND i.estatus = 'INSCRITO' 
             ORDER BY u.apellido_paterno ASC";
$stmt_alum = $pdo->prepare($sql_alum);
$stmt_alum->execute([$clave_grupo]);
$alumnos = $stmt_alum->fetchAll(PDO::FETCH_ASSOC);
$total_alumnos = count($alumnos);

$alumnos_json = json_encode($alumnos);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trabajo en Equipo</title>
    <link rel="stylesheet" href="../css/estilos.css">
    <link rel="stylesheet" href="../css/profesor.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <?php include 'menu_profesor.php'; ?>

    <main class="main-content">
        <div class="equipos-wrapper">
            <div class="topbar-equipos">
                <div>
                    <h1 class="title-asistencia" style="margin: 0;"><?php echo htmlspecialchars($info_grupo['clave_materia'] . ' - ' . $info_grupo['materia'] . ' ' . $info_grupo['nivel'] . ' - Trabajo en Equipo'); ?></h1>
                </div>
                <div class="stepper">
                    <div class="step active" id="st1"><span>1</span> Configurar</div>
                    <div class="step" id="st2"><span>2</span> Asignar</div>
                    <div class="step" id="st3"><span>3</span> Revelar</div>
                </div>
            </div>

            <!-- PANTALLA 1: CONFIGURAR -->
            <div id="pantalla1" class="pantalla active">
                <div class="icon-lg"><i class="fas fa-user-friends"></i></div>
                <h1 class="step-title">¿Cuántos integrantes por equipo?</h1>
                <p class="step-subtitle">Hay <strong><?php echo $total_alumnos; ?></strong> estudiantes en este grupo.</p>

                <div class="counter-box">
                    <button class="btn-circle" onclick="cambiarIntegrantes(-1)"><i class="fas fa-minus"></i></button>
                    <div>
                        <div class="counter-val" id="valIntegrantes">4</div>
                        <div class="counter-label">Integrantes</div>
                    </div>
                    <button class="btn-circle" onclick="cambiarIntegrantes(1)"><i class="fas fa-plus"></i></button>
                </div>

                <div class="summary-box">
                    <div class="summary-item">
                        <div class="summary-num" id="valEquiposTotales">0</div>
                        <div class="summary-text">equipos en total</div>
                    </div>
                    <div class="summary-item" style="border-left: 1px solid rgba(255,255,255,0.1); padding-left: 40px;">
                        <div class="summary-num" id="valPorEquipo">4</div>
                        <div class="summary-text">por equipo</div>
                    </div>
                    <div class="alert-impar" id="alertaImpar">
                        <i class="fas fa-bolt"></i> <span id="txtAlertaImpar"></span>
                    </div>
                </div>

                <button class="btn-continuar" onclick="irPantalla2()">Continuar <i class="fas fa-chevron-right"></i></button>
            </div>

            <!-- PANTALLA 2: ASIGNAR -->
            <div id="pantalla2" class="pantalla">
                <div style="width: 100%; margin-bottom: 20px;">
                    <h1 class="step-title">Selecciona los integrantes</h1>
                    <p class="step-subtitle" id="txtAsignados">0 de <?php echo $total_alumnos; ?> asignados — <?php echo $total_alumnos; ?> sin asignar</p>
                </div>

                <div class="asignar-container">
                    <!-- Lista Izquierda -->
                    <div class="col-izq">
                        <div class="col-header">
                            <span class="col-title">SIN ASIGNAR (<span id="countSinAsignar"><?php echo $total_alumnos; ?></span>)</span>
                            <button class="btn-azar" onclick="asignarAlAzar()"><i class="fas fa-random"></i> Asignar al azar</button>
                        </div>
                        <div class="lista-scroll" id="listaSinAsignar">
                            <!-- Se llena con JS -->
                        </div>
                    </div>

                    <!-- Lista Derecha -->
                    <div class="col-der">
                        <div class="col-header">
                            <span class="col-title">EQUIPOS (<span id="countEquipos">0</span>)</span>
                        </div>
                        <div class="lista-scroll" id="listaEquipos">
                            <!-- Se llena con JS -->
                        </div>
                    </div>
                </div>

                <button class="btn-continuar" id="btnRevelar" onclick="guardarYRevelar()" disabled>Asigna todos para continuar</button>
            </div>

            <!-- PANTALLA 3: REVELAR -->
            <div id="pantalla3" class="pantalla">
                <div class="badge-listos"><i class="fas fa-trophy"></i> EQUIPOS FORMADOS</div>
                <h1 class="step-title">¡<span id="txtTotalEquiposFormados"></span> equipos listos!</h1>
                <p class="step-subtitle"><?php echo $total_alumnos; ?> estudiantes asignados</p>

                <div class="revelar-grid" id="gridEquiposRevelados">
                    <!-- Se llena con JS -->
                </div>
                
                <button class="btn-azar" style="margin-top: 40px; padding: 10px 25px;" onclick="resetTeams();"><i class="fas fa-random"></i> Reorganizar nuevos equipos</button>
            </div>
        </div>
    </main>

    <?php include '../main_footer.php'; ?>


<script>
const ALUMNOS = <?php echo $alumnos_json; ?>;
const TOTAL_ALUMNOS = <?php echo $total_alumnos; ?>;
const CLAVE_GRUPO = "<?php echo htmlspecialchars($clave_grupo); ?>";

// Paleta de colores neón
const PALETA = ['#3b5bdb', '#0ca678', '#845ef7', '#f59f00', '#f03e3e', '#1098ad', '#d6336c', '#74b816', '#ae3ec9'];

let integrantesPorEquipo = 4;
let numEquipos = 0;
let estudiantesSobrantes = 0;

let equipos = []; // Array of { id, color, max, asignados: [] }
let unassigned = [...ALUMNOS];

// ================= PANTALLA 1 =================
function initP1() {
    calcularEquipos();
    // Revisar si ya hay equipos en DB para dar opción de cargar
    fetch('trabajo_equipo_api', {
        method: 'POST',
        body: JSON.stringify({ action: 'cargar_equipos', clave_grupo: CLAVE_GRUPO })
    }).then(res => res.json()).then(data => {
        if(data.success && data.equipos.length > 0) {
            // Cargar los equipos de la base de datos
            equipos = data.equipos.map((eqDb, index) => {
                return {
                    id: index + 1, // Reasignar ID visualmente
                    color: eqDb.color,
                    max: eqDb.integrantes.length,
                    asignados: eqDb.integrantes
                };
            });
            // Brincar directamente a la pantalla 3 (Revelar)
            document.getElementById('pantalla1').classList.remove('active');
            mostrarRevelar();
        }
    });
}

function cambiarIntegrantes(delta) {
    integrantesPorEquipo += delta;
    if (integrantesPorEquipo < 2) integrantesPorEquipo = 2;
    if (integrantesPorEquipo > TOTAL_ALUMNOS) integrantesPorEquipo = TOTAL_ALUMNOS;
    calcularEquipos();
}

function calcularEquipos() {
    document.getElementById('valIntegrantes').innerText = integrantesPorEquipo;
    document.getElementById('valPorEquipo').innerText = integrantesPorEquipo;
    
    if(TOTAL_ALUMNOS === 0) {
        document.getElementById('valEquiposTotales').innerText = 0;
        return;
    }

    numEquipos = Math.ceil(TOTAL_ALUMNOS / integrantesPorEquipo);
    estudiantesSobrantes = TOTAL_ALUMNOS % integrantesPorEquipo;

    document.getElementById('valEquiposTotales').innerText = numEquipos;

    const alertEl = document.getElementById('alertaImpar');
    if (estudiantesSobrantes > 0) {
        alertEl.style.display = 'block';
        document.querySelector('.summary-box').style.paddingBottom = '40px';
        document.getElementById('txtAlertaImpar').innerText = `${estudiantesSobrantes} estudiantes quedarán en un equipo de ${estudiantesSobrantes}.`;
    } else {
        alertEl.style.display = 'none';
        document.querySelector('.summary-box').style.paddingBottom = '20px';
    }
}

function irPantalla2() {
    if(TOTAL_ALUMNOS === 0) { alert('No hay alumnos'); return; }
    
    // Inicializar data
    equipos = [];
    unassigned = [...ALUMNOS];
    let colorIdx = 0;

    let alumnosRestantesTemp = TOTAL_ALUMNOS;
    for (let i = 0; i < numEquipos; i++) {
        let maxSlots = integrantesPorEquipo;
        // El último equipo podría tener menos si hay sobrantes
        if (i === numEquipos - 1 && estudiantesSobrantes > 0) {
            maxSlots = estudiantesSobrantes;
        }
        
        equipos.push({
            id: i + 1,
            color: PALETA[colorIdx % PALETA.length],
            max: maxSlots,
            asignados: []
        });
        colorIdx++;
    }

    renderP2();

    document.getElementById('pantalla1').classList.remove('active');
    document.getElementById('pantalla2').classList.add('active');
    document.getElementById('st1').classList.remove('active');
    document.getElementById('st2').classList.add('active');
}

// ================= PANTALLA 2 =================

function getIniciales(n, ap) {
    return (n.charAt(0) + (ap ? ap.charAt(0) : '')).toUpperCase();
}

function renderP2() {
    // Top text
    const numAsignados = TOTAL_ALUMNOS - unassigned.length;
    document.getElementById('txtAsignados').innerText = `${numAsignados} de ${TOTAL_ALUMNOS} asignados — ${unassigned.length} sin asignar`;
    document.getElementById('countSinAsignar').innerText = unassigned.length;
    document.getElementById('countEquipos').innerText = equipos.length;

    // Lista unassigned
    const cIzq = document.getElementById('listaSinAsignar');
    cIzq.innerHTML = '';
    unassigned.forEach((alum, index) => {
        const item = document.createElement('div');
        item.className = 'alumno-item';
        
        let colorAvatar = '#5c7cfa';
        // Determinar equipo actual para preseleccionarlo (el primer equipo con espacio)
        let equipoDestinoIdx = equipos.findIndex(eq => eq.asignados.length < eq.max);
        let colorDestino = equipoDestinoIdx !== -1 ? equipos[equipoDestinoIdx].color : '#555';

        item.innerHTML = `
            <div class="avatar-letra" style="background: rgba(255,255,255,0.1); color: ${colorDestino};">${getIniciales(alum.nombre, alum.apellido_paterno)}</div>
            <div class="alumno-nombre">${alum.nombre} ${alum.apellido_paterno}</div>
            <i class="fas fa-chevron-right icon-add"></i>
        `;
        item.onclick = () => asignarAlumno(index);
        cIzq.appendChild(item);
    });

    // Lista equipos
    const cDer = document.getElementById('listaEquipos');
    cDer.innerHTML = '';
    
    let allFull = true;

    equipos.forEach((eq, eqIdx) => {
        if(eq.asignados.length < eq.max) allFull = false;

        const caja = document.createElement('div');
        caja.className = 'equipo-caja';
        caja.style.borderColor = eq.color;
        
        let slotsHtml = '';
        // Llenos
        eq.asignados.forEach((alum, aIdx) => {
            slotsHtml += `<div class="slot filled" style="background-color: ${eq.color}; border-color: ${eq.color};" onclick="desasignarAlumno(${eqIdx}, ${aIdx})" title="Quitar a ${alum.nombre}">
                ${getIniciales(alum.nombre, alum.apellido_paterno)}
            </div>`;
        });
        // Vacios
        for (let s = 0; s < (eq.max - eq.asignados.length); s++) {
            slotsHtml += `<div class="slot"></div>`;
        }

        caja.innerHTML = `
            <div class="equipo-header">
                <div class="equipo-nombre" style="background: ${eq.color}; color: #fff;">EQUIPO ${eq.id}</div>
                <div class="equipo-count">${eq.asignados.length} / ${eq.max}</div>
            </div>
            <div class="slots-container">${slotsHtml}</div>
        `;
        cDer.appendChild(caja);
    });

    const btnContinuar = document.getElementById('btnRevelar');
    if (allFull && unassigned.length === 0) {
        btnContinuar.disabled = false;
        btnContinuar.innerText = 'Revelar equipos';
        btnContinuar.innerHTML = 'Revelar equipos <i class="fas fa-check"></i>';
    } else {
        btnContinuar.disabled = true;
        btnContinuar.innerText = 'Asigna todos para continuar';
    }
}

function asignarAlumno(unassignedIndex) {
    const alumno = unassigned[unassignedIndex];
    // Find first team with space
    const targetTeam = equipos.find(eq => eq.asignados.length < eq.max);
    if (!targetTeam) return; // No space anywhere

    targetTeam.asignados.push(alumno);
    unassigned.splice(unassignedIndex, 1);
    renderP2();
}

function desasignarAlumno(equipoIndex, alumnoIndex) {
    const alumno = equipos[equipoIndex].asignados[alumnoIndex];
    equipos[equipoIndex].asignados.splice(alumnoIndex, 1);
    unassigned.push(alumno);
    // Sort unassigned again by name to keep it tidy
    unassigned.sort((a, b) => a.apellido_paterno.localeCompare(b.apellido_paterno));
    renderP2();
}

function asignarAlAzar() {
    // Shuffle remaining unassigned
    let shuffled = [...unassigned].sort(() => 0.5 - Math.random());
    unassigned = [];

    shuffled.forEach(alum => {
        const targetTeam = equipos.find(eq => eq.asignados.length < eq.max);
        if(targetTeam) {
            targetTeam.asignados.push(alum);
        } else {
            unassigned.push(alum);
        }
    });
    renderP2();
}

// ================= PANTALLA 3 =================

function guardarYRevelar() {
    const btn = document.getElementById('btnRevelar');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
    
    const payload = {
        action: 'guardar_equipos',
        clave_grupo: CLAVE_GRUPO,
        equipos: equipos.map(eq => ({
            nombre: `Equipo ${eq.id}`,
            color: eq.color,
            integrantes: eq.asignados.map(a => a.inscripcion_id)
        }))
    };

    fetch('trabajo_equipo_api', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            mostrarRevelar();
        } else {
            alert("Error al guardar: " + data.error);
            btn.innerHTML = 'Revelar equipos <i class="fas fa-check"></i>';
        }
    })
    .catch(err => {
        alert("Error de conexión");
        btn.innerHTML = 'Revelar equipos <i class="fas fa-check"></i>';
    });
}

function mostrarRevelar() {
    document.getElementById('pantalla1').classList.remove('active');
    document.getElementById('pantalla2').classList.remove('active');
    document.getElementById('pantalla3').classList.add('active');
    
    document.getElementById('st1').classList.remove('active');
    document.getElementById('st2').classList.remove('active');
    document.getElementById('st3').classList.add('active');

    document.getElementById('txtTotalEquiposFormados').innerText = equipos.length;

    const grid = document.getElementById('gridEquiposRevelados');
    grid.innerHTML = '';

    equipos.forEach(eq => {
        const tarjeta = document.createElement('div');
        tarjeta.className = 'tarjeta-equipo';
        tarjeta.style.borderColor = eq.color;

        let alHtml = '';
        eq.asignados.forEach(a => {
            alHtml += `
                <div class="revelar-alumno">
                    <div class="avatar-letra" style="background: ${eq.color}; color: #fff;">${getIniciales(a.nombre, a.apellido_paterno)}</div>
                    <div class="nombre">${a.nombre} ${a.apellido_paterno}</div>
                </div>
            `;
        });

        tarjeta.innerHTML = `
            <div class="tarjeta-header" style="background: ${eq.color}; color: #fff;">
                <span>EQUIPO ${eq.id}</span>
                <i class="fas fa-users" style="opacity: 0.5;"></i>
            </div>
            <div class="tarjeta-body">
                ${alHtml}
            </div>
            <div class="tarjeta-footer">
                ${eq.asignados.length} INTEGRANTES
            </div>
        `;
        grid.appendChild(tarjeta);
    });
}

function resetTeams() {
    Swal.fire({
        title: '¿Reorganizar equipos?',
        text: "Se borrarán los equipos actuales y tendrás que armarlos de nuevo.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-trash-alt"></i> Sí, borrar y reorganizar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
        backdrop: `rgba(0,0,123,0.4)`
    }).then((result) => {
        if (result.isConfirmed) {
            // Eliminar los equipos enviando un arreglo vacío a la API
            fetch('trabajo_equipo_api', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'guardar_equipos', clave_grupo: CLAVE_GRUPO, equipos: [] })
            }).then(() => {
                location.reload();
            });
        }
    });
}

initP1();
</script>
</body>
</html>
