<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once "../config/db.php";

// IF AUTO-REFRESH IS REQUESTED: Output the data instantly as a clean JSON stream
if (isset($_GET['action_get_live_list'])) {
    header('Content-Type: application/json');
    $active = $pdo->query("SELECT guest_name FROM guests WHERE status = 'Active' LIMIT 1")->fetchColumn();
    $booked = $pdo->query("SELECT id, guest_name FROM guests WHERE status IN ('Booked', 'Confirmed', 'Pending') AND status != 'CheckedOut' ORDER BY checkin_date ASC")->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['active_guest' => $active ?: null, 'booked_guests' => $booked]);
    exit;
}

// OTHERWISE: Handle normal backend registration activation actions
header('Content-Type: application/json');
$inputRaw = file_get_contents("php://input");
$payload = json_decode($inputRaw, true);
$guest_id = isset($payload['guest_id']) ? intval($payload['guest_id']) : 0;

if ($guest_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'No target account specified.']);
    exit;
}

$pdo->beginTransaction();
try {
    $pdo->query("UPDATE guests SET status = 'Booked' WHERE status = 'Active'");
    $stmt = $pdo->prepare("UPDATE guests SET status = 'Active' WHERE id = ?");
    $stmt->execute([$guest_id]);
    $pdo->commit();
    echo json_encode(['success' => true]);
    exit;
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}