<?php
// /home/apartment/artistsfarmjaipur.com/Order/menu_manager.php
require_once "config/db.php";
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== 'Super Admin') die("Access Denied.");

// --- HANDLE ADD NEW ITEM ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action_add_menu'])) {
    $title = trim($_POST['new_title']);
    $url = trim($_POST['new_url']);
    $icon = trim($_POST['new_icon']) ?: 'fa-solid fa-link'; // Default secure mapping
    
    if (!empty($title) && !empty($url)) {
        $stmt = $pdo->prepare("INSERT INTO sys_menu (title, url, icon, sort_order, parent_id) VALUES (?, ?, ?, 999, 0)");
        $stmt->execute([$title, $url, $icon]);
        
        $new_id = $pdo->lastInsertId();
        $pdo->prepare("INSERT IGNORE INTO sys_role_menu (role_name, menu_id) VALUES ('Super Admin', ?)")->execute([$new_id]);
        
        header("Location: menu_manager.php?msg=added");
        exit;
    }
}

// --- HANDLE DELETE ITEM ---
if (isset($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);
    $pdo->prepare("DELETE FROM sys_role_menu WHERE menu_id = ?")->execute([$del_id]);
    $pdo->prepare("DELETE FROM sys_menu WHERE id = ?")->execute([$del_id]);
    $pdo->prepare("UPDATE sys_menu SET parent_id = 0 WHERE parent_id = ?")->execute([$del_id]);
    
    header("Location: menu_manager.php?msg=deleted");
    exit;
}

