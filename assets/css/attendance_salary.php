<?php
// /home/apartment/artistsfarmjaipur.com/Order/attendance_salary.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once "config/db.php";

if (!isset($_SESSION["role"]) || !check_page_access($pdo)) {
    die("Access Denied: You do not have permission to access this area.");
}

$selected_month = isset($_GET['filter_month']) ? trim($_GET['filter_month']) : date('Y-m');
$year = date('Y', strtotime($selected_month));
$month = date('m', strtotime($selected_month));
$days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);

// --- HANDLE POST ACTIONS ---
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    
    // 1. Update Single Attendance Cell
    if (isset($_POST['action_update_cell'])) {
        $u_id = intval($_POST['user_id']);
        $date = $_POST['date'];
        $status = $_POST['status'];
        
        $stmt = $pdo->prepare("INSERT INTO staff_attendance (attendance_date, user_id, status, marked_by) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status), marked_by = VALUES(marked_by)");
        $stmt->execute([$date, $u_id, $status, $_SESSION['username']]);
        header("Location: attendance_salary.php?filter_month=" . $selected_month);
        exit;
    }

    // 2. Update Daily Wage
    if (isset($_POST['action_update_wage'])) {
        $u_id = intval($_POST['user_id']);
        $wage = floatval($_POST['daily_wage']);
        
        $stmt = $pdo->prepare("INSERT INTO staff_wages (user_id, daily_wage) VALUES (?, ?) ON DUPLICATE KEY UPDATE daily_wage = VALUES(daily_wage)");
        $stmt->execute([$u_id, $wage]);
        header("Location: attendance_salary.php?filter_month=" . $selected_month);
        exit;
    }

    // 3. Add Advance
    if (isset($_POST['action_add_advance'])) {
        $u_id = intval($_POST['user_id']);
        $amount = floatval($_POST['advance_amount']);
        $remarks = trim($_POST['advance_remarks']);
        
        if ($amount > 0) {
            $stmt = $pdo->prepare("INSERT INTO staff_advances (user_id, month_year, amount, remarks) VALUES (?, ?, ?, ?)");
            $stmt->execute([$u_id, $selected_month, $amount, $remarks]);
        }
        header("Location: attendance_salary.php?filter_month=" . $selected_month);
        exit;
    }
}

