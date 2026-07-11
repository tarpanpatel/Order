<?php
// /home/apartment/artistsfarmjaipur.com/Order/requisitions.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "config/db.php";
require_once "config/telegram.php"; 
include_once __DIR__ . '/config/local_db_bridge.php';

// Disable visible execution errors to block raw framework tracking anomalies in production
error_reporting(E_ALL);
ini_set('display_errors', 0);

// --- CHEF NEW PRODUCT GENERATOR INTERCEPTOR ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action_create_chef_product"])) {
    $item_name   = trim($_POST["chef_prod_name"]);
    $category_id = intval($_POST["chef_prod_category"]);
    $pack_size   = floatval($_POST["chef_pack_size"]);
    $pack_unit   = trim($_POST["chef_pack_unit"]);

    if (!empty($item_name) && $category_id > 0) {
        $stmt = $pdo->prepare("INSERT INTO req_catalog (item_name, category_id, unit_type, unit_label, pack_size, pack_unit, is_verified, unit_cost, image_path) VALUES (?, ?, 'Count', 'Packets', ?, ?, 0, 0.00, 'https://placehold.co/150x100?text=No+Image')");
        $stmt->execute([$item_name, $category_id, $pack_size, $pack_unit]);

        $audit_stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, timestamp) VALUES (?, ?, NOW())");
        $audit_stmt->execute([$_SESSION['user_id'] ?? 0, "Chef registered a new pending custom inventory placeholder item: [" . $item_name . "]"]);
    }
    header("Location: requisitions.php");
    exit;
}

