<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "config/db.php";

// Restrict access explicitly to the Chef and Admin roles
if (!isset($_SESSION["role"]) || ($_SESSION["role"] !== "Chef" && $_SESSION["role"] !== "Admin")) {
    header("Location: login.php");
    exit;
}

// Fetch all categories and materials cleanly matching your primary schema
$categories = $pdo->query("SELECT * FROM material_categories ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
$materials  = $pdo->query("SELECT * FROM materials ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

include "includes/header.php";
?>

<style>
/* ==========================================================================
   RESTORING THE SPLIT REQUISITION VIEW AND RIGHT SIDEBAR LAYOUT
   ========================================================================== */
.split-requisition-layout {
    display: grid !important;
    grid-template-columns: 1fr 340px !important;
    gap: 20px !important;
    width: 100% !important;
    align-items: start !important;
    margin-top: 15px;
}

.materials-main-panel {
    background: #ffffff !important;
    border: 1px solid #e2e8f0 !important;
    border-radius: 12px !important;
    padding: 20px !important;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05) !important;
}

.requisition-right-sidebar {
    background: #ffffff !important;
    border: 1px solid #cbd5e0 !important;
    border-radius: 12px !important;
    padding: 20px !important;
    position: sticky !important;
    top: 20px !important;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05) !important;
    display: flex;
    flex-direction: column;
    min-height: 480px;
    text-align: left;
}

.catalog-tab-header {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 20px;
    border-bottom: 1px solid #edf2f7;
    padding-bottom: 12px;
}

.catalog-tab-btn {
    padding: 6px 12px;
    font-size: 12px;
    font-weight: 600;
    background: #f7fafc;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    cursor: pointer;
    color: #4a5568;
}

.catalog-tab-btn.active {
    background: #06b6d4;
    color: white;
    border-color: #06b6d4;
}

.material-item-grid {
    display: grid !important;
    grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)) !important;
    gap: 10px !important;
}

.material-item-card {
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 10px;
    background: #fff;
    text-align: center;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-height: 105px;
}

.material-item-name {
    font-size: 12px;
    font-weight: 600;
    color: #111827;
    margin-bottom: 8px;
    line-height: 1.4;
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
    max-height: 320px;
    margin-bottom: 15px;
}

.sidebar-cart-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 13px;
    padding: 8px 0;
    border-bottom: 1px solid #f7fafc;
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

@media (max-width: 1023px) {
    .split-requisition-layout {
        grid-template-columns: 1fr !important;
    }
    .requisition-right-sidebar {
        position: static !important;
        min-height: auto;
    }
}
</style>

<div class="app-body" style="max-width: 100% !important; width: 100% !important; display: block !important;">
    <div class="category-section" style="margin-bottom: 20px;">
        <h2 class="category-title" style="text-transform: none;">📦 Material Requests Panel</h2>
    </div>

    <div class="split-requisition-layout">
        
        <!-- LEFT PANEL: CATEGORIES AND MATERIAL CARDS -->
        <div class="materials-main-panel">
            <div class="catalog-tab-header">
                <button type="button" class="catalog-tab-btn active" onclick="filterMaterialCatalog('all', this)">All Items</button>
                <?php foreach ($categories as $cat): ?>
                    <button type="button" class="catalog-tab-btn" onclick="filterMaterialCatalog('cat_<?= $cat['id'] ?>', this)">
                        <?= htmlspecialchars($cat['name']) ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <div id="materialCatalogContainer">
                <?php foreach ($categories as $cat): 
                    $catItems = array_filter($materials, function($x) use ($cat) { return $x['category_id'] == $cat['id']; });
                    if (empty($catItems)) continue;
                ?>
                    <div class="category-block" id="cat_<?= $cat['id'] ?>" style="margin-bottom: 25px;">
                        <h4 style="font-size: 13px; text-transform: uppercase; color: #4b5563; text-align: left; margin-bottom: 10px; font-weight: 700;"><?= $cat['name'] ?></h4>
                        <div class="material-item-grid">
                            <?php foreach ($catItems as $item): ?>
                                <div class="material-item-card">
                                    <div class="material-item-name"><?= htmlspecialchars($item['name']) ?></div>
                                    <button type="button" class="btn btn-start" style="padding: 6px; font-size: 11px; width: 100%; border-radius: 6px;" onclick="addMaterialToSidebar(<?= $item['id'] ?>, '<?= htmlspecialchars(addslashes($item['name'])) ?>')">+ Add Item</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- RIGHT SIDEBAR PANEL: THE ACTIVE REQUISITION CART SUMMARY -->
        <div class="requisition-right-sidebar">
            <h3 class="sidebar-summary-title">📝 Requisition Summary</h3>
            
            <div class="sidebar-cart-list" id="sidebarCartRowsContainer">
                <p style="color: #a0aec0; text-align: center; font-size: 13px; margin-top: 40px; font-style: italic;">No items added to this request list yet.</p>
            </div>

            <div style="border-top: 1px dashed #e2e8f0; padding-top: 15px;">
                <div style="display: flex; justify-content: space-between; font-weight: 700; font-size: 14px; margin-bottom: 15px; color: #111827;">
                    <span>Total Item Types:</span>
                    <span id="sidebarTotalCount">0</span>
                </div>
                <button type="button" class="btn btn-bill" style="width: 100%; padding: 12px; font-size: 13px; font-weight: bold; border-radius: 8px;" onclick="submitSidebarRequisition()">Submit Requisition</button>
            </div>
        </div>

    </div>
