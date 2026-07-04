<?php
// /home/apartment/artistsfarmjaipur.com/Order/seed_june_data.php
require_once __DIR__ . '/config/db.php';

echo "<h2>Executing Schema-Unified June Database Migration & Operational Sync...</h2><hr>";

try {
    // 1. Pause foreign keys to safely wipe old logs across native tables
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $pdo->exec("TRUNCATE TABLE guests");
    $pdo->exec("TRUNCATE TABLE kitchen_expenses");
    $pdo->exec("TRUNCATE TABLE farm_expenses");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "✔ Native data grids flushed cleanly.<br>";

    // Prepare SQL compilation engines matching your layout exactly
    $guestInsert = $pdo->prepare("
        INSERT INTO guests (guest_name, booking_source, phone_number, checkin_date, expected_checkout, status, advance_paid, total_charge, pending_amount)
        VALUES (:name, 'Offline', :phone, :in, :out, 'CheckedOut', :advance, :total, 0.00)
    ");

    $kitchenInsert = $pdo->prepare("
        INSERT INTO kitchen_expenses (date, category, description, qty, unit, price_per_unit, vendor_name)
        VALUES (:date, :cat, :desc, :qty, :unit, :price, :vendor)
    ");

    $farmInsert = $pdo->prepare("
        INSERT INTO farm_expenses (date, description, amount, payment_mode, vendor_name, category)
        VALUES (:date, :desc, :amount, :mode, :vendor, :cat)
    ");

    // 2. Loop and generate entries for 28 Booked Days (Excluding June 23 & 26)
    $totalDays = 30; 
    $bookedCount = 0;
    
    for ($day = 1; $day <= $totalDays; $day++) {
        if ($day === 23 || $day === 26) {
            continue; 
        }

        $formattedDate = sprintf("2026-06-%02d", $day);
        $checkoutDateTime = sprintf("2026-06-%02d 11:00:00", $day + 1);
        $dayOfWeek = date('N', strtotime($formattedDate));
        
        // Weekend premium pricing scale matches original document yields
        $dailyTariff = ($dayOfWeek >= 5) ? 35000.00 : 30000.00; 

        $guestInsert->execute([
            ':name'    => 'Unnamed',
            ':phone'   => '0000000000',
            ':in'      => $formattedDate,
            ':out'     => $checkoutDateTime,
            ':advance' => $dailyTariff,
            ':total'   => $dailyTariff
        ]);
        $bookedCount++;
    }
    echo "✔ Successfully generated {$bookedCount} active guest booking revenue profiles.<br>";

    // 3. Populate Every Single Row from the Kitchen Expense Sheet (Total Sum: ₹150,842.00)
    $kitchenExpenses = [
        ['date' => '2026-06-01', 'cat' => 'Dairy', 'desc' => 'Butter, Milk, Curd, Paneer Pack', 'qty' => 1.00, 'unit' => 'KG/L', 'price' => 842.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-01', 'cat' => 'Frozen / Cold Items', 'desc' => 'Ice Bags', 'qty' => 10.00, 'unit' => 'PKT', 'price' => 25.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-01', 'cat' => 'Vegetable', 'desc' => 'Onion, Tomato, Shimla Mirch', 'qty' => 1.00, 'unit' => 'KG', 'price' => 176.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-02', 'cat' => 'Bakery', 'desc' => 'Pizza base', 'qty' => 2.00, 'unit' => 'PKT', 'price' => 50.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-02', 'cat' => 'Dairy', 'desc' => 'Aunty ji milk stock', 'qty' => 1.00, 'unit' => 'L', 'price' => 580.00, 'vendor' => 'Aunty ji vendor'],
        ['date' => '2026-06-02', 'cat' => 'Dairy', 'desc' => 'Milk, Curd, Ghee, Butter mix', 'qty' => 1.00, 'unit' => 'L/KG', 'price' => 672.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-02', 'cat' => 'Frozen / Cold Items', 'desc' => 'Cold goods stock', 'qty' => 1.00, 'unit' => 'PKT', 'price' => 4545.00, 'vendor' => 'Aunty ji vendor'],
        ['date' => '2026-06-02', 'cat' => 'Frozen / Cold Items', 'desc' => 'Ice Bags', 'qty' => 8.00, 'unit' => 'PKT', 'price' => 25.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-02', 'cat' => 'Fruits/ dry fruits', 'desc' => 'Fruit allocations', 'qty' => 1.00, 'unit' => 'KG', 'price' => 440.00, 'vendor' => 'Aunty ji vendor'],
        ['date' => '2026-06-02', 'cat' => 'Grocery', 'desc' => 'Bulk grains & dry inventory', 'qty' => 1.00, 'unit' => 'KG', 'price' => 16779.00, 'vendor' => 'Aunty ji vendor'],
        ['date' => '2026-06-02', 'cat' => 'Non Veg', 'desc' => 'Chicken & Mutton provisions', 'qty' => 1.00, 'unit' => 'KG', 'price' => 7260.00, 'vendor' => 'Arshad meat'],
        ['date' => '2026-06-02', 'cat' => 'Oils', 'desc' => 'Mustard Oil', 'qty' => 0.50, 'unit' => 'L', 'price' => 120.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-02', 'cat' => 'Other', 'desc' => 'Misc kitchen essentials', 'qty' => 1.00, 'unit' => 'PCS', 'price' => 23620.00, 'vendor' => 'Aunty ji vendor'],
        ['date' => '2026-06-02', 'cat' => 'Vegetable', 'desc' => 'Onion, Tomato, Khira, Shimla Lemons', 'qty' => 1.00, 'unit' => 'KG', 'price' => 661.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-03', 'cat' => 'Dairy', 'desc' => 'Milk refills', 'qty' => 8.00, 'unit' => 'L', 'price' => 56.25, 'vendor' => 'Raju'],
        ['date' => '2026-06-03', 'cat' => 'Vegetable', 'desc' => 'Tomato, Khira, Shimla', 'qty' => 1.00, 'unit' => 'KG', 'price' => 202.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-04', 'cat' => 'Dairy', 'desc' => 'Milk & Curd package', 'qty' => 1.00, 'unit' => 'L/KG', 'price' => 140.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-04', 'cat' => 'Vegetable', 'desc' => 'Onion, Tomato, Khira', 'qty' => 1.00, 'unit' => 'KG', 'price' => 146.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-05', 'cat' => 'Bakery', 'desc' => 'Bread buns', 'qty' => 1.00, 'unit' => 'PKT', 'price' => 60.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-05', 'cat' => 'Dairy', 'desc' => 'Milk & Curd daily batch', 'qty' => 1.00, 'unit' => 'L/KG', 'price' => 656.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-05', 'cat' => 'Grocery', 'desc' => 'Sugar', 'qty' => 1.00, 'unit' => 'KG', 'price' => 40.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-05', 'cat' => 'Vegetable', 'desc' => 'Mandi fresh vegetable items', 'qty' => 1.00, 'unit' => 'KG', 'price' => 324.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-06', 'cat' => 'Dairy', 'desc' => 'Paneer block supply', 'qty' => 2.00, 'unit' => 'KG', 'price' => 300.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-06', 'cat' => 'Vegetable', 'desc' => 'Tomato & Onion additions', 'qty' => 1.00, 'unit' => 'KG', 'price' => 90.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-07', 'cat' => 'Dairy', 'desc' => 'Milk and Curd stock', 'qty' => 1.00, 'unit' => 'L', 'price' => 416.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-07', 'cat' => 'Vegetable', 'desc' => 'Fresh herbs', 'qty' => 1.00, 'unit' => 'KG', 'price' => 66.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-08', 'cat' => 'Dairy', 'desc' => 'Milk additions', 'qty' => 1.00, 'unit' => 'L', 'price' => 474.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-08', 'cat' => 'Frozen / Cold Items', 'desc' => 'Ice Bags', 'qty' => 10.00, 'unit' => 'PKT', 'price' => 25.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-08', 'cat' => 'Vegetable', 'desc' => 'Tomato block purchase', 'qty' => 1.00, 'unit' => 'KG', 'price' => 60.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-09', 'cat' => 'Dairy', 'desc' => 'Curd & Paneer blocks', 'qty' => 1.00, 'unit' => 'KG', 'price' => 748.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-09', 'cat' => 'Vegetable', 'desc' => 'Green chillies & lemons', 'qty' => 1.00, 'unit' => 'KG', 'price' => 220.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-10', 'cat' => 'Bakery', 'desc' => 'Pizza bases', 'qty' => 1.00, 'unit' => 'PKT', 'price' => 50.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-10', 'cat' => 'Dairy', 'desc' => 'Paneer and raw cream batch', 'qty' => 1.00, 'unit' => 'KG', 'price' => 1160.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-10', 'cat' => 'Frozen / Cold Items', 'desc' => 'Ice cubes packaging', 'qty' => 10.00, 'unit' => 'PKT', 'price' => 25.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-10', 'cat' => 'Vegetable', 'desc' => 'Onions bulk supply', 'qty' => 1.00, 'unit' => 'KG', 'price' => 425.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-11', 'cat' => 'Dairy', 'desc' => 'Milk packages', 'qty' => 6.00, 'unit' => 'L', 'price' => 56.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-11', 'cat' => 'Vegetable', 'desc' => 'Local market greens', 'qty' => 1.00, 'unit' => 'KG', 'price' => 125.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-12', 'cat' => 'Dairy', 'desc' => 'Curd packaging lines', 'qty' => 1.00, 'unit' => 'KG', 'price' => 448.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-12', 'cat' => 'Vegetable', 'desc' => 'Tomatoes box supply', 'qty' => 1.00, 'unit' => 'KG', 'price' => 170.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-13', 'cat' => 'Dairy', 'desc' => 'Milk distribution batch', 'qty' => 1.00, 'unit' => 'L', 'price' => 450.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-13', 'cat' => 'Vegetable', 'desc' => 'Onions supply stack', 'qty' => 1.00, 'unit' => 'KG', 'price' => 310.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-14', 'cat' => 'Bakery', 'desc' => 'Bread loaves', 'qty' => 1.00, 'unit' => 'PKT', 'price' => 60.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-14', 'cat' => 'Dairy', 'desc' => 'Paneer blocks supply', 'qty' => 1.00, 'unit' => 'KG', 'price' => 672.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-14', 'cat' => 'Vegetable', 'desc' => 'Lemons and salad greens', 'qty' => 1.00, 'unit' => 'KG', 'price' => 205.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-15', 'cat' => 'Dairy', 'desc' => 'Milk distribution lines', 'qty' => 1.00, 'unit' => 'L', 'price' => 440.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-15', 'cat' => 'Vegetable', 'desc' => 'Onion load', 'qty' => 1.00, 'unit' => 'KG', 'price' => 110.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-16', 'cat' => 'Bakery', 'desc' => 'Bread package additions', 'qty' => 1.00, 'unit' => 'PKT', 'price' => 110.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-16', 'cat' => 'Cleaning', 'desc' => 'Detergents and kitchen scrubbers', 'qty' => 1.00, 'unit' => 'PCS', 'price' => 133.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-16', 'cat' => 'Dairy', 'desc' => 'Curd pack topup', 'qty' => 1.00, 'unit' => 'KG', 'price' => 180.00, 'vendor' => 'Blinkit'],
        ['date' => '2026-06-16', 'cat' => 'Dairy', 'desc' => 'Paneer blocking reset', 'qty' => 1.00, 'unit' => 'KG', 'price' => 440.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-16', 'cat' => 'Disposable', 'desc' => 'Paper food containers & disposables', 'qty' => 1.00, 'unit' => 'PKT', 'price' => 4055.00, 'vendor' => 'Disposable'],
        ['date' => '2026-06-16', 'cat' => 'Frozen / Cold Items', 'desc' => 'Ice Cubes block packs', 'qty' => 20.00, 'unit' => 'PKT', 'price' => 25.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-16', 'cat' => 'Kitchen Expense', 'desc' => 'Kitchen operational additions', 'qty' => 1.00, 'unit' => 'PCS', 'price' => 1252.00, 'vendor' => 'Chandar home colection'],
        ['date' => '2026-06-16', 'cat' => 'Other', 'desc' => 'Misc dining elements', 'qty' => 1.00, 'unit' => 'PCS', 'price' => 285.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-17', 'cat' => 'Dairy', 'desc' => 'Milk supply sync', 'qty' => 1.00, 'unit' => 'L', 'price' => 614.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-17', 'cat' => 'Vegetable', 'desc' => 'Tomato loaders', 'qty' => 1.00, 'unit' => 'KG', 'price' => 97.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-19', 'cat' => 'Dairy', 'desc' => 'Paneer block setup', 'qty' => 1.00, 'unit' => 'KG', 'price' => 784.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-19', 'cat' => 'Frozen / Cold Items', 'desc' => 'Ice cube packets', 'qty' => 10.00, 'unit' => 'PKT', 'price' => 25.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-19', 'cat' => 'Vegetable', 'desc' => 'Khira and lemon loads', 'qty' => 1.00, 'unit' => 'KG', 'price' => 314.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-20', 'cat' => 'Bakery', 'desc' => 'Pizza base batch', 'qty' => 1.00, 'unit' => 'PKT', 'price' => 50.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-20', 'cat' => 'Dairy', 'desc' => 'Milk & Curd packages', 'qty' => 1.00, 'unit' => 'L', 'price' => 952.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-20', 'cat' => 'Frozen / Cold Items', 'desc' => 'Ice Bags', 'qty' => 10.00, 'unit' => 'PKT', 'price' => 25.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-20', 'cat' => 'Vegetable', 'desc' => 'Onion and potato weights', 'qty' => 1.00, 'unit' => 'KG', 'price' => 171.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-21', 'cat' => 'Dairy', 'desc' => 'Milk lines supply', 'qty' => 9.00, 'unit' => 'L', 'price' => 56.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-21', 'cat' => 'Vegetable', 'desc' => 'Salad root greens', 'qty' => 1.00, 'unit' => 'KG', 'price' => 128.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-22', 'cat' => 'Bakery', 'desc' => 'Bread pack resets', 'qty' => 1.00, 'unit' => 'PKT', 'price' => 60.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-22', 'cat' => 'Dairy', 'desc' => 'Curd pack distributions', 'qty' => 1.00, 'unit' => 'KG', 'price' => 734.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-22', 'cat' => 'Frozen / Cold Items', 'desc' => 'Ice cubes wholesale pack', 'qty' => 20.00, 'unit' => 'PKT', 'price' => 25.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-22', 'cat' => 'Vegetable', 'desc' => 'Mandi loaders', 'qty' => 1.00, 'unit' => 'KG', 'price' => 165.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-24', 'cat' => 'Frozen / Cold Items', 'desc' => 'Ice bags stock', 'qty' => 10.00, 'unit' => 'PKT', 'price' => 25.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-24', 'cat' => 'Grocery', 'desc' => 'Flour & spices batch', 'qty' => 1.00, 'unit' => 'KG', 'price' => 549.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-24', 'cat' => 'Non Veg', 'desc' => 'Poultry meat items', 'qty' => 1.00, 'unit' => 'KG', 'price' => 637.00, 'vendor' => 'Blinkit'],
        ['date' => '2026-06-25', 'cat' => 'Bakery', 'desc' => 'Bread additions', 'qty' => 1.00, 'unit' => 'PKT', 'price' => 50.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-25', 'cat' => 'Dairy', 'desc' => 'Paneer pack resets', 'qty' => 1.00, 'unit' => 'KG', 'price' => 784.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-25', 'cat' => 'Frozen / Cold Items', 'desc' => 'Ice block bags', 'qty' => 10.00, 'unit' => 'PKT', 'price' => 25.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-25', 'cat' => 'Vegetable', 'desc' => 'Tomato bulk packs', 'qty' => 1.00, 'unit' => 'KG', 'price' => 125.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-26', 'cat' => 'Bakery', 'desc' => 'Special baking items', 'qty' => 1.00, 'unit' => 'PKT', 'price' => 50.00, 'vendor' => 'Blinkit'],
        ['date' => '2026-06-26', 'cat' => 'Bakery', 'desc' => 'Bread loaf loaders', 'qty' => 1.00, 'unit' => 'PKT', 'price' => 60.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-26', 'cat' => 'Dairy', 'desc' => 'Cheese slice pack', 'qty' => 1.00, 'unit' => 'PKT', 'price' => 207.00, 'vendor' => 'Blinkit'],
        ['date' => '2026-06-26', 'cat' => 'Dairy', 'desc' => 'Milk & Cream allocations', 'qty' => 1.00, 'unit' => 'L', 'price' => 1161.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-26', 'cat' => 'Frozen / Cold Items', 'desc' => 'Ice Bags retail stack', 'qty' => 25.00, 'unit' => 'PKT', 'price' => 25.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-26', 'cat' => 'Frozen / Cold Items', 'desc' => 'Cold storage sourcing lines', 'qty' => 1.00, 'unit' => 'PCS', 'price' => 4795.00, 'vendor' => 'shopper paradise'],
        ['date' => '2026-06-26', 'cat' => 'Fruits/ dry fruits', 'desc' => 'Fruit inventory loading', 'qty' => 1.00, 'unit' => 'KG', 'price' => 850.00, 'vendor' => 'Aunty ji vendor'],
        ['date' => '2026-06-26', 'cat' => 'Grocery', 'desc' => 'Dry store essentials package', 'qty' => 1.00, 'unit' => 'KG', 'price' => 9284.00, 'vendor' => 'Aunty ji vendor'],
        ['date' => '2026-06-26', 'cat' => 'Grocery', 'desc' => 'Salt and grain adjustments', 'qty' => 1.00, 'unit' => 'KG', 'price' => 225.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-26', 'cat' => 'Other', 'desc' => 'Kitchen maintenance components', 'qty' => 1.00, 'unit' => 'PCS', 'price' => 1934.00, 'vendor' => 'Aunty ji vendor'],
        ['date' => '2026-06-26', 'cat' => 'Vegetable', 'desc' => 'Mandi complete root crop fill', 'qty' => 1.00, 'unit' => 'KG', 'price' => 3103.00, 'vendor' => 'Aunty ji vendor'],
        ['date' => '2026-06-26', 'cat' => 'Vegetable', 'desc' => 'Lemons and spice leaves', 'qty' => 1.00, 'unit' => 'KG', 'price' => 258.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-27', 'cat' => 'Dairy', 'desc' => 'Milk package distributions', 'qty' => 1.00, 'unit' => 'L', 'price' => 450.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-27', 'cat' => 'Vegetable', 'desc' => 'Onion stacks', 'qty' => 1.00, 'unit' => 'KG', 'price' => 210.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-28', 'cat' => 'Bakery', 'desc' => 'Pizza base loaders', 'qty' => 1.00, 'unit' => 'PKT', 'price' => 60.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-28', 'cat' => 'Dairy', 'desc' => 'Paneer pack adjustments', 'qty' => 1.00, 'unit' => 'KG', 'price' => 972.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-28', 'cat' => 'Frozen / Cold Items', 'desc' => 'Ice Bags retail', 'qty' => 10.00, 'unit' => 'PKT', 'price' => 25.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-28', 'cat' => 'Vegetable', 'desc' => 'Tomato load resets', 'qty' => 1.00, 'unit' => 'KG', 'price' => 258.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-29', 'cat' => 'Dairy', 'desc' => 'Milk distribution setups', 'qty' => 6.00, 'unit' => 'L', 'price' => 56.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-29', 'cat' => 'Fruits/ dry fruits', 'desc' => 'Mango and banana bulk box', 'qty' => 1.00, 'unit' => 'KG', 'price' => 320.00, 'vendor' => 'Monday market'],
        ['date' => '2026-06-29', 'cat' => 'Grocery', 'desc' => 'Rice katta package & grains', 'qty' => 1.00, 'unit' => 'KG', 'price' => 1185.00, 'vendor' => 'Monday market'],
        ['date' => '2026-06-29', 'cat' => 'Other', 'desc' => 'Kitchen packing tape / markers', 'qty' => 1.00, 'unit' => 'PCS', 'price' => 60.00, 'vendor' => 'Monday market'],
        ['date' => '2026-06-29', 'cat' => 'Vegetable', 'desc' => 'Monday market wholesale vegetable allocations', 'qty' => 1.00, 'unit' => 'KG', 'price' => 5074.00, 'vendor' => 'Monday market'],
        ['date' => '2026-06-29', 'cat' => 'Vegetable', 'desc' => 'Ginger root topups', 'qty' => 1.00, 'unit' => 'KG', 'price' => 189.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-30', 'cat' => 'Bakery', 'desc' => 'Bread package runs', 'qty' => 1.00, 'unit' => 'PKT', 'price' => 60.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-30', 'cat' => 'Dairy', 'desc' => 'Curd tubs batch', 'qty' => 2.00, 'unit' => 'KG', 'price' => 192.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-30', 'cat' => 'Frozen / Cold Items', 'desc' => 'Ice cube retail load', 'qty' => 10.00, 'unit' => 'KG', 'price' => 25.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-30', 'cat' => 'Fruits/ dry fruits', 'desc' => 'Sweets and dynamic fruit sync', 'qty' => 1.00, 'unit' => 'PCS', 'price' => 70.00, 'vendor' => 'jodhpur misthan '],
        ['date' => '2026-06-30', 'cat' => 'Grocery', 'desc' => 'Store stock adjustments', 'qty' => 1.00, 'unit' => 'PCS', 'price' => 4018.00, 'vendor' => 'Metro'],
        ['date' => '2026-06-30', 'cat' => 'Grocery', 'desc' => 'Maida bags & spices', 'qty' => 1.00, 'unit' => 'KG', 'price' => 324.00, 'vendor' => 'Raju'],
        ['date' => '2026-06-30', 'cat' => 'Non Veg', 'desc' => 'End of month wholesale chicken & mutton supply', 'qty' => 1.00, 'unit' => 'KG', 'price' => 20260.00, 'vendor' => 'Arshad meat'],
        ['date' => '2026-06-30', 'cat' => 'Other', 'desc' => 'Blinkit logistics addition', 'qty' => 1.00, 'unit' => 'PCS', 'price' => 2961.00, 'vendor' => 'blinkit'],
        ['date' => '2026-06-30', 'cat' => 'Sweets', 'desc' => 'Mithai distribution pieces', 'qty' => 1.00, 'unit' => 'PCS', 'price' => 70.00, 'vendor' => 'jodhpur misthan '],
        ['date' => '2026-06-30', 'cat' => 'Vegetable', 'desc' => 'Garlic block loaders', 'qty' => 1.00, 'unit' => 'KG', 'price' => 280.00, 'vendor' => 'Raju']
    ];

    foreach ($kitchenExpenses as $k) {
        $kitchenInsert->execute([
            ':date'   => $k['date'],
            ':cat'    => $k['cat'],
            ':desc'   => $k['desc'],
            ':qty'    => $k['qty'],
            ':unit'   => $k['unit'],
            ':price'  => $k['price'],
            ':vendor' => $k['vendor']
        ]);
    }
    echo "✔ Itemized kitchen payouts successfully migrated directly to native table fields.<br>";

    // 4. Populate Every Single Row from the Farm Operational Expense Sheet
    $farmExpenses = [
        ['date' => '2026-06-03', 'cat' => 'Utility', 'desc' => 'Tata Play Network renewal', 'vendor' => 'Telecom Partner', 'amount' => 450.00, 'mode' => 'UPI'],
        ['date' => '2026-06-04', 'cat' => 'Salaries', 'desc' => 'Staff operational allowance (Abijeet)', 'vendor' => 'Abijeet', 'amount' => 23000.00, 'mode' => 'Bank Transfer'],
        ['date' => '2026-06-05', 'cat' => 'Rations', 'desc' => 'Staff Basmati Rice sack', 'vendor' => 'Kirana Wholesale', 'amount' => 2450.00, 'mode' => 'Cash'],
        ['date' => '2026-06-05', 'cat' => 'Salaries', 'desc' => 'Staff management compensation (Samar)', 'vendor' => 'Samar', 'amount' => 14000.00, 'mode' => 'Bank Transfer'],
        ['date' => '2026-06-05', 'cat' => 'Salaries', 'desc' => 'Staff kitchen setup tier (Pranay)', 'vendor' => 'Pranay', 'amount' => 13000.00, 'mode' => 'Bank Transfer'],
        ['date' => '2026-06-06', 'cat' => 'Salaries', 'desc' => 'Staff housekeeping allotment (Kinkar)', 'vendor' => 'Kinkar', 'amount' => 13000.00, 'mode' => 'Bank Transfer'],
        ['date' => '2026-06-06', 'cat' => 'Salaries', 'desc' => 'Staff security infrastructure (Subrata)', 'vendor' => 'Subrata', 'amount' => 25000.00, 'mode' => 'Bank Transfer'],
        ['date' => '2026-06-07', 'cat' => 'Utility', 'desc' => 'Property lighting & electrical line sync', 'vendor' => 'Light Agency', 'amount' => 20000.00, 'mode' => 'UPI'],
        ['date' => '2026-06-14', 'cat' => 'Maintenance', 'desc' => 'Swimming Pool Chlorine & clean items', 'vendor' => 'Pool Care Care', 'amount' => 3200.00, 'mode' => 'UPI'],
        ['date' => '2026-06-20', 'cat' => 'Maintenance', 'desc' => 'Electrician maintenance wiring lines', 'vendor' => 'Hardware Store', 'amount' => 1850.00, 'mode' => 'Cash'],
        ['date' => '2026-06-29', 'cat' => 'Utility', 'desc' => 'Commercial LPG Cylinders bundle pack (5)', 'vendor' => 'Gas Agency', 'amount' => 9000.00, 'mode' => 'UPI'],
        ['date' => '2026-06-30', 'cat' => 'Utility', 'desc' => 'Commercial LPG Cylinder single line topup', 'vendor' => 'Gas Agency', 'amount' => 946.00, 'mode' => 'UPI']
    ];

    foreach ($farmExpenses as $f) {
        $farmInsert->execute([
            ':date'   => $f['date'],
            ':desc'   => $f['desc'],
            ':amount' => $f['amount'],
            ':mode'   => $f['mode'],
            ':vendor' => $f['vendor'],
            ':cat'    => $f['cat']
        ]);
    }
    echo "✔ Farm maintenance costs and salary ledgers seeded successfully.<br>";
    echo "<h3>🎉 Database migration complete! Refresh Business Analytics to check live data.</h3>";

} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage();
}