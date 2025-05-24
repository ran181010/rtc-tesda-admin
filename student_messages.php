<?php
session_start();
require 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle new message submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send') {
    // Verify CSRF token
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid CSRF token.";
        header("Location: student_messages.php");
        exit;
    }
    
    $student_id = intval($_POST['student_id']);
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    $priority = isset($_POST['priority']) ? $_POST['priority'] : 'medium';
    
    if (empty($student_id) || empty($subject) || empty($message)) {
        $_SESSION['error'] = "All fields are required.";
    } else {
        try {
            // Insert message
            $stmt = $pdo->prepare("
                INSERT INTO messages 
                (sender_id, receiver_id, sender_type, receiver_type, subject, message, priority, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$_SESSION['user_id'], $student_id, 'admin', 'student', $subject, $message, $priority]);
            $message_id = $pdo->lastInsertId();
            
            // Handle file attachment if present
            if (isset($_FILES['attachment']) && $_FILES['attachment']['size'] > 0) {
                $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'image/jpeg', 'image/png'];
                $max_size = 5 * 1024 * 1024; // 5MB
                
                if ($_FILES['attachment']['size'] <= $max_size && in_array($_FILES['attachment']['type'], $allowed_types)) {
                    // Create attachments directory if it doesn't exist
                    if (!file_exists('attachments')) {
                        mkdir('attachments', 0755, true);
                    }
                    
                    $file_ext = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
                    $file_name = 'msg_' . $message_id . '_' . time() . '.' . $file_ext;
                    $file_path = 'attachments/' . $file_name;
                    
                    if (move_uploaded_file($_FILES['attachment']['tmp_name'], $file_path)) {
                        // Update message with attachment info
                        $stmt = $pdo->prepare("
                            UPDATE messages
                            SET attachment = ?, attachment_name = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([$file_path, $_FILES['attachment']['name'], $message_id]);
                    }
                }
            }
            
            $_SESSION['success'] = "Message sent successfully.";
            header("Location: student_messages.php?student_id=" . $student_id);
            exit;
        } catch (PDOException $e) {
            $_SESSION['error'] = "Database error: " . $e->getMessage();
        }
    }
}

// Get list of students for dropdown
$stmt = $pdo->query("SELECT id, name, email, course FROM enrollees WHERE status = 'approved' ORDER BY name");
$students = $stmt->fetchAll();

// Get conversation with a specific student if selected
$conversation = [];
$student_info = null;
if (isset($_GET['student_id'])) {
    $student_id = intval($_GET['student_id']);
    
    // Get student info
    $stmt = $pdo->prepare("SELECT id, name, email, course FROM enrollees WHERE id = ?");
    $stmt->execute([$student_id]);
    $student_info = $stmt->fetch();
    
    if ($student_info) {
        // Get messages between admin and this student
        $stmt = $pdo->prepare("
            SELECT 
                m.*,
                CASE 
                    WHEN m.sender_type = 'admin' THEN a.username
                    ELSE e.name
                END as sender_name
            FROM messages m
            LEFT JOIN admin_users a ON m.sender_id = a.id AND m.sender_type = 'admin'
            LEFT JOIN enrollees e ON m.sender_id = e.id AND m.sender_type = 'student'
            WHERE 
                (m.sender_id = ? AND m.sender_type = 'student' AND m.receiver_id = ? AND m.receiver_type = 'admin')
                OR
                (m.sender_id = ? AND m.sender_type = 'admin' AND m.receiver_id = ? AND m.receiver_type = 'student')
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$student_id, $_SESSION['user_id'], $_SESSION['user_id'], $student_id]);
        $conversation = $stmt->fetchAll();
        
        // Mark messages as read
        $stmt = $pdo->prepare("
            UPDATE messages
            SET is_read = 1, read_at = NOW()
            WHERE sender_id = ? AND sender_type = 'student'
            AND receiver_id = ? AND receiver_type = 'admin'
            AND is_read = 0
        ");
        $stmt->execute([$student_id, $_SESSION['user_id']]);
    }
}

