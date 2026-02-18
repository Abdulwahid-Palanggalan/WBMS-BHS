<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isAuthorized(['mother'])) {
    header("Location: ../login.php");
    exit();
}

redirectIfNotLoggedIn();

global $pdo;

$userId = $_SESSION['user_id'];

// Get mother's details - with error handling
$mother = $pdo->prepare("
    SELECT m.*, u.first_name, u.last_name, u.email, u.phone 
    FROM mothers m 
    JOIN users u ON m.user_id = u.id 
    WHERE m.user_id = ?
");
$mother->execute([$userId]);
$motherData = $mother->fetch(PDO::FETCH_ASSOC);

// ✅ FIXED: Don't redirect immediately - show warning instead
if (!$motherData) {
    // Set flag to show registration prompt but DON'T redirect
    $showRegistrationPrompt = true;
    
    // Create empty mother array to prevent errors
    $mother = [
        'first_name' => $_SESSION['first_name'],
        'last_name' => $_SESSION['last_name'],
        'email' => $_SESSION['email'] ?? '',
        'phone' => $_SESSION['phone'] ?? '',
        'gravida' => 0,
        'para' => 0,
        'id' => null
    ];
    
    // Set counts to zero
    $prenatalCount = 0;
    $postnatalCount = 0;
    $birthRecords = [];
    $weeksPregnant = 0;
    $nextAppointment = null;
    $isPregnant = false;
    $lmpDate = null;
    $edcDate = null;
    $pregnancyData = null;
} else {
    $mother = $motherData;
    $showRegistrationPrompt = false;
    
    // Check if mother is pregnant based on pregnancy_details table
    $isPregnant = false;
    $lmpDate = null;
    $edcDate = null;
    $pregnancyData = null;
    
    // Check pregnancy_details table
    $pregnancyStmt = $pdo->prepare("
        SELECT lmp, edc, gravida, para 
        FROM pregnancy_details 
        WHERE mother_id = ? 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $pregnancyStmt->execute([$mother['id']]);
    $pregnancyData = $pregnancyStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($pregnancyData) {
        $lmpDate = $pregnancyData['lmp'];
        $edcDate = $pregnancyData['edc'];
        
        // Check if currently pregnant (LMP within last 42 weeks)
        if (!empty($lmpDate) && $lmpDate != '0000-00-00') {
            try {
                $lmp = new DateTime($lmpDate);
                $now = new DateTime();
                $diff = $lmp->diff($now);
                $weeksPregnant = floor($diff->days / 7);
                
                // If less than 42 weeks, consider pregnant
                if ($weeksPregnant <= 42) {
                    $isPregnant = true;
                } else {
                    $isPregnant = false;
                    $weeksPregnant = 0;
                }
            } catch (Exception $e) {
                error_log("Invalid LMP date format: " . $lmpDate);
                $isPregnant = false;
                $weeksPregnant = 0;
            }
        }
    } else {
        $isPregnant = false;
        $weeksPregnant = 0;
    }
    
    // Update mother's gravida and para from pregnancy_details if available
    if ($pregnancyData) {
        $mother['gravida'] = $pregnancyData['gravida'] ?? $mother['gravida'];
        $mother['para'] = $pregnancyData['para'] ?? $mother['para'];
    }
    
    // Get prenatal records count for stats
    $prenatalCountStmt = $pdo->prepare("SELECT COUNT(*) as count FROM prenatal_records WHERE mother_id = ?");
    $prenatalCountStmt->execute([$mother['id']]);
    $prenatalCount = $prenatalCountStmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Get birth records
    $birthRecordsStmt = $pdo->prepare("
        SELECT * FROM birth_records 
        WHERE mother_id = ? 
        ORDER BY birth_date DESC
    ");
    $birthRecordsStmt->execute([$mother['id']]);
    $birthRecords = $birthRecordsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get postnatal records count for stats
    $postnatalCountStmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM postnatal_records pr 
        JOIN birth_records br ON pr.baby_id = br.id 
        WHERE br.mother_id = ?
    ");
    $postnatalCountStmt->execute([$mother['id']]);
    $postnatalCount = $postnatalCountStmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Get next appointment
    $nextAppointment = null;
    if ($isPregnant) {
        $nextAppointmentStmt = $pdo->prepare("
            SELECT * FROM prenatal_records 
            WHERE mother_id = ? AND visit_date > CURDATE() 
            ORDER BY visit_date ASC 
            LIMIT 1
        ");
        $nextAppointmentStmt->execute([$mother['id']]);
        $nextAppointment = $nextAppointmentStmt->fetch(PDO::FETCH_ASSOC);
    }
}

// Set base URL for links
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$script_dir = dirname($_SERVER['SCRIPT_NAME']);
$baseUrl = $protocol . "://" . $host . $script_dir;
$baseUrl = rtrim($baseUrl, '/');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mother Dashboard - Health Station System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        :root {
            --primary: #9c27b0;
            --primary-light: #f3e5f5;
            --secondary: #2196f3;
            --accent: #4caf50;
            --light: #f8f9fa;
            --dark: #2c3e50;
            --danger: #f44336;
            --warning: #ff9800;
            --info: #17a2b8;
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
        
        .stat-card.children { border-left-color: var(--secondary); }
        .stat-card.prenatal { border-left-color: var(--primary); }
        .stat-card.postnatal { border-left-color: var(--accent); }
        .stat-card.weeks { border-left-color: var(--danger); }
        .stat-card.not-pregnant { border-left-color: #6c757d; }
        
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
        
        .stat-card.children .stat-icon { background: #e3f2fd; color: var(--secondary); }
        .stat-card.prenatal .stat-icon { background: var(--primary-light); color: var(--primary); }
        .stat-card.postnatal .stat-icon { background: #e8f5e8; color: var(--accent); }
        .stat-card.weeks .stat-icon { background: #ffebee; color: var(--danger); }
        .stat-card.not-pregnant .stat-icon { background: #f8f9fa; color: #6c757d; }
        
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
        
        .quick-action {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 1.25rem;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        
        .quick-action:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            color: inherit;
            text-decoration: none;
        }
        
        .quick-action i {
            font-size: 1.5rem;
            margin-bottom: 0.75rem;
            display: block;
        }
        
        .appointment-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .profile-section {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .progress-container {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .child-card {
            background: white;
            border-radius: 10px;
            padding: 1rem;
            border-left: 4px solid var(--secondary);
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 1rem;
        }
        
        .limited-feature {
            opacity: 0.6;
            position: relative;
        }
        
        .limited-feature::after {
            content: "Complete profile to access";
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.8rem;
            opacity: 0;
            transition: opacity 0.3s;
            pointer-events: none;
        }
        
        .limited-feature:hover::after {
            opacity: 1;
        }
        
        .pregnancy-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-left: 10px;
        }
        
        .pregnant-badge {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .not-pregnant-badge {
            background-color: #f8f9fa;
            color: #6c757d;
            border: 1px solid #e9ecef;
        }
        
        .pregnancy-info-box {
            background: linear-gradient(135deg, #fdf2ff 0%, #f3e5f5 100%);
            border-radius: 10px;
            padding: 1rem;
            border-left: 4px solid var(--primary);
            margin-bottom: 1rem;
        }
        
        .due-date-box {
            background: linear-gradient(135deg, #e8f5e8 0%, #d4edda 100%);
            border-radius: 10px;
            padding: 1rem;
            border-left: 4px solid var(--accent);
        }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/../includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <!-- Registration Prompt -->
                <?php if (isset($showRegistrationPrompt) && $showRegistrationPrompt): ?>
                <div class="alert alert-warning alert-dismissible fade show mb-4" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                        <div class="flex-grow-1">
                            <h5 class="alert-heading mb-2">Complete Your Mother Profile</h5>
                            <p class="mb-2">To access all features like prenatal tracking and birth registration, please complete your mother profile first.</p>
                            <div class="mt-2">
                               <a href="/kibenes-ebirth/forms/mother_self_registration.php" class="btn btn-primary me-2">
    <i class="fas fa-user-plus me-1"></i>Complete Profile Now
</a>

                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="alert">
                                    I'll Do It Later
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Dashboard Header -->
                <div class="dashboard-header">
                    <div class="row align-items-center">
                        <div class="col">
                            <h1 class="h3 mb-2">Welcome, <?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?>! 👩‍👧‍👦</h1>
                            <p class="mb-0 opacity-75">Today is <?php echo date('l, F j, Y'); ?> • Your Maternal Health Dashboard</p>
                            
                            <!-- Pregnancy Status Badge -->
                            <div class="mt-2">
                                <?php if ($showRegistrationPrompt): ?>
                                    <span class="pregnancy-badge not-pregnant-badge">
                                        <i class="fas fa-user me-1"></i>Profile Incomplete
                                    </span>
                                <?php elseif ($isPregnant): ?>
                                    <span class="pregnancy-badge pregnant-badge">
                                        <i class="fas fa-baby me-1"></i>Currently Pregnant
                                    </span>
                                    <span class="ms-2">
                                        <strong><?php echo $weeksPregnant; ?> weeks</strong>
                                        <?php if (!empty($edcDate) && $edcDate != '0000-00-00'): ?>
                                        • Due date: <?php echo date('F j, Y', strtotime($edcDate)); ?>
                                        <?php endif; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="pregnancy-badge not-pregnant-badge">
                                        <i class="fas fa-user me-1"></i>Not Currently Pregnant
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <?php if (!$showRegistrationPrompt && !$isPregnant): ?>
                            <button class="btn btn-outline-primary btn-sm" onclick="location.href='/kibenes-ebirth/forms/register_pregnancy.php'">
                                <i class="fas fa-plus me-1"></i>Register Pregnancy
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="row mb-4">
                    <div class="col-md-3 col-6 mb-3">
                        <div class="stat-card children">
                            <div class="stat-icon">
                                <i class="fas fa-child"></i>
                            </div>
                            <div class="stat-number"><?php echo count($birthRecords); ?></div>
                            <div class="stat-label">My Children</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6 mb-3">
                        <div class="stat-card prenatal">
                            <div class="stat-icon">
                                <i class="fas fa-heartbeat"></i>
                            </div>
                            <div class="stat-number"><?php echo $prenatalCount; ?></div>
                            <div class="stat-label">Prenatal Visits</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6 mb-3">
                        <div class="stat-card postnatal">
                            <div class="stat-icon">
                                <i class="fas fa-baby-carriage"></i>
                            </div>
                            <div class="stat-number"><?php echo $postnatalCount; ?></div>
                            <div class="stat-label">Postnatal Visits</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6 mb-3">
                        <div class="stat-card <?php echo $isPregnant ? 'weeks' : 'not-pregnant'; ?>">
                            <div class="stat-icon">
                                <?php if ($showRegistrationPrompt): ?>
                                    <i class="fas fa-user"></i>
                                <?php elseif ($isPregnant): ?>
                                    <i class="fas fa-baby"></i>
                                <?php else: ?>
                                    <i class="fas fa-user"></i>
                                <?php endif; ?>
                            </div>
                            <div class="stat-number">
                                <?php if ($showRegistrationPrompt): ?>
                                    N/A
                                <?php elseif ($isPregnant): ?>
                                    <?php echo $weeksPregnant; ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </div>
                            <div class="stat-label">
                                <?php if ($showRegistrationPrompt): ?>
                                    Complete Profile
                                <?php elseif ($isPregnant): ?>
                                    Weeks Pregnant
                                <?php else: ?>
                                    Not Pregnant
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Left Column -->
                    <div class="col-lg-8">
                        <!-- Pregnancy Information Box -->
                        <?php if ($isPregnant): ?>
                        <div class="pregnancy-info-box mb-4">
                            <div class="row align-items-center">
                                <div class="col">
                                    <h5 class="mb-1"><i class="fas fa-baby me-2 text-primary"></i>Current Pregnancy Details</h5>
                                    <p class="mb-0">
                                        <strong><?php echo $weeksPregnant; ?> weeks pregnant</strong>
                                        <?php if (!empty($lmpDate) && $lmpDate != '0000-00-00'): ?>
                                        • LMP: <?php echo date('M j, Y', strtotime($lmpDate)); ?>
                                        <?php endif; ?>
                                    </p>
                                    <?php if ($pregnancyData): ?>
                                    <p class="mb-0 mt-1">
                                        <small>
                                            <i class="fas fa-history me-1"></i>
                                            Gravida: <?php echo $pregnancyData['gravida'] ?? $mother['gravida']; ?> • 
                                            Para: <?php echo $pregnancyData['para'] ?? $mother['para']; ?>
                                        </small>
                                    </p>
                                    <?php endif; ?>
                                </div>
                                <div class="col-auto">
                                    <!-- <span class="badge bg-primary">
                                        <?php echo $mother['gravida']; ?>th Pregnancy -->
                                    </span>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Quick Actions -->
                        <div class="section-card mb-4">
                            <div class="section-header">
                                <h5><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                            </div>
                            <div class="section-body">
                                <div class="row g-3">
                                    <div class="col-md-3 col-6">
                                        <?php if ($showRegistrationPrompt): ?>
                                        <div class="quick-action limited-feature" style="cursor: not-allowed;">
                                            <i class="fas fa-baby text-muted"></i>
                                            <div class="fw-semibold small text-muted">Register Birth</div>
                                        </div>
                                        <?php else: ?>
                                        <a href="/kibenes-ebirth/forms/birth_registration.php" class="quick-action">
    <i class="fas fa-baby text-success"></i>
    <div class="fw-semibold small">Register Birth</div>
</a>

                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-3 col-6">
                                        <a href="/kibenes-ebirth/profile.php" class="quick-action">
    <i class="fas fa-user-edit text-primary"></i>
    <div class="fw-semibold small">Update Profile</div>
</a>

                                    </div>
                                    <div class="col-md-3 col-6">
                                        <?php if ($showRegistrationPrompt): ?>
                                        <div class="quick-action limited-feature" style="cursor: not-allowed;">
                                            <i class="fas fa-clipboard-list text-muted"></i>
                                            <div class="fw-semibold small text-muted">View Prenatal</div>
                                        </div>
                                        <?php else: ?>
                                        <a href="#" class="quick-action" data-bs-toggle="modal" data-bs-target="#prenatalModal">
                                            <i class="fas fa-clipboard-list text-info"></i>
                                            <div class="fw-semibold small">View Prenatal</div>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-3 col-6">
                                        <?php if ($showRegistrationPrompt): ?>
                                        <div class="quick-action limited-feature" style="cursor: not-allowed;">
                                            <i class="fas fa-baby text-muted"></i>
                                            <div class="fw-semibold small text-muted">View Postnatal</div>
                                        </div>
                                        <?php else: ?>
                                        <a href="#" class="quick-action" data-bs-toggle="modal" data-bs-target="#postnatalModal">
                                            <i class="fas fa-baby text-warning"></i>
                                            <div class="fw-semibold small">View Postnatal</div>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Next Appointment -->
                        <?php if ($nextAppointment): ?>
                        <div class="appointment-card">
                            <div class="row align-items-center">
                                <div class="col">
                                    <h5 class="mb-1"><i class="fas fa-calendar-check me-2"></i>Next Appointment</h5>
                                    <p class="mb-0">
                                        <?php echo date('l, F j, Y', strtotime($nextAppointment['visit_date'])); ?> 
                                        at <?php echo !empty($nextAppointment['visit_time']) ? $nextAppointment['visit_time'] : 'Scheduled Time'; ?>
                                    </p>
                                    <small>Visit #<?php echo !empty($nextAppointment['visit_number']) ? $nextAppointment['visit_number'] : 'Next'; ?></small>
                                </div>
                                <div class="col-auto">
                                    <span class="badge bg-light text-dark">
                                        <i class="fas fa-clock me-1"></i>
                                        <?php 
                                        $daysUntil = date_diff(new DateTime(), new DateTime($nextAppointment['visit_date']))->days;
                                        echo $daysUntil == 0 ? 'Today' : ($daysUntil == 1 ? 'Tomorrow' : $daysUntil . ' days');
                                        ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- My Children Section -->
                        <div class="section-card mb-4">
                            <div class="section-header">
                                <h5><i class="fas fa-child me-2"></i>My Children</h5>
                            </div>
                            <div class="section-body">
                                <?php if (!empty($birthRecords)): ?>
                                    <div class="row">
                                        <?php foreach ($birthRecords as $record): 
                                            $age = '';
                                            if (!empty($record['birth_date']) && $record['birth_date'] != '0000-00-00') {
                                                $birthDate = new DateTime($record['birth_date']);
                                                $now = new DateTime();
                                                $interval = $birthDate->diff($now);
                                                if ($interval->y > 0) {
                                                    $age = $interval->y . ' years old';
                                                } elseif ($interval->m > 0) {
                                                    $age = $interval->m . ' months old';
                                                } else {
                                                    $age = $interval->d . ' days old';
                                                }
                                            }
                                        ?>
                                        <div class="col-md-6 mb-3">
                                            <div class="child-card">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></h6>
                                                        <p class="text-muted mb-1 small">
                                                            <i class="fas fa-birthday-cake me-1"></i>
                                                            Born <?php echo date('M j, Y', strtotime($record['birth_date'])); ?>
                                                        </p>
                                                        <p class="text-muted mb-1 small">
                                                            <i class="fas fa-clock me-1"></i>
                                                            <?php echo $age ?: 'Age not available'; ?>
                                                        </p>
                                                    </div>
                                                    <div class="text-end">
                                                        <span class="badge bg-<?php echo $record['gender'] == 'male' ? 'primary' : 'danger'; ?>">
                                                            <?php echo ucfirst($record['gender']); ?>
                                                        </span>
                                                        <br>
                                                        <small class="text-muted">
                                                            <?php echo $record['birth_weight'] ? $record['birth_weight'] . ' kg' : 'Weight N/A'; ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <i class="fas fa-child"></i>
                                        <h5>No Children Registered</h5>
                                        <p class="text-muted">You haven't registered any children yet.</p>
                                        <?php if (!$showRegistrationPrompt): ?>
                                       <a href="/kibenes-ebirth/forms/birth_registration.php" class="btn btn-primary">
    <i class="fas fa-plus me-2"></i>Register First Child
</a>

                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div class="col-lg-4">
                        <!-- Profile Information -->
                        <div class="profile-section mb-4">
                            <div class="text-center mb-3">
                                <div class="bg-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-2" 
                                     style="width: 80px; height: 80px;">
                                    <i class="fas fa-user fa-2x text-white"></i>
                                </div>
                                <h5><?php echo htmlspecialchars($mother['first_name'] . ' ' . $mother['last_name']); ?></h5>
                                <p class="text-muted mb-0">Mother</p>
                                
                                <!-- Pregnancy Status in Profile -->
                                <div class="mt-2">
                                    <?php if ($showRegistrationPrompt): ?>
                                        <span class="badge bg-warning">Profile Incomplete</span>
                                    <?php elseif ($isPregnant): ?>
                                        <span class="badge bg-success">Pregnant</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Not Pregnant</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="row text-center mb-3">
                                <div class="col-6">
                                    <div class="border-end">
                                        <h6 class="mb-1"><?php echo !empty($mother['gravida']) ? $mother['gravida'] : '0'; ?></h6>
                                        <small class="text-muted">Pregnancies</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <h6 class="mb-1"><?php echo !empty($mother['para']) ? $mother['para'] : '0'; ?></h6>
                                    <small class="text-muted">Live Births</small>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <p class="mb-2"><i class="fas fa-phone text-primary me-2"></i><?php echo !empty($mother['phone']) ? htmlspecialchars($mother['phone']) : 'Not specified'; ?></p>
                                <p class="mb-2"><i class="fas fa-envelope text-primary me-2"></i><?php echo !empty($mother['email']) ? htmlspecialchars($mother['email']) : 'Not specified'; ?></p>
                                <?php if (!empty($mother['address'])): ?>
                                <p class="mb-0"><i class="fas fa-home text-primary me-2"></i><?php echo htmlspecialchars($mother['address']); ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="text-center">
                               <a href="/kibenes-ebirth/profile.php" class="btn btn-primary btn-sm">
    <i class="fas fa-edit me-2"></i>Update Profile
</a>
                                <?php if (!$showRegistrationPrompt && !$isPregnant): ?>
                                <button class="btn btn-outline-success btn-sm mt-1" onclick="location.href='/kibenes-ebirth/forms/register_pregnancy.php'">
                                    <i class="fas fa-plus me-1"></i>Register Pregnancy
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Pregnancy Progress -->
                        <?php if ($isPregnant && $weeksPregnant > 0): ?>
                        <div class="progress-container mb-4">
                            <h6 class="text-center mb-3">Pregnancy Progress</h6>
                            <div class="text-center mb-3">
                                <h2 class="text-primary"><?php echo $weeksPregnant; ?> weeks</h2>
                                <p class="text-muted mb-0">Completed</p>
                            </div>
                            <div class="progress mb-3" style="height: 20px;">
                                <?php 
                                $progress = min(100, ($weeksPregnant / 40) * 100);
                                ?>
                                <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo $progress; ?>%" 
                                     aria-valuenow="<?php echo $progress; ?>" aria-valuemin="0" aria-valuemax="100">
                                    <?php echo round($progress); ?>%
                                </div>
                            </div>
                            <div class="text-center">
                                <small class="text-muted">
                                    <?php echo 40 - $weeksPregnant; ?> weeks remaining
                                </small>
                                <?php if (!empty($edcDate) && $edcDate != '0000-00-00'): ?>
                                <br>
                                <small class="text-muted">
                                    Due: <?php echo date('M j, Y', strtotime($edcDate)); ?>
                                </small>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Due Date Information -->
                        <?php if (!empty($edcDate) && $edcDate != '0000-00-00'): ?>
                        <div class="due-date-box mb-4">
                            <div class="text-center">
                                <i class="fas fa-calendar-alt fa-2x text-success mb-2"></i>
                                <h6>Expected Due Date</h6>
                                <h4 class="text-success mb-1"><?php echo date('M j, Y', strtotime($edcDate)); ?></h4>
                                <small class="text-muted">
                                    <?php 
                                    $edcDateTime = new DateTime($edcDate);
                                    $now = new DateTime();
                                    $daysLeft = $edcDateTime->diff($now)->days;
                                    echo $daysLeft . ' days from today';
                                    ?>
                                </small>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php elseif (!$showRegistrationPrompt && !$isPregnant): ?>
                        <div class="progress-container mb-4">
                            <div class="text-center py-4">
                                <div class="mb-3">
                                    <i class="fas fa-user fa-3x text-muted"></i>
                                </div>
                                <h6>Not Currently Pregnant</h6>
                                <p class="text-muted small mb-3">You can register a new pregnancy when needed</p>
                                <button class="btn btn-outline-primary btn-sm" onclick="location.href='/kibenes-ebirth/forms/register_pregnancy.php'">
                                    <i class="fas fa-plus me-1"></i>Register Pregnancy
                                </button>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Health Summary -->
                        <div class="profile-section">
                            <h6 class="mb-3">Health Summary</h6>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Pregnancy Status:</span>
                                    <strong>
                                        <?php if ($showRegistrationPrompt): ?>
                                            <span class="text-warning">Profile Incomplete</span>
                                        <?php elseif ($isPregnant): ?>
                                            <span class="text-success">Pregnant</span>
                                        <?php else: ?>
                                            <span class="text-muted">Not Pregnant</span>
                                        <?php endif; ?>
                                    </strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Prenatal Visits:</span>
                                    <strong><?php echo $prenatalCount; ?></strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Postnatal Visits:</span>
                                    <strong><?php echo $postnatalCount; ?></strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Children:</span>
                                    <strong><?php echo count($birthRecords); ?></strong>
                                </div>
                                <?php if ($isPregnant && !empty($lmpDate) && $lmpDate != '0000-00-00'): ?>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Last Period:</span>
                                    <strong><?php echo date('M j, Y', strtotime($lmpDate)); ?></strong>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Prenatal Modal Container -->
    <?php if (!$showRegistrationPrompt): ?>
    <div class="modal fade" id="prenatalModal" tabindex="-1" aria-labelledby="prenatalModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="prenatalModalLabel">
                        <i class="fas fa-heartbeat me-2"></i>Loading Prenatal Records...
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3">Loading prenatal records...</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Postnatal Modal Container -->
    <div class="modal fade" id="postnatalModal" tabindex="-1" aria-labelledby="postnatalModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="postnatalModalLabel">
                        <i class="fas fa-baby-carriage me-2"></i>Loading Postnatal Records...
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center py-5">
                    <div class="spinner-border text-success" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3">Loading postnatal records...</p>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh every 5 minutes
        setTimeout(function() {
            window.location.reload();
        }, 300000);

        // Load modal content via AJAX
        document.addEventListener('DOMContentLoaded', function() {
            // Prenatal Modal
            const prenatalModal = document.getElementById('prenatalModal');
            if (prenatalModal) {
                prenatalModal.addEventListener('show.bs.modal', function() {
                    loadPrenatalRecords();
                });
            }

            // Postnatal Modal
            const postnatalModal = document.getElementById('postnatalModal');
            if (postnatalModal) {
                postnatalModal.addEventListener('show.bs.modal', function() {
                    loadPostnatalRecords();
                });
            }
        });

        function loadPrenatalRecords() {
            fetch('<?php echo $baseUrl; ?>/ajax/get_mother_prenatal_records.php')
                .then(response => response.text())
                .then(data => {
                    document.querySelector('#prenatalModal .modal-content').innerHTML = data;
                })
                .catch(error => {
                    console.error('Error loading prenatal records:', error);
                    document.querySelector('#prenatalModal .modal-content').innerHTML = `
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title">Error</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-danger">Failed to load prenatal records. Please try again.</div>
                        </div>
                    `;
                });
        }

        function loadPostnatalRecords() {
            fetch('<?php echo $baseUrl; ?>/ajax/get_mother_postnatal_records.php')
                .then(response => response.text())
                .then(data => {
                    document.querySelector('#postnatalModal .modal-content').innerHTML = data;
                })
                .catch(error => {
                    console.error('Error loading postnatal records:', error);
                    document.querySelector('#postnatalModal .modal-content').innerHTML = `
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title">Error</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-danger">Failed to load postnatal records. Please try again.</div>
                        </div>
                    `;
                });
        }
    </script>
</body>
</html>