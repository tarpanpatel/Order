<?php
session_start();
require_once "config/db.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $passcode = trim($_POST['passcode'] ?? '');

    if ($passcode === "3685") {
        $_SESSION["user_id"] = "admin";
        $_SESSION["username"] = "Super Admin";
        $_SESSION["role"] = "Admin";
        $_SESSION["order_authenticated"] = true;
        header("Location: order.php");
        exit;
    } elseif ($passcode === "1202") {
        $_SESSION["user_id"] = "chef";
        $_SESSION["username"] = "Chef Terminal";
        $_SESSION["role"] = "Chef";
        $_SESSION["order_authenticated"] = true;
        header("Location: kitchen.php");
        exit;
    } else {
        $error = "Incorrect passcode verification profile instance.";
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
        .login-container { background: #1f2937; width: 100%; max-width: 380px; padding: 40px 30px; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.3); border: 1px solid #374151; }
        h2 { text-align: center; margin-bottom: 8px; font-weight: 600; font-size: 24px; color: #f3f4f6; }
        p.subtitle { text-align: center; font-size: 14px; color: #9ca3af; margin-bottom: 24px; }
        .display-wrapper { position: relative; margin-bottom: 24px; }
        input[type="password"] { width: 100%; padding: 15px; font-size: 32px; text-align: center; border: 2px solid #4b5563; background: #111827; color: #fff; border-radius: 10px; letter-spacing: 12px; font-family: monospace; outline: none; transition: border-color 0.2s; }
        input[type="password"]:focus { border-color: #06b6d4; }
        .pin-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 20px; }
        .pin-btn { background: #374151; border: 1px solid #4b5563; color: #f3f4f6; padding: 18px; font-size: 22px; font-weight: 600; border-radius: 10px; cursor: pointer; user-select: none; transition: background 0.1s; }
        .pin-btn:active { background: #4b5563; }
        .pin-btn.clear { background: #ef4444; border-color: #f87171; color: #fff; }
        .pin-btn.clear:active { background: #dc2626; }
        .submit-btn { width: 100%; background: #06b6d4; color: #fff; border: none; padding: 16px; font-size: 18px; font-weight: 600; border-radius: 10px; cursor: pointer; transition: background 0.2s; margin-top: 10px; }
        .submit-btn:hover { background: #0891b2; }
        .error-banner { background: #7f1d1d; border: 1px solid #f87171; color: #fca5a5; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; text-align: center; }
    </style>
</head>
<body>

<div class="login-container">
    <h2>Access Verified Gateway</h2>
    <p class="subtitle">Enter your assigned 4-digit security code</p>
    
    <?php if(!empty($error)): ?>
        <div class="error-banner"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" id="loginForm" action="login.php">
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
            <button type="button" class="pin-btn clear" onclick="clearPin()">C</button>
            <button type="button" class="pin-btn" onclick="pressNum('0')">0</button>
            <button type="submit" class="submit-btn" style="margin-top:0; padding:18px; font-size:16px;">Go</button>
        </div>
    </form>
</div>

<script>
    const field = document.getElementById('passcodeField');
    function pressNum(num) {
        if(field.value.length < 4) {
            field.value += num;
        }
    }
    function clearPin() {
        field.value = '';
    }
</script>
</body>
</html>