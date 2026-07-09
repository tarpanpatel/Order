<?php
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
        html, body {
            max-width: 100vw !important;
            width: 100% !important;
            overflow-x: hidden !important;
            box-sizing: border-box !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        .mobile-header-strip { display: none; background: #ffffff; padding: 8px 16px; border-bottom: 1px solid #e2e8f0; align-items: center; width: 100%; min-height: 56px; box-sizing: border-box; }
        .hamburger-btn { background: #f8f9fa; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 1.2rem; padding: 6px 14px; cursor: pointer; margin-right: 12px; font-weight: bold; color: #2d3748; }
        .desktop-sidebar-title { font-size: 11px; font-weight: 700; color: #718096; letter-spacing: 0.5px; text-transform: uppercase; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px dashed #e2e8f0; }
        .sidebar-action-card { display: flex; align-items: center; justify-content: center; gap: 8px; padding: 10px; font-size: 12px; font-weight: 700; border-radius: 8px; text-decoration: none; width: 100%; box-sizing: border-box; border: none; cursor: pointer; }
        .sb-btn-active { background: #e53e3e; color: white; border: 1px solid #c53030; }
        .sb-btn-inactive { background: #38a169; color: white; }
        
        @media (min-width: 1024px) { 
            .nav-bar { top: 0 !important; } 
            .close-drawer-btn { display: none !important; } 
        }
        @media (max-width: 1023px) {
            .mobile-header-strip { display: flex !important; width: 100% !important; box-sizing: border-box !important; }
            .sidebar { position: fixed !important; top: 0; left: 0; height: 100vh; width: 260px; z-index: 9999; transform: translateX(-100%); transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1); box-shadow: 4px 0 25px rgba(0,0,0,0.08); }
            .sidebar.is-drawer-open { transform: translateX(0) !important; }
        }
    </style>
</head>
<body>

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