<?php
// /home/apartment/artistsfarmjaipur.com/Order/deficient_stock_logs_view.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "config/db.php";

 

// Fetch aggregate statistics for overview cards
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total_incidents,
        COALESCE(SUM(deficit_qty), 0) as total_deficit_items,
        COALESCE(SUM(ordered_qty - delivered_qty), 0) as calculated_loss_count
    FROM deficient_stock_logs
")->fetch(PDO::FETCH_ASSOC);

// Fetch detailed log streams joining catalog items for name references
$logs = $pdo->query("
    SELECT 
        d.id,
        d.requisition_id,
        d.ordered_qty,
        d.delivered_qty,
        d.deficit_qty,
        d.logged_at,
        rc.item_name,
        rc.pack_size,
        rc.pack_unit
    FROM deficient_stock_logs d
    JOIN req_catalog rc ON d.catalog_id = rc.id
    ORDER BY d.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

include "includes/header.php";
?>

<style>
/* --- MOBILE FIRST RESPONSIVE DASHBOARD LAYOUT --- */
.deficit-view-container {
    padding: 16px;
    font-family: 'Segoe UI', Helvetica, Arial, sans-serif;
    text-align: left;
    max-width: 1200px;
    margin: 0 auto;
    box-sizing: border-box;
}

.deficit-header-box {
    margin-bottom: 20px;
}

.deficit-title {
    font-size: 22px;
    font-weight: 700;
    color: #1e293b;
    margin: 0 0 4px 0;
}

.deficit-subtitle {
    font-size: 13px;
    color: #64748b;
    margin: 0;
}

/* Summary Grid Cards */
.deficit-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 12px;
    margin-bottom: 24px;
}

.deficit-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 16px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    border-left: 4px solid #ef4444;
}

.deficit-card.info-card {
    border-left-color: #06b6d4;
}

.deficit-card h3 {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    color: #64748b;
    margin: 0 0 6px 0;
    letter-spacing: 0.5px;
}

.deficit-card .stat-value {
    font-size: 24px;
    font-weight: 700;
    color: #0f172a;
}

/* Responsive Adaptive Data List Table */
.deficit-table-wrapper {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
}

.deficit-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

.deficit-table th {
    background: #f8fafc;
    color: #334155;
    font-weight: 600;
    padding: 14px 16px;
    border-bottom: 2px solid #e2e8f0;
    text-align: left;
}

.deficit-table td {
    padding: 14px 16px;
    border-bottom: 1px solid #f1f5f9;
    color: #475569;
    vertical-align: middle;
}

.deficit-table tr:hover {
    background: #f8fafc;
}

/* Badges and Tags */
.shortfall-badge {
    background: #fef2f2;
    color: #dc2626;
    border: 1px solid #fee2e2;
    padding: 4px 8px;
    border-radius: 6px;
    font-weight: 700;
    font-size: 12px;
    display: inline-block;
}

.req-link-tag {
    color: #06b6d4;
    text-decoration: none;
    font-weight: 700;
    padding: 6px 10px;
    background: #ecfeff;
    border-radius: 6px;
    border: 1px solid #cffafe;
    display: inline-block;
    min-width: 44px;
    text-align: center;
}

/* --- MOBILE TARGET VIEWP0RT OVERRIDES (max-width: 767px) --- */
@media (max-width: 767px) {
    .deficit-table thead {
        display: none; /* Hide standard headers on smartphone view */
    }
    
    .deficit-table tr {
        display: block;
        border-bottom: 3px solid #e2e8f0;
        padding: 12px;
        background: #ffffff;
    }
    
    .deficit-table td {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 4px;
        border: none;
        text-align: right;
        font-size: 13px;
    }
    
    .deficit-table td::before {
        content: attr(data-label);
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        font-size: 11px;
        float: left;
        text-align: left;
    }
    
    .deficit-table td.item-main-cell {
        display: block;
        text-align: left;
        border-bottom: 1px dashed #f1f5f9;
        margin-bottom: 6px;
        padding-bottom: 10px;
    }
    
    .deficit-table td.item-main-cell::before {
        display: block;
        margin-bottom: 4px;
    }
}
</style>

<div class="deficit-view-container">
    
    <div class="deficit-header-box">
        <h2 class="deficit-title">⚠️ Deficit Shortfall Tracker</h2>
        <p class="deficit-subtitle">Auditing discrepancies between raw kitchen material orders and actual warehouse deliveries.</p>
    </div>

    <div class="deficit-stats-grid">
        <div class="deficit-card">
            <h3>Deficit Gaps Logged</h3>
            <div class="stat-value"><?= intval($stats['total_incidents']) ?></div>
        </div>
        <div class="deficit-card">
            <h3>Shortfall Quantity Count</h3>
            <div class="stat-value"><?= floatval($stats['total_deficit_items']) ?></div>
        </div>
        <div class="deficit-card info-card">
            <h3>Fulfilled Items Baseline</h3>
            <div class="stat-value"><?= intval($stats['total_incidents']) > 0 ? 'Audited' : 'Clean' ?></div>
        </div>
    </div>

    <div class="deficit-table-wrapper">
        <table class="deficit-table">
            <thead>
                <tr>
                    <th style="width: 100px;">Date Logged</th>
                    <th style="width: 100px;">Req Envelope</th>
                    <th>Material Descriptor Item</th>
                    <th style="text-align: right;">Ordered Qty</th>
                    <th style="text-align: right;">Delivered Qty</th>
                    <th style="text-align: center; width: 120px;">Shortfall Deficit</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($recent_logs)): foreach ($recent_logs as $log): ?>
                    <tr>
                        <td data-label="Date Logged" style="font-family: monospace; font-weight: 600;">
                            <?= date('d/m/Y', strtotime($log['logged_at'])) ?><br>
                            <span style="color:#94a3b8; font-size:11px;"><?= date('H:i', strtotime($pRow['logged_at'] ?? 'now')) ?></span>
                        </td>
                        <td data-label="Requisition Envelope ID">
                            <a href="requisitions.php" class="badge" style="background:#f1f5f9; color:#475569; text-decoration:none; border:1px solid #cbd5e0;">
                                #ID-<?= $log['requisition_id'] ?>
                            </a>
                        </td>
                        <td data-label="Material Descriptor">
                            <strong style="color: #1e293b; font-size: 13px;"><?= htmlspecialchars($log['item_name'] ?? $log['name'] ?? 'General Staple') ?></strong>
                            <div style="font-size: 11px; color:#64748b; margin-top:2px;">Size Spec: <?= floatval($log['pack_size']) ?> <?= htmlspecialchars($log['pack_unit']) ?></div>
                        </td>
                        <td data-label="Ordered Quantity" style="text-align: right; font-weight: 600;"><?= floatval($log['ordered_qty']) ?></td>
                        <td data-label="Delivered Quantity" style="text-align: right; font-weight: 600; color: #059669;"><?= floatval($log['delivered_qty']) ?></td>
                        <td data-label="Shortfall Deficit" style="text-align: center;">
                            <span class="shortfall-badge">
                                -<?= floatval($log['deficit_qty']) ?> units
                            </span>
                        </td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: #94a3b8; padding: 40px; font-style: italic;">
                            🎉 Perfect Supply Chain Match! No delivery shortfalls or vendor deficits logged.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include "includes/footer.php"; ?>