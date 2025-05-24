<?php
require_once 'db.php';

// Initialize response
$response = '';

// Get search term
$search = $_GET['id'] ?? '';

if (empty($search)) {
    echo '<p class="p-2 text-gray-500">Please enter a student ID or name to search</p>';
    exit;
}

try {
    // Build a SQL query that works with the existing database structure
    $sql = "SELECT id, first_name, middle_name, last_name 
           FROM enrollees 
           WHERE (id = :search 
               OR CONCAT(first_name, ' ', last_name) LIKE :name_search)
           GROUP BY id
           LIMIT 10";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'search' => $search,
        'name_search' => '%' . $search . '%'
    ]);
    
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($students) > 0) {
        $response .= '<div class="divide-y divide-gray-200">';
        foreach ($students as $student) {
            $fullName = htmlspecialchars($student['first_name'] . ' ' . 
                       ($student['middle_name'] ? $student['middle_name'] . ' ' : '') . 
                       $student['last_name']);
            $id = htmlspecialchars($student['id']);
            
            $response .= '<div class="p-2 hover:bg-gray-100 cursor-pointer student-item" 
                         data-id="' . $id . '">';
            $response .= '<div class="flex justify-between">
                            <span class="font-medium">' . $fullName . '</span>
                            <span class="text-sm text-gray-500">ID: ' . $id . '</span>
                          </div>';
            $response .= '</div>';
        }
        $response .= '</div>';
    } else {
        $response .= '<p class="p-2 text-gray-500">No students found</p>';
    }
} catch (PDOException $e) {
    $response = '<p class="p-2 text-red-500">Error: ' . $e->getMessage() . '</p>';
}

echo $response;
?>
