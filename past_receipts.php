<?php
// /home/apartment/artistsfarmjaipur.com/Order/past_receipts.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once "config/db.php";

if (!isset($_SESSION["role"]) || ($_SESSION["role"] !== "Admin" && $_SESSION["role"] !== "Super Admin")) {
    die("Access Denied: Administrative credentials required.");
}

// --- MASTER CONTROLLER: POST ACTION UPDATE SYSTEM INTERCEPTOR ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action_update_past_bill"])) {
    $guest_id = intval($_POST["update_guest_id"]);
    $base_room_rent = floatval($_POST["base_room_rent"] ?? 0);
    $advance_paid = floatval($_POST["advance_paid"] ?? 0);
    $advance_received_by = trim($_POST["advance_received_by"] ?? '');
    $food_received_by = trim($_POST["food_received_by"] ?? '');
    
    // Process updated food quantities
    $item_qtys = $_POST["invoice_item_qty"] ?? [];
    
    $pdo->beginTransaction();
    try {
        // 1. Update individual food line items
        foreach ($item_qtys as $item_id => $qty) {
            $item_id = intval($item_id);
            $qty = intval($qty);
            if ($qty <= 0) {
                $pdo->prepare("DELETE FROM order_items WHERE id = ?")->execute([$item_id]);
            } else {
                $pdo->prepare("UPDATE order_items SET quantity = ? WHERE id = ?")->execute([$qty, $item_id]);
            }
        }

        // 2. Process active adjustments update string safely
        $adjustments_payload = $_POST["applied_adjustments_payload"] ?? '[]';
        
        // Recalculate totals for verification
        $itemsStmt = $pdo->prepare("
            SELECT oi.quantity, oi.returned_qty, mi.price 
            FROM order_items oi 
            JOIN menu_items mi ON oi.menu_item_id = mi.id 
            JOIN orders o ON oi.order_id = o.id 
            WHERE o.guest_id = ?
        ");
        $itemsStmt->execute([$guest_id]);
        $food_lines = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $food_subtotal = 0;
        foreach ($food_lines as $f) {
            $food_subtotal += (intval($f['quantity']) - intval($f['returned_qty'])) * floatval($f['price']);
        }
        
        $adjustments = json_decode($adjustments_payload, true) ?: [];
        foreach ($adjustments as $adj) {
            $factor = ($adj['type'] === 'charge') ? 1 : -1;
            $food_subtotal += (floatval($adj['amount']) * $factor);
        }
        $food_subtotal = max(0, $food_subtotal);
        $pending_amount = max(0, $base_room_rent - $advance_paid);

        // 3. Persist modifications down to guest ledger metrics
        $updateGuest = $pdo->prepare("
            UPDATE guests 
            SET base_room_rent = ?,
                advance_paid = ?,
                advance_received_by = ?,
                pending_amount = ?,
                total_food = ?,
                food_received_by = ?,
                food_remark = ?
            WHERE id = ?
        ");
        $updateGuest->execute([
            $base_room_rent, 
            $advance_paid, 
            $advance_received_by, 
            $pending_amount, 
            $food_subtotal, 
            $food_received_by, 
            $adjustments_payload, 
            $guest_id
        ]);

        $pdo->commit();
        $_SESSION['invoice_success_toast'] = "Historical bill metrics saved and recalculated successfully!";
        header("Location: past_receipts.php");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error updating historical record: " . $e->getMessage());
    }
}

// Fetch Checked Out records generated via billing.php system workflows
$invoices = $pdo->query("
    SELECT g.*,
           COALESCE((SELECT SUM((oi.quantity - oi.returned_qty) * mi.price) 
                     FROM order_items oi 
                     JOIN menu_items mi ON oi.menu_item_id = mi.id 
                     JOIN orders o ON oi.order_id = o.id 
                     WHERE o.guest_id = g.id), 0) as food_base_subtotal
    FROM guests g 
    WHERE g.status = 'CheckedOut'
    ORDER BY g.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

include "includes/header.php";
?>

<style>
.receipts-dashboard-card { background: #ffffff; border: 1px solid #cbd5e0; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
.receipts-table { width: 100%; border-collapse: collapse; font-size: 13px; text-align: left; }
.receipts-table th { background: #f8fafc; padding: 12px; font-weight: 700; color: #475569; border-bottom: 2px solid #cbd5e0; }
.receipts-table td { padding: 12px; border-bottom: 1px solid #e2e8f0; color: #1e293b; vertical-align: middle; }
.modal-split-layout { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 10px; }
.modal-input-group { margin-bottom: 12px; }
.modal-input-group label { font-size: 11px; font-weight: 700; display: block; color: #475569; margin-bottom: 4px; text-transform: uppercase; }
.modal-field-control { width: 100%; padding: 8px; border: 1px solid #cbd5e0; border-radius: 6px; box-sizing: border-box; font-size: 13px; font-weight: 600; }
.modal-item-edit-row { display: flex; justify-content: space-between; align-items: center; padding: 8px; border-bottom: 1px solid #edf2f7; font-size: 12px; }
.modal-qty-field { width: 55px; padding: 5px; text-align: center; border: 1px solid #cbd5e0; border-radius: 4px; font-weight: bold; }
.adj-badge-del { background: none; border: none; color: #ef4444; cursor: pointer; font-weight: bold; font-size: 12px; }
</style>

<div class="app-body" style="padding: 20px; font-family: sans-serif; text-align: left;">
    
    <?php if (isset($_SESSION['invoice_success_toast'])): ?>
        <div style="padding: 12px 24px; background: #10b981; color: white; font-size: 14px; font-weight: 800; text-align: center; border-radius: 8px; margin-bottom: 15px;">
            📢 <strong><?= htmlspecialchars($_SESSION['invoice_success_toast']) ?></strong>
        </div>
    <?php unset($_SESSION['invoice_success_toast']); endif; ?>

    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px;">
        <div>
            <h2 style="margin:0; color:#1e293b;">📜 Past Receipts Archive Log</h2>
            <p style="color:#64748b; margin: 5px 0 0 0; font-size:0.9rem;">Review or modify entire historical billing sheets in one unified workspace</p>
        </div>
        <input type="text" id="invoiceSearchInput" onkeyup="searchInvoiceTable()" placeholder="🔍 Search Guest Phone/Name..." style="padding:10px; border:1px solid #cbd5e0; border-radius:8px; font-size:13px; max-width: 280px; width:100%;">
    </div>

    <div class="receipts-dashboard-card">
        <table class="receipts-table">
            <thead>
                <tr>
                    <th>Settlement Date</th>
                    <th>Guest Mapping</th>
                    <th>Accommodation (₹)</th>
                    <th>Food & Extras (₹)</th>
                    <th>Grand Total (₹)</th>
                    <th style="text-align:center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($invoices)): foreach ($invoices as $inv): 
                    $room_rent = floatval($inv['base_room_rent'] ?? 0);
                    $food_total = floatval($inv['total_food'] ?? 0);
                    $grand_total = $room_rent + $food_total;
                    $display_date = !empty($inv['checkout_date']) ? date('d M Y', strtotime($inv['checkout_date'])) : 'Stay Profile';

                    // Fetch associated order item details line matrix
                    $itemsStmt = $pdo->prepare("
                        SELECT oi.id, oi.quantity, mi.name, mi.price 
                        FROM order_items oi 
                        JOIN menu_items mi ON oi.menu_item_id = mi.id 
                        JOIN orders o ON oi.order_id = o.id 
                        WHERE o.guest_id = ?
                    ");
                    $itemsStmt->execute([$inv['id']]);
                    $serializedItems = json_encode($itemsStmt->fetchAll(PDO::FETCH_ASSOC));
                ?>
                    <tr class="invoice-data-row" data-search-string="<?= strtolower(htmlspecialchars($inv['guest_name']) . ' ' . htmlspecialchars($inv['phone_number'])) ?>">
                        <td style="font-weight: bold; color: #475569;"><?= $display_date ?></td>
                        <td style="font-weight: 600; color:#334155;">
                            <?= htmlspecialchars($inv['guest_name']) ?> 
                            <span style="font-family:monospace; display:block; font-size:11px; color:#64748b;">📱 <?= htmlspecialchars($inv['phone_number']) ?></span>
                        </td>
                        <td>₹<?= number_format($room_rent, 2) ?></td>
                        <td>₹<?= number_format($food_total, 2) ?></td>
                        <td style="font-weight: 800; color: #059669;">₹<?= number_format($grand_total, 2) ?></td>
                        <td style="text-align: center;">
                            <button type="button" class="btn btn-start" style="padding: 6px 14px; font-size:12px; border-radius:6px;"
                                    data-items='<?= htmlspecialchars($serializedItems, ENT_QUOTES, 'UTF-8') ?>' 
                                    data-adjustments='<?= htmlspecialchars($inv['food_remark'] ?? '[]', ENT_QUOTES, 'UTF-8') ?>'
                                    onclick="openEditInvoiceWorkspace(<?= json_encode($inv) ?>, this)">✏ Edit Bill</button>
                        </td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="6" style="text-align:center; padding:30px; color:#94a3b8;">No records found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="editInvoiceModalPopup" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); z-index: 99999; justify-content: center; align-items: center; backdrop-filter: blur(4px);">
    <div class="modal-content" style="background: white; max-width: 820px; width: 94%; border-radius: 12px; padding: 25px; position: relative; color: #111827; text-align: left; max-height: 90vh; overflow-y: auto;">
        <span style="position: absolute; top: 12px; right: 16px; font-size: 22px; cursor: pointer; color: #a0aec0; font-weight:bold;" onclick="closeEditInvoiceModal()">✕</span>
        <h3 style="font-size: 15px; font-weight: 700; text-transform: uppercase; margin-bottom: 15px; border-bottom: 2px solid #06b6d4; padding-bottom: 8px; color:#0284c7;" id="modalInvoiceTitle">Historical Statement Workspace</h3>
        
        <form method="POST" action="past_receipts.php" style="margin: 0;" id="masterWorkspaceForm">
            <input type="hidden" name="action_update_past_bill" value="1">
            <input type="hidden" name="update_guest_id" id="mdlInvoiceGuestId">
            <input type="hidden" name="applied_adjustments_payload" id="mdlAdjustmentsPayload">

            <div class="modal-split-layout">
                <div>
                    <h4 style="font-size:12px; text-transform:uppercase; color:#475569; border-bottom:1px dashed #cbd5e0; padding-bottom:4px; margin-top:0;">🏡 Stay Rent & Collector Metrics</h4>
                    
                    <div class="modal-input-group">
                        <label>Base Room Tariff Charges (₹)</label>
                        <input type="number" step="0.01" name="base_room_rent" id="mdlBaseRoomRent" class="modal-field-control" oninput="calculateWorkspaceOutstanding()">
                    </div>

                    <div class="modal-input-group">
                        <label>Advance Deposited Amount (₹)</label>
                        <input type="number" step="0.01" name="advance_paid" id="mdlAdvancePaid" class="modal-field-control" oninput="calculateWorkspaceOutstanding()">
                    </div>

                    <div class="modal-input-group">
                        <label>Accommodation Advance Taken By</label>
                        <select name="advance_received_by" id="mdlAdvanceReceivedBy" class="modal-field-control">
                            <option value="Tarpan bhaiya">Tarpan bhaiya</option>
                            <option value="Kamlesh">Kamlesh</option>
                            <option value="Abhijit">Abhijit</option>
                            <option value="Kinkar">Kinkar</option>
                            <option value="Subrata">Subrata</option>
                            <option value="Rohit">Rohit</option>
                            <option value="Vikas">Vikas</option>
                            <option value="Raju">Raju</option>
                        </select>
                    </div>

                    <div class="modal-input-group">
                        <label>Food & Incidentals Taken By</label>
                        <select name="food_received_by" id="mdlFoodReceivedBy" class="modal-field-control">
                            <option value="Tarpan bhaiya">Tarpan bhaiya</option>
                            <option value="Kamlesh">Kamlesh</option>
                            <option value="Abhijit">Abhijit</option>
                            <option value="Kinkar">Kinkar</option>
                            <option value="Subrata">Subrata</option>
                            <option value="Rohit">Rohit</option>
                            <option value="Vikas">Vikas</option>
                            <option value="Raju">Raju</option>
                        </select>
                    </div>
                </div>

                <div style="display:flex; flex-direction:column; justify-content:space-between;">
                    <div>
                        <h4 style="font-size:12px; text-transform:uppercase; color:#475569; border-bottom:1px dashed #cbd5e0; padding-bottom:4px; margin-top:0;">🍽️ Order Lines Assembly</h4>
                        <div id="mdlInvoiceItemsContainer" style="max-height: 160px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 8px; padding: 4px; background: #fafafa; margin-bottom:15px;"></div>
                        
                        <h4 style="font-size:12px; text-transform:uppercase; color:#475569; border-bottom:1px dashed #cbd5e0; padding-bottom:4px; margin-top:0;">➕ Add Adjustments Workspace</h4>
                        <div style="display:grid; grid-template-columns: 100px 1fr 40px; gap:6px; margin-bottom:10px;">
                            <select id="workspaceAdjType" class="modal-field-control" style="padding:6px;" onchange="handleTypeSelectorSync()">
                                <option value="charge">Charge (+)</option>
                                <option value="discount">Discount (-)</option>
                            </select>
                            <input type="text" id="workspaceAdjReason" class="modal-field-control" style="padding:6px;" placeholder="Reason descriptor label...">
                            <input type="number" id="workspaceAdjAmount" class="modal-field-control" style="padding:6px;" placeholder="₹">
                        </div>
                        <button type="button" class="btn btn-start" style="padding:6px 12px; font-size:11px; width:100%; border-radius:6px; font-weight:700; margin-bottom:10px;" onclick="addWorkspaceAdjustmentRow()">+ Inject Custom Adjustment Row</button>
                        
                        <div id="workspaceAdjustmentsContainer" style="max-height: 110px; overflow-y:auto; border:1px solid #edf2f7; padding:4px; border-radius:6px; background:#fff;"></div>
                    </div>
                </div>
            </div>

            <div style="margin-top:20px; background:#fafdfd; border:1px solid #06b6d4; padding:15px; border-radius:10px; display:flex; justify-content:space-between; align-items:center;">
                <div style="font-size:13px; color:#475569; line-height:1.4;">
                    <div>Stay Rent Outstanding: <span id="summaryStayRent" style="font-weight:700; color:#111827;">₹0.00</span></div>
                    <div>Food & extras Subtotal: <span id="summaryFoodExtras" style="font-weight:700; color:#0284c7;">₹0.00</span></div>
                </div>
                <div style="text-align:right; font-size:16px; font-weight:800; color:#1e293b;">
                    Total Collective due: <span id="summaryGrandTotal" style="color:#059669; border-bottom:4px double #059669; font-size:18px;">₹0.00</span>
                </div>
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end; align-items: center; margin-top:20px;">
                <button type="button" class="btn btn-log" style="padding: 10px 18px;" onclick="closeEditInvoiceModal()">Cancel</button>
                <button type="submit" class="btn btn-bill" style="padding: 10px 24px; font-weight: 800; background:#059669; border-color:#059669;">Recalculate & Save Sheet</button>
            </div>
        </form>
    </div>
</div>

<script>
let workspaceFoodItems = [];
let workspaceAdjustments = [];

function searchInvoiceTable() {
    const input = document.getElementById("invoiceSearchInput").value.toLowerCase().trim();
    document.querySelectorAll(".invoice-data-row").forEach(row => {
        const str = row.getAttribute("data-search-string") || "";
        row.style.display = str.includes(input) ? "" : "none";
    });
}

function handleTypeSelectorSync() {
    const type = document.getElementById("workspaceAdjType").value;
    const reason = document.getElementById("workspaceAdjReason");
    if (type === "discount") { reason.value = "Discount"; } 
    else if (reason.value === "Discount") { reason.value = ""; }
}

function openEditInvoiceWorkspace(guestData, element) {
    document.getElementById("mdlInvoiceGuestId").value = guestData.id;
    document.getElementById("modalInvoiceTitle").innerText = "Modify Bill Metrics: " + guestData.guest_name;
    document.getElementById("mdlBaseRoomRent").value = parseFloat(guestData.base_room_rent || 0);
    document.getElementById("mdlAdvancePaid").value = parseFloat(guestData.advance_paid || 0);
    document.getElementById("mdlAdvanceReceivedBy").value = guestData.advance_received_by || "Tarpan bhaiya";
    document.getElementById("mdlFoodReceivedBy").value = guestData.food_received_by || "Tarpan bhaiya";

    workspaceFoodItems = JSON.parse(element.getAttribute("data-items") || "[]");
    workspaceAdjustments = JSON.parse(element.getAttribute("data-adjustments") || "[]");

    renderFoodItemRows();
    renderWorkspaceAdjustmentsList();
    calculateWorkspaceOutstanding();
    handleTypeSelectorSync();

    document.getElementById("editInvoiceModalPopup").style.display = "flex";
}

function renderFoodItemRows() {
    const container = document.getElementById("mdlInvoiceItemsContainer");
    if (workspaceFoodItems.length === 0) {
        container.innerHTML = '<p style="text-align:center; color:#94a3b8; font-style:italic; padding:10px; margin:0;">No restaurant line items recorded.</p>';
        return;
    }
    container.innerHTML = workspaceFoodItems.map(i => `
        <div class="modal-item-edit-row">
            <div style="flex:1;">
                <strong>${i.name}</strong>
                <span style="color:#64748b; display:block; font-size:10px;">Unit Rate: ₹${parseFloat(i.price).toFixed(2)}</span>
            </div>
            <input type="number" name="invoice_item_qty[${i.id}]" value="${i.quantity}" min="0" class="modal-qty-field" data-price="${i.price}" oninput="updateFoodQuantityData(${i.id}, this)">
        </div>
    `).join('');
}

function updateFoodQuantityData(id, inputEl) {
    const target = workspaceFoodItems.find(x => x.id === id);
    if (target) {
        target.quantity = parseInt(inputEl.value) || 0;
        calculateWorkspaceOutstanding();
    }
}

function addWorkspaceAdjustmentRow() {
    const type = document.getElementById("workspaceAdjType").value;
    const reason = document.getElementById("workspaceAdjReason").value.trim();
    const amount = parseFloat(document.getElementById("workspaceAdjAmount").value) || 0;

    if (amount <= 0) return alert("Please type a valid adjustment amount value.");
    if (reason === "") return alert("Please map a descriptor label.");

    workspaceAdjustments.push({ id: Date.now(), type: type, reason: reason, amount: amount });
    document.getElementById("workspaceAdjAmount").value = "";
    document.getElementById("workspaceAdjReason").value = "";
    document.getElementById("workspaceAdjType").value = "charge";
    
    renderWorkspaceAdjustmentsList();
    calculateWorkspaceOutstanding();
    handleTypeSelectorSync();
}

function removeWorkspaceAdjustmentItem(id) {
    workspaceAdjustments = workspaceAdjustments.filter(x => x.id !== id);
    renderWorkspaceAdjustmentsList();
    calculateWorkspaceOutstanding();
}

function renderWorkspaceAdjustmentsList() {
    const container = document.getElementById("workspaceAdjustmentsContainer");
    if (workspaceAdjustments.length === 0) {
        container.innerHTML = '<p style="color:#cbd5e0; text-align:center; padding:8px; font-size:11px; margin:0; font-style:italic;">No dynamic variations added.</p>';
        return;
    }
    container.innerHTML = workspaceAdjustments.map(item => `
        <div style="display:flex; justify-content:space-between; align-items:center; font-size:12px; padding:4px; border-bottom:1px solid #f1f5f9;">
            <div><span style="font-weight:700; color:${item.type === 'charge' ? '#e53e3e' : '#38a169'};">[${item.type.toUpperCase()}]</span> ${item.reason} - <b>₹${item.amount}</b></div>
            <button type="button" class="adj-badge-del" onclick="removeWorkspaceAdjustmentItem(${item.id})">✕</button>
        </div>
    `).join('');
}

function calculateWorkspaceOutstanding() {
    const baseRoomRent = parseFloat(document.getElementById("mdlBaseRoomRent").value) || 0;
    const advancePaid = parseFloat(document.getElementById("mdlAdvancePaid").value) || 0;
    
    let stayOutstanding = Math.max(0, baseRoomRent - advancePaid);
    
    let foodSubtotal = 0;
    workspaceFoodItems.forEach(i => {
        foodSubtotal += (i.quantity * floatval(i.price));
    });

    workspaceAdjustments.forEach(adj => {
        if (adj.type === "charge") {
            foodSubtotal += parseFloat(adj.amount);
        } else {
            foodSubtotal -= parseFloat(adj.amount);
        }
    });
    foodSubtotal = Math.max(0, foodSubtotal);

    document.getElementById("summaryStayRent").innerText = "₹" + stayOutstanding.toFixed(2);
    document.getElementById("summaryFoodExtras").innerText = "₹" + foodSubtotal.toFixed(2);
    document.getElementById("summaryGrandTotal").innerText = "₹" + (stayOutstanding + foodSubtotal).toFixed(2);
    document.getElementById("mdlAdjustmentsPayload").value = JSON.stringify(workspaceAdjustments);
}

// Global runtime floating point conversion macro alignment helper function
function floatval(val) {
    let f = parseFloat(val);
    return isNaN(f) ? 0 : f;
}

function closeEditInvoiceModal() { 
    document.getElementById("editInvoiceModalPopup").style.display = "none"; 
}
</script>

<?php include "includes/footer.php"; ?>