<?php
// /home/apartment/artistsfarmjaipur.com/Order/menu_manager.php
require_once "config/db.php";
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== 'Super Admin') die("Access Denied.");

// --- HANDLE ADD NEW ITEM ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action_add_menu'])) {
    $title = trim($_POST['new_title']);
    $url = trim($_POST['new_url']);
    $icon = trim($_POST['new_icon']) ?: 'fa-solid fa-link';
    
    if (!empty($title) && !empty($url)) {
        $stmt = $pdo->prepare("INSERT INTO sys_menu (title, url, icon, sort_order, parent_id) VALUES (?, ?, ?, 999, 0)");
        $stmt->execute([$title, $url, $icon]);
        
        // Ensure Super Admin gets default access
        $new_id = $pdo->lastInsertId();
        $pdo->prepare("INSERT IGNORE INTO sys_role_menu (role_name, menu_id) VALUES ('Super Admin', ?)")->execute([$new_id]);
        
        header("Location: menu_manager.php?msg=added");
        exit;
    }
}

// --- HANDLE DELETE ITEM ---
if (isset($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);
    // Due to ON DELETE CASCADE on role table (or we do it manually)
    $pdo->prepare("DELETE FROM sys_role_menu WHERE menu_id = ?")->execute([$del_id]);
    $pdo->prepare("DELETE FROM sys_menu WHERE id = ?")->execute([$del_id]);
    // Also reset parent_id of children to 0 so they don't disappear
    $pdo->prepare("UPDATE sys_menu SET parent_id = 0 WHERE parent_id = ?")->execute([$del_id]);
    
    header("Location: menu_manager.php?msg=deleted");
    exit;
}

