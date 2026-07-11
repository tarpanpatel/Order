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
    $accommodation_due_now = $accommodation_pending > 0 ? 0 : $accommodation_pending;
    $checkout_total_due = $accommodation_due_now + $food_bill_total;
    
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

    $audit_stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, timestamp) VALUES (?, ?, NOW())");
    $audit_stmt->execute([$_SESSION['user_id'], "User [" . $_SESSION['username'] . "] executed final room checkout settlement for Guest [" . $guest['guest_name'] . "] (Total Room Rent Pending Collected: ₹" . $accommodation_pending . " | Total Kitchen Incidentals Settle Collected: ₹" . $food_bill_total . ")"]);

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
    $tg_msg .= "<b>TOTAL OUTSTANDING PAYABLE: ₹" . number_format($checkout_total_due, 2) . "</b>\n";

    if (function_exists('sendAdminTelegramMessage')) {
        sendAdminTelegramMessage($tg_msg); 
    }

    header("Location: index.php");
    exit;
}

include "includes/header.php";
?>

<div class="app-body">
    <?php if (!empty($_SESSION['staff_success'])): ?>
        <div class="billing-banner-success">
            <?= htmlspecialchars($_SESSION['staff_success']) ?>
        </div>
        <?php unset($_SESSION['staff_success']); ?>
    <?php endif; ?>
    <?php if (!empty($_SESSION['staff_error'])): ?>
        <div class="billing-banner-error">
            <?= htmlspecialchars($_SESSION['staff_error']) ?>
        </div>
        <?php unset($_SESSION['staff_error']); ?>
    <?php endif; ?>
    <?php if (!$guest): ?>
        <div class="billing-empty-state">
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
        $accommodation_due_now = $pending_payment_collected ? 0 : $accommodation_pending;
        $checkout_total_due = $accommodation_due_now + $total_incidentals_bill;
        $staff_list = $pdo->query("SELECT id, username FROM users ORDER BY username ASC")->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <div class="billing-grid-split">
        <div class="workspace-panel-stack">
            <div class="billing-card">
                <div class="billing-section-title">🏡 Accommodation Invoice Breakdown</div>
                <div class="data-display-row">
                    <span>Base Lodging Charges (Total Stay Contract):</span>
                    <strong class="text-slate-dark">₹<?= number_format($base_rent, 2) ?></strong>
                </div>
                <div class="data-display-row">
                    <span>Advance Payment Received (Accommodation Credit):</span>
                    <strong class="text-green-success">+ ₹<?= number_format($advance_paid, 2) ?> by <?= htmlspecialchars($advance_collector) ?></strong>
                </div>
                <div class="data-display-row">
                    <span>Pending Accommodation Balance:</span>
                    <strong class="text-red-danger">₹<?= number_format($accommodation_pending, 2) ?></strong>
                </div>

                <?php if ($accommodation_pending > 0 && !$pending_payment_collected): ?>
                    <div class="billing-alert-pending-box">
                        <h3 class="billing-alert-pending-heading">Pending accommodation payment must be recorded before final bill</h3>
                        <form method="POST" action="billing.php" class="billing-flex-form-row">
                            <input type="hidden" name="action_collect_pending" value="1">
                            <input type="hidden" name="guest_id" value="<?= $guest['id'] ?>">
                            <input type="hidden" name="amount" value="<?= $accommodation_pending ?>">
                            <div class="billing-flex-form-field">
                                <label class="billing-field-label">Collected By</label>
                                <select name="collector_id" required class="billing-field-select">
                                    <option value="">-- Select Staff --</option>
                                    <?php foreach ($staff_list as $s): ?>
                                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['username']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="billing-flex-form-field">
                                <label class="billing-field-label">Payment Mode</label>
                                <select name="payment_mode" required class="billing-field-select">
                                    <option value="Cash">Cash</option>
                                    <option value="UPI">UPI</option>
                                </select>
                            </div>
                            <button type="submit" class="billing-btn-mark-received">Mark as Received</button>
                        </form>
                    </div>
                <?php elseif ($accommodation_pending > 0): ?>
                    <div class="billing-alert-collected-msg">
                        Pending accommodation collected by <?= htmlspecialchars($pending_collector) ?>.
                    </div>
                <?php endif; ?>
            </div>

            <div class="billing-card">
                <div class="billing-section-title">🍽️ Food Orders & Combined Incidentals Log</div>
                <table class="billing-log-table">
                    <thead>
                        <tr class="billing-table-thead-row">
                            <th class="p-8">Description Line Item</th>
                            <th class="p-8-center">Qty</th>
                            <th class="p-8-right">Rate</th>
                            <th class="p-8-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($served_items)): foreach ($served_items as $item): 
                            $net_qty = $item['quantity'] - $item['returned_qty'];
                            if ($net_qty <= 0) continue;
                            $line_total = $net_qty * $item['price'];
                        ?>
                            <tr class="billing-table-tbody-row">
                                <td class="p-8-weight-600"><?= htmlspecialchars($item['name']) ?></td>
                                <td class="p-8-center"><?= $net_qty ?></td>
                                <td class="p-8-right">₹<?= number_format($item['price'], 2) ?></td>
                                <td class="p-8-right-weight-700">₹<?= number_format($line_total, 2) ?></td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="4" class="billing-table-empty-td">No restaurant orders recorded.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <?php if (!empty($adjustments)): ?>
                    <div class="billing-adjustments-summary-box">
                        <span class="billing-adjustments-summary-heading">Manual Dynamic Adjustments</span>
                        <?php foreach ($adjustments as $adj): ?>
                            <div class="billing-adjustment-summary-item">
                                <div class="flex-1">↳ <?= htmlspecialchars($adj['reason']) ?> (<?= $adj['type'] === 'charge' ? 'Extra' : 'Discount' ?>)</div>
                                <div class="p-8-right-weight-700 <?= $adj['type'] === 'charge' ? 'text-red-danger' : 'text-green-success' ?>">
                                    <?= $adj['type'] === 'charge' ? '+' : '-' ?>₹<?= number_format($adj['amount'], 2) ?>
                                </div>
                                <form method="POST" class="m-0">
                                    <input type="hidden" name="action_remove_adjustment" value="1">
                                    <input type="hidden" name="adj_id" value="<?= $adj['id'] ?>">
                                    <button type="submit" class="billing-btn-remove-adj">✕</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="billing-subtotal-row">
                    <span>Food & Extras Bill Subtotal: &nbsp;<strong class="text-sky-blue">₹<?= number_format($total_incidentals_bill, 2) ?></strong></span>
                </div>
            </div>
        </div>

        <div class="sidebar-panel-stack">
            <div class="billing-card">
                <div class="billing-section-title">➕ Add Custom Adjustments</div>
                <form method="POST" class="m-0" id="adjustmentEntryForm">
                    <input type="hidden" name="action_add_adjustment" value="1">
                    <div class="mb-10">
                        <label class="field-label-sm">Adjustment Strategy Type</label>
                        <select name="adj_type" id="adjTypeSelector" onchange="toggleLabelRequirement()" class="field-select-full">
                            <option value="charge">Extra Charge (+)</option>
                            <option value="discount">Discount / Rebate (-)</option>
                        </select>
                    </div>
                    <div class="mb-10">
                        <label class="field-label-sm">Adjustment Label Detail</label>
                        <input type="text" name="adj_reason" id="adjReasonInput" placeholder="Optional for discounts..." class="field-input-full">
                    </div>
                    <div class="mb-15">
                        <label class="field-label-sm">Amount (₹)</label>
                        <input type="number" name="adj_amount" step="0.01" required class="field-input-num-full">
                    </div>
                    <button type="submit" class="btn btn-start btn-apply-adj">Apply Adjustment</button>
                </form>
            </div>

            <div class="billing-card billing-card-checkout-highlight">
                <div class="billing-section-title billing-section-title-checkout">🏁 Final Checkout Settlement</div>
                
                <form method="POST" class="m-0">
                    <input type="hidden" name="action_finalize_checkout" value="1">
                    <input type="hidden" name="post_food_bill_total" value="<?= $total_incidentals_bill ?>">
                    <input type="hidden" name="post_accommodation_pending" value="<?= $accommodation_pending ?>">
                    
                    <div class="checkout-breakdown-box">
                        <div class="checkout-breakdown-row">
                            <span>Accommodation Pending Collected:</span>
                            <span class="text-green-dark-bold">₹<?= number_format($pending_payment_collected ? $accommodation_pending : 0, 2) ?></span>
                        </div>
                        <div class="checkout-breakdown-row">
                            <span>Accommodation Still Due:</span>
                            <span class="text-green-dark-bold <?= $accommodation_due_now > 0 ? 'text-red-danger' : 'text-green-dark-bold' ?>">₹<?= number_format($accommodation_due_now, 2) ?></span>
                        </div>
                        <div class="checkout-breakdown-row mb-10">
                            <span>Food & Incidentals Total:</span>
                            <span class="text-green-dark-bold text-sky-blue">₹<?= number_format($total_incidentals_bill, 2) ?></span>
                        </div>
                        <div class="checkout-breakdown-row mb-4">
                            <span>Total Due at Checkout:</span>
                            <span class="sidebar-total-label-green">₹<?= number_format($checkout_total_due, 2) ?></span>
                        </div>
                    </div>

                    <div class="mb-15">
                        <div class="checkout-breakdown-row mb-4">
                            <strong>Advance Collector:</strong>
                            <span class="text-green-dark-bold text-slate-dark"><?= htmlspecialchars($advance_collector) ?></span>
                        </div>
                        <div class="checkout-breakdown-row">
                            <strong>Pending Collector:</strong>
                            <span class="text-green-dark-bold <?= $pending_payment_collected ? 'text-green-dark-bold' : 'text-red-danger' ?>"><?= $pending_payment_collected ? htmlspecialchars($pending_collector) : 'Not recorded' ?></span>
                        </div>
                    </div>

                    <div class="mb-20">
                        <label class="field-label-grey-bold">👤 Food & Incidentals Collected By:</label>
                        <select name="food_received_by_staff" required class="staff-selector">
                            <option value="">-- Choose Collector --</option>
                            <?php foreach ($staff_list as $s): ?>
                                <option value="<?= htmlspecialchars($s['username']) ?>"><?= htmlspecialchars($s['username']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="button" class="btn btn-log btn-view-receipt" onclick="window.openCleanBillPopup()" <?= $pending_payment_collected ? '' : 'disabled' ?>>
                        <?= $pending_payment_collected ? 'View Print-Friendly Receipt' : 'Record Pending Accommodation First' ?>
                    </button>

                    <button type="submit" class="btn btn-bill btn-complete-checkout" onclick="return confirm('Archive this statement?')" <?= $pending_payment_collected ? '' : 'disabled' ?>>
                        <?= $pending_payment_collected ? 'Complete Checkout & Archive Bill' : 'Record Pending Accommodation First' ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- PRINT-FRIENDLY POPUP MODAL (MODIFIED OVERHAUL) -->
<div id="cleanPrintFriendlyModal" class="print-modal-overlay" style="display:none; align-items: flex-start; padding-top: 30px;">
    <div class="print-modal-box" style="position: relative; max-height: 85vh; overflow-y: auto; padding-top: 60px;">
        
        <!-- FIX 5: MOVE CONTROLS TO EXTREME TOP -->
        <div class="print-modal-actions-container" style="position: absolute; top: 15px; left: 20px; right: 20px; display: flex; justify-content: space-between; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px; margin-bottom: 0;">
            <button class="btn print-modal-btn-print" onclick="window.print()" style="background: #00b0ff; color: white; border: none; padding: 6px 16px; border-radius: 6px; font-weight: bold; cursor: pointer;">Print Receipt</button>
            <button class="btn print-modal-btn-close" onclick="closeEditInvoiceModal()" style="background: #ef4444; color: white; border: none; padding: 6px 16px; border-radius: 6px; font-weight: bold; cursor: pointer;">Close Window</button>
        </div>

        <div class="print-modal-header" style="margin-top: 10px;">
            <!-- FIX 1: REMOVED "Official Bill Invoice Copy" TEXT -->
            <h3 class="print-modal-company-title" style="margin-bottom: 12px;">ARTISTS FARM JAIPUR</h3>
            
            <!-- FIX 2 & 3: COMBINED PHONE + DATE REMOVED OFFLINE LINE -->
            <div class="print-modal-guest-meta" style="font-size: 13px; line-height: 1.6; border-bottom: 1px dashed #cbd5e0; padding-bottom: 10px; margin-bottom: 15px;">
                <div style="font-weight: bold; font-size: 15px; margin-bottom: 4px;">Guest: <span id="pGuestName"><?= htmlspecialchars($guest['guest_name'] ?? '') ?></span></div>
                <div style="display: flex; justify-content: space-between; color: #4b5563;">
                    <span><b>Phone:</b> <span id="pGuestPhone"><?= htmlspecialchars($guest['phone_number'] ?? '') ?></span></span>
                    <span><b>Date:</b> <span><?= date('d M Y, h:i A') ?></span></span>
                </div>
            </div>
        </div>

        <div class="print-modal-section-title" style="font-weight: bold; border-left: 3px solid #00b0ff; padding-left: 8px; margin-bottom: 10px; font-size: 14px; color: #1f2937;">Stay Logistics</div>
        <div class="print-modal-row">
            <span>Room Tariff (Contract Base):</span>
            <span>₹<?= number_format($base_rent, 2) ?></span>
        </div>
        <div class="print-modal-row-green">
            <span>[-] Advance Received:</span>
            <span>₹<?= number_format($advance_paid, 2) ?></span>
        </div>
        <div class="print-modal-row-muted">
            <span>Advance Collected By:</span>
            <span><?= htmlspecialchars($advance_collector) ?></span>
        </div>
        <div class="print-modal-row-total-dashed">
            <span>Pending Accommodation Collected:</span>
            <span>₹<?= number_format($pending_payment_collected ? $accommodation_pending : 0, 2) ?></span>
        </div>
        <?php if ($accommodation_pending > 0): ?>
            <div class="print-modal-row-collector-line">
                <span>Pending Collected By:</span>
                <span><?= $pending_payment_collected ? htmlspecialchars($pending_collector) : 'Not recorded' ?></span>
            </div>
        <?php endif; ?>
        <div class="print-modal-row-total-dashed" style="margin-bottom: 20px;">
            <span>Stay Balance Still Due:</span>
            <span>₹<?= number_format($accommodation_due_now, 2) ?></span>
        </div>

        <div class="print-modal-section-title" style="font-weight: bold; border-left: 3px solid #00b0ff; padding-left: 8px; margin-bottom: 10px; font-size: 14px; color: #1f2937;">KOT Food & Incidentals</div>
        
        <!-- CONTAIN CONTAINER FOR PAGINATED ITEMS -->
        <div id="popupReceiptItems" class="print-modal-items-container"></div>
        
        <!-- FIX 4: INTERACTIVE LOAD MORE LINK BUTTON -->
        <div id="receiptLoadMoreContainer" style="display: none; text-align: center; margin: 10px 0; padding: 5px; border-bottom: 1px dashed #e2e8f0;">
            <a href="javascript:void(0)" onclick="expandHiddenReceiptRows()" style="color: #00b0ff; font-weight: bold; font-size: 12px; text-decoration: none; outline: none;">➕ View More Long-Bill Line Items...</a>
        </div>

        <div class="print-modal-grand-total-row" style="margin-top: 15px; border-top: 2px double #1f2937; padding-top: 10px;">
            <span>Total Outstanding Payable:</span>
            <span class="print-modal-grand-total-val">₹<?= number_format($checkout_total_due, 2) ?></span>
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

// FIX 4: PAGINATION MECHANICS STATE GLOBALS
let currentVisibleReceiptRows = 0;
const receiptRowsThreshold = 8;
let allGeneratedReceiptNodes = [];

window.openCleanBillPopup = function() {
    const itemsContainer = document.getElementById("popupReceiptItems"); 
    itemsContainer.innerHTML = "";
    
    // Reset global pagination parameters
    currentVisibleReceiptRows = 0;
    document.getElementById("receiptLoadMoreContainer").style.display = "none";

    let itemLinesHtml = "";
    const cleanItems = <?php echo json_encode(array_values($served_items ?? [])); ?>;
    cleanItems.forEach(item => { 
        let net = item.quantity - item.returned_qty;
        if(net > 0) {
            itemLinesHtml += `<div class="receipt-item-line dynamic-receipt-row" style="display:none; justify-content: space-between; font-size: 13px; margin-bottom: 6px;"><span>${item.name} x${net}</span><span>₹${(net * item.price).toFixed(2)}</span></div>`; 
        }
    });
    
    const adjustments = <?php echo json_encode($adjustments ?? []); ?>;
    adjustments.forEach(item => {
        const sign = (item.type === "charge") ? "+" : "-";
        const colorClass = (item.type === "payment") ? "text-green-success" : "";
        
        itemLinesHtml += `
            <div class="receipt-adjustment-line dynamic-receipt-row ${colorClass}" style="display:none; justify-content: space-between; font-size: 13px; margin-bottom: 6px; font-style: italic; color: #4b5563;">
                <span>↳ ${item.reason}</span>
                <span>${sign}₹${parseFloat(item.amount).toFixed(2)}</span>
            </div>`;
    });
    
    itemsContainer.innerHTML = itemLinesHtml;
    allGeneratedReceiptNodes = Array.from(itemsContainer.querySelectorAll('.dynamic-receipt-row'));
    
    // Evaluate if bill size stretches across the threshold boundary rules
    if (allGeneratedReceiptNodes.length > receiptRowsThreshold) {
        for (let i = 0; i < receiptRowsThreshold; i++) {
            allGeneratedReceiptNodes[i].style.display = 'flex';
            currentVisibleReceiptRows++;
        }
        document.getElementById("receiptLoadMoreContainer").style.display = "block";
    } else {
        allGeneratedReceiptNodes.forEach(node => node.style.display = 'flex');
    }
    
    document.getElementById("cleanPrintFriendlyModal").style.display = "flex";
};

function expandHiddenReceiptRows() {
    const limit = currentVisibleReceiptRows + 15; // Load next 15 lines dynamically
    for (let i = currentVisibleReceiptRows; i < limit && i < allGeneratedReceiptNodes.length; i++) {
        allGeneratedReceiptNodes[i].style.display = 'flex';
        currentVisibleReceiptRows++;
    }
    if (currentVisibleReceiptRows >= allGeneratedReceiptNodes.length) {
        document.getElementById("receiptLoadMoreContainer").style.display = "none";
    }
}

function closeEditInvoiceModal() {
    document.getElementById("cleanPrintFriendlyModal").style.display = "none";
}

document.addEventListener("DOMContentLoaded", toggleLabelRequirement);
</script>

<?php include "includes/footer.php"; ?>