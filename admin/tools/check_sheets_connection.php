<?php
// ========== admin/tools/check_sheets_connection.php ==========
// <?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user'])) {
    die(json_encode(['success' => false, 'error' => 'غير مصرح']));
}

$SHEET_URL = 'https://docs.google.com/spreadsheets/d/e/2PACX-1vQwZKxSN1xw1hjbn6MY2eB-7cmG-S_AS11MswrhqkOoq8ALcECXf3EKb2ejFMRwQ80-7ds4_IPK90C8/pub?output=csv';

try {
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'user_agent' => 'Mozilla/5.0'
        ]
    ]);
    
    $csvContent = @file_get_contents($SHEET_URL, false, $context);
    
    if (!$csvContent) {
        throw new Exception('لا يمكن الاتصال بـ Google Sheets');
    }
    
    $lines = explode("\n", $csvContent);
    $rowCount = count(array_filter($lines, 'strlen')) - 1; // ناقص العناوين
    
    echo json_encode([
        'success' => true,
        'rows_count' => $rowCount,
        'last_update' => date('Y-m-d H:i:s'),
        'sheet_size' => strlen($csvContent) . ' bytes'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
// ?>



