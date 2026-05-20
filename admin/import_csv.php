<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

require_once '../config/database.php';

if ($_POST && isset($_FILES['csv_file'])) {
    $database = new Database();
    $db = $database->getConnection();
    
    $uploadedFile = $_FILES['csv_file'];
    
    if ($uploadedFile['error'] === UPLOAD_ERR_OK) {
        $tmpName = $uploadedFile['tmp_name'];
        
        // Read CSV file
        if (($handle = fopen($tmpName, "r")) !== FALSE) {
            $header = fgetcsv($handle); // Skip header row
            $imported = 0;
            $errors = [];
            
            // Get location ID for Lab 1 (default)
            $query = "SELECT id FROM locations WHERE location_name = 'Lab 1' LIMIT 1";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $default_location = $stmt->fetch(PDO::FETCH_ASSOC);
            $default_location_id = $default_location ? $default_location['id'] : 1;
            
            while (($data = fgetcsv($handle)) !== FALSE) {
                try {
                    // Map CSV columns to database fields
                    $item_number = $data[0];
                    $computer_set_description = $data[1];
                    $processor = $data[2];
                    $ram = $data[3];
                    $storage = $data[4];
                    $device_type = $data[5];
                    $keyboard_status = $data[6] ?: 'OK';
                    $mouse_status = $data[7] ?: 'OK';
                    $power_cord_status = $data[8] ?: 'OK';
                    $hdmi_status = $data[9] ?: 'OK';
                    $operating_system = $data[10];
                    $serial_number = $data[11];
                    $condition_status = $data[12] ?: 'Good';
                    $location_name = $data[13] ?: 'Lab 1';
                    $remarks = $data[14] ?: '';
                    
                    // Get or create location
                    $query = "SELECT id FROM locations WHERE location_name = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$location_name]);
                    $location = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$location) {
                        // Create new location
                        $query = "INSERT INTO locations (location_name, description) VALUES (?, ?)";
                        $stmt = $db->prepare($query);
                        $stmt->execute([$location_name, "Auto-created from CSV import"]);
                        $location_id = $db->lastInsertId();
                    } else {
                        $location_id = $location['id'];
                    }
                    
                    // Check if computer already exists
                    $query = "SELECT id FROM computer_inventory WHERE item_number = ? OR serial_number = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$item_number, $serial_number]);
                    
                    if ($stmt->rowCount() == 0) {
                        // Insert new computer
                        $query = "INSERT INTO computer_inventory (
                            item_number, computer_set_description, processor, ram, storage, device_type,
                            keyboard_status, mouse_status, power_cord_status, hdmi_status,
                            operating_system, serial_number, condition_status, location_id, remarks, status
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'available')";
                        
                        $stmt = $db->prepare($query);
                        $stmt->execute([
                            $item_number, $computer_set_description, $processor, $ram, $storage, $device_type,
                            $keyboard_status, $mouse_status, $power_cord_status, $hdmi_status,
                            $operating_system, $serial_number, $condition_status, $location_id, $remarks
                        ]);
                        
                        $imported++;
                    } else {
                        $errors[] = "Item {$item_number} already exists (skipped)";
                    }
                    
                } catch (Exception $e) {
                    $errors[] = "Error importing item {$item_number}: " . $e->getMessage();
                }
            }
            
            fclose($handle);
            
            $_SESSION['import_success'] = "Successfully imported {$imported} computers.";
            if (!empty($errors)) {
                $_SESSION['import_errors'] = $errors;
            }
        } else {
            $_SESSION['error'] = "Could not read CSV file.";
        }
    } else {
        $_SESSION['error'] = "File upload error.";
    }
    
    header("Location: inventory.php");
    exit();
}

$page_title = "Import CSV Data";
include '../includes/header.php';
?>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Import Computer Inventory from CSV</h6>
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            <h6><i class="fas fa-info-circle"></i> CSV Format Requirements</h6>
            <p>Your CSV file should have the following columns in order:</p>
            <ol>
                <li>Item Number</li>
                <li>Computer Set Description</li>
                <li>Processor</li>
                <li>RAM</li>
                <li>Storage</li>
                <li>Device Type</li>
                <li>Keyboard Status</li>
                <li>Mouse Status</li>
                <li>Power Cord Status</li>
                <li>HDMI Status</li>
                <li>Operating System</li>
                <li>Serial Number</li>
                <li>Condition</li>
                <li>Location</li>
                <li>Remarks</li>
            </ol>
        </div>
        
        <form method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="csv_file" class="form-label">Select CSV File</label>
                <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv" required>
                <div class="form-text">Only CSV files are accepted. Maximum file size: 10MB</div>
            </div>
            
            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="skip_duplicates" name="skip_duplicates" checked>
                    <label class="form-check-label" for="skip_duplicates">
                        Skip duplicate entries (based on Item Number and Serial Number)
                    </label>
                </div>
            </div>
            
            <div class="d-flex justify-content-between">
                <a href="inventory.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Inventory
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-upload"></i> Import CSV
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-success">Sample CSV Data</h6>
    </div>
    <div class="card-body">
        <p>Here's an example of how your CSV data should be formatted:</p>
        <div class="table-responsive">
            <table class="table table-sm table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>Item No.</th>
                        <th>Description</th>
                        <th>Processor</th>
                        <th>RAM</th>
                        <th>Storage</th>
                        <th>Device Type</th>
                        <th>Keyboard</th>
                        <th>Mouse</th>
                        <th>Power Cord</th>
                        <th>HDMI</th>
                        <th>OS</th>
                        <th>Serial Number</th>
                        <th>Condition</th>
                        <th>Location</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>1</td>
                        <td>COMLAB1-PC 01</td>
                        <td>Intel Core i5</td>
                        <td>8GB</td>
                        <td>256GB SSD</td>
                        <td>Desktop</td>
                        <td>OK</td>
                        <td>OK</td>
                        <td>OK</td>
                        <td>OK</td>
                        <td>Windows 11</td>
                        <td>SN-DTBK7SP00241200DDC9600</td>
                        <td>Good</td>
                        <td>Lab 1</td>
                        <td>WORKING</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>