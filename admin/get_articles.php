<?php
session_start();
header('Content-Type: application/json');
error_reporting(0); // Turn off error display for JSON
ini_set('display_errors', 0);

require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

$equipment_type = $_GET['type'] ?? '';

if (empty($equipment_type)) {
    echo json_encode(['success' => false, 'message' => 'Equipment type required']);
    exit;
}

// Define fallback articles for each equipment type
$fallback_articles = [
    'computer' => [
        ['article_name' => 'Laptop', 'has_dual_serial' => 0],
        ['article_name' => 'All-in-One', 'has_dual_serial' => 0],
        ['article_name' => 'Computer Package', 'has_dual_serial' => 1]
    ],
    'general' => [
        ['article_name' => 'Aircon', 'has_dual_serial' => 0],
        ['article_name' => 'Copier', 'has_dual_serial' => 0],
        ['article_name' => 'Projector', 'has_dual_serial' => 0],
        ['article_name' => 'Scanner', 'has_dual_serial' => 0],
        ['article_name' => 'Whiteboard', 'has_dual_serial' => 0],
        ['article_name' => 'Board', 'has_dual_serial' => 0],
        ['article_name' => 'Camera', 'has_dual_serial' => 0],
        ['article_name' => 'TV', 'has_dual_serial' => 0],
        ['article_name' => 'Sound System', 'has_dual_serial' => 0]
    ],
    'kitchen' => [
        ['article_name' => 'Refrigerator', 'has_dual_serial' => 0],
        ['article_name' => 'Stove', 'has_dual_serial' => 0],
        ['article_name' => 'Microwave', 'has_dual_serial' => 0],
        ['article_name' => 'Oven', 'has_dual_serial' => 0],
        ['article_name' => 'Blender', 'has_dual_serial' => 0]
    ],
    'office' => [
        ['article_name' => 'Chair', 'has_dual_serial' => 0],
        ['article_name' => 'Table', 'has_dual_serial' => 0],
        ['article_name' => 'Cabinet', 'has_dual_serial' => 0],
        ['article_name' => 'Filing Cabinet', 'has_dual_serial' => 0],
        ['article_name' => 'Desk', 'has_dual_serial' => 0]
    ],
    'lab' => [
        ['article_name' => 'Microscope', 'has_dual_serial' => 0],
        ['article_name' => 'Centrifuge', 'has_dual_serial' => 0],
        ['article_name' => 'Incubator', 'has_dual_serial' => 0],
        ['article_name' => 'Spectrophotometer', 'has_dual_serial' => 0],
        ['article_name' => 'pH Meter', 'has_dual_serial' => 0]
    ]
];

try {
    // Try to get articles from database
    $query = "SELECT id, article_name, has_dual_serial 
              FROM equipment_articles 
              WHERE equipment_type = :type AND is_active = 1 
              ORDER BY display_order ASC, article_name ASC";
    $stmt = $db->prepare($query);
    $stmt->execute([':type' => $equipment_type]);
    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If no articles found or empty result, use fallback
    if (empty($articles)) {
        $articles = $fallback_articles[$equipment_type] ?? [['article_name' => 'Default', 'has_dual_serial' => 0]];
    }
    
    echo json_encode(['success' => true, 'articles' => $articles]);
    
} catch (Exception $e) {
    // On error, return fallback articles
    $articles = $fallback_articles[$equipment_type] ?? [['article_name' => 'Default', 'has_dual_serial' => 0]];
    echo json_encode(['success' => true, 'articles' => $articles, 'error' => $e->getMessage()]);
}
?>