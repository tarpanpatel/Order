<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "config/db.php";

if (!isset($_SESSION["user_id"])) { 
    header("Location: login.php"); 
    exit; 
}

// Fetch all visible menu items
$menu_items = $pdo->query("SELECT id, category_id, name, price, image_path FROM menu_items WHERE is_hidden = 0 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch all menu categories
$categories = $pdo->query("SELECT * FROM menu_categories ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);

include "includes/header.php";
?>

<style>
/* ==========================================================================
   STREAMLINED GRID AND FLEX SIDEBAR ORDER PANEL SYSTEM
   ========================================================================== */
.split-order-layout {
    display: grid !important;
    grid-template-columns: 1fr 340px !important;
    gap: 20px !important;
    width: 100% !important;
    align-items: start !important;
    margin-top: 15px;
}

.order-main-panel {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.menu-cards-box {
    background: #ffffff !important;
    border: 1px solid #e2e8f0 !important;
    border-radius: 12px !important;
    padding: 20px !important;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05) !important;
}

.order-right-sidebar {
    background: #ffffff !important;
    border: 1px solid #cbd5e0 !important;
    border-radius: 12px !important;
    padding: 20px !important;
    position: sticky !important;
    top: 20px !important;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05) !important;
    display: flex;
    flex-direction: column;
    min-height: 460px;
    text-align: left;
}

.menu-tab-header {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 20px;
    border-bottom: 1px solid #edf2f7;
    padding-bottom: 12px;
}

.menu-tab-btn {
    padding: 6px 12px;
    font-size: 12px;
    font-weight: 600;
    background: #f7fafc;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    cursor: pointer;
    color: #4a5568;
    transition: all 0.15s ease;
}

.menu-tab-btn.active {
    background: #06b6d4;
    color: white;
    border-color: #06b6d4;
}

.dish-item-grid {
    display: grid !important;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)) !important;
    gap: 12px !important;
}

.dish-item-card {
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 10px;
    background: #fff;
    text-align: center;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-height: 120px;
}

.dish-item-name {
    font-size: 12px;
    font-weight: 600;
    color: #111827;
    margin-bottom: 4px;
    line-height: 1.4;
}

.dish-item-price {
    font-size: 13px;
    font-weight: 700;
    color: #06b6d4;
    margin-bottom: 8px;
}

.sidebar-summary-title {
    font-size: 14px;
    font-weight: 700;
    text-transform: uppercase;
    color: #111827;
    border-bottom: 1px dashed #e2e8f0;
    padding-bottom: 8px;
    margin-bottom: 15px;
    margin-top: 0;
}

.sidebar-cart-list {
    flex-grow: 1;
    overflow-y: auto;
    max-height: 300px;
    margin-bottom: 15px;
}

.sidebar-cart-row {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 10px 0;
    border-bottom: 1px solid #f3f4f6;
}

.sidebar-cart-row-main {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 13px;
}

.qty-btn-sm {
    padding: 2px 8px;
    font-size: 12px;
    font-weight: bold;
    border: 1px solid #cbd5e0;
    background: #f7fafc;
    border-radius: 4px;
    cursor: pointer;
}

.cart-item-notes-input {
    width: 100%;
    padding: 5px;
    font-size: 11px;
    border: 1px solid #edf2f7;
    border-radius: 4px;
    background: #fcfdfd;
    color: #4b5563;
    box-sizing: border-box;
}

@media (max-width: 1023px) {
    .split-order-layout { grid-template-columns: 1fr !important; }
    .order-right-sidebar { position: static !important; min-height: auto; }
}
</style>

<div class="app-body" style="max-width: 100% !important; width: 100% !important; display: block !important;">
    <div class="category-section" style="margin-bottom: 20px;">
        <h2 class="category-title" style="text-transform: none;">🍽️ Digital Order Matrix Terminal</h2>
    </div>

    <div class="split-order-layout">
        
        <div class="order-main-panel">
            <div class="menu-cards-box">
                <div class="menu-tab-header">
                    <button type="button" class="menu-tab-btn active" onclick="window.filterMenuCatalog('all', this)">All Categories</button>
                    <?php foreach ($categories as $cat): ?>
                        <button type="button" class="menu-tab-btn" onclick="window.filterMenuCatalog('cat_<?= $cat['id'] ?>', this)">
                            <?= htmlspecialchars($cat['name']) ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <div id="menuCatalogRowsContainer">
                    <?php foreach ($categories as $cat): 
                        $catItems = array_filter($menu_items, function($x) use ($cat) {
                            return (intval($x['category_id']) === intval($cat['id']));
                        });
                        if (empty($catItems)) continue;
                    ?>
                        <div class="category-block" id="cat_<?= $cat['id'] ?>" style="margin-bottom: 25px;">
                            <h4 style="font-size: 13px; text-transform: uppercase; color: #4b5563; text-align: left; margin-bottom: 12px; font-weight: 700;"><?= htmlspecialchars($cat['name']) ?></h4>
                            <div class="dish-item-grid">
                                <?php foreach ($catItems as $item): ?>
                                    <div class="dish-item-card">
                                        <div>
                                            <div class="dish-item-name"><?= htmlspecialchars($item['name']) ?></div>
                                            <div class="dish-item-price">₹<?= number_format($item['price'], 0) ?></div>
                                        </div>
                                        <button type="button" class="btn btn-start" style="padding: 6px; font-size: 11px; width: 100%; border-radius: 6px;" onclick="window.addFoodItemToCart(<?= $item['id'] ?>, '<?= htmlspecialchars(addslashes($item['name'])) ?>', <?= floatval($item['price']) ?>)">+ Add To Cart</button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="order-right-sidebar">
            <h3 class="sidebar-summary-title">🛒 Cart Summary</h3>
            <div class="sidebar-cart-list" id="sidebarCartRowsContainer">
                <p style="color: #a0aec0; text-align: center; font-size: 13px; margin-top: 50px; font-style: italic;">Your order cart is completely empty.</p>
            </div>
            
            <div style="border-top: 1px dashed #e2e8f0; padding-top: 15px;">
                <div style="display: flex; justify-content: space-between; font-weight: 700; font-size: 15px; margin-bottom: 15px; color: #111827;">
                    <span>Grand Total:</span>
                    <span id="sidebarCartGrandTotal">₹0</span>
                </div>
                <button type="button" class="btn btn-bill" style="width: 100%; padding: 12px; font-size: 13px; font-weight: bold; border-radius: 8px;" onclick="window.submitKitchenOrder()">Place Kitchen Order</button>
            </div>
        </div>

    </div>
