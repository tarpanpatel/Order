<?php
// /home/apartment/artistsfarmjaipur.com/Order/past_receipts.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/config/db.php'; 

if (!isset($_SESSION["user_id"])) { 
    header("Location: login.php"); 
    exit; 
}

// Fetch date filters if selected
$selectedMonth = isset($_GET['month']) ? intval($_GET['month']) : null;
$selectedYear  = isset($_GET['year']) ? intval($_GET['year']) : null;

// Build dynamic filtering SQL string
$sql = "SELECT * FROM farm_bookings";
$whereClauses = [];
$params = [];

if ($selectedMonth && $selectedYear) {
    $whereClauses[] = "MONTH(check_in_date) = :month AND YEAR(check_in_date) = :year";
    $params[':month'] = $selectedMonth;
    $params[':year'] = $selectedYear;
}

if (!empty($whereClauses)) {
    $sql .= " WHERE " . implode(" AND ", $whereClauses);
}
$sql .= " ORDER BY id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$receipts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch date ranges for dropdown filters
$filterDates = $pdo->query("SELECT DISTINCT MONTH(check_in_date) as m, YEAR(check_in_date) as y FROM farm_bookings WHERE check_in_date IS NOT NULL ORDER BY y DESC, m DESC")->fetchAll(PDO::FETCH_ASSOC);

include "includes/header.php";
?>

