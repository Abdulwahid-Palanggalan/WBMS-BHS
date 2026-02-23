<?php
/**
 * ajax/resolve_sos.php
 * Endpoint to resolve an emergency alert.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!isAuthorized(['midwife', 'admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

global $pdo;
$alertId = $_POST['alert_id'] ?? null;
$userId = $_SESSION['user_id'];

if (!$alertId) {
    echo json_encode(['success' => false, 'message' => 'Alert ID is required.']);
    exit();
}

try {
    $stmt = $pdo->prepare("
        UPDATE emergency_alerts 
        SET status = 'resolved', resolved_by = ?, resolved_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$userId, $alertId]);
    
    logActivity($userId, "Resolved SOS Alert #$alertId");
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
