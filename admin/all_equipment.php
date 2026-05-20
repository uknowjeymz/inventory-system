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

// Handle equipment actions
if ($_POST && isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            case 'add_equipment':
                $type = $_POST['equipment_type'];

                // Map equipment type to table and name field for item number generation
                $table_configs = [
                    'computer' => ['table' => 'computer_inventory', 'name_field' => 'article'],
                    'kitchen'  => ['table' => 'kitchen_equipment',  'name_field' => 'equipment_name'],
                    'office'   => ['table' => 'office_equipment',   'name_field' => 'equipment_name'],
                    'lab'      => ['table' => 'lab_equipment',      'name_field' => 'equipment_name'],
                    'general'  => ['table' => 'general_equipment',  'name_field' => 'article'],
                ];
                if (!isset($table_configs[$type])) throw new Exception("Invalid equipment type");

                $table_name = $table_configs[$type]['table'];
                $name_field = $table_configs[$type]['name_field'];

                // --- AUTO-GENERATE ITEM NUMBER ---
                $raw_name  = $_POST[$name_field] ?? 'EQP';
                $prefix    = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $raw_name), 0, 3));
                $num_stmt  = $db->prepare("SELECT item_number FROM {$table_name} WHERE item_number LIKE ? ORDER BY id DESC LIMIT 1");
                $num_stmt->execute([$prefix . '-%']);
                $last_item = $num_stmt->fetch(PDO::FETCH_ASSOC);
                $next_num  = 1;
                if ($last_item) {
                    $parts    = explode('-', $last_item['item_number']);
                    $next_num = (int)end($parts) + 1;
                }
                $automated_item_number = $prefix . '-' . str_pad($next_num, 3, '0', STR_PAD_LEFT);

                // --- ASSEMBLE REMARKS FROM ACCOUNTABLE PERSON FIELDS ---
                if (isset($_POST['accountable_lastname']) && trim($_POST['accountable_lastname']) !== '') {
                    $lastname  = trim($_POST['accountable_lastname']);
                    $firstname = trim($_POST['accountable_firstname'] ?? '');
                    $middle    = trim($_POST['accountable_middle'] ?? '');
                    $full_name = $lastname;
                    if (!empty($firstname)) $full_name .= ', ' . $firstname;
                    if (!empty($middle))    $full_name .= ' ' . $middle;
                    $_POST['remarks'] = $full_name;
                }
                unset($_POST['accountable_lastname'], $_POST['accountable_firstname'], $_POST['accountable_middle']);

                // --- COMPUTER-SPECIFIC FIELD MAPPING ---
                if ($type === 'computer') {
                    if (isset($_POST['article']) && $_POST['article'] === 'Computer Package') {
                        $_POST['serial_number_monitor'] = trim($_POST['serial_monitor'] ?? 'N/A');
                        $_POST['serial_number_system']  = trim($_POST['serial_system']  ?? 'N/A');
                        $_POST['device_type']   = 'Desktop';
                        $_POST['serial_number'] = $_POST['property_no'] ?? '';
                    } else {
                        $_POST['device_type']           = $_POST['article'] ?? '';
                        $_POST['serial_number_monitor'] = null;
                        $_POST['serial_number_system']  = null;
                        if (empty($_POST['serial_number']) && isset($_POST['property_no'])) {
                            $_POST['serial_number'] = $_POST['property_no'];
                        }
                    }
                    unset($_POST['serial_monitor'], $_POST['serial_system']);
                } else {
                    // For non-computer equipment types, remove any serial_monitor/serial_system fields
                    unset($_POST['serial_monitor'], $_POST['serial_system']);
                    // Also remove serial_number_monitor and serial_number_system if they exist
                    unset($_POST['serial_number_monitor'], $_POST['serial_number_system']);
                }

                // --- CLEAN PURCHASE DATE ---
                if (isset($_POST['purchase_date']) && $_POST['purchase_date'] === '') {
                    $_POST['purchase_date'] = null;
                }

                // --- HANDLE IMAGE UPLOAD ---
                $image_path = null;
                if (isset($_FILES['equipment_photo']) && $_FILES['equipment_photo']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = '../uploads/equipment/';
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                    $file_ext  = strtolower(pathinfo($_FILES['equipment_photo']['name'], PATHINFO_EXTENSION));
                    $file_name = $type . '_' . time() . '_' . rand(1000, 9999) . '.' . $file_ext;
                    if (move_uploaded_file($_FILES['equipment_photo']['tmp_name'], $upload_dir . $file_name)) {
                        $image_path = 'uploads/equipment/' . $file_name;
                    }
                }

                // --- BUILD INSERT ---
                $exclude_keys        = ['action', 'equipment_type', 'item_number', 'purchase_date_option'];
                $nullable_int_fields = ['location_id', 'assigned_to', 'condemned_by', 'maintenance_resolved_by'];

                $fields = [];
                $values = [];
                foreach ($_POST as $key => $value) {
                    if (in_array($key, $exclude_keys)) continue;
                    $fields[] = $key;
                    if (in_array($key, $nullable_int_fields)) {
                        $values[] = ($value === null || $value === '' || $value === '0') ? null : (int)$value;
                    } else {
                        $values[] = ($value === null || $value === '') ? null : trim($value);
                    }
                }

                // Append auto-generated item number
                $fields[] = 'item_number';
                $values[] = $automated_item_number;

                // Append status if not already present
                if (!in_array('status', $fields)) {
                    $fields[] = 'status';
                    $values[] = 'available';
                }

                // Append is_condemned
                if (!in_array('is_condemned', $fields)) {
                    $fields[] = 'is_condemned';
                    $values[] = 0;
                }

                if ($image_path) {
                    $fields[] = 'image_path';
                    $values[] = $image_path;
                }

                $current_time = date('Y-m-d H:i:s');
                $fields[] = 'created_at';
                $fields[] = 'updated_at';
                $values[] = $current_time;
                $values[] = $current_time;

                $values       = array_values($values);
                $placeholders = array_fill(0, count($values), '?');
                $query        = "INSERT INTO {$table_name} (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";

                $stmt = $db->prepare($query);
                $stmt->execute($values);
                $_SESSION['equipment_success'] = "New " . ucfirst($type) . " added successfully! Item Number: " . $automated_item_number;
                header("Location: all_equipment.php");
                exit();
                break;
            case 'update_assignment':
            $equipment_id = $_POST['equipment_id'];
            $equipment_type = $_POST['equipment_type'];
            $new_location_id = $_POST['location_id'] == '' ? null : $_POST['location_id'];
            
            $table_map = [
                'computer' => 'computer_inventory', 'computer_lab' => 'computer_inventory',
                'kitchen' => 'kitchen_equipment', 'office' => 'office_equipment',
                'lab' => 'lab_equipment', 'regular_lab' => 'lab_equipment',
                'general' => 'general_equipment'
            ];
            $table_name = $table_map[$equipment_type] ?? 'general_equipment';
            
            // Get old location for logging
            $old_query = "SELECT location_id, assigned_to, remarks FROM {$table_name} WHERE id = ?";
            $old_stmt = $db->prepare($old_query);
            $old_stmt->execute([$equipment_id]);
            $old_data = $old_stmt->fetch(PDO::FETCH_ASSOC);
            $old_location_id = $old_data['location_id'] ?? null;
            
            // Get equipment name/description for better logging
            $name_field = '';
            if ($table_name == 'computer_inventory') {
                $name_field = 'computer_set_description';
            } elseif (in_array($table_name, ['kitchen_equipment', 'office_equipment', 'lab_equipment'])) {
                $name_field = 'equipment_name';
            } elseif ($table_name == 'general_equipment') {
                $name_field = 'article';
            }
            
            $equipment_name = '';
            if ($name_field) {
                $name_query = "SELECT {$name_field} as name FROM {$table_name} WHERE id = ?";
                $name_stmt = $db->prepare($name_query);
                $name_stmt->execute([$equipment_id]);
                $name_data = $name_stmt->fetch(PDO::FETCH_ASSOC);
                $equipment_name = $name_data['name'] ?? '';
            }
            
            // 1. Update the main inventory table
            $update_query = "UPDATE {$table_name} SET location_id = ?, updated_at = NOW() WHERE id = ?";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->execute([$new_location_id, $equipment_id]);

            // 2. LOG THE MOVEMENT in assignment_history
            if ($new_location_id && $new_location_id != $old_location_id) {
                // If assigned to a new location
                $log_query = "INSERT INTO assignment_history (computer_id, location_id, user_id, assigned_by, status, assigned_date, notes, equipment_type, equipment_table) 
                            VALUES (?, ?, ?, ?, 'active', NOW(), ?, ?, ?)";
                $log_stmt = $db->prepare($log_query);
                $log_stmt->execute([
                    $equipment_id, 
                    $new_location_id, 
                    $_SESSION['user_id'], 
                    $_SESSION['user_id'],
                    'Assigned to location via All Equipment management' . ($equipment_name ? ' - ' . $equipment_name : ''),
                    $table_name,
                    $table_name
                ]);
            } elseif (!$new_location_id && $old_location_id) {
                // If removed from location
                $log_query = "INSERT INTO assignment_history (computer_id, location_id, user_id, assigned_by, status, returned_date, notes, equipment_type, equipment_table) 
                            VALUES (?, ?, ?, ?, 'returned', NOW(), ?, ?, ?)";
                $log_stmt = $db->prepare($log_query);
                $log_stmt->execute([
                    $equipment_id, 
                    $old_location_id, 
                    $_SESSION['user_id'], 
                    $_SESSION['user_id'],
                    'Removed from location via All Equipment management' . ($equipment_name ? ' - ' . $equipment_name : ''),
                    $table_name,
                    $table_name
                ]);
            }
            
            $_SESSION['equipment_success'] = "Location updated and movement logged successfully!";
            header("Location: all_equipment.php");
            exit();
            break;
                
            case 'condemn_equipment':
                $equipment_id = $_POST['equipment_id'];
                $equipment_type = $_POST['equipment_type'];
                $condemn_reason = $_POST['condemn_reason'] ?? 'Equipment condemned via All Equipment management';
                
                // Determine the correct table
                $table_name = '';
                switch ($equipment_type) {
                    case 'computer':
                    case 'computer_lab':
                        $table_name = 'computer_inventory';
                        break;
                    case 'kitchen':
                        $table_name = 'kitchen_equipment';
                        break;
                    case 'office':
                        $table_name = 'office_equipment';
                        break;
                    case 'lab':
                    case 'regular_lab':
                        $table_name = 'lab_equipment';
                        break;
                    default:
                        $table_name = 'general_equipment';
                        break;
                }
                
                // Check if equipment is at least 5 years old
                try {
                    // Get the purchase date
                    $check_date_query = "SELECT purchase_date FROM {$table_name} WHERE id = ?";
                    $check_stmt = $db->prepare($check_date_query);
                    $check_stmt->execute([$equipment_id]);
                    $equipment_data = $check_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($equipment_data && !empty($equipment_data['purchase_date']) && $equipment_data['purchase_date'] !== '0000-00-00') {
                        $purchase_year = date('Y', strtotime($equipment_data['purchase_date']));
                        $current_year = date('Y');
                        $age = $current_year - $purchase_year;
                        
                        if ($age < 5) {
                            throw new Exception("Equipment is only {$age} years old. Only equipment 5 years or older can be condemned.");
                        }
                    } else {
                        throw new Exception("Cannot condemn equipment without a purchase date.");
                    }
                } catch (Exception $e) {
                    $_SESSION['equipment_error'] = $e->getMessage();
                    header("Location: all_equipment.php");
                    exit();
                    break;
                }
                
                // Check if condemned columns exist in the table
                try {
                    $check_condemned = $db->prepare("SHOW COLUMNS FROM {$table_name} LIKE 'is_condemned'");
                    $check_condemned->execute();
                    
                    if ($check_condemned->rowCount() > 0) {
                        // Update to condemned
                        $update_query = "UPDATE {$table_name} SET is_condemned = TRUE, condemned_date = NOW(), condemned_reason = ?, condemned_by = ?, status = 'condemned' WHERE id = ?";
                        $update_stmt = $db->prepare($update_query);
                        $update_stmt->execute([$condemn_reason, $_SESSION['user_id'], $equipment_id]);
                        
                        $_SESSION['equipment_success'] = "Equipment has been condemned successfully!";
                    } else {
                        $_SESSION['equipment_error'] = "Condemned functionality not available. Please run database integration first.";
                    }
                } catch (Exception $e) {
                    $_SESSION['equipment_error'] = "Error condemning equipment: " . $e->getMessage();
                }
                header("Location: all_equipment.php");
                exit();
                break;
                
            case 'upload_csv':
                $equipment_type = $_POST['equipment_type'];
                $has_header = isset($_POST['has_header']);
                
                try {
                    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
                        throw new Exception("File upload failed");
                    }
                    
                    $file_path = $_FILES['csv_file']['tmp_name'];
                    $file_handle = fopen($file_path, 'r');
                    
                    if (!$file_handle) {
                        throw new Exception("Could not read CSV file");
                    }
                    
                    // Determine the correct table
                    $table_name = '';
                    switch ($equipment_type) {
                        case 'computer':
                            $table_name = 'computer_inventory';
                            break;
                        case 'kitchen':
                            $table_name = 'kitchen_equipment';
                            break;
                        case 'office':
                            $table_name = 'office_equipment';
                            break;
                        case 'lab':
                            $table_name = 'lab_equipment';
                            break;
                        default:
                            throw new Exception("Invalid equipment type");
                    }
                    
                    $row_count = 0;
                    $success_count = 0;
                    $error_count = 0;
                    $errors = [];
                    
                    // Skip header row if specified
                    if ($has_header) {
                        fgetcsv($file_handle);
                    }
                    
                    while (($data = fgetcsv($file_handle)) !== FALSE) {
                        $row_count++;
                        
                        try {
                            // Filter out empty values and prepare data
                            $filtered_data = array_filter($data, function($value) {
                                return trim($value) !== '';
                            });
                            
                            if (empty($filtered_data)) {
                                continue; // Skip empty rows
                            }
                            
                            // Build insert query based on equipment type
                            $fields = [];
                            $values = [];
                            
                            if ($equipment_type === 'computer') {
                                $fields = ['item_number', 'computer_set_description', 'processor', 'ram', 'storage', 'device_type', 'operating_system', 'serial_number', 'condition_status', 'remarks'];
                            } else {
                                $fields = ['item_number', 'equipment_name', 'brand', 'model', 'serial_number', 'specifications', 'condition_status', 'remarks'];
                                if ($equipment_type === 'kitchen') {
                                    $fields = ['item_number', 'equipment_name', 'brand', 'model', 'serial_number', 'capacity', 'power_rating', 'condition_status', 'remarks'];
                                } elseif ($equipment_type === 'lab') {
                                    $fields = ['item_number', 'equipment_name', 'brand', 'model', 'serial_number', 'specifications', 'calibration_date', 'condition_status', 'remarks'];
                                }
                            }
                            
                            // Map CSV data to fields
                            for ($i = 0; $i < min(count($data), count($fields)); $i++) {
                                if (trim($data[$i]) !== '') {
                                    $values[$fields[$i]] = trim($data[$i]);
                                }
                            }
                            
                            // Add default values
                            $values['status'] = 'available';
                            
                            $field_names = array_keys($values);
                            $placeholders = array_fill(0, count($values), '?');
                            
                            $query = "INSERT INTO {$table_name} (" . implode(', ', $field_names) . ", created_at, updated_at) VALUES (" . implode(', ', $placeholders) . ", NOW(), NOW())";
                            $stmt = $db->prepare($query);
                            $stmt->execute(array_values($values));
                            
                            $success_count++;
                        } catch (Exception $e) {
                            $error_count++;
                            $errors[] = "Row {$row_count}: " . $e->getMessage();
                        }
                    }
                    
                    fclose($file_handle);
                    
                    if ($success_count > 0) {
                        $_SESSION['csv_success'] = "CSV upload completed! {$success_count} items added successfully.";
                        if ($error_count > 0) {
                            $_SESSION['csv_success'] .= " {$error_count} items failed to import.";
                        }
                    } else {
                        $_SESSION['csv_error'] = "CSV upload failed. No items were imported.";
                    }
                    
                    if (!empty($errors)) {
                        $_SESSION['csv_errors'] = $errors;
                    }
                    
                } catch (Exception $e) {
                    $_SESSION['csv_error'] = "CSV upload error: " . $e->getMessage();
                }
                header("Location: all_equipment.php");
                exit();
                break;
                
            case 'bulk_assign':
            $equipment_items_string = $_POST['equipment_items'] ?? '';
            $new_location_id = $_POST['bulk_location_id'] == '' ? null : $_POST['bulk_location_id'];
            
            if (!empty($equipment_items_string)) {
                $equipment_items = explode(',', $equipment_items_string);
                $updated_count = 0;
                
                foreach ($equipment_items as $item) {
                    if (empty($item) || strpos($item, ':') === false) {
                        continue; // Skip invalid items
                    }
                    
                    $parts = explode(':', $item);
                    if (count($parts) !== 2) {
                        continue; // Skip malformed items
                    }
                    
                    list($equipment_type, $equipment_id) = $parts;
                    
                    // Determine the correct table
                    $table_name = '';
                    switch ($equipment_type) {
                        case 'computer':
                        case 'computer_lab':
                            $table_name = 'computer_inventory';
                            break;
                        case 'kitchen':
                            $table_name = 'kitchen_equipment';
                            break;
                        case 'office':
                            $table_name = 'office_equipment';
                            break;
                        case 'lab':
                        case 'regular_lab':
                            $table_name = 'lab_equipment';
                            break;
                        default:
                            $table_name = 'general_equipment';
                            break;
                    }
                    
                    // Get old location for logging
                    $old_query = "SELECT location_id, assigned_to FROM {$table_name} WHERE id = ?";
                    $old_stmt = $db->prepare($old_query);
                    $old_stmt->execute([$equipment_id]);
                    $old_data = $old_stmt->fetch(PDO::FETCH_ASSOC);
                    $old_location_id = $old_data['location_id'] ?? null;
                    
                    // Get equipment name/description for better logging
                    $name_field = '';
                    if ($table_name == 'computer_inventory') {
                        $name_field = 'computer_set_description';
                    } elseif (in_array($table_name, ['kitchen_equipment', 'office_equipment', 'lab_equipment'])) {
                        $name_field = 'equipment_name';
                    } elseif ($table_name == 'general_equipment') {
                        $name_field = 'article';
                    }
                    
                    $equipment_name = '';
                    if ($name_field) {
                        $name_query = "SELECT {$name_field} as name FROM {$table_name} WHERE id = ?";
                        $name_stmt = $db->prepare($name_query);
                        $name_stmt->execute([$equipment_id]);
                        $name_data = $name_stmt->fetch(PDO::FETCH_ASSOC);
                        $equipment_name = $name_data['name'] ?? '';
                    }
                    
                    // Update the equipment
                    $status = $new_location_id ? 'available' : 'available';
                    $update_query = "UPDATE {$table_name} SET location_id = ?, status = ? WHERE id = ?";
                    $update_stmt = $db->prepare($update_query);
                    $update_stmt->execute([$new_location_id, $status, $equipment_id]);
                    
                    // LOG THE MOVEMENT in assignment_history
                    if ($new_location_id && $new_location_id != $old_location_id) {
                        // If assigned to a new location
                        $log_query = "INSERT INTO assignment_history (computer_id, location_id, user_id, assigned_by, status, assigned_date, notes, equipment_type, equipment_table) 
                                    VALUES (?, ?, ?, ?, 'active', NOW(), ?, ?, ?)";
                        $log_stmt = $db->prepare($log_query);
                        $log_stmt->execute([
                            $equipment_id, 
                            $new_location_id, 
                            $_SESSION['user_id'], 
                            $_SESSION['user_id'],
                            'Bulk assigned to location via All Equipment management' . ($equipment_name ? ' - ' . $equipment_name : ''),
                            $table_name,
                            $table_name
                        ]);
                    } elseif (!$new_location_id && $old_location_id) {
                        // If removed from location
                        $log_query = "INSERT INTO assignment_history (computer_id, location_id, user_id, assigned_by, status, returned_date, notes, equipment_type, equipment_table) 
                                    VALUES (?, ?, ?, ?, 'returned', NOW(), ?, ?, ?)";
                        $log_stmt = $db->prepare($log_query);
                        $log_stmt->execute([
                            $equipment_id, 
                            $old_location_id, 
                            $_SESSION['user_id'], 
                            $_SESSION['user_id'],
                            'Bulk removed from location via All Equipment management' . ($equipment_name ? ' - ' . $equipment_name : ''),
                            $table_name,
                            $table_name
                        ]);
                    }
                    
                    $updated_count++;
                }
                
                $_SESSION['equipment_success'] = "{$updated_count} equipment items updated and logged successfully!";
            }
            case 'manual_condemn':
            $equipment_id = $_POST['equipment_id'];
            $equipment_type = $_POST['equipment_type'];
            $condemn_reason = $_POST['condemn_reason'] ?? 'Manual condemnation - Equipment broken/unusable';
            $condemn_category = $_POST['condemn_category'] ?? 'Other';
            
            // Map equipment type to table
            $table_map = [
                'computer' => 'computer_inventory',
                'computer_lab' => 'computer_inventory',
                'kitchen' => 'kitchen_equipment',
                'office' => 'office_equipment',
                'lab' => 'lab_equipment',
                'regular_lab' => 'lab_equipment',
                'general' => 'general_equipment'
            ];
            
            $table_name = $table_map[$equipment_type] ?? 'general_equipment';
            
            try {
                // Start transaction
                $db->beginTransaction();
                
                // Get equipment details
                $get_query = "SELECT * FROM {$table_name} WHERE id = ?";
                $get_stmt = $db->prepare($get_query);
                $get_stmt->execute([$equipment_id]);
                $equipment = $get_stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$equipment) {
                    throw new Exception("Equipment not found");
                }
                
                // Get model name from appropriate field
                $model = '';
                if (!empty($equipment['computer_set_description'])) {
                    $model = $equipment['computer_set_description'];
                } elseif (!empty($equipment['equipment_name'])) {
                    $model = $equipment['equipment_name'];
                } elseif (!empty($equipment['article'])) {
                    $model = $equipment['article'];
                } else {
                    $model = 'Unknown Model';
                }
                
                // Get serial number (handle different tables)
                $serial_number = 'N/A';
                if (!empty($equipment['serial_number'])) {
                    $serial_number = $equipment['serial_number'];
                } elseif (!empty($equipment['property_no'])) {
                    $serial_number = $equipment['property_no'];
                } elseif (!empty($equipment['serial_number_system'])) {
                    $serial_number = $equipment['serial_number_system'];
                }
                
                // Get accountable person (from remarks field)
                $remarks = $equipment['remarks'] ?? '';
                
                // Get cost if available
                $estimated_value = $equipment['cost'] ?? 0.00;
                
                // Map to appropriate category based on equipment type and data
                $category = $condemn_category;
                if ($category == 'Other' || empty($category)) {
                    // Auto-determine category if not manually selected
                    if ($table_name == 'computer_inventory') {
                        $device_type = $equipment['device_type'] ?? '';
                        if (strpos($device_type, 'Desktop') !== false || strpos($device_type, 'System') !== false) {
                            $category = 'System Unit';
                        } elseif (strpos($device_type, 'All-in-One') !== false) {
                            $category = 'All in one';
                        } elseif (strpos($device_type, 'Laptop') !== false) {
                            $category = 'Other';
                        } else {
                            $category = 'System Unit';
                        }
                    } elseif ($table_name == 'general_equipment') {
                        $article = $equipment['article'] ?? '';
                        if (strpos($article, 'Keyboard') !== false) {
                            $category = 'Keyboard';
                        } elseif (strpos($article, 'AVR') !== false) {
                            $category = 'AVR';
                        } elseif (strpos($article, 'Monitor') !== false) {
                            $category = 'Monitor';
                        } else {
                            $category = 'Other';
                        }
                    } else {
                        $category = 'Other';
                    }
                }
                
                // Determine equipment_type for condemned table (matches enum in condemned_equipment)
                $condemned_equipment_type = 'monitor_system'; // default
                if ($category == 'Keyboard' || $category == 'AVR') {
                    $condemned_equipment_type = 'keyboard';
                }
                
                // Insert into condemned_equipment table - NOW INCLUDING remarks column
                $insert_query = "INSERT INTO condemned_equipment 
                                (model, category, serial_number, equipment_type, reason_condemned, 
                                condemned_date, condemned_by, disposal_status, estimated_value, remarks) 
                                VALUES (?, ?, ?, ?, ?, NOW(), ?, 'pending', ?, ?)";
                $insert_stmt = $db->prepare($insert_query);
                $insert_stmt->execute([
                    $model,
                    $category,
                    $serial_number,
                    $condemned_equipment_type,
                    $condemn_reason,
                    $_SESSION['user_id'],
                    $estimated_value,
                    $remarks
                ]);
                
                // Update original equipment as condemned
                $update_query = "UPDATE {$table_name} 
                                SET is_condemned = 1, 
                                    condemned_date = NOW(), 
                                    condemned_reason = ?, 
                                    condemned_by = ?,
                                    status = 'condemned',
                                    updated_at = NOW() 
                                WHERE id = ?";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->execute([$condemn_reason, $_SESSION['user_id'], $equipment_id]);
                
                // Log to assignment_history
                $log_query = "INSERT INTO assignment_history 
                            (computer_id, equipment_type, equipment_table, user_id, assigned_by, notes, status, returned_date) 
                            VALUES (?, ?, ?, ?, ?, 'Equipment condemned - moved to condemned_equipment table', 'returned', NOW())";
                $log_stmt = $db->prepare($log_query);
                $log_stmt->execute([
                    $equipment_id,
                    $equipment_type,
                    $table_name,
                    $_SESSION['user_id'],
                    $_SESSION['user_id']
                ]);
                
                // Commit transaction
                $db->commit();
                
                $_SESSION['equipment_success'] = "Equipment has been condemned and moved to condemned_equipment table successfully!";
                
            } catch (Exception $e) {
                $db->rollBack();
                $_SESSION['equipment_error'] = "Error condemning equipment: " . $e->getMessage();
            }
            
            header("Location: all_equipment.php");
            exit();
            break;
        }
    } catch (Exception $e) {
        $_SESSION['equipment_error'] = "Error: " . $e->getMessage();
        header("Location: all_equipment.php");
        exit();
    }
}

