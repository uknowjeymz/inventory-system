<?php
session_start();
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    die("Unauthorized access");
}

require_once '../vendor/autoload.php';
require_once '../config/database.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Conditional;

$database = new Database();
$db = $database->getConnection();

// Get filter parameters
$stock_filter = $_GET['stock_filter'] ?? 'all';
$low_threshold = (int)($_GET['low'] ?? 10);
$critical_threshold = (int)($_GET['critical'] ?? 5);
$sort_by = $_GET['sort'] ?? 'item_name';
$category = $_GET['category'] ?? 'all';

// Build query based on filters
$query = "SELECT * FROM consumables WHERE 1=1";

// Category filter
if ($category !== 'all') {
    $query .= " AND category = " . $db->quote($category);
}

// Stock status filter
if ($stock_filter === 'available') {
    $query .= " AND quantity > $low_threshold";
} elseif ($stock_filter === 'low') {
    $query .= " AND quantity <= $low_threshold AND quantity > $critical_threshold";
} elseif ($stock_filter === 'critical') {
    $query .= " AND quantity <= $critical_threshold";
}

// Sorting
switch($sort_by) {
    case 'category':
        $query .= " ORDER BY category, item_name ASC";
        break;
    case 'quantity_asc':
        $query .= " ORDER BY quantity ASC, item_name ASC";
        break;
    case 'quantity_desc':
        $query .= " ORDER BY quantity DESC, item_name ASC";
        break;
    default:
        $query .= " ORDER BY item_name ASC";
}

$stmt = $db->query($query);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$total_items = count($items);
$low_count = 0;
$critical_count = 0;
$available_count = 0;

