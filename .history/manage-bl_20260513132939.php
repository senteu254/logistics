<?php
require_once 'config/database.php';
$page_title = 'Manage B/L';

// Handle delete
if (isset($_POST['delete_id'])) {
    $del = $pdo->prepare("DELETE FROM bills_of_lading WHERE id = ?");
    $del->execute([$_POST['delete_id']]);
    header('Location: manage-bl.php?deleted=1');
    exit();
}

// Handle status change
if (isset($_POST['status_id']) && isset($_POST['new_status'])) {
    $upd = $pdo->prepare("UPDATE bills_of_lading SET status = ? WHERE id = ?");
    $upd->execute([$_POST['new_status'], $_POST['status_id']]);
    header('Location: manage-bl.php?updated=1');
    exit();
}

// Filters
$search  = trim($_GET['search']  ?? '');
$status  = trim($_GET['status']  ?? '');
$perPage = 10;
$page    = max(1, intval($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

// Build query
$where  = [];
$params = [];

if ($search) {
    $where[]  = "(b.bl_number LIKE ? OR b.item_description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status) {
    $where[]  = "b.status = ?";
    $params[] = $status;
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Count
$countStmt = $pdo->prepare("
    SELECT COUNT(DISTINCT b.id)
    FROM bills_of_lading b
    LEFT JOIN bl_items i ON b.id = i.bl_id
    $whereSQL
");
$countStmt->execute($params);
$totalRows  = $countStmt->fetchColumn();
$totalPages = ceil($totalRows / $perPage);

// Fetch
$dataStmt = $pdo->prepare("
    SELECT b.*,
           u.username,
           u.full_name,
           COUNT(i.id)                       AS containers,
           COALESCE(SUM(i.number_of_bags),0) AS total_bags,
           COALESCE(SUM(i.gross_weight),0)   AS total_gw,
           COALESCE(SUM(i.net_weight),0)     AS total_nw
    FROM bills_of_lading b
    LEFT JOIN users u    ON b.user_id = u.id
    LEFT JOIN bl_items i ON b.id = i.bl_id
    $whereSQL
    GROUP BY b.id
    ORDER BY b.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$dataStmt->execute($params);
$bills = $dataStmt->fetchAll();

require_once 'includes/header.php';
?>

<style>
/* ── Manage-specific styles ── */
.filter-bar {
    background: white;
    border: 1px solid var(--border);
    border-radius: var(--r-lg);
    padding: 1.25rem 1.5rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    box-shadow: var(--shadow-sm);
}

.search-wrap {
    position: relative;
    flex: 1;
    min-width: 220px;
}

.search-wrap i {
    position: absolute;
    left: 13px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--txt-3);
    font-size: 0.85rem;
    pointer-events: none;
}

.search-input {
    width: 100%;
    padding: 9px 13px 9px 36px;
    border: 1.5px solid var(--border);
    border-radius: var(--r-sm);
    background: var(--bg);
    font-family: 'Inter', sans-serif;
    font-size: 0.875rem;
    color: var(--txt-1);
    outline: none;
    transition: var(--t);
}

.search-input:focus {
    border-color: var(--indigo);
    background: white;
    box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
}

.filter-select {
    padding: 9px 13px;
    border: 1.5px solid var(--border);
    border-radius: var(--r-sm);
    background: var(--bg);
    font-family: 'Inter', sans-serif;
    font-size: 0.875rem;
    color: var(--txt-1);
    outline: none;
    transition: var(--t);
    cursor: pointer;
    min-width: 150px;
}

.filter-select:focus {
    border-color: var(--indigo);
    background: white;
    box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
}

/* Pagination */
.pagination-wrap {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--border);
    flex-wrap: wrap;
    gap: 12px;
}

.pagination-info {
    font-size: 0.82rem;
    color: var(--txt-2);
    font-weight: 500;
}

.pagination-btns {
    display: flex;
    gap: 4px;
}

.pg-btn {
    width: 34px;
    height: 34px;
    border: 1.5px solid var(--border);
    border-radius: 8px;
    background: white;
    color: var(--txt-2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.82rem;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    transition: var(--t);
    font-family: 'Inter', sans-serif;
}

.pg-btn:hover {
    border-color: var(--indigo);
    color: var(--indigo);
    background: rgba(99,102,241,0.05);
}

.pg-btn.active {
    background: linear-gradient(135deg, var(--indigo), var(--violet));
    border-color: transparent;
    color: white;
    box-shadow: 0 3px 10px rgba(99,102,241,0.3);
}

.pg-btn.disabled {
    opacity: 0.4;
    pointer-events: none;
}

/* Action Menu */
.action-menu-wrap {
    position: relative;
}

.action-btn {
    width: 32px;
    height: 32px;
    border: 1.5px solid var(--border);
    border-radius: 8px;
    background: white;
    color: var(--txt-2);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: var(--t);
    font-size: 0.82rem;
}

.action-btn:hover {
    border-color: var(--indigo);
    color: var(--indigo);
    background: rgba(99,102,241,0.05);
}

.action-dropdown {
    position: absolute;
    right: 0;
    top: calc(100% + 6px);
    background: white;
    border: 1px solid var(--border);
    border-radius: var(--r-md);
    box-shadow: var(--shadow-lg);
    min-width: 175px;
    z-index: 200;
    overflow: hidden;
    display: none;
    animation: dropIn 0.2s ease;
}

@keyframes dropIn {
    from { opacity:0; transform:translateY(-6px); }
    to   { opacity:1; transform:translateY(0); }
}

.action-dropdown.show { display: block; }

.action-drop-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    font-size: 0.83rem;
    font-weight: 500;
    color: var(--txt-1);
    cursor: pointer;
    transition: var(--t);
    border: none;
    background: none;
    width: 100%;
    text-align: left;
    font-family: 'Inter', sans-serif;
    text-decoration: none;
}

.action-drop-item:hover {
    background: var(--bg);
    color: var(--indigo);
}

.action-drop-item.danger { color: var(--rose); }
.action-drop-item.danger:hover { background: rgba(244,63,94,0.06); }

.action-drop-divider {
    height: 1px;
    background: var(--border);
    margin: 4px 0;
}

/* Modal */
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.55);
    backdrop-filter: blur(6px);
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
    border-radius: var(--r-xl, 24px);
    padding: 2rem;
    max-width: 420px;
    width: 100%;
    box-shadow: 0 25px 60px rgba(0,0,0,0.2);
    transform: scale(0.95);
    transition: transform 0.25s cubic-bezier(0.22,1,0.36,1);
}

.modal-overlay.show .modal-box {
    transform: scale(1);
}

.modal-icon {
    width: 56px;
    height: 56px;
    background: rgba(244,63,94,0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    color: var(--rose);
    margin: 0 auto 1.25rem;
}

.modal-title {
    text-align: center;
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--txt-1);
    margin-bottom: 0.5rem;
}

.modal-desc {
    text-align: center;
    font-size: 0.875rem;
    color: var(--txt-2);
    margin-bottom: 1.75rem;
    line-height: 1.6;
}

.modal-actions {
    display: flex;
    gap: 10px;
}

.modal-actions .btn-m { flex: 1; justify-content: center; }

/* Status change select inside table */
.status-select {
    padding: 4px 8px;
    border: 1.5px solid var(--border);
    border-radius: 8px;
    background: var(--bg);
    font-family: 'Inter', sans-serif;
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--txt-1);
    outline: none;
    cursor: pointer;
}

