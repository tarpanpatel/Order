<?php
// /home/apartment/artistsfarmjaipur.com/Order/dashboard_analytics.php

// Include your standard database connection
include_once __DIR__ . '/config/db.php'; 

// 1. Determine Selected Month and Year (Defaults to current month/year if not set)
$selectedMonth = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));
$selectedYear  = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));

// 2. Fetch All Unique Months/Years From the Database for the Filter Menu
$monthsQuery = "
    SELECT DISTINCT MONTH(check_in_date) as m, YEAR(check_in_date) as y FROM farm_bookings
    UNION
    SELECT DISTINCT MONTH(date) as m, YEAR(date) as y FROM farm_expenses
    UNION
    SELECT DISTINCT MONTH(date) as m, YEAR(date) as y FROM kitchen_expenses
    ORDER BY y DESC, m DESC
";
$monthsStmt = $pdo->query($monthsQuery);
$availableMonths = $monthsStmt->fetchAll(PDO::FETCH_ASSOC);

// If no data exists yet, ensure the dropdown has at least the current month
if (empty($availableMonths)) {
    $availableMonths[] = ['m' => intval(date('m')), 'y' => intval(date('Y'))];
}

// 3. Fetch Financial Summary Data for the Chosen Month (Fixes SQLSTATE[HY093] parameter reuse bug)
$summaryQuery = "
    SELECT 
        COALESCE(SUM(total_charge + total_food_bill + decoration_charges + tip_amount), 0) AS gross_revenue,
        (SELECT COALESCE(SUM(amount), 0) FROM farm_expenses WHERE MONTH(date) = :farm_month AND YEAR(date) = :farm_year) AS farm_exp,
        (SELECT COALESCE(SUM(total_amount), 0) FROM kitchen_expenses WHERE MONTH(date) = :kit_month AND YEAR(date) = :kit_year) AS kitchen_exp
    FROM farm_bookings
    WHERE MONTH(check_in_date) = :book_month AND YEAR(check_in_date) = :book_year
";

$stmt = $pdo->prepare($summaryQuery);
$stmt->execute([
    ':farm_month'  => $selectedMonth, 
    ':farm_year'   => $selectedYear,
    ':kit_month'   => $selectedMonth, 
    ':kit_year'    => $selectedYear,
    ':book_month'  => $selectedMonth, 
    ':book_year'   => $selectedYear
]);
$metrics = $stmt->fetch(PDO::FETCH_ASSOC);

$revenue = $metrics['gross_revenue'];
$expenses = $metrics['farm_exp'] + $metrics['kitchen_exp'];
$profit = $revenue - $expenses;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farm Operations Dashboard</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f6f9; margin: 20px; color: #333; }
        h2, h3 { color: #2c3e50; }
        .filter-bar { background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .filter-bar select { padding: 8px 12px; font-size: 14px; border-radius: 4px; border: 1px solid #ccc; background: #fff; }
        .filter-bar button { padding: 8px 15px; font-size: 14px; background: #007bff; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .filter-bar button:hover { background: #0056b3; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .card h3 { margin: 0 0 10px 0; color: #7f8c8d; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; }
        .card .value { font-size: 26px; font-weight: bold; }
        .revenue { border-left: 5px solid #28a745; }
        .revenue .value { color: #28a745; }
        .expenses { border-left: 5px solid #dc3545; }
        .expenses .value { color: #dc3545; }
        .profit { border-left: 5px solid #17a2b8; }
        .profit .value { color: #17a2b8; }
        table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.05); margin-bottom: 30px; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #343a40; color: #fff; font-weight: 600; }
        tr:hover { background-color: #f8f9fa; }
    </style>
</head>
<body>

    <h2>Operational Overview</h2>
    
    <!-- Dynamic Month & Year Filter Form -->
    <form method="GET" class="filter-bar">
        <label for="date_select"><strong>Select View Period:</strong></label>
        <select id="date_select" onchange="splitPeriodValue(this.value)">
            <?php
            foreach ($availableMonths as $row) {
                if (empty($row['m']) || empty($row['y'])) continue;
                $dateObj = DateTime::createFromFormat('!m', $row['m']);
                $monthName = $dateObj->format('F');
                $optionValue = $row['m'] . '-' . $row['y'];
                $isSelected = ($row['m'] == $selectedMonth && $row['y'] == $selectedYear) ? 'selected' : '';
                echo "<option value='{$optionValue}' {$isSelected}>{$monthName} {$row['y']}</option>";
            }
            ?>
        </select>
        <input type="hidden" name="month" id="hidden_month" value="<?php echo $selectedMonth; ?>">
        <input type="hidden" name="year" id="hidden_year" value="<?php echo $selectedYear; ?>">
        <button type="submit">Filter Metrics</button>
    </form>

    <!-- Financial Metrics Grid -->
    <div class="grid">
        <div class="card revenue">
            <h3>Total Earnings (Stay + Food)</h3>
            <div class="value">₹<?php echo number_format($revenue, 2); ?></div>
        </div>
        <div class="card expenses">
            <h3>Total Expenses (Farm + Kitchen)</h3>
            <div class="value">₹<?php echo number_format($expenses, 2); ?></div>
        </div>
        <div class="card profit">
            <h3>Net Operational Profit</h3>
            <div class="value">₹<?php echo number_format($profit, 2); ?></div>
        </div>
    </div>

    <!-- Vendor Distribution Ledger Table -->
    <h3>Kitchen Vendor Distributions</h3>
    <table>
        <thead>
            <tr>
                <th>Vendor Name</th>
                <th>Total Transaction Logs</th>
                <th>Total Outstanding Payout</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $vendorQuery = "
                SELECT vendor_name, COUNT(*) as logs, SUM(total_amount) as total 
                FROM kitchen_expenses 
                WHERE MONTH(date) = :month AND YEAR(date) = :year
                GROUP BY vendor_name ORDER BY total DESC
            ";
            $vendorStmt = $pdo->prepare($vendorQuery);
            $vendorStmt->execute([':month' => $selectedMonth, ':year' => $selectedYear]);
            
            $hasVendors = false;
            while($vendor = $vendorStmt->fetch(PDO::FETCH_ASSOC)) {
                $hasVendors = true;
                $displayVendor = !empty($vendor['vendor_name']) ? htmlspecialchars($vendor['vendor_name']) : 'Unassigned / Generic';
                echo "<tr>
                        <td>" . $displayVendor . "</td>
                        <td>" . $vendor['logs'] . "</td>
                        <td>₹" . number_format($vendor['total'], 2) . "</td>
                      </tr>";
            }
            if (!$hasVendors) {
                echo "<tr><td colspan='3' style='text-align:center; color:#999;'>No kitchen expense logs found for this period.</td></tr>";
            }
            ?>
        </tbody>
    </table>

    <script>
    // JavaScript helper to unpack the dropdown value into structured HTTP parameters
    function splitPeriodValue(val) {
        if(!val) return;
        var parts = val.split('-');
        document.getElementById('hidden_month').value = parts[0];
        document.getElementById('hidden_year').value = parts[1];
    }
    
    // Ensure accurate value parsing immediately on runtime initialization
    var initialSelect = document.getElementById('date_select');
    if(initialSelect) {
        splitPeriodValue(initialSelect.value);
    }
    </script>

</body>
</html>