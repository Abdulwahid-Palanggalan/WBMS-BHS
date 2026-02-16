<?php
// Support for Cloud Hosts (Railway/Render/Pantheon), fallback to XAMPP for local dev
$host = getenv('DB_HOST') ?: (isset($_ENV['DB_HOST']) ? $_ENV['DB_HOST'] : 'localhost');
$dbname = getenv('DB_NAME') ?: (isset($_ENV['DB_NAME']) ? $_ENV['DB_NAME'] : 'kibenes_ebirth');
$username = getenv('DB_USER') ?: (isset($_ENV['DB_USER']) ? $_ENV['DB_USER'] : 'root');
$password = getenv('DB_PASS') ?: (isset($_ENV['DB_PASSWORD']) ? $_ENV['DB_PASSWORD'] : '');

// Pantheon special handling if needed
if (isset($_ENV['PANTHEON_ENVIRONMENT'])) {
    $host = $_ENV['DB_HOST'];
    $dbname = $_ENV['DB_NAME'];
    $username = $_ENV['DB_USER'];
    $password = $_ENV['DB_PASSWORD'];
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>