<?php
session_start();
require 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check for valid CSRF token in request header
$headers = getallheaders();
if (!isset($headers['X-Csrf-Token']) || !hash_equals($_SESSION['csrf_token'], $headers['X-Csrf-Token'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

// Get the JSON data from the request
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

if (!isset($data['admin_id']) || $data['admin_id'] != $_SESSION['user_id']) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

try {
    // Mark all unread messages as read
    $stmt = $pdo->prepare("
        UPDATE messages
        SET is_read = 1, read_at = NOW()
        WHERE receiver_id = ? 
        AND receiver_type = 'admin'
        AND is_read = 0
    ");
    
    $stmt->execute([$_SESSION['user_id']]);
    
    // Count how many messages were updated
    $count = $stmt->rowCount();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'message' => $count . ' messages marked as read',
        'count' => $count
    ]);
    
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
