<?php
// /home/apartment/artistsfarmjaipur.com/Order/includes/left_menu.php
if (!isset($pdo)) {
    require_once __DIR__ . '/../config/db.php';
}

$current_page = basename($_SERVER['PHP_SELF']);
$user_role = $_SESSION['role'] ?? 'Staff';
$is_super_admin = ($user_role === 'Super Admin');

// 🔑 FIXED: Initialize variable to NULL to prevent "Undefined" warnings
$active_guest = null;

// Fetch Dynamic Menus based on Role Access
if ($is_super_admin) {
    $menuStmt = $pdo->query("SELECT * FROM sys_menu ORDER BY parent_id ASC, sort_order ASC");
    $allowed_menus = $menuStmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $pdo->prepare("
        SELECT m.* FROM sys_menu m
        JOIN sys_role_menu rm ON m.id = rm.menu_id
        WHERE rm.role_name = ?
        ORDER BY m.parent_id ASC, m.sort_order ASC
    ");
    $stmt->execute([$user_role]);
    $allowed_menus = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Attempt to fetch active guest
try {
    $active_guest = $pdo->query("SELECT id, guest_name, phone_number FROM guests WHERE status = 'Active' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $active_guest = null;
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

<div class="sidebar" id="appLeftNavigationMenu" style="position: fixed; top: 0; left: 0; height: 100vh; width: 280px; background: #ffffff; box-shadow: 4px 0 25px rgba(0,0,0,0.15); display: flex; flex-direction: column; z-index: 10001; box-sizing: border-box; padding: 15px 16px; overflow-y: auto;">
    
    <button type="button" class="close-drawer-btn" onclick="toggleLeftMenu(false)" style="position:absolute; top:12px; right:15px; background:none; border:none; font-size:20px; cursor:pointer;">✕</button>

    <nav style="display: flex; flex-direction: column; gap: 4px; width: 100%; padding-top: 30px;">
        
        <div id="sidebarActionArea" style="margin-bottom: 20px;">
            <?php if ($active_guest): ?>
                <a href="billing.php" class="sidebar-action-card" style="background: #e53e3e; color: white; display:flex; justify-content:center; align-items:center;">
                    🛑 Guest Checkout (<?= htmlspecialchars($active_guest['guest_name']) ?>)
                </a>
            <?php else: ?>
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 8px;">
                    <form method="POST" action="checkin.php" style="margin: 0;">
                        <input type="hidden" name="action_sidebar_activate" value="1">
                        <select name="sidebar_guest_select" required style="width: 100%; padding: 6px; margin-bottom: 6px; border-radius: 6px; border: 1px solid #cbd5e0; font-size: 11px;">
                            <option value="">-- Choose Guest to Activate --</option>
                            <?php 
                            $guests = $pdo->query("SELECT id, guest_name, phone_number FROM guests WHERE status = 'Checked In' ORDER BY checkin_date DESC")->fetchAll();
                            foreach ($guests as $g): ?>
                                <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['guest_name']) ?> (<?= substr($g['phone_number'], -4) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="sidebar-action-card sb-btn-inactive">▶ Activate Ledger</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <?php foreach ($main_menus as $menu): ?>
            <?php if ($menu['title'] === 'Admin Control'): ?>
                <div class="admin-settings-wrapper" style="margin-top: 6px; border-top: 1px solid #cbd5e0; padding-top: 6px;">
                    <div onclick="toggleAdminSubMenu()" class="nav-link" style="display: flex; justify-content: space-between; cursor: pointer; padding: 10px 16px;">
                        <span><?= htmlspecialchars($menu['icon']) ?> Admin Control</span>
                        <span id="adminMenuChevron">▶</span>
                    </div>
                    <div id="adminSubMenuContent" style="display: none; padding-left: 15px;">
                        <?php foreach ($sub_menus[$menu['id']] ?? [] as $sub): ?>
                            <a href="<?= htmlspecialchars($sub['url']) ?>" class="nav-link" style="font-size: 12px; padding: 8px 12px;"><?= htmlspecialchars($sub['title']) ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <a href="<?= htmlspecialchars($menu['url']) ?>" class="nav-link <?= ($current_page === $menu['url']) ? 'active' : '' ?>">
                    <?= htmlspecialchars($menu['icon']) ?> <?= htmlspecialchars($menu['title']) ?>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>

        <a href="logout.php" class="nav-link" style="margin-top: 20px; color: #e53e3e; text-align: center;">🔒 Sign Out</a>
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