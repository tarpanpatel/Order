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
    // Re-calculate Summary Statement Metrics cleanly
    $bookingIncomeStmt = $pdo->prepare("SELECT COALESCE(SUM(total_charge + decoration_charges + tip_amount), 0) FROM guests WHERE MONTH(checkin_date) = :m AND YEAR(checkin_date) = :y");
    $bookingIncomeStmt->execute([':m' => $month, ':y' => $year]);
    $bookingIncome = $bookingIncomeStmt->fetchColumn();

    $foodIncomeStmt = $pdo->prepare("SELECT COALESCE(SUM(total_food_bill), 0) FROM farm_bookings WHERE MONTH(check_in_date) = :m AND YEAR(check_in_date) = :y");
    $foodIncomeStmt->execute([':m' => $month, ':y' => $year]);
    $foodIncome = $foodIncomeStmt->fetchColumn();

    $kitExpStmt = $pdo->prepare("SELECT COALESCE(SUM(qty * price_per_unit), 0) FROM kitchen_expenses WHERE MONTH(date) = :m AND YEAR(date) = :y");
    $kitExpStmt->execute([':m' => $month, ':y' => $year]);
    $kitchenExpenses = $kitExpStmt->fetchColumn();

    $upkeepStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM farm_utility_expenses WHERE MONTH(expense_date) = :m AND YEAR(expense_date) = :y AND category = 'Bills'");
    $upkeepStmt->execute([':m' => $month, ':y' => $year]);
    $upkeepExpenses = $upkeepStmt->fetchColumn();

    $salaryStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM farm_utility_expenses WHERE MONTH(expense_date) = :m AND YEAR(expense_date) = :y AND category = 'Salaries'");
    $salaryStmt->execute([':m' => $month, ':y' => $year]);
    $salaryExpenses = $salaryStmt->fetchColumn();

    echo "<tr><td>Accommodations Booking Gross Revenue</td><td><strong>₹" . number_format($bookingIncome, 2) . "</strong> <span class='badge badge-rev'>Revenue</span></td></tr>";
    echo "<tr><td>Kitchen Food Orders Gross Revenue</td><td><strong>₹" . number_format($foodIncome, 2) . "</strong> <span class='badge badge-rev'>Revenue</span></td></tr>";
    echo "<tr><td>Kitchen Procurement Inventory Costs</td><td><strong>₹" . number_format($kitchenExpenses, 2) . "</strong> <span class='badge badge-exp'>Expense</span></td></tr>";
    echo "<tr><td>Property Operational Bills & Utilities</td><td><strong>₹" . number_format($upkeepExpenses, 2) . "</strong> <span class='badge badge-exp'>Expense</span></td></tr>";
    echo "<tr><td>Staff Salaries Gross Disbursed Ledger</td><td><strong>₹" . number_format($salaryExpenses, 2) . "</strong> <span class='badge badge-exp'>Expense</span></td></tr>";
}

if ($tab === 'bookings') {
    $stmt = $pdo->prepare("SELECT * FROM guests WHERE MONTH(checkin_date) = :m AND YEAR(checkin_date) = :y ORDER BY id DESC LIMIT :limit OFFSET :offset");
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
            <td>" . (strtotime($row['checkout_date']) - strtotime($row['checkin_date'])) / 86400 . "</td>
            <td>₹" . number_format($row['per_night_charges'], 2) . "</td>
            <td>₹" . number_format($row['total_charge'], 2) . "</td>
            <td>₹" . number_format($row['advance_paid'], 2) . "</td>
            <td>" . htmlspecialchars($row['advance_received_by']) . "</td>
            <td>₹" . number_format($row['pending_amount'], 2) . "</td>
            <td>" . htmlspecialchars($row['pending_received_by']) . "</td>
            <td>₹" . number_format($row['decoration_charges'] ?? 0, 2) . "</td>
            <td>₹" . number_format($row['tip_amount'] ?? 0, 2) . "</td>
        </tr>";
    }
}

if ($tab === 'salaries') {
    // Pull from consolidated farm_utility_expenses using the structural category 'Salaries'
    $stmt = $pdo->prepare("SELECT * FROM farm_utility_expenses WHERE category = 'Salaries' AND MONTH(expense_date) = :m AND YEAR(expense_date) = :y ORDER BY expense_date DESC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':m', $month, PDO::PARAM_INT);
    $stmt->bindValue(':y', $year, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>
            <td>" . htmlspecialchars($row['expense_date']) . "</td>
            <td><span class='badge badge-exp'>" . htmlspecialchars($row['category']) . "</span></td>
            <td>" . htmlspecialchars($row['description']) . "</td>
            <td>" . htmlspecialchars($row['vendor_name']) . "</td>
            <td><strong>₹" . number_format($row['amount'], 2) . "</strong></td>
        </tr>";
    }
}

if ($tab === 'farm_upkeep') {
    // Pull bills and dynamic other categories
    $stmt = $pdo->prepare("SELECT * FROM farm_utility_expenses WHERE category IN ('Bills', 'Other') AND MONTH(expense_date) = :m AND YEAR(expense_date) = :y ORDER BY expense_date DESC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':m', $month, PDO::PARAM_INT);
    $stmt->bindValue(':y', $year, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>
            <td>" . htmlspecialchars($row['expense_date']) . "</td>
            <td><span class='badge' style='background:#f1f5f9; color:#475569;'>" . htmlspecialchars($row['category']) . "</span></td>
            <td>" . htmlspecialchars($row['description']) . "</td>
            <td>" . htmlspecialchars($row['vendor_name'] ?: 'N/A') . "</td>
            <td><strong>₹" . number_format($row['amount'], 2) . "</strong></td>
        </tr>";
    }
}