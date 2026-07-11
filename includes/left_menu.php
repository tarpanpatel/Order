<?php
// /home/apartment/artistsfarmjaipur.com/Order/includes/left_menu.php

require_once __DIR__ . "/../config/db.php";

$current_page = basename($_SERVER['PHP_SELF']);
$user_logged_role = $_SESSION['role'] ?? 'Staff';

// Fetch authorized menu records matching specific role scopes
if ($user_logged_role === 'Super Admin') {
    $menu_query = $pdo->query("SELECT * FROM sys_menu ORDER BY sort_order ASC");
    $all_raw_menus = $menu_query->fetchAll(PDO::FETCH_ASSOC);
} else {
    $menu_stmt = $pdo->prepare("
        SELECT m.* FROM sys_menu m
        JOIN sys_role_menu rm ON m.id = rm.menu_id
        WHERE rm.role_name = ?
        ORDER BY m.sort_order ASC
    ");
    $menu_stmt->execute([$user_logged_role]);
    $all_raw_menus = $menu_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Group menu records into distinct hierarchy objects
$root_menu_items = [];
$sub_menu_items  = [];
$is_sub_page_currently_active = false;

foreach ($all_raw_menus as $m) {
    if (intval($m['parent_id']) > 0) {
        $sub_menu_items[$m['parent_id']][] = $m;
        if ($current_page === $m['url']) {
            $is_sub_page_currently_active = true;
        }
    } else {
        $root_menu_items[] = $m;
    }
}
?>

<!-- NATIVE STRUCTURE AS DEFINED IN SECTION 3 OF THE MASTER THEME STYLESHEET -->
<aside class="sidebar" id="sidebarNavDrawer">
    <div class="desktop-sidebar-title">POS Dashboard</div>
    
    <nav>
        <?php foreach ($root_menu_items as $root): 
            $has_children = isset($sub_menu_items[$root['id']]);
            
            // Check if this root link or any of its nested children are currently open
            $is_root_active = ($current_page === $root['url']) || ($root['title'] === 'Admin Control' && $is_sub_page_currently_active);
            $active_class = $is_root_active ? 'active' : '';
            
            if ($has_children): 
            ?>
                <!-- Parent Dropdown Link Component Container Group -->
                <a href="javascript:void(0);" class="<?= $active_class ?>" onclick="toggleAdminDropdownMenu()" id="adminParentHeaderButton">
                    <span><?= htmlspecialchars($root['title']) ?> ▾</span>
                </a>
                
                <!-- Native Sub-Menu Content Layout Grid Wrapper -->
                <div id="adminSubMenuContent" style="display: <?= $is_sub_page_currently_active ? 'flex' : 'none' ?>; flex-direction: column; gap: 4px; padding-left: 15px; margin-top: 4px;">
                    <?php foreach ($sub_menu_items[$root['id']] as $sub): 
                        $is_sub_active = ($current_page === $sub['url']) ? 'active' : '';
                    ?>
                        <a href="<?= htmlspecialchars($sub['url']) ?>" class="<?= $is_sub_active ?>" style="margin-bottom: 2px; padding: 10px 14px;">
                            <span><?= htmlspecialchars($sub['title']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: 
                $href_target = empty($root['url']) || $root['url'] === '#' ? 'javascript:void(0);' : htmlspecialchars($root['url']);
            ?>
                <!-- Regular Standalone Main Menu Sidebar Anchor Link Row -->
                <a href="<?= $href_target ?>" class="<?= $active_class ?>">
                    <span><?= htmlspecialchars($root['title']) ?></span>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>

        <!-- Maintain target system styling structure for logouts exactly -->
        <a href="logout.php" style="color: #ff5252 !important; border-color: #ff5252 !important; margin-top: 20px;">
            <span>[➔] Sign Out Terminal</span>
        </a>
    </nav>
</aside>

<script>
// Interactive dropdown toggle handler
function toggleAdminDropdownMenu() {
    const subMenuBox = document.getElementById("adminSubMenuContent");
    if (subMenuBox) {
        if (subMenuBox.style.display === "none" || subMenuBox.style.display === "") {
            subMenuBox.style.display = "flex";
        } else {
            subMenuBox.style.display = "none";
        }
    }
}
</script>