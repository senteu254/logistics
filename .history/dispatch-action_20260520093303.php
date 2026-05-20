<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: manage-bl.php');
    exit();
}

$action      = trim($_POST['action'] ?? '');
$redirect_id = intval($_POST['redirect_id'] ?? 0);

if (!$redirect_id) {
    die("<h2 style='font-family:sans-serif;color:red;'>Error: Missing redirect_id</h2>
         <a href='manage-bl.php'>Go Back</a>");
}

// ══════════════════════════════════════════
// ACTION: DISPATCH
// ══════════════════════════════════════════
if ($action === 'dispatch') {
    $bl_item_id           = intval($_POST['bl_item_id'] ?? 0);
    $transporter_name     = trim($_POST['transporter_name'] ?? '');
    $truck_number         = strtoupper(trim($_POST['truck_number'] ?? ''));
    $clearing_agent_dnote = trim($_POST['clearing_agent_dnote'] ?? '');
    $transporter_dnote    = trim($_POST['transporter_dnote'] ?? '');
    $destination          = trim($_POST['destination'] ?? '');
    $notes                = trim($_POST['notes'] ?? '');

    if (!$bl_item_id || !$transporter_name || !$truck_number || !$destination) {
        header("Location: view-bl.php?id=$redirect_id&error=missing_fields");
        exit();
    }

    $stmt = $pdo->prepare("SELECT bl_id FROM bl_items WHERE id = ?");
    $stmt->execute([$bl_item_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        header("Location: view-bl.php?id=$redirect_id&error=invalid_item");
        exit();
    }

    $bl_id = $item['bl_id'];

    // Already dispatched?
    $chk = $pdo->prepare("SELECT id FROM dispatches WHERE bl_item_id = ? LIMIT 1");
    $chk->execute([$bl_item_id]);
    if ($chk->fetch()) {
        header("Location: view-bl.php?id=$redirect_id&error=already_dispatched");
        exit();
    }

    try {
        $pdo->beginTransaction();

        $pdo->prepare("
            INSERT INTO dispatches
                (bl_id, bl_item_id, transporter_name, truck_number,
                 clearing_agent_dnote, transporter_dnote, destination,
                 status, dispatched_by, notes, dispatched_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'transit', ?, ?, NOW())
        ")->execute([
            $bl_id,
            $bl_item_id,
            $transporter_name,
            $truck_number,
            $clearing_agent_dnote ?: null,
            $transporter_dnote ?: null,
            $destination,
            $_SESSION['user_id'],
            $notes ?: null,
        ]);

        $pdo->prepare("UPDATE bl_items SET dispatch_status='transit', dispatched_at=NOW() WHERE id=?")
            ->execute([$bl_item_id]);

        $pdo->commit();
        header("Location: view-bl.php?id=$redirect_id&dispatched=1");
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        die("DB Error (dispatch): " . htmlspecialchars($e->getMessage()) .
            " <a href='view-bl.php?id=$redirect_id'>Go Back</a>");
    }
}

// ══════════════════════════════════════════
// ACTION: UPDATE STATUS (Received / Rejected)
// ══════════════════════════════════════════
if ($action === 'update_status') {
    $dispatch_id      = intval($_POST['dispatch_id'] ?? 0);
    $bl_item_id       = intval($_POST['bl_item_id'] ?? 0);
    $new_status       = trim($_POST['new_status'] ?? '');
    $rejection_reason = trim($_POST['rejection_reason'] ?? '');
    $notes            = trim($_POST['notes'] ?? '');

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

        $pdo->prepare("
            UPDATE dispatches SET
                status           = ?,
                received_at      = NOW(),
                received_by      = ?,
                rejection_reason = ?,
                notes            = ?
            WHERE id = ?
        ")->execute([
            $new_status,
            $_SESSION['user_id'],
            ($new_status === 'rejected' && $rejection_reason) ? $rejection_reason : null,
            $notes ?: null,
            $dispatch_id,
        ]);

        $pdo->prepare("UPDATE bl_items SET dispatch_status = ? WHERE id = ?")
            ->execute([$new_status, $bl_item_id]);

        $pdo->commit();

        $param = ($new_status === 'received') ? 'received=1' : 'rejected=1';
        header("Location: view-bl.php?id=$redirect_id&$param");
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        die("DB Error (update_status): " . htmlspecialchars($e->getMessage()) .
            " <a href='view-bl.php?id=$redirect_id'>Go Back</a>");
    }
}

