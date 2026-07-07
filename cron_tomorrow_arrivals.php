<?php
// /home/apartment/artistsfarmjaipur.com/Order/cron_tomorrow_arrivals.php

// Prevent unauthorized browser execution - can only run via command line or cron
if (php_sapi_name() !== 'cli' && isset($_SERVER['REMOTE_ADDR'])) {
    die("Security Exception: Access Denied.");
}

require_once __DIR__ . "/config/db.php";

if (file_exists(__DIR__ . "/config/telegram.php")) {
    require_once __DIR__ . "/config/telegram.php";
}

if (!function_exists('sendAdminTelegramMessage')) {
    die("Error: Telegram notification engine function missing.");
}

try {
    // Fetch all bookings where the check-in date is tomorrow
    $stmt = $pdo->prepare("
        SELECT guest_name, phone_number, adults, children, DATE_FORMAT(checkin_date, '%h:%i %p') as arrival_time, notes 
        FROM guests 
        WHERE DATE(checkin_date) = CURRENT_DATE() + INTERVAL 1 DAY
    ");
    $stmt->execute();
    $tomorrow_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If bookings exist for tomorrow, construct and dispatch a single message summary
    if (!empty($tomorrow_bookings)) {
        $tg_msg = "🛎️ <b>UPCOMING ARRIVALS TOMORROW</b>\n";
        $tg_msg .= "━━━━━━━━━━━━━━━━━━\n";
        
        foreach ($tomorrow_bookings as $booking) {
            $tg_msg .= "👤 <b>Guest:</b> " . htmlspecialchars($booking['guest_name']) . "\n";
            $tg_msg .= "📱 <b>Phone:</b> " . htmlspecialchars($booking['phone_number']) . "\n";
            $tg_msg .= "👥 <b>Pax:</b> " . intval($booking['adults']) . " Adults";
            if ($booking['children'] > 0) {
                $tg_msg .= ", " . intval($booking['children']) . " Children";
            }
            $tg_msg .= "\n🕒 <b>Est. Time:</b> " . $booking['arrival_time'] . "\n";
            if (!empty($booking['notes'])) {
                $tg_msg .= "📝 <b>Notes:</b> " . htmlspecialchars($booking['notes']) . "\n";
            }
            $tg_msg .= "━━━━━━━━━━━━━━━━━━\n";
        }
        
        sendAdminTelegramMessage($tg_msg);
        echo "Telegram summary dispatched successfully for " . count($tomorrow_bookings) . " booking(s).\n";
    } else {
        echo "No upcoming arrivals found for tomorrow.\n";
    }

} catch (PDOException $e) {
    echo "Cron Execution Error: " . $e->getMessage() . "\n";
}