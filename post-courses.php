<?php
session_start();
require 'db.php';

// CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Move uploads directory to project root
$uploadDir = '../uploads/'; // Relative path from /admin to root/uploads/
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// DELETE course with CSRF protection
if (isset($_GET['delete_id']) && isset($_GET['csrf_token'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'])) {
        $_SESSION['error'] = "Invalid CSRF token.";
        header("Location: post-courses.php");
        exit;
    }
    
    $id = intval($_GET['delete_id']);
    try {
        $stmt = $pdo->prepare("SELECT course_image FROM courses WHERE id = ?");
        $stmt->execute([$id]);
        $course = $stmt->fetch();

        if ($course && $course['course_image']) {
            $imagePath = $uploadDir . $course['course_image'];
            if (file_exists($imagePath)) unlink($imagePath);
        }

        $pdo->prepare("DELETE FROM courses WHERE id = ?")->execute([$id]);
        $_SESSION['success'] = "Course deleted successfully.";
        header("Location: post-courses.php?deleted=1");
        exit;
    } catch (PDOException $e) {
        $_SESSION['error'] = "Database error: " . $e->getMessage();
        header("Location: post-courses.php");
        exit;
    }
}

// CREATE or UPDATE course
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid CSRF token.";
        header("Location: post-courses.php");
        exit;
    }

    $courseName = trim($_POST['course_name']);
    $courseHours = intval($_POST['course_hours']);
    $courseCategory = trim($_POST['course_category'] ?? '');
    $courseDescription = trim($_POST['course_description'] ?? '');
    $image = $_FILES['course_image'];
    $editMode = isset($_POST['edit_id']) && !empty($_POST['edit_id']);
    $imageName = null;

    if (empty($courseName) || $courseHours <= 0) {
        $_SESSION['error'] = "Course name and positive hours are required.";
        header("Location: post-courses.php" . ($editMode ? "?edit_id=" . intval($_POST['edit_id']) : ""));
        exit;
    }

    // Handle image upload
    if (!empty($image['name'])) {
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
        $imageType = strtolower(pathinfo($image['name'], PATHINFO_EXTENSION));

        if (!in_array($imageType, $allowedTypes)) {
            $_SESSION['error'] = "Invalid image format.";
            header("Location: post-courses.php" . ($editMode ? "?edit_id=" . intval($_POST['edit_id']) : ""));
            exit;
        }

        if ($image['size'] > 2 * 1024 * 1024) {
            $_SESSION['error'] = "Image too large (max 2MB).";
            header("Location: post-courses.php" . ($editMode ? "?edit_id=" . intval($_POST['edit_id']) : ""));
            exit;
        }

        $imageName = uniqid('course_', true) . '.' . $imageType;
        if (!move_uploaded_file($image['tmp_name'], $uploadDir . $imageName)) {
            $_SESSION['error'] = "Failed to upload image.";
            header("Location: post-courses.php" . ($editMode ? "?edit_id=" . intval($_POST['edit_id']) : ""));
            exit;
        }
    }

    if ($editMode) {
        $id = intval($_POST['edit_id']);
        try {
            if ($imageName) {
                // Remove old image
                $stmt = $pdo->prepare("SELECT course_image FROM courses WHERE id = ?");
                $stmt->execute([$id]);
                $old = $stmt->fetch();
                if ($old && $old['course_image']) {
                    $oldPath = $uploadDir . $old['course_image'];
                    if (file_exists($oldPath)) unlink($oldPath);
                }

                $stmt = $pdo->prepare("UPDATE courses SET course_name=?, course_hours=?, course_category=?, course_description=?, course_image=?, updated_at=NOW() WHERE id=?");
                $stmt->execute([$courseName, $courseHours, $courseCategory, $courseDescription, $imageName, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE courses SET course_name=?, course_hours=?, course_category=?, course_description=?, updated_at=NOW() WHERE id=?");
                $stmt->execute([$courseName, $courseHours, $courseCategory, $courseDescription, $id]);
            }

            $_SESSION['success'] = "Course updated successfully.";
            header("Location: post-courses.php?updated=1");
            exit;
        } catch (PDOException $e) {
            $_SESSION['error'] = "Database error: " . $e->getMessage();
            header("Location: post-courses.php?edit_id=" . $id);
            exit;
        }
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO courses (course_name, course_hours, course_category, course_description, course_image, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
            $stmt->execute([$courseName, $courseHours, $courseCategory, $courseDescription, $imageName]);
            $_SESSION['success'] = "Course posted successfully.";
            header("Location: post-courses.php?success=1");
            exit;
        } catch (PDOException $e) {
            $_SESSION['error'] = "Database error: " . $e->getMessage();
            header("Location: post-courses.php");
            exit;
        }
    }
}

// Fetch courses and edit data if needed
$courses = $pdo->query("SELECT * FROM courses ORDER BY id DESC")->fetchAll();
$editCourse = null;
if (isset($_GET['edit_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
    $stmt->execute([intval($_GET['edit_id'])]);
    $editCourse = $stmt->fetch();
}

// Check if course_category column exists
$columnsExist = true;
try {
    $pdo->query("SELECT course_category FROM courses LIMIT 1");
} catch (PDOException $e) {
    $columnsExist = false;
}

// Only query categories if the column exists
$categories = [];
if ($columnsExist) {
    try {
        $categories = $pdo->query("SELECT DISTINCT course_category FROM courses WHERE course_category IS NOT NULL AND course_category != ''")->fetchAll();
    } catch (PDOException $e) {
        // If there's still an error, just use an empty array
        $categories = [];
    }
}

$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? '';

if (!empty($search)) {
    // Adjust the search query based on whether course_description exists
    if ($columnsExist) {
        $stmt = $pdo->prepare("SELECT * FROM courses WHERE course_name LIKE :search_name OR course_description LIKE :search_desc ORDER BY id DESC");
        $stmt->execute([
            'search_name' => '%' . $search . '%',
            'search_desc' => '%' . $search . '%'
        ]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM courses WHERE course_name LIKE :search_name ORDER BY id DESC");
        $stmt->execute(['search_name' => '%' . $search . '%']);
    }
    $courses = $stmt->fetchAll();
} elseif (!empty($filter) && $filter !== 'all' && $columnsExist) {
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE course_category = :filter ORDER BY id DESC");
    $stmt->execute(['filter' => $filter]);
    $courses = $stmt->fetchAll();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Courses | TESDA Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
    <!-- Main -->
    <main class="flex-1 overflow-y-auto">
        <!-- Top navigation bar with user profile -->
        <div class="bg-white shadow-sm sticky top-0 z-10">
            <div class="max-w-full mx-auto px-6 py-3">
                <div class="flex justify-between items-center">
                    <h2 class="text-3xl font-bold text-blue-900"><?= $editCourse ? "Edit Course" : "Course Management" ?></h2>
                    
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
            <!-- Action Buttons -->
            <div class="flex gap-3 mb-6">
                <button id="addCourseBtn" class="bg-blue-500 hover:bg-blue-600 text-white rounded-md px-4 py-2 flex items-center gap-2 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                    </svg>
                    Add Course
                </button>
                
                <button id="filterCoursesBtn" class="border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 rounded-md px-4 py-2 flex items-center gap-2 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                    </svg>
                    Filter
                </button>
            </div>
            
            <!-- Form -->
            <form action="post-courses.php" method="POST" enctype="multipart/form-data" class="bg-white p-6 rounded shadow-md mb-8 <?= isset($_GET['edit_id']) ? '' : 'hidden' ?>" id="courseForm">
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="bg-red-100 text-red-700 p-3 rounded mb-4"><?= htmlspecialchars($_SESSION['error']) ?></div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <input type="hidden" name="edit_id" value="<?= $editCourse['id'] ?? '' ?>">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="mb-4">
                        <label class="block font-semibold mb-2">Course Name <span class="text-red-500">*</span></label>
                        <input type="text" name="course_name" value="<?= htmlspecialchars($editCourse['course_name'] ?? '') ?>" class="w-full p-3 border rounded focus:ring-blue-500 focus:border-blue-500" required>
                    </div>

                    <div class="mb-4">
                        <label class="block font-semibold mb-2">Course Hours <span class="text-red-500">*</span></label>
                        <input type="number" name="course_hours" value="<?= htmlspecialchars($editCourse['course_hours'] ?? '') ?>" class="w-full p-3 border rounded focus:ring-blue-500 focus:border-blue-500" required min="1">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="mb-4">
                        <label class="block font-semibold mb-2">Course Category</label>
                        <input type="text" name="course_category" list="category-list" value="<?= htmlspecialchars($editCourse['course_category'] ?? '') ?>" class="w-full p-3 border rounded focus:ring-blue-500 focus:border-blue-500" placeholder="Select or type a category">
                        <datalist id="category-list">
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= htmlspecialchars($category['course_category']) ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>

                    <div class="mb-4">
                        <label class="block font-semibold mb-2">Course Image</label>
                        <div class="flex items-center">
                            <label class="cursor-pointer bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-300 rounded p-2 transition-colors">
                                <span id="file-label">Choose file</span>
                                <input type="file" name="course_image" id="image-upload" class="hidden" accept="image/*" onchange="previewImage()">
                            </label>
                            <span id="file-name" class="ml-3 text-sm text-gray-500">No file chosen</span>
                        </div>
                        <div id="image-preview-container" class="mt-3 <?= ($editCourse && $editCourse['course_image']) ? '' : 'hidden' ?>">
                            <img id="image-preview" src="<?= ($editCourse && $editCourse['course_image']) ? '../uploads/'.htmlspecialchars($editCourse['course_image']) : '' ?>" class="h-28 rounded border border-gray-300" alt="Preview">
                        </div>
                        <p class="text-sm text-gray-500 mt-1">Max 2MB (JPG, PNG, GIF). Leave blank to keep existing.</p>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block font-semibold mb-2">Course Description</label>
                    <textarea name="course_description" class="w-full p-3 border rounded focus:ring-blue-500 focus:border-blue-500" rows="5" placeholder="Enter details about the course content, goals, and requirements"><?= htmlspecialchars($editCourse['course_description'] ?? '') ?></textarea>
                </div>

                <div class="flex items-center justify-between">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white py-3 px-6 rounded flex items-center transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <?= $editCourse ? "Update Course" : "Post Course" ?>
                    </button>
                    
                    <?php if ($editCourse): ?>
                        <a href="post-courses.php" class="bg-gray-500 hover:bg-gray-600 text-white py-2 px-4 rounded transition-colors">
                            Cancel Edit
                        </a>
                    <?php endif; ?>
                </div>
            </form>

            <!-- Search and Filter Section -->
            <div class="bg-white p-6 rounded shadow-md mb-6">
                <h3 class="text-xl font-semibold mb-4">Find Courses</h3>
                <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500" placeholder="Search by name or description...">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                        <select name="filter" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <option value="all" <?= $filter === 'all' || empty($filter) ? 'selected' : '' ?>>All Categories</option>
                            <?php foreach($categories as $category): ?>
                                <option value="<?= htmlspecialchars($category['course_category']) ?>" <?= $filter === $category['course_category'] ? 'selected' : '' ?>><?= htmlspecialchars($category['course_category']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md transition-colors flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            Search
                        </button>
                        <?php if (!empty($search) || (!empty($filter) && $filter !== 'all')): ?>
                            <a href="post-courses.php" class="ml-2 text-gray-600 hover:text-gray-800 px-4 py-2 rounded-md border border-gray-300 hover:bg-gray-50 transition-colors">
                                Clear
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Course List -->
            <div class="bg-white p-6 rounded shadow-md">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-2xl font-bold">Available Courses</h3>
                    <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-medium">
                        <?= count($courses) ?> Course<?= count($courses) != 1 ? 's' : '' ?>
                    </span>
                </div>
                
                <?php if (count($courses)): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($courses as $course): ?>
                            <div class="border border-gray-200 rounded-lg overflow-hidden shadow-sm hover:shadow-md transition-all hover-scale">
                                <div class="h-40 bg-gray-100 overflow-hidden">
                                    <?php if ($course['course_image']): ?>
                                        <img src="../uploads/<?= htmlspecialchars($course['course_image']) ?>" class="w-full h-full object-cover" alt="<?= htmlspecialchars($course['course_name']) ?>">
                                    <?php else: ?>
                                        <div class="w-full h-full flex items-center justify-center bg-gray-200">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="p-4">
                                    <div class="flex justify-between">
                                        <h4 class="text-lg font-semibold text-gray-800"><?= htmlspecialchars($course['course_name']) ?></h4>
                                        <span class="text-blue-600 font-medium"><?= htmlspecialchars($course['course_hours']) ?> hrs</span>
                                    </div>
                                    
                                    <?php if (!empty($course['course_category'])): ?>
                                        <span class="inline-block bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full mt-2">
                                            <?= htmlspecialchars($course['course_category']) ?>
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($course['course_description'])): ?>
                                        <p class="text-sm text-gray-600 mt-2 line-clamp-2"><?= htmlspecialchars($course['course_description']) ?></p>
                                    <?php endif; ?>
                                    
                                    <div class="flex justify-between mt-4">
                                        <span class="text-sm text-gray-500">
                                            <?php if (isset($course['created_at'])): ?>
                                                Added: <?= date('M d, Y', strtotime($course['created_at'])) ?>
                                            <?php endif; ?>
                                        </span>
                                        <div class="flex space-x-2">
                                            <a href="post-courses.php?edit_id=<?= $course['id'] ?>" class="bg-yellow-400 hover:bg-yellow-500 text-white py-1 px-3 rounded text-sm transition-colors flex items-center">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" />
                                                </svg>
                                                Edit
                                            </a>
                                            <button onclick="confirmDelete(<?= $course['id'] ?>)" class="bg-red-500 hover:bg-red-600 text-white py-1 px-3 rounded text-sm transition-colors flex items-center">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                                Delete
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8 text-gray-500">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                        <p class="mt-2">No courses found.</p>
                        <?php if (!empty($search) || (!empty($filter) && $filter !== 'all')): ?>
                            <p class="mt-1">Try adjusting your search criteria.</p>
                        <?php else: ?>
                            <p class="mt-1">Add a new course using the form above.</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- SweetAlert -->
<script>
<?php if (isset($_SESSION['success'])): ?>
    Swal.fire({ icon: 'success', title: 'Success!', text: '<?= htmlspecialchars($_SESSION['success']) ?>', timer: 2000, showConfirmButton: false });
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>

<?php if (isset($_GET['success'])): ?>
    Swal.fire({ icon: 'success', title: 'Posted!', text: 'Course posted successfully.', timer: 2000, showConfirmButton: false });
<?php elseif (isset($_GET['updated'])): ?>
    Swal.fire({ icon: 'success', title: 'Updated!', text: 'Course updated successfully.', timer: 2000, showConfirmButton: false });
<?php elseif (isset($_GET['deleted'])): ?>
    Swal.fire({ icon: 'success', title: 'Deleted!', text: 'Course deleted successfully.', timer: 2000, showConfirmButton: false });
<?php endif; ?>

function confirmDelete(id) {
    Swal.fire({
        title: 'Delete this course?',
        text: "This action cannot be undone.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'post-courses.php?delete_id=' + id + '&csrf_token=<?= $_SESSION['csrf_token'] ?>';
        }
    });
}

function previewImage() {
    const input = document.getElementById('image-upload');
    const preview = document.getElementById('image-preview');
    const container = document.getElementById('image-preview-container');
    const fileLabel = document.getElementById('file-name');
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            preview.src = e.target.result;
            container.classList.remove('hidden');
        }
        
        reader.readAsDataURL(input.files[0]);
        fileLabel.textContent = input.files[0].name;
    }
}

// Add validation for form submission
document.querySelector('form[action="post-courses.php"]').addEventListener('submit', function(e) {
    const courseName = document.querySelector('input[name="course_name"]').value.trim();
    const courseHours = parseInt(document.querySelector('input[name="course_hours"]').value);
    
    if (courseName === '') {
        e.preventDefault();
        Swal.fire({
            icon: 'error',
            title: 'Validation Error',
            text: 'Course name is required'
        });
        return false;
    }
    
    if (isNaN(courseHours) || courseHours <= 0) {
        e.preventDefault();
        Swal.fire({
            icon: 'error',
            title: 'Validation Error',
            text: 'Course hours must be a positive number'
        });
        return false;
    }
    
    return true;
});

// Toggle course form visibility
document.getElementById('addCourseBtn').addEventListener('click', function() {
    const form = document.getElementById('courseForm');
    form.classList.toggle('hidden');
    
    // Scroll to the form
    if (!form.classList.contains('hidden')) {
        form.scrollIntoView({ behavior: 'smooth' });
    }
});
</script>
</body>
</html>