<?php
// /home/apartment/artistsfarmjaipur.com/Order/order.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once "config/db.php";

// Fetching menu categories and configurations directly from the verified database mapping[cite: 10]
$categories = $pdo->query("SELECT * FROM menu_categories ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
$menu_items = $pdo->query("SELECT * FROM menu_items WHERE is_hidden = 0 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Get the live active guest details token[cite: 10]
$current_active_guest = $pdo->query("SELECT * FROM guests WHERE status = 'Active' LIMIT 1")->fetch(PDO::FETCH_ASSOC);

include "includes/header.php";
?>

<div class="app-body">

    <div class="category-section">
        <h2 class="mb-4">🍽️ Food Order Entry</h2>
        <p class="active-ticket-context-wrapper">
            Active Ticket Context: 
            <?php if ($current_active_guest): ?>
                <strong class="active-ticket-guest-highlight">📱 Guest (<?= htmlspecialchars($current_active_guest['guest_name']) ?> - <?= substr($current_active_guest['phone_number'], -4) ?>)</strong>[cite: 10]
            <?php else: ?>
                <span class="active-ticket-no-guest">No Active Guest Selected in Sidebar</span>[cite: 10]
            <?php endif; ?>
        </p>
    </div>

    <div class="split-requisition-layout">
        <div class="materials-main-panel">
            <div class="catalog-cards-box">
                <div class="search-input-wrapper">
                    <span class="search-icon-inside">🔍</span>
                    <input type="text" id="catalogQuickSearchInput" class="btn-quick-search-box" placeholder="Quick search menu items on the fly..." onkeyup="window.searchMenu()">[cite: 10]
                </div>

                <div class="catalog-tab-header">
                    <button type="button" id="globalAllTabBtn" class="catalog-tab-btn active" onclick="window.filterMenuCatalog('all', this)">All Menu</button>[cite: 10]
                    <?php foreach ($categories as $cat): ?>
                        <button type="button" class="catalog-tab-btn" onclick="window.filterMenuCatalog('cat_<?= $cat['id'] ?>', this)"><?= htmlspecialchars($cat['name']) ?></button>[cite: 10]
                    <?php endforeach; ?>
                </div>

                <div id="menuCatalogContainer">
                    <?php foreach ($categories as $cat): 
                        // FIXED: Changed array match from $m['category_id'] to match the native column $m['category']
                        $cat_items = array_filter($menu_items, function($m) use ($cat) { return $m['category'] == $cat['id']; });
                        if (empty($cat_items)) continue;
                    ?>
                        <div class="category-block mb-15" id="cat_<?= $cat['id'] ?>">
                            <h4 class="category-block-title category-title-custom">
                                <?= htmlspecialchars($cat['name']) ?>[cite: 10]
                            </h4>
                            <div class="material-item-grid">
                                <?php foreach ($cat_items as $item): ?>
                                    <div class="material-item-card" data-search-name="<?= strtolower(htmlspecialchars($item['name'])) ?>">[cite: 10]
                                        <div class="material-item-image-box">
                                            <?php if (!empty($item['image_path']) && file_exists($item['image_path'])): ?>
                                                <img src="<?= htmlspecialchars($item['image_path']) ?>" alt="" onerror="this.src='https://placehold.co/150x100?text=No+Image';">[cite: 10]
                                            <?php else: ?>
                                                <img src="https://placehold.co/150x100?text=No+Image" alt="">[cite: 10]
                                            <?php endif; ?>
                                        </div>
                                        <div class="material-item-name">
                                            <strong><?= htmlspecialchars($item['name']) ?></strong>[cite: 10]
                                            <div>₹<?= number_format($item['price'], 2) ?></div>[cite: 10]
                                        </div>
                                        <button type="button" class="btn-tab-styled-add" 
                                            onclick="window.addItemToCheckoutCart(this, <?= $item['id'] ?>, '<?= htmlspecialchars(addslashes($item['name'])) ?>', <?= $item['price'] ?>)">
                                            + Add
                                        </button>[cite: 10]
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="right-column-stack">
            <div class="requisition-right-sidebar" id="mobileSummaryStickyWrapper" onclick="window.handleMobileDrawerCollapseToggle(event)">[cite: 10]
                <h3 class="sidebar-summary-title">📝 Active Order Ticket</h3>[cite: 10]
                
                <div id="cartEmptyPlaceholder" class="sidebar-placeholder-msg">
                    Basket is empty.<br>Click items to load.[cite: 10]
                </div>

                <form id="checkoutCartSubmissionForm" onsubmit="window.submitFoodOrder(event)" class="checkout-cart-form">[cite: 10]
                    <input type="hidden" name="guest_id" value="<?= $current_active_guest['id'] ?? 0 ?>">[cite: 10]
                    
                    <div class="sidebar-cart-list" id="cartItemsContainerRows"></div>[cite: 10]
                    
                    <div>
                        <div class="sidebar-total-row-wrapper">
                            <span class="total-types-label sidebar-total-label-green">TICKET TOTAL:</span>[cite: 10]
                            <span class="total-types-value sidebar-total-label-green">₹<span id="labelCartTotalGross">0.00</span></span>[cite: 10]
                        </div>
                        <button type="submit" <?php echo !$current_active_guest ? 'disabled' : ''; ?> class="btn btn-bill btn-send-kitchen">[cite: 10]
                            <?php echo $current_active_guest ? 'Send Order to Kitchen' : 'Select Guest to Send'; ?>[cite: 10]
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
window.activeCartStateMap = {};[cite: 10]
let activeFilteredTabId = 'all';[cite: 10]

window.handleMobileDrawerCollapseToggle = function(event) {
    if (window.innerWidth >= 1024) return; [cite: 10]
    const sidebar = document.getElementById("mobileSummaryStickyWrapper");[cite: 10]
    if (event.target.closest('.sidebar-summary-title')) {
        sidebar.classList.toggle("drawer-open-state");[cite: 10]
    }
};

window.searchMenu = function() {
    const inputVal = document.getElementById("catalogQuickSearchInput").value.toLowerCase().trim();[cite: 10]
    const blocks = document.querySelectorAll(".category-block");[cite: 10]
    
    if (inputVal !== "" && activeFilteredTabId !== 'all') {
        activeFilteredTabId = 'all';[cite: 10]
        document.querySelectorAll(".catalog-tab-btn").forEach(b => b.classList.remove("active"));[cite: 10]
        document.getElementById("globalAllTabBtn").classList.add("active");[cite: 10]
    }

    blocks.forEach(block => {
        let parentHasVisibleItem = false;[cite: 10]
        const cards = block.querySelectorAll(".material-item-card");[cite: 10]
        
        cards.forEach(card => {
            const searchName = card.getAttribute("data-search-name") || "";[cite: 10]
            const isTabMatch = (activeFilteredTabId === 'all' || block.id === activeFilteredTabId);[cite: 10]
            const isSearchMatch = searchName.includes(inputVal);[cite: 10]

            if (isTabMatch && isSearchMatch) {
                card.style.setProperty("display", "flex", "important");[cite: 10]
                parentHasVisibleItem = true;[cite: 10]
            } else {
                card.style.setProperty("display", "none", "important");[cite: 10]
            }
        });

        block.style.display = parentHasVisibleItem ? "block" : "none";[cite: 10]
    });
};

window.filterMenuCatalog = function(catId, btn) {
    activeFilteredTabId = catId;[cite: 10]
    document.getElementById("catalogQuickSearchInput").value = ""; [cite: 10]

    document.querySelectorAll(".catalog-tab-btn").forEach(b => b.classList.remove("active"));[cite: 10]
    if (btn) btn.classList.add("active");[cite: 10]
    
    document.querySelectorAll(".category-block").forEach(block => {
        if (catId === 'all' || block.id === catId) {
            block.style.display = "block";[cite: 10]
            block.querySelectorAll(".material-item-card").forEach(c => c.style.setProperty("display", "flex", "important"));[cite: 10]
        } else {
            block.style.display = "none";[cite: 10]
        }
    });
};

function addItemToCheckoutCart(btn, id, name, price) {
    btn.classList.add('is-clicked-active');[cite: 10]
    btn.innerText = "✔ Added";[cite: 10]
    setTimeout(() => {
        btn.classList.remove('is-clicked-active');[cite: 10]
        btn.innerText = "+ Add";[cite: 10]
    }, 400);

    <?php if (!$current_active_guest): ?>
        alert("Please choose and activate an active guest profile...");[cite: 10]
        return;
    <?php endif; ?>

    if (activeCartStateMap[id]) {
        activeCartStateMap[id].qty++;[cite: 10]
    } else {
        activeCartStateMap[id] = { name: name, price: price, qty: 1 };[cite: 10]
    }
    renderCartInterfaceElements();[cite: 10]
}

window.updateCartRowQtyChange = function(id, delta) {
    if (!window.activeCartStateMap[id]) return;[cite: 10]
    window.activeCartStateMap[id].qty += delta;[cite: 10]
    if (window.activeCartStateMap[id].qty <= 0) {
        delete window.activeCartStateMap[id];[cite: 10]
    }
    window.renderCartInterfaceElements();[cite: 10]
};

window.renderCartInterfaceElements = function() {
    const container = document.getElementById("cartItemsContainerRows");[cite: 10]
    const form = document.getElementById("checkoutCartSubmissionForm");[cite: 10]
    const placeholder = document.getElementById("cartEmptyPlaceholder");[cite: 10]
    
    container.innerHTML = "";[cite: 10]
    
    let totalGrossSum = 0;[cite: 10]
    let keysCount = 0;[cite: 10]

    for (let id in window.activeCartStateMap) {
        keysCount++;[cite: 10]
        let row = window.activeCartStateMap[id];[cite: 10]
        let rowCost = row.qty * row.price;[cite: 10]
        totalGrossSum += rowCost;[cite: 10]

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
        `;[cite: 10]
    }

    document.getElementById("labelCartTotalGross").innerText = totalGrossSum.toFixed(2);[cite: 10]

    if (keysCount === 0) {
        placeholder.style.display = "block";[cite: 10]
        form.style.display = "none";[cite: 10]
    } else {
        placeholder.style.display = "none";[cite: 10]
        form.style.display = "flex";[cite: 10]
    }
};

window.submitFoodOrder = function(event) {
    event.preventDefault(); [cite: 10]

    const cartItems = [];[cite: 10]
    for (let id in window.activeCartStateMap) {
        cartItems.push({
            id: id,
            qty: window.activeCartStateMap[id].qty,[cite: 10]
            notes: "" 
        });
    }

    if (cartItems.length === 0) {
        alert("Cart is empty.");[cite: 10]
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
            alert("✔ Order sent to kitchen successfully!");[cite: 10]
            window.activeCartStateMap = {};[cite: 10]
            window.renderCartInterfaceElements();[cite: 10]
        } else {
            alert("❌ Order Failed: " + (data.message || "Unknown error"));[cite: 10]
        }
    })
    .catch(err => {
        console.error(err);[cite: 10]
        alert("❌ Network error: Could not reach process_order.php");[cite: 10]
    });
};
</script>

<?php include "includes/footer.php"; ?>