<?php
// Define absolute paths
define('ROOT_PATH', dirname(__FILE__) . '/../');
define('INCLUDE_PATH', ROOT_PATH . 'includes/');
define('DASHBOARD_PATH', ROOT_PATH . 'dashboards/');
define('FORM_PATH', ROOT_PATH . 'forms/');
define('CONFIG_PATH', ROOT_PATH . 'config/');
define('CSS_PATH', ROOT_PATH . 'css/');

// Function to safely include files
function require_once_safe($path) {
    if (file_exists($path)) {
        require_once $path;
    } else {
        throw new Exception("File not found: $path");
    }
}
?>