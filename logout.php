<?php
/**
 * TESDA Admin Logout
 * 
 * This file handles user logout by destroying sessions, 
 * clearing cookies, and logging the logout activity.
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'db.php';

// Log the logout activity if user is logged in
if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    $ipAddress = $_SERVER['REMOTE_ADDR'];
    $userAgent = $_SERVER['HTTP_USER_AGENT'];
    
    try {
        // Record logout action in user_logs
        $stmt = $pdo->prepare("INSERT INTO user_logs (user_id, action, description, ip_address, created_at) 
                              VALUES (:user_id, 'logout', :description, :ip, NOW())");
        $stmt->execute([
            'user_id' => $userId,
            'description' => 'User logged out from ' . $userAgent,
            'ip' => $ipAddress
        ]);
    } catch (PDOException $e) {
        // Silently fail - logout should continue even if logging fails
        error_log("Failed to log logout: " . $e->getMessage());
    }
}

// Clear all session variables
$_SESSION = array();

// Delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Delete remember me cookie if it exists
if (isset($_COOKIE['tesda_remember'])) {
    // Remove the token from the database
    try {
        $token = $_COOKIE['tesda_remember'];
        $stmt = $pdo->prepare("DELETE FROM remember_tokens WHERE token = :token");
        $stmt->execute(['token' => $token]);
    } catch (PDOException $e) {
        // Silently fail - continue logout process
        error_log("Failed to remove remember token: " . $e->getMessage());
    }
    
    // Delete the cookie
    setcookie('tesda_remember', '', time() - 42000, '/');
}

// Destroy the session
session_destroy();

// Start a new session for message only
session_start();
$_SESSION['message'] = "You have been successfully logged out.";

// Redirect to login page
header("Location: login.php");
exit;
?>
