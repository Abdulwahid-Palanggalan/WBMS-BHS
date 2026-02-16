<?php
require_once 'config/config.php';

header('Content-Type: application/json');

// Check if user is logged in
session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if mother_id is provided
if (!isset($_GET['mother_id']) || empty($_GET['mother_id'])) {
    echo json_encode(['success' => false, 'message' => 'Mother ID is required']);
    exit;
}

$motherId = intval($_GET['mother_id']);

try {
    global $pdo;
    
    // Get mother details from database
    $stmt = $pdo->prepare("
        SELECT 
            u.first_name, 
            u.last_name, 
            u.email, 
            u.phone,
            m.date_of_birth, 
            m.civil_status, 
            m.nationality, 
            m.religion,
            m.education, 
            m.occupation, 
            m.address,
            m.husband_first_name, 
            m.husband_last_name, 
            m.husband_date_of_birth,
            m.husband_occupation, 
            m.husband_education, 
            m.husband_phone
        FROM mothers m 
        JOIN users u ON m.user_id = u.id 
        WHERE m.id = ?
    ");
    $stmt->execute([$motherId]);
    $motherData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($motherData) {
        echo json_encode([
            'success' => true,
            'first_name' => $motherData['first_name'] ?? '',
            'last_name' => $motherData['last_name'] ?? '',
            'email' => $motherData['email'] ?? '',
            'phone' => $motherData['phone'] ?? '',
            'date_of_birth' => $motherData['date_of_birth'] ?? '',
            'civil_status' => $motherData['civil_status'] ?? '',
            'nationality' => $motherData['nationality'] ?? '',
            'religion' => $motherData['religion'] ?? '',
            'education' => $motherData['education'] ?? '',
            'occupation' => $motherData['occupation'] ?? '',
            'address' => $motherData['address'] ?? '',
            'husband_first_name' => $motherData['husband_first_name'] ?? '',
            'husband_last_name' => $motherData['husband_last_name'] ?? '',
            'husband_date_of_birth' => $motherData['husband_date_of_birth'] ?? '',
            'husband_occupation' => $motherData['husband_occupation'] ?? '',
            'husband_education' => $motherData['husband_education'] ?? '',
            'husband_phone' => $motherData['husband_phone'] ?? ''
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Mother not found']);
    }
} catch (Exception $e) {
    error_log("Error fetching mother details: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>