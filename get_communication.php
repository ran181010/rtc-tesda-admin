<?php
session_start();
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

// Get communication by ID
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    try {
        $stmt = $pdo->prepare("
            SELECT c.*, a.username as creator 
            FROM communications c
            JOIN admin_users a ON c.created_by = a.id
            WHERE c.id = ?
        ");
        $stmt->execute([$id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($item) {
            // Format dates
            $item['created_at'] = date('M d, Y', strtotime($item['created_at']));
            $item['expiry_date'] = $item['expiry_date'] ? date('M d, Y', strtotime($item['expiry_date'])) : 'Never';
            
            // Send JSON response
            header('Content-Type: application/json');
            echo json_encode(['success' => true] + $item);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Communication not found']);
        }
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Default response for invalid requests
header('Content-Type: application/json');
echo json_encode(['success' => false, 'message' => 'Invalid request']);