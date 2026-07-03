<?php
require_once "config/db.php"; 
// Hard check forcing entry block unless role matches Super Admin
requireSuperAdmin();
if (!isset($_SESSION["user_id"])) { header("Location: login.php"); exit; }

// Handle Adding a New Material
if (isset($_POST['add_material'])) {
    $name = trim($_POST['name']);
    $cat_id = intval($_POST['category_id']);
    if (!empty($name)) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO materials (name, category_id) VALUES (?, ?)");
        $stmt->execute([$name, $cat_id]);
    }
    header("Location: materials_admin.php"); exit;
}

// Handle Editing an Existing Material
if (isset($_POST['edit_material'])) {
    $id = intval($_POST['id']);
    $name = trim($_POST['name']);
    $cat_id = intval($_POST['category_id']);
    if (!empty($name)) {
        $stmt = $pdo->prepare("UPDATE materials SET name = ?, category_id = ? WHERE id = ?");
        $stmt->execute([$name, $cat_id, $id]);
    }
    header("Location: materials_admin.php"); exit;
}

// Handle Deleting a Material
if (isset($_GET['delete_id'])) {
    $stmt = $pdo->prepare("DELETE FROM materials WHERE id = ?");
    $stmt->execute([intval($_GET['delete_id'])]);
    header("Location: materials_admin.php"); exit;
}

$categories = $pdo->query("SELECT * FROM material_categories ORDER BY sort_order ASC")->fetchAll();
$materials = $pdo->query("SELECT m.*, mc.name as category_name FROM materials m JOIN material_categories mc ON m.category_id = mc.id ORDER BY mc.sort_order ASC, m.name ASC")->fetchAll();

include "includes/header.php";
?>

<div class="app-body">
    <div class="category-section">
        <h2 class="category-title" style="text-transform: none;">⚙️ Materials & Inventory Setup</h2>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px; align-items: start;">
        
        <div class="card">
            <h4 id="formTitle" style="font-size: 14px; font-weight: 700; margin-bottom: 12px; text-transform: uppercase;">Add New Material Element</h4>
            <form method="POST" id="materialActionForm">
                <input type="hidden" name="add_material" id="formActionKey" value="1">
                <input type="hidden" name="id" id="materialRecordId" value="">
                
                <label style="font-size: 12px; font-weight: 700; color: var(--text-muted);">Material Name</label>
                <input type="text" name="name" id="materialNameField" required placeholder="e.g., Capsicum Extra">
                
                <label style="font-size: 12px; font-weight: 700; color: var(--text-muted); display: block; margin-top: 10px;">Category Group</label>
                <select name="category_id" id="materialCategoryField" required>
                    <?php foreach($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                
                <div style="display: flex; gap: 8px; margin-top: 15px;">
                    <button type="submit" class="btn btn-start" style="padding: 12px;">Save Record</button>
                    <button type="button" id="cancelEditBtn" class="btn btn-log" style="display: none; padding: 12px;" onclick="resetAdminForm()">Cancel</button>
                </div>
            </form>
        </div>

        <div class="card" style="max-height: 75vh; overflow-y: auto;">
            <table class="bill-table" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Material Item</th>
                        <th>Assigned Category</th>
                        <th style="text-align: center; width: 120px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($materials as $m): ?>
                        <tr>
                            <td style="font-weight: 600; font-size: 13px;"><?= htmlspecialchars($m['name']) ?></td>
                            <td><span style="font-size: 11px; padding: 2px 8px; background: var(--primary-bg); color: var(--primary); font-weight: 700; border-radius: 12px;"><?= htmlspecialchars($m['category_name']) ?></span></td>
                            <td style="text-align: center;">
                                <button class="bill-mod-btn" onclick="populateEditData(<?= $m['id'] ?>, '<?= addslashes($m['name']) ?>', <?= $m['category_id'] ?>)">✏️</button>
                                <a href="materials_admin.php?delete_id=<?= $m['id'] ?>" class="bill-mod-btn" style="text-decoration: none; color: var(--danger);" onclick="return confirm('Delete item?');">🗑️</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function populateEditData(id, name, catId) {
    document.getElementById("formTitle").innerText = "Edit Selected Material";
    document.getElementById("formActionKey").name = "edit_material";
    document.getElementById("materialRecordId").value = id;
    document.getElementById("materialNameField").value = name;
    document.getElementById("materialCategoryField").value = catId;
    document.getElementById("cancelEditBtn").style.display = "inline-flex";
}

function resetAdminForm() {
    document.getElementById("formTitle").innerText = "Add New Material Element";
    document.getElementById("formActionKey").name = "add_material";
    document.getElementById("materialRecordId").value = "";
    document.getElementById("materialActionForm").reset();
    document.getElementById("cancelEditBtn").style.display = "none";
}
</script>
<?php include "includes/footer.php"; ?>