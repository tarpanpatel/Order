<?php
// /home/apartment/artistsfarmjaipur.com/Order/checkin.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "config/db.php";

if (file_exists(__DIR__ . "/config/telegram.php")) {
    require_once __DIR__ . "/config/telegram.php";
}
include_once __DIR__ . '/config/local_db_bridge.php';

if (!isset($_SESSION["role"]) || ($_SESSION["role"] !== "Admin" && $_SESSION["role"] !== "Chef")) {
    header("Location: login.php");
    exit;
}

$message = "";
$todayString = date('Y-m-d');

// --- 1. HANDLE NEW BOOKING INSERTIONS ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action_register_guest"])) {
    $phone_number = trim($_POST["phone_number"] ?? '');
    $checkin      = trim($_POST["checkin_date"] ?? '');
    $checkout     = trim($_POST["checkout_date"] ?? '');
    $notes        = trim($_POST["guest_notes"] ?? '');
    $advance      = floatval($_POST["advance_paid"] ?? 0);
    $pending      = floatval($_POST["pending_amount"] ?? 0);
    
    $booking_source      = trim($_POST["booking_source"] ?? 'Offline');
    $no_of_guests        = intval($_POST["no_of_guests"] ?? 1);
    $per_night_charges   = floatval($_POST["per_night_charges"] ?? 0); 
    $advance_received_by = trim($_POST["advance_received_by"] ?? 'Unnamed');
    $pending_received_by = trim($_POST["pending_received_by"] ?? 'Unnamed');

    if ($checkout <= $checkin) {
        $message = "❌ Error: Check-out date must be after the check-in date.";
    } elseif (!empty($checkin) && !empty($checkout) && !empty($phone_number)) {
        
        $pdo->beginTransaction();
        try {
            $check_overlap = $pdo->prepare("
                SELECT COUNT(*) FROM guests 
                WHERE status != 'CheckedOut' 
                AND NOT (expected_checkout <= CONCAT(?, ' 11:00:00') OR checkin_date >= ?)
                FOR UPDATE
            ");
            $check_overlap->execute([$checkin, $checkout]);
            
            if ($check_overlap->fetchColumn() > 0) {
                $message = "❌ Error: This date range conflicts with an active registry profile.";
                $pdo->rollBack();
            } else {
                $sql = "INSERT INTO guests (phone_number, adults, children, checkin_date, checkout_date, expected_checkout, notes, advance_paid, pending_amount, booking_source, no_of_guests, per_night_charges, total_charge, base_room_rent, advance_received_by, pending_received_by, status) 
                        VALUES (?, ?, 0, ?, ?, CONCAT(?, ' 11:00:00'), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Booked')";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $phone_number, 1, $checkin, $checkout, $checkout, $notes, $advance, $pending, 
                    $booking_source, $no_of_guests, $per_night_charges, $per_night_charges, 
                    $per_night_charges, $advance_received_by, $pending_received_by
                ]);
                $pdo->commit();
                $message = "✔ Guest reservation added to calendar database.";
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "❌ Server error occurred processing booking transaction pipeline.";
        }
    } else {
        $message = "❌ Error: Check-In, Check-Out, and Phone Number are required fields.";
    }
}

// --- 2. HANDLE SIDEBAR LEDGER ACTIVATION ENGINE ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action_sidebar_activate"])) {
    $activate_id = intval($_POST["sidebar_guest_select"] ?? 0);
    
    if ($activate_id > 0) {
        $pdo->beginTransaction();
        try {
            $pdo->query("UPDATE guests SET status = 'Booked' WHERE status = 'Active'");
            $stmt = $pdo->prepare("UPDATE guests SET status = 'Active' WHERE id = ?");
            $stmt->execute([$activate_id]);
            $pdo->commit();
            header("Location: order.php");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "❌ Activation breakdown: " . $e->getMessage();
        }
    } else {
        $message = "❌ Error: Please select a valid booked guest to activate.";
    }
}

