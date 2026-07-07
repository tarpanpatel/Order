<?php
// /home/apartment/artistsfarmjaipur.com/Order/billing.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "config/db.php";
include_once __DIR__ . '/config/local_db_bridge.php';

if (!isset($_SESSION["user_id"])) { 
    header("Location: login.php"); 
    exit; 
}

// Identify the single active running resident profile session
$guest = $pdo->query("SELECT * FROM guests WHERE status = 'Active' LIMIT 1")->fetch();

// Handle direct item quantity reductions (Returns / Cancellations)
if ($guest && $_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["adjust_action"])) {
    $id = intval($_POST["order_item_id"]); 
    $qty = intval($_POST["adjust_qty"]);
    // FIXED: Removed unescaped backslash typo syntax errors
    if ($_POST["adjust_type"] === 'Cancel') { 
        $pdo->prepare("UPDATE order_items SET quantity = quantity - ? WHERE id = ?")->execute([$qty, $id]); 
    } else if ($_POST["adjust_type"] === 'Return') { 
        $pdo->prepare("UPDATE order_items SET returned_qty = returned_qty + ? WHERE id = ?")->execute([$qty, $id]); 
    }
    header("Location: billing.php"); 
    exit;
}

// --- HANDLE POST: ASYNC ADDITION MODIFICATIONS ---
if ($guest && $_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action_add_adjustment"])) {
    $reason = trim($_POST["adj_reason"]);
    $amount = floatval($_POST["adj_amount"]);
    $type   = $_POST["adj_type"]; 

    if (!empty($reason) && $amount > 0) {
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
    }
    header("Location: billing.php");
    exit;
}

// --- HANDLE POST: REMOVE ADJUSTMENT ---
if ($guest && $_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action_remove_adjustment"])) {
    $adj_id = intval($_POST["adj_id"]);
    if (!empty($guest['food_remark'])) {
        $current_adjustments = json_decode($guest['food_remark'], true) ?: [];
        $filtered = array_filter($current_adjustments, function($item) use ($adj_id) {
            return $item['id'] !== $adj_id;
        });
        $pdo->prepare("UPDATE guests SET food_remark = ? WHERE id = ?")->execute([json_encode(array_values($filtered)), $guest['id']]);
    }
    header("Location: billing.php");
    exit;
}

