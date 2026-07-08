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

    // Insert new parent requisition ticket row
    $insertParent = $pdo->prepare("INSERT INTO requisitions (status, requested_at) VALUES ('Pending', NOW())");
    $insertParent->execute();
    $requisition_id = $pdo->lastInsertId();

    $items_summary_list = [];
    $human_action_phrases = [];

    // Setup statements for loop
    $insertItem = $pdo->prepare("
        INSERT INTO requisition_items (requisition_id, catalog_id, quantity, chosen_unit_label, item_status) 
        VALUES (?, ?, ?, ?, 'Pending')
    ");
    $fetchCatalogSpec = $pdo->prepare("SELECT item_name, pack_size, pack_unit, unit_label FROM req_catalog WHERE id = ?");

    foreach ($inputData['items'] as $cartItem) {
        $catalog_id = intval($cartItem['id']);
        $quantity   = floatval($cartItem['qty']);

        if ($quantity <= 0) continue;

        // Fetch specs to transform technical data into natural human phrases
        $fetchCatalogSpec->execute([$catalog_id]);
        $spec = $fetchCatalogSpec->fetch(PDO::FETCH_ASSOC);

        $item_name  = $spec ? $spec['item_name'] : "Unknown Item";
        $unit_label = $spec ? $spec['unit_label'] : "Pcs";
        $pack_size  = $spec ? floatval($spec['pack_size']) : 1;
        $pack_unit  = $spec ? $spec['pack_unit'] : "Pcs";

        // Record line item link
        $insertItem->execute([$requisition_id, $catalog_id, $quantity, $unit_label]);

        // Construct short human phrase for this specific element
        // Looks like: "2 liters of Mustard Oil" or "5 packets of Amul Butter"
        $human_action_phrases[] = $quantity . " " . $pack_unit . " of " . $item_name;

        // Add to list for the Telegram broadcast text block
        $items_summary_list[] = "• " . $item_name . " (" . $pack_size . " " . $pack_unit . ") x" . $quantity . " " . $unit_label;
    }

    // Build unified human-friendly summary sentence for the activity logs table
    // Looks like: "Requested stock of 2 liters of Mustard Oil, 1 kg of Basmati Rice."
    $final_log_summary = "Requested stock of " . implode(", ", $human_action_phrases) . ".";

    // Insert right into your active audit table engine prior to finalizing commits
    $audit_stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, timestamp) VALUES (?, ?, NOW())");
    $audit_stmt->execute([
        $_SESSION['user_id'], 
        $final_log_summary
    ]);

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