// Get filter parameters
$location_filter = $_GET['location'] ?? '';
$type_filter = $_GET['type'] ?? '';
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$campus_filter = $_GET['campus'] ?? ''; // ADD THIS LINE

// ─────────────────────────────────────────────────────────────────────────────
// LAZY-LOAD MODE: Only fetch quick counts for the stat cards.
// All table rows are loaded via AJAX (get_equipment_ajax.php).
// ─────────────────────────────────────────────────────────────────────────────

// Helper to count rows with condemned filter
function countEquipment(PDO $db, string $table, string $alias): int {
    try {
        $where = '';
        try {
            $chk = $db->prepare("SHOW COLUMNS FROM {$table} LIKE 'is_condemned'");
            $chk->execute();
            if ($chk->rowCount() > 0) {
                $where = "WHERE ({$alias}.is_condemned IS NULL OR {$alias}.is_condemned = FALSE)";
            }
        } catch (Exception $e) {}
        return (int)$db->query("SELECT COUNT(*) FROM {$table} {$alias} {$where}")->fetchColumn();
    } catch (Exception $e) { return 0; }
}

function countEquipmentWhere(PDO $db, string $table, string $alias, string $extra_where): int {
    try {
        $base_where = '';
        try {
            $chk = $db->prepare("SHOW COLUMNS FROM {$table} LIKE 'is_condemned'");
            $chk->execute();
            if ($chk->rowCount() > 0) {
                $base_where = "({$alias}.is_condemned IS NULL OR {$alias}.is_condemned = FALSE) AND ";
            }
        } catch (Exception $e) {}
        return (int)$db->query("SELECT COUNT(*) FROM {$table} {$alias} WHERE {$base_where}{$extra_where}")->fetchColumn();
    } catch (Exception $e) { return 0; }
}

$total_equipment   = countEquipment($db, 'computer_inventory', 'ci')
                   + countEquipment($db, 'kitchen_equipment', 'ke')
                   + countEquipment($db, 'office_equipment', 'oe')
                   + countEquipment($db, 'lab_equipment', 'le')
                   + countEquipment($db, 'general_equipment', 'ge');

$assigned_count    = countEquipmentWhere($db, 'computer_inventory', 'ci', 'ci.location_id IS NOT NULL')
                   + countEquipmentWhere($db, 'kitchen_equipment',   'ke', 'ke.location_id IS NOT NULL')
                   + countEquipmentWhere($db, 'office_equipment',    'oe', 'oe.location_id IS NOT NULL')
                   + countEquipmentWhere($db, 'lab_equipment',       'le', 'le.location_id IS NOT NULL')
                   + countEquipmentWhere($db, 'general_equipment',   'ge', 'ge.location_id IS NOT NULL');

$maintenance_count = countEquipmentWhere($db, 'computer_inventory', 'ci', "ci.status = 'maintenance'")
                   + countEquipmentWhere($db, 'kitchen_equipment',   'ke', "ke.status = 'maintenance'")
                   + countEquipmentWhere($db, 'office_equipment',    'oe', "oe.status = 'maintenance'")
                   + countEquipmentWhere($db, 'lab_equipment',       'le', "le.status = 'maintenance'")
                   + countEquipmentWhere($db, 'general_equipment',   'ge', "ge.status = 'maintenance'");

$unassigned_count  = $total_equipment - $assigned_count;

// Placeholder so existing references to $all_equipment don't crash
$all_equipment = [];

// NOTE: The big per-table SELECT queries below are REMOVED.
// They were replaced by the lazy-load system.
// Scroll to the tbody section to see the new AJAX loader.

// ─── SKIP: old Computer Inventory query (now handled by get_equipment_ajax.php) ───
if (false): // Computer Inventory
try {
    $computer_query = "SELECT ci.*, 'computer_lab' as equipment_type, 'Computer' as type_label,
                       l.location_name, l.id as location_id,
                       CONCAT(ci.item_number, ' - ', ci.computer_set_description) as equipment_name,
                       ci.serial_number, ci.status, ci.condition_status
                       FROM computer_inventory ci
                       LEFT JOIN locations l ON ci.location_id = l.id";

    // Check if condemned column exists
    try {
        $check_condemned = $db->prepare("SHOW COLUMNS FROM computer_inventory LIKE 'is_condemned'");
        $check_condemned->execute();
        if ($check_condemned->rowCount() > 0) {
            $computer_query .= " WHERE (ci.is_condemned IS NULL OR ci.is_condemned = FALSE)";
        }
    } catch (Exception $e) {
        // Column doesn't exist, continue without filter
    }

    $computer_conditions = [];
    
    // Add campus filter
    if ($campus_filter) {
        $computer_conditions[] = "ci.campus = :campus_filter";
    }
    
    if ($location_filter) {
        $computer_conditions[] = "ci.location_id = :location_filter";
    }
    if ($status_filter) {
        $computer_conditions[] = "ci.status = :status_filter";
    }
    
    // FIXED SEARCH - Include ALL serial number fields
    if ($search) {
        $computer_conditions[] = "(ci.item_number LIKE :search 
                               OR ci.computer_set_description LIKE :search 
                               OR ci.serial_number LIKE :search 
                               OR ci.property_no LIKE :search 
                               OR ci.serial_number_monitor LIKE :search 
                               OR ci.serial_number_system LIKE :search 
                               OR ci.remarks LIKE :search)";
    }

    if (!empty($computer_conditions)) {
        $where_clause = " WHERE " . implode(" AND ", $computer_conditions);
        // Check if we already added a WHERE clause for condemned
        if (strpos($computer_query, 'WHERE') !== false) {
            $computer_query .= " AND " . implode(" AND ", $computer_conditions);
        } else {
            $computer_query .= $where_clause;
        }
    }
    $computer_query .= " ORDER BY ci.created_at DESC";

    $computer_stmt = $db->prepare($computer_query);
    
    // Bind parameters
    if ($campus_filter) $computer_stmt->bindValue(':campus_filter', $campus_filter);
    if ($location_filter) $computer_stmt->bindValue(':location_filter', $location_filter);
    if ($status_filter) $computer_stmt->bindValue(':status_filter', $status_filter);
    if ($search) {
        $searchTerm = "%{$search}%";
        $computer_stmt->bindValue(':search', $searchTerm);
    }
    
    $computer_stmt->execute();
    $computers = $computer_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug: Check if the specific serial is found
    if ($search == '8CC3312N5B') {
        error_log("Searching for 8CC3312N5B - Found " . count($computers) . " results");
    }
    
} catch (Exception $e) {
    error_log("Computer search error: " . $e->getMessage());
    $computers = [];
}

// Kitchen Equipment
try {
    $kitchen_query = "SELECT ke.*, 'kitchen' as equipment_type, 'Kitchen Equipment' as type_label,
                      l.location_name, l.id as location_id,
                      CONCAT(ke.item_number, ' - ', ke.equipment_name) as equipment_name,
                      ke.serial_number, ke.status, ke.condition_status
                      FROM kitchen_equipment ke
                      LEFT JOIN locations l ON ke.location_id = l.id";

    // Check if condemned column exists
    try {
        $check_condemned = $db->prepare("SHOW COLUMNS FROM kitchen_equipment LIKE 'is_condemned'");
        $check_condemned->execute();
        if ($check_condemned->rowCount() > 0) {
            $kitchen_query .= " WHERE (ke.is_condemned IS NULL OR ke.is_condemned = FALSE)";
        }
    } catch (Exception $e) {
        // Column doesn't exist, continue without filter
    }

    $kitchen_conditions = [];
    
    // Add campus filter
    if ($campus_filter) {
        $kitchen_conditions[] = "ke.campus = :campus_filter";
    }
    
    if ($location_filter) {
        $kitchen_conditions[] = "ke.location_id = :location_filter";
    }
    if ($status_filter) {
        $kitchen_conditions[] = "ke.status = :status_filter";
    }
    if ($search) {
        $kitchen_conditions[] = "(ke.item_number LIKE :search 
                            OR ke.equipment_name LIKE :search 
                            OR ke.serial_number LIKE :search 
                            OR ke.property_no LIKE :search 
                            OR ke.remarks LIKE :search)";
    }

    if (!empty($kitchen_conditions)) {
        $kitchen_query .= (strpos($kitchen_query, 'WHERE') !== false ? " AND " : " WHERE ") . implode(" AND ", $kitchen_conditions);
    }
    $kitchen_query .= " ORDER BY ke.created_at DESC";

    $kitchen_stmt = $db->prepare($kitchen_query);
    if ($campus_filter) $kitchen_stmt->bindValue(':campus_filter', $campus_filter);
    if ($location_filter) $kitchen_stmt->bindValue(':location_filter', $location_filter);
    if ($status_filter) $kitchen_stmt->bindValue(':status_filter', $status_filter);
    if ($search) $kitchen_stmt->bindValue(':search', "%{$search}%");
    $kitchen_stmt->execute();
    $kitchen_equipment = $kitchen_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $kitchen_equipment = [];
}

// Office Equipment
try {
    $office_query = "SELECT oe.*, 'office' as equipment_type, 'Office Equipment' as type_label,
                     l.location_name, l.id as location_id,
                     CONCAT(oe.item_number, ' - ', oe.equipment_name) as equipment_name,
                     oe.serial_number, oe.property_no, oe.status, oe.condition_status
                     FROM office_equipment oe
                     LEFT JOIN locations l ON oe.location_id = l.id";

    // Check if condemned column exists
    try {
        $check_condemned = $db->prepare("SHOW COLUMNS FROM office_equipment LIKE 'is_condemned'");
        $check_condemned->execute();
        if ($check_condemned->rowCount() > 0) {
            $office_query .= " WHERE (oe.is_condemned IS NULL OR oe.is_condemned = FALSE)";
        }
    } catch (Exception $e) {
        // Column doesn't exist, continue without filter
    }

    $office_conditions = [];
    
    // Add campus filter
    if ($campus_filter) {
        $office_conditions[] = "oe.campus = :campus_filter";
    }
    
    if ($location_filter) {
        $office_conditions[] = "oe.location_id = :location_filter";
    }
    if ($status_filter) {
        $office_conditions[] = "oe.status = :status_filter";
    }
    if ($search) {
        $office_conditions[] = "(oe.item_number LIKE :search 
                            OR oe.equipment_name LIKE :search 
                            OR oe.serial_number LIKE :search 
                            OR oe.property_no LIKE :search
                            OR oe.remarks LIKE :search)";
    }

    if (!empty($office_conditions)) {
        $office_query .= (strpos($office_query, 'WHERE') !== false ? " AND " : " WHERE ") . implode(" AND ", $office_conditions);
    }
    $office_query .= " ORDER BY oe.created_at DESC";

    $office_stmt = $db->prepare($office_query);
    if ($campus_filter) $office_stmt->bindValue(':campus_filter', $campus_filter);
    if ($location_filter) $office_stmt->bindValue(':location_filter', $location_filter);
    if ($status_filter) $office_stmt->bindValue(':status_filter', $status_filter);
    if ($search) $office_stmt->bindValue(':search', "%{$search}%");
    $office_stmt->execute();
    $office_equipment = $office_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $office_equipment = [];
}

// Lab Equipment
try {
    $lab_query = "SELECT le.*, 'regular_lab' as equipment_type, 'Lab Equipment' as type_label,
                  l.location_name, l.id as location_id,
                  CONCAT(le.item_number, ' - ', le.equipment_name) as equipment_name,
                  le.serial_number, le.status, le.condition_status
                  FROM lab_equipment le
                  LEFT JOIN locations l ON le.location_id = l.id";

    // Check if condemned column exists
    try {
        $check_condemned = $db->prepare("SHOW COLUMNS FROM lab_equipment LIKE 'is_condemned'");
        $check_condemned->execute();
        if ($check_condemned->rowCount() > 0) {
            $lab_query .= " WHERE (le.is_condemned IS NULL OR le.is_condemned = FALSE)";
        }
    } catch (Exception $e) {
        // Column doesn't exist, continue without filter
    }

    $lab_conditions = [];
    
    // Add campus filter
    if ($campus_filter) {
        $lab_conditions[] = "le.campus = :campus_filter";
    }
    
    if ($location_filter) {
        $lab_conditions[] = "le.location_id = :location_filter";
    }
    if ($status_filter) {
        $lab_conditions[] = "le.status = :status_filter";
    }
    if ($search) {
        $lab_conditions[] = "(le.item_number LIKE :search 
                            OR le.equipment_name LIKE :search 
                            OR le.serial_number LIKE :search 
                            OR le.property_no LIKE :search 
                            OR le.remarks LIKE :search)";
    }

    if (!empty($lab_conditions)) {
        $lab_query .= (strpos($lab_query, 'WHERE') !== false ? " AND " : " WHERE ") . implode(" AND ", $lab_conditions);
    }
    $lab_query .= " ORDER BY le.created_at DESC";

    $lab_stmt = $db->prepare($lab_query);
    if ($campus_filter) $lab_stmt->bindValue(':campus_filter', $campus_filter);
    if ($location_filter) $lab_stmt->bindValue(':location_filter', $location_filter);
    if ($status_filter) $lab_stmt->bindValue(':status_filter', $status_filter);
    if ($search) $lab_stmt->bindValue(':search', "%{$search}%");
    $lab_stmt->execute();
    $lab_equipment = $lab_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $lab_equipment = [];
}

