<?php
require_once 'config/database.php';
$page_title = 'Add B/L';

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bl_number        = strtoupper(trim($_POST['bl_number']       ?? ''));
    $bl_type          = trim($_POST['bl_type']                    ?? '');
    $last_return_date = trim($_POST['last_return_date']           ?? '');
    $desc             = trim($_POST['item_description']           ?? '');
    $containers       = $_POST['container_number']                ?? [];
    $agent_seals      = $_POST['agent_seal_number']              ?? [];
    $bags             = $_POST['number_of_bags']                 ?? [];
    $sgs_seals        = $_POST['sgs_seal_number']                ?? [];
    $gross_weights    = $_POST['gross_weight']                   ?? [];
    $net_weights      = $_POST['net_weight']                     ?? [];

    // ── Validation ──
    if (empty($bl_number)) {
        $error = 'B/L Number is required.';
    } elseif (empty($bl_type) || !in_array($bl_type, ['TBL','Non-TBL'])) {
        $error = 'Please select a valid B/L Type (TBL or Non-TBL).';
    } elseif (empty($last_return_date)) {
        $error = 'Last container return date is required.';
    } elseif (strtotime($last_return_date) === false) {
        $error = 'Invalid return date format.';
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
            // Validate rows
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
                            (bl_number, bl_type, last_return_date, item_description, user_id, status)
                        VALUES (?, ?, ?, ?, ?, 'active')
                    ");
                    $stmt->execute([
                        $bl_number,
                        $bl_type,
                        $last_return_date,
                        $desc,
                        $_SESSION['user_id']
                    ]);
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
/* BL Number field */
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
    right: 13px; top: 50%;
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

/* MT unit badge */
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

/* ── BL Type Toggle ── */
.bl-type-wrap {
    display: flex;
    gap: 0;
    border: 1.5px solid var(--border);
    border-radius: var(--r-sm);
    overflow: hidden;
    background: var(--bg);
}

.bl-type-option {
    flex: 1;
    position: relative;
}

.bl-type-option input[type="radio"] {
    position: absolute;
    opacity: 0;
    width: 0; height: 0;
}

.bl-type-label {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 5px;
    padding: 14px 20px;
    cursor: pointer;
    transition: var(--t);
    text-align: center;
    border-right: 1.5px solid var(--border);
    background: var(--bg);
}

.bl-type-option:last-child .bl-type-label {
    border-right: none;
}

.bl-type-label .type-icon {
    font-size: 1.4rem;
    transition: var(--t);
}

.bl-type-label .type-name {
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--txt-2);
    transition: var(--t);
}

.bl-type-label .type-desc {
    font-size: 0.7rem;
    color: var(--txt-3);
    font-weight: 400;
    line-height: 1.4;
    transition: var(--t);
}

/* TBL selected */
.bl-type-option input[type="radio"][value="TBL"]:checked + .bl-type-label {
    background: linear-gradient(135deg, rgba(99,102,241,0.08), rgba(139,92,246,0.06));
    border-color: var(--indigo);
}

.bl-type-option input[type="radio"][value="TBL"]:checked + .bl-type-label .type-name {
    color: var(--indigo);
}

.bl-type-option input[type="radio"][value="TBL"]:checked + .bl-type-label .type-icon {
    transform: scale(1.1);
}

/* Non-TBL selected */
.bl-type-option input[type="radio"][value="Non-TBL"]:checked + .bl-type-label {
    background: linear-gradient(135deg, rgba(16,185,129,0.08), rgba(6,182,212,0.06));
}

.bl-type-option input[type="radio"][value="Non-TBL"]:checked + .bl-type-label .type-name {
    color: var(--emerald);
}

.bl-type-option input[type="radio"][value="Non-TBL"]:checked + .bl-type-label .type-icon {
    transform: scale(1.1);
}

/* Selected indicator */
.bl-type-option input[type="radio"]:checked + .bl-type-label::after {
    content: '✓';
    position: absolute;
    top: 8px; right: 10px;
    font-size: 0.7rem;
    font-weight: 800;
    width: 18px; height: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
}

.bl-type-option input[type="radio"][value="TBL"]:checked + .bl-type-label::after {
    background: var(--indigo);
    color: white;
}

