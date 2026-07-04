<?php
// /home/apartment/artistsfarmjaipur.com/Order/config/local_db_bridge.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * LOCAL TRANSACTION LOGGER
 * Routes financial logs directly to the local MySQL database instead of Google Sheets.
 *
 * @param string $type ('farm_booking', 'kitchen_expense', or 'farm_expense')
 * @param array $rowData The array of data sent from your forms
 * @return bool Success or failure status
 */
function appendRowToGoogleSheet($type, array $rowData) {
    global $pdo; // Uses your website's pre-existing database connection object

    // If the database connection isn't loaded yet, try to include it dynamically
    if (!isset($pdo)) {
        $dbPath = __DIR__ . '/db.php'; // Adjust if your database connection file has a different name
        if (file_exists($dbPath)) {
            include_once $dbPath;
        } else {
            error_log("Local DB Bridge Error: Database connection (\$pdo) is missing.");
            return false;
        }
    }

    try {
        switch ($type) {
            
            // 1. FARM BOOKINGS & FOOD LEDGER
            case 'Farm booking and food':
            case 'farm_booking':
                $sql = "INSERT INTO farm_bookings (
                            booking_source, contact_no, no_of_guests, check_in_date, check_out_date, 
                            per_night_charges, remarks, advance_paid, advance_received_by, 
                            pending_amount, pending_received_by, total_food_bill, food_received_by, 
                            decoration_charges, tip_amount
                        ) VALUES (
                            :booking_source, :contact_no, :no_of_guests, :check_in_date, :check_out_date, 
                            :per_night_charges, :remarks, :advance_paid, :advance_received_by, 
                            :pending_amount, :pending_received_by, :total_food_bill, :food_received_by, 
                            :decoration_charges, :tip_amount
                        )";
                
                $stmt = $pdo->prepare($sql);
                return $stmt->execute([
                    ':booking_source'      => $rowData[1] ?? 'Offline',
                    ':contact_no'          => $rowData[2] ?? null,
                    ':no_of_guests'        => !empty($rowData[3]) ? intval($rowData[3]) : 1,
                    ':check_in_date'       => $rowData[4] ?? date('Y-m-d'),
                    ':check_out_date'      => $rowData[5] ?? date('Y-m-d'),
                    ':per_night_charges'   => !empty($rowData[7]) ? floatval($rowData[7]) : 0.00,
                    ':remarks'             => $rowData[9] ?? null,
                    ':advance_paid'        => !empty($rowData[10]) ? floatval($rowData[10]) : 0.00,
                    ':advance_received_by' => $rowData[11] ?? null,
                    ':pending_amount'      => !empty($rowData[12]) ? floatval($rowData[12]) : 0.00,
                    ':pending_received_by' => $rowData[13] ?? null,
                    ':total_food_bill'     => !empty($rowData[14]) ? floatval($rowData[14]) : 0.00,
                    ':food_received_by'    => $rowData[15] ?? null,
                    ':decoration_charges'  => !empty($rowData[17]) ? floatval($rowData[17]) : 0.00,
                    ':tip_amount'          => !empty($rowData[18]) ? floatval($rowData[18]) : 0.00
                ]);

            // 2. KITCHEN EXPENSES LEDGER
            case 'Kitchen Exp':
            case 'kitchen_expense':
                $sql = "INSERT INTO kitchen_expenses (
                            date, category, description, qty, unit, price_per_unit, vendor_name
                        ) VALUES (
                            :date, :category, :description, :qty, :unit, :price_per_unit, :vendor_name
                        )";
                
                $stmt = $pdo->prepare($sql);
                return $stmt->execute([
                    ':date'           => $rowData[1] ?? date('Y-m-d'),
                    ':category'       => $rowData[2] ?? 'General',
                    ':description'    => $rowData[3] ?? null,
                    ':qty'            => !empty($rowData[4]) ? floatval($rowData[4]) : 1.00,
                    ':unit'           => $rowData[5] ?? 'pcs',
                    ':price_per_unit' => !empty($rowData[6]) ? floatval($rowData[6]) : 0.00,
                    ':vendor_name'    => $rowData[8] ?? null
                ]);

            // 3. FARM OPERATIONS EXPENSES LEDGER
            case 'Farm Exp ':
            case 'farm_expense':
                $sql = "INSERT INTO farm_expenses (
                            date, description, amount, payment_mode, vendor_name, category, remark
                        ) VALUES (
                            :date, :description, :amount, :payment_mode, :vendor_name, :category, :remark
                        )";
                
                $stmt = $pdo->prepare($sql);
                return $stmt->execute([
                    ':date'         => $rowData[1] ?? date('Y-m-d'),
                    ':description'  => $rowData[2] ?? 'Farm Expense',
                    ':amount'       => !empty($rowData[3]) ? floatval($rowData[3]) : 0.00,
                    ':payment_mode' => $rowData[4] ?? 'Cash',
                    ':vendor_name'  => $rowData[5] ?? null,
                    ':category'     => $rowData[6] ?? 'Other',
                    ':remark'       => $rowData[7] ?? null
                ]);

            default:
                error_log("Local DB Bridge Warning: Unknown ledger entry type requested: " . $type);
                return false;
        }
    } catch (Exception $e) {
        error_log("Local DB Bridge Exception: " . $e->getMessage());
        return false;
    }
}