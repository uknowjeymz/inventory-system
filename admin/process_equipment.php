<?php
session_start();
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

if ($_POST && isset($_POST['action']) && $_POST['action'] === 'add_equipment') {
    $equipment_type = $_POST['equipment_type'];
    
    try {
        $table_configs = [
            'computer' => [
                'table' => 'computer_inventory',
                'name_field' => 'article',
                'required_fields' => ['article', 'computer_set_description', 'processor', 'ram', 'storage', 'unit', 'property_no', 'campus'],
                'status_col' => 'status'
            ],
            'kitchen' => [
                'table' => 'kitchen_equipment', 
                'name_field' => 'equipment_name', 
                'required_fields' => ['equipment_name', 'unit', 'campus'], 
                'status_col' => 'status'
            ],
            'office' => [
                'table' => 'office_equipment', 
                'name_field' => 'equipment_name', 
                'required_fields' => ['equipment_name', 'unit', 'campus'], 
                'status_col' => 'status'
            ],
            'lab' => [
                'table' => 'lab_equipment', 
                'name_field' => 'equipment_name', 
                'required_fields' => ['equipment_name', 'unit', 'campus'], 
                'status_col' => 'status'
            ],
            'general' => [
                'table' => 'general_equipment', 
                'name_field' => 'article', 
                'required_fields' => ['article', 'unit', 'property_no', 'campus'], 
                'status_col' => 'status'
            ]
        ];
        
        if (!isset($table_configs[$equipment_type])) {
            throw new Exception("Invalid equipment type: " . $equipment_type);
        }
        
        $config = $table_configs[$equipment_type];
        $table_name = $config['table'];

        // --- STEP 1: GENERATE AUTOMATED ITEM NUMBER ---
        $raw_name = $_POST[$config['name_field']];
        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $raw_name), 0, 3));
        
        $num_stmt = $db->prepare("SELECT item_number FROM {$table_name} WHERE item_number LIKE ? ORDER BY id DESC LIMIT 1");
        $num_stmt->execute([$prefix . '-%']);
        $last_item = $num_stmt->fetch(PDO::FETCH_ASSOC);

        $next_num = 1;
        if ($last_item) {
            $parts = explode('-', $last_item['item_number']);
            $next_num = (int)end($parts) + 1;
        }
        $automated_item_number = $prefix . '-' . str_pad($next_num, 3, '0', STR_PAD_LEFT);
        
        // --- STEP 2: DATA MAPPING & LOGIC ---

        // Handle Unit field for ALL equipment types
        if (isset($_POST['unit']) && !empty($_POST['unit'])) {
            $_POST['unit'] = trim($_POST['unit']);
        } else {
            $_POST['unit'] = 'unit'; // Default to 'unit' if not specified
        }

        // Handle Property Number for ALL types
        if (isset($_POST['property_no'])) {
            $_POST['property_no'] = trim($_POST['property_no']);
        }

        // Handle Purchase Date properly
        if (isset($_POST['purchase_date_option'])) {
            unset($_POST['purchase_date_option']);
        }

        if (isset($_POST['purchase_date'])) {
            if ($_POST['purchase_date'] === '' || $_POST['purchase_date'] === null) {
                $_POST['purchase_date'] = null;
            }
        }

        // --- HANDLE ACCOUNTABLE PERSON NAME (remarks field) ---
        if (isset($_POST['accountable_lastname']) && trim($_POST['accountable_lastname']) !== '') {
            $lastname = trim($_POST['accountable_lastname']);
            $firstname = trim($_POST['accountable_firstname'] ?? '');
            $middle = trim($_POST['accountable_middle'] ?? '');
            
            $full_name = $lastname;
            if (!empty($firstname)) {
                $full_name .= ', ' . $firstname;
            }
            if (!empty($middle)) {
                $full_name .= ' ' . $middle;
            }
            
            $_POST['remarks'] = $full_name;
            
            unset($_POST['accountable_lastname']);
            unset($_POST['accountable_firstname']);
            unset($_POST['accountable_middle']);
        } else {
            if (!isset($_POST['remarks']) || $_POST['remarks'] === '') {
                $_POST['remarks'] = '';
            }
        }

        if ($equipment_type === 'computer') {
            if (isset($_POST['article']) && $_POST['article'] === 'Computer Package') {
                $_POST['serial_number_monitor'] = trim($_POST['serial_monitor'] ?? 'N/A');
                $_POST['serial_number_system'] = trim($_POST['serial_system'] ?? 'N/A');
                $_POST['device_type'] = 'Desktop';
                $_POST['serial_number'] = $_POST['property_no']; 
                
                unset($_POST['serial_monitor']);
                unset($_POST['serial_system']);
            } else {
                $_POST['device_type'] = $_POST['article'] ?? '';
                $_POST['serial_number_monitor'] = null;
                $_POST['serial_number_system'] = null;
                if (empty($_POST['serial_number']) && isset($_POST['property_no'])) {
                    $_POST['serial_number'] = $_POST['property_no'];
                }
            }
        } else {
            if (isset($_POST['serial_number'])) {
                $_POST['serial_number'] = trim($_POST['serial_number']);
            }
            
            if (isset($_POST['property_no'])) {
                $_POST['property_no'] = trim($_POST['property_no']);
            }
            
            if (isset($_POST['cost']) && $_POST['cost'] !== '') {
                $_POST['cost'] = floatval($_POST['cost']);
            }
        }

        // Validate required fields
        foreach ($config['required_fields'] as $field) {
            if (!isset($_POST[$field]) || (empty($_POST[$field]) && $_POST[$field] !== '0')) {
                throw new Exception("Missing required field: " . ucfirst(str_replace('_', ' ', $field)));
            }
        }

        // --- HANDLE IMAGE UPLOAD ---
        $image_path = null;
        if (isset($_FILES['equipment_photo']) && $_FILES['equipment_photo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/equipment/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $file_ext = strtolower(pathinfo($_FILES['equipment_photo']['name'], PATHINFO_EXTENSION));
            $file_name = $equipment_type . '_' . time() . '_' . rand(1000, 9999) . '.' . $file_ext;
            if (move_uploaded_file($_FILES['equipment_photo']['tmp_name'], $upload_dir . $file_name)) {
                $image_path = 'uploads/equipment/' . $file_name;
            }
        }
        
        // --- STEP 3: BUILD INSERT QUERY ---
        // Exclude internal post keys
        $exclude_keys = ['action', 'equipment_type', 'item_number', 'purchase_date_option'];

        // Fields that are foreign keys or nullable integers — empty string must become NULL
        $nullable_int_fields = ["location_id", "assigned_to", "condemned_by", "maintenance_resolved_by"];

        $fields = [];
        $values = [];

        // DEBUG: Print all POST data
        error_log("POST data: " . print_r($_POST, true));

        // First, add all POST data
        foreach ($_POST as $key => $value) {
            if (in_array($key, $exclude_keys)) continue;
            
            $fields[] = $key;
            if (in_array($key, $nullable_int_fields)) {
                $values[] = ($value === null || $value === "" || $value === "0") ? null : (int)$value;
            } else {
                $values[] = ($value === null || $value === "") ? null : trim($value);
            }
        }

        // Add required fields that might not be in POST
        $fields[] = 'item_number';
        $values[] = $automated_item_number;

        // Check if status field is already included
        $status_field = $config['status_col'];
        if (!in_array($status_field, $fields)) {
            $fields[] = $status_field;
            $values[] = 'available';
        }

        // Add image path if uploaded
        if ($image_path) {
            $fields[] = 'image_path';
            $values[] = $image_path;
        }

        // Add is_condemned field
        $fields[] = 'is_condemned';
        $values[] = 0;

        // DEBUG: Print fields and values before adding timestamps
        error_log("Fields before timestamps: " . print_r($fields, true));
        error_log("Values count before timestamps: " . count($values));
        error_log("Values before timestamps: " . print_r($values, true));

        // Add created_at and updated_at
        $current_time = date('Y-m-d H:i:s');
        $fields[] = 'created_at';
        $fields[] = 'updated_at';
        $values[] = $current_time;
        $values[] = $current_time;

        // DEBUG: Print final fields and values
        error_log("Final fields: " . print_r($fields, true));
        error_log("Final values count: " . count($values));
        error_log("Final values: " . print_r($values, true));
        error_log("Fields count: " . count($fields));
        error_log("Values count: " . count($values));

        // Re-index to eliminate any key gaps caused by unset() on $_POST entries
        $values = array_values($values);

        // Create placeholders based on the FINAL count of values
        $placeholders = array_fill(0, count($values), '?');

        // Build the query
        $query = "INSERT INTO {$table_name} (" . implode(', ', $fields) . ") 
                  VALUES (" . implode(', ', $placeholders) . ")";

        error_log("Final query: " . $query);
        error_log("Number of placeholders: " . count($placeholders));
        error_log("Number of values: " . count($values));

        $stmt = $db->prepare($query);
        $stmt->execute($values);
        
        $_SESSION['equipment_success'] = ucfirst($equipment_type) . " added successfully! Item Number: " . $automated_item_number;
        
    } catch (Exception $e) {
        $_SESSION['equipment_error'] = "Error adding equipment: " . $e->getMessage();
        error_log("Exception: " . $e->getMessage());
    }
}

header("Location: all_equipment.php");
exit();