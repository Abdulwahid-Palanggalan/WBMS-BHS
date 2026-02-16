<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isAuthorized(['midwife'])) {
    header("Location: ../login.php");
    exit();
}

redirectIfNotLoggedIn();

global $pdo;

// ✅ Dashboard stats (secured with fallback values)
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

// Get recent births
$recentBirths = $pdo->query("
    SELECT br.*, m.first_name as mother_first_name, m.last_name as mother_last_name,
           DATEDIFF(NOW(), br.birth_date) as days_old
    FROM birth_records br 
    JOIN mothers m ON br.mother_id = m.id 
    ORDER BY br.birth_date DESC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// ✅ Secure recent activities (prepared statement)
$stmt = $pdo->prepare("
    SELECT activity, timestamp 
    FROM system_activities 
    WHERE user_id = :user_id 
    ORDER BY timestamp DESC 
    LIMIT 8
");
$stmt->execute(['user_id' => $_SESSION['user_id']]);
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get upcoming appointments
$upcomingAppointments = $pdo->query("
    SELECT pr.*, m.first_name, m.last_name, m.phone,
           pd.edc, pr.visit_date
    FROM prenatal_records pr 
    JOIN mothers m ON pr.mother_id = m.id 
    LEFT JOIN pregnancy_details pd ON m.id = pd.mother_id
    WHERE pr.visit_date >= CURDATE() 
    ORDER BY pr.visit_date ASC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// ✅ Get pregnant women (active pregnancies - walang recent birth)
$pregnantWomen = $pdo->query("
    SELECT 
        m.id, m.first_name, m.last_name, m.date_of_birth, m.civil_status,
        m.nationality, m.religion, m.education, m.occupation, m.phone, 
        m.email, m.address, 
        pd.edc, pd.lmp, pd.gravida, pd.para, 
        pd.abortions, pd.living_children, pd.planned_pregnancy,
        hp.first_name as husband_first_name, hp.last_name as husband_last_name, hp.phone as husband_phone,
        m.registered_by, m.created_at,
        DATEDIFF(CURDATE(), m.date_of_birth) / 365.25 as age,
        DATEDIFF(pd.edc, CURDATE()) as days_until_due,
        ROUND(DATEDIFF(CURDATE(), pd.lmp) / 7, 1) as gestational_weeks,
        (SELECT MAX(visit_date) FROM prenatal_records WHERE mother_id = m.id) as last_visit,
        (SELECT COUNT(*) FROM prenatal_records WHERE mother_id = m.id) as prenatal_visits
    FROM mothers m
    JOIN pregnancy_details pd ON m.id = pd.mother_id
    LEFT JOIN husband_partners hp ON m.id = hp.mother_id
    WHERE (pd.edc IS NOT NULL AND pd.edc > CURDATE()) 
       AND m.id NOT IN (
           SELECT DISTINCT mother_id 
           FROM birth_records 
           WHERE birth_date > DATE_SUB(NOW(), INTERVAL 9 MONTH)
       )
    ORDER BY pd.edc ASC
")->fetchAll(PDO::FETCH_ASSOC) ?? [];

// ✅ Get all registered mothers
$registeredMothers = $pdo->query("
    SELECT 
        m.id, m.first_name, m.last_name, m.date_of_birth, m.civil_status,
        m.nationality, m.religion, m.education, m.occupation, m.phone, 
        m.email, m.address,
        pd.edc, pd.lmp, pd.gravida, pd.para, 
        pd.abortions, pd.living_children, pd.planned_pregnancy,
        hp.first_name as husband_first_name, hp.last_name as husband_last_name, hp.phone as husband_phone,
        m.registered_by, m.created_at,
        (SELECT COUNT(*) FROM prenatal_records WHERE mother_id = m.id) as prenatal_visits,
        (SELECT COUNT(*) FROM birth_records WHERE mother_id = m.id) as total_births,
        (SELECT MAX(birth_date) FROM birth_records WHERE mother_id = m.id) as last_birth_date,
        CASE 
            WHEN (pd.edc IS NOT NULL AND pd.edc > CURDATE()) AND m.id NOT IN (
                SELECT DISTINCT mother_id FROM birth_records WHERE birth_date > DATE_SUB(NOW(), INTERVAL 9 MONTH)
            ) THEN 'Pregnant'
            WHEN (SELECT COUNT(*) FROM birth_records WHERE mother_id = m.id) > 0 THEN 'Delivered'
            ELSE 'Registered'
        END as status
    FROM mothers m
    LEFT JOIN pregnancy_details pd ON m.id = pd.mother_id
    LEFT JOIN husband_partners hp ON m.id = hp.mother_id
    ORDER BY m.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC) ?? [];

// ✅ Get all registered babies
$registeredBabies = $pdo->query("
    SELECT 
        br.*,
        m.first_name as mother_first_name, 
        m.last_name as mother_last_name,
        m.phone as mother_phone,
        m.address as mother_address,
        DATEDIFF(CURDATE(), br.birth_date) as age_in_days,
        TIMESTAMPDIFF(MONTH, br.birth_date, CURDATE()) as age_in_months,
        (SELECT COUNT(*) FROM postnatal_records WHERE baby_id = br.id) as postnatal_visits,
        (SELECT MAX(baby_weight) FROM postnatal_records WHERE baby_id = br.id) as last_weight
    FROM birth_records br
    JOIN mothers m ON br.mother_id = m.id
    ORDER BY br.birth_date DESC
")->fetchAll(PDO::FETCH_ASSOC) ?? [];

// ✅ Get prenatal records
$prenatalRecords = $pdo->query("
    SELECT 
        pr.*,
        m.first_name, m.last_name, m.phone,
        pd.edc, pd.lmp,
        hp.first_name as husband_first_name, hp.last_name as husband_last_name,
        DATEDIFF(pr.visit_date, pd.lmp) as gestational_days,
        ROUND(DATEDIFF(pr.visit_date, pd.lmp) / 7, 1) as gestational_weeks,
        (SELECT weight FROM prenatal_records WHERE mother_id = m.id AND visit_date < pr.visit_date ORDER BY visit_date DESC LIMIT 1) as previous_weight,
        pr.weight - (SELECT weight FROM prenatal_records WHERE mother_id = m.id AND visit_date < pr.visit_date ORDER BY visit_date DESC LIMIT 1) as weight_change
    FROM prenatal_records pr
    JOIN mothers m ON pr.mother_id = m.id
    LEFT JOIN pregnancy_details pd ON m.id = pd.mother_id
    LEFT JOIN husband_partners hp ON m.id = hp.mother_id
    ORDER BY pr.visit_date DESC
")->fetchAll(PDO::FETCH_ASSOC) ?? [];

// ✅ Get postnatal records
$postnatalRecords = $pdo->query("
    SELECT 
        pr.*,
        br.first_name as baby_first_name, 
        br.last_name as baby_last_name,
        br.birth_date, br.birth_weight, br.gender,
        m.first_name as mother_first_name, 
        m.last_name as mother_last_name, 
        m.phone,
        DATEDIFF(pr.visit_date, br.birth_date) as days_after_birth,
        pr.baby_weight - br.birth_weight as weight_gain,
        (SELECT baby_weight FROM postnatal_records WHERE baby_id = br.id AND visit_date < pr.visit_date ORDER BY visit_date DESC LIMIT 1) as previous_baby_weight
    FROM postnatal_records pr
    JOIN birth_records br ON pr.baby_id = br.id
    JOIN mothers m ON br.mother_id = m.id
    ORDER BY pr.visit_date DESC
")->fetchAll(PDO::FETCH_ASSOC) ?? [];

// ✅ Get mother weight monitoring
$motherWeightRecords = $pdo->query("
    SELECT 
        pr.id, pr.visit_date, pr.weight,
        m.first_name, m.last_name, m.phone,
        pd.edc, pd.lmp,
        (SELECT weight FROM prenatal_records WHERE mother_id = m.id AND visit_date < pr.visit_date ORDER BY visit_date DESC LIMIT 1) as previous_weight,
        pr.weight - (SELECT weight FROM prenatal_records WHERE mother_id = m.id AND visit_date < pr.visit_date ORDER BY visit_date DESC LIMIT 1) as weight_change,
        CASE 
            WHEN pr.weight - (SELECT weight FROM prenatal_records WHERE mother_id = m.id AND visit_date < pr.visit_date ORDER BY visit_date DESC LIMIT 1) > 2 THEN 'High Gain'
            WHEN pr.weight - (SELECT weight FROM prenatal_records WHERE mother_id = m.id AND visit_date < pr.visit_date ORDER BY visit_date DESC LIMIT 1) < -2 THEN 'Weight Loss'
            ELSE 'Normal'
        END as weight_status,
        ROUND(DATEDIFF(pr.visit_date, pd.lmp) / 7, 1) as gestational_weeks
    FROM prenatal_records pr
    JOIN mothers m ON pr.mother_id = m.id
    LEFT JOIN pregnancy_details pd ON m.id = pd.mother_id
    WHERE pr.weight IS NOT NULL
    ORDER BY pr.visit_date DESC
")->fetchAll(PDO::FETCH_ASSOC) ?? [];

// ✅ Get baby weight monitoring
$babyWeightRecords = $pdo->query("
    SELECT 
        pr.id, pr.visit_date, pr.baby_weight,
        br.first_name, br.last_name, br.birth_weight, br.birth_date,
        m.first_name as mother_first_name, m.last_name as mother_last_name,
        DATEDIFF(pr.visit_date, br.birth_date) as baby_age_days,
        pr.baby_weight - br.birth_weight as total_weight_gain,
        (SELECT baby_weight FROM postnatal_records WHERE baby_id = br.id AND visit_date < pr.visit_date ORDER BY visit_date DESC LIMIT 1) as previous_weight,
        pr.baby_weight - (SELECT baby_weight FROM postnatal_records WHERE baby_id = br.id AND visit_date < pr.visit_date ORDER BY visit_date DESC LIMIT 1) as weight_gain_since_last,
        CASE 
            WHEN pr.baby_weight - br.birth_weight < 0 THEN 'Weight Loss'
            WHEN (pr.baby_weight - br.birth_weight) / DATEDIFF(pr.visit_date, br.birth_date) < 15 THEN 'Slow Gain'
            WHEN (pr.baby_weight - br.birth_weight) / DATEDIFF(pr.visit_date, br.birth_date) > 40 THEN 'Rapid Gain'
            ELSE 'Normal Gain'
        END as growth_status
    FROM postnatal_records pr
    JOIN birth_records br ON pr.baby_id = br.id
    JOIN mothers m ON br.mother_id = m.id
    WHERE pr.baby_weight IS NOT NULL
    ORDER BY pr.visit_date DESC
")->fetchAll(PDO::FETCH_ASSOC) ?? [];
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
    <style>
        :root {
            --primary: #3498db;
            --primary-light: #fce4ec;
            --secondary: #00bcd4;
            --accent: #8bc34a;
            --light: #f8f9fa;
            --dark: #2c3e50;
        }
        
        .dashboard-header {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border-left: 4px solid var(--primary);
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            border-left: 4px solid;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            text-align: center;
            height: 100%;
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card.pregnancies { border-left-color: var(--primary); }
        .stat-card.births { border-left-color: var(--secondary); }
        .stat-card.due { border-left-color: #ff9800; }
        .stat-card.postnatal { border-left-color: var(--accent); }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin: 0 auto 1rem auto;
        }
        
        .stat-card.pregnancies .stat-icon { background: var(--primary-light); color: var(--primary); }
        .stat-card.births .stat-icon { background: #e0f7fa; color: var(--secondary); }
        .stat-card.due .stat-icon { background: #fff3cd; color: #ff9800; }
        .stat-card.postnatal .stat-icon { background: #f1f8e9; color: var(--accent); }
        
        .stat-number {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
            color: var(--dark);
        }
        
        .stat-label {
            color: #6c757d;
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .section-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }
        
        .section-header {
            background: linear-gradient(135deg, var(--dark) 0%, #34495e 100%);
            color: white;
            padding: 1rem 1.5rem;
        }
        
        .section-header h5 {
            margin: 0;
            font-weight: 600;
        }
        
        .section-body {
            padding: 1.5rem;
        }
        
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
            font-size: 2rem;
            margin-bottom: 1rem;
            display: block;
            color: var(--primary);
        }
        
        .quick-access-card .count {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }
        
        .quick-access-card .label {
            color: #6c757d;
            font-weight: 500;
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
            background: var(--accent);
            color: white;
        }
        
        .badge-info {
            background: var(--secondary);
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
        
        .activity-item {
            display: flex;
            align-items: start;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.75rem;
            flex-shrink: 0;
            background: var(--primary-light);
            color: var(--primary);
        }
        
        .appointment-card {
            background: white;
            border-radius: 10px;
            padding: 1rem;
            border-left: 4px solid var(--secondary);
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 1rem;
        }
        
        .due-soon {
            border-left-color: #ff9800;
        }
        
        .urgent {
            border-left-color: #e74c3c;
        }
        
        .action-buttons .btn {
            margin: 0 2px;
            padding: 0.25rem 0.5rem;
        }
        
        .table-container {
            max-height: 400px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/../includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <!-- Main Dashboard View -->
                <div id="dashboard-view">
                    <!-- Dashboard Header -->
                    <div class="dashboard-header">
                        <div class="row align-items-center">
                            <div class="col">
                                <h1 class="h3 mb-2">Midwife Clinical Dashboard</h1>
                                <p class="mb-0 opacity-75">Welcome, <?= htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?>! • Today is <?= date('l, F j, Y'); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Stats -->
                    <div class="row mb-4">
                        <div class="col-md-3 col-6 mb-3">
                            <div class="stat-card pregnancies">
                                <div class="stat-icon">
                                    <i class="fas fa-female"></i>
                                </div>
                                <div class="stat-number"><?= $activePregnancies; ?></div>
                                <div class="stat-label">Active Pregnancies</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="stat-card births">
                                <div class="stat-icon">
                                    <i class="fas fa-baby"></i>
                                </div>
                                <div class="stat-number"><?= $birthsThisMonth; ?></div>
                                <div class="stat-label">Births This Month</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="stat-card due">
                                <div class="stat-icon">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                                <div class="stat-number"><?= $dueThisWeek; ?></div>
                                <div class="stat-label">Due This Week</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="stat-card postnatal">
                                <div class="stat-icon">
                                    <i class="fas fa-baby-carriage"></i>
                                </div>
                                <div class="stat-number"><?= $postnatalDue; ?></div>
                                <div class="stat-label">Postnatal Due</div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Access Cards -->
                    <div class="section-card mb-4">
                        <div class="section-header">
                            <h5><i class="fas fa-tachometer-alt me-2"></i>Quick Access</h5>
                        </div>
                        <div class="section-body">
                            <div class="row g-3">
                                <!-- Pregnant Women - INTERNAL -->
                                <div class="col-md-4 col-6">
                                    <div class="quick-access-card" onclick="showDataTable('pregnant-women')">
                                        <i class="fas fa-baby"></i>
                                        <div class="count"><?= count($pregnantWomen ?: []); ?></div>
                                        <div class="label">Pregnant Women</div>
                                    </div>
                                </div>
                                
                                <!-- ALL MOTHERS - FULL URL -->
                                <div class="col-md-4 col-6">
                                    <a href="/kibenes-ebirth/mothers_list.php" class="quick-access-card" style="display: block; text-decoration: none; color: inherit;">
                                        <i class="fas fa-female"></i>
                                        <div class="count"><?= count($registeredMothers ?: []); ?></div>
                                        <div class="label">All Mothers</div>
                                    </a>
                                </div>
                                
                                <!-- BIRTH RECORDS - FULL URL -->
                                <div class="col-md-4 col-6">
                                    <a href="/kibenes-ebirth/birth_records.php" class="quick-access-card" style="display: block; text-decoration: none; color: inherit;">
                                        <i class="fas fa-baby-carriage"></i>
                                        <div class="count"><?= count($registeredBabies ?: []); ?></div>
                                        <div class="label">Birth Records</div>
                                    </a>
                                </div>
                                
                                <!-- PRENATAL RECORDS - FULL URL -->
                                <div class="col-md-4 col-6">
                                    <a href="/kibenes-ebirth/prenatal_records.php" class="quick-access-card" style="display: block; text-decoration: none; color: inherit;">
                                        <i class="fas fa-heartbeat"></i>
                                        <div class="count"><?= count($prenatalRecords ?: []); ?></div>
                                        <div class="label">Prenatal Records</div>
                                    </a>
                                </div>

                                <!-- POSTNATAL RECORDS - FULL URL -->
                                <div class="col-md-4 col-6">
                                    <a href="/kibenes-ebirth/postnatal_records.php" class="quick-access-card" style="display: block; text-decoration: none; color: inherit;">
                                        <i class="fas fa-baby"></i>
                                        <div class="count"><?= count($postnatalRecords ?: []); ?></div>
                                        <div class="label">Postnatal Records</div>
                                    </a>
                                </div>

                                <!-- BABY WEIGHT - INTERNAL -->
                                <div class="col-md-4 col-6">
                                    <div class="quick-access-card" onclick="showDataTable('baby-weight')">
                                        <i class="fas fa-weight"></i>
                                        <div class="count"><?= count($babyWeightRecords ?: []); ?></div>
                                        <div class="label">Baby Weight</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Left Column -->
                        <div class="col-lg-8">
                            <!-- Mothers Due for Checkup -->
                            <div class="section-card mb-4">
                                <div class="section-header">
                                    <h5><i class="fas fa-exclamation-triangle me-2"></i>Mothers Due for Checkup</h5>
                                    <span class="badge badge-urgent"><?= count($mothersDueCheckup); ?> need attention</span>
                                </div>
                                <div class="section-body">
                                    <?php if (!empty($mothersDueCheckup)): ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Mother</th>
                                                        <th>Contact</th>
                                                        <th>Last Visit</th>
                                                        <th>Due Date</th>
                                                        <th>Status</th>
                                                        <th>Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($mothersDueCheckup as $mother): ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?= htmlspecialchars($mother['first_name'] . ' ' . $mother['last_name']); ?></strong>
                                                        </td>
                                                        <td><?= htmlspecialchars($mother['phone']); ?></td>
                                                        <td>
                                                            <?php if ($mother['days_since_visit']): ?>
                                                                <span class="text-danger"><?= $mother['days_since_visit']; ?> days ago</span>
                                                            <?php else: ?>
                                                                <span class="text-danger">No visits</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if (!empty($mother['due_date']) && $mother['due_date'] != '0000-00-00'): ?>
                                                                <?= date('M j, Y', strtotime($mother['due_date'])); ?>
                                                            <?php else: ?>
                                                                <span class="text-muted">Not set</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <span class="badge badge-urgent">Due for checkup</span>
                                                        </td>
                                                        <td>
                                                            <a href="/kibenes-ebirth/forms/prenatal_form.php?mother_id=<?= $mother['id']; ?>" class="btn btn-success btn-sm">
                                                                <i class="fas fa-plus"></i> Schedule Visit
                                                            </a>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <div class="empty-state">
                                            <i class="fas fa-check-circle text-success"></i>
                                            <h5>All Caught Up!</h5>
                                            <p class="text-muted">No mothers are currently due for checkups.</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Recent Births -->
                            <div class="section-card">
                                <div class="section-header">
                                    <h5><i class="fas fa-baby me-2"></i>Recent Births</h5>
                                </div>
                                <div class="section-body">
                                    <?php if (!empty($recentBirths)): ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Baby Name</th>
                                                        <th>Birth Date</th>
                                                        <th>Gender</th>
                                                        <th>Weight</th>
                                                        <th>Mother</th>
                                                        <th>Age</th>
                                                        <th>Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($recentBirths as $baby): ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?= htmlspecialchars($baby['first_name'] . ' ' . $baby['last_name']); ?></strong>
                                                        </td>
                                                        <td>
                                                            <?= date('M j, Y', strtotime($baby['birth_date'])); ?>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-<?= $baby['gender'] == 'Male' ? 'primary' : 'danger'; ?>">
                                                                <?= ucfirst($baby['gender']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?= $baby['birth_weight'] ? $baby['birth_weight'] . ' kg' : 'N/A'; ?>
                                                            <?php if ($baby['birth_weight'] && $baby['birth_weight'] < 2.5): ?>
                                                                <span class="badge bg-warning ms-1">Low</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?= htmlspecialchars($baby['mother_first_name'] . ' ' . $baby['mother_last_name']); ?></td>
                                                        <td>
                                                            <small class="text-muted"><?= $baby['days_old']; ?> days old</small>
                                                        </td>
                                                        <td>
                                                            <a href="/kibenes-ebirth/forms/postnatal_form.php?baby_id=<?= $baby['id']; ?>" class="btn btn-info btn-sm">
                                                                <i class="fas fa-baby-carriage"></i> Postnatal
                                                            </a>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <div class="empty-state">
                                            <i class="fas fa-baby"></i>
                                            <h5>No Recent Births</h5>
                                            <p class="text-muted">No birth records found for this month.</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column -->
                        <div class="col-lg-4">
                            <!-- Upcoming Appointments -->
                            <div class="section-card mb-4">
                                <div class="section-header">
                                    <h5><i class="fas fa-calendar me-2"></i>Upcoming Appointments</h5>
                                </div>
                                <div class="section-body">
                                    <?php if (!empty($upcomingAppointments)): ?>
                                        <div class="appointments-list">
                                            <?php foreach ($upcomingAppointments as $appointment): 
                                                $daysUntil = date_diff(new DateTime(), new DateTime($appointment['visit_date']))->days;
                                                $cardClass = $daysUntil == 0 ? 'urgent' : ($daysUntil <= 2 ? 'due-soon' : '');
                                            ?>
                                            <div class="appointment-card <?= $cardClass; ?>">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <h6 class="mb-0"><?= htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?></h6>
                                                    <span class="badge bg-<?= $daysUntil == 0 ? 'danger' : ($daysUntil <= 2 ? 'warning' : 'info'); ?>">
                                                        <?= $daysUntil == 0 ? 'Today' : ($daysUntil == 1 ? 'Tomorrow' : $daysUntil . ' days'); ?>
                                                    </span>
                                                </div>
                                                <p class="text-muted mb-1 small">
                                                    <i class="fas fa-calendar me-1"></i>
                                                    <?= date('M j, Y', strtotime($appointment['visit_date'])); ?>
                                                </p>
                                                <p class="text-muted mb-2 small">
                                                    <i class="fas fa-phone me-1"></i>
                                                    <?= htmlspecialchars($appointment['phone']); ?>
                                                </p>
                                                <?php if (!empty($appointment['edc']) && $appointment['edc'] != '0000-00-00'): ?>
                                                <p class="text-muted mb-0 small">
                                                    <i class="fas fa-baby me-1"></i>
                                                    Due: <?= date('M j, Y', strtotime($appointment['edc'])); ?>
                                                </p>
                                                <?php endif; ?>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="empty-state py-3">
                                            <i class="fas fa-calendar"></i>
                                            <h6>No Appointments</h6>
                                            <p class="text-muted">No upcoming appointments scheduled.</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Recent Activities -->
                            <div class="section-card">
                                <div class="section-header">
                                    <h5><i class="fas fa-history me-2"></i>Recent Activities</h5>
                                </div>
                                <div class="section-body">
                                    <?php if (!empty($activities)): ?>
                                        <div class="activity-feed" style="max-height: 300px; overflow-y: auto;">
                                            <?php foreach ($activities as $activity): ?>
                                            <div class="activity-item">
                                                <div class="activity-icon">
                                                    <i class="fas fa-bell"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="small"><?= htmlspecialchars($activity['activity']); ?></div>
                                                    <div class="text-muted small">
                                                        <i class="fas fa-clock me-1"></i>
                                                        <?= date('M j, g:i A', strtotime($activity['timestamp'])); ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="empty-state py-3">
                                            <i class="fas fa-history"></i>
                                            <h6>No Activities</h6>
                                            <p class="text-muted">No recent activities found.</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Data Tables Views (Internal) -->
                
                <!-- Pregnant Women Table -->
                <div id="pregnant-women-table" class="data-table-section" style="display: none;">
                    <div class="back-to-dashboard mb-3">
                        <button class="btn btn-secondary" onclick="showDashboard()">
                            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                        </button>
                    </div>
                    <div class="section-card">
                        <div class="section-header">
                            <h5><i class="fas fa-baby me-2"></i>Pregnant Women (<?= count($pregnantWomen ?: []); ?>)</h5>
                        </div>
                        <div class="section-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Mother</th>
                                            <th>Contact</th>
                                            <th>EDC</th>
                                            <th>Weeks Pregnant</th>
                                            <th>Gravida/Para</th>
                                            <th>Last Visit</th>
                                            <!-- <th>Actions</th> -->
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pregnantWomen as $mother): 
                                            $daysUntilDue = $mother['edc'] ? (strtotime($mother['edc']) - strtotime('now')) / (24 * 60 * 60) : 'N/A';
                                        ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($mother['first_name'] . ' ' . $mother['last_name']); ?></strong><br>
                                                <small class="text-muted"><?= round($mother['age']) ?> years</small>
                                            </td>
                                            <td><?= htmlspecialchars($mother['phone']); ?></td>
                                            <td>
                                                <?php if ($mother['edc']): ?>
                                                    <?= date('M j, Y', strtotime($mother['edc'])); ?><br>
                                                    <small class="text-<?= $daysUntilDue <= 7 ? 'danger' : ($daysUntilDue <= 30 ? 'warning' : 'success'); ?>">
                                                        <?= $daysUntilDue > 0 ? $daysUntilDue . ' days' : 'Overdue'; ?>
                                                    </small>
                                                <?php else: ?>
                                                    <span class="text-muted">Not set</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= $mother['gestational_weeks'] ?> weeks</td>
                                            <td>
                                                G<?= $mother['gravida']; ?>P<?= $mother['para']; ?><br>
                                                <small class="text-muted">A<?= $mother['abortions']; ?>L<?= $mother['living_children']; ?></small>
                                            </td>
                                            <td>
                                                <?php if ($mother['last_visit']): ?>
                                                    <?= date('M j, Y', strtotime($mother['last_visit'])); ?>
                                                <?php else: ?>
                                                    <span class="text-danger">No visits</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <!-- <a href="../forms/prenatal_form.php?mother_id=<?= $mother['id']; ?>" class="btn btn-success btn-sm">
                                                    <i class="fas fa-plus"></i> Visit
                                                </a> -->
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Baby Weight Table -->
                <div id="baby-weight-table" class="data-table-section" style="display: none;">
                    <div class="back-to-dashboard mb-3">
                        <button class="btn btn-secondary" onclick="showDashboard()">
                            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                        </button>
                    </div>
                    <div class="section-card">
                        <div class="section-header">
                            <h5><i class="fas fa-weight me-2"></i>Baby Weight Monitoring (<?= count($babyWeightRecords ?: []); ?>)</h5>
                        </div>
                        <div class="section-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Baby Name</th>
                                            <th>Mother</th>
                                            <th>Age</th>
                                            <th>Birth Weight</th>
                                            <th>Current Weight</th>
                                            <th>Total Gain</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($babyWeightRecords as $record): ?>
                                        <tr>
                                            <td><?= date('M j, Y', strtotime($record['visit_date'])) ?></td>
                                            <td><strong><?= htmlspecialchars($record['first_name'] . ' ' . $record['last_name']) ?></strong></td>
                                            <td><?= htmlspecialchars($record['mother_first_name'] . ' ' . $record['mother_last_name']) ?></td>
                                            <td>
                                                <?php
                                                $ageDays = $record['baby_age_days'];
                                                if ($ageDays < 30) {
                                                    echo $ageDays . ' days';
                                                } else {
                                                    echo floor($ageDays / 30) . ' months';
                                                }
                                                ?>
                                            </td>
                                            <td><?= $record['birth_weight'] ?> kg</td>
                                            <td><strong><?= $record['baby_weight'] ?> kg</strong></td>
                                            <td>
                                                <?php
                                                $totalGain = $record['total_weight_gain'];
                                                $class = $totalGain >= 0 ? 'text-success' : 'text-danger';
                                                $icon = $totalGain >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';
                                                ?>
                                                <span class="<?= $class ?>">
                                                    <i class="fas <?= $icon ?>"></i>
                                                    <?= abs($totalGain) ?> kg
                                                </span>
                                            </td>
                                            <td>
                                                <?php
                                                $badgeClass = [
                                                    'Weight Loss' => 'bg-danger',
                                                    'Slow Gain' => 'bg-warning',
                                                    'Rapid Gain' => 'bg-info',
                                                    'Normal Gain' => 'bg-success'
                                                ][$record['growth_status']] ?? 'bg-secondary';
                                                ?>
                                                <span class="badge <?= $badgeClass ?>">
                                                    <?= $record['growth_status'] ?>
                                                </span>
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
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh every 10 minutes
        setTimeout(function() {
            window.location.reload();
        }, 600000);

        // View management functions
        function showDashboard() {
            document.getElementById('dashboard-view').style.display = 'block';
            hideAllDataTables();
        }

        function showDataTable(tableType) {
            document.getElementById('dashboard-view').style.display = 'none';
            hideAllDataTables();
            
            const tableElement = document.getElementById(tableType + '-table');
            if (tableElement) {
                tableElement.style.display = 'block';
            }
        }

        function hideAllDataTables() {
            const dataTables = document.querySelectorAll('.data-table-section');
            dataTables.forEach(table => {
                table.style.display = 'none';
            });
        }

        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
</body>
</html>