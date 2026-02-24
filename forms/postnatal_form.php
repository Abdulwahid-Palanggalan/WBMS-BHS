<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';

if (!isAuthorized(['admin', 'midwife'])) {
    header("Location: ../login.php");
    exit();
}

redirectIfNotLoggedIn();

global $pdo;

$message = '';
$error = '';
$editMode = false;
$recordData = [];

// Check if in edit mode
if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $editMode = true;
    $recordId = intval($_GET['edit']);
    
    // FIXED QUERY - Use JOIN with mothers table to get mother info
    $stmt = $pdo->prepare("
        SELECT pn.*, 
               br.first_name as baby_first_name,
               br.last_name as baby_last_name,
               br.birth_date as baby_birth_date,
               br.mother_id,
               m.first_name as mother_first_name,
               m.last_name as mother_last_name
        FROM postnatal_records pn
        JOIN birth_records br ON pn.baby_id = br.id
        JOIN mothers m ON br.mother_id = m.id
        WHERE pn.id = ?
    ");
    $stmt->execute([$recordId]);
    $recordData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$recordData) {
        $error = "Postnatal record not found.";
        $editMode = false;
    } else {
        $selectedMotherId = $recordData['mother_id'];
    }
}

// Get mothers and their babies for dropdown
$mothers = $pdo->query("
    SELECT m.id, m.first_name, m.last_name 
    FROM mothers m 
    ORDER BY m.last_name, m.first_name
")->fetchAll(PDO::FETCH_ASSOC);

// Get babies for selected mother
$babies = [];
$selectedMotherId = $_GET['mother_id'] ?? $_POST['mother_id'] ?? ($recordData['mother_id'] ?? '');
if (!empty($selectedMotherId)) {
    $babyStmt = $pdo->prepare("
        SELECT id, first_name, last_name, birth_date 
        FROM birth_records 
        WHERE mother_id = ? 
        ORDER BY birth_date DESC
    ");
    $babyStmt->execute([$selectedMotherId]);
    $babies = $babyStmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Use null coalescing operator to avoid undefined array keys
    $motherId = $_POST['mother_id'] ?? '';
    $babyId = $_POST['baby_id'] ?? '';
    $visitDate = $_POST['visit_date'] ?? '';
    $visitNumber = $_POST['visit_number'] ?? '';
    $bloodPressure = trim($_POST['blood_pressure'] ?? '');
    $weight = $_POST['weight'] ?? '';
    $temperature = $_POST['temperature'] ?? '';
    $uterusStatus = $_POST['uterus_status'] ?? '';
    $lochiaStatus = $_POST['lochia_status'] ?? '';
    $perineumStatus = $_POST['perineum_status'] ?? '';
    $breastsStatus = $_POST['breasts_status'] ?? '';
    $emotionalState = $_POST['emotional_state'] ?? '';
    $complaints = trim($_POST['complaints'] ?? '');
    $treatment = trim($_POST['treatment'] ?? '');
    $babyWeight = $_POST['baby_weight'] ?? '';
    $feedingMethod = $_POST['feeding_method'] ?? '';
    $babyIssues = trim($_POST['baby_issues'] ?? '');
    $babyTreatment = trim($_POST['baby_treatment'] ?? '');
    $counselingTopics = trim($_POST['counseling_topics'] ?? '');
    $nextVisitDate = $_POST['next_visit_date'] ?? '';
    $referralNeeded = isset($_POST['referral_needed']) ? 1 : 0;
    $referralDetails = trim($_POST['referral_details'] ?? '');
    
    $userId = $_SESSION['user_id'];

    // Validate required fields
    if (!$motherId || !$babyId || !$visitDate || !$visitNumber) {
        $error = "Please fill in all required fields.";
    } 
    // Validate next visit date
    elseif (!empty($nextVisitDate) && (strtotime($nextVisitDate) <= strtotime($visitDate))) {
        $error = "Next visit date must be after the current visit date.";
    }
    else {
        if ($editMode) {
            // UPDATE existing record
            $recordId = $_POST['record_id'];
            $sql = "UPDATE postnatal_records SET 
                    visit_date = ?, visit_number = ?, blood_pressure = ?, weight = ?, temperature = ?, 
                    uterus_status = ?, lochia_status = ?, perineum_status = ?, breasts_status = ?, emotional_state = ?, 
                    complaints = ?, treatment = ?, baby_weight = ?, feeding_method = ?, baby_issues = ?, baby_treatment = ?, 
                    counseling_topics = ?, next_visit_date = ?, referral_needed = ?, referral_details = ?
                    WHERE id = ?";
            
            $stmt = $pdo->prepare($sql);
            
            if ($stmt->execute([
                $visitDate, $visitNumber, $bloodPressure, $weight, $temperature,
                $uterusStatus, $lochiaStatus, $perineumStatus, $breastsStatus, $emotionalState,
                $complaints, $treatment, $babyWeight, $feedingMethod, $babyIssues, $babyTreatment,
                $counselingTopics, $nextVisitDate, $referralNeeded, $referralDetails, $recordId
            ])) {
                $message = "Postnatal record updated successfully!";
                logActivity($userId, "Updated postnatal visit record ID: $recordId");
            } else {
                $error = "Failed to update postnatal record. Please try again.";
            }
        } else {
            // INSERT new record
            $sql = "INSERT INTO postnatal_records 
                    (mother_id, baby_id, visit_date, visit_number, blood_pressure, weight, temperature, 
                     uterus_status, lochia_status, perineum_status, breasts_status, emotional_state, 
                     complaints, treatment, baby_weight, feeding_method, baby_issues, baby_treatment, 
                     counseling_topics, next_visit_date, referral_needed, referral_details, recorded_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $pdo->prepare($sql);
            
            if ($stmt->execute([
                $motherId, $babyId, $visitDate, $visitNumber, $bloodPressure, $weight, $temperature,
                $uterusStatus, $lochiaStatus, $perineumStatus, $breastsStatus, $emotionalState,
                $complaints, $treatment, $babyWeight, $feedingMethod, $babyIssues, $babyTreatment,
                $counselingTopics, $nextVisitDate, $referralNeeded, $referralDetails, $userId
            ])) {
                $message = "Postnatal record saved successfully!";
                logActivity($userId, "Recorded postnatal visit for mother ID: $motherId");
                
                // Clear form after successful submission (only for new records)
                if (!$editMode) {
                    unset($_POST);
                    $selectedMotherId = '';
                    $babies = [];
                }
            } else {
                $error = "Failed to save postnatal record. Please try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $editMode ? 'Edit' : 'New'; ?> Postnatal Care - Health Station System </title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .vital-signs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .vital-sign-card {
            background-color: #f9f9f9;
            border-radius: 5px;
            padding: 15px;
            text-align: center;
        }
        
        .vital-sign-value {
            font-size: 24px;
            font-weight: 700;
            color: #2e7d32;
            margin: 5px 0;
        }
        
        .vital-sign-label {
            font-size: 12px;
            color: #666;
        }
        
        .baby-details {
            background-color: #f5f5f5;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        #loadingBabies {
            display: none;
            color: #0d6efd;
            margin-left: 10px;
        }
        
        /* Red warning styles for required fields */
        .required-field label::after {
            content: " *";
            color: #dc3545;
        }
        
        .required-field .form-control:required:invalid,
        .required-field .form-select:required:invalid {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }
        
        .required-field .form-control:required:valid,
        .required-field .form-select:required:valid {
            border-color: #198754;
            box-shadow: 0 0 0 0.2rem rgba(25, 135, 84, 0.25);
        }
        
        .error-message {
            color: #dc3545;
            font-size: 0.875em;
            margin-top: 0.25rem;
        }
        
        .required-indicator {
            color: #dc3545;
            font-weight: bold;
        }
        
        .field-warning {
            border: 2px solid #dc3545 !important;
            background-color: #fff5f5;
        }
        
        .date-error {
            border-color: #dc3545 !important;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25) !important;
        }
        
        .readonly-info {
            background-color: #e9ecef;
            opacity: 1;
        }
    </style>
</head>
<body>
    <?php include_once '../includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include_once '../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><?php echo $editMode ? 'Edit' : 'New'; ?> Postnatal Care Record</h1>
                    <?php if ($editMode): ?>
                    <div class="btn-toolbar mb-2 mb-md-0">
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-body">
                        <form method="POST" action="" id="postnatalForm" novalidate>
                            <?php if ($editMode): ?>
                            <input type="hidden" name="record_id" value="<?php echo $recordData['id']; ?>">
                            <?php endif; ?>
                            
                            <div class="row mb-3 required-field">
                                <div class="col-md-6">
                                    <label for="mother_id" class="form-label">Mother</label>
                                    <?php if ($editMode): ?>
                                    <input type="text" class="form-control readonly-info" 
                                           value="<?php echo htmlspecialchars($recordData['mother_first_name'] . ' ' . $recordData['mother_last_name']); ?>" 
                                           readonly>
                                    <input type="hidden" name="mother_id" value="<?php echo $recordData['mother_id']; ?>">
                                    <small class="text-muted">Mother cannot be changed in edit mode</small>
                                    <?php else: ?>
                                    <select class="form-select" id="mother_id" name="mother_id" required>
                                        <option value="">Select Mother</option>
                                        <?php foreach ($mothers as $mother): ?>
                                        <option value="<?php echo $mother['id']; ?>" <?php echo ($selectedMotherId == $mother['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($mother['first_name'] . ' ' . $mother['last_name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php endif; ?>
                                    <div class="error-message" id="mother_id_error"></div>
                                </div>
                                <div class="col-md-6 required-field">
                                    <label for="baby_id" class="form-label">Baby</label>
                                    <?php if ($editMode): ?>
                                    <input type="text" class="form-control readonly-info" 
                                           value="<?php echo htmlspecialchars($recordData['baby_first_name'] . ' ' . $recordData['baby_last_name'] . ' (Born: ' . date('M j, Y', strtotime($recordData['baby_birth_date'])) . ')'); ?>" 
                                           readonly>
                                    <input type="hidden" name="baby_id" value="<?php echo $recordData['baby_id']; ?>">
                                    <small class="text-muted">Baby cannot be changed in edit mode</small>
                                    <?php else: ?>
                                    <div class="d-flex align-items-center">
                                        <select class="form-select" id="baby_id" name="baby_id" required <?php echo empty($babies) ? 'disabled' : ''; ?>>
                                            <option value="">Select Baby</option>
                                            <?php foreach ($babies as $baby): ?>
                                            <option value="<?php echo $baby['id']; ?>" <?php echo (($_POST['baby_id'] ?? '') == $baby['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($baby['first_name'] . ' ' . $baby['last_name'] . ' (Born: ' . date('M j, Y', strtotime($baby['birth_date'])) . ')'); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <span id="loadingBabies" class="ms-2"><i class="fas fa-spinner fa-spin"></i></span>
                                    </div>
                                    <?php endif; ?>
                                    <div class="error-message" id="baby_id_error"></div>
                                </div>
                            </div>
                            
                            <div id="babyDetailsContainer">
                            <?php if (!empty($babies) || $editMode): ?>
                            <div class="baby-details">
                                <h5>Baby Details</h5>
                                <div class="row">
                                    <div class="col-md-4">
                                        <label class="form-label">Baby's Name</label>
                                        <input type="text" class="form-control readonly-info" 
                                               value="<?php echo $editMode ? htmlspecialchars($recordData['baby_first_name'] . ' ' . $recordData['baby_last_name']) : htmlspecialchars($babies[0]['first_name'] . ' ' . $babies[0]['last_name']); ?>" 
                                               readonly>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Date of Birth</label>
                                        <input type="text" class="form-control readonly-info" 
                                               value="<?php echo $editMode ? date('M j, Y', strtotime($recordData['baby_birth_date'])) : date('M j, Y', strtotime($babies[0]['birth_date'])); ?>" 
                                               readonly>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            </div>

                            <!-- Visit Information -->
                            <h5 class="mb-3"><i class="fas fa-calendar-alt"></i> Visit Information</h5>
                            <div class="row mb-3">
                                <div class="col-md-4 required-field">
                                    <label for="visit_date" class="form-label">Visit Date</label>
                                    <input type="date" class="form-control" id="visit_date" name="visit_date" required 
                                           value="<?php echo htmlspecialchars($recordData['visit_date'] ?? $_POST['visit_date'] ?? date('Y-m-d')); ?>">
                                    <div class="error-message" id="visit_date_error"></div>
                                </div>
                                <div class="col-md-4 required-field">
                                    <label for="visit_number" class="form-label">Visit Number</label>
                                    <input type="number" class="form-control" id="visit_number" name="visit_number" placeholder="e.g. 1" required min="1"
                                           value="<?php echo htmlspecialchars($recordData['visit_number'] ?? $_POST['visit_number'] ?? ''); ?>">
                                    <div class="error-message" id="visit_number_error"></div>
                                </div>
                            </div>

                            <!-- Mother's Vital Signs -->
                            <h5 class="mb-3"><i class="fas fa-heartbeat"></i> Mother's Vital Signs</h5>
                            <div class="vital-signs-grid mb-3">
                                <div class="vital-sign-card required-field">
                                    <div class="vital-sign-label">Blood Pressure</div>
                                    <div class="vital-sign-value">110/70</div>
                                    <input type="text" class="form-control form-control-sm" name="blood_pressure" placeholder="mmHg" required
                                           value="<?php echo htmlspecialchars($recordData['blood_pressure'] ?? $_POST['blood_pressure'] ?? ''); ?>">
                                    <div class="error-message" id="blood_pressure_error"></div>
                                </div>
                                <div class="vital-sign-card required-field">
                                    <div class="vital-sign-label">Weight</div>
                                    <div class="vital-sign-value">62</div>
                                    <input type="number" step="0.1" class="form-control form-control-sm" name="weight" placeholder="kg" required
                                           value="<?php echo htmlspecialchars($recordData['weight'] ?? $_POST['weight'] ?? ''); ?>">
                                    <div class="error-message" id="weight_error"></div>
                                </div>
                                <div class="vital-sign-card required-field">
                                    <div class="vital-sign-label">Temperature</div>
                                    <div class="vital-sign-value">36.7</div>
                                    <input type="number" step="0.1" class="form-control form-control-sm" name="temperature" placeholder="°C" required
                                           value="<?php echo htmlspecialchars($recordData['temperature'] ?? $_POST['temperature'] ?? ''); ?>">
                                    <div class="error-message" id="temperature_error"></div>
                                </div>
                            </div>

                            <!-- Postpartum Assessment -->
                            <h5 class="mb-3"><i class="fas fa-notes-medical"></i> Postpartum Assessment</h5>
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="uterus_status" class="form-label">Uterus</label>
                                    <select class="form-select" id="uterus_status" name="uterus_status">
                                        <option value="">Select status</option>
                                        <option value="normal" <?php echo (($recordData['uterus_status'] ?? $_POST['uterus_status'] ?? '') == 'normal') ? 'selected' : ''; ?>>Normal involution</option>
                                        <option value="delayed" <?php echo (($recordData['uterus_status'] ?? $_POST['uterus_status'] ?? '') == 'delayed') ? 'selected' : ''; ?>>Delayed involution</option>
                                        <option value="tender" <?php echo (($recordData['uterus_status'] ?? $_POST['uterus_status'] ?? '') == 'tender') ? 'selected' : ''; ?>>Tender</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="lochia_status" class="form-label">Lochia</label>
                                    <select class="form-select" id="lochia_status" name="lochia_status">
                                        <option value="">Select status</option>
                                        <option value="normal" <?php echo (($recordData['lochia_status'] ?? $_POST['lochia_status'] ?? '') == 'normal') ? 'selected' : ''; ?>>Normal</option>
                                        <option value="heavy" <?php echo (($recordData['lochia_status'] ?? $_POST['lochia_status'] ?? '') == 'heavy') ? 'selected' : ''; ?>>Heavy</option>
                                        <option value="foul" <?php echo (($recordData['lochia_status'] ?? $_POST['lochia_status'] ?? '') == 'foul') ? 'selected' : ''; ?>>Foul-smelling</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="perineum_status" class="form-label">Episiotomy/Perineum</label>
                                    <select class="form-select" id="perineum_status" name="perineum_status">
                                        <option value="">Select status</option>
                                        <option value="healed" <?php echo (($recordData['perineum_status'] ?? $_POST['perineum_status'] ?? '') == 'healed') ? 'selected' : ''; ?>>Healed</option>
                                        <option value="healing" <?php echo (($recordData['perineum_status'] ?? $_POST['perineum_status'] ?? '') == 'healing') ? 'selected' : ''; ?>>Healing well</option>
                                        <option value="infected" <?php echo (($recordData['perineum_status'] ?? $_POST['perineum_status'] ?? '') == 'infected') ? 'selected' : ''; ?>>Infected</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="breasts_status" class="form-label">Breasts</label>
                                    <select class="form-select" id="breasts_status" name="breasts_status">
                                        <option value="">Select status</option>
                                        <option value="normal" <?php echo (($recordData['breasts_status'] ?? $_POST['breasts_status'] ?? '') == 'normal') ? 'selected' : ''; ?>>Normal</option>
                                        <option value="engorged" <?php echo (($recordData['breasts_status'] ?? $_POST['breasts_status'] ?? '') == 'engorged') ? 'selected' : ''; ?>>Engorged</option>
                                        <option value="cracked" <?php echo (($recordData['breasts_status'] ?? $_POST['breasts_status'] ?? '') == 'cracked') ? 'selected' : ''; ?>>Cracked nipples</option>
                                        <option value="mastitis" <?php echo (($recordData['breasts_status'] ?? $_POST['breasts_status'] ?? '') == 'mastitis') ? 'selected' : ''; ?>>Mastitis</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="emotional_state" class="form-label">Emotional State</label>
                                    <select class="form-select" id="emotional_state" name="emotional_state">
                                        <option value="">Select status</option>
                                        <option value="normal" <?php echo (($recordData['emotional_state'] ?? $_POST['emotional_state'] ?? '') == 'normal') ? 'selected' : ''; ?>>Normal</option>
                                        <option value="baby-blues" <?php echo (($recordData['emotional_state'] ?? $_POST['emotional_state'] ?? '') == 'baby-blues') ? 'selected' : ''; ?>>Baby blues</option>
                                        <option value="depression" <?php echo (($recordData['emotional_state'] ?? $_POST['emotional_state'] ?? '') == 'depression') ? 'selected' : ''; ?>>Postpartum depression</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="complaints" class="form-label">Complaints</label>
                                    <textarea class="form-control" id="complaints" name="complaints" rows="3" placeholder="Enter any complaints"><?php echo htmlspecialchars($recordData['complaints'] ?? $_POST['complaints'] ?? ''); ?></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label for="treatment" class="form-label">Treatment Given</label>
                                    <textarea class="form-control" id="treatment" name="treatment" rows="3" placeholder="Enter treatment"><?php echo htmlspecialchars($recordData['treatment'] ?? $_POST['treatment'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            
                            <h5 class="mb-3"><i class="fas fa-baby"></i> Baby's Health</h5>
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="baby_weight" class="form-label">Current Weight (kg)</label>
                                    <input type="number" step="0.01" class="form-control" id="baby_weight" name="baby_weight" placeholder="Enter weight in kg"
                                           value="<?php echo htmlspecialchars($recordData['baby_weight'] ?? $_POST['baby_weight'] ?? ''); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="feeding_method" class="form-label">Feeding Method</label>
                                    <select class="form-select" id="feeding_method" name="feeding_method">
                                        <option value="">Select method</option>
                                        <option value="exclusive-breastfeeding" <?php echo (($recordData['feeding_method'] ?? $_POST['feeding_method'] ?? '') == 'exclusive-breastfeeding') ? 'selected' : ''; ?>>Exclusive breastfeeding</option>
                                        <option value="mixed-feeding" <?php echo (($recordData['feeding_method'] ?? $_POST['feeding_method'] ?? '') == 'mixed-feeding') ? 'selected' : ''; ?>>Mixed feeding</option>
                                        <option value="formula" <?php echo (($recordData['feeding_method'] ?? $_POST['feeding_method'] ?? '') == 'formula') ? 'selected' : ''; ?>>Formula feeding</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="baby_issues" class="form-label">Health Issues</label>
                                    <textarea class="form-control" id="baby_issues" name="baby_issues" rows="3" placeholder="Note any health issues"><?php echo htmlspecialchars($recordData['baby_issues'] ?? $_POST['baby_issues'] ?? ''); ?></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label for="baby_treatment" class="form-label">Treatment Given</label>
                                    <textarea class="form-control" id="baby_treatment" name="baby_treatment" rows="3" placeholder="Enter treatment for baby"><?php echo htmlspecialchars($recordData['baby_treatment'] ?? $_POST['baby_treatment'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            
                            <h5 class="mb-3"><i class="fas fa-calendar-check"></i> Follow-up & Counseling</h5>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="counseling_topics" class="form-label">Counseling Topics</label>
                                    <textarea class="form-control" id="counseling_topics" name="counseling_topics" rows="3" placeholder="Topics discussed"><?php echo htmlspecialchars($recordData['counseling_topics'] ?? $_POST['counseling_topics'] ?? ''); ?></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label for="next_visit_date" class="form-label">Next Visit Date</label>
                                    <input type="date" class="form-control" id="next_visit_date" name="next_visit_date"
                                           value="<?php echo htmlspecialchars($recordData['next_visit_date'] ?? $_POST['next_visit_date'] ?? ''); ?>">
                                    <div class="error-message" id="next_visit_date_error"></div>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="referral_needed" name="referral_needed" 
                                               <?php echo ($recordData['referral_needed'] ?? $_POST['referral_needed'] ?? 0) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="referral_needed">Referral Needed</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="referral_details" class="form-label">Referral Details</label>
                                    <textarea class="form-control" id="referral_details" name="referral_details" rows="2" placeholder="If referred, provide details"><?php echo htmlspecialchars($recordData['referral_details'] ?? $_POST['referral_details'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <small class="text-muted"><span class="required-indicator">*</span> indicates required field</small>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <?php echo $editMode ? 'Update' : 'Save'; ?> Postnatal Record
                            </button>
                            <button type="button" onclick="window.history.back()" class="btn btn-secondary">Cancel</button>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('postnatalForm');
            const motherSelect = document.getElementById('mother_id');
            const babySelect = document.getElementById('baby_id');
            const babyDetailsContainer = document.getElementById('babyDetailsContainer');
            const loadingIndicator = document.getElementById('loadingBabies');
            const visitDate = document.getElementById('visit_date');
            const nextVisitDate = document.getElementById('next_visit_date');
            const nextVisitDateError = document.getElementById('next_visit_date_error');
            
            // Real-time validation for all required fields
            const requiredFields = form.querySelectorAll('[required]');
            
            requiredFields.forEach(field => {
                field.addEventListener('blur', function() {
                    validateField(this);
                });
                
                field.addEventListener('input', function() {
                    if (this.value.trim() !== '') {
                        this.classList.remove('field-warning');
                        this.classList.add('is-valid');
                        document.getElementById(this.id + '_error').textContent = '';
                    }
                });
            });

            function validateField(field) {
                const errorElement = document.getElementById(field.id + '_error');
                
                if (field.value.trim() === '') {
                    field.classList.add('field-warning');
                    field.classList.remove('is-valid');
                    errorElement.textContent = 'This field is required';
                    return false;
                } else {
                    field.classList.remove('field-warning');
                    field.classList.add('is-valid');
                    errorElement.textContent = '';
                    return true;
                }
            }

            function validateNextVisitDate() {
                const visit = new Date(visitDate.value);
                const next = new Date(nextVisitDate.value);
                
                if (nextVisitDate.value && next <= visit) {
                    nextVisitDate.classList.add('date-error');
                    nextVisitDateError.textContent = 'Next visit date must be after the current visit date.';
                    return false;
                } else {
                    nextVisitDate.classList.remove('date-error');
                    nextVisitDateError.textContent = '';
                    return true;
                }
            }

            // Date validation events
            visitDate.addEventListener('change', function() {
                validateNextVisitDate();
                // Auto-calculate next visit date (2 weeks from visit date) - only for new records
                <?php if (!$editMode): ?>
                if (this.value && !nextVisitDate.value) {
                    const visit = new Date(this.value);
                    visit.setDate(visit.getDate() + 14); // 2 weeks later
                    const nextDate = visit.toISOString().split('T')[0];
                    nextVisitDate.value = nextDate;
                    validateNextVisitDate();
                }
                <?php endif; ?>
            });

            nextVisitDate.addEventListener('change', validateNextVisitDate);

            <?php if (!$editMode): ?>
            motherSelect.addEventListener('change', function() {
                const motherId = this.value;
                
                if (!motherId) {
                    babySelect.innerHTML = '<option value="">Select Baby</option>';
                    babySelect.disabled = true;
                    babyDetailsContainer.innerHTML = '';
                    return;
                }
                
                // Show loading indicator
                loadingIndicator.style.display = 'inline-block';
                babySelect.disabled = true;
                
                // Fetch babies via AJAX
                fetch('../includes/get_babies.php?mother_id=' + motherId)
                    .then(response => response.json())
                    .then(data => {
                        // Update baby dropdown
                        babySelect.innerHTML = '<option value="">Select Baby</option>';
                        if (data.babies && data.babies.length > 0) {
                            data.babies.forEach(baby => {
                                const option = document.createElement('option');
                                option.value = baby.id;
                                option.textContent = `${baby.first_name} ${baby.last_name} (Born: ${new Date(baby.birth_date).toLocaleDateString()})`;
                                babySelect.appendChild(option);
                            });
                            babySelect.disabled = false;
                            
                            // Update baby details
                            if (data.babies.length > 0) {
                                const baby = data.babies[0];
                                babyDetailsContainer.innerHTML = `
                                    <div class="baby-details">
                                        <h5>Baby Details</h5>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <label class="form-label">Baby's Name</label>
                                                <input type="text" class="form-control readonly-info" value="${baby.first_name} ${baby.last_name}" readonly>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Date of Birth</label>
                                                <input type="text" class="form-control readonly-info" value="${new Date(baby.birth_date).toLocaleDateString()}" readonly>
                                            </div>
                                        </div>
                                    </div>
                                `;
                            }
                        } else {
                            babyDetailsContainer.innerHTML = '<div class="alert alert-warning">No babies found for this mother.</div>';
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching babies:', error);
                        babySelect.innerHTML = '<option value="">Error loading babies</option>';
                        babyDetailsContainer.innerHTML = '<div class="alert alert-danger">Error loading baby information.</div>';
                    })
                    .finally(() => {
                        loadingIndicator.style.display = 'none';
                    });
            });
            
            // Trigger change event if a mother is already selected
            if (motherSelect.value) {
                motherSelect.dispatchEvent(new Event('change'));
            }
            <?php endif; ?>
            
            // Form submission validation
            form.addEventListener('submit', function(e) {
                let isValid = true;
                
                // Validate all required fields
                requiredFields.forEach(field => {
                    if (!validateField(field)) {
                        isValid = false;
                    }
                });
                
                // Validate next visit date
                if (!validateNextVisitDate()) {
                    isValid = false;
                }
                
                if (!isValid) {
                    e.preventDefault();
                    alert('Please fill in all required fields correctly before submitting.');
                    
                    // Scroll to first error
                    const firstError = form.querySelector('.field-warning, .date-error');
                    if (firstError) {
                        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        firstError.focus();
                    }
                }
            });

            // Initial validation for fields with existing values
            requiredFields.forEach(field => {
                if (field.value.trim() !== '') {
                    field.classList.add('is-valid');
                }
            });

            // Initial date validation
            validateNextVisitDate();
        });
    </script>
</body>
</html>