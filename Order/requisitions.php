<?php
require_once "config/db.php";
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION["user_id"])) { header("Location: login.php"); exit; }

// --- 1. FIXED BACKEND PAYLOAD INSERTION ENGINE ---
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $input = json_decode(file_get_contents("php://input"), true);
    
    if (isset($input["submit_requisition"]) && !empty($input["materials"])) {
        $pdo->beginTransaction();
        try {
            // Inserts into your exact 4-column schema fields seen in your phpMyAdmin description
            $stmt = $pdo->prepare("INSERT INTO requisitions (status, requested_at) VALUES ('Pending', NOW())");
            $stmt->execute();
            $requisition_id = $pdo->lastInsertId();

            $item_stmt = $pdo->prepare("INSERT INTO requisition_items (requisition_id, catalog_id, quantity) VALUES (?, ?, ?)");
            
            foreach ($input["materials"] as $material_id => $qty) {
                $qty = intval($qty);
                if ($qty > 0) {
                    $stmt_name = $pdo->prepare("SELECT name FROM materials WHERE id = ?");
                    $stmt_name->execute([intval($material_id)]);
                    $m_name = $stmt_name->fetchColumn();
                    
                    if ($m_name) {
                        $stmt_cat = $pdo->prepare("SELECT id FROM req_catalog WHERE item_name = ?");
                        $stmt_cat->execute([$m_name]);
                        $catalog_id = $stmt_cat->fetchColumn();
                        
                        if ($catalog_id) {
                            $item_stmt->execute([$requisition_id, $catalog_id, $qty]);
                        }
                    }
                }
            }
            $pdo->commit();
            header('Content-Type: application/json');
            echo json_encode(["success" => true]);
            exit;
        } catch (Exception $e) { 
            $pdo->rollBack(); 
            header('Content-Type: application/json');
            echo json_encode(["success" => false, "error" => $e->getMessage()]);
            exit;
        }
    }
}

// --- 2. HANDLE EDITING POPUP ITEMS MODIFICATIONS ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_requisition_items"])) {
    $requisition_id = intval($_POST["edit_requisition_id"]);
    if (isset($_POST["edit_items"])) {
        $pdo->beginTransaction();
        try {
            foreach ($_POST["edit_items"] as $item_id => $qty) {
                $qty = intval($qty);
                if ($qty <= 0) {
                    $pdo->prepare("DELETE FROM requisition_items WHERE id = ?")->execute([intval($item_id)]);
                } else {
                    $pdo->prepare("UPDATE requisition_items SET quantity = ? WHERE id = ?")->execute([$qty, intval($item_id)]);
                }
            }
            $item_count = $pdo->query("SELECT COUNT(*) FROM requisition_items WHERE requisition_id = $requisition_id")->fetchColumn();
            if ($item_count == 0) {
                $pdo->prepare("DELETE FROM requisitions WHERE id = ?")->execute([$requisition_id]);
            }
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
        }
    }
    header("Location: requisitions.php"); exit;
}

// --- 3. HANDLE CLEARING AN ACTIVE REQUEST SHEET ---
if (isset($_POST["clear_request_id"])) {
    $pdo->prepare("UPDATE requisitions SET status = 'Cleared' WHERE id = ?")->execute([$_POST["clear_request_id"]]);
    header("Location: requisitions.php"); exit;
}

include "includes/header.php";

$categories = $pdo->query("SELECT * FROM material_categories ORDER BY sort_order ASC")->fetchAll();
$all_materials = $pdo->query("SELECT * FROM materials ORDER BY name ASC")->fetchAll();
$pending_sheets = $pdo->query("SELECT id, requested_at FROM requisitions WHERE status = 'Pending' ORDER BY id DESC")->fetchAll();
?>

