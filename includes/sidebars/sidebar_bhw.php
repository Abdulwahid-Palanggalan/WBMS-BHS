<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$baseUrl = $GLOBALS['base_url'];
?>
<!-- BHW SIDEBAR - PURE WHITE -->
<div class="sidebar bg-white shadow-sm p-0 collapse d-md-block" id="sidebarMenu">
    <div class="d-flex flex-column flex-shrink-0 p-3">
        <div class="sidebar-header text-center p-3 border-bottom">
            <h5 class="text-primary"><i class="fas fa-home me-2"></i>BHW PANEL</h5>
            <small class="text-muted">Community Health Monitoring</small>
        </div>
        
        <ul class="nav nav-pills flex-column mb-auto mt-3">
            <li class="nav-item">
                <a class="nav-link text-dark <?= ($currentPage == 'dashboard.php') ? 'active bg-light text-primary' : '' ?>" 
                   href="<?= $baseUrl ?>/dashboard.php">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-dark <?= ($currentPage == 'reports.php') ? 'active bg-light text-primary' : '' ?>" 
                   href="<?= $baseUrl ?>/reports.php">
                    <i class="fas fa-chart-bar me-2"></i>Reports
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-dark <?= ($currentPage == 'profile.php') ? 'active bg-light text-primary' : '' ?>" 
                   href="<?= $baseUrl ?>/profile.php">
                    <i class="fas fa-user me-2"></i>Profile
                </a>
            </li>
        </ul>
    </div>
</div>