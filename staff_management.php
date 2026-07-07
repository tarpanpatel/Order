<?php
// /home/apartment/artistsfarmjaipur.com/Order/staff_management.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once "config/db.php";

// FIXED: Session checking to read natively from role_name column parameter mapping
if (!isset($_SESSION["role"]) || ($_SESSION["role"] !== "Admin" && $_SESSION["role"] !== "Super Admin")) {
    die("Access Denied: Administrative credentials required.");
}

// --- HANDLE POST: ADD NEW STAFF MEMBER ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action_add_staff"])) {
    $username = trim($_POST["username"]);
    $password_raw = trim($_POST["password"]);
    $role_name = $_POST["role_name"]; // FIXED: Matches database schema column

    if (!empty($username) && !empty($password_raw)) {
        $hashed_password = password_hash($password_raw, PASSWORD_BCRYPT);
        try {
            // FIXED SCHEMA QUERY: Insert into role_name instead of role
            $stmt = $pdo->prepare("INSERT INTO users (username, password, role_name) VALUES (?, ?, ?)");
            $stmt->execute([$username, $hashed_password, $role_name]);
            $_SESSION['staff_success'] = "Staff member '$username' added successfully!";
        } catch (PDOException $e) {
            $_SESSION['staff_error'] = "Error: Username might already exist or table constraint exception.";
        }
    }
    header("Location: staff_management.php");
    exit;
}

// --- HANDLE POST: REMOVE STAFF MEMBER ---
if (isset($_GET["delete_id"])) {
    $delete_id = intval($_GET["delete_id"]);
    if ($delete_id !== intval($_SESSION["user_id"])) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$delete_id]);
        $_SESSION['staff_success'] = "Staff member removed successfully!";
    }
    header("Location: staff_management.php");
    exit;
}

// FIXED SCHEMA QUERY: SELECT id, username, role_name FROM users
$staff_list = $pdo->query("SELECT id, username, role_name FROM users ORDER BY username ASC")->fetchAll(PDO::FETCH_ASSOC);

include "includes/header.php";
?>

<div class="app-body" style="padding: 20px; font-family: sans-serif; text-align: left;">
    <h2>👥 Team & Staff Roster Registry</h2>
    <p style="color:#64748b; margin-top:-10px; margin-bottom:20px;">Manage operational property roles, system credentials, and expense mappings inside the unified users ledger table.</p>

    <?php if (isset($_SESSION['staff_success'])): ?>
        <div style="padding:10px; background:#10b981; color:white; border-radius:6px; margin-bottom:15px; font-weight:bold;">📢 <?= htmlspecialchars($_SESSION['staff_success']); unset($_SESSION['staff_success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['staff_error'])): ?>
        <div style="padding:10px; background:#ef4444; color:white; border-radius:6px; margin-bottom:15px; font-weight:bold;">⚠️ <?= htmlspecialchars($_SESSION['staff_error']); unset($_SESSION['staff_error']); ?></div>
    <?php endif; ?>

    <div style="display:grid; grid-template-columns: 1fr 2fr; gap:30px; align-items:start;">
        <div style="background:#fff; border:1px solid #cbd5e0; border-radius:12px; padding:20px;">
            <h4 style="margin-top:0; border-bottom:1px dashed #cbd5e0; padding-bottom:8px; text-transform:uppercase; font-size:12px; color:#475569;">➕ Add New Member</h4>
            <form method="POST" action="staff_management.php">
                <input type="hidden" name="action_add_staff" value="1">
                <div style="margin-bottom:12px;">
                    <label style="font-size:11px; font-weight:700; display:block; margin-bottom:4px; color:#475569;">Staff Username / Name</label>
                    <input type="text" name="username" required placeholder="e.g., Kamlesh" style="width:100%; padding:8px; border:1px solid #cbd5e0; border-radius:6px; box-sizing:border-box;">
                </div>
                <div style="margin-bottom:12px;">
                    <label style="font-size:11px; font-weight:700; display:block; margin-bottom:4px; color:#475569;">System Passcode / Password</label>
                    <input type="password" name="password" required placeholder="Security pin or code" style="width:100%; padding:8px; border:1px solid #cbd5e0; border-radius:6px; box-sizing:border-box;">
                </div>
                <div style="margin-bottom:18px;">
                    <label style="font-size:11px; font-weight:700; display:block; margin-bottom:4px; color:#475569;">Permission Assignment Role</label>
                    <select name="role_name" style="width:100%; padding:8px; border:1px solid #cbd5e0; border-radius:6px; background:white;">
                        <option value="Staff">Staff Terminal</option>
                        <option value="Admin">Admin</option>
                        <option value="Super Admin">Super Admin</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-bill" style="width:100%; padding:10px; font-weight:bold; background:#06b6d4; border-color:#06b6d4; color:white; border-radius:6px;">Save Member Profile</button>
            </form>
        </div>

        <div style="background:#fff; border:1px solid #cbd5e0; border-radius:12px; padding:20px;">
            <h4 style="margin-top:0; border-bottom:1px solid #e2e8f0; padding-bottom:8px; text-transform:uppercase; font-size:12px; color:#475569;">📋 Active System Registry Profiles</h4>
            <table style="width:100%; border-collapse:collapse; font-size:13px;">
                <thead>
                    <tr style="background:#f8fafc; text-align:left; border-bottom:2px solid #e2e8f0;">
                        <th style="padding:10px;">Username</th>
                        <th style="padding:10px;">Assigned Role</th>
                        <th style="padding:10px; text-align:center;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(!empty($staff_list)): foreach ($staff_list as $member): ?>
                        <tr style="border-bottom:1px solid #f1f5f9;">
                            <td style="padding:10px; font-weight:bold; color:#1e293b;"><?= htmlspecialchars($member['username']) ?></td>
                            <td style="padding:10px;"><span style="background:#f1f5f9; padding:3px 8px; border-radius:12px; font-size:11px; font-weight:700; color:#475569;"><?= htmlspecialchars($member['role_name'] ?? 'Staff') ?></span></td>
                            <td style="padding:10px; text-align:center;">
                                <?php if ($member['id'] !== intval($_SESSION["user_id"])): ?>
                                    <a href="staff_management.php?delete_id=<?= $member['id'] ?>" onclick="return confirm('Remove access for this profile?')" style="color:#ef4444; font-weight:bold; text-decoration:none;">🗑️ Delete</a>
                                <?php else: ?>
                                    <span style="color:#94a3b8; font-style:italic;">Current Session</span>
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

<?php include "includes/footer.php"; ?>