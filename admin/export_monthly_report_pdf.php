<?php
session_start();
date_default_timezone_set('Asia/Manila');
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    die("Unauthorized access");
}

require_once '../vendor/autoload.php';
require_once '../config/database.php';

use Dompdf\Dompdf;
use Dompdf\Options;

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
$sq = "SELECT SUM(ri.quantity) AS total_consumption, COUNT(DISTINCT rg.id) AS total_requests,
              COUNT(DISTINCT c.category) AS total_categories, COUNT(DISTINCT rg.office) AS total_departments
       FROM request_items ri JOIN request_groups rg ON ri.group_id=rg.id JOIN consumables c ON ri.consumable_id=c.id
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
    $grouped[$mn][$r['category']][$r['department']][] = $r;
    $grand_total += (int)$r['total_quantity'];
}

// ── Build filter display string ──
$filterParts = ["Year: {$year}"];
if ($month      !== 'all') $filterParts[] = "Month: " . ($month_names[(int)$month] ?? $month);
if ($category   !== 'all') $filterParts[] = "Category: " . htmlspecialchars($category);
if ($department !== 'all') $filterParts[] = "Department: " . htmlspecialchars($department);
$filterStr = implode('  |  ', $filterParts);

// ── HTML ──
$html = '<!DOCTYPE html><html><head><meta charset="utf-8">
<style>
  @page { margin: 0.45in 0.4in; size: A4 landscape; }
  body  { font-family: Helvetica, Arial, sans-serif; font-size: 9px; color: #333; line-height: 1.4; }

  .header { text-align: center; border-bottom: 3px solid #0d6efd; padding-bottom: 8px; margin-bottom: 10px; }
  .header h1 { font-size: 18px; color: #0d6efd; margin: 0 0 3px; }
  .header h3 { font-size: 11px; color: #555; margin: 0 0 3px; font-weight: normal; }
  .header p  { font-size: 8px;  color: #999; margin: 0; }

  .filter-bar { background:#EBF3FF; border:1px solid #C5D9F0; border-radius:4px;
                padding:4px 10px; margin-bottom:10px; font-size:8px; color:#333; }

  .summary-bar { display:table; width:100%; border-collapse:collapse;
                 background:#0d6efd; color:#fff; margin-bottom:12px;
                 border-radius:4px; overflow:hidden; }
  .summary-cell { display:table-cell; text-align:center; padding:6px 8px;
                  border-right:1px solid rgba(255,255,255,0.2); }
  .summary-cell:last-child { border-right:none; }
  .summary-cell .lbl { font-size:7px; opacity:.85; }
  .summary-cell .val { font-size:14px; font-weight:bold; }

  table { width:100%; border-collapse:collapse; margin-bottom:6px; font-size:8px; }

  /* Group header row */
  tr.group-hdr td { background:#dde9ff; font-weight:bold; padding:5px 6px;
                    border-top:2px solid #0d6efd; border-bottom:1px solid #b0c8f0; }
  tr.group-hdr td.month-cell { color:#0d6efd; font-size:9px; }

  /* Item row */
  tr.item-row td { background:#f5f9ff; padding:3px 6px;
                   border-bottom:1px solid #e8eef8; }
  tr.item-row td.item-name { padding-left:20px; color:#333; font-style:italic; }
  tr.item-row td.qty-cell  { text-align:center; font-weight:bold; color:#0d6efd; }
  tr.item-row td.unit-cell { text-align:center; color:#666; }
  tr.item-row td.req-cell  { text-align:center; }

  /* Total row */
  tr.total-row td { background:#0d6efd; color:#fff; font-weight:bold;
                    padding:6px; text-align:right; font-size:9px; }

  .col-month { width:11%; }
  .col-cat   { width:16%; }
  .col-dept  { width:16%; }
  .col-item  { width:33%; }
  .col-unit  { width:7%;  text-align:center; }
  .col-req   { width:8%;  text-align:center; }
  .col-qty   { width:9%;  text-align:right; }

  .footer { margin-top:14px; text-align:right; font-size:7px; color:#aaa;
            border-top:1px solid #ddd; padding-top:6px; }
</style>
</head><body>

<div class="header">
  <h1>MONTHLY CONSUMPTION REPORT</h1>
  <h3>University of Caloocan City &mdash; Consumable Management System</h3>
  <p>Generated: ' . date('F d, Y  h:i A') . '&nbsp;&nbsp;&nbsp;|&nbsp;&nbsp;&nbsp;By: ' . htmlspecialchars($_SESSION['full_name'] ?? 'Admin') . '</p>
</div>

<div class="filter-bar"><strong>Filters:</strong> ' . $filterStr . '</div>

<div class="summary-bar">
  <div class="summary-cell"><div class="lbl">Total Consumed</div><div class="val">' . number_format($summary['total_consumption'] ?? 0) . '</div><div class="lbl">items released</div></div>
  <div class="summary-cell"><div class="lbl">Approved Requests</div><div class="val">' . number_format($summary['total_requests'] ?? 0) . '</div><div class="lbl">requests</div></div>
  <div class="summary-cell"><div class="lbl">Categories</div><div class="val">' . ($summary['total_categories'] ?? 0) . '</div><div class="lbl">with consumption</div></div>
  <div class="summary-cell"><div class="lbl">Departments</div><div class="val">' . ($summary['total_departments'] ?? 0) . '</div><div class="lbl">served</div></div>
</div>';

if (empty($grouped)) {
    $html .= '<p style="text-align:center;color:#999;padding:30px;">No consumption data found for the selected filters.</p>';
} else {
    $html .= '
<table>
  <thead>
    <tr style="background:#0d6efd;color:#fff;">
      <th class="col-month" style="padding:6px;text-align:left;">Month</th>
      <th class="col-cat"   style="padding:6px;text-align:left;">Category</th>
      <th class="col-dept"  style="padding:6px;text-align:left;">Department</th>
      <th class="col-item"  style="padding:6px;text-align:left;">Requested Item</th>
      <th class="col-unit"  style="padding:6px;">Unit</th>
      <th class="col-req"   style="padding:6px;">Requests</th>
      <th class="col-qty"   style="padding:6px;text-align:right;">Total Qty</th>
    </tr>
  </thead>
  <tbody>';

    foreach ($grouped as $mn => $cats) {
        foreach ($cats as $cat => $depts) {
            foreach ($depts as $dept => $items) {
                $deptTotal = array_sum(array_column($items, 'total_quantity'));
                $deptReqs  = array_sum(array_column($items, 'request_count'));

                $html .= '
    <tr class="group-hdr">
      <td class="col-month month-cell">' . htmlspecialchars($month_names[$mn]) . '</td>
      <td class="col-cat">' . htmlspecialchars($cat) . '</td>
      <td class="col-dept">' . htmlspecialchars($dept) . '</td>
      <td class="col-item" style="color:#555;font-weight:normal;font-style:italic;font-size:8px;">' . count($items) . ' item type(s)</td>
      <td class="col-unit" style="text-align:center;"></td>
      <td class="col-req"  style="text-align:center;">' . $deptReqs . '</td>
      <td class="col-qty"  style="text-align:right;color:#0d6efd;">' . number_format($deptTotal) . '</td>
    </tr>';

                foreach ($items as $item) {
                    $html .= '
    <tr class="item-row">
      <td class="col-month"></td>
      <td class="col-cat"></td>
      <td class="col-dept"></td>
      <td class="col-item item-name">&#8627; ' . htmlspecialchars($item['item_name']) . '</td>
      <td class="col-unit unit-cell">' . htmlspecialchars($item['unit'] ?? '-') . '</td>
      <td class="col-req req-cell">' . $item['request_count'] . '</td>
      <td class="col-qty qty-cell">' . number_format($item['total_quantity']) . '</td>
    </tr>';
                }
            }
        }
    }

    $html .= '
    <tr class="total-row">
      <td colspan="6">GRAND TOTAL</td>
      <td>' . number_format($grand_total) . '</td>
    </tr>
  </tbody>
</table>';
}

$html .= '
<div class="footer">
  <p>This report is generated automatically by UCC Consumable Management System</p>
</div>
</body></html>';

// ── Render PDF ──
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'Helvetica');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

$filename = "Monthly_Consumption_{$year}";
if ($month      !== 'all') $filename .= "_" . ($month_names[(int)$month] ?? $month);
if ($category   !== 'all') $filename .= "_{$category}";
if ($department !== 'all') $filename .= "_{$department}";
$filename .= "_" . date('Ymd') . ".pdf";

$dompdf->stream($filename, ["Attachment" => false]);
exit;
