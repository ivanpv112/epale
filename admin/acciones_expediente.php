<?php
session_start();
require '../db.php';
require_once '../security.php';

validar_csrf_estricto('POST');

// SEGURIDAD
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ADMIN') { 
    header("Location: ../index.php"); exit; 
}

// Función auxiliar para obtener el nombre completo rápidamente para los logs
function obtener_nombre_afectado(PDO $pdo, int $id) {
    $stmt = $pdo->prepare("SELECT CONCAT(nombre, ' ', apellido_paterno) as nombre_completo, codigo FROM usuarios WHERE usuario_id = ?");
    $stmt->execute([$id]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    return $res ? $res['nombre_completo'] . ($res['codigo'] ? ' ('.$res['codigo'].')' : '') : "Usuario ID: $id";
}

// 1. PROCESAR BORRADO DE FOTO DE PERFIL (MÉTODO GET)
if (isset($_GET['borrar_foto']) && $_GET['borrar_foto'] == 1 && isset($_GET['id'])) {
    // ESCUDO CSRF
    if (empty($_GET['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'])) {
        die("Error de Seguridad Crítico: Token CSRF inválido. Petición bloqueada.");
    }

    $usuario_id = $_GET['id'];
    $stmt_foto = $pdo->prepare("SELECT foto_perfil FROM usuarios WHERE usuario_id = ?");
    $stmt_foto->execute([$usuario_id]);
    $foto_actual = $stmt_foto->fetchColumn();
    
    if ($foto_actual && file_exists("../img/perfiles/" . $foto_actual)) {
        unlink("../img/perfiles/" . $foto_actual); 
    }
    
    $pdo->prepare("UPDATE usuarios SET foto_perfil = NULL WHERE usuario_id = ?")->execute([$usuario_id]);
    
    // LOG HISTORIAL
    $afectado = obtener_nombre_afectado($pdo, $usuario_id);
    registrar_historial($pdo, $_SESSION['user_id'], 'Edición', 'Usuarios', 'Foto de perfil eliminada', $afectado, "Se eliminó manualmente la foto de perfil del expediente del usuario.");
    
    header("Location: ver_expediente_alumno.php?id=" . $usuario_id . "&exito=foto"); exit;
}

// ESCUDO CSRF GLOBAL PARA TODAS LAS PETICIONES POST SIGUIENTES
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Error de Seguridad Crítico: Token CSRF inválido o ausente. Petición bloqueada.");
    }
}

// 2. PROCESAR ACTUALIZACIÓN MANUAL DE CALIFICACIONES
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['actualizar_calificaciones'])) {
    $usuario_id = $_POST['usuario_id']; // Recibimos el ID para saber a dónde regresar
    $insc_id = $_POST['inscripcion_id'];
    
    $detalles_calificaciones = [];
    
    if (isset($_POST['calificaciones']) && is_array($_POST['calificaciones'])) {
        foreach ($_POST['calificaciones'] as $codigo_examen => $puntaje) {
            $check = $pdo->prepare("SELECT calificacion_id, puntaje FROM calificaciones WHERE inscripcion_id = ? AND tipo_examen = ?");
            $check->execute([$insc_id, $codigo_examen]);
            $old_row = $check->fetch(PDO::FETCH_ASSOC);
            $exists = $old_row ? $old_row['calificacion_id'] : false;
            $old_puntaje = $old_row ? floatval($old_row['puntaje']) : null;

            $puntaje_str = (string)$puntaje;
            if (trim($puntaje_str) === '') {
                if ($exists) {
                    $pdo->prepare("DELETE FROM calificaciones WHERE calificacion_id = ?")->execute([$exists]);
                    $detalles_calificaciones[] = "$codigo_examen: $old_puntaje pts → N/A";
                }
            } else {
                $puntaje_val = floatval($puntaje);
                if ($exists) {
                    if ($old_puntaje !== $puntaje_val) {
                        $pdo->prepare("UPDATE calificaciones SET puntaje = ? WHERE calificacion_id = ?")->execute([$puntaje_val, $exists]);
                        $detalles_calificaciones[] = "$codigo_examen: $old_puntaje pts → $puntaje_val pts";
                    }
                } else {
                    $pdo->prepare("INSERT INTO calificaciones (inscripcion_id, tipo_examen, puntaje) VALUES (?, ?, ?)")->execute([$insc_id, $codigo_examen, $puntaje_val]);
                    $detalles_calificaciones[] = "$codigo_examen: N/A → $puntaje_val pts";
                }
            }
        }
    }
    
    // LOG HISTORIAL
    if (!empty($detalles_calificaciones)) {
        $afectado = obtener_nombre_afectado($pdo, $usuario_id);
        $detalle_final = "Se actualizaron manualmente los registros de calificaciones del estudiante desde su expediente (" . implode(", ", $detalles_calificaciones) . ").";
        registrar_historial($pdo, $_SESSION['user_id'], 'Calificación', 'Calificaciones', 'Modificación de calificaciones', $afectado, $detalle_final);
    }
    
    header("Location: ver_expediente_alumno.php?id=" . $usuario_id . "&exito=calificaciones"); exit;
}

