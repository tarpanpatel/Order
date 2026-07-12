<?php
// /home/apartment/artistsfarmjaipur.com/Order/order.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }[cite: 12]
require_once "config/db.php";[cite: 12]

// 1. Fetch data raw from database storage arrays
$categories = $pdo->query("SELECT * FROM menu_categories ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);[cite: 12]
$menu_items = $pdo->query("SELECT * FROM menu_items WHERE is_hidden = 0 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);[cite: 12]

// Get the live active guest details token
$current_active_guest = $pdo->query("SELECT * FROM guests WHERE status = 'Active' LIMIT 1")->fetch(PDO::FETCH_ASSOC);[cite: 12]

include "includes/header.php";[cite: 12]
?>

<div class="app-body">[cite: 12]

    <div class="category-section">[cite: 12]
        <h2 class="mb-4">🍽️ Food Order Entry</h2>[cite: 12]
        <p class="active-ticket-context-wrapper">[cite: 12]
            Active Ticket Context:  
            <?php if ($current_active_guest): ?>[cite: 12]
                <strong class="active-ticket-guest-highlight">📱 Guest (<?= htmlspecialchars($current_active_guest['guest_name']) ?> - <?= substr($current_active_guest['phone_number'], -4) ?>)</strong>[cite: 12]
            <?php else: ?>[cite: 12]
                <span class="active-ticket-no-guest">No Active Guest Selected in Sidebar</span>[cite: 12]
            <?php endif; ?>[cite: 12]
        </p> 
    </div> 

    <div class="split-requisition-layout">[cite: 12]
        <div class="materials-main-panel">[cite: 12]
            <div class="catalog-cards-box">[cite: 12]
                <div class="search-input-wrapper">[cite: 12]
                    <span class="search-icon-inside">🔍</span>[cite: 12]
                    <input type="text" id="catalogQuickSearchInput" class="btn-quick-search-box" placeholder="Quick search menu items on the fly..." onkeyup="window.runLiveSearch(this.value)">[cite: 12]
                </div> 

                <div class="catalog-tab-header">[cite: 12]
                    <button type="button" id="globalAllTabBtn" class="catalog-tab-btn active" onclick="window.switchActiveCategory('all', this)">All Menu</button>[cite: 12]
                    <?php foreach ($categories as $cat): ?>[cite: 12]
                        <button type="button" class="catalog-tab-btn" onclick="window.switchActiveCategory(<?= $cat['id'] ?>, this)"><?= htmlspecialchars($cat['name']) ?></button>[cite: 12]
                    <?php endforeach; ?>[cite: 12]
                </div> 

                <!-- Native container where Javascript will directly mount data -->
                <div id="menuCatalogContainer"></div> 
            </div> 
        </div> 

        <div class="right-column-stack">[cite: 12]
            <div class="requisition-right-sidebar" id="mobileSummaryStickyWrapper" onclick="window.handleMobileDrawerCollapseToggle(event)">[cite: 12]
                <h3 class="sidebar-summary-title">📝 Active Order Ticket</h3>[cite: 12]
                
                <div id="cartEmptyPlaceholder" class="sidebar-placeholder-msg">[cite: 12]
                    Basket is empty.<br>Click items to load.[cite: 12]
                </div> 

                <form id="checkoutCartSubmissionForm" onsubmit="window.submitFoodOrder(event)" class="checkout-cart-form">[cite: 12]
                    <input type="hidden" name="guest_id" value="<?= $current_active_guest['id'] ?? 0 ?>">[cite: 12]
                    
                    <div class="sidebar-cart-list" id="cartItemsContainerRows"></div>[cite: 12]
                    
                    <div>[cite: 12]
                        <div class="sidebar-total-row-wrapper">[cite: 12]
                            <span class="total-types-label sidebar-total-label-green">TICKET TOTAL:</span>[cite: 12]
                            <span class="total-types-value sidebar-total-label-green">₹<span id="labelCartTotalGross">0.00</span></span>[cite: 12]
                        </div> 
                        <button type="submit" <?php echo !$current_active_guest ? 'disabled' : ''; ?> class="btn btn-bill btn-send-kitchen">[cite: 12]
                            <?php echo $current_active_guest ? 'Send Order to Kitchen' : 'Select Guest to Send'; ?>[cite: 12]
                        </button> 
                    </div> 
                </form> 
            </div> 
        </div> 
    </div> 
</div> 

<!-- DAF SAFE TRANSFERRED POINTER DATA ARRAYS -->
<script id="raw-categories-json" type="application/json"><?= json_encode($categories) ?></script>
<script id="raw-items-json" type="application/json"><?= json_encode($menu_items) ?></script>

<script>
// SELF-INITIALIZING APPLICATION HOOK ENGINE
(function initializeOrderEntryApp() {
    // 1. Safely pull data from json nodes to clear column mapping casing discrepancies dynamically
    const rawCats = JSON.parse(document.getElementById('raw-categories-json').textContent);
    const rawItems = JSON.parse(document.getElementById('raw-items-json').textContent);
    
    // Normalize properties to lowercase map arrays smoothly
    const categoriesData = rawCats.map(c => arrayKeysToLowerCase(c));
    const itemsData = rawItems.map(i => arrayKeysToLowerCase(i));

    window.activeCartStateMap = window.activeCartStateMap || {};[cite: 12]
    let currentSelectedCategory = 'all';
    let activeSearchString = '';

    function arrayKeysToLowerCase(obj) {
        return Object.keys(obj).reduce((acc, key) => {
            acc[key.toLowerCase()] = obj[key];
            return acc;
        }, {});
    }

    // 2. Main Render Engine
    window.renderCatalogGridTemplate = function() {
        const targetContainer = document.getElementById("menuCatalogContainer");
        if (!targetContainer) return;

        let trackingHtmlMarkup = "";

        categoriesData.forEach(category => {
            // Filter out elements matching category relational pointer signatures safely
            const filteredGroupItems = itemsData.filter(item => {
                const itemCatId = item.category_id ?? item.category ?? null;
                const matchesCategory = (currentSelectedCategory === 'all' || Number(itemCatId) === Number(currentSelectedCategory));
                const matchesSearch = !activeSearchString || (item.name && item.name.toLowerCase().includes(activeSearchString));
                return matchesCategory && matchesSearch;
            });

            if (filteredGroupItems.length === 0) return;

            // Generate clean category blocks matching system layouts perfectly
            trackingHtmlMarkup += `
                <div class="category-block mb-15">
                    <h4 class="category-block-title category-title-custom">\${escapeHtml(category.name)}</h4>
                    <div class="material-item-grid">
            `;

            filteredGroupItems.forEach(item => {
                const itemImgSrc = item.image_path ? item.image_path : "https://placehold.co/150x100?text=No+Image";
                const cleanName = item.name ? item.name.replace(/'/g, "\\'") : '';

                trackingHtmlMarkup += `
                    <div class="material-item-card" data-search-name="\${escapeHtml(item.name ? item.name.toLowerCase() : '')}">
                        <div class="material-item-image-box">
                            <img src="\${escapeHtml(itemImgSrc)}" alt="" onerror="this.src='https://placehold.co/150x100?text=No+Image';">
                        </div>
                        <div class="material-item-name">
                            <strong>\${escapeHtml(item.name)}</strong>
                            <div>₹\${Number(item.price).toFixed(2)}</div>
                        </div>
                        <button type="button" class="btn-tab-styled-add" 
                            onclick="window.addItemToCheckoutCart(this, \${item.id}, '\${cleanName}', \${item.price})">
                            + Add
                        </button>
                    </div>
                `;
            });

            trackingHtmlMarkup += `</div></div>`;
        });

        targetContainer.innerHTML = trackingHtmlMarkup;
    };

    // 3. UI Controller Logic Bindings
    window.switchActiveCategory = function(categoryId, buttonElement) {
        currentSelectedCategory = categoryId;
        document.getElementById("catalogQuickSearchInput").value = "";
        activeSearchString = "";

        document.querySelectorAll(".catalog-tab-btn").forEach(btn => btn.classList.remove("active"));[cite: 12]
        if (buttonElement) buttonElement.classList.add("active");[cite: 12]

        window.renderCatalogGridTemplate();
    };

    window.runLiveSearch = function(searchQueryValue) {
        activeSearchString = searchQueryValue.toLowerCase().trim();
        if (activeSearchString !== "" && currentSelectedCategory !== 'all') {
            currentSelectedCategory = 'all';
            document.querySelectorAll(".catalog-tab-btn").forEach(btn => btn.classList.remove("active"));
            document.getElementById("globalAllTabBtn").classList.add("active");
        }
        window.renderCatalogGridTemplate();
    };

    window.addItemToCheckoutCart = function(btn, id, name, price) {[cite: 12]
        btn.classList.add('is-clicked-active');[cite: 12]
        btn.innerText = "✔ Added";[cite: 12]
        setTimeout(() => {
            btn.classList.remove('is-clicked-active');[cite: 12]
            btn.innerText = "+ Add";[cite: 12]
        }, 400);[cite: 12]

        if (window.activeCartStateMap[id]) {
            window.activeCartStateMap[id].qty++;[cite: 12]
        } else {
            window.activeCartStateMap[id] = { name: name, price: price, qty: 1 };[cite: 12]
        }
        window.renderCartInterfaceElements();[cite: 12]
    };

    window.updateCartRowQtyChange = function(id, delta) {[cite: 12]
        if (!window.activeCartStateMap[id]) return;[cite: 12]
        window.activeCartStateMap[id].qty += delta;[cite: 12]
        if (window.activeCartStateMap[id].qty <= 0) {
            delete window.activeCartStateMap[id];[cite: 12]
        }
        window.renderCartInterfaceElements();[cite: 12]
    };

    window.renderCartInterfaceElements = function() {[cite: 12]
        const container = document.getElementById("cartItemsContainerRows");[cite: 12]
        const form = document.getElementById("checkoutCartSubmissionForm");[cite: 12]
        const placeholder = document.getElementById("cartEmptyPlaceholder");[cite: 12]
        
        container.innerHTML = "";[cite: 12]
        let totalGrossSum = 0;[cite: 12]
        let keysCount = 0;[cite: 12]

        for (let id in window.activeCartStateMap) {
            keysCount++;[cite: 12]
            let row = window.activeCartStateMap[id];[cite: 12]
            let rowCost = row.qty * row.price;[cite: 12]
            totalGrossSum += rowCost;[cite: 12]

            container.innerHTML += `
                <div class="sidebar-cart-row-node">
                    <div class="sidebar-cart-item-name-node">
                        \${escapeHtml(row.name)} <span class="sidebar-cart-item-price-tag">(₹\${Number(row.price).toFixed(2)})</span>
                    </div>
                    <div class="sidebar-cart-qty-controls-node">
                        <button type="button" class="qty-btn-sm-node" onclick="window.updateCartRowQtyChange(\${id}, -1)">-</button>
                        <span class="qty-val-display-node">\${row.qty}</span>
                        <button type="button" class="qty-btn-sm-node" onclick="window.updateCartRowQtyChange(\${id}, 1)">+</button>
                    </div>
                    <input type="hidden" name="cart_items[\${id}][qty]" value="\${row.qty}">
                </div>
            `;[cite: 12]
        }

        document.getElementById("labelCartTotalGross").innerText = totalGrossSum.toFixed(2);[cite: 12]

        if (keysCount === 0) {
            placeholder.style.display = "block";[cite: 12]
            form.style.display = "none";[cite: 12]
        } else {
            placeholder.style.display = "none";[cite: 12]
            form.style.display = "flex";[cite: 12]
        }
    };

    window.submitFoodOrder = function(event) {[cite: 12]
        event.preventDefault();[cite: 12]

        const cartItems = [];[cite: 12]
        for (let id in window.activeCartStateMap) {
            cartItems.push({
                id: id,
                qty: window.activeCartStateMap[id].qty,[cite: 12]
                notes: "" [cite: 12]
            });
        }

        if (cartItems.length === 0) {
            alert("Cart is empty.");[cite: 12]
            return;[cite: 12]
        }

        fetch("process_order.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ items: cartItems })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert("✔ Order sent to kitchen successfully!");[cite: 12]
                window.activeCartStateMap = {};[cite: 12]
                window.renderCartInterfaceElements();[cite: 12]
            } else {
                alert("❌ Order Failed: " + (data.message || "Unknown error"));[cite: 12]
            }
        })
        .catch(err => {
            console.error(err);[cite: 12]
            alert("❌ Network error: Could not reach process_order.php");[cite: 12]
        });
    };

    window.handleMobileDrawerCollapseToggle = function(event) {[cite: 12]
        if (window.innerWidth >= 1024) return; [cite: 12]
        const sidebar = document.getElementById("mobileSummaryStickyWrapper");[cite: 12]
        if (event.target.closest('.sidebar-summary-title')) {
            sidebar.classList.toggle("drawer-open-state");[cite: 12]
        }
    };

    function escapeHtml(text) {
        if (!text) return '';
        return String(text).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

    // Fire the initial render instantly
    window.renderCatalogGridTemplate();
    window.renderCartInterfaceElements();
})();
</script>

<?php include "includes/footer.php"; ?>[cite: 12]