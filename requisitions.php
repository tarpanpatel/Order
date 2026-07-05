<?php
// /home/apartment/artistsfarmjaipur.com/Order/requisitions.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "config/db.php";
require_once "config/telegram.php"; 
include_once __DIR__ . '/config/local_db_bridge.php';

if (!isset($_SESSION["role"]) || ($_SESSION["role"] !== "Chef" && $_SESSION["role"] !== "Admin")) {
    header("Location: login.php");
    exit;
}

// Automatically loops through items, extracts catalog unit costs, and syncs data blocks downstream
function syncKitchenInventoryToGoogleSheets($req_id, $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT ri.quantity, rc.item_name, rc.category_id, rc.unit_cost, (SELECT name FROM material_categories WHERE id = rc.category_id) as cat_name FROM requisition_items ri JOIN req_catalog rc ON ri.catalog_id = rc.id WHERE ri.requisition_id = ? AND ri.item_status = 'Fulfilled'");
        $stmt->execute([$req_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($items as $item) {
            $qty = floatval($item['quantity']);
            $unit_cost = floatval($item['unit_cost']);
            $total_cost = $qty * $unit_cost;

            $rowPattern = [
                '', 
                date('d/m/y'), 
                $item['cat_name'] ?: 'General', 
                $item['item_name'], 
                $qty, 
                'PKT/KG/L', 
                $unit_cost, 
                $total_cost, 
                'Inventory Vendor Line'
            ];
            appendRowToGoogleSheet('Kitchen Exp', $rowPattern);
        }
    } catch (Exception $e) {
        error_log("Automation Sync Exception Raised.");
    }
}

// --- AUTOMATED DEFICIENCY RESOLUTION ENGINE ---
function autoResolveDeficiencies($req_id, $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT catalog_id, quantity FROM requisition_items WHERE requisition_id = ? AND item_status = 'Fulfilled'");
        $stmt->execute([$req_id]);
        $deliveredItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $getDeficiencies  = $pdo->prepare("SELECT id, deficit_qty, delivered_qty FROM deficient_stock_logs WHERE catalog_id = ? AND deficit_qty > 0 ORDER BY logged_at ASC");
        $updateDeficiency = $pdo->prepare("UPDATE deficient_stock_logs SET deficit_qty = ?, delivered_qty = ? WHERE id = ?");

        foreach ($deliveredItems as $item) {
            $catalog_id = intval($item['catalog_id']);
            $available_qty = intval($item['quantity']); 

            if ($available_qty <= 0) continue;

            $getDeficiencies->execute([$catalog_id]);
            $openDeficiencies = $getDeficiencies->fetchAll(PDO::FETCH_ASSOC);

            foreach ($openDeficiencies as $d) {
                if ($available_qty <= 0) break;

                $d_id = intval($d['id']);
                $current_deficit = intval($d['deficit_qty']);
                $current_delivered = intval($d['delivered_qty']);

                if ($available_qty >= $current_deficit) {
                    $available_qty -= $current_deficit;
                    $updateDeficiency->execute([0, $current_delivered + $current_deficit, $d_id]);
                } else {
                    $updateDeficiency->execute([$current_deficit - $available_qty, $current_delivered + $available_qty, $d_id]);
                    $available_qty = 0;
                }
            }
        }
    } catch (Exception $e) {}
}