</div>

<script>
// ==========================================================================
// EXPEL DOMCONTENTLOADED WRAPPERS TO ENSURE LIVE SPA ATTACHMENT COMPATIBILITY
// ==========================================================================
window.foodCart = [];

window.addFoodItemToCart = function(id, name, price) {
    let existing = window.foodCart.find(x => x.id === id);
    if (existing) {
        existing.qty += 1;
    } else {
        window.foodCart.push({ id: id, name: name, price: price, qty: 1, notes: '' });
    }
    window.renderOrderCart();
};

window.updateCartQty = function(id, delta) {
    let item = window.foodCart.find(x => x.id === id);
    if (item) {
        item.qty += delta;
        if (item.qty <= 0) {
            window.foodCart = window.foodCart.filter(x => x.id !== id);
        }
    }
    window.renderOrderCart();
};

window.updateCartNotes = function(id, value) {
    let item = window.foodCart.find(x => x.id === id);
    if (item) {
        item.notes = value;
    }
};

window.renderOrderCart = function() {
    const container = document.getElementById("sidebarCartRowsContainer");
    const totalEl = document.getElementById("sidebarCartGrandTotal");
    
    if (!container || !totalEl) return;

    if (window.foodCart.length === 0) {
        container.innerHTML = '<p style="color: #a0aec0; text-align: center; font-size: 13px; margin-top: 50px; font-style: italic;">Your order cart is completely empty.</p>';
        totalEl.innerText = "₹0";
        return;
    }

    let grandTotal = 0;
    container.innerHTML = window.foodCart.map(item => {
        const cost = item.price * item.qty;
        grandTotal += cost;
        return `
            <div class="sidebar-cart-row">
                <div class="sidebar-cart-row-main">
                    <div style="font-weight: 600; color: #111827; max-width: 170px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${item.name}</div>
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <button type="button" class="qty-btn-sm" onclick="window.updateCartQty(${item.id}, -1)">-</button>
                        <span style="font-weight: 700; font-size: 13px; width: 18px; text-align: center;">${item.qty}</span>
                        <button type="button" class="qty-btn-sm" onclick="window.updateCartQty(${item.id}, 1)">+</button>
                    </div>
                </div>
                <input type="text" class="cart-item-notes-input" placeholder="Cooking instructions..." value="${item.notes}" onchange="window.updateCartNotes(${item.id}, this.value)">
            </div>
        `;
    }).join('');

    totalEl.innerText = "₹" + grandTotal.toLocaleString('en-IN');
};

window.filterMenuCatalog = function(catId, btn) {
    document.querySelectorAll(".menu-tab-btn").forEach(b => b.classList.remove("active"));
    if (btn) btn.classList.add("active");
    document.querySelectorAll(".category-block").forEach(block => {
        block.style.display = (catId === 'all' || block.id === catId) ? "block" : "none";
    });
};

window.submitKitchenOrder = function() {
    if (window.foodCart.length === 0) return alert("Your order cart list is empty.");
    if (!confirm("Send this food order to the kitchen?")) return;

    fetch("api/process_order.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ items: window.foodCart })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert("✔ Food order sent to kitchen successfully!");
            window.foodCart = [];
            window.renderOrderCart();
            // If global history state reload handles routing parameters softly
            location.reload();
        } else {
            alert("❌ Entry failed: " + data.error);
        }
    })
    .catch(() => alert("❌ Critical processing communication fault error."));
};

// Global initializer fallback call triggered automatically by site-wide footer hooks
window.loadCatalog = function() {
    window.foodCart = [];
    window.renderOrderCart();
    window.filterMenuCatalog('all', document.querySelector(".menu-tab-btn"));
};
</script>

<?php include "includes/footer.php"; ?>