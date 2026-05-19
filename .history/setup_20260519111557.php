<?php
$host    = 'localhost';
$dbname  = 'bill_of_lading';
$db_user = 'root';
$db_pass = 'schlumberger';

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec("CREATE DATABASE IF NOT EXISTS bill_of_lading CHARACTER SET utf8 COLLATE utf8_general_ci");
    $pdo->exec("USE bill_of_lading");

    // Drop in reverse FK order
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $pdo->exec("DROP TABLE IF EXISTS empty_returns");
    $pdo->exec("DROP TABLE IF EXISTS dispatches");
    $pdo->exec("DROP TABLE IF EXISTS bl_items");
    $pdo->exec("DROP TABLE IF EXISTS bills_of_lading");
    $pdo->exec("DROP TABLE IF EXISTS users");
    $pdo->exec("DROP TABLE IF EXISTS locations");
    $pdo->exec("DROP TABLE IF EXISTS transporters");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    // ── 1. users ──────────────────────────────────────────────────────────────
    $pdo->exec("CREATE TABLE users (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        username   VARCHAR(50)  UNIQUE NOT NULL,
        email      VARCHAR(100) UNIQUE NOT NULL,
        password   VARCHAR(255) NOT NULL,
        full_name  VARCHAR(100) DEFAULT NULL,
        role       ENUM('admin','user') NOT NULL DEFAULT 'user',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    // ── 2. locations ──────────────────────────────────────────────────────────
    $pdo->exec("CREATE TABLE locations (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        name       VARCHAR(200) UNIQUE NOT NULL,
        type       ENUM('destination','depot','mixed') DEFAULT 'mixed',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    // ── 3. transporters ───────────────────────────────────────────────────────
    $pdo->exec("CREATE TABLE transporters (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        name       VARCHAR(150) UNIQUE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    // ── 4. bills_of_lading ────────────────────────────────────────────────────
    $pdo->exec("CREATE TABLE bills_of_lading (
        id               INT AUTO_INCREMENT PRIMARY KEY,
        bl_number        VARCHAR(50) UNIQUE NOT NULL,
        item_description TEXT NOT NULL,
        user_id          INT DEFAULT NULL,
        status           ENUM('draft','active','completed') DEFAULT 'active',
        created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    // ── 5. bl_items ───────────────────────────────────────────────────────────
    // bl_id lives HERE — dispatches joins through bl_items to get to bills_of_lading
    $pdo->exec("CREATE TABLE bl_items (
        id                INT AUTO_INCREMENT PRIMARY KEY,
        bl_id             INT NOT NULL,
        container_number  VARCHAR(100) NOT NULL,
        agent_seal_number VARCHAR(100) DEFAULT NULL,
        sgs_seal_number   VARCHAR(100) DEFAULT NULL,
        number_of_bags    INT NOT NULL,
        gross_weight      DECIMAL(10,3) NOT NULL,
        net_weight        DECIMAL(10,3) NOT NULL,
        created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (bl_id) REFERENCES bills_of_lading(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    // ── 6. dispatches ─────────────────────────────────────────────────────────
    // NO bl_id column — always derive via: dispatches → bl_items.bl_id → bills_of_lading
    $pdo->exec("CREATE TABLE dispatches (
        id                        INT AUTO_INCREMENT PRIMARY KEY,
        bl_item_id                INT NOT NULL,
        transporter_name          VARCHAR(150) NOT NULL,
        truck_number              VARCHAR(50)  NOT NULL,
        destination               VARCHAR(200) NOT NULL,
        clearing_agent_dnote      VARCHAR(100) DEFAULT NULL,
        transporter_dnote         VARCHAR(100) DEFAULT NULL,
        status                    ENUM('transit','received','rejected') DEFAULT 'transit',
        dispatched_by             INT DEFAULT NULL,
        dispatched_at             TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        received_at               TIMESTAMP NULL DEFAULT NULL,
        received_by               INT DEFAULT NULL,
        rejection_reason          TEXT,
        notes                     TEXT,
        redirected_destination_id INT DEFAULT NULL,
        redirected_at             TIMESTAMP NULL DEFAULT NULL,
        redirect_count            INT DEFAULT 0,
        FOREIGN KEY (bl_item_id)                REFERENCES bl_items(id)   ON DELETE CASCADE,
        FOREIGN KEY (dispatched_by)             REFERENCES users(id)      ON DELETE SET NULL,
        FOREIGN KEY (received_by)               REFERENCES users(id)      ON DELETE SET NULL,
        FOREIGN KEY (redirected_destination_id) REFERENCES locations(id)  ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    // ── 7. empty_returns ──────────────────────────────────────────────────────
    // bl_id stored here for quick lookup (denormalized by design) with proper FK
    $pdo->exec("CREATE TABLE empty_returns (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        dispatch_id   INT NOT NULL,
        bl_id         INT NOT NULL,
        bl_item_id    INT NOT NULL,
        bl_type       ENUM('TBL','Non-TBL') NOT NULL,
        return_status ENUM('in_transit','completed') DEFAULT 'in_transit',
        returned_date DATE DEFAULT NULL,
        depot         VARCHAR(200) DEFAULT NULL,
        local_depot   VARCHAR(200) DEFAULT NULL,
        transporter   VARCHAR(150) DEFAULT NULL,
        date_in       DATE DEFAULT NULL,
        notes         TEXT,
        created_by    INT DEFAULT NULL,
        created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        completed_at  TIMESTAMP NULL DEFAULT NULL,
        completed_by  INT DEFAULT NULL,
        FOREIGN KEY (dispatch_id) REFERENCES dispatches(id)        ON DELETE CASCADE,
        FOREIGN KEY (bl_id)       REFERENCES bills_of_lading(id)   ON DELETE CASCADE,
        FOREIGN KEY (bl_item_id)  REFERENCES bl_items(id)          ON DELETE CASCADE,
        FOREIGN KEY (created_by)  REFERENCES users(id)             ON DELETE SET NULL,
        FOREIGN KEY (completed_by)REFERENCES users(id)             ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    // ── Seed admin user ───────────────────────────────────────────────────────
    $hashedPassword = password_hash('admin123', PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name, role) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute(['admin', 'admin@example.com', $hashedPassword, 'System Administrator', 'admin']);

    $verify = password_verify('admin123', $hashedPassword);

    // ── Table summary ─────────────────────────────────────────────────────────
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Setup Complete</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', sans-serif; background: #f8fafc; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px; }
        .card { background: white; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.08); max-width: 640px; width: 100%; overflow: hidden; }
        .card-head { background: linear-gradient(135deg, #6366f1, #8b5cf6); padding: 28px 32px; color: white; }
        .card-head h2 { font-size: 1.4rem; margin-bottom: 4px; }
        .card-head p { opacity: 0.85; font-size: 0.9rem; }
        .card-body { padding: 28px 32px; }
        .section-title { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #94a3b8; margin: 20px 0 10px; }
        .section-title:first-child { margin-top: 0; }
        .info-row { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #f1f5f9; font-size: 0.9rem; }
        .info-row:last-child { border-bottom: none; }
        .info-row strong { color: #334155; }
        .info-row span { color: #6366f1; font-weight: 600; font-family: 'Courier New', monospace; font-size: 0.85rem; }
        .badge-ok  { background: #dcfce7; color: #16a34a; padding: 3px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; }
        .badge-err { background: #fee2e2; color: #dc2626; padding: 3px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; }
        .table-grid { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 4px; }
        .table-pill { background: #f1f5f9; color: #475569; padding: 5px 12px; border-radius: 20px; font-size: 0.78rem; font-weight: 600; font-family: 'Courier New', monospace; }
        .hash { font-size: 0.68rem; color: #94a3b8; word-break: break-all; margin-top: 14px; padding: 10px; background: #f8fafc; border-radius: 8px; }
        .btn { display: inline-block; margin-top: 24px; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; padding: 13px 30px; border-radius: 10px; text-decoration: none; font-weight: 600; font-size: 0.95rem; }
        .btn:hover { opacity: 0.9; }
        .warning { background: #fffbeb; border: 1px solid #fcd34d; border-radius: 10px; padding: 12px 16px; margin-top: 20px; font-size: 0.82rem; color: #92400e; }
        .warning strong { display: block; margin-bottom: 4px; }
    </style>
</head>
<body>
<div class="card">
    <div class="card-head">
        <h2>✅ Setup Complete</h2>
        <p>Database <strong>bill_of_lading</strong> initialized with <?php echo count($tables); ?> tables.</p>
    </div>
    <div class="card-body">

        <div class="section-title">Admin Credentials</div>
        <div class="info-row"><strong>Username</strong> <span>admin</span></div>
        <div class="info-row"><strong>Password</strong> <span>admin123</span></div>
        <div class="info-row"><strong>Email</strong>    <span>admin@example.com</span></div>
        <div class="info-row"><strong>Role</strong>     <span>admin</span></div>
        <div class="info-row">
            <strong>Password Hash Test</strong>
            <?php if ($verify): ?>
                <span class="badge-ok">✓ PASSED</span>
            <?php else: ?>
                <span class="badge-err">✗ FAILED</span>
            <?php endif; ?>
        </div>
        <div class="hash">Hash: <?php echo $hashedPassword; ?></div>

        <div class="section-title" style="margin-top:24px;">Tables Created</div>
        <div class="table-grid">
            <?php foreach ($tables as $t): ?>
                <span class="table-pill"><?php echo htmlspecialchars($t); ?></span>
            <?php endforeach; ?>
        </div>

        <div class="warning">
            <strong>⚠️ Security Notice</strong>
            Delete or block <code>setup.php</code> immediately after setup — it drops and recreates all tables if visited again.
        </div>

        <a href="login.php" class="btn">→ Go to Login</a>
    </div>
</div>
</body>
</html>
<?php

} catch (PDOException $e) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><title>Setup Failed</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; background: #fef2f2; }
        .card { background: white; border-radius: 16px; padding: 40px; max-width: 560px; box-shadow: 0 4px 24px rgba(0,0,0,0.08); }
        h2 { color: #dc2626; margin-bottom: 16px; }
        pre { background: #fef2f2; border: 1px solid #fca5a5; border-radius: 8px; padding: 16px; font-size: 0.85rem; color: #7f1d1d; white-space: pre-wrap; word-break: break-word; }
    </style>
</head>
<body>
    <div class="card">
        <h2>❌ Setup Failed</h2>
        <pre><?php echo htmlspecialchars($e->getMessage()); ?></pre>
    </div>
</body>
</html>
<?php
}
?>