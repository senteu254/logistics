<?php
$host    = 'localhost';
$dbname  = 'bill_of_lading';
$db_user = 'root';
$db_pass = 'schlumberger';

try {
    // Connect without selecting DB first
    $pdo = new PDO("mysql:host=$host;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create DB if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS bill_of_lading CHARACTER SET utf8 COLLATE utf8_general_ci");
    $pdo->exec("USE bill_of_lading");

    // Drop and recreate tables cleanly
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $pdo->exec("DROP TABLE IF EXISTS bl_items");
    $pdo->exec("DROP TABLE IF EXISTS bills_of_lading");
    $pdo->exec("DROP TABLE IF EXISTS users");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    // Create users table (with full_name)
    $pdo->exec("CREATE TABLE users (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        username   VARCHAR(50)  UNIQUE NOT NULL,
        email      VARCHAR(100) UNIQUE NOT NULL,
        password   VARCHAR(255) NOT NULL,
        full_name  VARCHAR(100) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Create bills_of_lading table
    $pdo->exec("CREATE TABLE bills_of_lading (
        id               INT AUTO_INCREMENT PRIMARY KEY,
        bl_number        VARCHAR(50) UNIQUE NOT NULL,
        item_description TEXT NOT NULL,
        user_id          INT,
        status           ENUM('draft','active','completed') DEFAULT 'active',
        created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");

    // Create bl_items table
    $pdo->exec("CREATE TABLE bl_items (
        id                INT AUTO_INCREMENT PRIMARY KEY,
        bl_id             INT NOT NULL,
        container_number  VARCHAR(100) NOT NULL,
        agent_seal_number VARCHAR(100) DEFAULT NULL,
        number_of_bags    INT NOT NULL,
        sgs_seal_number   VARCHAR(100) DEFAULT NULL,
        gross_weight      DECIMAL(10,2) NOT NULL,
        net_weight        DECIMAL(10,2) NOT NULL,
        created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (bl_id) REFERENCES bills_of_lading(id) ON DELETE CASCADE
    )");

    // Generate fresh password hash
    $hashedPassword = password_hash('admin123', PASSWORD_BCRYPT);

    // Insert admin user
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name) VALUES (?, ?, ?, ?)");
    $stmt->execute(['admin', 'admin@example.com', $hashedPassword, 'System Administrator']);

    // Verify password works
    $verify = password_verify('admin123', $hashedPassword);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Setup</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; max-width: 600px; margin: 60px auto; padding: 20px; }
        .success { background: #f0fdf4; border: 1px solid #86efac; border-radius: 12px; padding: 30px; }
        .success h2 { color: #16a34a; margin-bottom: 15px; }
        .info-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #e2e8f0; font-size: 0.95rem; }
        .info-row strong { color: #1e293b; }
        .info-row span { color: #6366f1; font-weight: 600; font-family: monospace; }
        .badge { display: inline-block; background: #dcfce7; color: #16a34a; padding: 3px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }
        .btn { display: inline-block; margin-top: 25px; background: #6366f1; color: white; padding: 12px 28px; border-radius: 10px; text-decoration: none; font-weight: 600; }
        .btn:hover { background: #4f46e5; }
        .hash { font-size: 0.7rem; color: #94a3b8; word-break: break-all; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="success">
        <h2>✅ Setup Complete!</h2>
        <p style="color:#64748b; margin-bottom:20px;">All tables created and admin user inserted successfully.</p>

        <div class="info-row">
            <strong>Username</strong>
            <span>admin</span>
        </div>
        <div class="info-row">
            <strong>Password</strong>
            <span>admin123</span>
        </div>
        <div class="info-row">
            <strong>Email</strong>
            <span>admin@example.com</span>
        </div>
        <div class="info-row">
            <strong>Password Verify Test</strong>
            <?php if ($verify): ?>
                <span class="badge">✓ PASSED</span>
            <?php else: ?>
                <span style="color:red;">✗ FAILED</span>
            <?php endif; ?>
        </div>

        <div class="hash">Hash: <?php echo $hashedPassword; ?></div>

        <a href="login.php" class="btn">→ Go to Login Page</a>
    </div>
</body>
</html>

<?php

} catch (PDOException $e) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Setup Failed</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; max-width: 600px; margin: 60px auto; padding: 20px; }
        .error { background: #fef2f2; border: 1px solid #fca5a5; border-radius: 12px; padding: 30px; }
        .error h2 { color: #dc2626; }
        .error p { color: #7f1d1d; margin-top: 10px; font-family: monospace; font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="error">
        <h2>❌ Setup Failed</h2>
        <p><?php echo htmlspecialchars($e->getMessage()); ?></p>
    </div>
</body>
</html>
<?php
}
?>