<?php
require_once 'config/database.php';
$page_title = 'Edit B/L';

$id = intval($_GET['id'] ?? 0);
if (!$id) { header('Location: manage-bl.php'); exit(); }

$blStmt = $pdo->prepare("SELECT * FROM bills_of_lading WHERE id = ?");
$blStmt->execute([$id]);
$bl = $blStmt->fetch();
if (!$bl) { header('Location: manage-bl.php'); exit(); }

$existingItems = $pdo->prepare("SELECT * FROM bl_items WHERE bl_id = ? ORDER BY id ASC");
$existingItems->execute([$id]);
$existingItems = $existingItems->fetchAll();

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bl_number     = strtoupper(trim($_POST['bl_number']      ?? ''));
    $bl_type          = trim($_POST['bl_type']          ?? '');
    $last_return_date = trim($_POST['last_return_date'] ?? '');
    $desc          = trim($_POST['item_description']           ?? '');
    $status        = $_POST['status']                          ?? 'active';
    $containers    = $_POST['container_number']                ?? [];
    $agent_seals   = $_POST['agent_seal_number']              ?? [];
    $bags          = $_POST['number_of_bags']                 ?? [];
    $sgs_seals     = $_POST['sgs_seal_number']                ?? [];
    $gross_weights = $_POST['gross_weight']                   ?? [];
    $net_weights   = $_POST['net_weight']                     ?? [];

    if (empty($bl_number)) {
        $error = 'B/L Number is required.';
    } elseif (empty($desc)) {
        $error = 'Item description is required.';
    } elseif (empty($containers)) {
        $error = 'At least one container row is required.';
    } else {
        // Check uniqueness (exclude current record)
        $chk = $pdo->prepare("SELECT id FROM bills_of_lading WHERE bl_number = ? AND id != ?");
        $chk->execute([$bl_number, $id]);
        if ($chk->fetch()) {
            $error = "B/L Number <strong>$bl_number</strong> is already used by another record.";
        } else {
            $valid = true;
            foreach ($containers as $k => $cn) {
                if (empty($cn) || empty($bags[$k]) || empty($gross_weights[$k]) || empty($net_weights[$k])) {
                    $valid = false;
                    break;
                }
            }

            if (!$valid) {
                $error = 'Please fill all required fields in every container row.';
            } else {
                try {
                    // Update BL
                    $upd = $pdo->prepare("
    UPDATE bills_of_lading
    SET bl_number=?, bl_type=?, last_return_date=?, item_description=?, status=?
    WHERE id=?
");
$upd->execute([$bl_number, $bl_type, $last_return_date, $desc, $status, $id]);


                    // Delete + re-insert items
                    $pdo->prepare("DELETE FROM bl_items WHERE bl_id=?")->execute([$id]);

                    $ins = $pdo->prepare("
                        INSERT INTO bl_items
                            (bl_id, container_number, agent_seal_number,
                             number_of_bags, sgs_seal_number, gross_weight, net_weight)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");

                    foreach ($containers as $k => $cn) {
                        $ins->execute([
                            $id,
                            strtoupper(trim($cn)),
                            trim($agent_seals[$k]   ?? ''),
                            intval($bags[$k]),
                            trim($sgs_seals[$k]     ?? ''),
                            floatval($gross_weights[$k]),
                            floatval($net_weights[$k]),
                        ]);
                    }

                    // Refresh data
                    $blStmt = $pdo->prepare("SELECT * FROM bills_of_lading WHERE id=?");
                    $blStmt->execute([$id]);
                    $bl = $blStmt->fetch();

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
}

require_once 'includes/header.php';
?>

<style>
.bl-number-field-wrap { position: relative; }

.bl-number-prefix {
    position: absolute;
    left: 0; top: 0; bottom: 0;
    display: flex;
    align-items: center;
    padding: 0 14px;
    background: linear-gradient(135deg, var(--indigo), var(--violet));
    border-radius: var(--r-sm) 0 0 var(--r-sm);
    color: white;
    font-size: 0.78rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    white-space: nowrap;
    pointer-events: none;
    gap: 7px;
}

.bl-number-input {
    width: 100%;
    background: var(--bg);
    border: 1.5px solid var(--indigo);
    border-radius: var(--r-sm);
    padding: 11px 44px 11px 110px;
    font-family: 'Courier New', monospace;
    font-size: 1rem;
    font-weight: 700;
    color: var(--indigo);
    letter-spacing: 1px;
    transition: var(--t);
    outline: none;
    text-transform: uppercase;
}

.bl-number-input:focus {
    border-color: var(--violet);
    background: white;
    box-shadow: 0 0 0 3px rgba(99,102,241,0.12);
}

.bl-number-input::placeholder {
    color: var(--txt-3);
    font-size: 0.88rem;
    letter-spacing: 0;
    font-family: 'Inter', sans-serif;
    font-weight: 400;
}

.bl-check-icon {
    position: absolute;
    right: 13px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 0.9rem;
    pointer-events: none;
    display: none;
}

.bl-check-icon.valid   { display:block; color: var(--emerald); }
.bl-check-icon.invalid { display:block; color: var(--rose); }

.bl-number-hint {
    font-size: 0.73rem;
    color: var(--txt-3);
    margin-top: 5px;
    display: flex;
    align-items: center;
    gap: 5px;
    font-weight: 500;
}

.bl-number-hint.checking { color: var(--amber); }
.bl-number-hint.taken    { color: var(--rose); }
.bl-number-hint.free     { color: var(--emerald); }
</style>

<!-- Page Header -->
<div class="page-header d-flex align-items-start justify-content-between flex-wrap gap-3">
    <div>
        <h1 class="page-title">Edit B/L</h1>
        <p class="page-sub">
            Editing &nbsp;
            <span style="font-family:monospace;font-weight:700;color:var(--indigo);">
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
    <i class="fas fa-circle-check alert-ico" style="color:var(--emerald);flex-shrink:0;"></i>
    <div>
        <?php echo $success; ?>
        <a href="view-bl.php?id=<?php echo $id; ?>" style="color:var(--emerald);font-weight:700;">
            View B/L →
        </a>
    </div>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert-m alert-error">
    <i class="fas fa-circle-exclamation alert-ico" style="color:var(--rose);flex-shrink:0;"></i>
    <div><?php echo $error; ?></div>
</div>
<?php endif; ?>

<form method="POST" id="editForm" novalidate>

    <!-- BL Details -->
    <div class="form-card">
        <div class="form-card-head">
            <div class="form-card-ico"><i class="fas fa-file-pen"></i></div>
            <div>
                <h5>B/L Details</h5>
                <p>Update the B/L number, description and status</p>
            </div>
        </div>
        <div class="form-card-body">
            <div class="row g-3">

                <!-- BL Number -->
                <div class="col-md-8">
                    <label class="f-label">
                        B/L Number <span class="req">*</span>
                        <span style="font-size:0.7rem;font-weight:400;color:var(--txt-3);text-transform:none;letter-spacing:0;margin-left:6px;">
                            (Primary identifier)
                        </span>
                    </label>
                    <div class="bl-number-field-wrap">
                        <div class="bl-number-prefix">
                            <i class="fas fa-hashtag"></i>
                            B/L No.
                        </div>
                        <input
                            type="text"
                            name="bl_number"
                            id="blNumberInput"
                            class="bl-number-input"
                            value="<?php echo htmlspecialchars($bl['bl_number']); ?>"
                            required
                            autocomplete="off"
                            data-original="<?php echo htmlspecialchars($bl['bl_number']); ?>"
                            oninput="this.value=this.value.toUpperCase(); checkBLNumber(this.value)"
                        >
                        <i class="fas fa-circle-check bl-check-icon" id="blCheckIcon"></i>
                    </div>
                    <div class="bl-number-hint" id="blHint">
                        <i class="fas fa-circle-info"></i>
                        Change the B/L number only if necessary
                    </div>
                </div>

                <!-- Status -->
                <div class="col-md-4">
                    <label class="f-label">Status</label>
                    <select name="status" class="f-input" style="cursor:pointer;">
                        <option value="active"    <?php echo $bl['status']==='active'    ?'selected':''; ?>>● Active</option>
                        <option value="draft"     <?php echo $bl['status']==='draft'     ?'selected':''; ?>>● Draft</option>
                        <option value="completed" <?php echo $bl['status']==='completed' ?'selected':''; ?>>● Completed</option>
                    </select>
                </div>

                <!-- Description -->
                <div class="col-12">
                    <label class="f-label">Item Description <span class="req">*</span></label>
                    <textarea
                        name="item_description"
                        class="f-input"
                        required
                        rows="3"
                    ><?php echo htmlspecialchars($bl['item_description']); ?></textarea>
                </div>

            </div>
        </div>
    </div>

    <!-- Container Rows -->
    <div class="form-card">
        <div class="form-card-head" style="flex-wrap:wrap;gap:12px;">
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

<script>
/* ── Pre-populate existing rows ── */
const existingItems = <?php echo json_encode($existingItems); ?>;

document.addEventListener('DOMContentLoaded', () => {
    if (existingItems && existingItems.length > 0) {
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

/* ── BL Number check (skip if unchanged) ── */
let blCheckTimer;

function checkBLNumber(val) {
    const icon     = document.getElementById('blCheckIcon');
    const hint     = document.getElementById('blHint');
    const original = document.getElementById('blNumberInput').dataset.original;

    icon.className = 'fas fa-circle-check bl-check-icon';
    clearTimeout(blCheckTimer);

    if (!val || val.length < 2) {
        hint.className = 'bl-number-hint';
        hint.innerHTML = '<i class="fas fa-circle-info"></i> Change the B/L number only if necessary';
        return;
    }

    // If same as original, it's fine
    if (val === original) {
        icon.className = 'fas fa-circle-check bl-check-icon valid';
        hint.className = 'bl-number-hint free';
        hint.innerHTML = '<i class="fas fa-circle-check"></i> Current B/L Number';
        return;
    }

    hint.className = 'bl-number-hint checking';
    hint.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking availability...';

    blCheckTimer = setTimeout(() => {
        fetch(`check-bl.php?bl_number=${encodeURIComponent(val)}`)
            .then(r => r.json())
            .then(data => {
                if (data.available) {
                    icon.className = 'fas fa-circle-check bl-check-icon valid';
                    hint.className = 'bl-number-hint free';
                    hint.innerHTML = '<i class="fas fa-circle-check"></i> B/L Number is available';
                } else {
                    icon.className = 'fas fa-circle-xmark bl-check-icon invalid';
                    hint.className = 'bl-number-hint taken';
                    hint.innerHTML = '<i class="fas fa-circle-xmark"></i> This B/L Number is already taken';
                }
            })
            .catch(() => {
                hint.className = 'bl-number-hint';
                hint.innerHTML = '<i class="fas fa-circle-info"></i> Could not verify — will be checked on save';
            });
    }, 600);
}

/* ── Submit guard ── */
document.getElementById('editForm').addEventListener('submit', function(e) {
    if (!document.querySelector('.item-row')) {
        e.preventDefault();
        alert('Please add at least one container row.');
        return;
    }
    const btn = document.getElementById('submitBtn');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
    btn.disabled  = true;
});
</script>

<?php require_once 'includes/footer.php'; ?>