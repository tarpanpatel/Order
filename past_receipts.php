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
    
    $pdo->beginTransaction();
    try {
        foreach ($item_qtys as $item_id => $qty) {
            $item_id = intval($item_id);
            $qty = intval($qty);
            
            if ($qty <= 0) {
                $pdo->prepare("DELETE FROM order_items WHERE id = ? AND order_id = ?")->execute([$item_id, $order_id]);
            } else {
                // Update quantity inside order_items table lines
                $pdo->prepare("UPDATE order_items SET quantity = ? WHERE id = ? AND order_id = ?")->execute([$qty, $item_id, $order_id]);
            }
        }
        
        $pdo->commit();
        $_SESSION['invoice_success_toast'] = "Invoice #$order_id updated successfully!";
        header("Location: past_receipts.php");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error updating invoice: " . $e->getMessage());
    }
}

// FIXED SQL: Joined order_items with menu_items to multiply quantity * mi.price accurately
$orders = $pdo->query("
    SELECT o.id, 
           (SELECT SUM(oi.quantity * mi.price) 
            FROM order_items oi 
            JOIN menu_items mi ON oi.menu_item_id = mi.id 
            WHERE oi.order_id = o.id) as grand_total,
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
                    <th>Guest Mapping</th>
                    <th>Grand Total (₹)</th>
                    <th style="text-align:center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($orders)): foreach ($orders as $order): 
                    $total_amount = floatval($order['grand_total'] ?? 0);

                    // Pull menu line prices properly from mi.price relation 
                    $itemsStmt = $pdo->prepare("SELECT oi.id, oi.quantity, mi.name, mi.price FROM order_items oi JOIN menu_items mi ON oi.menu_item_id = mi.id WHERE oi.order_id = ?");
                    $itemsStmt->execute([$order['id']]);
                    $serializedItems = json_encode($itemsStmt->fetchAll(PDO::FETCH_ASSOC));
                ?>
                    <tr class="invoice-data-row" data-search-string="<?= strtolower($order['id'] . ' ' . ($order['phone_number'] ?? '')) ?>">
                        <td style="font-weight: bold; color: #0284c7;">#<?= $order['id'] ?></td>
                        <td style="font-family:monospace; font-weight:bold; color:#475569;"><?= htmlspecialchars($order['phone_number'] ?? 'Walk-In Guest') ?></td>
                        <td style="font-weight: 800; color: #059669;">₹<?= number_format($total_amount, 2) ?></td>
                        <td style="text-align: center;">
                            <button type="button" class="btn btn-start" style="padding: 6px 14px; font-size:12px; border-radius:6px;" data-items='<?= htmlspecialchars($serializedItems, ENT_QUOTES, 'UTF-8') ?>' onclick="openEditInvoiceModal(<?= $order['id'] ?>, this)">✏ Edit Bill</button>
                        </td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="4" style="text-align:center; padding:30px; color:#94a3b8;">No records found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
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
            <div id="mdlInvoiceItemsContainer" style="max-height: 220px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 8px; padding: 6px; margin-bottom: 20px; background: #fafafa;"></div>
            
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

function openEditInvoiceModal(orderId, element) {
    document.getElementById("mdlInvoiceOrderId").value = orderId;
    document.getElementById("modalInvoiceTitle").innerText = "Modify Invoice #" + orderId;
    
    const container = document.getElementById("mdlInvoiceItemsContainer");
    const items = JSON.parse(element.getAttribute("data-items"));
    
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
}

function closeEditInvoiceModal() { 
    document.getElementById("editInvoiceModalPopup").style.display = "none"; 
}
</script>

<?php include "includes/footer.php"; ?>