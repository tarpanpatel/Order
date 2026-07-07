<?php
// /home/apartment/artistsfarmjaipur.com/Order/kitchen_expenses.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once "config/db.php";

if (!isset($_SESSION["user_id"])) { 
    header("Location: login.php"); 
    exit; 
}

$message = "";

// --- BACKEND LOGIC: POST INTERCEPTOR FOR RECORDING PROCUREMENT INVENTORY ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action_add_kitchen_expense"])) {
    $date           = $_POST["expense_date"];
    $category       = $_POST["item_category"];
    $item_detail    = trim($_POST["item_detail"]);
    $vendor         = trim($_POST["vendor_name"]);
    $qty            = floatval($_POST["quantity"]);
    $price_per_unit = floatval($_POST["price_per_unit"]);

    if (!empty($date) && !empty($item_detail) && $qty > 0 && $price_per_unit > 0) {
        $pdo->beginTransaction();
        try {
            // 1. Insert procurement row into log database
            $stmt = $pdo->prepare("
                INSERT INTO kitchen_expenses (date, category, item_detail, vendor, qty, price_per_unit)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$date, $category, $item_detail, $vendor, $qty, $price_per_unit]);
            
            // 2. AUTOMATED REQUISITION WORKFLOW HOOK:
            // Scan for pending material requests matching the exact name substring or item name
            $updateReq = $pdo->prepare("
                UPDATE material_requests 
                SET status = 'Fulfilled', fulfillment_date = ? 
                WHERE status = 'Pending' 
                AND (LOWER(item_name) LIKE LOWER(?) OR LOWER(?) LIKE CONCAT('%', LOWER(item_name), '%'))
            ");
            $updateReq->execute([$date, $item_detail, $item_detail]);

            $pdo->commit();
            $message = "✔ Stock entry recorded and pending material requisitions updated successfully!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "❌ System pipeline error: " . $e->getMessage();
        }
    } else {
        $message = "❌ Please fill out all required numeric fields correctly.";
    }
}

