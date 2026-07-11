<?php
// /home/apartment/artistsfarmjaipur.com/Order/config/db.php

// 1. CENTRALIZED 1-YEAR ABSOLUTE SESSION PERSISTENCE (SAFE FOR CPANEL OVERRIDES)
$sessionPath = __DIR__ . '/../_sessions';
if (!is_dir($sessionPath)) { 
    mkdir($sessionPath, 0755, true); 
}

ini_set('session.save_path', $sessionPath);
ini_set('session.gc_maxlifetime', 31536000); // 1 Year on server
session_set_cookie_params([
    'lifetime' => 31536000, // 1 Year in browser cookie
    'path' => '/Order/',    // Locks the cookie strictly to your POS directory scope
    'httponly' => true,
    'samesite' => 'Lax'
]);
ini_set('session.cookie_lifetime', 31536000);
ini_set('session.use_only_cookies', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. EXISTING DATABASE CONNECTION CONFIGURATION
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

// Keep your existing helper functions below (check_page_access, logAction, etc.)
function check_page_access($pdo) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'Super Admin') {
        return true;
    }
    $current_page = basename($_SERVER['PHP_SELF']);
    $role = $_SESSION['role'] ?? 'Staff';

    $stmt = $pdo->prepare("
        SELECT 1 FROM sys_menu m
        JOIN sys_role_menu rm ON m.id = rm.menu_id
        WHERE rm.role_name = ? AND m.url = ?
    ");
    $stmt->execute([$role, $current_page]);
    return (bool) $stmt->fetchColumn();
}
?>