<?php
require_once 'config/database.php';
$page_title = 'Manage B/L';

// Handle delete
if (isset($_POST['delete_id'])) {
    $pdo->prepare("DELETE FROM bills_of_lading WHERE id = ?")->execute([$_POST['delete_id']]);
    header('Location: manage-bl.php?deleted=1');
    exit();
}

// Handle status change
if (isset($_POST['status_id']) && isset($_POST['new_status'])) {
    $pdo->prepare("UPDATE bills_of_lading SET status = ? WHERE id = ?")
        ->execute([$_POST['new_status'], $_POST['status_id']]);
    header('Location: manage-bl.php?updated=1');
    exit();
}

// Filters
$search     = trim($_GET['search']  ?? '');
$status_f   = trim($_GET['status']  ?? '');
$type_f     = trim($_GET['bl_type'] ?? '');
$perPage    = 10;
$page       = max(1, intval($_GET['page'] ?? 1));
$offset     = ($page - 1) * $perPage;

// Build WHERE
$where  = ['1=1'];
$params = [];

if ($search) {
    $where[]  = "(b.bl_number LIKE ? OR b.item_description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status_f) {
    $where[]  = "b.status = ?";
    $params[] = $status_f;
}

if ($type_f) {
    $where[]  = "b.bl_type = ?";
    $params[] = $type_f;
}

$whereSQL = implode(' AND ', $where);

// Count
$countStmt = $pdo->prepare("
    SELECT COUNT(DISTINCT b.id)
    FROM bills_of_lading b
    LEFT JOIN bl_items i ON b.id = i.bl_id
    WHERE $whereSQL
");
$countStmt->execute($params);
$totalRows  = $countStmt->fetchColumn();
$totalPages = max(1, ceil($totalRows / $perPage));

// Fetch
$dataStmt = $pdo->prepare("
    SELECT b.*,
           u.username,
           u.full_name,
           COUNT(i.id)                              AS containers,
           COALESCE(SUM(i.number_of_bags),0)        AS total_bags,
           COALESCE(SUM(i.gross_weight),0)           AS total_gw,
           COALESCE(SUM(i.net_weight),0)             AS total_nw,
           SUM(CASE WHEN i.dispatch_status='pending'  THEN 1 ELSE 0 END) AS disp_pending,
           SUM(CASE WHEN i.dispatch_status='transit'  THEN 1 ELSE 0 END) AS disp_transit,
           SUM(CASE WHEN i.dispatch_status='received' THEN 1 ELSE 0 END) AS disp_received,
           SUM(CASE WHEN i.dispatch_status='rejected' THEN 1 ELSE 0 END) AS disp_rejected
    FROM bills_of_lading b
    LEFT JOIN users u    ON b.user_id = u.id
    LEFT JOIN bl_items i ON b.id = i.bl_id
    WHERE $whereSQL
    GROUP BY b.id
    ORDER BY b.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$dataStmt->execute($params);
$bills = $dataStmt->fetchAll();

require_once 'includes/header.php';
?>

<style>
/* Filter bar */
.filter-bar {
    background: white;
    border: 1px solid var(--border);
    border-radius: var(--r-lg);
    padding: 1.1rem 1.25rem;
    margin-bottom: 1.25rem;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    box-shadow: var(--shadow-sm);
}

.search-wrap { position:relative; flex:1; min-width:220px; }
.search-wrap i { position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--txt-3);font-size:0.83rem;pointer-events:none; }
.search-input {
    width:100%; padding:9px 12px 9px 35px;
    border:1.5px solid var(--border); border-radius:var(--r-sm);
    background:var(--bg); font-family:'Inter',sans-serif; font-size:0.875rem;
    color:var(--txt-1); outline:none; transition:var(--t);
}
.search-input:focus { border-color:var(--indigo); background:white; box-shadow:0 0 0 3px rgba(99,102,241,0.1); }

.filter-select {
    padding:9px 13px;
    border:1.5px solid var(--border); border-radius:var(--r-sm);
    background:var(--bg); font-family:'Inter',sans-serif; font-size:0.875rem;
    color:var(--txt-1); outline:none; transition:var(--t); cursor:pointer;
    min-width:140px;
}
.filter-select:focus { border-color:var(--indigo); background:white; }

/* Pagination */
.pg-wrap {
    display:flex; align-items:center; justify-content:space-between;
    padding:1rem 1.5rem; border-top:1px solid var(--border);
    flex-wrap:wrap; gap:12px;
}
.pg-info { font-size:0.8rem; color:var(--txt-2); font-weight:500; }
.pg-btns { display:flex; gap:4px; }
.pg-btn {
    width:32px; height:32px;
    border:1.5px solid var(--border); border-radius:8px;
    background:white; color:var(--txt-2);
    display:flex; align-items:center; justify-content:center;
    font-size:0.8rem; font-weight:600; cursor:pointer;
    text-decoration:none; transition:var(--t);
}
.pg-btn:hover  { border-color:var(--indigo); color:var(--indigo); }
.pg-btn.active { background:linear-gradient(135deg,var(--indigo),var(--violet)); border-color:transparent; color:white; }
.pg-btn.disabled { opacity:0.4; pointer-events:none; }

/* Delete Modal */
.modal-overlay {
    position:fixed; inset:0;
    background:rgba(0,0,0,0.55); backdrop-filter:blur(6px);
    z-index:2000;
    display:flex; align-items:center; justify-content:center;
    padding:1.5rem;
    opacity:0; pointer-events:none;
    transition:opacity 0.25s ease;
}
.modal-overlay.show { opacity:1; pointer-events:all; }
.modal-box {
    background:white; border-radius:24px;
    padding:2rem; max-width:420px; width:100%;
    box-shadow:0 25px 60px rgba(0,0,0,0.2);
    transform:scale(0.95);
    transition:transform 0.25s cubic-bezier(0.22,1,0.36,1);
}
.modal-overlay.show .modal-box { transform:scale(1); }

/* Return date urgency in table */
.date-urgent { color: var(--rose); font-weight: 700; }
.date-warn   { color: var(--amber); font-weight: 600; }
.date-ok     { color: var(--txt-2); }

/* Dispatch mini progress */
.disp-bar-track {
    height:5px; background:var(--border); border-radius:99px;
    overflow:hidden; display:flex; margin-top:4px;
    min-width:80px;
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

<!-- Flash Alerts -->
<?php if (isset($_GET['deleted'])): ?>
<div class="alert-m alert-error" id="flashAlert">
    <i class="fas fa-trash-can alert-ico" style="color:var(--rose);"></i>
    <div>Bill of Lading deleted successfully.</div>
</div>
<?php elseif (isset($_GET['updated'])): ?>
<div class="alert-m alert-success" id="flashAlert">
    <i class="fas fa-circle-check alert-ico" style="color:var(--emerald);"></i>
    <div>Status updated successfully.</div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const fa = document.getElementById('flashAlert');
    if (fa) {
        setTimeout(() => {
            fa.style.transition='opacity 0.5s'; fa.style.opacity='0';
            setTimeout(()=>fa.remove(),500);
        }, 4000);
    }
});
</script>

