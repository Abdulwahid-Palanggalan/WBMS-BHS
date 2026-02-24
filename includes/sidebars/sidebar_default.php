<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$baseUrl = $GLOBALS['base_url'];
?>
<!-- DEFAULT SIDEBAR - PURE WHITE -->
<div class="mb-8 px-2 flex items-center gap-3">
    <div class="w-10 h-10 bg-slate-600 rounded-xl flex items-center justify-center text-white shadow-soft">
        <i class="fas fa-hospital"></i>
    </div>
    <div>
        <h3 class="text-sm font-bold text-slate-800 tracking-tight">Kibenes eBirth</h3>
        <p class="text-[10px] font-semibold text-slate-500 uppercase tracking-wider">Health Management</p>
    </div>
</div>

<nav class="space-y-8">
    <div>
        <h4 class="px-2 mb-3 text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em]">General</h4>
        <div class="space-y-1">
            <a href="<?= $baseUrl ?>/dashboard.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl transition-all <?= ($currentPage == 'dashboard.php') ? 'bg-slate-100 text-slate-900' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 group' ?>">
                <i class="fas fa-th-large w-5 text-center <?= ($currentPage == 'dashboard.php') ? 'text-slate-900' : 'text-slate-400 group-hover:text-slate-600' ?>"></i>
                <span>Dashboard</span>
            </a>
            <a href="<?= $baseUrl ?>/profile.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl transition-all <?= ($currentPage == 'profile.php') ? 'bg-slate-100 text-slate-900' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 group' ?>">
                <i class="fas fa-user-circle w-5 text-center <?= ($currentPage == 'profile.php') ? 'text-slate-900' : 'text-slate-400 group-hover:text-slate-600' ?>"></i>
                <span>My Profile</span>
            </a>
        </div>
    </div>
</nav>