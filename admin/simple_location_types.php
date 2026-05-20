<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

$page_title = "Location Types Management";
include '../includes/header.php';
?>

<div class="container-fluid">
    <h2>Location Types - Simple Version</h2>
    
    <?php
    try {
        // Simple query
        $query = "SELECT * FROM location_types ORDER BY type_name";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $location_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($location_types)) {
            echo "<div class='alert alert-warning'>No location types found. <a href='create_location_types_table.php'>Run setup script</a></div>";
        } else {
            echo "<div class='alert alert-success'>Found " . count($location_types) . " location types</div>";
            
            echo "<div class='row'>";
            foreach ($location_types as $type) {
                echo "<div class='col-md-4 mb-3'>";
                echo "<div class='card'>";
                echo "<div class='card-body'>";
                echo "<h5 class='card-title'>" . htmlspecialchars($type['type_name']) . "</h5>";
                echo "<p class='card-text'>" . htmlspecialchars($type['description']) . "</p>";
                echo "<small class='text-muted'>Code: " . htmlspecialchars($type['type_code']) . "</small>";
                echo "</div>";
                echo "</div>";
                echo "</div>";
            }
            echo "</div>";
        }
        
    } catch (Exception $e) {
        echo "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
    }
    ?>
    
    <p><a href="location_types.php" class="btn btn-primary">Back to Full Version</a></p>
</div>

<?php include '../includes/footer.php'; ?>