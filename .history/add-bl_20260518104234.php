<?php
require_once 'config/database.php';
$page_title = "Add Bill of Lading";
require_once 'includes/header.php';

// Initialize variables
$errors = [];
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $bl_number        = trim($_POST['bl_number']);
    $item_description = trim($_POST['item_description']);
    $user_id          = $_SESSION['user_id'] ?? null;

    // Validate BL header
    if ($bl_number === '') {
        $errors[] = "BL Number is required.";
    }
    if ($item_description === '') {
        $errors[] = "Item description is required.";
    }

    // Validate container rows
    $containers = $_POST['container_number'] ?? [];
    if (empty($containers)) {
        $errors[] = "At least one container must be added.";
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Insert BL
            $stmt = $pdo->prepare("
                INSERT INTO bills_of_lading (bl_number, item_description, user_id, status)
                VALUES (:bl_number, :item_description, :user_id, 'active')
            ");
            $stmt->execute([
                ':bl_number'        => $bl_number,
                ':item_description' => $item_description,
                ':user_id'          => $user_id
            ]);

            $bl_id = $pdo->lastInsertId();

            // Insert BL items
            $item_stmt = $pdo->prepare("
                INSERT INTO bl_items 
                (bl_id, container_number, agent_seal_number, number_of_bags, sgs_seal_number, gross_weight, net_weight)
                VALUES 
                (:bl_id, :container_number, :agent_seal_number, :number_of_bags, :sgs_seal_number, :gross_weight, :net_weight)
            ");

            foreach ($containers as $i => $container_number) {

                if (trim($container_number) === '') continue;

                $item_stmt->execute([
                    ':bl_id'             => $bl_id,
                    ':container_number'  => trim($_POST['container_number'][$i]),
                    ':agent_seal_number' => trim($_POST['agent_seal_number'][$i]),
                    ':number_of_bags'    => (int) $_POST['number_of_bags'][$i],
                    ':sgs_seal_number'   => trim($_POST['sgs_seal_number'][$i]),
                    ':gross_weight'      => (float) $_POST['gross_weight'][$i],
                    ':net_weight'        => (float) $_POST['net_weight'][$i]
                ]);
            }

            $pdo->commit();
            $success = true;

        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}
?>

<div class="container py-4">

    <h3 class="fw-bold mb-3">New Bill of Lading</h3>

    <?php if ($success): ?>
        <div class="alert alert-success">
            Bill of Lading created successfully.
            <a href="manage-bl.php" class="alert-link">View All</a>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <strong>Fix the following:</strong>
            <ul class="mb-0">
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST">

        <!-- BL HEADER -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white">
                <strong>BL Details</strong>
            </div>
            <div class="card-body">

                <div class="mb-3">
                    <label class="form-label">BL Number</label>
                    <input type="text" name="bl_number" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Item Description</label>
                    <textarea name="item_description" class="form-control" rows="2" required></textarea>
                </div>

            </div>
        </div>

        <!-- CONTAINERS -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between">
                <strong>Containers</strong>
                <button type="button" class="btn btn-sm btn-primary" onclick="addRow()">
                    <i class="fas fa-plus"></i> Add Container
                </button>
            </div>

            <div class="card-body" id="container-rows">

                <!-- Default Row -->
                <div class="row g-2 mb-3 container-row">

                    <div class="col-md-3">
                        <input type="text" name="container_number[]" class="form-control" placeholder="Container Number" required>
                    </div>

                    <div class="col-md-2">
                        <input type="text" name="agent_seal_number[]" class="form-control" placeholder="Agent Seal">
                    </div>

                    <div class="col-md-2">
                        <input type="number" name="number_of_bags[]" class="form-control" placeholder="Bags" required>
                    </div>

                    <div class="col-md-2">
                        <input type="text" name="sgs_seal_number[]" class="form-control" placeholder="SGS Seal">
                    </div>

                    <div class="col-md-1">
                        <input type="number" step="0.01" name="gross_weight[]" class="form-control" placeholder="GW" required>
                    </div>

                    <div class="col-md-1">
                        <input type="number" step="0.01" name="net_weight[]" class="form-control" placeholder="NW" required>
                    </div>

                    <div class="col-md-1">
                        <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>

                </div>

            </div>
        </div>

        <button class="btn btn-success">Save BL</button>

    </form>

</div>

<script>
function addRow() {
    let row = document.querySelector('.container-row').cloneNode(true);
    row.querySelectorAll('input').forEach(i => i.value = '');
    document.getElementById('container-rows').appendChild(row);
}

function removeRow(btn) {
    let rows = document.querySelectorAll('.container-row');
    if (rows.length > 1) {
        btn.closest('.container-row').remove();
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
