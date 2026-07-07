<?php
// /home/apartment/artistsfarmjaipur.com/Order/expenses.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once "config/db.php";

if (!isset($_SESSION["user_id"])) { 
    header("Location: login.php"); 
    exit; 
}

// --- BACKEND LOGIC: POST INTERCEPTOR FOR COMMITTING FIXED COSTS ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action_record_expense"])) {
    $expense_date = $_POST["expense_date"];
    $category     = $_POST["cost_category"];
    $amount       = floatval($_POST["amount"]);
    $payment_mode = $_POST["payment_mode"];
    
    if ($category === "Staff Salaries" && !empty($_POST["selected_staff_name"])) {
        $vendor_name = trim($_POST["selected_staff_name"]);
        $description = "Salary payment for " . $vendor_name;
    } else {
        $vendor_name = trim($_POST["vendor_name"]);
        $description = trim($_POST["description"]);
    }

    if (!empty($expense_date) && $amount > 0 && !empty($category)) {
        $stmt = $pdo->prepare("
            INSERT INTO farm_utility_expenses (expense_date, category, description, amount, payment_mode, vendor_name)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$expense_date, $category, $description, $amount, $payment_mode, $vendor_name]);
        $_SESSION['expense_toast'] = "Expense recorded successfully!";
    }
    header("Location: expenses.php");
    exit;
}

// FIXED: Swapped 'role_name' out for the correct column name 'role' to resolve the PDO Exception
$db_staff = $pdo->query("SELECT username FROM users WHERE role != 'Super Admin' ORDER BY username ASC")->fetchAll(PDO::FETCH_COLUMN);

