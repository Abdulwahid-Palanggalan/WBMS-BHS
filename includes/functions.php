<?php
// includes/functions.php

// Use absolute path for database configuration
require_once dirname(__FILE__) . '/../config/database.php';

function registerUser($userData) {
    global $pdo;
    
    $hashedPassword = password_hash($userData['password'], PASSWORD_DEFAULT);
    
    $sql = "INSERT INTO users (username, email, password, role, first_name, last_name, phone, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        $userData['username'],
        $userData['email'],
        $hashedPassword,
        $userData['role'],
        $userData['first_name'],
        $userData['last_name'],
        $userData['phone'],
        $userData['role'] === 'mother' ? 'active' : 'pending'
    ]);
}

function loginUser($username, $password) {
    global $pdo;
    
    $sql = "SELECT * FROM users WHERE username = ? OR email = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password'])) {
        if ($user['status'] === 'active') {
            return $user;
        }
    }
    
    return false;
}

function getDashboardByRole($role) {
    switch ($role) {
        case 'admin':
            return 'dashboards/admin.php';
        case 'midwife':
            return 'dashboards/midwife.php';
        case 'bhw':
            return 'dashboards/bhw.php';
        case 'bns':
            return 'dashboards/bns.php';
        case 'mother':
            return 'dashboards/mother.php';
        default:
            return 'login.php';
    }
}

function logActivity($userId, $activity) {
    global $pdo;
    
    $ip = $_SERVER['REMOTE_ADDR'];
    $sql = "INSERT INTO system_activities (user_id, activity, ip_address) VALUES (?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId, $activity, $ip]);
}

// Helper function for pretty date formatting
function prettyDate($date) {
    return date('M j, Y', strtotime($date));
}
?>