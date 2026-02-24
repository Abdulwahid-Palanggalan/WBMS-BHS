<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$baseUrl = $GLOBALS['base_url'] ?? '';
?>

<!-- Mother Branding Section -->
<div class="mb-8 px-2 flex items-center gap-3">
    <div class="w-10 h-10 bg-rose-500 rounded-xl flex items-center justify-center text-white shadow-soft">
        <i class="fas fa-heart"></i>
    </div>
    <div>
        <h3 class="text-sm font-bold text-slate-800 tracking-tight">Mother Portal</h3>
        <p class="text-[10px] font-semibold text-rose-500 uppercase tracking-wider">My Journey</p>
    </div>
</div>

<nav class="space-y-8">
    <!-- Main Group -->
    <div>
        <h4 class="px-2 mb-3 text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em]">General</h4>
        <div class="space-y-1">
            <a href="<?= $baseUrl ?>/dashboard.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl transition-all <?= ($currentPage == 'dashboard.php') ? 'bg-rose-50 text-rose-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 group' ?>">
                <i class="fas fa-grid-2 w-5 text-center <?= ($currentPage == 'dashboard.php') ? 'text-rose-600' : 'text-slate-400 group-hover:text-slate-600' ?>"></i>
                <span>Dashboard</span>
            </a>
        </div>
    </div>

    <!-- Health Services Group -->
    <div>
        <h4 class="px-2 mb-3 text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em]">Services</h4>
        <div class="space-y-1">
            <a href="<?= $baseUrl ?>/forms/birth_registration.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl transition-all <?= ($currentPage == 'birth_registration.php') ? 'bg-rose-50 text-rose-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 group' ?>">
                <i class="fas fa-baby w-5 text-center <?= ($currentPage == 'birth_registration.php') ? 'text-rose-600' : 'text-slate-400 group-hover:text-slate-600' ?>"></i>
                <span>Birth Registration</span>
            </a>
            <a href="<?= $baseUrl ?>/forms/mother_self_registration.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl transition-all <?= ($currentPage == 'mother_self_registration.php') ? 'bg-rose-50 text-rose-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 group' ?>">
                <i class="fas fa-user-edit w-5 text-center <?= ($currentPage == 'mother_self_registration.php') ? 'text-rose-600' : 'text-slate-400 group-hover:text-slate-600' ?>"></i>
                <span>My Profile</span>
            </a>
            <a href="<?= $baseUrl ?>/library.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl transition-all <?= ($currentPage == 'library.php') ? 'bg-rose-50 text-rose-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 group' ?>">
                <i class="fas fa-book-medical w-5 text-center <?= ($currentPage == 'library.php') ? 'text-rose-600' : 'text-slate-400 group-hover:text-slate-600' ?>"></i>
                <span>Health Library</span>
            </a>
        </div>
    </div>

    <!-- Account Group -->
    <div>
        <h4 class="px-2 mb-3 text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em]">Account</h4>
        <div class="space-y-1">
            <a href="<?= $baseUrl ?>/profile.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl transition-all <?= ($currentPage == 'profile.php') ? 'bg-rose-50 text-rose-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 group' ?>">
                <i class="fas fa-user-circle w-5 text-center <?= ($currentPage == 'profile.php') ? 'text-rose-600' : 'text-slate-400 group-hover:text-slate-600' ?>"></i>
                <span>Settings</span>
            </a>
        </div>
    </div>
</nav>
