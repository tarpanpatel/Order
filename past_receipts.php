<?php
// /home/apartment/artistsfarmjaipur.com/Order/past_receipts.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once "config/db.php";

if (!isset($_SESSION["role"]) || ($_SESSION["role"] !== "Admin" && $_SESSION["role"] !== "Super Admin")) {
    die("Access Denied: Administrative credentials required.");
}

// --- BACKEND LOGIC: POST INTERCEPTOR FOR UPDATING PAST INVOICES ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action_update_invoice"])) {
    $order_id = intval($_POST["update_order_id"]);
    $item_qtys = $_POST["invoice_item_qty"] ?? [];
    $custom_discount = floatval($_POST["invoice_discount"] ?? 0);
    
    $pdo->beginTransaction();
    try {
        $subtotal = 0;
        
        // Loop through items to update or remove
        foreach ($item_qtys as $item_id => $qty) {
            $item_id = intval($item_id);
            $qty = intval($qty);
            
            if ($qty <= 0) {
                // Delete item from order line items if set to 0
                $stmt = $pdo->prepare("DELETE FROM order_items WHERE id = ? AND order_id = ?");
                $stmt->execute([$item_id, $order_id]);
            } else {
                // Fetch unit price to calculate new totals dynamically
                $priceStmt = $pdo->prepare("SELECT price FROM order_items WHERE id = ?");
                $priceStmt->execute([$item_id]);
                $unit_price = floatval($priceStmt->fetchColumn() ?: 0);
                
                $item_total = $unit_price * $qty;
                $subtotal += $item_total;
                
                // Update quantity and line total
                $stmt = $pdo->prepare("UPDATE order_items SET quantity = ?, total_price = ? WHERE id = ? AND order_id = ?");
                $stmt->execute([$qty, $item_total, $item_id, $order_id]);
            }
        }
        
        $grand_total = max(0, $subtotal - $custom_discount);
        
        // Update parent order totals
        $updateOrder = $pdo->prepare("UPDATE orders SET subtotal = ?, discount = ?, grand_total = ? WHERE id = ?");
        $updateOrder->execute([$subtotal, $custom_discount, $grand_total, $order_id]);
        
        $pdo->commit();
        $_SESSION['invoice_success_toast'] = "Invoice #$order_id updated and recalculated successfully!";
        header("Location: past_receipts.php");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error updating invoice: " . $e->getMessage());
    }
}

