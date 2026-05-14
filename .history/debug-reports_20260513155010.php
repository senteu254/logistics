<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once 'config/database.php';

echo "<h2>DB Connected ✅</h2>";

// Test each query one by one
try {
    $r = $pdo->query("SELECT COUNT(*) FROM bills_of_lading")->fetchColumn();
    echo "Total BLs: $r ✅<br>";
} catch(Exception $e) { echo "❌ Error 1: " . $e->getMessage() . "<br>"; }

try {
    $r = $pdo->query("SELECT COUNT(*) FROM bills_of_lading WHERE status='active'")->fetchColumn();
    echo "Active BLs: $r ✅<br>";
} catch(Exception $e) { echo "❌ Error 2: " . $e->getMessage() . "<br>"; }

try {
    $r = $pdo->query("SELECT COUNT(*) FROM bills_of_lading WHERE status='draft'")->fetchColumn();
    echo "Draft BLs: $r ✅<br>";
} catch(Exception $e) { echo "❌ Error 3: " . $e->getMessage() . "<br>"; }

try {
    $r = $pdo->query("SELECT COUNT(*) FROM bills_of_lading WHERE status='completed'")->fetchColumn();
    echo "Completed BLs: $r ✅<br>";
} catch(Exception $e) { echo "❌ Error 4: " . $e->getMessage() . "<br>"; }

try {
    $r = $pdo->query("SELECT COUNT(*) FROM bl_items")->fetchColumn();
    echo "Total containers: $r ✅<br>";
} catch(Exception $e) { echo "❌ Error 5: " . $e->getMessage() . "<br>"; }

try {
    $r = $pdo->query("SELECT COALESCE(SUM(number_of_bags),0) FROM bl_items")->fetchColumn();
    echo "Total bags: $r ✅<br>";
} catch(Exception $e) { echo "❌ Error 6: " . $e->getMessage() . "<br>"; }

try {
    $r = $pdo->query("SELECT COALESCE(SUM(gross_weight),0) FROM bl_items")->fetchColumn();
    echo "Total GW: $r ✅<br>";
} catch(Exception $e) { echo "❌ Error 7: " . $e->getMessage() . "<br>"; }

try {
    $r = $pdo->query("
        SELECT DATE(created_at) AS day, COUNT(*) AS cnt
        FROM bills_of_lading
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY day ASC
    ")->fetchAll();
    echo "BLs per day query: ✅ (" . count($r) . " rows)<br>";
} catch(Exception $e) { echo "❌ Error 8: " . $e->getMessage() . "<br>"; }

try {
    $r = $pdo->query("
        SELECT status, COUNT(*) AS cnt
        FROM bills_of_lading
        GROUP BY status
    ")->fetchAll();
    echo "Status dist: ✅ (" . count($r) . " rows)<br>";
} catch(Exception $e) { echo "❌ Error 9: " . $e->getMessage() . "<br>"; }

try {
    $r = $pdo->query("
        SELECT b.bl_number,
               COALESCE(SUM(i.gross_weight),0) AS gw,
               COALESCE(SUM(i.net_weight),0)   AS nw
        FROM bills_of_lading b
        LEFT JOIN bl_items i ON b.id = i.bl_id
        GROUP BY b.id
        ORDER BY b.created_at DESC
        LIMIT 10
    ")->fetchAll();
    echo "Weight data: ✅ (" . count($r) . " rows)<br>";
} catch(Exception $e) { echo "❌ Error 10: " . $e->getMessage() . "<br>"; }

try {
    $r = $pdo->query("
        SELECT b.bl_number,
               COALESCE(SUM(i.number_of_bags),0) AS bags
        FROM bills_of_lading b
        LEFT JOIN bl_items i ON b.id = i.bl_id
        GROUP BY b.id
        ORDER BY b.created_at DESC
        LIMIT 10
    ")->fetchAll();
    echo "Bags data: ✅ (" . count($r) . " rows)<br>";
} catch(Exception $e) { echo "❌ Error 11: " . $e->getMessage() . "<br>"; }

try {
    $r = $pdo->query("
        SELECT
            DATE_FORMAT(b.created_at,'%b %Y') AS month,
            DATE_FORMAT(b.created_at,'%Y-%m') AS month_sort,
            COUNT(DISTINCT b.id)              AS bl_count,
            COUNT(i.id)                       AS container_count,
            COALESCE(SUM(i.number_of_bags),0) AS bags,
            COALESCE(SUM(i.gross_weight),0)   AS gross,
            COALESCE(SUM(i.net_weight),0)     AS net
        FROM bills_of_lading b
        LEFT JOIN bl_items i ON b.id = i.bl_id
        GROUP BY DATE_FORMAT(b.created_at,'%Y-%m')
        ORDER BY month_sort DESC
        LIMIT 12
    ")->fetchAll();
    echo "Monthly: ✅ (" . count($r) . " rows)<br>";
} catch(Exception $e) { echo "❌ Error 12: " . $e->getMessage() . "<br>"; }

try {
    $r = $pdo->query("
        SELECT i.container_number,
               SUM(i.number_of_bags) AS bags,
               SUM(i.gross_weight)   AS gw,
               COUNT(*)              AS times_used
        FROM bl_items i
        GROUP BY i.container_number
        ORDER BY bags DESC
        LIMIT 8
    ")->fetchAll();
    echo "Top containers: ✅ (" . count($r) . " rows)<br>";
} catch(Exception $e) { echo "❌ Error 13: " . $e->getMessage() . "<br>"; }

echo "<br><strong>All checks done. Check for any ❌ above.</strong>";
?>