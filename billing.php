<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "config/db.php";
// require_once "config/google_sheets_bridge.php"; // Include your native cURL Google Sheet sync engine
include_once __DIR__ . '/config/local_db_bridge.php';
if (!isset($_SESSION["user_id"])) { 
    header("Location: login.php"); 
    exit; 
}

// 1. Identify the single active running resident profile session
$guest = $pdo->query("SELECT * FROM guests WHERE status = 'Active' LIMIT 1")->fetch();

// Handle direct item quantity reductions (Returns / Cancellations)
if ($guest && $_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["adjust_action"])) {
    $id = intval($_POST["order_item_id"]); 
    $qty = intval($_POST["adjust_qty"]);
    if ($_POST["adjust_type"] === "Cancel") { 
        $pdo->prepare("UPDATE order_items SET quantity = quantity - ? WHERE id = ?")->execute([$qty, $id]); 
    } else if ($_POST["adjust_type"] === "Return") { 
        $pdo->prepare("UPDATE order_items SET returned_qty = returned_qty + ? WHERE id = ?")->execute([$qty, $id]); 
    }
    header("Location: billing.php"); 
    exit;
}

// --- HANDLE POST: ASYNC ADDITION OF MISSED ITEMS BY STAFF ---
if ($guest && $_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action_add_missed_item_async"])) {
    header('Content-Type: application/json');
    $menu_item_id = intval($_POST["missed_menu_item_id"]);
    $quantity = intval($_POST["missed_item_qty"] ?? 1);

    if ($menu_item_id > 0 && $quantity > 0) {
        $pdo->beginTransaction();
        try {
            $insertOrder = $pdo->prepare("INSERT INTO orders (guest_id, order_time, status) VALUES (?, CURRENT_TIMESTAMP, 'Pending')");
            $insertOrder->execute([$guest['id']]);
            $new_order_id = $pdo->lastInsertId();

            $insertItem = $pdo->prepare("INSERT INTO order_items (order_id, menu_item_id, quantity, returned_qty, special_instructions, item_status, is_complimentary) VALUES (?, ?, ?, 0, '', 'Served', 0)");
            $insertItem->execute([$new_order_id, $menu_item_id, $quantity]);
            $pdo->commit();

            echo json_encode(['success' => true]);
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }
    echo json_encode(['success' => false, 'error' => 'Invalid structural fields.']);
    exit;
}

// 2. PROCESS TOTAL COMPLETED TICKET CLOSURE & GOOGLE SHEET WORKBOOK APPEND MATCH
if ($guest && isset($_POST["close_billing_ledger"])) {
    $pdo->beginTransaction();
    try {
        // Calculate stay timelines
        $checkin = new DateTime($guest['checkin_date']);
        $checkout = new DateTime($guest['expected_checkout'] ?? date('Y-m-d'));
        $total_days = max(1, $checkin->diff($checkout)->days);

        // Dynamic Total Charge directly mapped from dynamic registration variables
        $total_room_charge = floatval($guest['total_charge'] ?? 0);
        $advance = floatval($guest['advance_paid'] ?? 0);

        // Fetch cumulative final food value
        $foodItems = $pdo->query("SELECT oi.quantity, oi.returned_qty, mi.price 
                                  FROM order_items oi 
                                  JOIN menu_items mi ON oi.menu_item_id = mi.id 
                                  JOIN orders o ON oi.order_id = o.id 
                                  WHERE o.guest_id = " . intval($guest["id"]))->fetchAll();
        
        $final_food_total = 0;
        foreach ($foodItems as $f) {
            $net_qty = intval($f['quantity']) - intval($f['returned_qty']);
            if ($net_qty > 0) {
                $final_food_total += ($net_qty * floatval($f['price']));
            }
        }

        // Apply runtime dynamic adjustment parameters (Charges/Discounts adjustments)
        $session_adjustments_json = $_POST['applied_adjustments_payload'] ?? '[]';
        $adjustments = json_decode($session_adjustments_json, true);
        $extra_decorations = 0;
        
        foreach ($adjustments as $adj) {
            $sign = ($adj['type'] === 'charge') ? 1 : -1;
            if (stripos($adj['reason'], 'decoration') !== false) {
                $extra_decorations += ($adj['amount'] * $sign);
            } else {
                $final_food_total += ($adj['amount'] * $sign);
            }
        }

        $final_pending_due = max(0, ($total_room_charge + $final_food_total + $extra_decorations) - $advance);

        // Update local status records inside MySQL database
        $pdo->prepare("UPDATE guests SET status = 'CheckedOut', pending_amount = ? WHERE id = ?")->execute([$final_pending_due, $guest['id']]);
        $pdo->prepare("UPDATE orders SET status = 'Completed' WHERE guest_id = ? AND status = 'Pending'")->execute([$guest['id']]);
        
        // COMPILE AND PUSH EXCEL WORKBOOK MATRIX DATA TO THE CLOUD
        // Sheet Tab Name Target: "Farm booking and food"
        // Target Layout Columns Order Match: [Sr No, Booking Source, Contact No, No. of Guest, Check-in Date, Check-out Date, Total Days, Per Night Charges, Total Charge, Remark, Advance Paid, Received By, Pending Amount, Received By, Total Food, Received by .1, Remark.1, Decoration, Tip]
        $workbookRow = [
            '', 
            htmlspecialchars($guest['booking_source'] ?? 'Offline'), 
            htmlspecialchars($guest['phone_number'] ?? 'N/A'), 
            intval($guest['no_of_guests'] ?? 1), 
            $guest['checkin_date'], 
            date('Y-m-d'), 
            $total_days, 
            'Variable', 
            $total_room_charge, 
            'Checked out from POS Terminal', 
            $advance, 
            'System Ledger', 
            $final_pending_due, 
            htmlspecialchars($_SESSION["username"]), 
            $final_food_total, 
            htmlspecialchars($_SESSION["username"]), 
            'Automatic Sheet Update', 
            $extra_decorations, 
            '0' 
        ];
        
        appendRowToGoogleSheet('Farm booking and food', $workbookRow);

        $pdo->commit();
        header("Location: index.php");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
    }
}

$menu_catalog_list = $pdo->query("SELECT id, name, price FROM menu_items WHERE is_hidden = 0 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

include "includes/header.php";
?>

<div id="customAdjustmentModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 99999; justify-content: center; align-items: center; backdrop-filter: blur(4px);">
    <div class="modal-content" style="background: white; max-width: 500px; width: 90%; border-radius: 12px; padding: 24px; position: relative; color: #111827;">
        <span style="position: absolute; top: 12px; right: 16px; font-size: 22px; cursor: pointer; color: #a0aec0;" onclick="window.closeAdjustmentModal()">✕</span>
        <h3 style="font-size: 14px; font-weight: 700; text-transform: uppercase; margin-bottom: 15px;">Add Ledger Adjustments</h3>
        
        <div style="background: #f7fafc; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #e2e8f0;">
            <div style="display: flex; flex-direction: column; gap: 10px;">
                <div style="display: flex; gap: 8px;">
                    <select id="adjType" style="padding: 10px; font-size: 14px; width: 110px; border: 1px solid #d1d5db; border-radius: 6px;" onchange="window.handleTypeAutoPopulation()">
                        <option value="charge">Charge (+)</option>
                        <option value="discount">Discount (-)</option>
                    </select>
                    <input type="number" id="adjAmount" placeholder="Amount (₹)" style="padding: 10px; font-size: 14px; flex: 1; border: 1px solid #d1d5db; border-radius: 6px;" min="1">
                </div>
                <input type="text" id="adjReason" placeholder="Reason (e.g., Decoration, Extra Bed, Damage)" style="padding: 10px; font-size: 14px; border: 1px solid #d1d5db; border-radius: 6px;" autocomplete="off">
                <button type="button" class="btn btn-start" style="padding: 10px; font-size: 13px; font-weight: bold; border-radius: 6px; width: 100%;" onclick="window.addAdjustmentRow()">Insert Adjustment</button>
            </div>
        </div>

        <h4 style="font-size: 12px; font-weight: 700; text-transform: uppercase; margin-bottom: 10px; color: #4a5568;">Applied Ledger Alterations</h4>
        <div id="modalAdjustmentList" style="max-height: 25vh; overflow-y: auto; margin-bottom: 20px; border: 1px solid #e2e8f0; border-radius: 8px;"></div>

        <div style="display: flex; gap: 10px; justify-content: flex-end;">
            <button type="button" class="btn btn-log" style="padding: 10px 20px;" onclick="window.closeAdjustmentModal()">Cancel</button>
            <button type="button" class="btn btn-start" style="padding: 10px 20px;" onclick="window.applyAdjustmentsToBill()">Save & Apply changes</button>
        </div>
    </div>
</div>

<div id="receiptShareModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 99999; justify-content: center; align-items: center; backdrop-filter: blur(4px);">
    <div class="modal-content" style="max-width: 420px; background: white; padding: 20px; border-radius: 12px; font-family: monospace;">
        <span style="float: right; cursor: pointer; font-size: 20px; color: #a0aec0;" onclick="document.getElementById('receiptShareModal').style.display='none'">✕</span>
        <h3 style="text-align: center; margin-bottom: 4px;">RECEIPT</h3>
        <p style="text-align: center; font-size: 11px; color: #718096; margin-bottom: 15px;">Date: <?= date('d-m-Y H:i') ?></p>
        <div style="border-bottom: 1px dashed #cbd5e0; padding-bottom: 8px; margin-bottom: 12px; text-align: left;">
            Guest Name: <strong><?= $guest ? htmlspecialchars($guest["guest_name"]) : '' ?></strong>
        </div>
        <div id="popupReceiptItems"></div>
        <div id="popupReceiptTotal" style="text-align: right; font-weight: 700; font-size: 15px; margin-top: 15px; padding-top: 10px; border-top: 1px dashed #cbd5e0;"></div>
        <div style="margin-top: 20px; display: flex; gap: 8px; justify-content: flex-end;">
            <button class="btn" style="background: #4a5568; max-width: 80px; color: white;" onclick="window.print()">Print</button>
            <button class="btn" style="background: #e2e8f0; max-width: 80px; color: #111827;" onclick="document.getElementById('receiptShareModal').style.display='none'">Close</button>
        </div>
    </div>
</div>

<style>
.compact-billing-container { max-width: 680px !important; margin: 0 auto !important; width: 100% !important; }
.bill-table tbody tr:nth-child(even) { background-color: #f7fafc !important; }
.bill-section-header-row { background: #edf2f7 !important; font-weight: 700; color: #2d3748; font-size: 12px; text-transform: uppercase; }
.financial-summary-box { background: #f8fafc; border: 1px solid #cbd5e0; border-radius: 8px; padding: 15px; margin-top: 15px; text-align: left; }
.financial-row-line { display: flex; justify-content: space-between; font-size: 13px; padding: 6px 0; border-bottom: 1px dashed #edf2f7; }
.missed-items-box { background: #fff; border: 1px solid #cbd5e0; border-radius: 12px; padding: 20px; margin-top: 25px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); text-align: left; }
</style>

<div class="app-body">
    <div class="compact-billing-container">
        <div class="category-section">
            <h2 class="category-title" style="text-transform: none;">🧾 Final Settlements & Booking Billing</h2>
        </div>

        <?php if(!$guest): ?>
            <div class="card"><p style="color: var(--text-muted); font-weight: 600;">No active resident ledger balances opened.</p></div>
        <?php else: 
        $items = $pdo->query("SELECT oi.*, mi.name, mi.price, o.order_time as created_at 
                              FROM order_items oi 
                              JOIN menu_items mi ON oi.menu_item_id = mi.id 
                              JOIN orders o ON oi.order_id = o.id 
                              WHERE o.guest_id = ".intval($guest["id"]))->fetchAll();

        $food_subtotal = 0; 
        $consolidatedReceiptMap = [];
        
        $room_subtotal = floatval($guest['total_charge'] ?? 0);
        $days_calc = max(1, (strtotime(date('Y-m-d')) - strtotime($guest['checkin_date'])) / 86400);
        ?>
            <div class="digital-receipt" style="padding: 0;">
                <h4 style="text-align: center; font-size: 14px; margin-bottom: 4px; text-transform: uppercase;">Active Settlement Profile Summary</h4>
                <p style="text-align: center; font-size: 12px; color: var(--text-muted); margin-bottom: 20px;">Resident Name: <strong><?= htmlspecialchars($guest["guest_name"]) ?></strong> • Source: <?= htmlspecialchars($guest["booking_source"] ?? 'Offline') ?></p>
                
                <table class="bill-table" style="margin-bottom: 15px;">
                    <thead>
                        <tr>
                            <th>Item details Description</th>
                            <th style="text-align: center; width: 120px;">Adjustments</th>
                            <th style="text-align: right; width: 80px;">Amount</th>
                        </tr>
                    </thead>
                    <tbody id="billingTableBody">
                        
                        <tr class="bill-section-header-row"><td colspan="3">🏠 Accommodation Charges Overview</td></tr>
                        <tr>
                            <td>
                                <div style="font-weight:600; font-size:13px;">Total Room Booking Cost</div>
                                <div style="font-size:11px; color:#718096;">Stay Timeline: <?= $guest['checkin_date'] ?> to <?= date('Y-m-d') ?> (<?= $days_calc ?> Nights)</div>
                            </td>
                            <td style="text-align:center; font-size:11px; color:#a0aec0;">Pre-Calculated</td>
                            <td style="text-align:right; font-weight:700;">₹<?= number_format($room_subtotal, 0) ?></td>
                        </tr>

                        <tr class="bill-section-header-row"><td colspan="3">🍽️ Restaurant & Kitchen Invoice Details</td></tr>
                        <?php 
                        foreach($items as $i): 
                            $net = intval($i["quantity"]) - intval($i["returned_qty"]);
                            $is_cancelled = (intval($i["quantity"]) <= 0);
                            $cost = $is_cancelled ? 0 : ($net * floatval($i["price"]));
                            if(!$is_cancelled) { 
                                $food_subtotal += $cost; 
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
                        ?>
                            <tr <?= $is_cancelled ? 'style="opacity: 0.6; background-color: #fff5f5 !important;"' : '' ?>>
                                <td>
                                    <div style="font-weight: 600; font-size: 13px;">
                                        <?= htmlspecialchars($i["name"]) ?> <span style="color: var(--text-muted); font-size: 11px;">(x<?= max(0, $i["quantity"]) ?>)</span>
                                    </div>
                                    <div style="color: #a0aec0; font-size: 11px;">- <?= date('d M - H:i', strtotime($i['created_at'])) ?></div>
                                </td>
                                <td>
                                    <?php if($is_cancelled): ?>
                                        <span style="font-size: 11px; color: var(--text-muted); font-weight: 600;">No actions</span>
                                    <?php else: ?>
                                        <form method="POST" style="display: flex; gap: 4px; align-items: center; margin: 0;">
                                            <input type="hidden" name="order_item_id" value="<?= $i["id"] ?>">
                                            <input type="number" name="adjust_qty" value="1" max="<?= $net ?>" min="1" style="width: 40px; padding: 4px; margin: 0; font-size: 12px; text-align: center;">
                                            <select name="adjust_type" required style="padding: 4px 6px; margin: 0; font-size: 12px; width: 75px;">
                                                <option value="Return">Return</option>
                                                <option value="Cancel">Cancel</option>
                                            </select>
                                            <button type="submit" name="adjust_action" class="btn btn-start" style="padding: 5px 8px; font-size: 10px; flex: none; border-radius: 6px;">Apply</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right; font-weight: 700;">₹<?= number_format($cost, 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <tr id="adjustmentSeparatorRow" style="display:none;"><td colspan="3" style="border-top:2px dashed #cbd5e0; padding:0;"></td></tr>
                        
                        <tr>
                            <td colspan="2" style="padding: 15px 8px;">
                                <button type="button" class="btn btn-bill" style="max-width: 150px; padding: 8px; font-size: 12px; font-weight: bold; background: #edf2f7; color: #4a5568; border: 1px solid #cbd5e0; border-radius:6px;" onclick="window.openAdjustmentModal()">⚙ Add Extra / Discount</button>
                            </td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>

                <div class="financial-summary-box">
                    <h4 style="font-size:12px; text-transform:uppercase; margin-top:0; margin-bottom:10px; color:#334155; border-bottom:1px solid #e2e8f0; padding-bottom:4px;">Relational Financial Balance Ledger</h4>
                    <div class="financial-row-line"><span>Total Room Booking Charges:</span><strong>₹<?= number_format($room_subtotal, 0) ?></strong></div>
                    <div class="financial-row-line"><span>Total Kitchen Service Outlays:</span><strong id="summaryFoodSubtotal">₹<?= number_format($food_subtotal, 0) ?></strong></div>
                    <div class="financial-row-line" style="color:#059669;"><span>Deduction: Advance Security Deposit Paid:</span><strong>-₹<?= number_format($guest['advance_paid'], 0) ?></strong></div>
                    <div class="financial-row-line" style="font-size:15px; font-weight:800; border-top:2px solid #cbd5e0; padding-top:10px; margin-top:5px; color:#b91c1c;">
                        <span>Net Settled Amount Payable:</span>
                        <span id="billGrandTotalDisplay">₹<?= number_format(max(0, ($room_subtotal + $food_subtotal) - $guest['advance_paid']), 0) ?></span>
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="button" class="btn btn-log" style="font-size: 12px; padding: 12px;" onclick="window.openCleanBillPopup()">View Clean Bill</button>
                    <form method="POST" style="flex: 1; margin: 0;" onsubmit="return confirm('Archive statement and complete checkout?');">
                        <input type="hidden" name="applied_adjustments_payload" id="hiddenAdjustmentsPayload" value="[]">
                        <button type="submit" name="close_billing_ledger" class="btn btn-end" style="font-size: 13px; padding: 14px; width: 100%; font-weight:700; background:#059669; border-color:#059669; border-radius:8px;">✔ Finalize Checkout & Sync to Google Sheets</button>
                    </form>
                </div>
            </div>

            <div class="missed-items-box">
                <h3 style="font-size: 13px; font-weight: 700; text-transform: uppercase; margin-bottom: 12px; color: #111827; border-bottom: 1px dashed #cbd5e0; padding-bottom: 6px;">➕ Add Missed Food Item / Dish</h3>
                <form id="asyncMissedItemAdditionForm" style="margin: 0; display: grid; grid-template-columns: 2fr 80px 1fr; gap: 10px; align-items: end;">
                    <input type="hidden" name="action_add_missed_item_async" value="1">
                    <div>
                        <label style="font-size: 11px; font-weight: 700; color: #4b5563; display: block; margin-bottom: 4px;">Select Dish</label>
                        <select name="missed_menu_item_id" id="asyncSelectDish" required style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #cbd5e0; background: #fff; font-size: 13px;">
                            <option value="">-- Choose Menu Selection --</option>
                            <?php foreach ($menu_catalog_list as $dish): ?>
                                <option value="<?= $dish['id'] ?>"><?= htmlspecialchars($dish['name']) ?> (₹<?= number_format($dish['price'], 0) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="font-size: 11px; font-weight: 700; color: #4b5563; display: block; margin-bottom: 4px;">Qty</label>
                        <input type="number" name="missed_item_qty" id="asyncInputQty" value="1" min="1" required style="width: 100%; padding: 9px; border-radius: 6px; border: 1px solid #cbd5e0; text-align: center; box-sizing: border-box; font-size: 13px;">
                    </div>
                    <button type="submit" class="btn btn-start" style="padding: 11px; font-size: 12px; font-weight: bold; border-radius: 6px; width: 100%;">Add to Bill (AJAX)</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
window.cleanItems = <?php echo json_encode(array_values($consolidatedReceiptMap ?? [])); ?>;
window.menuSubtotal = <?= ($room_subtotal + $food_subtotal) ?>;
window.advanceCredit = <?= floatval($guest['advance_paid'] ?? 0) ?>;
window.dynamicAdjustments = [];

document.getElementById('asyncMissedItemAdditionForm')?.addEventListener('submit', function(event) {
    event.preventDefault();
    const formData = new FormData(this);
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.innerText = "Saving...";
    submitBtn.disabled = true;

    fetch('billing.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if(data.success) { location.reload(); } 
        else {
            alert('❌ DB Entry Failed: ' + data.error);
            submitBtn.innerText = "Add to Bill (AJAX)";
            submitBtn.disabled = false;
        }
    }).catch(() => {
        alert('❌ Network pipeline break exception error.');
        submitBtn.innerText = "Add to Bill (AJAX)";
        submitBtn.disabled = false;
    });
});

window.handleTypeAutoPopulation = function() {
    const typeDropdown = document.getElementById("adjType");
    const reasonField = document.getElementById("adjReason");
    if (typeDropdown.value === "discount") { reasonField.value = "Discount"; }
    else if (reasonField.value === "Discount") { reasonField.value = ""; }
};

window.openAdjustmentModal = function() {
    window.renderModalAdjustments();
    window.handleTypeAutoPopulation();
    document.getElementById("customAdjustmentModal").style.display = "flex";
};

window.closeAdjustmentModal = function() {
    document.getElementById("customAdjustmentModal").style.display = "none";
};

window.addAdjustmentRow = function() {
    const type = document.getElementById("adjType").value;
    const amount = parseFloat(document.getElementById("adjAmount").value) || 0;
    const reason = document.getElementById("adjReason").value.trim();

    if (amount <= 0) return alert("Please enter a valid value.");
    if (reason === "") return alert("Please type a clear reason descriptor.");

    window.dynamicAdjustments.push({ id: Date.now(), type: type, amount: amount, reason: reason });
    document.getElementById("adjAmount").value = "";
    document.getElementById("adjReason").value = "";
    document.getElementById("adjType").value = "charge";
    window.renderModalAdjustments();
};

window.removeAdjustmentItem = function(id) {
    window.dynamicAdjustments = window.dynamicAdjustments.filter(x => x.id !== id);
    window.renderModalAdjustments();
};

window.renderModalAdjustments = function() {
    const list = document.getElementById("modalAdjustmentList");
    if (window.dynamicAdjustments.length === 0) {
        list.innerHTML = '<p style="color:#a0aec0; text-align:center; padding:15px; font-size:12px; margin:0;">No variations attached.</p>';
        return;
    }
    list.innerHTML = window.dynamicAdjustments.map(item => `
        <div style="display:flex; justify-content:space-between; align-items:center; padding:10px; border-bottom:1px solid #edf2f7; font-size:13px;">
            <div><span style="font-weight:700; color:${item.type === 'charge' ? '#e53e3e' : '#38a169'};">[${item.type.toUpperCase()}]</span> <strong>₹${item.amount}</strong> - ${item.reason}</div>
            <button type="button" style="background:none; border:none; color:#e53e3e; font-weight:bold; cursor:pointer;" onclick="window.removeAdjustmentItem(${item.id})">🗑️</button>
        </div>
    `).join('');
};

window.applyAdjustmentsToBill = function() {
    document.querySelectorAll(".runtime-adj-row").forEach(el => el.remove());
    const separator = document.getElementById("adjustmentSeparatorRow");
    
    let modificationsSum = 0;
    if (window.dynamicAdjustments.length === 0) {
        separator.style.display = "none";
        document.getElementById("billGrandTotalDisplay").innerText = "₹" + Math.max(0, window.menuSubtotal - window.advanceCredit).toLocaleString('en-IN');
        document.getElementById("hiddenAdjustmentsPayload").value = "[]";
        window.closeAdjustmentModal();
        return;
    }

    let rowsHtml = "";
    window.dynamicAdjustments.forEach(item => {
        const prefix = item.type === "charge" ? "+" : "-";
        modificationsSum += (item.type === "charge" ? item.amount : -item.amount);
        rowsHtml += `
            <tr class="runtime-adj-row" style="background:#fdfbf7;">
                <td><span style="font-weight:600; color:#4a5568;">✨ Modification Adjustment: ${item.reason}</span></td>
                <td style="text-align:center; font-size:11px; color:#a0aec0;">Manual Entry</td>
                <td style="text-align:right; font-weight:700; color:${item.type === 'charge' ? '#e53e3e' : '#38a169'};">${prefix}₹${item.amount}</td>
            </tr>
        `;
    });

    separator.insertAdjacentHTML('beforebegin', rowsHtml);
    separator.style.display = "table-row";

    document.getElementById("hiddenAdjustmentsPayload").value = JSON.stringify(window.dynamicAdjustments);
    const absoluteFinalTotal = Math.max(0, (window.menuSubtotal + modificationsSum) - window.advanceCredit);
    document.getElementById("billGrandTotalDisplay").innerText = "₹" + absoluteFinalTotal.toLocaleString('en-IN');
    window.closeAdjustmentModal();
};

window.openCleanBillPopup = function() {
    const itemsContainer = document.getElementById("popupReceiptItems"); 
    itemsContainer.innerHTML = "";
    
    window.cleanItems.forEach(item => { 
        itemsContainer.innerHTML += `<div style="display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 13px;"><span>${item.name} x${item.qty}</span><span>₹${item.cost}</span></div>`; 
    });
    
    let cumulativeSum = window.menuSubtotal;
    window.dynamicAdjustments.forEach(item => {
        const sign = item.type === "charge" ? "+" : "-";
        cumulativeSum += (item.type === "charge" ? item.amount : -item.amount);
        itemsContainer.innerHTML += `<div style="display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 12px; color: #4a5568; font-style: italic;"><span>↳ ${item.reason}</span><span>${sign}₹${item.amount}</span></div>`;
    });
    
    document.getElementById("popupReceiptTotal").innerText = "Total Payable: ₹" + Math.max(0, cumulativeSum - window.advanceCredit);
    document.getElementById("receiptShareModal").style.display = "flex";
};
</script>
<?php include "includes/footer.php"; ?>