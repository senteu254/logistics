<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'config/database.php';

$page_title = 'Manage Shipments';

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
if (isset($_POST['status_id'], $_POST['new_status'])) {
    $allowedStatuses = ['active', 'draft', 'completed'];
    if (in_array($_POST['new_status'], $allowedStatuses)) {
        $stmt = $pdo->prepare("UPDATE bills_of_lading SET status = ? WHERE id = ?");
        $stmt->execute([$_POST['new_status'], (int)$_POST['status_id']]);
        header('Location: manage-bl.php?updated=1');
        exit();
    }
}

/*
|--------------------------------------------------------------------------
| FILTERS & PAGINATION
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
    $where[]  = "(b.bl_number LIKE ? OR b.item_description LIKE ?)";
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
| COUNT (for pagination)
|--------------------------------------------------------------------------
*/
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM bills_of_lading b WHERE {$whereSQL}");
$countStmt->execute($params);
$totalRows  = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));

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
        COALESCE(SUM(i.gross_weight),0)   AS total_gw,
        COALESCE(SUM(i.net_weight),0)     AS total_nw
    FROM bills_of_lading b
    LEFT JOIN bl_items i ON b.id = i.bl_id
    WHERE {$whereSQL}
    GROUP BY b.id
    ORDER BY b.created_at DESC
    LIMIT {$perPage} OFFSET {$offset}
");
$stmt->execute($params);
$bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Build query string for pagination links (preserve filters) */
$qBase = http_build_query(array_filter([
    'search' => $search,
    'status' => $status_f,
    'bl_type' => $type_f,
]));

