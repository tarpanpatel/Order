<?php
// /home/apartment/artistsfarmjaipur.com/Order/expense_items_management.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once "config/db.php";
 

// --- MASTER CONTROLLER: AJAX INLINE UPDATE INTERCEPTOR ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action_ajax_update_item"])) {
    header('Content-Type: application/json');
    $item_id   = intval($_POST["item_id"]);
    $new_value = trim($_POST["new_value"]);

    if ($item_id > 0 && !empty($new_value)) {
        try {
            $stmt = $pdo->prepare("UPDATE expense_predefined_items SET item_name = ? WHERE id = ?");
            $stmt->execute([$new_value, $item_id]);
            echo json_encode(["status" => "success", "message" => "Item updated successfully."]);
        } catch (PDOException $e) {
            echo json_encode(["status" => "error", "message" => "Duplicate or invalid name entry."]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid parameters supplied."]);
    }
    exit;
}

$message = "";

// Handle item addition
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action_add_item"])) {
    $item_name = trim($_POST["item_name"]);
    if (!empty($item_name)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO expense_predefined_items (item_name) VALUES (?)");
            $stmt->execute([$item_name]);
            $message = "✔ Predefined item added successfully!";
        } catch (PDOException $e) {
            $message = "❌ Error: Item already exists.";
        }
    }
}

// Handle item deletion
if (isset($_GET["delete_id"])) {
    $del_id = intval($_GET["delete_id"]);
    $stmt = $pdo->prepare("DELETE FROM expense_predefined_items WHERE id = ?");
    $stmt->execute([$del_id]);
    header("Location: expense_items_management.php");
    exit;
}

$predefined_items = $pdo->query("SELECT * FROM expense_predefined_items ORDER BY item_name ASC")->fetchAll(PDO::FETCH_ASSOC);

include "includes/header.php";
?>