<div id="editRequisitionModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 99999; justify-content: center; align-items: center; backdrop-filter: blur(4px);">
    <div class="modal-content" style="background: white; max-width: 500px; width: 90%; border-radius: var(--radius); padding: 24px; position: relative; box-shadow: 0 10px 25px rgba(0,0,0,0.15);">
        <span class="close-modal" style="position: absolute; top: 12px; right: 16px; font-size: 22px; cursor: pointer; color: var(--text-muted);" onclick="closeEditModal()">✕</span>
        <h3 style="font-size: 15px; font-weight: 700; text-transform: uppercase; margin-bottom: 4px; color: var(--text-main); text-align: left;">Modify Requisition Sheet</h3>
        <p id="modalTimestampLabel" style="font-size: 11px; color: var(--text-muted); margin-bottom: 15px; text-align: left;"></p>
        
        <form method="POST" action="requisitions.php" style="margin: 0;">
            <input type="hidden" name="update_requisition_items" value="1">
            <input type="hidden" name="edit_requisition_id" id="modalRequisitionId">
            <div id="modalItemsContainer" style="max-height: 40vh; overflow-y: auto; margin-bottom: 20px;"></div>
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn btn-log" style="padding: 10px 20px;" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="btn btn-start" style="padding: 10px 20px;">Save Updates</button>
            </div>
        </form>
    </div>
</div>

<div class="nav-bar">
    <a href="javascript:void(0)" class="nav-link active" onclick="scrollToSection('all', this)">All Categories</a>
    <?php foreach($categories as $cat): ?>
        <a href="javascript:void(0)" class="nav-link" onclick="scrollToSection('m_cat_<?= $cat['id'] ?>', this)">
            <?= htmlspecialchars($cat['name']) ?>
        </a>
    <?php endforeach; ?>
</div>

<div class="app-body" style="padding-bottom: 120px; max-width: 100% !important; width: 100% !important; display: block !important;">
    <div style="margin-bottom: 15px; max-width: 400px;">
        <input type="text" id="reqSearch" placeholder="Search material name..." style="margin: 0;" oninput="searchMaterials()">
    </div>

    <div class="requisitions-split-container">
        <div class="materials-catalog-side">
            <div id="materialsGroupCatalog">
                <?php foreach($categories as $cat): 
                    $cat_items = array_filter($all_materials, function($m) use ($cat) { return $m['category_id'] == $cat['id']; });
                    if(empty($cat_items)) continue;
                ?>
                    <div class="category-section" id="m_cat_<?= $cat['id'] ?>" style="scroll-margin-top: 140px;">
                        <h3 class="category-title"><?= htmlspecialchars($cat['name']) ?></h3>
                        <div class="grid">
                            <?php foreach($cat_items as $item): ?>
                               <div class="card material-row-node" data-name="<?= strtolower(htmlspecialchars($item['name'])) ?>">
                                    <div class="card-name" style="height: auto; margin-bottom: 12px; font-weight: 600; font-size: 13px;">
                                        <?= htmlspecialchars($item['name']) ?>
                                    </div>
                                    <div class="qty-controls">
                                        <button type="button" class="qty-btn" onclick="modifyReqQty(<?= $item['id'] ?>, '<?= htmlspecialchars(addslashes($item['name'])) ?>', -1)">-</button>
                                        <span id="row_qty_<?= $item['id'] ?>" class="qty-val">0</span>
                                        <button type="button" class="qty-btn" onclick="modifyReqQty(<?= $item['id'] ?>, '<?= htmlspecialchars(addslashes($item['name'])) ?>', 1)">+</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="requisitions-sidebar-side">
            <div class="sidebar-sticky-box">
                <h3 style="padding-bottom: 12px; border-bottom: 1px dashed #e2e8f0; margin-bottom: 12px; font-size: 14px; font-weight: 700; text-transform: uppercase; color: var(--text-main); text-align: left;">Active Selection</h3>
                
                <div id="liveCartPreview" style="font-size: 12px; text-align: left; overflow-y: auto; flex: 1; min-height: 80px; margin-bottom: 10px; color: var(--text-muted);">
                    No active materials chosen.
                </div>

                <button type="button" class="btn btn-start" style="width: 100%; padding: 12px; font-size: 12px; font-weight: bold; margin-bottom: 20px; border-radius: 8px;" onclick="submitMaterialRequisition()">Send Material Request</button>

                <h3 style="padding-bottom: 8px; border-bottom: 1px dashed #e2e8f0; margin-bottom: 12px; font-size: 13px; font-weight: 700; text-transform: uppercase; color: var(--text-main); text-align: left;">Sent Requests Logs</h3>
                
                <div style="overflow-y: auto; max-height: 45vh; padding-right: 2px;">
                    <?php if(empty($pending_sheets)): ?>
                        <p style="color: var(--text-muted); font-size: 12px; text-align: center; margin-top: 15px;">No logged pending sheets.</p>
                    <?php else: ?>
                        <?php foreach($pending_sheets as $sheet): 
                            $sheet_items = $pdo->query("SELECT ri.id, rc.item_name, ri.quantity FROM requisition_items ri JOIN req_catalog rc ON ri.catalog_id = rc.id WHERE ri.requisition_id = " . intval($sheet["id"]))->fetchAll();
                            $timestamp = strtotime($sheet['requested_at']);
                            $formatted_time = date('d/m/y \a\t H:i', $timestamp);
                        ?>
                            <div style="background: var(--bg); border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px; margin-bottom: 10px; border-top: 3px solid var(--warning) !important;">
                                <div style="display: flex; justify-content: space-between; font-size: 11px; font-weight: bold; border-bottom: 1px dotted #cbd5e0; padding-bottom: 4px; margin-bottom: 6px;">
                                    <span>Sheet #<?= $sheet["id"] ?></span>
                                    <span style="color: var(--warning);">Pending</span>
                                </div>
                                <div style="display: flex; flex-direction: column; gap: 4px; margin-bottom: 8px;">
                                    <button type="button" class="btn btn-bill" style="width: 100%; padding: 6px 4px; font-size: 11px; border-radius: 6px; font-weight: bold;" onclick='openEditModal(<?= $sheet['id'] ?>, "<?= $formatted_time ?>", <?= json_encode($sheet_items) ?>)'>
                                        Requested on <?= $formatted_time ?> ✏️
                                    </button>
                                    <form method="POST" style="margin: 0;" onsubmit="return confirm('Clear this material request?');">
                                        <input type="hidden" name="clear_request_id" value="<?= $sheet["id"] ?>">
                                        <button type="submit" class="btn btn-end" style="width: 100%; padding: 5px; font-size: 10px; border-radius: 6px;">Mark Clear</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let reqCart = [];