<!-- Filter Bar -->
<form method="GET" id="filterForm">
    <div class="filter-bar">

        <div class="search-wrap">
            <i class="fas fa-magnifying-glass"></i>
            <input type="text" name="search" class="search-input"
                   placeholder="Search B/L number or description..."
                   value="<?php echo htmlspecialchars($search); ?>"
                   oninput="debounceFilter()">
        </div>

        <select name="status" class="filter-select" onchange="this.form.submit()">
            <option value="">All Statuses</option>
            <option value="active"    <?php echo $status_f==='active'    ?'selected':''; ?>>Active</option>
            <option value="draft"     <?php echo $status_f==='draft'     ?'selected':''; ?>>Draft</option>
            <option value="completed" <?php echo $status_f==='completed' ?'selected':''; ?>>Completed</option>
        </select>

        <select name="bl_type" class="filter-select" onchange="this.form.submit()">
            <option value="">All Types</option>
            <option value="TBL"     <?php echo $type_f==='TBL'     ?'selected':''; ?>>TBL</option>
            <option value="Non-TBL" <?php echo $type_f==='Non-TBL' ?'selected':''; ?>>Non-TBL</option>
        </select>

        <?php if ($search || $status_f || $type_f): ?>
        <a href="manage-bl.php" class="btn-m btn-ghost btn-sm">
            <i class="fas fa-xmark"></i> Clear
        </a>
        <?php endif; ?>

        <div style="margin-left:auto;color:var(--txt-3);font-size:0.8rem;font-weight:500;white-space:nowrap;">
            <?php echo number_format($totalRows); ?> record<?php echo $totalRows!=1?'s':''; ?>
        </div>

    </div>
</form>

