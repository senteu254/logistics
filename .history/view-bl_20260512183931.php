<?php
require_once 'config/database.php';
$page_title = 'View B/L';

$id = intval($_GET['id'] ?? 0);
if (!$id) { header('Location: manage-bl.php'); exit(); }

// Fetch BL
$stmt = $pdo->prepare("
    SELECT b.*, u.username, u.full_name
    FROM bills_of_lading b
    LEFT JOIN users u ON b.user_id = u.id
    WHERE b.id = ?
");
$stmt->execute([$id]);
$bl = $stmt->fetch();
if (!$bl) { header('Location: manage-bl.php'); exit(); }

// Fetch items
$items = $pdo->prepare("SELECT * FROM bl_items WHERE bl_id = ? ORDER BY id ASC");
$items->execute([$id]);
$items = $items->fetchAll();

// Totals
$totalBags  = array_sum(array_column($items,'number_of_bags'));
$totalGross = array_sum(array_column($items,'gross_weight'));
$totalNet   = array_sum(array_column($items,'net_weight'));

$page_title = 'View — ' . $bl['bl_number'];
require_once 'includes/header.php';
?>

<style>
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

.view-hero-bl {
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    opacity: 0.7;
    margin-bottom: 8px;
}

.view-hero-num {
    font-size: 2rem;
    font-weight: 800;
    letter-spacing: -0.5px;
    margin-bottom: 0.5rem;
    font-family: 'Courier New', monospace;
}

.view-hero-desc {
    font-size: 0.9rem;
    opacity: 0.8;
    max-width: 500px;
    margin-bottom: 1.25rem;
}

.view-hero-meta {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
    font-size: 0.8rem;
    opacity: 0.75;
}

.view-hero-meta span {
    display: flex;
    align-items: center;
    gap: 6px;
}

/* Summary cards inside view */
.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px,1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.summary-tile {
    background: white;
    border: 1px solid var(--border);
    border-radius: var(--r-md);
    padding: 1.25rem;
    text-align: center;
    transition: var(--t);
}

.summary-tile:hover {
    box-shadow: var(--shadow-md);
    transform: translateY(-2px);
}

.summary-tile-ico {
    font-size: 1.4rem;
    margin-bottom: 0.6rem;
}

.summary-tile-val {
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--txt-1);
    letter-spacing: -0.5px;
    line-height: 1;
    margin-bottom: 4px;
}

.summary-tile-lbl {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--txt-2);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

/* Items Table header */
.items-head-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1rem;
    flex-wrap: wrap;
    gap: 10px;
}

/* Print styles */
@media print {
    .sidebar, .top-nav, .page-header .btn-m,
    .no-print { display: none !important; }
    .main-wrap { margin-left: 0 !important; }
    .page-content { padding: 0 !important; }
    .view-hero { -webkit-print-color-adjust: exact; }
    .data-card { box-shadow: none; border: 1px solid #ddd; }
}

/* Status pill */
.status-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.78rem;
    font-weight: 700;
    backdrop-filter: blur(4px);
}

.status-pill.active    { background: rgba(16,185,129,0.2);  color: #6ee7b7; }
.status-pill.draft     { background: rgba(245,158,11,0.2);  color: #fcd34d; }
.status-pill.completed { background: rgba(255,255,255,0.2); color: white; }

/* Container item card (mobile-friendly alternative) */
.container-row-card {
    background: var(--bg);
    border: 1.5px solid var(--border);
    border-radius: var(--r-md);
    overflow: hidden;
    transition: var(--t);
}

.container-row-card:hover {
    border-color: var(--indigo);
    box-shadow: 0 3px 16px rgba(99,102,241,0.08);
}

.container-row-num {
    background: linear-gradient(135deg, var(--indigo), var(--violet));
    color: white;
    padding: 10px 16px;
    font-size: 0.78rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 8px;
}

.container-row-body {
    padding: 1.1rem 1.25rem;
}

.detail-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px,1fr));
    gap: 1rem;
}

.detail-item-lbl {
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: var(--txt-3);
    margin-bottom: 3px;
}

.detail-item-val {
    font-size: 0.92rem;
    font-weight: 600;
    color: var(--txt-1);
}

.detail-item-val.mono {
    font-family: 'Courier New', monospace;
    color: var(--indigo);
    font-size: 0.95rem;
}

