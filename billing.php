<?php
// /home/apartment/artistsfarmjaipur.com/Order/billing.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "config/db.php";

if (file_exists(__DIR__ . "/config/telegram.php")) {
    require_once __DIR__ . "/config/telegram.php";
}
include_once __DIR__ . '/config/local_db_bridge.php';

if (!isset($_SESSION["role"]) || !check_page_access($pdo)) {
    die("Access Denied: You do not have permission to access this area.");
}

function resolveLedgerUserName($pdo, $value) {
    $value = trim((string)$value);
    if ($value === '') {
        return 'Unnamed';
    }

    if (ctype_digit($value)) {
        $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([intval($value)]);
        return $stmt->fetchColumn() ?: $value;
    }

    return $value;
}

function isUnassignedCollector($collectorName) {
    $normalized = strtolower(trim((string)$collectorName));
    return $normalized === '' || $normalized === 'unnamed' || $normalized === 'system ledger';
}

$guest = $pdo->query("SELECT * FROM guests WHERE status = 'Active' LIMIT 1")->fetch();

// --- HANDLE PENDING ACCOMMODATION PAYMENT ---
if ($guest && $_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action_collect_pending'])) {
    $guest_id = intval($_POST['guest_id']);
    $amount = floatval($_POST['amount']);
    $collector_id = intval($_POST['collector_id']);
    $mode = trim($_POST['payment_mode'] ?? 'Cash');

    $collector = $pdo->prepare("SELECT username FROM users WHERE id = ?");
    $collector->execute([$collector_id]);
    $collector_name = $collector->fetchColumn();

    if (!$collector_name || $guest_id !== intval($guest['id']) || $amount <= 0) {
        $_SESSION['staff_error'] = "Please select a valid staff member before recording the pending accommodation payment.";
        header("Location: billing.php");
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("UPDATE guests SET pending_received_by = ?, payment_status = 'Settled' WHERE id = ? AND status = 'Active'");
        $stmt->execute([$collector_name, $guest_id]);

        $log = $pdo->prepare("INSERT INTO audit_logs (user_id, action, timestamp) VALUES (?, ?, NOW())");
        $log->execute([$_SESSION['user_id'] ?? null, "Collected pending accommodation Rs. " . number_format($amount, 2) . " (Mode: $mode). Received by: $collector_name"]);

        $pdo->commit();
        $_SESSION['staff_success'] = "Pending accommodation payment recorded successfully.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['staff_error'] = "Transaction failed: " . $e->getMessage();
    }
    header("Location: billing.php");
    exit;
}
if ($guest && $_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["adjust_action"])) {
    $id = intval($_POST["order_item_id"]); 
    $qty = intval($_POST["adjust_qty"]);
    $type = $_POST["adjust_type"];

    if ($type === 'Cancel') { 
        $pdo->prepare("UPDATE order_items SET quantity = quantity - ? WHERE id = ?")->execute([$qty, $id]); 
    } else if ($type === 'Return') { 
        $pdo->prepare("UPDATE order_items SET returned_qty = returned_qty + ? WHERE id = ?")->execute([$qty, $id]); 
    }

    // --- AUDIT TRAIL LOGGING ---
    $audit_stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, timestamp) VALUES (?, ?, NOW())");
    $audit_stmt->execute([$_SESSION['user_id'], "User [" . $_SESSION['username'] . "] triggered " . $type . " operation on Order Item ID #" . $id . " with quantity context: " . $qty]);

    header("Location: billing.php"); 
    exit;
}

if ($guest && $_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action_add_adjustment"])) {
    $type   = $_POST["adj_type"]; 
    $reason = !empty(trim($_POST["adj_reason"])) ? trim($_POST["adj_reason"]) : ($type === 'discount' ? 'Discount Given' : 'Extra Charge');
    $amount = floatval($_POST["adj_amount"]);

    if ($amount > 0) {
        $current_adjustments = [];
        if (!empty($guest['food_remark'])) {
            $current_adjustments = json_decode($guest['food_remark'], true) ?: [];
        }
        $current_adjustments[] = [
            'id' => time(),
            'reason' => $reason,
            'amount' => $amount,
            'type' => $type
        ];
        $updated_json = json_encode($current_adjustments);
        $pdo->prepare("UPDATE guests SET food_remark = ? WHERE id = ?")->execute([$updated_json, $guest['id']]);

        // --- AUDIT TRAIL LOGGING ---
        $audit_stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, timestamp) VALUES (?, ?, NOW())");
        $audit_stmt->execute([$_SESSION['user_id'], "User [" . $_SESSION['username'] . "] appended custom incidental adjustment [" . $reason . "] totaling ₹" . $amount . " on Guest ID #" . $guest['id']]);
    }
    header("Location: billing.php");
    exit;
}

