<?php
// Determine the base URL dynamically
$baseUrl = $GLOBALS['base_url'] ?? '';

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

<!-- Tailwind Configuration & Custom Styles -->
<?php include_once 'tailwind_config.php'; ?>

<header class="sticky top-0 z-50 bg-white/80 backdrop-blur-md border-b border-slate-200">
    <div class="container mx-auto px-4">
        <div class="flex items-center justify-between h-16">
            <!-- Left: Logo & Toggle -->
            <div class="flex items-center gap-3">
                <!-- Mobile Menu Toggle -->
                <button id="sidebarToggleBtn" class="lg:hidden p-2 text-slate-600 hover:bg-slate-100 rounded-lg transition-colors">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                
                <!-- Logo -->
                <a href="<?php echo $baseUrl; ?>/dashboard.php" class="flex items-center gap-2 group">
                    <div class="w-10 h-10 bg-health-600 rounded-xl flex items-center justify-center text-white shadow-sm group-hover:bg-health-700 transition-colors">
                        <i class="fas fa-hospital"></i>
                    </div>
                    <div>
                        <span class="block text-lg font-bold text-slate-900 leading-tight">Health Station</span>
                        <span class="block text-[10px] font-semibold text-health-600 uppercase tracking-widest leading-none">Barangay Kibenes</span>
                    </div>
                </a>
            </div>

            <!-- Right: User Info & Actions -->
            <div class="flex items-center gap-4">
                <!-- User Profile (Desktop) -->
                <div class="hidden md:flex flex-col items-end mr-2">
                    <span class="text-sm font-semibold text-slate-800">
                        <?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?>
                    </span>
                    <span class="text-[10px] font-bold text-health-600 uppercase tracking-wider">
                        <?php echo $_SESSION['role']; ?>
                    </span>
                </div>

                <!-- Navigation Buttons -->
                <div class="flex items-center gap-2">
                    <?php if ($show_back_button): ?>
                        <button onclick="window.history.back()" class="p-2.5 text-slate-600 hover:bg-slate-100 rounded-xl transition-colors border border-transparent hover:border-slate-200 group" title="Go Back">
                            <i class="fas fa-arrow-left group-hover:-translate-x-0.5 transition-transform"></i>
                        </button>
                    <?php endif; ?>

                    <div class="h-8 w-px bg-slate-200 mx-1 hidden sm:block"></div>

                    <?php if ($show_nav_buttons): ?>
                        <a href="<?php echo $baseUrl; ?>/profile.php" class="hidden sm:flex items-center gap-2 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 rounded-xl transition-colors" title="My Profile">
                            <i class="fas fa-user-circle text-lg text-slate-400"></i>
                            <span>Profile</span>
                        </a>

                        <button onclick="confirmLogout(event)" class="flex items-center gap-2 px-4 py-2 text-sm font-semibold text-medical-red hover:bg-red-50 rounded-xl transition-colors" title="Sign Out">
                            <i class="fas fa-sign-out-alt"></i>
                            <span class="hidden sm:inline">Logout</span>
                        </button>
                    <?php endif; ?>
                    
                    <!-- Mobile Ellipsis/Menu (Optional, using icons for now) -->
                </div>
            </div>
        </div>
    </div>
</header>

<!-- Sidebar Overlay -->
<div id="sidebarOverlay" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-40 hidden transition-opacity duration-300 opacity-0"></div>

<!-- SweetAlert Script for Logout Confirmation -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function confirmLogout(event) {
    event.preventDefault();
    
    Swal.fire({
        title: 'Ready to leave?',
        text: 'Are you sure you want to logout from the Kibenes Health System?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#0D9488', // health-600
        cancelButtonColor: '#BE123C', // medical-red
        confirmButtonText: 'Yes, Sign Out',
        cancelButtonText: 'Stay',
        background: '#fff',
        borderRadius: '1rem',
        customClass: {
            title: 'text-xl font-bold text-slate-800',
            htmlContainer: 'text-slate-600'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '<?php echo $baseUrl; ?>/logout.php';
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

// Sidebar toggle (mobile)
document.addEventListener('DOMContentLoaded', function () {
    const toggleBtn = document.getElementById('sidebarToggleBtn');
    const sidebar   = document.querySelector('.sidebar'); // Assuming sidebar class
    const overlay   = document.getElementById('sidebarOverlay');

    function openSidebar() {
        if (sidebar) sidebar.classList.add('translate-x-0');
        if (sidebar) sidebar.classList.remove('-translate-x-full');
        if (overlay) {
            overlay.classList.remove('hidden');
            setTimeout(() => {
                overlay.classList.add('opacity-100');
                overlay.classList.remove('opacity-0');
            }, 10);
        }
    }

    function closeSidebar() {
        if (sidebar) sidebar.classList.remove('translate-x-0');
        if (sidebar) sidebar.classList.add('-translate-x-full');
        if (overlay) {
            overlay.classList.remove('opacity-100');
            overlay.classList.add('opacity-0');
            setTimeout(() => overlay.classList.add('hidden'), 300);
        }
    }

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function () {
            const isHidden = sidebar && sidebar.classList.contains('-translate-x-full');
            isHidden ? openSidebar() : closeSidebar();
        });
    }

    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }
});
</script>
