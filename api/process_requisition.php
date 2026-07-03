<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once "../config/db.php";

header('Content-Type: application/json');

$inputRaw = file_get_contents("php://input");
$payload = json_decode($inputRaw, true);

if (empty($payload['items'])) {
    echo json_encode(['success' => false, 'error' => 'No items selected.']);
    exit;
}

$pdo->beginTransaction();
try {
    // 1. Insert Master Requisition Record
    $insertReq = $pdo->prepare("INSERT INTO requisitions (requested_at, status) VALUES (CURRENT_TIMESTAMP, 'Pending')");
    $insertReq->execute();
    $requisition_id = $pdo->lastInsertId();

    // 2. Insert into child items table matching your exact column structure
    $insertItem = $pdo->prepare("INSERT INTO requisition_items (requisition_id, catalog_id, quantity) VALUES (?, ?, ?)");

    foreach ($payload['items'] as $item) {
        // Double-check your JS payload tags (usually item.id and item.qty)
        $material_id = intval($item['id']);
        $qty = intval($item['qty']);

        if ($qty > 0) {
            $insertItem->execute([$requisition_id, $material_id, $qty]);
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true]);
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}