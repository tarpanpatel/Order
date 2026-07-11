<?php
// /home/apartment/artistsfarmjaipur.com/Order/staff_management.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "config/db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== 'Super Admin') {
    die("Access Denied: Only Super Admins can manage system permissions.");
}

$msg = "";

// 🛠️ AUTOMATED DISCOVERY ENGINE: Scan directory for new pages on mount
$ignored_files = ['db.php', 'header.php', 'footer.php', 'left_menu.php', 'logout.php'];
$dir_path = __DIR__;
$discovered_php_files = [];

if (is_dir($dir_path)) {
    if ($dh = opendir($dir_path)) {
        while (($file = readdir($dh)) !== false) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'php' && !in_array($file, $ignored_files)) {
                $discovered_php_files[] = $file;
            }
        }
        closedir($dh);
    }
}

// ➕ ACTION: Register a newly discovered file into the sidebar
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action_create_menu'])) {
    $title = trim($_POST['menu_title']);
    $url = trim($_POST['menu_url']);
    $icon = trim($_POST['menu_icon'] ?: 'fa-solid fa-link');
    $order = intval($_POST['menu_order']);

    if (!empty($title) && !empty($url)) {
        $stmt = $pdo->prepare("INSERT INTO sys_menu (title, url, icon, sort_order) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE title=?, icon=?, sort_order=?");
        $stmt->execute([$title, $url, $icon, $order, $title, $icon, $order]);
        
        // Auto-assign permission to Super Admin immediately so it isn't hidden
        $menu_id = $pdo->query("SELECT id FROM sys_menu WHERE url = " . $pdo->quote($url))->fetchColumn();
        $pdo->prepare("INSERT IGNORE INTO sys_role_menu (role_name, menu_id) VALUES ('Super Admin', ?)")->execute([$menu_id]);
        
        $msg = "✔ Menu item '" . htmlspecialchars($title) . "' initialized and authorized successfully.";
    }
}

// ❌ ACTION: Remove an entry completely from the dynamic sidebar
if (isset($_GET['delete_menu_id'])) {
    $del_id = intval($_GET['delete_menu_id']);
    $pdo->prepare("DELETE FROM sys_menu WHERE id = ?")->execute([$del_id]);
    header("Location: staff_management.php?msg=deleted");
    exit;
}

// 🔐 ACTION: Commit structural role authorization matrices
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action_save_permissions'])) {
    $pdo->query("DELETE FROM sys_role_menu"); // Reset configurations cleanly
    
    $permissions = $_POST['perms'] ?? [];
    $stmt = $pdo->prepare("INSERT IGNORE INTO sys_role_menu (role_name, menu_id) VALUES (?, ?)");
    
    foreach ($permissions as $role => $menu_ids) {
        foreach ($menu_ids as $m_id) {
            $stmt->execute([$role, intval($m_id)]);
        }
    }
    // Explicitly guarantee Super Admin retains global overrides
    $pdo->query("INSERT IGNORE INTO sys_role_menu (role_name, menu_id) SELECT 'Super Admin', id FROM sys_menu");
    
    $msg = "✔ System access authorizations successfully updated.";
}

if (isset($_GET['msg']) && $_GET['msg'] === 'deleted') {
    $msg = "✔ Menu item successfully decoupled from system loops.";
}

