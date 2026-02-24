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
</head>
<body class="bg-health-50 font-inter text-slate-900 antialiased selection:bg-health-100 selection:text-health-700">
    <?php include_once '../includes/header.php'; ?>
    
    <div class="flex flex-col lg:flex-row min-h-[calc(100vh-4rem)]">
        <?php include_once '../includes/sidebar.php'; ?>
        
        <main class="flex-1 p-4 lg:p-8 space-y-8 no-print">
            <!-- Header Section -->
            <header class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-black text-slate-900 tracking-tight">
                        <?php echo $editMode ? 'Edit Prenatal Record' : 'New Prenatal Visit'; ?>
                    </h1>
                    <p class="text-slate-500 font-medium mt-1 italic">Comprehensive maternal health monitoring</p>
                </div>
                
                <?php if ($editMode): ?>
                    <div class="badge-edit flex items-center gap-2 group">
                        <div class="w-2 h-2 rounded-full bg-rose-500 animate-pulse"></div>
                        <span class="text-rose-700 font-bold uppercase tracking-widest text-[10px]">Active Edit Mode</span>
                    </div>
                <?php endif; ?>
            </header>

            <!-- Alerts Section -->
            <?php if ($message): ?>
                <div class="p-6 bg-emerald-50 border border-emerald-100 rounded-3xl flex items-start gap-4 shadow-sm animate-in fade-in slide-in-from-top-4 duration-500">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-500 flex items-center justify-center shrink-0 shadow-lg shadow-emerald-200">
                        <i class="fas fa-check text-white text-xl"></i>
                    </div>
                    <div class="space-y-1 pt-1">
                        <h3 class="text-emerald-900 font-black text-lg leading-tight uppercase tracking-tight">Operation Successful</h3>
                        <div class="text-emerald-700 font-medium text-sm leading-relaxed opacity-90"><?php echo $message; ?></div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="p-6 bg-rose-50 border border-rose-100 rounded-3xl flex items-start gap-4 shadow-sm animate-in fade-in slide-in-from-top-4 duration-500">
                    <div class="w-12 h-12 rounded-2xl bg-rose-500 flex items-center justify-center shrink-0 shadow-lg shadow-rose-200">
                        <i class="fas fa-exclamation-triangle text-white text-xl"></i>
                    </div>
                    <div class="space-y-1 pt-1">
                        <h3 class="text-rose-900 font-black text-lg leading-tight uppercase tracking-tight">System Error</h3>
                        <div class="text-rose-700 font-medium text-sm leading-relaxed opacity-90"><?php echo $error; ?></div>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="prenatalForm" novalidate class="space-y-8">
                <?php if ($editMode): ?>
                    <input type="hidden" name="record_id" value="<?php echo $recordData['id']; ?>">
                <?php endif; ?>

                <!-- Patient Selection -->
                <section class="card-premium">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-user-circle text-health-600"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-slate-800">Patient Identification</h2>
                            <p class="text-xs text-slate-400 font-bold uppercase tracking-widest mt-0.5">Select the mother for this prenatal visit</p>
                        </div>
                    </div>

                    <div class="max-w-xl">
                        <label for="mother_id" class="form-label-premium">Mother's Name <span class="text-rose-500">*</span></label>
                        <select class="form-input-premium appearance-none" id="mother_id" name="mother_id" required <?php echo $editMode ? 'disabled' : ''; ?>>
                            <option value="">Select Mother from Records</option>
                            <?php foreach ($mothers as $mother): ?>
                                <option value="<?php echo $mother['id']; ?>" 
                                    <?php echo (($recordData['mother_id'] ?? '') == $mother['id'] || ($_POST['mother_id'] ?? '') == $mother['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($mother['last_name'] . ', ' . $mother['first_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($editMode): ?>
                            <input type="hidden" name="mother_id" value="<?php echo $recordData['mother_id']; ?>">
                            <p class="mt-2 text-xs text-slate-400 italic">Mother profile cannot be changed during record updates</p>
                        <?php endif; ?>
                        <div class="hidden mt-2 p-2 bg-rose-50 text-rose-600 text-xs font-bold rounded-lg border border-rose-100" id="mother_id_warning">
                            <i class="fas fa-exclamation-triangle me-1"></i> Please select a mother
                        </div>
                    </div>
                </section>

                <!-- Visit Details -->
                <section class="card-premium">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-calendar-check text-health-600"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-slate-800">Visit & Gestation</h2>
                            <p class="text-xs text-slate-400 font-bold uppercase tracking-widest mt-0.5">Clinical timing and pregnancy progress</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label for="visit_date" class="form-label-premium">Visit Date <span class="text-rose-500">*</span></label>
                            <input type="date" class="form-input-premium" id="visit_date" name="visit_date" required 
                                   value="<?php echo htmlspecialchars($recordData['visit_date'] ?? $_POST['visit_date'] ?? date('Y-m-d')); ?>">
                            <div class="hidden mt-2 p-2 bg-rose-50 text-rose-600 text-xs font-bold rounded-lg border border-rose-100" id="visit_date_warning">
                                <i class="fas fa-exclamation-triangle me-1"></i> Visit date is required
                            </div>
                        </div>
                        <div>
                            <label for="gestational_age" class="form-label-premium">Gestational Age <span class="text-rose-500">*</span></label>
                            <input type="text" class="form-input-premium" id="gestational_age" name="gestational_age" placeholder="e.g. 12 weeks" required
                                   value="<?php echo htmlspecialchars($recordData['gestational_age'] ?? $_POST['gestational_age'] ?? ''); ?>">
                            <div class="hidden mt-2 p-2 bg-rose-50 text-rose-600 text-xs font-bold rounded-lg border border-rose-100" id="gestational_age_warning">
                                <i class="fas fa-exclamation-triangle me-1"></i> Required
                            </div>
                        </div>
                        <div>
                            <label for="visit_number" class="form-label-premium">Visit Number <span class="text-rose-500">*</span></label>
                            <input type="number" class="form-input-premium" id="visit_number" name="visit_number" placeholder="e.g. 1" required min="1"
                                   value="<?php echo htmlspecialchars($recordData['visit_number'] ?? $_POST['visit_number'] ?? ''); ?>">
                            <div class="hidden mt-2 p-2 bg-rose-50 text-rose-600 text-xs font-bold rounded-lg border border-rose-100" id="visit_number_warning">
                                <i class="fas fa-exclamation-triangle me-1"></i> Required
                            </div>
                        </div>
                    </div>
                </section>
                            
                <!-- Vital Signs -->
                <section class="card-premium">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-heartbeat text-health-600"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-slate-800">Vital Signs</h2>
                            <p class="text-xs text-slate-400 font-bold uppercase tracking-widest mt-0.5">Core physiological health indicators</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                        <div class="p-6 bg-slate-50 rounded-3xl border border-slate-100 space-y-4">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-slate-400 uppercase tracking-widest">Blood Pressure</span>
                                <i class="fas fa-gauge-high text-health-600 opacity-20 text-2xl"></i>
                            </div>
                            <div class="flex items-baseline gap-2">
                                <input type="text" class="w-full bg-transparent border-0 border-b-2 border-slate-200 focus:ring-0 focus:border-health-600 text-3xl font-black text-health-700 p-0" 
                                       id="blood_pressure" name="blood_pressure" placeholder="120/80" required
                                       value="<?php echo htmlspecialchars($recordData['blood_pressure'] ?? $_POST['blood_pressure'] ?? ''); ?>">
                                <span class="text-slate-400 font-bold text-xs">mmHg</span>
                            </div>
                            <div class="hidden p-2 bg-rose-50 text-rose-600 text-[10px] font-bold rounded-lg border border-rose-100" id="blood_pressure_warning">
                                Required
                            </div>
                        </div>

                        <div class="p-6 bg-slate-50 rounded-3xl border border-slate-100 space-y-4">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-slate-400 uppercase tracking-widest">Weight</span>
                                <i class="fas fa-weight text-health-600 opacity-20 text-2xl"></i>
                            </div>
                            <div class="flex items-baseline gap-2">
                                <input type="number" step="0.1" class="w-full bg-transparent border-0 border-b-2 border-slate-200 focus:ring-0 focus:border-health-600 text-3xl font-black text-health-700 p-0" 
                                       id="weight" name="weight" placeholder="58.0" required
                                       value="<?php echo htmlspecialchars($recordData['weight'] ?? $_POST['weight'] ?? ''); ?>">
                                <span class="text-slate-400 font-bold text-xs">kg</span>
                            </div>
                            <div class="hidden p-2 bg-rose-50 text-rose-600 text-[10px] font-bold rounded-lg border border-rose-100" id="weight_warning">
                                Required
                            </div>
                        </div>

                        <div class="p-6 bg-slate-50 rounded-3xl border border-slate-100 space-y-4">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-slate-400 uppercase tracking-widest">Temperature</span>
                                <i class="fas fa-thermometer-half text-health-600 opacity-20 text-2xl"></i>
                            </div>
                            <div class="flex items-baseline gap-2">
                                <input type="number" step="0.1" class="w-full bg-transparent border-0 border-b-2 border-slate-200 focus:ring-0 focus:border-health-600 text-3xl font-black text-health-700 p-0" 
                                       id="temperature" name="temperature" placeholder="36.8" required
                                       value="<?php echo htmlspecialchars($recordData['temperature'] ?? $_POST['temperature'] ?? ''); ?>">
                                <span class="text-slate-400 font-bold text-xs">°C</span>
                            </div>
                            <div class="hidden p-2 bg-rose-50 text-rose-600 text-[10px] font-bold rounded-lg border border-rose-100" id="temperature_warning">
                                Required
                            </div>
                        </div>
                    </div>
                </section>
                            
                <!-- Laboratory Tests -->
                <section class="card-premium">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-flask text-health-600"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-slate-800">Laboratory Profile</h2>
                            <p class="text-xs text-slate-400 font-bold uppercase tracking-widest mt-0.5">Diagnostic test results and clinical findings</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8 border-b border-slate-100 pb-8">
                        <div>
                            <label for="hb_level" class="form-label-premium">Hemoglobin (Hb)</label>
                            <div class="relative">
                                <input type="number" step="0.1" class="form-input-premium pr-12" id="hb_level" name="hb_level" placeholder="12.0"
                                       value="<?php echo htmlspecialchars($recordData['hb_level'] ?? $_POST['hb_level'] ?? ''); ?>">
                                <span class="absolute right-4 top-1/2 -translate-y-1/2 text-[10px] font-black text-slate-400 uppercase">g/dL</span>
                            </div>
                            <p class="mt-2 text-[10px] text-slate-400 font-bold uppercase tracking-wider">Ref Range: 11.5 - 16.0</p>
                        </div>
                        <div>
                            <label for="blood_group" class="form-label-premium">Blood Group</label>
                            <select class="form-input-premium appearance-none font-bold" id="blood_group" name="blood_group">
                                <option value="">Select</option>
                                <option value="A" <?php echo (($recordData['blood_group'] ?? '') == 'A' || ($_POST['blood_group'] ?? '') == 'A') ? 'selected' : ''; ?>>A</option>
                                <option value="B" <?php echo (($recordData['blood_group'] ?? '') == 'B' || ($_POST['blood_group'] ?? '') == 'B') ? 'selected' : ''; ?>>B</option>
                                <option value="AB" <?php echo (($recordData['blood_group'] ?? '') == 'AB' || ($_POST['blood_group'] ?? '') == 'AB') ? 'selected' : ''; ?>>AB</option>
                                <option value="O" <?php echo (($recordData['blood_group'] ?? '') == 'O' || ($_POST['blood_group'] ?? '') == 'O') ? 'selected' : ''; ?>>O</option>
                            </select>
                        </div>
                        <div>
                            <label for="rhesus_factor" class="form-label-premium">Rhesus Factor</label>
                            <select class="form-input-premium appearance-none" id="rhesus_factor" name="rhesus_factor">
                                <option value="">Select</option>
                                <option value="Positive" <?php echo (($recordData['rhesus_factor'] ?? '') == 'Positive' || ($_POST['rhesus_factor'] ?? '') == 'Positive') ? 'selected' : ''; ?>>Positive</option>
                                <option value="Negative" <?php echo (($recordData['rhesus_factor'] ?? '') == 'Negative' || ($_POST['rhesus_factor'] ?? '') == 'Negative') ? 'selected' : ''; ?>>Negative</option>
                            </select>
                        </div>
                        <div>
                            <label for="blood_sugar" class="form-label-premium">Blood Sugar</label>
                            <div class="relative">
                                <input type="number" step="0.1" class="form-input-premium pr-12" id="blood_sugar" name="blood_sugar" placeholder="95"
                                       value="<?php echo htmlspecialchars($recordData['blood_sugar'] ?? $_POST['blood_sugar'] ?? ''); ?>">
                                <span class="absolute right-4 top-1/2 -translate-y-1/2 text-[10px] font-black text-slate-400 uppercase">mg/dL</span>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8 border-b border-slate-100 pb-8">
                        <div>
                            <label for="urinalysis" class="form-label-premium">Urinalysis Findings</label>
                            <textarea class="form-input-premium min-h-[100px] py-3 text-sm" id="urinalysis" name="urinalysis" placeholder="Protein, Glucose, Ketones, Pus cells..."><?php echo htmlspecialchars($recordData['urinalysis'] ?? $_POST['urinalysis'] ?? ''); ?></textarea>
                        </div>
                        <div>
                            <label for="other_tests" class="form-label-premium">Supplemental Test Results</label>
                            <textarea class="form-input-premium min-h-[100px] py-3 text-sm" id="other_tests" name="other_tests" placeholder="USG findings, etc..."><?php echo htmlspecialchars($recordData['other_tests'] ?? $_POST['other_tests'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                        <div>
                            <label for="hiv_status" class="form-label-premium">HIV Screening</label>
                            <select class="form-input-premium appearance-none text-sm" id="hiv_status" name="hiv_status">
                                <option value="">Select Status</option>
                                <option value="Negative" <?php echo (($recordData['hiv_status'] ?? '') == 'Negative' || ($_POST['hiv_status'] ?? '') == 'Negative') ? 'selected' : ''; ?>>Negative</option>
                                <option value="Positive" <?php echo (($recordData['hiv_status'] ?? '') == 'Positive' || ($_POST['hiv_status'] ?? '') == 'Positive') ? 'selected' : ''; ?>>Positive</option>
                                <option value="Not Tested" <?php echo (($recordData['hiv_status'] ?? '') == 'Not Tested' || ($_POST['hiv_status'] ?? '') == 'Not Tested') ? 'selected' : ''; ?>>Not Tested</option>
                            </select>
                        </div>
                        <div>
                            <label for="hepatitis_b" class="form-label-premium">Hepatitis B (HBsAg)</label>
                            <select class="form-input-premium appearance-none text-sm" id="hepatitis_b" name="hepatitis_b">
                                <option value="">Select Status</option>
                                <option value="Negative" <?php echo (($recordData['hepatitis_b'] ?? '') == 'Negative' || ($_POST['hepatitis_b'] ?? '') == 'Negative') ? 'selected' : ''; ?>>Negative</option>
                                <option value="Positive" <?php echo (($recordData['hepatitis_b'] ?? '') == 'Positive' || ($_POST['hepatitis_b'] ?? '') == 'Positive') ? 'selected' : ''; ?>>Positive</option>
                                <option value="Not Tested" <?php echo (($recordData['hepatitis_b'] ?? '') == 'Not Tested' || ($_POST['hepatitis_b'] ?? '') == 'Not Tested') ? 'selected' : ''; ?>>Not Tested</option>
                            </select>
                        </div>
                        <div>
                            <label for="vdrl" class="form-label-premium">Syphilis (VDRL/RPR)</label>
                            <select class="form-input-premium appearance-none text-sm" id="vdrl" name="vdrl">
                                <option value="">Select Status</option>
                                <option value="Non-reactive" <?php echo (($recordData['vdrl'] ?? '') == 'Non-reactive' || ($_POST['vdrl'] ?? '') == 'Non-reactive') ? 'selected' : ''; ?>>Non-reactive</option>
                                <option value="Reactive" <?php echo (($recordData['vdrl'] ?? '') == 'Reactive' || ($_POST['vdrl'] ?? '') == 'Reactive') ? 'selected' : ''; ?>>Reactive</option>
                                <option value="Not Tested" <?php echo (($recordData['vdrl'] ?? '') == 'Not Tested' || ($_POST['vdrl'] ?? '') == 'Not Tested') ? 'selected' : ''; ?>>Not Tested</option>
                            </select>
                        </div>
                    </div>
                </section>
                            
                <!-- Medical Assessment -->
                <section class="card-premium">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-notes-medical text-health-600"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-slate-800">Clinical Evaluation</h2>
                            <p class="text-xs text-slate-400 font-bold uppercase tracking-widest mt-0.5">Physical findings and provider impressions</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                        <div>
                            <label for="complaints" class="form-label-premium">Chief Complaints</label>
                            <textarea class="form-input-premium min-h-[100px] py-3 text-sm" id="complaints" name="complaints" placeholder="Reason for visit or current issues..."><?php echo htmlspecialchars($recordData['complaints'] ?? $_POST['complaints'] ?? ''); ?></textarea>
                        </div>
                        <div>
                            <label for="findings" class="form-label-premium">Physical Findings</label>
                            <textarea class="form-input-premium min-h-[100px] py-3 text-sm" id="findings" name="findings" placeholder="Exam results, fetal movement, etc..."><?php echo htmlspecialchars($recordData['findings'] ?? $_POST['findings'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            <label for="diagnosis" class="form-label-premium text-health-900">Diagnosis / Assessment</label>
                            <input type="text" class="form-input-premium border-health-100 focus:border-health-600 focus:ring-health-200" id="diagnosis" name="diagnosis" placeholder="Primary diagnosis..."
                                   value="<?php echo htmlspecialchars($recordData['diagnosis'] ?? $_POST['diagnosis'] ?? ''); ?>">
                        </div>
                        <div>
                            <label for="treatment" class="form-label-premium text-health-900">Management Plan / Treatment</label>
                            <input type="text" class="form-input-premium border-health-100 focus:border-health-600 focus:ring-health-200" id="treatment" name="treatment" placeholder="Actions taken or prescribed..."
                                   value="<?php echo htmlspecialchars($recordData['treatment'] ?? $_POST['treatment'] ?? ''); ?>">
                        </div>
                    </div>
                </section>

                <!-- Medications & Supplements -->
                <section class="card-premium border-l-4 border-sky-500">
                    <div class="section-header">
                        <div class="section-icon bg-sky-50">
                            <i class="fas fa-pills text-sky-600 font-black"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-slate-800">Maternal Supplements</h2>
                            <p class="text-xs text-slate-400 font-bold uppercase tracking-widest mt-0.5">Nutritional support and prescriptions</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-8">
                        <label class="flex items-center gap-4 p-4 bg-sky-50 rounded-2xl border border-sky-100 cursor-pointer hover:bg-sky-100 transition-colors">
                            <input type="checkbox" id="iron_supplement" name="iron_supplement" class="w-5 h-5 rounded border-sky-300 text-sky-600 focus:ring-sky-500"
                                   <?php echo ($recordData['iron_supplement'] ?? $_POST['iron_supplement'] ?? 0) ? 'checked' : ''; ?>>
                            <span class="font-bold text-sky-900 text-sm">Iron Supplement</span>
                        </label>
                        <label class="flex items-center gap-4 p-4 bg-sky-50 rounded-2xl border border-sky-100 cursor-pointer hover:bg-sky-100 transition-colors">
                            <input type="checkbox" id="folic_acid" name="folic_acid" class="w-5 h-5 rounded border-sky-300 text-sky-600 focus:ring-sky-500"
                                   <?php echo ($recordData['folic_acid'] ?? $_POST['folic_acid'] ?? 0) ? 'checked' : ''; ?>>
                            <span class="font-bold text-sky-900 text-sm">Folic Acid</span>
                        </label>
                        <label class="flex items-center gap-4 p-4 bg-sky-50 rounded-2xl border border-sky-100 cursor-pointer hover:bg-sky-100 transition-colors">
                            <input type="checkbox" id="calcium" name="calcium" class="w-5 h-5 rounded border-sky-300 text-sky-600 focus:ring-sky-500"
                                   <?php echo ($recordData['calcium'] ?? $_POST['calcium'] ?? 0) ? 'checked' : ''; ?>>
                            <span class="font-bold text-sky-900 text-sm">Calcium</span>
                        </label>
                    </div>

                    <div>
                        <label for="other_meds" class="form-label-premium text-sky-900 text-[10px]">Other Prescribed Medications</label>
                        <textarea class="form-input-premium min-h-[80px] border-sky-100 focus:border-sky-500 focus:ring-sky-200 py-3 text-sm" id="other_meds" name="other_meds" placeholder="List other drugs..."><?php echo htmlspecialchars($recordData['other_meds'] ?? $_POST['other_meds'] ?? ''); ?></textarea>
                    </div>
                </section>

                <!-- Next Appointment -->
                <section class="card-premium border-l-4 border-emerald-500">
                    <div class="section-header">
                        <div class="section-icon bg-emerald-50">
                            <i class="fas fa-calendar-plus text-emerald-600 font-black"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-slate-800 text-emerald-900">Follow-up Care</h2>
                            <p class="text-xs text-slate-400 font-bold uppercase tracking-widest mt-0.5">Scheduling the next prenatal checkup</p>
                        </div>
                    </div>

                    <div class="max-w-md">
                        <label for="next_visit_date" class="form-label-premium text-emerald-900">Next Scheduled Visit Date</label>
                        <input type="date" class="form-input-premium border-emerald-100 focus:border-emerald-500 focus:ring-emerald-200 font-black text-emerald-700" id="next_visit_date" name="next_visit_date"
                               value="<?php echo htmlspecialchars($recordData['next_visit_date'] ?? $_POST['next_visit_date'] ?? ''); ?>">
                        <div class="hidden mt-2 p-2 bg-rose-50 text-rose-600 text-xs font-bold rounded-lg border border-rose-100" id="next_visit_date_warning">
                            Next visit must be after the current visit
                        </div>
                    </div>
                </section>

                <!-- Form Actions -->
                <div class="flex flex-col sm:flex-row justify-end items-center gap-4 pt-8 pb-12">
                    <button type="button" onclick="window.history.back()" 
                            class="w-full sm:w-auto px-8 py-4 bg-slate-100 text-slate-600 rounded-2xl font-bold uppercase tracking-widest text-xs hover:bg-slate-200 transition-all active:scale-95">
                        Discard Changes
                    </button>
                    <button type="submit" 
                            class="w-full sm:w-auto px-12 py-4 bg-health-600 text-white rounded-2xl font-black uppercase tracking-widest text-xs hover:bg-health-700 shadow-xl shadow-health-200 transition-all active:scale-95 flex items-center justify-center gap-2">
                        <i class="fas fa-save shadow-sm"></i>
                        <?php echo $editMode ? 'Update Prenatal Record' : 'Save Prenatal Visit'; ?>
                    </button>
                </div>
            </form>
        </main>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('prenatalForm');
            const visitDate = document.getElementById('visit_date');
            const nextVisitDate = document.getElementById('next_visit_date');

            // Real-time validation for all required fields
            const requiredFields = [
                'mother_id', 'visit_date', 'gestational_age', 'visit_number',
                'blood_pressure', 'weight', 'temperature'
            ];
            
            requiredFields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (!field) return;

                field.addEventListener('blur', function() {
                    validateField(this);
                });
                
                field.addEventListener('input', function() {
                    if (this.value.trim() !== '') {
                        hideWarning(this.id);
                    }
                });
            });

            function validateField(field) {
                if (field.value.trim() === '') {
                    showWarning(field.id);
                    return false;
                }
                hideWarning(field.id);
                return true;
            }

            function showWarning(fieldId) {
                const warning = document.getElementById(fieldId + '_warning');
                const field = document.getElementById(fieldId);
                if (warning) warning.classList.remove('hidden');
                if (field) {
                    // For vital signs we handle differently since they are transparent
                    if (['blood_pressure', 'weight', 'temperature'].includes(fieldId)) {
                        field.classList.add('border-rose-500');
                    } else {
                        field.classList.add('border-rose-500', 'ring-2', 'ring-rose-200');
                    }
                }
            }

            function hideWarning(fieldId) {
                const warning = document.getElementById(fieldId + '_warning');
                const field = document.getElementById(fieldId);
                if (warning) warning.classList.add('hidden');
                if (field) {
                    field.classList.remove('border-rose-500', 'ring-2', 'ring-rose-200');
                }
            }

            function validateDates() {
                const visit = new Date(visitDate.value);
                const next = new Date(nextVisitDate.value);
                
                if (nextVisitDate.value && next <= visit) {
                    showWarning('next_visit_date');
                    return false;
                } else {
                    hideWarning('next_visit_date');
                    return true;
                }
            }

            visitDate.addEventListener('change', validateDates);
            nextVisitDate.addEventListener('change', validateDates);

            form.addEventListener('submit', function(e) {
                let isValid = true;
                
                // Validate all required fields
                requiredFields.forEach(fieldId => {
                    const field = document.getElementById(fieldId);
                    if (field && !validateField(field)) {
                        isValid = false;
                    }
                });
                
                // Validate dates
                if (!validateDates()) {
                    isValid = false;
                }
                
                if (!isValid) {
                    e.preventDefault();
                    
                    // Scroll to first error
                    const firstError = document.querySelector('[id$="_warning"]:not(.hidden)');
                    if (firstError) {
                        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        const errorField = document.getElementById(firstError.id.replace('_warning', ''));
                        if (errorField) {
                            errorField.classList.add('animate-pulse');
                            setTimeout(() => errorField.classList.remove('animate-pulse'), 1000);
                        }
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

            // Initial focus visual feedback for edit mode
            if (<?php echo $editMode ? 'true' : 'false'; ?>) {
                // Potential logic for pre-validating existing data
            }
        });
    </script>
</body>
</html>