<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

if (!isAuthorized(['admin', 'midwife', 'bhw', 'bns'])) {
    header("Location: login.php");
    exit();
}

redirectIfNotLoggedIn();

global $pdo;

$userRole = $_SESSION['role'];
$currentMonth = date('F Y');
$reportMonth = $_GET['report_month'] ?? date('Y-m');
$reportType = $_GET['report_type'] ?? 'default';

// Set default report type based on role
$defaultReports = [
    'admin' => 'activity',
    'midwife' => 'prenatal_postnatal_combined',
    'bhw' => 'monthly',
    'bns' => 'monthly'
];

if ($reportType === 'default') {
    $reportType = $defaultReports[$userRole];
}

$timePeriod = $_GET['time_period'] ?? 'this-month';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';

// Calculate date range based on time period
if ($timePeriod === 'this-month') {
    $startDate = date('Y-m-01');
    $endDate = date('Y-m-t');
} elseif ($timePeriod === 'last-month') {
    $startDate = date('Y-m-01', strtotime('-1 month'));
    $endDate = date('Y-m-t', strtotime('-1 month'));
} elseif ($timePeriod === 'this-quarter') {
    $currentMonth = date('n');
    $currentQuarter = ceil($currentMonth / 3);
    $startMonth = (($currentQuarter - 1) * 3) + 1;
    $startDate = date('Y-' . sprintf('%02d', $startMonth) . '-01');
    $endDate = date('Y-m-t', strtotime(date('Y-' . sprintf('%02d', $startMonth + 2) . '-01')));
} elseif ($timePeriod === 'this-year') {
    $startDate = date('Y-01-01');
    $endDate = date('Y-12-31');
}

// Get report data based on type and role
$reportData = [];
$reportTitle = '';
$summaryStats = [];
$maternalData = [];
$childCareData = [];
$tbData = [];
$acknowledgementData = [];
$mothersList = [];
$allPrenatalRecords = [];
$recordsByMother = [];
$birthRecords = [];

// Helper function to get BHW's assigned addresses
function getBHWAddresses() {
    global $pdo;
    
    if ($_SESSION['role'] !== 'bhw') {
        return [];
    }
    
    $userId = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT assigned_sitios FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $bhwData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $assignedSitios = $bhwData['assigned_sitios'] ?? '';
    if (empty($assignedSitios)) {
        return [];
    }
    
    $bhwSitioArray = explode(',', $assignedSitios);
    
    // Map sitio names to full addresses
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
    
    $bhwAddresses = [];
    foreach ($bhwSitioArray as $sitio) {
        $sitio = trim($sitio);
        if (isset($sitioToAddress[$sitio])) {
            $bhwAddresses[] = $sitioToAddress[$sitio];
        }
    }
    
    return $bhwAddresses;
}

// Helper function to get BHW sitios for display
function getBHWSitiosForDisplay() {
    $bhwAddresses = getBHWAddresses();
    if (empty($bhwAddresses)) {
        return '';
    }
    
    $sitioNames = [];
    foreach ($bhwAddresses as $address) {
        $parts = explode(',', $address);
        $sitioNames[] = trim($parts[0]);
    }
    
    return implode(', ', $sitioNames);
}

// Role-based report generation
switch ($userRole) {
    case 'admin':
        if ($reportType === 'activity') {
            $reportTitle = 'System Activity Logs Report';
            $sql = "SELECT a.*, u.username, u.first_name, u.last_name, u.role 
                    FROM system_activities a 
                    LEFT JOIN users u ON a.user_id = u.id 
                    WHERE a.timestamp BETWEEN ? AND ? 
                    ORDER BY a.timestamp DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get activity summary
            $sql_summary = "SELECT 
                            COUNT(*) as total_activities,
                            COUNT(DISTINCT user_id) as unique_users,
                            MIN(timestamp) as first_activity,
                            MAX(timestamp) as last_activity
                        FROM system_activities 
                        WHERE timestamp BETWEEN ? AND ?";
            $stmt_summary = $pdo->prepare($sql_summary);
            $stmt_summary->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
            $summaryStats = $stmt_summary->fetch(PDO::FETCH_ASSOC);
        }
        break;

    case 'midwife':
        if ($reportType === 'prenatal_postnatal_combined') {
            $reportTitle = 'Prenatal and Postnatal Care Record';
            
            // Get combined records
            $sql = "SELECT 
                        pr.visit_date,
                        'prenatal' as record_type,
                        u.first_name, 
                        u.last_name, 
                        u.phone,
                        m.id as mother_id,
                        pr.blood_pressure,
                        pr.weight,
                        pr.gestational_age,
                        pr.hb_level,
                        NULL as mother_weight,
                        NULL as temperature,
                        NULL as baby_first_name,
                        NULL as baby_last_name, 
                        NULL as birth_date,
                        NULL as gender,
                        pr.complaints,
                        pr.findings,
                        pr.treatment,
                        pr.iron_supplement,
                        pr.folic_acid,
                        pr.calcium,
                        pr.other_meds,
                        pr.next_visit_date,
                        DATE_FORMAT(pr.visit_date, '%Y-%m-%d') as visit_date_formatted
                    FROM prenatal_records pr 
                    JOIN mothers m ON pr.mother_id = m.id 
                    JOIN users u ON m.user_id = u.id 
                    WHERE pr.visit_date BETWEEN ? AND ?
                    
                    UNION ALL
                    
                    SELECT 
                        pnr.visit_date,
                        'postnatal' as record_type,
                        u.first_name, 
                        u.last_name, 
                        u.phone,
                        m.id as mother_id,
                        pnr.blood_pressure,
                        pnr.weight as mother_weight,
                        NULL as gestational_age,
                        NULL as hb_level,
                        pnr.weight as mother_weight,
                        pnr.temperature,
                        br.first_name as baby_first_name,
                        br.last_name as baby_last_name, 
                        br.birth_date,
                        br.gender,
                        pnr.complaints,
                        NULL as findings,  
                        pnr.treatment,
                        NULL as iron_supplement,
                        NULL as folic_acid,
                        NULL as calcium,
                        NULL as other_meds,
                        pnr.next_visit_date,
                        DATE_FORMAT(pnr.visit_date, '%Y-%m-%d') as visit_date_formatted
                    FROM postnatal_records pnr 
                    JOIN birth_records br ON pnr.baby_id = br.id 
                    JOIN mothers m ON br.mother_id = m.id 
                    JOIN users u ON m.user_id = u.id 
                    WHERE pnr.visit_date BETWEEN ? AND ?
                    
                    ORDER BY last_name, first_name, visit_date DESC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$startDate, $endDate, $startDate, $endDate]);
            $combinedRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } elseif ($reportType === 'birth_registration') {
            $reportTitle = 'Birth Registration Report';
            
            // Get birth records
            $sql = "SELECT 
                        br.*,
                        u.first_name as mother_first_name,
                        u.last_name as mother_last_name,
                        u.phone as mother_phone,
                        m.address,
                        TIMESTAMPDIFF(YEAR, m.date_of_birth, CURDATE()) as mother_age,
                        br.first_name as baby_first_name,
                        br.last_name as baby_last_name,
                        br.birth_date,
                        br.birth_weight,
                        br.gender,
                        br.birth_place,
                        br.birth_attendant,
                        br.registered_at
                    FROM birth_records br
                    JOIN mothers m ON br.mother_id = m.id 
                    JOIN users u ON m.user_id = u.id 
                    WHERE br.birth_date BETWEEN ? AND ?
                    ORDER BY br.birth_date DESC, u.last_name, u.first_name";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$startDate, $endDate]);
            $birthRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        break;

    case 'bhw':
        if ($reportType === 'monthly') {
            $reportTitle = 'BHW Monthly Report';
            // Get data for BHW monthly report
            $prevMonth = date('Y-m', strtotime('-1 month', strtotime($reportMonth . '-01')));
            
            // Get maternal care data
            $maternalData = getBHWMaternalData($reportMonth, $prevMonth);
            $childCareData = getBHWChildCareData($reportMonth, $prevMonth);
            $tbData = getBHWTBData($reportMonth, $prevMonth);
        }
        break;

    case 'bns':
        if ($reportType === 'monthly') {
            $reportTitle = 'BNS Monthly Report';
            // Get data for BNS acknowledgement receipt
            $acknowledgementData = getBNSAcknowledgementData();
        }
        break;
}

// BHW Data Functions - UPDATED WITH SITIO FILTERING
function getBHWMaternalData($currentMonth, $prevMonth) {
    global $pdo;
    
    // Get BHW's assigned addresses
    $bhwAddresses = getBHWAddresses();
    
    // Build WHERE clause for addresses
    if (!empty($bhwAddresses)) {
        $placeholders = str_repeat('?,', count($bhwAddresses) - 1) . '?';
        $addressFilter = "AND m.address IN ($placeholders)";
    } else {
        $addressFilter = "";
    }
    
    // New referred - CURRENT MONTH
    $sql = "SELECT COUNT(DISTINCT m.id) as count 
            FROM mothers m 
            JOIN prenatal_records pr ON m.id = pr.mother_id 
            WHERE DATE_FORMAT(pr.visit_date, '%Y-%m') = ?
            $addressFilter";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(1, $currentMonth);
    if (!empty($bhwAddresses)) {
        foreach ($bhwAddresses as $index => $address) {
            $stmt->bindValue($index + 2, $address);
        }
    }
    $stmt->execute();
    $newReferredCurrent = $stmt->fetchColumn() ?: 0;
    
    // New referred - PREVIOUS MONTH
    $stmt->bindValue(1, $prevMonth);
    $stmt->execute();
    $newReferredPrev = $stmt->fetchColumn() ?: 0;
    
    $newAssistedCurrent = $newReferredCurrent;
    $newAssistedPrev = $newReferredPrev;
    
    // Tracked - CURRENT MONTH
    $sql = "SELECT COUNT(DISTINCT pr.mother_id) as count 
            FROM prenatal_records pr 
            JOIN mothers m ON pr.mother_id = m.id 
            WHERE DATE_FORMAT(pr.visit_date, '%Y-%m') = ? 
            AND pr.visit_number > 1
            $addressFilter";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(1, $currentMonth);
    if (!empty($bhwAddresses)) {
        foreach ($bhwAddresses as $index => $address) {
            $stmt->bindValue($index + 2, $address);
        }
    }
    $stmt->execute();
    $trackedCurrent = $stmt->fetchColumn() ?: 0;
    
    // Tracked - PREVIOUS MONTH
    $stmt->bindValue(1, $prevMonth);
    $stmt->execute();
    $trackedPrev = $stmt->fetchColumn() ?: 0;
    
    // Deliveries - CURRENT MONTH
    $sql = "SELECT COUNT(*) as count 
            FROM birth_records br 
            JOIN mothers m ON br.mother_id = m.id 
            WHERE DATE_FORMAT(br.birth_date, '%Y-%m') = ?
            $addressFilter";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(1, $currentMonth);
    if (!empty($bhwAddresses)) {
        foreach ($bhwAddresses as $index => $address) {
            $stmt->bindValue($index + 2, $address);
        }
    }
    $stmt->execute();
    $deliveriesCurrent = $stmt->fetchColumn() ?: 0;
    
    // Deliveries - PREVIOUS MONTH
    $stmt->bindValue(1, $prevMonth);
    $stmt->execute();
    $deliveriesPrev = $stmt->fetchColumn() ?: 0;
    
    return [
        'new_referred' => ['prev' => $newReferredPrev, 'current' => $newReferredCurrent],
        'new_assisted' => ['prev' => $newAssistedPrev, 'current' => $newAssistedCurrent],
        'tracked' => ['prev' => $trackedPrev, 'current' => $trackedCurrent],
        'deliveries' => ['prev' => $deliveriesPrev, 'current' => $deliveriesCurrent]
    ];
}

