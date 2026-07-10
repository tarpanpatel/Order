<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "config/db.php";

 
// --- NEW FEATURE: AJAX/POST HANDLER FOR INCOMING DEFICIENT MATERIALS ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action_receive_deficient_stock"])) {
    $log_id = intval($_POST["log_id"]);
    $incoming_qty = intval($_POST["incoming_qty"]);

    if ($log_id > 0 && $incoming_qty > 0) {
        $pdo->beginTransaction();
        try {
            // Fetch current deficit status metrics
            $stmt = $pdo->prepare("SELECT deficit_qty, delivered_qty FROM deficient_stock_logs WHERE id = ?");
            $stmt->execute([$log_id]);
            $log = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($log) {
                $new_deficit = max(0, $log['deficit_qty'] - $incoming_qty);
                $new_delivered = $log['delivered_qty'] + min($incoming_qty, $log['deficit_qty']);

                // Recalculate deficit and update delivery totals
                $update = $pdo->prepare("UPDATE deficient_stock_logs SET delivered_qty = ?, deficit_qty = ? WHERE id = ?");
                $update->execute([$new_delivered, $new_deficit, $log_id]);
            }
            $pdo->commit();
            header("Location: index.php");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
        }
    }
}

// --- CENTRAL ANALYTICS ROUTER: EVALUATE MATERIAL STOCK THRESHOLDS ---
$stock_alerts = $pdo->query("
    SELECT m.item_name, m.min_threshold_qty, COALESCE(SUM(k.qty), 0) as current_available_stock
    FROM materials_registry m
    LEFT JOIN kitchen_expenses k ON LOWER(m.item_name) = LOWER(k.item_detail)
    GROUP BY m.item_name, m.min_threshold_qty
    HAVING current_available_stock <= m.min_threshold_qty
")->fetchAll(PDO::FETCH_ASSOC);

// 1. FETCH ACTIVE GUEST CARD DETAILED LEDGER DATA
$guest = $pdo->query("SELECT * FROM guests WHERE status = 'Active' LIMIT 1")->fetch(PDO::FETCH_ASSOC);

// 2. FETCH LAST 5 KITCHEN ORDERS WITH CONCATENATED MENU ITEM NAMES
$recent_orders = $pdo->query("SELECT o.id, g.guest_name, o.order_time, o.status,
                              (SELECT GROUP_CONCAT(CONCAT(mi.name, ' (x', oi.quantity, ')') SEPARATOR ', ') 
                               FROM order_items oi 
                               JOIN menu_items mi ON oi.menu_item_id = mi.id 
                               WHERE oi.order_id = o.id) as item_summary
                              FROM orders o 
                              JOIN guests g ON o.guest_id = g.id 
                              ORDER BY o.id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

// 3. FETCH LAST 5 PENDING MATERIAL REQUESTS WITH THE CORRECT CATALOG JOIN
$recent_requisitions = $pdo->query("SELECT r.id, r.requested_at, r.status,
                                    (SELECT GROUP_CONCAT(CONCAT(rc.item_name, ' (x', ri.quantity, ')') SEPARATOR ', ')
                                     FROM requisition_items ri
                                     JOIN req_catalog rc ON ri.catalog_id = rc.id
                                     WHERE ri.requisition_id = r.id) as material_summary
                                    FROM requisitions r
                                    WHERE r.status = 'Pending' OR r.status = '' 
                                    ORDER BY r.id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

// 4. FETCH ONLY ACTIVE DEFICIENCIES WHERE DEFICIT QUANTITY IS GREATER THAN 0
$deficient_items = $pdo->query("SELECT d.*, rc.item_name 
                                FROM deficient_stock_logs d 
                                JOIN req_catalog rc ON d.catalog_id = rc.id 
                                WHERE d.deficit_qty > 0
                                ORDER BY d.id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

include "includes/header.php";
?>



<div class="app-body">
    <div class="category-section" style="margin-bottom: 5px;">
        <h2 class="category-title" style="text-transform: none;">📊 Operational Environment</h2>
       
    </div>

    <?php if (!empty($stock_alerts)): ?>
        <div style="padding: 14px; background: #fef2f2; border: 1px dashed #ef4444; border-radius: 12px; color: #b91c1c; font-size: 12px; font-weight: bold; margin-bottom: 20px; text-align: left;">
            <span style="font-size:13px; display:block; margin-bottom:6px;">⚠️ INVENTORY STOCK ALERT BOUNDARY THRESHOLDS:</span>
            <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                <?php foreach ($stock_alerts as $alert): ?>
                    <span style="background: #fee2e2; border: 1px solid #fca5a5; padding: 4px 8px; border-radius: 6px; font-size: 11px;">
                        <strong><?= htmlspecialchars($alert['item_name']) ?></strong> (Low: <?= $alert['current_available_stock'] ?> units left)
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="dashboard-grid-matrix">

        <div class="widget-card" style="border-top: 3px solid #38a169 !important;">
            <div>
                <h3 class="widget-title">🟢 Active Resident Profile</h3>
                <?php if ($guest): ?>
                    <table class="guest-profile-table">
                        <tr>
                            <th>Guest Profile:</th>
                            <td><strong><?= htmlspecialchars($guest["guest_name"]) ?></strong></td>
                        </tr>
                        <tr>
                            <th>Contact Phone:</th>
                            <td><?= htmlspecialchars($guest["phone_number"]) ?></td>
                        </tr>
                        <tr>
                            <th>Stay Duration:</th>
                            <td>
                                <?= date('d M', strtotime($guest["checkin_date"])) ?> to 
                                <?= date('d M', strtotime($guest["expected_checkout"])) ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Advance Paid:</th>
                            <td style="color: #38a169; font-weight: 700;">₹<?= number_format($guest["advance_paid"], 0) ?></td>
                        </tr>
                        <tr>
                            <th>Balance Pending:</th>
                            <td style="color: #e53e3e; font-weight: 700;">₹<?= number_format($guest["pending_amount"], 0) ?></td>
                        </tr>
                    </table>
                <?php else: ?>
                    <p style="color: #9ca3af; font-size: 13px; font-style: italic; padding: 20px 0; text-align: center;">No current running guest check-in session.</p>
                <?php endif; ?>
            </div>
            <?php if ($guest): ?>
                <a href="billing.php" class="widget-btn-action">Settlements & Billing Ledger →</a>
            <?php else: ?>
                <a href="checkin.php" class="widget-btn-action">Register New Guest →</a>
            <?php endif; ?>
        </div>

        <div class="widget-card" style="border-top: 3px solid #06b6d4 !important;">
            <div>
                <h3 class="widget-title">🍽️ Recent Kitchen Tickets</h3>
                <ul class="widget-data-list">
                    <?php if (!empty($recent_orders)): foreach ($recent_orders as $order): ?>
                        <li class="widget-data-item">
                            <div style="min-width: 0; flex: 1;">
                                <strong style="color: var(--text-main); font-size: 13px; display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    <?= !empty($order['item_summary']) ? htmlspecialchars($order['item_summary']) : "Empty Ticket" ?>
                                </strong>
                                <span style="color: #6b7280; font-size: 11px; display: block; margin-top: 2px;">
                                    <?= htmlspecialchars($order['guest_name']) ?> • <?= date('H:i', strtotime($order['order_time'])) ?>
                                </span>
                            </div>
                            <span class="status-badge-tag <?= $order['status'] === 'Pending' ? 'badge-pending' : 'badge-completed' ?>">
                                <?= $order['status'] ?>
                            </span>
                        </li>
                    <?php endforeach; else: ?>
                        <p style="color: #9ca3af; font-size: 13px; font-style: italic; padding: 35px 0; text-align: center;">No recent kitchen transactions.</p>
                    <?php endif; ?>
                </ul>
            </div>
            <a href="kitchen.php" class="widget-btn-action">See more Kitchen Orders →</a>
        </div>

        <div class="widget-card" style="border-top: 3px solid #eab308 !important;">
            <div>
                <h3 class="widget-title">📦 Open Material Requisitions</h3>
                <ul class="widget-data-list">
                    <?php if (!empty($recent_requisitions)): foreach ($recent_requisitions as $req): ?>
                        <li class="widget-data-item">
                            <div style="min-width: 0; flex: 1;">
                                <strong style="color: var(--text-main); font-size: 13px; display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    <?= !empty($req['material_summary']) ? htmlspecialchars($req['material_summary']) : "Unassigned Request" ?>
                                </strong>
                                <span style="color: #6b7280; font-size: 11px; display: block; margin-top: 2px;">
                                    Requested: <?= date('d M - H:i', strtotime($req['requested_at'])) ?>
                                </span>
                            </div>
                            <span class="status-badge-tag badge-pending">
                                <?= empty($req['status']) ? 'Pending' : htmlspecialchars($req['status']) ?>
                            </span>
                        </li>
                    <?php endforeach; else: ?>
                        <p style="color: #9ca3af; font-size: 13px; font-style: italic; padding: 35px 0; text-align: center;">No open housekeeping material requests found.</p>
                    <?php endif; ?>
                </ul>
            </div>
            <a href="requisitions.php" class="widget-btn-action">See more Material Requests →</a>
        </div>

        <div class="widget-card" style="border-top: 3px solid #e53e3e !important;">
            <div>
                <h3 class="widget-title" style="color: #e53e3e;">⚠️ Stock Deficiencies</h3>
                <ul class="widget-data-list">
                    <?php if (!empty($deficient_items)): foreach ($deficient_items as $dItem): ?>
                        <li class="widget-data-item" style="flex-direction: column; align-items: flex-start; gap: 6px;">
                            <div style="width: 100%; display: flex; justify-content: space-between; align-items: start;">
                                <div style="min-width: 0; flex: 1;">
                                    <strong style="color: #b91c1c; font-size: 13px; display: block;">
                                        <?= htmlspecialchars($dItem['item_name']) ?> (Short: <span style="font-size:14px;">x<?= $dItem['deficit_qty'] ?></span>)
                                    </strong>
                                    <span style="color: #6b7280; font-size: 11px; display: block; margin-top: 2px;">
                                        Req #<?= $dItem['requisition_id'] ?> • Asked: <?= $dItem['ordered_qty'] ?> | Recv: <?= $dItem['delivered_qty'] ?>
                                    </span>
                                </div>
                                <span style="font-size: 11px; color: #718096; white-space: nowrap;">
                                    <?= date('d M', strtotime($dItem['logged_at'])) ?>
                                </span>
                            </div>
                            
                            <form method="POST" action="index.php" style="margin: 0; width: 100%; display: flex; gap: 6px; align-items: center; background: #fff5f5; padding: 6px; border-radius: 6px; border: 1px solid #fee2e2;">
                                <input type="hidden" name="action_receive_deficient_stock" value="1">
                                <input type="hidden" name="log_id" value="<?= $dItem['id'] ?>">
                                <span style="font-size: 11px; font-weight: bold; color: #991b1b;">Log Arrival:</span>
                                <input type="number" name="incoming_qty" value="<?= $dItem['deficit_qty'] ?>" max="<?= $dItem['deficit_qty'] ?>" min="1" required style="width: 50px; padding: 4px; font-size: 11px; text-align: center; border: 1px solid #fca5a5; border-radius: 4px; margin: 0;">
                                <button type="submit" class="btn btn-start" style="padding: 4px 8px; font-size: 10px; font-weight: bold; border-radius: 4px; width: auto; background: #ef4444; border-color: #ef4444; margin: 0;">Receive</button>
                            </form>
                        </li>
                    <?php endforeach; else: ?>
                        <p style="color: #9ca3af; font-size: 13px; font-style: italic; padding: 45px 0; text-align: center;">No stock deficiencies recorded. Supply lines are matching requests!</p>
                    <?php endif; ?>
                </ul>
            </div>
            <span class="widget-btn-action" style="background:#fafafa; color:#9ca3af; cursor:default; margin-top: 10px;">Automated Deficiency Audit Trail Log</span>
        </div>

    </div>
</div>

<?php include "includes/footer.php"; ?>