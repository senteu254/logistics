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
    $pdo->exec("DROP TABLE IF EXISTS empty_container_returns");
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
    // bl_type        : TBL = return via shipping line | Non-TBL = return via local depot
    // last_return_date: deadline for returning empty containers
    $pdo->exec("CREATE TABLE bills_of_lading (
        id               INT AUTO_INCREMENT PRIMARY KEY,
        bl_number        VARCHAR(50)  UNIQUE NOT NULL,
        bl_type          ENUM('TBL','Non-TBL') NOT NULL DEFAULT 'TBL',
        last_return_date DATE NOT NULL,
        item_description TEXT NOT NULL,
        user_id          INT DEFAULT NULL,
        status           ENUM('draft','active','completed') DEFAULT 'active',
        created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    // ── 5. bl_items ───────────────────────────────────────────────────────────
    // dispatch_status : tracks where the loaded container is
    // return_status   : tracks where the empty container is after delivery
    $pdo->exec("CREATE TABLE bl_items (
        id                INT AUTO_INCREMENT PRIMARY KEY,
        bl_id             INT NOT NULL,
        container_number  VARCHAR(100) NOT NULL,
        agent_seal_number VARCHAR(100) DEFAULT NULL,
        sgs_seal_number   VARCHAR(100) DEFAULT NULL,
        number_of_bags    INT NOT NULL,
        gross_weight      DECIMAL(10,3) NOT NULL,
        net_weight        DECIMAL(10,3) NOT NULL,
        dispatch_status   ENUM('pending','transit','received','rejected') DEFAULT 'pending',
        dispatched_at     TIMESTAMP NULL DEFAULT NULL,
        return_status     ENUM('not_applicable','pending_return','in_transit_return','returned') DEFAULT 'not_applicable',
        created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (bl_id) REFERENCES bills_of_lading(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    // ── 6. dispatches ─────────────────────────────────────────────────────────
    // bl_id kept here for quick lookup (denormalised by design)
    // redispatch_count: increments each time a rejected container is re-dispatched
    $pdo->exec("CREATE TABLE dispatches (
        id                    INT AUTO_INCREMENT PRIMARY KEY,
        bl_id                 INT NOT NULL,
        bl_item_id            INT NOT NULL,
        transporter_name      VARCHAR(150) NOT NULL,
        truck_number          VARCHAR(50)  NOT NULL,
        destination           VARCHAR(200) NOT NULL,
        clearing_agent_dnote  VARCHAR(100) DEFAULT NULL,
        transporter_dnote     VARCHAR(100) DEFAULT NULL,
        status                ENUM('transit','received','rejected') DEFAULT 'transit',
        dispatched_by         INT DEFAULT NULL,
        dispatched_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        received_at           TIMESTAMP NULL DEFAULT NULL,
        received_by           INT DEFAULT NULL,
        rejection_reason      TEXT DEFAULT NULL,
        notes                 TEXT DEFAULT NULL,
        redispatch_count      INT DEFAULT 0,
        FOREIGN KEY (bl_id)        REFERENCES bills_of_lading(id) ON DELETE CASCADE,
        FOREIGN KEY (bl_item_id)   REFERENCES bl_items(id)        ON DELETE CASCADE,
        FOREIGN KEY (dispatched_by)REFERENCES users(id)           ON DELETE SET NULL,
        FOREIGN KEY (received_by)  REFERENCES users(id)           ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    // ── 7. empty_container_returns ────────────────────────────────────────────
    // Tracks the return of the empty container after successful delivery.
    //
    // TBL flow (1 step):
    //   User enters tbl_depot + tbl_date_in → return_status = 'returned' immediately
    //
    // Non-TBL flow (2 steps):
    //   Step 1: User enters nontbl_transporter + nontbl_truck_number + nontbl_final_depot
    //           → return_status = 'in_transit_return'
    //   Step 2: Container arrives at depot, user enters nontbl_date_in
    //           → return_status = 'returned'
    $pdo->exec("CREATE TABLE empty_container_returns (
        id                  INT AUTO_INCREMENT PRIMARY KEY,
        bl_id               INT NOT NULL,
        bl_item_id          INT NOT NULL,
        dispatch_id         INT NOT NULL,
        bl_type             ENUM('TBL','Non-TBL') NOT NULL,

        -- TBL fields
        tbl_depot           VARCHAR(200) DEFAULT NULL,
        tbl_date_in         DATE         DEFAULT NULL,

        -- Non-TBL fields
        nontbl_transporter  VARCHAR(150) DEFAULT NULL,
        nontbl_truck_number VARCHAR(50)  DEFAULT NULL,
        nontbl_final_depot  VARCHAR(200) DEFAULT NULL,
        nontbl_date_in      DATE         DEFAULT NULL,

        -- Status
        return_status       ENUM('pending_return','in_transit_return','returned') DEFAULT 'pending_return',

        created_by          INT DEFAULT NULL,
        completed_by        INT DEFAULT NULL,
        created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        completed_at        TIMESTAMP NULL DEFAULT NULL,

        FOREIGN KEY (bl_id)        REFERENCES bills_of_lading(id) ON DELETE CASCADE,
        FOREIGN KEY (bl_item_id)   REFERENCES bl_items(id)        ON DELETE CASCADE,
        FOREIGN KEY (dispatch_id)  REFERENCES dispatches(id)      ON DELETE CASCADE,
        FOREIGN KEY (created_by)   REFERENCES users(id)           ON DELETE SET NULL,
        FOREIGN KEY (completed_by) REFERENCES users(id)           ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    // ── Seed admin user ───────────────────────────────────────────────────────
    $hashedPassword = password_hash('admin123', PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name, role) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute(['admin', 'admin@example.com', $hashedPassword, 'System Administrator', 'admin']);

    $verify = password_verify('admin123', $hashedPassword);

    // ── Table summary ─────────────────────────────────────────────────────────
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    // ── Column summary for key tables ─────────────────────────────────────────
    $colSummary = [];
    foreach (['bills_of_lading','bl_items','dispatches','empty_container_returns'] as $tbl) {
        $cols = $pdo->query("SHOW COLUMNS FROM $tbl")->fetchAll(PDO::FETCH_ASSOC);
        $colSummary[$tbl] = $cols;
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Setup Complete</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', sans-serif; background: #f8fafc; padding: 30px 20px; }
        .card { background: white; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.08); max-width: 820px; width: 100%; overflow: hidden; margin: 0 auto; }
        .card-head { background: linear-gradient(135deg, #6366f1, #8b5cf6); padding: 28px 32px; color: white; }
        .card-head h2 { font-size: 1.4rem; margin-bottom: 4px; }
        .card-head p { opacity: 0.85; font-size: 0.9rem; }
        .card-body { padding: 28px 32px; }
        .section-title { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #94a3b8; margin: 24px 0 10px; }
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
        .schema-table { width: 100%; border-collapse: collapse; font-size: 0.8rem; margin-bottom: 20px; }
        .schema-table th { background: #f8fafc; color: #64748b; font-weight: 700; text-align: left; padding: 8px 12px; border-bottom: 2px solid #e2e8f0; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em; }
        .schema-table td { padding: 7px 12px; border-bottom: 1px solid #f1f5f9; color: #334155; font-family: 'Courier New', monospace; }
        .schema-table tr:last-child td { border-bottom: none; }
        .schema-table tr:hover td { background: #fafbff; }
        .col-type { color: #6366f1; }
        .col-null { color: #94a3b8; font-size: 0.72rem; }
        .col-default { color: #059669; font-size: 0.75rem; }
        .col-new { background: rgba(99,102,241,0.06); }
        .tbl-section { margin-bottom: 28px; }
        .tbl-name { font-size: 0.88rem; font-weight: 700; color: #1e293b; margin-bottom: 8px; display: flex; align-items: center; gap: 8px; }
        .tbl-name span { background: linear-gradient(135deg,#6366f1,#8b5cf6); color: white; padding: 2px 10px; border-radius: 20px; font-size: 0.72rem; font-weight: 600; }
    </style>
</head>
<body>
<div class="card">
    <div class="card-head">
        <h2>✅ Setup Complete</h2>
        <p>Database <strong>bill_of_lading</strong> initialized with <?php echo count($tables); ?> tables — all columns ready.</p>
    </div>
    <div class="card-body">

        <div class="section-title">Admin Credentials</div>
        <div class="info-row"><strong>Username</strong><span>admin</span></div>
        <div class="info-row"><strong>Password</strong><span>admin123</span></div>
        <div class="info-row"><strong>Email</strong>   <span>admin@example.com</span></div>
        <div class="info-row"><strong>Role</strong>    <span>admin</span></div>
        <div class="info-row">
            <strong>Password Hash Test</strong>
            <?php if ($verify): ?>
                <span class="badge-ok">✓ PASSED</span>
            <?php else: ?>
                <span class="badge-err">✗ FAILED</span>
            <?php endif; ?>
        </div>
        <div class="hash">Hash: <?php echo $hashedPassword; ?></div>

        <div class="section-title" style="margin-top:28px;">Tables Created</div>
        <div class="table-grid">
            <?php foreach ($tables as $t): ?>
                <span class="table-pill"><?php echo htmlspecialchars($t); ?></span>
            <?php endforeach; ?>
        </div>

        <div class="section-title" style="margin-top:32px;">Schema Detail — Key Tables</div>

        <?php
        // Highlight new columns that were missing in old schema
        $newCols = [
            'bills_of_lading'         => ['bl_type','last_return_date'],
            'bl_items'                => ['dispatch_status','dispatched_at','return_status'],
            'dispatches'              => ['bl_id','redispatch_count'],
            'empty_container_returns' => ['tbl_depot','tbl_date_in','nontbl_transporter','nontbl_truck_number','nontbl_final_depot','nontbl_date_in','return_status','completed_by','completed_at'],
        ];

        foreach ($colSummary as $tblName => $cols):
            $count = count($cols);
        ?>
        <div class="tbl-section">
            <div class="tbl-name">
                <?php echo htmlspecialchars($tblName); ?>
                <span><?php echo $count; ?> columns</span>
            </div>
            <table class="schema-table">
                <thead>
                    <tr>
                        <th>Column</th>
                        <th>Type</th>
                        <th>Null</th>
                        <th>Default</th>
                        <th>Extra</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cols as $col):
                        $isNew = in_array($col['Field'], $newCols[$tblName] ?? []);
                    ?>
                    <tr class="<?php echo $isNew ? 'col-new' : ''; ?>">
                        <td>
                            <?php if ($isNew): ?>
                                <span style="color:#6366f1;font-weight:700;">★ <?php echo htmlspecialchars($col['Field']); ?></span>
                            <?php else: ?>
                                <?php echo htmlspecialchars($col['Field']); ?>
                            <?php endif; ?>
                        </td>
                        <td class="col-type"><?php echo htmlspecialchars($col['Type']); ?></td>
                        <td class="col-null"><?php echo $col['Null']; ?></td>
                        <td class="col-default"><?php echo $col['Default'] ?? '—'; ?></td>
                        <td style="color:#94a3b8;font-size:0.72rem;"><?php echo $col['Extra'] ?: '—'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endforeach; ?>

        <div class="section-title">Workflow Summary</div>
        <div style="font-size:0.82rem;color:#475569;line-height:1.8;background:#f8fafc;border-radius:10px;padding:16px 20px;border:1px solid #e2e8f0;">
            <strong style="display:block;margin-bottom:8px;color:#1e293b;">Container Lifecycle</strong>
            <div>1. <strong>Add B/L</strong> → select TBL or Non-TBL + set last return date</div>
            <div>2. <strong>Dispatch</strong> → container goes <em>In Transit</em></div>
            <div>3. <strong>Update Status</strong> → mark as <em>Received</em> or <em>Rejected</em></div>
            <div style="margin-left:16px;color:#dc2626;">↳ If <strong>Rejected</strong> → Re-Dispatch to new destination → back to In Transit</div>
            <div>4. <strong>Return Empty</strong> (after Received):</div>
            <div style="margin-left:16px;color:#6366f1;">↳ <strong>TBL</strong>: Enter shipping line depot + Date In → <em>Returned ✓</em></div>
            <div style="margin-left:16px;color:#059669;">↳ <strong>Non-TBL</strong>: Enter transporter + local depot → <em>In Transit Return</em> → Arrive at depot → Enter Date In → <em>Returned ✓</em></div>
        </div>

        <div class="warning">
            <strong>⚠️ Security Notice</strong>
            Delete or block <code>setup.php</code> after setup — visiting it again drops and recreates ALL tables and ALL data.
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
    <meta charset="UTF-8">
    <title>Setup Failed</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; background: #fef2f2; padding: 20px; }
        .card { background: white; border-radius: 16px; padding: 40px; max-width: 640px; width: 100%; box-shadow: 0 4px 24px rgba(0,0,0,0.08); }
        h2 { color: #dc2626; margin-bottom: 16px; }
        pre { background: #fef2f2; border: 1px solid #fca5a5; border-radius: 8px; padding: 16px; font-size: 0.85rem; color: #7f1d1d; white-space: pre-wrap; word-break: break-word; }
        .back { display:inline-block;margin-top:20px;color:#6366f1;font-weight:600;text-decoration:none; }
    </style>
</head>
<body>
    <div class="card">
        <h2>❌ Setup Failed</h2>
        <pre><?php echo htmlspecialchars($e->getMessage()); ?></pre>
        <a href="setup.php" class="back">↩ Try Again</a>
    </div>
</body>
</html>
<?php
}
?>