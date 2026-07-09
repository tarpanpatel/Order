<?php
// Report all PHP errors
error_reporting(E_ALL);

// Force errors to be displayed on the screen
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
// /home/apartment/artistsfarmjaipur.com/Order/dashboard_analytics.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/config/db.php'; 

// 1. Set Active Filtering States (Defaults to current month/year)
$selectedMonth = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));
$selectedYear  = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));
$activeTab     = isset($_GET['tab']) ? $_GET['tab'] : 'overview';

// --- AJAX PAGINATION INTERCEPTOR PIPE ---
if (isset($_GET['action_ajax_load_more'])) {
    $_GET['limit'] = 30;
    include 'ajax_load_data.php';
    exit;
}

// 2. Fetch Available Date Ranges Dynamically across tables to build filters (FIXED: Removed farm_bookings)
$filterDates = $pdo->query("
    SELECT DISTINCT MONTH(checkin_date) as m, YEAR(checkin_date) as y FROM guests WHERE checkin_date IS NOT NULL
    UNION 
    SELECT DISTINCT MONTH(date) as m, YEAR(date) as y FROM kitchen_expenses WHERE date IS NOT NULL
    UNION 
    SELECT DISTINCT MONTH(date) as m, YEAR(date) as y FROM farm_expenses WHERE date IS NOT NULL
    UNION
    SELECT DISTINCT MONTH(expense_date) as m, YEAR(expense_date) as y FROM farm_utility_expenses WHERE expense_date IS NOT NULL
    ORDER BY y DESC, m DESC
")->fetchAll(PDO::FETCH_ASSOC);

if (empty($filterDates)) {
    $filterDates[] = ['m' => intval(date('m')), 'y' => intval(date('Y'))];
}

// 3. COMPUTE EXECUTIVE FINANCIAL METRICS COMBINING LEGACY AND NEW RECORDS
$bookingIncomeStmt = $pdo->prepare("SELECT COALESCE(SUM(total_charge + decoration_charges + tip_amount), 0) FROM guests WHERE MONTH(checkin_date) = :m AND YEAR(checkin_date) = :y");
$bookingIncomeStmt->execute([':m' => $selectedMonth, ':y' => $selectedYear]);
$totalBookingIncome = $bookingIncomeStmt->fetchColumn();

// FIXED: Sourcing Food Revenue directly from guests.total_food column
$foodIncomeStmt = $pdo->prepare("SELECT COALESCE(SUM(total_food), 0) FROM guests WHERE MONTH(checkin_date) = :m AND YEAR(checkin_date) = :y");
$foodIncomeStmt->execute([':m' => $selectedMonth, ':y' => $selectedYear]);
$totalFoodIncome = $foodIncomeStmt->fetchColumn();

$farmTotalRevenue = $totalBookingIncome + $totalFoodIncome;

// Kitchen Procurement sum
$kitExpStmt = $pdo->prepare("SELECT COALESCE(SUM(qty * price_per_unit), 0) FROM kitchen_expenses WHERE MONTH(date) = :m AND YEAR(date) = :y");
$kitExpStmt->execute([':m' => $selectedMonth, ':y' => $selectedYear]);
$kitchenExpensesSum = $kitExpStmt->fetchColumn();

// UNIFIED UPKEEP: Pull non-salary entries from legacy table + 'Bills' and 'Other' from new table
$upkeepLegacyStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM farm_expenses WHERE MONTH(date) = :m AND YEAR(date) = :y AND date != '1970-01-01' AND category NOT IN ('Salary', 'Salaries')");
$upkeepLegacyStmt->execute([':m' => $selectedMonth, ':y' => $selectedYear]);
$upkeepLegacy = $upkeepLegacyStmt->fetchColumn();

$upkeepNewStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM farm_utility_expenses WHERE MONTH(expense_date) = :m AND YEAR(expense_date) = :y AND category IN ('Bills', 'Other', 'Utility', 'Water Tanker', 'Maintenance', 'Rations')");
$upkeepNewStmt->execute([':m' => $selectedMonth, ':y' => $selectedYear]);
$upkeepNew = $upkeepNewStmt->fetchColumn();

$farmUpkeepExpensesSum = $upkeepLegacy + $upkeepNew;

// UNIFIED SALARIES: Pull both 'Salary' and 'Salaries' across both tables
$salaryLegacyStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM farm_expenses WHERE MONTH(date) = :m AND YEAR(date) = :y AND date != '1970-01-01' AND category IN ('Salary', 'Salaries')");
$salaryLegacyStmt->execute([':m' => $selectedMonth, ':y' => $selectedYear]);
$salaryLegacy = $salaryLegacyStmt->fetchColumn();

$salaryNewStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM farm_utility_expenses WHERE MONTH(expense_date) = :m AND YEAR(expense_date) = :y AND category = 'Salaries'");
$salaryNewStmt->execute([':m' => $selectedMonth, ':y' => $selectedYear]);
$salaryNew = $salaryNewStmt->fetchColumn();

$totalSalaries = $salaryLegacy + $salaryNew;

$totalExpenses = $kitchenExpensesSum + $farmUpkeepExpensesSum + $totalSalaries;
$netProfitLoss = $farmTotalRevenue - $totalExpenses;

// 4. AJAX ROUTING CHECK INTERCEPTOR
$is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') || isset($_GET['ajax']);
if (!$is_ajax) { include 'includes/header.php'; }
?>

<div class=" " style="padding: 12px; width: 100%; max-width: 100%; box-sizing: border-box; overflow-x: hidden; font-family: 'Segoe UI', Helvetica, Arial, sans-serif;">
    
    <style>
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .page-title { font-size: 20px; font-weight: 700; color: #1e293b; margin: 0; }
        .filter-form { display: flex; gap: 10px; align-items: center; background: #fff; padding: 10px 15px; border-radius: 8px; border: 1px solid #e2e8f0; }
        .filter-form select { padding: 6px 12px; border-radius: 6px; border: 1px solid #cbd5e0; background: #fff; font-size: 13px; }
        .filter-form button { padding: 6px 14px; background: #06b6d4; color: #fff; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 13px; }
        
        .metrics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .metric-card { background: #fff; padding: 16px; border-radius: 10px; border: 1px solid #e2e8f0; border-left: 4px solid #cbd5e0; text-align: left; }
        .metric-card h3 { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; margin: 0 0 8px 0; letter-spacing: 0.5px; }
        .metric-card .value { font-size: 22px; font-weight: 700; color: #1e293b; }
        
        .excel-tabs-bar { display: flex; border-bottom: 2px solid #e2e8f0; gap: 4px; margin-bottom: 20px; flex-wrap: wrap; }
        .excel-tab-link { padding: 10px 16px; font-size: 13px; font-weight: 600; color: #64748b; text-decoration: none; border-bottom: 2px solid transparent; margin-bottom: -2px; transition: all 0.15s ease; }
        .excel-tab-link:hover { color: #06b6d4; }
        .excel-tab-link.is-active { color: #06b6d4; border-bottom-color: #06b6d4; background: rgba(6, 182, 212, 0.04); border-radius: 6px 6px 0 0; }

        .excel-table-box { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; overflow-x: auto; max-width: 100%; display: block; box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
        .excel-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 13px; table-layout: auto; }
        .excel-table th { background: #f8fafc; color: #334155; font-weight: 600; padding: 12px 16px; border-bottom: 2px solid #e2e8f0; white-space: nowrap; }
        .excel-table td { padding: 12px 16px; border-bottom: 1px solid #f1f5f9; color: #475569; white-space: nowrap; }
        .excel-table tr:hover { background-color: #f8fafc; }
        
        .badge { padding: 2px 8px; font-size: 11px; font-weight: 600; border-radius: 4px; }
        .badge-rev { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .badge-exp { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
    </style>

    <div class="page-header">
        <h2 class="page-title"> Central Operations & Analytics</h2>
        <form method="GET" action="dashboard_analytics.php" class="filter-form">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($activeTab) ?>">
            <select name="month">
                <?php 
                $monthsLogged = array_unique(array_column($filterDates, 'm'));
                sort($monthsLogged);
                foreach ($monthsLogged as $m): 
                    $dateObj = DateTime::createFromFormat('!m', $m);
                    echo "<option value='{$m}' ".($m == $selectedMonth ? 'selected' : '').">{$dateObj->format('F')}</option>";
                endforeach; ?>
            </select>
            <select name="year">
                <?php 
                $yearsLogged = array_unique(array_column($filterDates, 'y'));
                sort($yearsLogged);
                foreach ($yearsLogged as $y):
                    echo "<option value='{$y}' ".($y == $selectedYear ? 'selected' : '').">{$y}</option>";
                endforeach; ?>
            </select>
            <button type="submit">Sync Sheet</button>
        </form>
    </div>

    <div class="metrics-grid">
        <div class="metric-card" style="border-left-color: <?= $netProfitLoss >= 0 ? '#10b981' : '#ef4444' ?>;">
            <h3>Net Profit / Loss</h3>
            <div class="value" style="color: <?= $netProfitLoss >= 0 ? '#10b981' : '#ef4444' ?>;">₹<?= number_format($netProfitLoss, 2) ?></div>
        </div>
        <div class="metric-card" style="border-left-color: #06b6d4;">
            <h3>Farm Total Revenue</h3>
            <div class="value">₹<?= number_format($farmTotalRevenue, 2) ?></div>
        </div>
        <div class="metric-card" style="border-left-color: #3b82f6;">
            <h3>Total Operational Expenses</h3>
            <div class="value">₹<?= number_format($totalExpenses, 2) ?></div>
        </div>
    </div>

    <div class="excel-tabs-bar">
        <a href="dashboard_analytics.php?tab=overview&month=<?= $selectedMonth ?>&year=<?= $selectedYear ?>" class="excel-tab-link <?= $activeTab === 'overview' ? 'is-active' : '' ?>">📊 Summary Statement</a>
        <a href="dashboard_analytics.php?tab=bookings&month=<?= $selectedMonth ?>&year=<?= $selectedYear ?>" class="excel-tab-link <?= $activeTab === 'bookings' ? 'is-active' : '' ?>">🏠 Booking Registry</a>
        <a href="dashboard_analytics.php?tab=food_logs&month=<?= $selectedMonth ?>&year=<?= $selectedYear ?>" class="excel-tab-link <?= $activeTab === 'food_logs' ? 'is-active' : '' ?>">🍽️ Food Order Logs</a>
        <a href="dashboard_analytics.php?tab=kitchen_expenses&month=<?= $selectedMonth ?>&year=<?= $selectedYear ?>" class="excel-tab-link <?= $activeTab === 'kitchen_expenses' ? 'is-active' : '' ?>">🍳 Kitchen Inventory Expenses</a>
        <a href="dashboard_analytics.php?tab=farm_upkeep&month=<?= $selectedMonth ?>&year=<?= $selectedYear ?>" class="excel-tab-link <?= $activeTab === 'farm_upkeep' ? 'is-active' : '' ?>">🛠️ Farm Upkeep</a>
        <a href="dashboard_analytics.php?tab=salaries&month=<?= $selectedMonth ?>&year=<?= $selectedYear ?>" class="excel-tab-link <?= $activeTab === 'salaries' ? 'is-active' : '' ?>">💼 Salaries Registry</a>
    </div>

    <div class="excel-table-box">
        <table class="excel-table" id="analyticsPrimaryTargetMatrix">
            <thead>
                <tr>
                    <?php if ($activeTab === 'overview'): ?>
                        <th>Financial Stat Metric Descriptor</th><th>Evaluated Cashflow Statement Value Balance</th>
                    <?php elseif ($activeTab === 'bookings'): ?>
                        <th>Guest Profile</th><th>Booking Source</th><th>Contact No.</th><th>No. of Guest</th><th>Check-In Date</th><th>Check-Out Date</th><th>Total Days</th><th>Per Night Charges</th><th>Total Charge</th><th>Advance Paid</th><th>Received by</th><th>Pending Amount</th><th>Received by</th><th>Decoration</th><th>Tip</th>
                    <?php elseif ($activeTab === 'food_logs'): ?>
                        <th>Guest Reference</th><th>Contact No.</th><th>Check-In Date</th><th>Total Food Revenue</th><th>Received by</th>
                    <?php elseif ($activeTab === 'kitchen_expenses'): ?>
                        <th>Recorded Date</th><th>Category</th><th>Inventory Item Detail</th><th>Vendor</th><th>Quantity</th><th>Unit Price</th><th>Total Outbound Disbursed</th>
                    <?php elseif ($activeTab === 'farm_upkeep'): ?>
                        <th>Recorded Date</th><th>Classification Category</th><th>Voucher Narration Description</th><th>Vendor Name</th><th>Amount Disbursed</th>
                    <?php elseif ($activeTab === 'salaries'): ?>
                        <th>Disbursed Date</th><th>Classification Profile</th><th>Voucher Reference/Notes</th><th>Employee Name</th><th>Net Salary Disbursed</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody id="ajaxPaginatedTableStream">
                <?php
                $_GET['offset'] = 0;
                $_GET['tab'] = $activeTab;
                $_GET['month'] = $selectedMonth;
                $_GET['year'] = $selectedYear;
                $_GET['limit'] = ($activeTab === 'overview') ? 10 : 31;
                include 'ajax_load_data.php';
                ?>
            </tbody>
        </table>
    </div>
    
    <?php if ($activeTab !== 'overview'): ?>
    <div style="padding: 15px; text-align: center; background: #fff; border-top: 1px solid #e2e8f0;">
        <button type="button" id="btnTriggerLiveFetch" data-current-offset="31" style="padding: 10px 24px; background: #06b6d4; color: #fff; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 13px;">Load More Rows...</button>
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const tableTarget = document.getElementById("analyticsPrimaryTargetMatrix");
    const actionFetchButton = document.getElementById("btnTriggerLiveFetch");
    
    if (actionFetchButton && tableTarget) {
        if (tableTarget.querySelectorAll("tbody tr").length < 31) {
            actionFetchButton.style.display = "none";
        }

        actionFetchButton.addEventListener("click", function() {
            const currentOffset = parseInt(this.getAttribute("data-current-offset"));
            this.innerText = "Loading data stream...";
            this.disabled = true;

            fetch(`dashboard_analytics.php?action_ajax_load_more=1&tab=<?= $activeTab ?>&month=<?= $selectedMonth ?>&year=<?= $selectedYear ?>&offset=${currentOffset}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.text())
            .then(bodyHtmlString => {
                if (bodyHtmlString.trim() === "") {
                    this.innerText = "All records loaded completely";
                    this.style.background = "#94a3b8";
                } else {
                    const targetDataContainer = document.getElementById("ajaxPaginatedTableStream");
                    targetDataContainer.insertAdjacentHTML('beforeend', bodyHtmlString);
                    this.setAttribute("data-current-offset", currentOffset + 30);
                    this.innerText = "Load More Rows...";
                    this.disabled = false;
                }
            })
            .catch(err => {
                console.error("Fetch pipeline failed:", err);
                this.innerText = "Network Error. Retry.";
                this.disabled = false;
            });
        });
    }
});
</script>

<?php if (!$is_ajax) { include 'includes/footer.php'; } ?>