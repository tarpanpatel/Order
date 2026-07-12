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
            $icon = trim($item['icon']) ?: 'fa-solid fa-link';
            
            // FIXED: Variable name matching forced to $parentId to align parameters cleanly
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
            
            echo '  <button type="button" class="btn-picker-trigger" onclick="window.openIconSelectorModal(this)" title="Click to open full searchable picker">';
            echo '     <i class="'.htmlspecialchars($menu['icon']).' menu-item-icon"></i>';
            echo '     <input type="hidden" class="menu-icon-value" value="'.htmlspecialchars($menu['icon']).'">';
            echo '  </button>';
            
            echo '  <input type="text" class="field-input-full menu-title-input" value="'.htmlspecialchars($menu['title']).'" title="Edit Display Name">';
            
            echo '  <i class="fa-solid fa-link" style="color:#cbd5e0; font-size:12px; margin-left:8px;"></i>';
            echo '  <input type="text" class="field-input-full menu-url-input" value="'.htmlspecialchars($menu['url']).'" title="Edit Link URL" placeholder="e.g., page.php or #">';
            
            echo '  <div class="flex-1"></div>'; 
            
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

    /* SEARCHABLE MODAL DESIGN */
    .icon-modal-backdrop {
        position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
        background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px);
        z-index: 99999; display: none; align-items: center; justify-content: center;
    }
    .icon-modal-content {
        background: #ffffff; width: 95%; max-width: 550px; padding: 24px;
        border-radius: 16px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
        border: 1px solid #e2e8f0; text-align: left;
    }
    .icon-modal-grid {
        display: grid; grid-template-columns: repeat(6, 1fr); gap: 10px;
        margin-top: 15px; max-height: 320px; overflow-y: auto; padding: 4px;
    }
    .icon-select-card {
        background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;
        padding: 12px 6px; text-align: center; cursor: pointer; font-size: 18px;
        color: #334155; transition: all 0.15s ease-in-out; display: flex; flex-direction: column; align-items: center; gap: 4px;
    }
    .icon-select-card span { font-size: 9px; font-weight: 500; color: #64748b; text-overflow: ellipsis; overflow: hidden; width: 100%; white-space: nowrap; }
    .icon-select-card:hover { background: #00b0ff; border-color: #00b0ff; color: #ffffff; transform: scale(1.08); }
    .icon-select-card:hover span { color: #fff; }
    .icon-search-input { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e0; border-radius: 8px; font-size: 14px; outline: none; }
    .icon-search-input:focus { border-color: #00b0ff; box-shadow: 0 0 0 3px rgba(0,176,255,0.15); }
</style>

<div class="app-body">
    <div class="page-header" style="margin-bottom: 15px; border-bottom: 1px dashed #cbd5e0; padding-bottom: 15px;">
        <h2 style="font-size: 20px; color: #0f172a; margin: 0 0 5px 0;">🛠 Searchable Interactive Menu Manager</h2>
        <p style="color: #64748b; font-size: 13px; margin: 0;">Drag the grip icon to nest/reorder. Click any square icon button to search and choose from the complete core FontAwesome directory arrays instantly.</p>
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
                    <label class="field-label-sm">Interactive Icon Picker</label>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <button type="button" class="btn-picker-trigger" id="newFormIconPickerBtn" onclick="window.openIconSelectorModal(this, true)" style="width: 55px; height: 42px;">
                            <i class="fa-solid fa-link" style="font-size: 18px;"></i>
                        </button>
                        <input type="hidden" name="new_icon" id="newFormIconHiddenValue" value="fa-solid fa-link">
                        <span style="font-size: 12px; color: #64748b; font-weight: bold;">Click card box to search library...</span>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-bill" style="width: 100%; padding: 12px; border-radius: 8px;">Add to Sidebar</button>
            </form>
        </div>
    </div>
</div>

<!-- LMDX SEARCH ENGINE ICON COMPONENT -->
<div class="icon-modal-backdrop" id="systemIconModalBackdrop" onclick="window.closeIconSelectorModal(event)">
    <div class="icon-modal-content" onclick="event.stopPropagation()">
        <h3 style="margin-top: 0; font-size: 15px; color: #0f172a; margin-bottom: 12px;">🔍 Search Icon Directory</h3>
        <input type="text" id="iconLibrarySearchInput" class="icon-search-input" placeholder="Type keyword to filter icons (e.g., chart, user, table, food, secure, setting)..." onkeyup="window.filterIconLibraryMatrix(this.value)">
        
        <div class="icon-modal-grid" id="modalIconsGlobalMountGrid"></div>
        
        <button type="button" class="btn" onclick="window.closeIconSelectorModal(null)" style="width: 100%; margin-top: 15px; padding: 10px; background:#64748b; color:white; border:none; border-radius:6px; font-weight:bold; cursor:pointer;">Close Selector</button>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/nestedSortable/2.0.0/jquery.mjs.nestedSortable.min.js"></script>

<script>
let currentActiveTargetButtonNode = null;
let isTargetingCreationFormCard = false;

// COMPLETE SYSTEM LIBRARY INDEX ARRAY MATCHING STYLES COMPLIANCE
const COMPLETE_FONTAWESOME_MAP_INDEX = [
    // Analytics & Metrics
    "fa-chart-line", "fa-chart-bar", "fa-chart-pie", "fa-chart-area", "fa-chart-gantt", "fa-arrow-trend-up", "fa-arrow-trend-down", "fa-percent", "fa-gauge", "fa-gauge-high", "fa-signal", "fa-database",
    // Users & Identity
    "fa-user", "fa-users", "fa-user-tie", "fa-user-shield", "fa-user-gear", "fa-user-check", "fa-user-plus", "fa-user-minus", "fa-user-lock", "fa-id-card", "fa-address-book", "fa-circle-user",
    // Commerce & Financials
    "fa-receipt", "fa-wallet", "fa-credit-card", "fa-indian-rupee-sign", "fa-money-bill", "fa-money-bill-wave", "fa-money-check-dollar", "fa-cash-register", "fa-scale-balanced", "fa-calculator", "fa-piggy-bank", "fa-coins",
    // Food & Dining
    "fa-utensils", "fa-kitchen-set", "fa-bowl-food", "fa-pizza-slice", "fa-burger", "fa-mug-hot", "fa-wine-glass", "fa-cake-candles", "fa-ice-cream", "fa-apple-whole", "fa-egg", "fa-plate-wheat", "fa-stroopwafel",
    // Logistics, Sourcing & Infrastructure
    "fa-boxes-stacked", "fa-box", "fa-box-open", "fa-warehouse", "fa-truck", "fa-truck-loading", "fa-cart-shopping", "fa-cart-plus", "fa-store", "fa-shop", "fa-industry", "fa-dolly", "fa-barcode", "fa-qrcode",
    // Operations & Configurations
    "fa-gear", "fa-gears", "fa-sliders", "fa-wrench", "fa-screwdriver-wrench", "fa-hammer", "fa-nut-wrench", "fa-compass", "fa-circle-info", "fa-circle-question", "fa-circle-exclamation", "fa-triangle-exclamation",
    // Safety & Verification
    "fa-shield-halved", "fa-lock", "fa-lock-open", "fa-key", "fa-key-skeleton", "fa-passport", "fa-fingerprint", "fa-eye", "fa-eye-slash", "fa-vault", "fa-circle-check", "fa-signature",
    // Documentation & Files
    "fa-file-lines", "fa-file-invoice", "fa-file-invoice-dollar", "fa-file-export", "fa-file-import", "fa-file-csv", "fa-file-pdf", "fa-clipboard-list", "fa-clipboard-check", "fa-folder", "fa-folder-open", "fa-book",
    // Communication & Alert Systems
    "fa-envelope", "fa-message", "fa-comments", "fa-phone", "fa-bell", "fa-bell-slash", "fa-bullhorn", "fa-circle-nodes", "fa-share-nodes", "fa-link", "fa-at", "fa-wifi",
    // General Layouts
    "fa-house", "fa-hotel", "fa-bed", "fa-calendar", "fa-calendar-days", "fa-calendar-check", "fa-clock", "fa-hourglass-half", "fa-map-location-dot", "fa-location-dot", "fa-compass", "fa-list-check", "fa-bars-staggered"
];

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

    // Populate full dashboard grid on init execution
    buildInteractiveGridMatrix(COMPLETE_FONTAWESOME_MAP_INDEX);
});

function buildInteractiveGridMatrix(iconClassesArray) {
    const mountPoint = document.getElementById("modalIconsGlobalMountGrid");
    if (!mountPoint) return;

    let contentHtml = "";
    iconClassesArray.forEach(className => {
        const structuralCleanLabel = className.replace("fa-", "").replace("-", " ");
        contentHtml += `
            <div class="icon-select-card" data-icon="fa-solid ${className}" title="${className}">
                <i class="fa-solid ${className}"></i>
                <span>${structuralCleanLabel}</span>
            </div>
        `;
    });

    mountPoint.innerHTML = contentHtml;

    // Bind event hooks dynamically
    $('.icon-select-card').off('click').on('click', function() {
        const pickedIconClass = $(this).data('icon');
        
        if (isTargetingCreationFormCard) {
            $('#newFormIconHiddenValue').val(pickedIconClass);
            document.getElementById('newFormIconPickerBtn').innerHTML = `<i class="${pickedIconClass}" style="font-size: 18px;"></i>`;
        } else if (currentActiveTargetButtonNode) {
            $(currentActiveTargetButtonNode).find('.menu-item-icon').attr('class', pickedIconClass + ' menu-item-icon');
            $(currentActiveTargetButtonNode).find('.menu-icon-value').val(pickedIconClass);
        }
        
        window.closeIconSelectorModal(null);
    });
}

window.filterIconLibraryMatrix = function(queryStr) {
    const cleanQuery = queryStr.toLowerCase().trim();
    if (cleanQuery === "") {
        $('.icon-select-card').show();
        return;
    }

    $('.icon-select-card').each(function() {
        const iconAttrLabel = $(this).data('icon').toLowerCase();
        if (iconAttrLabel.includes(cleanQuery)) {
            $(this).show();
        } else {
            $(this).hide();
        }
    });
};

window.openIconSelectorModal = function(triggerElement, isForCreationForm = false) {
    currentActiveTargetButtonNode = triggerElement;
    isTargetingCreationFormCard = isForCreationForm;
    document.getElementById("iconLibrarySearchInput").value = "";
    $('.icon-select-card').show();
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
                icon: card.find('.menu-icon-value').val(), 
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