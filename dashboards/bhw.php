<?php
// dashboard_bhw.php - UPDATED WITH SITIO FILTERING
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isAuthorized(['bhw'])) {
    header("Location: ../login.php");
    exit();
}

redirectIfNotLoggedIn();

global $pdo;

// Get current BHW's assigned sitios
$userId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT assigned_sitios FROM users WHERE id = ?");
$stmt->execute([$userId]);
$bhwData = $stmt->fetch(PDO::FETCH_ASSOC);

$assignedSitios = $bhwData['assigned_sitios'] ?? '';
$bhwSitioArray = $assignedSitios ? explode(',', $assignedSitios) : [];

// Create a mapping of sitio names to full addresses
$sitioToAddress = [
    'Proper 1' => 'Proper 1, Kibenes, Carmen, Cotabato',
    'Proper 2' => 'Proper 2, Kibenes, Carmen, Cotabato',
    'Takpan' => 'Takpan, Carmen, Cotabato',
    'Kupayan' => 'Kupayan, Carmen, Cotabato',
    'Kilaba' => 'Kilaba, Carmen, Cotabato',
    'Baingkungan' => 'Baingkungan, Carmen, Cotabato',
    'Butuan' => 'Butuan, Carmen, Cotabato',
    'Sambayangan' => 'Sambayangan, Carmen, Cotabato',
    'Village' => 'Village, Carmen, Cotabato'
];

// Get full addresses for this BHW
$bhwAddresses = [];
foreach ($bhwSitioArray as $sitio) {
    $sitio = trim($sitio);
    if (isset($sitioToAddress[$sitio])) {
        $bhwAddresses[] = $sitioToAddress[$sitio];
    }
}

// Build the WHERE clause for filtering
$addressFilter = "";
if (!empty($bhwAddresses)) {
    $placeholders = str_repeat('?,', count($bhwAddresses) - 1) . '?';
    $addressFilter = "WHERE m.address IN ($placeholders)";
}

// Get ALL mothers in assigned sitios
$allMothersQuery = "
    SELECT 
        m.id, m.first_name, m.middle_name, m.last_name, 
        m.date_of_birth, m.civil_status, m.phone, m.email, 
        m.address, m.blood_type, m.emergency_contact, m.emergency_phone,
        DATEDIFF(CURDATE(), m.date_of_birth) / 365.25 as age,
        pd.edc, pd.lmp,
        (SELECT COUNT(*) FROM birth_records WHERE mother_id = m.id) as total_children
    FROM mothers m
    LEFT JOIN pregnancy_details pd ON m.id = pd.mother_id 
        AND pd.lmp IS NOT NULL 
        AND DATE_ADD(pd.lmp, INTERVAL 42 WEEK) > CURDATE()
    $addressFilter
    ORDER BY m.first_name, m.last_name
";

$allMothersStmt = $pdo->prepare($allMothersQuery);
if (!empty($bhwAddresses)) {
    foreach ($bhwAddresses as $index => $address) {
        $allMothersStmt->bindValue($index + 1, $address);
    }
}
$allMothersStmt->execute();
$allMothers = $allMothersStmt->fetchAll(PDO::FETCH_ASSOC) ?? [];

// Get ALL babies in assigned sitios
$allBabiesQuery = "
    SELECT 
        br.id, br.first_name, br.last_name, br.gender, 
        br.birth_date, br.birth_weight, br.birth_length,
        br.birth_time, br.delivery_type, br.birth_attendant,
        m.first_name as mother_first_name, m.last_name as mother_last_name,
        m.address as mother_address,
        TIMESTAMPDIFF(YEAR, br.birth_date, CURDATE()) as age_years,
        TIMESTAMPDIFF(MONTH, br.birth_date, CURDATE()) % 12 as age_months,
        CONCAT(
            TIMESTAMPDIFF(YEAR, br.birth_date, CURDATE()), 'y ',
            TIMESTAMPDIFF(MONTH, br.birth_date, CURDATE()) % 12, 'm'
        ) as age_formatted
    FROM birth_records br
    JOIN mothers m ON br.mother_id = m.id
    $addressFilter
    ORDER BY br.birth_date DESC
";

$allBabiesStmt = $pdo->prepare($allBabiesQuery);
if (!empty($bhwAddresses)) {
    foreach ($bhwAddresses as $index => $address) {
        $allBabiesStmt->bindValue($index + 1, $address);
    }
}
$allBabiesStmt->execute();
$allBabies = $allBabiesStmt->fetchAll(PDO::FETCH_ASSOC) ?? [];

