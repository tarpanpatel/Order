<?php
// /home/apartment/artistsfarmjaipur.com/Order/staff_meals.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once "config/db.php";

if (!isset($_SESSION["role"]) || !check_page_access($pdo)) {
    die("Access Denied: You do not have permission to access this area.");
}

// --- HANDLE ADDING NEW PREDEFINED MEAL TO DATABASE ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action_add_predefined_meal"])) {
    $meal_name = trim($_POST["new_meal_name"]);
    $meal_price = floatval($_POST["new_meal_price"]);
    
    if (!empty($meal_name)) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO staff_predefined_meals (meal_name, default_price) VALUES (?, ?)");
        if($stmt->execute([$meal_name, $meal_price])) {
            $_SESSION['meal_toast'] = "✔ New custom meal saved to database!";
        }
    }
    header("Location: staff_meals.php");
    exit;
}

// --- HANDLE LOGGING STAFF MEALS ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action_log_staff_meal"])) {
    $staff_names = $_POST["staff_names"] ?? [];
    $consumption_type = $_POST["consumption_type"];
    $quantity = floatval($_POST["quantity"]);
    $estimated_cost = floatval($_POST["estimated_cost"]);
    $description = trim($_POST["custom_description"]);
    $logged_at_input = $_POST["logged_at"];
    
    // Ensure date is not in the future
    if (empty($logged_at_input) || strtotime($logged_at_input) > time()) {
        $logged_at = date('Y-m-d H:i:s');
    } else {
        $logged_at = date('Y-m-d H:i:s', strtotime($logged_at_input));
    }

    if (!empty($staff_names) && !empty($description) && $quantity > 0) {
        $stmt = $pdo->prepare("INSERT INTO staff_food_logs (staff_name, consumption_type, description, quantity, estimated_cost, logged_by, logged_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $audit_stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, timestamp) VALUES (?, ?, NOW())");
        
        foreach ($staff_names as $s_name) {
            $stmt->execute([$s_name, $consumption_type, $description, $quantity, $estimated_cost, $_SESSION['username'], $logged_at]);
            $audit_stmt->execute([$_SESSION['user_id'], "User [{$_SESSION['username']}] logged a staff meal ($consumption_type) for $s_name: $quantity x $description (Est. Cost: ₹$estimated_cost) at $logged_at"]);
        }

        $_SESSION['meal_toast'] = "✔ Staff meal(s) recorded successfully!";
        header("Location: staff_meals.php");
        exit;
    } else {
        $_SESSION['meal_error'] = "❌ Please select at least one staff member and fill required fields.";
    }
}

// --- FETCH DATA FOR UI ---
$staff_list = $pdo->query("SELECT username FROM users ORDER BY username ASC")->fetchAll(PDO::FETCH_COLUMN);
$predefined_meals = $pdo->query("SELECT meal_name, default_price FROM staff_predefined_meals ORDER BY meal_name ASC")->fetchAll(PDO::FETCH_ASSOC);

$selected_month = isset($_GET['filter_month']) ? trim($_GET['filter_month']) : date('Y-m');

// Fetch Recent Logs for the selected month
$logsStmt = $pdo->prepare("SELECT * FROM staff_food_logs WHERE DATE_FORMAT(logged_at, '%Y-%m') = ? ORDER BY logged_at DESC");
$logsStmt->execute([$selected_month]);
$recent_logs = $logsStmt->fetchAll(PDO::FETCH_ASSOC);

$current_datetime_local = date('Y-m-d\TH:i');

include "includes/header.php";
?>