// ══════════════════════════════════════════
// ACTION: RE-DISPATCH (rejected container to new destination)
// ══════════════════════════════════════════
// Creates a fresh dispatch row (status='transit') and increments
// redispatch_count on the old rejected record.
// The view-bl.php query always fetches the LATEST dispatch per item
// (ORDER BY dispatched_at DESC LIMIT 1), so the new row wins automatically.
// ══════════════════════════════════════════
if ($action === 'redispatch') {
    $bl_item_id       = intval($_POST['bl_item_id']      ?? 0);
    $old_dispatch_id  = intval($_POST['old_dispatch_id'] ?? 0);
    $transporter_name = trim($_POST['transporter_name']  ?? '');
    $truck_number     = strtoupper(trim($_POST['truck_number'] ?? ''));
    $clearing_agent_dnote = trim($_POST['clearing_agent_dnote'] ?? '');
    $transporter_dnote    = trim($_POST['transporter_dnote']    ?? '');
    $destination      = trim($_POST['destination'] ?? '');
    $notes            = trim($_POST['notes']       ?? '');

    if (!$bl_item_id || !$old_dispatch_id || !$transporter_name || !$truck_number || !$destination) {
        header("Location: view-bl.php?id=$redirect_id&error=missing_fields");
        exit();
    }

    // Fetch bl_id and verify the old dispatch actually belongs to this item
    // and is in 'rejected' status — prevents accidental double-redispatch.
    $stmt = $pdo->prepare("
        SELECT d.id, d.redispatch_count, i.bl_id
        FROM dispatches d
        JOIN bl_items i ON i.id = d.bl_item_id
        WHERE d.id = ? AND d.bl_item_id = ? AND d.status = 'rejected'
        LIMIT 1
    ");
    $stmt->execute([$old_dispatch_id, $bl_item_id]);
    $old = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$old) {
        header("Location: view-bl.php?id=$redirect_id&error=invalid_item");
        exit();
    }

    $bl_id           = $old['bl_id'];
    $redispatch_count = (int)$old['redispatch_count'] + 1;

    try {
        $pdo->beginTransaction();

        // 1. Bump redispatch_count on the old record so the UI can show "Re-dispatched Nx"
        $pdo->prepare("
            UPDATE dispatches
            SET redispatch_count = ?
            WHERE id = ?
        ")->execute([$redispatch_count, $old_dispatch_id]);

        // 2. Insert the new dispatch record
        $pdo->prepare("
            INSERT INTO dispatches
                (bl_id, bl_item_id, transporter_name, truck_number,
                 clearing_agent_dnote, transporter_dnote, destination,
                 status, dispatched_by, notes, redispatch_count, dispatched_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'transit', ?, ?, ?, NOW())
        ")->execute([
            $bl_id,
            $bl_item_id,
            $transporter_name,
            $truck_number,
            $clearing_agent_dnote ?: null,
            $transporter_dnote    ?: null,
            $destination,
            $_SESSION['user_id'],
            $notes ?: null,
            $redispatch_count,
        ]);

        // 3. Reset item status to 'transit' so Update Status button reappears
        $pdo->prepare("
            UPDATE bl_items
            SET dispatch_status = 'transit', dispatched_at = NOW()
            WHERE id = ?
        ")->execute([$bl_item_id]);

        $pdo->commit();
        header("Location: view-bl.php?id=$redirect_id&redispatched=1");
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        die("DB Error (redispatch): " . htmlspecialchars($e->getMessage()) .
            " <a href='view-bl.php?id=$redirect_id'>Go Back</a>");
    }
}

// ══════════════════════════════════════════
// ACTION: INITIATE EMPTY RETURN
// ══════════════════════════════════════════
// Called after container is marked "received".
// TBL:     depot required  → return_status = 'in_transit'
// Non-TBL: transporter + local_depot required → return_status = 'in_transit'
// Both start as in_transit; user then records arrival (date_in) to complete.
// ══════════════════════════════════════════
if ($action === 'empty_return') {
    $bl_id       = intval($_POST['bl_id']       ?? 0);
    $bl_item_id  = intval($_POST['bl_item_id']  ?? 0);
    $dispatch_id = intval($_POST['dispatch_id'] ?? 0);
    $bl_type     = trim($_POST['bl_type']       ?? '');
    $notes       = trim($_POST['notes']         ?? trim($_POST['notes_nontbl'] ?? ''));

    // TBL fields
    $depot       = trim($_POST['depot']        ?? '');

    // Non-TBL fields
    $transporter = trim($_POST['transporter']  ?? '');
    $local_depot = trim($_POST['local_depot']  ?? '');

    if (!$bl_id || !$bl_item_id || !$dispatch_id || !in_array($bl_type, ['TBL', 'Non-TBL'])) {
        header("Location: view-bl.php?id=$redirect_id&error=missing_fields");
        exit();
    }

    // Already initiated?
    $chk = $pdo->prepare("SELECT id FROM empty_returns WHERE bl_item_id = ? LIMIT 1");
    $chk->execute([$bl_item_id]);
    if ($chk->fetch()) {
        header("Location: view-bl.php?id=$redirect_id&error=return_exists");
        exit();
    }

    // Validate required fields per type
    if ($bl_type === 'TBL' && empty($depot)) {
        header("Location: view-bl.php?id=$redirect_id&error=missing_fields");
        exit();
    }
    if ($bl_type === 'Non-TBL' && (empty($transporter) || empty($local_depot))) {
        header("Location: view-bl.php?id=$redirect_id&error=missing_fields");
        exit();
    }

    try {
        $pdo->beginTransaction();

        $pdo->prepare("
            INSERT INTO empty_returns
                (dispatch_id, bl_id, bl_item_id, bl_type,
                 depot, transporter, local_depot,
                 return_status, created_by, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'in_transit', ?, ?)
        ")->execute([
            $dispatch_id,
            $bl_id,
            $bl_item_id,
            $bl_type,
            $bl_type === 'TBL'     ? $depot       : null,
            $bl_type === 'Non-TBL' ? $transporter : null,
            $bl_type === 'Non-TBL' ? $local_depot : null,
            $_SESSION['user_id'],
            $notes ?: null,
        ]);

        $pdo->prepare("UPDATE bl_items SET empty_return_status='in_transit' WHERE id=?")
            ->execute([$bl_item_id]);

        $pdo->commit();
        header("Location: view-bl.php?id=$redirect_id&return_initiated=1");
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        die("DB Error (empty_return): " . htmlspecialchars($e->getMessage()) .
            " <a href='view-bl.php?id=$redirect_id'>Go Back</a>");
    }
}

