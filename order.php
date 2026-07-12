<?php
// /home/apartment/artistsfarmjaipur.com/Order/order.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }[cite: 8]
require_once "config/db.php";[cite: 8]

// 1. Load the system headers first so any internal sidebar variables execute first[cite: 8]
include "includes/header.php";[cite: 8]

// 2. NOW pull the food items into a uniquely named variable to completely prevent collisions[cite: 8]
$categories = $pdo->query("SELECT * FROM menu_categories ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);[cite: 8]
$food_catalog_items = $pdo->query("SELECT * FROM menu_items WHERE is_hidden = 0 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);[cite: 8]

// Get the live active guest details token[cite: 8]
$current_active_guest = $pdo->query("SELECT * FROM guests WHERE status = 'Active' LIMIT 1")->fetch(PDO::FETCH_ASSOC);[cite: 8]
?>

<div class="app-body">[cite: 8]

    <div class="category-section">[cite: 8]
        <h2 class="mb-4">🍽️ Food Order Entry</h2>[cite: 8]
        <p class="active-ticket-context-wrapper">[cite: 8]
            Active Ticket Context:  
            <?php if ($current_active_guest): ?>[cite: 8]
                <strong class="active-ticket-guest-highlight">📱 Guest (<?= htmlspecialchars($current_active_guest['guest_name']) ?> - <?= substr($current_active_guest['phone_number'], -4) ?>)</strong>[cite: 8]
            <?php else: ?>[cite: 8]
                <span class="active-ticket-no-guest">No Active Guest Selected in Sidebar</span>[cite: 8]
            <?php endif; ?>[cite: 8]
        </p>[cite: 8]
    </div> 

    <div class="split-requisition-layout">[cite: 8]
        <div class="materials-main-panel">[cite: 8]
            <div class="catalog-cards-box">[cite: 8]
                <div class="search-input-wrapper">[cite: 8]
                    <span class="search-icon-inside">🔍</span>[cite: 8]
                    <input type="text" id="catalogQuickSearchInput" class="btn-quick-search-box" placeholder="Quick search menu items on the fly..." onkeyup="window.searchMenu()">[cite: 8]
                </div> 

                <div class="catalog-tab-header">[cite: 8]
                    <button type="button" id="globalAllTabBtn" class="catalog-tab-btn active" onclick="window.filterMenuCatalog('all', this)">All Menu</button>[cite: 8]
                    <?php foreach ($categories as $cat): ?>[cite: 8]
                        <button type="button" class="catalog-tab-btn" onclick="window.filterMenuCatalog('cat_<?= $cat['id'] ?>', this)"><?= htmlspecialchars($cat['name']) ?></button>[cite: 8]
                    <?php endforeach; ?>[cite: 8]
                </div> 

                <div id="menuCatalogContainer"> 
                    <?php foreach ($categories as $cat): 
                        // FIXED: Uses our unique, collision-proof variable '$food_catalog_items' matching 'category_id'[cite: 8]
                        $cat_items = array_filter($food_catalog_items, function($m) use ($cat) { 
                            return isset($m['category_id']) && $m['category_id'] == $cat['id']; 
                        });
                        if (empty($cat_items)) continue;[cite: 8]
                    ?>
                        <div class="category-block mb-15" id="cat_<?= $cat['id'] ?>">[cite: 8]
                            <h4 class="category-block-title category-title-custom">[cite: 8]
                                <?= htmlspecialchars($cat['name']) ?>[cite: 8]
                            </h4>[cite: 8]
                            <div class="material-item-grid">[cite: 8]
                                <?php foreach ($cat_items as $item): ?>[cite: 8]
                                    <div class="material-item-card" data-search-name="<?= strtolower(htmlspecialchars($item['name'])) ?>">[cite: 8]
                                        <div class="material-item-image-box">[cite: 8]
                                            <?php if (!empty($item['image_path']) && file_exists($item['image_path'])): ?>[cite: 8]
                                                <img src="<?= htmlspecialchars($item['image_path']) ?>" alt="">[cite: 8]
                                            <?php else: ?>[cite: 8]
                                                <img src="https://placehold.co/150x100?text=No+Image" alt="">[cite: 8]
                                            <?php endif; ?>[cite: 8]
                                        </div>[cite: 8]
                                        <div class="material-item-name">[cite: 8]
                                            <strong><?= htmlspecialchars($item['name']) ?></strong>[cite: 8]
                                            <div>₹<?= number_format($item['price'], 2) ?></div>[cite: 8]
                                        </div>[cite: 8]
                                        <button type="button" class="btn-tab-styled-add" 
                                            onclick="window.addItemToCheckoutCart(this, <?= $item['id'] ?>, '<?= htmlspecialchars(addslashes($item['name'])) ?>', <?= $item['price'] ?>)">[cite: 8]
                                            + Add[cite: 8]
                                        </button>[cite: 8]
                                    </div>[cite: 8]
                                <?php endforeach; ?>[cite: 8]
                            </div>[cite: 8]
                        </div>[cite: 8]
                    <?php endforeach; ?>[cite: 8]
                </div> 
            </div> 
        </div> 

        <div class="right-column-stack">[cite: 8]
            <div class="requisition-right-sidebar" id="mobileSummaryStickyWrapper" onclick="window.handleMobileDrawerCollapseToggle(event)">[cite: 8]
                <h3 class="sidebar-summary-title">📝 Active Order Ticket</h3>[cite: 8]
                
                <div id="cartEmptyPlaceholder" class="sidebar-placeholder-msg">[cite: 8]
                    Basket is empty.<br>Click items to load.[cite: 8]
                </div> 

                <form id="checkoutCartSubmissionForm" onsubmit="window.submitFoodOrder(event)" class="checkout-cart-form">[cite: 8]
                    <input type="hidden" name="guest_id" value="<?= $current_active_guest['id'] ?? 0 ?>">[cite: 8]
                    
                    <div class="sidebar-cart-list" id="cartItemsContainerRows"></div>[cite: 8]
                    
                    <div>[cite: 8]
                        <div class="sidebar-total-row-wrapper">[cite: 8]
                            <span class="total-types-label sidebar-total-label-green">TICKET TOTAL:</span>[cite: 8]
                            <span class="total-types-value sidebar-total-label-green">₹<span id="labelCartTotalGross">0.00</span></span>[cite: 8]
                        </div> 
                        <button type="submit" <?php echo !$current_active_guest ? 'disabled' : ''; ?> class="btn btn-bill btn-send-kitchen">[cite: 8]
                            <?php echo $current_active_guest ? 'Send Order to Kitchen' : 'Select Guest to Send'; ?>[cite: 8]
                        </button> 
                    </div> 
                </form>[cite: 8]
            </div> 
        </div> 
    </div> 
</div> 

<script>
window.activeCartStateMap = {};[cite: 8]
let activeFilteredTabId = 'all';[cite: 8]

window.handleMobileDrawerCollapseToggle = function(event) {
    if (window.innerWidth >= 1024) return; [cite: 8]
    const sidebar = document.getElementById("mobileSummaryStickyWrapper");[cite: 8]
    if (event.target.closest('.sidebar-summary-title')) {
        sidebar.classList.toggle("drawer-open-state");[cite: 8]
    }
};

window.searchMenu = function() {
    const inputVal = document.getElementById("catalogQuickSearchInput").value.toLowerCase().trim();[cite: 8]
    const blocks = document.querySelectorAll(".category-block");[cite: 8]
    
    if (inputVal !== "" && activeFilteredTabId !== 'all') {
        activeFilteredTabId = 'all';[cite: 8]
        document.querySelectorAll(".catalog-tab-btn").forEach(b => b.classList.remove("active"));[cite: 8]
        document.getElementById("globalAllTabBtn").classList.add("active");[cite: 8]
    }

    blocks.forEach(block => {
        let parentHasVisibleItem = false;[cite: 8]
        const cards = block.querySelectorAll(".material-item-card");[cite: 8]
        
        cards.forEach(card => {
            const searchName = card.getAttribute("data-search-name") || "";[cite: 8]
            const isTabMatch = (activeFilteredTabId === 'all' || block.id === activeFilteredTabId);[cite: 8]
            const isSearchMatch = searchName.includes(inputVal);[cite: 8]

            if (isTabMatch && isSearchMatch) {
                card.style.setProperty("display", "flex", "important");[cite: 8]
                parentHasVisibleItem = true;[cite: 8]
            } else {
                card.style.setProperty("display", "none", "important");[cite: 8]
            }
        });

        block.style.display = parentHasVisibleItem ? "block" : "none";[cite: 8]
    });
};

window.filterMenuCatalog = function(catId, btn) {
    activeFilteredTabId = catId;[cite: 8]
    document.getElementById("catalogQuickSearchInput").value = ""; [cite: 8]

    document.querySelectorAll(".catalog-tab-btn").forEach(b => b.classList.remove("active"));[cite: 8]
    if (btn) btn.classList.add("active");[cite: 8]
    
    document.querySelectorAll(".category-block").forEach(block => {
        if (catId === 'all' || block.id === catId) {
            block.style.display = "block";[cite: 8]
            block.querySelectorAll(".material-item-card").forEach(c => c.style.setProperty("display", "flex", "important"));[cite: 8]
        } else {
            block.style.display = "none";[cite: 8]
        }
    });
};

window.addItemToCheckoutCart = function(btn, id, name, price) { 
    btn.classList.add('is-clicked-active');[cite: 8]
    btn.innerText = "✔ Added";[cite: 8]
    setTimeout(() => {
        btn.classList.remove('is-clicked-active');[cite: 8]
        btn.innerText = "+ Add";[cite: 8]
    }, 400);[cite: 8]

    if (activeCartStateMap[id]) {
        activeCartStateMap[id].qty++;[cite: 8]
    } else {
        activeCartStateMap[id] = { name: name, price: price, qty: 1 };[cite: 8]
    }
    renderCartInterfaceElements();[cite: 8]
};

window.updateCartRowQtyChange = function(id, delta) {
    if (!window.activeCartStateMap[id]) return;[cite: 8]
    window.activeCartStateMap[id].qty += delta;
    if (window.activeCartStateMap[id].qty <= 0) {
        delete window.activeCartStateMap[id];[cite: 8]
    }
    window.renderCartInterfaceElements();[cite: 8]
};

window.renderCartInterfaceElements = function() {
    const container = document.getElementById("cartItemsContainerRows");[cite: 8]
    const form = document.getElementById("checkoutCartSubmissionForm");[cite: 8]
    const placeholder = document.getElementById("cartEmptyPlaceholder");[cite: 8]
    
    container.innerHTML = "";[cite: 8]
    
    let totalGrossSum = 0;[cite: 8]
    let keysCount = 0;[cite: 8]

    for (let id in window.activeCartStateMap) {
        keysCount++;[cite: 8]
        let row = window.activeCartStateMap[id];[cite: 8]
        let rowCost = row.qty * row.price;[cite: 8]
        totalGrossSum += rowCost;[cite: 8]

        container.innerHTML += `
            <div class="sidebar-cart-row-node">
                <div class="sidebar-cart-item-name-node">
                    \${row.name} <span class="sidebar-cart-item-price-tag">(₹\${row.price})</span>
                </div>
                <div class="sidebar-cart-qty-controls-node">
                    <button type="button" class="qty-btn-sm-node" onclick="window.updateCartRowQtyChange(\${id}, -1)">-</button>
                    <span class="qty-val-display-node">\${row.qty}</span>
                    <button type="button" class="qty-btn-sm-node" onclick="window.updateCartRowQtyChange(\${id}, 1)">+</button>
                </div>
                <input type="hidden" name="cart_items[\${id}][qty]" value="\${row.qty}">
            </div>
        `;[cite: 8]
    }

    document.getElementById("labelCartTotalGross").innerText = totalGrossSum.toFixed(2);[cite: 8]

    if (keysCount === 0) {
        placeholder.style.display = "block";[cite: 8]
        form.style.display = "none";[cite: 8]
    } else {
        placeholder.style.display = "none";[cite: 8]
        form.style.display = "flex";[cite: 8]
    }
};

window.submitFoodOrder = function(event) {
    event.preventDefault();[cite: 8]

    const cartItems = [];[cite: 8]
    for (let id in window.activeCartStateMap) {
        cartItems.push({
            id: id,
            qty: window.activeCartStateMap[id].qty,[cite: 8]
            notes: ""  
        });
    }

    if (cartItems.length === 0) {
        alert("Cart is empty.");[cite: 8]
        return;[cite: 8]
    }

    fetch("process_order.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ items: cartItems })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert("✔ Order sent to kitchen successfully!");[cite: 8]
            window.activeCartStateMap = {};[cite: 8]
            window.renderCartInterfaceElements();[cite: 8]
        } else {
            alert("❌ Order Failed: " + (data.message || "Unknown error"));[cite: 8]
        }
    })
    .catch(err => {
        console.error(err);[cite: 8]
        alert("❌ Network error: Could not reach process_order.php");[cite: 8]
    });
};
</script>

<?php include "includes/footer.php"; ?>[cite: 8]