<?php
// /home/apartment/artistsfarmjaipur.com/Order/kitchen.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Report all PHP errors
error_reporting(E_ALL);

// Force errors to be displayed on the screen
ini_set('display_errors', '1');
require_once "config/db.php";

// Load telegram config if it exists
if (file_exists(__DIR__ . "/config/telegram.php")) {
    require_once __DIR__ . "/config/telegram.php";
}

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

        try {
            $readyMsg = "✅ *ORDER PREPARED & READY TO SERVE*\n";
            $readyMsg .= "--------------------------------------\n";
            $readyMsg .= "👤 *Guest Name:* " . $guest_name . "\n";
            $readyMsg .= "🆔 *Order Ticket:* #" . $order_id . "\n";
            $readyMsg .= "⏰ *Completed at:* " . date('H:i') . "\n";
            $readyMsg .= "--------------------------------------\n\n";
            $readyMsg .= $itemsListBlock;
            $readyMsg .= "\n--------------------------------------\n";
            $readyMsg .= "🏃‍♂️ _Staff, please collect the order from the kitchen immediately._";

            if (function_exists('sendAdminTelegramMessage')) {
                sendAdminTelegramMessage($readyMsg);
            } else {
                error_log("Kitchen Warning: sendAdminTelegramMessage function not found.");
            }

        } catch (Exception $tgEx) {
            error_log("Telegram Error: " . $tgEx->getMessage());
        }

        // 3. Finalize core application database status values update cleanly
        $pdo->prepare("UPDATE orders SET status = 'Completed' WHERE id = ?")->execute([$order_id]); 

        // --- AUDIT TRAIL LOGGING ---
        $audit_stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, timestamp) VALUES (?, ?, NOW())");
        $audit_stmt->execute([$_SESSION['user_id'] ?? 0, "User [" . ($_SESSION['username'] ?? 'System') . "] marked Kitchen Ticket #" . $order_id . " as Completed/Ready to Serve."]);

    } catch (Exception $e) {
        error_log("Database Error in Kitchen: " . $e->getMessage());
    }

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
        <h2 class="category-title kitchen-title-no-transform">🍳 Kitchen Orders</h2>
    </div>

    <div class="grid kitchen-orders-grid">
        <?php foreach($pending as $order): 
            $items = $pdo->query("SELECT oi.*, mi.name, mi.category_id, mi.image_path FROM order_items oi JOIN menu_items mi ON oi.menu_item_id = mi.id WHERE oi.order_id = ".$order["id"])->fetchAll();
        ?>
            <div class="card kitchen-order-card">
                <div>
                    <div class="kitchen-card-header">
                        <span class="kitchen-order-number">Order #<?= $order["id"] ?></span>
                        <span class="kitchen-guest-badge"><?= htmlspecialchars($order["guest_name"]) ?></span>
                    </div>
                    
                    <div class="kitchen-items-container">
                        <?php foreach($items as $i): ?>
                            <div class="kitchen-item-row">
                                🍟 <span><strong><?= $i["quantity"] ?>x</strong> <?= htmlspecialchars($i["name"]) ?></span>
                                <?php if(!empty($i["special_instructions"])): ?>
                                    <div class="kitchen-special-instructions">⚠️ <?= htmlspecialchars($i["special_instructions"]) ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <form method="POST" id="form_kitchen_<?= $order["id"] ?>" class="kitchen-form-wrapper">
                    <input type="hidden" name="complete_order_id" value="<?= $order["id"] ?>">
                    <button type="button" class="btn btn-start kitchen-btn-ready" onclick="confirmKitchenDispatch(<?= $order["id"] ?>)">Ready</button>
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
                <div class="prompt-title kitchen-success-prompt-title">
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