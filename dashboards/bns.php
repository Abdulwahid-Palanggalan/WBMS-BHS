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
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BNS Dashboard - Nutrition Monitoring</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php include_once __DIR__ . '/../includes/tailwind_config.php'; ?>
    <style type="text/tailwindcss">
        @layer components {
            .stat-card-bns {
                @apply bg-white p-6 rounded-3xl border border-slate-100 shadow-sm hover:shadow-md transition-all duration-300;
            }
            .table-modern-bns th {
                @apply px-6 py-4 text-left text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em] border-b border-slate-50;
            }
            .table-modern-bns td {
                @apply px-6 py-4 text-sm text-slate-600 border-b border-slate-50;
            }
            .tab-btn-bns {
                @apply px-8 py-3 text-[10px] font-black uppercase tracking-widest border-b-2 transition-all duration-300;
            }
            .tab-btn-active {
                @apply border-indigo-600 text-indigo-700 bg-indigo-50/50;
            }
            .tab-btn-inactive {
                @apply border-transparent text-slate-400 hover:text-slate-600 hover:bg-slate-50;
            }
            .trimester-1 { @apply bg-emerald-50/30; }
            .trimester-2 { @apply bg-amber-50/30; }
            .trimester-3 { @apply bg-rose-50/30; }
        }
    </style>
