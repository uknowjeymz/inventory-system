<?php
session_start();
date_default_timezone_set('Asia/Manila');
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    die("Unauthorized access");
}

require_once '../config/database.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

$database = new Database();
$db = $database->getConnection();

// ── Category summary ──
$query = "SELECT
            category,
            COUNT(*)                                              AS total_items,
            SUM(quantity)                                         AS total_stock,
            AVG(quantity)                                         AS avg_stock,
            SUM(CASE WHEN quantity <= 10 THEN 1 ELSE 0 END)      AS critical_stock,
            SUM(CASE WHEN quantity > 10 AND quantity <= 20 THEN 1 ELSE 0 END) AS low_stock,
            SUM(CASE WHEN quantity > 20 THEN 1 ELSE 0 END)       AS in_stock,
            COUNT(DISTINCT brand)                                 AS total_brands
          FROM consumables
          WHERE category IS NOT NULL AND category != ''
          GROUP BY category
          ORDER BY category";
$stmt = $db->prepare($query);
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Items per category (current inventory) ──
$items_stmt = $db->prepare(
    "SELECT category, item_name, quantity, unit, brand, status, identification
     FROM consumables WHERE category IS NOT NULL AND category != ''
     ORDER BY category, item_name"
);
$items_stmt->execute();
$items_by_category = [];
foreach ($items_stmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
    $items_by_category[$item['category']][] = $item;
}

// ── Consumption per category+department+item (all-time, approved) ──
$cons_stmt = $db->prepare(
    "SELECT c.category, rg.office AS department, c.item_name, c.unit,
            SUM(ri.quantity) AS total_consumed, COUNT(DISTINCT rg.id) AS request_count,
            MAX(rg.request_date) AS last_requested
     FROM request_items ri
     JOIN request_groups rg ON ri.group_id = rg.id
     JOIN consumables    c  ON ri.consumable_id = c.id
     WHERE rg.status = 'Approved'
     GROUP BY c.category, rg.office, c.item_name
     ORDER BY c.category, rg.office, total_consumed DESC"
);
$cons_stmt->execute();
$consumption = [];
foreach ($cons_stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $consumption[$r['category']][$r['department']][] = $r;
}

// ── Spreadsheet ──
$spreadsheet = new Spreadsheet();
$spreadsheet->getProperties()
    ->setCreator($_SESSION['full_name'] ?? 'Admin')
    ->setTitle("Category Inventory Report");

$AMBER  = 'F59E0B';
$DKAMB  = 'B45309';
$LBLUE  = 'EBF3FF';
$WHITE  = 'FFFFFF';
$GREEN  = '198754';
$RED    = 'DC3545';

// ═══════════════════════════════════════════════
// SHEET 1 — Category Summary
// ═══════════════════════════════════════════════
$sheet1 = $spreadsheet->getActiveSheet();
$sheet1->setTitle('Category Summary');

$sheet1->mergeCells('A1:I1');
$sheet1->setCellValue('A1', 'CATEGORY INVENTORY REPORT');
$sheet1->getStyle('A1')->applyFromArray([
    'font'      => ['bold'=>true,'size'=>16,'color'=>['rgb'=>$WHITE]],
    'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>$AMBER]],
    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
]);
$sheet1->getRowDimension(1)->setRowHeight(28);

$sheet1->mergeCells('A2:I2');
$sheet1->setCellValue('A2', 'Generated: ' . date('F d, Y  h:i A') . '   |   By: ' . ($_SESSION['full_name'] ?? 'Admin'));
$sheet1->getStyle('A2')->applyFromArray([
    'font'      => ['italic'=>true,'size'=>8,'color'=>['rgb'=>'888888']],
    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER],
]);

$sheet1->mergeCells('A3:I3'); $sheet1->setCellValue('A3','');

