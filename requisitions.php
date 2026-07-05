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
        $stmt = $pdo->prepare("SELECT ri.quantity, rc.item_name, rc.category_id, rc.unit_cost, (SELECT name FROM material_categories WHERE id = rc.category_id) as cat_name FROM requisition_items ri JOIN req_catalog rc ON ri.catalog_id = rc.id WHERE ri.requisition_id = ?");
        $stmt->execute([$req_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($items as $item) {
            $qty = floatval($item['quantity']);
            $unit_cost = floatval($item['unit_cost']);
            $total_cost = $qty * $unit_cost;

            $rowPattern = [
                '', 
                date('Y-m-d'), 
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
        $stmt = $pdo->prepare("SELECT catalog_id, quantity FROM requisition_items WHERE requisition_id = ?");
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
        $lines = $pdo->prepare("SELECT ri.quantity, rc.item_name as name FROM requisition_items ri JOIN req_catalog rc ON ri.catalog_id = rc.id WHERE ri.requisition_id = ?");
        $lines->execute([$req_id]);
        $items = $lines->fetchAll(PDO::FETCH_ASSOC);

        $itemsBlock = "";
        foreach ($items as $i) {
            $itemsBlock .= "✅ *x" . $i['quantity'] . "* " . $i['name'] . "\n";
        }

        $msg = "📦 ✅ *MATERIAL REQUISITION DELIVERED & COMPLETED*\n";
        $msg .= "--------------------------------------\n";
        $msg .= "🆔 *Request ID Reference:* #" . $req_id . "\n";
        $msg .= "⏰ *Delivered At:* " . date('H:i d-m-Y') . "\n";
        $msg .= "--------------------------------------\n\n";
        $msg .= !empty($itemsBlock) ? $itemsBlock : "🔹 _No items logged._\n";
        $msg .= "\n--------------------------------------\n";
        $msg .= "👍 _Materials have been successfully verified and delivered to the kitchen/housekeeping staff._";

        sendTelegramNotification($msg);
    } catch (Exception $tgEx) {}
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action_update_requisition"])) {
    $req_id = intval($_POST["update_req_id"]);
    $new_status = trim($_POST["update_req_status"] ?? 'Pending');
    $quantities = $_POST["req_item_qty"] ?? [];

    $pdo->beginTransaction();
    try {
        $statusCheck = $pdo->prepare("SELECT status FROM requisitions WHERE id = ?");
        $statusCheck->execute([$req_id]);
        $old_status = $statusCheck->fetchColumn();

        $getOriginalQty = $pdo->prepare("SELECT quantity FROM requisition_items WHERE requisition_id = ? AND catalog_id = ?");
        $logDeficiency  = $pdo->prepare("INSERT INTO deficient_stock_logs (requisition_id, catalog_id, ordered_qty, delivered_qty, deficit_qty) VALUES (?, ?, ?, ?, ?)");
        $updateItem     = $pdo->prepare("UPDATE requisition_items SET quantity = ? WHERE requisition_id = ? AND catalog_id = ?");
        $delItem        = $pdo->prepare("DELETE FROM requisition_items WHERE requisition_id = ? AND catalog_id = ?");

        foreach ($quantities as $cat_id => $qty) {
            $cat_id = intval($cat_id);
            $new_qty = intval($qty);

            $getOriginalQty->execute([$req_id, $cat_id]);
            $original_qty = intval($getOriginalQty->fetchColumn() ?: 0);

            if ($new_qty < $original_qty) {
                $deficit = $original_qty - $new_qty;
                $logDeficiency->execute([$req_id, $cat_id, $original_qty, $new_qty, $deficit]);
            }

            if ($new_qty > 0) {
                $updateItem->execute([$new_qty, $req_id, $cat_id]);
            } else {
                $delItem->execute([$req_id, $cat_id]);
            }
        }

        $stmt = $pdo->prepare("UPDATE requisitions SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $req_id]);

        if ($new_status === 'Fulfilled' && $old_status !== 'Fulfilled') {
            autoResolveDeficiencies($req_id, $pdo);
            syncKitchenInventoryToGoogleSheets($req_id, $pdo);
        }

        $pdo->commit();

        if ($new_status === 'Fulfilled' && $old_status !== 'Fulfilled') {
            sendRequisitionFulfilledTelegram($req_id, $pdo);
        }

        header("Location: requisitions.php");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action_complete_requisition"])) {
    $req_id = intval($_POST["complete_req_id"]);
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("UPDATE requisitions SET status = 'Fulfilled' WHERE id = ?");
        $stmt->execute([$req_id]);
        autoResolveDeficiencies($req_id, $pdo);
        syncKitchenInventoryToGoogleSheets($req_id, $pdo);
        $pdo->commit();
        sendRequisitionFulfilledTelegram($req_id, $pdo);
        header("Location: requisitions.php");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
    }
}

$categories = $pdo->query("SELECT * FROM material_categories ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
$materials  = $pdo->query("SELECT id, item_name as name, category_id, image_path FROM req_catalog ORDER BY item_name ASC")->fetchAll(PDO::FETCH_ASSOC);

// MODIFIED: Fetches strictly the last 10 records for fast scannability on operations panel
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
.split-requisition-layout { display: grid !important; grid-template-columns: 1fr 340px !important; gap: 20px !important; width: 100% !important; align-items: start !important; margin-top: 15px; }
.materials-main-panel { display: flex; flex-direction: column; gap: 24px; }
.catalog-cards-box { background: #ffffff !important; border: 1px solid #e2e8f0 !important; border-radius: 12px !important; padding: 20px !important; box-shadow: 0 1px 3px rgba(0,0,0,0.05) !important; }
.requisition-right-sidebar { background: #ffffff !important; border: 1px solid #cbd5e0 !important; border-radius: 12px !important; padding: 20px !important; position: sticky !important; top: 20px !important; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05) !important; display: flex; flex-direction: column; min-height: 440px; text-align: left; }
.catalog-tab-header { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 20px; border-bottom: 1px solid #edf2f7; padding-bottom: 12px; }
.catalog-tab-btn { padding: 6px 12px; font-size: 12px; font-weight: 600; background: #f7fafc; border: 1px solid #e2e8f0; border-radius: 6px; cursor: pointer; color: #4a5568; }
.catalog-tab-btn.active { background: #06b6d4; color: white; border-color: #06b6d4; }
.material-item-grid { display: grid !important; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)) !important; gap: 10px !important; }
.material-item-card { border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px; background: #fff; text-align: center; display: flex; flex-direction: column; justify-content: space-between; min-height: 105px; }
.material-item-name { font-size: 12px; font-weight: 600; color: #111827; margin-bottom: 8px; line-height: 1.4; }
.sidebar-summary-title { font-size: 14px; font-weight: 700; text-transform: uppercase; color: #111827; border-bottom: 1px dashed #e2e8f0; padding-bottom: 8px; margin-bottom: 15px; margin-top: 0; }
.sidebar-cart-list { flex-grow: 1; overflow-y: auto; max-height: 280px; margin-bottom: 15px; }
.sidebar-cart-row { display: flex; justify-content: space-between; align-items: center; font-size: 13px; padding: 8px 0; border-bottom: 1px solid #f7fafc; }
.qty-btn-sm { padding: 2px 8px; font-size: 12px; font-weight: bold; border: 1px solid #cbd5e0; background: #f7fafc; border-radius: 4px; cursor: pointer; }
.past-log-section { background: #ffffff !important; border: 1px solid #e2e8f0 !important; border-radius: 12px !important; padding: 24px !important; box-shadow: 0 1px 3px rgba(0,0,0,0.05) !important; text-align: left; width: 100%; box-sizing: border-box; margin-top: 24px; }
.past-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.past-table th { background: #f8fafc; padding: 12px; font-weight: 700; color: #4b5563; border-bottom: 2px solid #e2e8f0; }
.past-table td { padding: 12px; border-bottom: 1px solid #edf2f7; color: #111827; vertical-align: middle; }
.status-pill { font-size: 11px; padding: 3px 8px; border-radius: 12px; font-weight: 700; display: inline-block; }
.status-pill.p-pending { background: #fef3c7; color: #d97706; }
.status-pill.p-fulfilled { background: #d1fae5; color: #059669; }

@media (max-width: 1023px) {
    .split-requisition-layout { grid-template-columns: 1fr !important; }
}
</style>

<div class="app-body" style="max-width: 100% !important; width: 100% !important; display: block !important;">
    <div class="category-section" style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
        <h2 class="category-title" style="text-transform: none; margin: 0;">📦 Material Requests Panel</h2>
        <a href="requisitions_log.php" style="padding: 8px 16px; background: #06b6d4; color: #fff; text-decoration: none; border-radius: 6px; font-size: 13px; font-weight: 600; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">📂 View Monthly Logs</a>
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
                                        <button type="button" class="btn btn-start" style="padding: 6px; font-size: 11px; width: 100%; border-radius: 6px;" onclick="window.addMaterialToSidebar(<?= $item['id'] ?>, '<?= htmlspecialchars(addslashes($item['name'])) ?>')">+ Add Item</button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

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
    </div>

    <!-- MODIFIED: Relocated structure positioned completely below requisition layout blocks -->
    <div class="past-log-section">
        <h3 style="font-size: 14px; font-weight: 700; text-transform: uppercase; color: #111827; margin-bottom: 15px; border-bottom: 1px dashed #e2e8f0; padding-bottom: 10px;">📋 Recent Requisitions (Last 10 Requests)</h3>
        <div style="overflow-x: auto;">
            <table class="past-table">
                <thead>
                    <tr>
                        <th style="width: 80px; text-align: center;">Req ID</th>
                        <th style="width: 140px;">Requested At</th>
                        <th>Material Selections Summary</th>
                        <th style="width: 110px; text-align: center;">Status</th>
                        <th style="width: 160px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($past_requisitions)): foreach ($past_requisitions as $pRow): 
                        $summary_clean = !empty($pRow['item_summary']) ? $pRow['item_summary'] : '<span style="color:#a0aec0; font-style:italic;">No mapped materials</span>';
                        $is_fulfilled = ($pRow['status'] === 'Fulfilled');
                        $pill_class = $is_fulfilled ? 'p-fulfilled' : 'p-pending';
                        $status_label = empty($pRow['status']) ? 'Pending' : $pRow['status'];

                        $lines = $pdo->prepare("SELECT ri.catalog_id, ri.quantity, rc.item_name as name FROM requisition_items ri JOIN req_catalog rc ON ri.catalog_id = rc.id WHERE ri.requisition_id = ?");
                        $lines->execute([$pRow['id']]);
                        $serializedItems = json_encode($lines->fetchAll(PDO::FETCH_ASSOC));
                    ?>
                        <tr>
                            <td style="text-align: center; font-weight: 700; color: #4a5568;">#<?= $pRow['id'] ?></td>
                            <td style="color: #718096;"><?= date('d M Y - H:i', strtotime($pRow['requested_at'])) ?></td>
                            <td style="font-weight: 600; color: #2d3748;"><?= $summary_clean ?></td>
                            <td style="text-align: center;">
                                <span class="status-pill <?= $pill_class ?>"><?= $status_label ?></span>
                            </td>
                            <td style="text-align: center;">
                                <div style="display: flex; gap: 6px; justify-content: center;">
                                    <button type="button" class="btn btn-start" style="padding: 6px 10px; font-size: 11px; border-radius: 4px;" data-items='<?= htmlspecialchars($serializedItems, ENT_QUOTES, 'UTF-8') ?>' onclick="window.openEditRequisitionModal(<?= $pRow['id'] ?>, '<?= $status_label ?>', this)">✏ Edit</button>
                                    <?php if (!$is_fulfilled): ?>
                                        <form method="POST" style="margin:0;" onsubmit="return confirm('Mark request #<?= $pRow['id'] ?> as complete?');">
                                            <input type="hidden" name="action_complete_requisition" value="1">
                                            <input type="hidden" name="complete_req_id" value="<?= $pRow['id'] ?>">
                                            <button type="submit" class="btn btn-bill" style="padding: 6px 10px; font-size: 11px; border-radius: 4px; background: #38a169; border-color: #38a169;">✔ Complete</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="5" style="text-align: center; color: #a0aec0; padding: 30px;">No historical data records saved.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="editReqModalPopup" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 99999; justify-content: center; align-items: center; backdrop-filter: blur(4px);">
    <div class="modal-content" style="background: white; max-width: 500px; width: 90%; border-radius: 12px; padding: 25px; position: relative; color: #111827; text-align: left;">
        <span style="position: absolute; top: 12px; right: 16px; font-size: 22px; cursor: pointer; color: #a0aec0;" onclick="window.closeEditReqModal()">✕</span>
        <h3 style="font-size: 15px; font-weight: 700; text-transform: uppercase; margin-bottom: 15px; border-bottom: 1px dashed #e2e8f0; padding-bottom: 8px;">Modify Requisition Parameters</h3>
        
        <form method="POST" action="requisitions.php" style="margin: 0;">
            <input type="hidden" name="action_update_requisition" value="1">
            <input type="hidden" name="update_req_id" id="mdlUpdateId">
            
            <div style="margin-bottom: 15px;">
                <label style="font-size: 12px; font-weight: 600; color: #4b5563; display: block; margin-bottom: 6px;">Ticket Status Allocation</label>
                <select name="update_req_status" id="mdlUpdateStatus" style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid #cbd5e0; font-size: 13px;">
                    <option value="Pending">Pending</option>
                    <option value="Fulfilled">Fulfilled</option>
                </select>
            </div>

            <h4 style="font-size: 12px; font-weight: 700; text-transform: uppercase; color: #4b5563; margin-bottom: 10px;">Item Quantity Mapping</h4>
            <div id="mdlItemsContainer" style="max-height: 200px; overflow-y: auto; border: 1px solid #edf2f7; border-radius: 6px; padding: 8px; margin-bottom: 20px; background: #fdfdfd;"></div>

            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn btn-log" style="padding: 10px 18px;" onclick="window.closeEditReqModal()">Cancel</button>
                <button type="submit" class="btn btn-start" style="padding: 10px 18px;">Commit Updates</button>
            </div>
        </form>
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

window.openEditRequisitionModal = function(reqId, currentStatus, element) {
    document.getElementById("mdlUpdateId").value = reqId;
    document.getElementById("mdlUpdateStatus").value = currentStatus;
    
    const container = document.getElementById("mdlItemsContainer");
    const rawItemsData = element.getAttribute("data-items");
    
    try {
        const items = JSON.parse(rawItemsData);
        if (!items || items.length === 0) {
            container.innerHTML = '<p style="text-align:center; color:#e53e3e; font-size:12px;">No sub-items loaded.</p>';
            return;
        }
        
        container.innerHTML = items.map(i => `
            <div style="display:flex; justify-content:space-between; align-items:center; padding:8px 0; border-bottom:1px solid #edf2f7;">
                <span style="font-size:13px; font-weight:600; color:#2d3748;">${i.name}</span>
                <input type="number" name="req_item_qty[${i.catalog_id}]" value="${i.quantity}" min="0" style="width:65px; padding:5px; border:1px solid #cbd5e0; border-radius:4px; text-align:center; font-size:13px;">
            </div>
        `).join('');
        
        document.getElementById("editReqModalPopup").style.display = "flex";
    } catch(err) {
        container.innerHTML = '<p style="text-align:center; color:#e53e3e; font-size:12px;">Failed parsing data bundle lines.</p>';
    }
};

window.closeEditReqModal = function() { 
    document.getElementById("editReqModalPopup").style.display = "none"; 
};
</script>

<?php include "includes/footer.php"; ?>