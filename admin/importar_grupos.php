<?php
session_start();
require '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ADMIN') { header("Location: ../index.php"); exit; }

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["archivo_csv"])) {
    $archivo = $_FILES["archivo_csv"]["tmp_name"];
    if ($_FILES["archivo_csv"]["size"] == 0) { header("Location: vista_csv_grupos.php?msg=error_file"); exit; }

    $file = fopen($archivo, "r");
    $fila_actual = 1;
    if (isset($_POST['ignorar_cabecera']) && $_POST['ignorar_cabecera'] == '1') { fgetcsv($file); $fila_actual = 2; }

    try {
        $pdo->beginTransaction(); 
        $registros_exitosos = 0;

        // Consultas de validación
        $stmt_ciclo = $pdo->prepare("SELECT ciclo_id FROM ciclos WHERE nombre = ? LIMIT 1");
        $stmt_materia = $pdo->prepare("SELECT materia_id FROM materias WHERE clave = ? LIMIT 1");
        $stmt_profesor = $pdo->prepare("SELECT u.usuario_id FROM usuarios u WHERE u.codigo = ? AND u.rol = 'PROFESOR' LIMIT 1");

        // Consultas de Inserción
        $stmt_insert_grupo = $pdo->prepare("INSERT IGNORE INTO grupos (nrc, clave_grupo, materia_id, ciclo_id, profesor_id, estado) VALUES (?, ?, ?, ?, ?, 'ACTIVO')");
        $stmt_delete_horarios = $pdo->prepare("DELETE FROM horarios WHERE nrc = ?"); // Limpia horarios anteriores si se resube el mismo NRC
        $stmt_insert_horario = $pdo->prepare("INSERT INTO horarios (nrc, modalidad, dias, hora_inicio, hora_fin, aula) VALUES (?, ?, ?, ?, ?, ?)");

        while (($datos = fgetcsv($file, 10000, ",")) !== FALSE) {
            $datos = array_map(function($valor) { $v = trim($valor); return mb_detect_encoding($v, 'UTF-8', true) ? $v : mb_convert_encoding($v, 'UTF-8', 'ISO-8859-1'); }, $datos);

            // Mapeo exacto a las 12 columnas acordadas
            $nrc           = !empty($datos[0]) ? trim($datos[0]) : null;                        // A
            $cod_profe     = !empty($datos[1]) ? trim($datos[1]) : null;                        // B
            $nom_grupo     = !empty($datos[2]) ? strtoupper(trim($datos[2])) : null;            // C (Ej. E.13.1.B)
            $clave_idioma  = !empty($datos[3]) ? strtoupper(trim($datos[3])) : null;            // D (Ej. CU182)
            $periodo       = !empty($datos[4]) ? strtoupper(trim($datos[4])) : null;            // E
            $nivel         = !empty($datos[5]) ? trim($datos[5]) : null;                        // F
            $aula          = !empty($datos[6]) ? strtoupper(trim($datos[6])) : null;            // G
            $hr_inicio     = !empty($datos[7]) ? trim($datos[7]) : null;                        // H
            $hr_final      = !empty($datos[8]) ? trim($datos[8]) : null;                        // I
            $dias          = !empty($datos[9]) ? strtoupper(trim($datos[9])) : null;            // J
            $presencial    = !empty($datos[10]) ? strtoupper(trim($datos[10])) : 'NA';          // K
            $virtual       = !empty($datos[11]) ? strtoupper(trim($datos[11])) : 'NA';          // L

            if (empty($nrc) || empty($periodo) || empty($clave_idioma) || empty($cod_profe)) { 
                $fila_actual++; continue; 
            }

            // 1. Buscar Ciclo
            $stmt_ciclo->execute([$periodo]);
            $ciclo_id = $stmt_ciclo->fetchColumn();
            if (!$ciclo_id) { $pdo->rollBack(); fclose($file); header("Location: vista_csv_grupos.php?msg=error_foraneo&fila=$fila_actual&detalle=" . urlencode("El ciclo '$periodo' no existe")); exit; }

            // 2. Buscar Materia por su Clave SIIAU
            $stmt_materia->execute([$clave_idioma]);
            $materia_id = $stmt_materia->fetchColumn();
            if (!$materia_id) { $pdo->rollBack(); fclose($file); header("Location: vista_csv_grupos.php?msg=error_foraneo&fila=$fila_actual&detalle=" . urlencode("La clave de materia '$clave_idioma' no existe")); exit; }

            // 3. Buscar Profesor
            $stmt_profesor->execute([$cod_profe]);
            $profesor_id = $stmt_profesor->fetchColumn();
            if (!$profesor_id) { $pdo->rollBack(); fclose($file); header("Location: vista_csv_grupos.php?msg=error_foraneo&fila=$fila_actual&detalle=" . urlencode("El profesor '$cod_profe' no existe")); exit; }

            // INSERTAR GRUPO (Usamos NOM_GRUPO como la clave_grupo interna)
            $stmt_insert_grupo->execute([$nrc, $nom_grupo, $materia_id, $ciclo_id, $profesor_id]);

            // LIMPIAR HORARIOS PREVIOS DEL NRC (En caso de que estén re-subiendo el Excel para corregir)
            $stmt_delete_horarios->execute([$nrc]);

            // INSERTAR HORARIO PRESENCIAL (Si aplica)
            if ($presencial !== 'NA') {
                $stmt_insert_horario->execute([$nrc, 'PRESENCIAL', $presencial, $hr_inicio, $hr_final, $aula]);
            }

            // INSERTAR HORARIO VIRTUAL (Si aplica)
            if ($virtual !== 'NA') {
                // Si es virtual, usualmente el aula se maneja como "VIRTUAL" o se deja el enlace, aquí lo ponemos como VIRTUAL.
                $aula_virtual = ($aula === 'NA' || empty($aula)) ? 'VIRTUAL' : 'VIRTUAL'; 
                $stmt_insert_horario->execute([$nrc, 'VIRTUAL', $virtual, $hr_inicio, $hr_final, $aula_virtual]);
            }

            $registros_exitosos++; 
            $fila_actual++;
        }
        
        $pdo->commit(); fclose($file); 
        header("Location: vista_csv_grupos.php?msg=ok_grupos&total=$registros_exitosos"); 
        exit;
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack(); 
        if (isset($file)) fclose($file); 
        header("Location: vista_csv_grupos.php?msg=error_db&fila=$fila_actual"); 
        exit;
    }
} else { 
    header("Location: vista_csv_grupos.php"); 
    exit; 
}
?>