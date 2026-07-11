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
        handle: 'div',        // Matches the wrapper inside your <li>
        tolerance: 'pointer',
        toleranceElement: '> div'
    });
});

// ROBUST SERIALIZER: Bypasses the plugin's crash-prone .toArray()
function saveMenu() {
    // Recursive function to build the tree object
    function getHierarchy(ul) {
        var items = [];
        ul.children('li').each(function() {
            var li = $(this);
            var item = {
                id: li.data('id'),
                // Collect checked roles
                roles: li.children('div').find('.role-chk:checked').map(function(){
                    return $(this).val();
                }).get(),
                children: []
            };
            
            // Check if this LI has a nested UL (children)
            var subUl = li.children('ul');
            if (subUl.length > 0) {
                item.children = getHierarchy(subUl);
            }
            items.push(item);
        });
        return items;
    }

    // Capture the structure
    var menuTree = getHierarchy($('#menu-builder'));

    // Send to server
    $.post('menu_manager.php', { menu_data: JSON.stringify(menuTree) }, function(response) {
        alert("Hierarchy and permissions saved successfully!");
        location.reload();
    }, 'json').fail(function() {
        alert("Error: Failed to save menu structure.");
    });
}
</script>
<?php include "includes/footer.php"; ?>