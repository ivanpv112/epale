<!-- ==========================================
     MODALES PARA CALIFICACIONES (ITERATIVO)
     ========================================== -->
<?php foreach ($todas_materias as $mat): 
    $stmt_crit = $pdo->prepare("SELECT * FROM criterios_evaluacion WHERE materia_id = ? ORDER BY categoria ASC");
    $stmt_crit->execute([$mat['materia_id']]);
    $criterios_materia = $stmt_crit->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt_cal_exist = $pdo->prepare("SELECT tipo_examen, puntaje FROM calificaciones WHERE inscripcion_id = ?");
    $stmt_cal_exist->execute([$mat['inscripcion_id']]);
    $calif_existentes = [];
    while($row = $stmt_cal_exist->fetch(PDO::FETCH_ASSOC)) { $calif_existentes[$row['tipo_examen']] = $row['puntaje']; }
?>
<div id="modalCalif_<?php echo $mat['inscripcion_id']; ?>" class="modal-overlay" style="display:none;">
    <div class="modal-content clean-modal">
        <div class="modal-header-clean">
            <h2><i class="fas fa-edit"></i> <?php echo htmlspecialchars((string)($mat['materia'] . ' ' . $mat['nivel'])); ?></h2>
            <button class="close-btn" onclick="cerrarModalCalif(<?php echo $mat['inscripcion_id']; ?>)">&times;</button>
        </div>
        <form action="acciones_expediente.php" method="POST" class="form-margin-0">
            <!-- Protección CSRF -->
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            
            <input type="hidden" name="usuario_id" value="<?php echo $usuario_id; ?>">
            <input type="hidden" name="actualizar_calificaciones" value="1">
            <input type="hidden" name="inscripcion_id" value="<?php echo $mat['inscripcion_id']; ?>">
            
            <div class="modal-body-scroll">
                <?php if($mat['grupo_estado'] == 'CERRADO' || $mat['activo'] == 0): ?>
                    <div class="alert-warning-mini">
                        <i class="fas fa-exclamation-triangle"></i> <strong>Atención:</strong> Estás editando las calificaciones de una clase finalizada.
                    </div>
                <?php endif; ?>
                <div style="display: grid; grid-template-columns: 1fr; gap: 15px;">
                    <?php foreach($criterios_materia as $crit): 
                        $codigo = $crit['codigo_examen'];
                        $val = isset($calif_existentes[$codigo]) ? floatval($calif_existentes[$codigo]) : '';
                    ?>
                        <div class="form-group mb-0">
                            <label><i class="fas <?php echo htmlspecialchars((string)$crit['icono']); ?>" style="color: <?php echo htmlspecialchars((string)$crit['color']); ?>;"></i> <?php echo htmlspecialchars((string)$crit['nombre_examen']); ?> <span class="text-muted">(Máx: <?php echo floatval($crit['puntos_maximos']); ?>)</span></label>
                            <input type="number" step="0.01" min="0" max="<?php echo floatval($crit['puntos_maximos']); ?>" name="calificaciones[<?php echo htmlspecialchars((string)$codigo); ?>]" value="<?php echo htmlspecialchars((string)$val); ?>" placeholder="Sin evaluar">
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="modal-footer-clean">
                <button type="button" class="btn-cancel" onclick="cerrarModalCalif(<?php echo $mat['inscripcion_id']; ?>)">Cancelar</button>
                <button type="submit" class="btn-save"><i class="fas fa-save"></i> Guardar</button>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>

<!-- ==========================================
     MODAL DE CERTIFICACIONES
     ========================================== -->
