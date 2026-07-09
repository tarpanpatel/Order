<?php
// /home/apartment/artistsfarmjaipur.com/Order/includes/left_menu.php

$current_page = basename($_SERVER['PHP_SELF']);
$user_role = $_SESSION['role'] ?? 'Staff';
$is_super_admin = ($user_role === 'Super Admin');

// Fetch Dynamic Menus based on Role Access
if ($is_super_admin) {
    // Super Admin gets everything
    $menuStmt = $pdo->query("SELECT * FROM sys_menu ORDER BY parent_id ASC, sort_order ASC");
    $allowed_menus = $menuStmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Other roles check the access mapping table
    $menuStmt = $pdo->prepare("
        SELECT m.* FROM sys_menu m
        JOIN sys_role_menu rm ON m.id = rm.menu_id
        WHERE rm.role_name = ?
        ORDER BY m.parent_id ASC, m.sort_order ASC
    ");
    $menuStmt->execute([$user_role]);
    $allowed_menus = $menuStmt->fetchAll(PDO::FETCH_ASSOC);
}

// Organize into Main items and Sub-items
$main_menus = [];
$sub_menus = [];
foreach ($allowed_menus as $menu) {
    if ($menu['parent_id'] == 0) {
        $main_menus[$menu['id']] = $menu;
    } else {
        $sub_menus[$menu['parent_id']][] = $menu;
    }
}
?>

<div class="sidebar" id="appLeftNavigationMenu" style="position: fixed; top: 0; left: 0; height: 100vh; width: 280px; background: #ffffff; box-shadow: 4px 0 25px rgba(0,0,0,0.15); display: flex; flex-direction: column; z-index: 10001; box-sizing: border-box; padding: 15px 16px; overflow-y: auto; -webkit-overflow-scrolling: touch;">
    
    <div style="position: absolute; top: 12px; right: -42px; z-index: 10002; margin: 0; padding: 0;">
        <button type="button" class="close-drawer-btn" onclick="event.stopPropagation(); toggleLeftMenu(false);" style="background: #ffffff; border: 1px solid #cbd5e0; border-radius: 50%; font-size: 1.1rem; cursor: pointer; font-weight: bold; color: #e53e3e; width: 34px; height: 34px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">✕</button>
    </div>
    
    <nav style="display: flex; flex-direction: column; gap: 4px; width: 100%; padding-top: 5px;">
        
        <?php
// 1. Check if there is an active guest ledger in the database
$active_guest_check = $pdo->query("SELECT id FROM guests WHERE status = 'Active' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
?>

<nav style="display: flex; flex-direction: column; gap: 4px; width: 100%; padding-top: 5px;">
    <a href="index.php" class="nav-link">📊 Dashboard</a>

    <?php if ($active_guest_check): ?>
        <div style="margin: 4px 0; background: #fff5f5; border: 1px solid #feb2b2; border-radius: 8px; padding: 8px;">
            <a href="billing.php" class="sidebar-action-card" style="background: #e53e3e; color: white; border: none; justify-content: center; display: flex; align-items: center; text-decoration: none;">
                🛑 Guest Checkout
            </a>
        </div>
    <?php else: ?>
        <div style="margin: 4px 0; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 8px;">
            <form method="POST" action="checkin.php" style="margin: 0;">
                <input type="hidden" name="action_sidebar_activate" value="1">
                <select name="sidebar_guest_select" required style="width: 100%; padding: 6px; margin-bottom: 6px; border-radius: 6px; border: 1px solid #cbd5e0; font-size: 11px; background: #fff; color: #1e293b;">
                    <option value="">-- Choose Guest --</option>
                    <?php
                    $activeGuests = $pdo->query("SELECT id, guest_name, phone_number FROM guests WHERE status = 'Checked In' OR status = 'Active' ORDER BY checkin_date DESC")->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($activeGuests as $g): ?>
                        <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['guest_name']) ?> (<?= substr($g['phone_number'], -4) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="sidebar-action-card sb-btn-inactive">▶ Activate Ledger</button>
            </form>
        </div>
    <?php endif; ?>

 
 
    </nav>
</div>

<script>
function toggleAdminSubMenu() {
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