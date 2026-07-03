<?php
session_start();
require_once "config/db.php";

// Allow access if they are logged in as either Admin or Chef
if (!isset($_SESSION["role"]) || ($_SESSION["role"] !== "Admin" && $_SESSION["role"] !== "Chef")) {
    header("Location: login.php");
    exit;
}

$message = "";

// --- 1. HANDLE NEW BOOKING INSERTIONS WITH SIMULTANEOUS MUTEX LOCKS ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action_register_guest"])) {
    $guest_name   = trim($_POST["guest_name"] ?? '') ?: "Walk-In Guest";
    $phone_number = trim($_POST["phone_number"] ?? '');
    $adults       = intval($_POST["adults"] ?? 1);
    $children     = intval($_POST["children"] ?? 0);
    $checkin      = trim($_POST["checkin_date"] ?? '');
    $checkout     = trim($_POST["checkout_date"] ?? '');
    $notes        = trim($_POST["guest_notes"] ?? '');
    $advance      = floatval($_POST["advance_paid"] ?? 0);
    $pending      = floatval($_POST["pending_amount"] ?? 0);

    if (!empty($checkin) && !empty($checkout) && !empty($phone_number)) {
        
        $pdo->beginTransaction();
        try {
            // Secure rows to block concurrent write actions from duplicate window clicks
            $check_overlap = $pdo->prepare("SELECT COUNT(*) FROM guests WHERE status != 'CheckedOut' AND NOT (expected_checkout <= ? OR checkin_date >= ?) FOR UPDATE");
            $check_overlap->execute([$checkin, $checkout]);
            
            if ($check_overlap->fetchColumn() > 0) {
                $message = "❌ Error: This date is already booked. Only one set of guests can be registered at a time.";
                $pdo->rollBack();
            } else {
                $stmt = $pdo->prepare("INSERT INTO guests (guest_name, phone_number, adults, children, checkin_date, expected_checkout, notes, advance_paid, pending_amount, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Booked')");
                $stmt->execute([$guest_name, $phone_number, $adults, $children, $checkin, $checkout, $notes, $advance, $pending]);
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
    $g_name   = trim($_POST["edit_guest_name"] ?? '');
    $phone    = trim($_POST["edit_phone_number"] ?? '');
    $adults   = intval($_POST["edit_adults"] ?? 1);
    $children = intval($_POST["edit_children"] ?? 0);
    $checkin  = trim($_POST["edit_checkin_date"] ?? '');
    $checkout = trim($_POST["edit_checkout_date"] ?? '');
    $notes    = trim($_POST["edit_guest_notes"] ?? '');
    $advance  = floatval($_POST["edit_advance_paid"] ?? 0);
    $pending  = floatval($_POST["edit_pending_amount"] ?? 0);

    if (!empty($checkin) && !empty($checkout) && !empty($phone)) {
        $stmt = $pdo->prepare("UPDATE guests SET guest_name = ?, phone_number = ?, adults = ?, children = ?, checkin_date = ?, expected_checkout = ?, notes = ?, advance_paid = ?, pending_amount = ? WHERE id = ?");
        $stmt->execute([$g_name, $phone, $adults, $children, $checkin, $checkout, $notes, $advance, $pending, $b_id]);
        header("Location: checkin.php");
        exit;
    }
}

// --- 4. DATA COMPILATION FOR UI RENDERING ---
$current_active_guest = $pdo->query("SELECT * FROM guests WHERE status = 'Active' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$all_booked_guests = $pdo->query("SELECT id, guest_name FROM guests WHERE status = 'Booked' ORDER BY checkin_date ASC")->fetchAll(PDO::FETCH_ASSOC);
$bookings = $pdo->query("SELECT id, guest_name, phone_number, adults, children, DATE(checkin_date) as cid, DATE(expected_checkout) as cod, notes, advance_paid, pending_amount, status FROM guests WHERE status != 'CheckedOut'")->fetchAll(PDO::FETCH_ASSOC);

include "includes/header.php";
?>

<style>
.split-registration-container { display: grid !important; grid-template-columns: 400px 1fr !important; gap: 25px !important; width: 100% !important; align-items: start !important; }
.form-registration-panel, .calendar-display-panel { background: #ffffff !important; border: 1px solid #e2e8f0 !important; border-radius: 12px !important; padding: 24px !important; box-shadow: 0 1px 3px rgba(0,0,0,0.05) !important; }
.input-field-group { display: flex; flex-direction: column; gap: 5px; margin-bottom: 14px; text-align: left; }
.input-field-group label { font-size: 12px; font-weight: 600; color: #111827; }
.input-field-group input, .input-field-group textarea { padding: 10px 12px; font-size: 14px; border: 1px solid #cbd5e0; border-radius: 8px; outline: none; background: #fff; color: #111827; width: 100%; box-sizing: border-box;}
.input-field-group input:focus, .input-field-group textarea:focus { border-color: #06b6d4; }
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
    <div class="category-section" style="margin-bottom: 20px;"><h2 class="category-title" style="text-transform: none;">📝 Guest Registration Suite</h2></div>

    <?php if(!empty($message)): ?>
        <div style="background:#f0fdf4; border:1px solid #bbf7d0; color:#166534; padding:14px; border-radius:8px; margin-bottom:20px; font-size:14px; text-align:left; font-weight:500;"><?= $message ?></div>
    <?php endif; ?>

    <div class="split-registration-container">
        <div class="form-registration-panel">
            <h3 style="font-size: 14px; font-weight: 700; text-transform: uppercase; color: #111827; text-align: left; padding-bottom: 8px; border-bottom: 1px dashed #e2e8f0; margin-bottom: 15px;">Add Guest to Calendar</h3>
            <form method="POST" action="checkin.php" style="margin: 0;">
                <input type="hidden" name="action_register_guest" value="1">
                <div class="input-field-group"><label>Primary Guest Name (Optional)</label><input type="text" name="guest_name" placeholder="Walk-In Guest"></div>
                <div class="input-field-group"><label>Contact Phone Number *</label><input type="text" name="phone_number" required placeholder="Enter active mobile number"></div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div class="input-field-group"><label>Adults</label><input type="number" name="adults" value="1" min="1"></div>
                    <div class="input-field-group"><label>Children</label><input type="number" name="children" value="0" min="0"></div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div class="input-field-group"><label>Check-In Date *</label><input type="date" id="fieldCheckin" name="checkin_date" required onchange="handleDateAutoLock()"></div>
                    <div class="input-field-group"><label>Expected Check-Out *</label><input type="date" id="fieldCheckout" name="checkout_date" required></div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div class="input-field-group"><label>Advance Paid (₹)</label><input type="number" name="advance_paid" value="0" min="0"></div>
                    <div class="input-field-group"><label>Pending Balance (₹)</label><input type="number" name="pending_amount" value="0" min="0"></div>
                </div>
                <div class="input-field-group" style="margin-bottom: 20px;"><label>Guest Notes / Special Layout Requests</label><textarea name="guest_notes" rows="2" placeholder="Dietary preferences, details..."></textarea></div>
                <button type="submit" class="btn btn-start" style="width: 100%; padding: 14px; font-size: 13px; font-weight: bold; border-radius: 8px;">Save Guest & Add to Calendar</button>
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
                $todayString = date('Y-m-d');

                for ($x = 0; $x < $dayOfWeek; $x++) { echo '<div class="calendar-day-cell" style="background:#f9fafb;"><span class="day-number"></span></div>'; }
                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $currentDateLoopStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
                    $isToday = ($currentDateLoopStr === $todayString) ? 'today-accent' : '';
                    echo '<div class="calendar-day-cell current-month ' . $isToday . '"><span class="day-number">' . $day . '</span>';
                    foreach ($bookings as $b) {
                        if ($currentDateLoopStr >= $b['cid'] && $currentDateLoopStr < $b['cod']) {
                            $jsonCleanStr = htmlspecialchars(json_encode($b), ENT_QUOTES, 'UTF-8');
                            $activeClass = ($b['status'] === 'Active') ? 'live-active' : '';
                            echo '<span class="booking-strip-tag ' . $activeClass . '" onclick=\'openDetailsModal(' . $jsonCleanStr . ')\'>🛎 ' . htmlspecialchars($b['guest_name']) . '</span>';
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
            <h3 style="font-size: 16px; font-weight: 700; text-transform: uppercase; margin-bottom: 15px; border-bottom: 1px dashed #e2e8f0; padding-bottom: 8px;">Residency Tracking Sheet</h3>
            <table style="width: 100%; font-size: 14px; text-align: left; border-collapse: collapse; margin-bottom: 20px;">
                <tr><th style="padding: 6px 0; color: #4b5563;">Guest Profile:</th><td><strong id="lblGuestName"></strong> <span id="lblStatusBadge"></span></td></tr>
                <tr><th style="padding: 6px 0; color: #4b5563;">Contact Phone:</th><td id="lblPhone"></td></tr>
                <tr><th style="padding: 6px 0; color: #4b5563;">Head Count:</th><td>Adults: <span id="lblAdults"></span> | Children: <span id="lblChildren"></span></td></tr>
                <tr><th style="padding: 6px 0; color: #4b5563;">Duration Stay:</th><td><span id="lblCheckin" style="font-weight:600;"></span> to <span id="lblCheckout" style="font-weight:600;"></span></td></tr>
                <tr><th style="padding: 6px 0; color: #4b5563;">Advance Ledger:</th><td><span style="color:#38a169; font-weight:700;">₹<span id="lblAdvance"></span></span> Paid</td></tr>
                <tr><th style="padding: 6px 0; color: #4b5563;">Pending Ledger:</th><td><span style="color:#e53e3e; font-weight:700;">₹<span id="lblPending"></span></span> Balance Due</td></tr>
                <tr><th style="padding: 6px 0; color: #4b5563; vertical-align: top;">Guest Notes:</th><td id="lblNotes" style="font-style: italic; color:#4a5568;"></td></tr>
            </table>
            <div style="display: flex; gap: 10px; justify-content: flex-end; border-top: 1px dashed #e2e8f0; padding-top: 15px;">
                <button type="button" class="btn btn-log" style="padding: 10px 16px;" onclick="closeDetailsModal()">Close</button>
                <button type="button" class="btn btn-bill" style="padding: 10px 16px; border-radius: 8px;" onclick="switchToEditMode()">✏ Edit Parameters</button>
            </div>
        </div>

        <div id="modalEditView" style="display: none;">
            <h3 style="font-size: 16px; font-weight: 700; text-transform: uppercase; margin-bottom: 15px;">Modify Parameters</h3>
            <form method="POST" action="checkin.php" style="margin: 0;">
                <input type="hidden" name="action_update_booking" value="1"><input type="hidden" name="edit_booking_id" id="txtEditId">
                <div style="display: flex; flex-direction: column; gap: 12px; max-height: 60vh; overflow-y: auto; padding-right: 4px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div><label style="font-size:12px; font-weight:600; color:#4b5563;">Guest Name</label><input type="text" name="edit_guest_name" id="txtEditName" style="width:100%; padding:8px; border:1px solid #cbd5e0; border-radius:6px;"></div>
                        <div><label style="font-size:12px; font-weight:600; color:#4b5563;">Contact Phone *</label><input type="text" name="edit_phone_number" id="txtEditPhone" required style="width:100%; padding:8px; border:1px solid #cbd5e0; border-radius:6px;"></div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div><label style="font-size:12px; font-weight:600; color:#4b5563;">Check-In Date *</label><input type="date" name="edit_checkin_date" id="txtEditCheckin" required style="width:100%; padding:8px; border:1px solid #cbd5e0; border-radius:6px;"></div>
                        <div><label style="font-size:12px; font-weight:600; color:#4b5563;">Check-Out Date *</label><input type="date" name="edit_checkout_date" id="txtEditCheckout" required style="width:100%; padding:8px; border:1px solid #cbd5e0; border-radius:6px;"></div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div><label style="font-size:12px; font-weight:600; color:#4b5563;">Adults</label><input type="number" name="edit_adults" id="txtEditAdults" style="width:100%; padding:8px; border:1px solid #cbd5e0; border-radius:6px;" min="1"></div>
                        <div><label style="font-size:12px; font-weight:600; color:#4b5563;">Children</label><input type="number" name="edit_children" id="txtEditChildren" style="width:100%; padding:8px; border:1px solid #cbd5e0; border-radius:6px;" min="0"></div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div><label style="font-size:12px; font-weight:600; color:#4b5563;">Advance Paid (₹)</label><input type="number" name="edit_advance_paid" id="txtEditAdvance" style="width:100%; padding:8px; border:1px solid #cbd5e0; border-radius:6px;"></div>
                        <div><label style="font-size:12px; font-weight:600; color:#4b5563;">Pending (₹)</label><input type="number" name="edit_pending_amount" id="txtEditPending" style="width:100%; padding:8px; border:1px solid #cbd5e0; border-radius:6px;"></div>
                    </div>
                    <div><label style="font-size:12px; font-weight:600; color:#4b5563;">Notes</label><textarea name="edit_guest_notes" id="txtEditNotes" rows="2" style="width:100%; padding:8px; border:1px solid #cbd5e0; border-radius:6px; font-family:inherit; resize:none;"></textarea></div>
                </div>
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;"><button type="button" class="btn btn-log" style="padding: 10px 20px;" onclick="switchToReadMode()">Back</button><button type="submit" class="btn btn-start" style="padding: 10px 20px;">Commit Updates</button></div>
            </form>
        </div>
    </div>
</div>

<script>
function setSystemDefaultFormTimestamps() {
    const checkinInput = document.getElementById("fieldCheckin");
    if (!checkinInput.value) {
        const now = new Date();
        checkinInput.value = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
    }
    handleDateAutoLock();
}

function handleDateAutoLock() {
    const checkinInput = document.getElementById("fieldCheckin");
    const checkoutInput = document.getElementById("fieldCheckout");
    if (!checkinInput.value) return;
    let dateObj = new Date(checkinInput.value);
    dateObj.setDate(dateObj.getDate() + 1);
    checkoutInput.value = `${dateObj.getFullYear()}-${String(dateObj.getMonth() + 1).padStart(2, '0')}-${String(dateObj.getDate()).padStart(2, '0')}`;
}

function openDetailsModal(bookingData) {
    currentActiveSelectedBookingObject = bookingData;
    document.getElementById("lblGuestName").innerText = bookingData.guest_name;
    document.getElementById("lblPhone").innerText     = bookingData.phone_number;
    document.getElementById("lblAdults").innerText    = bookingData.adults;
    document.getElementById("lblChildren").innerText  = bookingData.children;
    document.getElementById("lblCheckin").innerText   = bookingData.cid;
    document.getElementById("lblCheckout").innerText  = bookingData.cod;
    document.getElementById("lblAdvance").innerText   = parseFloat(bookingData.advance_paid).toFixed(0);
    document.getElementById("lblPending").innerText   = parseFloat(bookingData.pending_amount).toFixed(0);
    document.getElementById("lblNotes").innerText     = bookingData.notes ? bookingData.notes : "None";

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
    document.getElementById("txtEditId").value       = currentActiveSelectedBookingObject.id;
    document.getElementById("txtEditName").value     = currentActiveSelectedBookingObject.guest_name;
    document.getElementById("txtEditPhone").value    = currentActiveSelectedBookingObject.phone_number;
    document.getElementById("txtEditAdults").value   = currentActiveSelectedBookingObject.adults;
    document.getElementById("txtEditChildren").value = currentActiveSelectedBookingObject.children;
    document.getElementById("txtEditAdvance").value  = currentActiveSelectedBookingObject.advance_paid;
    document.getElementById("txtEditPending").value  = currentActiveSelectedBookingObject.pending_amount;
    document.getElementById("txtEditNotes").value    = currentActiveSelectedBookingObject.notes;
    document.getElementById("txtEditCheckin").value  = currentActiveSelectedBookingObject.cid.substring(0, 10);
    document.getElementById("txtEditCheckout").value = currentActiveSelectedBookingObject.cod.substring(0, 10);
    document.getElementById("modalReadView").style.display = "none";
    document.getElementById("modalEditView").style.display = "block";
}
function switchToReadMode() { document.getElementById("modalEditView").style.display = "none"; document.getElementById("modalReadView").style.display = "block"; }

window.addEventListener('DOMContentLoaded', setSystemDefaultFormTimestamps);
</script>
<?php include "includes/footer.php"; ?>