foreach ($items as $item) {
    if ($item['quantity'] <= $critical_threshold) {
        $critical_count++;
    } elseif ($item['quantity'] <= $low_threshold) {
        $low_count++;
    } else {
        $available_count++;
    }
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Consumables Inventory');

// 1. SET HEADER INFO
$sheet->setCellValue('A1', 'UNIVERSITY OF CALOOCAN CITY');
$sheet->setCellValue('A2', 'CONSUMABLE INVENTORY STATUS REPORT');
$sheet->setCellValue('A3', 'Generated on: ' . date("F d, Y - h:i A"));
$sheet->mergeCells('A1:G1');
$sheet->mergeCells('A2:G2');
$sheet->mergeCells('A3:G3');

// Add filter info
$filter_text = 'Filter: ';
if ($category !== 'all') $filter_text .= "Category: $category | ";
if ($stock_filter === 'available') $filter_text .= "Available (> $low_threshold)";
elseif ($stock_filter === 'low') $filter_text .= "Low Stock (≤ $low_threshold)";
elseif ($stock_filter === 'critical') $filter_text .= "Critical (≤ $critical_threshold)";
else $filter_text .= "All Items";

$sheet->setCellValue('A4', $filter_text);
$sheet->mergeCells('A4:G4');

// Style the title
$styleArrayTitle = [
    'font' => ['bold' => true, 'size' => 14],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
];
$sheet->getStyle('A1:A2')->applyFromArray($styleArrayTitle);
$sheet->getStyle('A3:A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('A4')->getFont()->setItalic(true)->setSize(10);

// 2. SET TABLE HEADERS
$headers = ['ID CODE', 'ITEM DESCRIPTION', 'CATEGORY', 'BRAND', 'STOCK', 'UNIT', 'STATUS'];
$columnLetter = 'A';
foreach ($headers as $header) {
    $sheet->setCellValue($columnLetter . '6', $header);
    $columnLetter++;
}

// Style the header row
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0D6EFD']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
];
$sheet->getStyle('A6:G6')->applyFromArray($headerStyle);

// 3. ADD SUMMARY STATISTICS
$sheet->setCellValue('A8', 'SUMMARY STATISTICS');
$sheet->mergeCells('A8:C8');
$sheet->getStyle('A8')->getFont()->setBold(true);
$sheet->setCellValue('A9', 'Total Items:');
$sheet->setCellValue('B9', $total_items);
$sheet->setCellValue('A10', 'Available:');
$sheet->setCellValue('B10', $available_count);
$sheet->setCellValue('A11', 'Low Stock:');
$sheet->setCellValue('B11', $low_count);
$sheet->setCellValue('A12', 'Critical:');
$sheet->setCellValue('B12', $critical_count);

// 4. POPULATE DATA (starting from row 14)
$rowNum = 14;
foreach ($items as $item) {
    $sheet->setCellValue('A' . $rowNum, $item['identification'] ?? 'N/A');
    $sheet->setCellValue('B' . $rowNum, $item['item_name']);
    $sheet->setCellValue('C' . $rowNum, $item['category'] ?? '-');
    $sheet->setCellValue('D' . $rowNum, $item['brand'] ?: '-');
    $sheet->setCellValue('E' . $rowNum, $item['quantity']);
    $sheet->setCellValue('F' . $rowNum, $item['unit'] ?? 'pcs');
    
    // Determine status
    if ($item['quantity'] <= $critical_threshold) {
        $status = 'CRITICAL';
    } elseif ($item['quantity'] <= $low_threshold) {
        $status = 'LOW STOCK';
    } else {
        $status = 'Available';
    }
    $sheet->setCellValue('G' . $rowNum, $status);

    // Center specific columns
    $sheet->getStyle('A' . $rowNum . ':G' . $rowNum)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getStyle('A' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('E' . $rowNum . ':G' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Color code based on status
    if ($item['quantity'] <= $critical_threshold) {
        $sheet->getStyle('G' . $rowNum)->getFont()->getColor()->setRGB('DC3545');
        $sheet->getStyle('G' . $rowNum)->getFont()->setBold(true);
        $sheet->getStyle('E' . $rowNum)->getFont()->getColor()->setRGB('DC3545');
        $sheet->getStyle('E' . $rowNum)->getFont()->setBold(true);
    } elseif ($item['quantity'] <= $low_threshold) {
        $sheet->getStyle('G' . $rowNum)->getFont()->getColor()->setRGB('FD7E14');
        $sheet->getStyle('G' . $rowNum)->getFont()->setBold(true);
        $sheet->getStyle('E' . $rowNum)->getFont()->getColor()->setRGB('FD7E14');
    }

    $rowNum++;
}

// 5. ADD CONDITIONAL FORMATTING FOR THE ENTIRE ROW
$conditionalStyles = [];
if ($rowNum > 14) {
    // Critical condition
    $conditionalCritical = new Conditional();
    $conditionalCritical->setConditionType(Conditional::CONDITION_CELLIS);
    $conditionalCritical->setOperatorType(Conditional::OPERATOR_LESSTHANOREQUAL);
    $conditionalCritical->addCondition($critical_threshold);
    $conditionalCritical->getStyle()->getFill()->setFillType(Fill::FILL_SOLID);
    $conditionalCritical->getStyle()->getFill()->getStartColor()->setRGB('F8D7DA');
    $conditionalCritical->getStyle()->getFont()->getColor()->setRGB('721C24');
    $conditionalStyles[] = $conditionalCritical;
    
    // Low condition
    $conditionalLow = new Conditional();
    $conditionalLow->setConditionType(Conditional::CONDITION_CELLIS);
    $conditionalLow->setOperatorType(Conditional::OPERATOR_BETWEEN);
    $conditionalLow->addCondition($critical_threshold + 1);
    $conditionalLow->addCondition($low_threshold);
    $conditionalLow->getStyle()->getFill()->setFillType(Fill::FILL_SOLID);
    $conditionalLow->getStyle()->getFill()->getStartColor()->setRGB('FFF3CD');
    $conditionalLow->getStyle()->getFont()->getColor()->setRGB('856404');
    $conditionalStyles[] = $conditionalLow;
    
    $sheet->getStyle('E14:E' . ($rowNum - 1))->setConditionalStyles($conditionalStyles);
}

// 6. AUTO-SIZE COLUMNS
foreach (range('A', 'G') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// 7. ADD BORDERS TO DATA
$sheet->getStyle('A6:G' . ($rowNum - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

// 8. FREEZE HEADER ROW
$sheet->freezePane('A7');

// 9. CREATE FILENAME AND STREAM
$filename = "UCC_Stock_Report_" . date('Y-m-d');
if ($category !== 'all') $filename .= "_" . preg_replace('/[^a-zA-Z0-9]/', '', $category);
if ($stock_filter !== 'all') $filename .= "_" . $stock_filter;
$filename .= ".xlsx";

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit();