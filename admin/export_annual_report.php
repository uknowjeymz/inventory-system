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

$year       = $_GET['year']       ?? date('Y');
$category   = $_GET['category']   ?? 'all';
$department = $_GET['department'] ?? 'all';

// ── Item-level query ──
$query = "SELECT
            c.category,
            rg.office     AS department,
            c.item_name,
            c.unit,
            SUM(ri.quantity)      AS total_quantity,
            COUNT(DISTINCT rg.id) AS request_count,
            ROUND(AVG(ri.quantity),1) AS avg_per_request
          FROM request_items ri
          JOIN request_groups rg ON ri.group_id = rg.id
          JOIN consumables    c  ON ri.consumable_id = c.id
          WHERE rg.status = 'Approved'
            AND YEAR(rg.request_date) = :year";
if ($category   !== 'all') $query .= " AND c.category = :category";
if ($department !== 'all') $query .= " AND rg.office  = :department";
$query .= " GROUP BY c.category, rg.office, c.item_name
            ORDER BY c.category, rg.office, total_quantity DESC";

$stmt = $db->prepare($query);
$stmt->bindParam(':year', $year);
if ($category   !== 'all') $stmt->bindParam(':category',   $category);
if ($department !== 'all') $stmt->bindParam(':department', $department);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Summary ──
$sq = "SELECT SUM(ri.quantity) AS total_consumption, COUNT(DISTINCT rg.id) AS total_requests,
              COUNT(DISTINCT c.category) AS total_categories, COUNT(DISTINCT rg.office) AS total_departments
       FROM request_items ri JOIN request_groups rg ON ri.group_id=rg.id JOIN consumables c ON ri.consumable_id=c.id
       WHERE rg.status='Approved' AND YEAR(rg.request_date)=:year";
if ($category   !== 'all') $sq .= " AND c.category=:category";
if ($department !== 'all') $sq .= " AND rg.office=:department";
$ss = $db->prepare($sq);
$ss->bindParam(':year', $year);
if ($category   !== 'all') $ss->bindParam(':category',   $category);
if ($department !== 'all') $ss->bindParam(':department', $department);
$ss->execute();
$summary = $ss->fetch(PDO::FETCH_ASSOC);

// ── Group ──
$grouped     = [];
$grand_total = 0;
foreach ($rows as $r) {
    $grouped[$r['category']][$r['department']][] = $r;
    $grand_total += (int)$r['total_quantity'];
}

// ── Spreadsheet ──
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Annual Consumption');

$spreadsheet->getProperties()
    ->setCreator($_SESSION['full_name'] ?? 'Admin')
    ->setTitle("Annual Consumption Report - {$year}");

$GREEN  = '198754';
$LGREEN = 'D6EFDF';
$IROW   = 'F2FBF5';
$WHITE  = 'FFFFFF';

// Title
$sheet->mergeCells('A1:G1');
$sheet->setCellValue('A1', 'ANNUAL CONSUMPTION SUMMARY REPORT — ' . $year);
$sheet->getStyle('A1')->applyFromArray([
    'font'      => ['bold'=>true,'size'=>16,'color'=>['rgb'=>$WHITE]],
    'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>$GREEN]],
    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
]);
$sheet->getRowDimension(1)->setRowHeight(28);

// Filter
$sheet->mergeCells('A2:G2');
$fi = "Year: {$year}";
if ($category   !== 'all') $fi .= " | Category: {$category}";
if ($department !== 'all') $fi .= " | Department: {$department}";
$sheet->setCellValue('A2', $fi);
$sheet->getStyle('A2')->applyFromArray([
    'font'      => ['italic'=>true,'size'=>9],
    'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'EBF9F0']],
    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER],
]);

// Generated
$sheet->mergeCells('A3:G3');
$sheet->setCellValue('A3', 'Generated: ' . date('F d, Y  h:i A') . '   |   By: ' . ($_SESSION['full_name'] ?? 'Admin'));
$sheet->getStyle('A3')->applyFromArray([
    'font'      => ['italic'=>true,'size'=>8,'color'=>['rgb'=>'888888']],
    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER],
]);

// Summary
$avg = ($summary['total_requests'] > 0)
    ? round(($summary['total_consumption'] ?? 0) / $summary['total_requests'], 1) : 0;
$sheet->mergeCells('A4:G4');
$sheet->setCellValue('A4',
    'Total Consumed: ' . number_format($summary['total_consumption'] ?? 0) . ' items   |   ' .
    'Requests: ' . number_format($summary['total_requests'] ?? 0) . '   |   ' .
    'Categories: ' . ($summary['total_categories'] ?? 0) . '   |   ' .
    'Departments: ' . ($summary['total_departments'] ?? 0) . '   |   ' .
    'Avg/Request: ' . $avg
);
$sheet->getStyle('A4')->applyFromArray([
    'font'      => ['bold'=>true,'size'=>9,'color'=>['rgb'=>'0A4030']],
    'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'C8EDD5']],
    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER],
]);

