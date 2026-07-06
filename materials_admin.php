<?php
// /home/apartment/artistsfarmjaipur.com/Order/materials_admin.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once "config/db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "Admin") {
    die("Access Denied: Super Admin / Admin clearance boundaries mandatory.");
}

// Handle Add/Edit catalog submissions
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action_save_catalog_item"])) {
    $item_id     = isset($_POST["item_id"]) ? intval($_POST["item_id"]) : 0;
    $item_name   = trim($_POST["item_name"]);
    $category_id = intval($_POST["category_id"]);
    $unit_type   = trim($_POST["unit_type"]);
    $unit_label  = trim($_POST["unit_label"]);
    $unit_cost   = floatval($_POST["unit_cost"]);

    if ($item_id > 0) {
        // Update existing record and force verify it (is_verified = 1)
        $stmt = $pdo->prepare("UPDATE req_catalog SET item_name = ?, category_id = ?, unit_type = ?, unit_label = ?, unit_cost = ?, is_verified = 1 WHERE id = ?");
        $stmt->execute([$item_name, $category_id, $unit_type, $unit_label, $unit_cost, $item_id]);
    } else {
        // Insert a brand new record
        $stmt = $pdo->prepare("INSERT INTO req_catalog (item_name, category_id, unit_type, unit_label, unit_cost, is_verified) VALUES (?, ?, ?, ?, ?, 1)");
        $stmt->execute([$item_name, $category_id, $unit_type, $unit_label, $unit_cost]);
    }
    header("Location: materials_admin.php");
    exit;
}

// Handle item verification from Chef unlisted product pipeline
if (isset($_GET['approve_id'])) {
    $approve_id = intval($_GET['approve_id']);
    $pdo->prepare("UPDATE req_catalog SET is_verified = 1 WHERE id = ?")->execute([$approve_id]);
    header("Location: materials_admin.php");
    exit;
}

