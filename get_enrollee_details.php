<?php
session_start();
require 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo '<div class="text-red-500 p-4">Unauthorized access. Please log in.</div>';
    exit;
}

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo '<div class="text-red-500 p-4">Invalid enrollee ID.</div>';
    exit;
}

$id = (int)$_GET['id'];

try {
    // Fetch enrollee details
    $stmt = $pdo->prepare("SELECT * FROM enrollees WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $enrollee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$enrollee) {
        echo '<div class="text-red-500 p-4">Enrollee not found.</div>';
        exit;
    }
    
    // Format the data for display
    $fullName = trim($enrollee['first_name'] . ' ' . $enrollee['middle_name'] . ' ' . $enrollee['last_name']);
    $gender = $enrollee['gender'] ?? 'Not specified';
    $birthDate = !empty($enrollee['birth_date']) ? date('F d, Y', strtotime($enrollee['birth_date'])) : 'Not specified';
    $email = !empty($enrollee['email']) ? $enrollee['email'] : 'Not provided';
    $phone = !empty($enrollee['phone']) ? $enrollee['phone'] : 'Not provided';
    $address = !empty($enrollee['address']) ? $enrollee['address'] : 'Not provided';
    $course = $enrollee['course'];
    $status = ucfirst($enrollee['status']);
    $createdAt = date('F d, Y g:i A', strtotime($enrollee['created_at']));
    $updatedAt = !empty($enrollee['updated_at']) ? date('F d, Y g:i A', strtotime($enrollee['updated_at'])) : 'Not updated';
    $educational = !empty($enrollee['educational_attainment']) ? $enrollee['educational_attainment'] : 'Not specified';
    
    // Get age if birth date is available
    $age = '';
    if (!empty($enrollee['birth_date'])) {
        $birthDate = new DateTime($enrollee['birth_date']);
        $today = new DateTime();
        $age = $birthDate->diff($today)->y;
    }
    
    // Check if birth certificate file exists
    $birthCertFile = $enrollee['birth_certificate_file'] ?? '';
    if (empty($birthCertFile)) {
        $birthCertFile = $enrollee['birth_cert_681f833c5e94c6_8951ef2459'] ?? '';  // Use field name from database
    }
    $hasBirthCert = !empty($birthCertFile);
    
    // Get the age from the database or calculate it
    $applicantAge = $enrollee['age'] ?? $age ?? 'Not specified';
    
    // Get employment details 
    $employmentStatus = $enrollee['employment_status'] ?? 'Not specified';
    $employmentType = $enrollee['employment_type'] ?? 'Not specified';
    
    // Additional details that may be in your database
    $birthplaceCity = $enrollee['birthplace_city'] ?? 'Not specified';
    $birthplaceProvince = $enrollee['birthplace_province'] ?? 'Not specified';
    $disability = $enrollee['disability'] ?? 'None';
    $disabilityCause = $enrollee['disability_cause'] ?? 'N/A';
    
    // Get civil status from the database
    $civilStatus = $enrollee['civil_status'] ?? 'Not specified';
    
    // Output HTML for the modal
    ?>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
        <!-- Personal Information Section -->
        <div class="col-span-2 bg-blue-50 p-4 rounded-lg">
            <h4 class="font-semibold text-blue-800 mb-2">Personal Information</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-gray-600">Full Name:</p>
                    <p class="font-medium"><?php echo htmlspecialchars($fullName); ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Gender:</p>
                    <p class="font-medium"><?php echo htmlspecialchars($gender); ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Birth Date:</p>
                    <p class="font-medium"><?php echo htmlspecialchars($birthDate); ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Age:</p>
                    <p class="font-medium"><?php echo htmlspecialchars($applicantAge); ?></p>
                </div>
                <?php if (!empty($civilStatus)): ?>
                <div>
                    <p class="text-sm text-gray-600">Civil Status:</p>
                    <p class="font-medium"><?php echo htmlspecialchars($civilStatus); ?></p>
                </div>
                <?php endif; ?>
                <?php if (!empty($birthplaceCity) || !empty($birthplaceProvince)): ?>
                <div>
                    <p class="text-sm text-gray-600">Birthplace:</p>
                    <p class="font-medium">
                        <?php 
                        $birthplace = [];
                        if (!empty($birthplaceCity)) $birthplace[] = $birthplaceCity;
                        if (!empty($birthplaceProvince)) $birthplace[] = $birthplaceProvince;
                        echo htmlspecialchars(implode(', ', $birthplace));
                        ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Contact Information Section -->
        <div class="bg-gray-50 p-4 rounded-lg">
            <h4 class="font-semibold text-blue-800 mb-2">Contact Information</h4>
            <div class="space-y-2">
                <div>
                    <p class="text-sm text-gray-600">Email:</p>
                    <p class="font-medium"><?php echo htmlspecialchars($email); ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Phone:</p>
                    <p class="font-medium"><?php echo htmlspecialchars($phone); ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Address:</p>
                    <p class="font-medium"><?php echo htmlspecialchars($address); ?></p>
                </div>
            </div>
        </div>
        
        <!-- Educational & Employment Section -->
        <div class="bg-gray-50 p-4 rounded-lg">
            <h4 class="font-semibold text-blue-800 mb-2">Education & Employment</h4>
            <div class="space-y-2">
                <div>
                    <p class="text-sm text-gray-600">Educational Attainment:</p>
                    <p class="font-medium"><?php echo htmlspecialchars($educational); ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Employment Status:</p>
                    <p class="font-medium"><?php echo htmlspecialchars($employmentStatus); ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Employment Type:</p>
                    <p class="font-medium"><?php echo htmlspecialchars($employmentType); ?></p>
                </div>
                <?php if (!empty($disability) && $disability != 'None'): ?>
                <div>
                    <p class="text-sm text-gray-600">Disability:</p>
                    <p class="font-medium"><?php echo htmlspecialchars($disability); ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Disability Cause:</p>
                    <p class="font-medium"><?php echo htmlspecialchars($disabilityCause); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Application Details Section -->
        <div class="col-span-2 bg-blue-50 p-4 rounded-lg">
            <h4 class="font-semibold text-blue-800 mb-2">Application Details</h4>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <p class="text-sm text-gray-600">Course Applied:</p>
                    <p class="font-medium"><?php echo htmlspecialchars($course); ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Application Status:</p>
                    <p class="font-medium">
                        <span class="inline-block px-2 py-1 text-xs font-semibold rounded 
                            <?php 
                            echo match(strtolower($status)) {
                                'approved' => 'bg-green-100 text-green-800',
                                'rejected' => 'bg-red-100 text-red-800',
                                'graduated' => 'bg-purple-100 text-purple-800',
                                default => 'bg-yellow-100 text-yellow-800',
                            }; 
                            ?>">
                            <?php echo $status; ?>
                        </span>
                    </p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Application Date:</p>
                    <p class="font-medium"><?php echo htmlspecialchars($createdAt); ?></p>
                </div>
                <?php if (!empty($enrollee['enrollment_date'])): ?>
                <div>
                    <p class="text-sm text-gray-600">Enrollment Date:</p>
                    <p class="font-medium"><?php echo date('F d, Y', strtotime($enrollee['enrollment_date'])); ?></p>
                </div>
                <?php endif; ?>
                <?php if ($hasBirthCert): ?>
                <div class="col-span-3">
                    <p class="text-sm text-gray-600">Birth Certificate/Supporting Documents:</p>
                    <a href="uploads/<?php echo htmlspecialchars($birthCertFile); ?>" target="_blank" class="text-blue-600 hover:underline flex items-center gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122" />
                        </svg>
                        View Birth Certificate
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Admin Actions Section -->
        <div class="col-span-2 border-t pt-4 mt-2">
            <h4 class="font-semibold text-blue-800 mb-2">Actions</h4>
            <div class="flex flex-wrap gap-2">
                <?php if ($status == 'Pending'): ?>
                <a href="manage-enrollment.php?action=approve&id=<?php echo $id; ?>" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded flex items-center gap-1">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    Approve Application
                </a>
                <a href="manage-enrollment.php?action=reject&id=<?php echo $id; ?>" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded flex items-center gap-1">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    Reject Application
                </a>
                <?php endif; ?>
                <button type="button" onclick="printEnrolleeDetails()" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded flex items-center gap-1">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    Print Details
                </button>
            </div>
        </div>
    </div>
    
    <script>
    function printEnrolleeDetails() {
        window.print();
    }
    </script>
    <?php
    
} catch (PDOException $e) {
    echo '<div class="text-red-500 p-4">Error retrieving enrollee details: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>
