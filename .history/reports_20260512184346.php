<?php
require_once 'config/database.php';
$page_title = 'Reports';

// ── Date range filter ──
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo   = $_GET['date_to']   ?? date('Y-m-d');

// ── Overview Stats ──
$totalBLs     = $pdo->query("SELECT COUNT(*) FROM bills_of_lading")->fetchColumn();
$totalActive  = $pdo->query("SELECT COUNT(*) FROM bills_of_lading WHERE status='active'")->fetchColumn();
$totalDraft   = $pdo->query("SELECT COUNT(*) FROM bills_of_lading WHERE status='draft'")->fetchColumn();
$totalDone    = $pdo->query("SELECT COUNT(*) FROM bills_of_lading WHERE status='completed'")->fetchColumn();

$totalContainers = $pdo->query("SELECT COUNT(*) FROM bl_items")->fetchColumn();
$totalBags       = $pdo->query("SELECT COALESCE(SUM(number_of_bags),0) FROM bl_items")->fetchColumn();
$totalGross      = $pdo->query("SELECT COALESCE(SUM(gross_weight),0) FROM bl_items")->fetchColumn();
$totalNet        = $pdo->query("SELECT COALESCE(SUM(net_weight),0) FROM bl_items")->fetchColumn();

// ── BLs per day (last 30 days) ──
$blsPerDay = $pdo->query("
    SELECT DATE(created_at) AS day, COUNT(*) AS cnt
    FROM bills_of_lading
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at)
    ORDER BY day ASC
")->fetchAll();

