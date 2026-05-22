<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/database.php';
$page_title = 'Dispatches';

$status_filter = isset($_GET['status']) && $_GET['status'] !== '' ? trim($_GET['status']) : null;
$search        = trim($_GET['search'] ?? '');
$perPage       = 15;
$page          = max(1, intval($_GET['page'] ?? 1));
$offset        = ($page - 1) * $perPage;

$where  = ['1=1'];
$params = [];

if ($status_filter) {
    $where[] = "d.status = ?";
    $params[] = $status_filter;
}
if ($search) {
    $where[] = "(b.bl_number LIKE ? OR d.truck_number LIKE ? OR d.transporter_name LIKE ? OR i.container_number LIKE ? OR d.destination LIKE ?)";
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s, $s, $s]);
}
$whereSQL = implode(' AND ', $where);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM dispatches d JOIN bl_items i ON d.bl_item_id = i.id JOIN bills_of_lading b ON i.bl_id = b.id WHERE $whereSQL");
$countStmt->execute($params);
$totalRows  = $countStmt->fetchColumn();
$totalPages = max(1, ceil($totalRows / $perPage));

$dataStmt = $pdo->prepare("
    SELECT d.*, i.container_number, i.number_of_bags, i.gross_weight, i.net_weight, i.bl_id,
           b.bl_number, b.item_description,
           u1.full_name AS dispatched_by_name, u2.full_name AS received_by_name
    FROM dispatches d
    JOIN bl_items i ON d.bl_item_id = i.id
    JOIN bills_of_lading b ON i.bl_id = b.id
    LEFT JOIN users u1 ON d.dispatched_by = u1.id
    LEFT JOIN users u2 ON d.received_by = u2.id
    WHERE $whereSQL ORDER BY d.dispatched_at DESC LIMIT $perPage OFFSET $offset
");
$dataStmt->execute($params);
$dispatches = $dataStmt->fetchAll();

$counts = $pdo->query("SELECT COUNT(*) AS total,
    COALESCE(SUM(CASE WHEN status='transit'  THEN 1 ELSE 0 END),0) AS transit,
    COALESCE(SUM(CASE WHEN status='received' THEN 1 ELSE 0 END),0) AS received,
    COALESCE(SUM(CASE WHEN status='rejected' THEN 1 ELSE 0 END),0) AS rejected
    FROM dispatches")->fetch();

require_once 'includes/header.php';
?>
<style>
    /* ── Google Font ── */
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600&display=swap');

    /* ── Reset & Base ── */
    *,
    *::before,
    *::after {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }

    /* ── Tokens ── */
    :root {
        --c-bg: #f4f6fb;
        --c-surface: #ffffff;
        --c-border: #e8ecf4;
        --c-border2: #f0f3f9;

        --c-indigo: #4f46e5;
        --c-indigo-l: #eef2ff;
        --c-indigo-m: #c7d2fe;
        --c-violet: #7c3aed;

        --c-amber: #d97706;
        --c-amber-l: #fffbeb;
        --c-amber-m: #fde68a;

        --c-green: #059669;
        --c-green-l: #ecfdf5;
        --c-green-m: #a7f3d0;

        --c-red: #dc2626;
        --c-red-l: #fff1f2;
        --c-red-m: #fecdd3;

        --c-txt1: #0f172a;
        --c-txt2: #475569;
        --c-txt3: #94a3b8;

        --r-xs: 6px;
        --r-sm: 10px;
        --r-md: 14px;
        --r-lg: 18px;
        --r-xl: 24px;

        --shadow-xs: 0 1px 2px rgba(15, 23, 42, .04);
        --shadow-sm: 0 1px 4px rgba(15, 23, 42, .06), 0 2px 8px rgba(15, 23, 42, .04);
        --shadow-md: 0 4px 12px rgba(15, 23, 42, .08), 0 1px 3px rgba(15, 23, 42, .06);
        --shadow-lg: 0 12px 32px rgba(15, 23, 42, .12), 0 2px 8px rgba(15, 23, 42, .06);

        --ff-body: 'Plus Jakarta Sans', system-ui, sans-serif;
        --ff-mono: 'JetBrains Mono', 'Courier New', monospace;

        --ease: cubic-bezier(.22, 1, .36, 1);
        --t-fast: 140ms;
        --t-base: 220ms;
    }

    /* ── Utility ── */
    .d-page {
        font-family: var(--ff-body);
        background: var(--c-bg);
        color: var(--c-txt1);
        min-height: 100vh;
    }

    /* ── Page Header ── */
    .ph {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }

    .ph-title {
        font-size: clamp(1.35rem, 5vw, 1.9rem);
        font-weight: 800;
        letter-spacing: -.02em;
        color: var(--c-txt1);
        line-height: 1.15;
    }

    .ph-sub {
        font-size: .83rem;
        color: var(--c-txt3);
        margin-top: 3px;
        font-weight: 500;
    }

    /* ── Buttons ── */
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 9px 18px;
        border-radius: var(--r-sm);
        font-family: var(--ff-body);
        font-size: .84rem;
        font-weight: 700;
        cursor: pointer;
        text-decoration: none;
        transition: all var(--t-fast) var(--ease);
        border: none;
        white-space: nowrap;
        line-height: 1;
    }

    .btn-primary {
        background: var(--c-indigo);
        color: #fff;
        box-shadow: 0 2px 8px rgba(79, 70, 229, .3);
    }

    .btn-primary:hover {
        background: var(--c-violet);
        box-shadow: 0 4px 16px rgba(79, 70, 229, .4);
        color: #fff;
        transform: translateY(-1px);
    }

    .btn-ghost {
        background: var(--c-surface);
        color: var(--c-txt2);
        border: 1.5px solid var(--c-border);
        box-shadow: var(--shadow-xs);
    }

    .btn-ghost:hover {
        background: var(--c-bg);
        color: var(--c-txt1);
    }

    .btn-sm {
        padding: 6px 12px;
        font-size: .79rem;
        border-radius: var(--r-xs);
        gap: 5px;
    }

    .btn-icon {
        padding: 8px;
        border-radius: var(--r-xs);
    }

    /* ── Alert ── */
    .alert-success {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 16px;
        border-radius: var(--r-md);
        margin-bottom: 16px;
        background: var(--c-green-l);
        border: 1px solid var(--c-green-m);
        color: #065f46;
        font-size: .875rem;
        font-weight: 600;
    }

    /* ── Stat Grid ── */
    .stat-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
        margin-bottom: 20px;
    }

    @media (min-width: 640px) {
        .stat-grid {
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
        }
    }

    .stat-card {
        background: var(--c-surface);
        border: 1px solid var(--c-border);
        border-radius: var(--r-lg);
        padding: 16px;
        box-shadow: var(--shadow-sm);
        display: flex;
        flex-direction: column;
        gap: 10px;
        transition: all var(--t-base) var(--ease);
        position: relative;
        overflow: hidden;
    }

    .stat-card::after {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(135deg, transparent 60%, rgba(255, 255, 255, .6));
        pointer-events: none;
    }

    .stat-card:hover {
        box-shadow: var(--shadow-md);
        transform: translateY(-2px);
    }

    .stat-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 10px;
        border-radius: 50px;
        font-size: .78rem;
        font-weight: 700;
        align-self: flex-start;
    }

    .stat-pill i {
        font-size: .8rem;
    }

    .stat-pill-indigo {
        background: var(--c-indigo-l);
        color: var(--c-indigo);
    }

    .stat-pill-amber {
        background: var(--c-amber-l);
        color: var(--c-amber);
    }

    .stat-pill-green {
        background: var(--c-green-l);
        color: var(--c-green);
    }

    .stat-pill-red {
        background: var(--c-red-l);
        color: var(--c-red);
    }

    .stat-num {
        font-size: clamp(1.6rem, 5vw, 2.1rem);
        font-weight: 800;
        letter-spacing: -.03em;
        color: var(--c-txt1);
        line-height: 1;
    }

    .stat-label {
        font-size: .72rem;
        font-weight: 600;
        color: var(--c-txt3);
        text-transform: uppercase;
        letter-spacing: .06em;
    }

    /* ── Status Tabs ── */
    .tabs-scroll {
        overflow-x: auto;
        scrollbar-width: none;
        -webkit-overflow-scrolling: touch;
        margin-bottom: 14px;
        /* fade edges */
        -webkit-mask-image: linear-gradient(to right, transparent 0, black 16px, black calc(100% - 16px), transparent 100%);
        mask-image: linear-gradient(to right, transparent 0, black 16px, black calc(100% - 16px), transparent 100%);
    }

    .tabs-scroll::-webkit-scrollbar {
        display: none;
    }

    .tabs-inner {
        display: flex;
        gap: 6px;
        padding: 0 4px;
        background: var(--c-surface);
        border: 1px solid var(--c-border);
        border-radius: var(--r-xl);
        padding: 5px 6px;
        box-shadow: var(--shadow-xs);
        width: max-content;
        min-width: 100%;
    }

    .tab-link {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 8px 16px;
        border-radius: var(--r-md);
        font-size: .82rem;
        font-weight: 700;
        color: var(--c-txt2);
        text-decoration: none;
        white-space: nowrap;
        transition: all var(--t-fast) var(--ease);
        border: none;
        background: none;
        cursor: pointer;
        font-family: var(--ff-body);
    }

    .tab-link:hover {
        color: var(--c-txt1);
        background: var(--c-bg);
    }

    .tab-link.active {
        background: var(--c-indigo);
        color: #fff;
        box-shadow: 0 2px 10px rgba(79, 70, 229, .3);
    }

    .tab-badge {
        background: rgba(255, 255, 255, .25);
        color: inherit;
        padding: 1px 8px;
        border-radius: 50px;
        font-size: .7rem;
        font-weight: 700;
        min-width: 22px;
        text-align: center;
    }

    .tab-link:not(.active) .tab-badge {
        background: var(--c-border);
        color: var(--c-txt3);
    }

    /* ── Filter Bar ── */
    .filter-bar {
        background: var(--c-surface);
        border: 1px solid var(--c-border);
        border-radius: var(--r-lg);
        padding: 10px 14px;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        box-shadow: var(--shadow-xs);
        margin-bottom: 16px;
    }

    .search-box {
        position: relative;
        flex: 1;
        min-width: 160px;
    }

    .search-icon {
        position: absolute;
        left: 11px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--c-txt3);
        font-size: .78rem;
        pointer-events: none;
    }

    .search-input {
        width: 100%;
        padding: 9px 12px 9px 32px;
        border: 1.5px solid var(--c-border);
        border-radius: var(--r-sm);
        background: var(--c-bg);
        font-family: var(--ff-body);
        font-size: .85rem;
        color: var(--c-txt1);
        outline: none;
        transition: all var(--t-fast) var(--ease);
    }

    .search-input::placeholder {
        color: var(--c-txt3);
    }

    .search-input:focus {
        border-color: var(--c-indigo);
        background: #fff;
        box-shadow: 0 0 0 3px rgba(79, 70, 229, .1);
    }

    .rec-count {
        font-size: .78rem;
        color: var(--c-txt3);
        font-weight: 600;
        white-space: nowrap;
        margin-left: auto;
    }

    /* ── Dispatch Cards ── */
    .dispatch-card {
        background: var(--c-surface);
        border: 1px solid var(--c-border);
        border-radius: var(--r-lg);
        overflow: hidden;
        margin-bottom: 10px;
        box-shadow: var(--shadow-xs);
        transition: all var(--t-base) var(--ease);
    }

    .dispatch-card:hover {
        box-shadow: var(--shadow-md);
        border-color: var(--c-indigo-m);
        transform: translateY(-1px);
    }

    /* Card head */
    .dc-head {
        padding: 12px 16px;
        border-bottom: 1px solid var(--c-border2);
        background: linear-gradient(to bottom, #fcfdff, var(--c-surface));
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 10px;
        align-items: start;
    }

    .dc-head-left {
        display: flex;
        align-items: flex-start;
        gap: 11px;
        min-width: 0;
    }

    .dc-avatar {
        width: 40px;
        height: 40px;
        flex-shrink: 0;
        border-radius: var(--r-sm);
        background: var(--c-indigo-l);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--c-indigo);
        font-size: .9rem;
    }

    .dc-meta {
        min-width: 0;
    }

    .dc-container {
        font-family: var(--ff-mono);
        font-weight: 600;
        font-size: .9rem;
        color: var(--c-indigo);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .dc-bl {
        font-size: .72rem;
        color: var(--c-txt3);
        margin-top: 2px;
    }

    .dc-bl a {
        color: var(--c-indigo);
        font-weight: 700;
        text-decoration: none;
    }

    .dc-bl a:hover {
        text-decoration: underline;
    }

    /* chips row */
    .dc-chips {
        display: flex;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
        margin-top: 8px;
    }

    .chip {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 9px;
        border-radius: 50px;
        font-size: .73rem;
        font-weight: 600;
        background: var(--c-bg);
        border: 1px solid var(--c-border);
        color: var(--c-txt2);
        white-space: nowrap;
    }

    .chip-truck {
        font-family: var(--ff-mono);
        background: var(--c-indigo-l);
        border-color: var(--c-indigo-m);
        color: var(--c-indigo);
    }

    .chip i {
        font-size: .68rem;
    }

    /* actions */
    .dc-actions {
        display: flex;
        align-items: center;
        gap: 6px;
        flex-shrink: 0;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    /* Status badges */
    .badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 5px 11px;
        border-radius: 50px;
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .01em;
    }

    .badge-transit {
        background: var(--c-amber-l);
        color: var(--c-amber);
        border: 1px solid var(--c-amber-m);
    }

    .badge-received {
        background: var(--c-green-l);
        color: var(--c-green);
        border: 1px solid var(--c-green-m);
    }

    .badge-rejected {
        background: var(--c-red-l);
        color: var(--c-red);
        border: 1px solid var(--c-red-m);
    }

    .pulse {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: var(--c-amber);
        animation: pulse 1.6s ease infinite;
    }

    @keyframes pulse {

        0%,
        100% {
            box-shadow: 0 0 0 0 rgba(217, 119, 6, .5);
        }

        50% {
            box-shadow: 0 0 0 5px rgba(217, 119, 6, 0);
        }
    }

    /* Card body — data grid */
    .dc-body {
        padding: 12px 16px;
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 10px 14px;
    }

    @media (min-width: 480px) {
        .dc-body {
            grid-template-columns: repeat(3, 1fr);
        }
    }

    @media (min-width: 768px) {
        .dc-body {
            grid-template-columns: repeat(4, 1fr);
        }
    }

    @media (min-width: 1024px) {
        .dc-body {
            grid-template-columns: repeat(5, 1fr);
            gap: 10px 20px;
        }
    }

    .di-label {
        font-size: .63rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: var(--c-txt3);
        display: flex;
        align-items: center;
        gap: 4px;
        margin-bottom: 3px;
    }

    .di-val {
        font-size: .84rem;
        font-weight: 600;
        color: var(--c-txt1);
    }

    .di-val.mono {
        font-family: var(--ff-mono);
        font-size: .8rem;
        color: var(--c-indigo);
    }

    .di-val.dim {
        color: var(--c-txt3);
        font-style: italic;
        font-weight: 400;
    }

    .di-val.green {
        color: var(--c-green);
    }

    .di-val.red {
        color: var(--c-red);
    }

    .span-full {
        grid-column: 1 / -1;
    }

    /* ── Empty State ── */
    .empty-box {
        background: var(--c-surface);
        border: 1px solid var(--c-border);
        border-radius: var(--r-xl);
        padding: 3rem 1.5rem;
        text-align: center;
        box-shadow: var(--shadow-xs);
    }

    .empty-icon {
        font-size: 2.5rem;
        color: var(--c-border);
        margin-bottom: 1rem;
    }

    .empty-box h5 {
        font-size: 1rem;
        font-weight: 700;
        color: var(--c-txt1);
        margin-bottom: 6px;
    }

    .empty-box p {
        font-size: .84rem;
        color: var(--c-txt3);
    }

    /* ── Pagination ── */
    .pag-wrap {
        background: var(--c-surface);
        border: 1px solid var(--c-border);
        border-radius: var(--r-lg);
        box-shadow: var(--shadow-xs);
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 16px;
        gap: 12px;
        flex-wrap: wrap;
        margin-top: 10px;
    }

    .pag-info {
        font-size: .78rem;
        color: var(--c-txt3);
        font-weight: 600;
    }

    .pag-btns {
        display: flex;
        gap: 4px;
        flex-wrap: wrap;
    }

    .pag-btn {
        min-width: 34px;
        height: 34px;
        padding: 0 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1.5px solid var(--c-border);
        border-radius: var(--r-xs);
        background: var(--c-surface);
        color: var(--c-txt2);
        font-size: .78rem;
        font-weight: 700;
        text-decoration: none;
        transition: all var(--t-fast) var(--ease);
        font-family: var(--ff-body);
        cursor: pointer;
    }

    .pag-btn:hover {
        border-color: var(--c-indigo);
        color: var(--c-indigo);
    }

    .pag-btn.cur {
        background: var(--c-indigo);
        border-color: transparent;
        color: #fff;
        box-shadow: 0 2px 8px rgba(79, 70, 229, .3);
    }

    .pag-btn.off {
        opacity: .35;
        pointer-events: none;
    }

    .pag-ellipsis {
        display: inline-flex;
        align-items: center;
        color: var(--c-txt3);
        padding: 0 4px;
        font-size: .78rem;
    }

    /* ── Modal ── */
    .modal-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, .55);
        backdrop-filter: blur(6px);
        -webkit-backdrop-filter: blur(6px);
        z-index: 1000;
        display: flex;
        align-items: flex-end;
        justify-content: center;
        padding: 0;
        opacity: 0;
        pointer-events: none;
        transition: opacity var(--t-base) ease;
    }

    @media (min-width: 600px) {
        .modal-backdrop {
            align-items: center;
            padding: 20px;
        }
    }

    .modal-backdrop.open {
        opacity: 1;
        pointer-events: all;
    }

    .modal-sheet {
        background: var(--c-surface);
        width: 100%;
        max-width: 480px;
        border-radius: var(--r-xl) var(--r-xl) 0 0;
        box-shadow: var(--shadow-lg);
        transform: translateY(40px) scale(.98);
        transition: transform var(--t-base) var(--ease);
        overflow: hidden;
        max-height: 92dvh;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
    }

    @media (min-width: 600px) {
        .modal-sheet {
            border-radius: var(--r-xl);
            transform: scale(.95) translateY(10px);
        }
    }

    .modal-backdrop.open .modal-sheet {
        transform: translateY(0) scale(1);
    }

    /* drag handle */
    .modal-handle {
        width: 36px;
        height: 4px;
        background: var(--c-border);
        border-radius: 2px;
        margin: 10px auto 0;
        flex-shrink: 0;
    }

    @media (min-width: 600px) {
        .modal-handle {
            display: none;
        }
    }

    .modal-hd {
        padding: 16px 20px 14px;
        border-bottom: 1px solid var(--c-border2);
        display: flex;
        align-items: center;
        gap: 12px;
        position: sticky;
        top: 0;
        background: var(--c-surface);
        z-index: 1;
    }

    .modal-hd-ico {
        width: 42px;
        height: 42px;
        border-radius: var(--r-sm);
        flex-shrink: 0;
        background: var(--c-green-l);
        color: var(--c-green);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: .95rem;
    }

    .modal-hd h5 {
        font-size: .95rem;
        font-weight: 800;
        color: var(--c-txt1);
    }

    .modal-hd p {
        font-size: .75rem;
        color: var(--c-txt3);
        margin-top: 1px;
    }

    .modal-bd {
        padding: 18px 20px;
        flex: 1;
    }

    .modal-ft {
        padding: 14px 20px;
        border-top: 1px solid var(--c-border2);
        display: flex;
        justify-content: flex-end;
        gap: 8px;
        position: sticky;
        bottom: 0;
        background: var(--c-surface);
    }

    /* outcome buttons */
    .outcome-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
        margin-bottom: 16px;
    }

    .outcome-btn {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 16px 10px;
        border-radius: var(--r-md);
        border: 2px solid var(--c-border);
        background: var(--c-surface);
        cursor: pointer;
        transition: all var(--t-fast) var(--ease);
        font-family: var(--ff-body);
    }

    .outcome-btn i {
        font-size: 1.5rem;
    }

    .outcome-btn span {
        font-size: .88rem;
        font-weight: 800;
    }

    .outcome-btn small {
        font-size: .71rem;
        color: var(--c-txt3);
    }

    .outcome-btn.o-received:hover,
    .outcome-btn.o-received.sel {
        border-color: var(--c-green);
        background: var(--c-green-l);
        color: var(--c-green);
    }

    .outcome-btn.o-rejected:hover,
    .outcome-btn.o-rejected.sel {
        border-color: var(--c-red);
        background: var(--c-red-l);
        color: var(--c-red);
    }

    .f-group {
        margin-top: 14px;
    }

    .f-label {
        font-size: .79rem;
        font-weight: 700;
        color: var(--c-txt2);
        display: block;
        margin-bottom: 6px;
    }

    .f-req {
        color: var(--c-red);
    }

    .f-input {
        width: 100%;
        padding: 9px 12px;
        border: 1.5px solid var(--c-border);
        border-radius: var(--r-sm);
        font-family: var(--ff-body);
        font-size: .875rem;
        color: var(--c-txt1);
        outline: none;
        resize: vertical;
        transition: border-color var(--t-fast) ease;
        background: var(--c-bg);
    }

    .f-input:focus {
        border-color: var(--c-indigo);
        background: #fff;
        box-shadow: 0 0 0 3px rgba(79, 70, 229, .09);
    }

    .rejection-block {
        display: none;
    }

    .rejection-block.show {
        display: block;
        animation: fadeSlide .2s var(--ease);
    }

    @keyframes fadeSlide {
        from {
            opacity: 0;
            transform: translateY(-4px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* ── Scrollbar for modal on desktop ── */
    .modal-sheet::-webkit-scrollbar {
        width: 5px;
    }

    .modal-sheet::-webkit-scrollbar-track {
        background: transparent;
    }

    .modal-sheet::-webkit-scrollbar-thumb {
        background: var(--c-border);
        border-radius: 3px;
    }
</style>

<!-- Page Header -->
<div class="ph">
    <div>
        <h1 class="ph-title">Dispatches</h1>
        <p class="ph-sub">Track container dispatches and delivery status</p>
    </div>
    <a href="manage-bl.php" class="btn btn-primary">
        <i class="fas fa-list-check"></i>
        <span class="d-none d-sm-inline">Manage B/L</span>
        <span class="d-sm-none">B/L</span>
    </a>
</div>

<?php if (isset($_GET['updated'])): ?>
    <div class="alert-success" id="flashMsg">
        <i class="fas fa-circle-check"></i> Dispatch status updated successfully.
    </div>
    <script>
        setTimeout(() => {
            const e = document.getElementById('flashMsg');
            if (e) {
                e.style.transition = 'opacity .4s';
                e.style.opacity = '0';
                setTimeout(() => e.remove(), 400);
            }
        }, 4000);
    </script>
<?php endif; ?>

<!-- Stat Grid -->
<div class="stat-grid">
    <?php $stats = [
        ['indigo', 'fa-truck-loading', 'Total Dispatches', $counts['total']],
        ['amber', 'fa-truck',       'In Transit',      $counts['transit']],
        ['green', 'fa-circle-check', 'Received',        $counts['received']],
        ['red',   'fa-circle-xmark', 'Rejected',        $counts['rejected']],
    ];
    foreach ($stats as [$color, $icon, $label, $val]): ?>
        <div class="stat-card">
            <div class="stat-pill stat-pill-<?php echo $color; ?>">
                <i class="fas <?php echo $icon; ?>"></i>
                <?php echo $label; ?>
            </div>
            <div>
                <div class="stat-num"><?php echo number_format($val); ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Tabs -->
<div class="tabs-scroll">
    <div class="tabs-inner">
        <?php
        $tabs = [
            ['',         'All',        'fas fa-layer-group', $counts['total']],
            ['transit',  'In Transit', 'fas fa-truck',       $counts['transit']],
            ['received', 'Received',   'fas fa-circle-check', $counts['received']],
            ['rejected', 'Rejected',   'fas fa-circle-xmark', $counts['rejected']],
        ];
        foreach ($tabs as [$val, $label, $icon, $cnt]):
            $qs = http_build_query(['status' => $val, 'search' => $search, 'page' => 1]);
            $active = ($status_filter === ($val ?: null) || ($val === '' && $status_filter === null)) ? 'active' : '';
        ?>
            <a href="?<?php echo $qs; ?>" class="tab-link <?php echo $active; ?>">
                <i class="<?php echo $icon; ?>"></i>
                <?php echo $label; ?>
                <span class="tab-badge"><?php echo number_format($cnt); ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- Filter -->
<form method="GET" id="filterForm">
    <input type="hidden" name="status" value="<?php echo htmlspecialchars((string)$status_filter); ?>">
    <div class="filter-bar">
        <div class="search-box">
            <i class="fas fa-magnifying-glass search-icon"></i>
            <input type="text" name="search" class="search-input"
                placeholder="Search B/L, container, truck, destination…"
                value="<?php echo htmlspecialchars($search); ?>"
                oninput="clearTimeout(window._dbt);window._dbt=setTimeout(()=>this.form.submit(),480)">
        </div>
        <?php if ($search): ?>
            <a href="?status=<?php echo urlencode((string)$status_filter); ?>" class="btn btn-ghost btn-sm">
                <i class="fas fa-xmark"></i> Clear
            </a>
        <?php endif; ?>
        <span class="rec-count"><?php echo number_format($totalRows); ?> result<?php echo $totalRows != 1 ? 's' : ''; ?></span>
    </div>
</form>

<!-- Dispatch List -->
<?php if (empty($dispatches)): ?>
    <div class="empty-box">
        <div class="empty-icon"><i class="fas fa-truck-ramp-box"></i></div>
        <h5>No dispatches found</h5>
        <p><?php echo ($search || $status_filter) ? 'Try clearing your filters.' : 'No containers dispatched yet.'; ?></p>
        <a href="manage-bl.php" class="btn btn-primary" style="margin-top:16px;">
            <i class="fas fa-list-check"></i> View Bills of Lading
        </a>
    </div>

<?php else: ?>

    <?php foreach ($dispatches as $d): ?>
        <div class="dispatch-card">

            <!-- Head -->
            <div class="dc-head">
                <div class="dc-head-left">
                    <div class="dc-avatar"><i class="fas fa-ship"></i></div>
                    <div class="dc-meta">
                        <div class="dc-container"><?php echo htmlspecialchars($d['container_number']); ?></div>
                        <div class="dc-bl">B/L &nbsp;<a href="view-bl.php?id=<?php echo $d['bl_id']; ?>"><?php echo htmlspecialchars($d['bl_number']); ?></a></div>
                        <div class="dc-chips">
                            <span class="chip chip-truck"><i class="fas fa-truck"></i><?php echo htmlspecialchars($d['truck_number']); ?></span>
                            <span class="chip"><i class="fas fa-location-dot" style="color:var(--c-red);"></i><?php echo htmlspecialchars($d['destination']); ?></span>
                        </div>
                    </div>
                </div>

                <div class="dc-actions">
                    <?php if ($d['status'] === 'transit'): ?>
                        <span class="badge badge-transit"><span class="pulse"></span>Transit</span>
                        <button type="button" class="btn btn-ghost btn-sm"
                            onclick="openModal(<?php echo $d['id']; ?>,'<?php echo htmlspecialchars($d['container_number']); ?>',<?php echo $d['bl_item_id']; ?>,<?php echo $d['bl_id']; ?>)">
                            <i class="fas fa-flag-checkered"></i><span class="d-none d-sm-inline">Update</span>
                        </button>
                    <?php elseif ($d['status'] === 'received'): ?>
                        <span class="badge badge-received"><i class="fas fa-circle-check"></i>Received</span>
                    <?php else: ?>
                        <span class="badge badge-rejected"><i class="fas fa-circle-xmark"></i>Rejected</span>
                    <?php endif; ?>
                    <a href="view-bl.php?id=<?php echo $d['bl_id']; ?>" class="btn btn-ghost btn-sm btn-icon" title="View B/L">
                        <i class="fas fa-eye"></i>
                    </a>
                </div>
            </div>

            <!-- Body -->
            <div class="dc-body">
                <div>
                    <div class="di-label"><i class="fas fa-user-tie"></i>Transporter</div>
                    <div class="di-val"><?php echo htmlspecialchars($d['transporter_name']); ?></div>
                </div>
                <div>
                    <div class="di-label"><i class="fas fa-boxes-stacked"></i>Bags</div>
                    <div class="di-val"><?php echo number_format($d['number_of_bags']); ?></div>
                </div>
                <div>
                    <div class="di-label"><i class="fas fa-weight-scale"></i>Gross Wt</div>
                    <div class="di-val"><?php echo number_format($d['gross_weight'], 3); ?> MT</div>
                </div>
                <div>
                    <div class="di-label"><i class="fas fa-file-lines"></i>CA D/Note</div>
                    <div class="di-val <?php echo $d['clearing_agent_dnote'] ? 'mono' : 'dim'; ?>">
                        <?php echo $d['clearing_agent_dnote'] ? htmlspecialchars($d['clearing_agent_dnote']) : '—'; ?>
                    </div>
                </div>
                <div>
                    <div class="di-label"><i class="fas fa-file-alt"></i>TP D/Note</div>
                    <div class="di-val <?php echo $d['transporter_dnote'] ? 'mono' : 'dim'; ?>">
                        <?php echo $d['transporter_dnote'] ? htmlspecialchars($d['transporter_dnote']) : '—'; ?>
                    </div>
                </div>
                <div>
                    <div class="di-label"><i class="fas fa-clock"></i>Dispatched</div>
                    <div class="di-val" style="font-size:.78rem;"><?php echo date('M j, Y H:i', strtotime($d['dispatched_at'])); ?></div>
                </div>
                <?php if ($d['status'] !== 'transit' && $d['received_at']): ?>
                    <div>
                        <div class="di-label">
                            <i class="fas fa-<?php echo $d['status'] === 'received' ? 'circle-check' : 'circle-xmark'; ?>"></i>
                            <?php echo ucfirst($d['status']); ?> At
                        </div>
                        <div class="di-val <?php echo $d['status'] === 'received' ? 'green' : 'red'; ?>" style="font-size:.78rem;">
                            <?php echo date('M j, Y H:i', strtotime($d['received_at'])); ?>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ($d['status'] === 'rejected' && $d['rejection_reason']): ?>
                    <div class="span-full">
                        <div class="di-label"><i class="fas fa-comment-exclamation"></i>Rejection Reason</div>
                        <div class="di-val red" style="font-size:.82rem;"><?php echo htmlspecialchars($d['rejection_reason']); ?></div>
                    </div>
                <?php endif; ?>
                <?php if ($d['notes']): ?>
                    <div class="span-full">
                        <div class="di-label"><i class="fas fa-note-sticky"></i>Notes</div>
                        <div class="di-val" style="font-size:.82rem;color:var(--c-txt3);"><?php echo htmlspecialchars($d['notes']); ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="pag-wrap">
            <div class="pag-info">
                Showing <strong><?php echo $offset + 1; ?>–<?php echo min($offset + $perPage, $totalRows); ?></strong>
                of <strong><?php echo number_format($totalRows); ?></strong>
            </div>
            <div class="pag-btns">
                <?php $qs = http_build_query(['status' => $status_filter, 'search' => $search, 'page' => $page - 1]); ?>
                <a href="?<?php echo $qs; ?>" class="pag-btn<?php echo $page <= 1 ? ' off' : ''; ?>"><i class="fas fa-chevron-left"></i></a>
                <?php
                for ($p = 1; $p <= $totalPages; $p++) {
                    if ($p !== 1 && $p !== $totalPages && abs($p - $page) > 2) {
                        if ($p === 2 || $p === $totalPages - 1) echo '<span class="pag-ellipsis">…</span>';
                        continue;
                    }
                    $qs = http_build_query(['status' => $status_filter, 'search' => $search, 'page' => $p]);
                    $cls = $p == $page ? ' cur' : '';
                    echo "<a href='?$qs' class='pag-btn$cls'>$p</a>";
                }
                ?>
                <?php $qs = http_build_query(['status' => $status_filter, 'search' => $search, 'page' => $page + 1]); ?>
                <a href="?<?php echo $qs; ?>" class="pag-btn<?php echo $page >= $totalPages ? ' off' : ''; ?>"><i class="fas fa-chevron-right"></i></a>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<!-- Modal -->
<div class="modal-backdrop" id="statusModal">
    <div class="modal-sheet">
        <div class="modal-handle"></div>
        <div class="modal-hd">
            <div class="modal-hd-ico"><i class="fas fa-flag-checkered"></i></div>
            <div>
                <h5>Update Delivery Status</h5>
                <p id="modalSubtitle">Select the outcome</p>
            </div>
        </div>
        <div class="modal-bd">
            <form id="statusForm" method="POST" action="dispatch-action.php">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="dispatch_id" id="fDispatchId">
                <input type="hidden" name="bl_item_id" id="fItemId">
                <input type="hidden" name="redirect_id" id="fRedirectId">
                <input type="hidden" name="new_status" id="fStatus" value="">

                <div class="outcome-grid">
                    <button type="button" class="outcome-btn o-received" onclick="pickOutcome('received')">
                        <i class="fas fa-circle-check" style="color:var(--c-green);"></i>
                        <span>Received</span>
                        <small>Delivered successfully</small>
                    </button>
                    <button type="button" class="outcome-btn o-rejected" onclick="pickOutcome('rejected')">
                        <i class="fas fa-circle-xmark" style="color:var(--c-red);"></i>
                        <span>Rejected</span>
                        <small>Not accepted</small>
                    </button>
                </div>

                <div class="rejection-block f-group" id="rejectionBlock">
                    <label class="f-label">Rejection reason <span class="f-req">*</span></label>
                    <textarea id="rejectionReason" name="rejection_reason" class="f-input" rows="3"
                        placeholder="Explain why the container was rejected…"></textarea>
                </div>

                <div class="f-group">
                    <label class="f-label">Notes <span style="color:var(--c-txt3);font-weight:500;">(optional)</span></label>
                    <textarea name="notes" class="f-input" rows="2" placeholder="Any additional delivery notes…"></textarea>
                </div>
            </form>
        </div>
        <div class="modal-ft">
            <button type="button" class="btn btn-ghost" onclick="closeModal()"><i class="fas fa-xmark"></i> Cancel</button>
            <button type="button" class="btn btn-primary" id="confirmBtn" onclick="confirmUpdate()" disabled>
                <i class="fas fa-check"></i> Confirm
            </button>
        </div>
    </div>
</div>

<script>
    let _outcome = '';

    function openModal(id, container, itemId, blId) {
        _outcome = '';
        document.getElementById('fDispatchId').value = id;
        document.getElementById('fItemId').value = itemId;
        document.getElementById('fRedirectId').value = blId;
        document.getElementById('fStatus').value = '';
        document.getElementById('modalSubtitle').textContent = container;
        document.getElementById('rejectionReason').value = '';
        document.getElementById('rejectionBlock').className = 'rejection-block f-group';
        document.getElementById('confirmBtn').disabled = true;
        document.querySelectorAll('.outcome-btn').forEach(b => b.classList.remove('sel'));
        document.getElementById('statusModal').classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        document.getElementById('statusModal').classList.remove('open');
        document.body.style.overflow = '';
    }

    function pickOutcome(val) {
        _outcome = val;
        document.getElementById('fStatus').value = val;
        document.getElementById('confirmBtn').disabled = false;
        document.querySelectorAll('.outcome-btn').forEach(b => b.classList.remove('sel'));
        document.querySelector('.o-' + val).classList.add('sel');
        const rb = document.getElementById('rejectionBlock');
        rb.className = 'rejection-block f-group' + (val === 'rejected' ? ' show' : '');
    }

    function confirmUpdate() {
        if (!_outcome) return;
        if (_outcome === 'rejected') {
            const r = document.getElementById('rejectionReason').value.trim();
            if (!r) {
                const el = document.getElementById('rejectionReason');
                el.style.borderColor = 'var(--c-red)';
                el.focus();
                el.addEventListener('input', () => el.style.borderColor = '', {
                    once: true
                });
                return;
            }
        }
        const btn = document.getElementById('confirmBtn');
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';
        btn.disabled = true;
        document.getElementById('statusForm').submit();
    }

    // Close on backdrop click
    document.getElementById('statusModal').addEventListener('click', e => {
        if (e.target === e.currentTarget) closeModal();
    });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeModal();
    });

    // Drag-to-close on mobile (sheet)
    const sheet = document.querySelector('.modal-sheet');
    let startY = 0,
        dragging = false;
    sheet.addEventListener('touchstart', e => {
        startY = e.touches[0].clientY;
        dragging = true;
    }, {
        passive: true
    });
    sheet.addEventListener('touchmove', e => {
        if (!dragging) return;
        const dy = e.touches[0].clientY - startY;
        if (dy > 0) sheet.style.transform = `translateY(${dy}px)`;
    }, {
        passive: true
    });
    sheet.addEventListener('touchend', e => {
        const dy = e.changedTouches[0].clientY - startY;
        sheet.style.transition = 'transform var(--t-base) var(--ease)';
        if (dy > 100) {
            closeModal();
        } else {
            sheet.style.transform = '';
        }
        dragging = false;
        setTimeout(() => sheet.style.transition = '', 300);
    });
</script>

<?php require_once 'includes/footer.php'; ?>s