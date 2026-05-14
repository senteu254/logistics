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

// Items with dispatch info
$itemsStmt = $pdo->prepare("
    SELECT i.*,
           d.id                  AS dispatch_id,
           d.transporter_name,
           d.truck_number,
           d.destination,
           d.status              AS dispatch_status_detail,
           d.dispatched_at       AS dispatched_at_detail,
           d.received_at,
           d.clearing_agent_dnote,
           d.transporter_dnote,
           d.rejection_reason
    FROM bl_items i
    LEFT JOIN dispatches d ON d.bl_item_id = i.id
    WHERE i.bl_id = ?
    ORDER BY i.id ASC
");
$itemsStmt->execute([$id]);
$items = $itemsStmt->fetchAll();

// Totals
$totalBags  = array_sum(array_column($items, 'number_of_bags'));
$totalGross = array_sum(array_column($items, 'gross_weight'));
$totalNet   = array_sum(array_column($items, 'net_weight'));
$totalTare  = $totalGross - $totalNet;

// Dispatch counts
$pendingCount  = count(array_filter($items, fn($i) => ($i['dispatch_status'] ?? 'pending') === 'pending'));
$transitCount  = count(array_filter($items, fn($i) => ($i['dispatch_status'] ?? 'pending') === 'transit'));
$receivedCount = count(array_filter($items, fn($i) => ($i['dispatch_status'] ?? 'pending') === 'received'));
$rejectedCount = count(array_filter($items, fn($i) => ($i['dispatch_status'] ?? 'pending') === 'rejected'));

// Return date info
$returnDate = $bl['last_return_date'] ?? '';
$daysLeft   = $returnDate ? (int)((strtotime($returnDate) - strtotime('today')) / 86400) : null;

function fmtMT($val, $dec = 3) {
    return number_format((float)$val, $dec) . ' MT';
}

$page_title = 'View — ' . $bl['bl_number'];
require_once 'includes/header.php';
?>

<style>
/* Hero */
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
    pointer-events: none;
}

.view-hero::after {
    content: '';
    position: absolute;
    right: 60px; bottom: -80px;
    width: 160px; height: 160px;
    background: rgba(255,255,255,0.05);
    border-radius: 50%;
    pointer-events: none;
}

.view-hero-content { position: relative; z-index: 1; }
.view-hero-bl   { font-size:0.76rem;font-weight:700;text-transform:uppercase;letter-spacing:0.12em;opacity:0.65;margin-bottom:6px; }
.view-hero-num  { font-size:1.9rem;font-weight:800;letter-spacing:-0.5px;margin-bottom:0.4rem;font-family:'Courier New',monospace; }
.view-hero-desc { font-size:0.88rem;opacity:0.78;max-width:560px;margin-bottom:1rem;line-height:1.5; }

.view-hero-tags {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 0.75rem;
}

.hero-tag {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.76rem;
    font-weight: 700;
    backdrop-filter: blur(4px);
}

