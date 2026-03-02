<?php
session_start();
require '../db.php';

// seguridad básica
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ADMIN') {
    header("Location: ../index.php"); exit;
}

// recolectar datos
$profesor_id = $_POST['profesor_id'] ?? null;
$materia_id = $_POST['materia_id'] ?? null;
$ciclo_id = $_POST['ciclo_id'] ?? null;

$rnc_presencial = trim($_POST['rnc_presencial'] ?? '');
$aula_presencial = trim($_POST['aula_presencial'] ?? '');
$dias_presencial = trim($_POST['dias_presencial'] ?? '');
$inicio_presencial = $_POST['inicio_presencial'] ?? null;
$fin_presencial = $_POST['fin_presencial'] ?? null;

$rnc_virtual = trim($_POST['rnc_virtual'] ?? '');
$aula_virtual = trim($_POST['aula_virtual'] ?? '');
$dias_virtual = trim($_POST['dias_virtual'] ?? '');
$inicio_virtual = $_POST['inicio_virtual'] ?? null;
$fin_virtual = $_POST['fin_virtual'] ?? null;

// validaciones
if (!$profesor_id || !$materia_id || !$ciclo_id) {
    header("Location: grupos_nrc.php?error=incomplete"); exit;
}

try {
    $pdo->beginTransaction();

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

    $pdo->commit();
    header("Location: grupos_nrc.php?success=1"); exit;
} catch (Exception $e) {
    $pdo->rollBack();
    header("Location: grupos_nrc.php?error=" . urlencode($e->getMessage())); exit;
}
?>