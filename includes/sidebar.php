<?php
// Sidebar Configuration and Base URL Setup
$currentPage = basename($_SERVER['PHP_SELF']);
$baseUrl = $GLOBALS['base_url'] ?? '';
$userRole = $_SESSION['role'] ?? 'guest';

// Available roles
$availableRoles = ['admin', 'midwife', 'bhw', 'bns', 'mother'];
$sidebarDir = __DIR__ . '/sidebars/';
$sidebarFile = $sidebarDir . 'sidebar_' . $userRole . '.php';

// Sidebar Container (Hidden on mobile by default)
?>
<aside class="sidebar fixed inset-y-0 left-0 z-[60] w-64 bg-white border-r border-slate-200 transform -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-in-out lg:sticky lg:top-16 lg:h-[calc(100vh-4rem)]">
    <div class="h-full overflow-y-auto py-6 px-4">
        <?php
        if (in_array($userRole, $availableRoles) && file_exists($sidebarFile)) {
            include_once $sidebarFile;
        } else {
            // Fallback Sidebar Content
            ?>
            <div class="mb-6 px-2">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Kibenes eBirth</span>
                <p class="text-xs text-health-600 font-semibold mt-1">Role: <?php echo htmlspecialchars($userRole); ?></p>
            </div>
            <nav class="space-y-1">
                <a href="<?= $baseUrl ?>/dashboard.php" class="flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-xl transition-all <?= ($currentPage == 'dashboard.php') ? 'bg-health-50 text-health-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' ?>">
                    <i class="fas fa-tachometer-alt w-5 text-center"></i>
                    <span>Dashboard</span>
                </a>
                <a href="<?= $baseUrl ?>/profile.php" class="flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-xl transition-all <?= ($currentPage == 'profile.php') ? 'bg-health-50 text-health-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' ?>">
                    <i class="fas fa-user w-5 text-center"></i>
                    <span>Profile</span>
                </a>
            </nav>
            <?php
        }
        ?>
    </div>
</aside>
