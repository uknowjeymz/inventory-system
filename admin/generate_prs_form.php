<?php
session_start();
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    die("Unauthorized access");
}

require_once '../vendor/autoload.php';
require_once '../config/database.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

$database = new Database();
$db = $database->getConnection();

// Get selected IDs from POST
$selected_ids = isset($_POST['selected_ids']) ? $_POST['selected_ids'] : '';

if (empty($selected_ids)) {
    $_SESSION['error'] = "No items selected for PRS form generation.";
    header("Location: condemned.php");
    exit();
}

// Convert comma-separated IDs to array
$ids_array = explode(',', $selected_ids);
$placeholders = implode(',', array_fill(0, count($ids_array), '?'));

// Fetch selected condemned items
$query = "SELECT 
    ce.id,
    ce.model,
    ce.category,
    ce.serial_number,
    ce.equipment_type,
    ce.reason_condemned,
    ce.condemned_date,
    ce.estimated_value,
    u.full_name as condemned_by_name
FROM condemned_equipment ce 
LEFT JOIN users u ON ce.condemned_by = u.id 
WHERE ce.id IN ($placeholders)
ORDER BY ce.condemned_date DESC";

$stmt = $db->prepare($query);
$stmt->execute($ids_array);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($items)) {
    $_SESSION['error'] = "No condemned items found with the selected IDs.";
    header("Location: condemned.php");
    exit();
}

// Load the PRS-form.xlsx template
$templatePath = '../excels/PRS-form.xlsx';

if (!file_exists($templatePath)) {
    $_SESSION['error'] = "PRS form template not found.";
    header("Location: condemned.php");
    exit();
}

try {
    // Load the template
    $spreadsheet = IOFactory::load($templatePath);
    $sheet = $spreadsheet->getActiveSheet();
    
    // Starting row for data (A9 as specified)
    $startRow = 9;
    $endRow = 54; // Maximum row limit (A55 has footer data)
    $maxItems = $endRow - $startRow + 1; // 46 items maximum
    $currentRow = $startRow;
    
    // Limit items to maximum allowed rows
    if (count($items) > $maxItems) {
        $items = array_slice($items, 0, $maxItems);
        $_SESSION['warning'] = "Only the first {$maxItems} items were included in the PRS form due to space limitations.";
    }
    
    // Column mapping based on the header:
    // QTY | UNIT | DESCRIPTION | YEAR ACQUIRED | PROP NO | END-USER | UNIT VALUE | TOTAL VALUE
    // A   | B    | C           | D             | E       | F        | G          | H
    
    foreach ($items as $item) {
        // Safety check: don't exceed row 54
        if ($currentRow > $endRow) {
            break;
        }
        // Extract year from condemned_date
        $yearAcquired = $item['condemned_date'] ? date('Y', strtotime($item['condemned_date'])) : 'N/A';
        
        // QTY (Column A)
        $sheet->setCellValue('A' . $currentRow, 1);
        
        // UNIT (Column B)
        $sheet->setCellValue('B' . $currentRow, 'pc');
        
        // DESCRIPTION (Column C) - Combine model and category
        $description = $item['model'] . ' (' . $item['category'] . ')';
        if (!empty($item['reason_condemned'])) {
            $description .= ' - ' . $item['reason_condemned'];
        }
        $sheet->setCellValue('C' . $currentRow, $description);
        
        // YEAR ACQUIRED (Column D)
        $sheet->setCellValue('D' . $currentRow, $yearAcquired);
        
        // PROP NO (Column E) - Serial Number
        $sheet->setCellValue('E' . $currentRow, $item['serial_number'] ?: 'N/A');
        
        // END-USER (Column F) - Condemned by
        $sheet->setCellValue('F' . $currentRow, $item['condemned_by_name'] ?: 'N/A');
        
        // UNIT VALUE (Column G)
        $unitValue = $item['estimated_value'] > 0 ? $item['estimated_value'] : 0;
        $sheet->setCellValue('G' . $currentRow, $unitValue);
        
        // TOTAL VALUE (Column H) - QTY * UNIT VALUE
        $totalValue = 1 * $unitValue;
        $sheet->setCellValue('H' . $currentRow, $totalValue);
        
        $currentRow++;
    }
    
    // Generate filename with timestamp
    $filename = 'PRS_Form_' . date('Ymd_His') . '.xlsx';
    
    // Set headers for download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    // Write file to output
    $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save('php://output');
    exit();
    
} catch (Exception $e) {
    $_SESSION['error'] = "Error generating PRS form: " . $e->getMessage();
    header("Location: condemned.php");
    exit();
}
