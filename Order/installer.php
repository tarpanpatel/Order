<?php
// installer.php - Run this once via browser to generate the ENTIRE system instantly.

$structure = [
    // 1. TAILORED PRODUCTION DATABASE BRIDGE SETTING
    'config/db.php' => '<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$db_host = "localhost"; 
$db_user = "apartment_blue"; 
$db_pass = "tPatel13@"; 
$db_name = "apartment_blue";
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) { die("Database Connection Error: " . $e->getMessage()); }

function logAction($userId, $actionText) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?, ?)");
    $stmt->execute([$userId, $actionText]);
}
?>',
    
    // 2. REVISED STYLING (SLEEK ROWS & MODAL LAYOUTS)
    'assets/css/style.css' => ':root {
    --bg-light: #FAFAFA; --surface-white: #FFFFFF; --gold-primary: #C5A880; --gold-dark: #A38458;
    --text-dark: #2C2C2C; --text-muted: #707070; --border-color: #EAEAEA; --radius-premium: 12px;
    --shadow-soft: 0 4px 20px rgba(0,0,0,0.03); --font-stack: "Inter", system-ui, sans-serif;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body { background-color: var(--bg-light); color: var(--text-dark); font-family: var(--font-stack); -webkit-font-smoothing: antialiased; }
.app-container { display: grid; grid-template-columns: 260px 1fr; min-height: 100vh; }
.sidebar { background: var(--surface-white); border-right: 1px solid var(--border-color); padding: 2.5rem 1.5rem; }
.main-content { padding: 2.5rem; overflow-y: auto; }
.category-navbar { position: sticky; top: 0; background: var(--bg-light); padding: 1rem 0; z-index: 100; display: flex; gap: 0.5rem; overflow-x: auto; white-space: nowrap; margin-bottom: 1.5rem; scrollbar-width: none; }
.category-navbar::-webkit-scrollbar { display: none; }
.category-tab { padding: 0.6rem 1.2rem; background: var(--surface-white); border: 1px solid var(--border-color); border-radius: 30px; color: var(--text-dark); text-decoration: none; font-size: 0.9rem; transition: all 0.2s ease; }
.category-tab:hover, .category-tab.active { background: var(--gold-primary); color: white; border-color: var(--gold-primary); }
.menu-row { background: var(--surface-white); border-bottom: 1px solid var(--border-color); padding: 1rem; display: flex; align-items: center; justify-content: space-between; }
.menu-row:hover { background: #FCFCFA; }
.menu-details { flex: 1; }
.menu-title { font-weight: 500; font-size: 1rem; color: var(--text-dark); }
.menu-price { color: var(--gold-dark); font-size: 0.9rem; font-weight: 600; margin-top: 0.2rem; }
.controls-wrapper { display: flex; align-items: center; gap: 1rem; }
.qty-btn { width: 36px; height: 36px; border-radius: 50%; border: 1px solid var(--border-color); background: white; font-size: 1.2rem; display: flex; align-items: center; justify-content: center; cursor: pointer; }
.icon-btn { border: none; background: none; cursor: pointer; font-size: 1.2rem; color: var(--text-muted); padding: 0.5rem; }
.modal-overlay { position: fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.4); display:none; justify-content:center; align-items:center; z-index:500; }
.modal-box { background: white; padding: 2rem; border-radius: var(--radius-premium); width: 90%; max-width: 450px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); }
.card { background: var(--surface-white); border-radius: var(--radius-premium); padding: 2rem; box-shadow: var(--shadow-soft); border: 1px solid var(--border-color); margin-bottom: 1.5rem; }
.btn-premium { background: var(--text-dark); color: #fff; border:none; padding: 0.8rem 1.5rem; border-radius: var(--radius-premium); cursor: pointer; text-transform: uppercase; letter-spacing: 1px; font-size: 0.8rem; text-decoration: none; display: inline-block; text-align: center; }
.btn-premium:hover { background: var(--gold-dark); }
.btn-gold { background: var(--gold-primary); color: white; }
.responsive-table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
.responsive-table th, .responsive-table td { padding: 1rem; border-bottom: 1px solid var(--border-color); text-align: left; }
input, select, textarea { width: 100%; padding: 0.8rem 1rem; border: 1px solid var(--border-color); border-radius: var(--radius-premium); background: var(--bg-light); color: var(--text-dark); margin-bottom: 1rem; font-family: inherit; }
@media(max-width: 768px) { .app-container { grid-template-columns: 1fr; } .sidebar { display: none; } .main-content { padding: 1rem; } }',

    // 3. BASE HEADER INCLUDES
    'includes/header.php' => '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>The Artists Farm Operations</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app-container">
    <div class="sidebar">
        <h2 style="color:var(--gold-dark); font-size:1.1rem; letter-spacing:2px; text-transform:uppercase; text-align:center; margin-bottom:3rem;">The Artists Farm</h2>
        <ul style="list-style:none; padding:0;">
            <?php if(isset($_SESSION["role"])): ?>
                <li style="margin-bottom:0.8rem;"><a href="index.php" style="color:var(--text-dark); text-decoration:none;">Dashboard</a></li>
                <li style="margin-bottom:0.8rem;"><a href="billing.php" style="color:var(--text-dark); text-decoration:none;">Settlements & Billing</a></li>
                <li style="margin-bottom:0.8rem;"><a href="menu_admin.php" style="color:var(--text-dark); text-decoration:none;">Menu Setup (Admin)</a></li>
            <?php endif; ?>
            <li style="margin-bottom:0.8rem;"><a href="order.php" style="color:var(--text-dark); text-decoration:none;">Take Food Order</a></li>
            <li style="margin-bottom:0.8rem;"><a href="kitchen.php" style="color:var(--text-dark); text-decoration:none;">Kitchen Orders</a></li>
            <li style="margin-bottom:0.8rem;"><a href="requisitions.php" style="color:var(--text-dark); text-decoration:none;">Material Requests</a></li>
            <li style="margin-top:4rem;"><a href="logout.php" style="color:var(--text-muted); text-decoration:none;">Sign Out Ledger</a></li>
        </ul>
    </div>
    <div class="main-content">',

    'includes/footer.php' => '    </div>
</div>
</body>
</html>',

    // 4. MANAGEMENT BACKEND DASHBOARD
    'index.php' => '<?php
require_once "config/db.php";
if (!isset($_SESSION["user_id"])) { header("Location: login.php"); exit; }
$guest = $pdo->query("SELECT * FROM guests WHERE status = \'Active\' LIMIT 1")->fetch();
include "includes/header.php";
?>
<h2>Operational Environment</h2>
<p style="color:var(--text-muted); margin-bottom:2rem;">The Artists Farm Admin Panel</p>
<div class="card">
    <h3>Active Guest Profile</h3>
    <p><strong>Resident Identity:</strong> <?= $guest ? htmlspecialchars($guest["guest_name"]) : "No running check-in session."; ?></p>
</div>
<?php include "includes/footer.php"; ?>',

    // 5. RESPONSIVE WAITER ROW ORDER VIEW
    'order.php' => '<?php
require_once "config/db.php";
if (!isset($_SESSION["user_id"]) && !isset($_SESSION["order_authenticated"])) { header("Location: order_gate.php"); exit; }
include "includes/header.php";
?>
<div class="category-navbar">
    <a href="#" class="category-tab active" onclick="filterCategory(\'all\', this)">All Items</a>
    <?php
    $categories = $pdo->query("SELECT * FROM menu_categories ORDER BY sort_order ASC")->fetchAll();
    foreach($categories as $cat) { echo \'<a href="#" class="category-tab" onclick="filterCategory(\'.$cat[\'id\'].\', this)">\'.htmlspecialchars($cat[\'name\']).\'</a>\'; }
    ?>
</div>
<div class="order-workspace">
    <div>
        <input type="text" id="rowSearch" placeholder="Type to instantly filter menu items..." style="margin-bottom:1rem;" oninput="searchMenu()">
        <div id="rowCatalogView" class="card" style="padding:0;"></div>
    </div>
    <div class="card" style="position:sticky; top:80px; height:calc(100vh - 120px); display:flex; flex-direction:column; justify-content:space-between;">
        <div><h3>Running Cart</h3><div id="cartContent" style="overflow-y:auto; max-height:55vh;"></div></div>
        <button class="btn-premium btn-gold" style="width:100%;" onclick="submitCart()">Route Order to Kitchen</button>
    </div>
</div>
<div id="notesModal" class="modal-overlay">
    <div class="modal-box">
        <h4>Prep Instructions</h4>
        <input type="text" id="modalNotesText" placeholder="Eg: Extra Spicy...">
        <input type="hidden" id="modalTargetItemId">
        <div style="display:flex; gap:1rem;"><button class="btn-premium" onclick="saveNotesModal()">Save</button><button class="btn-premium" onclick="closeNotesModal()" style="background:#ccc;">Cancel</button></div>
    </div>
</div>
<script {STRICT_COMPLETION}>
let cart = [], menuData = [];
function loadCatalog() { fetch("api/search_menu.php").then(res=>res.json()).then(data=>{ menuData=data; renderRows(menuData); }); }
function renderRows(items) {
    const container = document.getElementById("rowCatalogView"); container.innerHTML = "";
    items.forEach(item => {
        container.innerHTML += `<div class="menu-row">
            <div class="menu-details"><span class="menu-title">${item.name}</span><div class="menu-price">₹${parseFloat(item.price).toFixed(2)}</div></div>
            <div class="controls-wrapper">
                <button class="icon-btn" onclick="openNotesModal(${item.id})">✏️</button>
                <button class="qty-btn" onclick="modifyCartQty(${item.id}, \'${item.name.replace(/\'/g, "\\\'")}\', ${item.price}, 1)">+</button>
                <span id="row_qty_${item.id}" style="font-weight:600;">0</span>
                <button class="qty-btn" onclick="modifyCartQty(${item.id}, \'${item.name.replace(/\'/g, "\\\'")}\', ${item.price}, -1)">-</button>
            </div>
        </div>`;
    });
    updateQtyCounters();
}
function modifyCartQty(id, name, price, delta) {
    let existing = cart.find(x => x.id === id);
    if(existing) { existing.qty += delta; if(existing.qty <= 0) cart = cart.filter(x => x.id !== id); }
    else if(delta > 0) { cart.push({ id, name, price, qty: 1, notes: "" }); }
    updateQtyCounters(); renderCart();
}
function updateQtyCounters() {
    menuData.forEach(item => { const el = document.getElementById(`row_qty_${item.id}`); if(el) { let inCart = cart.find(x => x.id === item.id); el.innerText = inCart ? inCart.qty : 0; } });
}
function renderCart() {
    const wrap = document.getElementById("cartContent"); wrap.innerHTML = "";
    cart.forEach(item => { wrap.innerHTML += `<div style="padding:0.5rem 0; border-bottom:1px dashed #eee;"><strong>${item.qty}x</strong> ${item.name} ${item.notes ? `<br><small style="color:brown;">↳ ${item.notes}</small>` : ""}</div>`; });
}
function filterCategory(catId, btn) {
    document.querySelectorAll(".category-tab").forEach(t => t.classList.remove("active")); btn.classList.add("active");
    renderRows(catId === "all" ? menuData : menuData.filter(x => x.category_id == catId));
}
function searchMenu() { let q = document.getElementById("rowSearch").value.toLowerCase(); renderRows(menuData.filter(x => x.name.toLowerCase().includes(q))); }
function openNotesModal(id) { document.getElementById("modalTargetItemId").value = id; let item = cart.find(x => x.id === id); document.getElementById("modalNotesText").value = item ? item.notes : ""; document.getElementById("notesModal").style.display = "flex"; }
function closeNotesModal() { document.getElementById("notesModal").style.display = "none"; }
function saveNotesModal() { let id = parseInt(document.getElementById("modalTargetItemId").value); let item = cart.find(x => x.id === id); if(item) { item.notes = document.getElementById("modalNotesText").value; } closeNotesModal(); renderCart(); }
function submitCart() {
    if(cart.length === 0) return alert("Cart empty");
    fetch("api/process_order.php", { method: "POST", headers: {"Content-Type": "application/json"}, body: JSON.stringify({ items: cart }) }).then(res=>res.json()).then(d => { if(d.success) { alert("Dispatched to kitchen"); cart = []; updateQtyCounters(); renderCart(); } });
}
window.onload = loadCatalog;
</script>',

    // 6. KITCHEN LOG QUEUE DISPLAY SCREEN
    'kitchen.php' => '<?php
require_once "config/db.php";
if (!isset($_SESSION["user_id"]) && !isset($_SESSION["order_authenticated"])) { header("Location: order_gate.php"); exit; }
if (isset($_POST["complete_order_id"])) { $pdo->prepare("UPDATE orders SET status = \'Completed\' WHERE id = ?")->execute([$_POST["complete_order_id"]]); }
$pending = $pdo->query("SELECT o.id, g.guest_name FROM orders o JOIN guests g ON o.guest_id = g.id WHERE o.status = \'Pending\'")->fetchAll();
include "includes/header.php";
?>
<h2>Kitchen Live Prep Queue</h2>
<div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap:1.5rem;">
    <?php foreach($pending as $order): 
        $items = $pdo->query("SELECT oi.*, mi.name FROM order_items oi JOIN menu_items mi ON oi.menu_item_id = mi.id WHERE oi.order_id = ".$order["id"])->fetchAll();
    ?>
        <div class="card">
            <h4>Order #<?= $order["id"] ?></h4>
            <p style="font-size:0.85rem; color:grey; margin-bottom:1rem;">Guest: <?= htmlspecialchars($order["guest_name"]) ?></p>
            <ul style="margin-bottom:1rem;">
                <?php foreach($items as $i) echo "<li><strong>{$i["quantity"]}x</strong> {$i["name"]} <br><small style=\'color:brown;\'>{$i["special_instructions"]}</small></li>"; ?>
            </ul>
            <form method="POST"><input type="hidden" name="complete_order_id" value="<?= $order["id"] ?>"><button type="submit" class="btn-premium" style="width:100%;">Fulfill Dispatch</button></form>
        </div>
    <?php endforeach; ?>
</div>
<?php include "includes/footer.php"; ?>',

    // 7. COMPREHENSIVE ADJUSTABLE BILLING SUITE
    'billing.php' => '<?php
require_once "config/db.php";
if (!isset($_SESSION["user_id"])) { header("Location: login.php"); exit; }
$guest = $pdo->query("SELECT * FROM guests WHERE status = \'Active\' LIMIT 1")->fetch();
if ($guest && isset($_POST["adjust_action"])) {
    $id = intval($_POST["order_item_id"]); $qty = intval($_POST["adjust_qty"]);
    if($_POST["adjust_type"] === "Cancel") { $pdo->prepare("UPDATE order_items SET quantity = quantity - ? WHERE id = ?")->execute([$qty, $id]); }
    else { $pdo->prepare("UPDATE order_items SET returned_qty = returned_qty + ? WHERE id = ?")->execute([$qty, $id]); }
    header("Location: billing.php"); exit;
}
include "includes/header.php";
?>
<h2>Adjustments & Invoicing Screen</h2>
<?php if(!$guest): ?><div class="card"><p>No open guest invoices available.</p></div><?php else: 
$items = $pdo->query("SELECT oi.*, mi.name FROM order_items oi JOIN menu_items mi ON oi.menu_item_id = mi.id JOIN orders o ON oi.order_id = o.id WHERE o.guest_id = ".$guest["id"])->fetchAll();
$subtotal = 0;
?>
<div class="card">
    <h3>Open Statement Run: <?= htmlspecialchars($guest["guest_name"]) ?></h3>
    <table class="responsive-table">
        <thead><tr><th>Item Specification</th><th>Ordered</th><th>Returned</th><th>Net Charged</th><th>Actions</th></tr></thead>
        <tbody>
            <?php foreach($items as $i): if($i["quantity"] <= 0) continue; 
                $net = $i["quantity"] - $i["returned_qty"]; $cost = $net * $pdo->query("SELECT price FROM menu_items WHERE id = ".$i["menu_item_id"])->fetchColumn(); $subtotal += $cost;
            ?>
            <tr>
                <td><?= htmlspecialchars($i["name"]) ?></td><td><?= $i["quantity"] ?></td><td style="color:red;"><?= $i["returned_qty"] ?></td><td>₹<?= number_format($cost, 2) ?></td>
                <td>
                    <form method="POST" style="display:flex; gap:2px; margin:0;">
                        <input type="hidden" name="order_item_id" value="<?= $i["id"] ?>"><input type="number" name="adjust_qty" value="1" max="<?= $net ?>" style="width:45px; margin:0; padding:2px;">
                        <select name="adjust_type" style="margin:0; padding:2px; width:80px;"><option value="Return">Return</option><option value="Cancel">Cancel</option></select>
                        <button type="submit" name="adjust_action" class="btn-premium" style="padding:2px 6px; font-size:0.7rem;">Go</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <h3 style="text-align:right; margin-top:2rem;">Payable Balance: ₹<?= number_format($subtotal, 2) ?></h3>
</div>
<?php endif; include "includes/footer.php"; ?>',

    // 8. ADMIN MENU ITEM INDIVIDUAL MANAGER
    'menu_admin.php' => '<?php
require_once "config/db.php";
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "Admin") { die("Admin status required."); }
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_item"])) {
    $pdo->prepare("INSERT INTO menu_items (category_id, name, price) VALUES (?, ?, ?)")->execute([$_POST["category_id"], $_POST["name"], $_POST["price"]]);
}
include "includes/header.php";
?>
<div class="card" style="max-width:500px; margin:0 auto;">
    <h3>Create Individual Menu Item</h3>
    <form method="POST">
        <select name="category_id"><?php foreach($pdo->query("SELECT * FROM menu_categories")->fetchAll() as $c) echo "<option value=\'{$c["id"]}\'>{$c["name"]}</option>"; ?></select>
        <input type="text" name="name" placeholder="Exact Item Specification Name" required>
        <input type="number" step="0.01" name="price" placeholder="Rate Unit Price (₹)" required>
        <button type="submit" name="add_item" class="btn-premium btn-gold" style="width:100%;">Add Row Entry</button>
    </form>