<style>
.item-text-label { cursor: pointer; font-weight: 600; color: #1e293b; padding: 4px 8px; border-radius: 4px; border: 1px solid transparent; width: 80%; display: inline-block; transition: all 0.2s ease; }
.item-text-label:hover { background: #f0fdfa; border-color: #a5f3fc; color: #0891b2; }
.inline-edit-input { width: 80%; padding: 4px 8px; border: 2px solid #06b6d4; border-radius: 6px; font-size: 13px; font-weight: 600; color: #1e293b; outline: none; background: #fff; }
.toast-alert-badge { position: fixed; bottom: 20px; right: 20px; padding: 12px 24px; color: #fff; border-radius: 8px; font-size: 14px; font-weight: 700; box-shadow: 0 4px 12px rgba(0,0,0,0.1); z-index: 99999; display: none; }
.toast-success { background: #10b981; }
.toast-error { background: #ef4444; }
</style>

<div class="app-body" style="padding: 20px; font-family: sans-serif; text-align: left;">
    <h2>⚙ Predefined Expense Items Workspace</h2>
    <p style="color:#64748b; margin-top:-10px; margin-bottom:20px;">Click directly on any entry text link below to edit its name inline instantly.</p>

    <?php if(!empty($message)): ?>
        <div style="background:#f0fdf4; border:1px solid #bbf7d0; color:#166534; padding:12px; border-radius:8px; margin-bottom:20px; font-size:14px; font-weight:bold;"><?= $message ?></div>
    <?php endif; ?>

    <div style="display:grid; grid-template-columns: 1fr 2fr; gap:30px; align-items:start;">
        <div style="background:#fff; border:1px solid #cbd5e0; border-radius:12px; padding:20px;">
            <h4 style="margin-top:0; border-bottom:1px dashed #cbd5e0; padding-bottom:8px; text-transform:uppercase; font-size:11px; color:#475569;">➕ Add Description Entry</h4>
            <form method="POST" action="expense_items_management.php">
                <input type="hidden" name="action_add_item" value="1">
                <div style="margin-bottom:15px;">
                    <label style="font-size:11px; font-weight:700; display:block; margin-bottom:4px; color:#475569;">Item Description Name</label>
                    <input type="text" name="item_name" required placeholder="e.g., Garbage, Cab Rent" style="width:100%; padding:10px; border:1px solid #cbd5e0; border-radius:6px; box-sizing:border-box;">
                </div>
                <button type="submit" class="btn btn-bill" style="width:100%; padding:10px; font-weight:bold; background:#06b6d4; border-color:#06b6d4; color:white; border-radius:6px;">Add To Registry</button>
            </form>
        </div>

        <div style="background:#fff; border:1px solid #cbd5e0; border-radius:12px; padding:20px;">
            <h4 style="margin-top:0; border-bottom:1px solid #e2e8f0; padding-bottom:8px; text-transform:uppercase; font-size:11px; color:#475569;">📋 Current Sub-category Entries List</h4>
            <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap:10px; max-height:450px; overflow-y:auto; padding-right:5px;">
                <?php if(!empty($predefined_items)): foreach ($predefined_items as $item): ?>
                    <div style="display:flex; justify-content:space-between; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:8px 12px; font-size:13px; align-items:center;" id="item-wrapper-row-<?= $item['id'] ?>">
                        
                        <span class="item-text-label" id="label-text-<?= $item['id'] ?>" onclick="initializeInlineEditRow(<?= $item['id'] ?>)"><?= htmlspecialchars($item['item_name']) ?></span>
                        
                        <a href="expense_items_management.php?delete_id=<?= $item['id'] ?>" onclick="return confirm('Remove this predefined item?')" style="color:#ef4444; font-weight:bold; text-decoration:none; font-size:12px; padding-left:5px;">✕</a>
                    </div>
                <?php endforeach; else: ?>
                    <div style="grid-column: span 2; text-align:center; padding:20px; color:#94a3b8; font-style:italic;">No predefined categories initialized.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div id="runtimeFeedbackToast" class="toast-alert-badge"></div>

<script>
function initializeInlineEditRow(itemId) {
    const labelSpan = document.getElementById(`label-text-${itemId}`);
    const originalValue = labelSpan.innerText.trim();

    // Prevent multiple input element initializations on double clicks
    if (labelSpan.querySelector('input')) return;

    // Inject temporary interactive input field container
    labelSpan.innerHTML = `<input type="text" class="inline-edit-input" id="input-field-${itemId}" value="${originalValue.replace(/"/g, '&quot;')}">`;
    
    const inputElement = document.getElementById(`input-field-${itemId}`);
    inputElement.focus();
    inputElement.select();

    // Hook blur trigger listener context matrix
    inputElement.addEventListener('blur', () => {
        commitInlineItemValueUpdate(itemId, originalValue);
    });

    // Hook keyboard enter button submit tracking lines
    inputElement.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            inputElement.blur(); // Triggers blur listener logic naturally
        }
        if (e.key === 'Escape') {
            e.preventDefault();
            labelSpan.innerText = originalValue; // Rollback instantly
        }
    });
}

function commitInlineItemValueUpdate(itemId, fallbackValue) {
    const labelSpan = document.getElementById(`label-text-${itemId}`);
    const inputField = document.getElementById(`input-field-${itemId}`);
    
    if (!inputField) return;

    const updatedValue = inputField.value.trim();

    // Cancel operation if no change was made
    if (updatedValue === fallbackValue) {
        labelSpan.innerText = fallbackValue;
        return;
    }

    if (updatedValue === "") {
        triggerToastNotification("Item description cannot be left empty.", "error");
        labelSpan.innerText = fallbackValue;
        return;
    }

    // Build Form Request Payload Matrix
    const postPayload = new FormData();
    postPayload.append('action_ajax_update_item', '1');
    postPayload.append('item_id', itemId);
    postPayload.append('new_value', updatedValue);

    // Asynchronous background database save operations handler pipeline
    fetch('expense_items_management.php', {
        method: 'POST',
        body: postPayload
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            labelSpan.innerText = updatedValue;
            triggerToastNotification("✔ Description updated successfully.", "success");
        } else {
            labelSpan.innerText = fallbackValue;
            triggerToastNotification("❌ " + data.message, "error");
        }
    })
    .catch(error => {
        labelSpan.innerText = fallbackValue;
        triggerToastNotification("❌ Server connection lost. Update failed.", "error");
    });
}

function triggerToastNotification(message, statusType) {
    const toast = document.getElementById("runtimeFeedbackToast");
    toast.innerText = message;
    toast.className = `toast-alert-badge ${statusType === 'success' ? 'toast-success' : 'toast-error'}`;
    toast.style.display = "block";
    
    setTimeout(() => {
        toast.style.display = "none";
    }, 3000);
}
</script>

<?php include "includes/footer.php"; ?>