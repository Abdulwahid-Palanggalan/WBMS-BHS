<?php
// get_postnatal_details.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$rootPath = __DIR__;
require_once $rootPath . '/includes/auth.php';
require_once $rootPath . '/includes/functions.php';

if (!isAuthorized(['admin', 'midwife'])) {
    http_response_code(403);
    echo '<div class="alert alert-danger">Unauthorized access.</div>';
    exit();
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    http_response_code(400);
    echo '<div class="alert alert-danger">Postnatal record ID is required.</div>';
    exit();
}

$recordId = intval($_GET['id']);

try {
    // Get postnatal record
    $stmt = $pdo->prepare("SELECT * FROM postnatal_records WHERE id = ?");
    $stmt->execute([$recordId]);
    $postnatalRecord = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$postnatalRecord) {
        http_response_code(404);
        echo '<div class="alert alert-danger">Postnatal record not found.</div>';
        exit();
    }
    
    // Get baby record
    $babyStmt = $pdo->prepare("SELECT * FROM birth_records WHERE id = ?");
    $babyStmt->execute([$postnatalRecord['baby_id']]);
    $babyRecord = $babyStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$babyRecord) {
        echo '<div class="alert alert-danger">Baby record not found.</div>';
        exit();
    }
    
    // Get mother record - CORRECTED: Get complete mother information
    $motherStmt = $pdo->prepare("SELECT first_name, middle_name, last_name, phone, address FROM mothers WHERE id = ?");
    $motherStmt->execute([$babyRecord['mother_id']]);
    $motherRecord = $motherStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$motherRecord) {
        $motherRecord = [
            'first_name' => null, 
            'middle_name' => null, 
            'last_name' => null, 
            'phone' => null, 
            'address' => null
        ];
    }
    
    // Get recorded by user
    $userStmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
    $userStmt->execute([$postnatalRecord['recorded_by']]);
    $userRecord = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    // Combine all data - CORRECTED: Use mother data from mothers table
    $record = array_merge($postnatalRecord, [
        'baby_first_name' => $babyRecord['first_name'],
        'baby_middle_name' => $babyRecord['middle_name'],
        'baby_last_name' => $babyRecord['last_name'],
        'birth_date' => $babyRecord['birth_date'],
        'birth_weight' => $babyRecord['birth_weight'],
        'gender' => $babyRecord['gender'],
        'mother_first_name' => $motherRecord['first_name'],  // From mothers table
        'mother_middle_name' => $motherRecord['middle_name'], // From mothers table
        'mother_last_name' => $motherRecord['last_name'],    // From mothers table
        'mother_phone' => $motherRecord['phone'],
        'mother_address' => $motherRecord['address'],
        'recorded_first_name' => $userRecord['first_name'] ?? '',
        'recorded_last_name' => $userRecord['last_name'] ?? ''
    ]);
    
    // Calculate days after birth manually
    $daysAfterBirth = '';
    if (!empty($record['visit_date']) && !empty($record['birth_date'])) {
        $daysAfterBirth = floor((strtotime($record['visit_date']) - strtotime($record['birth_date'])) / (60 * 60 * 24));
    }
    
    // Continue with your HTML display code...
    ?>
    
    <div class="row">
        <!-- Mother Information -->
        <div class="col-md-6">
            <div class="detail-section mother-info">
                <h6><i class="fas fa-female me-2"></i>Mother Information</h6>
                <div class="detail-item d-flex">
                    <span class="detail-label">Name:</span>
                    <span class="detail-value"><?= htmlspecialchars($record['mother_first_name'] . ' ' . $record['mother_last_name']) ?></span>
                </div>
                <div class="detail-item d-flex">
                    <span class="detail-label">Contact:</span>
                    <span class="detail-value"><?= !empty($record['mother_phone']) ? htmlspecialchars($record['mother_phone']) : '<span class="empty-data">Not provided</span>' ?></span>
                </div>
                <div class="detail-item d-flex">
                    <span class="detail-label">Address:</span>
                    <span class="detail-value"><?= !empty($record['mother_address']) ? htmlspecialchars($record['mother_address']) : '<span class="empty-data">Not provided</span>' ?></span>
                </div>
            </div>
        </div>
        
        <!-- Baby Information -->
        <div class="col-md-6">
            <div class="detail-section baby-info">
                <h6><i class="fas fa-baby me-2"></i>Baby Information</h6>
                <div class="detail-item d-flex">
                    <span class="detail-label">Name:</span>
                    <span class="detail-value"><?= htmlspecialchars($record['baby_first_name'] . ' ' . $record['baby_last_name']) ?></span>
                </div>
                <div class="detail-item d-flex">
                    <span class="detail-label">Gender:</span>
                    <span class="detail-value"><?= ucfirst($record['gender']) ?></span>
                </div>
                <div class="detail-item d-flex">
                    <span class="detail-label">Birth Date:</span>
                    <span class="detail-value"><?= date('M j, Y', strtotime($record['birth_date'])) ?></span>
                </div>
                <div class="detail-item d-flex">
                    <span class="detail-label">Birth Weight:</span>
                    <span class="detail-value"><?= $record['birth_weight'] ?> kg</span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Visit Information -->
    <div class="detail-section visit-info">
        <h6><i class="fas fa-calendar-alt me-2"></i>Visit Information</h6>
        <div class="row">
            <div class="col-md-3">
                <div class="detail-item">
                    <span class="detail-label">Visit Date:</span>
                    <span class="detail-value"><?= date('M j, Y', strtotime($record['visit_date'])) ?></span>
                </div>
            </div>
            <div class="col-md-3">
                <div class="detail-item">
                    <span class="detail-label">Visit Number:</span>
                    <span class="detail-value"><?= $record['visit_number'] ?></span>
                </div>
            </div>
            <div class="col-md-3">
                <div class="detail-item">
                    <span class="detail-label">Days After Birth:</span>
                    <span class="detail-value"><?= $daysAfterBirth ? $daysAfterBirth . ' days' : '<span class="empty-data">Not calculated</span>' ?></span>
                </div>
            </div>
            <div class="col-md-3">
                <div class="detail-item">
                    <span class="detail-label">Recorded By:</span>
                    <span class="detail-value"><?= htmlspecialchars($record['recorded_first_name'] . ' ' . $record['recorded_last_name']) ?></span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Mother's Health Assessment -->
        <div class="col-md-6">
            <div class="detail-section">
                <h6><i class="fas fa-heartbeat me-2"></i>Mother's Health Assessment</h6>
                
                <!-- Vital Signs -->
                <div class="mb-3">
                    <strong class="text-primary">Vital Signs:</strong>
                    <div class="row mt-2">
                        <div class="col-4">
                            <div class="detail-item">
                                <span class="detail-label">Blood Pressure:</span>
                                <span class="detail-value"><?= !empty($record['blood_pressure']) ? htmlspecialchars($record['blood_pressure']) : '<span class="empty-data">Not recorded</span>' ?></span>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="detail-item">
                                <span class="detail-label">Weight:</span>
                                <span class="detail-value"><?= !empty($record['weight']) ? $record['weight'] . ' kg' : '<span class="empty-data">Not recorded</span>' ?></span>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="detail-item">
                                <span class="detail-label">Temperature:</span>
                                <span class="detail-value"><?= !empty($record['temperature']) ? $record['temperature'] . '°C' : '<span class="empty-data">Not recorded</span>' ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Postpartum Assessment -->
                <div class="mb-3">
                    <strong class="text-primary">Postpartum Assessment:</strong>
                    <div class="row mt-2">
                        <div class="col-6">
                            <div class="detail-item">
                                <span class="detail-label">Uterus:</span>
                                <span class="detail-value">
                                    <?= !empty($record['uterus_status']) ? htmlspecialchars(ucfirst($record['uterus_status'])) : '<span class="empty-data">Not assessed</span>' ?>
                                </span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="detail-item">
                                <span class="detail-label">Lochia:</span>
                                <span class="detail-value">
                                    <?= !empty($record['lochia_status']) ? htmlspecialchars(ucfirst($record['lochia_status'])) : '<span class="empty-data">Not assessed</span>' ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <div class="detail-item">
                                <span class="detail-label">Perineum:</span>
                                <span class="detail-value">
                                    <?= !empty($record['perineum_status']) ? htmlspecialchars(ucfirst($record['perineum_status'])) : '<span class="empty-data">Not assessed</span>' ?>
                                </span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="detail-item">
                                <span class="detail-label">Breasts:</span>
                                <span class="detail-value">
                                    <?= !empty($record['breasts_status']) ? htmlspecialchars(ucfirst($record['breasts_status'])) : '<span class="empty-data">Not assessed</span>' ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <div class="detail-item">
                                <span class="detail-label">Emotional State:</span>
                                <span class="detail-value">
                                    <?= !empty($record['emotional_state']) ? htmlspecialchars(ucfirst(str_replace('-', ' ', $record['emotional_state']))) : '<span class="empty-data">Not assessed</span>' ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Complaints & Treatment -->
                <div class="mb-3">
                    <div class="detail-item">
                        <span class="detail-label">Complaints:</span>
                        <span class="detail-value"><?= !empty($record['complaints']) ? nl2br(htmlspecialchars($record['complaints'])) : '<span class="empty-data">None reported</span>' ?></span>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="detail-item">
                        <span class="detail-label">Treatment Given:</span>
                        <span class="detail-value"><?= !empty($record['treatment']) ? nl2br(htmlspecialchars($record['treatment'])) : '<span class="empty-data">No treatment given</span>' ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Baby's Health Assessment -->
        <div class="col-md-6">
            <div class="detail-section">
                <h6><i class="fas fa-baby me-2"></i>Baby's Health Assessment</h6>
                
                <!-- Weight Information -->
                <div class="mb-3">
                    <strong class="text-primary">Weight Information:</strong>
                    <div class="row mt-2">
                        <div class="col-6">
                            <div class="detail-item">
                                <span class="detail-label">Current Weight:</span>
                                <span class="detail-value"><?= !empty($record['baby_weight']) ? $record['baby_weight'] . ' kg' : '<span class="empty-data">Not recorded</span>' ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Feeding & Health -->
                <div class="mb-3">
                    <div class="detail-item">
                        <span class="detail-label">Feeding Method:</span>
                        <span class="detail-value">
                            <?= !empty($record['feeding_method']) ? htmlspecialchars(ucfirst(str_replace('-', ' ', $record['feeding_method']))) : '<span class="empty-data">Not specified</span>' ?>
                        </span>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="detail-item">
                        <span class="detail-label">Health Issues:</span>
                        <span class="detail-value"><?= !empty($record['baby_issues']) ? nl2br(htmlspecialchars($record['baby_issues'])) : '<span class="empty-data">None reported</span>' ?></span>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="detail-item">
                        <span class="detail-label">Baby Treatment:</span>
                        <span class="detail-value"><?= !empty($record['baby_treatment']) ? nl2br(htmlspecialchars($record['baby_treatment'])) : '<span class="empty-data">No treatment given</span>' ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Follow-up & Counseling -->
            <div class="detail-section">
                <h6><i class="fas fa-calendar-check me-2"></i>Follow-up & Counseling</h6>
                
                <div class="mb-3">
                    <div class="detail-item">
                        <span class="detail-label">Counseling Topics:</span>
                        <span class="detail-value"><?= !empty($record['counseling_topics']) ? nl2br(htmlspecialchars($record['counseling_topics'])) : '<span class="empty-data">No counseling recorded</span>' ?></span>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="detail-item">
                        <span class="detail-label">Next Visit Date:</span>
                        <span class="detail-value">
                            <?= !empty($record['next_visit_date']) && $record['next_visit_date'] != '0000-00-00' ? 
                                date('M j, Y', strtotime($record['next_visit_date'])) : 
                                '<span class="empty-data">Not scheduled</span>' ?>
                        </span>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="detail-item">
                        <span class="detail-label">Referral Needed:</span>
                        <span class="detail-value">
                            <?= $record['referral_needed'] ? 
                                '<span class="badge bg-warning">Yes</span>' : 
                                '<span class="badge bg-secondary">No</span>' ?>
                        </span>
                    </div>
                </div>
                
                <?php if ($record['referral_needed'] && !empty($record['referral_details'])): ?>
                <div class="mb-3">
                    <div class="detail-item">
                        <span class="detail-label">Referral Details:</span>
                        <span class="detail-value"><?= nl2br(htmlspecialchars($record['referral_details'])) ?></span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php
    
} catch (PDOException $e) {
    error_log("Database error in get_postnatal_details.php: " . $e->getMessage());
    http_response_code(500);
    echo '<div class="alert alert-danger">Database Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
} catch (Exception $e) {
    error_log("Error in get_postnatal_details.php: " . $e->getMessage());
    http_response_code(500);
    echo '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>