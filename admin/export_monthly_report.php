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
$month      = $_GET['month']      ?? 'all';
$category   = $_GET['category']   ?? 'all';
$department = $_GET['department'] ?? 'all';

$month_names = [
    1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',6=>'June',
    7=>'July',8=>'August',9=>'September',10=>'October',11=>'November',12=>'December'
];

// ── Item-level query ──
$query = "SELECT
            MONTH(rg.request_date)     AS month_num,
            MONTHNAME(rg.request_date) AS month_name,
            c.category,
            rg.office                  AS department,
            c.item_name,
            c.unit,
            SUM(ri.quantity)           AS total_quantity,
            COUNT(DISTINCT rg.id)      AS request_count
          FROM request_items ri
          JOIN request_groups rg ON ri.group_id = rg.id
          JOIN consumables    c  ON ri.consumable_id = c.id
          WHERE rg.status = 'Approved'
            AND YEAR(rg.request_date) = :year";
if ($month      !== 'all') $query .= " AND MONTH(rg.request_date) = :month";
if ($category   !== 'all') $query .= " AND c.category = :category";
if ($department !== 'all') $query .= " AND rg.office  = :department";
$query .= " GROUP BY MONTH(rg.request_date), c.category, rg.office, c.item_name
            ORDER BY MONTH(rg.request_date), c.category, rg.office, total_quantity DESC";

$stmt = $db->prepare($query);
$stmt->bindParam(':year', $year);
if ($month      !== 'all') $stmt->bindParam(':month',      $month);
if ($category   !== 'all') $stmt->bindParam(':category',   $category);
if ($department !== 'all') $stmt->bindParam(':department', $department);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Summary ──
$sq = "SELECT SUM(ri.quantity) AS total_consumption,
              COUNT(DISTINCT rg.id) AS total_requests,
              COUNT(DISTINCT c.category) AS total_categories,
              COUNT(DISTINCT rg.office) AS total_departments
       FROM request_items ri
       JOIN request_groups rg ON ri.group_id = rg.id
       JOIN consumables    c  ON ri.consumable_id = c.id
       WHERE rg.status='Approved' AND YEAR(rg.request_date)=:year";
if ($month      !== 'all') $sq .= " AND MONTH(rg.request_date)=:month";
if ($category   !== 'all') $sq .= " AND c.category=:category";
if ($department !== 'all') $sq .= " AND rg.office=:department";
$ss = $db->prepare($sq);
$ss->bindParam(':year', $year);
if ($month      !== 'all') $ss->bindParam(':month',      $month);
if ($category   !== 'all') $ss->bindParam(':category',   $category);
if ($department !== 'all') $ss->bindParam(':department', $department);
$ss->execute();
$summary = $ss->fetch(PDO::FETCH_ASSOC);

// ── Group rows ──
$grouped     = [];
$grand_total = 0;
foreach ($rows as $r) {
    $mn   = (int)$r['month_num'];
    $cat  = $r['category'];
    $dept = $r['department'];
    $grouped[$mn][$cat][$dept][] = $r;
    $grand_total += (int)$r['total_quantity'];
}

// ── Spreadsheet ──
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Monthly Consumption');

$spreadsheet->getProperties()
    ->setCreator($_SESSION['full_name'] ?? 'Admin')
    ->setTitle("Monthly Consumption Report - {$year}");

$BLUE  = '0D6EFD';
$LBLUE = 'DDE9FF';
$IROW  = 'F5F9FF';
$WHITE = 'FFFFFF';

// Title
$sheet->mergeCells('A1:G1');
$sheet->setCellValue('A1', 'MONTHLY CONSUMPTION REPORT');
$sheet->getStyle('A1')->applyFromArray([
    'font'      => ['bold'=>true,'size'=>16,'color'=>['rgb'=>$WHITE]],
    'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>$BLUE]],
    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
]);
$sheet->getRowDimension(1)->setRowHeight(28);

// Filter info
$sheet->mergeCells('A2:G2');
$fi = "Year: {$year}";
if ($month      !== 'all') $fi .= " | Month: " . ($month_names[(int)$month] ?? $month);
if ($category   !== 'all') $fi .= " | Category: {$category}";
if ($department !== 'all') $fi .= " | Department: {$department}";
$sheet->setCellValue('A2', $fi);
$sheet->getStyle('A2')->applyFromArray([
    'font'      => ['italic'=>true,'size'=>9],
    'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'EBF3FF']],
    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER],
]);

// Generated on
$sheet->mergeCells('A3:G3');
$sheet->setCellValue('A3', 'Generated: ' . date('F d, Y  h:i A') . '   |   By: ' . ($_SESSION['full_name'] ?? 'Admin'));
$sheet->getStyle('A3')->applyFromArray([
    'font'      => ['italic'=>true,'size'=>8,'color'=>['rgb'=>'888888']],
    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER],
]);

