<?php
// /home/apartment/artistsfarmjaipur.com/Order/menu_admin.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once "config/db.php";

if (!isset($_SESSION["role"]) || ($_SESSION["role"] !== "Admin" && $_SESSION["role"] !== "Super Admin")) {
    die("Access Denied: Administrative credentials required.");
}

// AUTOMATED IMAGE RESIZING AND CROPPING GD ENGINE (150x50)
function cropAndScaleMenuPhoto($source_file, $target_file, $max_w = 150, $max_h = 50) {
    list($width, $height, $type) = getimagesize($source_file);
    
    switch ($type) {
        case IMAGETYPE_GIF:  $src = imagecreatefromgif($source_file); break;
        case IMAGETYPE_JPEG: $src = imagecreatefromjpeg($source_file); break;
        case IMAGETYPE_PNG:  $src = imagecreatefrompng($source_file); break;
        default: return false;
    }
    
    if (!$src) return false;

    $target_ratio = $max_w / $max_h;
    $source_ratio = $width / $height;
    
    $src_x = 0;
    $src_y = 0;
    $src_w = $width;
    $src_h = $height;

    if ($source_ratio > $target_ratio) {
        $src_w = round($height * $target_ratio);
        $src_x = round(($width - $src_w) / 2);
    } else {
        $src_h = round($width / $target_ratio);
        $src_y = round(($height - $src_h) / 2);
    }

    $dst = imagecreatetruecolor($max_w, $max_h);

    if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_GIF) {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
    }

    imagecopyresampled($dst, $src, 0, 0, $src_x, $src_y, $max_w, $max_h, $src_w, $src_h);

    // FIXED: Corrected undefined variable pointer to match image type mappings smoothly
    switch ($type) {
        case IMAGETYPE_GIF:  imagegif($dst, $target_file); break;
        case IMAGETYPE_JPEG: imagejpeg($dst, $target_file, 90); break;
        case IMAGETYPE_PNG:  imagepng($dst, $target_file, 5); break;
    }

    imagedestroy($src);
    imagedestroy($dst);
    return true;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_menu_item"])) {
    $item_id = intval($_POST["edit_item_id"]);
    $final_image_path = trim($_POST["current_image_path"]);

    // FIXED: Resolved mismatched quote syntax parser crash bug on array element keys
    if (!empty($_FILES["edit_file_upload"]["name"]) && $_FILES["edit_file_upload"]["error"] === UPLOAD_ERR_OK) {
        $target_dir = "assets/images/";
        if (!is_dir($target_dir)) { mkdir($target_dir, 0755, true); }
        
        $file_ext = strtolower(pathinfo($_FILES["edit_file_upload"]["name"], PATHINFO_EXTENSION));
        $new_filename = "item_" . $item_id . "_" . time() . "." . $file_ext;
        $target_file = $target_dir . $new_filename;

        if (cropAndScaleMenuPhoto($_FILES["edit_file_upload"]["tmp_name"], $target_file)) {
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
    header("Location: menu_admin.php"); 
    exit;
}

$items = $pdo->query("SELECT mi.*, mc.name as category_name FROM menu_items mi JOIN menu_categories mc ON mi.category_id = mc.id ORDER BY mc.sort_order ASC, mi.name ASC")->fetchAll(PDO::FETCH_ASSOC);
$categories = $pdo->query("SELECT * FROM menu_categories ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
include "includes/header.php";
?>

<div class="app-body" style="padding: 20px; font-family: sans-serif; text-align: left;">
    <div style="border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; margin-bottom: 20px;">
        <h2 style="margin:0; color:#1e293b;">⚙️ Menu Management Dashboard</h2>
        <p style="color:var(--text-muted); margin: 5px 0 0 0; font-size:0.9rem;">Modify menu items, pricing, or upload pictures (Auto Resized to 150x50)</p>
    </div>

    <div class="card" style="padding: 1rem; background:#fff; border: 1px solid #cbd5e0; border-radius: 8px; overflow-x:auto;">
        <table class="responsive-table" style="width:100%; border-collapse:collapse; font-size:13px;">
            <thead>
                <tr style="background:#f8fafc; border-bottom:2px solid #cbd5e0; color:#475569; text-align:left;">
                    <th style="padding:12px; width:15%;">Image Preview</th>
                    <th style="padding:12px; width:20%;">Category</th>
                    <th style="padding:12px; width:30%;">Item Name</th>
                    <th style="padding:12px; width:15%;">Price (₹)</th>
                    <th style="padding:12px; width:20%;">Upload Image File</th>
                    <th style="padding:12px; text-align:center; width:10%;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($items as $item): 
                    $preview_img = !empty($item["image_path"]) ? $item["image_path"] : "assets/images/catalog/placeholder.png";
                ?>
                <tr style="border-bottom:1px solid #e2e8f0;">
                    <form method="POST" enctype="multipart/form-data" style="margin:0;">
                        <td style="padding:12px; text-align:center;">
                            <img src="<?= $preview_img ?>" alt="" style="width:150px; height:50px; object-fit:cover; border-radius:6px; background:#f8fafc; border:1px solid #e2e8f0; display:block; margin:0 auto;">
                        </td>
                        <td style="padding:12px; vertical-align:middle;">
                            <select name="edit_category_id" style="width:100%; padding:8px; border:1px solid #cbd5e0; border-radius:6px;">
                                <?php foreach($categories as $c): ?>
                                    <option value="<?= $c["id"] ?>" <?= $c["id"] == $item["category_id"] ? 'selected' : '' ?>><?= htmlspecialchars($c["name"]) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td style="padding:12px; vertical-align:middle;">
                            <input type="text" name="edit_name" value="<?= htmlspecialchars($item["name"]) ?>" style="width:100%; padding:8px; border:1px solid #cbd5e0; border-radius:6px; box-sizing:border-box; font-weight:bold;" required>
                        </td>
                        <td style="padding:12px; vertical-align:middle;">
                            <input type="number" name="edit_price" value="<?= intval($item["price"]) ?>" style="width:100%; padding:8px; border:1px solid #cbd5e0; border-radius:6px; box-sizing:border-box; font-weight:bold; color:#059669;" required>
                        </td>
                        <td style="padding:12px; vertical-align:middle;">
                            <input type="file" name="edit_file_upload" accept="image/*" style="font-size:11px; color:#64748b;">
                            <input type="hidden" name="current_image_path" value="<?= htmlspecialchars($item["image_path"] ?? '') ?>">
                        </td>
                        <td style="padding:12px; text-align:center; vertical-align:middle;">
                            <input type="hidden" name="edit_item_id" value="<?= $item["id"] ?>">
                            <button type="submit" name="update_menu_item" class="btn btn-start" style="padding:6px 16px; font-weight:700; border-radius:6px;">Save</button>
                        </td>
                    </form>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include "includes/footer.php"; ?>