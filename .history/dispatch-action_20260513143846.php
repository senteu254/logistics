<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$action      = $_POST['action']      ?? '';
$redirect_id = intval($_POST['redirect_id'] ?? 0);

if (!$redirect_id) {
    die("Error: No redirect ID provided. <a href='manage-bl.php'>Go Back</a>");
}

// ══════════════════════════════════════
// ACTION: DISPATCH
// ══════════════════════════════════════
if ($action === 'dispatch') {

    $bl_id                = intval($_POST['bl_id']              ?? 0);
    $bl_item_id           = intval($_POST['bl_item_id']         ?? 0);
    $transporter_name     = trim($_POST['transporter_name']     ?? '');
    $truck_number         = strtoupper(trim($_POST['truck_number'] ?? ''));
    $clearing_agent_dnote = trim($_POST['clearing_agent_dnote'] ?? '');
    $transporter_dnote    = trim($_POST['transporter_dnote']    ?? '');
    $destination          = trim($_POST['destination']          ?? '');
    $notes                = trim($_POST['notes']                ?? '');

    // Validate required fields
    if (!$bl_id || !$bl_item_id || !$transporter_name || !$truck_number || !$destination) {
        header("Location: view-bl.php?id=$redirect_id&error=missing_fields");
        exit();
    }

    // Check not already dispatched
    $chk = $pdo->prepare("SELECT id FROM dispatches WHERE bl_item_id = ?");
    $chk->execute([$bl_item_id]);
    if ($chk->fetch()) {
        header("Location: view-bl.php?id=$redirect_id&error=already_dispatched");
        exit();
    }

    try {
        // Insert dispatch record
        $ins = $pdo->prepare("
            INSERT INTO dispatches
                (bl_id, bl_item_id, transporter_name, truck_number,
                 clearing_agent_dnote, transporter_dnote, destination,
                 status, dispatched_by, notes, dispatched_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'transit', ?, ?, NOW())
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
            $notes                ?: null,
        ]);

        // Update bl_items dispatch_status
        $upd = $pdo->prepare("
            UPDATE bl_items
            SET dispatch_status = 'transit',
                dispatched_at   = NOW()
            WHERE id = ?
        ");
        $upd->execute([$bl_item_id]);

        header("Location: view-bl.php?id=$redirect_id&dispatched=1");
        exit();

    } catch (PDOException $e) {
        // Show actual error for debugging
        die("DB Error (dispatch): " . htmlspecialchars($e->getMessage()) .
            " <a href='view-bl.php?id=$redirect_id'>Go Back</a>");
    }
}

// ══════════════════════════════════════
// ACTION: UPDATE STATUS
// ══════════════════════════════════════
if ($action === 'update_status') {

    $dispatch_id      = intval($_POST['dispatch_id']    ?? 0);
    $bl_item_id       = intval($_POST['bl_item_id']     ?? 0);
    $new_status       = trim($_POST['new_status']        ?? '');
    $rejection_reason = trim($_POST['rejection_reason'] ?? '');
    $notes            = trim($_POST['notes']             ?? '');

    if (!in_array($new_status, ['received', 'rejected'])) {
        header("Location: view-bl.php?id=$redirect_id&error=invalid_status");
        exit();
    }

    if ($new_status === 'rejected' && empty($rejection_reason)) {
        header("Location: view-bl.php?id=$redirect_id&error=missing_reason");
        exit();
    }

    try {
        // Update dispatch record
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
            $new_status === 'rejected' ? $rejection_reason : null,
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

        $param = $new_status === 'received' ? 'received=1' : 'rejected=1';
        header("Location: view-bl.php?id=$redirect_id&$param");
        exit();

    } catch (PDOException $e) {
        die("DB Error (update_status): " . htmlspecialchars($e->getMessage()) .
            " <a href='view-bl.php?id=$redirect_id'>Go Back</a>");
    }
}

// No valid action
die("Error: No valid action. POST data: " . htmlspecialchars(print_r($_POST, true)));
?>