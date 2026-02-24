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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BHW Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .dashboard-header {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border-left: 4px solid #28a745;
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
            cursor: pointer;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .stat-card.mothers { border-left-color: #007bff; }
        .stat-card.babies { border-left-color: #28a745; }
        .stat-card.pregnant { border-left-color: #dc3545; }
        
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
        
        .stat-card.mothers .stat-icon { background: #e3f2fd; color: #007bff; }
        .stat-card.babies .stat-icon { background: #e8f5e8; color: #28a745; }
        .stat-card.pregnant .stat-icon { background: #ffebee; color: #dc3545; }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
            color: #2c3e50;
        }
        
        .stat-label {
            color: #6c757d;
            font-weight: 500;
            font-size: 0.85rem;
        }
        
        @media (max-width: 768px) {
            .stat-card {
                padding: 1rem;
            }
            .stat-number {
                font-size: 1.25rem;
            }
            .stat-label {
                font-size: 0.75rem;
            }
            .stat-icon {
                width: 40px;
                height: 40px;
                font-size: 1rem;
                margin-bottom: 0.5rem;
            }
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
        }
        
        .section-header h5 {
            margin: 0;
            font-weight: 600;
        }
        
        .section-body {
            padding: 1.5rem;
        }
        
        .sitio-badge {
            font-size: 0.75rem;
            padding: 3px 8px;
            border-radius: 10px;
            background: #e3f2fd;
            color: #007bff;
            border: 1px solid #bbdefb;
        }
        
        .pregnant-badge {
            background: #ffebee;
            color: #dc3545;
            border: 1px solid #ffcdd2;
        }
        
        .table-hover tbody tr:hover {
            background-color: #f8f9fa;
        }
        
        .table-responsive {
            max-height: 400px;
            overflow-y: auto;
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
        
        .profile-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #007bff;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 10px;
        }
        
        .tab-content {
            background: white;
            border-radius: 0 0 12px 12px;
            padding: 1.5rem;
        }
        
        .nav-tabs {
            flex-wrap: nowrap;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            border-bottom: 1px solid #dee2e6;
        }
        
        .nav-tabs .nav-link {
            border: none;
            color: #6c757d;
            font-weight: 500;
            padding: 0.75rem 1rem;
            white-space: nowrap;
        }
        
        .nav-tabs .nav-link.active {
            color: #007bff;
            border-bottom: 3px solid #007bff;
            background: transparent;
        }

    </style>
</head>
<body>
    <?php include_once __DIR__ . '/../includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <!-- Dashboard Header with Sitio Info -->
                <div class="dashboard-header">
                    <div class="row align-items-center">
                        <div class="col">
                            <h1 class="h3 mb-2">BHW Dashboard</h1>
                            <p class="mb-0 opacity-75">
                                Welcome, <strong><?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?></strong>
                                • Today: <?php echo date('l, F j, Y'); ?>
                            </p>
                            
                            <!-- Assigned Sitios -->
                            <?php if (!empty($bhwSitioArray)): ?>
                            <div class="mt-2">
                                <small class="text-muted">Assigned Sitios:</small>
                                <?php foreach ($bhwSitioArray as $sitio): ?>
                                    <span class="badge bg-primary me-1"><?php echo htmlspecialchars($sitio); ?></span>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                            <div class="mt-2">
                                <span class="badge bg-warning">No sitio assignment</span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="row mb-4">
                    <div class="col-md-4 mb-3">
                        <div class="stat-card mothers" onclick="showTab('mothers')">
                            <div class="stat-icon">
                                <i class="fas fa-user-friends"></i>
                            </div>
                            <div class="stat-number"><?php echo $totalMothers; ?></div>
                            <div class="stat-label">Registered Mothers</div>
                            <small class="text-muted">In your assigned sitios</small>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="stat-card babies" onclick="showTab('babies')">
                            <div class="stat-icon">
                                <i class="fas fa-baby"></i>
                            </div>
                            <div class="stat-number"><?php echo $totalBabies; ?></div>
                            <div class="stat-label">Registered Babies</div>
                            <small class="text-muted">In your assigned sitios</small>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="stat-card pregnant" onclick="showTab('pregnant')">
                            <div class="stat-icon">
                                <i class="fas fa-heartbeat"></i>
                            </div>
                            <div class="stat-number"><?php echo $totalPregnant; ?></div>
                            <div class="stat-label">Pregnant Women</div>
                            <small class="text-muted">In your assigned sitios</small>
                        </div>
                    </div>
                </div>

                <!-- Main Content Tabs -->
                <div class="section-card">
                    <div class="section-header">
                        <h5><i class="fas fa-chart-bar me-2"></i>Population Overview</h5>
                    </div>
                    
                    <!-- Tabs Navigation -->
                    <nav>
                        <div class="nav nav-tabs" id="nav-tab" role="tablist">
                            <button class="nav-link active" id="nav-mothers-tab" data-bs-toggle="tab" 
                                    data-bs-target="#nav-mothers" type="button" role="tab">
                                <i class="fas fa-user-friends me-1"></i>
                                Mothers (<?php echo $totalMothers; ?>)
                            </button>
                            <button class="nav-link" id="nav-babies-tab" data-bs-toggle="tab" 
                                    data-bs-target="#nav-babies" type="button" role="tab">
                                <i class="fas fa-baby me-1"></i>
                                Babies (<?php echo $totalBabies; ?>)
                            </button>
                            <button class="nav-link" id="nav-pregnant-tab" data-bs-toggle="tab" 
                                    data-bs-target="#nav-pregnant" type="button" role="tab">
                                <i class="fas fa-heartbeat me-1"></i>
                                Pregnant (<?php echo $totalPregnant; ?>)
                            </button>
                        </div>
                    </nav>
                    
                    <!-- Tabs Content -->
                    <div class="tab-content" id="nav-tabContent">
                        
                        <!-- Mothers Tab -->
                        <div class="tab-pane fade show active" id="nav-mothers" role="tabpanel">
                            <?php if (!empty($allMothers)): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Mother</th>
                                                <th>Sitio</th>
                                                <th>Age</th>
                                                <th>Civil Status</th>
                                                <th>Contact</th>
                                                <th>Children</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($allMothers as $mother): 
                                                // Extract sitio from address
                                                $addressParts = explode(',', $mother['address']);
                                                $sitio = $addressParts[0];
                                                $isPregnant = !empty($mother['lmp']);
                                            ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="profile-avatar">
                                                            <?php echo strtoupper(substr($mother['first_name'], 0, 1)); ?>
                                                        </div>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($mother['first_name'] . ' ' . $mother['last_name']); ?></strong>
                                                            <?php if ($mother['middle_name']): ?>
                                                                <br><small class="text-muted"><?php echo htmlspecialchars($mother['middle_name']); ?></small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="sitio-badge"><?php echo htmlspecialchars($sitio); ?></span>
                                                </td>
                                                <td><?php echo floor($mother['age']); ?> years</td>
                                                <td><?php echo htmlspecialchars($mother['civil_status']); ?></td>
                                                <td>
                                                    <?php if ($mother['phone']): ?>
                                                        <i class="fas fa-phone text-primary me-1"></i><?php echo htmlspecialchars($mother['phone']); ?><br>
                                                    <?php endif; ?>
                                                    <?php if ($mother['emergency_contact']): ?>
                                                        <small class="text-muted">Emergency: <?php echo htmlspecialchars($mother['emergency_contact']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary"><?php echo $mother['total_children']; ?> children</span>
                                                </td>
                                                <td>
                                                    <?php if ($isPregnant): ?>
                                                        <span class="badge bg-danger">Pregnant</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success">Not Pregnant</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-user-friends"></i>
                                    <h5>No Mothers Found</h5>
                                    <p class="text-muted">No mothers registered in your assigned sitios.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Babies Tab -->
                        <div class="tab-pane fade" id="nav-babies" role="tabpanel">
                            <?php if (!empty($allBabies)): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Baby</th>
                                                <th>Sitio</th>
                                                <th>Birth Date</th>
                                                <th>Age</th>
                                                <th>Gender</th>
                                                <th>Weight</th>
                                                <th>Mother</th>
                                                <th>Delivery</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($allBabies as $baby): 
                                                // Extract sitio from address
                                                $addressParts = explode(',', $baby['mother_address']);
                                                $sitio = $addressParts[0];
                                            ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($baby['first_name'] . ' ' . $baby['last_name']); ?></strong>
                                                <?php if ($baby['gender'] == 'male'): ?>
                                                    <i class="fas fa-mars text-primary ms-1"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-venus text-danger ms-1"></i>
                                                <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="sitio-badge"><?php echo htmlspecialchars($sitio); ?></span>
                                                </td>
                                                <td>
                                                    <?php if ($baby['birth_date'] && $baby['birth_date'] != '0000-00-00'): ?>
                                                        <?php echo date('M j, Y', strtotime($baby['birth_date'])); ?>
                                                    <?php else: ?>
                                                        N/A
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo $baby['age_formatted']; ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $baby['gender'] == 'male' ? 'primary' : 'danger'; ?>">
                                                        <?php echo ucfirst($baby['gender']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($baby['birth_weight']): ?>
                                                        <?php echo $baby['birth_weight']; ?> kg
                                                    <?php else: ?>
                                                        N/A
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($baby['mother_first_name'] . ' ' . $baby['mother_last_name']); ?>
                                                </td>
                                                <td>
                                                    <?php if ($baby['delivery_type']): ?>
                                                        <small class="text-muted"><?php echo ucfirst($baby['delivery_type']); ?></small>
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
                                    <h5>No Babies Found</h5>
                                    <p class="text-muted">No babies registered in your assigned sitios.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Pregnant Women Tab -->
                        <div class="tab-pane fade" id="nav-pregnant" role="tabpanel">
                            <?php if (!empty($pregnantWomen)): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Mother</th>
                                                <th>Sitio</th>
                                                <th>Age</th>
                                                <th>Contact</th>
                                                <th>Due Date</th>
                                                <th>Progress</th>
                                                <th>Prenatal Visits</th>
                                                <th>G/P</th>
                                                <th>Husband</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($pregnantWomen as $woman): 
                                                // Extract sitio from address
                                                $addressParts = explode(',', $woman['address']);
                                                $sitio = $addressParts[0];
                                                
                                                // Calculate progress percentage
                                                $progress = min(100, ($woman['gestational_weeks'] / 40) * 100);
                                            ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="profile-avatar bg-danger">
                                                            <i class="fas fa-heartbeat"></i>
                                                        </div>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($woman['first_name'] . ' ' . $woman['last_name']); ?></strong>
                                                            <br><small class="text-muted">G: <?php echo $woman['gravida']; ?> P: <?php echo $woman['para']; ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="sitio-badge"><?php echo htmlspecialchars($sitio); ?></span>
                                                </td>
                                                <td><?php echo floor($woman['age']); ?> years</td>
                                                <td>
                                                    <i class="fas fa-phone text-primary me-1"></i><?php echo htmlspecialchars($woman['phone']); ?>
                                                </td>
                                                <td>
                                                    <?php if ($woman['edc']): ?>
                                                        <strong><?php echo date('M j, Y', strtotime($woman['edc'])); ?></strong>
                                                        <br>
                                                        <?php if ($woman['days_until_due'] > 0): ?>
                                                            <small class="text-success"><?php echo $woman['days_until_due']; ?> days left</small>
                                                        <?php else: ?>
                                                            <small class="text-danger">Overdue by <?php echo abs($woman['days_until_due']); ?> days</small>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="me-2" style="width: 60px;">
                                                            <div class="progress" style="height: 6px;">
                                                                <div class="progress-bar bg-danger" role="progressbar" 
                                                                     style="width: <?php echo $progress; ?>%"></div>
                                                            </div>
                                                        </div>
                                                        <small>Week <?php echo number_format($woman['gestational_weeks'], 1); ?></small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info"><?php echo $woman['prenatal_visits']; ?> visits</span>
                                                    <?php if ($woman['last_visit']): ?>
                                                        <br><small>Last: <?php echo date('M j', strtotime($woman['last_visit'])); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small>G<?php echo $woman['gravida']; ?>P<?php echo $woman['para']; ?></small>
                                                    <?php if ($woman['abortions'] > 0): ?>
                                                        <br><small class="text-danger">A: <?php echo $woman['abortions']; ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($woman['husband_first_name']): ?>
                                                        <?php echo htmlspecialchars($woman['husband_first_name'] . ' ' . $woman['husband_last_name']); ?>
                                                        <?php if ($woman['husband_phone']): ?>
                                                            <br><small class="text-muted"><?php echo htmlspecialchars($woman['husband_phone']); ?></small>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">N/A</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-heartbeat"></i>
                                    <h5>No Pregnant Women Found</h5>
                                    <p class="text-muted">No pregnant women in your assigned sitios.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh every 5 minutes
        setTimeout(function() {
            window.location.reload();
        }, 300000);
        
        // Function to show specific tab when clicking on stat cards
        function showTab(tabName) {
            var tabButton = document.getElementById('nav-' + tabName + '-tab');
            var bsTab = new bootstrap.Tab(tabButton);
            bsTab.show();
        }
        
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    </script>
</body>
</html>