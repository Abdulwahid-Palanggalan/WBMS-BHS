<?php
// Enable Error Reporting for Debugging (Temporary)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Default Local XAMPP Credentials
$host = 'localhost';
$dbname = 'kibenes_ebirth';
$username = 'root';
$password = '';

// If we are on the server, the CI/CD pipeline creates this secret file
$creds_file = __DIR__ . '/db_credentials.php';
if (file_exists($creds_file)) {
    include($creds_file);
    // Use the variables if they are set in the included file
    if (isset($db_host)) $host = $db_host;
    if (isset($db_name)) $dbname = $db_name;
    if (isset($db_user)) $username = $db_user;
    if (isset($db_pass)) $password = $db_pass;
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "<h1>Database Connection Error</h1>";
    echo "<p>" . $e->getMessage() . "</p>";
    exit;
}

// Centralized Base URL calculation
if (!isset($GLOBALS['base_url'])) {
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $scriptName = $_SERVER['SCRIPT_NAME'];
    $path = dirname($scriptName);
    $path = str_replace('\\', '/', $path);
    
    // Climb up from known subdirectories
    $markers = ['/dashboards', '/forms', '/ajax', '/includes', '/config'];
    foreach ($markers as $marker) {
        if (($pos = strpos($path, $marker)) !== false) {
            $path = substr($path, 0, $pos);
            break;
        }
    }
    
    $path = ($path === '/') ? '' : rtrim($path, '/');
    $GLOBALS['base_url'] = $protocol . "://" . $host . $path;
}
?>