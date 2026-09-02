let charts = { genero: null, calif: null, radar: null, historico: null };

document.addEventListener('DOMContentLoaded', cargarCiclos);

function cargarCiclos() {
    fetch('reportes_api.php', { 
        method: 'POST', 
        headers: {'Content-Type': 'application/x-www-form-urlencoded'}, 
        body: `action=get_ciclos&csrf_token=${encodeURIComponent(csrfToken)}` 
    })
    .then(res => res.json())
    .then(data => {
        let sel = document.getElementById('sel_ciclo');
        sel.innerHTML = '<option value="">— Seleccionar ciclo —</option>';
        data.forEach(c => sel.innerHTML += `<option value="${c.ciclo_id}">${c.nombre}</option>`);
    });
}

function cargarIdiomas() {
    let ciclo_id = document.getElementById('sel_ciclo').value;
    let selId = document.getElementById('sel_idioma');
    let selNiv = document.getElementById('sel_nivel');
    
    selNiv.innerHTML = '<option value="">— Seleccionar nivel —</option>'; selNiv.disabled = true;
    
    if(!ciclo_id) { selId.innerHTML = '<option value="">— Seleccionar idioma —</option>'; selId.disabled = true; ocultarDashboard(); return; }

    fetch('reportes_api.php', { 
        method: 'POST', 
        headers: {'Content-Type': 'application/x-www-form-urlencoded'}, 
        body: `action=get_idiomas&ciclo_id=${ciclo_id}&csrf_token=${encodeURIComponent(csrfToken)}` 
    })
    .then(res => res.json())
    .then(data => {
        selId.disabled = false; selId.innerHTML = '<option value="">— Seleccionar idioma —</option>';
        // Filtro 2 Muestra Idiomas
        data.forEach(i => {
            selId.innerHTML += `<option value="${i.nombre}">${i.nombre}</option>`;
        });
        ocultarDashboard();
    });
}

function cargarNiveles() {
    let ciclo_id = document.getElementById('sel_ciclo').value;
    let idioma = document.getElementById('sel_idioma').value;
    let selNiv = document.getElementById('sel_nivel');

    if(!idioma) { selNiv.innerHTML = '<option value="">— Seleccionar nivel —</option>'; selNiv.disabled = true; ocultarDashboard(); return; }

    fetch('reportes_api.php', { 
        method: 'POST', 
        headers: {'Content-Type': 'application/x-www-form-urlencoded'}, 
        body: `action=get_niveles&ciclo_id=${ciclo_id}&idioma=${encodeURIComponent(idioma)}&csrf_token=${encodeURIComponent(csrfToken)}` 
    })
    .then(res => res.json())
    .then(data => {
        selNiv.disabled = false; selNiv.innerHTML = '<option value="">— Seleccionar nivel —</option>';
        // Filtro 3 Muestra Nivel
        data.forEach(n => selNiv.innerHTML += `<option value="${n.materia_id}">Nivel ${n.nivel} (${n.clave})</option>`);
        ocultarDashboard();
    });
}

function cargarDashboard() {
    let ciclo = document.getElementById('sel_ciclo').value;
    let idioma = document.getElementById('sel_idioma').value; 
    let materia_id = document.getElementById('sel_nivel').value; 

    if(!ciclo || !idioma || !materia_id) { ocultarDashboard(); return; }

    let textoCiclo = document.getElementById('sel_ciclo').options[document.getElementById('sel_ciclo').selectedIndex].text;
    let textoNivelCompleto = document.getElementById('sel_nivel').options[document.getElementById('sel_nivel').selectedIndex].text;

    fetch('reportes_api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=get_stats&ciclo_id=${ciclo}&materia_id=${materia_id}&idioma=${encodeURIComponent(idioma)}&csrf_token=${encodeURIComponent(csrfToken)}`
    })
    .then(res => res.json())
    .then(data => {
        document.getElementById('empty_state').style.display = 'none';
        document.getElementById('dashboard_container').style.display = 'block';

        // Breadcrumbs y KPIs
        document.getElementById('bread_ciclo').innerText = textoCiclo;
        document.getElementById('bread_idioma').innerText = idioma;
        document.getElementById('bread_nivel').innerText = textoNivelCompleto;

        document.getElementById('kpi_alumnos').innerText = data.total_alumnos;
        document.getElementById('kpi_sub_alumnos').innerText = data.total_grupos + ' grupos activos';
        
        document.getElementById('kpi_grupos').innerText = data.total_grupos;
        let pGrupos = data.total_grupos > 0 ? Math.round(data.total_alumnos / data.total_grupos) : 0;
        document.getElementById('kpi_sub_grupos').innerText = '~' + pGrupos + ' alumnos/grupo';

        document.getElementById('kpi_promedio').innerText = data.desempeno;
        document.getElementById('kpi_tasa').innerText = data.tasa_aprobacion + '%';
        document.getElementById('kpi_bar').style.width = data.tasa_aprobacion + '%';

        document.getElementById('box_aprob').innerText = data.aprobados;
        document.getElementById('box_rep').innerText = data.reprobados;
        document.getElementById('txt_aprob').innerText = data.tasa_aprobacion + '%';
        let tasaRep = data.total_alumnos > 0 ? (100 - data.tasa_aprobacion) : 0;
        document.getElementById('txt_rep').innerText = tasaRep + '%';
        document.getElementById('bar_aprob').style.width = data.tasa_aprobacion + '%';
        document.getElementById('bar_rep').style.width = tasaRep + '%';

        // Tabla Grupos
        document.getElementById('subtitle_tabla').innerText = `${data.total_grupos} grupos | ${idioma} ${textoNivelCompleto} - ${textoCiclo}`;
        let tb = document.getElementById('tablaGruposBody'); tb.innerHTML = '';
        data.tabla_grupos.forEach(g => {
            let colorPerf = g.promedio >= 90 ? '#00d27a' : (g.promedio >= 80 ? '#ffc107' : '#fb4d5e');
            
            tb.innerHTML += `<tr>
                <td style="font-weight:bold; color:var(--udg-blue);">${g.nrc_label}</td>
                <td>Prof. ${g.nombre} ${g.apellido_paterno}</td>
                <td style="text-align:center; font-weight:bold;">${g.cant_alumnos}</td>
                <td style="text-align:center; font-weight:bold; color:${colorPerf};">${g.promedio}</td>
                <td style="width: 150px;">
                    <div class="progress-track" style="margin-top:0; height:8px; background:#eee;">
                        <div class="progress-fill" style="width:${g.promedio}%; background:${colorPerf};"></div>
                    </div>
                </td>
            </tr>`;
        });

        // Títulos de gráficas
        document.getElementById('titulo_radar').innerText = `Promedio por Nivel — ${idioma}`;
        document.getElementById('subtitle_historico').innerText = `${idioma} ${textoNivelCompleto} · Histórico de matrículas y promedios`;

        // Renderizado de Gráficas
        pintarGenero(data.hombres, data.mujeres, data.otros, data.total_alumnos);
        pintarCalificaciones(data.distribucion);
        pintarRadar(data.radar.labels, data.radar.data);
        pintarHistorico(data.historico.labels, data.historico.alumnos, data.historico.promedios);
    });
}

