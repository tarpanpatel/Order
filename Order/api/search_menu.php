<?php
require_once "../config/db.php";
header("Content-Type: application/json");
echo json_encode($pdo->query("SELECT * FROM menu_items WHERE is_hidden = 0 ORDER BY name ASC")->fetchAll());
?>