.bl-type-option input[type="radio"][value="Non-TBL"]:checked + .bl-type-label::after {
    background: var(--emerald);
    color: white;
}

/* ── Date field ── */
.date-input-wrap {
    position: relative;
}

.date-input-wrap .f-input[type="date"] {
    padding-right: 44px;
    cursor: pointer;
}

.date-input-icon {
    position: absolute;
    right: 13px; top: 50%;
    transform: translateY(-50%);
    color: var(--indigo);
    font-size: 0.9rem;
    pointer-events: none;
}

/* Return date urgency */
.return-date-info {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 6px;
    font-size: 0.73rem;
    font-weight: 600;
    padding: 6px 10px;
    border-radius: 8px;
    display: none;
}

.return-date-info.urgent {
    background: rgba(244,63,94,0.08);
    color: var(--rose);
    border: 1px solid rgba(244,63,94,0.2);
    display: flex;
}

.return-date-info.warning {
    background: rgba(245,158,11,0.08);
    color: var(--amber);
    border: 1px solid rgba(245,158,11,0.2);
    display: flex;
}

.return-date-info.ok {
    background: rgba(16,185,129,0.08);
    color: var(--emerald);
    border: 1px solid rgba(16,185,129,0.2);
    display: flex;
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

    <!-- ══ BL DETAILS CARD ══ -->
    <div class="form-card">
        <div class="form-card-head">
            <div class="form-card-ico"><i class="fas fa-file-lines"></i></div>
            <div>
                <h5>Bill of Lading Details</h5>
                <p>Enter B/L number, type, return date and item description</p>
            </div>
        </div>
        <div class="form-card-body">
            <div class="row g-4">

                <!-- ── BL Number ── -->
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
                            placeholder="e.g. AMC123456B or BL-2024-001"
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

                <!-- ── BL Type Selection ── -->
                <div class="col-md-6">
                    <label class="f-label">
                        B/L Type <span class="req">*</span>
                        <span style="font-size:0.7rem;font-weight:400;color:var(--txt-3);text-transform:none;letter-spacing:0;margin-left:6px;">
                            (Determines empty container return process)
                        </span>
                    </label>

                    <div class="bl-type-wrap">

                        <!-- TBL -->
                        <div class="bl-type-option">
                            <input
                                type="radio"
                                name="bl_type"
                                id="typeTBL"
                                value="TBL"
                                <?php echo (($_POST['bl_type'] ?? '') === 'TBL') ? 'checked' : ''; ?>
                                required
                            >
                            <label class="bl-type-label" for="typeTBL">
                                <span class="type-icon">🚢</span>
                                <span class="type-name">TBL</span>
                                <span class="type-desc">Through Bill of Lading — Container return via shipping line</span>
                            </label>
                        </div>

                        <!-- Non-TBL -->
                        <div class="bl-type-option">
                            <input
                                type="radio"
                                name="bl_type"
                                id="typeNonTBL"
                                value="Non-TBL"
                                <?php echo (($_POST['bl_type'] ?? '') === 'Non-TBL') ? 'checked' : ''; ?>
                            >
                            <label class="bl-type-label" for="typeNonTBL">
                                <span class="type-icon">📋</span>
                                <span class="type-name">Non-TBL</span>
                                <span class="type-desc">Local Bill of Lading — Container return via local depot</span>
                            </label>
                        </div>

                    </div>

                    <!-- Type info hint -->
                    <div id="typeHint" style="margin-top:8px;font-size:0.73rem;color:var(--txt-3);display:flex;align-items:center;gap:6px;">
                        <i class="fas fa-circle-info"></i>
                        Select the B/L type — this affects the empty container return workflow
                    </div>
                </div>

                <!-- ── Last Return Date ── -->
                <div class="col-md-6">
                    <label class="f-label">
                        Last Container Return Date <span class="req">*</span>
                        <span style="font-size:0.7rem;font-weight:400;color:var(--txt-3);text-transform:none;letter-spacing:0;margin-left:6px;">
                            (Deadline for returning empty containers)
                        </span>
                    </label>

                    <div class="date-input-wrap">
                        <input
                            type="date"
                            name="last_return_date"
                            id="lastReturnDate"
                            class="f-input"
                            value="<?php echo htmlspecialchars($_POST['last_return_date'] ?? ''); ?>"
                            min="<?php echo date('Y-m-d'); ?>"
                            required
                            onchange="checkReturnDate(this.value)"
                        >
                        <i class="fas fa-calendar-days date-input-icon"></i>
                    </div>

                    <!-- Urgency indicator -->
                    <div class="return-date-info" id="returnDateInfo">
                        <i class="fas fa-circle-exclamation"></i>
                        <span id="returnDateMsg"></span>
                    </div>
                </div>

                <!-- ── Item Description ── -->
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

    <!-- ══ CONTAINER DETAILS ══ -->
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

    <!-- ══ SUBMIT ══ -->
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mt-1">
        <p style="color:var(--txt-3);font-size:0.8rem;">
            <i class="fas fa-circle-info me-1"></i>
            Fields marked <span style="color:var(--rose);font-weight:700;">*</span> are required.
            Weights in <strong>Metric Tonnes (MT)</strong>.
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
/* ── BL Number availability check ── */
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
                hint.innerHTML = '<i class="fas fa-circle-info"></i> Could not verify — will be checked on save';
            });
    }, 600);
}

