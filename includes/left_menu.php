<?php
// /home/apartment/artistsfarmjaipur.com/Order/includes/left_menu.php

// Ensure database connection and session parameters are initialized safely
require_once __DIR__ . "/../config/db.php";

$current_page = basename($_SERVER['PHP_SELF']);
$user_logged_role = $_SESSION['role'] ?? 'Staff';

// Fetch allowed items dynamically based on database visibility rows
if ($user_logged_role === 'Super Admin') {
    $menu_query = $pdo->query("SELECT * FROM sys_menu ORDER BY sort_order ASC");
    $active_menu_items = $menu_query->fetchAll(PDO::FETCH_ASSOC);
} else {
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

<!-- Original styling class selectors wrapper retained exactly -->
<div class="left-menu-panel" id="leftMenuPanel">
    <div class="menu-items-scroll-box">
        <?php foreach ($active_menu_items as $menu): 
            // Handle dynamic link generation matching current view status
            $is_current = ($current_page === $menu['url']) ? 'active' : '';
            
            // Default anchor target mapping logic
            $href_target = htmlspecialchars($menu['url']);
            if (empty($menu['url']) || $menu['url'] === '#') {
                $href_target = 'javascript:void(0);';
            }
        ?>
            <a href="<?= $href_target ?>" class="menu-item-btn <?= $is_current ?>">
                <!-- Keep your exact system icons if populated, fallback safely if empty -->
                <i class="<?= htmlspecialchars($menu['icon'] ?: 'fa-solid fa-link') ?>"></i>
                <span><?= htmlspecialchars($menu['title']) ?></span>
            </a>
        <?php endforeach; ?>

        <!-- Retain original protected sign out anchor markup exactly -->
        <a href="logout.php" class="menu-item-btn sign-out-btn" style="margin-top: 20px;">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span>Sign Out</span>
        </a>
    </div>
</div>