function ocultarDashboard() {
    document.getElementById('empty_state').style.display = 'block';
    document.getElementById('dashboard_container').style.display = 'none';
}

function pintarGenero(h, m, o, total) {
    if(charts.genero) charts.genero.destroy();
    let pH = total>0 ? Math.round((h/total)*100) : 0;
    let pM = total>0 ? Math.round((m/total)*100) : 0;
    let pO = total>0 ? Math.round((o/total)*100) : 0;

    charts.genero = new Chart(document.getElementById('chartGenero'), {
        type: 'doughnut',
        data: {
            labels: [`Hombres: ${h} (${pH}%)`, `Mujeres: ${m} (${pM}%)`, `Otros: ${o} (${pO}%)`],
            datasets: [{ data: [h, m, o], backgroundColor: ['#007bff', '#fd3995', '#8a2be2'], borderWidth: 3 }]
        },
        options: { responsive: true, maintainAspectRatio: false, cutout: '70%', plugins: { legend: { position: 'right' } } }
    });
}

function pintarCalificaciones(dist) {
    if(charts.calif) charts.calif.destroy();
    charts.calif = new Chart(document.getElementById('chartCalif'), {
        type: 'bar',
        data: {
            labels: ['0-69', '70-79', '80-85', '86-95', '96-100'],
            datasets: [{ 
                data: dist, 
                backgroundColor: ['#dc3545', '#fd7e14', '#ffc107', '#00d27a', '#0dcaf0'], 
                borderRadius: 6 
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { borderDash: [5, 5] } }, x: { grid: { display: false } } } }
    });
}

function pintarRadar(labels, datos) {
    if(charts.radar) charts.radar.destroy();
    charts.radar = new Chart(document.getElementById('chartRadar'), {
        type: 'radar',
        data: {
            labels: labels,
            datasets: [{ label: 'Promedio General', data: datos, backgroundColor: 'rgba(42, 67, 146, 0.2)', borderColor: '#2a4392', pointBackgroundColor: '#2a4392', borderWidth: 2 }]
        },
        options: { responsive: true, maintainAspectRatio: false, scales: { r: { beginAtZero: true, max: 100, ticks: { stepSize: 20 } } }, plugins: { legend: { display: false } } }
    });
}

function pintarHistorico(labels, alumnos, promedios) {
    if(charts.historico) charts.historico.destroy();
    
    charts.historico = new Chart(document.getElementById('chartHistorico'), {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Promedio',
                    data: promedios,
                    backgroundColor: '#0d2366',
                    borderRadius: 4,
                    yAxisID: 'y'
                },
                {
                    label: 'Alumnos',
                    data: alumnos,
                    backgroundColor: '#cce5ff',
                    borderRadius: 4,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
                y: { type: 'linear', position: 'left', beginAtZero: true, max: 100, title: { display: true, text: 'Promedio (0-100)' } },
                y1: { type: 'linear', position: 'right', beginAtZero: true, grid: { drawOnChartArea: false }, title: { display: true, text: 'Cant. Alumnos' } }
            }
        }
    });
}