function getBHWChildCareData($currentMonth, $prevMonth) {
    global $pdo;
    
    // Get BHW's assigned addresses
    $bhwAddresses = getBHWAddresses();
    
    // Build WHERE clause for addresses
    if (!empty($bhwAddresses)) {
        $placeholders = str_repeat('?,', count($bhwAddresses) - 1) . '?';
        $addressFilter = "AND m.address IN ($placeholders)";
    } else {
        $addressFilter = "";
    }
    
    // Immunization referred - CURRENT MONTH
    $sql = "SELECT COUNT(DISTINCT pnr.id) as count 
            FROM postnatal_records pnr 
            JOIN birth_records br ON pnr.baby_id = br.id 
            JOIN mothers m ON br.mother_id = m.id 
            WHERE DATE_FORMAT(pnr.visit_date, '%Y-%m') = ? 
            AND TIMESTAMPDIFF(MONTH, br.birth_date, pnr.visit_date) < 12
            $addressFilter";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(1, $currentMonth);
    if (!empty($bhwAddresses)) {
        foreach ($bhwAddresses as $index => $address) {
            $stmt->bindValue($index + 2, $address);
        }
    }
    $stmt->execute();
    $immunReferredCurrent = $stmt->fetchColumn() ?: 0;
    
    // Immunization referred - PREVIOUS MONTH
    $stmt->bindValue(1, $prevMonth);
    $stmt->execute();
    $immunReferredPrev = $stmt->fetchColumn() ?: 0;
    
    $immunAssistedCurrent = $immunReferredCurrent;
    $immunAssistedPrev = $immunReferredPrev;
    
    // Defaulter tracked - CURRENT MONTH
    $sql = "SELECT COUNT(DISTINCT br.id) as count 
            FROM birth_records br 
            JOIN mothers m ON br.mother_id = m.id 
            LEFT JOIN postnatal_records pnr ON br.id = pnr.baby_id 
                AND DATE_FORMAT(pnr.visit_date, '%Y-%m') = ?
            WHERE TIMESTAMPDIFF(MONTH, br.birth_date, CURDATE()) < 12 
            AND pnr.id IS NULL
            $addressFilter";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(1, $currentMonth);
    if (!empty($bhwAddresses)) {
        foreach ($bhwAddresses as $index => $address) {
            $stmt->bindValue($index + 2, $address);
        }
    }
    $stmt->execute();
    $defaulterCurrent = $stmt->fetchColumn() ?: 0;
    
    // Defaulter tracked - PREVIOUS MONTH
    $stmt->bindValue(1, $prevMonth);
    $stmt->execute();
    $defaulterPrev = $stmt->fetchColumn() ?: 0;
    
    return [
        'immun_referred' => ['prev' => $immunReferredPrev, 'current' => $immunReferredCurrent],
        'immun_assisted' => ['prev' => $immunAssistedPrev, 'current' => $immunAssistedCurrent],
        'defaulter_tracked' => ['prev' => $defaulterPrev, 'current' => $defaulterCurrent]
    ];
}

function getBHWTBData($currentMonth, $prevMonth) {
    // This function remains the same as it doesn't query mothers table
    return [
        'tb_referred' => ['prev' => 0, 'current' => 0],
        'sputum_followup' => ['prev' => 0, 'current' => 0]
    ];
}

