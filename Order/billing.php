<?php
require_once "config/db.php";
if (!isset($_SESSION["user_id"])) { header("Location: login.php"); exit; }
$guest = $pdo->query("SELECT * FROM guests WHERE status = 'Active' LIMIT 1")->fetch();

if ($guest && isset($_POST["adjust_action"])) {
    $id = intval($_POST["order_item_id"]); $qty = intval($_POST["adjust_qty"]);
    if($_POST["adjust_type"] === "Cancel") { $pdo->prepare("UPDATE order_items SET quantity = quantity - ? WHERE id = ?")->execute([$qty, $id]); }
    else if($_POST["adjust_type"] === "Return") { $pdo->prepare("UPDATE order_items SET returned_qty = returned_qty + ? WHERE id = ?")->execute([$qty, $id]); }
    header("Location: billing.php"); exit;
}

if ($guest && isset($_POST["close_billing_ledger"])) {
    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE guests SET status = 'CheckedOut' WHERE id = ?")->execute([$guest['id']]);
        $pdo->prepare("UPDATE orders SET status = 'Completed' WHERE guest_id = ? AND status = 'Pending'")->execute([$guest['id']]);
        $pdo->commit();
    } catch(Exception $e) { $pdo->rollBack(); }
    header("Location: checkin.php"); exit;
}

include "includes/header.php";
?>

<div id="receiptShareModal" class="modal">
    <div class="modal-content" style="max-width: 420px; font-family: monospace;">
        <span class="close-modal" onclick="document.getElementById('receiptShareModal').style.display='none'">✕</span>
        <h3 style="text-align: center; margin-bottom: 4px;">RECEIPT</h3>
        <p style="text-align: center; font-size: 11px; color: var(--text-muted); margin-bottom: 15px;">Date: <?= date('d-m-Y H:i') ?></p>
        <div style="border-bottom: 1px dashed #cbd5e0; padding-bottom: 8px; margin-bottom: 12px;">
            Guest Name: <strong><?= $guest ? htmlspecialchars($guest["guest_name"]) : '' ?></strong>
        </div>
        <div id="popupReceiptItems"></div>
        <div id="popupReceiptTotal" style="text-align: right; font-weight: 700; font-size: 15px; margin-top: 15px; padding-top: 10px; border-top: 1px dashed #cbd5e0;"></div>
        <div style="margin-top: 20px; display: flex; gap: 8px; justify-content: flex-end;">
            <button class="btn" style="background: #4a5568; max-width: 80px;" onclick="window.print()">Print</button>
            <button class="btn btn-end" style="max-width: 80px;" onclick="document.getElementById('receiptShareModal').style.display='none'">Close</button>
        </div>
    </div>
</div>

<div class="app-body">
    <div class="category-section">
        <h2 class="category-title" style="text-transform: none;">🧾 Settlements & Billing</h2>
    </div>

    <?php if(!$guest): ?>
        <div class="card"><p style="color: var(--text-muted); font-weight: 600;">No active resident ledger balances opened.</p></div>
    <?php else: 
    $items = $pdo->query("SELECT oi.*, mi.name FROM order_items oi JOIN menu_items mi ON oi.menu_item_id = mi.id JOIN orders o ON oi.order_id = o.id WHERE o.guest_id = ".$guest["id"])->fetchAll();
    $subtotal = 0; $jsItemsArray = [];
    ?>
        <div class="digital-receipt">
            <h4 style="text-align: center; font-size: 15px; margin-bottom: 4px;">ACTIVE SHEET PREVIEW</h4>
            <p style="text-align: center; font-size: 12px; color: var(--text-muted); margin-bottom: 20px;">Guest: <strong><?= htmlspecialchars($guest["guest_name"]) ?></strong></p>
            
            <table class="bill-table" style="margin-bottom: 20px;">
                <thead>
                    <tr>
                        <th>Item Details</th>
                        <th style="text-align: center; width: 120px;">Adjustments</th>
                        <th style="text-align: right; width: 80px;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($items as $i): if($i["quantity"] <= 0) continue; 
                    $net = $i["quantity"] - $i["returned_qty"]; $cost = $net * $pdo->query("SELECT price FROM menu_items WHERE id = ".$i["menu_item_id"])->fetchColumn(); $subtotal += $cost;
                    if($net > 0) { $jsItemsArray[] = ["name" => $i["name"], "qty" => $net, "cost" => $cost]; }
                ?>
                    <tr>
                        <td>
                            <div style="font-weight: 600; font-size: 13px;"><?= htmlspecialchars($i["name"]) ?> <span style="color: var(--text-muted); font-size: 11px;">(x<?= $i["quantity"] ?>)</span></div>
                            <?php if($i["returned_qty"] > 0): ?>
                                <div style="color: var(--danger); font-size: 11px; font-weight: 700; margin-top: 2px;">↳ Returned: <?= $i["returned_qty"] ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="POST" style="display: flex; gap: 4px; align-items: center; margin: 0;">
                                <input type="hidden" name="order_item_id" value="<?= $i["id"] ?>">
                                <input type="number" name="adjust_qty" value="1" max="<?= $net ?>" min="1" style="width: 40px; padding: 4px; margin: 0; font-size: 12px; text-align: center;">
                                <select name="adjust_type" required style="padding: 4px 20px 4px 6px; margin: 0; font-size: 12px; width: 75px;">
                                    <option value="Return">Return</option>
                                    <option value="Cancel">Cancel</option>
                                </select>
                                <button type="submit" name="adjust_action" class="btn btn-start" style="padding: 5px 8px; font-size: 10px; flex: none; border-radius: 6px;">Apply</button>
                            </form>
                        </td>
                        <td style="text-align: right; font-weight: 700; font-size: 13px;">₹<?= number_format($cost, 0) ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="bill-total-row">
                    <td colspan="2" style="font-weight: 800; padding: 12px 8px;">Total Balance Due:</td>
                    <td style="text-align: right; font-weight: 800; padding: 12px 8px;">₹<?= number_format($subtotal, 0) ?></td>
                </tr>
                </tbody>
            </table>
            
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="button" class="btn btn-log" style="font-size: 12px; padding: 12px;" onclick="openCleanBillPopup()">View Clean Bill</button>
                <form method="POST" style="flex: 1; margin: 0;" onsubmit="return confirm('Close account and check out guest?');">
                    <button type="submit" name="close_billing_ledger" class="btn btn-end" style="font-size: 12px; padding: 12px; width: 100%;">Complete Check-Out</button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
const cleanItems = <?php echo json_encode($jsItemsArray); ?>;
const finalTotal = <?= $subtotal ?>;

function openCleanBillPopup() {
    const itemsContainer = document.getElementById("popupReceiptItems");
    itemsContainer.innerHTML = "";
    cleanItems.forEach(item => {
        itemsContainer.innerHTML += `
            <div style="display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 13px;">
                <span>${item.name} x${item.qty}</span>
                <span>Template_Currency_Symbol${item.cost}</span>
            </div>
        `;
    });
   // Change this final calculation total line
document.getElementById("popupReceiptTotal").innerText = "Total: ₹" + finalTotal;
    document.getElementById("receiptShareModal").style.display = "flex";
}
</script>
<?php include "includes/footer.php"; ?>