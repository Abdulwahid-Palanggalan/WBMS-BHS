<?php
require_once dirname(__FILE__) . '/../config/config.php';

// Check if user can register their own profile
if (!canRegisterOwnProfile()) {
    header("Location: ../login.php");
    exit();
}

redirectIfNotLoggedIn();

global $pdo;

$message = '';
$error = '';

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
    $nationality = trim($_POST['nationality'] ?? '');
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
    $gravida = $_POST['gravida'] ?? '';
    $para = $_POST['para'] ?? '';
    $abortions = $_POST['abortions'] ?? 0;
    $livingChildren = $_POST['living_children'] ?? '';
    $plannedPregnancy = $_POST['planned_pregnancy'] ?? '';
    $firstPrenatalVisit = $_POST['first_prenatal_visit'] ?? '';
    $referredBy = trim($_POST['referred_by'] ?? '');
    
    // Medical History Information
    $allergies = trim($_POST['allergies'] ?? '');
    $medicalConditions = trim($_POST['medical_conditions'] ?? '');
    $previousSurgeries = trim($_POST['previous_surgeries'] ?? '');
    $familyHistory = trim($_POST['family_history'] ?? '');
    $contraceptiveUse = trim($_POST['contraceptive_use'] ?? '');
    $previousComplications = trim($_POST['previous_complications'] ?? '');
    
    // Calculate EDC based on LMP (40 weeks)
    $edc = !empty($lmp) ? date('Y-m-d', strtotime($lmp . ' + 280 days')) : '';
    
    $userId = $_SESSION['user_id'];
    $registeredBy = $_SESSION['user_id']; // User registers their own profile
    
    try {
        $pdo->beginTransaction();
        
        // Check if mother profile already exists
        $checkStmt = $pdo->prepare("SELECT id FROM mothers WHERE user_id = ?");
        $checkStmt->execute([$userId]);
        
        if ($checkStmt->fetch()) {
            $error = "You already have a mother profile. Please update your existing profile instead.";
            $pdo->rollBack();
        } else {
            // Update user information first
            $userSql = "UPDATE users SET 
                       first_name = ?, middle_name = ?, last_name = ?, email = ?, phone = ? 
                       WHERE id = ?";
            $userStmt = $pdo->prepare($userSql);
            $userStmt->execute([$firstName, $middleName, $lastName, $email, $phone, $userId]);
            
            // Insert into mothers table
            $motherSql = "INSERT INTO mothers (
                user_id, first_name, middle_name, last_name, date_of_birth, civil_status, 
                nationality, religion, education, occupation, phone, email, address, 
                emergency_contact, emergency_phone, blood_type, rh_factor, registered_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $motherStmt = $pdo->prepare($motherSql);
            $motherStmt->execute([
                $userId, $firstName, $middleName, $lastName, $dateOfBirth, $civilStatus,
                $nationality, $religion, $education, $occupation, $phone, $email, $address,
                $emergencyContact, $emergencyPhone, $bloodType, $rhFactor, $registeredBy
            ]);
            
            $motherId = $pdo->lastInsertId();
            
            if ($motherId) {
                // Insert into husband_partners table if husband information is provided
                if (!empty($husbandFirstName) || !empty($husbandLastName)) {
                    $husbandSql = "INSERT INTO husband_partners (
                        mother_id, first_name, middle_name, last_name, date_of_birth, 
                        occupation, education, phone, citizenship, religion, marriage_date, marriage_place
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    
                    $husbandStmt = $pdo->prepare($husbandSql);
                    $husbandStmt->execute([
                        $motherId, $husbandFirstName, $husbandMiddleName, $husbandLastName,
                        $husbandDateOfBirth, $husbandOccupation, $husbandEducation, $husbandPhone,
                        $husbandCitizenship, $husbandReligion, $marriageDate, $marriagePlace
                    ]);
                }
                
                // Insert into pregnancy_details table
                $pregnancySql = "INSERT INTO pregnancy_details (
                    mother_id, lmp, edc, gravida, para, abortions, living_children, 
                    planned_pregnancy, first_prenatal_visit, referred_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $pregnancyStmt = $pdo->prepare($pregnancySql);
                $pregnancyStmt->execute([
                    $motherId, $lmp, $edc, $gravida, $para, $abortions, $livingChildren,
                    $plannedPregnancy, $firstPrenatalVisit, $referredBy
                ]);
                
                // Insert into medical_histories table
                $medicalSql = "INSERT INTO medical_histories (
                    mother_id, allergies, medical_conditions, previous_surgeries, 
                    family_history, contraceptive_use, previous_complications
                ) VALUES (?, ?, ?, ?, ?, ?, ?)";
                
                $medicalStmt = $pdo->prepare($medicalSql);
                $medicalStmt->execute([
                    $motherId, $allergies, $medicalConditions, $previousSurgeries,
                    $familyHistory, $contraceptiveUse, $previousComplications
                ]);
                
                $pdo->commit();
                
                $message = "
                <div class='alert alert-success'>
                    <h5><i class='fas fa-check-circle'></i> Mother Profile Created Successfully!</h5>
                    <strong>Name:</strong> $firstName " . ($middleName ? $middleName . ' ' : '') . "$lastName<br>
                    <strong>EDC:</strong> " . (!empty($edc) ? date('F j, Y', strtotime($edc)) : 'Not calculated') . "
                </div>";
                
                logActivity($userId, "Created own mother profile: $firstName " . ($middleName ? $middleName . ' ' : '') . "$lastName");
                
                // Redirect to dashboard after 3 seconds
                header("Refresh: 3; URL=../dashboard.php");
            } else {
                $pdo->rollBack();
                $error = "Failed to create mother profile. Please try again.";
            }
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

// Get current user data to pre-fill the form
$userId = $_SESSION['user_id'];
$userStmt = $pdo->prepare("SELECT first_name, middle_name, last_name, email, phone FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$userData = $userStmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Mother Profile - Health Station System </title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .section-title {
            background: linear-gradient(135deg, #1a73e8 0%, #6a11cb 100%);
            color: white;
            padding: 10px 15px;
            border-radius: 8px;
            margin: 20px 0 15px 0;
            font-size: 1.1rem;
            font-weight: 600;
        }
        .required-field::after {
            content: " *";
            color: #dc3545;
        }
        .form-control, .form-select {
            border-radius: 6px;
            border: 1.5px solid #e0e0e0;
        }
        .form-control:focus, .form-select:focus {
            border-color: #1a73e8;
            box-shadow: 0 0 0 2px rgba(26, 115, 232, 0.15);
        }
        .husband-section {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            border-left: 4px solid #28a745;
        }
        .field-warning {
            font-size: 0.875rem;
            color: #856404;
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 4px;
            padding: 5px 10px;
            margin-top: 5px;
            display: none;
        }
        .is-invalid {
            border-color: #dc3545 !important;
            box-shadow: 0 0 0 2px rgba(220, 53, 69, 0.15) !important;
        }
        .is-valid {
            border-color: #198754 !important;
        }
        .warning-icon {
            color: #856404;
            margin-right: 5px;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-text {
            font-size: 0.8rem;
        }
    </style>
</head>
<body>
    <?php include_once INCLUDE_PATH . 'header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include_once INCLUDE_PATH . 'sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Register Mother Profile</h1>
                </div>
                
                <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo $message; ?>
                </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo $error; ?>
                </div>
                <?php endif; ?>

                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    You are creating your own mother profile. This information will be used for prenatal and postnatal care tracking.
                    Fields marked with <span class="text-danger">*</span> are required.
                </div>
                
                <div class="card">
                    <div class="card-body">
                        <form method="POST" action="" id="motherRegistrationForm" novalidate>
                            <!-- Personal Information -->
                            <div class="section-title">Personal Information</div>
                            <div class="row">
                                <div class="col-md-4 form-group">
                                    <label for="first_name" class="form-label required-field">First Name</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                           value="<?= htmlspecialchars($userData['first_name'] ?? $_POST['first_name'] ?? ''); ?>" required>
                                    <div class="field-warning" id="first_name_warning">
                                        <i class="fas fa-exclamation-triangle warning-icon"></i>
                                        Please enter your first name
                                    </div>
                                </div>
                                <div class="col-md-4 form-group">
                                    <label for="middle_name" class="form-label">Middle Name</label>
                                    <input type="text" class="form-control" id="middle_name" name="middle_name" 
                                           value="<?= htmlspecialchars($userData['middle_name'] ?? $_POST['middle_name'] ?? ''); ?>" placeholder="Optional">
                                </div>
                                <div class="col-md-4 form-group">
                                    <label for="last_name" class="form-label required-field">Last Name</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                           value="<?= htmlspecialchars($userData['last_name'] ?? $_POST['last_name'] ?? ''); ?>" required>
                                    <div class="field-warning" id="last_name_warning">
                                        <i class="fas fa-exclamation-triangle warning-icon"></i>
                                        Please enter your last name
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-3 form-group">
                                    <label for="date_of_birth" class="form-label required-field">Date of Birth</label>
                                    <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" 
                                           value="<?= htmlspecialchars($_POST['date_of_birth'] ?? ''); ?>" required max="<?= date('Y-m-d'); ?>">
                                    <div class="field-warning" id="date_of_birth_warning">
                                        <i class="fas fa-exclamation-triangle warning-icon"></i>
                                        Please select your date of birth
                                    </div>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label for="civil_status" class="form-label required-field">Civil Status</label>
                                    <select class="form-select" id="civil_status" name="civil_status" required>
                                        <option value="">Select Status</option>
                                        <option value="Single" <?= ($_POST['civil_status'] ?? '') == 'Single' ? 'selected' : ''; ?>>Single</option>
                                        <option value="Married" <?= ($_POST['civil_status'] ?? '') == 'Married' ? 'selected' : ''; ?>>Married</option>
                                        <option value="Divorced" <?= ($_POST['civil_status'] ?? '') == 'Divorced' ? 'selected' : ''; ?>>Divorced</option>
                                        <option value="Widowed" <?= ($_POST['civil_status'] ?? '') == 'Widowed' ? 'selected' : ''; ?>>Widowed</option>
                                        <option value="Separated" <?= ($_POST['civil_status'] ?? '') == 'Separated' ? 'selected' : ''; ?>>Separated</option>
                                    </select>
                                    <div class="field-warning" id="civil_status_warning">
                                        <i class="fas fa-exclamation-triangle warning-icon"></i>
                                        Please select civil status
                                    </div>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label for="nationality" class="form-label">Nationality</label>
                                    <input type="text" class="form-control" id="nationality" name="nationality" 
                                           value="<?= htmlspecialchars($_POST['nationality'] ?? 'Filipino'); ?>" placeholder="Filipino">
                                </div>
                                <div class="col-md-3 form-group">
                                    <label for="religion" class="form-label">Religion</label>
                                    <input type="text" class="form-control" id="religion" name="religion" 
                                           value="<?= htmlspecialchars($_POST['religion'] ?? ''); ?>" placeholder="e.g., Roman Catholic">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 form-group">
                                    <label for="education" class="form-label">Education</label>
                                    <select class="form-select" id="education" name="education">
                                        <option value="">Select Education</option>
                                        <option value="No Formal Education" <?= ($_POST['education'] ?? '') == 'No Formal Education' ? 'selected' : ''; ?>>No Formal Education</option>
                                        <option value="Elementary" <?= ($_POST['education'] ?? '') == 'Elementary' ? 'selected' : ''; ?>>Elementary</option>
                                        <option value="High School" <?= ($_POST['education'] ?? '') == 'High School' ? 'selected' : ''; ?>>High School</option>
                                        <option value="College" <?= ($_POST['education'] ?? '') == 'College' ? 'selected' : ''; ?>>College</option>
                                        <option value="Vocational" <?= ($_POST['education'] ?? '') == 'Vocational' ? 'selected' : ''; ?>>Vocational</option>
                                        <option value="Post Graduate" <?= ($_POST['education'] ?? '') == 'Post Graduate' ? 'selected' : ''; ?>>Post Graduate</option>
                                    </select>
                                </div>
                                <div class="col-md-4 form-group">
                                    <label for="occupation" class="form-label">Occupation</label>
                                    <input type="text" class="form-control" id="occupation" name="occupation" 
                                           value="<?= htmlspecialchars($_POST['occupation'] ?? ''); ?>" placeholder="e.g., Housewife, Teacher">
                                </div>
                            </div>
                            
                            <!-- Contact Information -->
                            <div class="section-title">Contact Information</div>
                            <div class="row">
                                <div class="col-md-4 form-group">
                                    <label for="phone" class="form-label required-field">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?= htmlspecialchars($userData['phone'] ?? $_POST['phone'] ?? ''); ?>" required placeholder="09XXXXXXXXX" pattern="[0-9]{11}">
                                    <div class="field-warning" id="phone_warning">
                                        <i class="fas fa-exclamation-triangle warning-icon"></i>
                                        Please enter a valid 11-digit phone number
                                    </div>
                                </div>
                                <div class="col-md-4 form-group">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?= htmlspecialchars($userData['email'] ?? $_POST['email'] ?? ''); ?>" placeholder="optional@email.com">
                                </div>
                                <div class="col-md-4 form-group">
                                    <label for="address" class="form-label required-field">Address</label>
                                    <select class="form-select" id="address" name="address" required>
                                        <option value="">Select Address</option>
                                        <?php foreach ($addressOptions as $addressOption): ?>
                                            <option value="<?= htmlspecialchars($addressOption); ?>" 
                                                <?= ($_POST['address'] ?? '') == $addressOption ? 'selected' : ''; ?>>
                                                <?= htmlspecialchars($addressOption); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="field-warning" id="address_warning">
                                        <i class="fas fa-exclamation-triangle warning-icon"></i>
                                        Please select your address from the list
                                    </div>
                                </div>
                            </div>

                            <!-- Emergency Contact -->
                            <div class="row">
                                <div class="col-md-6 form-group">
                                    <label for="emergency_contact" class="form-label">Emergency Contact Person</label>
                                    <input type="text" class="form-control" id="emergency_contact" name="emergency_contact" 
                                           value="<?= htmlspecialchars($_POST['emergency_contact'] ?? ''); ?>" placeholder="Full name of emergency contact">
                                </div>
                                <div class="col-md-6 form-group">
                                    <label for="emergency_phone" class="form-label">Emergency Contact Phone</label>
                                    <input type="tel" class="form-control" id="emergency_phone" name="emergency_phone" 
                                           value="<?= htmlspecialchars($_POST['emergency_phone'] ?? ''); ?>" placeholder="09XXXXXXXXX" pattern="[0-9]{11}">
                                </div>
                            </div>
                            
                            <!-- Medical Information -->
                            <div class="section-title">Medical Information</div>
                            <div class="row">
                                <div class="col-md-3 form-group">
                                    <label for="blood_type" class="form-label">Blood Type</label>
                                    <select class="form-select" id="blood_type" name="blood_type">
                                        <option value="">Select Blood Type</option>
                                        <option value="A+" <?= ($_POST['blood_type'] ?? '') == 'A+' ? 'selected' : ''; ?>>A+</option>
                                        <option value="A-" <?= ($_POST['blood_type'] ?? '') == 'A-' ? 'selected' : ''; ?>>A-</option>
                                        <option value="B+" <?= ($_POST['blood_type'] ?? '') == 'B+' ? 'selected' : ''; ?>>B+</option>
                                        <option value="B-" <?= ($_POST['blood_type'] ?? '') == 'B-' ? 'selected' : ''; ?>>B-</option>
                                        <option value="AB+" <?= ($_POST['blood_type'] ?? '') == 'AB+' ? 'selected' : ''; ?>>AB+</option>
                                        <option value="AB-" <?= ($_POST['blood_type'] ?? '') == 'AB-' ? 'selected' : ''; ?>>AB-</option>
                                        <option value="O+" <?= ($_POST['blood_type'] ?? '') == 'O+' ? 'selected' : ''; ?>>O+</option>
                                        <option value="O-" <?= ($_POST['blood_type'] ?? '') == 'O-' ? 'selected' : ''; ?>>O-</option>
                                    </select>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label for="rh_factor" class="form-label">RH Factor</label>
                                    <select class="form-select" id="rh_factor" name="rh_factor">
                                        <option value="">Select RH Factor</option>
                                        <option value="Positive" <?= ($_POST['rh_factor'] ?? '') == 'Positive' ? 'selected' : ''; ?>>Positive</option>
                                        <option value="Negative" <?= ($_POST['rh_factor'] ?? '') == 'Negative' ? 'selected' : ''; ?>>Negative</option>
                                        <option value="Unknown" <?= ($_POST['rh_factor'] ?? '') == 'Unknown' ? 'selected' : ''; ?>>Unknown</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Medical History -->
                            <div class="row">
                                <div class="col-md-6 form-group">
                                    <label for="allergies" class="form-label">Allergies</label>
                                    <textarea class="form-control" id="allergies" name="allergies" rows="2" placeholder="List any known allergies"><?= htmlspecialchars($_POST['allergies'] ?? ''); ?></textarea>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label for="medical_conditions" class="form-label">Medical Conditions</label>
                                    <textarea class="form-control" id="medical_conditions" name="medical_conditions" rows="2" placeholder="e.g., Hypertension, Diabetes, Asthma"><?= htmlspecialchars($_POST['medical_conditions'] ?? ''); ?></textarea>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 form-group">
                                    <label for="previous_surgeries" class="form-label">Previous Surgeries</label>
                                    <textarea class="form-control" id="previous_surgeries" name="previous_surgeries" rows="2" placeholder="List any previous surgeries"><?= htmlspecialchars($_POST['previous_surgeries'] ?? ''); ?></textarea>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label for="family_history" class="form-label">Family Medical History</label>
                                    <textarea class="form-control" id="family_history" name="family_history" rows="2" placeholder="Family history of medical conditions"><?= htmlspecialchars($_POST['family_history'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            
                            <!-- Husband/Father Information -->
                            <div class="section-title">Husband/Father Information</div>
                            <div class="husband-section">
                                <div class="row">
                                    <div class="col-md-4 form-group">
                                        <label for="husband_first_name" class="form-label">First Name</label>
                                        <input type="text" class="form-control" id="husband_first_name" name="husband_first_name" 
                                               value="<?= htmlspecialchars($_POST['husband_first_name'] ?? ''); ?>" placeholder="Husband's first name">
                                    </div>
                                    <div class="col-md-4 form-group">
                                        <label for="husband_middle_name" class="form-label">Middle Name</label>
                                        <input type="text" class="form-control" id="husband_middle_name" name="husband_middle_name" 
                                               value="<?= htmlspecialchars($_POST['husband_middle_name'] ?? ''); ?>" placeholder="Optional">
                                    </div>
                                    <div class="col-md-4 form-group">
                                        <label for="husband_last_name" class="form-label">Last Name</label>
                                        <input type="text" class="form-control" id="husband_last_name" name="husband_last_name" 
                                               value="<?= htmlspecialchars($_POST['husband_last_name'] ?? ''); ?>" placeholder="Husband's last name">
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-3 form-group">
                                        <label for="husband_date_of_birth" class="form-label">Date of Birth</label>
                                        <input type="date" class="form-control" id="husband_date_of_birth" name="husband_date_of_birth" 
                                               value="<?= htmlspecialchars($_POST['husband_date_of_birth'] ?? ''); ?>" max="<?= date('Y-m-d'); ?>">
                                    </div>
                                    <div class="col-md-3 form-group">
                                        <label for="husband_occupation" class="form-label">Occupation</label>
                                        <input type="text" class="form-control" id="husband_occupation" name="husband_occupation" 
                                               value="<?= htmlspecialchars($_POST['husband_occupation'] ?? ''); ?>" placeholder="Husband's occupation">
                                    </div>
                                    <div class="col-md-3 form-group">
                                        <label for="husband_education" class="form-label">Education</label>
                                        <select class="form-select" id="husband_education" name="husband_education">
                                            <option value="">Select Education</option>
                                            <option value="No Formal Education" <?= ($_POST['husband_education'] ?? '') == 'No Formal Education' ? 'selected' : ''; ?>>No Formal Education</option>
                                            <option value="Elementary" <?= ($_POST['husband_education'] ?? '') == 'Elementary' ? 'selected' : ''; ?>>Elementary</option>
                                            <option value="High School" <?= ($_POST['husband_education'] ?? '') == 'High School' ? 'selected' : ''; ?>>High School</option>
                                            <option value="College" <?= ($_POST['husband_education'] ?? '') == 'College' ? 'selected' : ''; ?>>College</option>
                                            <option value="Vocational" <?= ($_POST['husband_education'] ?? '') == 'Vocational' ? 'selected' : ''; ?>>Vocational</option>
                                            <option value="Post Graduate" <?= ($_POST['husband_education'] ?? '') == 'Post Graduate' ? 'selected' : ''; ?>>Post Graduate</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3 form-group">
                                        <label for="husband_phone" class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" id="husband_phone" name="husband_phone" 
                                               value="<?= htmlspecialchars($_POST['husband_phone'] ?? ''); ?>" placeholder="09XXXXXXXXX" pattern="[0-9]{11}">
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-4 form-group">
                                        <label for="husband_citizenship" class="form-label">Citizenship</label>
                                        <input type="text" class="form-control" id="husband_citizenship" name="husband_citizenship" 
                                               value="<?= htmlspecialchars($_POST['husband_citizenship'] ?? 'Filipino'); ?>" placeholder="Filipino">
                                    </div>
                                    <div class="col-md-4 form-group">
                                        <label for="husband_religion" class="form-label">Religion</label>
                                        <input type="text" class="form-control" id="husband_religion" name="husband_religion" 
                                               value="<?= htmlspecialchars($_POST['husband_religion'] ?? ''); ?>" placeholder="Husband's religion">
                                    </div>
                                    <div class="col-md-4 form-group">
                                        <label for="marriage_date" class="form-label">Marriage Date</label>
                                        <input type="date" class="form-control" id="marriage_date" name="marriage_date" 
                                               value="<?= htmlspecialchars($_POST['marriage_date'] ?? ''); ?>" max="<?= date('Y-m-d'); ?>">
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-12 form-group">
                                        <label for="marriage_place" class="form-label">Marriage Place</label>
                                        <input type="text" class="form-control" id="marriage_place" name="marriage_place" 
                                               value="<?= htmlspecialchars($_POST['marriage_place'] ?? ''); ?>" placeholder="Place of marriage">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Obstetric History -->
                            <div class="section-title">Pregnancy Information</div>
                            <div class="row">
                                <div class="col-md-4 form-group">
                                    <label for="lmp" class="form-label required-field">Last Menstrual Period (LMP)</label>
                                    <input type="date" class="form-control" id="lmp" name="lmp" 
                                           value="<?= htmlspecialchars($_POST['lmp'] ?? ''); ?>" required max="<?= date('Y-m-d'); ?>">
                                    <div class="field-warning" id="lmp_warning">
                                        <i class="fas fa-exclamation-triangle warning-icon"></i>
                                        Required to calculate due date
                                    </div>
                                </div>
                                <div class="col-md-4 form-group">
                                    <label for="edc" class="form-label">Estimated Due Date (EDC)</label>
                                    <input type="date" class="form-control" id="edc" name="edc" readonly
                                           value="<?= htmlspecialchars($_POST['edc'] ?? ''); ?>">
                                </div>
                                <div class="col-md-4 form-group">
                                    <label for="first_prenatal_visit" class="form-label">First Prenatal Visit</label>
                                    <input type="date" class="form-control" id="first_prenatal_visit" name="first_prenatal_visit" 
                                           value="<?= htmlspecialchars($_POST['first_prenatal_visit'] ?? ''); ?>" max="<?= date('Y-m-d'); ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-3 form-group">
                                    <label for="gravida" class="form-label required-field">Gravida</label>
                                    <input type="number" class="form-control" id="gravida" name="gravida" 
                                           value="<?= htmlspecialchars($_POST['gravida'] ?? '1'); ?>" min="1" required placeholder="Total pregnancies">
                                    <div class="field-warning" id="gravida_warning">
                                        <i class="fas fa-exclamation-triangle warning-icon"></i>
                                        Please enter total number of pregnancies
                                    </div>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label for="para" class="form-label required-field">Para</label>
                                    <input type="number" class="form-control" id="para" name="para" 
                                           value="<?= htmlspecialchars($_POST['para'] ?? '0'); ?>" min="0" required placeholder="Live births">
                                    <div class="field-warning" id="para_warning">
                                        <i class="fas fa-exclamation-triangle warning-icon"></i>
                                        Please enter number of live births
                                    </div>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label for="abortions" class="form-label">Abortions</label>
                                    <input type="number" class="form-control" id="abortions" name="abortions" 
                                           value="<?= htmlspecialchars($_POST['abortions'] ?? '0'); ?>" min="0" placeholder="0">
                                </div>
                                <div class="col-md-3 form-group">
                                    <label for="living_children" class="form-label">Living Children</label>
                                    <input type="number" class="form-control" id="living_children" name="living_children" 
                                           value="<?= htmlspecialchars($_POST['living_children'] ?? ''); ?>" min="0" placeholder="Current living children">
                                </div>
                            </div>

                            <!-- Additional Obstetric Information -->
                            <div class="row">
                                <div class="col-md-6 form-group">
                                    <label for="previous_complications" class="form-label">Previous Pregnancy Complications</label>
                                    <textarea class="form-control" id="previous_complications" name="previous_complications" rows="2" placeholder="Complications in previous pregnancies"><?= htmlspecialchars($_POST['previous_complications'] ?? ''); ?></textarea>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label for="contraceptive_use" class="form-label">Previous Contraceptive Use</label>
                                    <textarea class="form-control" id="contraceptive_use" name="contraceptive_use" rows="2" placeholder="Previous contraceptive methods used"><?= htmlspecialchars($_POST['contraceptive_use'] ?? ''); ?></textarea>
                                </div>
                            </div>

                            <!-- Current Pregnancy -->
                            <div class="row">
                                <div class="col-md-4 form-group">
                                    <label for="planned_pregnancy" class="form-label">Planned Pregnancy</label>
                                    <select class="form-select" id="planned_pregnancy" name="planned_pregnancy">
                                        <option value="">Select</option>
                                        <option value="Yes" <?= ($_POST['planned_pregnancy'] ?? '') == 'Yes' ? 'selected' : ''; ?>>Yes</option>
                                        <option value="No" <?= ($_POST['planned_pregnancy'] ?? '') == 'No' ? 'selected' : ''; ?>>No</option>
                                        <option value="Unsure" <?= ($_POST['planned_pregnancy'] ?? '') == 'Unsure' ? 'selected' : ''; ?>>Unsure</option>
                                    </select>
                                </div>
                                <div class="col-md-8 form-group">
                                    <label for="referred_by" class="form-label">Referred By</label>
                                    <input type="text" class="form-control" id="referred_by" name="referred_by" 
                                           value="<?= htmlspecialchars($_POST['referred_by'] ?? ''); ?>" placeholder="Name of person or facility who referred you">
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                                <button type="button" onclick="window.history.back()" class="btn btn-secondary me-md-2">Cancel</button>
                                <button type="submit" class="btn btn-primary">Create My Profile</button>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
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
                    const firstError = document.querySelector('.field-warning[style*="display: block"]');
                    if (firstError) {
                        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                    // Show general alert
                    alert('Please fix the errors in the form before submitting.');
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
                'first_name': 'Please enter your first name',
                'last_name': 'Please enter your last name',
                'date_of_birth': 'Please select your date of birth',
                'civil_status': 'Please select your civil status',
                'phone': 'Please enter a valid 11-digit phone number',
                'address': 'Please select your address from the list',
                'lmp': 'Please select your last menstrual period date',
                'gravida': 'Please enter number of pregnancies',
                'para': 'Please enter number of live births'
            };
            return messages[fieldId] || 'This field is required';
        }

        function showWarning(fieldId, message) {
            const warning = document.getElementById(fieldId + '_warning');
            if (warning) {
                warning.textContent = message;
                warning.style.display = 'block';
                
                // Highlight the field
                const field = document.getElementById(fieldId);
                if (field) {
                    field.classList.add('is-invalid');
                }
            }
        }

        function hideWarning(fieldId) {
            if (typeof fieldId === 'object') {
                fieldId = fieldId.target.id;
            }
            
            const warning = document.getElementById(fieldId + '_warning');
            if (warning) {
                warning.style.display = 'none';
                
                // Remove highlight from the field
                const field = document.getElementById(fieldId);
                if (field) {
                    field.classList.remove('is-invalid');
                }
            }
        }
    </script>
</body>
</html>