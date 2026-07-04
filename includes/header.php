<?php
// /home/apartment/artistsfarmjaipur.com/Order/includes/header.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . "/../config/db.php";

$todayString = date('Y-m-d');

$has_active_guest = $pdo->query("SELECT COUNT(*) FROM guests WHERE status = 'Active'")->fetchColumn() > 0;
$is_staff_role = isset($_SESSION['role']) && $_SESSION['role'] === 'Staff';
$current_page = basename($_SERVER['PHP_SELF']);

// Compile guest listings directly for the native sidebar dropdown controls
$current_active_guest = $pdo->query("SELECT * FROM guests WHERE status = 'Active' LIMIT 1")->fetch(PDO::FETCH_ASSOC);

// FIXED DROPDOWN FILTER: Now dynamically fetches ONLY bookings active or arriving TODAY
$todaysStmt = $pdo->prepare("
    SELECT id, CONCAT('📱 (', RIGHT(phone_number, 4), ')') as guest_label 
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
    <title>Property Management System</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #06b6d4;
            --primary-hover: #0891b2;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --dark: #0f172a;
            --light: #f8fafc;
            --border: #e2e8f0;
        }
        body { margin: 0; background: var(--light); font-family: 'Segoe UI', Helvetica, sans-serif; display: flex; min-height: 100vh; }
        .sidebar { width: 260px; background: var(--dark); color: white; display: flex; flex-direction: column; padding: 20px 15px; box-sizing: border-box; position: fixed; height: 100vh; left: 0; top: 0; z-index: 1000; }
        .sidebar-brand { font-size: 16px; font-weight: 700; color: white; display: flex; align-items: center; gap: 10px; margin-bottom: 25px; padding-left: 10px; text-transform: uppercase; letter-spacing: 0.5px; }
        .nav-menu { display: flex; flex-direction: column; gap: 4px; flex-grow: 1; }
        .nav-link { display: flex; align-items: center; gap: 12px; padding: 12px 15px; color: #94a3b8; text-decoration: none; font-size: 14px; font-weight: 600; border-radius: 8px; transition: all 0.2s ease; border-left: 3px solid transparent; }
        .nav-link:hover { color: white; background: rgba(255,255,255,0.05); }
        .nav-link.active { color: white; background: rgba(6, 182, 212, 0.1); border-left-color: var(--primary); }
        .main-content { flex-grow: 1; margin-left: 260px; padding: 24px; box-sizing: border-box; min-height: 100vh; width: calc(100% - 260px); }
        
        .sidebar-context-card { background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); border-radius: 10px; padding: 12px; margin-bottom: 20px; }
        .context-label { font-size: 10px; text-transform: uppercase; font-weight: 700; color: #64748b; letter-spacing: 0.5px; display: block; margin-bottom: 4px; }
        .context-value { font-size: 13px; font-weight: 600; color: #e2e8f0; display: flex; align-items: center; gap: 6px; }
        
        .sidebar-select { width: 100%; padding: 8px 10px; font-size: 13px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.15); background: #1e293b; color: white; outline: none; margin-top: 8px; }
        .sidebar-btn { width: 100%; margin-top: 8px; padding: 8px; background: var(--success); color: white; border: none; font-weight: 600; border-radius: 6px; font-size: 13px; cursor: pointer; transition: background 0.2s; }
        .sidebar-btn:hover { background: #059669; }
        
        .admin-submenu { background: rgba(0,0,0,0.15); border-radius: 8px; margin-top: 4px; display: none; flex-direction: column; gap: 2px; padding: 4px; }
        .admin-sub-link { padding: 8px 15px 8px 40px; font-size: 13px; color: #94a3b8; text-decoration: none; border-radius: 6px; font-weight: 500; }
        .admin-sub-link:hover, .admin-sub-link.active { color: white; background: rgba(255,255,255,0.05); }
        .admin-sub-link.active { color: var(--primary); font-weight: 600; }
    </style>
</head>
<body>

    <?php if ($current_page !== 'login.php'): ?>
    <div class="sidebar">
        <div class="sidebar-brand">
            <i class="fa-solid fa-wheat-awn" style="color: var(--primary);"></i> Operations Hub
        </div>

        <div class="sidebar-context-card">
            <span class="context-label">Active Terminal Profile</span>
            <div class="context-value">
                <?php if ($current_active_guest): ?>
                    <i class="fa-solid fa-circle-check" style="color: var(--success); font-size: 11px;"></i> 
                    📱 (<?= substr($current_active_guest['phone_number'], -4) ?>)
                <?php else: ?>
                    <i class="fa-solid fa-circle-minus" style="color: #64748b; font-size: 11px;"></i> 
                    <span style="color: #64748b;">No Active Session</span>
                <?php endif; ?>
            </div>

            <?php if (!$is_staff_role): ?>
            <form method="POST" action="checkin.php" style="margin: 0;">
                <input type="hidden" name="action_sidebar_activate" value="1">
                <select name="sidebar_guest_select" class="sidebar-select">
                    <?php if (empty($all_booked_guests)): ?>
                        <option value="0">No bookings arriving today</option>
                    <?php else: ?>
                        <option value="0">Select Today's Arrival</option>
                        <?php foreach ($all_booked_guests as $bg): ?>
                            <option value="<?= $bg['id'] ?>"><?= htmlspecialchars($bg['guest_label']) ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
                <?php if (!empty($all_booked_guests)): ?>
                    <button type="submit" class="sidebar-btn">Activate Session</button>
                <?php endif; ?>
            </form>
            <?php endif; ?>
        </div>

        <nav class="nav-menu">
            <a href="order.php" class="nav-link <?= $current_page === 'order.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-utensils"></i> POS Order Terminal
            </a>
            
            <a href="checkin.php" class="nav-link <?= $current_page === 'checkin.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-calendar-days"></i> Calendar Registration
            </a>

            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin'): ?>
                <div style="margin-top: 10px;">
                    <a href="#" class="nav-link" onclick="toggleAdminPanelSubMenu(event)" style="justify-content: space-between;">
                        <span><i class="fa-solid fa-sliders"></i> Administrative Tools</span>
                        <i class="fa-solid fa-chevron-right" id="adminMenuChevron" style="font-size: 11px; transition: transform 0.2s;"></i>
                    </a>
                    <div class="admin-submenu" id="adminSubMenuContent">
                        <a href="dashboard_analytics.php" class="admin-sub-link <?= $current_page === 'dashboard_analytics.php' ? 'active' : '' ?>">📈 Business Ledger Sheets</a>
                        <a href="kitchen_inventory.php" class="admin-sub-link <?= $current_page === 'kitchen_inventory.php' ? 'active' : '' ?>">🍳 Kitchen Sourcing Sheet</a>
                        <a href="farm_expenses_manager.php" class="admin-sub-link <?= $current_page === 'farm_expenses_manager.php' ? 'active' : '' ?>">🚜 General Farm Expenses</a>
                    </div>
                </div>

                <script>
                function toggleAdminPanelSubMenu(e) {
                    if(e) e.preventDefault();
                    const content = document.getElementById("adminSubMenuContent");
                    const chevron = document.getElementById("adminMenuChevron");
                    if (!content || !chevron) return;
                    
                    if (content.style.display === "none" || content.style.display === "") {
                        content.style.display = "flex";
                        chevron.style.transform = "rotate(90deg)";
                        localStorage.setItem("adminPanelExpanded", "true");
                    } else {
                        content.style.display = "none";
                        chevron.style.transform = "rotate(0deg)";
                        localStorage.setItem("adminPanelExpanded", "false");
                    }
                }

                document.addEventListener("DOMContentLoaded", () => {
                    if (localStorage.getItem("adminPanelExpanded") === "true") {
                        const content = document.getElementById("adminSubMenuContent");
                        const chevron = document.getElementById("adminMenuChevron");
                        if (content && chevron) {
                            content.style.display = "flex";
                            chevron.style.transform = "rotate(90deg)";
                        }
                    }
                });
                </script>
            <?php endif; ?>

            <a href="logout.php" class="nav-link" style="margin-top: auto; color: var(--danger); font-weight: 700;">
                <i class="fa-solid fa-lock"></i> Sign Out
            </a>
        </nav>
    </div>
    <?php endif; ?>
    
    <div class="main-content">