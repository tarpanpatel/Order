<?php
// /home/apartment/artistsfarmjaipur.com/Order/config/telegram.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Keep your existing token or change if you use two distinct bots
define('TELEGRAM_BOT_TOKEN', '8999394059:AAHGKM4gFvH6IIQtOEiuiKEL7ewflHSa6DU'); 

// The original chat group for kitchen KOT orders and requisitions
define('TELEGRAM_KITCHEN_CHAT_ID', '-5456387701'); 

// NEW: The separate chat group ID for Expenses, Checkouts, and Booking Reminders
define('TELEGRAM_ADMIN_CHAT_ID', '-5415746187'); 

/**
 * Sends messages to the primary Kitchen/Requisitions group
 */
if (!function_exists('sendTelegramMessage')) {
    function sendTelegramMessage($message) {
        $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage";
        $data = [
            'chat_id' => TELEGRAM_KITCHEN_CHAT_ID,
            'text' => $message,
            'parse_mode' => 'HTML'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_exec($ch);
        curl_close($ch);
    }
}

/**
 * NEW: Sends operational financial/admin alerts to the dedicated Admin Group
 */
if (!function_exists('sendAdminTelegramMessage')) {
    function sendAdminTelegramMessage($message) {
        $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage";
        $data = [
            'chat_id' => TELEGRAM_ADMIN_CHAT_ID,
            'text' => $message,
            'parse_mode' => 'HTML'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_exec($ch);
        curl_close($ch);
    }
}