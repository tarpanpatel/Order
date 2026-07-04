<?php
// /home/apartment/artistsfarmjaipur.com/Order/dashboard_analytics.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/config/db.php'; 

// 1. Set Active Filtering States (Defaults to current month/year)
$selectedMonth = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));
$selectedYear  = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));
$activeTab     = isset($_GET['tab']) ? $_GET['tab'] : 'overview';

// 2. Fetch Available Date Ranges Dynamically to Build Filters
$filterDates = $pdo->query("
    SELECT DISTINCT MONTH(transaction_date) as m, YEAR(transaction_date) as y FROM transaction_ledger
    UNION 
    SELECT DISTINCT MONTH(checkin_date) as m, YEAR(checkin_date) as y FROM guests
    ORDER BY y DESC, m DESC
")->fetchAll(PDO::FETCH_ASSOC);

if (empty($filterDates)) {
    $filterDates[] = ['m' => intval(date('m')), 'y' => intval(date('Y'))];
}

// 3. COMPUTE EXECUTIVE FINANCIAL METRICS FOR ACTIVE PERIOD
$revenueStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM transaction_ledger WHERE MONTH(transaction_date) = :m AND YEAR(transaction_date) = :y AND category IN ('Room Rent', 'Food Bill', 'Decoration', 'Tips')");
$revenueStmt->execute([':m' => $selectedMonth, ':y' => $selectedYear]);
$grossRevenue = $revenueStmt->fetchColumn();

$expenseStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM transaction_ledger WHERE MONTH(transaction_date) = :m AND YEAR(transaction_date) = :y AND category IN ('Farm Expense', 'Kitchen Expense', 'Staff Expense')");
$expenseStmt->execute([':m' => $selectedMonth, ':y' => $selectedYear]);
$totalExpenses = $expenseStmt->fetchColumn();

$netProfit = $grossRevenue - $totalExpenses;

// 4. AJAX ROUTING CHECK INTERCEPTOR
$is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') || isset($_GET['ajax']);
if (!$is_ajax) { include 'includes/header.php'; }
?>

<div class="main-content" style="padding: 12px; width: 100%; font-family: 'Segoe UI', Helvetica, Arial, sans-serif; box-sizing: border-box;">
    
    <style>
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px; }
        .page-title { font-size: 20px; font-weight: 700; color: #1e293b; margin: 0; }
        .filter-form { display: flex; gap: 10px; align-items: center; background: #fff; padding: 10px 15px; border-radius: 8px; border: 1px solid #e2e8f0; }
        .filter-form select { padding: 6px 12px; border-radius: 6px; border: 1px solid #cbd5e0; background: #fff; font-size: 13px; }
        .filter-form button { padding: 6px 14px; background: #06b6d4; color: #fff; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 13px; }
        .filter-form button:hover { background: #0891b2; }
        
        /* Dashboard Metric Cards */
        .metrics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .metric-card { background: #fff; padding: 16px; border-radius: 10px; border: 1px solid #e2e8f0; border-left: 4px solid #cbd5e0; }
        .metric-card h3 { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; margin: 0 0 8px 0; letter-spacing: 0.5px; }
        .metric-card .value { font-size: 24px; font-weight: 700; color: #1e293b; }
        .card-revenue { border-left-color: #10b981; }
        .card-revenue .value { color: #10b981; }
        .card-expenses { border-left-color: #ef4444; }
        .card-expenses .value { color: #ef4444; }
        .card-profit { border-left-color: #06b6d4; }
        .card-profit .value { color: #06b6d4; }

        /* Excel View Tabs Setup */
        .excel-tabs-bar { display: flex; border-bottom: 2px solid #e2e8f0; gap: 4px; margin-bottom: 20px; }
        .excel-tab-link { padding: 10px 16px; font-size: 13px; font-weight: 600; color: #64748b; text-decoration: none; border-bottom: 2px solid transparent; margin-bottom: -2px; transition: all 0.15s ease; }
        .excel-tab-link:hover { color: #06b6d4; }
        .excel-tab-link.is-active { color: #06b6d4; border-bottom-color: #06b6d4; background: rgba(6, 182, 212, 0.04); border-radius: 6px 6px 0 0; }

        /* Ledger Sheets Matrix Tables - SCROLLING OVERFLOW FIX APPLIED HERE */
        .excel-table-box { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; overflow-x: auto; width: 100%; box-shadow: 0 1px 3px rgba(0,0,0,0.02); margin-top: 10px; }
        .excel-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 13px; white-space: nowrap; }
        .excel-table th { background: #f8fafc; color: #334155; font-weight: 600; padding: 12px 16px; border-bottom: 2px solid #e2e8f0; }
        .excel-table td { padding: 12px 16px; border-bottom: 1px solid #f1f5f9; color: #475569; }
        .excel-table tr:hover { background-color: #f8fafc; }
        .badge { padding: 2px 8px; font-size: 11px; font-weight: 600; border-radius: 4px; }
        .badge-rev { background: rgba(16, 108, 242, 0.1); color: #10b981; }
        .badge-exp { background: rgba(239, 68, 68, 0.1); color: #ef4444; }

        /* Column Visibility Switcher Grid Styles */
        .visibility-control-panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 16px; margin-bottom: 15px; text-align: left; }
        .visibility-control-panel summary { font-size: 13px; font-weight: 600; color: #475569; cursor: pointer; outline: none; user-select: none; }
        .visibility-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 8px; margin-top: 12px; border-top: 1px dashed #e2e8f0; padding-top: 10px; }
        .visibility-label { display: flex; align-items: center; gap: 6px; font-size: 12px; color: #334155; cursor: pointer; font-weight: 500; }
        .visibility-label input { accent-color: #06b6d4; cursor: pointer; }
    </style>

    <div class="page-header">
        <h2 class="page-title">📈 Central Operations & Analytics</h2>
        <form method="GET" action="dashboard_analytics.php" class="filter-form">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($activeTab) ?>">
            <select name="month">
                <?php foreach ($filterDates as $d): 
                    $dateObj = DateTime::createFromFormat('!m', $d['m']);
                    $isSelected = ($d['m'] == $selectedMonth && $d['y'] == $selectedYear) ? 'selected' : '';
                    echo "<option value='{$d['m']}' {$isSelected}>{$dateObj->format('F')} {$d['y']}</option>";
                endforeach; ?>
            </select>
            <select name="year">
                <?php 
                $years = array_unique(array_column($filterDates, 'y'));
                foreach ($years as $y):
                    $isSelected = ($y == $selectedYear) ? 'selected' : '';
                    echo "<option value='{$y}' {$isSelected}>{$y}</option>";
                endforeach; ?>
            </select>
            <button type="submit">Sync Sheet</button>
        </form>
    </div>

    <div class="metrics-grid">
        <div class="metric-card card-revenue">
            <h3>Gross Incoming Revenue</h3>
            <div class="value">₹<?= number_format($grossRevenue, 2) ?></div>
        </div>
        <div class="metric-card card-expenses">
            <h3>Operational Expenses</h3>
            <div class="value">₹<?= number_format($totalExpenses, 2) ?></div>
        </div>
        <div class="metric-card card-profit">
            <h3>Net Property Yield</h3>
            <div class="value">₹<?= number_format($netProfit, 2) ?></div>
        </div>
    </div>

    <div class="excel-tabs-bar">
        <a href="dashboard_analytics.php?tab=overview&month=<?= $selectedMonth ?>&year=<?= $selectedYear ?>" class="excel-tab-link <?= $activeTab === 'overview' ? 'is-active' : '' ?>">📊 Summary Statement</a>
        <a href="dashboard_analytics.php?tab=bookings&month=<?= $selectedMonth ?>&year=<?= $selectedYear ?>" class="excel-tab-link <?= $activeTab === 'bookings' ? 'is-active' : '' ?>">🏠 Bookings & Food Logs</a>
        <a href="dashboard_analytics.php?tab=expenses&month=<?= $selectedMonth ?>&year=<?= $selectedYear ?>" class="excel-tab-link <?= $activeTab === 'expenses' ? 'is-active' : '' ?>">💸 Outgoing Expense Register</a>
    </div>

    <details class="visibility-control-panel" open>
        <summary><i class="fa-solid fa-eye-slash" style="color: #06b6d4; margin-right: 4px;"></i> Show / Hide Spreadsheet Columns</summary>
        <div class="visibility-grid" id="columnToggleContainer">
            </div>
    </details>

    <div class="excel-table-box">
        <?php if ($activeTab === 'overview'): ?>
            <table class="excel-table" id="analyticsMainTargetTable">
                <thead>
                    <tr>
                        <th>Transaction Date</th>
                        <th>Classification</th>
                        <th>Narration Description</th>
                        <th>Method</th>
                        <th>Cashflow Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $ledgerStmt = $pdo->prepare("SELECT * FROM transaction_ledger WHERE MONTH(transaction_date) = :m AND YEAR(transaction_date) = :y ORDER BY transaction_date DESC");
                    $ledgerStmt->execute([':m' => $selectedMonth, ':y' => $selectedYear]);
                    $entries = $ledgerStmt->fetchAll(PDO::FETCH_ASSOC);

                    if (empty($entries)): ?>
                        <tr><td colspan="5" style="text-align: center; color: #94a3b8;">No records logged for this period profile.</td></tr>
                    <?php else: foreach ($entries as $row): 
                        $isRev = in_array($row['category'], ['Room Rent', 'Food Bill', 'Decoration', 'Tips']);
                        ?>
                        <tr>
                            <td><?= date('d M Y', strtotime($row['transaction_date'])) ?></td>
                            <td><span class="badge <?= $isRev ? 'badge-rev' : 'badge-exp' ?>"><?= htmlspecialchars($row['category']) ?></span></td>
                            <td><?= htmlspecialchars($row['description']) ?></td>
                            <td><strong><?= htmlspecialchars($row['payment_mode']) ?></strong></td>
                            <td style="font-weight: 700; color: <?= $isRev ? '#10b981' : '#ef4444' ?>;">
                                <?= $isRev ? '+' : '-' ?> ₹<?= number_format($row['amount'], 2) ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>

        <?php elseif ($activeTab === 'bookings'): ?>
            <table class="excel-table" id="analyticsMainTargetTable">
                <thead>
                    <tr>
                        <th>Guest Registration Profile</th>
                        <th>Check In</th>
                        <th>Check Out</th>
                        <th>Base Room Tariff</th>
                        <th>Advance Deposited</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $guestStmt = $pdo->prepare("SELECT * FROM guests WHERE MONTH(checkin_date) = :m AND YEAR(checkin_date) = :y ORDER BY checkin_date DESC");
                    $guestStmt->execute([':m' => $selectedMonth, ':y' => $selectedYear]);
                    $guestRows = $guestStmt->fetchAll(PDO::FETCH_ASSOC);

                    if (empty($guestRows)): ?>
                        <tr><td colspan="6" style="text-align: center; color: #94a3b8;">No room registrations records found.</td></tr>
                    <?php else: foreach ($guestRows as $g): ?>
                        <tr>
                            <td><strong>📱 (<?= substr($g['phone_number'], -4) ?>)</strong></td>
                            <td><?= date('d M Y', strtotime($g['checkin_date'])) ?></td>
                            <td><?= date('d M Y', strtotime($g['checkout_date'])) ?></td>
                            <td>₹<?= number_format($g['base_room_rent'], 2) ?></td>
                            <td style="color: #10b981; font-weight: 600;">₹<?= number_format($g['advance_paid'], 2) ?></td>
                            <td><strong><?= htmlspecialchars($g['payment_status']) ?></strong></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>

        <?php elseif ($activeTab === 'expenses'): ?>
            <table class="excel-table" id="analyticsMainTargetTable">
                <thead>
                    <tr>
                        <th>Recorded Date</th>
                        <th>Allocation Category</th>
                        <th>Voucher Reference/Item Detail</th>
                        <th>Amount Disbursed</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $expStmt = $pdo->prepare("SELECT * FROM transaction_ledger WHERE MONTH(transaction_date) = :m AND YEAR(transaction_date) = :y AND category IN ('Farm Expense', 'Kitchen Expense', 'Staff Expense') ORDER BY transaction_date DESC");
                    $expStmt->execute([':m' => $selectedMonth, ':y' => $selectedYear]);
                    $expRows = $expStmt->fetchAll(PDO::FETCH_ASSOC);

                    if (empty($expRows)): ?>
                        <tr><td colspan="4" style="text-align: center; color: #94a3b8;">No outgoing costs or voucher logs saved.</td></tr>
                    <?php else: foreach ($expRows as $e): ?>
                        <tr>
                            <td><?= date('d M Y', strtotime($e['transaction_date'])) ?></td>
                            <td><span class="badge badge-exp"><?= htmlspecialchars($e['category']) ?></span></td>
                            <td><?= htmlspecialchars($e['description']) ?></td>
                            <td style="font-weight: 700; color: #ef4444;">₹<?= number_format($e['amount'], 2) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const table = document.getElementById("analyticsMainTargetTable");
    const switcherContainer = document.getElementById("columnToggleContainer");
    if (!table || !switcherContainer) return;

    const headers = table.querySelectorAll("thead th");
    
    // Dynamically build toggles matching your active table layout configuration
    headers.forEach((th, index) => {
        const title = th.innerText.trim();
        
        const label = document.createElement("label");
        label.className = "visibility-label";
        
        const checkbox = document.createElement("input");
        checkbox.type = "checkbox";
        checkbox.checked = true;
        checkbox.dataset.columnIndex = index;
        
        checkbox.addEventListener("change", function() {
            const columnIndex = this.dataset.columnIndex;
            const displayValue = this.checked ? "" : "none";
            
            // Toggle the header cell
            th.style.display = displayValue;
            
            // Toggle corresponding row cells safely
            const rows = table.querySelectorAll("tbody tr");
            rows.forEach(row => {
                const cell = row.cells[columnIndex];
                if (cell) cell.style.display = displayValue;
            });
        });
        
        label.appendChild(checkbox);
        label.appendChild(document.createTextNode(title));
        switcherContainer.appendChild(label);
    });
});
</script>

<?php if (!$is_ajax) { include 'includes/footer.php'; } ?>