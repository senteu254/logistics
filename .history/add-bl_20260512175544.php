<?php
require_once 'config/database.php';
$page_title = 'Add B/L';

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $desc          = trim($_POST['item_description'] ?? '');
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
            $error = 'Please fill all required fields in every container row.';
        } else {
            try {
                $bl_num = 'BL-' . date('Ymd') . '-' . str_pad(mt_rand(1,9999),4,'0',STR_PAD_LEFT);
                $stmt = $pdo->prepare("INSERT INTO bills_of_lading (bl_number,item_description,user_id,status) VALUES (?,?,?,'active')");
                $stmt->execute([$bl_num, $desc, $_SESSION['user_id']]);
                $bl_id = $pdo->lastInsertId();

                $ins = $pdo->prepare("INSERT INTO bl_items (bl_id,container_number,agent_seal_number,number_of_bags,sgs_seal_number,gross_weight,net_weight) VALUES (?,?,?,?,?,?,?)");
                foreach ($containers as $k => $cn) {
                    $ins->execute([
                        $bl_id,
                        strtoupper(trim($cn)),
                        trim($agent_seals[$k] ?? ''),
                        intval($bags[$k]),
                        trim($sgs_seals[$k] ?? ''),
                        floatval($gross_weights[$k]),
                        floatval($net_weights[$k]),
                    ]);
                }
                $success = $bl_num;
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
        <h1 class="page-title">Add Bill of Lading</h1>
        <p class="page-sub">Create a new B/L with container details</p>
    </div>
    <a href="dashboard.php" class="btn-m btn-ghost">
        <i class="fas fa-arrow-left"></i> Back
    </a>
</div>

<!-- Alerts -->
<?php if ($success): ?>
<div class="alert-m alert-success">
    <i class="fas fa-circle-check alert-ico" style="color:var(--emerald);"></i>
    <div>
        Bill of Lading <strong><?php echo $success; ?></strong> created successfully!
        &nbsp;<a href="dashboard.php" style="color:var(--emerald);font-weight:700;">View Dashboard →</a>
    </div>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert-m alert-error">
    <i class="fas fa-circle-exclamation alert-ico" style="color:var(--rose);"></i>
    <div><?php echo htmlspecialchars($error); ?></div>
</div>
<?php endif; ?>

<form method="POST" id="blForm" novalidate>

    <!-- Description Card -->
    <div class="form-card">
        <div class="form-card-head">
            <div class="form-card-ico"><i class="fas fa-file-lines"></i></div>
            <div>
                <h5>B/L Details</h5>
                <p>General information for this Bill of Lading</p>
            </div>
        </div>
        <div class="form-card-body">
            <label class="f-label">Item Description <span class="req">*</span></label>
            <textarea name="item_description" class="f-input" required
                placeholder="e.g. Bagged White Sugar, 50kg per bag — Grade A, Origin: Brazil"><?php echo htmlspecialchars($_POST['item_description'] ?? ''); ?></textarea>
        </div>
    </div>

    <!-- Container Card -->
    <div class="form-card">
        <div class="form-card-head">
            <div class="form-card-ico"><i class="fas fa-boxes-stacked"></i></div>
            <div>
                <h5>Container Details</h5>
                <p>Add one or more container rows below</p>
            </div>
            <!-- Live Totals -->
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
                    <span class="total-item-val c-amber" id="tGross">0.00 M.T</span>
                </div>
                <div class="total-item">
                    <span class="total-item-lbl">Net Wt.</span>
                    <span class="total-item-val c-rose" id="tNet">0.00 M.T</span>
                </div>
            </div>
        </div>

        <div class="form-card-body">
            <!-- Dynamic Rows -->
            <div class="rows-wrap" id="rowsWrap"></div>

            <!-- Add Row -->
            <button type="button" class="btn-add-row" onclick="addItemRow()">
                <i class="fas fa-circle-plus"></i>
                Add Another Container Row
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
            <button type="button" class="btn-m btn-ghost" onclick="location.reload()">
                <i class="fas fa-rotate-left"></i> Reset
            </button>
            <button type="submit" class="btn-m btn-indigo" id="submitBtn">
                <i class="fas fa-floppy-disk"></i> Save Bill of Lading
            </button>
        </div>
    </div>

</form>

<script>
document.getElementById('blForm').addEventListener('submit', function(e) {
    if (!document.querySelector('.item-row')) {
        e.preventDefault();
        alert('Please add at least one container row.');
        return;
    }
    const btn = document.getElementById('submitBtn');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    btn.disabled = true;
});
</script>

<?php require_once 'includes/footer.php'; ?>