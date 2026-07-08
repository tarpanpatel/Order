<?php
// /home/apartment/artistsfarmjaipur.com/Order/login_logs.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once "config/db.php";

if (!isset($_SESSION["user_id"])) { 
    header("Location: login.php"); 
    exit; 
}

// Restrict access to administrative staff layers
if ($_SESSION["role"] !== "Admin" && $_SESSION["role"] !== "Super Admin") {
    die("Security Exception: Access Denied to unauthorized monitoring parameters.");
}

// Pull authorization history logs mapped to entries
$logs = $pdo->query("SELECT * FROM security_login_logs ORDER BY id DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);

include "includes/header.php";
?>

<div class="app-body" style="padding: 20px; font-family: sans-serif; text-align: left;">
    <div style="background: #fff; border: 1px solid #cbd5e0; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        <h3 style="margin-top:0; color:#1e293b; border-bottom:1px solid #e2e8f0; padding-bottom:10px; text-transform:uppercase; font-size:14px; letter-spacing:0.5px;">🖥️ Security Access Trace Logs</h3>
        
        <div style="overflow-x: auto; margin-top: 15px;">
            <table style="width:100%; border-collapse:collapse; font-size:13px; text-align:left;">
                <thead>
                    <tr style="background:#f8fafc; border-bottom:2px solid #cbd5e0; color:#475569; text-transform:uppercase; font-size:11px;">
                        <th style="padding:12px 10px;">Timestamp</th>
                        <th style="padding:12px 10px;">Username Profile</th>
                        <th style="padding:12px 10px;">Security Code Used</th>
                        <th style="padding:12px 10px;">Role Checked</th>
                        <th style="padding:12px 10px;">IP Address</th>
                        <th style="padding:12px 10px;">Device Endpoint</th>
                        <th style="padding:12px 10px; text-align:center;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(!empty($logs)): foreach ($logs as $row): ?>
                        <tr style="border-bottom:1px solid #edf2f7; background: <?= $row['login_status'] === 'Failed' ? '#fff5f5' : 'transparent'; ?>">
                            <td style="padding:12px 10px; color:#64748b; font-weight:600;"><?= $row['logged_at'] ?></td>
                            <td style="padding:12px 10px; font-weight:700; color:#1e293b;"><?= htmlspecialchars($row['username_entered']) ?></td>
                            <td style="padding:12px 10px; font-family:monospace; font-size:14px; color:#475569;">••••</td>
                            <td style="padding:12px 10px;"><span style="font-weight:700; color:#0284c7;"><?= htmlspecialchars($row['role_assigned']) ?></span></td>
                            <td style="padding:12px 10px; color:#4a5568; font-family:monospace;"><?= htmlspecialchars($row['ip_address']) ?></td>
                            <td style="padding:12px 10px; color:#718096; font-size:12px;"><?= htmlspecialchars($row['device_type']) ?></td>
                            <td style="padding:12px 10px; text-align:center;">
                                <span style="font-size:10px; font-weight:bold; padding:3px 8px; border-radius:12px; <?= $row['login_status'] === 'Success' ? 'background:#d1fae5; color:#065f46;' : 'background:#fee2e2; color:#991b1b;'; ?>">
                                    <?= $row['login_status'] ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="7" style="padding:20px; text-align:center; color:#94a3b8; font-style:italic;">No authorization records trace logs found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include "includes/footer.php"; ?>