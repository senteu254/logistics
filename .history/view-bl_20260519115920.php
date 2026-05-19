<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
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

// Items with dispatch + empty return info
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
           d.rejection_reason,
           er.id                 AS return_id,
           er.return_status,
           er.bl_type            AS return_bl_type,
           er.depot,
           er.transporter        AS return_transporter,
           er.local_depot,
           er.date_in,
           er.returned_date,
           er.completed_at       AS return_completed_at
    FROM bl_items i
    LEFT JOIN dispatches d   ON d.bl_item_id = i.id
    LEFT JOIN empty_returns er ON er.bl_item_id = i.id
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
/* ══ Existing styles (kept) ══ */
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
    content:''; position:absolute; right:-60px; top:-60px;
    width:220px; height:220px; background:rgba(255,255,255,0.07);
    border-radius:50%; pointer-events:none;
}
.view-hero::after {
    content:''; position:absolute; right:60px; bottom:-80px;
    width:160px; height:160px; background:rgba(255,255,255,0.05);
    border-radius:50%; pointer-events:none;
}
.view-hero-content { position:relative; z-index:1; }
.view-hero-bl   { font-size:0.76rem;font-weight:700;text-transform:uppercase;letter-spacing:0.12em;opacity:0.65;margin-bottom:6px; }
.view-hero-num  { font-size:1.9rem;font-weight:800;letter-spacing:-0.5px;margin-bottom:0.4rem;font-family:'Courier New',monospace; }
.view-hero-desc { font-size:0.88rem;opacity:0.78;max-width:560px;margin-bottom:1rem;line-height:1.5; }
.view-hero-tags { display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:0.75rem; }
.hero-tag { display:inline-flex;align-items:center;gap:5px;padding:4px 12px;border-radius:20px;font-size:0.76rem;font-weight:700;backdrop-filter:blur(4px); }
.hero-tag-type-tbl    { background:rgba(165,180,252,0.25);color:#c7d2fe;border:1px solid rgba(165,180,252,0.3); }
.hero-tag-type-nontbl { background:rgba(110,231,183,0.25);color:#a7f3d0;border:1px solid rgba(110,231,183,0.3); }
.hero-tag-status      { background:rgba(255,255,255,0.15);color:white;border:1px solid rgba(255,255,255,0.2); }
.hero-tag-date-ok     { background:rgba(110,231,183,0.2);color:#a7f3d0;border:1px solid rgba(110,231,183,0.3); }
.hero-tag-date-warn   { background:rgba(252,211,77,0.2);color:#fef08a;border:1px solid rgba(252,211,77,0.3); }
.hero-tag-date-urgent { background:rgba(252,165,165,0.25);color:#fca5a5;border:1px solid rgba(252,165,165,0.3); }
.view-hero-meta { display:flex;align-items:center;gap:16px;flex-wrap:wrap;font-size:0.78rem;opacity:0.65; }
.view-hero-meta span { display:flex;align-items:center;gap:5px; }

.summary-grid { display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:1rem;margin-bottom:1.5rem; }
.summary-tile { background:white;border:1px solid var(--border);border-radius:var(--r-md);padding:1.1rem;text-align:center;transition:var(--t); }
.summary-tile:hover { box-shadow:var(--shadow-md);transform:translateY(-2px); }
.summary-tile-ico { font-size:1.25rem;margin-bottom:0.5rem; }
.summary-tile-val { font-size:1.2rem;font-weight:800;color:var(--txt-1);line-height:1;margin-bottom:3px; }
.summary-tile-lbl { font-size:0.68rem;font-weight:600;color:var(--txt-2);text-transform:uppercase;letter-spacing:0.05em; }

.return-date-banner { display:flex;align-items:center;gap:12px;padding:12px 18px;border-radius:var(--r-md);margin-bottom:1.25rem;font-size:0.855rem;font-weight:600; }
.rdb-overdue { background:rgba(244,63,94,0.08);border:1px solid rgba(244,63,94,0.25);color:#9f1239; }
.rdb-urgent  { background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.25);color:#92400e; }

.dispatch-progress-card { background:white;border:1px solid var(--border);border-radius:var(--r-lg);padding:1.4rem;margin-bottom:1.5rem;box-shadow:var(--shadow-sm); }
.progress-track { height:10px;background:var(--border);border-radius:99px;overflow:hidden;margin-bottom:1rem;display:flex; }
.progress-seg { height:100%;transition:width 0.6s ease; }
.seg-received { background:var(--emerald); }
.seg-transit  { background:var(--amber); }
.seg-rejected { background:var(--rose); }
.dispatch-counts { display:flex;gap:1.25rem;flex-wrap:wrap; }
.dispatch-count-item { display:flex;align-items:center;gap:6px;font-size:0.78rem;font-weight:600;color:var(--txt-2); }
.count-dot { width:10px;height:10px;border-radius:50%;flex-shrink:0; }

.container-card { background:white;border:1px solid var(--border);border-radius:var(--r-lg);overflow:hidden;transition:var(--t);margin-bottom:1rem; }
.container-card:hover { box-shadow:var(--shadow-md);border-color:rgba(99,102,241,0.2); }
.container-card-head { display:flex;align-items:center;justify-content:space-between;padding:1rem 1.4rem;border-bottom:1px solid var(--border-l);flex-wrap:wrap;gap:10px;background:#fafbff; }
.container-num-badge { width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--indigo),var(--violet));color:white;display:flex;align-items:center;justify-content:center;font-size:0.82rem;font-weight:800;flex-shrink:0; }
.container-num-text { font-family:'Courier New',monospace;font-weight:700;font-size:1rem;color:var(--indigo);letter-spacing:0.5px; }
.container-num-sub { font-size:0.74rem;color:var(--txt-2);margin-top:1px; }
.container-card-body { display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:1rem;padding:1rem 1.4rem; }
.detail-item-lbl { font-size:0.67rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:var(--txt-3);margin-bottom:3px; }
.detail-item-val { font-size:0.875rem;font-weight:600;color:var(--txt-1); }
.detail-item-val.mono  { font-family:'Courier New',monospace;color:var(--indigo); }
.detail-item-val.muted { color:var(--txt-3);font-style:italic;font-weight:400; }

.ds-badge { display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:20px;font-size:0.75rem;font-weight:700; }
.ds-pending  { background:rgba(148,163,184,0.1);color:#64748b;border:1px solid rgba(148,163,184,0.3); }
.ds-transit  { background:rgba(245,158,11,0.1);color:#d97706;border:1px solid rgba(245,158,11,0.3); }
.ds-received { background:rgba(16,185,129,0.1);color:#059669;border:1px solid rgba(16,185,129,0.3); }
.ds-rejected { background:rgba(244,63,94,0.1);color:#e11d48;border:1px solid rgba(244,63,94,0.3); }

.pulse-dot { width:7px;height:7px;background:var(--amber);border-radius:50%;animation:pulseAnim 1.5s infinite; }
@keyframes pulseAnim {
    0%   { box-shadow:0 0 0 0 rgba(245,158,11,0.5); }
    70%  { box-shadow:0 0 0 6px rgba(245,158,11,0); }
    100% { box-shadow:0 0 0 0 rgba(245,158,11,0); }
}

.dispatch-info-strip { padding:0.75rem 1.4rem;border-top:1px solid var(--border-l);background:#f8fafc;display:flex;align-items:center;gap:1.25rem;flex-wrap:wrap;font-size:0.78rem;color:var(--txt-2); }
.dispatch-info-strip span { display:flex;align-items:center;gap:5px;font-weight:500; }
.dispatch-info-strip strong { color:var(--txt-1); }

/* ══ Empty Return Strip ══ */
.return-strip {
    padding: 0.85rem 1.4rem;
    border-top: 1px solid var(--border-l);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
}

.return-strip-none {
    background: linear-gradient(to right, rgba(99,102,241,0.03), rgba(139,92,246,0.03));
}

.return-strip-transit {
    background: linear-gradient(to right, rgba(245,158,11,0.05), rgba(251,191,36,0.03));
}

.return-strip-completed {
    background: linear-gradient(to right, rgba(16,185,129,0.05), rgba(6,182,212,0.03));
}

.return-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 13px;
    border-radius: 20px;
    font-size: 0.74rem;
    font-weight: 700;
}

.return-badge-none     { background:rgba(99,102,241,0.08);color:var(--indigo);border:1px solid rgba(99,102,241,0.2); }
.return-badge-transit  { background:rgba(245,158,11,0.1);color:#d97706;border:1px solid rgba(245,158,11,0.3); }
.return-badge-completed{ background:rgba(16,185,129,0.1);color:#059669;border:1px solid rgba(16,185,129,0.3); }

.return-info-chips {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.return-chip {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 0.76rem;
    color: var(--txt-2);
    font-weight: 500;
}

.return-chip strong { color: var(--txt-1); }

/* ══ Modals ══ */
.modal-overlay {
    position:fixed;inset:0;
    background:rgba(0,0,0,0.6);
    backdrop-filter:blur(8px);
    z-index:2000;
    display:flex;align-items:center;justify-content:center;
    padding:1.5rem;
    opacity:0;pointer-events:none;
    transition:opacity 0.25s ease;
}
.modal-overlay.show { opacity:1;pointer-events:all; }
.modal-box {
    background:white;border-radius:24px;
    max-width:560px;width:100%;
    box-shadow:0 25px 80px rgba(0,0,0,0.25);
    transform:scale(0.94) translateY(10px);
    transition:transform 0.3s cubic-bezier(0.22,1,0.36,1);
    overflow:hidden;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
}
.modal-overlay.show .modal-box { transform:scale(1) translateY(0); }
.modal-head { padding:1.4rem 1.75rem;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:14px;background:linear-gradient(to right,#fafbff,white);flex-shrink:0; }
.modal-head-ico { width:44px;height:44px;border-radius:var(--r-sm);display:flex;align-items:center;justify-content:center;font-size:1.1rem;color:white;flex-shrink:0; }
.modal-head h5 { font-size:1rem;font-weight:700;color:var(--txt-1);margin:0; }
.modal-head p  { font-size:0.78rem;color:var(--txt-2);margin:0;margin-top:2px; }
.modal-body    { padding:1.5rem 1.75rem;overflow-y:auto; }
.modal-foot    { padding:1rem 1.75rem;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:flex-end;gap:10px;background:#fafbff;flex-shrink:0; }

.outcome-btns { display:flex;gap:12px;margin-bottom:1.25rem; }
.outcome-btn {
    flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;
    gap:8px;padding:1.1rem;border-radius:var(--r-md);
    border:2px solid var(--border);background:white;cursor:pointer;transition:var(--t);
    font-family:'Inter',sans-serif;
}
.outcome-btn i     { font-size:1.5rem; }
.outcome-btn span  { font-size:0.88rem;font-weight:700; }
.outcome-btn small { font-size:0.72rem;color:var(--txt-2); }
.outcome-btn.received-btn:hover,.outcome-btn.received-btn.selected { border-color:var(--emerald);background:rgba(16,185,129,0.06);color:var(--emerald); }
.outcome-btn.rejected-btn:hover,.outcome-btn.rejected-btn.selected { border-color:var(--rose);background:rgba(244,63,94,0.06);color:var(--rose); }

.rejection-wrap { display:none;animation:slideDown 0.25s ease; }
@keyframes slideDown { from{opacity:0;transform:translateY(-5px);}to{opacity:1;transform:translateY(0);} }

.filter-tabs { display:flex;gap:4px;background:white;border:1px solid var(--border);border-radius:var(--r-sm);padding:4px; }
.filter-tab { padding:5px 12px;border:none;border-radius:6px;font-size:0.78rem;font-weight:600;cursor:pointer;transition:var(--t);background:none;color:var(--txt-2);font-family:'Inter',sans-serif; }
.filter-tab:hover { background:var(--bg);color:var(--txt-1); }
.filter-tab.active { background:linear-gradient(135deg,var(--indigo),var(--violet));color:white; }

/* ══ Return Flow: Step indicator ══ */
.return-steps {
    display: flex;
    align-items: center;
    gap: 0;
    margin-bottom: 1.5rem;
}

.return-step {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    position: relative;
}

.return-step:not(:last-child)::after {
    content: '';
    position: absolute;
    top: 16px;
    left: 50%;
    width: 100%;
    height: 2px;
    background: var(--border);
    z-index: 0;
}

.return-step.active::after,
.return-step.done::after {
    background: linear-gradient(to right, var(--indigo), var(--violet));
}

.step-dot {
    width: 32px; height: 32px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.78rem; font-weight: 800;
    position: relative; z-index: 1;
    border: 2px solid var(--border);
    background: white;
    color: var(--txt-3);
    transition: var(--t);
}

.return-step.active .step-dot {
    background: linear-gradient(135deg, var(--indigo), var(--violet));
    border-color: var(--indigo);
    color: white;
}

.return-step.done .step-dot {
    background: var(--emerald);
    border-color: var(--emerald);
    color: white;
}

.step-lbl {
    font-size: 0.67rem;
    font-weight: 600;
    color: var(--txt-3);
    text-align: center;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.return-step.active .step-lbl { color: var(--indigo); }
.return-step.done   .step-lbl { color: var(--emerald); }

/* ══ BL Type selector in modal ══ */
.type-toggle {
    display: flex;
    gap: 0;
    border: 1.5px solid var(--border);
    border-radius: var(--r-sm);
    overflow: hidden;
    margin-bottom: 1.25rem;
}

.type-toggle-btn {
    flex: 1;
    padding: 12px 16px;
    border: none;
    background: var(--bg);
    cursor: pointer;
    font-family: 'Inter', sans-serif;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--txt-2);
    transition: var(--t);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    border-right: 1.5px solid var(--border);
}

.type-toggle-btn:last-child { border-right: none; }

.type-toggle-btn.active-tbl {
    background: linear-gradient(135deg, rgba(99,102,241,0.1), rgba(139,92,246,0.07));
    color: var(--indigo);
}

.type-toggle-btn.active-nontbl {
    background: linear-gradient(135deg, rgba(16,185,129,0.1), rgba(6,182,212,0.07));
    color: var(--emerald);
}

/* Info callout box */
.info-callout {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 12px 14px;
    border-radius: var(--r-md);
    font-size: 0.8rem;
    line-height: 1.5;
    margin-bottom: 1.25rem;
}

.info-callout-indigo {
    background: rgba(99,102,241,0.06);
    border: 1px solid rgba(99,102,241,0.18);
    color: #3730a3;
}

.info-callout-emerald {
    background: rgba(16,185,129,0.06);
    border: 1px solid rgba(16,185,129,0.18);
    color: #065f46;
}

.info-callout i { font-size: 0.85rem; flex-shrink: 0; margin-top: 1px; }

@media print {
    .sidebar,.top-nav,.no-print { display:none !important; }
    .main-wrap { margin-left:0 !important; }
    .page-content { padding:0 !important; }
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
if (isset($_GET['dispatched']))       { $flashMsg = '<i class="fas fa-truck"></i> Container dispatched — now <strong>In Transit</strong>.'; $flashType = 'success'; }
elseif (isset($_GET['received']))     { $flashMsg = '<i class="fas fa-circle-check"></i> Container marked as <strong>Received</strong>.'; $flashType = 'success'; }
elseif (isset($_GET['rejected']))     { $flashMsg = '<i class="fas fa-circle-xmark"></i> Container marked as <strong>Rejected</strong>.'; $flashType = 'error'; }
elseif (isset($_GET['return_initiated'])) { $flashMsg = '<i class="fas fa-rotate-left"></i> Empty container return <strong>initiated</strong> successfully.'; $flashType = 'success'; }
elseif (isset($_GET['date_in_saved'])){ $flashMsg = '<i class="fas fa-calendar-check"></i> Date-in recorded. Return is <strong>in progress</strong>.'; $flashType = 'success'; }
elseif (isset($_GET['return_completed'])) { $flashMsg = '<i class="fas fa-circle-check"></i> Empty container return <strong>completed</strong>!'; $flashType = 'success'; }
elseif (isset($_GET['error'])) {
    $msgs = [
        'missing_fields'     => 'Please fill all required fields.',
        'already_dispatched' => 'This container has already been dispatched.',
        'invalid_status'     => 'Invalid status update.',
        'missing_reason'     => 'Please provide a rejection reason.',
        'return_exists'      => 'An empty return has already been initiated for this container.',
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
setTimeout(()=>{
    const el=document.getElementById('flashAlert');
    if(el){el.style.transition='opacity 0.5s';el.style.opacity='0';setTimeout(()=>el.remove(),500);}
},5000);
</script>
<?php endif; ?>

<!-- Return Date Banner -->
<?php if ($daysLeft !== null && $daysLeft < 0): ?>
<div class="return-date-banner rdb-overdue">
    <i class="fas fa-circle-exclamation" style="font-size:1.1rem;flex-shrink:0;"></i>
    <div>
        <strong>Container Return Overdue!</strong>
        The last return date was <strong><?php echo date('M d, Y', strtotime($returnDate)); ?></strong>
        — <?php echo abs($daysLeft); ?> day<?php echo abs($daysLeft)>1?'s':''; ?> ago.
        Immediate action required.
    </div>
</div>
<?php elseif ($daysLeft !== null && $daysLeft <= 7): ?>
<div class="return-date-banner rdb-urgent">
    <i class="fas fa-triangle-exclamation" style="font-size:1.1rem;flex-shrink:0;"></i>
    <div>
        <strong>Return Deadline Approaching!</strong>
        Containers must be returned by <strong><?php echo date('M d, Y', strtotime($returnDate)); ?></strong>
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
        <div class="view-hero-tags">
            <?php $isT = ($bl['bl_type'] === 'TBL'); ?>
            <span class="hero-tag <?php echo $isT ? 'hero-tag-type-tbl' : 'hero-tag-type-nontbl'; ?>">
                <i class="fas <?php echo $isT ? 'fa-ship' : 'fa-clipboard'; ?>"></i>
                <?php echo htmlspecialchars($bl['bl_type']); ?>
            </span>
            <span class="hero-tag hero-tag-status">
                <i class="fas fa-circle" style="font-size:0.4rem;"></i>
                <?php echo ucfirst($bl['status']); ?>
            </span>
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
                    if ($daysLeft < 0)       echo abs($daysLeft) . ' days overdue';
                    elseif ($daysLeft === 0) echo 'Due TODAY';
                    else                     echo $daysLeft . ' days left';
                ?>
            </span>
            <?php endif; ?>
        </div>
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
    <div class="summary-tile"><div class="summary-tile-ico">🚢</div><div class="summary-tile-val"><?php echo count($items); ?></div><div class="summary-tile-lbl">Containers</div></div>
    <div class="summary-tile"><div class="summary-tile-ico">📦</div><div class="summary-tile-val"><?php echo number_format($totalBags); ?></div><div class="summary-tile-lbl">Total Bags</div></div>
    <div class="summary-tile"><div class="summary-tile-ico">⚖️</div><div class="summary-tile-val"><?php echo number_format($totalGross,3); ?></div><div class="summary-tile-lbl">Gross Wt (MT)</div></div>
    <div class="summary-tile"><div class="summary-tile-ico">🏋️</div><div class="summary-tile-val"><?php echo number_format($totalNet,3); ?></div><div class="summary-tile-lbl">Net Wt (MT)</div></div>
    <div class="summary-tile"><div class="summary-tile-ico">📊</div><div class="summary-tile-val"><?php echo number_format($totalTare,3); ?></div><div class="summary-tile-lbl">Tare Wt (MT)</div></div>
    <div class="summary-tile"><div class="summary-tile-ico">⏳</div><div class="summary-tile-val" style="color:var(--txt-3);"><?php echo $pendingCount; ?></div><div class="summary-tile-lbl">Pending</div></div>
    <div class="summary-tile"><div class="summary-tile-ico">🚛</div><div class="summary-tile-val" style="color:var(--amber);"><?php echo $transitCount; ?></div><div class="summary-tile-lbl">In Transit</div></div>
    <div class="summary-tile"><div class="summary-tile-ico">✅</div><div class="summary-tile-val" style="color:var(--emerald);"><?php echo $receivedCount; ?></div><div class="summary-tile-lbl">Received</div></div>
    <div class="summary-tile"><div class="summary-tile-ico">❌</div><div class="summary-tile-val" style="color:var(--rose);"><?php echo $rejectedCount; ?></div><div class="summary-tile-lbl">Rejected</div></div>
</div>

<!-- Dispatch Progress -->
<?php
$total  = count($items) ?: 1;
$recPct = round(($receivedCount/$total)*100);
$traPct = round(($transitCount/$total)*100);
$rejPct = round(($rejectedCount/$total)*100);
?>
<div class="dispatch-progress-card">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:0.9rem;">
        <div style="font-size:0.93rem;font-weight:700;color:var(--txt-1);display:flex;align-items:center;gap:8px;">
            <i class="fas fa-route" style="color:var(--indigo);"></i> Dispatch Progress
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
    $ds  = $item['dispatch_status'] ?? 'pending';
    $ers = $item['return_status']   ?? null;   // null = no return initiated
    $blType = $bl['bl_type'];
?>
<div class="container-card" data-status="<?php echo $ds; ?>">

    <!-- Card Head -->
    <div class="container-card-head">
        <div style="display:flex;align-items:center;gap:12px;">
            <div class="container-num-badge"><?php echo $i+1; ?></div>
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
            <!-- Dispatch Status Badge -->
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

            <?php elseif ($ds === 'received' && !$ers): ?>
            <!-- Prompt to initiate empty return -->
            <button type="button"
                    class="btn-m btn-sm no-print"
                    style="background:linear-gradient(135deg,#f59e0b,#d97706);color:white;border:none;display:flex;align-items:center;gap:6px;padding:7px 14px;border-radius:var(--r-sm);font-size:0.8rem;font-weight:700;cursor:pointer;transition:var(--t);"
                    onclick="openReturn(
                        <?php echo $item['id']; ?>,
                        '<?php echo htmlspecialchars($item['container_number']); ?>',
                        <?php echo $item['dispatch_id']; ?>,
                        '<?php echo $blType; ?>'
                    )">
                <i class="fas fa-rotate-left"></i> Initiate Return
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Card Body -->
    <div class="container-card-body">
        <div><div class="detail-item-lbl">Container No.</div><div class="detail-item-val mono"><?php echo htmlspecialchars($item['container_number']); ?></div></div>
        <div><div class="detail-item-lbl">Agent Seal</div><div class="detail-item-val <?php echo $item['agent_seal_number']?'':'muted'; ?>"><?php echo $item['agent_seal_number'] ? htmlspecialchars($item['agent_seal_number']) : '—'; ?></div></div>
        <div><div class="detail-item-lbl">SGS Seal</div><div class="detail-item-val <?php echo $item['sgs_seal_number']?'':'muted'; ?>"><?php echo $item['sgs_seal_number'] ? htmlspecialchars($item['sgs_seal_number']) : '—'; ?></div></div>
        <div><div class="detail-item-lbl">No. of Bags</div><div class="detail-item-val" style="color:var(--indigo);"><?php echo number_format($item['number_of_bags']); ?></div></div>
        <div><div class="detail-item-lbl">Gross Weight</div><div class="detail-item-val" style="color:var(--amber);"><?php echo fmtMT($item['gross_weight']); ?></div></div>
        <div><div class="detail-item-lbl">Net Weight</div><div class="detail-item-val" style="color:var(--emerald);"><?php echo fmtMT($item['net_weight']); ?></div></div>
        <div><div class="detail-item-lbl">Tare Weight</div><div class="detail-item-val" style="color:var(--txt-2);"><?php echo fmtMT((float)$item['gross_weight']-(float)$item['net_weight']); ?></div></div>
        <div><div class="detail-item-lbl">Dispatch Status</div><div class="detail-item-val"><?php echo ucfirst($ds); ?></div></div>
    </div>

    <!-- Dispatch Info Strip -->
    <?php if ($ds !== 'pending' && $item['transporter_name']): ?>
    <div class="dispatch-info-strip">
        <span><i class="fas fa-user-tie"></i> <strong><?php echo htmlspecialchars($item['transporter_name']); ?></strong></span>
        <span><i class="fas fa-truck"></i> <strong><?php echo htmlspecialchars($item['truck_number']); ?></strong></span>
        <span><i class="fas fa-location-dot"></i> <strong><?php echo htmlspecialchars($item['destination']); ?></strong></span>
        <?php if ($item['clearing_agent_dnote']): ?><span><i class="fas fa-file-lines"></i> CA: <strong><?php echo htmlspecialchars($item['clearing_agent_dnote']); ?></strong></span><?php endif; ?>
        <?php if ($item['transporter_dnote']): ?><span><i class="fas fa-file-alt"></i> TR: <strong><?php echo htmlspecialchars($item['transporter_dnote']); ?></strong></span><?php endif; ?>
        <span style="margin-left:auto;color:var(--txt-3);font-size:0.72rem;">
            Dispatched <?php echo date('M d, Y H:i', strtotime($item['dispatched_at_detail'])); ?>
        </span>
        <?php if ($ds === 'received' && $item['received_at']): ?>
        <span style="color:var(--emerald);"><i class="fas fa-circle-check"></i> Received <?php echo date('M d, Y H:i', strtotime($item['received_at'])); ?></span>
        <?php endif; ?>
        <?php if ($ds === 'rejected' && $item['rejection_reason']): ?>
        <span style="color:var(--rose);"><i class="fas fa-circle-xmark"></i> Reason: <?php echo htmlspecialchars($item['rejection_reason']); ?></span>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ══ Empty Return Strip ══ -->
    <?php if ($ds === 'received'): ?>
    <?php if (!$ers): ?>
    <!-- Not yet initiated -->
    <div class="return-strip return-strip-none">
        <div style="display:flex;align-items:center;gap:10px;">
            <span class="return-badge return-badge-none">
                <i class="fas fa-rotate-left"></i> Empty Return Pending
            </span>
            <span style="font-size:0.77rem;color:var(--txt-3);">
                <?php echo $blType === 'TBL' ? 'Container to be returned to shipping line depot' : 'Container to be returned to local depot'; ?>
            </span>
        </div>
        <button type="button"
                class="btn-m btn-sm no-print"
                style="background:linear-gradient(135deg,var(--indigo),var(--violet));color:white;border:none;font-size:0.78rem;font-weight:700;padding:6px 14px;border-radius:var(--r-sm);cursor:pointer;display:flex;align-items:center;gap:6px;"
                onclick="openReturn(<?php echo $item['id']; ?>,'<?php echo htmlspecialchars($item['container_number']); ?>',<?php echo $item['dispatch_id']; ?>,'<?php echo $blType; ?>')">
            <i class="fas fa-rotate-left"></i> Initiate Return
        </button>
    </div>

    <?php elseif ($ers === 'in_transit'): ?>
    <!-- Return in transit — waiting for date-in -->
    <div class="return-strip return-strip-transit">
        <div class="return-info-chips">
            <span class="return-badge return-badge-transit">
                <span class="pulse-dot" style="background:var(--amber);"></span>
                Return In Transit
            </span>
            <?php if ($blType === 'Non-TBL' && $item['return_transporter']): ?>
            <span class="return-chip"><i class="fas fa-user-tie"></i> <strong><?php echo htmlspecialchars($item['return_transporter']); ?></strong></span>
            <?php endif; ?>
            <?php if ($item['local_depot']): ?>
            <span class="return-chip"><i class="fas fa-warehouse"></i> <strong><?php echo htmlspecialchars($item['local_depot']); ?></strong></span>
            <?php endif; ?>
            <?php if ($item['depot']): ?>
            <span class="return-chip"><i class="fas fa-anchor"></i> <strong><?php echo htmlspecialchars($item['depot']); ?></strong></span>
            <?php endif; ?>
        </div>
        <button type="button"
                class="btn-m btn-sm no-print"
                style="background:linear-gradient(135deg,var(--amber),#d97706);color:white;border:none;font-size:0.78rem;font-weight:700;padding:6px 14px;border-radius:var(--r-sm);cursor:pointer;display:flex;align-items:center;gap:6px;"
                onclick="openDateIn(<?php echo $item['id']; ?>,'<?php echo htmlspecialchars($item['container_number']); ?>',<?php echo $item['return_id']; ?>,'<?php echo $blType; ?>')">
            <i class="fas fa-calendar-check"></i> Record Arrival
        </button>
    </div>

    <?php elseif ($ers === 'completed'): ?>
    <!-- Return completed -->
    <div class="return-strip return-strip-completed">
        <div class="return-info-chips">
            <span class="return-badge return-badge-completed">
                <i class="fas fa-circle-check"></i> Return Completed
            </span>
            <?php if ($item['depot']): ?>
            <span class="return-chip"><i class="fas fa-anchor"></i> Depot: <strong><?php echo htmlspecialchars($item['depot']); ?></strong></span>
            <?php endif; ?>
            <?php if ($item['local_depot']): ?>
            <span class="return-chip"><i class="fas fa-warehouse"></i> Depot: <strong><?php echo htmlspecialchars($item['local_depot']); ?></strong></span>
            <?php endif; ?>
            <?php if ($item['date_in']): ?>
            <span class="return-chip"><i class="fas fa-calendar-check"></i> Date In: <strong><?php echo date('M d, Y', strtotime($item['date_in'])); ?></strong></span>
            <?php endif; ?>
            <?php if ($item['return_completed_at']): ?>
            <span class="return-chip" style="color:var(--txt-3);font-size:0.72rem;">Completed <?php echo date('M d, Y', strtotime($item['return_completed_at'])); ?></span>
            <?php endif; ?>
        </div>
        <span style="font-size:0.78rem;color:var(--emerald);font-weight:600;display:flex;align-items:center;gap:5px;">
            <i class="fas fa-circle-check"></i> Closed
        </span>
    </div>
    <?php endif; ?>
    <?php endif; ?>

</div>
<?php endforeach; ?>
</div>

<!-- ════════════════════════════════════════
     MODAL 1: DISPATCH
     ════════════════════════════════════════ -->
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
                        <input type="text" name="transporter_name" class="f-input" placeholder="e.g. XYZ Transport Ltd" required>
                    </div>
                    <div class="col-md-6">
                        <label class="f-label">Truck Number <span class="req">*</span></label>
                        <input type="text" name="truck_number" class="f-input" placeholder="e.g. KDG 123A" required oninput="this.value=this.value.toUpperCase()">
                    </div>
                    <div class="col-md-6">
                        <label class="f-label">Clearing Agent D/Note</label>
                        <input type="text" name="clearing_agent_dnote" class="f-input" placeholder="e.g. CA-2024-0012">
                    </div>
                    <div class="col-md-6">
                        <label class="f-label">Transporter D/Note</label>
                        <input type="text" name="transporter_dnote" class="f-input" placeholder="e.g. TR-2024-0056">
                    </div>
                    <div class="col-12">
                        <label class="f-label">Destination <span class="req">*</span></label>
                        <input type="text" name="destination" class="f-input" placeholder="e.g. Nairobi ICD" required>
                    </div>
                    <div class="col-12">
                        <label class="f-label">Notes (Optional)</label>
                        <textarea name="notes" class="f-input" rows="2" placeholder="Any additional dispatch notes..."></textarea>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn-m btn-ghost" onclick="closeDispatchModal()"><i class="fas fa-xmark"></i> Cancel</button>
            <button type="button" class="btn-m btn-indigo" onclick="submitDispatch()"><i class="fas fa-truck"></i> Dispatch Container</button>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════
     MODAL 2: OUTCOME (Received / Rejected)
     ════════════════════════════════════════ -->
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
                    <textarea name="rejection_reason" id="rejectionReason" class="f-input" rows="3" placeholder="Why was the container rejected?"></textarea>
                </div>
                <div style="margin-top:12px;">
                    <label class="f-label">Notes (Optional)</label>
                    <textarea name="notes" class="f-input" rows="2" placeholder="Additional delivery notes..."></textarea>
                </div>
            </form>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn-m btn-ghost" onclick="closeOutcomeModal()"><i class="fas fa-xmark"></i> Cancel</button>
            <button type="button" class="btn-m btn-indigo" id="confirmOutcomeBtn" onclick="submitOutcome()" disabled><i class="fas fa-check"></i> Confirm</button>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════
     MODAL 3: INITIATE EMPTY RETURN
     Shown after container is received.
     TBL:     enter depot name → save (status: in_transit)
     Non-TBL: enter transporter + depot → save (status: in_transit)
     ════════════════════════════════════════ -->
<div class="modal-overlay" id="returnModal">
    <div class="modal-box">
        <div class="modal-head">
            <div class="modal-head-ico" style="background:linear-gradient(135deg,#f59e0b,#d97706);">
                <i class="fas fa-rotate-left"></i>
            </div>
            <div>
                <h5>Initiate Empty Return</h5>
                <p id="returnContainerName">Container return details</p>
            </div>
        </div>
        <div class="modal-body">

            <!-- Step indicator -->
            <div class="return-steps">
                <div class="return-step active" id="rStep1">
                    <div class="step-dot">1</div>
                    <div class="step-lbl">Initiate</div>
                </div>
                <div class="return-step" id="rStep2">
                    <div class="step-dot">2</div>
                    <div class="step-lbl">In Transit</div>
                </div>
                <div class="return-step" id="rStep3">
                    <div class="step-dot">3</div>
                    <div class="step-lbl">Complete</div>
                </div>
            </div>

            <form id="returnForm" method="POST" action="dispatch-action.php" novalidate>
                <input type="hidden" name="action"      value="empty_return">
                <input type="hidden" name="redirect_id" value="<?php echo $bl['id']; ?>">
                <input type="hidden" name="bl_id"       value="<?php echo $bl['id']; ?>">
                <input type="hidden" name="bl_item_id"  id="returnItemId">
                <input type="hidden" name="dispatch_id" id="returnDispatchId">
                <input type="hidden" name="bl_type"     id="returnBlType">

                <!-- TBL fields -->
                <div id="returnTblFields">
                    <div class="info-callout info-callout-indigo">
                        <i class="fas fa-ship"></i>
                        <div>
                            <strong>TBL Container</strong> — empty container will be returned to the <strong>shipping line depot</strong>.
                            Enter the depot name below to initiate the return process.
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="f-label">Shipping Line Depot <span class="req">*</span></label>
                            <input type="text" name="depot" id="returnDepotTbl" class="f-input"
                                   placeholder="e.g. Mombasa CFS, Kenya Ports Authority Yard">
                        </div>
                        <div class="col-12">
                            <label class="f-label">Notes (Optional)</label>
                            <textarea name="notes" class="f-input" rows="2"
                                      placeholder="Any notes about the return..."></textarea>
                        </div>
                    </div>
                </div>

                <!-- Non-TBL fields -->
                <div id="returnNonTblFields" style="display:none;">
                    <div class="info-callout info-callout-emerald">
                        <i class="fas fa-warehouse"></i>
                        <div>
                            <strong>Non-TBL Container</strong> — empty container will be returned to a <strong>local depot</strong> via transporter.
                            Enter the transporter and depot details below.
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="f-label">Transporter <span class="req">*</span></label>
                            <input type="text" name="transporter" id="returnTransporter" class="f-input"
                                   placeholder="e.g. Swift Logistics Ltd">
                        </div>
                        <div class="col-md-6">
                            <label class="f-label">Local Depot <span class="req">*</span></label>
                            <input type="text" name="local_depot" id="returnLocalDepot" class="f-input"
                                   placeholder="e.g. ICD Nairobi Empty Yard">
                        </div>
                        <div class="col-12">
                            <label class="f-label">Notes (Optional)</label>
                            <textarea name="notes_nontbl" class="f-input" rows="2"
                                      placeholder="Any notes about the return..."></textarea>
                        </div>
                    </div>
                </div>

            </form>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn-m btn-ghost" onclick="closeReturnModal()"><i class="fas fa-xmark"></i> Cancel</button>
            <button type="button" class="btn-m" id="returnSubmitBtn"
                    style="background:linear-gradient(135deg,#f59e0b,#d97706);color:white;border:none;"
                    onclick="submitReturn()">
                <i class="fas fa-rotate-left"></i> Initiate Return
            </button>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════
     MODAL 4: RECORD ARRIVAL (Date In)
     After container arrives at depot, record date in → complete.
     ════════════════════════════════════════ -->
<div class="modal-overlay" id="dateInModal">
    <div class="modal-box">
        <div class="modal-head">
            <div class="modal-head-ico" style="background:linear-gradient(135deg,var(--emerald),#0d9488);">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div>
                <h5>Record Container Arrival</h5>
                <p id="dateInContainerName">Enter arrival details</p>
            </div>
        </div>
        <div class="modal-body">

            <!-- Step indicator -->
            <div class="return-steps">
                <div class="return-step done" id="diStep1">
                    <div class="step-dot"><i class="fas fa-check" style="font-size:0.7rem;"></i></div>
                    <div class="step-lbl">Initiated</div>
                </div>
                <div class="return-step active" id="diStep2">
                    <div class="step-dot">2</div>
                    <div class="step-lbl">Arrived</div>
                </div>
                <div class="return-step" id="diStep3">
                    <div class="step-dot">3</div>
                    <div class="step-lbl">Complete</div>
                </div>
            </div>

            <form id="dateInForm" method="POST" action="dispatch-action.php" novalidate>
                <input type="hidden" name="action"      value="complete_return">
                <input type="hidden" name="redirect_id" value="<?php echo $bl['id']; ?>">
                <input type="hidden" name="bl_item_id"  id="dateInItemId">
                <input type="hidden" name="return_id"   id="dateInReturnId">

                <div id="dateInTblInfo" style="display:none;">
                    <div class="info-callout info-callout-indigo">
                        <i class="fas fa-ship"></i>
                        <div>Container has arrived at the <strong>shipping line depot</strong>. Enter the date it was received at the depot to complete the return.</div>
                    </div>
                </div>
                <div id="dateInNonTblInfo" style="display:none;">
                    <div class="info-callout info-callout-emerald">
                        <i class="fas fa-warehouse"></i>
                        <div>Container has arrived at the <strong>local depot</strong>. Enter the date it was received to complete the return process.</div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-12">
                        <label class="f-label">Date Container Arrived at Depot <span class="req">*</span></label>
                        <div style="position:relative;">
                            <input type="date" name="returned_date" id="dateInDate" class="f-input"
                                   max="<?php echo date('Y-m-d'); ?>" required
                                   style="padding-right:44px;">
                            <i class="fas fa-calendar-days" style="position:absolute;right:13px;top:50%;transform:translateY(-50%);color:var(--indigo);font-size:0.9rem;pointer-events:none;"></i>
                        </div>
                        <div style="font-size:0.73rem;color:var(--txt-3);margin-top:5px;">
                            <i class="fas fa-circle-info"></i>
                            Enter the actual date the empty container arrived at the depot.
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="f-label">Notes (Optional)</label>
                        <textarea name="notes" class="f-input" rows="2"
                                  placeholder="Any final notes about the return completion..."></textarea>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn-m btn-ghost" onclick="closeDateInModal()"><i class="fas fa-xmark"></i> Cancel</button>
            <button type="button" class="btn-m btn-indigo" onclick="submitDateIn()">
                <i class="fas fa-circle-check"></i> Complete Return
            </button>
        </div>
    </div>
</div>

<script>
/* ══ Dispatch Modal ══ */
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
    const form = document.getElementById('dispatchForm');
    const required = form.querySelectorAll('[required]');
    let valid = true;
    required.forEach(el => {
        if (!el.value.trim()) {
            el.style.borderColor = 'var(--rose)';
            el.style.boxShadow   = '0 0 0 3px rgba(244,63,94,0.1)';
            valid = false;
            el.addEventListener('input', () => { el.style.borderColor=''; el.style.boxShadow=''; }, { once:true });
        }
    });
    if (!valid) return;
    const btn = document.querySelector('#dispatchModal .btn-indigo');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Dispatching...';
    btn.disabled  = true;
    form.submit();
}
document.getElementById('dispatchModal').addEventListener('click', function(e) { if (e.target===this) closeDispatchModal(); });

/* ══ Outcome Modal ══ */
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
function closeOutcomeModal() { document.getElementById('outcomeModal').classList.remove('show'); }
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
        if (!r) { document.getElementById('rejectionReason').style.borderColor='var(--rose)'; document.getElementById('rejectionReason').focus(); return; }
    }
    const btn = document.getElementById('confirmOutcomeBtn');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
    btn.disabled  = true;
    document.getElementById('outcomeForm').submit();
}
document.getElementById('outcomeModal').addEventListener('click', function(e) { if (e.target===this) closeOutcomeModal(); });

/* ══ Return Modal ══ */
let currentBlType = '';
function openReturn(itemId, containerNum, dispatchId, blType) {
    currentBlType = blType;
    document.getElementById('returnItemId').value     = itemId;
    document.getElementById('returnDispatchId').value = dispatchId;
    document.getElementById('returnBlType').value     = blType;
    document.getElementById('returnContainerName').textContent = 'Container: ' + containerNum + ' (' + blType + ')';

    // Show/hide fields by type
    document.getElementById('returnTblFields').style.display    = blType === 'TBL' ? '' : 'none';
    document.getElementById('returnNonTblFields').style.display = blType === 'Non-TBL' ? '' : 'none';

    // Clear fields
    document.getElementById('returnDepotTbl').value     = '';
    document.getElementById('returnTransporter').value  = '';
    document.getElementById('returnLocalDepot').value   = '';

    document.getElementById('returnModal').classList.add('show');
}
function closeReturnModal() { document.getElementById('returnModal').classList.remove('show'); }
function submitReturn() {
    let valid = true;
    if (currentBlType === 'TBL') {
        const depot = document.getElementById('returnDepotTbl');
        if (!depot.value.trim()) {
            depot.style.borderColor = 'var(--rose)';
            depot.style.boxShadow   = '0 0 0 3px rgba(244,63,94,0.1)';
            depot.focus();
            depot.addEventListener('input', () => { depot.style.borderColor=''; depot.style.boxShadow=''; }, { once:true });
            valid = false;
        }
    } else {
        const tr = document.getElementById('returnTransporter');
        const ld = document.getElementById('returnLocalDepot');
        [tr, ld].forEach(el => {
            if (!el.value.trim()) {
                el.style.borderColor = 'var(--rose)';
                el.style.boxShadow   = '0 0 0 3px rgba(244,63,94,0.1)';
                el.addEventListener('input', () => { el.style.borderColor=''; el.style.boxShadow=''; }, { once:true });
                valid = false;
            }
        });
        if (!valid) { document.getElementById('returnTransporter').focus(); }
    }
    if (!valid) return;
    const btn = document.getElementById('returnSubmitBtn');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Initiating...';
    btn.disabled  = true;
    document.getElementById('returnForm').submit();
}
document.getElementById('returnModal').addEventListener('click', function(e) { if (e.target===this) closeReturnModal(); });

/* ══ Date-In Modal ══ */
function openDateIn(itemId, containerNum, returnId, blType) {
    document.getElementById('dateInItemId').value  = itemId;
    document.getElementById('dateInReturnId').value= returnId;
    document.getElementById('dateInContainerName').textContent = 'Container: ' + containerNum;
    document.getElementById('dateInDate').value    = '';

    // Show relevant callout
    document.getElementById('dateInTblInfo').style.display    = blType === 'TBL' ? '' : 'none';
    document.getElementById('dateInNonTblInfo').style.display = blType === 'Non-TBL' ? '' : 'none';

    document.getElementById('dateInModal').classList.add('show');
}
function closeDateInModal() { document.getElementById('dateInModal').classList.remove('show'); }
function submitDateIn() {
    const dateEl = document.getElementById('dateInDate');
    if (!dateEl.value) {
        dateEl.style.borderColor = 'var(--rose)';
        dateEl.style.boxShadow   = '0 0 0 3px rgba(244,63,94,0.1)';
        dateEl.focus();
        dateEl.addEventListener('input', () => { dateEl.style.borderColor=''; dateEl.style.boxShadow=''; }, { once:true });
        return;
    }
    const btn = document.querySelector('#dateInModal .btn-indigo');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Completing...';
    btn.disabled  = true;
    document.getElementById('dateInForm').submit();
}
document.getElementById('dateInModal').addEventListener('click', function(e) { if (e.target===this) closeDateInModal(); });

/* ══ Filter Cards ══ */
function filterCards(status, btn) {
    document.querySelectorAll('.filter-tab').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.container-card').forEach(card => {
        card.style.display = (status==='all' || card.dataset.status===status) ? '' : 'none';
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>