if ($guest && $_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action_remove_adjustment"])) {
    $adj_id = intval($_POST["adj_id"]);
    if (!empty($guest['food_remark'])) {
        $current_adjustments = json_decode($guest['food_remark'], true) ?: [];
        $filtered = array_filter($current_adjustments, function($item) use ($adj_id) {
            return $item['id'] !== $adj_id;
        });
        $pdo->prepare("UPDATE guests SET food_remark = ? WHERE id = ?")->execute([json_encode(array_values($filtered)), $guest['id']]);

        // --- AUDIT TRAIL LOGGING ---
        $audit_stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, timestamp) VALUES (?, ?, NOW())");
        $audit_stmt->execute([$_SESSION['user_id'], "User [" . $_SESSION['username'] . "] revoked an incidental adjustment from active Profile ID #" . $guest['id']]);
    }
    header("Location: billing.php");
    exit;
}

if ($guest && $_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action_finalize_checkout"])) {
    $guest_id = $guest['id'];
    $food_bill_total = floatval($_POST["post_food_bill_total"]);
    $stored_pending = floatval($guest["pending_amount"] ?? 0);
    $calculated_pending = max(0, floatval($guest["base_room_rent"] ?? 0) - floatval($guest["advance_paid"] ?? 0));
    $accommodation_pending = $stored_pending > 0 ? $stored_pending : $calculated_pending;
    
    $advance_collected_by = resolveLedgerUserName($pdo, $guest['advance_received_by'] ?? 'Unnamed');
    $pending_collected_by = resolveLedgerUserName($pdo, $guest['pending_received_by'] ?? 'Unnamed');
    $food_collected_by          = trim($_POST["food_received_by_staff"]);

    if ($accommodation_pending > 0 && isUnassignedCollector($pending_collected_by)) {
        $_SESSION['staff_error'] = "Please record the pending accommodation payment collector before generating the final bill.";
        header("Location: billing.php");
        exit;
    }
    
    $itemsQuery = $pdo->prepare("
        SELECT oi.*, mi.name, mi.price 
        FROM order_items oi 
        JOIN menu_items mi ON oi.menu_item_id = mi.id 
        JOIN orders o ON oi.order_id = o.id 
        WHERE o.guest_id = ? AND oi.item_status = 'Served'
    ");
    $itemsQuery->execute([$guest_id]);
    $items_list = $itemsQuery->fetchAll(PDO::FETCH_ASSOC);

    $adjustments = [];
    if (!empty($guest['food_remark'])) {
        $adjustments = json_decode($guest['food_remark'], true) ?: [];
    }

    $pdo->prepare("
        UPDATE guests 
        SET status = 'CheckedOut', 
            checkout_date = CURRENT_DATE(),
            pending_amount = ?,
            pending_received_by = ?,
            total_food = ?,
            food_received_by = ?
        WHERE id = ?
    ")->execute([$accommodation_pending, $pending_collected_by, $food_bill_total, $food_collected_by, $guest_id]);

    $farmBookingsTable = $pdo->query("SHOW TABLES LIKE 'farm_bookings'")->fetchColumn();
    if ($farmBookingsTable) {
        $pdo->prepare("
            INSERT INTO farm_bookings (booking_source, contact_no, no_of_guests, check_in_date, check_out_date, per_night_charges, advance_paid, advance_received_by, pending_amount, pending_received_by, total_food_bill, food_received_by, remarks)
            VALUES (?, ?, ?, ?, CURRENT_DATE(), ?, ?, ?, ?, ?, ?, ?, 'Checked out from Dual Collector POS')
        ")->execute([
            $guest['booking_source'], $guest['phone_number'], $guest['no_of_guests'], $guest['checkin_date'],
            $guest['per_night_charges'], $guest['advance_paid'], $advance_collected_by,
            $accommodation_pending, $pending_collected_by, $food_bill_total, $food_collected_by
        ]);
    }

    // --- AUDIT TRAIL LOGGING ---
    $audit_stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, timestamp) VALUES (?, ?, NOW())");
    $audit_stmt->execute([$_SESSION['user_id'], "User [" . $_SESSION['username'] . "] executed final room checkout settlement for Guest [" . $guest['guest_name'] . "] (Total Room Rent Pending Collected: ₹" . $accommodation_pending . " | Total Kitchen Incidentals Settle Collected: ₹" . $food_bill_total . ")"]);

    // Telegram Summary Dispatch Logic Hook
    $tg_msg  = "🔔 <b>FARM CHECKOUT SETTLEMENT REPORT</b>\n";
    $tg_msg .= "━━━━━━━━━━━━━━━━━━\n";
    $tg_msg .= "👤 <b>Guest:</b> " . htmlspecialchars($guest['guest_name'] ?: 'Walk-In') . "\n";
    $tg_msg .= "📱 <b>Contact:</b> " . htmlspecialchars($guest['phone_number'] ?: 'N/A') . "\n";
    $tg_msg .= "🗓️ <b>Check-In:</b> " . $guest['checkin_date'] . "\n\n";

    $tg_msg .= "🏠 <b>ACCOMMODATION BILLING</b>\n";
    $tg_msg .= "• Total Tariff: ₹" . number_format($guest['base_room_rent'], 2) . "\n";
    $tg_msg .= "• Advance Paid: ₹" . number_format($guest['advance_paid'], 2) . " (" . htmlspecialchars($advance_collected_by) . ")\n";
    $tg_msg .= "• Pending Due Taken: <b>₹" . number_format($accommodation_pending, 2) . "</b>\n";
    $tg_msg .= "💼 <i>Pending Collected By: " . htmlspecialchars($pending_collected_by) . "</i>\n\n";

    $tg_msg .= "🍽️ <b>RESTAURANT & KITCHEN BILL</b>\n";
    if (!empty($items_list)) {
        foreach ($items_list as $itm) {
            $net_q = $itm['quantity'] - $itm['returned_qty'];
            if ($net_q > 0) {
                $tg_msg .= "• " . htmlspecialchars($itm['name']) . " (x" . $net_q . "): ₹" . number_format($net_q * $itm['price'], 2) . "\n";
            }
        }
    } else {
        $tg_msg .= "• No food orders recorded.\n";
    }
    
    if (!empty($adjustments)) {
        foreach ($adjustments as $adj) {
            $prefix = ($adj['type'] === 'charge') ? "+" : "-";
            $tg_msg .= "• [Adj] " . htmlspecialchars($adj['reason']) . ": " . $prefix . "₹" . number_format($adj['amount'], 2) . "\n";
        }
    }
    $tg_msg .= "• Total Kitchen Settlement: <b>₹" . number_format($food_bill_total, 2) . "</b>\n";
    $tg_msg .= "👤 <i>Collected By: " . htmlspecialchars($food_collected_by) . "</i>\n";
    $tg_msg .= "━━━━━━━━━━━━━━━━━━\n";
    $tg_msg .= "💰 <b>TOTAL REVENUE PAYABLE: ₹" . number_format(($accommodation_pending + $food_bill_total), 2) . "</b>\n";

    if (function_exists('sendAdminTelegramMessage')) {
        sendAdminTelegramMessage($tg_msg); 
    }

    header("Location: index.php");
    exit;
}

