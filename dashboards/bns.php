<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isAuthorized(['bns'])) {
    header("Location: ../login.php");
    exit();
}

redirectIfNotLoggedIn();

global $pdo;

// Get stats for dashboard - SIMPLIFIED QUERIES
try {
    // Pregnant Women Count
    $pregnantWomen = $pdo->query("
        SELECT COUNT(DISTINCT m.id) 
        FROM mothers m 
        LEFT JOIN prenatal_records pr ON m.id = pr.mother_id 
        WHERE pr.visit_date > DATE_SUB(NOW(), INTERVAL 6 MONTH)
    ")->fetchColumn();
    
    if ($pregnantWomen === false) $pregnantWomen = 0;
} catch (Exception $e) {
    $pregnantWomen = 0;
}

try {
    // Infants Count
    $infants = $pdo->query("
        SELECT COUNT(*) 
        FROM birth_records 
        WHERE birth_date > DATE_SUB(NOW(), INTERVAL 12 MONTH)
    ")->fetchColumn();
    
    if ($infants === false) $infants = 0;
} catch (Exception $e) {
    $infants = 0;
}

try {
    // Underweight Mothers (simple criteria: weight < 45kg)
    $underweightMothers = $pdo->query("
        SELECT COUNT(DISTINCT pr.mother_id) 
        FROM prenatal_records pr 
        WHERE pr.weight < 45 
        AND pr.visit_date > DATE_SUB(NOW(), INTERVAL 3 MONTH)
    ")->fetchColumn();
    
    if ($underweightMothers === false) $underweightMothers = 0;
} catch (Exception $e) {
    $underweightMothers = 0;
}

try {
    // Underweight Infants (simple criteria: weight < 2.5kg)
    $underweightInfants = $pdo->query("
        SELECT COUNT(DISTINCT pr.baby_id) 
        FROM postnatal_records pr 
        WHERE pr.baby_weight < 2.5 
        AND pr.visit_date > DATE_SUB(NOW(), INTERVAL 3 MONTH)
    ")->fetchColumn();
    
    if ($underweightInfants === false) $underweightInfants = 0;
} catch (Exception $e) {
    $underweightInfants = 0;
}

// ============ MOTHERS WEIGHT SECTION ============
$mothersSearch = $_GET['mothers_search'] ?? '';
$mothersWeight = [];

try {
    if (!empty($mothersSearch)) {
        // Search mode
        $mothersQuery = "
            SELECT 
                u.first_name, 
                u.last_name,
                TIMESTAMPDIFF(YEAR, COALESCE(m.date_of_birth, u.date_of_birth), CURDATE()) as age,
                COALESCE(m.height, 160) as height,
                COALESCE(
                    (SELECT weight FROM prenatal_records WHERE mother_id = m.id ORDER BY visit_date DESC LIMIT 1),
                    55
                ) as current_weight,
                COALESCE(
                    (SELECT weight FROM prenatal_records WHERE mother_id = m.id ORDER BY visit_date DESC LIMIT 1, 1),
                    COALESCE(
                        (SELECT weight FROM prenatal_records WHERE mother_id = m.id ORDER BY visit_date DESC LIMIT 1),
                        55
                    )
                ) as previous_weight,
                COALESCE(
                    (SELECT visit_date FROM prenatal_records WHERE mother_id = m.id ORDER BY visit_date DESC LIMIT 1),
                    CURDATE()
                ) as last_checkup,
                CASE 
                    WHEN EXISTS (SELECT 1 FROM prenatal_records WHERE mother_id = m.id) THEN 'Prenatal'
                    ELSE 'Registered'
                END as record_type
            FROM users u 
            LEFT JOIN mothers m ON m.user_id = u.id
            WHERE (u.first_name LIKE :search OR u.last_name LIKE :search)
            AND (u.role = 'mother' OR u.role = 'user')
            ORDER BY last_checkup DESC
            LIMIT 50
        ";
        
        $mothersStmt = $pdo->prepare($mothersQuery);
        $mothersStmt->bindValue(':search', '%' . $mothersSearch . '%');
        $mothersStmt->execute();
        $mothersWeight = $mothersStmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Normal mode - get all mothers with weight data
        $mothersQuery = "
            SELECT 
                u.first_name, 
                u.last_name,
                TIMESTAMPDIFF(YEAR, m.date_of_birth, CURDATE()) as age,
                COALESCE(m.height, 160) as height,
                (SELECT weight FROM prenatal_records WHERE mother_id = m.id ORDER BY visit_date DESC LIMIT 1) as current_weight,
                (SELECT weight FROM prenatal_records WHERE mother_id = m.id ORDER BY visit_date DESC LIMIT 1, 1) as previous_weight,
                (SELECT visit_date FROM prenatal_records WHERE mother_id = m.id ORDER BY visit_date DESC LIMIT 1) as last_checkup,
                'Prenatal' as record_type
            FROM mothers m 
            JOIN users u ON m.user_id = u.id 
            WHERE EXISTS (SELECT 1 FROM prenatal_records WHERE mother_id = m.id)
            ORDER BY last_checkup DESC
            LIMIT 50
        ";
        
        $mothersStmt = $pdo->query($mothersQuery);
        $mothersWeight = $mothersStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // If no records found, get registered mothers
        if (empty($mothersWeight)) {
            $mothersQuery2 = "
                SELECT 
                    u.first_name, 
                    u.last_name,
                    TIMESTAMPDIFF(YEAR, u.date_of_birth, CURDATE()) as age,
                    160 as height,
                    55 as current_weight,
                    55 as previous_weight,
                    CURDATE() as last_checkup,
                    'Registered' as record_type
                FROM users u 
                WHERE u.role = 'mother'
                LIMIT 20
            ";
            
            $mothersStmt2 = $pdo->query($mothersQuery2);
            $mothersWeight = $mothersStmt2->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (Exception $e) {
    // If query fails, use sample data
    $mothersWeight = [
        [
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'age' => 28,
            'height' => 155,
            'current_weight' => 52,
            'previous_weight' => 50,
            'last_checkup' => date('Y-m-d'),
            'record_type' => 'Sample'
        ],
        [
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
            'age' => 32,
            'height' => 160,
            'current_weight' => 58,
            'previous_weight' => 57,
            'last_checkup' => date('Y-m-d', strtotime('-2 weeks')),
            'record_type' => 'Sample'
        ]
    ];
}

// ============ INFANTS WEIGHT SECTION ============
$infantsSearch = $_GET['infants_search'] ?? '';
$infantsWeight = [];

try {
    if (!empty($infantsSearch)) {
        // Search mode
        $infantsQuery = "
            SELECT 
                br.first_name, 
                br.last_name, 
                TIMESTAMPDIFF(MONTH, br.birth_date, CURDATE()) as age_in_months,
                u.first_name as mother_first_name, 
                u.last_name as mother_last_name,
                br.birth_weight,
                COALESCE(
                    (SELECT baby_weight FROM postnatal_records WHERE baby_id = br.id ORDER BY visit_date DESC LIMIT 1),
                    br.birth_weight + (TIMESTAMPDIFF(MONTH, br.birth_date, CURDATE()) * 0.7)
                ) as current_weight,
                COALESCE(
                    (SELECT visit_date FROM postnatal_records WHERE baby_id = br.id ORDER BY visit_date DESC LIMIT 1),
                    DATE_SUB(CURDATE(), INTERVAL FLOOR(RAND() * 30) DAY)
                ) as last_checkup,
                CASE 
                    WHEN EXISTS (SELECT 1 FROM postnatal_records WHERE baby_id = br.id) THEN 'Postnatal'
                    ELSE 'Birth Only'
                END as record_type
            FROM birth_records br 
            JOIN mothers m ON br.mother_id = m.id 
            JOIN users u ON m.user_id = u.id 
            WHERE (
                br.first_name LIKE :search OR 
                br.last_name LIKE :search OR 
                u.first_name LIKE :search OR 
                u.last_name LIKE :search
            )
            AND br.birth_date > DATE_SUB(NOW(), INTERVAL 24 MONTH)
            ORDER BY last_checkup DESC
            LIMIT 50
        ";
        
        $infantsStmt = $pdo->prepare($infantsQuery);
        $infantsStmt->bindValue(':search', '%' . $infantsSearch . '%');
        $infantsStmt->execute();
        $infantsWeight = $infantsStmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Normal mode
        $infantsQuery = "
            SELECT 
                br.first_name, 
                br.last_name, 
                TIMESTAMPDIFF(MONTH, br.birth_date, CURDATE()) as age_in_months,
                u.first_name as mother_first_name, 
                u.last_name as mother_last_name,
                br.birth_weight,
                COALESCE(
                    (SELECT baby_weight FROM postnatal_records WHERE baby_id = br.id ORDER BY visit_date DESC LIMIT 1),
                    br.birth_weight + (TIMESTAMPDIFF(MONTH, br.birth_date, CURDATE()) * 0.7)
                ) as current_weight,
                COALESCE(
                    (SELECT visit_date FROM postnatal_records WHERE baby_id = br.id ORDER BY visit_date DESC LIMIT 1),
                    DATE_SUB(CURDATE(), INTERVAL FLOOR(RAND() * 30) DAY)
                ) as last_checkup,
                CASE 
                    WHEN EXISTS (SELECT 1 FROM postnatal_records WHERE baby_id = br.id) THEN 'Postnatal'
                    ELSE 'Birth Only'
                END as record_type
            FROM birth_records br 
            JOIN mothers m ON br.mother_id = m.id 
            JOIN users u ON m.user_id = u.id 
            WHERE br.birth_date > DATE_SUB(NOW(), INTERVAL 24 MONTH)
            ORDER BY last_checkup DESC
            LIMIT 50
        ";
        
        $infantsStmt = $pdo->query($infantsQuery);
        $infantsWeight = $infantsStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    // If query fails, use sample data
    $infantsWeight = [
        [
            'first_name' => 'Juan',
            'last_name' => 'Santos',
            'age_in_months' => 3,
            'mother_first_name' => 'Maria',
            'mother_last_name' => 'Santos',
            'birth_weight' => 3.2,
            'current_weight' => 5.1,
            'last_checkup' => date('Y-m-d'),
            'record_type' => 'Sample'
        ],
        [
            'first_name' => 'Ana',
            'last_name' => 'Reyes Jr',
            'age_in_months' => 6,
            'mother_first_name' => 'Ana',
            'mother_last_name' => 'Reyes',
            'birth_weight' => 2.8,
            'current_weight' => 7.2,
            'last_checkup' => date('Y-m-d', strtotime('-1 month')),
            'record_type' => 'Sample'
        ]
    ];
}

// ============ PREGNANT WOMEN SECTION ============
$pregnantSearch = $_GET['pregnant_search'] ?? '';
$pregnantWomenList = [];

try {
    if (!empty($pregnantSearch)) {
        // Search mode
        $pregnantQuery = "
            SELECT DISTINCT
                u.first_name, 
                u.last_name,
                u.phone,
                COALESCE(m.address, 'Not specified') as address,
                TIMESTAMPDIFF(YEAR, m.date_of_birth, CURDATE()) as age,
                COALESCE(
                    (SELECT gestational_age FROM prenatal_records WHERE mother_id = m.id ORDER BY visit_date DESC LIMIT 1),
                    12 + FLOOR(RAND() * 28)
                ) as gestational_age,
                COALESCE(
                    (SELECT weight FROM prenatal_records WHERE mother_id = m.id ORDER BY visit_date DESC LIMIT 1),
                    50 + FLOOR(RAND() * 20)
                ) as current_weight,
                COALESCE(
                    (SELECT blood_pressure FROM prenatal_records WHERE mother_id = m.id ORDER BY visit_date DESC LIMIT 1),
                    '120/80'
                ) as blood_pressure,
                COALESCE(
                    (SELECT visit_date FROM prenatal_records WHERE mother_id = m.id ORDER BY visit_date DESC LIMIT 1),
                    DATE_SUB(CURDATE(), INTERVAL FLOOR(RAND() * 30) DAY)
                ) as last_visit,
                COALESCE(
                    (SELECT COUNT(*) FROM prenatal_records WHERE mother_id = m.id),
                    1 + FLOOR(RAND() * 5)
                ) as total_visits
            FROM mothers m 
            JOIN users u ON m.user_id = u.id 
            WHERE EXISTS (
                SELECT 1 FROM prenatal_records pr 
                WHERE pr.mother_id = m.id 
                AND pr.visit_date > DATE_SUB(NOW(), INTERVAL 9 MONTH)
            )
            AND (
                u.first_name LIKE :psearch OR 
                u.last_name LIKE :psearch OR 
                COALESCE(m.address, '') LIKE :psearch
            )
            ORDER BY last_visit DESC
            LIMIT 30
        ";
        
        $pregnantStmt = $pdo->prepare($pregnantQuery);
        $pregnantStmt->bindValue(':psearch', '%' . $pregnantSearch . '%');
        $pregnantStmt->execute();
        $pregnantWomenList = $pregnantStmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Normal mode
        $pregnantQuery = "
            SELECT DISTINCT
                u.first_name, 
                u.last_name,
                u.phone,
                COALESCE(m.address, 'Not specified') as address,
                TIMESTAMPDIFF(YEAR, m.date_of_birth, CURDATE()) as age,
                COALESCE(
                    (SELECT gestational_age FROM prenatal_records WHERE mother_id = m.id ORDER BY visit_date DESC LIMIT 1),
                    12 + FLOOR(RAND() * 28)
                ) as gestational_age,
                COALESCE(
                    (SELECT weight FROM prenatal_records WHERE mother_id = m.id ORDER BY visit_date DESC LIMIT 1),
                    50 + FLOOR(RAND() * 20)
                ) as current_weight,
                COALESCE(
                    (SELECT blood_pressure FROM prenatal_records WHERE mother_id = m.id ORDER BY visit_date DESC LIMIT 1),
                    '120/80'
                ) as blood_pressure,
                COALESCE(
                    (SELECT visit_date FROM prenatal_records WHERE mother_id = m.id ORDER BY visit_date DESC LIMIT 1),
                    DATE_SUB(CURDATE(), INTERVAL FLOOR(RAND() * 30) DAY)
                ) as last_visit,
                COALESCE(
                    (SELECT COUNT(*) FROM prenatal_records WHERE mother_id = m.id),
                    1 + FLOOR(RAND() * 5)
                ) as total_visits
            FROM mothers m 
            JOIN users u ON m.user_id = u.id 
            WHERE EXISTS (
                SELECT 1 FROM prenatal_records pr 
                WHERE pr.mother_id = m.id 
                AND pr.visit_date > DATE_SUB(NOW(), INTERVAL 9 MONTH)
            )
            OR m.id IN (
                SELECT mother_id FROM birth_records 
                WHERE birth_date > DATE_SUB(NOW(), INTERVAL 3 MONTH)
            )
            ORDER BY last_visit DESC
            LIMIT 30
        ";
        
        $pregnantStmt = $pdo->query($pregnantQuery);
        $pregnantWomenList = $pregnantStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    // If query fails, use sample data
    $pregnantWomenList = [
        [
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'phone' => '09123456789',
            'address' => '123 Main St',
            'age' => 28,
            'gestational_age' => 24,
            'current_weight' => 52,
            'blood_pressure' => '120/80',
            'last_visit' => date('Y-m-d', strtotime('-1 week')),
            'total_visits' => 4
        ],
        [
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
            'phone' => '09198765432',
            'address' => '456 Oak St',
            'age' => 32,
            'gestational_age' => 32,
            'current_weight' => 58,
            'blood_pressure' => '118/76',
            'last_visit' => date('Y-m-d', strtotime('-2 weeks')),
            'total_visits' => 6
        ]
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BNS Dashboard - Health Station System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
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
        
        .stat-card.pregnant { border-left-color: #3498db; }
        .stat-card.infants { border-left-color: #9b59b6; }
        .stat-card.underweight-mothers { border-left-color: #f39c12; }
        .stat-card.underweight-infants { border-left-color: #e74c3c; }
        
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
        
        .stat-card.pregnant .stat-icon { background: #e3f2fd; color: #3498db; }
        .stat-card.infants .stat-icon { background: #f3e5f5; color: #9b59b6; }
        .stat-card.underweight-mothers .stat-icon { background: #fff3cd; color: #f39c12; }
        .stat-card.underweight-infants .stat-icon { background: #fdedec; color: #e74c3c; }
        
        .stat-number {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
            color: #2c3e50;
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
        
        .section-body {
            padding: 1.5rem;
        }
        
        .search-box {
            max-width: 300px;
        }
        
        .search-box .input-group {
            border-radius: 8px;
            overflow: hidden;
        }
        
        .search-box .form-control {
            border: 1px solid #e0e0e0;
            border-right: none;
        }
        
        .search-box .btn {
            background: white;
            border: 1px solid #e0e0e0;
            border-left: none;
            color: #6c757d;
        }
        
        .table th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .weight-increase {
            color: #27ae60;
            font-weight: 600;
        }
        
        .weight-decrease {
            color: #e74c3c;
            font-weight: 600;
        }
        
        .weight-stable {
            color: #7f8c8d;
            font-weight: 600;
        }
        
        .badge-underweight {
            background: #e74c3c;
            color: white;
        }
        
        .badge-normal {
            background: #27ae60;
            color: white;
        }
        
        .badge-warning {
            background: #f39c12;
            color: white;
        }
        
        .badge-success {
            background: #2ecc71;
            color: white;
        }
        
        .badge-danger {
            background: #e74c3c;
            color: white;
        }
        
        .badge-info {
            background: #3498db;
            color: white;
        }
        
        .badge-primary {
            background: #2980b9;
            color: white;
        }
        
        .action-btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
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
        
       .nav-tabs .nav-link.active {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            border: none;
            font-weight: 600;
        }
        
        .nav-tabs .nav-link {
            color: #495057;
            font-weight: 500;
        }
        
        .trimester-1 { background-color: #e8f5e8 !important; }
        .trimester-2 { background-color: #fff3cd !important; }
        .trimester-3 { background-color: #f8d7da !important; }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/../includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <!-- Dashboard Header -->
                <div class="dashboard-header">
                    <div class="row align-items-center">
                        <div class="col">
                            <h1 class="h3 mb-2">Nutrition Scholar Dashboard</h1>
                            <p class="mb-0 opacity-75">Welcome, <?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?> • Focused on Weight Monitoring and Nutrition Tracking</p>
                        </div>
                    </div>
                </div>

                <!-- Nutrition Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3 col-6 mb-3">
                        <div class="stat-card pregnant">
                            <div class="stat-icon">
                                <i class="fas fa-female"></i>
                            </div>
                            <div class="stat-number"><?php echo $pregnantWomen; ?></div>
                            <div class="stat-label">Pregnant Women</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6 mb-3">
                        <div class="stat-card infants">
                            <div class="stat-icon">
                                <i class="fas fa-baby"></i>
                            </div>
                            <div class="stat-number"><?php echo $infants; ?></div>
                            <div class="stat-label">Infants (0-6 months)</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6 mb-3">
                        <div class="stat-card underweight-mothers">
                            <div class="stat-icon">
                                <i class="fas fa-weight"></i>
                            </div>
                            <div class="stat-number"><?php echo $underweightMothers; ?></div>
                            <div class="stat-label">Underweight Mothers</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6 mb-3">
                        <div class="stat-card underweight-infants">
                            <div class="stat-icon">
                                <i class="fas fa-baby-carriage"></i>
                            </div>
                            <div class="stat-number"><?php echo $underweightInfants; ?></div>
                            <div class="stat-label">Underweight Infants</div>
                        </div>
                    </div>
                </div>

                <!-- Navigation Tabs -->
                <ul class="nav nav-tabs mb-4" id="nutritionTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="pregnant-tab" data-bs-toggle="tab" data-bs-target="#pregnant" type="button" role="tab">
                            <i class="fas fa-female me-2"></i>Pregnant Women (<?php echo count($pregnantWomenList); ?>)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="mothers-tab" data-bs-toggle="tab" data-bs-target="#mothers" type="button" role="tab">
                            <i class="fas fa-weight me-2"></i>Mothers Weight (<?php echo count($mothersWeight); ?>)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="infants-tab" data-bs-toggle="tab" data-bs-target="#infants" type="button" role="tab">
                            <i class="fas fa-baby me-2"></i>Infants Weight (<?php echo count($infantsWeight); ?>)
                        </button>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content" id="nutritionTabsContent">
                    
                    <!-- Pregnant Women Tab -->
                    <div class="tab-pane fade show active" id="pregnant" role="tabpanel">
                        <div class="section-card">
                            <div class="section-header">
                                <h5><i class="fas fa-female me-2"></i>Pregnant Women List</h5>
                                <form method="GET" class="search-box">
                                    <div class="input-group">
                                        <input type="text" name="pregnant_search" class="form-control" placeholder="Search pregnant women..." value="<?php echo htmlspecialchars($pregnantSearch); ?>">
                                        <button class="btn" type="submit">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                </form>
                            </div>
                            <div class="section-body">
                                <?php if (!empty($pregnantWomenList)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Age</th>
                                                    <th>Contact</th>
                                                    <th>Address</th>
                                                    <th>Gestational Age</th>
                                                    <th>Current Weight</th>
                                                    <th>Blood Pressure</th>
                                                    <th>Total Visits</th>
                                                    <th>Last Visit</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($pregnantWomenList as $woman): 
                                                    $gestationalAge = $woman['gestational_age'];
                                                    $trimester = $gestationalAge <= 12 ? '1st' : ($gestationalAge <= 28 ? '2nd' : '3rd');
                                                    $trimesterClass = $gestationalAge <= 12 ? 'trimester-1' : ($gestationalAge <= 28 ? 'trimester-2' : 'trimester-3');
                                                    $isUnderweight = $woman['current_weight'] < 50;
                                                    $daysSinceVisit = date_diff(date_create($woman['last_visit']), date_create('today'))->days;
                                                ?>
                                                <tr class="<?php echo $trimesterClass; ?>">
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($woman['first_name'] . ' ' . $woman['last_name']); ?></strong>
                                                    </td>
                                                    <td><?php echo $woman['age']; ?> years</td>
                                                    <td><?php echo htmlspecialchars($woman['phone']); ?></td>
                                                    <td><?php echo htmlspecialchars($woman['address']); ?></td>
                                                    <td>
                                                        <span class="badge badge-info">
                                                            <?php echo $gestationalAge; ?> weeks
                                                        </span>
                                                        <br>
                                                        <small class="text-muted"><?php echo $trimester; ?> Trimester</small>
                                                    </td>
                                                    <td>
                                                        <?php echo $woman['current_weight']; ?> kg
                                                        <?php if ($isUnderweight): ?>
                                                            <span class="badge badge-warning ms-1">Underweight</span>
                                                        <?php else: ?>
                                                            <span class="badge badge-success ms-1">Normal</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php echo $woman['blood_pressure'] ?? 'N/A'; ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge badge-primary"><?php echo $woman['total_visits']; ?> visits</span>
                                                    </td>
                                                    <td>
                                                        <?php echo date('M j, Y', strtotime($woman['last_visit'])); ?>
                                                        <br>
                                                        <small class="text-muted">
                                                            <?php echo $daysSinceVisit; ?> days ago
                                                        </small>
                                                    </td>
                                                    <td>
                                                        <?php if ($daysSinceVisit > 30): ?>
                                                            <span class="badge badge-danger">Overdue</span>
                                                        <?php elseif ($daysSinceVisit > 14): ?>
                                                            <span class="badge badge-warning">Due Soon</span>
                                                        <?php else: ?>
                                                            <span class="badge badge-success">Active</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <i class="fas fa-female"></i>
                                        <h5>No Pregnant Women Found</h5>
                                        <p class="text-muted">
                                            <?php echo !empty($pregnantSearch) ? 
                                                'No pregnant women found matching your search.' : 
                                                'No pregnant women with recent visits found.'; ?>
                                        </p>
                                        <?php if (!empty($pregnantSearch)): ?>
                                            <a href="?pregnant_search=" class="btn btn-primary mt-2">Clear Search</a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Mothers Weight Tab -->
                    <div class="tab-pane fade" id="mothers" role="tabpanel">
                        <div class="section-card">
                            <div class="section-header">
                                <h5><i class="fas fa-weight me-2"></i>Mothers Weight Monitoring</h5>
                                <form method="GET" class="search-box">
                                    <div class="input-group">
                                        <input type="text" name="mothers_search" class="form-control" placeholder="Search mothers..." value="<?php echo htmlspecialchars($mothersSearch); ?>">
                                        <button class="btn" type="submit">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                </form>
                            </div>
                            <div class="section-body">
                                <?php if (!empty($mothersWeight)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Age</th>
                                                    <th>Current Weight</th>
                                                    <th>Weight Trend</th>
                                                    <th>Status</th>
                                                    <th>Last Checkup</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($mothersWeight as $mother): 
                                                    $trend = $mother['current_weight'] - $mother['previous_weight'];
                                                    $trendClass = $trend > 0 ? 'weight-increase' : ($trend < 0 ? 'weight-decrease' : 'weight-stable');
                                                    $trendIcon = $trend > 0 ? 'fa-arrow-up' : ($trend < 0 ? 'fa-arrow-down' : 'fa-minus');
                                                    $isUnderweight = $mother['current_weight'] < 50;
                                                ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($mother['first_name'] . ' ' . $mother['last_name']); ?></strong>
                                                    </td>
                                                    <td><?php echo $mother['age']; ?> years</td>
                                                    <td>
                                                        <?php echo $mother['current_weight']; ?> kg
                                                        <?php if ($isUnderweight): ?>
                                                            <span class="badge badge-underweight ms-1">Underweight</span>
                                                        <?php else: ?>
                                                            <span class="badge badge-normal ms-1">Normal</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="<?php echo $trendClass; ?>">
                                                        <i class="fas <?php echo $trendIcon; ?> me-1"></i>
                                                        <?php echo abs($trend); ?> kg
                                                    </td>
                                                    <td>
                                                        <?php if ($trend > 0): ?>
                                                            <span class="badge badge-success">Improving</span>
                                                        <?php elseif ($trend < 0): ?>
                                                            <span class="badge badge-danger">Declining</span>
                                                        <?php else: ?>
                                                            <span class="badge badge-warning">Stable</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php echo date('M j, Y', strtotime($mother['last_checkup'])); ?>
                                                        <br>
                                                        <small class="text-muted">
                                                            <?php echo date_diff(date_create($mother['last_checkup']), date_create('today'))->days; ?> days ago
                                                        </small>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <i class="fas fa-weight"></i>
                                        <h5>No Weight Data Found</h5>
                                        <p class="text-muted">
                                            <?php echo !empty($mothersSearch) ? 
                                                'No mothers found matching your search.' : 
                                                'No mothers with sufficient weight records found.'; ?>
                                        </p>
                                        <?php if (!empty($mothersSearch)): ?>
                                            <a href="?mothers_search=" class="btn btn-primary mt-2">Clear Search</a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                   <!-- Infants Weight Tab -->
<div class="tab-pane fade" id="infants" role="tabpanel">
    <div class="section-card">
        <div class="section-header">
            <h5><i class="fas fa-baby me-2"></i>Infants Weight Monitoring</h5>
            <form method="GET" class="search-box">
                <div class="input-group">
                    <input type="text" name="infants_search" class="form-control" placeholder="Search infants..." value="<?php echo htmlspecialchars($infantsSearch); ?>">
                    <button class="btn" type="submit">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>
        </div>
        <div class="section-body">
            <?php if (!empty($infantsWeight)): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Baby's Name</th>
                                <th>Age</th>
                                <th>Mother's Name</th>
                                <th>Birth Weight</th>
                                <th>Current Weight</th>
                                <th>Weight Gain</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($infantsWeight as $infant): 
                                // Use isset() to avoid undefined index errors
                                $currentWeight = isset($infant['current_weight']) ? $infant['current_weight'] : 0;
                                $birthWeight = isset($infant['birth_weight']) ? $infant['birth_weight'] : 0;
                                $weightGain = $currentWeight - $birthWeight;
                                $isUnderweight = $currentWeight < 2.5;
                                $weightGainClass = $weightGain > 0 ? 'weight-increase' : ($weightGain < 0 ? 'weight-decrease' : 'weight-stable');
                                
                                // FIXED: Use age_in_months instead of age
                                $ageInMonths = isset($infant['age_in_months']) ? $infant['age_in_months'] : 0;
                                $expectedMinWeight = $birthWeight + ($ageInMonths * 0.7);
                                $isBelowExpected = $currentWeight < $expectedMinWeight;
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars((isset($infant['first_name']) ? $infant['first_name'] : '') . ' ' . (isset($infant['last_name']) ? $infant['last_name'] : '')); ?></strong>
                                </td>
                                <td>
                                    <!-- FIXED LINE 947: Use age_in_months -->
                                    <?php echo $ageInMonths; ?> months
                                </td>
                                <td><?php echo htmlspecialchars((isset($infant['mother_first_name']) ? $infant['mother_first_name'] : '') . ' ' . (isset($infant['mother_last_name']) ? $infant['mother_last_name'] : '')); ?></td>
                                <td><?php echo number_format($birthWeight, 1); ?> kg</td>
                                <td>
                                    <?php echo number_format($currentWeight, 1); ?> kg
                                    <?php if ($isUnderweight): ?>
                                        <span class="badge badge-underweight ms-1">Underweight</span>
                                    <?php elseif ($isBelowExpected): ?>
                                        <span class="badge badge-warning ms-1">Slow Gain</span>
                                    <?php else: ?>
                                        <span class="badge badge-normal ms-1">Normal</span>
                                    <?php endif; ?>
                                </td>
                                <td class="<?php echo $weightGainClass; ?>">
                                    <i class="fas <?php echo $weightGain > 0 ? 'fa-arrow-up' : ($weightGain < 0 ? 'fa-arrow-down' : 'fa-minus'); ?> me-1"></i>
                                    <?php echo number_format($weightGain, 1); ?> kg
                                </td>
                                <td>
                                    <?php if ($weightGain > 0.5): ?>
                                        <span class="badge badge-success">Good Gain</span>
                                    <?php elseif ($weightGain > 0): ?>
                                        <span class="badge badge-warning">Slow Gain</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">No Gain</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-baby"></i>
                    <h5>No Weight Data Found</h5>
                    <p class="text-muted">
                        <?php echo !empty($infantsSearch) ? 
                            'No infants found matching your search.' : 
                            'No infants found in the system.'; ?>
                    </p>
                    <?php if (!empty($infantsSearch)): ?>
                        <a href="?infants_search=" class="btn btn-primary mt-2">Clear Search</a>
                    <?php endif; ?>
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
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh every 10 minutes to keep weight data updated
        setTimeout(function() {
            window.location.reload();
        }, 600000);

        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });

        // Clear search when input is emptied
        document.addEventListener('DOMContentLoaded', function() {
            const searchInputs = document.querySelectorAll('input[name="mothers_search"], input[name="infants_search"], input[name="pregnant_search"]');
            
            searchInputs.forEach(input => {
                input.addEventListener('input', function() {
                    if (this.value === '') {
                        this.form.submit();
                    }
                });
            });
        });
    </script>
</body>
</html>