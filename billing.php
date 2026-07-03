<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
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

<div id="customAdjustmentModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 99999; justify-content: center; align-items: center; backdrop-filter: blur(4px);">
    <div class="modal-content" style="background: white; max-width: 500px; width: 90%; border-radius: var(--radius, 12px); padding: 24px; position: relative; box-shadow: 0 10px 25px rgba(0,0,0,0.15); color: var(--text-main, #111827);">
        <span style="position: absolute; top: 12px; right: 16px; font-size: 22px; cursor: pointer; color: #a0aec0;" onclick="closeAdjustmentModal()">✕</span>
        <h3 style="font-size: 16px; font-weight: 700; text-transform: uppercase; margin-bottom: 15px;">Add Ledger Adjustments</h3>
        
        <div style="background: #f7fafc; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #e2e8f0;">
            <div style="display: flex; flex-direction: column; gap: 10px;">
                <div style="display: flex; gap: 8px;">
                    <select id="adjType" style="padding: 10px; font-size: 14px; width: 110px; border: 1px solid #d1d5db; border-radius: 6px;" onchange="handleTypeAutoPopulation()">
                        <option value="charge">Charge (+)</option>
                        <option value="discount">Discount (-)</option>
                    </select>
                    <input type="number" id="adjAmount" placeholder="Amount (₹)" style="padding: 10px; font-size: 14px; flex: 1; border: 1px solid #d1d5db; border-radius: 6px;" min="1">
                </div>
                <input type="text" id="adjReason" placeholder="Reason (e.g., Decoration, Property Damage, Extra Bed)" style="padding: 10px; font-size: 14px; border: 1px solid #d1d5db; border-radius: 6px;" autocomplete="off">
                <button type="button" class="btn btn-start" style="padding: 10px; font-size: 13px; font-weight: bold; border-radius: 6px; width: 100%;" onclick="addAdjustmentRow()">Insert Adjustment</button>
            </div>
        </div>

        <h4 style="font-size: 13px; font-weight: 700; text-transform: uppercase; margin-bottom: 10px; color: #4a5568;">Applied Ledger Alterations</h4>
        <div id="modalAdjustmentList" style="max-height: 25vh; overflow-y: auto; margin-bottom: 20px; border: 1px solid #e2e8f0; border-radius: 8px;">
            </div>

        <div style="display: flex; gap: 10px; justify-content: flex-end;">
            <button type="button" class="btn btn-log" style="padding: 10px 20px;" onclick="closeAdjustmentModal()">Cancel</button>
            <button type="button" class="btn btn-start" style="padding: 10px 20px;" onclick="applyAdjustmentsToBill()">Save & Apply changes</button>
        </div>
    </div>
</div>

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

<style>
/* ==========================================================================
   COMPACT BILLING VIEW & ALTERNATE ROW BACKGROUND STYLING
   ========================================================================== */
.compact-billing-container {
    max-width: 640px !important;
    margin: 0 auto !important;
    width: 100% !important;
}

.bill-table tbody tr:nth-child(even) {
    background-color: #f7fafc !important; 
}

.bill-table tbody tr:hover {
    background-color: #edf2f7 !important;
}

.item-timestamp-label {
    display: inline-block;
    color: #a0aec0;
    font-size: 11px;
    font-weight: 500;
    margin-top: 2px;
}
</style>

