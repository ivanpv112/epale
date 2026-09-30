<?php
require 'db.php';
$stmt=$pdo->query('SELECT * FROM horarios LIMIT 1');
print_r($stmt->fetch(PDO::FETCH_ASSOC));
