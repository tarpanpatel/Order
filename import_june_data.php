<?php
// /home/apartment/artistsfarmjaipur.com/Order/import_june_data.php

header('Content-Type: text/plain');
include_once __DIR__ . '/config/db.php';

echo "=== STARTING HISTORICAL DATA MIGRATION FOR JUNE ===\n\n";

// Helper function to cleanly parse currency/number strings from Excel cells
function cleanAmount($val) {
    return floatval(str_replace([',', ' ', '₹'], '', $val));
}

// ==========================================
// 1. IMPORT FARM EXPENSES
// ==========================================
$farmCsvFile = __DIR__ . '/june_farm_expenses.csv';

if (file_exists($farmCsvFile)) {
    echo "Processing Farm Expenses CSV...\n";
    $handle = fopen($farmCsvFile, 'r');
    
    $rowCount = 0;
    $insertedCount = 0;
    $currentLastDate = '2026-06-01'; // Default backup date

    // Prepare SQL Statement
    $sql = "INSERT INTO farm_expenses (date, description, amount, payment_mode, vendor_name, category, remark) 
            VALUES (:date, :description, :amount, :payment_mode, :vendor_name, 'Other', :remark)";
    $stmt = $pdo->prepare($sql);

    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        $rowCount++;
        
        // Skip header structures, titles, and blank summary blocks from rows 1-3
        if ($rowCount <= 3 || empty($data[2])) {
            continue; 
        }

        // Handle date carrying logic for blank cells
        if (!empty($data[1])) {
            // Convert Excel date text format cleanly
            $currentLastDate = date('Y-m-d', strtotime($data[1]));
        }

        $description = trim($data[2]);
        $amount      = cleanAmount($data[3]);
        $paymentMode = !empty($data[4]) ? trim($data[4]) : 'online';
        $vendor      = !empty($data[5]) ? trim($data[5]) : null;
        $remark      = !empty($data[6]) ? trim($data[6]) : null;

        $stmt->execute([
            ':date'         => $currentLastDate,
            ':description'  => $description,
            ':amount'       => $amount,
            ':payment_mode' => $paymentMode,
            ':vendor_name'  => $vendor,
            ':remark'       => $remark
        ]);
        $insertedCount++;
    }
    fclose($handle);
    echo "Successfully imported {$insertedCount} Farm Expense entries.\n\n";
} else {
    echo "Warning: june_farm_expenses.csv not found in Order directory. Skipping.\n\n";
}


// ==========================================
// 2. IMPORT KITCHEN EXPENSES
// ==========================================
$kitchenCsvFile = __DIR__ . '/june_kitchen_expenses.csv';

if (file_exists($kitchenCsvFile)) {
    echo "Processing Kitchen Expenses CSV...\n";
    $handle = fopen($kitchenCsvFile, 'r');
    
    $rowCount = 0;
    $insertedCount = 0;
    $currentLastDate = '2026-06-01'; // Default backup date

    $sql = "INSERT INTO kitchen_expenses (date, category, description, qty, unit, price_per_unit, vendor_name) 
            VALUES (:date, :category, :description, :qty, :unit, :price_per_unit, :vendor_name)";
    $stmt = $pdo->prepare($sql);

    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        $rowCount++;
        
        // Skip headings and empty validation metrics rows 1-3
        if ($rowCount <= 3 || empty($data[3])) {
            continue;
        }

        // Stop processing early if we hit summary text at the bottom
        if (trim($data[0]) === 'Total' || trim($data[1]) === 'Total') {
            break;
        }

        // Handle date carrying logic for blank rows
        if (!empty($data[1])) {
            $currentLastDate = date('Y-m-d', strtotime($data[1]));
        }

        $category     = !empty($data[2]) ? trim($data[2]) : 'General';
        $description  = trim($data[3]);
        $qty          = floatval($data[4]);
        $unit         = !empty($data[5]) ? trim($data[5]) : 'pcs';
        $pricePerUnit = cleanAmount($data[6]);
        $vendor       = !empty($data[9]) ? trim($data[9]) : 'Other';

        $stmt->execute([
            ':date'           => $currentLastDate,
            ':category'       => $category,
            ':description'    => $description,
            ':qty'            => $qty,
            ':unit'           => $unit,
            ':price_per_unit' => $pricePerUnit,
            ':vendor_name'    => $vendor
        ]);
        $insertedCount++;
    }
    fclose($handle);
    echo "Successfully imported {$insertedCount} Kitchen Expense entries.\n\n";
} else {
    echo "Warning: june_kitchen_expenses.csv not found in Order directory. Skipping.\n\n";
}

echo "=== MIGRATION COMPLETE ===";