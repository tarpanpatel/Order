<?php
ob_start(); // Start output buffering to prevent header errors
error_reporting(E_ALL);
ini_set('display_errors', 0); // Production safe: suppress screen errors

// ⚙️ SYSTEM SESSION LIFESPAN PERSISTENCE CONFIGURATION ENGINE
$sessionPath = __DIR__ . '/_sessions';
if (!is_dir($sessionPath)) { 
    mkdir($sessionPath, 0755, true); 
}

ini_set('session.save_path', $sessionPath);
ini_set('session.gc_maxlifetime', 1209600); // 14 Days persistent storage lifetime[cite: 43]
ini_set('session.cookie_lifetime', 1209600); // Keep session cookie alive in browser[cite: 43]
ini_set('session.use_only_cookies', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "config/db.php";

if (isset($_SESSION["user_id"]) && !isset($_GET['error'])) {
    if (isset($_SESSION["role"]) && $_SESSION["role"] === 'Chef') {
        header("Location: kitchen.php");
    } else {
        header("Location: order.php");
    }
    exit;
}

// 1. FETCH ALL REGISTERED USERS FOR THE SECURE DROPDOWN[cite: 43]
try {
    $db_users = $pdo->query("SELECT id, username, role FROM users ORDER BY username ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Initialization Error.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS Passcode</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #111827; display: flex; justify-content: center; align-items: center; min-height: 100vh; color: #fff; }
        .login-container { background: #1f2937; width: 100%; max-width: 380px; padding: 30px 24px; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.3); border: 1px solid #374151; }
        h2 { text-align: center; margin-bottom: 4px; font-weight: 600; font-size: 22px; color: #f3f4f6; }
        p.subtitle { text-align: center; font-size: 13px; color: #9ca3af; margin-bottom: 20px; }
        .display-wrapper { position: relative; margin-bottom: 20px; }
        .user-dropdown-select { width: 100%; padding: 12px; font-size: 14px; font-weight: 600; border: 2px solid #4b5563; background: #111827; color: #fff; border-radius: 10px; margin-bottom: 14px; outline: none; transition: border-color 0.2s; }
        .user-dropdown-select:focus { border-color: #06b6d4; }
        input[type="password"] { width: 100%; padding: 12px; font-size: 28px; text-align: center; border: 2px solid #4b5563; background: #111827; color: #fff; border-radius: 10px; letter-spacing: 10px; font-family: monospace; outline: none; transition: border-color 0.2s; }
        input[type="password"]:focus { border-color: #06b6d4; }
        .pin-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 10px; }
        .pin-btn { background: #374151; border: 1px solid #4b5563; color: #f3f4f6; padding: 16px; font-size: 20px; font-weight: 600; border-radius: 10px; cursor: pointer; user-select: none; transition: background 0.1s; }
        .pin-btn:active { background: #4b5563; }
        .submit-btn { width: 100%; background: #06b6d4; color: #fff; border: none; padding: 16px; font-size: 16px; font-weight: 600; border-radius: 10px; cursor: pointer; transition: background 0.2s; }
        .submit-btn:hover { background: #0891b2; }
        .error-banner { background: #7f1d1d; border: 1px solid #f87171; color: #fca5a5; padding: 10px; border-radius: 8px; margin-bottom: 16px; font-size: 13px; text-align: center; }
    </style>
</head>
<body>

<div class="login-container">
    <h2>Access Verified Gateway</h2>
    <p class="subtitle">Select your identity name profile context and code</p>
    
    <?php if(!empty($error)): ?>
        <div class="error-banner"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" id="loginForm" action="login.php">
        <select name="user_id" required class="user-dropdown-select">
            <option value="">-- Choose Your Username Profile --</option>
            <?php foreach ($db_users as $u): ?>
                <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['username']) ?> (<?= htmlspecialchars($u['role']) ?>)</option>
            <?php endforeach; ?>
        </select>

        <div class="display-wrapper">
            <input type="password" name="passcode" id="passcodeField" maxlength="4" placeholder="••••" readonly required>
        </div>

        <div class="pin-grid">
            <button type="button" class="pin-btn" onclick="pressNum('1')">1</button>
            <button type="button" class="pin-btn" onclick="pressNum('2')">2</button>
            <button type="button" class="pin-btn" onclick="pressNum('3')">3</button>
            <button type="button" class="pin-btn" onclick="pressNum('4')">4</button>
            <button type="button" class="pin-btn" onclick="pressNum('5')">5</button>
            <button type="button" class="pin-btn" onclick="pressNum('6')">6</button>
            <button type="button" class="pin-btn" onclick="pressNum('7')">7</button>
            <button type="button" class="pin-btn" onclick="pressNum('8')">8</button>
            <button type="button" class="pin-btn" onclick="pressNum('9')">9</button>
            <button type="button" class="pin-btn" onclick="pressNum('C')" style="background:#ef4444; color:white; border-color:#ef4444;">C</button>
            <button type="button" class="pin-btn" onclick="pressNum('0')">0</button>
            <button type="submit" class="submit-btn">Go</button>
        </div>
    </form>
</div>
<script>
    const field = document.getElementById('passcodeField');
    function pressNum(num) {
        if(num === 'C') { field.value = ''; return; }
        if(field.value.length < 4) { field.value += num; }
    }
    
    // Ensure form submits with the value
    document.getElementById('loginForm').addEventListener('submit', function(e) {
        if(field.value.length !== 4) {
            e.preventDefault();
            alert("Please enter a 4-digit passcode.");
        }
    });
</script>
<script>
    const field = document.getElementById('passcodeField');
    function pressNum(num) {
        if(num === 'C') { field.value = ''; return; }
        if(field.value.length < 4) { field.value += num; }
    }
</script>
</body>
</html>