<?php try {
    // Perform Operation
} catch (Exception $e) {
    // AUTOMATIC LOGGING: Record the failure to the DB
    $fail_log = $pdo->prepare("INSERT INTO audit_logs (user_id, action, timestamp) VALUES (?, ?, NOW())");
    $fail_log->execute([$_SESSION['user_id'], "FAILED_ACTION: " . $e->getMessage()]);
    
    // Now the error will show up on your new "System Health" page automatically
}; ?>