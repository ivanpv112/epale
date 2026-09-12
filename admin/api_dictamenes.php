<?php
session_start();
require '../db.php';
require_once '../security.php';

validar_csrf_estricto('POST');

header('Content-Type: application/json');

// Seguridad: Solo peticiones POST y rol de ADMIN
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido.']); exit;
}

if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ADMIN') {
    echo json_encode(['success' => false, 'error' => 'Acceso denegado.']); exit;
}

$action = $_POST['action'] ?? '';

// Validación estricta del Token CSRF
$token_post = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || empty($token_post) || !hash_equals($_SESSION['csrf_token'], $token_post)) {
    echo json_encode(['success' => false, 'error' => 'Token de seguridad inválido (CSRF). Recarga la página.']); exit;
}

// Limpiar filas corruptas previas (como la de "N" y "Nombre de la materia") por seguridad
try {
    $pdo->exec("DELETE FROM dictamenes_estudiantes WHERE num_dictamen = 'N' OR nombre_materia = 'Nombre de la materia'");
} catch(Exception $e) {}


// Función para corregir codificación de Excel (ISO-8859-1 / Windows-1252) a UTF-8 (BD)
function limpiarTextoExcel(string $cadena) {
    if (empty($cadena)) return '';
    // Detecta la codificación y la fuerza a UTF-8 para que las tildes no corten la cadena
    return mb_convert_encoding(trim($cadena), 'UTF-8', 'UTF-8, ISO-8859-1, WINDOWS-1252');
}

// =========================================================
// ACCIÓN 1: PROCESAR EL CSV DE ALUMNOS Y DICTÁMENES
// =========================================================
if ($action === 'upload_csv') {
    if (!isset($_FILES['archivo_csv']) || $_FILES['archivo_csv']['error'] != 0) {
        echo json_encode(['success' => false, 'error' => 'Error al subir el archivo CSV.']); exit;
    }

    $ignorar_cabecera = isset($_POST['ignorar_cabecera']) && $_POST['ignorar_cabecera'] == '1';
    
    $file = $_FILES['archivo_csv']['tmp_name'];
    $handle = fopen($file, "r");
    
    if ($handle !== FALSE) {
        $insertados = 0;
        
        $sql = "INSERT IGNORE INTO dictamenes_estudiantes 
                (codigo_alumno, nombre_alumno, carrera, ciclo_acreditacion, clave_materia, nombre_materia, num_dictamen) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);

        $row_count = 0;
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $row_count++;
            
            // Ignorar la primera fila explícitamente si el checkbox está marcado
            if ($row_count === 1 && $ignorar_cabecera) {
                continue;
            }

            // Asegurarse de que la línea tenga al menos 7 columnas
            if (count($data) >= 7) {
                // Pasamos cada dato por la función que limpia y convierte a UTF-8
                $codigo       = limpiarTextoExcel($data[0]);
                $nombre       = limpiarTextoExcel($data[1]);
                $carrera      = limpiarTextoExcel($data[2]);
                $ciclo        = limpiarTextoExcel($data[3]);
                $clave_mat    = limpiarTextoExcel($data[4]);
                $nom_mat      = limpiarTextoExcel($data[5]);
                $num_dictamen = limpiarTextoExcel($data[6]);

                if (!empty($codigo) && !empty($num_dictamen) && strtoupper($codigo) !== 'CODIGO') {
                    $stmt->execute([$codigo, $nombre, $carrera, $ciclo, $clave_mat, $nom_mat, $num_dictamen]);
                    if ($stmt->rowCount() > 0) { 
                        $insertados++; 
                    }
                }
            }
        }
        fclose($handle);
        echo json_encode(['success' => true, 'message' => "Se procesó el archivo. $insertados nuevos registros insertados (los duplicados fueron ignorados)."]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No se pudo leer el archivo CSV. Verifique el formato.']);
    }
    exit;
}

// =========================================================
// ACCIÓN 2: SUBIR PDFs AL SERVIDOR LOCAL
// =========================================================
if ($action === 'upload_pdfs') {
    $upload_dir = '../uploads/dictamenes/';
    
    if (!is_dir($upload_dir)) { mkdir($upload_dir, 0755, true); }

    $subidos = 0;
    $errores = 0;

    if (isset($_FILES['pdfs'])) {
        $total_archivos = count($_FILES['pdfs']['name']);
        
        for ($i = 0; $i < $total_archivos; $i++) {
            $tmp_name = $_FILES['pdfs']['tmp_name'][$i];
            $nombre_original = basename($_FILES['pdfs']['name'][$i]);
            
            $ext = strtolower(pathinfo($nombre_original, PATHINFO_EXTENSION));
            $mime = mime_content_type($tmp_name);
            
            if ($ext === 'pdf' && $mime === 'application/pdf') {
                $nombre_seguro = preg_replace('/[^a-zA-Z0-9.\- _]/', '', $nombre_original);
                $ruta_destino = $upload_dir . $nombre_seguro;
                
                if (move_uploaded_file($tmp_name, $ruta_destino)) {
                    $subidos++;
                } else {
                    $errores++;
                }
            } else {
                $errores++;
            }
        }
    }
    
    if ($subidos > 0) {
        echo json_encode(['success' => true, 'message' => "Se subieron $subidos archivos PDF exitosamente." . ($errores > 0 ? " ($errores omitidos por formato inválido)." : "")]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No se pudo subir ningún PDF válido.']);
    }
    exit;
}

// =========================================================
// ACCIÓN 3: OBTENER EL ESTATUS DEL SEMÁFORO (MATCHING)
// =========================================================
if ($action === 'get_status') {
    try {
        $sql = "SELECT num_dictamen, MAX(nombre_materia) as materia, MAX(clave_materia) as clave_materia, COUNT(codigo_alumno) as total_alumnos 
                FROM dictamenes_estudiantes 
                GROUP BY num_dictamen 
                ORDER BY num_dictamen ASC";
        $dictamenes = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        $upload_dir = '../uploads/dictamenes/';
        $archivos_fisicos = [];
        if (is_dir($upload_dir)) {
            $archivos_fisicos = array_diff(scandir($upload_dir), array('.', '..'));
        }

        $resultados = [];
        foreach ($dictamenes as $d) {
            $pdf_encontrado = false;
            
            // LA MAGIA: Convertimos III/547/2026 a III.547.2026
            $llave_busqueda = str_replace('/', '.', $d['num_dictamen']);

            foreach ($archivos_fisicos as $archivo) {
                if (stripos($archivo, $llave_busqueda) !== false) {
                    $pdf_encontrado = true;
                    break;
                }
            }

            $resultados[] = [
                'num_dictamen'  => $d['num_dictamen'],
                'materia'       => $d['materia'],
                'clave_materia' => $d['clave_materia'],
                'total_alumnos' => $d['total_alumnos'],
                'pdf_existe'    => $pdf_encontrado
            ];
        }

        echo json_encode($resultados);
    } catch(PDOException $e) {
        error_log("Error PDO en api_dictamenes: " . $e->getMessage());
        echo json_encode(['error' => 'Error al conectar con la base de datos.']);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Acción no válida.']);