include "includes/header.php";
?>

<style>
.billing-grid-split { display: grid; grid-template-columns: 1fr 400px; gap: 20px; text-align: left; align-items: start; }
.billing-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 20px; }
.billing-section-title { font-size: 14px; font-weight: 700; text-transform: uppercase; color: #1e293b; border-bottom: 1px dashed #cbd5e0; padding-bottom: 8px; margin-top: 0; margin-bottom: 15px; letter-spacing: 0.5px; }
.alert-highlight-pending { background: #fff5f5; border: 1px solid #feb2b2; padding: 12px; border-radius: 8px; color: #c53030; font-weight: bold; font-size: 15px; display: flex; justify-content: space-between; align-items: center; margin-top: 10px; }
.data-display-row { display: flex; justify-content: space-between; align-items: center; font-size: 13px; padding: 8px 0; border-bottom: 1px solid #f1f5f9; }
.staff-selector { width: 100%; padding: 8px; border-radius: 6px; border: 1px solid #cbd5e0; background: #fff; font-size: 13px; font-weight: 600; color: #334155; margin-top: 4px; }
</style>

<div class="app-body" style="max-width:100%; width:100%;">
    <?php if (!empty($_SESSION['staff_success'])): ?>
        <div style="padding:12px 16px; background:#ecfdf5; border:1px solid #a7f3d0; color:#065f46; border-radius:8px; margin-bottom:15px; font-size:14px; font-weight:700;">
            <?= htmlspecialchars($_SESSION['staff_success']) ?>
        </div>
        <?php unset($_SESSION['staff_success']); ?>
    <?php endif; ?>
    <?php if (!empty($_SESSION['staff_error'])): ?>
        <div style="padding:12px 16px; background:#fef2f2; border:1px solid #fecaca; color:#991b1b; border-radius:8px; margin-bottom:15px; font-size:14px; font-weight:700;">
            <?= htmlspecialchars($_SESSION['staff_error']) ?>
        </div>
        <?php unset($_SESSION['staff_error']); ?>
    <?php endif; ?>
    <?php if (!$guest): ?>
        <div style="padding:40px; background:#fff; border:1px solid #e2e8f0; text-align:center; border-radius:12px; font-style:italic; color:#94a3b8;">
            📭 There are no active operational guest billing accounts found running on the farm property today.
        </div>
    <?php else: 
        $ordersQuery = $pdo->prepare("
            SELECT oi.*, mi.name, mi.price 
            FROM order_items oi 
            JOIN menu_items mi ON oi.menu_item_id = mi.id 
            JOIN orders o ON oi.order_id = o.id 
            WHERE o.guest_id = ? AND oi.item_status = 'Served'
        ");
        $ordersQuery->execute([$guest['id']]);
        $served_items = $ordersQuery->fetchAll(PDO::FETCH_ASSOC);

        $food_subtotal = 0;
        foreach ($served_items as $item) {
            $net_qty = $item['quantity'] - $item['returned_qty'];
            if ($net_qty > 0) {
                $food_subtotal += ($net_qty * $item['price']);
            }
        }

        $adjustments = [];
        if (!empty($guest['food_remark'])) {
            $adjustments = json_decode($guest['food_remark'], true) ?: [];
        }

        $adjustments_sum = 0;
        foreach ($adjustments as $adj) {
            if ($adj['type'] === 'charge') {
                $adjustments_sum += $adj['amount'];
            } else {
                $adjustments_sum -= $adj['amount'];
            }
        }

        $total_incidentals_bill = max(0, $food_subtotal + $adjustments_sum);
        $base_rent = floatval($guest['base_room_rent'] ?? 0);
        $advance_paid = floatval($guest['advance_paid'] ?? 0);
        $stored_pending = floatval($guest['pending_amount'] ?? 0);
        $calculated_pending = max(0, $base_rent - $advance_paid);
        $accommodation_pending = $stored_pending > 0 ? $stored_pending : $calculated_pending;
        $advance_collector = resolveLedgerUserName($pdo, $guest['advance_received_by'] ?? 'Unnamed');
        $pending_collector = resolveLedgerUserName($pdo, $guest['pending_received_by'] ?? 'Unnamed');
        $pending_payment_collected = $accommodation_pending <= 0 || !isUnassignedCollector($pending_collector);
        $staff_list = $pdo->query("SELECT id, username FROM users ORDER BY username ASC")->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <div class="billing-grid-split">
        <div class="workspace-panel-stack">
            <div class="billing-card">
                <div class="billing-section-title">🏡 Accommodation Invoice Breakdown</div>
                <div class="data-display-row">
                    <span>Base Lodging Charges (Total Stay Contract):</span>
                    <strong style="color: #334155;">₹<?= number_format($base_rent, 2) ?></strong>
                </div>
                <div class="data-display-row">
                    <span>Advance Payment Received (Accommodation Credit):</span>
                    <strong style="color: #38a169;">+ ₹<?= number_format($advance_paid, 2) ?> by <?= htmlspecialchars($advance_collector) ?></strong>
                </div>
                <div class="data-display-row">
                    <span>Pending Accommodation Balance:</span>
                    <strong style="color: #c53030;">₹<?= number_format($accommodation_pending, 2) ?></strong>
                </div>

                <?php if ($accommodation_pending > 0 && !$pending_payment_collected): ?>
                    <div style="background: #fff7ed; border: 1px solid #f97316; padding: 20px; border-radius: 12px; margin-top: 15px;">
                        <h3 style="margin-top:0; color:#9a3412; font-size:15px;">Pending accommodation payment must be recorded before final bill</h3>
                        <form method="POST" action="billing.php" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end;">
                            <input type="hidden" name="action_collect_pending" value="1">
                            <input type="hidden" name="guest_id" value="<?= $guest['id'] ?>">
                            <input type="hidden" name="amount" value="<?= $accommodation_pending ?>">
                            <div style="flex: 1; min-width: 150px;">
                                <label style="font-size: 11px; font-weight:700;">Collected By</label>
                                <select name="collector_id" required style="width:100%; padding:8px; border-radius:6px; border:1px solid #cbd5e0;">
                                    <option value="">-- Select Staff --</option>
                                    <?php foreach ($staff_list as $s): ?>
                                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['username']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div style="flex: 1; min-width: 150px;">
                                <label style="font-size: 11px; font-weight:700;">Payment Mode</label>
                                <select name="payment_mode" required style="width:100%; padding:8px; border-radius:6px; border:1px solid #cbd5e0;">
                                    <option value="Cash">Cash</option>
                                    <option value="UPI">UPI</option>
                                </select>
                            </div>
                            <button type="submit" style="padding:8px 20px; background:#f97316; color:white; border:none; border-radius:6px; font-weight:bold; cursor:pointer;">Mark as Received</button>
                        </form>
                    </div>
                <?php elseif ($accommodation_pending > 0): ?>
                    <div style="background:#ecfdf5; border:1px solid #a7f3d0; color:#065f46; padding:12px; border-radius:8px; margin-top:15px; font-size:13px; font-weight:700;">
                        Pending accommodation collected by <?= htmlspecialchars($pending_collector) ?>.
                    </div>
                <?php endif; ?>
            </div>

            <div class="billing-card">
                <div class="billing-section-title">🍽️ Food Orders & Combined Incidentals Log</div>
                <table style="width:100%; border-collapse:collapse; font-size:13px; margin-bottom:15px;">
                    <thead>
                        <tr style="background:#f8fafc; border-bottom:1px solid #cbd5e0; color:#475569; text-align:left;">
                            <th style="padding:8px;">Description Line Item</th>
                            <th style="padding:8px; text-align:center;">Qty</th>
                            <th style="padding:8px; text-align:right;">Rate</th>
                            <th style="padding:8px; text-align:right;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($served_items)): foreach ($served_items as $item): 
                            $net_qty = $item['quantity'] - $item['returned_qty'];
                            if ($net_qty <= 0) continue;
                            $line_total = $net_qty * $item['price'];
                        ?>
                            <tr style="border-bottom:1px solid #f1f5f9;">
                                <td style="padding:8px; font-weight:600;"><?= htmlspecialchars($item['name']) ?></td>
                                <td style="padding:8px; text-align:center;"><?= $net_qty ?></td>
                                <td style="padding:8px; text-align:right;">₹<?= number_format($item['price'], 2) ?></td>
                                <td style="padding:8px; text-align:right; font-weight:700;">₹<?= number_format($line_total, 2) ?></td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="4" style="text-align:center; color:#94a3b8; padding:15px; font-style:italic;">No restaurant orders recorded.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <?php if (!empty($adjustments)): ?>
                    <div style="margin-top:15px; background:#f8fafc; border-radius:6px; padding:10px; border:1px solid #cbd5e0;">
                        <span style="font-size:11px; font-weight:700; text-transform:uppercase; color:#64748b; display:block; margin-bottom:5px;">Manual Dynamic Adjustments</span>
                        <?php foreach ($adjustments as $adj): ?>
                            <div style="display:flex; justify-content:space-between; align-items:center; font-size:12px; padding:4px 0;">
                                <div style="flex:1;">↳ <?= htmlspecialchars($adj['reason']) ?> (<?= $adj['type'] === 'charge' ? 'Extra' : 'Discount' ?>)</div>
                                <div style="font-weight:700; margin-right:15px; color:<?= $adj['type'] === 'charge' ? '#e53e3e' : '#38a169' ?>;">
                                    <?= $adj['type'] === 'charge' ? '+' : '-' ?>₹<?= number_format($adj['amount'], 2) ?>
                                </div>
                                <form method="POST" style="margin:0;">
                                    <input type="hidden" name="action_remove_adjustment" value="1">
                                    <input type="hidden" name="adj_id" value="<?= $adj['id'] ?>">
                                    <button type="submit" style="background:none; border:none; color:#ef4444; cursor:pointer; font-size:11px;">✕</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div style="display:flex; justify-content:flex-end; margin-top:15px; font-size:14px;">
                    <span>Food & Extras Bill Subtotal: &nbsp;<strong style="color:#0284c7;">₹<?= number_format($total_incidentals_bill, 2) ?></strong></span>
                </div>
            </div>
        </div>

        <div class="sidebar-panel-stack">
            <div class="billing-card">
                <div class="billing-section-title">➕ Add Custom Adjustments</div>
                <form method="POST" style="margin:0;" id="adjustmentEntryForm">
                    <input type="hidden" name="action_add_adjustment" value="1">
                    <div style="margin-bottom:10px;">
                        <label style="font-size:11px; font-weight:700; display:block; margin-bottom:4px;">Adjustment Strategy Type</label>
                        <select name="adj_type" id="adjTypeSelector" onchange="toggleLabelRequirement()" style="width:100%; padding:8px; border:1px solid #cbd5e0; border-radius:6px;">
                            <option value="charge">Extra Charge (+)</option>
                            <option value="discount">Discount / Rebate (-)</option>
                        </select>
                    </div>
                    <div style="margin-bottom:10px;">
                        <label style="font-size:11px; font-weight:700; display:block; margin-bottom:4px;">Adjustment Label Detail</label>
                        <input type="text" name="adj_reason" id="adjReasonInput" placeholder="Optional for discounts..." style="width:100%; padding:8px; border:1px solid #cbd5e0; border-radius:6px; box-sizing:border-box;">
                    </div>
                    <div style="margin-bottom:15px;">
                        <label style="font-size:11px; font-weight:700; display:block; margin-bottom:4px;">Amount (₹)</label>
                        <input type="number" name="adj_amount" step="0.01" required style="width:100%; padding:8px; border:1px solid #cbd5e0; border-radius:6px; box-sizing:border-box;">
                    </div>
                    <button type="submit" class="btn btn-start" style="width:100%; padding:10px; border-radius:6px; font-weight:700;">Apply Adjustment</button>
                </form>
            </div>

            <div class="billing-card" style="border:2px solid #06b6d4; background:#fafdfd;">
                <div class="billing-section-title" style="color:#0891b2; border-color:#0891b2;">🏁 Final Checkout Settlement</div>
                
                <form method="POST" style="margin:0;">
                    <input type="hidden" name="action_finalize_checkout" value="1">
                    <input type="hidden" name="post_food_bill_total" value="<?= $total_incidentals_bill ?>">
                    <input type="hidden" name="post_accommodation_pending" value="<?= $accommodation_pending ?>">
                    
                    <div style="padding:10px; background:#fff; border:1px solid #e2e8f0; border-radius:8px; margin-bottom:15px; font-size:12px; line-height:1.5;">
                        <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                            <span>Accommodation Outstanding:</span>
                            <span style="font-weight:700; color:#c53030;">₹<?= number_format($accommodation_pending, 2) ?></span>
                        </div>
                        <div style="display:flex; justify-content:space-between; border-bottom:1px dashed #cbd5e0; padding-bottom:6px; margin-bottom:6px;">
                            <span>Food & Incidentals Total:</span>
                            <span style="font-weight:700; color:#0284c7;">₹<?= number_format($total_incidentals_bill, 2) ?></span>
                        </div>
                        <div style="display:flex; justify-content:space-between; font-size:14px; font-weight:bold;">
                            <span>Total Due at Checkout:</span>
                            <span style="color:#059669;">₹<?= number_format(($accommodation_pending + $total_incidentals_bill), 2) ?></span>
                        </div>
                    </div>

                    <div style="margin-bottom:15px; background:#f1f5f9; padding:8px 12px; border-radius:6px; border:1px dashed #cbd5e0; font-size:12px; color:#475569;">
                        <div style="display:flex; justify-content:space-between; gap:8px; margin-bottom:4px;">
                            <strong>Advance Collector:</strong>
                            <span style="font-weight:bold; color:#1e293b"><?= htmlspecialchars($advance_collector) ?></span>
                        </div>
                        <div style="display:flex; justify-content:space-between; gap:8px;">
                            <strong>Pending Collector:</strong>
                            <span style="font-weight:bold; color:<?= $pending_payment_collected ? '#065f46' : '#c53030' ?>"><?= $pending_payment_collected ? htmlspecialchars($pending_collector) : 'Not recorded' ?></span>
                        </div>
                    </div>

                    <div style="margin-bottom:20px;">
                        <label style="font-size:11px; font-weight:700; display:block; color:#475569;">👤 Food & Incidentals Collected By:</label>
                        <select name="food_received_by_staff" required class="staff-selector">
                            <option value="">-- Choose Collector --</option>
                            <?php foreach ($staff_list as $s): ?>
                                <option value="<?= htmlspecialchars($s['username']) ?>"><?= htmlspecialchars($s['username']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="button" class="btn btn-log" style="width:100%; padding:10px; margin-bottom:10px; font-weight:700; background:#f1f5f9; color:#475569; border:1px solid #cbd5e0; border-radius:6px;" onclick="window.openCleanBillPopup()" <?= $pending_payment_collected ? '' : 'disabled' ?>>
                        <?= $pending_payment_collected ? 'View Print-Friendly Receipt' : 'Record Pending Accommodation First' ?>
                    </button>

                    <button type="submit" class="btn btn-bill" style="width:100%; padding:12px; border-radius:8px; font-size:14px; font-weight:800; background:<?= $pending_payment_collected ? '#06b6d4' : '#94a3b8' ?>; border-color:<?= $pending_payment_collected ? '#06b6d4' : '#94a3b8' ?>;" <?= $pending_payment_collected ? '' : 'disabled' ?>>
                        <?= $pending_payment_collected ? 'Complete Checkout & Archive Bill' : 'Record Pending Accommodation First' ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<div id="cleanPrintFriendlyModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.4); z-index:999999; justify-content:center; align-items:center; backdrop-filter:blur(2px);">
    <div style="background:#ffffff; max-width:420px; width:90%; border-radius:8px; padding:25px; box-shadow:0 10px 25px rgba(0,0,0,0.15); text-align:left; color:#000000; font-family:monospace;">
        <div style="text-align:center; margin-bottom:15px; border-bottom:2px dashed #000;">
            <h3 style="margin:0 0 5px 0; font-size:16px; text-transform:uppercase; letter-spacing:1px;">ARTISTS FARM JAIPUR</h3>
            <span style="font-size:11px; color:#555;">Official Bill Invoice Copy</span>
            <div style="margin:10px 0; font-size:12px; text-align:left;">
                <div><b>Guest:</b> <span id="pGuestName"><?= htmlspecialchars($guest['guest_name'] ?? '') ?></span></div>
                <div><b>Phone:</b> <span id="pGuestPhone"><?= htmlspecialchars($guest['phone_number'] ?? '') ?></span></div>
                <div><b>Date:</b> <span><?= date('d M Y, h:i A') ?></span></div>
            </div>
        </div>

        <div style="font-size:12px; font-weight:bold; text-transform:uppercase; margin-bottom:6px; border-bottom:1px solid #000;">Stay Logistics</div>
        <div style="display:flex; justify-content:space-between; font-size:12px; margin-bottom:4px;">
            <span>Room Tariff (Contract Base):</span>
            <span>₹<?= number_format($base_rent, 2) ?></span>
        </div>
        <div style="display:flex; justify-content:space-between; font-size:12px; margin-bottom:4px; color:#2f855a;">
            <span>[-] Advance Received:</span>
            <span>₹<?= number_format($advance_paid, 2) ?></span>
        </div>
        <div style="display:flex; justify-content:space-between; font-size:11px; margin-bottom:4px; color:#444;">
            <span>Advance Collected By:</span>
            <span><?= htmlspecialchars($advance_collector) ?></span>
        </div>
        <div style="display:flex; justify-content:space-between; font-size:12px; font-weight:bold; margin-bottom:15px; border-bottom:1px dashed #000; padding-bottom:6px;">
            <span>Stay Balance Due:</span>
            <span>₹<?= number_format($accommodation_pending, 2) ?></span>
        </div>
        <?php if ($accommodation_pending > 0): ?>
            <div style="display:flex; justify-content:space-between; font-size:11px; margin-top:-10px; margin-bottom:15px; color:#444;">
                <span>Pending Collected By:</span>
                <span><?= $pending_payment_collected ? htmlspecialchars($pending_collector) : 'Not recorded' ?></span>
            </div>
        <?php endif; ?>

        <div style="font-size:12px; font-weight:bold; text-transform:uppercase; margin-bottom:6px; border-bottom:1px solid #000;">KOT Food & Incidentals</div>
        <div id="popupReceiptItems" style="border-bottom:2px dashed #000; padding-bottom:8px; margin-bottom:12px;"></div>

        <div style="display:flex; justify-content:space-between; font-size:14px; font-weight:bold; text-transform:uppercase;">
            <span>Total Outstanding Payable:</span>
            <span style="font-size:15px; border-bottom:4px double #000;">₹<?= number_format(($accommodation_pending + $total_incidentals_bill), 2) ?></span>
        </div>
        <div style="margin-top: 20px; display: flex; gap: 8px; justify-content: flex-end;">
            <button class="btn" style="background: #4a5568; max-width: 80px; color: white; padding:6px 12px; font-size:12px; cursor:pointer;" onclick="window.print()">Print</button>
            <button class="btn" style="background: #e2e8f0; max-width: 80px; color: #111827; padding:6px 14px; border:none; cursor:pointer; font-size:12px;" onclick="closeEditInvoiceModal()">Close</button>
        </div>
    </div>
</div>

<script>
function toggleLabelRequirement() {
    const typeSelector = document.getElementById("adjTypeSelector");
    const reasonInput = document.getElementById("adjReasonInput");
    if (typeSelector && reasonInput) {
        if (typeSelector.value === "discount") {
            reasonInput.removeAttribute("required");
            reasonInput.placeholder = "Optional description label for discounts...";
        } else {
            reasonInput.setAttribute("required", "required");
            reasonInput.placeholder = "Required description label for extra charges...";
        }
    }
}

window.openCleanBillPopup = function() {
    const itemsContainer = document.getElementById("popupReceiptItems"); 
    itemsContainer.innerHTML = "";
    
    const cleanItems = <?php echo json_encode(array_values($served_items ?? [])); ?>;
    cleanItems.forEach(item => { 
        let net = item.quantity - item.returned_qty;
        if(net > 0) {
            itemsContainer.innerHTML += `<div style="display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 13px;"><span>${item.name} x${net}</span><span>` + "₹" + (net * item.price).toFixed(2) + `</span></div>`; 
        }
    });
    
 const adjustments = <?php echo json_encode($adjustments ?? []); ?>;
    adjustments.forEach(item => {
        // Logic: Charges are "+", Payments/Adjustments are "-"
        const sign = (item.type === "charge") ? "+" : "-";
        
        // Visual Styling: Green for payments/credits, default for charges
        const color = (item.type === "payment") ? "#059669" : "#444";
        
        itemsContainer.innerHTML += `
            <div style="display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 12px; color: ${color}; font-style: italic;">
                <span>↳ ${item.reason}</span>
                <span>${sign}₹${parseFloat(item.amount).toFixed(2)}</span>
            </div>`;
    });
    
    document.getElementById("cleanPrintFriendlyModal").style.display = "flex";
};

function closeEditInvoiceModal() {
    document.getElementById("cleanPrintFriendlyModal").style.display = "none";
}

document.addEventListener("DOMContentLoaded", toggleLabelRequirement);
</script>

<?php include "includes/footer.php"; ?>
