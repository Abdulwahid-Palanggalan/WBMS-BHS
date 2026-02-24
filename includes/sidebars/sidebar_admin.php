<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$baseUrl = $GLOBALS['base_url'];
?>
<!-- ADMIN SIDEBAR -->
<div class="sidebar bg-white shadow-sm" id="sidebarMenu">

    <!-- Brand -->
    <div class="px-3 pb-2 border-bottom mb-1">
        <div class="d-flex align-items-center gap-2 py-2">
            <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center" style="width:32px;height:32px;flex-shrink:0;">
                <i class="fas fa-shield-alt text-white" style="font-size:.8rem;"></i>
            </div>
            <div>
                <div class="fw-bold text-primary lh-sm" style="font-size:.85rem;">ADMIN PANEL</div>
                <div class="text-muted" style="font-size:.7rem;">Full Access</div>
            </div>
        </div>
    </div>

    <nav class="px-1">

        <!-- Main -->
        <div class="sidebar-section-label">Main</div>
        <ul class="nav nav-pills flex-column">
            <li class="nav-item">
                <a class="nav-link <?= ($currentPage == 'dashboard.php') ? 'active' : 'text-dark' ?>"
                   href="<?= $baseUrl ?>/dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
        </ul>

        <!-- Records -->
        <div class="sidebar-section-label">Records</div>
        <ul class="nav nav-pills flex-column">
            <li class="nav-item">
                <a class="nav-link <?= ($currentPage == 'birth_records.php' || $currentPage == 'birth_registration.php') ? 'active' : 'text-dark' ?>"
                   href="<?= $baseUrl ?>/birth_records.php">
                    <i class="fas fa-baby"></i> Birth Records
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= in_array($currentPage, ['mothers_list.php','mother_registration.php','mother_profile.php']) ? 'active' : 'text-dark' ?>"
                   href="<?= $baseUrl ?>/mothers_list.php">
                    <i class="fas fa-female"></i> Mothers
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= in_array($currentPage, ['pregnant_women.php']) ? 'active' : 'text-dark' ?>"
                   href="<?= $baseUrl ?>/pregnant_women.php">
                    <i class="fas fa-baby-carriage"></i> Pregnant Women
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= in_array($currentPage, ['prenatal_records.php','prenatal_form.php']) ? 'active' : 'text-dark' ?>"
                   href="<?= $baseUrl ?>/prenatal_records.php">
                    <i class="fas fa-heartbeat"></i> Prenatal Care
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= in_array($currentPage, ['postnatal_records.php','postnatal_form.php']) ? 'active' : 'text-dark' ?>"
                   href="<?= $baseUrl ?>/postnatal_records.php">
                    <i class="fas fa-child"></i> Postnatal Care
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= in_array($currentPage, ['immunization_records.php','immunization_form.php']) ? 'active' : 'text-dark' ?>"
                   href="<?= $baseUrl ?>/immunization_records.php">
                    <i class="fas fa-syringe"></i> Immunization
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($currentPage == 'family_planning.php') ? 'active' : 'text-dark' ?>"
                   href="<?= $baseUrl ?>/family_planning.php">
                    <i class="fas fa-pills"></i> Family Planning
                </a>
            </li>
        </ul>

        <!-- Admin -->
        <div class="sidebar-section-label">Admin</div>
        <ul class="nav nav-pills flex-column">
            <li class="nav-item">
                <a class="nav-link <?= ($currentPage == 'user_management.php') ? 'active' : 'text-dark' ?>"
                   href="<?= $baseUrl ?>/user_management.php">
                    <i class="fas fa-users-cog"></i> User Management
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($currentPage == 'reports.php') ? 'active' : 'text-dark' ?>"
                   href="<?= $baseUrl ?>/reports.php">
                    <i class="fas fa-chart-bar"></i> Reports
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($currentPage == 'activity_logs.php') ? 'active' : 'text-dark' ?>"
                   href="<?= $baseUrl ?>/activity_logs.php">
                    <i class="fas fa-history"></i> Activity Logs
                </a>
            </li>
        </ul>

        <!-- Account -->
        <div class="sidebar-section-label">Account</div>
        <ul class="nav nav-pills flex-column mb-3">
            <li class="nav-item">
                <a class="nav-link <?= ($currentPage == 'library.php') ? 'active' : 'text-dark' ?>"
                   href="<?= $baseUrl ?>/library.php">
                    <i class="fas fa-book-medical"></i> Health Library
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($currentPage == 'profile.php') ? 'active' : 'text-dark' ?>"
                   href="<?= $baseUrl ?>/profile.php">
                    <i class="fas fa-user"></i> My Profile
                </a>
            </li>
        </ul>

    </nav>
</div>