// ── Status distribution ──
$statusDist = $pdo->query("
    SELECT status, COUNT(*) AS cnt
    FROM bills_of_lading
    GROUP BY status
")->fetchAll();

// ── Weight per BL (last 10) ──
$weightData = $pdo->query("
    SELECT b.bl_number,
           COALESCE(SUM(i.gross_weight),0) AS gw,
           COALESCE(SUM(i.net_weight),0)   AS nw
    FROM bills_of_lading b
    LEFT JOIN bl_items i ON b.id = i.bl_id
    GROUP BY b.id
    ORDER BY b.created_at DESC
    LIMIT 10
")->fetchAll();

// ── Bags per BL (last 10) ──
$bagsData = $pdo->query("
    SELECT b.bl_number,
           COALESCE(SUM(i.number_of_bags),0) AS bags
    FROM bills_of_lading b
    LEFT JOIN bl_items i ON b.id = i.bl_id
    GROUP BY b.id
    ORDER BY b.created_at DESC
    LIMIT 10
")->fetchAll();

// ── Monthly summary ──
$monthly = $pdo->query("
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
    GROUP BY DATE_FORMAT(b.created_at,'%Y-%m')
    ORDER BY month_sort DESC
    LIMIT 12
")->fetchAll();

// ── Top containers by bags ──
$topContainers = $pdo->query("
    SELECT i.container_number,
           SUM(i.number_of_bags) AS bags,
           SUM(i.gross_weight)   AS gw,
           COUNT(*)              AS times_used
    FROM bl_items i
    GROUP BY i.container_number
    ORDER BY bags DESC
    LIMIT 8
")->fetchAll();

// ── Prepare chart data ──
$dayLabels    = array_column($blsPerDay, 'day');
$dayCounts    = array_column($blsPerDay, 'cnt');
$statusLabels = array_column($statusDist, 'status');
$statusCounts = array_column($statusDist, 'cnt');
$weightLabels = array_reverse(array_column($weightData, 'bl_number'));
$weightGW     = array_reverse(array_column($weightData, 'gw'));
$weightNW     = array_reverse(array_column($weightData, 'nw'));
$bagsLabels   = array_reverse(array_column($bagsData, 'bl_number'));
$bagsCounts   = array_reverse(array_column($bagsData, 'bags'));

require_once 'includes/header.php';
?>

<style>
/* ── Reports specific ── */
.report-stat {
    background: white;
    border: 1px solid var(--border);
    border-radius: var(--r-lg);
    padding: 1.4rem 1.5rem;
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
    left: 0; top: 0; bottom: 0;
    width: 4px;
    border-radius: 4px 0 0 4px;
}

.report-stat.c-indigo::before  { background: linear-gradient(180deg,var(--indigo),var(--violet)); }
.report-stat.c-emerald::before { background: linear-gradient(180deg,var(--emerald),var(--cyan)); }
.report-stat.c-amber::before   { background: linear-gradient(180deg,var(--amber),#f97316); }
.report-stat.c-rose::before    { background: linear-gradient(180deg,var(--rose),#e879f9); }
.report-stat.c-cyan::before    { background: linear-gradient(180deg,var(--cyan),#6366f1); }
.report-stat.c-violet::before  { background: linear-gradient(180deg,var(--violet),#ec4899); }

.report-stat:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.report-stat-ico {
    width: 48px; height: 48px;
    border-radius: var(--r-md);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.2rem;
    flex-shrink: 0;
}

.report-stat-ico.c-indigo  { background:rgba(99,102,241,0.1);  color:var(--indigo); }
.report-stat-ico.c-emerald { background:rgba(16,185,129,0.1);  color:var(--emerald); }
.report-stat-ico.c-amber   { background:rgba(245,158,11,0.1);  color:var(--amber); }
.report-stat-ico.c-rose    { background:rgba(244,63,94,0.1);   color:var(--rose); }
.report-stat-ico.c-cyan    { background:rgba(6,182,212,0.1);   color:var(--cyan); }
.report-stat-ico.c-violet  { background:rgba(139,92,246,0.1);  color:var(--violet); }

.report-stat-val {
    font-size: 1.65rem;
    font-weight: 800;
    letter-spacing: -0.5px;
    color: var(--txt-1);
    line-height: 1;
    margin-bottom: 3px;
}

.report-stat-lbl {
    font-size: 0.78rem;
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

.chart-card-head {
    padding: 1.2rem 1.5rem;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 8px;
}

.chart-card-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--txt-1);
    display: flex;
    align-items: center;
    gap: 9px;
}

.chart-card-title i { color: var(--indigo); }

.chart-card-sub {
    font-size: 0.75rem;
    color: var(--txt-3);
    font-weight: 400;
}

.chart-card-body {
    padding: 1.25rem 1.5rem;
    position: relative;
}

/* Section Divider */
.section-divider {
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 2rem 0 1.25rem;
}

.section-divider-line {
    flex: 1;
    height: 1px;
    background: var(--border);
}

.section-divider-label {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--txt-3);
    white-space: nowrap;
}

/* Monthly Table */
.month-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 9px;
    border-radius: 20px;
    font-size: 0.72rem;
    font-weight: 700;
    background: rgba(99,102,241,0.08);
    color: var(--indigo);
}

/* Progress bar inside table */
.mini-bar-wrap {
    display: flex;
    align-items: center;
    gap: 8px;
}

.mini-bar-track {
    flex: 1;
    height: 6px;
    background: var(--border);
    border-radius: 99px;
    overflow: hidden;
    min-width: 60px;
}

.mini-bar-fill {
    height: 100%;
    border-radius: 99px;
    transition: width 1s ease;
}

/* Top containers */
.container-rank {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 0;
    border-bottom: 1px solid var(--border-l);
}

.container-rank:last-child { border-bottom: none; }

.rank-num {
    width: 28px; height: 28px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.75rem;
    font-weight: 700;
    flex-shrink: 0;
}

.rank-num.gold   { background:rgba(245,158,11,0.15); color:var(--amber); }
.rank-num.silver { background:rgba(148,163,184,0.15); color:#94a3b8; }
.rank-num.bronze { background:rgba(180,83,9,0.12); color:#b45309; }
.rank-num.other  { background:var(--bg); color:var(--txt-3); }

.rank-container {
    font-family: 'Courier New', monospace;
    font-weight: 700;
    font-size: 0.88rem;
    color: var(--indigo);
}

.rank-meta {
    font-size: 0.75rem;
    color: var(--txt-2);
    margin-top: 1px;
}

/* Export button */
.export-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 14px;
    background: rgba(16,185,129,0.08);
    color: var(--emerald);
    border: 1.5px solid rgba(16,185,129,0.2);
    border-radius: var(--r-sm);
    font-size: 0.78rem;
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

/* Gauge */
.gauge-wrap {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 1rem;
}

.gauge-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--txt-2);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-top: 0.75rem;
}

.gauge-value {
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--txt-1);
}
</style>

<!-- Page Header -->
<div class="page-header d-flex align-items-start justify-content-between flex-wrap gap-3">
    <div>
        <h1 class="page-title">Reports & Analytics</h1>
        <p class="page-sub">Comprehensive overview of all Bills of Lading activity</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <button onclick="window.print()" class="btn-m btn-ghost">
            <i class="fas fa-print"></i> Print
        </button>
        <a href="manage-bl.php" class="btn-m btn-indigo">
            <i class="fas fa-list-check"></i> Manage B/L
        </a>
    </div>
</div>

<!-- ══════════════════════════════════════════
     OVERVIEW STATS
══════════════════════════════════════════ -->
<div class="section-divider">
    <div class="section-divider-label">Overview</div>
    <div class="section-divider-line"></div>
</div>

<div class="row g-3 mb-2">
    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="report-stat c-indigo">
            <div class="report-stat-ico c-indigo"><i class="fas fa-file-invoice"></i></div>
            <div>
                <div class="report-stat-val"><?php echo number_format($totalBLs); ?></div>
                <div class="report-stat-lbl">Total B/Ls</div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="report-stat c-emerald">
            <div class="report-stat-ico c-emerald"><i class="fas fa-circle-check"></i></div>
            <div>
                <div class="report-stat-val"><?php echo number_format($totalActive); ?></div>
                <div class="report-stat-lbl">Active</div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="report-stat c-amber">
            <div class="report-stat-ico c-amber"><i class="fas fa-file-pen"></i></div>
            <div>
                <div class="report-stat-val"><?php echo number_format($totalDraft); ?></div>
                <div class="report-stat-lbl">Drafts</div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="report-stat c-violet">
            <div class="report-stat-ico c-violet"><i class="fas fa-flag-checkered"></i></div>
            <div>
                <div class="report-stat-val"><?php echo number_format($totalDone); ?></div>
                <div class="report-stat-lbl">Completed</div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="report-stat c-cyan">
            <div class="report-stat-ico c-cyan"><i class="fas fa-ship"></i></div>
            <div>
                <div class="report-stat-val"><?php echo number_format($totalContainers); ?></div>
                <div class="report-stat-lbl">Containers</div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="report-stat c-rose">
            <div class="report-stat-ico c-rose"><i class="fas fa-boxes-stacked"></i></div>
            <div>
                <div class="report-stat-val"><?php echo number_format($totalBags); ?></div>
                <div class="report-stat-lbl">Total Bags</div>
            </div>
        </div>
    </div>
</div>

<!-- Weight summary row -->
<div class="row g-3 mb-2">
    <div class="col-md-4">
        <div class="report-stat c-amber">
            <div class="report-stat-ico c-amber"><i class="fas fa-weight-hanging"></i></div>
            <div>
                <div class="report-stat-val"><?php echo number_format($totalGross/1000,2); ?>t</div>
                <div class="report-stat-lbl">Total Gross Weight</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="report-stat c-emerald">
            <div class="report-stat-ico c-emerald"><i class="fas fa-scale-balanced"></i></div>
            <div>
                <div class="report-stat-val"><?php echo number_format($totalNet/1000,2); ?>t</div>
                <div class="report-stat-lbl">Total Net Weight</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="report-stat c-rose">
            <div class="report-stat-ico c-rose"><i class="fas fa-minus-circle"></i></div>
            <div>
                <div class="report-stat-val"><?php echo number_format(($totalGross-$totalNet)/1000,2); ?>t</div>
                <div class="report-stat-lbl">Total Tare Weight</div>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════
     CHARTS ROW 1
══════════════════════════════════════════ -->
<div class="section-divider">
    <div class="section-divider-label">Charts & Trends</div>
    <div class="section-divider-line"></div>
</div>

<div class="row g-3 mb-3">

    <!-- BLs Over Time (Line) -->
    <div class="col-xl-8">
        <div class="chart-card">
            <div class="chart-card-head">
                <div>
                    <div class="chart-card-title">
                        <i class="fas fa-chart-line"></i>
                        Bills of Lading — Last 30 Days
                    </div>
                    <div class="chart-card-sub">Daily creation activity</div>
                </div>
            </div>
            <div class="chart-card-body">
                <canvas id="chartLine" height="110"></canvas>
            </div>
        </div>
    </div>

    <!-- Status Doughnut -->
    <div class="col-xl-4">
        <div class="chart-card">
            <div class="chart-card-head">
                <div>
                    <div class="chart-card-title">
                        <i class="fas fa-chart-pie"></i>
                        Status Distribution
                    </div>
                    <div class="chart-card-sub">All Bills of Lading</div>
                </div>
            </div>
            <div class="chart-card-body" style="display:flex;flex-direction:column;align-items:center;">
                <canvas id="chartDoughnut" height="200" style="max-width:220px;"></canvas>
                <!-- Legend -->
                <div style="display:flex;gap:16px;margin-top:1rem;flex-wrap:wrap;justify-content:center;">
                    <?php foreach ($statusDist as $s): ?>
                    <div style="display:flex;align-items:center;gap:6px;font-size:0.78rem;font-weight:600;color:var(--txt-2);">
                        <div style="width:10px;height:10px;border-radius:50%;background:
                            <?php echo $s['status']=='active'?'#10b981':($s['status']=='draft'?'#f59e0b':'#6366f1'); ?>">
                        </div>
                        <?php echo ucfirst($s['status']); ?> (<?php echo $s['cnt']; ?>)
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- CHARTS ROW 2 -->
<div class="row g-3 mb-3">

    <!-- Weight Bar Chart -->
    <div class="col-xl-7">
        <div class="chart-card">
            <div class="chart-card-head">
                <div>
                    <div class="chart-card-title">
                        <i class="fas fa-chart-bar"></i>
                        Gross vs Net Weight
                    </div>
                    <div class="chart-card-sub">Last 10 Bills of Lading (kg)</div>
                </div>
            </div>
            <div class="chart-card-body">
                <canvas id="chartWeight" height="130"></canvas>
            </div>
        </div>
    </div>

    <!-- Bags Bar -->
    <div class="col-xl-5">
        <div class="chart-card">
            <div class="chart-card-head">
                <div>
                    <div class="chart-card-title">
                        <i class="fas fa-boxes-stacked"></i>
                        Bags per B/L
                    </div>
                    <div class="chart-card-sub">Last 10 Bills of Lading</div>
                </div>
            </div>
            <div class="chart-card-body">
                <canvas id="chartBags" height="130"></canvas>
            </div>
        </div>
    </div>

</div>

<!-- ══════════════════════════════════════════
     MONTHLY TABLE + TOP CONTAINERS
══════════════════════════════════════════ -->
<div class="section-divider">
    <div class="section-divider-label">Detailed Breakdown</div>
    <div class="section-divider-line"></div>
</div>

<div class="row g-3">

    <!-- Monthly Summary -->
    <div class="col-xl-8">
        <div class="data-card">
            <div class="data-card-head">
                <div class="data-card-title">
                    <i class="fas fa-calendar-days"></i>
                    Monthly Summary
                </div>
                <button class="export-btn" onclick="exportMonthly()">
                    <i class="fas fa-download"></i> Export CSV
                </button>
            </div>
            <?php if (empty($monthly)): ?>
            <div class="empty">
                <div class="empty-ico"><i class="fas fa-chart-simple"></i></div>
                <h5>No Data Yet</h5>
                <p>Create some Bills of Lading to see monthly stats.</p>
            </div>
            <?php else: ?>
            <?php
            $maxBags = max(array_column($monthly,'bags')) ?: 1;
            $maxGW   = max(array_column($monthly,'gross')) ?: 1;
            ?>
            <div class="table-responsive">
                <table class="tbl" id="monthlyTable">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>B/Ls</th>
                            <th>Containers</th>
                            <th>Total Bags</th>
                            <th>Gross Weight</th>
                            <th>Net Weight</th>
                            <th>Tare Weight</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($monthly as $m): ?>
                        <tr>
                            <td>
                                <span class="month-badge">
                                    <i class="fas fa-calendar-day" style="font-size:0.6rem;"></i>
                                    <?php echo htmlspecialchars($m['month']); ?>
                                </span>
                            </td>
                            <td style="font-weight:700;color:var(--indigo);"><?php echo $m['bl_count']; ?></td>
                            <td style="font-weight:600;"><?php echo number_format($m['container_count']); ?></td>
                            <td>
                                <div class="mini-bar-wrap">
                                    <span style="font-weight:700;min-width:40px;"><?php echo number_format($m['bags']); ?></span>
                                    <div class="mini-bar-track">
                                        <div class="mini-bar-fill" style="width:<?php echo round(($m['bags']/$maxBags)*100); ?>%;background:linear-gradient(90deg,var(--indigo),var(--violet));"></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="mini-bar-wrap">
                                    <span style="font-weight:600;min-width:70px;white-space:nowrap;"><?php echo number_format($m['gross']/1000,2); ?>t</span>
                                    <div class="mini-bar-track">
                                        <div class="mini-bar-fill" style="width:<?php echo round(($m['gross']/$maxGW)*100); ?>%;background:linear-gradient(90deg,var(--amber),#f97316);"></div>
                                    </div>
                                </div>
                            </td>
                            <td style="font-weight:600;white-space:nowrap;color:var(--emerald);">
                                <?php echo number_format($m['net']/1000,2); ?>t
                            </td>
                            <td style="font-weight:600;white-space:nowrap;color:var(--txt-2);">
                                <?php echo number_format(($m['gross']-$m['net'])/1000,2); ?>t
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background:#f8fafc;border-top:2px solid var(--border);">
                            <td style="padding:11px 15px;font-weight:700;">TOTAL</td>
                            <td style="padding:11px 15px;font-weight:800;color:var(--indigo);">
                                <?php echo number_format(array_sum(array_column($monthly,'bl_count'))); ?>
                            </td>
                            <td style="padding:11px 15px;font-weight:800;">
                                <?php echo number_format(array_sum(array_column($monthly,'container_count'))); ?>
                            </td>
                            <td style="padding:11px 15px;font-weight:800;">
                                <?php echo number_format(array_sum(array_column($monthly,'bags'))); ?>
                            </td>
                            <td style="padding:11px 15px;font-weight:800;color:var(--amber);">
                                <?php echo number_format(array_sum(array_column($monthly,'gross'))/1000,2); ?>t
                            </td>
                            <td style="padding:11px 15px;font-weight:800;color:var(--emerald);">
                                <?php echo number_format(array_sum(array_column($monthly,'net'))/1000,2); ?>t
                            </td>
                            <td style="padding:11px 15px;font-weight:800;color:var(--txt-2);">
                                <?php echo number_format((array_sum(array_column($monthly,'gross'))-array_sum(array_column($monthly,'net')))/1000,2); ?>t
                            </td>
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
            <div style="padding:1rem 1.5rem;">
                <?php if (empty($topContainers)): ?>
                <div class="empty">
                    <div class="empty-ico"><i class="fas fa-ship"></i></div>
                    <h5>No Data Yet</h5>
                </div>
                <?php else: ?>
                <?php
                $maxTopBags = max(array_column($topContainers,'bags')) ?: 1;
                $rankClasses = ['gold','silver','bronze'];
                foreach ($topContainers as $idx => $c):
                    $rankClass = $rankClasses[$idx] ?? 'other';
                ?>
                <div class="container-rank">
                    <div class="rank-num <?php echo $rankClass; ?>"><?php echo $idx+1; ?></div>
                    <div style="flex:1;min-width:0;">
                        <div class="rank-container"><?php echo htmlspecialchars($c['container_number']); ?></div>
                        <div class="rank-meta">
                            <?php echo number_format($c['bags']); ?> bags
                            &nbsp;·&nbsp;
                            <?php echo number_format($c['gw'],0); ?> kg GW
                            &nbsp;·&nbsp;
                            Used <?php echo $c['times_used']; ?>×
                        </div>
                        <div class="mini-bar-track" style="margin-top:5px;">
                            <div class="mini-bar-fill"
                                 style="width:<?php echo round(($c['bags']/$maxTopBags)*100); ?>%;
                                        background:<?php echo $rankClass=='gold'?'linear-gradient(90deg,#f59e0b,#fbbf24)':($rankClass=='silver'?'linear-gradient(90deg,#94a3b8,#cbd5e1)':($rankClass=='bronze'?'linear-gradient(90deg,#b45309,#d97706)':'linear-gradient(90deg,var(--indigo),var(--violet))')); ?>;">
                            </div>
                        </div>
                    </div>
                    <div style="font-size:0.82rem;font-weight:800;color:var(--txt-1);white-space:nowrap;margin-left:4px;">
                        <?php echo number_format($c['bags']); ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<!-- ── Chart.js ── -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
/* ── Global Chart Defaults ── */
Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
Chart.defaults.font.size   = 12;
Chart.defaults.color       = '#64748b';
Chart.defaults.plugins.legend.display = false;
Chart.defaults.plugins.tooltip.backgroundColor = '#0f172a';
Chart.defaults.plugins.tooltip.padding         = 10;
Chart.defaults.plugins.tooltip.cornerRadius    = 10;
Chart.defaults.plugins.tooltip.titleFont       = { weight:'700', size:12 };
Chart.defaults.plugins.tooltip.bodyFont        = { size:11 };

/* ── Data from PHP ── */
const dayLabels    = <?php echo json_encode($dayLabels); ?>;
const dayCounts    = <?php echo json_encode(array_map('intval',$dayCounts)); ?>;
const statusLabels = <?php echo json_encode(array_map('ucfirst',$statusLabels)); ?>;
const statusCounts = <?php echo json_encode(array_map('intval',$statusCounts)); ?>;
const weightLabels = <?php echo json_encode($weightLabels); ?>;
const weightGW     = <?php echo json_encode(array_map('floatval',$weightGW)); ?>;
const weightNW     = <?php echo json_encode(array_map('floatval',$weightNW)); ?>;
const bagsLabels   = <?php echo json_encode($bagsLabels); ?>;
const bagsCounts   = <?php echo json_encode(array_map('intval',$bagsCounts)); ?>;

/* ── 1. Line Chart — BLs per day ── */
const ctxLine = document.getElementById('chartLine').getContext('2d');
const lineGrad = ctxLine.createLinearGradient(0,0,0,200);
lineGrad.addColorStop(0,'rgba(99,102,241,0.25)');
lineGrad.addColorStop(1,'rgba(99,102,241,0.0)');

new Chart(ctxLine, {
    type: 'line',
    data: {
        labels: dayLabels.length ? dayLabels : ['No data'],
        datasets: [{
            label: 'B/Ls Created',
            data: dayCounts.length ? dayCounts : [0],
            borderColor: '#6366f1',
            backgroundColor: lineGrad,
            borderWidth: 2.5,
            pointRadius: 4,
            pointBackgroundColor: '#6366f1',
            pointBorderColor: 'white',
            pointBorderWidth: 2,
            pointHoverRadius: 6,
            tension: 0.4,
            fill: true,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    title: ctx => 'Date: ' + ctx[0].label,
                    label: ctx => ' B/Ls created: ' + ctx.parsed.y,
                }
            }
        },
        scales: {
            x: {
                grid: { display:false },
                border: { display:false },
                ticks: { maxTicksLimit:10, maxRotation:0 }
            },
            y: {
                beginAtZero: true,
                grid: { color:'rgba(0,0,0,0.04)', drawBorder:false },
                border: { display:false },
                ticks: { stepSize:1, precision:0 }
            }
        }
    }
});

/* ── 2. Doughnut — Status ── */
const statusColors = statusLabels.map(s =>
    s==='Active'?'#10b981': s==='Draft'?'#f59e0b':'#6366f1'
);

new Chart(document.getElementById('chartDoughnut'), {
    type: 'doughnut',
    data: {
        labels: statusLabels.length ? statusLabels : ['No Data'],
        datasets: [{
            data: statusCounts.length ? statusCounts : [1],
            backgroundColor: statusColors.length ? statusColors : ['#e2e8f0'],
            borderWidth: 3,
            borderColor: 'white',
            hoverBorderWidth: 3,
            hoverOffset: 8,
        }]
    },
    options: {
        responsive: true,
        cutout: '68%',
        plugins: {
            legend: { display:false },
            tooltip: {
                callbacks: {
                    label: ctx => ` ${ctx.label}: ${ctx.parsed} B/Ls`
                }
            }
        }
    }
});

/* ── 3. Bar Chart — Weight ── */
new Chart(document.getElementById('chartWeight'), {
    type: 'bar',
    data: {
        labels: weightLabels.length ? weightLabels : ['No Data'],
        datasets: [
            {
                label: 'Gross Weight (kg)',
                data: weightGW,
                backgroundColor: 'rgba(245,158,11,0.8)',
                borderRadius: 6,
                borderSkipped: false,
            },
            {
                label: 'Net Weight (kg)',
                data: weightNW,
                backgroundColor: 'rgba(16,185,129,0.8)',
                borderRadius: 6,
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
                    boxWidth: 8,
                    padding: 16,
                    font: { size:11, weight:'600' }
                }
            },
            tooltip: {
                callbacks: {
                    label: ctx => ` ${ctx.dataset.label}: ${Number(ctx.parsed.y).toLocaleString()} kg`
                }
            }
        },
        scales: {
            x: {
                grid: { display:false },
                border: { display:false },
                ticks: {
                    maxRotation: 30,
                    callback: function(val, i) {
                        const lbl = this.getLabelForValue(val);
                        return lbl.length > 10 ? lbl.slice(0,10)+'…' : lbl;
                    }
                }
            },
            y: {
                beginAtZero: true,
                grid: { color:'rgba(0,0,0,0.04)' },
                border: { display:false },
                ticks: {
                    callback: v => (v/1000).toFixed(1)+'t'
                }
            }
        }
    }
});

/* ── 4. Horizontal Bar — Bags ── */
new Chart(document.getElementById('chartBags'), {
    type: 'bar',
    data: {
        labels: bagsLabels.length ? bagsLabels : ['No Data'],
        datasets: [{
            label: 'Bags',
            data: bagsCounts,
            backgroundColor: bagsLabels.map((_,i) => {
                const colors = ['rgba(99,102,241,0.8)','rgba(139,92,246,0.8)','rgba(6,182,212,0.8)','rgba(16,185,129,0.8)','rgba(245,158,11,0.8)','rgba(244,63,94,0.8)'];
                return colors[i % colors.length];
            }),
            borderRadius: 6,
            borderSkipped: false,
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        plugins: {
            legend: { display:false },
            tooltip: {
                callbacks: {
                    label: ctx => ` Bags: ${Number(ctx.parsed.x).toLocaleString()}`
                }
            }
        },
        scales: {
            x: {
                beginAtZero: true,
                grid: { color:'rgba(0,0,0,0.04)' },
                border: { display:false },
                ticks: { precision:0 }
            },
            y: {
                grid: { display:false },
                border: { display:false },
                ticks: {
                    callback: function(val) {
                        const lbl = this.getLabelForValue(val);
                        return lbl.length > 12 ? lbl.slice(0,12)+'…' : lbl;
                    }
                }
            }
        }
    }
});

/* ── CSV Export ── */
function exportMonthly() {
    const rows = [
        ['Month','B/Ls','Containers','Total Bags','Gross Weight (t)','Net Weight (t)','Tare Weight (t)']
    ];

    document.querySelectorAll('#monthlyTable tbody tr').forEach(tr => {
        const cols = tr.querySelectorAll('td');
        if (cols.length >= 7) {
            rows.push([
                cols[0].innerText.trim(),
                cols[1].innerText.trim(),
                cols[2].innerText.trim(),
                cols[3].innerText.trim(),
                cols[4].innerText.trim(),
                cols[5].innerText.trim(),
                cols[6].innerText.trim(),
            ]);
        }
    });

    const csv     = rows.map(r => r.map(c => `"${c}"`).join(',')).join('\n');
    const blob    = new Blob([csv], { type:'text/csv' });
    const url     = URL.createObjectURL(blob);
    const a       = document.createElement('a');
    a.href        = url;
    a.download    = 'bl-monthly-report-' + new Date().toISOString().slice(0,10) + '.csv';
    a.click();
    URL.revokeObjectURL(url);
}
</script>

<?php require_once 'includes/footer.php'; ?>