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
// HELPER: check if all containers on a B/L
// are fully returned and mark the B/L completed
// ══════════════════════════════════════════
function maybeCompleteBL(PDO $pdo, int $bl_id): void
{
    // Count items that still have outstanding dispatch/return work
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM bl_items
        WHERE bl_id = ?
          AND NOT (
              dispatch_status = 'received'
              AND return_status = 'returned'
          )
    ");
    $stmt->execute([$bl_id]);
    $outstanding = (int)$stmt->fetchColumn();

    if ($outstanding === 0) {
        $pdo->prepare("
            UPDATE bills_of_lading
            SET status = 'completed', updated_at = NOW()
            WHERE id = ?
        ")->execute([$bl_id]);
    }
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
        die("DB Error (dispatch): " . htmlspecialchars($e->getMessage()) . " <a href='view-bl.php?id=$redirect_id'>Go Back</a>");
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

    // Get bl_id for the completion check later
    $stmt = $pdo->prepare("SELECT bl_id FROM bl_items WHERE id = ?");
    $stmt->execute([$bl_item_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        header("Location: view-bl.php?id=$redirect_id&error=invalid_item");
        exit();
    }
    $bl_id = $row['bl_id'];

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

        if ($new_status === 'received') {
            // Mark item received AND set return_status to pending_return
            // so the "Return Empty" button appears immediately
            $pdo->prepare("
                UPDATE bl_items
                SET dispatch_status = 'received',
                    return_status   = 'pending_return'
                WHERE id = ?
            ")->execute([$bl_item_id]);
        } else {
            $pdo->prepare("UPDATE bl_items SET dispatch_status = 'rejected' WHERE id = ?")
                ->execute([$bl_item_id]);
        }

        $pdo->commit();
        $param = ($new_status === 'received') ? 'received=1' : 'rejected=1';
        header("Location: view-bl.php?id=$redirect_id&$param");
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        die("DB Error (update_status): " . htmlspecialchars($e->getMessage()) . " <a href='view-bl.php?id=$redirect_id'>Go Back</a>");
    }
}

// ══════════════════════════════════════════
// ACTION: RE-DISPATCH (rejected → new destination)
// ══════════════════════════════════════════
if ($action === 'redispatch') {
    $bl_item_id           = intval($_POST['bl_item_id']      ?? 0);
    $old_dispatch_id      = intval($_POST['old_dispatch_id'] ?? 0);
    $transporter_name     = trim($_POST['transporter_name']  ?? '');
    $truck_number         = strtoupper(trim($_POST['truck_number'] ?? ''));
    $clearing_agent_dnote = trim($_POST['clearing_agent_dnote'] ?? '');
    $transporter_dnote    = trim($_POST['transporter_dnote']    ?? '');
    $destination          = trim($_POST['destination'] ?? '');
    $notes                = trim($_POST['notes']       ?? '');

    if (!$bl_item_id || !$old_dispatch_id || !$transporter_name || !$truck_number || !$destination) {
        header("Location: view-bl.php?id=$redirect_id&error=missing_fields");
        exit();
    }

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

    $bl_id            = $old['bl_id'];
    $redispatch_count = (int)$old['redispatch_count'] + 1;

    try {
        $pdo->beginTransaction();

        $pdo->prepare("UPDATE dispatches SET redispatch_count = ? WHERE id = ?")
            ->execute([$redispatch_count, $old_dispatch_id]);

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
            $transporter_dnote ?: null,
            $destination,
            $_SESSION['user_id'],
            $notes ?: null,
            $redispatch_count,
        ]);

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
        die("DB Error (redispatch): " . htmlspecialchars($e->getMessage()) . " <a href='view-bl.php?id=$redirect_id'>Go Back</a>");
    }
}

