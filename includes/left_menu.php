<?php
// /home/apartment/artistsfarmjaipur.com/Order/includes/left_menu.php

// Ensure central database initialization checks run cleanly
require_once __DIR__ . "/../config/db.php";

$current_page = basename($_SERVER['PHP_SELF']);
$user_logged_role = $_SESSION['role'] ?? 'Staff';

// Fetch items dynamically based on verified user authorizations
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

<!-- NATIVE STRUCTURE AS DEFINED IN SECTION 3 OF THE MASTER THEME STYLESHEET -->
<aside class="sidebar" id="sidebarNavDrawer">
    <!-- Keep the exact header branding title styles -->
    <div class="desktop-sidebar-title">POS Dashboard</div>
    
    <nav>
        <?php foreach ($active_menu_items as $menu): 
            // Standard conditional class indicator assignment matching active views
            $is_current = ($current_page === $menu['url']) ? 'active' : '';
            
            $href_target = htmlspecialchars($menu['url']);
            if (empty($menu['url']) || $menu['url'] === '#') {
                $href_target = 'javascript:void(0);';
            }
        ?>
            <!-- Render standard structural anchor loops tied into the primary style definitions -->
            <a href="<?= $href_target ?>" class="<?= $is_current ?>">
                <span><?= htmlspecialchars($menu['title']) ?></span>
            </a>
        <?php endforeach; ?>

        <!-- Maintain target system styling structure for logouts exactly -->
        <a href="logout.php" style="color: #ff5252 !important; border-color: #ff5252 !important; margin-top: 20px;">
            <span>[➔] Sign Out Terminal</span>
        </a>
    </nav>
</aside>