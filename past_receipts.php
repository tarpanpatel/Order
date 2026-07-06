<?php
// /home/apartment/artistsfarmjaipur.com/Order/past_receipts.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once "config/db.php";

if (!isset($_SESSION["role"]) || ($_SESSION["role"] !== "Admin" && $_SESSION["role"] !== "Super Admin")) {
    die("Access Denied: Administrative credentials required.");
}

// --- BACKEND LOGIC: POST INTERCEPTOR ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action_update_invoice"])) {
    $order_id = intval($_POST["update_order_id"]);
    $item_qtys = $_POST["invoice_item_qty"] ?? [];
    
    $pdo->beginTransaction();
    try {
        foreach ($item_qtys as $item_id => $qty) {
            $item_id = intval($item_id);
            $qty = intval($qty);
            
            if ($qty <= 0) {
                $pdo->prepare("DELETE FROM order_items WHERE id = ? AND order_id = ?")->execute([$item_id, $order_id]);
            } else {
                $pdo->prepare("UPDATE order_items SET quantity = ? WHERE id = ? AND order_id = ?")->execute([$qty, $item_id, $order_id]);
            }
        }
        $pdo->commit();
        $_SESSION['invoice_success_toast'] = "Invoice #$order_id updated successfully!";
        header("Location: past_receipts.php");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error: " . $e->getMessage());
    }
}

// FIXED SQL: Only selecting columns that exist in 'orders' table
$orders = $pdo->query("
    SELECT o.id, 
           (SELECT SUM(oi.quantity * oi.price) FROM order_items oi WHERE oi.order_id = o.id) as grand_total,
           g.phone_number
    FROM orders o 
    LEFT JOIN guests g ON o.guest_id = g.id 
    ORDER BY o.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

include "includes/header.php";
?>

<div class="app-body" style="padding: 20px; font-family: sans-serif; text-align: left;">
    <?php if (isset($_SESSION['invoice_success_toast'])): ?>
        <div style="padding: 12px; background: #10b981; color: white; text-align: center; border-radius: 8px; margin-bottom: 15px;">
            📢 <?= htmlspecialchars($_SESSION['invoice_success_toast']); unset($_SESSION['invoice_success_toast']); ?>
        </div>
    <?php endif; ?>

    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
        <h2>📜 Past Receipts Archive</h2>
    </div>

    <div style="background: #ffffff; border: 1px solid #cbd5e0; padding: 20px; border-radius: 12px;">
        <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
            <thead>
                <tr style="background:#f8fafc; border-bottom:2px solid #e2e8f0; text-align:left;">
                    <th style="padding:12px;">Invoice ID</th>
                    <th style="padding:12px;">Guest</th>
                    <th style="padding:12px;">Grand Total (₹)</th>
                    <th style="padding:12px; text-align:center;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): 
                    $total = floatval($order['grand_total'] ?? 0);
                    $itemsStmt = $pdo->prepare("SELECT oi.*, mi.name, mi.price FROM order_items oi JOIN menu_items mi ON oi.menu_item_id = mi.id WHERE oi.order_id = ?");
                    $itemsStmt->execute([$order['id']]);
                    $serializedItems = json_encode($itemsStmt->fetchAll(PDO::FETCH_ASSOC));
                ?>
                    <tr>
                        <td style="padding:12px; font-weight:bold; color:#0284c7;">#<?= $order['id'] ?></td>
                        <td style="padding:12px;"><?= htmlspecialchars($order['phone_number'] ?? 'Guest') ?></td>
                        <td style="padding:12px; font-weight:800; color:#059669;">₹<?= number_format($total, 2) ?></td>
                        <td style="padding:12px; text-align:center;">
                            <button type="button" class="btn btn-start" style="padding:6px 14px; border-radius:6px;" data-items='<?= htmlspecialchars($serializedItems, ENT_QUOTES, 'UTF-8') ?>' onclick="openEditInvoiceModal(<?= $order['id'] ?>, this)">✏ Edit</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="editInvoiceModalPopup" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); z-index: 99999; justify-content: center; align-items: center;">
    <div class="modal-content" style="background: white; width: 400px; padding: 25px; border-radius: 12px;">
        <h3 id="modalInvoiceTitle">Modify Invoice</h3>
        <form method="POST" action="past_receipts.php">
            <input type="hidden" name="action_update_invoice" value="1">
            <input type="hidden" name="update_order_id" id="mdlInvoiceOrderId">
            <div id="mdlInvoiceItemsContainer"></div>
            <div style="margin-top:20px; text-align:right;">
                <button type="button" onclick="closeEditInvoiceModal()">Cancel</button>
                <button type="submit">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditInvoiceModal(orderId, element) {
    document.getElementById("mdlInvoiceOrderId").value = orderId;
    document.getElementById("modalInvoiceTitle").innerText = "Modify Invoice #" + orderId;
    const container = document.getElementById("mdlInvoiceItemsContainer");
    const items = JSON.parse(element.getAttribute("data-items"));
    container.innerHTML = items.map(i => `
        <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
            <span>${i.name}</span>
            <input type="number" name="invoice_item_qty[${i.id}]" value="${i.quantity}" style="width:50px;">
        </div>
    `).join('');
    document.getElementById("editInvoiceModalPopup").style.display = "flex";
}
function closeEditInvoiceModal() { document.getElementById("editInvoiceModalPopup").style.display = "none"; }
</script>
<?php include "includes/footer.php"; ?>