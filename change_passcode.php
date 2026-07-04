<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "config/db.php";

// Restrict access exclusively to privileged Admin or Super Admin roles
if (!isset($_SESSION["role"]) || ($_SESSION["role"] !== "Admin" && $_SESSION["role"] !== "Super Admin")) {
    header("Location: login.php");
    exit;
}

$message = "";
$msg_class = "";

// Handle user credential update submissions
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action_modify_user_passcode"])) {
    $target_user_id = intval($_POST["target_user_id"]);
    $new_passcode = trim($_POST["new_passcode"]);

    if ($target_user_id > 0 && !empty($new_passcode)) {
        // Securely hash the password string to maintain authentication protections
        $hashed_password = password_hash($new_passcode, PASSWORD_BCRYPT);
        
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        if ($stmt->execute([$hashed_password, $target_user_id])) {
            $message = "✔ User passcode updated successfully!";
            $msg_class = "success-banner";
        } else {
            $message = "❌ Failed to update the passcode parameters.";
            $msg_class = "error-banner";
        }
    }
}

// Fetch all system users to populate the target selection dropdown
$system_users = $pdo->query("SELECT id, username, role FROM users ORDER BY username ASC")->fetchAll(PDO::FETCH_ASSOC);

include "includes/header.php";
?>

<style>
.security-card-box { max-width: 480px; margin: 20px auto; background: #fff; border: 1px solid #cbd5e0; border-radius: 12px; padding: 25px; text-align: left; }
.form-group-block { margin-bottom: 15px; }
.form-group-block label { font-size: 12px; font-weight: 700; color: #4b5563; display: block; margin-bottom: 5px; }
.form-group-block select, .form-group-block input { width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #cbd5e0; font-size: 13px; box-sizing: border-box; background: #fff; }
.feedback-banner { padding: 10px; font-size: 12px; font-weight: 700; border-radius: 6px; margin-bottom: 15px; text-align: center; }
.success-banner { background: #d1fae5; color: #069669; border: 1px solid #a7f3d0; }
.error-banner { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }
</style>

<div class="app-body">
    <div class="category-section">
        <h2 class="category-title" style="text-transform: none;">🔐 System Passcode Management</h2>
    </div>

    <div class="security-card-box">
        <h3 style="font-size: 14px; font-weight: 700; text-transform: uppercase; margin-top: 0; margin-bottom: 15px; border-bottom: 1px dashed #cbd5e0; padding-bottom: 8px; color: #111827;">Modify User Access Credentials</h3>
        
        <?php if (!empty($message)): ?>
            <div class="feedback-banner <?= $msg_class ?>"><?= $message ?></div>
        <?php endif; ?>

        <form method="POST" action="change_passcode.php" onsubmit="return confirm('Commit these terminal credential access changes?');">
            <input type="hidden" name="action_modify_user_passcode" value="1">
            
            <div class="form-group-block">
                <label>Select Staff Account Profile</label>
                <select name="target_user_id" required>
                    <option value="">-- Select Active User --</option>
                    <?php foreach ($system_users as $u): ?>
                        <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['username']) ?> (<?= $u['role'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group-block">
                <label>New Passcode Access Key</label>
                <input type="password" name="new_passcode" required placeholder="Type new secure passcode key" minlength="4" autocomplete="new-password">
            </div>

            <button type="submit" class="btn btn-start" style="width: 100%; padding: 12px; font-weight: bold; border-radius: 6px; margin-top: 10px;">Apply Security Update</button>
        </form>
    </div>
</div>

<?php include "includes/footer.php"; ?>