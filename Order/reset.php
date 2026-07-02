<?php
require_once 'config/db.php';

// Generate a clean native hash for 'password123'
$new_password = password_hash('password123', PASSWORD_BCRYPT);

try {
    // Force reset the admin user account
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = 'admin'");
    $stmt->execute([$new_password]);
    
    echo "<h3>Success!</h3>";
    echo "The admin password has been cleanly reset by the server engine.<br>";
    echo "Please delete <strong>reset.php</strong> immediately for security and try logging in again.";
} catch (Exception $e) {
    echo "Error updating database: " . $e->getMessage();
}
?>