// General Equipment
try {
    $general_query = "SELECT ge.*, 'general' as equipment_type, 'General Equipment' as type_label,
                       l.location_name, l.id as location_id,
                       ge.article as equipment_name,
                       ge.serial_number, ge.property_no, ge.status, ge.condition_status,
                       ge.projector_brand, ge.projector_model, ge.projector_serial_number
                       FROM general_equipment ge
                       LEFT JOIN locations l ON ge.location_id = l.id";

    $general_conditions = [];
    
    // Add campus filter
    if ($campus_filter) {
        $general_conditions[] = "ge.campus = :campus_filter";
    }
    
    if ($location_filter) {
        $general_conditions[] = "ge.location_id = :location_filter";
    }
    if ($status_filter) {
        $general_conditions[] = "ge.status = :status_filter";
    }
    if ($search) {
        $general_conditions[] = "(ge.item_number LIKE :search 
                            OR ge.article LIKE :search 
                            OR ge.serial_number LIKE :search 
                            OR ge.property_no LIKE :search
                            OR ge.remarks LIKE :search)";
    }

    if (!empty($general_conditions)) {
        $general_query .= " WHERE " . implode(" AND ", $general_conditions);
    }
    $general_query .= " ORDER BY ge.created_at DESC";

    $general_stmt = $db->prepare($general_query);
    if ($campus_filter) $general_stmt->bindValue(':campus_filter', $campus_filter);
    if ($location_filter) $general_stmt->bindValue(':location_filter', $location_filter);
    if ($status_filter) $general_stmt->bindValue(':status_filter', $status_filter);
    if ($search) {
        $searchTerm = "%{$search}%";
        $general_stmt->bindValue(':search', $searchTerm);
    }
    $general_stmt->execute();
    $general_equipment_list = $general_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $general_equipment_list = [];
}

// END of skipped old queries
endif; // end if(false)

// Get all locations for dropdowns (needed for Add Equipment modal & bulk assign)
$locations_query = "SELECT id, location_name FROM locations ORDER BY location_name";
$locations_stmt = $db->prepare($locations_query);
$locations_stmt->execute();
$locations = $locations_stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "All Equipment Management";
include '../includes/header.php';
?>

<style>
:root {
    --eq-green-primary: #2E7D32;
    --eq-green-secondary: #4CAF50;
    --eq-green-light: #81C784;
    --eq-green-soft: #E8F5E9;
    --eq-green-mint: #C8E6C9;
    --eq-green-dark: #1B5E20;
    --eq-white: #FFFFFF;
    --eq-off-white: #F8F9FA;
    --eq-gray-light: #F1F8E9;
    --eq-gray: #6B7280;
    --eq-border: #E0E0E0;
    --eq-shadow: 0 10px 30px rgba(46, 125, 50, 0.1);
}

/* Page Header */
.page-header {
    background: linear-gradient(135deg, var(--eq-green-primary) 0%, var(--eq-green-dark) 100%);
    border-radius: 24px;
    padding: 2rem 2.5rem;
    margin-bottom: 2rem;
    color: white;
    position: relative;
    overflow: hidden;
    box-shadow: 0 20px 40px rgba(46, 125, 50, 0.2);
}

.page-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    animation: rotate 20s linear infinite;
}

@keyframes rotate {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.header-content {
    position: relative;
    z-index: 2;
}

.header-title {
    font-size: 2.2rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.header-subtitle {
    font-size: 1rem;
    opacity: 0.9;
    margin-bottom: 1.5rem;
}

.header-stats {
    display: flex;
    gap: 2rem;
    flex-wrap: wrap;
}

.header-stat-item {
    text-align: center;
    background: rgba(255,255,255,0.1);
    padding: 0.8rem 1.5rem;
    border-radius: 50px;
    backdrop-filter: blur(10px);
}

.header-stat-number {
    font-size: 1.5rem;
    font-weight: 700;
    display: block;
    line-height: 1;
}

.header-stat-label {
    font-size: 0.8rem;
    opacity: 0.9;
}

.header-actions {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
}

.header-actions .btn {
    background: rgba(255,255,255,0.15);
    border: 1px solid rgba(255,255,255,0.3);
    color: white;
    padding: 0.8rem 1.5rem;
    border-radius: 50px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.header-actions .btn:hover {
    background: white;
    color: var(--eq-green-primary);
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}

/* Alert Messages */
.alert {
    border-radius: 16px;
    border: none;
    padding: 1rem 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
}

.alert-success {
    background: #E8F5E9;
    color: var(--eq-green-dark);
    border-left: 4px solid var(--eq-green-primary);
}

.alert-danger {
    background: #FFEBEE;
    color: #B71C1C;
    border-left: 4px solid #D32F2F;
}

.alert-warning {
    background: #FFF3E0;
    color: #B76E00;
    border-left: 4px solid #F57C00;
}

/* Stats Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    box-shadow: var(--eq-shadow);
    border: 1px solid var(--eq-green-mint);
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px rgba(46, 125, 50, 0.15);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    color: white;
}

.stat-icon.primary { background: linear-gradient(135deg, var(--eq-green-primary) 0%, var(--eq-green-secondary) 100%); }
.stat-icon.success { background: linear-gradient(135deg, #43A047 0%, #66BB6A 100%); }
.stat-icon.warning { background: linear-gradient(135deg, #F57C00 0%, #FFB74D 100%); }
.stat-icon.danger { background: linear-gradient(135deg, #D32F2F 0%, #F44336 100%); }

.stat-content h3 {
    font-size: 2rem;
    font-weight: 800;
    margin: 0;
    line-height: 1.2;
    color: var(--eq-green-dark);
}

.stat-content p {
    margin: 0;
    color: var(--eq-gray);
    font-size: 0.9rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-trend {
    font-size: 0.75rem;
    color: var(--eq-green-primary);
    margin-top: 0.3rem;
}

/* Filter Section */
.filter-section {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: var(--eq-shadow);
    border: 1px solid var(--eq-green-mint);
}

.filter-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--eq-green-dark);
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.filter-title i {
    color: var(--eq-green-primary);
}

.filter-badge {
    background: var(--eq-green-soft);
    color: var(--eq-green-dark);
    padding: 0.3rem 1rem;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 600;
    margin-left: 1rem;
}

.filter-group {
    border-right: 1px solid var(--eq-green-mint);
    padding-right: 1.5rem;
    margin-right: 1.5rem;
}

.filter-group:last-child {
    border-right: none;
    margin-right: 0;
    padding-right: 0;
}

.filter-label {
    font-size: 0.8rem;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--eq-gray);
    margin-bottom: 0.75rem;
}

.filter-btn-group {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.filter-btn {
    border: 1px solid var(--eq-green-mint);
    border-radius: 50px;
    padding: 0.5rem 1.2rem;
    font-size: 0.85rem;
    font-weight: 500;
    color: var(--eq-gray);
    background: white;
    transition: all 0.2s ease;
    cursor: pointer;
}

.filter-btn:hover {
    background: var(--eq-green-soft);
    border-color: var(--eq-green-primary);
    color: var(--eq-green-dark);
}

.filter-btn.active {
    background: linear-gradient(135deg, var(--eq-green-primary) 0%, var(--eq-green-secondary) 100%);
    color: white;
    border-color: var(--eq-green-primary);
}

/* Bulk Actions */
.bulk-actions {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: var(--eq-shadow);
    border: 1px solid var(--eq-green-mint);
}

.bulk-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--eq-green-dark);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.bulk-title i {
    color: var(--eq-green-primary);
}

.selection-badge {
    background: var(--eq-green-soft);
    color: var(--eq-green-dark);
    padding: 0.3rem 1rem;
    border-radius: 50px;
    font-size: 0.9rem;
    font-weight: 700;
}

.selection-info {
    background: #F0F9FF;
    border-left: 4px solid var(--eq-green-primary);
    padding: 1rem;
    border-radius: 12px;
}

/* Table Container */
.table-container {
    background: white;
    border-radius: 20px;
    box-shadow: var(--eq-shadow);
    border: 1px solid var(--eq-green-mint);
    overflow: hidden;
    margin-bottom: 2rem;
}

.table-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--eq-green-mint);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.table-title {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--eq-green-dark);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.table-title i {
    color: var(--eq-green-primary);
}

.table-badge {
    background: var(--eq-green-soft);
    color: var(--eq-green-dark);
    padding: 0.3rem 1rem;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 600;
    margin-left: 1rem;
}

/* Equipment Table */
.equipment-table {
    width: 100%;
    border-collapse: collapse;
}

.equipment-table thead th {
    background: var(--eq-green-soft);
    color: var(--eq-green-dark);
    font-weight: 600;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 1rem;
    border-bottom: 2px solid var(--eq-green-primary);
}

.equipment-table tbody td {
    padding: 1rem;
    border-bottom: 1px solid var(--eq-green-mint);
    vertical-align: middle;
}

.equipment-table tbody tr {
    transition: all 0.3s ease;
}

.equipment-table tbody tr:hover {
    background: var(--eq-green-soft);
}

.equipment-table tbody tr.selected {
    background: #F0F9FF;
    border-left: 3px solid var(--eq-green-primary);
}