$sheet->mergeCells('A5:G5'); $sheet->setCellValue('A5','');

// Column headers
$headers = ['Category','Department','Requested Item','Unit','Requests','Total Qty','Avg/Req'];
foreach ($headers as $i => $h) {
    $sheet->setCellValue(chr(65+$i).'6', $h);
}
$sheet->getStyle('A6:G6')->applyFromArray([
    'font'      => ['bold'=>true,'color'=>['rgb'=>$WHITE],'size'=>10],
    'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>$GREEN]],
    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
    'borders'   => ['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>$WHITE]]],
]);
$sheet->getRowDimension(6)->setRowHeight(20);

// Data
$row = 7;
foreach ($grouped as $cat => $depts) {
    foreach ($depts as $dept => $items) {
        $deptTotal = array_sum(array_column($items, 'total_quantity'));
        $deptReqs  = array_sum(array_column($items, 'request_count'));
        $deptAvg   = $deptReqs > 0 ? round($deptTotal / $deptReqs, 1) : 0;

        // Group header
        $sheet->setCellValue('A'.$row, $cat);
        $sheet->setCellValue('B'.$row, $dept);
        $sheet->setCellValue('C'.$row, count($items) . ' item type(s)');
        $sheet->setCellValue('D'.$row, '');
        $sheet->setCellValue('E'.$row, $deptReqs);
        $sheet->setCellValue('F'.$row, number_format($deptTotal));
        $sheet->setCellValue('G'.$row, $deptAvg);

        $sheet->getStyle('A'.$row.':G'.$row)->applyFromArray([
            'font'    => ['bold'=>true,'size'=>9],
            'fill'    => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>$LGREEN]],
            'borders' => ['top'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>'90C8A0']]],
        ]);
        $sheet->getStyle('C'.$row)->getFont()->setBold(false)->setItalic(true)->setSize(8);
        $sheet->getStyle('C'.$row)->getFont()->getColor()->setRGB('666666');
        $sheet->getStyle('E'.$row.':G'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('F'.$row)->getFont()->getColor()->setRGB($GREEN);
        $row++;

        // Item rows
        foreach ($items as $item) {
            $sheet->setCellValue('A'.$row, '');
            $sheet->setCellValue('B'.$row, '');
            $sheet->setCellValue('C'.$row, '    ↳  ' . $item['item_name']);
            $sheet->setCellValue('D'.$row, $item['unit'] ?? '-');
            $sheet->setCellValue('E'.$row, $item['request_count']);
            $sheet->setCellValue('F'.$row, number_format($item['total_quantity']));
            $sheet->setCellValue('G'.$row, $item['avg_per_request']);

            $sheet->getStyle('A'.$row.':G'.$row)->applyFromArray([
                'fill'    => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>$IROW]],
                'borders' => ['bottom'=>['borderStyle'=>Border::BORDER_HAIR,'color'=>['rgb'=>'CCCCCC']]],
            ]);
            $sheet->getStyle('C'.$row)->getFont()->setItalic(true)->setSize(9);
            $sheet->getStyle('D'.$row.':G'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $row++;
        }
    }
}

// Grand total
$sheet->mergeCells('A'.$row.':F'.$row);
$sheet->setCellValue('A'.$row, 'GRAND TOTAL');
$sheet->setCellValue('G'.$row, number_format($grand_total));
$sheet->getStyle('A'.$row.':G'.$row)->applyFromArray([
    'font'      => ['bold'=>true,'color'=>['rgb'=>$WHITE],'size'=>11],
    'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>$GREEN]],
    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER],
    'borders'   => ['top'=>['borderStyle'=>Border::BORDER_MEDIUM]],
]);

// Widths
$sheet->getColumnDimension('A')->setWidth(24);
$sheet->getColumnDimension('B')->setWidth(24);
$sheet->getColumnDimension('C')->setWidth(38);
$sheet->getColumnDimension('D')->setWidth(10);
$sheet->getColumnDimension('E')->setWidth(12);
$sheet->getColumnDimension('F')->setWidth(14);
$sheet->getColumnDimension('G')->setWidth(12);
$sheet->freezePane('A7');

// Output
$filename = "Annual_Consumption_{$year}";
if ($category   !== 'all') $filename .= "_{$category}";
if ($department !== 'all') $filename .= "_{$department}";
$filename .= "_" . date('Ymd') . ".xlsx";

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');
(new Xlsx($spreadsheet))->save('php://output');
exit;
