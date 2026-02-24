<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$baseUrl = $GLOBALS['base_url'] ?? '';
?>

<!-- Midwife Branding Section -->
<div class="mb-8 px-2 flex items-center gap-3">
    <div class="w-10 h-10 bg-health-600 rounded-xl flex items-center justify-center text-white shadow-soft">
        <i class="fas fa-user-nurse"></i>
    </div>
    <div>
        <h3 class="text-sm font-bold text-slate-800 tracking-tight">Midwife Panel</h3>
        <p class="text-[10px] font-semibold text-health-600 uppercase tracking-wider">Maternal & Child Care</p>
    </div>
</div>

<nav class="space-y-8">
    <!-- Main Group -->
    <div>
        <h4 class="px-2 mb-3 text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em]">General</h4>
        <div class="space-y-1">
            <a href="<?= $baseUrl ?>/dashboard.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl transition-all <?= ($currentPage == 'dashboard.php') ? 'bg-health-50 text-health-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 group' ?>">
                <i class="fas fa-th-large w-5 text-center <?= ($currentPage == 'dashboard.php') ? 'text-health-600' : 'text-slate-400 group-hover:text-slate-600' ?>"></i>
                <span>Dashboard</span>
            </a>
        </div>
    </div>

    <!-- Maternal Care Group -->
    <div>
        <h4 class="px-2 mb-3 text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em]">Maternal Care</h4>
        <div class="space-y-1">
            <?php
            $navItems = [
                ['url' => 'forms/birth_registration.php', 'icon' => 'fa-baby', 'label' => 'Register Birth', 'active' => ($currentPage == 'birth_registration.php')],
                ['url' => 'forms/mother_registration.php', 'icon' => 'fa-user-plus', 'label' => 'Register Mother', 'active' => ($currentPage == 'mother_registration.php')],
                ['url' => 'forms/prenatal_form.php', 'icon' => 'fa-heart-pulse', 'label' => 'Prenatal Care', 'active' => ($currentPage == 'prenatal_form.php')],
                ['url' => 'forms/postnatal_form.php', 'icon' => 'fa-child-reaching', 'label' => 'Postnatal Care', 'active' => ($currentPage == 'postnatal_form.php')],
                ['url' => 'immunization_records.php', 'icon' => 'fa-syringe', 'label' => 'Immunization', 'active' => ($currentPage == 'immunization_records.php' || $currentPage == 'immunization_form.php')],
                ['url' => 'family_planning.php', 'icon' => 'fa-pills', 'label' => 'Family Planning', 'active' => ($currentPage == 'family_planning.php')],
            ];

            foreach ($navItems as $item): ?>
                <a href="<?= $baseUrl ?>/<?= $item['url'] ?>" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl transition-all <?= $item['active'] ? 'bg-health-50 text-health-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 group' ?>">
                    <div class="flex items-center justify-center w-5 h-5">
                        <i class="fas <?= $item['icon'] ?> text-sm <?= $item['active'] ? 'text-health-600' : 'text-slate-400 group-hover:text-slate-600' ?>"></i>
                    </div>
                    <span><?= $item['label'] ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- System Group -->
    <div>
        <h4 class="px-2 mb-3 text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em]">Services</h4>
        <div class="space-y-1">
            <a href="<?= $baseUrl ?>/reports.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl transition-all <?= ($currentPage == 'reports.php') ? 'bg-health-50 text-health-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 group' ?>">
                <i class="fas fa-chart-pie w-5 text-center <?= ($currentPage == 'reports.php') ? 'text-health-600' : 'text-slate-400 group-hover:text-slate-600' ?>"></i>
                <span>Reports</span>
            </a>
            <a href="<?= $baseUrl ?>/library.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl transition-all <?= ($currentPage == 'library.php') ? 'bg-health-50 text-health-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 group' ?>">
                <i class="fas fa-book-medical w-5 text-center <?= ($currentPage == 'library.php') ? 'text-health-600' : 'text-slate-400 group-hover:text-slate-600' ?>"></i>
                <span>Health Library</span>
            </a>
            <a href="<?= $baseUrl ?>/profile.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl transition-all <?= ($currentPage == 'profile.php') ? 'bg-health-50 text-health-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 group' ?>">
                <i class="fas fa-user-circle w-5 text-center <?= ($currentPage == 'profile.php') ? 'text-health-600' : 'text-slate-400 group-hover:text-slate-600' ?>"></i>
                <span>My Profile</span>
            </a>
        </div>
    </div>
</nav>
