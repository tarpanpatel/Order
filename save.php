<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');

// ⚠️ PASTE YOUR COPIED GOOGLE APPS SCRIPT WEB APP DEPLOYMENT URL STRING HERE
$google_script_url = "https://script.google.com/macros/s/AKfycbxXnEepbFDYwOYO2hVcqyguihNEdl0YuoMPjCzi7Qx6x0kcgVOoYdFjZYzVmtikdTsi6A/exec";

$action = $_GET['action'] ?? '';

// 1. FORWARD THE MENU REQUEST (GET)
if ($action === 'get_menu') {
    $response = file_get_contents($google_script_url . "?action=get_menu");
    echo $response;
    exit;
}

// 2. FORWARD NEW ENTRIES, CHECK-INS, & CHECK-OUTS (POST)
if ($action === 'save_order') {
    $input_payload = file_get_contents('php://input');
    
    $options = [
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\n",
            'content' => $input_payload,
            'follow_location' => 1
        ]
    ];
    
    $context  = stream_context_create($options);
    $result = file_get_contents($google_script_url, false, $context);
    echo $result;
    exit;
}

// 3. FORWARD THE DAY LOG VIEW REQUESTS (GET)
if ($action === 'get_day_log') {
    $target_date = $_GET['date'] ?? '';
    $response = file_get_contents($google_script_url . "?action=get_day_log&date=" . urlencode($target_date));
    echo $response;
    exit;
}

// 4. FORWARD ACTIVE BILL CALCULATIONS (GET)
if ($action === 'get_active_session') {
    $response = file_get_contents($google_script_url . "?action=get_active_session");
    echo $response;
    exit;
}

// 5. FORWARD SOFT DELETIONS / CANCEL ITEMS (GET)
if ($action === 'delete_row') {
    $row_id = $_GET['row_id'] ?? '';
    $response = file_get_contents($google_script_url . "?action=delete_row&row_id=" . urlencode($row_id));
    echo $response;
    exit;
}

// 6. FORWARD LIVE QTY ADJUSTMENTS INSIDE THE BILL OVERLAY (GET)
if ($action === 'modify_qty') {
    $row_id = $_GET['row_id'] ?? '';
    $change = $_GET['change'] ?? '';
    $response = file_get_contents($google_script_url . "?action=modify_qty&row_id=" . urlencode($row_id) . "&change=" . urlencode($change));
    echo $response;
    exit;
}

// Catch-all safety fallback response
echo json_encode(['status' => 'error', 'message' => 'Invalid action parameter specified']);
exit;