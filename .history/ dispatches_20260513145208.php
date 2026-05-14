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
            <div class="stat-ico c-emerald"><i class="fas fa-circle-check"></i></div>
            <div class="stat-val"><?php echo number_format($counts['received']); ?></div>
            <div class="stat-lbl">Received</div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="stat-card c-rose">
            <div class="stat-ico c-rose"><i class="fas fa-circle-xmark"></i></div>
            <div class="stat-val"><?php echo number_format($counts['rejected']); ?></div>
            <div class="stat-lbl">Rejected</div>
        </div>
    </div>
</div>

<!-- Status Tabs -->
<div class="status-tabs">
    <?php
    $tabs = [
        [''         , 'All',        'fas fa-list',          $counts['total']],
        ['transit'  , 'In Transit', 'fas fa-truck',         $counts['transit']],
        ['received' , 'Received',   'fas fa-circle-check',  $counts['received']],
        ['rejected' , 'Rejected',   'fas fa-circle-xmark',  $counts['rejected']],
    ];
    foreach ($tabs as [$val, $label, $icon, $count]):
        $qs     = http_build_query(['status'=>$val,'search'=>$search,'page'=>1]);
        $active = $status_filter === $val ? 'active' : '';
    ?>
    <a href="?<?php echo $qs; ?>" class="status-tab <?php echo $active; ?>">
        <i class="<?php echo $icon; ?>"></i>
        <?php echo $label; ?>
        <span class="tab-count"><?php echo $count; ?></span>
    </a>
    <?php endforeach; ?>
</div>

<!-- Filter Bar -->
<form method="GET" id="filterForm">
    <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
    <div class="filter-bar">
        <div class="search-wrap">
            <i class="fas fa-magnifying-glass"></i>
            <input
                type="text"
                name="search"
                class="search-input"
                placeholder="Search B/L, container, truck, transporter, destination..."
                value="<?php echo htmlspecialchars($search); ?>"
                oninput="clearTimeout(window._st); window._st=setTimeout(()=>this.form.submit(),500)"
            >
        </div>
        <?php if ($search): ?>
        <a href="?status=<?php echo urlencode($status_filter); ?>" class="btn-m btn-ghost btn-sm">
            <i class="fas fa-xmark"></i> Clear
        </a>
        <?php endif; ?>
        <div style="color:var(--txt-3);font-size:0.8rem;font-weight:500;white-space:nowrap;margin-left:auto;">
            <?php echo number_format($totalRows); ?> record<?php echo $totalRows!=1?'s':''; ?>
        </div>
    </div>
</form>

<!-- Dispatch List -->
<?php if (empty($dispatches)): ?>
<div class="data-card">
    <div class="empty">
        <div class="empty-ico"><i class="fas fa-truck"></i></div>
        <h5>No Dispatches Found</h5>
        <p><?php echo $search||$status_filter ? 'Try adjusting your filters.' : 'No containers have been dispatched yet. Go to a B/L to dispatch containers.'; ?></p>
        <a href="manage-bl.php" class="btn-m btn-indigo mt-3" style="width:fit-content;margin:1rem auto 0;">
            <i class="fas fa-list-check"></i> View Bills of Lading
        </a>
    </div>
</div>

<?php else: ?>

