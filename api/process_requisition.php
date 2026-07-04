<?php
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}
require_once "../config/db.php";
require_once "../config/telegram.php"; // Global telegram dispatch configurations gateway route bridge

header('Content-Type: application/json');

// Read raw inbound JSON string from application fetch parameters
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

    // 2. Core database parameters execution targeting catalog_id fields
    $insertItem = $pdo->prepare("INSERT INTO requisition_items (requisition_id, catalog_id, quantity) VALUES (?, ?, ?)");

    $telegramMaterialsBlock = "";

    foreach ($payload['items'] as $item) {
        $material_id = intval($item['id']);
        $qty = intval($item['qty']);
        $itemName = trim($item['name'] ?? 'Unknown Item');

        if ($qty > 0) {
            $insertItem->execute([$requisition_id, $material_id, $qty]);
            
            // Build an informative visual bullet line for the group notification string
            $telegramMaterialsBlock .= "🔹 *x" . $qty . "* " . $itemName . "\n";
        }
    }

    $pdo->commit();

    // 3. TELEGRAM CHANNEL NOTIFICATION DISPATCH ROUTE
    try {
        $requisitionMsg = "📦 *NEW MATERIAL REQUISITION REQUEST*\n";
        $requisitionMsg .= "--------------------------------------\n";
      //  $requisitionMsg .= "🆔 *Request ID Reference:* #" . $requisition_id . "\n";
        $requisitionMsg .= "⏰ *Requested At:* " . date('H:i d-m-Y') . "\n";
        $requisitionMsg .= "--------------------------------------\n\n";
        $requisitionMsg .= $telegramMaterialsBlock;
        $requisitionMsg .= "\n--------------------------------------\n";
       // $requisitionMsg .= "🛠️ _Staff, please verify and fulfill these inventory items from the store dashboard._";

        // Dispatch out to your whitelisted gateway group
        sendTelegramNotification($requisitionMsg);
    } catch (Exception $tgEx) {
        // Safe catch ensures network proxy latency never stalls the checkout workflow UI response
    }

    echo json_encode(['success' => true]);
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}