function sendRequisitionFulfilledTelegram($req_id, $pdo) {
    try {
        $lines = $pdo->prepare("SELECT ri.quantity, rc.item_name as name FROM requisition_items ri JOIN req_catalog rc ON ri.catalog_id = rc.id WHERE ri.requisition_id = ? AND ri.item_status = 'Fulfilled'");
        $lines->execute([$req_id]);
        $items = $lines->fetchAll(PDO::FETCH_ASSOC);

        $itemsBlock = "";
        foreach ($items as $i) {
            $itemsBlock .= "✅ *x" . $i['quantity'] . "* " . $i['name'] . "\n";
        }

        $msg = "📦 ✅ *MATERIAL REQUISITION VERIFIED BY BACKEND*\n";
        $msg .= "--------------------------------------\n";
        $msg .= "🆔 *Request ID Reference:* #" . $req_id . "\n";
        $msg .= "⏰ *Processed At:* " . date('d/m/y • H:i') . "\n";
        $msg .= "--------------------------------------\n\n";
        $msg .= !empty($itemsBlock) ? $itemsBlock : "🔹 _No fulfilled items logged._\n";
        $msg .= "\n--------------------------------------\n";
        $msg .= "👍 _Materials synced downstream successfully._";

        sendTelegramNotification($msg);
    } catch (Exception $tgEx) {}
}