<div class="app-body">
    <div class="compact-billing-container">
        <div class="category-section">
            <h2 class="category-title" style="text-transform: none;">🧾 Settlements & Billing</h2>
        </div>

        <?php if(!$guest): ?>
            <div class="card"><p style="color: var(--text-muted); font-weight: 600;">No active resident ledger balances opened.</p></div>
        <?php else: 
        $items = [];
        try {
            $items = $pdo->query("SELECT oi.*, mi.name, mi.price, o.created_at 
                                  FROM order_items oi 
                                  JOIN menu_items mi ON oi.menu_item_id = mi.id 
                                  JOIN orders o ON oi.order_id = o.id 
                                  WHERE o.guest_id = ".intval($guest["id"]))->fetchAll();
        } catch (Exception $e) {
            $items = $pdo->query("SELECT oi.*, mi.name, mi.price 
                                  FROM order_items oi 
                                  JOIN menu_items mi ON oi.menu_item_id = mi.id 
                                  WHERE oi.order_id IN (SELECT id FROM orders WHERE guest_id = ".intval($guest["id"]).")")->fetchAll();
        }

        $subtotal = 0; 
        $consolidatedReceiptMap = [];
        ?>
            <div class="digital-receipt" style="padding: 0;">
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
                    <tbody id="billingTableBody">
                    <?php 
                    if (is_array($items)):
                        foreach($items as $i): 
                            $net = intval($i["quantity"] ?? 0) - intval($i["returned_qty"] ?? 0);
                            $is_cancelled = (intval($i["quantity"] ?? 0) <= 0);
                            
                            if ($is_cancelled) {
                                $cost = 0;
                            } else {
                                $item_unit_price = isset($i["price"]) ? floatval($i["price"]) : 0;
                                $cost = $net * $item_unit_price; 
                                $subtotal += $cost;
                            }
                            
                            if(!$is_cancelled && $net > 0) {
                                if (!isset($consolidatedReceiptMap[$i["menu_item_id"]])) {
                                    $consolidatedReceiptMap[$i["menu_item_id"]] = [
                                        "name" => $i["name"],
                                        "qty" => 0,
                                        "cost" => 0
                                    ];
                                }
                                $consolidatedReceiptMap[$i["menu_item_id"]]["qty"] += $net;
                                $consolidatedReceiptMap[$i["menu_item_id"]]["cost"] += $cost;
                            }
                            
                            $timestamp_raw = $i["created_at"] ?? ($i["order_time"] ?? null);
                            $timestamp_unix = !empty($timestamp_raw) ? strtotime($timestamp_raw) : time();
                            $formatted_time_string = date('d/m - H:i', $timestamp_unix);
                        ?>
                            <tr <?= $is_cancelled ? 'style="opacity: 0.6; background-color: #fff5f5 !important;"' : '' ?>>
                                <td>
                                    <div style="font-weight: 600; font-size: 13px; color: var(--text-main);">
                                        <?= htmlspecialchars($i["name"] ?? 'Unknown Item') ?> 
                                        <span style="color: var(--text-muted); font-size: 11px;">(x<?= max(0, $i["quantity"]) ?>)</span>
                                    </div>
                                    <div class="item-timestamp-label">- <?= $formatted_time_string ?></div>
                                    
                                    <?php if($is_cancelled): ?>
                                        <div style="color: var(--danger); font-size: 11px; font-weight: 700; margin-top: 2px;">⚠️ Cancelled</div>
                                    <?php elseif($i["returned_qty"] > 0): ?>
                                        <div style="color: var(--danger); font-size: 11px; font-weight: 700; margin-top: 2px;">↳ Returned: <?= $i["returned_qty"] ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($is_cancelled): ?>
                                        <span style="font-size: 11px; color: var(--text-muted); font-weight: 600;">No actions</span>
                                    <?php else: ?>
                                        <form method="POST" style="display: flex; gap: 4px; align-items: center; margin: 0;">
                                            <input type="hidden" name="order_item_id" value="<?= $i["id"] ?>">
                                            <input type="number" name="adjust_qty" value="1" max="<?= $net ?>" min="1" style="width: 40px; padding: 4px; margin: 0; font-size: 12px; text-align: center;">
                                            <select name="adjust_type" required style="padding: 4px 20px 4px 6px; margin: 0; font-size: 12px; width: 75px;">
                                                <option value="Return">Return</option>
                                                <option value="Cancel">Cancel</option>
                                            </select>
                                            <button type="submit" name="adjust_action" class="btn btn-start" style="padding: 5px 8px; font-size: 10px; flex: none; border-radius: 6px;">Apply</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right; font-weight: 700; font-size: 13px;">
                                    ₹<?= number_format($cost, 0) ?>
                                </td>
                            </tr>
                        <?php 
                        endforeach; 
                    endif;
                    ?>
                    
                    <tr id="adjustmentSeparatorRow" style="display:none;"><td colspan="3" style="border-top:2px dashed #cbd5e0; padding:0;"></td></tr>
                    
                    <tr>
                        <td colspan="2" style="padding: 15px 8px; text-align: left;">
                            <button type="button" class="btn btn-bill" style="max-width: 160px; padding: 8px 12px; font-size: 12px; font-weight: bold; border-radius: 6px; background: #edf2f7; color: #4a5568; border: 1px solid #cbd5e0;" onclick="openAdjustmentModal()">⚙ Adjustment</button>
                        </td>
                        <td></td>
                    </tr>

                    <tr class="bill-total-row">
                        <td colspan="2" style="font-weight: 800; padding: 12px 8px;">Total Balance Due:</td>
                        <td style="text-align: right; font-weight: 800; padding: 12px 8px;" id="billGrandTotalDisplay">₹<?= number_format($subtotal, 0) ?></td>
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
</div>

<script>
const cleanItems = <?php echo json_encode(array_values($consolidatedReceiptMap)); ?>;
const menuSubtotal = <?= $subtotal ?? 0 ?>;
let dynamicAdjustments = [];

// AUTOMATION ENGINE: Listens to dropdown selections and automatically populates values
function handleTypeAutoPopulation() {
    const typeDropdown = document.getElementById("adjType");
    const reasonField = document.getElementById("adjReason");
    
    if (typeDropdown.value === "discount") {
        reasonField.value = "Discount";
    } else {
        // Clear it if switching back to charge so they can input an exact custom parameters reason
        if (reasonField.value === "Discount") {
            reasonField.value = "";
        }
    }
}

function openAdjustmentModal() {
    renderModalAdjustments();
    // Fire checking sequence on opening configuration parameters initialization
    handleTypeAutoPopulation();
    document.getElementById("customAdjustmentModal").style.display = "flex";
}

function closeAdjustmentModal() {
    document.getElementById("customAdjustmentModal").style.display = "none";
}

function addAdjustmentRow() {
    const type = document.getElementById("adjType").value;
    const amount = parseFloat(document.getElementById("adjAmount").value) || 0;
    const reason = document.getElementById("adjReason").value.trim();

    if (amount <= 0) return alert("Please enter a valid amount value.");
    if (reason === "") return alert("Please clarify a brief description explanation parameters.");

    dynamicAdjustments.push({
        id: Date.now(),
        type: type,
        amount: amount,
        reason: reason
    });

    document.getElementById("adjAmount").value = "";
    document.getElementById("adjReason").value = "";
    // Reset type picker dropdown options configuration matrix maps back to index 0
    document.getElementById("adjType").value = "charge";
    renderModalAdjustments();
}

function removeAdjustmentItem(id) {
    dynamicAdjustments = dynamicAdjustments.filter(x => x.id !== id);
    renderModalAdjustments();
}

function renderModalAdjustments() {
    const list = document.getElementById("modalAdjustmentList");
    if (dynamicAdjustments.length === 0) {
        list.innerHTML = '<p style="color: #a0aec0; text-align: center; padding: 15px; font-size: 12px; margin:0;">No current adjustments typed.</p>';
        return;
    }

    list.innerHTML = dynamicAdjustments.map(item => `
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px; border-bottom: 1px solid #edf2f7; font-size: 13px;">
            <div style="text-align: left;">
                <span style="font-weight: 700; color: ${item.type === 'charge' ? '#e53e3e' : '#38a169'};">
                    [${item.type === 'charge' ? 'CHARGE' : 'DISCOUNT'}]
                </span> 
                <strong>₹${item.amount}</strong> - <span style="color:#4a5568;">${item.reason}</span>
            </div>
            <button type="button" style="background: none; border: none; color: #e53e3e; cursor: pointer; font-size: 14px; font-weight: bold;" onclick="removeAdjustmentItem(${item.id})">🗑️</button>
        </div>
    `).join('');
}

function applyAdjustmentsToBill() {
    document.querySelectorAll(".runtime-adj-row").forEach(el => el.remove());

    let totalModifications = 0;
    const separator = document.getElementById("adjustmentSeparatorRow");

    if (dynamicAdjustments.length === 0) {
        separator.style.display = "none";
        document.getElementById("billGrandTotalDisplay").innerText = "₹" + menuSubtotal.toLocaleString('en-IN');
        closeAdjustmentModal();
        return;
    }

    let rowsHtml = "";
    dynamicAdjustments.forEach(item => {
        const prefix = item.type === "charge" ? "+" : "-";
        const signVal = item.type === "charge" ? item.amount : -item.amount;
        totalModifications += signVal;

        rowsHtml += `
            <tr class="runtime-adj-row" style="background: #fdfbf7;">
                <td style="font-size: 12px; font-weight: 600; color: #4a5568;">
                    ✨ Layout Adjust: <span style="color: ${item.type === 'charge' ? '#e53e3e' : '#38a169'}; font-weight:700;">${item.reason}</span>
                </td>
                <td style="text-align: center; font-size: 11px; color: #a0aec0; font-weight:600;">Custom Insertion</td>
                <td style="text-align: right; font-weight: 700; font-size: 13px; color: ${item.type === 'charge' ? '#e53e3e' : '#38a169'};">
                    ${prefix}₹${item.amount.toLocaleString('en-IN')}
                </td>
            </tr>
        `;
    });

    separator.insertAdjacentHTML('beforebegin', rowsHtml);
    separator.style.display = "table-row";

    const netFinalBillTotal = Math.max(0, menuSubtotal + totalModifications);
    document.getElementById("billGrandTotalDisplay").innerText = "₹" + netFinalBillTotal.toLocaleString('en-IN');
    
    closeAdjustmentModal();
}

function openCleanBillPopup() {
    const itemsContainer = document.getElementById("popupReceiptItems");
    itemsContainer.innerHTML = "";
    
    cleanItems.forEach(item => {
        itemsContainer.innerHTML += `
            <div style="display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 13px;">
                <span>${item.name} x${item.qty}</span>
                <span>₹${item.cost}</span>
            </div>
        `;
    });

    let cumulativeSum = menuSubtotal;

    dynamicAdjustments.forEach(item => {
        const sign = item.type === "charge" ? "+" : "-";
        const numeric = item.type === "charge" ? item.amount : -item.amount;
        cumulativeSum += numeric;

        itemsContainer.innerHTML += `
            <div style="display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 12px; color: #4a5568; font-style: italic;">
                <span>↳ ${item.reason}</span>
                <span>${sign}₹${item.amount}</span>
            </div>
        `;
    });

    const absoluteFinalNetValue = Math.max(0, cumulativeSum);
    document.getElementById("popupReceiptTotal").innerText = "Total: ₹" + absoluteFinalNetValue;
    document.getElementById("receiptShareModal").style.display = "flex";
}
</script>
<?php include "includes/footer.php"; ?>