// --- 3. HANDLE INLINE BOOKING UPDATES ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action_update_booking"])) {
    $b_id     = intval($_POST["edit_booking_id"]);
    $phone    = trim($_POST["edit_phone_number"] ?? '');
    $checkin  = trim($_POST["edit_checkin_date"] ?? '');
    $checkout = trim($_POST["edit_checkout_date"] ?? '');
    $notes    = trim($_POST["edit_guest_notes"] ?? '');
    $advance  = floatval($_POST["edit_advance_paid"] ?? 0);
    $pending  = floatval($_POST["edit_pending_amount"] ?? 0);
    
    $booking_source      = trim($_POST["edit_booking_source"] ?? 'Offline');
    $no_of_guests        = intval($_POST["edit_no_of_guests"] ?? 1);
    $per_night_charges   = floatval($_POST["edit_per_night_charges"] ?? 0);
    $advance_received_by = trim($_POST["edit_advance_received_by"] ?? 'Unnamed');
    $pending_received_by = trim($_POST["edit_pending_received_by"] ?? 'Unnamed');

    if (!empty($checkin) && !empty($checkout) && !empty($phone)) {
        $pdo->beginTransaction();
        try {
            $check_edit_overlap = $pdo->prepare("SELECT COUNT(*) FROM guests WHERE id != ? AND status != 'CheckedOut' AND NOT (expected_checkout <= CONCAT(?, ' 11:00:00') OR checkin_date >= ?) FOR UPDATE");
            $check_edit_overlap->execute([$b_id, $checkin, $checkout]);

            if ($check_edit_overlap->fetchColumn() > 0) {
                $pdo->rollBack();
                echo "<script>alert('❌ Error: These modified parameters conflict with an existing room booking timeline.'); window.location.href = 'checkin.php';</script>";
                exit;
            } else {
                $sql = "UPDATE guests SET phone_number = ?, checkin_date = ?, checkout_date = ?, expected_checkout = CONCAT(?, ' 11:00:00'), notes = ?, advance_paid = ?, pending_amount = ?, booking_source = ?, no_of_guests = ?, per_night_charges = ?, total_charge = ?, base_room_rent = ?, advance_received_by = ?, pending_received_by = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $phone, $checkin, $checkout, $checkout, $notes, $advance, $pending, 
                    $booking_source, $no_of_guests, $per_night_charges, $per_night_charges, 
                    $per_night_charges, $advance_received_by, $pending_received_by, $b_id
                ]);
                $pdo->commit();
                header("Location: checkin.php");
                exit;
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "❌ Server error modifying parameter allocations: " . $e->getMessage();
        }
    }
}

// --- 4. DATA COMPILATION FOR UI RENDERING ---
$current_active_guest = $pdo->query("SELECT * FROM guests WHERE status = 'Active' LIMIT 1")->fetch(PDO::FETCH_ASSOC);

