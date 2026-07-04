<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// CRITICAL CONFIGURATION: Put your shared personal Google Drive Folder ID right here!
define('TARGET_DRIVE_FOLDER_ID', '1kkjEFopdAXP0xFT2AprTjPpwzWpXBdLb');
define('PERSONAL_GOOGLE_EMAIL', 'artistic.sthan@gmail.com'); //

/**
 * CUSTOM LOGGING TRACKER
 * Forces error tracking parameters into a custom file even if cPanel logging is disabled.
 */
function logGoogleSheetsDebug($message, $data = null) { //
    $logFile = __DIR__ . '/google_sheets_debug.log'; //
    $timestamp = date('[Y-m-d H:i:s]'); //
    $logMessage = $timestamp . " " . $message; //
    if ($data !== null) { //
        $logMessage .= " | Data: " . (is_array($data) || is_object($data) ? json_encode($data) : $data); //
    }
    $logMessage .= "\n"; //
    file_put_contents($logFile, $logMessage, FILE_APPEND); //
}

/**
 * Global base64 URL safe encoder helper.
 */
function base64UrlEncode($data) { //
    return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data)); //
}

/**
 * Requests a secure OAuth2 bearer token string from Google using your Service Account JSON key asset.
 */
function getGoogleAccessToken() { //
    $jsonKeyPath = __DIR__ . '/credentials/google_service_account.json'; //
    if (!file_exists($jsonKeyPath)) { //
        logGoogleSheetsDebug("CRITICAL ERROR: Service account JSON key file missing at: " . $jsonKeyPath); //
        return false; //
    }

    $keyData = json_decode(file_get_contents($jsonKeyPath), true); //
    $clientEmail = $keyData['client_email'] ?? ''; //
    $privateKey = $keyData['private_key'] ?? ''; //

    if (empty($clientEmail) || empty($privateKey)) { //
        logGoogleSheetsDebug("CRITICAL ERROR: Invalid service account JSON properties. Check your key file content."); //
        return false; //
    }

    $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']); //
    $now = time(); //
    $payload = json_encode([ //
        'iss' => $clientEmail, //
        'scope' => 'https://www.googleapis.com/auth/spreadsheets https://www.googleapis.com/auth/drive', //
        'aud' => 'https://oauth2.googleapis.com/token', //
        'exp' => $now + 3600, //
        'iat' => $now //
    ]);

    $assertionString = base64UrlEncode($header) . '.' . base64UrlEncode($payload); //
    if (!openssl_sign($assertionString, $signature, $privateKey, 'SHA256')) { //
        logGoogleSheetsDebug("CRITICAL ERROR: OpenSSL private key signature generation failed."); //
        return false; //
    }
    $jwt = $assertionString . '.' . base64UrlEncode($signature); //

    $ch = curl_init('https://oauth2.googleapis.com/token'); //
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); //
    curl_setopt($ch, CURLOPT_POST, true); //
    curl_setopt($ch, CURLOPT_POSTFIELDS, [ //
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', //
        'assertion' => $jwt //
    ]);
    $responseRaw = curl_exec($ch); //
    $tokenResponse = json_decode($responseRaw, true); //
    curl_close($ch); //

    if (!isset($tokenResponse['access_token'])) { //
        logGoogleSheetsDebug("OAUTH FAILURE: Google rejected service account JWT token.", $responseRaw); //
        return false; //
    }

    return $tokenResponse['access_token']; //
}

/**
 * AUTOMATED WORKBOOK BUILDER SHORTCUT
 * Spawns a new monthly spreadsheet natively inside your shared target personal drive folder.
 */
