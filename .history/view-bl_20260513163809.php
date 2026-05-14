<?php
require_once 'config/database.php';
$page_title = 'View B/L';

$id = intval($_GET['id'] ?? 0);
if (!$id) { header('Location: manage-bl.php'); exit(); }

$stmt = $pdo->prepare("
    SELECT b.*, u.username, u.full_name
    FROM bills_of_lading b
    LEFT JOIN users u ON b.user_id = u.id
    WHERE b.id = ?
");
$stmt->execute([$id]);
$bl = $stmt->fetch();
if (!$bl) { header('Location: manage-bl.php'); exit(); }

// Fetch items with dispatch info
$items = $pdo->prepare("
    SELECT i.*,
           d.id              AS dispatch_id,
           d.transporter_name,
           d.truck_number,
           d.destination,
           d.status          AS dispatch_status_detail,
           d.dispatched_at   AS dispatched_at_detail,
           d.received_at,
           d.clearing_agent_dnote,
           d.transporter_dnote
    FROM bl_items i
    LEFT JOIN dispatches d ON d.bl_item_id = i.id
    WHERE i.bl_id = ?
    ORDER BY i.id ASC
");
$items->execute([$id]);
$items = $items->fetchAll();

$totalBags  = array_sum(array_column($items, 'number_of_bags'));
$totalGross = array_sum(array_column($items, 'gross_weight'));
$totalNet   = array_sum(array_column($items, 'net_weight'));
$totalTare  = $totalGross - $totalNet;

// Dispatch counts
$pendingCount  = count(array_filter($items, fn($i) => ($i['dispatch_status'] ?? 'pending') === 'pending'));
$transitCount  = count(array_filter($items, fn($i) => ($i['dispatch_status'] ?? 'pending') === 'transit'));
$receivedCount = count(array_filter($items, fn($i) => ($i['dispatch_status'] ?? 'pending') === 'received'));
$rejectedCount = count(array_filter($items, fn($i) => ($i['dispatch_status'] ?? 'pending') === 'rejected'));

function fmtMT($val, $dec = 3) {
    return number_format((float)$val, $dec) . ' MT';
}

$page_title = 'View — ' . $bl['bl_number'];
require_once 'includes/header.php';
?>
<!-- Flash Messages — add after page header div -->
<?php
$flashMsg  = '';
$flashType = '';

if (isset($_GET['dispatched'])) {
    $flashMsg  = '<i class="fas fa-truck"></i> Container dispatched successfully and is now <strong>In Transit</strong>.';
    $flashType = 'success';
} elseif (isset($_GET['received'])) {
    $flashMsg  = '<i class="fas fa-circle-check"></i> Container marked as <strong>Received</strong> successfully.';
    $flashType = 'success';
} elseif (isset($_GET['rejected'])) {
    $flashMsg  = '<i class="fas fa-circle-xmark"></i> Container marked as <strong>Rejected</strong>.';
    $flashType = 'error';
} elseif (isset($_GET['error'])) {
    $msgs = [
        'missing_fields'    => 'Please fill all required dispatch fields.',
        'already_dispatched'=> 'This container has already been dispatched.',
        'invalid_status'    => 'Invalid status update.',
        'missing_reason'    => 'Please provide a rejection reason.',
        'db_error'          => 'A database error occurred. Please try again.',
    ];
    $flashMsg  = '<i class="fas fa-circle-exclamation"></i> ' . ($msgs[$_GET['error']] ?? 'An error occurred.');
    $flashType = 'error';
}
?>

<?php if ($flashMsg): ?>
<div class="alert-m alert-<?php echo $flashType === 'success' ? 'success' : 'error'; ?>"
     style="margin-bottom:1.25rem;"
     id="flashAlert">
    <div><?php echo $flashMsg; ?></div>
</div>
<script>
    setTimeout(() => {
        const el = document.getElementById('flashAlert');
        if (el) { el.style.transition='opacity 0.5s'; el.style.opacity='0'; setTimeout(()=>el.remove(),500); }
    }, 4000);
</script>
<?php endif; ?>

<style>
/* ── View Hero ── */
.view-hero {
    background: linear-gradient(135deg, var(--indigo) 0%, var(--violet) 100%);
    border-radius: var(--r-lg);
    padding: 2rem 2.25rem;
    margin-bottom: 1.5rem;
    color: white;
    position: relative;
    overflow: hidden;
}

.view-hero::before {
    content: '';
    position: absolute;
    right: -60px; top: -60px;
    width: 220px; height: 220px;
    background: rgba(255,255,255,0.07);
    border-radius: 50%;
}

.view-hero::after {
    content: '';
    position: absolute;
    right: 60px; bottom: -80px;
    width: 160px; height: 160px;
    background: rgba(255,255,255,0.05);
    border-radius: 50%;
}

.view-hero-bl   { font-size:0.78rem;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;opacity:0.7;margin-bottom:8px; }
.view-hero-num  { font-size:2rem;font-weight:800;letter-spacing:-0.5px;margin-bottom:0.5rem;font-family:'Courier New',monospace; }
.view-hero-desc { font-size:0.9rem;opacity:0.8;max-width:500px;margin-bottom:1.25rem; }
.view-hero-meta { display:flex;align-items:center;gap:16px;flex-wrap:wrap;font-size:0.8rem;opacity:0.75; }
.view-hero-meta span { display:flex;align-items:center;gap:6px; }

/* ── Summary ── */
.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px,1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.summary-tile {
    background: white;
    border: 1px solid var(--border);
    border-radius: var(--r-md);
    padding: 1.1rem;
    text-align: center;
    transition: var(--t);
}

.summary-tile:hover { box-shadow: var(--shadow-md); transform: translateY(-2px); }
.summary-tile-ico { font-size:1.3rem; margin-bottom:0.5rem; }
.summary-tile-val { font-size:1.25rem; font-weight:800; color:var(--txt-1); line-height:1; margin-bottom:3px; }
.summary-tile-lbl { font-size:0.7rem; font-weight:600; color:var(--txt-2); text-transform:uppercase; letter-spacing:0.05em; }

/* ── Dispatch Progress Bar ── */
.dispatch-progress-card {
    background: white;
    border: 1px solid var(--border);
    border-radius: var(--r-lg);
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: var(--shadow-sm);
}

.dispatch-progress-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--txt-1);
    margin-bottom: 1.1rem;
    display: flex;
    align-items: center;
    gap: 8px;
}

