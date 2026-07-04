<?php
// ==========================================================================
// TEMPORARY DEVELOPMENT DEBUGGING ENGINE
// ==========================================================================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
 
// Force the application layer to use Indian Standard Time
date_default_timezone_set('Asia/Kolkata');
// Your existing PDO database connection code continues below...
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

$db_host = "localhost";  
$db_user = "apartment_blue";  
$db_pass = "tPatel13@";  
$db_name = "apartment_blue"; 

try {
    $pdo = new PDO("mysql:host=localhost;dbname=apartment_blue;charset=utf8mb4", "username", "password", [
        PDO::ATTR_ERRMODE => PDO::ATTR_ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    // CRITICAL: Force the MySQL connection session to run on IST (+05:30)
    $pdo->exec("SET time_zone = '+05:30';");

} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
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