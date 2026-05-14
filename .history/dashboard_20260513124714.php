<?php
require_once 'config/database.php';
$page_title = 'Dashboard';
require_once 'includes/header.php';

$total_bls  = $pdo->query("SELECT COUNT(*) FROM bills_of_lading")->fetchColumn();
$active_bls = $pdo->query("SELECT COUNT(*) FROM bills_of_lading WHERE status='active'")->fetchColumn();
$total_bags = $pdo->query("SELECT COALESCE(SUM(number_of_bags),0) FROM bl_items")->fetchColumn();
$total_gw   = $pdo->query("SELECT COALESCE(SUM(gross_weight),0) FROM bl_items")->fetchColumn();

$recent = $pdo->query("
    SELECT b.*, u.username,
           COUNT(i.id)            AS containers,
           COALESCE(SUM(i.number_of_bags),0) AS total_bags,
           COALESCE(SUM(i.gross_weight),0)   AS total_gw
    FROM   bills_of_lading b
    LEFT JOIN users u    ON b.user_id = u.id
    LEFT JOIN bl_items i ON b.id = i.bl_id
    GROUP BY b.id
    ORDER BY b.created_at DESC
    LIMIT 8
")->fetchAll();
?>

<!-- Header -->
<div class="page-header d-flex align-items-start justify-content-between flex-wrap gap-3">
    <div>
        <h1 class="page-title">Dashboard</h1>
        <p class="page-sub">
            Welcome back, <strong><?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?></strong>
        </p>
    </div>
    <a href="add-bl.php" class="btn-m btn-indigo">
        <i class="fas fa-circle-plus"></i> New B/L
    </a>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-sm-6">
        <div class="stat-card c-indigo">
            <div class="stat-ico c-indigo"><i class="fas fa-file-invoice"></i></div>
            <div class="stat-val"><?php echo number_format($total_bls); ?></div>
            <div class="stat-lbl">Total Bill of Lading</div>
            <span class="stat-badge"><i class="fas fa-layer-group"></i> All Time</span>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="stat-card c-emerald">
            <div class="stat-ico c-emerald"><i class="fas fa-circle-check"></i></div>
            <div class="stat-val"><?php echo number_format($active_bls); ?></div>
            <div class="stat-lbl">Active B/Ls</div>
            <span class="stat-badge"><i class="fas fa-bolt"></i> In Progress</span>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="stat-card c-amber">
            <div class="stat-ico c-amber"><i class="fas fa-boxes-stacked"></i></div>
            <div class="stat-val"><?php echo number_format($total_bags); ?></div>
            <div class="stat-lbl">Total Bags</div>
            <span class="stat-badge"><i class="fas fa-cubes"></i> All Containers</span>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="stat-card c-rose">
            <div class="stat-ico c-rose"><i class="fas fa-weight-hanging"></i></div>
            <div class="stat-val"><?php echo number_format($total_gw, 3); ?></div>
             <div class="stat-lbl">Total Gross Weight (MT)</div>
            <span class="stat-badge"><i class="fas fa-scale-balanced"></i> Metric Tons</span>
        </div>
    </div>
</div>

<!-- Recent Table -->
<div class="data-card">
    <div class="data-card-head">
        <div class="data-card-title">
            <i class="fas fa-clock-rotate-left"></i>
            Recent Bills of Lading
        </div>
        <a href="manage-bl.php" class="btn-m btn-ghost btn-sm">
            View All <i class="fas fa-arrow-right"></i>
        </a>
    </div>

    <?php if (empty($recent)): ?>
    <div class="empty">
        <div class="empty-ico"><i class="fas fa-file-circle-xmark"></i></div>
        <h5>No Bills of Lading Yet</h5>
        <p>Get started by creating your first B/L.</p>
        <a href="add-bl.php" class="btn-m btn-indigo mt-3 mx-auto">
            <i class="fas fa-circle-plus"></i> Create First B/L
        </a>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="tbl">
            <thead>
                <tr>
                    <th>B/L Number</th>
                    <th>Description</th>
                    <th>Containers</th>
                    <th>Total Bags</th>
                    <th>Gross Weight</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent as $bl): ?>
                <tr>
                    <td>
                        <span style="font-weight:700;color:var(--indigo);font-family:monospace;font-size:0.82rem;">
                            <?php echo htmlspecialchars($bl['bl_number']); ?>
                        </span>
                    </td>
                    <td>
                        <span style="color:var(--txt-2);font-size:0.83rem;display:block;max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                            <?php echo htmlspecialchars($bl['item_description']); ?>
                        </span>
                    </td>
                    <td>
                        <span class="bdg bdg-active">
                            <i class="fas fa-circle" style="font-size:0.4rem;"></i>
                            <?php echo $bl['containers']; ?>
                        </span>
                    </td>
                    <td style="font-weight:600;"><?php echo number_format($bl['total_bags']); ?></td>
                  <td style="font-weight:600;white-space:nowrap;"><?php echo number_format($bl['total_gw'], 3); ?> MT</td>
                   <td>
                        <span class="bdg bdg-<?php echo $bl['status']; ?>">
                            <i class="fas fa-circle" style="font-size:0.4rem;"></i>
                            <?php echo ucfirst($bl['status']); ?>
                        </span>
                    </td>
                    <td style="color:var(--txt-3);font-size:0.8rem;">
                        <?php echo date('M d, Y', strtotime($bl['created_at'])); ?>
                    </td>
                    <td>
                        <div style="display:flex;gap:5px;">
                            <a href="view-bl.php?id=<?php echo $bl['id']; ?>" class="btn-m btn-ghost btn-sm">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="edit-bl.php?id=<?php echo $bl['id']; ?>" class="btn-m btn-ghost btn-sm">
                                <i class="fas fa-pen"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>