// ══════════════════════════════════════════
// ACTION: INITIATE TBL EMPTY RETURN
// ══════════════════════════════════════════
// TBL containers are returned directly to the shipping line depot.
// Step 1 of 2: user enters depot name → status = 'in_transit_return'
// (Container is notionally "on its way" to the depot until date-in is confirmed)
// ══════════════════════════════════════════
if ($action === 'return_tbl_initiate') {
    $bl_item_id  = intval($_POST['bl_item_id']  ?? 0);
    $dispatch_id = intval($_POST['dispatch_id'] ?? 0);
    $tbl_depot   = trim($_POST['tbl_depot']     ?? '');

    if (!$bl_item_id || !$dispatch_id || empty($tbl_depot)) {
        header("Location: view-bl.php?id=$redirect_id&error=missing_fields");
        exit();
    }

    // Get bl_id
    $stmt = $pdo->prepare("SELECT bl_id FROM bl_items WHERE id = ? AND dispatch_status = 'received'");
    $stmt->execute([$bl_item_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        header("Location: view-bl.php?id=$redirect_id&error=invalid_item");
        exit();
    }
    $bl_id = $row['bl_id'];

    // Already has a return record?
    $chk = $pdo->prepare("SELECT id FROM empty_container_returns WHERE bl_item_id = ? LIMIT 1");
    $chk->execute([$bl_item_id]);
    if ($chk->fetch()) {
        header("Location: view-bl.php?id=$redirect_id&error=return_exists");
        exit();
    }

    try {
        $pdo->beginTransaction();

        $pdo->prepare("
            INSERT INTO empty_container_returns
                (bl_id, bl_item_id, dispatch_id, bl_type,
                 tbl_depot, return_status, created_by, created_at)
            VALUES (?, ?, ?, 'TBL', ?, 'in_transit_return', ?, NOW())
        ")->execute([$bl_id, $bl_item_id, $dispatch_id, $tbl_depot, $_SESSION['user_id']]);

        $pdo->prepare("UPDATE bl_items SET return_status = 'in_transit_return' WHERE id = ?")
            ->execute([$bl_item_id]);

        $pdo->commit();
        header("Location: view-bl.php?id=$redirect_id&return_initiated=1");
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        die("DB Error (return_tbl_initiate): " . htmlspecialchars($e->getMessage()) . " <a href='view-bl.php?id=$redirect_id'>Go Back</a>");
    }
}

// ══════════════════════════════════════════
// ACTION: COMPLETE TBL EMPTY RETURN
// ══════════════════════════════════════════
// Step 2 of 2: user enters the Date In (container arrived at TBL depot)
// → return_status = 'returned', B/L completion check runs
// ══════════════════════════════════════════
if ($action === 'return_tbl_complete') {
    $bl_item_id  = intval($_POST['bl_item_id']  ?? 0);
    $return_id   = intval($_POST['return_id']   ?? 0);
    $tbl_date_in = trim($_POST['tbl_date_in']   ?? '');

    if (!$bl_item_id || !$return_id || empty($tbl_date_in)) {
        header("Location: view-bl.php?id=$redirect_id&error=missing_fields");
        exit();
    }
    if (!strtotime($tbl_date_in)) {
        header("Location: view-bl.php?id=$redirect_id&error=missing_fields");
        exit();
    }

    $stmt = $pdo->prepare("SELECT bl_id FROM bl_items WHERE id = ?");
    $stmt->execute([$bl_item_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        header("Location: view-bl.php?id=$redirect_id&error=invalid_item");
        exit();
    }
    $bl_id = $row['bl_id'];

    try {
        $pdo->beginTransaction();

        $pdo->prepare("
            UPDATE empty_container_returns SET
                tbl_date_in  = ?,
                return_status = 'returned',
                completed_by  = ?,
                completed_at  = NOW()
            WHERE id = ?
        ")->execute([$tbl_date_in, $_SESSION['user_id'], $return_id]);

        $pdo->prepare("UPDATE bl_items SET return_status = 'returned' WHERE id = ?")
            ->execute([$bl_item_id]);

        $pdo->commit();

        // Check if the whole B/L is now done
        maybeCompleteBL($pdo, $bl_id);

        header("Location: view-bl.php?id=$redirect_id&return_completed=1");
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        die("DB Error (return_tbl_complete): " . htmlspecialchars($e->getMessage()) . " <a href='view-bl.php?id=$redirect_id'>Go Back</a>");
    }
}

// ══════════════════════════════════════════
// ACTION: INITIATE NON-TBL EMPTY RETURN
// ══════════════════════════════════════════
// Non-TBL: container goes via a local transporter to a local depot.
// Step 1 of 2: user enters transporter + truck + depot → status = 'in_transit_return'
// ══════════════════════════════════════════
if ($action === 'return_nontbl_initiate') {
    $bl_item_id         = intval($_POST['bl_item_id']         ?? 0);
    $dispatch_id        = intval($_POST['dispatch_id']        ?? 0);
    $nontbl_transporter = trim($_POST['nontbl_transporter']   ?? '');
    $nontbl_truck       = strtoupper(trim($_POST['nontbl_truck_number'] ?? ''));
    $nontbl_depot       = trim($_POST['nontbl_final_depot']   ?? '');

    if (!$bl_item_id || !$dispatch_id || !$nontbl_transporter || !$nontbl_truck || !$nontbl_depot) {
        header("Location: view-bl.php?id=$redirect_id&error=missing_fields");
        exit();
    }

    $stmt = $pdo->prepare("SELECT bl_id FROM bl_items WHERE id = ? AND dispatch_status = 'received'");
    $stmt->execute([$bl_item_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        header("Location: view-bl.php?id=$redirect_id&error=invalid_item");
        exit();
    }
    $bl_id = $row['bl_id'];

    $chk = $pdo->prepare("SELECT id FROM empty_container_returns WHERE bl_item_id = ? LIMIT 1");
    $chk->execute([$bl_item_id]);
    if ($chk->fetch()) {
        header("Location: view-bl.php?id=$redirect_id&error=return_exists");
        exit();
    }

    try {
        $pdo->beginTransaction();

        $pdo->prepare("
            INSERT INTO empty_container_returns
                (bl_id, bl_item_id, dispatch_id, bl_type,
                 nontbl_transporter, nontbl_truck_number, nontbl_final_depot,
                 return_status, created_by, created_at)
            VALUES (?, ?, ?, 'Non-TBL', ?, ?, ?, 'in_transit_return', ?, NOW())
        ")->execute([
            $bl_id,
            $bl_item_id,
            $dispatch_id,
            $nontbl_transporter,
            $nontbl_truck,
            $nontbl_depot,
            $_SESSION['user_id'],
        ]);

        $pdo->prepare("UPDATE bl_items SET return_status = 'in_transit_return' WHERE id = ?")
            ->execute([$bl_item_id]);

        $pdo->commit();
        header("Location: view-bl.php?id=$redirect_id&return_initiated=1");
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        die("DB Error (return_nontbl_initiate): " . htmlspecialchars($e->getMessage()) . " <a href='view-bl.php?id=$redirect_id'>Go Back</a>");
    }
}

// ══════════════════════════════════════════
// ACTION: COMPLETE NON-TBL EMPTY RETURN
// ══════════════════════════════════════════
// Step 2 of 2: container arrived at local depot — user enters Date In
// → return_status = 'returned', B/L completion check runs
// ══════════════════════════════════════════
if ($action === 'return_nontbl_complete') {
    $bl_item_id   = intval($_POST['bl_item_id']  ?? 0);
    $return_id    = intval($_POST['return_id']   ?? 0);
    $nontbl_date_in = trim($_POST['nontbl_date_in'] ?? '');

    if (!$bl_item_id || !$return_id || empty($nontbl_date_in)) {
        header("Location: view-bl.php?id=$redirect_id&error=missing_fields");
        exit();
    }
    if (!strtotime($nontbl_date_in)) {
        header("Location: view-bl.php?id=$redirect_id&error=missing_fields");
        exit();
    }

    $stmt = $pdo->prepare("SELECT bl_id FROM bl_items WHERE id = ?");
    $stmt->execute([$bl_item_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        header("Location: view-bl.php?id=$redirect_id&error=invalid_item");
        exit();
    }
    $bl_id = $row['bl_id'];

    try {
        $pdo->beginTransaction();

        $pdo->prepare("
            UPDATE empty_container_returns SET
                nontbl_date_in = ?,
                return_status  = 'returned',
                completed_by   = ?,
                completed_at   = NOW()
            WHERE id = ?
        ")->execute([$nontbl_date_in, $_SESSION['user_id'], $return_id]);

        $pdo->prepare("UPDATE bl_items SET return_status = 'returned' WHERE id = ?")
            ->execute([$bl_item_id]);

        $pdo->commit();

        // Check if the whole B/L is now done
        maybeCompleteBL($pdo, $bl_id);

        header("Location: view-bl.php?id=$redirect_id&return_completed=1");
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        die("DB Error (return_nontbl_complete): " . htmlspecialchars($e->getMessage()) . " <a href='view-bl.php?id=$redirect_id'>Go Back</a>");
    }
}

die("Unknown action: " . htmlspecialchars($action));
