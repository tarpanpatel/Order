<?php
// /home/apartment/artistsfarmjaipur.com/Order/includes/left_menu.php
require_once __DIR__ . "/../config/db.php";

$current_page = basename($_SERVER['PHP_SELF']);
$user_logged_role = $_SESSION['role'] ?? 'Staff';

// Fetch all menu items
if ($user_logged_role === 'Super Admin') {
    $menu_items = $pdo->query("SELECT * FROM sys_menu ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $menu_items = $pdo->prepare("
        SELECT m.* FROM sys_menu m
        JOIN sys_role_menu rm ON m.id = rm.menu_id
        WHERE rm.role_name = ?
        ORDER BY m.sort_order ASC
    ");
    $menu_items->execute([$user_logged_role]);
    $menu_items = $menu_items->fetchAll(PDO::FETCH_ASSOC);
}

// Helper to group by parent
$nested = [];
foreach ($menu_items as $item) {
    $nested[$item['parent_id']][] = $item;
}

// Function to render items recursively
function renderSidebar($items, $parentId, $current_page) {
    if (!isset($items[$parentId])) return;
    
    foreach ($items[$parentId] as $menu) {
        $has_children = isset($items[$menu['id']]);
        $is_active = ($current_page === $menu['url']) ? 'active' : '';
        
        // If it has children, render as a dropdown
        if ($has_children) {
            echo '<a href="javascript:void(0);" onclick="this.nextElementSibling.style.display = (this.nextElementSibling.style.display === \'flex\') ? \'none\' : \'flex\'" class="'.$is_active.'">';
            echo '<span>' . htmlspecialchars($menu['title']) . ' ▾</span></a>';
            echo '<div style="display:none; flex-direction:column; padding-left:15px;">';
            renderSidebar($items, $menu['id'], $current_page);
            echo '</div>';
        } else {
            // Standard link
            echo '<a href="'.htmlspecialchars($menu['url']).'" class="'.$is_active.'">';
            echo '<span>' . htmlspecialchars($menu['title']) . '</span></a>';
        }
    }
}
?>

<aside class="sidebar" id="sidebarNavDrawer">
    <div class="desktop-sidebar-title">POS Dashboard</div>
    <nav>
        <?php renderSidebar($nested, 0, $current_page); ?>
        
        <a href="logout.php" style="color: #ff5252 !important; border-color: #ff5252 !important; margin-top: 20px;">
            <span>[➔] Sign Out</span>
        </a>
    </nav>
</aside>