// --- HANDLE POST: COMMIT COMPLETE CHECKOUT SETTLEMENT ---
if ($guest && $_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action_finalize_checkout"])) {
    $guest_id = $guest['id'];
    $food_bill_total = floatval($_POST["post_food_bill_total"]);
    $received_by = trim($_POST["received_by_staff"]);
    
    // Save state out to historical ledger archives
    $pdo->prepare("
        UPDATE guests 
        SET status = 'CheckedOut', 
            checkout_date = CURRENT_DATE(),
            total_food = ?,
            food_received_by = ?
        WHERE id = ?
    ")->execute([$food_bill_total, $received_by, $guest_id]);

    // Push record out to general farm operational tracking metrics
    $pdo->prepare("
        INSERT INTO farm_bookings (booking_source, contact_no, no_of_guests, check_in_date, check_out_date, per_night_charges, advance_paid, advance_received_by, pending_amount, pending_received_by, total_food_bill, food_received_by, remarks)
        VALUES (?, ?, ?, ?, CURRENT_DATE(), ?, ?, ?, ?, ?, ?, ?, 'Checked out from POS Terminal')
    ")->execute([
        $guest['booking_source'],
        $guest['phone_number'],
        $guest['no_of_guests'],
        $guest['checkin_date'],
        $guest['per_night_charges'],
        $guest['advance_paid'],
        $guest['advance_received_by'],
        $guest['pending_amount'],
        $guest['pending_received_by'],
        $food_bill_total,
        $received_by
    ]);

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
.data-display-row:last-child { border-bottom: none; }
.staff-selector { width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #cbd5e0; background: #fff; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 15px; }
</style>

<div class="app-body" style="max-width:100%; width:100%;">
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
            $food_subtotal += ($item['quantity'] - $item['returned_qty']) * $item['price'];
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
        $accommodation_pending = max(0, $base_rent - $advance_paid);
        $days_calc = max(1, (strtotime(date('Y-m-d')) - strtotime($guest['checkin_date'])) / 86400);
    ?>

    <div class="billing-grid-split">
        <div class="workspace-panel-stack">
            <!-- ACCOMMODATION PANEL -->
            <div class="billing-card">
                <div class="billing-section-title">🏡 Accommodation & Stay Invoice</div>
                <div class="data-display-row">
                    <span>Base Lodging Charges (Total Stay Contract):</span>
                    <strong style="color: #334155;">₹<?= number_format($base_rent, 2) ?></strong>
                </div>
                <div class="data-display-row">
                    <span>Advance Payment Received (Accommodation Credit):</span>
                    <strong style="color: #38a169;">+ ₹<?= number_format($advance_paid, 2) ?></strong>
                </div>
                <div class="alert-highlight-pending">
                    <span>⚠️ Remaining Accommodation Pending Payment:</span>
                    <span>₹<?= number_format($accommodation_pending, 2) ?></span>
                </div>
            </div>

            <!-- FOOD AND INCIDENTALS PANEL -->
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

        <!-- RIGHT CONTROL PANEL SIDEBAR -->
        <div class="sidebar-panel-stack">
            <!-- ADJUSTMENTS CONTROLLER CARD -->
            <div class="billing-card">
                <div class="billing-section-title">➕ Add Custom Adjustments</div>
                <form method="POST" style="margin:0;">
                    <input type="hidden" name="action_add_adjustment" value="1">
                    <div style="margin-bottom:10px;">
                        <label style="font-size:11px; font-weight:700; display:block; margin-bottom:4px;">Adjustment Label Detail</label>
                        <input type="text" name="adj_reason" placeholder="e.g., Decoration, extra bedding" required style="width:100%; padding:8px; border:1px solid #cbd5e0; border-radius:6px; box-sizing:border-box;">
                    </div>
                    <div style="margin-bottom:10px;">
                        <label style="font-size:11px; font-weight:700; display:block; margin-bottom:4px;">Amount (₹)</label>
                        <input type="number" name="adj_amount" step="0.01" required style="width:100%; padding:8px; border:1px solid #cbd5e0; border-radius:6px; box-sizing:border-box;">
                    </div>
                    <div style="margin-bottom:15px;">
                        <label style="font-size:11px; font-weight:700; display:block; margin-bottom:4px;">Adjustment Strategy</label>
                        <select name="adj_type" style="width:100%; padding:8px; border:1px solid #cbd5e0; border-radius:6px;">
                            <option value="charge">Extra Charge (+)</option>
                            <option value="discount">Discount / Rebate (-)</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-start" style="width:100%; padding:10px; border-radius:6px; font-weight:700;">Apply Adjustment</button>
                </form>
            </div>

            <!-- COMMIT CHECKOUT SETTLEMENT CARD -->
            <div class="billing-card" style="border:2px solid #06b6d4; background:#fafdfd;">
                <div class="billing-section-title" style="color:#0891b2; border-color:#0891b2;">🏁 Final Checkout Settlement</div>
                
                <div style="font-size:13px; margin-bottom:15px; border-bottom:1px dashed #cbd5e0; padding-bottom:10px;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:5px;">
                        <span>Pending Accommodation:</span>
                        <span>₹<?= number_format($accommodation_pending, 2) ?></span>
                    </div>
                    <div style="display:flex; justify-content:space-between; font-weight:bold;">
                        <span>Food & Incidentals:</span>
                        <span>₹<?= number_format($total_incidentals_bill, 2) ?></span>
                    </div>
                </div>

                <div style="display:flex; justify-content:space-between; font-size:16px; font-weight:800; color:#1e293b; margin-bottom:20px;">
                    <span>Total Outstanding Due:</span>
                    <span style="color:#059669;">₹<?= number_format(($accommodation_pending + $total_incidentals_bill), 2) ?></span>
                </div>

                <form method="POST" style="margin:0;">
                    <input type="hidden" name="action_finalize_checkout" value="1">
                    <input type="hidden" name="post_food_bill_total" value="<?= $total_incidentals_bill ?>">
                    
                    <div style="margin-bottom:15px;">
                        <label style="font-size:11px; font-weight:700; display:block; margin-bottom:6px; color:#475569;">👤 Who is taking the deposit?</label>
                        <select name="received_by_staff" required class="staff-selector">
                            <option value="">-- Choose Collector --</option>
                            <option value="Tarpan bhaiya">Tarpan bhaiya</option>
                            <option value="Kamlesh">Kamlesh</option>
                            <option value="Abhijit">Abhijit</option>
                            <option value="Kinkar">Kinkar</option>
                            <option value="Subrata">Subrata</option>
                            <option value="Rohit">Rohit</option>
                            <option value="Vikas">Vikas</option>
                            <option value="Raju">Raju</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-bill" style="width:100%; padding:12px; border-radius:8px; font-size:14px; font-weight:800; background:#06b6d4; border-color:#06b6d4;" onclick="return confirm('Confirm processing total collection settlement and ending guest session?');">
                        Complete Checkout & Archive Bill
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include "includes/footer.php"; ?>