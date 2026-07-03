<?php
require_once "../config/db.php";
if (!isset($_SESSION["user_id"]) && !isset($_SESSION["order_authenticated"])) {
    echo json_encode(["success" => false, "message" => "Unauthorized access"]);
    exit;
}
header("Content-Type: application/json");
$data = json_decode(file_get_contents("php://input"), true);
$guest = $pdo->query("SELECT id FROM guests WHERE status = 'Active' LIMIT 1")->fetch();

if(!$guest || empty($data["items"])) { echo json_encode(["success" => false]); exit; }

$pdo->beginTransaction();
try {
    $pdo->prepare("INSERT INTO orders (guest_id, status) VALUES (?, 'Pending')")->execute([$guest["id"]]);
    $order_id = $pdo->lastInsertId();
    
    $stmt = $pdo->prepare("INSERT INTO order_items (order_id, menu_item_id, quantity, special_instructions) VALUES (?, ?, ?, ?)");
    foreach($data["items"] as $item) { 
        $stmt->execute([$order_id, $item["id"], $item["qty"], $item["notes"]]); 
    }
    $pdo->commit();
    
    // ONE-WAY DATA PUSH ENGINE OUT TO GOOGLE APP SCRIPT WEBHOOK URL
    $google_sheets_webhook_url = "https://script.google.com/macros/s/AKfycbxXnEepbFDYwOYO2hVcqyguihNEdl0YuoMPjCzi7Qx6x0kcgVOoYdFjZYzVmtikdTsi6A/exec";
    
    $payload_data = [
        "order_id" => $order_id,
        "items" => $data["items"]
    ];
    
    $ch = curl_init($google_sheets_webhook_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 4); // Quick timeout drops connection immediately after dispatching
    curl_exec($ch);
    curl_close($ch);

    echo json_encode(["success" => true]);
} catch(Exception $e) { 
    $pdo->rollBack(); 
    echo json_encode(["success" => false]); 
}
?>