.detail-item-val.muted {
    color: var(--txt-3);
    font-style: italic;
    font-weight: 400;
}
</style>

<!-- Page Header -->
<div class="page-header d-flex align-items-start justify-content-between flex-wrap gap-3 no-print">
    <div>
        <h1 class="page-title">Bill of Lading</h1>
        <p class="page-sub">Detailed view of all container information</p>
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

<!-- Hero Banner -->
<div class="view-hero">
    <div class="view-hero-bl">Bill of Lading</div>
    <div class="view-hero-num"><?php echo htmlspecialchars($bl['bl_number']); ?></div>
    <div class="view-hero-desc"><?php echo htmlspecialchars($bl['item_description']); ?></div>
    <div class="view-hero-meta">
        <span><i class="fas fa-calendar"></i> <?php echo date('F d, Y', strtotime($bl['created_at'])); ?></span>
        <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($bl['full_name'] ?? $bl['username']); ?></span>
        <span><i class="fas fa-clock"></i> <?php echo date('H:i', strtotime($bl['created_at'])); ?></span>
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
        <div class="summary-tile-ico" style="color:var(--indigo);">🚢</div>
        <div class="summary-tile-val"><?php echo count($items); ?></div>
        <div class="summary-tile-lbl">Containers</div>
    </div>
    <div class="summary-tile">
        <div class="summary-tile-ico" style="color:var(--emerald);">📦</div>
        <div class="summary-tile-val"><?php echo number_format($totalBags); ?></div>
        <div class="summary-tile-lbl">Total Bags</div>
    </div>
    <div class="summary-tile">
        <div class="summary-tile-ico" style="color:var(--amber);">⚖️</div>
        <div class="summary-tile-val"><?php echo number_format($totalGross/1000,2); ?>t</div>
        <div class="summary-tile-lbl">Gross Weight</div>
    </div>
    <div class="summary-tile">
        <div class="summary-tile-ico" style="color:var(--rose);">🏋️</div>
        <div class="summary-tile-val"><?php echo number_format($totalNet/1000,2); ?>t</div>
        <div class="summary-tile-lbl">Net Weight</div>
    </div>
    <div class="summary-tile">
        <div class="summary-tile-ico" style="color:var(--cyan);">📊</div>
        <div class="summary-tile-val"><?php echo number_format(($totalGross-$totalNet)/1000,2); ?>t</div>
        <div class="summary-tile-lbl">Tare Weight</div>
    </div>
</div>

