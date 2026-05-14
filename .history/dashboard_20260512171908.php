<?php
require_once 'config/database.php';
$page_title = 'Dashboard';
require_once 'includes/header.php';

// Fetch stats
$total_bls  = $pdo->query("SELECT COUNT(*) FROM bills_of_lading")->fetchColumn();
$active_bls = $pdo->query("SELECT COUNT(*) FROM bills_of_lading WHERE status='active'")->fetchColumn();
$total_bags = $pdo->query("SELECT COALESCE(SUM(number_of_bags),0) FROM bl_items")->fetchColumn();
$total_gw   = $pdo->query("SELECT COALESCE(SUM(gross_weight),0) FROM bl_items")->fetchColumn();

// Recent BLs
$recent = $pdo->query("
    SELECT b.*, u.username,
           COUNT(i.id) as item_count,
           SUM(i.number_of_bags) as total_bags,
           SUM(i.gross_weight) as total_gw
    FROM bills_of_lading b
    LEFT JOIN users u ON b.user_id = u.id
    LEFT JOIN bl_items i ON b.id = i.bl_id
    GROUP BY b.id
    ORDER BY b.created_at DESC
    LIMIT 8
")->fetchAll();
?>

<!-- Page Header -->
<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3">
    <div>
        <h1 class="page-title">Dashboard</h1>
        <p class="page-subtitle">
            Welcome back, <strong><?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?></strong>
            — Here's what's happening today.
        </p>
    </div>
    <a href="add-bl.php" class="btn-modern btn-indigo">
        <i class="fas fa-circle-plus"></i>
        New B/L
    </a>
</div>

<!-- Stat Cards -->
<div class="row g-4 mb-4">

    <div class="col-xl-3 col-sm-6">
        <div class="stat-card indigo">
            <div class="stat-icon indigo">
                <i class="fas fa-file-invoice"></i>
            </div>
            <div class="stat-value"><?php echo number_format($total_bls); ?></div>
            <div class="stat-label">Total Bills of Lading</div>
            <span class="stat-change up"><i class="fas fa-arrow-trend-up"></i> All Time</span>
        </div>
    </div>

    <div class="col-xl-3 col-sm-6">
        <div class="stat-card emerald">
            <div class="stat-icon emerald">
                <i class="fas fa-circle-check"></i>
            </div>
            <div class="stat-value"><?php echo number_format($active_bls); ?></div>
            <div class="stat-label">Active B/Ls</div>
            <span class="stat-change up"><i class="fas fa-arrow-trend-up"></i> In Progress</span>
        </div>
    </div>

    <div class="col-xl-3 col-sm-6">
        <div class="stat-card amber">
            <div class="stat-icon amber">
                <i class="fas fa-boxes-stacked"></i>
            </div>
            <div class="stat-value"><?php echo number_format($total_bags); ?></div>
            <div class="stat-label">Total Bags</div>
            <span class="stat-change up"><i class="fas fa-cubes"></i> All Containers</span>
        </div>
    </div>

    <div class="col-xl-3 col-sm-6">
        <div class="stat-card rose">
            <div class="stat-icon rose">
                <i class="fas fa-weight-hanging"></i>
            </div>
            <div class="stat-value"><?php echo number_format($total_gw / 1000, 1); ?>t</div>
            <div class="stat-label">Total Gross Weight</div>
            <span class="stat-change up"><i class="fas fa-scale-balanced"></i> Metric Tons</span>
        </div>
    </div>

</div>

<!-- Recent BLs Table -->
<div class="data-card">
    <div class="data-card-header">
        <div class="data-card-title">
            <i class="fas fa-clock-rotate-left"></i>
            Recent Bills of Lading
        </div>
        <a href="manage-bl.php" class="btn-modern btn-outline btn-sm">
            View All <i class="fas fa-arrow-right"></i>
        </a>
    </div>

    <?php if (empty($recent)): ?>
        <div class="empty-state">
            <div class="empty-state-icon"><i class="fas fa-file-circle-xmark"></i></div>
            <h5>No Bills of Lading Yet</h5>
            <p>Get started by creating your first B/L.</p>
            <a href="add-bl.php" class="btn-modern btn-indigo mt-3">
                <i class="fas fa-circle-plus"></i> Create First B/L
            </a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table-modern">
                <thead>
                    <tr>
                        <th>B/L Number</th>
                        <th>Item Description</th>
                        <th>Containers</th>
                        <th>Total Bags</th>
                        <th>Gross Weight</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent as $bl): ?>
                    <tr>
                        <td>
                            <span style="font-weight:700; color:var(--indigo); font-family:monospace;">
                                <?php echo htmlspecialchars($bl['bl_number']); ?>
                            </span>
                        </td>
                        <td style="max-width:200px;">
                            <span style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis; display:block; max-width:200px; color:var(--text-secondary);">
                                <?php echo htmlspecialchars($bl['item_description']); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge-modern badge-active">
                                <i class="fas fa-container-storage"></i>
                                <?php echo $bl['item_count']; ?>
                            </span>
                        </td>
                        <td style="font-weight:600;"><?php echo number_format($bl['total_bags']); ?></td>
                        <td style="font-weight:600;"><?php echo number_format($bl['total_gw'], 2); ?> kg</td>
                        <td>
                            <span class="badge-modern badge-<?php echo $bl['status']; ?>">
                                <i class="fas fa-circle" style="font-size:0.4rem;"></i>
                                <?php echo ucfirst($bl['status']); ?>
                            </span>
                        </td>
                        <td style="color:var(--text-muted); font-size:0.82rem;">
                            <?php echo date('M d, Y', strtotime($bl['created_at'])); ?>
                        </td>
                        <td>
                            <div style="display:flex; gap:6px;">
                                <a href="view-bl.php?id=<?php echo $bl['id']; ?>"
                                   class="btn-modern btn-outline btn-sm">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="edit-bl.php?id=<?php echo $bl['id']; ?>"
                                   class="btn-modern btn-outline btn-sm">
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