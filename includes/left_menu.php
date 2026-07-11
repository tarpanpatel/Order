<?php
// /home/apartment/artistsfarmjaipur.com/Order/includes/left_menu.php

// Load database environment safely
require_once __DIR__ . "/../config/db.php";

$current_active_script = basename($_SERVER['PHP_SELF']);
$user_logged_role = $_SESSION['role'] ?? 'Staff';

// Fetch allowed visibility parameters dynamically based on user role permissions
if ($user_logged_role === 'Super Admin') {
    // Super Admins automatically see all records sorted by index order
    $menu_query = $pdo->query("
        SELECT * FROM sys_menu 
        ORDER BY sort_order ASC
    ");
    $active_menu_items = $menu_query->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Staff/Chefs only see items explicitly mapped to their role rows
    $menu_stmt = $pdo->prepare("
        SELECT m.* FROM sys_menu m
        JOIN sys_role_menu rm ON m.id = rm.menu_id
        WHERE rm.role_name = ?
        ORDER BY m.sort_order ASC
    ");
    $menu_stmt->execute([$user_logged_role]);
    $active_menu_items = $menu_stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<div class="sidebar-navigation-container" style="background: #ffffff; border-right: 1px solid #e2e8f0; height: 100vh; padding: 20px 10px; width: 260px; box-sizing: border-box;">
    <!-- Brand Context -->
    <div style="padding: 10px; margin-bottom: 20px; border-bottom: 1px dashed #cbd5e0; text-align: left;">
        <strong style="color: #0f172a; font-size: 16px; font-weight: 800; letter-spacing: 0.5px;">ARTISTS FARM</strong>
        <span style="display: block; font-size: 11px; color: #64748b; font-weight: 600; margin-top: 2px;">Role: <?= htmlspecialchars($user_logged_role) ?></span>
    </div>

    <!-- Dynamic Link Loop Renderer -->
    <nav style="display: flex; flex-direction: column; gap: 4px;">
        <?php foreach ($active_menu_items as $menu): 
            $is_current = ($current_active_script === $menu['url']) ? true : false;
        ?>
            <a href="<?= htmlspecialchars($menu['url']) ?>" 
               style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; font-size: 13px; font-weight: 700; border-radius: 8px; text-decoration: none; transition: all 0.2s;
                      background: <?= $is_current ? '#00b0ff' : 'transparent' ?>; 
                      color: <?= $is_current ? '#ffffff' : '#475569' ?>;"
               onmouseover="if(!<?= $is_current ? 'true' : 'false' ?>) this.style.backgroundColor='#f1f5f9';"
               onmouseout="if(!<?= $is_current ? 'true' : 'false' ?>) this.style.backgroundColor='transparent';">
                
                <i class="<?= htmlspecialchars($menu['icon'] ?? 'fa-solid fa-link') ?>" style="font-size: 14px; width: 20px; text-align: center;"></i>
                <span><?= htmlspecialchars($menu['title']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <!-- Logout Anchor -->
    <div style="position: absolute; bottom: 20px; left: 10px; right: 10px;">
        <a href="logout.php" style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; font-size: 13px; font-weight: 700; color: #ef4444; text-decoration: none; border-radius: 8px;" onmouseover="this.style.backgroundColor='#fef2f2';" onmouseout="this.style.backgroundColor='transparent';">
            <i class="fa-solid fa-right-from-bracket" style="font-size: 14px; width: 20px; text-align: center;"></i>
            <span>Sign Out Terminal</span>
        </a>
    </div>
</div>