.hero-tag-type-tbl    { background: rgba(165,180,252,0.25); color: #c7d2fe; border: 1px solid rgba(165,180,252,0.3); }
.hero-tag-type-nontbl { background: rgba(110,231,183,0.25); color: #a7f3d0; border: 1px solid rgba(110,231,183,0.3); }
.hero-tag-status      { background: rgba(255,255,255,0.15); color: white;   border: 1px solid rgba(255,255,255,0.2); }
.hero-tag-date-ok     { background: rgba(110,231,183,0.2);  color: #a7f3d0; border: 1px solid rgba(110,231,183,0.3); }
.hero-tag-date-warn   { background: rgba(252,211,77,0.2);   color: #fef08a; border: 1px solid rgba(252,211,77,0.3); }
.hero-tag-date-urgent { background: rgba(252,165,165,0.25); color: #fca5a5; border: 1px solid rgba(252,165,165,0.3); }

.view-hero-meta {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
    font-size: 0.78rem;
    opacity: 0.65;
}

.view-hero-meta span {
    display: flex;
    align-items: center;
    gap: 5px;
}

/* Summary tiles */
.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(130px,1fr));
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
.summary-tile-ico { font-size:1.25rem; margin-bottom:0.5rem; }
.summary-tile-val { font-size:1.2rem; font-weight:800; color:var(--txt-1); line-height:1; margin-bottom:3px; }
.summary-tile-lbl { font-size:0.68rem; font-weight:600; color:var(--txt-2); text-transform:uppercase; letter-spacing:0.05em; }

/* Return date alert banner */
.return-date-banner {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 18px;
    border-radius: var(--r-md);
    margin-bottom: 1.25rem;
    font-size: 0.855rem;
    font-weight: 600;
}

.rdb-overdue { background: rgba(244,63,94,0.08); border: 1px solid rgba(244,63,94,0.25); color: #9f1239; }
.rdb-urgent  { background: rgba(245,158,11,0.08); border: 1px solid rgba(245,158,11,0.25); color: #92400e; }

/* Dispatch Progress */
.dispatch-progress-card {
    background: white;
    border: 1px solid var(--border);
    border-radius: var(--r-lg);
    padding: 1.4rem;
    margin-bottom: 1.5rem;
    box-shadow: var(--shadow-sm);
}

.progress-track {
    height: 10px;
    background: var(--border);
    border-radius: 99px;
    overflow: hidden;
    margin-bottom: 1rem;
    display: flex;
}

.progress-seg { height: 100%; transition: width 0.6s ease; }
.seg-received { background: var(--emerald); }
.seg-transit  { background: var(--amber); }
.seg-rejected { background: var(--rose); }

.dispatch-counts { display: flex; gap: 1.25rem; flex-wrap: wrap; }
.dispatch-count-item { display: flex; align-items: center; gap: 6px; font-size: 0.78rem; font-weight: 600; color: var(--txt-2); }
.count-dot { width:10px; height:10px; border-radius:50%; flex-shrink:0; }

/* Container Cards */
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
    border-color: rgba(99,102,241,0.2);
}

.container-card-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.4rem;
    border-bottom: 1px solid var(--border-l);
    flex-wrap: wrap;
    gap: 10px;
    background: #fafbff;
}

.container-num-badge {
    width: 36px; height: 36px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--indigo), var(--violet));
    color: white;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.82rem; font-weight: 800; flex-shrink: 0;
}

.container-num-text {
    font-family: 'Courier New', monospace;
    font-weight: 700;
    font-size: 1rem;
    color: var(--indigo);
    letter-spacing: 0.5px;
}

.container-num-sub {
    font-size: 0.74rem;
    color: var(--txt-2);
    margin-top: 1px;
}

.container-card-body {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px,1fr));
    gap: 1rem;
    padding: 1rem 1.4rem;
}

.detail-item-lbl {
    font-size: 0.67rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: var(--txt-3);
    margin-bottom: 3px;
}

.detail-item-val { font-size: 0.875rem; font-weight: 600; color: var(--txt-1); }
.detail-item-val.mono  { font-family: 'Courier New', monospace; color: var(--indigo); }
.detail-item-val.muted { color: var(--txt-3); font-style: italic; font-weight: 400; }

/* Status Badges */
.ds-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 700;
}

