<?php
// Use correct path to includes
$rootPath = __DIR__;

require_once $rootPath . '/includes/auth.php';
require_once $rootPath . '/includes/functions.php';

if (!isAuthorized(['admin'])) {
    header("Location: " . $rootPath . "/login.php");
    exit();
}

// Check if mother ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: " . $rootPath . "/mothers_list.php");
    exit();
}

$mother_id = intval($_GET['id']);

// Get mother data
global $pdo;

// Get mother basic information
$mother = $pdo->prepare("
    SELECT m.*, u.username, u.email, u.created_at as user_created
    FROM mothers m 
    LEFT JOIN users u ON m.user_id = u.id 
    WHERE m.id = ?
");
$mother->execute([$mother_id]);
$mother = $mother->fetch(PDO::FETCH_ASSOC);

if (!$mother) {
    header("Location: " . $rootPath . "/mothers_list.php");
    exit();
}

// Get prenatal records
$prenatal_records = $pdo->prepare("
    SELECT * FROM prenatal_records WHERE mother_id = ? ORDER BY visit_date DESC
");
$prenatal_records->execute([$mother_id]);
$prenatal_records = $prenatal_records->fetchAll(PDO::FETCH_ASSOC);

// Get birth records
$birth_records = $pdo->prepare("
    SELECT * FROM birth_records WHERE mother_id = ? ORDER BY birth_date DESC
");
$birth_records->execute([$mother_id]);
$birth_records = $birth_records->fetchAll(PDO::FETCH_ASSOC);

// Get postnatal records
$postnatal_records = $pdo->prepare("
    SELECT pr.*, br.first_name as baby_first_name, br.last_name as baby_last_name
    FROM postnatal_records pr 
    LEFT JOIN birth_records br ON pr.baby_id = br.id 
    WHERE pr.mother_id = ? 
    ORDER BY pr.visit_date DESC
");
$postnatal_records->execute([$mother_id]);
$postnatal_records = $postnatal_records->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Mother Profile - Health Station System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/css/style.css">
    <style>
        .profile-header {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .info-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .section-title {
            border-bottom: 2px solid #1a73e8;
            padding-bottom: 0.5rem;
            margin-bottom: 1rem;
            color: #1a73e8;
        }
    </style>
</head>
<body>
    <?php include_once $rootPath . '/includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include_once $rootPath . '/includes/sidebar.php'; ?>
            
            <main class="main-content">
                <!-- Profile Header -->
                <div class="profile-header">
                    <div class="row align-items-center">
                        <div class="col">
                            <h1 class="h3 mb-1">Mother Profile</h1>
                            <p class="text-muted mb-0"><?php echo htmlspecialchars($mother['first_name'] . ' ' . $mother['last_name']); ?></p>
                        </div>
                        <div class="col-auto">
                            <a href="<?php echo $baseUrl; ?>/pregnant_women.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Back to Pregnant Women
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Basic Information -->
                <div class="info-card">
                    <h4 class="section-title"><i class="fas fa-user me-2"></i>Basic Information</h4>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Full Name:</strong> <?php echo htmlspecialchars($mother['first_name'] . ' ' . $mother['last_name']); ?></p>
                            <p><strong>Date of Birth:</strong> <?php echo !empty($mother['date_of_birth']) ? date('M j, Y', strtotime($mother['date_of_birth'])) : 'N/A'; ?></p>
                            <p><strong>Civil Status:</strong> <?php echo htmlspecialchars($mother['civil_status'] ?? 'N/A'); ?></p>
                            <p><strong>Nationality:</strong> <?php echo htmlspecialchars($mother['nationality'] ?? 'N/A'); ?></p>
                            <p><strong>Religion:</strong> <?php echo htmlspecialchars($mother['religion'] ?? 'N/A'); ?></p>
                            <p><strong>Education:</strong> <?php echo htmlspecialchars($mother['education'] ?? 'N/A'); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Phone:</strong> <?php echo htmlspecialchars($mother['phone'] ?? 'N/A'); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($mother['email'] ?? 'N/A'); ?></p>
                            <p><strong>Address:</strong> <?php echo htmlspecialchars($mother['address'] ?? 'N/A'); ?></p>
                            <p><strong>Blood Type:</strong> <?php echo htmlspecialchars($mother['blood_type'] ?? 'N/A'); ?></p>
                            <p><strong>RH Factor:</strong> <?php echo htmlspecialchars($mother['rh_factor'] ?? 'N/A'); ?></p>
                            <p><strong>Registered:</strong> <?php echo date('M j, Y', strtotime($mother['created_at'])); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Pregnancy Information -->
                <?php if (!empty($mother['lmp']) || !empty($mother['edc'])): ?>
                <div class="info-card">
                    <h4 class="section-title"><i class="fas fa-baby me-2"></i>Pregnancy Information</h4>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Last Menstrual Period (LMP):</strong> <?php echo !empty($mother['lmp']) ? date('M j, Y', strtotime($mother['lmp'])) : 'N/A'; ?></p>
                            <p><strong>Estimated Due Date (EDC):</strong> <?php echo !empty($mother['edc']) ? date('M j, Y', strtotime($mother['edc'])) : 'N/A'; ?></p>
                            <p><strong>Gravida:</strong> <?php echo $mother['gravida'] ?? 'N/A'; ?></p>
                            <p><strong>Para:</strong> <?php echo $mother['para'] ?? 'N/A'; ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Abortions:</strong> <?php echo $mother['abortions'] ?? '0'; ?></p>
                            <p><strong>Living Children:</strong> <?php echo $mother['living_children'] ?? '0'; ?></p>
                            <p><strong>Planned Pregnancy:</strong> <?php echo ($mother['planned_pregnancy'] ?? 'No'); ?></p>
                            <p><strong>First Prenatal Visit:</strong> <?php echo !empty($mother['first_prenatal_visit']) ? date('M j, Y', strtotime($mother['first_prenatal_visit'])) : 'N/A'; ?></p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Husband/Partner Information -->
                <?php if (!empty($mother['husband_first_name'])): ?>
                <div class="info-card">
                    <h4 class="section-title"><i class="fas fa-users me-2"></i>Husband/Partner Information</h4>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Name:</strong> <?php echo htmlspecialchars($mother['husband_first_name'] . ' ' . $mother['husband_last_name']); ?></p>
                            <p><strong>Date of Birth:</strong> <?php echo !empty($mother['husband_date_of_birth']) ? date('M j, Y', strtotime($mother['husband_date_of_birth'])) : 'N/A'; ?></p>
                            <p><strong>Occupation:</strong> <?php echo htmlspecialchars($mother['husband_occupation'] ?? 'N/A'); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Education:</strong> <?php echo htmlspecialchars($mother['husband_education'] ?? 'N/A'); ?></p>
                            <p><strong>Phone:</strong> <?php echo htmlspecialchars($mother['husband_phone'] ?? 'N/A'); ?></p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Medical Information -->
                <?php if (!empty($mother['allergies']) || !empty($mother['medical_conditions'])): ?>
                <div class="info-card">
                    <h4 class="section-title"><i class="fas fa-stethoscope me-2"></i>Medical Information</h4>
                    <div class="row">
                        <?php if (!empty($mother['allergies'])): ?>
                        <div class="col-md-6">
                            <p><strong>Allergies:</strong> <?php echo htmlspecialchars($mother['allergies']); ?></p>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($mother['medical_conditions'])): ?>
                        <div class="col-md-6">
                            <p><strong>Medical Conditions:</strong> <?php echo htmlspecialchars($mother['medical_conditions']); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Prenatal Records -->
                <div class="info-card">
                    <h4 class="section-title"><i class="fas fa-heartbeat me-2"></i>Prenatal Records (<?php echo count($prenatal_records); ?>)</h4>
                    <?php if (!empty($prenatal_records)): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Visit Date</th>
                                        <th>Visit #</th>
                                        <th>Weight (kg)</th>
                                        <th>BP</th>
                                        <th>Gestational Age</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($prenatal_records as $record): ?>
                                    <tr>
                                        <td><?php echo date('M j, Y', strtotime($record['visit_date'])); ?></td>
                                        <td><?php echo $record['visit_number'] ?? 'N/A'; ?></td>
                                        <td><?php echo $record['weight'] ?? 'N/A'; ?></td>
                                        <td><?php echo $record['blood_pressure'] ?? 'N/A'; ?></td>
                                        <td><?php echo $record['gestational_age'] ?? 'N/A'; ?></td>
                                        <td>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No prenatal records found.</p>
                    <?php endif; ?>
                </div>

                <!-- Birth Records -->
                <div class="info-card">
                    <h4 class="section-title"><i class="fas fa-baby-carriage me-2"></i>Birth Records (<?php echo count($birth_records); ?>)</h4>
                    <?php if (!empty($birth_records)): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Baby Name</th>
                                        <th>Birth Date</th>
                                        <th>Gender</th>
                                        <th>Birth Weight</th>
                                        <th>Birth Type</th>
                                        
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($birth_records as $record): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($record['birth_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($record['gender']); ?></td>
                                        <td><?php echo $record['birth_weight'] ?? 'N/A'; ?> kg</td>
                                        <td><?php echo htmlspecialchars($record['type_of_birth'] ?? 'N/A'); ?></td>
                                        <td>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No birth records found.</p>
                    <?php endif; ?>
                </div>

                <!-- Postnatal Records -->
                <div class="info-card">
                    <h4 class="section-title"><i class="fas fa-baby me-2"></i>Postnatal Records (<?php echo count($postnatal_records); ?>)</h4>
                    <?php if (!empty($postnatal_records)): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Visit Date</th>
                                        <th>Visit #</th>
                                        <th>Mother Weight</th>
                                        <th>Baby Weight</th>
                                        <th>BP</th>
                                    
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($postnatal_records as $record): ?>
                                    <tr>
                                        <td><?php echo date('M j, Y', strtotime($record['visit_date'])); ?></td>
                                        <td><?php echo $record['visit_number'] ?? 'N/A'; ?></td>
                                        <td><?php echo $record['weight'] ?? 'N/A'; ?> kg</td>
                                        <td><?php echo $record['baby_weight'] ?? 'N/A'; ?> kg</td>
                                        <td><?php echo $record['blood_pressure'] ?? 'N/A'; ?></td>
                                        <td>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No postnatal records found.</p>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>