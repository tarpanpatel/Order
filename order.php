<?php
// /home/apartment/artistsfarmjaipur.com/Order/order.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once "config/db.php";

if (!isset($_SESSION["user_id"])) { 
    header("Location: login.php"); 
    exit; 
}

// Fetching menu categories and configurations directly from the verified database mapping
$categories = $pdo->query("SELECT * FROM menu_categories ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
$menu_items = $pdo->query("SELECT * FROM menu_items WHERE is_hidden = 0 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Get the live active guest details token
$current_active_guest = $pdo->query("SELECT * FROM guests WHERE status = 'Active' LIMIT 1")->fetch(PDO::FETCH_ASSOC);

include "includes/header.php";
?>

<style>
.order-layout-split { display: grid; grid-template-columns: 1fr 360px; gap: 20px; width: 100%; align-items: start; }
.menu-items-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 12px; }

/* Card formatting exactly matching your native design style */
.menu-card-item { 
    background: var(--card-bg); 
    border-radius: var(--radius); 
    border: 1px solid #e2e8f0; 
    padding: 12px; 
    text-align: center; 
    position: relative;
    cursor: pointer; 
    transition: transform 0.15s ease, border-color 0.15s ease;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-height: 150px;
}
.menu-card-item:hover { transform: translateY(-2px); border-color: #06b6d4; }

/* Pure circular profile thumbnail item images styling rules */
.menu-card-item img { 
    width: 44px; 
    height: 44px; 
    object-fit: cover; 
    border-radius: 50%; 
    margin: 0 auto 6px auto; 
    background: #f7fafc; 
    padding: 3px; 
    display: block;
}

.cart-sticky-panel { background: #f8fafc; border: 1px solid #cbd5e0; border-radius: 12px; padding: 16px; position: sticky; top: 130px; max-height: calc(100vh - 160px); display: flex; flex-direction: column; }
</style>

<div class="app-body" style="padding: 15px; font-family: sans-serif; text-align: left;">

    <div style="background: #fff; border: 1px solid #cbd5e0; border-radius: 10px; padding: 12px 20px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; width:100%;">
        <div style="display:flex; width:100%; justify-content:space-between; align-items:center;">
            <div>
                <h3 style="margin:0; color:#0f172a;">🍽️ Point of Sale - Food Order Entry</h3>
                <p style="margin:2px 0 0 0; font-size:12px; color:#64748b;">
                    Active Ticket Context: 
                    <?php if ($current_active_guest): ?>
                        <strong style="color: #0891b2;">📱 Guest (<?= htmlspecialchars($current_active_guest['guest_name']) ?> - <?= substr($current_active_guest['phone_number'], -4) ?>)</strong>
                    <?php else: ?>
                        <span style="color:#ef4444; font-weight: bold;">No Active Guest Selected in Sidebar</span>
                    <?php endif; ?>
                </p>
            </div>
            <div style="max-width:260px; width:100%;">
                <input type="text" id="rowSearch" placeholder="Search dish name..." class="form-input-container" style="width:100%; padding:8px 12px; border-radius:8px; border:1px solid #cbd5e0;" oninput="searchMenu()">
            </div>
        </div>
    </div>

    <div class="order-layout-split">
        <div>
            <?php foreach ($categories as $cat): 
                $cat_items = array_filter($menu_items, function($m) use ($cat) { return $m['category_id'] == $cat['id']; });
                if (empty($cat_items)) continue;
            ?>
                <h4 style="border-left: 4px solid #06b6d4; padding-left: 8px; margin: 25px 0 12px 0; color: #1e293b; text-transform: uppercase; font-size:13px;" id="sec_cat_<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></h4>
                <div class="menu-items-grid">
                    <?php foreach ($cat_items as $item): ?>
                        <div class="menu-card-item menu-row-item" data-name="<?= strtolower(htmlspecialchars($item['name'])) ?>" onclick="addItemToCheckoutCart(<?= $item['id'] ?>, '<?= htmlspecialchars(addslashes($item['name'])) ?>', <?= $item['price'] ?>)">
                            
                            <div>
                                <?php if (!empty($item['image_path']) && file_exists($item['image_path'])): ?>
                                    <img src="<?= htmlspecialchars($item['image_path']) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                                <?php endif; ?>
                                
                                <div class="card-name" style="font-size: 13px; font-weight: 600; margin-bottom: 4px; color: var(--text-main); height: auto;">
                                    <?= htmlspecialchars($item['name']) ?>
                                </div>
                            </div>

                            <div style="display:flex; justify-content:space-between; align-items:center; width:100%; margin-top:8px;">
                                <span style="font-size:13px; font-weight:800; color:#059669;">₹<?= number_format($item['price'], 2) ?></span>
                                <span style="font-size:10px; color:#22c55e; font-weight:700;">➕ Add</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="cart-sticky-panel">
            <h4 style="margin-top:0; border-bottom:1px solid #cbd5e0; padding-bottom:8px; text-transform:uppercase; font-size:12px; text-align:left;">Active Order Ticket</h4>
            
            <div id="cartEmptyPlaceholder" style="padding:40px 10px; text-align:center; color:#94a3b8; font-style:italic; font-size:13px; flex:1;">
                Basket is empty.<br>Click items to load.
            </div>

            <form method="POST" action="process_food_order.php" id="checkoutCartSubmissionForm" style="display:none; margin:0; flex:1; flex-direction:column; justify-content:space-between;">
                <input type="hidden" name="guest_id" value="<?= $current_active_guest['id'] ?? 0 ?>">
                
                <div id="cartItemsContainerRows" style="max-height:320px; overflow-y:auto; margin-bottom:15px; flex:1; padding-right:2px;"></div>
                
                <div style="margin-top:auto;">
                    <div style="border-top:2px dashed #cbd5e0; padding-top:10px; margin-bottom:15px; display:flex; justify-content:space-between; font-weight:bold; font-size:14px; color:#1e293b;">
                        <span>TICKET TOTAL:</span>
                        <span>₹<span id="labelCartTotalGross">0.00</span></span>
                    </div>

                    <button type="submit" <?php echo !$current_active_guest ? 'disabled' : ''; ?> style="width:100%; padding:12px; background: #059669; border:none; color:white; font-weight:bold; border-radius:6px; cursor:pointer; font-size:13px;">
                        <?php echo $current_active_guest ? 'Send Order to Kitchen' : 'Select Guest to Send'; ?>
                    </button>
                </div>
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
    document.getElementById("checkoutCartSubmissionForm").style.display = "flex";

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

function scrollToSection(id, tab) {
    document.querySelectorAll(".nav-bar .nav-link").forEach(t => t.classList.remove("active"));
    tab.classList.add("active");
    const target = document.getElementById("sec_" + id);
    if(target) target.scrollIntoView({ behavior: 'smooth' });
}

function searchMenu() {
    let q = document.getElementById("rowSearch").value.toLowerCase().trim();
    document.querySelectorAll(".menu-row-item").forEach(row => {
        row.style.setProperty("display", row.getAttribute("data-name").includes(q) ? "flex" : "none", "important");
    });
}
</script>

<?php include "includes/footer.php"; ?>