// ══════════════════════════════════════════
// ACTION: COMPLETE RETURN (Record Date-In)
// ══════════════════════════════════════════
// User enters the date the container arrived at the depot.
// Sets return_status = 'completed', records returned_date & date_in.
// ══════════════════════════════════════════
if ($action === 'complete_return') {
    $bl_item_id    = intval($_POST['bl_item_id']  ?? 0);
    $return_id     = intval($_POST['return_id']   ?? 0);
    $returned_date = trim($_POST['returned_date'] ?? '');

    if (!$return_id || !$bl_item_id || empty($returned_date)) {
        header("Location: view-bl.php?id=$redirect_id&error=missing_fields");
        exit();
    }

    // Validate date
    if (strtotime($returned_date) === false) {
        header("Location: view-bl.php?id=$redirect_id&error=missing_fields");
        exit();
    }

    try {
        $pdo->beginTransaction();

        $pdo->prepare("
            UPDATE empty_returns SET
                return_status = 'completed',
                date_in       = ?,
                returned_date = ?,
                completed_at  = NOW(),
                completed_by  = ?
            WHERE id = ?
        ")->execute([
            $returned_date,
            $returned_date,
            $_SESSION['user_id'],
            $return_id,
        ]);

        $pdo->prepare("UPDATE bl_items SET empty_return_status='completed' WHERE id=?")
            ->execute([$bl_item_id]);

        $pdo->commit();
        header("Location: view-bl.php?id=$redirect_id&return_completed=1");
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        die("DB Error (complete_return): " . htmlspecialchars($e->getMessage()) .
            " <a href='view-bl.php?id=$redirect_id'>Go Back</a>");
    }
}


die("Unknown action: " . htmlspecialchars($action));
