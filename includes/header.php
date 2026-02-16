<?php
// Determine the base URL dynamically
// Base URL is now centrally handled in database.php
$baseUrl = $GLOBALS['base_url'];

// Get current page name
$current_page = basename($_SERVER['PHP_SELF']);

// Check if current page is dashboard
$show_nav_buttons = ($current_page == 'dashboard.php');
$show_back_button = ($current_page != 'dashboard.php');

// Now require auth if not already required
if (!function_exists('isLoggedIn')) {
    require_once dirname(__FILE__) . '/auth.php';
}
?>
<nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(to right, #1a73e8, #6a11cb);">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?php echo $GLOBALS['base_url']; ?>/dashboard.php">
            <i class="fas fa-hospital me-2"></i>
            Health Station System
        </a>

        <div class="d-flex ms-auto">
            <span class="navbar-text text-white me-3">
                <i class="fas fa-user-circle"></i>
                <?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?>
                <small class="badge bg-light text-dark ms-1"><?php echo $_SESSION['role']; ?></small>
            </span>
            
            <?php if ($show_back_button): ?>
            <button class="btn btn-outline-light me-2" onclick="window.history.back()">
                <i class="fas fa-arrow-left"></i> Back
            </button>
            <?php endif; ?>
            
            <?php if ($show_nav_buttons): ?>
            <a class="btn btn-outline-light me-2" href="<?php echo $GLOBALS['base_url']; ?>/profile.php">
                <i class="fas fa-user"></i> Profile
            </a>
            <?php endif; ?>
            
            <!-- Logout Button - Only show on dashboard -->
            <?php if ($show_nav_buttons): ?>
            <a class="btn btn-danger" href="#" onclick="confirmLogout(event)">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<!-- SweetAlert Script for Logout Confirmation -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function confirmLogout(event) {
    event.preventDefault();
    
    Swal.fire({
        title: 'Logout?',
        text: 'Are you sure you want to logout?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, logout!',
        cancelButtonText: 'Cancel',
        background: 'white',
        color: 'black'
    }).then((result) => {
        if (result.isConfirmed) {
            // Create a form to submit logout request
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '<?php echo $GLOBALS['base_url']; ?>/logout.php';
            
            // Add CSRF token if you have one
            const csrfToken = document.createElement('input');
            csrfToken.type = 'hidden';
            csrfToken.name = 'logout';
            csrfToken.value = 'true';
            form.appendChild(csrfToken);
            
            document.body.appendChild(form);
            form.submit();
        }
    });
}
</script>