// Get list of students with unread messages
$stmt = $pdo->prepare("
    SELECT 
        e.id, e.name, e.email, e.course,
        COUNT(m.id) as unread_count
    FROM enrollees e
    JOIN messages m ON e.id = m.sender_id AND m.sender_type = 'student'
    WHERE m.receiver_id = ? AND m.receiver_type = 'admin' AND m.is_read = 0
    GROUP BY e.id
    ORDER BY unread_count DESC
");
$stmt->execute([$_SESSION['user_id']]);
$unread_messages = $stmt->fetchAll();

// Add message templates
$message_templates = [
    'welcome' => [
        'subject' => 'Welcome to TESDA Training Program',
        'message' => 'Dear {student_name},\n\nWelcome to our training program. We are excited to have you join us.\n\nBest regards,\nTESDA Admin'
    ],
    'reminder' => [
        'subject' => 'Important Reminder: {course_name}',
        'message' => 'Dear {student_name},\n\nThis is a reminder about your upcoming {course_name} training.\n\nBest regards,\nTESDA Admin'
    ],
    'certificate' => [
        'subject' => 'Your TESDA Certificate is Ready',
        'message' => 'Dear {student_name},\n\nCongratulations! Your TESDA certificate is now ready for collection.\n\nBest regards,\nTESDA Admin'
    ]
];

// Add message categories
$message_categories = [
    'general' => 'General Information',
    'course' => 'Course Related',
    'certificate' => 'Certificate Related',
    'payment' => 'Payment Related',
    'schedule' => 'Schedule Related'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Messages - TESDA Admin</title>
    <link rel="icon" href="logoT.png">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .message-container {
            height: calc(100vh - 380px);
            min-height: 300px;
        }
        
        /* Message priority indicators */
        .priority-high {
            border-left: 4px solid #ef4444;
        }
        
        .priority-medium {
            border-left: 4px solid #f59e0b;
        }
        
        .priority-low {
            border-left: 4px solid #10b981;
        }
        
        /* Improve readability and spacing */
        .message-bubble {
            position: relative;
            max-width: 85%;
            word-break: break-word;
        }
        
        /* Animation for new messages */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .new-message {
            animation: fadeIn 0.3s ease-out forwards;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="flex min-h-screen">
         <!-- Sidebar -->
    <aside class="w-64 bg-blue-900 dark:bg-slate-900 text-white p-4 flex flex-col">
      <div class="flex items-center justify-center mb-6">
        <img src="Tesda-logo.jpg" alt="TESDA Logo" class="h-28 w-28 rounded-full object-cover" />
      </div>
      <h1 class="text-2xl font-bold text-center mb-6">TESDA Admin</h1>
      <ul class="flex-1 space-y-4">
        <li>
          <a href="home-page.php" class="flex items-center gap-3 p-2 bg-blue-700 dark:bg-blue-900 rounded transition-all hover:bg-blue-600 dark:hover:bg-blue-800">
            <!-- Home icon -->
            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1h2a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1h2" />
            </svg>
            Home
          </a>
        </li>
        <li>
          <a href="manage-enrollment.php" class="flex items-center gap-3 p-2 hover:bg-blue-700 dark:hover:bg-blue-800 rounded transition-all">
            <!-- Enrollment icon -->
            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
            </svg>
            Manage Enrollment
          </a>
        </li>
        <li>
          <a href="communications.php" class="flex items-center gap-3 p-2 hover:bg-blue-700 dark:hover:bg-blue-800 rounded transition-all">
            <!-- Communications icon -->
            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" />
            </svg>
            Communications
          </a>
        </li>
        <li>
          <a href="student_messages.php" class="flex items-center gap-3 p-2 hover:bg-blue-700 dark:hover:bg-blue-800 rounded transition-all">
            <!-- Messages icon -->
            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
            </svg>
            Student Messages
            <?php
            // Get unread message count
            $unread_count = 0;
            try {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as count
                    FROM messages
                    WHERE receiver_id = ? 
                    AND receiver_type = 'admin'
                    AND is_read = 0
                ");
                $stmt->execute([$_SESSION['user_id']]);
                $unread_count = $stmt->fetch()['count'];
            } catch (PDOException $e) {
                // Table might not exist yet
                $unread_count = 0;
            }
            
            if ($unread_count > 0):
            ?>
              <span class="ml-auto bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                <?php echo $unread_count; ?>
              </span>
            <?php endif; ?>
          </a>
        </li>
        <li>
          <a href="list-graduates.php" class="flex items-center gap-3 p-2 hover:bg-blue-700 dark:hover:bg-blue-800 rounded transition-all">
            <!-- Graduates icon -->
            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path d="M12 14l9-5-9-5-9 5 9 5z" />
              <path d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998a12.078 12.078 0 01.665-6.479L12 14z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998a12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222" />
            </svg>
            Graduates
          </a>
        </li>
        <li>
          <a href="post-courses.php" class="flex items-center gap-3 p-2 hover:bg-blue-700 dark:hover:bg-blue-800 rounded transition-all">
            <!-- Courses icon -->
            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
            </svg>
            Courses
          </a>
        </li>
      </ul>
    </aside>
           
        <!-- Main Content -->
        <main class="flex-1 overflow-y-auto">
            <!-- Top navigation bar with user profile -->
            <div class="bg-card shadow-sm sticky top-0 z-10 transition-colors">
                <div class="max-w-full mx-auto px-6 py-3">
                    <div class="flex justify-between items-center">
                        <h2 class="text-3xl font-bold text-blue-900 dark:text-blue-300">Student Messages</h2>
                        
                        <div class="flex items-center space-x-4">
                            <div class="text-sm text-secondary">
                                <?php echo date('l, F j, Y'); ?>
                            </div>
                            <div class="flex items-center space-x-4">
                                <!-- Notification Icons -->
                                <div class="flex items-center space-x-2">
                                    <?php
                                    // Get unread message count
                                    $unread_count = 0;
                                    try {
                                        $stmt = $pdo->prepare("
                                            SELECT COUNT(*) as count
                                            FROM messages
                                            WHERE receiver_id = ? 
                                            AND receiver_type = 'admin'
                                            AND is_read = 0
                                        ");
                                        $stmt->execute([$_SESSION['user_id']]);
                                        $unread_count = $stmt->fetch()['count'];
                                    } catch (PDOException $e) {
                                        // Table might not exist yet
                                        $unread_count = 0;
                                    }
                                    
                                    if ($unread_count > 0):
                                    ?>
                                        <a href="student_messages.php" class="relative text-gray-600 hover:text-blue-600">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                                            </svg>
                                            <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                                                <?php echo $unread_count; ?>
                                            </span>
                                        </a>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Dark Mode Toggle -->
                                <div id="darkModeToggle" class="dark-mode-toggle">
                                    <div class="toggle-circle">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                            <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z" />
                                        </svg>
                                    </div>
                                </div>
                                
                                <!-- User dropdown -->
                                <div class="relative inline-block text-left">
                                    <div class="flex items-center space-x-3 cursor-pointer group">
                                        <div class="bg-blue-700 p-2 rounded-full">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="font-semibold"><?php echo htmlspecialchars($_SESSION['username']); ?></p>
                                            <p class="text-xs text-secondary"><?php echo isset($_SESSION['admin_level']) ? ucfirst($_SESSION['admin_level']) : 'Admin'; ?></p>
                                        </div>
                                        <a href="logout.php" class="bg-red-600 hover:bg-red-700 text-white py-1 px-3 rounded-md text-sm transition-colors">
                                            Logout
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Main Content Area -->
            <div class="container mx-auto px-6 py-8">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <!-- Left Sidebar - Student List -->
                    <div class="md:col-span-1">
                        <div class="bg-white rounded-lg shadow-md p-4">
                            <h2 class="text-lg font-semibold text-gray-800 mb-4">Students</h2>
                            
                            <!-- Search Box -->
                            <div class="mb-4">
                                <div class="relative">
                                    <input id="student-search" type="text" placeholder="Search students..." 
                                           class="w-full pl-10 pr-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <div class="absolute left-3 top-2.5 text-gray-400">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (!empty($unread_messages)): ?>
                                <div class="flex items-center justify-between mb-2">
                                    <h3 class="text-sm font-medium text-gray-700">Unread Messages</h3>
                                    <button id="mark-all-read" class="text-xs text-blue-600 hover:text-blue-800">
                                        Mark all as read
                                    </button>
                                </div>
                                <ul class="mb-6 space-y-2 student-list">
                                    <?php foreach ($unread_messages as $student): ?>
                                        <li class="student-item" data-name="<?php echo strtolower(htmlspecialchars($student['name'])); ?>" data-course="<?php echo strtolower(htmlspecialchars($student['course'])); ?>">
                                            <a href="student_messages.php?student_id=<?php echo $student['id']; ?>" 
                                               class="flex items-center justify-between p-2 rounded hover:bg-blue-50 <?php echo (isset($_GET['student_id']) && $_GET['student_id'] == $student['id']) ? 'bg-blue-50' : ''; ?>">
                                                <div>
                                                    <div class="font-medium text-gray-800"><?php echo htmlspecialchars($student['name']); ?></div>
                                                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($student['course']); ?></div>
                                                </div>
                                                <span class="bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                                                    <?php echo $student['unread_count']; ?>
                                                </span>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            
                            <h3 class="text-sm font-medium text-gray-700 mb-2">All Students</h3>
                            <select id="student-select" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50 mb-4">
                                <option value="">Select a student</option>
                                <?php foreach ($students as $student): ?>
                                    <option value="<?php echo $student['id']; ?>" <?php echo (isset($_GET['student_id']) && $_GET['student_id'] == $student['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($student['name']); ?> - <?php echo htmlspecialchars($student['course']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Right Side - Message Area -->
                    <div class="md:col-span-3">
                        <div class="bg-white rounded-lg shadow-md">
                            <?php if ($student_info): ?>
                                <!-- Conversation Header -->
                                <div class="border-b p-4">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h2 class="text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($student_info['name']); ?></h2>
                                            <p class="text-sm text-gray-500">
                                                <?php echo htmlspecialchars($student_info['email']); ?> | 
                                                <?php echo htmlspecialchars($student_info['course']); ?>
                                            </p>
                                        </div>
                                        <div>
                                            <a href="manage-enrollment.php?action=view&id=<?php echo $student_info['id']; ?>" class="text-sm text-blue-600 hover:text-blue-800">
                                                View Student Profile
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Message History -->
                                <div class="p-4 message-container overflow-y-auto">
                                    <?php if (empty($conversation)): ?>
                                        <div class="text-center py-8 text-gray-500">
                                            <p>No messages yet.</p>
                                            <p class="mt-1">Start the conversation by sending a message below.</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="space-y-4">
                                            <?php foreach ($conversation as $msg): ?>
                                                <div class="flex <?php echo $msg['sender_type'] == 'admin' ? 'justify-end' : 'justify-start'; ?>">
                                                    <div class="message-bubble rounded-lg p-3 <?php echo $msg['sender_type'] == 'admin' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800 priority-' . $msg['priority']; ?>">
                                                        <div class="font-medium">
                                                            <?php echo $msg['sender_type'] == 'admin' ? 'You' : htmlspecialchars($msg['sender_name']); ?>
                                                        </div>
                                                        <div class="text-sm mb-1">
                                                            <strong>Subject:</strong> <?php echo htmlspecialchars($msg['subject']); ?>
                                                        </div>
                                                        <div>
                                                            <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                                                        </div>
                                                        <?php if (isset($msg['attachment']) && !empty($msg['attachment'])): ?>
                                                            <div class="mt-2 p-2 bg-gray-50 rounded">
                                                                <a href="<?php echo htmlspecialchars($msg['attachment']); ?>" target="_blank" class="flex items-center text-blue-600 hover:text-blue-800">
                                                                    <!-- Attachment icon -->
                                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                                                    </svg>
                                                                    <?php echo htmlspecialchars($msg['attachment_name'] ?? basename($msg['attachment'])); ?>
                                                                </a>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div class="text-xs text-gray-500 mt-1 text-right">
                                                            <?php echo date('M d, Y g:i A', strtotime($msg['created_at'])); ?>
                                                            <?php if ($msg['is_read'] && $msg['sender_type'] == 'admin'): ?>
                                                                <span class="inline-flex items-center ml-1">
                                                                    • Read <span class="ml-1 text-blue-600">
                                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                                                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                                                        </svg>
                                                                    </span>
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Message Form -->
                                <div class="border-t p-4">
                                    <form action="student_messages.php" method="POST" enctype="multipart/form-data">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <input type="hidden" name="action" value="send">
                                        <input type="hidden" name="student_id" value="<?php echo $student_info['id']; ?>">
                                        
                                        <!-- Templates Dropdown -->
                                        <div class="mb-3">
                                            <label for="template" class="block text-sm font-medium text-gray-700 mb-1">Message Template</label>
                                            <select id="template-select" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                                <option value="">Select a template</option>
                                                <option value="reminder">Payment Reminder</option>
                                                <option value="assessment">Assessment Schedule</option>
                                                <option value="congratulations">Congratulations</option>
                                                <option value="documents">Required Documents</option>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="subject" class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                                            <input type="text" id="subject" name="subject" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="message" class="block text-sm font-medium text-gray-700 mb-1">Message</label>
                                            <textarea id="message" name="message" rows="4" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" required></textarea>
                                        </div>
                                        
                                        <div class="mb-4">
                                            <label for="attachment" class="block text-sm font-medium text-gray-700 mb-1">Attachment (optional)</label>
                                            <input type="file" id="attachment" name="attachment" class="block w-full text-sm text-gray-500
                                                                                        file:mr-4 file:py-2 file:px-4
                                                                                        file:rounded-md file:border-0
                                                                                        file:text-sm file:font-semibold
                                                                                        file:bg-blue-50 file:text-blue-700
                                                                                        hover:file:bg-blue-100">
                                            <p class="mt-1 text-xs text-gray-500">PDF, DOC, JPG, PNG (max 5MB)</p>
                                        </div>
                                        
                                        <div class="flex justify-end">
                                            <button type="submit" class="bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                                Send Message
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            <?php else: ?>
                                <div class="p-8 text-center text-gray-500">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto text-gray-300 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                                    </svg>
                                    <h3 class="text-lg font-medium mb-2">No conversation selected</h3>
                                    <p>Select a student from the list to view or start a conversation.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- SweetAlert for notifications -->
    <script>
        <?php if (isset($_SESSION['success'])): ?>
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: "<?php echo $_SESSION['success']; ?>",
                timer: 3000,
                timerProgressBar: true
            });
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: "<?php echo $_SESSION['error']; ?>",
                timer: 3000,
                timerProgressBar: true
            });
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        // Student selection dropdown
        document.getElementById('student-select').addEventListener('change', function() {
            if (this.value) {
                window.location.href = 'student_messages.php?student_id=' + this.value;
            }
        });
        
        // Auto-scroll to bottom of conversation
        window.onload = function() {
            const messageContainer = document.querySelector('.message-container');
            if (messageContainer) {
                messageContainer.scrollTop = messageContainer.scrollHeight;
            }
        };
        
        // Student search functionality
        document.getElementById('student-search').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const studentItems = document.querySelectorAll('.student-item');
            
            studentItems.forEach(item => {
                const name = item.dataset.name;
                const course = item.dataset.course;
                
                if (name.includes(searchTerm) || course.includes(searchTerm)) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        });
        
        // Mark all messages as read
        document.getElementById('mark-all-read')?.addEventListener('click', function() {
            fetch('mark_all_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': '<?php echo $_SESSION['csrf_token']; ?>'
                },
                body: JSON.stringify({
                    admin_id: <?php echo $_SESSION['user_id']; ?>
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: 'All messages marked as read',
                        timer: 2000,
                        timerProgressBar: true
                    }).then(() => {
                        window.location.reload();
                    });
                }
            });
        });
        
        // Message template functionality
        document.getElementById('template-select').addEventListener('change', function() {
            const subjectField = document.getElementById('subject');
            const messageField = document.getElementById('message');
            
            // Define templates
            const templates = {
                reminder: {
                    subject: "Payment Reminder for TESDA Course",
                    message: "Dear Student,\n\nThis is a friendly reminder that your payment for the course is due on [DATE]. Please ensure that you complete the payment to continue your studies without interruption.\n\nThank you,\nTESDA Admin"
                },
                assessment: {
                    subject: "Upcoming Assessment Schedule",
                    message: "Dear Student,\n\nYour assessment for [COURSE] has been scheduled for [DATE] at [TIME].\n\nPlease make sure to prepare accordingly and arrive on time.\n\nBest regards,\nTESDA Admin"
                },
                congratulations: {
                    subject: "Congratulations on Your Progress",
                    message: "Dear Student,\n\nCongratulations on successfully completing the first phase of your training! Your dedication and hard work are truly commendable.\n\nWe look forward to your continued success.\n\nBest regards,\nTESDA Admin"
                },
                documents: {
                    subject: "Required Documents Reminder",
                    message: "Dear Student,\n\nThis is a reminder that you need to submit the following documents to complete your registration:\n\n1. [DOCUMENT 1]\n2. [DOCUMENT 2]\n3. [DOCUMENT 3]\n\nPlease submit these at your earliest convenience.\n\nBest regards,\nTESDA Admin"
                }
            };
            
            // Apply template
            if (this.value && templates[this.value]) {
                subjectField.value = templates[this.value].subject;
                messageField.value = templates[this.value].message;
            }
        });
    </script>
</body>
</html>