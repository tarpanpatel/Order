<?php
// /home/apartment/artistsfarmjaipur.com/Order/process_requisition.php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . "/config/db.php";

if (file_exists(__DIR__ . "/config/telegram.php")) {
    require_once __DIR__ . "/config/telegram.php";
}

if (!isset($_SESSION["user_id"])) {
    echo json_encode(["success" => false, "error" => "Session expired or unauthenticated."]);
    exit;
}

// Read raw JSON data stream payload sent from the front-end cart interface
$rawInput = file_get_contents('php://input');
$inputData = json_decode($rawInput, true);

if (empty($inputData['items']) || !is_array($inputData['items'])) {
    echo json_encode(["success" => false, "error" => "No inventory items selected in payload."]);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Create a parent envelope container inside the requisitions log
    $insertParent = $pdo->prepare("INSERT INTO requisitions (requested_by, status, requested_at) VALUES (?, 'Pending', NOW())");
    $insertParent->execute([$_SESSION['user_id']]);
    $requisition_id = $pdo->lastInsertId();

    $items_summary_list = [];

    // 2. Loop through and save individual rows into requisition_items
    $insertItem = $pdo->prepare("
        INSERT INTO requisition_items (requisition_id, catalog_id, quantity, chosen_unit_label, item_status) 
        VALUES (?, ?, ?, ?, 'Pending')
    ");

    $fetchCatalogSpec = $pdo->prepare("SELECT item_name, pack_size, pack_unit, unit_label FROM req_catalog WHERE id = ?");

    foreach ($inputData['items'] as $cartItem) {
        $catalog_id = intval($cartItem['id']);
        $quantity   = floatval($cartItem['qty']);

        if ($quantity <= 0) continue;

        // Fetch specs to preserve item data details
        $fetchCatalogSpec->execute([$catalog_id]);
        $spec = $fetchCatalogSpec->fetch(PDO::FETCH_ASSOC);

        $item_name  = $spec ? $spec['item_name'] : "Unknown Item";
        $unit_label = $spec ? $spec['unit_label'] : "Pcs";
        $pack_size  = $spec ? floatval($spec['pack_size']) : 1;
        $pack_unit  = $spec ? $spec['pack_unit'] : "Pcs";

        // Record line item link
        $insertItem->execute([$requisition_id, $catalog_id, $quantity, $unit_label]);

        // Add to list for the Telegram broadcast text block
        $items_summary_list[] = "• " . $item_name . " (" . $pack_size . " " . $pack_unit . ") x" . $quantity . " " . $unit_label;
    }

    $pdo->commit();

    // ==========================================================================
    // DISPATCH TO TELEGRAM KITCHEN CHANNEL
    // ==========================================================================
    if (!empty($items_summary_list) && function_exists('sendTelegramMessage')) {
        $tg_body = "📦 <b>NEW MATERIAL REQUEST DISPATCHED</b>\n";
        $tg_body .= "━━━━━━━━━━━━━━━━━━\n";
        $tg_body .= "🆔 <b>Order ID:</b> #ID-" . $requisition_id . "\n";
        $tg_body .= "👤 <b>Requested By:</b> " . htmlspecialchars($_SESSION['username'] ?? 'Kitchen Staff') . "\n";
        $tg_body .= "📅 <b>Time:</b> " . date('d M Y, h:i A') . "\n\n";
        $tg_body .= "📝 <b>Items List Required:</b>\n";
        $tg_body .= implode("\n", $items_summary_list) . "\n";
        $tg_body .= "━━━━━━━━━━━━━━━━━━\n";
        $tg_body .= "📊 <b>Status:</b> 🟡 Pending Sourcing from Market";

        sendTelegramMessage($tg_body);
    }
    // ==========================================================================

    echo json_encode(["success" => true, "requisition_id" => $requisition_id]);
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(["success" => false, "error" => "Database exception: " . $e->getMessage()]);
    exit;
}