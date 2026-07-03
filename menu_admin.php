<?php
require_once "config/db.php";
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "Admin") { die("Access Denied."); }

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_menu_item"])) {
    $item_id = intval($_POST["edit_item_id"]);
    $final_image_path = trim($_POST["current_image_path"]);

    // Handle physical file binary upload if present
    if (!empty($_FILES["edit_file_upload"]["name"])) {
        $target_dir = "assets/images/";
        if (!is_dir($target_dir)) { mkdir($target_dir, 0755, true); }
        
        $file_ext = strtolower(pathinfo($_FILES["edit_file_upload"]["name"], PATHINFO_EXTENSION));
        $new_filename = "item_" . $item_id . "_" . time() . "." . $file_ext;
        $target_file = $target_dir . $new_filename;

        if (move_uploaded_file($_FILES["edit_file_upload"]["tmp_name"], $target_file)) {
            $final_image_path = $target_file;
        }
    }

    $stmt = $pdo->prepare("UPDATE menu_items SET name = ?, price = ?, category_id = ?, image_path = ? WHERE id = ?");
    $stmt->execute([
        trim($_POST["edit_name"]),
        floatval($_POST["edit_price"]),
        intval($_POST["edit_category_id"]),
        $final_image_path,
        $item_id
    ]);
    header("Location: menu_admin.php"); exit;
}

$items = $pdo->query("SELECT mi.*, mc.name as category_name FROM menu_items mi JOIN menu_categories mc ON mi.category_id = mc.id ORDER BY mc.sort_order ASC, mi.name ASC")->fetchAll();
$categories = $pdo->query("SELECT * FROM menu_categories ORDER BY sort_order ASC")->fetchAll();
include "includes/header.php";
?>

<h2>⚙️ Menu Management Dashboard</h2>
<p style="color:var(--text-muted); margin-bottom:1.5rem; font-size:0.9rem;">Modify menu items, pricing, or upload pictures</p>

<div class="card" style="padding: 1rem;">
    <table class="responsive-table" style="width:100%;">
        <thead>
            <tr>
                <th style="width:20%;">Category</th>
                <th style="width:30%;">Item Name</th>
                <th style="width:15%;">Price (₹)</th>
                <th style="width:25%;">Upload Image File</th>
                <th style="width:10%;">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($items as $item): ?>
            <tr>
                <form method="POST" enctype="multipart/form-data" style="margin:0;">
                    <td>
                        <select name="edit_category_id" style="margin:0; padding:0.5rem;">
                            <?php foreach($categories as $c): ?>
                                <option value="<?= $c["id"] ?>" <?= $c["id"] == $item["category_id"] ? 'selected' : '' ?>><?= htmlspecialchars($c["name"]) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <input type="text" name="edit_name" value="<?= htmlspecialchars($item["name"]) ?>" style="margin:0; padding:0.5rem;" required>
                    </td>
                    <td>
                        <input type="number" name="edit_price" value="<?= intval($item["price"]) ?>" style="margin:0; padding:0.5rem;" required>
                    </td>
                    <td>
                        <input type="file" name="edit_file_upload" accept="image/*" style="margin:0; padding:0.4rem; font-size:0.8rem;">
                        <input type="hidden" name="current_image_path" value="<?= htmlspecialchars($item["image_path"] ?? '') ?>">
                    </td>
                    <td>
                        <input type="hidden" name="edit_item_id" value="<?= $item["id"] ?>">
                        <button type="submit" name="update_menu_item" class="pos-action-btn" style="padding:0.5rem;">Save</button>
                    </td>
                </form>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php include "includes/footer.php"; ?>