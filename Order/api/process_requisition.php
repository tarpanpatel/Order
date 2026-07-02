<?php
require_once "../config/db.php";
if($_SERVER["REQUEST_METHOD"] === "POST" && !empty($_POST["req_qty"])) {
    $pdo->beginTransaction();
    try {
        $pdo->query("INSERT INTO requisitions (status) VALUES ('Pending')"); $req_id = $pdo->lastInsertId();
        $stmt = $pdo->prepare("INSERT INTO requisition_items (requisition_id, catalog_id, quantity) VALUES (?, ?, ?)");
        foreach($_POST["req_qty"] as $catalog_id => $qty) {
            if(intval($qty) > 0) { $stmt->execute([$req_id, $catalog_id, intval($qty)]); }
        }
        $pdo->commit();
    } catch(Exception $e) { $pdo->rollBack(); }
}
header("Location: ../requisitions.php");
exit;
?>