<?php
// /home/apartment/artistsfarmjaipur.com/Order/order.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once "config/db.php";

if (!isset($_SESSION["user_id"])) { 
    header("Location: login.php"); 
    exit; 
}

// --- CORE ANALYTICS: EVALUATE MATERIAL STOCK THRESHOLDS ---
// Left outer joins track actual kitchen_expenses procurement counts against minimum safe alert levels
$stock_alerts = $pdo->query("
    SELECT m.item_name, m.min_threshold_qty, COALESCE(SUM(k.qty), 0) as current_available_stock
    FROM materials_registry m
    LEFT JOIN kitchen_expenses k ON LOWER(m.item_name) = LOWER(k.item_detail)
    GROUP BY m.item_name, m.min_threshold_qty
    HAVING current_available_stock <= m.min_threshold_qty
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch menu categories and active menu configurations
$categories = $pdo->query("SELECT DISTINCT category FROM menu_items ORDER BY category ASC")->fetchAll(PDO::FETCH_COLUMN);
$menu_items = $pdo->query("SELECT * FROM menu_items WHERE is_available = 1 ORDER BY item_name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Get the live active guest details token
$current_active_guest = $pdo->query("SELECT * FROM guests WHERE status = 'Active' LIMIT 1")->fetch(PDO::FETCH_ASSOC);

include "includes/header.php";
?>

<style>
.order-layout-split { display: grid; grid-template-columns: 1fr 360px; gap: 20px; width: 100%; align-items: start; }
.menu-items-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 12px; }
.menu-card-item { background: #fff; border: 1px solid #cbd5e0; border-radius: 8px; padding: 12px; text-align: left; cursor: pointer; transition: transform 0.15s; }
.menu-card-item:hover { transform: translateY(-2px); border-color: #06b6d4; }
.cart-sticky-panel { background: #f8fafc; border: 1px solid #cbd5e0; border-radius: 12px; padding: 16px; position: sticky; top: 20px; }
.alert-pill-box { padding: 8px 12px; background: #fef2f2; border: 1px dashed #ef4444; border-radius: 6px; color: #b91c1c; font-size: 11px; font-weight: bold; margin-bottom: 15px; }
</style>

<div class="app-body" style="padding: 15px; font-family: sans-serif; text-align: left;">
    
    <?php if (!empty($stock_alerts)): ?>
        <div class="alert-pill-box">
            <span style="font-size:13px;">⚠️ INVENTORY STOCK ALERT BOUNDARY THRESHOLDS:</span>
            <div style="display: flex; flex-wrap: wrap; gap: 6px; margin-top: 5px;">
                <?php foreach ($stock_alerts as $alert): ?>
                    <span style="background: #fee2e2; border: 1px solid #fca5a5; padding: 2px 6px; border-radius: 4px; font-size: 11px;">
                        <strong><?= htmlspecialchars($alert['item_name']) ?></strong> (Low: <?= $alert['current_available_stock'] ?> units left)
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div style="background: #fff; border: 1px solid #cbd5e0; border-radius: 10px; padding: 12px 20px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h3 style="margin:0; color:#0f172a;">🍽️ Point of Sale - Food Order Entry</h3>
            <p style="margin:2px 0 0 0; font-size:12px; color:#64748b;">
                Active Ticket Context: 
                <?php if ($current_active_guest): ?>
                    <strong style="color: #0891b2;">📱 Guest (<?= substr($current_active_guest['phone_number'], -4) ?>)</strong>
                <?php else: ?>
                    <span style="color:#ef4444; font-weight: bold;">No Active Guest Selected in Sidebar</span>
                <?php endif; ?>
            </p>
        </div>
    </div>

    <div class="order-layout-split">
        <div>
            <?php foreach ($categories as $cat): ?>
                <h4 style="border-left: 4px solid #06b6d4; padding-left: 8px; margin: 20px 0 10px 0; color: #1e293b; text-transform: uppercase; font-size:13px;"><?= htmlspecialchars($cat) ?></h4>
                <div class="menu-items-grid">
                    <?php 
                    foreach ($menu_items as $item): 
                        if ($item['category'] !== $cat) continue;
                    ?>
                        <div class="menu-card-item" onclick="addItemToCheckoutCart(<?= $item['id'] ?>, '<?= htmlspecialchars($item['item_name'], ENT_QUOTES) ?>', <?= $item['price'] ?>)">
                            <strong style="font-size:13px; display:block; color:#1e293b;"><?= htmlspecialchars($item['item_name']) ?></strong>
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:8px;">
                                <span style="font-size:13px; font-weight:800; color:#059669;">₹<?= number_format($item['price'], 2) ?></span>
                                <span style="font-size:10px; color:#22c55e; font-weight:700;">➕ Add</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="cart-sticky-panel">
            <h4 style="margin-top:0; border-bottom:1px solid #cbd5e0; padding-bottom:8px; text-transform:uppercase; font-size:12px;">Active Order Ticket</h4>
            
            <div id="cartEmptyPlaceholder" style="padding:30px 10px; text-align:center; color:#94a3b8; font-style:italic; font-size:13px;">
                Basket is empty.<br>Click items to load.
            </div>

            <form method="POST" action="process_food_order.php" id="checkoutCartSubmissionForm" style="display:none; margin:0;">
                <input type="hidden" name="guest_id" value="<?= $current_active_guest['id'] ?? 0 ?>">
                
                <div id="cartItemsContainerRows" style="max-height:260px; overflow-y:auto; margin-bottom:15px;"></div>
                
                <div style="border-top:2px dashed #cbd5e0; padding-top:10px; margin-bottom:15px; display:flex; justify-content:space-between; font-weight:bold; font-size:14px; color:#1e293b;">
                    <span>TICKET TOTAL:</span>
                    <span>₹<span id="labelCartTotalGross">0.00</span></span>
                </div>

                <button type="submit" <?php echo !$current_active_guest ? 'disabled' : ''; ?> style="width:100%; padding:12px; background: #059669; border:none; color:white; font-weight:bold; border-radius:6px; cursor:pointer; font-size:13px;">
                    <?php echo $current_active_guest ? 'Send Order to Kitchen' : 'Select Guest to Send'; ?>
                </button>
            </form>
        </div>
    </div>
</div>

<script>
let activeCartStateMap = {};

function addItemToCheckoutCart(id, name, price) {
    <?php if (!$current_active_guest): ?>
        alert("Please choose and activate an active guest profile ledger session inside the sidebar dropdown configuration list before recording orders.");
        return;
    <?php endif; ?>

    document.getElementById("cartEmptyPlaceholder").style.display = "none";
    document.getElementById("checkoutCartSubmissionForm").style.display = "block";

    if (activeCartStateMap[id]) {
        activeCartStateMap[id].qty++;
    } else {
        activeCartStateMap[id] = { name: name, price: price, qty: 1 };
    }
    renderCartInterfaceElements();
}

function updateCartRowQtyChange(id, delta) {
    if (!activeCartStateMap[id]) return;
    activeCartStateMap[id].qty += delta;
    if (activeCartStateMap[id].qty <= 0) {
        delete activeCartStateMap[id];
    }
    renderCartInterfaceElements();
}

function renderCartInterfaceElements() {
    const container = document.getElementById("cartItemsContainerRows");
    container.innerHTML = "";
    
    let totalGrossSum = 0;
    let keysCount = 0;

    for (let id in activeCartStateMap) {
        keysCount++;
        let row = activeCartStateMap[id];
        let rowCost = row.qty * row.price;
        totalGrossSum += rowCost;

        container.innerHTML += `
            <div style="display:flex; justify-content:space-between; align-items:center; background:white; border:1px solid #e2e8f0; border-radius:6px; padding:8px; margin-bottom:6px; font-size:12px;">
                <div style="text-align:left; max-width:160px;">
                    <strong style="color:#1e293b;">${row.name}</strong>
                    <div style="color:#64748b; font-size:11px;">₹${row.price.toFixed(2)} x ${row.qty}</div>
                </div>
                <div style="display:flex; align-items:center; gap:5px;">
                    <button type="button" onclick="updateCartRowQtyChange(${id}, -1)" style="padding:2px 6px; background:#ef4444; border:none; color:white; border-radius:4px; font-weight:bold; cursor:pointer;">-</button>
                    <span style="font-weight:bold; width:15px; text-align:center;">${row.qty}</span>
                    <button type="button" onclick="updateCartRowQtyChange(${id}, 1)" style="padding:2px 6px; background:#22c55e; border:none; color:white; border-radius:4px; font-weight:bold; cursor:pointer;">+</button>
                </div>
                <input type="hidden" name="cart_items[${id}][qty]" value="${row.qty}">
            </div>
        `;
    }

    document.getElementById("labelCartTotalGross").innerText = totalGrossSum.toFixed(2);

    if (keysCount === 0) {
        document.getElementById("cartEmptyPlaceholder").style.display = "block";
        document.getElementById("checkoutCartSubmissionForm").style.display = "none";
    }
}
</script>

<?php include "includes/footer.php"; ?>