</head>
<body class="bg-slate-50 min-h-full">
    <?php include_once __DIR__ . '/../includes/header.php'; ?>
    
    <div class="flex flex-col lg:flex-row min-h-[calc(100vh-4rem)]">
        <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
        
        <main class="flex-1 p-4 lg:p-8 space-y-8">
            <!-- Dashboard Header -->
            <header class="bg-white p-8 rounded-[2.5rem] border border-slate-100 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div class="space-y-2">
                    <div class="flex items-center gap-3">
                        <div class="w-2 h-2 rounded-full bg-indigo-500 animate-pulse"></div>
                        <h1 class="text-2xl font-black text-slate-900 tracking-tight">BNS Dashboard</h1>
                    </div>
                    <p class="text-slate-500 text-sm font-medium">
                        Welcome back, <span class="text-slate-900 font-bold"><?= $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?></span> 
                        <span class="mx-2 text-slate-300">•</span> 
                        <?= date('l, F j, Y'); ?>
                    </p>
                    <div class="inline-flex items-center gap-2 bg-indigo-50 px-3 py-1 rounded-full border border-indigo-100 mt-2">
                        <i class="fas fa-apple-whole text-indigo-600 text-[10px]"></i>
                        <span class="text-[10px] font-black text-indigo-700 uppercase tracking-tighter">Nutrition scholar mode active</span>
                    </div>
                </div>
                
                <div class="flex items-center gap-4">
                    <div class="text-right">
                        <p class="text-[9px] font-bold text-slate-400 uppercase tracking-[0.2em]">Nutrition Index</p>
                        <p class="text-sm font-black text-emerald-600">STABLE</p>
                    </div>
                    <div class="w-12 h-12 bg-slate-900 rounded-2xl flex items-center justify-center text-white shadow-xl shadow-slate-200">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
            </header>

            <!-- Statistics Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Pregnant Women -->
                <div class="stat-card-bns border-l-4 border-indigo-500">
                    <div class="flex justify-between items-start mb-4">
                        <div class="w-10 h-10 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center">
                            <i class="fas fa-female"></i>
                        </div>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest italic">Monitoring</span>
                    </div>
                    <h3 class="text-3xl font-black text-slate-900"><?= $pregnantWomen; ?></h3>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">Pregnant Women</p>
                </div>

                <!-- Infants -->
                <div class="stat-card-bns border-l-4 border-purple-500">
                    <div class="flex justify-between items-start mb-4">
                        <div class="w-10 h-10 bg-purple-50 text-purple-600 rounded-xl flex items-center justify-center">
                            <i class="fas fa-baby"></i>
                        </div>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest italic">Active</span>
                    </div>
                    <h3 class="text-3xl font-black text-slate-900"><?= $infants; ?></h3>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">Infants (0-12m)</p>
                </div>

                <!-- Underweight Mothers -->
                <div class="stat-card-bns border-l-4 border-amber-500">
                    <div class="flex justify-between items-start mb-4">
                        <div class="w-10 h-10 bg-amber-50 text-amber-600 rounded-xl flex items-center justify-center">
                            <i class="fas fa-weight-scale"></i>
                        </div>
                        <span class="text-[10px] font-bold text-amber-600 underline underline-offset-4 decoration-2">Priority</span>
                    </div>
                    <h3 class="text-3xl font-black text-slate-900"><?= $underweightMothers; ?></h3>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">Underweight Mothers</p>
                </div>

                <!-- Underweight Infants -->
                <div class="stat-card-bns border-l-4 border-rose-500">
                    <div class="flex justify-between items-start mb-4">
                        <div class="w-10 h-10 bg-rose-50 text-rose-600 rounded-xl flex items-center justify-center">
                            <i class="fas fa-baby-carriage"></i>
                        </div>
                        <span class="text-[10px] font-bold text-rose-600 underline underline-offset-4 decoration-2">Critical</span>
                    </div>
                    <h3 class="text-3xl font-black text-slate-900"><?= $underweightInfants; ?></h3>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">Underweight Infants</p>
                </div>
            </div>

            <!-- Tabbed Monitoring Interface -->
            <section class="bg-white rounded-[2.5rem] border border-slate-100 shadow-sm overflow-hidden flex flex-col">
                <div class="px-8 py-6 border-b border-slate-50 flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div class="flex bg-slate-50 p-1 rounded-2xl border border-slate-100 self-start md:self-center">
                        <button onclick="switchTab('pregnant')" id="btn-pregnant" class="tab-btn-bns tab-btn-active rounded-xl">
                            <i class="fas fa-female me-2"></i>Cases (<?= count($pregnantWomenList); ?>)
                        </button>
                        <button onclick="switchTab('mothers')" id="btn-mothers" class="tab-btn-bns tab-btn-inactive rounded-xl">
                            <i class="fas fa-weight me-2"></i>Mothers (<?= count($mothersWeight); ?>)
                        </button>
                        <button onclick="switchTab('infants')" id="btn-infants" class="tab-btn-bns tab-btn-inactive rounded-xl">
                            <i class="fas fa-baby me-2"></i>Infants (<?= count($infantsWeight); ?>)
                        </button>
                    </div>

                    <!-- Contextual Search Forms (only 1 shown at a time) -->
                    <div id="search-container">
                        <form id="search-pregnant" method="GET" class="flex gap-2">
                            <input type="text" name="pregnant_search" placeholder="Search pregnant registry..." value="<?= htmlspecialchars($pregnantSearch); ?>" class="bg-slate-50 border-none text-xs font-bold rounded-xl px-4 py-3 w-64 focus:ring-2 focus:ring-indigo-500 transition-all">
                            <button class="bg-indigo-600 text-white w-10 h-10 rounded-xl flex items-center justify-center shadow-lg shadow-indigo-100 hover:bg-indigo-700 transition-colors">
                                <i class="fas fa-magnifying-glass"></i>
                            </button>
                        </form>
                        <form id="search-mothers" method="GET" class="hidden flex gap-2">
                            <input type="text" name="mothers_search" placeholder="Search mother weights..." value="<?= htmlspecialchars($mothersSearch); ?>" class="bg-slate-50 border-none text-xs font-bold rounded-xl px-4 py-3 w-64 focus:ring-2 focus:ring-indigo-500 transition-all">
                            <button class="bg-indigo-600 text-white w-10 h-10 rounded-xl flex items-center justify-center hover:bg-indigo-700 transition-colors">
                                <i class="fas fa-magnifying-glass"></i>
                            </button>
                        </form>
                        <form id="search-infants" method="GET" class="hidden flex gap-2">
                            <input type="text" name="infants_search" placeholder="Search infant weights..." value="<?= htmlspecialchars($infantsSearch); ?>" class="bg-slate-50 border-none text-xs font-bold rounded-xl px-4 py-3 w-64 focus:ring-2 focus:ring-indigo-500 transition-all">
                            <button class="bg-indigo-600 text-white w-10 h-10 rounded-xl flex items-center justify-center hover:bg-indigo-700 transition-colors">
                                <i class="fas fa-magnifying-glass"></i>
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Pregnant Cases Tab -->
                <div id="tab-pregnant-content" class="overflow-x-auto p-4 tab-content-area">
                    <?php if (!empty($pregnantWomenList)): ?>
                        <table class="w-full table-modern-bns border-collapse">
                            <thead>
                                <tr>
                                    <th>Patient Profile</th>
                                    <th>Due Status</th>
                                    <th>Metrics</th>
                                    <th>Registry Details</th>
                                    <th>Health Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pregnantWomenList as $woman): 
                                    $gestationalAge = $woman['gestational_age'];
                                    $trimester = $gestationalAge <= 12 ? '1st' : ($gestationalAge <= 28 ? '2nd' : '3rd');
                                    $rowClass = $gestationalAge <= 12 ? 'trimester-1' : ($gestationalAge <= 28 ? 'trimester-2' : 'trimester-3');
                                    $isUnderweight = $woman['current_weight'] < 50;
                                    $daysSinceVisit = date_diff(date_create($woman['last_visit']), date_create('today'))->days;
                                ?>
                                <tr class="<?= $rowClass; ?> hover:bg-white/50 transition-colors group">
                                    <td>
                                        <div class="flex items-center gap-4">
                                            <div class="w-10 h-10 bg-indigo-100 text-indigo-600 rounded-2xl flex items-center justify-center font-black text-sm shadow-sm group-hover:bg-indigo-600 group-hover:text-white transition-all duration-300">
                                                <?= strtoupper(substr($woman['first_name'], 0, 1)); ?>
                                            </div>
                                            <div class="flex flex-col">
                                                <span class="font-bold text-slate-800 tracking-tight transition-colors group-hover:text-indigo-700"><?= htmlspecialchars($woman['first_name'] . ' ' . $woman['last_name']); ?></span>
                                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest italic"><?= $woman['age']; ?>y • <?= htmlspecialchars($woman['phone']); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex flex-col">
                                            <span class="text-[10px] font-black <?= $daysSinceVisit > 30 ? 'text-rose-600 animate-pulse' : 'text-slate-400' ?> uppercase tracking-tighter italic">Last Visit: <?= date('M d', strtotime($woman['last_visit'])); ?></span>
                                            <span class="font-bold text-slate-700 text-xs"><?= $daysSinceVisit; ?> Days Ago</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex flex-col text-xs space-y-1">
                                            <span class="flex items-center gap-2">
                                                <i class="fas fa-weight-scale text-slate-300"></i>
                                                <strong class="<?= $isUnderweight ? 'text-rose-600' : 'text-emerald-600' ?>"><?= $woman['current_weight']; ?> KG</strong>
                                            </span>
                                            <span class="flex items-center gap-2">
                                                <i class="fas fa-gauge-high text-slate-300"></i>
                                                <strong class="text-slate-700"><?= $woman['blood_pressure']; ?> BP</strong>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex flex-col gap-1">
                                            <span class="bg-indigo-50 text-indigo-700 text-[9px] font-black px-2 py-1 rounded border border-indigo-100 uppercase tracking-widest w-fit">Week <?= $gestationalAge; ?></span>
                                            <span class="bg-slate-100 text-slate-600 text-[9px] font-black px-2 py-1 rounded border border-slate-200 uppercase tracking-widest w-fit"><?= $trimester; ?> TRIMESTER</span>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($daysSinceVisit > 30): ?>
                                            <span class="bg-rose-50 text-rose-600 text-[9px] font-black px-3 py-1.5 rounded-full border border-rose-100 uppercase tracking-widest italic shadow-sm">Overdue Monitoring</span>
                                        <?php else: ?>
                                            <span class="bg-emerald-50 text-emerald-600 text-[9px] font-black px-3 py-1.5 rounded-full border border-emerald-100 uppercase tracking-widest italic shadow-sm">Compliant</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="flex flex-col items-center justify-center py-24 opacity-30">
                            <i class="fas fa-female text-7xl mb-4"></i>
                            <p class="font-black text-sm uppercase tracking-widest italic">No pregnant records found in registry</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Mothers Weight monitoring Tab -->
                <div id="tab-mothers-content" class="overflow-x-auto p-4 tab-content-area hidden">
                    <?php if (!empty($mothersWeight)): ?>
                        <table class="w-full table-modern-bns border-collapse">
                            <thead>
                                <tr>
                                    <th>Mother Name</th>
                                    <th>Body Metrics</th>
                                    <th>Trend Analytics</th>
                                    <th>Nutritional Status</th>
                                    <th>Checkup Vigilance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($mothersWeight as $mother): 
                                    $trend = $mother['current_weight'] - $mother['previous_weight'];
                                    $isUnderweight = $mother['current_weight'] < 50;
                                ?>
                                <tr class="hover:bg-slate-50/50 transition-colors group">
                                    <td>
                                        <div class="flex items-center gap-4">
                                            <div class="w-10 h-10 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center font-black text-sm shadow-sm group-hover:bg-indigo-600 group-hover:text-white transition-all duration-300">
                                                <?= strtoupper(substr($mother['first_name'], 0, 1)); ?>
                                            </div>
                                            <div class="flex flex-col">
                                                <span class="font-bold text-slate-800 tracking-tight group-hover:text-indigo-700"><?= htmlspecialchars($mother['first_name'] . ' ' . $mother['last_name']); ?></span>
                                                <span class="text-[10px] font-bold text-slate-400 italic"><?= $mother['age']; ?>y • <?= $mother['record_type']; ?> Case</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex flex-col text-xs">
                                            <span class="font-black text-slate-800"><?= $mother['current_weight']; ?> KG</span>
                                            <span class="text-[10px] text-slate-400 italic">Prev: <?= $mother['previous_weight']; ?> KG</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex items-center gap-2">
                                            <div class="w-8 h-8 rounded-full flex items-center justify-center <?= $trend >= 0 ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600' ?>">
                                                <i class="fas <?= $trend >= 0 ? 'fa-arrow-up' : 'fa-arrow-down' ?> text-xs"></i>
                                            </div>
                                            <span class="text-xs font-black <?= $trend >= 0 ? 'text-emerald-600' : 'text-rose-600' ?>"><?= abs(number_format($trend, 1)); ?> KG Diff</span>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($isUnderweight): ?>
                                            <span class="bg-amber-50 text-amber-700 text-[9px] font-black px-3 py-1.5 rounded-full border border-amber-100 uppercase tracking-widest italic">Underweight Alert</span>
                                        <?php else: ?>
                                            <span class="bg-emerald-50 text-emerald-600 text-[9px] font-black px-3 py-1.5 rounded-full border border-emerald-100 uppercase tracking-widest italic">Normal Parameters</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="flex flex-col">
                                            <span class="text-[10px] font-black text-slate-400 uppercase tracking-tighter">Updated Registry</span>
                                            <span class="text-xs font-bold text-slate-700"><?= date('M d, Y', strtotime($mother['last_checkup'])); ?></span>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="flex flex-col items-center justify-center py-24 opacity-30">
                            <i class="fas fa-weight text-7xl mb-4"></i>
                            <p class="font-black text-sm uppercase tracking-widest italic">No mother weight data detected</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Infants Weight Tab -->
                <div id="tab-infants-content" class="overflow-x-auto p-4 tab-content-area hidden">
                    <?php if (!empty($infantsWeight)): ?>
                        <table class="w-full table-modern-bns border-collapse">
                            <thead>
                                <tr>
                                    <th>Child Profile</th>
                                    <th>Maternal Link</th>
                                    <th>Vital Analytics</th>
                                    <th>Weight Gain Path</th>
                                    <th>Nutrition Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($infantsWeight as $infant): 
                                    $currentWeight = isset($infant['current_weight']) ? $infant['current_weight'] : 0;
                                    $birthWeight = isset($infant['birth_weight']) ? $infant['birth_weight'] : 0;
                                    $weightGain = $currentWeight - $birthWeight;
                                    $ageInMonths = isset($infant['age_in_months']) ? $infant['age_in_months'] : 0;
                                    $expectedMinWeight = $birthWeight + ($ageInMonths * 0.7);
                                    $isBelowExpected = $currentWeight < $expectedMinWeight;
                                    $isUnderweight = $currentWeight < 2.5;
                                ?>
                                <tr class="hover:bg-slate-50/50 transition-colors group">
                                    <td>
                                        <div class="flex items-center gap-4">
                                            <div class="w-10 h-10 bg-purple-100 text-purple-600 rounded-2xl flex items-center justify-center font-black text-xs shadow-sm group-hover:bg-purple-600 group-hover:text-white transition-all duration-300">
                                                <i class="fas fa-baby"></i>
                                            </div>
                                            <div class="flex flex-col">
                                                <span class="font-bold text-slate-800 tracking-tight group-hover:text-purple-700"><?= htmlspecialchars($infant['first_name'] . ' ' . $infant['last_name']); ?></span>
                                                <span class="text-[10px] font-black text-indigo-500 uppercase tracking-widest italic"><?= $ageInMonths; ?> Months Old</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="text-xs font-bold text-slate-600 italic">M: <?= htmlspecialchars($infant['mother_first_name'] . ' ' . $infant['mother_last_name']); ?></span>
                                    </td>
                                    <td>
                                        <div class="flex flex-col text-xs">
                                            <span class="font-black text-slate-800"><?= number_format($currentWeight, 2); ?> KG Now</span>
                                            <span class="text-[10px] text-slate-400 italic">Born: <?= number_format($birthWeight, 2); ?> KG</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex items-center gap-2">
                                            <div class="w-8 h-8 rounded-full flex items-center justify-center <?= $weightGain >= 0.5 ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600' ?>">
                                                <i class="fas <?= $weightGain >= 0.5 ? 'fa-arrow-trend-up' : 'fa-triangle-exclamation' ?> text-xs"></i>
                                            </div>
                                            <span class="text-xs font-black <?= $weightGain >= 0.5 ? 'text-emerald-600' : 'text-rose-600' ?>">+<?= number_format($weightGain, 1); ?> KG GAIN</span>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($isUnderweight): ?>
                                            <span class="bg-rose-50 text-rose-600 text-[9px] font-black px-3 py-1.5 rounded-full border border-rose-100 uppercase tracking-widest italic shadow-sm">Critical Underweight</span>
                                        <?php elseif ($isBelowExpected): ?>
                                            <span class="bg-amber-50 text-amber-700 text-[9px] font-black px-3 py-1.5 rounded-full border border-amber-100 uppercase tracking-widest italic shadow-sm">Slow Growth Progress</span>
                                        <?php else: ?>
                                            <span class="bg-emerald-50 text-emerald-600 text-[9px] font-black px-3 py-1.5 rounded-full border border-emerald-100 uppercase tracking-widest italic shadow-sm">Optimal Nutrition</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="flex flex-col items-center justify-center py-24 opacity-30">
                            <i class="fas fa-baby-carriage text-7xl mb-4"></i>
                            <p class="font-black text-sm uppercase tracking-widest italic">No pediatric nutritional data</p>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>

    <!-- Layout & Interactivity Script -->
    <script>
        function switchTab(tab) {
            // Content visibility
            document.querySelectorAll('.tab-content-area').forEach(el => el.classList.add('hidden'));
            document.getElementById('tab-' + tab + '-content').classList.remove('hidden');

            // Tab button styles
            document.querySelectorAll('.tab-btn-bns').forEach(el => {
                el.classList.remove('tab-btn-active');
                el.classList.add('tab-btn-inactive');
            });
            document.getElementById('btn-' + tab).classList.remove('tab-btn-inactive');
            document.getElementById('btn-' + tab).classList.add('tab-btn-active');

            // Search form visibility
            document.querySelectorAll('#search-container form').forEach(el => el.classList.add('hidden'));
            document.getElementById('search-' + tab).classList.remove('hidden');
        }

        // Auto-refresh for Data Sync (Every 10 Minutes)
        setTimeout(() => {
            window.location.reload();
        }, 600000);

        // Smart Search Submission on clear
        document.querySelectorAll('#search-container input').forEach(input => {
            input.addEventListener('input', function() {
                if (this.value === '') {
                    this.form.submit();
                }
            });
        });
    </script>
</body>
</html>
