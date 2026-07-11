<?php
// /home/apartment/artistsfarmjaipur.com/Order/miscellaneous.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "config/db.php";

if (!isset($_SESSION["role"]) || !check_page_access($pdo)) {
    die("Access Denied: You do not have permission to access this area.");
}

$msg = "";
// Handle adding new predefined categories
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action_add_misc'])) {
    $type = trim($_POST['new_charge_type'] ?? '');
    if (!empty($type)) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO miscellaneous_catalog (charge_type) VALUES (?)");
        $stmt->execute([$type]);
        $msg = "✔ Predefined adjustment template mapped successfully.";
    }
}

// Handle deleting categories
if (isset($_GET['delete_id'])) {
    $stmt = $pdo->prepare("DELETE FROM miscellaneous_catalog WHERE id = ?");
    $stmt->execute([intval($_GET['delete_id'])]);
    header("Location: miscellaneous.php");
    exit;
}

$catalog = $pdo->query("SELECT * FROM miscellaneous_catalog ORDER BY charge_type ASC")->fetchAll(PDO::FETCH_ASSOC);
include "includes/header.php";
?>

<div class="app-body" style="padding: 20px; text-align: left;">
    <div class="page-header" style="margin-bottom: 20px;">
        <h2 class="page-title">⚙ Predefined Miscellaneous Charge Strategies</h2>
        <p style="color: #64748b; font-size: 13px; margin-top: 4px;">Configure structural catalog line items like Decorations or Pets available at room check-in registry bounds.</p>
    </div>

    <?php if(!empty($msg)): ?>
        <div style="background:#f0fdf4; border:1px solid #bbf7d0; color:#166534; padding:12px; border-radius:8px; margin-bottom:20px; font-size:14px;"><?= $msg ?></div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: 350px 1fr; gap: 20px; align-items: start;">
        <div style="background:#fff; border:1px solid #e2e8f0; padding:20px; border-radius:12px;">
            <strong style="font-size:14px; display:block; margin-bottom:12px; color:#1f2937;">Add New Template Group</strong>
            <form method="POST" action="miscellaneous.php">
                <input type="hidden" name="action_add_misc" value="1">
                <div style="margin-bottom:15px;">
                    <label style="font-size:12px; font-weight:700; display:block; margin-bottom:6px;">Charge Label Name</label>
                    <input type="text" name="new_charge_type" required placeholder="e.g., Decoration Fees" style="width:100%; padding:10px; border:1px solid #cbd5e0; border-radius:8px; font-size:14px;">
                </div>
                <button type="submit" style="width:100%; padding:12px; background:#00b0ff; border:none; color:#fff; font-weight:bold; border-radius:8px; cursor:pointer;">Save Configuration</button>
            </form>
        </div>

        <div style="background:#fff; border:1px solid #e2e8f0; padding:20px; border-radius:12px;">
            <table style="width:100%; border-collapse:collapse; font-size:14px;">
                <thead>
                    <tr style="border-bottom:2px solid #e2e8f0; text-align:left; color:#4b5563; font-weight:bold;">
                        <th style="padding:10px;">Predefined Template Label</th>
                        <th style="padding:10px; text-align:right;">Operations</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(!empty($catalog)): foreach($catalog as $row): ?>
                        <tr style="border-bottom:1px solid #edf2f7;">
                            <td style="padding:10px; font-weight:600; color:#2d3748;"><?= htmlspecialchars($row['charge_type']) ?></td>
                            <td style="padding:10px; text-align:right;">
                                <a href="miscellaneous.php?delete_id=<?= $row['id'] ?>" onclick="return confirm('Remove layout parameter template?')" style="color:#ef4444; font-weight:bold; text-decoration:none;">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="2" style="padding:20px; text-align:center; color:#9ca3af;">No template models created yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include "includes/footer.php"; ?>