<?php
// infant_growth_chart.php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

if (!isAuthorized(['admin', 'midwife'])) {
    header("Location: login.php");
    exit();
}

$babyId = $_GET['baby_id'] ?? '';

if (empty($babyId)) {
    die("Baby ID is required.");
}

// 1. Get Baby Info & Birth Data
$stmt = $pdo->prepare("SELECT * FROM birth_records WHERE id = ?");
$stmt->execute([$babyId]);
$baby = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$baby) {
    die("Baby not found.");
}

// 2. Get Postnatal Growth Data (Weight & optional Height)
$stmt = $pdo->prepare("
    SELECT visit_date, baby_weight, baby_height 
    FROM postnatal_records 
    WHERE baby_id = ? 
    ORDER BY visit_date ASC
");
$stmt->execute([$babyId]);
$measurements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare Data for Chart.js
$dates = [];
$weights = [];
$heights = [];

// Add Birth Data as the first point
$dates[] = date('Y-m-d', strtotime($baby['birth_date'])) . " (Birth)";
$weights[] = $baby['birth_weight'];
$heights[] = $baby['birth_length'];

foreach ($measurements as $m) {
    if (!empty($m['baby_weight'])) {
        $dates[] = date('Y-m-d', strtotime($m['visit_date']));
        $weights[] = $m['baby_weight'];
        // Check if baby_height column exists/has data
        $heights[] = $m['baby_height'] ?? null; 
    }
}

// Convert to JSON for JS
$jsonDates = json_encode($dates);
$jsonWeights = json_encode($weights);
$jsonHeights = json_encode($heights);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Growth Chart - <?php echo htmlspecialchars($baby['first_name']); ?></title>
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
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Growth Chart: <?php echo htmlspecialchars($baby['first_name'] . ' ' . $baby['last_name']); ?></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="immunization_records.php" class="btn btn-secondary me-2">
                            <i class="fas fa-arrow-left me-2"></i>Back to Records
                        </a>
                        <button class="btn btn-outline-primary" onclick="window.print()">
                            <i class="fas fa-print me-2"></i>Print Chart
                        </button>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-8">
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Weight-for-Age Monitoring</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="weightChart"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">Growth Statistics</h5>
                            </div>
                            <div class="card-body">
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        Birth Weight
                                        <span class="badge bg-primary rounded-pill"><?php echo $baby['birth_weight']; ?> kg</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        Current Weight
                                        <span class="badge bg-success rounded-pill"><?php echo end($weights); ?> kg</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        Total Weight Gain
                                        <span class="badge bg-info text-dark rounded-pill">
                                            <?php echo number_format(end($weights) - $baby['birth_weight'], 2); ?> kg
                                        </span>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- WHO Standards Reference (Simplified) -->
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <h6><i class="fas fa-info-circle text-primary me-2"></i>WHO Standards</h6>
                                <p class="small text-muted">
                                    Expected weight gain for first 3 months: ~25-30g per day.<br>
                                    Birth weight usually doubles by 5 months.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        const ctx = document.getElementById('weightChart').getContext('2d');
        const weightChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo $jsonDates; ?>,
                datasets: [{
                    label: 'Weight (kg)',
                    data: <?php echo $jsonWeights; ?>,
                    borderColor: 'rgba(75, 192, 192, 1)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    borderWidth: 3,
                    pointBackgroundColor: 'rgba(75, 192, 192, 1)',
                    pointRadius: 5,
                    fill: true,
                    tension: 0.3 // smooth curves
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: false,
                        title: {
                            display: true,
                            text: 'Weight in Kilograms (kg)'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Visit Date'
                        }
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Weight Progression'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.parsed.y + ' kg';
                            }
                        }
                    }
                }
            }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
