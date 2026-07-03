<?php
// ==========================================================================
// WHITELISTED TELEGRAM OUTBOUND GATEWAY ENGINE
// ==========================================================================
ini_set('display_errors', 0); 
error_reporting(E_ALL);

// Your exact verified active bot credentials
define('TELEGRAM_BOT_TOKEN', '8999394059:AAHGKM4gFvH6IIQtOEiuiKEL7ewflHSa6DU'); 
define('TELEGRAM_CHAT_ID', '-5456387701');                                    

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['text'])) {
    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage";
    
    $data = [
        'chat_id'    => TELEGRAM_CHAT_ID,
        'text'       => $_POST['text'],
        'parse_mode' => 'Markdown'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    header('Content-Type: application/json');
    echo json_encode(['success' => $response !== false]);
    exit;
} else {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'Missing parameter packet.']);
    exit;
}