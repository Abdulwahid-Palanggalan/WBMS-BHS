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

// Debug session data
error_log("DELETE - Session role: " . ($_SESSION['role'] ?? 'NOT SET'));
error_log("DELETE - Session user_id: " . ($_SESSION['user_id'] ?? 'NOT SET'));

// FIXED: Check authorization for both admin and midwife
if (!isAuthorized(['admin', 'midwife'])) {
    error_log("DELETE - User not authorized. Role: " . ($_SESSION['role'] ?? 'NOT SET'));
    $_SESSION['error'] = "Unauthorized access. Only admin and midwife can delete records.";
    header("Location: login.php");
    exit();
}

// Handle both GET and POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['type'])) {
    $type = $_POST['type'] ?? $_GET['type'] ?? '';
    $id = isset($_POST['id']) ? intval($_POST['id']) : (isset($_GET['id']) ? intval($_GET['id']) : 0);
    
    error_log("DELETE - Type: $type, ID: $id");
    
    if (empty($type) || $id <= 0) {
        $_SESSION['error'] = "Invalid request parameters.";
        header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'dashboard.php'));
        exit();
    }
    
    try {
        switch($type) {
            case 'mother':
                // Check if mother exists first
                $stmt = $pdo->prepare("SELECT m.*, u.first_name, u.last_name FROM mothers m JOIN users u ON m.user_id = u.id WHERE m.id = ?");
                $stmt->execute([$id]);
                $mother = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$mother) {
                    throw new Exception("Mother record not found.");
                }
                
                // Log the deletion for audit trail
                error_log("DELETE - Deleting mother: " . $mother['first_name'] . " " . $mother['last_name'] . " (ID: $id) by user: " . ($_SESSION['user_id'] ?? 'unknown'));
                
                // Delete related records first to maintain referential integrity
                
                // 1. Delete pregnancy details
                $stmt = $pdo->prepare("DELETE FROM pregnancy_details WHERE mother_id = ?");
                $stmt->execute([$id]);
                
                // 2. Delete medical histories
                $stmt = $pdo->prepare("DELETE FROM medical_histories WHERE mother_id = ?");
                $stmt->execute([$id]);
                
                // 3. Delete husband/partner records
                $stmt = $pdo->prepare("DELETE FROM husband_partners WHERE mother_id = ?");
                $stmt->execute([$id]);
                
                // 4. Delete prenatal records
                $stmt = $pdo->prepare("DELETE FROM prenatal_records WHERE mother_id = ?");
                $stmt->execute([$id]);
                
                // 5. Delete postnatal records
                $stmt = $pdo->prepare("DELETE FROM postnatal_records WHERE mother_id = ?");
                $stmt->execute([$id]);
                
                // 6. Get birth records associated with this mother to delete babies
                $stmt = $pdo->prepare("SELECT id FROM birth_records WHERE mother_id = ?");
                $stmt->execute([$id]);
                $birthRecords = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                // 7. Delete birth records and associated babies
                foreach ($birthRecords as $birthId) {
                    // Delete birth record
                    $stmt = $pdo->prepare("DELETE FROM birth_records WHERE id = ?");
                    $stmt->execute([$birthId]);
                }
                
                // 8. Finally delete the mother record
                $stmt = $pdo->prepare("DELETE FROM mothers WHERE id = ?");
                $stmt->execute([$id]);
                
                // Note: We don't delete the user record to maintain audit trail
                // You can optionally delete the user if needed:
                // $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                // $stmt->execute([$mother['user_id']]);
                
                $_SESSION['success'] = "Mother record and all associated data deleted successfully.";
                break;
                
            case 'baby':
                // Check if baby exists
                $stmt = $pdo->prepare("SELECT * FROM birth_records WHERE id = ?");
                $stmt->execute([$id]);
                $baby = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$baby) {
                    throw new Exception("Baby record not found.");
                }
                
                // Log the deletion
                error_log("DELETE - Deleting baby record ID: $id by user: " . ($_SESSION['user_id'] ?? 'unknown'));
                
                // Delete baby record
                $stmt = $pdo->prepare("DELETE FROM birth_records WHERE id = ?");
                $stmt->execute([$id]);
                
                $_SESSION['success'] = "Baby record deleted successfully.";
                break;
                
            case 'prenatal_record':
                // Check if prenatal record exists
                $stmt = $pdo->prepare("SELECT pr.*, m.first_name, m.last_name 
                                      FROM prenatal_records pr 
                                      JOIN mothers m ON pr.mother_id = m.id 
                                      WHERE pr.id = ?");
                $stmt->execute([$id]);
                $prenatalRecord = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$prenatalRecord) {
                    throw new Exception("Prenatal record not found.");
                }
                
                // Log the deletion for audit trail
                error_log("DELETE - Deleting prenatal record ID: $id for mother: " . $prenatalRecord['first_name'] . " " . $prenatalRecord['last_name'] . " by user: " . ($_SESSION['user_id'] ?? 'unknown'));
                
                // Delete prenatal record
                $stmt = $pdo->prepare("DELETE FROM prenatal_records WHERE id = ?");
                $stmt->execute([$id]);
                
                $_SESSION['success'] = "Prenatal visit record deleted successfully.";
                break;
                
            default:
                throw new Exception("Invalid record type specified.");
        }
        
    } catch (Exception $e) {
        error_log("DELETE - Error: " . $e->getMessage());
        $_SESSION['error'] = "Error deleting record: " . $e->getMessage();
    }
    
    // Return JSON response for AJAX requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        if (isset($_SESSION['error'])) {
            echo json_encode(['success' => false, 'message' => $_SESSION['error']]);
            unset($_SESSION['error']);
        } else {
            echo json_encode(['success' => true, 'message' => $_SESSION['success']]);
            unset($_SESSION['success']);
        }
        exit();
    } else {
        // Redirect back for GET requests
        header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'dashboard.php'));
        exit();
    }
} else {
    $_SESSION['error'] = "Invalid request method.";
    header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'dashboard.php'));
    exit();
}
?>