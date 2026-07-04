<?php
// /home/apartment/artistsfarmjaipur.com/Order/dashboard_analytics.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/config/db.php'; 

// 1. Set Active Filtering States (Defaults to current month/year)
$selectedMonth = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));
$selectedYear  = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));
$activeTab     = isset($_GET['tab']) ? $_GET['tab'] : 'overview';

// --- AJAX PAGINATION INTERCEPTOR PIPE ---
if (isset($_GET['action_ajax_load_more'])) {
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 31;
    $limit  = 30;

    if ($activeTab === 'bookings') {
        $guestStmt = $pdo->prepare("SELECT * FROM guests WHERE MONTH(checkin_date) = :m AND YEAR(checkin_date) = :y ORDER BY checkin_date DESC LIMIT :limit OFFSET :offset");
        $guestStmt->bindValue(':m', $selectedMonth, PDO::PARAM_INT);
        $guestStmt->bindValue(':y', $selectedYear, PDO::PARAM_INT);
        $guestStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $guestStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $guestStmt->execute();
        $nextGuestRows = $guestStmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($nextGuestRows)) {
            foreach ($nextGuestRows as $g) {
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
    exit;
}

// 2. Fetch Available Date Ranges Dynamically across tables to build filters
$filterDates = $pdo->query("
    SELECT DISTINCT MONTH(checkin_date) as m, YEAR(checkin_date) as y FROM guests WHERE checkin_date IS NOT NULL
    UNION 
    SELECT DISTINCT MONTH(date) as m, YEAR(date) as y FROM kitchen_expenses WHERE date IS NOT NULL
    UNION 
    SELECT DISTINCT MONTH(date) as m, YEAR(date) as y FROM farm_expenses WHERE date IS NOT NULL
    ORDER BY y DESC, m DESC
")->fetchAll(PDO::FETCH_ASSOC);

if (empty($filterDates)) {
    $filterDates[] = ['m' => intval(date('m')), 'y' => intval(date('Y'))];
}

// 3. COMPUTE METRICS FOR THE CHOSEN PERIOD FROM INDIVIDUAL PRODUCTION TABLES
$revenueStmt = $pdo->prepare("
    SELECT COALESCE(SUM(total_charge + total_food + decoration_charges + tip_amount), 0) 
    FROM guests 
    WHERE MONTH(checkin_date) = :m AND YEAR(checkin_date) = :y
");
$revenueStmt->execute([':m' => $selectedMonth, ':y' => $selectedYear]);
$grossRevenue = $revenueStmt->fetchColumn();

$kitExpStmt = $pdo->prepare("
    SELECT COALESCE(SUM(qty * price_per_unit), 0) 
    FROM kitchen_expenses 
    WHERE MONTH(date) = :m AND YEAR(date) = :y
");
$kitExpStmt->execute([':m' => $selectedMonth, ':y' => $selectedYear]);
$kitchenExpensesSum = $kitExpStmt->fetchColumn();

$farmExpStmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0) 
    FROM farm_expenses 
    WHERE MONTH(date) = :m AND YEAR(date) = :y AND date != '1970-01-01'
");
$farmExpStmt->execute([':m' => $selectedMonth, ':y' => $selectedYear]);
$farmExpensesSum = $farmExpStmt->fetchColumn();

$totalExpenses = $kitchenExpensesSum + $farmExpensesSum;
$netProfit = $grossRevenue - $totalExpenses;

// 4. AJAX ROUTING CHECK INTERCEPTOR
$is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') || isset($_GET['ajax']);
if (!$is_ajax) { include 'includes/header.php'; }
?>

<div class="main-content" style="padding: 12px; width: 100%; font-family: 'Segoe UI', Helvetica, Arial, sans-serif; box-sizing: border-box;">
    
    <style>
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .page-title { font-size: 20px; font-weight: 700; color: #1e293b; margin: 0; }
        .filter-form { display: flex; gap: 10px; align-items: center; background: #fff; padding: 10px 15px; border-radius: 8px; border: 1px solid #e2e8f0; }
        .filter-form select { padding: 6px 12px; border-radius: 6px; border: 1px solid #cbd5e0; background: #fff; font-size: 13px; }
        .filter-form button { padding: 6px 14px; background: #06b6d4; color: #fff; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 13px; }
        .filter-form button:hover { background: #0891b2; }
        
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

        .excel-tabs-bar { display: flex; border-bottom: 2px solid #e2e8f0; gap: 4px; margin-bottom: 20px; }
        .excel-tab-link { padding: 10px 16px; font-size: 13px; font-weight: 600; color: #64748b; text-decoration: none; border-bottom: 2px solid transparent; margin-bottom: -2px; transition: all 0.15s ease; }
        .excel-tab-link:hover { color: #06b6d4; }
        .excel-tab-link.is-active { color: #06b6d4; border-bottom-color: #06b6d4; background: rgba(6, 182, 212, 0.04); border-radius: 6px 6px 0 0; }

        /* FIX 1: Table Stretching Resolved via Scroller Frame Container */
        .excel-table-box { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; overflow-x: auto; width: 100%; box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
        .excel-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 13px; }
        .excel-table th { background: #f8fafc; color: #334155; font-weight: 600; padding: 12px 16px; border-bottom: 2px solid #e2e8f0; }
        .excel-table td { padding: 12px 16px; border-bottom: 1px solid #f1f5f9; color: #475569; }
        .excel-table tr:hover { background-color: #f8fafc; }
        .badge { padding: 2px 8px; font-size: 11px; font-weight: 600; border-radius: 4px; }
        .badge-rev { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .badge-exp { background: rgba(239, 68, 68, 0.1); color: #ef4444; }

        .column-visibility-widget { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; margin-bottom: 15px; text-align: left; }
        .column-visibility-widget summary { font-size: 13px; font-weight: 600; color: #475569; cursor: pointer; outline: none; }
        .toggle-flex-wrap { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 10px; border-top: 1px dashed #e2e8f0; padding-top: 8px; }
        .toggle-checkbox-label { display: flex; align-items: center; gap: 6px; font-size: 12px; color: #334155; cursor: pointer; font-weight: 500; }
        .toggle-checkbox-label input { accent-color: #06b6d4; }
    </style>

    <div class="page-header">
        <h2 class="page-title">📈 Central Operations & Analytics</h2>
        <form method="GET" action="dashboard_analytics.php" class="filter-form">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($activeTab) ?>">
            <select name="month">
                <?php 
                $monthsLogged = array_unique(array_column($filterDates, 'm'));
                sort($monthsLogged);
                foreach ($monthsLogged as $m): 
                    $dateObj = DateTime::createFromFormat('!m', $m);
                    $isSelected = ($m == $selectedMonth) ? 'selected' : '';
                    echo "<option value='{$m}' {$isSelected}>{$dateObj->format('F')}</option>";
                endforeach; ?>
            </select>
            <select name="year">
                <?php 
                $yearsLogged = array_unique(array_column($filterDates, 'y'));
                sort($yearsLogged);
                foreach ($yearsLogged as $y):
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

    <details class="column-visibility-widget" open>
        <summary><i class="fa-solid fa-filter" style="color:#06b6d4; margin-right:4px;"></i> Hide / Show Columns Selector</summary>
        <div class="toggle-flex-wrap" id="liveColumnToggleWrapperPanel"></div>
    </details>

    <div class="excel-table-box">
        <?php if ($activeTab === 'overview'): ?>
            <table class="excel-table" id="analyticsPrimaryTargetMatrix">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Classification</th>
                        <th>Narration Description</th>
                        <th>Vendor / Guest</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $unionQuery = "
                        SELECT checkin_date AS date, 'Room Revenue' AS type, CONCAT('Booking charge collected for group') AS notes, guest_name AS entity, total_charge AS amt, 1 AS is_rev FROM guests WHERE MONTH(checkin_date) = :m1 AND YEAR(checkin_date) = :y1
                        UNION ALL
                        SELECT date AS date, 'Kitchen Outbound' AS type, CONCAT(category, ': ', description) AS notes, vendor_name AS entity, (qty * price_per_unit) AS amt, 0 AS is_rev FROM kitchen_expenses WHERE MONTH(date) = :m2 AND YEAR(date) = :y2
                        UNION ALL
                        SELECT date AS date, 'Farm Outbound' AS type, description AS notes, vendor_name AS entity, amount AS amt, 0 AS is_rev FROM farm_expenses WHERE MONTH(date) = :m3 AND YEAR(date) = :y3 AND date != '1970-01-01'
                        ORDER BY date DESC LIMIT 100
                    ";
                    $stmt = $pdo->prepare($unionQuery);
                    $stmt->execute([
                        ':m1' => $selectedMonth, ':y1' => $selectedYear,
                        ':m2' => $selectedMonth, ':y2' => $selectedYear,
                        ':m3' => $selectedMonth, ':y3' => $selectedYear
                    ]);
                    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    if (empty($entries)): ?>
                        <tr><td colspan="5" style="text-align: center; color: #94a3b8;">No operations logged for this period dashboard view profile.</td></tr>
                    <?php else: foreach ($entries as $row): ?>
                        <tr>
                            <td><?= date('d M Y', strtotime($row['date'])) ?></td>
                            <td><span class="badge <?= $row['is_rev'] ? 'badge-rev' : 'badge-exp' ?>"><?= htmlspecialchars($row['type']) ?></span></td>
                            <td><?= htmlspecialchars($row['notes']) ?></td>
                            <td><strong><?= htmlspecialchars($row['entity'] ?: 'Unnamed') ?></strong></td>
                            <td style="font-weight: 700; color: <?= $row['is_rev'] ? '#10b981' : '#ef4444' ?>;">
                                <?= $row['is_rev'] ? '+' : '-' ?> ₹<?= number_format($row['amt'], 2) ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>

        <?php elseif ($activeTab === 'bookings'): ?>
            <div style="width: 100%;">
                <table class="excel-table" id="analyticsPrimaryTargetMatrix" style="white-space: nowrap;">
                    <thead>
                        <tr>
                            <th>Guest Profile</th>
                            <th>Booking Source</th>
                            <th>Contact No.</th>
                            <th>No. of Guest</th>
                            <th>Check-In Date</th>
                            <th>Check-Out Date</th>
                            <th>Total Days</th>
                            <th>Per Night Charges</th>
                            <th>Total Charge</th>
                            <th>Advance Paid</th>
                            <th>Received by</th>
                            <th>Pending Amount</th>
                            <th>Received by</th>
                            <th>Total Food</th>
                            <th>Received by</th>
                            <th>Decoration</th>
                            <th>Tip</th>
                        </tr>
                    </thead>
                    <tbody id="ajaxPaginatedTableStream">
                        <?php
                        // FIX 3: Initial loading bound at 31 items
                        $_GET['offset'] = 0;
                        $_GET['tab'] = 'bookings';
                        $_GET['month'] = $selectedMonth;
                        $_GET['year'] = $selectedYear;
                        $_GET['limit'] = 31;
                        include 'ajax_load_data.php';
                        ?>
                    </tbody>
                </table>
            </div>
            
            <div style="padding: 15px; text-align: center; background: #fff; border-top: 1px solid #e2e8f0;">
                <button type="button" id="btnTriggerLiveFetch" data-current-offset="31" style="padding: 10px 24px; background: #06b6d4; color: #fff; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 13px; transition: background 0.2s;">Load More Bookings...</button>
            </div>

        <?php elseif ($activeTab === 'expenses'): ?>
            <table class="excel-table" id="analyticsPrimaryTargetMatrix">
                <thead>
                    <tr>
                        <th>Recorded Date</th>
                        <th>Allocation Category</th>
                        <th>Voucher Reference / Item Detail</th>
                        <th>Vendor Name</th>
                        <th>Amount Disbursed</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $expQuery = "
                        SELECT date, 'Kitchen Cost' AS source, CONCAT(category, ': ', IFNULL(description, 'Provisions')) AS detail, vendor_name, (qty * price_per_unit) AS amount FROM kitchen_expenses WHERE MONTH(date) = :m1 AND YEAR(date) = :y1
                        UNION ALL
                        SELECT date, 'Farm Operation' AS source, description AS detail, vendor_name, amount FROM farm_expenses WHERE MONTH(date) = :m2 AND YEAR(date) = :y2 AND date != '1970-01-01'
                        ORDER BY date DESC
                    ";
                    $expStmt = $pdo->prepare($expQuery);
                    $expStmt->execute([':m1' => $selectedMonth, ':y1' => $selectedYear, ':m2' => $selectedMonth, ':y2' => $selectedYear]);
                    $expRows = $expStmt->fetchAll(PDO::FETCH_ASSOC);

                    if (empty($expRows)): ?>
                        <tr><td colspan="5" style="text-align: center; color: #94a3b8;">No outgoing cost vouchers or grocery sheets saved for this month profile.</td></tr>
                    <?php else: foreach ($expRows as $e): ?>
                        <tr>
                            <td><?= date('d M Y', strtotime($e['date'])) ?></td>
                            <td><span class="badge badge-exp"><?= htmlspecialchars($e['source']) ?></span></td>
                            <td><?= htmlspecialchars($e['detail']) ?></td>
                            <td><strong><?= htmlspecialchars($e['vendor_name'] ?: 'Other') ?></strong></td>
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
    const tableTarget = document.getElementById("analyticsPrimaryTargetMatrix");
    const containerPanel = document.getElementById("liveColumnToggleWrapperPanel");
    
    // 1. Synchronized Show/Hide Column Processor Logic
    if (tableTarget && containerPanel) {
        const structuralHeaders = tableTarget.querySelectorAll("thead th");
        
        structuralHeaders.forEach((thCell, index) => {
            const rawTitle = thCell.innerText.trim();
            
            const labelNode = document.createElement("label");
            labelNode.className = "toggle-checkbox-label";
            
            const checkControl = document.createElement("input");
            checkControl.type = "checkbox";
            checkControl.checked = true;
            checkControl.dataset.targetColumnIndex = index;
            
            checkControl.addEventListener("change", function() {
                const targetIdx = this.dataset.targetColumnIndex;
                const displayStyle = this.checked ? "" : "none";
                
                thCell.style.display = displayStyle;
                
                const tableBodyRows = tableTarget.querySelectorAll("tbody tr");
                tableBodyRows.forEach(row => {
                    const dataCell = row.cells[targetIdx];
                    if (dataCell) {
                        dataCell.style.display = displayStyle;
                    }
                });
            });
            
            labelNode.appendChild(checkControl);
            labelNode.appendChild(document.createTextNode(rawTitle));
            containerPanel.appendChild(labelNode);
        });
    }

    // 2. Synchronized Loop Loader Module
    const actionFetchButton = document.getElementById("btnTriggerLiveFetch");
    if (actionFetchButton) {
        actionFetchButton.addEventListener("click", function() {
            const currentOffset = parseInt(this.getAttribute("data-current-offset"));
            this.innerText = "Loading data stream...";
            this.disabled = true;

            const requestUrl = `dashboard_analytics.php?action_ajax_load_more=1&tab=<?= $activeTab ?>&month=<?= $selectedMonth ?>&year=<?= $selectedYear ?>&offset=${currentOffset}`;

            fetch(requestUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(res => res.text())
                .then(bodyHtmlString => {
                    if (bodyHtmlString.trim() === "") {
                        this.innerText = "All records pulled completely";
                        this.style.background = "#94a3b8";
                    } else {
                        const targetDataContainer = document.getElementById("ajaxPaginatedTableStream");
                        targetDataContainer.insertAdjacentHTML('beforeend', bodyHtmlString);
                        
                        const runningUpdatedOffset = currentOffset + 30;
                        this.setAttribute("data-current-offset", runningUpdatedOffset);
                        this.innerText = "Load More Bookings...";
                        this.disabled = false;
                        
                        // Enforce hidden mask visibility layers over dynamically loaded elements
                        if (tableTarget) {
                            const visibilityCheckboxes = containerPanel.querySelectorAll("input[type='checkbox']");
                            visibilityCheckboxes.forEach(cb => {
                                if (!cb.checked) {
                                    const uncheckedIdx = cb.dataset.targetColumnIndex;
                                    const brokenRows = targetDataContainer.querySelectorAll("tr");
                                    brokenRows.forEach(r => {
                                        const singleCell = r.cells[uncheckedIdx];
                                        if (singleCell) singleCell.style.display = "none";
                                    });
                                }
                            });
                        }
                    }
                })
                .catch(error => {
                    console.error("Network sync logic exception:", error);
                    this.innerText = "Network pipeline error. Retry.";
                    this.disabled = false;
                });
        });
    }
});
</script>

<?php if (!$is_ajax) { include 'includes/footer.php'; } ?>