/* BL number style */
.bl-num {
    font-weight: 700;
    color: var(--indigo);
    font-family: 'Courier New', monospace;
    font-size: 0.82rem;
    letter-spacing: 0.3px;
}

/* Highlight row */
.tbl tbody tr.highlight {
    background: rgba(99,102,241,0.04);
}
</style>

<!-- Page Header -->
<div class="page-header d-flex align-items-start justify-content-between flex-wrap gap-3">
    <div>
        <h1 class="page-title">Manage B/L</h1>
        <p class="page-sub">View, search, edit and manage all Bills of Lading</p>
    </div>
    <a href="add-bl.php" class="btn-m btn-indigo">
        <i class="fas fa-circle-plus"></i> New B/L
    </a>
</div>

<!-- Alerts -->
<?php if (isset($_GET['deleted'])): ?>
<div class="alert-m alert-error" style="background:rgba(244,63,94,0.07);border:1px solid rgba(244,63,94,0.2);color:#9f1239;">
    <i class="fas fa-trash-can alert-ico" style="color:var(--rose);"></i>
    <div>Bill of Lading deleted successfully.</div>
</div>
<?php endif; ?>

<?php if (isset($_GET['updated'])): ?>
<div class="alert-m alert-success">
    <i class="fas fa-circle-check alert-ico" style="color:var(--emerald);"></i>
    <div>Status updated successfully.</div>
