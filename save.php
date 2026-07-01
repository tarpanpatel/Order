<?php
header('Content-Type: application/json');
date_default_timezone_set('Asia/Kolkata'); 

// ⚠️ UPDATE THESE STRINGS WITH YOUR ASSIGNED CPANEL SQL CONFIGURATIONS
$db_host = 'localhost';
$db_user = 'apartment_blue';
$db_pass = 'tPatel13@';
$db_name = 'apartment_blue';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

$action = $_GET['action'] ?? '';

// Fetches list of all dishes from database to render frontend dashboard dynamically
if ($action === 'get_menu') {
    $stmt = $pdo->query("SELECT id, name, category, price FROM menu ORDER BY category, id");
    echo json_encode($stmt->fetchAll());
    exit;
}

// Appends active transactions or logs Check-In / Check-Out session limits
if ($action === 'save_order') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || empty($input['items'])) {
        echo json_encode(['status' => 'error', 'message' => 'Empty items payload']);
        exit;
    }
    
    // Looks back to calculate chronological auto increment order sequences smoothly
    $stmt = $pdo->query("SELECT MAX(order_id) as max_id FROM orders");
    $row = $stmt->fetch();
    $next_order_id = ($row['max_id']) ? $row['max_id'] + 1 : 1001;
    
    $current_date = date('Y-m-d');
    $current_time = date('H:i');
    $status = $input['status'] ?? 'ACTIVE';
    
    $insert = $pdo->prepare("INSERT INTO orders (order_id, booking_date, timestamp, category, item_name, quantity, price, total, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    foreach ($input['items'] as $item) {
        $insert->execute([
            $next_order_id,
            $current_date,
            $current_time,
            $item['category'],
            $item['name'],
            $item['quantity'],
            $item['price'],
            $item['total'],
            $status
        ]);
    }
    echo json_encode(['status' => 'success', 'currentLocalDate' => $current_date]);
    exit;
}

// Compiles historical log timelines matching exact chosen stay timeline queries
if ($action === 'get_day_log') {
    $target_date = $_GET['date'] ?? date('Y-m-d');
    $stmt = $pdo->prepare("SELECT id as rowId, order_id as orderId, timestamp, item_name as name, quantity, total, status, category FROM orders WHERE booking_date = ? ORDER BY id DESC");
    $stmt->execute([$target_date]);
    echo json_encode($stmt->fetchAll());
    exit;
}

// Scans back backward from the newest entries, stopping cleanly once hitting the first active session block marker
if ($action === 'get_active_session') {
    $stmt = $pdo->query("SELECT id as rowId, order_id as orderId, timestamp, category, item_name as name, quantity, price, total, status FROM orders ORDER BY id DESC");
    $all_rows = $stmt->fetchAll();
    
    $session_rows = [];
    foreach ($all_rows as $row) {
        if ($row['status'] === 'SESSION_START') {
            break;
        }
        $session_rows[] = $row;
    }
    echo json_encode(array_reverse($session_rows));
    exit;
}

// Flags lines soft deleted with strike through styles in system reporting
if ($action === 'delete_row') {
    $row_id = (int)($_GET['row_id'] ?? 0);
    $stmt = $pdo->prepare("UPDATE orders SET status = 'DELETED' WHERE id = ?");
    $stmt->execute([$row_id]);
    echo json_encode(['status' => 'success']);
    exit;
}

// Modifies quantity structures directly within the itemized bill popup portal database
if ($action === 'modify_qty') {
    $row_id = (int)($_GET['row_id'] ?? 0);
    $change = (int)($_GET['change'] ?? 0);
    
    $stmt = $pdo->prepare("SELECT quantity, price FROM orders WHERE id = ?");
    $stmt->execute([$row_id]);
    $item = $stmt->fetch();
    
    if (!$item) {
        echo json_encode(['status' => 'error']);
        exit;
    }
    
    $new_qty = $item['quantity'] + $change;
    if ($new_qty <= 0) {
        $stmt = $pdo->prepare("UPDATE orders SET status = 'DELETED' WHERE id = ?");
        $stmt->execute([$row_id]);
    } else {
        $new_total = $new_qty * $item['price'];
        $stmt = $pdo->prepare("UPDATE orders SET quantity = ?, total = ? WHERE id = ?");
        $stmt->execute([$new_qty, $new_total, $row_id]);
    }
    echo json_encode(['status' => 'success']);
    exit;
}