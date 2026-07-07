<?php
// /home/apartment/artistsfarmjaipur.com/Order/ajax_load_data.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/config/db.php';

$tab    = $_GET['tab'] ?? 'overview';
$month  = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));
$year   = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));
$limit  = isset($_GET['limit']) ? intval($_GET['limit']) : 31;
$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

if ($tab === 'overview') {
    echo "<tr><td>Accommodations Booking Gross Revenue</td><td><strong>₹" . number_format($totalBookingIncome, 2) . "</strong> <span class='badge badge-rev'>Revenue</span></td></tr>";
    echo "<tr><td>Kitchen Food Orders Gross Revenue</td><td><strong>₹" . number_format($totalFoodIncome, 2) . "</strong> <span class='badge badge-rev'>Revenue</span></td></tr>";
    echo "<tr><td>Kitchen Procurement Inventory Costs</td><td><strong>₹" . number_format($kitchenExpensesSum, 2) . "</strong> <span class='badge badge-exp'>Expense</span></td></tr>";
    echo "<tr><td>Property Operational Bills & Utilities</td><td><strong>₹" . number_format($farmUpkeepExpensesSum, 2) . "</strong> <span class='badge badge-exp'>Expense</span></td></tr>";
    echo "<tr><td>Staff Salaries Gross Disbursed Ledger</td><td><strong>₹" . number_format($totalSalaries, 2) . "</strong> <span class='badge badge-exp'>Expense</span></td></tr>";
}

if ($tab === 'bookings') {
    $stmt = $pdo->prepare("SELECT *, (TO_DAYS(checkout_date) - TO_DAYS(checkin_date)) as total_days FROM guests WHERE MONTH(checkin_date) = :m AND YEAR(checkin_date) = :y ORDER BY id DESC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':m', $month, PDO::PARAM_INT);
    $stmt->bindValue(':y', $year, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>
            <td>" . htmlspecialchars($row['guest_name'] ?? 'Walking Guest') . "</td>
            <td>" . htmlspecialchars($row['booking_source']) . "</td>
            <td>" . htmlspecialchars($row['phone_number']) . "</td>
            <td>" . htmlspecialchars($row['no_of_guests']) . "</td>
            <td>" . htmlspecialchars($row['checkin_date']) . "</td>
            <td>" . htmlspecialchars($row['checkout_date']) . "</td>
            <td>" . htmlspecialchars($row['total_days'] ?? 1) . "</td>
            <td>₹" . number_format($row['per_night_charges'], 2) . "</td>
            <td>₹" . number_format($row['total_charge'], 2) . "</td>
            <td>₹" . number_format($row['advance_paid'], 2) . "</td>
            <td>" . htmlspecialchars($row['advance_received_by']) . "</td>
            <td>₹" . number_format($row['pending_amount'], 2) . "</td>
            <td>" . htmlspecialchars($row['pending_received_by']) . "</td>
            <td>₹" . number_format($row['decoration_charges'], 2) . "</td>
            <td>₹" . number_format($row['tip_amount'], 2) . "</td>
        </tr>";
    }
}

if ($tab === 'food_logs') {
    $stmt = $pdo->prepare("SELECT * FROM farm_bookings WHERE MONTH(check_in_date) = :m AND YEAR(check_in_date) = :y ORDER BY id DESC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':m', $month, PDO::PARAM_INT);
    $stmt->bindValue(':y', $year, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>
            <td>Order Roll Ticket #" . $row['id'] . "</td>
            <td>" . htmlspecialchars($row['contact_no']) . "</td>
            <td>" . htmlspecialchars($row['check_in_date']) . "</td>
            <td><strong>₹" . number_format($row['total_food_bill'], 2) . "</strong></td>
            <td>" . htmlspecialchars($row['food_received_by'] ?? 'POS Cashier') . "</td>
        </tr>";
    }
}

if ($tab === 'kitchen_expenses') {
    $stmt = $pdo->prepare("SELECT *, (qty * price_per_unit) as calculated_total FROM kitchen_expenses WHERE MONTH(date) = :m AND YEAR(date) = :y ORDER BY date DESC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':m', $month, PDO::PARAM_INT);
    $stmt->bindValue(':y', $year, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $display_name = $row['item_detail'] ?? $row['item_name'] ?? $row['description'] ?? 'Procurement Item';
        echo "<tr>
            <td>" . htmlspecialchars($row['date']) . "</td>
            <td>" . htmlspecialchars($row['category']) . "</td>
            <td>" . htmlspecialchars($display_name) . "</td>
            <td>" . htmlspecialchars($row['vendor_name'] ?? $row['vendor'] ?? 'Market') . "</td>
            <td>" . htmlspecialchars($row['qty']) . "</td>
            <td>₹" . number_format($row['price_per_unit'], 2) . "</td>
            <td><strong>₹" . number_format($row['calculated_total'], 2) . "</strong></td>
        </tr>";
    }
}

if ($tab === 'farm_upkeep') {
    // Combine old farm_expenses and new farm_utility_expenses rows for non-salaries
    $stmt = $pdo->prepare("
        SELECT date as recorded_date, category, description, vendor_name, amount FROM farm_expenses 
        WHERE MONTH(date) = :m AND YEAR(date) = :y AND category NOT IN ('Salary', 'Salaries')
        UNION ALL
        SELECT expense_date as recorded_date, category, description, vendor_name, amount FROM farm_utility_expenses 
        WHERE MONTH(expense_date) = :m2 AND YEAR(expense_date) = :y2 AND category IN ('Bills', 'Other', 'Utility', 'Water Tanker', 'Maintenance', 'Rations')
        ORDER BY recorded_date DESC LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':m', $month, PDO::PARAM_INT);
    $stmt->bindValue(':y', $year, PDO::PARAM_INT);
    $stmt->bindValue(':m2', $month, PDO::PARAM_INT);
    $stmt->bindValue(':y2', $year, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>
            <td>" . htmlspecialchars($row['recorded_date']) . "</td>
            <td>" . htmlspecialchars($row['category']) . "</td>
            <td>" . htmlspecialchars($row['description']) . "</td>
            <td>" . htmlspecialchars($row['vendor_name'] ?? 'Other') . "</td>
            <td><strong>₹" . number_format($row['amount'], 2) . "</strong></td>
        </tr>";
    }
}

if ($tab === 'salaries') {
    // Combine old farm_expenses and new farm_utility_expenses rows for salaries
    $stmt = $pdo->prepare("
        SELECT date as recorded_date, category, description, vendor_name, amount FROM farm_expenses 
        WHERE MONTH(date) = :m AND YEAR(date) = :y AND category IN ('Salary', 'Salaries')
        UNION ALL
        SELECT expense_date as recorded_date, category, description, vendor_name, amount FROM farm_utility_expenses 
        WHERE MONTH(expense_date) = :m2 AND YEAR(expense_date) = :y2 AND category = 'Salaries'
        ORDER BY recorded_date DESC LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':m', $month, PDO::PARAM_INT);
    $stmt->bindValue(':y', $year, PDO::PARAM_INT);
    $stmt->bindValue(':m2', $month, PDO::PARAM_INT);
    $stmt->bindValue(':y2', $year, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>
            <td>" . htmlspecialchars($row['recorded_date']) . "</td>
            <td><span class='badge' style='background:#f0fdf4; color:#166534;'>Salaries</span></td>
            <td>" . htmlspecialchars($row['description']) . "</td>
            <td>" . htmlspecialchars($row['vendor_name'] ?? 'Teammate') . "</td>
            <td><strong>₹" . number_format($row['amount'], 2) . "</strong></td>
        </tr>";
    }
}