// --- FETCH DATA ---
$staff_members = $pdo->query("SELECT id, username, role FROM users ORDER BY username ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch Attendance for the month
$att_stmt = $pdo->prepare("SELECT user_id, DAY(attendance_date) as d, status FROM staff_attendance WHERE DATE_FORMAT(attendance_date, '%Y-%m') = ?");
$att_stmt->execute([$selected_month]);
$attendance_data = [];
while ($row = $att_stmt->fetch()) {
    $attendance_data[$row['user_id']][$row['d']] = $row['status'];
}

// Fetch Wages
$wages_data = $pdo->query("SELECT user_id, daily_wage FROM staff_wages")->fetchAll(PDO::FETCH_KEY_PAIR);

// Fetch Advances for the month
$adv_stmt = $pdo->prepare("SELECT user_id, SUM(amount) as total_advance FROM staff_advances WHERE month_year = ? GROUP BY user_id");
$adv_stmt->execute([$selected_month]);
$advances_data = $adv_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

include "includes/header.php";
?>

<div class="app-body" style="padding: 20px; text-align: left;">
    <div class="page-header" style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
        <div>
            <h2 class="page-title">📅 Farm Staff Calendar & Salaries</h2>
            <p style="color: #64748b; font-size: 13px; margin-top: 4px;">Click any day in the grid to edit attendance. Formulas auto-calculate below.</p>
        </div>
        <form method="GET" style="margin: 0; display: flex; gap: 10px; align-items: center;">
            <label style="font-size: 13px; font-weight: 700; color: #475569;">Select Month:</label>
            <input type="month" name="filter_month" value="<?= $selected_month ?>" class="form-input-container" style="width: auto; padding: 6px 12px;" onchange="this.form.submit()">
        </form>
    </div>

    <!-- 1. VISUAL CALENDAR GRID -->
    <div class="calendar-grid-wrapper">
        <table class="calendar-table">
            <thead>
                <tr>
                    <th>Staff Member</th>
                    <?php for ($d = 1; $d <= $days_in_month; $d++): 
                        $day_name = date('D', strtotime("$year-$month-$d"));
                    ?>
                        <th><?= $d ?><br><span style="font-weight: 400; font-size: 9px;"><?= $day_name ?></span></th>
                    <?php endfor; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($staff_members as $staff): $uid = $staff['id']; ?>
                    <tr>
                        <td><?= htmlspecialchars($staff['username']) ?></td>
                        <?php for ($d = 1; $d <= $days_in_month; $d++): 
                            $status = $attendance_data[$uid][$d] ?? null;
                            $cell_class = 'cal-empty';
                            $display_char = '-';
                            
                            if ($status === 'Present') { $cell_class = 'cal-p'; $display_char = 'P'; }
                            if ($status === 'Absent') { $cell_class = 'cal-a'; $display_char = 'A'; }
                            if ($status === 'Half Day') { $cell_class = 'cal-h'; $display_char = 'H'; }
                            if ($status === 'Paid Leave') { $cell_class = 'cal-l'; $display_char = 'L'; }
                            if ($status === 'Weekly Off') { $cell_class = 'cal-o'; $display_char = 'O'; }
                            
                            $date_str = "$year-$month-" . str_pad($d, 2, '0', STR_PAD_LEFT);
                        ?>
                            <td>
                                <div class="cal-cell <?= $cell_class ?>" onclick="openCellModal(<?= $uid ?>, '<?= htmlspecialchars($staff['username']) ?>', '<?= $date_str ?>', '<?= $status ?>')">
                                    <?= $display_char ?>
                                </div>
                            </td>
                        <?php endfor; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- 2. FINANCIAL SUMMARY & DATA EXTRACTOR -->
    <div class="billing-card" style="padding: 0; overflow: hidden;">
        <div class="billing-section-title" style="padding: 20px 20px 0 20px; border: none;">💰 Monthly Payout Calculator</div>
        
        <div style="overflow-x: auto;">
            <table class="salary-summary-table">
                <thead>
                    <tr>
                        <th>Staff Name</th>
                        <th>Daily Wage (₹)</th>
                        <th>Present Days (Calculated)</th>
                        <th>Total Earned (₹)</th>
                        <th>Advances (₹)</th>
                        <th>Pending Payout (₹)</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $grand_total_earned = 0;
                    $grand_total_advances = 0;
                    $grand_total_pending = 0;

                    foreach ($staff_members as $staff): 
                        $uid = $staff['id'];
                        $daily_wage = floatval($wages_data[$uid] ?? 0);
                        
                        // Calculate workable days
                        $full_days = 0;
                        $half_days = 0;
                        if (isset($attendance_data[$uid])) {
                            $counts = array_count_values($attendance_data[$uid]);
                            $full_days = ($counts['Present'] ?? 0) + ($counts['Paid Leave'] ?? 0);
                            $half_days = ($counts['Half Day'] ?? 0);
                        }
                        
                        $calculated_days = $full_days + ($half_days * 0.5);
                        $total_earned = $calculated_days * $daily_wage;
                        $advance_taken = floatval($advances_data[$uid] ?? 0);
                        $pending_payout = $total_earned - $advance_taken;

                        $grand_total_earned += $total_earned;
                        $grand_total_advances += $advance_taken;
                        $grand_total_pending += $pending_payout;
                    ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($staff['username']) ?></strong></td>
                            <td>
                                <form method="POST" style="margin: 0; display: flex; gap: 5px;">
                                    <input type="hidden" name="action_update_wage" value="1">
                                    <input type="hidden" name="user_id" value="<?= $uid ?>">
                                    <input type="number" step="1" name="daily_wage" value="<?= $daily_wage ?>" class="wage-input-inline" onchange="this.form.submit()">
                                </form>
                            </td>
                            <td><?= $calculated_days ?> days</td>
                            <td class="text-sky-blue" style="font-weight: 700;">₹<?= number_format($total_earned, 2) ?></td>
                            <td class="text-red-danger" style="font-weight: 700;">₹<?= number_format($advance_taken, 2) ?></td>
                            <td class="text-green-success" style="font-weight: 800; font-size: 15px;">₹<?= number_format($pending_payout, 2) ?></td>
                            <td>
                                <button type="button" class="btn-outline-action" onclick="openAdvanceModal(<?= $uid ?>, '<?= htmlspecialchars($staff['username']) ?>')">+ Give Advance</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot style="background: #f8fafc; font-weight: 800; font-size: 14px;">
                    <tr>
                        <td colspan="3" style="text-align: right; padding: 15px;">FARM TOTALS:</td>
                        <td class="text-sky-blue">₹<?= number_format($grand_total_earned, 2) ?></td>
                        <td class="text-red-danger">₹<?= number_format($grand_total_advances, 2) ?></td>
                        <td class="text-green-success">₹<?= number_format($grand_total_pending, 2) ?></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<!-- MODAL 1: Edit Single Day Attendance -->
<div id="cellEditModal" class="print-modal-overlay">
    <div class="print-modal-box">
        <h3 style="margin-top: 0; color: #1e293b; border-bottom: 1px dashed #cbd5e0; padding-bottom: 10px; margin-bottom: 15px;">Edit Attendance</h3>
        <p id="modalCellDetails" style="font-size: 13px; color: #64748b; margin-bottom: 15px;"></p>
        
        <form method="POST" action="attendance_salary.php?filter_month=<?= $selected_month ?>" style="margin: 0;">
            <input type="hidden" name="action_update_cell" value="1">
            <input type="hidden" name="user_id" id="modalCellUid">
            <input type="hidden" name="date" id="modalCellDate">
            
            <div class="mb-20">
                <label class="form-label-header">Status</label>
                <select name="status" id="modalCellStatus" required class="form-input-container">
                    <option value="Present">🟢 Present (1x Wage)</option>
                    <option value="Half Day">🟡 Half Day (0.5x Wage)</option>
                    <option value="Paid Leave">🟣 Paid Leave (1x Wage)</option>
                    <option value="Weekly Off">⚪ Weekly Off (0x Wage)</option>
                    <option value="Absent">🔴 Absent (0x Wage)</option>
                </select>
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn" style="background: #e2e8f0; color: #475569;" onclick="document.getElementById('cellEditModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-start">Update Day</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL 2: Add Cash Advance -->
<div id="advanceModal" class="print-modal-overlay">
    <div class="print-modal-box">
        <h3 style="margin-top: 0; color: #1e293b; border-bottom: 1px dashed #cbd5e0; padding-bottom: 10px; margin-bottom: 15px;">Issue Cash Advance</h3>
        <p id="modalAdvanceName" style="font-size: 13px; font-weight: 700; color: #00b0ff; margin-bottom: 15px;"></p>
        
        <form method="POST" action="attendance_salary.php?filter_month=<?= $selected_month ?>" style="margin: 0;">
            <input type="hidden" name="action_add_advance" value="1">
            <input type="hidden" name="user_id" id="modalAdvanceUid">
            
            <div class="mb-15">
                <label class="form-label-header">Advance Amount (₹)</label>
                <input type="number" step="1" name="advance_amount" required placeholder="e.g. 500" class="form-input-container">
            </div>

            <div class="mb-20">
                <label class="form-label-header">Remarks / Reason</label>
                <input type="text" name="advance_remarks" placeholder="Optional context..." class="form-input-container">
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn" style="background: #e2e8f0; color: #475569;" onclick="document.getElementById('advanceModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-bill" style="background: #f59e0b; border-color: #f59e0b;">Deduct Advance</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCellModal(uid, name, date, currentStatus) {
    document.getElementById('modalCellUid').value = uid;
    document.getElementById('modalCellDate').value = date;
    
    // Set active dropdown if it exists, otherwise default to Present
    const select = document.getElementById('modalCellStatus');
    if (currentStatus) {
        select.value = currentStatus;
    } else {
        select.value = "Present";
    }

    // Format date for display natively
    const dateObj = new Date(date);
    const displayDate = dateObj.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
    
    document.getElementById('modalCellDetails').innerHTML = `Modifying record for <strong>${name}</strong> on <strong>${displayDate}</strong>.`;
    document.getElementById('cellEditModal').style.display = 'flex';
}

function openAdvanceModal(uid, name) {
    document.getElementById('modalAdvanceUid').value = uid;
    document.getElementById('modalAdvanceName').innerText = "Staff: " + name;
    document.getElementById('advanceModal').style.display = 'flex';
}
</script>

<?php include "includes/footer.php"; ?>