</div>
<?php endif; ?>

<!-- Filter Bar -->
<form method="GET" id="filterForm">
    <div class="filter-bar">

        <div class="search-wrap">
            <i class="fas fa-magnifying-glass"></i>
            <input
                type="text"
                name="search"
                class="search-input"
                placeholder="Search B/L number or description..."
                value="<?php echo htmlspecialchars($search); ?>"
                oninput="debounceFilter()"
            >
        </div>

        <select name="status" class="filter-select" onchange="this.form.submit()">
            <option value="">All Statuses</option>
            <option value="active"    <?php echo $status=='active'    ?'selected':''; ?>>Active</option>
            <option value="draft"     <?php echo $status=='draft'     ?'selected':''; ?>>Draft</option>
            <option value="completed" <?php echo $status=='completed' ?'selected':''; ?>>Completed</option>
        </select>

        <?php if ($search || $status): ?>
        <a href="manage-bl.php" class="btn-m btn-ghost btn-sm">
            <i class="fas fa-xmark"></i> Clear
        </a>
        <?php endif; ?>

        <div style="margin-left:auto; color:var(--txt-3); font-size:0.82rem; font-weight:500; white-space:nowrap;">
            <?php echo number_format($totalRows); ?> record<?php echo $totalRows!=1?'s':''; ?> found
        </div>

    </div>
</form>

<!-- Data Table -->
<div class="data-card">

    <?php if (empty($bills)): ?>
    <div class="empty">
        <div class="empty-ico"><i class="fas fa-file-circle-question"></i></div>
        <h5>No Bills of Lading Found</h5>
        <p><?php echo $search||$status ? 'Try adjusting your search or filters.' : 'Start by creating your first B/L.'; ?></p>
        <?php if (!$search && !$status): ?>
        <a href="add-bl.php" class="btn-m btn-indigo mt-3 mx-auto" style="width:fit-content;">
            <i class="fas fa-circle-plus"></i> Create First B/L
        </a>
        <?php endif; ?>
    </div>

    <?php else: ?>

    <div class="table-responsive">
        <table class="tbl">
            <thead>
                <tr>
                    <th>#</th>
                    <th>B/L Number</th>
                    <th>Item Description</th>
                    <th>Containers</th>
                    <th>Dispatch</th>
                    <th>Total Bags</th>
                    <th>Gross Weight</th>
                    <th>Net Weight</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bills as $i => $bl): ?>
                <tr id="row-<?php echo $bl['id']; ?>">

                    <td style="color:var(--txt-3);font-size:0.78rem;font-weight:600;">
                        <?php echo $offset + $i + 1; ?>
                    </td>

                    <td>
                        <a href="view-bl.php?id=<?php echo $bl['id']; ?>"
                           class="bl-num"
                           style="text-decoration:none;">
                            <?php echo htmlspecialchars($bl['bl_number']); ?>
                        </a>
                    </td>

                    <td style="max-width:200px;">
                        <span style="display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:200px;color:var(--txt-2);font-size:0.83rem;">
                            <?php echo htmlspecialchars($bl['item_description']); ?>
                        </span>
                    </td>

                    <td>
                        <span class="bdg bdg-active">
                            <i class="fas fa-circle" style="font-size:0.35rem;"></i>
                            <?php echo $bl['containers']; ?>
                        </span>
                    </td>

                    <td style="font-weight:600;"><?php echo number_format($bl['total_bags']); ?></td>

                    <td style="font-weight:600;white-space:nowrap;">
                        <?php echo number_format($bl['total_gw'],2); ?> MT
                    </td>

                    <td style="font-weight:600;white-space:nowrap;">
                        <?php echo number_format($bl['total_nw'],2); ?> MT
                    </td>

                    <td>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="status_id" value="<?php echo $bl['id']; ?>">
                            <select
                                name="new_status"
                                class="status-select bdg bdg-<?php echo $bl['status']; ?>"
                                onchange="this.form.submit()"
                                title="Change status"
                            >
                                <option value="active"    <?php echo $bl['status']=='active'    ?'selected':''; ?>>● Active</option>
                                <option value="draft"     <?php echo $bl['status']=='draft'     ?'selected':''; ?>>● Draft</option>
                                <option value="completed" <?php echo $bl['status']=='completed' ?'selected':''; ?>>● Completed</option>
                            </select>
                        </form>
                    </td>

                    <td style="color:var(--txt-3);font-size:0.8rem;white-space:nowrap;">
                        <?php echo date('M d, Y', strtotime($bl['created_at'])); ?>
                    </td>

                    <td>
                        <div style="display:flex;justify-content:flex-end;gap:5px;">

                            <a href="view-bl.php?id=<?php echo $bl['id']; ?>"
                               class="btn-m btn-ghost btn-sm" title="View">
                                <i class="fas fa-eye"></i>
                            </a>

                            <a href="edit-bl.php?id=<?php echo $bl['id']; ?>"
                               class="btn-m btn-ghost btn-sm" title="Edit">
                                <i class="fas fa-pen"></i>
                            </a>

                            <button
                                type="button"
                                class="btn-m btn-danger btn-sm"
                                title="Delete"
                                onclick="confirmDelete(<?php echo $bl['id']; ?>, '<?php echo htmlspecialchars($bl['bl_number']); ?>')"
                            >
                                <i class="fas fa-trash-can"></i>
                            </button>

                        </div>
                    </td>

                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination-wrap">
        <div class="pagination-info">
            Showing <strong><?php echo $offset+1; ?>–<?php echo min($offset+$perPage,$totalRows); ?></strong>
            of <strong><?php echo number_format($totalRows); ?></strong> results
        </div>
        <div class="pagination-btns">
            <!-- Prev -->
            <?php
            $qs = http_build_query(['search'=>$search,'status'=>$status,'page'=>$page-1]);
            ?>
            <a href="?<?php echo $qs; ?>"
               class="pg-btn <?php echo $page<=1?'disabled':''; ?>">
                <i class="fas fa-chevron-left"></i>
            </a>

            <?php for ($p = 1; $p <= $totalPages; $p++):
                $qs = http_build_query(['search'=>$search,'status'=>$status,'page'=>$p]);
            ?>
            <a href="?<?php echo $qs; ?>"
               class="pg-btn <?php echo $p==$page?'active':''; ?>">
                <?php echo $p; ?>
            </a>
            <?php endfor; ?>

            <!-- Next -->
            <?php
            $qs = http_build_query(['search'=>$search,'status'=>$status,'page'=>$page+1]);
            ?>
            <a href="?<?php echo $qs; ?>"
               class="pg-btn <?php echo $page>=$totalPages?'disabled':''; ?>">
                <i class="fas fa-chevron-right"></i>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>

