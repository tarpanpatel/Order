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
        foreach ($item_qtys as $item_id => $qty) {
            $item_id = intval($item_id);
            $qty = intval($qty);
            
            if ($qty <= 0) {
                $pdo->prepare("DELETE FROM order_items WHERE id = ? AND order_id = ?")->execute([$item_id, $order_id]);
            } else {
                $priceStmt = $pdo->prepare("SELECT price FROM order_items WHERE id = ?");
                $priceStmt->execute([$item_id]);
                $unit_price = floatval($priceStmt->fetchColumn() ?: 0);
                $item_total = $unit_price * $qty;
                $subtotal += $item_total;
                $pdo->prepare("UPDATE order_items SET quantity = ?, total_price = ? WHERE id = ? AND order_id = ?")->execute([$qty, $item_total, $item_id, $order_id]);
            }
        }
        
        $grand_total = max(0, $subtotal - $custom_discount);
        // Updating only existing columns
        $pdo->prepare("UPDATE orders SET discount = ?, grand_total = ? WHERE id = ?")->execute([$custom_discount, $grand_total, $order_id]);
        
        $pdo->commit();
        $_SESSION['invoice_success_toast'] = "Invoice #$order_id updated!";
        header("Location: past_receipts.php");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error updating invoice: " . $e->getMessage());
    }
}

// FIXED SQL: Calculating totals dynamically from order_items since o.subtotal/o.grand_total columns don't exist
$orders = $pdo->query("
    SELECT o.id, o.created_at, o.discount, 
           (SELECT SUM(total_price) FROM order_items WHERE order_id = o.id) as subtotal,
           g.phone_number
    FROM orders o 
    LEFT JOIN guests g ON o.guest_id = g.id 
    ORDER BY o.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

include "includes/header.php";
?>

<style>
.receipts-dashboard-card { background: #ffffff; border: 1px solid #cbd5e0; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
.receipts-table { width: 100%; border-collapse: collapse; font-size: 13px; text-align: left; }
.receipts-table th { background: #f8fafc; padding: 12px; font-weight: 700; color: #475569; border-bottom: 2px solid #cbd5e0; }
.receipts-table td { padding: 12px; border-bottom: 1px solid #e2e8f0; color: #1e293b; vertical-align: middle; }
.modal-item-edit-row { display: flex; justify-content: space-between; align-items: center; padding: 10px; border-bottom: 1px solid #edf2f7; gap: 10px; }
.modal-qty-field { width: 60px; padding: 6px; text-align: center; border: 1px solid #cbd5e0; border-radius: 6px; font-weight: bold; }
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
        </div>
        <input type="text" id="invoiceSearchInput" onkeyup="searchInvoiceTable()" placeholder="🔍 Search Invoice ID..." style="padding:10px; border:1px solid #cbd5e0; border-radius:8px; font-size:13px;">
    </div>

    <div class="receipts-dashboard-card">
        <table class="receipts-table" id="pastReceiptsMasterTable">
            <thead>
                <tr>
                    <th>Invoice ID</th>
                    <th>Date</th>
                    <th>Subtotal (₹)</th>
                    <th>Discount (₹)</th>
                    <th>Grand Total (₹)</th>
                    <th style="text-align:center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($orders)): foreach ($orders as $order): 
                    $subtotal = floatval($order['subtotal'] ?? 0);
                    $discount = floatval($order['discount'] ?? 0);
                    $grand_total = $subtotal - $discount;

                    $itemsStmt = $pdo->prepare("SELECT oi.*, mi.name FROM order_items oi JOIN menu_items mi ON oi.menu_item_id = mi.id WHERE oi.order_id = ?");
                    $itemsStmt->execute([$order['id']]);
                    $serializedItems = json_encode($itemsStmt->fetchAll(PDO::FETCH_ASSOC));
                ?>
                    <tr class="invoice-data-row">
                        <td style="font-weight: bold; color: #0284c7;">#<?= $order['id'] ?></td>
                        <td><?= date('d M Y', strtotime($order['created_at'])) ?></td>
                        <td>₹<?= number_format($subtotal, 2) ?></td>
                        <td style="color:#ef4444;">₹<?= number_format($discount, 2) ?></td>
                        <td style="font-weight: 800; color: #059669;">₹<?= number_format($grand_total, 2) ?></td>
                        <td style="text-align: center;">
                            <button type="button" class="btn btn-start" style="padding: 6px 14px; font-size:12px; border-radius:6px;" data-items='<?= htmlspecialchars($serializedItems, ENT_QUOTES, 'UTF-8') ?>' onclick="openEditInvoiceModal(<?= $order['id'] ?>, <?= $discount ?>, this)">✏ Edit Bill</button>
                        </td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="6" style="text-align:center; padding:30px; color:#94a3b8;">No records found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function openEditInvoiceModal(orderId, currentDiscount, element) {
    document.getElementById("mdlInvoiceOrderId").value = orderId;
    document.getElementById("mdlInvoiceDiscountInput").value = currentDiscount;
    const container = document.getElementById("mdlInvoiceItemsContainer");
    const items = JSON.parse(element.getAttribute("data-items"));
    container.innerHTML = items.map(i => `
        <div class="modal-item-edit-row">
            <div style="flex:1;">${i.name}</div>
            <input type="number" name="invoice_item_qty[${i.id}]" value="${i.quantity}" class="modal-qty-field">
        </div>
    `).join('');
    document.getElementById("editInvoiceModalPopup").style.display = "flex";
}
function closeEditInvoiceModal() { document.getElementById("editInvoiceModalPopup").style.display = "none"; }
</script>

<?php include "includes/footer.php"; ?>