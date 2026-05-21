<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';
$page_title = 'Reports';

// ── Safe query helper ──
function safeQuery($pdo, $sql, $params = [], $single = false)
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $single ? $stmt->fetchColumn() : $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Query error: " . $e->getMessage());
        return $single ? 0 : [];
    }
}

// ── Overview Stats ──
$totalBLs    = (int) safeQuery($pdo, "SELECT COUNT(*) FROM bills_of_lading", [], true);
$totalActive = (int) safeQuery($pdo, "SELECT COUNT(*) FROM bills_of_lading WHERE status='active'", [], true);
$totalDraft  = (int) safeQuery($pdo, "SELECT COUNT(*) FROM bills_of_lading WHERE status='draft'", [], true);
$totalDone   = (int) safeQuery($pdo, "SELECT COUNT(*) FROM bills_of_lading WHERE status='completed'", [], true);

$totalContainers = (int)   safeQuery($pdo, "SELECT COUNT(*) FROM bl_items", [], true);
$totalBags       = (float) safeQuery($pdo, "SELECT COALESCE(SUM(number_of_bags),0) FROM bl_items", [], true);
$totalGross      = (float) safeQuery($pdo, "SELECT COALESCE(SUM(gross_weight),0) FROM bl_items", [], true);
$totalNet        = (float) safeQuery($pdo, "SELECT COALESCE(SUM(net_weight),0) FROM bl_items", [], true);
$totalTare       = $totalGross - $totalNet;

// ── BLs per day (last 30 days) ──
$blsPerDay = safeQuery($pdo, "
    SELECT DATE(created_at) AS day, COUNT(*) AS cnt
    FROM bills_of_lading
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at)
    ORDER BY day ASC
");

