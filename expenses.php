<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "config/db.php";
// require_once "config/google_sheets_bridge.php"; 
include_once __DIR__ . '/config/local_db_bridge.php';

if (!isset($_SESSION["role"]) || ($_SESSION["role"] !== "Admin" && $_SESSION["role"] !== "Super Admin")) {
    header("Location: login.php");
    exit;
}

$feedback = "";
$feedback_class = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action_add_utility_expense"])) {
    $exp_date   = $_POST["exp_date"];
    $category   = $_POST["exp_category"];
    $desc       = trim($_POST["exp_desc"]);
    $amount     = floatval($_POST["exp_amount"]);
    $pay_mode   = $_POST["exp_pay_mode"];
    $vendor     = trim($_POST["exp_vendor"] ?: 'Other');

    if (!empty($desc) && $amount > 0) {
        $stmt = $pdo->prepare("INSERT INTO farm_utility_expenses (expense_date, category, description, amount, payment_mode, vendor_name) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$exp_date, $category, $desc, $amount, $pay_mode, $vendor])) {
            
            // GOOGLE WORKBOOK SYNC PASS-THROUGH
            // Target Sheet Tab Name: "Farm Exp "
            // Row Layout Format: [Sr No, Date, Description, Amount, Payment Mode, Vendor, Remark/Category]
            $googleRowData = ['', $exp_date, $desc, $amount, $pay_mode, $vendor, $category];
            appendRowToGoogleSheet('Farm Exp ', $googleRowData);

            $feedback = "✔ Utility expense successfully saved and synced to Google Sheets!";
            $feedback_class = "success-msg";
        }
    }
}

$recent_utilities = $pdo->query("SELECT * FROM farm_utility_expenses ORDER BY id DESC LIMIT 15")->fetchAll(PDO::FETCH_ASSOC);

include "includes/header.php";
?>

<style>
.utility-card-container { max-width:650px; margin:20px auto; background:#fff; border:1px solid #cbd5e0; border-radius:12px; padding:25px; text-align:left;}
.entry-row-block { margin-bottom:12px; }
.entry-row-block label { display:block; font-size:11px; font-weight:700; color:#4b5563; margin-bottom:4px; text-transform:uppercase;}
.entry-row-block input, .entry-row-block select { width:100%; padding:10px; font-size:13px; border:1px solid #cbd5e0; border-radius:6px; box-sizing:border-box; background:#fff;}
.alert-banner { padding:10px; font-size:12px; font-weight:700; border-radius:6px; text-align:center; margin-bottom:15px;}
.success-msg { background:#d1fae5; color:#069669; border:1px solid #a7f3d0;}
.utility-table { width:100%; border-collapse:collapse; font-size:12px; margin-top:20px;}
.utility-table th { background:#f8fafc; padding:10px; font-weight:700; color:#4b5563; border-bottom:2px solid #cbd5e0;}
.utility-table td { padding:10px; border-bottom:1px solid #edf2f7; color:#111827;}
</style>

<div class="app-body">
    <div class="category-section">
        <h2 class="category-title" style="text-transform: none;">🔧 Operational Utility Expense Registry</h2>
    </div>

    <div class="utility-card-container" style="border-top:3px solid #06b6d4;">
        <h3 style="font-size:13px; font-weight:700; text-transform:uppercase; margin-top:0; margin-bottom:15px; border-bottom:1px dashed #cbd5e0; padding-bottom:8px;">Record Fixed Farm Costs</h3>
        
        <?php if(!empty($feedback)): ?>
            <div class="alert-banner <?= $feedback_class ?>"><?= $feedback ?></div>
        <?php endif; ?>

        <form method="POST" action="expenses.php">
            <input type="hidden" name="action_add_utility_expense" value="1">
            
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div class="entry-row-block">
                    <label>Expense Date</label>
                    <input type="date" name="exp_date" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="entry-row-block">
                    <label>Cost Category Group</label>
                    <select name="exp_category" required>
                        <option value="Salaries">Staff Salaries</option>
                        <option value="Petrol / Diesel">Fuel (Petrol/Diesel)</option>
                        <option value="Water Tanker">Water Tanker Supply</option>
                        <option value="Electricity Bill">Electricity Bill</option>
                        <option value="Hardware / Repairs">Hardware Maintenance & Repairs</option>
                        <option value="Garbage Collection">Garbage Collection</option>
                    </select>
                </div>
            </div>

            <div class="entry-row-block">
                <label>Detailed Description</label>
                <input type="text" name="exp_desc" placeholder="e.g., Generator fuel purchase, Monthly salary for Kamlesh" required autocomplete="off">
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px;">
                <div class="entry-row-block">
                    <label>Amount (₹)</label>
                    <input type="number" step="0.01" name="exp_amount" min="1" required placeholder="0.00">
                </div>
                <div class="entry-row-block">
                    <label>Payment Mode</label>
                    <select name="exp_pay_mode">
                        <option value="online">Online / UPI / QR</option>
                        <option value="cash">Cash Outlay</option>
                    </select>
                </div>
                <div class="entry-row-block">
                    <label>Vendor / Recipient</label>
                    <input type="text" name="exp_vendor" placeholder="e.g., RMD Indian Oil" autocomplete="off">
                </div>
            </div>

            <button type="submit" class="btn btn-start" style="width:100%; padding:12px; font-weight:bold; border-radius:6px; margin-top:5px;">Commit & Sync to Workbook</button>
        </form>

        <h4 style="font-size:11px; text-transform:uppercase; color:#4b5563; margin-top:25px; margin-bottom:5px; font-weight:700;">Recent Utility Entries Feed</h4>
        <table class="utility-table">
            <thead><tr><th>Date</th><th>Category</th><th>Description</th><th>Total</th><th>Mode</th></tr></thead>
            <tbody>
                <?php if(!empty($recent_utilities)): foreach($recent_utilities as $u): ?>
                    <tr>
                        <td><?= $u['expense_date'] ?></td>
                        <td><span style="color:#0891b2; font-weight:700;"><?= $u['category'] ?></span></td>
                        <td><strong><?= htmlspecialchars($u['description']) ?></strong></td>
                        <td style="font-weight:700;">₹<?= number_format($u['amount'], 0) ?></td>
                        <td style="text-transform:capitalize; color:#718096; font-size:11px;"><?= $u['payment_mode'] ?></td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="5" style="text-align:center; color:#a0aec0; padding:15px; font-style:italic;">No historical utilities entry records logged.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include "includes/footer.php"; ?>