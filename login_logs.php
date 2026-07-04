<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "config/db.php";

// Restrict access exclusively to privileged Admin or Super Admin roles
if (!isset($_SESSION["role"]) || ($_SESSION["role"] !== "Admin" && $_SESSION["role"] !== "Super Admin")) {
    header("Location: login.php");
    exit;
}

// Fetch the last 50 authentication log records ordered chronologically
$logs = $pdo->query("SELECT * FROM security_login_logs ORDER BY id DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);

include "includes/header.php";
?>

<style>
.audit-log-panel { background: #ffffff !important; border: 1px solid #cbd5e0 !important; border-radius: 12px !important; padding: 20px !important; text-align: left; }
.audit-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.audit-table th { background: #f8fafc; padding: 10px; font-weight: 700; color: #4b5563; border-bottom: 2px solid #cbd5e0; }
.audit-table td { padding: 10px; border-bottom: 1px solid #edf2f7; color: #111827; vertical-align: top; }
.badge-status { font-size: 10px; padding: 2px 6px; border-radius: 4px; font-weight: 700; display: inline-block; }
.status-ok { background: #d1fae5; color: #059669; }
.status-fail { background: #fee2e2; color: #b91c1c; }
.agent-string-text { max-width: 250px; word-break: break-all; font-size: 11px; color: #6b7280; display: block; line-height: 1.3; }
</style>

<div class="app-body" style="max-width:100% !important; width:100% !important;">
    <div class="category-section">
        <h2 class="category-title" style="text-transform: none;">🖥️ Security Device Auditing Footprints</h2>
    </div>

    <div class="audit-log-panel">
        <h3 style="font-size: 14px; font-weight: 700; text-transform: uppercase; margin-top: 0; margin-bottom: 15px; border-bottom: 1px dashed #cbd5e0; padding-bottom: 8px;">Active Session Access Trail Log</h3>
        
        <div style="overflow-x: auto;">
            <table class="audit-table">
                <thead>
                    <tr>
                        <th style="width: 130px;">Timestamp Logged</th>
                        <th style="width: 110px;">Username Typed</th>
                        <th style="width: 100px;">Role Assigned</th>
                        <th style="width: 110px;">Client IP Footprint</th>
                        <th>Device/Hardware Metadata</th>
                        <th style="width: 250px;">Browser Agent Signature</th>
                        <th style="width: 80px; text-align: center;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($logs)): foreach ($logs as $row): 
                        $is_success = ($row['login_status'] === 'Success');
                        $badge_class = $is_success ? 'status-ok' : 'status-fail';
                    ?>
                        <tr style="<?= !$is_success ? 'background: #fff5f5;' : '' ?>">
                            <td style="color: #4b5563; font-weight: 600;"><?= date('d M Y - H:i:s', strtotime($row['logged_at'])) ?></td>
                            <td><strong><?= htmlspecialchars($row['username_entered']) ?></strong></td>
                            <td><span style="color: #4a5568; font-weight:600;"><?= htmlspecialchars($row['role_assigned']) ?></span></td>
                            <td style="font-family: monospace; font-weight: 600; color: #2563eb;"><?= htmlspecialchars($row['ip_address']) ?></td>
                            <td style="font-weight: 600; color: #475569;"><?= htmlspecialchars($row['device_type']) ?></td>
                            <td><span class="agent-string-text"><?= htmlspecialchars($row['browser_agent']) ?></span></td>
                            <td style="text-align: center;">
                                <span class="badge-status <?= $badge_class ?>"><?= $row['login_status'] ?></span>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="7" style="text-align: center; color: #a0aec0; padding: 30px; font-style: italic;">No system logging entries compiled.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include "includes/footer.php"; ?>