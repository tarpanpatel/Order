<?php
// /home/apartment/artistsfarmjaipur.com/Order/materials_admin.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once "config/db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "Admin") {
    die("Access Denied: Administrative clearance required.");
}

// Handle Add/Edit catalog submissions with image uploads
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action_save_catalog_item"])) {
    $item_id     = isset($_POST["item_id"]) ? intval($_POST["item_id"]) : 0;
    $item_name   = trim($_POST["item_name"]);
    $category_id = intval($_POST["category_id"]);
    $unit_type   = trim($_POST["unit_type"]);
    $unit_label  = trim($_POST["unit_label"]);
    $unit_cost   = floatval($_POST["unit_cost"]);
    
    // Process image uploads if a file is provided
    $image_path = "";
    if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] === UPLOAD_ERR_OK) {
        $file_tmp   = $_FILES['item_image']['tmp_name'];
        $file_name  = time() . '_' . basename($_FILES['item_image']['name']);
        $target_dir = "assets/images/catalog/";
        
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true);
        }
        
        if (move_uploaded_file($file_tmp, $target_dir . $file_name)) {
            $image_path = $target_dir . $file_name;
        }
    }

    if ($item_id > 0) {
        if (!empty($image_path)) {
            $stmt = $pdo->prepare("UPDATE req_catalog SET item_name = ?, category_id = ?, unit_type = ?, unit_label = ?, unit_cost = ?, image_path = ?, is_verified = 1 WHERE id = ?");
            $stmt->execute([$item_name, $category_id, $unit_type, $unit_label, $unit_cost, $image_path, $item_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE req_catalog SET item_name = ?, category_id = ?, unit_type = ?, unit_label = ?, unit_cost = ?, is_verified = 1 WHERE id = ?");
            $stmt->execute([$item_name, $category_id, $unit_type, $unit_label, $unit_cost, $item_id]);
        }
    } else {
        $fallback_img = !empty($image_path) ? $image_path : "assets/images/catalog/placeholder.png";
        $stmt = $pdo->prepare("INSERT INTO req_catalog (item_name, category_id, unit_type, unit_label, unit_cost, image_path, is_verified) VALUES (?, ?, ?, ?, ?, ?, 1)");
        $stmt->execute([$item_name, $category_id, $unit_type, $unit_label, $unit_cost, $fallback_img]);
    }
    header("Location: materials_admin.php");
    exit;
}

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
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; gap:20px; flex-wrap:wrap;">
        <div style="display:flex; align-items:center; gap:20px; flex:1; min-width:300px;">
            <h2 style="margin:0; color:#1e293b; white-space:nowrap;">🛠️ Master Materials Admin</h2>
            <input type="text" id="adminCatalogSearch" onkeyup="filterAdminCatalogTable()" placeholder="🔍 Search product descriptions or variants instantly..." style="width:100%; max-width:400px; padding:10px 14px; border:1px solid #cbd5e0; border-radius:8px; font-size:13px; box-shadow:inset 0 1px 2px rgba(0,0,0,0.02);">
        </div>
        <button class="btn btn-start" style="padding: 10px 20px; font-size:13px; font-weight:700; border-radius:8px;" onclick="openAdminCatalogModal(0)">➕ Add New Material</button>
    </div>

    <div id="groupedTablesContainer">
        <?php foreach ($categories as $cat): 
            $catItems = array_filter($allItems, function($x) use ($cat) {
                return intval($x['category_id']) === intval($cat['id']);
            });
        ?>
            <div class="admin-category-block" id="admin_block_cat_<?= $cat['id'] ?>" style="margin-bottom: 35px;">
                <h3 style="font-size:14px; text-transform:uppercase; color:#4b5563; background:#edf2f7; padding:10px 14px; margin-bottom:10px; font-weight:700; border-radius:6px; letter-spacing:0.5px; border-left:4px solid #06b6d4;"><?= htmlspecialchars($cat['name']) ?></h3>
                
                <div style="background:#fff; border: 1px solid #cbd5e0; border-radius: 8px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,0.02);">
                    <table class="admin-data-table" style="width:100%; border-collapse:collapse; font-size:13px; text-align:left;">
                        <thead>
                            <tr style="background:#f8fafc; border-bottom: 2px solid #cbd5e0; color:#475569;">
                                <th style="padding:12px; width:70px;">Image</th>
                                <th style="padding:12px;">Material Item Name</th>
                                <th style="padding:12px;">Unit Strategy</th>
                                <th style="padding:12px;">Base Metric</th>
                                <th style="padding:12px;">Unit Cost</th>
                                <th style="padding:12px; text-align:center; width:120px;">Verification</th>
                                <th style="padding:12px; text-align:center; width:140px;">Actions Matrix</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($catItems)): foreach ($catItems as $item): 
                                $is_pending = ($item['is_verified'] == 0);
                                $img_src = !empty($item['image_path']) ? $item['image_path'] : 'assets/images/catalog/placeholder.png';
                            ?>
                                <tr class="item-row" data-search-name="<?= strtolower(htmlspecialchars($item['item_name'])) ?>" style="border-bottom: 1px solid #e2e8f0; <?= $is_pending ? 'background:#fffbeb;' : '' ?>">
                                    <td style="padding:8px 12px; text-align:center;">
                                        <img src="<?= $img_src ?>" alt="" style="width:36px; height:36px; object-fit:cover; border-radius:6px; background:#f8fafc; border:1px solid #e2e8f0;">
                                    </td>
                                    <td class="item-name-cell" style="padding:12px; font-weight:bold; color:#1e293b;"><?= htmlspecialchars($item['item_name']) ?></td>
                                    <td style="padding:12px;"><span style="padding:2px 6px; background:#f1f5f9; border-radius:4px; font-size:11px; font-weight:600; color:#475569;"><?= $item['unit_type'] ?></span></td>
                                    <td style="padding:12px; font-weight:600; color:#334155;"><?= htmlspecialchars($item['unit_label']) ?></td>
                                    <td style="padding:12px; font-weight:700; color:#0284c7;">₹<?= number_format($item['unit_cost'], 2) ?></td>
                                    <td style="padding:12px; text-align:center;">
                                        <?php if ($is_pending): ?>
                                            <span style="padding:2px 8px; font-size:11px; background:#fef3c7; color:#d97706; font-weight:700; border-radius:12px;">Review Needed</span>
                                        <?php else: ?>
                                            <span style="padding:2px 8px; font-size:11px; background:#d1fae5; color:#059669; font-weight:700; border-radius:12px;">Verified Active</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:12px; text-align:center;">
                                        <div style="display:flex; gap:6px; justify-content:center;">
                                            <button type="button" class="btn btn-bill" style="padding:5px 12px; font-size:11px; background:#475569; border-radius:4px;" onclick="openAdminCatalogModal(<?= htmlspecialchars(json_encode($item)) ?>)">✏ Edit</button>
                                            <?php if ($is_pending): ?>
                                                <a href="materials_admin.php?approve_id=<?= $item['id'] ?>" class="btn btn-start" style="padding:5px 12px; font-size:11px; text-decoration:none; border-radius:4px;">✔ Verify</a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr class="empty-placeholder-row"><td colspan="7" style="text-align: center; color: #a0aec0; padding: 20px; font-style:italic;">No registered products found in this category group.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div id="adminCatalogModal" class="modal">
    <div class="modal-content" style="background: white; max-width: 480px; width: 92%; border-radius: 12px; padding: 25px; color: #111827;">
        <span style="float:right; cursor:pointer; font-size:22px; color:#a0aec0; font-weight:bold;" onclick="closeAdminCatalogModal()">✕</span>
        <h3 id="modalFormTitle" style="margin-top:0; margin-bottom:20px; border-bottom:1px dashed #cbd5e0; padding-bottom:8px; text-transform:uppercase; font-size:14px; color:#0f172a;">Add Catalog Item</h3>
        
        <form method="POST" action="materials_admin.php" enctype="multipart/form-data" style="margin:0;">
            <input type="hidden" name="action_save_catalog_item" value="1">
            <input type="hidden" name="item_id" id="formItemId" value="0">
            
            <div style="margin-bottom:12px;">
                <label style="font-size:11px; font-weight:700; color:#475569; display:block; margin-bottom:4px;">Material Description Name</label>
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
                    <label style="font-size:11px; font-weight:700; color:#475569; display:block; margin-bottom:4px;">Base Unit Metric Option</label>
                    <select name="unit_label" id="formUnitLabel" style="width:100%; padding:8px; border:1px solid #cbd5e0; border-radius:6px; font-size:13px;"></select>
                </div>
            </div>

            <div style="margin-bottom:12px;">
                <label style="font-size:11px; font-weight:700; color:#475569; display:block; margin-bottom:4px;">Baseline Unit Purchasing Cost (₹)</label>
                <input type="number" name="unit_cost" id="formUnitCost" required step="0.01" min="0" style="width:100%; padding:8px; border:1px solid #cbd5e0; border-radius:6px; font-size:13px;">
            </div>

            <div style="margin-bottom:20px;">
                <label style="font-size:11px; font-weight:700; color:#475569; display:block; margin-bottom:4px;">Product Reference Image</label>
                <input type="file" name="item_image" accept="image/*" style="width:100%; font-size:12px; color:#64748b;">
            </div>

            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <button type="button" class="btn btn-log" style="padding:10px 18px; border-radius:6px;" onclick="closeAdminCatalogModal()">Cancel</button>
                <button type="submit" class="btn btn-start" style="padding:10px 24px; font-weight:800; border-radius:6px;">Save Parameters</button>
            </div>
        </form>
    </div>
</div>

<script>
function filterAdminCatalogTable() {
    const input = document.getElementById("adminCatalogSearch").value.toLowerCase().trim();
    const rows = document.querySelectorAll(".item-row");
    
    rows.forEach(row => {
        const name = row.getAttribute("data-search-name") || "";
        if (name.includes(input)) {
            row.style.display = "";
        } else {
            row.style.display = "none";
        }
    });

    // Toggle category headers if all rows inside them are hidden
    document.querySelectorAll(".admin-category-block").forEach(block => {
        const totalRows = block.querySelectorAll(".item-row").length;
        const hiddenRows = block.querySelectorAll(".item-row[style='display: none;']").length;
        
        if (totalRows > 0 && totalRows === hiddenRows) {
            block.style.display = "none";
        } else {
            block.style.display = "";
        }
    });
}

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