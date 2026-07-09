<?php
// /home/apartment/artistsfarmjaipur.com/Order/requisitions_log.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once "config/db.php";
 

$selectedMonth = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));
$selectedYear  = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));

$availableDates = $pdo->query("SELECT DISTINCT MONTH(requested_at) as m, YEAR(requested_at) as y FROM requisitions WHERE requested_at IS NOT NULL ORDER BY y DESC, m DESC")->fetchAll(PDO::FETCH_ASSOC);

if (empty($availableDates)) {
    $availableDates[] = ['m' => intval(date('m')), 'y' => intval(date('Y'))];
}

$stmt = $pdo->prepare("
    SELECT r.id, r.requested_at, r.status,
           (SELECT GROUP_CONCAT(CONCAT(rc.item_name, ' (x', ri.quantity, ')') SEPARATOR ', ')
            FROM requisition_items ri
            JOIN req_catalog rc ON ri.catalog_id = rc.id
            WHERE ri.requisition_id = r.id) as item_summary
    FROM requisitions r
    WHERE MONTH(r.requested_at) = :m AND YEAR(r.requested_at) = :y
    ORDER BY r.id DESC
");
$stmt->execute([':m' => $selectedMonth, ':y' => $selectedYear]);
$loggedRequisitions = $stmt->fetchAll(PDO::FETCH_ASSOC);

include "includes/header.php";
?>

<style>
.log-filter-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }
.filter-box-card { display: flex; gap: 10px; align-items: center; background: #fff; padding: 10px 15px; border-radius: 8px; border: 1px solid #e2e8f0; }
.filter-box-card select { padding: 6px 12px; border-radius: 6px; border: 1px solid #cbd5e0; background: #fff; font-size: 13px; }
.filter-box-card button, .btn-link-back { padding: 6px 14px; background: #06b6d4; color: #fff; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 13px; text-decoration: none; display: inline-block; }
.filter-box-card button:hover, .btn-link-back:hover { background: #0891b2; }
.btn-back-style { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e0; margin-right: 10px; }
.btn-back-style:hover { background: #e2e8f0; }

.log-table-container { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); text-align: left; }
.past-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.past-table th { background: #f8fafc; padding: 12px; font-weight: 700; color: #4b5563; border-bottom: 2px solid #e2e8f0; }
.past-table td { padding: 12px; border-bottom: 1px solid #edf2f7; color: #111827; vertical-align: middle; }
.status-pill { font-size: 11px; padding: 3px 8px; border-radius: 12px; font-weight: 700; display: inline-block; }
.status-pill.p-pending { background: #fef3c7; color: #d97706; }
.status-pill.p-fulfilled { background: #d1fae5; color: #059669; }

.modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 99999; justify-content: center; align-items: center; backdrop-filter: blur(4px); }
.modal-content { background: white; max-width: 500px; width: 90%; border-radius: 12px; padding: 25px; position: relative; color: #111827; text-align: left; }
</style>

<div class="app-body" style="max-width: 100% !important; width: 100% !important; display: block !important;">
    
    <div class="log-filter-header">
        <div style="display: flex; align-items: center;">
            <a href="requisitions.php" class="btn-link-back btn-back-style">⬅ Return</a>
            <h2 style="font-size: 20px; font-weight: 700; color: #1e293b; margin: 0;">📂 Requisition Archives Log Lookups</h2>
        </div>
        
        <form method="GET" action="requisitions_log.php" class="filter-box-card">
            <select name="month">
                <?php 
                $monthsLogged = array_unique(array_column($availableDates, 'm'));
                sort($monthsLogged);
                foreach ($monthsLogged as $m): 
                    $dateObj = DateTime::createFromFormat('!m', $m);
                    echo "<option value='{$m}' ".($m == $selectedMonth ? 'selected' : '').">{$dateObj->format('F')}</option>";
                endforeach; ?>
            </select>
            <select name="year">
                <?php 
                $yearsLogged = array_unique(array_column($availableDates, 'y'));
                sort($yearsLogged);
                foreach ($yearsLogged as $y):
                    echo "<option value='{$y}' ".($y == $selectedYear ? 'selected' : '').">{$y}</option>";
                endforeach; ?>
            </select>
            <button type="submit">Filter Archives</button>
        </form>
    </div>

    <div class="log-table-container">
        <div style="overflow-x: auto;">
            <table class="past-table">
                <thead>
                    <tr>
                        <th style="width: 80px; text-align: center;">Req ID</th>
                        <th style="width: 140px;">Requested At</th>
                        <th>Material Selections Summary</th>
                        <th style="width: 110px; text-align: center;">Status</th>
                        <th style="width: 160px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($loggedRequisitions)): foreach ($loggedRequisitions as $pRow): 
                        $summary_clean = !empty($pRow['item_summary']) ? $pRow['item_summary'] : '<span style="color:#a0aec0; font-style:italic;">No mapped materials</span>';
                        $is_fulfilled = ($pRow['status'] === 'Fulfilled');
                        $pill_class = $is_fulfilled ? 'p-fulfilled' : 'p-pending';
                        $status_label = empty($pRow['status']) ? 'Pending' : $pRow['status'];

                        $lines = $pdo->prepare("SELECT ri.catalog_id, ri.quantity, rc.item_name as name FROM requisition_items ri JOIN req_catalog rc ON ri.catalog_id = rc.id WHERE ri.requisition_id = ?");
                        $lines->execute([$pRow['id']]);
                        $serializedItems = json_encode($lines->fetchAll(PDO::FETCH_ASSOC));
                    ?>
                        <tr>
                            <td style="text-align: center; font-weight: 700; color: #4a5568;">#<?= $pRow['id'] ?></td>
                            <td style="color: #718096;"><?= date('d M Y - H:i', strtotime($pRow['requested_at'])) ?></td>
                            <td style="font-weight: 600; color: #2d3748;"><?= $summary_clean ?></td>
                            <td style="text-align: center;">
                                <span class="status-pill <?= $pill_class ?>"><?= $status_label ?></span>
                            </td>
                            <td style="text-align: center;">
                                <div style="display: flex; gap: 6px; justify-content: center;">
                                    <button type="button" class="btn btn-start" style="padding: 6px 10px; font-size: 11px; border-radius: 4px;" data-items='<?= htmlspecialchars($serializedItems, ENT_QUOTES, 'UTF-8') ?>' onclick="window.openEditRequisitionModal(<?= $pRow['id'] ?>, '<?= $status_label ?>', this)">✏ Edit</button>
                                    <?php if (!$is_fulfilled): ?>
                                        <form method="POST" action="requisitions.php" style="margin:0;" onsubmit="return confirm('Mark request #<?= $pRow['id'] ?> as complete?');">
                                            <input type="hidden" name="action_complete_requisition" value="1">
                                            <input type="hidden" name="complete_req_id" value="<?= $pRow['id'] ?>">
                                            <button type="submit" class="btn btn-bill" style="padding: 6px 10px; font-size: 11px; border-radius: 4px; background: #38a169; border-color: #38a169;">✔ Complete</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="5" style="text-align: center; color: #a0aec0; padding: 40px;">No matching historical parameters logged for this month selection scope.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="editReqModalPopup" class="modal">
    <div class="modal-content">
        <span style="position: absolute; top: 12px; right: 16px; font-size: 22px; cursor: pointer; color: #a0aec0;" onclick="window.closeEditReqModal()">✕</span>
        <h3 style="font-size: 15px; font-weight: 700; text-transform: uppercase; margin-bottom: 15px; border-bottom: 1px dashed #e2e8f0; padding-bottom: 8px;">Modify Requisition Parameters</h3>
        
        <form method="POST" action="requisitions.php" style="margin: 0;">
            <input type="hidden" name="action_update_requisition" value="1">
            <input type="hidden" name="update_req_id" id="mdlUpdateId">
            
            <div style="margin-bottom: 15px;">
                <label style="font-size: 12px; font-weight: 600; color: #4b5563; display: block; margin-bottom: 6px;">Ticket Status Allocation</label>
                <select name="update_req_status" id="mdlUpdateStatus" style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid #cbd5e0; font-size: 13px;">
                    <option value="Pending">Pending</option>
                    <option value="Fulfilled">Fulfilled</option>
                </select>
            </div>

            <h4 style="font-size: 12px; font-weight: 700; text-transform: uppercase; color: #4b5563; margin-bottom: 10px;">Item Quantity Mapping</h4>
            <div id="mdlItemsContainer" style="max-height: 200px; overflow-y: auto; border: 1px solid #edf2f7; border-radius: 6px; padding: 8px; margin-bottom: 20px; background: #fdfdfd;"></div>

            <div style="display: flex; gap: 10px; justify-content: flex-end; align-items: center;">
                <button type="button" class="btn btn-log" style="padding: 10px 18px;" onclick="window.closeEditReqModal()">Cancel</button>
                <button type="submit" class="btn btn-start" style="padding: 10px 18px;">Commit Updates</button>
            </div>
        </form>
    </div>
</div>

<script>
window.openEditRequisitionModal = function(reqId, currentStatus, element) {
    document.getElementById("mdlUpdateId").value = reqId;
    document.getElementById("mdlUpdateStatus").value = currentStatus;
    
    const container = document.getElementById("mdlItemsContainer");
    const rawItemsData = element.getAttribute("data-items");
    
    try {
        const items = JSON.parse(rawItemsData);
        if (!items || items.length === 0) {
            container.innerHTML = '<p style="text-align:center; color:#e53e3e; font-size:12px;">No sub-items loaded.</p>';
            return;
        }
        
        container.innerHTML = items.map(i => `
            <div style="display:flex; justify-content:space-between; align-items:center; padding:8px 0; border-bottom:1px solid #edf2f7;">
                <span style="font-size:13px; font-weight:600; color:#2d3748;">${i.name}</span>
                <input type="number" name="req_item_qty[${i.catalog_id}]" value="${i.quantity}" min="0" style="width:65px; padding:5px; border:1px solid #cbd5e0; border-radius:4px; text-align:center; font-size:13px;">
            </div>
        `).join('');
        
        document.getElementById("editReqModalPopup").style.display = "flex";
    } catch(err) {
        container.innerHTML = '<p style="text-align:center; color:#e53e3e; font-size:12px;">Failed parsing data bundle lines.</p>';
    }
};

window.closeEditReqModal = function() { 
    document.getElementById("editReqModalPopup").style.display = "none"; 
};
</script>

<?php include "includes/footer.php"; ?>