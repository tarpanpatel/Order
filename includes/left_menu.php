<?php
// /home/apartment/artistsfarmjaipur.com/Order/includes/left_menu.php
// Optimized fluid scrolling layout for tall mobile screens
?>
<div class="sidebar" id="appLeftNavigationMenu" style="position: fixed; top: 0; left: 0; height: 100vh; width: 280px; background: #ffffff; box-shadow: 4px 0 25px rgba(0,0,0,0.15); display: flex; flex-direction: column; z-index: 10001; box-sizing: border-box; padding: 15px 16px; overflow-y: auto; -webkit-overflow-scrolling: touch;">
    
    <div style="position: absolute; top: 12px; right: -42px; z-index: 10002; margin: 0; padding: 0;">
        <button type="button" class="close-drawer-btn" onclick="event.stopPropagation(); toggleLeftMenu(false);" style="background: #ffffff; border: 1px solid #cbd5e0; border-radius: 50%; font-size: 1.1rem; cursor: pointer; font-weight: bold; color: #e53e3e; width: 34px; height: 34px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">✕</button>
    </div>
    
    <nav style="display: flex; flex-direction: column; gap: 4px; width: 100%; padding-top: 5px;">
        <a href="index.php" class="nav-link <?= ($current_page === 'index.php') ? 'active' : ''; ?>">📊 Dashboard</a>

        <?php if (!empty($current_active_guest)): ?>
            <div style="margin: 4px 0; background: #fdfaf7; border: 1px solid #cbd5e0; border-radius: 8px; padding: 8px;">
                <div style="font-size:11px; font-weight:bold; color:#0891b2; margin-bottom:5px; text-align:center; text-transform:uppercase;">● Active: 📱 (<?= substr($current_active_guest['phone_number'], -4) ?>)</div>
                <a href="billing.php" class="sidebar-action-card sb-btn-active">⏸ Checkout Billing</a>
            </div>
        <?php else: ?>
            <div style="margin: 4px 0; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 8px;">
                <form method="POST" action="checkin.php" style="margin: 0;">
                    <input type="hidden" name="action_sidebar_activate" value="1">
                    <select name="sidebar_guest_select" required style="width: 100%; padding: 6px; margin-bottom: 6px; border-radius: 6px; border: 1px solid #cbd5e0; font-size: 11px; background: #fff; color: #1e293b;">
                        <option value="">-- Choose Guest --</option>
                        <?php foreach ($all_booked_guests as $g): ?>
                            <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['guest_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="sidebar-action-card sb-btn-inactive">▶ Activate Ledger</button>
                </form>
            </div>
        <?php endif; ?>

        <a href="checkin.php" class="nav-link <?= ($current_page === 'checkin.php') ? 'active' : ''; ?>">👤 Guest Registration</a>
        <a href="billing.php" class="nav-link <?= ($current_page === 'billing.php') ? 'active' : ''; ?>">🧾 Settlements & Billing</a>
        <a href="order.php" class="nav-link <?= ($current_page === 'order.php') ? 'active' : ''; ?>">🍽️ Take Food Order</a>
        <a href="kitchen.php" class="nav-link <?= ($current_page === 'kitchen.php') ? 'active' : ''; ?>">🍳 Kitchen Orders</a>
        <a href="requisitions.php" class="nav-link <?= ($current_page === 'requisitions.php') ? 'active' : ''; ?>">📦 Material Requests</a>
        <a href="kitchen_purchases.php" class="nav-link <?= ($current_page === 'kitchen_purchases.php') ? 'active' : ''; ?>">🛒 Kitchen Purchases</a>
        <a href="expenses.php" class="nav-link <?= ($current_page === 'expenses.php') ? 'active' : ''; ?>">📈 Expenses</a>

        <?php 
        $is_manager = isset($_SESSION["role"]) && $_SESSION["role"] === 'Admin';
        $is_super   = isset($_SESSION["role"]) && $_SESSION["role"] === 'Super Admin';
        if ($is_super || $is_manager): 
        ?>
            <div class="admin-settings-wrapper" style="margin-top: 6px; border-top: 1px solid #cbd5e0; padding-top: 6px; width: 100%;">
                <div onclick="toggleAdminSubMenu()" class="nav-link" style="display: flex; justify-content: space-between; align-items: center; cursor: pointer; font-weight: 700; color: #475569; padding: 10px 16px;">
                    <span>🛠️ Admin Control</span>
                    <span id="adminMenuChevron" style="font-size: 10px; transition: transform 0.2s ease;">▶</span>
                </div>

                <div id="adminSubMenuContent" style="display: none; flex-direction: column; gap: 4px; padding-left: 15px; margin-top: 4px;">
                    <a href="dashboard_analytics.php" class="nav-link <?= ($current_page === 'dashboard_analytics.php') ? 'active' : ''; ?>" style="font-size: 12px; padding: 8px 12px;">📊 Dashboard Analytics</a>
                    <a href="export_center.php" class="nav-link <?= ($current_page === 'export_center.php') ? 'active' : ''; ?>" style="font-size: 12px; padding: 8px 12px;">💾 Data Export Center</a>
                    <a href="deficient_stock_logs_view.php" class="nav-link <?= ($current_page === 'deficient_stock_logs_view.php') ? 'active' : ''; ?>" style="font-size: 12px; padding: 8px 12px;">⚠️ Deficit Shortfalls Log</a>
                    <a href="past_receipts.php" class="nav-link <?= ($current_page === 'past_receipts.php') ? 'active' : ''; ?>" style="font-size: 12px; padding: 8px 12px;">📜 Past Receipts Log</a>
                    <a href="menu_admin.php" class="nav-link <?= ($current_page === 'menu_admin.php') ? 'active' : ''; ?>" style="font-size: 12px; padding: 8px 12px;">🍽️ Menu Configuration</a>
                    <a href="materials_admin.php" class="nav-link <?= ($current_page === 'materials_admin.php') ? 'active' : ''; ?>" style="font-size: 12px; padding: 8px 12px;">📦 Material Registry</a>
                    <a href="expense_items_management.php" class="nav-link <?= ($current_page === 'expense_items_management.php') ? 'active' : ''; ?>" style="font-size: 12px; padding: 8px 12px;">🔧 Add Expense Items</a>
                    <a href="staff_management.php" class="nav-link <?= ($current_page === 'staff_management.php') ? 'active' : ''; ?>" style="font-size: 12px; padding: 8px 12px;">👥 Add Staff Members</a>
                    <a href="activity_logs.php" class="nav-link <?= ($current_page === 'activity_logs.php') ? 'active' : ''; ?>" style="font-size: 12px; padding: 8px 12px;">👣 Staff Activity Trail</a>
                    <a href="change_passcode.php" class="nav-link <?= ($current_page === 'change_passcode.php') ? 'active' : ''; ?>" style="font-size: 12px; padding: 8px 12px;">🔐 Passcode Control</a>
                    <a href="login_logs.php" class="nav-link <?= ($current_page === 'login_logs.php') ? 'active' : ''; ?>" style="font-size: 12px; padding: 8px 12px;">🖥️ Security Trace Logs</a>
                </div>
            </div>
        <?php endif; ?>

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