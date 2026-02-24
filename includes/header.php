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
        <!-- Sidebar Toggler for Mobile -->
        <button class="btn btn-link text-white d-lg-none me-2 p-0" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle navigation">
            <i class="fas fa-bars fa-lg"></i>
        </button>

        <a class="navbar-brand me-auto me-lg-4" href="<?php echo $GLOBALS['base_url']; ?>/dashboard.php">
            <i class="fas fa-hospital me-2"></i>
            <span class="d-none d-sm-inline">Health Station System</span>
            <span class="d-inline d-sm-none">BHS</span>
        </a>

        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="fas fa-ellipsis-v text-white"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <div class="d-flex flex-column flex-lg-row ms-auto align-items-lg-center mt-2 mt-lg-0">
                <span class="navbar-text text-white me-lg-3 mb-2 mb-lg-0 text-center text-lg-start">
                    <i class="fas fa-user-circle"></i>
                    <?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?>
                    <small class="badge bg-light text-dark ms-1"><?php echo $_SESSION['role']; ?></small>
                </span>
                
                <div class="d-flex flex-wrap justify-content-center gap-2">
                    <?php if ($show_back_button): ?>
                    <button class="btn btn-outline-light btn-sm px-3" onclick="window.history.back()">
                        <i class="fas fa-arrow-left"></i> <span class="d-none d-sm-inline ms-1">Back</span>
                    </button>
                    <?php endif; ?>
                    
                    <?php if ($show_nav_buttons): ?>
                    <a class="btn btn-outline-light btn-sm px-3" href="<?php echo $GLOBALS['base_url']; ?>/profile.php">
                        <i class="fas fa-user"></i> <span class="d-none d-sm-inline ms-1">Profile</span>
                    </a>
                    
                    <a class="btn btn-danger btn-sm px-3" href="#" onclick="confirmLogout(event)">
                        <i class="fas fa-sign-out-alt"></i> <span class="d-none d-sm-inline ms-1">Logout</span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
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