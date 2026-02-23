<?php
/**
 * ajax/trigger_sos.php
 * Endpoint to trigger an emergency alert from the Mother Portal.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!isAuthorized(['mother'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

global $pdo;
$userId = $_SESSION['user_id'];

// Get mother_id
$stmt = $pdo->prepare("SELECT id FROM mothers WHERE user_id = ?");
$stmt->execute([$userId]);
$motherId = $stmt->fetchColumn();

if (!$motherId) {
    echo json_encode(['success' => false, 'message' => 'Mother profile not found.']);
    exit();
}

$type = $_POST['alert_type'] ?? 'General Emergency';
$location = $_POST['location'] ?? 'Location not shared';

try {
    $stmt = $pdo->prepare("
        INSERT INTO emergency_alerts (mother_id, alert_type, location_data, status)
        VALUES (?, ?, ?, 'active')
    ");
    $stmt->execute([$motherId, $type, $location]);
    
    // Log activity
    logActivity($userId, "Triggered SOS Alert: $type");
    
    echo json_encode(['success' => true, 'message' => 'SOS Alert triggered successfully. Health station has been notified.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
