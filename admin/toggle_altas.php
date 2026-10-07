<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ADMIN') { exit; }

$data = json_decode(file_get_contents('php://input'), true);
if (isset($data['habilitadas'])) {
    $config_file = '../config.json';
    $config = file_exists($config_file) ? json_decode(file_get_contents($config_file), true) : [];
    $config['altas_habilitadas'] = (bool)$data['habilitadas'];
    file_put_contents($config_file, json_encode($config));
    echo json_encode(['success' => true]);
}
?>
