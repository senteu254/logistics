<?php require_once 'includes/header.php'; ?>

<style>
    .page-title {
        font-weight: 700;
        letter-spacing: -0.3px;
    }

    .sub-text {
        font-size: 0.9rem;
    }

    .table td, .table th {
        vertical-align: middle;
        white-space: nowrap;
    }

    .card {
        border-radius: 14px;
    }

    .btn-icon {
        width: 36px;
        height: 36px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
    }

    .badge-soft {
        padding: 6px 10px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.75rem;
    }

    .badge-active { background: #e8f5e9; color: #1b5e20; }
    .badge-draft { background: #fff3e0; color: #e65100; }
    .badge-completed { background: #e3f2fd; color: #0d47a1; }

    .table-responsive {
        border-radius: 14px;
        overflow: hidden;
    }
</style>

<div class="container-fluid py-4">

    <!-- HEADER -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">

        <div>
            <h3 class="page-title mb-1">Manage Bills of Lading</h3>
            <div class="text-muted sub-text">
                View, track and manage all shipment records
            </div>
        </div>

        <a href="add-bl.php" class="btn btn-primary px-3">
            <i class="fas fa-plus-circle me-1"></i>
            New B/L
        </a>

    </div>

    <!-- ALERTS -->
    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-danger shadow-sm">
            Bill of Lading deleted successfully.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['updated'])): ?>
        <div class="alert alert-success shadow-sm">
            Status updated successfully.
        </div>
    <?php endif; ?>

    <!-- FILTER CARD -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">

            <form method="GET">
                <div class="row g-3">

                    <div class="col-lg-4 col-md-6">
                        <input type="text"
                               name="search"
                               class="form-control"
                               placeholder="Search B/L Number or Description..."
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>

                    <div class="col-lg-3 col-md-6">
                        <select name="status" class="form-select">
                            <option value="">All Statuses</option>
                            <option value="active" <?php echo $status_f==='active'?'selected':''; ?>>Active</option>
                            <option value="draft" <?php echo $status_f==='draft'?'selected':''; ?>>Draft</option>
                            <option value="completed" <?php echo $status_f==='completed'?'selected':''; ?>>Completed</option>
                        </select>
                    </div>

                    <div class="col-lg-3 col-md-6">
                        <select name="bl_type" class="form-select">
                            <option value="">All Types</option>
                            <option value="TBL" <?php echo $type_f==='TBL'?'selected':''; ?>>TBL</option>
                            <option value="Non-TBL" <?php echo $type_f==='Non-TBL'?'selected':''; ?>>Non-TBL</option>
                        </select>
                    </div>

                    <div class="col-lg-2 col-md-6 d-grid">
                        <button class="btn btn-dark">
                            <i class="fas fa-filter me-1"></i> Filter
                        </button>
                    </div>

                </div>
            </form>

        </div>
    </div>

    <!-- TABLE CARD -->
    <div class="card shadow-sm border-0">

        <div class="table-responsive">

            <table class="table table-hover align-middle mb-0">

                <thead class="table-light">
                    <tr>
                        <th>B/L Number</th>
                        <th>Type</th>
                        <th>Description</th>
                        <th>Containers</th>
                        <th>Bags</th>
                        <th>Gross Wt</th>
                        <th>Net Wt</th>
                        <th>Return Date</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th class="text-end">Actions</th>
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

                            <!-- BL NUMBER -->
                            <td>
                                <a href="view-bl.php?id=<?php echo $bl['id']; ?>"
                                   class="fw-semibold text-decoration-none">
                                    <?php echo htmlspecialchars($bl['bl_number']); ?>
                                </a>
                            </td>

                            <!-- TYPE -->
                            <td>
                                <span class="badge bg-primary">
                                    <?php echo htmlspecialchars($bl['bl_type']); ?>
                                </span>
                            </td>

                            <!-- DESC -->
                            <td class="text-truncate" style="max-width: 220px;">
                                <?php echo htmlspecialchars($bl['item_description']); ?>
                            </td>

                            <td><?php echo number_format($bl['containers']); ?></td>
                            <td><?php echo number_format($bl['total_bags']); ?></td>
                            <td><?php echo number_format($bl['total_gw'], 3); ?></td>
                            <td><?php echo number_format($bl['total_nw'], 3); ?></td>

                            <!-- RETURN DATE -->
                            <td>
                                <?php echo $bl['last_return_date']
                                    ? date('M d, Y', strtotime($bl['last_return_date']))
                                    : '<span class="text-muted">—</span>'; ?>
                            </td>

                            <!-- STATUS (VISUAL BADGE + INLINE CHANGE) -->
                            <td>
                                <form method="POST" class="d-flex align-items-center gap-2">

                                    <input type="hidden" name="status_id" value="<?php echo $bl['id']; ?>">

                                    <select name="new_status"
                                            class="form-select form-select-sm"
                                            onchange="this.form.submit()">

                                        <option value="active" <?php echo $bl['status']==='active'?'selected':''; ?>>Active</option>
                                        <option value="draft" <?php echo $bl['status']==='draft'?'selected':''; ?>>Draft</option>
                                        <option value="completed" <?php echo $bl['status']==='completed'?'selected':''; ?>>Completed</option>

                                    </select>

                                </form>
                            </td>

                            <!-- CREATED -->
                            <td class="text-muted">
                                <?php echo date('M d, Y', strtotime($bl['created_at'])); ?>
                            </td>

                            <!-- ACTIONS -->
                            <td class="text-end">

                                <div class="d-inline-flex gap-1">

                                    <a href="view-bl.php?id=<?php echo $bl['id']; ?>"
                                       class="btn btn-outline-primary btn-icon">
                                        <i class="fas fa-eye"></i>
                                    </a>

                                    <a href="edit-bl.php?id=<?php echo $bl['id']; ?>"
                                       class="btn btn-outline-dark btn-icon">
                                        <i class="fas fa-pen"></i>
                                    </a>

                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="delete_id" value="<?php echo $bl['id']; ?>">
                                        <button type="submit"
                                                class="btn btn-outline-danger btn-icon"
                                                onclick="return confirm('Delete this B/L?')">
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