<div class="main-content" style="padding: 12px; width: 100%; max-width: 100%; box-sizing: border-box; overflow-x: hidden; font-family: 'Segoe UI', Helvetica, Arial, sans-serif;">
    
    <style>
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .page-title { font-size: 20px; font-weight: 700; color: #1e293b; margin: 0; }
        .filter-form { display: flex; gap: 10px; align-items: center; background: #fff; padding: 10px 15px; border-radius: 8px; border: 1px solid #e2e8f0; }
        .filter-form select { padding: 6px 12px; border-radius: 6px; border: 1px solid #cbd5e0; background: #fff; font-size: 13px; }
        .filter-form button, .btn-action { padding: 6px 14px; background: #06b6d4; color: #fff; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 13px; text-decoration: none; }
        .filter-form button:hover, .btn-action:hover { background: #0891b2; }
        .btn-clear { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e0; }
        .btn-clear:hover { background: #e2e8f0; }

        .excel-table-box { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; overflow-x: auto; max-width: 100%; display: block; box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
        .excel-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 13px; table-layout: auto; }
        .excel-table th { background: #f8fafc; color: #334155; font-weight: 600; padding: 12px 16px; border-bottom: 2px solid #e2e8f0; white-space: nowrap; }
        .excel-table td { padding: 12px 16px; border-bottom: 1px solid #f1f5f9; color: #475569; white-space: nowrap; }
        .excel-table tr:hover { background-color: #f8fafc; }
        
        .badge { padding: 2px 8px; font-size: 11px; font-weight: 600; border-radius: 4px; background: #f1f5f9; color: #475569; }
        .badge-success { background: rgba(16, 185, 129, 0.1); color: #10b981; }

        /* Modal Styles */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 99999; justify-content: center; align-items: center; backdrop-filter: blur(4px); }
        .modal-content { max-width: 450px; width: 90%; background: white; padding: 24px; border-radius: 12px; font-family: monospace; color: #111827; box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .receipt-line { display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 13px; }
        .receipt-divider { border-bottom: 1px dashed #cbd5e0; margin: 12px 0; }
    </style>

    <div class="page-header">
        <h2 class="page-title">📜 Historical Checkouts & Past Receipts</h2>
        <form method="GET" action="past_receipts.php" class="filter-form">
            <select name="month" required>
                <option value="">-- Select Month --</option>
                <?php 
                $monthsLogged = array_unique(array_column($filterDates, 'm'));
                sort($monthsLogged);
                foreach ($monthsLogged as $m): 
                    $dateObj = DateTime::createFromFormat('!m', $m);
                    echo "<option value='{$m}' ".($m == $selectedMonth ? 'selected' : '').">{$dateObj->format('F')}</option>";
                endforeach; ?>
            </select>
            <select name="year" required>
                <option value="">-- Select Year --</option>
                <?php 
                $yearsLogged = array_unique(array_column($filterDates, 'y'));
                sort($yearsLogged);
                foreach ($yearsLogged as $y):
                    echo "<option value='{$y}' ".($y == $selectedYear ? 'selected' : '').">{$y}</option>";
                endforeach; ?>
            </select>
            <button type="submit">Filter Logs</button>
            <?php if ($selectedMonth): ?>
                <a href="past_receipts.php" class="btn-action btn-clear">Clear Filter</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="excel-table-box">
        <table class="excel-table">
            <thead>
                <tr>
                    <th>Invoice ID</th>
                    <th>Guest / Source</th>
                    <th>Contact No.</th>
                    <th>Check-In</th>
                    <th>Check-Out</th>
                    <th>Stay Nights</th>
                    <th>Total Settlement</th>
                    <th>Remarks</th>
                    <th style="text-align: center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($receipts)): ?>
                    <tr><td colspan="9" style="text-align: center; color: #94a3b8; padding: 24px;">No historical checkout logs found for the selection filters.</td></tr>
                <?php else: foreach ($receipts as $r): 
                    $grandTotalBill = floatval($r['total_charge']) + floatval($r['total_food_bill']) + floatval($r['decoration_charges']) + floatval($r['tip_amount']);
                ?>
                    <tr>
                        <td><strong>#INV-<?= $r['id'] ?></strong></td>
                        <td><strong><?= htmlspecialchars($r['booking_source'] ?: 'Offline Guest') ?></strong> <span class="badge"><?= intval($r['no_of_guests']) ?> Pax</span></td>
                        <td><?= htmlspecialchars($r['contact_no'] ?: 'N/A') ?></td>
                        <td><?= date('d M Y', strtotime($r['check_in_date'])) ?></td>
                        <td><?= date('d M Y', strtotime($r['check_out_date'])) ?></td>
                        <td style="text-align: center;"><?= intval($r['total_days']) ?> Nights</td>
                        <td style="font-weight: 700; color: #0f172a;">₹<?= number_format($grandTotalBill, 2) ?></td>
                        <td><span class="badge badge-success"><?= htmlspecialchars($r['remarks']) ?></span></td>
                        <td style="text-align: center;">
                            <button type="button" class="btn-action" 
                                    onclick="openInvoiceModal(<?= htmlspecialchars(json_encode($r)) ?>)">
                                🔍 View Receipt
                            </button>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="historicalReceiptModal" class="modal">
    <div class="modal-content">
        <span style="float: right; cursor: pointer; font-size: 20px; color: #a0aec0; font-family: sans-serif;" onclick="closeInvoiceModal()">✕</span>
        <h3 style="text-align: center; margin-bottom: 4px; letter-spacing: 1px;">ARTISTIC STHAN</h3>
        <p style="text-align: center; font-size: 11px; color: #718096; margin-top: 0; margin-bottom: 5px;">Jaipur, Rajasthan</p>
        <h4 style="text-align: center; margin-bottom: 15px; text-decoration: underline;">INVOICE STATEMENT</h4>
        
        <div class="receipt-line"><span>Invoice Ref:</span><strong id="rcptInvId"></strong></div>
        <div class="receipt-line"><span>Guest / Channel:</span><strong id="rcptGuest"></strong></div>
        <div class="receipt-line"><span>Contact No:</span><strong id="rcptPhone"></strong></div>
        <div class="receipt-line"><span>Stay Timeline:</span><strong id="rcptDates"></strong></div>
        
        <div class="receipt-divider"></div>
        
        <div class="receipt-line"><span>Accommodation Cost:</span><span id="rcptRoom"></span></div>
        <div class="receipt-line"><span>Kitchen & Food Bill:</span><span id="rcptFood"></span></div>
        <div class="receipt-line"><span>Decoration Charges:</span><span id="rcptDecor"></span></div>
        <div class="receipt-line"><span>Staff Tip Gratuity:</span><span id="rcptTip"></span></div>
        
        <div class="receipt-divider"></div>
        
        <div class="receipt-line" style="font-size: 14px; font-weight: bold;"><span>Gross Total Bill:</span><span id="rcptGross"></span></div>
        <div class="receipt-line" style="color: #059669;"><span>Advance Deducted:</span><span id="rcptAdvance"></span></div>
        
        <div class="receipt-divider" style="border-bottom-style: double; border-bottom-width: 3px;"></div>
        
        <div class="receipt-line" style="font-size: 15px; font-weight: 800; color: #b91c1c;"><span>Net Collected Amount:</span><span id="rcptNet"></span></div>
        
        <div style="margin-top: 25px; display: flex; gap: 8px; justify-content: flex-end; font-family: sans-serif;">
            <button class="btn-action" style="background: #4a5568;" onclick="window.print()">🖨️ Print</button>
            <button class="btn-action btn-clear" onclick="closeInvoiceModal()">Close</button>
        </div>
    </div>
</div>

<script>
function openInvoiceModal(data) {
    const totalRoom = parseFloat(data.total_charge) || 0;
    const totalFood = parseFloat(data.total_food_bill) || 0;
    const totalDecor = parseFloat(data.decoration_charges) || 0;
    const totalTip = parseFloat(data.tip_amount) || 0;
    const advance = parseFloat(data.advance_paid) || 0;
    
    const grossBill = totalRoom + totalFood + totalDecor + totalTip;
    const netPaid = parseFloat(data.pending_amount) || 0;

    document.getElementById("rcptInvId").innerText = "#INV-" + data.id;
    document.getElementById("rcptGuest").innerText = data.booking_source;
    document.getElementById("rcptPhone").innerText = data.contact_no || 'N/A';
    document.getElementById("rcptDates").innerText = data.total_days + " Nights";
    
    document.getElementById("rcptRoom").innerText = "₹" + totalRoom.toFixed(2);
    document.getElementById("rcptFood").innerText = "₹" + totalFood.toFixed(2);
    document.getElementById("rcptDecor").innerText = "₹" + totalDecor.toFixed(2);
    document.getElementById("rcptTip").innerText = "₹" + totalTip.toFixed(2);
    
    document.getElementById("rcptGross").innerText = "₹" + grossBill.toFixed(2);
    document.getElementById("rcptAdvance").innerText = "-₹" + advance.toFixed(2);
    document.getElementById("rcptNet").innerText = "₹" + (advance + netPaid).toFixed(2);

    document.getElementById("historicalReceiptModal").style.display = "flex";
}

function closeInvoiceModal() {
    document.getElementById("historicalReceiptModal").style.display = "none";
}
</script>

<?php include "includes/footer.php"; ?>