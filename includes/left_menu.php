<?php
// /home/apartment/artistsfarmjaipur.com/Order/includes/left_menu.php
require_once __DIR__ . "/../config/db.php";

$current_page = basename($_SERVER['PHP_SELF']);
$user_logged_role = $_SESSION['role'] ?? 'Staff';

// 1. Fetch authorized menu items
if ($user_logged_role === 'Super Admin') {
    $menu_items = $pdo->query("SELECT * FROM sys_menu ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $menu_stmt = $pdo->prepare("SELECT m.* FROM sys_menu m JOIN sys_role_menu rm ON m.id = rm.menu_id WHERE rm.role_name = ? ORDER BY m.sort_order ASC");
    $menu_stmt->execute([$user_logged_role]);
    $menu_items = $menu_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 2. Group items into a hierarchy
$nested = [];
foreach ($menu_items as $item) {
    $nested[$item['parent_id']][] = $item;
}

// 3. Render function matching your specific CSS structure
function renderSidebar($items, $parentId, $current_page) {
    if (!isset($items[$parentId])) return;
    
    foreach ($items[$parentId] as $menu) {
        $has_children = isset($items[$menu['id']]);
        $is_active = ($current_page === $menu['url']) ? 'active' : '';
        
        if ($has_children) {
            // Dropdown Parent
            echo '<a href="javascript:void(0);" onclick="toggleSubMenu(this)" class="menu-item-dropdown">';
            echo '<span>' . htmlspecialchars($menu['title']) . ' ▾</span></a>';
            // Wrapper matches style.css #adminSubMenuContent
            echo '<div id="adminSubMenuContent" style="display: none; flex-direction:column; gap: 4px; padding-left: 15px; margin-top: 4px;">';
            renderSidebar($items, $menu['id'], $current_page);
            echo '</div>';
        } else {
            // Standard Link
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
            <span>[➔] Sign Out Terminal</span>
        </a>
    </nav>
</aside>

<script>
function toggleSubMenu(btn) {
    const subMenu = btn.nextElementSibling;
    if (subMenu.style.display === "none" || subMenu.style.display === "") {
        subMenu.style.display = "flex";
    } else {
        subMenu.style.display = "none";
    }
}
</script>