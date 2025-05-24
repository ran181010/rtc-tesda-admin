<?php
// Start session with secure settings
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Strict');
session_start();

require 'db.php';

// Ensure login_attempts table exists
function setupDatabase() {
    global $pdo;
    try {
        // Check if login_attempts table exists
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'login_attempts'");
        $tableExists = $tableCheck->rowCount() > 0;
        
        if (!$tableExists) {
            // Create login_attempts table if it doesn't exist
            $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ip_address VARCHAR(45) NOT NULL,
                username VARCHAR(100) NOT NULL,
                success TINYINT(1) NOT NULL DEFAULT 0,
                attempt_time DATETIME NOT NULL,
                details VARCHAR(255) NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            
            // Create indexes for faster searching
            $pdo->exec("CREATE INDEX idx_ip_time ON login_attempts(ip_address, attempt_time)");
            $pdo->exec("CREATE INDEX idx_username ON login_attempts(username)");
        }
        
        return true;
    } catch (PDOException $e) {
        // If there's an error, log it but don't stop execution
        error_log("Database setup error: " . $e->getMessage());
        return false;
    }
}

// Try to set up database, otherwise disable rate limiting
$rateLimit = setupDatabase();

// Sanitize input to replace deprecated FILTER_SANITIZE_STRING
function sanitizeInput($input) {
    $input = trim($input);
    $input = stripslashes($input);
    $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    return $input;
}

// Rate limiting with exponential backoff
function checkRateLimit($ip) {
    global $pdo, $rateLimit;
    
    // Skip rate limiting if table doesn't exist
    if (!$rateLimit) {
        return true;
    }
    
    try {
        $timeWindow = 300; // 5 minutes
        $maxAttempts = 5;

        // Count recent failed attempts
        $sql = "SELECT COUNT(*) as attempts, MAX(attempt_time) as last_attempt FROM login_attempts 
                WHERE ip_address = ? AND success = 0 AND attempt_time > DATE_SUB(NOW(), INTERVAL ? SECOND)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$ip, $timeWindow]);
        $result = $stmt->fetch();
        
        $attempts = isset($result['attempts']) ? $result['attempts'] : 0;
        $lastAttempt = isset($result['last_attempt']) ? $result['last_attempt'] : null;
        
        // Implement exponential backoff - wait time increases with each attempt
        if ($attempts >= $maxAttempts) {
            // Calculate wait time: 2^(attempts-maxAttempts) seconds (with a cap)
            $waitTime = min(300, pow(2, $attempts - $maxAttempts)); // Cap at 5 minutes
            
            if ($lastAttempt) {
                $timeSinceLastAttempt = time() - strtotime($lastAttempt);
                if ($timeSinceLastAttempt < $waitTime) {
                    // Store remaining wait time in session for user feedback
                    $_SESSION['wait_time'] = $waitTime - $timeSinceLastAttempt;
                    return false;
                }
            }
        }
        return true;
    } catch (PDOException $e) {
        // If there's a database error, log it and disable rate limiting
        error_log("Rate limit check error: " . $e->getMessage());
        return true;
    }
}

function logLoginAttempt($ip, $username, $success, $details = '') {
    global $pdo, $rateLimit;
    
    // Skip logging if table doesn't exist
    if (!$rateLimit) {
        return;
    }
    
    try {
        $sql = "INSERT INTO login_attempts (ip_address, username, success, attempt_time, details) 
                VALUES (?, ?, ?, NOW(), ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$ip, $username, $success, $details]);
        
        // If successful login, clear previous failed attempts
        if ($success) {
            $sql = "DELETE FROM login_attempts WHERE ip_address = ? AND username = ? AND success = 0";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$ip, $username]);
        }
    } catch (PDOException $e) {
        // If there's a database error, just log it
        error_log("Login attempt logging error: " . $e->getMessage());
    }
}

// Force clear all session data
if (isset($_GET['clear_session']) && $_GET['clear_session'] == 1) {
    // Clear all session variables
    $_SESSION = array();
    
    // Delete the session cookie securely
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            true, true
        );
    }
    
    session_destroy();
    
    // Clear any remember me cookie
    setcookie('tesda_remember', '', time() - 42000, '/', null, true, true);
    
    // Clear any other cookies
    foreach ($_COOKIE as $name => $value) {
        setcookie($name, '', time() - 42000, '/', null, true, true);
    }
    
    session_start();
    $_SESSION['message'] = "Session cleared successfully.";
    
    header("Location: login.php");
    exit;
}

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: home-page.php");
    exit;
}

