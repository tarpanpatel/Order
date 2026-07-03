<?php
// ==========================================================================
// SYSTEM TELEGRAM INTERNAL INTERCEPTOR BRIDGE
// ==========================================================================

/**
 * Forwards receipt text payloads to the local whitelisted proxy script path
 * @param string $message
 * @return bool
 */
function sendTelegramNotification($message) {
    // Dynamically maps matching internal host pathways
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $url = $protocol . $host . "/Order/api/send_request.php";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['text' => $message]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return $response !== false;
}