$headers1 = ['Category','Total Items','Total Stock','Avg Stock','In Stock','Low Stock','Critical Stock','Unique Brands','Health %'];
foreach ($headers1 as $i => $h) {
    $sheet1->setCellValue(chr(65+$i).'4', $h);
}
$sheet1->getStyle('A4:I4')->applyFromArray([
    'font'      => ['bold'=>true,'color'=>['rgb'=>$WHITE],'size'=>10],
    'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>$AMBER]],
    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
    'borders'   => ['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>$WHITE]]],
]);
$sheet1->getRowDimension(4)->setRowHeight(18);

$row = 5;
$grand_items = 0;
$grand_stock = 0;
foreach ($categories as $cat) {
    $grand_items += $cat['total_items'];
    $grand_stock += $cat['total_stock'];
    $hp = ($cat['total_items'] > 0) ? round(($cat['in_stock'] / $cat['total_items']) * 100, 1) : 0;
    $hpColor = $hp >= 70 ? $GREEN : ($hp >= 40 ? $AMBER : $RED);

    $sheet1->setCellValue('A'.$row, $cat['category']);
    $sheet1->setCellValue('B'.$row, $cat['total_items']);
    $sheet1->setCellValue('C'.$row, $cat['total_stock']);
    $sheet1->setCellValue('D'.$row, round($cat['avg_stock'], 1));
    $sheet1->setCellValue('E'.$row, $cat['in_stock']);
    $sheet1->setCellValue('F'.$row, $cat['low_stock']);
    $sheet1->setCellValue('G'.$row, $cat['critical_stock']);
    $sheet1->setCellValue('H'.$row, $cat['total_brands']);
    $sheet1->setCellValue('I'.$row, $hp . '%');

    $sheet1->getStyle('B'.$row.':I'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet1->getStyle('E'.$row)->getFont()->getColor()->setRGB($GREEN);
    $sheet1->getStyle('F'.$row)->getFont()->getColor()->setRGB($AMBER);
    $sheet1->getStyle('G'.$row)->getFont()->getColor()->setRGB($RED);

    if ($row % 2 == 0) {
        $sheet1->getStyle('A'.$row.':I'.$row)->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFFDF0');
    }
    $row++;
}

// Total row
$sheet1->setCellValue('A'.$row, 'TOTAL');
$sheet1->setCellValue('B'.$row, $grand_items);
$sheet1->setCellValue('C'.$row, $grand_stock);
$sheet1->getStyle('A'.$row.':I'.$row)->applyFromArray([
    'font' => ['bold'=>true],
    'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'F0F0F0']],
    'borders' => ['top'=>['borderStyle'=>Border::BORDER_MEDIUM]],
]);
$sheet1->getStyle('B'.$row.':I'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

foreach (range('A','I') as $c) { $sheet1->getColumnDimension($c)->setAutoSize(true); }

// ═══════════════════════════════════════════════
// SHEET 2 — Items by Category (Inventory)
// ═══════════════════════════════════════════════
$sheet2 = $spreadsheet->createSheet();
$sheet2->setTitle('Items by Category');

$sheet2->mergeCells('A1:G1');
$sheet2->setCellValue('A1', 'ITEMS BY CATEGORY — CURRENT INVENTORY');
$sheet2->getStyle('A1')->applyFromArray([
    'font'      => ['bold'=>true,'size'=>14,'color'=>['rgb'=>$WHITE]],
    'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>$AMBER]],
    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
]);
$sheet2->getRowDimension(1)->setRowHeight(24);

$sheet2->mergeCells('A2:G2');
$sheet2->setCellValue('A2', 'Generated: ' . date('F d, Y  h:i A'));
$sheet2->getStyle('A2')->applyFromArray([
    'font'      => ['italic'=>true,'size'=>8,'color'=>['rgb'=>'888888']],
    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER],
]);

$r2 = 4;
foreach ($items_by_category as $catName => $catItems) {
    // Category header
    $sheet2->mergeCells('A'.$r2.':G'.$r2);
    $sheet2->setCellValue('A'.$r2, strtoupper($catName) . '  (' . count($catItems) . ' items)');
    $sheet2->getStyle('A'.$r2)->applyFromArray([
        'font' => ['bold'=>true,'size'=>11,'color'=>['rgb'=>$DKAMB]],
        'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'FFF3CD']],
        'borders' => ['bottom'=>['borderStyle'=>Border::BORDER_MEDIUM,'color'=>['rgb'=>$AMBER]]],
    ]);
    $r2++;

    // Sub-headers
    foreach (['Item Name','Brand','Quantity','Unit','Status','ID Code','Health'] as $ci => $ch) {
        $sheet2->setCellValue(chr(65+$ci).$r2, $ch);
    }
    $sheet2->getStyle('A'.$r2.':G'.$r2)->applyFromArray([
        'font'    => ['bold'=>true,'size'=>9],
        'fill'    => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'F8F9FA']],
        'borders' => ['bottom'=>['borderStyle'=>Border::BORDER_THIN]],
    ]);
    $r2++;

    foreach ($catItems as $item) {
        $sColor = match($item['status']) {
            'Critical','Out of Stock' => $RED,
            'Low'                     => $AMBER,
            default                   => $GREEN,
        };
        $sheet2->setCellValue('A'.$r2, $item['item_name']);
        $sheet2->setCellValue('B'.$r2, $item['brand'] ?: 'Generic');
        $sheet2->setCellValue('C'.$r2, $item['quantity']);
        $sheet2->setCellValue('D'.$r2, $item['unit'] ?: '-');
        $sheet2->setCellValue('E'.$r2, $item['status']);
        $sheet2->setCellValue('F'.$r2, $item['identification'] ?? '');
        $sheet2->setCellValue('G'.$r2, $item['quantity'] <= 10 ? 'Critical' : ($item['quantity'] <= 20 ? 'Low' : 'Good'));
        $sheet2->getStyle('G'.$r2)->getFont()->getColor()->setRGB($sColor);
        $sheet2->getStyle('C'.$r2.':D'.$r2)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $r2++;
    }
    $r2++; // spacer
}
foreach (range('A','G') as $c) { $sheet2->getColumnDimension($c)->setAutoSize(true); }

