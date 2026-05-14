<?php
$host     = 'localhost';
$dbname   = 'bill_of_lading';
$db_user  = 'root';   // Change to your MySQL username
$db_pass  = 'schlumberger';       // Change to your MySQL password

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die(json_encode(['error' => 'DB Connection failed: ' . $e->getMessage()]));
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>