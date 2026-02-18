<?php
// Start output buffering at the VERY beginning
ob_start();

// Include required files
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Initialize variables
$error = '';
$success = '';

// Security questions array
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

// Process registration form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs
    $firstName = trim($_POST['first_name'] ?? '');
    $middleName = trim($_POST['middle_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role = $_POST['role'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $security_question = $_POST['security_question'] ?? '';
    $security_answer = trim($_POST['security_answer'] ?? '');
    $terms = isset($_POST['terms']);
    
    // Validation
    if (empty($firstName) || empty($lastName) || empty($username) || empty($email) || empty($password)) {
        $error = "All required fields must be filled.";
    } elseif (!$terms) {
        $error = "You must agree to the Terms and Privacy Policy.";
    } elseif ($password !== $confirmPassword) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (empty($security_question) || empty($security_answer)) {
        $error = "Please select a security question and provide an answer.";
    } else {
        try {
            global $pdo;
            
            // Check if user already exists
            $checkSql = "SELECT id FROM users WHERE username = ? OR email = ?";
            $checkStmt = $pdo->prepare($checkSql);
            $checkStmt->execute([$username, $email]);
            
            if ($checkStmt->fetch()) {
                $error = "Username or email already exists!";
            } else {
                // Hash password and security answer
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $hashedSecurityAnswer = password_hash(strtolower(trim($security_answer)), PASSWORD_DEFAULT);
                
                // Insert user with pending status
                $sql = "INSERT INTO users (first_name, middle_name, last_name, username, email, phone, role, password, security_question, security_answer, assigned_sitios, status, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, 'pending', NOW())";
                $stmt = $pdo->prepare($sql);
                
                if ($stmt->execute([$firstName, $middleName, $lastName, $username, $email, $phone, $role, $hashedPassword, $security_question, $hashedSecurityAnswer])) {
                    // Store success message in session for display on login page
                    $_SESSION['registration_success'] = "Account created successfully! Your account is now waiting for admin approval.";
                    
                    // Clear form fields
                    $_POST = array();
                    
                    // REMOVED PHP REDIRECT - SweetAlert will handle it
                    $success = "Account created successfully! Your account is now waiting for admin approval.";
                    
                } else {
                    $error = "Registration failed. Please try again.";
                }
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Clean the buffer and start output
ob_end_clean();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kibenes eBirth - Register</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SweetAlert CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
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
        
        .register-container {
            max-width: 500px;
            width: 100%;
        }
        
        .register-card {
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            border: none;
            overflow: hidden;
            background: white;
        }
        
        .register-header {
            background: linear-gradient(135deg, #1a73e8 0%, #6a11cb 100%);
            color: white;
            padding: 20px 15px;
            text-align: center;
        }
        
        .register-icon {
            font-size: 1.8rem;
            margin-bottom: 10px;
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            width: 60px;
            height: 60px;
            border-radius: 50%;
            line-height: 60px;
        }
        
        .register-header h3 {
            margin-bottom: 3px;
            font-weight: 600;
            font-size: 1.3rem;
        }
        
        .register-header p {
            margin-bottom: 0;
            opacity: 0.9;
            font-size: 0.85rem;
        }
        
        .register-body {
            padding: 20px;
            max-height: 80vh;
            overflow-y: auto;
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
        
        .form-select {
            padding: 10px 12px;
            border-radius: 8px;
            border: 1.5px solid #e0e0e0;
            font-size: 0.9rem;
        }
        
        .form-select:focus {
            border-color: #1a73e8;
            box-shadow: 0 0 0 2px rgba(26, 115, 232, 0.15);
        }
        
        .btn-register {
            background: linear-gradient(to right, #1a73e8, #6a11cb);
            border: none;
            padding: 10px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.95rem;
            color: white;
            transition: all 0.3s ease;
        }
        
        .btn-register:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(26, 115, 232, 0.3);
            color: white;
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
        
        .register-footer {
            background-color: #f8f9fa;
            padding: 12px 15px;
            text-align: center;
            border-top: 1px solid #e0e0e0;
        }
        
        .register-footer a {
            color: #1a73e8;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.85rem;
        }
        
        .register-footer a:hover {
            color: #6a11cb;
            text-decoration: underline;
        }
        
        .login-link {
            text-align: center;
            margin-top: 15px;
        }
        
        .login-link a {
            color: #1a73e8;
            font-weight: 500;
            text-decoration: none;
            font-size: 0.9rem;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        .form-check {
            margin-bottom: 15px;
        }
        
        .form-check-label {
            font-size: 0.85rem;
        }
        
        .form-label {
            font-weight: 500;
            margin-bottom: 6px;
            font-size: 0.9rem;
        }
        
        .mb-3 {
            margin-bottom: 15px !important;
        }
        
        .password-strength {
            font-size: 0.8rem;
            margin-top: 5px;
        }
        
        .success-icon {
            font-size: 1.5rem;
            margin-right: 10px;
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .field-warning {
            color: #dc3545;
            font-size: 0.75rem;
            margin-top: 3px;
            display: none;
        }
        
        .security-note {
            font-size: 0.8rem;
            color: #6c757d;
            margin-top: 5px;
        }
        
        .optional-badge {
            font-size: 0.7rem;
            padding: 2px 6px;
            background-color: #e9ecef;
            border-radius: 4px;
            margin-left: 5px;
            color: #6c757d;
        }
        
        /* SweetAlert Custom Styles */
        .swal2-popup {
            border-radius: 12px !important;
            padding: 20px !important;
        }
        
        .swal2-title {
            color: #1a73e8 !important;
            font-size: 1.5rem !important;
            margin-bottom: 15px !important;
        }
        
        .swal2-html-container {
            font-size: 1rem !important;
            line-height: 1.5 !important;
        }
        
        .swal2-timer-progress-bar {
            background: linear-gradient(to right, #1a73e8, #6a11cb) !important;
        }
        
        .countdown-number {
            font-size: 2.5rem !important;
            font-weight: bold !important;
            color: #1a73e8 !important;
            margin: 10px 0 !important;
        }
        
        .countdown-text {
            font-size: 1rem !important;
            color: #666 !important;
            margin-bottom: 15px !important;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-card">
            <div class="register-header">
                <div class="register-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <h3>Create Account</h3>
                <p>Health Station System</p>
            </div>
            
            <div class="register-body">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger fade-in">
                        <i class="fas fa-exclamation-triangle success-icon"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <!-- SweetAlert will show automatically, so hide normal success message -->
                    <style>
                        .alert-success {
                            display: none !important;
                        }
                        #registrationForm {
                            display: none !important;
                        }
                        .login-link {
                            display: none !important;
                        }
                    </style>
                <?php endif; ?>
                
                <form method="POST" action="" id="registrationForm">
                    <div class="row">
                        <div class="col-md-4">
                            <label for="first_name" class="form-label">First Name *</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" required>
                            <div class="field-warning" id="first_name_warning">Please enter your first name</div>
                        </div>
                        <div class="col-md-4">
                            <label for="middle_name" class="form-label">Middle Name</span></label>
                            <input type="text" class="form-control" id="middle_name" name="middle_name" value="<?php echo htmlspecialchars($_POST['middle_name'] ?? ''); ?>">
                            <div class="field-warning" id="middle_name_warning"></div>
                        </div>
                        <div class="col-md-4">
                            <label for="last_name" class="form-label">Last Name *</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" required>
                            <div class="field-warning" id="last_name_warning">Please enter your last name</div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="username" class="form-label">Username *</label>
                        <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                        <div class="field-warning" id="username_warning">Please choose a username</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address *</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                        <div class="field-warning" id="email_warning">Please enter a valid email address</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone Number <span class="optional-badge">Optional</span></label>
                        <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                        <div class="field-warning" id="phone_warning">Please enter a valid phone number</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password *</label>
                        <input type="password" class="form-control" id="password" name="password" required minlength="6">
                        <div class="password-strength text-muted">Minimum 6 characters</div>
                        <div class="field-warning" id="password_warning">Password must be at least 6 characters long</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password *</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        <div class="field-warning" id="confirm_password_warning">Passwords do not match</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="security_question" class="form-label">Security Question *</label>
                        <select class="form-select" id="security_question" name="security_question" required>
                            <option value="">Select a security question</option>
                            <?php foreach ($security_questions as $key => $question): ?>
                                <option value="<?php echo htmlspecialchars($key); ?>" <?php echo ($_POST['security_question'] ?? '') == $key ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($question); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="field-warning" id="security_question_warning">Please select a security question</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="security_answer" class="form-label">Security Answer *</label>
                        <input type="text" class="form-control" id="security_answer" name="security_answer" value="<?php echo htmlspecialchars($_POST['security_answer'] ?? ''); ?>" required>
                        <div class="security-note">This will be used to verify your identity if you forget your password.</div>
                        <div class="field-warning" id="security_answer_warning">Please provide an answer to your security question</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="role" class="form-label">Register As *</label>
                        <select class="form-select" id="role" name="role" required>
                            <option value="mother" <?php echo ($_POST['role'] ?? '') === 'mother' ? 'selected' : ''; ?>>Mother</option>
                            <option value="midwife" <?php echo ($_POST['role'] ?? '') === 'midwife' ? 'selected' : ''; ?>>Midwife</option>
                            <option value="bhw" <?php echo ($_POST['role'] ?? '') === 'bhw' ? 'selected' : ''; ?>>Health Worker</option>
                            <option value="bns" <?php echo ($_POST['role'] ?? '') === 'bns' ? 'selected' : ''; ?>>Nutrition Scholar</option>
                        </select>
                        <div class="field-warning" id="role_warning">Please select your role</div>
                    </div>
                    
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="terms" name="terms" <?php echo isset($_POST['terms']) ? 'checked' : ''; ?> required>
                        <label class="form-check-label" for="terms">
                            I agree to the Terms and Privacy Policy
                        </label>
                        <div class="field-warning" id="terms_warning">You must agree to the Terms and Privacy Policy</div>
                    </div>
                    
                    <button type="submit" class="btn btn-register w-100 mt-3">Create Account</button>
                </form>
                
                <div class="login-link">
                    <p>Already have an account? <a href="login.php">Login here</a></p>
                    <p><a href="forgot-password.php">Forgot your password?</a></p>
                </div>
            </div>
        </div>
    </div>

    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Only run form validation if form exists
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('registrationForm');
            if (!form) return;
            
            const fields = [
                'first_name', 'last_name', 'username', 'email', 'password', 
                'confirm_password', 'security_question', 'security_answer', 'role'
            ];
            
            // Add event listeners to all fields
            fields.forEach(field => {
                const input = document.getElementById(field);
                if (input) {
                    input.addEventListener('blur', validateField);
                    input.addEventListener('input', hideWarning);
                }
            });
            
            // Validate terms checkbox
            const terms = document.getElementById('terms');
            if (terms) {
                terms.addEventListener('change', function() {
                    hideWarning('terms');
                });
            }
            
            // Form submission validation
            form.addEventListener('submit', function(e) {
                let isValid = true;
                
                // Validate all fields
                fields.forEach(field => {
                    if (!validateField({ target: document.getElementById(field) })) {
                        isValid = false;
                    }
                });
                
                // Validate terms
                if (!terms.checked) {
                    showWarning('terms', 'You must agree to the Terms and Privacy Policy');
                    isValid = false;
                }
                
                // Validate password match
                const password = document.getElementById('password').value;
                const confirmPassword = document.getElementById('confirm_password').value;
                if (password !== confirmPassword) {
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
                    case 'first_name':
                    case 'last_name':
                        if (value === '') {
                            showWarning(fieldId, 'This field is required');
                            return false;
                        }
                        break;
                        
                    case 'username':
                        if (value === '') {
                            showWarning(fieldId, 'Please choose a username');
                            return false;
                        }
                        break;
                        
                    case 'email':
                        if (value === '' || !isValidEmail(value)) {
                            showWarning(fieldId, 'Please enter a valid email address');
                            return false;
                        }
                        break;
                        
                    case 'phone':
                        if (value !== '' && !isValidPhone(value)) {
                            showWarning(fieldId, 'Please enter a valid phone number');
                            return false;
                        }
                        break;
                        
                    case 'password':
                        if (value.length < 6) {
                            showWarning(fieldId, 'Password must be at least 6 characters long');
                            return false;
                        }
                        break;
                        
                    case 'confirm_password':
                        const password = document.getElementById('password').value;
                        if (value !== password) {
                            showWarning(fieldId, 'Passwords do not match');
                            return false;
                        }
                        break;
                        
                    case 'security_question':
                        if (value === '') {
                            showWarning(fieldId, 'Please select a security question');
                            return false;
                        }
                        break;
                        
                    case 'security_answer':
                        if (value === '') {
                            showWarning(fieldId, 'Please provide an answer to your security question');
                            return false;
                        }
                        break;
                        
                    case 'role':
                        if (value === '') {
                            showWarning(fieldId, 'Please select your role');
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
                    
                    const field = document.getElementById(fieldId);
                    if (field) {
                        field.style.borderColor = '#e0e0e0';
                        field.style.boxShadow = 'none';
                    }
                }
            }
            
            function isValidEmail(email) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return emailRegex.test(email);
            }
            
            function isValidPhone(phone) {
                const phoneRegex = /^[\+]?[0-9\s\-\(\)]{10,}$/;
                return phoneRegex.test(phone);
            }
            
            // Real-time password match indicator
            document.getElementById('confirm_password').addEventListener('input', function() {
                const password = document.getElementById('password').value;
                const confirmPassword = this.value;
                
                if (confirmPassword && password !== confirmPassword) {
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
        });
        
       <?php if (!empty($success)): ?>
document.addEventListener('DOMContentLoaded', function() {
    let timeLeft = 5;
    let timerInterval;
    
    Swal.fire({
        title: 'Registration Successful!',
        html: `
            <div style="text-align: center; font-size: 14px;">
                <i class="fas fa-check-circle" style="font-size: 40px; color: #28a745; margin-bottom: 10px;"></i>
                <p style="margin-bottom: 5px; color: #2c3e50;">
                    <strong>Account Created Successfully!</strong>
                </p>
                <p style="color: #666; margin-bottom: 10px; font-size: 13px;">
                    Account pending admin approval.<br>
                    Redirecting in <strong style="font-size: 18px; color: #1a73e8;" id="swal-countdown">${timeLeft}</strong> seconds...
                </p>
            </div>
        `,
        icon: 'success',
        showConfirmButton: false,
        allowOutsideClick: false,
        allowEscapeKey: false,
        width: 350,
        padding: '15px',
        backdrop: true,
        timer: 5000,
        timerProgressBar: true,
        didOpen: () => {
            const countdownEl = Swal.getHtmlContainer().querySelector('#swal-countdown');
            
            // Start countdown timer
            timerInterval = setInterval(() => {
                timeLeft--;
                if (countdownEl) {
                    countdownEl.textContent = timeLeft;
                }
                
                if (timeLeft <= 0) {
                    clearInterval(timerInterval);
                }
            }, 1000);
        },
        willClose: () => {
            clearInterval(timerInterval);
            window.location.href = 'login.php';
        }
    });
    
    // Add manual close button after 1 second
    setTimeout(() => {
        const swalContainer = document.querySelector('.swal2-popup');
        if (swalContainer) {
            const closeBtn = document.createElement('button');
            closeBtn.innerHTML = '<i class="fas fa-sign-in-alt me-1"></i>Login Now';
            closeBtn.className = 'btn btn-primary btn-sm';
            closeBtn.style.marginTop = '10px';
            closeBtn.style.padding = '5px 15px';
            closeBtn.style.fontSize = '12px';
            closeBtn.onclick = function() {
                Swal.close();
                window.location.href = 'login.php';
            };
            
            const htmlContainer = Swal.getHtmlContainer();
            if (htmlContainer) {
                htmlContainer.appendChild(closeBtn);
            }
        }
    }, 1000);
});

<?php endif; ?>
</script>
</body>
</html>