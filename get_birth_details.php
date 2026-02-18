<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session FIRST before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$rootPath = __DIR__;
require_once $rootPath . '/includes/auth.php';
require_once $rootPath . '/includes/functions.php';

// Check authorization
if (!isAuthorized(['admin', 'midwife'])) {
    header("Location: login.php");
    exit();
}

$babyId = intval($_GET['id']);

// Get complete birth record details with proper table relationships
$query = "SELECT br.*, 
                 m.first_name as mother_first_name,
                 m.middle_name as mother_middle_name,
                 m.last_name as mother_last_name,
                 m.date_of_birth as mother_date_of_birth,
                 m.nationality as mother_nationality,
                 m.religion as mother_religion,
                 m.occupation as mother_occupation,
                 m.address as mother_address,
                 m.phone as mother_phone,
                 hp.first_name as father_first_name,
                 hp.middle_name as father_middle_name,
                 hp.last_name as father_last_name,
                 hp.date_of_birth as father_date_of_birth,
                 hp.citizenship as father_citizenship,
                 hp.religion as father_religion,
                 hp.occupation as father_occupation,
                 hp.phone as father_phone,
                 hp.marriage_date,
                 hp.marriage_place,
                 u.first_name as registered_by_firstname, 
                 u.last_name as registered_by_lastname,
                 bi.informant_name,
                 bi.informant_relationship,
                 bi.informant_address
          FROM birth_records br 
          LEFT JOIN mothers m ON br.mother_id = m.id
          LEFT JOIN husband_partners hp ON m.id = hp.mother_id
          LEFT JOIN birth_informants bi ON br.id = bi.birth_record_id
          LEFT JOIN users u ON br.registered_by = u.id
          WHERE br.id = ?";

$stmt = $pdo->prepare($query);
$stmt->execute([$babyId]);
$baby = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$baby) {
    echo '<div class="alert alert-danger">Birth record not found.</div>';
    exit;
}

// Helper functions
function displayData($value, $default = 'N/A') {
    return !empty($value) && $value != '0000-00-00' ? htmlspecialchars($value) : $default;
}

function displayDate($date, $format = 'F j, Y') {
    if (empty($date) || $date == '0000-00-00') return 'N/A';
    return date($format, strtotime($date));
}

function displayDateTime($date, $time, $format = 'F j, Y g:i A') {
    if (empty($date) || $date == '0000-00-00') return 'N/A';
    $datetime = $date . ' ' . $time;
    return date($format, strtotime($datetime));
}
?>

<!-- Baby Information -->
<div class="detail-section baby-info">
    <h6><i class="fas fa-baby me-2"></i>Baby Information</h6>
    <div class="row">
        <div class="col-md-4 detail-item">
            <span class="detail-label">Full Name:</span>
            <span class="detail-value">
                <?php 
                $babyName = htmlspecialchars($baby['first_name']);
                if (!empty($baby['middle_name'])) {
                    $babyName .= ' ' . htmlspecialchars($baby['middle_name']);
                }
                $babyName .= ' ' . htmlspecialchars($baby['last_name']);
                echo $babyName;
                ?>
            </span>
        </div>
        <div class="col-md-4 detail-item">
            <span class="detail-label">Gender:</span>
            <span class="detail-value"><?php echo displayData($baby['gender']); ?></span>
        </div>
        <div class="col-md-4 detail-item">
            <span class="detail-label">Birth Date & Time:</span>
            <span class="detail-value"><?php echo displayDateTime($baby['birth_date'], $baby['birth_time']); ?></span>
        </div>
        <div class="col-md-3 detail-item">
            <span class="detail-label">Birth Weight:</span>
            <span class="detail-value"><?php echo !empty($baby['birth_weight']) ? $baby['birth_weight'] . ' kg' : 'N/A'; ?></span>
        </div>
        <div class="col-md-3 detail-item">
            <span class="detail-label">Birth Length:</span>
            <span class="detail-value"><?php echo !empty($baby['birth_length']) ? $baby['birth_length'] . ' cm' : 'N/A'; ?></span>
        </div>
        <div class="col-md-3 detail-item">
            <span class="detail-label">Birth Order:</span>
            <span class="detail-value"><?php echo displayData($baby['birth_order']); ?></span>
        </div>
        <div class="col-md-3 detail-item">
            <span class="detail-label">Type of Birth:</span>
            <span class="detail-value"><?php echo displayData($baby['type_of_birth']); ?></span>
        </div>
    </div>
</div>

<!-- Birth Details -->
<div class="detail-section">
    <h6><i class="fas fa-hospital me-2"></i>Birth Details</h6>
    <div class="row">
        <div class="col-md-4 detail-item">
            <span class="detail-label">Delivery Type:</span>
            <span class="detail-value"><?php echo displayData($baby['delivery_type']); ?></span>
        </div>
        <div class="col-md-4 detail-item">
            <span class="detail-label">Birth Attendant:</span>
            <span class="detail-value"><?php echo displayData($baby['birth_attendant']); ?></span>
        </div>
        <div class="col-md-4 detail-item">
            <span class="detail-label">Attendant Title:</span>
            <span class="detail-value"><?php echo displayData($baby['birth_attendant_title']); ?></span>
        </div>
        <div class="col-md-6 detail-item">
            <span class="detail-label">Birth Place:</span>
            <span class="detail-value"><?php echo displayData($baby['birth_place']); ?></span>
        </div>
        <div class="col-md-6 detail-item">
            <span class="detail-label">Place Type:</span>
            <span class="detail-value"><?php echo displayData($baby['birth_place_type']); ?></span>
        </div>
        <div class="col-12 detail-item">
            <span class="detail-label">Birth Address:</span>
            <span class="detail-value"><?php echo displayData($baby['birth_address']); ?></span>
        </div>
        <div class="col-md-6 detail-item">
            <span class="detail-label">City/Municipality:</span>
            <span class="detail-value"><?php echo displayData($baby['birth_city']); ?></span>
        </div>
        <div class="col-md-6 detail-item">
            <span class="detail-label">Province:</span>
            <span class="detail-value"><?php echo displayData($baby['birth_province']); ?></span>
        </div>
    </div>
