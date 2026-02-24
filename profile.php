<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

redirectIfNotLoggedIn();

global $pdo;

// Get user data
$userId = $_SESSION['user_id'];
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if user has security question
$hasSecurityQuestion = !empty($user['security_question']) && !empty($user['security_answer']);

$message = '';
$error = '';

// Check for password change messages
if (isset($_SESSION['password_change_success'])) {
    $message = $_SESSION['password_change_success'];
    unset($_SESSION['password_change_success']);
}

if (isset($_SESSION['password_change_error'])) {
    $error = $_SESSION['password_change_error'];
    unset($_SESSION['password_change_error']);
}

// Check for security question messages
if (isset($_SESSION['security_question_success'])) {
    $message = $_SESSION['security_question_success'];
    unset($_SESSION['security_question_success']);
}

if (isset($_SESSION['security_question_error'])) {
    $error = $_SESSION['security_question_error'];
    unset($_SESSION['security_question_error']);
}

// Security questions array (same as registration)
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if it's profile update or security question update
    if (isset($_POST['first_name'])) {
        // Profile update
        $firstName = trim($_POST['first_name']);
        $lastName = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        
        // Check if email already exists for another user
        $checkEmail = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $checkEmail->execute([$email, $userId]);
        
        if ($checkEmail->rowCount() > 0) {
            $error = "Email already exists for another user.";
        } else {
            $updateSql = "UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE id = ?";
            $updateStmt = $pdo->prepare($updateSql);
            
            if ($updateStmt->execute([$firstName, $lastName, $email, $phone, $userId])) {
                $_SESSION['first_name'] = $firstName;
                $_SESSION['last_name'] = $lastName;
                $message = "Profile updated successfully!";
                
                logActivity($userId, "Updated profile information");
            } else {
                $error = "Failed to update profile. Please try again.";
            }
        }
    } elseif (isset($_POST['security_question'])) {
        // Security question update
        $question = trim($_POST['security_question']);
        $answer = trim($_POST['security_answer']);
        
        if (empty($question) || empty($answer)) {
            $error = "Please provide both security question and answer.";
        } else {
            // Update user's security question
            $updateSql = "UPDATE users SET security_question = ?, security_answer = ? WHERE id = ?";
            $updateStmt = $pdo->prepare($updateSql);
            
            // Hash the security answer
            $hashedAnswer = password_hash(strtolower(trim($answer)), PASSWORD_DEFAULT);
            
            if ($updateStmt->execute([$question, $hashedAnswer, $userId])) {
                $_SESSION['security_question_success'] = "Security question set successfully!";
                header("Location: profile.php");
                exit();
            } else {
                $error = "Failed to set security question. Please try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Kibenes eBirth</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        
        .card-header {
            background: linear-gradient(135deg, #1a73e8 0%, #6a11cb 100%);
            color: white;
            border-radius: 12px 12px 0 0 !important;
            border: none;
            padding: 15px 20px;
        }
        
        .card-header h5 {
            margin: 0;
            font-weight: 600;
        }
        
        .form-control {
            border-radius: 8px;
            border: 1.5px solid #e0e0e0;
            padding: 10px 12px;
        }
        
        .form-control:focus {
            border-color: #1a73e8;
            box-shadow: 0 0 0 2px rgba(26, 115, 232, 0.15);
        }
        
        .btn-primary {
            background: linear-gradient(to right, #1a73e8, #6a11cb);
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 500;
        }
        
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(26, 115, 232, 0.3);
        }
        
        .alert {
            border-radius: 8px;
            border: none;
            padding: 12px 15px;
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
        
        .password-strength {
            font-size: 0.8rem;
            margin-top: 5px;
        }
        
        .field-warning {
            color: #dc3545;
            font-size: 0.75rem;
            margin-top: 3px;
            display: none;
        }
        
        .security-info {
            background-color: #e8f4fd;
            border-left: 4px solid #1a73e8;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        .security-completed {
            background-color: #d1fae5;
            border-left: 4px solid #10b981;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        .security-note {
            font-size: 0.8rem;
            color: #6c757d;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <?php include_once 'includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include_once 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">My Profile</h1>
                </div>
                
                <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5>Personal Information</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="mb-3">
                                        <label for="first_name" class="form-label">First Name</label>
                                        <input type="text" class="form-control" id="first_name" name="first_name" 
                                               value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="last_name" class="form-label">Last Name</label>
                                        <input type="text" class="form-control" id="last_name" name="last_name" 
                                               value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email Address</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                               value="<?php echo htmlspecialchars($user['phone']); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Role</label>
                                        <input type="text" class="form-control" value="<?php echo ucfirst($user['role']); ?>" disabled>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Username</label>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Account Status</label>
                                        <input type="text" class="form-control" value="<?php echo ucfirst($user['status']); ?>" disabled>
                                    </div>
                                    <button type="submit" class="btn btn-primary">Update Profile</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <!-- Password Change Card -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5>Change Password</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="change_password.php" id="passwordForm">
                                    <div class="mb-3">
                                        <label for="current_password" class="form-label">Current Password</label>
                                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                                        <div class="field-warning" id="current_password_warning">Please enter your current password</div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">New Password</label>
                                        <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
                                        <div class="password-strength text-muted">Minimum 6 characters</div>
                                        <div class="field-warning" id="new_password_warning">Password must be at least 6 characters long</div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                        <div class="field-warning" id="confirm_password_warning">Passwords do not match</div>
                                    </div>
                                    <button type="submit" class="btn btn-primary">Change Password</button>
                                </form>
                            </div>
                        </div>

                        <!-- Security Question Card - ONLY SHOW IF USER HAS NO SECURITY QUESTION -->
                        <?php if (!$hasSecurityQuestion): ?>
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-shield-alt me-2"></i>Set Security Question</h5>
                            </div>
                            <div class="card-body">
                                <div class="security-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Important:</strong> Set up a security question to enable password recovery in case you forget your password.
                                </div>
                                
                                <form method="POST" action="">
                                    <div class="mb-3">
                                        <label for="security_question" class="form-label">Security Question *</label>
                                        <select class="form-control" id="security_question" name="security_question" required>
                                            <option value="">Select a security question</option>
                                            <?php foreach ($security_questions as $key => $question): ?>
                                                <option value="<?php echo htmlspecialchars($key); ?>">
                                                    <?php echo htmlspecialchars($question); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="field-warning" id="security_question_warning">Please select a security question</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="security_answer" class="form-label">Security Answer *</label>
                                        <input type="text" class="form-control" id="security_answer" name="security_answer" required>
                                        <div class="security-note">This will be used to verify your identity if you forget your password.</div>
                                        <div class="field-warning" id="security_answer_warning">Please provide an answer to your security question</div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">Set Security Question</button>
                                </form>
                            </div>
                        </div>
                        <?php else: ?>
                        <!-- Show security question status if already set -->
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-shield-alt me-2"></i>Security Question</h5>
                            </div>
                            <div class="card-body">
                                <div class="security-completed">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <strong>Security question is set up.</strong> You can use this for password recovery.
                                </div>
                                
                                <div class="mt-3">
                                    <p><strong>Your Security Question:</strong></p>
                                    <p class="text-muted"><?php echo htmlspecialchars($security_questions[$user['security_question']] ?? 'Unknown question'); ?></p>
                                </div>
                                
                                <div class="mt-2">
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle me-1"></i>
                                        To change your security question, please contact the administrator.
                                    </small>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password form validation
        document.addEventListener('DOMContentLoaded', function() {
            const passwordForm = document.getElementById('passwordForm');
            const fields = ['current_password', 'new_password', 'confirm_password'];
            
            // Add event listeners to all fields
            fields.forEach(field => {
                const input = document.getElementById(field);
                if (input) {
                    input.addEventListener('blur', validateField);
                    input.addEventListener('input', hideWarning);
                }
            });
            
            // Form submission validation
            passwordForm.addEventListener('submit', function(e) {
                let isValid = true;
                
                // Validate all fields
                fields.forEach(field => {
                    if (!validateField({ target: document.getElementById(field) })) {
                        isValid = false;
                    }
                });
                
                // Validate password match
                const newPassword = document.getElementById('new_password').value;
                const confirmPassword = document.getElementById('confirm_password').value;
                if (newPassword !== confirmPassword) {
                    showWarning('confirm_password', 'Passwords do not match');
                    isValid = false;
                }
                
                if (!isValid) {
                    e.preventDefault();
                    // Scroll to first error
                    const firstError = document.querySelector('.field-warning[style*="display: block"]');
                    if (firstError) {
                        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }
            });
            
            function validateField(e) {
                const field = e.target;
                const fieldId = field.id;
                const value = field.value.trim();
                
                switch(fieldId) {
                    case 'current_password':
                        if (value === '') {
                            showWarning(fieldId, 'Please enter your current password');
                            return false;
                        }
                        break;
                        
                    case 'new_password':
                        if (value.length < 6) {
                            showWarning(fieldId, 'Password must be at least 6 characters long');
                            return false;
                        }
                        break;
                        
                    case 'confirm_password':
                        const newPassword = document.getElementById('new_password').value;
                        if (value !== newPassword) {
                            showWarning(fieldId, 'Passwords do not match');
                            return false;
                        }
                        break;
                }
                
                hideWarning(fieldId);
                return true;
            }
            
            function showWarning(fieldId, message) {
                const warning = document.getElementById(fieldId + '_warning');
                if (warning) {
                    warning.textContent = message;
                    warning.style.display = 'block';
                    
                    // Highlight the field
                    const field = document.getElementById(fieldId);
                    if (field) {
                        field.style.borderColor = '#dc3545';
                        field.style.boxShadow = '0 0 0 2px rgba(220, 53, 69, 0.15)';
                    }
                }
            }
            
            function hideWarning(fieldId) {
                if (typeof fieldId === 'object') {
                    fieldId = fieldId.target.id;
                }
                
                const warning = document.getElementById(fieldId + '_warning');
                if (warning) {
                    warning.style.display = 'none';
                    
                    // Remove highlight from the field
                    const field = document.getElementById(fieldId);
                    if (field) {
                        field.style.borderColor = '#e0e0e0';
                        field.style.boxShadow = 'none';
                    }
                }
            }
            
            // Real-time password match indicator
            document.getElementById('confirm_password').addEventListener('input', function() {
                const newPassword = document.getElementById('new_password').value;
                const confirmPassword = this.value;
                
                if (confirmPassword && newPassword !== confirmPassword) {
                    this.style.borderColor = '#dc3545';
                    this.style.boxShadow = '0 0 0 2px rgba(220, 53, 69, 0.15)';
                } else if (confirmPassword) {
                    this.style.borderColor = '#198754';
                    this.style.boxShadow = '0 0 0 2px rgba(25, 135, 84, 0.15)';
                } else {
                    this.style.borderColor = '#e0e0e0';
                    this.style.boxShadow = 'none';
                }
            });

            // Security question form validation
            const securityQuestionForm = document.querySelector('form[action=""]');
            if (securityQuestionForm) {
                const securityFields = ['security_question', 'security_answer'];
                
                securityFields.forEach(field => {
                    const input = document.getElementById(field);
                    if (input) {
                        input.addEventListener('blur', validateSecurityField);
                        input.addEventListener('input', hideSecurityWarning);
                    }
                });

                securityQuestionForm.addEventListener('submit', function(e) {
                    let isValid = true;
                    
                    securityFields.forEach(field => {
                        if (!validateSecurityField({ target: document.getElementById(field) })) {
                            isValid = false;
                        }
                    });
                    
                    if (!isValid) {
                        e.preventDefault();
                    }
                });

                function validateSecurityField(e) {
                    const field = e.target;
                    const fieldId = field.id;
                    const value = field.value.trim();
                    
                    if (value === '') {
                        showSecurityWarning(fieldId, 'This field is required');
                        return false;
                    }
                    
                    hideSecurityWarning(fieldId);
                    return true;
                }

                function showSecurityWarning(fieldId, message) {
                    const warning = document.getElementById(fieldId + '_warning');
                    if (warning) {
                        warning.textContent = message;
                        warning.style.display = 'block';
                        
                        const field = document.getElementById(fieldId);
                        if (field) {
                            field.style.borderColor = '#dc3545';
                            field.style.boxShadow = '0 0 0 2px rgba(220, 53, 69, 0.15)';
                        }
                    }
                }

                function hideSecurityWarning(fieldId) {
                    if (typeof fieldId === 'object') {
                        fieldId = fieldId.target.id;
                    }
                    
                    const warning = document.getElementById(fieldId + '_warning');
                    if (warning) {
                        warning.style.display = 'none';
                        
                        const field = document.getElementById(fieldId);
                        if (field) {
                            field.style.borderColor = '#e0e0e0';
                            field.style.boxShadow = 'none';
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>