// Fetch material list dropdown options to ensure naming standard compliance
$materials_list = $pdo->query("SELECT item_name, category FROM materials_registry ORDER BY item_name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch recent entries to show logs stream feed
$recent_logs = $pdo->query("SELECT * FROM kitchen_expenses ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);

include "includes/header.php";
?>

<div class="app-body" style="padding: 20px; font-family: sans-serif; text-align: left;">
    <div style="max-width: 800px; margin: 0 auto; background: #ffffff; border: 1px solid #cbd5e0; border-radius: 12px; padding: 25px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        <h3 style="margin-top:0; color:#1e293b; border-bottom:1px solid #e2e8f0; padding-bottom:10px;">🍳 RECORD KITCHEN PROCUREMENT & STOCK</h3>
        
        <?php if(!empty($message)): ?>
            <div style="background:#f0fdf4; border:1px solid #bbf7d0; color:#166534; padding:12px; border-radius:8px; margin-bottom:15px; font-size:14px; font-weight:bold;"><?= $message ?></div>
        <?php endif; ?>

        <form method="POST" action="kitchen_expenses.php">
            <input type="hidden" name="action_add_kitchen_expense" value="1">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom:15px;">
                <div>
                    <label style="font-size:11px; font-weight:700; color:#475569; display:block; margin-bottom:4px;">RECORDED DATE</label>
                    <input type="date" name="expense_date" required class="form-control" value="<?= date('Y-m-d') ?>" style="width:100%; padding:10px; border:1px solid #cbd5e0; border-radius:6px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="font-size:11px; font-weight:700; color:#475569; display:block; margin-bottom:4px;">CLASSIFICATION PROFILE</label>
                    <select name="item_category" required style="width:100%; padding:10px; border:1px solid #cbd5e0; border-radius:6px; background:white; font-weight:bold;">
                        <option value="Rations">Kitchen Provisions / Rations</option>
                        <option value="Vegetables">Fresh Produce / Vegetables</option>
                        <option value="Dairy">Dairy Products</option>
                        <option value="Gas / Fuel">LPG Gas / Generator Fuel</option>
                    </select>
                </div>
            </div>

            <div style="margin-bottom:15px;">
                <label style="font-size:11px; font-weight:700; color:#475569; display:block; margin-bottom:4px;">INVENTORY ITEM DETAIL (standard name link match)</label>
                <input type="text" name="item_detail" id="itemDetailInput" list="registryMaterialsList" required placeholder="e.g., Mustard Oil, Basmati Rice" style="width:100%; padding:10px; border:1px solid #cbd5e0; border-radius:6px; box-sizing:border-box; font-weight:600;">
                <datalist id="registryMaterialsList">
                    <?php foreach ($materials_list as $mat): ?>
                        <option value="<?= htmlspecialchars($mat['item_name']) ?>"><?= htmlspecialchars($mat['category']) ?></option>
                    <?php endforeach; ?>
                </datalist>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom:20px;">
                <div>
                    <label style="font-size:11px; font-weight:700; color:#475569; display:block; margin-bottom:4px;">QUANTITY purchased</label>
                    <input type="number" step="0.01" name="quantity" required placeholder="0" style="width:100%; padding:10px; border:1px solid #cbd5e0; border-radius:6px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="font-size:11px; font-weight:700; color:#475569; display:block; margin-bottom:4px;">UNIT PRICE (₹)</label>
                    <input type="number" step="0.01" name="price_per_unit" required placeholder="0.00" style="width:100%; padding:10px; border:1px solid #cbd5e0; border-radius:6px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="font-size:11px; font-weight:700; color:#475569; display:block; margin-bottom:4px;">VENDOR NAME</label>
                    <input type="text" name="vendor_name" placeholder="Local Wholesale Mart" style="width:100%; padding:10px; border:1px solid #cbd5e0; border-radius:6px; box-sizing:border-box;">
                </div>
            </div>

            <button type="submit" style="width:100%; padding:12px; font-size:14px; font-weight:bold; background:#0284c7; border:none; color:white; border-radius:8px; cursor:pointer;">Save Stock Entry & Check Requisitions</button>
        </form>
    </div>

    <div style="max-width:800px; margin:25px auto 0 auto; background:#fff; border:1px solid #cbd5e0; border-radius:12px; padding:20px;">
        <h4 style="margin-top:0; border-bottom:1px solid #e2e8f0; padding-bottom:8px; text-transform:uppercase; font-size:11px; color:#475569;">Recent Kitchen Stock Procurement Entries</h4>
        <table style="width:100%; border-collapse:collapse; font-size:13px; text-align:left;">
            <thead>
                <tr style="background:#f8fafc; border-bottom:2px solid #cbd5e0;">
                    <th style="padding:10px;">Date</th>
                    <th style="padding:10px;">Category</th>
                    <th style="padding:10px;">Item Description</th>
                    <th style="padding:10px; text-align:right;">Qty</th>
                    <th style="padding:10px; text-align:right;">Total Paid</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_logs as $row): ?>
                    <tr style="border-bottom:1px solid #edf2f7;">
                        <td style="padding:10px; color:#64748b;"><?= $row['date'] ?></td>
                        <td style="padding:10px;"><span style="font-weight:700; color:#ea580c;"><?= htmlspecialchars($row['category']) ?></span></td>
                        <td style="padding:10px;"><strong><?= htmlspecialchars($row['item_detail']) ?></strong> <span style="font-size:11px; color:#64748b;">(via <?= htmlspecialchars($row['vendor'] ?: 'Market') ?>)</span></td>
                        <td style="padding:10px; text-align:right; font-weight:600;"><?= $row['qty'] ?></td>
                        <td style="padding:10px; text-align:right; font-weight:800; color:#1e293b;">₹<?= number_format($row['qty'] * $row['price_per_unit'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include "includes/footer.php"; ?>