<?php
require_once 'config/database.php';
$page_title = 'Edit B/L';

$id = intval($_GET['id'] ?? 0);
if (!$id) { header('Location: manage-bl.php'); exit(); }

$bl = $pdo->prepare("SELECT * FROM bills_of_lading WHERE id = ?");
$bl->execute([$id]);
$bl = $bl->fetch();
if (!$bl) { header('Location: manage-bl.php'); exit(); }

$existingItems = $pdo->prepare("SELECT * FROM bl_items WHERE bl_id = ? ORDER BY id ASC");
$existingItems->execute([$id]);
$existingItems = $existingItems->fetchAll();

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $desc          = trim($_POST['item_description'] ?? '');
    $status        = $_POST['status'] ?? 'active';
    $containers    = $_POST['container_number']  ?? [];
    $agent_seals   = $_POST['agent_seal_number'] ?? [];
    $bags          = $_POST['number_of_bags']    ?? [];
    $sgs_seals     = $_POST['sgs_seal_number']   ?? [];
    $gross_weights = $_POST['gross_weight']      ?? [];
    $net_weights   = $_POST['net_weight']        ?? [];

    if (empty($desc)) {
        $error = 'Item description is required.';
    } elseif (empty($containers)) {
        $error = 'At least one container row is required.';
    } else {
        $valid = true;
        foreach ($containers as $k => $cn) {
            if (empty($cn) || empty($bags[$k]) || empty($gross_weights[$k]) || empty($net_weights[$k])) {
                $valid = false; break;
            }
        }

        if (!$valid) {
            $error = 'Please fill all required fields.';
        } else {
            try {
                // Update BL
                $upd = $pdo->prepare("UPDATE bills_of_lading SET item_description=?,status=? WHERE id=?");
                $upd->execute([$desc, $status, $id]);

                // Delete old items
                $pdo->prepare("DELETE FROM bl_items WHERE bl_id=?")->execute([$id]);

                // Re-insert
                $ins = $pdo->prepare("INSERT INTO bl_items (bl_id,container_number,agent_seal_number,number_of_bags,sgs_seal_number,gross_weight,net_weight) VALUES (?,?,?,?,?,?,?)");
                foreach ($containers as $k => $cn) {
                    $ins->execute([
                        $id,
                        strtoupper(trim($cn)),
                        trim($agent_seals[$k] ?? ''),
                        intval($bags[$k]),
                        trim($sgs_seals[$k] ?? ''),
                        floatval($gross_weights[$k]),
                        floatval($net_weights[$k]),
                    ]);
                }

                // Refresh
                $bl = $pdo->prepare("SELECT * FROM bills_of_lading WHERE id=?");
                $bl->execute([$id]);
                $bl = $bl->fetch();

                $existingItems = $pdo->prepare("SELECT * FROM bl_items WHERE bl_id=? ORDER BY id ASC");
                $existingItems->execute([$id]);
                $existingItems = $existingItems->fetchAll();

                $success = 'Bill of Lading updated successfully!';

            } catch (PDOException $e) {
                $error = 'Error: ' . $e->getMessage();
            }
        }
    }
}

require_once 'includes/header.php';
?>

<!-- Page Header -->
<div class="page-header d-flex align-items-start justify-content-between flex-wrap gap-3">
    <div>
        <h1 class="page-title">Edit B/L</h1>
        <p class="page-sub">
            Editing &nbsp;<span style="font-family:monospace;font-weight:700;color:var(--indigo);">
                <?php echo htmlspecialchars($bl['bl_number']); ?>
            </span>
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="view-bl.php?id=<?php echo $id; ?>" class="btn-m btn-ghost">
            <i class="fas fa-eye"></i> View
        </a>
        <a href="manage-bl.php" class="btn-m btn-ghost">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>
</div>

<!-- Alerts -->
<?php if ($success): ?>
<div class="alert-m alert-success">
    <i class="fas fa-circle-check alert-ico" style="color:var(--emerald);"></i>
    <div><?php echo $success; ?> <a href="view-bl.php?id=<?php echo $id; ?>" style="color:var(--emerald);font-weight:700;">View B/L →</a></div>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert-m alert-error">
    <i class="fas fa-circle-exclamation alert-ico" style="color:var(--rose);"></i>
    <div><?php echo htmlspecialchars($error); ?></div>
</div>
<?php endif; ?>

