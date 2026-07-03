<?php
require_once "config/db.php";
if (!isset($_SESSION["user_id"])) { header("Location: login.php"); exit; }

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_stock"])) {
    $stmt = $pdo->prepare("UPDATE inventory SET current_stock = ? WHERE id = ?");
    $stmt->execute([$_POST["current_stock"], $_POST["item_id"]]);
    logAction($_SESSION["user_id"], "Adjusted structural warehouse inventory asset sequence allocation index: ID ".$_POST["item_id"]);
}

$stocks = $pdo->query("SELECT * FROM inventory ORDER BY item_name ASC")->fetchAll();
include "includes/header.php";
?>
<h2>Inventory Status Asset Management</h2>
<table class="responsive-table card">
    <thead>
        <tr><th>Commodity Core Asset</th><th>Current Tracked Units</th><th>Alert Threshold</th><th>Quick Balance Amendment</th></tr>
    </thead>
    <tbody>
        <?php foreach($stocks as $s): ?>
            <tr style="<?= $s["current_stock"] <= $s["low_stock_threshold"] ? 'background:#FFF2F2;' : '' ?>">
                <td><?= htmlspecialchars($s["item_name"]) ?></td>
                <td><strong><?= $s["current_stock"] ?> Units</strong></td>
                <td><?= $s["low_stock_threshold"] ?> Units</td>
                <td>
                    <form method="POST" style="display:flex; gap:0.5rem; margin:0;">
                        <input type="number" name="current_stock" value="<?= $s["current_stock"] ?>" style="width:90px; margin:0; padding:0.4rem;">
                        <input type="hidden" name="item_id" value="<?= $s["id"] ?>">
                        <input type="hidden" name="update_stock" value="1">
                        <button type="submit" class="btn-premium" style="padding:0.4rem 1rem; font-size:0.75rem;">Modify</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php include "includes/footer.php"; ?>