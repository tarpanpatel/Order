<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "config/db.php"; 

// Restrict access explicitly to Admin or Super Admin roles
if (!isset($_SESSION["role"]) || ($_SESSION["role"] !== "Admin" && $_SESSION["role"] !== "Super Admin")) {
    header("Location: login.php");
    exit;
}

// --- IMAGE UPLOAD HELPER FUNCTION ---
function handleMaterialImageUpload() {
    if (isset($_FILES['material_image']) && $_FILES['material_image']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['material_image']['tmp_name'];
        $fileName = $_FILES['material_image']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        if (in_array($fileExtension, $allowedExtensions)) {
            $uploadFileDir = 'assets/img/materials/';
            if (!is_dir($uploadFileDir)) {
                mkdir($uploadFileDir, 0755, true);
            }
            
            $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
            $dest_path = $uploadFileDir . $newFileName;
            
            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                return $dest_path;
            }
        }
    }
    return null;
}

// --- HANDLE POST: ADD NEW MATERIAL ---
if (isset($_POST['add_material'])) {
    $name = trim($_POST['name']);
    $cat_id = intval($_POST['category_id']);
    $image_path = handleMaterialImageUpload();
    
    if (!empty($name)) {
        $stmt = $pdo->prepare("INSERT INTO req_catalog (item_name, category_id, image_path) VALUES (?, ?, ?)");
        $stmt->execute([$name, $cat_id, $image_path]);
    }
    header("Location: materials_admin.php"); 
    exit;
}

// --- HANDLE POST: EDIT MATERIAL PARAMETERS ---
if (isset($_POST['edit_material'])) {
    $id = intval($_POST['id']);
    $name = trim($_POST['name']);
    $cat_id = intval($_POST['category_id']);
    $new_image = handleMaterialImageUpload();
    
    if (!empty($name)) {
        if ($new_image) {
            $stmt = $pdo->prepare("UPDATE req_catalog SET item_name = ?, category_id = ?, image_path = ? WHERE id = ?");
            $stmt->execute([$name, $cat_id, $new_image, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE req_catalog SET item_name = ?, category_id = ? WHERE id = ?");
            $stmt->execute([$name, $cat_id, $id]);
        }
    }
    header("Location: materials_admin.php"); 
    exit;
}

// --- HANDLE POST: SYSTEM BULK CATEGORY UPDATE ENGINE ---
if (isset($_POST['action_bulk_reassign'])) {
    $selected_item_ids = $_POST['selected_materials'] ?? [];
    $target_category_id = intval($_POST['bulk_target_category_id']);
    
    if (!empty($selected_item_ids) && $target_category_id > 0) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE req_catalog SET category_id = ? WHERE id = ?");
            foreach ($selected_item_ids as $id) {
                $stmt->execute([$target_category_id, intval($id)]);
            }
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
        }
    }
    header("Location: materials_admin.php");
    exit;
}

// --- HANDLE GET: REMOVE ITEM FROM CATALOG ENTIRELY ---
if (isset($_GET['delete_id'])) {
    $stmt = $pdo->prepare("DELETE FROM req_catalog WHERE id = ?");
    $stmt->execute([intval($_GET['delete_id'])]);
    header("Location: materials_admin.php"); 
    exit;
}

