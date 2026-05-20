<?php
session_start();
require_once '../config/database.php';
$db = (new Database())->getConnection();

if ($_POST) {
    $ids_array = explode(',', $_POST['selected_ids']); 
    $ids_string = $_POST['selected_ids']; // Example: "12,15,22"
    $type = $_POST['equipment_type'];
    $to_campus = $_POST['to_campus'];
    
    // Combine the name fields into a single string: "Last Name, First Name M.I."
    $lastname = trim($_POST['new_accountable_lastname'] ?? '');
    $firstname = trim($_POST['new_accountable_firstname'] ?? '');
    $middle = trim($_POST['new_accountable_middle'] ?? '');
    
    $new_accountable = $lastname;
    if (!empty($firstname)) {
        $new_accountable .= ', ' . $firstname;
    }
    if (!empty($middle)) {
        $new_accountable .= ' ' . $middle . '.';
    }
    
    $table_map = [
        'computer' => 'computer_inventory', 'computer_lab' => 'computer_inventory',
        'kitchen' => 'kitchen_equipment', 'office' => 'office_equipment',
        'lab' => 'lab_equipment', 'regular_lab' => 'lab_equipment',
        'general' => 'general_equipment'
    ];
    $table = $table_map[$type];

    try {
        $db->beginTransaction();

        // 1. Get info from the FIRST item to capture the "Origin" for the batch
        $stmt = $db->prepare("SELECT campus, remarks FROM $table WHERE id = ?");
        $stmt->execute([$ids_array[0]]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);

        // 2. Update ALL selected items in the main inventory table
        $placeholders = implode(',', array_fill(0, count($ids_array), '?'));
        $upd_sql = "UPDATE $table SET campus = ?, remarks = ?, location_id = NULL, updated_at = NOW() WHERE id IN ($placeholders)";
        $upd_stmt = $db->prepare($upd_sql);
        $params = array_merge([$to_campus, $new_accountable], $ids_array);
        $upd_stmt->execute($params);

        // 3. Log ONE row for the entire batch
        $log = $db->prepare("INSERT INTO transfer_history 
            (equipment_ids, equipment_type, from_campus, to_campus, previous_accountable, new_accountable, transferred_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        $log->execute([
            $ids_string, 
            $type, 
            $current['campus'], 
            $to_campus, 
            $current['remarks'], 
            $new_accountable, 
            $_SESSION['user_id']
        ]);

        $db->commit();
        $_SESSION['equipment_success'] = "Batch transfer of " . count($ids_array) . " items successful!";
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['equipment_error'] = "Transfer failed: " . $e->getMessage();
    }
}
header("Location: all_equipment.php");