<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

redirectIfNotLoggedIn();

$role = getUserRole();

// mapping of roles to dashboard files
$roleDashboards = [
    'admin'   => 'dashboards/admin.php',
    'midwife' => 'dashboards/midwife.php',
    'bhw'     => 'dashboards/bhw.php',
    'bns'     => 'dashboards/bns.php',
    'mother'  => 'dashboards/mother.php'
];

// check kung may dashboard for this role
if (isset($roleDashboards[$role])) {
    $dashboardFile = __DIR__ . '/' . $roleDashboards[$role];

    if (file_exists($dashboardFile)) {
        include_once $dashboardFile;
        exit;
    }
}

// Check if mother user has a profile
if ($_SESSION['role'] === 'mother') {
    $userId = $_SESSION['user_id'];
    
    $checkMother = $pdo->prepare("SELECT id FROM mothers WHERE user_id = ?");
    $checkMother->execute([$userId]);
    $motherExists = $checkMother->fetch(PDO::FETCH_ASSOC);
    
    if (!$motherExists) {
        // Redirect to profile creation
        header("Location: forms/mother_self_registration.php");
        exit();
    }
}

// fallback kung walang dashboard
echo '<h3>Dashboard not found for your role: ' . htmlspecialchars($role) . '</h3>';
error_log("Dashboard not found for role: $role");
