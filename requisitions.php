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

// --- CHEF NEW PRODUCT GENERATOR INTERCEPTOR ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action_create_chef_product"])) {
    $item_name   = trim($_POST["chef_prod_name"]);
    $category_id = intval($_POST["chef_prod_category"]);
    $pack_size   = floatval($_POST["chef_pack_size"]);
    $pack_unit   = trim($_POST["chef_pack_unit"]);

    if (!empty($item_name) && $category_id > 0) {
        $stmt = $pdo->prepare("INSERT INTO req_catalog (item_name, category_id, unit_type, unit_label, pack_size, pack_unit, is_verified, unit_cost, image_path) VALUES (?, ?, 'Count', 'Packets', ?, ?, 0, 0.00, 'https://placehold.co/150x100?text=No+Image')");
        $stmt->execute([$item_name, $category_id, $pack_size, $pack_unit]);

        // --- AUDIT TRAIL LOGGING ---
        $audit_stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, timestamp) VALUES (?, ?, NOW())");
        $audit_stmt->execute([$_SESSION['user_id'], "User [" . $_SESSION['username'] . "] registered a new custom product catalog item: [" . $item_name . "]"]);

        $_SESSION['requisition_saved_toast'] = "Product requested with packing specifications!";
    }
    header("Location: requisitions.php");
    exit;
}

// --- MASTER SAVING BATCH SUBMISSION BLOCK ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action_update_requisition"])) {
    $req_id = intval($_POST["update_req_id"]);
    $quantities = $_POST["req_item_qty"] ?? [];
    $item_statuses = $_POST["req_item_status"] ?? [];

    $pdo->beginTransaction();
    try {
        $getOriginalQty = $pdo->prepare("SELECT quantity FROM requisition_items WHERE requisition_id = ? AND catalog_id = ?");
        $logDeficiency  = $pdo->prepare("INSERT INTO deficient_stock_logs (requisition_id, catalog_id, ordered_qty, delivered_qty, deficit_qty) VALUES (?, ?, ?, ?, ?)");
        $updateItem     = $pdo->prepare("UPDATE requisition_items SET quantity = ?, item_status = ? WHERE requisition_id = ? AND catalog_id = ?");
        $deleteItem     = $pdo->prepare("DELETE FROM requisition_items WHERE requisition_id = ? AND catalog_id = ?");

        $all_fulfilled = true;
        
        foreach ($quantities as $cat_id => $qty) {
            $cat_id = intval($cat_id);
            $new_qty = floatval($qty); 
            $allocated_status = isset($item_statuses[$cat_id]) ? trim($item_statuses[$cat_id]) : 'Pending';

            if ($new_qty <= 0 || $allocated_status === 'Cancelled') {
                $deleteItem->execute([$req_id, $cat_id]);
                continue;
            }

            if ($allocated_status !== 'Fulfilled') {
                $all_fulfilled = false;
            }

            $getOriginalQty->execute([$req_id, $cat_id]);
            $original_qty = floatval($getOriginalQty->fetchColumn() ?: 0);

            if ($new_qty < $original_qty && $allocated_status === 'Fulfilled') {
                $deficit = $original_qty - $new_qty;
                $logDeficiency->execute([$req_id, $cat_id, $original_qty, $new_qty, $deficit]);
            }

            $updateItem->execute([$new_qty, $allocated_status, $req_id, $cat_id]);
        }

        $countRemaining = $pdo->prepare("SELECT COUNT(*) FROM requisition_items WHERE requisition_id = ?");
        $countRemaining->execute([$req_id]);
        $remainingItems = intval($countRemaining->fetchColumn() ?: 0);

        if ($remainingItems === 0) {
            $deleteParent = $pdo->prepare("DELETE FROM requisitions WHERE id = ?");
            $deleteParent->execute([$req_id]);
            
            // --- AUDIT TRAIL LOGGING ---
            $audit_stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, timestamp) VALUES (?, ?, NOW())");
            $audit_stmt->execute([$_SESSION['user_id'], "User [" . $_SESSION['username'] . "] entirely cleared and removed Requisition Ticket Sheet #" . $req_id]);

            $_SESSION['requisition_saved_toast'] = "Stock order entirely cleared and removed!";
        } else {
            $final_global_status = $all_fulfilled ? 'Fulfilled' : 'Pending';
            $stmt = $pdo->prepare("UPDATE requisitions SET status = ? WHERE id = ?");
            $stmt->execute([$final_global_status, $req_id]);

            // --- AUDIT TRAIL LOGGING ---
            $audit_stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, timestamp) VALUES (?, ?, NOW())");
            $audit_stmt->execute([$_SESSION['user_id'], "User [" . $_SESSION['username'] . "] updated items/quantities on open Requisition Sheet #" . $req_id . " (Global status set to: " . $final_global_status . ")"]);

            $_SESSION['requisition_saved_toast'] = "Notification Saved successfully!";
        }

        $pdo->commit();
        header("Location: requisitions.php");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
    }
}

