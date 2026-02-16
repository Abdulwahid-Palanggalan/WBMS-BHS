<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$baseUrl = $GLOBALS['base_url'];
?>
<!-- DEFAULT SIDEBAR - PURE WHITE -->
<div class="sidebar col-md-3 col-lg-2 d-md-block bg-white shadow-sm">
    <div class="position-sticky pt-3">
        <div class="sidebar-header text-center p-3 border-bottom">
            <h6 class="text-secondary">KIBENES eBIRTH</h6>
            <small class="text-muted">Health Management System</small>
        </div>
        
        <ul class="nav flex-column mt-3">
            <li class="nav-item">
                <a class="nav-link text-dark <?= ($currentPage == 'dashboard.php') ? 'active bg-light text-secondary' : '' ?>" 
                   href="<?= $baseUrl ?>/dashboard.php">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-dark <?= ($currentPage == 'profile.php') ? 'active bg-light text-secondary' : '' ?>" 
                   href="<?= $baseUrl ?>/profile.php">
                    <i class="fas fa-user me-2"></i>Profile
                </a>
            </li>
        </ul>
    </div>
</div>