<?php
// /home/apartment/artistsfarmjaipur.com/Order/staff_management.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once "config/db.php";

// Strict Gatekeeper Validation
$user_role = $_SESSION["role"] ?? '';
if ($user_role !== "Admin" && $user_role !== "Super Admin") {
    die("Access Denied: Administrative credentials required.");
}

// Ensure columns and tracking logs exist
try {
    $pdo->query("CREATE TABLE IF NOT EXISTS `users` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `username` varchar(50) NOT NULL,
        `password` varchar(255) NOT NULL,
        `role` varchar(50) DEFAULT 'Staff',
        PRIMARY KEY (`id`),
        UNIQUE KEY `username` (`username`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (PDOException $e) { }

// --- HANDLE POST: ADD NEW STAFF MEMBER ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action_add_staff"])) {
    $username = trim($_POST["username"]);
    $password_raw = trim($_POST["password"]);
    $role = $_POST["role"];

    if (!empty($username) && !empty($password_raw)) {
        $hashed_password = password_hash($password_raw, PASSWORD_BCRYPT);
        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
            $stmt->execute([$username, $hashed_password, $role]);
            
            $audit_stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, timestamp) VALUES (?, ?, NOW())");
            $audit_stmt->execute([$_SESSION['user_id'], "User [" . $_SESSION['username'] . "] registered new system user: [" . $username . "] with role: " . $role]);
            
            $_SESSION['staff_success'] = "Staff member '$username' added successfully!";
        } catch (PDOException $e) {
            $_SESSION['staff_error'] = "Error: Username might already exist or constraints failed.";
        }
    } else {
        $_SESSION['staff_error'] = "Please provide both username and a valid passcode.";
    }
    header("Location: staff_management.php");
    exit;
}

// --- HANDLE GET: DELETE STAFF MEMBER ---
if (isset($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);
    if ($del_id !== intval($_SESSION["user_id"])) {
        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$del_id]);
            
            $audit_stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, timestamp) VALUES (?, ?, NOW())");
            $audit_stmt->execute([$_SESSION['user_id'], "User [" . $_SESSION['username'] . "] deleted user profile ID: [" . $del_id . "]"]);

            $_SESSION['staff_success'] = "User profile permanently removed.";
        } catch (PDOException $e) {
            $_SESSION['staff_error'] = "Error removing user from active tables.";
        }
    } else {
        $_SESSION['staff_error'] = "You cannot delete your own active session profile.";
    }
    header("Location: staff_management.php");
    exit;
}

// --- HANDLE POST: UPDATE ACCESS PERMISSION MATRIX ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action_update_permissions"])) {
    $permissions = $_POST['permissions'] ?? [];
    
    $pdo->beginTransaction();
    try {
        $managed_roles = ['Admin', 'Chef', 'Staff'];
        $inQuery = implode(',', array_fill(0, count($managed_roles), '?'));
        $clearStmt = $pdo->prepare("DELETE FROM sys_role_menu WHERE role_name IN ($inQuery)");
        $clearStmt->execute($managed_roles);

        if (!empty($permissions) && is_array($permissions)) {
            $insertStmt = $pdo->prepare("INSERT INTO sys_role_menu (role_name, menu_id) VALUES (?, ?)");
            foreach ($permissions as $role_key => $menu_ids) {
                foreach ($menu_ids as $m_id => $checked) {
                    $insertStmt->execute([$role_key, $m_id]);
                }
            }
        }
        
        $audit_stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, timestamp) VALUES (?, ?, NOW())");
        $audit_stmt->execute([$_SESSION['user_id'], "User [" . $_SESSION['username'] . "] modified global Role Access Matrix"]);

        $pdo->commit();
        $_SESSION['staff_success'] = "Universal menu access permissions updated successfully!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['staff_error'] = "Failed to update permissions: " . $e->getMessage();
    }
    header("Location: staff_management.php");
    exit;
}