// 3. PROCESAR REGISTRO DE CERTIFICACIONES
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['guardar_certificacion'])) {
    $usuario_id = $_POST['usuario_id'];
    $alum_id_cert = $_POST['alumno_id_cert'];
    $idioma_cert = $_POST['idioma_cert'];
    $nivel_cert = strtoupper(trim($_POST['nivel_cert'] ?? ''));
    $puntaje_cert = trim($_POST['puntaje_cert'] ?? '');
    $periodo_cert = strtoupper(trim($_POST['periodo_cert'] ?? ''));
    $fecha_cert = !empty($_POST['fecha_cert']) ? $_POST['fecha_cert'] : null;
    
    $stmt_chk = $pdo->prepare("SELECT certificacion_id, nivel_obtenido, puntaje FROM certificaciones WHERE alumno_id = ? AND idioma = ?");
    $stmt_chk->execute([$alum_id_cert, $idioma_cert]);
    $old_cert = $stmt_chk->fetch(PDO::FETCH_ASSOC);
    
    if ($old_cert) {
        $exists = $old_cert['certificacion_id'];
        $pdo->prepare("UPDATE certificaciones SET nivel_obtenido = ?, puntaje = ?, periodo = ?, fecha_aplicacion = ? WHERE certificacion_id = ?")->execute([$nivel_cert, $puntaje_cert, $periodo_cert, $fecha_cert, $exists]);
        
        $cambios = [];
        if ($old_cert['nivel_obtenido'] !== $nivel_cert) $cambios[] = "Nivel: {$old_cert['nivel_obtenido']} → $nivel_cert";
        if ($old_cert['puntaje'] != $puntaje_cert) $cambios[] = "Puntaje: {$old_cert['puntaje']} → $puntaje_cert";
        
        $detalle_str = !empty($cambios) ? " (" . implode(", ", $cambios) . ")" : "";
        $detalle = "Se actualizó la certificación de $idioma_cert$detalle_str en el expediente del estudiante.";
    } else {
        $pdo->prepare("INSERT INTO certificaciones (alumno_id, idioma, nivel_obtenido, puntaje, periodo, fecha_aplicacion) VALUES (?, ?, ?, ?, ?, ?)")->execute([$alum_id_cert, $idioma_cert, $nivel_cert, $puntaje_cert, $periodo_cert, $fecha_cert]);
        $detalle = "Se agregó una nueva certificación de $idioma_cert (Nivel: $nivel_cert, Puntaje: $puntaje_cert) al expediente del estudiante.";
    }
    
    // LOG HISTORIAL
    $afectado = obtener_nombre_afectado($pdo, $usuario_id);
    registrar_historial($pdo, $_SESSION['user_id'], 'Edición', 'Archivos', 'Registro de certificación', $afectado, $detalle);
    
    header("Location: ver_expediente_alumno.php?id=" . $usuario_id . "&exito=certificacion"); exit;
}

// 4. PROCESAR GUARDADO/EDICIÓN DE EXAMEN DIAGNÓSTICO
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['guardar_diagnostico'])) {
    $usuario_id = $_POST['usuario_id'];
    $examen_id = $_POST['examen_id'] ?? '';
    $alum_id = $_POST['alumno_id_diag'];
    $idioma = trim($_POST['idioma_diag'] ?? '');
    $periodo = strtoupper(trim($_POST['periodo_diag'] ?? ''));
    $nivel = intval($_POST['nivel_diag'] ?? 0);
    $calificacion = strtoupper(trim($_POST['calif_diag'] ?? ''));
    $fecha = $_POST['fecha_diag'];

    if (!empty($examen_id)) {
        $stmt_chk_d = $pdo->prepare("SELECT nivel_asignado, calificacion_texto FROM examenes_diagnosticos WHERE examen_id = ?");
        $stmt_chk_d->execute([$examen_id]);
        $old_diag = $stmt_chk_d->fetch(PDO::FETCH_ASSOC);

        $pdo->prepare("UPDATE examenes_diagnosticos SET idioma=?, periodo=?, nivel_asignado=?, calificacion_texto=?, fecha_realizacion=? WHERE examen_id=?")
            ->execute([$idioma, $periodo, $nivel, $calificacion, $fecha, $examen_id]);
            
        $cambios = [];
        if ($old_diag && $old_diag['nivel_asignado'] != $nivel) $cambios[] = "Nivel: {$old_diag['nivel_asignado']} → $nivel";
        if ($old_diag && $old_diag['calificacion_texto'] != $calificacion) $cambios[] = "Puntaje: {$old_diag['calificacion_texto']} → $calificacion";
        
        $detalle_str = !empty($cambios) ? " (" . implode(", ", $cambios) . ")" : "";
        $detalle = "Se editó el resultado del examen diagnóstico de $idioma$detalle_str en el expediente del estudiante.";
    } else {
        $pdo->prepare("INSERT INTO examenes_diagnosticos (alumno_id, idioma, periodo, nivel_asignado, calificacion_texto, fecha_realizacion) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([$alum_id, $idioma, $periodo, $nivel, $calificacion, $fecha]);
        $detalle = "Se registró un nuevo examen diagnóstico de $idioma (Nivel: $nivel, Puntaje: $calificacion) en el expediente del estudiante.";
    }
    
    // LOG HISTORIAL
    $afectado = obtener_nombre_afectado($pdo, $usuario_id);
    registrar_historial($pdo, $_SESSION['user_id'], 'Nivel', 'Usuarios', 'Examen diagnóstico guardado', $afectado, $detalle);
    
    header("Location: ver_expediente_alumno.php?id=" . $usuario_id . "&exito=diagnostico"); exit;
}

// Si se accede a este archivo sin mandar datos, te regresa a la lista
header("Location: expedientes.php"); exit;
?>