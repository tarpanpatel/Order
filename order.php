<?php
// /home/apartment/artistsfarmjaipur.com/Order/order.php
if (session_status() === PHP_SESSION_NONE) { session_start(); } //[cite: 11]
require_once "config/db.php"; //[cite: 11]

// Fetching menu categories and configurations directly from the verified database mapping[cite: 11]
$categories = $pdo->query("SELECT * FROM menu_categories ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC); //[cite: 11]
$menu_items = $pdo->query("SELECT * FROM menu_items WHERE is_hidden = 0 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC); //[cite: 11]

// Get the live active guest details token[cite: 11]
$current_active_guest = $pdo->query("SELECT * FROM guests WHERE status = 'Active' LIMIT 1")->fetch(PDO::FETCH_ASSOC); //[cite: 11]

include "includes/header.php"; //[cite: 11]
?>

<div class="app-body"> <!--[cite: 11] -->

    <div class="category-section"> <!--[cite: 11] -->
        <h2 class="mb-4">🍽️ Food Order Entry</h2> <!--[cite: 11] -->
        <p class="active-ticket-context-wrapper"> <!--[cite: 11] -->
            Active Ticket Context:  <!--[cite: 11] -->
            <?php if ($current_active_guest): ?> <!--[cite: 11] -->
                <strong class="active-ticket-guest-highlight">📱 Guest (<?= htmlspecialchars($current_active_guest['guest_name']) ?> - <?= substr($current_active_guest['phone_number'], -4) ?>)</strong> <!--[cite: 11] -->
            <?php else: ?> <!--[cite: 11] -->
                <span class="active-ticket-no-guest">No Active Guest Selected in Sidebar</span> <!--[cite: 11] -->
            <?php endif; ?> <!--[cite: 11] -->
        </p> <!--[cite: 11] -->
    </div> <!--[cite: 11] -->

    <div class="split-requisition-layout"> <!--[cite: 11] -->
        <div class="materials-main-panel"> <!--[cite: 11] -->
            <div class="catalog-cards-box"> <!--[cite: 11] -->
                <div class="search-input-wrapper"> <!--[cite: 11] -->
                    <span class="search-icon-inside">🔍</span> <!--[cite: 11] -->
                    <input type="text" id="catalogQuickSearchInput" class="btn-quick-search-box" placeholder="Quick search menu items on the fly..." onkeyup="window.searchMenu()"> <!--[cite: 11] -->
                </div> <!--[cite: 11] -->

                <div class="catalog-tab-header"> <!--[cite: 11] -->
                    <button type="button" id="globalAllTabBtn" class="catalog-tab-btn active" onclick="window.filterMenuCatalog('all', this)">All Menu</button> <!--[cite: 11] -->
                    <?php foreach ($categories as $cat): ?> <!--[cite: 11] -->
                        <button type="button" class="catalog-tab-btn" onclick="window.filterMenuCatalog('cat_<?= $cat['id'] ?>', this)"><?= htmlspecialchars($cat['name']) ?></button> <!--[cite: 11] -->
                    <?php endforeach; ?> <!--[cite: 11] -->
                </div> <!--[cite: 11] -->

                <div id="menuCatalogContainer"> <!--[cite: 11] -->
                    <?php foreach ($categories as $cat): 
                        // VERIFIED KEY: Column mapping updated to strictly use 'category_id' matching database index criteria[cite: 11, 12]
                        $cat_items = array_filter($menu_items, function($m) use ($cat) { return $m['category_id'] == $cat['id']; }); 
                        if (empty($cat_items)) continue; //[cite: 11]
                    ?>
                        <div class="category-block mb-15" id="cat_<?= $cat['id'] ?>"> <!--[cite: 11] -->
                            <h4 class="category-block-title category-title-custom"> <!--[cite: 11] -->
                                <?= htmlspecialchars($cat['name']) ?> <!--[cite: 11] -->
                            </h4> <!--[cite: 11] -->
                            <div class="material-item-grid"> <!--[cite: 11] -->
                                <?php foreach ($cat_items as $item): ?> <!--[cite: 11] -->
                                    <div class="material-item-card" data-search-name="<?= strtolower(htmlspecialchars($item['name'])) ?>"> <!--[cite: 11] -->
                                        <div class="material-item-image-box"> <!--[cite: 11] -->
                                            <?php if (!empty($item['image_path']) && file_exists($item['image_path'])): ?> <!--[cite: 11] -->
                                                <img src="<?= htmlspecialchars($item['image_path']) ?>" alt="" onerror="this.src='https://placehold.co/150x100?text=No+Image';"> <!--[cite: 11] -->
                                            <?php else: ?> <!--[cite: 11] -->
                                                <img src="https://placehold.co/150x100?text=No+Image" alt=""> <!--[cite: 11] -->
                                            <?php endif; ?> <!--[cite: 11] -->
                                        </div> <!--[cite: 11] -->
                                        <div class="material-item-name"> <!--[cite: 11] -->
                                            <strong><?= htmlspecialchars($item['name']) ?></strong> <!--[cite: 11] -->
                                            <div>₹<?= number_format($item['price'], 2) ?></div> <!--[cite: 11] -->
                                        </div> <!--[cite: 11] -->
                                        <button type="button" class="btn-tab-styled-add" 
                                            onclick="window.addItemToCheckoutCart(this, <?= $item['id'] ?>, '<?= htmlspecialchars(addslashes($item['name'])) ?>', <?= $item['price'] ?>)"> <!--[cite: 11] -->
                                            + Add <!--[cite: 11] -->
                                        </button> <!--[cite: 11] -->
                                    </div> <!--[cite: 11] -->
                                <?php endforeach; ?> <!--[cite: 11] -->
                            </div> <!--[cite: 11] -->
                        </div> <!--[cite: 11] -->
                    <?php endforeach; ?> <!--[cite: 11] -->
                </div> <!--[cite: 11] -->
            </div> <!--[cite: 11] -->
        </div> <!--[cite: 11] -->

        <div class="right-column-stack"> <!--[cite: 11] -->
            <div class="requisition-right-sidebar" id="mobileSummaryStickyWrapper" onclick="window.handleMobileDrawerCollapseToggle(event)"> <!--[cite: 11] -->
                <h3 class="sidebar-summary-title">📝 Active Order Ticket</h3> <!--[cite: 11] -->
                
                <div id="cartEmptyPlaceholder" class="sidebar-placeholder-msg"> <!--[cite: 11] -->
                    Basket is empty.<br>Click items to load. <!--[cite: 11] -->
                </div> <!--[cite: 11] -->

                <form id="checkoutCartSubmissionForm" onsubmit="window.submitFoodOrder(event)" class="checkout-cart-form"> <!--[cite: 11] -->
                    <input type="hidden" name="guest_id" value="<?= $current_active_guest['id'] ?? 0 ?>"> <!--[cite: 11] -->
                    
                    <div class="sidebar-cart-list" id="cartItemsContainerRows"></div> <!--[cite: 11] -->
                    
                    <div> <!--[cite: 11] -->
                        <div class="sidebar-total-row-wrapper"> <!--[cite: 11] -->
                            <span class="total-types-label sidebar-total-label-green">TICKET TOTAL:</span> <!--[cite: 11] -->
                            <span class="total-types-value sidebar-total-label-green">₹<span id="labelCartTotalGross">0.00</span></span> <!--[cite: 11] -->
                        </div> <!--[cite: 11] -->
                        <button type="submit" <?php echo !$current_active_guest ? 'disabled' : ''; ?> class="btn btn-bill btn-send-kitchen"> <!--[cite: 11] -->
                            <?php echo $current_active_guest ? 'Send Order to Kitchen' : 'Select Guest to Send'; ?> <!--[cite: 11] -->
                        </button> <!--[cite: 11] -->
                    </div> <!--[cite: 11] -->
                </form> <!--[cite: 11] -->
            </div> <!--[cite: 11] -->
        </div> <!--[cite: 11] -->
    </div> <!--[cite: 11] -->
</div> <!--[cite: 11] -->

<script>
window.activeCartStateMap = {}; //[cite: 11]
let activeFilteredTabId = 'all'; //[cite: 11]

window.handleMobileDrawerCollapseToggle = function(event) {
    if (window.innerWidth >= 1024) return;  //[cite: 11]
    const sidebar = document.getElementById("mobileSummaryStickyWrapper"); //[cite: 11]
    if (event.target.closest('.sidebar-summary-title')) {
        sidebar.classList.toggle("drawer-open-state"); //[cite: 11]
    }
};

window.searchMenu = function() {
    const inputVal = document.getElementById("catalogQuickSearchInput").value.toLowerCase().trim(); //[cite: 11]
    const blocks = document.querySelectorAll(".category-block"); //[cite: 11]
    
    if (inputVal !== "" && activeFilteredTabId !== 'all') {
        activeFilteredTabId = 'all'; //[cite: 11]
        document.querySelectorAll(".catalog-tab-btn").forEach(b => b.classList.remove("active")); //[cite: 11]
        document.getElementById("globalAllTabBtn").classList.add("active"); //[cite: 11]
    }

    blocks.forEach(block => {
        let parentHasVisibleItem = false; //[cite: 11]
        const cards = block.querySelectorAll(".material-item-card"); //[cite: 11]
        
        cards.forEach(card => {
            const searchName = card.getAttribute("data-search-name") || ""; //[cite: 11]
            const isTabMatch = (activeFilteredTabId === 'all' || block.id === activeFilteredTabId); //[cite: 11]
            const isSearchMatch = searchName.includes(inputVal); //[cite: 11]

            if (isTabMatch && isSearchMatch) {
                card.style.setProperty("display", "flex", "important"); //[cite: 11]
                parentHasVisibleItem = true; //[cite: 11]
            } else {
                card.style.setProperty("display", "none", "important"); //[cite: 11]
            }
        });

        block.style.display = parentHasVisibleItem ? "block" : "none"; //[cite: 11]
    });
};

window.filterMenuCatalog = function(catId, btn) {
    activeFilteredTabId = catId; //[cite: 11]
    document.getElementById("catalogQuickSearchInput").value = "";  //[cite: 11]

    document.querySelectorAll(".catalog-tab-btn").forEach(b => b.classList.remove("active")); //[cite: 11]
    if (btn) btn.classList.add("active"); //[cite: 11]
    
    document.querySelectorAll(".category-block").forEach(block => {
        if (catId === 'all' || block.id === catId) {
            block.style.display = "block"; //[cite: 11]
            block.querySelectorAll(".material-item-card").forEach(c => c.style.setProperty("display", "flex", "important")); //[cite: 11]
        } else {
            block.style.display = "none"; //[cite: 11]
        }
    });
};

// FIXED FUNCTION SIGNATURE: Added button context ('btn') parameter back to process elements accurately without JavaScript errors[cite: 11]
window.addItemToCheckoutCart = function(btn, id, name, price) { 
    // Trigger Visual Button Feedback loop[cite: 11]
    btn.classList.add('is-clicked-active'); //[cite: 11]
    btn.innerText = "✔ Added"; //[cite: 11]
    setTimeout(() => {
        btn.classList.remove('is-clicked-active'); //[cite: 11]
        btn.innerText = "+ Add"; //[cite: 11]
    }, 400); //[cite: 11]

    <?php if (!$current_active_guest): ?> <!--[cite: 11] -->
        alert("Please choose and activate an active guest profile..."); //[cite: 11]
        return; //[cite: 11]
    <?php endif; ?> <!--[cite: 11] -->

    if (activeCartStateMap[id]) {
        activeCartStateMap[id].qty++; //[cite: 11]
    } else {
        activeCartStateMap[id] = { name: name, price: price, qty: 1 }; //[cite: 11]
    }
    renderCartInterfaceElements(); //[cite: 11]
};

window.updateCartRowQtyChange = function(id, delta) {
    if (!window.activeCartStateMap[id]) return; //[cite: 11]
    window.activeCartStateMap[id].qty += delta; //[cite: 11]
    if (window.activeCartStateMap[id].qty <= 0) {
        delete window.activeCartStateMap[id]; //[cite: 11]
    }
    window.renderCartInterfaceElements(); //[cite: 11]
};

window.renderCartInterfaceElements = function() {
    const container = document.getElementById("cartItemsContainerRows"); //[cite: 11]
    const form = document.getElementById("checkoutCartSubmissionForm"); //[cite: 11]
    const placeholder = document.getElementById("cartEmptyPlaceholder"); //[cite: 11]
    
    container.innerHTML = ""; //[cite: 11]
    
    let totalGrossSum = 0; //[cite: 11]
    let keysCount = 0; //[cite: 11]

    for (let id in window.activeCartStateMap) {
        keysCount++; //[cite: 11]
        let row = window.activeCartStateMap[id]; //[cite: 11]
        let rowCost = row.qty * row.price; //[cite: 11]
        totalGrossSum += rowCost; //[cite: 11]

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
        `; //[cite: 11]
    }

    document.getElementById("labelCartTotalGross").innerText = totalGrossSum.toFixed(2); //[cite: 11]

    if (keysCount === 0) {
        placeholder.style.display = "block"; //[cite: 11]
        form.style.display = "none"; //[cite: 11]
    } else {
        placeholder.style.display = "none"; //[cite: 11]
        form.style.display = "flex"; //[cite: 11]
    }
};

window.submitFoodOrder = function(event) {
    event.preventDefault();  //[cite: 11]

    const cartItems = []; //[cite: 11]
    for (let id in window.activeCartStateMap) {
        cartItems.push({
            id: id,
            qty: window.activeCartStateMap[id].qty, //[cite: 11]
            notes: ""  //[cite: 11]
        });
    }

    if (cartItems.length === 0) {
        alert("Cart is empty."); //[cite: 11]
        return; //[cite: 11]
    }

    fetch("process_order.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ items: cartItems })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert("✔ Order sent to kitchen successfully!"); //[cite: 11]
            window.activeCartStateMap = {}; //[cite: 11]
            window.renderCartInterfaceElements(); //[cite: 11]
        } else {
            alert("❌ Order Failed: " + (data.message || "Unknown error")); //[cite: 11]
        }
    })
    .catch(err => {
        console.error(err); //[cite: 11]
        alert("❌ Network error: Could not reach process_order.php"); //[cite: 11]
    });
};
</script>

<?php include "includes/footer.php"; ?> <!--[cite: 11] -->