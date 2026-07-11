<?php
// /home/apartment/artistsfarmjaipur.com/Order/menu_manager.php
require_once "config/db.php";
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== 'Super Admin') die("Access Denied.");

// --- HANDLE SAVE ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['menu_data'])) {
    $menu_data = json_decode($_POST['menu_data'], true);
    $pdo->beginTransaction();
    
    // Reset defaults before saving
    $pdo->query("UPDATE sys_menu SET parent_id = 0, sort_order = 0");
    $pdo->query("DELETE FROM sys_role_menu");

    function saveMenuRecursive($items, $parentId = 0, $depth = 0) {
        global $pdo;
        $order = 0;
        foreach ($items as $item) {
            $order++;
            $id = intval($item['id']);
            
            // Update structure
            $stmt = $pdo->prepare("UPDATE sys_menu SET parent_id = ?, sort_order = ? WHERE id = ?");
            $stmt->execute([$parentId, $order, $id]);
            
            // Save Roles if checked
            if (!empty($item['roles'])) {
                foreach ($item['roles'] as $role) {
                    $pdo->prepare("INSERT IGNORE INTO sys_role_menu (role_name, menu_id) VALUES (?, ?)")->execute([$role, $id]);
                }
            }
            
            // Recurse children
            if (!empty($item['children'])) {
                saveMenuRecursive($item['children'], $id, $depth + 1);
            }
        }
    }
    saveMenuRecursive($menu_data);
    $pdo->commit();
    echo json_encode(['status' => 'success']);
    exit;
}

$all_menus = $pdo->query("SELECT * FROM sys_menu ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
$permissions = $pdo->query("SELECT * FROM sys_role_menu")->fetchAll(PDO::FETCH_ASSOC);
$role_map = [];
foreach($permissions as $p) $role_map[$p['menu_id']][] = $p['role_name'];

// Recursive function to build the initial list
function renderInitialList($items, $parentId = 0) {
    global $role_map;
    echo '<ul' . ($parentId == 0 ? ' id="menu-builder" class="sortable"' : '') . '>';
    foreach ($items as $menu) {
        if ($menu['parent_id'] == $parentId) {
            echo '<li data-id="'.$menu['id'].'">';
            echo '<div>'.$menu['title'].' ';
            echo '<label><input type="checkbox" class="role-chk" value="Staff" '.(in_array('Staff', $role_map[$menu['id']]??[]) ? 'checked' : '').'> Staff</label> ';
            echo '<label><input type="checkbox" class="role-chk" value="Chef" '.(in_array('Chef', $role_map[$menu['id']]??[]) ? 'checked' : '').'> Chef</label>';
            echo '</div>';
            renderInitialList($items, $menu['id']); // Recursion
            echo '</li>';
        }
    }
    echo '</ul>';
}

include "includes/header.php";
?>
<link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
<style>
    .sortable { list-style: none; padding: 0; }
    .sortable li { margin: 5px 0; background: #fff; border: 1px solid #ccc; cursor: move; }
    .sortable li > div { padding: 10px; background: #f8f9fa; }
    .placeholder { background: #e0f7fa; border: 1px dashed #00b0ff; height: 40px; }
</style>

<div class="app-body">
    <h2>🛠 Menu Manager</h2>
    <p>Drag items to reorder. Nest items to create sub-menus.</p>
    
    <?php renderInitialList($all_menus); ?>
    
    <button onclick="saveMenu()" class="btn btn-start">Save Hierarchy & Permissions</button>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/nestedSortable/2.0.0/jquery.mjs.nestedSortable.min.js"></script>

<script>
$(document).ready(function() {
    $('#menu-builder').nestedSortable({
        items: 'li',
        listType: 'ul',
        placeholder: 'placeholder',
        forcePlaceholderSize: true,
        handle: 'div',
        tolerance: 'pointer',
        toleranceElement: '> div'
    });
});

function saveMenu() {
    // This function converts your sorted list into a JSON object
    var menuData = $('#menu-builder').nestedSortable('toArray', {
        startDepthCount: 0
    });

    // Helper to structure the flat array into a tree
    var tree = [];
    var lookup = {};
    
    // First pass: create lookup
    $('.sortable li').each(function() {
        var id = $(this).data('id');
        var roles = [];
        $(this).find('.role-chk:checked').each(function() {
            roles.push($(this).val());
        });
        lookup[id] = { id: id, roles: roles, children: [] };
    });

    // Second pass: build hierarchy
    $('#menu-builder li').each(function() {
        var id = $(this).data('id');
        var parentId = $(this).parent().closest('li').data('id') || 0;
        if(parentId === 0) {
            tree.push(lookup[id]);
        } else {
            lookup[parentId].children.push(lookup[id]);
        }
    });

    $.post('menu_manager.php', { menu_data: JSON.stringify(tree) }, function(response) {
        alert("Hierarchy and permissions updated!");
        location.reload();
    }, 'json');
}
</script>
<?php include "includes/footer.php"; ?>