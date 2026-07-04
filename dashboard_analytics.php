<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/config/db.php'; 

$selectedMonth = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));
$selectedYear  = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));
$activeTab     = isset($_GET['tab']) ? $_GET['tab'] : 'overview';

// Fetch filter dates
$filterDates = $pdo->query("SELECT DISTINCT MONTH(checkin_date) as m, YEAR(checkin_date) as y FROM guests UNION SELECT DISTINCT MONTH(date) as m, YEAR(date) as y FROM kitchen_expenses UNION SELECT DISTINCT MONTH(date) as m, YEAR(date) as y FROM farm_expenses ORDER BY y DESC, m DESC")->fetchAll(PDO::FETCH_ASSOC);

$is_ajax = isset($_GET['ajax']);
if (!$is_ajax) { include 'includes/header.php'; }
?>

<div class="main-content" style="padding: 12px; width: 100%; box-sizing: border-box;">
    <style>
        .excel-table-box { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; overflow-x: auto; width: 100%; box-shadow: 0 1px 3px rgba(0,0,0,0.02); margin-top: 10px; }
        .excel-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 13px; white-space: nowrap; }
        .excel-table th, .excel-table td { padding: 12px 16px; border-bottom: 1px solid #f1f5f9; }
        .visibility-panel { background: #fff; border: 1px solid #e2e8f0; padding: 12px; border-radius: 8px; margin-bottom: 15px; }
        .visibility-grid { display: flex; flex-wrap: wrap; gap: 15px; margin-top: 8px; }
    </style>

    <div class="visibility-panel">
        <strong>Toggle Columns:</strong>
        <div class="visibility-grid" id="columnToggleContainer"></div>
    </div>

    <div class="excel-table-box">
        <table class="excel-table" id="analyticsTable">
            <thead>
                <tr id="headerRow">
                    <?php 
                    $headers = ($activeTab === 'bookings') ? ['Profile', 'Booking Source', 'Contact', 'Guests', 'Check-In', 'Check-Out', 'Total', 'Advance', 'Pending'] : ['Date', 'Type', 'Description', 'Entity', 'Amount'];
                    foreach($headers as $h) echo "<th>$h</th>";
                    ?>
                </tr>
            </thead>
            <tbody id="tableBody">
                <?php include 'ajax_load_data.php'; ?>
            </tbody>
        </table>
        <button id="loadMoreBtn" data-offset="31" style="width:100%; padding:10px; cursor:pointer;">Load More...</button>
    </div>
</div>

<script>
// Column Toggle Logic
function initToggles() {
    const table = document.getElementById("analyticsTable");
    const container = document.getElementById("columnToggleContainer");
    container.innerHTML = "";
    table.querySelectorAll("thead th").forEach((th, i) => {
        let label = document.createElement("label");
        let cb = document.createElement("input");
        cb.type = "checkbox"; cb.checked = true;
        cb.onchange = () => {
            th.style.display = cb.checked ? "" : "none";
            table.querySelectorAll(`tbody tr td:nth-child(${i + 1})`).forEach(td => td.style.display = cb.checked ? "" : "none");
        };
        label.append(cb, " " + th.innerText);
        container.appendChild(label);
    });
}

// AJAX Load More
document.getElementById("loadMoreBtn").onclick = function() {
    let offset = this.getAttribute("data-offset");
    fetch(`?tab=<?=$activeTab?>&month=<?=$selectedMonth?>&year=<?=$selectedYear?>&ajax=1&offset=${offset}`)
        .then(r => r.text())
        .then(html => {
            if(html.trim() == "") { this.innerText = "No more data"; this.disabled = true; }
            else { 
                document.getElementById("tableBody").insertAdjacentHTML('beforeend', html);
                this.setAttribute("data-offset", parseInt(offset) + 30);
            }
        });
};
initToggles();
</script>

<?php if (!$is_ajax) { include 'includes/footer.php'; } ?>