<!-- Table -->
<div class="data-card">

    <?php if (empty($bills)): ?>
    <div class="empty">
        <div class="empty-ico"><i class="fas fa-file-circle-question"></i></div>
        <h5>No Bills of Lading Found</h5>
        <p><?php echo ($search||$status_f||$type_f) ? 'Try adjusting your search or filters.' : 'Start by creating your first B/L.'; ?></p>
        <?php if (!$search && !$status_f && !$type_f): ?>
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
                    <th>Type</th>
                    <th>Description</th>
                    <th>Containers</th>
                    <th>Dispatch</th>
                    <th>Total Bags</th>
                    <th>Gross Wt (MT)</th>
                    <th>Net Wt (MT)</th>
                    <th>Return Date</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bills as $i => $bl):
                    $rd      = $bl['last_return_date'] ?? '';
                    $days    = $rd ? (int)((strtotime($rd) - strtotime('today')) / 86400) : null;
                    $rdClass = '';
                    $rdIcon  = '';
                    if ($days !== null) {
                        if ($days < 0)       { $rdClass='date-urgent'; $rdIcon='<i class="fas fa-circle-exclamation" style="font-size:0.68rem;margin-right:3px;"></i>'; }
                        elseif ($days <= 7)  { $rdClass='date-urgent'; $rdIcon='<i class="fas fa-triangle-exclamation" style="font-size:0.68rem;margin-right:3px;"></i>'; }
                        elseif ($days <= 21) { $rdClass='date-warn';   $rdIcon='<i class="fas fa-clock" style="font-size:0.68rem;margin-right:3px;"></i>'; }
                        else                 { $rdClass='date-ok'; }
                    }

                    $type    = $bl['bl_type'] ?? 'TBL';
                    $isT     = $type === 'TBL';
                    $total_c = max(1, (int)$bl['containers']);
                    $recW    = round(($bl['disp_received'] / $total_c) * 100);
                    $traW    = round(($bl['disp_transit']  / $total_c) * 100);
                    $rejW    = round(($bl['disp_rejected'] / $total_c) * 100);
                ?>
                <tr>
                    <td style="color:var(--txt-3);font-size:0.76rem;font-weight:600;"><?php echo $offset+$i+1; ?></td>

                    <!-- BL Number -->
                    <td>
                        <a href="view-bl.php?id=<?php echo $bl['id']; ?>"
                           style="font-weight:700;color:var(--indigo);font-family:'Courier New',monospace;font-size:0.82rem;text-decoration:none;">
                            <?php echo htmlspecialchars($bl['bl_number']); ?>
                        </a>
                    </td>

                    <!-- BL Type -->
                    <td>
                        <span style="
                            background:<?php echo $isT?'rgba(99,102,241,0.08)':'rgba(16,185,129,0.08)'; ?>;
                            color:<?php echo $isT?'var(--indigo)':'var(--emerald)'; ?>;
                            border:1px solid <?php echo $isT?'rgba(99,102,241,0.2)':'rgba(16,185,129,0.2)'; ?>;
                            padding:3px 9px; border-radius:20px;
                            font-size:0.72rem; font-weight:700;
                        ">
                            <i class="fas <?php echo $isT?'fa-ship':'fa-clipboard'; ?>" style="font-size:0.65rem;margin-right:3px;"></i>
                            <?php echo htmlspecialchars($type); ?>
                        </span>
                    </td>

                    <!-- Description -->
                    <td style="max-width:180px;">
                        <span style="display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:180px;color:var(--txt-2);font-size:0.82rem;">
                            <?php echo htmlspecialchars($bl['item_description']); ?>
                        </span>
                    </td>

                    <!-- Containers -->
                    <td>
                        <span class="bdg bdg-active">
                            <i class="fas fa-circle" style="font-size:0.35rem;"></i>
                            <?php echo $bl['containers']; ?>
                        </span>
                    </td>

                    <!-- Dispatch Progress -->
                    <td>
                        <div style="font-size:0.73rem;color:var(--txt-2);font-weight:500;white-space:nowrap;">
                            <?php echo $bl['disp_received']; ?>/<?php echo $bl['containers']; ?> received
                            <?php if ($bl['disp_transit'] > 0): ?>
                            · <span style="color:var(--amber);font-weight:700;"><?php echo $bl['disp_transit']; ?> transit</span>
                            <?php endif; ?>
                        </div>
                        <div class="disp-bar-track">
                            <div style="width:<?php echo $recW; ?>%;background:var(--emerald);height:100%;"></div>
                            <div style="width:<?php echo $traW; ?>%;background:var(--amber);height:100%;"></div>
                            <div style="width:<?php echo $rejW; ?>%;background:var(--rose);height:100%;"></div>
                        </div>
                    </td>

                    <!-- Bags -->
                    <td style="font-weight:600;"><?php echo number_format($bl['total_bags']); ?></td>

                    <!-- Gross Weight -->
                    <td style="font-weight:600;white-space:nowrap;"><?php echo number_format($bl['total_gw'],3); ?></td>

                    <!-- Net Weight -->
                    <td style="font-weight:600;white-space:nowrap;"><?php echo number_format($bl['total_nw'],3); ?></td>

                    <!-- Return Date -->
                    <td>
                        <div class="<?php echo $rdClass; ?>" style="font-size:0.8rem;white-space:nowrap;">
                            <?php echo $rdIcon; ?>
                            <?php echo $rd ? date('M d, Y', strtotime($rd)) : '—'; ?>
                        </div>
                        <?php if ($days !== null): ?>
                        <div style="font-size:0.7rem;color:<?php echo ($days<0||$days<=7)?'var(--rose)':($days<=21?'var(--amber)':'var(--txt-3)'); ?>;margin-top:1px;">
                            <?php
                                if ($days < 0)      echo abs($days).' days overdue';
                                elseif ($days === 0) echo 'Due TODAY';
                                else                 echo $days.' days left';
                            ?>
                        </div>
                        <?php endif; ?>
                    </td>

                    <!-- Status -->
                    <td>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="status_id" value="<?php echo $bl['id']; ?>">
                            <select name="new_status"
                                    class="status-select bdg bdg-<?php echo $bl['status']; ?>"
                                    onchange="this.form.submit()"
                                    style="padding:4px 8px;border:1.5px solid var(--border);border-radius:8px;background:var(--bg);font-family:'Inter',sans-serif;font-size:0.76rem;font-weight:700;color:var(--txt-1);outline:none;cursor:pointer;">
                                <option value="active"    <?php echo $bl['status']==='active'    ?'selected':''; ?>>● Active</option>
                                <option value="draft"     <?php echo $bl['status']==='draft'     ?'selected':''; ?>>● Draft</option>
                                <option value="completed" <?php echo $bl['status']==='completed' ?'selected':''; ?>>● Completed</option>
                            </select>
                        </form>
                    </td>

                    <!-- Created -->
                    <td style="color:var(--txt-3);font-size:0.78rem;white-space:nowrap;">
                        <?php echo date('M d, Y', strtotime($bl['created_at'])); ?>
                    </td>

                    <!-- Actions -->
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
                            <button type="button"
                                    class="btn-m btn-danger btn-sm" title="Delete"
                                    onclick="confirmDelete(<?php echo $bl['id']; ?>, '<?php echo htmlspecialchars($bl['bl_number']); ?>')">
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
    <div class="pg-wrap">
        <div class="pg-info">
            Showing <strong><?php echo $offset+1; ?>–<?php echo min($offset+$perPage,$totalRows); ?></strong>
            of <strong><?php echo number_format($totalRows); ?></strong> results
        </div>
        <div class="pg-btns">
            <?php $qs = http_build_query(['search'=>$search,'status'=>$status_f,'bl_type'=>$type_f,'page'=>$page-1]); ?>
            <a href="?<?php echo $qs; ?>" class="pg-btn <?php echo $page<=1?'disabled':''; ?>">
                <i class="fas fa-chevron-left"></i>
            </a>
            <?php for ($p=1;$p<=$totalPages;$p++):
                $qs = http_build_query(['search'=>$search,'status'=>$status_f,'bl_type'=>$type_f,'page'=>$p]);
            ?>
            <a href="?<?php echo $qs; ?>" class="pg-btn <?php echo $p==$page?'active':''; ?>"><?php echo $p; ?></a>
            <?php endfor; ?>
            <?php $qs = http_build_query(['search'=>$search,'status'=>$status_f,'bl_type'=>$type_f,'page'=>$page+1]); ?>
            <a href="?<?php echo $qs; ?>" class="pg-btn <?php echo $page>=$totalPages?'disabled':''; ?>">
                <i class="fas fa-chevron-right"></i>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>

