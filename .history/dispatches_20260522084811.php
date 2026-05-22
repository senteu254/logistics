<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/database.php';
$page_title = 'Dispatches';

// Filters
$status_filter = isset($_GET['status']) && $_GET['status'] !== ''
    ? trim($_GET['status'])
    : null;
$search        = trim($_GET['search'] ?? '');
$perPage       = 15;
$page          = max(1, intval($_GET['page'] ?? 1));
$offset        = ($page - 1) * $perPage;

// Build WHERE
$where  = ['1=1'];
$params = [];

if ($status_filter) {
    $where[]  = "d.status = ?";
    $params[] = $status_filter;
}

if ($search) {
    $where[]  = "(b.bl_number LIKE ? OR d.truck_number LIKE ? OR d.transporter_name LIKE ? OR i.container_number LIKE ? OR d.destination LIKE ?)";
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s, $s, $s]);
}

$whereSQL = implode(' AND ', $where);

// Count
$countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM dispatches d
    JOIN bl_items i          ON d.bl_item_id = i.id
    JOIN bills_of_lading b   ON i.bl_id      = b.id
    WHERE $whereSQL
");
$countStmt->execute($params);
$totalRows  = $countStmt->fetchColumn();
$totalPages = max(1, ceil($totalRows / $perPage));

