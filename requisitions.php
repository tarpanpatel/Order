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

// Automatically loops through items, extracts catalog unit costs, and syncs data blocks downstream[cite: 1]
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

// --- AUTOMATED DEFICIENCY RESOLUTION ENGINE ---[cite: 1]
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

// --- SAVING SUBMISSION INTERCEPTOR RE-INSTALLED TO SAVE ENTIRE BATCH AT ONCE ---[cite: 1]
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action_update_requisition"])) {
    $req_id = intval($_POST["update_req_id"]);
    $quantities = $_POST["req_item_qty"] ?? [];
    $item_statuses = $_POST["req_item_status"] ?? [];

    $pdo->beginTransaction();
    try {
        $getOriginalQty = $pdo->prepare("SELECT quantity FROM requisition_items WHERE requisition_id = ? AND catalog_id = ?");
        $logDeficiency  = $pdo->prepare("INSERT INTO deficient_stock_logs (requisition_id, catalog_id, ordered_qty, delivered_qty, deficit_qty) VALUES (?, ?, ?, ?, ?)");
        $updateItem     = $pdo->prepare("UPDATE requisition_items SET quantity = ?, item_status = ? WHERE requisition_id = ? AND catalog_id = ?");

        $all_fulfilled = true;
        
        foreach ($quantities as $cat_id => $qty) {
            $cat_id = intval($cat_id);
            $new_qty = intval($qty);
            $allocated_status = isset($item_statuses[$cat_id]) ? trim($item_statuses[$cat_id]) : 'Pending';

            if ($allocated_status !== 'Fulfilled' && $allocated_status !== 'Cancelled') {
                $all_fulfilled = false;
            }

            $getOriginalQty->execute([$req_id, $cat_id]);
            $original_qty = intval($getOriginalQty->fetchColumn() ?: 0);

            if ($new_qty < $original_qty && $allocated_status === 'Fulfilled') {
                $deficit = $original_qty - $new_qty;
                $logDeficiency->execute([$req_id, $cat_id, $original_qty, $new_qty, $deficit]);
            }

            $updateItem->execute([$new_qty, $allocated_status, $req_id, $cat_id]);
        }

        $final_global_status = $all_fulfilled ? 'Fulfilled' : 'Pending';
        
        $stmt = $pdo->prepare("UPDATE requisitions SET status = ? WHERE id = ?");
        $stmt->execute([$final_global_status, $req_id]);

        if ($final_global_status === 'Fulfilled') {
            autoResolveDeficiencies($req_id, $pdo);
            syncKitchenInventoryToGoogleSheets($req_id, $pdo);
            sendRequisitionFulfilledTelegram($req_id, $pdo);
        }

        $pdo->commit();
        $_SESSION['requisition_saved_toast'] = "Notification Saved successfully!";
        header("Location: requisitions.php");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
    }
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

