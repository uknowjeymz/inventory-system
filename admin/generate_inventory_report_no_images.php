<?php
// TEMPORARY VERSION WITHOUT IMAGES - Works without GD extension
// Use this until you enable the GD extension in php.ini

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

// Get Filter Parameters
$mode = $_POST['report_mode'] ?? 'campus';
$status_filter = $_POST['filter_status'] ?? 'ALL';
$campus = $_POST['filter_campus'] ?? 'ALL';
$remarks = $_POST['filter_remarks'] ?? 'ALL';

$tables = [
    'computer_inventory' => ['item_number', 'computer_set_description as name', 'serial_number', 'campus', 'status', 'remarks', 'purchase_date'],
    'kitchen_equipment'  => ['item_number', 'equipment_name as name', 'serial_number', 'campus', 'status', 'remarks', 'purchase_date'],
    'office_equipment'   => ['item_number', 'equipment_name as name', 'serial_number', 'campus', 'status', 'remarks', 'purchase_date'],
    'lab_equipment'      => ['item_number', 'equipment_name as name', 'serial_number', 'campus', 'status', 'remarks', 'purchase_date'],
    'general_equipment'  => ['item_number', 'article as name', 'property_no as serial_number', 'campus', 'status', 'remarks', 'purchase_date']
];

$combined_results = [];
$reportScopeText = ($mode == 'campus') ? "Campus: $campus" : "Accountable: $remarks";