<!-- DELETE MODAL -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-box">
        <div style="width:56px;height:56px;background:rgba(244,63,94,0.1);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.4rem;color:var(--rose);margin:0 auto 1.25rem;">
            <i class="fas fa-triangle-exclamation"></i>
        </div>
        <div style="text-align:center;font-size:1.1rem;font-weight:700;color:var(--txt-1);margin-bottom:0.5rem;">
            Delete Bill of Lading?
        </div>
        <div style="text-align:center;font-size:0.875rem;color:var(--txt-2);margin-bottom:1.75rem;line-height:1.6;">
            You are about to permanently delete
            <strong id="modalBLNum"></strong>.
            This will also remove all associated container and dispatch data.
            <br><br>This action <strong>cannot be undone.</strong>
        </div>
        <form method="POST" id="deleteForm">
            <input type="hidden" name="delete_id" id="deleteIdInput">
            <div style="display:flex;gap:10px;">
                <button type="button" class="btn-m btn-ghost" style="flex:1;justify-content:center;" onclick="closeModal()">
                    <i class="fas fa-xmark"></i> Cancel
                </button>
                <button type="submit" class="btn-m" style="flex:1;justify-content:center;background:var(--rose);color:white;border:none;">
                    <i class="fas fa-trash-can"></i> Delete
                </button>
            </div>
        </form>
    </div>
</div>

<script>
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

let debTimer;
function debounceFilter() {
    clearTimeout(debTimer);
    debTimer = setTimeout(() => document.getElementById('filterForm').submit(), 500);
}
</script>

<?php require_once 'includes/footer.php'; ?>