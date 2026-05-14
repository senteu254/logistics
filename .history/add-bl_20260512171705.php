<?php
require_once 'config/database.php';
$page_title = 'Add B/L';

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $description      = trim($_POST['item_description'] ?? '');
    $containers       = $_POST['container_number']  ?? [];
    $agent_seals      = $_POST['agent_seal_number'] ?? [];
    $bags             = $_POST['number_of_bags']    ?? [];
    $sgs_seals        = $_POST['sgs_seal_number']   ?? [];
    $gross_weights    = $_POST['gross_weight']       ?? [];
    $net_weights      = $_POST['net_weight']         ?? [];

    // Validate
    if (empty($description)) {
        $error = 'Item description is required.';
    } elseif (empty($containers)) {
        $error = 'At least one container row is required.';
    } else {
        // Check required fields in rows
        $rowError = false;
        foreach ($containers as $k => $cn) {
            if (empty($cn) || empty($bags[$k]) || empty($gross_weights[$k]) || empty($net_weights[$k])) {
                $rowError = true;
                break;
            }
        }

        if ($rowError) {
            $error = 'Please fill all required fields in every container row.';
        } else {
            try {
                // Generate BL Number
                $bl_number = 'BL-' . strtoupper(date('Ymd')) . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

                // Insert BL
                $stmt = $pdo->prepare("
                    INSERT INTO bills_of_lading (bl_number, item_description, user_id, status)
                    VALUES (?, ?, ?, 'active')
                ");
                $stmt->execute([$bl_number, $description, $_SESSION['user_id']]);
                $bl_id = $pdo->lastInsertId();

                // Insert Items
                $item_stmt = $pdo->prepare("
                    INSERT INTO bl_items
                        (bl_id, container_number, agent_seal_number, number_of_bags, sgs_seal_number, gross_weight, net_weight)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");

                foreach ($containers as $k => $cn) {
                    $item_stmt->execute([
                        $bl_id,
                        strtoupper(trim($cn)),
                        trim($agent_seals[$k] ?? ''),
                        intval($bags[$k]),
                        trim($sgs_seals[$k] ?? ''),
                        floatval($gross_weights[$k]),
                        floatval($net_weights[$k]),
                    ]);
                }

                $success = "Bill of Lading <strong>$bl_number</strong> created successfully!";

            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

require_once 'includes/header.php';
?>

<!-- Page Header -->
<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3">
    <div>
        <h1 class="page-title">Add Bill of Lading</h1>
        <p class="page-subtitle">Create a new B/L with container details</p>
    </div>
    <a href="dashboard.php" class="btn-modern btn-outline">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>
</div>

<!-- Alerts -->
<?php if ($success): ?>
<div class="alert-modern alert-success-modern">
    <i class="fas fa-circle-check alert-icon" style="color:var(--emerald);"></i>
    <div><?php echo $success; ?> <a href="dashboard.php" style="color:var(--emerald); font-weight:700;">View Dashboard →</a></div>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert-modern alert-error-modern">
    <i class="fas fa-circle-exclamation alert-icon" style="color:var(--rose);"></i>
    <div><?php echo htmlspecialchars($error); ?></div>
</div>
<?php endif; ?>

<form method="POST" id="blForm" novalidate>

    <!-- Item Description -->
    <div class="form-card mb-4">
        <div class="form-card-header">
            <div class="form-card-header-icon">
                <i class="fas fa-file-lines"></i>
            </div>
            <div>
                <h5>Bill of Lading Details</h5>
                <p>Enter the general information for this B/L</p>
            </div>
        </div>
        <div class="form-card-body">
            <div class="field-group">
                <label class="field-label">
                    Item Description <span class="req">*</span>
                </label>
                <textarea
                    name="item_description"
                    class="field-input"
                    placeholder="e.g. Bagged White Sugar, 50kg per bag — Grade A, Origin: Brazil"
                    required
                ><?php echo htmlspecialchars($_POST['item_description'] ?? ''); ?></textarea>
            </div>
        </div>
    </div>

    <!-- Container Rows -->
    <div class="form-card mb-4">
        <div class="form-card-header">
            <div class="form-card-header-icon">
                <i class="fas fa-boxes-stacked"></i>
            </div>
            <div>
                <h5>Container Details</h5>
                <p>Add one or more container rows</p>
            </div>

            <!-- Live Totals -->
            <div class="d-flex gap-3 ms-auto flex-wrap">
                <div style="text-align:center;">
                    <div style="font-size:0.7rem; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; color:var(--text-muted);">Containers</div>
                    <div id="totalRows" style="font-size:1.2rem; font-weight:800; color:var(--indigo);">0</div>
                </div>
                <div style="text-align:center;">
                    <div style="font-size:0.7rem; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; color:var(--text-muted);">Total Bags</div>
                    <div id="totalBags" style="font-size:1.2rem; font-weight:800; color:var(--emerald);">0</div>
                </div>
                <div style="text-align:center;">
                    <div style="font-size:0.7rem; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; color:var(--text-muted);">Gross Wt.</div>
                    <div id="totalGross" style="font-size:1.2rem; font-weight:800; color:var(--amber);">0.00 kg</div>
                </div>
                <div style="text-align:center;">
                    <div style="font-size:0.7rem; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; color:var(--text-muted);">Net Wt.</div>
                    <div id="totalNet" style="font-size:1.2rem; font-weight:800; color:var(--rose);">0.00 kg</div>
                </div>
            </div>
        </div>

        <div class="form-card-body">
            <!-- Dynamic Rows -->
            <div class="item-rows-wrapper" id="itemRowsWrapper"></div>

            <!-- Add Row Button -->
            <button type="button" class="btn-add-row" onclick="addItemRow()">
                <i class="fas fa-circle-plus"></i>
                Add Another Container Row
            </button>
        </div>
    </div>

    <!-- Submit -->
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
        <p style="color:var(--text-muted); font-size:0.82rem;">
            <i class="fas fa-info-circle me-1"></i>
            Fields marked with <span style="color:var(--rose); font-weight:700;">*</span> are required.
        </p>
        <div class="d-flex gap-3">
            <button type="reset" class="btn-modern btn-outline" onclick="location.reload()">
                <i class="fas fa-rotate-left"></i> Reset
            </button>
            <button type="submit" class="btn-modern btn-indigo" id="submitBtn">
                <i class="fas fa-floppy-disk"></i> Save Bill of Lading
            </button>
        </div>
    </div>

</form>

<script>
// Loading state on submit
document.getElementById('blForm').addEventListener('submit', function(e) {
    const btn = document.getElementById('submitBtn');
    const rows = document.querySelectorAll('.item-row');
    if (rows.length === 0) {
        e.preventDefault();
        alert('Please add at least one container row.');
        return;
    }
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    btn.disabled = true;
});
</script>

<?php require_once 'includes/footer.php'; ?>