// --- HANDLE AJAX SAVE (Hierarchy, Titles, Roles) ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['menu_data'])) {
    $menu_data = json_decode($_POST['menu_data'], true);
    $pdo->beginTransaction();
    
    // Reset defaults before saving new structure
    $pdo->query("UPDATE sys_menu SET parent_id = 0, sort_order = 0");
    $pdo->query("DELETE FROM sys_role_menu WHERE role_name != 'Super Admin'"); // Keep Super Admin access intact

    function saveMenuRecursive($items, $parentId = 0, $depth = 0) {
        global $pdo;
        $order = 0;
        foreach ($items as $item) {
            $order++;
            $id = intval($item['id']);
            $title = trim($item['title']);
            
            // Update structure AND editable title
            $stmt = $pdo->prepare("UPDATE sys_menu SET parent_id = ?, sort_order = ?, title = ? WHERE id = ?");
            $stmt->execute([$parentId, $order, $title, $id]);
            
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

// Recursive function to build the visual list
function renderInitialList($items, $parentId = 0) {
    global $role_map;
    echo '<ul' . ($parentId == 0 ? ' id="menu-builder" class="menu-builder-list"' : '') . '>';
    foreach ($items as $menu) {
        if ($menu['parent_id'] == $parentId) {
            echo '<li data-id="'.$menu['id'].'">';
            
            // Item Card UI using existing framework classes
            echo '<div class="menu-item-card">';
            echo '  <i class="fa-solid fa-grip-vertical drag-handle" title="Drag to reorder"></i>';
            echo '  <i class="'.htmlspecialchars($menu['icon']).' menu-item-icon"></i>';
            
            // Editable Title Input
            echo '  <input type="text" class="field-input-full menu-title-input" value="'.htmlspecialchars($menu['title']).'" title="Edit Display Name">';
            
            // File URL display
            echo '  <span class="menu-url-text">'.htmlspecialchars($menu['url']).'</span>';
            echo '  <div class="flex-1"></div>'; // Spacer
            
            // Role Permissions Toggles
            echo '  <div class="role-checkboxes">';
            echo '    <label><input type="checkbox" class="role-chk" value="Staff" '.(in_array('Staff', $role_map[$menu['id']]??[]) ? 'checked' : '').'> Staff</label>';
            echo '    <label><input type="checkbox" class="role-chk" value="Chef" '.(in_array('Chef', $role_map[$menu['id']]??[]) ? 'checked' : '').'> Chef</label>';
            echo '  </div>';
            
            // Delete Action
            echo '  <a href="?delete_id='.$menu['id'].'" onclick="return confirm(\'Delete this menu item? Nested items will be moved to the root level.\')" class="btn-del" style="margin-left:12px;"><i class="fa-solid fa-trash"></i></a>';
            echo '</div>';
            
            // Recursion for nested children
            renderInitialList($items, $menu['id']); 
            echo '</li>';
        }
    }
    echo '</ul>';
}

include "includes/header.php";
?>
<link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
<style>
    .menu-builder-list { list-style: none; padding: 0; min-height: 50px; }
    .menu-builder-list ul { padding-left: 35px; margin-top: 6px; min-height: 20px; border-left: 2px dashed #cbd5e0; list-style: none; }
    
    .menu-item-card { 
        display: flex; align-items: center; gap: 12px; 
        padding: 10px 14px; background: #ffffff; 
        border: 1px solid #e2e8f0; border-radius: 8px; 
        margin-bottom: 6px; box-shadow: 0 1px 2px rgba(0,0,0,0.03);
        transition: box-shadow 0.2s;
    }
    .menu-item-card:hover { box-shadow: 0 4px 6px rgba(0,0,0,0.05); border-color: #cbd5e0; }
    
    .drag-handle { cursor: grab; color: #94a3b8; font-size: 18px; padding: 0 8px; }
    .drag-handle:active { cursor: grabbing; color: #00b0ff; }
    
    .menu-item-icon { color: #64748b; width: 24px; text-align: center; font-size: 16px; }
    .menu-title-input { max-width: 220px; margin: 0; font-weight: 700; color: #1e293b; border-color: transparent; background: #f8fafc; transition: border 0.2s; }
    .menu-title-input:focus { border-color: #00b0ff; background: #fff; }
    
    .menu-url-text { font-size: 11px; color: #94a3b8; font-family: monospace; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 150px; }
    
    .role-checkboxes { display: flex; gap: 12px; align-items: center; background: #f8fafc; padding: 6px 12px; border-radius: 6px; border: 1px solid #edf2f7; font-size: 12px; font-weight: 600; color: #475569; }
    .role-checkboxes input[type="checkbox"] { transform: scale(1.1); margin-right: 4px; cursor: pointer; }
    
    .placeholder { background: #f0fdf4; border: 2px dashed #10b981; border-radius: 8px; height: 50px; margin-bottom: 6px; }
    
    /* Layout split mirroring billing.php */
    .manager-grid { display: grid; grid-template-columns: 1fr 340px; gap: 20px; align-items: start; margin-top: 15px; }
    
    @media (max-width: 1024px) {
        .manager-grid { grid-template-columns: 1fr; }
    }
</style>

<div class="app-body">
    <div class="page-header" style="margin-bottom: 15px; border-bottom: 1px dashed #cbd5e0; padding-bottom: 15px;">
        <h2 style="font-size: 20px; color: #0f172a; margin: 0 0 5px 0;">🛠 Interactive Menu Manager</h2>
        <p style="color: #64748b; font-size: 13px; margin: 0;">Drag the grip icon to nest/reorder. Rename items directly in the text boxes. Check roles to grant access.</p>
    </div>

    <?php if(isset($_GET['msg'])): ?>
        <div class="billing-banner-success">✔ System sidebar structure updated successfully.</div>
    <?php endif; ?>

    <div class="manager-grid">
        <!-- LEFT: Drag & Drop Builder -->
        <div style="background: transparent;">
            <?php renderInitialList($all_menus); ?>
            
            <div style="margin-top: 20px; padding: 15px; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); position: sticky; bottom: 10px; z-index: 10;">
                <button onclick="saveMenu()" class="btn btn-start" style="width: 100%; padding: 14px; font-size: 14px; border-radius: 8px;">💾 Save Menu Hierarchy & Permissions</button>
            </div>
        </div>

        <!-- RIGHT: Add New Item Form -->
        <div class="billing-card" style="position: sticky; top: 75px;">
            <h3 style="font-size: 14px; font-weight: 700; text-transform: uppercase; color: #1e293b; border-bottom: 1px dashed #cbd5e0; padding-bottom: 8px; margin-top: 0; margin-bottom: 15px;">➕ Add New Link</h3>
            <form method="POST" action="menu_manager.php" style="margin: 0;">
                <input type="hidden" name="action_add_menu" value="1">
                
                <div class="mb-10">
                    <label class="field-label-sm">Display Title *</label>
                    <input type="text" name="new_title" required class="field-input-full" placeholder="e.g., Room Settings">
                </div>
                
                <div class="mb-10">
                    <label class="field-label-sm">Target URL / File Path *</label>
                    <input type="text" name="new_url" required class="field-input-full" placeholder="e.g., settings.php or #">
                    <span style="font-size: 10px; color: #94a3b8; margin-top: 4px; display: block;">Use "#" if this will only be a parent dropdown.</span>
                </div>
                
                <div class="mb-15">
                    <label class="field-label-sm">FontAwesome Icon Class</label>
                    <input type="text" name="new_icon" class="field-input-full" placeholder="fa-solid fa-gear" value="fa-solid fa-link">
                </div>
                
                <button type="submit" class="btn btn-bill" style="width: 100%; padding: 12px; border-radius: 8px;">Add to Sidebar</button>
            </form>
        </div>
    </div>
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
        handle: '.drag-handle', // RESTRICTS DRAG TO THE GRIP ICON ONLY
        tolerance: 'pointer',
        toleranceElement: '> div',
        maxLevels: 2, // Prevents nesting too deep for standard sidebars
        opacity: 0.8
    });
});

// ROBUST SERIALIZER
function saveMenu() {
    function getHierarchy(ul) {
        var items = [];
        ul.children('li').each(function() {
            var li = $(this);
            var card = li.children('.menu-item-card');
            
            var item = {
                id: li.data('id'),
                title: card.find('.menu-title-input').val(), // Captures edited names
                roles: card.find('.role-chk:checked').map(function(){
                    return $(this).val();
                }).get(),
                children: []
            };
            
            var subUl = li.children('ul');
            if (subUl.length > 0) {
                item.children = getHierarchy(subUl);
            }
            items.push(item);
        });
        return items;
    }

    var menuTree = getHierarchy($('#menu-builder'));

    // Loading state
    const saveBtn = document.querySelector('.btn-start');
    const originalText = saveBtn.innerText;
    saveBtn.innerText = "⏳ Saving...";
    saveBtn.style.opacity = "0.7";

    $.post('menu_manager.php', { menu_data: JSON.stringify(menuTree) }, function(response) {
        location.href = "menu_manager.php?msg=saved";
    }, 'json').fail(function() {
        alert("Error: Failed to save menu structure.");
        saveBtn.innerText = originalText;
        saveBtn.style.opacity = "1";
    });
}
</script>
<?php include "includes/footer.php"; ?>