.ds-pending  { background:rgba(148,163,184,0.1); color:#64748b; border:1px solid rgba(148,163,184,0.3); }
.ds-transit  { background:rgba(245,158,11,0.1);  color:#d97706; border:1px solid rgba(245,158,11,0.3); }
.ds-received { background:rgba(16,185,129,0.1);  color:#059669; border:1px solid rgba(16,185,129,0.3); }
.ds-rejected { background:rgba(244,63,94,0.1);   color:#e11d48; border:1px solid rgba(244,63,94,0.3); }

.pulse-dot {
    width: 7px; height: 7px;
    background: var(--amber);
    border-radius: 50%;
    animation: pulseAnim 1.5s infinite;
}

@keyframes pulseAnim {
    0%   { box-shadow: 0 0 0 0 rgba(245,158,11,0.5); }
    70%  { box-shadow: 0 0 0 6px rgba(245,158,11,0); }
    100% { box-shadow: 0 0 0 0 rgba(245,158,11,0); }
}

/* Dispatch info strip */
.dispatch-info-strip {
    padding: 0.75rem 1.4rem;
    border-top: 1px solid var(--border-l);
    background: #f8fafc;
    display: flex;
    align-items: center;
    gap: 1.25rem;
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

/* Modals */
.modal-overlay {
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.6);
    backdrop-filter: blur(8px);
    z-index: 2000;
    display: flex; align-items: center; justify-content: center;
    padding: 1.5rem;
    opacity: 0; pointer-events: none;
    transition: opacity 0.25s ease;
}

.modal-overlay.show { opacity: 1; pointer-events: all; }

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

.modal-overlay.show .modal-box { transform: scale(1) translateY(0); }

.modal-head {
    padding: 1.4rem 1.75rem;
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center; gap: 14px;
    background: linear-gradient(to right, #fafbff, white);
}

.modal-head-ico {
    width: 44px; height: 44px;
    border-radius: var(--r-sm);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem; color: white; flex-shrink: 0;
}

.modal-head h5 { font-size:1rem; font-weight:700; color:var(--txt-1); margin:0; }
.modal-head p  { font-size:0.78rem; color:var(--txt-2); margin:0; margin-top:2px; }
.modal-body    { padding: 1.5rem 1.75rem; }
.modal-foot    {
    padding: 1rem 1.75rem;
    border-top: 1px solid var(--border);
    display: flex; align-items: center; justify-content: flex-end; gap: 10px;
    background: #fafbff;
}

.outcome-btns { display: flex; gap: 12px; margin-bottom: 1.25rem; }

.outcome-btn {
    flex: 1;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    gap: 8px; padding: 1.1rem;
    border-radius: var(--r-md);
    border: 2px solid var(--border);
    background: white; cursor: pointer; transition: var(--t);
    font-family: 'Inter', sans-serif;
}

.outcome-btn i     { font-size: 1.5rem; }
.outcome-btn span  { font-size: 0.88rem; font-weight: 700; }
.outcome-btn small { font-size: 0.72rem; color: var(--txt-2); }

.outcome-btn.received-btn:hover, .outcome-btn.received-btn.selected {
    border-color: var(--emerald); background: rgba(16,185,129,0.06); color: var(--emerald);
}

.outcome-btn.rejected-btn:hover, .outcome-btn.rejected-btn.selected {
    border-color: var(--rose); background: rgba(244,63,94,0.06); color: var(--rose);
}

.rejection-wrap {
    display: none;
    animation: slideDown 0.25s ease;
}

@keyframes slideDown {
    from { opacity:0; transform:translateY(-5px); }
    to   { opacity:1; transform:translateY(0); }
}

/* Filter tabs */
.filter-tabs {
    display: flex;
    gap: 4px;
    background: white;
    border: 1px solid var(--border);
    border-radius: var(--r-sm);
    padding: 4px;
}

.filter-tab {
    padding: 5px 12px;
    border: none;
    border-radius: 6px;
    font-size: 0.78rem;
    font-weight: 600;
    cursor: pointer;
    transition: var(--t);
    background: none;
    color: var(--txt-2);
    font-family: 'Inter', sans-serif;
}

.filter-tab:hover { background: var(--bg); color: var(--txt-1); }
.filter-tab.active { background: linear-gradient(135deg,var(--indigo),var(--violet)); color: white; }

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

<!-- Flash Messages -->
<?php
$flashMsg  = '';
$flashType = '';
if (isset($_GET['dispatched']))  { $flashMsg = '<i class="fas fa-truck"></i> Container dispatched — now <strong>In Transit</strong>.'; $flashType = 'success'; }
elseif (isset($_GET['received'])) { $flashMsg = '<i class="fas fa-circle-check"></i> Container marked as <strong>Received</strong>.'; $flashType = 'success'; }
elseif (isset($_GET['rejected'])) { $flashMsg = '<i class="fas fa-circle-xmark"></i> Container marked as <strong>Rejected</strong>.'; $flashType = 'error'; }
elseif (isset($_GET['error'])) {
    $msgs = [
        'missing_fields'     => 'Please fill all required dispatch fields.',
        'already_dispatched' => 'This container has already been dispatched.',
        'invalid_status'     => 'Invalid status update.',
        'missing_reason'     => 'Please provide a rejection reason.',
        'db_error'           => 'A database error occurred.',
    ];
    $flashMsg  = '<i class="fas fa-circle-exclamation"></i> ' . ($msgs[$_GET['error']] ?? 'An error occurred.');
    $flashType = 'error';
}
?>

<?php if ($flashMsg): ?>
<div class="alert-m alert-<?php echo $flashType === 'success' ? 'success' : 'error'; ?>" id="flashAlert">
    <div><?php echo $flashMsg; ?></div>
</div>
<script>
setTimeout(() => {
    const el = document.getElementById('flashAlert');
    if (el) { el.style.transition='opacity 0.5s'; el.style.opacity='0'; setTimeout(()=>el.remove(),500); }
}, 4000);
</script>
<?php endif; ?>

<!-- Return Date Overdue Banner -->
<?php if ($daysLeft !== null && $daysLeft < 0): ?>
<div class="return-date-banner rdb-overdue">
    <i class="fas fa-circle-exclamation" style="font-size:1.1rem;flex-shrink:0;"></i>
    <div>
        <strong>Container Return Overdue!</strong>
        The last return date for this B/L was
        <strong><?php echo date('M d, Y', strtotime($returnDate)); ?></strong>
        — <?php echo abs($daysLeft); ?> day<?php echo abs($daysLeft)>1?'s':''; ?> ago.
        Immediate action required.
    </div>
</div>
<?php elseif ($daysLeft !== null && $daysLeft <= 7): ?>
<div class="return-date-banner rdb-urgent">
    <i class="fas fa-triangle-exclamation" style="font-size:1.1rem;flex-shrink:0;"></i>
    <div>
        <strong>Return Deadline Approaching!</strong>
        Containers must be returned by
        <strong><?php echo date('M d, Y', strtotime($returnDate)); ?></strong>
        — only <strong><?php echo $daysLeft; ?> day<?php echo $daysLeft>1?'s':''; ?></strong> remaining.
    </div>
</div>
<?php endif; ?>

<!-- HERO -->
<div class="view-hero">
    <div class="view-hero-content">
        <div class="view-hero-bl">Bill of Lading</div>
        <div class="view-hero-num"><?php echo htmlspecialchars($bl['bl_number']); ?></div>
        <div class="view-hero-desc"><?php echo htmlspecialchars($bl['item_description']); ?></div>

        <!-- Tags Row -->
        <div class="view-hero-tags">

            <!-- BL Type -->
            <?php $isT = ($bl['bl_type'] === 'TBL'); ?>
            <span class="hero-tag <?php echo $isT ? 'hero-tag-type-tbl' : 'hero-tag-type-nontbl'; ?>">
                <i class="fas <?php echo $isT ? 'fa-ship' : 'fa-clipboard'; ?>"></i>
                <?php echo htmlspecialchars($bl['bl_type']); ?>
            </span>

            <!-- Status -->
            <span class="hero-tag hero-tag-status">
                <i class="fas fa-circle" style="font-size:0.4rem;"></i>
                <?php echo ucfirst($bl['status']); ?>
            </span>

            <!-- Return Date -->
            <?php if ($returnDate):
                if ($daysLeft < 0)       $rdClass = 'hero-tag-date-urgent';
                elseif ($daysLeft <= 7)  $rdClass = 'hero-tag-date-urgent';
                elseif ($daysLeft <= 21) $rdClass = 'hero-tag-date-warn';
                else                     $rdClass = 'hero-tag-date-ok';
            ?>
            <span class="hero-tag <?php echo $rdClass; ?>">
                <i class="fas fa-calendar-xmark"></i>
                Return by: <?php echo date('M d, Y', strtotime($returnDate)); ?>
                &nbsp;·&nbsp;
                <?php
                    if ($daysLeft < 0)      echo abs($daysLeft) . ' days overdue';
                    elseif ($daysLeft === 0) echo 'Due TODAY';
                    else                     echo $daysLeft . ' days left';
                ?>
            </span>
            <?php endif; ?>

        </div>

        <!-- Meta -->
        <div class="view-hero-meta">
            <span><i class="fas fa-calendar"></i> <?php echo date('F d, Y', strtotime($bl['created_at'])); ?></span>
            <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($bl['full_name'] ?? $bl['username']); ?></span>
            <span><i class="fas fa-clock"></i> <?php echo date('H:i', strtotime($bl['created_at'])); ?></span>
            <span><i class="fas fa-boxes-stacked"></i> <?php echo count($items); ?> container<?php echo count($items)!=1?'s':''; ?></span>
        </div>
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
        <div class="summary-tile-lbl">Gross Wt (MT)</div>
    </div>
    <div class="summary-tile">
        <div class="summary-tile-ico">🏋️</div>
        <div class="summary-tile-val"><?php echo number_format($totalNet, 3); ?></div>
        <div class="summary-tile-lbl">Net Wt (MT)</div>
    </div>
    <div class="summary-tile">
        <div class="summary-tile-ico">📊</div>
        <div class="summary-tile-val"><?php echo number_format($totalTare, 3); ?></div>
        <div class="summary-tile-lbl">Tare Wt (MT)</div>
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
$total  = count($items) ?: 1;
$recPct = round(($receivedCount / $total) * 100);
$traPct = round(($transitCount  / $total) * 100);
$rejPct = round(($rejectedCount / $total) * 100);
?>
<div class="dispatch-progress-card">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:0.9rem;">
        <div style="font-size:0.93rem;font-weight:700;color:var(--txt-1);display:flex;align-items:center;gap:8px;">
            <i class="fas fa-route" style="color:var(--indigo);"></i>
            Dispatch Progress
        </div>
        <span style="font-size:0.78rem;font-weight:500;color:var(--txt-2);">
            <?php echo $receivedCount; ?> of <?php echo count($items); ?> containers received
        </span>
    </div>
    <div class="progress-track">
        <div class="progress-seg seg-received" style="width:<?php echo $recPct; ?>%;"></div>
        <div class="progress-seg seg-transit"  style="width:<?php echo $traPct; ?>%;"></div>
        <div class="progress-seg seg-rejected" style="width:<?php echo $rejPct; ?>%;"></div>
    </div>
    <div class="dispatch-counts">
        <div class="dispatch-count-item"><div class="count-dot" style="background:var(--emerald);"></div>Received (<?php echo $receivedCount; ?>)</div>
        <div class="dispatch-count-item"><div class="count-dot" style="background:var(--amber);"></div>In Transit (<?php echo $transitCount; ?>)</div>
        <div class="dispatch-count-item"><div class="count-dot" style="background:var(--rose);"></div>Rejected (<?php echo $rejectedCount; ?>)</div>
        <div class="dispatch-count-item"><div class="count-dot" style="background:var(--border);border:1px solid #cbd5e1;"></div>Pending (<?php echo $pendingCount; ?>)</div>
    </div>
</div>

<!-- Container List Header -->
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:0.85rem;">
    <h6 style="font-weight:700;color:var(--txt-1);font-size:0.93rem;margin:0;">
        <i class="fas fa-boxes-stacked" style="color:var(--indigo);margin-right:7px;"></i>
        Container Details & Dispatch
    </h6>
    <div class="filter-tabs no-print">
        <button class="filter-tab active" onclick="filterCards('all',this)">All</button>
        <button class="filter-tab" onclick="filterCards('pending',this)">Pending</button>
        <button class="filter-tab" onclick="filterCards('transit',this)">Transit</button>
        <button class="filter-tab" onclick="filterCards('received',this)">Received</button>
        <button class="filter-tab" onclick="filterCards('rejected',this)">Rejected</button>
    </div>
</div>

<!-- Container Cards -->
<div id="containerList">
<?php foreach ($items as $i => $item):
    $ds = $item['dispatch_status'] ?? 'pending';
?>
<div class="container-card" data-status="<?php echo $ds; ?>">

    <!-- Card Head -->
    <div class="container-card-head">
        <div style="display:flex;align-items:center;gap:12px;">
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
                <span class="ds-badge ds-transit"><span class="pulse-dot"></span> In Transit</span>
            <?php elseif ($ds === 'received'): ?>
                <span class="ds-badge ds-received"><i class="fas fa-circle-check"></i> Received</span>
            <?php elseif ($ds === 'rejected'): ?>
                <span class="ds-badge ds-rejected"><i class="fas fa-circle-xmark"></i> Rejected</span>
            <?php endif; ?>

            <!-- Action Buttons -->
            <?php if ($ds === 'pending'): ?>
            <button type="button" class="btn-m btn-indigo btn-sm no-print"
                    onclick="openDispatch(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['container_number']); ?>')">
                <i class="fas fa-truck"></i> Dispatch
            </button>
            <?php elseif ($ds === 'transit'): ?>
            <button type="button" class="btn-m btn-ghost btn-sm no-print"
                    onclick="openOutcome(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['container_number']); ?>', <?php echo $item['dispatch_id']; ?>)">
                <i class="fas fa-flag-checkered"></i> Update Status
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Card Body -->
    <div class="container-card-body">
        <div>
            <div class="detail-item-lbl">Container No.</div>
            <div class="detail-item-val mono"><?php echo htmlspecialchars($item['container_number']); ?></div>
        </div>
        <div>
            <div class="detail-item-lbl">Agent Seal</div>
            <div class="detail-item-val <?php echo $item['agent_seal_number']?'':'muted'; ?>">
                <?php echo $item['agent_seal_number'] ? htmlspecialchars($item['agent_seal_number']) : '—'; ?>
            </div>
        </div>
        <div>
            <div class="detail-item-lbl">SGS Seal</div>
            <div class="detail-item-val <?php echo $item['sgs_seal_number']?'':'muted'; ?>">
                <?php echo $item['sgs_seal_number'] ? htmlspecialchars($item['sgs_seal_number']) : '—'; ?>
            </div>
        </div>
        <div>
            <div class="detail-item-lbl">No. of Bags</div>
            <div class="detail-item-val" style="color:var(--indigo);"><?php echo number_format($item['number_of_bags']); ?></div>
        </div>
        <div>
            <div class="detail-item-lbl">Gross Weight</div>
            <div class="detail-item-val" style="color:var(--amber);"><?php echo fmtMT($item['gross_weight']); ?></div>
        </div>
        <div>
            <div class="detail-item-lbl">Net Weight</div>
            <div class="detail-item-val" style="color:var(--emerald);"><?php echo fmtMT($item['net_weight']); ?></div>
        </div>
        <div>
            <div class="detail-item-lbl">Tare Weight</div>
            <div class="detail-item-val" style="color:var(--txt-2);"><?php echo fmtMT((float)$item['gross_weight']-(float)$item['net_weight']); ?></div>
        </div>
        <div>
            <div class="detail-item-lbl">Dispatch Status</div>
            <div class="detail-item-val"><?php echo ucfirst($ds); ?></div>
        </div>
    </div>

    <!-- Dispatch Info Strip -->
    <?php if ($ds !== 'pending' && $item['transporter_name']): ?>
    <div class="dispatch-info-strip">
        <span><i class="fas fa-user-tie"></i> <strong><?php echo htmlspecialchars($item['transporter_name']); ?></strong></span>
        <span><i class="fas fa-truck"></i> <strong><?php echo htmlspecialchars($item['truck_number']); ?></strong></span>
        <span><i class="fas fa-location-dot"></i> <strong><?php echo htmlspecialchars($item['destination']); ?></strong></span>
        <?php if ($item['clearing_agent_dnote']): ?>
        <span><i class="fas fa-file-lines"></i> CA: <strong><?php echo htmlspecialchars($item['clearing_agent_dnote']); ?></strong></span>
        <?php endif; ?>
        <?php if ($item['transporter_dnote']): ?>
        <span><i class="fas fa-file-alt"></i> TR: <strong><?php echo htmlspecialchars($item['transporter_dnote']); ?></strong></span>
        <?php endif; ?>
        <span style="margin-left:auto;color:var(--txt-3);font-size:0.72rem;">
            Dispatched <?php echo date('M d, Y H:i', strtotime($item['dispatched_at_detail'])); ?>
        </span>
        <?php if ($ds === 'received' && $item['received_at']): ?>
        <span style="color:var(--emerald);">
            <i class="fas fa-circle-check"></i>
            Received <?php echo date('M d, Y H:i', strtotime($item['received_at'])); ?>
        </span>
        <?php endif; ?>
        <?php if ($ds === 'rejected' && $item['rejection_reason']): ?>
        <span style="color:var(--rose);">
            <i class="fas fa-circle-xmark"></i>
            Reason: <?php echo htmlspecialchars($item['rejection_reason']); ?>
        </span>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>
<?php endforeach; ?>
</div>

<!-- DISPATCH MODAL -->
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
                        <input type="text" name="transporter_name" class="f-input"
                               placeholder="e.g. XYZ Transport Ltd" required>
                    </div>
                    <div class="col-md-6">
                        <label class="f-label">Truck Number <span class="req">*</span></label>
                        <input type="text" name="truck_number" class="f-input"
                               placeholder="e.g. KDG 123A"
                               required oninput="this.value=this.value.toUpperCase()">
                    </div>
                    <div class="col-md-6">
                        <label class="f-label">Clearing Agent D/Note</label>
                        <input type="text" name="clearing_agent_dnote" class="f-input"
                               placeholder="e.g. CA-2024-0012">
                    </div>
                    <div class="col-md-6">
                        <label class="f-label">Transporter D/Note</label>
                        <input type="text" name="transporter_dnote" class="f-input"
                               placeholder="e.g. TR-2024-0056">
                    </div>
                    <div class="col-12">
                        <label class="f-label">Destination <span class="req">*</span></label>
                        <input type="text" name="destination" class="f-input"
                               placeholder="e.g. Nairobi ICD" required>
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

<!-- OUTCOME MODAL -->
<div class="modal-overlay" id="outcomeModal">
    <div class="modal-box">
        <div class="modal-head">
            <div class="modal-head-ico" style="background:linear-gradient(135deg,var(--emerald),var(--cyan));">
                <i class="fas fa-flag-checkered"></i>
            </div>
            <div>
                <h5>Update Container Status</h5>
                <p id="outcomeContainerName">Select delivery outcome</p>
            </div>
        </div>
        <div class="modal-body">
            <form id="outcomeForm" method="POST" action="dispatch-action.php" novalidate>
                <input type="hidden" name="action"      value="update_status">
                <input type="hidden" name="redirect_id" value="<?php echo $bl['id']; ?>">
                <input type="hidden" name="dispatch_id" id="outcomeDispatchId">
                <input type="hidden" name="bl_item_id"  id="outcomeItemId">
                <input type="hidden" name="new_status"  id="outcomeStatus" value="">

                <div class="outcome-btns">
                    <button type="button" class="outcome-btn received-btn" onclick="selectOutcome('received')">
                        <i class="fas fa-circle-check" style="color:var(--emerald);"></i>
                        <span>Received</span>
                        <small>Delivered successfully</small>
                    </button>
                    <button type="button" class="outcome-btn rejected-btn" onclick="selectOutcome('rejected')">
                        <i class="fas fa-circle-xmark" style="color:var(--rose);"></i>
                        <span>Rejected</span>
                        <small>Container not accepted</small>
                    </button>
                </div>

                <div class="rejection-wrap" id="rejectionWrap">
                    <label class="f-label">Rejection Reason <span class="req">*</span></label>
                    <textarea name="rejection_reason" id="rejectionReason" class="f-input" rows="3"
                              placeholder="Why was the container rejected?"></textarea>
                </div>

                <div style="margin-top:12px;">
                    <label class="f-label">Notes (Optional)</label>
                    <textarea name="notes" class="f-input" rows="2"
                              placeholder="Additional delivery notes..."></textarea>
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
/* ── Dispatch Modal ── */
function openDispatch(itemId, containerNum) {
    document.getElementById('dispatchItemId').value = itemId;
    document.getElementById('dispatchContainerName').textContent = 'Container: ' + containerNum;
    document.getElementById('dispatchForm').reset();
    document.getElementById('dispatchItemId').value = itemId;
    document.getElementById('dispatchModal').classList.add('show');
}

function closeDispatchModal() {
    document.getElementById('dispatchModal').classList.remove('show');
}

function submitDispatch() {
    const form     = document.getElementById('dispatchForm');
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

document.getElementById('dispatchModal').addEventListener('click', function(e) {
    if (e.target === this) closeDispatchModal();
});

/* ── Outcome Modal ── */
let selectedOutcome = '';

function openOutcome(itemId, containerNum, dispatchId) {
    selectedOutcome = '';
    document.getElementById('outcomeItemId').value     = itemId;
    document.getElementById('outcomeDispatchId').value = dispatchId;
    document.getElementById('outcomeContainerName').textContent = 'Container: ' + containerNum;
    document.getElementById('outcomeStatus').value     = '';
    document.getElementById('rejectionWrap').style.display = 'none';
    document.getElementById('rejectionReason').value  = '';
    document.getElementById('confirmOutcomeBtn').disabled = true;
    document.querySelectorAll('.outcome-btn').forEach(b => b.classList.remove('selected'));
    document.getElementById('outcomeModal').classList.add('show');
}

function closeOutcomeModal() {
    document.getElementById('outcomeModal').classList.remove('show');
}

function selectOutcome(outcome) {
    selectedOutcome = outcome;
    document.getElementById('outcomeStatus').value    = outcome;
    document.getElementById('confirmOutcomeBtn').disabled = false;
    document.querySelectorAll('.outcome-btn').forEach(b => b.classList.remove('selected'));
    document.querySelector('.' + outcome + '-btn').classList.add('selected');
    document.getElementById('rejectionWrap').style.display = outcome === 'rejected' ? 'block' : 'none';
}

function submitOutcome() {
    if (!selectedOutcome) return;
    if (selectedOutcome === 'rejected') {
        const r = document.getElementById('rejectionReason').value.trim();
        if (!r) {
            document.getElementById('rejectionReason').style.borderColor = 'var(--rose)';
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

/* ── Filter Cards ── */
function filterCards(status, btn) {
    document.querySelectorAll('.filter-tab').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.container-card').forEach(card => {
        card.style.display = (status === 'all' || card.dataset.status === status) ? '' : 'none';
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>