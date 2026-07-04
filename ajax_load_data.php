<?php
require_once __DIR__ . '/config/db.php';

$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
$m = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));
$y = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'overview';
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 30;

if ($tab === 'bookings') {
    $stmt = $pdo->prepare("SELECT * FROM guests WHERE MONTH(checkin_date) = :m AND YEAR(checkin_date) = :y ORDER BY checkin_date DESC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':m', $m, PDO::PARAM_INT);
    $stmt->bindValue(':y', $y, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $guestRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($guestRows) && $offset === 0) {
        echo '<tr><td colspan="17" style="text-align: center; color: #94a3b8;">No room registrations or food records indexed for this selection period.</td></tr>';
    } else {
        foreach ($guestRows as $g) {
            echo '<tr>
                <td><strong>' . htmlspecialchars($g['guest_name'] ?: 'Unnamed') . '</strong></td>
                <td>' . htmlspecialchars($g['booking_source'] ?: 'Offline') . '</td>
                <td>' . htmlspecialchars($g['phone_number'] ?: '0000000000') . '</td>
                <td style="text-align: center;">' . intval($g['no_of_guests']) . '</td>
                <td>' . ($g['checkin_date'] ? date('d M Y', strtotime($g['checkin_date'])) : '-') . '</td>
                <td>' . ($g['checkout_date'] ? date('d M Y', strtotime($g['checkout_date'])) : '-') . '</td>
                <td style="text-align: center;">' . intval($g['total_days']) . '</td>
                <td>₹' . number_format($g['per_night_charges'], 2) . '</td>
                <td style="font-weight: 600;">₹' . number_format($g['total_charge'], 2) . '</td>
                <td style="color: #10b981; font-weight: 600;">₹' . number_format($g['advance_paid'], 2) . '</td>
                <td><span class="badge" style="background: #f1f5f9; color: #475569;">' . htmlspecialchars($g['advance_received_by'] ?: 'Unnamed') . '</span></td>
                <td style="color: #ef4444; font-weight: 600;">₹' . number_format($g['pending_amount'], 2) . '</td>
                <td><span class="badge" style="background: #f1f5f9; color: #475569;">' . htmlspecialchars($g['pending_received_by'] ?: 'Unnamed') . '</span></td>
                <td style="color: #06b6d4; font-weight: 700;">₹' . number_format($g['total_food'], 2) . '</td>
                <td><span class="badge badge-rev">' . htmlspecialchars($g['food_received_by'] ?: 'Unnamed') . '</span></td>
                <td>₹' . number_format($g['decoration_charges'], 2) . '</td>
                <td style="color: #f59e0b; font-weight: 600;">₹' . number_format($g['tip_amount'], 2) . '</td>
            </tr>';
        }
    }
}
?>