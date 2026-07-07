<?php
// /home/apartment/artistsfarmjaipur.com/Order/expenses.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once "config/db.php";

// FIXED SYSTEM PATH: Load the dual-channel configuration engine safely
if (file_exists(__DIR__ . "/config/telegram.php")) {
    require_once __DIR__ . "/config/telegram.php";
}

if (!isset($_SESSION["user_id"])) { 
    header("Location: login.php"); 
    exit; 
}

$user_role = $_SESSION["role"] ?? 'Staff';

// --- BACKEND LOGIC: AJAX INLINE ROW UPDATE INTERCEPTOR ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action_ajax_update_expense"])) {
    header('Content-Type: application/json');
    $expense_id = intval($_POST["expense_id"]);
    $new_amount = floatval($_POST["new_amount"]);
    $new_date   = trim($_POST["new_date"]);

    if ($expense_id > 0 && $new_amount > 0 && !empty($new_date)) {
        try {
            $stmt = $pdo->prepare("UPDATE farm_utility_expenses SET amount = ?, expense_date = ? WHERE id = ?");
            $stmt->execute([$new_amount, $new_date, $expense_id]);
            echo json_encode(["status" => "success", "message" => "Expense entry log row updated successfully."]);
        } catch (PDOException $e) {
            echo json_encode(["status" => "error", "message" => "Database synchronization rejection fault."]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid criteria parameters payload supplied."]);
    }
    exit;
}

// --- STANDARD SUBMISSION FORM HANDLER ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action_record_expense"])) {
    $expense_date = $_POST["expense_date"];
    $category     = $_POST["cost_category"];
    $amount       = floatval($_POST["amount"]);
    $payment_mode = $_POST["payment_mode"];
    
    if ($category === "Salaries" && ($user_role !== "Admin" && $user_role !== "Super Admin")) {
        die("Security Exception: Access Denied to unauthorized financial categories.");
    }

    if ($category === "Salaries" && !empty($_POST["selected_staff_name"])) {
        $vendor_name = trim($_POST["selected_staff_name"]);
        $description = "Salary payment for " . $vendor_name;
    } else if ($category === "Other") {
        $vendor_name = trim($_POST["predefined_item_selection"]);
        $more_info = trim($_POST["more_info_notes"] ?? '');
        $description = !empty($more_info) ? $vendor_name . " - " . $more_info : $vendor_name;
    } else {
        $vendor_name = trim($_POST["vendor_name"]);
        $description = trim($_POST["description"]);
    }

    if (!empty($expense_date) && $amount > 0 && !empty($category)) {
        $stmt = $pdo->prepare("INSERT INTO farm_utility_expenses (expense_date, category, description, amount, payment_mode, vendor_name) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$expense_date, $category, $description, $amount, $payment_mode, $vendor_name]);
        
        // ==========================================================================
        // DUAL-CHANNEL NOTIFICATION ROUTER: FILTER SALARIES EXCLUSIVELY
        // ==========================================================================
        if ($category !== 'Salaries' && function_exists('sendAdminTelegramMessage')) {
            $tg_exp = "💸 <b>NEW OPERATIONAL EXPENSE LOGGED</b>\n";
            $tg_exp .= "━━━━━━━━━━━━━━━━━━\n";
            $tg_exp .= "📅 <b>Date:</b> " . $expense_date . "\n";
            $tg_exp .= "🗂️ <b>Category:</b> " . htmlspecialchars($category) . "\n";
            $tg_exp .= "👤 <b>Recipient:</b> " . htmlspecialchars($vendor_name) . "\n";
            $tg_exp .= "📝 <b>Details:</b> " . htmlspecialchars($description) . "\n";
            $tg_exp .= "💳 <b>Method:</b> " . htmlspecialchars($payment_mode) . "\n";
            $tg_exp .= "━━━━━━━━━━━━━━━━━━\n";
            $tg_exp .= "💰 <b>AMOUNT PAID: ₹" . number_format($amount, 2) . "</b>";
            
            sendAdminTelegramMessage($tg_exp);
        }
        // ==========================================================================
        
        $_SESSION['expense_toast'] = "Expense recorded successfully!";
    }
    header("Location: expenses.php");
    exit;
}

$db_staff = $pdo->query("SELECT username FROM users WHERE role != 'Super Admin' ORDER BY username ASC")->fetchAll(PDO::FETCH_COLUMN);
$predefined_items = $pdo->query("SELECT item_name FROM expense_predefined_items ORDER BY item_name ASC")->fetchAll(PDO::FETCH_COLUMN);
$recent_expenses = $pdo->query("SELECT * FROM farm_utility_expenses ORDER BY id DESC LIMIT 15")->fetchAll(PDO::FETCH_ASSOC);

include "includes/header.php";
?>

<style>
.form-input-container { width:100%; padding:10px; border:1px solid #cbd5e0; border-radius:8px; box-sizing:border-box; font-size:14px; font-weight:600; color:#1e293b; background:#fff; }
.form-label-header { font-size:11px; font-weight:700; color:#475569; display:block; margin-bottom:5px; text-transform:uppercase; }
.autocomplete-search-popup { position:absolute; background:white; border:1px solid #cbd5e0; border-radius:8px; width:100%; max-height:180px; overflow-y:auto; box-shadow:0 4px 6px rgba(0,0,0,0.05); z-index:999; margin-top:2px; display:none; }
.autocomplete-suggestion-item { padding:10px; cursor:pointer; font-size:13px; font-weight:600; border-bottom:1px solid #f1f5f9; }
.autocomplete-suggestion-item:hover { background:#f0fdfa; color:#06b6d4; }
.editable-click-cell { cursor: pointer; border-bottom: 1px dashed #06b6d4; padding: 2px 4px; border-radius: 4px; }
.editable-click-cell:hover { background: #ecfeff; color: #0891b2; }
.inline-cell-editor { width: 90px; padding: 4px; font-size: 13px; font-weight: bold; border: 2px solid #06b6d4; border-radius: 4px; }
</style>

<div class="app-body" style="padding: 20px; font-family: sans-serif; text-align: left;">
    
    <?php if (isset($_SESSION['expense_toast'])): ?>
        <div style="max-width:820px; margin:0 auto 15px auto; padding:12px; background:#10b981; color:white; border-radius:6px; font-weight:bold;">📢 <?= htmlspecialchars($_SESSION['expense_toast']); unset($_SESSION['expense_toast']); ?></div>
    <?php endif; ?>

    <div style="max-width: 820px; margin: 0 auto; background: #ffffff; border: 1px solid #cbd5e0; border-radius: 12px; padding: 25px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        <h3 style="margin-top:0; color:#1e293b; border-bottom:1px solid #e2e8f0; padding-bottom:10px; text-transform:uppercase; font-size:15px; letter-spacing:0.5px;">📝 Expenses Workspace</h3>
        
        <form method="POST" action="expenses.php" id="expenseRegistryForm" autocomplete="off">
            <input type="hidden" name="action_record_expense" value="1">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom:15px;">
                <div>
                    <label class="form-label-header">Expense Date</label>
                    <input type="date" name="expense_date" required class="form-input-container" value="<?= date('Y-m-d') ?>">
                </div>
                <div>
                    <label class="form-label-header">Cost Category Group</label>
                    <select name="cost_category" id="costCategoryGroup" onchange="toggleExpenseCategoryView()" required class="form-input-container" style="font-weight:bold;">
                        <?php if ($user_role === 'Admin' || $user_role === 'Super Admin'): ?>
                            <option value="Salaries">Salaries</option>
                        <?php endif; ?>
                        <option value="Bills" selected>Bills</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
            </div>

            <div id="staffSalaryDropdownContainer" style="display:none; margin-bottom:15px; background:#f8fafc; padding:15px; border-radius:8px; border:1px dashed #06b6d4;">
                <label class="form-label-header" style="color:#0891b2;">👤 Select Salary Recipient Member</label>
                <select name="selected_staff_name" id="selectedStaffField" class="form-input-container">
                    <option value="">-- Choose Staff Member getting Paid --</option>
                    <?php if (!empty($db_staff)): foreach ($db_staff as $name): ?>
                        <option value="<?= htmlspecialchars($name) ?>"><?= htmlspecialchars($name) ?></option>
                    <?php endforeach; endif; ?>
                </select>
            </div>

            <div id="otherPredefinedAutocompleteContainer" style="display:none; margin-bottom:15px; background:#f8fafc; padding:15px; border-radius:8px; border:1px dashed #64748b; position:relative;">
                <label class="form-label-header">🔍 Details Descriptions</label>
                <input type="text" id="detailsDescriptionAutocompleteInput" name="predefined_item_selection" placeholder="Type to search items... (e.g., MCB, Petrol)" class="form-input-container" onkeyup="filterPredefinedSuggestions()" onfocus="filterPredefinedSuggestions()">
                <div id="autocompleteSuggestionsMenu" class="autocomplete-search-popup"></div>

                <div id="moreInformationOptionalFieldWrapper" style="display:none; margin-top:15px;">
                    <label class="form-label-header" style="color:#475569;">ℹ More Information (if any)</label>
                    <input type="text" name="more_info_notes" id="moreInfoOptionalField" placeholder="Optional contextual notes..." class="form-input-container">
                </div>
            </div>

            <div id="standardExpenseInputsContainer">
                <div style="margin-bottom:15px;">
                    <label class="form-label-header">Detailed Description</label>
                    <input type="text" name="description" id="expDescInput" required placeholder="e.g., Electricity bill payment, Generator maintenance" class="form-input-container">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom:20px;">
                <div>
                    <label class="form-label-header">Amount (₹)</label>
                    <input type="number" step="0.01" name="amount" required placeholder="0.00" class="form-input-container">
                </div>
                <div>
                    <label class="form-label-header">Payment Mode</label>
                    <select name="payment_mode" class="form-input-container">
                        <option value="Online">Online / UPI / QR</option>
                        <option value="Cash">Cash</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                    </select>
                </div>
                <div id="vendorNameInputWrapper">
                    <label class="form-label-header">Vendor / Recipient</label>
                    <input type="text" name="vendor_name" id="expVendorInput" required placeholder="e.g., JVVNL Electricity" class="form-input-container">
                </div>
            </div>

            <button type="submit" class="btn btn-bill" style="width:100%; padding:12px; font-size:14px; font-weight:bold; background:#14b8a6; border-color:#14b8a6; color:white; border-radius:8px;">Commit & Sync to Workbook</button>
        </form>
    </div>

    <div style="max-width:820px; margin:25px auto 0 auto; background:#fff; border:1px solid #cbd5e0; border-radius:12px; padding:20px;">
        <h4 style="margin-top:0; border-bottom:1px solid #e2e8f0; padding-bottom:8px; text-transform:uppercase; font-size:11px; color:#475569;">Recent Operational Cost Logs (Click Date or Amount to edit inline)</h4>
        <table style="width:100%; border-collapse:collapse; font-size:13px; text-align:left;">
            <thead>
                <tr style="background:#f8fafc; border-bottom:2px solid #cbd5e0;">
                    <th style="padding:10px;">Date</th>
                    <th style="padding:10px;">Category</th>
                    <th style="padding:10px;">Description</th>
                    <th style="padding:10px; text-align:right;">Total</th>
                    <th style="padding:10px; text-align:center;">Mode</th>
                </tr>
            </thead>
            <tbody>
                <?php if(!empty($recent_expenses)): foreach ($recent_expenses as $row): ?>
                    <tr style="border-bottom:1px solid #edf2f7;" id="expense-row-id-<?= $row['id'] ?>">
                        <td style="padding:10px;">
                            <span class="editable-click-cell" id="cell-date-<?= $row['id'] ?>" onclick="openInlineFieldEditor(<?= $row['id'] ?>, 'date', '<?= $row['expense_date'] ?>')"><?= $row['expense_date'] ?></span>
                        </td>
                        <td style="padding:10px;"><span style="font-weight:700; color:#0284c7;"><?= htmlspecialchars($row['category']) ?></span></td>
                        <td style="padding:10px;"><strong><?= htmlspecialchars($row['vendor_name'] ?? 'Other') ?></strong> - <span style="color:#475569; font-size:12px;"><?= htmlspecialchars($row['description']) ?></span></td>
                        <td style="padding:10px; text-align:right; font-weight:800; color:#1e293b;">
                            ₹<span class="editable-click-cell" id="cell-amount-<?= $row['id'] ?>" onclick="openInlineFieldEditor(<?= $row['id'] ?>, 'amount', '<?= $row['amount'] ?>')"><?= number_format($row['amount'], 2, '.', '') ?></span>
                        </td>
                        <td style="padding:10px; text-align:center;"><span style="font-size:11px; font-weight:bold; color:#64748b;"><?= htmlspecialchars($row['payment_mode']) ?></span></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
const datasetPredefinedOptions = <?php echo json_encode($predefined_items); ?>;

function toggleExpenseCategoryView() {
    const activeSelection = document.getElementById("costCategoryGroup").value;
    const blockSalaries    = document.getElementById("staffSalaryDropdownContainer");
    const blockOther       = document.getElementById("otherPredefinedAutocompleteContainer");
    const blockStandard    = document.getElementById("standardExpenseInputsContainer");
    const fieldVendorBlock = document.getElementById("vendorNameInputWrapper");

    const fieldStaff       = document.getElementById("selectedStaffField");
    const fieldAutocomplete= document.getElementById("detailsDescriptionAutocompleteInput");
    const fieldDesc        = document.getElementById("expDescInput");
    const fieldVendor      = document.getElementById("expVendorInput");

    blockSalaries.style.display = "none";
    blockOther.style.display    = "none";
    blockStandard.style.display = "none";
    fieldVendorBlock.style.display = "block";

    fieldStaff.removeAttribute("required");
    fieldAutocomplete.removeAttribute("required");
    fieldDesc.removeAttribute("required");
    fieldVendor.setAttribute("required", "required");

    if (activeSelection === "Salaries") {
        blockSalaries.style.display = "block";
        fieldVendorBlock.style.display = "none";
        fieldStaff.setAttribute("required", "required");
        fieldVendor.removeAttribute("required");
    } else if (activeSelection === "Other") {
        blockOther.style.display = "block";
        fieldVendorBlock.style.display = "none";
        fieldAutocomplete.setAttribute("required", "required");
        fieldVendor.removeAttribute("required");
        setTimeout(() => { fieldAutocomplete.focus(); }, 50);
    } else {
        blockStandard.style.display = "block";
        fieldDesc.setAttribute("required", "required");
    }
}

function filterPredefinedSuggestions() {
    const inputField = document.getElementById("detailsDescriptionAutocompleteInput");
    const filterText = inputField.value.toLowerCase().trim();
    const popupMenu  = document.getElementById("autocompleteSuggestionsMenu");
    const matches = datasetPredefinedOptions.filter(item => item.toLowerCase().includes(filterText));
    
    if (matches.length === 0) {
        popupMenu.innerHTML = '<div style="padding:10px; color:#94a3b8; font-size:12px; font-style:italic;">No matches found.</div>';
    } else {
        popupMenu.innerHTML = matches.map(item => `<div class="autocomplete-suggestion-item" onclick="selectPredefinedItem('${item.replace(/'/g, "\\'")}')">${item}</div>`).join('');
    }
    popupMenu.style.display = "block";
}

function selectPredefinedItem(value) {
    const inputField = document.getElementById("detailsDescriptionAutocompleteInput");
    inputField.value = value;
    document.getElementById("autocompleteSuggestionsMenu").style.display = "none";
    document.getElementById("moreInformationOptionalFieldWrapper").style.display = "block";
    setTimeout(() => { document.getElementById("moreInfoOptionalField").focus(); }, 50);
}

function openInlineFieldEditor(rowId, type, rawValue) {
    const targetCell = document.getElementById(`cell-${type}-${rowId}`);
    if (targetCell.querySelector('input')) return;

    const inputType = (type === 'date') ? 'date' : 'number';
    const extraAttributes = (type === 'amount') ? 'step="0.01" min="0.01"' : '';
    
    targetCell.innerHTML = `<input type="${inputType}" ${extraAttributes} class="inline-cell-editor" id="inline-edit-${type}-${rowId}" value="${rawValue}">`;
    const inputEl = document.getElementById(`inline-edit-${type}-${rowId}`);
    inputEl.focus();

    inputEl.addEventListener('blur', () => saveInlineFieldChange(rowId, type, rawValue));
    inputEl.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') inputEl.blur();
        if (e.key === 'Escape') targetCell.innerText = rawValue;
    });
}

function saveInlineFieldChange(rowId, type, fallbackValue) {
    const targetCell = document.getElementById(`cell-${type}-${rowId}`);
    const inputEl = document.getElementById(`inline-edit-${type}-${rowId}`);
    if (!inputEl) return;

    const finalValue = inputEl.value.trim();
    if (finalValue === "" || finalValue === fallbackValue) {
        targetCell.innerText = type === 'amount' ? parseFloat(fallbackValue).toFixed(2) : fallbackValue;
        return;
    }

    const targetDate = (type === 'date') ? finalValue : document.getElementById(`cell-date-${rowId}`).innerText;
    const targetAmount = (type === 'amount') ? finalValue : document.getElementById(`cell-amount-${rowId}`).innerText;

    const payload = new FormData();
    payload.append('action_ajax_update_expense', '1');
    payload.append('expense_id', rowId);
    payload.append('new_amount', targetAmount);
    payload.append('new_date', targetDate);

    fetch('expenses.php', { method: 'POST', body: payload })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            document.getElementById(`cell-date-${rowId}`).innerText = targetDate;
            document.getElementById(`cell-amount-${rowId}`).innerText = parseFloat(targetAmount).toFixed(2);
            document.getElementById(`cell-date-${rowId}`).onclick = () => openInlineFieldEditor(rowId, 'date', targetDate);
            document.getElementById(`cell-amount-${rowId}`).onclick = () => openInlineFieldEditor(rowId, 'amount', targetAmount);
        } else {
            alert("❌ Update failure: " + data.message);
            location.reload();
        }
    }).catch(() => {
        alert("❌ Loss of connection link network tunnel connectivity.");
        location.reload();
    });
}

document.addEventListener("click", function(e) {
    const inputField = document.getElementById("detailsDescriptionAutocompleteInput");
    const popupMenu  = document.getElementById("autocompleteSuggestionsMenu");
    if (inputField && popupMenu && e.target !== inputField && !popupMenu.contains(e.target)) {
        popupMenu.style.display = "none";
    }
});

document.addEventListener("DOMContentLoaded", toggleExpenseCategoryView);
</script>

<?php include "includes/footer.php"; ?>