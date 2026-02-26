<?php
session_start();
session_destroy(); // Destruye todos los datos de la sesión
header("Location: index.php"); // Redirige al login
exit;
?>