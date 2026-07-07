<?php
// /home/apartment/artistsfarmjaipur.com/Order/expense_items_management.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once "config/db.php";

if (!isset($_SESSION["role"]) || ($_SESSION["role"] !== "Admin" && $_SESSION["role"] !== "Super Admin")) {
    die("Access Denied: Administrative credentials required.");
}

$message = "";

// Handle item addition
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action_add_item"])) {
    $item_name = trim($_POST["item_name"]);
    if (!empty($item_name)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO expense_predefined_items (item_name) VALUES (?)");
            $stmt->execute([$item_name]);
            $message = "✔ Predefined item added successfully!";
        } catch (PDOException $e) {
            $message = "❌ Error: Item already exists.";
        }
    }
}

// Handle item deletion
if (isset($_GET["delete_id"])) {
    $del_id = intval($_GET["delete_id"]);
    $stmt = $pdo->prepare("DELETE FROM expense_predefined_items WHERE id = ?");
    $stmt->execute([$del_id]);
    header("Location: expense_items_management.php");
    exit;
}

$predefined_items = $pdo->query("SELECT * FROM expense_predefined_items ORDER BY item_name ASC")->fetchAll(PDO::FETCH_ASSOC);

include "includes/header.php";
?>

<div class="app-body" style="padding: 20px; font-family: sans-serif; text-align: left;">
    <h2>⚙ Predefined Expense Items Workspace</h2>
    <p style="color:#64748b; margin-top:-10px; margin-bottom:20px;">Add or remove descriptions that appear when users choose the 'Other' expense category.</p>

    <?php if(!empty($message)): ?>
        <div style="background:#f0fdf4; border:1px solid #bbf7d0; color:#166534; padding:12px; border-radius:8px; margin-bottom:20px; font-size:14px; font-weight:bold;"><?= $message ?></div>
    <?php endif; ?>

    <div style="display:grid; grid-template-columns: 1fr 2fr; gap:30px; align-items:start;">
        <div style="background:#fff; border:1px solid #cbd5e0; border-radius:12px; padding:20px;">
            <h4 style="margin-top:0; border-bottom:1px dashed #cbd5e0; padding-bottom:8px; text-transform:uppercase; font-size:11px; color:#475569;">➕ Add Description Entry</h4>
            <form method="POST" action="expense_items_management.php">
                <input type="hidden" name="action_add_item" value="1">
                <div style="margin-bottom:15px;">
                    <label style="font-size:11px; font-weight:700; display:block; margin-bottom:4px; color:#475569;">Item Description Name</label>
                    <input type="text" name="item_name" required placeholder="e.g., Garbage, Cab Rent" style="width:100%; padding:10px; border:1px solid #cbd5e0; border-radius:6px; box-sizing:border-box;">
                </div>
                <button type="submit" class="btn btn-bill" style="width:100%; padding:10px; font-weight:bold; background:#06b6d4; border-color:#06b6d4; color:white; border-radius:6px;">Add To Registry</button>
            </form>
        </div>

        <div style="background:#fff; border:1px solid #cbd5e0; border-radius:12px; padding:20px;">
            <h4 style="margin-top:0; border-bottom:1px solid #e2e8f0; padding-bottom:8px; text-transform:uppercase; font-size:11px; color:#475569;">📋 Current Sub-category Entries List</h4>
            <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap:10px; max-height:400px; overflow-y:auto; padding-right:5px;">
                <?php foreach ($predefined_items as $item): ?>
                    <div style="display:flex; justify-content:space-between; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:8px 12px; font-size:13px; align-items:center;">
                        <span style="font-weight:600; color:#1e293b;"><?= htmlspecialchars($item['item_name']) ?></span>
                        <a href="expense_items_management.php?delete_id=<?= $item['id'] ?>" onclick="return confirm('Remove this predefined item?')" style="color:#ef4444; font-weight:bold; text-decoration:none; font-size:12px;">✕</a>
                    </div>
                <?php endphp endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php include "includes/footer.php"; ?>