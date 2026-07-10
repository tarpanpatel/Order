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
    <div class="category-section" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2 style="margin: 0;">📦 Material Requisition Request</h2>
        <button type="button" class="btn btn-start" onclick="window.openChefNewProductModal()" style="padding: 8px 16px; font-size: 12px; font-weight: bold; border-radius: 6px;">+ Request New Product</button>
    </div>

    <div id="chefNewProductModal" class="prompt-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.4); backdrop-filter: blur(4px); z-index: 999999; justify-content: center; align-items: center;">
        <div class="card" style="width: 100%; max-width: 400px; padding: 25px; background: #fff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.15);">
            <h3 style="margin-top: 0; margin-bottom: 15px; color: #1e293b;">Suggest Unlisted Product</h3>
            <form method="POST" action="requisitions.php">
                <input type="hidden" name="action_create_chef_product" value="1">
                
                <div style="margin-bottom: 12px; text-align: left;">
                    <label style="font-size: 11px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">Item Description Title</label>
                    <input type="text" name="chef_prod_name" required placeholder="e.g., Mother Dairy Fresh Cream" style="width: 100%; padding: 10px; border: 1px solid #cbd5e0; border-radius: 6px;">
                </div>

                <div style="margin-bottom: 12px; text-align: left;">
                    <label style="font-size: 11px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">Storage Classification Category</label>
                    <select name="chef_prod_category" required style="width: 100%; padding: 10px; border: 1px solid #cbd5e0; border-radius: 6px; background: #fff;">
                        <option value="">-- Choose Category --</option>
                        <?php foreach($categories as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 20px; text-align: left;">
                    <div>
                        <label style="font-size: 11px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">Pack Size Size Spec</label>
                        <input type="number" step="0.01" name="chef_pack_size" value="1" required style="width: 100%; padding: 10px; border: 1px solid #cbd5e0; border-radius: 6px;">
                    </div>
                    <div>
                        <label style="font-size: 11px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">Packaging UOM Unit</label>
                        <select name="chef_pack_unit" style="width: 100%; padding: 10px; border: 1px solid #cbd5e0; border-radius: 6px; background: #fff;">
                            <option value="kg">kg (Kilograms)</option>
                            <option value="Litre">Litre (L)</option>
                            <option value="Packet">Packet (Pkt)</option>
                            <option value="Box">Box (Bx)</option>
                            <option value="Piece">Piece (Pc)</option>
                        </select>
                    </div>
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn" style="background:#e2e8f0; color:#475569;" onclick="window.closeChefNewProductModal()">Discard</button>
                    <button type="submit" class="btn btn-bill" style="background:#00b0ff; border-color:#00b0ff; padding: 10px 20px;">Submit Request</button>
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
                        <div class="category-block" id="cat_<?= $cat['id'] ?>" style="margin-bottom: 25px;">
                            <h4 class="category-block-title" style="font-size: 11px; text-transform: uppercase; color: #475569; letter-spacing: 0.5px; font-weight: 700; margin-bottom: 10px; border-left: 3px solid #00b0ff; padding-left: 8px;">
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
                                            <div style="font-size: 11px; color: #64748b; margin-top: 2px;">Pack: <?= floatval($item['pack_size']) ?> <?= htmlspecialchars($item['pack_unit']) ?></div>
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
                <div id="cartEmptyPlaceholder" style="color: #94a3b8; text-align: center; font-size: 12px; margin-top: 40px; font-style: italic;">
                    No materials loaded.<br>Click catalog items to compile.
                </div>

                <form id="checkoutCartSubmissionForm" onsubmit="window.submitMaterialRequisitionRequest(event)" style="display:none; margin:0; flex-direction:column; width:100%;">
                    <div class="sidebar-cart-list" id="cartItemsContainerRows"></div>
                    <div style="margin-top: 15px;">
                        <button type="submit" class="btn btn-bill" style="width: 100%; padding: 12px; font-weight: bold; font-size: 13px; background: #00b0ff; border-color: #00b0ff; border-radius: 8px;">Dispatch Requirement</button>
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
            container.innerHTML += `
                <div class="cart-item-row" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #edf2f7; gap: 10px;">
                    <div class="cart-item-name" style="font-weight: 700; color: #1e293b; flex: 1; text-align: left; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                        ${row.name} <span style="font-size:10px; color:#94a3b8; font-weight:bold;">(${row.unit})</span>
                    </div>
                    <div class="cart-qty-controls" style="display: flex; align-items: center; border: 1px solid #cbd5e0; border-radius: 6px; overflow: hidden; height: 28px;">
                        <button type="button" class="cart-qty-btn" style="width:26px; border:none; background:#f8fafc; font-weight:bold; cursor:pointer;" onclick="window.modifyCartRowQtyIndex(${id}, -1)">-</button>
                        <span class="cart-qty-val" style="width:30px; text-align:center; font-weight:800; font-size:12px;">${row.qty}</span>
                        <button type="button" class="cart-qty-btn" style="width:26px; border:none; background:#f8fafc; font-weight:bold; cursor:pointer;" onclick="window.modifyCartRowQtyIndex(${id}, 1)">+</button>
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