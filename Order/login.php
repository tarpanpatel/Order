<?php
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
        <?php if(isset($error)) echo "<p style='color:red; font-size:0.85rem; margin-bottom:1rem;'>$error</p>"; ?>
        <input type="text" name="username" placeholder="Username ID" required autocomplete="off">
        <input type="password" name="password" placeholder="Access Password" required>
        <button type="submit" class="btn-premium btn-gold" style="width:100%;">Sign In Dashboard</button>
    </form>
</body></html>