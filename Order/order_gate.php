<?php
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
        <?php if(isset($error)) echo "<p style='color:red;'>$error</p>"; ?>
        <input type="password" name="pad_password" placeholder="••••" required style="text-align:center; font-size:2rem;" inputmode="numeric">
        <button type="submit" class="btn-premium btn-gold" style="width:100%;">Unlock Menu Screen</button>
    </form>
</body></html>