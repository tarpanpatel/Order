<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . "/../config/db.php";

$has_active_guest = $pdo->query("SELECT COUNT(*) FROM guests WHERE status = 'Active'")->fetchColumn() > 0;
$is_staff_role = isset($_SESSION['role']) && $_SESSION['role'] === 'Staff';
$current_page = basename($_SERVER['PHP_SELF']);

// Compile guest listings directly for the native sidebar dropdown controls
$current_active_guest = $pdo->query("SELECT * FROM guests WHERE status = 'Active' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$all_booked_guests    = $pdo->query("SELECT id, guest_name FROM guests WHERE status = 'Booked' ORDER BY checkin_date ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>POS</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time(); ?>">
    <style>
        /* ==========================================================================
           RESPONSIVE SIDEBAR NAVIGATION TOGGLE UI MECHANICS
           ========================================================================== */
        .mobile-header-strip {
            display: none;
            background: #ffffff;
            padding: 8px 16px;
            border-bottom: 1px solid #e2e8f0;
            align-items: center;
            width: 100%;
            min-height: 56px;
        }
        .hamburger-btn {
            background: var(--bg, #f8f9fa); 
            border: 1px solid #e2e8f0; 
            border-radius: 8px;
            font-size: 1.2rem; 
            padding: 6px 14px; 
            cursor: pointer; 
            margin-right: 12px;
            font-weight: bold;
            color: var(--text-main, #2d3748);
        }

        /* Desktop Sidebar Styles */
        .desktop-sidebar-title {
            font-size: 11px; 
            font-weight: 700; 
            color: var(--text-muted); 
            letter-spacing: 0.5px; 
            text-transform: uppercase;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px dashed #e2e8f0;
        }

        /* Streamlined Sidebar Form Controllers */
        .sidebar-action-card {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px;
            font-size: 12px;
            font-weight: 700;
            border-radius: 8px;
            text-decoration: none;
            width: 100%;
            box-sizing: border-box;
            border: none;
            cursor: pointer;
        }
        .sb-btn-active {
            background: #e53e3e;
            color: white;
            border: 1px solid #c53030;
        }
        .sb-btn-active:hover {
            background: #c53030;
        }
        .sb-btn-inactive {
            background: #38a169;
            color: white;
        }
        .sb-btn-inactive:hover {
            background: #2f855a;
        }

        @media (min-width: 1024px) {
            .nav-bar { 
                top: 0 !important; 
            }
            .close-drawer-btn {
                display: none !important;
            }
        }

        @media (max-width: 1023px) {
            .mobile-header-strip { display: flex; }
            
            .sidebar {
                position: fixed !important; 
                top: 0; 
                left: 0; 
                height: 100vh; 
                width: 260px;
                z-index: 9999;
                transform: translateX(-100%);
                transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
                box-shadow: 4px 0 25 rgba(0,0,0,0.08);
            }
            .sidebar.is-drawer-open { 
                transform: translateX(0) !important; 
            }
            
            .sidebar-backdrop {
                position: fixed; 
                top: 0; 
                left: 0; 
                width: 100%; 
                height: 100%;
                background: rgba(45, 55, 72, 0.3); 
                backdrop-filter: blur(2px);
                z-index: 9998; 
                display: none;
            }
            .sidebar-backdrop.is-active { display: block !important; }
        }
    </style>
</head>
<body>

<div class="mobile-header-strip">
    <button type="button" class="hamburger-btn" onclick="toggleLeftMenu(true)">☰</button>
    <strong style="font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-main);">POS Dashboard</strong>
</div>

<div class="app-container" <?= ($is_staff_role && $current_page === 'kitchen.php') ? 'style="grid-template-columns: 1fr;"' : ''; ?>>
    
    <?php if (!($is_staff_role && $current_page === 'kitchen.php')): ?>
    <div class="sidebar" id="appLeftNavigationMenu">
        
        <div class="desktop-sidebar-title">
            <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                <span>Navigation Menu</span>
                <button type="button" class="close-drawer-btn" onclick="event.stopPropagation(); toggleLeftMenu(false);" style="background: var(--bg); border: 1px solid #e2e8f0; border-radius: 50%; font-size: 1rem; cursor: pointer; font-weight: bold; color: var(--danger); width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; z-index: 10002;">✕</button>
            </div>
        </div>
        
        <nav style="display: flex; flex-direction: column; gap: 4px;">
            <?php if (isset($_SESSION["role"]) && ($_SESSION["role"] === 'Admin' || $_SESSION["role"] === 'Super Admin')): ?>
                <a href="index.php" class="nav-link <?= ($current_page === 'index.php') ? 'active' : ''; ?>">📊 Dashboard</a>
                
                <?php if (!empty($current_active_guest)): ?>
                    <div style="margin: 6px 0;">
                        <a href="billing.php" class="sidebar-action-card sb-btn-active">
                            ⏸ Checkout Billing
                        </a>
                    </div>
                <?php else: ?>
                    <div style="margin: 6px 0; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 8px;">
                        <form method="POST" action="checkin.php" style="margin: 0;">
                            <input type="hidden" name="action_sidebar_activate" value="1">
                            <select name="sidebar_guest_select" required style="width: 100%; padding: 6px; margin-bottom: 6px; border-radius: 6px; border: 1px solid #cbd5e0; font-size: 11px; background: #fff; font-family: inherit;">
                                <option value="">-- Choose Guest --</option>
                                <?php foreach ($all_booked_guests as $g): ?>
                                    <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['guest_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="sidebar-action-card sb-btn-inactive">
                                ▶ Activate Ledger
                            </button>
                        </form>
                    </div>
                <?php endif; ?>

                <a href="checkin.php" class="nav-link <?= ($current_page === 'checkin.php') ? 'active' : ''; ?>">👤 Guest Registration</a>
                <a href="billing.php" class="nav-link <?= ($current_page === 'billing.php') ? 'active' : ''; ?>">🧾 Settlements & Billing</a>
                
                <?php if ($_SESSION["role"] === 'Super Admin'): ?>
                    <a href="menu_admin.php" class="nav-link <?= ($current_page === 'menu_admin.php') ? 'active' : ''; ?>">⚙️ Menu Setup (Admin)</a>
                    <a href="materials_admin.php" class="nav-link <?= ($current_page === 'materials_admin.php') ? 'active' : ''; ?>">📦 Material Settings</a>
                <?php endif; ?>
            <?php endif; ?>
            <a href="order.php" class="nav-link <?= ($current_page === 'order.php') ? 'active' : ''; ?>">🍽️ Take Food Order</a>
            <a href="kitchen.php" class="nav-link <?= ($current_page === 'kitchen.php') ? 'active' : ''; ?>">🍳 Kitchen Orders</a>
            <a href="requisitions.php" class="nav-link <?= ($current_page === 'requisitions.php') ? 'active' : ''; ?>">📦 Material Requests</a>
            <a href="logout.php" class="nav-link" style="margin-top: 30px; color: var(--danger); border-color: transparent; background: transparent; text-align: center;">🔒 Sign Out</a>
        </nav>
    </div>
    <?php endif; ?>
    
    <div class="main-content" style="padding: 12px;">