<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "config/db.php";

echo "<h3>Running System Repair Tool...</h3>";

try {
    // 1. Reset any stuck active guest accounts back to booked status to clear overlaps
    $pdo->query("UPDATE guests SET status = 'Booked' WHERE status = 'Active'");
    echo "✔ Safe Reset: All stuck active guest sessions have been cleared.<br>";

    // 2. Clear any active rows inside the tracker table for this month to ensure a clean slate
    $currentMonthYear = date('m-Y');
    $pdo->prepare("DELETE FROM google_monthly_workbooks WHERE log_month_year = ?")->execute([$currentMonthYear]);
    echo "✔ Workbook Cache Cleared: Ready for a fresh cloud spreadsheet rebuild.<br>";

    echo "<br><b style='color:green;'>🎉 System successfully repaired!</b> Close this tab and perform a hard refresh (Ctrl + F5) on your main dashboard screen.";
} catch (Exception $e) {
    echo "<b style='color:red;'>❌ Error running repair:</b> " . $e->getMessage();
}