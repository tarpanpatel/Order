<?php
// /home/apartment/artistsfarmjaipur.com/Order/kitchen.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "config/db.php";
require_once "config/telegram.php";
 
// Report all PHP errors
error_reporting(E_ALL);
ini_set('display_errors', '1');

// --- HANDLE DISPATCH SYSTEM COMPLETION TRIGGER WITH TELEGRAM GATEWAY ---
if (isset($_POST["complete_order_id"])) { 
    $order_id = intval($_POST["complete_order_id"]);

    try {
        // 1. Fetch order item descriptions and guest configurations before redirection
        $stmt = $pdo->prepare("SELECT g.guest_name FROM orders o JOIN guests g ON o.guest_id = g.id WHERE o.id = ?");
        $stmt->execute([$order_id]);
        $guest_name = $stmt->fetchColumn() ?: "Walk-In Guest";

        $itemsStmt = $pdo->prepare("SELECT oi.quantity, mi.name, oi.special_instructions FROM order_items oi JOIN menu_items mi ON oi.menu_item_id = mi.id WHERE oi.order_id = ?");
        $itemsStmt->execute([$order_id]);
        $readyItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        $itemsListBlock = "";
        foreach ($readyItems as $item) {
            $instructionBadge = !empty($item['special_instructions']) ? " *(_Note: " . htmlspecialchars($item['special_instructions']) . "_)*" : "";
            $itemsListBlock .= "🔹 *x" . $item['quantity'] . "* " . $item['name'] . $instructionBadge . "\n";
        }

        // 2. Build the Telegram text ticket string payload
        $readyMsg = "✅ *ORDER PREPARED & READY TO SERVE*\n";
        $readyMsg .= "--------------------------------------\n";
        $readyMsg .= "👤 *Guest Name:* " . $guest_name . "\n";
        $readyMsg .= "🆔 *Order Ticket:* #" . $order_id . "\n";
        $readyMsg .= "⏰ *Completed at:* " . date('H:i') . "\n";
        $readyMsg .= "--------------------------------------\n\n";
        $readyMsg .= $itemsListBlock;
        $readyMsg .= "\n--------------------------------------\n";
        $readyMsg .= "🏃‍♂️ _Staff, please collect the order from the kitchen immediately._";

        // Dispatch out through your internal whitelisted local gateway proxy route
        sendTelegramNotification($readyMsg);

    } catch (Exception $tgEx) {
        // Safe catch block prevents connection errors from crashing database updates
    }

    // 3. Finalize core application database status values update cleanly
    $pdo->prepare("UPDATE orders SET status = 'Completed' WHERE id = ?")->execute([$order_id]); 

    // --- AUDIT TRAIL LOGGING ---
    $audit_stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, timestamp) VALUES (?, ?, NOW())");
    $audit_stmt->execute([$_SESSION['user_id'], "User [" . $_SESSION['username'] . "] marked Kitchen Ticket #" . $order_id . " as Completed/Ready to Serve."]);

    header("Location: kitchen.php"); 
    exit;
}

$pending = $pdo->query("SELECT o.id, g.guest_name FROM orders o JOIN guests g ON o.guest_id = g.id WHERE o.status = 'Pending' ORDER BY o.id ASC")->fetchAll();
include "includes/header.php";
?>

<div id="kitchenPromptModal" class="prompt-modal">
    <div class="prompt-box" id="kitchenPromptBox">
        <div id="kitchenInteractiveContent">
            <div class="prompt-title">Confirm Ticket Status</div>
            <div class="prompt-desc">Is this food order cooked and ready for dispatch?</div>
            <div class="prompt-actions">
                <button class="prompt-btn p-btn-confirm" id="kitchenConfirmBtn">Yes, Ready</button>
                <button class="prompt-btn p-btn-cancel" onclick="document.getElementById('kitchenPromptModal').style.display='none'">No</button>
            </div>
        </div>
    </div>
</div>

<div class="app-body">
    <div class="category-section">
        <h2 class="category-title" style="text-transform: none;">🍳 Kitchen Orders</h2>
    </div>

    <div class="grid" style="grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));">
        <?php foreach($pending as $order): 
            $items = $pdo->query("SELECT oi.*, mi.name, mi.category_id, mi.image_path FROM order_items oi JOIN menu_items mi ON oi.menu_item_id = mi.id WHERE oi.order_id = ".$order["id"])->fetchAll();
        ?>
            <div class="card" style="text-align: left; display: flex; flex-direction: column; justify-content: space-between; min-height: 220px; border-top: 3px solid var(--primary) !important;">
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; border-bottom: 1px dashed #e2e8f0; padding-bottom: 6px;">
                        <span style="font-weight: 700; font-size: 14px; color: var(--text-main);">Order #<?= $order["id"] ?></span>
                        <span style="font-size: 11px; padding: 2px 8px; background: var(--primary-bg); color: var(--primary); font-weight: 700; border-radius: 12px;"><?= htmlspecialchars($order["guest_name"]) ?></span>
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <?php foreach($items as $i): ?>
                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px; font-size: 13px; font-weight: 600;">
                                🍟 <span><strong><?= $i["quantity"] ?>x</strong> <?= htmlspecialchars($i["name"]) ?></span>
                                <?php if(!empty($i["special_instructions"])): ?>
                                    <div style="color: var(--danger); font-size: 11px; font-weight: 700; margin-left: auto;">⚠️ <?= htmlspecialchars($i["special_instructions"]) ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <form method="POST" id="form_kitchen_<?= $order["id"] ?>" style="margin: 0;">
                    <input type="hidden" name="complete_order_id" value="<?= $order["id"] ?>">
                    <button type="button" class="btn btn-start" style="width: 100%; padding: 10px; font-size: 12px;" onclick="confirmKitchenDispatch(<?= $order["id"] ?>)">Ready</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
let activeFormId = null;
function confirmKitchenDispatch(orderId) {
    activeFormId = "form_kitchen_" + orderId;
    
    document.getElementById("kitchenInteractiveContent").innerHTML = `
        <div class="prompt-title">Confirm Ticket Status</div>
        <div class="prompt-desc">Is this food order cooked and ready for dispatch?</div>
        <div class="prompt-actions">
            <button class="prompt-btn p-btn-confirm" id="kitchenConfirmBtn">Yes, Ready</button>
            <button class="prompt-btn p-btn-cancel" onclick="document.getElementById('kitchenPromptModal').style.display='none'">No</button>
        </div>
    `;
    
    document.getElementById("kitchenPromptModal").style.display = "flex";
    
    document.getElementById("kitchenConfirmBtn").onclick = function() {
        if(activeFormId) {
            document.getElementById("kitchenInteractiveContent").innerHTML = `
                <div class="prompt-title" style="color: var(--success); margin-bottom: 0;">
                    ✔ Ticket marked complete! Dispatching...
                </div>
            `;
            setTimeout(() => {
                document.getElementById(activeFormId).submit();
            }, 1000);
        }
    };
}
</script>
<?php include "includes/footer.php"; ?>