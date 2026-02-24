<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$baseUrl = $GLOBALS['base_url'] ?? '';
?>
<!-- BHW SIDEBAR - PURE WHITE -->
<div class="sidebar bg-white shadow-sm" id="sidebarMenu">
    <!-- BHW Branding Section -->
    <div class="mb-8 px-2 flex items-center gap-3">
        <div class="w-10 h-10 bg-emerald-600 rounded-xl flex items-center justify-center text-white shadow-soft">
            <i class="fas fa-hand-holding-hand"></i>
        </div>
        <div>
            <h3 class="text-sm font-bold text-slate-800 tracking-tight">BHW Panel</h3>
            <p class="text-[10px] font-semibold text-emerald-600 uppercase tracking-wider">Community Outreach</p>
        </div>
    </div>

    <nav class="space-y-8">
        <!-- Main Group -->
        <div>
            <h4 class="px-2 mb-3 text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em]">General</h4>
            <div class="space-y-1">
                <a href="<?= $baseUrl ?>/dashboard.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl transition-all <?= ($currentPage == 'dashboard.php') ? 'bg-emerald-50 text-emerald-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 group' ?>">
                    <i class="fas fa-grid-2 w-5 text-center <?= ($currentPage == 'dashboard.php') ? 'text-emerald-600' : 'text-slate-400 group-hover:text-slate-600' ?>"></i>
                    <span>Dashboard</span>
                </a>
            </div>
        </div>

        <!-- Community Health Group -->
        <div>
            <h4 class="px-2 mb-3 text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em]">Monitoring</h4>
            <div class="space-y-1">
                <a href="<?= $baseUrl ?>/reports.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl transition-all <?= ($currentPage == 'reports.php') ? 'bg-emerald-50 text-emerald-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 group' ?>">
                    <i class="fas fa-chart-line w-5 text-center <?= ($currentPage == 'reports.php') ? 'text-emerald-600' : 'text-slate-400 group-hover:text-slate-600' ?>"></i>
                    <span>Reports</span>
                </a>
                <a href="<?= $baseUrl ?>/library.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl transition-all <?= ($currentPage == 'library.php') ? 'bg-emerald-50 text-emerald-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 group' ?>">
                    <i class="fas fa-book-medical w-5 text-center <?= ($currentPage == 'library.php') ? 'text-emerald-600' : 'text-slate-400 group-hover:text-slate-600' ?>"></i>
                    <span>Health Library</span>
                </a>
            </div>
        </div>

        <!-- System Group -->
        <div>
            <h4 class="px-2 mb-3 text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em]">Settings</h4>
            <div class="space-y-1">
                <a href="<?= $baseUrl ?>/profile.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl transition-all <?= ($currentPage == 'profile.php') ? 'bg-emerald-50 text-emerald-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 group' ?>">
                    <i class="fas fa-user-circle w-5 text-center <?= ($currentPage == 'profile.php') ? 'text-emerald-600' : 'text-slate-400 group-hover:text-slate-600' ?>"></i>
                    <span>My Profile</span>
                </a>
            </div>
        </div>
    </nav>
</div>