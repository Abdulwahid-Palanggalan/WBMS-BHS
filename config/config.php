<?php
// config/config.php

// Define root directory
define('ROOT_PATH', dirname(__DIR__));

// Define include paths
define('INCLUDE_PATH', ROOT_PATH . '/includes/');
define('CONFIG_PATH', ROOT_PATH . '/config/');
define('DASHBOARD_PATH', ROOT_PATH . '/dashboards/');
define('FORM_PATH', ROOT_PATH . '/forms/');
define('CSS_PATH', ROOT_PATH . '/css/');

// Set up error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database configuration
require_once CONFIG_PATH . 'database.php';

// Function to get absolute URL
function getBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $script = $_SERVER['SCRIPT_NAME'];
    
    // Remove the script name from the path
    $path = dirname($script);
    
    // Handle different environments
    if ($path === '/' || $path === '\\') {
        $path = '';
    }
    
    return $protocol . "://" . $host . $path;
}

// Set global base URL
if (!isset($GLOBALS['base_url'])) {
    $GLOBALS['base_url'] = getBaseUrl();
}

// INCLUDE FUNCTIONS.PHP BEFORE AUTH.PHP - IMPORTANT!
require_once INCLUDE_PATH . 'functions.php';

// Include auth functions
require_once INCLUDE_PATH . 'auth.php';

// Set up autoloading for other includes
spl_autoload_register(function ($class_name) {
    $file = INCLUDE_PATH . $class_name . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});
?>