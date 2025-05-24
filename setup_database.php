<?php
/**
 * TESDA Admin Database Setup Script
 * 
 * This script automatically creates the necessary tables for the TESDA Admin system.
 * Run this once to set up your database.
 */

// Include database connection
require_once 'db.php';

echo "<h1>TESDA Admin Database Setup</h1>";

try {
    // Begin transaction for all operations
    $pdo->beginTransaction();
    
    echo "<h2>Creating database tables...</h2>";
    
    // Create admin_users table
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_users (
        id INT NOT NULL AUTO_INCREMENT,
        username VARCHAR(50) NOT NULL,
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(100) NULL,
        email VARCHAR(100) NULL,
        admin_level ENUM('super_admin', 'admin', 'manager', 'staff') NOT NULL DEFAULT 'staff',
        position VARCHAR(100) NULL,
        department VARCHAR(100) NULL,
        contact_number VARCHAR(20) NULL,
        profile_image VARCHAR(255) NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        last_login DATETIME NULL,
        failed_login_attempts INT NOT NULL DEFAULT 0,
        lockout_until DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE INDEX username_UNIQUE (username),
        UNIQUE INDEX email_UNIQUE (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✓ admin_users table created<br>";
    
    // Create admin_permissions table
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_permissions (
        id INT NOT NULL AUTO_INCREMENT,
        admin_id INT NOT NULL,
        can_manage_users TINYINT(1) NOT NULL DEFAULT 0,
        can_manage_courses TINYINT(1) NOT NULL DEFAULT 0,
        can_manage_enrollees TINYINT(1) NOT NULL DEFAULT 0,
        can_manage_graduates TINYINT(1) NOT NULL DEFAULT 0,
        can_export_data TINYINT(1) NOT NULL DEFAULT 0,
        can_view_reports TINYINT(1) NOT NULL DEFAULT 0,
        can_approve_applications TINYINT(1) NOT NULL DEFAULT 0,
        can_modify_system_settings TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        INDEX fk_admin_permissions_admin_users_idx (admin_id),
        CONSTRAINT fk_admin_permissions_admin_users
            FOREIGN KEY (admin_id)
            REFERENCES admin_users (id)
            ON DELETE CASCADE
            ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✓ admin_permissions table created<br>";
    
    // Create remember_tokens table
    $pdo->exec("CREATE TABLE IF NOT EXISTS remember_tokens (
        id INT NOT NULL AUTO_INCREMENT,
        user_id INT NOT NULL,
        token VARCHAR(255) NOT NULL,
        expires DATETIME NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        INDEX fk_remember_tokens_users_idx (user_id),
        CONSTRAINT fk_remember_tokens_users
            FOREIGN KEY (user_id)
            REFERENCES admin_users (id)
            ON DELETE CASCADE
            ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✓ remember_tokens table created<br>";
    
    // Create login_logs table
    $pdo->exec("CREATE TABLE IF NOT EXISTS login_logs (
        id INT NOT NULL AUTO_INCREMENT,
        user_id INT NOT NULL,
        ip_address VARCHAR(45) NOT NULL,
        login_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        status ENUM('success', 'failed') NOT NULL DEFAULT 'success',
        PRIMARY KEY (id),
        INDEX fk_login_logs_users_idx (user_id),
        CONSTRAINT fk_login_logs_users
            FOREIGN KEY (user_id)
            REFERENCES admin_users (id)
            ON DELETE CASCADE
            ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✓ login_logs table created<br>";
    
    // Create user_logs table
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_logs (
        id INT NOT NULL AUTO_INCREMENT,
        user_id INT NOT NULL,
        action VARCHAR(50) NOT NULL,
        description TEXT NULL,
        ip_address VARCHAR(45) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        INDEX fk_user_logs_users_idx (user_id),
        CONSTRAINT fk_user_logs_users
            FOREIGN KEY (user_id)
            REFERENCES admin_users (id)
            ON DELETE CASCADE
            ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✓ user_logs table created<br>";
    
    // Create courses table
    $pdo->exec("CREATE TABLE IF NOT EXISTS courses (
        id INT NOT NULL AUTO_INCREMENT,
        course_code VARCHAR(20) NOT NULL,
        title VARCHAR(100) NOT NULL,
        description TEXT NULL,
        duration_hours INT NULL,
        training_cost DECIMAL(10,2) NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE INDEX course_code_UNIQUE (course_code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✓ courses table created<br>";
    
    // Create enrollees table
    $pdo->exec("CREATE TABLE IF NOT EXISTS enrollees (
        id INT NOT NULL AUTO_INCREMENT,
        first_name VARCHAR(50) NOT NULL,
        middle_name VARCHAR(50) NULL,
        last_name VARCHAR(50) NOT NULL,
        gender ENUM('Male', 'Female', 'Other') NOT NULL,
        birth_date DATE NOT NULL,
        email VARCHAR(100) NULL,
        phone VARCHAR(20) NULL,
        address TEXT NULL,
        course VARCHAR(100) NOT NULL,
        educational_attainment VARCHAR(100) NULL,
        status ENUM('pending', 'approved', 'rejected', 'graduated') NOT NULL DEFAULT 'pending',
        enrollment_date DATE NOT NULL,
        notes TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✓ enrollees table created<br>";
    
    // Create messages table with new features
    $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
        id INT NOT NULL AUTO_INCREMENT,
        sender_id INT NOT NULL,
        receiver_id INT NOT NULL,
        sender_type ENUM('admin', 'student') NOT NULL,
        receiver_type ENUM('admin', 'student') NOT NULL,
        subject VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        category ENUM('general', 'course', 'certificate', 'payment', 'schedule') NOT NULL DEFAULT 'general',
        priority ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'medium',
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        read_at DATETIME NULL,
        scheduled_time DATETIME NULL,
        attachment VARCHAR(255) NULL,
        attachment_name VARCHAR(255) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        INDEX idx_sender (sender_id, sender_type),
        INDEX idx_receiver (receiver_id, receiver_type),
        INDEX idx_category (category),
        INDEX idx_scheduled (scheduled_time)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✓ messages table created<br>";
    
    // Insert default super admin user
    $hashedPassword = password_hash('Admin@123', PASSWORD_BCRYPT, ['cost' => 12]);
    
    $stmt = $pdo->prepare("INSERT INTO admin_users 
        (username, password, full_name, email, admin_level, position, department) 
        VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->execute([
        'admin', 
        $hashedPassword, 
        'System Administrator', 
        'admin@tesda.edu.ph',
        'super_admin',
        'IT Director',
        'Information Technology'
    ]);
    
    $adminId = $pdo->lastInsertId();
    
    echo "✓ Default admin user created<br>";
    
    // Insert default permissions for super admin
    $stmt = $pdo->prepare("INSERT INTO admin_permissions 
        (admin_id, can_manage_users, can_manage_courses, can_manage_enrollees, 
        can_manage_graduates, can_export_data, can_view_reports, 
        can_approve_applications, can_modify_system_settings) 
        VALUES (?, 1, 1, 1, 1, 1, 1, 1, 1)");
    
    $stmt->execute([$adminId]);
    
    echo "✓ Default admin permissions set<br>";
    
    // Add sample courses
    $courses = [
        ['SMAW-NC-II', 'Shielded Metal Arc Welding NC II', 'Learn basic to advanced welding techniques using SMAW process.', 162, 5000.00],
        ['COMPOP-NC-II', 'Computer Operations NC II', 'Basic to advanced computer operations and office applications.', 120, 4000.00],
        ['BREAD-NC-II', 'Bread and Pastry Production NC II', 'Learn to create various bread and pastry products with industry standards.', 140, 4500.00],
        ['EIM-NC-II', 'Electrical Installation and Maintenance NC II', 'Learn electrical wiring installation and maintenance for residential and commercial buildings.', 180, 5500.00],
        ['DRESS-NC-II', 'Dressmaking NC II', 'Learn to create various clothing items and garments following industry standards.', 160, 4000.00]
    ];
    
    $stmt = $pdo->prepare("INSERT INTO courses (course_code, title, description, duration_hours, training_cost) 
                          VALUES (?, ?, ?, ?, ?)");
    
    foreach ($courses as $course) {
        $stmt->execute($course);
    }
    
    echo "✓ Sample courses added<br>";
    
    // Commit all changes
    $pdo->commit();
    
    echo "<div style='margin-top: 20px; padding: 15px; background-color: #d4edda; color: #155724; border-radius: 5px;'>
        <h3>Database setup completed successfully!</h3>
        <p>You can now <a href='login.php?clear_session=1' style='color: #155724; font-weight: bold;'>login</a> with:</p>
        <ul>
            <li>Username: <strong>admin</strong></li>
            <li>Password: <strong>Admin@123</strong></li>
        </ul>
        <p><strong>Important:</strong> Please change this password after your first login for security!</p>
    </div>";
    
} catch (PDOException $e) {
    // Roll back the transaction if something failed
    $pdo->rollback();
    echo "<div style='margin-top: 20px; padding: 15px; background-color: #f8d7da; color: #721c24; border-radius: 5px;'>
        <h3>Error during database setup:</h3>
        <p>" . htmlspecialchars($e->getMessage()) . "</p>
        <p>Line: " . $e->getLine() . " in " . basename($e->getFile()) . "</p>
    </div>";
}
?>
