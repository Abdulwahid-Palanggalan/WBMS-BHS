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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Midwife Dashboard - Health Station System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .alert-pulse {
            animation: pulse-red 2s infinite;
        }
        @keyframes pulse-red {
            0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); }
            100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/../includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
            
            <main class="main-content">
                <div id="dashboard-view">
                    
                    <!-- SOS ALERT CENTER (Bento Overlay) -->
                    <?php if (!empty($activeAlerts)): ?>
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card border-0 shadow-sm alert-pulse" style="border-left: 5px solid var(--danger) !important;">
                                <div class="card-header bg-danger text-white py-3">
                                    <h5 class="mb-0 fw-bold"><i class="fas fa-truck-medical me-2"></i>ACTIVE EMERGENCY ALERTS (<?= count($activeAlerts) ?>)</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <?php foreach ($activeAlerts as $alert): ?>
                                        <div class="col-md-6 col-lg-4">
                                            <div class="p-3 bg-light rounded-xl border">
                                                <div class="d-flex justify-content-between mb-2">
                                                    <span class="badge bg-danger">Active SOS</span>
                                                    <small class="text-muted"><?= date('H:i', strtotime($alert['created_at'])) ?></small>
                                                </div>
                                                <h6 class="fw-bold mb-1"><?= htmlspecialchars($alert['first_name'] . ' ' . $alert['last_name']) ?></h6>
                                                <p class="small text-muted mb-2"><i class="fas fa-map-marker-alt me-1"></i> <?= $alert['location_data'] ?></p>
                                                <div class="d-flex gap-2">
                                                    <a href="tel:<?= $alert['phone'] ?>" class="btn btn-sm btn-outline-primary flex-grow-1">Call Mother</a>
                                                    <button class="btn btn-sm btn-success flex-grow-1" onclick="resolveAlert(<?= $alert['id'] ?>)">Resolve</button>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Page Title -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2 class="fw-bold mb-1">Clinical Overview</h2>
                            <p class="text-muted small mb-0">Monitor maternal health and delivery metrics</p>
                        </div>
                        <div class="text-end d-none d-md-block">
                            <span class="badge bg-white text-dark shadow-sm p-3 border rounded-pill">
                                <i class="far fa-calendar-alt me-2 text-primary"></i>
                                <?= date('l, F j, Y'); ?>
                            </span>
                        </div>
                    </div>

                    <!-- Bento Stat Grid -->
                    <div class="row g-4 mb-4">
                        <div class="col-xl-3 col-md-6">
                            <div class="card border-0 shadow-sm stats-card" style="border-top: 4px solid var(--primary) !important;">
                                <div class="stats-icon" style="background: var(--primary-light); color: var(--primary);">
                                    <i class="fas fa-female"></i>
                                </div>
                                <span class="stats-number"><?= $activePregnancies; ?></span>
                                <span class="stats-label">Active Pregnancies</span>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6">
                            <div class="card border-0 shadow-sm stats-card" style="border-top: 4px solid var(--accent) !important;">
                                <div class="stats-icon" style="background: #ecfdf5; color: var(--accent);">
                                    <i class="fas fa-baby"></i>
                                </div>
                                <span class="stats-number"><?= $birthsThisMonth; ?></span>
                                <span class="stats-label">Births This Month</span>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6">
                            <div class="card border-0 shadow-sm stats-card" style="border-top: 4px solid var(--warning) !important;">
                                <div class="stats-icon" style="background: #fffbeb; color: var(--warning);">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                                <span class="stats-number"><?= $dueThisWeek; ?></span>
                                <span class="stats-label">Due This Week</span>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6">
                            <div class="card border-0 shadow-sm stats-card" style="border-top: 4px solid var(--secondary) !important;">
                                <div class="stats-icon" style="background: #f0f9ff; color: var(--secondary);">
                                    <i class="fas fa-baby-carriage"></i>
                                </div>
                                <span class="stats-number"><?= $postnatalDue; ?></span>
                                <span class="stats-label">Postnatal Follow-ups</span>
                            </div>
                        </div>
                    </div>

                    <div class="row g-4 mb-4">
                        <!-- Chart Section (Bento Large) -->
                        <div class="col-lg-8">
                            <div class="card h-100 border-0 shadow-sm">
                                <div class="card-header border-0 bg-transparent py-4">
                                    <h5 class="mb-0 fw-bold">Birth Delivery Trends</h5>
                                    <p class="text-muted small mb-0">Monthly analysis of successful deliveries</p>
                                </div>
                                <div class="card-body">
                                    <div style="height: 300px;">
                                        <canvas id="birthChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Upcoming Appointments (Bento Small) -->
                        <div class="col-lg-4">
                            <div class="card h-100 border-0 shadow-sm">
                                <div class="card-header border-0 bg-transparent py-4">
                                    <h5 class="mb-0 fw-bold">Next Appointments</h5>
                                </div>
                                <div class="card-body pt-0">
                                    <?php if (!empty($upcomingAppointments)): ?>
                                        <?php foreach ($upcomingAppointments as $app): ?>
                                            <div class="p-3 bg-light rounded-3 mb-3 border-start border-4 border-primary">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <span class="fw-bold"><?= htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?></span>
                                                    <small class="text-primary fw-medium"><?= date('M d', strtotime($app['visit_date'])); ?></small>
                                                </div>
                                                <small class="text-muted"><i class="fas fa-phone-alt me-1"></i> <?= $app['phone']; ?></small>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="text-center py-5">
                                            <i class="fas fa-calendar-day fa-3x text-light mb-3"></i>
                                            <p class="text-muted">No appointments today</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-4">
                        <!-- Mothers Due Alert -->
                        <div class="col-lg-12">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header border-0 bg-transparent py-4 d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="mb-0 fw-bold">Attention Needed</h5>
                                        <p class="text-muted small mb-0">Mothers due for checkup (>30 days since last visit)</p>
                                    </div>
                                    <a href="<?= $GLOBALS['base_url'] ?>/mothers_list.php" class="btn btn-sm btn-outline-primary">View All Mothers</a>
                                </div>
                                <div class="card-body pt-0">
                                    <div class="table-responsive">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>Mother</th>
                                                    <th>Last Visit</th>
                                                    <th>EDC (Due Date)</th>
                                                    <th>Status</th>
                                                    <th class="text-end">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($mothersDueCheckup as $mother): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="bg-primary-light text-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px; font-weight: 600;">
                                                                <?= strtoupper(substr($mother['first_name'], 0, 1)); ?>
                                                            </div>
                                                            <div>
                                                                <span class="fw-bold d-block"><?= htmlspecialchars($mother['first_name'] . ' ' . $mother['last_name']); ?></span>
                                                                <small class="text-muted"><?= $mother['phone']; ?></small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="text-danger">
                                                            <?= $mother['days_since_visit'] ? $mother['days_since_visit'] . ' days ago' : 'Never visited'; ?>
                                                        </span>
                                                    </td>
                                                    <td><?= $mother['due_date'] ? date('M d, Y', strtotime($mother['due_date'])) : '--'; ?></td>
                                                    <td><span class="badge bg-warning text-dark px-3 rounded-pill">Overdue</span></td>
                                                    <td class="text-end">
                                                        <a href="<?= $GLOBALS['base_url'] ?>/forms/prenatal_form.php?mother_id=<?= $mother['id']; ?>" class="btn btn-sm btn-primary">Add Visit</a>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Birth Trends Chart Implementation
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('birthChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?= $chartLabels; ?>,
                    datasets: [{
                        label: 'Deliveries',
                        data: <?= $chartData; ?>,
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37, 99, 235, 0.1)',
                        borderWidth: 3,
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: '#fff',
                        pointBorderColor: '#2563eb',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { borderDash: [5, 5], color: '#e2e8f0' },
                            ticks: { stepSize: 1, color: '#64748b' }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { color: '#64748b' }
                        }
                    }
                }
            });

            // Poll for new SOS alerts every 30 seconds
            setInterval(() => {
                location.reload();
            }, 30000);
        });

        function resolveAlert(id) {
            if (confirm('Mark this emergency as resolved?')) {
                fetch('../ajax/resolve_sos.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `alert_id=${id}`
                }).then(() => location.reload());
            }
        }
    </script>
</body>
</html>