// Fetch past closed orders/receipts
$orders = $pdo->query("
    SELECT o.*, g.phone_number, g.room_number 
    FROM orders o 
    LEFT JOIN guests g ON o.guest_id = g.id 
    ORDER BY o.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

include "includes/header.php";
?>

<style>
.receipts-dashboard-card { background: #ffffff; border: 1px solid #cbd5e0; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
.receipts-table { width: 100%; border-collapse: collapse; font-size: 13px; text-align: left; }
.receipts-table th { background: #f8fafc; padding: 12px; font-weight: 700; color: #475569; border-bottom: 2px solid #cbd5e0; }
.receipts-table td { padding: 12px; border-bottom: 1px solid #e2e8f0; color: #1e293b; vertical-align: middle; }
.invoice-search-bar { width: 100%; max-width: 360px; padding: 10px 14px; border: 1px solid #cbd5e0; border-radius: 8px; font-size: 13px; }
.modal-item-edit-row { display: flex; justify-content: space-between; align-items: center; padding: 10px; border-bottom: 1px solid #edf2f7; gap: 10px; }
.modal-qty-field { width: 60px; padding: 6px; text-align: center; border: 1px solid #cbd5e0; border-radius: 6px; font-weight: bold; }
</style>

<div class="app-body" style="padding: 20px; font-family: sans-serif; text-align: left;">
    
    <?php if (isset($_SESSION['invoice_success_toast'])): ?>
        <div style="padding: 12px 24px; background: #10b981; color: white; font-size: 14px; font-weight: 800; text-align: center; border-radius: 8px; margin-bottom: 15px;">
            📢 <strong><?= htmlspecialchars($_SESSION['invoice_success_toast']) ?></strong>
        </div>
    <?php unset($_SESSION['invoice_success_toast']); endif; ?>

    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; gap:20px; flex-wrap:wrap;">
        <div>
            <h2 style="margin:0; color:#1e293b;">📜 Past Receipts Archive Log</h2>
            <p style="color:var(--text-muted); margin: 5px 0 0 0; font-size:0.9rem;">Review, search, or edit historically closed invoices</p>
        </div>
        <input type="text" id="invoiceSearchInput" onkeyup="searchInvoiceTable()" placeholder="🔍 Search by Invoice ID, Room, or Guest..." class="form-control" style="max-width:320px; padding:10px; border:1px solid #cbd5e0; border-radius:8px; font-size:13px;">
    </div>

    <div class="receipts-dashboard-card">
        <div style="overflow-x:auto;">
            <table class="receipts-table" id="pastReceiptsMasterTable">
                <thead>
                    <tr>
                        <th>Invoice ID</th>
                        <th>Timestamp Date</th>
                        <th>Room Assignment</th>
                        <th>Guest Mapping</th>
                        <th>Subtotal Amount</th>
                        <th>Discount Given</th>
                        <th>Grand Total (₹)</th>
                        <th style="text-align:center;">Actions Matrix</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($orders)): foreach ($orders as $order): 
                        // Fetch order items to pass to JavaScript modal array securely
                        $itemsStmt = $pdo->prepare("SELECT oi.*, mi.name FROM order_items oi JOIN menu_items mi ON oi.menu_item_id = mi.id WHERE oi.order_id = ?");
                        $itemsStmt->execute([$order['id']]);
                        $serializedItems = json_encode($itemsStmt->fetchAll(PDO::FETCH_ASSOC));
                    ?>
                        <tr class="invoice-data-row" data-search-string="<?= strtolower($order['id'] . ' room ' . ($order['room_number'] ?? '') . ' ' . ($order['phone_number'] ?? '')) ?>">
                            <td style="font-weight: bold; color: #0284c7;">#<?= $order['id'] ?></td>
                            <td><?= date('d M Y, h:i A', strtotime($order['created_at'])) ?></td>
                            <td><span style="padding:4px 8px; background:#f1f5f9; border-radius:6px; font-weight:600;">Room <?= htmlspecialchars($order['room_number'] ?? 'N/A') ?></span></td>
                            <td style="font-family:monospace; font-weight:bold; color:#475569;"><?= htmlspecialchars($order['phone_number'] ?? 'Walk-In Guest') ?></td>
                            <td>₹<?= number_format($order['subtotal'], 2) ?></td>
                            <td style="color:#ef4444;">₹<?= number_format($order['discount'], 2) ?></td>
                            <td style="font-weight: 800; color: #059669;">Extra ₹<?= number_format($order['grand_total'], 2) ?></td>
                            <td style="text-align: center;">
                                <button type="button" class="btn btn-start" style="padding: 6px 14px; font-size:12px; font-weight:700; border-radius:6px;" data-items='<?= htmlspecialchars($serializedItems, ENT_QUOTES, 'UTF-8') ?>' onclick="openEditInvoiceModal(<?= $order['id'] ?>, <?= $order['discount'] ?>, this)">
                                    ✏ Edit Bill
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="8" style="text-align:center; color:#94a3b8; padding:30px; font-style:italic;">No past invoice records found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="editInvoiceModalPopup" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); z-index: 99999; justify-content: center; align-items: center; backdrop-filter: blur(4px);">
    <div class="modal-content" style="background: white; max-width: 520px; width: 92%; border-radius: 12px; padding: 25px; position: relative; color: #111827; text-align: left;">
        <span style="position: absolute; top: 12px; right: 16px; font-size: 22px; cursor: pointer; color: #a0aec0; font-weight:bold;" onclick="closeEditInvoiceModal()">✕</span>
        <h3 style="font-size: 14px; font-weight: 700; text-transform: uppercase; margin-bottom: 15px; border-bottom: 1px dashed #e2e8f0; padding-bottom: 8px; color:#0284c7;" id="modalInvoiceTitle">Modify Invoice #00</h3>
        
        <form method="POST" action="past_receipts.php" style="margin: 0;">
            <input type="hidden" name="action_update_invoice" value="1">
            <input type="hidden" name="update_order_id" id="mdlInvoiceOrderId">
            
            <label style="font-size: 11px; font-weight: 700; color: #475569; display: block; margin-bottom: 6px; text-transform: uppercase;">Line Items Assembly</label>
            <div id="mdlInvoiceItemsContainer" style="max-height: 220px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 8px; padding: 6px; margin-bottom: 15px; background: #fafafa;"></div>
            
            <div style="margin-bottom: 20px;">
                <label style="font-size: 11px; font-weight: 700; color: #475569; display: block; margin-bottom: 6px; text-transform: uppercase;">Applied Flat Discount (₹)</label>
                <input type="number" name="invoice_discount" id="mdlInvoiceDiscountInput" required min="0" step="0.01" style="width:100%; padding:8px; border:1px solid #cbd5e0; border-radius:6px; font-size:14px; font-weight:bold; color:#ef4444;">
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end; align-items: center;">
                <button type="button" class="btn btn-log" style="padding: 10px 18px;" onclick="closeEditInvoiceModal()">Cancel</button>
                <button type="submit" class="btn btn-bill" style="padding: 10px 24px; font-weight: 800; background:#059669; border-color:#059669;">Recalculate & Save</button>
            </div>
        </form>
    </div>
</div>

<script>
function searchInvoiceTable() {
    const input = document.getElementById("invoiceSearchInput").value.toLowerCase().trim();
    const rows = document.querySelectorAll(".invoice-data-row");
    
    rows.forEach(row => {
        const searchStr = row.getAttribute("data-search-string") || "";
        if (searchStr.includes(input)) {
            row.style.display = "";
        } else {
            row.style.display = "none";
        }
    });
}

function openEditInvoiceModal(orderId, currentDiscount, element) {
    document.getElementById("mdlInvoiceOrderId").value = orderId;
    document.getElementById("modalInvoiceTitle").innerText = "Modify Invoice #" + orderId;
    document.getElementById("mdlInvoiceDiscountInput").value = currentDiscount;
    
    const container = document.getElementById("mdlInvoiceItemsContainer");
    const rawItemsData = element.getAttribute("data-items");
    
    try {
        const items = JSON.parse(rawItemsData);
        if (!items || items.length === 0) {
            container.innerHTML = '<p style="text-align:center; color:#ef4444; font-size:12px; padding:15px;">No structural line entries found.</p>';
            return;
        }
        
        container.innerHTML = items.map(i => `
            <div class="modal-item-edit-row">
                <div style="flex: 1; text-align: left;">
                    <span style="font-weight:700; font-size:13px; color:#1e293b; display:block;">${i.name}</span>
                    <span style="font-size:11px; color:#64748b; font-weight:600;">Unit Price: ₹${parseFloat(i.price).toFixed(2)}</span>
                </div>
                <div style="display:flex; align-items:center; gap:6px;">
                    <label style="font-size:11px; font-weight:700; color:#475569;">Qty:</label>
                    <input type="number" name="invoice_item_qty[${i.id}]" value="${i.quantity}" min="0" class="modal-qty-field">
                </div>
            </div>
        `).join('');
        
        document.getElementById("editInvoiceModalPopup").style.display = "flex";
    } catch(err) {
        container.innerHTML = '<p style="text-align:center; color:#ef4444; font-size:12px; padding:15px;">JSON parsing failure.</p>';
    }
}

function closeEditInvoiceModal() {
    document.getElementById("editInvoiceModalPopup").style.display = "none";
}
</script>

<?php include "includes/footer.php"; ?>