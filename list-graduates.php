<?php
session_start();
require 'db.php';

// Handle New Graduate Form Submission (Add or Update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['addGraduate']) || isset($_POST['updateGraduate']))) {
    $name = $_POST['name'];
    $course = $_POST['course'];
    $year = $_POST['year'];
    $status = $_POST['status'];

    if (isset($_POST['updateGraduate'])) {
        // Update existing graduate
        $id = intval($_POST['graduate_id']);
        $stmt = $pdo->prepare("UPDATE graduates SET name = ?, course = ?, year = ?, status = ? WHERE id = ?");
        $stmt->execute([$name, $course, $year, $status, $id]);
        header("Location: list-graduates.php?updated=1");
    } else {
        // Add new graduate
        $stmt = $pdo->prepare("INSERT INTO graduates (name, course, year, status) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $course, $year, $status]);
        header("Location: list-graduates.php?added=1");
    }
    exit;
}

// Handle Delete Graduate
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $pdo->prepare("DELETE FROM graduates WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: list-graduates.php?deleted=1");
    exit;
}

// Get filter values
$filterYear = isset($_GET['filter_year']) ? $_GET['filter_year'] : '';
$filterCourse = isset($_GET['filter_course']) ? $_GET['filter_course'] : '';
$filterStatus = isset($_GET['filter_status']) ? $_GET['filter_status'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build the query with filters
$query = "SELECT * FROM graduates WHERE 1=1";
$params = [];

if (!empty($filterYear)) {
    $query .= " AND year = :year";
    $params[':year'] = $filterYear;
}

if (!empty($filterCourse)) {
    $query .= " AND course = :course";
    $params[':course'] = $filterCourse;
}

if (!empty($filterStatus)) {
    $query .= " AND status = :status";
    $params[':status'] = $filterStatus;
}

if (!empty($search)) {
    $query .= " AND (name LIKE :search_name OR course LIKE :search_course)";
    $params[':search_name'] = "%$search%";
    $params[':search_course'] = "%$search%";
}

$query .= " ORDER BY id DESC";

// Prepare and execute the query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$graduates = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique years, courses, and statuses for filter dropdowns
$yearStmt = $pdo->query("SELECT DISTINCT year FROM graduates ORDER BY year DESC");
$years = $yearStmt->fetchAll(PDO::FETCH_COLUMN);

$courseStmt = $pdo->query("SELECT DISTINCT course FROM graduates ORDER BY course");
$courses = $courseStmt->fetchAll(PDO::FETCH_COLUMN);

$statusStmt = $pdo->query("SELECT DISTINCT status FROM graduates ORDER BY status");
$statuses = $statusStmt->fetchAll(PDO::FETCH_COLUMN);

// Get graduate statistics
$statsQuery = "SELECT status, COUNT(*) AS count FROM graduates GROUP BY status";
$statsStmt = $pdo->query($statsQuery);
$stats = [];
$totalGraduates = 0;

while ($row = $statsStmt->fetch(PDO::FETCH_ASSOC)) {
    $stats[$row['status']] = $row['count'];
    $totalGraduates += $row['count'];
}

// Fetch graduate data for editing if edit_id is set
$editGraduate = null;
if (isset($_GET['edit_id'])) {
    $id = intval($_GET['edit_id']);
    $stmt = $pdo->prepare("SELECT * FROM graduates WHERE id = ?");
    $stmt->execute([$id]);
    $editGraduate = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Graduate Tracking | TESDA Admin</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script> <!-- For alerts -->
  <style>
    /* Add smooth scrolling */
    html {
      scroll-behavior: smooth;
    }
    /* Add transitions */
    .transition-all {
      transition: all 0.3s ease;
    }
    .hover-scale:hover {
      transform: scale(1.02);
    }
    /* Custom badge styles */
    .status-badge {
      @apply px-2 py-1 rounded-full text-xs font-medium;
    }
    .status-badge-employed {
      @apply bg-green-100 text-green-800;
    }
    .status-badge-self-employed {
      @apply bg-yellow-100 text-yellow-800;
    }
    .status-badge-unemployed {
      @apply bg-red-100 text-red-800;
    }
    /* Loader */
    .loader {
      border-top-color: #3498db;
      -webkit-animation: spinner 1.5s linear infinite;
      animation: spinner 1.5s linear infinite;
    }
    @-webkit-keyframes spinner {
      0% { -webkit-transform: rotate(0deg); }
      100% { -webkit-transform: rotate(360deg); }
    }
    @keyframes spinner {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
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
            <h2 class="text-3xl font-bold text-blue-900 dark:text-blue-300">Graduate Tracking</h2>
            
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
      
      <div class="p-6">
        <!-- Action Buttons -->
        <div class="flex flex-wrap gap-3 mb-6">
          <div class="flex gap-3">
            <button id="addGraduateBtn" class="bg-blue-500 hover:bg-blue-600 text-white rounded-md px-4 py-2 flex items-center gap-2 transition-colors">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
              </svg>
              Add Graduate
            </button>
            
            <button id="filterBtn" class="border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 rounded-md px-4 py-2 flex items-center gap-2 transition-colors">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
              </svg>
              Filter
            </button>
          </div>
        </div>
        
        <!-- Graduate Statistics Dashboard -->
        <div class="mb-6 grid grid-cols-1 md:grid-cols-4 gap-4">
          <div class="bg-white p-4 rounded shadow-md">
            <h3 class="text-lg font-semibold text-gray-700 mb-2">Total Graduates</h3>
            <p class="text-3xl font-bold text-blue-600"><?php echo $totalGraduates; ?></p>
          </div>
          
          <div class="bg-white p-4 rounded shadow-md">
            <h3 class="text-lg font-semibold text-gray-700 mb-2">Employed</h3>
            <p class="text-3xl font-bold text-green-600"><?php echo $stats['Employed'] ?? 0; ?></p>
            <p class="text-sm text-gray-500">
              <?php echo ($totalGraduates > 0) ? round(($stats['Employed'] ?? 0) / $totalGraduates * 100) . '%' : '0%'; ?>
            </p>
          </div>
          
          <div class="bg-white p-4 rounded shadow-md">
            <h3 class="text-lg font-semibold text-gray-700 mb-2">Self-Employed</h3>
            <p class="text-3xl font-bold text-yellow-600"><?php echo $stats['Self-Employed'] ?? 0; ?></p>
            <p class="text-sm text-gray-500">
              <?php echo ($totalGraduates > 0) ? round(($stats['Self-Employed'] ?? 0) / $totalGraduates * 100) . '%' : '0%'; ?>
            </p>
          </div>
          
          <div class="bg-white p-4 rounded shadow-md">
            <h3 class="text-lg font-semibold text-gray-700 mb-2">Unemployed</h3>
            <p class="text-3xl font-bold text-red-600"><?php echo $stats['Unemployed'] ?? 0; ?></p>
            <p class="text-sm text-gray-500">
              <?php echo ($totalGraduates > 0) ? round(($stats['Unemployed'] ?? 0) / $totalGraduates * 100) . '%' : '0%'; ?>
            </p>
          </div>
        </div>

        <!-- Form to Add/Edit Graduate (Modal) -->
        <div id="graduateFormModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
          <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-2/3 xl:w-1/2 shadow-lg rounded-md bg-white">
            <div class="flex justify-between items-center mb-4">
              <h3 class="text-xl font-bold"><?php echo $editGraduate ? 'Edit Graduate' : 'Add Graduate'; ?></h3>
              <button id="closeGraduateModal" class="text-gray-400 hover:text-gray-500">
                <span class="text-2xl">&times;</span>
              </button>
            </div>
            
            <form method="POST" class="space-y-4">
              <?php if ($editGraduate): ?>
                <input type="hidden" name="graduate_id" value="<?php echo $editGraduate['id']; ?>">
              <?php endif; ?>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                  <input type="text" id="name" name="name" required class="w-full p-2 border rounded" 
                    value="<?php echo $editGraduate ? htmlspecialchars($editGraduate['name']) : ''; ?>">
                </div>
                <div>
                  <label for="course" class="block text-sm font-medium text-gray-700 mb-1">Course</label>
                  <input type="text" id="course" name="course" required class="w-full p-2 border rounded" 
                    value="<?php echo $editGraduate ? htmlspecialchars($editGraduate['course']) : ''; ?>">
                </div>
                <div>
                  <label for="year" class="block text-sm font-medium text-gray-700 mb-1">Year Graduated</label>
                  <input type="number" id="year" name="year" required class="w-full p-2 border rounded" 
                    value="<?php echo $editGraduate ? htmlspecialchars($editGraduate['year']) : date('Y'); ?>">
                </div>
                <div>
                  <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Employment Status</label>
                  <select id="status" name="status" required class="w-full p-2 border rounded">
                    <option value="Employed" <?php echo ($editGraduate && $editGraduate['status'] === 'Employed') ? 'selected' : ''; ?>>Employed</option>
                    <option value="Self-Employed" <?php echo ($editGraduate && $editGraduate['status'] === 'Self-Employed') ? 'selected' : ''; ?>>Self-Employed</option>
                    <option value="Unemployed" <?php echo ($editGraduate && $editGraduate['status'] === 'Unemployed') ? 'selected' : ''; ?>>Unemployed</option>
                  </select>
                </div>
              </div>
              <div class="flex justify-end space-x-2 mt-4">
                <button type="button" id="cancelGraduateBtn" class="px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300">Cancel</button>
                <button type="submit" name="<?php echo $editGraduate ? 'updateGraduate' : 'addGraduate'; ?>" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
                  <?php echo $editGraduate ? 'Update Graduate' : 'Add Graduate'; ?>
                </button>
              </div>
            </form>
          </div>
        </div>

        <!-- Filter Panel (Hidden by default) -->
        <div id="filterPanel" class="mb-6 bg-white p-4 rounded shadow-md hidden">
          <h3 class="text-lg font-semibold mb-3">Filter Graduates</h3>
          <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
              <label for="filter_year" class="block text-sm font-medium text-gray-700 mb-1">Year</label>
              <select name="filter_year" id="filter_year" class="w-full p-2 border rounded">
                <option value="">All Years</option>
                <?php foreach ($years as $year): ?>
                  <option value="<?php echo $year; ?>" <?php echo ($filterYear == $year) ? 'selected' : ''; ?>>
                    <?php echo $year; ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            
            <div>
              <label for="filter_course" class="block text-sm font-medium text-gray-700 mb-1">Course</label>
              <select name="filter_course" id="filter_course" class="w-full p-2 border rounded">
                <option value="">All Courses</option>
                <?php foreach ($courses as $course): ?>
                  <option value="<?php echo htmlspecialchars($course); ?>" <?php echo ($filterCourse == $course) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($course); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            
            <div>
              <label for="filter_status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
              <select name="filter_status" id="filter_status" class="w-full p-2 border rounded">
                <option value="">All Statuses</option>
                <?php foreach ($statuses as $status): ?>
                  <option value="<?php echo htmlspecialchars($status); ?>" <?php echo ($filterStatus == $status) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($status); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            
            <div>
              <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
              <input type="text" name="search" id="search" placeholder="Search by name or course" class="w-full p-2 border rounded" value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <div class="md:col-span-4 flex justify-end space-x-2">
              <a href="list-graduates.php" class="px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300">Reset</a>
              <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">Apply Filters</button>
            </div>
          </form>
        </div>

        <!-- Table List -->
        <div class="overflow-x-auto">
          <!-- Quick Search -->
          <div class="mb-4">
            <form action="" method="GET" class="flex gap-2 max-w-xl">
              <input 
                type="text" 
                name="search" 
                placeholder="Search graduates by name or course..." 
                class="border border-gray-300 rounded-md px-3 py-2 w-full focus:outline-none focus:ring-2 focus:ring-blue-500"
                value="<?php echo htmlspecialchars($search); ?>"
              >
              <button 
                type="submit" 
                class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-2 rounded flex items-center gap-1"
              >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                Search
              </button>
              <?php if (!empty($search)): ?>
                <a href="list-graduates.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-3 py-2 rounded flex items-center">
                  Clear
                </a>
              <?php endif; ?>
            </form>
          </div>
          
          <?php if (!empty($search)): ?>
            <div class="mb-4 text-sm text-blue-600">
              Showing results for: "<?php echo htmlspecialchars($search); ?>"
            </div>
          <?php endif; ?>
          
          <?php
          // Pagination settings
          $itemsPerPage = 10;
          $totalItems = count($graduates);
          $currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
          $totalPages = ceil($totalItems / $itemsPerPage);
          $offset = ($currentPage - 1) * $itemsPerPage;
          $paginatedGraduates = array_slice($graduates, $offset, $itemsPerPage);
          ?>
          
          <table id="graduatesTable" class="w-full border-collapse border border-gray-300 bg-white">
            <thead class="bg-blue-900 text-white">
              <tr>
                <th class="border p-2 cursor-pointer" onclick="sortTable(0)">
                  Name <span class="sort-icon inline-block ml-1">↕</span>
                </th>
                <th class="border p-2 cursor-pointer" onclick="sortTable(1)">
                  Course <span class="sort-icon inline-block ml-1">↕</span>
                </th>
                <th class="border p-2 cursor-pointer" onclick="sortTable(2)">
                  Year <span class="sort-icon inline-block ml-1">↕</span>
                </th>
                <th class="border p-2 cursor-pointer" onclick="sortTable(3)">
                  Status <span class="sort-icon inline-block ml-1">↕</span>
                </th>
                <th class="border p-2">Actions</th>
              </tr>
            </thead>
            <tbody id="graduateList" class="text-gray-700">
              <?php if (count($paginatedGraduates) > 0): ?>
                <?php foreach ($paginatedGraduates as $grad): ?>
                  <tr class="hover:bg-gray-100 transition-colors">
                    <td class="border p-2"><?php echo htmlspecialchars($grad['name']); ?></td>
                    <td class="border p-2"><?php echo htmlspecialchars($grad['course']); ?></td>
                    <td class="border p-2"><?php echo htmlspecialchars($grad['year']); ?></td>
                    <td class="border p-2">
                      <?php 
                        $statusClass = '';
                        switch($grad['status']) {
                          case 'Employed': 
                            $statusClass = 'status-badge-employed'; 
                            break;
                          case 'Self-Employed': 
                            $statusClass = 'status-badge-self-employed'; 
                            break;
                          case 'Unemployed': 
                            $statusClass = 'status-badge-unemployed'; 
                            break;
                          default: 
                            $statusClass = 'bg-gray-100 text-gray-800';
                        }
                      ?>
                      <span class="status-badge <?php echo $statusClass; ?>">
                        <?php echo htmlspecialchars($grad['status']); ?>
                      </span>
                    </td>
                    <td class="border p-2">
                      <a href="list-graduates.php?edit_id=<?php echo $grad['id']; ?>" class="bg-yellow-500 text-white px-3 py-1 rounded hover:bg-yellow-600 text-sm mr-2 inline-flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" viewBox="0 0 20 20" fill="currentColor">
                          <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                        </svg>
                        Edit
                      </a>
                      <button onclick="confirmDelete(<?php echo $grad['id']; ?>)" class="bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600 text-sm inline-flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" viewBox="0 0 20 20" fill="currentColor">
                          <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0111 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                        Delete
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="5" class="text-center p-4">
                    <?php if (!empty($search) || !empty($filterYear) || !empty($filterCourse) || !empty($filterStatus)): ?>
                      No graduates found matching your criteria.
                    <?php else: ?>
                      No graduates found. Add some graduates to get started.
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
          
          <!-- Pagination -->
          <?php if ($totalPages > 1): ?>
          <div class="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6 mt-4">
            <div class="flex flex-1 justify-between sm:hidden">
              <?php if ($currentPage > 1): ?>
              <a href="?page=<?php echo $currentPage - 1; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>" class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Previous</a>
              <?php endif; ?>
              <?php if ($currentPage < $totalPages): ?>
              <a href="?page=<?php echo $currentPage + 1; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>" class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Next</a>
              <?php endif; ?>
            </div>
            <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
              <div>
                <p class="text-sm text-gray-700">
                  Showing <span class="font-medium"><?php echo min(($offset + 1), $totalItems); ?></span> to <span class="font-medium"><?php echo min(($offset + $itemsPerPage), $totalItems); ?></span> of <span class="font-medium"><?php echo $totalItems; ?></span> results
                </p>
              </div>
              <div>
                <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                  <?php if ($currentPage > 1): ?>
                  <a href="?page=<?php echo $currentPage - 1; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>" class="relative inline-flex items-center px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0">
                    <span class="sr-only">Previous</span>
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" />
                    </svg>
                  </a>
                  <?php endif; ?>
                  
                  <?php 
                  $startPage = max(1, min($currentPage - 2, $totalPages - 4));
                  $endPage = min($totalPages, max($currentPage + 2, 5));
                  
                  for ($i = $startPage; $i <= $endPage; $i++): 
                  ?>
                    <a href="?page=<?php echo $i; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>" class="relative inline-flex items-center px-4 py-2 text-sm font-semibold <?php echo ($i == $currentPage) ? 'bg-blue-500 text-white focus:z-20 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600' : 'text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0'; ?>">
                      <?php echo $i; ?>
                    </a>
                  <?php endfor; ?>
                  
                  <?php if ($currentPage < $totalPages): ?>
                  <a href="?page=<?php echo $currentPage + 1; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>" class="relative inline-flex items-center px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0">
                    <span class="sr-only">Next</span>
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" />
                    </svg>
                  </a>
                  <?php endif; ?>
                </nav>
              </div>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </main>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Delete Graduate with Confirmation
      window.confirmDelete = function(id) {
        Swal.fire({
          title: 'Are you sure?',
          text: "You won't be able to revert this!",
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#d33',
          cancelButtonColor: '#3085d6',
          confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
          if (result.isConfirmed) {
            // Show loading indicator
            Swal.fire({
              title: 'Deleting...',
              html: 'Please wait...',
              allowOutsideClick: false,
              didOpen: () => {
                Swal.showLoading();
              }
            });
            // Redirect to delete
            window.location.href = 'list-graduates.php?delete=' + id;
          }
        });
      };
    
      // User Menu Dropdown Toggle
      const userMenuBtn = document.getElementById('userMenuBtn');
      const userMenuDropdown = document.getElementById('userMenuDropdown');
      
      if (userMenuBtn && userMenuDropdown) {
        userMenuBtn.addEventListener('click', function() {
          userMenuDropdown.classList.toggle('hidden');
        });
        
        // Hide dropdown when clicking outside
        document.addEventListener('click', function(event) {
          if (!userMenuBtn.contains(event.target) && !userMenuDropdown.contains(event.target)) {
            userMenuDropdown.classList.add('hidden');
          }
        });
      }
    
      // Add/Edit Graduate Modal Controls
      const graduateFormModal = document.getElementById('graduateFormModal');
      const addGraduateBtn = document.getElementById('addGraduateBtn');
      const closeGraduateModal = document.getElementById('closeGraduateModal');
      const cancelGraduateBtn = document.getElementById('cancelGraduateBtn');
      const graduateForm = graduateFormModal ? graduateFormModal.querySelector('form') : null;
      const submitBtn = graduateForm ? graduateForm.querySelector('button[type="submit"]') : null;
      
      // Open modal when Add Graduate button is clicked
      if (addGraduateBtn && graduateFormModal) {
        addGraduateBtn.addEventListener('click', function(e) {
          e.preventDefault();
          graduateFormModal.classList.remove('hidden');
          document.body.classList.add('overflow-hidden'); // Prevent scrolling
        });
      }
    
      // Close modal when X button is clicked
      if (closeGraduateModal && graduateFormModal) {
        closeGraduateModal.addEventListener('click', function() {
          graduateFormModal.classList.add('hidden');
          document.body.classList.remove('overflow-hidden');
        });
      }
    
      // Close modal when Cancel button is clicked
      if (cancelGraduateBtn && graduateFormModal) {
        cancelGraduateBtn.addEventListener('click', function() {
          graduateFormModal.classList.add('hidden');
          document.body.classList.remove('overflow-hidden');
        });
      }
    
      // Close modal when clicking outside the modal content
      if (graduateFormModal) {
        graduateFormModal.addEventListener('click', function(event) {
          if (event.target === graduateFormModal) {
            graduateFormModal.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
          }
        });
      }
      
      // Add loading state when form is submitted
      if (graduateForm && submitBtn) {
        graduateForm.addEventListener('submit', function() {
          submitBtn.disabled = true;
          const originalText = submitBtn.innerHTML;
          submitBtn.innerHTML = `
            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline-block" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Processing...
          `;
          
          // Re-enable after 5 seconds (in case of network issues)
          setTimeout(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
          }, 5000);
        });
      }

      // Show graduate form modal if there was a form error or edit_id is present
      <?php if (isset($_POST['addGraduate']) || isset($_POST['updateGraduate']) || isset($_GET['edit_id'])): ?>
        if (graduateFormModal) {
          graduateFormModal.classList.remove('hidden');
          document.body.classList.add('overflow-hidden');
        }
      <?php endif; ?>
    
      // Filter Panel Toggle
      const filterBtn = document.getElementById('filterBtn');
      const filterPanel = document.getElementById('filterPanel');

      if (filterBtn && filterPanel) {
        filterBtn.addEventListener('click', function() {
          filterPanel.classList.toggle('hidden');
        });
        
        // Show filter panel if there are active filters
        <?php if (!empty($filterYear) || !empty($filterCourse) || !empty($filterStatus)): ?>
          filterPanel.classList.remove('hidden');
        <?php endif; ?>
      }
      
      // Table Sorting
      window.sortTable = function(colIndex) {
        const table = document.getElementById('graduatesTable');
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        
        // Determine sort direction
        const sortDir = table.getAttribute('data-sort-dir-' + colIndex) === 'asc' ? 'desc' : 'asc';
        
        // Reset all sort indicators
        for (let i = 0; i < 5; i++) {
          table.setAttribute('data-sort-dir-' + i, '');
        }
        
        // Set current sort direction
        table.setAttribute('data-sort-dir-' + colIndex, sortDir);
        
        // Update sort indicators in UI
        const sortIcons = table.querySelectorAll('.sort-icon');
        sortIcons.forEach((icon, i) => {
          if (i === colIndex) {
            icon.textContent = sortDir === 'asc' ? '↑' : '↓';
          } else {
            icon.textContent = '↕';
          }
        });
        
        // Sort the rows
        rows.sort((a, b) => {
          let aVal = a.cells[colIndex].textContent.trim();
          let bVal = b.cells[colIndex].textContent.trim();
          
          // Special handling for year column (numeric)
          if (colIndex === 2) {
            return sortDir === 'asc' 
              ? parseInt(aVal) - parseInt(bVal)
              : parseInt(bVal) - parseInt(aVal);
          }
          
          // Default string comparison
          return sortDir === 'asc'
            ? aVal.localeCompare(bVal)
            : bVal.localeCompare(aVal);
        });
        
        // Remove existing rows and add sorted rows
        while (tbody.firstChild) {
          tbody.removeChild(tbody.firstChild);
        }
        
        rows.forEach(row => {
          tbody.appendChild(row);
        });
      };

      // Show success message if needed
      <?php if (isset($_GET['added'])): ?>
        Swal.fire('Success!', 'Graduate added successfully.', 'success');
      <?php elseif (isset($_GET['updated'])): ?>
        Swal.fire('Success!', 'Graduate updated successfully.', 'success');
      <?php elseif (isset($_GET['deleted'])): ?>
        Swal.fire('Deleted!', 'Graduate has been removed.', 'success');
      <?php endif; ?>
    });
  </script>
</body>
</html>