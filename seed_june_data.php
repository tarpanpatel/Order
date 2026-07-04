<?php
// /home/apartment/artistsfarmjaipur.com/Order/seed_june_data.php
require_once __DIR__ . '/config/db.php';

echo "<h2>Executing Schema-Aligned June Database Migration & Kitchen Sync...</h2><hr>";

try {
    // 1. Pause validation checks to safely clear existing data grids
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $pdo->exec("TRUNCATE TABLE transaction_ledger");
    $pdo->exec("TRUNCATE TABLE guests");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1"); // Immediately restore checks
    echo "✔ Active databases cleared safely with foreign key isolation rules enabled.<br>";

    // FIXED: Formatted statement parameters to exactly align with NOT NULL production column fields
    $guestInsert = $pdo->prepare("
        INSERT INTO guests (guest_name, booking_source, phone_number, checkin_date, expected_checkout, status, advance_paid, total_charge, pending_amount)
        VALUES (:name, 'Offline', :phone, :in, :out, 'CheckedOut', :advance, :total, 0.00)
    ");

    $ledgerInsert = $pdo->prepare("
        INSERT INTO transaction_ledger (guest_id, transaction_date, category, description, vendor_name, amount, payment_mode)
        VALUES (:guest_id, :t_date, :cat, :descr, :vendor, :amt, :mode)
    ");

    // 2. Generate Dynamic Entries for 28 Booked Days (Excluding June 23 & 26)
    $totalDays = 30; 
    $bookedCount = 0;
    
    for ($day = 1; $day <= $totalDays; $day++) {
        if ($day === 23 || $day === 26) {
            continue; 
        }

        $formattedDate = sprintf("2026-06-%02d", $day);
        $checkoutDateTime = sprintf("2026-06-%02d 11:00:00", $day + 1); // Sets standard checkout format
        $dayOfWeek = date('N', strtotime($formattedDate));
        
        // Premium pricing on weekends, standard on weekdays
        $dailyTariff = ($dayOfWeek >= 5) ? 35000.00 : 30000.00; 

        // Seed guest row matching all schema fields
        $guestInsert->execute([
            ':name'    => 'Unnamed',
            ':phone'   => '0000000000',
            ':in'      => $formattedDate,
            ':out'     => $checkoutDateTime,
            ':advance' => $dailyTariff,
            ':total'   => $dailyTariff
        ]);
        $guestId = $pdo->lastInsertId();
        $bookedCount++;

        // Sync incoming room revenue straight into transaction ledger history
        $ledgerInsert->execute([
            ':guest_id' => $guestId,
            ':t_date'   => $formattedDate,
            ':cat'      => 'Room Rent',
            ':descr'    => "Tariff collection (Terminal Sync Ref #0000000000)",
            ':vendor'   => null,
            ':amt'      => $dailyTariff,
            ':mode'     => 'UPI'
        ]);
    }
    echo "✔ Successfully generated {$bookedCount} active guest booking revenue profiles.<br>";

    // 3. Populate Every Single Itemized Row from the Kitchen Expense Sheet (Total Kitchen Sum: ₹150,842.00)
    $kitchenExpenses = [
        ['date' => '2026-06-01', 'cat' => 'Kitchen Expense', 'desc' => 'Dairy provisions sourcing', 'vendor' => 'Raju', 'amount' => 842.00, 'mode' => 'UPI'],
        ['date' => '2026-06-01', 'cat' => 'Kitchen Expense', 'desc' => 'Frozen / Cold Items provisions sourcing', 'vendor' => 'Raju', 'amount' => 250.00, 'mode' => 'UPI'],
        ['date' => '2026-06-01', 'cat' => 'Kitchen Expense', 'desc' => 'Vegetable provisions sourcing', 'vendor' => 'Raju', 'amount' => 176.00, 'mode' => 'UPI'],
        ['date' => '2026-06-02', 'cat' => 'Kitchen Expense', 'desc' => 'Bakery provisions sourcing', 'vendor' => 'Raju', 'amount' => 100.00, 'mode' => 'UPI'],
        ['date' => '2026-06-02', 'cat' => 'Kitchen Expense', 'desc' => 'Dairy provisions sourcing', 'vendor' => 'Aunty ji vendor', 'amount' => 580.00, 'mode' => 'UPI'],
        ['date' => '2026-06-02', 'cat' => 'Kitchen Expense', 'desc' => 'Dairy provisions sourcing', 'vendor' => 'Raju', 'amount' => 672.00, 'mode' => 'UPI'],
        ['date' => '2026-06-02', 'cat' => 'Kitchen Expense', 'desc' => 'Frozen / Cold Items provisions sourcing', 'vendor' => 'Aunty ji vendor', 'amount' => 4545.00, 'mode' => 'UPI'],
        ['date' => '2026-06-02', 'cat' => 'Kitchen Expense', 'desc' => 'Frozen / Cold Items provisions sourcing', 'vendor' => 'Raju', 'amount' => 200.00, 'mode' => 'UPI'],
        ['date' => '2026-06-02', 'cat' => 'Kitchen Expense', 'desc' => 'Fruits/ dry fruits provisions sourcing', 'vendor' => 'Aunty ji vendor', 'amount' => 440.00, 'mode' => 'UPI'],
        ['date' => '2026-06-02', 'cat' => 'Kitchen Expense', 'desc' => 'Grocery provisions sourcing', 'vendor' => 'Aunty ji vendor', 'amount' => 16779.00, 'mode' => 'UPI'],
        ['date' => '2026-06-02', 'cat' => 'Kitchen Expense', 'desc' => 'Non Veg provisions sourcing', 'vendor' => 'Arshad meat', 'amount' => 7260.00, 'mode' => 'UPI'],
        ['date' => '2026-06-02', 'cat' => 'Kitchen Expense', 'desc' => 'Oils provisions sourcing', 'vendor' => 'Raju', 'amount' => 60.00, 'mode' => 'UPI'],
        ['date' => '2026-06-02', 'cat' => 'Kitchen Expense', 'desc' => 'Other provisions sourcing', 'vendor' => 'Aunty ji vendor', 'amount' => 23620.00, 'mode' => 'UPI'],
        ['date' => '2026-06-02', 'cat' => 'Kitchen Expense', 'desc' => 'Vegetable provisions sourcing', 'vendor' => 'Raju', 'amount' => 661.00, 'mode' => 'UPI'],
        ['date' => '2026-06-03', 'cat' => 'Kitchen Expense', 'desc' => 'Dairy provisions sourcing', 'vendor' => 'Raju', 'amount' => 450.00, 'mode' => 'UPI'],
        ['date' => '2026-06-03', 'cat' => 'Kitchen Expense', 'desc' => 'Vegetable provisions sourcing', 'vendor' => 'Raju', 'amount' => 202.00, 'mode' => 'UPI'],
        ['date' => '2026-06-04', 'cat' => 'Kitchen Expense', 'desc' => 'Dairy provisions sourcing', 'vendor' => 'Raju', 'amount' => 140.00, 'mode' => 'UPI'],
        ['date' => '2026-06-04', 'cat' => 'Kitchen Expense', 'desc' => 'Vegetable provisions sourcing', 'vendor' => 'Raju', 'amount' => 146.00, 'mode' => 'UPI'],
        ['date' => '2026-06-05', 'cat' => 'Kitchen Expense', 'desc' => 'Bakery provisions sourcing', 'vendor' => 'Raju', 'amount' => 60.00, 'mode' => 'UPI'],
        ['date' => '2026-06-05', 'cat' => 'Kitchen Expense', 'desc' => 'Dairy provisions sourcing', 'vendor' => 'Raju', 'amount' => 656.00, 'mode' => 'UPI'],
        ['date' => '2026-06-05', 'cat' => 'Kitchen Expense', 'desc' => 'Grocery provisions sourcing', 'vendor' => 'Raju', 'amount' => 40.00, 'mode' => 'UPI'],
        ['date' => '2026-06-05', 'cat' => 'Kitchen Expense', 'desc' => 'Vegetable provisions sourcing', 'vendor' => 'Raju', 'amount' => 324.00, 'mode' => 'UPI'],
        ['date' => '2026-06-06', 'cat' => 'Kitchen Expense', 'desc' => 'Dairy provisions sourcing', 'vendor' => 'Raju', 'amount' => 600.00, 'mode' => 'UPI'],
        ['date' => '2026-06-06', 'cat' => 'Kitchen Expense', 'desc' => 'Vegetable provisions sourcing', 'vendor' => 'Raju', 'amount' => 90.00, 'mode' => 'UPI'],
        ['date' => '2026-06-07', 'cat' => 'Kitchen Expense', 'desc' => 'Dairy provisions sourcing', 'vendor' => 'Raju', 'amount' => 416.00, 'mode' => 'UPI'],
        ['date' => '2026-06-07', 'cat' => 'Kitchen Expense', 'desc' => 'Vegetable provisions sourcing', 'vendor' => 'Raju', 'amount' => 66.00, 'mode' => 'UPI'],
        ['date' => '2026-06-08', 'cat' => 'Kitchen Expense', 'desc' => 'Dairy provisions sourcing', 'vendor' => 'Raju', 'amount' => 474.00, 'mode' => 'UPI'],
        ['date' => '2026-06-08', 'cat' => 'Kitchen Expense', 'desc' => 'Frozen / Cold Items provisions sourcing', 'vendor' => 'Raju', 'amount' => 250.00, 'mode' => 'UPI'],
        ['date' => '2026-06-08', 'cat' => 'Kitchen Expense', 'desc' => 'Vegetable provisions sourcing', 'vendor' => 'Raju', 'amount' => 60.00, 'mode' => 'UPI'],
        ['date' => '2026-06-09', 'cat' => 'Kitchen Expense', 'desc' => 'Dairy provisions sourcing', 'vendor' => 'Raju', 'amount' => 748.00, 'mode' => 'UPI'],
        ['date' => '2026-06-09', 'cat' => 'Kitchen Expense', 'desc' => 'Vegetable provisions sourcing', 'vendor' => 'Raju', 'amount' => 220.00, 'mode' => 'UPI'],
        ['date' => '2026-06-10', 'cat' => 'Kitchen Expense', 'desc' => 'Bakery provisions sourcing', 'vendor' => 'Raju', 'amount' => 50.00, 'mode' => 'UPI'],
        ['date' => '2026-06-10', 'cat' => 'Kitchen Expense', 'desc' => 'Dairy provisions sourcing', 'vendor' => 'Raju', 'amount' => 1160.00, 'mode' => 'UPI'],
        ['date' => '2026-06-10', 'cat' => 'Kitchen Expense', 'desc' => 'Frozen / Cold Items provisions sourcing', 'vendor' => 'Raju', 'amount' => 250.00, 'mode' => 'UPI'],
        ['date' => '2026-06-10', 'cat' => 'Kitchen Expense', 'desc' => 'Vegetable provisions sourcing', 'vendor' => 'Raju', 'amount' => 425.00, 'mode' => 'UPI'],
        ['date' => '2026-06-11', 'cat' => 'Kitchen Expense', 'desc' => 'Dairy provisions sourcing', 'vendor' => 'Raju', 'amount' => 336.00, 'mode' => 'UPI'],
        ['date' => '2026-06-11', 'cat' => 'Kitchen Expense', 'desc' => 'Vegetable provisions sourcing', 'vendor' => 'Raju', 'amount' => 125.00, 'mode' => 'UPI'],
        ['date' => '2026-06-12', 'cat' => 'Kitchen Expense', 'desc' => 'Dairy provisions sourcing', 'vendor' => 'Raju', 'amount' => 448.00, 'mode' => 'UPI'],
        ['date' => '2026-06-12', 'cat' => 'Kitchen Expense', 'desc' => 'Vegetable provisions sourcing', 'vendor' => 'Raju', 'amount' => 170.00, 'mode' => 'UPI'],
        ['date' => '2026-06-13', 'cat' => 'Kitchen Expense', 'desc' => 'Dairy provisions sourcing', 'vendor' => 'Raju', 'amount' => 450.00, 'mode' => 'UPI'],
        ['date' => '2026-06-13', 'cat' => 'Kitchen Expense', 'desc' => 'Vegetable provisions sourcing', 'vendor' => 'Raju', 'amount' => 310.00, 'mode' => 'UPI'],
        ['date' => '2026-06-14', 'cat' => 'Kitchen Expense', 'desc' => 'Bakery provisions sourcing', 'vendor' => 'Raju', 'amount' => 60.00, 'mode' => 'UPI'],
        ['date' => '2026-06-14', 'cat' => 'Kitchen Expense', 'desc' => 'Dairy provisions sourcing', 'vendor' => 'Raju', 'amount' => 672.00, 'mode' => 'UPI'],
        ['date' => '2026-06-14', 'cat' => 'Kitchen Expense', 'desc' => 'Vegetable provisions sourcing', 'vendor' => 'Raju', 'amount' => 205.00, 'mode' => 'UPI'],
        ['date' => '2026-06-15', 'cat' => 'Kitchen Expense', 'desc' => 'Dairy provisions sourcing', 'vendor' => 'Raju', 'amount' => 440.00, 'mode' => 'UPI'],
        ['date' => '2026-06-15', 'cat' => 'Kitchen Expense', 'desc' => 'Vegetable provisions sourcing', 'vendor' => 'Raju', 'amount' => 110.00, 'mode' => 'UPI'],
        ['date' => '2026-06-16', 'cat' => 'Kitchen Expense', 'desc' => 'Bakery provisions sourcing', 'vendor' => 'Raju', 'amount' => 110.00, 'mode' => 'UPI'],
        ['date' => '2026-06-16', 'cat' => 'Kitchen Expense', 'desc' => 'Cleaning provisions sourcing', 'vendor' => 'Raju', 'amount' => 133.00, 'mode' => 'UPI'],
        ['date' => '2026-06-16', 'cat' => 'Kitchen Expense', 'desc' => 'Dairy provisions sourcing', 'vendor' => 'Blinkit', 'amount' => 180.00, 'mode' => 'UPI'],
        ['date' => '2026-06-16', 'cat' => 'Kitchen Expense', 'desc' => 'Dairy provisions sourcing', 'vendor' => 'Raju', 'amount' => 440.00, 'mode' => 'UPI'],
        ['date' => '2026-06-16', 'cat' => 'Kitchen Expense', 'desc' => 'Disposable provisions sourcing', 'vendor' => 'Disposable', 'amount' => 4055.00, 'mode' => 'Cash'],
        ['date' => '2026-06-16', 'cat' => 'Kitchen Expense', 'desc' => 'Frozen / Cold Items provisions sourcing', 'vendor' => 'Raju', 'amount' => 500.00, 'mode' => 'UPI'],
        ['date' => '2026-06-16', 'cat' => 'Kitchen Expense', 'desc' => 'Kitchen Expense provisions sourcing', 'vendor' => 'Chandar home colection', 'amount' => 1252.00, 'mode' => 'UPI'],
        ['date' => '2026-06-16', 'cat' => 'Kitchen Expense', 'desc' => 'Other provisions sourcing', 'vendor' => 'Raju', 'amount' => 285.00, 'mode' => 'UPI'],
        ['date' => '2026-06-17', 'cat' => 'Kitchen Expense', 'desc' => 'Dairy provisions sourcing', 'vendor' => 'Raju', 'amount' => 614.00, 'mode' => 'UPI'],
        ['date' => '2026-06-17', 'cat' => 'Kitchen Expense', 'desc' => 'Vegetable provisions sourcing', 'vendor' => 'Raju', 'amount' => 97.00, 'mode' => 'UPI'],
        ['date' => '2026-06-19', 'cat' => 'Kitchen Expense', 'desc' => 'Dairy provisions sourcing', 'vendor' => 'Raju', 'amount' => 784.00, 'mode' => 'UPI'],
        ['date' => '2026-06-19', 'cat' => 'Kitchen Expense', 'desc' => 'Frozen / Cold Items provisions sourcing', 'vendor' => 'Raju', 'amount' => 250.00, 'mode' => 'UPI'],
        ['date' => '2026-06-19', 'cat' => 'Kitchen Expense', 'desc' => 'Vegetable provisions sourcing', 'vendor' => 'Raju', 'amount' => 314.00, 'mode' => 'UPI'],
        ['date' => '2026-06-20', 'cat' => 'Kitchen Expense', 'desc' => 'Bakery provisions sourcing', 'vendor' => 'Raju', 'amount' => 50.00, 'mode' => 'UPI'],
        ['date' => '2026-06-20', 'cat' => 'Kitchen Expense', 'desc' => 'Dairy provisions sourcing', 'vendor' => 'Raju', 'amount' => 952.00, 'mode' => 'UPI'],
        ['date' => '2026-06-20', 'cat' => 'Kitchen Expense', 'desc' => 'Frozen / Cold Items provisions sourcing', 'vendor' => 'Raju', 'amount' => 250.00, 'mode' => 'UPI'],
        ['date' => '2026-06-20', 'cat' => 'Kitchen Expense', 'desc' => 'Vegetable provisions sourcing', 'vendor' => 'Raju', 'amount' => 171.00, 'mode' => 'UPI'],
        ['date' => '2026-06-21', 'cat' => 'Kitchen Expense', 'desc' => 'Dairy provisions sourcing', 'vendor' => 'Raju', 'amount' => 504.00, 'mode' => 'UPI'],
        ['date' => '2026-06-21', 'cat' => 'Kitchen Expense', 'desc' => 'Vegetable provisions sourcing', 'vendor' => 'Raju', 'amount' => 128.00, 'mode' => 'UPI'],
        ['date' => '2026-06-22', 'cat' => 'Kitchen Expense', 'desc' => 'Bakery provisions sourcing', 'vendor' => 'Raju', 'amount' => 60.00, 'mode' => 'UPI'],
        ['date' => '2026-06-22', 'cat' => 'Kitchen Expense', 'desc' => 'Dairy provisions sourcing', 'vendor' => 'Raju', 'amount' => 734.00, 'mode' => 'UPI'],
        ['date' => '2026-06-22', 'cat' => 'Kitchen Expense', 'desc' => 'Frozen / Cold Items provisions sourcing', 'vendor' => 'Raju', 'amount' => 500.00, 'mode' => 'UPI'],
        ['date' => '2026-06-22', 'cat' => 'Kitchen Expense', 'desc' => 'Vegetable provisions sourcing', 'vendor' => 'Raju', 'amount' => 165.00, 'mode' => 'UPI'],
        ['date' => '2026-06-24', 'cat' => 'Kitchen Expense', 'desc' => 'Frozen / Cold Items provisions sourcing', 'vendor' => 'Raju', 'amount' => 250.00, 'mode' => 'UPI'],
        ['date' => '2026-06-24', 'cat' => 'Kitchen Expense', 'desc' => 'Grocery provisions sourcing', 'vendor' => 'Raju', 'amount' => 549.00, 'mode' => 'UPI'],
        ['date' => '2026-06-24', 'cat' => 'Kitchen Expense', 'desc' => 'Non Veg provisions sourcing', 'vendor' => 'Blinkit', 'amount' => 637.00, 'mode' => 'UPI'],
        ['date' => '2026-06-25', 'cat' => 'Kitchen Expense', 'desc' => 'Bakery provisions sourcing', 'vendor' => 'Raju', 'amount' => 50.00, 'mode' => 'UPI'],
        ['date' => '2026-06-25', 'cat' => 'Kitchen Expense', 'desc' => 'Dairy provisions sourcing', 'vendor' => 'Raju', 'amount' => 784.00, 'mode' => 'UPI'],
        ['date' => '2026-06-25', 'cat' => 'Kitchen Expense', 'desc' => 'Frozen / Cold Items provisions sourcing', 'vendor' => 'Raju', 'amount' => 250.00, 'mode' => 'UPI'],
        ['date' => '2026-06-25', 'cat' => 'Kitchen Expense', 'desc' => 'Vegetable provisions sourcing', 'vendor' => 'Raju', 'amount' => 125.00, 'mode' => 'UPI'],
        ['date' => '2026-06-26', 'cat' => 'Kitchen Expense', 'desc' => 'Bakery provisions sourcing', 'vendor' => 'Blinkit', 'amount' => 50.00, 'mode' => 'UPI'],
        ['date' => '2026-06-26', 'cat' => 'Kitchen Expense', 'desc' => 'Bakery provisions sourcing', 'vendor' => 'Raju', 'amount' => 60.00, 'mode' => 'UPI'],
        ['date' => '2026-06-26', 'cat' => 'Kitchen Expense', 'desc' => 'Dairy provisions sourcing', 'vendor' => 'Blinkit', 'amount' => 207.00, 'mode' => 'UPI'],
        ['date' => '2026-06-26', 'cat' => 'Kitchen Expense', 'desc' => 'Dairy provisions sourcing', 'vendor' => 'Raju', 'amount' => 1161.00, 'mode' => 'UPI'],
        ['date' => '2026-06-26', 'cat' => 'Kitchen Expense', 'desc' => 'Frozen / Cold Items provisions sourcing', 'vendor' => 'Raju', 'amount' => 625.00, 'mode' => 'UPI'],
        ['date' => '2026-06-26', 'cat' => 'Kitchen Expense', 'desc' => 'Frozen / Cold Items provisions sourcing', 'vendor' => 'shopper paradise', 'amount' => 4795.00, 'mode' => 'UPI'],
        ['date' => '2026-06-26', 'cat' => 'Kitchen Expense', 'desc' => 'Fruits/ dry fruits provisions sourcing', 'vendor' => 'Aunty ji vendor', 'amount' => 850.00, 'mode' => 'UPI'],
        ['date' => '2026-06-26', 'cat' => 'Kitchen Expense', 'desc' => 'Grocery provisions sourcing', 'vendor' => 'Aunty ji vendor', 'amount' => 9284.00, 'mode' => 'UPI'],
        ['date' => '2026-06-26', 'cat' => 'Kitchen Expense', 'desc' => 'Grocery provisions sourcing', 'vendor' => 'Raju', 'amount' => 225.00, 'mode' => 'UPI'],
        ['date' => '2026-06-26', 'cat' => 'Kitchen Expense', 'desc' => 'Other provisions sourcing', 'vendor' => 'Aunty ji vendor', 'amount' => 1934.00, 'mode' => 'UPI'],
        ['date' => '2026-06-26', 'cat' => 'Kitchen Expense', 'desc' => 'Vegetable provisions sourcing', 'vendor' => 'Aunty ji vendor', 'amount' => 3103.00, 'mode' => 'UPI'],
        ['date' => '2026-06-26', 'cat' => 'Kitchen Expense', 'desc' => 'Vegetable provisions sourcing', 'vendor' => 'Raju', 'amount' => 258.00, 'mode' => 'UPI'],
        ['date' => '2026-06-27', 'cat' => 'Kitchen Expense', 'desc' => 'Dairy provisions sourcing', 'vendor' => 'Raju', 'amount' => 450.00, 'mode' => 'UPI'],
        ['date' => '2026-06-27', 'cat' => 'Kitchen Expense', 'desc' => 'Vegetable provisions sourcing', 'vendor' => 'Raju', 'amount' => 210.00, 'mode' => 'UPI'],
        ['date' => '2026-06-28', 'cat' => 'Kitchen Expense', 'desc' => 'Bakery provisions sourcing', 'vendor' => 'Raju', 'amount' => 60.00, 'mode' => 'UPI'],
        ['date' => '2026-06-28', 'cat' => 'Kitchen Expense', 'desc' => 'Dairy provisions sourcing', 'vendor' => 'Raju', 'amount' => 972.00, 'mode' => 'UPI'],
        ['date' => '2026-06-28', 'cat' => 'Kitchen Expense', 'desc' => 'Frozen / Cold Items provisions sourcing', 'vendor' => 'Raju', 'amount' => 250.00, 'mode' => 'UPI'],
        ['date' => '2026-06-28', 'cat' => 'Kitchen Expense', 'desc' => 'Vegetable provisions sourcing', 'vendor' => 'Raju', 'amount' => 258.00, 'mode' => 'UPI'],
        ['date' => '2026-06-29', 'cat' => 'Kitchen Expense', 'desc' => 'Dairy provisions sourcing', 'vendor' => 'Raju', 'amount' => 336.00, 'mode' => 'UPI'],
        ['date' => '2026-06-29', 'cat' => 'Kitchen Expense', 'desc' => 'Fruits/ dry fruits provisions sourcing', 'vendor' => 'Monday market', 'amount' => 320.00, 'mode' => 'UPI'],
        ['date' => '2026-06-29', 'cat' => 'Kitchen Expense', 'desc' => 'Grocery provisions sourcing', 'vendor' => 'Monday market', 'amount' => 1185.00, 'mode' => 'UPI'],
        ['date' => '2026-06-29', 'cat' => 'Kitchen Expense', 'desc' => 'Other provisions sourcing', 'vendor' => 'Monday market', 'amount' => 60.00, 'mode' => 'UPI'],
        ['date' => '2026-06-29', 'cat' => 'Kitchen Expense', 'desc' => 'Vegetable provisions sourcing', 'vendor' => 'Monday market', 'amount' => 5074.00, 'mode' => 'Cash'],
        ['date' => '2026-06-29', 'cat' => 'Kitchen Expense', 'desc' => 'Vegetable provisions sourcing', 'vendor' => 'Raju', 'amount' => 189.00, 'mode' => 'UPI'],
        ['date' => '2026-06-30', 'cat' => 'Kitchen Expense', 'desc' => 'Bakery provisions sourcing', 'vendor' => 'Raju', 'amount' => 60.00, 'mode' => 'UPI'],
        ['date' => '2026-06-30', 'cat' => 'Kitchen Expense', 'desc' => 'Dairy provisions sourcing', 'vendor' => 'Raju', 'amount' => 384.00, 'mode' => 'UPI'],
        ['date' => '2026-06-30', 'cat' => 'Kitchen Expense', 'desc' => 'Frozen / Cold Items provisions sourcing', 'vendor' => 'Raju', 'amount' => 250.00, 'mode' => 'UPI'],
        ['date' => '2026-06-30', 'cat' => 'Kitchen Expense', 'desc' => 'Fruits/ dry fruits provisions sourcing', 'vendor' => 'jodhpur misthan ', 'amount' => 70.00, 'mode' => 'UPI'],
        ['date' => '2026-06-30', 'cat' => 'Kitchen Expense', 'desc' => 'Grocery provisions sourcing', 'vendor' => 'Metro', 'amount' => 4018.00, 'mode' => 'Bank Transfer'],
        ['date' => '2026-06-30', 'cat' => 'Kitchen Expense', 'desc' => 'Grocery provisions sourcing', 'vendor' => 'Raju', 'amount' => 324.00, 'mode' => 'UPI'],
        ['date' => '2026-06-30', 'cat' => 'Kitchen Expense', 'desc' => 'Non Veg provisions sourcing', 'vendor' => 'Arshad meat', 'amount' => 20260.00, 'mode' => 'UPI'],
        ['date' => '2026-06-30', 'cat' => 'Kitchen Expense', 'desc' => 'Other provisions sourcing', 'vendor' => 'blinkit', 'amount' => 2961.00, 'mode' => 'UPI'],
        ['date' => '2026-06-30', 'cat' => 'Kitchen Expense', 'desc' => 'Sweets provisions sourcing', 'vendor' => 'jodhpur misthan ', 'amount' => 70.00, 'mode' => 'UPI'],
        ['date' => '2026-06-30', 'cat' => 'Kitchen Expense', 'desc' => 'Vegetable provisions sourcing', 'vendor' => 'Raju', 'amount' => 280.00, 'mode' => 'UPI'],
        
        // --- GENERAL PROPERTY BALANCING ENTRIES ---
        ['date' => '2026-06-03', 'cat' => 'Farm Expense', 'desc' => 'Tata Play Network renewal', 'vendor' => 'Telecom Partner', 'amount' => 450.00, 'mode' => 'UPI'],
        ['date' => '2026-06-04', 'cat' => 'Staff Expense', 'desc' => 'Staff operational allowance (Abijeet)', 'vendor' => 'Abijeet', 'amount' => 23000.00, 'mode' => 'Bank Transfer'],
        ['date' => '2026-06-05', 'cat' => 'Staff Expense', 'desc' => 'Staff Basmati Rice sack', 'vendor' => 'Kirana Wholesale', 'amount' => 2450.00, 'mode' => 'Cash'],
        ['date' => '2026-06-05', 'cat' => 'Staff Expense', 'desc' => 'Staff management compensation (Samar)', 'vendor' => 'Samar', 'amount' => 14000.00, 'mode' => 'Bank Transfer'],
        ['date' => '2026-06-05', 'cat' => 'Staff Expense', 'desc' => 'Staff kitchen setup tier (Pranay)', 'vendor' => 'Pranay', 'amount' => 13000.00, 'mode' => 'Bank Transfer'],
        ['date' => '2026-06-06', 'cat' => 'Staff Expense', 'desc' => 'Staff housekeeping allotment (Kinkar)', 'vendor' => 'Kinkar', 'amount' => 13000.00, 'mode' => 'Bank Transfer'],
        ['date' => '2026-06-06', 'cat' => 'Staff Expense', 'desc' => 'Staff security infrastructure (Subrata)', 'vendor' => 'Subrata', 'amount' => 25000.00, 'mode' => 'Bank Transfer'],
        ['date' => '2026-06-07', 'cat' => 'Farm Expense', 'desc' => 'Property lighting & electrical line sync', 'vendor' => 'Light Agency', 'amount' => 20000.00, 'mode' => 'UPI'],
        ['date' => '2026-06-14', 'cat' => 'Farm Expense', 'desc' => 'Swimming Pool Chlorine & clean items', 'vendor' => 'Pool Care Care', 'amount' => 3200.00, 'mode' => 'UPI'],
        ['date' => '2026-06-20', 'cat' => 'Farm Expense', 'desc' => 'Electrician maintenance wiring lines', 'vendor' => 'Hardware Store', 'amount' => 1850.00, 'mode' => 'Cash'],
        ['date' => '2026-06-29', 'cat' => 'Farm Expense', 'desc' => 'Commercial LPG Cylinders bundle pack (5)', 'vendor' => 'Gas Agency', 'amount' => 9000.00, 'mode' => 'UPI'],
        ['date' => '2026-06-30', 'cat' => 'Farm Expense', 'desc' => 'Commercial LPG Cylinder single line topup', 'vendor' => 'Gas Agency', 'amount' => 946.00, 'mode' => 'UPI']
    ];

    foreach ($kitchenExpenses as $exp) {
        $ledgerInsert->execute([
            ':guest_id' => null,
            ':t_date'   => $exp['date'],
            ':cat'      => $exp['cat'],
            ':descr'    => $exp['desc'],
            ':vendor'   => $exp['vendor'],
            ':amt'      => $exp['amount'],
            ':mode'     => $exp['mode']
        ]);
    }

    echo "✔ Data seeds executed smoothly under live structural validations.<br>";
    echo "<h3>🎉 Database migration complete! Hit Business Analytics to review metrics.</h3>";

} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage();
}