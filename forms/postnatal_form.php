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
                        <?php echo $editMode ? 'Edit Postnatal Record' : 'New Postnatal Visit'; ?>
                    </h1>
                    <p class="text-slate-500 font-medium mt-1 italic">Comprehensive postpartum care monitoring</p>
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

            <form method="POST" action="" id="postnatalForm" novalidate class="space-y-8">
                <?php if ($editMode): ?>
                    <input type="hidden" name="record_id" value="<?php echo $recordData['id']; ?>">
                <?php endif; ?>

                <!-- Patient Identification -->
                <section class="card-premium">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-user-friends text-health-600"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-slate-800">Mother & Baby Identification</h2>
                            <p class="text-xs text-slate-400 font-bold uppercase tracking-widest mt-0.5">Link the visit to maternal and infant records</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            <label class="form-label-premium">Mother's Name <span class="text-rose-500">*</span></label>
                            <?php if ($editMode): ?>
                                <div class="p-4 bg-slate-50 border border-slate-100 rounded-2xl text-slate-700 font-bold flex items-center gap-3">
                                    <i class="fas fa-venus text-rose-400"></i>
                                    <?php echo htmlspecialchars($recordData['mother_first_name'] . ' ' . $recordData['mother_last_name']); ?>
                                </div>
                                <input type="hidden" name="mother_id" value="<?php echo $recordData['mother_id']; ?>">
                                <p class="mt-2 text-[10px] text-slate-400 italic">Maternal record locked during update</p>
                            <?php else: ?>
                                <select class="form-input-premium appearance-none" id="mother_id" name="mother_id" required>
                                    <option value="">Select Mother from Database</option>
                                    <?php foreach ($mothers as $mother): ?>
                                        <option value="<?php echo $mother['id']; ?>" <?php echo ($selectedMotherId == $mother['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($mother['last_name'] . ', ' . $mother['first_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="hidden mt-2 p-2 bg-rose-50 text-rose-600 text-xs font-bold rounded-lg border border-rose-100" id="mother_id_warning">
                                    <i class="fas fa-exclamation-triangle me-1"></i> Please select a mother
                                </div>
                            <?php endif; ?>
                        </div>

                        <div>
                            <label class="form-label-premium text-health-900">Baby's Record <span class="text-rose-500">*</span></label>
                            <?php if ($editMode): ?>
                                <div class="p-4 bg-slate-50 border border-slate-100 rounded-2xl text-slate-700 font-bold flex items-center gap-3">
                                    <i class="fas fa-baby text-health-400"></i>
                                    <?php echo htmlspecialchars($recordData['baby_first_name'] . ' ' . $recordData['baby_last_name']); ?>
                                </div>
                                <input type="hidden" name="baby_id" value="<?php echo $recordData['baby_id']; ?>">
                                <p class="mt-2 text-[10px] text-slate-400 italic">Infant record locked during update</p>
                            <?php else: ?>
                                <div class="relative group">
                                    <select class="form-input-premium appearance-none pr-12 transition-all duration-300 group-hover:border-health-400" id="baby_id" name="baby_id" required <?php echo empty($babies) ? 'disabled' : ''; ?>>
                                        <option value="">Select Infant Profile</option>
                                        <?php foreach ($babies as $baby): ?>
                                            <option value="<?php echo $baby['id']; ?>" <?php echo (($_POST['baby_id'] ?? '') == $baby['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($baby['last_name'] . ', ' . $baby['first_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="absolute right-4 top-1/2 -translate-y-1/2 flex items-center pointer-events-none">
                                        <span id="loadingBabies" class="hidden"><i class="fas fa-spinner fa-spin text-health-600"></i></span>
                                        <i class="fas fa-chevron-down text-slate-300 group-hover:text-health-400 transition-colors ml-2"></i>
                                    </div>
                                </div>
                                <div class="hidden mt-2 p-2 bg-rose-50 text-rose-600 text-xs font-bold rounded-lg border border-rose-100" id="baby_id_warning">
                                    <i class="fas fa-exclamation-triangle me-1"></i> Please select a baby
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div id="babyDetailsContainer" class="mt-8 transition-all duration-500 overflow-hidden">
                        <?php if (!empty($babies) || $editMode): ?>
                            <div class="p-6 bg-health-50 rounded-3xl border border-health-100 flex flex-col md:flex-row gap-8 items-center">
                                <div class="w-16 h-16 rounded-2xl bg-white shadow-sm flex items-center justify-center">
                                    <i class="fas fa-baby-carriage text-health-600 text-2xl"></i>
                                </div>
                                <div class="flex-1 grid grid-cols-1 sm:grid-cols-2 gap-6 w-full">
                                    <div class="space-y-1">
                                        <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Infant Name</span>
                                        <div class="text-slate-800 font-bold"><?php echo $editMode ? htmlspecialchars($recordData['baby_first_name'] . ' ' . $recordData['baby_last_name']) : htmlspecialchars($babies[0]['first_name'] . ' ' . $babies[0]['last_name']); ?></div>
                                    </div>
                                    <div class="space-y-1">
                                        <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Date of Birth</span>
                                        <div class="text-slate-800 font-bold"><?php echo $editMode ? date('M j, Y', strtotime($recordData['baby_birth_date'])) : date('M j, Y', strtotime($babies[0]['birth_date'])); ?></div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- Visit Details -->
                <section class="card-premium">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-calendar-alt text-health-600"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-slate-800">Visit & Timing</h2>
                            <p class="text-xs text-slate-400 font-bold uppercase tracking-widest mt-0.5">Clinical timing and visit sequence</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="visit_date" class="form-label-premium">Visit Date <span class="text-rose-500">*</span></label>
                            <input type="date" class="form-input-premium font-bold text-slate-700" id="visit_date" name="visit_date" required 
                                   value="<?php echo htmlspecialchars($recordData['visit_date'] ?? $_POST['visit_date'] ?? date('Y-m-d')); ?>">
                            <div class="hidden mt-2 p-2 bg-rose-50 text-rose-600 text-xs font-bold rounded-lg border border-rose-100" id="visit_date_warning">
                                Required field
                            </div>
                        </div>
                        <div>
                            <label for="visit_number" class="form-label-premium">Visit Sequence <span class="text-rose-500">*</span></label>
                            <input type="number" class="form-input-premium" id="visit_number" name="visit_number" placeholder="e.g. 1" required min="1"
                                   value="<?php echo htmlspecialchars($recordData['visit_number'] ?? $_POST['visit_number'] ?? ''); ?>">
                            <div class="hidden mt-2 p-2 bg-rose-50 text-rose-600 text-xs font-bold rounded-lg border border-rose-100" id="visit_number_warning">
                                Required field
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Mother's Vital Signs -->
                <section class="card-premium">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-heartbeat text-health-600"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-slate-800">Maternal Vitals</h2>
                            <p class="text-xs text-slate-400 font-bold uppercase tracking-widest mt-0.5">Physical health metrics</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label for="blood_pressure" class="form-label-premium">Blood Pressure <span class="text-rose-500">*</span></label>
                            <div class="relative">
                                <input type="text" class="form-input-premium pr-12" id="blood_pressure" name="blood_pressure" placeholder="110/70" required
                                       value="<?php echo htmlspecialchars($recordData['blood_pressure'] ?? $_POST['blood_pressure'] ?? ''); ?>">
                                <span class="absolute right-4 top-1/2 -translate-y-1/2 text-[10px] font-black text-slate-400 uppercase">mmHg</span>
                            </div>
                            <div class="hidden mt-2 p-2 bg-rose-50 text-rose-600 text-[10px] font-bold rounded-lg border border-rose-100" id="blood_pressure_warning">
                                <i class="fas fa-exclamation-triangle me-1"></i> Required
                            </div>
                        </div>

                        <div>
                            <label for="weight" class="form-label-premium">Weight (kg) <span class="text-rose-500">*</span></label>
                            <div class="relative">
                                <input type="number" step="0.1" class="form-input-premium pr-12" id="weight" name="weight" placeholder="00.0" required
                                       value="<?php echo htmlspecialchars($recordData['weight'] ?? $_POST['weight'] ?? ''); ?>">
                                <span class="absolute right-4 top-1/2 -translate-y-1/2 text-[10px] font-black text-slate-400 uppercase">kg</span>
                            </div>
                            <div class="hidden mt-2 p-2 bg-rose-50 text-rose-600 text-[10px] font-bold rounded-lg border border-rose-100" id="weight_warning">
                                <i class="fas fa-exclamation-triangle me-1"></i> Required
                            </div>
                        </div>

                        <div>
                            <label for="temperature" class="form-label-premium">Temperature (°C) <span class="text-rose-500">*</span></label>
                            <div class="relative">
                                <input type="number" step="0.1" class="form-input-premium pr-12" id="temperature" name="temperature" placeholder="36.5" required
                                       value="<?php echo htmlspecialchars($recordData['temperature'] ?? $_POST['temperature'] ?? ''); ?>">
                                <span class="absolute right-4 top-1/2 -translate-y-1/2 text-[10px] font-black text-slate-400 uppercase">°C</span>
                            </div>
                            <div class="hidden mt-2 p-2 bg-rose-50 text-rose-600 text-[10px] font-bold rounded-lg border border-rose-100" id="temperature_warning">
                                <i class="fas fa-exclamation-triangle me-1"></i> Required
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Postpartum Assessment -->
                <section class="card-premium">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-notes-medical text-health-600"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-slate-800">Physical Assessment</h2>
                            <p class="text-xs text-slate-400 font-bold uppercase tracking-widest mt-0.5">Comprehensive postpartum evaluation</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-8">
                        <div>
                            <label for="uterus_status" class="form-label-premium">Uterus Status</label>
                            <select class="form-input-premium appearance-none" id="uterus_status" name="uterus_status">
                                <option value="">Assessment Result</option>
                                <option value="normal" <?php echo (($recordData['uterus_status'] ?? $_POST['uterus_status'] ?? '') == 'normal') ? 'selected' : ''; ?>>Normal involution</option>
                                <option value="delayed" <?php echo (($recordData['uterus_status'] ?? $_POST['uterus_status'] ?? '') == 'delayed') ? 'selected' : ''; ?>>Delayed involution</option>
                                <option value="tender" <?php echo (($recordData['uterus_status'] ?? $_POST['uterus_status'] ?? '') == 'tender') ? 'selected' : ''; ?>>Tender</option>
                            </select>
                        </div>
                        <div>
                            <label for="lochia_status" class="form-label-premium">Lochia</label>
                            <select class="form-input-premium appearance-none" id="lochia_status" name="lochia_status">
                                <option value="">Assessment Result</option>
                                <option value="normal" <?php echo (($recordData['lochia_status'] ?? $_POST['lochia_status'] ?? '') == 'normal') ? 'selected' : ''; ?>>Normal discharge</option>
                                <option value="heavy" <?php echo (($recordData['lochia_status'] ?? $_POST['lochia_status'] ?? '') == 'heavy') ? 'selected' : ''; ?>>Heavy flow</option>
                                <option value="foul" <?php echo (($recordData['lochia_status'] ?? $_POST['lochia_status'] ?? '') == 'foul') ? 'selected' : ''; ?>>Foul-smelling</option>
                            </select>
                        </div>
                        <div>
                            <label for="perineum_status" class="form-label-premium">Perineum/Episiotomy</label>
                            <select class="form-input-premium appearance-none" id="perineum_status" name="perineum_status">
                                <option value="">Assessment Result</option>
                                <option value="healed" <?php echo (($recordData['perineum_status'] ?? $_POST['perineum_status'] ?? '') == 'healed') ? 'selected' : ''; ?>>Healed</option>
                                <option value="healing" <?php echo (($recordData['perineum_status'] ?? $_POST['perineum_status'] ?? '') == 'healing') ? 'selected' : ''; ?>>Healing well</option>
                                <option value="infected" <?php echo (($recordData['perineum_status'] ?? $_POST['perineum_status'] ?? '') == 'infected') ? 'selected' : ''; ?>>Infected / Needs attention</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                        <div>
                            <label for="breasts_status" class="form-label-premium">Breasts Assessment</label>
                            <select class="form-input-premium appearance-none" id="breasts_status" name="breasts_status">
                                <option value="">Assessment Result</option>
                                <option value="normal" <?php echo (($recordData['breasts_status'] ?? $_POST['breasts_status'] ?? '') == 'normal') ? 'selected' : ''; ?>>Normal / Lactating</option>
                                <option value="engorged" <?php echo (($recordData['breasts_status'] ?? $_POST['breasts_status'] ?? '') == 'engorged') ? 'selected' : ''; ?>>Engorged</option>
                                <option value="cracked" <?php echo (($recordData['breasts_status'] ?? $_POST['breasts_status'] ?? '') == 'cracked') ? 'selected' : ''; ?>>Cracked nipples</option>
                                <option value="mastitis" <?php echo (($recordData['breasts_status'] ?? $_POST['breasts_status'] ?? '') == 'mastitis') ? 'selected' : ''; ?>>Possible Mastitis</option>
                            </select>
                        </div>
                        <div>
                            <label for="emotional_state" class="form-label-premium">Emotional State</label>
                            <select class="form-input-premium appearance-none" id="emotional_state" name="emotional_state">
                                <option value="">Assessment Result</option>
                                <option value="normal" <?php echo (($recordData['emotional_state'] ?? $_POST['emotional_state'] ?? '') == 'normal') ? 'selected' : ''; ?>>Normal / Positive</option>
                                <option value="baby-blues" <?php echo (($recordData['emotional_state'] ?? $_POST['emotional_state'] ?? '') == 'baby-blues') ? 'selected' : ''; ?>>Baby blues</option>
                                <option value="depression" <?php echo (($recordData['emotional_state'] ?? $_POST['emotional_state'] ?? '') == 'depression') ? 'selected' : ''; ?>>Signs of Postpartum Depression</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="complaints" class="form-label-premium">Maternal Complaints</label>
                            <textarea class="form-input-premium min-h-[100px] py-3 text-sm" id="complaints" name="complaints" placeholder="Note any maternal issues..."><?php echo htmlspecialchars($recordData['complaints'] ?? $_POST['complaints'] ?? ''); ?></textarea>
                        </div>
                        <div>
                            <label for="treatment" class="form-label-premium text-health-900">Maternal Management Plan</label>
                            <textarea class="form-input-premium min-h-[100px] border-health-100 focus:border-health-600 focus:ring-health-200 py-3 text-sm" id="treatment" name="treatment" placeholder="Actions taken for mother..."><?php echo htmlspecialchars($recordData['treatment'] ?? $_POST['treatment'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </section>
                            
                <!-- Baby's Health Assessment -->
                <section class="card-premium">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-baby text-health-600"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-slate-800">Infant Health Status</h2>
                            <p class="text-xs text-slate-400 font-bold uppercase tracking-widest mt-0.5">Physical growth and nutrition</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8 border-b border-slate-100 pb-8">
                        <div>
                            <label for="baby_weight" class="form-label-premium">Current Weight (kg)</label>
                            <div class="relative">
                                <input type="number" step="0.01" class="form-input-premium pr-12" id="baby_weight" name="baby_weight" placeholder="e.g. 3.20"
                                       value="<?php echo htmlspecialchars($recordData['baby_weight'] ?? $_POST['baby_weight'] ?? ''); ?>">
                                <span class="absolute right-4 top-1/2 -translate-y-1/2 text-[10px] font-black text-slate-400 uppercase">kg</span>
                            </div>
                        </div>
                        <div>
                            <label for="feeding_method" class="form-label-premium">Nutrition / Feeding</label>
                            <select class="form-input-premium appearance-none" id="feeding_method" name="feeding_method">
                                <option value="">Select Feeding Method</option>
                                <option value="exclusive-breastfeeding" <?php echo (($recordData['feeding_method'] ?? $_POST['feeding_method'] ?? '') == 'exclusive-breastfeeding') ? 'selected' : ''; ?>>Exclusive breastfeeding</option>
                                <option value="mixed-feeding" <?php echo (($recordData['feeding_method'] ?? $_POST['feeding_method'] ?? '') == 'mixed-feeding') ? 'selected' : ''; ?>>Mixed feeding</option>
                                <option value="formula" <?php echo (($recordData['feeding_method'] ?? $_POST['feeding_method'] ?? '') == 'formula') ? 'selected' : ''; ?>>Formula feeding</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="baby_issues" class="form-label-premium">Observed Issues</label>
                            <textarea class="form-input-premium min-h-[100px] py-3 text-sm" id="baby_issues" name="baby_issues" placeholder="Note any health concerns for the baby..."><?php echo htmlspecialchars($recordData['baby_issues'] ?? $_POST['baby_issues'] ?? ''); ?></textarea>
                        </div>
                        <div>
                            <label for="baby_treatment" class="form-label-premium text-health-900">Infant Management Plan</label>
                            <textarea class="form-input-premium min-h-[100px] border-health-100 focus:border-health-600 focus:ring-health-200 py-3 text-sm" id="baby_treatment" name="baby_treatment" placeholder="Actions taken for baby..."><?php echo htmlspecialchars($recordData['baby_treatment'] ?? $_POST['baby_treatment'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </section>

                <!-- Follow-up & Counseling -->
                <section class="card-premium border-l-4 border-emerald-500 shadow-emerald-100/50">
                    <div class="section-header">
                        <div class="section-icon bg-emerald-50">
                            <i class="fas fa-calendar-check text-emerald-600"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-slate-800">Counseling & Scheduling</h2>
                            <p class="text-xs text-slate-400 font-bold uppercase tracking-widest mt-0.5">Education and future care plan</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                        <div>
                            <label for="counseling_topics" class="form-label-premium text-emerald-900">Education Provided</label>
                            <textarea class="form-input-premium border-emerald-100 focus:border-emerald-500 focus:ring-emerald-200 min-h-[100px] py-3 text-sm" id="counseling_topics" name="counseling_topics" placeholder="Topics discussed (e.g., breastfeeding, hygiene, FP)..."><?php echo htmlspecialchars($recordData['counseling_topics'] ?? $_POST['counseling_topics'] ?? ''); ?></textarea>
                        </div>
                        <div>
                            <label for="next_visit_date" class="form-label-premium text-emerald-900">Next Scheduled Visit</label>
                            <input type="date" class="form-input-premium border-emerald-100 focus:border-emerald-500 focus:ring-emerald-200 font-black text-emerald-700" id="next_visit_date" name="next_visit_date"
                                   value="<?php echo htmlspecialchars($recordData['next_visit_date'] ?? $_POST['next_visit_date'] ?? ''); ?>">
                            <div class="hidden mt-2 p-2 bg-rose-50 text-rose-600 text-[10px] font-bold rounded-lg border border-rose-100" id="next_visit_date_warning">
                                Must be after current visit date
                            </div>
                        </div>
                    </div>

                    <div class="p-6 bg-slate-50 rounded-3xl border border-slate-100">
                        <div class="flex flex-col md:flex-row gap-8">
                            <div class="flex-none">
                                <label class="flex items-center gap-4 p-4 bg-white rounded-2xl border border-slate-200 cursor-pointer hover:border-emerald-500 transition-all group">
                                    <input type="checkbox" id="referral_needed" name="referral_needed" class="w-5 h-5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                           <?php echo ($recordData['referral_needed'] ?? $_POST['referral_needed'] ?? 0) ? 'checked' : ''; ?>>
                                    <span class="font-bold text-slate-700 text-sm group-hover:text-emerald-700 transition-colors">Requires Specialist Referral?</span>
                                </label>
                            </div>
                            <div class="flex-1">
                                <label for="referral_details" class="form-label-premium text-[10px]">Referral Particulars</label>
                                <textarea class="form-input-premium border-slate-100 focus:border-emerald-500 focus:ring-emerald-100 min-h-[80px] py-3 text-sm" id="referral_details" name="referral_details" placeholder="Facility name, reason for referral..."><?php echo htmlspecialchars($recordData['referral_details'] ?? $_POST['referral_details'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Form Actions -->
                <div class="flex flex-col sm:flex-row justify-end items-center gap-4 pt-8 pb-12">
                    <button type="button" onclick="window.history.back()" 
                            class="w-full sm:w-auto px-10 py-4 bg-slate-100 text-slate-600 rounded-2xl font-bold uppercase tracking-widest text-xs hover:bg-slate-200 transition-all active:scale-95">
                        Cancel & Discard
                    </button>
                    <button type="submit" 
                            class="w-full sm:w-auto px-16 py-4 bg-health-600 text-white rounded-2xl font-black uppercase tracking-widest text-xs hover:bg-health-700 shadow-xl shadow-health-200 transition-all active:scale-95 flex items-center justify-center gap-3">
                        <i class="fas fa-save shadow-sm"></i>
                        <?php echo $editMode ? 'Update Record' : 'Save Postnatal Visit'; ?>
                    </button>
                </div>
            </form>
        </main>
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
            
            // Real-time validation for all required fields
            const requiredFields = [
                'mother_id', 'baby_id', 'visit_date', 'visit_number',
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
                    // Unique handling for vital sign parent cards
                    if (['blood_pressure', 'weight', 'temperature'].includes(fieldId)) {
                        field.closest('.p-6').classList.add('border-rose-500', 'ring-2', 'ring-rose-100');
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
                    if (['blood_pressure', 'weight', 'temperature'].includes(fieldId)) {
                        field.closest('.p-6').classList.remove('border-rose-500', 'ring-2', 'ring-rose-100');
                    } else {
                        field.classList.remove('border-rose-500', 'ring-2', 'ring-rose-200');
                    }
                }
            }

            function validateNextVisitDate() {
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
                    babySelect.innerHTML = '<option value="">Select Infant Profile</option>';
                    babySelect.disabled = true;
                    babyDetailsContainer.innerHTML = '';
                    return;
                }
                
                // Show loading indicator
                loadingIndicator.classList.remove('hidden');
                babySelect.disabled = true;
                
                // Fetch babies via AJAX
                fetch('../includes/get_babies.php?mother_id=' + motherId)
                    .then(response => response.json())
                    .then(data => {
                        // Update baby dropdown
                        babySelect.innerHTML = '<option value="">Select Infant Profile</option>';
                        if (data.babies && data.babies.length > 0) {
                            data.babies.forEach(baby => {
                                const option = document.createElement('option');
                                option.value = baby.id;
                                option.textContent = `${baby.last_name}, ${baby.first_name}`;
                                babySelect.appendChild(option);
                            });
                            babySelect.disabled = false;
                            
                            // Update baby details (preview first baby)
                            updateBabyDetailUI(data.babies[0]);
                        } else {
                            babyDetailsContainer.innerHTML = `
                                <div class="p-6 bg-rose-50 rounded-3xl border border-rose-100 flex items-center gap-4">
                                    <div class="w-10 h-10 rounded-xl bg-rose-500 flex items-center justify-center">
                                        <i class="fas fa-exclamation-triangle text-white"></i>
                                    </div>
                                    <div class="text-rose-900 font-bold">No registered infants found for this mother.</div>
                                </div>
                            `;
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching babies:', error);
                        babyDetailsContainer.innerHTML = '<div class="p-6 bg-rose-50 rounded-3xl border border-rose-100 text-rose-700">Error loading baby data.</div>';
                    })
                    .finally(() => {
                        loadingIndicator.classList.add('hidden');
                    });
            });

            babySelect.addEventListener('change', function() {
                const babyId = this.value;
                if (!babyId) {
                    babyDetailsContainer.innerHTML = '';
                    return;
                }
                // We could fetch specific baby info here if needed, 
                // but for now we'll just keep the first baby info or clear it
            });

            function updateBabyDetailUI(baby) {
                babyDetailsContainer.innerHTML = `
                    <div class="p-6 bg-health-50 rounded-3xl border border-health-100 flex flex-col md:flex-row gap-8 items-center animate-in fade-in slide-in-from-bottom-4 duration-500">
                        <div class="w-16 h-16 rounded-2xl bg-white shadow-sm flex items-center justify-center">
                            <i class="fas fa-baby-carriage text-health-600 text-2xl"></i>
                        </div>
                        <div class="flex-1 grid grid-cols-1 sm:grid-cols-2 gap-6 w-full">
                            <div class="space-y-1">
                                <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Infant Name</span>
                                <div class="text-slate-800 font-bold">${baby.first_name} ${baby.last_name}</div>
                            </div>
                            <div class="space-y-1">
                                <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Date of Birth</span>
                                <div class="text-slate-800 font-bold">${new Date(baby.birth_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</div>
                            </div>
                        </div>
                    </div>
                `;
            }
            <?php endif; ?>
            
            // Form submission validation
            form.addEventListener('submit', function(e) {
                let isValid = true;
                
                // Validate all required fields
                requiredFields.forEach(fieldId => {
                    const field = document.getElementById(fieldId);
                    if (field && !validateField(field)) {
                        isValid = false;
                    }
                });
                
                // Validate next visit date
                if (!validateNextVisitDate()) {
                    isValid = false;
                }
                
                if (!isValid) {
                    e.preventDefault();
                    
                    // Scroll to first error
                    const firstError = document.querySelector('[id$="_warning"]:not(.hidden)');
                    if (firstError) {
                        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        const errorField = document.getElementById(firstError.id.replace('_warning', ''));
                        const target = errorField ? (['blood_pressure', 'weight', 'temperature'].includes(errorField.id) ? errorField.closest('.p-6') : errorField) : null;
                        
                        if (target) {
                            target.classList.add('animate-pulse');
                            setTimeout(() => target.classList.remove('animate-pulse'), 1000);
                        }
                    }
                }
            });

            // Initial focus visual feedback for edit mode
            if (<?php echo $editMode ? 'true' : 'false'; ?>) {
                // Initialize validation UI for existing data if needed
            }
        });
    </script>
</body>
</html>