/* Equipment Info */
.equipment-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.equipment-icon {
    width: 45px;
    height: 45px;
    border-radius: 12px;
    background: linear-gradient(135deg, var(--eq-green-primary) 0%, var(--eq-green-secondary) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.2rem;
    box-shadow: 0 4px 10px rgba(46, 125, 50, 0.2);
}

.equipment-details h6 {
    font-weight: 700;
    color: var(--eq-green-dark);
    margin: 0 0 0.2rem 0;
}

.equipment-details small {
    color: var(--eq-gray);
    font-size: 0.7rem;
    display: flex;
    align-items: center;
    gap: 0.3rem;
}

.equipment-details small i {
    color: var(--eq-green-primary);
    font-size: 0.6rem;
}

/* Type Badges */
.type-badge {
    padding: 0.4rem 1rem;
    border-radius: 50px;
    font-size: 0.75rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.type-badge.computer { background: #E8F5E9; color: var(--eq-green-primary); border: 1px solid var(--eq-green-primary); }
.type-badge.kitchen { background: #FFF3E0; color: #F57C00; border: 1px solid #F57C00; }
.type-badge.office { background: #E3F2FD; color: #1976D2; border: 1px solid #1976D2; }
.type-badge.lab { background: #FFEBEE; color: #D32F2F; border: 1px solid #D32F2F; }
.type-badge.general { background: #F3E5F5; color: #7B1FA2; border: 1px solid #7B1FA2; }

/* Status Badge */
.status-badge {
    padding: 0.3rem 0.8rem;
    border-radius: 50px;
    font-size: 0.7rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}

.status-badge.available { background: #E8F5E9; color: var(--eq-green-primary); }
.status-badge.maintenance { background: #FFF3E0; color: #F57C00; }
.status-badge.condemned { background: #FFEBEE; color: #D32F2F; }

/* Assignment Badge */
.assignment-badge {
    padding: 0.3rem 0.8rem;
    border-radius: 50px;
    font-size: 0.7rem;
    font-weight: 600;
}

.assignment-badge.assigned { background: #E8F5E9; color: var(--eq-green-primary); }
.assignment-badge.unassigned { background: #FFF3E0; color: #F57C00; }

/* Location Select */
.location-select {
    min-width: 140px;
    border-radius: 8px;
    border: 2px solid var(--eq-green-mint);
    padding: 0.3rem 0.8rem;
    font-size: 0.85rem;
    transition: all 0.3s ease;
}

.location-select:focus {
    border-color: var(--eq-green-primary);
    box-shadow: 0 0 0 0.2rem rgba(46, 125, 50, 0.15);
    outline: none;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 0.3rem;
    justify-content: flex-end;
}

.action-btn {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    transition: all 0.3s ease;
    cursor: pointer;
}

.action-btn.pdf { background: #FFEBEE; color: #D32F2F; }
.action-btn.edit { background: var(--eq-green-soft); color: var(--eq-green-primary); }
.action-btn.view { background: #E3F2FD; color: #1976D2; }
.action-btn.condemn { background: #FFF3E0; color: #F57C00; }

.action-btn:hover {
    transform: translateY(-2px);
    filter: brightness(0.9);
}

.action-btn.pdf:hover { background: #D32F2F; color: white; }
.action-btn.edit:hover { background: var(--eq-green-primary); color: white; }
.action-btn.view:hover { background: #1976D2; color: white; }
.action-btn.condemn:hover { background: #F57C00; color: white; }

/* Age Indicator */
.age-badge {
    padding: 0.2rem 0.6rem;
    border-radius: 50px;
    font-size: 0.65rem;
    font-weight: 600;
    margin-left: 0.3rem;
}

.age-badge.eligible { background: #D32F2F; color: white; }
.age-badge.warning { background: #F57C00; color: white; }
.age-badge.new { background: var(--eq-green-primary); color: white; }

/* Modal Styling */
.modal-content {
    border-radius: 24px;
    border: none;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}

.modal-header {
    border-radius: 24px 24px 0 0;
    padding: 1.5rem;
    border: none;
}

.modal-header.bg-primary { background: linear-gradient(135deg, var(--eq-green-primary) 0%, var(--eq-green-dark) 100%) !important; }
.modal-header.bg-warning { background: linear-gradient(135deg, #F57C00 0%, #E65100 100%) !important; }
.modal-header.bg-danger { background: linear-gradient(135deg, #D32F2F 0%, #B71C1C 100%) !important; }
.modal-header.bg-info { background: linear-gradient(135deg, #1976D2 0%, #0D47A1 100%) !important; }

.modal-header .modal-title {
    font-weight: 700;
    font-size: 1.3rem;
}

.modal-header .btn-close {
    opacity: 0.8;
    border-radius: 50%;
    padding: 0.5rem;
}

.modal-body {
    padding: 2rem;
}

.modal-footer {
    padding: 1.5rem;
    border-top: 1px solid var(--eq-green-mint);
}

/* Ensure alerts inside modals never fade out */
.modal .alert {
    transition: none !important;
    opacity: 1 !important;
    visibility: visible !important;
    display: block !important;
}

/* Form Elements */
.form-label {
    font-weight: 600;
    color: var(--eq-green-dark);
    font-size: 0.9rem;
    margin-bottom: 0.3rem;
}

.form-control, .form-select {
    border-radius: 12px;
    border: 2px solid var(--eq-green-mint);
    padding: 0.6rem 1rem;
    transition: all 0.3s ease;
}

.form-control:focus, .form-select:focus {
    border-color: var(--eq-green-primary);
    box-shadow: 0 0 0 0.25rem rgba(46, 125, 50, 0.15);
}

/* Equipment Type Cards */
.equipment-type-card {
    border: 2px solid var(--eq-green-mint);
    border-radius: 16px;
    padding: 2rem 1rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    background: white;
    height: 100%;
}

.equipment-type-card:hover {
    border-color: var(--eq-green-primary);
    box-shadow: 0 10px 30px rgba(46, 125, 50, 0.15);
    transform: translateY(-5px);
}

.equipment-type-icon {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1rem;
    color: white;
}

.equipment-type-icon.computer { background: linear-gradient(135deg, var(--eq-green-primary) 0%, var(--eq-green-secondary) 100%); }
.equipment-type-icon.kitchen { background: linear-gradient(135deg, #F57C00 0%, #FFB74D 100%); }
.equipment-type-icon.office { background: linear-gradient(135deg, #1976D2 0%, #64B5F6 100%); }
.equipment-type-icon.lab { background: linear-gradient(135deg, #D32F2F 0%, #F44336 100%); }
.equipment-type-icon.general { background: linear-gradient(135deg, #7B1FA2 0%, #BA68C8 100%); }

/* Add Method Cards */
.add-method-card {
    border: 2px solid var(--eq-green-mint);
    border-radius: 16px;
    padding: 1.5rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    background: white;
    height: 100%;
}

.add-method-card:hover {
    border-color: var(--eq-green-primary);
    box-shadow: 0 10px 30px rgba(46, 125, 50, 0.15);
    transform: translateY(-5px);
}

.add-method-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1rem;
    color: white;
}

.add-method-icon.manual { background: linear-gradient(135deg, var(--eq-green-primary) 0%, var(--eq-green-secondary) 100%); }
.add-method-icon.csv { background: linear-gradient(135deg, #F57C00 0%, #FFB74D 100%); }

/* Empty State */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
}

.empty-state-icon {
    width: 120px;
    height: 120px;
    border-radius: 60px;
    background: var(--eq-green-soft);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 2rem;
    font-size: 3rem;
    color: var(--eq-green-primary);
}

/* Unit Badge */
.badge i {
    font-size: 0.7rem;
}

.bg-primary.bg-opacity-10 { background-color: rgba(13, 110, 253, 0.1) !important; }
.bg-success.bg-opacity-10 { background-color: rgba(25, 135, 84, 0.1) !important; }
.bg-info.bg-opacity-10 { background-color: rgba(13, 202, 240, 0.1) !important; }
.bg-warning.bg-opacity-10 { background-color: rgba(255, 193, 7, 0.1) !important; }

/* Prevent PRS preview modal alerts from ever disappearing */
#prsPreviewModalAllEquipment .alert.prs-preview-alert {
    display: block !important;
    opacity: 1 !important;
    visibility: visible !important;
    transition: none !important;
}

.action-btn.delete { background: #FEE2E2; color: #DC2626; }
.action-btn.delete:hover { background: #DC2626; color: white; }

/* Responsive */
@media (max-width: 768px) {
    .page-header {
        padding: 1.5rem;
    }
    
    .header-title {
        font-size: 1.8rem;
        flex-direction: column;
        align-items: flex-start;
    }
    
    .header-actions {
        margin-top: 1rem;
        justify-content: flex-start;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .filter-group {
        border-right: none;
        padding-right: 0;
        margin-right: 0;
        margin-bottom: 1.5rem;
    }
    
    .equipment-table thead {
        display: none;
    }
    
    .equipment-table tbody tr {
        display: block;
        margin-bottom: 1rem;
        border: 1px solid var(--eq-green-mint);
        border-radius: 12px;
    }
    
    .equipment-table tbody td {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.8rem 1rem;
        border-bottom: 1px solid var(--eq-green-mint);
    }
    
    .equipment-table tbody td::before {
        content: attr(data-label);
        font-weight: 600;
        color: var(--eq-green-dark);
        width: 40%;
    }
}

/* Animation */
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.stat-card, .filter-section, .bulk-actions, .table-container {
    animation: slideIn 0.5s ease-out forwards;
}
</style>

<!-- Page Header -->
<div class="page-header">
    <div class="header-content">
        <div class="row align-items-center">
            <div class="col-lg-7">
                <div class="header-title">
                    <i class="fas fa-list-alt"></i>
                    <span>All Equipment Management</span>
                </div>
                <p class="header-subtitle">
                    Comprehensive view and management of all equipment across all categories. 
                    Filter, assign, and track equipment inventory in real-time.
                </p>
                <div class="header-stats">
                    <div class="header-stat-item">
                        <span class="header-stat-number"><?php echo $total_equipment; ?></span>
                        <span class="header-stat-label">Total Equipment</span>
                    </div>
                    <div class="header-stat-item">
                        <span class="header-stat-number"><?php echo $assigned_count; ?></span>
                        <span class="header-stat-label">Assigned</span>
                    </div>
                    <div class="header-stat-item">
                        <span class="header-stat-number"><?php echo $maintenance_count; ?></span>
                        <span class="header-stat-label">Maintenance</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="header-actions">
                    <button type="button" class="btn" data-bs-toggle="modal" data-bs-target="#parReportModal">
                        <i class="fas fa-file-signature me-2"></i>PAR
                    </button>
                    <button type="button" class="btn" data-bs-toggle="modal" data-bs-target="#inventoryReportModal">
                        <i class="fas fa-file-alt me-2"></i>Report
                    </button>
                    <button type="button" class="btn" onclick="openTransferModal()">
                        <i class="fas fa-exchange-alt me-2"></i>Transfer
                    </button>
                    <a href="transfer_history.php" class="btn">
                        <i class="fas fa-history me-2"></i>History
                    </a>
                    <button type="button" class="btn" data-bs-toggle="modal" data-bs-target="#addEquipmentModal">
                        <i class="fas fa-plus-circle me-2"></i>Add Equipment
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Alert Messages -->
<?php if (isset($_SESSION['equipment_success'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-2"></i>
    <?php echo $_SESSION['equipment_success']; unset($_SESSION['equipment_success']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (isset($_SESSION['equipment_error'])): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-circle me-2"></i>
    <?php echo $_SESSION['equipment_error']; unset($_SESSION['equipment_error']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (isset($_SESSION['csv_success'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-2"></i>
    <?php echo $_SESSION['csv_success']; unset($_SESSION['csv_success']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (isset($_SESSION['csv_error'])): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-circle me-2"></i>
    <?php echo $_SESSION['csv_error']; unset($_SESSION['csv_error']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (isset($_SESSION['csv_errors'])): ?>
<div class="alert alert-warning alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-triangle me-2"></i>
    <strong>CSV Import Errors:</strong>
    <ul class="mb-0 mt-2">
        <?php foreach ($_SESSION['csv_errors'] as $error): ?>
        <li><?php echo htmlspecialchars($error); ?></li>
        <?php endforeach; ?>
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php unset($_SESSION['csv_errors']); ?>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon primary">
            <i class="fas fa-boxes"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $total_equipment; ?></h3>
            <p>Total Equipment</p>
            <div class="stat-trend">
                <i class="fas fa-arrow-up"></i> All categories
            </div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon success">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $assigned_count; ?></h3>
            <p>Assigned to Rooms</p>
            <div class="stat-trend">
                <i class="fas fa-map-marker-alt"></i> Currently located
            </div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon warning">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $unassigned_count; ?></h3>
            <p>Unassigned</p>
            <div class="stat-trend">
                <i class="fas fa-clock"></i> Needs assignment
            </div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon danger">
            <i class="fas fa-tools"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $maintenance_count; ?></h3>
            <p>Under Maintenance</p>
            <div class="stat-trend">
                <i class="fas fa-wrench"></i> In repair
            </div>
        </div>
    </div>
</div>

<!-- Filter Section -->
<div class="filter-section">
    <div class="filter-title">
        <i class="fas fa-filter"></i>
        Filter Equipment
        <span class="filter-badge" id="filterBadge"><?php echo $total_equipment; ?> items</span>
    </div>
    
    <!-- Filter Section - Add this new filter group after the Status filter group -->
    <div class="row">
        <div class="col-md-3 filter-group"> <!-- Changed from col-md-4 to accommodate 4 groups -->
            <div class="filter-label">Equipment Type</div>
            <div class="filter-btn-group">
                <button class="filter-btn <?php echo empty($type_filter) ? 'active' : ''; ?>" onclick="filterByType('')">All</button>
                <button class="filter-btn <?php echo $type_filter === 'computer_lab' ? 'active' : ''; ?>" onclick="filterByType('computer_lab')">Computer</button>
                <button class="filter-btn <?php echo $type_filter === 'kitchen' ? 'active' : ''; ?>" onclick="filterByType('kitchen')">Kitchen</button>
                <button class="filter-btn <?php echo $type_filter === 'office' ? 'active' : ''; ?>" onclick="filterByType('office')">Office</button>
                <button class="filter-btn <?php echo $type_filter === 'regular_lab' ? 'active' : ''; ?>" onclick="filterByType('regular_lab')">Lab</button>
                <button class="filter-btn <?php echo $type_filter === 'general' ? 'active' : ''; ?>" onclick="filterByType('general')">General</button>
            </div>
        </div>
        
        <div class="col-md-3 filter-group">
            <div class="filter-label">Campus</div>
            <div class="filter-btn-group">
                <button class="filter-btn <?php echo empty($campus_filter) ? 'active' : ''; ?>" onclick="filterByCampus('')">All Campuses</button>
                <button class="filter-btn <?php echo $campus_filter === 'South Campus' ? 'active' : ''; ?>" onclick="filterByCampus('South Campus')">South</button>
                <button class="filter-btn <?php echo $campus_filter === 'Congressional Campus' ? 'active' : ''; ?>" onclick="filterByCampus('Congressional Campus')">Congressional</button>
                <button class="filter-btn <?php echo $campus_filter === 'Camarin Campus' ? 'active' : ''; ?>" onclick="filterByCampus('Camarin Campus')">Camarin</button>
                <button class="filter-btn <?php echo $campus_filter === 'Bagong Silang Campus' ? 'active' : ''; ?>" onclick="filterByCampus('Bagong Silang Campus')">Bagong Silang</button>
            </div>
        </div>
        
        <div class="col-md-3 filter-group">
            <div class="filter-label">Location</div>
            <div class="filter-btn-group">
                <button class="filter-btn <?php echo empty($location_filter) ? 'active' : ''; ?>" onclick="filterByLocation('')">All Locations</button>
                <?php foreach (array_slice($locations, 0, 3) as $location): ?>
                <button class="filter-btn <?php echo $location_filter == $location['id'] ? 'active' : ''; ?>" onclick="filterByLocation('<?php echo $location['id']; ?>')">
                    <?php echo htmlspecialchars($location['location_name']); ?>
                </button>
                <?php endforeach; ?>
                <?php if (count($locations) > 3): ?>
                <button class="filter-btn" data-bs-toggle="modal" data-bs-target="#locationFilterModal">More...</button>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="col-md-3 filter-group">
            <div class="filter-label">Status</div>
            <div class="filter-btn-group">
                <button class="filter-btn <?php echo empty($status_filter) ? 'active' : ''; ?>" onclick="filterByStatus('')">All</button>
                <button class="filter-btn <?php echo $status_filter === 'available' ? 'active' : ''; ?>" onclick="filterByStatus('available')">Available</button>
                <button class="filter-btn <?php echo $status_filter === 'maintenance' ? 'active' : ''; ?>" onclick="filterByStatus('maintenance')">Maintenance</button>
                <button class="filter-btn <?php echo $status_filter === 'condemned' ? 'active' : ''; ?>" onclick="filterByStatus('condemned')">Condemned</button>
            </div>
        </div>
    </div>
    
    <div class="row mt-3">
        <div class="col-12">
            <div class="d-flex justify-content-end">
                <a href="all_equipment.php" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-times me-1"></i>Clear Filters
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Actions -->
<div class="bulk-actions">
    <div class="bulk-title">
        <i class="fas fa-tasks"></i>
        Bulk Actions
        <span class="selection-badge" id="selectedCount">0 selected</span>
    </div>
    
    <div class="row align-items-center">
        <div class="col-lg-8">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Selection Tools</label>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="selectAll()">
                            <i class="fas fa-check-double me-1"></i>All
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="selectUnassigned()">
                            <i class="fas fa-question-circle me-1"></i>Unassigned
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearSelection()">
                            <i class="fas fa-times me-1"></i>Clear
                        </button>
                    </div>
                </div>
                
                <div class="col-md-5">
                    <label class="form-label">Assign to Location</label>
                    <div class="d-flex gap-2">
                        <select class="form-select form-select-sm" id="bulkLocationSelect">
                            <option value="">-- Select Location --</option>
                            <?php foreach ($locations as $location): ?>
                            <option value="<?php echo $location['id']; ?>">
                                <?php echo htmlspecialchars($location['location_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn btn-success btn-sm" onclick="executeBulkAssignment()" id="executeBulkBtn" disabled>
                            <i class="fas fa-check me-1"></i>Apply
                        </button>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Download</label>
                    <button type="button" class="btn btn-success btn-sm w-100" id="downloadPRSBtn" onclick="downloadPRSForm()" disabled>
                        <i class="fas fa-file-excel me-1"></i>PRS Form
                    </button>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="selection-info">
                <i class="fas fa-info-circle me-2"></i>
                <span>Select equipment to perform bulk operations</span>
            </div>
        </div>
    </div>
</div>

<!-- Equipment Table -->
<div class="table-container">
    <div class="table-header">
        <div class="table-title">
            <i class="fas fa-list"></i>
            Equipment Inventory
            <span class="table-badge" id="tableBadge"><?php echo $total_equipment; ?> items</span>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <!-- Show entries -->
            <div class="d-flex align-items-center gap-1">
                <label class="text-muted small mb-0 text-nowrap">Show</label>
                <select id="perPageSelect" class="form-select form-select-sm" style="width:80px;">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50" selected>50</option>
                    <option value="100">100</option>
                </select>
                <span class="text-muted small mb-0 text-nowrap">entries</span>
            </div>
            <!-- Search -->
            <div class="input-group input-group-sm" style="width:280px;">
                <span class="input-group-text bg-white border-end-0">
                    <i class="fas fa-search text-muted"></i>
                </span>
                <input type="text" id="equipmentSearch" class="form-control border-start-0"
                       placeholder="Search item #, name, serial #, property #..."
                       value="<?php echo htmlspecialchars($search); ?>">
                <button class="btn btn-outline-secondary btn-sm" type="button" id="clearSearchBtn"
                        style="<?php echo $search ? '' : 'display:none;'; ?>"
                        onclick="clearSearch()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    </div>
    
    <div class="table-responsive">
        <table class="equipment-table" id="equipmentTable">
            <thead>
                <tr>
                    <th width="40">
                        <input type="checkbox" class="form-check-input" id="selectAllCheckbox" onchange="toggleSelectAll()">
                    </th>
                    <th>Equipment</th>
                    <th>Type</th>
                    <th>Unit</th>
                    <th>Serial/Property No.</th>
                    <th>Accountable Person</th>
                    <th>Status</th>
                    <th>Assignment</th>
                    <th>Location</th>
                    <th>Last Updated</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody id="equipmentTableBody">
                <!-- Rows are loaded via AJAX lazy-load (get_equipment_ajax.php) -->
                <tr id="initialLoader">
                    <td colspan="11" class="text-center py-5">
                        <div class="d-flex flex-column align-items-center gap-2">
                            <div class="spinner-border text-success" role="status"></div>
                            <span class="text-muted">Loading equipment...</span>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Load More Button -->
<div class="text-center my-3" id="loadMoreContainer" style="display:none;">
    <button class="btn btn-outline-success px-4" id="loadMoreBtn" onclick="loadMoreEquipment()">
        <i class="fas fa-chevron-down me-2"></i>
        Load More <span id="loadMoreCount"></span>
    </button>
</div>

<!-- End of list indicator -->
<div class="text-center text-muted small py-2" id="endOfListMsg" style="display:none;">
    <i class="fas fa-check-circle me-1 text-success"></i> All equipment loaded
</div>

<!-- Showing X of Y -->
<div class="text-center text-muted small pb-3" id="loadedInfo" style="opacity:0.7;"></div>

<!-- Hidden form for bulk assignment -->
<form method="POST" id="bulkAssignForm" style="display: none;">
    <input type="hidden" name="action" value="bulk_assign">
    <input type="hidden" name="equipment_items" id="hiddenEquipmentItems">
    <input type="hidden" name="bulk_location_id" id="hiddenLocationId">
</form>

<!-- Add Equipment Modal -->
<div class="modal fade" id="addEquipmentModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalTitle">
                    <i class="fas fa-plus-circle me-2"></i>
                    Add New Equipment
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="modalBody">
                
                <!-- Step 1: Select Category -->
                <div id="step1" class="modal-step">
                    <div class="text-center mb-4">
                        <h5 class="fw-bold">Select Equipment Category</h5>
                        <p class="text-muted">Choose the type of equipment you want to add to inventory</p>
                    </div>
                    <div class="row g-4">
                        <div class="col-md-4">
                            <div class="equipment-type-card" data-equipment-type="computer">
                                <div class="equipment-type-icon computer">
                                    <i class="fas fa-desktop fa-2x"></i>
                                </div>
                                <h6 class="fw-bold mb-0">Computer</h6>
                                <small class="text-muted">Desktops, Laptops, All-in-One</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="equipment-type-card" data-equipment-type="kitchen">
                                <div class="equipment-type-icon kitchen">
                                    <i class="fas fa-utensils fa-2x"></i>
                                </div>
                                <h6 class="fw-bold mb-0">Kitchen</h6>
                                <small class="text-muted">Kitchen appliances & equipment</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="equipment-type-card" data-equipment-type="office">
                                <div class="equipment-type-icon office">
                                    <i class="fas fa-briefcase fa-2x"></i>
                                </div>
                                <h6 class="fw-bold mb-0">Office</h6>
                                <small class="text-muted">Office furniture & equipment</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="equipment-type-card" data-equipment-type="lab">
                                <div class="equipment-type-icon lab">
                                    <i class="fas fa-flask fa-2x"></i>
                                </div>
                                <h6 class="fw-bold mb-0">Laboratory</h6>
                                <small class="text-muted">Lab equipment & instruments</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="equipment-type-card" data-equipment-type="general">
                                <div class="equipment-type-icon general">
                                    <i class="fas fa-tools fa-2x"></i>
                                </div>
                                <h6 class="fw-bold mb-0">General</h6>
                                <small class="text-muted">Miscellaneous items</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Step 2: Choose Addition Method -->
                <div id="step2" class="modal-step" style="display: none;">
                    <div id="selectedTypeDisplay" class="bg-light p-3 rounded mb-4 text-center">
                        <div id="selectedTypeIcon" class="equipment-type-icon-small mb-2"></div>
                        <h5 id="selectedTypeName" class="fw-bold mb-0"></h5>
                    </div>
                    <div class="row g-4 justify-content-center">
                        <div class="col-md-8">
                            <div class="add-method-card" data-add-method="manual">
                                <div class="add-method-icon manual">
                                    <i class="fas fa-edit fa-2x"></i>
                                </div>
                                <h6 class="fw-bold">Manual Entry</h6>
                                <p class="small text-muted mb-0">Fill in details for a single item</p>
                            </div>
                        </div>
                        <!-- CSV Upload option removed -->
                    </div>
                    <div class="text-center mt-4">
                        <button class="btn btn-link text-decoration-none" onclick="showStep(1)">
                            <i class="fas fa-arrow-left me-1"></i> Back to Categories
                        </button>
                    </div>
                </div>
                
                <!-- Step 3: Manual Entry Form -->
                <div id="step3" class="modal-step" style="display: none;">
                    <form method="POST" enctype="multipart/form-data" id="manualAddForm">
                        <input type="hidden" name="action" value="add_equipment">
                        <input type="hidden" name="equipment_type" id="manual_equipment_type">
                        
                        <div id="manualFormFields" class="row g-3">
                            <!-- Dynamic fields will be inserted here -->
                        </div>
                        
                        <div class="border-top pt-4 mt-4 d-flex justify-content-between">
                            <button type="button" class="btn btn-outline-secondary" onclick="showStep(2)">
                                <i class="fas fa-arrow-left me-2"></i>Back
                            </button>
                            <button type="submit" class="btn btn-success px-4">
                                <i class="fas fa-save me-2"></i>Add to Inventory
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Step 4: CSV Upload -->
                <div id="step4" class="modal-step" style="display: none;">
                    <form method="POST" enctype="multipart/form-data" action="process_equipment_csv.php">
                        <input type="hidden" name="action" value="upload_csv">
                        <input type="hidden" name="equipment_type" id="csv_equipment_type">
                        
                        <div class="text-center mb-4">
                            <div class="add-method-icon csv mx-auto mb-3" style="width: 80px; height: 80px;">
                                <i class="fas fa-file-csv fa-3x"></i>
                            </div>
                            <h6 class="fw-bold">Upload <span id="csvEquipmentType">Equipment</span> File</h6>
                            <p class="small text-muted">Supports .csv and .xlsx formats</p>
                        </div>
                        
                        <div class="alert alert-info border-0 small">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Format Guide:</strong> First row should contain column headers matching the database fields.
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Target Campus</label>
                            <select class="form-select" name="target_campus" required>
                                <option value="South Campus">South Campus</option>
                                <option value="Congressional Campus">Congressional Campus</option>
                                <option value="Bagong Silang Campus" selected>Bagong Silang Campus</option>
                                <option value="Camarin Campus">Camarin Campus</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Select File</label>
                            <input type="file" class="form-control" name="csv_file" id="fileInput" accept=".csv, .xlsx" required>
                        </div>
                        
                        <div id="workbookSection" class="mb-3 p-3 border rounded bg-light" style="display: none;">
                            <label class="form-label fw-bold">Select Worksheet</label>
                            <select class="form-select" name="selected_sheet" id="sheetSelector"></select>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="has_header" id="has_header" checked>
                                <label class="form-check-label" for="has_header">First row contains headers</label>
                            </div>
                        </div>
                        
                        <div class="border-top pt-4 mt-4 d-flex justify-content-between">
                            <button type="button" class="btn btn-outline-secondary" onclick="showStep(2)">
                                <i class="fas fa-arrow-left me-2"></i>Back
                            </button>
                            <button type="submit" class="btn btn-success px-4">
                                <i class="fas fa-upload me-2"></i>Upload & Import
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- View Details Modal -->
<div class="modal fade" id="viewDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">
                    <i class="fas fa-info-circle me-2"></i>
                    Equipment Specifications
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="detailsModalBody">
                <!-- Dynamic content will be loaded here -->
                <div class="text-center py-5">
                    <div class="spinner-border text-info mb-3"></div>
                    <p class="text-muted">Loading equipment details...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Equipment Modal -->
<div class="modal fade" id="editEquipmentModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title">
                    <i class="fas fa-edit me-2"></i>
                    Edit Equipment Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="editModalBody">
                <!-- Dynamic content will be loaded here -->
                <div class="text-center py-5">
                    <div class="spinner-border text-warning mb-3"></div>
                    <p class="text-muted">Loading edit form...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Condemn Equipment Modal -->
<div class="modal fade" id="condemnModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Condemn Equipment
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="condemnForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="condemn_equipment">
                    <input type="hidden" name="equipment_id" id="condemnEquipmentId">
                    <input type="hidden" name="equipment_type" id="condemnEquipmentType">
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Warning:</strong> This action will mark the equipment as condemned and remove it from active inventory. This cannot be undone.
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Equipment</label>
                        <p class="form-control-plaintext" id="condemnEquipmentName"></p>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Reason for Condemnation <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="condemn_reason" id="condemn_reason" rows="4" 
                                  placeholder="Enter detailed reason why this equipment is being condemned..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <button type="button" class="btn btn-danger" onclick="confirmCondemn()">
                        <i class="fas fa-exclamation-triangle me-2"></i>Condemn Equipment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Transfer Modal -->
<div class="modal fade" id="transferModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title">
                    <i class="fas fa-exchange-alt me-2"></i>
                    Transfer Inventory
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="process_transfer.php" method="POST" id="transferForm">
                <div class="modal-body p-4">
                    <div id="transferSelectionError" class="alert alert-danger d-none">
                        Please select items from the table first.
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold">Selected Items (<span id="transferItemCount">0</span>)</label>
                        <div id="selectedItemsList" class="p-3 bg-light rounded border small" style="max-height: 200px; overflow-y: auto;">
                            <!-- Selected items will be listed here -->
                        </div>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Transfer to Campus</label>
                            <select class="form-select" name="to_campus" required>
                                <option value="" selected disabled>Select Destination</option>
                                <option value="South Campus">South Campus</option>
                                <option value="Congressional Campus">Congressional Campus</option>
                                <option value="Camarin Campus">Camarin Campus</option>
                                <option value="Bagong Silang Campus">Bagong Silang Campus</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">New Accountable Person</label>
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <input type="text" name="new_accountable_lastname" class="form-control" placeholder="Last Name" required>
                                </div>
                                <div class="col-md-4">
                                    <input type="text" name="new_accountable_firstname" class="form-control" placeholder="First Name" required>
                                </div>
                                <div class="col-md-4">
                                    <input type="text" name="new_accountable_middle" class="form-control" placeholder="M.I." maxlength="2">
                                </div>
                            </div>
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i> Will be saved as "Last Name, First Name M.I."
                            </small>
                        </div>
                        <input type="hidden" name="selected_ids" id="transferIdsInput">
                        <input type="hidden" name="equipment_type" id="transferTypeInput">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-warning px-4" onclick="return confirm('Confirm inventory transfer?')">
                        <i class="fas fa-exchange-alt me-2"></i>Execute Transfer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Inventory Report Modal -->
<div class="modal fade" id="inventoryReportModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, var(--eq-green-primary) 0%, var(--eq-green-dark) 100%);">
                <h5 class="modal-title text-white">
                    <i class="fas fa-file-invoice me-2"></i>
                    Generate Inventory Report
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="generate_inventory_report.php" method="POST" target="_blank">
                <div class="modal-body p-4">
                    <div class="text-center mb-4">
                        <div class="bg-light d-inline-block p-3 rounded-circle mb-2">
                            <i class="fas fa-chart-pie fa-2x" style="color: var(--eq-green-primary);"></i>
                        </div>
                        <h6 class="fw-bold" style="color: var(--eq-green-dark);">Report Configuration</h6>
                        <p class="small text-muted">Select filters to generate a customized inventory report</p>
                    </div>
                    
                    <!-- Report Type Selection -->
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-uppercase" style="color: var(--eq-green-dark);">
                            <i class="fas fa-filter me-1"></i>1. Select Report Type
                        </label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="report_mode" id="modeCampus" value="campus" checked onchange="toggleReportMode()">
                            <label class="btn btn-outline-success" for="modeCampus" style="border-color: var(--eq-green-mint);">
                                <i class="fas fa-university me-2"></i>By Campus
                            </label>

                            <input type="radio" class="btn-check" name="report_mode" id="modeAccountable" value="accountable" onchange="toggleReportMode()">
                            <label class="btn btn-outline-success" for="modeAccountable" style="border-color: var(--eq-green-mint);">
                                <i class="fas fa-user-tie me-2"></i>By Person
                            </label>
                        </div>
                    </div>

                    <!-- Campus Selection Section -->
                    <div id="campusSection" class="mb-4">
                        <label class="form-label small fw-bold text-uppercase" style="color: var(--eq-green-dark);">
                            <i class="fas fa-map-marker-alt me-1"></i>2. Select Campus
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-white" style="border-color: var(--eq-green-mint);">
                                <i class="fas fa-university" style="color: var(--eq-green-primary);"></i>
                            </span>
                            <select class="form-select" name="filter_campus" style="border-color: var(--eq-green-mint);">
                                <option value="ALL">All Campuses</option>
                                <option value="South Campus">South Campus</option>
                                <option value="Congressional Campus">Congressional Campus</option>
                                <option value="Bagong Silang Campus">Bagong Silang Campus</option>
                                <option value="Camarin Campus">Camarin Campus</option>
                            </select>
                        </div>
                        <div class="form-text text-muted small">
                            <i class="fas fa-info-circle me-1"></i>Select a specific campus or include all
                        </div>
                    </div>

                    <!-- Accountable Person Selection Section (hidden by default) -->
                    <div id="accountableSection" class="mb-4" style="display: none;">
                        <label class="form-label small fw-bold text-uppercase" style="color: var(--eq-green-dark);">
                            <i class="fas fa-user me-1"></i>2. Select Accountable Person
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-white" style="border-color: var(--eq-green-mint);">
                                <i class="fas fa-user-tie" style="color: var(--eq-green-primary);"></i>
                            </span>
                            <select class="form-select" name="filter_remarks" id="remarksSelect" style="border-color: var(--eq-green-mint);">
                                <option value="">Loading accountable persons...</option>
                            </select>
                        </div>
                        <div class="form-text text-muted small">
                            <i class="fas fa-info-circle me-1"></i>Select a specific person or include all
                        </div>
                    </div>

                    <!-- Status Filter -->
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-uppercase" style="color: var(--eq-green-dark);">
                            <i class="fas fa-tag me-1"></i>3. Filter by Status
                        </label>
                        <select class="form-select" name="filter_status" style="border-color: var(--eq-green-mint);">
                            <option value="ALL">All Statuses</option>
                            <option value="available">Available Only</option>
                            <option value="maintenance">Under Maintenance</option>
                            <option value="condemned">Condemned Items</option>
                        </select>
                    </div>

                    <!-- Summary Card -->
                    <div class="alert" style="background: var(--eq-green-soft); border-left: 4px solid var(--eq-green-primary);">
                        <div class="d-flex">
                            <div class="me-3">
                                <i class="fas fa-info-circle" style="color: var(--eq-green-primary); font-size: 1.2rem;"></i>
                            </div>
                            <div>
                                <small class="fw-bold d-block mb-1" style="color: var(--eq-green-dark);">Report Summary</small>
                                <small class="text-muted d-block" id="reportSummary">Showing all equipment from all campuses</small>
                                <small class="text-muted d-block mt-1">
                                    <i class="fas fa-file-pdf me-1"></i>Report will open in a new tab
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="border-top-color: var(--eq-green-mint);">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-success px-4" style="background: linear-gradient(135deg, var(--eq-green-primary) 0%, var(--eq-green-dark) 100%); border: none;">
                        <i class="fas fa-file-pdf me-2"></i>Generate Report
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- PAR Report Modal -->
<div class="modal fade" id="parReportModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, var(--eq-green-primary) 0%, var(--eq-green-dark) 100%);">
                <h5 class="modal-title text-white">
                    <i class="fas fa-file-signature me-2"></i>
                    Generate PAR Report
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="generate_par_report.php" method="POST" target="_blank" id="parForm">
                <div class="modal-body p-4">
                    <div class="text-center mb-4">
                        <div class="bg-light d-inline-block p-3 rounded-circle mb-2">
                            <i class="fas fa-file-pdf fa-2x" style="color: var(--eq-green-primary);"></i>
                        </div>
                        <h6 class="fw-bold" style="color: var(--eq-green-dark);">Property Acknowledgement Receipt</h6>
                        <p class="small text-muted">Select an accountable person to generate PAR</p>
                    </div>
                    
                    <!-- Hidden field to set source as accountable -->
                    <input type="hidden" name="par_source" value="accountable">
                    
                    <!-- Accountable Person Selection -->
                    <div class="mb-4" id="parAccountableSection">
                        <label class="form-label small fw-bold text-uppercase" style="color: var(--eq-green-dark);">
                            <i class="fas fa-user me-1"></i>Select Accountable Person <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-white" style="border-color: var(--eq-green-mint);">
                                <i class="fas fa-user-tie" style="color: var(--eq-green-primary);"></i>
                            </span>
                            <select class="form-select" name="par_accountable_person" id="parAccountableSelect" required>
                                <option value="">-- Select Accountable Person --</option>
                                <?php
                                // Fetch unique accountable persons from all equipment tables
                                $accountable_persons = [];
                                
                                // From computer_inventory
                                $comp_query = "SELECT DISTINCT remarks FROM computer_inventory WHERE remarks IS NOT NULL AND remarks != '' AND remarks != 'None' AND remarks != 'N/A' AND (is_condemned = 0 OR is_condemned IS NULL)";
                                $comp_stmt = $db->query($comp_query);
                                while($row = $comp_stmt->fetch(PDO::FETCH_ASSOC)) {
                                    if (!empty($row['remarks']) && $row['remarks'] !== 'N/A' && $row['remarks'] !== 'None Assigned' && $row['remarks'] !== 'None' && $row['remarks'] !== 'Unassigned') {
                                        $accountable_persons[] = trim($row['remarks']);
                                    }
                                }
                                
                                // From general_equipment
                                $gen_query = "SELECT DISTINCT remarks FROM general_equipment WHERE remarks IS NOT NULL AND remarks != '' AND remarks != 'None' AND remarks != 'N/A' AND (is_condemned = 0 OR is_condemned IS NULL)";
                                $gen_stmt = $db->query($gen_query);
                                while($row = $gen_stmt->fetch(PDO::FETCH_ASSOC)) {
                                    if (!empty($row['remarks']) && $row['remarks'] !== 'N/A' && $row['remarks'] !== 'None Assigned' && $row['remarks'] !== 'None' && $row['remarks'] !== 'Unassigned') {
                                        $accountable_persons[] = trim($row['remarks']);
                                    }
                                }
                                
                                // From office_equipment
                                $off_query = "SELECT DISTINCT remarks FROM office_equipment WHERE remarks IS NOT NULL AND remarks != '' AND remarks != 'None' AND remarks != 'N/A' AND (is_condemned = 0 OR is_condemned IS NULL)";
                                $off_stmt = $db->query($off_query);
                                while($row = $off_stmt->fetch(PDO::FETCH_ASSOC)) {
                                    if (!empty($row['remarks']) && $row['remarks'] !== 'N/A' && $row['remarks'] !== 'None Assigned' && $row['remarks'] !== 'None' && $row['remarks'] !== 'Unassigned') {
                                        $accountable_persons[] = trim($row['remarks']);
                                    }
                                }
                                
                                // From kitchen_equipment
                                $kit_query = "SELECT DISTINCT remarks FROM kitchen_equipment WHERE remarks IS NOT NULL AND remarks != '' AND remarks != 'None' AND remarks != 'N/A' AND (is_condemned = 0 OR is_condemned IS NULL)";
                                $kit_stmt = $db->query($kit_query);
                                while($row = $kit_stmt->fetch(PDO::FETCH_ASSOC)) {
                                    if (!empty($row['remarks']) && $row['remarks'] !== 'N/A' && $row['remarks'] !== 'None Assigned' && $row['remarks'] !== 'None' && $row['remarks'] !== 'Unassigned') {
                                        $accountable_persons[] = trim($row['remarks']);
                                    }
                                }
                                
                                // From lab_equipment
                                $lab_query = "SELECT DISTINCT remarks FROM lab_equipment WHERE remarks IS NOT NULL AND remarks != '' AND remarks != 'None' AND remarks != 'N/A' AND (is_condemned = 0 OR is_condemned IS NULL)";
                                $lab_stmt = $db->query($lab_query);
                                while($row = $lab_stmt->fetch(PDO::FETCH_ASSOC)) {
                                    if (!empty($row['remarks']) && $row['remarks'] !== 'N/A' && $row['remarks'] !== 'None Assigned' && $row['remarks'] !== 'None' && $row['remarks'] !== 'Unassigned') {
                                        $accountable_persons[] = trim($row['remarks']);
                                    }
                                }
                                
                                // Remove duplicates and sort
                                $accountable_persons = array_unique($accountable_persons);
                                sort($accountable_persons);
                                
                                foreach ($accountable_persons as $person):
                                ?>
                                <option value="<?php echo htmlspecialchars($person); ?>"><?php echo htmlspecialchars($person); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-text text-muted small mt-2">
                            <i class="fas fa-info-circle me-1"></i>PAR will include all non-condemned items assigned to this person
                        </div>
                    </div>
                    
                    <!-- Hidden Fields -->
                    <input type="hidden" name="from_name" id="parFromName" value="REYNALDO H. CARANDANG JR.">
                    <input type="hidden" name="from_pos" id="parFromPos" value="AVP for Administration">
                    <input type="hidden" name="by_name" id="parByName">
                    <input type="hidden" name="by_pos" id="parByPos" value="Accountable Person">
                    <input type="hidden" name="report_date" value="<?php echo date('Y-m-d'); ?>">
                    <input type="hidden" name="selected_items" id="parSelectedItems" value="">
                    
                    <!-- Auto-fill based on selection -->
                    <div class="mb-4 p-3 rounded" style="background: var(--eq-green-soft);">
                        <h6 class="fw-bold mb-3" style="color: var(--eq-green-dark);">
                            <i class="fas fa-file-signature me-2"></i>Signatory Preview
                        </h6>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <small class="text-muted d-block">Received From:</small>
                                <strong id="previewFromName">REYNALDO H. CARANDANG JR.</strong>
                                <small class="text-muted d-block">AVP for Administration</small>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted d-block">Received By:</small>
                                <strong id="previewByName">[Select Person]</strong>
                                <small class="text-muted d-block">Accountable Person</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Info Card - REPLACING THE ALERT -->
                    <div class="card border-0 shadow-sm" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-start">
                                <div class="me-3">
                                    <div class="rounded-circle p-2" style="background: rgba(46, 125, 50, 0.1);">
                                        <i class="fas fa-info-circle" style="color: var(--eq-green-primary); font-size: 1.2rem;"></i>
                                    </div>
                                </div>
                                <div>
                                    <strong class="d-block mb-1" style="color: var(--eq-green-dark);">PAR Summary</strong>
                                    <small class="text-muted d-block" id="parSummary">Please select an accountable person</small>
                                    <small class="text-muted d-block mt-2">
                                        <i class="fas fa-file-pdf me-1"></i>Report will open in a new tab
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="border-top-color: var(--eq-green-mint);">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-success px-4" id="parSubmitBtn" disabled style="background: linear-gradient(135deg, var(--eq-green-primary) 0%, var(--eq-green-dark) 100%); border: none;">
                        <i class="fas fa-file-pdf me-2"></i>Generate PAR
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Location Filter Modal (for more locations) -->
<div class="modal fade" id="locationFilterModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-map-marker-alt me-2"></i>
                    Select Location
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="list-group">
                    <button class="list-group-item list-group-item-action" onclick="filterByLocation('')">
                        All Locations
                    </button>
                    <?php foreach ($locations as $location): ?>
                    <button class="list-group-item list-group-item-action" onclick="filterByLocation('<?php echo $location['id']; ?>')">
                        <?php echo htmlspecialchars($location['location_name']); ?>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- PRS Preview Modal for All Equipment -->
<div class="modal fade" id="prsPreviewModalAllEquipment" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="fas fa-file-excel me-2"></i>
                    PRS Form Preview
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-info border-0 mb-3 prs-preview-alert">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Preview of selected items (<span id="previewItemCountAllEquipment">0</span>):</strong> 
                    Please review the items below before generating the PRS form.
                </div>
                
                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-bordered table-hover table-sm">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>#</th>
                                <th>QTY</th>
                                <th>UNIT</th>
                                <th>DESCRIPTION</th>
                                <th>YEAR</th>
                                <th>PROP NO</th>
                                <th>END-USER</th>
                                <th>UNIT VALUE</th>
                            </tr>
                        </thead>
                        <tbody id="prsPreviewBodyAllEquipment">
                            <!-- Will be populated by JavaScript -->
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-3 p-3 bg-light rounded">
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Total Items:</strong> <span id="previewTotalItemsAllEquipment">0</span>
                        </div>
                        <div class="col-md-6 text-end">
                            <strong>Total Value:</strong> <span id="totalValueAllEquipment">₱ 0.00</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <button type="button" class="btn btn-success" id="confirmDownloadBtnAllEquipment">
                    <i class="fas fa-file-excel me-2"></i>Generate PRS Form
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Manual Condemn Equipment Modal -->
<div class="modal fade" id="manualCondemnModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="fas fa-times-circle me-2"></i>
                    Manual Condemn Equipment
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="manualCondemnForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="manual_condemn">
                    <input type="hidden" name="equipment_id" id="manual_condemn_equipment_id">
                    <input type="hidden" name="equipment_type" id="manual_condemn_equipment_type">
                    
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Warning:</strong> This action will permanently move this equipment to the condemned_equipment table. This indicates the equipment is broken, unusable, or no longer functioning.
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Equipment</label>
                        <p class="form-control-plaintext bg-light p-2 rounded" id="manual_condemn_equipment_name"></p>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Category <span class="text-danger">*</span></label>
                        <select class="form-select" name="condemn_category" id="condemn_category" required>
                            <option value="">-- Select Category --</option>
                            <option value="System Unit">System Unit</option>
                            <option value="Monitor">Monitor</option>
                            <option value="All in one">All in One</option>
                            <option value="Keyboard">Keyboard</option>
                            <option value="AVR">AVR</option>
                            <option value="Other">Other</option>
                        </select>
                        <small class="text-muted">Select the appropriate category for this condemned item</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Reason for Condemnation <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="condemn_reason" id="manual_condemn_reason" rows="4" 
                                  placeholder="Describe why this equipment is being condemned (e.g., broken screen, power failure, no longer works, etc.)" required></textarea>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Note:</strong> This equipment will be:
                        <ul class="mb-0 mt-2">
                            <li>Copied to the condemned_equipment table with status 'pending'</li>
                            <li>Marked as condemned in the current inventory</li>
                            <li>Viewable in <strong>Condemned Equipment</strong> page</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to condemn this equipment? This action cannot be undone.')">
                        <i class="fas fa-times-circle me-2"></i>Confirm Condemnation
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let selectedEquipmentType = '';
let selectedEquipment = [];

// Equipment type configurations
const equipmentTypes = {
    computer: {
        name: 'Computer Equipment',
        icon: 'fas fa-desktop',
        color: 'primary',
        table: 'computer_inventory',
        fields: [
            { name: 'article', label: 'Article (Device Type)', type: 'select', required: true, options: [] }, // Options will be loaded dynamically
            { name: 'computer_set_description', label: 'Description', type: 'text', required: true },
            { name: 'processor', label: 'Processor', type: 'text', required: true },
            { name: 'ram', label: 'RAM', type: 'text', required: true },
            { name: 'storage', label: 'Storage', type: 'text', required: true },
            { name: 'unit', label: 'Unit', type: 'unit_select', required: true },
            { name: 'campus', label: 'Campus', type: 'campus_select', required: true },
            { name: 'location_id', label: 'Location', type: 'location_select', required: false },
            { name: 'purchase_date', label: 'Date of Purchase', type: 'purchase_date_toggle', required: false },
            { name: 'operating_system', label: 'Operating System', type: 'text', required: false },
            { name: 'property_no', label: 'Property Number', type: 'text', required: true },
            { name: 'condition_status', label: 'Condition', type: 'select', required: true, options: ['Excellent', 'Good', 'Fair', 'Poor', 'Damaged'] },
            { name: 'accountable_person', label: 'Accountable Person', type: 'accountable_name', required: false },
            { name: 'cost', label: 'Cost (₱)', type: 'number', step: '0.01', required: false }
        ]
    },
    kitchen: {
        name: 'Kitchen Equipment',
        icon: 'fas fa-utensils',
        color: 'warning',
        table: 'kitchen_equipment',
        fields: [
            { name: 'equipment_name', label: 'Equipment Name', type: 'text', required: true },
            { name: 'description', label: 'Description', type: 'textarea', required: false }, // NEW LINE
            { name: 'brand', label: 'Brand', type: 'text', required: false },
            { name: 'model', label: 'Model', type: 'text', required: false },
            { name: 'unit', label: 'Unit', type: 'unit_select', required: true },
            { name: 'serial_number', label: 'Serial Number', type: 'text', required: true },
            { name: 'property_no', label: 'Property Number', type: 'text', required: false },
            { name: 'cost', label: 'Cost (₱)', type: 'number', step: '0.01', required: false },
            { name: 'purchase_date', label: 'Date of Purchase', type: 'purchase_date_toggle', required: false },
            { name: 'campus', label: 'Campus', type: 'campus_select', required: true },
            { name: 'location_id', label: 'Location', type: 'location_select', required: false },
            { name: 'condition_status', label: 'Condition', type: 'select', required: true, options: ['Excellent', 'Good', 'Fair', 'Poor', 'Damaged'] },
            { name: 'accountable_person', label: 'Accountable Person', type: 'accountable_name', required: false },
            { name: 'cost', label: 'Cost (₱)', type: 'number', step: '0.01', required: false }
        ]
    },
    office: {
        name: 'Office Equipment',
        icon: 'fas fa-briefcase',
        color: 'info',
        table: 'office_equipment',
        fields: [
            { name: 'equipment_name', label: 'Equipment Name', type: 'text', required: true },
            { name: 'description', label: 'Description', type: 'textarea', required: false }, // NEW LINE
            { name: 'brand', label: 'Brand', type: 'text', required: false },
            { name: 'model', label: 'Model', type: 'text', required: false },
            { name: 'unit', label: 'Unit', type: 'unit_select', required: true },
            { name: 'serial_number', label: 'Serial Number', type: 'text', required: true },
            { name: 'property_no', label: 'Property Number', type: 'text', required: false },
            { name: 'cost', label: 'Cost (₱)', type: 'number', step: '0.01', required: false },
            { name: 'purchase_date', label: 'Date of Purchase', type: 'purchase_date_toggle', required: false },
            { name: 'campus', label: 'Campus', type: 'campus_select', required: true },
            { name: 'location_id', label: 'Location', type: 'location_select', required: false },
            { name: 'condition_status', label: 'Condition', type: 'select', required: true, options: ['Excellent', 'Good', 'Fair', 'Poor', 'Damaged'] },
            { name: 'accountable_person', label: 'Accountable Person', type: 'accountable_name', required: false },
            { name: 'cost', label: 'Cost (₱)', type: 'number', step: '0.01', required: false }
        ]
    },
    lab: {
        name: 'Lab Equipment',
        icon: 'fas fa-flask',
        color: 'danger',
        table: 'lab_equipment',
        fields: [
            { name: 'equipment_name', label: 'Equipment Name', type: 'text', required: true },
            { name: 'description', label: 'Description', type: 'textarea', required: false }, // NEW LINE
            { name: 'brand', label: 'Brand', type: 'text', required: false },
            { name: 'model', label: 'Model', type: 'text', required: false },
            { name: 'unit', label: 'Unit', type: 'unit_select', required: true },
            { name: 'serial_number', label: 'Serial Number', type: 'text', required: true },
            { name: 'property_no', label: 'Property Number', type: 'text', required: false },
            { name: 'cost', label: 'Cost (₱)', type: 'number', step: '0.01', required: false },
            { name: 'purchase_date', label: 'Date of Purchase', type: 'purchase_date_toggle', required: false },
            { name: 'campus', label: 'Campus', type: 'campus_select', required: true },
            { name: 'location_id', label: 'Location', type: 'location_select', required: false },
            { name: 'condition_status', label: 'Condition', type: 'select', required: true, options: ['Excellent', 'Good', 'Fair', 'Poor', 'Damaged'] },
            { name: 'calibration_date', label: 'Calibration Date', type: 'date', required: false },
            { name: 'accountable_person', label: 'Accountable Person', type: 'accountable_name', required: false },
            { name: 'cost', label: 'Cost (₱)', type: 'number', step: '0.01', required: false }
        ]
    },
    general: {
        name: 'General Equipment',
        icon: 'fas fa-tools',
        color: 'secondary',
        table: 'general_equipment',
        fields: [
            { 
                name: 'article', 
                label: 'Article', 
                type: 'select', 
                required: true, 
                options: ['Aircon', 'Copier', 'Projector', 'Scanner', 'Whiteboard', 'Board', 'Smartboard', 'Camera'] 
            },
            { name: 'description', label: 'Description', type: 'textarea', required: false },
            { name: 'brand', label: 'Brand', type: 'text', required: false },
            { name: 'model', label: 'Model', type: 'text', required: false },
            // Projector fields (will be shown/hidden dynamically)
            { name: 'projector_brand', label: 'Projector Brand', type: 'text', required: false, depends_on: 'article', depends_value: 'Smartboard' },
            { name: 'projector_model', label: 'Projector Model', type: 'text', required: false, depends_on: 'article', depends_value: 'Smartboard' },
            { name: 'projector_serial_number', label: 'Projector Serial Number', type: 'text', required: false, depends_on: 'article', depends_value: 'Smartboard' },
            { name: 'unit', label: 'Unit', type: 'unit_select', required: true },
            // REMOVED the duplicate serial_number field - it's now handled by the article dropdown
            { name: 'property_no', label: 'Property No.', type: 'text', required: true },
            { name: 'cost', label: 'Cost (₱)', type: 'number', step: '0.01', required: false },
            { name: 'purchase_date', label: 'Date of Purchase', type: 'purchase_date_toggle', required: false },
            { name: 'campus', label: 'Campus', type: 'campus_select', required: true },
            { name: 'location_id', label: 'Location', type: 'location_select', required: false },
            { name: 'condition_status', label: 'Condition', type: 'select', required: true, options: ['Excellent', 'Good', 'Fair', 'Poor', 'Damaged'] },
            { name: 'accountable_person', label: 'Accountable Person', type: 'accountable_name', required: false }
        ]
    }
};

// DataTable replaced by lazy-load system (search + per-page handled by #equipmentSearch / #perPageSelect)
$(document).ready(function() {





















    // Auto-hide alerts after 5 seconds - EXCLUDE PRS PREVIEW MODAL ALERTS
    setTimeout(function() {
        // Hide all alerts that are NOT inside any modal
        $('.alert').not('.modal .alert').fadeOut(500);
    }, 5000);

    updateSelection();
    
    // Initialize Add Equipment Modal functionality
    initAddEquipmentModal();
});

// Toggle Report Mode
function toggleReportMode() {
    const isCampus = document.getElementById('modeCampus').checked;
    const campusSection = document.getElementById('campusSection');
    const accountableSection = document.getElementById('accountableSection');
    const reportSummary = document.getElementById('reportSummary');
    
    if (isCampus) {
        campusSection.style.display = 'block';
        accountableSection.style.display = 'none';
        reportSummary.textContent = 'Showing equipment filtered by campus selection';
    } else {
        campusSection.style.display = 'none';
        accountableSection.style.display = 'block';
        reportSummary.textContent = 'Showing equipment filtered by accountable person';
        
        // Load accountable persons if not already loaded
        loadAccountablePersons();
    }
}

// Load Accountable Persons
function loadAccountablePersons() {
    const select = document.getElementById('remarksSelect');
    if (!select) return;
    
    // Show loading state
    select.innerHTML = '<option value="">Loading...</option>';
    
    fetch('get_unique_remarks.php?' + new Date().getTime())
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            console.log('Remarks data received:', data); // Debug log
            
            // Clear loading
            select.innerHTML = '';
            
            // Add "All" option
            const allOption = document.createElement('option');
            allOption.value = 'ALL';
            allOption.textContent = '-- All Accountable Persons --';
            select.appendChild(allOption);
            
            if (data && Array.isArray(data) && data.length > 0) {
                // Add each person to select
                data.forEach(name => {
                    if (name && name.trim() !== '') {
                        const option = document.createElement('option');
                        option.value = name;
                        option.textContent = name;
                        select.appendChild(option);
                    }
                });
                
                // If we added options, show success message in console
                console.log(`Loaded ${data.length} accountable persons`);
            } else {
                // No data found
                console.log('No accountable persons found in database');
                const noDataOption = document.createElement('option');
                noDataOption.value = '';
                noDataOption.disabled = true;
                noDataOption.textContent = 'No accountable persons found';
                select.appendChild(noDataOption);
                
                // Add some sample data for testing (remove in production)
                // This is just to test if the dropdown works
                const testOption = document.createElement('option');
                testOption.value = 'TEST USER';
                testOption.textContent = 'TEST USER (Sample)';
                select.appendChild(testOption);
            }
        })
        .catch(error => {
            console.error('Error loading accountable persons:', error);
            select.innerHTML = '<option value="">Error loading names: ' + error.message + '</option>';
        });
}

// Manual Condemn Equipment
function manualCondemnEquipment(id, type, name) {
    // Set the values in the modal
    document.getElementById('manual_condemn_equipment_id').value = id;
    document.getElementById('manual_condemn_equipment_type').value = type;
    document.getElementById('manual_condemn_equipment_name').textContent = name;
    document.getElementById('manual_condemn_reason').value = '';
    document.getElementById('condemn_category').value = '';
    
    // Show the modal
    new bootstrap.Modal(document.getElementById('manualCondemnModal')).show();
}

// Add submit handler for manual condemn form
document.getElementById('manualCondemnForm')?.addEventListener('submit', function(e) {
    const reason = document.getElementById('manual_condemn_reason').value.trim();
    const category = document.getElementById('condemn_category').value;
    
    if (reason === '') {
        e.preventDefault();
        Swal.fire({
            icon: 'warning',
            title: 'Reason Required',
            text: 'Please provide a reason for condemning this equipment.'
        });
        return false;
    }
    
    if (category === '') {
        e.preventDefault();
        Swal.fire({
            icon: 'warning',
            title: 'Category Required',
            text: 'Please select a category for this condemned equipment.'
        });
        return false;
    }
    
    return true;
});

// Update report summary based on selections
function updateReportSummary() {
    const isCampus = document.getElementById('modeCampus').checked;
    const reportSummary = document.getElementById('reportSummary');
    const campusSelect = document.querySelector('select[name="filter_campus"]');
    const remarksSelect = document.getElementById('remarksSelect');
    const statusSelect = document.querySelector('select[name="filter_status"]');
    
    let summary = '';
    
    if (isCampus) {
        const campus = campusSelect.options[campusSelect.selectedIndex].text;
        summary = `Campus: ${campus}`;
    } else {
        const person = remarksSelect.options[remarksSelect.selectedIndex]?.text || 'All Persons';
        summary = `Person: ${person}`;
    }
    
    const status = statusSelect.options[statusSelect.selectedIndex].text;
    summary += ` | Status: ${status}`;
    
    reportSummary.textContent = summary;
}

// Add event listeners for filter changes
$(document).ready(function() {
    // Add change event listeners to update summary
    $('select[name="filter_campus"], select[name="filter_remarks"], select[name="filter_status"]').on('change', function() {
        updateReportSummary();
    });
    
    // Initialize report summary
    setTimeout(updateReportSummary, 100);
});

// Initialize Add Equipment Modal
function initAddEquipmentModal() {
    console.log('Initializing Add Equipment Modal...');
    
    // Remove any existing event listeners and add new ones
    $('.equipment-type-card').off('click').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const type = $(this).data('equipment-type');
        console.log('Equipment type selected:', type);
        
        if (type) {
            selectedEquipmentType = type;
            selectEquipmentType(type);
        }
    });
    
    // Add method card click handler - now only manual is available
    $('.add-method-card').off('click').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const method = $(this).data('add-method');
        console.log('Add method selected:', method);
        
        if (method && method === 'manual') {
            selectAddMethod(method);
        }
    });
    
    // Reset modal when opened
    $('#addEquipmentModal').off('show.bs.modal').on('show.bs.modal', function() {
        console.log('Add Equipment Modal opened');
        showStep(1);
        selectedEquipmentType = '';
    });
}

// Filter by campus
function filterByCampus(campus) {
    const url = new URL(window.location.href);
    if (campus) {
        url.searchParams.set('campus', campus);
    } else {
        url.searchParams.delete('campus');
    }
    window.location.href = url.toString();
}

// Equipment type selection
function selectEquipmentType(type) {
    console.log('Selecting equipment type:', type);
    const config = equipmentTypes[type];
    
    if (!config) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Equipment type configuration not found: ' + type
        });
        return;
    }
    
    const iconElement = document.getElementById('selectedTypeIcon');
    const nameElement = document.getElementById('selectedTypeName');
    
    if (iconElement && nameElement) {
        // Clear previous content
        iconElement.innerHTML = '';
        iconElement.className = `equipment-type-icon bg-${config.color} mx-auto mb-3`;
        
        // Create icon element
        const icon = document.createElement('i');
        icon.className = `${config.icon} fa-3x text-white`;
        iconElement.appendChild(icon);
        
        nameElement.textContent = config.name;
        
        // Show step 2 (addition method)
        showStep(2);
    } else {
        console.error('Selected type display elements not found');
    }
}

function showStep(stepNumber) {
    console.log('Showing step:', stepNumber);
    
    // Hide all steps
    document.querySelectorAll('.modal-step').forEach(step => {
        step.style.display = 'none';
    });
    
    // Show target step
    const targetStep = document.getElementById('step' + stepNumber);
    if (targetStep) {
        targetStep.style.display = 'block';
    }
    
    // Update modal title
    const modalTitle = document.getElementById('modalTitle');
    if (modalTitle) {
        switch(stepNumber) {
            case 1:
                modalTitle.innerHTML = '<i class="fas fa-plus-circle me-2"></i>Add New Equipment';
                break;
            case 2:
                modalTitle.innerHTML = '<i class="fas fa-cog me-2"></i>Choose Addition Method';
                break;
            case 3:
                modalTitle.innerHTML = '<i class="fas fa-edit me-2"></i>Manual Entry';
                break;
        }
    }
}

// Add method selection - now only manual is available
function selectAddMethod(method) {
    console.log('Selecting add method:', method);
    const config = equipmentTypes[selectedEquipmentType];
    
    if (!config) {
        console.error('No equipment type selected');
        Swal.fire({
            icon: 'warning',
            title: 'No Selection',
            text: 'Please select an equipment type first.'
        });
        showStep(1);
        return;
    }

    if (method === 'manual') {
        document.getElementById('manual_equipment_type').value = selectedEquipmentType;
        generateManualForm(selectedEquipmentType);
        showStep(3);
    }
}

// Back button functionality
function goBackToStep(step) {
    showStep(step);
}

// Add click handler for back buttons
$(document).on('click', '.btn-outline-secondary[onclick*="showStep"]', function(e) {
    e.preventDefault();
    const onclickAttr = $(this).attr('onclick');
    const match = onclickAttr.match(/showStep\((\d+)\)/);
    if (match && match[1]) {
        showStep(parseInt(match[1]));
    }
});

// Re-attach click handlers when modal content changes
$(document).on('click', '.equipment-type-card', function(e) {
    e.preventDefault();
    e.stopPropagation();
    
    const type = $(this).data('equipment-type');
    console.log('Equipment type selected (delegated):', type);
    
    if (type) {
        selectedEquipmentType = type;
        selectEquipmentType(type);
    }
});

$(document).on('click', '.add-method-card', function(e) {
    e.preventDefault();
    e.stopPropagation();
    
    const method = $(this).data('add-method');
    console.log('Add method selected (delegated):', method);
    
    if (method && method === 'manual') {
        selectAddMethod(method);
    }
});


function generateManualForm(type) {
    console.log('Generating manual form for:', type);
    const config = equipmentTypes[type];
    const container = document.getElementById('manualFormFields');
    
    if (!container) {
        console.error('Container not found!');
        return;
    }
    
    let html = '';
    const locations = <?php echo json_encode($locations); ?>;
    
    // Define article options based on equipment type
    const articleOptions = {
        computer: [
            { name: 'Laptop', has_dual_serial: false },
            { name: 'All-in-One', has_dual_serial: false },
            { name: 'Computer Package', has_dual_serial: true }
        ],
        general: [
            { name: 'Aircon', has_dual_serial: false },
            { name: 'Copier', has_dual_serial: false },
            { name: 'Projector', has_dual_serial: false },
            { name: 'Scanner', has_dual_serial: false },
            { name: 'Whiteboard', has_dual_serial: false },
            { name: 'Board', has_dual_serial: false },
            { name: 'Camera', has_dual_serial: false },
            { name: 'TV', has_dual_serial: false },
            { name: 'Sound System', has_dual_serial: false }
        ],
        kitchen: [
            { name: 'Refrigerator', has_dual_serial: false },
            { name: 'Stove', has_dual_serial: false },
            { name: 'Microwave', has_dual_serial: false },
            { name: 'Oven', has_dual_serial: false },
            { name: 'Blender', has_dual_serial: false }
        ],
        office: [
            { name: 'Chair', has_dual_serial: false },
            { name: 'Table', has_dual_serial: false },
            { name: 'Cabinet', has_dual_serial: false },
            { name: 'Filing Cabinet', has_dual_serial: false },
            { name: 'Desk', has_dual_serial: false }
        ],
        lab: [
            { name: 'Microscope', has_dual_serial: false },
            { name: 'Centrifuge', has_dual_serial: false },
            { name: 'Incubator', has_dual_serial: false },
            { name: 'Spectrophotometer', has_dual_serial: false },
            { name: 'pH Meter', has_dual_serial: false }
        ]
    };
    
    // Get articles for this equipment type
    const articles = articleOptions[type] || articleOptions.general;
    
    config.fields.forEach((field) => {
        let dependsAttr = '';
        let styleAttr = '';
        
        if (field.depends_on) {
            dependsAttr = ` data-depends-on="${field.depends_on}" data-depends-value="${field.depends_value}"`;
            styleAttr = ' style="display: none;"';
        }
        
        const colClass = (field.type === 'textarea' || field.type === 'dynamic_serial' || field.type === 'purchase_date_toggle') ? 'col-12' : 'col-md-6';
        
        html += `<div class="${colClass} mb-3 conditional-field" id="field_container_${field.name}"${dependsAttr}${styleAttr}>
                    <label class="form-label small fw-bold">${field.label}${field.required ? ' <span class="text-danger">*</span>' : ''}</label>`;
        
        if (field.type === 'purchase_date_toggle') {
            html += `
                <div class="purchase-date-container border rounded p-3 bg-light">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="purchase_date_option" id="purchase_date_yes_${type}" value="yes" checked>
                                <label class="form-check-label" for="purchase_date_yes_${type}">
                                    <i class="fas fa-calendar-check text-success me-1"></i> Have Date of Purchase
                                </label>
                            </div>
                            <div class="mt-2" id="purchase_date_picker_${type}">
                                <input type="date" class="form-control" name="purchase_date" id="purchase_date_input_${type}" max="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="purchase_date_option" id="purchase_date_no_${type}" value="no">
                                <label class="form-check-label" for="purchase_date_no_${type}">
                                    <i class="fas fa-times-circle text-danger me-1"></i> No Date
                                </label>
                            </div>
                            <div class="mt-2 text-muted" id="purchase_date_na_${type}" style="display: none;">
                                <span class="badge bg-secondary"><i class="fas fa-clock me-1"></i> Will be saved as NULL</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <script>
                (function() {
                    function initPurchaseDateToggle_${type}() {
                        const radioYes = document.getElementById('purchase_date_yes_${type}');
                        const radioNo = document.getElementById('purchase_date_no_${type}');
                        const datePicker = document.getElementById('purchase_date_picker_${type}');
                        const naDiv = document.getElementById('purchase_date_na_${type}');
                        const dateInput = document.getElementById('purchase_date_input_${type}');
                        
                        if (!radioYes || !radioNo || !datePicker || !naDiv || !dateInput) {
                            setTimeout(initPurchaseDateToggle_${type}, 200);
                            return;
                        }
                        
                        function updateDisplay() {
                            if (radioYes.checked) {
                                datePicker.style.display = 'block';
                                naDiv.style.display = 'none';
                                dateInput.disabled = false;
                                dateInput.name = 'purchase_date';
                            } else {
                                datePicker.style.display = 'none';
                                naDiv.style.display = 'block';
                                dateInput.disabled = true;
                                dateInput.removeAttribute('name');
                            }
                        }
                        
                        radioYes.addEventListener('change', updateDisplay);
                        radioNo.addEventListener('change', updateDisplay);
                        updateDisplay();
                    }
                    
                    if (document.readyState === 'loading') {
                        document.addEventListener('DOMContentLoaded', initPurchaseDateToggle_${type});
                    } else {
                        initPurchaseDateToggle_${type}();
                    }
                })();
                <\/script>
            `;
        } else if (field.type === 'accountable_name') {
            html += `
                <div class="row g-2">
                    <div class="col-md-5">
                        <input type="text" class="form-control" name="accountable_lastname" placeholder="Last Name">
                    </div>
                    <div class="col-md-5">
                        <input type="text" class="form-control" name="accountable_firstname" placeholder="First Name">
                    </div>
                    <div class="col-md-2">
                        <input type="text" class="form-control" name="accountable_middle" placeholder="M.I." maxlength="2">
                    </div>
                </div>
                <small class="text-muted"><i class="fas fa-info-circle"></i> Will be saved as "Last Name, First Name M.I."</small>
            `;
        } else if (field.type === 'dynamic_serial') {
            // SKIP THIS - we handle serial in the article dropdown
        } else if (field.type === 'campus_select') {
            html += `<select class="form-select" name="campus" required>
                        <option value="">-- Select Campus --</option>
                        <option value="South Campus">South Campus</option>
                        <option value="Congressional Campus">Congressional Campus</option>
                        <option value="Bagong Silang Campus">Bagong Silang Campus</option>
                        <option value="Camarin Campus">Camarin Campus</option>
                    </select>`;
        } else if (field.type === 'location_select') {
            html += `<select class="form-select" name="location_id">
                        <option value="">-- Unassigned / Storage --</option>`;
            locations.forEach(loc => { html += `<option value="${loc.id}">${loc.location_name}</option>`; });
            html += `</select>`;
        } else if (field.type === 'select') {
            if (field.name === 'article') {
                // Build article dropdown options
                let articleHtml = `<select class="form-select" name="article" id="dynamic_article_select_${type}" required>
                                    <option value="">-- Select Article --</option>`;
                articles.forEach(article => {
                    articleHtml += `<option value="${article.name}" data-has-dual-serial="${article.has_dual_serial}">${article.name}</option>`;
                });
                articleHtml += `</select>`;
                
                if (type === 'computer') {
                    articleHtml += `<div id="dualSerialContainer_${type}" style="display: none; margin-top: 15px;">
                                        <div class="row g-2">
                                            <div class="col-md-6">
                                                <label class="small text-muted">SERIAL NUMBER (MONITOR) <span class="text-danger">*</span></label>
                                                <input type="text" name="serial_monitor" class="form-control" id="serial_monitor_input_${type}">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="small text-muted">SERIAL NUMBER (SYSTEM UNIT) <span class="text-danger">*</span></label>
                                                <input type="text" name="serial_system" class="form-control" id="serial_system_input_${type}">
                                            </div>
                                        </div>
                                    </div>`;
                } else {
                    articleHtml += `<div id="dualSerialContainer_${type}" style="display: none;"></div>`;
                }
                
                articleHtml += `<div id="singleSerialContainer_${type}" style="margin-top: 15px;">
                                    <label class="small text-muted">SERIAL NUMBER</label>
                                    <input type="text" name="serial_number" class="form-control" id="single_serial_input_${type}" placeholder="Enter Serial Number">
                                </div>`;
                
                html += articleHtml;
            } else {
                html += `<select class="form-select" name="${field.name}" required>`;
                if (field.options && field.options.length) {
                    field.options.forEach(opt => { 
                        html += `<option value="${opt}">${opt}</option>`; 
                    });
                }
                html += `</select>`;
            }
        } else if (field.type === 'textarea') {
            html += `<textarea class="form-control" name="${field.name}" rows="3"></textarea>`;
        } else if (field.type === 'unit_select') {
            html += `<select class="form-select" name="unit" required>
                        <option value="">-- Select Unit --</option>
                        <option value="unit">Unit</option>
                        <option value="box">Box</option>
                        <option value="pcs">Pieces (pcs)</option>
                        <option value="lot">Lot</option>
                    </select>`;
        } else {
            html += `<input type="${field.type}" step="${field.step || ''}" class="form-control" name="${field.name}" ${field.required ? 'required' : ''} placeholder="Enter ${field.label}">`;
        }
        html += `</div>`;
    });
    
    html += `<input type="hidden" name="item_number" value="AUTO">`;
    html += `<div class="col-12 mt-2"><div class="p-3 border rounded bg-light"><label class="form-label fw-bold"><i class="fas fa-camera me-2"></i>Equipment Photo</label><input type="file" class="form-control" name="equipment_photo" accept="image/*"></div></div>`;
    
    container.innerHTML = html;
    
    // ADD EVENT LISTENER FOR ARTICLE DROPDOWN - FIXED VERSION
    setTimeout(function() {
        const articleSelect = document.getElementById(`dynamic_article_select_${type}`);
        if (articleSelect) {
            console.log('Article select found for type:', type);
            
            // Remove any existing listeners to prevent duplicates (using off or replace with new one)
            const newArticleSelect = articleSelect.cloneNode(true);
            articleSelect.parentNode.replaceChild(newArticleSelect, articleSelect);
            
            // Get the new reference
            const finalArticleSelect = document.getElementById(`dynamic_article_select_${type}`);
            
            if (type === 'computer') {
                finalArticleSelect.addEventListener('change', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const dualContainer = document.getElementById(`dualSerialContainer_${type}`);
                    const singleContainer = document.getElementById(`singleSerialContainer_${type}`);
                    const selectedOption = this.options[this.selectedIndex];
                    const hasDualSerial = selectedOption.getAttribute('data-has-dual-serial') === 'true';
                    
                    console.log('Article changed:', this.value, 'Has dual serial:', hasDualSerial);
                    
                    if (hasDualSerial) {
                        if (dualContainer) {
                            dualContainer.style.display = 'block';
                            console.log('Showing dual serial container');
                        }
                        if (singleContainer) {
                            singleContainer.style.display = 'none';
                        }
                        const singleInput = document.getElementById(`single_serial_input_${type}`);
                        const monitorInput = document.getElementById(`serial_monitor_input_${type}`);
                        const systemInput = document.getElementById(`serial_system_input_${type}`);
                        if (singleInput) singleInput.removeAttribute('required');
                        if (monitorInput) monitorInput.setAttribute('required', 'required');
                        if (systemInput) systemInput.setAttribute('required', 'required');
                    } else {
                        if (dualContainer) {
                            dualContainer.style.display = 'none';
                        }
                        if (singleContainer) {
                            singleContainer.style.display = 'block';
                        }
                        const monitorInput = document.getElementById(`serial_monitor_input_${type}`);
                        const systemInput = document.getElementById(`serial_system_input_${type}`);
                        const singleInput = document.getElementById(`single_serial_input_${type}`);
                        if (monitorInput) monitorInput.removeAttribute('required');
                        if (systemInput) systemInput.removeAttribute('required');
                        if (singleInput) singleInput.setAttribute('required', 'required');
                    }
                });
            } else {
                // For non-computer types, ensure dual container is hidden and single is shown
                const dualContainer = document.getElementById(`dualSerialContainer_${type}`);
                const singleContainer = document.getElementById(`singleSerialContainer_${type}`);
                if (dualContainer) dualContainer.style.display = 'none';
                if (singleContainer) singleContainer.style.display = 'block';
            }
            
            // Trigger initial change to set correct state for computer type
            if (type === 'computer') {
                const initialEvent = new Event('change');
                finalArticleSelect.dispatchEvent(initialEvent);
            }
        } else {
            console.warn('Article select not found for type:', type);
        }
        
        // Initialize purchase date toggle if exists
        const radioYes = document.getElementById(`purchase_date_yes_${type}`);
        const radioNo = document.getElementById(`purchase_date_no_${type}`);
        const datePicker = document.getElementById(`purchase_date_picker_${type}`);
        const naDiv = document.getElementById(`purchase_date_na_${type}`);
        const dateInput = document.getElementById(`purchase_date_input_${type}`);
        
        if (radioYes && radioNo && datePicker && naDiv && dateInput) {
            function updateDisplay() {
                if (radioYes.checked) {
                    datePicker.style.display = 'block';
                    naDiv.style.display = 'none';
                    dateInput.disabled = false;
                    dateInput.name = 'purchase_date';
                } else {
                    datePicker.style.display = 'none';
                    naDiv.style.display = 'block';
                    dateInput.disabled = true;
                    dateInput.removeAttribute('name');
                }
            }
            radioYes.addEventListener('change', updateDisplay);
            radioNo.addEventListener('change', updateDisplay);
            updateDisplay();
        }
    }, 100);
}

// Filter functions
function filterByType(type) {
    const url = new URL(window.location.href);
    if (type) {
        url.searchParams.set('type', type);
    } else {
        url.searchParams.delete('type');
    }
    window.location.href = url.toString();
}

function filterByLocation(locationId) {
    const url = new URL(window.location.href);
    if (locationId) {
        url.searchParams.set('location', locationId);
    } else {
        url.searchParams.delete('location');
    }
    window.location.href = url.toString();
}

function filterByStatus(status) {
    const url = new URL(window.location.href);
    if (status) {
        url.searchParams.set('status', status);
    } else {
        url.searchParams.delete('status');
    }
    window.location.href = url.toString();
}

// Selection functions
function updateSelection() {
    selectedEquipment = [];
    
    document.querySelectorAll('.equipment-checkbox:checked').forEach(checkbox => {
        selectedEquipment.push(checkbox.value);
        checkbox.closest('tr').classList.add('selected');
    });
    
    document.querySelectorAll('.equipment-checkbox:not(:checked)').forEach(checkbox => {
        checkbox.closest('tr').classList.remove('selected');
    });
    
    const count = selectedEquipment.length;
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    const totalCheckboxes = document.querySelectorAll('.equipment-checkbox').length;
    
    // Update selection display
    document.getElementById('selectedCount').textContent = count + ' selected';
    
    // Update bulk action button
    const executeBtn = document.getElementById('executeBulkBtn');
    const prsBtn = document.getElementById('downloadPRSBtn');
    
    if (count > 0) {
        executeBtn.disabled = false;
        prsBtn.disabled = false;
        prsBtn.innerHTML = '<i class="fas fa-file-excel me-1"></i>PRS Form (' + count + ')';
    } else {
        executeBtn.disabled = true;
        prsBtn.disabled = true;
        prsBtn.innerHTML = '<i class="fas fa-file-excel me-1"></i>PRS Form';
    }
    
    // Update select all checkbox
    if (count === 0) {
        selectAllCheckbox.checked = false;
        selectAllCheckbox.indeterminate = false;
    } else if (count === totalCheckboxes) {
        selectAllCheckbox.checked = true;
        selectAllCheckbox.indeterminate = false;
    } else {
        selectAllCheckbox.indeterminate = true;
    }
}

function toggleSelectAll() {
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    document.querySelectorAll('.equipment-checkbox').forEach(checkbox => {
        checkbox.checked = selectAllCheckbox.checked;
    });
    updateSelection();
}

function selectAll() {
    document.getElementById('selectAllCheckbox').checked = true;
    toggleSelectAll();
}

function selectUnassigned() {
    document.querySelectorAll('.equipment-checkbox').forEach(checkbox => {
        const row = checkbox.closest('tr');
        const assignmentBadge = row.querySelector('.assignment-badge');
        checkbox.checked = assignmentBadge && assignmentBadge.textContent.includes('Unassigned');
    });
    updateSelection();
}

function clearSelection() {
    document.getElementById('selectAllCheckbox').checked = false;
    toggleSelectAll();
}

// PAR Report Modal Functions
function toggleParSource() {
    const sourceAll = document.getElementById('parSourceAll')?.checked;
    const sourceAccountable = document.getElementById('parSourceAccountable')?.checked;
    const sourceSelected = document.getElementById('parSourceSelected')?.checked;
    
    const accountableSection = document.getElementById('parAccountableSection');
    const selectedSection = document.getElementById('parSelectedSection');
    const previewByName = document.getElementById('previewByName');
    const byNameInput = document.getElementById('parByName');
    const selectedCount = document.getElementById('selectedCountForPar');
    const selectedItemsInput = document.getElementById('parSelectedItems');
    const submitBtn = document.getElementById('parSubmitBtn');
    const parSummary = document.getElementById('parSummary');
    
    if (!accountableSection || !selectedSection || !previewByName || !byNameInput || !selectedCount || !selectedItemsInput || !submitBtn || !parSummary) return;
    
    // Get current selected items count
    const currentSelected = document.querySelectorAll('.equipment-checkbox:checked').length;
    selectedCount.textContent = currentSelected + ' items selected';
    
    if (sourceAll) {
        accountableSection.style.display = 'none';
        selectedSection.style.display = 'none';
        previewByName.textContent = '[All Equipment]';
        byNameInput.value = '';
        selectedItemsInput.value = '';
        parSummary.textContent = 'PAR will include all non-condemned equipment from all categories.';
        submitBtn.disabled = false;
    } else if (sourceAccountable) {
        accountableSection.style.display = 'block';
        selectedSection.style.display = 'none';
        
        // Get selected accountable person
        const select = document.getElementById('parAccountableSelect');
        const selectedPerson = select.options[select.selectedIndex]?.text || '[Select Person]';
        previewByName.textContent = selectedPerson;
        byNameInput.value = select.value;
        selectedItemsInput.value = '';
        
        if (select.value) {
            parSummary.textContent = `PAR will include all non-condemned equipment assigned to ${selectedPerson}.`;
            submitBtn.disabled = false;
        } else {
            parSummary.textContent = 'Please select an accountable person.';
            submitBtn.disabled = true;
        }
    } else if (sourceSelected) {
        accountableSection.style.display = 'none';
        selectedSection.style.display = 'block';
        previewByName.textContent = '[Selected Items]';
        byNameInput.value = '';
        
        if (currentSelected === 0) {
            parSummary.textContent = '⚠️ No items selected. Please select items from the table first.';
            submitBtn.disabled = true;
        } else {
            parSummary.textContent = `PAR will include ${currentSelected} selected item(s).`;
            submitBtn.disabled = false;
            
            // Collect selected items
            const selectedItems = [];
            document.querySelectorAll('.equipment-checkbox:checked').forEach(cb => {
                selectedItems.push(cb.value);
            });
            selectedItemsInput.value = selectedItems.join(',');
        }
    }
}

// PAR Report Modal Functions
$(document).ready(function() {
    // Accountable person select change handler
    $('#parAccountableSelect').on('change', function() {
        const selectedPerson = this.options[this.selectedIndex]?.text || '';
        const selectedValue = this.value;
        
        // Update preview
        document.getElementById('previewByName').textContent = selectedPerson || '[Select Person]';
        document.getElementById('parByName').value = selectedValue;
        
        // Update summary and button state using the info card
        const parSummary = document.getElementById('parSummary');
        const submitBtn = document.getElementById('parSubmitBtn');
        
        if (selectedValue) {
            parSummary.innerHTML = `<i class="fas fa-check-circle text-success me-1"></i>PAR will include all non-condemned equipment assigned to <strong>${selectedPerson}</strong>.`;
            submitBtn.disabled = false;
        } else {
            parSummary.innerHTML = '<i class="fas fa-exclamation-triangle text-warning me-1"></i>Please select an accountable person.';
            submitBtn.disabled = true;
        }
    });
});

// Reset modal when opened
$('#parReportModal').on('show.bs.modal', function() {
    // Reset dropdown
    $('#parAccountableSelect').val('');
    document.getElementById('previewByName').textContent = '[Select Person]';
    document.getElementById('parByName').value = '';
    document.getElementById('parSummary').innerHTML = '<i class="fas fa-info-circle text-info me-1"></i>Please select an accountable person';
    document.getElementById('parSubmitBtn').disabled = true;
    
    // Reload accountable persons to ensure fresh data
    loadParAccountablePersons();
});

// Load Accountable Persons for PAR
function loadParAccountablePersons() {
    const select = document.getElementById('parAccountableSelect');
    if (!select) return;
    
    // Store current selection
    const currentValue = select.value;
    
    // Show loading state
    select.innerHTML = '<option value="">Loading...</option>';
    
    fetch('get_unique_remarks.php')
        .then(response => response.json())
        .then(data => {
            select.innerHTML = '<option value="">-- Select Accountable Person --</option>';
            
            if (data && data.length > 0) {
                // Filter and add options
                const validNames = data.filter(name => {
                    return name && 
                           name !== 'N/A' && 
                           name !== 'None Assigned' && 
                           name !== 'None' && 
                           name !== 'Unassigned' && 
                           name.trim() !== '';
                });
                
                // Sort alphabetically
                validNames.sort();
                
                validNames.forEach(name => {
                    const option = document.createElement('option');
                    option.value = name;
                    option.textContent = name;
                    select.appendChild(option);
                });
                
                if (validNames.length === 0) {
                    select.innerHTML += '<option value="" disabled>No accountable persons found</option>';
                }
            } else {
                select.innerHTML += '<option value="" disabled>No accountable persons found</option>';
            }
            
            // Restore previous selection if it exists
            if (currentValue) {
                select.value = currentValue;
                // Trigger change event to update preview
                $(select).trigger('change');
            }
        })
        .catch(error => {
            console.error('Error loading accountable persons:', error);
            select.innerHTML = '<option value="">-- Error loading names --</option>';
        });
}

// Add a button to open the PAR modal in the header actions
// Find this in your code and add this button if it doesn't exist:
// <button type="button" class="btn" data-bs-toggle="modal" data-bs-target="#parReportModal">
//     <i class="fas fa-file-signature me-2"></i>PAR
// </button>

function executeBulkAssignment() {
    if (selectedEquipment.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'No Selection',
            text: 'Please select at least one equipment item.'
        });
        return;
    }
    
    const locationSelect = document.getElementById('bulkLocationSelect');
    const locationId = locationSelect.value;
    
    if (!locationId) {
        Swal.fire({
            icon: 'warning',
            title: 'No Location',
            text: 'Please select a location to assign.'
        });
        return;
    }
    
    const locationName = locationSelect.options[locationSelect.selectedIndex].text;
    
    Swal.fire({
        title: 'Confirm Bulk Assignment',
        html: `Assign <strong>${selectedEquipment.length}</strong> equipment items to <strong>${locationName}</strong>?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#2E7D32',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, assign them!'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('hiddenEquipmentItems').value = selectedEquipment.join(',');
            document.getElementById('hiddenLocationId').value = locationId;
            document.getElementById('bulkAssignForm').submit();
        }
    });
}

function downloadPRSForm() {
    if (selectedEquipment.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'No Selection',
            text: 'Please select at least one equipment item.'
        });
        return;
    }
    
    showPRSPreviewAllEquipment(selectedEquipment);
}

function showPRSPreviewAllEquipment(selectedItems) {
    // Check if modal element exists first
    const modalElement = document.getElementById('prsPreviewModalAllEquipment');
    if (!modalElement) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'PRS Preview Modal not found in the page. Please refresh and try again.'
        });
        return;
    }

    // Show loading indicator
    Swal.fire({
        title: 'Loading Preview',
        text: 'Please wait...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    fetch('get_prs_preview_data_all_equipment.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'selected_items=' + encodeURIComponent(selectedItems.join(','))
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok: ' + response.status);
        }
        return response.json();
    })
    .then(data => {
        Swal.close();
        
        if (data.success) {
            displayPRSPreviewAllEquipment(data.data, selectedItems);
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message || 'Failed to load preview data.'
            });
        }
    })
    .catch(error => {
        Swal.close();
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Failed to load preview data. Please try again. Error: ' + error.message
        });
    });
}

function displayPRSPreviewAllEquipment(items, selectedItems) {
    const previewBody = document.getElementById('prsPreviewBodyAllEquipment');
    const modalElement = document.getElementById('prsPreviewModalAllEquipment');
    
    if (!previewBody) {
        console.error('Preview body element not found');
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Preview element not found. Please refresh the page.'
        });
        return;
    }

    if (!modalElement) {
        console.error('Modal element not found');
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Modal element not found. Please refresh the page.'
        });
        return;
    }

    // Reset alert visibility manually every time the modal opens
    const previewAlert = modalElement.querySelector('.prs-preview-alert');
    if (previewAlert) {
        previewAlert.style.display = 'block';
        previewAlert.style.opacity = '1';
        previewAlert.style.visibility = 'visible';
    }
    
    previewBody.innerHTML = '';
    
    let totalValue = 0;
    const maxItems = 46;
    const itemsToShow = items.slice(0, maxItems);
    
    // Get all the elements we need to update
    const previewItemCount = document.getElementById('previewItemCountAllEquipment');
    const previewTotalItems = document.getElementById('previewTotalItemsAllEquipment');
    const totalValueElement = document.getElementById('totalValueAllEquipment');
    const confirmBtn = document.getElementById('confirmDownloadBtnAllEquipment');
    
    // Check if elements exist before setting properties
    if (previewItemCount) {
        previewItemCount.textContent = itemsToShow.length;
    }
    
    if (previewTotalItems) {
        previewTotalItems.textContent = itemsToShow.length;
    }
    
    itemsToShow.forEach(function(item, index) {
        const unitValue = parseFloat(item.unit_value) || 0;
        totalValue += unitValue;
        
        const row = document.createElement('tr');
        row.innerHTML = `
            <td class="text-center">${index + 1}</td>
            <td class="text-center">1</td>
            <td class="text-center">pc</td>
            <td>${escapeHtml(item.description)}</td>
            <td class="text-center">${escapeHtml(item.year_acquired)}</td>
            <td>${escapeHtml(item.serial_number)}</td>
            <td>${escapeHtml(item.end_user)}</td>
            <td class="text-end">₱ ${unitValue.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
        `;
        previewBody.appendChild(row);
    });
    
    if (items.length > maxItems) {
        const warningRow = document.createElement('tr');
        warningRow.innerHTML = `
            <td colspan="8" class="text-center text-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Only showing first ${maxItems} items. The PRS form will include all items.
            </td>
        `;
        previewBody.appendChild(warningRow);
    }
    
    if (totalValueElement) {
        totalValueElement.innerHTML = '₱ ' + totalValue.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }
    
    if (confirmBtn) {
        confirmBtn.setAttribute('data-selected-items', selectedItems.join(','));
    }
    
    // Show the modal
    try {
        const modal = new bootstrap.Modal(modalElement);
        modal.show();
    } catch (error) {
        console.error('Error showing modal:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Failed to show preview modal. Please refresh the page.'
        });
    }
}

// Make sure this event listener exists
document.addEventListener('DOMContentLoaded', function() {
    // Existing code...
    
    // Confirm download button for PRS form
    const confirmBtn = document.getElementById('confirmDownloadBtnAllEquipment');
    if (confirmBtn) {
        confirmBtn.addEventListener('click', function() {
            const selectedItems = this.getAttribute('data-selected-items');
            
            if (!selectedItems) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'No items selected.'
                });
                return;
            }
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'generate_prs_form_all_equipment.php';
            form.target = '_blank';
            
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'selected_items';
            input.value = selectedItems;
            
            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
            
            // Close the modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('prsPreviewModalAllEquipment'));
            if (modal) {
                modal.hide();
            }
        });
    }
});

function escapeHtml(text) {
    if (!text) return 'N/A';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
}

// Open transfer modal
function openTransferModal() {
    if (selectedEquipment.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'No Selection',
            text: 'Please select items to transfer first.'
        });
        return;
    }

    const list = document.getElementById('selectedItemsList');
    const idsInput = document.getElementById('transferIdsInput');
    const typeInput = document.getElementById('transferTypeInput');
    
    let itemsHtml = '<table class="table table-sm"><thead><tr><th>Item</th><th>Campus</th><th>Accountable</th></tr></thead><tbody>';
    let ids = [];
    let type = "";

    document.querySelectorAll('.equipment-checkbox:checked').forEach(cb => {
        const row = cb.closest('tr');
        const name = row.querySelector('h6').innerText;
        const campus = row.getAttribute('data-current-campus');
        const remarks = row.getAttribute('data-current-remarks');
        
        const parts = cb.value.split(':');
        type = parts[0];
        ids.push(parts[1]);

        itemsHtml += `<tr>
            <td>${name}</td>
            <td><span class="badge bg-info">${campus}</span></td>
            <td>${remarks}</td>
        </tr>`;
    });

    itemsHtml += '</tbody></table>';
    list.innerHTML = itemsHtml;
    document.getElementById('transferItemCount').innerText = selectedEquipment.length;
    idsInput.value = ids.join(',');
    typeInput.value = type;

    new bootstrap.Modal(document.getElementById('transferModal')).show();
}

// Open edit modal
function openEditModal(id, type) {
    const modal = new bootstrap.Modal(document.getElementById('editEquipmentModal'));
    const body = document.getElementById('editModalBody');
    
    body.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-warning mb-3"></div><p>Loading equipment details...</p></div>';
    modal.show();
    
    fetch('get_edit_fields.php?id=' + id + '&type=' + type)
        .then(response => response.text())
        .then(html => {
            body.innerHTML = html;

            // Re-execute scripts injected via innerHTML (they don't run automatically)
            body.querySelectorAll('script').forEach(oldScript => {
                const newScript = document.createElement('script');
                newScript.textContent = oldScript.textContent;
                document.body.appendChild(newScript);
                document.body.removeChild(newScript);
            });
        })
        .catch(error => {
            console.error('Error:', error);
            body.innerHTML = '<div class="alert alert-danger m-3">Error loading edit form.</div>';
        });
}

// View equipment details
function viewEquipmentDetails(id, type) {
    const modal = new bootstrap.Modal(document.getElementById('viewDetailsModal'));
    const body = document.getElementById('detailsModalBody');
    
    body.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-info mb-3"></div><p>Loading specifications...</p></div>';
    modal.show();

    fetch('get_equipment_details.php?id=' + id + '&type=' + type)
        .then(response => response.text())
        .then(html => {
            body.innerHTML = html;
        })
        .catch(err => {
            body.innerHTML = '<div class="alert alert-danger m-3">Error loading equipment details.</div>';
            console.error(err);
        });
}

// Condemn equipment
function condemnEquipment(id, type, name) {
    document.getElementById('condemnEquipmentId').value = id;
    document.getElementById('condemnEquipmentType').value = type;
    document.getElementById('condemnEquipmentName').textContent = name;
    document.getElementById('condemn_reason').value = '';
    
    new bootstrap.Modal(document.getElementById('condemnModal')).show();
}

function confirmCondemn() {
    const reason = document.getElementById('condemn_reason').value.trim();
    if (reason === '') {
        Swal.fire({
            icon: 'warning',
            title: 'Reason Required',
            text: 'Please provide a reason for condemning this equipment.'
        });
        return;
    }
    
    document.getElementById('condemnForm').submit();
}

// File input handler
$('#fileInput').on('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;
    
    const extension = file.name.split('.').pop().toLowerCase();
    const workbookSection = document.getElementById('workbookSection');
    const sheetSelector = document.getElementById('sheetSelector');

    if (extension === 'xlsx') {
        const formData = new FormData();
        formData.append('file', file);

        fetch('get_excel_sheets.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(sheets => {
            if (sheets.length > 0) {
                sheetSelector.innerHTML = '';
                sheets.forEach(name => {
                    const option = document.createElement('option');
                    option.value = name;
                    option.textContent = name;
                    sheetSelector.appendChild(option);
                });
                workbookSection.style.display = 'block';
            }
        })
        .catch(error => {
            console.error('Error loading sheets:', error);
        });
    } else {
        workbookSection.style.display = 'none';
    }
});

// PAR Report Modal
const parReportModal = document.getElementById('parReportModal');
if (parReportModal) {
    parReportModal.addEventListener('show.bs.modal', function() {
        document.getElementById('parSourceAll').checked = true;
        toggleParSource();
    });
}

function toggleParSource() {
    const sourceAll = document.getElementById('parSourceAll')?.checked;
    const sourceSelected = document.getElementById('parSourceSelected')?.checked;
    const sourceAccountable = document.getElementById('parSourceAccountable')?.checked;
    
    const accountableSection = document.getElementById('parAccountableSection');
    const infoText = document.getElementById('parInfoText');
    const selectedCount = document.getElementById('selectedCountForPar');
    const selectedItemsInput = document.getElementById('parSelectedItems');
    const submitBtn = document.getElementById('parSubmitBtn');
    
    if (!accountableSection || !infoText || !selectedCount || !selectedItemsInput || !submitBtn) return;
    
    const currentSelected = document.querySelectorAll('.equipment-checkbox:checked').length;
    selectedCount.textContent = currentSelected;
    
    if (sourceAll) {
        accountableSection.style.display = 'none';
        infoText.textContent = 'PAR will include all non-condemned equipment from all categories.';
        selectedItemsInput.value = '';
        submitBtn.disabled = false;
    } else if (sourceSelected) {
        accountableSection.style.display = 'none';
        
        if (currentSelected === 0) {
            infoText.textContent = '⚠️ No items selected. Please select items from the table first.';
            submitBtn.disabled = true;
        } else {
            infoText.textContent = `PAR will include ${currentSelected} selected item(s).`;
            submitBtn.disabled = false;
            
            const selectedItems = [];
            document.querySelectorAll('.equipment-checkbox:checked').forEach(cb => {
                selectedItems.push(cb.value);
            });
            selectedItemsInput.value = selectedItems.join(',');
        }
    } else if (sourceAccountable) {
        accountableSection.style.display = 'block';
        infoText.textContent = 'PAR will include all non-condemned equipment assigned to the selected person.';
        selectedItemsInput.value = '';
        submitBtn.disabled = false;
        loadParAccountablePersons();
    }
}

function loadParAccountablePersons() {
    const select = document.getElementById('parAccountableSelect');
    if (!select) return;
    
    if (select.options.length > 1 && select.value !== "") return;

    select.innerHTML = '<option value="">Loading...</option>';
    
    fetch('get_unique_remarks.php')
        .then(response => response.json())
        .then(data => {
            select.innerHTML = '<option value="">-- Select Accountable Person --</option>';
            data.forEach(name => {
                if (name && name !== 'N/A' && name !== 'None Assigned' && name !== '') {
                    const option = document.createElement('option');
                    option.value = name;
                    option.textContent = name;
                    select.appendChild(option);
                }
            });
        })
        .catch(err => {
            select.innerHTML = '<option value="">Error loading names</option>';
            console.error(err);
        });
}

// Delete equipment function with DOUBLE confirmation
function confirmDeleteEquipment(id, type, name) {
    // FIRST CONFIRMATION
    Swal.fire({
        title: 'Delete Equipment?',
        html: `Are you sure you want to delete <strong>${escapeHtmlForDelete(name)}</strong>?<br><br>This action cannot be undone. All assignment history will also be deleted.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-trash me-2"></i>Yes, delete it!',
        cancelButtonText: '<i class="fas fa-times me-2"></i>Cancel',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            // SECOND CONFIRMATION - FINAL WARNING
            Swal.fire({
                title: '⚠️ FINAL WARNING ⚠️',
                html: `<strong style="color: #dc2626;">THIS ACTION IS IRREVERSIBLE!</strong><br><br>
                       You are about to permanently delete <strong>${escapeHtmlForDelete(name)}</strong>.<br><br>
                       This equipment will be:<br>
                       • Removed from all records<br>
                       • Removed from assignment history<br>
                       • Removed from the database completely<br><br>
                       <strong style="color: #dc2626;">There is NO way to recover this equipment once deleted.</strong><br><br>
                       Type <strong style="color: #dc2626;">"DELETE"</strong> in the box below to confirm:`,
                icon: 'error',
                input: 'text',
                inputPlaceholder: 'Type DELETE here',
                inputAttributes: {
                    'aria-label': 'Type DELETE to confirm'
                },
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-trash me-2"></i>Permanently Delete',
                cancelButtonText: '<i class="fas fa-times me-2"></i>Cancel',
                reverseButtons: true,
                preConfirm: (inputValue) => {
                    if (inputValue !== 'DELETE') {
                        Swal.showValidationMessage('Please type "DELETE" to confirm deletion');
                        return false;
                    }
                    return true;
                }
            }).then((finalResult) => {
                if (finalResult.isConfirmed) {
                    // Get table name based on type
                    let tableName = '';
                    switch(type) {
                        case 'computer':
                        case 'computer_lab':
                            tableName = 'computer_inventory';
                            break;
                        case 'kitchen':
                            tableName = 'kitchen_equipment';
                            break;
                        case 'office':
                            tableName = 'office_equipment';
                            break;
                        case 'lab':
                        case 'regular_lab':
                            tableName = 'lab_equipment';
                            break;
                        case 'general':
                            tableName = 'general_equipment';
                            break;
                        default:
                            tableName = type;
                    }
                    
                    // Show loading state
                    Swal.fire({
                        title: 'Deleting...',
                        text: 'Please wait while the equipment is being permanently deleted.',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    // Create form and submit via AJAX
                    const formData = new FormData();
                    formData.append('id', id);
                    formData.append('type', type);
                    formData.append('table', tableName);
                    formData.append('action', 'delete_equipment');
                    
                    fetch('delete_equipment.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Deleted!',
                                text: data.message + ' This equipment has been permanently removed.',
                                timer: 3000,
                                showConfirmButton: false,
                                background: '#f8f9fa',
                                iconColor: '#10b981'
                            }).then(() => {
                                window.location.href = 'all_equipment.php';
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error!',
                                text: data.message,
                                confirmButtonColor: '#d33'
                            });
                        }
                    })
                    .catch(error => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: 'An error occurred while deleting the equipment.',
                            confirmButtonColor: '#d33'
                        });
                        console.error('Error:', error);
                    });
                }
            });
        }
    });
}

function escapeHtmlForDelete(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
}

// ─────────────────────────────────────────────────────────────────────────────
// LAZY-LOAD / INFINITE SCROLL
// ─────────────────────────────────────────────────────────────────────────────
(function() {
    let perPage      = parseInt(document.getElementById('perPageSelect')?.value || 50);
    let currentPage  = 1;
    let isLoading    = false;
    let hasMore      = true;
    let totalLoaded  = 0;
    let grandTotal   = 0;
    let searchTimer  = null;

    // Read active filters from URL + live search input
    function getFilters() {
        const p = new URLSearchParams(window.location.search);
        return {
            search:   document.getElementById('equipmentSearch')?.value || p.get('search') || '',
            type:     p.get('type')     || '',
            status:   p.get('status')   || '',
            location: p.get('location') || '',
            campus:   p.get('campus')   || '',
        };
    }

    function buildURL(page) {
        const f   = getFilters();
        const url = new URL('get_equipment_ajax.php', window.location.href);
        url.searchParams.set('page', page);
        url.searchParams.set('per_page', perPage);
        if (f.search)   url.searchParams.set('search',   f.search);
        if (f.type)     url.searchParams.set('type',     f.type);
        if (f.status)   url.searchParams.set('status',   f.status);
        if (f.location) url.searchParams.set('location', f.location);
        if (f.campus)   url.searchParams.set('campus',   f.campus);
        return url.toString();
    }

    function setLoading(state) {
        isLoading = state;
        const btn = document.getElementById('loadMoreBtn');
        if (btn) {
            btn.disabled = state;
            if (state) {
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Loading...';
            } else {
                btn.innerHTML = '<i class="fas fa-chevron-down me-2"></i>Load More <span id="loadMoreCount"></span>';
                updateLoadMoreCount();
            }
        }
    }

    function updateLoadMoreCount() {
        const el = document.getElementById('loadMoreCount');
        if (!el) return;
        const remaining = grandTotal - totalLoaded;
        el.textContent = remaining > 0 ? `(${remaining} more)` : '';
    }

    function updateBadges(total) {
        grandTotal = total;
        ['tableBadge', 'filterBadge'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.textContent = total + ' items';
        });
    }

    // Full reset + reload from page 1
    function resetAndReload() {
        currentPage = 1;
        hasMore     = true;
        totalLoaded = 0;
        grandTotal  = 0;
        // Show loader
        const tbody = document.getElementById('equipmentTableBody');
        if (tbody) {
            tbody.innerHTML = `<tr id="initialLoader">
                <td colspan="11" class="text-center py-5">
                    <div class="d-flex flex-column align-items-center gap-2">
                        <div class="spinner-border text-success" role="status"></div>
                        <span class="text-muted">Loading equipment...</span>
                    </div>
                </td>
            </tr>`;
        }
        const lmc = document.getElementById('loadMoreContainer');
        const eol = document.getElementById('endOfListMsg');
        if (lmc) lmc.style.display = 'none';
        if (eol) eol.style.display = 'none';
        loadPage(1);
    }

    function loadPage(page) {
        if (isLoading || (!hasMore && page > 1)) return;
        setLoading(true);

        fetch(buildURL(page))
            .then(r => r.json())
            .then(data => {
                const tbody = document.getElementById('equipmentTableBody');

                // Clear on first page load
                if (page === 1) {
                    tbody.innerHTML = '';
                }

                if (!data.html && page === 1) {
                    tbody.innerHTML = `
                        <tr><td colspan="11" class="text-center py-5">
                            <div class="empty-state">
                                <div class="empty-state-icon"><i class="fas fa-box-open"></i></div>
                                <h5 class="mb-2">No Equipment Found</h5>
                                <p class="text-muted mb-3">Try adjusting your filters or search term.</p>
                            </div>
                        </td></tr>`;
                    updateBadges(0);
                    setLoading(false);
                    return;
                }

                // Inject HTML — use cloneNode trick so inline <script> tags execute
                const tmp = document.createElement('tbody');
                tmp.innerHTML = data.html;
                tmp.querySelectorAll('script').forEach(oldScript => {
                    const newScript = document.createElement('script');
                    newScript.textContent = oldScript.textContent;
                    document.body.appendChild(newScript);
                    document.body.removeChild(newScript);
                });
                while (tmp.firstChild) tbody.appendChild(tmp.firstChild);

                hasMore     = data.has_more;
                totalLoaded = data.loaded;
                currentPage = data.page;

                // Update badges and stat cards on first page
                if (page === 1 && data.stats) {
                    updateBadges(data.stats.total);
                    const statCards = document.querySelectorAll('.stat-content h3');
                    if (statCards[0]) statCards[0].textContent = data.stats.total;
                    if (statCards[1]) statCards[1].textContent = data.stats.assigned;
                    if (statCards[2]) statCards[2].textContent = data.stats.unassigned;
                    if (statCards[3]) statCards[3].textContent = data.stats.maintenance;
                } else if (page > 1) {
                    updateBadges(grandTotal); // keep showing correct total
                }

                // Show/hide load more and end-of-list
                const lmc = document.getElementById('loadMoreContainer');
                const eol = document.getElementById('endOfListMsg');
                if (lmc) lmc.style.display = hasMore ? 'block' : 'none';
                if (eol) eol.style.display = (!hasMore && totalLoaded > 0) ? 'block' : 'none';

                // Update loaded count info
                const loadedInfo = document.getElementById('loadedInfo');
                if (loadedInfo) loadedInfo.textContent = `Showing ${totalLoaded} of ${grandTotal}`;

                updateLoadMoreCount();
                setLoading(false);
            })
            .catch(err => {
                console.error('Lazy-load error:', err);
                if (page === 1) {
                    document.getElementById('equipmentTableBody').innerHTML =
                        '<tr><td colspan="11" class="text-center py-4 text-danger"><i class="fas fa-exclamation-circle me-2"></i>Failed to load equipment. Please refresh the page.</td></tr>';
                }
                setLoading(false);
            });
    }

    // Public: Load More button
    window.loadMoreEquipment = function() {
        if (hasMore && !isLoading) loadPage(currentPage + 1);
    };

    // Public: clear search
    window.clearSearch = function() {
        const input = document.getElementById('equipmentSearch');
        if (input) {
            input.value = '';
            document.getElementById('clearSearchBtn').style.display = 'none';
            resetAndReload();
        }
    };

    // Infinite scroll: trigger when 400px from bottom
    window.addEventListener('scroll', function() {
        if (!hasMore || isLoading) return;
        if (window.scrollY + window.innerHeight >= document.documentElement.scrollHeight - 400) {
            loadPage(currentPage + 1);
        }
    });

    // Wire up search input with 500ms debounce
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('equipmentSearch');
        const clearBtn    = document.getElementById('clearSearchBtn');
        const perPageSel  = document.getElementById('perPageSelect');

        if (searchInput) {
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimer);
                clearBtn.style.display = this.value ? 'inline-flex' : 'none';
                searchTimer = setTimeout(resetAndReload, 500);
            });
            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    clearTimeout(searchTimer);
                    resetAndReload();
                }
            });
        }

        if (perPageSel) {
            perPageSel.addEventListener('change', function() {
                perPage = parseInt(this.value);
                resetAndReload();
            });
        }

        // Kick off first load
        loadPage(1);
    });
})();
</script>

<?php include '../includes/footer.php'; ?>