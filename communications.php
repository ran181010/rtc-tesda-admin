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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid CSRF token.";
        header("Location: communications.php");
        exit;
    }
    
    $type = $_POST['type'] ?? '';
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $target_audience = $_POST['target_audience'] ?? 'all';
    $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : NULL;
    
    // Validate input
    if (empty($type) || empty($title) || empty($content)) {
        $_SESSION['error'] = "All fields are required.";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO communications 
                (type, title, content, created_by, expiry_date, target_audience) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$type, $title, $content, $_SESSION['user_id'], $expiry_date, $target_audience]);
            
            $_SESSION['success'] = ucfirst($type) . " created successfully.";
            header("Location: communications.php");
            exit;
        } catch (PDOException $e) {
            $_SESSION['error'] = "Database error: " . $e->getMessage();
        }
    }
}

// Handle deletion
if (isset($_GET['delete']) && isset($_GET['csrf_token'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'])) {
        $_SESSION['error'] = "Invalid CSRF token.";
        header("Location: communications.php");
        exit;
    }
    
    $id = intval($_GET['delete']);
    try {
        $stmt = $pdo->prepare("DELETE FROM communications WHERE id = ?");
        $stmt->execute([$id]);
        
        $_SESSION['success'] = "Item deleted successfully.";
        header("Location: communications.php");
        exit;
    } catch (PDOException $e) {
        $_SESSION['error'] = "Database error: " . $e->getMessage();
    }
}

// Get existing communications
try {
    $stmt = $pdo->query("
        SELECT c.*, a.username as creator 
        FROM communications c
        JOIN admin_users a ON c.created_by = a.id
        ORDER BY c.created_at DESC
    ");
    $communications = $stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['error'] = "Error loading communications: " . $e->getMessage();
    $communications = [];
}

// Count metrics for dashboard
$expiring_soon = 0;
$active_announcements = 0;
$active_alerts = 0;

foreach($communications as $comm) {
    // Count expiring communications (within 7 days)
    if (!empty($comm['expiry_date']) && strtotime($comm['expiry_date']) - time() < 7 * 24 * 60 * 60 && strtotime($comm['expiry_date']) - time() > 0) {
        $expiring_soon++;
    }
    
    // Count active announcements
    if ($comm['type'] == 'announcement' && (empty($comm['expiry_date']) || strtotime($comm['expiry_date']) > time())) {
        $active_announcements++;
    }
    
    // Count active alerts
    if ($comm['type'] == 'alert' && (empty($comm['expiry_date']) || strtotime($comm['expiry_date']) > time())) {
        $active_alerts++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Communications | TESDA Admin</title>
    <link rel="icon" href="logoT.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
        <main class="flex-1 overflow-auto">
            <!-- Top navigation bar with user profile -->
            <div class="bg-card shadow-sm sticky top-0 z-10 transition-colors">
                <div class="max-w-full mx-auto px-6 py-3">
                    <div class="flex justify-between items-center">
                        <h2 class="text-3xl font-bold text-blue-900 dark:text-blue-300">Communications</h2>
                        
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
                                                <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" />
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
            
            <!-- Page Content -->
            <div class="p-6">
                <div class="flex justify-between items-center mb-6">
                    <h1 class="text-2xl font-bold text-gray-800">Announcement System</h1>
                </div>
                
                <!-- Create New Communication -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                    <h2 class="text-xl font-semibold text-gray-800 mb-6">Create New Communication</h2>
                    
                    <form action="communications.php" method="POST" class="space-y-6">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="type" class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                                <select id="type" name="type" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                    <option value="announcement">Announcement</option>
                                    <option value="reminder">Reminder</option>
                                </select>
                            </div>
                            
                            <div>
                                <label for="target_audience" class="block text-sm font-medium text-gray-700 mb-1">Target Audience</label>
                                <select id="target_audience" name="target_audience" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                    <option value="all">Everyone</option>
                                    <option value="students">Students Only</option>
                                    <option value="admins">Administrators Only</option>
                                </select>
                            </div>
                        </div>
                        
                        <div>
                            <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                            <input type="text" id="title" name="title" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" required>
                        </div>
                        
                        <div>
                            <label for="content" class="block text-sm font-medium text-gray-700 mb-1">Content</label>
                            <textarea id="content" name="content" rows="5" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" required></textarea>
                        </div>
                        
                        <div>
                            <label for="expiry_date" class="block text-sm font-medium text-gray-700 mb-1">Expiry Date (Optional)</label>
                            <input type="date" id="expiry_date" name="expiry_date" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                            <p class="mt-1 text-sm text-gray-500">Leave blank if this does not expire</p>
                        </div>
                        
                        <div class="flex justify-end">
                            <button type="submit" class="bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                Create
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- List of Communications -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-6">Active Communications</h2>
                    
                    <?php if (empty($communications)): ?>
                        <div class="text-center py-8 text-gray-500">
                            <p>No communications found.</p>
                            <p class="mt-1">Create your first announcement or reminder using the form above.</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Audience</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expires</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($communications as $item): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php if ($item['type'] == 'announcement'): ?>
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                                        Announcement
                                                    </span>
                                                <?php else: ?>
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                        Reminder
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($item['title']); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo ucfirst($item['target_audience']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo date('M d, Y', strtotime($item['created_at'])); ?><br>
                                                <span class="text-xs text-gray-400">By: <?php echo htmlspecialchars($item['creator']); ?></span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo $item['expiry_date'] ? date('M d, Y', strtotime($item['expiry_date'])) : 'Never'; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <button onclick="viewItem(<?php echo $item['id']; ?>)" class="text-blue-600 hover:text-blue-900 mr-3">
                                                    View
                                                </button>
                                                <a href="communications.php?delete=<?php echo $item['id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" 
                                                   onclick="return confirm('Are you sure you want to delete this item?')"
                                                   class="text-red-600 hover:text-red-900">
                                                    Delete
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
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
        
        function viewItem(id) {
            // Fetch the data for the specified communication
            fetch(`get_communication.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            title: data.title,
                            html: `
                                <div class="text-left">
                                    <p class="mb-2"><strong>Type:</strong> ${data.type.charAt(0).toUpperCase() + data.type.slice(1)}</p>
                                    <p class="mb-2"><strong>Target:</strong> ${data.target_audience.charAt(0).toUpperCase() + data.target_audience.slice(1)}</p>
                                    <p class="mb-2"><strong>Created:</strong> ${data.created_at} by ${data.creator}</p>
                                    <p class="mb-4"><strong>Expires:</strong> ${data.expiry_date || 'Never'}</p>
                                    <div class="border-t pt-4">
                                        <p>${data.content}</p>
                                    </div>
                                </div>
                            `,
                            confirmButtonText: 'Close',
                            width: '600px'
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Could not load the communication details.'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'An error occurred while fetching data.'
                    });
                });
        }
    </script>
    
    <!-- Theme toggle script -->
    <script>
        const toggleBtn = document.getElementById('theme-toggle');
        const circle = document.getElementById('toggle-circle');
        let isDarkMode = false;
        
        // Check for saved user preference
        if (localStorage.getItem('darkMode') === 'true') {
            enableDarkMode();
        }
        
        toggleBtn.addEventListener('click', function() {
            if (isDarkMode) {
                disableDarkMode();
            } else {
                enableDarkMode();
            }
        });
        
        function enableDarkMode() {
            document.body.classList.add('bg-gray-900', 'text-white');
            circle.classList.add('translate-x-6');
            localStorage.setItem('darkMode', 'true');
            isDarkMode = true;
        }
        
        function disableDarkMode() {
            document.body.classList.remove('bg-gray-900', 'text-white');
            circle.classList.remove('translate-x-6');
            localStorage.setItem('darkMode', 'false');
            isDarkMode = false;
        }
    </script>
</body>
</html>