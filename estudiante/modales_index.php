    <?php 
    /**
     * @var array $avisos_prof_todas
     */
    /*  MODAL 1: HISTORIAL DE TODAS LAS ACTIVIDADES (Activas, Próximas y Finalizadas)  */ 
    ?>
    <div id="modalTodasActividades" class="modal-overlay">
        <div class="modal-card-lg" style="max-height: 90vh;">
            <div class="modal-header" style="border-bottom: 1px solid var(--bg-gray); padding-bottom: 15px; margin-bottom: 15px;">
                <h2 style="margin:0; color:var(--udg-blue); font-size: 1.4rem;"><i class="fas fa-tasks"></i> Historial de Anuncios</h2>
                <button class="close-btn" onclick="cerrarModalTodasActividades()" style="position:relative; top:0; right:0;">&times;</button>
            </div>
            <div class="modal-card-body" style="padding-right: 15px;">
                <?php if (count($avisos_prof_todas) > 0): ?>
                    <div class="modal-avisos-grid">
                        <?php foreach ($avisos_prof_todas as $a):
                            $badgeClass = '';
                            if ($a['estatus'] === 'PRÓXIMA') $badgeClass = 'background-color: #ffc107; color: #000;';
                            elseif ($a['estatus'] === 'ACTIVA') $badgeClass = 'background-color: #28a745; color: white;';
                            elseif ($a['estatus'] === 'FINALIZADA') $badgeClass = 'background-color: #dc3545; color: white;';

                            $icono = $a['tipo'] === 'AVISO' ? '📢' : '📝';
                            $borde_izq = $a['tipo'] === 'AVISO' ? '#17a2b8' : '#0056b3';

                            // JSON Data-Info Limpio
                            $aviso_prof_data = [
                                'materia' => $a['materia_nombre'],
                                'estatus_html' => '<span style="' . $badgeClass . ' font-size: 0.75rem; padding: 3px 8px; border-radius: 12px; font-weight: bold;">' . $a['estatus'] . '</span>',
                                'titulo' => $icono . ' ' . $a['titulo'],
                                'descripcion' => nl2br(htmlspecialchars($a['descripcion'])),
                                'profesor' => $a['prof_nombre'] . ' ' . $a['prof_ap'],
                                'fecha_inicio' => date('d/m/Y h:i A', strtotime($a['fecha_inicio'])),
                                'fecha_fin' => date('d/m/Y h:i A', strtotime($a['fecha_fin']))
                            ];
                            $json_info = htmlspecialchars(json_encode($aviso_prof_data), ENT_QUOTES, 'UTF-8');
                        ?>
                            <div class="aviso-card-tile aviso-clickable texto-seguro" onclick="abrirModalDetalle(this)" data-info="<?php echo $json_info; ?>" style="border-left: 4px solid <?php echo $borde_izq; ?>;">
                                <div>
                                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom: 8px;">
                                        <span class="tag-aviso" style="background: var(--bg-gray); color: var(--text-muted); border: 1px solid var(--text-muted); font-weight:bold; font-size:0.75rem; padding: 3px 8px; border-radius: 12px;"><?php echo htmlspecialchars($a['materia_nombre'] . ' ' . nivel_a_romano($a['materia_nivel'])); ?></span>
                                        <span style="<?php echo $badgeClass; ?> font-size: 0.75rem; padding: 3px 8px; border-radius: 12px; font-weight: bold;"><?php echo $a['estatus']; ?></span>
                                    </div>
                                    <div style="margin-top: 6px; margin-bottom: 6px;">
                                        <strong style="color: var(--text-dark); font-size:1rem;"><?php echo $icono; ?> <?php echo htmlspecialchars($a['titulo']); ?></strong>
                                    </div>
                                    <div style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 8px;">
                                        <?php echo recortar_texto(htmlspecialchars($a['descripcion']), 70); ?>
                                    </div>
                                </div>
                                <div style="font-size: 0.75rem; color: var(--text-muted); border-top: 1px dashed rgba(0,0,0,0.08); padding-top: 6px; margin-top: 6px;">
                                    <i class="far fa-calendar-alt"></i> Cierre: <strong><?php echo date('d/m/Y h:i A', strtotime($a['fecha_fin'])); ?></strong>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="text-align:center; color:var(--text-muted); padding: 30px 0;">No hay anuncios registrados en este periodo.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php /*  MODAL 2: DETALLE FLOTANTE DEL AVISO INDIVIDUAL (Superpuesto)  */ ?>
    <div id="modalDetalleActividad" class="modal-overlay" style="z-index: 3100;">
        <div class="modal-card">
            <div class="modal-card-body">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:15px;">
                    <span id="modMateria" class="tag-aviso" style="background:var(--bg-gray); color:var(--text-muted); border:1px solid var(--text-muted); font-weight:bold; font-size:0.8rem; padding:4px 10px; border-radius:12px;"></span>
                    <span id="modBadge"></span>
                </div>

                <h2 id="modTitulo" style="color:var(--udg-blue); margin-top:0; margin-bottom:15px; font-size:1.4rem; padding-right: 15px;"></h2>

                <div id="modDesc" class="texto-seguro" style="color:var(--text-dark); font-size:1.05rem; line-height:1.6; margin-bottom:25px;"></div>

                <div style="background:rgba(0, 26, 87, 0.05); padding:15px; border-radius:8px; border-left: 4px solid var(--udg-blue); font-size:0.9rem; color:var(--text-muted);">
                    <i class="fas fa-chalkboard-teacher"></i> <strong>Publicado por:</strong> <span id="modProf"></span><br>
                    <i class="far fa-calendar-alt" style="margin-top: 8px;"></i> <strong>Fecha de Publicación:</strong> <span id="modInicio"></span><br>
                    <i class="far fa-calendar-check" style="margin-top: 8px;"></i> <strong>Fecha Límite:</strong> <span id="modFin"></span>
                </div>
            </div>

            <!-- Footer con botón rojo neón de cierre -->
            <div style="display: flex; justify-content: flex-end; margin-top: 15px; padding-top: 12px; border-top: 1px solid rgba(128,128,128,0.15);">
                <button type="button" class="btn-neon-close" onclick="cerrarModalDetalle()">
                    Cerrar
                </button>
            </div>
        </div>
    </div>

    <?php /*  MODAL 3: DETALLE DEL AVISO ADMINISTRATIVO (CON IMAGEN)  */ ?>
    <div id="modalDetalleAvisoAdmin" class="modal-overlay" style="z-index: 3100;">
        <div class="modal-card">
            <div class="modal-card-body">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:15px;">
                    <span id="modAdminTag" class="tag-aviso" style="font-weight:bold; font-size:0.8rem; padding:4px 10px; border-radius:12px;"></span>
                </div>

                <h2 id="modAdminTitulo" style="color:var(--udg-blue); margin-top:0; margin-bottom:15px; font-size:1.4rem; padding-right: 15px;"></h2>

                <div id="modAdminDesc" class="texto-seguro" style="color:var(--text-dark); font-size:1.05rem; line-height:1.6; margin-bottom:25px; text-align: justify; text-justify: inter-word;"></div>
                
                <div id="modAdminImgContainer" style="display: none; text-align: center; margin-bottom: 25px;">
                    <div style="position: relative; display: inline-block; cursor: pointer;" onclick="ampliarImagenAviso(document.getElementById('modAdminImg').src)">
                        <img id="modAdminImg" src="" alt="Cartel Informativo" style="max-width: 100%; max-height: 420px; object-fit: contain; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.12); transition: transform 0.2s, opacity 0.2s;" onmouseover="this.style.opacity='0.92'" onmouseout="this.style.opacity='1'">
                        <div style="position: absolute; bottom: 10px; right: 10px; background: rgba(0,0,0,0.65); color: white; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; pointer-events: none;">
                            <i class="fas fa-search-plus"></i> Clic para ampliar
                        </div>
                    </div>
                </div>

                <div style="background:rgba(0, 26, 87, 0.05); padding:15px; border-radius:8px; border-left: 4px solid var(--udg-blue); font-size:0.9rem; color:var(--text-muted);">
                    <i class="fas fa-user-shield"></i> <strong>Publicado por:</strong> <span id="modAdminAutor">Administración E-Pale</span><br>
                    <i class="far fa-calendar-alt" style="margin-top: 8px;"></i> <strong>Fecha de Publicación:</strong> <span id="modAdminFecha"></span><br>
                    <span id="modAdminExpiraRow" style="display: none;">
                        <i class="fas fa-stopwatch" style="margin-top: 8px; color: #dc3545;"></i> <strong>Fecha Límite:</strong> <span id="modAdminExpira"></span>
                    </span>
                </div>
            </div>

            <!-- Footer con botón rojo neón de cierre -->
            <div style="display: flex; justify-content: flex-end; margin-top: 15px; padding-top: 12px; border-top: 1px solid rgba(128,128,128,0.15);">
                <button type="button" class="btn-neon-close" onclick="cerrarModalAvisoAdmin()">
                    Cerrar
                </button>
            </div>
        </div>
    </div>

    <!-- Lightbox para ampliar imagen del aviso sin abrir pestaña -->
    <div id="lightboxAvisoAdmin" onclick="cerrarLightboxAviso()" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.85); z-index:99999; justify-content:center; align-items:center; cursor:pointer; backdrop-filter:blur(3px);">
        <img id="lightboxImgAviso" src="" alt="Cartel ampliado" style="max-width:92%; max-height:92vh; border-radius:8px; box-shadow:0 8px 30px rgba(0,0,0,0.6); object-fit:contain; border:2px solid rgba(255,255,255,0.2); cursor:default;" onclick="event.stopPropagation()">
    </div>
