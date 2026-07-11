<?php
// /home/apartment/artistsfarmjaipur.com/Order/menu_manager.php
require_once "config/db.php";
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== 'Super Admin') die("Access Denied.");

// --- HANDLE SAVE ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['menu_data'])) {
    $menu_data = json_decode($_POST['menu_data'], true);
    $pdo->beginTransaction();
    
    // Clear current hierarchy/roles
    $pdo->query("UPDATE sys_menu SET parent_id = 0, sort_order = 0");
    $pdo->query("DELETE FROM sys_role_menu");

    function saveMenu($items, $parentId = 0, $order = 0) {
        global $pdo;
        foreach ($items as $item) {
            $order++;
            $stmt = $pdo->prepare("UPDATE sys_menu SET parent_id = ?, sort_order = ? WHERE id = ?");
            $stmt->execute([$parentId, $order, $item['id']]);
            
            // Save Roles
            if (isset($item['roles'])) {
                foreach ($item['roles'] as $role) {
                    $pdo->prepare("INSERT INTO sys_role_menu (role_name, menu_id) VALUES (?, ?)")->execute([$role, $item['id']]);
                }
            }
            
            if (isset($item['children'])) {
                saveMenu($item['children'], $item['id'], $order * 100);
            }
        }
    }
    saveMenu($menu_data);
    $pdo->commit();
    echo json_encode(['status' => 'success']);
    exit;
}

$all_menus = $pdo->query("SELECT * FROM sys_menu ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
$permissions = $pdo->query("SELECT * FROM sys_role_menu")->fetchAll(PDO::FETCH_ASSOC);
$role_map = [];
foreach($permissions as $p) $role_map[$p['menu_id']][] = $p['role_name'];

include "includes/header.php";
?>
<link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
<style>
    .sortable { list-style: none; padding: 0; }
    .sortable li { margin: 5px 0; padding: 10px; background: #fff; border: 1px solid #ccc; cursor: move; }
    .placeholder { background: #e0f7fa; border: 1px dashed #00b0ff; height: 40px; }
</style>

<div class="app-body">
    <h2>🛠 Menu Manager</h2>
    <p>Drag and drop items to set hierarchy. Nest items to create sub-menus.</p>
    
    <ul id="menu-builder" class="sortable">
        <?php foreach ($all_menus as $menu): if($menu['parent_id'] == 0): ?>
            <li data-id="<?= $menu['id'] ?>">
                <?= $menu['title'] ?>
                <label><input type="checkbox" class="role-chk" value="Staff" <?= in_array('Staff', $role_map[$menu['id']]??[]) ? 'checked' : '' ?>> Staff</label>
                <label><input type="checkbox" class="role-chk" value="Chef" <?= in_array('Chef', $role_map[$menu['id']]??[]) ? 'checked' : '' ?>> Chef</label>
            </li>
        <?php endif; endforeach; ?>
    </ul>
    <button onclick="saveMenu()" class="btn btn-start">Save Hierarchy & Permissions</button>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/nestedSortable/2.0.0/jquery.mjs.nestedSortable.min.js"></script>
<script>
    $('#menu-builder').nestedSortable({
        listType: 'ul', // Explicitly define the list type
        items: 'li',
        placeholder: 'placeholder',
        forcePlaceholderSize: true,
        handle: 'div', // Optional: Use a specific handle (e.g., a drag icon)
        helper: 'clone',
        opacity: 0.6,
        revert: 250,
        tabSize: 25,
        toleranceElement: '> div'
    });
</script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/nestedSortable/2.0.0/jquery.mjs.nestedSortable.min.js"></script>
<?php include "includes/footer.php"; ?>