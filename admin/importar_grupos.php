<?php
session_start();
require '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ADMIN') { header("Location: ../index.php"); exit; }

// 2. ESCUDO CSRF: Bloquear peticiones de origen cruzado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Error de Seguridad Crítico: Token CSRF inválido o ausente. Petición bloqueada.");
    }
}

// --- FUNCIÓN PARA FORMATEAR LAS HORAS ---
function formatearHora($time) {
    $time = trim($time);
    if (empty($time) || strtoupper($time) === 'NA') return null;
    if (strpos($time, ':') !== false) {
        $parts = explode(':', $time);
        return str_pad($parts[0], 2, '0', STR_PAD_LEFT) . ':' . str_pad($parts[1], 2, '0', STR_PAD_RIGHT);
    }
    $time = preg_replace('/[^0-9]/', '', $time);
    if (strlen($time) == 3) {
        return '0' . substr($time, 0, 1) . ':' . substr($time, 1, 2);
    } elseif (strlen($time) >= 4) {
        return substr($time, 0, 2) . ':' . substr($time, 2, 2);
    }
    return null;
}

// --- FUNCIÓN PARA GENERAR UN NRC VIRTUAL ÚNICO ---
function generarNrcVirtual($pdo) {
    while (true) {
        // Genera un número aleatorio entre 800000 y 999999 para diferenciarlo de los reales
        $nrc_rand = mt_rand(800000, 999999);
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM grupos WHERE nrc = ?");
        $stmt->execute([$nrc_rand]);
        if ($stmt->fetchColumn() == 0) {
            return $nrc_rand;
        }
    }
}

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
        $stmt_insert_ciclo = $pdo->prepare("INSERT INTO ciclos (nombre, activo) VALUES (?, 1)");
        $stmt_profesor = $pdo->prepare("SELECT u.usuario_id FROM usuarios u WHERE u.codigo = ? AND u.rol = 'PROFESOR' LIMIT 1");

        // Consultas de Materias
        $stmt_buscar_materia = $pdo->prepare("SELECT materia_id FROM materias WHERE nombre = ? AND nivel = ? LIMIT 1");
        $stmt_insert_materia = $pdo->prepare("INSERT INTO materias (clave, nombre, nivel) VALUES (?, ?, ?)");
        $stmt_check_clave = $pdo->prepare("SELECT COUNT(*) FROM materias WHERE clave = ?");

        // Consultas de Inserción de Grupos
        $stmt_insert_grupo = $pdo->prepare("INSERT IGNORE INTO grupos (nrc, clave_siiau, clave_grupo, materia_id, ciclo_id, profesor_id, estado) VALUES (?, ?, ?, ?, ?, ?, 'ACTIVO')");
        $stmt_delete_horarios = $pdo->prepare("DELETE FROM horarios WHERE nrc = ?");
        $stmt_insert_horario = $pdo->prepare("INSERT INTO horarios (nrc, modalidad, dias_patron, hora_inicio, hora_fin, aula) VALUES (?, ?, ?, ?, ?, ?)");

        while (($datos = fgetcsv($file, 10000, ",")) !== FALSE) {
            if (empty(implode('', $datos))) { $fila_actual++; continue; }

            $datos = array_map(function($valor) { $v = trim($valor); return mb_detect_encoding($v, 'UTF-8', true) ? $v : mb_convert_encoding($v, 'UTF-8', 'ISO-8859-1'); }, $datos);

            // MAPEO DE COLUMNAS
            $nrc           = !empty($datos[0]) ? trim($datos[0]) : null;                        
            $clave_siiau   = !empty($datos[1]) ? strtoupper(trim($datos[1])) : null;            
            $idioma        = !empty($datos[2]) ? strtoupper(trim($datos[2])) : null;            
            $nivel         = !empty($datos[3]) ? trim($datos[3]) : null;                        
            $nom_grupo     = !empty($datos[4]) ? strtoupper(trim($datos[4])) : null;            
            $cod_profe     = !empty($datos[5]) ? trim($datos[5]) : null;                        
            $periodo       = !empty($datos[6]) ? strtoupper(trim($datos[6])) : null;            
            $aula          = !empty($datos[7]) ? strtoupper(trim($datos[7])) : null;            
            
            $hr_inicio     = formatearHora(!empty($datos[8]) ? $datos[8] : null);                        
            $hr_final      = formatearHora(!empty($datos[9]) ? $datos[9] : null);                        
            
            $presencial    = !empty($datos[10]) ? strtoupper(trim($datos[10])) : 'NA';          
            $virtual       = !empty($datos[11]) ? strtoupper(trim($datos[11])) : 'NA';          

            // VALIDACIONES DE CAMPOS
            if (empty($nrc)) throw new Exception("Fila $fila_actual, Columna A (NRC): El dato está vacío.");
            if (empty($clave_siiau)) throw new Exception("Fila $fila_actual, Columna B (CLAVE SIIAU): El dato está vacío para el NRC $nrc.");
            if (empty($idioma)) throw new Exception("Fila $fila_actual, Columna C (IDIOMA): El dato está vacío para el NRC $nrc.");
            if (empty($nivel)) throw new Exception("Fila $fila_actual, Columna D (NIVEL): El dato está vacío para el NRC $nrc.");
            if (empty($nom_grupo)) throw new Exception("Fila $fila_actual, Columna E (NOM_GRUPO): El dato está vacío para el NRC $nrc.");
            if (empty($cod_profe)) throw new Exception("Fila $fila_actual, Columna F (COD_PROFE): El dato está vacío para el NRC $nrc.");
            if (empty($periodo)) throw new Exception("Fila $fila_actual, Columna G (PERIODO): El dato está vacío para el NRC $nrc.");

            // 1. LÓGICA INTELIGENTE DE MATERIAS
            $stmt_buscar_materia->execute([$idioma, $nivel]);
            $materia_id = $stmt_buscar_materia->fetchColumn();

            if (!$materia_id) {
                $palabras = explode(' ', $idioma);
                $iniciales = '';
                foreach ($palabras as $p) { if (!empty($p)) { $iniciales .= substr($p, 0, 1); } }
                
                $base_clave = $iniciales . $nivel; 
                $clave_nueva = $base_clave;
                $contador = 1;
                
                while (true) {
                    $stmt_check_clave->execute([$clave_nueva]);
                    if ($stmt_check_clave->fetchColumn() == 0) { break; }
                    $clave_nueva = $base_clave . '-' . $contador;
                    $contador++;
                }

                $stmt_insert_materia->execute([$clave_nueva, $idioma, $nivel]);
                $materia_id = $pdo->lastInsertId();
            }

            // 2. LÓGICA INTELIGENTE DE CICLOS
            $stmt_ciclo->execute([$periodo]);
            $ciclo_id = $stmt_ciclo->fetchColumn();
            
            if (!$ciclo_id) { 
                $stmt_insert_ciclo->execute([$periodo]);
                $ciclo_id = $pdo->lastInsertId();
            }

            // 3. Buscar Profesor
            $stmt_profesor->execute([$cod_profe]);
            $profesor_id = $stmt_profesor->fetchColumn();
            if (!$profesor_id) throw new Exception("Fila $fila_actual, Columna F (COD_PROFE): El profesor con código '$cod_profe' no existe en el sistema.");

            // 4. INSERTAR GRUPO BASE SIEMPRE
            $stmt_insert_grupo->execute([$nrc, $clave_siiau, $nom_grupo, $materia_id, $ciclo_id, $profesor_id]);

            // 5. ASIGNAR HORARIOS SEGÚN MODALIDAD AL NRC BASE
            if ($presencial !== 'NA' && !empty($presencial)) {
                // Si tiene presencial, el NRC oficial se queda con la clase presencial
                $stmt_delete_horarios->execute([$nrc]);
                $stmt_insert_horario->execute([$nrc, 'PRESENCIAL', $presencial, $hr_inicio, $hr_final, $aula]);
            } elseif ($virtual !== 'NA' && !empty($virtual) && ($presencial === 'NA' || empty($presencial))) {
                // Si es 100% virtual, el NRC oficial se queda con la clase virtual
                $stmt_delete_horarios->execute([$nrc]);
                $aula_virtual = ($aula === 'NA' || empty($aula)) ? 'VIRTUAL' : 'VIRTUAL'; 
                $stmt_insert_horario->execute([$nrc, 'VIRTUAL', $virtual, $hr_inicio, $hr_final, $aula_virtual]);
            }

            // 6. CASO CLASE MIXTA: Generar Grupo Virtual con NRC Aleatorio
            if ($presencial !== 'NA' && !empty($presencial) && $virtual !== 'NA' && !empty($virtual)) {
                
                // Buscar si ya le habíamos generado un NRC virtual antes (para evitar duplicados al re-subir el CSV)
                $stmt_buscar_nrc_virtual = $pdo->prepare("
                    SELECT g.nrc FROM grupos g 
                    JOIN horarios h ON g.nrc = h.nrc 
                    WHERE g.clave_grupo = ? AND g.ciclo_id = ? AND h.modalidad = 'VIRTUAL' 
                    LIMIT 1
                ");
                $stmt_buscar_nrc_virtual->execute([$nom_grupo, $ciclo_id]);
                $existente = $stmt_buscar_nrc_virtual->fetchColumn();
                
                $nrc_virtual = ($existente && $existente != $nrc) ? $existente : generarNrcVirtual($pdo);
                
                // Insertamos el SEGUNDO grupo (El bloque virtual independiente)
                $stmt_insert_grupo->execute([$nrc_virtual, $clave_siiau, $nom_grupo, $materia_id, $ciclo_id, $profesor_id]);
                $stmt_delete_horarios->execute([$nrc_virtual]);
                
                $aula_virtual = ($aula === 'NA' || empty($aula)) ? 'VIRTUAL' : 'VIRTUAL'; 
                $stmt_insert_horario->execute([$nrc_virtual, 'VIRTUAL', $virtual, $hr_inicio, $hr_final, $aula_virtual]);
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
        header("Location: vista_csv_grupos.php?msg=error_datos&detalle=" . urlencode($e->getMessage())); 
        exit;
    }
} else { 
    header("Location: vista_csv_grupos.php"); 
    exit; 
}
?>