</div>
<?php include "includes/footer.php"; ?>',

    // 9. REQUISITION REQUEST ENGINE LINK
    'requisitions.php' => '<?php
require_once "config/db.php";
if (isset($_POST["clear_req_id"])) { $pdo->prepare("UPDATE requisitions SET status = \'Fulfilled\' WHERE id = ?")->execute([$_POST["clear_req_id"]]); }
include "includes/header.php";
?>
<h2>Kitchen Requisition System</h2>
<div class="order-workspace">
    <div class="card">
        <h3>File Material Request</h3>
        <form action="api/process_requisition.php" method="POST">
            <div style="max-height:45vh; overflow-y:auto; border:1px solid #eee; padding:1rem; border-radius:8px; margin-bottom:1rem;">
                <?php foreach($pdo->query("SELECT * FROM req_catalog ORDER BY item_name ASC")->fetchAll() as $item): ?>
                    <div style="display:flex; justify-content:space-between; margin-bottom:0.5rem;">
                        <span style="font-size:0.9rem;"><?= htmlspecialchars($item["item_name"]) ?></span>
                        <input type="number" name="req_qty[<?= $item["id"] ?>]" value="0" min="0" style="width:65px; margin:0; padding:2px;">
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="submit" class="btn-premium btn-gold" style="width:100%;">Send Request</button>
        </form>
    </div>
    <div>
        <h3>Active Requests</h3>
        <?php foreach($pdo->query("SELECT * FROM requisitions WHERE status = \'Pending\' ORDER BY requested_at DESC")->fetchAll() as $r): ?>
            <div class="card" style="padding:1rem;">
                <strong>Request Sheet #<?= $r["id"] ?></strong>
                <ul><?php foreach($pdo->query("SELECT ri.quantity, rc.item_name FROM requisition_items ri JOIN req_catalog rc ON ri.catalog_id = rc.id WHERE ri.requisition_id = ".$r["id"])->fetchAll() as $ri) echo "<li>{$ri["quantity"]}x {$ri["item_name"]}</li>"; ?></ul>
                <form method="POST" style="margin-top:0.5rem;"><input type="hidden" name="clear_req_id" value="<?= $r["id"] ?>"><button type="submit" class="btn-premium" style="padding:2px 8px; font-size:0.75rem;">Mark Clear</button></form>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php include "includes/footer.php"; ?>',

    // 10. API ENDPOINTS HANDLERS
    'api/search_menu.php' => '<?php
