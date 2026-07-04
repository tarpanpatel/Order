<?php
// ==========================================================================
// TEMPORARY DEVELOPMENT DEBUGGING ENGINE
// ==========================================================================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Your existing PDO database connection code continues below...
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

 
// /home/apartment/artistsfarmjaipur.com/Order/config/db.php
date_default_timezone_set('Asia/Kolkata'); //

if (session_status() === PHP_SESSION_NONE) { session_start(); } //

$db_host = "localhost";   //
$db_user = "apartment_blue";   //
$db_pass = "tPatel13@";   //
$db_name = "apartment_blue";  //

try {
    // FIXED: Swapped out placeholder strings for your real login variable strings
    $pdo = new PDO("mysql:host={$db_host};dbname={$db_name};charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, //
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC //
    ]);
    
    // Force MySQL session synchronization to Indian Standard Time
    $pdo->exec("SET time_zone = '+05:30';"); //

} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage()); //
}

function logAction($userId, $actionText) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?, ?)");
    $stmt->execute([$userId, $actionText]);
}

// Security Check: Restrict access to Super Admins only
function requireSuperAdmin() {
    if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "Super Admin") {
        header("Location: index.php?error=unauthorized");
        exit;
    }
}
?>