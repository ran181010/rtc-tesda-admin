<?php
require 'db.php';

try {
    // Create admin_users table
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(100) NULL,
        email VARCHAR(100) NULL UNIQUE,
        admin_level ENUM('super_admin', 'admin', 'manager', 'staff') DEFAULT 'staff',
        is_active TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Create default admin user
    $hashedPassword = password_hash('Admin@123', PASSWORD_BCRYPT, ['cost' => 12]);
    $pdo->exec("INSERT IGNORE INTO admin_users (username, password, full_name, email, admin_level) 
                VALUES ('admin', '$hashedPassword', 'System Administrator', 'admin@example.com', 'super_admin')");
    
    echo "Database tables created successfully! You can now <a href='login.php'>login</a> with:<br>";
    echo "Username: admin<br>Password: Admin@123";
} catch (PDOException $e) {
    echo "Error setting up database: " . $e->getMessage();
}