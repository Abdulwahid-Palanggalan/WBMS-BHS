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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Health Station System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #1a73e8;
            --primary-light: #e8f0fe;
            --secondary: #34a853;
            --accent: #fbbc05;
            --light: #f8f9fa;
            --dark: #202124;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }
        
        /* Dashboard Header */
        .dashboard-header {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border-left: 4px solid var(--primary);
        }
        
        /* Charts Section */
        .charts-section {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 1rem;
        }
        
        .chart-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border-left: 4px solid var(--primary);
        }
        
        /* Quick Access Cards */
        .quick-access-card {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
            height: 100%;
        }
        
        .quick-access-card:hover {
            border-color: var(--primary);
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            color: inherit;
            text-decoration: none;
        }
        
        .quick-access-card i {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            display: block;
        }
        
        /* Different colors for each quick access card */
        .quick-access-card:nth-child(1) i { color: #9c27b0; } /* Prenatal */
        .quick-access-card:nth-child(2) i { color: #00bcd4; } /* Postnatal */
        .quick-access-card:nth-child(3) i { color: var(--secondary); } /* Mothers */
        .quick-access-card:nth-child(4) i { color: var(--accent); } /* Babies */
        .quick-access-card:nth-child(5) i { color: var(--primary); } /* Users */
        .quick-access-card:nth-child(6) i { color: #ff6b6b; } /* Pregnant */
        .quick-access-card:nth-child(7) i { color: #6c757d; } /* Activities */
        .quick-access-card:nth-child(8) i { color: #17a2b8; } /* User Management */
        
        .quick-access-card .count {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }
        
        .quick-access-card .label {
            color: #6c757d;
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        /* Content Sections */
        .content-section {
            background: white;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        .section-header {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            padding: 1rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .section-header h5 {
            margin: 0;
            font-weight: 600;
        }
        
        .section-header i {
            margin-right: 0.5rem;
        }
        
        .section-body {
            padding: 1.5rem;
        }
        
        /* Activity Feed */
        .activity-feed {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .activity-item {
            display: flex;
            align-items: start;
            padding: 1rem 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-light);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            flex-shrink: 0;
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-text {
            margin: 0;
            color: var(--dark);
            font-size: 0.9rem;
        }
        
        .activity-time {
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        /* View All Button */
        .btn-view-all {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
        }
        
        .btn-view-all:hover {
            background: rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.5);
            color: white;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .chart-container {
                height: 250px;
            }
            
            .quick-access-card {
                padding: 1rem;
            }
            
            .quick-access-card i {
                font-size: 2rem;
            }
            
            .quick-access-card .count {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <?php include_once $rootPath . '/includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include_once $rootPath . '/includes/sidebar.php'; ?>
            
            <main class="ms-sm-auto px-md-4 main-content">
                <!-- Dashboard Header -->
                <div class="dashboard-header">
                    <div class="row align-items-center">
                        <div class="col">
                            <h1 class="h3 mb-1">Admin Dashboard</h1>
                            <p class="text-muted mb-0">Welcome back! Here's an overview of your system.</p>
                        </div>
                        <div class="col-auto">
                            <div class="btn-group">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="charts-section">
                    <h4 class="mb-4"><i class="fas fa-chart-bar me-2"></i>System Overview</h4>
                    
                    <div class="row">
                        <!-- Records Overview Chart -->
                        <div class="col-lg-8">
                            <h6 class="mb-3">Records Distribution</h6>
                            <div class="chart-container">
                                <canvas id="recordsChart"></canvas>
                            </div>
                        </div>
                        
                        <!-- User Distribution Chart -->
                        <div class="col-lg-4">
                            <h6 class="mb-3">User Distribution</h6>
                            <div class="chart-container">
                                <canvas id="userDistributionChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Access Grid -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header border-0 bg-transparent py-4">
                        <h5 class="mb-0 fw-bold"><i class="fas fa-th-large me-2 text-primary"></i>Quick Actions</h5>
                    </div>
                    <div class="card-body pt-0">
                        <div class="row g-3">
                            <div class="col-md-3 col-6">
                                <a href="<?php echo $baseUrl; ?>/prenatal_records.php" class="btn btn-light w-100 py-4 border shadow-sm text-center transition-all h-100 d-flex flex-column align-items-center justify-content-center">
                                    <i class="fas fa-heartbeat text-primary fa-2x mb-2"></i>
                                    <span class="fw-bold">Prenatal</span>
                                </a>
                            </div>
                            <div class="col-md-3 col-6">
                                <a href="<?php echo $baseUrl; ?>/postnatal_records.php" class="btn btn-light w-100 py-4 border shadow-sm text-center transition-all h-100 d-flex flex-column align-items-center justify-content-center">
                                    <i class="fas fa-baby-carriage text-success fa-2x mb-2"></i>
                                    <span class="fw-bold">Postnatal</span>
                                </a>
                            </div>
                            <div class="col-md-3 col-6">
                                <a href="<?php echo $baseUrl; ?>/mothers_list.php" class="btn btn-light w-100 py-4 border shadow-sm text-center transition-all h-100 d-flex flex-column align-items-center justify-content-center">
                                    <i class="fas fa-female text-info fa-2x mb-2"></i>
                                    <span class="fw-bold">Mothers</span>
                                </a>
                            </div>
                            <div class="col-md-3 col-6">
                                <a href="<?php echo $baseUrl; ?>/forms/birth_registration.php" class="btn btn-light w-100 py-4 border shadow-sm text-center transition-all h-100 d-flex flex-column align-items-center justify-content-center">
                                    <i class="fas fa-baby text-warning fa-2x mb-2"></i>
                                    <span class="fw-bold">Birth Reg</span>
                                </a>
                            </div>
                            <div class="col-md-3 col-6">
                                <a href="<?php echo $baseUrl; ?>/user_management.php" class="btn btn-light w-100 py-4 border shadow-sm text-center transition-all h-100 d-flex flex-column align-items-center justify-content-center">
                                    <i class="fas fa-users-cog text-primary fa-2x mb-2"></i>
                                    <span class="fw-bold">Users</span>
                                </a>
                            </div>
                            <div class="col-md-3 col-6">
                                <a href="<?php echo $baseUrl; ?>/pregnant_women.php" class="btn btn-light w-100 py-4 border shadow-sm text-center transition-all h-100 d-flex flex-column align-items-center justify-content-center">
                                    <i class="fas fa-calendar-check text-danger fa-2x mb-2"></i>
                                    <span class="fw-bold">Pregnancy</span>
                                </a>
                            </div>
                            <div class="col-md-3 col-6">
                                <a href="<?php echo $baseUrl; ?>/reports.php" class="btn btn-light w-100 py-4 border shadow-sm text-center transition-all h-100 d-flex flex-column align-items-center justify-content-center">
                                    <i class="fas fa-chart-line text-secondary fa-2x mb-2"></i>
                                    <span class="fw-bold">Reports</span>
                                </a>
                            </div>
                            <div class="col-md-3 col-6">
                                <a href="<?php echo $baseUrl; ?>/activity_logs.php" class="btn btn-light w-100 py-4 border shadow-sm text-center transition-all h-100 d-flex flex-column align-items-center justify-content-center">
                                    <i class="fas fa-history text-dark fa-2x mb-2"></i>
                                    <span class="fw-bold">Logs</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activities Section -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="content-section">
                            <div class="section-header">
                                <h5><i class="fas fa-history me-2"></i>Recent Activities</h5>
                                <a href="<?php echo $baseUrl; ?>/activity_logs.php" class="btn btn-view-all">
                                    <i class="fas fa-list me-1"></i>View All Activities
                                </a>
                            </div>
                            <div class="section-body">
                                <?php
                                // Get recent activities
                                $activities = $pdo->query("
                                    SELECT a.activity, a.timestamp, u.username, u.role
                                    FROM system_activities a 
                                    LEFT JOIN users u ON a.user_id = u.id 
                                    ORDER BY a.timestamp DESC 
                                    LIMIT 8
                                ")->fetchAll(PDO::FETCH_ASSOC);
                                ?>
                                
                                <div class="activity-feed">
                                    <?php if (!empty($activities)): ?>
                                        <?php foreach ($activities as $activity): ?>
                                        <div class="activity-item">
                                            <div class="activity-icon" style="background: <?php 
                                                echo $activity['role'] == 'admin' ? '#e8f5e8' : 
                                                     ($activity['role'] == 'midwife' ? '#e3f2fd' : '#fff3cd'); 
                                            ?>; color: <?php 
                                                echo $activity['role'] == 'admin' ? '#34a853' : 
                                                     ($activity['role'] == 'midwife' ? '#1a73e8' : '#fbbc05'); 
                                            ?>;">
                                                <i class="fas <?php 
                                                    echo $activity['role'] == 'admin' ? 'fa-user-shield' : 
                                                         ($activity['role'] == 'midwife' ? 'fa-user-nurse' : 'fa-user'); 
                                                ?>"></i>
                                            </div>
                                            <div class="activity-content">
                                                <p class="activity-text"><?php echo $activity['activity']; ?></p>
                                                <div class="activity-time">
                                                    <i class="fas fa-clock me-1"></i>
                                                    <?php echo !empty($activity['timestamp']) ? date('M j, Y g:i A', strtotime($activity['timestamp'])) : 'N/A'; ?>
                                                    <?php if (isset($activity['username'])): ?>
                                                        <span class="badge bg-secondary ms-2"><?php echo $activity['username']; ?> (<?php echo $activity['role']; ?>)</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="text-center text-muted py-4">
                                            <i class="fas fa-info-circle fa-2x mb-3 d-block"></i>
                                            No recent activities found
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

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