<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../db.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ADMIN') { header("Location: ../index.php"); exit; }

$action = $_POST['action'] ?? 'create';
$profesor_id = $_POST['profesor_id'] ?? null;
$materia_id = $_POST['materia_id'] ?? null;
$ciclo_id = $_POST['ciclo_id'] ?? null;
$cupo = isset($_POST['cupo']) ? intval($_POST['cupo']) : 30; 
$edicion_total = isset($_POST['edicion_total']) ? 1 : 0;

$rnc_presencial = trim($_POST['rnc_presencial'] ?? ''); $aula_presencial = trim($_POST['aula_presencial'] ?? ''); $dias_presencial = trim($_POST['dias_presencial'] ?? ''); $inicio_presencial = !empty($_POST['inicio_presencial']) ? $_POST['inicio_presencial'] : null; $fin_presencial = !empty($_POST['fin_presencial']) ? $_POST['fin_presencial'] : null;
$rnc_virtual = trim($_POST['rnc_virtual'] ?? ''); $aula_virtual = trim($_POST['aula_virtual'] ?? ''); $dias_virtual = trim($_POST['dias_virtual'] ?? ''); $inicio_virtual = !empty($_POST['inicio_virtual']) ? $_POST['inicio_virtual'] : null; $fin_virtual = !empty($_POST['fin_virtual']) ? $_POST['fin_virtual'] : null;

if (!$profesor_id || !$materia_id || !$ciclo_id) { header("Location: grupos_nrc.php?error=Faltan campos"); exit; }
if ($rnc_presencial === '' && $rnc_virtual === '') { header("Location: grupos_nrc.php?error=Escribe al menos un NRC"); exit; }

try {
    $pdo->beginTransaction();

    if ($action === 'create') {
        $clave_grupo = uniqid('grp_'); // <--- MAGIA: CREA EL ID ÚNICO DEL GRUPO COMPLETO
        
        $insertGrupo = $pdo->prepare("INSERT INTO grupos (nrc, materia_id, profesor_id, ciclo_id, cupo, edicion_total, clave_grupo) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $insertHorario = $pdo->prepare("INSERT INTO horarios (nrc, dias_patron, hora_inicio, hora_fin, modalidad, aula) VALUES (?, ?, ?, ?, ?, ?)");

        if ($rnc_presencial !== '') {
            $check = $pdo->prepare("SELECT COUNT(*) FROM grupos WHERE nrc = ?"); $check->execute([$rnc_presencial]);
            if($check->fetchColumn() > 0) throw new Exception("El NRC $rnc_presencial ya existe.");
            $insertGrupo->execute([$rnc_presencial, $materia_id, $profesor_id, $ciclo_id, $cupo, $edicion_total, $clave_grupo]);
            $insertHorario->execute([$rnc_presencial, $dias_presencial, $inicio_presencial, $fin_presencial, 'PRESENCIAL', $aula_presencial]);
        }

        if ($rnc_virtual !== '') {
            $check = $pdo->prepare("SELECT COUNT(*) FROM grupos WHERE nrc = ?"); $check->execute([$rnc_virtual]);
            if($check->fetchColumn() > 0) throw new Exception("El NRC $rnc_virtual ya existe.");
            $insertGrupo->execute([$rnc_virtual, $materia_id, $profesor_id, $ciclo_id, $cupo, $edicion_total, $clave_grupo]);
            $insertHorario->execute([$rnc_virtual, $dias_virtual, $inicio_virtual, $fin_virtual, 'VIRTUAL', $aula_virtual]);
        }
    }
    $pdo->commit();
    
    if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'gestionar_grupo.php') !== false) { header("Location: gestionar_grupo.php?clave=$clave_grupo"); } 
    else { header("Location: grupos_nrc.php?success=1"); }
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    header("Location: grupos_nrc.php?error=" . urlencode($e->getMessage())); exit;
}
?>
