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

// Get selected items from POST (format: "type:id,type:id,...")
$selected_items = isset($_POST['selected_items']) ? $_POST['selected_items'] : '';

if (empty($selected_items)) {
    $_SESSION['error'] = "No items selected for PRS form generation.";
    header("Location: all_equipment.php");
    exit();
}

// Parse the selected items
$items_array = explode(',', $selected_items);
$equipment_data = [];

// Table mapping
$table_map = [
    'computer' => 'computer_inventory',
    'computer_lab' => 'computer_inventory',
    'kitchen' => 'kitchen_equipment',
    'office' => 'office_equipment',
    'lab' => 'lab_equipment',
    'regular_lab' => 'lab_equipment',
    'general' => 'general_equipment'
];

// Fetch equipment details from different tables
foreach ($items_array as $item) {
    if (empty($item) || strpos($item, ':') === false) {
        continue;
    }
    
    list($type, $id) = explode(':', $item, 2);
    $table_name = $table_map[$type] ?? '';
    
    if (empty($table_name)) {
        continue;
    }
    
    try {
        // Determine the name column based on table
        $name_column = 'equipment_name';
        if ($table_name === 'computer_inventory') {
            $name_column = 'computer_set_description';
        } elseif ($table_name === 'general_equipment') {
            $name_column = 'article';
        }
        
        // Determine serial number column
        $serial_column = ($table_name === 'general_equipment') ? 'property_no' : 'serial_number';
        
        // Check if purchase_date column exists
        $check_column = $db->prepare("SHOW COLUMNS FROM `{$table_name}` LIKE 'purchase_date'");
        $check_column->execute();
        $has_purchase_date = $check_column->rowCount() > 0;
        
        // Build the SELECT clause - always include item_number if it exists
        $check_item_number = $db->prepare("SHOW COLUMNS FROM `{$table_name}` LIKE 'item_number'");
        $check_item_number->execute();
        $has_item_number = $check_item_number->rowCount() > 0;
        
        $select_clause = "id, {$name_column} as name, {$serial_column} as serial_number, created_at";
        
        if ($has_item_number) {
            $select_clause .= ", item_number";
        }
        
        // Check if remarks column exists
        $check_remarks = $db->prepare("SHOW COLUMNS FROM `{$table_name}` LIKE 'remarks'");
        $check_remarks->execute();
        $has_remarks = $check_remarks->rowCount() > 0;
        
        if ($has_remarks) {
            $select_clause .= ", remarks";
        }
        
        if ($has_purchase_date) {
            $select_clause .= ", purchase_date";
        }
        
        // Check if cost column exists
        $check_cost = $db->prepare("SHOW COLUMNS FROM `{$table_name}` LIKE 'cost'");
        $check_cost->execute();
        $has_cost = $check_cost->rowCount() > 0;
        
        if ($has_cost) {
            $select_clause .= ", cost";
        }
        
        // Fetch the equipment data
        $query = "SELECT {$select_clause} FROM {$table_name} WHERE id = ?";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$id]);
        $equipment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($equipment) {
            $equipment['equipment_type'] = $type;
            $equipment['table_name'] = $table_name;
            $equipment['has_purchase_date'] = $has_purchase_date;
            $equipment['has_cost'] = $has_cost;
            $equipment['has_item_number'] = $has_item_number;
            $equipment['has_remarks'] = $has_remarks;
            $equipment_data[] = $equipment;
        }
    } catch (Exception $e) {
        // Skip items that cause errors
        continue;
    }
}

if (empty($equipment_data)) {
    $_SESSION['error'] = "No valid equipment items found with the selected IDs.";
    header("Location: all_equipment.php");
    exit();
}

// Load the PRS-form.xlsx template
$templatePath = '../excels/PRS-form.xlsx';

if (!file_exists($templatePath)) {
    $_SESSION['error'] = "PRS form template not found.";
    header("Location: all_equipment.php");
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
    if (count($equipment_data) > $maxItems) {
        $equipment_data = array_slice($equipment_data, 0, $maxItems);
        $_SESSION['warning'] = "Only the first {$maxItems} items were included in the PRS form due to space limitations.";
    }
    
    // Column mapping based on the header:
    // QTY | UNIT | DESCRIPTION | YEAR ACQUIRED | PROP NO | END-USER | UNIT VALUE | TOTAL VALUE
    // A   | B    | C           | D             | E       | F        | G          | H
    
    foreach ($equipment_data as $item) {
        // Safety check: don't exceed row 54
        if ($currentRow > $endRow) {
            break;
        }
        
        // Extract year from purchase_date or created_at
        $yearAcquired = 'N/A';
        if (isset($item['has_purchase_date']) && $item['has_purchase_date'] && !empty($item['purchase_date']) && $item['purchase_date'] !== '0000-00-00') {
            $timestamp = strtotime($item['purchase_date']);
            if ($timestamp !== false && $timestamp > 0) {
                $yearAcquired = date('Y', $timestamp);
                // Ensure year is valid
                if ((int)$yearAcquired < 1900) {
                    $yearAcquired = 'N/A';
                }
            }
        } elseif (!empty($item['created_at']) && $item['created_at'] !== '0000-00-00') {
            $timestamp = strtotime($item['created_at']);
            if ($timestamp !== false && $timestamp > 0) {
                $yearAcquired = date('Y', $timestamp);
                if ((int)$yearAcquired < 1900) {
                    $yearAcquired = 'N/A';
                }
            }
        }
        // QTY (Column A)
        $sheet->setCellValue('A' . $currentRow, 1);
        
        // UNIT (Column B)
        $sheet->setCellValue('B' . $currentRow, 'pc');
        
        // DESCRIPTION (Column C) - Equipment name
        $description = $item['name'];
        if (isset($item['has_item_number']) && $item['has_item_number'] && !empty($item['item_number'])) {
            $description = $item['item_number'] . ' - ' . $description;
        }
        $sheet->setCellValue('C' . $currentRow, $description);
        
        // YEAR ACQUIRED (Column D)
        $sheet->setCellValue('D' . $currentRow, $yearAcquired);
        
        // PROP NO (Column E) - Serial Number
        $sheet->setCellValue('E' . $currentRow, $item['serial_number'] ?: 'N/A');
        
        // END-USER (Column F) - Accountable person (remarks)
        $endUser = 'N/A';
        if (isset($item['has_remarks']) && $item['has_remarks'] && !empty($item['remarks'])) {
            $endUser = $item['remarks'];
        }
        $sheet->setCellValue('F' . $currentRow, $endUser);
        
        // UNIT VALUE (Column G)
        $unitValue = 0;
        if (isset($item['has_cost']) && $item['has_cost'] && isset($item['cost'])) {
            $unitValue = $item['cost'] > 0 ? $item['cost'] : 0;
        }
        $sheet->setCellValue('G' . $currentRow, $unitValue);
        
        // TOTAL VALUE (Column H) - QTY * UNIT VALUE
        $totalValue = 1 * $unitValue;
        $sheet->setCellValue('H' . $currentRow, $totalValue);
        
        $currentRow++;
    }
    
    // Generate filename with timestamp
    $filename = 'PRS_Form_All_Equipment_' . date('Ymd_His') . '.xlsx';
    
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
    header("Location: all_equipment.php");
    exit();
}