<!-- ═══════════════════════════════
     DELETE CONFIRMATION MODAL
═══════════════════════════════ -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-box">
        <div class="modal-icon">
            <i class="fas fa-triangle-exclamation"></i>
        </div>
        <div class="modal-title">Delete Bill of Lading?</div>
        <div class="modal-desc">
            You are about to permanently delete
            <strong id="modalBLNum"></strong>.
            This will also remove all associated container data.
            <br><br>This action <strong>cannot be undone.</strong>
        </div>
        <form method="POST" id="deleteForm">
            <input type="hidden" name="delete_id" id="deleteIdInput">
            <div class="modal-actions">
                <button type="button" class="btn-m btn-ghost" onclick="closeModal()">
                    <i class="fas fa-xmark"></i> Cancel
                </button>
                <button type="submit" class="btn-m btn-danger" style="background:var(--rose);color:white;border:none;">
                    <i class="fas fa-trash-can"></i> Delete
                </button>
            </div>
        </form>
    </div>
</div>

<script>
/* ── Delete Modal ── */
function confirmDelete(id, num) {
    document.getElementById('deleteIdInput').value = id;
    document.getElementById('modalBLNum').textContent = num;
    document.getElementById('deleteModal').classList.add('show');
}

function closeModal() {
    document.getElementById('deleteModal').classList.remove('show');
}

document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

/* ── Search Debounce ── */
let debTimer;
function debounceFilter() {
    clearTimeout(debTimer);
    debTimer = setTimeout(() => {
        document.getElementById('filterForm').submit();
    }, 500);
}

/* ── Auto-dismiss alerts ── */
document.querySelectorAll('.alert-m').forEach(el => {
    setTimeout(() => {
        el.style.transition = 'opacity 0.5s';
        el.style.opacity = '0';
        setTimeout(() => el.remove(), 500);
    }, 4000);
});
</script>

<?php require_once 'includes/footer.php'; ?>