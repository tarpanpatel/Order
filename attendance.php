<?php
// /home/apartment/artistsfarmjaipur.com/Order/attendance.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once "config/db.php";

if (!isset($_SESSION["role"]) || !check_page_access($pdo)) {
    die("Access Denied: You do not have permission to access this area.");
}

$selected_date = isset($_GET['date']) ? trim($_GET['filter_date']) : date('Y-m-d');

// --- BATCH SAVE ATTENDANCE INPUTS ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action_save_attendance"])) {
    $target_date = $_POST["attendance_target_date"];
    $records     = $_POST["attendance_records"] ?? [];
    $remarks     = $_POST["attendance_remarks"] ?? [];

    $pdo->beginTransaction();
    try {
        $upsert_stmt = $pdo->prepare("
            INSERT INTO staff_attendance (attendance_date, user_id, status, remarks, marked_by) 
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE status = VALUES(status), remarks = VALUES(remarks), marked_by = VALUES(marked_by)
        ");
        
        $audit_stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, timestamp) VALUES (?, ?, NOW())");

        foreach ($records as $u_id => $status) {
            $u_id   = intval($u_id);
            $remark = trim($remarks[$u_id] ?? '');
            
            $upsert_stmt->execute([$target_date, $u_id, $status, $remark, $_SESSION['username']]);
        }

        $audit_stmt->execute([$_SESSION['user_id'], "User [{$_SESSION['username']}] saved manual roll-call attendance sheet parameters for date context: $target_date"]);
        $pdo->commit();
        
        $_SESSION['attendance_success'] = "✔ Attendance roster for $target_date updated successfully.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['attendance_error'] = "❌ System transaction error: " . $e->getMessage();
    }
    header("Location: attendance.php?filter_date=" . $target_date);
    exit;
}

// Fetch all registered staff members (excluding custom system profiles if any)
$staff_members = $pdo->query("SELECT id, username, role FROM users ORDER BY username ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch existing logged attendance status tokens for the target day
$existing_stmt = $pdo->prepare("SELECT user_id, status, remarks FROM staff_attendance WHERE attendance_date = ?");
$existing_stmt->execute([$selected_date]);
$existing_records = $existing_stmt->fetchAll(PDO::FETCH_UNIQUE);

include "includes/header.php";
?>

<div class="app-body" style="padding: 20px; text-align: left;">

    <?php if (isset($_SESSION['attendance_success'])): ?>
        <div class="billing-banner-success">📢 <?= htmlspecialchars($_SESSION['attendance_success']); unset($_SESSION['attendance_success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['attendance_error'])): ?>
        <div class="billing-banner-error">⚠️ <?= htmlspecialchars($_SESSION['attendance_error']); unset($_SESSION['attendance_error']); ?></div>
    <?php endif; ?>

    <div class="page-header" style="margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
        <div>
            <h2 class="page-title">📋 Staff Attendance Registry</h2>
            <p style="color: #64748b; font-size: 13px; margin-top: 4px;">Manual daily roster assignment panel for farm operators.</p>
        </div>
        <form method="GET" action="attendance.php" class="filter-form" style="margin: 0; display: flex; gap: 10px; align-items: center;">
            <label style="font-size: 13px; font-weight: 700; color: #475569;">Target Roster Date:</label>
            <input type="date" name="filter_date" value="<?= $selected_date ?>" max="<?= date('Y-m-d') ?>" class="form-input-container" style="width: auto; padding: 6px 12px;" onchange="this.form.submit()">
        </form>
    </div>

    <form method="POST" action="attendance.php" style="margin: 0;">
        <input type="hidden" name="action_save_attendance" value="1">
        <input type="hidden" name="attendance_target_date" value="<?= $selected_date ?>">

        <div class="attendance-grid">
            <?php foreach ($staff_members as $staff): 
                $s_id = $staff['id'];
                $current_status = $existing_records[$s_id]['status'] ?? 'Present';
                $current_remark = $existing_records[$s_id]['remarks'] ?? '';
                
                // Determine CSS select styling state dynamically
                $color_class = 'status-select-present';
                if ($current_status === 'Absent')   $color_class = 'status-select-absent';
                if ($current_status === 'Half Day') $color_class = 'status-select-half';
                if ($current_status === 'Paid Leave') $color_class = 'status-select-leave';
                if ($current_status === 'Weekly Off') $color_class = 'status-select-off';
            ?>
                <div class="attendance-row">
                    <div class="attendance-staff-profile">
                        <span class="attendance-staff-name"><?= htmlspecialchars($staff['username']) ?></span>
                        <span class="attendance-staff-role">Role: <?= htmlspecialchars($staff['role']) ?></span>
                    </div>

                    <div class="attendance-controls-group">
                        <input type="text" name="attendance_remarks[<?= $s_id ?>]" value="<?= htmlspecialchars($current_remark) ?>" placeholder="Optional notes (e.g., Late arrival, medical)..." class="attendance-input-remark">
                        
                        <select name="attendance_records[<?= $s_id ?>]" class="attendance-status-select <?= $color_class ?>" onchange="updateSelectColorDrop(this)">
                            <option value="Present" <?= $current_status === 'Present' ? 'selected' : '' ?>>🟢 Present</option>
                            <option value="Absent" <?= $current_status === 'Absent' ? 'selected' : '' ?>>🔴 Absent</option>
                            <option value="Half Day" <?= $current_status === 'Half Day' ? 'selected' : '' ?>>🟡 Half Day</option>
                            <option value="Paid Leave" <?= $current_status === 'Paid Leave' ? 'selected' : '' ?>>🟣 Paid Leave</option>
                            <option value="Weekly Off" <?= $current_status === 'Weekly Off' ? 'selected' : '' ?>>⚪ Weekly Off</option>
                        </select>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div style="margin-top: 25px; text-align: right;">
            <button type="submit" class="btn btn-bill" style="width: auto; min-width: 200px; padding: 12px 35px; font-size: 14px; font-weight: 800; border-radius: 8px; background: #00b0ff; border-color: #00b0ff;">
                💾 Save Attendance Roster
            </button>
        </div>
    </form>
</div>

<script>
function updateSelectColorDrop(selectElement) {
    // Strip old status modifier indicators
    selectElement.classList.remove('status-select-present', 'status-select-absent', 'status-select-half', 'status-select-leave', 'status-select-off');
    
    const val = selectElement.value;
    if (val === 'Present')    selectElement.classList.add('status-select-present');
    if (val === 'Absent')     selectElement.classList.add('status-select-absent');
    if (val === 'Half Day')   selectElement.classList.add('status-select-half');
    if (val === 'Paid Leave') selectElement.classList.add('status-select-leave');
    if (val === 'Weekly Off') selectElement.classList.add('status-select-off');
}
</script>

<?php include "includes/footer.php"; ?>