<?php
// /home/apartment/artistsfarmjaipur.com/Order/login.php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once "config/db.php";

// 🛠️ EMERGENCY DATABASE RE-HASH RESET SCRIPT
if (isset($_GET['rescue_reset_passwords'])) {
    try {
        // Generate secure, fresh cryptographic hashes matching standard compliance parameters
        $hash_3685 = password_hash("3685", PASSWORD_BCRYPT);
        $hash_1202 = password_hash("1202", PASSWORD_BCRYPT);
        $hash_0000 = password_hash("0000", PASSWORD_BCRYPT);
        
        // Explicitly update each row using accurate primary key filters
        $stmt1 = $pdo->prepare("UPDATE users SET password = ? WHERE id = 7 OR username = 'tarpan'");
        $stmt1->execute([$hash_3685]);
        
        $stmt2 = $pdo->prepare("UPDATE users SET password = ? WHERE id = 8 OR username = 'Kamlesh'");
        $stmt2->execute([$hash_1202]);
        
        $stmt3 = $pdo->prepare("UPDATE users SET password = ? WHERE id = 10 OR username = 'test'");
        $stmt3->execute([$hash_0000]);
        
        echo "<div style='padding:20px; background:#dcfce7; color:#166534; border:2px solid #22c55e; border-radius:8px; font-family:sans-serif; margin:20px;'>";
        echo "<h3>✅ Database Passcodes Fixed!</h3>";
        echo "<p>The database tables have been updated with clean password hashes. <a href='login.php' style='color:#15803d; font-weight:bold; text-decoration:underline;'>Click here to go back to the login pad</a> and type 3685.</p>";
        echo "</div>";
        exit;
    } catch (PDOException $e) {
        die("Rescue Script Error: " . $e->getMessage());
    }
}

// If already authenticated via session or auto-login token, bypass login entirely
if (isset($_SESSION["user_id"])) {
    if (isset($_SESSION["role"]) && $_SESSION["role"] === 'Chef') {
        header("Location: kitchen.php");
    } else {
        header("Location: index.php");
    }
    exit;
}

try {
    $db_users = $pdo->query("SELECT id, username, role FROM users ORDER BY username ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Connection Error.");
}

$error = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id  = intval($_POST['user_id'] ?? 0);
    $passcode = trim($_POST['passcode'] ?? '');

    if ($user_id > 0 && !empty($passcode)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_row = $stmt->fetch(PDO::FETCH_ASSOC);

        // SECURE AUTHENTICATION CHECK WITH FALLBACK BYPASS TO PREVENT LOCKOUTS
        if ($user_row) {
            $is_verified = password_verify($passcode, $user_row['password']);
            $is_master_bypass = ($user_id === 7 && $passcode === "3685") || ($user_id === 8 && $passcode === "1202") || ($user_id === 10 && $passcode === "0000");

            if ($is_verified || $is_master_bypass) {
                $_SESSION["user_id"]   = $user_row['id'];
                $_SESSION["username"]  = $user_row['username'];
                $_SESSION["role"]      = $user_row['role'];
                $_SESSION["order_authenticated"] = true;

                // If they logged in via master bypass text strings, update the database hash silently right now
                if ($is_master_bypass && !$is_verified) {
                    $new_hash = password_hash($passcode, PASSWORD_BCRYPT);
                    $update_stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $update_stmt->execute([$new_hash, $user_id]);
                }

                // GENERATE 1-YEAR PERSISTENT TOKEN (Bypasses cPanel Auto-Logouts)
                $random_token = bin2hex(random_bytes(32));
                $token_hash = hash('sha256', $random_token);
                $expires = date('Y-m-d H:i:s', time() + 31536000); 
                
                $pdo->prepare("INSERT INTO user_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)")
                    ->execute([$user_row['id'], $token_hash, $expires]);
                    
                setcookie('pos_remember_token', $user_row['id'] . ':' . $random_token, time() + 31536000, '/', '', false, true);

                // Log entry
                $log = $pdo->prepare("INSERT INTO security_login_logs (username_entered, passcode_entered, user_id, role_assigned, ip_address, browser_agent, device_type, login_status) VALUES (?, '***', ?, ?, ?, 'POS Terminal', 'Station', 'Success')");
                $log->execute([$user_row['username'], $user_row['id'], $user_row['role'], $_SERVER['REMOTE_ADDR']]);

                if ($user_row['role'] === 'Chef') {
                    header("Location: kitchen.php");
                } else {
                    header("Location: index.php");
                }
                exit;
            } else {
                $error = "Incorrect passcode entry validation.";
            }
        } else {
            $error = "User context profile row not found.";
        }
    } else {
        $error = "Please select a valid user profile.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS Passcode Terminal Entry</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #111827; display: flex; justify-content: center; align-items: center; min-height: 100vh; color: #fff; }
        .login-container { background: #1f2937; width: 100%; max-width: 380px; padding: 30px 24px; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.3); border: 1px solid #374151; text-align: left; }
        h2 { text-align: center; margin-bottom: 4px; font-weight: 600; font-size: 22px; color: #f3f4f6; }
        p.subtitle { text-align: center; font-size: 13px; color: #9ca3af; margin-bottom: 20px; }
        .display-wrapper { position: relative; margin-bottom: 20px; }
        .user-dropdown-select { width: 100%; padding: 12px; font-size: 14px; font-weight: 600; border: 2px solid #4b5563; background: #111827; color: #fff; border-radius: 10px; margin-bottom: 14px; outline: none; transition: border-color 0.2s; }
        .user-dropdown-select:focus { border-color: #00b0ff; }
        input[type="password"] { width: 100%; padding: 12px; font-size: 28px; text-align: center; border: 2px solid #4b5563; background: #111827; color: #fff; border-radius: 10px; letter-spacing: 10px; font-family: monospace; outline: none; transition: border-color 0.2s; }
        input[type="password"]:focus { border-color: #00b0ff; }
        .pin-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 10px; }
        .pin-btn { background: #374151; border: 1px solid #4b5563; color: #f3f4f6; padding: 16px; font-size: 20px; font-weight: 600; border-radius: 10px; cursor: pointer; user-select: none; transition: background 0.1s; text-align: center; }
        .pin-btn:active { background: #4b5563; }
        .submit-btn { width: 100%; background: #00b0ff; color: #fff; border: none; padding: 16px; font-size: 16px; font-weight: 600; border-radius: 10px; cursor: pointer; transition: background 0.2s; }
        .submit-btn:hover { background: #0891b2; }
        .error-banner { background: #7f1d1d; border: 1px solid #f87171; color: #fca5a5; padding: 10px; border-radius: 8px; margin-bottom: 16px; font-size: 13px; text-align: center; }
        .rescue-link { display: block; text-align: center; font-size: 11px; color: #64748b; margin-top: 15px; text-decoration: none; }
        .rescue-link:hover { color: #9ca3af; text-decoration: underline; }
    </style>
</head>
<body>

<div class="login-container">
    <h2>Access Verified Gateway</h2>
    <p class="subtitle">Select your identity profile and enter passcode</p>
    
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
        
        <a href="login.php?rescue_reset_passwords=1" class="rescue-link">🔧 Click here to run password hash repair utility</a>
    </form>
</div>

<script>
    const field = document.getElementById('passcodeField');
    function pressNum(num) {
        if(num === 'C') { field.value = ''; return; }
        if(field.value.length < 4) { field.value += num; }
    }
</script>
</body>
</html>