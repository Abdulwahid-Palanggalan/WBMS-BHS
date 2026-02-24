<?php
require_once dirname(__FILE__) . '/../config/config.php';

redirectIfNotLoggedIn();

if (!canRegisterBirths()) {
    header("Location: ../login.php");
    exit();
}

global $pdo;

$message = '';
$error = '';
$isEditMode = false;
$birthRecord = null;

// Check if we're editing an existing birth record
if ((isset($_GET['edit_id']) && !empty($_GET['edit_id'])) || (isset($_GET['edit']) && !empty($_GET['edit']))) {
    $isEditMode = true;
    $birthId = isset($_GET['edit_id']) ? $_GET['edit_id'] : $_GET['edit'];
    
    // Fetch the existing birth record with related data
    $stmt = $pdo->prepare("
        SELECT br.*, 
               m.first_name as mother_first_name, m.middle_name as mother_middle_name, m.last_name as mother_last_name,
               m.date_of_birth as mother_birthdate, m.nationality as mother_citizenship, m.religion as mother_religion,
               m.occupation as mother_occupation,
               hp.first_name as father_first_name, hp.middle_name as father_middle_name, hp.last_name as father_last_name,
               hp.date_of_birth as father_birthdate, hp.citizenship as father_citizenship, hp.religion as father_religion,
               hp.occupation as father_occupation, hp.marriage_date as parents_marriage_date, hp.marriage_place as parents_marriage_place,
               bi.informant_name, bi.informant_relationship, bi.informant_address
        FROM birth_records br 
        LEFT JOIN mothers m ON br.mother_id = m.id 
        LEFT JOIN husband_partners hp ON m.id = hp.mother_id
        LEFT JOIN birth_informants bi ON br.id = bi.birth_record_id
        WHERE br.id = ?
    ");
    $stmt->execute([$birthId]);
    $birthRecord = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$birthRecord) {
        $error = "Birth record not found.";
        $isEditMode = false;
    }
}

