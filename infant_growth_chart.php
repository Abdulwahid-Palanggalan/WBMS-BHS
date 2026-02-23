<?php
/**
 * infant_growth_chart.php - WHO-Standard Growth Engine
 * Upgraded with percentile shading and premium UI
 */
require_once 'includes/auth.php';
require_once 'includes/functions.php';

if (!isAuthorized(['admin', 'midwife', 'mother'])) {
    header("Location: login.php");
    exit();
}

$babyId = $_GET['baby_id'] ?? '';
if (empty($babyId)) die("Baby ID is required.");

global $pdo;

// 1. Get Baby Info
$stmt = $pdo->prepare("SELECT * FROM birth_records WHERE id = ?");
$stmt->execute([$babyId]);
$baby = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$baby) die("Baby not found.");

// 2. Get Postnatal Growth Data
$stmt = $pdo->prepare("
    SELECT visit_date, baby_weight, baby_height 
    FROM postnatal_records 
    WHERE baby_id = ? 
    ORDER BY visit_date ASC
");
$stmt->execute([$babyId]);
$measurements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. WHO Metadata (Simplified Weight-for-age 0-12 months for demonstration)
$isMale = (strtolower($baby['gender']) === 'male');

// WHO Weight-for-age (Simplified Data Points for 0-12 months)
// Format: Month => [P3, P15, P50, P85, P97]
$whoBoys = [
    0 => [2.5, 2.9, 3.3, 3.9, 4.4],
    1 => [3.4, 3.9, 4.5, 5.1, 5.8],
    2 => [4.3, 4.9, 5.6, 6.3, 7.1],
    3 => [5.0, 5.7, 6.4, 7.2, 8.0],
    4 => [5.6, 6.3, 7.0, 7.8, 8.7],
    6 => [6.4, 7.1, 7.9, 8.8, 9.8],
    9 => [7.1, 8.0, 8.9, 9.9, 11.0],
    12 => [7.7, 8.6, 9.6, 10.8, 12.0]
];

$whoGirls = [
    0 => [2.4, 2.8, 3.2, 3.7, 4.2],
    1 => [3.2, 3.6, 4.2, 4.8, 5.5],
    2 => [3.9, 4.5, 5.1, 5.8, 6.6],
    3 => [4.5, 5.2, 5.8, 6.6, 7.5],
    4 => [5.0, 5.7, 6.4, 7.3, 8.2],
    6 => [5.7, 6.5, 7.3, 8.2, 9.3],
    9 => [6.5, 7.4, 8.2, 9.3, 10.5],
    12 => [7.0, 7.9, 8.9, 10.1, 11.5]
];

$standards = $isMale ? $whoBoys : $whoGirls;
$p3 = []; $p15 = []; $p50 = []; $p85 = []; $p97 = []; $labels = [];

foreach ($standards as $month => $vals) {
    $labels[] = "Mo $month";
    $p3[] = $vals[0]; $p15[] = $vals[1]; $p50[] = $vals[2]; $p85[] = $vals[3]; $p97[] = $vals[4];
}

// Prepare Baby's Data
$babyPoints = [];
// Birth point (Month 0)
$babyPoints[] = ['x' => 'Mo 0', 'y' => $baby['birth_weight']];

foreach ($measurements as $m) {
    if (!empty($m['baby_weight'])) {
        $visitDate = new DateTime($m['visit_date']);
        $birthDate = new DateTime($baby['birth_date']);
        $diff = $birthDate->diff($visitDate);
        $months = floor(($diff->days) / 30.41);
        
        // Only plot if within the standard range (0-12 months)
        if ($months <= 12) {
            $babyPoints[] = ['x' => "Mo $months", 'y' => $m['baby_weight']];
        }
    }
}

// Recent Growth Logic
$currentWeight = !empty($measurements) ? end($measurements)['baby_weight'] : $baby['birth_weight'];
$weightGain = $currentWeight - $baby['birth_weight'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Growth Chart - <?= htmlspecialchars($baby['first_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include_once 'includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include_once 'includes/sidebar.php'; ?>
            
            <main class="main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="fw-bold mb-1">Growth Monitoring</h2>
                        <p class="text-muted small mb-0">WHO Standardized Weight-for-Age Chart</p>
                    </div>
                    <div class="text-end">
                        <button class="btn btn-outline-secondary btn-sm me-2" onclick="history.back()">
                            <i class="fas fa-arrow-left me-1"></i> Back
                        </button>
                        <button class="btn btn-primary btn-sm" onclick="window.print()">
                            <i class="fas fa-print me-1"></i> Export PDF
                        </button>
                    </div>
                </div>

                <!-- Bento Stat Grid -->
                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <div class="card stats-card border-0 shadow-sm" style="border-top: 4px solid var(--primary) !important;">
                            <div class="stats-icon" style="background: var(--primary-light); color: var(--primary);">
                                <i class="fas fa-id-card"></i>
                            </div>
                            <span class="fw-bold d-block"><?= htmlspecialchars($baby['first_name'] . ' ' . $baby['last_name']); ?></span>
                            <span class="stats-label"><?= ucfirst($baby['gender']); ?> • Born <?= date('M d, Y', strtotime($baby['birth_date'])); ?></span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stats-card border-0 shadow-sm" style="border-top: 4px solid var(--accent) !important;">
                            <div class="stats-icon" style="background: #e8f5e8; color: var(--accent);">
                                <i class="fas fa-weight-scale"></i>
                            </div>
                            <span class="stats-number"><?= $currentWeight; ?> kg</span>
                            <span class="stats-label">Latest Recorded Weight</span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stats-card border-0 shadow-sm" style="border-top: 4px solid var(--secondary) !important;">
                            <div class="stats-icon" style="background: #e3f2fd; color: var(--secondary);">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <span class="stats-number"><?= number_format($weightGain, 2); ?> kg</span>
                            <span class="stats-label">Total Gain from Birth</span>
                        </div>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-lg-9">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header border-0 bg-transparent py-4">
                                <h5 class="fw-bold mb-0">Weight-for-Age (0-12 Months)</h5>
                                <p class="text-muted small mb-0">Shaded areas represent WHO percentile distributions</p>
                            </div>
                            <div class="card-body">
                                <div style="height: 450px;">
                                    <canvas id="growthChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3">
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-body">
                                <h6 class="fw-bold mb-3">Legend Guide</h6>
                                <div class="small">
                                    <div class="d-flex align-items-center mb-2">
                                        <div style="width: 12px; height: 12px; background: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444;" class="me-2"></div>
                                        <span>Borderline (P3/P97)</span>
                                    </div>
                                    <div class="d-flex align-items-center mb-2">
                                        <div style="width: 12px; height: 12px; background: rgba(16, 185, 129, 0.2); border: 1px solid #10b981;" class="me-2"></div>
                                        <span>Optimal (P15-P85)</span>
                                    </div>
                                    <div class="d-flex align-items-center mb-4">
                                        <div style="width: 12px; height: 12px; background: #2563eb; border-radius: 50%;" class="me-2"></div>
                                        <span>Patient Record</span>
                                    </div>
                                    
                                    <div class="alert alert-light border small">
                                        <i class="fas fa-info-circle text-primary me-2"></i>
                                        Healthy growth usually follows parallel to the curves. Sudden drops or spikes should be reviewed by a midwife.
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card border-0 shadow-sm bg-primary-light border-start border-4 border-primary">
                            <div class="card-body">
                                <h6 class="fw-bold text-primary mb-2">Next Actions</h6>
                                <ul class="list-unstyled small mb-0">
                                    <li class="mb-2"><i class="fas fa-check-circle me-2"></i> Schedule 6-month check</li>
                                    <li><i class="fas fa-check-circle me-2"></i> Update immunization</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        const ctx = document.getElementById('growthChart').getContext('2d');
        const chartData = {
            labels: <?= json_encode($labels); ?>,
            datasets: [
                {
                    label: 'Baby Weight',
                    data: <?= json_encode($babyPoints); ?>,
                    borderColor: '#1e293b',
                    backgroundColor: '#1e293b',
                    borderWidth: 3,
                    pointRadius: 6,
                    pointHoverRadius: 8,
                    z: 10,
                    type: 'line'
                },
                {
                    label: '97th Percentile',
                    data: <?= json_encode($p97); ?>,
                    borderColor: '#ef4444',
                    borderWidth: 1,
                    pointRadius: 0,
                    fill: false
                },
                {
                    label: '85th Percentile',
                    data: <?= json_encode($p85); ?>,
                    borderColor: '#10b981',
                    borderWidth: 1,
                    pointRadius: 0,
                    fill: '-1',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)'
                },
                {
                    label: '50th Percentile (Median)',
                    data: <?= json_encode($p50); ?>,
                    borderColor: '#10b981',
                    borderDash: [5, 5],
                    borderWidth: 2,
                    pointRadius: 0,
                    fill: false
                },
                {
                    label: '15th Percentile',
                    data: <?= json_encode($p15); ?>,
                    borderColor: '#10b981',
                    borderWidth: 1,
                    pointRadius: 0,
                    fill: '-1',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)'
                },
                {
                    label: '3rd Percentile',
                    data: <?= json_encode($p3); ?>,
                    borderColor: '#ef4444',
                    borderWidth: 1,
                    pointRadius: 0,
                    fill: '-1',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)'
                }
            ]
        };

        new Chart(ctx, {
            type: 'line',
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: '#f1f5f9' },
                        title: { display: true, text: 'Weight (kg)', font: { weight: '600' } }
                    },
                    x: {
                        grid: { display: false },
                        title: { display: true, text: 'Age (Months)', font: { weight: '600' } }
                    }
                }
            }
        });
    </script>
</body>
</html>