/* ── Return Date urgency check ── */
function checkReturnDate(val) {
    const info = document.getElementById('returnDateInfo');
    const msg  = document.getElementById('returnDateMsg');
    if (!val) { info.className = 'return-date-info'; return; }

    const today    = new Date();
    today.setHours(0,0,0,0);
    const selected = new Date(val);
    const diffDays = Math.round((selected - today) / (1000*60*60*24));

    if (diffDays < 0) {
        info.className = 'return-date-info urgent';
        msg.textContent = 'Date is in the past — please select a future date';
    } else if (diffDays === 0) {
        info.className = 'return-date-info urgent';
        msg.textContent = 'Return date is TODAY — immediate action required';
    } else if (diffDays <= 7) {
        info.className = 'return-date-info urgent';
        msg.textContent = `⚠ Only ${diffDays} day${diffDays>1?'s':''} until return deadline`;
    } else if (diffDays <= 21) {
        info.className = 'return-date-info warning';
        msg.textContent = `${diffDays} days until return deadline — plan accordingly`;
    } else {
        info.className = 'return-date-info ok';
        msg.textContent = `${diffDays} days until return deadline`;
    }
}

/* ── BL Type hint update ── */
document.querySelectorAll('input[name="bl_type"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const hint = document.getElementById('typeHint');
        if (this.value === 'TBL') {
            hint.innerHTML = '<i class="fas fa-ship" style="color:var(--indigo);"></i> <span style="color:var(--indigo);font-weight:600;">TBL selected</span> — Empty containers returned to shipping line';
        } else {
            hint.innerHTML = '<i class="fas fa-warehouse" style="color:var(--emerald);"></i> <span style="color:var(--emerald);font-weight:600;">Non-TBL selected</span> — Empty containers returned to local depot';
        }
    });
});

/* ── Form Submit ── */
document.getElementById('blForm').addEventListener('submit', function(e) {
    // Check BL number
    if (!document.getElementById('blNumberInput').value.trim()) {
        e.preventDefault();
        document.getElementById('blNumberInput').focus();
        return;
    }

    // Check BL type
    const typeSelected = document.querySelector('input[name="bl_type"]:checked');
    if (!typeSelected) {
        e.preventDefault();
        alert('Please select a B/L Type (TBL or Non-TBL).');
        return;
    }

    // Check return date
    if (!document.getElementById('lastReturnDate').value) {
        e.preventDefault();
        document.getElementById('lastReturnDate').focus();
        alert('Please select the last container return date.');
        return;
    }

    // Check container rows
    if (!document.querySelector('.item-row')) {
        e.preventDefault();
        alert('Please add at least one container row.');
        return;
    }

    const btn = document.getElementById('submitBtn');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    btn.disabled  = true;
});

/* ── Init date check if value pre-filled ── */
const existingDate = document.getElementById('lastReturnDate').value;
if (existingDate) checkReturnDate(existingDate);
</script>

<?php require_once 'includes/footer.php'; ?>