<?php
// Report all PHP errors
error_reporting(E_ALL);

// Force errors to be displayed on the screen
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
// /home/apartment/artistsfarmjaipur.com/Order/includes/header.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . "/../config/db.php";
if (!isset($_SESSION["role"]) || !check_page_access($pdo)) {
    die("Access Denied: You do not have permission to access this area.");
}
$todayString = date('Y-m-d');
$has_active_guest = $pdo->query("SELECT COUNT(*) FROM guests WHERE status = 'Active'")->fetchColumn() > 0;
$is_staff_role = isset($_SESSION['role']) && $_SESSION['role'] === 'Staff';
$current_page = basename($_SERVER['PHP_SELF']);

// Compile guest listings directly for the dropdown controllers
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
    <style>
      
    </style>
    
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