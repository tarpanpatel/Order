<?php
// /home/apartment/artistsfarmjaipur.com/Order/order.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once "config/db.php";

// 1. Include header first so sidebar setup variables run and clear out of the global stack
include "includes/header.php";

// 2. Query food details using a custom variable name ($food_catalog_items) to stop array collisions
$categories = $pdo->query("SELECT * FROM menu_categories ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
$food_catalog_items = $pdo->query("SELECT * FROM menu_items WHERE is_hidden = 0 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// 3. Read live guest parameters safely
$current_active_guest = $pdo->query("SELECT * FROM guests WHERE status = 'Active' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
?>

<div class="app-body">

    <div class="category-section">
        <h2 class="mb-4">🍽️ Food Order Entry</h2>
        <p class="active-ticket-context-wrapper">
            Active Ticket Context: 
            <?php if ($current_active_guest): ?>
                <strong class="active-ticket-guest-highlight">📱 Guest (<?= htmlspecialchars($current_active_guest['guest_name']) ?> - <?= substr($current_active_guest['phone_number'], -4) ?>)</strong>
            <?php else: ?>
                <span class="active-ticket-no-guest">No Active Guest Selected in Sidebar</span>
            <?php endif; ?>
        </p>
    </div>

    <div class="split-requisition-layout">
        <div class="materials-main-panel">
            <div class="catalog-cards-box">
                <div class="search-input-wrapper">
                    <span class="search-icon-inside">🔍</span>
                    <input type="text" id="catalogQuickSearchInput" class="btn-quick-search-box" placeholder="Quick search menu items on the fly..." onkeyup="window.searchMenu()">
                </div>

                <div class="catalog-tab-header">
                    <button type="button" id="globalAllTabBtn" class="catalog-tab-btn active" onclick="window.filterMenuCatalog('all', this)">All Menu</button>
                    <?php foreach ($categories as $cat): ?>
                        <button type="button" class="catalog-tab-btn" onclick="window.filterMenuCatalog('cat_<?= $cat['id'] ?>', this)"><?= htmlspecialchars($cat['name']) ?></button>
                    <?php endforeach; ?>
                </div>

                <div id="menuCatalogContainer">
                    <?php foreach ($categories as $cat): 
                        // Target the correct category_id field from our collision-proof food array
                        $cat_items = array_filter($food_catalog_items, function($m) use ($cat) { 
                            return isset($m['category_id']) && $m['category_id'] == $cat['id']; 
                        });
                        if (empty($cat_items)) continue;
                    ?>
                        <div class="category-block mb-15" id="cat_<?= $cat['id'] ?>">
                            <h4 class="category-block-title category-title-custom">
                                <?= htmlspecialchars($cat['name']) ?>
                            </h4>
                            <div class="material-item-grid">
                                <?php foreach ($cat_items as $item): ?>
                                    <div class="material-item-card" data-search-name="<?= strtolower(htmlspecialchars($item['name'])) ?>">
                                        <div class="material-item-image-box">
                                            <?php if (!empty($item['image_path']) && file_exists($item['image_path'])): ?>
                                                <img src="<?= htmlspecialchars($item['image_path']) ?>" alt="">
                                            <?php else: ?>
                                                <img src="https://placehold.co/150x100?text=No+Image" alt="">
                                            <?php endif; ?>
                                        </div>
                                        <div class="material-item-name">
                                            <strong><?= htmlspecialchars($item['name']) ?></strong>
                                            <div>₹<?= number_format($item['price'], 2) ?></div>
                                        </div>
                                        <button type="button" class="btn-tab-styled-add" 
                                            onclick="window.addItemToCheckoutCart(this, <?= $item['id'] ?>, '<?= htmlspecialchars(addslashes($item['name'])) ?>', <?= $item['price'] ?>)">
                                            + Add
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="right-column-stack">
            <div class="requisition-right-sidebar" id="mobileSummaryStickyWrapper" onclick="window.handleMobileDrawerCollapseToggle(event)">
                <h3 class="sidebar-summary-title">📝 Active Order Ticket</h3>
                
                <div id="cartEmptyPlaceholder" class="sidebar-placeholder-msg">
                    Basket is empty.<br>Click items to load.
                </div>

                <form id="checkoutCartSubmissionForm" onsubmit="window.submitFoodOrder(event)" class="checkout-cart-form">
                    <input type="hidden" name="guest_id" value="<?= $current_active_guest['id'] ?? 0 ?>">
                    
                    <div class="sidebar-cart-list" id="cartItemsContainerRows"></div>
                    
                    <div>
                        <div class="sidebar-total-row-wrapper">
                            <span class="total-types-label sidebar-total-label-green">TICKET TOTAL:</span>
                            <span class="total-types-value sidebar-total-label-green">₹<span id="labelCartTotalGross">0.00</span></span>
                        </div>
                        <button type="submit" <?php echo !$current_active_guest ? 'disabled' : ''; ?> class="btn btn-bill btn-send-kitchen">
                            <?php echo $current_active_guest ? 'Send Order to Kitchen' : 'Select Guest to Send'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
window.activeCartStateMap = {};
let activeFilteredTabId = 'all';

window.handleMobileDrawerCollapseToggle = function(event) {
    if (window.innerWidth >= 1024) return; 
    const sidebar = document.getElementById("mobileSummaryStickyWrapper");
    if (event.target.closest('.sidebar-summary-title')) {
        sidebar.classList.toggle("drawer-open-state");
    }
};

window.searchMenu = function() {
    const inputVal = document.getElementById("catalogQuickSearchInput").value.toLowerCase().trim();
    const blocks = document.querySelectorAll(".category-block");
    
    if (inputVal !== "" && activeFilteredTabId !== 'all') {
        activeFilteredTabId = 'all';
        document.querySelectorAll(".catalog-tab-btn").forEach(b => b.classList.remove("active"));
        document.getElementById("globalAllTabBtn").classList.add("active");
    }

    blocks.forEach(block => {
        let parentHasVisibleItem = false;
        const cards = block.querySelectorAll(".material-item-card");
        
        cards.forEach(card => {
            const searchName = card.getAttribute("data-search-name") || "";
            const isTabMatch = (activeFilteredTabId === 'all' || block.id === activeFilteredTabId);
            const isSearchMatch = searchName.includes(inputVal);

            if (isTabMatch && isSearchMatch) {
                card.style.setProperty("display", "flex", "important");
                parentHasVisibleItem = true;
            } else {
                card.style.setProperty("display", "none", "important");
            }
        });

        block.style.display = parentHasVisibleItem ? "block" : "none";
    });
};

window.filterMenuCatalog = function(catId, btn) {
    activeFilteredTabId = catId;
    document.getElementById("catalogQuickSearchInput").value = "";

    document.querySelectorAll(".catalog-tab-btn").forEach(b => b.classList.remove("active"));
    if (btn) btn.classList.add("active");
    
    document.querySelectorAll(".category-block").forEach(block => {
        if (catId === 'all' || block.id === catId) {
            block.style.display = "block";
            block.querySelectorAll(".material-item-card").forEach(c => c.style.setProperty("display", "flex", "important"));
        } else {
            block.style.display = "none";
        }
    });
};

window.addItemToCheckoutCart = function(btn, id, name, price) { 
    btn.classList.add('is-clicked-active');
    btn.innerText = "✔ Added";
    setTimeout(() => {
        btn.classList.remove('is-clicked-active');
        btn.innerText = "+ Add";
    }, 400);

    if (activeCartStateMap[id]) {
        activeCartStateMap[id].qty++;
    } else {
        activeCartStateMap[id] = { name: name, price: price, qty: 1 };
    }
    renderCartInterfaceElements();
};

window.updateCartRowQtyChange = function(id, delta) {
    if (!window.activeCartStateMap[id]) return;
    window.activeCartStateMap[id].qty += delta;
    if (window.activeCartStateMap[id].qty <= 0) {
        delete window.activeCartStateMap[id];
    }
    window.renderCartInterfaceElements();
};

window.renderCartInterfaceElements = function() {
    const container = document.getElementById("cartItemsContainerRows");
    const form = document.getElementById("checkoutCartSubmissionForm");
    const placeholder = document.getElementById("cartEmptyPlaceholder");
    
    container.innerHTML = "";
    
    let totalGrossSum = 0;
    let keysCount = 0;

    for (let id in window.activeCartStateMap) {
        keysCount++;
        let row = window.activeCartStateMap[id];
        let rowCost = row.qty * row.price;
        totalGrossSum += rowCost;

        window.renderCartInterfaceElements = function() {
    const container = document.getElementById("cartItemsContainerRows");
    const form = document.getElementById("checkoutCartSubmissionForm");
    const placeholder = document.getElementById("cartEmptyPlaceholder");
    
    container.innerHTML = "";
    
    let totalGrossSum = 0;
    let keysCount = 0;

    for (let id in window.activeCartStateMap) {
        keysCount++;
        let row = window.activeCartStateMap[id];
        let rowCost = row.qty * row.price;
        totalGrossSum += rowCost;

        // FIXED: Stripped backslash escapes so JavaScript evaluates variables dynamically
        container.innerHTML += `
            <div class="sidebar-cart-row-node">
                <div class="sidebar-cart-item-name-node">
                    ${row.name} <span class="sidebar-cart-item-price-tag">(₹${row.price})</span>
                </div>
                <div class="sidebar-cart-qty-controls-node">
                    <button type="button" class="qty-btn-sm-node" onclick="window.updateCartRowQtyChange(${id}, -1)">-</button>
                    <span class="qty-val-display-node">${row.qty}</span>
                    <button type="button" class="qty-btn-sm-node" onclick="window.updateCartRowQtyChange(${id}, 1)">+</button>
                </div>
                <input type="hidden" name="cart_items[${id}][qty]" value="${row.qty}">
            </div>
        `;
    }

    document.getElementById("labelCartTotalGross").innerText = totalGrossSum.toFixed(2);

    if (keysCount === 0) {
        placeholder.style.display = "block";
        form.style.display = "none";
    } else {
        placeholder.style.display = "none";
        form.style.display = "flex";
    }
};
   

    document.getElementById("labelCartTotalGross").innerText = totalGrossSum.toFixed(2);

    if (keysCount === 0) {
        placeholder.style.display = "block";
        form.style.display = "none";
    } else {
        placeholder.style.display = "none";
        form.style.display = "flex";
    }
};

window.submitFoodOrder = function(event) {
    event.preventDefault();

    const cartItems = [];
    for (let id in window.activeCartStateMap) {
        cartItems.push({
            id: id,
            qty: window.activeCartStateMap[id].qty,
            notes: ""
        });
    }

    if (cartItems.length === 0) {
        alert("Cart is empty.");
        return;
    }

    fetch("process_order.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ items: cartItems })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert("✔ Order sent to kitchen successfully!");
            window.activeCartStateMap = {};
            window.renderCartInterfaceElements();
        } else {
            alert("❌ Order Failed: " + (data.message || "Unknown error"));
        }
    })
    .catch(err => {
        console.error(err);
        alert("❌ Network error: Could not reach process_order.php");
    });
};
</script>

<?php include "includes/footer.php"; ?>