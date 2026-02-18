<?php
// forgot-password.php
ob_start();
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$error = '';
$success = '';
$step = 1; // 1: Enter email, 2: Security question, 3: Reset password

// Security questions array - MUST MATCH THE ONE IN REGISTRATION.PHP
$security_questions = [
    "q1" => "What was the name of your first pet?",
    "q2" => "What city were you born in?",
    "q3" => "What is your mother's maiden name?",
    "q4" => "What was the name of your elementary school?",
    "q5" => "What was your childhood nickname?",
    "q6" => "What is the name of your favorite childhood friend?",
    "q7" => "What street did you grow up on?",
    "q8" => "What was the make of your first car?",
    "q9" => "What is your favorite movie?",
    "q10" => "What is your favorite book?"
];

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['email'])) {
        // Step 1: Verify email
        $email = trim($_POST['email']);
        
        try {
            global $pdo;
            $sql = "SELECT id, security_question FROM users WHERE email = ? AND status = 'active'";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                $_SESSION['reset_user_id'] = $user['id'];
                $_SESSION['reset_security_question'] = $user['security_question'];
                $step = 2;
            } else {
                $error = "No active account found with that email address.";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    } elseif (isset($_POST['security_answer'])) {
        // Step 2: Verify security answer
        $security_answer = trim($_POST['security_answer']);
        $user_id = $_SESSION['reset_user_id'] ?? 0;
        
        try {
            global $pdo;
            $sql = "SELECT security_answer FROM users WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if ($user && password_verify(strtolower($security_answer), $user['security_answer'])) {
                $_SESSION['reset_verified'] = true;
                $step = 3;
            } else {
                $error = "Incorrect security answer. Please try again.";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    } elseif (isset($_POST['new_password'])) {
        // Step 3: Reset password
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        $user_id = $_SESSION['reset_user_id'] ?? 0;
        
        // Check if verification was completed
        if (!isset($_SESSION['reset_verified']) || !$_SESSION['reset_verified']) {
            $error = "Security verification required.";
            $step = 1;
        } elseif ($new_password !== $confirm_password) {
            $error = "Passwords do not match.";
            $step = 3;
        } elseif (strlen($new_password) < 6) {
            $error = "Password must be at least 6 characters long.";
            $step = 3;
        } else {
            try {
                global $pdo;
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                $sql = "UPDATE users SET password = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                
                if ($stmt->execute([$hashed_password, $user_id])) {
                    $success = "Password reset successfully! You can now login with your new password.";
                    // Clear session
                    unset($_SESSION['reset_user_id']);
                    unset($_SESSION['reset_security_question']);
                    unset($_SESSION['reset_verified']);
                    $step = 1;
                } else {
                    $error = "Failed to reset password. Please try again.";
                    $step = 3;
                }
            } catch (PDOException $e) {
                $error = "Database error: " . $e->getMessage();
                $step = 3;
            }
        }
    }
}

// Get current step from session if applicable
if (isset($_SESSION['reset_user_id']) && isset($_SESSION['reset_security_question'])) {
    if (isset($_SESSION['reset_verified']) && $_SESSION['reset_verified']) {
        $step = 3;
    } else {
        $step = 2;
    }
}

ob_end_clean();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kibenes eBirth - Forgot Password</title>
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
        
        .forgot-container {
            max-width: 450px;
            width: 100%;
        }
        
        .forgot-card {
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            border: none;
            overflow: hidden;
            background: white;
        }
        
        .forgot-header {
            background: linear-gradient(135deg, #1a73e8 0%, #6a11cb 100%);
            color: white;
            padding: 20px 15px;
            text-align: center;
        }
        
        .forgot-icon {
            font-size: 1.8rem;
            margin-bottom: 10px;
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            width: 60px;
            height: 60px;
            border-radius: 50%;
            line-height: 60px;
        }
        
        .forgot-header h3 {
            margin-bottom: 3px;
            font-weight: 600;
            font-size: 1.3rem;
        }
        
        .forgot-header p {
            margin-bottom: 0;
            opacity: 0.9;
            font-size: 0.85rem;
        }
        
        .alert {
            border-radius: 8px;
            border: none;
            padding: 12px 15px;
            font-size: 0.85rem;
            margin-bottom: 15px;
        }
        
        .alert-success {
            background-color: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }
        
        .alert-danger {
            background-color: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }
        
        .btn-primary {
            background: linear-gradient(to right, #1a73e8, #6a11cb);
            border: none;
            padding: 10px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(26, 115, 232, 0.3);
        }
        
        .form-control {
            padding: 10px 12px;
            border-radius: 8px;
            border: 1.5px solid #e0e0e0;
            font-size: 0.9rem;
        }
        
        .form-control:focus {
            border-color: #1a73e8;
            box-shadow: 0 0 0 2px rgba(26, 115, 232, 0.15);
        }
        
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            position: relative;
        }
        
        .step-indicator::before {
            content: '';
            position: absolute;
            top: 15px;
            left: 0;
            right: 0;
            height: 2px;
            background: #e0e0e0;
            z-index: 1;
        }
        
        .step {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: white;
            border: 2px solid #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: bold;
            position: relative;
            z-index: 2;
        }
        
        .step.active {
            border-color: #1a73e8;
            background: #1a73e8;
            color: white;
        }
        
        .step.completed {
            border-color: #10b981;
            background: #10b981;
            color: white;
        }
        
        .security-question {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 4px solid #1a73e8;
        }
    </style>
</head>
<body>
    <div class="forgot-container">
        <div class="forgot-card">
            <div class="forgot-header">
                <div class="forgot-icon">
                    <i class="fas fa-key"></i>
                </div>
                <h3>Reset Password</h3>
                <p>Kibenes eBirth System</p>
            </div>
            
            <div class="p-4">
                <!-- Step Indicator -->
                <div class="step-indicator">
                    <div class="step <?php echo $step >= 1 ? 'active' : ''; ?> <?php echo $step > 1 ? 'completed' : ''; ?>">1</div>
                    <div class="step <?php echo $step >= 2 ? 'active' : ''; ?> <?php echo $step > 2 ? 'completed' : ''; ?>">2</div>
                    <div class="step <?php echo $step >= 3 ? 'active' : ''; ?>">3</div>
                </div>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($step == 1): ?>
                    <form method="POST">
                        <div class="mb-3">
                            <label for="email" class="form-label">Enter your email address</label>
                            <input type="email" class="form-control" id="email" name="email" required 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Continue</button>
                    </form>
                <?php elseif ($step == 2): ?>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Security Question</label>
                            <div class="security-question">
                                <?php 
                                $question_key = $_SESSION['reset_security_question'] ?? '';
                                if (isset($security_questions[$question_key])): 
                                ?>
                                    <strong><?php echo htmlspecialchars($security_questions[$question_key]); ?></strong>
                                <?php else: ?>
                                    <strong class="text-danger">Error: Security question not found. Please contact support.</strong>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="security_answer" class="form-label">Your Answer</label>
                            <input type="text" class="form-control" id="security_answer" name="security_answer" required>
                            <div class="form-text">Answer is case-insensitive.</div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Verify Answer</button>
                    </form>
                <?php elseif ($step == 3): ?>
                    <form method="POST">
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
                            <div class="form-text">Password must be at least 6 characters long.</div>
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Reset Password</button>
                    </form>
                <?php endif; ?>
                
                <div class="text-center mt-3">
                    <a href="login.php" class="text-decoration-none">Back to Login</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password confirmation validation
        document.addEventListener('DOMContentLoaded', function() {
            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');
            
            if (newPassword && confirmPassword) {
                confirmPassword.addEventListener('input', function() {
                    if (this.value && newPassword.value !== this.value) {
                        this.style.borderColor = '#dc3545';
                        this.style.boxShadow = '0 0 0 2px rgba(220, 53, 69, 0.15)';
                    } else if (this.value) {
                        this.style.borderColor = '#198754';
                        this.style.boxShadow = '0 0 0 2px rgba(25, 135, 84, 0.15)';
                    } else {
                        this.style.borderColor = '#e0e0e0';
                        this.style.boxShadow = 'none';
                    }
                });
            }
        });
    </script>
</body>
</html>