<?php
require_once __DIR__ . '/avisos_logic.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Avisos y Notificaciones | Admin</title>
    <link rel="stylesheet" href="../css/estilos.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <style>
        .select2-container .select2-selection--single {
            height: auto !important;
            padding: 4px !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: normal !important;
            white-space: normal !important;
            padding-right: 25px !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 100% !important;
        }
    </style>
</head>
<body>

    <?php include 'menu_admin.php'; ?>

    <main class="main-content">
        <div class="page-title-center mb-30">
            <h1><i class="fas fa-bullhorn"></i> Avisos Dinámicos</h1>
            <p>Redacta mensajes para los estudiantes. Puedes dirigirlos a toda la escuela o a grupos específicos.</p>
        </div>

        <?php if($mensaje): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({ title: '<?php echo ($tipo_mensaje == "success") ? "¡Éxito!" : "Error"; ?>', text: '<?php echo addslashes($mensaje); ?>', icon: '<?php echo $tipo_mensaje; ?>', confirmButtonColor: 'var(--udg-blue)' });
                    const url = new URL(window.location); url.searchParams.delete('borrar'); url.searchParams.delete('csrf_token'); window.history.replaceState({}, '', url);
                });
            </script>
        <?php endif; ?>

        <div style="display: grid; grid-template-columns: 1fr 1.5fr; gap: 20px; align-items: start;">
            
            <div class="card" style="margin-top: 0; border-top: 4px solid var(--udg-blue);">
                <h3 style="margin-top: 0; color: var(--udg-blue); border-bottom: 1px solid #eee; padding-bottom: 10px;"><i class="fas fa-pen-nib"></i> Redactar Aviso</h3>
                
                <form id="formCrearAviso" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="crear_aviso" value="1">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="form-group">
                        <label>Dirigido a:</label>
                        <select name="audiencia" required>
                            <option value="GLOBAL::ALL" data-html="<div style='display: flex; align-items: center; justify-content: space-between; gap: 10px;'><span style='font-size: 0.85em; color: #fff; background: #007bff; padding: 4px 8px; border-radius: 4px; font-weight: bold; white-space: nowrap;'><i class='fas fa-globe'></i> GLOBAL</span><span style='font-weight: bold; font-size: 1.05em; color: #2d3748; flex-grow: 1;'>Toda la escuela</span></div>">Toda la escuela (Global)</option>
                            
                            <optgroup label="Por Idioma General">
                                <?php foreach($idiomas as $idm): 
                                    $html_idioma = "<div style='display: flex; align-items: center; justify-content: space-between; gap: 10px;'><span style='font-size: 0.85em; color: #555; background: #e2e8f0; padding: 4px 8px; border-radius: 4px; font-weight: bold; white-space: nowrap;'>" . htmlspecialchars($idm['codigo'] ?: 'IDIOMA') . "</span><span style='font-weight: bold; font-size: 1.05em; color: #2d3748; flex-grow: 1;'>" . htmlspecialchars($idm['nombre']) . " <span style='font-size:0.8em; color:#777; font-weight:normal;'>(Todos los niveles)</span></span></div>";
                                ?>
                                    <option value="IDIOMA::<?php echo htmlspecialchars($idm['nombre']); ?>" data-html="<?php echo htmlspecialchars($html_idioma); ?>">Todos los grupos de <?php echo htmlspecialchars($idm['codigo'] ? $idm['codigo'] . ' - ' . $idm['nombre'] : $idm['nombre']); ?></option>
                                <?php endforeach; ?>
                            </optgroup>

                            <optgroup label="Por Nivel / Materia">
                                <?php foreach($materias as $m): 
                                    $roman = numeroARomano($m['nivel']);
                                    $html_mat = "<div style='display: flex; align-items: center; justify-content: flex-start; gap: 10px;'><span style='font-size: 0.85em; color: #555; background: #e2e8f0; padding: 4px 8px; border-radius: 4px; font-weight: bold; white-space: nowrap;'>" . htmlspecialchars($m['clave']) . "</span><span style='font-weight: bold; font-size: 1.05em; color: #2d3748;'>" . htmlspecialchars($m['nombre']) . "</span><span style='font-weight: bold; color: var(--udg-blue); font-size: 1.1em; background: #eef2ff; padding: 4px 10px; border-radius: 6px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);'>" . $roman . "</span></div>";
                                ?>
                                    <option value="MATERIA::<?php echo $m['materia_id']; ?>" data-html="<?php echo htmlspecialchars($html_mat); ?>"><?php echo htmlspecialchars($m['clave'] . ' - ' . $m['nombre'] . ' Nivel ' . $roman); ?></option>
                                <?php endforeach; ?>
                            </optgroup>

                            <optgroup label="Por Clase (Ambas modalidades)">
                                <?php foreach($clases as $c): 
                                    $nrc_p = $c['nrc_p'] ? $c['nrc_p'] : 'NA';
                                    $nrc_v = $c['nrc_v'] ? $c['nrc_v'] : 'NA';
                                    $roman = numeroARomano($c['nivel']);
                                    $prof = strtok($c['prof'] ?? '', ' ');
                                    
                                    $lbl_nrc = "<i class='fas fa-building' style='color:#ffc107;' title='Presencial'></i> " . $nrc_p . " <span style='color:#ccc; margin:0 4px;'>|</span> <i class='fas fa-laptop' style='color:#17a2b8;' title='Virtual'></i> " . $nrc_v;
                                    $txt_nrc = "Presencial $nrc_p / Virtual $nrc_v";
                                    
                                    $html_clase = "<div style='display: flex; align-items: center; justify-content: flex-start; gap: 10px;'><span style='font-size: 0.85em; color: #555; background: #e2e8f0; padding: 4px 8px; border-radius: 4px; font-weight: bold; white-space: nowrap;'>" . $lbl_nrc . "</span><span style='font-weight: bold; font-size: 1.05em; color: #2d3748;'>" . htmlspecialchars($c['nombre']) . "</span><span style='font-weight: bold; color: var(--udg-blue); font-size: 1.1em; background: #eef2ff; padding: 4px 10px; border-radius: 6px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);'>" . $roman . "</span><span style='font-size:0.85em; color:#777; font-weight:bold; white-space:nowrap;'>(Prof. " . htmlspecialchars($prof) . ")</span></div>";
                                ?>
                                    <option value="CLASE::<?php echo $c['clave_grupo']; ?>" data-html="<?php echo htmlspecialchars($html_clase); ?>">
                                        NRC: <?php echo $txt_nrc; ?> - <?php echo htmlspecialchars($c['nombre'] . ' Nivel ' . $roman); ?> (Prof. <?php echo htmlspecialchars($prof); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Título (En negritas)</label>
                        <input type="text" name="titulo" required placeholder="Ej. Aviso de mantenimiento" maxlength="100">
                    </div>

                    <div class="form-group">
                        <label>Cuerpo del Mensaje</label>
                        <textarea name="cuerpo" rows="4" required style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; resize:none; font-family:inherit; text-align: justify; text-justify: inter-word; text-align-last: left; line-height: 1.6;" placeholder="Ej. El día de mañana la plataforma estará en mantenimiento..."></textarea>
                    </div>

                    <div class="form-group">
                        <label>Duración (Caducidad)</label>
                        <select name="tipo_duracion" id="tipoDuracion" onchange="toggleFechaCustom()" required>
                            <option value="INDEFINIDO">Hasta que yo lo borre (Indefinido)</option>
                            <option value="PERSONALIZADO">Elegir fecha y hora exacta...</option>
                        </select>
                    </div>

                    <div class="form-group" id="grupoFechaCustom" style="display: none; background: #f8f9fa; padding: 15px; border-radius: 8px; border: 1px solid #eee; border-left: 4px solid var(--udg-blue);">
                        <label style="color: var(--udg-blue);"><i class="far fa-calendar-alt"></i> Selecciona la fecha límite:</label>
                        <input type="datetime-local" name="fecha_expiracion_custom" id="inputFechaCustom" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; font-family:inherit; margin-top:5px;">
                        <small style="color: #888; display: block; margin-top: 5px;">El aviso se borrará automáticamente de los tableros en esta fecha.</small>
                    </div>

                    <div class="form-group">
                        <label>Imagen Promocional (Opcional)</label>
                        <input type="file" name="imagen" id="inputImagenAviso" accept="image/jpeg,image/png,image/webp" style="width:100%; padding:8px; border:1px dashed #bbb; border-radius:6px; background:#fdfdfd;">
                        <small style="color: #888; display: block; margin-top: 5px;">Sube un flyer o imagen (JPG, PNG, WEBP; máx. <?php echo ini_get('upload_max_filesize'); ?>). Ideal para congresos o talleres.</small>
                    </div>

                    <div class="form-group" style="margin-top: 15px;">
                        <label style="font-weight: 600; color: #1e3a8a; margin-bottom: 8px; display: block;">
                            <i class="fas fa-users-cog"></i> Público Objetivo (¿Quién puede ver este aviso?):
                        </label>
                        <div style="display: flex; flex-direction: column; gap: 8px;">
                            <label style="display: flex; align-items: center; gap: 10px; font-size: 0.88rem; cursor: pointer; color: #333; font-weight: 500; background: white; padding: 10px 14px; border-radius: 8px; border: 1px solid #cbd5e1; width: 100%; box-sizing: border-box; transition: all 0.2s ease;">
                                <input type="radio" name="rol_destinatario" value="TODOS" checked style="cursor: pointer; width: 16px; height: 16px; margin: 0;">
                                <span style="display: flex; align-items: center; gap: 8px; flex-grow: 1;">
                                    <i class="fas fa-users" style="color: var(--udg-blue); width: 18px; text-align: center;"></i>
                                    <span>Toda la Comunidad (Alumnos y Profesores)</span>
                                </span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 10px; font-size: 0.88rem; cursor: pointer; color: #333; font-weight: 500; background: white; padding: 10px 14px; border-radius: 8px; border: 1px solid #cbd5e1; width: 100%; box-sizing: border-box; transition: all 0.2s ease;">
                                <input type="radio" name="rol_destinatario" value="ALUMNO" style="cursor: pointer; width: 16px; height: 16px; margin: 0;">
                                <span style="display: flex; align-items: center; gap: 8px; flex-grow: 1;">
                                    <i class="fas fa-user-graduate" style="color: #28a745; width: 18px; text-align: center;"></i>
                                    <span>Solo Estudiantes</span>
                                </span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 10px; font-size: 0.88rem; cursor: pointer; color: #333; font-weight: 500; background: white; padding: 10px 14px; border-radius: 8px; border: 1px solid #cbd5e1; width: 100%; box-sizing: border-box; transition: all 0.2s ease;">
                                <input type="radio" name="rol_destinatario" value="PROFESOR" style="cursor: pointer; width: 16px; height: 16px; margin: 0;">
                                <span style="display: flex; align-items: center; gap: 8px; flex-grow: 1;">
                                    <i class="fas fa-chalkboard-teacher" style="color: #f59e0b; width: 18px; text-align: center;"></i>
                                    <span>Solo Profesores</span>
                                </span>
                            </label>
                        </div>
                        <small style="color: #64748b; display: block; margin-top: 6px;">Este filtro aplicará tanto para el Centro de Notificaciones en la plataforma como para el envío por correo.</small>
                    </div>

                    <div class="form-group" style="background: #eef4ff; padding: 14px 16px; border-radius: 8px; border: 1px solid #c7d9fc; margin-top: 15px;">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; margin: 0; font-weight: 600; color: var(--udg-blue);">
                            <input type="checkbox" name="notificar_correo" value="1" id="checkNotificarCorreo" style="width: 18px; height: 18px; cursor: pointer;">
                            <span><i class="fas fa-envelope-open-text"></i> Notificar también por correo electrónico al público seleccionado</span>
                        </label>
                        <small style="color: #556987; display: block; margin-top: 5px; margin-left: 28px;">Se enviará una copia con diseño oficial directamente a los correos activos del público elegido.</small>
                    </div>

                    <button type="submit" id="btnSubmitAviso" class="btn-save" style="width: 100%; margin-top: 15px;"><i class="fas fa-paper-plane"></i> Publicar Aviso</button>
                </form>
            </div>

            <div class="card" style="margin-top: 0;">
                <div style="border-bottom: 1px solid #eee; padding-bottom: 12px; margin-bottom: 14px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; flex-wrap: wrap; gap: 8px;">
                        <h3 style="margin: 0; color: #333; font-size: 1.15rem; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-broadcast-tower" style="color: var(--udg-blue);"></i> 
                            <span>Gestor de Comunicados</span>
                            <span style="font-size: 0.8rem; background: #e0e7ff; color: #3730a3; padding: 2px 8px; border-radius: 12px; font-weight: 700;">
                                <?php echo count($avisos); ?>
                            </span>
                        </h3>
                        
                        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                            <!-- Botón disparador del selector de ciclo (modal desplegable compacto) -->
                            <div style="position: relative;">
                                <button type="button" 
                                        id="btnAbrirModalCiclos" 
                                        onclick="abrirModalSelectorCiclos()" 
                                        class="btn-selector-ciclo-trigger"
                                        style="height: 38px; padding: 0 12px; font-size: 0.82rem; border-width: 1.5px;">
                                    <i class="fas fa-calendar-alt" style="color: var(--udg-blue);"></i>
                                    <span>Ciclo: <b><?php echo htmlspecialchars($ciclo_seleccionado_obj['nombre'] ?? 'Seleccionar'); ?></b></span>
                                    <?php if (!empty($ciclo_seleccionado_obj['activo'])): ?>
                                        <span class="neon-badge-activo" style="font-size: 0.62rem; padding: 1px 5px;">ACTIVO</span>
                                    <?php endif; ?>
                                    <i class="fas fa-chevron-down" style="font-size: 0.7rem; color: #64748b; margin-left: 2px;"></i>
                                </button>
                            </div>

                            <?php if (count($avisos) > 0): ?>
                                <button type="button" 
                                        class="btn-vaciar-ciclo-avisos" 
                                        onclick="confirmarVaciarCiclo('<?php echo htmlspecialchars($ciclo_seleccionado_obj['nombre'] ?? 'este ciclo'); ?>', 'avisos.php?vaciar_ciclo=<?php echo $ciclo_filtro_id; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>')"
                                        style="background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; padding: 5px 12px; border-radius: 8px; font-size: 0.8rem; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; transition: all 0.2s ease;">
                                    <i class="fas fa-broom"></i> Vaciar ciclo
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div style="max-height: 540px; overflow-y: auto; padding-right: 5px;">
                    <?php if(count($avisos) > 0): ?>
                        <div style="display: flex; flex-direction: column; gap: 12px;">
                        <?php foreach($avisos as $a): 
                            // Formatear la etiqueta de la audiencia
                            $badge_color = '#e2e3e5'; $badge_text = '#383d41'; $txt_audiencia = '';
                            if($a['tipo_audiencia'] == 'GLOBAL') { $txt_audiencia = 'Global'; $badge_color = '#fff3cd'; $badge_text = '#856404'; }
                            elseif($a['tipo_audiencia'] == 'IDIOMA') { $txt_audiencia = 'Idioma: ' . htmlspecialchars($a['audiencia_ref']); $badge_color = '#cce5ff'; $badge_text = '#004085'; }
                            elseif($a['tipo_audiencia'] == 'MATERIA') { $txt_materia = $materia_lookup[$a['audiencia_ref']] ?? htmlspecialchars('ID ' . $a['audiencia_ref']); $txt_audiencia = 'Materia: ' . $txt_materia; $badge_color = '#d4edda'; $badge_text = '#155724'; }
                            elseif($a['tipo_audiencia'] == 'GRUPO') { $txt_grupo = $grupo_lookup[$a['audiencia_ref']] ?? htmlspecialchars('NRC ' . $a['audiencia_ref']); $txt_audiencia = 'Grupo: ' . $txt_grupo; $badge_color = '#e2e3e5'; $badge_text = '#383d41'; }

                            $img_src_rel = $a['imagen'] ? '../img/avisos/' . basename($a['imagen']) : '';
                            $aviso_data = [
                                'titulo' => $a['titulo'],
                                'etiqueta' => $txt_audiencia,
                                'tag_color' => $badge_color,
                                'tag_text' => $badge_text,
                                'cuerpo' => nl2br(htmlspecialchars($a['cuerpo'])),
                                'imagen' => $img_src_rel,
                                'fecha' => date('d/m/Y h:i A', strtotime($a['fecha_creacion'])),
                                'expira' => $a['fecha_expiracion'] ? date('d/m/Y h:i A', strtotime($a['fecha_expiracion'])) : null
                            ];
                            $json_info = htmlspecialchars(json_encode($aviso_data), ENT_QUOTES, 'UTF-8');
                        ?>
                            <div class="aviso-card-tile aviso-clickable texto-seguro" onclick="abrirModalAvisoAdmin(this)" data-info="<?php echo $json_info; ?>" style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; position: relative; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px; padding-right: 30px;">
                                    <div style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap;">
                                        <span class="tag-aviso" style="background: <?php echo $badge_color; ?>; color: <?php echo $badge_text; ?>; padding: 3px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: bold; border: 1px solid <?php echo $badge_text; ?>;">
                                            <?php echo $txt_audiencia; ?>
                                        </span>
                                        <?php if(($a['rol_destinatario'] ?? 'TODOS') === 'ALUMNO'): ?>
                                            <span style="font-size: 0.72rem; background: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7; padding: 2px 7px; border-radius: 6px; font-weight: bold;">
                                                <i class="fas fa-user-graduate"></i> Solo Alumnos
                                            </span>
                                        <?php elseif(($a['rol_destinatario'] ?? 'TODOS') === 'PROFESOR'): ?>
                                            <span style="font-size: 0.72rem; background: #fff8e1; color: #f57f17; border: 1px solid #ffe082; padding: 2px 7px; border-radius: 6px; font-weight: bold;">
                                                <i class="fas fa-chalkboard-teacher"></i> Solo Profesores
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($a['imagen'])): ?>
                                        <span style="font-size: 0.75rem; color: var(--udg-blue); font-weight: bold; display: flex; align-items: center; gap: 4px;">
                                            <i class="fas fa-image"></i> Cartel
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <a href="#" onclick="event.stopPropagation(); confirmarBorrado('avisos.php?borrar=<?php echo $a['aviso_id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>')" style="position: absolute; top: 12px; right: 12px; color: #dc3545; font-size: 1.1rem; padding: 4px 6px; border-radius: 4px; transition: background 0.2s;" title="Eliminar este aviso"><i class="fas fa-trash-alt"></i></a>

                                <div style="margin-bottom: 6px;">
                                    <strong style="color: var(--text-dark); font-size: 0.98rem;"><?php echo htmlspecialchars($a['titulo']); ?></strong>
                                </div>

                                <div style="color: var(--text-muted); font-size: 0.88rem; margin-bottom: 8px; line-height: 1.5;">
                                    <?php echo recortar_texto(htmlspecialchars($a['cuerpo']), 110); ?>
                                </div>

                                <div style="font-size: 0.75rem; color: var(--text-muted); border-top: 1px dashed rgba(0,0,0,0.08); padding-top: 8px; margin-top: 4px; display: flex; justify-content: space-between; align-items: center;">
                                    <span><i class="far fa-clock"></i> <?php echo date('d/m/Y H:i', strtotime($a['fecha_creacion'])); ?></span>
                                    <span>
                                        <?php if($a['fecha_expiracion']): ?>
                                            <i class="fas fa-stopwatch" style="color: #dc3545;"></i> Expira: <strong><?php echo date('d/m/Y H:i', strtotime($a['fecha_expiracion'])); ?></strong>
                                        <?php else: ?>
                                            <i class="fas fa-infinity" style="color: #28a745;"></i> Indefinido
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 40px 20px; color: #aaa;">
                            <i class="fas fa-wind" style="font-size: 3rem; margin-bottom: 15px; display: block; color: #eee;"></i>
                            No hay avisos activos en este momento.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </main>

    <?php /* MODAL DE DETALLE DEL AVISO (VISTA IDÉNTICA A LA DEL ESTUDIANTE) */ ?>
    <div id="modalDetalleAvisoAdmin" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 3100; justify-content: center; align-items: center; padding: 20px;">
        <div class="modal-card" style="background: white; border-radius: 12px; width: 100%; max-width: 650px; max-height: 90vh; overflow-y: auto; padding: 25px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); position: relative;">
            <div class="modal-card-body">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
                    <span id="modAdminTag" class="tag-aviso" style="font-weight: bold; font-size: 0.8rem; padding: 4px 10px; border-radius: 12px;"></span>
                </div>

                <h2 id="modAdminTitulo" style="color: var(--udg-blue); margin-top: 0; margin-bottom: 15px; font-size: 1.35rem; padding-right: 15px; line-height: 1.4;"></h2>

                <div id="modAdminDesc" class="texto-seguro" style="color: var(--text-dark); font-size: 1rem; line-height: 1.6; margin-bottom: 25px; text-align: justify; text-justify: inter-word;"></div>
                
                <div id="modAdminImgContainer" style="display: none; text-align: center; margin-bottom: 25px;">
                    <div style="position: relative; display: inline-block; cursor: pointer;" onclick="ampliarImagenAviso(document.getElementById('modAdminImg').src)">
                        <img id="modAdminImg" src="" alt="Cartel Informativo" style="max-width: 100%; max-height: 420px; object-fit: contain; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.12); transition: transform 0.2s, opacity 0.2s;" onmouseover="this.style.opacity='0.92'" onmouseout="this.style.opacity='1'">
                        <div style="position: absolute; bottom: 10px; right: 10px; background: rgba(0,0,0,0.65); color: white; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; pointer-events: none;">
                            <i class="fas fa-search-plus"></i> Clic para ampliar
                        </div>
                    </div>
                </div>

                <div style="background: rgba(0, 26, 87, 0.05); padding: 15px; border-radius: 8px; border-left: 4px solid var(--udg-blue); font-size: 0.88rem; color: #555;">
                    <i class="fas fa-user-shield"></i> <strong>Publicado por:</strong> Administración E-Pale<br>
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

    <!-- Modal desplegable para seleccionar ciclo escolar estandarizado -->
    <div id="modalSelectorCiclosAdmin" class="modal-ciclos-overlay" onclick="if(event.target === this) cerrarModalSelectorCiclos();">
        <div class="modal-ciclos-card">
            <div class="modal-ciclos-header">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-calendar-alt" style="font-size: 1.15rem; color: #93c5fd;"></i>
                    <h3>Seleccionar Ciclo Escolar</h3>
                </div>
                <button type="button" class="modal-ciclos-close-btn" onclick="cerrarModalSelectorCiclos()">&times;</button>
            </div>
            
            <div class="modal-ciclos-body">
                <p class="modal-ciclos-desc">
                    Elige el periodo que deseas consultar o gestionar en el Gestor de Comunicados:
                </p>

                <div class="modal-ciclos-list">
                    <?php foreach($todos_los_ciclos as $c_item): 
                        $es_sel = ($c_item['ciclo_id'] == $ciclo_filtro_id);
                        $es_activo_sys = ($c_item['activo'] == 1);
                    ?>
                        <a href="avisos.php?ciclo_ver=<?php echo $c_item['ciclo_id']; ?>" 
                           class="ciclo-item-link <?php echo $es_sel ? 'is-selected' : ''; ?>">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <i class="fas fa-folder<?php echo $es_sel ? '-open' : ''; ?>" style="font-size: 1rem;"></i>
                                <span style="font-size: 0.92rem; font-weight: <?php echo $es_sel ? '700' : '600'; ?>;">
                                    Ciclo <?php echo htmlspecialchars($c_item['nombre']); ?>
                                </span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <?php if ($es_activo_sys): ?>
                                    <span style="font-size: 0.68rem; padding: 2px 8px; border-radius: 12px; font-weight: 700; background: #dcfce7; color: #166534; border: 1px solid #bbf7d0;">
                                        ACTIVO
                                    </span>
                                <?php endif; ?>
                                <?php if ($es_sel): ?>
                                    <i class="fas fa-check-circle" style="font-size: 1.05rem;"></i>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="modal-ciclos-footer">
                <button type="button" class="modal-ciclos-footer-btn" onclick="cerrarModalSelectorCiclos()">
                    Cerrar
                </button>
            </div>
        </div>
    </div>

    <!-- Lightbox para ampliar imagen del aviso sin abrir pestaña -->
    <div id="lightboxAvisoAdmin" onclick="cerrarLightboxAviso()" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); z-index: 99999; justify-content: center; align-items: center; cursor: pointer; backdrop-filter: blur(3px);">
        <img id="lightboxImgAviso" src="" alt="Cartel ampliado" style="max-width: 92%; max-height: 92vh; border-radius: 8px; box-shadow: 0 8px 30px rgba(0,0,0,0.6); object-fit: contain; border: 2px solid rgba(255,255,255,0.2); cursor: default;" onclick="event.stopPropagation()">
    </div>

    <?php include '../main_footer.php'; ?>
    
    <script>
        function toggleMobileMenu() { document.getElementById('navWrapper').classList.toggle('active'); document.getElementById('menuOverlay').classList.toggle('active'); }
        
        function confirmarBorrado(url) {
            Swal.fire({ title: '¿Eliminar Aviso?', text: "Este aviso desaparecerá inmediatamente de los tableros de los alumnos.", icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc3545', cancelButtonColor: '#6c757d', confirmButtonText: 'Sí, eliminar' }).then((result) => { if (result.isConfirmed) { window.location.href = url; } });
        }

        function confirmarVaciarCiclo(nombreCiclo, url) {
            Swal.fire({
                title: '¿Vaciar avisos de ' + nombreCiclo + '?',
                html: '<div style="font-size:0.95rem; color:#475569; line-height:1.5;">Esta acción eliminará <b>todos los comunicados institucionales</b> registrados en este ciclo escolar junto con sus archivos de imagen.<br><br><span style="color:#dc2626; font-weight:600;">Esta acción no se puede deshacer.</span></div>',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#64748b',
                confirmButtonText: '<i class="fas fa-trash-alt"></i> Sí, vaciar todos',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = url;
                }
            });
        }

        // Toggle del contenedor de opciones de rol cuando se activa el checkbox de correo
        function toggleOpcionesCorreo() {
            const check = document.getElementById('checkNotificarCorreo');
            const cont = document.getElementById('contenedorFiltroRol');
            if (check && cont) {
                cont.style.display = check.checked ? 'block' : 'none';
            }
        }

        // Modal de Detalle de Aviso (Igual que en estudiante/index.php)
        function abrirModalAvisoAdmin(elemento) {
            try {
                const info = JSON.parse(elemento.getAttribute('data-info'));

                const tag = document.getElementById('modAdminTag');
                tag.innerHTML = info.etiqueta;
                tag.style.background = info.tag_color;
                tag.style.color = info.tag_text;
                tag.style.border = '1px solid ' + info.tag_text;

                document.getElementById('modAdminTitulo').innerHTML = info.titulo;
                document.getElementById('modAdminDesc').innerHTML = info.cuerpo;
                document.getElementById('modAdminFecha').innerHTML = info.fecha;

                const expRow = document.getElementById('modAdminExpiraRow');
                const expSpan = document.getElementById('modAdminExpira');
                if (info.expira) {
                    expSpan.innerHTML = info.expira;
                    expRow.style.display = 'inline';
                } else {
                    expRow.style.display = 'none';
                }

                const imgContainer = document.getElementById('modAdminImgContainer');
                const img = document.getElementById('modAdminImg');

                if (info.imagen) {
                    img.src = info.imagen;
                    imgContainer.style.display = 'block';
                } else {
                    imgContainer.style.display = 'none';
                    img.src = '';
                }

                document.getElementById('modalDetalleAvisoAdmin').style.display = 'flex';
            } catch (e) {
                console.error("Error al procesar el aviso administrativo", e);
            }
        }

        function cerrarModalAvisoAdmin() {
            document.getElementById('modalDetalleAvisoAdmin').style.display = 'none';
        }

        function ampliarImagenAviso(src) {
            if (!src) return;
            const lightbox = document.getElementById('lightboxAvisoAdmin');
            const img = document.getElementById('lightboxImgAviso');
            img.src = src;
            lightbox.style.display = 'flex';
        }

        function cerrarLightboxAviso() {
            const lightbox = document.getElementById('lightboxAvisoAdmin');
            if (lightbox) lightbox.style.display = 'none';
        }

        // Funciones para el modal selector de ciclos
        function abrirModalSelectorCiclos() {
            const modal = document.getElementById('modalSelectorCiclosAdmin');
            if (modal) modal.style.display = 'flex';
        }

        function cerrarModalSelectorCiclos() {
            const modal = document.getElementById('modalSelectorCiclosAdmin');
            if (modal) modal.style.display = 'none';
        }

        // Tecla Escape para cerrar modales y lightbox
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                cerrarLightboxAviso();
                cerrarModalAvisoAdmin();
                cerrarModalSelectorCiclos();
            }
        });

        // Cerrar modal o lightbox al hacer clic en el fondo oscuro
        window.onclick = function(e) {
            if (e.target == document.getElementById('modalDetalleAvisoAdmin')) cerrarModalAvisoAdmin();
            if (e.target == document.getElementById('lightboxAvisoAdmin')) cerrarLightboxAviso();
            if (e.target == document.getElementById('modalSelectorCiclosAdmin')) cerrarModalSelectorCiclos();
        };

        // Validación en el cliente del peso de la imagen y confirmación previa de publicación
        (function() {
            const input = document.getElementById('inputImagenAviso');
            const form = document.getElementById('formCrearAviso');
            let formConfirmado = false;
            if (!input || !form) return;

            function parseSize(sizeStr) {
                if (!sizeStr) return 2 * 1024 * 1024;
                const units = { K: 1024, M: 1024 * 1024, G: 1024 * 1024 * 1024 };
                const match = sizeStr.toString().trim().match(/^(\d+)\s*([KMG])?B?$/i);
                if (match) {
                    const num = parseInt(match[1], 10);
                    const unit = match[2] ? match[2].toUpperCase() : '';
                    return num * (units[unit] || 1);
                }
                return parseInt(sizeStr, 10) || (2 * 1024 * 1024);
            }

            const iniUpload = '<?php echo ini_get('upload_max_filesize'); ?>';
            const iniPost = '<?php echo ini_get('post_max_size'); ?>';
            const maxBytesUpload = parseSize(iniUpload);
            const maxBytesPost = parseSize(iniPost);
            const effectiveMaxBytes = Math.min(maxBytesUpload, maxBytesPost);
            const displayLimit = iniUpload;

            function validarArchivo() {
                if (input.files && input.files.length > 0) {
                    const file = input.files[0];
                    if (file.size > effectiveMaxBytes) {
                        Swal.fire({
                            title: 'Imagen demasiado pesada',
                            text: 'El archivo seleccionado (' + (file.size / (1024 * 1024)).toFixed(2) + ' MB) supera el límite máximo permitido de ' + displayLimit + '. Por favor, selecciona una imagen más ligera.',
                            icon: 'warning',
                            confirmButtonColor: 'var(--udg-blue)'
                        });
                        input.value = '';
                        return false;
                    }
                }
                return true;
            }

            input.addEventListener('change', validarArchivo);

            form.addEventListener('submit', function(e) {
                if (!validarArchivo()) {
                    e.preventDefault();
                    return;
                }

                // Si ya fue confirmado por el SweetAlert, ejecutar el submit y mostrar animación de carga
                if (formConfirmado) {
                    const btn = document.getElementById('btnSubmitAviso');
                    const notifCheck = document.getElementById('checkNotificarCorreo');
                    const enviandoCorreo = notifCheck && notifCheck.checked;

                    if (btn) {
                        btn.disabled = true;
                        btn.style.opacity = '0.85';
                        btn.style.cursor = 'not-allowed';
                        if (enviandoCorreo) {
                            btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Publicando y enviando correos...';
                        } else {
                            btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Publicando aviso...';
                        }
                    }

                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            title: enviandoCorreo ? 'Publicando y notificando...' : 'Publicando comunicado...',
                            html: enviandoCorreo 
                                ? '<div style="font-size:0.95rem; color:#64748b; line-height:1.5; margin-top:8px;">'
                                    + '<i class="fas fa-paper-plane fa-bounce" style="color:var(--udg-blue); font-size:1.8rem; margin-bottom:12px; display:block;"></i>'
                                    + 'Generando el aviso y enviando copias por correo electrónico a la comunidad seleccionada.<br><small style="color:#94a3b8;">Esto tomará solo unos segundos...</small>'
                                  + '</div>'
                                : '<div style="font-size:0.95rem; color:#64748b; margin-top:8px;">Guardando la publicación en el ciclo escolar activo...</div>',
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            showConfirmButton: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });
                    }
                    return;
                }

                // Detener el envío directo y pedir confirmación previa
                e.preventDefault();

                // Recopilar datos clave para mostrar en la alerta de confirmación
                const inputTitulo = form.querySelector('input[name="titulo"]');
                const tituloTexto = inputTitulo ? inputTitulo.value.trim() : 'Aviso';
                
                const radioRol = form.querySelector('input[name="rol_destinatario"]:checked');
                let rolTexto = 'Toda la comunidad (Alumnos y Profesores)';
                if (radioRol) {
                    if (radioRol.value === 'ALUMNO') rolTexto = 'Solo Alumnos';
                    else if (radioRol.value === 'PROFESOR') rolTexto = 'Solo Profesores';
                }

                const notifCheck = document.getElementById('checkNotificarCorreo');
                const enviandoCorreo = notifCheck && notifCheck.checked;

                let htmlConfirmacion = '<div style="font-size:0.92rem; color:#475569; text-align:left; line-height:1.6; margin-top:10px;">'
                    + '<div style="margin-bottom:8px;"><b>Título:</b> <span style="color:#0f172a;">' + $('<div>').text(tituloTexto).html() + '</span></div>'
                    + '<div style="margin-bottom:8px;"><b>Público:</b> <span style="color:#1e40af; font-weight:600;">' + rolTexto + '</span></div>';

                if (enviandoCorreo) {
                    htmlConfirmacion += '<div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:10px; margin-top:10px; color:#1e3a8a; font-size:0.85rem;">'
                        + '<i class="fas fa-envelope" style="color:#2563eb; margin-right:6px;"></i><b>Atención:</b> Se enviarán notificaciones por <b>correo electrónico</b> a los usuarios correspondientes.'
                        + '</div>';
                }

                htmlConfirmacion += '</div>';

                Swal.fire({
                    title: '¿Publicar este comunicado?',
                    html: htmlConfirmacion,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: 'var(--udg-blue)',
                    cancelButtonColor: '#64748b',
                    confirmButtonText: '<i class="fas fa-paper-plane"></i> Sí, publicar',
                    cancelButtonText: 'Revisar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        formConfirmado = true;
                        // Disparar submit nuevamente (ahora formConfirmado = true procederá al envío y loading spinner)
                        form.requestSubmit();
                    }
                });
            });
        })();

        // Lógica para mostrar/ocultar el calendario
        function toggleFechaCustom() {
            const select = document.getElementById('tipoDuracion');
            const grupoFecha = document.getElementById('grupoFechaCustom');
            const inputFecha = document.getElementById('inputFechaCustom');

            if (select.value === 'PERSONALIZADO') {
                grupoFecha.style.display = 'block';
                inputFecha.required = true;
                
                const tzoffset = (new Date()).getTimezoneOffset() * 60000;
                const localISOTime = (new Date(Date.now() - tzoffset)).toISOString().slice(0, 16);
                inputFecha.min = localISOTime;
            } else {
                grupoFecha.style.display = 'none';
                inputFecha.required = false;
                inputFecha.value = '';
            }
        }

        // Iniciar Select2
        $(document).ready(function() {
            function formatAudiencia(state) {
                if (!state.id) { return state.text; }
                var html = state.element.getAttribute('data-html');
                if (html) {
                    return $('<span>' + html + '</span>');
                }
                return state.text;
            }

            $('select[name="audiencia"]').select2({
                placeholder: "Selecciona a quién va dirigido",
                width: '100%',
                templateResult: formatAudiencia,
                templateSelection: formatAudiencia
            });
        });
    </script>
</body>
</html>