<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$db_host = "localhost"; 
$db_user = "apartment_blue"; 
$db_pass = "tPatel13@"; 
$db_name = "apartment_blue";
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) { die("Database Connection Error: " . $e->getMessage()); }

function logAction($userId, $actionText) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?, ?)");
    $stmt->execute([$userId, $actionText]);
}
?>