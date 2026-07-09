<?php
// /home/apartment/artistsfarmjaipur.com/Order/order.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once "config/db.php";
 

// Fetching menu categories and configurations directly from the verified database mapping
$categories = $pdo->query("SELECT * FROM menu_categories ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
$menu_items = $pdo->query("SELECT * FROM menu_items WHERE is_hidden = 0 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Get the live active guest details token
$current_active_guest = $pdo->query("SELECT * FROM guests WHERE status = 'Active' LIMIT 1")->fetch(PDO::FETCH_ASSOC);

include "includes/header.php";
?>

<div class="app-body">

    <div class="category-section">
        <h2 style="margin-bottom: 4px;">🍽️ Food Order Entry</h2>
        <p style="margin:0; font-size:12px; color:#64748b; font-weight:600;">
            Active Ticket Context: 
            <?php if ($current_active_guest): ?>
                <strong style="color: #0891b2;">📱 Guest (<?= htmlspecialchars($current_active_guest['guest_name']) ?> - <?= substr($current_active_guest['phone_number'], -4) ?>)</strong>
            <?php else: ?>
                <span style="color:#ef4444; font-weight: bold;">No Active Guest Selected in Sidebar</span>
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
                        $cat_items = array_filter($menu_items, function($m) use ($cat) { return $m['category_id'] == $cat['id']; });
                        if (empty($cat_items)) continue;
                    ?>
                        <div class="category-block" id="cat_<?= $cat['id'] ?>" style="margin-bottom: 15px;">
                            <h4 class="category-block-title" style="font-size: 12px; text-transform: uppercase; color: #4b5563; text-align: left; margin-bottom: 8px; font-weight: 700;">
                                <?= htmlspecialchars($cat['name']) ?>
                            </h4>
                            <div class="material-item-grid">
                                <?php foreach ($cat_items as $item): ?>
                                    <div class="material-item-card" data-search-name="<?= strtolower(htmlspecialchars($item['name'])) ?>">
                                        <div class="material-item-image-box">
                                            <?php if (!empty($item['image_path']) && file_exists($item['image_path'])): ?>
                                                <img src="<?= htmlspecialchars($item['image_path']) ?>" alt="" onerror="this.src='https://placehold.co/150x100?text=No+Image';">
                                            <?php else: ?>
                                                <img src="https://placehold.co/150x100?text=No+Image" alt="">
                                            <?php endif; ?>
                                        </div>
                                        <div class="material-item-name">
                                            <strong><?= htmlspecialchars($item['name']) ?></strong>
                                            <div>₹<?= number_format($item['price'], 2) ?></div>
                                        </div>
                                        <button type="button" class="btn-tab-styled-add" onclick="window.addItemToCheckoutCart(<?= $item['id'] ?>, '<?= htmlspecialchars(addslashes($item['name'])) ?>', <?= $item['price'] ?>)">+ Add</button>
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
                
                <div id="cartEmptyPlaceholder" style="color: #a0aec0; text-align: center; font-size: 12px; margin-top: 30px; font-style: italic;">
                    Basket is empty.<br>Click items to load.
                </div>

                <form method="POST" action="api/process_order.php" id="checkoutCartSubmissionForm" style="display:none; margin:0; flex-direction:column; width:100%;">
                    <input type="hidden" name="guest_id" value="<?= $current_active_guest['id'] ?? 0 ?>">
                    
                    <div class="sidebar-cart-list" id="cartItemsContainerRows">
                        </div>
                    
                    <div>
                        <div class="sidebar-total-row-wrapper">
                            <span class="total-types-label" style="color:#059669;">TICKET TOTAL:</span>
                            <span class="total-types-value" style="color:#059669;">₹<span id="labelCartTotalGross">0.00</span></span>
                        </div>
                        <button type="submit" <?php echo !$current_active_guest ? 'disabled' : ''; ?> class="btn btn-bill" style="width: 100%; padding: 12px; font-size: 13px; font-weight: bold; border-radius: 8px; background: #059669; border-color:#059669;">
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

// Matches requisitions.php collapsible mobile mechanics
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

window.addItemToCheckoutCart = function(id, name, price) {
    <?php if (!$current_active_guest): ?>
        alert("Please choose and activate an active guest profile ledger session inside the sidebar dropdown configuration list before recording orders.");
        return;
    <?php endif; ?>

    if (window.activeCartStateMap[id]) {
        window.activeCartStateMap[id].qty++;
    } else {
        window.activeCartStateMap[id] = { name: name, price: price, qty: 1 };
    }
    window.renderCartInterfaceElements();
};

window.updateCartRowQtyChange = function(id, delta) {
    if (!window.activeCartStateMap[id]) return;
    window.activeCartStateMap[id].qty += delta;
    if (window.activeCartStateMap[id].qty <= 0) {
        delete window.activeCartStateMap[id];
    }
    window.renderCartInterfaceElements();
};

// Layout perfectly mapped to .sidebar-cart-row logic configured inside style.css
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

        container.innerHTML += `
            <div class="sidebar-cart-row" style="display: flex !important; justify-content: space-between !important; align-items: center !important; font-size: 13px !important; padding: 6px 0 !important; border-bottom: 1px solid #edf2f7 !important; gap: 10px !important; width: 100% !important; box-sizing: border-box !important;">
                <div class="sidebar-cart-item-name" style="font-weight: 700 !important; color: #111827 !important; flex: 1 !important; white-space: nowrap !important; overflow: hidden !important; text-overflow: ellipsis !important; text-align: left !important;">
                    ${row.name} <span style="font-size:10px; color:#64748b; font-weight:bold;">(₹${row.price})</span>
                </div>
                <div class="sidebar-cart-qty-controls" style="display: flex !important; align-items: center !important; border: 1px solid #cbd5e0 !important; border-radius: 6px !important; overflow: hidden !important; background: #ffffff !important; height: 28px !important; flex-shrink: 0 !important;">
                    <button type="button" class="qty-btn-sm" style="width: 26px !important; height: 100% !important; background: #f8fafc !important; border: none !important; font-weight: bold !important; cursor: pointer !important; padding: 0 !important; display: flex !important; align-items: center !important; justify-content: center !important;" onclick="window.updateCartRowQtyChange(${id}, -1)">-</button>
                    <span style="font-weight: 800 !important; font-size: 12px !important; width: 30px !important; text-align: center !important; display: inline-block !important; color: #1e293b !important;">${row.qty}</span>
                    <button type="button" class="qty-btn-sm" style="width: 26px !important; height: 100% !important; background: #f8fafc !important; border: none !important; font-weight: bold !important; cursor: pointer !important; padding: 0 !important; display: flex !important; align-items: center !important; justify-content: center !important;" onclick="window.updateCartRowQtyChange(${id}, 1)">+</button>
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
</script>

<?php include "includes/footer.php"; ?>