// Summary row
$sheet->mergeCells('A4:G4');
$sheet->setCellValue('A4',
    'Total Consumed: ' . number_format($summary['total_consumption'] ?? 0) . ' items   |   ' .
    'Approved Requests: ' . number_format($summary['total_requests'] ?? 0) . '   |   ' .
    'Categories: ' . ($summary['total_categories'] ?? 0) . '   |   ' .
    'Departments: ' . ($summary['total_departments'] ?? 0)
);
$sheet->getStyle('A4')->applyFromArray([
    'font'      => ['bold'=>true,'size'=>9,'color'=>['rgb'=>'0A4580']],
    'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'D0E8FF']],
    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER],
]);

// Blank spacer
$sheet->mergeCells('A5:G5'); $sheet->setCellValue('A5','');

// Column headers
$headers = ['Month','Category','Department','Requested Item','Unit','Requests','Total Qty'];
foreach ($headers as $i => $h) {
    $sheet->setCellValue(chr(65+$i).'6', $h);
}
$sheet->getStyle('A6:G6')->applyFromArray([
    'font'      => ['bold'=>true,'color'=>['rgb'=>$WHITE],'size'=>10],
    'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>$BLUE]],
    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
    'borders'   => ['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>$WHITE]]],
]);
$sheet->getRowDimension(6)->setRowHeight(20);

// Data
$row = 7;
foreach ($grouped as $mn => $cats) {
    foreach ($cats as $cat => $depts) {
        foreach ($depts as $dept => $items) {
            $deptTotal = array_sum(array_column($items, 'total_quantity'));
            $deptReqs  = array_sum(array_column($items, 'request_count'));

            // Group header row (Month | Category | Department | — | — | subtotal reqs | subtotal qty)
            $sheet->setCellValue('A'.$row, $month_names[$mn]);
            $sheet->setCellValue('B'.$row, $cat);
            $sheet->setCellValue('C'.$row, $dept);
            $sheet->setCellValue('D'.$row, count($items) . ' item type(s)');
            $sheet->setCellValue('E'.$row, '');
            $sheet->setCellValue('F'.$row, $deptReqs);
            $sheet->setCellValue('G'.$row, number_format($deptTotal));

            $sheet->getStyle('A'.$row.':G'.$row)->applyFromArray([
                'font'    => ['bold'=>true,'size'=>9],
                'fill'    => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>$LBLUE]],
                'borders' => ['top'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>'B0C8F0']]],
            ]);
            $sheet->getStyle('D'.$row)->getFont()->setBold(false)->setItalic(true)->setSize(8);
            $sheet->getStyle('D'.$row)->getFont()->getColor()->setRGB('666666');
            $sheet->getStyle('F'.$row.':G'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('G'.$row)->getFont()->getColor()->setRGB($BLUE);
            $row++;

            // Item rows
            foreach ($items as $item) {
                $sheet->setCellValue('A'.$row, '');
                $sheet->setCellValue('B'.$row, '');
                $sheet->setCellValue('C'.$row, '');
                $sheet->setCellValue('D'.$row, '    ↳  ' . $item['item_name']);
                $sheet->setCellValue('E'.$row, $item['unit'] ?? '-');
                $sheet->setCellValue('F'.$row, $item['request_count']);
                $sheet->setCellValue('G'.$row, number_format($item['total_quantity']));

                $sheet->getStyle('A'.$row.':G'.$row)->applyFromArray([
                    'fill'    => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>$IROW]],
                    'borders' => ['bottom'=>['borderStyle'=>Border::BORDER_HAIR,'color'=>['rgb'=>'DDDDDD']]],
                ]);
                $sheet->getStyle('D'.$row)->getFont()->setItalic(true)->setSize(9);
                $sheet->getStyle('E'.$row.':G'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $row++;
            }
        }
    }
}

// Grand total
$sheet->mergeCells('A'.$row.':F'.$row);
$sheet->setCellValue('A'.$row, 'GRAND TOTAL');
$sheet->setCellValue('G'.$row, number_format($grand_total));
$sheet->getStyle('A'.$row.':G'.$row)->applyFromArray([
    'font'      => ['bold'=>true,'color'=>['rgb'=>$WHITE],'size'=>11],
    'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>$BLUE]],
    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER],
    'borders'   => ['top'=>['borderStyle'=>Border::BORDER_MEDIUM]],
]);

// Column widths
$sheet->getColumnDimension('A')->setWidth(14);
$sheet->getColumnDimension('B')->setWidth(24);
$sheet->getColumnDimension('C')->setWidth(24);
$sheet->getColumnDimension('D')->setWidth(38);
$sheet->getColumnDimension('E')->setWidth(10);
$sheet->getColumnDimension('F')->setWidth(12);
$sheet->getColumnDimension('G')->setWidth(14);
$sheet->freezePane('A7');

// Output
$filename = "Monthly_Consumption_{$year}";
if ($month      !== 'all') $filename .= "_" . ($month_names[(int)$month] ?? $month);
if ($category   !== 'all') $filename .= "_{$category}";
if ($department !== 'all') $filename .= "_{$department}";
$filename .= "_" . date('Ymd') . ".xlsx";

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');
(new Xlsx($spreadsheet))->save('php://output');
exit;
