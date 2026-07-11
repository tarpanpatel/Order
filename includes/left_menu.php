<?php
// /home/apartment/artistsfarmjaipur.com/Order/includes/left_menu.php

require_once __DIR__ . "/../config/db.php";

$current_page = basename($_SERVER['PHP_SELF']);
$user_logged_role = $_SESSION['role'] ?? 'Staff';

// Fetch items based on roles
if ($user_logged_role === 'Super Admin') {
    $menu_query = $pdo->query("SELECT * FROM sys_menu ORDER BY sort_order ASC");
    $all_menu_items = $menu_query->fetchAll(PDO::FETCH_ASSOC);
} else {
    $menu_stmt = $pdo->prepare("
        SELECT m.* FROM sys_menu m
        JOIN sys_role_menu rm ON m.id = rm.menu_id
        WHERE rm.role_name = ?
        ORDER BY m.sort_order ASC
    ");
    $menu_stmt->execute([$user_logged_role]);
    $all_menu_items = $menu_stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** 
 * PLACE THE RECURSIVE FUNCTION HERE
 * I have updated it slightly to include active states 
 * and your specific sidebar CSS classes.
 */
function renderMenu($items, $parentId = 0, $current_page) {
    foreach ($items as $item) {
        if ($item['parent_id'] == $parentId) {
            $is_active = ($current_page === $item['url']) ? 'active' : '';
            echo "<a href='" . htmlspecialchars($item['url']) . "' class='menu-item-btn {$is_active}'>";
            echo "<i class='" . htmlspecialchars($item['icon'] ?: 'fa-solid fa-link') . "'></i>";
            echo "<span>" . htmlspecialchars($item['title']) . "</span>";
            echo "</a>";
            
            // Check for children and render them recursively
            renderMenu($items, $item['id'], $current_page);
        }
    }
}
?>

<aside class="sidebar" id="sidebarNavDrawer">
    <div class="desktop-sidebar-title">POS Dashboard</div>
    
    <nav>
        <!-- CALL THE FUNCTION HERE TO RENDER THE FULL HIERARCHY -->
        <?php renderMenu($all_menu_items, 0, $current_page); ?>

        <a href="logout.php" style="color: #ff5252 !important; border-color: #ff5252 !important; margin-top: 20px;">
            <span>[➔] Sign Out Terminal</span>
        </a>
    </nav>
</aside>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/nestedSortable/2.0.0/jquery.mjs.nestedSortable.min.js"></script>