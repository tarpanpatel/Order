<?php
// /home/apartment/artistsfarmjaipur.com/Order/config/db.php

// ==========================================================================
// 🔒 SAFE LONG-LIVED PRIVATE SESSION MANAGER (PREVENTS AUTO-LOGOUTS)
// ==========================================================================
$sessionPath = __DIR__ . '/../_sessions';
if (!is_dir($sessionPath)) {
    mkdir($sessionPath, 0700, true);
}

// Enforce private directory path and expand lifetime parameters to 14 days (1,209,600 seconds)
ini_set('session.save_path', $sessionPath);
ini_set('session.gc_maxlifetime', 1209600);
ini_set('session.cookie_lifetime', 1209600);
ini_set('session.use_only_cookies', 1);

if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

// ==========================================================================
// TEMPORARY DEVELOPMENT DEBUGGING ENGINE
// ==========================================================================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('Asia/Kolkata');

$db_host = "localhost";   
$db_user = "apartment_blue";   
$db_pass = "tPatel13@";   
$db_name = "apartment_blue";  

try {
    $pdo = new PDO("mysql:host={$db_host};dbname={$db_name};charset=utf8mb4}", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, 
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC 
    ]);
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
?>