$categories = $pdo->query("SELECT * FROM material_categories ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
$materials  = $pdo->query("SELECT id, item_name as name, category_id, unit_type, unit_label, pack_size, pack_unit, image_path FROM req_catalog WHERE is_verified = 1 ORDER BY item_name ASC")->fetchAll(PDO::FETCH_ASSOC);

$past_requisitions = $pdo->query("SELECT r.id, r.requested_at, r.status,
                                  (SELECT GROUP_CONCAT(CONCAT(rc.item_name, ' (', CAST(rc.pack_size AS CHAR), ' ', rc.pack_unit, ') x', ri.quantity, ' ', COALESCE(ri.chosen_unit_label, rc.unit_label)) SEPARATOR ', ')
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

.material-item-grid { display: grid !important; grid-template-columns: repeat(auto-fill, minmax(145px, 1fr)) !important; gap: 12px !important; }
.material-item-card { border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px; background: #fff; text-align: center; display: flex; flex-direction: column; justify-content: space-between; align-items: center; min-height: 175px; box-sizing: border-box; }
.material-item-image-box { width: 100%; height: 50px; overflow: hidden; border-radius: 6px; border: 1px solid #edf2f7; background: #f8fafc; margin-bottom: 8px; }
.material-item-image-box img { width: 100%; height: 100%; object-fit: cover; }
.material-item-name { font-size: 12px; font-weight: 600; color: #111827; margin-bottom: 8px; line-height: 1.3; text-align: center; width: 100%; word-break: break-word; }

.btn-quick-search-box { width: 100%; padding: 10px 14px 10px 35px; border: 1px solid #cbd5e0; border-radius: 8px; font-size: 13px; font-weight: 600; color: #1e293b; background: #fff; outline: none; box-sizing: border-box; transition: all 0.2s; }
.btn-quick-search-box:focus { border-color: #06b6d4; box-shadow: 0 0 0 3px rgba(6, 182, 212, 0.15); }
.search-input-wrapper { position: relative; margin-bottom: 15px; width: 100%; }
.search-icon-inside { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 14px; pointer-events: none; }

.btn-tab-styled-add { 
    display: inline-block !important; padding: 5px 14px !important; font-size: 12px !important; font-weight: 600 !important; 
    background: #ffffff !important; color: #475569 !important; border: 1px solid #cbd5e0 !important; border-radius: 6px !important; 
    cursor: pointer !important; box-shadow: 0 1px 2px rgba(0,0,0,0.02) !important; transition: all 0.15s ease !important; width: 100% !important; margin: 0 auto !important;
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
    display: block !important; width: 100% !important; padding: 6px 4px !important; font-size: 10px !important; font-weight: 700 !important; 
    text-transform: uppercase; border-radius: 6px !important; cursor: pointer !important; text-align: center !important; line-height: 1.3 !important; border: 1px solid transparent !important;
}
.btn-action-trigger span { display: block !important; font-size: 9px !important; font-weight: 800 !important; text-transform: lowercase; }

.btn-status-pending { background: #fef3c7 !important; color: #d97706 !important; border-color: #f59e0b !important; }
.btn-status-pending:hover { background: #fde68a !important; }
.btn-status-fulfilled { background: #d1fae5 !important; color: #059669 !important; border-color: #10b981 !important; }
.btn-status-fulfilled:hover { background: #a7f3d0 !important; }

.btn-sidebar-past-link { display: block !important; text-align: center !important; width: 100% !important; padding: 8px !important; background: #f1f5f9 !important; color: #475569 !important; border: 1px solid #cbd5e0 !important; border-radius: 6px !important; font-size: 12px !important; font-weight: 600 !important; text-decoration: none !important; margin-top: 12px !important; }

.binary-toggle-container { display: flex; gap: 4px; background: #f1f5f9; padding: 3px; border-radius: 6px; border: 1px solid #cbd5e0; }
.toggle-choice-btn { padding: 6px 12px !important; font-size: 11px !important; font-weight: 700 !important; border: none !important; border-radius: 6px !important; color: #ffffff !important; cursor: pointer !important; text-transform: uppercase !important; letter-spacing: 0.5px !important; box-shadow: 0 1px 2px rgba(0,0,0,0.05) !important; }

.btn-block-fulfilled { background: #10b981 !important; border: 1px solid #059669 !important; opacity: 1; }
.btn-block-fulfilled:hover { background: #059669 !important; }
.btn-block-fulfilled:disabled { background: #cbd5e0 !important; border-color: #cbd5e0 !important; color: #94a3b8 !important; cursor: not-allowed !important; opacity: 0.7; }

.btn-block-remove { background: #ef4444 !important; border: 1px solid #dc2626 !important; opacity: 1; }
.btn-block-remove:hover { background: #dc2626 !important; }
.btn-block-remove:disabled { background: #cbd5e0 !important; border-color: #cbd5e0 !important; color: #94a3b8 !important; cursor: not-allowed !important; opacity: 0.7; }

.row-state-greyed-out .item-text-title { color: #94a3b8 !important; text-decoration: line-through; }
.row-state-green-highlight .item-text-title { color: #10b981 !important; font-weight: 800; }

.modal-input-qty { width: 55px; padding: 6px 4px; border: 1px solid #cbd5e0; text-align: center; font-size: 13px; font-weight: 700; color: #1e293b; background: #fff !important; }
.modal-qty-container { display: flex; align-items: center; border-radius: 6px; overflow: hidden; border: 1px solid #cbd5e0; background: #fff; }
.modal-qty-btn { width: 28px; height: 31px; background: #f8fafc; border: none; color: #475569; font-weight: bold; font-size: 14px; cursor: pointer; display: flex; align-items: center; justify-content: center; }
.modal-qty-btn:hover { background: #e2e8f0; color: #0f172a; }

.audit-history-subtitle { font-size: 11px; color: #64748b; font-weight: 600; display: block; margin-top: 2px; font-family: monospace; }
.global-toast-notification { padding: 12px 24px; background: #10b981; color: white; font-size: 14px; font-weight: 800; text-align: center; border-radius: 8px; box-shadow: 0 4px 12px rgba(16,185,129,0.2); margin-bottom: 15px; border: 1px solid #059669; }

/* ==========================================================================
   ADVANCED MOBILE OVERLAY FLOATING SHEET LAYOUT ENGINE
   ========================================================================== */
@media (max-width: 1023px) {
    .split-requisition-layout {
        grid-template-columns: 1fr !important;
        padding-bottom: 75px !important; /* Space so sticky bar does not cover elements */
    }
    
    .right-column-stack {
        position: fixed !important;
        bottom: 0 !important;
        left: 0 !important;
        width: 100% !important;
        z-index: 9999 !important;
        gap: 0 !important;
    }

    .past-log-section {
        display: none !important; /* Hide logs on mobile workspace to prevent visual clutter */
    }

    .requisition-right-sidebar {
        border-radius: 20px 20px 0 0 !important;
        border: none !important;
        border-top: 2px solid #cbd5e0 !important;
        box-shadow: 0 -4px 15px rgba(0, 0, 0, 0.12) !important;
        padding: 12px 18px !important;
        background: #ffffff !important;
    }

    .sidebar-summary-title {
        margin-bottom: 8px !important;
        padding-bottom: 4px !important;
        font-size: 13px !important;
        display: flex !important;
        justify-content: space-between !important;
        align-items: center !important;
        cursor: pointer !important;
    }
    
    /* Dynamic arrow indicator showing drawer expandable status */
    .sidebar-summary-title::after {
        content: "▲ Expand Chosen Box";
        font-size: 11px;
        color: #06b6d4;
        font-weight: bold;
    }
    
    /* Toggle action swaps content when drawer gets clicked */
    .requisition-right-sidebar.drawer-open-state .sidebar-summary-title::after {
        content: "▼ Collapse Content";
    }

    .sidebar-cart-list {
        max-height: 0px !important;
        overflow-y: auto !important;
        margin-bottom: 0px !important;
        transition: max-height 0.25s ease-out, margin 0.25s ease-out !important;
    }

    .requisition-right-sidebar.drawer-open-state .sidebar-cart-list {
        max-height: 200px !important;
        margin-bottom: 12px !important;
    }

    .requisition-right-sidebar div style {
        border-top: none !important;
        padding-top: 0px !important;
    }
}
</style>

<div class="app-body" style="max-width: 100% !important; width: 100% !important; display: block !important;">
    
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
                <div class="search-input-wrapper">
                    <span class="search-icon-inside">🔍</span>
                    <input type="text" id="catalogQuickSearchInput" class="btn-quick-search-box" placeholder="Quick search materials registry on the fly..." onkeyup="window.quickSearchCatalogRegistry()">
                </div>

                <div class="catalog-tab-header">
                    <button type="button" id="globalAllTabBtn" class="catalog-tab-btn active" onclick="window.filterMaterialCatalog('all', this)">All Items</button>
                    <?php foreach ($categories as $cat): ?>
                        <button type="button" class="catalog-tab-btn" onclick="window.filterMaterialCatalog('cat_<?= $cat['id'] ?>', this)"><?= htmlspecialchars($cat['name']) ?></button>
                    <?php endforeach; ?>
                    <button type="button" class="catalog-tab-btn" style="background:#fffbeb; color:#d97706; border: 1px dashed #f59e0b; margin-left: auto;" onclick="window.openChefNewProductModal()">➕ New Product</button>
                </div>

                <div id="materialCatalogContainer">
                    <?php foreach ($categories as $cat):
                        $catItems = array_filter($materials, function($x) use ($cat) {
                            return (intval($x['category_id']) === intval($cat['id']));
                        });
                        if (empty($catItems)) continue;
                    ?>
                        <div class="category-block" id="cat_<?= $cat['id'] ?>" style="margin-bottom: 25px;">
                            <h4 class="category-block-title" style="font-size: 13px; text-transform: uppercase; color: #4b5563; text-align: left; margin-bottom: 10px; font-weight: 700;"><?= $cat['name'] ?></h4>
                            <div class="material-item-grid">
                                <?php foreach ($catItems as $item): 
                                    $item_img = !empty($item['image_path']) ? $item['image_path'] : 'https://placehold.co/150x100?text=No+Image';
                                ?>
                                    <div class="material-item-card" data-search-name="<?= strtolower(htmlspecialchars($item['name'])) ?>">
                                        <div class="material-item-image-box">
                                            <img src="<?= $item_img ?>" alt="" onerror="this.src='https://placehold.co/150x100?text=No+Image';">
                                        </div>
                                        <div class="material-item-name">
                                            <?= htmlspecialchars($item['name']) ?>
                                            <div style="font-size:10px; color:#64748b; font-weight:bold; margin-top:2px;">(Size: <?= floatval($item['pack_size']) ?> <?= $item['pack_unit'] ?>)</div>
                                        </div>
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
            <div class="requisition-right-sidebar" id="mobileSummaryStickyWrapper" onclick="window.handleMobileDrawerCollapseToggle(event)">
                <h3 class="sidebar-summary-title">📝 Requisition Summary</h3>
                <div class="sidebar-cart-list" id="sidebarCartRowsContainer">
                    <p style="color: #a0aec0; text-align: center; font-size: 13px; margin-top: 40px; font-style: italic;">No items added to this request list yet.</p>
                </div>
                <div>
                    <div style="display: flex; justify-content: space-between; font-weight: 700; font-size: 14px; margin-bottom: 12px; color: #111827;">
                        <span>Total Item Types:</span>
                        <span id="sidebarTotalCount">0</span>
                    </div>
                    <button type="button" class="btn btn-bill" style="width: 100%; padding: 12px; font-size: 13px; font-weight: bold; border-radius: 8px;" onclick="window.submitSidebarRequisition(event)">Submit Requisition</button>
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

                                $lines = $pdo->prepare("SELECT ri.quantity, rc.unit_type, rc.unit_label, ri.chosen_unit_label, COALESCE(ri.item_status, 'Pending') as item_status, rc.item_name as name, ri.catalog_id, rc.pack_size, rc.pack_unit, rc.image_path FROM requisition_items ri JOIN req_catalog rc ON ri.catalog_id = rc.id WHERE ri.requisition_id = ?");
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
    <div class="modal-content" style="background: white; max-width: 580px; width: 92%; border-radius: 12px; padding: 25px; position: relative; color: #111827; text-align: left;">
        <span style="position: absolute; top: 12px; right: 16px; font-size: 22px; cursor: pointer; color: #a0aec0;" onclick="window.closeEditReqModal()">✕</span>
        <h3 style="font-size: 14px; font-weight: 700; text-transform: uppercase; margin-bottom: 15px; border-bottom: 1px dashed #e2e8f0; padding-bottom: 8px; letter-spacing: 0.5px;">Modify Stock Order</h3>
        
        <form method="POST" action="requisitions.php" style="margin: 0;">
            <input type="hidden" name="action_update_requisition" value="1">
            <input type="hidden" name="update_req_id" id="mdlUpdateId">
            <div id="mdlItemsContainer" style="max-height: 290px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 8px; padding: 6px; margin-bottom: 20px; background: #fafafa;"></div>
            <div style="display: flex; gap: 10px; justify-content: flex-end; align-items: center;">
                <button type="button" class="btn btn-log" style="padding: 10px 18px;" onclick="window.closeEditReqModal()">Cancel</button>
                <button type="submit" class="btn btn-start" style="padding: 10px 24px; font-weight: 800;">Save</button>
            </div>
        </form>
    </div>
</div>

<div id="chefNewProductModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); z-index: 99999; justify-content: center; align-items: center; backdrop-filter: blur(4px);">
    <div class="modal-content" style="background: white; max-width: 440px; width: 90%; border-radius: 12px; padding: 25px; color: #111827; text-align: left;">
        <span style="position: absolute; top: 12px; right: 16px; font-size: 22px; cursor: pointer; color: #a0aec0;" onclick="window.closeChefNewProductModal()">✕</span>
        <h3 style="font-size: 14px; font-weight: 700; text-transform: uppercase; margin-bottom: 15px; border-bottom: 1px dashed #e2e8f0; padding-bottom: 8px; color: #d97706;">Request Unlisted Product</h3>
        <form method="POST" action="requisitions.php" style="margin: 0;">
            <input type="hidden" name="action_create_chef_product" value="1">
            <div style="margin-bottom: 12px;">
                <label style="font-size: 11px; font-weight: 700; color: #4b5563; display: block; margin-bottom: 4px;">Product Name Description</label>
                <input type="text" name="chef_prod_name" required style="width:100%; padding:8px; border-radius:6px; border:1px solid #cbd5e0; font-size:13px;" placeholder="e.g. Fresh Milk Packets">
            </div>
            <div style="margin-bottom: 12px;">
                <label style="font-size: 11px; font-weight: 700; color: #4b5563; display: block; margin-bottom: 4px;">Allocation Category</label>
                <select name="chef_prod_category" required style="width:100%; padding:8px; border-radius:6px; border:1px solid #cbd5e0; font-size:13px;">
                    <option value="">-- Choose Category --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div style="margin-bottom: 20px; display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <div>
                    <label style="font-size: 11px; font-weight: 700; color: #4b5563; display: block; margin-bottom: 4px;">Packaging Value Size</label>
                    <input type="number" name="chef_pack_size" required step="0.1" value="1" style="width:100%; padding:8px; border-radius:6px; border:1px solid #cbd5e0; font-size:13px;">
                </div>
                <div>
                    <label style="font-size: 11px; font-weight: 700; color: #4b5563; display: block; margin-bottom: 4px;">Capacity Metric</label>
                    <select name="chef_pack_unit" style="width:100%; padding:8px; border-radius:6px; border:1px solid #cbd5e0; font-size:13px;">
                        <option value="kg">kg</option>
                        <option value="gms">gms</option>
                        <option value="Ltr">Ltr</option>
                        <option value="ml">ml</option>
                        <option value="Pcs">Pcs</option>
                    </select>
                </div>
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn btn-log" style="padding: 8px 16px;" onclick="window.closeChefNewProductModal()">Cancel</button>
                <button type="submit" class="btn btn-bill" style="padding: 8px 20px; font-weight: 800; background:#d97706; border-color:#d97706;">Add To Catalog</button>
            </div>
        </form>
    </div>
</div>

<script>
window.reqCart = [];
let activeFilteredTabId = 'all';

// DYNAMIC HANDLER TO EXPAND/COLLAPSE CHOSEN PRODUCTS DRAWER ON MOBILE CONTEXTS
window.handleMobileDrawerCollapseToggle = function(event) {
    if (window.innerWidth >= 1024) return; // Ignore on desktop viewports
    
    const sidebar = document.getElementById("mobileSummaryStickyWrapper");
    // Only toggle if they click the heading layer directly
    if (event.target.closest('.sidebar-summary-title')) {
        sidebar.classList.toggle("drawer-open-state");
    }
};

window.quickSearchCatalogRegistry = function() {
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
                card.style.display = "flex";
                parentHasVisibleItem = true;
            } else {
                card.style.display = "none";
            }
        });

        block.style.display = parentHasVisibleItem ? "block" : "none";
    });
};

window.filterMaterialCatalog = function(catId, btn) {
    activeFilteredTabId = catId;
    document.getElementById("catalogQuickSearchInput").value = ""; 

    document.querySelectorAll(".catalog-tab-btn").forEach(b => b.classList.remove("active"));
    if (btn) btn.classList.add("active");
    
    document.querySelectorAll(".category-block").forEach(block => {
        if (catId === 'all' || block.id === catId) {
            block.style.display = "block";
            block.querySelectorAll(".material-item-card").forEach(c => c.style.display = "flex");
        } else {
            block.style.display = "none";
        }
    });
};

window.addMaterialToSidebar = function(id, name) {
    let existing = window.reqCart.find(x => x.id === id);
    if (existing) { existing.qty += 1; }
    else { window.reqCart.push({ id: id, name: name, qty: 1 }); }
    window.renderSidebarCart();
    
    // Auto expand mobile drawer briefly so staff see visual confirmation of choice load
    if (window.innerWidth < 1024) {
        document.getElementById("mobileSummaryStickyWrapper").classList.add("drawer-open-state");
    }
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
            <div style="font-weight: 600; color: #111827; max-width: 140px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${item.name}</div>
            <div style="display: flex; align-items: center; gap: 8px;">
                <button type="button" class="qty-btn-sm" onclick="window.updateSidebarQty(${item.id}, -1)">-</button>
                <span style="font-weight: 700; font-size: 13px; width: 25px; text-align: center;">${item.qty}</span>
                <button type="button" class="qty-btn-sm" onclick="window.updateSidebarQty(${item.id}, 1)">+</button>
            </div>
        </div>
    `).join('');
};

window.submitSidebarRequisition = function(event) {
    if(event) event.stopPropagation(); // Avoid triggering mobile collapse actions
    if (window.reqCart.length === 0) return alert("Please select material choices first.");
    if (!confirm("Dispatch this material request list to inventory history logs?")) return;

    fetch("process_requisition.php", {
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
            alert("❌ Connection error: " + (data.error || "Unknown validation exception"));
        }
    }).catch(err => alert("❌ Network connection failure. Check if process_requisition.php is missing."));
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
            const initialStatus = i.item_status || 'Pending';
            const unitType = i.unit_type || 'Count';
            const currentLabel = i.chosen_unit_label || i.unit_label || 'Pcs';
            const pSize = parseFloat(i.pack_size) || 1;
            const pUnit = i.pack_unit || 'Pcs';
            
            let rowStateClass = '';
            let fDisabled = '';
            let rDisabled = '';
           
            if (initialStatus === 'Fulfilled') { rowStateClass = 'row-state-green-highlight'; fDisabled = 'disabled'; }
            if (initialStatus === 'Cancelled') { rowStateClass = 'row-state-greyed-out'; rDisabled = 'disabled'; }

            const computationFactor = (unitType === 'Weight') ? 0.25 : 1;

            return `
                <div id="itemVerificationRow_${i.catalog_id}" class="verification-item-row-wrapper ${rowStateClass}" style="display:flex; justify-content:space-between; align-items:center; padding:12px 10px; border-bottom:1px solid #e2e8f0; background:#ffffff; margin-bottom:6px; border-radius:8px; gap:12px; width:100%; box-sizing:border-box;">
                    <div style="flex:1; min-width:0; text-align:left;">
                        <span class="item-text-title" style="font-size:13px; font-weight:700; color:#1e293b; display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${i.name}</span>
                        <span style="font-size:11px; color:#475569; font-weight:bold; display:block; margin-bottom:2px;">Packing Spec: ${pSize} ${pUnit} | Target: ${currentLabel}</span>
                        <span id="qtyAuditSubtitle_${i.catalog_id}" class="audit-history-subtitle" data-original="${i.quantity}">Ordered: ${i.quantity}</span>
                    </div>
                   
                    <div style="display:flex; align-items:center; gap:6px;">
                        <div class="modal-qty-container">
                            <button type="button" class="modal-qty-btn" onclick="window.adjustVerificationRowQty(${i.catalog_id}, -${computationFactor})">-</button>
                            <input type="number" id="mdlQtyInput_${i.catalog_id}" name="req_item_qty[${i.catalog_id}]" value="${i.quantity}" min="0" step="${computationFactor}" class="modal-input-qty" oninput="window.handleQuantityInputChangeDirect(${i.catalog_id})">
                            <button type="button" class="modal-qty-btn" onclick="window.adjustVerificationRowQty(${i.catalog_id}, ${computationFactor})">+</button>
                        </div>
                    </div>

                    <div class="binary-toggle-container" style="background:transparent; border:none; padding:0;">
                        <input type="hidden" id="mdlStatusHidden_${i.catalog_id}" name="req_item_status[${i.catalog_id}]" value="${initialStatus}">
                        <button type="button" id="toggleBtn_F_${i.catalog_id}" ${fDisabled} class="toggle-choice-btn btn-block-fulfilled" onclick="window.triggerMemoryStateUpdate(${i.catalog_id}, 'Fulfilled')">Fulfilled</button>
                        <button type="button" id="toggleBtn_C_${i.catalog_id}" ${rDisabled} class="toggle-choice-btn btn-block-remove" onclick="window.triggerMemoryStateUpdate(${i.catalog_id}, 'Cancelled')">Remove</button>
                    </div>
                </div>
            `;
        }).join('');
        
        items.forEach(i => window.syncRowAuditText(i.catalog_id));
        document.getElementById("editReqModalPopup").style.display = "flex";
    } catch(err) {
        container.innerHTML = '<p style="text-align:center; color:#ef4444; font-size:12px; padding:15px;">Failed loading data arrays.</p>';
    }
};

window.adjustVerificationRowQty = function(catalogId, stepValue) {
    const input = document.getElementById(`mdlQtyInput_${catalogId}`);
    if (input) {
        let currentVal = parseFloat(input.value) || 0;
        let finalVal = Math.max(0, currentVal + stepValue);
        input.value = finalVal % 1 === 0 ? finalVal : finalVal.toFixed(2);
       
        window.reactivateActionRowButtons(catalogId, finalVal);
        window.syncRowAuditText(catalogId);
    }
};

window.handleQuantityInputChangeDirect = function(catalogId) {
    const input = document.getElementById(`mdlQtyInput_${catalogId}`);
    if (input) {
        let finalVal = parseFloat(input.value) || 0;
        if (finalVal < 0) { finalVal = 0; input.value = 0; }
       
        window.reactivateActionRowButtons(catalogId, finalVal);
        window.syncRowAuditText(catalogId);
    }
};

window.reactivateActionRowButtons = function(catalogId, activeValue) {
    const hiddenStatus = document.getElementById(`mdlStatusHidden_${catalogId}`);
    const rowWrapper   = document.getElementById(`itemVerificationRow_${catalogId}`);
    const btnFulfilled = document.getElementById("toggleBtn_F_" + catalogId);
    const btnCancelled = document.getElementById("toggleBtn_C_" + catalogId);

    if (!hiddenStatus || !rowWrapper || !btnFulfilled || !btnCancelled) return;

    rowWrapper.classList.remove('row-state-greyed-out', 'row-state-green-highlight');
    btnFulfilled.removeAttribute('disabled');
    btnCancelled.removeAttribute('disabled');

    hiddenStatus.value = (activeValue > 0) ? 'Pending' : 'Cancelled';
    if (activeValue === 0) {
        rowWrapper.classList.add('row-state-greyed-out');
        btnCancelled.setAttribute('disabled', 'disabled');
        window.syncRowAuditText(catalogId);
    }
};

window.syncRowAuditText = function(catalogId) {
    const input = document.getElementById(`mdlQtyInput_${catalogId}`);
    const subtitle = document.getElementById(`qtyAuditSubtitle_${catalogId}`);
    if (!input || !subtitle) return;

    const originalCount = parseFloat(subtitle.getAttribute("data-original")) || 0;
    const currentCount  = parseFloat(input.value) || 0;

    if (currentCount === originalCount) {
        subtitle.innerHTML = `Ordered: ${originalCount}`;
    } else {
        subtitle.innerHTML = `Ordered: <del>${originalCount}</del> <strong style="color:#0284c7;">${currentCount}</strong>`;
    }
};

window.triggerMemoryStateUpdate = function(catalogId, targetedState) {
    const hiddenStatus = document.getElementById(`mdlStatusHidden_${catalogId}`);
    const rowWrapper   = document.getElementById(`itemVerificationRow_${catalogId}`);
    const qtyInput     = document.getElementById(`mdlQtyInput_${catalogId}`);
    const btnFulfilled = document.getElementById("toggleBtn_F_" + catalogId);
    const btnCancelled = document.getElementById("toggleBtn_C_" + catalogId);

    if (!hiddenStatus || !rowWrapper || !qtyInput || !btnFulfilled || !btnCancelled) return;

    rowWrapper.classList.remove('row-state-greyed-out', 'row-state-green-highlight');
    btnFulfilled.removeAttribute('disabled');
    btnCancelled.removeAttribute('disabled');

    if (hiddenStatus.value === targetedState) {
        hiddenStatus.value = 'Pending';
    } else {
        hiddenStatus.value = targetedState;
        if (targetedState === 'Cancelled') {
            qtyInput.value = 0;
            rowWrapper.classList.add('row-state-greyed-out');
            btnCancelled.setAttribute('disabled', 'disabled');
            window.syncRowAuditText(catalogId);
        } else if (targetedState === 'Fulfilled') {
            rowWrapper.classList.add('row-state-green-highlight');
            btnFulfilled.setAttribute('disabled', 'disabled');
        }
    }
};

window.closeChefNewProductModal = function() {
    document.getElementById("chefNewProductModal").style.display = "none";
};
window.openChefNewProductModal = function() {
    document.getElementById("chefNewProductModal").style.display = "flex";
};
window.closeChefNewProductModal = function() {
    document.getElementById("chefNewProductModal").style.display = "none";
};
</script>

<?php include "includes/footer.php"; ?>