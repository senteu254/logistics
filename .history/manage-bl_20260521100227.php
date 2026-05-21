<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'config/database.php';

$page_title = 'Manage Shipment';

/*
|--------------------------------------------------------------------------
| DELETE RECORD
|--------------------------------------------------------------------------
*/
if (isset($_POST['delete_id'])) {

    $stmt = $pdo->prepare("DELETE FROM bills_of_lading WHERE id = ?");
    $stmt->execute([(int)$_POST['delete_id']]);

    header('Location: manage-bl.php?deleted=1');
    exit();
}

/*
|--------------------------------------------------------------------------
| UPDATE STATUS
|--------------------------------------------------------------------------
*/
if (isset($_POST['status_id']) && isset($_POST['new_status'])) {

    $allowedStatuses = ['active', 'draft', 'completed'];

    if (in_array($_POST['new_status'], $allowedStatuses)) {

        $stmt = $pdo->prepare("
            UPDATE bills_of_lading
            SET status = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $_POST['new_status'],
            (int)$_POST['status_id']
        ]);

        header('Location: manage-bl.php?updated=1');
        exit();
    }
}

/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/
$search   = trim($_GET['search'] ?? '');
$status_f = trim($_GET['status'] ?? '');
$type_f   = trim($_GET['bl_type'] ?? '');

$perPage = 10;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

$where  = ['1=1'];
$params = [];

if ($search) {
    $where[] = "(b.bl_number LIKE ? OR b.item_description LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($status_f) {
    $where[] = "b.status = ?";
    $params[] = $status_f;
}

if ($type_f) {
    $where[] = "b.bl_type = ?";
    $params[] = $type_f;
}

$whereSQL = implode(' AND ', $where);

/*
|--------------------------------------------------------------------------
| FETCH DATA
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT
        b.*,
        COUNT(i.id) AS containers,
        COALESCE(SUM(i.number_of_bags),0) AS total_bags,
        COALESCE(SUM(i.gross_weight),0) AS total_gw,
        COALESCE(SUM(i.net_weight),0) AS total_nw
    FROM bills_of_lading b
    LEFT JOIN bl_items i ON b.id = i.bl_id
    WHERE {$whereSQL}
    GROUP BY b.id
    ORDER BY b.created_at DESC
    LIMIT {$perPage} OFFSET {$offset}
");

$stmt->execute($params);
$bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once 'includes/header.php';
?>

<style>
    .card {
        border-radius: 12px;
    }

    .table td,
    .table th {
        vertical-align: middle;
    }

    /* Mobile switch */
    @media (max-width: 768px) {
        .bl-table {
            display: none;
        }
    }

    @media (min-width: 769px) {
        .bl-card {
            display: none;
        }
    }

    /* Cards */
    .bl-item {
        border: 1px solid #eee;
        border-radius: 12px;
        padding: 16px;
        margin-bottom: 15px;
        background: #fff;
    }

    .bl-meta {
        font-size: 13px;
        color: #6c757d;
    }
</style>

