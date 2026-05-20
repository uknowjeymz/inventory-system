<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

require_once '../vendor/autoload.php';
require_once '../config/database.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$database = new Database();
$db = $database->getConnection();

if ($_POST && isset($_POST['action']) && $_POST['action'] === 'upload_csv') {
    $equipment_type = $_POST['equipment_type'];
    $has_header = isset($_POST['has_header']);
    $target_campus = $_POST['target_campus'] ?? 'Main Campus';
    
    try {
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("File upload failed");
        }
        
        $file_path = $_FILES['csv_file']['tmp_name'];
        $file_ext = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));
        $rows = [];

        if ($file_ext === 'xlsx') {
            $spreadsheet = IOFactory::load($file_path);
            $selected_sheet = $_POST['selected_sheet'] ?? '';
            $worksheet = (!empty($selected_sheet)) ? $spreadsheet->getSheetByName($selected_sheet) : $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();
        } else {
            $file_handle = fopen($file_path, 'r');
            while (($data = fgetcsv($file_handle)) !== FALSE) { $rows[] = $data; }
            fclose($file_handle);
        }

        $table_configs = [
            'computer' => [
                'table' => 'computer_inventory', 
                'name_field' => 'article', 
                // MATCHES EXCEL: A:Description, B:Processor, C:RAM, D:Storage, E:DeviceType, F:OS, G:Serial, H:Campus, I:Amount
                'fields' => [
                    'computer_set_description', // Column A
                    'processor',                // Column B
                    'ram',                      // Column C
                    'storage',                  // Column D
                    'device_type',              // Column E
                    'operating_system',         // Column F
                    'serial_number',            // Column G
                    'campus',                   // Column H
                    'cost',                     // Column I
                    'condition_status',         // Column J
                    'remarks'                   // Column K
                ]
            ],
            'kitchen' => ['table' => 'kitchen_equipment', 'name_field' => 'equipment_name', 'fields' => ['equipment_name', 'brand', 'model', 'serial_number', 'capacity', 'power_rating', 'condition_status', 'remarks']],
            'office'  => ['table' => 'office_equipment', 'name_field' => 'equipment_name', 'fields' => ['equipment_name', 'brand', 'model', 'serial_number', 'specifications', 'condition_status', 'remarks']],
            'lab'     => ['table' => 'lab_equipment', 'name_field' => 'equipment_name', 'fields' => ['equipment_name', 'brand', 'model', 'serial_number', 'specifications', 'calibration_date', 'condition_status', 'remarks']],
            'general' => ['table' => 'general_equipment', 'name_field' => 'article', 'fields' => ['article', 'description', 'property_no', 'cost', 'condition_status', 'remarks']]
        ];

        $config = $table_configs[$equipment_type];
        $success_count = 0; $error_count = 0; $errors = [];

        if ($has_header) array_shift($rows);

        foreach ($rows as $index => $data) {
            try {
                // Skip empty rows or rows that look like extra headers
                $first_cell = trim($data[0] ?? '');
                if (empty($first_cell) || in_array(strtoupper($first_cell), ['DESCRIPTION', 'ARTICLE', 'ITEM #', 'NAME'])) {
                    continue; 
                }

                $values = [];
                foreach ($config['fields'] as $keyIndex => $fieldName) {
                    $values[$fieldName] = isset($data[$keyIndex]) ? trim($data[$keyIndex]) : null;
                }

                // --- AUTOMATED ARTICLE & PREFIX LOGIC ---
                // If the Excel doesn't have an 'article' column, we assign a default based on type
                if (!isset($values['article']) || empty($values['article'])) {
                    if ($equipment_type === 'computer') {
                        $values['article'] = 'Computer';
                    } else {
                        // Fallback to a generic term or the first word of the description/name
                        $values['article'] = 'Item';
                    }
                }

                // --- COST CLEANING ---
                // Transforms "₱68,640.00" into "68640.00" for decimal database columns
                if (isset($values['cost'])) {
                    $values['cost'] = preg_replace('/[^0-9.]/', '', $values['cost']);
                }

                // --- AUTOMATED ITEM NUMBER GENERATION ---
                // Extract first 3 letters of the article for the prefix
                $raw_name = $values['article'];
                $prefix = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $raw_name), 0, 3));
                
                // Query the database to find the highest existing suffix for this specific prefix
                $num_stmt = $db->prepare("SELECT item_number FROM {$config['table']} WHERE item_number LIKE ? ORDER BY id DESC LIMIT 1");
                $num_stmt->execute([$prefix . '-%']);
                $last_item = $num_stmt->fetch(PDO::FETCH_ASSOC);

                if ($last_item) {
                    // Extract the numeric part (e.g., get 12 from COM-012)
                    $parts = explode('-', $last_item['item_number']);
                    $last_num = (int)end($parts);
                    $next_num = $last_num + 1;
                } else {
                    $next_num = 1;
                }

                // Combine into the automated format: XXX-000
                $values['item_number'] = $prefix . '-' . str_pad($next_num, 3, '0', STR_PAD_LEFT);
                
                // Standard default fields
                $values['status'] = 'available';
                $values['is_condemned'] = 0;
                if (empty($values['campus'])) {
                    $values['campus'] = $target_campus;
                }
                
                // Prepare and execute the Insert
                $field_names = array_keys($values);
                $placeholders = array_fill(0, count($values), '?');
                
                $sql = "INSERT INTO {$config['table']} (" . implode(', ', $field_names) . ", created_at, updated_at) 
                        VALUES (" . implode(', ', $placeholders) . ", NOW(), NOW())";
                
                $stmt = $db->prepare($sql);
                $stmt->execute(array_values($values));
                
                $success_count++;

            } catch (Exception $e) {
                $error_count++;
                $errors[] = "Row " . ($index + 1) . ": " . $e->getMessage();
            }
        }

        $_SESSION['csv_success'] = "Import Complete: $success_count items added.";
        if ($error_count > 0) $_SESSION['csv_errors'] = $errors;

    } catch (Exception $e) {
        $_SESSION['csv_error'] = $e->getMessage();
    }
}
header("Location: all_equipment.php");
exit();