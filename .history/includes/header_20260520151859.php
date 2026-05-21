<?php
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
$current = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title ?? 'BL Manager'); ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        /* ============================================
       BL MANAGER — ULTRA MODERN 2026 UI
    ============================================ */
        :root {
            --sb-width: 260px;
            --nav-height: 68px;
            --indigo: #6366f1;
            --indigo-l: #818cf8;
            --violet: #8b5cf6;
            --emerald: #10b981;
            --amber: #f59e0b;
            --rose: #f43f5e;
            --cyan: #06b6d4;
            --bg: #f1f5f9;
            --card: #ffffff;
            --border: #e2e8f0;
            --border-l: #f1f5f9;
            --txt-1: #0f172a;
            --txt-2: #64748b;
            --txt-3: #94a3b8;
            --sb-bg: #0d1117;
            --sb-border: rgba(255, 255, 255, 0.07);
            --sb-txt: rgba(255, 255, 255, 0.5);
            --sb-txt-h: rgba(255, 255, 255, 0.92);
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.07);
            --shadow-md: 0 4px 16px rgba(0, 0, 0, 0.09);
            --shadow-lg: 0 10px 40px rgba(0, 0, 0, 0.11);
            --t: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            --r-sm: 10px;
            --r-md: 14px;
            --r-lg: 20px;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: var(--bg);
            color: var(--txt-1);
            -webkit-font-smoothing: antialiased;
            line-height: 1.6;
        }

        ::-webkit-scrollbar {
            width: 5px;
            height: 5px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 99px;
        }

        /* ── LAYOUT ── */
        .app-wrap {
            display: flex;
            min-height: 100vh;
        }

        /* ── SIDEBAR ── */
        .sidebar {
            width: var(--sb-width);
            background: var(--sb-bg);
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            overflow-x: hidden;
            border-right: 1px solid var(--sb-border);
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Logo Area */
        .sb-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 1.6rem 1.4rem 1.2rem;
            border-bottom: 1px solid var(--sb-border);
            text-decoration: none;
        }

        .sb-logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--indigo), var(--violet));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.05rem;
            flex-shrink: 0;
            box-shadow: 0 4px 14px rgba(99, 102, 241, 0.4);
        }

        .sb-logo-title {
            display: block;
            color: white;
            font-weight: 700;
            font-size: 0.97rem;
            letter-spacing: -0.3px;
            line-height: 1.2;
        }

        .sb-logo-sub {
            display: block;
            color: var(--sb-txt);
            font-size: 0.7rem;
            font-weight: 500;
            margin-top: 2px;
        }

        /* User */
        .sb-user {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 1.2rem 1.4rem;
            border-bottom: 1px solid var(--sb-border);
        }

        .sb-avatar {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, var(--indigo), var(--violet));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 0.88rem;
            flex-shrink: 0;
        }

        .sb-user-name {
            display: block;
            color: rgba(255, 255, 255, 0.88);
            font-weight: 600;
            font-size: 0.85rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .sb-user-role {
            display: flex;
            align-items: center;
            gap: 5px;
            color: var(--emerald);
            font-size: 0.7rem;
            font-weight: 500;
            margin-top: 1px;
        }

        .sb-user-role .dot {
            width: 6px;
            height: 6px;
            background: var(--emerald);
            border-radius: 50%;
            animation: blink 2s infinite;
        }

        @keyframes blink {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.3;
            }
        }

        /* Nav */
        .sb-nav {
            flex: 1;
            padding: 1.2rem 0.85rem;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .sb-section-lbl {
            font-size: 0.66rem;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--sb-txt);
            padding: 0 0.6rem;
            margin: 10px 0 6px;
        }

        .sb-link {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 10px 12px;
            border-radius: var(--r-sm);
            color: var(--sb-txt);
            text-decoration: none;
            font-size: 0.86rem;
            font-weight: 500;
            transition: var(--t);
            position: relative;
            cursor: pointer;
        }

        .sb-link:hover {
            background: rgba(255, 255, 255, 0.07);
            color: var(--sb-txt-h);
        }

        .sb-link.active {
            background: rgba(99, 102, 241, 0.18);
            color: #a5b4fc;
        }

        .sb-link.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 18%;
            bottom: 18%;
            width: 3px;
            background: var(--indigo-l);
            border-radius: 0 4px 4px 0;
        }

        .sb-link-ico {
            width: 30px;
            height: 30px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.06);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.82rem;
            flex-shrink: 0;
            transition: var(--t);
        }

        .sb-link.active .sb-link-ico {
            background: rgba(99, 102, 241, 0.3);
            color: #a5b4fc;
        }

        .sb-link-arrow {
            margin-left: auto;
            font-size: 0.6rem;
            opacity: 0;
            transition: var(--t);
        }

        .sb-link:hover .sb-link-arrow,
        .sb-link.active .sb-link-arrow {
            opacity: 1;
        }

        .sb-link.logout {
            color: rgba(244, 63, 94, 0.65);
        }

        .sb-link.logout:hover {
            background: rgba(244, 63, 94, 0.1);
            color: #fb7185;
        }

        /* Sidebar Footer */
        .sb-footer {
            padding: 0.9rem 1.4rem;
            border-top: 1px solid var(--sb-border);
            color: var(--sb-txt);
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            gap: 7px;
        }

        /* Backdrop */
        .sb-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.55);
            backdrop-filter: blur(4px);
            z-index: 999;
        }

        .sb-backdrop.show {
            display: block;
        }

        /* ── MAIN ── */
        .main-wrap {
            flex: 1;
            margin-left: var(--sb-width);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            transition: var(--t);
        }

        /* ── TOP NAV ── */
        .top-nav {
            height: var(--nav-height);
            background: white;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.75rem;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: var(--shadow-sm);
        }

        .nav-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .menu-btn {
            width: 36px;
            height: 36px;
            border: 1.5px solid var(--border);
            border-radius: var(--r-sm);
            background: white;
            color: var(--txt-2);
            display: none;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--t);
            font-size: 0.9rem;
        }

        .menu-btn:hover {
            background: var(--bg);
            color: var(--txt-1);
        }

        .breadcrumb-wrap {
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: 0.83rem;
        }

        .bc-home {
            color: var(--txt-3);
        }

        .bc-sep {
            color: var(--txt-3);
            font-size: 0.6rem;
        }

        .bc-curr {
            color: var(--txt-1);
            font-weight: 600;
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .nav-icon-btn {
            width: 36px;
            height: 36px;
            border: 1.5px solid var(--border);
            border-radius: var(--r-sm);
            background: white;
            color: var(--txt-2);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--t);
            position: relative;
            font-size: 0.88rem;
        }

        .nav-icon-btn:hover {
            background: var(--bg);
            border-color: var(--indigo);
            color: var(--indigo);
        }

        .notif-dot {
            position: absolute;
            top: 6px;
            right: 6px;
            width: 7px;
            height: 7px;
            background: var(--rose);
            border-radius: 50%;
            border: 1.5px solid white;
        }

        .nav-clock {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--txt-2);
            background: var(--bg);
            padding: 6px 11px;
            border-radius: var(--r-sm);
            border: 1.5px solid var(--border);
            font-variant-numeric: tabular-nums;
        }

        .nav-avatar {
            width: 34px;
            height: 34px;
            background: linear-gradient(135deg, var(--indigo), var(--violet));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 0.82rem;
            cursor: pointer;
        }

        /* ── PAGE ── */
        .page-content {
            flex: 1;
            padding: 2rem;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 1.7rem;
            font-weight: 800;
            color: var(--txt-1);
            letter-spacing: -0.5px;
            margin-bottom: 3px;
        }

        .page-sub {
            color: var(--txt-2);
            font-size: 0.88rem;
        }

        /* ── STAT CARDS ── */
        .stat-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: var(--r-lg);
            padding: 1.5rem;
            transition: var(--t);
            position: relative;
            overflow: hidden;
        }

        .stat-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            border-radius: var(--r-lg) var(--r-lg) 0 0;
        }

        .stat-card.c-indigo::after {
            background: linear-gradient(90deg, var(--indigo), var(--violet));
        }

        .stat-card.c-emerald::after {
            background: linear-gradient(90deg, var(--emerald), var(--cyan));
        }

        .stat-card.c-amber::after {
            background: linear-gradient(90deg, var(--amber), #f97316);
        }

        .stat-card.c-rose::after {
            background: linear-gradient(90deg, var(--rose), #e879f9);
        }

        .stat-card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-3px);
            border-color: transparent;
        }

        .stat-ico {
            width: 50px;
            height: 50px;
            border-radius: var(--r-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin-bottom: 1.1rem;
        }

        .stat-ico.c-indigo {
            background: rgba(99, 102, 241, 0.1);
            color: var(--indigo);
        }

        .stat-ico.c-emerald {
            background: rgba(16, 185, 129, 0.1);
            color: var(--emerald);
        }

        .stat-ico.c-amber {
            background: rgba(245, 158, 11, 0.1);
            color: var(--amber);
        }

        .stat-ico.c-rose {
            background: rgba(244, 63, 94, 0.1);
            color: var(--rose);
        }

        .stat-val {
            font-size: 2.1rem;
            font-weight: 800;
            color: var(--txt-1);
            letter-spacing: -1px;
            line-height: 1;
            margin-bottom: 5px;
        }

        .stat-lbl {
            font-size: 0.8rem;
            font-weight: 500;
            color: var(--txt-2);
            margin-bottom: 0.9rem;
        }

        .stat-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 20px;
            background: rgba(16, 185, 129, 0.1);
            color: var(--emerald);
        }

        /* ── DATA CARD ── */
        .data-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: var(--r-lg);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .data-card-head {
            padding: 1.2rem 1.5rem;
            border-bottom: 1px solid var(--border-l);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

        .data-card-title {
            font-size: 0.97rem;
            font-weight: 700;
            color: var(--txt-1);
            display: flex;
            align-items: center;
            gap: 9px;
        }

        .data-card-title i {
            color: var(--indigo);
        }

        /* ── TABLE ── */
        .tbl {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.855rem;
        }

        .tbl thead th {
            background: #f8fafc;
            color: var(--txt-2);
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.07em;
            text-transform: uppercase;
            padding: 11px 15px;
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }

        .tbl tbody tr {
            border-bottom: 1px solid var(--border-l);
            transition: var(--t);
        }

        .tbl tbody tr:last-child {
            border-bottom: none;
        }

        .tbl tbody tr:hover {
            background: #f8faff;
        }

        .tbl tbody td {
            padding: 13px 15px;
            color: var(--txt-1);
            vertical-align: middle;
        }

        /* ── BADGES ── */
        .bdg {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.73rem;
            font-weight: 600;
        }

        .bdg-active {
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
        }

        .bdg-draft {
            background: rgba(245, 158, 11, 0.1);
            color: #d97706;
        }

        .bdg-completed {
            background: rgba(99, 102, 241, 0.1);
            color: var(--indigo);
        }

        /* ── BUTTONS ── */
        .btn-m {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 9px 18px;
            border-radius: var(--r-sm);
            font-size: 0.855rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: var(--t);
            text-decoration: none;
            font-family: 'Inter', sans-serif;
            white-space: nowrap;
            line-height: 1;
        }

        .btn-indigo {
            background: linear-gradient(135deg, var(--indigo), var(--violet));
            color: white !important;
            box-shadow: 0 3px 12px rgba(99, 102, 241, 0.3);
        }

        .btn-indigo:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 22px rgba(99, 102, 241, 0.4);
            color: white;
        }

        .btn-ghost {
            background: white;
            color: var(--txt-2) !important;
            border: 1.5px solid var(--border);
        }

        .btn-ghost:hover {
            background: var(--bg);
            color: var(--txt-1) !important;
            border-color: var(--indigo);
        }

        .btn-danger {
            background: rgba(244, 63, 94, 0.08);
            color: var(--rose) !important;
            border: 1.5px solid rgba(244, 63, 94, 0.2);
        }

        .btn-danger:hover {
            background: var(--rose);
            color: white !important;
        }

        .btn-sm {
            padding: 6px 13px;
            font-size: 0.78rem;
        }

        /* ── FORM CARD ── */
        .form-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: var(--r-lg);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            margin-bottom: 1.5rem;
        }

        .form-card-head {
            padding: 1.35rem 1.75rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 14px;
            background: linear-gradient(to right, #fafbff, white);
            flex-wrap: wrap;
        }

        .form-card-ico {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--indigo), var(--violet));
            border-radius: var(--r-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.95rem;
            flex-shrink: 0;
            box-shadow: 0 3px 10px rgba(99, 102, 241, 0.3);
        }

        .form-card-head h5 {
            font-size: 1rem;
            font-weight: 700;
            color: var(--txt-1);
            margin: 0;
            line-height: 1.3;
        }

        .form-card-head p {
            color: var(--txt-2);
            font-size: 0.8rem;
            margin: 0;
        }

        .form-card-body {
            padding: 1.75rem;
        }

        /* ── FORM FIELDS ── */
        .f-label {
            display: block;
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--txt-2);
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-bottom: 6px;
        }

        .f-label .req {
            color: var(--rose);
            margin-left: 2px;
        }

        .f-input {
            width: 100%;
            background: var(--bg);
            border: 1.5px solid var(--border);
            border-radius: var(--r-sm);
            padding: 10px 13px;
            font-family: 'Inter', sans-serif;
            font-size: 0.875rem;
            font-weight: 400;
            color: var(--txt-1);
            transition: var(--t);
            outline: none;
            display: block;
        }

        .f-input:focus {
            border-color: var(--indigo);
            background: white;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .f-input::placeholder {
            color: var(--txt-3);
        }

        textarea.f-input {
            resize: vertical;
            min-height: 95px;
            line-height: 1.6;
        }

        /* ── ITEM ROWS ── */
        .rows-wrap {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .item-row {
            background: var(--bg);
            border: 1.5px solid var(--border);
            border-radius: var(--r-md);
            padding: 1.35rem;
            transition: var(--t);
            animation: rowIn 0.3s cubic-bezier(0.22, 1, 0.36, 1);
        }

        @keyframes rowIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .item-row:hover {
            border-color: var(--indigo);
            box-shadow: 0 3px 16px rgba(99, 102, 241, 0.08);
            background: white;
        }

        .row-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.1rem;
        }

        .row-num {
            display: flex;
            align-items: center;
            gap: 9px;
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--indigo);
        }

        .row-num-badge {
            width: 26px;
            height: 26px;
            background: linear-gradient(135deg, var(--indigo), var(--violet));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.72rem;
            font-weight: 700;
        }

        .btn-remove {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 11px;
            background: rgba(244, 63, 94, 0.07);
            color: var(--rose);
            border: 1.5px solid rgba(244, 63, 94, 0.18);
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--t);
            font-family: 'Inter', sans-serif;
        }

        .btn-remove:hover {
            background: var(--rose);
            color: white;
            border-color: var(--rose);
        }

        .btn-add-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            width: 100%;
            padding: 13px;
            background: white;
            border: 2px dashed var(--border);
            border-radius: var(--r-md);
            color: var(--txt-2);
            font-size: 0.855rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--t);
            font-family: 'Inter', sans-serif;
            margin-top: 6px;
        }

        .btn-add-row:hover {
            border-color: var(--indigo);
            color: var(--indigo);
            background: rgba(99, 102, 241, 0.03);
        }

        /* ── TOTALS BAR ── */
        .totals-bar {
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
            margin-left: auto;
        }

        .total-item {
            text-align: center;
        }

        .total-item-lbl {
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--txt-3);
            display: block;
        }

        .total-item-val {
            font-size: 1.15rem;
            font-weight: 800;
            display: block;
            line-height: 1.2;
        }

        .total-item-val.c-indigo {
            color: var(--indigo);
        }

        .total-item-val.c-emerald {
            color: var(--emerald);
        }

        .total-item-val.c-amber {
            color: var(--amber);
        }

        .total-item-val.c-rose {
            color: var(--rose);
        }

        /* ── ALERTS ── */
        .alert-m {
            display: flex;
            align-items: flex-start;
            gap: 11px;
            padding: 13px 16px;
            border-radius: var(--r-md);
            margin-bottom: 1.4rem;
            font-size: 0.86rem;
            font-weight: 500;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-6px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.08);
            border: 1px solid rgba(16, 185, 129, 0.25);
            color: #065f46;
        }

        .alert-error {
            background: rgba(244, 63, 94, 0.08);
            border: 1px solid rgba(244, 63, 94, 0.25);
            color: #9f1239;
        }

        .alert-ico {
            font-size: 0.95rem;
            margin-top: 1px;
            flex-shrink: 0;
        }

        /* ── EMPTY STATE ── */
        .empty {
            text-align: center;
            padding: 3.5rem 2rem;
            color: var(--txt-3);
        }

        .empty-ico {
            font-size: 2.8rem;
            margin-bottom: 0.9rem;
            opacity: 0.35;
        }

        .empty h5 {
            color: var(--txt-2);
            font-weight: 600;
            margin-bottom: 0.4rem;
        }

        .empty p {
            font-size: 0.85rem;
        }

        /* ── RESPONSIVE ── */
        @media (max-width: 991px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.open {
                transform: translateX(0);
                box-shadow: var(--shadow-lg);
            }

            .main-wrap {
                margin-left: 0;
            }

            .menu-btn {
                display: flex;
            }

            .page-content {
                padding: 1.2rem;
            }

            .nav-clock {
                display: none;
            }
        }

        @media (max-width: 575px) {
            .page-title {
                font-size: 1.35rem;
            }

            .stat-val {
                font-size: 1.7rem;
            }

            .form-card-body {
                padding: 1.2rem;
            }
        }
    </style>
