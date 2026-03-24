<?php
require_once 'index.php'; // This might not work if index.php expects certain GET params
// Let's just copy the PDO part
$host = 'localhost';
$db   = 'vitalliance';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
     $sql = "SELECT TABLE_NAME, COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE COLUMN_NAME LIKE '%tag%' AND TABLE_SCHEMA = DATABASE()";
     $stmt = $pdo->query($sql);
     $results = $stmt->fetchAll();
     echo "FOUND TAG COLUMNS:\n";
     print_r($results);

     $sql = "SHOW TABLES";
     $stmt = $pdo->query($sql);
     $tables = $stmt->fetchAll();
     echo "\nALL TABLES:\n";
     print_r($tables);

} catch (\PDOException $e) {
     echo "ERROR: " . $e->getMessage();
}