<div class="app-body" style="padding: 20px; text-align: left;">

    <?php if (isset($_SESSION['meal_toast'])): ?>
        <div class="billing-banner-success">📢 <?= htmlspecialchars($_SESSION['meal_toast']); unset($_SESSION['meal_toast']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['meal_error'])): ?>
        <div class="billing-banner-error">⚠️ <?= htmlspecialchars($_SESSION['meal_error']); unset($_SESSION['meal_error']); ?></div>
    <?php endif; ?>

    <div class="page-header" style="margin-bottom: 20px;">
        <div>
            <h2 class="page-title">🍲 Staff Duty Meals & Leftovers</h2>
            <p style="color: #64748b; font-size: 13px; margin-top: 4px;">Track employee food consumption and quantify leftover guest orders.</p>
        </div>
        <form method="GET" class="filter-form" style="margin: 0;">
            <input type="month" name="filter_month" value="<?= $selected_month ?>" class="field-select-full" style="width: auto;" onchange="this.form.submit()">
        </form>
    </div>

    <div class="billing-grid-split" style="grid-template-columns: 380px 1fr;">
        
        <!-- LOGGING FORM -->
        <div class="workspace-panel-stack">
            <div class="billing-card" style="position: sticky; top: 80px;">
                <div class="billing-section-title">➕ Record Consumption</div>
                <form method="POST" action="staff_meals.php" style="margin: 0;">
                    <input type="hidden" name="action_log_staff_meal" value="1">

                    <div class="mb-15">
                        <label class="form-label-header">Date & Time of Record</label>
                        <input type="datetime-local" name="logged_at" value="<?= $current_datetime_local ?>" max="<?= $current_datetime_local ?>" required class="form-input-container">
                    </div>

                    <div class="mb-15">
                        <label class="form-label-header">Consuming Staff Members</label>
                        <div style="max-height: 140px; overflow-y: auto; border: 1px solid #cbd5e0; padding: 10px; border-radius: 8px; background: #f8fafc;">
                            <?php foreach ($staff_list as $staff): ?>
                                <label style="display: block; margin-bottom: 6px; font-size: 13px; font-weight: 600; color: #1e293b; cursor: pointer;">
                                    <input type="checkbox" name="staff_names[]" value="<?= htmlspecialchars($staff) ?>" style="margin-right: 8px; transform: scale(1.1);"> 
                                    <?= htmlspecialchars($staff) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="mb-15">
                        <label class="form-label-header">Consumption Type</label>
                        <select name="consumption_type" required class="form-input-container">
                            <option value="Freshly Prepared">Freshly Prepared (New Stock)</option>
                            <option value="Guest Leftover/Cancelled">Guest Leftover / Cancelled Order</option>
                        </select>
                    </div>

                    <div class="mb-15">
                        <label class="form-label-header">Custom Meal Combination</label>
                        <div style="display: flex; gap: 8px; align-items: center;">
                            <select name="custom_description" id="mealDropdown" required class="form-input-container" onchange="autoFillPrice()">
                                <option value="" data-price="0">-- Select Database Meal --</option>
                                <?php foreach ($predefined_meals as $meal): ?>
                                    <option value="<?= htmlspecialchars($meal['meal_name']) ?>" data-price="<?= $meal['default_price'] ?>"><?= htmlspecialchars($meal['meal_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn btn-log" style="flex: 0 0 44px; height: 44px; padding: 0; font-size: 18px;" onclick="document.getElementById('addMealModal').style.display='flex'">+</button>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 20px;">
                        <div>
                            <label class="form-label-header">Quantity</label>
                            <input type="number" step="0.01" name="quantity" id="qtyInput" value="1" required class="form-input-container" oninput="autoFillPrice()">
                        </div>
                        <div>
                            <label class="form-label-header">Est. Cost Value (₹)</label>
                            <input type="number" step="0.01" name="estimated_cost" id="estCostInput" required class="form-input-container">
                        </div>
                    </div>

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
                    <tbody id="staffLogsContainer">
                        <?php if (!empty($recent_logs)): foreach ($recent_logs as $log): 
                            $badge_class = ($log['consumption_type'] === 'Freshly Prepared') ? 'badge-completed' : 'badge-pending';
                        ?>
                            <tr class="billing-table-tbody-row staff-log-row" style="display: none;">
                                <td class="p-8" style="color:#64748b; font-size:11px; font-weight:600;">
                                    <?= date('d M', strtotime($log['logged_at'])) ?><br>
                                    <span style="font-size:10px;"><?= date('H:i A', strtotime($log['logged_at'])) ?></span>
                                </td>
                                <td class="p-8-weight-600"><?= htmlspecialchars($log['staff_name']) ?></td>
                                <td class="p-8">
                                    <strong style="color: #1e293b;"><?= floatval($log['quantity']) ?>x <?= htmlspecialchars($log['description']) ?></strong>
                                </td>
                                <td class="p-8-center"><span class="status-badge-tag <?= $badge_class ?>"><?= $log['consumption_type'] ?></span></td>
                                <td class="p-8-right-weight-700 text-sky-blue">₹<?= number_format($log['estimated_cost'], 2) ?></td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr id="emptyLogState"><td colspan="5" class="billing-table-empty-td">No staff meals or leftovers recorded this month.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <?php if (count($recent_logs) > 10): ?>
                    <button type="button" id="loadMoreBtn" class="btn btn-log" style="width: 100%; padding: 10px; border-radius: 8px; margin-top: 10px;" onclick="loadMoreLogs()">Load More Entries</button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal to Add New Custom Predefined Meal to DB -->
<div id="addMealModal" class="print-modal-overlay">
    <div class="print-modal-box" style="font-family: inherit;">
        <h3 style="margin-top: 0; color: #1e293b; border-bottom: 1px dashed #cbd5e0; padding-bottom: 10px; margin-bottom: 15px;">➕ Create Custom Meal Template</h3>
        <form method="POST" action="staff_meals.php" style="margin: 0;">
            <input type="hidden" name="action_add_predefined_meal" value="1">
            <div class="mb-15">
                <label class="form-label-header">Combo/Meal Name</label>
                <input type="text" name="new_meal_name" required placeholder="e.g., 2 Roti, Dal & Rice" class="form-input-container">
            </div>
            <div class="mb-20">
                <label class="form-label-header">Default Estimated Price (₹)</label>
                <input type="number" step="0.01" name="new_meal_price" required placeholder="50.00" class="form-input-container">
            </div>
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn" style="background: #e2e8f0; color: #475569; padding: 10px 16px;" onclick="document.getElementById('addMealModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-start" style="padding: 10px 20px;">Save to Database</button>
            </div>
        </form>
    </div>
</div>

<script>
// Auto Fill Cost based on selected meal template and quantity
function autoFillPrice() {
    const dropdown = document.getElementById("mealDropdown");
    const selectedOption = dropdown.options[dropdown.selectedIndex];
    const basePrice = parseFloat(selectedOption.getAttribute("data-price")) || 0;
    const qty = parseFloat(document.getElementById("qtyInput").value) || 1;
    
    if (basePrice > 0) {
        document.getElementById("estCostInput").value = (basePrice * qty).toFixed(2);
    } else {
        document.getElementById("estCostInput").value = "";
    }
}

// Pagination Logic for Logs (Fixed: Runs instantly on tab load instead of waiting for DOMContentLoaded)
let currentRenderedCount = 0;
const entriesPerPage = 10;
const logRows = Array.from(document.querySelectorAll('.staff-log-row'));

function loadMoreLogs() {
    const btn = document.getElementById('loadMoreBtn');
    const limit = currentRenderedCount + entriesPerPage;
    
    for (let i = currentRenderedCount; i < limit && i < logRows.length; i++) {
        logRows[i].style.display = 'table-row';
        currentRenderedCount++;
    }
    
    if (btn) {
        btn.style.display = currentRenderedCount >= logRows.length ? 'none' : 'block';
    }
}

// Force table rendering initialization immediately on file mount
if (logRows.length > 0) {
    loadMoreLogs();
}

function loadMoreLogs() {
    const btn = document.getElementById('loadMoreBtn');
    const limit = currentRenderedCount + entriesPerPage;
    
    for (let i = currentRenderedCount; i < limit && i < logRows.length; i++) {
        logRows[i].style.display = 'table-row';
        currentRenderedCount++;
    }
    
    if (btn) {
        btn.style.display = currentRenderedCount >= logRows.length ? 'none' : 'block';
    }
}
</script>

<?php include "includes/footer.php"; ?>