require_once "../config/db.php";
header("Content-Type: application/json");
echo json_encode($pdo->query("SELECT * FROM menu_items WHERE is_hidden = 0 ORDER BY name ASC")->fetchAll());
?>',

    'api/process_order.php' => '<?php
require_once "../config/db.php";
header("Content-Type: application/json");
$data = json_decode(file_get_contents("php://input"), true);
$guest = $pdo->query("SELECT id FROM guests WHERE status = \'Active\' LIMIT 1")->fetch();
if(!$guest || empty($data["items"])) { echo json_encode(["success"=>false]); exit; }
$pdo->beginTransaction();
try {
    $pdo->prepare("INSERT INTO orders (guest_id, status) VALUES (?, \'Pending\')")->execute([$guest["id"]]);
    $order_id = $pdo->lastInsertId();
    $stmt = $pdo->prepare("INSERT INTO order_items (order_id, menu_item_id, quantity, special_instructions) VALUES (?, ?, ?, ?)");
    foreach($data["items"] as $item) { $stmt->execute([$order_id, $item["id"], $item["qty"], $item["notes"]]); }
    $pdo->commit(); echo json_encode(["success"=>true]);
} catch(Exception $e) { $pdo->rollBack(); echo json_encode(["success"=>false]); }
?>',

    'api/process_requisition.php' => '<?php
