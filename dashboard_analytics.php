<?php
// /home/apartment/artistsfarmjaipur.com/Order/dashboard_analytics.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/config/db.php'; 

// 1. Set Active Filtering States
$selectedMonth = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));
$selectedYear  = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));
$activeTab     = isset($_GET['tab']) ? $_GET['tab'] : 'overview';

// 2. Fetch Available Date Ranges (Restored Original Logic)
$filterDates = $pdo->query("
    SELECT DISTINCT MONTH(checkin_date) as m, YEAR(checkin_date) as y FROM guests WHERE checkin_date IS NOT NULL
    UNION SELECT DISTINCT MONTH(date) as m, YEAR(date) as y FROM kitchen_expenses WHERE date IS NOT NULL
    UNION SELECT DISTINCT MONTH(date) as m, YEAR(date) as y FROM farm_expenses WHERE date IS NOT NULL
    ORDER BY y DESC, m DESC
")->fetchAll(PDO::FETCH_ASSOC);

if (empty($filterDates)) { $filterDates[] = ['m' => intval(date('m')), 'y' => intval(date('Y'))]; }

// 3. COMPUTE FINANCIAL METRICS (Restored Original Logic)
$revenueStmt = $pdo->prepare("SELECT COALESCE(SUM(total_charge + total_food + decoration_charges + tip_amount), 0) FROM guests WHERE MONTH(checkin_date) = :m AND YEAR(checkin_date) = :y");
$revenueStmt->execute([':m' => $selectedMonth, ':y' => $selectedYear]);
$grossRevenue = $revenueStmt->fetchColumn();

$kitExpStmt = $pdo->prepare("SELECT COALESCE(SUM(qty * price_per_unit), 0) FROM kitchen_expenses WHERE MONTH(date) = :m AND YEAR(date) = :y");
$kitExpStmt->execute([':m' => $selectedMonth, ':y' => $selectedYear]);
$kitchenExpensesSum = $kitExpStmt->fetchColumn();

$farmExpStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM farm_expenses WHERE MONTH(date) = :m AND YEAR(date) = :y AND date != '1970-01-01'");
$farmExpStmt->execute([':m' => $selectedMonth, ':y' => $selectedYear]);
$farmExpensesSum = $farmExpStmt->fetchColumn();

$totalExpenses = $kitchenExpensesSum + $farmExpensesSum;
$netProfit = $grossRevenue - $totalExpenses;

$is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') || isset($_GET['ajax']);
if (!$is_ajax) { include 'includes/header.php'; }
?>

<div class="main-content" style="padding: 12px; width: 100%; box-sizing: border-box;">
    <style>
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .metrics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .metric-card { background: #fff; padding: 16px; border-radius: 10px; border: 1px solid #e2e8f0; border-left: 4px solid #cbd5e0; }
        .metric-card .value { font-size: 24px; font-weight: 700; color: #1e293b; }
        
        /* FIX: Table Overflow Wrapper */
        .excel-table-box { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; overflow-x: auto; width: 100%; box-shadow: 0 1px 3px rgba(0,0,0,0.02); margin-top: 10px; }
        .excel-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 13px; white-space: nowrap; }
        .excel-table th, .excel-table td { padding: 12px 16px; border-bottom: 1px solid #f1f5f9; color: #475569; }
        
        /* Column Toggle Style */
        .visibility-panel { background: #fff; border: 1px solid #e2e8f0; padding: 12px; border-radius: 8px; margin-bottom: 15px; }
    </style>

    <div class="visibility-panel">
        <strong>Hide/Show Columns:</strong>
        <div id="columnToggleContainer" style="display:flex; flex-wrap:wrap; gap:15px; margin-top:8px;"></div>
    </div>

    <div class="excel-table-box">
        <table class="excel-table" id="analyticsTable">
            </table>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const table = document.getElementById("analyticsTable");
    const container = document.getElementById("columnToggleContainer");
    
    // Safety check: if table doesn't exist, don't break the page
    if (!table || !container) return;

    // Build toggles
    table.querySelectorAll("thead th").forEach((th, i) => {
        let label = document.createElement("label");
        label.style.cursor = "pointer";
        let cb = document.createElement("input");
        cb.type = "checkbox"; 
        cb.checked = true;
        cb.style.marginRight = "5px";
        
        cb.onchange = function() {
            const display = this.checked ? "" : "none";
            th.style.display = display;
            table.querySelectorAll(`tbody tr td:nth-child(${i + 1})`).forEach(td => td.style.display = display);
        };
        
        label.append(cb, " " + th.innerText);
        container.appendChild(label);
    });
});

// Load More Logic (Keep your existing AJAX code below this)
document.getElementById("loadMoreBtn").onclick = function() {
    let offset = this.getAttribute("data-offset");
    fetch(`ajax_load_data.php?tab=<?=$activeTab?>&month=<?=$selectedMonth?>&year=<?=$selectedYear?>&offset=${offset}`)
        .then(r => r.text())
        .then(html => {
            if(html.trim() == "") { this.innerText = "No more data"; this.disabled = true; }
            else { 
                document.getElementById("tableBody").insertAdjacentHTML('beforeend', html);
                this.setAttribute("data-offset", parseInt(offset) + 30);
            }
        });
};
</script>

<?php if (!$is_ajax) { include 'includes/footer.php'; } ?>