<?php
// /home/apartment/artistsfarmjaipur.com/Order/export_center.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/config/db.php';

if (!isset($_SESSION["role"]) || ($_SESSION["role"] !== "Admin" && $_SESSION["role"] !== "Super Admin")) {
    header("Location: login.php");
    exit;
}

// Fetch dynamic date ranges across all logs to seed the spreadsheet filter dropdowns
$filterDates = $pdo->query("
    SELECT DISTINCT MONTH(checkin_date) as m, YEAR(checkin_date) as y FROM guests WHERE checkin_date IS NOT NULL
    UNION 
    SELECT DISTINCT MONTH(date) as m, YEAR(date) as y FROM kitchen_expenses WHERE date IS NOT NULL
    UNION
    SELECT DISTINCT MONTH(expense_date) as m, YEAR(expense_date) as y FROM farm_utility_expenses WHERE expense_date IS NOT NULL
    ORDER BY y DESC, m DESC
")->fetchAll(PDO::FETCH_ASSOC);

if (empty($filterDates)) {
    $filterDates[] = ['m' => intval(date('m')), 'y' => intval(date('Y'))];
}

$selectedMonth = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));
$selectedYear  = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));

include "includes/header.php";
?>

<style>
/* --- OPERATIONAL DOWNLOAD CENTER RESPONSIVE UI STYLES --- */
.export-center-container {
    padding: 16px;
    font-family: 'Segoe UI', Helvetica, Arial, sans-serif;
    text-align: left;
    max-width: 900px;
    margin: 0 auto;
    box-sizing: border-box;
}

.export-header-box {
    margin-bottom: 24px;
    border-bottom: 1px solid #e2e8f0;
    padding-bottom: 16px;
}

.export-title {
    font-size: 22px;
    font-weight: 700;
    color: #1e293b;
    margin: 0 0 4px 0;
}

.export-subtitle {
    font-size: 13px;
    color: #64748b;
    margin: 0;
}

/* Central Filter Bar Wrapper */
.export-filter-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 24px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
}

.filter-row-layout {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.filter-group-field {
    display: flex;
    flex-direction: column;
    gap: 4px;
    flex: 1;
}

.filter-group-label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    color: #475569;
    letter-spacing: 0.5px;
}

.filter-select-element {
    padding: 10px 12px;
    border-radius: 8px;
    border: 1px solid #cbd5e0;
    background: #ffffff;
    font-size: 14px;
    font-weight: 600;
    color: #1e293b;
    min-height: 44px;
    width: 100%;
}

/* Large Touch Target Export Stack Cards */
.export-actions-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 16px;
}

.action-row-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 18px;
    display: flex;
    flex-direction: column;
    gap: 14px;
    align-items: flex-start;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    transition: transform 0.15s ease;
}

.action-row-card:hover {
    border-color: #cbd5e0;
}

.action-card-info {
    text-align: left;
}

