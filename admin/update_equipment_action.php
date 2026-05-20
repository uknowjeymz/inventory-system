<?php
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $database = new Database();
    $db = $database->getConnection();

    $id = $_POST['id'];
    
    // Match the form field names
    $current_table = $_POST['table'] ?? '';
    $new_type = $_POST['equipment_type'] ?? '';

    $table_map = [
        'computer' => 'computer_inventory',
        'computer_lab' => 'computer_inventory',
        'kitchen'  => 'kitchen_equipment',
        'office'   => 'office_equipment',
        'lab'      => 'lab_equipment',
        'regular_lab' => 'lab_equipment',
        'general'  => 'general_equipment'
    ];

    // Validate table
    if (!isset($table_map[$new_type])) {
        $_SESSION['equipment_error'] = "Invalid equipment type: " . $new_type;
        header("Location: all_equipment.php");
        exit();
    }

    $target_table = $table_map[$new_type];

    try {
        $db->beginTransaction();

        // Get columns of the target table
        $stmt_cols = $db->prepare("DESCRIBE $target_table");
        $stmt_cols->execute();
        $allowed_columns = $stmt_cols->fetchAll(PDO::FETCH_COLUMN);

        $update_values = [];
        $exclude = ['id', 'table', 'equipment_type', 'action', 'purchase_date_option', 
                    'accountable_lastname', 'accountable_firstname', 'accountable_middle']; // Added name fields to exclude

        // --- HANDLE ACCOUNTABLE PERSON NAME (remarks field) ---
        // Combine Last Name, First Name, and Middle Initial into a full name format
        if (isset($_POST['accountable_lastname']) && trim($_POST['accountable_lastname']) !== '') {
            $lastname = trim($_POST['accountable_lastname']);
            $firstname = trim($_POST['accountable_firstname'] ?? '');
            $middle = trim($_POST['accountable_middle'] ?? '');
            
            // Build the full name (Format: Last Name, First Name M.I.)
            $full_name = $lastname;
            if (!empty($firstname)) {
                $full_name .= ', ' . $firstname;
            }
            if (!empty($middle)) {
                $full_name .= ' ' . $middle;
            }
            
            // Set the remarks field with the combined name
            $_POST['remarks'] = $full_name;
        } else if (isset($_POST['accountable_lastname']) && trim($_POST['accountable_lastname']) === '') {
            // If last name is empty, set remarks to empty string
            $_POST['remarks'] = '';
        }
        // If no accountable fields were submitted, keep original remarks value

        // Map data from POST to valid table columns
        foreach ($_POST as $key => $value) {
            if (in_array($key, $exclude)) continue;
            
            // Handle empty strings properly
            $final_val = (is_string($value) && trim($value) === "") ? null : $value;

            if (in_array($key, $allowed_columns)) {
                $update_values[$key] = $final_val;
            }
        }

        // Handle purchase date based on radio button selection
        if (isset($_POST['purchase_date_option'])) {
            if ($_POST['purchase_date_option'] === 'no') {
                // If "No Date" selected, set purchase_date to NULL
                $update_values['purchase_date'] = null;
            } else {
                // If "Yes" selected, use the purchase_date value from POST
                if (isset($_POST['purchase_date']) && !empty($_POST['purchase_date'])) {
                    $update_values['purchase_date'] = $_POST['purchase_date'];
                } else {
                    $update_values['purchase_date'] = null;
                }
            }
        }

        // Handle file upload if present
        $image_path = null;
        if (isset($_FILES['equipment_photo']) && $_FILES['equipment_photo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/equipment/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            
            $file_ext = pathinfo($_FILES['equipment_photo']['name'], PATHINFO_EXTENSION);
            $file_name = $new_type . '_' . time() . '.' . $file_ext;
            $target_file = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['equipment_photo']['tmp_name'], $target_file)) {
                $image_path = 'uploads/equipment/' . $file_name;
                $update_values['image_path'] = $image_path;
            }
        }

        if (empty($update_values)) {
            throw new Exception("No fields to update");
        }

        if ($current_table === $target_table) {
            // SCENARIO 1: Simple Update
            $sets = [];
            $params = [];
            foreach ($update_values as $col => $val) {
                $sets[] = "$col = ?";
                $params[] = $val;
            }
            $params[] = $id;

            $sql = "UPDATE $current_table SET " . implode(', ', $sets) . ", updated_at = NOW() WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
        } else {
            // SCENARIO 2: Move to New Table (Migration)
            $stmt_old = $db->prepare("SELECT * FROM $current_table WHERE id = ?");
            $stmt_old->execute([$id]);
            $old_row = $stmt_old->fetch(PDO::FETCH_ASSOC);

            if (!$old_row) {
                throw new Exception("Original record not found in $current_table");
            }

            $final_row = [];
            foreach ($allowed_columns as $col) {
                if ($col == 'id' || $col == 'created_at') continue;
                
                if (array_key_exists($col, $update_values)) {
                    $final_row[$col] = $update_values[$col];
                } elseif (isset($old_row[$col])) {
                    $final_row[$col] = $old_row[$col];
                } else {
                    // Defaults for NOT NULL columns
                    if (in_array($col, ['processor', 'ram', 'storage'])) {
                        $final_row[$col] = 'N/A';
                    } else if ($col === 'device_type') {
                        $final_row[$col] = 'Desktop';
                    } else if ($col === 'status') {
                        $final_row[$col] = 'available';
                    } else {
                        $final_row[$col] = null;
                    }
                }
            }

            if (empty($final_row)) {
                throw new Exception("No data to insert into $target_table");
            }

            $cols_str = implode(', ', array_keys($final_row));
            $placeholders = implode(', ', array_fill(0, count($final_row), '?'));
            
            $insert_sql = "INSERT INTO $target_table ($cols_str, created_at, updated_at) VALUES ($placeholders, NOW(), NOW())";
            $stmt = $db->prepare($insert_sql);
            $stmt->execute(array_values($final_row));

            // Delete from old table
            $stmt_del = $db->prepare("DELETE FROM $current_table WHERE id = ?");
            $stmt_del->execute([$id]);
        }

        $db->commit();
        $_SESSION['equipment_success'] = "Equipment details updated successfully!";
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $_SESSION['equipment_error'] = "Error: " . $e->getMessage();
        
        // Log the error for debugging
        error_log("Update equipment error: " . $e->getMessage());
        if (isset($sql)) error_log("SQL: " . $sql);
        if (isset($params)) error_log("Params: " . print_r($params, true));
    }

    header("Location: all_equipment.php");
    exit();
} else {
    // If someone accesses this file directly without POST data
    header("Location: all_equipment.php");
    exit();
}