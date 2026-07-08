<?php
// /home/apartment/artistsfarmjaipur.com/Order/activity_logs.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once "config/db.php";

if (!isset($_SESSION["user_id"])) { 
    header("Location: login.php"); 
    exit; 
}

// Restrict access strictly to Administrative roles
if ($_SESSION["role"] !== "Admin" && $_SESSION["role"] !== "Super Admin") {
    die("Security Exception: Access Denied.");
}

// Fetch all tracked backend modifications joined with the users table to get real names
$query_string = "
    SELECT al.id, al.action, al.timestamp, u.username, u.role 
    FROM audit_logs al 
    LEFT JOIN users u ON al.user_id = u.id 
    ORDER BY al.timestamp DESC 
    LIMIT 150
";
$activities = $pdo->query($query_string)->fetchAll(PDO::FETCH_ASSOC);

include "includes/header.php";
?>

<style>
.form-input-container { width:100%; padding:10px; border:1px solid #cbd5e0; border-radius:8px; box-sizing:border-box; font-size:14px; font-weight:600; color:#1e293b; background:#fff; }
.form-label-header { font-size:11px; font-weight:700; color:#475569; display:block; margin-bottom:5px; text-transform:uppercase; }
.log-row-node { transition: background 0.1s ease; }
.log-row-node:hover { background: #f8fafc; }
</style>

<div class="app-body" style="padding: 20px; font-family: sans-serif; text-align: left;">
    <div style="background: #fff; border: 1px solid #cbd5e0; border-radius: 12px; padding: 25px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px; margin-bottom: 20px;">
            <div>
                <h3 style="margin:0; color:#1e293b; text-transform: uppercase; font-size:15px; letter-spacing:0.5px;">👣 Staff Activity Trail</h3>
                <p style="margin:3px 0 0 0; font-size:12px; color:#64748b;">Real-time history of backend actions performed by logged-in staff</p>
            </div>
            <div style="max-width: 320px; width: 100%;">
                <input type="text" id="activitySearchField" placeholder="Filter by user, action, or keyword..." class="form-input-container" style="border-color: #06b6d4;" oninput="runLiveActivityFilter()">
            </div>
        </div>

        <div style="overflow-x: auto;">
            <table style="width:100%; border-collapse:collapse; font-size:13px; text-align:left;">
                <thead>
                    <tr style="background:#f8fafc; border-bottom:2px solid #cbd5e0; color:#475569; text-transform:uppercase; font-size:11px;">
                        <th style="padding:12px 10px; width: 160px;">Timestamp</th>
                        <th style="padding:12px 10px; width: 150px;">Performed By</th>
                        <th style="padding:12px 10px; width: 120px;">Staff Role</th>
                        <th style="padding:12px 10px;">Action Description Summary</th>
                    </tr>
                </thead>
                <tbody id="activityLogTableBody">
                    <?php if (!empty($activities)): foreach ($activities as $row): 
                        $search_hash = strtolower(($row['username'] ?? 'system') . ' ' . ($row['role'] ?? 'automated') . ' ' . $row['action']);
                    ?>
                        <tr style="border-bottom:1px solid #edf2f7;" class="log-row-node" data-search-hash="<?= htmlspecialchars($search_hash) ?>">
                            <td style="padding:12px 10px; color:#64748b; font-family: monospace; font-weight: 600;">
                                <?= date('d M Y - H:i:s', strtotime($row['timestamp'])) ?>
                            </td>
                            <td style="padding:12px 10px; font-weight: 700; color: #0f172a;">
                                👤 <?= htmlspecialchars($row['username'] ?? 'System / Automated') ?>
                            </td>
                            <td style="padding:12px 10px;">
                                <span style="font-size:11px; font-weight:bold; padding:2px 6px; border-radius:4px; background:#e0f2fe; color:#0369a1;">
                                    <?= htmlspecialchars($row['role'] ?? 'System') ?>
                                </span>
                            </td>
                            <td style="padding:12px 10px; color: #334155; font-weight: 500; line-height: 1.4;">
                                <?= htmlspecialchars($row['action']) ?>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr id="emptyLogsPlaceholder"><td colspan="4" style="padding:30px; text-align:center; color:#94a3b8; font-style:italic;">No logged operational background actions found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function runLiveActivityFilter() {
    let query = document.getElementById("activitySearchField").value.toLowerCase().trim();
    let rows = document.querySelectorAll(".log-row-node");
    
    rows.forEach(row => {
        let hash = row.getAttribute("data-search-hash") || "";
        if (hash.includes(query)) {
            row.style.display = "";
        } else {
            row.style.display = "none";
        }
    });
}
</script>

<?php include "includes/footer.php"; ?>