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

<div class="container-fluid py-4">

    <!-- HEADER -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h3 class="fw-bold mb-0">Dashboard</h3>
            <small class="text-muted">Operations Center</small>
        </div>

        <a href="add-bl.php" class="btn btn-primary">
            <i class="fas fa-plus me-1"></i> New Shipment
        </a>
    </div>

    <!-- STATS -->
    <div class="row g-3 mb-4">

        <div class="col-6 col-md-3">
            <div class="card shadow-sm border-0 text-center p-3">
                <div class="text-primary fs-4 mb-2"><i class="fas fa-file-invoice"></i></div>
                <h5 class="fw-bold mb-0"><?= number_format($total_bls); ?></h5>
                <small class="text-muted">Total Shipments</small>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="card shadow-sm border-0 text-center p-3">
                <div class="text-success fs-4 mb-2"><i class="fas fa-circle-check"></i></div>
                <h5 class="fw-bold mb-0"><?= number_format($active_bls); ?></h5>
                <small class="text-muted">Active</small>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="card shadow-sm border-0 text-center p-3">
                <div class="text-warning fs-4 mb-2"><i class="fas fa-boxes-stacked"></i></div>
                <h5 class="fw-bold mb-0"><?= number_format($total_bags); ?></h5>
                <small class="text-muted">Total Bags</small>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="card shadow-sm border-0 text-center p-3">
                <div class="text-danger fs-4 mb-2"><i class="fas fa-weight-hanging"></i></div>
                <h5 class="fw-bold mb-0"><?= number_format($total_gw, 3); ?></h5>
                <small class="text-muted">Gross Weight (MT)</small>
            </div>
        </div>

    </div>

    <!-- RECENT -->
    <div class="card shadow-sm border-0">

        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <strong>Recent Shipments</strong>
            <a href="manage-bl.php" class="btn btn-sm btn-outline-primary">
                View All
            </a>
        </div>

        <?php if (empty($recent)): ?>

            <div class="text-center py-5">
                <i class="fas fa-box-open fa-2x text-muted mb-2"></i>
                <p class="text-muted">No shipments yet</p>
                <a href="add-bl.php" class="btn btn-primary btn-sm">
                    Add BL
                </a>
            </div>

        <?php else: ?>

        <!-- DESKTOP TABLE -->
        <div class="table-responsive d-none d-md-block">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>B/L</th>
                        <th>Description</th>
                        <th>Containers</th>
                        <th>Bags</th>
                        <th>Gross</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th width="120">Actions</th>
                    </tr>
                </thead>

                <tbody>
                <?php foreach ($recent as $bl): ?>
                    <tr>

                        <td class="fw-bold text-primary">
                            <?= htmlspecialchars($bl['bl_number']); ?>
                        </td>

                        <td class="text-muted small">
                            <?= htmlspecialchars($bl['item_description']); ?>
                        </td>

                        <td><?= $bl['containers']; ?></td>
                        <td><?= number_format($bl['total_bags']); ?></td>
                        <td><?= number_format($bl['total_gw'], 3); ?> MT</td>

                        <td>
                            <span class="badge bg-<?= $bl['status']=='active'?'success':($bl['status']=='draft'?'secondary':'primary'); ?>">
                                <?= ucfirst($bl['status']); ?>
                            </span>
                        </td>

                        <td class="small text-muted">
                            <?= date('M d, Y', strtotime($bl['created_at'])); ?>
                        </td>

                        <td>
                            <div class="d-flex gap-1">
                                <a href="view-bl.php?id=<?= $bl['id']; ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="edit-bl.php?id=<?= $bl['id']; ?>" class="btn btn-sm btn-outline-dark">
                                    <i class="fas fa-pen"></i>
                                </a>
                            </div>
                        </td>

                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- MOBILE CARDS -->
        <div class="d-md-none p-3">

            <?php foreach ($recent as $bl): ?>

            <div class="border rounded p-3 mb-3">

                <div class="d-flex justify-content-between">

                    <div>
                        <strong><?= htmlspecialchars($bl['bl_number']); ?></strong>
                        <div class="text-muted small">
                            <?= htmlspecialchars($bl['item_description']); ?>
                        </div>
                    </div>

                    <span class="badge bg-<?= $bl['status']=='active'?'success':($bl['status']=='draft'?'secondary':'primary'); ?>">
                        <?= ucfirst($bl['status']); ?>
                    </span>

                </div>

                <hr>

                <div class="row text-center">

                    <div class="col-4">
                        <small class="text-muted">Containers</small><br>
                        <?= $bl['containers']; ?>
                    </div>

                    <div class="col-4">
                        <small class="text-muted">Bags</small><br>
                        <?= number_format($bl['total_bags']); ?>
                    </div>

                    <div class="col-4">
                        <small class="text-muted">Gross</small><br>
                        <?= number_format($bl['total_gw'], 2); ?>
                    </div>

                </div>

                <hr>

                <div class="d-flex justify-content-between align-items-center">

                    <small class="text-muted">
                        <?= date('M d, Y', strtotime($bl['created_at'])); ?>
                    </small>

                    <div class="d-flex gap-2">
                        <a href="view-bl.php?id=<?= $bl['id']; ?>" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-eye"></i>
                        </a>
                        <a href="edit-bl.php?id=<?= $bl['id']; ?>" class="btn btn-sm btn-outline-dark">
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