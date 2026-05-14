<?php
require_once 'config/database.php';
$page_title = 'Dispatches';

// Filters
$status_filter = trim($_GET['status'] ?? '');
$search        = trim($_GET['search'] ?? '');
$perPage       = 15;
$page          = max(1, intval($_GET['page'] ?? 1));
$offset        = ($page - 1) * $perPage;

// Build WHERE
$where  = ['1=1'];
$params = [];

if ($status_filter) {
    $where[]  = "d.status = ?";
    $params[] = $status_filter;
}

if ($search) {
    $where[]  = "(b.bl_number LIKE ? OR d.truck_number LIKE ? OR d.transporter_name LIKE ? OR i.container_number LIKE ? OR d.destination LIKE ?)";
    $s = "%$search%";
    $params = array_merge($params, [$s,$s,$s,$s,$s]);
}

$whereSQL = implode(' AND ', $where);

// Count
$countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM dispatches d
    JOIN bl_items i          ON d.bl_item_id = i.id
    JOIN bills_of_lading b   ON d.bl_id      = b.id
    WHERE $whereSQL
");
$countStmt->execute($params);
$totalRows  = $countStmt->fetchColumn();
$totalPages = max(1, ceil($totalRows / $perPage));

// Fetch
$dataStmt = $pdo->prepare("
    SELECT
        d.*,
        i.container_number,
        i.number_of_bags,
        i.gross_weight,
        i.net_weight,
        b.bl_number,
        b.item_description,
        u1.full_name   AS dispatched_by_name,
        u2.full_name   AS received_by_name
    FROM dispatches d
    JOIN bl_items i          ON d.bl_item_id = i.id
    JOIN bills_of_lading b   ON d.bl_id      = b.id
    LEFT JOIN users u1       ON d.dispatched_by = u1.id
    LEFT JOIN users u2       ON d.received_by   = u2.id
    WHERE $whereSQL
    ORDER BY d.dispatched_at DESC
    LIMIT $perPage OFFSET $offset
");
$dataStmt->execute($params);
$dispatches = $dataStmt->fetchAll();

// Summary counts
$counts = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status='transit'  THEN 1 ELSE 0 END) AS transit,
        SUM(CASE WHEN status='received' THEN 1 ELSE 0 END) AS received,
        SUM(CASE WHEN status='rejected' THEN 1 ELSE 0 END) AS rejected
    FROM dispatches
")->fetch();

require_once 'includes/header.php';
?>

<style>
/* ── Status tabs ── */
.status-tabs {
    display: flex;
    gap: 4px;
    background: white;
    border: 1px solid var(--border);
    border-radius: var(--r-lg);
    padding: 5px;
    margin-bottom: 1.25rem;
    overflow-x: auto;
    box-shadow: var(--shadow-sm);
    flex-wrap: nowrap;
}

.status-tab {
    display: flex;
    align-items: center;
    gap: 7px;
    padding: 9px 18px;
    border-radius: var(--r-md);
    font-size: 0.845rem;
    font-weight: 600;
    color: var(--txt-2);
    text-decoration: none;
    transition: var(--t);
    white-space: nowrap;
    border: none;
    background: none;
}

.status-tab:hover {
    background: var(--bg);
    color: var(--txt-1);
}

.status-tab.active {
    background: linear-gradient(135deg, var(--indigo), var(--violet));
    color: white;
    box-shadow: 0 3px 12px rgba(99,102,241,0.3);
}

.status-tab .tab-count {
    background: rgba(255,255,255,0.25);
    padding: 1px 7px;
    border-radius: 20px;
    font-size: 0.72rem;
}

.status-tab:not(.active) .tab-count {
    background: var(--border);
    color: var(--txt-2);
}

/* ── Dispatch Row Card ── */
.dispatch-row {
    background: white;
    border: 1px solid var(--border);
    border-radius: var(--r-lg);
    overflow: hidden;
    margin-bottom: 12px;
    transition: var(--t);
}

.dispatch-row:hover {
    box-shadow: var(--shadow-md);
    border-color: rgba(99,102,241,0.2);
    transform: translateY(-1px);
}

.dispatch-row-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.4rem;
    border-bottom: 1px solid var(--border-l);
    flex-wrap: wrap;
    gap: 10px;
    background: #fafbff;
}

.dispatch-row-body {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 1rem;
    padding: 1rem 1.4rem;
}

.d-info-item {}
.d-info-lbl {
    font-size: 0.67rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: var(--txt-3);
    margin-bottom: 3px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.d-info-val {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--txt-1);
}

.d-info-val.mono {
    font-family: 'Courier New', monospace;
    color: var(--indigo);
}

.d-info-val.muted {
    color: var(--txt-3);
    font-style: italic;
    font-weight: 400;
}

/* ── Status Badges ── */
.ds-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 700;
}

