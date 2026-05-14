<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Must be POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: manage-bl.php');
    exit();
}

$action      = trim($_POST['action']      ?? '');
$redirect_id = intval($_POST['redirect_id'] ?? 0);

// Debug — uncomment if still having issues
// echo "<pre>POST: "; print_r($_POST); echo "</pre>"; exit();

if (!$redirect_id) {
    die("
        <h2 style='font-family:sans-serif;color:red;'>Error: Missing redirect_id</h2>
        <pre style='font-family:monospace;'>" . print_r($_POST, true) . "</pre>
        <a href='manage-bl.php' style='font-family:sans-serif;'>← Go Back</a>
    ");
}

// ══════════════════════════════════════════════
// DISPATCH A CONTAINER
// ══════════════════════════════════════════════
if ($action === 'dispatch') {

    $bl_id                = intval(trim($_POST['bl_id']               ?? 0));
    $bl_item_id           = intval(trim($_POST['bl_item_id']          ?? 0));
    $transporter_name     = trim($_POST['transporter_name']           ?? '');
    $truck_number         = strtoupper(trim($_POST['truck_number']    ?? ''));
    $clearing_agent_dnote = trim($_POST['clearing_agent_dnote']       ?? '');
    $transporter_dnote    = trim($_POST['transporter_dnote']          ?? '');
    $destination          = trim($_POST['destination']                ?? '');
    $notes                = trim($_POST['notes']                      ?? '');

    // ── Validate ──
    $errors = [];
    if (!$bl_id)             $errors[] = 'Missing bl_id';
    if (!$bl_item_id)        $errors[] = 'Missing bl_item_id';
    if (!$transporter_name)  $errors[] = 'Transporter name required';
    if (!$truck_number)      $errors[] = 'Truck number required';
    if (!$destination)       $errors[] = 'Destination required';

    if (!empty($errors)) {
        header("Location: view-bl.php?id=$redirect_id&error=missing_fields");
        exit();
    }

    // ── Check not already dispatched ──
    $chk = $pdo->prepare("SELECT id FROM dispatches WHERE bl_item_id = ? LIMIT 1");
    $chk->execute([$bl_item_id]);
    if ($chk->fetch()) {
        header("Location: view-bl.php?id=$redirect_id&error=already_dispatched");
        exit();
    }

    try {
        $pdo->beginTransaction();

        // Insert into dispatches
        $ins = $pdo->prepare("
            INSERT INTO dispatches
                (bl_id, bl_item_id, transporter_name, truck_number,
                 clearing_agent_dnote, transporter_dnote, destination,
                 status, dispatched_by, notes, dispatched_at)
            VALUES
                (?, ?, ?, ?,
                 ?, ?, ?,
                 'transit', ?, ?, NOW())
        ");

        $ins->execute([
            $bl_id,
            $bl_item_id,
            $transporter_name,
            $truck_number,
            $clearing_agent_dnote ?: null,
            $transporter_dnote    ?: null,
            $destination,
            $_SESSION['user_id'],
            $notes ?: null,
        ]);

        // Update bl_items status
        $upd = $pdo->prepare("
            UPDATE bl_items
            SET dispatch_status = 'transit',
                dispatched_at   = NOW()
            WHERE id = ?
        ");
        $upd->execute([$bl_item_id]);

        $pdo->commit();

        header("Location: view-bl.php?id=$redirect_id&dispatched=1");
        exit();

    } catch (PDOException $e) {
        $pdo->rollBack();
        die("
            <h2 style='font-family:sans-serif;color:red;'>Database Error (dispatch)</h2>
            <p style='font-family:monospace;'>" . htmlspecialchars($e->getMessage()) . "</p>
            <a href='view-bl.php?id=$redirect_id' style='font-family:sans-serif;'>← Go Back</a>
        ");
    }
}

// ══════════════════════════════════════════════
// UPDATE STATUS — Received or Rejected
// ══════════════════════════════════════════════
if ($action === 'update_status') {

    $dispatch_id      = intval(trim($_POST['dispatch_id']      ?? 0));
    $bl_item_id       = intval(trim($_POST['bl_item_id']       ?? 0));
    $new_status       = trim($_POST['new_status']               ?? '');
    $rejection_reason = trim($_POST['rejection_reason']         ?? '');
    $notes            = trim($_POST['notes']                    ?? '');

    // Validate
    if (!$dispatch_id || !$bl_item_id) {
        header("Location: view-bl.php?id=$redirect_id&error=missing_ids");
        exit();
    }

    if (!in_array($new_status, ['received', 'rejected'])) {
        header("Location: view-bl.php?id=$redirect_id&error=invalid_status");
        exit();
    }

    if ($new_status === 'rejected' && empty($rejection_reason)) {
        header("Location: view-bl.php?id=$redirect_id&error=missing_reason");
        exit();
    }

    try {
        $pdo->beginTransaction();

        // Update dispatches table
        $upd = $pdo->prepare("
            UPDATE dispatches
            SET status           = ?,
                received_at      = NOW(),
                received_by      = ?,
                rejection_reason = ?,
                notes            = ?
            WHERE id = ?
        ");
        $upd->execute([
            $new_status,
            $_SESSION['user_id'],
            ($new_status === 'rejected' && $rejection_reason) ? $rejection_reason : null,
            $notes ?: null,
            $dispatch_id,
        ]);

        // Update bl_items dispatch_status
        $upd2 = $pdo->prepare("
            UPDATE bl_items
            SET dispatch_status = ?
            WHERE id = ?
        ");
        $upd2->execute([$new_status, $bl_item_id]);

        $pdo->commit();

        $param = ($new_status === 'received') ? 'received=1' : 'rejected=1';
        header("Location: view-bl.php?id=$redirect_id&$param");
        exit();

    } catch (PDOException $e) {
        $pdo->rollBack();
        die("
            <h2 style='font-family:sans-serif;color:red;'>Database Error (update_status)</h2>
            <p style='font-family:monospace;'>" . htmlspecialchars($e->getMessage()) . "</p>
            <a href='view-bl.php?id=$redirect_id' style='font-family:sans-serif;'>← Go Back</a>
        ");
    }
}

// ── Unknown action ──
die("
    <h2 style='font-family:sans-serif;color:orange;'>Unknown action: " . htmlspecialchars($action) . "</h2>
    <pre style='font-family:monospace;'>" . print_r($_POST, true) . "</pre>
    <a href='manage-bl.php' style='font-family:sans-serif;'>← Go Back</a>
");
?>