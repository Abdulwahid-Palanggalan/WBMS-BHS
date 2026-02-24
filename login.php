<?php
// login.php

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Debug: Check if config file exists
if (!file_exists('config/config.php')) {
    die('<div class="alert alert-danger">Error: config/config.php not found. Please check your file structure.</div>');
}

// Use central configuration
require_once 'config/config.php';

// Debug: Check if auth functions are available
if (!function_exists('isLoggedIn')) {
    die('<div class="alert alert-danger">Error: Auth functions not loaded. Please check includes/auth.php</div>');
}

// Initialize error variable
$error = '';

// Check for logout success message
$logout_success = isset($_GET['logout']) && $_GET['logout'] === 'success';

// Now we can use functions from auth.php and functions.php
if (isLoggedIn()) {
    redirectBasedOnRole();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    // Debug output
    error_log("Login attempt - Username: $username");
    
    $user = loginUser($username, $password);
    
    if ($user && is_array($user)) {
        error_log("Login successful for user: " . $user['username']);
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name'] = $user['last_name'];
        
        logActivity($user['id'], "User logged in");
        
        redirectBasedOnRole();
    } else {
        $error = "Invalid username or password, or account not activated";
        error_log("Login failed for username: $username");
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kibenes eBirth - Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include_once 'includes/tailwind_config.php'; ?>
</head>
<body class="min-h-screen flex items-center justify-center p-4 bg-gradient-to-br from-health-50 via-white to-health-100">
    <div class="max-w-md w-full">
        <!-- Brand Header -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-health-600 rounded-2xl shadow-lg shadow-health-200 mb-4 transform hover:scale-105 transition-transform duration-300">
                <i class="fas fa-heartbeat text-white text-4xl"></i>
            </div>
            <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Barangay Kibenes</h1>
            <p class="text-health-600 font-medium">Health Station Information System</p>
        </div>

        <div class="card-health p-8 space-y-6">
            <div class="space-y-2">
                <h2 class="text-xl font-semibold text-slate-800">Welcome back</h2>
                <p class="text-sm text-slate-500 text-balance">Enter your credentials to access the system components.</p>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="p-4 bg-red-50 border border-red-100 rounded-xl flex items-start gap-3 text-red-700 animate-in fade-in slide-in-from-top-2 duration-300">
                    <i class="fas fa-exclamation-circle mt-1"></i>
                    <div class="text-sm">
                        <p class="font-medium"><?php echo htmlspecialchars($error); ?></p>
                        <?php if (isset($_POST['username'])): ?>
                            <div class="mt-2 text-xs opacity-80 font-mono bg-white/50 p-2 rounded">
                                <strong>Debug:</strong> <?php echo htmlspecialchars($_POST['username']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" class="space-y-5">
                <div class="space-y-1.5">
                    <label for="username" class="text-sm font-medium text-slate-700 ml-1">Username or Email</label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 group-focus-within:text-health-500 transition-colors">
                            <i class="fas fa-user-md"></i>
                        </div>
                        <input type="text" class="input-health pl-10" id="username" name="username" placeholder="Enter your username" required value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                    </div>
                </div>
                
                <div class="space-y-1.5">
                    <div class="flex justify-between items-center px-1">
                        <label for="password" class="text-sm font-medium text-slate-700">Password</label>
                        <a href="forgot-password.php" class="text-xs font-semibold text-health-600 hover:text-health-700 transition-colors">Forgot password?</a>
                    </div>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 group-focus-within:text-health-500 transition-colors">
                            <i class="fas fa-key"></i>
                        </div>
                        <input type="password" class="input-health pl-10 pr-10" id="password" name="password" placeholder="••••••••" required>
                        <button type="button" onclick="togglePassword()" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-health-500 transition-colors">
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>
                
                <div class="flex items-center px-1">
                    <input type="checkbox" class="w-4 h-4 text-health-600 border-slate-300 rounded focus:ring-health-500" id="remember">
                    <label class="ml-2 text-sm text-slate-600" for="remember">Keep me signed in</label>
                </div>
                
                <button type="submit" class="w-full btn-health py-3 text-base flex items-center justify-center gap-2 group">
                    <span>Sign In</span>
                    <i class="fas fa-arrow-right text-sm group-hover:translate-x-1 transition-transform"></i>
                </button>
            </form>
            
            <div class="relative py-2">
                <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-slate-100"></div></div>
                <div class="relative flex justify-center text-xs uppercase"><span class="bg-white px-2 text-slate-400 font-medium">New to System?</span></div>
            </div>

            <div class="text-center">
                <a href="register.php" class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700 hover:text-health-600 transition-colors">
                    Create a new account
                </a>
            </div>
        </div>
        
        <p class="text-center mt-10 text-slate-400 text-xs">
            &copy; <?php echo date('Y'); ?> Barangay Kibenes Health Station. All rights reserved.
        </p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
    </script>
    
    <?php if ($logout_success): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            title: 'Logout Successful!',
            text: 'You have been logged out successfully.',
            icon: 'success',
            confirmButtonColor: '#3085d6',
            confirmButtonText: 'OK',
            background: 'white',
            color: 'black'
        }).then(() => {
            // Remove the logout parameter from URL without page reload
            if (window.history.replaceState) {
                const url = new URL(window.location);
                url.searchParams.delete('logout');
                window.history.replaceState({}, '', url);
            }
        });
    });
</script>
<?php endif; ?>
</body>
</html>