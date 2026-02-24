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
    
    // Get existing record data - FIXED FOR YOUR DATABASE STRUCTURE
    $stmt = $pdo->prepare("
        SELECT pr.*, 
               m.first_name as mother_first_name, 
               m.last_name as mother_last_name,
               pd.lmp, pd.edc, pd.gravida, pd.para
        FROM prenatal_records pr
        JOIN mothers m ON pr.mother_id = m.id
        LEFT JOIN pregnancy_details pd ON m.id = pd.mother_id
        WHERE pr.id = ?
    ");
    $stmt->execute([$recordId]);
    $recordData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$recordData) {
        $error = "Prenatal record not found.";
        $editMode = false;
    }
}

// Get mothers for dropdown
$mothers = $pdo->query("
    SELECT m.id, m.first_name, m.last_name 
    FROM mothers m 
    ORDER BY m.last_name, m.first_name
")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $motherId = $_POST['mother_id'];
    $visitDate = $_POST['visit_date'];
    $gestationalAge = trim($_POST['gestational_age']);
    $visitNumber = $_POST['visit_number'];
    $bloodPressure = trim($_POST['blood_pressure']);
    $weight = $_POST['weight'];
    $temperature = $_POST['temperature'];
    $complaints = trim($_POST['complaints']);
    $findings = trim($_POST['findings']);
    $diagnosis = trim($_POST['diagnosis']);
    $treatment = trim($_POST['treatment']);
    $ironSupplement = isset($_POST['iron_supplement']) ? 1 : 0;
    $folicAcid = isset($_POST['folic_acid']) ? 1 : 0;
    $calcium = isset($_POST['calcium']) ? 1 : 0;
    $otherMeds = trim($_POST['other_meds']);
    $nextVisitDate = $_POST['next_visit_date'];
    
    // Laboratory values
    $hbLevel = $_POST['hb_level'] ?? null;
    $bloodGroup = $_POST['blood_group'] ?? '';
    $rhesusFactor = $_POST['rhesus_factor'] ?? '';
    $urinalysis = trim($_POST['urinalysis'] ?? '');
    $bloodSugar = $_POST['blood_sugar'] ?? null;
    $hivStatus = $_POST['hiv_status'] ?? '';
    $hepatitisB = $_POST['hepatitis_b'] ?? '';
    $vdrl = $_POST['vdrl'] ?? '';
    $otherTests = trim($_POST['other_tests'] ?? '');
    
    $userId = $_SESSION['user_id'];
    
    // Date validation
    if (!empty($nextVisitDate) && $nextVisitDate <= $visitDate) {
        $error = "Next visit date must be after the current visit date.";
    } else {
        if ($editMode) {
            // UPDATE existing record
            $sql = "UPDATE prenatal_records SET 
                    mother_id = ?, visit_date = ?, gestational_age = ?, visit_number = ?, 
                    blood_pressure = ?, weight = ?, temperature = ?, complaints = ?, 
                    findings = ?, diagnosis = ?, treatment = ?, iron_supplement = ?, 
                    folic_acid = ?, calcium = ?, other_meds = ?, next_visit_date = ?, 
                    hb_level = ?, blood_group = ?, rhesus_factor = ?, urinalysis = ?, 
                    blood_sugar = ?, hiv_status = ?, hepatitis_b = ?, vdrl = ?, other_tests = ?
                    WHERE id = ?";
            
            $stmt = $pdo->prepare($sql);
            
            if ($stmt->execute([
                $motherId, $visitDate, $gestationalAge, $visitNumber, $bloodPressure, $weight, $temperature,
                $complaints, $findings, $diagnosis, $treatment, $ironSupplement, $folicAcid, $calcium,
                $otherMeds, $nextVisitDate, $hbLevel, $bloodGroup, $rhesusFactor, $urinalysis,
                $bloodSugar, $hivStatus, $hepatitisB, $vdrl, $otherTests, $recordId
            ])) {
                $message = "Prenatal record updated successfully!";
                logActivity($userId, "Updated prenatal visit record ID: $recordId");
            } else {
                $error = "Failed to update prenatal record. Please try again.";
            }
        } else {
            // INSERT new record
            $sql = "INSERT INTO prenatal_records 
                    (mother_id, visit_date, gestational_age, visit_number, blood_pressure, weight, temperature, 
                     complaints, findings, diagnosis, treatment, iron_supplement, folic_acid, calcium, 
                     other_meds, next_visit_date, hb_level, blood_group, rhesus_factor, urinalysis, 
                     blood_sugar, hiv_status, hepatitis_b, vdrl, other_tests, recorded_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $pdo->prepare($sql);
            
            if ($stmt->execute([
                $motherId, $visitDate, $gestationalAge, $visitNumber, $bloodPressure, $weight, $temperature,
                $complaints, $findings, $diagnosis, $treatment, $ironSupplement, $folicAcid, $calcium,
                $otherMeds, $nextVisitDate, $hbLevel, $bloodGroup, $rhesusFactor, $urinalysis,
                $bloodSugar, $hivStatus, $hepatitisB, $vdrl, $otherTests, $userId
            ])) {
                $message = "Prenatal record saved successfully!";
                logActivity($userId, "Recorded prenatal visit for mother ID: $motherId");
                
                // Clear form after successful submission (only for new records)
                if (!$editMode) {
                    unset($_POST);
                    $recordData = [];
                }
            } else {
                $error = "Failed to save prenatal record. Please try again.";
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
    <title><?php echo $editMode ? 'Edit' : 'New'; ?> Prenatal Care - Health Station System </title>
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
        
        .lab-test-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .lab-test-card {
            background-color: #f0f7ff;
            border-radius: 5px;
            padding: 15px;
            border-left: 4px solid #0d6efd;
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
    </style>
</head>
<body>
    <?php include_once '../includes/header.php'; ?>
    
    <div class="flex flex-col lg:flex-row min-h-[calc(100vh-4rem)]">
        <?php include_once '../includes/sidebar.php'; ?>
        
        <main class="flex-1 p-4 lg:p-8 space-y-8 no-print">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><?php echo $editMode ? 'Edit' : 'New'; ?> Prenatal Care Record</h1>
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
                        <form method="POST" action="" id="prenatalForm" novalidate>
                            <?php if ($editMode): ?>
                            <input type="hidden" name="record_id" value="<?php echo $recordData['id']; ?>">
                            <?php endif; ?>
                            
                            <div class="row mb-3 required-field">
                                <div class="col-md-6">
                                    <label for="mother_id" class="form-label">Mother</label>
                                    <select class="form-select" id="mother_id" name="mother_id" required <?php echo $editMode ? 'disabled' : ''; ?>>
                                        <option value="">Select Mother</option>
                                        <?php foreach ($mothers as $mother): ?>
                                        <option value="<?php echo $mother['id']; ?>" 
                                            <?php echo (($recordData['mother_id'] ?? '') == $mother['id'] || ($_POST['mother_id'] ?? '') == $mother['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($mother['first_name'] . ' ' . $mother['last_name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if ($editMode): ?>
                                    <input type="hidden" name="mother_id" value="<?php echo $recordData['mother_id']; ?>">
                                    <small class="text-muted">Mother cannot be changed in edit mode</small>
                                    <?php endif; ?>
                                    <div class="error-message" id="mother_id_error"></div>
                                </div>
                            </div>
                            
                            <h5 class="mb-3"><i class="fas fa-calendar-alt"></i> Visit Information</h5>
                            <div class="row mb-3">
                                <div class="col-md-4 required-field">
                                    <label for="visit_date" class="form-label">Visit Date</label>
                                    <input type="date" class="form-control" id="visit_date" name="visit_date" required 
                                           value="<?php echo htmlspecialchars($recordData['visit_date'] ?? $_POST['visit_date'] ?? date('Y-m-d')); ?>">
                                    <div class="error-message" id="visit_date_error"></div>
                                </div>
                                <div class="col-md-4 required-field">
                                    <label for="gestational_age" class="form-label">Gestational Age</label>
                                    <input type="text" class="form-control" id="gestational_age" name="gestational_age" placeholder="e.g. 12 weeks" required
                                           value="<?php echo htmlspecialchars($recordData['gestational_age'] ?? $_POST['gestational_age'] ?? ''); ?>">
                                    <div class="error-message" id="gestational_age_error"></div>
                                </div>
                                <div class="col-md-4 required-field">
                                    <label for="visit_number" class="form-label">Visit Number</label>
                                    <input type="number" class="form-control" id="visit_number" name="visit_number" placeholder="e.g. 1" required min="1"
                                           value="<?php echo htmlspecialchars($recordData['visit_number'] ?? $_POST['visit_number'] ?? ''); ?>">
                                    <div class="error-message" id="visit_number_error"></div>
                                </div>
                            </div>
                            
                            <h5 class="mb-3"><i class="fas fa-heartbeat"></i> Vital Signs</h5>
                            <div class="vital-signs-grid mb-3">
                                <div class="vital-sign-card required-field">
                                    <div class="vital-sign-label">Blood Pressure</div>
                                    <div class="vital-sign-value">120/80</div>
                                    <input type="text" class="form-control form-control-sm" name="blood_pressure" placeholder="mmHg" required
                                           value="<?php echo htmlspecialchars($recordData['blood_pressure'] ?? $_POST['blood_pressure'] ?? ''); ?>">
                                    <div class="error-message" id="blood_pressure_error"></div>
                                </div>
                                <div class="vital-sign-card required-field">
                                    <div class="vital-sign-label">Weight</div>
                                    <div class="vital-sign-value">58</div>
                                    <input type="number" step="0.1" class="form-control form-control-sm" name="weight" placeholder="kg" required
                                           value="<?php echo htmlspecialchars($recordData['weight'] ?? $_POST['weight'] ?? ''); ?>">
                                    <div class="error-message" id="weight_error"></div>
                                </div>
                                <div class="vital-sign-card required-field">
                                    <div class="vital-sign-label">Temperature</div>
                                    <div class="vital-sign-value">36.8</div>
                                    <input type="number" step="0.1" class="form-control form-control-sm" name="temperature" placeholder="°C" required
                                           value="<?php echo htmlspecialchars($recordData['temperature'] ?? $_POST['temperature'] ?? ''); ?>">
                                    <div class="error-message" id="temperature_error"></div>
                                </div>
                            </div>
                            
                            <!-- Laboratory Section -->
                            <h5 class="mb-3"><i class="fas fa-flask"></i> Laboratory Tests</h5>
                            <div class="lab-test-grid mb-3">
                                <div class="lab-test-card">
                                    <label for="hb_level" class="form-label">Hemoglobin (Hb)</label>
                                    <input type="number" step="0.1" class="form-control" id="hb_level" name="hb_level" placeholder="g/dL"
                                           value="<?php echo htmlspecialchars($recordData['hb_level'] ?? $_POST['hb_level'] ?? ''); ?>">
                                    <small class="text-muted">Normal: 11.5-16 g/dL</small>
                                </div>
                                <div class="lab-test-card">
                                    <label for="blood_group" class="form-label">Blood Group</label>
                                    <select class="form-select" id="blood_group" name="blood_group">
                                        <option value="">Select</option>
                                        <option value="A" <?php echo (($recordData['blood_group'] ?? '') == 'A' || ($_POST['blood_group'] ?? '') == 'A') ? 'selected' : ''; ?>>A</option>
                                        <option value="B" <?php echo (($recordData['blood_group'] ?? '') == 'B' || ($_POST['blood_group'] ?? '') == 'B') ? 'selected' : ''; ?>>B</option>
                                        <option value="AB" <?php echo (($recordData['blood_group'] ?? '') == 'AB' || ($_POST['blood_group'] ?? '') == 'AB') ? 'selected' : ''; ?>>AB</option>
                                        <option value="O" <?php echo (($recordData['blood_group'] ?? '') == 'O' || ($_POST['blood_group'] ?? '') == 'O') ? 'selected' : ''; ?>>O</option>
                                    </select>
                                </div>
                                <div class="lab-test-card">
                                    <label for="rhesus_factor" class="form-label">Rhesus Factor</label>
                                    <select class="form-select" id="rhesus_factor" name="rhesus_factor">
                                        <option value="">Select</option>
                                        <option value="Positive" <?php echo (($recordData['rhesus_factor'] ?? '') == 'Positive' || ($_POST['rhesus_factor'] ?? '') == 'Positive') ? 'selected' : ''; ?>>Positive</option>
                                        <option value="Negative" <?php echo (($recordData['rhesus_factor'] ?? '') == 'Negative' || ($_POST['rhesus_factor'] ?? '') == 'Negative') ? 'selected' : ''; ?>>Negative</option>
                                    </select>
                                </div>
                                <div class="lab-test-card">
                                    <label for="blood_sugar" class="form-label">Blood Sugar</label>
                                    <input type="number" step="0.1" class="form-control" id="blood_sugar" name="blood_sugar" placeholder="mg/dL"
                                           value="<?php echo htmlspecialchars($recordData['blood_sugar'] ?? $_POST['blood_sugar'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="urinalysis" class="form-label">Urinalysis</label>
                                    <textarea class="form-control" id="urinalysis" name="urinalysis" rows="2" placeholder="Protein, glucose, ketones, etc."><?php echo htmlspecialchars($recordData['urinalysis'] ?? $_POST['urinalysis'] ?? ''); ?></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label for="other_tests" class="form-label">Other Tests</label>
                                    <textarea class="form-control" id="other_tests" name="other_tests" rows="2" placeholder="Other laboratory findings"><?php echo htmlspecialchars($recordData['other_tests'] ?? $_POST['other_tests'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="hiv_status" class="form-label">HIV Status</label>
                                    <select class="form-select" id="hiv_status" name="hiv_status">
                                        <option value="">Select</option>
                                        <option value="Negative" <?php echo (($recordData['hiv_status'] ?? '') == 'Negative' || ($_POST['hiv_status'] ?? '') == 'Negative') ? 'selected' : ''; ?>>Negative</option>
                                        <option value="Positive" <?php echo (($recordData['hiv_status'] ?? '') == 'Positive' || ($_POST['hiv_status'] ?? '') == 'Positive') ? 'selected' : ''; ?>>Positive</option>
                                        <option value="Not Tested" <?php echo (($recordData['hiv_status'] ?? '') == 'Not Tested' || ($_POST['hiv_status'] ?? '') == 'Not Tested') ? 'selected' : ''; ?>>Not Tested</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="hepatitis_b" class="form-label">Hepatitis B</label>
                                    <select class="form-select" id="hepatitis_b" name="hepatitis_b">
                                        <option value="">Select</option>
                                        <option value="Negative" <?php echo (($recordData['hepatitis_b'] ?? '') == 'Negative' || ($_POST['hepatitis_b'] ?? '') == 'Negative') ? 'selected' : ''; ?>>Negative</option>
                                        <option value="Positive" <?php echo (($recordData['hepatitis_b'] ?? '') == 'Positive' || ($_POST['hepatitis_b'] ?? '') == 'Positive') ? 'selected' : ''; ?>>Positive</option>
                                        <option value="Not Tested" <?php echo (($recordData['hepatitis_b'] ?? '') == 'Not Tested' || ($_POST['hepatitis_b'] ?? '') == 'Not Tested') ? 'selected' : ''; ?>>Not Tested</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="vdrl" class="form-label">VDRL/RPR</label>
                                    <select class="form-select" id="vdrl" name="vdrl">
                                        <option value="">Select</option>
                                        <option value="Non-reactive" <?php echo (($recordData['vdrl'] ?? '') == 'Non-reactive' || ($_POST['vdrl'] ?? '') == 'Non-reactive') ? 'selected' : ''; ?>>Non-reactive</option>
                                        <option value="Reactive" <?php echo (($recordData['vdrl'] ?? '') == 'Reactive' || ($_POST['vdrl'] ?? '') == 'Reactive') ? 'selected' : ''; ?>>Reactive</option>
                                        <option value="Not Tested" <?php echo (($recordData['vdrl'] ?? '') == 'Not Tested' || ($_POST['vdrl'] ?? '') == 'Not Tested') ? 'selected' : ''; ?>>Not Tested</option>
                                    </select>
                                </div>
                            </div>
                            
                            <h5 class="mb-3"><i class="fas fa-notes-medical"></i> Medical Assessment</h5>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="complaints" class="form-label">Chief Complaints</label>
                                    <textarea class="form-control" id="complaints" name="complaints" rows="3" placeholder="Enter any complaints"><?php echo htmlspecialchars($recordData['complaints'] ?? $_POST['complaints'] ?? ''); ?></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label for="findings" class="form-label">Physical Findings</label>
                                    <textarea class="form-control" id="findings" name="findings" rows="3" placeholder="Enter physical findings"><?php echo htmlspecialchars($recordData['findings'] ?? $_POST['findings'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="diagnosis" class="form-label">Diagnosis</label>
                                    <input type="text" class="form-control" id="diagnosis" name="diagnosis" placeholder="Enter diagnosis"
                                           value="<?php echo htmlspecialchars($recordData['diagnosis'] ?? $_POST['diagnosis'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="treatment" class="form-label">Treatment Given</label>
                                    <input type="text" class="form-control" id="treatment" name="treatment" placeholder="Enter treatment"
                                           value="<?php echo htmlspecialchars($recordData['treatment'] ?? $_POST['treatment'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <h5 class="mb-3"><i class="fas fa-pills"></i> Medications & Supplements</h5>
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="iron_supplement" name="iron_supplement" 
                                               <?php echo ($recordData['iron_supplement'] ?? $_POST['iron_supplement'] ?? 0) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="iron_supplement">Iron Supplement</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="folic_acid" name="folic_acid"
                                               <?php echo ($recordData['folic_acid'] ?? $_POST['folic_acid'] ?? 0) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="folic_acid">Folic Acid</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="calcium" name="calcium"
                                               <?php echo ($recordData['calcium'] ?? $_POST['calcium'] ?? 0) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="calcium">Calcium</label>
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label for="other_meds" class="form-label">Other Medications</label>
                                    <textarea class="form-control" id="other_meds" name="other_meds" rows="2" placeholder="List other medications"><?php echo htmlspecialchars($recordData['other_meds'] ?? $_POST['other_meds'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            
                            <h5 class="mb-3"><i class="fas fa-calendar-check"></i> Next Appointment</h5>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="next_visit_date" class="form-label">Next Visit Date</label>
                                    <input type="date" class="form-control" id="next_visit_date" name="next_visit_date"
                                           value="<?php echo htmlspecialchars($recordData['next_visit_date'] ?? $_POST['next_visit_date'] ?? ''); ?>">
                                    <div class="error-message" id="dateError"></div>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <small class="text-muted"><span class="required-indicator">*</span> indicates required field</small>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <?php echo $editMode ? 'Update' : 'Save'; ?> Prenatal Record
                            </button>
                            <button type="button" onclick="window.history.back()" class="btn btn-secondary">Cancel</button>
                        </form>
                    </div>
                </div>
            </main>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('prenatalForm');
            const visitDate = document.getElementById('visit_date');
            const nextVisitDate = document.getElementById('next_visit_date');
            const dateError = document.getElementById('dateError');

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

            function validateDates() {
                const visit = new Date(visitDate.value);
                const next = new Date(nextVisitDate.value);
                
                if (nextVisitDate.value && next <= visit) {
                    dateError.textContent = 'Next visit date must be after the current visit date.';
                    nextVisitDate.classList.add('field-warning');
                    return false;
                } else {
                    dateError.textContent = '';
                    nextVisitDate.classList.remove('field-warning');
                    return true;
                }
            }

            visitDate.addEventListener('change', validateDates);
            nextVisitDate.addEventListener('change', validateDates);

            form.addEventListener('submit', function(e) {
                let isValid = true;
                
                // Validate all required fields
                requiredFields.forEach(field => {
                    if (!validateField(field)) {
                        isValid = false;
                    }
                });
                
                // Validate dates
                if (!validateDates()) {
                    isValid = false;
                }
                
                if (!isValid) {
                    e.preventDefault();
                    alert('Please fill in all required fields correctly before submitting.');
                    
                    // Scroll to first error
                    const firstError = form.querySelector('.field-warning');
                    if (firstError) {
                        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        firstError.focus();
                    }
                }
            });

            // Auto-calculate next visit date (4 weeks from visit date)
            visitDate.addEventListener('change', function() {
                if (this.value && !nextVisitDate.value) {
                    const visit = new Date(this.value);
                    visit.setDate(visit.getDate() + 28); // 4 weeks later
                    const nextDate = visit.toISOString().split('T')[0];
                    nextVisitDate.value = nextDate;
                    validateDates();
                }
            });

            // Initial validation for edit mode
            if (<?php echo $editMode ? 'true' : 'false'; ?>) {
                requiredFields.forEach(field => {
                    if (field.value.trim() !== '') {
                        field.classList.add('is-valid');
                    }
                });
            }
        });
    </script>
</body>
</html>