// ═══════════════════════════════════════════════
// SHEET 3 — Consumption by Category & Department
// ═══════════════════════════════════════════════
$sheet3 = $spreadsheet->createSheet();
$sheet3->setTitle('Consumption by Dept');

$sheet3->mergeCells('A1:G1');
$sheet3->setCellValue('A1', 'ITEM CONSUMPTION BY CATEGORY & DEPARTMENT');
$sheet3->getStyle('A1')->applyFromArray([
    'font'      => ['bold'=>true,'size'=>14,'color'=>['rgb'=>$WHITE]],
    'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'0D6EFD']],
    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
]);
$sheet3->getRowDimension(1)->setRowHeight(24);

$sheet3->mergeCells('A2:G2');
$sheet3->setCellValue('A2', 'All-time approved requests  |  Generated: ' . date('F d, Y  h:i A'));
$sheet3->getStyle('A2')->applyFromArray([
    'font'      => ['italic'=>true,'size'=>8,'color'=>['rgb'=>'888888']],
    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER],
]);

// Column headers
foreach (['Category','Department','Requested Item','Unit','Requests','Total Consumed','Last Requested'] as $ci => $ch) {
    $sheet3->setCellValue(chr(65+$ci).'4', $ch);
}
$sheet3->getStyle('A4:G4')->applyFromArray([
    'font'      => ['bold'=>true,'color'=>['rgb'=>$WHITE],'size'=>10],
    'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'0D6EFD']],
    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER],
    'borders'   => ['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>$WHITE]]],
]);
$sheet3->getRowDimension(4)->setRowHeight(18);

$r3 = 5;
foreach ($consumption as $catName => $depts) {
    foreach ($depts as $deptName => $items) {
        $deptTotal = array_sum(array_column($items, 'total_consumed'));
        $deptReqs  = array_sum(array_column($items, 'request_count'));

        // Group header
        $sheet3->setCellValue('A'.$r3, $catName);
        $sheet3->setCellValue('B'.$r3, $deptName);
        $sheet3->setCellValue('C'.$r3, count($items) . ' item type(s)');
        $sheet3->setCellValue('D'.$r3, '');
        $sheet3->setCellValue('E'.$r3, $deptReqs);
        $sheet3->setCellValue('F'.$r3, number_format($deptTotal));
        $sheet3->setCellValue('G'.$r3, '');
        $sheet3->getStyle('A'.$r3.':G'.$r3)->applyFromArray([
            'font'    => ['bold'=>true,'size'=>9],
            'fill'    => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'DDE9FF']],
            'borders' => ['top'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>'B0C8F0']]],
        ]);
        $sheet3->getStyle('C'.$r3)->getFont()->setBold(false)->setItalic(true)->setSize(8);
        $sheet3->getStyle('C'.$r3)->getFont()->getColor()->setRGB('666666');
        $sheet3->getStyle('E'.$r3.':F'.$r3)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet3->getStyle('F'.$r3)->getFont()->getColor()->setRGB('0D6EFD');
        $r3++;

        foreach ($items as $item) {
            $sheet3->setCellValue('A'.$r3, '');
            $sheet3->setCellValue('B'.$r3, '');
            $sheet3->setCellValue('C'.$r3, '    ↳  ' . $item['item_name']);
            $sheet3->setCellValue('D'.$r3, $item['unit'] ?? '-');
            $sheet3->setCellValue('E'.$r3, $item['request_count']);
            $sheet3->setCellValue('F'.$r3, number_format($item['total_consumed']));
            $sheet3->setCellValue('G'.$r3, $item['last_requested'] ? date('M d, Y', strtotime($item['last_requested'])) : '-');
            $sheet3->getStyle('A'.$r3.':G'.$r3)->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F5F9FF');
            $sheet3->getStyle('C'.$r3)->getFont()->setItalic(true)->setSize(9);
            $sheet3->getStyle('D'.$r3.':F'.$r3)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet3->getStyle('A'.$r3.':G'.$r3)->getBorders()
                ->getBottom()->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB('DDDDDD');
            $r3++;
        }
    }
}

foreach (range('A','G') as $c) { $sheet3->getColumnDimension($c)->setAutoSize(true); }
$sheet3->getColumnDimension('C')->setWidth(38);
$sheet3->freezePane('A5');

// Set active to sheet 1
$spreadsheet->setActiveSheetIndex(0);

// Output
$filename = "Category_Report_" . date('Ymd') . ".xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');
(new Xlsx($spreadsheet))->save('php://output');
exit;
