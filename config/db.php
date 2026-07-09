<?php
// /home/apartment/artistsfarmjaipur.com/Order/config/db.php

// REMOVE all session_start() and ini_set() calls from here.
// Login.php handles the session. This file should only handle DB connection.

date_default_timezone_set('Asia/Kolkata');

$db_host = "localhost";
$db_user = "apartment_blue";
$db_pass = "tPatel13@";
$db_name = "apartment_blue";

try {
    $pdo = new PDO("mysql:host={$db_host};dbname={$db_name};charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    $pdo->exec("SET time_zone = '+05:30';");
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

try {
    /* 🔑 FIXED: Removed the stray closing brace right after utf8mb4 */
    $pdo = new PDO("mysql:host={$db_host};dbname={$db_name};charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, 
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC 
    ]);
    
    // Force MySQL session synchronization to Indian Standard Time
    $pdo->exec("SET time_zone = '+05:30';"); 

} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage()); 
}

function logAction($userId, $actionText) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?, ?)");
    $stmt->execute([$userId, $actionText]);
}

function requireSuperAdmin() {
    if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "Super Admin") {
        header("Location: index.php?error=unauthorized");
        exit;
    }
}

function logUserAction($pdo, $action_description) {
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;
    
    $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, timestamp) VALUES (?, ?, NOW())");
    $stmt->execute([$user_id, $action_description]);
}
function check_page_access($pdo, $user_role, $current_page_url) {
    if ($user_role === 'Super Admin') return true;

    $stmt = $pdo->prepare("
        SELECT 1 FROM sys_menu m
        JOIN sys_role_menu rm ON m.id = rm.menu_id
        WHERE rm.role_name = ? AND m.url = ?
    ");
    $stmt->execute([$user_role, basename($current_page_url)]);
    return (bool) $stmt->fetchColumn();
}
?>