<?php
session_start();
requerir_rol('ADMIN');

$data = json_decode(file_get_contents('php://input'), true);
if (isset($data['habilitadas'])) {
    config_set('bajas_habilitadas', (bool)$data['habilitadas']);
    echo json_encode(['success' => true]);
}
?>
