<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'config/database.php';

$page_title = 'Manage B/L';

/*
|--------------------------------------------------------------------------
| AUTO DATABASE MIGRATION
|--------------------------------------------------------------------------
*/

try {

    /*
    |--------------------------------------------------------------------------
    | bills_of_lading.bl_type
    |--------------------------------------------------------------------------
    */

    $check = $pdo->query("
        SHOW COLUMNS
        FROM bills_of_lading
        LIKE 'bl_type'
    ");

    if ($check->rowCount() == 0) {

        $pdo->exec("
            ALTER TABLE bills_of_lading
            ADD COLUMN bl_type VARCHAR(20)
            NOT NULL DEFAULT 'TBL'
            AFTER bl_number
        ");
    }

    /*
    |--------------------------------------------------------------------------
    | bills_of_lading.last_return_date
    |--------------------------------------------------------------------------
    */

    $check = $pdo->query("
        SHOW COLUMNS
        FROM bills_of_lading
        LIKE 'last_return_date'
    ");

    if ($check->rowCount() == 0) {

        $pdo->exec("
            ALTER TABLE bills_of_lading
            ADD COLUMN last_return_date DATE NULL
            AFTER item_description
        ");
    }

    /*
    |--------------------------------------------------------------------------
    | bl_items.dispatch_status
    |--------------------------------------------------------------------------
    */

    $check = $pdo->query("
        SHOW COLUMNS
        FROM bl_items
        LIKE 'dispatch_status'
    ");

    if ($check->rowCount() == 0) {

        $pdo->exec("
            ALTER TABLE bl_items
            ADD COLUMN dispatch_status ENUM(
                'pending',
                'transit',
                'received',
                'rejected'
            )
            NOT NULL DEFAULT 'pending'
            AFTER net_weight
        ");

        /*
        |--------------------------------------------------------------------------
        | MIGRATE OLD STATUS VALUES
        |--------------------------------------------------------------------------
        */

        $pdo->exec("
            UPDATE bl_items
            SET dispatch_status =
            CASE
                WHEN empty_return_status = 'pending'
                    THEN 'pending'

                WHEN empty_return_status = 'in_transit'
                    THEN 'transit'

                WHEN empty_return_status = 'completed'
                    THEN 'received'

                ELSE 'pending'
            END
        ");
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE INDEXES SAFELY
    |--------------------------------------------------------------------------
    */

    // idx_bl_number
    $check = $pdo->query("
        SHOW INDEX
        FROM bills_of_lading
        WHERE Key_name = 'idx_bl_number'
    ");

    if ($check->rowCount() == 0) {

        $pdo->exec("
            CREATE INDEX idx_bl_number
            ON bills_of_lading(bl_number)
        ");
    }

    // idx_bl_status
    $check = $pdo->query("
        SHOW INDEX
        FROM bills_of_lading
        WHERE Key_name = 'idx_bl_status'
    ");

    if ($check->rowCount() == 0) {

        $pdo->exec("
            CREATE INDEX idx_bl_status
            ON bills_of_lading(status)
        ");
    }

    // idx_bl_type
    $check = $pdo->query("
        SHOW INDEX
        FROM bills_of_lading
        WHERE Key_name = 'idx_bl_type'
    ");

    if ($check->rowCount() == 0) {

        $pdo->exec("
            CREATE INDEX idx_bl_type
            ON bills_of_lading(bl_type)
        ");
    }

    // idx_bl_items_blid
    $check = $pdo->query("
        SHOW INDEX
        FROM bl_items
        WHERE Key_name = 'idx_bl_items_blid'
    ");

    if ($check->rowCount() == 0) {

        $pdo->exec("
            CREATE INDEX idx_bl_items_blid
            ON bl_items(bl_id)
        ");
    }

} catch (Exception $e) {

    die("Migration Error: " . $e->getMessage());
}

/*
|--------------------------------------------------------------------------
| DELETE RECORD
|--------------------------------------------------------------------------
*/

if (isset($_POST['delete_id'])) {

    $stmt = $pdo->prepare("
        DELETE FROM bills_of_lading
        WHERE id = ?
    ");

    $stmt->execute([
        (int)$_POST['delete_id']
    ]);

    header('Location: manage-bl.php?deleted=1');
    exit();
}

/*
|--------------------------------------------------------------------------
| UPDATE STATUS
|--------------------------------------------------------------------------
*/

if (
    isset($_POST['status_id']) &&
    isset($_POST['new_status'])
) {

    $allowedStatuses = [
        'active',
        'draft',
        'completed'
    ];

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

    $where[] = "
        (
            b.bl_number LIKE ?
            OR b.item_description LIKE ?
        )
    ";

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
| COUNT RECORDS
|--------------------------------------------------------------------------
*/

$countStmt = $pdo->prepare("
    SELECT COUNT(DISTINCT b.id)

    FROM bills_of_lading b

    LEFT JOIN bl_items i
        ON b.id = i.bl_id

    WHERE {$whereSQL}
");

$countStmt->execute($params);

$totalRows  = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalRows / $perPage));

/*
|--------------------------------------------------------------------------
| FETCH DATA
|--------------------------------------------------------------------------
*/

$dataStmt = $pdo->prepare("
    SELECT

        b.*,

        u.username,
        u.full_name,

        COUNT(i.id) AS containers,

        COALESCE(SUM(i.number_of_bags), 0) AS total_bags,

        COALESCE(SUM(i.gross_weight), 0) AS total_gw,

        COALESCE(SUM(i.net_weight), 0) AS total_nw,

        SUM(
            CASE
                WHEN i.dispatch_status = 'pending'
                THEN 1
                ELSE 0
            END
        ) AS disp_pending,

        SUM(
            CASE
                WHEN i.dispatch_status = 'transit'
                THEN 1
                ELSE 0
            END
        ) AS disp_transit,

        SUM(
            CASE
                WHEN i.dispatch_status = 'received'
                THEN 1
                ELSE 0
            END
        ) AS disp_received,

        SUM(
            CASE
                WHEN i.dispatch_status = 'rejected'
                THEN 1
                ELSE 0
            END
        ) AS disp_rejected

    FROM bills_of_lading b

    LEFT JOIN users u
        ON b.user_id = u.id

    LEFT JOIN bl_items i
        ON b.id = i.bl_id

    WHERE {$whereSQL}

    GROUP BY b.id

    ORDER BY b.created_at DESC

    LIMIT " . (int)$perPage . "
    OFFSET " . (int)$offset . "
");

$dataStmt->execute($params);

$bills = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

require_once 'includes/header.php';

?>

<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h2 class="fw-bold mb-1">Manage Bills of Lading</h2>
            <p class="text-muted mb-0">
                View and manage shipment records
            </p>
        </div>

        <a href="add-bl.php" class="btn btn-primary">
            <i class="fas fa-plus-circle me-1"></i>
            New B/L
        </a>

    </div>

    <?php if (isset($_GET['deleted'])): ?>

        <div class="alert alert-danger">
            Bill of Lading deleted successfully.
        </div>

    <?php endif; ?>

    <?php if (isset($_GET['updated'])): ?>

        <div class="alert alert-success">
            Status updated successfully.
        </div>

    <?php endif; ?>

    <!-- FILTERS -->

    <div class="card shadow-sm border-0 mb-4">

        <div class="card-body">

            <form method="GET">

                <div class="row g-3">

                    <div class="col-md-4">

                        <input
                            type="text"
                            name="search"
                            class="form-control"
                            placeholder="Search B/L Number..."
                            value="<?php echo htmlspecialchars($search); ?>"
                        >

                    </div>

                    <div class="col-md-3">

                        <select
                            name="status"
                            class="form-select"
                        >

                            <option value="">
                                All Statuses
                            </option>

                            <option
                                value="active"
                                <?php echo $status_f === 'active' ? 'selected' : ''; ?>
                            >
                                Active
                            </option>

                            <option
                                value="draft"
                                <?php echo $status_f === 'draft' ? 'selected' : ''; ?>
                            >
                                Draft
                            </option>

                            <option
                                value="completed"
                                <?php echo $status_f === 'completed' ? 'selected' : ''; ?>
                            >
                                Completed
                            </option>

                        </select>

                    </div>

                    <div class="col-md-3">

                        <select
                            name="bl_type"
                            class="form-select"
                        >

                            <option value="">
                                All Types
                            </option>

                            <option
                                value="TBL"
                                <?php echo $type_f === 'TBL' ? 'selected' : ''; ?>
                            >
                                TBL
                            </option>

                            <option
                                value="Non-TBL"
                                <?php echo $type_f === 'Non-TBL' ? 'selected' : ''; ?>
                            >
                                Non-TBL
                            </option>

                        </select>

                    </div>

                    <div class="col-md-2">

                        <button class="btn btn-dark w-100">
                            Filter
                        </button>

                    </div>

                </div>

            </form>

        </div>

    </div>

    <!-- TABLE -->

    <div class="card border-0 shadow-sm">

        <div class="table-responsive">

            <table class="table table-hover align-middle mb-0">

                <thead class="table-light">

                    <tr>

                        <th>B/L Number</th>
                        <th>Type</th>
                        <th>Description</th>
                        <th>Containers</th>
                        <th>Total Bags</th>
                        <th>Gross Weight</th>
                        <th>Net Weight</th>
                        <th>Return Date</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th width="180">Actions</th>

                    </tr>

                </thead>

                <tbody>

                <?php if (empty($bills)): ?>

                    <tr>

                        <td colspan="11" class="text-center py-5 text-muted">
                            No Bills of Lading found
                        </td>

                    </tr>

                <?php else: ?>

                    <?php foreach ($bills as $bl): ?>

                        <tr>

                            <td>

                                <a
                                    href="view-bl.php?id=<?php echo $bl['id']; ?>"
                                    class="fw-bold text-decoration-none"
                                >
                                    <?php echo htmlspecialchars($bl['bl_number']); ?>
                                </a>

                            </td>

                            <td>

                                <span class="badge bg-primary">
                                    <?php echo htmlspecialchars($bl['bl_type']); ?>
                                </span>

                            </td>

                            <td>

                                <?php echo htmlspecialchars($bl['item_description']); ?>

                            </td>

                            <td>

                                <?php echo number_format($bl['containers']); ?>

                            </td>

                            <td>

                                <?php echo number_format($bl['total_bags']); ?>

                            </td>

                            <td>

                                <?php echo number_format($bl['total_gw'], 3); ?>

                            </td>

                            <td>

                                <?php echo number_format($bl['total_nw'], 3); ?>

                            </td>

                            <td>

                                <?php if ($bl['last_return_date']): ?>

                                    <?php echo date('M d, Y', strtotime($bl['last_return_date'])); ?>

                                <?php else: ?>

                                    —

                                <?php endif; ?>

                            </td>

                            <td>

                                <form method="POST">

                                    <input
                                        type="hidden"
                                        name="status_id"
                                        value="<?php echo $bl['id']; ?>"
                                    >

                                    <select
                                        name="new_status"
                                        class="form-select form-select-sm"
                                        onchange="this.form.submit()"
                                    >

                                        <option
                                            value="active"
                                            <?php echo $bl['status'] === 'active' ? 'selected' : ''; ?>
                                        >
                                            Active
                                        </option>

                                        <option
                                            value="draft"
                                            <?php echo $bl['status'] === 'draft' ? 'selected' : ''; ?>
                                        >
                                            Draft
                                        </option>

                                        <option
                                            value="completed"
                                            <?php echo $bl['status'] === 'completed' ? 'selected' : ''; ?>
                                        >
                                            Completed
                                        </option>

                                    </select>

                                </form>

                            </td>

                            <td>

                                <?php echo date('M d, Y', strtotime($bl['created_at'])); ?>

                            </td>

                            <td>

                                <div class="d-flex gap-2">

                                    <a
                                        href="view-bl.php?id=<?php echo $bl['id']; ?>"
                                        class="btn btn-sm btn-outline-primary"
                                    >
                                        <i class="fas fa-eye"></i>
                                    </a>

                                    <a
                                        href="edit-bl.php?id=<?php echo $bl['id']; ?>"
                                        class="btn btn-sm btn-outline-dark"
                                    >
                                        <i class="fas fa-edit"></i>
                                    </a>

                                    <form method="POST">

                                        <input
                                            type="hidden"
                                            name="delete_id"
                                            value="<?php echo $bl['id']; ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="btn btn-sm btn-outline-danger"
                                            onclick="return confirm('Delete this B/L?')"
                                        >
                                            <i class="fas fa-trash"></i>
                                        </button>

                                    </form>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

<?php require_once 'includes/footer.php'; ?>