<div id="modalCert" class="modal-overlay" style="display:none;">
    <div class="modal-content clean-modal">
        <div class="modal-header-clean">
            <h2><i class="fas fa-award"></i> Asignar Certificación</h2>
            <button class="close-btn" onclick="cerrarModalCert()">&times;</button>
        </div>
        <form action="acciones_expediente.php" method="POST" class="form-margin-0">
            <!-- Protección CSRF -->
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            
            <input type="hidden" name="usuario_id" value="<?php echo $usuario_id; ?>">
            <input type="hidden" name="guardar_certificacion" value="1">
            <input type="hidden" name="alumno_id_cert" value="<?php echo $alumno_id; ?>">
            <input type="hidden" name="idioma_cert" id="inputIdiomaCert">
            <div class="modal-body-scroll">
                <p style="font-size: 0.9rem; color: #666; margin-top: 0; margin-bottom: 15px;">Actualizando nivel oficial obtenido en: <strong id="textoIdiomaCert" style="color: var(--udg-blue);"></strong></p>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group mb-0"><label>Nivel (Ej. B1, B2, C1)</label><input type="text" name="nivel_cert" id="inputNivelCert" required placeholder="Ej. B2" style="text-transform: uppercase;"></div>
                    <div class="form-group mb-0"><label>Puntaje Obtenido</label><input type="text" name="puntaje_cert" id="inputPuntajeCert" placeholder="Ej. 550"></div>
                    <div class="form-group mb-0"><label>Periodo</label><input type="text" name="periodo_cert" id="inputPeriodoCert" placeholder="Ej. 2022B" style="text-transform: uppercase;"></div>
                    <div class="form-group mb-0"><label>Fecha de Aplicación</label><input type="date" name="fecha_cert" id="inputFechaCert"></div>
                </div>
            </div>
            <div class="modal-footer-clean">
                <button type="button" class="btn-cancel" onclick="cerrarModalCert()">Cancelar</button>
                <button type="submit" class="btn-save" style="background:var(--udg-blue); color:white;"><i class="fas fa-save"></i> Guardar Nivel</button>
            </div>
        </form>
    </div>
</div>

<!-- ==========================================
     MODAL DE EXAMEN DIAGNÓSTICO
     ========================================== -->
<div id="modalDiag" class="modal-overlay" style="display:none;">
    <div class="modal-content clean-modal">
        <div class="modal-header-clean">
            <h2 id="modalDiagTitle"><i class="fas fa-clipboard-check"></i> Examen Diagnóstico</h2>
            <button class="close-btn" onclick="cerrarModalDiag()">&times;</button>
        </div>
        <form action="acciones_expediente.php" method="POST" class="form-margin-0">
            <!-- Validación CSRF -->
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            
            <input type="hidden" name="usuario_id" value="<?php echo $usuario_id; ?>">
            <input type="hidden" name="guardar_diagnostico" value="1">
            <input type="hidden" name="examen_id" id="inputExamenId">
            <input type="hidden" name="alumno_id_diag" value="<?php echo $alumno_id; ?>">
            <div class="modal-body-scroll">
                <div class="form-group"><label>Idioma</label><input type="text" name="idioma_diag" id="inputIdiomaDiag" required placeholder="Ej. Inglés"></div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group mb-0"><label>Nivel Asignado</label><input type="number" name="nivel_diag" id="inputNivelDiag" required min="1" max="10"></div>
                    <div class="form-group mb-0"><label>Calificación Textual</label><input type="text" name="calif_diag" id="inputCalifDiag" required placeholder="Ej. A2 INICIAL" style="text-transform: uppercase;"></div>
                    <div class="form-group mb-0"><label>Periodo</label><input type="text" name="periodo_diag" id="inputPeriodoDiag" required placeholder="Ej. 2022-B" style="text-transform: uppercase;"></div>
                    <div class="form-group mb-0"><label>Fecha de Realización</label><input type="date" name="fecha_diag" id="inputFechaDiag" required></div>
                </div>
            </div>
            <div class="modal-footer-clean">
                <button type="button" class="btn-cancel" onclick="cerrarModalDiag()">Cancelar</button>
                <button type="submit" class="btn-save" style="background:#17a2b8; color:white;"><i class="fas fa-save"></i> Guardar Diagnóstico</button>
            </div>
        </form>
    </div>
</div>