function createNewMonthlyWorkbook($monthYearLabel, $accessToken) { //
    try {
        $workbookTitle = "Farm_Ledger_" . date('M_Y'); //
        logGoogleSheetsDebug("Attempting to create an authorized monthly workbook titled: " . $workbookTitle); //
        
        // Step A: Spawn Document directly under your shared parent folder location to pass authentication policies
        $url = 'https://www.googleapis.com/drive/v3/files';
        $payload = json_encode([
            'name' => $workbookTitle,
            'mimeType' => 'application/vnd.google-apps.spreadsheet',
            'parents' => [TARGET_DRIVE_FOLDER_ID]
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ]);
        $responseRaw = curl_exec($ch);
        $response = json_decode($responseRaw, true);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            logGoogleSheetsDebug("WORKBOOK METADATA CREATION REJECTED: HTTP " . $httpCode, $responseRaw);
            return false;
        }

        $newSpreadsheetId = $response['id'] ?? '';
        
        if (!empty($newSpreadsheetId)) {
            logGoogleSheetsDebug("Base Spreadsheet File Formed! ID: " . $newSpreadsheetId . ". Spawning worksheets...");

            // Step B: Initialize required operational tab structural profiles
            $sheetsUrl = 'https://sheets.googleapis.com/v4/spreadsheets/' . $newSpreadsheetId . ':batchUpdate';
            $sheetsPayload = json_encode([
                'requests' => [
                    ['updateSpreadsheetProperties' => ['properties' => ['title' => $workbookTitle], 'fields' => 'title']],
                    ['addSheet' => ['properties' => ['title' => 'Farm booking and food']]],
                    ['addSheet' => ['properties' => ['title' => 'Kitchen Exp']]],
                    ['addSheet' => ['properties' => ['title' => 'Farm Exp ']]]
                ]
            ]);

            $ch = curl_init($sheetsUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $sheetsPayload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ]);
            curl_exec($ch);
            curl_close($ch);

            // Step C: Inject structural matrix headers
            $headersMap = [ //
                'Farm booking and food' => ['Sr No.', 'Booking Source', 'Contact No.', 'No. of Guest', 'Check-in Date', 'Check-out Date', 'Total Days', 'Per Night Charges', 'Total Charge', 'Remark', 'Advance Paid', 'Received by', 'Pending Amount', 'Received by', 'Total Food', 'Received by', 'Remark', 'Decoration', 'Tip'], //
                'Kitchen Exp'           => ['Sr No.', 'Date', 'Category', 'Description', 'Qty', 'Unit', 'Price', 'Total', 'Vendor'], //
                'Farm Exp '             => ['Sr No.', 'Date', 'Description', 'Amount', 'Payment Mode', 'Vendor', 'Remark'] //
            ]; //

            foreach ($headersMap as $tabName => $headerCols) { //
                $appendUrl = 'https://sheets.googleapis.com/v4/spreadsheets/' . $newSpreadsheetId . '/values/' . urlencode($tabName . '!A1') . '?valueInputOption=USER_ENTERED'; //
                $ch = curl_init($appendUrl); //
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); //
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT'); //
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['values' => [$headerCols]])); //
                curl_setopt($ch, CURLOPT_HTTPHEADER, [ //
                    'Authorization: Bearer ' . $accessToken, //
                    'Content-Type: application/json' //
                ]); //
                curl_exec($ch); //
                curl_close($ch); //
            } //

            return $newSpreadsheetId; //
        }
    } catch (Exception $e) { //
        logGoogleSheetsDebug("EXCEPTION IN AUTOMATED GENERATOR NEST: " . $e->getMessage()); //
    } //
    return false; //
}

/**
 * Automatically calculates and retrieves the exact active Google Spreadsheet ID for the current month.
 */
function getActiveMonthlySpreadsheetId($accessToken) { //
    global $pdo; //
    $currentMonthYear = date('m-Y'); //
    
    $stmt = $pdo->prepare("SELECT spreadsheet_id FROM google_monthly_workbooks WHERE log_month_year = ? LIMIT 1"); //
    $stmt->execute([$currentMonthYear]); //
    $existingId = $stmt->fetchColumn(); //
    
    if ($existingId) { //
        return $existingId; //
    } //
    
    logGoogleSheetsDebug("No active spreadsheet registered in MySQL for month row [" . $currentMonthYear . "]. Triggering generation process."); //
    $newSheetId = createNewMonthlyWorkbook($currentMonthYear, $accessToken); //
    if ($newSheetId) { //
        $ins = $pdo->prepare("INSERT INTO google_monthly_workbooks (log_month_year, spreadsheet_id) VALUES (?, ?)"); //
        $ins->execute([$currentMonthYear, $newSheetId]); //
        return $newSheetId; //
    } //
    
    return false; //
}

/**
 * Dynamic server-to-server Google Sheets API gateway connector via cURL streams.
 */
function appendRowToGoogleSheet($sheetName, array $rowData) { //
    logGoogleSheetsDebug("----------------------------------------------------------------------"); //
    logGoogleSheetsDebug("NEW APPEND ROW TRANSACTION REQUEST INITIALIZED FOR TAB: [" . $sheetName . "]"); //
    logGoogleSheetsDebug("Data Payload:", $rowData); //

    try { //
        $accessToken = getGoogleAccessToken(); //
        if (!$accessToken) { //
            logGoogleSheetsDebug("APPEND CANCELED: Could not get valid Google OAuth Token."); //
            return false; //
        } //

        $spreadsheetId = getActiveMonthlySpreadsheetId($accessToken); //
        if (!$spreadsheetId) { //
            logGoogleSheetsDebug("APPEND CANCELED: Could not resolve target Spreadsheet ID."); //
            return false; //
        } //

        $url = 'https://sheets.googleapis.com/v4/spreadsheets/' . $spreadsheetId . '/values/' . urlencode($sheetName . '!A:A') . ':append?valueInputOption=USER_ENTERED'; //
        $payloadBody = json_encode(['values' => [$rowData]]); //

        $ch = curl_init($url); //
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); //
        curl_setopt($ch, CURLOPT_POST, true); //
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payloadBody); //
        curl_setopt($ch, CURLOPT_HTTPHEADER, [ //
            'Authorization: Bearer ' . $accessToken, //
            'Content-Type: application/json' //
        ]); //
        
        $responseRaw = curl_exec($ch); //
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); //
        curl_close($ch); //

        if ($httpCode !== 200) { //
            logGoogleSheetsDebug("ROW APPEND ACTION REJECTED BY GOOGLE SHEETS API. HTTP Code: " . $httpCode, $responseRaw); //
            return false; //
        } //

        logGoogleSheetsDebug("SUCCESS: Row entries successfully appended to Google cloud spreadsheet workbook!"); //
        return true; //
    } catch (Exception $e) { //
        logGoogleSheetsDebug("CRITICAL UNCAUGHT EXCEPTION TRACE DETECTED: " . $e->getMessage()); //
        return false; //
    } //
}