<?php
session_start();
require '../db.php';

// Seguridad básica
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ADMIN') {
    header("Location: ../index.php"); exit;
}

// Saber si es crear o editar
$action = $_POST['action'] ?? 'create';
$old_nrc_p = $_POST['old_nrc_p'] ?? '';
$old_nrc_v = $_POST['old_nrc_v'] ?? '';

// Recolectar datos generales
$profesor_id = $_POST['profesor_id'] ?? null;
$materia_id = $_POST['materia_id'] ?? null;
$ciclo_id = $_POST['ciclo_id'] ?? null;

// Recolectar datos presenciales
$rnc_presencial = trim($_POST['rnc_presencial'] ?? '');
$aula_presencial = trim($_POST['aula_presencial'] ?? '');
$dias_presencial = trim($_POST['dias_presencial'] ?? '');
$inicio_presencial = !empty($_POST['inicio_presencial']) ? $_POST['inicio_presencial'] : null;
$fin_presencial = !empty($_POST['fin_presencial']) ? $_POST['fin_presencial'] : null;

// Recolectar datos virtuales
$rnc_virtual = trim($_POST['rnc_virtual'] ?? '');
$aula_virtual = trim($_POST['aula_virtual'] ?? '');
$dias_virtual = trim($_POST['dias_virtual'] ?? '');
$inicio_virtual = !empty($_POST['inicio_virtual']) ? $_POST['inicio_virtual'] : null;
$fin_virtual = !empty($_POST['fin_virtual']) ? $_POST['fin_virtual'] : null;

// Validaciones básicas
if (!$profesor_id || !$materia_id || !$ciclo_id) {
    header("Location: grupos_nrc.php?error=Faltan campos obligatorios"); exit;
}

try {
    $pdo->beginTransaction();

    if ($action === 'edit') {
        // === MODO EDICIÓN ===
        
        // 1. Actualizar grupo presencial (si ya existía)
        if ($old_nrc_p !== '') {
            // Actualizamos profesor, materia y ciclo
            $pdo->prepare("UPDATE grupos SET profesor_id=?, materia_id=?, ciclo_id=? WHERE nrc=?")
                ->execute([$profesor_id, $materia_id, $ciclo_id, $old_nrc_p]);
            // Actualizamos los horarios
            $pdo->prepare("UPDATE horarios SET dias_patron=?, hora_inicio=?, hora_fin=?, aula=? WHERE nrc=?")
                ->execute([$dias_presencial, $inicio_presencial, $fin_presencial, $aula_presencial, $old_nrc_p]);
        } 
        // 1.B. Si no existía presencial pero el admin lo acaba de llenar
        elseif ($rnc_presencial !== '') {
            $pdo->prepare("INSERT INTO grupos (nrc, materia_id, profesor_id, ciclo_id, cupo) VALUES (?, ?, ?, ?, 30)")
                ->execute([$rnc_presencial, $materia_id, $profesor_id, $ciclo_id]);
            $pdo->prepare("INSERT INTO horarios (nrc, dias_patron, hora_inicio, hora_fin, modalidad, aula) VALUES (?, ?, ?, ?, 'PRESENCIAL', ?)")
                ->execute([$rnc_presencial, $dias_presencial, $inicio_presencial, $fin_presencial, $aula_presencial]);
        }

        // 2. Actualizar grupo virtual (si ya existía)
        if ($old_nrc_v !== '') {
            $pdo->prepare("UPDATE grupos SET profesor_id=?, materia_id=?, ciclo_id=? WHERE nrc=?")
                ->execute([$profesor_id, $materia_id, $ciclo_id, $old_nrc_v]);
            $pdo->prepare("UPDATE horarios SET dias_patron=?, hora_inicio=?, hora_fin=?, aula=? WHERE nrc=?")
                ->execute([$dias_virtual, $inicio_virtual, $fin_virtual, $aula_virtual, $old_nrc_v]);
        }
        // 2.B. Si no existía virtual pero el admin lo acaba de llenar
        elseif ($rnc_virtual !== '') {
            $pdo->prepare("INSERT INTO grupos (nrc, materia_id, profesor_id, ciclo_id, cupo) VALUES (?, ?, ?, ?, 30)")
                ->execute([$rnc_virtual, $materia_id, $profesor_id, $ciclo_id]);
            $pdo->prepare("INSERT INTO horarios (nrc, dias_patron, hora_inicio, hora_fin, modalidad, aula) VALUES (?, ?, ?, ?, 'VIRTUAL', ?)")
                ->execute([$rnc_virtual, $dias_virtual, $inicio_virtual, $fin_virtual, $aula_virtual]);
        }

    } else {
        // === MODO CREACIÓN (Nuevo Grupo) ===
        $insertGrupo = $pdo->prepare("INSERT INTO grupos (nrc, materia_id, profesor_id, ciclo_id, cupo) VALUES (?, ?, ?, ?, 30)");
        $insertHorario = $pdo->prepare("INSERT INTO horarios (nrc, dias_patron, hora_inicio, hora_fin, modalidad, aula) VALUES (?, ?, ?, ?, ?, ?)");

        if ($rnc_presencial !== '') {
            $insertGrupo->execute([$rnc_presencial, $materia_id, $profesor_id, $ciclo_id]);
            if ($dias_presencial || $inicio_presencial || $fin_presencial || $aula_presencial) {
                $insertHorario->execute([$rnc_presencial, $dias_presencial, $inicio_presencial, $fin_presencial, 'PRESENCIAL', $aula_presencial]);
            }
        }

        if ($rnc_virtual !== '') {
            $insertGrupo->execute([$rnc_virtual, $materia_id, $profesor_id, $ciclo_id]);
            if ($dias_virtual || $inicio_virtual || $fin_virtual || $aula_virtual) {
                $insertHorario->execute([$rnc_virtual, $dias_virtual, $inicio_virtual, $fin_virtual, 'VIRTUAL', $aula_virtual]);
            }
        }
    }

    $pdo->commit();
    header("Location: grupos_nrc.php?success=1"); exit;
} catch (Exception $e) {
    $pdo->rollBack();
    header("Location: grupos_nrc.php?error=" . urlencode($e->getMessage())); exit;
}
?>