// Handle remember me token
if (isset($_COOKIE['tesda_remember']) && !isset($_SESSION['user_id'])) {
    global $pdo;
    // Use proper sanitization instead of deprecated function
    $token = sanitizeInput($_COOKIE['tesda_remember']);
    
    $sql = "SELECT user_id FROM remember_tokens 
            WHERE token = ? AND expires > NOW() AND is_valid = 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$token]);
    $result = $stmt->fetch();
    
    if ($result) {
        $sql = "SELECT * FROM admin_users WHERE id = ? AND is_active = 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$result['user_id']]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['admin_level'] = $user['admin_level'];
            
            // Get user permissions
            $permStmt = $pdo->prepare("SELECT * FROM admin_permissions WHERE admin_id = ?");
            $permStmt->execute([$user['id']]);
            $permissions = $permStmt->fetch();
            
            if ($permissions) {
                $_SESSION['permissions'] = $permissions;
            }
            
            // Rotate remember me token for security
            $newToken = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+30 days'));
            
            // Invalidate old token and create new one
            $pdo->beginTransaction();
            try {
                $invalidateStmt = $pdo->prepare("UPDATE remember_tokens SET is_valid = 0 WHERE token = ?");
                $invalidateStmt->execute([$token]);
                
                $insertStmt = $pdo->prepare("INSERT INTO remember_tokens (user_id, token, expires) VALUES (?, ?, ?)");
                $insertStmt->execute([$user['id'], $newToken, $expiry]);
                
                $pdo->commit();
                
                setcookie('tesda_remember', $newToken, time() + (86400 * 30), "/", 
                         null, true, true);
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("Token rotation failed: " . $e->getMessage());
            }
            
            header("Location: home-page.php");
            exit;
        }
    }
    // Invalid or expired token - clear it
    setcookie('tesda_remember', '', time() - 42000, '/', null, true, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip = $_SERVER['REMOTE_ADDR'];
    $browser = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    
    // Check rate limiting
    if (!checkRateLimit($ip)) {
        if (isset($_SESSION['wait_time'])) {
            $_SESSION['error'] = "Too many login attempts. Please try again after " . $_SESSION['wait_time'] . " seconds.";
        } else {
            $_SESSION['error'] = "Too many login attempts. Please try again later.";
        }
        header("Location: login.php");
        exit;
    }
    
    // Enhanced security - check for possible CSRF attack
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token'] ?? '') {
        $_SESSION['error'] = "Invalid form submission. Please try again.";
        logLoginAttempt($ip, 'unknown', false, 'CSRF token mismatch');
        header("Location: login.php");
        exit;
    }
    
    // Sanitize input using the new function instead of deprecated FILTER_SANITIZE_STRING
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']) ? true : false;

    if (empty($username) || empty($password)) {
        $_SESSION['error'] = "Please fill in all fields.";
        logLoginAttempt($ip, $username, false, 'Empty fields');
    } else {
        try {
            global $pdo;
            $sql = "SELECT * FROM admin_users WHERE username = ? AND is_active = 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Successful login
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['admin_level'] = $user['admin_level'];
                
                // Get user permissions
                $permStmt = $pdo->prepare("SELECT * FROM admin_permissions WHERE admin_id = ?");
                $permStmt->execute([$user['id']]);
                $permissions = $permStmt->fetch();
                
                if ($permissions) {
                    $_SESSION['permissions'] = $permissions;
                }
                
                // Clear failed attempts
                $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = ? AND success = 0")
                    ->execute([$ip]);
                
                // Handle remember me
                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    $expiry = date('Y-m-d H:i:s', strtotime('+30 days'));
                    
                    $pdo->prepare("INSERT INTO remember_tokens (user_id, token, expires) VALUES (?, ?, ?)")
                        ->execute([$user['id'], $token, $expiry]);
                    
                    setcookie('tesda_remember', $token, time() + (86400 * 30), "/", 
                         null, true, true);
                }
                
                // Log successful login
                logLoginAttempt($ip, $username, true, 'Login successful');
                
                // Log activity
                $pdo->prepare("INSERT INTO activity_logs (user_id, action, ip_address) VALUES (?, 'login', ?)")
                    ->execute([$user['id'], $ip]);
                
                header("Location: home-page.php");
                exit;
            } else {
                $_SESSION['error'] = "Invalid username or password.";
                logLoginAttempt($ip, $username, false, 'Invalid credentials');
                usleep(random_int(100000, 500000)); // Random delay between 0.1-0.5 seconds
            }
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            $_SESSION['error'] = "An error occurred. Please try again later.";
        }
    }
}