$categories = $pdo->query("SELECT * FROM material_categories ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
$allItems   = $pdo->query("SELECT rc.*, mc.name as category_name FROM req_catalog rc JOIN material_categories mc ON rc.category_id = mc.id ORDER BY rc.is_verified ASC, rc.item_name ASC")->fetchAll(PDO::FETCH_ASSOC);

include "includes/header.php";
?>

<div class="app-body" style="padding: 20px; font-family: sans-serif; text-align: left;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px;">
        <h2 style="margin:0; color:#1e293b;">🛠️ Master Materials Catalog Management (Admin)</h2>
        <button class="btn btn-start" style="padding: 8px 16px; font-size:13px;" onclick="openAdminCatalogModal(0)">➕ Add New Material</button>
    </div>

    <div style="background:#fff; border: 1px solid #cbd5e0; border-radius: 8px; overflow:hidden;">
        <table style="width:100%; border-collapse:collapse; font-size:13px; text-align:left;">
            <thead>
                <tr style="background:#f8fafc; border-bottom: 2px solid #cbd5e0;">
                    <th style="padding:12px;">Material Item Name</th>
                    <th style="padding:12px;">Category</th>
                    <th style="padding:12px;">Unit Type</th>
                    <th style="padding:12px;">Unit Metric</th>
                    <th style="padding:12px;">Unit Cost</th>
                    <th style="padding:12px; text-align:center;">Status Scope</th>
                    <th style="padding:12px; text-align:center;">Actions Matrix</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allItems as $item): 
                    $is_pending_review = ($item['is_verified'] == 0);
                ?>
                    <tr style="border-bottom: 1px solid #e2e8f0; <?= $is_pending_review ? 'background:#fffbeb;' : '' ?>">
                        <td style="padding:12px; font-weight:bold;"><?= htmlspecialchars($item['item_name']) ?></td>
                        <td style="padding:12px;"><?= htmlspecialchars($item['category_name']) ?></td>
                        <td style="padding:12px;"><span style="padding:2px 6px; background:#f1f5f9; border-radius:4px; font-size:11px;"><?= $item['unit_type'] ?></span></td>
                        <td style="padding:12px; font-weight:600;"><?= htmlspecialchars($item['unit_label']) ?></td>
                        <td style="padding:12px; font-weight:700; color:#0284c7;">₹<?= number_format($item['unit_cost'], 2) ?></td>
                        <td style="padding:12px; text-align:center;">
                            <?php if ($is_pending_review): ?>
                                <span style="padding:2px 8px; font-size:11px; background:#fef3c7; color:#d97706; font-weight:700; border-radius:12px;">Pending Review</span>
                            <?php else: ?>
                                <span style="padding:2px 8px; font-size:11px; background:#d1fae5; color:#059669; font-weight:700; border-radius:12px;">Verified Active</span>
                            <?php mapping: endif; ?>
                        </td>
                        <td style="padding:12px; text-align:center;">
                            <div style="display:flex; gap:6px; justify-content:center;">
                                <button class="btn btn-bill" style="padding:4px 10px; font-size:11px; background:#475569;" onclick="openAdminCatalogModal(<?= htmlspecialchars(json_encode($item)) ?>)">✏ Edit</button>
                                <?php if ($is_pending_review): ?>
                                    <a href="materials_admin.php?approve_id=<?= $item['id'] ?>" class="btn btn-start" style="padding:4px 10px; font-size:11px; text-decoration:none; display:inline-block;">✔ Verify</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="adminCatalogModal" class="modal">
    <div class="modal-content" style="background: white; max-width: 460px; width: 92%; border-radius: 12px; padding: 25px; color: #111827;">
        <span style="float:right; cursor:pointer; font-size:22px; color:#a0aec0;" onclick="closeAdminCatalogModal()">✕</span>
        <h3 id="modalFormTitle" style="margin-top:0; margin-bottom:20px; border-bottom:1px dashed #cbd5e0; padding-bottom:8px; text-transform:uppercase; font-size:14px;">Add Catalog Item</h3>
        
        <form method="POST" action="materials_admin.php" style="margin:0;">
            <input type="hidden" name="action_save_catalog_item" value="1">
            <input type="hidden" name="item_id" id="formItemId" value="0">
            
            <div style="margin-bottom:12px;">
                <label style="font-size:11px; font-weight:700; color:#475569; display:block; margin-bottom:4px;">Material Description / Name</label>
                <input type="text" name="item_name" id="formItemName" required style="width:100%; padding:8px; border:1px solid #cbd5e0; border-radius:6px; font-size:13px;">
            </div>
            
            <div style="margin-bottom:12px;">
                <label style="font-size:11px; font-weight:700; color:#475569; display:block; margin-bottom:4px;">Category Mapping Group</label>
                <select name="category_id" id="formCategoryId" required style="width:100%; padding:8px; border:1px solid #cbd5e0; border-radius:6px; font-size:13px;">
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="margin-bottom:12px; display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                <div>
                    <label style="font-size:11px; font-weight:700; color:#475569; display:block; margin-bottom:4px;">Unit Strategy Type</label>
                    <select name="unit_type" id="formUnitType" onchange="updateFormUnitLabelOptions()" style="width:100%; padding:8px; border:1px solid #cbd5e0; border-radius:6px; font-size:13px;">
                        <option value="Count">Count (Integers)</option>
                        <option value="Weight">Weight / Volume Matrix</option>
                    </select>
                </div>
                <div>
                    <label style="font-size:11px; font-weight:700; color:#475569; display:block; margin-bottom:4px;">Base Unit Metric</label>
                    <select name="unit_label" id="formUnitLabel" style="width:100%; padding:8px; border:1px solid #cbd5e0; border-radius:6px; font-size:13px;"></select>
                </div>
            </div>

            <div style="margin-bottom:20px;">
                <label style="font-size:11px; font-weight:700; color:#475569; display:block; margin-bottom:4px;">Baseline Unit Purchasing Cost (₹)</label>
                <input type="number" name="unit_cost" id="formUnitCost" required step="0.01" min="0" style="width:100%; padding:8px; border:1px solid #cbd5e0; border-radius:6px; font-size:13px;">
            </div>

            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <button type="button" class="btn btn-log" style="padding:8px 16px;" onclick="closeAdminCatalogModal()">Cancel</button>
                <button type="submit" class="btn btn-start" style="padding:8px 24px; font-weight:800;">Save Parameters</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAdminCatalogModal(data) {
    const title = document.getElementById("modalFormTitle");
    const itemId = document.getElementById("formItemId");
    const itemName = document.getElementById("formItemName");
    const catId = document.getElementById("formCategoryId");
    const uType = document.getElementById("formUnitType");
    const uCost = document.getElementById("formUnitCost");

    if (data === 0) {
        title.innerText = "Add Catalog Item";
        itemId.value = "0";
        itemName.value = "";
        uType.value = "Count";
        uCost.value = "0.00";
    } else {
        title.innerText = "Edit Catalog Item";
        itemId.value = data.id;
        itemName.value = data.item_name;
        catId.value = data.category_id;
        uType.value = data.unit_type;
        uCost.value = data.unit_cost;
    }

    updateFormUnitLabelOptions();
    if (data !== 0) {
        document.getElementById("formUnitLabel").value = data.unit_label;
    }

    document.getElementById("adminCatalogModal").style.display = "flex";
}

function updateFormUnitLabelOptions() {
    const type = document.getElementById("formUnitType").value;
    const label = document.getElementById("formUnitLabel");
    if (type === 'Weight') {
        label.innerHTML = `
            <option value="kg">kg</option>
            <option value="gms">gms</option>
            <option value="Ltr">Ltr</option>
            <option value="ml">ml</option>
        `;
    } else {
        label.innerHTML = `
            <option value="Pcs">Pcs</option>
            <option value="Packets">Packets</option>
            <option value="Boxes">Boxes</option>
        `;
    }
}

function closeAdminCatalogModal() {
    document.getElementById("adminCatalogModal").style.display = "none";
}
</script>

<?php include "includes/footer.php"; ?>