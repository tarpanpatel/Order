<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "config/db.php";

if (!isset($_SESSION["user_id"])) { 
    header("Location: login.php"); 
    exit; 
}

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

/// 3. FETCH LAST 5 PENDING MATERIAL REQUESTS WITH THE CORRECT LIVE MATERIALS TABLE MATCH
$recent_requisitions = $pdo->query("SELECT r.id, r.requested_at, r.status,
                                    (SELECT GROUP_CONCAT(CONCAT(m.name, ' (x', ri.quantity, ')') SEPARATOR ', ')
                                     FROM requisition_items ri
                                     JOIN materials m ON ri.catalog_id = m.id
                                     WHERE ri.requisition_id = r.id) as material_summary
                                    FROM requisitions r
                                    WHERE r.status = 'Pending' OR r.status = '' 
                                    ORDER BY r.id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

include "includes/header.php";
?>

<style>
/* ==========================================================================
   DASHBOARD WIDGET MATRIX GRID SYSTEM LAYOUTS
   ========================================================================== */
.dashboard-grid-matrix {
    display: grid !important;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)) !important;
    gap: 20px !important;
    width: 100% !important;
    margin-top: 15px;
}

.widget-card {
    background: #ffffff !important;
    border: 1px solid #e2e8f0 !important;
    border-radius: 12px !important;
    padding: 20px !important;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05) !important;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    text-align: left;
}

.widget-title {
    font-size: 14px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #4b5563;
    border-bottom: 1px dashed #e2e8f0;
    padding-bottom: 10px;
    margin-bottom: 15px;
    margin-top: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.widget-data-list {
    list-style: none;
    padding: 0;
    margin: 0;
    flex-grow: 1;
}

.widget-data-item {
    padding: 12px 0;
    border-bottom: 1px solid #f3f4f6;
    font-size: 13px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
}

.widget-data-item:last-child {
    border-bottom: none;
}

.widget-btn-action {
    display: block;
    text-align: center;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    color: #4b5563;
    padding: 8px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 700;
    text-decoration: none;
    margin-top: 15px;
    transition: all 0.2s ease;
}

.widget-btn-action:hover {
    background: #edf2f7;
    color: #1a202c;
}

.status-badge-tag {
    font-size: 10px;
    padding: 2px 6px;
    border-radius: 4px;
    font-weight: 700;
    white-space: nowrap;
}

.badge-pending { background: #fef3c7; color: #d97706; }
.badge-completed { background: #d1fae5; color: #059669; }

.guest-profile-table {
    width: 100%;
    font-size: 13px;
    border-collapse: collapse;
}

.guest-profile-table th {
    font-weight: 600;
    color: #6b7280;
    padding: 6px 0;
    width: 130px;
}

.guest-profile-table td {
    color: #111827;
    padding: 6px 0;
}
</style>

<div class="app-body">
    <div class="category-section" style="margin-bottom: 5px;">
        <h2 class="category-title" style="text-transform: none;">📊 Operational Environment</h2>
        <p style="color: var(--text-muted); margin-bottom: 1.5rem; font-size: 13px;">The Artists Farm Admin Panel Hub</p>
    </div>

    <div class="dashboard-grid-matrix">

        <!-- BOX 1: CURRENT ACTIVE GUEST LEDGER PROFILE -->
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

        <!-- BOX 2: LAST 5 KITCHEN ORDERS WITH ACTUAL ITEM NAMES -->
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

        <!-- BOX 3: PENDING MATERIAL REQUESTS WITH ACTUAL MATERIAL NAMES -->
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

    </div>
</div>

<?php include "includes/footer.php"; ?>