// Generate CSRF token for form security
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | TESDA Portal</title>
    <link rel="icon" href="logoT.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --tesda-blue: #0f52ba;
            --tesda-dark-blue: #083a87;
            --tesda-light-blue: #3b82f6;
        }
        
        .bg-tesda-gradient {
            background: linear-gradient(135deg, var(--tesda-blue) 0%, var(--tesda-light-blue) 100%);
        }
        
        @keyframes fadeIn {
            0% { opacity: 0; transform: translateY(-10px); }
            100% { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fadeIn {
            animation: fadeIn 0.5s ease-out forwards;
        }
        
        .password-toggle {
            cursor: pointer;
        }
        
        /* Add animation for login button */
        .btn-animate {
            transition: all 0.3s ease;
        }
        
        .btn-animate:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        /* Custom focus styles */
        .custom-focus:focus {
            outline: none;
            border-color: var(--tesda-light-blue);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3);
        }
    </style>
</head>

<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="bg-white rounded-lg shadow-xl overflow-hidden animate-fadeIn">
            <div class="bg-tesda-gradient p-6 text-white text-center">
                <div class="w-24 h-24 bg-white rounded-full mx-auto mb-4 p-2 shadow-lg">
                    <img src="Tesda-logo.jpg" alt="TESDA Logo" class="w-full h-full object-contain rounded-full">
                </div>
                <h2 class="text-2xl font-bold">Admin Login</h2>
                <p class="text-blue-100 text-sm">Technical Education and Skills Development Authority</p>
            </div>

            <div class="p-8">
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="bg-blue-100 text-blue-700 p-3 rounded-md mb-4 text-center">
                        <?php 
                            echo htmlspecialchars($_SESSION['message']); 
                            unset($_SESSION['message']);
                        ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="bg-red-100 text-red-700 p-3 rounded-md mb-4 text-center">
                        <?php 
                            echo htmlspecialchars($_SESSION['error']); 
                            unset($_SESSION['error']);
                        ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['wait_time'])): ?>
                    <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6">
                        <p class="font-medium">Login attempts limit reached</p>
                        <p class="text-sm mt-1">Please wait <?php echo $_SESSION['wait_time']; ?> seconds before trying again.</p>
                    </div>
                <?php endif; ?>

                <form action="login.php" method="POST" class="space-y-5" id="loginForm">
                    <!-- CSRF Token for security -->
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <input type="text" id="username" name="username" 
                                   class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm custom-focus"
                                   required autocomplete="username" autofocus placeholder="Enter your username">
                        </div>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <input type="password" id="password" name="password"
                                   class="block w-full pl-10 pr-10 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm custom-focus"
                                   required autocomplete="current-password" placeholder="Enter your password">
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400 password-toggle" viewBox="0 0 20 20" fill="currentColor" onclick="togglePassword()">
                                    <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                    <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <input type="checkbox" id="remember" name="remember" 
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="remember" class="ml-2 block text-sm text-gray-900">
                                Remember me
                            </label>
                        </div>
                    </div>

                    <div>
                        <button type="submit" 
                                class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 btn-animate">
                            Sign in
                        </button>
                    </div>
                </form>
                
                <!-- Sign up link -->
                <div class="mt-6 text-center">
                    <p class="text-sm text-gray-600">
                        Don't have an account? 
                        <a href="register.php" class="font-medium text-blue-600 hover:text-blue-500">
                            Sign up
                        </a>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Copyright and version info -->
        <div class="mt-4 text-center text-xs text-gray-500">
            <p> 2023 TESDA Admin Portal • v1.0.2</p>
            <p class="mt-1">Technical Education and Skills Development Authority</p>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.querySelector('.password-toggle');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.innerHTML = `
                    <path fill-rule="evenodd" d="M3.707 2.293a1 1 0 00-1.414 1.414l14 14a1 1 0 001.414-1.414l-1.473-1.473A10.014 10.014 0 0019.542 10C18.268 5.943 14.478 3 10 3a9.958 9.958 0 00-4.512 1.074l-1.78-1.781zm4.261 4.26l1.514 1.515a2.003 2.003 0 012.45 2.45l1.514 1.514a4 4 0 00-5.478-5.478z" clip-rule="evenodd" />
                    <path d="M12.454 16.697L9.75 13.992a4 4 0 01-3.742-3.741L2.335 6.578A9.98 9.98 0 00.458 10c1.274 4.057 5.065 7 9.542 7 .847 0 1.669-.105 2.454-.303z" />
                `;
            } else {
                passwordInput.type = 'password';
                toggleIcon.innerHTML = `
                    <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                    <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                `;
            }
        }

        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
        
        // Focus username field on page load
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('username').focus();
        });
    </script>
    
    <!-- SweetAlert2 for more attractive notifications -->
    <?php if (isset($_SESSION['error'])): ?>
    <script>
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: <?php echo json_encode($_SESSION['error']); ?>,
            confirmButtonColor: '#3B82F6'
        });
    </script>
    <?php endif; ?>
</body>
</html>
