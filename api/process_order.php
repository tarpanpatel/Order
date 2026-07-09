<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Report all PHP errors
error_reporting(E_ALL);

// Force errors to be displayed on the screen
ini_set('display_errors', '1');
require_once "../config/db.php";
require_once "../config/telegram.php";

header('Content-Type: application/json');

// Ensure the user has an authenticated session role (Admin or Chef)
if (!isset($_SESSION["role"]) || ($_SESSION["role"] !== "Admin" && $_SESSION["role"] !== "Chef")) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access segment.']);
    exit;
}

// Read the raw JSON stream sent from order.php
$inputRaw = file_get_contents("php://input");
$data = json_encode(json_decode($inputRaw, true)); // Validate format
$payload = json_decode($data, true);

if (empty($payload['items'])) {
    echo json_encode(['success' => false, 'error' => 'Cart selection matrix is completely empty.']);
    exit;
}

// 1. Identify the currently Active Resident Guest to bind the orders cleanly
$guestQuery = $pdo->query("SELECT id, guest_name FROM guests WHERE status = 'Active' LIMIT 1");
$activeGuest = $guestQuery->fetch(PDO::FETCH_ASSOC);

if (!$activeGuest) {
    echo json_encode(['success' => false, 'error' => 'No active resident ledger sheet opened in sidebar.']);
    exit;
}

$guest_id = $activeGuest['id'];
$guest_name = $activeGuest['guest_name'];

$pdo->beginTransaction();
try {
    // 2. Insert into master orders ledger table
    $insertOrder = $pdo->prepare("INSERT INTO orders (guest_id, order_time, status) VALUES (?, CURRENT_TIMESTAMP, 'Pending')");
    $insertOrder->execute([$guest_id]);
    $order_id = $pdo->lastInsertId();

    // 3. Insert individual choices into child order_items table
    $insertItem = $pdo->prepare("INSERT INTO order_items (order_id, menu_item_id, quantity, returned_qty, special_instructions, item_status, is_complimentary) VALUES (?, ?, ?, 0, ?, 'Served', 0)");

    $telegramItemsBlock = "";
    foreach ($payload['items'] as $item) {
        $menu_item_id = intval($item['id']);
        $qty = intval($item['qty']);
        $instructions = trim($item['notes'] ?? '');

        $insertItem->execute([$order_id, $menu_item_id, $qty, $instructions]);

        // Accumulate a readable item layout block string for the Telegram transmission
        $instructionBadge = !empty($instructions) ? " *(_Instructions: " . htmlspecialchars($instructions) . "_)*" : "";
        $telegramItemsBlock .= "🔹 *x" . $qty . "* " . $item['name'] . $instructionBadge . "\n";
    }

    $pdo->commit();

    // 4. GENERATE AND DISPATCH THE TELEGRAM KITCHEN TICKETING NOTIFICATION
    try {
        $kitchenMsg = "🍳 *NEW KITCHEN ORDER RECEIVED*\n";
        $kitchenMsg .= "--------------------------------------\n";
        $kitchenMsg .= "⏰ *Received Time:* " . date('H:i d-m-Y') . "\n";
        $kitchenMsg .= "--------------------------------------\n\n";
        $kitchenMsg .= $telegramItemsBlock;
        $kitchenMsg .= "\n--------------------------------------\n";

        // Push out to your whitelisted proxy script gateway route
        sendTelegramNotification($kitchenMsg);
    } catch (Exception $tgEx) {
        // Fail-safe protection ensures API issues won't crash user response feedback logs
    }

    echo json_encode(['success' => true, 'order_id' => $order_id]);
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => 'Database process fault: ' . $e->getMessage()]);
    exit;
}