$todaysStmt = $pdo->prepare("
    SELECT id, CONCAT('📱 (', RIGHT(phone_number, 4), ')') as guest_label 
    FROM guests 
    WHERE status = 'Booked' AND :today >= checkin_date AND :today2 < checkout_date
    ORDER BY id ASC
");
$todaysStmt->execute([':today' => $todayString, ':today2' => $todayString]);
$todays_booked_guests = $todaysStmt->fetchAll(PDO::FETCH_ASSOC);

$bookings = $pdo->query("SELECT *, DATE(checkin_date) as cid, DATE(checkout_date) as cod FROM guests WHERE status != 'CheckedOut'")->fetchAll(PDO::FETCH_ASSOC);

$disabledDatesArray = [];
foreach ($bookings as $b) {
    if (!empty($b['cid']) && !empty($b['cod'])) {
        $start = new DateTime($b['cid']);
        $end   = new DateTime($b['cod']);
        $interval = new DateInterval('P1D');
        $period   = new DatePeriod($start, $interval, $end);
        foreach ($period as $date) {
            $disabledDatesArray[] = $date->format('Y-m-d');
        }
    }
}
$disabledDatesJson = json_encode($disabledDatesArray);

$db_staff = $pdo->query("SELECT username FROM users ORDER BY username ASC")->fetchAll(PDO::FETCH_COLUMN);

// ==========================================================================
// BACKGROUND AUTOMATION: 1-DAY BEFORE ADVANCE ARRIVAL NOTIFIER REMINDER
// ==========================================================================
$lastNotificationSentDate = $_SESSION['last_reminder_broadcast_date'] ?? '';
if ($lastNotificationSentDate !== $todayString) {
    $tomorrowDateString = date('Y-m-d', strtotime('+1 day'));
    
    $tomorrowQuery = $pdo->prepare("SELECT * FROM guests WHERE DATE(checkin_date) = ? AND status = 'Booked'");
    $tomorrowQuery->execute([$tomorrowDateString]);
    $tomorrow_arrivals = $tomorrowQuery->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($tomorrow_arrivals) && function_exists('sendAdminTelegramMessage')) {
        $rem_msg = "🗓️ <b>TOMORROW'S ARRIVALS REMINDER SHEET</b>\n";
        $rem_msg .= "📅 Check-in Date: <b>" . date('d M Y', strtotime($tomorrowDateString)) . "</b>\n";
        $rem_msg .= "━━━━━━━━━━━━━━━━━━\n\n";
        
        foreach ($tomorrow_arrivals as $index => $res) {
            $num = $index + 1;
            $rem_msg .= "<b>{$num}. Phone:</b> 📱 (" . substr($res['phone_number'], -4) . ")\n";
            $rem_msg .= "• Source: " . htmlspecialchars($res['booking_source']) . " | Headcount: " . $res['no_of_guests'] . " Pax\n";
            $rem_msg .= "• Total Tariff: ₹" . number_format($res['per_night_charges'], 2) . "\n";
            $rem_msg .= "• Advance Paid: ₹" . number_format($res['advance_paid'], 2) . " (" . htmlspecialchars($res['advance_received_by']) . ")\n";
            if (!empty($res['notes'])) {
                $rem_msg .= "• <i>Notes: " . htmlspecialchars($res['notes']) . "</i>\n";
            }
            $rem_msg .= "⎯⎯⎯⎯⎯⎯⎯⎯⎯⎯⎯⎯⎯⎯⎯\n";
        }
        
        sendAdminTelegramMessage($rem_msg);
    }
    $_SESSION['last_reminder_broadcast_date'] = $todayString;
}
// ==========================================================================

include "includes/header.php";
?>

