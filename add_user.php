<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

if (!isAuthorized(['admin'])) {
    header("Location: login.php");
    exit();
}

redirectIfNotLoggedIn();

global $pdo;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name']);
    $lastName = trim($_POST['last_name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    $password = $_POST['password'];
    
    // Check if username or email already exists
    $checkUser = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $checkUser->execute([$username, $email]);
    
    if ($checkUser->rowCount() > 0) {
        $_SESSION['error'] = "Username or email already exists.";
    } else {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO users (username, email, password, role, first_name, last_name, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        
        $status = ($role === 'mother') ? 'active' : 'pending';
        
        if ($stmt->execute([$username, $email, $hashedPassword, $role, $firstName, $lastName, $status])) {
            $_SESSION['message'] = "User added successfully!";
            logActivity($_SESSION['user_id'], "Added new user: $username");
        } else {
            $_SESSION['error'] = "Failed to add user. Please try again.";
        }
    }
}

header("Location: user_management.php");
exit();
?>