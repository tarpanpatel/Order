<?php
// /home/apartment/artistsfarmjaipur.com/Order/login.php
session_start();
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0); // Security: Errors hidden

if (session_status() === PHP_SESSION_NONE) {
    $sessionPath = __DIR__ . '/_sessions';
    if (!is_dir($sessionPath)) { mkdir($sessionPath, 0755, true); }
    ini_set('session.save_path', $sessionPath);
    session_start();
}

require_once "config/db.php";

try {
    $db_users = $pdo->query("SELECT id, username, role FROM users ORDER BY username ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Initialization Error.");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id  = intval($_POST['user_id'] ?? 0);
    $passcode = trim($_POST['passcode'] ?? '');

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($passcode, $user['password'])) {
        $_SESSION["user_id"]  = $user['id'];
        $_SESSION["username"] = $user['username'];
        $_SESSION["role"]     = $user['role'];
        
        $log = $pdo->prepare("INSERT INTO security_login_logs (username_entered, role_assigned, ip_address, login_status) VALUES (?, ?, ?, 'Success')");
        $log->execute([$user['username'], $user['role'], $_SERVER['REMOTE_ADDR']]);
        
        header("Location: order.php");
        exit;
    } else {
        $error = "Invalid passcode.";
    }
}
?>
<!DOCTYPE html><html><head><link rel="stylesheet" href="style.css"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body>
<div class="login-container">
    <form method="POST" id="loginForm" class="card">
        <h3>Staff Access</h3>
        <select name="user_id" required><option value="">Select User</option><?php foreach($db_users as $u): ?><option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['username']) ?></option><?php endforeach; ?></select>
        <input type="password" name="passcode" id="passcodeField" placeholder="••••" required readonly>
        <div class="pin-pad">
            <button type="button" class="pin-btn" onclick="pressNum('1')">1</button>
            <button type="button" class="pin-btn" onclick="pressNum('2')">2</button>
            <button type="button" class="pin-btn" onclick="pressNum('3')">3</button>
            <button type="button" class="pin-btn" onclick="pressNum('4')">4</button>
            <button type="button" class="pin-btn" onclick="pressNum('5')">5</button>
            <button type="button" class="pin-btn" onclick="pressNum('6')">6</button>
            <button type="button" class="pin-btn" onclick="pressNum('7')">7</button>
            <button type="button" class="pin-btn" onclick="pressNum('8')">8</button>
            <button type="button" class="pin-btn" onclick="pressNum('9')">9</button>
            <button type="button" class="pin-btn" onclick="pressNum('C')">C</button>
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
</script>
</body></html>