<?php
session_start();
require 'security.php';

validar_csrf_estricto('GET');

session_destroy(); // Destruye todos los datos de la sesión
header("Location: index.php"); // Redirige al login
exit;
?>
