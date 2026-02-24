<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$baseUrl = $GLOBALS['base_url'] ?? '';
?>

<!-- Admin Branding Section -->
<div class="mb-8 px-2 flex items-center gap-3">
    <div class="w-10 h-10 bg-health-600 rounded-xl flex items-center justify-center text-white shadow-soft">
        <i class="fas fa-user-shield"></i>
    </div>
    <div>
        <h3 class="text-sm font-bold text-slate-800 tracking-tight">System Admin</h3>
        <p class="text-[10px] font-semibold text-health-600 uppercase tracking-wider">Full Control</p>
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

    <!-- Health Records Group -->
    <div>
        <h4 class="px-2 mb-3 text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em]">Health Records</h4>
        <div class="space-y-1">
            <?php
            $navItems = [
                ['url' => 'birth_records.php', 'icon' => 'fa-baby', 'label' => 'Birth Records', 'active' => ($currentPage == 'birth_records.php' || $currentPage == 'birth_registration.php')],
                ['url' => 'mothers_list.php', 'icon' => 'fa-female', 'label' => 'Mothers', 'active' => in_array($currentPage, ['mothers_list.php','mother_registration.php','mother_profile.php'])],
                ['url' => 'pregnant_women.php', 'icon' => 'fa-baby-carriage', 'label' => 'Pregnant Women', 'active' => ($currentPage == 'pregnant_women.php')],
                ['url' => 'prenatal_records.php', 'icon' => 'fa-heart-pulse', 'label' => 'Prenatal Care', 'active' => in_array($currentPage, ['prenatal_records.php','prenatal_form.php'])],
                ['url' => 'postnatal_records.php', 'icon' => 'fa-child-reaching', 'label' => 'Postnatal Care', 'active' => in_array($currentPage, ['postnatal_records.php','postnatal_form.php'])],
                ['url' => 'immunization_records.php', 'icon' => 'fa-syringe', 'label' => 'Immunization', 'active' => in_array($currentPage, ['immunization_records.php','immunization_form.php'])],
                ['url' => 'family_planning.php', 'icon' => 'fa-pills', 'label' => 'Family Planning', 'active' => ($currentPage == 'family_planning.php')],
            ];

            foreach ($navItems as $item): ?>
                <a href="<?= $baseUrl ?>/<?= $item['url'] ?>" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl transition-all <?= $item['active'] ? 'bg-health-50 text-health-700 shadow-sm shadow-health-100/50' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 group' ?>">
                    <div class="flex items-center justify-center w-5 h-5">
                        <i class="fas <?= $item['icon'] ?> text-sm <?= $item['active'] ? 'text-health-600' : 'text-slate-400 group-hover:text-slate-600' ?>"></i>
                    </div>
                    <span><?= $item['label'] ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Administration Group -->
    <div>
        <h4 class="px-2 mb-3 text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em]">System Admin</h4>
        <div class="space-y-1">
            <a href="<?= $baseUrl ?>/user_management.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl transition-all <?= ($currentPage == 'user_management.php') ? 'bg-health-50 text-health-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 group' ?>">
                <i class="fas fa-users-cog w-5 text-center <?= ($currentPage == 'user_management.php') ? 'text-health-600' : 'text-slate-400 group-hover:text-slate-600' ?>"></i>
                <span>User Management</span>
            </a>
            <a href="<?= $baseUrl ?>/reports.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl transition-all <?= ($currentPage == 'reports.php') ? 'bg-health-50 text-health-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 group' ?>">
                <i class="fas fa-chart-pie w-5 text-center <?= ($currentPage == 'reports.php') ? 'text-health-600' : 'text-slate-400 group-hover:text-slate-600' ?>"></i>
                <span>Reports</span>
            </a>
            <a href="<?= $baseUrl ?>/activity_logs.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl transition-all <?= ($currentPage == 'activity_logs.php') ? 'bg-health-50 text-health-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 group' ?>">
                <i class="fas fa-clock-rotate-left w-5 text-center <?= ($currentPage == 'activity_logs.php') ? 'text-health-600' : 'text-slate-400 group-hover:text-slate-600' ?>"></i>
                <span>Activity Logs</span>
            </a>
        </div>
    </div>

    <!-- Resources Group -->
    <div>
        <h4 class="px-2 mb-3 text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em]">Resources</h4>
        <div class="space-y-1">
            <a href="<?= $baseUrl ?>/library.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl transition-all <?= ($currentPage == 'library.php') ? 'bg-health-50 text-health-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 group' ?>">
                <i class="fas fa-book-medical w-5 text-center <?= ($currentPage == 'library.php') ? 'text-health-600' : 'text-slate-400 group-hover:text-slate-600' ?>"></i>
                <span>Health Library</span>
            </a>
        </div>
    </div>
</nav>

<!-- Sidebar Footer Info -->
<div class="mt-12 px-2 py-4 bg-slate-50 rounded-2xl border border-slate-100">
    <div class="flex items-center gap-3 mb-2">
        <div class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div>
        <span class="text-[10px] font-bold text-slate-500 uppercase">System Status</span>
    </div>
    <p class="text-[10px] text-slate-400 leading-relaxed">Logged in as admin. Your session is active and secure.</p>
</div>