.dispatch-progress-title i { color: var(--indigo); }

.progress-track {
    height: 10px;
    background: var(--border);
    border-radius: 99px;
    overflow: hidden;
    margin-bottom: 1rem;
    display: flex;
}

.progress-seg {
    height: 100%;
    transition: width 0.6s ease;
}

.seg-received { background: var(--emerald); }
.seg-transit  { background: var(--amber); }
.seg-rejected { background: var(--rose); }
.seg-pending  { background: var(--border); }

.dispatch-counts {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.dispatch-count-item {
    display: flex;
    align-items: center;
    gap: 7px;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--txt-2);
}

.count-dot {
    width: 10px; height: 10px;
    border-radius: 50%;
    flex-shrink: 0;
}

/* ── Container Cards ── */
.container-card {
    background: white;
    border: 1px solid var(--border);
    border-radius: var(--r-lg);
    overflow: hidden;
    transition: var(--t);
    margin-bottom: 1rem;
}

.container-card:hover {
    box-shadow: var(--shadow-md);
    border-color: rgba(99,102,241,0.25);
}

.container-card-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.4rem;
    border-bottom: 1px solid var(--border-l);
    flex-wrap: wrap;
    gap: 10px;
}

.container-card-left {
    display: flex;
    align-items: center;
    gap: 12px;
}

.container-num-badge {
    width: 36px; height: 36px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--indigo), var(--violet));
    color: white;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.82rem;
    font-weight: 800;
    flex-shrink: 0;
}

.container-num-text {
    font-family: 'Courier New', monospace;
    font-weight: 700;
    font-size: 1rem;
    color: var(--indigo);
    letter-spacing: 0.5px;
}

.container-num-sub {
    font-size: 0.75rem;
    color: var(--txt-2);
    margin-top: 1px;
}

.container-card-body {
    padding: 1rem 1.4rem;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(130px,1fr));
    gap: 1rem;
}

.detail-item { }
.detail-item-lbl {
    font-size: 0.67rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: var(--txt-3);
    margin-bottom: 3px;
}

.detail-item-val {
    font-size: 0.88rem;
    font-weight: 600;
    color: var(--txt-1);
}

.detail-item-val.mono  { font-family: 'Courier New', monospace; color: var(--indigo); }
.detail-item-val.muted { color: var(--txt-3); font-style: italic; font-weight:400; }

/* ── Dispatch Status Badge ── */
.ds-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 700;
    white-space: nowrap;
}

