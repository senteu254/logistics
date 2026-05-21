<?php
require_once 'config/database.php';
$page_title = 'Dashboard';
require_once 'includes/header.php';

function getCount($pdo, $sql)
{
    try {
        $stmt = $pdo->query($sql);
        $result = $stmt->fetch(PDO::FETCH_NUM);
        return $result[0] ?? 0;
    } catch (PDOException $e) {
        error_log("Query error: " . $e->getMessage());
        return 0;
    }
}

$total_bls  = getCount($pdo, "SELECT COUNT(*) FROM bills_of_lading");
$active_bls = getCount($pdo, "SELECT COUNT(*) FROM bills_of_lading WHERE status='active'");
$total_bags = getCount($pdo, "SELECT COALESCE(SUM(number_of_bags),0) FROM bl_items");
$total_gw   = getCount($pdo, "SELECT COALESCE(SUM(gross_weight),0) FROM bl_items");

try {
    $recent = $pdo->query("
        SELECT b.id, b.bl_number, b.item_description, b.status, b.created_at,
               u.username,
               COUNT(i.id) AS containers,
               COALESCE(SUM(i.number_of_bags),0) AS total_bags,
               COALESCE(SUM(i.gross_weight),0) AS total_gw
        FROM bills_of_lading b
        LEFT JOIN users u ON b.user_id = u.id
        LEFT JOIN bl_items i ON b.id = i.bl_id
        GROUP BY b.id, b.bl_number, b.item_description, b.status, b.created_at, u.username
        ORDER BY b.created_at DESC
        LIMIT 8
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recent = [];
}
?>

<div class="page-content">

    <!-- Page Header -->
    <div class="page-header fade-up">
        <div class="page-header-left">
            <h1 class="page-header-title">Dashboard</h1>
            <p class="page-header-sub">
                <i class="fas fa-circle-dot me-1" style="color:var(--emerald);font-size:.6rem;"></i>
                Operations overview — <?= date('l, F j, Y'); ?>
            </p>
        </div>
        <a href="add-bl.php" class="btn-primary">
            <i class="fas fa-plus"></i> New Shipment
        </a>
    </div>

    <!-- Stats Grid -->
    <div class="row g-3 mb-4">

        <div class="col-6 col-xl-3 fade-up fade-up-1">
            <div class="stat-card stat-indigo">
                <div class="stat-ico">
                    <i class="fas fa-file-invoice"></i>
                </div>
                <div class="stat-body">
                    <div class="stat-val"><?= number_format($total_bls); ?></div>
                    <div class="stat-lbl">Total Shipments</div>
                </div>
            </div>
        </div>

        <div class="col-6 col-xl-3 fade-up fade-up-2">
            <div class="stat-card stat-emerald">
                <div class="stat-ico">
                    <i class="fas fa-circle-check"></i>
                </div>
                <div class="stat-body">
                    <div class="stat-val"><?= number_format($active_bls); ?></div>
                    <div class="stat-lbl">Active</div>
                </div>
            </div>
        </div>

        <div class="col-6 col-xl-3 fade-up fade-up-3">
            <div class="stat-card stat-amber">
                <div class="stat-ico">
                    <i class="fas fa-boxes-stacked"></i>
                </div>
                <div class="stat-body">
                    <div class="stat-val"><?= number_format($total_bags); ?></div>
                    <div class="stat-lbl">Total Bags</div>
                </div>
            </div>
        </div>

        <div class="col-6 col-xl-3 fade-up fade-up-4">
            <div class="stat-card stat-rose">
                <div class="stat-ico">
                    <i class="fas fa-weight-hanging"></i>
                </div>
                <div class="stat-body">
                    <div class="stat-val"><?= number_format($total_gw, 2); ?></div>
                    <div class="stat-lbl">Gross Weight (MT)</div>
                </div>
            </div>
        </div>

    </div>

    <!-- Recent Shipments -->
    <div class="data-card fade-up fade-up-5">

        <div class="card-header-bar">
            <div class="card-title">
                <div class="card-title-ico" style="background:rgba(79,70,229,.1);color:var(--indigo);">
                    <i class="fas fa-clock-rotate-left"></i>
                </div>
                Recent Shipments
            </div>
            <a href="manage-bl.php" class="btn-ghost" style="font-size:.8rem;padding:7px 14px;">
                View all <i class="fas fa-arrow-right ms-1" style="font-size:.7rem;"></i>
            </a>
        </div>

        <?php if (empty($recent)): ?>

            <div class="empty-state">
                <div class="empty-state-ico">
                    <i class="fas fa-box-open"></i>
                </div>
                <h5>No shipments yet</h5>
                <p>Create your first Bill of Lading to get started</p>
                <a href="add-bl.php" class="btn-primary">
                    <i class="fas fa-plus"></i> Add First B/L
                </a>
            </div>

        <?php else: ?>

            <!-- Desktop Table -->
            <div class="desktop-only">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>B/L Number</th>
                            <th>Description</th>
                            <th>Containers</th>
                            <th>Bags</th>
                            <th>Gross (MT)</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th style="width:96px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent as $bl): ?>
                            <tr>
                                <td>
                                    <a href="view-bl.php?id=<?= $bl['id']; ?>" class="td-link">
                                        <?= htmlspecialchars($bl['bl_number']); ?>
                                    </a>
                                </td>
                                <td style="max-width:200px;color:var(--txt-3);font-size:.825rem;">
                                    <?= htmlspecialchars(mb_strimwidth($bl['item_description'], 0, 40, '…')); ?>
                                </td>
                                <td class="fw-600"><?= $bl['containers']; ?></td>
                                <td class="fw-600"><?= number_format($bl['total_bags']); ?></td>
                                <td class="fw-600"><?= number_format($bl['total_gw'], 3); ?></td>
                                <td>
                                    <?php
                                    $s = $bl['status'];
                                    $cls = $s === 'active' ? 'badge-active' : ($s === 'draft' ? 'badge-draft' : 'badge-completed');
                                    $ico = $s === 'active' ? 'circle-check' : ($s === 'draft' ? 'circle-dashed' : 'flag-checkered');
                                    ?>
                                    <span class="badge-status <?= $cls; ?>">
                                        <i class="fas fa-<?= $ico; ?>"></i>
                                        <?= ucfirst($s); ?>
                                    </span>
                                </td>
                                <td style="color:var(--txt-3);font-size:.8rem;">
                                    <?= date('M d, Y', strtotime($bl['created_at'])); ?>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a href="view-bl.php?id=<?= $bl['id']; ?>" class="btn-icon" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit-bl.php?id=<?= $bl['id']; ?>" class="btn-icon" title="Edit">
                                            <i class="fas fa-pen"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile Cards -->
            <div class="mobile-only" style="padding:12px;">
                <?php foreach ($recent as $bl):
                    $s = $bl['status'];
                    $cls = $s === 'active' ? 'badge-active' : ($s === 'draft' ? 'badge-draft' : 'badge-completed');
                ?>
                    <div class="mobile-card">
                        <div class="mobile-card-head">
                            <div>
                                <a href="view-bl.php?id=<?= $bl['id']; ?>" class="td-link" style="font-size:.9rem;">
                                    <?= htmlspecialchars($bl['bl_number']); ?>
                                </a>
                                <div style="font-size:.75rem;color:var(--txt-3);margin-top:2px;line-height:1.4;">
                                    <?= htmlspecialchars(mb_strimwidth($bl['item_description'], 0, 44, '…')); ?>
                                </div>
                            </div>
                            <span class="badge-status <?= $cls; ?>">
                                <?= ucfirst($s); ?>
                            </span>
                        </div>
                        <div class="mobile-card-body">
                            <div class="mobile-card-grid">
                                <div class="mc-field">
                                    <div class="mc-label">Containers</div>
                                    <div class="mc-val"><?= $bl['containers']; ?></div>
                                </div>
                                <div class="mc-field">
                                    <div class="mc-label">Total Bags</div>
                                    <div class="mc-val"><?= number_format($bl['total_bags']); ?></div>
                                </div>
                                <div class="mc-field">
                                    <div class="mc-label">Gross (MT)</div>
                                    <div class="mc-val"><?= number_format($bl['total_gw'], 2); ?></div>
                                </div>
                                <div class="mc-field">
                                    <div class="mc-label">Date</div>
                                    <div class="mc-val" style="font-size:.8rem;"><?= date('M d, Y', strtotime($bl['created_at'])); ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="mobile-card-foot">
                            <span style="font-size:.75rem;color:var(--txt-3);">
                                <i class="fas fa-user me-1"></i><?= htmlspecialchars($bl['username'] ?? 'System'); ?>
                            </span>
                            <div class="d-flex gap-2">
                                <a href="view-bl.php?id=<?= $bl['id']; ?>" class="btn-icon">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="edit-bl.php?id=<?= $bl['id']; ?>" class="btn-icon">
                                    <i class="fas fa-pen"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>
    </div>

</div>

<?php require_once 'includes/footer.php'; ?>