// Fetch categories and map materials using an internal relational table query join
$categories = $pdo->query("SELECT * FROM material_categories ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
$materials  = $pdo->query("SELECT rc.id, rc.item_name as name, rc.category_id, rc.image_path, mc.name as category_name 
                           FROM req_catalog rc 
                           LEFT JOIN material_categories mc ON rc.category_id = mc.id 
                           ORDER BY rc.item_name ASC")->fetchAll(PDO::FETCH_ASSOC);

include "includes/header.php";
?>

<div class="app-body">
    <div class="category-section">
        <h2 class="category-title" style="text-transform: none;">⚙️ Materials & Inventory Catalog Setup</h2>
    </div>

    <div style="background: #f8fafc; border: 1px solid #cbd5e0; border-radius: 12px; padding: 15px; margin-bottom: 20px; text-align: left;">
        <form method="POST" action="materials_admin.php" id="bulkReassignForm" style="margin:0; display:flex; flex-wrap:wrap; align-items:center; gap:15px;">
            <input type="hidden" name="action_bulk_reassign" value="1">
            <span style="font-size: 13px; font-weight: 700; color: #111827;">🛠️ Bulk Action:</span>
            <div style="display: flex; align-items: center; gap: 8px;">
                <label style="font-size: 12px; color: #4b5563;">Move Selected to:</label>
                <select name="bulk_target_category_id" required style="padding: 8px; font-size: 12px; border-radius: 6px; border: 1px solid #cbd5e0; background:#fff;">
                    <option value="">-- Choose Target Category --</option>
                    <?php foreach($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-bill" style="padding: 10px 16px; font-size: 12px; font-weight: bold; border-radius: 6px; background: #06b6d4; border-color: #06b6d4; max-width: 200px;" onclick="return confirm('Bulk reassign selected items?');">Update Checked Items</button>
        </form>
    </div>

    <div style="display: grid; grid-template-columns: 340px 1fr; gap: 20px; align-items: start;">
        
        <div class="card" style="text-align: left;">
            <h4 id="formTitle" style="font-size: 14px; font-weight: 700; margin-bottom: 12px; text-transform: uppercase; color:#111827;">Add New Material Element</h4>
            <form method="POST" id="materialActionForm" enctype="multipart/form-data">
                <input type="hidden" name="add_material" id="formActionKey" value="1">
                <input type="hidden" name="id" id="materialRecordId" value="">
                
                <div style="margin-bottom: 12px;">
                    <label style="font-size: 12px; font-weight: 700; color: #4b5563; display:block; margin-bottom:4px;">Material Name</label>
                    <input type="text" name="name" id="materialNameField" required placeholder="e.g., Capsicum Extra" style="width:100%; padding:10px; border:1px solid #cbd5e0; border-radius:6px; box-sizing:border-box;">
                </div>

                <div style="margin-bottom: 12px;">
                    <label style="font-size: 12px; font-weight: 700; color: #4b5563; display: block; margin-bottom:4px;">Category Group Allocation</label>
                    <select name="category_id" id="materialCategoryField" required style="width:100%; padding:10px; border:1px solid #cbd5e0; border-radius:6px; background:#fff;">
                        <?php foreach($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="margin-bottom: 15px;">
                    <label style="font-size: 12px; font-weight: 700; color: #4b5563; display: block; margin-bottom:4px;">Material Display Thumbnail Image</label>
                    <input type="file" name="material_image" accept="image/*" style="font-size:12px; color:#4b5563;">
                    <div id="editPreviewContainer" style="margin-top: 8px; display: none;">
                        <span style="font-size:11px; color:#718096; display:block; margin-bottom:2px;">Current Active Thumbnail:</span>
                        <img id="editImagePreview" src="" style="width:50px; height:50px; object-fit:cover; border-radius:6px; border:1px solid #cbd5e0;">
                    </div>
                </div>
                
                <div style="display: flex; gap: 8px; margin-top: 20px;">
                    <button type="submit" class="btn btn-start" style="padding: 12px; font-weight:700; font-size:13px; width: 100%;">Save Parameters</button>
                    <button type="button" id="cancelEditBtn" class="btn btn-log" style="display: none; padding: 12px; font-weight:700; font-size:13px;" onclick="resetAdminForm()">Cancel</button>
                </div>
            </form>
        </div>

        <div class="card" style="max-height: 75vh; overflow-y: auto;">
            <table class="bill-table" style="width: 100%; text-align: left;">
                <thead>
                    <tr>
                        <th style="width: 40px; text-align:center;"><input type="checkbox" id="selectAllCheckboxMaster" onclick="toggleSelectAllInventoryRows(this)"></th>
                        <th style="width: 60px; text-align:center;">Media</th>
                        <th>Material Item Title</th>
                        <th style="width: 180px;">Assigned Category</th>
                        <th style="text-align: center; width: 100px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($materials)): foreach($materials as $m): 
                        $mediaDisplay = !empty($m['image_path']) ? 
                            '<img src="'.htmlspecialchars($m['image_path']).'" style="width:34px; height:34px; object-fit:cover; border-radius:6px; border:1px solid #edf2f7; display:inline-block; vertical-align:middle;">' : 
                            '<span style="font-size:1.2rem; display:inline-block; vertical-align:middle;">📦</span>';
                        $cat_display_name = !empty($m['category_name']) ? $m['category_name'] : 'Unassigned';
                    ?>
                        <tr>
                            <td style="text-align: center; vertical-align: middle;">
                                <input type="checkbox" name="selected_materials[]" value="<?= $m['id'] ?>" class="inventory-row-checkbox" onchange="syncCheckboxToFormState()">
                            </td>
                            <td style="text-align: center; vertical-align: middle; padding: 6px;"><?= $mediaDisplay ?></td>
                            <td style="font-weight: 600; font-size: 13px; color:#2d3748; vertical-align: middle;">
                                <?= htmlspecialchars($m['name']) ?>
                            </td>
                            <td style="vertical-align: middle;">
                                <span style="font-size: 11px; padding: 3px 8px; background: #f1f5f9; color: #475569; font-weight: 700; border-radius: 12px; border: 1px solid #e2e8f0; display: inline-block; max-width: 160px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    📁 <?= htmlspecialchars($cat_display_name) ?>
                                </span>
                            </td>
                            <td style="text-align: center; vertical-align: middle;">
                                <button class="bill-mod-btn" style="cursor:pointer;" onclick="populateEditData(<?= $m['id'] ?>, '<?= addslashes($m['name']) ?>', <?= intval($m['category_id']) ?>, '<?= !empty($m['image_path']) ? htmlspecialchars($m['image_path']) : '' ?>')">✏️</button>
                                <a href="materials_admin.php?delete_id=<?= $m['id'] ?>" class="bill-mod-btn" style="text-decoration: none; color: var(--danger); cursor:pointer;" onclick="return confirm('Drop this element?');">🗑️</a>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="5" style="text-align:center; padding:30px; color:#a0aec0; font-style:italic;">No custom catalog configurations exist.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Toggle select all checkboxes in the grid matrix array maps
function toggleSelectAllInventoryRows(masterCheckbox) {
    const checkboxes = document.querySelectorAll('.inventory-row-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = masterCheckbox.checked;
    });
    syncCheckboxToFormState();
}

// Intercept selected indexes to append them dynamically into our batch execution form payload
function syncCheckboxToFormState() {
    const bulkForm = document.getElementById('bulkReassignForm');
    
    // Wipe old programmatic clones to maintain form data integrity
    bulkForm.querySelectorAll('.cloned-payload-input').forEach(el => el.remove());
    
    // Clone currently checked boxes into the bulk form container
    const activeCheckboxes = document.querySelectorAll('.inventory-row-checkbox:checked');
    activeCheckboxes.forEach(cb => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'selected_materials[]';
        input.value = cb.value;
        input.className = 'cloned-payload-input';
        bulkForm.appendChild(input);
    });
}

function populateEditData(id, name, catId, imgPath) {
    document.getElementById("formTitle").innerText = "Edit Selected Material";
    document.getElementById("formActionKey").name = "edit_material";
    document.getElementById("materialRecordId").value = id;
    document.getElementById("materialNameField").value = name;
    document.getElementById("materialCategoryField").value = catId;
    
    const previewBox = document.getElementById("editPreviewContainer");
    if (imgPath && imgPath !== '') {
        document.getElementById("editImagePreview").src = imgPath;
        previewBox.style.display = "block";
    } else {
        previewBox.style.display = "none";
    }
    
    document.getElementById("cancelEditBtn").style.display = "inline-flex";
}

function resetAdminForm() {
    document.getElementById("formTitle").innerText = "Add New Material Element";
    document.getElementById("formActionKey").name = "add_material";
    document.getElementById("materialRecordId").value = "";
    document.getElementById("editPreviewContainer").style.display = "none";
    document.getElementById("materialActionForm").reset();
    document.getElementById("cancelEditBtn").style.display = "none";
}
</script>
<?php include "includes/footer.php"; ?>