<?php
// /home/apartment/artistsfarmjaipur.com/Order/activity_logs.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once "config/db.php";
 // Report all PHP errors
error_reporting(E_ALL);

// Force errors to be displayed on the screen
ini_set('display_errors', '1');

// Fetch up to 150 entries to keep historical data accessible via 'Load More'
$query_string = "
    SELECT al.id, al.action, al.timestamp, u.username 
    FROM audit_logs al 
    LEFT JOIN users u ON al.user_id = u.id 
    ORDER BY al.timestamp DESC 
    LIMIT 150
";
$activities = $pdo->query($query_string)->fetchAll(PDO::FETCH_ASSOC);

include "includes/header.php";

// Helper function to turn technical log strings into human-friendly language
function turnActionIntoHumanLanguage($action_text) {
    if (preg_match('/submitted a new Housekeeping\/Kitchen Material Requisition Request/i', $action_text)) {
        return "Submitted a new material requisition request sheet.";
    }

    if (preg_match('/registered an operational expense under category \[(.*?)\] totaling ₹(.*)/i', $action_text, $matches)) {
        return "Added an expense of ₹" . number_format(floatval($matches[2]), 2) . " under the '" . htmlspecialchars($matches[1]) . "' category.";
    }

    if (preg_match('/registered a kitchen purchase for (.*?) unit\(s\) of \[(.*?)\]/i', $action_text, $matches)) {
        $quantity = floatval($matches[1]);
        $item_name = htmlspecialchars($matches[2]);
        return "Purchased and logged stock of " . $quantity . " units of " . $item_name . ".";
    }

    if (preg_match('/marked Kitchen Ticket #(.*?) as/i', $action_text, $matches)) {
        return "Marked food order ticket #" . htmlspecialchars($matches[1]) . " as ready and served from the kitchen.";
    }

    if (preg_match('/executed final room checkout settlement for Guest \[(.*?)\]/i', $action_text, $matches)) {
        return "Completed final settlement and room checkout for guest: " . htmlspecialchars($matches[1]) . ".";
    }

    return htmlspecialchars($action_text);
}
?>



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
                        <th style="padding:12px 10px; width: 180px;">Timestamp</th>
                        <th style="padding:12px 10px; width: 200px;">Performed By (user name)</th>
                        <th style="padding:12px 10px;">Action Description Summary</th>
                    </tr>
                </thead>
                <tbody id="activityLogTableBody">
                    <?php if (!empty($activities)): foreach ($activities as $row): 
                        $human_friendly_action = turnActionIntoHumanLanguage($row['action']);
                        $search_hash = strtolower(($row['username'] ?? 'system') . ' ' . $human_friendly_action);
                    ?>
                        <tr style="border-bottom:1px solid #edf2f7; display: none;" class="log-row-node" data-search-hash="<?= htmlspecialchars($search_hash) ?>">
                            <td style="padding:12px 10px; color:#64748b; font-family: monospace; font-weight: 600;">
                                <?= date('d M Y - h:i A', strtotime($row['timestamp'])) ?>
                            </td>
                            <td style="padding:12px 10px; font-weight: 700; color: #0f172a;">
                                👤 <?= htmlspecialchars($row['username'] ?? 'System / Automated') ?>
                            </td>
                            <td style="padding:12px 10px; color: #334155; font-weight: 500; line-height: 1.4;">
                                <?= $human_friendly_action ?>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr id="emptyLogsPlaceholder" style="display: table-row !important;"><td colspan="3" style="padding:30px; text-align:center; color:#94a3b8; font-style:italic;">No logged operational actions found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if (count($activities) > 10): ?>
            <button type="button" id="loadMoreActionBtn" class="btn-load-more" onclick="window.revealNextActivityBatch()">Load More</button>
        <?php endif; ?>

    </div>
</div>

<script>
let currentRenderedCount = 0;
const entriesPerPageChunk = 10;
let filteredNodesCache = [];

function initializeActivityLogsPagination() {
    // Collect all data row elements
    const rows = Array.from(document.querySelectorAll(".log-row-node"));
    filteredNodesCache = rows;
    currentRenderedCount = 0;
    
    // Reset views
    rows.forEach(r => r.style.setProperty("display", "none", "important"));
    
    window.revealNextActivityBatch();
}

window.revealNextActivityBatch = function() {
    const nextBatchBound = currentRenderedCount + entriesPerPageChunk;
    const loadMoreBtn = document.getElementById("loadMoreActionBtn");
    
    for (let i = currentRenderedCount; i < nextBatchBound && i < filteredNodesCache.length; i++) {
        filteredNodesCache[i].style.setProperty("display", "table-row", "important");
        currentRenderedCount++;
    }
    
    if (loadMoreBtn) {
        if (currentRenderedCount >= filteredNodesCache.length) {
            loadMoreBtn.style.setProperty("display", "none", "important");
        } else {
            loadMoreBtn.style.setProperty("display", "block", "important");
        }
    }
};

function runLiveActivityFilter() {
    let query = document.getElementById("activitySearchField").value.toLowerCase().trim();
    let rows = Array.from(document.querySelectorAll(".log-row-node"));
    const placeholder = document.getElementById("emptyLogsPlaceholder");
    const loadMoreBtn = document.getElementById("loadMoreActionBtn");
    
    // Clear display matching loops
    rows.forEach(r => r.style.setProperty("display", "none", "important"));
    
    if (query === "") {
        filteredNodesCache = rows;
        currentRenderedCount = 0;
        if (placeholder) placeholder.style.setProperty("display", "none", "important");
        window.revealNextActivityBatch();
        return;
    }
    
    // Filter rows based on search parameters hash matching
    filteredNodesCache = rows.filter(row => {
        let hash = row.getAttribute("data-search-hash") || "";
        return hash.includes(query);
    });
    
    currentRenderedCount = 0;
    
    if (filteredNodesCache.length === 0) {
        if (placeholder) placeholder.style.setProperty("display", "table-row", "important");
        if (loadMoreBtn) loadMoreBtn.style.setProperty("display", "none", "important");
    } else {
        if (placeholder) placeholder.style.setProperty("display", "none", "important");
        window.revealNextActivityBatch();
    }
}

// Fire table pagination on structural layout readiness
document.addEventListener("DOMContentLoaded", initializeActivityLogsPagination);
</script>

<?php include "includes/footer.php"; ?>