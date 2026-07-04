<?php
require_once __DIR__ . '/config/db.php';
$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
$m = $_GET['month'] ?? date('m');
$y = $_GET['year'] ?? date('Y');
$tab = $_GET['tab'] ?? 'overview';

if ($tab === 'bookings') {
    $stmt = $pdo->prepare("SELECT * FROM guests WHERE MONTH(checkin_date) = :m AND YEAR(checkin_date) = :y LIMIT 30 OFFSET :off");
    $stmt->execute([':m' => $m, ':y' => $y, ':off' => $offset]);
    while($g = $stmt->fetch()) {
        echo "<tr>
            <td>".htmlspecialchars($g['guest_name'])."</td>
            <td>".htmlspecialchars($g['booking_source'])."</td>
            <td>".htmlspecialchars($g['phone_number'])."</td>
            <td>".intval($g['no_of_guests'])."</td>
            <td>".date('d M Y', strtotime($g['checkin_date']))."</td>
            <td>".date('d M Y', strtotime($g['checkout_date']))."</td>
            <td>₹".number_format($g['total_charge'], 2)."</td>
            <td>₹".number_format($g['advance_paid'], 2)."</td>
            <td>₹".number_format($g['pending_amount'], 2)."</td>
        </tr>";
    }
}
?>