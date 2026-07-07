<?php
// /home/apartment/artistsfarmjaipur.com/Order/export_and_backup.php
// WARNING: Ensure there are absolutely no spaces or blank lines above this line!

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/config/db.php';

if (!isset($_SESSION["role"]) || ($_SESSION["role"] !== "Admin" && $_SESSION["role"] !== "Super Admin")) {
    die("Security Error: Unauthorized operation access.");
}

$action = $_GET['action'] ?? '';

// --- CSV EXPORT ---
if ($action === 'export_excel') {
    $tab   = $_GET['tab'] ?? 'overview';
    $month = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));
    $year  = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));
    
    $dateObj = DateTime::createFromFormat('!m', $month);
    $monthName = $dateObj ? $dateObj->format('F') : 'Month';
    
    // Clear out any previous buffering anomalies
    if (ob_get_level()) { ob_end_clean(); }
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="Farm_Report_' . $tab . '_' . $monthName . '_' . $year . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM flag to ensure Excel opens Hindi/Urdu descriptions or names without character breaking
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    if ($tab === 'bookings') {
        fputcsv($output, ['Guest Name', 'Booking Source', 'Phone', 'Guests', 'Check-In', 'Check-Out', 'Room Rent', 'Advance Paid', 'Advance Received By', 'Pending Amount', 'Pending Received By', 'Decoration', 'Tips']);
        $stmt = $pdo->prepare("SELECT * FROM guests WHERE MONTH(checkin_date) = :m AND YEAR(checkin_date) = :y ORDER BY id DESC");
        $stmt->execute([':m' => $month, ':y' => $year]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [
                $row['guest_name'] ?? 'Walking Guest', $row['booking_source'], $row['phone_number'], $row['no_of_guests'],
                $row['checkin_date'], $row['checkout_date'], $row['total_charge'], $row['advance_paid'],
                $row['advance_received_by'], $row['pending_amount'], $row['pending_received_by'],
                $row['decoration_charges'], $row['tip_amount']
            ]);
        }
    } 
    elseif ($tab === 'kitchen_expenses') {
        fputcsv($output, ['Date', 'Category', 'Item Detail', 'Vendor', 'Qty', 'Unit Price', 'Total Cost']);
        $stmt = $pdo->prepare("SELECT *, (qty * price_per_unit) as calculated_total FROM kitchen_expenses WHERE MONTH(date) = :m AND YEAR(date) = :y ORDER BY date DESC");
        $stmt->execute([':m' => $month, ':y' => $year]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $name = $row['item_detail'] ?? $row['item_name'] ?? $row['description'] ?? 'Unnamed';
            fputcsv($output, [$row['date'], $row['category'], $name, $row['vendor_name'] ?? $row['vendor'] ?? 'Market', $row['qty'], $row['price_per_unit'], $row['calculated_total']]);
        }
    } 
    elseif ($tab === 'farm_upkeep') {
        fputcsv($output, ['Date', 'Category', 'Description', 'Vendor', 'Amount']);
        $stmt = $pdo->prepare("
            SELECT date as recorded_date, category, description, vendor_name, amount FROM farm_expenses WHERE MONTH(date) = :m AND YEAR(date) = :y AND category NOT IN ('Salary', 'Salaries')
            UNION ALL
            SELECT expense_date as recorded_date, category, description, vendor_name, amount FROM farm_utility_expenses WHERE MONTH(expense_date) = :m2 AND YEAR(expense_date) = :y2 AND category IN ('Bills', 'Other', 'Utility', 'Water Tanker', 'Maintenance', 'Rations')
            ORDER BY recorded_date DESC
        ");
        $stmt->execute([':m' => $month, ':y' => $year, ':m2' => $month, ':y2' => $year]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [$row['recorded_date'], $row['category'], $row['description'], $row['vendor_name'] ?? 'Other', $row['amount']]);
        }
    } 
    elseif ($tab === 'salaries') {
        fputcsv($output, ['Date', 'Category', 'Description', 'Employee Name', 'Amount Paid']);
        $stmt = $pdo->prepare("
            SELECT date as recorded_date, category, description, vendor_name, amount FROM farm_expenses WHERE MONTH(date) = :m AND YEAR(date) = :y AND category IN ('Salary', 'Salaries')
            UNION ALL
            SELECT expense_date as recorded_date, category, description, vendor_name, amount FROM farm_utility_expenses WHERE MONTH(expense_date) = :m2 AND YEAR(expense_date) = :y2 AND category = 'Salaries'
            ORDER BY recorded_date DESC
        ");
        $stmt->execute([':m' => $month, ':y' => $year, ':m2' => $month, ':y2' => $year]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [$row['recorded_date'], 'Salaries', $row['description'], $row['vendor_name'] ?? 'Teammate', $row['amount']]);
        }
    } 
    
    fclose($output);
    exit;
}

// --- SQL SYSTEM DUMP BACKUP ---
if ($action === 'backup_db') {
    if (ob_get_level()) { ob_end_clean(); }
    
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="Backup_Apartment_Blue_' . date('Y-m-d_H-i-s') . '.sql"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo "-- ======================================================\n";
    echo "-- AUTOMATED BACKUP DISPATCH FOR APARTMENT_BLUE DATABASE\n";
    echo "-- Generated At: " . date('Y-m-d H:i:s') . "\n";
    echo "-- ======================================================\n\n";
    
    $tables = ['guests', 'farm_bookings', 'farm_expenses', 'farm_utility_expenses', 'kitchen_expenses', 'requisitions', 'requisition_items', 'req_catalog', 'users'];
    
    foreach ($tables as $table) {
        try {
            $res = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
            echo "DROP TABLE IF EXISTS `$table`;\n" . $res[1] . ";\n\n";
            
            $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($rows)) {
                foreach ($rows as $row) {
                    $keys = array_map(function($k) { return "`$k`"; }, array_keys($row));
                    $values = array_map(function($v) use ($pdo) {
                        return ($v === null) ? 'NULL' : $pdo->quote($v);
                    }, array_values($row));
                    
                    echo "INSERT INTO `$table` (" . implode(', ', $keys) . ") VALUES (" . implode(', ', $values) . ");\n";
                }
                echo "\n";
            }
        } catch (Exception $e) {
            echo "-- Error generating backup entry for table $table: " . $e->getMessage() . "\n";
        }
    }
    exit;
}