.binary-toggle-container { display: flex; gap: 4px; background: #f1f5f9; padding: 3px; border-radius: 6px; border: 1px solid #cbd5e0; }
.toggle-choice-btn { padding: 6px 12px !important; font-size: 11px !important; font-weight: 700 !important; border: none !important; border-radius: 6px !important; color: #ffffff !important; cursor: pointer !important; text-transform: uppercase !important; letter-spacing: 0.5px !important; box-shadow: 0 1px 2px rgba(0,0,0,0.05) !important; }

/* Fixed baseline state styling blueprints */
.btn-block-fulfilled { background: #10b981 !important; border: 1px solid #059669 !important; opacity: 1; }
.btn-block-fulfilled:hover { background: #059669 !important; }
.btn-block-remove { background: #ef4444 !important; border: 1px solid #dc2626 !important; opacity: 1; }
.btn-block-remove:hover { background: #dc2626 !important; }

/* Visual feedback state modifier targets */
.row-state-greyed-out .item-text-title { color: #94a3b8 !important; text-decoration: line-through; }
.row-state-greyed-out .btn-block-remove { background: #cbd5e0 !important; border-color: #cbd5e0 !important; color: #94a3b8 !important; cursor: not-allowed !important; opacity: 0.6; }

.row-state-green-highlight .item-text-title { color: #10b981 !important; font-weight: 800; }
.row-state-green-highlight .btn-block-fulfilled { background: #cbd5e0 !important; border-color: #cbd5e0 !important; color: #94a3b8 !important; cursor: not-allowed !important; opacity: 0.6; }

.modal-input-qty { width: 50px; padding: 6px 4px; border: 1px solid #cbd5e0; text-align: center; font-size: 13px; font-weight: 700; color: #1e293b; border-radius: 0; border-left: none; border-right: none; background: #fff !important; }
.modal-qty-container { display: flex; align-items: center; border-radius: 6px; overflow: hidden; border: 1px solid #cbd5e0; background: #fff; }
.modal-qty-btn { width: 28px; height: 31px; background: #f8fafc; border: none; color: #475569; font-weight: bold; font-size: 14px; cursor: pointer; display: flex; align-items: center; justify-content: center; }
.modal-qty-btn:hover { background: #e2e8f0; color: #0f172a; }

.audit-history-subtitle { font-size: 11px; color: #64748b; font-weight: 600; display: block; margin-top: 2px; font-family: monospace; }
.global-toast-notification { padding: 12px 24px; background: #10b981; color: white; font-size: 14px; font-weight: 800; text-align: center; border-radius: 8px; box-shadow: 0 4px 12px rgba(16,185,129,0.2); margin-bottom: 15px; border: 1px solid #059669; }
</style>

<div class="app-body" style="max-width: 100% !important; width: 100% !important; display: block !important;">
    
    <!-- HIGH-VISIBILITY TOAST NOTIFICATION CONTAINER -->
    <?php if (isset($_SESSION['requisition_saved_toast'])): ?>
        <div class="global-toast-notification">
            📢 <strong><?= htmlspecialchars($_SESSION['requisition_saved_toast']) ?></strong>
        </div>
    <?php unset($_SESSION['requisition_saved_toast']); endif; ?>

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

<!-- CLIENT-SIDE RE-INTEGRATED STANDALONE TRANSACTION BATCH MODAL LAYER -->
<div id="editReqModalPopup" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); z-index: 99999; justify-content: center; align-items: center; backdrop-filter: blur(4px);">
    <div class="modal-content" style="background: white; max-width: 580px; width: 94%; border-radius: 12px; padding: 25px; position: relative; color: #111827; text-align: left;">
        <span style="position: absolute; top: 12px; right: 16px; font-size: 22px; cursor: pointer; color: #a0aec0;" onclick="window.closeEditReqModal()">✕</span>
        <h3 style="font-size: 14px; font-weight: 700; text-transform: uppercase; margin-bottom: 15px; border-bottom: 1px dashed #e2e8f0; padding-bottom: 8px; letter-spacing: 0.5px;">Modify Requisition Parameters</h3>
        
        <!-- NATIVE SQL FORM RESTORED TO RETREIVE ENTIRE CHANGESET MAP AT ONCE -->[cite: 1]
        <form method="POST" action="requisitions.php" style="margin: 0;">
            <input type="hidden" name="action_update_requisition" value="1">
            <input type="hidden" name="update_req_id" id="mdlUpdateId">
            
            <h4 style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #64748b; margin-bottom: 12px; letter-spacing: 0.5px;">Item Verification Matrix</h4>
            <div id="mdlItemsContainer" style="max-height: 290px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 8px; padding: 6px; margin-bottom: 20px; background: #fafafa;"></div>

            <div style="display: flex; gap: 10px; justify-content: flex-end; align-items: center;">
                <button type="button" class="btn btn-log" style="padding: 10px 18px;" onclick="window.closeEditReqModal()">Cancel</button>
                <!-- RENAME: Commit updates button renamed to Save -->
                <button type="submit" class="btn btn-start" style="padding: 10px 24px; font-weight: 800;">Save</button>
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

// --- RENDER COMPACT INTERACTION MATRIX AND POPULATE SUB-DOM TREE ---
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
            const initialStatus = i.item_status || 'Pending';
            let rowStateClass = '';
            if (initialStatus === 'Fulfilled') rowStateClass = 'row-state-green-highlight';
            if (initialStatus === 'Cancelled') rowStateClass = 'row-state-greyed-out';

            return `
                <div id="itemVerificationRow_${i.catalog_id}" class="verification-item-row-wrapper ${rowStateClass}" style="display:flex; justify-content:space-between; align-items:center; padding:12px 10px; border-bottom:1px solid #e2e8f0; background:#ffffff; margin-bottom:6px; border-radius:8px; gap:8px;">
                    <div style="flex:1; min-width:0; text-align:left;">
                        <span class="item-text-title" style="font-size:12px; font-weight:700; color:#1e293b; display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; transition:all 0.15s ease;">${i.name}</span>
                        <!-- AUDIT TRAIL TIMELINE ELEMENT HOOK -->
                        <span id="qtyAuditSubtitle_${i.catalog_id}" class="audit-history-subtitle" data-original="${i.quantity}">Ordered: ${i.quantity}</span>
                    </div>
                    
                    <div class="modal-qty-container">
                        <button type="button" class="modal-qty-btn" onclick="window.adjustVerificationRowQty(${i.catalog_id}, -1)">-</button>
                        <input type="number" id="mdlQtyInput_${i.catalog_id}" name="req_item_qty[${i.catalog_id}]" value="${i.quantity}" min="0" class="modal-input-qty" oninput="window.syncRowAuditText(${i.catalog_id})">
                        <button type="button" class="modal-qty-btn" onclick="window.adjustVerificationRowQty(${i.catalog_id}, 1)">+</button>
                    </div>

                    <div class="binary-toggle-container" style="background:transparent; border:none; padding:0;">
                        <input type="hidden" id="mdlStatusHidden_${i.catalog_id}" name="req_item_status[${i.catalog_id}]" value="${initialStatus}">
                        <button type="button" class="toggle-choice-btn btn-block-fulfilled" onclick="window.triggerMemoryStateUpdate(${i.catalog_id}, 'Fulfilled')">Fulfilled</button>
                        <button type="button" class="toggle-choice-btn btn-block-remove" onclick="window.triggerMemoryStateUpdate(${i.catalog_id}, 'Cancelled')">Remove</button>
                    </div>
                </div>
            `;
        }).join('');
        
        // Loop again quickly on render to correctly configure the cross-strike text states on setup initialization
        items.forEach(i => window.syncRowAuditText(i.catalog_id));
        document.getElementById("editReqModalPopup").style.display = "flex";
    } catch(err) {
        container.innerHTML = '<p style="text-align:center; color:#ef4444; font-size:12px; padding:15px;">Failed loading data arrays.</p>';
    }
};

window.adjustVerificationRowQty = function(catalogId, stepValue) {
    const input = document.getElementById(`mdlQtyInput_${catalogId}`);
    if (input) {
        let currentVal = parseInt(input.value) || 0;
        input.value = Math.max(0, currentVal + stepValue);
        
        // Force evaluation check logic rules: if quantity goes back above 0, clear accidental greyed out states
        if (currentVal + stepValue > 0) {
            const hiddenStatus = document.getElementById(`mdlStatusHidden_${catalogId}`);
            if (hiddenStatus && hiddenStatus.value === 'Cancelled') {
                window.triggerMemoryStateUpdate(catalogId, 'Pending');
            }
        }
        window.syncRowAuditText(catalogId);
    }
};

window.validateInputBound = function(element) {
    let val = parseInt(element.value);
    if (isNaN(val) || val < 0) { element.value = 0; }
};

// --- DYNAMICALLY RENDER STRIKETHROUGH CHARACTERS AUDIT PATTERNS ---
window.syncRowAuditText = function(catalogId) {
    const input = document.getElementById(`mdlQtyInput_${catalogId}`);
    const subtitle = document.getElementById(`qtyAuditSubtitle_${catalogId}`);
    if (!input || !subtitle) return;

    const originalCount = parseInt(subtitle.getAttribute("data-original")) || 0;
    const currentCount  = parseInt(input.value) || 0;

    if (currentCount === originalCount) {
        subtitle.innerHTML = `Ordered: ${originalCount}`;
    } else {
        subtitle.innerHTML = `Ordered: <del>${originalCount}</del> <strong style="color:#0284c7;">${currentCount}</strong>`;
    }
};

// --- LOCAL APPLICATION MEMORY LAYER MODIFIER STATE ENGINE ---
window.triggerMemoryStateUpdate = function(catalogId, targetedState) {
    const hiddenStatus = document.getElementById(`mdlStatusHidden_${catalogId}`);
    const rowWrapper   = document.getElementById(`itemVerificationRow_${catalogId}`);
    const inputQty     = document.getElementById(`mdlQtyInput_${catalogId}`);

    if (!hiddenStatus || !rowWrapper) return;

    // Reset runtime utility class mappings
    rowWrapper.classList.remove('row-state-greyed-out', 'row-state-green-highlight');

    if (targetedState === 'Cancelled') {
        hiddenStatus.value = 'Cancelled';
        rowWrapper.classList.add('row-state-greyed-out');
    } else if (targetedState === 'Fulfilled') {
        hiddenStatus.value = 'Fulfilled';
        rowWrapper.classList.add('row-state-green-highlight');
    } else {
        hiddenStatus.value = 'Pending';
    }
};

window.closeEditReqModal = function() { 
    document.getElementById("editReqModalPopup").style.display = "none"; 
};
</script>

<?php include "includes/footer.php"; ?>