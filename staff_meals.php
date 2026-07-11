<?php
// /home/apartment/artistsfarmjaipur.com/Order/staff_meals.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once "config/db.php";

if (!isset($_SESSION["role"]) || !check_page_access($pdo)) {
    die("Access Denied: You do not have permission to access this area.");
}

// --- HANDLE FORM SUBMISSION ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action_log_staff_meal"])) {
    $staff_name = trim($_POST["staff_name"]);
    $consumption_type = $_POST["consumption_type"];
    $quantity = floatval($_POST["quantity"]);
    $estimated_cost = floatval($_POST["estimated_cost"]);
    
    // Determine description based on whether they picked a menu item or custom input
    if ($_POST["food_source"] === "menu") {
        $menu_item_id = intval($_POST["menu_item_id"]);
        $stmt = $pdo->prepare("SELECT name FROM menu_items WHERE id = ?");
        $stmt->execute([$menu_item_id]);
        $description = $stmt->fetchColumn() ?: "Unknown Menu Item";
    } else {
        $description = trim($_POST["custom_description"]);
    }

    if (!empty($staff_name) && !empty($description) && $quantity > 0) {
        $stmt = $pdo->prepare("INSERT INTO staff_food_logs (staff_name, consumption_type, description, quantity, estimated_cost, logged_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$staff_name, $consumption_type, $description, $quantity, $estimated_cost, $_SESSION['username']]);

        // Audit Trail
        $audit_stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, timestamp) VALUES (?, ?, NOW())");
        $audit_stmt->execute([$_SESSION['user_id'], "User [{$_SESSION['username']}] logged a staff meal ($consumption_type) for $staff_name: $quantity x $description (Est. Cost: ₹$estimated_cost)"]);

        $_SESSION['meal_toast'] = "✔ Staff meal recorded successfully!";
        header("Location: staff_meals.php");
        exit;
    } else {
        $_SESSION['meal_error'] = "❌ Please fill out all required fields.";
    }
}