// Fetch
$dataStmt = $pdo->prepare("
    SELECT
        d.*,
        i.container_number,
        i.number_of_bags,
        i.gross_weight,
        i.net_weight,
        i.bl_id,
        b.bl_number,
        b.item_description,
        u1.full_name   AS dispatched_by_name,
        u2.full_name   AS received_by_name
    FROM dispatches d
    JOIN bl_items i          ON d.bl_item_id = i.id
    JOIN bills_of_lading b   ON i.bl_id      = b.id
    LEFT JOIN users u1       ON d.dispatched_by = u1.id
    LEFT JOIN users u2       ON d.received_by   = u2.id
    WHERE $whereSQL
    ORDER BY d.dispatched_at DESC
    LIMIT $perPage OFFSET $offset
");
$dataStmt->execute($params);
$dispatches = $dataStmt->fetchAll();

// Summary counts
$counts = $pdo->query("
    SELECT
        COUNT(*) AS total,
        COALESCE(SUM(CASE WHEN status='transit'  THEN 1 ELSE 0 END), 0) AS transit,
        COALESCE(SUM(CASE WHEN status='received' THEN 1 ELSE 0 END), 0) AS received,
        COALESCE(SUM(CASE WHEN status='rejected' THEN 1 ELSE 0 END), 0) AS rejected
    FROM dispatches
")->fetch();

require_once 'includes/header.php';
?>

<style>
    /* ── CSS Variables ── */
    :root {
        --indigo: #6366f1;
        --violet: #8b5cf6;
        --emerald: #10b981;
        --cyan: #06b6d4;
        --amber: #f59e0b;
        --rose: #f43f5e;
        --txt-1: #111827;
        --txt-2: #6b7280;
        --txt-3: #9ca3af;
        --border: #e5e7eb;
        --border-l: #f3f4f6;
        --bg: #f9fafb;
        --r-sm: 8px;
        --r-md: 12px;
        --r-lg: 16px;
        --shadow-sm: 0 1px 3px rgba(0, 0, 0, .07);
        --shadow-md: 0 4px 16px rgba(0, 0, 0, .10);
        --t: all .18s ease;
    }

    /* ── Page Header ── */
    .page-header {
        margin-bottom: 1.5rem;
    }

    .page-title {
        font-size: clamp(1.3rem, 4vw, 1.75rem);
        font-weight: 800;
        color: var(--txt-1);
        margin: 0;
    }

    .page-sub {
        font-size: 0.875rem;
        color: var(--txt-2);
        margin: .25rem 0 0;
    }

    /* ── Stat Cards ── */
    .stat-card {
        background: white;
        border: 1px solid var(--border);
        border-radius: var(--r-lg);
        padding: 1.1rem 1.25rem;
        display: flex;
        align-items: center;
        gap: 14px;
        box-shadow: var(--shadow-sm);
        transition: var(--t);
    }

    .stat-card:hover {
        box-shadow: var(--shadow-md);
        transform: translateY(-2px);
    }

    .stat-ico {
        width: 44px;
        height: 44px;
        border-radius: var(--r-md);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        color: white;
        flex-shrink: 0;
    }

    .stat-ico.c-indigo {
        background: linear-gradient(135deg, var(--indigo), var(--violet));
    }

    .stat-ico.c-amber {
        background: linear-gradient(135deg, var(--amber), #f97316);
    }

    .stat-ico.c-emerald {
        background: linear-gradient(135deg, var(--emerald), var(--cyan));
    }

    .stat-ico.c-rose {
        background: linear-gradient(135deg, var(--rose), #f97316);
    }

    .stat-val {
        font-size: 1.5rem;
        font-weight: 800;
        color: var(--txt-1);
        line-height: 1;
    }

    .stat-lbl {
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--txt-2);
        margin-top: 3px;
        text-transform: uppercase;
        letter-spacing: .04em;
    }

    /* ── Alert ── */
    .alert-m {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: .875rem 1.25rem;
        border-radius: var(--r-md);
        margin-bottom: 1rem;
        font-size: .875rem;
        font-weight: 500;
    }

    .alert-success {
        background: rgba(16, 185, 129, .08);
        border: 1px solid rgba(16, 185, 129, .25);
        color: #065f46;
    }

    .alert-ico {
        font-size: 1.1rem;
    }

    /* ── Status Tabs ── */
    .status-tabs {
        display: flex;
        gap: 4px;
        background: white;
        border: 1px solid var(--border);
        border-radius: var(--r-lg);
        padding: 5px;
        margin-bottom: 1.25rem;
        overflow-x: auto;
        box-shadow: var(--shadow-sm);
        flex-wrap: nowrap;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
    }

    .status-tabs::-webkit-scrollbar {
        display: none;
    }

    .status-tab {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 8px 14px;
        border-radius: var(--r-md);
        font-size: 0.82rem;
        font-weight: 600;
        color: var(--txt-2);
        text-decoration: none;
        transition: var(--t);
        white-space: nowrap;
        border: none;
        background: none;
        flex-shrink: 0;
    }

    .status-tab:hover {
        background: var(--bg);
        color: var(--txt-1);
    }

    .status-tab.active {
        background: linear-gradient(135deg, var(--indigo), var(--violet));
        color: white;
        box-shadow: 0 3px 12px rgba(99, 102, 241, .3);
    }

    .tab-count {
        background: rgba(255, 255, 255, .25);
        padding: 1px 7px;
        border-radius: 20px;
        font-size: .7rem;
    }

    .status-tab:not(.active) .tab-count {
        background: var(--border);
        color: var(--txt-2);
    }

    /* ── Filter Bar ── */
    .filter-bar {
        background: white;
        border: 1px solid var(--border);
        border-radius: var(--r-lg);
        padding: .875rem 1.1rem;
        margin-bottom: 1.25rem;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        box-shadow: var(--shadow-sm);
    }

    .search-wrap {
        position: relative;
        flex: 1;
        min-width: 180px;
    }

    .search-wrap i {
        position: absolute;
        left: 11px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--txt-3);
        font-size: .8rem;
        pointer-events: none;
    }

    .search-input {
        width: 100%;
        padding: 8px 11px 8px 32px;
        border: 1.5px solid var(--border);
        border-radius: var(--r-sm);
        background: var(--bg);
        font-size: .86rem;
        color: var(--txt-1);
        outline: none;
        transition: var(--t);
        font-family: inherit;
    }

    .search-input:focus {
        border-color: var(--indigo);
        background: white;
        box-shadow: 0 0 0 3px rgba(99, 102, 241, .1);
    }

    /* ── Buttons ── */
    .btn-m {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 8px 16px;
        border-radius: var(--r-sm);
        font-size: .84rem;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        transition: var(--t);
        border: none;
        font-family: inherit;
        white-space: nowrap;
    }

    .btn-indigo {
        background: linear-gradient(135deg, var(--indigo), var(--violet));
        color: white;
    }

    .btn-indigo:hover {
        opacity: .9;
        transform: translateY(-1px);
        box-shadow: 0 4px 14px rgba(99, 102, 241, .3);
        color: white;
    }

    .btn-ghost {
        background: transparent;
        color: var(--txt-2);
        border: 1.5px solid var(--border);
    }

    .btn-ghost:hover {
        background: var(--bg);
        color: var(--txt-1);
    }

    .btn-sm {
        padding: 6px 12px;
        font-size: .8rem;
    }

    /* ── Dispatch Card ── */
    .dispatch-card {
        background: white;
        border: 1px solid var(--border);
        border-radius: var(--r-lg);
        overflow: hidden;
        margin-bottom: 12px;
        transition: var(--t);
    }

    .dispatch-card:hover {
        box-shadow: var(--shadow-md);
        border-color: rgba(99, 102, 241, .2);
        transform: translateY(-1px);
    }

    .dispatch-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: .875rem 1.1rem;
        border-bottom: 1px solid var(--border-l);
        flex-wrap: wrap;
        gap: 10px;
        background: #fafbff;
    }

    .dispatch-head-left {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        min-width: 0;
    }

    .dispatch-head-right {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        flex-shrink: 0;
    }

    .container-avatar {
        width: 36px;
        height: 36px;
        flex-shrink: 0;
        background: linear-gradient(135deg, var(--indigo), var(--violet));
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: .8rem;
    }

    .container-num {
        font-family: 'Courier New', monospace;
        font-weight: 700;
        color: var(--indigo);
        font-size: .9rem;
    }

    .bl-ref {
        font-size: .72rem;
        color: var(--txt-2);
    }

    .bl-ref a {
        color: var(--indigo);
        font-weight: 600;
        text-decoration: none;
    }

    .truck-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        background: var(--bg);
        border-radius: var(--r-sm);
        border: 1px solid var(--border);
        font-weight: 700;
        font-size: .8rem;
        font-family: 'Courier New', monospace;
    }

    .dest-chip {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: .8rem;
        color: var(--txt-2);
    }

    .dest-chip strong {
        color: var(--txt-1);
    }

    /* ── Dispatch Body Grid ── */
    .dispatch-body {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: .75rem 1rem;
        padding: .875rem 1.1rem;
    }

    @media (min-width: 576px) {
        .dispatch-body {
            grid-template-columns: repeat(3, 1fr);
        }
    }

    @media (min-width: 768px) {
        .dispatch-body {
            grid-template-columns: repeat(4, 1fr);
        }
    }

    @media (min-width: 992px) {
        .dispatch-body {
            grid-template-columns: repeat(5, 1fr);
        }
    }

    .d-info-lbl {
        font-size: .65rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .07em;
        color: var(--txt-3);
        margin-bottom: 3px;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .d-info-val {
        font-size: .845rem;
        font-weight: 600;
        color: var(--txt-1);
    }

    .d-info-val.mono {
        font-family: 'Courier New', monospace;
        color: var(--indigo);
    }

    .d-info-val.muted {
        color: var(--txt-3);
        font-style: italic;
        font-weight: 400;
    }

    .full-width {
        grid-column: 1 / -1;
    }

    /* ── Badges ── */
    .ds-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 11px;
        border-radius: 20px;
        font-size: .72rem;
        font-weight: 700;
    }

    .ds-transit {
        background: rgba(245, 158, 11, .1);
        color: #d97706;
        border: 1px solid rgba(245, 158, 11, .3);
    }

    .ds-received {
        background: rgba(16, 185, 129, .1);
        color: #059669;
        border: 1px solid rgba(16, 185, 129, .3);
    }

    .ds-rejected {
        background: rgba(244, 63, 94, .1);
        color: #e11d48;
        border: 1px solid rgba(244, 63, 94, .3);
    }

    .pulse-dot {
        width: 7px;
        height: 7px;
        background: var(--amber);
        border-radius: 50%;
        animation: pulseRing 1.5s infinite;
    }

    @keyframes pulseRing {
        0% {
            box-shadow: 0 0 0 0 rgba(245, 158, 11, .5);
        }

        70% {
            box-shadow: 0 0 0 6px rgba(245, 158, 11, 0);
        }

        100% {
            box-shadow: 0 0 0 0 rgba(245, 158, 11, 0);
        }
    }

    /* ── Empty State ── */
    .empty-state {
        text-align: center;
        padding: 3rem 1rem;
    }

    .empty-ico {
        font-size: 2.5rem;
        color: var(--txt-3);
        margin-bottom: 1rem;
    }

    .empty-state h5 {
        font-weight: 700;
        color: var(--txt-1);
    }

    .empty-state p {
        color: var(--txt-2);
        font-size: .875rem;
    }

    /* ── Pagination ── */
    .pg-wrap {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: .875rem 1.1rem;
        flex-wrap: wrap;
        gap: 10px;
        border-top: 1px solid var(--border);
    }

    .pg-info {
        font-size: .8rem;
        color: var(--txt-2);
        font-weight: 500;
    }

    .pg-btns {
        display: flex;
        gap: 4px;
        flex-wrap: wrap;
    }

    .pg-btn {
        min-width: 32px;
        height: 32px;
        padding: 0 8px;
        border: 1.5px solid var(--border);
        border-radius: 8px;
        background: white;
        color: var(--txt-2);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: .8rem;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        transition: var(--t);
    }

    .pg-btn:hover {
        border-color: var(--indigo);
        color: var(--indigo);
    }

    .pg-btn.active {
        background: linear-gradient(135deg, var(--indigo), var(--violet));
        border-color: transparent;
        color: white;
    }

    .pg-btn.disabled {
        opacity: .4;
        pointer-events: none;
    }

    /* ── Data Card wrapper ── */
    .data-card {
        background: white;
        border: 1px solid var(--border);
        border-radius: var(--r-lg);
        box-shadow: var(--shadow-sm);
        overflow: hidden;
    }

    /* ── Modal ── */
    .modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, .6);
        backdrop-filter: blur(8px);
        z-index: 2000;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        opacity: 0;
        pointer-events: none;
        transition: opacity .25s ease;
    }

    .modal-overlay.show {
        opacity: 1;
        pointer-events: all;
    }

    .modal-box {
        background: white;
        border-radius: 20px;
        max-width: 500px;
        width: 100%;
        box-shadow: 0 25px 80px rgba(0, 0, 0, .25);
        transform: scale(.94) translateY(10px);
        transition: transform .3s cubic-bezier(.22, 1, .36, 1);
        overflow: hidden;
        max-height: 90vh;
        overflow-y: auto;
    }

    .modal-overlay.show .modal-box {
        transform: scale(1) translateY(0);
    }

    .modal-head {
        padding: 1.1rem 1.5rem;
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: center;
        gap: 12px;
        background: linear-gradient(to right, #fafbff, white);
        position: sticky;
        top: 0;
    }

    .modal-head-ico {
        width: 40px;
        height: 40px;
        border-radius: var(--r-sm);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        color: white;
        flex-shrink: 0;
    }

    .modal-head h5 {
        font-size: .95rem;
        font-weight: 700;
        color: var(--txt-1);
        margin: 0;
    }

    .modal-head p {
        font-size: .76rem;
        color: var(--txt-2);
        margin: 0;
    }

    .modal-body {
        padding: 1.25rem 1.5rem;
    }

    .modal-foot {
        padding: .875rem 1.5rem;
        border-top: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 10px;
        background: #fafbff;
        position: sticky;
        bottom: 0;
    }

    .outcome-btns {
        display: flex;
        gap: 10px;
        margin-bottom: 1rem;
    }

    .outcome-btn {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 6px;
        padding: .9rem .5rem;
        border-radius: var(--r-md);
        border: 2px solid var(--border);
        background: white;
        cursor: pointer;
        transition: var(--t);
        font-family: inherit;
    }

    .outcome-btn i {
        font-size: 1.4rem;
    }

    .outcome-btn span {
        font-size: .84rem;
        font-weight: 700;
    }

    .outcome-btn small {
        font-size: .7rem;
        color: var(--txt-2);
        text-align: center;
    }

    .outcome-btn.received-btn:hover,
    .outcome-btn.received-btn.selected {
        border-color: var(--emerald);
        background: rgba(16, 185, 129, .06);
        color: var(--emerald);
    }

    .outcome-btn.rejected-btn:hover,
    .outcome-btn.rejected-btn.selected {
        border-color: var(--rose);
        background: rgba(244, 63, 94, .06);
        color: var(--rose);
    }

    .rejection-wrap {
        display: none;
        animation: slideDown .25s ease;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-5px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .f-label {
        display: block;
        font-size: .8rem;
        font-weight: 600;
        color: var(--txt-2);
        margin-bottom: 5px;
    }

    .req {
        color: var(--rose);
    }

    .f-input {
        width: 100%;
        padding: 8px 11px;
        border: 1.5px solid var(--border);
        border-radius: var(--r-sm);
        font-family: inherit;
        font-size: .875rem;
        color: var(--txt-1);
        outline: none;
        transition: var(--t);
        resize: vertical;
    }

    .f-input:focus {
        border-color: var(--indigo);
        box-shadow: 0 0 0 3px rgba(99, 102, 241, .1);
    }

    /* ── Mobile Adjustments ── */
    @media (max-width: 575.98px) {
        .page-header {
            gap: .75rem;
        }

        .stat-card {
            padding: .875rem 1rem;
            gap: 10px;
        }

        .stat-val {
            font-size: 1.25rem;
        }

        .stat-ico {
            width: 38px;
            height: 38px;
        }

        .status-tab {
            padding: 7px 11px;
            font-size: .78rem;
        }

        .dispatch-head {
            padding: .75rem .875rem;
        }

        .dispatch-body {
            padding: .75rem .875rem;
            gap: .65rem .75rem;
        }

        .truck-chip,
        .dest-chip {
            display: none;
        }

        /* shown in body on small screens */

        .modal-box {
            border-radius: 20px 20px 0 0;
        }

        .modal-overlay {
            align-items: flex-end;
            padding: 0;
        }

        .outcome-btns {
            gap: 8px;
        }

        .outcome-btn {
            padding: .75rem .4rem;
        }

        .outcome-btn small {
            display: none;
        }
    }

    /* ── Records counter ── */
    .rec-count {
        color: var(--txt-3);
        font-size: .8rem;
        font-weight: 500;
        white-space: nowrap;
        margin-left: auto;
    }
</style>

<!-- Page Header -->
<div class="page-header d-flex align-items-start justify-content-between flex-wrap gap-2">
    <div>
        <h1 class="page-title">Dispatches</h1>
        <p class="page-sub">Track all container dispatches and delivery status</p>
    </div>
    <a href="manage-bl.php" class="btn-m btn-indigo">
        <i class="fas fa-list-check"></i>
        <span class="d-none d-sm-inline">Manage B/L</span>
        <span class="d-sm-none">B/L</span>
    </a>
</div>

<!-- Flash Messages -->
<?php if (isset($_GET['updated'])): ?>
    <div class="alert-m alert-success" id="flashAlert">
        <i class="fas fa-circle-check alert-ico" style="color:var(--emerald);"></i>
        <div>Dispatch status updated successfully.</div>
    </div>
    <script>
        setTimeout(() => {
            const el = document.getElementById('flashAlert');
            if (el) {
                el.style.transition = 'opacity .5s';
                el.style.opacity = '0';
                setTimeout(() => el.remove(), 500);
            }
        }, 4000);
    </script>
<?php endif; ?>

<!-- Summary Stat Cards -->
<div class="row g-2 g-sm-3 mb-3">
    <?php
    $stats = [
        ['c-indigo',  'fa-truck-loading', $counts['total'],    'Total Dispatches'],
        ['c-amber',   'fa-truck',         $counts['transit'],  'In Transit'],
        ['c-emerald', 'fa-circle-check',  $counts['received'], 'Received'],
        ['c-rose',    'fa-circle-xmark',  $counts['rejected'], 'Rejected'],
    ];
    foreach ($stats as [$cls, $ico, $val, $lbl]):
    ?>
        <div class="col-6 col-sm-6 col-md-3">
            <div class="stat-card">
                <div class="stat-ico <?php echo $cls; ?>"><i class="fas <?php echo $ico; ?>"></i></div>
                <div>
                    <div class="stat-val"><?php echo number_format($val); ?></div>
                    <div class="stat-lbl"><?php echo $lbl; ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Status Tabs -->
<div class="status-tabs">
    <?php
    $tabs = [
        ['',         'All',        'fas fa-list',         $counts['total']],
        ['transit',  'In Transit', 'fas fa-truck',        $counts['transit']],
        ['received', 'Received',   'fas fa-circle-check', $counts['received']],
        ['rejected', 'Rejected',   'fas fa-circle-xmark', $counts['rejected']],
    ];
    foreach ($tabs as [$val, $label, $icon, $count]):
        $qs     = http_build_query(['status' => $val, 'search' => $search, 'page' => 1]);
        $active = ($status_filter === ($val ?: null) || ($val === '' && $status_filter === null)) ? 'active' : '';
    ?>
        <a href="?<?php echo $qs; ?>" class="status-tab <?php echo $active; ?>">
            <i class="<?php echo $icon; ?>"></i>
            <?php echo $label; ?>
            <span class="tab-count"><?php echo $count; ?></span>
        </a>
    <?php endforeach; ?>
</div>

<!-- Filter Bar -->
<form method="GET" id="filterForm">
    <input type="hidden" name="status" value="<?php echo htmlspecialchars((string)$status_filter); ?>">
    <div class="filter-bar">
        <div class="search-wrap">
            <i class="fas fa-magnifying-glass"></i>
            <input
                type="text" name="search" class="search-input"
                placeholder="Search B/L, container, truck, transporter…"
                value="<?php echo htmlspecialchars($search); ?>"
                oninput="clearTimeout(window._st);window._st=setTimeout(()=>this.form.submit(),500)">
        </div>
        <?php if ($search): ?>
            <a href="?status=<?php echo urlencode((string)$status_filter); ?>" class="btn-m btn-ghost btn-sm">
                <i class="fas fa-xmark"></i> <span class="d-none d-sm-inline">Clear</span>
            </a>
        <?php endif; ?>
        <div class="rec-count"><?php echo number_format($totalRows); ?> record<?php echo $totalRows != 1 ? 's' : ''; ?></div>
    </div>
</form>

<!-- Dispatch List -->
<?php if (empty($dispatches)): ?>
    <div class="data-card">
        <div class="empty-state">
            <div class="empty-ico"><i class="fas fa-truck"></i></div>
            <h5>No Dispatches Found</h5>
            <p><?php echo $search || $status_filter ? 'Try adjusting your filters.' : 'No containers have been dispatched yet.'; ?></p>
            <a href="manage-bl.php" class="btn-m btn-indigo" style="width:fit-content;margin:.75rem auto 0;">
                <i class="fas fa-list-check"></i> View Bills of Lading
            </a>
        </div>
    </div>

<?php else: ?>

    <?php foreach ($dispatches as $d): ?>
        <div class="dispatch-card">

            <!-- Card Header -->
            <div class="dispatch-head">
                <div class="dispatch-head-left">

                    <!-- Container -->
                    <div style="display:flex;align-items:center;gap:9px;min-width:0;">
                        <div class="container-avatar"><i class="fas fa-ship"></i></div>
                        <div style="min-width:0;">
                            <div class="container-num"><?php echo htmlspecialchars($d['container_number']); ?></div>
                            <div class="bl-ref">B/L:&nbsp;<a href="view-bl.php?id=<?php echo $d['bl_id']; ?>"><?php echo htmlspecialchars($d['bl_number']); ?></a></div>
                        </div>
                    </div>

                    <!-- Truck (hidden on xs, shown md+) -->
                    <div class="truck-chip d-none d-md-inline-flex">
                        <i class="fas fa-truck" style="color:var(--indigo);font-size:.75rem;"></i>
                        <?php echo htmlspecialchars($d['truck_number']); ?>
                    </div>

                    <!-- Destination (hidden on xs, shown sm+) -->
                    <div class="dest-chip d-none d-sm-flex">
                        <i class="fas fa-location-dot" style="color:var(--rose);"></i>
                        <strong><?php echo htmlspecialchars($d['destination']); ?></strong>
                    </div>

                </div>

                <!-- Right: badge + actions -->
                <div class="dispatch-head-right">
                    <?php if ($d['status'] === 'transit'): ?>
                        <span class="ds-badge ds-transit"><span class="pulse-dot"></span> <span class="d-none d-sm-inline">In </span>Transit</span>
                        <button type="button" class="btn-m btn-ghost btn-sm" onclick="openOutcome(
                    <?php echo $d['id']; ?>,
                    '<?php echo htmlspecialchars($d['container_number']); ?>',
                    <?php echo $d['bl_item_id']; ?>,
                    <?php echo $d['bl_id']; ?>
                )">
                            <i class="fas fa-flag-checkered"></i>
                            <span class="d-none d-sm-inline">Update</span>
                        </button>
                    <?php elseif ($d['status'] === 'received'): ?>
                        <span class="ds-badge ds-received"><i class="fas fa-circle-check"></i> <span class="d-none d-sm-inline">Received</span></span>
                    <?php elseif ($d['status'] === 'rejected'): ?>
                        <span class="ds-badge ds-rejected"><i class="fas fa-circle-xmark"></i> <span class="d-none d-sm-inline">Rejected</span></span>
                    <?php endif; ?>

                    <a href="view-bl.php?id=<?php echo $d['bl_id']; ?>" class="btn-m btn-ghost btn-sm">
                        <i class="fas fa-eye"></i>
                    </a>
                </div>
            </div>

            <!-- Card Body -->
            <div class="dispatch-body">

                <!-- On mobile show truck + destination here -->
                <div class="d-info-item d-md-none">
                    <div class="d-info-lbl"><i class="fas fa-truck"></i> Truck</div>
                    <div class="d-info-val mono"><?php echo htmlspecialchars($d['truck_number']); ?></div>
                </div>
                <div class="d-info-item d-sm-none">
                    <div class="d-info-lbl"><i class="fas fa-location-dot"></i> Destination</div>
                    <div class="d-info-val"><?php echo htmlspecialchars($d['destination']); ?></div>
                </div>

                <div class="d-info-item">
                    <div class="d-info-lbl"><i class="fas fa-user-tie"></i> Transporter</div>
                    <div class="d-info-val"><?php echo htmlspecialchars($d['transporter_name']); ?></div>
                </div>

                <div class="d-info-item">
                    <div class="d-info-lbl"><i class="fas fa-boxes-stacked"></i> Bags / Weight</div>
                    <div class="d-info-val" style="font-size:.8rem;">
                        <?php echo number_format($d['number_of_bags']); ?> bags &nbsp;·&nbsp;
                        <?php echo number_format($d['gross_weight'], 3); ?> MT
                    </div>
                </div>

                <div class="d-info-item">
                    <div class="d-info-lbl"><i class="fas fa-file-lines"></i> CA D/Note</div>
                    <div class="d-info-val <?php echo $d['clearing_agent_dnote'] ? 'mono' : 'muted'; ?>">
                        <?php echo $d['clearing_agent_dnote'] ? htmlspecialchars($d['clearing_agent_dnote']) : '—'; ?>
                    </div>
                </div>

                <div class="d-info-item">
                    <div class="d-info-lbl"><i class="fas fa-file-alt"></i> TP D/Note</div>
                    <div class="d-info-val <?php echo $d['transporter_dnote'] ? 'mono' : 'muted'; ?>">
                        <?php echo $d['transporter_dnote'] ? htmlspecialchars($d['transporter_dnote']) : '—'; ?>
                    </div>
                </div>

                <div class="d-info-item">
                    <div class="d-info-lbl"><i class="fas fa-clock"></i> Dispatched</div>
                    <div class="d-info-val" style="font-size:.79rem;">
                        <?php echo date('M d, Y H:i', strtotime($d['dispatched_at'])); ?>
                    </div>
                </div>

                <?php if ($d['status'] !== 'transit' && $d['received_at']): ?>
                    <div class="d-info-item">
                        <div class="d-info-lbl">
                            <i class="fas fa-<?php echo $d['status'] === 'received' ? 'circle-check' : 'circle-xmark'; ?>"></i>
                            <?php echo ucfirst($d['status']); ?> At
                        </div>
                        <div class="d-info-val" style="font-size:.79rem;color:<?php echo $d['status'] === 'received' ? 'var(--emerald)' : 'var(--rose)'; ?>">
                            <?php echo date('M d, Y H:i', strtotime($d['received_at'])); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($d['status'] === 'rejected' && $d['rejection_reason']): ?>
                    <div class="d-info-item full-width">
                        <div class="d-info-lbl"><i class="fas fa-comment-exclamation"></i> Rejection Reason</div>
                        <div class="d-info-val" style="color:var(--rose);font-size:.82rem;">
                            <?php echo htmlspecialchars($d['rejection_reason']); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($d['notes']): ?>
                    <div class="d-info-item full-width">
                        <div class="d-info-lbl"><i class="fas fa-note-sticky"></i> Notes</div>
                        <div class="d-info-val" style="font-size:.82rem;color:var(--txt-2);">
                            <?php echo htmlspecialchars($d['notes']); ?>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    <?php endforeach; ?>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="data-card mt-2">
            <div class="pg-wrap">
                <div class="pg-info">
                    Showing <strong><?php echo $offset + 1; ?>–<?php echo min($offset + $perPage, $totalRows); ?></strong>
                    of <strong><?php echo number_format($totalRows); ?></strong>
                </div>
                <div class="pg-btns">
                    <?php $qs = http_build_query(['status' => $status_filter, 'search' => $search, 'page' => $page - 1]); ?>
                    <a href="?<?php echo $qs; ?>" class="pg-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <?php
                    // Show smart page range to avoid overflow on mobile
                    $range = 2;
                    for ($p = 1; $p <= $totalPages; $p++):
                        if ($p !== 1 && $p !== $totalPages && abs($p - $page) > $range) {
                            if ($p === 2 || $p === $totalPages - 1) echo '<span class="pg-btn disabled" style="border:none;color:var(--txt-3);">…</span>';
                            continue;
                        }
                        $qs = http_build_query(['status' => $status_filter, 'search' => $search, 'page' => $p]);
                    ?>
                        <a href="?<?php echo $qs; ?>" class="pg-btn <?php echo $p == $page ? 'active' : ''; ?>"><?php echo $p; ?></a>
                    <?php endfor; ?>
                    <?php $qs = http_build_query(['status' => $status_filter, 'search' => $search, 'page' => $page + 1]); ?>
                    <a href="?<?php echo $qs; ?>" class="pg-btn <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<!-- ══ UPDATE STATUS MODAL ══ -->
<div class="modal-overlay" id="outcomeModal">
    <div class="modal-box">
        <div class="modal-head">
            <div class="modal-head-ico" style="background:linear-gradient(135deg,var(--emerald),var(--cyan));">
                <i class="fas fa-flag-checkered"></i>
            </div>
            <div>
                <h5>Update Delivery Status</h5>
                <p id="outcomeLabel">Select the delivery outcome</p>
            </div>
        </div>
        <div class="modal-body">
            <form id="outcomeForm" method="POST" action="dispatch-action.php">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="dispatch_id" id="outcomeDispatchId">
                <input type="hidden" name="bl_item_id" id="outcomeItemId">
                <input type="hidden" name="redirect_id" id="outcomeRedirectId">
                <input type="hidden" name="new_status" id="outcomeStatus" value="">

                <div class="outcome-btns">
                    <button type="button" class="outcome-btn received-btn" onclick="selectOutcome('received')">
                        <i class="fas fa-circle-check" style="color:var(--emerald);"></i>
                        <span>Received</span>
                        <small>Delivered successfully</small>
                    </button>
                    <button type="button" class="outcome-btn rejected-btn" onclick="selectOutcome('rejected')">
                        <i class="fas fa-circle-xmark" style="color:var(--rose);"></i>
                        <span>Rejected</span>
                        <small>Container not accepted</small>
                    </button>
                </div>

                <div class="rejection-wrap" id="rejectionWrap">
                    <label class="f-label">Rejection Reason <span class="req">*</span></label>
                    <textarea name="rejection_reason" id="rejectionReason"
                        class="f-input" rows="3"
                        placeholder="Why was the container rejected?"></textarea>
                </div>

                <div style="margin-top:12px;">
                    <label class="f-label">Notes (Optional)</label>
                    <textarea name="notes" class="f-input" rows="2"
                        placeholder="Additional delivery notes…"></textarea>
                </div>
            </form>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn-m btn-ghost" onclick="closeModal()">
                <i class="fas fa-xmark"></i> Cancel
            </button>
            <button type="button" class="btn-m btn-indigo" id="confirmBtn" onclick="submitOutcome()" disabled>
                <i class="fas fa-check"></i> Confirm
            </button>
        </div>
    </div>
</div>

<script>
    let selectedOutcome = '';

    function openOutcome(dispatchId, containerNum, itemId, blId) {
        selectedOutcome = '';
        document.getElementById('outcomeDispatchId').value = dispatchId;
        document.getElementById('outcomeItemId').value = itemId;
        document.getElementById('outcomeRedirectId').value = blId;
        document.getElementById('outcomeLabel').textContent = 'Container: ' + containerNum;
        document.getElementById('outcomeStatus').value = '';
        document.getElementById('rejectionWrap').style.display = 'none';
        document.getElementById('rejectionReason').value = '';
        document.getElementById('confirmBtn').disabled = true;
        document.querySelectorAll('.outcome-btn').forEach(b => b.classList.remove('selected'));
        document.getElementById('outcomeModal').classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        document.getElementById('outcomeModal').classList.remove('show');
        document.body.style.overflow = '';
    }

    function selectOutcome(outcome) {
        selectedOutcome = outcome;
        document.getElementById('outcomeStatus').value = outcome;
        document.getElementById('confirmBtn').disabled = false;
        document.querySelectorAll('.outcome-btn').forEach(b => b.classList.remove('selected'));
        document.querySelector('.' + outcome + '-btn').classList.add('selected');
        document.getElementById('rejectionWrap').style.display = outcome === 'rejected' ? 'block' : 'none';
    }

    function submitOutcome() {
        if (!selectedOutcome) return;
        if (selectedOutcome === 'rejected') {
            const r = document.getElementById('rejectionReason').value.trim();
            if (!r) {
                document.getElementById('rejectionReason').style.borderColor = 'var(--rose)';
                document.getElementById('rejectionReason').focus();
                return;
            }
        }
        const btn = document.getElementById('confirmBtn');
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating…';
        btn.disabled = true;
        document.getElementById('outcomeForm').submit();
    }

    document.getElementById('outcomeModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });

    // Close modal on ESC
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeModal();
    });
</script>

<?php require_once 'includes/footer.php'; ?>