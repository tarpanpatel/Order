<?php
require_once "config/db.php";
if (!isset($_SESSION["user_id"]) && !isset($_SESSION["order_authenticated"])) { header("Location: order_gate.php"); exit; }

if (isset($_POST["complete_order_id"])) { 
    $pdo->prepare("UPDATE orders SET status = 'Completed' WHERE id = ?")->execute([$_POST["complete_order_id"]]); 
    header("Location: kitchen.php"); exit;
}

$pending = $pdo->query("SELECT o.id, g.guest_name FROM orders o JOIN guests g ON o.guest_id = g.id WHERE o.status = 'Pending' ORDER BY o.id ASC")->fetchAll();
include "includes/header.php";
?>

<div id="kitchenPromptModal" class="prompt-modal">
    <div class="prompt-box">
        <div class="prompt-title">Confirm Ticket Status</div>
        <div class="prompt-desc">Is this food order cooked and ready for dispatch?</div>
        <div class="prompt-actions">
            <button class="prompt-btn p-btn-confirm" id="kitchenConfirmBtn">Yes, Ready</button>
            <button class="prompt-btn p-btn-cancel" onclick="document.getElementById('kitchenPromptModal').style.display='none'">No</button>
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
    document.getElementById("kitchenPromptModal").style.display = "flex";
}
document.getElementById("kitchenConfirmBtn").onclick = function() {
    if(activeFormId) document.getElementById(activeFormId).submit();
};
</script>
<?php include "includes/footer.php"; ?>