require_once 'includes/header.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Manage Shipments</title>

    <!-- Bootstrap 5.3 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">

    <style>
        /* ── Base ── */
        :root {
            --dash-bg: #f4f6fb;
            --card-bg: #ffffff;
            --card-radius: 14px;
            --accent-blue: #2563eb;
            --accent-green: #16a34a;
            --accent-amber: #d97706;
            --accent-red: #dc2626;
            --muted: #6b7280;
            --border: #e5e7eb;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, .08), 0 1px 2px rgba(0, 0, 0, .04);
            --shadow-md: 0 4px 12px rgba(0, 0, 0, .08);
            --font-main: 'DM Sans', system-ui, sans-serif;
            --font-mono: 'DM Mono', monospace;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        body {
            background: var(--dash-bg);
            font-family: var(--font-main);
            font-size: 15px;
            color: #111827;
            -webkit-font-smoothing: antialiased;
        }

        /* ── Page wrapper ── */
        .dash-wrapper {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1.25rem 1rem 3rem;
        }

        @media (min-width: 768px) {
            .dash-wrapper {
                padding: 2rem 1.5rem 3rem;
            }
        }

        /* ── Page header ── */
        .page-header {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: flex-start;
            gap: .75rem;
            margin-bottom: 1.5rem;
        }

        .page-header h1 {
            font-size: clamp(1.3rem, 4vw, 1.75rem);
            font-weight: 600;
            margin: 0;
            letter-spacing: -.02em;
            line-height: 1.2;
        }

        .page-header .subtitle {
            font-size: .8125rem;
            color: var(--muted);
            margin-top: 2px;
        }

        /* ── Section card ── */
        .section-card {
            background: var(--card-bg);
            border-radius: var(--card-radius);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
            overflow: hidden;
            margin-bottom: 1rem;
        }

        /* ── Filter bar ── */
        .filter-bar {
            padding: 1rem 1.25rem;
        }

        .filter-bar .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
        }

        .filter-input {
            font-family: var(--font-main);
            font-size: .875rem;
            height: 38px;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 0 .75rem;
            background: #fff;
            color: #111827;
            outline: none;
            transition: border-color .15s, box-shadow .15s;
            flex: 1 1 160px;
            min-width: 0;
        }

        .filter-input:focus {
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .12);
        }

        select.filter-input {
            cursor: pointer;
        }

        .btn-filter {
            height: 38px;
            padding: 0 1.1rem;
            background: var(--accent-blue);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-family: var(--font-main);
            font-size: .875rem;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: .375rem;
            transition: background .15s;
            white-space: nowrap;
        }

        .btn-filter:hover {
            background: #1d4ed8;
        }

        .btn-reset {
            height: 38px;
            padding: 0 .9rem;
            background: transparent;
            color: var(--muted);
            border: 1px solid var(--border);
            border-radius: 8px;
            font-family: var(--font-main);
            font-size: .875rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: .25rem;
            transition: background .15s, color .15s;
            text-decoration: none;
            white-space: nowrap;
        }

        .btn-reset:hover {
            background: #f3f4f6;
            color: #374151;
        }

        /* Filter divider */
        .filter-divider {
            height: 1px;
            background: var(--border);
        }

        /* Result count chip */
        .result-chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: #eff6ff;
            color: #1d4ed8;
            font-size: .75rem;
            font-weight: 600;
            padding: .2em .7em;
            border-radius: 20px;
        }

        /* ── Desktop table ── */
        .ship-table {
            width: 100%;
            border-collapse: collapse;
            font-size: .875rem;
        }

        .ship-table thead tr {
            background: #f9fafb;
            border-bottom: 1px solid var(--border);
        }

        .ship-table thead th {
            padding: .625rem 1rem;
            font-size: .72rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: var(--muted);
            white-space: nowrap;
        }

        .ship-table tbody tr {
            border-bottom: 1px solid #f3f4f6;
            transition: background .1s;
        }

        .ship-table tbody tr:last-child {
            border-bottom: none;
        }

        .ship-table tbody tr:hover {
            background: #f9fafb;
        }

        .ship-table tbody td {
            padding: .75rem 1rem;
            vertical-align: middle;
        }

        /* BL number mono style */
        .bl-num {
            font-family: var(--font-mono);
            font-size: .8125rem;
            font-weight: 500;
            color: var(--accent-blue);
            text-decoration: none;
        }

        .bl-num:hover {
            text-decoration: underline;
            color: #1d4ed8;
        }

        /* Type badge */
        .type-badge {
            display: inline-block;
            padding: .2em .65em;
            border-radius: 6px;
            font-size: .72rem;
            font-weight: 600;
            letter-spacing: .02em;
            text-transform: uppercase;
        }

        .type-badge.tbl {
            background: #eff6ff;
            color: #1d4ed8;
        }

        .type-badge.non-tbl {
            background: #fef3c7;
            color: #92400e;
        }

        /* Status badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: .22em .65em;
            border-radius: 20px;
            font-size: .72rem;
            font-weight: 600;
            letter-spacing: .02em;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .status-badge .dot {
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: currentColor;
        }

        .status-badge.active {
            background: #f0fdf4;
            color: #15803d;
        }

        .status-badge.draft {
            background: #f3f4f6;
            color: #4b5563;
        }

        .status-badge.completed {
            background: #eff6ff;
            color: #1d4ed8;
        }

        /* Action buttons */
        .act-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            border-radius: 7px;
            border: 1px solid var(--border);
            background: #fff;
            color: #374151;
            font-size: .875rem;
            text-decoration: none;
            cursor: pointer;
            transition: all .15s;
        }

        .act-btn:hover {
            background: #f3f4f6;
            border-color: #d1d5db;
        }

        .act-btn.view:hover {
            color: var(--accent-blue);
        }

        .act-btn.edit:hover {
            color: #374151;
        }

        .act-btn.del:hover {
            color: var(--accent-red);
            border-color: #fca5a5;
            background: #fef2f2;
        }

        /* ── Mobile cards ── */
        .mobile-list {
            padding: .75rem;
            display: flex;
            flex-direction: column;
            gap: .75rem;
        }

        .ship-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
        }

        .ship-card-top {
            padding: .875rem 1rem .625rem;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: .5rem;
        }

        .ship-card-title {
            font-family: var(--font-mono);
            font-size: .8125rem;
            font-weight: 500;
            color: var(--accent-blue);
            text-decoration: none;
            display: block;
        }

        .ship-card-desc {
            font-size: .8125rem;
            color: var(--muted);
            margin-top: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 220px;
        }

        .ship-card-badges {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 4px;
            flex-shrink: 0;
        }

        .ship-card-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
        }

        .ship-stat {
            padding: .5rem .25rem;
            text-align: center;
        }

        .ship-stat+.ship-stat {
            border-left: 1px solid var(--border);
        }

        .ship-stat-label {
            font-size: .625rem;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: var(--muted);
            display: block;
            margin-bottom: 1px;
        }

        .ship-stat-val {
            font-size: .875rem;
            font-weight: 600;
            line-height: 1.2;
        }

        .ship-card-footer {
            padding: .625rem 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: .5rem;
        }

        .ship-card-date {
            font-size: .75rem;
            color: var(--muted);
            display: flex;
            align-items: center;
            gap: 4px;
        }

        /* Delete modal on mobile */
        .del-form-mob {
            margin: 0;
        }

        /* ── Empty state ── */
        .empty-state {
            padding: 4rem 1rem;
            text-align: center;
        }

        .empty-icon {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            background: #f3f4f6;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--muted);
            margin-bottom: 1rem;
        }

        /* ── Pagination ── */
        .pager {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: .5rem;
            padding: .875rem 1.25rem;
            border-top: 1px solid var(--border);
            font-size: .8125rem;
        }

        .pager-info {
            color: var(--muted);
        }

        .pager-links {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
        }

        .pager-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 32px;
            height: 32px;
            padding: 0 .4rem;
            border-radius: 7px;
            border: 1px solid var(--border);
            background: #fff;
            color: #374151;
            font-size: .8125rem;
            font-family: var(--font-main);
            text-decoration: none;
            transition: all .15s;
        }

        .pager-btn:hover {
            background: #f3f4f6;
            color: var(--accent-blue);
        }

        .pager-btn.active {
            background: var(--accent-blue);
            color: #fff;
            border-color: var(--accent-blue);
            font-weight: 600;
        }

        .pager-btn.disabled {
            opacity: .4;
            pointer-events: none;
        }

        /* ── Alert ── */
        .dash-alert {
            display: flex;
            align-items: center;
            gap: .625rem;
            padding: .75rem 1rem;
            border-radius: 10px;
            font-size: .875rem;
            font-weight: 500;
            margin-bottom: 1rem;
            animation: fadeSlide .3s ease;
        }

        @keyframes fadeSlide {
            from {
                opacity: 0;
                transform: translateY(-6px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .dash-alert.success {
            background: #f0fdf4;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        .dash-alert.danger {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        /* ── Primary button ── */
        .btn-primary-dash {
            background: var(--accent-blue);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: .5rem 1rem;
            font-size: .875rem;
            font-weight: 500;
            font-family: var(--font-main);
            display: inline-flex;
            align-items: center;
            gap: .375rem;
            text-decoration: none;
            white-space: nowrap;
            transition: background .15s, transform .1s;
        }

        .btn-primary-dash:hover {
            background: #1d4ed8;
            color: #fff;
        }

        .btn-primary-dash:active {
            transform: scale(.97);
        }

        /* ── Visibility ── */
        .desktop-only {
            display: none;
        }

        .mobile-only {
            display: block;
        }

        @media (min-width: 768px) {
            .desktop-only {
                display: block;
            }

            .mobile-only {
                display: none;
            }
        }

        /* Table horizontal scroll */
        .table-scroll {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
    </style>

    <div class="dash-wrapper">

        <!-- PAGE HEADER -->
        <header class="page-header">
            <div>
                <h1>Shipments</h1>
                <p class="subtitle">Manage shipment records</p>
            </div>
            <a href="add-bl.php" class="btn-primary-dash">
                <i class="bi bi-plus-lg"></i> New Shipment
            </a>
        </header>

        <!-- ALERTS -->
        <?php if (isset($_GET['deleted'])): ?>
            <div class="dash-alert danger" role="alert">
                <i class="bi bi-trash3"></i> Record deleted successfully.
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['updated'])): ?>
            <div class="dash-alert success" role="alert">
                <i class="bi bi-check-circle"></i> Status updated successfully.
            </div>
        <?php endif; ?>

        <!-- FILTER BAR -->
        <div class="section-card mb-3">
            <div class="filter-bar">
                <form method="GET" class="filter-row" role="search" aria-label="Filter shipments">

                    <input
                        type="search"
                        name="search"
                        class="filter-input"
                        placeholder="Search B/L number or description…"
                        value="<?= htmlspecialchars($search) ?>"
                        aria-label="Search">

                    <select name="status" class="filter-input" aria-label="Filter by status">
                        <option value="">All statuses</option>
                        <option value="active" <?= $status_f === 'active'    ? 'selected' : '' ?>>Active</option>
                        <option value="draft" <?= $status_f === 'draft'     ? 'selected' : '' ?>>Draft</option>
                        <option value="completed" <?= $status_f === 'completed' ? 'selected' : '' ?>>Completed</option>
                    </select>

                    <select name="bl_type" class="filter-input" aria-label="Filter by type">
                        <option value="">All types</option>
                        <option value="TBL" <?= $type_f === 'TBL'     ? 'selected' : '' ?>>TBL</option>
                        <option value="Non-TBL" <?= $type_f === 'Non-TBL' ? 'selected' : '' ?>>Non-TBL</option>
                    </select>

                    <button type="submit" class="btn-filter">
                        <i class="bi bi-funnel"></i> Filter
                    </button>

                    <?php if ($search || $status_f || $type_f): ?>
                        <a href="manage-bl.php" class="btn-reset">
                            <i class="bi bi-x-lg"></i> Clear
                        </a>
                    <?php endif; ?>

                </form>
            </div>

            <!-- Results info row -->
            <?php if ($totalRows > 0): ?>
                <div class="filter-divider"></div>
                <div style="padding:.5rem 1.25rem; display:flex; align-items:center; gap:.5rem;">
                    <span class="result-chip">
                        <i class="bi bi-list-ul" style="font-size:.8rem;"></i>
                        <?= number_format($totalRows) ?> record<?= $totalRows !== 1 ? 's' : '' ?>
                    </span>
                    <?php if ($search || $status_f || $type_f): ?>
                        <span style="font-size:.75rem; color:var(--muted);">— filtered</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- SHIPMENT LIST -->
        <div class="section-card">

            <?php if (empty($bills)): ?>

                <div class="empty-state">
                    <div class="empty-icon" aria-hidden="true"><i class="bi bi-inbox"></i></div>
                    <p style="font-weight:500; margin-bottom:.25rem;">No shipments found</p>
                    <p style="font-size:.875rem; color:var(--muted); margin-bottom:1.25rem;">
                        <?= ($search || $status_f || $type_f) ? 'Try adjusting your filters.' : 'Create your first Bill of Lading to get started.' ?>
                    </p>
                    <?php if (!$search && !$status_f && !$type_f): ?>
                        <a href="add-bl.php" class="btn-primary-dash" style="margin: 0 auto;">
                            <i class="bi bi-plus-lg"></i> Add BL
                        </a>
                    <?php else: ?>
                        <a href="manage-bl.php" class="btn-reset" style="margin: 0 auto;">
                            <i class="bi bi-arrow-left"></i> Clear filters
                        </a>
                    <?php endif; ?>
                </div>

            <?php else: ?>

                <!-- ═══════════════════════════════════════
           DESKTOP TABLE (≥768px)
      ════════════════════════════════════════ -->
                <div class="desktop-only table-scroll">
                    <table class="ship-table" aria-label="Shipments list">
                        <thead>
                            <tr>
                                <th scope="col">B/L Number</th>
                                <th scope="col">Type</th>
                                <th scope="col">Containers</th>
                                <th scope="col">Bags</th>
                                <th scope="col">Gross (MT)</th>
                                <th scope="col">Net (MT)</th>
                                <th scope="col">Status</th>
                                <th scope="col">Created</th>
                                <th scope="col" style="width:110px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bills as $bl):
                                $sc = match ($bl['status']) {
                                    'active' => 'active',
                                    'draft' => 'draft',
                                    default => 'completed'
                                };
                                $tc = strtolower($bl['bl_type']) === 'tbl' ? 'tbl' : 'non-tbl';
                            ?>
                                <tr>
                                    <td>
                                        <a href="view-bl.php?id=<?= (int)$bl['id'] ?>" class="bl-num">
                                            <?= htmlspecialchars($bl['bl_number']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="type-badge <?= $tc ?>">
                                            <?= htmlspecialchars($bl['bl_type']) ?>
                                        </span>
                                    </td>
                                    <td><?= number_format((int)$bl['containers']) ?></td>
                                    <td><?= number_format((int)$bl['total_bags']) ?></td>
                                    <td><?= number_format((float)$bl['total_gw'], 3) ?></td>
                                    <td><?= number_format((float)$bl['total_nw'], 3) ?></td>
                                    <td>
                                        <span class="status-badge <?= $sc ?>">
                                            <span class="dot" aria-hidden="true"></span>
                                            <?= ucfirst($bl['status']) ?>
                                        </span>
                                    </td>
                                    <td style="color:var(--muted); font-size:.8125rem; white-space:nowrap;">
                                        <?= date('M d, Y', strtotime($bl['created_at'])) ?>
                                    </td>
                                    <td>
                                        <div style="display:flex; gap:5px; align-items:center;">
                                            <a href="view-bl.php?id=<?= (int)$bl['id'] ?>"
                                                class="act-btn view"
                                                title="View" aria-label="View <?= htmlspecialchars($bl['bl_number']) ?>">
                                                <i class="bi bi-eye" aria-hidden="true"></i>
                                            </a>
                                            <a href="edit-bl.php?id=<?= (int)$bl['id'] ?>"
                                                class="act-btn edit"
                                                title="Edit" aria-label="Edit <?= htmlspecialchars($bl['bl_number']) ?>">
                                                <i class="bi bi-pencil" aria-hidden="true"></i>
                                            </a>
                                            <form method="POST" style="margin:0;">
                                                <input type="hidden" name="delete_id" value="<?= (int)$bl['id'] ?>">
                                                <button type="submit"
                                                    class="act-btn del"
                                                    title="Delete"
                                                    aria-label="Delete <?= htmlspecialchars($bl['bl_number']) ?>"
                                                    onclick="return confirm('Permanently delete <?= htmlspecialchars(addslashes($bl['bl_number'])) ?>?')">
                                                    <i class="bi bi-trash3" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                </div>

                <!-- ═══════════════════════════════════════
           MOBILE CARDS (<768px)
      ════════════════════════════════════════ -->
                <div class="mobile-only mobile-list">
                    <?php foreach ($bills as $bl):
                        $sc = match ($bl['status']) {
                            'active' => 'active',
                            'draft' => 'draft',
                            default => 'completed'
                        };
                        $tc = strtolower($bl['bl_type']) === 'tbl' ? 'tbl' : 'non-tbl';
                    ?>
                        <article class="ship-card">

                            <div class="ship-card-top">
                                <div style="min-width:0;">
                                    <a href="view-bl.php?id=<?= (int)$bl['id'] ?>" class="ship-card-title">
                                        <?= htmlspecialchars($bl['bl_number']) ?>
                                    </a>
                                    <p class="ship-card-desc" title="<?= htmlspecialchars($bl['item_description']) ?>">
                                        <?= htmlspecialchars($bl['item_description']) ?>
                                    </p>
                                </div>
                                <div class="ship-card-badges">
                                    <span class="type-badge <?= $tc ?>"><?= htmlspecialchars($bl['bl_type']) ?></span>
                                    <span class="status-badge <?= $sc ?>">
                                        <span class="dot" aria-hidden="true"></span>
                                        <?= ucfirst($bl['status']) ?>
                                    </span>
                                </div>
                            </div>

                            <div class="ship-card-stats">
                                <div class="ship-stat">
                                    <span class="ship-stat-label">Cont.</span>
                                    <span class="ship-stat-val"><?= (int)$bl['containers'] ?></span>
                                </div>
                                <div class="ship-stat">
                                    <span class="ship-stat-label">Bags</span>
                                    <span class="ship-stat-val"><?= number_format((int)$bl['total_bags']) ?></span>
                                </div>
                                <div class="ship-stat">
                                    <span class="ship-stat-label">Gross</span>
                                    <span class="ship-stat-val"><?= number_format((float)$bl['total_gw'], 1) ?></span>
                                </div>
                                <div class="ship-stat">
                                    <span class="ship-stat-label">Net</span>
                                    <span class="ship-stat-val"><?= number_format((float)$bl['total_nw'], 1) ?></span>
                                </div>
                            </div>

                            <div class="ship-card-footer">
                                <span class="ship-card-date">
                                    <i class="bi bi-calendar3" aria-hidden="true"></i>
                                    <?= date('M d, Y', strtotime($bl['created_at'])) ?>
                                </span>
                                <div style="display:flex; gap:6px; align-items:center;">
                                    <a href="view-bl.php?id=<?= (int)$bl['id'] ?>"
                                        class="act-btn view" aria-label="View <?= htmlspecialchars($bl['bl_number']) ?>">
                                        <i class="bi bi-eye" aria-hidden="true"></i>
                                    </a>
                                    <a href="edit-bl.php?id=<?= (int)$bl['id'] ?>"
                                        class="act-btn edit" aria-label="Edit <?= htmlspecialchars($bl['bl_number']) ?>">
                                        <i class="bi bi-pencil" aria-hidden="true"></i>
                                    </a>
                                    <form method="POST" class="del-form-mob">
                                        <input type="hidden" name="delete_id" value="<?= (int)$bl['id'] ?>">
                                        <button type="submit"
                                            class="act-btn del"
                                            aria-label="Delete <?= htmlspecialchars($bl['bl_number']) ?>"
                                            onclick="return confirm('Permanently delete <?= htmlspecialchars(addslashes($bl['bl_number'])) ?>?')">
                                            <i class="bi bi-trash3" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>

                        </article>
                    <?php endforeach ?>
                </div>

                <!-- ═══════════════════════════════════════
           PAGINATION
      ════════════════════════════════════════ -->
                <?php if ($totalPages > 1): ?>
                    <nav class="pager" aria-label="Pagination">

                        <span class="pager-info">
                            Showing <?= number_format($offset + 1) ?>–<?= number_format(min($offset + $perPage, $totalRows)) ?>
                            of <?= number_format($totalRows) ?>
                        </span>

                        <div class="pager-links">

                            <!-- Prev -->
                            <?php $prevQ = $qBase ? $qBase . '&page=' . ($page - 1) : 'page=' . ($page - 1); ?>
                            <a href="manage-bl.php?<?= $prevQ ?>"
                                class="pager-btn <?= $page <= 1 ? 'disabled' : '' ?>"
                                aria-label="Previous page" <?= $page <= 1 ? 'tabindex="-1" aria-disabled="true"' : '' ?>>
                                <i class="bi bi-chevron-left" style="font-size:.75rem;"></i>
                            </a>

                            <!-- Page numbers -->
                            <?php
                            $start = max(1, $page - 2);
                            $end   = min($totalPages, $page + 2);
                            if ($start > 1): ?>
                                <?php $q = $qBase ? $qBase . '&page=1' : 'page=1'; ?>
                                <a href="manage-bl.php?<?= $q ?>" class="pager-btn">1</a>
                                <?php if ($start > 2): ?><span class="pager-btn" style="pointer-events:none;border:none;color:var(--muted);">…</span><?php endif; ?>
                            <?php endif; ?>

                            <?php for ($p = $start; $p <= $end; $p++):
                                $q = $qBase ? $qBase . '&page=' . $p : 'page=' . $p;
                            ?>
                                <a href="manage-bl.php?<?= $q ?>"
                                    class="pager-btn <?= $p === $page ? 'active' : '' ?>"
                                    <?= $p === $page ? 'aria-current="page"' : '' ?>>
                                    <?= $p ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($end < $totalPages):
                                if ($end < $totalPages - 1): ?><span class="pager-btn" style="pointer-events:none;border:none;color:var(--muted);">…</span><?php endif;
                                                                                                                                    $q = $qBase ? $qBase . '&page=' . $totalPages : 'page=' . $totalPages;
                                                                                                                                        ?>
                                <a href="manage-bl.php?<?= $q ?>" class="pager-btn"><?= $totalPages ?></a>
                            <?php endif; ?>

                            <!-- Next -->
                            <?php $nextQ = $qBase ? $qBase . '&page=' . ($page + 1) : 'page=' . ($page + 1); ?>
                            <a href="manage-bl.php?<?= $nextQ ?>"
                                class="pager-btn <?= $page >= $totalPages ? 'disabled' : '' ?>"
                                aria-label="Next page" <?= $page >= $totalPages ? 'tabindex="-1" aria-disabled="true"' : '' ?>>
                                <i class="bi bi-chevron-right" style="font-size:.75rem;"></i>
                            </a>

                        </div>
                    </nav>
                <?php endif; ?>

            <?php endif; ?>

        </div><!-- /.section-card -->

    </div><!-- /.dash-wrapper -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        /* Auto-dismiss alerts after 4 seconds */
        document.querySelectorAll('.dash-alert').forEach(el => {
            setTimeout(() => {
                el.style.transition = 'opacity .4s';
                el.style.opacity = '0';
                setTimeout(() => el.remove(), 400);
            }, 4000);
        });
    </script>

</html>

<?php require_once 'includes/footer.php'; ?>