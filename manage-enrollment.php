<?php
session_start();
require 'db.php'; // Now this gives us $pdo

$message = "";

if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id = (int)$_GET['id'];

    if (in_array($action, ['approve', 'reject'])) {
        $status = $action === 'approve' ? 'approved' : 'rejected';
        $stmt = $pdo->prepare("UPDATE enrollees SET status = :status WHERE id = :id");
        $stmt->execute(['status' => $status, 'id' => $id]);
        $message = "Student has been " . ($status === 'approved' ? "approved" : "rejected") . " successfully.";
    }
}

if (isset($_POST['add_student'])) {
    // Get form data
    $first_name = $_POST['first_name'] ?? '';
    $middle_name = $_POST['middle_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $gender = $_POST['gender'] ?? 'Male';
    $birth_date = $_POST['birth_date'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    $course = $_POST['course'] ?? '';
    $educational_attainment = $_POST['educational_attainment'] ?? '';
    $is_re_enroll = $_POST['is_re_enroll'] ?? '0';
    $student_id = $_POST['student_id'] ?? '';
    
    // Create directory for documents if needed
    $documents_path = '';
    if (!empty($_FILES['documents']['name'])) {
        $upload_dir = 'uploads/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_name = time() . '_' . $_FILES['documents']['name'];
        $upload_path = $upload_dir . $file_name;
        
        if (move_uploaded_file($_FILES['documents']['tmp_name'], $upload_path)) {
            $documents_path = $upload_path;
        }
    }
    
    try {
        $enrollment_date = date('Y-m-d');
        if ($is_re_enroll == '1') {
            $stmt = $pdo->prepare("INSERT INTO enrollees 
                (student_id, course, enrollment_date, status) 
                VALUES (:student_id, :course, :enrollment_date, 'pending')");
            
            $stmt->execute([
                'student_id' => $student_id,
                'course' => $course,
                'enrollment_date' => $enrollment_date
            ]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO enrollees 
                (first_name, middle_name, last_name, gender, birth_date, email, phone, address, 
                course, educational_attainment, status, enrollment_date) 
                VALUES (:first_name, :middle_name, :last_name, :gender, :birth_date, :email, :phone, 
                :address, :course, :educational_attainment, 'pending', :enrollment_date)");
            
            $stmt->execute([
                'first_name' => $first_name,
                'middle_name' => $middle_name,
                'last_name' => $last_name,
                'gender' => $gender,
                'birth_date' => $birth_date,
                'email' => $email,
                'phone' => $phone,
                'address' => $address,
                'course' => $course,
                'educational_attainment' => $educational_attainment,
                'enrollment_date' => $enrollment_date
            ]);
        }
        
        $message = "Student added successfully.";
    } catch (PDOException $e) {
        $message = "Error adding student: " . $e->getMessage();
    }
}

// Filter parameters
$courseFilter = $_GET['course'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$searchQuery = $_GET['search'] ?? '';
$pendingSearchQuery = $_GET['pending_search'] ?? '';

// Filter pending enrollees
$pendingSql = "SELECT * FROM enrollees WHERE status='pending'";
if (!empty($pendingSearchQuery)) {
    $pendingSql .= " AND (first_name LIKE '%$pendingSearchQuery%' OR middle_name LIKE '%$pendingSearchQuery%' OR last_name LIKE '%$pendingSearchQuery%' OR email LIKE '%$pendingSearchQuery%' OR phone LIKE '%$pendingSearchQuery%')";
}
if (!empty($courseFilter)) {
    $pendingSql .= " AND course LIKE '%$courseFilter%'";
}
$pendingSql .= " ORDER BY created_at DESC";
$pending = $pdo->query($pendingSql)->fetchAll();

// Get enrolled students (approved status)
$sql = "SELECT * FROM enrollees WHERE status='approved'";
if (!empty($searchQuery)) {
    $sql .= " AND (first_name LIKE '%$searchQuery%' OR middle_name LIKE '%$searchQuery%' OR last_name LIKE '%$searchQuery%' OR email LIKE '%$searchQuery%' OR phone LIKE '%$searchQuery%')";
}
if (!empty($courseFilter)) {
    $sql .= " AND course LIKE '%$courseFilter%'";
}
$sql .= " ORDER BY created_at DESC";
$enrolled = $pdo->query($sql)->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Manage Enrolling | TESDA Admin</title>
  <script src="https://cdn.tailwindcss.com"></script>
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
      <div class="bg-white shadow-sm sticky top-0 z-10">
        <div class="max-w-full mx-auto px-6 py-3">
          <div class="flex justify-between items-center">
            <h2 class="text-3xl font-bold text-blue-900">Manage Enrolling</h2>
            
            <div class="flex items-center space-x-4">
              <div class="text-sm text-gray-600">
                <?php echo date('l, F j, Y'); ?>
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
                    <p class="font-semibold text-gray-700"><?php echo htmlspecialchars($_SESSION['username']); ?></p>
                    <p class="text-xs text-gray-500"><?php echo isset($_SESSION['admin_level']) ? ucfirst($_SESSION['admin_level']) : 'Admin'; ?></p>
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
      
      <div class="p-6">
        <!-- Toast Message -->
        <?php if (!empty($message)): ?>
          <div id="toast" class="bg-green-500 text-white p-3 rounded mb-6 shadow-md animate-fade-in-down">
            <?php echo htmlspecialchars($message); ?>
          </div>
        <?php endif; ?>

        <!-- Action Buttons and Search -->
        <div class="flex flex-wrap gap-4 mb-6 justify-between">
          <div class="flex gap-4">
            <button id="openFormBtn" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded flex items-center gap-2">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
              </svg>
              Add Student
            </button>
            
            <div class="relative">
              <button id="filterBtn" class="border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 px-4 py-2 rounded flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                </svg>
                Filter
              </button>
              <!-- Filter Dropdown -->
              <div id="filterDropdown" class="absolute z-10 mt-1 bg-white border border-gray-200 rounded shadow-lg p-2 hidden min-w-[240px]">
                <div class="mb-2 pb-2 border-b">
                  <p class="text-sm font-semibold text-gray-700 mb-1">Filter by Course</p>
                  <select id="courseFilter" class="w-full border border-gray-300 rounded px-2 py-1 text-sm">
                    <option value="">All Courses</option>
                    <option value="Shielded Metal Arc Welding (SMAW) NC I">Shielded Metal Arc Welding (SMAW) NC I</option>
                    <option value="Computer Systems Servicing NC II">Computer Systems Servicing NC II</option>
                    <option value="Bread and Pastry Production NC II">Bread and Pastry Production NC II</option>
                    <option value="Electrical Installation and Maintenance NC II">Electrical Installation and Maintenance NC II</option>
                    <option value="Automotive Servicing NC I">Automotive Servicing NC I</option>
                    <option value="Driving NC II">Driving NC II</option>
                    <option value="Cookery NC II">Cookery NC II</option>
                    <option value="Carpentry NC II">Carpentry NC II</option>
                    <option value="Dressmaking NC II">Dressmaking NC II</option>
                  </select>
                </div>
                <div class="mb-2 pb-2 border-b">
                  <p class="text-sm font-semibold text-gray-700 mb-1">Filter by Status</p>
                  <select id="statusFilter" class="w-full border border-gray-300 rounded px-2 py-1 text-sm">
                    <option value="">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                  </select>
                </div>
                <div class="text-right">
                  <button id="applyFilterBtn" class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm">
                    Apply
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Pending Requests List -->
        <div class="bg-white p-6 shadow rounded mb-6">
          <div class="flex flex-wrap justify-between items-center mb-4">
            <h3 class="text-xl font-semibold text-blue-800">
              <span id="enrollmentListTitle">Pending Enrollment Requests</span>
              <span id="filterIndicator" class="ml-2 text-sm font-normal text-blue-600 hidden">(Filtered)</span>
            </h3>

            <!-- Pending Students Search Bar -->
            <div class="mt-2 sm:mt-0">
              <form action="" method="GET" class="flex flex-wrap gap-2">
                <input 
                  type="text" 
                  name="pending_search" 
                  placeholder="Search pending applications..." 
                  class="border border-gray-300 rounded-md px-3 py-2 w-48 md:w-64 focus:outline-none focus:ring-2 focus:ring-blue-500"
                  value="<?php echo htmlspecialchars($pendingSearchQuery); ?>"
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
                <?php if (!empty($pendingSearchQuery)): ?>
                  <a href="?<?php echo http_build_query(array_diff_key($_GET, ['pending_search' => ''])); ?>" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-3 py-2 rounded flex items-center">
                    Clear
                  </a>
                <?php endif; ?>
                
                <!-- Preserve other GET parameters -->
                <?php foreach ($_GET as $key => $value): ?>
                  <?php if ($key !== 'pending_search'): ?>
                    <input type="hidden" name="<?php echo htmlspecialchars($key); ?>" value="<?php echo htmlspecialchars($value); ?>">
                  <?php endif; ?>
                <?php endforeach; ?>
              </form>
            </div>
          </div>

          <ul id="pendingEnrollees" class="space-y-4">
            <?php if (count($pending) > 0): ?>
              <?php if (!empty($pendingSearchQuery)): ?>
                <div class="mb-4 text-sm text-blue-600">
                  Showing results for: "<?php echo htmlspecialchars($pendingSearchQuery); ?>"
                </div>
              <?php endif; ?>
              <?php foreach ($pending as $row): ?>
                <li class="flex justify-between items-center bg-gray-50 p-4 rounded shadow hover:bg-gray-100 cursor-pointer transition duration-200" 
                    onclick="openEnrolleeDetails(<?php echo $row['id']; ?>)">
                  <div>
                    <p class="font-bold text-gray-800">
                      <?php echo htmlspecialchars(($row['first_name'] ?? '') . ' ' . ($row['middle_name'] ?? '') . ' ' . ($row['last_name'] ?? '')); ?>
                    </p>
                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($row['course'] ?? 'Not specified'); ?></p>
                    <p class="text-xs text-gray-500">Applied: <?php echo isset($row['created_at']) ? date('M d, Y', strtotime($row['created_at'])) : 'Unknown date'; ?></p>
                  </div>
                  <div class="flex space-x-2">
                    <a href="?action=approve&id=<?php echo $row['id']; ?>" class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-sm flex items-center gap-1">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                      </svg>
                      Approve
                    </a>
                    <a href="?action=reject&id=<?php echo $row['id']; ?>" class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-sm flex items-center gap-1">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                      </svg>
                      Reject
                    </a>
                  </div>
                </li>
              <?php endforeach; ?>
            <?php else: ?>
              <li class="text-center py-8 text-gray-500">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <p class="mt-2">No pending enrollment requests</p>
              </li>
            <?php endif; ?>
          </ul>
        </div>
        
        <!-- Enrolled Students List -->
        <div class="bg-white p-6 shadow rounded mt-8">
          <h3 class="text-xl font-semibold mb-4 text-blue-800">
            <span>Enrolled Students</span>
            <?php if (!empty($searchQuery)): ?>
              <span class="ml-2 text-sm font-normal text-blue-600">
                (Search results for: "<?php echo htmlspecialchars($searchQuery); ?>")
              </span>
            <?php endif; ?>
          </h3>
          
          <!-- Student Search Bar -->
          <div class="flex items-center mb-4">
            <form action="" method="GET" class="flex flex-wrap gap-2">
              <input 
                type="text" 
                name="search" 
                placeholder="Search students by name, email or phone" 
                class="border border-gray-300 rounded-md px-3 py-2 w-48 md:w-64 focus:outline-none focus:ring-2 focus:ring-blue-500"
                value="<?php echo htmlspecialchars($searchQuery); ?>"
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
              <?php if (!empty($searchQuery)): ?>
                <a href="manage-enrollment.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-3 py-2 rounded flex items-center">
                  Reset
                </a>
              <?php endif; ?>
            </form>
          </div>
          
          <?php if (!empty($enrolled)): ?>
            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200" id="enrolledTable">
                <thead class="bg-gray-50">
                  <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100" onclick="sortTable(0)">
                      Student
                      <span class="ml-1 inline-block">↕</span>
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100" onclick="sortTable(1)">
                      Course
                      <span class="ml-1 inline-block">↕</span>
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100" onclick="sortTable(2)">
                      Contact
                      <span class="ml-1 inline-block">↕</span>
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100" onclick="sortTable(3)">
                      Enrollment Date
                      <span class="ml-1 inline-block">↕</span>
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <?php 
                  // Pagination settings
                  $itemsPerPage = 10;
                  $totalItems = count($enrolled);
                  $currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
                  $totalPages = ceil($totalItems / $itemsPerPage);
                  $offset = ($currentPage - 1) * $itemsPerPage;
                  $paginatedEnrolled = array_slice($enrolled, $offset, $itemsPerPage);
                  
                  foreach ($paginatedEnrolled as $student): 
                  ?>
                    <tr class="hover:bg-gray-50">
                      <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                          <div class="flex-shrink-0 h-10 w-10 bg-blue-100 rounded-full flex items-center justify-center">
                            <span class="text-blue-800 font-medium">
                              <?php echo substr($student['first_name'] ?? '', 0, 1) . substr($student['last_name'] ?? '', 0, 1); ?>
                            </span>
                          </div>
                          <div class="ml-4">
                            <div class="text-sm font-medium text-gray-900">
                              <?php echo htmlspecialchars(($student['first_name'] ?? '') . ' ' . ($student['middle_name'] ?? '') . ' ' . ($student['last_name'] ?? '')); ?>
                            </div>
                            <div class="text-sm text-gray-500">
                              ID: <?php echo $student['id']; ?>
                            </div>
                          </div>
                        </div>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($student['course'] ?? 'Not specified'); ?></div>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($student['email'] ?? 'No email provided'); ?></div>
                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($student['phone'] ?? 'No phone provided'); ?></div>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <?php echo isset($student['enrollment_date']) ? date('M d, Y', strtotime($student['enrollment_date'])) : 'Not specified'; ?>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <?php 
                        $status = $student['status'] ?? 'pending';
                        $statusClasses = [
                            'pending' => 'bg-yellow-100 text-yellow-800',
                            'approved' => 'bg-green-100 text-green-800',
                            'rejected' => 'bg-red-100 text-red-800'
                        ];
                        $statusClass = $statusClasses[$status] ?? 'bg-gray-100 text-gray-800';
                        ?>
                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                          <?php echo ucfirst($status); ?>
                        </span>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <button onclick="openEnrolleeDetails(<?php echo $student['id']; ?>)" class="text-blue-600 hover:text-blue-900 mr-2">
                          View Details
                        </button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
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
                      <a href="?page=<?php echo $currentPage - 1; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>" class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <span class="sr-only">Previous</span>
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                          <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" />
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
                      <a href="?page=<?php echo $currentPage + 1; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>" class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0">
                        <span class="sr-only">Next</span>
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                          <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                        </svg>
                      </a>
                      <?php endif; ?>
                    </nav>
                  </div>
                </div>
              </div>
              <?php endif; ?>
            </div>
          <?php else: ?>
            <div class="text-center py-8 text-gray-500">
              <?php if (!empty($searchQuery)): ?>
                <p class="mt-2">No students found matching your search criteria</p>
              <?php else: ?>
                <p class="mt-2">No enrolled students found</p>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
        
        <!-- Add New Student Form (hidden by default) -->
        <div id="addStudentModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
          <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-2/3 xl:w-1/2 shadow-lg rounded-md bg-white">
            <div class="flex justify-between items-center mb-4">
              <h3 class="text-lg font-medium">Add New Student</h3>
              <button id="closeAddStudentModal" class="text-gray-400 hover:text-gray-500">
                <span class="text-2xl">&times;</span>
              </button>
            </div>
            
            <!-- Toggle between new and existing student -->
            <div class="mb-6">
              <div class="flex items-center space-x-4">
                <div class="flex items-center">
                  <input id="new-student" name="student_type" type="radio" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300" checked>
                  <label for="new-student" class="ml-2 block text-sm text-gray-700">New Student</label>
                </div>
                <div class="flex items-center">
                  <input id="existing-student" name="student_type" type="radio" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                  <label for="existing-student" class="ml-2 block text-sm text-gray-700">Re-enroll Existing Student</label>
                </div>
              </div>
            </div>

            <!-- Search for existing student (initially hidden) -->
            <div id="existing-student-search" class="mb-6 hidden">
              <label for="student-search" class="block text-sm font-medium text-gray-700 mb-1">Search Student</label>
              <div class="flex space-x-2">
                <input type="text" id="student-search" name="student_search" placeholder="Search by ID or name" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                <button type="button" id="search-student-btn" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                  Search
                </button>
              </div>
              <div id="search-results" class="mt-2 border rounded-md hidden max-h-40 overflow-y-auto">
                <!-- Search results will be populated here -->
              </div>
            </div>

            <form id="enrollmentForm" action="" method="POST" enctype="multipart/form-data" class="space-y-4">
              <input type="hidden" id="student_id" name="student_id" value="">
              <input type="hidden" name="is_re_enroll" id="is_re_enroll" value="0">
              
              <div id="new-student-fields">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label for="first_name" class="block text-sm font-medium text-gray-700">First Name <span class="text-red-500">*</span></label>
                    <input type="text" id="first_name" name="first_name" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                  </div>
                  <div>
                    <label for="middle_name" class="block text-sm font-medium text-gray-700">Middle Name</label>
                    <input type="text" id="middle_name" name="middle_name" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                  </div>
                  <div>
                    <label for="last_name" class="block text-sm font-medium text-gray-700">Last Name <span class="text-red-500">*</span></label>
                    <input type="text" id="last_name" name="last_name" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                  </div>
                  <div>
                    <label for="gender" class="block text-sm font-medium text-gray-700">Gender <span class="text-red-500">*</span></label>
                    <select id="gender" name="gender" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                      <option value="Male">Male</option>
                      <option value="Female">Female</option>
                      <option value="Other">Other</option>
                    </select>
                  </div>
                  <div>
                    <label for="birth_date" class="block text-sm font-medium text-gray-700">Birth Date <span class="text-red-500">*</span></label>
                    <input type="date" id="birth_date" name="birth_date" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                  </div>
                  <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Email <span class="text-red-500">*</span></label>
                    <input type="email" id="email" name="email" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                  </div>
                  <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700">Phone <span class="text-red-500">*</span></label>
                    <input type="tel" id="phone" name="phone" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                  </div>
                  <div>
                    <label for="address" class="block text-sm font-medium text-gray-700">Address <span class="text-red-500">*</span></label>
                    <input type="text" id="address" name="address" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                  </div>
                  <div>
                    <label for="educational_attainment" class="block text-sm font-medium text-gray-700">Educational Attainment <span class="text-red-500">*</span></label>
                    <select id="educational_attainment" name="educational_attainment" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                      <option value="Elementary">Elementary</option>
                      <option value="High School">High School</option>
                      <option value="Senior High School">Senior High School</option>
                      <option value="Vocational">Vocational</option>
                      <option value="College">College</option>
                      <option value="Post Graduate">Post Graduate</option>
                    </select>
                  </div>
                  <div>
                    <label for="documents" class="block text-sm font-medium text-gray-700">Documents (PDF, max 5MB)</label>
                    <input type="file" id="documents" name="documents" accept=".pdf,.jpg,.jpeg,.png" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                  </div>
                </div>
              </div>

              <!-- OCR Upload Section -->
              <div class="border-t border-gray-200 pt-4 mt-4">
                <h4 class="text-md font-medium text-gray-900 mb-4 flex items-center">
                  <span>OCR Document Scanner</span>
                  <span class="ml-2 text-xs text-gray-500">(Upload ID/document to auto-fill form)</span>
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label for="ocr_image" class="block text-sm font-medium text-gray-700">Upload ID or Document Image</label>
                    <input type="file" id="ocr_image" name="ocr_image" accept=".jpg,.jpeg,.png,.pdf" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                  </div>
                  <div class="flex items-end">
                    <button type="button" id="processOcrBtn" class="mt-1 px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                      <span class="flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        Scan & Auto-fill Form
                      </span>
                    </button>
                  </div>
                </div>
                <div id="ocr_preview" class="mt-2 hidden">
                  <div class="border rounded-md p-2 bg-gray-50">
                    <h5 class="text-sm font-medium mb-2">OCR Preview:</h5>
                    <div id="ocr_preview_content" class="text-xs font-mono whitespace-pre-wrap max-h-32 overflow-y-auto"></div>
                  </div>
                </div>
                <div id="ocr_status" class="mt-2 hidden">
                  <div class="p-2 rounded-md"></div>
                </div>
              </div>

              <div class="border-t border-gray-200 pt-4">
                <h4 class="text-md font-medium text-gray-900 mb-4">Enrollment Details</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label for="course" class="block text-sm font-medium text-gray-700">Course <span class="text-red-500">*</span></label>
                    <select id="course" name="course" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                      <?php
                      try {
                        $stmt = $pdo->query("SELECT * FROM courses");
                        while ($course = $stmt->fetch(PDO::FETCH_ASSOC)) {
                          echo "<option value='" . htmlspecialchars($course['name']) . "'>" . htmlspecialchars($course['name']) . "</option>";
                        }
                      } catch (PDOException $e) {
                        echo "<option value=''>Error loading courses</option>";
                      }
                      ?>
                    </select>
                  </div>
                  <div>
                    <label for="enrollment_date" class="block text-sm font-medium text-gray-700">Enrollment Date <span class="text-red-500">*</span></label>
                    <input type="date" id="enrollment_date" name="enrollment_date" required value="<?php echo date('Y-m-d'); ?>" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                  </div>
                </div>
              </div>

              <div class="flex justify-end space-x-3 pt-4 border-t mt-6">
                <button type="button" id="cancelAddStudent" class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                  Cancel
                </button>
                <button type="submit" name="add_student" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                  Enroll Student
                </button>
              </div>
            </form>
          </div>
        </div>

        <!-- Enrollee Details Modal -->
        <div id="enrolleeDetailsModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
          <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-4xl max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
              <h3 class="text-xl font-bold text-gray-800" id="enrolleeDetailsTitle">Enrollee Details</h3>
              <button id="closeDetailsBtn" class="text-gray-500 hover:text-gray-700">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
            
            <div id="enrolleeDetailsContent">
              <!-- Details will be loaded here via AJAX -->
              <div class="animate-pulse">
                <div class="h-4 bg-gray-200 rounded w-3/4 mb-4"></div>
                <div class="h-4 bg-gray-200 rounded w-1/2 mb-4"></div>
                <div class="h-4 bg-gray-200 rounded w-5/6 mb-4"></div>
                <div class="h-4 bg-gray-200 rounded w-2/3 mb-4"></div>
              </div>
            </div>
            
            <div class="flex justify-end mt-6 space-x-3 border-t pt-4">
              <button id="printDetailsBtn" class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                Print Details
              </button>
              <button id="closeDetailsBtnBottom" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                Close
              </button>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <!-- Auto-Hide Toast -->
  <script>
    const toast = document.getElementById('toast');
    if (toast) {
      setTimeout(function() {
        toast.style.opacity = '0';
        setTimeout(function() {
          toast.style.display = 'none';
        }, 500);
      }, 5000);
    }
    
    // Display selected file name for document upload
    document.addEventListener('DOMContentLoaded', function() {
      const fileInput = document.querySelector('input[name="documents"]');
      const fileNameDisplay = document.getElementById('file-name');
      
      if (fileInput && fileNameDisplay) {
        fileInput.addEventListener('change', function() {
          if (this.files.length > 0) {
            fileNameDisplay.textContent = this.files[0].name;
          } else {
            fileNameDisplay.textContent = 'No file chosen';
          }
        });
      }
      
      // Form visibility handling
      const openFormBtn = document.getElementById('openFormBtn');
      const addStudentModal = document.getElementById('addStudentModal');
      const cancelAddStudent = document.getElementById('cancelAddStudent');
      
      if (openFormBtn && addStudentModal && cancelAddStudent) {
        openFormBtn.addEventListener('click', function() {
          addStudentModal.classList.remove('hidden');
        });
        
        cancelAddStudent.addEventListener('click', function() {
          addStudentModal.classList.add('hidden');
        });
      }
      
      // Enrollee details modal handling
      const detailsModal = document.getElementById('enrolleeDetailsModal');
      const closeDetailsBtn = document.getElementById('closeDetailsBtn');
      const closeDetailsBtnBottom = document.getElementById('closeDetailsBtnBottom');
      const printDetailsBtn = document.getElementById('printDetailsBtn');
      
      if (closeDetailsBtn && detailsModal) {
        closeDetailsBtn.addEventListener('click', function() {
          detailsModal.classList.add('hidden');
        });
      }
      
      if (closeDetailsBtnBottom && detailsModal) {
        closeDetailsBtnBottom.addEventListener('click', function() {
          detailsModal.classList.add('hidden');
        });
      }
      
      if (printDetailsBtn) {
        printDetailsBtn.addEventListener('click', function() {
          const content = document.getElementById('enrolleeDetailsContent');
          const printWindow = window.open('', '', 'height=600,width=800');
          
          printWindow.document.write('<html><head><title>Enrollee Details</title>');
          printWindow.document.write('<link rel="stylesheet" href="https://cdn.tailwindcss.com">');
          printWindow.document.write('</head><body class="p-4">');
          printWindow.document.write('<h1 class="text-xl font-bold mb-4">Enrollee Details</h1>');
          printWindow.document.write(content.innerHTML);
          printWindow.document.write('</body></html>');
          
          printWindow.document.close();
          printWindow.focus();
          setTimeout(function() {
            printWindow.print();
            printWindow.close();
          }, 1000);
        });
      }
      
      // Print functionality
      const printBtn = document.getElementById('printBtn');
      if (printBtn) {
        printBtn.addEventListener('click', function() {
          window.print();
        });
      }
      
      // Filter functionality
      const filterBtn = document.getElementById('filterBtn');
      const filterDropdown = document.getElementById('filterDropdown');
      const applyFilterBtn = document.getElementById('applyFilterBtn');
      const courseFilter = document.getElementById('courseFilter');
      const statusFilter = document.getElementById('statusFilter');
      const filterIndicator = document.getElementById('filterIndicator');
      
      // Toggle filter dropdown
      if (filterBtn && filterDropdown) {
        filterBtn.addEventListener('click', function() {
          filterDropdown.classList.toggle('hidden');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
          if (!filterBtn.contains(event.target) && !filterDropdown.contains(event.target)) {
            filterDropdown.classList.add('hidden');
          }
        });
      }
      
      // Apply filter
      if (applyFilterBtn && courseFilter && statusFilter) {
        applyFilterBtn.addEventListener('click', function() {
          const course = courseFilter.value;
          const status = statusFilter.value;
          
          // Redirect with filter parameters
          let url = window.location.pathname;
          let params = [];
          
          if (course) {
            params.push('course=' + encodeURIComponent(course));
          }
          
          if (status) {
            params.push('status=' + encodeURIComponent(status));
          }
          
          if (params.length > 0) {
            url += '?' + params.join('&');
          }
          
          window.location.href = url;
        });
      }
      
      // Show filter indicator if filters are active
      if (filterIndicator) {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('course') || urlParams.has('status')) {
          filterIndicator.classList.remove('hidden');
        }
      }
      
      // Toggle between new and existing student
      const newStudentRadio = document.getElementById('new-student');
      const existingStudentRadio = document.getElementById('existing-student');
      const existingStudentSearch = document.getElementById('existing-student-search');
      const newStudentFields = document.getElementById('new-student-fields');
      
      if (newStudentRadio && existingStudentRadio && existingStudentSearch && newStudentFields) {
        newStudentRadio.addEventListener('change', function() {
          existingStudentSearch.classList.add('hidden');
          newStudentFields.classList.remove('hidden');
        });
        
        existingStudentRadio.addEventListener('change', function() {
          existingStudentSearch.classList.remove('hidden');
          newStudentFields.classList.add('hidden');
        });
      }
      
      // Search for existing student
      const searchStudentBtn = document.getElementById('search-student-btn');
      const searchResults = document.getElementById('search-results');
      
      if (searchStudentBtn && searchResults) {
        searchStudentBtn.addEventListener('click', function() {
          const studentSearchInput = document.getElementById('student-search');
          const studentId = studentSearchInput.value;
          
          // Fetch student data
          fetch(`get_student_data.php?id=${studentId}`)
            .then(response => response.text())
            .then(data => {
              searchResults.innerHTML = data;
              searchResults.classList.remove('hidden');
            })
            .catch(error => {
              searchResults.innerHTML = '<p>Error loading student data: ' + error.message + '</p>';
              searchResults.classList.remove('hidden');
            });
        });
      }
      
      // Event delegation to handle selecting a student from search results
      document.addEventListener('click', function(e) {
        if (e.target.closest('.student-item')) {
          const studentItem = e.target.closest('.student-item');
          const studentId = studentItem.dataset.id;
          
          // Set the student ID in the hidden field
          document.getElementById('student_id').value = studentId;
          document.getElementById('is_re_enroll').value = '1';
          
          // Hide the search results
          document.getElementById('search-results').classList.add('hidden');
          
          // Show a confirmation message
          const searchContainer = document.getElementById('existing-student-search');
          const confirmationMsg = document.createElement('div');
          confirmationMsg.className = 'mt-3 p-2 bg-green-50 text-green-700 border border-green-200 rounded-md';
          confirmationMsg.innerHTML = `<strong>Student selected:</strong> ${studentItem.querySelector('.font-medium').textContent}`;
          
          // Remove any existing confirmation message
          const existingMsg = searchContainer.querySelector('.bg-green-50');
          if (existingMsg) {
            existingMsg.remove();
          }
          
          searchContainer.appendChild(confirmationMsg);
        }
      });
      
      // OCR Functionality
      const ocrImageInput = document.getElementById('ocr_image');
      const processOcrBtn = document.getElementById('processOcrBtn');
      const ocrPreview = document.getElementById('ocr_preview');
      const ocrPreviewContent = document.getElementById('ocr_preview_content');
      const ocrStatus = document.getElementById('ocr_status');
      
      if (processOcrBtn && ocrImageInput) {
        processOcrBtn.addEventListener('click', function() {
          if (!ocrImageInput.files || ocrImageInput.files.length === 0) {
            showOcrStatus('Please select an image file first', 'error');
            return;
          }
          
          const file = ocrImageInput.files[0];
          const formData = new FormData();
          formData.append('ocr_image', file);
          
          // Show loading state
          processOcrBtn.disabled = true;
          processOcrBtn.innerHTML = '<svg class="animate-spin h-5 w-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Processing...';
          showOcrStatus('Processing document...', 'info');
          
          // Send to OCR endpoint
          fetch('process_ocr.php', {
            method: 'POST',
            body: formData
          })
          .then(response => {
            if (!response.ok) {
              throw new Error('Network response was not ok');
            }
            return response.json();
          })
          .then(data => {
            // Reset button state
            processOcrBtn.disabled = false;
            processOcrBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg> Scan & Auto-fill Form';
            
            if (data.success) {
              // Show OCR text preview
              ocrPreview.classList.remove('hidden');
              ocrPreviewContent.textContent = data.text;
              
              // Auto-fill form fields
              if (data.fields) {
                populateFormFields(data.fields);
                showOcrStatus('Form fields have been auto-filled!', 'success');
              } else {
                showOcrStatus('Text extracted but no structured data found. Please fill the form manually.', 'warning');
              }
            } else {
              showOcrStatus(data.message || 'OCR processing failed', 'error');
            }
          })
          .catch(error => {
            console.error('Error:', error);
            processOcrBtn.disabled = false;
            processOcrBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg> Scan & Auto-fill Form';
            showOcrStatus('Error processing the document. Please try again.', 'error');
          });
        });
      }
      
      // Helper function to populate form fields
      function populateFormFields(fields) {
        // Map OCR extracted fields to form fields
        const fieldMapping = {
          first_name: 'first_name',
          middle_name: 'middle_name',
          last_name: 'last_name',
          email: 'email',
          phone: 'phone',
          birth_date: 'birth_date',
          address: 'address'
        };
        
        // Populate each field if data is available
        for (const [ocrField, formField] of Object.entries(fieldMapping)) {
          if (fields[ocrField] && document.getElementById(formField)) {
            document.getElementById(formField).value = fields[ocrField];
            
            // Highlight the filled field
            document.getElementById(formField).classList.add('bg-green-50');
            setTimeout(() => {
              document.getElementById(formField).classList.remove('bg-green-50');
            }, 2000);
          }
        }
      }
      
      // Helper function to show OCR status messages
      function showOcrStatus(message, type) {
        ocrStatus.classList.remove('hidden');
        const statusDiv = ocrStatus.querySelector('div');
        
        // Clear previous classes
        statusDiv.className = 'p-2 rounded-md';
        
        // Set appropriate styling based on message type
        switch(type) {
          case 'success':
            statusDiv.classList.add('bg-green-50', 'text-green-700', 'border', 'border-green-200');
            break;
          case 'error':
            statusDiv.classList.add('bg-red-50', 'text-red-700', 'border', 'border-red-200');
            break;
          case 'warning':
            statusDiv.classList.add('bg-yellow-50', 'text-yellow-700', 'border', 'border-yellow-200');
            break;
          case 'info':
          default:
            statusDiv.classList.add('bg-blue-50', 'text-blue-700', 'border', 'border-blue-200');
        }
        
        statusDiv.textContent = message;
      }
    });
    
    // Function to open enrollee details
    function openEnrolleeDetails(id) {
      const modal = document.getElementById('enrolleeDetailsModal');
      const content = document.getElementById('enrolleeDetailsContent');
      
      if (modal && content) {
        // Show modal with loading state
        modal.classList.remove('hidden');
        content.innerHTML = `
          <div class="animate-pulse">
            <div class="h-4 bg-gray-200 rounded w-3/4 mb-4"></div>
            <div class="h-4 bg-gray-200 rounded w-1/2 mb-4"></div>
            <div class="h-4 bg-gray-200 rounded w-5/6 mb-4"></div>
            <div class="h-4 bg-gray-200 rounded w-2/3 mb-4"></div>
          </div>
        `;
        
        // Fetch enrollee details
        fetch(`get_enrollee_details.php?id=${id}`)
          .then(response => response.text())
          .then(data => {
            content.innerHTML = data;
          })
          .catch(error => {
            content.innerHTML = `
              <div class="text-red-500">
                <p>Error loading enrollee details: ${error.message}</p>
                <p>Please try again later.</p>
              </div>
            `;
          });
      }
    }
    
    // Table sorting functionality
    function sortTable(columnIndex) {
      const table = document.getElementById('enrolledTable');
      const tbody = table.querySelector('tbody');
      const rows = Array.from(tbody.querySelectorAll('tr'));
      
      // Determine sort direction
      const sortDirection = table.getAttribute('data-sort-dir') === 'asc' ? 'desc' : 'asc';
      table.setAttribute('data-sort-dir', sortDirection);
      
      // Sort the rows
      rows.sort((a, b) => {
        let aValue = a.querySelectorAll('td')[columnIndex].textContent.trim();
        let bValue = b.querySelectorAll('td')[columnIndex].textContent.trim();
        
        // Special handling for dates
        if (columnIndex === 3) { // Enrollment Date column
          aValue = new Date(aValue);
          bValue = new Date(bValue);
        }
        
        if (sortDirection === 'asc') {
          if (aValue < bValue) return -1;
          if (aValue > bValue) return 1;
          return 0;
        } else {
          if (aValue < bValue) return 1;
          if (aValue > bValue) return -1;
          return 0;
        }
      });
      
      // Clear and re-append rows in new order
      while (tbody.firstChild) {
        tbody.removeChild(tbody.firstChild);
      }
      
      rows.forEach(row => {
        tbody.appendChild(row);
      });
    }
  </script>

  <!-- Animation -->
  <style>
    @keyframes fade-in-down {
      from {
        opacity: 0;
        transform: translateY(-10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    .animate-fade-in-down {
      animation: fade-in-down 0.5s ease-out;
    }
  </style>
</body>
</html>