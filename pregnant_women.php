<?php
// Use correct path to includes - FIXED PATH
$rootPath = __DIR__; // Current directory where pregnant_women.php is located

require_once $rootPath . '/includes/auth.php';
require_once $rootPath . '/includes/functions.php';

if (!isAuthorized(['admin'])) {
    header("Location: " . $rootPath . "/login.php");
    exit();
}

// Get pregnant women data
global $pdo;

// ✅ Get pregnant women data for table - AUTOMATICALLY REMOVED AFTER BIRTH
$pregnantWomen = $pdo->query("
    SELECT 
        m.id,
        m.first_name,
        m.last_name,
        m.phone,
        MAX(pr.visit_date) as last_prenatal_visit,
        DATEDIFF(NOW(), MAX(pr.visit_date)) as days_since_visit,
        pd.edc,
        pd.lmp,
        pd.gravida,
        pd.para,
        pd.abortions,
        pd.living_children,
        DATEDIFF(CURDATE(), pd.lmp) as gestational_days,
        ROUND(DATEDIFF(CURDATE(), pd.lmp) / 7, 1) as gestational_weeks,
        DATEDIFF(pd.edc, CURDATE()) as days_until_due,
        (SELECT COUNT(*) FROM prenatal_records WHERE mother_id = m.id) as prenatal_visits,
        (SELECT COUNT(*) FROM birth_records WHERE mother_id = m.id AND birth_date >= DATE_SUB(NOW(), INTERVAL 3 MONTH)) as recent_births
    FROM mothers m
    INNER JOIN prenatal_records pr ON m.id = pr.mother_id
    LEFT JOIN pregnancy_details pd ON m.id = pd.mother_id
    WHERE pr.visit_date >= DATE_SUB(NOW(), INTERVAL 9 MONTH)
    AND m.id NOT IN (
        SELECT DISTINCT mother_id FROM birth_records 
        WHERE birth_date >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
    )
    AND (pd.edc IS NULL OR pd.edc > CURDATE())
    GROUP BY m.id, m.first_name, m.last_name, m.phone, pd.edc, pd.lmp, pd.gravida, pd.para, pd.abortions, pd.living_children
    ORDER BY pd.edc ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Set the base URL
if (!isset($GLOBALS['base_url'])) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $path = dirname($_SERVER['SCRIPT_NAME']);
    $GLOBALS['base_url'] = $protocol . "://" . $host . $path;
    $GLOBALS['base_url'] = rtrim($GLOBALS['base_url'], '/');
}

$baseUrl = $GLOBALS['base_url'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pregnant Women - Health Station System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/css/style.css">
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
        
        .dashboard-header {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border-left: 4px solid var(--primary);
        }
        
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
        
        .table th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            color: var(--dark);
        }
        
        .badge-urgent {
            background: #e74c3c;
            color: white;
        }
        
        .badge-warning {
            background: #f39c12;
            color: white;
        }
        
        .badge-success {
            background: var(--secondary);
            color: white;
        }
        
        .badge-info {
            background: var(--primary);
            color: white;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <?php include_once $rootPath . '/includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include_once $rootPath . '/includes/sidebar.php'; ?>
            
            <main class="main-content">
                <!-- Dashboard Header -->
                <div class="dashboard-header">
                    <div class="row align-items-center">
                        <div class="col">
                            <h1 class="h3 mb-1">Pregnant Women Management</h1>
                            <p class="text-muted mb-0">Currently pregnant women in the system</p>
                        </div>
                        <div class="col-auto">
                            <span class="badge bg-primary fs-6">Total: <?php echo count($pregnantWomen); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Pregnant Women Section -->
                <div class="content-section">
                    <div class="section-header">
                        <h5><i class="fas fa-baby me-2"></i>Currently Pregnant Women</h5>
                        <a href="<?php echo $baseUrl; ?>/dashboard.php" class="btn btn-view-all">
                            <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                        </a>
                    </div>
                    <div class="section-body">
                        <?php if (!empty($pregnantWomen)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Contact</th>
                                            <th>EDC</th>
                                            <th>Weeks Pregnant</th>
                                            <th>Gravida/Para</th>
                                            <th>Last Visit</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pregnantWomen as $woman): 
                                            $daysUntilDue = $woman['edc'] ? (strtotime($woman['edc']) - strtotime('now')) / (24 * 60 * 60) : null;
                                            $gestationalWeeks = $woman['gestational_weeks'] ?? 0;
                                        ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($woman['first_name'] . ' ' . $woman['last_name']); ?></strong>
                                            </td>
                                            <td><?php echo htmlspecialchars($woman['phone'] ?? 'N/A'); ?></td>
                                            <td>
                                                <?php if (!empty($woman['edc'])): ?>
                                                    <?php echo date('M j, Y', strtotime($woman['edc'])); ?>
                                                    <?php if ($daysUntilDue !== null): ?>
                                                        <br>
                                                        <small class="text-<?php echo $daysUntilDue <= 7 ? 'danger' : ($daysUntilDue <= 30 ? 'warning' : 'success'); ?>">
                                                            <?php echo $daysUntilDue > 0 ? round($daysUntilDue) . ' days' : 'Overdue'; ?>
                                                        </small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Not set</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo $gestationalWeeks; ?> weeks
                                                <?php if ($gestationalWeeks > 40): ?>
                                                    <span class="badge badge-warning">Post-term</span>
                                                <?php elseif ($gestationalWeeks < 37): ?>
                                                    <span class="badge badge-info">Pre-term</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                G<?php echo $woman['gravida'] ?? '?'; ?>P<?php echo $woman['para'] ?? '?'; ?>
                                                <?php if ($woman['abortions'] > 0): ?>
                                                    <br>
                                                    <small class="text-muted">A<?php echo $woman['abortions']; ?>L<?php echo $woman['living_children']; ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($woman['last_prenatal_visit'])): ?>
                                                    <?php echo date('M j, Y', strtotime($woman['last_prenatal_visit'])); ?>
                                                    <br>
                                                    <small class="text-<?php echo ($woman['days_since_visit'] ?? 0) > 30 ? 'danger' : 'success'; ?>">
                                                        <?php echo $woman['days_since_visit'] ?? 'N/A'; ?> days ago
                                                    </small>
                                                <?php else: ?>
                                                    <span class="text-danger">No visits</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (($woman['days_since_visit'] ?? 0) > 30): ?>
                                                    <span class="badge badge-urgent">Due for checkup</span>
                                                <?php elseif ($daysUntilDue !== null && $daysUntilDue <= 7): ?>
                                                    <span class="badge badge-warning">Due soon</span>
                                                <?php else: ?>
                                                    <span class="badge badge-success">Active</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="<?php echo $baseUrl; ?>/mother_profile.php?id=<?php echo $woman['id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                                <a href="<?php echo $baseUrl; ?>/forms/prenatal_form.php?mother_id=<?php echo $woman['id']; ?>" 
                                                   class="btn btn-sm btn-success">
                                                    <i class="fas fa-plus"></i> Visit
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-baby fa-2x mb-3 d-block"></i>
                                <h5>No Currently Pregnant Women</h5>
                                <p class="text-muted">There are no pregnant women in the system at the moment.</p>
                                <a href="<?php echo $baseUrl; ?>/forms/mother_registration.php" class="btn btn-primary">
                                    <i class="fas fa-user-plus me-2"></i>Register New Mother
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>