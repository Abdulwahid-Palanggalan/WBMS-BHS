<?php
require_once dirname(__FILE__) . '/../config/config.php';

// Only admin and midwife can register mothers
if (!canRegisterMothers()) {
    header("Location: ../login.php");
    exit();
}

redirectIfNotLoggedIn();

global $pdo;

$message = '';
$error = '';
$editMode = false;
$motherData = [];
$pregnancyData = [];
$medicalData = [];
$husbandData = [];

// Check if in edit mode
if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $editMode = true;
    $motherId = intval($_GET['edit']);
    
    // Get existing mother data
    $stmt = $pdo->prepare("
        SELECT m.*, u.first_name, u.middle_name, u.last_name, u.email, u.phone 
        FROM mothers m 
        LEFT JOIN users u ON m.user_id = u.id 
        WHERE m.id = ?
    ");
    $stmt->execute([$motherId]);
    $motherData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$motherData) {
        $error = "Mother record not found.";
        $editMode = false;
    } else {
        // Get pregnancy details
        $stmt = $pdo->prepare("SELECT * FROM pregnancy_details WHERE mother_id = ?");
        $stmt->execute([$motherId]);
        $pregnancyData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get medical history
        $stmt = $pdo->prepare("SELECT * FROM medical_histories WHERE mother_id = ?");
        $stmt->execute([$motherId]);
        $medicalData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get husband/partner information
        $stmt = $pdo->prepare("SELECT * FROM husband_partners WHERE mother_id = ?");
        $stmt->execute([$motherId]);
        $husbandData = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} // <-- ADDED THIS MISSING CLOSING BRACE

