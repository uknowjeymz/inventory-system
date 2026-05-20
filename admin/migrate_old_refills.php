<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    die("Unauthorized access");
}

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

echo "<h2>Migrate Old Refill Data to Consumables History</h2>";
echo "<p>This script will copy all records from <code>consumable_refills</code> to <code>consumables_history</code></p>";

try {
    // Check if both tables exist
    $check_old = $db->query("SHOW TABLES LIKE 'consumable_refills'")->fetch();
    $check_new = $db->query("SHOW TABLES LIKE 'consumables_history'")->fetch();
    
    if (!$check_old) {
        die("<p style='color: red;'>❌ consumable_refills table does not exist. Nothing to migrate.</p>");
    }
    
    if (!$check_new) {
        die("<p style='color: red;'>❌ consumables_history table does not exist. Please run the migration SQL first.</p>");
    }
    
    echo "<p style='color: green;'>✅ Both tables exist</p>";
    
    // Get all refills from old table
    $old_refills = $db->query("SELECT * FROM consumable_refills ORDER BY refill_date ASC")->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p>Found <strong>" . count($old_refills) . "</strong> records in consumable_refills table</p>";
    
    if (count($old_refills) == 0) {
        echo "<p>No records to migrate.</p>";
        exit;
    }
    
    // Check which ones already exist in new table
    $existing_check = $db->query("
        SELECT consumable_id, action_date, quantity_change 
        FROM consumables_history 
        WHERE action_type = 'refill' AND reference_type = 'manual_refill'
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    $existing_map = [];
    foreach ($existing_check as $record) {
        $key = $record['consumable_id'] . '_' . $record['action_date'] . '_' . $record['quantity_change'];
        $existing_map[$key] = true;
    }
    
    echo "<p>Found <strong>" . count($existing_map) . "</strong> existing refill records in consumables_history</p>";
    
    // Start migration
    $db->beginTransaction();
    
    $insert_query = "INSERT INTO consumables_history 
                     (consumable_id, action_type, previous_quantity, quantity_change, new_quantity, 
                      action_date, performed_by, reference_type, reference_id, remarks, created_at) 
                     VALUES (?, 'refill', ?, ?, ?, ?, ?, 'manual_refill', ?, ?, ?)";
    
    $stmt = $db->prepare($insert_query);
    
    $migrated = 0;
    $skipped = 0;
    
    foreach ($old_refills as $refill) {
        // Check if this record already exists
        $key = $refill['consumable_id'] . '_' . $refill['refill_date'] . '_' . $refill['refill_quantity'];
        
        if (isset($existing_map[$key])) {
            $skipped++;
            continue;
        }
        
        // Insert into new table
        $stmt->execute([
            $refill['consumable_id'],
            $refill['previous_quantity'],
            $refill['refill_quantity'],
            $refill['new_quantity'],
            $refill['refill_date'],
            $refill['refilled_by'],
            $refill['id'], // Store old refill ID as reference
            $refill['remarks'] ?? '',
            $refill['refill_date'] // Use refill_date as created_at
        ]);
        
        $migrated++;
    }
    
    $db->commit();
    
    echo "<hr>";
    echo "<p style='color: green; font-size: 18px;'><strong>✅ Migration Complete!</strong></p>";
    echo "<ul>";
    echo "<li><strong>$migrated</strong> records migrated successfully</li>";
    echo "<li><strong>$skipped</strong> records skipped (already exist)</li>";
    echo "</ul>";
    
    // Show summary
    $total_history = $db->query("SELECT COUNT(*) as total FROM consumables_history")->fetch();
    echo "<p>Total records in consumables_history: <strong>{$total_history['total']}</strong></p>";
    
    echo "<hr>";
    echo "<p><a href='consumables.php' class='btn btn-primary'>← Back to Consumables</a></p>";
    echo "<p><a href='test_history_table.php'>Run Diagnostic Again</a></p>";
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>
