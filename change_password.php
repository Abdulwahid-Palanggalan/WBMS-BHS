<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

redirectIfNotLoggedIn();

global $pdo;

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $user_id = $_SESSION['user_id'];
    
    // Validation
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "All password fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match.";
    } elseif (strlen($new_password) < 6) {
        $error = "New password must be at least 6 characters long.";
    } else {
        try {
            // Get current user data
            $sql = "SELECT password FROM users WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($current_password, $user['password'])) {
                // Hash new password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                // Update password
                $updateSql = "UPDATE users SET password = ? WHERE id = ?";
                $updateStmt = $pdo->prepare($updateSql);
                
                if ($updateStmt->execute([$hashed_password, $user_id])) {
                    $message = "Password changed successfully!";
                    logActivity($user_id, "Changed password");
                    
                    // Redirect back to profile with success message
                    $_SESSION['password_change_success'] = $message;
                    header("Location: profile.php");
                    exit();
                } else {
                    $error = "Failed to change password. Please try again.";
                }
            } else {
                $error = "Current password is incorrect.";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
    
    // Store error in session for redirect
    if ($error) {
        $_SESSION['password_change_error'] = $error;
        header("Location: profile.php");
        exit();
    }
} else {
    // If not POST request, redirect to profile
    header("Location: profile.php");
    exit();
}
?>