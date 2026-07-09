<?php
// /home/apartment/artistsfarmjaipur.com/Order/staff_management.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once "config/db.php";

// 1. Strict Gatekeeper
$user_role = $_SESSION["role"] ?? '';
if ($user_role !== "Admin" && $user_role !== "Super Admin") {
    die("Access Denied: Administrative credentials required.");
}

// --- HANDLE POST: ADD NEW STAFF MEMBER ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action_add_staff"])) {
    $username = trim($_POST["username"]);
    $password_raw = trim($_POST["password"]);
    // 🔑 FIXED: Set default if role is missing
    $role = $_POST["role"] ?? 'Staff'; 

    if (!empty($username) && !empty($password_raw)) {
        $hashed_password = password_hash($password_raw, PASSWORD_BCRYPT);
        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
            $stmt->execute([$username, $hashed_password, $role]);
            $_SESSION['staff_success'] = "Staff member '$username' added successfully!";
        } catch (PDOException $e) {
            $_SESSION['staff_error'] = "Error: Username might already exist.";
        }
    }
    header("Location: staff_management.php");
    exit;
}

// --- HANDLE POST: UPDATE ACCESS PERMISSION MATRIX ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action_update_permissions"])) {
    $permissions = $_POST['permissions'] ?? [];
    $pdo->beginTransaction();
    try {
        // Clear old permissions for the roles we are managing
        $pdo->query("DELETE FROM sys_role_menu WHERE role_name IN ('Admin', 'Chef', 'Staff')");
        
        // Insert new checked permissions
        if (!empty($permissions)) {
            $insertStmt = $pdo->prepare("INSERT INTO sys_role_menu (role_name, menu_id) VALUES (?, ?)");
            foreach ($permissions as $role_key => $menu_ids) {
                foreach ($menu_ids as $m_id => $checked) {
                    $insertStmt->execute([$role_key, $m_id]);
                }
            }
        }
        $pdo->commit();
        $_SESSION['staff_success'] = "Access Matrix Updated!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['staff_error'] = "Update failed: " . $e->getMessage();
    }
    header("Location: staff_management.php");
    exit;
}

// --- DATA FETCHING ---
// 🔑 FIXED: Used COALESCE to ensure role is never blank in UI
$all_users = $pdo->query("SELECT id, username, COALESCE(role, 'Staff') as role FROM users ORDER BY role ASC, username ASC")->fetchAll(PDO::FETCH_ASSOC);
$all_menus = $pdo->query("SELECT * FROM sys_menu ORDER BY parent_id ASC, sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);

// Map permissions for the checkboxes
$raw_perms = $pdo->query("SELECT * FROM sys_role_menu")->fetchAll(PDO::FETCH_ASSOC);
$active_perms = [];
foreach ($raw_perms as $rp) { $active_perms[$rp['role_name']][$rp['menu_id']] = true; }
$roles_to_manage = ['Admin', 'Chef', 'Staff'];

include "includes/header.php";
?>

<div class="app-body" style="padding: 20px;">
    <?php if(isset($_SESSION['staff_success'])): ?><div style="background:#f0fdf4; color:#166534; padding:12px; border-radius:8px; margin-bottom:15px; font-weight:bold;">✔ <?= $_SESSION['staff_success']; unset($_SESSION['staff_success']); ?></div><?php endif; ?>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; margin-bottom: 25px;">
        <div style="background: #fff; padding: 25px; border-radius: 12px; border:1px solid #cbd5e0;">
            <h3 style="margin-top:0;">Add New Staff</h3>
            <form method="POST" action="staff_management.php">
                <input type="hidden" name="action_add_staff" value="1">
                <input type="text" name="username" placeholder="Username" required style="width:100%; padding:10px; margin-bottom:10px; border:1px solid #cbd5e0; border-radius:6px;">
                <input type="password" name="password" placeholder="Passcode" required style="width:100%; padding:10px; margin-bottom:10px; border:1px solid #cbd5e0; border-radius:6px;">
                <select name="role" style="width:100%; padding:10px; margin-bottom:15px; border:1px solid #cbd5e0; border-radius:6px;">
                    <option value="Staff">Staff</option>
                    <option value="Chef">Chef</option>
                    <option value="Admin">Admin</option>
                </select>
                <button type="submit" style="width:100%; padding:10px; background:#0284c7; color:white; border:none; border-radius:6px; font-weight:bold;">Create User</button>
            </form>
        </div>

        <div style="background: #fff; padding: 25px; border-radius: 12px; border:1px solid #cbd5e0;">
            <h3 style="margin-top:0;">Current Staff</h3>
            <table style="width:100%; border-collapse:collapse;">
                <?php foreach ($all_users as $member): ?>
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:10px;"><?= htmlspecialchars($member['username']) ?></td>
                        <td style="padding:10px;"><span style="background:#f1f5f9; padding:4px 8px; border-radius:6px; font-size:11px; font-weight:bold;"><?= htmlspecialchars($member['role']) ?></span></td>
                        <td style="padding:10px; text-align:right;">
                            <?php if ($member['id'] !== intval($_SESSION["user_id"])): ?>
                                <a href="staff_management.php?delete_id=<?= $member['id'] ?>" style="color:red; text-decoration:none;">Delete</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>

    <div style="background: #fff; padding: 25px; border-radius: 12px; border:1px solid #cbd5e0;">
        <h3 style="margin-top:0;">Access Permission Matrix</h3>
        <form method="POST" action="staff_management.php">
            <input type="hidden" name="action_update_permissions" value="1">
            <table style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr style="background:#f8fafc;">
                        <th style="padding:10px; text-align:left;">Menu Item</th>
                        <?php foreach($roles_to_manage as $r): ?><th style="padding:10px;"><?= $r ?></th><?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_menus as $menu): ?>
                        <tr>
                            <td style="padding:10px; border-bottom:1px solid #f1f5f9;"><?= htmlspecialchars($menu['title']) ?></td>
                            <?php foreach($roles_to_manage as $r): ?>
                                <td style="padding:10px; text-align:center; border-bottom:1px solid #f1f5f9;">
                                    <input type="checkbox" name="permissions[<?= $r ?>][<?= $menu['id'] ?>]" value="1" <?= isset($active_perms[$r][$menu['id']]) ? 'checked' : '' ?>>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <button type="submit" style="margin-top:20px; padding:12px 30px; background:#059669; color:white; border:none; border-radius:6px; font-weight:bold;">Save Matrix</button>
        </form>
    </div>
</div>
<?php include "includes/footer.php"; ?>