</div>

<!-- Parents Information -->
<div class="detail-section parents-info">
    <h6><i class="fas fa-users me-2"></i>Parents Information</h6>
    <div class="row">
        <div class="col-md-6">
            <h6 class="text-success">Mother's Information</h6>
            <div class="detail-item">
                <span class="detail-label">Full Name:</span>
                <span class="detail-value">
                    <?php 
                    $motherName = htmlspecialchars($baby['mother_first_name'] ?? '');
                    if (!empty($baby['mother_middle_name'])) {
                        $motherName .= ' ' . htmlspecialchars($baby['mother_middle_name']);
                    }
                    $motherName .= ' ' . htmlspecialchars($baby['mother_last_name'] ?? '');
                    echo !empty(trim($motherName)) ? $motherName : 'N/A';
                    ?>
                </span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Birthdate:</span>
                <span class="detail-value"><?php echo displayDate($baby['mother_date_of_birth'] ?? ''); ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Citizenship:</span>
                <span class="detail-value"><?php echo displayData($baby['mother_nationality'] ?? '', 'Filipino'); ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Religion:</span>
                <span class="detail-value"><?php echo displayData($baby['mother_religion'] ?? ''); ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Occupation:</span>
                <span class="detail-value"><?php echo displayData($baby['mother_occupation'] ?? ''); ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Address:</span>
                <span class="detail-value"><?php echo displayData($baby['mother_address'] ?? ''); ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Phone:</span>
                <span class="detail-value"><?php echo displayData($baby['mother_phone'] ?? ''); ?></span>
            </div>
        </div>
        
        <div class="col-md-6">
            <h6 class="text-primary">Father's Information</h6>
            <div class="detail-item">
                <span class="detail-label">Full Name:</span>
                <span class="detail-value">
                    <?php 
                    $fatherName = htmlspecialchars($baby['father_first_name'] ?? '');
                    if (!empty($baby['father_middle_name'])) {
                        $fatherName .= ' ' . htmlspecialchars($baby['father_middle_name']);
                    }
                    $fatherName .= ' ' . htmlspecialchars($baby['father_last_name'] ?? '');
                    echo !empty(trim($fatherName)) ? $fatherName : 'N/A';
                    ?>
                </span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Birthdate:</span>
                <span class="detail-value"><?php echo displayDate($baby['father_date_of_birth'] ?? ''); ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Citizenship:</span>
                <span class="detail-value"><?php echo displayData($baby['father_citizenship'] ?? '', 'Filipino'); ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Religion:</span>
                <span class="detail-value"><?php echo displayData($baby['father_religion'] ?? ''); ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Occupation:</span>
                <span class="detail-value"><?php echo displayData($baby['father_occupation'] ?? ''); ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Phone:</span>
                <span class="detail-value"><?php echo displayData($baby['father_phone'] ?? ''); ?></span>
            </div>
        </div>
    </div>
    
    <!-- Parents Marriage Information -->
    <?php if (!empty($baby['marriage_date']) || !empty($baby['marriage_place'])): ?>
    <div class="row mt-3">
        <div class="col-12">
            <h6 class="text-primary">Marriage Information</h6>
            <div class="detail-item">
                <span class="detail-label">Marriage Date:</span>
                <span class="detail-value"><?php echo displayDate($baby['marriage_date'] ?? ''); ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Marriage Place:</span>
                <span class="detail-value"><?php echo displayData($baby['marriage_place'] ?? ''); ?></span>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Informant Information -->
<?php if (!empty($baby['informant_name'])): ?>
<div class="detail-section">
    <h6><i class="fas fa-user-check me-2"></i>Informant Information</h6>
    <div class="row">
        <div class="col-md-4 detail-item">
            <span class="detail-label">Informant Name:</span>
            <span class="detail-value"><?php echo displayData($baby['informant_name']); ?></span>
        </div>
        <div class="col-md-4 detail-item">
            <span class="detail-label">Relationship:</span>
            <span class="detail-value"><?php echo displayData($baby['informant_relationship']); ?></span>
        </div>
        <div class="col-md-4 detail-item">
            <span class="detail-label">Informant Address:</span>
            <span class="detail-value"><?php echo displayData($baby['informant_address']); ?></span>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Registration Information -->
<div class="detail-section">
    <h6><i class="fas fa-info-circle me-2"></i>Registration Information</h6>
    <div class="row">
        <div class="col-md-4 detail-item">
            <span class="detail-label">Registered By:</span>
            <span class="detail-value">
                <?php 
                if (!empty($baby['registered_by_firstname'])) {
                    echo htmlspecialchars($baby['registered_by_firstname'] . ' ' . $baby['registered_by_lastname']);
                } else {
                    echo 'System';
                }
                ?>
            </span>
        </div>
        <div class="col-md-4 detail-item">
            <span class="detail-label">Registration Date:</span>
            <span class="detail-value"><?php echo displayDate($baby['created_at']); ?></span>
        </div>
        <?php if (!empty($baby['updated_at']) && $baby['updated_at'] != $baby['created_at']): ?>
        <div class="col-md-4 detail-item">
            <span class="detail-label">Last Updated:</span>
            <span class="detail-value"><?php echo displayDate($baby['updated_at']); ?></span>
        </div>
        <?php endif; ?>
    </div>
</div>