require_once "../config/db.php";
if($_SERVER["REQUEST_METHOD"] === "POST" && !empty($_POST["req_qty"])) {
    $pdo->beginTransaction();
    try {
        $pdo->query("INSERT INTO requisitions (status) VALUES (\'Pending\')"); $req_id = $pdo->lastInsertId();
        $stmt = $pdo->prepare("INSERT INTO requisition_items (requisition_id, catalog_id, quantity) VALUES (?, ?, ?)");
        foreach($_POST["req_qty"] as $catalog_id => $qty) {
            if(intval($qty) > 0) { $stmt->execute([$req_id, $catalog_id, intval($qty)]); }
        }
        $pdo->commit();
    } catch(Exception $e) { $pdo->rollBack(); }
}
header("Location: ../requisitions.php");
exit;
?>',

    // 11. PASSCODES ONLY AND PORTAL ENTRY ACCESS GATES
    'order_gate.php' => '<?php
require_once "config/db.php";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if ($_POST["pad_password"] === "farm123") { $_SESSION["order_authenticated"] = true; header("Location: order.php"); exit; }
    else { $error = "Incorrect Entry Passcode String Reference."; }
}
?>
<!DOCTYPE html><html><head><link rel="stylesheet" href="assets/css/style.css"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="display:flex; justify-content:center; align-items:center; height:100vh; background:var(--bg-light); text-align:center;">
    <form class="card" method="POST" style="width:100%; max-width:400px;">
        <h3>Order Entry Pad</h3><p style="color:grey; font-size:0.85rem; margin-bottom:1.5rem;">Enter Property Floor Passcode</p>
        <?php if(isset($error)) echo "<p style=\'color:red;\'>$error</p>"; ?>
        <input type="password" name="pad_password" placeholder="••••" required style="text-align:center; font-size:2rem;" inputmode="numeric">
        <button type="submit" class="btn-premium btn-gold" style="width:100%;">Unlock Menu Screen</button>
    </form>
