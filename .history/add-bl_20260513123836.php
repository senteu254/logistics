<?php
require_once 'config/database.php';
$page_title = 'Add B/L';

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bl_number     = strtoupper(trim($_POST['bl_number']       ?? ''));
    $desc          = trim($_POST['item_description']            ?? '');
    $containers    = $_POST['container_number']                 ?? [];
    $agent_seals   = $_POST['agent_seal_number']               ?? [];
    $bags          = $_POST['number_of_bags']                  ?? [];
    $sgs_seals     = $_POST['sgs_seal_number']                 ?? [];
    $gross_weights = $_POST['gross_weight']                    ?? [];
    $net_weights   = $_POST['net_weight']                      ?? [];

    if (empty($bl_number)) {
        $error = 'B/L Number is required.';
    } elseif (empty($desc)) {
        $error = 'Item description is required.';
    } elseif (empty($containers)) {
        $error = 'At least one container row is required.';
    } else {
        // Check BL number uniqueness
        $chk = $pdo->prepare("SELECT id FROM bills_of_lading WHERE bl_number = ?");
        $chk->execute([$bl_number]);
        if ($chk->fetch()) {
            $error = "B/L Number <strong>$bl_number</strong> already exists. Please use a unique B/L number.";
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
                    $stmt = $pdo->prepare("
                        INSERT INTO bills_of_lading
                            (bl_number, item_description, user_id, status)
                        VALUES (?, ?, ?, 'active')
                    ");
                    $stmt->execute([$bl_number, $desc, $_SESSION['user_id']]);
                    $bl_id = $pdo->lastInsertId();

                    $ins = $pdo->prepare("
                        INSERT INTO bl_items
                            (bl_id, container_number, agent_seal_number,
                             number_of_bags, sgs_seal_number, gross_weight, net_weight)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");

                    foreach ($containers as $k => $cn) {
                        $ins->execute([
                            $bl_id,
                            strtoupper(trim($cn)),
                            trim($agent_seals[$k]    ?? ''),
                            intval($bags[$k]),
                            trim($sgs_seals[$k]      ?? ''),
                            floatval($gross_weights[$k]),
                            floatval($net_weights[$k]),
                        ]);
                    }

                    $success = $bl_number;

                } catch (PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
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

.bl-check-icon.valid   { display: block; color: var(--emerald); }
.bl-check-icon.invalid { display: block; color: var(--rose); }

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

/* MT unit badge inside input */
.input-unit-wrap { position: relative; }
.input-unit-wrap .f-input { padding-right: 46px; }
.input-unit-badge {
    position: absolute;
    right: 0; top: 0; bottom: 0;
    display: flex;
    align-items: center;
    padding: 0 12px;
    background: var(--border);
    border-radius: 0 var(--r-sm) var(--r-sm) 0;
    border-left: 1.5px solid var(--border);
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--txt-2);
    letter-spacing: 0.05em;
    pointer-events: none;
}
</style>

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
    <i class="fas fa-circle-check alert-ico" style="color:var(--emerald);flex-shrink:0;"></i>
    <div>
        Bill of Lading
        <strong style="font-family:monospace;"><?php echo htmlspecialchars($success); ?></strong>
        created successfully!
        &nbsp;
        <a href="dashboard.php" style="color:var(--emerald);font-weight:700;">View Dashboard →</a>
    </div>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert-m alert-error">
    <i class="fas fa-circle-exclamation alert-ico" style="color:var(--rose);flex-shrink:0;"></i>
    <div><?php echo $error; ?></div>
</div>
<?php endif; ?>

<form method="POST" id="blForm" novalidate>

    <!-- BL Details -->
    <div class="form-card">
        <div class="form-card-head">
            <div class="form-card-ico"><i class="fas fa-file-lines"></i></div>
            <div>
                <h5>Bill of Lading Details</h5>
                <p>Enter the B/L number and item description</p>
            </div>
        </div>
        <div class="form-card-body">
            <div class="row g-3">

                <!-- BL Number -->
                <div class="col-12">
                    <label class="f-label">
                        B/L Number <span class="req">*</span>
                        <span style="font-size:0.7rem;font-weight:400;color:var(--txt-3);text-transform:none;letter-spacing:0;margin-left:6px;">
                            (Primary identifier — must be unique)
                        </span>
                    </label>
                    <div class="bl-number-field-wrap">
                        <div class="bl-number-prefix">
                            <i class="fas fa-hashtag"></i> B/L No.
                        </div>
                        <input
                            type="text"
                            name="bl_number"
                            id="blNumberInput"
                            class="bl-number-input"
                            placeholder="e.g. MSCUBL20240001 or BL-2024-001"
                            value="<?php echo htmlspecialchars($_POST['bl_number'] ?? ''); ?>"
                            required
                            autocomplete="off"
                            oninput="this.value=this.value.toUpperCase(); checkBLNumber(this.value)"
                        >
                        <i class="fas fa-circle-check bl-check-icon" id="blCheckIcon"></i>
                    </div>
                    <div class="bl-number-hint" id="blHint">
                        <i class="fas fa-circle-info"></i>
                        Enter your B/L reference number exactly as it appears on the document
                    </div>
                </div>

                <!-- Description -->
                <div class="col-12">
                    <label class="f-label">Item Description <span class="req">*</span></label>
                    <textarea
                        name="item_description"
                        class="f-input"
                        placeholder="e.g. Bagged White Sugar, 50kg per bag — Grade A, Origin: Brazil"
                        required
                        rows="3"
                    ><?php echo htmlspecialchars($_POST['item_description'] ?? ''); ?></textarea>
                </div>

            </div>
        </div>
    </div>

    <!-- Container Details -->
    <div class="form-card">
        <div class="form-card-head" style="flex-wrap:wrap;gap:12px;">
            <div class="form-card-ico"><i class="fas fa-boxes-stacked"></i></div>
            <div>
                <h5>Container Details</h5>
                <p>Add one or more container rows — weights in Metric Tonnes (MT)</p>
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
                    <span class="total-item-val c-amber" id="tGross">0.000 MT</span>
                </div>
                <div class="total-item">
                    <span class="total-item-lbl">Net Wt.</span>
                    <span class="total-item-val c-rose" id="tNet">0.000 MT</span>
                </div>
            </div>
        </div>

        <div class="form-card-body">
            <div class="rows-wrap" id="rowsWrap"></div>
            <button type="button" class="btn-add-row" onclick="addItemRow()">
                <i class="fas fa-circle-plus"></i>
                Add Another Container Row
            </button>
        </div>
    </div>

    <!-- Submit -->
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mt-1">
        <p style="color:var(--txt-3);font-size:0.8rem;">
            <i class="fas fa-circle-info me-1"></i>
            Fields marked <span style="color:var(--rose);font-weight:700;">*</span> are required.
            Weights must be entered in <strong>Metric Tonnes (MT)</strong>.
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
let blCheckTimer;

function checkBLNumber(val) {
    const icon = document.getElementById('blCheckIcon');
    const hint = document.getElementById('blHint');
    icon.className = 'fas fa-circle-check bl-check-icon';
    clearTimeout(blCheckTimer);

    if (val.length < 2) {
        hint.className = 'bl-number-hint';
        hint.innerHTML = '<i class="fas fa-circle-info"></i> Enter your B/L reference number exactly as it appears on the document';
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
                    hint.innerHTML = '<i class="fas fa-circle-xmark"></i> This B/L Number already exists — please use a different one';
                }
            })
            .catch(() => {
                hint.className = 'bl-number-hint';
                hint.innerHTML = '<i class="fas fa-circle-info"></i> Could not verify — number will be checked on save';
            });
    }, 600);
}

document.getElementById('blForm').addEventListener('submit', function(e) {
    const blNum = document.getElementById('blNumberInput').value.trim();
    if (!blNum) {
        e.preventDefault();
        document.getElementById('blNumberInput').focus();
        return;
    }
    if (!document.querySelector('.item-row')) {
        e.preventDefault();
        alert('Please add at least one container row.');
        return;
    }
    const btn = document.getElementById('submitBtn');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    btn.disabled  = true;
});
</script>

<?php require_once 'includes/footer.php'; ?>