</div>

<script>
let reqCart = [];

function addMaterialToSidebar(id, name) {
    let existing = reqCart.find(x => x.id === id);
    if (existing) {
        existing.qty += 1;
    } else {
        reqCart.push({ id: id, name: name, qty: 1 });
    }
    renderSidebarCart();
}

function updateSidebarQty(id, delta) {
    let item = reqCart.find(x => x.id === id);
    if (item) {
        item.qty += delta;
        if (item.qty <= 0) {
            reqCart = reqCart.filter(x => x.id !== id);
        }
    }
    renderSidebarCart();
}

function renderSidebarCart() {
    const container = document.getElementById("sidebarCartRowsContainer");
    const totalCountEl = document.getElementById("sidebarTotalCount");
    
    if (reqCart.length === 0) {
        container.innerHTML = '<p style="color: #a0aec0; text-align: center; font-size: 13px; margin-top: 40px; font-style: italic;">No items added to this request list yet.</p>';
        totalCountEl.innerText = "0";
        return;
    }

    totalCountEl.innerText = reqCart.length;
    container.innerHTML = reqCart.map(item => `
        <div class="sidebar-cart-row">
            <div style="font-weight: 600; color: #111827; max-width: 180px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                ${item.name}
            </div>
            <div style="display: flex; align-items: center; gap: 8px;">
                <button type="button" class="qty-btn-sm" onclick="updateSidebarQty(${item.id}, -1)">-</button>
                <span style="font-weight: 700; font-size: 13px; width: 20px; text-align: center;">${item.qty}</span>
                <button type="button" class="qty-btn-sm" onclick="updateSidebarQty(${item.id}, 1)">+</button>
            </div>
        </div>
    `).join('');
}

function filterMaterialCatalog(catId, btn) {
    document.querySelectorAll(".catalog-tab-btn").forEach(b => b.classList.remove("active"));
    btn.classList.add("active");

    const blocks = document.querySelectorAll(".category-block");
    blocks.forEach(block => {
        if (catId === 'all' || block.id === catId) {
            block.style.display = "block";
        } else {
            block.style.display = "none";
        }
    });
}

function submitSidebarRequisition() {
    if (reqCart.length === 0) return alert("Please select material choices first.");
    if (!confirm("Dispatch this material request list to inventory history logs?")) return;

    fetch("api/process_requisition.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ items: reqCart })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert("✔ Requisition request saved successfully!");
            reqCart = [];
            renderSidebarCart();
        } else {
            alert("❌ Connection error: " + (data.error || "details pipeline breakdown."));
        }
    })
    .catch(err => {
        alert("❌ Network pipeline error connection failure.");
    });
}
</script>

<?php include "includes/footer.php"; ?>