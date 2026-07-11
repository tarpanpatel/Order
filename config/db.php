<?php
// /home/apartment/artistsfarmjaipur.com/Order/config/db.php

// 1. STANDARD SESSION SETUP
$sessionPath = __DIR__ . '/../_sessions';
if (!is_dir($sessionPath)) { 
    mkdir($sessionPath, 0755, true); 
}

ini_set('session.save_path', $sessionPath);
ini_set('session.gc_maxlifetime', 31536000); 
session_set_cookie_params([
    'lifetime' => 31536000, 
    'path' => '/',    
    'httponly' => true,
    'samesite' => 'Lax'
]);
ini_set('session.cookie_lifetime', 31536000);
ini_set('session.use_only_cookies', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. DATABASE CONNECTION
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

// 3. PERSISTENT TOKEN AUTO-LOGIN ENGINE (Bypasses cPanel Session Drops)
if (!isset($_SESSION['user_id']) && isset($_COOKIE['pos_remember_token'])) {
    list($cookie_user_id, $cookie_token) = explode(':', $_COOKIE['pos_remember_token']);
    $cookie_user_id = intval($cookie_user_id);
    
    $token_stmt = $pdo->prepare("SELECT * FROM user_tokens WHERE user_id = ? AND expires_at > NOW()");
    $token_stmt->execute([$cookie_user_id]);
    $tokens = $token_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($tokens as $t) {
        if (hash_equals($t['token_hash'], hash('sha256', $cookie_token))) {
            $user_stmt = $pdo->prepare("SELECT id, username, role FROM users WHERE id = ?");
            $user_stmt->execute([$cookie_user_id]);
            $user_row = $user_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user_row) {
                $_SESSION["user_id"]   = $user_row['id'];
                $_SESSION["username"]  = $user_row['username'];
                $_SESSION["role"]      = $user_row['role'];
                $_SESSION["order_authenticated"] = true;
            }
            break;
        }
    }
}

// 4. HELPER FUNCTIONS
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

function logAction($userId, $actionText) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?, ?)");
    $stmt->execute([$userId, $actionText]);
}
?>