</head>

<body>
    <div class="app-wrap">

        <!-- ═══════ SIDEBAR ═══════ -->
        <aside class="sidebar" id="sidebar">

            <div class="sb-logo">
                <div class="sb-logo-icon"><i class="fas fa-ship"></i></div>
                <div>
                    <span class="sb-logo-title">RVP Operations</span>
                    <span class="sb-logo-sub">v2.0 &mdash; Shipping Suite</span>
                </div>
            </div> 204.41

            <div class="sb-user">
                <div class="sb-avatar"><?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?></div>
                <div style="overflow:hidden;">
                    <span class="sb-user-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?></span>
                    <div class="sb-user-role">
                        <span class="dot"></span> Administrator
                    </div>
                </div>
            </div>

            <nav class="sb-nav">

                <span class="sb-section-lbl">Main Menu</span>

                <a href="dashboard.php"
                    class="sb-link <?php echo $current == 'dashboard.php' ? 'active' : ''; ?>">
                    <div class="sb-link-ico"><i class="fas fa-house"></i></div>
                    <span>Dashboard</span>
                    <i class="fas fa-chevron-right sb-link-arrow"></i>
                </a>

                <a href="add-bl.php"
                    class="sb-link <?php echo $current == 'add-bl.php' ? 'active' : ''; ?>">
                    <div class="sb-link-ico"><i class="fas fa-circle-plus"></i></div>
                    <span>Add Shipment</span>
                    <i class="fas fa-chevron-right sb-link-arrow"></i>
                </a>

                <a href="manage-bl.php"
                    class="sb-link <?php echo $current == 'manage-bl.php' ? 'active' : ''; ?>">
                    <div class="sb-link-ico"><i class="fas fa-list-check"></i></div>
                    <span>Manage Shipment</span>
                    <i class="fas fa-chevron-right sb-link-arrow"></i>
                </a>
                <a href="manage-bl.php"
                    class="sb-link <?php echo $current == 'view-bl.php' ? 'active' : ''; ?>">
                    <div class="sb-link-ico"><i class="fas fa-list-check"></i></div>
                    <span>View Shipment</span>
                    <i class="fas fa-chevron-right sb-link-arrow"></i>
                </a>

                <a href="dispatches.php"
                    class="sb-link <?php echo $current == 'dispatches.php' ? 'active' : ''; ?>">
                    <div class="sb-link-ico"><i class="fas fa-truck"></i></div>
                    <span>Dispatches</span>
                    <?php
                    // Badge: count in-transit containers
                    try {
                        $transitBadge = $pdo->query("SELECT COUNT(*) FROM dispatches WHERE status='transit'")->fetchColumn();
                        if ($transitBadge > 0):
                    ?>
                            <span style="margin-left:auto;background:var(--amber);color:white;font-size:0.65rem;font-weight:700;padding:2px 7px;border-radius:20px;min-width:20px;text-align:center;">
                                <?php echo $transitBadge; ?>
                            </span>
                    <?php endif;
                    } catch (Exception $e) {
                    } ?>
                </a>

                <a href="reports.php"
                    class="sb-link <?php echo $current == 'reports.php' ? 'active' : ''; ?>">
                    <div class="sb-link-ico"><i class="fas fa-chart-bar"></i></div>
                    <span>Reports</span>
                    <i class="fas fa-chevron-right sb-link-arrow"></i>
                </a>

                <span class="sb-section-lbl" style="margin-top:1rem;">Account</span>

                <a href="profile.php"
                    class="sb-link <?php echo $current == 'profile.php' ? 'active' : ''; ?>">
                    <div class="sb-link-ico"><i class="fas fa-user-gear"></i></div>
                    <span>Profile</span>
                    <i class="fas fa-chevron-right sb-link-arrow"></i>
                </a>

                <a href="logout.php" class="sb-link logout">
                    <div class="sb-link-ico"><i class="fas fa-arrow-right-from-bracket"></i></div>
                    <span>Logout</span>
                </a>

            </nav>
            <div class="sb-footer">
                <i class="fas fa-shield-halved"></i> Secure session active
            </div>

        </aside>

        <!-- Backdrop -->
        <div class="sb-backdrop" id="sbBackdrop" onclick="closeSidebar()"></div>

        <!-- ═══════ MAIN ═══════ -->
        <div class="main-wrap">

            <!-- Top Nav -->
            <header class="top-nav">
                <div class="nav-left">
                    <button class="menu-btn" onclick="toggleSidebar()">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="breadcrumb-wrap">
                        <i class="fas fa-house bc-home"></i>
                        <i class="fas fa-chevron-right bc-sep"></i>
                        <span class="bc-curr"><?php echo htmlspecialchars($page_title ?? 'Dashboard'); ?></span>
                    </div>
                </div>
                <div class="nav-right">
                    <button class="nav-icon-btn">
                        <i class="fas fa-bell"></i>
                        <span class="notif-dot"></span>
                    </button>
                    <div class="nav-clock" id="navClock"></div>
                    <div class="nav-avatar"><?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?></div>
                </div>
            </header>

            <!-- Content -->
            <div class="page-content">