function modifyReqQty(id, name, delta) {
    let existing = reqCart.find(x => x.id === id);
    if (existing) {
        existing.qty += delta;
        if (existing.qty <= 0) reqCart = reqCart.filter(x => x.id !== id);
    } else if (delta > 0) {
        reqCart.push({ id, name, qty: 1 });
    }
    updateReqCounters();
    renderLiveCart();
}

function updateReqCounters() {
    document.querySelectorAll(".material-row-node").forEach(node => {
        const span = node.querySelector(".qty-val");
        if (span) {
            const matchId = span.id.replace("row_qty_", "");
            let item = reqCart.find(x => x.id == matchId);
            span.innerText = item ? item.qty : 0;
        }
    });
}

function renderLiveCart() {
    const preview = document.getElementById("liveCartPreview");
    if (reqCart.length === 0) {
        preview.innerHTML = "No active materials chosen.";
        return;
    }
    
    let html = '<div style="display:flex; flex-direction:column; gap:6px;">';
    reqCart.forEach(item => {
        html += `<div style="font-weight:600; color:var(--text-main); font-size:12px; text-align:left;">📝 <strong>${item.qty}x</strong> ${item.name}</div>`;
    });
    html += '</div>';
    preview.innerHTML = html;
}

function submitMaterialRequisition() {
    if (reqCart.length === 0) return alert("Please select material quantities first.");
    
    if (confirm("Send this selection to the active requests pipeline?")) {
        let materialMap = {};
        reqCart.forEach(x => { materialMap[x.id] = x.qty; });

        fetch("requisitions.php", {
            method: "POST",
            headers: {"Content-Type": "application/json"},
            body: JSON.stringify({ submit_requisition: 1, materials: materialMap })
        }).then(res => res.json()).then(data => {
            if (data.success) {
                reqCart = [];
                window.location.reload();
            } else {
                alert("Submission failed: " + (data.error || "Check database alignment."));
            }
        }).catch(() => {
            window.location.reload();
        });
    }
}