// --- NEW AJAX INSTANT-COMMIT SUBMISSION BACKGROUND CHANNEL ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action_instant_ajax_item_sync"])) {
    header('Content-Type: application/json');
    $req_id     = intval($_POST["requisition_id"]);
    $catalog_id = intval($_POST["catalog_id"]);
    $new_qty    = intval($_POST["quantity"]);
    $new_status = trim($_POST["item_status"]); // 'Fulfilled' or 'Cancelled'

    $pdo->beginTransaction();
    try {
        $getOriginalQty = $pdo->prepare("SELECT quantity FROM requisition_items WHERE requisition_id = ? AND catalog_id = ?");
        $logDeficiency  = $pdo->prepare("INSERT INTO deficient_stock_logs (requisition_id, catalog_id, ordered_qty, delivered_qty, deficit_qty) VALUES (?, ?, ?, ?, ?)");
        $updateItem     = $pdo->prepare("UPDATE requisition_items SET quantity = ?, item_status = ? WHERE requisition_id = ? AND catalog_id = ?");

        $getOriginalQty->execute([$req_id, $catalog_id]);
        $original_qty = intval($getOriginalQty->fetchColumn() ?: 0);

        if ($new_qty < $original_qty && $new_status === 'Fulfilled') {
            $deficit = $original_qty - $new_qty;
            $logDeficiency->execute([$req_id, $catalog_id, $original_qty, $new_qty, $deficit]);
        }

        $updateItem->execute([$new_qty, $new_status, $req_id, $catalog_id]);

        // Evaluate parent ticket context: look at all sibling items in this batch request
        $siblingCheck = $pdo->prepare("SELECT COUNT(*) FROM requisition_items WHERE requisition_id = ? AND item_status NOT IN ('Fulfilled', 'Cancelled')");
        $siblingCheck->execute([$req_id]);
        $pendingSiblingsCount = intval($siblingCheck->fetchColumn() ?: 0);

        // Micro-status lookup logic: globally marked Fulfilled only if 0 pending items remain
        $final_global_status = ($pendingSiblingsCount === 0) ? 'Fulfilled' : 'Pending';
        
        $stmt = $pdo->prepare("UPDATE requisitions SET status = ? WHERE id = ?");
        $stmt->execute([$final_global_status, $req_id]);

        if ($final_global_status === 'Fulfilled') {
            autoResolveDeficiencies($req_id, $pdo);
            syncKitchenInventoryToGoogleSheets($req_id, $pdo);
            sendRequisitionFulfilledTelegram($req_id, $pdo);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'global_status' => $final_global_status]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

$categories = $pdo->query("SELECT * FROM material_categories ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
$materials  = $pdo->query("SELECT id, item_name as name, category_id, image_path FROM req_catalog ORDER BY item_name ASC")->fetchAll(PDO::FETCH_ASSOC);

$past_requisitions = $pdo->query("SELECT r.id, r.requested_at, r.status,
                                  (SELECT GROUP_CONCAT(CONCAT(rc.item_name, ' (x', ri.quantity, ')') SEPARATOR ', ')
                                   FROM requisition_items ri
                                   JOIN req_catalog rc ON ri.catalog_id = rc.id
                                   WHERE ri.requisition_id = r.id) as item_summary
                                  FROM requisitions r
                                  ORDER BY r.id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
                                  
include "includes/header.php";
?>

<style>
.split-requisition-layout { display: grid !important; grid-template-columns: 1fr 380px !important; gap: 20px !important; width: 100% !important; align-items: start !important; margin-top: 15px; }
.materials-main-panel { display: flex; flex-direction: column; gap: 24px; }
.catalog-cards-box { background: #ffffff !important; border: 1px solid #e2e8f0 !important; border-radius: 12px !important; padding: 20px !important; box-shadow: 0 1px 3px rgba(0,0,0,0.05) !important; }

.right-column-stack { display: flex; flex-direction: column; gap: 20px; position: sticky !important; top: 20px !important; }
.requisition-right-sidebar { background: #ffffff !important; border: 1px solid #cbd5e0 !important; border-radius: 12px !important; padding: 20px !important; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05) !important; display: flex; flex-direction: column; text-align: left; }

.catalog-tab-header { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 20px; border-bottom: 1px solid #edf2f7; padding-bottom: 12px; }
.catalog-tab-btn { padding: 6px 12px; font-size: 12px; font-weight: 600; background: #f7fafc; border: 1px solid #cbd5e0; border-radius: 6px; cursor: pointer; color: #475569; transition: all 0.15s ease; }
.catalog-tab-btn:hover { background: #e2e8f0; }
.catalog-tab-btn.active { background: #06b6d4; color: white; border-color: #06b6d4; }

.material-item-grid { display: grid !important; grid-template-columns: repeat(auto-fill, minmax(135px, 1fr)) !important; gap: 12px !important; }
.material-item-card { border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; background: #fff; text-align: center; display: flex; flex-direction: column; justify-content: space-between; align-items: center; min-height: 110px; box-sizing: border-box; }
.material-item-name { font-size: 12px; font-weight: 600; color: #111827; margin-bottom: 10px; line-height: 1.4; text-align: center; width: 100%; word-wrap: break-word; }

.btn-tab-styled-add { 
    display: inline-block !important;
    padding: 5px 14px !important; 
    font-size: 12px !important; 
    font-weight: 600 !important; 
    background: #ffffff !important; 
    color: #475569 !important; 
    border: 1px solid #cbd5e0 !important; 
    border-radius: 6px !important; 
    cursor: pointer !important; 
    box-shadow: 0 1px 2px rgba(0,0,0,0.02) !important;
    transition: all 0.15s ease !important;
    width: auto !important;
    margin: 0 auto !important;
}
.btn-tab-styled-add:hover { background: #f8fafc !important; border-color: #94a3b8 !important; color: #0f172a !important; }

.sidebar-summary-title { font-size: 14px; font-weight: 700; text-transform: uppercase; color: #111827; border-bottom: 1px dashed #e2e8f0; padding-bottom: 8px; margin-bottom: 15px; margin-top: 0; }
.sidebar-cart-list { max-height: 240px; overflow-y: auto; margin-bottom: 15px; }
.sidebar-cart-row { display: flex; justify-content: space-between; align-items: center; font-size: 13px; padding: 8px 0; border-bottom: 1px solid #f7fafc; }
.qty-btn-sm { padding: 2px 8px; font-size: 12px; font-weight: bold; border: 1px solid #cbd5e0; background: #f7fafc; border-radius: 4px; cursor: pointer; }

.past-log-section { background: #ffffff !important; border: 1px solid #cbd5e0 !important; border-radius: 12px !important; padding: 14px !important; box-shadow: 0 2px 4px rgba(0,0,0,0.02) !important; text-align: left; width: 100%; box-sizing: border-box; }
.past-table { width: 100%; border-collapse: collapse; font-size: 12px; table-layout: fixed; }
.past-table th { background: #f8fafc; padding: 6px 4px; font-weight: 700; color: #4b5563; border-bottom: 2px solid #e2e8f0; text-align: left; }
.past-table td { padding: 8px 4px; border-bottom: 1px solid #edf2f7; color: #111827; vertical-align: middle; }

.btn-action-trigger { 
    display: block !important; 
    width: 100% !important; 
    padding: 6px 4px !important; 
    font-size: 10px !important; 
    font-weight: 700 !important; 
    text-transform: uppercase; 
    border-radius: 6px !important; 
    cursor: pointer !important; 
    text-align: center !important; 
    line-height: 1.3 !important;
    border: 1px solid transparent !important;
}
.btn-action-trigger span { display: block !important; font-size: 9px !important; font-weight: 800 !important; text-transform: lowercase; }

.btn-status-pending { background: #fef3c7 !important; color: #d97706 !important; border-color: #f59e0b !important; }
.btn-status-pending:hover { background: #fde68a !important; }
.btn-status-fulfilled { background: #d1fae5 !important; color: #059669 !important; border-color: #10b981 !important; }
.btn-status-fulfilled:hover { background: #a7f3d0 !important; }

.btn-sidebar-past-link { display: block !important; text-align: center !important; width: 100% !important; padding: 8px !important; background: #f1f5f9 !important; color: #475569 !important; border: 1px solid #cbd5e0 !important; border-radius: 6px !important; font-size: 12px !important; font-weight: 600 !important; text-decoration: none !important; margin-top: 12px !important; }

.binary-actions-wrapper { display: flex; gap: 6px; align-items: center; }
.action-block-btn { padding: 6px 12px !important; font-size: 11px !important; font-weight: 700 !important; border: none !important; border-radius: 6px !important; color: #ffffff !important; cursor: pointer !important; text-transform: uppercase !important; letter-spacing: 0.5px !important; box-shadow: 0 1px 2px rgba(0,0,0,0.05) !important; }

.btn-block-fulfilled { background: #38a169 !important; }
.btn-block-fulfilled:hover { background: #2f855a !important; }
.btn-block-remove { background: #e53e3e !important; }
.btn-block-remove:hover { background: #c53030 !important; }

.modal-input-qty { width: 50px; padding: 6px 4px; border: 1px solid #cbd5e0; text-align: center; font-size: 13px; font-weight: 700; color: #1e293b; border-radius: 0; border-left: none; border-right: none; background: #fff !important; }
.modal-qty-container { display: flex; align-items: center; border-radius: 6px; overflow: hidden; border: 1px solid #cbd5e0; }
.modal-qty-btn { width: 28px; height: 31px; background: #f8fafc; border: none; color: #475569; font-weight: bold; font-size: 14px; cursor: pointer; display: flex; align-items: center; justify-content: center; }
.modal-qty-btn:hover { background: #e2e8f0; color: #0f172a; }

/* Inline micro-notification message styling */
.micro-saved-toast { font-size: 11px; font-weight: bold; color: #10b981; display: block; margin-top: 4px; opacity: 0; transition: opacity 0.2s ease; text-align: center; font-family: monospace; }
</style>

<div class="app-body" style="max-width: 100% !important; width: 100% !important; display: block !important;">
    <div class="category-section" style="margin-bottom: 20px;">
        <h2 class="category-title" style="text-transform: none; margin: 0;">📦 Material Requests Panel</h2>
    </div>

    <div class="split-requisition-layout">
        <div class="materials-main-panel">
            <div class="catalog-cards-box">
                <div class="catalog-tab-header">
                    <button type="button" class="catalog-tab-btn active" onclick="window.filterMaterialCatalog('all', this)">All Items</button>
                    <?php foreach ($categories as $cat): ?>
                        <button type="button" class="catalog-tab-btn" onclick="window.filterMaterialCatalog('cat_<?= $cat['id'] ?>', this)">
                            <?= htmlspecialchars($cat['name']) ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <div id="materialCatalogContainer">
                    <?php foreach ($categories as $cat): 
                        $catItems = array_filter($materials, function($x) use ($cat) {
                            return (intval($x['category_id']) === intval($cat['id']));
                        });
                        if (empty($catItems)) continue;
                    ?>
                        <div class="category-block" id="cat_<?= $cat['id'] ?>" style="margin-bottom: 25px;">
                            <h4 style="font-size: 13px; text-transform: uppercase; color: #4b5563; text-align: left; margin-bottom: 10px; font-weight: 700;"><?= $cat['name'] ?></h4>
                            <div class="material-item-grid">
                                <?php foreach ($catItems as $item): ?>
                                    <div class="material-item-card">
                                        <div class="material-item-name"><?= htmlspecialchars($item['name']) ?></div>
                                        <button type="button" class="btn-tab-styled-add" onclick="window.addMaterialToSidebar(<?= $item['id'] ?>, '<?= htmlspecialchars(addslashes($item['name'])) ?>')">+ Add</button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="right-column-stack">
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
                    <button type="button" class="btn btn-bill" style="width: 100%; padding: 12px; font-size: 13px; font-weight: bold; border-radius: 8px;" onclick="window.submitSidebarRequisition()">Submit Requisition</button>
                </div>
            </div>

            <div class="past-log-section">
                <h3 style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #111827; margin-bottom: 12px; border-bottom: 1px dashed #cbd5e0; padding-bottom: 6px; letter-spacing: 0.5px;">📋 Recent Requisitions Log</h3>
                <div style="overflow-x: auto;">
                    <table class="past-table">
                        <thead>
                            <tr>
                                <th style="width: 65px;">Date &amp; Time</th>
                                <th>Summary Details</th>
                                <th style="text-align: center; width: 80px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($past_requisitions)): foreach ($past_requisitions as $pRow): 
                                $summary_clean = !empty($pRow['item_summary']) ? $pRow['item_summary'] : 'No items';
                                $is_fulfilled = ($pRow['status'] === 'Fulfilled');
                                $status_style = $is_fulfilled ? 'btn-status-fulfilled' : 'btn-status-pending';
                                $status_label = empty($pRow['status']) ? 'Pending' : $pRow['status'];

                                $lines = $pdo->prepare("SELECT ri.catalog_id, ri.quantity, COALESCE(ri.item_status, 'Pending') as item_status, rc.item_name as name FROM requisition_items ri JOIN req_catalog rc ON ri.catalog_id = rc.id WHERE ri.requisition_id = ?");
                                $lines->execute([$pRow['id']]);
                                $serializedItems = json_encode($lines->fetchAll(PDO::FETCH_ASSOC));
                            ?>
                                <tr>
                                    <td style="color: #475569; font-weight: 600; font-family: monospace; font-size: 11px; line-height: 1.2;">
                                        <?= date('d/m/y', strtotime($pRow['requested_at'])) ?><br>
                                        <span style="color: #94a3b8; font-size: 10px;"><?= date('H:i', strtotime($pRow['requested_at'])) ?></span>
                                    </td>
                                    <td style="max-width: 140px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 500; font-size: 11px; padding-right: 4px;" title="<?= htmlspecialchars($summary_clean) ?>"><?= $summary_clean ?></td>
                                    <td style="text-align: center;">
                                        <button type="button" class="btn-action-trigger <?= $status_style ?>" data-items='<?= htmlspecialchars($serializedItems, ENT_QUOTES, 'UTF-8') ?>' onclick="window.openEditRequisitionModal(<?= $pRow['id'] ?>, this)">
                                            <span>(<?= strtolower($status_label) ?>)</span>
                                            Take Action
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="3" style="text-align: center; color: #a0aec0; padding: 15px;">No requests found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <a href="requisitions_log.php" class="btn-sidebar-past-link">📂 See Past Requests archives</a>
            </div>
        </div>
    </div>
</div>

<div id="editReqModalPopup" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); z-index: 99999; justify-content: center; align-items: center; backdrop-filter: blur(4px);">
    <div class="modal-content" style="background: white; max-width: 550px; width: 92%; border-radius: 12px; padding: 25px; position: relative; color: #111827; text-align: left;">
        <span style="position: absolute; top: 12px; right: 16px; font-size: 22px; cursor: pointer; color: #a0aec0;" onclick="window.closeEditReqModal()">✕</span>
        <h3 style="font-size: 14px; font-weight: 700; text-transform: uppercase; margin-bottom: 15px; border-bottom: 1px dashed #e2e8f0; padding-bottom: 8px; letter-spacing: 0.5px;">Modify Requisition Parameters</h3>
        
        <h4 style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #64748b; margin-bottom: 12px; letter-spacing: 0.5px;">Item Verification Matrix</h4>
        
        <input type="hidden" id="mdlUpdateId">
        <div id="mdlItemsContainer" style="max-height: 320px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 8px; padding: 4px; margin-bottom: 15px; background: #fafafa;"></div>

        <div style="display: flex; justify-content: flex-end;">
            <button type="button" class="btn btn-log" style="padding: 10px 24px; border-radius: 8px;" onclick="window.closeEditReqModal()">Close Window</button>
        </div>
    </div>
</div>

<script>
window.reqCart = [];

window.addMaterialToSidebar = function(id, name) {
    let existing = window.reqCart.find(x => x.id === id);
    if (existing) { existing.qty += 1; } 
    else { window.reqCart.push({ id: id, name: name, qty: 1 }); }
    window.renderSidebarCart();
};

window.updateSidebarQty = function(id, delta) {
    let item = window.reqCart.find(x => x.id === id);
    if (item) {
        item.qty += delta;
        if (item.qty <= 0) { window.reqCart = window.reqCart.filter(x => x.id !== id); }
    }
    window.renderSidebarCart();
};

window.renderSidebarCart = function() {
    const container = document.getElementById("sidebarCartRowsContainer");
    const totalCountEl = document.getElementById("sidebarTotalCount");
    
    if (!container) return;
    if (window.reqCart.length === 0) {
        container.innerHTML = '<p style="color: #a0aec0; text-align: center; font-size: 13px; margin-top: 40px; font-style: italic;">No items added to this request list yet.</p>';
        totalCountEl.innerText = "0"; return;
    }
    totalCountEl.innerText = window.reqCart.length;
    container.innerHTML = window.reqCart.map(item => `
        <div class="sidebar-cart-row">
            <div style="font-weight: 600; color: #111827; max-width: 180px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${item.name}</div>
            <div style="display: flex; align-items: center; gap: 8px;">
                <button type="button" class="qty-btn-sm" onclick="window.updateSidebarQty(${item.id}, -1)">-</button>
                <span style="font-weight: 700; font-size: 13px; width: 20px; text-align: center;">${item.qty}</span>
                <button type="button" class="qty-btn-sm" onclick="window.updateSidebarQty(${item.id}, 1)">+</button>
            </div>
        </div>
    `).join('');
};

window.filterMaterialCatalog = function(catId, btn) {
    document.querySelectorAll(".catalog-tab-btn").forEach(b => b.classList.remove("active"));
    if (btn) btn.classList.add("active");
    document.querySelectorAll(".category-block").forEach(block => {
        block.style.display = (catId === 'all' || block.id === catId) ? "block" : "none";
    });
};

window.submitSidebarRequisition = function() {
    if (window.reqCart.length === 0) return alert("Please select material choices first.");
    if (!confirm("Dispatch this material request list to inventory history logs?")) return;

    fetch("api/process_requisition.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ items: window.reqCart })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert("✔ Requisition request saved successfully!");
            window.reqCart = [];
            location.reload();
        } else {
            alert("❌ Connection error.");
        }
    }).catch(err => alert("❌ Network connection failure."));
};

window.openEditRequisitionModal = function(reqId, element) {
    document.getElementById("mdlUpdateId").value = reqId;
    const container = document.getElementById("mdlItemsContainer");
    const rawItemsData = element.getAttribute("data-items");
    
    try {
        const items = JSON.parse(rawItemsData);
        if (!items || items.length === 0) {
            container.innerHTML = '<p style="text-align:center; color:#ef4444; font-size:12px; padding:15px;">No items loaded.</p>';
            return;
        }
        
        container.innerHTML = items.map(i => {
            return `
                <div style="display:flex; justify-content:space-between; align-items:center; padding:10px 8px; border-bottom:1px solid #e2e8f0; background:#ffffff; margin-bottom:4px; border-radius:6px; gap:8px;">
                    <div style="flex:1; min-width:0;">
                        <span style="font-size:12px; font-weight:700; color:#1e293b; display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${i.name}</span>
                        <span id="microSavedToast_${i.catalog_id}" class="micro-saved-toast">Saved ✓</span>
                    </div>
                    
                    <div class="modal-qty-container">
                        <button type="button" class="modal-qty-btn" onclick="window.stepModalQty(${i.catalog_id}, -1)">-</button>
                        <input type="number" id="mdlQtyInput_${i.catalog_id}" value="${i.quantity}" min="0" class="modal-input-qty" oninput="window.validateInputBound(this)">
                        <button type="button" class="modal-qty-btn" onclick="window.stepModalQty(${i.catalog_id}, 1)">+</button>
                    </div>

                    <div class="binary-toggle-container" style="background:#fff;">
                        <button type="button" class="action-block-btn btn-block-fulfilled" onclick="window.commitRowStateInstant(${i.catalog_id}, 'Fulfilled')">Fulfilled</button>
                        <button type="button" class="action-block-btn btn-block-remove" onclick="window.commitRowStateInstant(${i.catalog_id}, 'Cancelled')">Remove</button>
                    </div>
                </div>
            `;
        }).join('');
        
        document.getElementById("editReqModalPopup").style.display = "flex";
    } catch(err) {
        container.innerHTML = '<p style="text-align:center; color:#ef4444; font-size:12px; padding:15px;">Failed loading data arrays.</p>';
    }
};

window.stepModalQty = function(catalogId, stepValue) {
    const input = document.getElementById(`mdlQtyInput_${catalogId}`);
    if (input) {
        let currentVal = parseInt(input.value) || 0;
        input.value = Math.max(0, currentVal + stepValue);
    }
};

window.validateInputBound = function(element) {
    let val = parseInt(element.value);
    if (isNaN(val) || val < 0) {
        element.value = 0;
    }
};

// --- CORE RE-ENGINEERING: FIXED AUTOMATED BACKGROUND TRANSACTIONS PIPE ENGINE ---
window.commitRowStateInstant = function(catalogId, selectedState) {
    const macroReqId = document.getElementById("mdlUpdateId").value;
    const qtyValue   = document.getElementById(`mdlQtyInput_${catalogId}`).value;
    const toastLabel = document.getElementById(`microSavedToast_${catalogId}`);

    // Create standard secure network form data bundle block
    const formParams = new FormData();
    formParams.append('action_instant_ajax_item_sync', '1');
    formParams.append('requisition_id', macroReqId);
    formParams.append('catalog_id', catalogId);
    formParams.append('quantity', qtyValue);
    formParams.append('item_status', selectedState);

    // Fire instant background fetch pipeline execution
    fetch('requisitions.php', {
        method: 'POST',
        body: formParams
    })
    .then(res => res.json())
    .then(payload => {
        if (payload.success) {
            // Display clean custom success confirmation label inline for exactly 500ms
            if (toastLabel) {
                toastLabel.style.opacity = '1';
                setTimeout(() => {
                    toastLabel.style.opacity = '0';
                }, 500);
            }
        } else {
            alert('❌ Operation transaction failure.');
        }
    })
    .catch(() => {
        alert('❌ Network connection drop error context.');
    });
};

window.closeEditReqModal = function() { 
    document.getElementById("editReqModalPopup").style.display = "none";
    // Reload parent dashboard container state context to align color indicator row blocks beautifully
    location.reload(); 
};
</script>

<?php include "includes/footer.php"; ?>