// Data extraction mappings
$all_menus = $pdo->query("SELECT * FROM sys_menu ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
$roles_pool = ['Super Admin', 'Staff', 'Chef'];

// Build permission tracking dictionary
$allowed_matrix = [];
$raw_perms = $pdo->query("SELECT * FROM sys_role_menu")->fetchAll(PDO::FETCH_ASSOC);
foreach ($raw_perms as $rp) {
    $allowed_matrix[$rp['role_name']][] = $rp['menu_id'];
}

include "includes/header.php";
?>

<div class="app-body" style="padding: 20px; text-align: left; font-family: sans-serif;">
    <div class="page-header" style="margin-bottom: 25px; border-bottom: 1px dashed #cbd5e0; padding-bottom: 15px;">
        <h2 class="page-title">🛠️ Master Portal & Sidebar Permission Manager</h2>
        <p style="color: #64748b; font-size: 13px; margin-top: 4px;">Scan files, register new components, and control staff layout permissions completely dynamically.</p>
    </div>

    <?php if(!empty($msg)): ?>
        <div style="background:#f0fdf4; border:1px solid #bbf7d0; color:#166534; padding:12px; border-radius:8px; margin-bottom:20px; font-weight: 600; font-size:14px;"><?= $msg ?></div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px; align-items: start; margin-bottom: 30px;">
        
        <!-- PANEL A: DYNAMIC SERVER FOLDER SCANNER -->
        <div style="background:#fff; border:1px solid #e2e8f0; padding:20px; border-radius:12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <h3 style="font-size:14px; text-transform:uppercase; margin-bottom:15px; color:#0f172a; border-left:3px solid #00b0ff; padding-left:8px;">📁 Server File Auto-Discovery</h3>
            <p style="font-size:12px; color:#64748b; margin-bottom:15px;">The following files are sitting in your server folder. Select one to push it directly into your dashboard sidebar menu instantly:</p>
            
            <table style="width:100%; font-size:13px; border-collapse:collapse;">
                <thead>
                    <tr style="background:#f8fafc; text-align:left; font-weight:bold; border-bottom:1px solid #e2e8f0;">
                        <th style="padding:8px;">Detected Filename</th>
                        <th style="padding:8px; text-align:right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($discovered_php_files as $php_file): 
                        $already_mapped = false;
                        foreach ($all_menus as $am) { if ($am['url'] === $php_file) { $already_mapped = true; break; } }
                    ?>
                        <tr style="border-bottom:1px solid #edf2f7;">
                            <td style="padding:8px; font-family:monospace; font-weight:bold; color:#334155;"><?= htmlspecialchars($php_file) ?></td>
                            <td style="padding:8px; text-align:right;">
                                <?php if ($already_mapped): ?>
                                    <span style="color:#64748b; font-size:11px; font-weight:bold; background:#f1f5f9; padding:3px 8px; border-radius:4px;">Active in Sidebar</span>
                                <?php else: ?>
                                    <button type="button" onclick="populateCreationForm('<?= htmlspecialchars($php_file) ?>')" style="padding:4px 10px; background:#00b0ff; color:#fff; border:none; border-radius:4px; font-weight:bold; cursor:pointer; font-size:11px;">+ Configure Link</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- CONFIGURATION SETUP ENTRY SUB-FORM -->
            <div id="quickConfigBox" style="margin-top:20px; padding-top:20px; border-top:1px dashed #e2e8f0; display:none;">
                <strong style="font-size:13px; display:block; margin-bottom:10px; color:#0f172a;">Configure Selected Component:</strong>
                <form method="POST" action="staff_management.php" style="margin:0; display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                    <input type="hidden" name="action_create_menu" value="1">
                    <div>
                        <label style="font-size:11px; font-weight:bold; display:block; margin-bottom:4px;">File Path target</label>
                        <input type="text" name="menu_url" id="formMenuUrl" readonly style="width:100%; padding:8px; border:1px solid #cbd5e0; border-radius:6px; background:#f8fafc; font-family:monospace;">
                    </div>
                    <div>
                        <label style="font-size:11px; font-weight:bold; display:block; margin-bottom:4px;">Display Label text</label>
                        <input type="text" name="menu_title" id="formMenuTitle" required placeholder="e.g., Room Settings" style="width:100%; padding:8px; border:1px solid #cbd5e0; border-radius:6px;">
                    </div>
                    <div>
                        <label style="font-size:11px; font-weight:bold; display:block; margin-bottom:4px;">Icon Element Class</label>
                        <input type="text" name="menu_icon" placeholder="fa-solid fa-gear" style="width:100%; padding:8px; border:1px solid #cbd5e0; border-radius:6px;">
                    </div>
                    <div>
                        <label style="font-size:11px; font-weight:bold; display:block; margin-bottom:4px;">Sorting Order Sequence</label>
                        <input type="number" name="menu_order" value="10" min="0" style="width:100%; padding:8px; border:1px solid #cbd5e0; border-radius:6px;">
                    </div>
                    <button type="submit" style="grid-column: span 2; padding:10px; background:#38a169; color:#fff; border:none; border-radius:6px; font-weight:bold; cursor:pointer; margin-top:5px;">Inject Dynamic Sidebar Link</button>
                </form>
            </div>
        </div>

        <!-- PANEL B: AUTHORIZATION MATRIX AND REMOVAL LEDGER -->
        <div style="background:#fff; border:1px solid #e2e8f0; padding:20px; border-radius:12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <h3 style="font-size:14px; text-transform:uppercase; margin-bottom:15px; color:#0f172a; border-left:3px solid #38a169; padding-left:8px;">🔐 Dynamic Staff Permissions Grid</h3>
            <p style="font-size:12px; color:#64748b; margin-bottom:15px;">Check the boxes to allow roles to access pages. Uncheck or delete rows to instantly remove them from their sidebar menus:</p>
            
            <form method="POST" action="staff_management.php" style="margin:0;">
                <input type="hidden" name="action_save_permissions" value="1">
                <table style="width:100%; font-size:13px; border-collapse:collapse; margin-bottom:20px;">
                    <thead>
                        <tr style="background:#f8fafc; border-bottom:1px solid #e2e8f0; text-align:left; font-weight:bold;">
                            <th style="padding:8px;">Active Page Menu</th>
                            <th style="padding:8px; text-align:center;">Staff</th>
                            <th style="padding:8px; text-align:center;">Chef</th>
                            <th style="padding:8px; text-align:right;">Drop Link</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_menus as $menu_item): ?>
                            <tr style="border-bottom:1px solid #edf2f7;">
                                <td style="padding:8px;">
                                    <strong style="color:#1e293b;"><?= htmlspecialchars($menu_item['title']) ?></strong>
                                    <span style="display:block; font-size:10px; color:#64748b; font-family:monospace;"><?= htmlspecialchars($menu_item['url']) ?></span>
                                </td>
                                <td style="padding:8px; text-align:center;">
                                    <?php if ($menu_item['url'] === 'index.php'): ?>
                                        <input type="checkbox" disabled checked style="transform:scale(1.2);">
                                    <?php else: 
                                        $staff_checked = (isset($allowed_matrix['Staff']) && in_array($menu_item['id'], $allowed_matrix['Staff'])) ? 'checked' : '';
                                    ?>
                                        <input type="checkbox" name="perms[Staff][]" value="<?= $menu_item['id'] ?>" <?= $staff_checked ?> style="transform:scale(1.2); cursor:pointer;">
                                    <?php endif; ?>
                                </td>
                                <td style="padding:8px; text-align:center;">
                                    <?php $chef_checked = (isset($allowed_matrix['Chef']) && in_array($menu_item['id'], $allowed_matrix['Chef'])) ? 'checked' : ''; ?>
                                    <input type="checkbox" name="perms[Chef][]" value="<?= $menu_item['id'] ?>" <?= $chef_checked ?> style="transform:scale(1.2); cursor:pointer;">
                                </td>
                                <td style="padding:8px; text-align:right;">
                                    <?php if (in_array($menu_item['url'], ['index.php', 'billing.php', 'checkin.php', 'staff_management.php'])): ?>
                                        <span style="color:#94a3b8; font-size:11px; font-style:italic;">Protected Core</span>
                                    <?php else: ?>
                                        <a href="staff_management.php?delete_menu_id=<?= $menu_item['id'] ?>" onclick="return confirm('Completely remove this link configuration from all systems?')" style="color:#ef4444; font-weight:bold; text-decoration:none; font-size:12px;">✕ Delete</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <button type="submit" style="width:100%; padding:12px; background:#38a169; border:none; color:#fff; font-weight:bold; border-radius:8px; cursor:pointer;">Save Permissions & Update Sidebars</button>
            </form>
        </div>
    </div>
</div>

<script>
function populateCreationForm(fileName) {
    document.getElementById("quickConfigBox").style.display = "block";
    document.getElementById("formMenuUrl").value = fileName;
    
    // Auto-format readable text titles from raw filenames
    let cleanTitle = fileName.replace('.php', '').replace('_', ' ');
    cleanTitle = cleanTitle.charAt(0).toUpperCase() + cleanTitle.slice(1);
    document.getElementById("formMenuTitle").value = cleanTitle;
}
</script>

<?php include "includes/footer.php"; ?>