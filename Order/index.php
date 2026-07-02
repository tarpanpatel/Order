<?php
require_once "config/db.php";
if (!isset($_SESSION["user_id"])) { header("Location: login.php"); exit; }
$guest = $pdo->query("SELECT * FROM guests WHERE status = 'Active' LIMIT 1")->fetch();
include "includes/header.php";
?>
<h2>Operational Environment</h2>
<p style="color:var(--text-muted); margin-bottom:2rem;">The Artists Farm Admin Panel</p>
<div class="card">
    <h3>Active Guest Profile</h3>
    <p><strong>Resident Identity:</strong> <?= $guest ? htmlspecialchars($guest["guest_name"]) : "No running check-in session."; ?></p>
</div>
<?php include "includes/footer.php"; ?>