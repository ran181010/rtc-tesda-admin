<?php
require 'db.php';

// Get all scheduled messages that are due
$stmt = $pdo->prepare("
    SELECT * FROM messages 
    WHERE scheduled_time IS NOT NULL 
    AND scheduled_time <= NOW() 
    AND is_sent = 0
");
$stmt->execute();
$scheduled_messages = $stmt->fetchAll();

foreach ($scheduled_messages as $message) {
    try {
        // Mark message as sent
        $stmt = $pdo->prepare("
            UPDATE messages 
            SET is_sent = 1, 
                sent_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$message['id']]);
        
        // Log the sent message
        $stmt = $pdo->prepare("
            INSERT INTO message_logs 
            (message_id, status, details) 
            VALUES (?, 'sent', 'Scheduled message sent successfully')
        ");
        $stmt->execute([$message['id']]);
        
    } catch (PDOException $e) {
        // Log error
        error_log("Error sending scheduled message {$message['id']}: " . $e->getMessage());
        
        // Update message status
        $stmt = $pdo->prepare("
            UPDATE messages 
            SET send_attempts = send_attempts + 1,
                last_error = ?
            WHERE id = ?
        ");
        $stmt->execute([$e->getMessage(), $message['id']]);
    }
}
?> 