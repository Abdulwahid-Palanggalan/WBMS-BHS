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
    <title>Create Account - Barangay Kibenes</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SweetAlert CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <?php include_once 'includes/tailwind_config.php'; ?>
</head>
<body class="min-h-screen py-10 px-4 flex items-center justify-center bg-gradient-to-br from-health-50 via-white to-primary/10">
    <div class="max-w-2xl w-full">
        <!-- Brand Header -->
        <div class="text-center mb-10">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-health-600 rounded-2xl shadow-lg shadow-health-200 mb-4 transform hover:scale-105 transition-transform duration-300">
                <i class="fas fa-user-plus text-white text-4xl"></i>
            </div>
            <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Health Station Registry</h1>
            <p class="text-health-600 font-medium uppercase tracking-widest text-xs mt-1">Join Barangay Kibenes Information System</p>
        </div>

        <div class="card-health p-8 md:p-12">
            <div class="mb-10 text-center md:text-left">
                <h2 class="text-2xl font-bold text-slate-800">Create your account</h2>
                <p class="text-slate-500 text-sm mt-1">Please fill in the required details to register for the system.</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="mb-8 p-4 bg-red-50 border border-red-100 rounded-xl flex items-start gap-3 text-red-700 animate-in fade-in slide-in-from-top-2 duration-300">
                    <i class="fas fa-exclamation-circle mt-1"></i>
                    <p class="text-sm font-medium"><?php echo htmlspecialchars($error); ?></p>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="registrationForm" class="space-y-8">
                <!-- Personal Information Section -->
                <div>
                    <h3 class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em] mb-4 flex items-center gap-2">
                        <i class="fas fa-user text-health-500"></i>
                        Personal Information
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="space-y-1.5">
                            <label for="first_name" class="text-sm font-semibold text-slate-700 ml-1">First Name <span class="text-medical-red">*</span></label>
                            <input type="text" class="input-health" id="first_name" name="first_name" value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" required placeholder="John">
                        </div>
                        <div class="space-y-1.5">
                            <label for="middle_name" class="text-sm font-semibold text-slate-700 ml-1">Middle Name</label>
                            <input type="text" class="input-health" id="middle_name" name="middle_name" value="<?php echo htmlspecialchars($_POST['middle_name'] ?? ''); ?>" placeholder="Quincy">
                        </div>
                        <div class="space-y-1.5">
                            <label for="last_name" class="text-sm font-semibold text-slate-700 ml-1">Last Name <span class="text-medical-red">*</span></label>
                            <input type="text" class="input-health" id="last_name" name="last_name" value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" required placeholder="Doe">
                        </div>
                    </div>
                </div>

                <!-- Account Credentials Section -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 pt-4 border-t border-slate-50">
                    <div class="space-y-6">
                        <h3 class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em] mb-4 flex items-center gap-2">
                            <i class="fas fa-id-card text-health-500"></i>
                            Account Details
                        </h3>
                        <div class="space-y-1.5">
                            <label for="username" class="text-sm font-semibold text-slate-700 ml-1">Username <span class="text-medical-red">*</span></label>
                            <input type="text" class="input-health" id="username" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required placeholder="johndoe22">
                        </div>
                        <div class="space-y-1.5">
                            <label for="email" class="text-sm font-semibold text-slate-700 ml-1">Email Address <span class="text-medical-red">*</span></label>
                            <input type="email" class="input-health" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required placeholder="john@example.com">
                        </div>
                        <div class="space-y-1.5">
                            <label for="phone" class="text-sm font-semibold text-slate-700 ml-1">Phone Number</label>
                            <input type="tel" class="input-health" id="phone" name="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" placeholder="0912 345 6789">
                        </div>
                    </div>

                    <div class="space-y-6">
                        <h3 class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em] mb-4 flex items-center gap-2">
                            <i class="fas fa-lock text-health-500"></i>
                            Security
                        </h3>
                        <div class="space-y-1.5">
                            <label for="password" class="text-sm font-semibold text-slate-700 ml-1">Password <span class="text-medical-red">*</span></label>
                            <input type="password" class="input-health" id="password" name="password" required minlength="6" placeholder="••••••••">
                            <p class="text-[10px] text-slate-400 italic">Minimum 6 characters</p>
                        </div>
                        <div class="space-y-1.5">
                            <label for="confirm_password" class="text-sm font-semibold text-slate-700 ml-1">Confirm Password <span class="text-medical-red">*</span></label>
                            <input type="password" class="input-health" id="confirm_password" name="confirm_password" required placeholder="••••••••">
                        </div>
                        <div class="space-y-1.5">
                            <label for="role" class="text-sm font-semibold text-slate-700 ml-1">Register As <span class="text-medical-red">*</span></label>
                            <select class="input-health appearance-none" id="role" name="role" required>
                                <option value="mother" <?php echo ($_POST['role'] ?? '') === 'mother' ? 'selected' : ''; ?>>Mother</option>
                                <option value="midwife" <?php echo ($_POST['role'] ?? '') === 'midwife' ? 'selected' : ''; ?>>Midwife</option>
                                <option value="bhw" <?php echo ($_POST['role'] ?? '') === 'bhw' ? 'selected' : ''; ?>>Health Worker (BHW)</option>
                                <option value="bns" <?php echo ($_POST['role'] ?? '') === 'bns' ? 'selected' : ''; ?>>Nutrition Scholar (BNS)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Security Verification Section -->
                <div class="pt-4 border-t border-slate-50">
                    <h3 class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em] mb-6 flex items-center gap-2">
                        <i class="fas fa-shield-halved text-health-500"></i>
                        Identity Verification
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div class="space-y-1.5">
                            <label for="security_question" class="text-sm font-semibold text-slate-700 ml-1">Security Question <span class="text-medical-red">*</span></label>
                            <select class="input-health appearance-none" id="security_question" name="security_question" required>
                                <option value="">Select a security question</option>
                                <?php foreach ($security_questions as $key => $question): ?>
                                    <option value="<?php echo htmlspecialchars($key); ?>" <?php echo ($_POST['security_question'] ?? '') == $key ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($question); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="space-y-1.5">
                            <label for="security_answer" class="text-sm font-semibold text-slate-700 ml-1">Security Answer <span class="text-medical-red">*</span></label>
                            <input type="text" class="input-health" id="security_answer" name="security_answer" value="<?php echo htmlspecialchars($_POST['security_answer'] ?? ''); ?>" required placeholder="Your secret answer">
                            <p class="text-[10px] text-slate-400">Used for password recovery only.</p>
                        </div>
                    </div>
                </div>

                <!-- Agreements & Action -->
                <div class="pt-10 flex flex-col items-center space-y-6">
                    <label class="flex items-center gap-3 cursor-pointer group">
                        <div class="relative flex items-center">
                            <input type="checkbox" class="peer h-5 w-5 cursor-pointer appearance-none rounded border border-slate-200 transition-all checked:bg-health-600 checked:border-health-600 focus:outline-none" id="terms" name="terms" <?php echo isset($_POST['terms']) ? 'checked' : ''; ?> required>
                            <span class="absolute text-white transition-opacity opacity-0 pointer-events-none top-1/2 left-1/2 -translate-y-1/2 -translate-x-1/2 peer-checked:opacity-100">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                            </span>
                        </div>
                        <span class="text-sm text-slate-600 selection:bg-transparent tracking-tight">
                            I agree to the <a href="#" class="text-health-600 font-bold hover:underline">Terms of Service</a> and <a href="#" class="text-health-600 font-bold hover:underline">Privacy Policy</a>
                        </span>
                    </label>

                    <button type="submit" class="w-full md:w-80 btn-health py-4 text-base shadow-xl shadow-health-100 flex items-center justify-center gap-3 group">
                        <i class="fas fa-file-signature transition-transform group-hover:scale-110"></i>
                        <span>Register Account</span>
                    </button>
                    
                    <div class="pt-4 text-sm text-slate-500 font-medium">
                        Already have an account? 
                        <a href="login.php" class="text-health-600 font-black hover:underline transition-all">Log in here</a>
                    </div>
                </div>
            </form>
        </div>

        <p class="text-center mt-12 text-slate-400 text-xs font-semibold uppercase tracking-[0.2em]">
            &copy; <?php echo date('Y'); ?> Barangay Kibenes Health Station
        </p>
    </div>

    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Form Validation Logic
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('registrationForm');
            if (!form) return;
            
            form.addEventListener('submit', function(e) {
                const password = document.getElementById('password').value;
                const confirmPassword = document.getElementById('confirm_password').value;
                
                if (password !== confirmPassword) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'Oops...',
                        text: 'Passwords do not match!',
                        confirmButtonColor: '#0D9488'
                    });
                }
            });
        });

        <?php if (!empty($success)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            let timeLeft = 5;
            let timerInterval;
            
            Swal.fire({
                title: 'Success!',
                html: `
                    <div class="text-center py-4">
                        <div class="w-16 h-16 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl shadow-sm">
                            <i class="fas fa-check"></i>
                        </div>
                        <h4 class="text-lg font-bold text-slate-800 mb-2">Account Created!</h4>
                        <p class="text-sm text-slate-600 mb-6">Your account is pending admin approval.</p>
                        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Redirecting in</p>
                            <p class="text-2xl font-black text-health-600" id="swal-countdown">${timeLeft}</p>
                            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mt-1">Seconds</p>
                        </div>
                    </div>
                `,
                icon: undefined,
                showConfirmButton: true,
                confirmButtonText: '<i class="fas fa-sign-in-alt me-2"></i>Go to Login',
                confirmButtonColor: '#0D9488',
                allowOutsideClick: false,
                allowEscapeKey: false,
                width: 400,
                padding: '2rem',
                timer: 5000,
                timerProgressBar: true,
                customClass: {
                    popup: 'rounded-[2rem] border-none shadow-2xl',
                    confirmButton: 'rounded-xl px-10 py-3 text-sm font-bold uppercase tracking-widest shadow-lg shadow-health-100'
                },
                didOpen: () => {
                    const countdownEl = Swal.getHtmlContainer().querySelector('#swal-countdown');
                    timerInterval = setInterval(() => {
                        timeLeft--;
                        if (countdownEl) countdownEl.textContent = timeLeft;
                    }, 1000);
                },
                willClose: () => {
                    clearInterval(timerInterval);
                    window.location.href = 'login.php';
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'login.php';
                }
            });
        });
        <?php endif; ?>
    </script>
</body>
</html>
