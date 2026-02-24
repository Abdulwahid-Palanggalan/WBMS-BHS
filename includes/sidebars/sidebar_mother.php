<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$baseUrl = $GLOBALS['base_url'];
?>
<!-- MOTHER SIDEBAR - PURE WHITE -->
<div class="sidebar bg-white shadow-sm" id="sidebarMenu">
    <div class="d-flex flex-column flex-shrink-0 p-3">
        <div class="sidebar-header text-center p-3 border-bottom">
            <h5 class="text-primary"><i class="fas fa-user me-2"></i>MOTHER'S PORTAL</h5>
            <small class="text-muted">My Pregnancy Journey</small>
        </div>
        
        <ul class="nav nav-pills flex-column mb-auto mt-3">
            <li class="nav-item">
                <a class="nav-link text-dark <?= ($currentPage == 'dashboard.php') ? 'active bg-light text-primary' : '' ?>" 
                   href="<?= $baseUrl ?>/dashboard.php">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-dark <?= ($currentPage == 'birth_registration.php') ? 'active bg-light text-primary' : '' ?>" 
                   href="<?= $baseUrl ?>/forms/birth_registration.php">
                    <i class="fas fa-baby me-2"></i>Birth Registration
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-dark <?= ($currentPage == 'mother_self_registration.php') ? 'active bg-light text-primary' : '' ?>" 
                   href="<?= $baseUrl ?>/forms/mother_self_registration.php">
                    <i class="fas fa-user-edit me-2"></i>My Mother Profile
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-dark <?= ($currentPage == 'library.php') ? 'active bg-light text-primary' : '' ?>" 
                   href="<?= $baseUrl ?>/library.php">
                    <i class="fas fa-book-medical me-2"></i>Health Library
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-dark <?= ($currentPage == 'profile.php') ? 'active bg-light text-primary' : '' ?>" 
                   href="<?= $baseUrl ?>/profile.php">
                    <i class="fas fa-cog me-2"></i>Profile
                </a>
            </li>
        </ul>
    </div>
</div>