// Fetch recent entries to show in logs feed
$recent_expenses = $pdo->query("SELECT * FROM farm_utility_expenses ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);

include "includes/header.php";
?>

<div class="app-body" style="padding: 20px; font-family: sans-serif; text-align: left;">
    <div style="max-width: 760px; margin: 0 auto; background: #ffffff; border: 1px solid #cbd5e0; border-radius: 12px; padding: 25px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        <h3 style="margin-top:0; color:#1e293b; border-bottom:1px solid #e2e8f0; padding-bottom:10px;">📝 RECORD FIXED FARM COSTS</h3>
        
        <form method="POST" action="expenses.php" id="expenseRegistryForm">
            <input type="hidden" name="action_record_expense" value="1">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom:15px;">
                <div>
                    <label style="font-size:11px; font-weight:700; color:#475569; display:block; margin-bottom:4px;">EXPENSE DATE</label>
                    <input type="date" name="expense_date" required class="form-control" value="<?= date('Y-m-d') ?>" style="width:100%; padding:10px; border:1px solid #cbd5e0; border-radius:6px;">
                </div>
                <div>
                    <label style="font-size:11px; font-weight:700; color:#475569; display:block; margin-bottom:4px;">COST CATEGORY GROUP</label>
                    <select name="cost_category" id="costCategoryGroup" onchange="toggleExpenseFieldsView()" required style="width:100%; padding:10px; border:1px solid #cbd5e0; border-radius:6px; background:white; font-weight:bold;">
                        <option value="Utility">Utility Costs</option>
                        <option value="Water Tanker">Water Tanker</option>
                        <option value="Maintenance">Property Maintenance</option>
                        <option value="Rations">Kitchen Provisions / Rations</option>
                        <option value="Staff Salaries">Staff Salaries</option>
                    </select>
                </div>
            </div>

            <div id="staffSalaryDropdownContainer" style="display:none; margin-bottom:15px; background:#f8fafc; padding:15px; border-radius:8px; border:1px dashed #06b6d4;">
                <label style="font-size:11px; font-weight:700; color:#0891b2; display:block; margin-bottom:4px;">👤 SELECT SALARY RECIPIENT MEMBER</label>
                <select name="selected_staff_name" style="width:100%; padding:10px; border:1px solid #cbd5e0; border-radius:6px; background:white; font-weight:bold; color:#1e293b;">
                    <option value="">-- Choose Staff Member getting Paid --</option>
                    <?php if (!empty($db_staff)): foreach ($db_staff as $name): ?>
                        <option value="<?= htmlspecialchars($name) ?>"><?= htmlspecialchars($name) ?></option>
                    <?php endforeach; endif; ?>
                </select>
            </div>

            <div id="standardExpenseInputsContainer">
                <div style="margin-bottom:15px;">
                    <label style="font-size:11px; font-weight:700; color:#475569; display:block; margin-bottom:4px;">DETAILED DESCRIPTION</label>
                    <input type="text" name="description" id="expDescInput" placeholder="e.g., Generator fuel purchase, Monthly laundry load" style="width:100%; padding:10px; border:1px solid #cbd5e0; border-radius:6px; box-sizing:border-box;">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom:20px;">
                <div>
                    <label style="font-size:11px; font-weight:700; color:#475569; display:block; margin-bottom:4px;">AMOUNT (₹)</label>
                    <input type="number" step="0.01" name="amount" required placeholder="0.00" style="width:100%; padding:10px; border:1px solid #cbd5e0; border-radius:6px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="font-size:11px; font-weight:700; color:#475569; display:block; margin-bottom:4px;">PAYMENT MODE</label>
                    <select name="payment_mode" style="width:100%; padding:10px; border:1px solid #cbd5e0; border-radius:6px; background:white;">
                        <option value="Online">Online / UPI / QR</option>
                        <option value="Cash">Cash</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                    </select>
                </div>
                <div id="vendorNameInputWrapper">
                    <label style="font-size:11px; font-weight:700; color:#475569; display:block; margin-bottom:4px;">VENDOR / RECIPIENT</label>
                    <input type="text" name="vendor_name" id="expVendorInput" placeholder="e.g., Supply vendor name" style="width:100%; padding:10px; border:1px solid #cbd5e0; border-radius:6px; box-sizing:border-box;">
                </div>
            </div>

            <button type="submit" class="btn btn-bill" style="width:100%; padding:12px; font-size:14px; font-weight:bold; background:#14b8a6; border-color:#14b8a6; color:white; border-radius:8px;">Commit & Sync to Workbook</button>
        </form>
    </div>

    <div style="max-width:760px; margin:25px auto 0 auto; background:#fff; border:1px solid #cbd5e0; border-radius:12px; padding:20px;">
        <h4 style="margin-top:0; border-bottom:1px solid #e2e8f0; padding-bottom:8px; text-transform:uppercase; font-size:11px; color:#475569;">Recent Operational Cost Logs</h4>
        <table style="width:100%; border-collapse:collapse; font-size:13px; text-align:left;">
            <thead>
                <tr style="background:#f8fafc; border-bottom:2px solid #cbd5e0;">
                    <th style="padding:10px;">Date</th>
                    <th style="padding:10px;">Category</th>
                    <th style="padding:10px;">Description</th>
                    <th style="padding:10px; text-align:right;">Total</th>
                    <th style="padding:10px; text-align:center;">Mode</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_expenses as $row): ?>
                    <tr style="border-bottom:1px solid #edf2f7;">
                        <td style="padding:10px; color:#64748b;"><?= $row['expense_date'] ?></td>
                        <td style="padding:10px;"><span style="font-weight:700; color:#0284c7;"><?= htmlspecialchars($row['category']) ?></span></td>
                        <td style="padding:10px;"><strong><?= htmlspecialchars($row['vendor_name']) ?></strong> - <span style="color:#475569; font-size:12px;"><?= htmlspecialchars($row['description']) ?></span></td>
                        <td style="padding:10px; text-align:right; font-weight:800; color:#1e293b;">₹<?= number_format($row['amount'], 2) ?></td>
                        <td style="padding:10px; text-align:center;"><span style="font-size:11px; font-weight:bold; color:#64748b;"><?= $row['payment_mode'] ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function toggleExpenseFieldsView() {
    const categoryGroup = document.getElementById("costCategoryGroup").value;
    const staffContainer = document.getElementById("staffSalaryDropdownContainer");
    const standardInputs = document.getElementById("standardExpenseInputsContainer");
    const vendorWrapper  = document.getElementById("vendorNameInputWrapper");
    
    const descField   = document.getElementById("expDescInput");
    const vendorField = document.getElementById("expVendorInput");

    if (categoryGroup === "Staff Salaries") {
        staffContainer.style.display = "block";
        standardInputs.style.display = "none";
        vendorWrapper.style.display = "none";
        
        descField.removeAttribute("required");
        vendorField.removeAttribute("required");
    } else {
        staffContainer.style.display = "none";
        standardInputs.style.display = "block";
        vendorWrapper.style.display = "block";
        
        descField.setAttribute("required", "required");
        vendorField.setAttribute("required", "required");
    }
}
document.addEventListener("DOMContentLoaded", toggleExpenseFieldsView);
</script>

<?php include "includes/footer.php"; ?>