// Fetch master components
$categories = $pdo->query("SELECT * FROM material_categories ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$catalog_items = $pdo->query("SELECT * FROM req_catalog WHERE is_verified = 1 ORDER BY item_name ASC")->fetchAll(PDO::FETCH_ASSOC);

include "includes/header.php";
?>

<div class="app-body">
    <div class="category-section req-header-flex">
        <h2 class="req-header-title">📦 Stock Request</h2>
        <button type="button" class="btn btn-start btn-request-new" onclick="window.openChefNewProductModal()">+ Request New Product</button>
    </div>

    <div id="chefNewProductModal" class="chef-modal-overlay">
        <div class="card chef-modal-card">
            <h3 class="chef-modal-title">Suggest Unlisted Product</h3>
            <form method="POST" action="requisitions.php">
                <input type="hidden" name="action_create_chef_product" value="1">
                
                <div class="chef-form-group">
                    <label class="chef-form-label">Item Description Title</label>
                    <input type="text" name="chef_prod_name" required placeholder="e.g., Mother Dairy Fresh Cream" class="chef-form-input">
                </div>

                <div class="chef-form-group">
                    <label class="chef-form-label">Storage Classification Category</label>
                    <select name="chef_prod_category" required class="chef-form-select">
                        <option value="">-- Choose Category --</option>
                        <?php foreach($categories as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>

                <div class="chef-form-grid">
                    <div>
                        <label class="chef-form-label">Pack Size Spec</label>
                        <input type="number" step="0.01" name="chef_pack_size" value="1" required class="chef-form-input">
                    </div>
                    <div>
                        <label class="chef-form-label">Packaging UOM Unit</label>
                        <select name="chef_pack_unit" class="chef-form-select">
                            <option value="kg">kg (Kilograms)</option>
                            <option value="Litre">Litre (L)</option>
                            <option value="Packet">Packet (Pkt)</option>
                            <option value="Box">Box (Bx)</option>
                            <option value="Piece">Piece (Pc)</option>
                        </select>
                    </div>
                </div>

                <div class="chef-modal-actions">
                    <button type="button" class="btn btn-discard" onclick="window.closeChefNewProductModal()">Discard</button>
                    <button type="submit" class="btn btn-submit-request">Submit Request</button>
                </div>
            </form>
        </div>
    </div>

    <div class="split-requisition-layout">
        <div class="materials-main-panel">
            <div class="catalog-cards-box">
                <div class="search-input-wrapper">
                    <span class="search-icon-inside">🔍</span>
                    <input type="text" id="catalogQuickSearchInput" class="btn-quick-search-box" placeholder="Quick search catalog metrics..." onkeyup="window.searchCatalogDatabaseStream()">
                </div>

                <div class="catalog-tab-header">
                    <button type="button" id="globalAllTabBtn" class="catalog-tab-btn active" onclick="window.filterCatalogCategoryView('all', this)">All Items</button>
                    <?php foreach ($categories as $cat): ?>
                        <button type="button" class="catalog-tab-btn" onclick="window.filterCatalogCategoryView('cat_<?= $cat['id'] ?>', this)"><?= htmlspecialchars($cat['name']) ?></button>
                    <?php endforeach; ?>
                </div>

                <div id="materialCatalogContainer">
                    <?php foreach ($categories as $cat): 
                        $cat_items = array_filter($catalog_items, function($m) use ($cat) { return $m['category_id'] == $cat['id']; });
                        if (empty($cat_items)) continue;
                    ?>
                        <div class="category-block req-category-block" id="cat_<?= $cat['id'] ?>">
                            <h4 class="req-category-title">
                                <?= htmlspecialchars($cat['name']) ?>
                            </h4>
                            <div class="material-item-grid">
                                <?php foreach ($cat_items as $item): ?>
                                    <div class="material-item-card" data-search-name="<?= strtolower(htmlspecialchars($item['item_name'])) ?>">
                                        <div class="material-item-image-box">
                                            <img src="<?= htmlspecialchars($item['image_path'] ?: 'https://placehold.co/150x100?text=No+Image') ?>" alt="">
                                        </div>
                                        <div class="material-item-name">
                                            <strong><?= htmlspecialchars($item['item_name']) ?></strong>
                                            <div class="req-item-pack-info">Pack: <?= floatval($item['pack_size']) ?> <?= htmlspecialchars($item['pack_unit']) ?></div>
                                        </div>
                                        <button type="button" class="btn-tab-styled-add" onclick="window.addMaterialToSidebar(this, <?= $item['id'] ?>, '<?= htmlspecialchars(addslashes($item['item_name'])) ?>', '<?= htmlspecialchars($item['pack_unit']) ?>')">+ Add</button>
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
                <h3 class="sidebar-summary-title">📋 Supply Order Basket</h3>
                <div id="cartEmptyPlaceholder" class="req-cart-empty-msg">
                    No materials loaded.<br>Click catalog items to compile.
                </div>

                <form id="checkoutCartSubmissionForm" onsubmit="window.submitMaterialRequisitionRequest(event)" class="req-cart-form">
                    <div class="sidebar-cart-list" id="cartItemsContainerRows"></div>
                    <div class="req-cart-submit-wrapper">
                        <button type="submit" class="btn btn-dispatch-req">Dispatch Requirement</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Encapsulate structural state variables in a functional block to fix "already declared" namespace crashes
(function() {
    window.activeRequisitionCartMap = {};
    let localActiveFilteredTabId = 'all';

    window.handleMobileDrawerCollapseToggle = function(event) {
        if (window.innerWidth >= 1024) return; 
        const sidebar = document.getElementById("mobileSummaryStickyWrapper");
        if (event.target.closest('.sidebar-summary-title')) {
            sidebar.classList.toggle("drawer-open-state");
        }
    };

    window.searchCatalogDatabaseStream = function() {
        const query = document.getElementById("catalogQuickSearchInput").value.toLowerCase().trim();
        const blocks = document.querySelectorAll(".category-block");
        
        if (query !== "" && localActiveFilteredTabId !== 'all') {
            localActiveFilteredTabId = 'all';
            document.querySelectorAll(".catalog-tab-btn").forEach(b => b.classList.remove("active"));
            document.getElementById("globalAllTabBtn").classList.add("active");
        }

        blocks.forEach(block => {
            let matchesFound = false;
            block.querySelectorAll(".material-item-card").forEach(card => {
                const searchName = card.getAttribute("data-search-name") || "";
                const matchesTab = (localActiveFilteredTabId === 'all' || block.id === localActiveFilteredTabId);
                const matchesQuery = searchName.includes(query);

                if (matchesTab && matchesQuery) {
                    card.style.setProperty("display", "flex", "important");
                    matchesFound = true;
                } else {
                    card.style.setProperty("display", "none", "important");
                }
            });
            block.style.display = matchesFound ? "block" : "none";
        });
    };

    window.filterCatalogCategoryView = function(catId, elementButton) {
        localActiveFilteredTabId = catId;
        document.getElementById("catalogQuickSearchInput").value = ""; 
        document.querySelectorAll(".catalog-tab-btn").forEach(b => b.classList.remove("active"));
        if (elementButton) elementButton.classList.add("active");
        
        document.querySelectorAll(".category-block").forEach(block => {
            if (catId === 'all' || block.id === catId) {
                block.style.display = "block";
                block.querySelectorAll(".material-item-card").forEach(c => c.style.setProperty("display", "flex", "important"));
            } else {
                block.style.display = "none";
            }
        });
    };

    window.addMaterialToSidebar = function(buttonElement, id, name, unit) {
        // Visual green flashing button action logic feedback integration loop
        if (buttonElement) {
            buttonElement.classList.add('is-clicked-active');
            buttonElement.innerText = "✔ Added";
            setTimeout(() => {
                buttonElement.classList.remove('is-clicked-active');
                buttonElement.innerText = "+ Add";
            }, 400);
        }

        if (window.activeRequisitionCartMap[id]) {
            window.activeRequisitionCartMap[id].qty++;
        } else {
            window.activeRequisitionCartMap[id] = { name: name, unit: unit, qty: 1 };
        }
        window.renderRequisitionInterfaceState();
    };

    window.modifyCartRowQtyIndex = function(id, delta) {
        if (!window.activeRequisitionCartMap[id]) return;
        window.activeRequisitionCartMap[id].qty += delta;
        if (window.activeRequisitionCartMap[id].qty <= 0) {
            delete window.activeRequisitionCartMap[id];
        }
        window.renderRequisitionInterfaceState();
    };

    window.renderRequisitionInterfaceState = function() {
        const container = document.getElementById("cartItemsContainerRows");
        const form = document.getElementById("checkoutCartSubmissionForm");
        const placeholder = document.getElementById("cartEmptyPlaceholder");
        
        container.innerHTML = "";
        let rowCounter = 0;

        for (let id in window.activeRequisitionCartMap) {
            rowCounter++;
            let row = window.activeRequisitionCartMap[id];
            
            // Generate DOM using semantic classes extracted to style.css
            container.innerHTML += `
                <div class="req-cart-item-row">
                    <div class="req-cart-item-name">
                        ${row.name} <span class="req-cart-item-unit">(${row.unit})</span>
                    </div>
                    <div class="req-cart-qty-controls">
                        <button type="button" class="req-qty-btn" onclick="window.modifyCartRowQtyIndex(${id}, -1)">-</button>
                        <span class="req-qty-val">${row.qty}</span>
                        <button type="button" class="req-qty-btn" onclick="window.modifyCartRowQtyIndex(${id}, 1)">+</button>
                    </div>
                </div>
            `;
        }

        if (rowCounter === 0) {
            placeholder.style.display = "block";
            form.style.display = "none";
        } else {
            placeholder.style.display = "none";
            form.style.display = "flex";
        }
    };

    window.submitMaterialRequisitionRequest = function(event) {
        event.preventDefault();
        const payloadItems = [];
        for (let id in window.activeRequisitionCartMap) {
            payloadItems.push({ id: id, qty: window.activeRequisitionCartMap[id].qty });
        }

        if (payloadItems.length === 0) return;

        fetch("process_requisition.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ items: payloadItems })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert("✔ Material requisition submitted successfully!");
                window.activeRequisitionCartMap = {};
                window.renderRequisitionInterfaceState();
            } else {
                alert("❌ Critical Error: " + (data.error || "Execution failed."));
            }
        })
        .catch(() => alert("❌ Connectivity failure to operational engine endpoint gateway."));
    };

    window.closeChefNewProductModal = function() { document.getElementById("chefNewProductModal").style.display = "none"; };
    window.openChefNewProductModal = function() { document.getElementById("chefNewProductModal").style.display = "flex"; };
})();
</script>
<?php include "includes/footer.php"; ?>