foreach ($tables as $table => $cols) {
    try {
        // Check if purchase_date column exists in this table
        $check_column = $db->prepare("SHOW COLUMNS FROM `{$table}` LIKE 'purchase_date'");
        $check_column->execute();
        $has_purchase_date = $check_column->rowCount() > 0;
        
        // If purchase_date doesn't exist, remove it from the columns list
        if (!$has_purchase_date) {
            $cols = array_filter($cols, function($col) {
                return $col !== 'purchase_date';
            });
            // Add NULL as purchase_date for consistency
            $cols[] = 'NULL as purchase_date';
        }
        
        $conditions = [];
        $params = [];

        // Filter by Campus OR Remarks based on mode
        if ($mode == 'campus' && $campus !== 'ALL') {
            $conditions[] = "campus = :campus";
            $params[':campus'] = $campus;
        } elseif ($mode == 'accountable' && $remarks !== 'ALL') {
            $conditions[] = "remarks = :remarks";
            $params[':remarks'] = $remarks;
        }

        if ($status_filter !== 'ALL') {
            $conditions[] = "status = :status";
            $params[':status'] = $status_filter;
        }

        $query = "SELECT " . implode(', ', $cols) . ", '$table' as category FROM $table";
        if (!empty($conditions)) {
            $query .= " WHERE " . implode(" AND ", $conditions);
        }
        $query .= " ORDER BY item_number ASC";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $combined_results = array_merge($combined_results, $stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Exception $e) {
        // Skip tables that don't exist or have errors
        continue;
    }
}

// Generate the HTML for Dompdf - NO IMAGES VERSION
$html = '
<html>
<head>
    <style>
        @page { margin: 80px 25px; }
        body { font-family: "Helvetica", sans-serif; font-size: 10px; color: #333; line-height: 1.4; }
        
        .header { position: fixed; top: -65px; left: 0px; right: 0px; height: 80px; text-align: center; border-bottom: 2px solid #333; }
        .header h1 { margin: 5px 0; font-size: 18px; color: #000; letter-spacing: 1px; }
        .header p { margin: 2px 0; font-size: 11px; }
        .report-title { margin-top: 5px; font-weight: bold; text-decoration: underline; font-size: 12px; text-transform: uppercase; }

        .footer { position: fixed; bottom: -50px; left: 0px; right: 0px; height: 50px; border-top: 1px solid #ccc; padding-top: 10px; font-size: 9px; }
        .footer table { width: 100%; }

        .summary-box { margin-top: 10px; margin-bottom: 15px; padding: 8px; background: #fcfcfc; border: 1px solid #eee; }
        .inventory-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .inventory-table th { background-color: #f5f5f5; color: #333; padding: 7px; text-transform: uppercase; border: 1px solid #ddd; font-size: 9px; }
        .inventory-table td { padding: 6px; border: 1px solid #ddd; vertical-align: top; }
        
        .status-available { color: #2e7d32; }
        .status-maintenance { color: #f57c00; }
        .status-condemned { color: #d32f2f; }
        
        .signature-section { margin-top: 50px; width: 100%; }
        .sig-box { border-top: 1.5px solid #000; text-align: center; width: 220px; margin-top: 40px; }
        .sig-container { display: inline-block; width: 45%; vertical-align: top; }
    </style>
</head>
<body>
    <div class="header">
        <h1>UNIVERSITY OF CALOOCAN CITY</h1>
        <p>INVENTORY MANAGEMENT SYSTEM</p>
        <p class="report-title">Asset Inventory Master List</p>
    </div>

    <div class="footer">
        <table>
            <tr>
                <td width="50%">Generated by: <strong>' . htmlspecialchars($_SESSION['full_name'] ?? 'Admin') . '</strong></td>
                <td width="50%" align="right">Date: ' . date("F d, Y") . '</td>
            </tr>
        </table>
    </div>

    <div class="summary-box">
        <table width="100%">
            <tr>
                <td width="50%"><strong>Filter Scope:</strong> ' . htmlspecialchars($reportScopeText) . '</td>
                <td width="50%" align="right"><strong>Status Filter:</strong> ' . htmlspecialchars($status_filter) . '</td>
            </tr>
            <tr>
                <td colspan="2"><strong>Total Records Found:</strong> ' . count($combined_results) . '</td>
            </tr>
        </table>
    </div>

    <table class="inventory-table">
        <thead>
            <tr>
                <th width="8%">Item #</th>
                <th width="30%">Equipment Name / Description</th>
                <th width="12%">Serial/Property #</th>
                <th width="10%">Year Acquired</th>
                <th width="12%">Category</th>
                <th width="18%">Accountable</th>
                <th width="10%">Status</th>
            </tr>
        </thead>
        <tbody>';

        if (empty($combined_results)) {
            $html .= '<tr><td colspan="7" align="center" style="padding: 20px;">No equipment records found matching the selected criteria.</td></tr>';
        } else {
            foreach ($combined_results as $row) {
                $categoryName = ucfirst(str_replace(['_inventory', '_equipment'], '', $row['category']));
                $statusColorClass = 'status-' . strtolower($row['status']);
                
                // Extract year from purchase_date
                $yearAcquired = 'N/A';
                if (!empty($row['purchase_date'])) {
                    $yearAcquired = date('Y', strtotime($row['purchase_date']));
                }
                
                $html .= '<tr>
                    <td align="center"><strong>' . htmlspecialchars($row['item_number']) . '</strong></td>
                    <td>' . htmlspecialchars($row['name']) . '</td>
                    <td>' . htmlspecialchars($row['serial_number'] ?: 'N/A') . '</td>
                    <td align="center">' . htmlspecialchars($yearAcquired) . '</td>
                    <td align="center">' . $categoryName . '</td>
                    <td>' . htmlspecialchars($row['remarks'] ?: 'Unassigned') . '</td>
                    <td align="center" class="' . $statusColorClass . '" style="font-weight: bold;">' . strtoupper($row['status']) . '</td>
                </tr>';
            }
        }

$html .= '
        </tbody>
    </table>

    <div class="signature-section">
        <div class="sig-container">
            <div style="height: 40px;"></div>
            <div class="sig-box">
                <strong>' . htmlspecialchars($_SESSION['full_name'] ?? 'Authorized Personnel') . '</strong><br>
                <small>Prepared By / Inventory Custodian</small>
            </div>
        </div>
        <div class="sig-container" style="float: right;">
            <div style="height: 40px;"></div>
            <div class="sig-box">
                <br>
                <small>Department Head / OIC Signature</small>
            </div>
        </div>
    </div>

</body>
</html>';

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', false); // Disable remote to avoid image issues
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$dompdf->stream("Inventory_Master_List_" . date('Ymd') . ".pdf", ["Attachment" => false]);
