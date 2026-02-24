<?php
// Use absolute path to includes
$rootPath = dirname(__DIR__);

require_once $rootPath . '/includes/auth.php';
require_once $rootPath . '/includes/functions.php';

if (!isAuthorized(['admin'])) {
    header("Location: ../login.php");
    exit();
}

// Get stats for dashboard
global $pdo;
$usersCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$mothersCount = $pdo->query("SELECT COUNT(*) FROM mothers")->fetchColumn();
$birthsCount = $pdo->query("SELECT COUNT(*) FROM birth_records")->fetchColumn();
$pendingUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn();

// ✅ Get PREGNANT WOMEN count properly
$pregnantWomenCount = $pdo->query("
    SELECT COUNT(DISTINCT m.id) 
    FROM mothers m
    INNER JOIN prenatal_records pr ON m.id = pr.mother_id
    WHERE pr.visit_date >= DATE_SUB(NOW(), INTERVAL 9 MONTH)
    AND m.id NOT IN (
        SELECT DISTINCT mother_id FROM birth_records 
        WHERE birth_date >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
    )
")->fetchColumn() ?? 0;

$prenatalRecordsCount = $pdo->query("SELECT COUNT(*) FROM prenatal_records")->fetchColumn() ?? 0;
$postnatalRecordsCount = $pdo->query("SELECT COUNT(*) FROM postnatal_records")->fetchColumn() ?? 0;
$activitiesCount = $pdo->query("SELECT COUNT(*) FROM system_activities")->fetchColumn() ?? 0;

// ✅ Get data for charts
$monthlyBirths = $pdo->query("
    SELECT 
        YEAR(birth_date) as year,
        MONTH(birth_date) as month,
        COUNT(*) as count
    FROM birth_records 
    WHERE birth_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY YEAR(birth_date), MONTH(birth_date)
    ORDER BY year, month
")->fetchAll(PDO::FETCH_ASSOC);

$monthlyRegistrations = $pdo->query("
    SELECT 
        YEAR(created_at) as year,
        MONTH(created_at) as month,
        COUNT(*) as count
    FROM mothers 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY YEAR(created_at), MONTH(created_at)
    ORDER BY year, month
")->fetchAll(PDO::FETCH_ASSOC);

$userTypes = $pdo->query("
    SELECT role, COUNT(*) as count 
    FROM users 
    WHERE status = 'active'
    GROUP BY role
")->fetchAll(PDO::FETCH_ASSOC);

// ✅ Get records by type for bar chart
$recordsByType = $pdo->query("
    SELECT 'Mothers' as type, COUNT(*) as count FROM mothers
    UNION ALL
    SELECT 'Birth Records' as type, COUNT(*) as count FROM birth_records
    UNION ALL
    SELECT 'Prenatal' as type, COUNT(*) as count FROM prenatal_records
    UNION ALL
    SELECT 'Postnatal' as type, COUNT(*) as count FROM postnatal_records
    UNION ALL
    SELECT 'Users' as type, COUNT(*) as count FROM users
    UNION ALL
    SELECT 'Activities' as type, COUNT(*) as count FROM system_activities
")->fetchAll(PDO::FETCH_ASSOC);

// ✅ Get pregnant women data for table
$pregnantWomen = $pdo->query("
    SELECT 
        m.id,
        m.first_name,
        m.last_name,
        m.phone,
        MAX(pr.visit_date) as last_prenatal_visit,
        DATEDIFF(NOW(), MAX(pr.visit_date)) as days_since_visit
    FROM mothers m
    INNER JOIN prenatal_records pr ON m.id = pr.mother_id
    WHERE pr.visit_date >= DATE_SUB(NOW(), INTERVAL 9 MONTH)
    AND m.id NOT IN (
        SELECT DISTINCT mother_id FROM birth_records 
        WHERE birth_date >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
    )
    GROUP BY m.id, m.first_name, m.last_name, m.phone
    ORDER BY last_prenatal_visit DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Base URL is now centrally handled in database.php
$baseUrl = $GLOBALS['base_url'];
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Health Station System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php include_once $rootPath . '/includes/tailwind_config.php'; ?>
</head>
<body class="bg-slate-50 min-h-full">
    <?php include_once $rootPath . '/includes/header.php'; ?>
    
    <div class="flex flex-col lg:flex-row min-h-[calc(100vh-4rem)]">
        <?php include_once $rootPath . '/includes/sidebar.php'; ?>
        
        <main class="flex-1 p-4 lg:p-8 space-y-8">
            <!-- Dashboard Welcome Header -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 group flex items-center gap-2">
                        <i class="fas fa-hand-holding-heart text-health-600"></i>
                        Dashboard Overview
                    </h1>
                    <p class="text-slate-500 text-sm mt-1">Hello, Admin. Here is what's happening at Barangay Kibenes today.</p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="px-4 py-2 bg-white rounded-xl border border-slate-200 shadow-sm flex items-center gap-2">
                        <div class="w-2 h-2 rounded-full bg-emerald-500"></div>
                        <span class="text-xs font-semibold text-slate-600 uppercase tracking-wider"><?php echo date('F d, Y'); ?></span>
                    </div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="card-health p-6 flex items-center gap-4 group hover:border-health-200 transition-all cursor-default">
                    <div class="w-12 h-12 bg-health-50 text-health-600 rounded-xl flex items-center justify-center text-xl group-hover:bg-health-600 group-hover:text-white transition-all duration-300">
                        <i class="fas fa-users"></i>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest leading-tight">Total Users</p>
                        <h3 class="text-2xl font-bold text-slate-900 mt-1"><?php echo $usersCount; ?></h3>
                    </div>
                </div>
                
                <div class="card-health p-6 flex items-center gap-4 group hover:border-health-200 transition-all cursor-default">
                    <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center text-xl group-hover:bg-emerald-600 group-hover:text-white transition-all duration-300">
                        <i class="fas fa-female"></i>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest leading-tight">Registered Mothers</p>
                        <h3 class="text-2xl font-bold text-slate-900 mt-1"><?php echo $mothersCount; ?></h3>
                    </div>
                </div>

                <div class="card-health p-6 flex items-center gap-4 group hover:border-health-200 transition-all cursor-default">
                    <div class="w-12 h-12 bg-sky-50 text-sky-600 rounded-xl flex items-center justify-center text-xl group-hover:bg-sky-600 group-hover:text-white transition-all duration-300">
                        <i class="fas fa-baby"></i>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest leading-tight">Birth Records</p>
                        <h3 class="text-2xl font-bold text-slate-900 mt-1"><?php echo $birthsCount; ?></h3>
                    </div>
                </div>

                <div class="card-health p-6 flex items-center gap-4 group hover:border-health-200 transition-all cursor-default">
                    <div class="w-12 h-12 bg-rose-50 text-rose-600 rounded-xl flex items-center justify-center text-xl group-hover:bg-rose-600 group-hover:text-white transition-all duration-300">
                        <i class="fas fa-user-clock"></i>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest leading-tight">Pending Users</p>
                        <h3 class="text-2xl font-bold text-slate-900 mt-1"><?php echo $pendingUsers; ?></h3>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
                <!-- Records Distribution Chart -->
                <div class="xl:col-span-2 card-health p-6 pb-2">
                    <div class="flex items-center justify-between mb-8">
                        <div>
                            <h3 class="text-lg font-bold text-slate-800">Records Overview</h3>
                            <p class="text-xs text-slate-400 mt-0.5 font-medium uppercase tracking-wider">Health Data Distribution</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="w-3 h-3 bg-health-600 rounded-full"></span>
                            <span class="text-[10px] font-bold text-slate-500 uppercase">Live Stats</span>
                        </div>
                    </div>
                    <div class="h-[300px] w-full">
                        <canvas id="recordsChart"></canvas>
                    </div>
                </div>

                <!-- User Type Distribution Pie Chart -->
                <div class="card-health p-6">
                    <div class="mb-8">
                        <h3 class="text-lg font-bold text-slate-800">Personnel Roles</h3>
                        <p class="text-xs text-slate-400 mt-0.5 font-medium uppercase tracking-wider">User Distribution by Role</p>
                    </div>
                    <div class="h-[250px] w-full flex items-center justify-center">
                        <canvas id="userDistributionChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Quick Actions Grid -->
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                        <i class="fas fa-bolt text-amber-400 text-sm"></i>
                        Quick Access
                    </h3>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-4">
                    <?php
                    $actions = [
                        ['url' => 'prenatal_records.php', 'icon' => 'fa-heart-pulse', 'color' => 'bg-emerald-50 text-emerald-600', 'label' => 'Prenatal'],
                        ['url' => 'postnatal_records.php', 'icon' => 'fa-child-reaching', 'color' => 'bg-sky-50 text-sky-600', 'label' => 'Postnatal'],
                        ['url' => 'mothers_list.php', 'icon' => 'fa-female', 'color' => 'bg-indigo-50 text-indigo-600', 'label' => 'Mothers List'],
                        ['url' => 'forms/birth_registration.php', 'icon' => 'fa-baby-carriage', 'color' => 'bg-amber-50 text-amber-600', 'label' => 'Birth Reg'],
                        ['url' => 'user_management.php', 'icon' => 'fa-users-gear', 'color' => 'bg-purple-50 text-purple-600', 'label' => 'Users'],
                        ['url' => 'pregnant_women.php', 'icon' => 'fa-person-pregnant', 'color' => 'bg-rose-50 text-rose-600', 'label' => 'Pregnant'],
                        ['url' => 'reports.php', 'icon' => 'fa-chart-mixed', 'color' => 'bg-slate-100 text-slate-600', 'label' => 'Reports'],
                        ['url' => 'activity_logs.php', 'icon' => 'fa-history', 'color' => 'bg-blue-50 text-blue-600', 'label' => 'Logs'],
                    ];
                    foreach ($actions as $action): ?>
                        <a href="<?php echo $baseUrl; ?>/<?php echo $action['url']; ?>" class="group p-4 bg-white border border-slate-100 rounded-2xl flex flex-col items-center justify-center gap-3 hover:border-health-500 hover:shadow-xl hover:shadow-health-100/20 transition-all duration-300">
                            <div class="w-10 h-10 rounded-xl flex items-center justify-center text-lg <?php echo $action['color']; ?> group-hover:scale-110 transition-transform">
                                <i class="fas <?php echo $action['icon']; ?>"></i>
                            </div>
                            <span class="text-xs font-bold text-slate-600 group-hover:text-slate-900 whitespace-nowrap"><?php echo $action['label']; ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Recent Activities & Stats Table -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mt-8">
                <!-- Activity Feed -->
                <div class="card-health border-0">
                    <div class="px-6 py-4 bg-slate-900 flex items-center justify-between">
                        <h5 class="text-white font-bold flex items-center gap-2">
                            <i class="fas fa-timeline text-health-500"></i>
                            Recent Activities
                        </h5>
                        <a href="<?php echo $baseUrl; ?>/activity_logs.php" class="text-[10px] font-bold text-slate-300 uppercase tracking-widest hover:text-white transition-colors">
                            View All History
                        </a>
                    </div>
                    <div class="divide-y divide-slate-100 max-h-[500px] overflow-y-auto elegant-scrollbar">
                        <?php
                        $activities = $pdo->query("
                            SELECT a.activity, a.timestamp, u.username, u.role
                            FROM system_activities a 
                            LEFT JOIN users u ON a.user_id = u.id 
                            ORDER BY a.timestamp DESC 
                            LIMIT 10
                        ")->fetchAll(PDO::FETCH_ASSOC);

                        if (!empty($activities)):
                            foreach ($activities as $activity): ?>
                                <div class="p-5 flex items-start gap-4 hover:bg-slate-50 transition-colors">
                                    <div class="mt-1 w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 <?php 
                                        echo $activity['role'] == 'admin' ? 'bg-emerald-100 text-emerald-600' : 
                                            ($activity['role'] == 'midwife' ? 'bg-sky-100 text-sky-600' : 'bg-amber-100 text-amber-600'); 
                                    ?>">
                                        <i class="fas <?php 
                                            echo $activity['role'] == 'admin' ? 'fa-user-shield' : 
                                                ($activity['role'] == 'midwife' ? 'fa-user-nurse' : 'fa-user'); 
                                        ?> text-xs"></i>
                                    </div>
                                    <div class="flex-1">
                                        <p class="text-sm text-slate-800 font-medium"><?php echo $activity['activity']; ?></p>
                                        <div class="flex items-center gap-3 mt-1.5">
                                            <span class="text-[10px] font-bold text-slate-400 flex items-center gap-1">
                                                <i class="far fa-clock"></i>
                                                <?php echo !empty($activity['timestamp']) ? date('M j, g:i A', strtotime($activity['timestamp'])) : 'N/A'; ?>
                                            </span>
                                            <span class="text-[10px] px-2 py-0.5 rounded-full font-bold uppercase tracking-wider <?php 
                                                echo $activity['role'] == 'admin' ? 'bg-emerald-50 text-emerald-700' : 
                                                    ($activity['role'] == 'midwife' ? 'bg-sky-50 text-sky-700' : 'bg-amber-50 text-amber-700'); 
                                            ?>">
                                                By <?php echo $activity['username'] ?? 'System'; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach;
                        else: ?>
                            <div class="p-10 text-center space-y-3 text-slate-400">
                                <i class="fas fa-inbox text-3xl opacity-20"></i>
                                <p class="text-xs font-medium uppercase tracking-widest">No activities recorded</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Pregnant Women Summary -->
                <div class="card-health">
                    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                        <h5 class="text-slate-800 font-bold flex items-center gap-2">
                            <i class="fas fa-person-pregnant text-rose-500"></i>
                            Pregnant Women (Active)
                        </h5>
                        <span class="px-2 py-1 bg-rose-50 text-rose-600 text-[10px] font-bold rounded-md uppercase tracking-wider">
                            <?php echo $pregnantWomenCount; ?> Total
                        </span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-6 py-3 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Mother Name</th>
                                    <th class="px-6 py-3 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Last Visit</th>
                                    <th class="px-6 py-3 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php if (!empty($pregnantWomen)): ?>
                                    <?php foreach ($pregnantWomen as $woman): ?>
                                        <tr class="hover:bg-slate-50 transition-colors">
                                            <td class="px-6 py-4">
                                                <div class="text-sm font-semibold text-slate-800"><?php echo htmlspecialchars($woman['first_name'] . ' ' . $woman['last_name']); ?></div>
                                                <div class="text-[10px] text-slate-400"><?php echo htmlspecialchars($woman['phone'] ?? 'No contact'); ?></div>
                                            </td>
                                            <td class="px-6 py-4 text-xs text-slate-600">
                                                <?php echo date('M d, Y', strtotime($woman['last_prenatal_visit'])); ?>
                                            </td>
                                            <td class="px-6 py-4">
                                                <?php if ($woman['days_since_visit'] > 30): ?>
                                                    <span class="px-2 py-0.5 bg-rose-50 text-rose-600 text-[10px] font-bold rounded-full uppercase tracking-wider">Overdue</span>
                                                <?php else: ?>
                                                    <span class="px-2 py-0.5 bg-emerald-50 text-emerald-600 text-[10px] font-bold rounded-full uppercase tracking-wider">Regular</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="px-6 py-10 text-center text-slate-400 italic text-sm">
                                            No active pregnancy records found.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="p-4 border-t border-slate-100 text-center">
                        <a href="<?php echo $baseUrl; ?>/pregnant_women.php" class="text-xs font-bold text-health-600 hover:text-health-700 uppercase tracking-widest">View Full Registry</a>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- JavaScript & Chart Initialization -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Records Distribution Chart
            const recordsCtx = document.getElementById('recordsChart').getContext('2d');
            new Chart(recordsCtx, {
                type: 'bar',
                data: {
                    labels: [<?php echo implode(', ', array_map(function($r) { return "'" . $r['type'] . "'"; }, $recordsByType)); ?>],
                    datasets: [{
                        label: 'Internal Records',
                        data: [<?php echo implode(', ', array_column($recordsByType, 'count')); ?>],
                        backgroundColor: '#0D9488', // health-600
                        borderRadius: 8,
                        hoverBackgroundColor: '#0f766e',
                        barThickness: 32
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#0f172a',
                            padding: 12,
                            titleFont: { size: 14, weight: 'bold' },
                            bodyFont: { size: 13 },
                            cornerRadius: 8,
                            displayColors: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: '#f1f5f9' },
                            border: { display: false }
                        },
                        x: {
                            grid: { display: false },
                            border: { display: false },
                            ticks: { 
                                font: { weight: '600' },
                                color: '#64748b'
                            }
                        }
                    }
                }
            });

            // Personel Distribution Chart
            const userCtx = document.getElementById('userDistributionChart').getContext('2d');
            new Chart(userCtx, {
                type: 'doughnut',
                data: {
                    labels: [<?php echo implode(', ', array_map(function($u) { return "'" . ucfirst($u['role']) . "'"; }, $userTypes)); ?>],
                    datasets: [{
                        data: [<?php echo implode(', ', array_column($userTypes, 'count')); ?>],
                        backgroundColor: ['#0D9488', '#0284C7', '#7C3AED', '#F59E0B', '#EF4444'],
                        borderWidth: 0,
                        hoverOffset: 12
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '75%',
                    plugins: {
                        legend: { position: 'bottom', labels: { usePointStyle: true, font: { size: 11, weight: '600' }, color: '#64748b' } }
                    }
                }
            });
        });
    </script>

    <style>
        .shadow-soft { box-shadow: 0 10px 15px -3px rgba(13, 148, 136, 0.1), 0 4px 6px -2px rgba(13, 148, 136, 0.05); }
        .elegant-scrollbar::-webkit-scrollbar { width: 5px; }
        .elegant-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .elegant-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
        .elegant-scrollbar::-webkit-scrollbar-thumb:hover { background: #cbd5e1; }
    </style>
</body>
</html>

    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize Charts
        document.addEventListener('DOMContentLoaded', function() {
            // Records Distribution Chart (Bar Chart)
            const recordsCtx = document.getElementById('recordsChart').getContext('2d');
            const recordsChart = new Chart(recordsCtx, {
                type: 'bar',
                data: {
                    labels: [
                        <?php
                        $labels = [];
                        foreach ($recordsByType as $record) {
                            $labels[] = "'" . $record['type'] . "'";
                        }
                        echo implode(', ', $labels);
                        ?>
                    ],
                    datasets: [{
                        label: 'Total Records',
                        data: [<?php echo implode(', ', array_column($recordsByType, 'count')); ?>],
                        backgroundColor: [
                            '#9c27b0', // Mothers - Purple
                            '#00bcd4', // Birth Records - Cyan
                            '#4caf50', // Prenatal - Green
                            '#2196f3', // Postnatal - Blue
                            '#ff9800', // Users - Orange
                            '#607d8b'  // Activities - Gray
                        ],
                        borderColor: [
                            '#7b1fa2',
                            '#0097a7', 
                            '#388e3c',
                            '#1976d2',
                            '#f57c00',
                            '#455a64'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.parsed.y + ' records';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });

            // User Distribution Chart (Doughnut)
            const userCtx = document.getElementById('userDistributionChart').getContext('2d');
            const userChart = new Chart(userCtx, {
                type: 'doughnut',
                data: {
                    labels: [
                        <?php
                        $roleLabels = [];
                        foreach ($userTypes as $user) {
                            $roleLabels[] = "'" . ucfirst($user['role']) . "'";
                        }
                        echo implode(', ', $roleLabels);
                        ?>
                    ],
                    datasets: [{
                        data: [<?php echo implode(', ', array_column($userTypes, 'count')); ?>],
                        backgroundColor: [
                            '#1a73e8', // Admin - Blue
                            '#34a853', // Midwife - Green
                            '#fbbc05', // User - Yellow
                            '#9c27b0', // Other roles
                            '#00bcd4'
                        ],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
            
            // Auto-refresh every 5 minutes
            setTimeout(function() {
                window.location.reload();
            }, 300000);
        });
    </script>
</body>
</html>