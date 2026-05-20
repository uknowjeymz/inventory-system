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
          GROUP BY category ORDER BY category";
$stmt = $db->prepare($query);
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Items per category ──
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

// ── Consumption per category+department+item ──
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

// Totals
$grand_total_items = 0;
$grand_total_stock = 0;
foreach ($categories as $cat) {
    $grand_total_items += $cat['total_items'];
    $grand_total_stock += $cat['total_stock'];
}

// ── HTML ──
$html = '<!DOCTYPE html><html><head><meta charset="utf-8">
<style>
  @page { margin: 0.45in 0.4in; size: A4 portrait; }
  body  { font-family: Helvetica, Arial, sans-serif; font-size: 9px; color: #333; line-height: 1.4; }

  .header { text-align: center; border-bottom: 3px solid #F59E0B; padding-bottom: 8px; margin-bottom: 12px; }
  .header h1 { font-size: 18px; color: #B45309; margin: 0 0 3px; }
  .header h3 { font-size: 11px; color: #555; margin: 0 0 3px; font-weight: normal; }
  .header p  { font-size: 8px;  color: #999; margin: 0; }

  /* ── Section titles ── */
  .section-title { font-size: 12px; font-weight: bold; color: #B45309;
                   border-bottom: 2px solid #F59E0B; padding-bottom: 4px;
                   margin: 18px 0 8px; text-transform: uppercase; }

  /* ── Summary table ── */
  table.summary-tbl { width:100%; border-collapse:collapse; margin-bottom:16px; font-size:8px; }
  table.summary-tbl th { background:#F59E0B; color:#fff; padding:5px 6px; text-align:center; text-transform:uppercase; }
  table.summary-tbl td { padding:4px 6px; border-bottom:1px solid #eee; }
  table.summary-tbl tr:nth-child(even) td { background:#FFFDF0; }
  table.summary-tbl tr.total-row td { background:#F0F0F0; font-weight:bold; }

  /* ── Inventory items ── */
  .cat-block { margin-bottom: 14px; }
  .cat-title { background:#FFF3CD; border-left:4px solid #F59E0B; font-weight:bold;
               font-size:10px; color:#92400E; padding:4px 8px; margin-bottom:0; text-transform:uppercase; }
  table.inv-tbl { width:100%; border-collapse:collapse; font-size:8px; margin-bottom:4px; }
  table.inv-tbl th { background:#F8F9FA; font-weight:bold; padding:4px 5px;
                     border-bottom:1px solid #ddd; text-align:center; }
  table.inv-tbl th:first-child { text-align:left; }
  table.inv-tbl td { padding:3px 5px; border-bottom:1px solid #f0f0f0; }
  .badge { display:inline-block; padding:1px 5px; border-radius:3px; font-size:7px; font-weight:bold; }
  .badge-ok  { background:#d4edda; color:#155724; }
  .badge-low { background:#fff3cd; color:#856404; }
  .badge-crit{ background:#f8d7da; color:#721c24; }

  /* ── Consumption by dept ── */
  .cons-section { margin-top: 20px; }
  table.cons-tbl { width:100%; border-collapse:collapse; font-size:8px; margin-bottom:6px; }
  tr.cons-group td { background:#DDE9FF; font-weight:bold; padding:5px 6px;
                     border-top:2px solid #0d6efd; border-bottom:1px solid #B0C8F0; }
  tr.cons-group td.cat-cell  { color:#0d6efd; font-size:9px; }
  tr.cons-item  td { background:#F5F9FF; padding:3px 6px; border-bottom:1px solid #E8EEF8; }
  tr.cons-item  td.item-cell { padding-left:18px; font-style:italic; }
  tr.cons-item  td.num-cell  { text-align:center; }
  tr.cons-item  td.qty-cell  { text-align:center; font-weight:bold; color:#0d6efd; }
  tr.cons-total td { background:#0d6efd; color:#fff; font-weight:bold;
                     padding:5px 6px; text-align:right; }

  .footer { margin-top:14px; text-align:right; font-size:7px; color:#aaa;
            border-top:1px solid #ddd; padding-top:6px; }

  .text-center { text-align:center; }
  .text-right  { text-align:right; }
  .color-ok    { color:#198754; }
  .color-low   { color:#B45309; }
  .color-crit  { color:#DC3545; }
</style>
</head><body>

<div class="header">
  <h1>CATEGORY INVENTORY REPORT</h1>
  <h3>University of Caloocan City &mdash; Consumable Management System</h3>
  <p>Generated: ' . date('F d, Y  h:i A') . '&nbsp;&nbsp;|&nbsp;&nbsp;By: ' . htmlspecialchars($_SESSION['full_name'] ?? 'Admin') . '</p>
</div>

<!-- ═══ CATEGORY SUMMARY TABLE ═══ -->
<div class="section-title">Category Summary</div>
<table class="summary-tbl">
  <thead>
    <tr>
      <th style="text-align:left;">Category</th>
      <th>Total Items</th>
      <th>Total Stock</th>
      <th>Avg Stock</th>
      <th>In Stock</th>
      <th>Low Stock</th>
      <th>Critical Stock</th>
      <th>Brands</th>
      <th>Health %</th>
    </tr>
  </thead>
  <tbody>';

foreach ($categories as $cat) {
    $hp = ($cat['total_items'] > 0) ? round(($cat['in_stock'] / $cat['total_items']) * 100, 1) : 0;
    $hpClass = $hp >= 70 ? 'color-ok' : ($hp >= 40 ? 'color-low' : 'color-crit');
    $html .= '
    <tr>
      <td><strong>' . htmlspecialchars($cat['category']) . '</strong></td>
      <td class="text-center">' . $cat['total_items'] . '</td>
      <td class="text-center">' . number_format($cat['total_stock']) . '</td>
      <td class="text-center">' . round($cat['avg_stock'], 1) . '</td>
      <td class="text-center color-ok">'   . $cat['in_stock']       . '</td>
      <td class="text-center color-low">'  . $cat['low_stock']      . '</td>
      <td class="text-center color-crit">' . $cat['critical_stock'] . '</td>
      <td class="text-center">' . $cat['total_brands'] . '</td>
      <td class="text-center ' . $hpClass . '"><strong>' . $hp . '%</strong></td>
    </tr>';
}

$html .= '
    <tr class="total-row">
      <td><strong>TOTAL</strong></td>
      <td class="text-center"><strong>' . $grand_total_items . '</strong></td>
      <td class="text-center"><strong>' . number_format($grand_total_stock) . '</strong></td>
      <td colspan="6"></td>
    </tr>
  </tbody>
</table>

<!-- ═══ ITEMS BY CATEGORY ═══ -->
<div class="section-title">Items by Category &mdash; Current Inventory</div>';

foreach ($items_by_category as $catName => $catItems) {
    $html .= '
<div class="cat-block">
  <div class="cat-title">' . strtoupper(htmlspecialchars($catName)) . ' &nbsp;(' . count($catItems) . ' items)</div>
  <table class="inv-tbl">
    <thead>
      <tr>
        <th>Item Name</th>
        <th>Brand</th>
        <th>Qty</th>
        <th>Unit</th>
        <th>Status</th>
        <th>ID Code</th>
      </tr>
    </thead>
    <tbody>';

    foreach ($catItems as $item) {
        $badgeClass = match(true) {
            $item['quantity'] <= 10 => 'badge-crit',
            $item['quantity'] <= 20 => 'badge-low',
            default                 => 'badge-ok',
        };
        $badgeLabel = match(true) {
            $item['quantity'] <= 10 => 'Critical',
            $item['quantity'] <= 20 => 'Low',
            default                 => 'Good',
        };
        $html .= '
      <tr>
        <td>' . htmlspecialchars($item['item_name']) . '</td>
        <td>' . htmlspecialchars($item['brand'] ?: 'Generic') . '</td>
        <td class="text-center">' . $item['quantity'] . '</td>
        <td class="text-center">' . htmlspecialchars($item['unit'] ?: '-') . '</td>
        <td class="text-center"><span class="badge ' . $badgeClass . '">' . $badgeLabel . '</span></td>
        <td><small>' . htmlspecialchars($item['identification'] ?? '') . '</small></td>
      </tr>';
    }

    $html .= '
    </tbody>
  </table>
</div>';
}

// ── Consumption by Category & Department ──
$html .= '
<div class="section-title cons-section">Item Consumption by Category &amp; Department</div>
<table class="cons-tbl">
  <thead>
    <tr style="background:#0d6efd;color:#fff;">
      <th style="padding:6px;text-align:left;width:18%;">Category</th>
      <th style="padding:6px;text-align:left;width:18%;">Department</th>
      <th style="padding:6px;text-align:left;width:36%;">Requested Item</th>
      <th style="padding:6px;text-align:center;width:7%;">Unit</th>
      <th style="padding:6px;text-align:center;width:8%;">Requests</th>
      <th style="padding:6px;text-align:center;width:8%;">Consumed</th>
      <th style="padding:6px;text-align:center;width:5%;">Last Req.</th>
    </tr>
  </thead>
  <tbody>';

$cons_grand = 0;
foreach ($consumption as $catName => $depts) {
    foreach ($depts as $deptName => $items) {
        $deptTotal = array_sum(array_column($items, 'total_consumed'));
        $deptReqs  = array_sum(array_column($items, 'request_count'));
        $cons_grand += $deptTotal;

        $html .= '
    <tr class="cons-group">
      <td class="cat-cell">'  . htmlspecialchars($catName)  . '</td>
      <td>' . htmlspecialchars($deptName) . '</td>
      <td style="font-weight:normal;font-style:italic;font-size:8px;color:#555;">' . count($items) . ' item type(s)</td>
      <td></td>
      <td class="text-center">' . $deptReqs . '</td>
      <td class="text-center" style="color:#0d6efd;">' . number_format($deptTotal) . '</td>
      <td></td>
    </tr>';

        foreach ($items as $item) {
            $html .= '
    <tr class="cons-item">
      <td></td>
      <td></td>
      <td class="item-cell">&#8627; ' . htmlspecialchars($item['item_name']) . '</td>
      <td class="num-cell">' . htmlspecialchars($item['unit'] ?? '-') . '</td>
      <td class="num-cell">' . $item['request_count'] . '</td>
      <td class="qty-cell">' . number_format($item['total_consumed']) . '</td>
      <td class="num-cell" style="font-size:7px;">' . ($item['last_requested'] ? date('M d, Y', strtotime($item['last_requested'])) : '-') . '</td>
    </tr>';
        }
    }
}

$html .= '
    <tr class="cons-total">
      <td colspan="5">GRAND TOTAL CONSUMED (ALL TIME)</td>
      <td>' . number_format($cons_grand) . '</td>
      <td></td>
    </tr>
  </tbody>
</table>

<div class="footer">
  <p>This report is generated automatically by UCC Consumable Management System</p>
</div>
</body></html>';

// ── Render ──
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'Helvetica');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$dompdf->stream("Category_Report_" . date('Ymd') . ".pdf", ["Attachment" => false]);
exit;