<?php foreach ($dispatches as $d): ?>
<div class="dispatch-row">

    <!-- Row Header -->
    <div class="dispatch-row-head">
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">

            <!-- Container Number -->
            <div style="display:flex;align-items:center;gap:9px;">
                <div style="width:38px;height:38px;background:linear-gradient(135deg,var(--indigo),var(--violet));border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-size:0.85rem;flex-shrink:0;">
                    <i class="fas fa-ship"></i>
                </div>
                <div>
                    <div style="font-family:'Courier New',monospace;font-weight:700;color:var(--indigo);font-size:0.95rem;">
                        <?php echo htmlspecialchars($d['container_number']); ?>
                    </div>
                    <div style="font-size:0.75rem;color:var(--txt-2);">
                        B/L: <a href="view-bl.php?id=<?php echo $d['bl_id']; ?>"
                               style="color:var(--indigo);font-weight:600;text-decoration:none;">
                            <?php echo htmlspecialchars($d['bl_number']); ?>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Truck -->
            <div style="display:flex;align-items:center;gap:7px;padding:5px 12px;background:var(--bg);border-radius:var(--r-sm);border:1px solid var(--border);">
                <i class="fas fa-truck" style="color:var(--indigo);font-size:0.82rem;"></i>
                <span style="font-weight:700;font-size:0.85rem;font-family:'Courier New',monospace;">
                    <?php echo htmlspecialchars($d['truck_number']); ?>
                </span>
            </div>

            <!-- Destination -->
            <div style="display:flex;align-items:center;gap:6px;font-size:0.82rem;color:var(--txt-2);">
                <i class="fas fa-location-dot" style="color:var(--rose);"></i>
                <strong style="color:var(--txt-1);"><?php echo htmlspecialchars($d['destination']); ?></strong>
            </div>

        </div>

        <!-- Right side: status + actions -->
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">

            <?php if ($d['status'] === 'transit'): ?>
                <span class="ds-badge ds-transit">
                    <span class="pulse-dot"></span> In Transit
                </span>
                <button
                    type="button"
                    class="btn-m btn-ghost btn-sm"
                    onclick="openOutcome(
                        <?php echo $d['id']; ?>,
                        '<?php echo htmlspecialchars($d['container_number']); ?>',
                        <?php echo $d['bl_item_id']; ?>,
                        <?php echo $d['bl_id']; ?>
                    )"
                >
                    <i class="fas fa-flag-checkered"></i> Update Status
                </button>

            <?php elseif ($d['status'] === 'received'): ?>
                <span class="ds-badge ds-received">
                    <i class="fas fa-circle-check"></i> Received
                </span>

            <?php elseif ($d['status'] === 'rejected'): ?>
                <span class="ds-badge ds-rejected">
                    <i class="fas fa-circle-xmark"></i> Rejected
                </span>
            <?php endif; ?>

            <a href="view-bl.php?id=<?php echo $d['bl_id']; ?>"
               class="btn-m btn-ghost btn-sm">
                <i class="fas fa-eye"></i>
            </a>

        </div>
    </div>

    <!-- Row Body -->
    <div class="dispatch-row-body">

        <div class="d-info-item">
            <div class="d-info-lbl"><i class="fas fa-user-tie"></i> Transporter</div>
            <div class="d-info-val"><?php echo htmlspecialchars($d['transporter_name']); ?></div>
        </div>

        <div class="d-info-item">
            <div class="d-info-lbl"><i class="fas fa-boxes-stacked"></i> Bags / Weight</div>
            <div class="d-info-val">
                <?php echo number_format($d['number_of_bags']); ?> bags
                &nbsp;·&nbsp;
                <?php echo number_format($d['gross_weight'],3); ?> MT GW
            </div>
        </div>

        <div class="d-info-item">
            <div class="d-info-lbl"><i class="fas fa-file-lines"></i> CA D/Note</div>
            <div class="d-info-val <?php echo $d['clearing_agent_dnote'] ? 'mono' : 'muted'; ?>">
                <?php echo $d['clearing_agent_dnote'] ? htmlspecialchars($d['clearing_agent_dnote']) : '—'; ?>
            </div>
        </div>

        <div class="d-info-item">
            <div class="d-info-lbl"><i class="fas fa-file-alt"></i> Transporter D/Note</div>
            <div class="d-info-val <?php echo $d['transporter_dnote'] ? 'mono' : 'muted'; ?>">
                <?php echo $d['transporter_dnote'] ? htmlspecialchars($d['transporter_dnote']) : '—'; ?>
            </div>
        </div>

        <div class="d-info-item">
            <div class="d-info-lbl"><i class="fas fa-clock"></i> Dispatched</div>
            <div class="d-info-val" style="font-size:0.82rem;">
                <?php echo date('M d, Y H:i', strtotime($d['dispatched_at'])); ?>
            </div>
        </div>

        <?php if ($d['status'] !== 'transit' && $d['received_at']): ?>
        <div class="d-info-item">
            <div class="d-info-lbl">
                <i class="fas fa-<?php echo $d['status']==='received'?'circle-check':'circle-xmark'; ?>"></i>
                <?php echo ucfirst($d['status']); ?> At
            </div>
            <div class="d-info-val" style="font-size:0.82rem;color:<?php echo $d['status']==='received'?'var(--emerald)':'var(--rose)'; ?>">
                <?php echo date('M d, Y H:i', strtotime($d['received_at'])); ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($d['status'] === 'rejected' && $d['rejection_reason']): ?>
        <div class="d-info-item" style="grid-column:1/-1;">
            <div class="d-info-lbl"><i class="fas fa-comment-exclamation"></i> Rejection Reason</div>
            <div class="d-info-val" style="color:var(--rose);font-size:0.82rem;">
                <?php echo htmlspecialchars($d['rejection_reason']); ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($d['notes']): ?>
        <div class="d-info-item" style="grid-column:1/-1;">
            <div class="d-info-lbl"><i class="fas fa-note-sticky"></i> Notes</div>
            <div class="d-info-val" style="font-size:0.82rem;color:var(--txt-2);">
                <?php echo htmlspecialchars($d['notes']); ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>