.ds-transit  { background:rgba(245,158,11,0.1);  color:#d97706; border:1px solid rgba(245,158,11,0.3); }
.ds-received { background:rgba(16,185,129,0.1);  color:#059669; border:1px solid rgba(16,185,129,0.3); }
.ds-rejected { background:rgba(244,63,94,0.1);   color:#e11d48; border:1px solid rgba(244,63,94,0.3); }

.pulse-dot {
    width: 7px; height: 7px;
    background: var(--amber);
    border-radius: 50%;
    animation: pulseRing 1.5s infinite;
}

@keyframes pulseRing {
    0%   { box-shadow: 0 0 0 0 rgba(245,158,11,0.5); }
    70%  { box-shadow: 0 0 0 6px rgba(245,158,11,0); }
    100% { box-shadow: 0 0 0 0 rgba(245,158,11,0); }
}

/* ── Update Status Modal ── */
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
    max-width: 520px;
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
    padding: 1.4rem 1.75rem;
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

.modal-head h5 { font-size:1rem; font-weight:700; color:var(--txt-1); margin:0; }
.modal-head p  { font-size:0.78rem; color:var(--txt-2); margin:0; margin-top:2px; }
.modal-body    { padding:1.5rem 1.75rem; }
.modal-foot    {
    padding: 1rem 1.75rem;
    border-top: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 10px;
    background: #fafbff;
}

.outcome-btns {
    display: flex;
    gap: 12px;
    margin-bottom: 1.25rem;
}

.outcome-btn {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 1.1rem;
    border-radius: var(--r-md);
    border: 2px solid var(--border);
    background: white;
    cursor: pointer;
    transition: var(--t);
    font-family: 'Inter', sans-serif;
}

.outcome-btn i     { font-size: 1.5rem; }
.outcome-btn span  { font-size: 0.88rem; font-weight: 700; }
.outcome-btn small { font-size: 0.72rem; color: var(--txt-2); }

.outcome-btn.received-btn:hover,
.outcome-btn.received-btn.selected {
    border-color: var(--emerald);
    background: rgba(16,185,129,0.06);
    color: var(--emerald);
}

.outcome-btn.rejected-btn:hover,
.outcome-btn.rejected-btn.selected {
    border-color: var(--rose);
    background: rgba(244,63,94,0.06);
    color: var(--rose);
}

.rejection-wrap {
    display: none;
    animation: slideDown 0.25s ease;
}

@keyframes slideDown {
    from { opacity:0; transform:translateY(-5px); }
    to   { opacity:1; transform:translateY(0); }
}

/* ── Filter bar ── */
.filter-bar {
    background: white;
    border: 1px solid var(--border);
    border-radius: var(--r-lg);
    padding: 1rem 1.25rem;
    margin-bottom: 1.25rem;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    box-shadow: var(--shadow-sm);
}

.search-wrap { position:relative; flex:1; min-width:200px; }
.search-wrap i { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--txt-3); font-size:0.82rem; pointer-events:none; }
.search-input {
    width:100%; padding:8px 12px 8px 34px;
    border:1.5px solid var(--border); border-radius:var(--r-sm);
    background:var(--bg); font-family:'Inter',sans-serif; font-size:0.86rem;
    color:var(--txt-1); outline:none; transition:var(--t);
}
.search-input:focus { border-color:var(--indigo); background:white; box-shadow:0 0 0 3px rgba(99,102,241,0.1); }

/* Pagination */
.pg-wrap { display:flex; align-items:center; justify-content:space-between; padding:1rem 1.5rem; border-top:1px solid var(--border); flex-wrap:wrap; gap:12px; }
.pg-info { font-size:0.8rem; color:var(--txt-2); font-weight:500; }
.pg-btns { display:flex; gap:4px; }
.pg-btn  {
    width:32px; height:32px;
    border:1.5px solid var(--border); border-radius:8px;
    background:white; color:var(--txt-2);
    display:flex; align-items:center; justify-content:center;
    font-size:0.8rem; font-weight:600; cursor:pointer;
    text-decoration:none; transition:var(--t);
}
.pg-btn:hover  { border-color:var(--indigo); color:var(--indigo); }
.pg-btn.active { background:linear-gradient(135deg,var(--indigo),var(--violet)); border-color:transparent; color:white; }
.pg-btn.disabled { opacity:0.4; pointer-events:none; }
</style>

<!-- Page Header -->
<div class="page-header d-flex align-items-start justify-content-between flex-wrap gap-3">
    <div>
        <h1 class="page-title">Dispatches</h1>
        <p class="page-sub">Track all container dispatches and delivery status</p>
    </div>
    <a href="manage-bl.php" class="btn-m btn-indigo">
        <i class="fas fa-list-check"></i> Manage B/L
    </a>
</div>

<!-- Flash Messages -->
<?php if (isset($_GET['updated'])): ?>
<div class="alert-m alert-success" id="flashAlert">
    <i class="fas fa-circle-check alert-ico" style="color:var(--emerald);"></i>
    <div>Dispatch status updated successfully.</div>
</div>
<script>
setTimeout(()=>{
    const el=document.getElementById('flashAlert');
    if(el){el.style.transition='opacity 0.5s';el.style.opacity='0';setTimeout(()=>el.remove(),500);}
},4000);
</script>
<?php endif; ?>

<!-- Summary Stat Cards -->
<div class="row g-3 mb-3">
    <div class="col-sm-3">
        <div class="stat-card c-indigo">
            <div class="stat-ico c-indigo"><i class="fas fa-truck-loading"></i></div>
            <div class="stat-val"><?php echo number_format($counts['total']); ?></div>
            <div class="stat-lbl">Total Dispatches</div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="stat-card c-amber">
            <div class="stat-ico c-amber"><i class="fas fa-truck"></i></div>
            <div class="stat-val"><?php echo number_format($counts['transit']); ?></div>
            <div class="stat-lbl">In Transit</div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="stat-card c-emerald">
            