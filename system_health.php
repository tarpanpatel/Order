<?php
// /home/apartment/artistsfarmjaipur.com/Order/system_health.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once "config/db.php";

// Only for Admins
if ($_SESSION["role"] !== "Super Admin") { die("Unauthorized."); }

// 1. Find Gaps: Orders that exist but have no corresponding Audit Log
$gaps = $pdo->query("
    SELECT id, 'orders' as table_name 
    FROM orders 
    WHERE id NOT IN (
        SELECT CAST(SUBSTRING_INDEX(action, '#', -1) AS UNSIGNED) 
        FROM audit_logs 
        WHERE action LIKE '%Ticket%'
    )
")->fetchAll();

include "includes/header.php";
?>
<div class="app-body" style="padding: 20px;">
    <h2>🖥️ System Health Dashboard</h2>
    
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        <div class="card">
            <h3>⚠️ Audit Log Gaps (Unlogged Actions)</h3>
            <?php if (empty($gaps)): ?>
                <p style="color: green;">✔ No discrepancies found.</p>
            <?php else: ?>
                <table style="width: 100%;">
                    <?php foreach ($gaps as $g): ?>
                        <tr><td>Order #<?= $g['id'] ?></td><td style="color: red;">Missing Log!</td></tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3>📡 Telegram Notification Status</h3>
            <p>Last sent notification: 
                <?php 
                $lastLog = $pdo->query("SELECT timestamp FROM audit_logs WHERE action LIKE '%Telegram%' ORDER BY id DESC LIMIT 1")->fetchColumn();
                echo $lastLog ?: "Never sent"; 
                ?>
            </p>
        </div>
    </div>
</div>