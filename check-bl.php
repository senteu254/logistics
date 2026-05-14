<?php
// check-bl.php — checks if a BL number is already taken
require_once 'config/database.php';

header('Content-Type: application/json');

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['available' => false, 'error' => 'Unauthorized']);
    exit();
}

$bl_number = strtoupper(trim($_GET['bl_number'] ?? ''));

if (empty($bl_number)) {
    echo json_encode(['available' => false]);
    exit();
}

$stmt = $pdo->prepare("SELECT id FROM bills_of_lading WHERE bl_number = ?");
$stmt->execute([$bl_number]);
$exists = $stmt->fetch();

echo json_encode([
    'available' => !$exists,
    'bl_number' => $bl_number,
]);
exit();
?>