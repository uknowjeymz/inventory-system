<?php
// Quick diagnostic script to check if consumables_history table exists
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

echo "<h2>Consumables History Table Diagnostic</h2>";

// Check if consumables_history table exists
try {
    $check_table = $db->query("SHOW TABLES LIKE 'consumables_history'")->fetch();
    
    if ($check_table) {
        echo "<p style='color: green;'>✅ consumables_history table EXISTS</p>";
        
        // Count records
        $count = $db->query("SELECT COUNT(*) as total FROM consumables_history")->fetch();
        echo "<p>Total records in consumables_history: <strong>{$count['total']}</strong></p>";
        
        // Show sample records
        if ($count['total'] > 0) {
            echo "<h3>Sample Records:</h3>";
            $sample = $db->query("SELECT * FROM consumables_history ORDER BY action_date DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
            echo "<pre>" . print_r($sample, true) . "</pre>";
        }
        
    } else {
        echo "<p style='color: red;'>❌ consumables_history table DOES NOT EXIST</p>";
        echo "<p><strong>Action Required:</strong> You need to run the migration SQL file:</p>";
        echo "<ol>";
        echo "<li>Open phpMyAdmin</li>";
        echo "<li>Select your database (ucc_labtech)</li>";
        echo "<li>Go to Import tab</li>";
        echo "<li>Choose file: <code>database/consumables_history_migration.sql</code></li>";
        echo "<li>Click 'Go'</li>";
        echo "</ol>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

// Check consumable_refills table (old table)
echo "<hr>";
try {
    $check_old = $db->query("SHOW TABLES LIKE 'consumable_refills'")->fetch();
    
    if ($check_old) {
        echo "<p style='color: green;'>✅ consumable_refills table EXISTS (old table)</p>";
        
        $count_old = $db->query("SELECT COUNT(*) as total FROM consumable_refills")->fetch();
        echo "<p>Total records in consumable_refills: <strong>{$count_old['total']}</strong></p>";
        
        if ($count_old['total'] > 0) {
            echo "<h3>Sample Records from Old Table:</h3>";
            $sample_old = $db->query("SELECT * FROM consumable_refills ORDER BY refill_date DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
            echo "<pre>" . print_r($sample_old, true) . "</pre>";
        }
    } else {
        echo "<p style='color: orange;'>⚠️ consumable_refills table does not exist</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Error checking old table: " . $e->getMessage() . "</p>";
}

// Test the get_refill_history.php endpoint
echo "<hr>";
echo "<h3>Testing get_refill_history.php endpoint:</h3>";

// Get a consumable ID to test with
try {
    $test_consumable = $db->query("SELECT id, item_name FROM consumables LIMIT 1")->fetch();
    if ($test_consumable) {
        echo "<p>Testing with consumable ID: {$test_consumable['id']} ({$test_consumable['item_name']})</p>";
        
        // Simulate the AJAX call
        $_GET['consumable_id'] = $test_consumable['id'];
        $_GET['limit'] = 10;
        
        ob_start();
        include 'get_refill_history.php';
        $response = ob_get_clean();
        
        echo "<h4>Response from get_refill_history.php:</h4>";
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
        
        // Try to decode JSON
        $json = json_decode($response, true);
        if ($json) {
            echo "<h4>Decoded JSON:</h4>";
            echo "<pre>" . print_r($json, true) . "</pre>";
        }
    } else {
        echo "<p>No consumables found to test with</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Error testing endpoint: " . $e->getMessage() . "</p>";
}
?>
