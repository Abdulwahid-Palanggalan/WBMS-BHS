<?php
// -----------------------------------------
// Sidebar Configuration and Base URL Setup
// -----------------------------------------

// Get the current page filename
$currentPage = basename($_SERVER['PHP_SELF']);

// Automatically build a consistent base URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];

// Use centralized base URL
$baseUrl = $GLOBALS['base_url'];
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];

// -----------------------------------------
// Dynamic Sidebar Loading Based on Role - FIXED PATH
// -----------------------------------------

$userRole = $_SESSION['role'] ?? 'guest';

// Use absolute path to be sure
$rootPath = __DIR__; // This gives the includes folder path
$sidebarDir = $rootPath . '/sidebars/';

// Available roles
$availableRoles = ['admin', 'midwife', 'bhw', 'bns', 'mother'];
$sidebarFile = $sidebarDir . 'sidebar_' . $userRole . '.php';

// Check if role-specific sidebar exists
if (in_array($userRole, $availableRoles) && file_exists($sidebarFile)) {
    include_once $sidebarFile;
} else {
    // Use simple fallback sidebar
    ?>
    <!-- FALLBACK SIDEBAR -->
    <div class="sidebar col-md-3 col-lg-2 d-md-block bg-light">
        <div class="position-sticky pt-3">
            <div class="sidebar-header text-center p-3 border-bottom">
                <h6>KIBENES eBIRTH</h6>
                <small class="text-muted">Role: <?php echo htmlspecialchars($userRole); ?></small>
            </div>
            <ul class="nav flex-column mt-3">
                <li class="nav-item">
                    <a class="nav-link <?= ($currentPage == 'dashboard.php') ? 'active' : '' ?>" 
                       href="<?= $baseUrl ?>/dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($currentPage == 'profile.php') ? 'active' : '' ?>" 
                       href="<?= $baseUrl ?>/profile.php">
                        <i class="fas fa-user me-2"></i>Profile
                    </a>
                </li>
            </ul>
        </div>
    </div>
    <?php
}
?>