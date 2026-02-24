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
    <style type="text/tailwindcss">
        @layer components {
            .section-header {
                @apply flex items-center gap-3 py-4 border-b border-slate-100 mb-6;
            }
            .section-icon {
                @apply w-10 h-10 bg-health-50 text-health-600 rounded-xl flex items-center justify-center text-lg;
            }
            .form-input-premium {
                @apply w-full px-4 py-3 rounded-2xl border border-slate-200 focus:border-health-500 focus:ring-4 focus:ring-health-500/10 outline-none transition-all duration-200 bg-white;
            }
            .form-label-premium {
                @apply block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2 ml-1;
            }
            .card-premium {
                @apply bg-white rounded-[2rem] border border-slate-100 shadow-sm shadow-slate-200/50 p-8;
            }
            .info-box {
                @apply bg-slate-50 rounded-2xl p-6 border border-slate-100;
            }
            .badge-edit {
                @apply bg-amber-500 text-white px-4 py-1.5 rounded-full text-xs font-black uppercase tracking-widest shadow-lg shadow-amber-200;
            }
        }
    </style>
</head>
<body>
    <?php include_once INCLUDE_PATH . 'header.php'; ?>

    <div class="flex flex-col lg:flex-row min-h-[calc(100vh-4rem)]">
        <?php include_once INCLUDE_PATH . 'sidebar.php'; ?>

        <main class="flex-1 p-4 lg:p-8 space-y-8 no-print">
            <!-- Header Section -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 bg-white p-8 rounded-[2rem] border border-slate-100 shadow-sm">
                <div>
                    <h1 class="text-3xl font-black text-slate-900 tracking-tight flex items-center gap-3">
                        <i class="fas fa-baby text-health-600"></i>
                        <?php echo $isEditMode ? 'Edit Birth Record' : 'Birth Registration'; ?>
                    </h1>
                    <p class="text-slate-500 font-medium mt-1">
                        Professional health record management system
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <?php if ($isEditMode && $birthRecord): ?>
                        <span class="badge-edit">
                            <i class="fas fa-edit me-1"></i>Edit Mode Active
                        </span>
                    <?php endif; ?>
                    <div class="px-4 py-2 bg-slate-50 rounded-xl border border-slate-100 text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                        Ref: WBMS-BR-<?= date('Y') ?>
                    </div>
                </div>
            </div>

            <?php if ($isEditMode): ?>
                <div class="bg-amber-50 border border-amber-100 rounded-2xl p-4 flex items-center gap-4 text-amber-800">
                    <div class="w-10 h-10 bg-amber-100 rounded-xl flex items-center justify-center text-lg">
                        <i class="fas fa-user-pen"></i>
                    </div>
                    <div>
                        <p class="text-xs uppercase font-black tracking-widest mb-0.5">Currently Editing</p>
                        <p class="font-bold"><?php echo htmlspecialchars($birthRecord['first_name'] . ' ' . $birthRecord['last_name']); ?> (Born: <?php echo date('F j, Y', strtotime($birthRecord['birth_date'])); ?>)</p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Success/Error Alerts -->
            <?php if ($message): ?>
                <div class="bg-emerald-50 border border-emerald-100 rounded-2xl p-4 flex items-center gap-3 text-emerald-800 animate-in fade-in slide-in-from-top duration-500">
                    <div class="w-8 h-8 bg-emerald-100 rounded-lg flex items-center justify-center text-sm">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <p class="font-bold text-sm"><?php echo $message; ?></p>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="bg-rose-50 border border-rose-100 rounded-2xl p-4 flex items-center gap-3 text-rose-800 animate-in fade-in slide-in-from-top duration-500">
                    <div class="w-8 h-8 bg-rose-100 rounded-lg flex items-center justify-center text-sm">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <p class="font-bold text-sm"><?php echo $error; ?></p>
                </div>
            <?php endif; ?>

            <div class="bg-sky-50 border border-sky-100 rounded-2xl p-4 flex items-center gap-3 text-sky-800">
                <div class="w-8 h-8 bg-sky-100 rounded-lg flex items-center justify-center text-sm">
                    <i class="fas fa-info-circle"></i>
                </div>
                <p class="text-sm font-medium">Fields marked with <span class="text-rose-500 font-bold">*</span> are required for official registration.</p>
            </div>

            <form method="POST" action="" id="birthRegistrationForm" class="space-y-8" novalidate>
                <?php if ($isEditMode && $birthRecord): ?>
                    <input type="hidden" name="birth_id" value="<?php echo $birthRecord['id']; ?>">
                <?php endif; ?>

                <!-- Mother Selection -->
                <section class="card-premium">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-venus text-health-600"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-slate-800">Mother Selection</h2>
                            <p class="text-xs text-slate-400 font-bold uppercase tracking-widest mt-0.5">Assign this record to a registered mother</p>
                        </div>
                    </div>

                    <?php if ($_SESSION['role'] === 'mother' && count($mothers) > 0): ?>
                        <input type="hidden" name="mother_id" value="<?php echo $mothers[0]['id']; ?>">
                        <div class="info-box flex items-center gap-4">
                            <div class="w-12 h-12 bg-health-100 text-health-700 rounded-full flex items-center justify-center text-xl">
                                <i class="fas fa-user-check"></i>
                            </div>
                            <div>
                                <p class="text-sm text-slate-500 font-medium">Registering a birth for yourself:</p>
                                <p class="text-lg font-black text-slate-900"><?php echo htmlspecialchars($mothers[0]['first_name'] . ' ' . $mothers[0]['last_name']); ?></p>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="mother_id" class="form-label-premium">Select Mother <span class="text-rose-500">*</span></label>
                                <?php if ($isEditMode && $birthRecord): ?>
                                    <div class="relative">
                                        <input type="text" class="form-input-premium bg-slate-50 text-slate-500 cursor-not-allowed pr-10" 
                                               value="<?php echo htmlspecialchars($birthRecord['mother_first_name'] . ' ' . $birthRecord['mother_last_name']); ?>" 
                                               readonly>
                                        <div class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400">
                                            <i class="fas fa-lock"></i>
                                        </div>
                                    </div>
                                    <input type="hidden" name="mother_id" value="<?php echo $birthRecord['mother_id']; ?>">
                                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-2 px-1">
                                        <i class="fas fa-info-circle me-1"></i> Mother cannot be changed in edit mode
                                    </p>
                                <?php else: ?>
                                    <select class="form-input-premium appearance-none" id="mother_id" name="mother_id" required>
                                        <option value="">Select Mother</option>
                                        <?php foreach ($mothers as $mother): ?>
                                        <option value="<?php echo $mother['id']; ?>" 
                                            <?php echo (isset($_GET['mother_id']) && $_GET['mother_id'] == $mother['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($mother['first_name'] . ' ' . $mother['last_name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="hidden mt-2 p-2 bg-rose-50 text-rose-600 text-xs font-bold rounded-lg border border-rose-100" id="mother_id_warning">
                                        <i class="fas fa-exclamation-triangle me-1"></i> Please select a mother
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </section>

                <!-- Mother's Information Display -->
                <section class="card-premium">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-address-card text-health-600"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-slate-800">Mother's Profile Data</h2>
                            <p class="text-xs text-slate-400 font-bold uppercase tracking-widest mt-0.5">Automatically synced with registration</p>
                        </div>
                    </div>

                    <div class="info-box">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
                            <div class="space-y-1">
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Full Name</p>
                                <p class="text-sm font-bold text-slate-800">
                                    <?php if ($isEditMode && $birthRecord): ?>
                                        <?php echo htmlspecialchars($birthRecord['mother_first_name'] . ' ' . ($birthRecord['mother_middle_name'] ? $birthRecord['mother_middle_name'] . ' ' : '') . $birthRecord['mother_last_name']); ?>
                                    <?php else: ?>
                                        <span class="text-slate-300 italic">Select mother to preview</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="space-y-1">
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Date of Birth</p>
                                <p class="text-sm font-bold text-slate-800">
                                    <?php if ($isEditMode && $birthRecord && $birthRecord['mother_birthdate']): ?>
                                        <?php echo date('M j, Y', strtotime($birthRecord['mother_birthdate'])); ?>
                                    <?php else: ?>
                                        <span class="text-slate-300">-</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="space-y-1">
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Occupation</p>
                                <p class="text-sm font-bold text-slate-800">
                                    <?php if ($isEditMode && $birthRecord && $birthRecord['mother_occupation']): ?>
                                        <?php echo htmlspecialchars($birthRecord['mother_occupation']); ?>
                                    <?php else: ?>
                                        <span class="text-slate-300">-</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="space-y-1">
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Citizenship</p>
                                <p class="text-sm font-bold text-slate-800">
                                    <?php if ($isEditMode && $birthRecord && $birthRecord['mother_citizenship']): ?>
                                        <?php echo htmlspecialchars($birthRecord['mother_citizenship']); ?>
                                    <?php else: ?>
                                        <span class="text-slate-300">Filipino</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="space-y-1">
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Religion</p>
                                <p class="text-sm font-bold text-slate-800">
                                    <?php if ($isEditMode && $birthRecord && $birthRecord['mother_religion']): ?>
                                        <?php echo htmlspecialchars($birthRecord['mother_religion']); ?>
                                    <?php else: ?>
                                        <span class="text-slate-300">-</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </section>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> 
                                Mother's information is automatically retrieved from the mother's profile and cannot be edited here.
                            </div>

                <!-- Father's Information Display -->
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

                    <div class="info-box">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
                            <div class="space-y-1">
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Full Name</p>
                                <p class="text-sm font-bold text-slate-800">
                                    <?php if ($isEditMode && $birthRecord && $birthRecord['father_first_name']): ?>
                                        <?php echo htmlspecialchars($birthRecord['father_first_name'] . ' ' . ($birthRecord['father_middle_name'] ? $birthRecord['father_middle_name'] . ' ' : '') . $birthRecord['father_last_name']); ?>
                                    <?php else: ?>
                                        <span class="text-slate-300 italic">Select mother to preview</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="space-y-1">
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Date of Birth</p>
                                <p class="text-sm font-bold text-slate-800">
                                    <?php if ($isEditMode && $birthRecord && $birthRecord['father_birthdate']): ?>
                                        <?php echo date('M j, Y', strtotime($birthRecord['father_birthdate'])); ?>
                                    <?php else: ?>
                                        <span class="text-slate-300">-</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="space-y-1">
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Occupation</p>
                                <p class="text-sm font-bold text-slate-800">
                                    <?php if ($isEditMode && $birthRecord && $birthRecord['father_occupation']): ?>
                                        <?php echo htmlspecialchars($birthRecord['father_occupation']); ?>
                                    <?php else: ?>
                                        <span class="text-slate-300">-</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>

                        <?php if ($isEditMode && $birthRecord && $birthRecord['parents_marriage_date']): ?>
                            <div class="mt-6 pt-6 border-t border-slate-200 grid grid-cols-1 sm:grid-cols-2 gap-8">
                                <div class="space-y-1">
                                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Marriage Date</p>
                                    <p class="text-sm font-bold text-slate-800"><?php echo date('M j, Y', strtotime($birthRecord['parents_marriage_date'])); ?></p>
                                </div>
                                <div class="space-y-1">
                                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Marriage Place</p>
                                    <p class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($birthRecord['parents_marriage_place'] ?? '-'); ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- Baby's Information -->
                <section class="card-premium">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-child text-health-600"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-slate-800">Child's Primary Details</h2>
                            <p class="text-xs text-slate-400 font-bold uppercase tracking-widest mt-0.5">Please provide accurate information for the birth certificate</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                        <div>
                            <label for="first_name" class="form-label-premium">First Name <span class="text-rose-500">*</span></label>
                            <input type="text" class="form-input-premium" id="first_name" name="first_name" 
                                   value="<?= htmlspecialchars($birthRecord['first_name'] ?? $_POST['first_name'] ?? ''); ?>" placeholder="Enter first name" required>
                            <div class="hidden mt-2 p-2 bg-rose-50 text-rose-600 text-xs font-bold rounded-lg border border-rose-100" id="first_name_warning">
                                <i class="fas fa-exclamation-triangle me-1"></i> Please enter child's first name
                            </div>
                        </div>
                        <div>
                            <label for="middle_name" class="form-label-premium">Middle Name</label>
                            <input type="text" class="form-input-premium" id="middle_name" name="middle_name"
                                   value="<?= htmlspecialchars($birthRecord['middle_name'] ?? $_POST['middle_name'] ?? ''); ?>" placeholder="Enter middle name (optional)">
                        </div>
                        <div>
                            <label for="last_name" class="form-label-premium">Last Name <span class="text-rose-500">*</span></label>
                            <input type="text" class="form-input-premium" id="last_name" name="last_name"
                                   value="<?= htmlspecialchars($birthRecord['last_name'] ?? $_POST['last_name'] ?? ''); ?>" placeholder="Enter last name" required>
                            <div class="hidden mt-2 p-2 bg-rose-50 text-rose-600 text-xs font-bold rounded-lg border border-rose-100" id="last_name_warning">
                                <i class="fas fa-exclamation-triangle me-1"></i> Please enter child's last name
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <div>
                            <label for="birth_date" class="form-label-premium">Date of Birth <span class="text-rose-500">*</span></label>
                            <input type="date" class="form-input-premium" id="birth_date" name="birth_date" 
                                   value="<?= htmlspecialchars($birthRecord['birth_date'] ?? $_POST['birth_date'] ?? ''); ?>" required max="<?php echo date('Y-m-d'); ?>">
                            <div class="hidden mt-2 p-2 bg-rose-50 text-rose-600 text-xs font-bold rounded-lg border border-rose-100" id="birth_date_warning">
                                <i class="fas fa-exclamation-triangle me-1"></i> Please select birth date
                            </div>
                        </div>
                        <div>
                            <label for="birth_time" class="form-label-premium">Time of Birth <span class="text-rose-500">*</span></label>
                            <input type="time" class="form-input-premium" id="birth_time" name="birth_time" 
                                   value="<?= htmlspecialchars($birthRecord['birth_time'] ?? $_POST['birth_time'] ?? ''); ?>" required>
                            <div class="hidden mt-2 p-2 bg-rose-50 text-rose-600 text-xs font-bold rounded-lg border border-rose-100" id="birth_time_warning">
                                <i class="fas fa-exclamation-triangle me-1"></i> Please select birth time
                            </div>
                        </div>
                        <div>
                            <label for="gender" class="form-label-premium">Gender <span class="text-rose-500">*</span></label>
                            <select class="form-input-premium appearance-none" id="gender" name="gender" required>
                                <option value="">Select Gender</option>
                                <option value="Male" <?= (($birthRecord['gender'] ?? $_POST['gender'] ?? '') == 'Male') ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?= (($birthRecord['gender'] ?? $_POST['gender'] ?? '') == 'Female') ? 'selected' : ''; ?>>Female</option>
                            </select>
                            <div class="hidden mt-2 p-2 bg-rose-50 text-rose-600 text-xs font-bold rounded-lg border border-rose-100" id="gender_warning">
                                <i class="fas fa-exclamation-triangle me-1"></i> Please select gender
                            </div>
                        </div>
                        <div>
                            <label for="birth_order" class="form-label-premium">Birth Order</label>
                            <select class="form-input-premium appearance-none" id="birth_order" name="birth_order">
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

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                        <div>
                            <label for="birth_weight" class="form-label-premium">Weight (kg)</label>
                            <input type="number" step="0.01" class="form-input-premium" id="birth_weight" name="birth_weight" 
                                   min="0.5" max="6.0" placeholder="e.g., 3.2" value="<?= htmlspecialchars($birthRecord['birth_weight'] ?? $_POST['birth_weight'] ?? ''); ?>">
                        </div>
                        <div>
                            <label for="birth_length" class="form-label-premium">Length (cm)</label>
                            <input type="number" step="0.1" class="form-input-premium" id="birth_length" name="birth_length" 
                                   min="30" max="60" placeholder="e.g., 50.5" value="<?= htmlspecialchars($birthRecord['birth_length'] ?? $_POST['birth_length'] ?? ''); ?>">
                        </div>
                        <div>
                            <label for="type_of_birth" class="form-label-premium">Type of Birth</label>
                            <select class="form-input-premium appearance-none" id="type_of_birth" name="type_of_birth">
                                <option value="">Select Type</option>
                                <option value="Single" <?= (($birthRecord['type_of_birth'] ?? $_POST['type_of_birth'] ?? '') == 'Single') ? 'selected' : ''; ?>>Single</option>
                                <option value="Twin" <?= (($birthRecord['type_of_birth'] ?? $_POST['type_of_birth'] ?? '') == 'Twin') ? 'selected' : ''; ?>>Twin</option>
                                <option value="Triplet" <?= (($birthRecord['type_of_birth'] ?? $_POST['type_of_birth'] ?? '') == 'Triplet') ? 'selected' : ''; ?>>Triplet</option>
                                <option value="Multiple" <?= (($birthRecord['type_of_birth'] ?? $_POST['type_of_birth'] ?? '') == 'Multiple') ? 'selected' : ''; ?>>Multiple</option>
                            </select>
                        </div>
                        <div>
                            <label for="delivery_type" class="form-label-premium">Mode of Delivery</label>
                            <select class="form-input-premium appearance-none" id="delivery_type" name="delivery_type">
                                <option value="">Select Type</option>
                                <option value="Normal Spontaneous Delivery" <?= (($birthRecord['delivery_type'] ?? $_POST['delivery_type'] ?? '') == 'Normal Spontaneous Delivery') ? 'selected' : ''; ?>>Normal Spontaneous Delivery</option>
                                <option value="Cesarean Section" <?= (($birthRecord['delivery_type'] ?? $_POST['delivery_type'] ?? '') == 'Cesarean Section') ? 'selected' : ''; ?>>Cesarean Section</option>
                                <option value="Forceps Delivery" <?= (($birthRecord['delivery_type'] ?? $_POST['delivery_type'] ?? '') == 'Forceps Delivery') ? 'selected' : ''; ?>>Forceps Delivery</option>
                                <option value="Vacuum Extraction" <?= (($birthRecord['delivery_type'] ?? $_POST['delivery_type'] ?? '') == 'Vacuum Extraction') ? 'selected' : ''; ?>>Vacuum Extraction</option>
                            </select>
                        </div>
                    </div>
                </section>

                <!-- Birth Details -->
                <section class="card-premium">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-hospital text-health-600"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-slate-800">Birth Occurrence Details</h2>
                            <p class="text-xs text-slate-400 font-bold uppercase tracking-widest mt-0.5">Location and medical attendant information</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                        <div>
                            <label for="birth_attendant" class="form-label-premium">Birth Attendant Name</label>
                            <input type="text" class="form-input-premium" id="birth_attendant" name="birth_attendant" 
                                   placeholder="e.g., Dr. Juan Dela Cruz" value="<?= htmlspecialchars($birthRecord['birth_attendant'] ?? $_POST['birth_attendant'] ?? ''); ?>">
                        </div>
                        <div>
                            <label for="birth_attendant_title" class="form-label-premium">Attendant Title</label>
                            <select class="form-input-premium appearance-none" id="birth_attendant_title" name="birth_attendant_title">
                                <option value="">Select Title</option>
                                <option value="Doctor" <?= (($birthRecord['birth_attendant_title'] ?? $_POST['birth_attendant_title'] ?? '') == 'Doctor') ? 'selected' : ''; ?>>Doctor</option>
                                <option value="Midwife" <?= (($birthRecord['birth_attendant_title'] ?? $_POST['birth_attendant_title'] ?? '') == 'Midwife') ? 'selected' : ''; ?>>Midwife</option>
                                <option value="Nurse" <?= (($birthRecord['birth_attendant_title'] ?? $_POST['birth_attendant_title'] ?? '') == 'Nurse') ? 'selected' : ''; ?>>Nurse</option>
                                <option value="Traditional Birth Attendant" <?= (($birthRecord['birth_attendant_title'] ?? $_POST['birth_attendant_title'] ?? '') == 'Traditional Birth Attendant') ? 'selected' : ''; ?>>Traditional Birth Attendant</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                        <div>
                            <label for="birth_place_type" class="form-label-premium">Place of Birth Type</label>
                            <select class="form-input-premium appearance-none" id="birth_place_type" name="birth_place_type">
                                <option value="">Select Type</option>
                                <option value="Hospital" <?= (($birthRecord['birth_place_type'] ?? $_POST['birth_place_type'] ?? '') == 'Hospital') ? 'selected' : ''; ?>>Hospital</option>
                                <option value="Clinic" <?= (($birthRecord['birth_place_type'] ?? $_POST['birth_place_type'] ?? '') == 'Clinic') ? 'selected' : ''; ?>>Clinic</option>
                                <option value="Home" <?= (($birthRecord['birth_place_type'] ?? $_POST['birth_place_type'] ?? '') == 'Home') ? 'selected' : ''; ?>>Home</option>
                                <option value="Birthing Center" <?= (($birthRecord['birth_place_type'] ?? $_POST['birth_place_type'] ?? '') == 'Birthing Center') ? 'selected' : ''; ?>>Birthing Center</option>
                                <option value="Other" <?= (($birthRecord['birth_place_type'] ?? $_POST['birth_place_type'] ?? '') == 'Other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div>
                            <label for="birth_place" class="form-label-premium">Facility Name</label>
                            <input type="text" class="form-input-premium" id="birth_place" name="birth_place" 
                                   placeholder="e.g., Kibenes General Hospital" value="<?= htmlspecialchars($birthRecord['birth_place'] ?? $_POST['birth_place'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-6 mb-8">
                        <div>
                            <label for="birth_address" class="form-label-premium">Complete Address</label>
                            <input type="text" class="form-input-premium" id="birth_address" name="birth_address" 
                                   placeholder="Street Address / Sitio" value="<?= htmlspecialchars($birthRecord['birth_address'] ?? $_POST['birth_address'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="birth_city" class="form-label-premium">City/Municipality</label>
                            <input type="text" class="form-input-premium" id="birth_city" name="birth_city" 
                                   placeholder="e.g., Valencia City" value="<?= htmlspecialchars($birthRecord['birth_city'] ?? $_POST['birth_city'] ?? ''); ?>">
                        </div>
                        <div>
                            <label for="birth_province" class="form-label-premium">Province</label>
                            <input type="text" class="form-input-premium" id="birth_province" name="birth_province" 
                                   placeholder="e.g., Bukidnon" value="<?= htmlspecialchars($birthRecord['birth_province'] ?? $_POST['birth_province'] ?? ''); ?>">
                        </div>
                    </div>
                </section>

                <!-- Informant Information -->
                <section class="card-premium">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-info-circle text-health-600"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-slate-800">Informant Information</h2>
                            <p class="text-xs text-slate-400 font-bold uppercase tracking-widest mt-0.5">Person providing this information</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                        <div>
                            <label for="informant_name" class="form-label-premium">Informant's Full Name</label>
                            <input type="text" class="form-input-premium" id="informant_name" name="informant_name" 
                                   placeholder="Enter full name" value="<?= htmlspecialchars($birthRecord['informant_name'] ?? $_POST['informant_name'] ?? ''); ?>">
                        </div>
                        <div>
                            <label for="informant_relationship" class="form-label-premium">Relationship to Child</label>
                            <select class="form-input-premium appearance-none" id="informant_relationship" name="informant_relationship">
                                <option value="">Select Relationship</option>
                                <option value="Father" <?= (($birthRecord['informant_relationship'] ?? $_POST['informant_relationship'] ?? '') == 'Father') ? 'selected' : ''; ?>>Father</option>
                                <option value="Mother" <?= (($birthRecord['informant_relationship'] ?? $_POST['informant_relationship'] ?? '') == 'Mother') ? 'selected' : ''; ?>>Mother</option>
                                <option value="Grandparent" <?= (($birthRecord['informant_relationship'] ?? $_POST['informant_relationship'] ?? '') == 'Grandparent') ? 'selected' : ''; ?>>Grandparent</option>
                                <option value="Aunt/Uncle" <?= (($birthRecord['informant_relationship'] ?? $_POST['informant_relationship'] ?? '') == 'Aunt/Uncle') ? 'selected' : ''; ?>>Aunt/Uncle</option>
                                <option value="Other Relative" <?= (($birthRecord['informant_relationship'] ?? $_POST['informant_relationship'] ?? '') == 'Other Relative') ? 'selected' : ''; ?>>Other Relative</option>
                                <option value="Hospital Staff" <?= (($birthRecord['informant_relationship'] ?? $_POST['informant_relationship'] ?? '') == 'Hospital Staff') ? 'selected' : ''; ?>>Hospital Staff</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-6">
                        <div>
                            <label for="informant_address" class="form-label-premium">Informant Address</label>
                            <input type="text" class="form-input-premium" id="informant_address" name="informant_address" 
                                   placeholder="Complete address of informant" value="<?= htmlspecialchars($birthRecord['informant_address'] ?? $_POST['informant_address'] ?? ''); ?>">
                        </div>
                    </div>
                </section>

                <!-- Form Actions -->
                <div class="flex flex-col sm:flex-row justify-end items-center gap-4 pt-8">
                    <button type="button" onclick="history.back()" 
                            class="w-full sm:w-auto px-8 py-4 bg-slate-100 text-slate-600 rounded-2xl font-bold uppercase tracking-widest text-xs hover:bg-slate-200 transition-all active:scale-95">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="w-full sm:w-auto px-12 py-4 bg-health-600 text-white rounded-2xl font-black uppercase tracking-widest text-xs hover:bg-health-700 shadow-xl shadow-health-200 transition-all active:scale-95 flex items-center justify-center gap-2">
                        <i class="fas fa-save shadow-sm"></i>
                        <?php echo $isEditMode ? 'Update Birth Record' : 'Register Birth'; ?>
                    </button>
                </div>
            </form>
        </main>
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
                // Update message if it's different (optional, allows dynamic messages)
                if (warning.querySelector('span')) {
                    warning.querySelector('span').textContent = message;
                } else if (!warning.querySelector('i')) {
                    warning.textContent = message;
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