function getBNSAcknowledgementData() {
    global $pdo;
    
    $sql = "SELECT 
                u.first_name, 
                u.last_name, 
                m.address,
                br.first_name as child_first_name,
                br.last_name as child_last_name,
                TIMESTAMPDIFF(YEAR, br.birth_date, CURDATE()) as child_age,
                br.gender,
                'No' as is_pregnant,
                'No' as is_lactating,  
                (SELECT COUNT(*) FROM birth_records br2 
                 WHERE br2.mother_id = m.id 
                 AND TIMESTAMPDIFF(YEAR, br2.birth_date, CURDATE()) < 5) as children_under_5
            FROM mothers m
            JOIN users u ON m.user_id = u.id
            JOIN birth_records br ON m.id = br.mother_id 
            WHERE TIMESTAMPDIFF(YEAR, br.birth_date, CURDATE()) < 5
            ORDER BY u.last_name, u.first_name";
    
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle PDF download
if (isset($_GET['download_pdf'])) {
    try {
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        require_once 'includes/dompdf/autoload.inc.php';
        
        $options = new \Dompdf\Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Arial');
        $options->set('defaultEncoding', 'UTF-8');
        $options->set('isPhpEnabled', true);
        $options->set('isJavascriptEnabled', false);
        
        $dompdf = new \Dompdf\Dompdf($options);
        
        $html = generateDOMPDFHTML();
        
        if (empty($html)) {
            throw new Exception('Empty HTML content generated');
        }
        
        $dompdf->loadHtml($html);
        
        // LANDSCAPE FOR MIDWIFE REPORTS AND BNS
        if ($userRole === 'midwife' || ($userRole === 'bns' && $reportType === 'monthly')) {
            $dompdf->setPaper('A4', 'landscape');
        } else {
            $dompdf->setPaper('A4', 'portrait');
        }
        
        $dompdf->render();
        
        $filename = str_replace(' ', '_', $reportTitle) . '_' . date('Y-m-d') . '.pdf';
        $dompdf->stream($filename, [
            "Attachment" => true
        ]);
        exit;
        
    } catch (Exception $e) {
        error_log('PDF Generation Error: ' . $e->getMessage());
        die('Error generating PDF. Please try again or contact administrator.');
    }
}

function generateDOMPDFHTML() {
    global $userRole, $reportType, $reportTitle, $maternalData, $childCareData, $tbData, $acknowledgementData, $reportMonth, $reportData, $summaryStats, $startDate, $endDate, $mothersList, $allPrenatalRecords, $recordsByMother, $combinedRecords, $birthRecords;
    
    // Define logo paths
    $dohLogo = 'data:image/png;base64,' . base64_encode(file_get_contents('images/doh-logo.png'));
    $brgyLogo = 'data:image/png;base64,' . base64_encode(file_get_contents('images/brgy-logo.png'));
    
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title><?php echo $reportTitle; ?></title>
        <style>
            body { 
                font-family: Arial, sans-serif; 
                font-size: 10pt; 
                line-height: 1.3;
                margin: 10px;
                color: #333;
            }
            
            /* Report Header with Logos - USING TABLE FOR BETTER DOMPDF SUPPORT */
            
            .summary-section {
                background: #f8f9fa;
                padding: 10px;
                margin: 15px 0;
                border-left: 4px solid #3498db;
            }
            .summary-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 10px;
                margin: 10px 0;
            }
            .summary-item {
                text-align: center;
                padding: 8px;
                background: white;
                border-radius: 4px;
                border: 1px solid #ddd;
            }
            .summary-value {
                font-size: 14pt;
                font-weight: bold;
                color: #2c3e50;
            }
            .summary-label {
                font-size: 8pt;
                color: #666;
                margin-top: 2px;
            }
            table { 
                width: 100%; 
                border-collapse: collapse; 
                margin: 15px 0;
                font-size: 8pt;
            }
            th, td { 
                border: 1px solid #000; 
                padding: 6px; 
                text-align: center;
                vertical-align: top;
            }
            th { 
                background-color: #f0f0f0; 
                font-weight: bold;
            }
            .section-title { 
                background-color: #2c3e50; 
                color: white;
                padding: 8px 12px; 
                font-weight: bold; 
                margin: 20px 0 10px 0;
                border-radius: 4px;
                font-size: 10pt;
            }
            .text-left { text-align: left; }
            .text-center { text-align: center; }
            .text-right { text-align: right; }
            .note { 
                font-style: italic; 
                font-size: 7pt; 
                margin-top: 10px;
                color: #666;
            }
            .mother-section {
                margin-bottom: 20px;
                border: 1px solid #ddd;
                border-radius: 5px;
                padding: 10px;
            }
            .mother-header {
                background-color: #e9ecef;
                padding: 8px 12px;
                margin: -10px -10px 10px -10px;
                border-radius: 5px 5px 0 0;
                font-weight: bold;
            }
            .prenatal-row {
                background-color: #f0f8ff;
            }
            .postnatal-row {
                background-color: #fff0f5;
            }
            
            /* BNS Specific Styles */
            .bns-table {
                font-size: 7pt;
            }
            .bns-table th, .bns-table td {
                padding: 3px 4px;
            }
            .signature-line {
                width: 200px;
                margin: 5px auto;
                border-bottom: 1px solid #000;
            }
            
            /* BHW Specific Styles */
            .bhw-official-header {
                text-align: center;
                margin-bottom: 20px;
            }
            .bhw-official-header h5 {
                margin: 3px 0;
                font-weight: normal;
            }
            .bhw-official-header h2 {
                margin: 10px 0;
                color: #2c3e50;
            }
            
            /* BHW Sitio Info */
            .bhw-sitio-info {
                background: #e8f4fd;
                padding: 8px;
                border-left: 3px solid #3498db;
                margin-bottom: 15px;
                font-size: 9pt;
                border-radius: 4px;
            }
        </style>
    </head>
    <body>
        
       <!-- CLEAN HEADER WITH LOGOS – NO BORDERS -->
<table width="100%" style="margin-bottom: 15px; border-collapse: collapse;">
    <tr style="border: none;">
        
        <!-- LEFT LOGO -->
        <td style="width: 80px; text-align: left; vertical-align: middle; border: none;">
            <img src="<?php echo $dohLogo; ?>" style="width:150px; height:auto;" alt="DOH Logo">
        </td>

        <!-- CENTER CONTENT -->
        <td style="text-align: center; vertical-align: middle; padding: 0 10px; border: none;">
            
            <?php if ($userRole === 'admin' && $reportType === 'activity'): ?>
                <h1 style="margin: 5px 0; font-size:16pt; color:#2c3e50; font-weight:bold;">SYSTEM ACTIVITY LOGS REPORT</h1>
            <?php elseif ($userRole === 'midwife' && $reportType === 'prenatal_postnatal_combined'): ?>
                <h1 style="margin: 5px 0; font-size:16pt; color:#2c3e50; font-weight:bold;">PRENATAL AND POSTNATAL CARE RECORD</h1>
            <?php elseif ($userRole === 'midwife' && $reportType === 'birth_registration'): ?>
                <h1 style="margin: 5px 0; font-size:16pt; color:#2c3e50; font-weight:bold;">BIRTH REGISTRATION REPORT</h1>
            <?php elseif ($userRole === 'bhw' && $reportType === 'monthly'): ?>
                <h1 style="margin: 5px 0; font-size:16pt; color:#2c3e50; font-weight:bold;">BARANGAY HEALTH WORKER MONTHLY REPORT</h1>
            <?php elseif ($userRole === 'bns' && $reportType === 'monthly'): ?>
                <h1 style="margin: 5px 0; font-size:16pt; color:#2c3e50; font-weight:bold;">BARANGAY NUTRITION SCHOLAR MONTHLY REPORT</h1>
            <?php else: ?>
                <h1 style="margin: 5px 0; font-size:16pt; color:#2c3e50; font-weight:bold;"><?php echo $reportTitle; ?></h1>
            <?php endif; ?>

            <p style="margin: 2px 0; font-size:9pt; color:#666;">BARANGAY HEALTH STATION - KIBENES</p>
            <p style="margin: 2px 0; font-size:9pt; color:#666;">KIBENES, CARMEN, NORTH COTABATO</p>

            <div style="margin: 4px 0; font-size:9pt; font-weight:bold; color:#2c3e50;">
                <?php if (($userRole === 'bhw' && $reportType === 'monthly') || ($userRole === 'bns' && $reportType === 'monthly')): ?>
                    Month: <?php echo date('F Y', strtotime($reportMonth . '-01')); ?>
                <?php else: ?>
                    Period: <?php echo date('M j, Y', strtotime($startDate)); ?> to <?php echo date('M j, Y', strtotime($endDate)); ?>
                <?php endif; ?>
            </div>

        </td>

        <!-- RIGHT LOGO -->
        <td style="width: 80px; text-align: right; vertical-align: middle; border: none;">
            <img src="<?php echo $brgyLogo; ?>" style="width:100px; height:auto;" alt="Barangay Logo">
        </td>

    </tr>
</table>

        <?php if ($userRole === 'admin' && $reportType === 'activity'): ?>
            <!-- Admin Activity Logs Report -->
            <?php if (!empty($summaryStats)): ?>
            <div class="summary-section">
                <h3 style="margin: 0 0 10px 0; color: #2c3e50;">Activity Summary</h3>
                <div class="summary-grid">
                    <div class="summary-item">
                        <div class="summary-value"><?php echo $summaryStats['total_activities']; ?></div>
                        <div class="summary-label">Total Activities</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-value"><?php echo $summaryStats['unique_users']; ?></div>
                        <div class="summary-label">Unique Users</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-value"><?php echo date('M j', strtotime($summaryStats['first_activity'])); ?></div>
                        <div class="summary-label">First Activity</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-value"><?php echo date('M j', strtotime($summaryStats['last_activity'])); ?></div>
                        <div class="summary-label">Last Activity</div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="section-title">Detailed Activity Logs</div>
            <?php if (!empty($reportData)): ?>
            <table>
                <thead>
                    <tr>
                        <th style="width: 15%">Date & Time</th>
                        <th style="width: 20%">User</th>
                        <th style="width: 10%">Role</th>
                        <th style="width: 45%">Activity Description</th>
                        <th style="width: 10%">IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reportData as $row): ?>
                    <tr>
                        <td><?php echo date('M j, Y g:i A', strtotime($row['timestamp'])); ?></td>
                        <td class="text-left">
                            <?php if ($row['username']): ?>
                                <?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>
                                <br><small>(@<?php echo htmlspecialchars($row['username']); ?>)</small>
                            <?php else: ?>
                                <span>System</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="role-badge"><?php echo ucfirst($row['role'] ?? 'System'); ?></span>
                        </td>
                        <td class="text-left"><?php echo htmlspecialchars($row['activity']); ?></td>
                        <td><?php echo htmlspecialchars($row['ip_address'] ?? 'N/A'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p style="text-align: center; color: #666; font-style: italic;">No activity records found for the selected period.</p>
            <?php endif; ?>

        <!-- Prenatal and Postnatal Care Record - COMBINED SINGLE TABLE -->
        <?php elseif ($userRole === 'midwife' && $reportType === 'prenatal_postnatal_combined'): ?>

            <!-- Single Combined Table -->
            <table>
                <thead>
                    <tr>
                        <th width="12%">MOTHER NAME</th>
                        <th width="8%">DATE</th>
                        <th width="8%">TYPE</th>
                        <th width="8%">BP</th>
                        <th width="6%">WT</th>
                        <th width="8%">AOG/TEMP</th>
                        <th width="6%">HB</th>
                        <th width="15%">BABY INFO</th>
                        <th width="20%">FINDINGS/TREATMENT</th>
                        <th width="9%">NEXT VISIT</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($combinedRecords)): ?>
                        <?php foreach ($combinedRecords as $record): ?>
                        <tr class="<?php echo $record['record_type'] === 'prenatal' ? 'prenatal-row' : 'postnatal-row'; ?>">
                            <td class="text-left">
                                <strong><?php echo htmlspecialchars($record['last_name'] . ', ' . $record['first_name']); ?></strong>
                                <br><?php echo htmlspecialchars($record['phone']); ?>
                            </td>
                            <td><?php echo date('m/d/Y', strtotime($record['visit_date'])); ?></td>
                            <td>
                                <?php echo $record['record_type'] === 'prenatal' ? 'Prenatal' : 'Postnatal'; ?>
                            </td>
                            <td><?php echo $record['blood_pressure'] ?? 'N/A'; ?></td>
                            <td>
                                <?php if ($record['record_type'] === 'prenatal'): ?>
                                    <?php echo $record['weight'] ?? 'N/A'; ?>
                                <?php else: ?>
                                    <?php echo $record['mother_weight'] ?? 'N/A'; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($record['record_type'] === 'prenatal'): ?>
                                    <?php echo $record['gestational_age'] ?? 'N/A'; ?>
                                <?php else: ?>
                                    <?php echo $record['temperature'] ?? 'N/A'; ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $record['hb_level'] ?? 'N/A'; ?></td>
                            <td class="text-left">
                                <?php if ($record['record_type'] === 'postnatal' && !empty($record['baby_first_name'])): ?>
                                    <strong>Baby:</strong> <?php echo htmlspecialchars($record['baby_first_name'] . ' ' . $record['baby_last_name']); ?><br>
                                    <strong>DOB:</strong> <?php echo !empty($record['birth_date']) ? date('m/d/Y', strtotime($record['birth_date'])) : 'N/A'; ?><br>
                                    <strong>Gender:</strong> <?php echo $record['gender'] ?? 'N/A'; ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td class="text-left">
                                <?php
                                $findings = [];
                                if (!empty($record['complaints'])) $findings[] = "C: " . $record['complaints'];
                                if (!empty($record['findings'])) $findings[] = "F: " . $record['findings'];
                                if (!empty($record['treatment'])) $findings[] = "T: " . $record['treatment'];
                                
                                echo implode(' ', $findings) ?: 'None';
                                ?>
                            </td>
                            <td><?php echo !empty($record['next_visit_date']) ? date('m/d/Y', strtotime($record['next_visit_date'])) : 'N/A'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" class="text-center text-muted">
                                No prenatal or postnatal records found for the selected period.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- SIGNATURE SECTION -->
            <div style="margin-top: 40px; text-align: right; padding-right: 100px;">
                <div style="display: inline-block; text-align: center;">
                    <p class="mb-0">Prepared by:</p>
                    <div style="margin-top: 20px;">
                        <strong><?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?></strong>
                        <div style="width: 200px; border-bottom: 1px solid #000; margin: 5px auto 0 auto;"></div>
                        <small>Signature Over Printed Name</small>
                    </div>
                </div>
            </div>

        <!-- Birth Registration Report -->
        <?php elseif ($userRole === 'midwife' && $reportType === 'birth_registration'): ?>

            <!-- Birth Records Table -->
            <table>
                <thead>
                    <tr>
                        <th width="8%">BIRTH DATE</th>
                        <th width="15%">MOTHER NAME</th>
                        <th width="12%">BABY NAME</th>
                        <th width="8%">GENDER</th>
                        <th width="8%">BIRTH WEIGHT</th>
                        <th width="12%">PLACE OF BIRTH</th>
                        <th width="15%">ATTENDED BY</th>
                        <th width="12%">ADDRESS</th>
                        <th width="10%">REGISTRATION DATE</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($birthRecords)): ?>
                        <?php foreach ($birthRecords as $record): ?>
                        <tr>
                            <td><?php echo date('m/d/Y', strtotime($record['birth_date'])); ?></td>
                            <td class="text-left">
                                <strong><?php echo htmlspecialchars($record['mother_last_name'] . ', ' . $record['mother_first_name']); ?></strong>
                                <br><small><?php echo htmlspecialchars($record['mother_phone']); ?></small>
                            </td>
                            <td class="text-left">
                                <strong><?php echo htmlspecialchars($record['baby_first_name'] . ' ' . $record['baby_last_name']); ?></strong>
                            </td>
                            <td><?php echo ucfirst($record['gender']); ?></td>
                            <td><?php echo $record['birth_weight']; ?> kg</td>
                            <td><?php echo htmlspecialchars($record['birth_place']); ?></td>
                            <td><?php echo htmlspecialchars($record['birth_attendant']); ?></td>
                            <td class="text-left"><?php echo htmlspecialchars($record['address']); ?></td>
                            <td><?php echo !empty($record['registered_at']) ? date('m/d/Y', strtotime($record['registered_at'])) : 'N/A'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted">
                                No birth records found for the selected period.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- SIGNATURE SECTION -->
            <div style="margin-top: 40px; text-align: right; padding-right: 100px;">
                <div style="display: inline-block; text-align: center;">
                    <p class="mb-0">Prepared by:</p>
                    <div style="margin-top: 20px;">
                        <strong><?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?></strong>
                        <div style="width: 200px; border-bottom: 1px solid #000; margin: 5px auto 0 auto;"></div>
                        <small>Signature Over Printed Name</small>
                    </div>
                </div>
            </div>

        <?php elseif ($userRole === 'bhw' && $reportType === 'monthly'): ?>
            <!-- BHW Monthly Report PDF -->
            
            <!-- BHW Sitio Information -->
            <div class="bhw-sitio-info">
                <strong>BHW:</strong> <?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?>
                <?php 
                $bhwSitios = getBHWSitiosForDisplay();
                if (!empty($bhwSitios)): 
                ?>
                    • <strong>Assigned Sitios:</strong> <?php echo $bhwSitios; ?>
                <?php endif; ?>
            </div>

            <!-- Maternal Care Section -->
            <div class="section-title">Maternal Care</div>
            <table>
                <thead>
                    <tr>
                        <th style="width: 60%">Indicator</th>
                        <th style="width: 13%">Previous Month</th>
                        <th style="width: 13%">Current Month</th>
                        <th style="width: 14%">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="text-left">1. Number of newly Pregnant Women referred to BHS for prenatal:</td>
                        <td><?php echo $maternalData['new_referred']['prev']; ?></td>
                        <td><?php echo $maternalData['new_referred']['current']; ?></td>
                        <td><?php echo $maternalData['new_referred']['prev'] + $maternalData['new_referred']['current']; ?></td>
                    </tr>
                    <tr>
                        <td class="text-left">2. Number of newly Pregnant Women assisted for prenatal:</td>
                        <td><?php echo $maternalData['new_assisted']['prev']; ?></td>
                        <td><?php echo $maternalData['new_assisted']['current']; ?></td>
                        <td><?php echo $maternalData['new_assisted']['prev'] + $maternalData['new_assisted']['current']; ?></td>
                    </tr>
                    <tr>
                        <td class="text-left">3. Number of pregnant women tracked (follow-up visits):</td>
                        <td><?php echo $maternalData['tracked']['prev']; ?></td>
                        <td><?php echo $maternalData['tracked']['current']; ?></td>
                        <td><?php echo $maternalData['tracked']['prev'] + $maternalData['tracked']['current']; ?></td>
                    </tr>
                    <tr>
                        <td class="text-left">4. Number of deliveries (Home and Facility):</td>
                        <td><?php echo $maternalData['deliveries']['prev']; ?></td>
                        <td><?php echo $maternalData['deliveries']['current']; ?></td>
                        <td><?php echo $maternalData['deliveries']['prev'] + $maternalData['deliveries']['current']; ?></td>
                    </tr>
                </tbody>
            </table>

            <!-- Child Care Section -->
            <div class="section-title">Child Care</div>
            <table>
                <thead>
                    <tr>
                        <th style="width: 60%">Indicator</th>
                        <th style="width: 13%">Previous Month</th>
                        <th style="width: 13%">Current Month</th>
                        <th style="width: 14%">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="text-left">1. Number of newly under 1 year old referred to BHS for immunization:</td>
                        <td><?php echo $childCareData['immun_referred']['prev']; ?></td>
                        <td><?php echo $childCareData['immun_referred']['current']; ?></td>
                        <td><?php echo $childCareData['immun_referred']['prev'] + $childCareData['immun_referred']['current']; ?></td>
                    </tr>
                    <tr>
                        <td class="text-left">2. Number of newly under 1 year old assisted immunization:</td>
                        <td><?php echo $childCareData['immun_assisted']['prev']; ?></td>
                        <td><?php echo $childCareData['immun_assisted']['current']; ?></td>
                        <td><?php echo $childCareData['immun_assisted']['prev'] + $childCareData['immun_assisted']['current']; ?></td>
                    </tr>
                    <tr>
                        <td class="text-left">3. Number of NIP defaulter tracked:</td>
                        <td><?php echo $childCareData['defaulter_tracked']['prev']; ?></td>
                        <td><?php echo $childCareData['defaulter_tracked']['current']; ?></td>
                        <td><?php echo $childCareData['defaulter_tracked']['prev'] + $childCareData['defaulter_tracked']['current']; ?></td>
                    </tr>
                </tbody>
            </table>

            <!-- Tuberculosis Program Section -->
            <div class="section-title">Tuberculosis Program</div>
            <table>
                <thead>
                    <tr>
                        <th style="width: 60%">Indicator</th>
                        <th style="width: 13%">Previous Month</th>
                        <th style="width: 13%">Current Month</th>
                        <th style="width: 14%">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="text-left">1. Number of TB presumptive cases referred to RHU:</td>
                        <td></td>
                        <td></td>
                        <td></td>
                    </tr>
                    <tr>
                        <td class="text-left">2. Number of sputum follows-up conducted:</td>
                        <td></td>
                        <td></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>

            <div class="note">
                Note: Sources of data should be based from your household visits not from TCL.
            </div>

            <!-- Signatures -->
            <div style="margin-top: 15px;">
                <table style="width: 100%; border-collapse: collapse; font-size: 10pt; border: none;">
                    <tr>
                        <!-- Prepared -->
                        <td style="width: 50%; padding: 5px 10px; border: none;">
                            <strong>Prepared by:</strong><br><br>
                            <div style="text-align: center;">
                                <span style="font-weight: bold;">
                                    <?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?>
                                </span>
                                <div style="width: 85%; margin: 1px auto; border-bottom: 1px solid #000;"></div>
                                <div style="font-size: 8pt; margin-top: 1px;">
                                    Signature Over Printed Name of BHW
                                </div>
                            </div>
                        </td>

                        <!-- Reviewed -->
                        <td style="width: 50%; padding: 5px 10px; border: none;">
                            <strong>Reviewed by:</strong><br><br>
                            <div style="text-align: center;">
                                <span style="font-weight: bold;">&nbsp;</span>
                                <div style="width: 85%; margin: 1px auto; border-bottom: 1px solid #000;"></div>
                                <div style="font-size: 8pt; margin-top: 1px;">
                                    Signature Over Printed Name of Midwife/Nurse
                                </div>
                            </div>
                        </td>
                    </tr>

                    <!-- Approved -->
                    <tr>
                        <td colspan="2" style="padding: 10px; text-align: center; border: none;">
                            <strong>Approved by:</strong><br><br>
                            <div style="text-align: center;">
                                <span style="font-weight: bold;">Ronald K. Akmad</span>
                                <div style="width: 35%; margin: 1px auto; border-bottom: 1px solid #000;"></div>
                                <div style="font-size: 8pt; margin-top: 1px;">
                                    Signature Over Printed Name of Punong Barangay
                                </div>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>

       <?php elseif ($userRole === 'bns' && $reportType === 'monthly'): ?>
            <!-- BNS Monthly Report PDF - SLIGHTLY SMALLER -->
            <div class="bhw-official-header">
            </div>

            <!-- Compact Acknowledgement Receipt Table -->
            <table class="bns-table">
                <thead>
                    <tr>
                        <th rowspan="2" style="width: 4%">NO.</th>
                        <th rowspan="2" style="width: 14%">NAME OF PARENTS/GUARDIAN</th>
                        <th rowspan="2" style="width: 9%">PUROK/BARANGAY</th>
                        <th colspan="3" style="width: 22%">MALNOURISHED CHILDREN</th>
                        <th rowspan="2" style="width: 7%">Check (/) if<br>PREGNANT</th>
                        <th rowspan="2" style="width: 7%">Check (/) if<br>LACTATING</th>
                        <th rowspan="2" style="width: 10%"># of Children<br>below 5</th>
                        <th rowspan="2" style="width: 10%">Signature</th>
                    </tr>
                    <tr>
                        <th style="width: 9%">NAME</th>
                        <th style="width: 6%">AGE</th>
                        <th style="width: 7%">GENDER<br>(M/F)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $counter = 1; ?>
                    <?php foreach ($acknowledgementData as $row): ?>
                    <tr>
                        <td><?php echo $counter; ?></td>
                        <td class="text-left"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                        <td class="text-left"><?php echo htmlspecialchars($row['address']); ?></td>
                        <td class="text-left"><?php echo htmlspecialchars($row['child_first_name'] . ' ' . $row['child_last_name']); ?></td>
                        <td><?php echo $row['child_age']; ?></td>
                        <td><?php echo $row['gender'] === 'male' ? 'M' : 'F'; ?></td>
                        <td></td>
                        <!-- <?php echo $row['is_pregnant'] ? '✓' : ''; ?> -->
                        <td></td>
                        <td><?php echo $row['children_under_5']; ?></td>
                        <td style="height: 18px;"></td>
                    </tr>
                    <?php $counter++; ?>
                    <?php endforeach; ?>
                    
                    <!-- Empty rows for additional entries -->
                    <?php for ($i = $counter; $i <= 10; $i++): ?>
                    <tr>
                        <td><?php echo $i; ?></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td style="height: 18px;"></td>
                    </tr>
                    <?php endfor; ?>
                </tbody>
            </table>

            <!-- SIGNATURE SECTIONS - SAME FORMAT -->
            <table style="width: 100%; border-collapse: collapse; font-size: 9pt; border: none; margin-top: 20px;">
                <tr>
                    <!-- Prepared by -->
                    <td style="width: 50%; padding: 5px 10px; border: none;">
                        <strong>Prepared by:</strong><br><br>
                        <div class="text-center">
                            <span style="font-weight: bold;">
                                <?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?>
                            </span>
                            <div class="signature-line"></div>
                            <div style="font-size: 8pt; margin-top: 1px;">
                                Signature Over Printed Name of BNS
                            </div>
                        </div>
                    </td>

                    <!-- Reviewed by -->
                    <td style="width: 50%; padding: 5px 10px; border: none;">
                        <strong>Reviewed by:</strong><br><br>
                        <div class="text-center">
                            <span style="font-weight: bold;">&nbsp;</span>
                            <div class="signature-line"></div>
                            <div style="font-size: 8pt; margin-top: 1px;">
                                Signature Over Printed Name of Midwife/Nurse
                            </div>
                        </div>
                    </td>
                </tr>
                
                <!-- Approved by -->
                <tr>
                    <td colspan="2" style="text-align: center; padding-top: 12px;">
                        <strong>Approved by:</strong><br><br>
                        <div class="text-center">
                            <span style="font-weight: bold;">Ronald K. Akmad</span>
                            <div style="width: 140px; margin: 1px auto; border-bottom: 1px solid #000;"></div>
                            <div style="font-size: 8pt; margin-top: 1px;">
                                Signature Over Printed Name of Punong Barangay
                            </div>
                        </div>
                    </td>
                </tr>
            </table>

        <?php else: ?>
            <!-- Default Report for other roles -->
            <?php if (!empty($reportData)): ?>
            <div class="section-title">Detailed Report Data</div>
            <table>
                <thead>
                    <tr>
                        <th>Data</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reportData as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars(json_encode($row)); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p style="text-align: center; color: #666; font-style: italic;">No data found for the selected report criteria.</p>
            <?php endif; ?>
        <?php endif; ?>

    </body>
    </html>
    <?php
    return ob_get_clean();
}
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - WBMS-BHS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include_once 'includes/tailwind_config.php'; ?>
    <style>
        @media print {
            body * { visibility: hidden !important; }
            #print-area, #print-area * { visibility: visible !important; }
            #print-area {
                position: absolute;
                top: 0;
                left: 0;
                width: 100% !important;
                margin: 0 !important;
                padding: 10px !important;
                background: white !important;
            }
        }
    </style>
