<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

require_once '../config/database.php';

// Handle CSV preview
$preview_data = [];
if ($_POST && isset($_FILES['csv_file']) && isset($_POST['preview'])) {
    $uploadedFile = $_FILES['csv_file'];
    
    if ($uploadedFile['error'] === UPLOAD_ERR_OK) {
        $tmpName = $uploadedFile['tmp_name'];
        
        if (($handle = fopen($tmpName, "r")) !== FALSE) {
            $header = fgetcsv($handle); // Skip header row
            $row_count = 0;
            
            while (($data = fgetcsv($handle)) !== FALSE && $row_count < 10) { // Preview first 10 rows
                $row_count++;
                
                // Monitor/System section
                if (!empty($data[0]) && !empty($data[1]) && trim($data[0]) !== '') {
                    $model = trim($data[0]);
                    $category = trim($data[1]);
                    $serial_number = isset($data[2]) && trim($data[2]) !== '' ? trim($data[2]) : 'N/A';
                    
                    if (strtolower($model) !== 'model' && strtolower($category) !== 'category') {
                        $preview_data[] = [
                            'model' => $model,
                            'category' => $category,
                            'serial_number' => $serial_number,
                            'equipment_type' => 'Monitor/System'
                        ];
                    }
                }
                
                // Keyboard section
                if (isset($data[4]) && isset($data[5]) && !empty($data[4]) && !empty($data[5]) && trim($data[4]) !== '') {
                    $model = trim($data[4]);
                    $category = trim($data[5]);
                    $serial_number = isset($data[6]) && trim($data[6]) !== '' ? trim($data[6]) : 'N/A';
                    
                    if (strtolower($model) !== 'model' && strtolower($category) !== 'category') {
                        $preview_data[] = [
                            'model' => $model,
                            'category' => $category,
                            'serial_number' => $serial_number,
                            'equipment_type' => 'Keyboard'
                        ];
                    }
                }
            }
            
            fclose($handle);
        }
    }
}

// Handle actual import
if ($_POST && isset($_FILES['csv_file']) && isset($_POST['import'])) {
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
            $row_number = 1; // Start from 1 since we skipped header
            
            while (($data = fgetcsv($handle)) !== FALSE) {
                $row_number++;
                try {
                    // Parse the condemned CSV format
                    // The CSV has two sections: Monitor/System and Keyboard
                    
                    // Monitor/System section (columns 0-2)
                    if (!empty($data[0]) && !empty($data[1]) && trim($data[0]) !== '') {
                        $model = trim($data[0]);
                        $category = trim($data[1]);
                        $serial_number = isset($data[2]) && trim($data[2]) !== '' ? trim($data[2]) : 'N/A';
                        
                        // Skip if this looks like a header or empty row
                        if (strtolower($model) === 'model' || strtolower($category) === 'category') {
                            continue;
                        }
                        
                        // Check if equipment already exists
                        $query = "SELECT id FROM condemned_equipment WHERE model = ? AND category = ? AND serial_number = ? AND equipment_type = 'monitor_system'";
                        $stmt = $db->prepare($query);
                        $stmt->execute([$model, $category, $serial_number]);
                        
                        if ($stmt->rowCount() == 0) {
                            // Insert new condemned equipment
                            $query = "INSERT INTO condemned_equipment (
                                model, category, serial_number, equipment_type, 
                                reason_condemned, condemned_by, disposal_status, condemned_date
                            ) VALUES (?, ?, ?, 'monitor_system', 'Imported from CSV - Equipment condemned due to damage/obsolescence', ?, 'pending', NOW())";
                            
                            $stmt = $db->prepare($query);
                            $stmt->execute([$model, $category, $serial_number, $_SESSION['user_id']]);
                            $imported++;
                        }
                    }
                    
                    // Keyboard section (columns 4-6)
                    if (isset($data[4]) && isset($data[5]) && !empty($data[4]) && !empty($data[5]) && trim($data[4]) !== '') {
                        $model = trim($data[4]);
                        $category = trim($data[5]);
                        $serial_number = isset($data[6]) && trim($data[6]) !== '' ? trim($data[6]) : 'N/A';
                        
                        // Skip if this looks like a header or empty row
                        if (strtolower($model) === 'model' || strtolower($category) === 'category') {
                            continue;
                        }
                        
                        // Check if equipment already exists
                        $query = "SELECT id FROM condemned_equipment WHERE model = ? AND category = ? AND serial_number = ? AND equipment_type = 'keyboard'";
                        $stmt = $db->prepare($query);
                        $stmt->execute([$model, $category, $serial_number]);
                        
                        if ($stmt->rowCount() == 0) {
                            // Insert new condemned equipment
                            $query = "INSERT INTO condemned_equipment (
                                model, category, serial_number, equipment_type, 
                                reason_condemned, condemned_by, disposal_status, condemned_date
                            ) VALUES (?, ?, ?, 'keyboard', 'Imported from CSV - Equipment condemned due to damage/obsolescence', ?, 'pending', NOW())";
                            
                            $stmt = $db->prepare($query);
                            $stmt->execute([$model, $category, $serial_number, $_SESSION['user_id']]);
                            $imported++;
                        }
                    }
                    
                } catch (Exception $e) {
                    $errors[] = "Error importing row {$row_number}: " . $e->getMessage();
                }
            }
            
            fclose($handle);
            
            // Add some debugging information
            if ($imported > 0) {
                $_SESSION['import_success'] = "Successfully imported {$imported} condemned equipment items.";
            } else {
                $_SESSION['import_success'] = "CSV file processed, but no new items were imported. This might be because all items already exist in the database.";
            }
            
            if (!empty($errors)) {
                $_SESSION['import_errors'] = $errors;
            }
        } else {
            $_SESSION['error'] = "Could not read CSV file.";
        }
    } else {
        $_SESSION['error'] = "File upload error.";
    }
    
    header("Location: condemned.php");
    exit();
}

