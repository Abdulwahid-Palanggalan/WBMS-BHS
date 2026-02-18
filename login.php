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
        
        .login-container {
            max-width: 320px;
            width: 100%;
        }
        
        .login-card {
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            border: none;
            overflow: hidden;
            background: white;
        }
        
        .login-header {
            background: linear-gradient(135deg, #1a73e8 0%, #6a11cb 100%);
            color: white;
            padding: 20px 15px;
            text-align: center;
        }
        
        .login-icon {
            font-size: 1.8rem;
            margin-bottom: 10px;
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            width: 60px;
            height: 60px;
            border-radius: 50%;
            line-height: 60px;
        }
        
        .login-header h3 {
            margin-bottom: 3px;
            font-weight: 600;
            font-size: 1.3rem;
        }
        
        .login-header p {
            margin-bottom: 0;
            opacity: 0.9;
            font-size: 0.85rem;
        }
        
        .login-body {
            padding: 20px;
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
        
        .input-group-text {
            background: white;
            border: 1.5px solid #e0e0e0;
            border-right: none;
            border-radius: 8px 0 0 8px;
            padding: 10px 12px;
        }
        
        .input-group .form-control {
            border-left: none;
            border-radius: 0 8px 8px 0;
        }
        
        .btn-login {
            background: linear-gradient(to right, #1a73e8, #6a11cb);
            border: none;
            padding: 10px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.95rem;
        }
        
        .btn-login:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(26, 115, 232, 0.3);
        }
        
        .alert {
            border-radius: 8px;
            border: none;
            padding: 10px 12px;
            font-size: 0.85rem;
            margin-bottom: 15px;
        }
        
        .login-footer {
            background-color: #f8f9fa;
            padding: 12px 15px;
            text-align: center;
            border-top: 1px solid #e0e0e0;
        }
        
        .login-footer a {
            color: #1a73e8;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.85rem;
        }
        
        .login-footer a:hover {
            color: #6a11cb;
            text-decoration: underline;
        }
        
        .register-link {
            text-align: center;
            margin-top: 15px;
        }
        
        .register-link a {
            color: #1a73e8;
            font-weight: 500;
            text-decoration: none;
            font-size: 0.9rem;
        }
        
        .register-link a:hover {
            text-decoration: underline;
        }
        
        .form-check {
            margin-bottom: 15px;
        }
        
        .form-check-label {
            font-size: 0.9rem;
        }
        
        .password-toggle {
            cursor: pointer;
            border-left: none;
            border-radius: 0 8px 8px 0 !important;
            background: white;
            color: #6c757d;
            transition: color 0.2s;
        }
        
        .password-toggle:hover {
            color: #1a73e8;
        }

        .input-group-password .form-control {
            border-right: none;
            border-radius: 0;
        }
        
        .debug-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 10px;
            margin-top: 15px;
            font-size: 0.8rem;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="login-icon">
                    <i class="fas fa-baby"></i>
                </div>
                <h3>Barangay Kibenes</h3>
                <p>Health Station System</p>
            </div>
            
            <div class="login-body">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger">
                        <?php echo htmlspecialchars($error); ?>
                        <?php if (isset($_POST['username'])): ?>
                            <div class="debug-info">
                                <strong>Debug Info:</strong><br>
                                Username: <?php echo htmlspecialchars($_POST['username']); ?><br>
                                Check your error logs for more details.
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="mb-3">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" class="form-control" id="username" name="username" placeholder="Username or Email" required value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="input-group input-group-password">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                            <span class="input-group-text password-toggle" onclick="togglePassword()">
                                <i class="fas fa-eye" id="toggleIcon"></i>
                            </span>
                        </div>
                    </div>
                    
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="remember">
                        <label class="form-check-label" for="remember">Remember me</label>
                    </div>
                    
                    <button type="submit" class="btn btn-login w-100">Login</button>
                </form>
                
                <div class="register-link">
                    <p>No account? <a href="register.php">Register</a></p>
                </div>
            </div>
            
            <div class="login-footer">
                <small>
                    <a href="forgot-password.php">Forgot password?</a> 
                </small>
            </div>
        </div>
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