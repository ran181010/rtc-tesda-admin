<?php
// Start session
session_start();

// Force clear all session data regardless of URL parameter
if (isset($_GET['clear_session']) && $_GET['clear_session'] == 1) {
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
    
    // Destroy the session
    session_destroy();
    
    // Also clear any remember me cookie
    if (isset($_COOKIE['tesda_remember'])) {
        setcookie('tesda_remember', '', time() - 42000, '/');
    }
    
    // Clear any other cookies for this domain
    foreach ($_COOKIE as $name => $value) {
        setcookie($name, '', time() - 42000, '/');
    }
    
    // Restart session
    session_start();
    $_SESSION['message'] = "Session cleared. You can now register.";
    
    // Redirect to clean URL to avoid resubmission
    header("Location: register.php");
    exit;
}

include 'db.php'; // Include your database connection

// Check if user is already logged in - but only after the potential session clearing
if (isset($_SESSION['user_id'])) {
    header("Location: home-page.php");
    exit;
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid form submission.";
    } else {
        $username = trim($_POST['new-username']);
        $password = $_POST['new-password'];
        $confirmPassword = $_POST['confirm-password'];
        $fullName = trim($_POST['full-name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $adminLevel = 'staff'; // Default admin level for new registrations

        if (empty($username) || empty($password) || empty($confirmPassword)) {
            $error = "Please fill in all required fields!";
        } elseif ($password !== $confirmPassword) {
            $error = "Passwords do not match!";
        } elseif (strlen($password) < 8) {
            $error = "Password must be at least 8 characters!";
        } elseif (!preg_match('/[A-Z]/', $password)) {
            $error = "Password must contain at least one uppercase letter!";
        } elseif (!preg_match('/[0-9]/', $password)) {
            $error = "Password must contain at least one number!";
        } elseif (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $error = "Password must contain at least one special character!";
        } else {
            // Check if username already exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_users WHERE username = :username");
            $stmt->execute(['username' => $username]);
            $userCount = $stmt->fetchColumn();

            if ($userCount > 0) {
                $error = "Username already exists!";
            } else {
                // Check if email already exists (if email field is present)
                if (!empty($email)) {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_users WHERE email = :email");
                    $stmt->execute(['email' => $email]);
                    $emailCount = $stmt->fetchColumn();
                    
                    if ($emailCount > 0) {
                        $error = "Email address already in use!";
                    }
                }
                
                if (empty($error)) {
                    // Register the user with bcrypt hashing (cost factor 12)
                    $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                    
                    try {
                        // Begin transaction to ensure both user and permissions are created
                        $pdo->beginTransaction();
                        
                        // First check if required tables exist
                        $stmt = $pdo->query("SHOW TABLES LIKE 'admin_users'");
                        $adminUsersExists = $stmt->rowCount() > 0;
                        
                        $stmt = $pdo->query("SHOW TABLES LIKE 'admin_permissions'");
                        $adminPermissionsExists = $stmt->rowCount() > 0;
                        
                        if (!$adminUsersExists || !$adminPermissionsExists) {
                            $pdo->rollBack();
                            $error = "Database setup incomplete. Please run the setup_database.php script first.";
                        } else {
                            $stmt = $pdo->prepare("INSERT INTO admin_users (username, password, full_name, email, admin_level, created_at) 
                                                 VALUES (:username, :password, :full_name, :email, :admin_level, NOW())");
                            $result = $stmt->execute([
                                'username' => $username, 
                                'password' => $hashedPassword,
                                'full_name' => $fullName,
                                'email' => $email,
                                'admin_level' => $adminLevel
                            ]);
                            
                            if ($result) {
                                $userId = $pdo->lastInsertId();
                                
                                // Create default permissions for this user
                                $permStmt = $pdo->prepare("INSERT INTO admin_permissions 
                                    (admin_id, can_manage_enrollees, can_export_data, can_view_reports) 
                                    VALUES (:admin_id, 1, 1, 1)");
                                $permResult = $permStmt->execute(['admin_id' => $userId]);
                                
                                if ($permResult) {
                                    // Check if user_logs table exists before logging
                                    $stmt = $pdo->query("SHOW TABLES LIKE 'user_logs'");
                                    $userLogsExists = $stmt->rowCount() > 0;
                                    
                                    // Only log if the table exists
                                    if ($userLogsExists) {
                                        $ipAddress = $_SERVER['REMOTE_ADDR'];
                                        $registrationLog = $pdo->prepare("INSERT INTO user_logs (user_id, action, ip_address, created_at) 
                                                                        VALUES (:user_id, 'registration', :ip, NOW())");
                                        $registrationLog->execute([
                                            'user_id' => $userId,
                                            'ip' => $ipAddress
                                        ]);
                                    }
                                    
                                    $pdo->commit();
                                    $success = true;
                                } else {
                                    $pdo->rollBack();
                                    $error = "Failed to set user permissions. Please try again.";
                                }
                            } else {
                                $pdo->rollBack();
                                $error = "Failed to register user. Please try again.";
                            }
                        }
                    } catch (PDOException $e) {
                        $pdo->rollBack();
                        
                        // Check if error relates to missing columns
                        if (strpos($e->getMessage(), "Unknown column") !== false || 
                            strpos($e->getMessage(), "Table") !== false) {
                            $error = "Database setup incomplete. Please run the setup_database.php script first.";
                        } else {
                            $error = "Database error: " . $e->getMessage();
                        }
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin Sign Up | TESDA Portal</title>
  <link rel="icon" href="logoT.png" />
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    .bg-tesda-gradient {
      background: linear-gradient(135deg, #0f52ba 0%, #2563eb 100%);
    }
    .animate-fade-in {
      animation: fadeIn 0.5s ease-in-out;
    }
    @keyframes fadeIn {
      0% { opacity: 0; transform: translateY(-10px); }
      100% { opacity: 1; transform: translateY(0); }
    }
    .password-strength-meter {
      height: 5px;
      border-radius: 5px;
      margin-top: 5px;
      transition: all 0.3s ease;
    }
    .strength-weak { width: 25%; background-color: #ef4444; }
    .strength-medium { width: 50%; background-color: #f59e0b; }
    .strength-good { width: 75%; background-color: #10b981; }
    .strength-strong { width: 100%; background-color: #10b981; }
  </style>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
  <div class="w-full max-w-md">
    <!-- Registration Card -->
    <div class="bg-white rounded-lg shadow-xl overflow-hidden animate-fade-in">
      <!-- Header -->
      <div class="bg-tesda-gradient p-6 text-white text-center">
        <div class="w-24 h-24 bg-white rounded-full mx-auto mb-4 p-2 shadow-lg">
          <img src="Tesda-logo.jpg" alt="TESDA Logo" class="w-full h-full object-contain rounded-full" />
        </div>
        <h2 class="text-2xl font-bold">Admin Registration</h2>
        <p class="text-blue-100 text-sm">Create your account to access the admin panel</p>
      </div>

      <!-- Form Container -->
      <div class="p-8">
        <!-- Show message -->
        <?php if (isset($_SESSION['message'])): ?>
          <div class="bg-blue-100 text-blue-700 p-3 rounded-md mb-4 text-center">
            <?php echo htmlspecialchars($_SESSION['message']); ?>
            <?php unset($_SESSION['message']); ?>
          </div>
        <?php endif; ?>
        
        <!-- Show error -->
        <?php if (!empty($error)): ?>
          <div class="bg-red-100 text-red-700 p-3 rounded-md mb-4 text-center">
            <?php echo htmlspecialchars($error); ?>
          </div>
        <?php endif; ?>

        <!-- Show success -->
        <?php if ($success): ?>
          <div class="bg-green-100 text-green-700 p-5 rounded-md mb-4 text-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 mx-auto mb-2 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <h3 class="text-lg font-semibold mb-1">Registration Successful!</h3>
            <p>Your account has been created. You can now sign in.</p>
            <a href="login.php" class="inline-block mt-3 px-5 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition">Go to Login</a>
          </div>
        <?php else: ?>
          <!-- Registration Form -->
          <form action="register.php" method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            
            <div>
              <label for="full-name" class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
              <input 
                type="text" 
                id="full-name" 
                name="full-name" 
                placeholder="Enter your full name"
                class="w-full p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
              />
            </div>
            
            <div>
              <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
              <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                  </svg>
                </div>
                <input 
                  type="email" 
                  id="email" 
                  name="email" 
                  placeholder="Enter your email address"
                  class="w-full pl-10 p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                />
              </div>
            </div>

            <div>
              <label for="new-username" class="block text-sm font-medium text-gray-700 mb-1">Username <span class="text-red-500">*</span></label>
              <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" />
                  </svg>
                </div>
                <input 
                  type="text" 
                  id="new-username" 
                  name="new-username" 
                  placeholder="Choose a username"
                  class="w-full pl-10 p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                  required 
                />
              </div>
            </div>

            <div>
              <label for="new-password" class="block text-sm font-medium text-gray-700 mb-1">Password <span class="text-red-500">*</span></label>
              <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                  </svg>
                </div>
                <input 
                  type="password" 
                  id="new-password" 
                  name="new-password" 
                  placeholder="Create a secure password"
                  class="w-full pl-10 pr-10 p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                  required 
                  minlength="8"
                  onkeyup="checkPasswordStrength(this.value)"
                />
                <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                  <button type="button" id="toggleNewPassword" class="text-gray-400 hover:text-gray-600 focus:outline-none">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                      <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                      <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                    </svg>
                  </button>
                </div>
              </div>
              <div class="password-strength-container mt-2">
                <div class="password-strength-meter bg-gray-200"></div>
                <p id="password-strength-text" class="text-xs mt-1 text-gray-600">Password must be at least 8 characters with numbers, uppercase and special characters</p>
              </div>
            </div>

            <div>
              <label for="confirm-password" class="block text-sm font-medium text-gray-700 mb-1">Confirm Password <span class="text-red-500">*</span></label>
              <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                  </svg>
                </div>
                <input 
                  type="password" 
                  id="confirm-password" 
                  name="confirm-password" 
                  placeholder="Confirm your password"
                  class="w-full pl-10 pr-10 p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                  required 
                  onkeyup="checkPasswordMatch()"
                />
                <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                  <button type="button" id="toggleConfirmPassword" class="text-gray-400 hover:text-gray-600 focus:outline-none">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                      <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                      <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                    </svg>
                  </button>
                </div>
              </div>
              <p id="password-match" class="text-xs mt-1 hidden text-red-600">Passwords do not match</p>
            </div>

            <div class="flex items-center">
              <input 
                type="checkbox" 
                id="agree-terms" 
                name="agree-terms" 
                class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded" 
                required
              />
              <label for="agree-terms" class="ml-2 block text-sm text-gray-700">
                I agree to the <a href="#" class="text-blue-600 hover:underline">Terms of Service</a> and <a href="#" class="text-blue-600 hover:underline">Privacy Policy</a>
              </label>
            </div>

            <button 
              type="submit" 
              id="register-button"
              class="w-full bg-blue-600 text-white py-3 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
            >
              Create Account
            </button>
          </form>
        <?php endif; ?>

        <div class="mt-6 text-center">
          <p class="text-sm text-gray-600">
            Already have an account? 
            <a href="login.php" class="font-medium text-blue-600 hover:text-blue-800">
              Sign in instead
            </a>
          </p>
        </div>
      </div>
    </div>
    
    <!-- Footer -->
    <div class="text-center mt-6">
      <p class="text-xs text-gray-500"> 2024 TESDA Admin Portal. All rights reserved.</p>
    </div>
  </div>

  <!-- JavaScript for password strength and toggle -->
  <script>
    // Toggle password visibility
    document.getElementById('toggleNewPassword').addEventListener('click', function() {
      const passwordInput = document.getElementById('new-password');
      const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
      passwordInput.setAttribute('type', type);
      
      // Change the eye icon
      this.innerHTML = type === 'text' 
        ? '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3.707 2.293a1 1 0 00-1.414 1.414l14 14a1 1 0 001.414-1.414l-1.473-1.473A10.014 10.014 0 0019.542 10C18.268 5.943 14.478 3 10 3a9.958 9.958 0 00-4.512 1.074l-1.78-1.781zm4.261 4.26l1.514 1.515a2.003 2.003 0 012.45 2.45l1.514 1.514a4 4 0 00-5.478-5.478z" clip-rule="evenodd" /><path d="M12.454 16.697L9.75 13.992a4 4 0 01-3.742-3.741L2.335 6.578A9.98 9.98 0 00.458 10c1.274 4.057 5.065 7 9.542 7 .847 0 1.669-.105 2.454-.303z" /></svg>'
        : '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M10 12a2 2 0 100-4 2 2 0 000 4z" /><path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" /></svg>';
    });
    
    document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
      const passwordInput = document.getElementById('confirm-password');
      const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
      passwordInput.setAttribute('type', type);
      
      // Change the eye icon
      this.innerHTML = type === 'text' 
        ? '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3.707 2.293a1 1 0 00-1.414 1.414l14 14a1 1 0 001.414-1.414l-1.473-1.473A10.014 10.014 0 0019.542 10C18.268 5.943 14.478 3 10 3a9.958 9.958 0 00-4.512 1.074l-1.78-1.781zm4.261 4.26l1.514 1.515a2.003 2.003 0 012.45 2.45l1.514 1.514a4 4 0 00-5.478-5.478z" clip-rule="evenodd" /><path d="M12.454 16.697L9.75 13.992a4 4 0 01-3.742-3.741L2.335 6.578A9.98 9.98 0 00.458 10c1.274 4.057 5.065 7 9.542 7 .847 0 1.669-.105 2.454-.303z" /></svg>'
        : '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M10 12a2 2 0 100-4 2 2 0 000 4z" /><path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" /></svg>';
    });
    
    // Check password strength
    function checkPasswordStrength(password) {
      const meter = document.querySelector('.password-strength-meter');
      const strengthText = document.getElementById('password-strength-text');
      
      // Remove all classes
      meter.classList.remove('strength-weak', 'strength-medium', 'strength-good', 'strength-strong');
      
      // Calculate strength
      let strength = 0;
      
      if (password.length >= 8) strength += 1;
      if (password.match(/[A-Z]/)) strength += 1;
      if (password.match(/[0-9]/)) strength += 1;
      if (password.match(/[^A-Za-z0-9]/)) strength += 1;
      
      // Set meter class and text based on strength
      switch(strength) {
        case 0:
          meter.classList.add('strength-weak');
          strengthText.textContent = 'Password is too weak';
          strengthText.className = 'text-xs mt-1 text-red-600';
          break;
        case 1:
          meter.classList.add('strength-weak');
          strengthText.textContent = 'Password is weak';
          strengthText.className = 'text-xs mt-1 text-red-600';
          break;
        case 2:
          meter.classList.add('strength-medium');
          strengthText.textContent = 'Password strength: Medium';
          strengthText.className = 'text-xs mt-1 text-yellow-600';
          break;
        case 3:
          meter.classList.add('strength-good');
          strengthText.textContent = 'Password strength: Good';
          strengthText.className = 'text-xs mt-1 text-green-600';
          break;
        case 4:
          meter.classList.add('strength-strong');
          strengthText.textContent = 'Password strength: Strong';
          strengthText.className = 'text-xs mt-1 text-green-600';
          break;
      }
      
      // Also check password match
      checkPasswordMatch();
    }
    
    // Check if passwords match
    function checkPasswordMatch() {
      const password = document.getElementById('new-password').value;
      const confirmPassword = document.getElementById('confirm-password').value;
      const matchText = document.getElementById('password-match');
      
      if (confirmPassword.length > 0) {
        if (password === confirmPassword) {
          matchText.className = 'text-xs mt-1 text-green-600';
          matchText.textContent = 'Passwords match';
          matchText.style.display = 'block';
        } else {
          matchText.className = 'text-xs mt-1 text-red-600';
          matchText.textContent = 'Passwords do not match';
          matchText.style.display = 'block';
        }
      } else {
        matchText.style.display = 'none';
      }
    }
  </script>
</body>
</html>