.action-card-title {
    font-size: 15px;
    font-weight: 700;
    color: #0f172a;
    margin: 0 0 4px 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.action-card-desc {
    font-size: 13px;
    color: #64748b;
    margin: 0;
    line-height: 1.4;
}

/* Master 44px Touch Target Export Action Link Buttons */
.btn-export-trigger {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    min-height: 44px !important;
    padding: 10px 20px !important;
    font-size: 13px !important;
    font-weight: 700 !important;
    text-transform: uppercase !important;
    text-decoration: none !important;
    border-radius: 8px !important;
    width: 100% !important;
    box-sizing: border-box !important;
    text-align: center !important;
    cursor: pointer !important;
}

.btn-excel { background: #10b981 !important; color: #ffffff !important; border: 1px solid #059669 !important; }
.btn-excel:active { background: #059669 !important; }

.btn-backup { background: #ef4444 !important; color: #ffffff !important; border: 1px solid #dc2626 !important; }
.btn-backup:active { background: #dc2626 !important; }

/* --- RESPONSIVE TABLET/DESKTOP VIEWPORTS (min 768px) --- */
@media (min-width: 768px) {
    .filter-row-layout {
        flex-direction: row;
        align-items: flex-end;
    }
    .export-actions-grid {
        grid-template-columns: 1fr;
    }
    .action-row-card {
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
        gap: 24px;
    }
    .btn-export-trigger {
        width: auto !important;
        min-width: 200px !important;
    }
}
</style>

<div class="export-center-container">
    
    <div class="export-header-box">
        <h2 class="export-title">⚙️ Data Export & Backup Center</h2>
        <p class="export-subtitle">Download master auditing spreadsheets or generate snapshot recovery files for your records workbook.</p>
    </div>

    <div class="export-filter-card">
        <form method="GET" action="export_center.php" id="exportTimeframeForm">
            <div class="filter-row-layout">
                <div class="filter-group-field">
                    <label class="filter-group-label">Target Statement Month</label>
                    <select name="month" class="filter-select-element" onchange="document.getElementById('exportTimeframeForm').submit();">
                        <?php 
                        $monthsLogged = array_unique(array_column($filterDates, 'm'));
                        sort($monthsLogged);
                        foreach ($monthsLogged as $m): 
                            $dateObj = DateTime::createFromFormat('!m', $m);
                            echo "<option value='{$m}' ".($m === $selectedMonth ? 'selected' : '').">{$dateObj->format('F')}</option>";
                        endforeach; ?>
                    </select>
                </div>
                <div class="filter-group-field">
                    <label class="filter-group-label">Target Statement Year</label>
                    <select name="year" class="filter-select-element" onchange="document.getElementById('exportTimeframeForm').submit();">
                        <?php 
                        $yearsLogged = array_unique(array_column($filterDates, 'y'));
                        sort($yearsLogged);
                        foreach ($yearsLogged as $y):
                            echo "<option value='{$y}' ".($y === $selectedYear ? 'selected' : '').">{$y}</option>";
                        endforeach; ?>
                    </select>
                </div>
            </div>
        </form>
    </div>

    <div class="export-actions-grid">
        
        <div class="action-row-card">
            <div class="action-card-info">
                <h4 class="action-card-title">🏠 Accommodations Booking Spreadsheet</h4>
                <p class="action-card-desc">Extracts comprehensive check-in logs, occupancy timelines, advance splits, and room collections.</p>
            </div>
            <a href="export_and_backup.php?action=export_excel&tab=bookings&month=<?= $selectedMonth ?>&year=<?= $selectedYear ?>" download target="_blank" class="btn-export-trigger btn-excel">Export Sheets</a>
        </div>

        <div class="action-row-card">
            <div class="action-card-info">
                <h4 class="action-card-title">🍳 Kitchen Purchases Workbook</h4>
                <p class="action-card-desc">Downloads inventory replenishment lists, raw ration tracking, volume weights, and market vendor bills.</p>
            </div>
            <a href="export_and_backup.php?action=export_excel&tab=kitchen_expenses&month=<?= $selectedMonth ?>&year=<?= $selectedYear ?>" download target="_blank" class="btn-export-trigger btn-excel">Export Sheets</a>
        </div>

        <div class="action-row-card">
            <div class="action-card-info">
                <h4 class="action-card-title">🛠️ Property Maintenance &amp; Utilities Logs</h4>
                <p class="action-card-desc">Generates itemized expense spreadsheets for water tankers, electricity bills, hardware, and physical farm upkeep.</p>
            </div>
            <a href="export_and_backup.php?action=export_excel&tab=farm_upkeep&month=<?= $selectedMonth ?>&year=<?= $selectedYear ?>" download target="_blank" class="btn-export-trigger btn-excel">Export Sheets</a>
        </div>

        <div class="action-row-card">
            <div class="action-card-info">
                <h4 class="action-card-title">💼 Payroll &amp; Salaries Registry</h4>
                <p class="action-card-desc">Compiles all recorded payouts, staff management stipends, and continuous operational field allowances.</p>
            </div>
            <a href="export_and_backup.php?action=export_excel&tab=salaries&month=<?= $selectedMonth ?>&year=<?= $selectedYear ?>" download target="_blank" class="btn-export-trigger btn-excel">Export Sheets</a>
        </div>

        <div class="action-row-card" style="border-left-color: #ef4444; margin-top: 15px; background: #fffdfd;">
            <div class="action-card-info">
                <h4 class="action-card-title" style="color:#b91c1c;">🗄️ Full System Snapshot Backup</h4>
                <p class="action-card-desc">Generates an instant raw sql dump of your entire database structure and entries for full data protection.</p>
            </div>
            <a href="export_and_backup.php?action=backup_db" download target="_blank" class="btn-export-trigger btn-backup">Download Backup (.sql)</a>
        </div>

    </div>
</div>

<?php include "includes/footer.php"; ?>