<?php endforeach; ?>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div class="data-card">
    <div class="pg-wrap">
        <div class="pg-info">
            Showing <strong><?php echo $offset+1; ?>–<?php echo min($offset+$perPage,$totalRows); ?></strong>
            of <strong><?php echo number_format($totalRows); ?></strong>
        </div>
        <div class="pg-btns">
            <?php $qs = http_build_query(['status'=>$status_filter,'search'=>$search,'page'=>$page-1]); ?>
            <a href="?<?php echo $qs; ?>" class="pg-btn <?php echo $page<=1?'disabled':''; ?>">
                <i class="fas fa-chevron-left"></i>
            </a>
            <?php for ($p=1;$p<=$totalPages;$p++):
                $qs = http_build_query(['status'=>$status_filter,'search'=>$search,'page'=>$p]);
            ?>
            <a href="?<?php echo $qs; ?>" class="pg-btn <?php echo $p==$page?'active':''; ?>"><?php echo $p; ?></a>
            <?php endfor; ?>
            <?php $qs = http_build_query(['status'=>$status_filter,'search'=>$search,'page'=>$page+1]); ?>
            <a href="?<?php echo $qs; ?>" class="pg-btn <?php echo $page>=$totalPages?'disabled':''; ?>">
                <i class="fas fa-chevron-right"></i>
            </a>
        </div>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- ══ UPDATE STATUS MODAL ══ -->
<div class="modal-overlay" id="outcomeModal">
    <div class="modal-box">
        <div class="modal-head">
            <div class="modal-head-ico" style="background:linear-gradient(135deg,var(--emerald),var(--cyan));">
                <i class="fas fa-flag-checkered"></i>
            </div>
            <div>
                <h5>Update Delivery Status</h5>
                <p id="outcomeLabel">Select the delivery outcome</p>
            </div>
        </div>
        <div class="modal-body">
            <form id="outcomeForm" method="POST" action="dispatch-action.php">
                <input type="hidden" name="action"      value="update_status">
                <input type="hidden" name="dispatch_id" id="outcomeDispatchId">
                <input type="hidden" name="bl_item_id"  id="outcomeItemId">
                <input type="hidden" name="redirect_id" id="outcomeRedirectId">
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
                    <label class="f-label">
                        Rejection Reason <span class="req">*</span>
                    </label>
                    <textarea name="rejection_reason" id="rejectionReason"
                              class="f-input" rows="3"
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
            <button type="button" class="btn-m btn-ghost" onclick="closeModal()">
                <i class="fas fa-xmark"></i> Cancel
            </button>
            <button type="button" class="btn-m btn-indigo"
                    id="confirmBtn" onclick="submitOutcome()" disabled>
                <i class="fas fa-check"></i> Confirm
            </button>
        </div>
    </div>
</div>

<script>
let selectedOutcome = '';

function openOutcome(dispatchId, containerNum, itemId, blId) {
    selectedOutcome = '';
    document.getElementById('outcomeDispatchId').value = dispatchId;
    document.getElementById('outcomeItemId').value     = itemId;
    document.getElementById('outcomeRedirectId').value = blId;
    document.getElementById('outcomeLabel').textContent = 'Container: ' + containerNum;
    document.getElementById('outcomeStatus').value     = '';
    document.getElementById('rejectionWrap').style.display = 'none';
    document.getElementById('rejectionReason').value  = '';
    document.getElementById('confirmBtn').disabled     = true;
    document.querySelectorAll('.outcome-btn').forEach(b => b.classList.remove('selected'));
    document.getElementById('outcomeModal').classList.add('show');
}

function closeModal() {
    document.getElementById('outcomeModal').classList.remove('show');
}

function selectOutcome(outcome) {
    selectedOutcome = outcome;
    document.getElementById('outcomeStatus').value    = outcome;
    document.getElementById('confirmBtn').disabled    = false;
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
    const btn = document.getElementById('confirmBtn');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
    btn.disabled  = true;
    document.getElementById('outcomeForm').submit();
}

document.getElementById('outcomeModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
</script>

<?php require_once 'includes/footer.php'; ?>