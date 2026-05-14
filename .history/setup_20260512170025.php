<?php
$host = 'localhost';
$dbname = 'bill_of_lading';
$username = 'root';
$password = '';

try {
    // Connect without database first
    $pdo = new PDO("mysql:host=$host;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS bill_of_lading CHARACTER SET utf8 COLLATE utf8_general_ci");
    $pdo->exec("USE bill_of_lading");

    // Create users table
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Create bills_of_lading table
    $pdo->exec("CREATE TABLE IF NOT EXISTS bills_of_lading (
        id INT AUTO_INCREMENT PRIMARY KEY,
        bl_number VARCHAR(50) UNIQUE NOT NULL,
        item_description TEXT NOT NULL,
        user_id INT,
        status ENUM('draft','active','completed') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");

    // Create bl_items table
    $pdo->exec("CREATE TABLE IF NOT EXISTS bl_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        bl_id INT,
        container_number VARCHAR(100) NOT NULL,
        agent_seal_number VARCHAR(100),
        number_of_bags INT NOT NULL,
        sgs_seal_number VARCHAR(100),
        gross_weight DECIMAL(10,2) NOT NULL,
        net_weight DECIMAL(10,2) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (bl_id) REFERENCES bills_of_lading(id) ON DELETE CASCADE
    )");

    // Generate proper password hash
    $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);

    // Delete existing admin and re-insert with fresh hash
    $pdo->exec("DELETE FROM users WHERE username = 'admin'");
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name) VALUES (?, ?, ?, ?)");
    $stmt->execute(['admin', 'admin@example.com', $hashedPassword, 'System Administrator']);

    echo "<h2 style='font-family:sans-serif; color:green;'>✅ Setup Complete!</h2>";
    echo "<p style='font-family:sans-serif;'>Database and tables created successfully.</p>";
    echo "<p style='font-family:sans-serif;'><strong>Login Credentials:</strong></p>";
    echo "<p style='font-family:sans-serif;'>Username: <strong>admin</strong></p>";
    echo "<p style='font-family:sans-serif;'>Password: <strong>admin123</strong></p>";
    echo "<p style='font-family:sans-serif;'>Hash used: <code>$hashedPassword</code></p>";
    echo "<br><a href='login.php' style='font-family:sans-serif;'>→ Go to Login Page</a>";

} catch (PDOException $e) {
    echo "<h2 style='font-family:sans-serif; color:red;'>❌ Setup Failed</h2>";
    echo "<p style='font-family:sans-serif;'>Error: " . $e->getMessage() . "</p>";
}
?>