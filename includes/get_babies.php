<?php
require_once 'auth.php';
require_once 'functions.php';

if (!isAuthorized(['admin', 'midwife'])) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

redirectIfNotLoggedIn();

global $pdo;

header('Content-Type: application/json');

if (isset($_GET['mother_id']) && !empty($_GET['mother_id'])) {
    $motherId = $_GET['mother_id'];
    
    $babyStmt = $pdo->prepare("
        SELECT id, first_name, last_name, birth_date 
        FROM birth_records 
        WHERE mother_id = ? 
        ORDER BY birth_date DESC
    ");
    $babyStmt->execute([$motherId]);
    $babies = $babyStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['babies' => $babies]);
} else {
    echo json_encode(['babies' => []]);
}