// --- FETCH DATA FOR UI ---
$staff_list = $pdo->query("SELECT username FROM users ORDER BY username ASC")->fetchAll(PDO::FETCH_COLUMN);
$menu_items = $pdo->query("SELECT id, name, price FROM menu_items WHERE is_hidden = 0 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

$selected_month = isset($_GET['filter_month']) ? trim($_GET['filter_month']) : date('Y-m');

// Calculate Monthly Totals
$statsStmt = $pdo->prepare("
    SELECT 
        SUM(CASE WHEN consumption_type = 'Freshly Prepared' THEN estimated_cost ELSE 0 END) as fresh_cost,
        SUM(CASE WHEN consumption_type = 'Guest Leftover/Cancelled' THEN estimated_cost ELSE 0 END) as leftover_cost
    FROM staff_food_logs 
    WHERE DATE_FORMAT(logged_at, '%Y-%m') = ?
");
$statsStmt->execute([$selected_month]);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

$fresh_cost = floatval($stats['fresh_cost'] ?? 0);
$leftover_cost = floatval($stats['leftover_cost'] ?? 0);
$total_cost = $fresh_cost + $leftover_cost;

// Fetch Recent Logs
$logsStmt = $pdo->prepare("SELECT * FROM staff_food_logs WHERE DATE_FORMAT(logged_at, '%Y-%m') = ? ORDER BY logged_at DESC");
$logsStmt->execute([$selected_month]);
$recent_logs = $logsStmt->fetchAll(PDO::FETCH_ASSOC);

include "includes/header.php";
?>

<div class="app-body" style="padding: 20px; text-align: left;">

    <?php if (isset($_SESSION['meal_toast'])): ?>
        <div class="billing-banner-success">📢 <?= htmlspecialchars($_SESSION['meal_toast']); unset($_SESSION['meal_toast']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['meal_error'])): ?>
        <div class="billing-banner-error">⚠️ <?= htmlspecialchars($_SESSION['meal_error']); unset($_SESSION['meal_error']); ?></div>
    <?php endif; ?>

    <div class="page-header">
        <div>
            <h2 class="page-title">🍲 Staff Duty Meals & Leftovers</h2>
            <p style="color: #64748b; font-size: 13px; margin-top: 4px;">Track employee food consumption and quantify leftover guest orders.</p>
        </div>
        <form method="GET" class="filter-form" style="margin: 0;">
            <input type="month" name="filter_month" value="<?= $selected_month ?>" class="field-select-full" style="width: auto;" onchange="this.form.submit()">
        </form>
    </div>

    <!-- Analytics Cards -->
    <div class="metrics-grid">
        <div class="metric-card" style="border-left-color: #3b82f6;">
            <h3>Total Staff Food Value (<?= date('M Y', strtotime($selected_month)) ?>)</h3>
            <div class="value text-sky-blue">₹<?= number_format($total_cost, 2) ?></div>
        </div>
        <div class="metric-card" style="border-left-color: #10b981;">
            <h3>Freshly Prepared Resources</h3>
            <div class="value text-green-success">₹<?= number_format($fresh_cost, 2) ?></div>
        </div>
        <div class="metric-card" style="border-left-color: #f59e0b;">
            <h3>Salvaged Guest Leftovers</h3>
            <div class="value" style="color: #d97706;">₹<?= number_format($leftover_cost, 2) ?></div>
        </div>
    </div>

    <div class="billing-grid-split" style="grid-template-columns: 350px 1fr;">
        
        <!-- LOGGING FORM -->
        <div class="workspace-panel-stack">
            <div class="billing-card" style="position: sticky; top: 80px;">
                <div class="billing-section-title">➕ Record Consumption</div>
                <form method="POST" action="staff_meals.php" style="margin: 0;">
                    <input type="hidden" name="action_log_staff_meal" value="1">

                    <div class="mb-15">
                        <label class="form-label-header">Consuming Staff Member</label>
                        <select name="staff_name" required class="form-input-container">
                            <option value="">-- Select Staff --</option>
                            <?php foreach ($staff_list as $staff): ?>
                                <option value="<?= htmlspecialchars($staff) ?>"><?= htmlspecialchars($staff) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-15">
                        <label class="form-label-header">Consumption Type</label>
                        <select name="consumption_type" required class="form-input-container">
                            <option value="Freshly Prepared">Freshly Prepared (New Stock)</option>
                            <option value="Guest Leftover/Cancelled">Guest Leftover / Cancelled Order</option>
                        </select>
                    </div>

                    <div class="mb-15">
                        <label class="form-label-header">Food Source</label>
                        <div style="display: flex; gap: 10px; margin-bottom: 8px;">
                            <label style="font-size: 13px; font-weight: 600;"><input type="radio" name="food_source" value="menu" checked onchange="toggleFoodSource()"> Menu Item</label>
                            <label style="font-size: 13px; font-weight: 600;"><input type="radio" name="food_source" value="custom" onchange="toggleFoodSource()"> Raw/Custom</label>
                        </div>
                    </div>

                    <!-- Toggleable Input: Menu Item -->
                    <div id="menuItemSelector" class="mb-15">
                        <label class="form-label-header">Select Menu Item</label>
                        <select name="menu_item_id" id="menuItemDropdown" class="form-input-container" onchange="autoCalculateCost()">
                            <option value="" data-price="0">-- Select Food --</option>
                            <?php foreach ($menu_items as $item): ?>
                                <option value="<?= $item['id'] ?>" data-price="<?= $item['price'] ?>"><?= htmlspecialchars($item['name']) ?> (Menu: ₹<?= $item['price'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Toggleable Input: Custom Raw Food -->
                    <div id="customItemSelector" class="mb-15" style="display: none;">
                        <label class="form-label-header">Custom Description</label>
                        <input type="text" name="custom_description" placeholder="e.g., 2 Pcs Roti & Dal" class="form-input-container">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 20px;">
                        <div>
                            <label class="form-label-header">Quantity</label>
                            <input type="number" step="0.01" name="quantity" id="qtyInput" value="1" required class="form-input-container" oninput="autoCalculateCost()">
                        </div>
                        <div>
                            <label class="form-label-header">Est. Cost Value (₹)</label>
                            <input type="number" step="0.01" name="estimated_cost" id="estCostInput" required class="form-input-container">
                        </div>
                    </div>

                    <p style="font-size: 11px; color: #64748b; margin-bottom: 15px; font-style: italic;">Note: Food cost for menu items is auto-estimated at 30% of the selling price, but you can override it manually.</p>

                    <button type="submit" class="btn btn-bill" style="width: 100%; padding: 12px; border-radius: 8px; font-size: 14px; font-weight: 800;">Log Staff Meal</button>
                </form>
            </div>
        </div>

        <!-- TABLE DATA LOGS -->
        <div class="workspace-panel-stack">
            <div class="billing-card">
                <div class="billing-section-title">📋 Monthly Tracking Log (<?= date('M Y', strtotime($selected_month)) ?>)</div>
                <table class="billing-log-table">
                    <thead>
                        <tr class="billing-table-thead-row">
                            <th class="p-8">Date & Time</th>
                            <th class="p-8">Staff Member</th>
                            <th class="p-8">Food Consumed</th>
                            <th class="p-8-center">Type</th>
                            <th class="p-8-right">Value (₹)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recent_logs)): foreach ($recent_logs as $log): 
                            $badge_class = ($log['consumption_type'] === 'Freshly Prepared') ? 'badge-completed' : 'badge-pending';
                        ?>
                            <tr class="billing-table-tbody-row">
                                <td class="p-8" style="color:#64748b; font-size:11px; font-weight:600;"><?= date('d M, H:i', strtotime($log['logged_at'])) ?></td>
                                <td class="p-8-weight-600"><?= htmlspecialchars($log['staff_name']) ?></td>
                                <td class="p-8">
                                    <strong style="color: #1e293b;"><?= floatval($log['quantity']) ?>x <?= htmlspecialchars($log['description']) ?></strong>
                                </td>
                                <td class="p-8-center"><span class="status-badge-tag <?= $badge_class ?>"><?= $log['consumption_type'] ?></span></td>
                                <td class="p-8-right-weight-700 text-sky-blue">₹<?= number_format($log['estimated_cost'], 2) ?></td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="5" class="billing-table-empty-td">No staff meals or leftovers recorded this month.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function toggleFoodSource() {
    const isMenu = document.querySelector('input[name="food_source"]:checked').value === "menu";
    const menuDiv = document.getElementById("menuItemSelector");
    const customDiv = document.getElementById("customItemSelector");
    const menuDropdown = document.getElementById("menuItemDropdown");
    const customInput = document.querySelector('input[name="custom_description"]');
    
    if (isMenu) {
        menuDiv.style.display = "block";
        customDiv.style.display = "none";
        menuDropdown.setAttribute("required", "required");
        customInput.removeAttribute("required");
    } else {
        menuDiv.style.display = "none";
        customDiv.style.display = "block";
        menuDropdown.removeAttribute("required");
        customInput.setAttribute("required", "required");
        document.getElementById("estCostInput").value = ""; // Clear cost for manual entry
    }
}

function autoCalculateCost() {
    if (document.querySelector('input[name="food_source"]:checked').value !== "menu") return;
    
    const dropdown = document.getElementById("menuItemDropdown");
    const selectedOption = dropdown.options[dropdown.selectedIndex];
    const menuPrice = parseFloat(selectedOption.getAttribute("data-price")) || 0;
    const qty = parseFloat(document.getElementById("qtyInput").value) || 1;
    
    // Auto estimate Food Cost at 30% of the menu selling price
    const estimatedCost = (menuPrice * 0.30) * qty;
    
    if (estimatedCost > 0) {
        document.getElementById("estCostInput").value = estimatedCost.toFixed(2);
    } else {
        document.getElementById("estCostInput").value = "";
    }
}
</script>

<?php include "includes/footer.php"; ?>