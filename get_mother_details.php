<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session only if none is active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// CORRECTED PATH - adjust based on your actual file structure
$rootPath = __DIR__;
require_once $rootPath . '/config/config.php';

// FIX: Allow both admin and midwife
$allowedRoles = ['admin', 'midwife'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowedRoles)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

global $pdo;

if (isset($_GET['id']) && !empty($_GET['id'])) {
    $motherId = intval($_GET['id']);
    
    try {
        // Get complete mother details with ALL related information from new tables
        $query = "SELECT 
                    m.*, 
                    u.first_name, u.last_name, u.email, u.phone, u.created_at as registered_date,
                    pd.lmp, pd.edc, pd.gravida, pd.para, pd.abortions, pd.living_children,
                    pd.planned_pregnancy, pd.first_prenatal_visit, pd.referred_by,
                    hp.first_name as husband_first_name, hp.middle_name as husband_middle_name, 
                    hp.last_name as husband_last_name, hp.date_of_birth as husband_birthdate,
                    hp.occupation as husband_occupation, hp.education as husband_education,
                    hp.phone as husband_phone, hp.citizenship as husband_citizenship,
                    hp.religion as husband_religion, hp.marriage_date, hp.marriage_place,
                    mh.allergies, mh.medical_conditions, mh.previous_surgeries,
                    mh.family_history, mh.contraceptive_use, mh.previous_complications
                  FROM mothers m 
                  JOIN users u ON m.user_id = u.id 
                  LEFT JOIN pregnancy_details pd ON m.id = pd.mother_id
                  LEFT JOIN husband_partners hp ON m.id = hp.mother_id
                  LEFT JOIN medical_histories mh ON m.id = mh.mother_id
                  WHERE m.id = ?";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$motherId]);
        $mother = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$mother) {
            echo '<div class="alert alert-danger">Mother not found.</div>';
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

        function calculateAge($birthDate) {
            if (empty($birthDate) || $birthDate == '0000-00-00') return 'N/A';
            $age = date_diff(date_create($birthDate), date_create('today'))->y;
            return $age . ' years';
        }
?>

<!-- Personal Information -->
<div class="detail-section">
    <h6><i class="fas fa-user me-2"></i>Personal Information</h6>
    <div class="row">
        <div class="col-md-6 detail-item">
            <span class="detail-label">Full Name:</span>
            <span class="detail-value"><?php echo htmlspecialchars($mother['first_name'] . ' ' . $mother['last_name']); ?></span>
        </div>
        <div class="col-md-6 detail-item">
            <span class="detail-label">Date of Birth:</span>
            <span class="detail-value"><?php echo displayDate($mother['date_of_birth']); ?> (<?php echo calculateAge($mother['date_of_birth']); ?>)</span>
        </div>
        <div class="col-md-6 detail-item">
            <span class="detail-label">Civil Status:</span>
            <span class="detail-value"><?php echo displayData($mother['civil_status']); ?></span>
        </div>
        <div class="col-md-6 detail-item">
            <span class="detail-label">Nationality:</span>
            <span class="detail-value"><?php echo displayData($mother['nationality'], 'Filipino'); ?></span>
        </div>
        <div class="col-md-6 detail-item">
            <span class="detail-label">Religion:</span>
            <span class="detail-value"><?php echo displayData($mother['religion']); ?></span>
        </div>
        <div class="col-md-6 detail-item">
            <span class="detail-label">Education:</span>
            <span class="detail-value"><?php echo displayData($mother['education']); ?></span>
        </div>
        <div class="col-md-6 detail-item">
            <span class="detail-label">Occupation:</span>
            <span class="detail-value"><?php echo displayData($mother['occupation']); ?></span>
        </div>
        <div class="col-md-6 detail-item">
            <span class="detail-label">Registered Date:</span>
            <span class="detail-value"><?php echo displayDate($mother['registered_date']); ?></span>
        </div>
    </div>
</div>

<!-- Contact Information -->
<div class="detail-section">
    <h6><i class="fas fa-address-book me-2"></i>Contact Information</h6>
    <div class="row">
        <div class="col-md-6 detail-item">
            <span class="detail-label">Phone Number:</span>
            <span class="detail-value"><?php echo displayData($mother['phone']); ?></span>
        </div>
        <div class="col-md-6 detail-item">
            <span class="detail-label">Email Address:</span>
            <span class="detail-value"><?php echo displayData($mother['email']); ?></span>
        </div>
        <div class="col-12 detail-item">
            <span class="detail-label">Address:</span>
            <span class="detail-value"><?php echo displayData($mother['address']); ?></span>
        </div>
        <div class="col-md-6 detail-item">
            <span class="detail-label">Emergency Contact:</span>
            <span class="detail-value"><?php echo displayData($mother['emergency_contact']); ?></span>
        </div>
        <div class="col-md-6 detail-item">
            <span class="detail-label">Emergency Phone:</span>
            <span class="detail-value"><?php echo displayData($mother['emergency_phone']); ?></span>
        </div>
    </div>
</div>

<!-- Medical Information -->
<div class="detail-section medical-info">
    <h6><i class="fas fa-heartbeat me-2"></i>Medical Information</h6>
    <div class="row">
        <div class="col-md-3 detail-item">
            <span class="detail-label">Blood Type:</span>
            <span class="detail-value"><?php echo displayData($mother['blood_type']); ?></span>
        </div>
        <div class="col-md-3 detail-item">
            <span class="detail-label">RH Factor:</span>
            <span class="detail-value"><?php echo displayData($mother['rh_factor']); ?></span>
        </div>
        <div class="col-12 detail-item">
            <span class="detail-label">Allergies:</span>
            <span class="detail-value"><?php echo displayData($mother['allergies']); ?></span>
        </div>
        <div class="col-12 detail-item">
            <span class="detail-label">Medical Conditions:</span>
            <span class="detail-value"><?php echo displayData($mother['medical_conditions']); ?></span>
        </div>
        <div class="col-12 detail-item">
            <span class="detail-label">Previous Surgeries:</span>
            <span class="detail-value"><?php echo displayData($mother['previous_surgeries']); ?></span>
        </div>
        <div class="col-12 detail-item">
            <span class="detail-label">Family Medical History:</span>
            <span class="detail-value"><?php echo displayData($mother['family_history']); ?></span>
        </div>
        <div class="col-12 detail-item">
            <span class="detail-label">Contraceptive Use:</span>
            <span class="detail-value"><?php echo displayData($mother['contraceptive_use']); ?></span>
        </div>
        <div class="col-12 detail-item">
            <span class="detail-label">Previous Complications:</span>
            <span class="detail-value"><?php echo displayData($mother['previous_complications']); ?></span>
        </div>
    </div>
</div>

<!-- Husband/Partner Information -->
<?php if (!empty($mother['husband_first_name'])): ?>
<div class="detail-section husband-info">
    <h6><i class="fas fa-user-friends me-2"></i>Husband/Partner Information</h6>
    <div class="row">
        <div class="col-md-6 detail-item">
            <span class="detail-label">Full Name:</span>
            <span class="detail-value">
                <?php 
                $husbandName = trim($mother['husband_first_name'] . ' ' . 
                    ($mother['husband_middle_name'] ? $mother['husband_middle_name'] . ' ' : '') . 
                    $mother['husband_last_name']);
                echo htmlspecialchars($husbandName);
                ?>
            </span>
        </div>
        <div class="col-md-6 detail-item">
            <span class="detail-label">Date of Birth:</span>
            <span class="detail-value"><?php echo displayDate($mother['husband_birthdate']); ?></span>
        </div>
        <div class="col-md-6 detail-item">
            <span class="detail-label">Occupation:</span>
            <span class="detail-value"><?php echo displayData($mother['husband_occupation']); ?></span>
        </div>
        <div class="col-md-6 detail-item">
            <span class="detail-label">Education:</span>
            <span class="detail-value"><?php echo displayData($mother['husband_education']); ?></span>
        </div>
        <div class="col-md-6 detail-item">
            <span class="detail-label">Phone Number:</span>
            <span class="detail-value"><?php echo displayData($mother['husband_phone']); ?></span>
        </div>
        <div class="col-md-6 detail-item">
            <span class="detail-label">Citizenship:</span>
            <span class="detail-value"><?php echo displayData($mother['husband_citizenship'], 'Filipino'); ?></span>
        </div>
        <div class="col-md-6 detail-item">
            <span class="detail-label">Religion:</span>
            <span class="detail-value"><?php echo displayData($mother['husband_religion']); ?></span>
        </div>
        <?php if (!empty($mother['marriage_date'])): ?>
        <div class="col-md-6 detail-item">
            <span class="detail-label">Marriage Date:</span>
            <span class="detail-value"><?php echo displayDate($mother['marriage_date']); ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($mother['marriage_place'])): ?>
        <div class="col-12 detail-item">
            <span class="detail-label">Marriage Place:</span>
            <span class="detail-value"><?php echo displayData($mother['marriage_place']); ?></span>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Pregnancy Information -->
<?php if (!empty($mother['lmp'])): ?>
<div class="detail-section pregnancy-info">
    <h6><i class="fas fa-baby me-2"></i>Pregnancy Information</h6>
    <div class="row">
        <div class="col-md-4 detail-item">
            <span class="detail-label">Last Menstrual Period:</span>
            <span class="detail-value"><?php echo displayDate($mother['lmp']); ?></span>
        </div>
        <div class="col-md-4 detail-item">
            <span class="detail-label">Estimated Due Date:</span>
            <span class="detail-value"><?php echo displayDate($mother['edc']); ?></span>
        </div>
        <div class="col-md-4 detail-item">
            <span class="detail-label">First Prenatal Visit:</span>
            <span class="detail-value"><?php echo displayDate($mother['first_prenatal_visit']); ?></span>
        </div>
        <div class="col-md-3 detail-item">
            <span class="detail-label">Gravida (G):</span>
            <span class="detail-value"><?php echo displayData($mother['gravida'], '0'); ?></span>
        </div>
        <div class="col-md-3 detail-item">
            <span class="detail-label">Para (P):</span>
            <span class="detail-value"><?php echo displayData($mother['para'], '0'); ?></span>
        </div>
        <div class="col-md-3 detail-item">
            <span class="detail-label">Abortions:</span>
            <span class="detail-value"><?php echo displayData($mother['abortions'], '0'); ?></span>
        </div>
        <div class="col-md-3 detail-item">
            <span class="detail-label">Living Children:</span>
            <span class="detail-value"><?php echo displayData($mother['living_children'], '0'); ?></span>
        </div>
        <div class="col-md-6 detail-item">
            <span class="detail-label">Planned Pregnancy:</span>
            <span class="detail-value"><?php echo displayData($mother['planned_pregnancy']); ?></span>
        </div>
        <div class="col-md-6 detail-item">
            <span class="detail-label">Referred By:</span>
            <span class="detail-value"><?php echo displayData($mother['referred_by']); ?></span>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
    } catch (PDOException $e) {
        error_log("Database error in get_mother_details: " . $e->getMessage());
        echo '<div class="alert alert-danger">Database error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
} else {
    echo '<div class="alert alert-danger">No mother ID provided</div>';
}
?>