// Load mothers based on role
if ($_SESSION['role'] === 'mother') {
    $motherId = $_SESSION['user_id'];
    $stmt = $pdo->prepare("
        SELECT m.id, m.first_name, m.last_name 
        FROM mothers m 
        WHERE m.user_id = ?
    ");
    $stmt->execute([$motherId]);
    $mothers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($mothers)) {
        header("Location: mother_self_registration.php");
        exit();
    }
} else {
    $mothers = $pdo->query("
        SELECT m.id, m.first_name, m.last_name 
        FROM mothers m 
        ORDER BY m.last_name, m.first_name
    ")->fetchAll(PDO::FETCH_ASSOC);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $motherId = $_POST['mother_id'] ?? '';
    
    // Baby's Information
    $firstName = trim($_POST['first_name'] ?? '');
    $middleName = trim($_POST['middle_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $birthDate = $_POST['birth_date'] ?? '';
    $birthTime = $_POST['birth_time'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $birthWeight = $_POST['birth_weight'] ?? '';
    $birthLength = $_POST['birth_length'] ?? '';
    $birthOrder = $_POST['birth_order'] ?? '';
    $typeOfBirth = $_POST['type_of_birth'] ?? '';
    
    // Birth Details
    $deliveryType = $_POST['delivery_type'] ?? '';
    $birthAttendant = $_POST['birth_attendant'] ?? '';
    $birthAttendantTitle = $_POST['birth_attendant_title'] ?? '';
    $birthPlace = $_POST['birth_place'] ?? '';
    $birthPlaceType = $_POST['birth_place_type'] ?? '';
    $birthAddress = $_POST['birth_address'] ?? '';
    $birthCity = $_POST['birth_city'] ?? '';
    $birthProvince = $_POST['birth_province'] ?? '';
    
    // Informant Information
    $informantName = trim($_POST['informant_name'] ?? '');
    $informantRelationship = $_POST['informant_relationship'] ?? '';
    $informantAddress = trim($_POST['informant_address'] ?? '');
    
    $userId = $_SESSION['user_id'];

    if (!$motherId || !$firstName || !$lastName || !$birthDate || !$birthTime || !$gender) {
        $error = "Please fill in all required fields marked with *.";
    } else {
        try {
            $pdo->beginTransaction();

            if ($isEditMode && isset($_POST['birth_id'])) {
                // UPDATE existing birth record
                $birthId = $_POST['birth_id'];
                
                // Update birth record
                $sql = "UPDATE birth_records SET 
                        first_name = ?, middle_name = ?, last_name = ?, 
                        birth_date = ?, birth_time = ?, gender = ?, 
                        birth_weight = ?, birth_length = ?, birth_order = ?, 
                        type_of_birth = ?, delivery_type = ?, birth_attendant = ?,
                        birth_attendant_title = ?, birth_place = ?, birth_place_type = ?, 
                        birth_address = ?, birth_city = ?, birth_province = ?,
                        updated_at = NOW()
                        WHERE id = ?";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $firstName, $middleName, $lastName,
                    $birthDate, $birthTime, $gender,
                    $birthWeight, $birthLength, $birthOrder,
                    $typeOfBirth, $deliveryType, $birthAttendant,
                    $birthAttendantTitle, $birthPlace, $birthPlaceType,
                    $birthAddress, $birthCity, $birthProvince,
                    $birthId
                ]);

                // Update or insert informant
                if ($informantName) {
                    $checkInformant = $pdo->prepare("SELECT id FROM birth_informants WHERE birth_record_id = ?");
                    $checkInformant->execute([$birthId]);
                    
                    if ($checkInformant->fetch()) {
                        // Update existing informant
                        $updateInformant = $pdo->prepare("
                            UPDATE birth_informants SET 
                            informant_name = ?, informant_relationship = ?, informant_address = ?
                            WHERE birth_record_id = ?
                        ");
                        $updateInformant->execute([$informantName, $informantRelationship, $informantAddress, $birthId]);
                    } else {
                        // Insert new informant
                        $insertInformant = $pdo->prepare("
                            INSERT INTO birth_informants (birth_record_id, informant_name, informant_relationship, informant_address)
                            VALUES (?, ?, ?, ?)
                        ");
                        $insertInformant->execute([$birthId, $informantName, $informantRelationship, $informantAddress]);
                    }
                }

                $pdo->commit();
                // ✅ FIXED: Set success message (NO REDIRECT)
                $message = "Birth updated successfully! Baby " . htmlspecialchars($firstName) . " " . htmlspecialchars($lastName) . " has been updated.";
                
                // Clear form data
                unset($_POST);
                
                // If staff (not mother), preserve mother selection
                if ($_SESSION['role'] !== 'mother' && !empty($motherId)) {
                    $_SESSION['last_mother_id'] = $motherId;
                }

            } else {
                                // INSERT new birth record
                $sql = "INSERT INTO birth_records 
                        (mother_id, first_name, middle_name, last_name, birth_date, birth_time, gender, 
                         birth_weight, birth_length, birth_order, type_of_birth, delivery_type, birth_attendant,
                         birth_attendant_title, birth_place, birth_place_type, birth_address, birth_city, birth_province,
                         status, registered_by) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $motherId, $firstName, $middleName, $lastName, $birthDate, $birthTime, $gender,
                    $birthWeight, $birthLength, $birthOrder, $typeOfBirth, $deliveryType, $birthAttendant,
                    $birthAttendantTitle, $birthPlace, $birthPlaceType, $birthAddress, $birthCity, $birthProvince,
                    $userId
                ]);

                $birthRecordId = $pdo->lastInsertId();

                // Insert informant if provided
                if ($informantName) {
                    $insertInformant = $pdo->prepare("
                        INSERT INTO birth_informants (birth_record_id, informant_name, informant_relationship, informant_address)
                        VALUES (?, ?, ?, ?)
                    ");
                    $insertInformant->execute([$birthRecordId, $informantName, $informantRelationship, $informantAddress]);
                }

                $pdo->commit();
                // ✅ FIXED: Set success message (NO REDIRECT)
                $message = "Birth registered successfully! Baby " . htmlspecialchars($firstName) . " " . htmlspecialchars($lastName) . " has been registered.";
                
                // Clear form data
                unset($_POST);
                
                // If staff (not mother), preserve mother selection
                if ($_SESSION['role'] !== 'mother' && !empty($motherId)) {
                    $_SESSION['last_mother_id'] = $motherId;
                }
                
                // ✅ FIXED: NO REDIRECT - stays on same page
                // Don't use: header("Location: dashboard.php");
                // Don't use: exit();
            } // ← IMPORTANT: Closing brace for the else block

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Failed to " . ($isEditMode ? "update" : "register") . " birth record. Please try again.";
            error_log("Database Error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isEditMode ? 'Edit Birth Record' : 'Birth Registration'; ?> - Health Station System </title>
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
        .form-control, .form-select {
            border-radius: 6px;
            border: 1.5px solid #e0e0e0;
        }
        .form-control:focus, .form-select:focus {
            border-color: #1a73e8;
            box-shadow: 0 0 0 2px rgba(26, 115, 232, 0.15);
        }
        .required-field::after {
            content: " *";
            color: #dc3545;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
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
        .edit-mode-banner {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            color: white;
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 600;
        }
        .readonly-field {
            background-color: #f8f9fa;
            border-color: #e9ecef;
            color: #6c757d;
            cursor: not-allowed;
        }
        .lock-icon {
            color: #6c757d;
            margin-right: 5px;
        }
        .edit-mode-badge {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .info-section {
            background-color: #e7f3ff;
            border: 1px solid #b6d7ff;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
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
                    <h1 class="h2"><?php echo $isEditMode ? 'Edit Birth Record' : 'Birth Registration'; ?></h1>
                    <?php if ($isEditMode && $birthRecord): ?>
                        <span class="edit-mode-badge">
                            <i class="fas fa-edit me-1"></i>Edit Mode
                        </span>
                    <?php endif; ?>
                </div>

                <?php if ($isEditMode): ?>
                    <div class="edit-mode-banner">
                        <i class="fas fa-edit me-2"></i>
                        You are editing birth record for: <strong><?php echo htmlspecialchars($birthRecord['first_name'] . ' ' . $birthRecord['last_name']); ?></strong>
                        | Birth Date: <?php echo date('F j, Y', strtotime($birthRecord['birth_date'])); ?>
                    </div>
                <?php endif; ?>

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
                    Fields marked with <span class="text-danger">*</span> are required.
                </div>

                <div class="card">
                    <div class="card-body">
                        <form method="POST" action="" id="birthRegistrationForm" novalidate>
                            <?php if ($isEditMode && $birthRecord): ?>
                                <input type="hidden" name="birth_id" value="<?php echo $birthRecord['id']; ?>">
                            <?php endif; ?>

                            <!-- Mother Selection -->
                            <?php if ($_SESSION['role'] === 'mother' && count($mothers) > 0): ?>
                                <input type="hidden" name="mother_id" value="<?php echo $mothers[0]['id']; ?>">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> You are <?php echo $isEditMode ? 'editing' : 'registering'; ?> a birth for yourself: 
                                    <strong><?php echo htmlspecialchars($mothers[0]['first_name'] . ' ' . $mothers[0]['last_name']); ?></strong>
                                </div>
                            <?php else: ?>
                                <div class="section-title">Mother Selection</div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="mother_id" class="form-label required-field">Select Mother</label>
                                        <?php if ($isEditMode && $birthRecord): ?>
                                            <!-- In edit mode: READ-ONLY -->
                                            <input type="text" class="form-control readonly-field" 
                                                   value="<?php echo htmlspecialchars($birthRecord['mother_first_name'] . ' ' . $birthRecord['mother_last_name']); ?>" 
                                                   readonly>
                                            <input type="hidden" name="mother_id" value="<?php echo $birthRecord['mother_id']; ?>">
                                            <small class="text-muted"><i class="fas fa-lock lock-icon"></i>Mother cannot be changed in edit mode</small>
                                        <?php else: ?>
                                            <!-- In new record mode, show dropdown -->
                                            <select class="form-select" id="mother_id" name="mother_id" required>
                                                <option value="">Select Mother</option>
                                                <?php foreach ($mothers as $mother): ?>
                                                <option value="<?php echo $mother['id']; ?>" 
                                                    <?php echo (isset($_GET['mother_id']) && $_GET['mother_id'] == $mother['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($mother['first_name'] . ' ' . $mother['last_name']); ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="field-warning" id="mother_id_warning">
                                                <i class="fas fa-exclamation-triangle warning-icon"></i>
                                                Please select a mother
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Mother's Information (Display Only) -->
                            <div class="section-title">Mother's Information</div>
                            <div class="info-section">
                                <div class="row">
                                    <div class="col-md-4">
                                        <strong>Name:</strong> 
                                        <?php if ($isEditMode && $birthRecord): ?>
                                            <?php echo htmlspecialchars($birthRecord['mother_first_name'] . ' ' . ($birthRecord['mother_middle_name'] ? $birthRecord['mother_middle_name'] . ' ' : '') . $birthRecord['mother_last_name']); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Will be automatically retrieved</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-4">
                                        <strong>Birthdate:</strong> 
                                        <?php if ($isEditMode && $birthRecord && $birthRecord['mother_birthdate']): ?>
                                            <?php echo date('F j, Y', strtotime($birthRecord['mother_birthdate'])); ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-4">
                                        <strong>Occupation:</strong> 
                                        <?php if ($isEditMode && $birthRecord && $birthRecord['mother_occupation']): ?>
                                            <?php echo htmlspecialchars($birthRecord['mother_occupation']); ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-md-6">
                                        <strong>Citizenship:</strong> 
                                        <?php if ($isEditMode && $birthRecord && $birthRecord['mother_citizenship']): ?>
                                            <?php echo htmlspecialchars($birthRecord['mother_citizenship']); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Filipino</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Religion:</strong> 
                                        <?php if ($isEditMode && $birthRecord && $birthRecord['mother_religion']): ?>
                                            <?php echo htmlspecialchars($birthRecord['mother_religion']); ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> 
                                Mother's information is automatically retrieved from the mother's profile and cannot be edited here.
                            </div>

                            <!-- Father's Information (Display Only) -->
                            <div class="section-title">Father's Information</div>
                            <div class="info-section">
                                <div class="row">
                                    <div class="col-md-4">
                                        <strong>Name:</strong> 
                                        <?php if ($isEditMode && $birthRecord && $birthRecord['father_first_name']): ?>
                                            <?php echo htmlspecialchars($birthRecord['father_first_name'] . ' ' . ($birthRecord['father_middle_name'] ? $birthRecord['father_middle_name'] . ' ' : '') . $birthRecord['father_last_name']); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Will be automatically retrieved</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-4">
                                        <strong>Birthdate:</strong> 
                                        <?php if ($isEditMode && $birthRecord && $birthRecord['father_birthdate']): ?>
                                            <?php echo date('F j, Y', strtotime($birthRecord['father_birthdate'])); ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-4">
                                        <strong>Occupation:</strong> 
                                        <?php if ($isEditMode && $birthRecord && $birthRecord['father_occupation']): ?>
                                            <?php echo htmlspecialchars($birthRecord['father_occupation']); ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if ($isEditMode && $birthRecord && $birthRecord['parents_marriage_date']): ?>
                                <div class="row mt-2">
                                    <div class="col-md-6">
                                        <strong>Marriage Date:</strong> 
                                        <?php echo date('F j, Y', strtotime($birthRecord['parents_marriage_date'])); ?>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Marriage Place:</strong> 
                                        <?php echo htmlspecialchars($birthRecord['parents_marriage_place'] ?? '-'); ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> 
                                Father's information is automatically retrieved from the husband/partner profile and cannot be edited here.
                            </div>

                            <!-- Baby's Information -->
                            <div class="section-title">Baby's Information</div>
                            <div class="row">
                                <div class="col-md-4 form-group">
                                    <label for="first_name" class="form-label required-field">First Name</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                           value="<?= htmlspecialchars($birthRecord['first_name'] ?? $_POST['first_name'] ?? ''); ?>" required>
                                    <div class="field-warning" id="first_name_warning">
                                        <i class="fas fa-exclamation-triangle warning-icon"></i>
                                        Please enter baby's first name
                                    </div>
                                </div>
                                <div class="col-md-4 form-group">
                                    <label for="middle_name" class="form-label">Middle Name</label>
                                    <input type="text" class="form-control" id="middle_name" name="middle_name"
                                           value="<?= htmlspecialchars($birthRecord['middle_name'] ?? $_POST['middle_name'] ?? ''); ?>" placeholder="Optional">
                                </div>
                                <div class="col-md-4 form-group">
                                    <label for="last_name" class="form-label required-field">Last Name</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name"
                                           value="<?= htmlspecialchars($birthRecord['last_name'] ?? $_POST['last_name'] ?? ''); ?>" required>
                                    <div class="field-warning" id="last_name_warning">
                                        <i class="fas fa-exclamation-triangle warning-icon"></i>
                                        Please enter baby's last name
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-3 form-group">
                                    <label for="birth_date" class="form-label required-field">Birth Date</label>
                                    <input type="date" class="form-control" id="birth_date" name="birth_date" 
                                           value="<?= htmlspecialchars($birthRecord['birth_date'] ?? $_POST['birth_date'] ?? ''); ?>" required max="<?php echo date('Y-m-d'); ?>">
                                    <div class="field-warning" id="birth_date_warning">
                                        <i class="fas fa-exclamation-triangle warning-icon"></i>
                                        Please select birth date
                                    </div>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label for="birth_time" class="form-label required-field">Birth Time</label>
                                    <input type="time" class="form-control" id="birth_time" name="birth_time" 
                                           value="<?= htmlspecialchars($birthRecord['birth_time'] ?? $_POST['birth_time'] ?? ''); ?>" required>
                                    <div class="field-warning" id="birth_time_warning">
                                        <i class="fas fa-exclamation-triangle warning-icon"></i>
                                        Please select birth time
                                    </div>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label for="gender" class="form-label required-field">Gender</label>
                                    <select class="form-select" id="gender" name="gender" required>
                                        <option value="">Select Gender</option>
                                        <option value="Male" <?= (($birthRecord['gender'] ?? $_POST['gender'] ?? '') == 'Male') ? 'selected' : ''; ?>>Male</option>
                                        <option value="Female" <?= (($birthRecord['gender'] ?? $_POST['gender'] ?? '') == 'Female') ? 'selected' : ''; ?>>Female</option>
                                    </select>
                                    <div class="field-warning" id="gender_warning">
                                        <i class="fas fa-exclamation-triangle warning-icon"></i>
                                        Please select baby's gender
                                    </div>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label for="birth_order" class="form-label">Birth Order</label>
                                    <select class="form-select" id="birth_order" name="birth_order">
                                        <option value="">Select Order</option>
                                        <?php 
                                        $birthOrders = ['1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th', '9th', '10th'];
                                        foreach ($birthOrders as $order): 
                                        ?>
                                            <option value="<?php echo $order; ?>"
                                                <?= (($birthRecord['birth_order'] ?? $_POST['birth_order'] ?? '') == $order) ? 'selected' : '' ?>>
                                                <?php echo $order; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-3 form-group">
                                    <label for="birth_weight" class="form-label">Birth Weight (kg)</label>
                                    <input type="number" step="0.01" class="form-control" id="birth_weight" name="birth_weight" 
                                           min="0.5" max="6.0" placeholder="e.g., 3.2" value="<?= htmlspecialchars($birthRecord['birth_weight'] ?? $_POST['birth_weight'] ?? ''); ?>">
                                </div>
                                <div class="col-md-3 form-group">
                                    <label for="birth_length" class="form-label">Birth Length (cm)</label>
                                    <input type="number" step="0.1" class="form-control" id="birth_length" name="birth_length" 
                                           min="30" max="60" placeholder="e.g., 50.5" value="<?= htmlspecialchars($birthRecord['birth_length'] ?? $_POST['birth_length'] ?? ''); ?>">
                                </div>
                                <div class="col-md-3 form-group">
                                    <label for="type_of_birth" class="form-label">Type of Birth</label>
                                    <select class="form-select" id="type_of_birth" name="type_of_birth">
                                        <option value="">Select Type</option>
                                        <option value="Single" <?= (($birthRecord['type_of_birth'] ?? $_POST['type_of_birth'] ?? '') == 'Single') ? 'selected' : ''; ?>>Single</option>
                                        <option value="Twin" <?= (($birthRecord['type_of_birth'] ?? $_POST['type_of_birth'] ?? '') == 'Twin') ? 'selected' : ''; ?>>Twin</option>
                                        <option value="Triplet" <?= (($birthRecord['type_of_birth'] ?? $_POST['type_of_birth'] ?? '') == 'Triplet') ? 'selected' : ''; ?>>Triplet</option>
                                        <option value="Multiple" <?= (($birthRecord['type_of_birth'] ?? $_POST['type_of_birth'] ?? '') == 'Multiple') ? 'selected' : ''; ?>>Multiple</option>
                                    </select>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label for="delivery_type" class="form-label">Delivery Type</label>
                                    <select class="form-select" id="delivery_type" name="delivery_type">
                                        <option value="">Select Type</option>
                                        <option value="Normal Spontaneous Delivery" <?= (($birthRecord['delivery_type'] ?? $_POST['delivery_type'] ?? '') == 'Normal Spontaneous Delivery') ? 'selected' : ''; ?>>Normal Spontaneous Delivery</option>
                                        <option value="Cesarean Section" <?= (($birthRecord['delivery_type'] ?? $_POST['delivery_type'] ?? '') == 'Cesarean Section') ? 'selected' : ''; ?>>Cesarean Section</option>
                                        <option value="Forceps Delivery" <?= (($birthRecord['delivery_type'] ?? $_POST['delivery_type'] ?? '') == 'Forceps Delivery') ? 'selected' : ''; ?>>Forceps Delivery</option>
                                        <option value="Vacuum Extraction" <?= (($birthRecord['delivery_type'] ?? $_POST['delivery_type'] ?? '') == 'Vacuum Extraction') ? 'selected' : ''; ?>>Vacuum Extraction</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Birth Details -->
                            <div class="section-title">Birth Details</div>
                            <div class="row">
                                <div class="col-md-4 form-group">
                                    <label for="birth_attendant" class="form-label">Birth Attendant Name</label>
                                    <input type="text" class="form-control" id="birth_attendant" name="birth_attendant" 
                                           placeholder="e.g., Dr. Juan Dela Cruz" value="<?= htmlspecialchars($birthRecord['birth_attendant'] ?? $_POST['birth_attendant'] ?? ''); ?>">
                                </div>
                                <div class="col-md-4 form-group">
                                    <label for="birth_attendant_title" class="form-label">Attendant Title</label>
                                    <select class="form-select" id="birth_attendant_title" name="birth_attendant_title">
                                        <option value="">Select Title</option>
                                        <option value="Doctor" <?= (($birthRecord['birth_attendant_title'] ?? $_POST['birth_attendant_title'] ?? '') == 'Doctor') ? 'selected' : ''; ?>>Doctor</option>
                                        <option value="Midwife" <?= (($birthRecord['birth_attendant_title'] ?? $_POST['birth_attendant_title'] ?? '') == 'Midwife') ? 'selected' : ''; ?>>Midwife</option>
                                        <option value="Nurse" <?= (($birthRecord['birth_attendant_title'] ?? $_POST['birth_attendant_title'] ?? '') == 'Nurse') ? 'selected' : ''; ?>>Nurse</option>
                                        <option value="Traditional Birth Attendant" <?= (($birthRecord['birth_attendant_title'] ?? $_POST['birth_attendant_title'] ?? '') == 'Traditional Birth Attendant') ? 'selected' : ''; ?>>Traditional Birth Attendant</option>
                                    </select>
                                </div>
                                <div class="col-md-4 form-group">
                                    <label for="birth_place_type" class="form-label">Place of Birth Type</label>
                                    <select class="form-select" id="birth_place_type" name="birth_place_type">
                                        <option value="">Select Type</option>
                                        <option value="Hospital" <?= (($birthRecord['birth_place_type'] ?? $_POST['birth_place_type'] ?? '') == 'Hospital') ? 'selected' : ''; ?>>Hospital</option>
                                        <option value="Clinic" <?= (($birthRecord['birth_place_type'] ?? $_POST['birth_place_type'] ?? '') == 'Clinic') ? 'selected' : ''; ?>>Clinic</option>
                                        <option value="Home" <?= (($birthRecord['birth_place_type'] ?? $_POST['birth_place_type'] ?? '') == 'Home') ? 'selected' : ''; ?>>Home</option>
                                        <option value="Birthing Center" <?= (($birthRecord['birth_place_type'] ?? $_POST['birth_place_type'] ?? '') == 'Birthing Center') ? 'selected' : ''; ?>>Birthing Center</option>
                                        <option value="Other" <?= (($birthRecord['birth_place_type'] ?? $_POST['birth_place_type'] ?? '') == 'Other') ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 form-group">
                                    <label for="birth_place" class="form-label">Name of Birth Place</label>
                                    <input type="text" class="form-control" id="birth_place" name="birth_place" 
                                           placeholder="e.g., Kibenes General Hospital" value="<?= htmlspecialchars($birthRecord['birth_place'] ?? $_POST['birth_place'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6 form-group">
                                    <label for="birth_address" class="form-label">Birth Address</label>
                                    <input type="text" class="form-control" id="birth_address" name="birth_address" 
                                           placeholder="Street Address" value="<?= htmlspecialchars($birthRecord['birth_address'] ?? $_POST['birth_address'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 form-group">
                                    <label for="birth_city" class="form-label">City/Municipality</label>
                                    <input type="text" class="form-control" id="birth_city" name="birth_city" 
                                           placeholder="City/Municipality" value="<?= htmlspecialchars($birthRecord['birth_city'] ?? $_POST['birth_city'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6 form-group">
                                    <label for="birth_province" class="form-label">Province</label>
                                    <input type="text" class="form-control" id="birth_province" name="birth_province" 
                                           placeholder="Province" value="<?= htmlspecialchars($birthRecord['birth_province'] ?? $_POST['birth_province'] ?? ''); ?>">
                                </div>
                            </div>

                            <!-- Informant Information -->
                            <div class="section-title">Informant Information</div>
                            <div class="row">
                                <div class="col-md-4 form-group">
                                    <label for="informant_name" class="form-label">Informant Name</label>
                                    <input type="text" class="form-control" id="informant_name" name="informant_name" 
                                           placeholder="Full Name" value="<?= htmlspecialchars($birthRecord['informant_name'] ?? $_POST['informant_name'] ?? ''); ?>">
                                </div>
                                <div class="col-md-4 form-group">
                                    <label for="informant_relationship" class="form-label">Relationship to Child</label>
                                    <select class="form-select" id="informant_relationship" name="informant_relationship">
                                        <option value="">Select Relationship</option>
                                        <option value="Father" <?= (($birthRecord['informant_relationship'] ?? $_POST['informant_relationship'] ?? '') == 'Father') ? 'selected' : ''; ?>>Father</option>
                                        <option value="Mother" <?= (($birthRecord['informant_relationship'] ?? $_POST['informant_relationship'] ?? '') == 'Mother') ? 'selected' : ''; ?>>Mother</option>
                                        <option value="Grandparent" <?= (($birthRecord['informant_relationship'] ?? $_POST['informant_relationship'] ?? '') == 'Grandparent') ? 'selected' : ''; ?>>Grandparent</option>
                                        <option value="Aunt/Uncle" <?= (($birthRecord['informant_relationship'] ?? $_POST['informant_relationship'] ?? '') == 'Aunt/Uncle') ? 'selected' : ''; ?>>Aunt/Uncle</option>
                                        <option value="Other Relative" <?= (($birthRecord['informant_relationship'] ?? $_POST['informant_relationship'] ?? '') == 'Other Relative') ? 'selected' : ''; ?>>Other Relative</option>
                                        <option value="Hospital Staff" <?= (($birthRecord['informant_relationship'] ?? $_POST['informant_relationship'] ?? '') == 'Hospital Staff') ? 'selected' : ''; ?>>Hospital Staff</option>
                                    </select>
                                </div>
                                <div class="col-md-4 form-group">
                                    <label for="informant_address" class="form-label">Informant Address</label>
                                    <input type="text" class="form-control" id="informant_address" name="informant_address" 
                                           placeholder="Complete Address" value="<?= htmlspecialchars($birthRecord['informant_address'] ?? $_POST['informant_address'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                                <button type="button" onclick="history.back()" class="btn btn-secondary me-md-2">Cancel</button>
                                <button type="submit" class="btn btn-primary">
                                    <?php echo $isEditMode ? 'Update Birth Record' : 'Register Birth'; ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('birth_date').max = today;

            // Setup validation
            setupFieldValidation();
        });

        function setupFieldValidation() {
            const form = document.getElementById('birthRegistrationForm');
            const requiredFields = [
                'first_name', 'last_name', 'birth_date', 'birth_time', 'gender'
            ];

            // Add mother_id to required fields if it exists
            const motherSelect = document.getElementById('mother_id');
            if (motherSelect) {
                requiredFields.push('mother_id');
            }

            // Add event listeners to all required fields
            requiredFields.forEach(field => {
                const input = document.getElementById(field);
                if (input) {
                    input.addEventListener('blur', validateRequiredField);
                    input.addEventListener('input', hideWarning);
                }
            });

            // Form submission validation
            form.addEventListener('submit', function(e) {
                let isValid = true;

                // Validate all required fields
                requiredFields.forEach(field => {
                    if (!validateRequiredField({ target: document.getElementById(field) })) {
                        isValid = false;
                    }
                });

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

        function getFieldWarningMessage(fieldId) {
            const messages = {
                'first_name': 'Please enter baby\'s first name',
                'last_name': 'Please enter baby\'s last name',
                'birth_date': 'Please select birth date',
                'birth_time': 'Please select birth time',
                'gender': 'Please select baby\'s gender',
                'mother_id': 'Please select a mother'
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