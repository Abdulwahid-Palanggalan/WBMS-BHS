<?php
// Default Local XAMPP Credentials
$host = 'localhost';
$dbname = 'kibenes_ebirth';
$username = 'root';
$password = '';

// If we are on the server, the CI/CD pipeline creates this secret file
$creds_file = __DIR__ . '/db_credentials.php';
if (file_exists($creds_file)) {
    include($creds_file);
    $host = $db_host;
    $dbname = $db_name;
    $username = $db_user;
    $password = $db_pass;
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>