</body></html>',

    'login.php' => '<?php
require_once "config/db.php";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"]); $password = trim($_POST["password"]);
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?"); $stmt->execute([$username]); $user = $stmt->fetch();
    if ($user && password_verify($password, $user["password"])) {
        $_SESSION["user_id"] = $user["id"]; $_SESSION["role"] = $user["role"]; header("Location: index.php"); exit;
    } else { $error = "Verification clearance exception."; }
}
?>
<!DOCTYPE html><html><head><link rel="stylesheet" href="assets/css/style.css"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="display:flex; justify-content:center; align-items:center; height:100vh; background:var(--bg-light);">
    <form class="card" method="POST" style="width:100%; max-width:400px; padding:2.5rem;">
        <h2 style="text-align:center; margin-bottom:1.5rem;">THE ARTISTS FARM</h2>
        <?php if(isset($error)) echo "<p style=\'color:red; font-size:0.85rem; margin-bottom:1rem;\'>$error</p>"; ?>
        <input type="text" name="username" placeholder="Username ID" required autocomplete="off">
        <input type="password" name="password" placeholder="Access Password" required>
        <button type="submit" class="btn-premium btn-gold" style="width:100%;">Sign In Dashboard</button>
    </form>
</body></html>',

    'logout.php' => '<?php require_once "config/db.php"; session_destroy(); header("Location: login.php"); exit; ?>'
];

// Execute deployment generation cycle loop
foreach ($structure as $path => $code) {
    $dir = dirname($path);
    if (!is_dir($dir)) { mkdir($dir, 0755, true); }
    file_put_contents($path, trim($code));
    echo "Generated asset file sequence: <span style='color:green;'>✔</span> <strong>$path</strong><br>";
}
echo "<br><strong>Ecosystem Extracted with requested profile config!</strong> Delete `installer.php` and open up <a href='login.php' style='font-weight:bold;color:blue;'>login.php</a>.";
?>