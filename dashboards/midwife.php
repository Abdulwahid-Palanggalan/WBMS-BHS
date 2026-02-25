<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isAuthorized(['midwife'])) {
    header("Location: ../login.php");
    exit();
}

redirectIfNotLoggedIn();

global $pdo;

// ✅ Data for Chart (Birth Trends - Last 6 Months)
$birthTrends = $pdo->query("
    SELECT 
        DATE_FORMAT(birth_date, '%b %Y') as month_year,
        COUNT(*) as count
    FROM birth_records 
    WHERE birth_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY YEAR(birth_date), MONTH(birth_date)
    ORDER BY YEAR(birth_date), MONTH(birth_date)
")->fetchAll(PDO::FETCH_ASSOC);

$chartLabels = json_encode(array_column($birthTrends, 'month_year') ?: []);
$chartData = json_encode(array_column($birthTrends, 'count') ?: []);

// ✅ Emergency Alerts (Active)
$activeAlerts = $pdo->query("
    SELECT ea.*, u.first_name, u.last_name, u.phone 
    FROM emergency_alerts ea
    JOIN mothers m ON ea.mother_id = m.id
    JOIN users u ON m.user_id = u.id
    WHERE ea.status IN ('active', 'responding')
    ORDER BY ea.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// ✅ Dashboard stats
$activePregnancies = $pdo->query("
    SELECT COUNT(DISTINCT pr.mother_id) 
    FROM prenatal_records pr
    JOIN pregnancy_details pd ON pr.mother_id = pd.mother_id
    WHERE pr.visit_date > DATE_SUB(NOW(), INTERVAL 3 MONTH)
      AND pd.edc > CURDATE()
")->fetchColumn() ?? 0;

$birthsThisMonth = $pdo->query("
    SELECT COUNT(*) 
    FROM birth_records 
    WHERE MONTH(birth_date) = MONTH(NOW()) 
      AND YEAR(birth_date) = YEAR(NOW())
")->fetchColumn() ?? 0;

$dueThisWeek = $pdo->query("
    SELECT COUNT(*) 
    FROM pregnancy_details 
    WHERE edc BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
")->fetchColumn() ?? 0;

$postnatalDue = $pdo->query("
    SELECT COUNT(DISTINCT pr.mother_id) 
    FROM postnatal_records pr 
    JOIN birth_records br ON pr.baby_id = br.id 
    WHERE br.birth_date BETWEEN DATE_SUB(NOW(), INTERVAL 6 WEEK) AND NOW()
      AND (pr.next_visit_date <= DATE_ADD(NOW(), INTERVAL 7 DAY) OR pr.next_visit_date IS NULL)
")->fetchColumn() ?? 0;

// Get mothers due for checkup
$mothersDueCheckup = $pdo->query("
    SELECT m.*, u.first_name, u.last_name, u.phone,
           pd.edc as due_date,
           DATEDIFF(NOW(), MAX(pr.visit_date)) as days_since_visit
    FROM mothers m 
    JOIN users u ON m.user_id = u.id 
    LEFT JOIN prenatal_records pr ON m.id = pr.mother_id 
    LEFT JOIN pregnancy_details pd ON m.id = pd.mother_id
    GROUP BY m.id 
    HAVING days_since_visit > 30 OR days_since_visit IS NULL
    ORDER BY days_since_visit DESC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Get upcoming appointments
$upcomingAppointments = $pdo->query("
    SELECT pr.*, m.first_name, m.last_name, m.phone,
           pd.edc, pr.visit_date
    FROM prenatal_records pr 
    JOIN mothers m ON pr.mother_id = m.id 
    LEFT JOIN pregnancy_details pd ON m.id = pd.mother_id
    WHERE pr.visit_date >= CURDATE() 
    ORDER BY pr.visit_date ASC
    LIMIT 4
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Midwife Dashboard - Health Station System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php include_once __DIR__ . '/../includes/tailwind_config.php'; ?>
    <style type="text/tailwindcss">
        @layer components {
            .alert-pulse-red {
                @apply relative overflow-hidden;
            }
            .alert-pulse-red::after {
                content: '';
                @apply absolute inset-0 rounded-2xl ring-4 ring-rose-500/30 animate-pulse;
            }
            .stat-card-clinical {
                @apply bg-white p-6 rounded-3xl border border-slate-100 shadow-sm hover:shadow-md transition-all duration-300;
            }
            .table-modern th {
                @apply px-6 py-4 text-left text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em] border-b border-slate-50;
            }
            .table-modern td {
                @apply px-6 py-4 text-sm text-slate-600 border-b border-slate-50;
            }
        }
    </style>
</head>
<body class="bg-slate-50 min-h-full">
    <?php include_once __DIR__ . '/../includes/header.php'; ?>
    
    <div class="flex flex-col lg:flex-row min-h-[calc(100vh-4rem)]">
        <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
        
        <main class="flex-1 p-4 lg:p-8 space-y-8">
            <!-- SOS ALERT CENTER -->
            <?php if (!empty($activeAlerts)): ?>
            <section class="space-y-4">
                <div class="flex items-center gap-3">
                    <div class="w-2 h-2 rounded-full bg-rose-500 animate-ping"></div>
                    <h2 class="text-sm font-black text-rose-600 uppercase tracking-[0.3em]">Critical Emergency Center</h2>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                    <?php foreach ($activeAlerts as $alert): ?>
                    <div class="alert-pulse-red bg-white p-6 rounded-[2rem] border-2 border-rose-100 shadow-xl shadow-rose-100/50 flex flex-col justify-between group">
                        <div class="flex justify-between items-start border-b border-rose-50 pb-4 mb-4">
                            <div>
                                <span class="bg-rose-600 text-white text-[10px] font-bold px-3 py-1 rounded-full uppercase tracking-widest shadow-lg shadow-rose-200">Active SOS</span>
                                <h3 class="text-lg font-bold text-slate-900 mt-3"><?= htmlspecialchars($alert['first_name'] . ' ' . $alert['last_name']) ?></h3>
                            </div>
                            <div class="bg-rose-50 p-3 rounded-2xl text-rose-600 group-hover:bg-rose-600 group-hover:text-white transition-colors duration-500">
                                <i class="fas fa-truck-medical text-xl"></i>
                            </div>
                        </div>
                        
                        <div class="space-y-3 mb-6">
                            <div class="flex items-center gap-3 text-sm text-slate-500 bg-slate-50 p-3 rounded-xl border border-slate-100">
                                <i class="fas fa-map-marker-alt text-rose-500"></i>
                                <span class="truncate italic"><?= $alert['location_data'] ?></span>
                            </div>
                            <div class="flex items-center justify-between text-xs font-medium">
                                <span class="text-slate-400"><i class="far fa-clock me-1"></i> Triggered: <?= date('H:i', strtotime($alert['created_at'])) ?></span>
                                <a href="tel:<?= $alert['phone'] ?>" class="text-health-600 hover:underline font-bold tracking-tight"><i class="fas fa-phone-alt me-1"></i> Call Mother Now</a>
                            </div>
                        </div>
                        
                        <button onclick="resolveAlert(<?= $alert['id'] ?>)" class="w-full bg-emerald-500 hover:bg-emerald-600 text-white font-bold py-3 rounded-2xl transition-all shadow-lg shadow-emerald-100 flex items-center justify-center gap-2">
                            <i class="fas fa-check-circle"></i>
                            <span>Mark as Resolved</span>
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <!-- Global Analytics & Stats -->
            <div class="space-y-6">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-slate-900 leading-tight">Clinical Overview</h1>
                        <p class="text-slate-500 text-sm">Real-time health station delivery and maternal statistics.</p>
                    </div>
                    <div class="bg-white px-4 py-2 rounded-2xl border border-slate-100 shadow-sm flex items-center gap-3">
                        <i class="far fa-calendar-alt text-health-600"></i>
                        <span class="text-xs font-bold text-slate-600 uppercase tracking-widest"><?= date('l, F j, Y'); ?></span>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-6">
                    <div class="stat-card-clinical border-l-4 border-health-600">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em] mb-1">Active Pregnancies</p>
                                <h3 class="text-3xl font-black text-slate-900"><?= $activePregnancies; ?></h3>
                            </div>
                            <div class="w-10 h-10 bg-health-50 text-health-600 rounded-xl flex items-center justify-center shadow-soft">
                                <i class="fas fa-person-breastfeeding"></i>
                            </div>
                        </div>
                        <div class="mt-4 flex items-center gap-2 text-[10px] font-bold text-health-600 bg-health-50 w-fit px-2 py-1 rounded-lg">
                            <i class="fas fa-chart-line"></i> Monitoring Active
                        </div>
                    </div>

                    <div class="stat-card-clinical border-l-4 border-sky-600">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em] mb-1">Births (Month)</p>
                                <h3 class="text-3xl font-black text-slate-900"><?= $birthsThisMonth; ?></h3>
                            </div>
                            <div class="w-10 h-10 bg-sky-50 text-sky-600 rounded-xl flex items-center justify-center shadow-soft">
                                <i class="fas fa-baby"></i>
                            </div>
                        </div>
                        <div class="mt-4 flex items-center gap-2 text-[10px] font-bold text-sky-600 bg-sky-50 w-fit px-2 py-1 rounded-lg">
                            <i class="fas fa-calendar-check"></i> Latest Records
                        </div>
                    </div>

                    <div class="stat-card-clinical border-l-4 border-amber-600">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em] mb-1">Due This Week</p>
                                <h3 class="text-3xl font-black text-slate-900"><?= $dueThisWeek; ?></h3>
                            </div>
                            <div class="w-10 h-10 bg-amber-50 text-amber-600 rounded-xl flex items-center justify-center shadow-soft">
                                <i class="fas fa-hospital-user"></i>
                            </div>
                        </div>
                        <div class="mt-4 flex items-center gap-2 text-[10px] font-bold text-amber-600 bg-amber-50 w-fit px-2 py-1 rounded-lg uppercase tracking-tight">
                            Critical Vigilance
                        </div>
                    </div>

                    <div class="stat-card-clinical border-l-4 border-rose-600">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em] mb-1">Postnatal Checks</p>
                                <h3 class="text-3xl font-black text-slate-900"><?= $postnatalDue; ?></h3>
                            </div>
                            <div class="w-10 h-10 bg-rose-50 text-rose-600 rounded-xl flex items-center justify-center shadow-soft">
                                <i class="fas fa-baby-carriage"></i>
                            </div>
                        </div>
                        <div class="mt-4 flex items-center gap-2 text-[10px] font-bold text-rose-600 bg-rose-50 w-fit px-2 py-1 rounded-lg uppercase tracking-tight">
                            Follow-ups Pending
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-8">
                    <!-- Delivery Trends Chart -->
                    <div class="bg-white p-8 rounded-[2.5rem] border border-slate-100 shadow-sm relative overflow-hidden group">
                        <div class="absolute top-0 right-0 w-64 h-64 bg-health-50/50 rounded-full blur-3xl -translate-y-1/2 translate-x-1/2 -z-0"></div>
                        
                        <div class="relative z-10 flex flex-col h-full">
                            <div class="flex justify-between items-start mb-8">
                                <div>
                                    <h3 class="text-lg font-bold text-slate-800">Birth Delivery Trends</h3>
                                    <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mt-1">Last 6 Months Data</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <div class="flex items-center gap-1.5 px-3 py-1.5 bg-slate-50 rounded-lg text-slate-500 text-[10px] font-bold uppercase border border-slate-100">
                                        Active Deliveries
                                    </div>
                                </div>
                            </div>
                            <div class="flex-1 min-h-[300px]">
                                <canvas id="birthChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Access Grid -->
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                            <i class="fas fa-bolt text-amber-400 text-sm"></i>
                            Quick Access
                        </h3>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
                        <?php
                        $actions = [
                            ['url' => 'prenatal_records.php', 'icon' => 'fa-heart-pulse', 'color' => 'bg-emerald-50 text-emerald-600', 'label' => 'Prenatal'],
                            ['url' => 'postnatal_records.php', 'icon' => 'fa-child-reaching', 'color' => 'bg-sky-50 text-sky-600', 'label' => 'Postnatal'],
                            ['url' => 'mothers_list.php', 'icon' => 'fa-female', 'color' => 'bg-indigo-50 text-indigo-600', 'label' => 'Mothers List'],
                            ['url' => 'forms/birth_registration.php', 'icon' => 'fa-baby-carriage', 'color' => 'bg-amber-50 text-amber-600', 'label' => 'Birth Reg'],
                            ['url' => 'pregnant_women.php', 'icon' => 'fa-person-pregnant', 'color' => 'bg-rose-50 text-rose-600', 'label' => 'Pregnant'],
                            ['url' => 'reports.php', 'icon' => 'fa-chart-mixed', 'color' => 'bg-slate-100 text-slate-600', 'label' => 'Reports'],
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

                <!-- Patient Management Lists -->
                <div class="grid grid-cols-1 xl:grid-cols-12 gap-8">
                    <!-- Upcoming Care Plan -->
                    <div class="xl:col-span-12 space-y-4">
                        <div class="flex items-center justify-between px-2">
                            <h3 class="text-sm font-black text-slate-400 uppercase tracking-[0.3em]">Patient Care Schedule</h3>
                            <span class="bg-health-50 text-health-600 text-[10px] font-bold px-3 py-1 rounded-full border border-health-100">Next 7 Days</span>
                        </div>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                            <?php if (!empty($upcomingAppointments)): ?>
                                <?php foreach ($upcomingAppointments as $app): ?>
                                    <div class="bg-white p-5 rounded-3xl border border-slate-100 shadow-sm hover:-translate-y-1 hover:shadow-lg transition-all duration-300 group">
                                        <div class="flex items-center gap-4 mb-3">
                                            <div class="w-10 h-10 rounded-xl bg-health-50 text-health-600 flex items-center justify-center shadow-soft">
                                                <i class="fas fa-calendar-day"></i>
                                            </div>
                                            <div class="flex-1">
                                                <h4 class="text-sm font-bold text-slate-900 leading-none group-hover:text-health-700 transition-colors"><?= htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?></h4>
                                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1"><?= date('M d, Y', strtotime($app['visit_date'])); ?></p>
                                            </div>
                                        </div>
                                        <div class="flex items-center justify-between border-t border-slate-50 pt-3 mt-3">
                                            <span class="text-[9px] font-black text-slate-300 uppercase tracking-tighter italic">Regular Checkup</span>
                                            <a href="tel:<?= $app['phone'] ?>" class="text-[10px] text-health-600 font-bold hover:underline"><i class="fas fa-phone-alt me-1"></i> Remind</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="col-span-full py-10 bg-slate-50 rounded-[2rem] border-2 border-dashed border-slate-200 text-center">
                                    <i class="fas fa-calendar-xmark text-slate-200 text-4xl mb-3"></i>
                                    <p class="text-slate-400 font-bold text-xs uppercase tracking-widest">No patient visits scheduled</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Overdue Patient Table -->
                    <div class="xl:col-span-12 bg-white rounded-[2.5rem] border border-slate-100 shadow-sm overflow-hidden">
                        <div class="px-8 py-6 flex flex-col md:flex-row md:items-center justify-between bg-white border-b border-slate-50 gap-4">
                            <div>
                                <h3 class="text-lg font-bold text-slate-800">Critical Attention Needed</h3>
                                <p class="text-xs font-bold text-rose-500 uppercase tracking-widest mt-1">Mothers Overdue for Clinical Checkup (>30 Days)</p>
                            </div>
                            <a href="<?= $GLOBALS['base_url'] ?>/mothers_list.php" class="bg-slate-50 hover:bg-slate-100 text-slate-600 px-5 py-2.5 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all">View Full Registry</a>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="w-full table-modern">
                                <thead>
                                    <tr>
                                        <th>Patient Name</th>
                                        <th>Inactivity Risk</th>
                                        <th>EDC (Due Date)</th>
                                        <th>Clinical Status</th>
                                        <th class="text-right">Care Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($mothersDueCheckup as $mother): ?>
                                    <tr class="hover:bg-slate-50/50 transition-colors group">
                                        <td class="py-5">
                                            <div class="flex items-center gap-4">
                                                <div class="w-10 h-10 bg-health-600 text-white rounded-2xl flex items-center justify-center text-sm font-bold shadow-lg shadow-health-100">
                                                    <?= strtoupper(substr($mother['first_name'], 0, 1)); ?>
                                                </div>
                                                <div class="flex flex-col">
                                                    <span class="font-bold text-slate-800 tracking-tight group-hover:text-health-700 transition-colors"><?= htmlspecialchars($mother['first_name'] . ' ' . $mother['last_name']); ?></span>
                                                    <span class="text-[10px] font-medium text-slate-400 italic"><?= $mother['phone']; ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="flex flex-col">
                                                <span class="text-rose-600 font-black text-xs uppercase tracking-tighter">
                                                    <?= $mother['days_since_visit'] ? $mother['days_since_visit'] . ' days' : 'Record Missing'; ?>
                                                </span>
                                                <div class="w-24 h-1 bg-slate-100 rounded-full mt-1.5 overflow-hidden">
                                                    <div class="bg-rose-500 h-full w-[80%] rounded-full shadow-[0_0_8px_rgba(244,63,94,0.5)]"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="text-xs font-bold text-slate-600 tracking-wider">
                                                <?= $mother['due_date'] ? date('M d, Y', strtotime($mother['due_date'])) : '--'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="bg-rose-50 text-rose-600 text-[9px] font-black px-3 py-1.5 rounded-full uppercase tracking-widest border border-rose-100 italic">High Priority</span>
                                        </td>
                                        <td class="text-right">
                                            <a href="<?= $GLOBALS['base_url'] ?>/forms/prenatal_form.php?mother_id=<?= $mother['id']; ?>" class="bg-health-600 hover:bg-health-700 text-white text-[10px] font-black px-4 py-2 rounded-xl uppercase tracking-widest transition-all shadow-lg shadow-health-100 hover:-translate-x-1">Assign Visit</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Scripts Footer -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('birthChart').getContext('2d');
            
            // Modern Gradient
            const gradient = ctx.createLinearGradient(0, 0, 0, 300);
            gradient.addColorStop(0, 'rgba(13, 148, 136, 0.2)');
            gradient.addColorStop(1, 'rgba(13, 148, 136, 0)');

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?= $chartLabels; ?>,
                    datasets: [{
                        label: 'Successful Deliveries',
                        data: <?= $chartData; ?>,
                        borderColor: '#0d9488',
                        backgroundColor: gradient,
                        borderWidth: 4,
                        tension: 0.45,
                        fill: true,
                        pointBackgroundColor: '#fff',
                        pointBorderColor: '#0d9488',
                        pointBorderWidth: 3,
                        pointRadius: 6,
                        pointHoverRadius: 8,
                        pointHoverBorderWidth: 4,
                        pointHoverBackgroundColor: '#0d9488'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#1e293b',
                            titleFont: { size: 12, weight: 'bold' },
                            padding: 12,
                            cornerRadius: 12,
                            displayColors: false
                        }
                    },
                    interaction: {
                        intersect: false,
                        mode: 'index',
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { borderDash: [5, 5], color: '#f1f5f9' },
                            ticks: { 
                                stepSize: 1, 
                                color: '#94a3b8',
                                font: { weight: 'bold', size: 10 }
                            },
                            border: { display: false }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { 
                                color: '#94a3b8',
                                font: { weight: 'bold', size: 10 }
                            },
                            border: { display: false }
                        }
                    }
                }
            });

            // Auto-refresh for SOS Monitoring (Poll every 30s)
            setInterval(() => {
                location.reload();
            }, 30000);
        });

        function resolveAlert(id) {
            Swal.fire({
                title: 'Confirm Resolution',
                text: "Have you reached and assisted this patient? This will clear the emergency status.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#f43f5e',
                confirmButtonText: 'Yes, Resolved Out',
                padding: '2rem',
                customClass: {
                    popup: 'rounded-[2rem]',
                    confirmButton: 'rounded-xl font-bold py-3 px-6 px-10',
                    cancelButton: 'rounded-xl font-bold py-3 px-6'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('../ajax/resolve_sos.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `alert_id=${id}`
                    }).then(r => r.json()).then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'SOS Resolved',
                                text: 'The emergency alert has been updated successfully.',
                                customClass: { popup: 'rounded-[2rem]' }
                            }).then(() => location.reload());
                        }
                    });
                }
            });
        }
    </script>
</body>
</html>