<form method="POST" id="editForm" novalidate>

    <!-- BL Details -->
    <div class="form-card">
        <div class="form-card-head">
            <div class="form-card-ico"><i class="fas fa-file-pen"></i></div>
            <div>
                <h5>B/L Details</h5>
                <p><?php echo htmlspecialchars($bl['bl_number']); ?></p>
            </div>
        </div>
        <div class="form-card-body">
            <div class="row g-3">
                <div class="col-md-9">
                    <label class="f-label">Item Description <span class="req">*</span></label>
                    <textarea name="item_description" class="f-input" required><?php echo htmlspecialchars($bl['item_description']); ?></textarea>
                </div>
                <div class="col-md-3">
                    <label class="f-label">Status</label>
                    <select name="status" class="f-input" style="cursor:pointer;">
                        <option value="active"    <?php echo $bl['status']=='active'    ?'selected':''; ?>>Active</option>
                        <option value="draft"     <?php echo $bl['status']=='draft'     ?'selected':''; ?>>Draft</option>
                        <option value="completed" <?php echo $bl['status']=='completed' ?'selected':''; ?>>Completed</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Containers -->
    <div class="form-card">
        <div class="form-card-head">
            <div class="form-card-ico"><i class="fas fa-boxes-stacked"></i></div>
            <div>
                <h5>Container Details</h5>
                <p>Edit container rows below</p>
            </div>
            <div class="totals-bar">
                <div class="total-item">
                    <span class="total-item-lbl">Containers</span>
                    <span class="total-item-val c-indigo" id="tRows">0</span>
                </div>
                <div class="total-item">
                    <span class="total-item-lbl">Total Bags</span>
                    <span class="total-item-val c-emerald" id="tBags">0</span>
                </div>
                <div class="total-item">
                    <span class="total-item-lbl">Gross Wt.</span>
                    <span class="total-item-val c-amber" id="tGross">0.00 kg</span>
                </div>
                <div class="total-item">
                    <span class="total-item-lbl">Net Wt.</span>
                    <span class="total-item-val c-rose" id="tNet">0.00 kg</span>
                </div>
            </div>
        </div>
        <div class="form-card-body">
            <div class="rows-wrap" id="rowsWrap"></div>
            <button type="button" class="btn-add-row" onclick="addItemRow()">
                <i class="fas fa-circle-plus"></i> Add Another Container Row
            </button>
        </div>
    </div>

    <!-- Actions -->
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mt-1">
        <p style="color:var(--txt-3);font-size:0.8rem;">
            <i class="fas fa-circle-info me-1"></i>
            Fields marked <span style="color:var(--rose);font-weight:700;">*</span> are required.
        </p>
        <div class="d-flex gap-3 flex-wrap">
            <a href="view-bl.php?id=<?php echo $id; ?>" class="btn-m btn-ghost">
                <i class="fas fa-xmark"></i> Cancel
            </a>
            <button type="submit" class="btn-m btn-indigo" id="submitBtn">
                <i class="fas fa-floppy-disk"></i> Update Bill of Lading
            </button>
        </div>
    </div>

</form>

<!-- Pre-populate existing items -->
<script>
const existingItems = <?php echo json_encode($existingItems); ?>;

document.addEventListener('DOMContentLoaded', () => {
    if (existingItems.length > 0) {
        existingItems.forEach(item => {
            addItemRow();
            const rows = document.querySelectorAll('.item-row');
            const row  = rows[rows.length - 1];

            row.querySelector('input[name="container_number[]"]').value  = item.container_number  || '';
            row.querySelector('input[name="agent_seal_number[]"]').value = item.agent_seal_number || '';
            row.querySelector('input[name="number_of_bags[]"]').value    = item.number_of_bags    || '';
            row.querySelector('input[name="sgs_seal_number[]"]').value   = item.sgs_seal_number   || '';
            row.querySelector('input[name="gross_weight[]"]').value      = item.gross_weight      || '';
            row.querySelector('input[name="net_weight[]"]').value        = item.net_weight        || '';
        });
        calcTotals();
    } else {
        addItemRow();
    }
});

document.getElementById('editForm').addEventListener('submit', function(e) {
    if (!document.querySelector('.item-row')) {
        e.preventDefault();
        alert('Please add at least one container row.');
        return;
    }
    const btn = document.getElementById('submitBtn');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
    btn.disabled = true;
});
</script>

<?php require_once 'includes/footer.php'; ?>