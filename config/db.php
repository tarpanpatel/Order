<?php
// /home/apartment/artistsfarmjaipur.com/Order/config/db.php

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

// 🔐 Centralized Permission Helper Functions
if (!function_exists('check_page_access')) {
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
}

if (!function_exists('logAction')) {
    function logAction($userId, $actionText) {
        global $pdo;
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?, ?)");
        $stmt->execute([$userId, $actionText]);
    }
}
?>