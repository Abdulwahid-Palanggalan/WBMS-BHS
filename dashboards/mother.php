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

// Get mother's details
$mother = $pdo->prepare("
    SELECT m.*, u.first_name, u.last_name, u.email, u.phone 
    FROM mothers m 
    JOIN users u ON m.user_id = u.id 
    WHERE m.user_id = ?
");
$mother->execute([$userId]);
$motherData = $mother->fetch(PDO::FETCH_ASSOC);

if (!$motherData) {
    $showRegistrationPrompt = true;
    $mother = [
        'first_name' => $_SESSION['first_name'],
        'last_name' => $_SESSION['last_name'],
        'id' => null
    ];
} else {
    $mother = $motherData;
    $showRegistrationPrompt = false;
    
    // Check pregnancy status
    $pregnancyStmt = $pdo->prepare("
        SELECT lmp, edc, gravida, para 
        FROM pregnancy_details 
        WHERE mother_id = ? 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $pregnancyStmt->execute([$mother['id']]);
    $pregnancyData = $pregnancyStmt->fetch(PDO::FETCH_ASSOC);
    
    $isPregnant = false;
    $weeksPregnant = 0;
    if ($pregnancyData && !empty($pregnancyData['lmp'])) {
        $lmp = new DateTime($pregnancyData['lmp']);
        $now = new DateTime();
        $diff = $lmp->diff($now);
        $weeksPregnant = floor($diff->days / 7);
        if ($weeksPregnant <= 42) $isPregnant = true;
    }

    // Stats
    $prenatalCount = $pdo->query("SELECT COUNT(*) FROM prenatal_records WHERE mother_id = " . (int)$mother['id'])->fetchColumn() ?: 0;
    
    $birthRecords = $pdo->prepare("SELECT * FROM birth_records WHERE mother_id = ? ORDER BY birth_date DESC");
    $birthRecords->execute([$mother['id']]);
    $birthRecords = $birthRecords->fetchAll(PDO::FETCH_ASSOC);

    $postnatalCount = $pdo->prepare("
        SELECT COUNT(*) FROM postnatal_records pr 
        JOIN birth_records br ON pr.baby_id = br.id 
        WHERE br.mother_id = ?
    ");
    $postnatalCount->execute([$mother['id']]);
    $postnatalCount = $postnatalCount->fetchColumn() ?: 0;

    $nextAppointment = null;
    if ($isPregnant) {
        $nextAppStmt = $pdo->prepare("
            SELECT * FROM prenatal_records 
            WHERE mother_id = ? AND visit_date >= CURDATE() 
            ORDER BY visit_date ASC 
            LIMIT 1
        ");
        $nextAppStmt->execute([$mother['id']]);
        $nextAppointment = $nextAppStmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mother's Portal - Health Station System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .sos-btn {
            background: var(--danger);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 50px;
            font-weight: 700;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.4);
            transition: all 0.3s ease;
        }
        .sos-btn:hover {
            transform: scale(1.05);
            background: #dc2626;
            color: white;
        }
        .id-card {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            color: white;
            border-radius: 20px;
            padding: 24px;
            position: relative;
            overflow: hidden;
        }
        .id-card::after {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 150px;
            height: 150px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
        }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/../includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
            
            <main class="main-content">
                <!-- Registration Alert -->
                <?php if ($showRegistrationPrompt): ?>
                <div class="alert alert-primary border-0 shadow-sm rounded-xl p-4 mb-4">
                    <div class="d-flex align-items-center">
                        <div class="bg-white text-primary rounded-circle p-3 me-4">
                            <i class="fas fa-sparkles fa-2x"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1">Welcome to your Health Portal!</h5>
                            <p class="mb-3">Complete your profile to unlock pregnancy tracking, digital records, and health reminders.</p>
                            <a href="../forms/mother_self_registration.php" class="btn btn-primary px-4">Complete Profile Now</a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="fw-bold mb-1">Hello, <?= htmlspecialchars($mother['first_name']); ?>! 👋</h2>
                        <p class="text-muted small mb-0">Your personal health companion</p>
                    </div>
                    <div class="d-flex align-items-center">
                        <button class="sos-btn me-3" id="sosTrigger">
                            <i class="fas fa-exclamation-triangle me-2"></i>SOS EMERGENCY
                        </button>
                        <?php if ($isPregnant): ?>
                        <span class="badge bg-primary-light text-primary px-4 py-2 rounded-pill fw-bold d-none d-md-inline">
                            <i class="fas fa-baby me-2"></i><?= $weeksPregnant; ?> Weeks Pregnant
                        </span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Bento Stats -->
                <div class="row g-4 mb-4">
                    <div class="col-xl-3 col-md-6">
                        <div class="card border-0 shadow-sm stats-card" style="border-top: 4px solid var(--secondary) !important;">
                            <div class="stats-icon" style="background: var(--primary-light); color: var(--secondary);">
                                <i class="fas fa-child"></i>
                            </div>
                            <span class="stats-number"><?= count($birthRecords); ?></span>
                            <span class="stats-label">My Children</span>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card border-0 shadow-sm stats-card" style="border-top: 4px solid var(--primary) !important;">
                            <div class="stats-icon" style="background: var(--primary-light); color: var(--primary);">
                                <i class="fas fa-clipboard-heart"></i>
                            </div>
                            <span class="stats-number"><?= $prenatalCount; ?></span>
                            <span class="stats-label">Prenatal Visits</span>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card border-0 shadow-sm stats-card" style="border-top: 4px solid var(--accent) !important;">
                            <div class="stats-icon" style="background: #ecfdf5; color: var(--accent);">
                                <i class="fas fa-house-medical-check"></i>
                            </div>
                            <span class="stats-number"><?= $postnatalCount; ?></span>
                            <span class="stats-label">Postnatal Checks</span>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card border-0 shadow-sm stats-card" style="border-top: 4px solid var(--warning) !important;">
                            <div class="stats-icon" style="background: #fffbeb; color: var(--warning);">
                                <i class="fas fa-bell"></i>
                            </div>
                            <span class="stats-number"><?= $nextAppointment ? '1' : '0'; ?></span>
                            <span class="stats-label">Active Reminders</span>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <!-- Health Journey Timeline (Bento Large) -->
                    <div class="col-lg-8">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header border-0 bg-transparent py-4">
                                <h5 class="fw-bold mb-0">Health Journey Timeline</h5>
                                <p class="text-muted small mb-0">Track your pregnancy milestones</p>
                            </div>
                            <div class="card-body">
                                <?php if ($isPregnant): ?>
                                    <div class="journey-timeline">
                                        <div class="timeline-track"></div>
                                        <div class="timeline-progress" style="width: <?= min(100, ($weeksPregnant / 40) * 100); ?>%;"></div>
                                        <div class="timeline-points">
                                            <div class="timeline-point completed">
                                                <div class="point-marker"></div>
                                                <div class="point-label">LMP</div>
                                                <small class="text-muted d-block"><?= date('M j', strtotime($pregnancyData['lmp'])); ?></small>
                                            </div>
                                            <div class="timeline-point <?= $weeksPregnant >= 12 ? 'completed' : 'active'; ?>">
                                                <div class="point-marker"></div>
                                                <div class="point-label">Tri 1</div>
                                                <small class="text-muted d-block">Week 12</small>
                                            </div>
                                            <div class="timeline-point <?= $weeksPregnant >= 26 ? 'completed' : ($weeksPregnant >= 13 ? 'active' : ''); ?>">
                                                <div class="point-marker"></div>
                                                <div class="point-label">Tri 2</div>
                                                <small class="text-muted d-block">Week 26</small>
                                            </div>
                                            <div class="timeline-point <?= $weeksPregnant >= 40 ? 'completed' : ($weeksPregnant >= 27 ? 'active' : ''); ?>">
                                                <div class="point-marker"></div>
                                                <div class="point-label">Tri 3</div>
                                                <small class="text-muted d-block">Week 40</small>
                                            </div>
                                            <div class="timeline-point">
                                                <div class="point-marker"></div>
                                                <div class="point-label">EDC</div>
                                                <small class="text-muted d-block"><?= date('M j', strtotime($pregnancyData['edc'])); ?></small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-4 p-3 bg-light rounded-xl border">
                                        <p class="mb-0 small"><i class="fas fa-info-circle text-primary me-2"></i> You are in your <strong>Trimester <?= $weeksPregnant <= 12 ? '1' : ($weeksPregnant <= 26 ? '2' : '3'); ?></strong>. Make sure to attend your upcoming checkup!</p>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-heart-pulse fa-3x text-light mb-3"></i>
                                        <p class="text-muted">No active pregnancy timeline to show.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Digital ID Card (Bento Small) -->
                    <div class="col-lg-4">
                        <div class="id-card h-100 shadow-lg position-relative">
                            <div class="d-flex justify-content-between align-items-start mb-4">
                                <div>
                                    <h6 class="text-uppercase small opacity-50 mb-1 fw-bold">Electronic Health ID</h6>
                                    <h4 class="fw-bold mb-0">ID: BHS-<?= str_pad($mother['id'] ?? 0, 5, '0', STR_PAD_LEFT) ?></h4>
                                </div>
                                <div class="bg-white rounded p-1">
                                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=60x60&data=MOTHER-<?= $mother['id'] ?>" alt="QR Code" width="60">
                                </div>
                            </div>
                            <div class="mb-4">
                                <h5 class="fw-bold mb-1"><?= htmlspecialchars($mother['first_name'] . ' ' . $mother['last_name']) ?></h5>
                                <p class="small opacity-75 mb-0"><i class="fas fa-phone me-2"></i> <?= htmlspecialchars($mother['phone'] ?? 'N/A') ?></p>
                            </div>
                            <div class="mt-auto">
                                <div class="d-flex justify-content-between align-items-end">
                                    <small class="opacity-50">ISSUED BY BARANGAY HEALTH STATION</small>
                                    <i class="fas fa-shield-heart fa-2x opacity-20"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4">
                    <!-- Quick Records Access -->
                    <div class="col-lg-4">
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-header border-0 bg-transparent py-4">
                                <h5 class="fw-bold mb-0">Record Dashboard</h5>
                            </div>
                            <div class="card-body pt-0">
                                <div class="list-group list-group-flush">
                                    <a href="#" class="list-group-item list-group-item-action border-0 px-0 py-3 d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#prenatalModal">
                                        <div class="bg-primary-light text-primary rounded-pill p-2 me-3"><i class="fas fa-file-medical"></i></div>
                                        <div class="flex-grow-1">
                                            <span class="d-block fw-bold small">Prenatal Records</span>
                                            <small class="text-muted"><?= $prenatalCount; ?> visits on file</small>
                                        </div>
                                        <i class="fas fa-chevron-right text-muted small"></i>
                                    </a>
                                    <a href="#" class="list-group-item list-group-item-action border-0 px-0 py-3 d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#postnatalModal">
                                        <div class="bg-success-light text-success rounded-pill p-2 me-3" style="background: #ecfdf5; color: #10b981;"><i class="fas fa-file-prescription"></i></div>
                                        <div class="flex-grow-1">
                                            <span class="d-block fw-bold small">Postnatal Checks</span>
                                            <small class="text-muted"><?= $postnatalCount; ?> records found</small>
                                        </div>
                                        <i class="fas fa-chevron-right text-muted small"></i>
                                    </a>
                                    <a href="../forms/birth_registration.php" class="list-group-item list-group-item-action border-0 px-0 py-3 d-flex align-items-center">
                                        <div class="bg-warning-light text-warning rounded-pill p-2 me-3" style="background: #fffbeb; color: #f59e0b;"><i class="fas fa-scroll"></i></div>
                                        <div class="flex-grow-1">
                                            <span class="d-block fw-bold small">Birth Registration</span>
                                            <small class="text-muted">Register a new delivery</small>
                                        </div>
                                        <i class="fas fa-chevron-right text-muted small"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- My Children -->
                    <div class="col-lg-8">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header border-0 bg-transparent py-4 d-flex justify-content-between align-items-center">
                                <h5 class="fw-bold mb-0">My Children</h5>
                                <button class="btn btn-sm btn-primary" onclick="location.href='../forms/birth_registration.php'">Add Record</button>
                            </div>
                            <div class="card-body pt-0">
                                <?php if (!empty($birthRecords)): ?>
                                    <div class="row g-3">
                                        <?php foreach ($birthRecords as $record): ?>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded-xl hover-shadow transition-all bg-light">
                                                    <div class="d-flex align-items-center mb-2">
                                                        <div class="rounded-circle bg-<?= $record['gender'] == 'male' ? 'primary' : 'danger'; ?> text-white d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                                            <i class="fas fa-child"></i>
                                                        </div>
                                                        <div>
                                                            <h6 class="fw-bold mb-0"><?= htmlspecialchars($record['first_name']); ?></h6>
                                                            <small class="text-muted"><?= date('M d, Y', strtotime($record['birth_date'])); ?></small>
                                                        </div>
                                                    </div>
                                                    <div class="d-flex justify-content-between small">
                                                        <span class="text-muted">Gender: <?= ucfirst($record['gender']); ?></span>
                                                        <span class="fw-bold"><?= $record['birth_weight'] ?> kg</span>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-5 border rounded-xl border-dashed">
                                        <i class="fas fa-child fa-3x text-light mb-3"></i>
                                        <p class="text-muted small">No registered children records yet.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modals -->
    <div class="modal fade" id="prenatalModal" tabindex="-1"><div class="modal-dialog modal-xl"><div class="modal-content"></div></div></div>
    <div class="modal fade" id="postnatalModal" tabindex="-1"><div class="modal-dialog modal-xl"><div class="modal-content"></div></div></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // AJAX Loaders for Modals
            const loadModal = (id, url) => {
                document.getElementById(id).addEventListener('show.bs.modal', function() {
                    const modalContent = this.querySelector('.modal-content');
                    modalContent.innerHTML = '<div class="p-5 text-center"><div class="spinner-border text-primary"></div></div>';
                    fetch(url).then(r => r.text()).then(d => modalContent.innerHTML = d);
                });
            };
            loadModal('prenatalModal', '../ajax/get_mother_prenatal_records.php');
            loadModal('postnatalModal', '../ajax/get_mother_postnatal_records.php');

            // SOS Handler
            document.getElementById('sosTrigger').addEventListener('click', function() {
                Swal.fire({
                    title: 'Trigger Emergency Alert?',
                    text: "This will immediately notify the midwife of your status.",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#ef4444',
                    cancelButtonColor: '#64748b',
                    confirmButtonText: 'Yes, Send SOS!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        navigator.geolocation.getCurrentPosition(position => {
                            sendSos(`${position.coords.latitude}, ${position.coords.longitude}`);
                        }, () => {
                            sendSos('Location access denied');
                        });
                    }
                });
            });

            function sendSos(location) {
                fetch('../ajax/trigger_sos.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `alert_type=Urgent SOS&location=${encodeURIComponent(location)}`
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire('Alert Sent!', data.message, 'success');
                    } else {
                        Swal.fire('Error', data.message, 'error');
                    }
                });
            }
        });
    </script>
</body>
</html>