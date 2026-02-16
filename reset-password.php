<?php
// reset-password.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/config.php';

// Check if reset token is valid
if (!isset($_SESSION['reset_token'])) {
    header('Location: forgot-password.php');
    exit;
}

$reset_token = $_SESSION['reset_token'];
$error = '';
$success = '';

// Verify token
$stmt = $pdo->prepare("SELECT id FROM users WHERE reset_token = ? AND token_expires > NOW()");
$stmt->execute([$reset_token]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    unset($_SESSION['reset_token']);
    header('Location: forgot-password.php?error=invalid_token');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if ($new_password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif (strlen($new_password) < 8) {
        $error = "Password must be at least 8 characters long";
    } else {
        // Update password
        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, reset_token = NULL, token_expires = NULL WHERE reset_token = ?");
        $stmt->execute([$password_hash, $reset_token]);
        
        if ($stmt->rowCount() > 0) {
            $success = "Password reset successfully! You can now login with your new password.";
            unset($_SESSION['reset_token']);
            unset($_SESSION['reset_user_id']);
        } else {
            $error = "Failed to reset password. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Health Station System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 15px;
        }
        
        .reset-container {
            max-width: 450px;
            width: 100%;
        }
        
        .reset-card {
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            border: none;
            overflow: hidden;
            background: white;
        }
        
        .reset-header {
            background: linear-gradient(135deg, #1a73e8 0%, #6a11cb 100%);
            color: white;
            padding: 20px 15px;
            text-align: center;
        }
        
        .reset-icon {
            font-size: 1.8rem;
            margin-bottom: 10px;
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            width: 60px;
            height: 60px;
            border-radius: 50%;
            line-height: 60px;
        }
        
        .reset-body {
            padding: 25px;
        }
        
        .btn-success {
            background: linear-gradient(to right, #28a745, #20c997);
            border: none;
            padding: 10px;
            border-radius: 8px;
            font-weight: 500;
        }
        
        .back-link {
            display: block;
            text-align: center;
            margin-top: 15px;
            color: #1a73e8;
            text-decoration: none;
        }
        
        .password-strength {
            margin-top: 5px;
            font-size: 0.85rem;
        }
        
        .strength-weak { color: #dc3545; }
        .strength-medium { color: #fd7e14; }
        .strength-strong { color: #28a745; }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-card">
            <div class="reset-header">
                <div class="reset-icon">
                    <i class="fas fa-lock"></i>
                </div>
                <h3>Create New Password</h3>
                <p>Enter your new password</p>
            </div>
            
            <div class="reset-body">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                    <div class="text-center">
                        <a href="login.php" class="btn btn-primary">Go to Login</a>
                    </div>
                <?php else: ?>
                    <form method="POST" action="" id="resetForm">
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="new_password" name="new_password" placeholder="Enter new password" required minlength="8">
                            </div>
                            <div class="password-strength" id="passwordStrength"></div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required>
                            </div>
                            <div class="form-text" id="passwordMatch"></div>
                        </div>
                        
                        <button type="submit" class="btn btn-success w-100">Reset Password</button>
                    </form>
                <?php endif; ?>
                
                <a href="login.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Back to Login
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('new_password').addEventListener('input', function() {
            const password = this.value;
            const strengthText = document.getElementById('passwordStrength');
            let strength = '';
            let color = '';
            
            if (password.length === 0) {
                strength = '';
            } else if (password.length < 8) {
                strength = 'Weak - at least 8 characters required';
                color = 'strength-weak';
            } else if (password.length < 12) {
                strength = 'Medium';
                color = 'strength-medium';
            } else {
                strength = 'Strong';
                color = 'strength-strong';
            }
            
            strengthText.textContent = strength;
            strengthText.className = 'password-strength ' + color;
        });
        
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('new_password').value;
            const confirm = this.value;
            const matchText = document.getElementById('passwordMatch');
            
            if (confirm.length === 0) {
                matchText.textContent = '';
            } else if (password === confirm) {
                matchText.textContent = 'Passwords match';
                matchText.style.color = '#28a745';
            } else {
                matchText.textContent = 'Passwords do not match';
                matchText.style.color = '#dc3545';
            }
        });
    </script>
</body>
</html>