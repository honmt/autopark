<?php
$config = require __DIR__ . '/config.php';
$db = $config['db'];
$dsn = "mysql:host={$db['host']};dbname={$db['name']};charset={$db['charset']}";
try {
$pdo = new PDO($dsn, $db['user'], $db['pass'], [
PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
} catch (PDOException $e) {
die('DB connect error: '. $e->getMessage());
}