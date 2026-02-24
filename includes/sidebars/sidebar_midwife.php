<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$baseUrl = $GLOBALS['base_url'];
?>
<!-- MIDWIFE SIDEBAR - PURE WHITE -->
<div class="sidebar col-md-3 col-lg-2 bg-white shadow-sm p-0 collapse d-lg-block" id="sidebarMenu">
    <div class="d-flex flex-column flex-shrink-0 p-3">
        <div class="sidebar-header text-center p-3 border-bottom">
            <h5 class="text-primary"><i class="fas fa-user-nurse me-2"></i>MIDWIFE PANEL</h5>
            <small class="text-muted">Maternal & Child Care</small>
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
                <a class="nav-link text-dark <?= ($currentPage == 'mother_registration.php') ? 'active bg-light text-primary' : '' ?>" 
                   href="<?= $baseUrl ?>/forms/mother_registration.php">
                    <i class="fas fa-user-plus me-2"></i>Register Mother
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-dark <?= ($currentPage == 'prenatal_form.php') ? 'active bg-light text-primary' : '' ?>" 
                   href="<?= $baseUrl ?>/forms/prenatal_form.php">
                    <i class="fas fa-heartbeat me-2"></i>Prenatal Care
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-dark <?= ($currentPage == 'postnatal_form.php') ? 'active bg-light text-primary' : '' ?>" 
                   href="<?= $baseUrl ?>/forms/postnatal_form.php">
                    <i class="fas fa-child me-2"></i>Postnatal Care
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-dark <?= ($currentPage == 'immunization_records.php' || $currentPage == 'immunization_form.php') ? 'active bg-light text-primary' : '' ?>" 
                   href="<?= $baseUrl ?>/immunization_records.php">
                    <i class="fas fa-syringe me-2"></i>Immunization
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-dark <?= ($currentPage == 'family_planning.php') ? 'active bg-light text-primary' : '' ?>" 
                   href="<?= $baseUrl ?>/family_planning.php">
                    <i class="fas fa-pills me-2"></i>Family Planning
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-dark <?= ($currentPage == 'reports.php') ? 'active bg-light text-primary' : '' ?>" 
                   href="<?= $baseUrl ?>/reports.php">
                    <i class="fas fa-chart-bar me-2"></i>Reports
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
                    <i class="fas fa-user me-2"></i>Profile
                </a>
            </li>
        </ul>
    </div>
</div>