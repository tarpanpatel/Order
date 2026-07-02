<?php
require_once "config/db.php";
if (!isset($_SESSION["user_id"])) { header("Location: login.php"); exit; }

// Check if an active group is already checked in
$active_guest = $pdo->query("SELECT * FROM guests WHERE status = 'Active' LIMIT 1")->fetch();

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["start_billing"])) {
    if ($active_guest) {
        $error = "System Constraint: Only ONE active group session can exist at The Artists Farm simultaneously.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO guests (guest_name, phone_number, adults, children, checkin_date, expected_checkout, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Active')");
        $stmt->execute([
            trim($_POST["guest_name"]),
            trim($_POST["phone_number"]),
            intval($_POST["adults"]),
            intval($_POST["children"]),
            $_POST["checkin_date"],
            $_POST["expected_checkout"],
            trim($_POST["notes"])
        ]);
        
        logAction($_SESSION["user_id"], "Initialized billing session for guest group: " . $_POST["guest_name"]);
        header("Location: index.php");
        exit;
    }
}
include "includes/header.php";
?>

<div style="max-width: 600px; margin: 0 auto;">
    <h2>Guest Registration Suite</h2>
    <p style="color: var(--text-muted); margin-bottom: 2rem;">Initialize exclusive property residency tracking</p>

    <?php if (isset($error)): ?>
        <div class="card" style="border-left: 4px solid #D9534F; background: #FDF7F7; color: #D9534F; padding: 1rem;">
            <strong>Registration Blocked:</strong> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($active_guest): ?>
        <div class="card" style="border-top: 4px solid var(--gold-primary);">
            <h3>Current Occupant Active</h3>
            <p style="margin: 1rem 0; color: var(--text-muted);">
                The property is currently occupied by <strong><?= htmlspecialchars($active_guest['guest_name']) ?></strong>.<br>
                You must settle and close their balance sheet via the billing panel before launching a new stay workflow.
            </p>
            <a href="billing.php" class="btn-premium btn-gold">Go to Settlement Billing</a>
        </div>
    <?php else: ?>
        <form method="POST" class="card" style="padding: 2.5rem;" onsubmit="return confirm('Confirm check-in initialization? This opens a running master invoice.');">
            <label style="font-weight: 600; display: block; margin-bottom: 0.5rem;">Primary Guest Name</label>
            <input type="text" name="guest_name" placeholder="Enter full name" required>

            <label style="font-weight: 600; display: block; margin-bottom: 0.5rem;">Contact Number</label>
            <input type="text" name="phone_number" placeholder="Mobile number" required>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                <div>
                    <label style="font-weight: 600; display: block; margin-bottom: 0.5rem;">Adults</label>
                    <input type="number" name="adults" value="1" min="1" required>
                </div>
                <div>
                    <label style="font-weight: 600; display: block; margin-bottom: 0.5rem;">Children</label>
                    <input type="number" name="children" value="0" min="0" required>
                </div>
            </div>

            <label style="font-weight: 600; display: block; margin-bottom: 0.5rem;">Check-In Date & Time</label>
            <input type="datetime-local" name="checkin_date" value="<?= date('Y-m-d\TH:i') ?>" required>

            <label style="font-weight: 600; display: block; margin-bottom: 0.5rem;">Expected Check-Out Date</label>
            <input type="datetime-local" name="expected_checkout" required>

            <label style="font-weight: 600; display: block; margin-bottom: 0.5rem;">Special Instructions / Guest Notes</label>
            <textarea name="notes" rows="4" placeholder="Dietary preferences, allergies, special arrangements..." style="width: 100%; padding: 0.8rem 1rem; border: 1px solid var(--border-color); border-radius: var(--radius-premium); background: var(--bg-light); font-family: inherit; margin-bottom: 1.5rem;"></textarea>

            <button type="submit" name="start_billing" class="btn-premium btn-gold" style="width: 100%; padding: 1.2rem;">Start Stay & Open Billing Ledger</button>
        </form>
    <?php endif; ?>
</div>

<?php include "includes/footer.php"; ?>