<style>
.split-registration-container { display: grid !important; grid-template-columns: 400px 1fr !important; gap: 25px !important; width: 100% !important; align-items: start !important; }
.form-registration-panel, .calendar-display-panel { background: #ffffff !important; border: 1px solid #e2e8f0 !important; border-radius: 12px !important; padding: 24px !important; box-shadow: 0 1px 3px rgba(0,0,0,0.05) !important; }
.input-field-group { display: flex; flex-direction: column; gap: 5px; margin-bottom: 14px; text-align: left; }
.input-field-group label { font-size: 12px; font-weight: 600; color: #111827; }
.input-field-group input, .input-field-group textarea, .input-field-group select { padding: 10px 12px; font-size: 14px; border: 1px solid #cbd5e0; border-radius: 8px; outline: none; background: #fff; color: #111827; width: 100%; box-sizing: border-box;}
.input-field-group input:focus, .input-field-group textarea:focus, .input-field-group select:focus { border-color: #06b6d4; }
.calendar-grid-header { display: grid; grid-template-columns: repeat(7, 1fr); gap: 6px; text-align: center; font-weight: 700; font-size: 12px; color: #4b5563; text-transform: uppercase; margin-bottom: 10px; }
.calendar-days-matrix { display: grid; grid-template-columns: repeat(7, 1fr); gap: 6px; }
.calendar-day-cell { border: 1px solid #e2e8f0; border-radius: 8px; min-height: 85px; padding: 6px; text-align: left; position: relative; background: #fff; box-sizing: border-box; }
.calendar-day-cell .day-number { font-size: 12px; font-weight: 700; color: #9ca3af; }
.calendar-day-cell.current-month .day-number { color: #111827; }
.calendar-day-cell.today-accent { border-color: #06b6d4; background: #f0fdfa; }
.calendar-day-cell.today-accent .day-number { color: #06b6d4; }
.booking-strip-tag { color: white; font-size: 10px; font-weight: 700; padding: 3px 6px; border-radius: 4px; margin-top: 4px; cursor: pointer; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block; background: #4b5563; }
.booking-strip-tag.live-active { background: #38a169 !important; }
@media (max-width: 1023px) { .split-registration-container { grid-template-columns: 1fr !important; } }
</style>

<div class="app-body" style="max-width: 100% !important; width: 100% !important; display: block !important;">
    
    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 15px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <div style="text-align: left;">
            <span style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: #64748b; letter-spacing: 0.5px;">Active Session Context</span>
            <div style="font-size: 14px; font-weight: 600; color: #0f172a; margin-top: 2px;">
                Current Active: <?php echo $current_active_guest ? "📱 (" . substr($current_active_guest['phone_number'], -4) . ")" : "<span style='color:#94a3b8;'>None Selected</span>"; ?>
            </div>
        </div>
        <form method="POST" action="checkin.php" style="display: flex; gap: 10px; margin: 0; align-items: center;">
            <input type="hidden" name="action_sidebar_activate" value="1">
            <select name="sidebar_guest_select" style="padding: 6px 10px; font-size: 13px; border-radius: 6px; border: 1px solid #cbd5e0; background: #fff; min-width: 180px;">
                <?php if (empty($todays_booked_guests)): ?>
                    <option value="0">No bookings arriving today</option>
                <?php else: ?>
                    <option value="0">Select Today's Arrival</option>
                    <?php foreach ($todays_booked_guests as $tg): ?>
                        <option value="<?= $tg['id'] ?>"><?= htmlspecialchars($tg['guest_label']) ?></option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
            <button type="submit" style="padding: 6px 12px; background: #38a169; color: #fff; border: none; font-weight: 600; border-radius: 6px; font-size: 13px; cursor: pointer;">Activate Ledger</button>
        </form>
    </div>

    <?php if(!empty($message)): ?>
        <div style="background:#f0fdf4; border:1px solid #bbf7d0; color:#166534; padding:14px; border-radius:8px; margin-bottom:20px; font-size:14px; text-align:left; font-weight:500;"><?= $message ?></div>
    <?php endif; ?>

    <div class="split-registration-container">
        <div class="form-registration-panel">
            <h3 style="font-size: 14px; font-weight: 700; text-transform: uppercase; color: #111827; text-align: left; padding-bottom: 8px; border-bottom: 1px dashed #e2e8f0; margin-bottom: 15px;">Add Guest Booking</h3>
            <form method="POST" action="checkin.php" style="margin: 0;">
                <input type="hidden" name="action_register_guest" value="1">
                
                <div class="input-field-group"><label>Contact Phone Number *</label><input type="text" name="phone_number" required placeholder="Enter mobile number"></div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div class="input-field-group">
                        <label>Booking Source</label>
                        <select name="booking_source">
                            <option value="Offline">Offline</option>
                            <option value="Airbnb">Airbnb</option>
                        </select>
                    </div>
                    <div class="input-field-group"><label>Total Headcount</label><input type="number" name="no_of_guests" value="1" min="1"></div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div class="input-field-group"><label>Check-In Date *</label><input type="date" id="fieldCheckin" name="checkin_date" min="<?php echo $todayString; ?>" required onchange="handleDateAutoLock()"></div>
                    <div class="input-field-group"><label>Check-Out Date *</label><input type="date" id="fieldCheckout" name="checkout_date" required onchange="validateCheckoutDate(this)"></div>
                </div>
                
                <div class="input-field-group"><label>Total Tariff (₹)</label><input type="number" id="fieldTotalTariff" name="per_night_charges" value="0" min="0" oninput="autoCalculatePendingBalance('field')"></div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div class="input-field-group"><label>Advance Paid (₹)</label><input type="number" id="fieldAdvancePaid" name="advance_paid" value="0" min="0" oninput="autoCalculatePendingBalance('field')"></div>
                    <div class="input-field-group">
                        <label>Advance Received By</label>
                        <select name="advance_received_by" required>
                            <option value="">-- Choose Collector --</option>
                            <?php if (!empty($db_staff)): foreach ($db_staff as $staff_name): ?>
                                <option value="<?= htmlspecialchars($staff_name) ?>"><?= htmlspecialchars($staff_name) ?></option>
                            <?php endforeach; else: ?>
                                <option value="Unnamed">Unnamed</option>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div class="input-field-group"><label>Pending Balance (₹)</label><input type="number" id="fieldPendingBalance" name="pending_amount" value="0" min="0"></div>
                    <div class="input-field-group">
                        <label>Pending Received By</label>
                        <select name="pending_received_by" required>
                            <option value="">-- Choose Collector --</option>
                            <?php if (!empty($db_staff)): foreach ($db_staff as $staff_name): ?>
                                <option value="<?= htmlspecialchars($staff_name) ?>"><?= htmlspecialchars($staff_name) ?></option>
                            <?php endforeach; else: ?>
                                <option value="Unnamed">Unnamed</option>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>

                <div class="input-field-group" style="margin-bottom: 20px;"><label>Guest Notes</label><textarea name="guest_notes" rows="2" placeholder="Dietary adjustments..."></textarea></div>
                <button type="submit" class="btn btn-start" style="width: 100%; padding: 14px; font-size: 13px; font-weight: bold; border-radius: 8px;">Save Guest Booking</button>
            </form>
        </div>

        <div class="calendar-display-panel">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="font-size: 15px; font-weight: 700; text-transform: uppercase; color: #111827; margin: 0;"><?= date('F Y') ?></h3>
                <span style="font-size: 12px; color:#6b7280; font-weight:600;">Active Tracking Matrix</span>
            </div>
            <div class="calendar-grid-header"><div>Sun</div><div>Mon</div><div>Tue</div><div>Wed</div><div>Thu</div><div>Fri</div><div>Sat</div></div>
            <div class="calendar-days-matrix">
                <?php
                $year = intval(date('Y')); $month = intval(date('m'));
                $firstDayUnix = mktime(0, 0, 0, $month, 1, $year);
                $daysInMonth = intval(date('t', $firstDayUnix)); $dayOfWeek = intval(date('w', $firstDayUnix));

                for ($x = 0; $x < $dayOfWeek; $x++) { echo '<div class="calendar-day-cell" style="background:#f9fafb;"><span class="day-number"></span></div>'; }
                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $currentDateLoopStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
                    $isToday = ($currentDateLoopStr === $todayString) ? 'today-accent' : '';
                    echo '<div class="calendar-day-cell current-month ' . $isToday . '"><span class="day-number">' . $day . '</span>';
                    foreach ($bookings as $b) {
                        if ($currentDateLoopStr >= $b['cid'] && $currentDateLoopStr < $b['cod']) {
                            $jsonCleanStr = htmlspecialchars(json_encode($b), ENT_QUOTES, 'UTF-8');
                            $activeClass = ($b['status'] === 'Active') ? 'live-active' : '';
                            echo '<span class="booking-strip-tag ' . $activeClass . '" onclick=\'openDetailsModal(' . $jsonCleanStr . ')\'>🛎 (' . substr($b['phone_number'], -4) . ')</span>';
                        }
                    }
                    echo '</div>';
                }
                ?>
            </div>
        </div>
    </div>
</div>

<div id="bookingDetailsModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 99999; justify-content: center; align-items: center; backdrop-filter: blur(4px);">
    <div class="modal-content" style="background: white; max-width: 520px; width: 90%; border-radius: 12px; padding: 25px; position: relative; box-shadow: 0 10px 25px rgba(0,0,0,0.15); color: #111827;">
        <span style="position: absolute; top: 12px; right: 16px; font-size: 22px; cursor: pointer; color: #a0aec0;" onclick="closeDetailsModal()">✕</span>
        
        <div id="modalReadView">
            <h3 style="font-size: 16px; font-weight: 700; text-transform: uppercase; margin-bottom: 15px; border-bottom: 1px dashed #e2e8f0; padding-bottom: 8px;">Residency Tracking Sheet <span id="lblStatusBadge"></span></h3>
            <table style="width: 100%; font-size: 14px; text-align: left; border-collapse: collapse; margin-bottom: 20px;">
                <tr><th style="padding: 4px 0; color: #4b5563;">Contact Phone:</th><td id="lblPhone"></td></tr>
                <tr><th style="padding: 4px 0; color: #4b5563;">Channel Source:</th><td id="lblSource"></td></tr>
                <tr><th style="padding: 4px 0; color: #4b5563;">Headcount Group:</th><td><span id="lblGuestsCount"></span> Persons</td></tr>
                <tr><th style="padding: 4px 0; color: #4b5563;">Duration Stay:</th><td><span id="lblCheckin" style="font-weight:600;"></span> to <span id="lblCheckout" style="font-weight:600;"></span></td></tr>
                <tr><th style="padding: 4px 0; color: #4b5563;">Total Tariff:</th><td>₹<span id="lblRate"></span></td></tr>
                <tr><th style="padding: 4px 0; color: #4b5563;">Advance Ledger:</th><td><span style="color:#38a169; font-weight:700;">₹<span id="lblAdvance"></span></span> (<span id="lblAdvanceBy"></span>)</td></tr>
                <tr><th style="padding: 4px 0; color: #4b5563;">Pending Ledger:</th><td><span style="color:#e53e3e; font-weight:700;">₹<span id="lblPending"></span></span> (<span id="lblPendingBy"></span>)</td></tr>
                <tr><th style="padding: 4px 0; color: #4b5563; vertical-align: top;">Guest Notes:</th><td id="lblNotes" style="font-style: italic; color:#4a5568;"></td></tr>
            </table>
            <div style="display: flex; gap: 10px; justify-content: flex-end; border-top: 1px dashed #e2e8f0; padding-top: 15px;">
                <button type="button" class="btn btn-log" style="padding: 10px 16px;" onclick="closeDetailsModal()">Close</button>
                <button type="button" class="btn btn-bill" style="padding: 10px 16px; border-radius: 8px;" onclick="switchToEditMode()">✏ Edit Parameters</button>
            </div>
        </div>

        <div id="modalEditView" style="display: none;">
            <h3 style="font-size: 16px; font-weight: 700; text-transform: uppercase; margin-bottom: 15px;">Modify Dynamic Parameters</h3>
            <form method="POST" action="checkin.php" style="margin: 0;">
                <input type="hidden" name="action_update_booking" value="1"><input type="hidden" name="edit_booking_id" id="txtEditId">
                <div style="display: flex; flex-direction: column; gap: 12px; max-height: 60vh; overflow-y: auto; padding-right: 4px;" class="input-field-group">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div><label>Contact Phone *</label><input type="text" name="edit_phone_number" id="txtEditPhone" required></div>
                        <div>
                            <label>Booking Source</label>
                            <select name="edit_booking_source" id="txtEditSource">
                                <option value="Offline">Offline</option>
                                <option value="Airbnb">Airbnb</option>
                            </select>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div><label>No. of Guests</label><input type="number" name="edit_no_of_guests" id="txtEditGuestsCount" min="1"></div>
                        <div><label>Total Tariff (₹)</label><input type="number" name="edit_per_night_charges" id="txtEditRate" min="0" oninput="autoCalculatePendingBalance('edit')"></div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div><label>Check-In Date *</label><input type="date" name="edit_checkin_date" id="txtEditCheckin" min="<?php echo $todayString; ?>" required onchange="handleEditDateAutoLock()"></div>
                        <div><label>Check-Out Date *</label><input type="date" name="edit_checkout_date" id="txtEditCheckout" required></div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div><label>Advance Paid (₹)</label><input type="number" name="edit_advance_paid" id="txtEditAdvance" oninput="autoCalculatePendingBalance('edit')"></div>
                        <div>
                            <label>Advance Received By</label>
                            <select name="edit_advance_received_by" id="txtEditAdvanceBy" required>
                                <option value="">-- Choose Collector --</option>
                                <?php if (!empty($db_staff)): foreach ($db_staff as $staff_name): ?>
                                    <option value="<?= htmlspecialchars($staff_name) ?>"><?= htmlspecialchars($staff_name) ?></option>
                                <?php endforeach; else: ?>
                                    <option value="Unnamed">Unnamed</option>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div><label>Pending Balance (₹)</label><input type="number" name="edit_pending_amount" id="txtEditPending"></div>
                        <div>
                            <label>Pending Received By</label>
                            <select name="edit_pending_received_by" id="txtEditPendingBy" required>
                                <option value="">-- Choose Collector --</option>
                                <?php if (!empty($db_staff)): foreach ($db_staff as $staff_name): ?>
                                    <option value="<?= htmlspecialchars($staff_name) ?>"><?= htmlspecialchars($staff_name) ?></option>
                                <?php endforeach; else: ?>
                                    <option value="Unnamed">Unnamed</option>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                    <div><label>Notes</label><textarea name="edit_guest_notes" id="txtEditNotes" rows="2"></textarea></div>
                </div>
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;"><button type="button" class="btn btn-log" style="padding: 10px 20px;" onclick="switchToReadMode()">Back</button><button type="submit" class="btn btn-start" style="padding: 10px 20px;">Commit Updates</button></div>
            </form>
        </div>
    </div>
</div>

<script>
const blacklistedBookedDates = <?php echo $disabledDatesJson; ?>;
let currentActiveSelectedBookingObject = null;

function setSystemDefaultFormTimestamps() {
    const checkinInput = document.getElementById("fieldCheckin");
    if (!checkinInput.value) {
        const now = new Date();
        checkinInput.value = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
        handleDateAutoLock();
    }
}

function handleDateAutoLock() {
    const checkinInput = document.getElementById("fieldCheckin");
    const checkoutInput = document.getElementById("fieldCheckout");
    if (!checkinInput.value) return;

    let baseDate = new Date(checkinInput.value);
    baseDate.setDate(baseDate.getDate() + 1);
    
    const nextDayString = `${baseDate.getFullYear()}-${String(baseDate.getMonth() + 1).padStart(2, '0')}-${String(baseDate.getDate()).padStart(2, '0')}`;
    checkoutInput.min = nextDayString;
    checkoutInput.value = nextDayString;
}

function handleEditDateAutoLock() {
    const checkinInput = document.getElementById("txtEditCheckin");
    const checkoutInput = document.getElementById("txtEditCheckout");
    if (!checkinInput.value) return;

    let baseDate = new Date(checkinInput.value);
    baseDate.setDate(baseDate.getDate() + 1);
    
    const nextDayString = `${baseDate.getFullYear()}-${String(baseDate.getMonth() + 1).padStart(2, '0')}-${String(baseDate.getDate()).padStart(2, '0')}`;
    checkoutInput.min = nextDayString;
}

function autoCalculatePendingBalance(prefix) {
    const tariff = parseFloat(document.getElementById(prefix === 'field' ? 'fieldTotalTariff' : 'txtEditRate').value) || 0;
    const advance = parseFloat(document.getElementById(prefix === 'field' ? 'fieldAdvancePaid' : 'txtEditAdvance').value) || 0;
    const balanceField = document.getElementById(prefix === 'field' ? 'fieldPendingBalance' : 'txtEditPending');
    
    balanceField.value = tariff - advance;
}

function validateCheckoutDate(checkoutInput) {
    const checkinVal = document.getElementById("fieldCheckin").value;
    if (checkoutInput.value <= checkinVal) {
        alert("❌ Error: Check-out date must be at least 1 day after the check-in date.");
        handleDateAutoLock();
        return;
    }
    validateInputSelectionOverlap(checkoutInput);
}

function validateInputSelectionOverlap(inputEl) {
    if (blacklistedBookedDates.includes(inputEl.value)) {
        alert("❌ Warning: The date " + inputEl.value + " is already booked!");
        inputEl.value = "";
    }
}

function openDetailsModal(bookingData) {
    currentActiveSelectedBookingObject = bookingData;
    document.getElementById("lblSource").innerText      = bookingData.booking_source || 'Offline';
    document.getElementById("lblPhone").innerText       = bookingData.phone_number || '0000000000';
    document.getElementById("lblGuestsCount").innerText = bookingData.no_of_guests || 1;
    document.getElementById("lblCheckin").innerText     = bookingData.cid;
    document.getElementById("lblCheckout").innerText    = bookingData.cod;
    document.getElementById("lblRate").innerText        = parseFloat(bookingData.per_night_charges || 0).toFixed(2);
    document.getElementById("lblAdvance").innerText     = parseFloat(bookingData.advance_paid || 0).toFixed(2);
    document.getElementById("lblAdvanceBy").innerText   = bookingData.advance_received_by || 'Unnamed';
    document.getElementById("lblPending").innerText     = parseFloat(bookingData.pending_amount || 0).toFixed(2);
    document.getElementById("lblPendingBy").innerText   = bookingData.pending_received_by || 'Unnamed';
    document.getElementById("lblNotes").innerText       = bookingData.notes ? bookingData.notes : "None";

    const badge = document.getElementById("lblStatusBadge");
    if (bookingData.status === "Active") {
        badge.innerText = "Live Active Ledger";
        badge.style.cssText = "font-size:11px; padding:2px 6px; background:#f0fdf4; color:#166534; border-radius:4px; font-weight:bold; margin-left:8px;";
    } else {
        badge.innerText = "Holding Booking";
        badge.style.cssText = "font-size:11px; padding:2px 6px; background:#f3f4f6; color:#4b5563; border-radius:4px; font-weight:bold; margin-left:8px;";
    }
    switchToReadMode();
    document.getElementById("bookingDetailsModal").style.display = "flex";
}

function closeDetailsModal() { document.getElementById("bookingDetailsModal").style.display = "none"; }

function switchToEditMode() {
    if (!currentActiveSelectedBookingObject) return;
    document.getElementById("txtEditId").value          = currentActiveSelectedBookingObject.id;
    document.getElementById("txtEditPhone").value       = currentActiveSelectedBookingObject.phone_number;
    document.getElementById("txtEditSource").value      = currentActiveSelectedBookingObject.booking_source || 'Offline';
    document.getElementById("txtEditGuestsCount").value = currentActiveSelectedBookingObject.no_of_guests || 1;
    document.getElementById("txtEditAdvance").value     = currentActiveSelectedBookingObject.advance_paid;
    document.getElementById("txtEditAdvanceBy").value   = currentActiveSelectedBookingObject.advance_received_by || 'Unnamed';
    document.getElementById("txtEditPending").value     = currentActiveSelectedBookingObject.pending_amount;
    document.getElementById("txtEditPendingBy").value   = currentActiveSelectedBookingObject.pending_received_by || 'Unnamed';
    document.getElementById("txtEditRate").value        = currentActiveSelectedBookingObject.per_night_charges || 0;
    document.getElementById("txtEditNotes").value       = currentActiveSelectedBookingObject.notes;
    document.getElementById("txtEditCheckin").value     = currentActiveSelectedBookingObject.cid.substring(0, 10);
    document.getElementById("txtEditCheckout").value    = currentActiveSelectedBookingObject.cod.substring(0, 10);
    
    handleEditDateAutoLock();
    
    document.getElementById("modalReadView").style.display = "none";
    document.getElementById("modalEditView").style.display = "block";
}

function switchToReadMode() { document.getElementById("modalEditView").style.display = "none"; document.getElementById("modalReadView").style.display = "block"; }

window.addEventListener('DOMContentLoaded', setSystemDefaultFormTimestamps);
</script>
<?php include "includes/footer.php"; ?>