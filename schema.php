<?php
require 'db.php';
$stmt=$pdo->query('SHOW COLUMNS FROM exportaciones');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
