<?php
// get_prenatal_details.php - UPDATED TO SHOW ALL VISITS' DATA
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
    echo '<div class="alert alert-danger">Prenatal record ID is required.</div>';
    exit();
}

$recordId = intval($_GET['id']);

try {
    // First, get the specific prenatal record details
    $query = "
        SELECT pr.*, 
               m.first_name as mother_first_name, 
               m.last_name as mother_last_name,
               m.phone as mother_phone,
               m.email as mother_email,
               m.address, m.blood_type, m.rh_factor,
               pd.edc, pd.lmp, 
               pd.gravida, pd.para, pd.living_children, pd.abortions,
               u_recorder.first_name as recorded_first_name,
               u_recorder.last_name as recorded_last_name
        FROM prenatal_records pr
        JOIN mothers m ON pr.mother_id = m.id
        LEFT JOIN pregnancy_details pd ON m.id = pd.mother_id
        LEFT JOIN users u_recorder ON pr.recorded_by = u_recorder.id 
        WHERE pr.id = ?
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$recordId]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$record) {
        http_response_code(404);
        echo '<div class="alert alert-danger">Prenatal record not found.</div>';
        exit();
    }
    
    // Now, get ALL prenatal records for this mother to show lab results and treatments from all visits
    $motherId = $record['mother_id'];
    $allVisitsQuery = "
        SELECT pr.*, 
               u.first_name as recorded_first_name,
               u.last_name as recorded_last_name
        FROM prenatal_records pr
        LEFT JOIN users u ON pr.recorded_by = u.id
        WHERE pr.mother_id = ?
        ORDER BY pr.visit_date ASC, pr.visit_number ASC
    ";
    
    $allVisitsStmt = $pdo->prepare($allVisitsQuery);
    $allVisitsStmt->execute([$motherId]);
    $allVisits = $allVisitsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate gestational weeks if LMP is available
    $gestationalWeeks = '';
    if (!empty($record['lmp']) && $record['lmp'] != '0000-00-00') {
        $lmpDate = new DateTime($record['lmp']);
        $visitDate = new DateTime($record['visit_date']);
        $interval = $lmpDate->diff($visitDate);
        $weeks = floor($interval->days / 7);
        $days = $interval->days % 7;
        $gestationalWeeks = $weeks . ' weeks ' . $days . ' days';
    }
    
    // Format the data for display
    ?>
    
    <div class="row">
        <!-- Mother Information -->
        <div class="col-md-6">
            <div class="detail-section mother-info">
                <h6><i class="fas fa-female me-2"></i>Mother Information</h6>
                <div class="detail-item d-flex">
                    <span class="detail-label">Name:</span>
                    <span class="detail-value"><?= htmlspecialchars(($record['mother_first_name'] ?? '') . ' ' . ($record['mother_last_name'] ?? '')) ?></span>
                </div>
                <div class="detail-item d-flex">
                    <span class="detail-label">Contact:</span>
                    <span class="detail-value"><?= !empty($record['mother_phone']) ? htmlspecialchars($record['mother_phone']) : '<span class="empty-data">Not provided</span>' ?></span>
                </div>
                <div class="detail-item d-flex">
                    <span class="detail-label">Email:</span>
                    <span class="detail-value"><?= !empty($record['mother_email']) ? htmlspecialchars($record['mother_email']) : '<span class="empty-data">Not provided</span>' ?></span>
                </div>
                <div class="detail-item d-flex">
                    <span class="detail-label">Address:</span>
                    <span class="detail-value"><?= !empty($record['address']) ? htmlspecialchars($record['address']) : '<span class="empty-data">Not provided</span>' ?></span>
                </div>
                <div class="detail-item d-flex">
                    <span class="detail-label">Blood Type:</span>
                    <span class="detail-value">
                        <?= !empty($record['blood_type']) ? 
                            htmlspecialchars($record['blood_type'] . ($record['rh_factor'] ?? '')) : 
                            '<span class="empty-data">Not recorded</span>' ?>
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Pregnancy Information -->
        <div class="col-md-6">
            <div class="detail-section pregnancy-info">
                <h6><i class="fas fa-baby me-2"></i>Pregnancy Information</h6>
                <div class="detail-item d-flex">
                    <span class="detail-label">Gravida/Para:</span>
                    <span class="detail-value">G<?= $record['gravida'] ?? '?' ?> P<?= $record['para'] ?? '?' ?></span>
                </div>
                <div class="detail-item d-flex">
                    <span class="detail-label">Living Children:</span>
                    <span class="detail-value"><?= $record['living_children'] ?? '0' ?></span>
                </div>
                <div class="detail-item d-flex">
                    <span class="detail-label">Abortions:</span>
                    <span class="detail-value"><?= $record['abortions'] ?? '0' ?></span>
                </div>
                <div class="detail-item d-flex">
                    <span class="detail-label">LMP:</span>
                    <span class="detail-value">
                        <?= !empty($record['lmp']) && $record['lmp'] != '0000-00-00' ? 
                            date('M j, Y', strtotime($record['lmp'])) : 
                            '<span class="empty-data">Not recorded</span>' ?>
                    </span>
                </div>
                <div class="detail-item d-flex">
                    <span class="detail-label">EDC:</span>
                    <span class="detail-value">
                        <?= !empty($record['edc']) && $record['edc'] != '0000-00-00' ? 
                            date('M j, Y', strtotime($record['edc'])) : 
                            '<span class="empty-data">Not calculated</span>' ?>
                    </span>
                </div>
                <?php if (!empty($gestationalWeeks)): ?>
                <div class="detail-item d-flex">
                    <span class="detail-label">Gestational Age:</span>
                    <span class="detail-value"><?= $gestationalWeeks ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Current Visit Information -->
    <div class="detail-section visit-info">
        <h6><i class="fas fa-calendar-alt me-2"></i>Current Visit Information</h6>
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
                    <span class="detail-value"><?= $record['visit_number'] ?? '' ?></span>
                </div>
            </div>
            <div class="col-md-3">
                <div class="detail-item">
                    <span class="detail-label">Gestational Age:</span>
                    <span class="detail-value"><?= $record['gestational_age'] ?? 'Not recorded' ?></span>
                </div>
            </div>
            <div class="col-md-3">
                <div class="detail-item">
                    <span class="detail-label">Recorded By:</span>
                    <span class="detail-value">
                        <?= !empty($record['recorded_first_name']) ? 
                            htmlspecialchars(($record['recorded_first_name'] ?? '') . ' ' . ($record['recorded_last_name'] ?? '')) : 
                            '<span class="empty-data">System</span>' ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Current Visit - Vital Signs & Examination -->
        <div class="col-md-6">
            <div class="detail-section">
                <h6><i class="fas fa-heartbeat me-2"></i>Current Visit - Vital Signs & Examination</h6>
                
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
                
                <!-- Complaints & Findings -->
                <div class="mb-3">
                    <div class="detail-item">
                        <span class="detail-label">Complaints:</span>
                        <span class="detail-value"><?= !empty($record['complaints']) ? nl2br(htmlspecialchars($record['complaints'])) : '<span class="empty-data">None reported</span>' ?></span>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="detail-item">
                        <span class="detail-label">Findings:</span>
                        <span class="detail-value"><?= !empty($record['findings']) ? nl2br(htmlspecialchars($record['findings'])) : '<span class="empty-data">No findings</span>' ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Current Visit - Diagnosis & Treatment -->
        <div class="col-md-6">
            <div class="detail-section">
                <h6><i class="fas fa-stethoscope me-2"></i>Current Visit - Diagnosis & Treatment</h6>
                
                <div class="mb-3">
                    <div class="detail-item">
                        <span class="detail-label">Diagnosis:</span>
                        <span class="detail-value"><?= !empty($record['diagnosis']) ? nl2br(htmlspecialchars($record['diagnosis'])) : '<span class="empty-data">No diagnosis</span>' ?></span>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="detail-item">
                        <span class="detail-label">Treatment:</span>
                        <span class="detail-value"><?= !empty($record['treatment']) ? nl2br(htmlspecialchars($record['treatment'])) : '<span class="empty-data">No treatment</span>' ?></span>
                    </div>
                </div>
                
                <!-- Medications -->
                <div class="mb-3">
                    <strong class="text-primary">Medications:</strong>
                    <div class="row mt-2">
                        <div class="col-4">
                            <div class="detail-item">
                                <span class="detail-label">Iron:</span>
                                <span class="detail-value">
                                    <?= $record['iron_supplement'] ? 
                                        '<span class="medication-yes">Yes</span>' : 
                                        '<span class="medication-no">No</span>' ?>
                                </span>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="detail-item">
                                <span class="detail-label">Folic Acid:</span>
                                <span class="detail-value">
                                    <?= $record['folic_acid'] ? 
                                        '<span class="medication-yes">Yes</span>' : 
                                        '<span class="medication-no">No</span>' ?>
                                </span>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="detail-item">
                                <span class="detail-label">Calcium:</span>
                                <span class="detail-value">
                                    <?= $record['calcium'] ? 
                                        '<span class="medication-yes">Yes</span>' : 
                                        '<span class="medication-no">No</span>' ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php if (!empty($record['other_meds'])): ?>
                    <div class="detail-item">
                        <span class="detail-label">Other Meds:</span>
                        <span class="detail-value"><?= nl2br(htmlspecialchars($record['other_meds'])) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Laboratory Results and Treatments Given sections removed as requested for testing auto-sync -->

    
    <!-- Follow-up Information -->
    <div class="detail-section mt-4">
        <h6><i class="fas fa-calendar-check me-2"></i>Follow-up Information</h6>
        
        <div class="row">
            <div class="col-md-6">
                <div class="detail-item">
                    <span class="detail-label">Next Visit Date:</span>
                    <span class="detail-value">
                        <?= !empty($record['next_visit_date']) && $record['next_visit_date'] != '0000-00-00' ? 
                            date('M j, Y', strtotime($record['next_visit_date'])) : 
                            '<span class="empty-data">Not scheduled</span>' ?>
                    </span>
                </div>
            </div>
            <div class="col-md-6">
                <div class="detail-item">
                    <span class="detail-label">Recorded At:</span>
                    <span class="detail-value"><?= date('M j, Y g:i A', strtotime($record['recorded_at'])) ?></span>
                </div>
            </div>
        </div>
    </div>

    <?php
    
} catch (PDOException $e) {
    error_log("Database error in get_prenatal_details.php: " . $e->getMessage());
    http_response_code(500);
    echo '<div class="alert alert-danger">Database Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
} catch (Exception $e) {
    error_log("Error in get_prenatal_details.php: " . $e->getMessage());
    http_response_code(500);
    echo '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>