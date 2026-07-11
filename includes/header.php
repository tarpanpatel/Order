<?php
// /home/apartment/artistsfarmjaipur.com/Order/includes/header.php
error_reporting(E_ALL);
ini_set('display_errors', '1');
 

// 1. CONFIGURE WORKSPACE LIFETIMES BEFORE ANY SESSION IS ACTIVE
$sessionPath = __DIR__ . '/../_sessions';
if (!is_dir($sessionPath)) { 
    mkdir($sessionPath, 0755, true); 
}

// If session parameters leak out, intercept them cleanly
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
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

// 2. NOW SAFELY LOAD THE REQUISITE DATABASE LAYER
require_once __DIR__ . "/../config/db.php";

$current_page = basename($_SERVER['PHP_SELF']);

// 3. PERSISTENT COOKIE REMEMBER TOKEN ENGINE (Bypasses Server Logouts)
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

// 4. AUTHENTICATION PROTECTION GATEWAY (With Target Appending)
if (!isset($_SESSION["user_id"]) || !isset($_SESSION["role"])) {
    $redirect_target = $current_page;
    if (!empty($_SERVER['QUERY_STRING'])) {
        $redirect_target .= '?' . $_SERVER['QUERY_STRING'];
    }
    header("Location: login.php?next=" . urlencode($redirect_target));
    exit;
}

// 5. ROLE ACCESS PERMISSION CHECKER
if (!check_page_access($pdo)) {
    header("Location: login.php?error=permissions_revoked");
    exit;
}

$todayString = date('Y-m-d');
$has_active_guest = $pdo->query("SELECT COUNT(*) FROM guests WHERE status = 'Active'")->fetchColumn() > 0;
$is_staff_role = isset($_SESSION['role']) && $_SESSION['role'] === 'Staff';

$current_active_guest = $pdo->query("SELECT * FROM guests WHERE status = 'Active' LIMIT 1")->fetch(PDO::FETCH_ASSOC);

$todaysStmt = $pdo->prepare("
    SELECT id, CONCAT(' (', RIGHT(phone_number, 4), ')') as guest_name 
    FROM guests 
    WHERE status = 'Booked' AND :today >= checkin_date AND :today2 < checkout_date
    ORDER BY id ASC
");
$todaysStmt->execute([':today' => $todayString, ':today2' => $todayString]);
$all_booked_guests = $todaysStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>POS System</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div id="globalSystemLoaderScreen" style="
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(4px);
    z-index: 999999;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    transition: opacity 0.2s ease-in-out;
    pointer-events: all;
">
    <div style="
        width: 50px;
        height: 50px;
        border: 5px solid #edf2f7;
        border-top: 5px solid #00b0ff;
        border-radius: 50%;
        animation: spinSystemLoader 0.8s linear infinite;
    "></div>
    <p style="
        margin-top: 15px;
        font-family: sans-serif;
        font-size: 14px;
        font-weight: 700;
        color: #2d3748;
        letter-spacing: 0.5px;
    ">Processing Request...</p>
</div>

<style>
@keyframes spinSystemLoader {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>
<div class="mobile-header-strip">
    <button type="button" class="hamburger-btn" onclick="toggleLeftMenu(true)">☰</button>
    <strong style="font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">POS Dashboard</strong>
</div>

<div class="app-container" <?php echo ($is_staff_role && $current_page === 'kitchen.php') ? 'style="grid-template-columns: 1fr;"' : 'style=""'; ?>>
    
    <?php 
    if (!($is_staff_role && $current_page === 'kitchen.php')) {
        include __DIR__ . "/left_menu.php";
    } 
    ?>
    
    <div class="main-content">