// Fetch Active Data Arrays
$all_users = $pdo->query("SELECT id, username, role FROM users ORDER BY role ASC, username ASC")->fetchAll(PDO::FETCH_ASSOC);
$all_menus = $pdo->query("SELECT * FROM sys_menu ORDER BY parent_id ASC, sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);

// Map Current Permissions
$raw_perms = $pdo->query("SELECT * FROM sys_role_menu")->fetchAll(PDO::FETCH_ASSOC);
$active_perms = [];
foreach ($raw_perms as $rp) {
    $active_perms[$rp['role_name']][$rp['menu_id']] = true;
}

$roles_to_manage = ['Admin', 'Chef', 'Staff'];

include "includes/header.php";
?>

<div class="app-body" style="padding: 20px; font-family: sans-serif; text-align: left;">
    
    <?php if(isset($_SESSION['staff_success'])): ?>
        <div style="background:#f0fdf4; border:1px solid #bbf7d0; color:#166534; padding:12px; border-radius:8px; margin-bottom:15px; font-size:14px; font-weight:bold;">
            ✔ <?= $_SESSION['staff_success']; unset($_SESSION['staff_success']); ?>
        </div>
    <?php endif; ?>
    
    <?php if(isset($_SESSION['staff_error'])): ?>
        <div style="background:#fef2f2; border:1px solid #fecaca; color:#991b1b; padding:12px; border-radius:8px; margin-bottom:15px; font-size:14px; font-weight:bold;">
            ❌ <?= $_SESSION['staff_error']; unset($_SESSION['staff_error']); ?>
        </div>
    <?php endif; ?>

    <div class="category-section">
        <h2 style="margin-bottom: 4px;">👥 Staff & Identity Management</h2>
        <p style="margin:0; font-size:12px; color:#64748b; font-weight:600;">Control system users and functional role capabilities.</p>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; margin-bottom: 25px;">
        
        <div style="background: #ffffff; border: 1px solid #cbd5e0; border-radius: 12px; padding: 25px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <h3 style="margin-top:0; color:#1e293b; border-bottom:1px solid #e2e8f0; padding-bottom:10px; font-size:14px; text-transform:uppercase;">Add New Terminal Identity</h3>
            <form method="POST" action="staff_management.php">
                <input type="hidden" name="action_add_staff" value="1">
                
                <div style="margin-bottom:15px;">
                    <label style="font-size:11px; font-weight:700; color:#475569; display:block; margin-bottom:4px;">USERNAME IDENTIFIER</label>
                    <input type="text" name="username" required autocomplete="off" style="width:100%; padding:10px; border:1px solid #cbd5e0; border-radius:6px; box-sizing:border-box; font-weight:600;">
                </div>
                
                <div style="margin-bottom:15px;">
                    <label style="font-size:11px; font-weight:700; color:#475569; display:block; margin-bottom:4px;">TERMINAL PASSCODE (PIN)</label>
                    <input type="password" name="password" required autocomplete="off" placeholder="••••" style="width:100%; padding:10px; border:1px solid #cbd5e0; border-radius:6px; box-sizing:border-box; letter-spacing:4px; font-weight:bold;">
                </div>
                
                <div style="margin-bottom:20px;">
                    <label style="font-size:11px; font-weight:700; color:#475569; display:block; margin-bottom:4px;">FUNCTIONAL ROLE</label>
                    <select name="role" required style="width:100%; padding:10px; border:1px solid #cbd5e0; border-radius:6px; background:white; font-weight:bold;">
                        <option value="Staff">Staff (Standard Terminal Access)</option>
                        <option value="Chef">Chef (Kitchen Management Only)</option>
                        <option value="Admin">Admin (Full System Privilege)</option>
                    </select>
                </div>

                <button type="submit" style="width:100%; padding:12px; font-size:14px; font-weight:bold; background:#0284c7; border:none; color:white; border-radius:8px; cursor:pointer;">Register Profile Credentials</button>
            </form>
        </div>

        <div style="background: #ffffff; border: 1px solid #cbd5e0; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); display: flex; flex-direction: column;">
            <h3 style="margin-top:0; color:#1e293b; border-bottom:1px solid #e2e8f0; padding-bottom:10px; font-size:14px; text-transform:uppercase;">Active Users Ledger</h3>
            <div style="overflow-y: auto; max-height: 250px; flex: 1; padding-right: 5px;">
                <table class="past-table" style="width:100%;">
                    <thead>
                        <tr>
                            <th style="padding:8px; position:sticky; top:0; background:#f8fafc; z-index:10;">Username</th>
                            <th style="padding:8px; position:sticky; top:0; background:#f8fafc; z-index:10;">Assigned Role</th>
                            <th style="padding:8px; position:sticky; top:0; background:#f8fafc; z-index:10; text-align:center;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($all_users)): foreach ($all_users as $member): ?>
                            <tr style="border-bottom:1px solid #f1f5f9;">
                                <td style="padding:10px; font-weight:bold; color:#1e293b;"><?= htmlspecialchars($member['username']) ?></td>
                                <td style="padding:10px;">
                                    <span style="background:#f1f5f9; padding:3px 8px; border-radius:12px; font-size:11px; font-weight:700; color:#475569; border: 1px solid #e2e8f0;">
                                        <?= htmlspecialchars($member['role'] ?? 'Staff') ?>
                                    </span>
                                </td>
                                <td style="padding:10px; text-align:center;">
                                    <?php if ($member['id'] !== intval($_SESSION["user_id"])): ?>
                                        <a href="staff_management.php?delete_id=<?= $member['id'] ?>" onclick="return confirm('Remove access for this profile permanently?')" style="color:#ef4444; font-weight:bold; text-decoration:none; font-size:12px; background:#fef2f2; padding:4px 8px; border-radius:6px; border:1px solid #fecaca;">Delete</a>
                                    <?php else: ?>
                                        <span style="color:#94a3b8; font-size:11px; font-style:italic;">Your Session</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="3" style="text-align:center; padding:20px; color:#94a3b8; font-style:italic;">No profiles registered in users table.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div style="background: #ffffff; border: 1px solid #cbd5e0; border-radius: 12px; padding: 25px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        <h3 style="margin-top:0; color:#1e293b; border-bottom:1px solid #e2e8f0; padding-bottom:10px; font-size:14px; text-transform:uppercase;">System Access Permission Matrix</h3>
        <p style="font-size:12px; color:#64748b; margin-bottom:20px;">Use the checkboxes below to define which UI navigation panels and operational actions are visible to specific structural roles. (Super Admins bypass this matrix and always retain universal system control.)</p>
        
        <form method="POST" action="staff_management.php" style="margin: 0; overflow-x: auto;">
            <input type="hidden" name="action_update_permissions" value="1">
            <table class="past-table" style="width:100%; min-width:600px; border:1px solid #e2e8f0; border-radius:8px; overflow:hidden;">
                <thead>
                    <tr style="background:#f8fafc;">
                        <th style="padding:12px; border-right:1px solid #e2e8f0;">Navigation Link Name</th>
                        <?php foreach($roles_to_manage as $r): ?>
                            <th style="padding:12px; text-align:center; border-right:1px solid #e2e8f0; font-size:13px;"><?= $r ?> Role</th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_menus as $menu): 
                        // Visual indentation for sub-menus
                        $is_sub = ($menu['parent_id'] > 0);
                        $indent = $is_sub ? "padding-left: 35px;" : "font-weight: bold;";
                        $prefix = $is_sub ? "↳ " : "";
                    ?>
                        <tr style="border-bottom:1px solid #edf2f7; <?= $is_sub ? 'background:#fafafa;' : '' ?>">
                            <td style="padding:10px 12px; <?= $indent ?> border-right:1px solid #e2e8f0;">
                                <span style="font-size:14px; margin-right:4px;"><?= htmlspecialchars($menu['icon']) ?></span>
                                <?= $prefix . htmlspecialchars($menu['title']) ?>
                                <?php if ($menu['title'] === 'Admin Control'): ?>
                                    <span style="font-size:10px; color:#94a3b8; font-weight:normal; margin-left:8px;">(Dropdown Parent Container)</span>
                                <?php endif; ?>
                            </td>
                            
                            <?php foreach($roles_to_manage as $r): 
                                $isChecked = isset($active_perms[$r][$menu['id']]) ? 'checked' : '';
                            ?>
                                <td style="text-align:center; padding:10px; border-right:1px solid #e2e8f0; vertical-align:middle;">
                                    <input type="checkbox" name="permissions[<?= $r ?>][<?= $menu['id'] ?>]" value="1" <?= $isChecked ?> style="transform:scale(1.3); cursor:pointer; accent-color:#0284c7;">
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div style="margin-top:20px; text-align:right;">
                <button type="submit" class="btn btn-start" style="padding:12px 30px; font-size:14px; font-weight:800; border-radius:8px;">💾 Save Matrix Permissions</button>
            </div>
        </form>
    </div>

</div>

<?php include "includes/footer.php"; ?>