// Get PREGNANT women in assigned sitios
$pregnantWomenQuery = "
    SELECT 
        m.id, m.first_name, m.last_name, m.date_of_birth, m.civil_status,
        m.phone, m.email, m.address, m.blood_type,
        pd.edc, pd.lmp, pd.gravida, pd.para, 
        pd.abortions, pd.living_children,
        hp.first_name as husband_first_name, hp.last_name as husband_last_name,
        hp.phone as husband_phone,
        DATEDIFF(CURDATE(), m.date_of_birth) / 365.25 as age,
        DATEDIFF(pd.edc, CURDATE()) as days_until_due,
        ROUND(DATEDIFF(CURDATE(), pd.lmp) / 7, 1) as gestational_weeks,
        (SELECT MAX(visit_date) FROM prenatal_records WHERE mother_id = m.id) as last_visit,
        (SELECT COUNT(*) FROM prenatal_records WHERE mother_id = m.id) as prenatal_visits,
        (SELECT COUNT(*) FROM birth_records WHERE mother_id = m.id) as total_children
    FROM mothers m
    JOIN pregnancy_details pd ON m.id = pd.mother_id
    LEFT JOIN husband_partners hp ON m.id = hp.mother_id
    WHERE pd.lmp IS NOT NULL 
        AND DATE_ADD(pd.lmp, INTERVAL 42 WEEK) > CURDATE()
        " . (!empty($bhwAddresses) ? " AND m.address IN (" . str_repeat('?,', count($bhwAddresses) - 1) . '?)' : "") . "
    ORDER BY pd.edc ASC
";

$pregnantWomenStmt = $pdo->prepare($pregnantWomenQuery);
if (!empty($bhwAddresses)) {
    foreach ($bhwAddresses as $index => $address) {
        $pregnantWomenStmt->bindValue($index + 1, $address);
    }
}
$pregnantWomenStmt->execute();
$pregnantWomen = $pregnantWomenStmt->fetchAll(PDO::FETCH_ASSOC) ?? [];

// Get counts for stats
$totalMothers = count($allMothers);
$totalBabies = count($allBabies);
$totalPregnant = count($pregnantWomen);
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BHW Dashboard - Community Health</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php include_once __DIR__ . '/../includes/tailwind_config.php'; ?>
    <style type="text/tailwindcss">
        @layer components {
            .stat-card-bhw {
                @apply bg-white p-6 rounded-3xl border border-slate-100 shadow-sm hover:shadow-md transition-all duration-300 cursor-pointer;
            }
            .table-modern-bhw th {
                @apply px-6 py-4 text-left text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em] border-b border-slate-50;
            }
            .table-modern-bhw td {
                @apply px-6 py-4 text-sm text-slate-600 border-b border-slate-50;
            }
            .tab-btn {
                @apply px-6 py-3 text-sm font-bold uppercase tracking-widest border-b-2 transition-all duration-300;
            }
            .tab-btn-active {
                @apply border-emerald-600 text-emerald-600 bg-emerald-50/50;
            }
            .tab-btn-inactive {
                @apply border-transparent text-slate-400 hover:text-slate-600 hover:bg-slate-50;
            }
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
                        <div class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div>
                        <h1 class="text-2xl font-black text-slate-900 tracking-tight">BHW Dashboard</h1>
                    </div>
                    <p class="text-slate-500 text-sm font-medium">
                        Welcome, <span class="text-slate-900 font-bold"><?= $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?></span> 
                        <span class="mx-2 text-slate-300">•</span> 
                        <?= date('l, F j, Y'); ?>
                    </p>
                    
                    <!-- Assigned Sitios -->
                    <div class="flex flex-wrap gap-2 mt-4">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest self-center mr-2">Assigned Sitios:</span>
                        <?php if (!empty($bhwSitioArray)): ?>
                            <?php foreach ($bhwSitioArray as $sitio): ?>
                                <span class="bg-emerald-50 text-emerald-700 text-[10px] font-black px-3 py-1 rounded-full border border-emerald-100 uppercase tracking-tighter">
                                    <i class="fas fa-location-dot me-1"></i><?= htmlspecialchars($sitio); ?>
                                </span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="bg-amber-50 text-amber-700 text-[10px] font-black px-3 py-1 rounded-full border border-amber-100 uppercase tracking-widest">
                                <i class="fas fa-triangle-exclamation me-1"></i>No Assignments
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="flex flex-col items-end gap-2">
                    <div class="bg-slate-900 text-white px-6 py-3 rounded-2xl shadow-xl shadow-slate-200 flex items-center gap-4">
                        <div class="text-right">
                            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-[0.2em]">Community Risk</p>
                            <p class="text-sm font-black">LOW STATUS</p>
                        </div>
                        <div class="w-10 h-10 bg-emerald-500 rounded-xl flex items-center justify-center">
                            <i class="fas fa-shield-heart"></i>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Quick Stats Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Registered Mothers -->
                <div class="stat-card-bhw border-l-4 border-sky-500" onclick="switchTab('mothers')">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-1">Registered Mothers</p>
                            <h3 class="text-3xl font-black text-slate-900"><?= $totalMothers; ?></h3>
                        </div>
                        <div class="w-12 h-12 bg-sky-50 text-sky-600 rounded-2xl flex items-center justify-center text-xl shadow-soft">
                            <i class="fas fa-person-breastfeeding"></i>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center justify-between text-[10px] font-bold">
                        <span class="text-sky-600 bg-sky-50 px-2 py-1 rounded-lg uppercase tracking-tight">Active Registry</span>
                        <span class="text-slate-400">Total in Assigned Area</span>
                    </div>
                </div>

                <!-- Registered Babies -->
                <div class="stat-card-bhw border-l-4 border-emerald-500" onclick="switchTab('babies')">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-1">Registered Babies</p>
                            <h3 class="text-3xl font-black text-slate-900"><?= $totalBabies; ?></h3>
                        </div>
                        <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center text-xl shadow-soft">
                            <i class="fas fa-baby"></i>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center justify-between text-[10px] font-bold">
                        <span class="text-emerald-600 bg-emerald-50 px-2 py-1 rounded-lg uppercase tracking-tight">Growth Monitoring</span>
                        <span class="text-slate-400">Total in Assigned Area</span>
                    </div>
                </div>

                <!-- Pregnant Women -->
                <div class="stat-card-bhw border-l-4 border-rose-500" onclick="switchTab('pregnant')">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-1">Pregnant Women</p>
                            <h3 class="text-3xl font-black text-slate-900"><?= $totalPregnant; ?></h3>
                        </div>
                        <div class="w-12 h-12 bg-rose-50 text-rose-600 rounded-2xl flex items-center justify-center text-xl shadow-soft">
                            <i class="fas fa-heart-pulse"></i>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center justify-between text-[10px] font-bold">
                        <span class="text-rose-600 bg-rose-50 px-2 py-1 rounded-lg uppercase tracking-tight">Prenatal Vigilance</span>
                        <span class="text-slate-400">Total in Assigned Area</span>
                    </div>
                </div>
            </div>

            <!-- Main Population Overview -->
            <section class="bg-white rounded-[2.5rem] border border-slate-100 shadow-sm overflow-hidden flex flex-col">
                <div class="px-8 py-6 border-b border-slate-50 flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-bold text-slate-800 tracking-tight">Population Management</h2>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mt-1">Detailed breakdown of residents</p>
                    </div>
                    
                    <div class="flex bg-slate-50 p-1 rounded-2xl border border-slate-100 self-start md:self-center">
                        <button id="tab-mothers" onclick="switchTab('mothers')" class="tab-btn tab-btn-active rounded-xl">
                            <i class="fas fa-person-breastfeeding me-2"></i>Mothers
                        </button>
                        <button id="tab-babies" onclick="switchTab('babies')" class="tab-btn tab-btn-inactive rounded-xl">
                            <i class="fas fa-baby me-2"></i>Babies
                        </button>
                        <button id="tab-pregnant" onclick="switchTab('pregnant')" class="tab-btn tab-btn-inactive rounded-xl">
                            <i class="fas fa-heart-pulse me-2"></i>Pregnant
                        </button>
                    </div>
                </div>

                <!-- Mothers Table -->
                <div id="content-mothers" class="overflow-x-auto p-4 content-section">
                    <?php if (!empty($allMothers)): ?>
                        <table class="w-full table-modern-bhw border-collapse">
                            <thead>
                                <tr>
                                    <th>Mother Name</th>
                                    <th>Address/Sitio</th>
                                    <th>Demographics</th>
                                    <th>Registry Stats</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allMothers as $mother): 
                                    $addressParts = explode(',', $mother['address']);
                                    $sitio = $addressParts[0];
                                    $isPregnant = !empty($mother['lmp']);
                                ?>
                                <tr class="hover:bg-slate-50/50 transition-colors group">
                                    <td>
                                        <div class="flex items-center gap-4">
                                            <div class="w-10 h-10 bg-sky-100 text-sky-600 rounded-2xl flex items-center justify-center font-black text-sm shadow-sm group-hover:bg-sky-600 group-hover:text-white transition-all duration-300">
                                                <?= strtoupper(substr($mother['first_name'], 0, 1)); ?>
                                            </div>
                                            <div class="flex flex-col">
                                                <span class="font-bold text-slate-800 tracking-tight transition-colors group-hover:text-sky-700"><?= htmlspecialchars($mother['first_name'] . ' ' . $mother['last_name']); ?></span>
                                                <span class="text-[10px] font-medium text-slate-400 italic"><?= $mother['phone'] ?: 'No Phone'; ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="bg-slate-100 text-slate-600 text-[10px] font-black px-3 py-1.5 rounded-full uppercase tracking-tighter shadow-sm">
                                            <?= htmlspecialchars($sitio); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="flex flex-col text-xs font-medium text-slate-500">
                                            <span>Age: <strong class="text-slate-800"><?= floor($mother['age']); ?>y</strong></span>
                                            <span>Status: <strong class="text-slate-800"><?= htmlspecialchars($mother['civil_status']); ?></strong></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex items-center gap-2">
                                            <span class="bg-emerald-50 text-emerald-600 text-[10px] font-black px-3 py-1.5 rounded-full border border-emerald-100">
                                                <?= $mother['total_children']; ?> CHILDREN
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($isPregnant): ?>
                                            <span class="bg-rose-50 text-rose-600 text-[9px] font-black px-3 py-1.5 rounded-full uppercase tracking-widest border border-rose-100 italic">Pregnant</span>
                                        <?php else: ?>
                                            <span class="bg-slate-50 text-slate-400 text-[9px] font-black px-3 py-1.5 rounded-full uppercase tracking-widest border border-slate-100">Stable</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="flex flex-col items-center justify-center py-20 opacity-30">
                            <i class="fas fa-person-breastfeeding text-6xl mb-4"></i>
                            <p class="font-black text-sm uppercase tracking-widest">No mother data available in your sitios</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Babies Table -->
                <div id="content-babies" class="overflow-x-auto p-4 content-section hidden">
                    <?php if (!empty($allBabies)): ?>
                        <table class="w-full table-modern-bhw border-collapse">
                            <thead>
                                <tr>
                                    <th>Child Profile</th>
                                    <th>Birth Identity</th>
                                    <th>Vital Growth</th>
                                    <th>Maternal Link</th>
                                    <th>Location</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allBabies as $baby): 
                                    $addressParts = explode(',', $baby['mother_address']);
                                    $sitio = $addressParts[0];
                                ?>
                                <tr class="hover:bg-slate-50/50 transition-colors group">
                                    <td>
                                        <div class="flex items-center gap-4">
                                            <div class="w-10 h-10 <?= $baby['gender'] == 'male' ? 'bg-sky-100 text-sky-600' : 'bg-rose-100 text-rose-600' ?> rounded-2xl flex items-center justify-center text-sm shadow-sm">
                                                <i class="fas <?= $baby['gender'] == 'male' ? 'fa-mars' : 'fa-venus' ?>"></i>
                                            </div>
                                            <div class="flex flex-col">
                                                <span class="font-bold text-slate-800 tracking-tight group-hover:text-emerald-700 transition-colors"><?= htmlspecialchars($baby['first_name'] . ' ' . $baby['last_name']); ?></span>
                                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest"><?= $baby['age_formatted']; ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex flex-col text-xs space-y-0.5">
                                            <span class="font-black text-slate-800"><?= date('M d, Y', strtotime($baby['birth_date'])); ?></span>
                                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-tighter italic">DOB Recorded</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex items-center gap-2">
                                            <span class="bg-amber-50 text-amber-700 text-[10px] font-black px-3 py-1.5 rounded-full border border-amber-100">
                                                <?= $baby['birth_weight'] ?: '0.0'; ?> KG
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="text-xs font-bold text-slate-600 underline decoration-slate-200 decoration-2 underline-offset-4">
                                            <?= htmlspecialchars($baby['mother_first_name'] . ' ' . $baby['mother_last_name']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="bg-emerald-50 text-emerald-600 text-[10px] font-black px-3 py-1.5 rounded-full border border-emerald-100 uppercase tracking-tighter">
                                            <?= htmlspecialchars($sitio); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="flex flex-col items-center justify-center py-20 opacity-30">
                            <i class="fas fa-baby text-6xl mb-4"></i>
                            <p class="font-black text-sm uppercase tracking-widest">No baby records found in your sitios</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Pregnant Women Table -->
                <div id="content-pregnant" class="overflow-x-auto p-4 content-section hidden">
                    <?php if (!empty($pregnantWomen)): ?>
                        <table class="w-full table-modern-bhw border-collapse">
                            <thead>
                                <tr>
                                    <th>Patient Profile</th>
                                    <th>Due Date (EDC)</th>
                                    <th>Gestation Progress</th>
                                    <th>Visits & Parity</th>
                                    <th>Community Support</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pregnantWomen as $woman): 
                                    $addressParts = explode(',', $woman['address']);
                                    $sitio = $addressParts[0];
                                    $progress = min(100, ($woman['gestational_weeks'] / 40) * 100);
                                ?>
                                <tr class="hover:bg-slate-50/50 transition-colors group">
                                    <td>
                                        <div class="flex items-center gap-4">
                                            <div class="w-10 h-10 bg-rose-500 text-white rounded-2xl flex items-center justify-center text-sm font-bold shadow-lg shadow-rose-100">
                                                <i class="fas fa-heart-pulse"></i>
                                            </div>
                                            <div class="flex flex-col">
                                                <span class="font-bold text-slate-800 tracking-tight group-hover:text-rose-700 transition-colors"><?= htmlspecialchars($woman['first_name'] . ' ' . $woman['last_name']); ?></span>
                                                <span class="text-[10px] font-bold text-rose-500 uppercase tracking-widest italic">High Priority Monitoring</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex flex-col">
                                            <span class="font-black text-slate-800 text-sm"><?= date('M d, Y', strtotime($woman['edc'])); ?></span>
                                            <?php if ($woman['days_until_due'] > 0): ?>
                                                <span class="text-[10px] font-black text-emerald-600 uppercase tracking-tighter"><?= $woman['days_until_due']; ?> DAYS REMAINING</span>
                                            <?php else: ?>
                                                <span class="text-[10px] font-black text-rose-600 uppercase tracking-tighter animation-pulse italic">OVERDUE BY <?= abs($woman['days_until_due']); ?> DAYS</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex flex-col gap-2 w-40">
                                            <div class="flex justify-between text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                                                <span>Week <?= number_format($woman['gestational_weeks'], 1); ?></span>
                                                <span><?= round($progress); ?>%</span>
                                            </div>
                                            <div class="h-1.5 bg-slate-100 rounded-full overflow-hidden">
                                                <div class="bg-rose-500 h-full rounded-full shadow-[0_0_8px_rgba(244,63,94,0.5)] transition-all duration-1000" style="width: <?= $progress; ?>%"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex flex-col text-[10px] font-black gap-1">
                                            <span class="bg-sky-50 text-sky-600 px-3 py-1.5 rounded-full border border-sky-100 w-fit">G<?= $woman['gravida']; ?> P<?= $woman['para']; ?> L<?= $woman['living_children']; ?></span>
                                            <span class="bg-purple-50 text-purple-600 px-3 py-1.5 rounded-full border border-purple-100 w-fit"><?= $woman['prenatal_visits']; ?> PRENATAL VISITS</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex flex-col text-xs font-bold text-slate-600 italic">
                                            <span>Mr. <?= htmlspecialchars($woman['husband_first_name'] ?: '---'); ?></span>
                                            <span class="text-[10px] text-slate-400 uppercase tracking-tighter">Emergency Hub: <?= htmlspecialchars($sitio); ?></span>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="flex flex-col items-center justify-center py-20 opacity-30">
                            <i class="fas fa-heart-pulse text-6xl mb-4"></i>
                            <p class="font-black text-sm uppercase tracking-widest">No prenatal cases in your community</p>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>

    <!-- Layout & Interactivity Script -->
    <script>
        function switchTab(tab) {
            // Hide all sections
            document.querySelectorAll('.content-section').forEach(el => el.classList.add('hidden'));
            // Remove active styles from all buttons
            document.querySelectorAll('.tab-btn').forEach(el => {
                el.classList.remove('tab-btn-active');
                el.classList.add('tab-btn-inactive');
            });

            // Show target section
            document.getElementById('content-' + tab).classList.remove('hidden');
            // Add active styles to target button
            document.getElementById('tab-' + tab).classList.remove('tab-btn-inactive');
            document.getElementById('tab-' + tab).classList.add('tab-btn-active');
        }

        // Auto-refresh for Data Sync (Every 5 Minutes)
        setTimeout(() => {
            window.location.reload();
        }, 300000);

        // Responsive Sidebar Toggle Logic is handled in header.php
    </script>
</body>
</html>