.ds-pending  { background: rgba(148,163,184,0.12); color: #64748b; border: 1px solid rgba(148,163,184,0.3); }
.ds-transit  { background: rgba(245,158,11,0.1);   color: #d97706; border: 1px solid rgba(245,158,11,0.3); }
.ds-received { background: rgba(16,185,129,0.1);   color: #059669; border: 1px solid rgba(16,185,129,0.3); }
.ds-rejected { background: rgba(244,63,94,0.1);    color: #e11d48; border: 1px solid rgba(244,63,94,0.3); }

/* Pulse dot */
.ds-transit .pulse-dot {
    width: 7px; height: 7px;
    background: var(--amber);
    border-radius: 50%;
    animation: pulse-ring 1.5s infinite;
}

@keyframes pulse-ring {
    0%   { box-shadow: 0 0 0 0 rgba(245,158,11,0.5); }
    70%  { box-shadow: 0 0 0 6px rgba(245,158,11,0); }
    100% { box-shadow: 0 0 0 0 rgba(245,158,11,0); }
}

/* ── Dispatch info strip ── */
.dispatch-info-strip {
    padding: 0.75rem 1.4rem;
    border-top: 1px solid var(--border-l);
    background: #f8fafc;
    display: flex;
    align-items: center;
    gap: 1.5rem;
    flex-wrap: wrap;
    font-size: 0.78rem;
    color: var(--txt-2);
}

.dispatch-info-strip span {
    display: flex;
    align-items: center;
    gap: 5px;
    font-weight: 500;
}

.dispatch-info-strip strong { color: var(--txt-1); }

/* ── Modal ── */
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.6);
    backdrop-filter: blur(8px);
    z-index: 2000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1.5rem;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.25s ease;
}

.modal-overlay.show {
    opacity: 1;
    pointer-events: all;
}

.modal-box {
    background: white;
    border-radius: 24px;
    max-width: 560px;
    width: 100%;
    box-shadow: 0 25px 80px rgba(0,0,0,0.25);
    transform: scale(0.94) translateY(10px);
    transition: transform 0.3s cubic-bezier(0.22,1,0.36,1);
    overflow: hidden;
}

.modal-overlay.show .modal-box {
    transform: scale(1) translateY(0);
}

.modal-head {
    padding: 1.5rem 1.75rem;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 14px;
    background: linear-gradient(to right, #fafbff, white);
}

.modal-head-ico {
    width: 44px; height: 44px;
    border-radius: var(--r-sm);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem;
    color: white;
    flex-shrink: 0;
}

.modal-head h5 {
    font-size: 1rem;
    font-weight: 700;
    color: var(--txt-1);
    margin: 0;
}

.modal-head p {
    font-size: 0.78rem;
    color: var(--txt-2);
    margin: 0;
    margin-top: 2px;
}

.modal-body { padding: 1.5rem 1.75rem; }
.modal-foot {
    padding: 1rem 1.75rem;
    border-top: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 10px;
    background: #fafbff;
}

/* ── Receive / Reject modal ── */
.outcome-btn {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 1.25rem;
    border-radius: var(--r-md);
    border: 2px solid var(--border);
    background: white;
    cursor: pointer;
    transition: var(--t);
    font-family: 'Inter', sans-serif;
}

.outcome-btn i { font-size: 1.5rem; }
.outcome-btn span { font-size: 0.88rem; font-weight: 700; }
.outcome-btn small { font-size: 0.72rem; color: var(--txt-2); }

.outcome-btn.received-btn:hover, .outcome-btn.received-btn.selected {
    border-color: var(--emerald);
    background: rgba(16,185,129,0.06);
    color: var(--emerald);
}

.outcome-btn.rejected-btn:hover, .outcome-btn.rejected-btn.selected {
    border-color: var(--rose);
    background: rgba(244,63,94,0.06);
    color: var(--rose);
}

.rejection-wrap {
    display: none;
    margin-top: 1rem;
    animation: slideDown 0.25s ease;
}

@keyframes slideDown {
    from { opacity:0; transform:translateY(-6px); }
    to   { opacity:1; transform:translateY(0); }
}

/* Status pill for hero */
.status-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.78rem;
    font-weight: 700;
}

.status-pill.active    { background: rgba(16,185,129,0.2); color: #6ee7b7; }
.status-pill.draft     { background: rgba(245,158,11,0.2); color: #fcd34d; }
.status-pill.completed { background: rgba(255,255,255,0.2); color: white; }

@media print {
    .sidebar, .top-nav, .no-print { display: none !important; }
    .main-wrap { margin-left: 0 !important; }
    .page-content { padding: 0 !important; }
}
</style>

<!-- Page Header -->
<div class="page-header d-flex align-items-start justify-content-between flex-wrap gap-3 no-print">
    <div>
        <h1 class="page-title">Bill of Lading</h1>
        <p class="page-sub">View and manage container dispatches</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <button onclick="window.print()" class="btn-m btn-ghost">
            <i class="fas fa-print"></i> Print
        </button>
        <a href="edit-bl.php?id=<?php echo $bl['id']; ?>" class="btn-m btn-ghost">
            <i class="fas fa-pen"></i> Edit
        </a>
        <a href="manage-bl.php" class="btn-m btn-ghost">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>
</div>

<!-- Hero -->
<div class="view-hero">
    <div class="view-hero-bl">Bill of Lading</div>
    <div class="view-hero-num"><?php echo htmlspecialchars($bl['bl_number']); ?></div>
    <div class="view-hero-desc"><?php echo htmlspecialchars($bl['item_description']); ?></div>
    <div class="view-hero-meta">
        <span><i class="fas fa-calendar"></i> <?php echo date('F d, Y', strtotime($bl['created_at'])); ?></span>
        <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($bl['full_name'] ?? $bl['username']); ?></span>
        <span><i class="fas fa-boxes-stacked"></i> <?php echo count($items); ?> Containers</span>
    </div>
    <div style="position:absolute;right:2rem;top:50%;transform:translateY(-50%);z-index:1;" class="no-print">
        <span class="status-pill <?php echo $bl['status']; ?>">
            <i class="fas fa-circle" style="font-size:0.4rem;"></i>
            <?php echo ucfirst($bl['status']); ?>
        </span>
    </div>
</div>

<!-- Summary Tiles -->
<div class="summary-grid">
    <div class="summary-tile">
        <div class="summary-tile-ico">🚢</div>
        <div class="summary-tile-val"><?php echo count($items); ?></div>
        <div class="summary-tile-lbl">Containers</div>
    </div>
    <div class="summary-tile">
        <div class="summary-tile-ico">📦</div>
        <div class="summary-tile-val"><?php echo number_format($totalBags); ?></div>
        <div class="summary-tile-lbl">Total Bags</div>
    </div>
    <div class="summary-tile">
        <div class="summary-tile-ico">⚖️</div>
        <div class="summary-tile-val"><?php echo number_format($totalGross, 3); ?></div>
        <div class="summary-tile-lbl">Gross Weight (MT)</div>
    </div>
    <div class="summary-tile">
        <div class="summary-tile-ico">🏋️</div>
        <div class="summary-tile-val"><?php echo number_format($totalNet, 3); ?></div>
        <div class="summary-tile-lbl">Net Weight (MT)</div>
    </div>
    <div class="summary-tile">
        <div class="summary-tile-ico">⏳</div>
        <div class="summary-tile-val" style="color:var(--txt-3);"><?php echo $pendingCount; ?></div>
        <div class="summary-tile-lbl">Pending</div>
    </div>
    <div class="summary-tile">
        <div class="summary-tile-ico">🚛</div>
        <div class="summary-tile-val" style="color:var(--amber);"><?php echo $transitCount; ?></div>
        <div class="summary-tile-lbl">In Transit</div>
    </div>
    <div class="summary-tile">
        <div class="summary-tile-ico">✅</div>
        <div class="summary-tile-val" style="color:var(--emerald);"><?php echo $receivedCount; ?></div>
        <div class="summary-tile-lbl">Received</div>
    </div>
    <div class="summary-tile">
        <div class="summary-tile-ico">❌</div>
        <div class="summary-tile-val" style="color:var(--rose);"><?php echo $rejectedCount; ?></div>
        <div class="summary-tile-lbl">Rejected</div>
    </div>
</div>

<!-- Dispatch Progress -->
<?php
$total = count($items);
$recPct = $total > 0 ? round(($receivedCount / $total) * 100) : 0;
$traPct = $total > 0 ? round(($transitCount  / $total) * 100) : 0;
$rejPct = $total > 0 ? round(($rejectedCount / $total) * 100) : 0;
$penPct = 100 - $recPct - $traPct - $rejPct;
?>
<div class="dispatch-progress-card">
    <div class="dispatch-progress-title">
        <i class="fas fa-route"></i>
        Dispatch Progress
        <span style="margin-left:auto;font-size:0.78rem;font-weight:500;color:var(--txt-2);">
            <?php echo $receivedCount; ?> of <?php echo $total; ?> containers received
        </span>
    </div>
    <div class="progress-track">
        <div class="progress-seg seg-received" style="width:<?php echo $recPct; ?>%;"></div>
        <div class="progress-seg seg-transit"  style="width:<?php echo $traPct; ?>%;"></div>
        <div class="progress-seg seg-rejected" style="width:<?php echo $rejPct; ?>%;"></div>
        <div class="progress-seg seg-pending"  style="width:<?php echo $penPct; ?>%;"></div>
    </div>
    <div class="dispatch-counts">
        <div class="dispatch-count-item">
            <div class="count-dot" style="background:var(--emerald);"></div>
            Received (<?php echo $receivedCount; ?>)
        </div>
        <div class="dispatch-count-item">
            <div class="count-dot" style="background:var(--amber);"></div>
            In Transit (<?php echo $transitCount; ?>)
        </div>
        <div class="dispatch-count-item">
            <div class="count-dot" style="background:var(--rose);"></div>
            Rejected (<?php echo $rejectedCount; ?>)
        </div>
        <div class="dispatch-count-item">
            <div class="count-dot" style="background:var(--border);border:1px solid #cbd5e1;"></div>
            Pending (<?php echo $pendingCount; ?>)
        </div>
    </div>
</div>

<!-- Container Cards -->
<div style="margin-bottom:0.5rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
    <h6 style="font-weight:700;color:var(--txt-1);font-size:0.95rem;margin:0;">
        <i class="fas fa-boxes-stacked" style="color:var(--indigo);margin-right:7px;"></i>
        Container Details & Dispatch
    </h6>
    <!-- Filter tabs -->
    <div style="display:flex;gap:4px;background:white;border:1px solid var(--border);border-radius:var(--r-sm);padding:4px;" class="no-print">
        <button class="filter-tab active" onclick="filterCards('all', this)" style="padding:5px 12px;border:none;border-radius:6px;font-size:0.78rem;font-weight:600;cursor:pointer;transition:all 0.2s;background:linear-gradient(135deg,var(--indigo),var(--violet));color:white;font-family:'Inter',sans-serif;">All</button>
        <button class="filter-tab" onclick="filterCards('pending', this)"  style="padding:5px 12px;border:none;border-radius:6px;font-size:0.78rem;font-weight:600;cursor:pointer;transition:all 0.2s;background:none;color:var(--txt-2);font-family:'Inter',sans-serif;">Pending</button>
        <button class="filter-tab" onclick="filterCards('transit', this)"  style="padding:5px 12px;border:none;border-radius:6px;font-size:0.78rem;font-weight:600;cursor:pointer;transition:all 0.2s;background:none;color:var(--txt-2);font-family:'Inter',sans-serif;">Transit</button>
        <button class="filter-tab" onclick="filterCards('received', this)" style="padding:5px 12px;border:none;border-radius:6px;font-size:0.78rem;font-weight:600;cursor:pointer;transition:all 0.2s;background:none;color:var(--txt-2);font-family:'Inter',sans-serif;">Received</button>
        <button class="filter-tab" onclick="filterCards('rejected', this)" style="padding:5px 12px;border:none;border-radius:6px;font-size:0.78rem;font-weight:600;cursor:pointer;transition:all 0.2s;background:none;color:var(--txt-2);font-family:'Inter',sans-serif;">Rejected</button>
    </div>
</div>

<div id="containerList">
<?php foreach ($items as $i => $item):
    $ds = $item['dispatch_status'] ?? 'pending';
?>
<div class="container-card" data-status="<?php echo $ds; ?>">

    <!-- Card Header -->
    <div class="container-card-head">
        <div class="container-card-left">
            <div class="container-num-badge"><?php echo $i + 1; ?></div>
            <div>
                <div class="container-num-text"><?php echo htmlspecialchars($item['container_number']); ?></div>
                <div class="container-num-sub">
                    <?php echo number_format($item['number_of_bags']); ?> bags
                    &nbsp;·&nbsp; <?php echo fmtMT($item['gross_weight']); ?> GW
                    &nbsp;·&nbsp; <?php echo fmtMT($item['net_weight']); ?> NW
                </div>
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">

            <!-- Status Badge -->
            <?php if ($ds === 'pending'): ?>
                <span class="ds-badge ds-pending"><i class="fas fa-clock"></i> Pending Dispatch</span>
            <?php elseif ($ds === 'transit'): ?>
                <span class="ds-badge ds-transit">
                    <span class="pulse-dot"></span> In Transit
                </span>
            <?php elseif ($ds === 'received'): ?>
                <span class="ds-badge ds-received"><i class="fas fa-circle-check"></i> Received</span>
            <?php elseif ($ds === 'rejected'): ?>
                <span class="ds-badge ds-rejected"><i class="fas fa-circle-xmark"></i> Rejected</span>
            <?php endif; ?>

            <!-- Action Buttons -->
            <?php if ($ds === 'pending'): ?>
                <button
                    type="button"
                    class="btn-m btn-indigo btn-sm no-print"
                    onclick="openDispatch(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['container_number']); ?>')"
                >
                    <i class="fas fa-truck"></i> Dispatch
                </button>

            <?php elseif ($ds === 'transit'): ?>
                <button
                    type="button"
                    class="btn-m btn-ghost btn-sm no-print"
                    onclick="openOutcome(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['container_number']); ?>', <?php echo $item['dispatch_id']; ?>)"
                >
                    <i class="fas fa-flag-checkered"></i> Update Status
                </button>
            <?php endif; ?>

        </div>
    </div>

    <!-- Card Body — Weight & Seal Info -->
    <div class="container-card-body">
        <div class="detail-item">
            <div class="detail-item-lbl">Container No.</div>
            <div class="detail-item-val mono"><?php echo htmlspecialchars($item['container_number']); ?></div>
        </div>
        <div class="detail-item">
            <div class="detail-item-lbl">Agent Seal</div>
            <div class="detail-item-val <?php echo $item['agent_seal_number'] ? '' : 'muted'; ?>">
                <?php echo $item['agent_seal_number'] ? htmlspecialchars($item['agent_seal_number']) : '—'; ?>
            </div>
        </div>
        <div class="detail-item">
            <div class="detail-item-lbl">SGS Seal</div>
            <div class="detail-item-val <?php echo $item['sgs_seal_number'] ? '' : 'muted'; ?>">
                <?php echo $item['sgs_seal_number'] ? htmlspecialchars($item['sgs_seal_number']) : '—'; ?>
            </div>
        </div>
        <div class="detail-item">
            <div class="detail-item-lbl">No. of Bags</div>
            <div class="detail-item-val" style="color:var(--indigo);"><?php echo number_format($item['number_of_bags']); ?></div>
        </div>
        <div class="detail-item">
            <div class="detail-item-lbl">Gross Weight</div>
            <div class="detail-item-val" style="color:var(--amber);"><?php echo fmtMT($item['gross_weight']); ?></div>
        </div>
        <div class="detail-item">
            <div class="detail-item-lbl">Net Weight</div>
            <div class="detail-item-val" style="color:var(--emerald);"><?php echo fmtMT($item['net_weight']); ?></div>
        </div>
        <div class="detail-item">
            <div class="detail-item-lbl">Tare Weight</div>
            <div class="detail-item-val" style="color:var(--txt-2);"><?php echo fmtMT((float)$item['gross_weight'] - (float)$item['net_weight']); ?></div>
        </div>
    </div>

    <!-- Dispatch Info Strip (if dispatched) -->
    <?php if ($ds !== 'pending' && $item['transporter_name']): ?>
    <div class="dispatch-info-strip">
        <span><i class="fas fa-truck"></i> <strong><?php echo htmlspecialchars($item['transporter_name']); ?></strong></span>
        <span><i class="fas fa-hashtag"></i> <strong><?php echo htmlspecialchars($item['truck_number']); ?></strong></span>
        <span><i class="fas fa-location-dot"></i> <strong><?php echo htmlspecialchars($item['destination']); ?></strong></span>
        <?php if ($item['clearing_agent_dnote']): ?>
        <span><i class="fas fa-file-alt"></i> CA: <strong><?php echo htmlspecialchars($item['clearing_agent_dnote']); ?></strong></span>
        <?php endif; ?>
        <?php if ($item['transporter_dnote']): ?>
        <span><i class="fas fa-file-lines"></i> TR: <strong><?php echo htmlspecialchars($item['transporter_dnote']); ?></strong></span>
        <?php endif; ?>
        <span style="margin-left:auto;color:var(--txt-3);font-size:0.73rem;">
            Dispatched <?php echo date('M d, Y H:i', strtotime($item['dispatched_at_detail'])); ?>
        </span>
        <?php if ($ds === 'received' && $item['received_at']): ?>
        <span style="color:var(--emerald);">
            <i class="fas fa-circle-check"></i>
            Received <?php echo date('M d, Y H:i', strtotime($item['received_at'])); ?>
        </span>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>
<?php endforeach; ?>
</div>

<!-- ══════════════════════════════════════
     DISPATCH MODAL
══════════════════════════════════════ -->
<div class="modal-overlay" id="dispatchModal">
    <div class="modal-box">
        <div class="modal-head">
            <div class="modal-head-ico" style="background:linear-gradient(135deg,var(--indigo),var(--violet));">
                <i class="fas fa-truck"></i>
            </div>
            <div>
                <h5>Dispatch Container</h5>
                <p id="dispatchContainerName">Container details</p>
            </div>
        </div>
        <div class="modal-body">
            <form id="dispatchForm" method="POST" action="dispatch-action.php" novalidate>
                <input type="hidden" name="action"      value="dispatch">
                <input type="hidden" name="bl_id"       value="<?php echo $bl['id']; ?>">
                <input type="hidden" name="bl_item_id"  id="dispatchItemId">
                <input type="hidden" name="redirect_id" value="<?php echo $bl['id']; ?>">

                <div class="row g-3">

                    <div class="col-md-6">
                        <label class="f-label">Transporter Name <span class="req">*</span></label>
                        <input type="text" name="transporter_name" id="transporterName"
                               class="f-input" placeholder="e.g. XYZ Transport Ltd" required>
                    </div>

                    <div class="col-md-6">
                        <label class="f-label">Truck Number <span class="req">*</span></label>
                        <input type="text" name="truck_number" id="truckNumber"
                               class="f-input" placeholder="e.g. KDG 123A"
                               required oninput="this.value=this.value.toUpperCase()">
                    </div>

                    <div class="col-md-6">
                        <label class="f-label">Clearing Agent D/Note</label>
                        <input type="text" name="clearing_agent_dnote"
                               class="f-input" placeholder="e.g. CA-2024-0012">
                    </div>

                    <div class="col-md-6">
                        <label class="f-label">Transporter D/Note</label>
                        <input type="text" name="transporter_dnote"
                               class="f-input" placeholder="e.g. TR-2024-0056">
                    </div>

                    <div class="col-12">
                        <label class="f-label">Destination <span class="req">*</span></label>
                        <input type="text" name="destination"
                               class="f-input" placeholder="e.g. Nairobi Inland Container Depot" required>
                    </div>

                    <div class="col-12">
                        <label class="f-label">Notes (Optional)</label>
                        <textarea name="notes" class="f-input" rows="2"
                                  placeholder="Any additional dispatch notes..."></textarea>
                    </div>

                </div>
            </form>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn-m btn-ghost" onclick="closeDispatchModal()">
                <i class="fas fa-xmark"></i> Cancel
            </button>
            <button type="button" class="btn-m btn-indigo" onclick="submitDispatch()">
                <i class="fas fa-truck"></i> Dispatch Container
            </button>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════
     OUTCOME MODAL (Received / Rejected)
══════════════════════════════════════ -->
<div class="modal-overlay" id="outcomeModal">
    <div class="modal-box">
        <div class="modal-head">
            <div class="modal-head-ico" style="background:linear-gradient(135deg,var(--emerald),var(--cyan));">
                <i class="fas fa-flag-checkered"></i>
            </div>
            <div>
                <h5>Update Container Status</h5>
                <p id="outcomeContainerName">Select the delivery outcome</p>
            </div>
        </div>
        <div class="modal-body">
            <form id="outcomeForm" method="POST" action="dispatch-action.php" novalidate>
                <input type="hidden" name="action"      value="update_status">
                <input type="hidden" name="redirect_id" value="<?php echo $bl['id']; ?>">
                <input type="hidden" name="dispatch_id" id="outcomeDispatchId">
                <input type="hidden" name="bl_item_id"  id="outcomeItemId">
                <input type="hidden" name="new_status"  id="outcomeStatus" value="">

                <!-- Outcome Buttons -->
                <div style="display:flex;gap:12px;margin-bottom:1.25rem;">
                    <button type="button" class="outcome-btn received-btn" onclick="selectOutcome('received')">
                        <i class="fas fa-circle-check" style="color:var(--emerald);"></i>
                        <span>Received</span>
                        <small>Container delivered successfully</small>
                    </button>
                    <button type="button" class="outcome-btn rejected-btn" onclick="selectOutcome('rejected')">
                        <i class="fas fa-circle-xmark" style="color:var(--rose);"></i>
                        <span>Rejected</span>
                        <small>Container was not accepted</small>
                    </button>
                </div>

                <!-- Rejection Reason (shown only if rejected) -->
                <div class="rejection-wrap" id="rejectionWrap">
                    <label class="f-label">
                        Rejection Reason <span class="req">*</span>
                    </label>
                    <textarea
                        name="rejection_reason"
                        id="rejectionReason"
                        class="f-input"
                        rows="3"
                        placeholder="Please explain why the container was rejected..."
                    ></textarea>
                </div>

                <!-- Notes -->
                <div>
                    <label class="f-label">Additional Notes (Optional)</label>
                    <textarea name="notes" class="f-input" rows="2"
                              placeholder="Any additional notes about the delivery..."></textarea>
                </div>

            </form>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn-m btn-ghost" onclick="closeOutcomeModal()">
                <i class="fas fa-xmark"></i> Cancel
            </button>
            <button type="button" class="btn-m btn-indigo" id="confirmOutcomeBtn"
                    onclick="submitOutcome()" disabled>
                <i class="fas fa-check"></i> Confirm
            </button>
        </div>
    </div>
</div>

<script>
/* ══ DISPATCH MODAL ══ */
function openDispatch(itemId, containerNum) {
    document.getElementById('dispatchItemId').value      = itemId;
    document.getElementById('dispatchContainerName').textContent = 'Container: ' + containerNum;
    document.getElementById('dispatchForm').reset();
    document.getElementById('dispatchItemId').value = itemId;
    document.getElementById('dispatchModal').classList.add('show');
}

function closeDispatchModal() {
    document.getElementById('dispatchModal').classList.remove('show');
}

function submitDispatch() {
    const form = document.getElementById('dispatchForm');

    // Basic validation
    const required = form.querySelectorAll('[required]');
    let valid = true;
    required.forEach(el => {
        if (!el.value.trim()) {
            el.style.borderColor = 'var(--rose)';
            el.style.boxShadow   = '0 0 0 3px rgba(244,63,94,0.1)';
            valid = false;
            el.addEventListener('input', () => {
                el.style.borderColor = '';
                el.style.boxShadow   = '';
            }, { once: true });
        }
    });

    if (!valid) return;

    const btn = document.querySelector('#dispatchModal .btn-indigo');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Dispatching...';
    btn.disabled  = true;
    form.submit();
}

// Close on backdrop click
document.getElementById('dispatchModal').addEventListener('click', function(e) {
    if (e.target === this) closeDispatchModal();
});

/* ══ OUTCOME MODAL ══ */
let selectedOutcome = '';

function openOutcome(itemId, containerNum, dispatchId) {
    selectedOutcome = '';
    document.getElementById('outcomeItemId').value       = itemId;
    document.getElementById('outcomeDispatchId').value   = dispatchId;
    document.getElementById('outcomeContainerName').textContent = 'Container: ' + containerNum;
    document.getElementById('outcomeStatus').value       = '';
    document.getElementById('rejectionWrap').style.display = 'none';
    document.getElementById('rejectionReason').value    = '';
    document.getElementById('confirmOutcomeBtn').disabled = true;

    // Reset buttons
    document.querySelectorAll('.outcome-btn').forEach(b => b.classList.remove('selected'));

    document.getElementById('outcomeModal').classList.add('show');
}

function closeOutcomeModal() {
    document.getElementById('outcomeModal').classList.remove('show');
}

function selectOutcome(outcome) {
    selectedOutcome = outcome;
    document.getElementById('outcomeStatus').value = outcome;
    document.getElementById('confirmOutcomeBtn').disabled = false;

    // Highlight selected button
    document.querySelectorAll('.outcome-btn').forEach(b => b.classList.remove('selected'));
    document.querySelector('.' + outcome + '-btn').classList.add('selected');

    // Show rejection reason if rejected
    const rw = document.getElementById('rejectionWrap');
    rw.style.display = outcome === 'rejected' ? 'block' : 'none';
}

function submitOutcome() {
    if (!selectedOutcome) return;

    if (selectedOutcome === 'rejected') {
        const reason = document.getElementById('rejectionReason').value.trim();
        if (!reason) {
            document.getElementById('rejectionReason').style.borderColor = 'var(--rose)';
            document.getElementById('rejectionReason').style.boxShadow   = '0 0 0 3px rgba(244,63,94,0.1)';
            document.getElementById('rejectionReason').focus();
            return;
        }
    }

    const btn = document.getElementById('confirmOutcomeBtn');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
    btn.disabled  = true;
    document.getElementById('outcomeForm').submit();
}

document.getElementById('outcomeModal').addEventListener('click', function(e) {
    if (e.target === this) closeOutcomeModal();
});

/* ══ FILTER TABS ══ */
function filterCards(status, btn) {
    // Update button styles
    document.querySelectorAll('.filter-tab').forEach(b => {
        b.style.background = 'none';
        b.style.color      = 'var(--txt-2)';
    });
    btn.style.background = 'linear-gradient(135deg,var(--indigo),var(--violet))';
    btn.style.color      = 'white';

    // Show/hide cards
    document.querySelectorAll('.container-card').forEach(card => {
        if (status === 'all' || card.dataset.status === status) {
            card.style.display = '';
            card.style.animation = 'rowIn 0.3s ease';
        } else {
            card.style.display = 'none';
        }
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>