<div class="container-fluid py-4">

    <!-- HEADER -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">

        <div>
            <h3 class="fw-bold mb-0">Shipments</h3>
            <small class="text-muted">Manage shipment records</small>
        </div>

    </div>

    <!-- ALERTS -->
    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-danger">Deleted successfully</div>
    <?php endif; ?>

    <?php if (isset($_GET['updated'])): ?>
        <div class="alert alert-success">Status updated</div>
    <?php endif; ?>

    <!-- FILTERS -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">

            <form method="GET" class="row g-2">

                <div class="col-lg-4 col-md-6">
                    <input type="text" name="search" class="form-control"
                        placeholder="Search B/L..."
                        value="<?= htmlspecialchars($search); ?>">
                </div>

                <div class="col-lg-3 col-md-6">
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="active" <?= $status_f == 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="draft" <?= $status_f == 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="completed" <?= $status_f == 'completed' ? 'selected' : '' ?>>Completed</option>
                    </select>
                </div>

                <div class="col-lg-3 col-md-6">
                    <select name="bl_type" class="form-select">
                        <option value="">All Types</option>
                        <option value="TBL" <?= $type_f == 'TBL' ? 'selected' : '' ?>>TBL</option>
                        <option value="Non-TBL" <?= $type_f == 'Non-TBL' ? 'selected' : '' ?>>Non-TBL</option>
                    </select>
                </div>

                <div class="col-lg-2 col-md-6 d-grid">
                    <button class="btn btn-primary">Filter</button>
                </div>

            </form>

        </div>
    </div>

    <!-- DESKTOP TABLE -->
    <div class="card shadow-sm bl-table">
        <div class="table-responsive">

            <table class="table table-hover mb-0">

                <thead class="table-light">
                    <tr>
                        <th>B/L</th>
                        <th>Type</th>
                        <th>Containers</th>
                        <th>Bags</th>
                        <th>Gross</th>
                        <th>Net</th>
                        <!-- <th>Status</th> -->
                        <th>Created</th>
                        <th width="150">Actions</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (empty($bills)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                No records found
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($bills as $bl): ?>

                        <tr>

                            <td>
                                <a href="view-bl.php?id=<?= $bl['id']; ?>" class="fw-bold text-decoration-none">
                                    <?= htmlspecialchars($bl['bl_number']); ?>
                                </a>
                            </td>

                            <td><span class="badge bg-primary"><?= $bl['bl_type']; ?></span></td>

                            <td><?= number_format($bl['containers']); ?></td>
                            <td><?= number_format($bl['total_bags']); ?></td>
                            <td><?= number_format($bl['total_gw'], 2); ?></td>
                            <td><?= number_format($bl['total_nw'], 2); ?></td>

                            <!-- <td>
                        <form method="POST">
                            <input type="hidden" name="status_id" value="<?= $bl['id']; ?>">
                            <select name="new_status" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="active" <?= $bl['status'] == 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="draft" <?= $bl['status'] == 'draft' ? 'selected' : '' ?>>Draft</option>
                                <option value="completed" <?= $bl['status'] == 'completed' ? 'selected' : '' ?>>Completed</option>
                            </select>
                        </form>
                    </td> -->

                            <td><?= date('M d, Y', strtotime($bl['created_at'])); ?></td>

                            <td>
                                <div class="d-flex gap-1">

                                    <a href="view-bl.php?id=<?= $bl['id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye"></i>
                                    </a>

                                    <a href="edit-bl.php?id=<?= $bl['id']; ?>" class="btn btn-sm btn-outline-dark">
                                        <i class="fas fa-edit"></i>
                                    </a>

                                    <form method="POST">
                                        <input type="hidden" name="delete_id" value="<?= $bl['id']; ?>">
                                        <button class="btn btn-sm btn-outline-danger"
                                            onclick="return confirm('Delete this B/L?')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>

                                </div>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>
    </div>

    <!-- MOBILE CARDS -->
    <div class="bl-card">

        <?php foreach ($bills as $bl): ?>

            <div class="bl-item">

                <div class="d-flex justify-content-between">

                    <div>
                        <strong><?= htmlspecialchars($bl['bl_number']); ?></strong>
                        <div class="bl-meta"><?= htmlspecialchars($bl['item_description']); ?></div>
                    </div>

                    <span class="badge bg-primary"><?= $bl['bl_type']; ?></span>

                </div>

                <hr>

                <div class="row">

                    <div class="col-6"><small>Containers</small><br><?= $bl['containers']; ?></div>
                    <div class="col-6"><small>Bags</small><br><?= $bl['total_bags']; ?></div>
                    <div class="col-6 mt-2"><small>Gross</small><br><?= $bl['total_gw']; ?></div>
                    <div class="col-6 mt-2"><small>Net</small><br><?= $bl['total_nw']; ?></div>

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
                            <i class="fas fa-edit"></i>
                        </a>

                    </div>

                </div>

            </div>

        <?php endforeach; ?>

    </div>

</div>

<?php require_once 'includes/footer.php'; ?>