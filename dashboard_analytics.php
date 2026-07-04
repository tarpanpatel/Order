<?php
// /home/apartment/artistsfarmjaipur.com/Order/dashboard_analytics.php

if (session_status() === PHP_SESSION_NONE) { session_start(); }

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

if (empty($availableMonths)) {
    $availableMonths[] = ['m' => intval(date('m')), 'y' => intval(date('Y'))];
}

// 3. Fetch Financial Summary Data for the Chosen Month
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

// Detect if this page is loaded via the AJAX single-page routine engine
$is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') || isset($_GET['ajax']);

if (!$is_ajax) {
    include 'includes/header.php';
}
?>

<div class="main-content" style="padding: 12px; width: 100%;">
    
    <style>
        .analytics-title { color: #2c3e50; font-family: 'Segoe UI', Arial, sans-serif; margin-bottom: 5px; }
        .filter-bar { background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); margin-bottom: 20px; display: flex; align-items: center; gap: 10px; font-family: 'Segoe UI', Arial, sans-serif; }
        .filter-bar select { padding: 8px 12px; font-size: 14px; border-radius: 4px; border: 1px solid #ccc; background: #fff; }
        .filter-bar button { padding: 8px 15px; font-size: 14px; background: #007bff; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .filter-bar button:hover { background: #0056b3; }
        .analytics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; font-family: 'Segoe UI', Arial, sans-serif; }
        .analytics-card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .analytics-card h3 { margin: 0 0 10px 0; color: #7f8c8d; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; }
        .analytics-card .value { font-size: 26px; font-weight: bold; }
        .revenue-card { border-left: 5px solid #28a745; }
        .revenue-card .value { color: #28a745; }
        .expenses-card { border-left: 5px solid #dc3545; }
        .expenses-card .value { color: #dc3545; }
        .profit-card { border-left: 5px solid #17a2b8; }
        .profit-card .value { color: #17a2b8; }
        .ledger-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.05); margin-bottom: 30px; font-family: 'Segoe UI', Arial, sans-serif; }
        .ledger-table th, .ledger-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; }
        .ledger-table th { background: #343a40; color: #fff; font-weight: 600; }
        .ledger-table tr:hover { background-color: #f8f9fa; }
    </style>

    <h2 class="analytics-title">Operational Overview</h2>
    
    <form method="GET" action="dashboard_analytics.php" class="filter-bar" id="analyticsFilterForm">
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

    <div class="analytics-grid">
        <div class="analytics-card revenue-card">
            <h3>Total Earnings (Stay + Food)</h3>
            <div class="value">₹<?php echo number_format($revenue, 2); ?></div>
        </div>
        <div class="analytics-card expenses-card">
            <h3>Total Expenses (Farm + Kitchen)</h3>
            <div class="value">₹<?php echo number_format($expenses, 2); ?></div>
        </div>
        <div class="analytics-card profit-card">
            <h3>Net Operational Profit</h3>
            <div class="value">₹<?php echo number_format($profit, 2); ?></div>
        </div>
    </div>

    <h3 class="analytics-title">Kitchen Vendor Distributions</h3>
    <table class="ledger-table">
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
    function splitPeriodValue(val) {
        if(!val) return;
        var parts = val.split('-');
        document.getElementById('hidden_month').value = parts[0];
        document.getElementById('hidden_year').value = parts[1];
    }
    
    var initialSelect = document.getElementById('date_select');
    if(initialSelect) {
        splitPeriodValue(initialSelect.value);
    }
    </script>
</div>

<?php
if (!$is_ajax) {
    include 'includes/footer.php';
}
?>