</head>
<body class="bg-slate-50 min-h-full font-inter">
    <?php include_once 'includes/header.php'; ?>
    
    <div class="flex flex-col lg:flex-row min-h-[calc(100vh-4rem)]">
        <?php include_once 'includes/sidebar.php'; ?>
        
        <main class="flex-1 p-4 lg:p-8 space-y-8 no-print">
            <header class="bg-white p-8 rounded-[2.5rem] border border-slate-100 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div class="space-y-2">
                    <h1 class="text-3xl font-black text-slate-900 tracking-tight">Reports & Analytics</h1>
                    <p class="text-slate-500 text-sm font-medium">Generate health monitoring reports for <?= ucfirst($userRole) ?></p>
                </div>
                <div class="flex items-center gap-3">
                    <a href="?download_pdf=1&report_type=<?= $reportType ?>&report_month=<?= $reportMonth ?>&time_period=<?= $timePeriod ?>&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>" 
                       class="bg-rose-600 text-white px-6 py-3 rounded-2xl font-black text-xs uppercase tracking-widest shadow-lg shadow-rose-100 hover:bg-rose-700 transition-all flex items-center gap-3">
                        <i class="fas fa-file-pdf"></i>
                        Export PDF
                    </a>
                    <button onclick="optimizePrint()" class="bg-health-600 text-white px-6 py-3 rounded-2xl font-black text-xs uppercase tracking-widest shadow-lg shadow-health-100 hover:bg-health-700 transition-all flex items-center gap-3">
                        <i class="fas fa-print"></i>
                        Print
                    </button>
                </div>
            </header>

            <!-- Report Filters -->
            <section class="bg-white p-8 rounded-[2.5rem] border border-slate-100 shadow-sm">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Report Type</label>
                        <select name="report_type" onchange="this.form.submit()" class="w-full bg-slate-50 border-none rounded-2xl py-3 px-4 text-sm font-bold focus:ring-2 focus:ring-health-600 transition-all">
                            <?php if ($userRole === 'admin'): ?>
                                <option value="activity" <?= $reportType === 'activity' ? 'selected' : '' ?>>Activity Logs</option>
                            <?php elseif ($userRole === 'midwife'): ?>
                                <option value="prenatal_postnatal_combined" <?= $reportType === 'prenatal_postnatal_combined' ? 'selected' : '' ?>>Maternal Care Combined</option>
                                <option value="birth_registration" <?= $reportType === 'birth_registration' ? 'selected' : '' ?>>Birth Registrations</option>
                            <?php elseif ($userRole === 'bhw'): ?>
                                <option value="monthly" <?= $reportType === 'monthly' ? 'selected' : '' ?>>Monthly Community Report</option>
                            <?php elseif ($userRole === 'bns'): ?>
                                <option value="monthly" <?= $reportType === 'monthly' ? 'selected' : '' ?>>Monthly Nutrition Report</option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Time Period</label>
                        <select name="time_period" id="timePeriodSelector" onchange="this.form.submit()" class="w-full bg-slate-50 border-none rounded-2xl py-3 px-4 text-sm font-bold focus:ring-2 focus:ring-health-600 transition-all">
                            <option value="this-month" <?= $timePeriod === 'this-month' ? 'selected' : '' ?>>This Month</option>
                            <option value="last-month" <?= $timePeriod === 'last-month' ? 'selected' : '' ?>>Last Month</option>
                            <option value="this-quarter" <?= $timePeriod === 'this-quarter' ? 'selected' : '' ?>>This Quarter</option>
                            <option value="this-year" <?= $timePeriod === 'this-year' ? 'selected' : '' ?>>This Year</option>
                            <option value="custom" <?= $timePeriod === 'custom' ? 'selected' : '' ?>>Custom Range</option>
                        </select>
                    </div>

                    <div class="space-y-2" id="startDateCol" style="<?= $timePeriod !== 'custom' ? 'display: none;' : '' ?>">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Start Date</label>
                        <input type="date" name="start_date" value="<?= $startDate ?>" class="w-full bg-slate-50 border-none rounded-2xl py-3 px-4 text-sm font-bold focus:ring-2 focus:ring-health-600 transition-all">
                    </div>

                    <div class="space-y-2" id="endDateCol" style="<?= $timePeriod !== 'custom' ? 'display: none;' : '' ?>">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">End Date</label>
                        <div class="flex gap-2">
                            <input type="date" name="end_date" value="<?= $endDate ?>" class="w-full bg-slate-50 border-none rounded-2xl py-3 px-4 text-sm font-bold focus:ring-2 focus:ring-health-600 transition-all">
                            <button type="submit" class="bg-health-600 text-white p-3 rounded-2xl hover:bg-health-700 transition-all">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </section>

            <div class="bg-white p-8 rounded-[3rem] border border-slate-100 shadow-soft overflow-hidden">
                <div class="flex items-center gap-4 mb-8">
                    <h2 class="text-xl font-black text-slate-900 uppercase tracking-tight"><?= $reportTitle ?></h2>
                    <div class="flex-1 h-px bg-slate-100"></div>
                </div>
                                        
                                    <!-- BNS Reports -->
                                    <?php elseif ($userRole === 'bns'): ?>
                                        <option value="monthly" <?php echo $reportType === 'monthly' ? 'selected' : ''; ?>>Monthly BNS Report</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            
                            <!-- BHW and BNS Month Selector -->
                            <?php if (($userRole === 'bhw' && $reportType === 'monthly') || ($userRole === 'bns' && $reportType === 'monthly')): ?>
                            <div class="col-md-3">
                                <label for="report_month" class="form-label">Report Month</label>
                                <input type="month" class="form-control" id="report_month" name="report_month" 
                                       value="<?php echo $reportMonth; ?>" max="<?php echo date('Y-m'); ?>">
                            </div>
                            <?php else: ?>
                            <!-- Time Period for other reports -->
                            <div class="col-md-3">
                                <label for="time_period" class="form-label">Time Period</label>
                                <select class="form-select" id="time_period" name="time_period">
                                    <option value="this-month" <?php echo $timePeriod === 'this-month' ? 'selected' : ''; ?>>This Month</option>
                                    <option value="last-month" <?php echo $timePeriod === 'last-month' ? 'selected' : ''; ?>>Last Month</option>
                                    <option value="this-quarter" <?php echo $timePeriod === 'this-quarter' ? 'selected' : ''; ?>>This Quarter</option>
                                    <option value="this-year" <?php echo $timePeriod === 'this-year' ? 'selected' : ''; ?>>This Year</option>
                                    <option value="custom" <?php echo $timePeriod === 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                                </select>
                            </div>
                            <div class="col-md-2" id="start_date_container" style="<?php echo $timePeriod !== 'custom' ? 'display: none;' : ''; ?>">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $startDate; ?>">
                            </div>
                            <div class="col-md-2" id="end_date_container" style="<?php echo $timePeriod !== 'custom' ? 'display: none;' : ''; ?>">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $endDate; ?>">
                            </div>
                            <?php endif; ?>
                            
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search"></i> Generate
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Action Buttons -->
            <?php if (!empty($reportData) || !empty($summaryStats) || ($userRole === 'bhw' && $reportType === 'monthly') || ($userRole === 'bns' && $reportType === 'monthly') || ($userRole === 'midwife' && $reportType === 'prenatal_postnatal_combined') || ($userRole === 'midwife' && $reportType === 'birth_registration' && !empty($birthRecords))): ?>
            <div class="action-buttons no-print">
                <div class="d-flex justify-content-center gap-3">
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['download_pdf' => 1])); ?>" class="btn btn-danger btn-lg">
                        <i class="fas fa-file-pdf me-2"></i> Download PDF
                    </a>
                    <button type="button" class="btn btn-outline-primary btn-lg" onclick="window.print()">
                        <i class="fas fa-print me-2"></i> Print Report
                    </button>
                </div>
            </div>
            <?php endif; ?>

            <!-- Admin Reports -->
            <?php if ($userRole === 'admin'): ?>
                <?php if ($reportType === 'activity'): ?>
                    <!-- Admin Activity Logs Report -->
                    <div class="card" id="print-area">
                        <!-- PRINT HEADER WITH LOGOS -->
                        <div class="print-header d-none d-print-block">
                            <div class="print-logo-left">
                                <img src="images/doh-logo.png" alt="DOH Logo">
                            </div>
                            <div class="print-header-center">
                                <h4>SYSTEM ACTIVITY LOGS REPORT</h4>
                                <p>BARANGAY HEALTH STATION - KIBENES</p>
                                <p>KIBENES, CARMEN, NORTH COTABATO</p>
                                <div class="report-period">
                                    Period: <?php echo date('M j, Y', strtotime($startDate)); ?> to <?php echo date('M j, Y', strtotime($endDate)); ?>
                                </div>
                            </div>
                            <div class="print-logo-right">
                                <img src="images/brgy-logo.png" alt="Barangay Logo">
                            </div>
                        </div>
                        
                        <!-- SCREEN HEADER -->
                        <div class="report-header no-print">
                            <div class="logo-container">
                                <img src="images/doh-logo.png" alt="DOH Logo">
                            </div>
                            <div class="header-center">
                                <h4>SYSTEM ACTIVITY LOGS REPORT</h4>
                                <p>BARANGAY HEALTH STATION - KIBENES</p>
                                <p>KIBENES, CARMEN, NORTH COTABATO</p>
                                <div class="report-period">
                                    Period: <?php echo date('M j, Y', strtotime($startDate)); ?> to <?php echo date('M j, Y', strtotime($endDate)); ?>
                                </div>
                            </div>
                            <div class="logo-container text-end">
                                <img src="images/brgy-logo.png" alt="Barangay Logo">
                            </div>
                        </div>
                        
                <?php if ($userRole === 'admin' && $reportType === 'activity'): ?>
                    <!-- Admin Activity Logs -->
                    <div id="print-area" class="space-y-8">
                        <?php if (!empty($summaryStats)): ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                            <div class="bg-gradient-to-br from-indigo-600 to-indigo-700 p-6 rounded-3xl text-white shadow-lg shadow-indigo-100">
                                <div class="text-3xl font-black mb-1"><?php echo $summaryStats['total_activities']; ?></div>
                                <div class="text-[10px] font-bold uppercase tracking-widest opacity-80">Total Activities</div>
                            </div>
                            <div class="bg-gradient-to-br from-emerald-600 to-emerald-700 p-6 rounded-3xl text-white shadow-lg shadow-emerald-100">
                                <div class="text-3xl font-black mb-1"><?php echo $summaryStats['unique_users']; ?></div>
                                <div class="text-[10px] font-bold uppercase tracking-widest opacity-80">Unique Users</div>
                            </div>
                            <div class="bg-gradient-to-br from-amber-600 to-amber-700 p-6 rounded-3xl text-white shadow-lg shadow-amber-100">
                                <div class="text-xl font-black mb-1"><?php echo date('M j, Y', strtotime($summaryStats['first_activity'])); ?></div>
                                <div class="text-[10px] font-bold uppercase tracking-widest opacity-80">First Recorded</div>
                            </div>
                            <div class="bg-gradient-to-br from-rose-600 to-rose-700 p-6 rounded-3xl text-white shadow-lg shadow-rose-100">
                                <div class="text-xl font-black mb-1"><?php echo date('M j, Y', strtotime($summaryStats['last_activity'])); ?></div>
                                <div class="text-[10px] font-bold uppercase tracking-widest opacity-80">Last Activity</div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($reportData)): ?>
                        <div class="overflow-x-auto rounded-2xl border border-slate-100">
                            <table class="w-full text-left font-inter">
                                <thead class="bg-slate-50 border-b border-slate-100">
                                    <tr>
                                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Timestamp</th>
                                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">User Details</th>
                                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Role</th>
                                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Action</th>
                                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">IP</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50">
                                    <?php foreach ($reportData as $row): ?>
                                    <tr class="hover:bg-slate-50/50 transition-colors">
                                        <td class="px-6 py-4 text-xs font-bold text-slate-500"><?php echo date('M j, g:i A', strtotime($row['timestamp'])); ?></td>
                                        <td class="px-6 py-4">
                                            <?php if ($row['username']): ?>
                                                <div class="text-sm font-black text-slate-900"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></div>
                                                <div class="text-[10px] font-bold text-slate-400 lowercase italic">@<?php echo htmlspecialchars($row['username']); ?></div>
                                            <?php else: ?>
                                                <span class="text-xs font-bold text-slate-400 uppercase tracking-widest">System</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="px-3 py-1 bg-slate-100 text-slate-600 rounded-lg text-[10px] font-black uppercase tracking-widest border border-slate-200">
                                                <?php echo ucfirst($row['role'] ?? 'System'); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-sm font-medium text-slate-600"><?php echo htmlspecialchars($row['activity']); ?></td>
                                        <td class="px-6 py-4 text-[10px] font-mono text-slate-400"><?php echo htmlspecialchars($row['ip_address'] ?? 'N/A'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="py-20 flex flex-col items-center justify-center opacity-30 select-none">
                            <i class="fas fa-history text-6xl mb-4"></i>
                            <p class="font-black text-sm uppercase tracking-widest">No activity logs found</p>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                    </div>
                <?php endif; ?>

            <!-- Midwife Reports -->
            <?php elseif ($userRole === 'midwife'): ?>
                <?php if ($userRole === 'midwife'): ?>
                    <?php if ($reportType === 'prenatal_postnatal_combined'): ?>
                        <div id="print-area" class="space-y-8">
                            <!-- Printable Header -->
                            <div class="hidden print:flex justify-between items-center border-b-2 border-slate-900 pb-6 mb-8">
                                <img src="images/doh-logo.png" class="h-20 w-auto" alt="DOH">
                                <div class="text-center">
                                    <h4 class="text-xl font-black uppercase tracking-tight">Prenatal & Postnatal Care Record</h4>
                                    <p class="text-[10px] font-bold text-slate-500 uppercase">Barangay Health Station - Kibenes</p>
                                    <div class="text-[10px] font-black mt-1 uppercase tracking-widest">Period: <?= date('M j, Y', strtotime($startDate)) ?> - <?= date('M j, Y', strtotime($endDate)) ?></div>
                                </div>
                                <img src="images/brgy-logo.png" class="h-20 w-auto" alt="Barangay">
                            </div>

                            <div class="overflow-x-auto rounded-3xl border border-slate-100 shadow-sm print:border-none">
                                <table class="w-full text-left font-inter">
                                    <thead class="bg-slate-50 border-b border-slate-100 print:bg-slate-100">
                                        <tr class="divide-x divide-slate-100 print:divide-slate-200">
                                            <th class="px-4 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest">Mother Name</th>
                                            <th class="px-3 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center">Date</th>
                                            <th class="px-3 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center">Type</th>
                                            <th class="px-3 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center">BP</th>
                                            <th class="px-3 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center">Wt</th>
                                            <th class="px-3 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center">AOG/Temp</th>
                                            <th class="px-3 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center">HB</th>
                                            <th class="px-4 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest">Baby Details</th>
                                            <th class="px-4 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest">Findings/Treatment</th>
                                            <th class="px-3 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center">Next Visit</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-50">
                                        <?php if (!empty($combinedRecords)): ?>
                                            <?php foreach ($combinedRecords as $record): ?>
                                            <tr class="divide-x divide-slate-50 border-b border-slate-50 print:border-slate-100 <?= $record['record_type'] === 'prenatal' ? 'bg-indigo-50/20' : 'bg-rose-50/20' ?>">
                                                <td class="px-4 py-3">
                                                    <div class="text-sm font-black text-slate-900"><?= htmlspecialchars($record['last_name'] . ', ' . $record['first_name']) ?></div>
                                                    <div class="text-[10px] font-bold text-slate-400"><?= htmlspecialchars($record['phone']) ?></div>
                                                </td>
                                                <td class="px-3 py-3 text-center text-xs font-bold text-slate-600"><?= date('m/d/y', strtotime($record['visit_date'])) ?></td>
                                                <td class="px-3 py-3 text-center">
                                                    <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-widest <?= $record['record_type'] === 'prenatal' ? 'bg-indigo-100 text-indigo-700' : 'bg-rose-100 text-rose-700' ?>">
                                                        <?= $record['record_type'] ?>
                                                    </span>
                                                </td>
                                                <td class="px-3 py-3 text-center text-xs font-black tracking-tight"><?= $record['blood_pressure'] ?: '-' ?></td>
                                                <td class="px-3 py-3 text-center text-xs font-medium text-slate-600"><?= ($record['record_type'] === 'prenatal' ? $record['weight'] : $record['mother_weight']) ?: '-' ?></td>
                                                <td class="px-3 py-3 text-center text-xs font-medium text-slate-600"><?= ($record['record_type'] === 'prenatal' ? $record['gestational_age'] . 'w' : $record['temperature'] . '°C') ?: '-' ?></td>
                                                <td class="px-3 py-3 text-center text-xs font-medium text-slate-600"><?= $record['hb_level'] ?: '-' ?></td>
                                                <td class="px-4 py-3">
                                                    <?php if ($record['record_type'] === 'postnatal' && !empty($record['baby_first_name'])): ?>
                                                        <div class="text-xs font-black text-slate-800"><?= htmlspecialchars($record['baby_first_name']) ?></div>
                                                        <div class="text-[9px] font-bold text-slate-400 uppercase tracking-tight"><?= $record['gender'] ?> • <?= date('m/d/y', strtotime($record['birth_date'])) ?></div>
                                                    <?php else: ?>
                                                        <span class="text-[9px] font-black text-slate-300 uppercase italic">In Utero</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <p class="text-[11px] font-medium text-slate-600 leading-relaxed max-w-[200px] print:max-w-none">
                                                        <?php
                                                        $parts = [];
                                                        if (!empty($record['complaints'])) $parts[] = "<span class='font-black text-slate-900 uppercase text-[9px]'>C:</span> " . $record['complaints'];
                                                        if (!empty($record['findings'])) $parts[] = "<span class='font-black text-slate-900 uppercase text-[9px]'>F:</span> " . $record['findings'];
                                                        if (!empty($record['treatment'])) $parts[] = "<span class='font-black text-slate-900 uppercase text-[9px]'>T:</span> " . $record['treatment'];
                                                        echo implode('<br>', $parts) ?: '-';
                                                        ?>
                                                    </p>
                                                </td>
                                                <td class="px-3 py-3 text-center text-xs font-black text-health-600"><?= !empty($record['next_visit_date']) ? date('m/d/y', strtotime($record['next_visit_date'])) : '-' ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="10" class="py-12 text-center opacity-30">
                                                    <i class="fas fa-notes-medical text-4xl mb-2"></i>
                                                    <p class="text-[10px] font-black uppercase tracking-widest">No mother care records found</p>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Prepared Section -->
                            <div class="flex justify-end mt-12 pr-12">
                                <div class="text-center space-y-4">
                                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Prepared by</p>
                                    <div class="space-y-1">
                                        <div class="text-sm font-black text-slate-900 uppercase tracking-tight"><?= $_SESSION['first_name'] . ' ' . $_SESSION['last_name'] ?></div>
                                        <div class="h-px bg-slate-900 w-full"></div>
                                        <div class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">Official Designation</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                
                <?php elseif ($reportType === 'birth_registration'): ?>
                    <!-- Birth Registration Report -->
                    <div id="print-area" class="space-y-8">
                        <!-- Printable Header -->
                        <div class="hidden print:flex justify-between items-center border-b-2 border-slate-900 pb-6 mb-8">
                            <img src="images/doh-logo.png" class="h-20 w-auto" alt="DOH">
                            <div class="text-center">
                                <h4 class="text-xl font-black uppercase tracking-tight">Birth Registration Report</h4>
                                <p class="text-[10px] font-bold text-slate-500 uppercase">Barangay Health Station - Kibenes</p>
                                <div class="text-[10px] font-black mt-1 uppercase tracking-widest">Period: <?= date('M j, Y', strtotime($startDate)) ?> - <?= date('M j, Y', strtotime($endDate)) ?></div>
                            </div>
                            <img src="images/brgy-logo.png" class="h-20 w-auto" alt="Barangay">
                        </div>

                        <div class="overflow-x-auto rounded-3xl border border-slate-100 shadow-sm print:border-none">
                            <table class="w-full text-left font-inter">
                                <thead class="bg-slate-50 border-b border-slate-100 print:bg-slate-100">
                                    <tr class="divide-x divide-slate-100">
                                        <th class="px-4 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center">DOB</th>
                                        <th class="px-4 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest">Mother Name</th>
                                        <th class="px-4 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest">Baby Details</th>
                                        <th class="px-3 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center" width="80">Gender</th>
                                        <th class="px-3 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center" width="80">Wt (kg)</th>
                                        <th class="px-4 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest">Place of Birth</th>
                                        <th class="px-4 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest">Attendant</th>
                                        <th class="px-4 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest">Address</th>
                                        <th class="px-3 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center">Registered</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50">
                                    <?php if (!empty($birthRecords)): ?>
                                        <?php foreach ($birthRecords as $record): ?>
                                        <tr class="divide-x divide-slate-50 hover:bg-slate-50/30 transition-colors">
                                            <td class="px-4 py-3 text-center text-xs font-black text-slate-600"><?= date('m/d/y', strtotime($record['birth_date'])) ?></td>
                                            <td class="px-4 py-3">
                                                <div class="text-sm font-black text-slate-900"><?= htmlspecialchars($record['mother_last_name'] . ', ' . $record['mother_first_name']) ?></div>
                                                <div class="text-[10px] font-bold text-slate-400 tracking-tight"><?= htmlspecialchars($record['mother_phone']) ?></div>
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="text-sm font-black text-indigo-700"><?= htmlspecialchars($record['baby_first_name'] . ' ' . $record['baby_last_name']) ?></div>
                                            </td>
                                            <td class="px-3 py-3 text-center">
                                                <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-widest <?= $record['gender'] === 'male' ? 'bg-blue-100 text-blue-700' : 'bg-pink-100 text-pink-700' ?>">
                                                    <?= $record['gender'] ?>
                                                </span>
                                            </td>
                                            <td class="px-3 py-3 text-center text-xs font-black text-slate-700"><?= number_format($record['birth_weight'], 2) ?></td>
                                            <td class="px-4 py-3 text-xs font-medium text-slate-600"><?= htmlspecialchars($record['birth_place']) ?></td>
                                            <td class="px-4 py-3 text-xs font-medium text-slate-600"><?= htmlspecialchars($record['birth_attendant']) ?></td>
                                            <td class="px-4 py-3 text-[10px] font-bold text-slate-400 uppercase tracking-tight"><?= htmlspecialchars($record['address']) ?></td>
                                            <td class="px-3 py-3 text-center text-[10px] font-black text-health-600 uppercase tracking-widest italic"><?= !empty($record['registered_at']) ? date('m/d/y', strtotime($record['registered_at'])) : 'Pending' ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" class="py-12 text-center opacity-30">
                                                <i class="fas fa-baby text-4xl mb-2"></i>
                                                <p class="text-[10px] font-black uppercase tracking-widest">No birth registrations found</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Signatures -->
                        <div class="flex justify-end mt-12 pr-12 print:mt-24">
                            <div class="text-center space-y-4">
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Authenticated by</p>
                                <div class="space-y-1">
                                    <div class="text-sm font-black text-slate-900 uppercase tracking-tight"><?= $_SESSION['first_name'] . ' ' . $_SESSION['last_name'] ?></div>
                                    <div class="h-px bg-slate-900 w-full"></div>
                                    <div class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">Registered Midwife</div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            <?php elseif ($userRole === 'bhw' && $reportType === 'monthly'): ?>
                <div id="print-area" class="space-y-8">
                    <!-- Printable Header -->
                    <div class="hidden print:flex justify-between items-center border-b-2 border-slate-900 pb-6 mb-4">
                        <img src="images/doh-logo.png" class="h-16 w-auto" alt="DOH">
                        <div class="text-center">
                            <h4 class="text-lg font-black uppercase tracking-tight text-slate-900">Barangay Health Worker Monthly Report</h4>
                            <p class="text-[9px] font-bold text-slate-500 uppercase tracking-widest">Kibenes Health Station • Carmen, North Cotabato</p>
                            <p class="text-[10px] font-black mt-1 uppercase text-health-600">Month of <?= date('F Y', strtotime($reportMonth . '-01')) ?></p>
                        </div>
                        <img src="images/brgy-logo.png" class="h-16 w-auto" alt="Barangay">
                    </div>
                    
                    <div class="bg-indigo-50/50 p-6 rounded-3xl border border-indigo-100/50 flex flex-wrap items-center gap-6 no-print">
                        <div class="w-12 h-12 bg-indigo-600 rounded-2xl flex items-center justify-center text-white shadow-lg shadow-indigo-100">
                            <i class="fas fa-id-badge text-xl"></i>
                        </div>
                        <div class="flex-1">
                            <div class="text-[10px] font-black text-indigo-400 uppercase tracking-widest leading-none mb-1">Assigned BHW</div>
                            <div class="text-lg font-black text-slate-900"><?= $_SESSION['first_name'] . ' ' . $_SESSION['last_name'] ?></div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <?php 
                            $bhwSitios = getBHWSitiosForDisplay();
                            if (!empty($bhwSitios)): 
                                $sitiosArray = explode(', ', $bhwSitios);
                                foreach ($sitiosArray as $sitio): 
                            ?>
                                <span class="bg-white px-3 py-1.5 rounded-xl text-[10px] font-black text-indigo-600 uppercase tracking-widest shadow-sm border border-indigo-100 flex items-center gap-2">
                                    <i class="fas fa-map-marker-alt text-[8px]"></i>
                                    <?= $sitio ?>
                                </span>
                            <?php endforeach; else: ?>
                                <span class="bg-amber-50 text-amber-600 px-4 py-1.5 rounded-xl text-[10px] font-black uppercase tracking-widest italic border border-amber-100">No Sitio Assigned</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="space-y-8">
                        <!-- Maternal Care Section -->
                        <section class="rounded-3xl border border-slate-100 overflow-hidden shadow-sm">
                            <div class="bg-slate-50 px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                                <h3 class="text-xs font-black text-slate-800 uppercase tracking-widest">I. Maternal Care Indicators</h3>
                                <i class="fas fa-female text-slate-300"></i>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left font-inter text-xs">
                                    <thead class="bg-slate-100/50 print:bg-slate-200">
                                        <tr class="divide-x divide-slate-100">
                                            <th class="px-6 py-4 font-black text-slate-500 uppercase tracking-tighter" style="width: 55%">Indicator Description</th>
                                            <th class="px-4 py-4 font-black text-slate-500 uppercase tracking-tighter text-center" style="width: 15%">Previous Month</th>
                                            <th class="px-4 py-4 font-black text-slate-900 uppercase tracking-tighter text-center bg-slate-100" style="width: 15%">Current Month</th>
                                            <th class="px-4 py-4 font-black text-health-600 uppercase tracking-tighter text-center font-black" style="width: 15%">Cumulative Total</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-50 whitespace-nowrap">
                                        <tr class="divide-x divide-slate-50 hover:bg-slate-50 transition-colors">
                                            <td class="px-6 py-4 font-medium text-slate-700">1. Newly Pregnant referred for prenatal</td>
                                            <td class="px-4 py-4 text-center font-bold text-slate-400"><?= $maternalData['new_referred']['prev'] ?></td>
                                            <td class="px-4 py-4 text-center font-black text-slate-900 bg-slate-50 group-hover:bg-slate-100/50"><?= $maternalData['new_referred']['current'] ?></td>
                                            <td class="px-4 py-4 text-center font-black text-health-600 italic"><?= $maternalData['new_referred']['prev'] + $maternalData['new_referred']['current'] ?></td>
                                        </tr>
                                        <tr class="divide-x divide-slate-50 hover:bg-slate-50">
                                            <td class="px-6 py-4 font-medium text-slate-700">2. Newly Pregnant assisted for prenatal</td>
                                            <td class="px-4 py-4 text-center font-bold text-slate-400"><?= $maternalData['new_assisted']['prev'] ?></td>
                                            <td class="px-4 py-4 text-center font-black text-slate-900 bg-slate-50"><?= $maternalData['new_assisted']['current'] ?></td>
                                            <td class="px-4 py-4 text-center font-black text-health-600 italic"><?= $maternalData['new_assisted']['prev'] + $maternalData['new_assisted']['current'] ?></td>
                                        </tr>
                                        <tr class="divide-x divide-slate-50 hover:bg-slate-50">
                                            <td class="px-6 py-4 font-medium text-slate-700">3. Pregnant women tracked (follow-up)</td>
                                            <td class="px-4 py-4 text-center font-bold text-slate-400"><?= $maternalData['tracked']['prev'] ?></td>
                                            <td class="px-4 py-4 text-center font-black text-slate-900 bg-slate-50"><?= $maternalData['tracked']['current'] ?></td>
                                            <td class="px-4 py-4 text-center font-black text-health-600 italic"><?= $maternalData['tracked']['prev'] + $maternalData['tracked']['current'] ?></td>
                                        </tr>
                                        <tr class="divide-x divide-slate-50 hover:bg-slate-50">
                                            <td class="px-6 py-4 font-medium text-slate-700">4. Total Deliveries (Home & Facility)</td>
                                            <td class="px-4 py-4 text-center font-bold text-slate-400"><?= $maternalData['deliveries']['prev'] ?></td>
                                            <td class="px-4 py-4 text-center font-black text-slate-900 bg-slate-50"><?= $maternalData['deliveries']['current'] ?></td>
                                            <td class="px-4 py-4 text-center font-black text-health-600 italic"><?= $maternalData['deliveries']['prev'] + $maternalData['deliveries']['current'] ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <!-- Child Care Section -->
                        <section class="rounded-3xl border border-slate-100 overflow-hidden shadow-sm">
                            <div class="bg-slate-50 px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                                <h3 class="text-xs font-black text-slate-800 uppercase tracking-widest">II. Child Care Indicators</h3>
                                <i class="fas fa-baby text-slate-300"></i>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left font-inter text-xs">
                                    <thead class="bg-slate-100/50">
                                        <tr class="divide-x divide-slate-100">
                                            <th class="px-6 py-4 font-black text-slate-500 uppercase tracking-tighter" style="width: 55%">Indicator Description</th>
                                            <th class="px-4 py-4 font-black text-slate-500 uppercase tracking-tighter text-center" style="width: 15%">Previous Month</th>
                                            <th class="px-4 py-4 font-black text-slate-900 uppercase tracking-tighter text-center bg-slate-100" style="width: 15%">Current Month</th>
                                            <th class="px-4 py-4 font-black text-health-600 uppercase tracking-tighter text-center font-black" style="width: 15%">Cumulative Total</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-50 whitespace-nowrap">
                                        <tr class="divide-x divide-slate-50 hover:bg-slate-50">
                                            <td class="px-6 py-4 font-medium text-slate-700">1. Under 1yr referred for immunization</td>
                                            <td class="px-4 py-4 text-center font-bold text-slate-400"><?= $childCareData['immun_referred']['prev'] ?></td>
                                            <td class="px-4 py-4 text-center font-black text-slate-900 bg-slate-50"><?= $childCareData['immun_referred']['current'] ?></td>
                                            <td class="px-4 py-4 text-center font-black text-health-600 italic"><?= $childCareData['immun_referred']['prev'] + $childCareData['immun_referred']['current'] ?></td>
                                        </tr>
                                        <tr class="divide-x divide-slate-50 hover:bg-slate-50">
                                            <td class="px-6 py-4 font-medium text-slate-700">2. Under 1yr assisted immunization</td>
                                            <td class="px-4 py-4 text-center font-bold text-slate-400"><?= $childCareData['immun_assisted']['prev'] ?></td>
                                            <td class="px-4 py-4 text-center font-black text-slate-900 bg-slate-50"><?= $childCareData['immun_assisted']['current'] ?></td>
                                            <td class="px-4 py-4 text-center font-black text-health-600 italic"><?= $childCareData['immun_assisted']['prev'] + $childCareData['immun_assisted']['current'] ?></td>
                                        </tr>
                                        <tr class="divide-x divide-slate-50 hover:bg-slate-50">
                                            <td class="px-6 py-4 font-medium text-slate-700">3. NIP Defaulters tracked</td>
                                            <td class="px-4 py-4 text-center font-bold text-slate-400"><?= $childCareData['defaulter_tracked']['prev'] ?></td>
                                            <td class="px-4 py-4 text-center font-black text-slate-900 bg-slate-50"><?= $childCareData['defaulter_tracked']['current'] ?></td>
                                            <td class="px-4 py-4 text-center font-black text-health-600 italic"><?= $childCareData['defaulter_tracked']['prev'] + $childCareData['defaulter_tracked']['current'] ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <!-- TB Section -->
                        <section class="rounded-3xl border border-slate-100 overflow-hidden shadow-sm">
                            <div class="bg-slate-50 px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                                <h3 class="text-xs font-black text-slate-800 uppercase tracking-widest">III. Tuberculosis Program</h3>
                                <i class="fas fa-microscope text-slate-300"></i>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left font-inter text-xs">
                                    <thead class="bg-slate-100/50">
                                        <tr class="divide-x divide-slate-100">
                                            <th class="px-6 py-4 font-black text-slate-500 uppercase tracking-tighter" style="width: 55%">Indicator Description</th>
                                            <th class="px-4 py-4 font-black text-slate-500 uppercase tracking-tighter text-center" style="width: 15%">Prev</th>
                                            <th class="px-4 py-4 font-black text-slate-900 uppercase tracking-tighter text-center bg-slate-100" style="width: 15%">Current</th>
                                            <th class="px-4 py-4 font-black text-health-600 uppercase tracking-tighter text-center font-black" style="width: 15%">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-50">
                                        <tr class="divide-x divide-slate-50">
                                            <td class="px-6 py-4 font-medium text-slate-700 italic">No TB indicators recorded for this period</td>
                                            <td class="px-4 py-4 text-center text-slate-300">-</td>
                                            <td class="px-4 py-4 text-center text-slate-300 bg-slate-50">-</td>
                                            <td class="px-4 py-4 text-center text-slate-300">-</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest leading-loose italic">
                            <i class="fas fa-info-circle mr-1"></i> Data sources should be based on actual household visits, not solely from TCL.
                        </p>

                        <!-- Signature Matrix -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-12 mt-16 print:mt-32">
                            <div class="text-center space-y-4">
                                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest leading-none">Prepared By</p>
                                <div class="space-y-1">
                                    <div class="text-xs font-black text-slate-900 uppercase tracking-tight"><?= $_SESSION['first_name'] . ' ' . $_SESSION['last_name'] ?></div>
                                    <div class="h-px bg-slate-900 w-full mx-auto" style="width: 80%"></div>
                                    <div class="text-[8px] font-bold text-slate-400 uppercase">Barangay Health Worker</div>
                                </div>
                            </div>
                            <div class="text-center space-y-4">
                                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest leading-none">Reviewed By</p>
                                <div class="space-y-1">
                                    <div class="text-xs font-black text-slate-900 uppercase tracking-tight opacity-0 italic">Midwife Name</div>
                                    <div class="h-px bg-slate-900 w-full mx-auto" style="width: 80%"></div>
                                    <div class="text-[8px] font-bold text-slate-400 uppercase">Public Health Midwife</div>
                                </div>
                            </div>
                            <div class="text-center space-y-4">
                                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest leading-none">Approved By</p>
                                <div class="space-y-1">
                                    <div class="text-xs font-black text-slate-900 uppercase tracking-tight">HON. RONALD K. AKMAD</div>
                                    <div class="h-px bg-slate-900 w-full mx-auto" style="width: 80%"></div>
                                    <div class="text-[8px] font-bold text-slate-400 uppercase">Punong Barangay</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            <!-- BNS Monthly Report -->
            <?php elseif ($userRole === 'bns' && $reportType === 'monthly'): ?>
                <div id="print-area" class="space-y-12">
                     <!-- Printable Header -->
                     <div class="hidden print:flex justify-between items-center border-b-2 border-slate-900 pb-6 mb-8">
                        <img src="images/doh-logo.png" class="h-20 w-auto" alt="DOH">
                        <div class="text-center">
                            <h4 class="text-xl font-black uppercase tracking-tight text-slate-900">Barangay Nutrition Scholar Monthly Report</h4>
                            <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest leading-loose">Kibenes Health Station • Carmen, North Cotabato</p>
                            <p class="text-[11px] font-black mt-1 uppercase text-health-600 bg-health-50 px-4 py-1 rounded-full inline-block">Period: <?= date('F Y', strtotime($reportMonth . '-01')) ?></p>
                        </div>
                        <img src="images/brgy-logo.png" class="h-20 w-auto" alt="Barangay">
                    </div>

                    <div class="space-y-6">
                        <div class="flex items-center gap-4 px-2">
                             <div class="w-1.5 h-6 bg-health-600 rounded-full"></div>
                             <h3 class="text-sm font-black text-slate-900 uppercase tracking-widest">Child Nutrition Registry & Acknowledgement</h3>
                        </div>

                        <div class="overflow-x-auto rounded-[2rem] border border-slate-100 shadow-sm print:border-none print:shadow-none">
                            <table class="w-full text-left font-inter table-fixed">
                                <thead>
                                    <tr class="bg-slate-50 border-b border-slate-100 text-[9px] font-black text-slate-400 uppercase tracking-widest divide-x divide-slate-100 print:bg-slate-100 print:divide-slate-200">
                                        <th class="px-3 py-4 text-center" width="40">#</th>
                                        <th class="px-4 py-4" width="200">Parent/Guardian</th>
                                        <th class="px-4 py-4 text-center" width="120">Purok</th>
                                        <th class="px-4 py-4" width="220">Child Name</th>
                                        <th class="px-3 py-4 text-center" width="60">Age</th>
                                        <th class="px-3 py-4 text-center" width="60">Gen</th>
                                        <th class="px-3 py-4 text-center" width="60">Preg</th>
                                        <th class="px-3 py-4 text-center" width="60">Lact</th>
                                        <th class="px-3 py-4 text-center" width="80">Sub-5</th>
                                        <th class="px-4 py-4 text-center" width="140">Signature</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50">
                                    <?php $counter = 1; ?>
                                    <?php foreach ($acknowledgementData as $row): ?>
                                    <tr class="divide-x divide-slate-50 hover:bg-slate-50 transition-colors text-[11px] print:divide-slate-100">
                                        <td class="px-3 py-3 text-center font-bold text-slate-400 italic"><?= $counter ?></td>
                                        <td class="px-4 py-3 font-black text-slate-900"><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
                                        <td class="px-4 py-3 text-center text-slate-500"><?= htmlspecialchars($row['address']) ?></td>
                                        <td class="px-4 py-3 font-bold text-indigo-700"><?= htmlspecialchars($row['child_first_name'] . ' ' . $row['child_last_name']) ?></td>
                                        <td class="px-3 py-3 text-center font-black"><?= $row['child_age'] ?></td>
                                        <td class="px-3 py-3 text-center uppercase font-bold text-slate-400"><?= substr($row['gender'], 0, 1) ?></td>
                                        <td class="px-3 py-3 text-center opacity-20">-</td>
                                        <td class="px-3 py-3 text-center opacity-20">-</td>
                                        <td class="px-3 py-3 text-center font-black text-health-600"><?= $row['children_under_5'] ?></td>
                                        <td class="px-4 py-3 bg-slate-50/50 print:bg-transparent"></td>
                                    </tr>
                                    <?php $counter++; ?>
                                    <?php endforeach; ?>
                                    
                                    <?php for ($i = $counter; $i <= 10; $i++): ?>
                                    <tr class="divide-x divide-slate-50 border-b border-slate-50 text-[11px] h-10 print:divide-slate-100">
                                        <td class="px-3 text-center text-slate-300"><?= $i ?></td>
                                        <td class="px-4"></td><td class="px-4"></td><td class="px-4"></td><td class="px-3"></td><td class="px-3"></td><td class="px-3"></td><td class="px-3"></td><td class="px-3"></td><td class="px-4 bg-slate-50/50 print:bg-transparent"></td>
                                    </tr>
                                    <?php endfor; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Signature Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-12 mt-16 print:mt-32">
                        <div class="text-center space-y-4">
                            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest leading-none">Prepared By</p>
                            <div class="space-y-1">
                                <div class="text-xs font-black text-slate-900 uppercase tracking-tight"><?= $_SESSION['first_name'] . ' ' . $_SESSION['last_name'] ?></div>
                                <div class="h-px bg-slate-900 w-full mx-auto" style="width: 80%"></div>
                                <div class="text-[8px] font-bold text-slate-400 uppercase tracking-tight">Barangay Nutrition Scholar</div>
                            </div>
                        </div>
                        <div class="text-center space-y-4">
                            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest leading-none">Reviewed By</p>
                            <div class="space-y-1">
                                <div class="text-xs font-black text-slate-900 uppercase tracking-tight opacity-0 italic">Midwife Name</div>
                                <div class="h-px bg-slate-900 w-full mx-auto" style="width: 80%"></div>
                                <div class="text-[8px] font-bold text-slate-400 uppercase tracking-tight">Public Health Midwife</div>
                            </div>
                        </div>
                        <div class="text-center space-y-4">
                            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest leading-none">Approved By</p>
                            <div class="space-y-1">
                                <div class="text-xs font-black text-slate-900 uppercase tracking-tight">HON. RONALD K. AKMAD</div>
                                <div class="h-px bg-slate-900 w-full mx-auto" style="width: 80%"></div>
                                <div class="text-[8px] font-bold text-slate-400 uppercase tracking-tight">Punong Barangay</div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            <?php endif; ?>

        </main>
    </div>
</div>

<script>
    // Show/hide custom date range
    document.getElementById('timePeriodSelector')?.addEventListener('change', function() {
        const isCustom = this.value === 'custom';
        const startCol = document.getElementById('startDateCol');
        const endCol = document.getElementById('endDateCol');
        
        if (startCol && endCol) {
            startCol.style.display = isCustom ? 'block' : 'none';
            endCol.style.display = isCustom ? 'block' : 'none';
        }
    });

    function optimizePrint() {
        window.print();
    }
</script>
</body>
</html>