$page_title = "Import Condemned Equipment CSV";
include '../includes/header.php';
?>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Import Condemned Equipment from CSV</h6>
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            <h6><i class="fas fa-info-circle"></i> CSV Format Requirements</h6>
            <p>Your CSV file should have the following structure:</p>
            <ul>
                <li><strong>Columns 1-3:</strong> Monitor/System Unit data (Model, Category, Serial Number)</li>
                <li><strong>Columns 5-7:</strong> Keyboard data (Model, Category, Serial Number)</li>
            </ul>
            <p>The system will automatically detect and import both sections.</p>
        </div>
        
        <form method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="csv_file" class="form-label">Select CSV File</label>
                <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv" required>
                <div class="form-text">Only CSV files are accepted. Maximum file size: 10MB</div>
            </div>
            
            <div class="d-flex justify-content-between">
                <a href="condemned.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Condemned Equipment
                </a>
                <div>
                    <button type="submit" name="preview" class="btn btn-info me-2">
                        <i class="fas fa-eye"></i> Preview Data
                    </button>
                    <?php if (!empty($preview_data)): ?>
                    <button type="submit" name="import" class="btn btn-primary">
                        <i class="fas fa-upload"></i> Import CSV
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </form>
        
        <?php if (!empty($preview_data)): ?>
        <div class="mt-4">
            <div class="alert alert-info">
                <h6><i class="fas fa-eye me-2"></i>Preview Data (First 10 rows)</h6>
                <p>The following items will be imported. Click "Import CSV" to proceed.</p>
            </div>
            
            <div class="table-responsive">
                <table class="table table-sm table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Model</th>
                            <th>Category</th>
                            <th>Serial Number</th>
                            <th>Equipment Type</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($preview_data as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['model']); ?></td>
                            <td><?php echo htmlspecialchars($item['category']); ?></td>
                            <td><?php echo htmlspecialchars($item['serial_number']); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $item['equipment_type'] === 'Keyboard' ? 'success' : 'primary'; ?>">
                                    <?php echo htmlspecialchars($item['equipment_type']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
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
                        <th>Model</th>
                        <th>Category</th>
                        <th>SN#</th>
                        <th></th>
                        <th>Model</th>
                        <th>Category</th>
                        <th>SN#</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Lenovo</td>
                        <td>System Unit</td>
                        <td>P900TVMN</td>
                        <td></td>
                        <td>INT</td>
                        <td>Keyboard</td>
                        <td>N/A</td>
                    </tr>
                    <tr>
                        <td>LG</td>
                        <td>Monitor</td>
                        <td>4041NLV5E904</td>
                        <td></td>
                        <td>ASUS</td>
                        <td>Keyboard</td>
                        <td>3070</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>