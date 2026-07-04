<?php
// /home/apartment/artistsfarmjaipur.com/Order/ajax_load_data.php
require_once __DIR__ . '/config/db.php';

$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
$m = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));
$y = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));
$tab = $_GET['tab'] ?? 'overview';
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 30;

if ($tab === 'overview') {
    $bookingIncome = $pdo->query("SELECT COALESCE(SUM(total_charge + decoration_charges + tip_amount), 0) FROM guests WHERE MONTH(checkin_date) = $m AND YEAR(checkin_date) = $y")->fetchColumn();
    $foodIncome = $pdo->query("SELECT COALESCE(SUM(total_food_bill), 0) FROM farm_bookings WHERE MONTH(check_in_date) = $m AND YEAR(check_in_date) = $y")->fetchColumn();
    $farmTotalRevenue = $bookingIncome + $foodIncome;

    $kitchenExpenses = $pdo->query("SELECT COALESCE(SUM(qty * price_per_unit), 0) FROM kitchen_expenses WHERE MONTH(date) = $m AND YEAR(date) = $y")->fetchColumn();
    $farmUpkeep = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM farm_expenses WHERE MONTH(date) = $m AND YEAR(date) = $y AND date != '1970-01-01' AND category != 'Salary'")->fetchColumn();
    $salaries = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM farm_expenses WHERE MONTH(date) = $m AND YEAR(date) = $y AND date != '1970-01-01' AND category = 'Salary'")->fetchColumn();
    
    $totalExpenses = $kitchenExpenses + $farmUpkeep + $salaries;
    $netProfitLoss = $farmTotalRevenue - $totalExpenses;

    $summaryRows = [
        ['Total Income from Bookings', $bookingIncome, '#10b981', '+'],
        ['Total Income from Food Orders', $foodIncome, '#10b981', '+'],
        ['Farm Total Gross Revenue', $farmTotalRevenue, '#06b6d4', ' '],
        ['Total Operational Expenses of Kitchen', $kitchenExpenses, '#ef4444', '-'],
        ['Total Expenses of Farm Upkeep', $farmUpkeep, '#ef4444', '-'],
        ['Total Employee Salaries Disbursed', $salaries, '#ef4444', '-'],
        ['Net Executive Profit / Loss Statement Yield', $netProfitLoss, ($netProfitLoss >= 0 ? '#10b981' : '#ef4444'), ($netProfitLoss >= 0 ? '+' : '-')]
    ];

    foreach ($summaryRows as $row) {
        echo '<tr>
            <td><strong>' . $row[0] . '</strong></td>
            <td style="font-weight: 700; color: ' . $row[2] . ';">' . $row[3] . ' ₹' . number_format($row[1], 2) . '</td>
        </tr>';
    }

} elseif ($tab === 'bookings') {
    $stmt = $pdo->prepare("SELECT * FROM guests WHERE MONTH(checkin_date) = :m AND YEAR(checkin_date) = :y ORDER BY checkin_date DESC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':m', $m, PDO::PARAM_INT); $stmt->bindValue(':y', $y, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT); $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    while ($g = $stmt->fetch()) {
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
            <td>₹' . number_format($g['decoration_charges'], 2) . '</td>
            <td style="color: #f59e0b; font-weight: 600;">₹' . number_format($g['tip_amount'], 2) . '</td>
        </tr>';
    }

} elseif ($tab === 'food_logs') {
    // RESOLVED: Querying directly from farm_bookings to catch historical checkout totals correctly
    $stmt = $pdo->prepare("SELECT booking_source as guest_name, contact_no as phone_number, check_in_date as checkin_date, total_food_bill as total_food, food_received_by FROM farm_bookings WHERE MONTH(check_in_date) = :m AND YEAR(check_in_date) = :y ORDER BY check_in_date DESC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':m', $m, PDO::PARAM_INT); $stmt->bindValue(':y', $y, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT); $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    while ($g = $stmt->fetch()) {
        echo '<tr>
            <td><strong>' . htmlspecialchars($g['guest_name'] ?: 'Offline Guest') . '</strong></td>
            <td>' . htmlspecialchars($g['phone_number'] ?: 'N/A') . '</td>
            <td>' . ($g['checkin_date'] ? date('d M Y', strtotime($g['checkin_date'])) : '-') . '</td>
            <td style="color: #06b6d4; font-weight: 700;">₹' . number_format($g['total_food'], 2) . '</td>
            <td><span class="badge badge-rev">' . htmlspecialchars($g['food_received_by'] ?: 'System Ledger') . '</span></td>
        </tr>';
    }

} elseif ($tab === 'kitchen_expenses') {
    $stmt = $pdo->prepare("SELECT * FROM kitchen_expenses WHERE MONTH(date) = :m AND YEAR(date) = :y ORDER BY date DESC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':m', $m, PDO::PARAM_INT); $stmt->bindValue(':y', $y, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT); $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    while ($k = $stmt->fetch()) {
        echo '<tr>
            <td>' . date('d M Y', strtotime($k['date'])) . '</td>
            <td><span class="badge" style="background:rgba(245,158,11,0.1); color:#f59e0b;">' . htmlspecialchars($k['category']) . '</span></td>
            <td>' . htmlspecialchars($k['description']) . '</td>
            <td><strong>' . htmlspecialchars($k['vendor_name'] ?: 'Other') . '</strong></td>
            <td>' . floatval($k['qty']) . '</td>
            <td>₹' . number_format($k['price_per_unit'], 2) . '</td>
            <td style="font-weight: 700; color: #ef4444;">₹' . number_format($k['qty'] * $k['price_per_unit'], 2) . '</td>
        </tr>';
    }

} elseif ($tab === 'farm_upkeep') {
    $stmt = $pdo->prepare("SELECT * FROM farm_expenses WHERE MONTH(date) = :m AND YEAR(date) = :y AND date != '1970-01-01' AND category != 'Salary' ORDER BY date DESC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':m', $m, PDO::PARAM_INT); $stmt->bindValue(':y', $y, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT); $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    while ($f = $stmt->fetch()) {
        echo '<tr>
            <td>' . date('d M Y', strtotime($f['date'])) . '</td>
            <td><span class="badge" style="background: rgba(16,185,129,0.1); color: #10b981;">' . htmlspecialchars($f['category']) . '</span></td>
            <td>' . htmlspecialchars($f['description']) . '</td>
            <td><strong>' . htmlspecialchars($f['vendor_name'] ?: 'Other') . '</strong></td>
            <td style="font-weight: 700; color: #ef4444;">₹' . number_format($f['amount'], 2) . '</td>
        </tr>';
    }

} elseif ($tab === 'salaries') {
    $stmt = $pdo->prepare("SELECT * FROM farm_expenses WHERE MONTH(date) = :m AND YEAR(date) = :y AND date != '1970-01-01' AND category = 'Salary' ORDER BY date DESC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':m', $m, PDO::PARAM_INT); $stmt->bindValue(':y', $y, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT); $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    while ($s = $stmt->fetch()) {
        echo '<tr>
            <td>' . date('d M Y', strtotime($s['date'])) . '</td>
            <td><span class="badge" style="background: rgba(59,130,246,0.1); color: #3b82f6;">' . htmlspecialchars($s['category']) . '</span></td>
            <td>' . htmlspecialchars($s['description']) . '</td>
            <td><strong>' . htmlspecialchars($s['vendor_name'] ?: 'Employee') . '</strong></td>
            <td style="font-weight: 700; color: #ef4444;">₹' . number_format($s['amount'], 2) . '</td>
        </tr>';
    }
}
?>