// Address options for Carmen, Cotabato
$addressOptions = [
    'Proper 1, Kibenes, Carmen, Cotabato',
    'Proper 2, Kibenes, Carmen, Cotabato',
    'Takpan, Carmen, Cotabato',
    'Kupayan, Carmen, Cotabato',
    'Kilaba, Carmen, Cotabato',
    'Baingkungan, Carmen, Cotabato',
    'Butuan, Carmen, Cotabato',
    'Sambayangan, Carmen, Cotabato',
    'Village, Carmen, Cotabato'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Personal Information
    $firstName = trim($_POST['first_name'] ?? '');
    $middleName = trim($_POST['middle_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $dateOfBirth = $_POST['date_of_birth'] ?? '';
    $civilStatus = $_POST['civil_status'] ?? '';
    $nationality = trim($_POST['nationality'] ?? 'Filipino');
    $religion = trim($_POST['religion'] ?? '');
    $education = $_POST['education'] ?? '';
    $occupation = trim($_POST['occupation'] ?? '');
    
    // Contact Information
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $emergencyContact = trim($_POST['emergency_contact'] ?? '');
    $emergencyPhone = trim($_POST['emergency_phone'] ?? '');
    
    // Medical Information
    $bloodType = $_POST['blood_type'] ?? '';
    $rhFactor = $_POST['rh_factor'] ?? '';
    $allergies = trim($_POST['allergies'] ?? '');
    $medicalConditions = trim($_POST['medical_conditions'] ?? '');
    $previousSurgeries = trim($_POST['previous_surgeries'] ?? '');
    $familyHistory = trim($_POST['family_history'] ?? '');
    $contraceptiveUse = trim($_POST['contraceptive_use'] ?? '');
    $previousComplications = trim($_POST['previous_complications'] ?? '');
    
    // Husband/Father Information
    $husbandFirstName = trim($_POST['husband_first_name'] ?? '');
    $husbandMiddleName = trim($_POST['husband_middle_name'] ?? '');
    $husbandLastName = trim($_POST['husband_last_name'] ?? '');
    $husbandDateOfBirth = $_POST['husband_date_of_birth'] ?? '';
    $husbandOccupation = trim($_POST['husband_occupation'] ?? '');
    $husbandEducation = $_POST['husband_education'] ?? '';
    $husbandPhone = trim($_POST['husband_phone'] ?? '');
    $husbandCitizenship = trim($_POST['husband_citizenship'] ?? 'Filipino');
    $husbandReligion = trim($_POST['husband_religion'] ?? '');
    $marriageDate = $_POST['marriage_date'] ?? '';
    $marriagePlace = trim($_POST['marriage_place'] ?? '');
    
    // Pregnancy Information
    $lmp = $_POST['lmp'] ?? '';
    $gravida = intval($_POST['gravida'] ?? 0);
    $para = intval($_POST['para'] ?? 0);
    $abortions = intval($_POST['abortions'] ?? 0);
    $livingChildren = intval($_POST['living_children'] ?? 0);
    $plannedPregnancy = $_POST['planned_pregnancy'] ?? '';
    $firstPrenatalVisit = $_POST['first_prenatal_visit'] ?? '';
    $referredBy = trim($_POST['referred_by'] ?? '');
    
    // Calculate EDC based on LMP (40 weeks)
    $edc = !empty($lmp) ? date('Y-m-d', strtotime($lmp . ' + 280 days')) : '';
    
    $userId = $_SESSION['user_id'];
    $registeredBy = $userId;
    
    try {
        $pdo->beginTransaction();
        
        if ($editMode) {
            $motherId = intval($_POST['mother_id'] ?? 0);
            
            if ($motherId <= 0) {
                throw new Exception("Invalid mother ID");
            }
            
            // UPDATE existing mother in mothers table
            $motherSql = "UPDATE mothers SET 
                first_name = ?, middle_name = ?, last_name = ?, 
                date_of_birth = ?, civil_status = ?, nationality = ?, religion = ?, 
                education = ?, occupation = ?, phone = ?, email = ?, address = ?, 
                emergency_contact = ?, emergency_phone = ?, blood_type = ?, rh_factor = ?
                WHERE id = ?";
            
            $motherStmt = $pdo->prepare($motherSql);
            $motherStmt->execute([
                $firstName, $middleName, $lastName, $dateOfBirth, $civilStatus, 
                $nationality, $religion, $education, $occupation, $phone, $email, 
                $address, $emergencyContact, $emergencyPhone, $bloodType, $rhFactor, 
                $motherId
            ]);
            
            // UPDATE or INSERT pregnancy details
            if ($pregnancyData) {
                $pregnancySql = "UPDATE pregnancy_details SET 
                    lmp = ?, edc = ?, gravida = ?, para = ?, abortions = ?, 
                    living_children = ?, planned_pregnancy = ?, first_prenatal_visit = ?, 
                    referred_by = ? WHERE mother_id = ?";
                $pregnancyStmt = $pdo->prepare($pregnancySql);
                $pregnancyStmt->execute([
                    $lmp, $edc, $gravida, $para, $abortions, $livingChildren, 
                    $plannedPregnancy, $firstPrenatalVisit, $referredBy, $motherId
                ]);
            } else {
                $pregnancySql = "INSERT INTO pregnancy_details 
                    (mother_id, lmp, edc, gravida, para, abortions, living_children, 
                    planned_pregnancy, first_prenatal_visit, referred_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $pregnancyStmt = $pdo->prepare($pregnancySql);
                $pregnancyStmt->execute([
                    $motherId, $lmp, $edc, $gravida, $para, $abortions, $livingChildren, 
                    $plannedPregnancy, $firstPrenatalVisit, $referredBy
                ]);
            }
            
            // UPDATE or INSERT medical history
            if ($medicalData) {
                $medicalSql = "UPDATE medical_histories SET 
                    allergies = ?, medical_conditions = ?, previous_surgeries = ?, 
                    family_history = ?, contraceptive_use = ?, previous_complications = ? 
                    WHERE mother_id = ?";
                $medicalStmt = $pdo->prepare($medicalSql);
                $medicalStmt->execute([
                    $allergies, $medicalConditions, $previousSurgeries, $familyHistory,
                    $contraceptiveUse, $previousComplications, $motherId
                ]);
            } else {
                $medicalSql = "INSERT INTO medical_histories 
                    (mother_id, allergies, medical_conditions, previous_surgeries, 
                    family_history, contraceptive_use, previous_complications) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
                $medicalStmt = $pdo->prepare($medicalSql);
                $medicalStmt->execute([
                    $motherId, $allergies, $medicalConditions, $previousSurgeries, 
                    $familyHistory, $contraceptiveUse, $previousComplications
                ]);
            }
            
            // UPDATE or INSERT husband/partner information
            if ($husbandData) {
                $husbandSql = "UPDATE husband_partners SET 
                    first_name = ?, middle_name = ?, last_name = ?, date_of_birth = ?, 
                    occupation = ?, education = ?, phone = ?, citizenship = ?, religion = ?,
                    marriage_date = ?, marriage_place = ? WHERE mother_id = ?";
                $husbandStmt = $pdo->prepare($husbandSql);
                $husbandStmt->execute([
                    $husbandFirstName, $husbandMiddleName, $husbandLastName, 
                    $husbandDateOfBirth, $husbandOccupation, $husbandEducation, 
                    $husbandPhone, $husbandCitizenship, $husbandReligion, 
                    $marriageDate, $marriagePlace, $motherId
                ]);
            } else if (!empty($husbandFirstName) || !empty($husbandLastName)) {
                $husbandSql = "INSERT INTO husband_partners 
                    (mother_id, first_name, middle_name, last_name, date_of_birth, 
                    occupation, education, phone, citizenship, religion, marriage_date, marriage_place) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $husbandStmt = $pdo->prepare($husbandSql);
                $husbandStmt->execute([
                    $motherId, $husbandFirstName, $husbandMiddleName, $husbandLastName, 
                    $husbandDateOfBirth, $husbandOccupation, $husbandEducation, 
                    $husbandPhone, $husbandCitizenship, $husbandReligion, 
                    $marriageDate, $marriagePlace
                ]);
            }
            
            $message = "
            <div class='alert alert-success'>
                <h5><i class='fas fa-check-circle'></i> Mother Information Updated Successfully</h5>
                <strong>Name:</strong> $firstName " . ($middleName ? $middleName . ' ' : '') . "$lastName<br>
                <strong>EDC:</strong> " . (!empty($edc) ? date('F j, Y', strtotime($edc)) : 'Not calculated') . "
            </div>";
            
            logActivity($userId, "Updated mother information: $firstName " . ($middleName ? $middleName . ' ' : '') . "$lastName");
            
        } else {
            // INSERT new mother
            // Generate username (without middle name)
            $username = strtolower($firstName . '.' . $lastName);
            
            // Check if username already exists
            $checkUser = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $checkUser->execute([$username]);
            
            if ($checkUser->rowCount() > 0) {
                $username = $username . rand(100, 999);
            }
            
            // Create a temporary password
            $tempPassword = bin2hex(random_bytes(4));
            $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);
            
            // Insert into users table
            $userSql = "INSERT INTO users (username, email, password, role, first_name, middle_name, last_name, phone, status) 
                        VALUES (?, ?, ?, 'mother', ?, ?, ?, ?, 'active')";
            $userStmt = $pdo->prepare($userSql);
            $userStmt->execute([$username, $email, $hashedPassword, $firstName, $middleName, $lastName, $phone]);
            
            $newUserId = $pdo->lastInsertId();
            
            // Insert into mothers table
            $motherSql = "INSERT INTO mothers 
                (user_id, first_name, middle_name, last_name, date_of_birth, civil_status, 
                nationality, religion, education, occupation, phone, email, address, 
                emergency_contact, emergency_phone, blood_type, rh_factor, registered_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $motherStmt = $pdo->prepare($motherSql);
            $motherStmt->execute([
                $newUserId, $firstName, $middleName, $lastName, $dateOfBirth, $civilStatus,
                $nationality, $religion, $education, $occupation, $phone, $email, $address,
                $emergencyContact, $emergencyPhone, $bloodType, $rhFactor, $registeredBy
            ]);
            
            $newMotherId = $pdo->lastInsertId();
            
            // Insert pregnancy details
            if (!empty($lmp)) {
                $pregnancySql = "INSERT INTO pregnancy_details 
                    (mother_id, lmp, edc, gravida, para, abortions, living_children, 
                    planned_pregnancy, first_prenatal_visit, referred_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $pregnancyStmt = $pdo->prepare($pregnancySql);
                $pregnancyStmt->execute([
                    $newMotherId, $lmp, $edc, $gravida, $para, $abortions, $livingChildren,
                    $plannedPregnancy, $firstPrenatalVisit, $referredBy
                ]);
            }
            
            // Insert medical history
            if (!empty($allergies) || !empty($medicalConditions) || !empty($previousSurgeries) || 
                !empty($familyHistory) || !empty($contraceptiveUse) || !empty($previousComplications)) {
                $medicalSql = "INSERT INTO medical_histories 
                    (mother_id, allergies, medical_conditions, previous_surgeries, 
                    family_history, contraceptive_use, previous_complications) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
                $medicalStmt = $pdo->prepare($medicalSql);
                $medicalStmt->execute([
                    $newMotherId, $allergies, $medicalConditions, $previousSurgeries,
                    $familyHistory, $contraceptiveUse, $previousComplications
                ]);
            }
            
            // Insert husband/partner information
            if (!empty($husbandFirstName) || !empty($husbandLastName)) {
                $husbandSql = "INSERT INTO husband_partners 
                    (mother_id, first_name, middle_name, last_name, date_of_birth, 
                    occupation, education, phone, citizenship, religion, marriage_date, marriage_place) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $husbandStmt = $pdo->prepare($husbandSql);
                $husbandStmt->execute([
                    $newMotherId, $husbandFirstName, $husbandMiddleName, $husbandLastName,
                    $husbandDateOfBirth, $husbandOccupation, $husbandEducation, $husbandPhone,
                    $husbandCitizenship, $husbandReligion, $marriageDate, $marriagePlace
                ]);
            }
            
            $message = "
            <div class='alert alert-success'>
                <h5><i class='fas fa-check-circle'></i> Mother Registered Successfully</h5>
                <strong>Name:</strong> $firstName " . ($middleName ? $middleName . ' ' : '') . "$lastName<br>
                <strong>Username:</strong> $username<br>
                <strong>Temporary Password:</strong> $tempPassword<br>
                <strong>EDC:</strong> " . (!empty($edc) ? date('F j, Y', strtotime($edc)) : 'Not calculated') . "
            </div>";
            
            logActivity($userId, "Registered mother: $firstName " . ($middleName ? $middleName . ' ' : '') . "$lastName");
            
            // Clear form after successful submission (only for new records)
            if (!$editMode) {
                $_POST = array();
            }
        }
        
        $pdo->commit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Failed to " . ($editMode ? "update" : "register") . " mother. Please try again. Error: " . $e->getMessage();
    }
} // <-- ADDED THIS CLOSING BRACE FOR THE POST CHECK
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $editMode ? 'Edit' : 'Register'; ?> Mother - Health Station System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="bg-health-50 font-inter text-slate-900 antialiased selection:bg-health-100 selection:text-health-700">
    <?php include_once INCLUDE_PATH . 'header.php'; ?>
    
    <div class="flex flex-col lg:flex-row min-h-[calc(100vh-4rem)]">
        <?php include_once INCLUDE_PATH . 'sidebar.php'; ?>
        
        <main class="flex-1 p-4 lg:p-8 space-y-8 no-print">
            <!-- Header Section -->
            <header class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-black text-slate-900 tracking-tight">
                        <?php echo $editMode ? 'Edit Mother Profile' : 'Mother Registration'; ?>
                    </h1>
                    <p class="text-slate-500 font-medium mt-1 italic">Comprehensive maternal health record system</p>
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
                        <h3 class="text-rose-900 font-black text-lg leading-tight uppercase tracking-tight">Action Required</h3>
                        <p class="text-rose-700 font-medium text-sm leading-relaxed opacity-90"><?php echo $error; ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <div class="info-box flex items-center gap-4 bg-sky-50 border-sky-100">
                <div class="w-10 h-10 rounded-xl bg-sky-500 flex items-center justify-center shrink-0 shadow-md">
                    <i class="fas fa-info text-white text-sm"></i>
                </div>
                <p class="text-sky-800 text-sm font-bold leading-tight uppercase tracking-widest">
                    Fields marked with <span class="text-rose-500 font-black">*</span> are mandatory for legal documentation.
                </p>
            </div>

            <form method="POST" action="" id="motherRegistrationForm" class="space-y-8" novalidate>
                <?php if ($editMode): ?>
                    <input type="hidden" name="mother_id" value="<?php echo $motherData['id']; ?>">
                <?php endif; ?>

                <!-- Personal Information -->
                <section class="card-premium">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-user text-health-600"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-slate-800">Maternal Personal Data</h2>
                            <p class="text-xs text-slate-400 font-bold uppercase tracking-widest mt-0.5">Primary identification information</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                        <div>
                            <label for="first_name" class="form-label-premium">First Name <span class="text-rose-500">*</span></label>
                            <input type="text" class="form-input-premium" id="first_name" name="first_name" 
                                   value="<?= htmlspecialchars($motherData['first_name'] ?? $_POST['first_name'] ?? ''); ?>" placeholder="Enter first name" required>
                            <div class="hidden mt-2 p-2 bg-rose-50 text-rose-600 text-xs font-bold rounded-lg border border-rose-100" id="first_name_warning">
                                <i class="fas fa-exclamation-triangle me-1"></i> Please enter first name
                            </div>
                        </div>
                        <div>
                            <label for="middle_name" class="form-label-premium">Middle Name</label>
                            <input type="text" class="form-input-premium" id="middle_name" name="middle_name" 
                                   value="<?= htmlspecialchars($motherData['middle_name'] ?? $_POST['middle_name'] ?? ''); ?>" placeholder="Enter middle name (optional)">
                        </div>
                        <div>
                            <label for="last_name" class="form-label-premium">Last Name <span class="text-rose-500">*</span></label>
                            <input type="text" class="form-input-premium" id="last_name" name="last_name" 
                                   value="<?= htmlspecialchars($motherData['last_name'] ?? $_POST['last_name'] ?? ''); ?>" placeholder="Enter last name" required>
                            <div class="hidden mt-2 p-2 bg-rose-50 text-rose-600 text-xs font-bold rounded-lg border border-rose-100" id="last_name_warning">
                                <i class="fas fa-exclamation-triangle me-1"></i> Please enter last name
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <div>
                            <label for="date_of_birth" class="form-label-premium">Date of Birth <span class="text-rose-500">*</span></label>
                            <input type="date" class="form-input-premium" id="date_of_birth" name="date_of_birth" 
                                   value="<?= htmlspecialchars($motherData['date_of_birth'] ?? $_POST['date_of_birth'] ?? ''); ?>" required max="<?= date('Y-m-d'); ?>">
                            <div class="hidden mt-2 p-2 bg-rose-50 text-rose-600 text-xs font-bold rounded-lg border border-rose-100" id="date_of_birth_warning">
                                <i class="fas fa-exclamation-triangle me-1"></i> Please select date of birth
                            </div>
                        </div>
                        <div>
                            <label for="civil_status" class="form-label-premium">Civil Status <span class="text-rose-500">*</span></label>
                            <select class="form-input-premium appearance-none" id="civil_status" name="civil_status" required>
                                <option value="">Select Status</option>
                                <option value="Single" <?= (($motherData['civil_status'] ?? $_POST['civil_status'] ?? '') == 'Single') ? 'selected' : ''; ?>>Single</option>
                                <option value="Married" <?= (($motherData['civil_status'] ?? $_POST['civil_status'] ?? '') == 'Married') ? 'selected' : ''; ?>>Married</option>
                                <option value="Divorced" <?= (($motherData['civil_status'] ?? $_POST['civil_status'] ?? '') == 'Divorced') ? 'selected' : ''; ?>>Divorced</option>
                                <option value="Widowed" <?= (($motherData['civil_status'] ?? $_POST['civil_status'] ?? '') == 'Widowed') ? 'selected' : ''; ?>>Widowed</option>
                                <option value="Separated" <?= (($motherData['civil_status'] ?? $_POST['civil_status'] ?? '') == 'Separated') ? 'selected' : ''; ?>>Separated</option>
                            </select>
                            <div class="hidden mt-2 p-2 bg-rose-50 text-rose-600 text-xs font-bold rounded-lg border border-rose-100" id="civil_status_warning">
                                <i class="fas fa-exclamation-triangle me-1"></i> Please select civil status
                            </div>
                        </div>
                        <div>
                            <label for="nationality" class="form-label-premium">Nationality</label>
                            <input type="text" class="form-input-premium" id="nationality" name="nationality" 
                                   value="<?= htmlspecialchars($motherData['nationality'] ?? $_POST['nationality'] ?? 'Filipino'); ?>" placeholder="e.g., Filipino">
                        </div>
                        <div>
                            <label for="religion" class="form-label-premium">Religion</label>
                            <input type="text" class="form-input-premium" id="religion" name="religion" 
                                   value="<?= htmlspecialchars($motherData['religion'] ?? $_POST['religion'] ?? ''); ?>" placeholder="e.g., Roman Catholic">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="education" class="form-label-premium">Educational Attainment</label>
                            <select class="form-input-premium appearance-none" id="education" name="education">
                                <option value="">Select Education</option>
                                <option value="No Formal Education" <?= (($motherData['education'] ?? $_POST['education'] ?? '') == 'No Formal Education') ? 'selected' : ''; ?>>No Formal Education</option>
                                <option value="Elementary" <?= (($motherData['education'] ?? $_POST['education'] ?? '') == 'Elementary') ? 'selected' : ''; ?>>Elementary</option>
                                <option value="High School" <?= (($motherData['education'] ?? $_POST['education'] ?? '') == 'High School') ? 'selected' : ''; ?>>High School</option>
                                <option value="College" <?= (($motherData['education'] ?? $_POST['education'] ?? '') == 'College') ? 'selected' : ''; ?>>College</option>
                                <option value="Vocational" <?= (($motherData['education'] ?? $_POST['education'] ?? '') == 'Vocational') ? 'selected' : ''; ?>>Vocational</option>
                                <option value="Post Graduate" <?= (($motherData['education'] ?? $_POST['education'] ?? '') == 'Post Graduate') ? 'selected' : ''; ?>>Post Graduate</option>
                            </select>
                        </div>
                        <div>
                            <label for="occupation" class="form-label-premium">Occupation</label>
                            <input type="text" class="form-input-premium" id="occupation" name="occupation" 
                                   value="<?= htmlspecialchars($motherData['occupation'] ?? $_POST['occupation'] ?? ''); ?>" placeholder="e.g., Housewife, Teacher">
                        </div>
                    </div>
                </section>
                            
                <!-- Contact Information -->
                <section class="card-premium">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-address-book text-health-600"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-slate-800">Contact & Address Data</h2>
                            <p class="text-xs text-slate-400 font-bold uppercase tracking-widest mt-0.5">Reliable reachability and emergency details</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                        <div>
                            <label for="phone" class="form-label-premium">Phone Number <span class="text-rose-500">*</span></label>
                            <input type="tel" class="form-input-premium" id="phone" name="phone" 
                                   value="<?= htmlspecialchars($motherData['phone'] ?? $_POST['phone'] ?? ''); ?>" required placeholder="09123456789" pattern="[0-9]{11}">
                            <div class="hidden mt-2 p-2 bg-rose-50 text-rose-600 text-xs font-bold rounded-lg border border-rose-100" id="phone_warning">
                                <i class="fas fa-exclamation-triangle me-1"></i> Please enter an 11-digit phone number
                            </div>
                        </div>
                        <div>
                            <label for="email" class="form-label-premium">Email Address</label>
                            <input type="email" class="form-input-premium" id="email" name="email" 
                                   value="<?= htmlspecialchars($motherData['email'] ?? $_POST['email'] ?? ''); ?>" placeholder="optional@email.com">
                        </div>
                        <div>
                            <label for="address" class="form-label-premium">Permanent Address <span class="text-rose-500">*</span></label>
                            <select class="form-input-premium appearance-none" id="address" name="address" required>
                                <option value="">Select Address</option>
                                <?php foreach ($addressOptions as $addressOption): ?>
                                    <option value="<?= htmlspecialchars($addressOption); ?>" 
                                        <?= (($motherData['address'] ?? $_POST['address'] ?? '') == $addressOption) ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($addressOption); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="hidden mt-2 p-2 bg-rose-50 text-rose-600 text-xs font-bold rounded-lg border border-rose-100" id="address_warning">
                                <i class="fas fa-exclamation-triangle me-1"></i> Please select mother's address
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="emergency_contact" class="form-label-premium">Emergency Contact Person</label>
                            <input type="text" class="form-input-premium" id="emergency_contact" name="emergency_contact" 
                                   value="<?= htmlspecialchars($motherData['emergency_contact'] ?? $_POST['emergency_contact'] ?? ''); ?>" placeholder="Full name of emergency contact">
                        </div>
                        <div>
                            <label for="emergency_phone" class="form-label-premium">Emergency Contact Phone</label>
                            <input type="tel" class="form-input-premium" id="emergency_phone" name="emergency_phone" 
                                   value="<?= htmlspecialchars($motherData['emergency_phone'] ?? $_POST['emergency_phone'] ?? ''); ?>" placeholder="09123456789" pattern="[0-9]{11}">
                        </div>
                    </div>
                </section>
                            
                <!-- Medical Information -->
                <section class="card-premium">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-notes-medical text-health-600"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-slate-800">Maternal Medical Profile</h2>
                            <p class="text-xs text-slate-400 font-bold uppercase tracking-widest mt-0.5">Clinical history and health indicators</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-8 border-b border-slate-100 pb-8">
                        <div>
                            <label for="blood_type" class="form-label-premium">Blood Type</label>
                            <select class="form-input-premium appearance-none" id="blood_type" name="blood_type">
                                <option value="">Select Blood Type</option>
                                <option value="A+" <?= (($motherData['blood_type'] ?? $_POST['blood_type'] ?? '') == 'A+') ? 'selected' : ''; ?>>A+</option>
                                <option value="A-" <?= (($motherData['blood_type'] ?? $_POST['blood_type'] ?? '') == 'A-') ? 'selected' : ''; ?>>A-</option>
                                <option value="B+" <?= (($motherData['blood_type'] ?? $_POST['blood_type'] ?? '') == 'B+') ? 'selected' : ''; ?>>B+</option>
                                <option value="B-" <?= (($motherData['blood_type'] ?? $_POST['blood_type'] ?? '') == 'B-') ? 'selected' : ''; ?>>B-</option>
                                <option value="AB+" <?= (($motherData['blood_type'] ?? $_POST['blood_type'] ?? '') == 'AB+') ? 'selected' : ''; ?>>AB+</option>
                                <option value="AB-" <?= (($motherData['blood_type'] ?? $_POST['blood_type'] ?? '') == 'AB-') ? 'selected' : ''; ?>>AB-</option>
                                <option value="O+" <?= (($motherData['blood_type'] ?? $_POST['blood_type'] ?? '') == 'O+') ? 'selected' : ''; ?>>O+</option>
                                <option value="O-" <?= (($motherData['blood_type'] ?? $_POST['blood_type'] ?? '') == 'O-') ? 'selected' : ''; ?>>O-</option>
                            </select>
                        </div>
                        <div>
                            <label for="rh_factor" class="form-label-premium">RH Factor</label>
                            <select class="form-input-premium appearance-none" id="rh_factor" name="rh_factor">
                                <option value="">Select RH Factor</option>
                                <option value="Positive" <?= (($motherData['rh_factor'] ?? $_POST['rh_factor'] ?? '') == 'Positive') ? 'selected' : ''; ?>>Positive</option>
                                <option value="Negative" <?= (($motherData['rh_factor'] ?? $_POST['rh_factor'] ?? '') == 'Negative') ? 'selected' : ''; ?>>Negative</option>
                                <option value="Unknown" <?= (($motherData['rh_factor'] ?? $_POST['rh_factor'] ?? '') == 'Unknown') ? 'selected' : ''; ?>>Unknown</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8 border-b border-slate-100 pb-8">
                        <div class="space-y-6">
                            <div>
                                <label for="allergies" class="form-label-premium">Clinical Allergies</label>
                                <textarea class="form-input-premium min-h-[100px] resize-none py-3" id="allergies" name="allergies" placeholder="List any known allergies..."><?= htmlspecialchars($medicalData['allergies'] ?? $_POST['allergies'] ?? ''); ?></textarea>
                            </div>
                            <div>
                                <label for="medical_conditions" class="form-label-premium">Existing Medical Conditions</label>
                                <textarea class="form-input-premium min-h-[100px] resize-none py-3" id="medical_conditions" name="medical_conditions" placeholder="e.g., Hypertension, Diabetes, Asthma..."><?= htmlspecialchars($medicalData['medical_conditions'] ?? $_POST['medical_conditions'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        <div class="space-y-6">
                            <div>
                                <label for="previous_surgeries" class="form-label-premium">Surgical History</label>
                                <textarea class="form-input-premium min-h-[100px] resize-none py-3" id="previous_surgeries" name="previous_surgeries" placeholder="List any previous operations..."><?= htmlspecialchars($medicalData['previous_surgeries'] ?? $_POST['previous_surgeries'] ?? ''); ?></textarea>
                            </div>
                            <div>
                                <label for="family_history" class="form-label-premium">Family Health Background</label>
                                <textarea class="form-input-premium min-h-[100px] resize-none py-3" id="family_history" name="family_history" placeholder="Inherited conditions or family history..."><?= htmlspecialchars($medicalData['family_history'] ?? $_POST['family_history'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            <label for="contraceptive_use" class="form-label-premium">Obstetrical History: Contraceptive Use</label>
                            <textarea class="form-input-premium min-h-[100px] resize-none py-3" id="contraceptive_use" name="contraceptive_use" placeholder="Methods used prior to this pregnancy..."><?= htmlspecialchars($medicalData['contraceptive_use'] ?? $_POST['contraceptive_use'] ?? ''); ?></textarea>
                        </div>
                        <div>
                            <label for="previous_complications" class="form-label-premium">Previous Pregnancy Complications</label>
                            <textarea class="form-input-premium min-h-[100px] resize-none py-3" id="previous_complications" name="previous_complications" placeholder="Any issues in earlier gestations..."><?= htmlspecialchars($medicalData['previous_complications'] ?? $_POST['previous_complications'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </section>
                            
                <!-- Husband/Father Information -->
                <section class="card-premium">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-mars text-health-600"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-slate-800">Husband/Partner's Profile Data</h2>
                            <p class="text-xs text-slate-400 font-bold uppercase tracking-widest mt-0.5">Retrieved from verified family records</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                        <div>
                            <label for="husband_first_name" class="form-label-premium">First Name</label>
                            <input type="text" class="form-input-premium" id="husband_first_name" name="husband_first_name" 
                                   value="<?= htmlspecialchars($husbandData['first_name'] ?? $_POST['husband_first_name'] ?? ''); ?>" placeholder="Husband's first name">
                        </div>
                        <div>
                            <label for="husband_middle_name" class="form-label-premium">Middle Name</label>
                            <input type="text" class="form-input-premium" id="husband_middle_name" name="husband_middle_name" 
                                   value="<?= htmlspecialchars($husbandData['middle_name'] ?? $_POST['husband_middle_name'] ?? ''); ?>" placeholder="Optional">
                        </div>
                        <div>
                            <label for="husband_last_name" class="form-label-premium">Last Name</label>
                            <input type="text" class="form-input-premium" id="husband_last_name" name="husband_last_name" 
                                   value="<?= htmlspecialchars($husbandData['last_name'] ?? $_POST['last_name'] ?? ''); ?>" placeholder="Husband's last name">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <div>
                            <label for="husband_date_of_birth" class="form-label-premium">Date of Birth</label>
                            <input type="date" class="form-input-premium" id="husband_date_of_birth" name="husband_date_of_birth" 
                                   value="<?= htmlspecialchars($husbandData['date_of_birth'] ?? $_POST['husband_date_of_birth'] ?? ''); ?>" max="<?= date('Y-m-d'); ?>">
                        </div>
                        <div>
                            <label for="husband_occupation" class="form-label-premium">Occupation</label>
                            <input type="text" class="form-input-premium" id="husband_occupation" name="husband_occupation" 
                                   value="<?= htmlspecialchars($husbandData['occupation'] ?? $_POST['husband_occupation'] ?? ''); ?>" placeholder="Husband's occupation">
                        </div>
                        <div>
                            <label for="husband_education" class="form-label-premium">Education</label>
                            <select class="form-input-premium appearance-none" id="husband_education" name="husband_education">
                                <option value="">Select Education</option>
                                <option value="No Formal Education" <?= (($husbandData['education'] ?? $_POST['husband_education'] ?? '') == 'No Formal Education') ? 'selected' : ''; ?>>No Formal Education</option>
                                <option value="Elementary" <?= (($husbandData['education'] ?? $_POST['husband_education'] ?? '') == 'Elementary') ? 'selected' : ''; ?>>Elementary</option>
                                <option value="High School" <?= (($husbandData['education'] ?? $_POST['husband_education'] ?? '') == 'High School') ? 'selected' : ''; ?>>High School</option>
                                <option value="College" <?= (($husbandData['education'] ?? $_POST['husband_education'] ?? '') == 'College') ? 'selected' : ''; ?>>College</option>
                                <option value="Vocational" <?= (($husbandData['education'] ?? $_POST['husband_education'] ?? '') == 'Vocational') ? 'selected' : ''; ?>>Vocational</option>
                                <option value="Post Graduate" <?= (($husbandData['education'] ?? $_POST['husband_education'] ?? '') == 'Post Graduate') ? 'selected' : ''; ?>>Post Graduate</option>
                            </select>
                        </div>
                        <div>
                            <label for="husband_phone" class="form-label-premium">Phone Number</label>
                            <input type="tel" class="form-input-premium" id="husband_phone" name="husband_phone" 
                                   value="<?= htmlspecialchars($husbandData['phone'] ?? $_POST['husband_phone'] ?? ''); ?>" placeholder="09XXXXXXXXX" pattern="[0-9]{11}">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                        <div>
                            <label for="husband_citizenship" class="form-label-premium">Citizenship</label>
                            <input type="text" class="form-input-premium" id="husband_citizenship" name="husband_citizenship" 
                                   value="<?= htmlspecialchars($husbandData['citizenship'] ?? $_POST['husband_citizenship'] ?? 'Filipino'); ?>" placeholder="Filipino">
                        </div>
                        <div>
                            <label for="husband_religion" class="form-label-premium">Religion</label>
                            <input type="text" class="form-input-premium" id="husband_religion" name="husband_religion" 
                                   value="<?= htmlspecialchars($husbandData['religion'] ?? $_POST['husband_religion'] ?? ''); ?>" placeholder="e.g., Roman Catholic">
                        </div>
                        <div>
                            <label for="marriage_date" class="form-label-premium">Marriage Date</label>
                            <input type="date" class="form-input-premium" id="marriage_date" name="marriage_date" 
                                   value="<?= htmlspecialchars($husbandData['marriage_date'] ?? $_POST['marriage_date'] ?? ''); ?>" max="<?= date('Y-m-d'); ?>">
                        </div>
                    </div>

                    <div>
                        <label for="marriage_place" class="form-label-premium">Marriage Place</label>
                        <input type="text" class="form-input-premium" id="marriage_place" name="marriage_place" 
                               value="<?= htmlspecialchars($husbandData['marriage_place'] ?? $_POST['marriage_place'] ?? ''); ?>" placeholder="Place of marriage">
                    </div>
                </section>
                            
                <!-- Pregnancy Information -->
                <section class="card-premium">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-baby text-health-600"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-slate-800">Pregancy & Gestation Data</h2>
                            <p class="text-xs text-slate-400 font-bold uppercase tracking-widest mt-0.5">Clinical data for current and past pregnancies</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8 border-b border-slate-100 pb-8">
                        <div>
                            <label for="lmp" class="form-label-premium">Last Menstrual Period (LMP) <span class="text-rose-500">*</span></label>
                            <input type="date" class="form-input-premium" id="lmp" name="lmp" 
                                   value="<?= htmlspecialchars($pregnancyData['lmp'] ?? $_POST['lmp'] ?? ''); ?>" required max="<?= date('Y-m-d'); ?>">
                            <div class="hidden mt-2 p-2 bg-rose-50 text-rose-600 text-xs font-bold rounded-lg border border-rose-100" id="lmp_warning">
                                <i class="fas fa-exclamation-triangle me-1"></i> LMP is required for EDC calculation
                            </div>
                        </div>
                        <div>
                            <label for="edc" class="form-label-premium opacity-60">Estimated Due Date (EDC)</label>
                            <input type="date" class="form-input-premium bg-slate-50 cursor-not-allowed font-black text-health-700" id="edc" name="edc" readonly
                                   value="<?= htmlspecialchars($pregnancyData['edc'] ?? $_POST['edc'] ?? ''); ?>">
                        </div>
                        <div>
                            <label for="first_prenatal_visit" class="form-label-premium">First Prenatal Visit</label>
                            <input type="date" class="form-input-premium" id="first_prenatal_visit" name="first_prenatal_visit" 
                                   value="<?= htmlspecialchars($pregnancyData['first_prenatal_visit'] ?? $_POST['first_prenatal_visit'] ?? ''); ?>" max="<?= date('Y-m-d'); ?>">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mb-8">
                        <div>
                            <label for="gravida" class="form-label-premium">Gravida <span class="text-rose-500">*</span></label>
                            <input type="number" class="form-input-premium" id="gravida" name="gravida" 
                                   value="<?= htmlspecialchars($pregnancyData['gravida'] ?? $_POST['gravida'] ?? ''); ?>" min="1" required placeholder="Total">
                            <div class="hidden mt-2 p-2 bg-rose-50 text-rose-600 text-xs font-bold rounded-lg border border-rose-100" id="gravida_warning">
                                <i class="fas fa-exclamation-triangle me-1"></i> Required
                            </div>
                        </div>
                        <div>
                            <label for="para" class="form-label-premium">Para <span class="text-rose-500">*</span></label>
                            <input type="number" class="form-input-premium" id="para" name="para" 
                                   value="<?= htmlspecialchars($pregnancyData['para'] ?? $_POST['para'] ?? ''); ?>" min="0" required placeholder="Live">
                            <div class="hidden mt-2 p-2 bg-rose-50 text-rose-600 text-xs font-bold rounded-lg border border-rose-100" id="para_warning">
                                <i class="fas fa-exclamation-triangle me-1"></i> Required
                            </div>
                        </div>
                        <div>
                            <label for="abortions" class="form-label-premium text-rose-400">Abortions</label>
                            <input type="number" class="form-input-premium border-rose-100" id="abortions" name="abortions" 
                                   value="<?= htmlspecialchars($pregnancyData['abortions'] ?? $_POST['abortions'] ?? '0'); ?>" min="0" placeholder="0">
                        </div>
                        <div>
                            <label for="living_children" class="form-label-premium text-sky-400">Living Children</label>
                            <input type="number" class="form-input-premium border-sky-100" id="living_children" name="living_children" 
                                   value="<?= htmlspecialchars($pregnancyData['living_children'] ?? $_POST['living_children'] ?? ''); ?>" min="0" placeholder="0">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="planned_pregnancy" class="form-label-premium">Planned Pregnancy</label>
                            <select class="form-input-premium appearance-none" id="planned_pregnancy" name="planned_pregnancy">
                                <option value="">Select Option</option>
                                <option value="Yes" <?= (($pregnancyData['planned_pregnancy'] ?? $_POST['planned_pregnancy'] ?? '') == 'Yes') ? 'selected' : ''; ?>>Yes</option>
                                <option value="No" <?= (($pregnancyData['planned_pregnancy'] ?? $_POST['planned_pregnancy'] ?? '') == 'No') ? 'selected' : ''; ?>>No</option>
                                <option value="Unsure" <?= (($pregnancyData['planned_pregnancy'] ?? $_POST['planned_pregnancy'] ?? '') == 'Unsure') ? 'selected' : ''; ?>>Unsure</option>
                            </select>
                        </div>
                        <div>
                            <label for="referred_by" class="form-label-premium">Referred By</label>
                            <input type="text" class="form-input-premium" id="referred_by" name="referred_by" 
                                   value="<?= htmlspecialchars($pregnancyData['referred_by'] ?? $_POST['referred_by'] ?? ''); ?>" placeholder="Name of clinic or health worker">
                        </div>
                    </div>
                </section>
                <!-- Form Actions -->
                <div class="flex flex-col sm:flex-row justify-end items-center gap-4 pt-8">
                    <button type="button" onclick="window.history.back()" 
                            class="w-full sm:w-auto px-8 py-4 bg-slate-100 text-slate-600 rounded-2xl font-bold uppercase tracking-widest text-xs hover:bg-slate-200 transition-all active:scale-95">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="w-full sm:w-auto px-12 py-4 bg-health-600 text-white rounded-2xl font-black uppercase tracking-widest text-xs hover:bg-health-700 shadow-xl shadow-health-200 transition-all active:scale-95 flex items-center justify-center gap-2">
                        <i class="fas fa-save shadow-sm"></i>
                        <?php echo $editMode ? 'Update Mother Profile' : 'Register Mother'; ?>
                    </button>
                </div>
            </form>
        </main>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Calculate EDC based on LMP
        document.getElementById('lmp').addEventListener('change', function() {
            const lmp = new Date(this.value);
            if (!isNaN(lmp.getTime())) {
                const edc = new Date(lmp);
                edc.setDate(edc.getDate() + 280); // 40 weeks
                document.getElementById('edc').value = edc.toISOString().split('T')[0];
            }
        });

        // Set maximum dates to today
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('date_of_birth').max = today;
            document.getElementById('lmp').max = today;
            document.getElementById('first_prenatal_visit').max = today;
            document.getElementById('husband_date_of_birth').max = today;
            document.getElementById('marriage_date').max = today;

            // Auto-calculate EDC if LMP is already filled
            const lmp = document.getElementById('lmp').value;
            if (lmp) {
                document.getElementById('lmp').dispatchEvent(new Event('change'));
            }

            // Add event listeners for validation
            setupFieldValidation();
        });

        function setupFieldValidation() {
            const form = document.getElementById('motherRegistrationForm');
            const requiredFields = [
                'first_name', 'last_name', 'date_of_birth', 'civil_status', 
                'phone', 'address', 'lmp', 'gravida', 'para'
            ];

            // Add event listeners to all required fields
            requiredFields.forEach(field => {
                const input = document.getElementById(field);
                if (input) {
                    input.addEventListener('blur', validateRequiredField);
                    input.addEventListener('input', hideWarning);
                }
            });

            // Add validation for phone field
            const phone = document.getElementById('phone');
            if (phone) {
                phone.addEventListener('blur', validatePhoneField);
                phone.addEventListener('input', hideWarning);
            }

            // Form submission validation
            form.addEventListener('submit', function(e) {
                let isValid = true;

                // Validate all required fields
                requiredFields.forEach(field => {
                    if (!validateRequiredField({ target: document.getElementById(field) })) {
                        isValid = false;
                    }
                });

                // Validate phone field
                if (phone && !validatePhoneField({ target: phone })) {
                    isValid = false;
                }

                if (!isValid) {
                    e.preventDefault();
                    // Scroll to first error
                    const firstError = document.querySelector('[id$="_warning"]:not(.hidden)');
                    if (firstError) {
                        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        // Add a subtle shake effect to the first invalid field
                        const errorField = document.getElementById(firstError.id.replace('_warning', ''));
                        if (errorField) {
                            errorField.classList.add('animate-pulse');
                            setTimeout(() => errorField.classList.remove('animate-pulse'), 1000);
                        }
                    }
                }
            });
        }

        function validateRequiredField(e) {
            const field = e.target;
            const fieldId = field.id;
            const value = field.value.trim();

            if (value === '') {
                showWarning(fieldId, getFieldWarningMessage(fieldId));
                return false;
            }

            hideWarning(fieldId);
            return true;
        }

        function validatePhoneField(e) {
            const field = e.target;
            const fieldId = field.id;
            const value = field.value.trim();

            if (value !== '' && !/^[0-9]{11}$/.test(value)) {
                showWarning(fieldId, 'Please enter a valid 11-digit phone number');
                return false;
            }

            hideWarning(fieldId);
            return true;
        }

        function getFieldWarningMessage(fieldId) {
            const messages = {
                'first_name': 'Please enter mother\'s first name',
                'last_name': 'Please enter mother\'s last name',
                'date_of_birth': 'Please select date of birth',
                'civil_status': 'Please select civil status',
                'phone': 'Please enter a valid 11-digit phone number',
                'address': 'Please select address from the list',
                'lmp': 'Please select last menstrual period date',
                'gravida': 'Please enter number of pregnancies',
                'para': 'Please enter number of live births'
            };
            return messages[fieldId] || 'This field is required';
        }

        function showWarning(fieldId, message) {
            const warning = document.getElementById(fieldId + '_warning');
            if (warning) {
                // Update message if it's different (optional, allows dynamic messages)
                const textSpan = warning.querySelector('span') || warning;
                if (textSpan.tagName === 'SPAN') {
                    textSpan.textContent = message;
                } else {
                    // If it's the div itself, we need to be careful not to overwrite the icon
                    const icon = warning.querySelector('i');
                    if (icon) {
                        warning.innerHTML = '';
                        warning.appendChild(icon);
                        const textNode = document.createTextNode(' ' + message);
                        warning.appendChild(textNode);
                    } else {
                        warning.textContent = message;
                    }
                }
                
                warning.classList.remove('hidden');
                
                // Highlight the field
                const field = document.getElementById(fieldId);
                if (field) {
                    field.classList.add('border-rose-500', 'ring-2', 'ring-rose-200');
                }
            }
        }

        function hideWarning(fieldId) {
            if (typeof fieldId === 'object') {
                fieldId = fieldId.target.id;
            }
            
            const warning = document.getElementById(fieldId + '_warning');
            if (warning) {
                warning.classList.add('hidden');
                
                // Remove highlight from the field
                const field = document.getElementById(fieldId);
                if (field) {
                    field.classList.remove('border-rose-500', 'ring-2', 'ring-rose-200');
                }
            }
        }
    </script>
</body>
</html>