// ── Status distribution ──
$statusDist = safeQuery($pdo, "
    SELECT status, COUNT(*) AS cnt
    FROM bills_of_lading
    GROUP BY status
");

// ── Weight per BL (last 10) ──
$weightData = safeQuery($pdo, "
    SELECT b.bl_number,
           COALESCE(SUM(i.gross_weight),0) AS gw,
           COALESCE(SUM(i.net_weight),0)   AS nw
    FROM bills_of_lading b
    LEFT JOIN bl_items i ON b.id = i.bl_id
    GROUP BY b.id, b.bl_number, b.created_at
    ORDER BY b.created_at DESC
    LIMIT 10
");

// ── Bags per BL (last 10) ──
$bagsData = safeQuery($pdo, "
    SELECT b.bl_number,
           COALESCE(SUM(i.number_of_bags),0) AS bags
    FROM bills_of_lading b
    LEFT JOIN bl_items i ON b.id = i.bl_id
    GROUP BY b.id, b.bl_number, b.created_at
    ORDER BY b.created_at DESC
    LIMIT 10
");

// ── Monthly summary ──
$monthly = safeQuery($pdo, "
    SELECT
        DATE_FORMAT(b.created_at,'%b %Y')        AS month,
        DATE_FORMAT(b.created_at,'%Y-%m')        AS month_sort,
        COUNT(DISTINCT b.id)                     AS bl_count,
        COUNT(i.id)                              AS container_count,
        COALESCE(SUM(i.number_of_bags),0)        AS bags,
        COALESCE(SUM(i.gross_weight),0)          AS gross,
        COALESCE(SUM(i.net_weight),0)            AS net
    FROM bills_of_lading b
    LEFT JOIN bl_items i ON b.id = i.bl_id
    GROUP BY DATE_FORMAT(b.created_at,'%Y-%m'), DATE_FORMAT(b.created_at,'%b %Y')
    ORDER BY month_sort DESC
    LIMIT 12
");

// ── Top containers by bags ──
$topContainers = safeQuery($pdo, "
    SELECT i.container_number,
           SUM(i.number_of_bags) AS bags,
           SUM(i.gross_weight)   AS gw,
           COUNT(*)              AS times_used
    FROM bl_items i
    GROUP BY i.container_number
    ORDER BY bags DESC
    LIMIT 8
");

// ── Safe chart data preparation ──
$dayLabels  = !empty($blsPerDay)  ? array_column($blsPerDay,  'day') : [];
$dayCounts  = !empty($blsPerDay)  ? array_map('intval', array_column($blsPerDay, 'cnt')) : [];

$statusLabels = !empty($statusDist) ? array_map('ucfirst', array_column($statusDist, 'status')) : [];
$statusCounts = !empty($statusDist) ? array_map('intval',  array_column($statusDist, 'cnt'))    : [];

$weightLabels = !empty($weightData) ? array_reverse(array_column($weightData, 'bl_number')) : [];
$weightGW     = !empty($weightData) ? array_reverse(array_map('floatval', array_column($weightData, 'gw'))) : [];
$weightNW     = !empty($weightData) ? array_reverse(array_map('floatval', array_column($weightData, 'nw'))) : [];

$bagsLabels = !empty($bagsData) ? array_reverse(array_column($bagsData, 'bl_number')) : [];
$bagsCounts = !empty($bagsData) ? array_reverse(array_map('intval',  array_column($bagsData, 'bags')))    : [];

$maxBags = !empty($monthly) ? (max(array_column($monthly, 'bags')) ?: 1) : 1;
$maxGW   = !empty($monthly) ? (max(array_column($monthly, 'gross')) ?: 1) : 1;
$maxTopBags = !empty($topContainers) ? (max(array_column($topContainers, 'bags')) ?: 1) : 1;

require_once 'includes/header.php';
?>

<style>
    .report-stat {
        background: white;
        border: 1px solid var(--border);
        border-radius: var(--r-lg);
        padding: 1.3rem 1.4rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        transition: var(--t);
        position: relative;
        overflow: hidden;
    }

    .report-stat::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 4px;
    }

    .report-stat.c-indigo::before {
        background: linear-gradient(180deg, var(--indigo), var(--violet));
    }

    .report-stat.c-emerald::before {
        background: linear-gradient(180deg, var(--emerald), var(--cyan));
    }

    .report-stat.c-amber::before {
        background: linear-gradient(180deg, var(--amber), #f97316);
    }

    .report-stat.c-rose::before {
        background: linear-gradient(180deg, var(--rose), #e879f9);
    }

    .report-stat.c-cyan::before {
        background: linear-gradient(180deg, var(--cyan), #6366f1);
    }

    .report-stat.c-violet::before {
        background: linear-gradient(180deg, var(--violet), #ec4899);
    }

    .report-stat:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }

    .rs-ico {
        width: 46px;
        height: 46px;
        border-radius: var(--r-md);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.15rem;
        flex-shrink: 0;
    }

    .rs-ico.c-indigo {
        background: rgba(99, 102, 241, 0.1);
        color: var(--indigo);
    }

    .rs-ico.c-emerald {
        background: rgba(16, 185, 129, 0.1);
        color: var(--emerald);
    }

    .rs-ico.c-amber {
        background: rgba(245, 158, 11, 0.1);
        color: var(--amber);
    }

    .rs-ico.c-rose {
        background: rgba(244, 63, 94, 0.1);
        color: var(--rose);
    }

    .rs-ico.c-cyan {
        background: rgba(6, 182, 212, 0.1);
        color: var(--cyan);
    }

    .rs-ico.c-violet {
        background: rgba(139, 92, 246, 0.1);
        color: var(--violet);
    }

    .rs-val {
        font-size: 1.6rem;
        font-weight: 800;
        letter-spacing: -0.5px;
        color: var(--txt-1);
        line-height: 1;
        margin-bottom: 3px;
    }

    .rs-lbl {
        font-size: 0.76rem;
        font-weight: 500;
        color: var(--txt-2);
    }

    /* Chart Cards */
    .chart-card {
        background: white;
        border: 1px solid var(--border);
        border-radius: var(--r-lg);
        overflow: hidden;
        box-shadow: var(--shadow-sm);
        height: 100%;
    }

    .chart-head {
        padding: 1.1rem 1.4rem;
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 8px;
    }

    .chart-title {
        font-size: 0.93rem;
        font-weight: 700;
        color: var(--txt-1);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .chart-title i {
        color: var(--indigo);
    }

    .chart-sub {
        font-size: 0.73rem;
        color: var(--txt-3);
        margin-top: 2px;
    }

    .chart-body {
        padding: 1.25rem 1.4rem;
    }

    /* Section label */
    .sec-label {
        display: flex;
        align-items: center;
        gap: 12px;
        margin: 1.75rem 0 1.1rem;
    }

    .sec-label-line {
        flex: 1;
        height: 1px;
        background: var(--border);
    }

    .sec-label-txt {
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        color: var(--txt-3);
        white-space: nowrap;
        padding: 4px 10px;
        background: white;
        border: 1px solid var(--border);
        border-radius: 20px;
    }

    /* Mini bar */
    .mini-bar-wrap {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .mini-bar-track {
        flex: 1;
        height: 5px;
        background: var(--border);
        border-radius: 99px;
        overflow: hidden;
        min-width: 50px;
    }

    .mini-bar-fill {
        height: 100%;
        border-radius: 99px;
    }

    /* Month badge */
    .month-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 9px;
        border-radius: 20px;
        font-size: 0.72rem;
        font-weight: 700;
        background: rgba(99, 102, 241, 0.08);
        color: var(--indigo);
    }

    /* Rank */
    .rank-row {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 11px 0;
        border-bottom: 1px solid var(--border-l);
    }

    .rank-row:last-child {
        border-bottom: none;
    }

    .rank-num {
        width: 27px;
        height: 27px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.72rem;
        font-weight: 800;
        flex-shrink: 0;
    }

    .rn-gold {
        background: rgba(245, 158, 11, 0.15);
        color: #d97706;
    }

    .rn-silver {
        background: rgba(148, 163, 184, 0.15);
        color: #64748b;
    }

    .rn-bronze {
        background: rgba(180, 83, 9, 0.12);
        color: #b45309;
    }

    .rn-other {
        background: var(--bg);
        color: var(--txt-3);
    }

    .rank-name {
        font-family: 'Courier New', monospace;
        font-weight: 700;
        font-size: 0.86rem;
        color: var(--indigo);
    }

    .rank-meta {
        font-size: 0.73rem;
        color: var(--txt-2);
        margin-top: 1px;
    }

    /* Export btn */
    .export-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 13px;
        background: rgba(16, 185, 129, 0.07);
        color: var(--emerald);
        border: 1.5px solid rgba(16, 185, 129, 0.2);
        border-radius: var(--r-sm);
        font-size: 0.76rem;
        font-weight: 600;
        cursor: pointer;
        transition: var(--t);
        font-family: 'Inter', sans-serif;
        text-decoration: none;
    }

    .export-btn:hover {
        background: var(--emerald);
        color: white;
        border-color: var(--emerald);
    }

    @media print {

        .sidebar,
        .top-nav,
        .page-header .btn-m,
        .no-print {
            display: none !important;
        }

        .main-wrap {
            margin-left: 0 !important;
        }

        .page-content {
            padding: 0 !important;
        }
    }
</style>

<!-- Page Header -->
<div class="page-header d-flex align-items-start justify-content-between flex-wrap gap-3">
    <div>
        <h1 class="page-title">Reports & Analytics</h1>
        <p class="page-sub">Comprehensive overview of all Bills of Lading activity</p>
    </div>
    <div class="d-flex gap-2 flex-wrap no-print">
        <button onclick="window.print()" class="btn-m btn-ghost">
            <i class="fas fa-print"></i> Print
        </button>
        <!-- <a href="add-bl.php" class="btn-m btn-indigo">
            <i class="fas fa-circle-plus"></i> New B/L
        </a> -->
    </div>
</div>

<!-- ══ OVERVIEW STATS ══ -->
<div class="sec-label">
    <div class="sec-label-line"></div>
    <div class="sec-label-txt">Overview</div>
    <div class="sec-label-line"></div>
</div>

<div class="row g-3 mb-3">
    <div class="col-xl-2 col-md-4 col-6">
        <div class="report-stat c-indigo">
            <div class="rs-ico c-indigo"><i class="fas fa-file-invoice"></i></div>
            <div>
                <div class="rs-val"><?php echo number_format($totalBLs); ?></div>
                <div class="rs-lbl">Total B/Ls</div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="report-stat c-emerald">
            <div class="rs-ico c-emerald"><i class="fas fa-circle-check"></i></div>
            <div>
                <div class="rs-val"><?php echo number_format($totalActive); ?></div>
                <div class="rs-lbl">Active</div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="report-stat c-amber">
            <div class="rs-ico c-amber"><i class="fas fa-file-pen"></i></div>
            <div>
                <div class="rs-val"><?php echo number_format($totalDraft); ?></div>
                <div class="rs-lbl">Drafts</div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="report-stat c-violet">
            <div class="rs-ico c-violet"><i class="fas fa-flag-checkered"></i></div>
            <div>
                <div class="rs-val"><?php echo number_format($totalDone); ?></div>
                <div class="rs-lbl">Completed</div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="report-stat c-cyan">
            <div class="rs-ico c-cyan"><i class="fas fa-ship"></i></div>
            <div>
                <div class="rs-val"><?php echo number_format($totalContainers); ?></div>
                <div class="rs-lbl">Containers</div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="report-stat c-rose">
            <div class="rs-ico c-rose"><i class="fas fa-boxes-stacked"></i></div>
            <div>
                <div class="rs-val"><?php echo number_format($totalBags); ?></div>
                <div class="rs-lbl">Total Bags</div>
            </div>
        </div>
    </div>
</div>

<!-- Weight row -->
<div class="row g-3 mb-2">
    <div class="col-md-4">
        <div class="report-stat c-amber">
            <div class="rs-ico c-amber"><i class="fas fa-weight-hanging"></i></div>
            <div>
                <div class="rs-val"><?php echo number_format($totalGross, 4); ?> MT</div>
                <div class="rs-lbl">Total Gross Weight</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="report-stat c-emerald">
            <div class="rs-ico c-emerald"><i class="fas fa-scale-balanced"></i></div>
            <div>
                <div class="rs-val"><?php echo number_format($totalNet, 4); ?>MT</div>
                <div class="rs-lbl">Total Net Weight</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="report-stat c-rose">
            <div class="rs-ico c-rose"><i class="fas fa-circle-minus"></i></div>
            <div>
                <div class="rs-val"><?php echo number_format($totalTare, 4); ?>MT</div>
                <div class="rs-lbl">Total Tare Weight</div>
            </div>
        </div>
    </div>
</div>

<!-- ══ CHARTS ══ -->
<div class="sec-label">
    <div class="sec-label-line"></div>
    <div class="sec-label-txt">Charts & Trends</div>
    <div class="sec-label-line"></div>
</div>

<!-- Row 1 -->
<div class="row g-3 mb-3">

    <!-- Line Chart -->
    <div class="col-xl-8">
        <div class="chart-card">
            <div class="chart-head">
                <div>
                    <div class="chart-title"><i class="fas fa-chart-line"></i> Bills of Lading — Last 30 Days</div>
                    <div class="chart-sub">Daily creation activity</div>
                </div>
            </div>
            <div class="chart-body">
                <canvas id="chartLine" height="110"></canvas>
            </div>
        </div>
    </div>

    <!-- Doughnut -->
    <div class="col-xl-4">
        <div class="chart-card">
            <div class="chart-head">
                <div>
                    <div class="chart-title"><i class="fas fa-chart-pie"></i> Status Distribution</div>
                    <div class="chart-sub">All Bills of Lading</div>
                </div>
            </div>
            <div class="chart-body" style="display:flex;flex-direction:column;align-items:center;">
                <canvas id="chartDoughnut" height="190" style="max-width:210px;"></canvas>
                <div style="display:flex;gap:14px;margin-top:1rem;flex-wrap:wrap;justify-content:center;">
                    <?php foreach ($statusDist as $s):
                        $col = $s['status'] == 'active' ? '#10b981' : ($s['status'] == 'draft' ? '#f59e0b' : '#6366f1');
                    ?>
                        <div style="display:flex;align-items:center;gap:6px;font-size:0.76rem;font-weight:600;color:var(--txt-2);">
                            <div style="width:9px;height:9px;border-radius:50%;background:<?php echo $col; ?>;flex-shrink:0;"></div>
                            <?php echo ucfirst($s['status']); ?> (<?php echo $s['cnt']; ?>)
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($statusDist)): ?>
                        <div style="font-size:0.76rem;color:var(--txt-3);">No data yet</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Row 2 -->
<div class="row g-3 mb-3">

    <!-- Weight Bar -->
    <div class="col-xl-7">
        <div class="chart-card">
            <div class="chart-head">
                <div>
                    <div class="chart-title"><i class="fas fa-chart-bar"></i> Gross vs Net Weight</div>
                    <div class="chart-sub">Last 10 Bills of Lading (kg)</div>
                </div>
            </div>
            <div class="chart-body">
                <canvas id="chartWeight" height="130"></canvas>
            </div>
        </div>
    </div>

    <!-- Bags Horizontal Bar -->
    <div class="col-xl-5">
        <div class="chart-card">
            <div class="chart-head">
                <div>
                    <div class="chart-title"><i class="fas fa-boxes-stacked"></i> Bags per B/L</div>
                    <div class="chart-sub">Last 10 Bills of Lading</div>
                </div>
            </div>
            <div class="chart-body">
                <canvas id="chartBags" height="130"></canvas>
            </div>
        </div>
    </div>

</div>

<!-- ══ DETAILED BREAKDOWN ══ -->
<div class="sec-label">
    <div class="sec-label-line"></div>
    <div class="sec-label-txt">Detailed Breakdown</div>
    <div class="sec-label-line"></div>
</div>

<div class="row g-3">

    <!-- Monthly Table -->
    <div class="col-xl-8">
        <div class="data-card">
            <div class="data-card-head">
                <div class="data-card-title">
                    <i class="fas fa-calendar-days"></i>
                    Monthly Summary
                </div>
                <button class="export-btn" onclick="exportCSV()">
                    <i class="fas fa-download"></i> Export CSV
                </button>
            </div>

            <?php if (empty($monthly)): ?>
                <div class="empty">
                    <div class="empty-ico"><i class="fas fa-chart-simple"></i></div>
                    <h5>No Data Yet</h5>
                    <p>Create some Shipments</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="tbl" id="monthlyTbl">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>B/Ls</th>
                                <th>Containers</th>
                                <th>Total Bags</th>
                                <th>Gross (t)</th>
                                <th>Net (t)</th>
                                <th>Tare (t)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($monthly as $m):
                                $bagsPct  = $maxBags > 0 ? round(($m['bags'] / $maxBags) * 100) : 0;
                                $grossPct = $maxGW   > 0 ? round(($m['gross'] / $maxGW) * 100)  : 0;
                            ?>
                                <tr>
                                    <td>
                                        <span class="month-badge">
                                            <i class="fas fa-calendar-day" style="font-size:0.55rem;"></i>
                                            <?php echo htmlspecialchars($m['month']); ?>
                                        </span>
                                    </td>
                                    <td style="font-weight:700;color:var(--indigo);"><?php echo $m['bl_count']; ?></td>
                                    <td style="font-weight:600;"><?php echo number_format($m['container_count']); ?></td>
                                    <td>
                                        <div class="mini-bar-wrap">
                                            <span style="font-weight:700;min-width:36px;"><?php echo number_format($m['bags']); ?></span>
                                            <div class="mini-bar-track">
                                                <div class="mini-bar-fill" style="width:<?php echo $bagsPct; ?>%;background:linear-gradient(90deg,var(--indigo),var(--violet));"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="mini-bar-wrap">
                                            <span style="font-weight:600;min-width:48px;"><?php echo number_format($m['gross'], 2); ?></span>
                                            <div class="mini-bar-track">
                                                <div class="mini-bar-fill" style="width:<?php echo $grossPct; ?>%;background:linear-gradient(90deg,var(--amber),#f97316);"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="font-weight:600;color:var(--emerald);"><?php echo number_format($m['net'], 2); ?></td>
                                    <td style="font-weight:600;color:var(--txt-2);"><?php echo number_format(($m['gross'] - $m['net']), 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <?php
                            $sumBL    = array_sum(array_column($monthly, 'bl_count'));
                            $sumCont  = array_sum(array_column($monthly, 'container_count'));
                            $sumBags  = array_sum(array_column($monthly, 'bags'));
                            $sumGross = array_sum(array_column($monthly, 'gross'));
                            $sumNet   = array_sum(array_column($monthly, 'net'));
                            ?>
                            <tr style="background:#f8fafc;border-top:2px solid var(--border);">
                                <td style="padding:11px 15px;font-weight:700;font-size:0.82rem;">TOTALS</td>
                                <td style="padding:11px 15px;font-weight:800;color:var(--indigo);"><?php echo number_format($sumBL); ?></td>
                                <td style="padding:11px 15px;font-weight:800;"><?php echo number_format($sumCont); ?></td>
                                <td style="padding:11px 15px;font-weight:800;"><?php echo number_format($sumBags); ?></td>
                                <td style="padding:11px 15px;font-weight:800;color:var(--amber);"><?php echo number_format($sumGross, 2); ?></td>
                                <td style="padding:11px 15px;font-weight:800;color:var(--emerald);"><?php echo number_format($sumNet, 2); ?></td>
                                <td style="padding:11px 15px;font-weight:800;color:var(--txt-2);"><?php echo number_format(($sumGross - $sumNet), 2); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Top Containers -->
    <div class="col-xl-4">
        <div class="data-card" style="height:100%;">
            <div class="data-card-head">
                <div class="data-card-title">
                    <i class="fas fa-trophy"></i>
                    Top Containers by Bags
                </div>
            </div>
            <div style="padding:0.75rem 1.4rem 1.25rem;">
                <?php if (empty($topContainers)): ?>
                    <div class="empty">
                        <div class="empty-ico"><i class="fas fa-ship"></i></div>
                        <h5>No Data Yet</h5>
                        <p>Add container data to see rankings.</p>
                    </div>
                <?php else: ?>
                    <?php
                    $rnClasses = ['rn-gold', 'rn-silver', 'rn-bronze'];
                    foreach ($topContainers as $idx => $c):
                        $rnClass  = $rnClasses[$idx] ?? 'rn-other';
                        $pct      = $maxTopBags > 0 ? round(($c['bags'] / $maxTopBags) * 100) : 0;
                        $barColor = $idx === 0
                            ? 'linear-gradient(90deg,#f59e0b,#fbbf24)'
                            : ($idx === 1
                                ? 'linear-gradient(90deg,#94a3b8,#cbd5e1)'
                                : ($idx === 2
                                    ? 'linear-gradient(90deg,#b45309,#d97706)'
                                    : 'linear-gradient(90deg,var(--indigo),var(--violet))'));
                    ?>
                        <div class="rank-row">
                            <div class="rank-num <?php echo $rnClass; ?>"><?php echo $idx + 1; ?></div>
                            <div style="flex:1;min-width:0;">
                                <div class="rank-name"><?php echo htmlspecialchars($c['container_number']); ?></div>
                                <div class="rank-meta">
                                    <?php echo number_format($c['bags']); ?> bags
                                    &nbsp;·&nbsp;
                                    <?php echo number_format($c['gw'], 0); ?> MT GW
                                    &nbsp;·&nbsp;
                                    <?php echo $c['times_used']; ?>× used
                                </div>
                                <div class="mini-bar-track" style="margin-top:5px;">
                                    <div class="mini-bar-fill" style="width:<?php echo $pct; ?>%;background:<?php echo $barColor; ?>;"></div>
                                </div>
                            </div>
                            <div style="font-size:0.85rem;font-weight:800;color:var(--txt-1);white-space:nowrap;padding-left:8px;">
                                <?php echo number_format($c['bags']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<script>
    /* ── Global defaults ── */
    Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
    Chart.defaults.font.size = 11;
    Chart.defaults.color = '#64748b';
    Chart.defaults.plugins.legend.display = false;
    Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(15,23,42,0.92)';
    Chart.defaults.plugins.tooltip.padding = 10;
    Chart.defaults.plugins.tooltip.cornerRadius = 10;
    Chart.defaults.plugins.tooltip.titleFont = {
        weight: '700',
        size: 12
    };
    Chart.defaults.plugins.tooltip.bodyFont = {
        size: 11
    };
    Chart.defaults.plugins.tooltip.displayColors = true;

    /* ── PHP data ── */
    const dayLabels = <?php echo json_encode($dayLabels); ?>;
    const dayCounts = <?php echo json_encode($dayCounts); ?>;
    const statusLabels = <?php echo json_encode($statusLabels); ?>;
    const statusCounts = <?php echo json_encode($statusCounts); ?>;
    const weightLabels = <?php echo json_encode($weightLabels); ?>;
    const weightGW = <?php echo json_encode($weightGW); ?>;
    const weightNW = <?php echo json_encode($weightNW); ?>;
    const bagsLabels = <?php echo json_encode($bagsLabels); ?>;
    const bagsCounts = <?php echo json_encode($bagsCounts); ?>;

    /* ── Helpers ── */
    function shortLabel(str, max = 12) {
        return str && str.length > max ? str.slice(0, max) + '…' : (str || '');
    }

    /* ══ 1. LINE — BLs per day ══ */
    (function() {
        const ctx = document.getElementById('chartLine');
        if (!ctx) return;
        const c = ctx.getContext('2d');
        const grad = c.createLinearGradient(0, 0, 0, 220);
        grad.addColorStop(0, 'rgba(99,102,241,0.22)');
        grad.addColorStop(1, 'rgba(99,102,241,0)');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: dayLabels.length ? dayLabels : ['No data'],
                datasets: [{
                    label: 'B/Ls Created',
                    data: dayCounts.length ? dayCounts : [0],
                    borderColor: '#6366f1',
                    backgroundColor: grad,
                    borderWidth: 2.5,
                    pointRadius: 4,
                    pointBackgroundColor: '#6366f1',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointHoverRadius: 6,
                    tension: 0.4,
                    fill: true,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            title: c => 'Date: ' + c[0].label,
                            label: c => '  B/Ls created: ' + c.parsed.y,
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        border: {
                            display: false
                        },
                        ticks: {
                            maxTicksLimit: 10,
                            maxRotation: 0
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.04)'
                        },
                        border: {
                            display: false
                        },
                        ticks: {
                            stepSize: 1,
                            precision: 0
                        }
                    }
                }
            }
        });
    })();

    /* ══ 2. DOUGHNUT — Status ══ */
    (function() {
        const ctx = document.getElementById('chartDoughnut');
        if (!ctx) return;

        const colors = statusLabels.map(s =>
            s === 'Active' ? '#10b981' : s === 'Draft' ? '#f59e0b' : '#6366f1'
        );

        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: statusLabels.length ? statusLabels : ['No Data'],
                datasets: [{
                    data: statusCounts.length ? statusCounts : [1],
                    backgroundColor: colors.length ? colors : ['#e2e8f0'],
                    borderWidth: 4,
                    borderColor: '#fff',
                    hoverBorderWidth: 4,
                    hoverOffset: 10,
                }]
            },
            options: {
                responsive: true,
                cutout: '70%',
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: c => '  ' + c.label + ': ' + c.parsed + ' B/Ls'
                        }
                    }
                }
            }
        });
    })();

    /* ══ 3. BAR — Weight ══ */
    (function() {
        const ctx = document.getElementById('chartWeight');
        if (!ctx) return;

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: weightLabels.length ? weightLabels : ['No Data'],
                datasets: [{
                        label: 'Gross Weight (kg)',
                        data: weightGW.length ? weightGW : [0],
                        backgroundColor: 'rgba(245,158,11,0.8)',
                        borderRadius: 5,
                        borderSkipped: false,
                    },
                    {
                        label: 'Net Weight (kg)',
                        data: weightNW.length ? weightNW : [0],
                        backgroundColor: 'rgba(16,185,129,0.8)',
                        borderRadius: 5,
                        borderSkipped: false,
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        align: 'end',
                        labels: {
                            usePointStyle: true,
                            pointStyle: 'circle',
                            boxWidth: 7,
                            padding: 14,
                            font: {
                                size: 11,
                                weight: '600'
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: c => '  ' + c.dataset.label + ': ' + Number(c.parsed.y).toLocaleString() + ' kg'
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        border: {
                            display: false
                        },
                        ticks: {
                            maxRotation: 30,
                            callback: function(val) {
                                return shortLabel(this.getLabelForValue(val), 10);
                            }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.04)'
                        },
                        border: {
                            display: false
                        },
                        ticks: {
                            callback: v => (v / 1000).toFixed(1) + 't'
                        }
                    }
                }
            }
        });
    })();

    /* ══ 4. HORIZONTAL BAR — Bags ══ */
    (function() {
        const ctx = document.getElementById('chartBags');
        if (!ctx) return;

        const palette = [
            'rgba(99,102,241,0.8)',
            'rgba(139,92,246,0.8)',
            'rgba(6,182,212,0.8)',
            'rgba(16,185,129,0.8)',
            'rgba(245,158,11,0.8)',
            'rgba(244,63,94,0.8)',
            'rgba(236,72,153,0.8)',
            'rgba(20,184,166,0.8)',
            'rgba(168,85,247,0.8)',
            'rgba(251,146,60,0.8)',
        ];

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: bagsLabels.length ? bagsLabels : ['No Data'],
                datasets: [{
                    label: 'Bags',
                    data: bagsCounts.length ? bagsCounts : [0],
                    backgroundColor: bagsLabels.map((_, i) => palette[i % palette.length]),
                    borderRadius: 5,
                    borderSkipped: false,
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: c => '  Bags: ' + Number(c.parsed.x).toLocaleString()
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.04)'
                        },
                        border: {
                            display: false
                        },
                        ticks: {
                            precision: 0
                        }
                    },
                    y: {
                        grid: {
                            display: false
                        },
                        border: {
                            display: false
                        },
                        ticks: {
                            callback: function(val) {
                                return shortLabel(this.getLabelForValue(val), 12);
                            }
                        }
                    }
                }
            }
        });
    })();

    /* ══ CSV Export ══ */
    function exportCSV() {
        const rows = [
            ['Month', 'B/Ls', 'Containers', 'Total Bags', 'Gross (t)', 'Net (t)', 'Tare (t)']
        ];
        document.querySelectorAll('#monthlyTbl tbody tr').forEach(tr => {
            const cells = tr.querySelectorAll('td');
            if (cells.length >= 7) {
                rows.push([
                    cells[0].innerText.trim(),
                    cells[1].innerText.trim(),
                    cells[2].innerText.trim(),
                    cells[3].innerText.trim(),
                    cells[4].innerText.trim(),
                    cells[5].innerText.trim(),
                    cells[6].innerText.trim(),
                ]);
            }
        });
        const csv = rows.map(r => r.map(c => '"' + c.replace(/"/g, '""') + '"').join(',')).join('\n');
        const blob = new Blob([csv], {
            type: 'text/csv;charset=utf-8;'
        });
        const url = URL.createObjectURL(blob);
        const a = Object.assign(document.createElement('a'), {
            href: url,
            download: 'bl-report-' + new Date().toISOString().slice(0, 10) + '.csv'
        });
        a.click();
        URL.revokeObjectURL(url);
    }
</script>

<?php require_once 'includes/footer.php'; ?>