<!-- Container Items -->
<div class="data-card">
    <div class="data-card-head">
        <div class="data-card-title">
            <i class="fas fa-boxes-stacked"></i>
            Container Details
            <span class="bdg bdg-active" style="font-size:0.72rem;">
                <?php echo count($items); ?> container<?php echo count($items)!=1?'s':''; ?>
            </span>
        </div>
        <!-- Toggle View -->
        <div class="d-flex gap-2 no-print">
            <button onclick="setView('table')" id="btnTable" class="btn-m btn-ghost btn-sm active-view">
                <i class="fas fa-table"></i>
            </button>
            <button onclick="setView('cards')" id="btnCards" class="btn-m btn-ghost btn-sm">
                <i class="fas fa-grip"></i>
            </button>
        </div>
    </div>

    <!-- TABLE VIEW -->
    <div id="viewTable">
        <div class="table-responsive">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Container No.</th>
                        <th>Agent Seal</th>
                        <th>No. of Bags</th>
                        <th>SGS Seal</th>
                        <th>Gross Weight</th>
                        <th>Net Weight</th>
                        <th>Tare Weight</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $i => $item): ?>
                    <tr>
                        <td style="color:var(--txt-3);font-size:0.78rem;font-weight:600;"><?php echo $i+1; ?></td>
                        <td>
                            <span style="font-family:monospace;font-weight:700;color:var(--indigo);font-size:0.88rem;">
                                <?php echo htmlspecialchars($item['container_number']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($item['agent_seal_number']): ?>
                                <span style="font-weight:600;"><?php echo htmlspecialchars($item['agent_seal_number']); ?></span>
                            <?php else: ?>
                                <span style="color:var(--txt-3);font-style:italic;">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-weight:700;"><?php echo number_format($item['number_of_bags']); ?></td>
                        <td>
                            <?php if ($item['sgs_seal_number']): ?>
                                <span style="font-weight:600;"><?php echo htmlspecialchars($item['sgs_seal_number']); ?></span>
                            <?php else: ?>
                                <span style="color:var(--txt-3);font-style:italic;">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-weight:600;white-space:nowrap;"><?php echo number_format($item['gross_weight'],2); ?> kg</td>
                        <td style="font-weight:600;white-space:nowrap;"><?php echo number_format($item['net_weight'],2); ?> kg</td>
                        <td style="font-weight:600;white-space:nowrap;color:var(--txt-2);">
                            <?php echo number_format($item['gross_weight']-$item['net_weight'],2); ?> kg
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:#f8fafc;border-top:2px solid var(--border);">
                        <td colspan="3" style="padding:12px 15px;font-weight:700;color:var(--txt-1);font-size:0.85rem;">
                            TOTALS
                        </td>
                        <td style="padding:12px 15px;font-weight:800;color:var(--indigo);">
                            <?php echo number_format($totalBags); ?>
                        </td>
                        <td></td>
                        <td style="padding:12px 15px;font-weight:800;color:var(--amber);white-space:nowrap;">
                            <?php echo number_format($totalGross,2); ?> kg
                        </td>
                        <td style="padding:12px 15px;font-weight:800;color:var(--emerald);white-space:nowrap;">
                            <?php echo number_format($totalNet,2); ?> kg
                        </td>
                        <td style="padding:12px 15px;font-weight:800;color:var(--txt-2);white-space:nowrap;">
                            <?php echo number_format($totalGross-$totalNet,2); ?> kg
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- CARDS VIEW -->
    <div id="viewCards" style="display:none;padding:1.5rem;">
        <div style="display:flex;flex-direction:column;gap:12px;">
            <?php foreach ($items as $i => $item): ?>
            <div class="container-row-card">
                <div class="container-row-num">
                    <i class="fas fa-ship"></i>
                    Container #<?php echo $i+1; ?>
                    &nbsp;·&nbsp;
                    <span style="font-family:monospace;font-size:0.9rem;">
                        <?php echo htmlspecialchars($item['container_number']); ?>
                    </span>
                </div>
                <div class="container-row-body">
                    <div class="detail-grid">
                        <div>
                            <div class="detail-item-lbl">Container No.</div>
                            <div class="detail-item-val mono"><?php echo htmlspecialchars($item['container_number']); ?></div>
                        </div>
                        <div>
                            <div class="detail-item-lbl">Agent Seal No.</div>
                            <div class="detail-item-val <?php echo $item['agent_seal_number']?'':'muted'; ?>">
                                <?php echo $item['agent_seal_number'] ? htmlspecialchars($item['agent_seal_number']) : 'Not provided'; ?>
                            </div>
                        </div>
                        <div>
                            <div class="detail-item-lbl">No. of Bags</div>
                            <div class="detail-item-val" style="color:var(--emerald);"><?php echo number_format($item['number_of_bags']); ?></div>
                        </div>
                        <div>
                            <div class="detail-item-lbl">SGS Seal No.</div>
                            <div class="detail-item-val <?php echo $item['sgs_seal_number']?'':'muted'; ?>">
                                <?php echo $item['sgs_seal_number'] ? htmlspecialchars($item['sgs_seal_number']) : 'Not provided'; ?>
                            </div>
                        </div>
                        <div>
                            <div class="detail-item-lbl">Gross Weight</div>
                            <div class="detail-item-val" style="color:var(--amber);"><?php echo number_format($item['gross_weight'],2); ?> kg</div>
                        </div>
                        <div>
                            <div class="detail-item-lbl">Net Weight</div>
                            <div class="detail-item-val" style="color:var(--rose);"><?php echo number_format($item['net_weight'],2); ?> kg</div>
                        </div>
                        <div>
                            <div class="detail-item-lbl">Tare Weight</div>
                            <div class="detail-item-val" style="color:var(--txt-2);">
                                <?php echo number_format($item['gross_weight']-$item['net_weight'],2); ?> kg
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

</div>

<script>
function setView(v) {
    document.getElementById('viewTable').style.display = v==='table' ? 'block' : 'none';
    document.getElementById('viewCards').style.display = v==='cards' ? 'block' : 'none';
    document.getElementById('btnTable').style.background = v==='table' ? 'var(--bg)' : '';
    document.getElementById('btnCards').style.background = v==='cards' ? 'var(--bg)' : '';
}
</script>

<?php require_once 'includes/footer.php'; ?>