// --- HANDLE AJAX SAVE (Hierarchy, Titles, URLs, Icons, Roles) ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['menu_data'])) {
    $menu_data = json_decode($_POST['menu_data'], true);
    $pdo->beginTransaction();
    
    $pdo->query("UPDATE sys_menu SET parent_id = 0, sort_order = 0");
    $pdo->query("DELETE FROM sys_role_menu WHERE role_name != 'Super Admin'"); 

    function saveMenuRecursive($items, $parentId = 0, $depth = 0) {
        global $pdo;
        $order = 0;
        foreach ($items as $item) {
            $order++;
            $id = intval($item['id']);
            $title = trim($item['title']);
            $url = trim($item['url']);
            $icon = trim($item['icon']) ?: 'fa-solid fa-link'; // Captures the dynamically picked icon class cleanly
            
            // FIXED: Added icon parameter synchronization directly to the structural compilation pipeline
            $stmt = $pdo->prepare("UPDATE sys_menu SET parent_id = ?, sort_order = ?, title = ?, url = ?, icon = ? WHERE id = ?");
            $stmt->execute([$parentId, $order, $title, $url, $icon, $id]);
            
            if (!empty($item['roles'])) {
                foreach ($item['roles'] as $role) {
                    $pdo->prepare("INSERT IGNORE INTO sys_role_menu (role_name, menu_id) VALUES (?, ?)")->execute([$role, $id]);
                }
            }
            
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

function renderInitialList($items, $parentId = 0) {
    global $role_map;
    echo '<ul' . ($parentId == 0 ? ' id="menu-builder" class="menu-builder-list"' : '') . '>';
    foreach ($items as $menu) {
        if ($menu['parent_id'] == $parentId) {
            echo '<li data-id="'.$menu['id'].'">';
            
            echo '<div class="menu-item-card">';
            echo '  <i class="fa-solid fa-grip-vertical drag-handle" title="Drag left/right to nest, up/down to reorder"></i>';
            
            // INTERACTIVE ICON PICKER TRIGGER BUTTON
            echo '  <button type="button" class="btn-picker-trigger" onclick="window.openIconSelectorModal(this)" title="Click to change icon dynamically">';
            echo '     <i class="'.htmlspecialchars($menu['icon']).' menu-item-icon"></i>';
            echo '     <input type="hidden" class="menu-icon-value" value="'.htmlspecialchars($menu['icon']).'">';
            echo '  </button>';
            
            // Editable Title
            echo '  <input type="text" class="field-input-full menu-title-input" value="'.htmlspecialchars($menu['title']).'" title="Edit Display Name">';
            
            // Editable URL Link
            echo '  <i class="fa-solid fa-link" style="color:#cbd5e0; font-size:12px; margin-left:8px;"></i>';
            echo '  <input type="text" class="field-input-full menu-url-input" value="'.htmlspecialchars($menu['url']).'" title="Edit Link URL" placeholder="e.g., page.php or #">';
            
            echo '  <div class="flex-1"></div>'; 
            
            // Roles
            echo '  <div class="role-checkboxes">';
            echo '    <label><input type="checkbox" class="role-chk" value="Staff" '.(in_array('Staff', $role_map[$menu['id']]??[]) ? 'checked' : '').'> Staff</label>';
            echo '    <label><input type="checkbox" class="role-chk" value="Chef" '.(in_array('Chef', $role_map[$menu['id']]??[]) ? 'checked' : '').'> Chef</label>';
            echo '  </div>';
            
            echo '  <a href="?delete_id='.$menu['id'].'" onclick="return confirm(\'Delete this menu item? Nested items will be moved to the root level.\')" class="btn-del" style="margin-left:12px;"><i class="fa-solid fa-trash"></i></a>';
            echo '</div>';
            
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
    .menu-builder-list ul { padding-left: 40px; margin-top: 6px; min-height: 30px; padding-bottom: 10px; border-left: 2px dashed #cbd5e0; list-style: none; }
    
    .menu-item-card { 
        display: flex; align-items: center; gap: 8px; 
        padding: 10px 14px; background: #ffffff; 
        border: 1px solid #e2e8f0; border-radius: 8px; 
        margin-bottom: 6px; box-shadow: 0 1px 2px rgba(0,0,0,0.03);
        transition: box-shadow 0.2s;
    }
    .menu-item-card:hover { box-shadow: 0 4px 6px rgba(0,0,0,0.05); border-color: #cbd5e0; }
    
    .drag-handle { cursor: grab; color: #94a3b8; font-size: 18px; padding: 0 8px; }
    .drag-handle:active { cursor: grabbing; color: #00b0ff; }
    
    /* STYLED INTERACTIVE INTERFACE SWITCH ELEMENT */
    .btn-picker-trigger {
        background: #f1f5f9; border: 1px solid #cbd5e0; 
        padding: 6px 10px; border-radius: 6px; cursor: pointer;
        display: inline-flex; align-items: center; justify-content: center;
        transition: all 0.2s ease-in-out; min-width: 44px; height: 34px;
    }
    .btn-picker-trigger:hover { background: #e2e8f0; border-color: #94a3b8; transform: scale(1.05); }
    .menu-item-icon { color: #475569; font-size: 15px; text-align: center; }
    
    .menu-title-input { max-width: 180px; margin: 0; font-weight: 700; color: #1e293b; border-color: transparent; background: #f8fafc; transition: border 0.2s; padding: 6px 10px; font-size: 13px; }
    .menu-url-input { max-width: 180px; margin: 0; font-family: monospace; font-size: 12px; color: #475569; border-color: transparent; background: #f8fafc; transition: border 0.2s; padding: 6px 10px; }
    .menu-title-input:focus, .menu-url-input:focus { border-color: #00b0ff; background: #fff; }
    
    .role-checkboxes { display: flex; gap: 12px; align-items: center; background: #f8fafc; padding: 6px 12px; border-radius: 6px; border: 1px solid #edf2f7; font-size: 12px; font-weight: 600; color: #475569; }
    .role-checkboxes input[type="checkbox"] { transform: scale(1.1); margin-right: 4px; cursor: pointer; }
    
    .placeholder { background: #f0fdf4; border: 2px dashed #10b981; border-radius: 8px; height: 50px; margin-bottom: 6px; }
    .manager-grid { display: grid; grid-template-columns: 1fr 340px; gap: 20px; align-items: start; margin-top: 15px; }
    @media (max-width: 1024px) { .manager-grid { grid-template-columns: 1fr; } }

    /* SYSTEM MODAL FONTAWESOME MATRIX DASHBOARD */
    .icon-modal-backdrop {
        position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
        background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px);
        z-index: 99999; display: none; align-items: center; justify-content: center;
    }
    .icon-modal-content {
        background: #ffffff; width: 90%; max-width: 500px; padding: 24px;
        border-radius: 16px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
        border: 1px solid #e2e8f0; text-align: left;
    }
    .icon-modal-grid {
        display: grid; grid-template-columns: repeat(5, 1fr); gap: 12px;
        margin-top: 15px; max-height: 280px; overflow-y: auto; padding: 4px;
    }
    .icon-select-card {
        background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;
        padding: 14px; text-align: center; cursor: pointer; font-size: 18px;
        color: #334155; transition: all 0.15s ease-in-out;
    }
    .icon-select-card:hover { background: #00b0ff; border-color: #00b0ff; color: #ffffff; transform: scale(1.1); }
</style>

<div class="app-body">
    <div class="page-header" style="margin-bottom: 15px; border-bottom: 1px dashed #cbd5e0; padding-bottom: 15px;">
        <h2 style="font-size: 20px; color: #0f172a; margin: 0 0 5px 0;">🛠 Interactive Menu Manager</h2>
        <p style="color: #64748b; font-size: 13px; margin: 0;">Drag the grip icon to nest/reorder. Click the icon grids to change them interactively without coding. Rename items or change URLs directly in the text boxes.</p>
    </div>

    <?php if(isset($_GET['msg'])): ?>
        <div class="billing-banner-success">✔ System sidebar structure updated successfully.</div>
    <?php endif; ?>

    <div class="manager-grid">
        <div style="background: transparent;">
            <?php renderInitialList($all_menus); ?>
            
            <div style="margin-top: 20px; padding: 15px; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); position: sticky; bottom: 10px; z-index: 10;">
                <button onclick="saveMenu()" class="btn btn-start" style="width: 100%; padding: 14px; font-size: 14px; border-radius: 8px;">💾 Save Menu Hierarchy & Permissions</button>
            </div>
        </div>

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
                    <label class="field-label-sm">Interactive Icon Selection</label>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <button type="button" class="btn-picker-trigger" id="newFormIconPickerBtn" onclick="window.openIconSelectorModal(this, true)" style="width: 55px; height: 42px;">
                            <i class="fa-solid fa-link" style="font-size: 18px;"></i>
                        </button>
                        <!-- Form hidden collector value element -->
                        <input type="hidden" name="new_icon" id="newFormIconHiddenValue" value="fa-solid fa-link">
                        <span style="font-size: 12px; color: #64748b; font-weight: bold;">Click square to change icon selection</span>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-bill" style="width: 100%; padding: 12px; border-radius: 8px;">Add to Sidebar</button>
            </form>
        </div>
    </div>
</div>

<!-- FLOATING INTEGRATED ICON PICKER MODAL LAYER -->
<div class="icon-modal-backdrop" id="systemIconModalBackdrop" onclick="window.closeIconSelectorModal(event)">
    <div class="icon-modal-content" onclick="event.stopPropagation()">
        <h3 style="margin-top: 0; font-size: 16px; color: #0f172a; border-bottom: 1px dashed #cbd5e0; padding-bottom: 10px;">🎨 Select Dashboard Icon</h3>
        
        <div class="icon-modal-grid">
            <!-- Hospitality POS optimized specific FontAwesome icon maps -->
            <div class="icon-select-card" data-icon="fa-solid fa-chart-line"><i class="fa-solid fa-chart-line"></i></div>
            <div class="icon-select-card" data-icon="fa-solid fa-chart-bar"><i class="fa-solid fa-chart-bar"></i></div>
            <div class="icon-select-card" data-icon="fa-solid fa-user"><i class="fa-solid fa-user"></i></div>
            <div class="icon-select-card" data-icon="fa-solid fa-users"><i class="fa-solid fa-users"></i></div>
            <div class="icon-select-card" data-icon="fa-solid fa-receipt"><i class="fa-solid fa-receipt"></i></div>
            <div class="icon-select-card" data-icon="fa-solid fa-utensils"><i class="fa-solid fa-utensils"></i></div>
            <div class="icon-select-card" data-icon="fa-solid fa-kitchen-set"><i class="fa-solid fa-kitchen-set"></i></div>
            <div class="icon-select-card" data-icon="fa-solid fa-boxes-stacked"><i class="fa-solid fa-boxes-stacked"></i></div>
            <div class="icon-select-card" data-icon="fa-solid fa-cart-shopping"><i class="fa-solid fa-cart-shopping"></i></div>
            <div class="icon-select-card" data-icon="fa-solid fa-wallet"><i class="fa-solid fa-wallet"></i></div>
            <div class="icon-select-card" data-icon="fa-solid fa-credit-card"><i class="fa-solid fa-credit-card"></i></div>
            <div class="icon-select-card" data-icon="fa-solid fa-clock-history"><i class="fa-solid fa-clock-history"></i></div>
            <div class="icon-select-card" data-icon="fa-solid fa-gears"><i class="fa-solid fa-gears"></i></div>
            <div class="icon-select-card" data-icon="fa-solid fa-shield-halved"><i class="fa-solid fa-shield-halved"></i></div>
            <div class="icon-select-card" data-icon="fa-solid fa-file-export"><i class="fa-solid fa-file-export"></i></div>
            <div class="icon-select-card" data-icon="fa-solid fa-bell"><i class="fa-solid fa-bell"></i></div>
            <div class="icon-select-card" data-icon="fa-solid fa-key"><i class="fa-solid fa-key"></i></div>
            <div class="icon-select-card" data-icon="fa-solid fa-envelope"><i class="fa-solid fa-envelope"></i></div>
            <div class="icon-select-card" data-icon="fa-solid fa-warehouse"><i class="fa-solid fa-warehouse"></i></div>
            <div class="icon-select-card" data-icon="fa-solid fa-link"><i class="fa-solid fa-link"></i></div>
        </div>
        
        <button type="button" class="btn" onclick="window.closeIconSelectorModal(null)" style="width: 100%; margin-top: 15px; padding: 10px; background:#64748b; color:white; border:none; border-radius:6px; font-weight:bold; cursor:pointer;">Cancel</button>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/nestedSortable/2.0.0/jquery.mjs.nestedSortable.min.js"></script>

<script>
let currentActiveTargetButtonNode = null;
let isTargetingCreationFormCard = false;

$(document).ready(function() {
    $('#menu-builder').nestedSortable({
        items: 'li',
        listType: 'ul',
        placeholder: 'placeholder',
        forcePlaceholderSize: true,
        handle: '.drag-handle',
        tolerance: 'pointer',
        toleranceElement: '> div',
        maxLevels: 3, 
        isTree: true,       
        tabSize: 30,         
        opacity: 0.8
    });

    // Handle selection event from modal grid options
    $('.icon-select-card').on('click', function() {
        const pickedIconClass = $(this).data('icon');
        
        if (isTargetingCreationFormCard) {
            // Target elements inside creation box
            $('#newFormIconPickerBtn find("i")').attr('class', pickedIconClass);
            $('#newFormIconHiddenValue').val(pickedIconClass);
            document.getElementById('newFormIconPickerBtn').innerHTML = `<i class="${pickedIconClass}" style="font-size: 18px;"></i>`;
        } else if (currentActiveTargetButtonNode) {
            // Target live active list node variables inside the dynamic hierarchy
            $(currentActiveTargetButtonNode).find('.menu-item-icon').attr('class', pickedIconClass + ' menu-item-icon');
            $(currentActiveTargetButtonNode).find('.menu-icon-value').val(pickedIconClass);
        }
        
        window.closeIconSelectorModal(null);
    });
});

window.openIconSelectorModal = function(triggerElement, isForCreationForm = false) {
    currentActiveTargetButtonNode = triggerElement;
    isTargetingCreationFormCard = isForCreationForm;
    $('#systemIconModalBackdrop').css('display', 'flex').hide().fadeIn(150);
};

window.closeIconSelectorModal = function(e) {
    if (e === null || e.target.id === 'systemIconModalBackdrop') {
        $('#systemIconModalBackdrop').fadeOut(100);
    }
};

function saveMenu() {
    function getHierarchy(ul) {
        var items = [];
        ul.children('li').each(function() {
            var li = $(this);
            var card = li.children('.menu-item-card');
            
            var item = {
                id: li.data('id'),
                title: card.find('.menu-title-input').val(), 
                url: card.find('.menu-url-input').val(),
                icon: card.find('.menu-icon-value').val(), // FIXED: Captures updated class mappings safely during traversal
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