function openEditModal(sheetId, timestamp, items) {
    document.getElementById("modalRequisitionId").value = sheetId;
    document.getElementById("modalTimestampLabel").innerText = "Requested on " + timestamp;
    
    const container = document.getElementById("modalItemsContainer");
    container.innerHTML = "";
    
    items.forEach(item => {
        container.innerHTML += `
            <div style="display: flex; align-items: center; justify-content: space-between; padding: 10px; border-bottom: 1px solid #edf2f7;" id="modal_row_${item.id}">
                <div style="font-size: 13px; font-weight: 600; color: var(--text-main); flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; text-align: left;">
                    ${item.item_name}
                </div>
                <div class="qty-controls" style="gap: 6px; display: flex; align-items: center; flex-shrink: 0;">
                    <button type="button" class="qty-btn" style="width:24px; height:24px; font-size:12px;" onclick="adjustModalQty(${item.id}, -1)">-</button>
                    <input type="number" name="edit_items[${item.id}]" id="modal_field_${item.id}" value="${item.quantity}" min="0" style="width: 40px; padding: 4px 2px; text-align: center; font-size: 13px; font-weight: 700; border: 1px solid #e2e8f0; background: var(--bg); border-radius: 50px;">
                    <button type="button" class="qty-btn" style="width:24px; height:24px; font-size:12px;" onclick="adjustModalQty(${item.id}, 1)">+</button>
                    <button type="button" style="background: none; border: none; color: var(--danger); font-size: 14px; cursor: pointer; margin-left: 6px; font-weight: bold;" onclick="removeModalRow(${item.id})">🗑️</button>
                </div>
            </div>
        `;
    });
    document.getElementById("editRequisitionModal").style.display = "flex";
}

function adjustModalQty(id, delta) {
    const el = document.getElementById("modal_field_" + id);
    if (!el) return;
    let v = (parseInt(el.value) || 0) + delta;
    if (v < 0) v = 0;
    el.value = v;
    if (v === 0) removeModalRow(id);
}

// Updates opacity style layout context to visually indicate immediate deletion confirmation
function removeModalRow(id) {
    const el = document.getElementById("modal_field_" + id);
    if (el) el.value = 0;
    const row = document.getElementById("modal_row_" + id);
    if (row) row.style.opacity = "0.4";
}

function closeEditModal() {
    document.getElementById("editRequisitionModal").style.display = "none";
}

function scrollToSection(id, tab) {
    document.querySelectorAll(".nav-bar .nav-link").forEach(t => t.classList.remove("active")); tab.classList.add("active");
    if(id === 'all') window.scrollTo({ top: 0, behavior: 'smooth' });
    else { const target = document.getElementById(id); if(target) target.scrollIntoView({ behavior: 'smooth' }); }
}

function searchMaterials() {
    let q = document.getElementById("reqSearch").value.toLowerCase();
    document.querySelectorAll(".material-row-node").forEach(node => {
        node.style.setProperty("display", node.getAttribute("data-name").includes(q) ? "block" : "none", "important");
    });
}
</script>

<style>
.requisitions-split-container {
    display: grid !important;
    grid-template-columns: 1fr 350px !important;
    gap: 24px !important;
    width: 100% !important;
    align-items: start !important;
}
.materials-catalog-side { flex: 1 !important; min-width: 0 !important; }
.requisitions-sidebar-side { width: 350px !important; min-width: 350px !important; flex-shrink: 0 !important; }
.sidebar-sticky-box {
    background: var(--card-bg) !important;
    border: 1px solid #e2e8f0 !important;
    border-radius: var(--radius) !important;
    padding: 20px 16px !important;
    display: flex !important;
    flex-direction: column !important;
    position: sticky !important;
    top: 70px !important;
    max-height: calc(100vh - 90px) !important;
}
input[type=number]::-webkit-inner-spin-button, 
input[type=number]::-webkit-outer-spin-button { 
    -webkit-appearance: none; 
    margin: 0; 
}
@media (max-width: 1023px) {
    .requisitions-split-container { grid-template-columns: 1fr !important; }
    .requisitions-sidebar-side { width: 100% !important; }
    .sidebar-sticky-box { position: static !important; max-height: none !important; }
}
</style>

<?php include "includes/footer.php"; ?>