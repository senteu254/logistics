<?php
require_once 'config/database.php';
$page_title = 'Dashboard';
require_once 'includes/header.php';

/* -----------------------------------------------------------
   SAFE COUNT HELPER
----------------------------------------------------------- */
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

/* -----------------------------------------------------------
   TOP-LEVEL STATS
----------------------------------------------------------- */
$total_bls  = getCount($pdo, "SELECT COUNT(*) FROM bills_of_lading");
$active_bls = getCount($pdo, "SELECT COUNT(*) FROM bills_of_lading WHERE status='active'");
$total_bags = getCount($pdo, "SELECT COALESCE(SUM(number_of_bags),0) FROM bl_items");
$total_gw   = getCount($pdo, "SELECT COALESCE(SUM(gross_weight),0) FROM bl_items");

/* -----------------------------------------------------------
   RECENT SHIPMENTS (MySQL 5.7 SAFE)
----------------------------------------------------------- */
try {
    $recent = $pdo->query("
        SELECT 
            b.id,
            b.bl_number,
            b.item_description,
            b.status,
            b.created_at,
            u.username,
            COUNT(i.id) AS containers,
            COALESCE(SUM(i.number_of_bags),0) AS total_bags,
            COALESCE(SUM(i.gross_weight),0) AS total_gw
        FROM bills_of_lading b
        LEFT JOIN users u ON b.user_id = u.id
        LEFT JOIN bl_items i ON b.id = i.bl_id
        GROUP BY 
            b.id, b.bl_number, b.item_description, 
            b.status, b.created_at, u.username
        ORDER BY b.created_at DESC
        LIMIT 8
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Recent query error: " . $e->getMessage());
    $recent = [];
}

/* Helper: status badge class */
function statusBadge($status)
{
    return match ($status) {
        'active'    => 'bg-success',
        'draft'     => 'bg-secondary',
        'completed' => 'bg-primary',
        default     => 'bg-info',
    };
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Dashboard — Operations Center</title>

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
            line-height: 1.2;
            letter-spacing: -.02em;
        }

        .page-header .subtitle {
            font-size: .8125rem;
            color: var(--muted);
            margin-top: 2px;
        }

        /* ── Stat cards ── */
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: .75rem;
            margin-bottom: 1.5rem;
        }

        @media (min-width: 576px) {
            .stat-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
        }

        @media (min-width: 992px) {
            .stat-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: var(--card-radius);
            padding: 1.1rem 1.25rem;
            box-shadow: var(--shadow-sm);
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: box-shadow .15s;
            border: 1px solid transparent;
        }

        .stat-card:hover {
            box-shadow: var(--shadow-md);
            border-color: var(--border);
        }

        .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 1.25rem;
        }

        .stat-icon.blue {
            background: #eff6ff;
            color: var(--accent-blue);
        }

        .stat-icon.green {
            background: #f0fdf4;
            color: var(--accent-green);
        }

        .stat-icon.amber {
            background: #fffbeb;
            color: var(--accent-amber);
        }

        .stat-icon.red {
            background: #fef2f2;
            color: var(--accent-red);
        }

        .stat-body {
            min-width: 0;
        }

        .stat-value {
            font-size: clamp(1.2rem, 3.5vw, 1.5rem);
            font-weight: 600;
            line-height: 1.1;
            letter-spacing: -.02em;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .stat-label {
            font-size: .75rem;
            color: var(--muted);
            margin-top: 2px;
            white-space: nowrap;
        }

        /* ── Section card ── */
        .section-card {
            background: var(--card-bg);
            border-radius: var(--card-radius);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            border: 1px solid var(--border);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--border);
            gap: .5rem;
        }

        .section-title {
            font-size: .9375rem;
            font-weight: 600;
            margin: 0;
            letter-spacing: -.01em;
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
            font-size: .75rem;
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

        .bl-number {
            font-family: var(--font-mono);
            font-size: .8125rem;
            font-weight: 500;
            color: var(--accent-blue);
        }

        .desc-cell {
            max-width: 180px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: var(--muted);
        }

        /* ── Status badge ── */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: .25em .65em;
            border-radius: 20px;
            font-size: .72rem;
            font-weight: 600;
            letter-spacing: .02em;
            text-transform: uppercase;
            white-space: nowrap;
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

        .status-dot {
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: currentColor;
        }

        /* ── Action buttons ── */
        .action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            border-radius: 7px;
            border: 1px solid var(--border);
            background: #fff;
            color: #374151;
            text-decoration: none;
            font-size: .875rem;
            transition: all .15s;
        }

        .action-btn:hover {
            background: #f3f4f6;
            color: var(--accent-blue);
            border-color: #d1d5db;
        }

        /* ── Mobile cards ── */
        .mobile-list {
            padding: .75rem;
            display: flex;
            flex-direction: column;
            gap: .75rem;
        }

        .ship-mob-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
        }

        .ship-mob-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: .875rem 1rem .625rem;
            gap: .5rem;
        }

        .ship-mob-bl {
            font-family: var(--font-mono);
            font-size: .8125rem;
            font-weight: 500;
            color: var(--accent-blue);
            margin: 0;
            line-height: 1;
        }

        .ship-mob-desc {
            font-size: .8125rem;
            color: var(--muted);
            margin: .25rem 0 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 200px;
        }

        .ship-mob-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
        }

        .ship-mob-stat {
            padding: .625rem .5rem;
            text-align: center;
        }

        .ship-mob-stat+.ship-mob-stat {
            border-left: 1px solid var(--border);
        }

        .ship-mob-stat-label {
            font-size: .6875rem;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .04em;
            display: block;
            margin-bottom: 2px;
        }

        .ship-mob-stat-val {
            font-size: .9375rem;
            font-weight: 600;
            line-height: 1.1;
        }

        .ship-mob-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: .625rem 1rem;
            gap: .5rem;
        }

        .ship-mob-date {
            font-size: .75rem;
            color: var(--muted);
        }

        /* ── Empty state ── */
        .empty-state {
            padding: 3.5rem 1rem;
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

        /* ── Utility ── */
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

        .btn-outline-dash {
            background: transparent;
            color: var(--accent-blue);
            border: 1px solid currentColor;
            border-radius: 7px;
            padding: .3rem .75rem;
            font-size: .8125rem;
            font-weight: 500;
            font-family: var(--font-main);
            display: inline-flex;
            align-items: center;
            gap: .25rem;
            text-decoration: none;
            transition: background .15s;
        }

        .btn-outline-dash:hover {
            background: #eff6ff;
            color: #1d4ed8;
        }

        /* ── Mobile-first visibility ── */
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

        /* ── Scrollable table wrapper ── */
        .table-scroll {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
    </style>
</head>

<body>

    <div class="dash-wrapper">

        <!-- PAGE HEADER -->
        <header class="page-header">
            <div>
                <h1>Dashboard</h1>
                <p class="subtitle">Operations Center</p>
            </div>
            <a href="add-bl.php" class="btn-primary-dash">
                <i class="bi bi-plus-lg"></i> New Shipment
            </a>
        </header>

        <!-- STAT CARDS -->
        <div class="stat-grid" role="list" aria-label="Summary statistics">

            <div class="stat-card" role="listitem">
                <div class="stat-icon blue" aria-hidden="true">
                    <i class="bi bi-file-earmark-text"></i>
                </div>
                <div class="stat-body">
                    <div class="stat-value"><?= number_format($total_bls) ?></div>
                    <div class="stat-label">Shipments</div>
                </div>
            </div>

            <div class="stat-card" role="listitem">
                <div class="stat-icon green" aria-hidden="true">
                    <i class="bi bi-check-circle"></i>
                </div>
                <div class="stat-body">
                    <div class="stat-value"><?= number_format($active_bls) ?></div>
                    <div class="stat-label">Active</div>
                </div>
            </div>

            <div class="stat-card" role="listitem">
                <div class="stat-icon amber" aria-hidden="true">
                    <i class="bi bi-boxes"></i>
                </div>
                <div class="stat-body">
                    <div class="stat-value"><?= number_format($total_bags) ?></div>
                    <div class="stat-label">Total Bags</div>
                </div>
            </div>

            <div class="stat-card" role="listitem">
                <div class="stat-icon red" aria-hidden="true">
                    <i class="bi bi-speedometer2"></i>
                </div>
                <div class="stat-body">
                    <div class="stat-value"><?= number_format($total_gw, 1) ?></div>
                    <div class="stat-label">Gross Weight (MT)</div>
                </div>
            </div>

        </div>

        <!-- RECENT SHIPMENTS -->
        <div class="section-card">

            <div class="section-header">
                <h2 class="section-title">Recent Shipments</h2>
                <a href="manage-bl.php" class="btn-outline-dash">
                    View all <i class="bi bi-arrow-right" aria-hidden="true"></i>
                </a>
            </div>

            <?php if (empty($recent)): ?>

                <div class="empty-state">
                    <div class="empty-icon" aria-hidden="true"><i class="bi bi-inbox"></i></div>
                    <p style="font-weight:500; margin-bottom:.25rem;">No shipments yet</p>
                    <p style="font-size:.875rem; color:var(--muted); margin-bottom:1.25rem;">
                        Create your first Bill of Lading to get started.
                    </p>
                    <a href="add-bl.php" class="btn-primary-dash" style="margin:0 auto;">
                        <i class="bi bi-plus-lg"></i> Add BL
                    </a>
                </div>

            <?php else: ?>

                <!-- DESKTOP TABLE -->
                <div class="desktop-only table-scroll">
                    <table class="ship-table" aria-label="Recent shipments">
                        <thead>
                            <tr>
                                <th scope="col">B/L Number</th>
                                <th scope="col">Description</th>
                                <th scope="col">Containers</th>
                                <th scope="col">Bags</th>
                                <th scope="col">Gross (MT)</th>
                                <th scope="col">Status</th>
                                <th scope="col">Date</th>
                                <th scope="col" style="width:90px">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent as $bl):
                                $sc = match ($bl['status']) {
                                    'active' => 'active',
                                    'draft' => 'draft',
                                    default => 'completed'
                                };
                            ?>
                                <tr>
                                    <td>
                                        <span class="bl-number"><?= htmlspecialchars($bl['bl_number']) ?></span>
                                    </td>
                                    <td>
                                        <span class="desc-cell" title="<?= htmlspecialchars($bl['item_description']) ?>">
                                            <?= htmlspecialchars($bl['item_description']) ?>
                                        </span>
                                    </td>
                                    <td><?= (int)$bl['containers'] ?></td>
                                    <td><?= number_format($bl['total_bags']) ?></td>
                                    <td><?= number_format($bl['total_gw'], 3) ?></td>
                                    <td>
                                        <span class="status-badge <?= $sc ?>">
                                            <span class="status-dot" aria-hidden="true"></span>
                                            <?= ucfirst($bl['status']) ?>
                                        </span>
                                    </td>
                                    <td style="color:var(--muted); font-size:.8125rem; white-space:nowrap;">
                                        <?= date('M d, Y', strtotime($bl['created_at'])) ?>
                                    </td>
                                    <td>
                                        <div style="display:flex; gap:6px;">
                                            <a href="view-bl.php?id=<?= (int)$bl['id'] ?>"
                                                class="action-btn" title="View" aria-label="View <?= htmlspecialchars($bl['bl_number']) ?>">
                                                <i class="bi bi-eye" aria-hidden="true"></i>
                                            </a>
                                            <a href="edit-bl.php?id=<?= (int)$bl['id'] ?>"
                                                class="action-btn" title="Edit" aria-label="Edit <?= htmlspecialchars($bl['bl_number']) ?>">
                                                <i class="bi bi-pencil" aria-hidden="true"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                </div>

                <!-- MOBILE CARDS -->
                <div class="mobile-only mobile-list">
                    <?php foreach ($recent as $bl):
                        $sc = match ($bl['status']) {
                            'active' => 'active',
                            'draft' => 'draft',
                            default => 'completed'
                        };
                    ?>
                        <article class="ship-mob-card">

                            <div class="ship-mob-top">
                                <div style="min-width:0;">
                                    <p class="ship-mob-bl"><?= htmlspecialchars($bl['bl_number']) ?></p>
                                    <p class="ship-mob-desc" title="<?= htmlspecialchars($bl['item_description']) ?>">
                                        <?= htmlspecialchars($bl['item_description']) ?>
                                    </p>
                                </div>
                                <span class="status-badge <?= $sc ?>" style="flex-shrink:0;">
                                    <span class="status-dot" aria-hidden="true"></span>
                                    <?= ucfirst($bl['status']) ?>
                                </span>
                            </div>

                            <div class="ship-mob-stats">
                                <div class="ship-mob-stat">
                                    <span class="ship-mob-stat-label">Containers</span>
                                    <span class="ship-mob-stat-val"><?= (int)$bl['containers'] ?></span>
                                </div>
                                <div class="ship-mob-stat">
                                    <span class="ship-mob-stat-label">Bags</span>
                                    <span class="ship-mob-stat-val"><?= number_format($bl['total_bags']) ?></span>
                                </div>
                                <div class="ship-mob-stat">
                                    <span class="ship-mob-stat-label">Gross MT</span>
                                    <span class="ship-mob-stat-val"><?= number_format($bl['total_gw'], 2) ?></span>
                                </div>
                            </div>

                            <div class="ship-mob-footer">
                                <span class="ship-mob-date">
                                    <i class="bi bi-calendar3" aria-hidden="true" style="margin-right:3px;"></i>
                                    <?= date('M d, Y', strtotime($bl['created_at'])) ?>
                                </span>
                                <div style="display:flex; gap:8px;">
                                    <a href="view-bl.php?id=<?= (int)$bl['id'] ?>"
                                        class="action-btn" aria-label="View <?= htmlspecialchars($bl['bl_number']) ?>">
                                        <i class="bi bi-eye" aria-hidden="true"></i>
                                    </a>
                                    <a href="edit-bl.php?id=<?= (int)$bl['id'] ?>"
                                        class="action-btn" aria-label="Edit <?= htmlspecialchars($bl['bl_number']) ?>">
                                        <i class="bi bi-pencil" aria-hidden="true"></i>
                                    </a>
                                </div>
                            </div>

                        </article>
                    <?php endforeach ?>
                </div>

            <?php endif ?>

        </div><!-- /.section-card -->

    </div><!-- /.dash-wrapper -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>

<?php require_once 'includes/footer.php'; ?>