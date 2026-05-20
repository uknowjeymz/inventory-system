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

// Function to handle local images in Dompdf
function getBase64Image($path) {
    if (file_exists($path)) {
        $type = pathinfo($path, PATHINFO_EXTENSION);
        $data = file_get_contents($path);
        return 'data:image/' . $type . ';base64,' . base64_encode($data);
    }
    return '';
}

$uccLogo = getBase64Image('../assets/UCC_Logo.png');
$caloocanLogo = getBase64Image('../assets/caloocan.png');

// Get Filter Parameters
$mode = $_POST['report_mode'] ?? 'campus';
$status_filter = $_POST['filter_status'] ?? 'ALL';
$campus = $_POST['filter_campus'] ?? 'ALL';
$remarks = $_POST['filter_remarks'] ?? 'ALL';

$tables = [
    'computer_inventory' => [
        'item_number', 
        'computer_set_description as name', 
        'serial_number', 
        'serial_number_monitor', 
        'serial_number_system', 
        'property_no', 
        'campus', 
        'status', 
        'remarks', 
        'purchase_date',
        'article'
    ],
    'kitchen_equipment'  => [
        'item_number', 
        'equipment_name as name', 
        'serial_number', 
        'NULL as serial_number_monitor', 
        'NULL as serial_number_system', 
        'property_no', 
        'campus', 
        'status', 
        'remarks', 
        'purchase_date',
        'NULL as article'
    ],
    'office_equipment'   => [
        'item_number', 
        'equipment_name as name', 
        'serial_number', 
        'NULL as serial_number_monitor', 
        'NULL as serial_number_system', 
        'property_no', 
        'campus', 
        'status', 
        'remarks', 
        'purchase_date',
        'NULL as article'
    ],
    'lab_equipment'      => [
        'item_number', 
        'equipment_name as name', 
        'serial_number', 
        'NULL as serial_number_monitor', 
        'NULL as serial_number_system', 
        'property_no', 
        'campus', 
        'status', 
        'remarks', 
        'purchase_date',
        'NULL as article'
    ],
    'general_equipment'  => [
        'item_number', 
        'article as name', 
        'serial_number', 
        'NULL as serial_number_monitor', 
        'NULL as serial_number_system', 
        'property_no', 
        'campus', 
        'status', 
        'remarks', 
        'purchase_date',
        'article'
    ]
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

// Generate the HTML for Dompdf using the official Room Report Style
$html = '
<html>
<head>
    <style>
        @page { margin: 100px 25px; }
        body { font-family: "Helvetica", sans-serif; font-size: 9px; color: #333; line-height: 1.3; }
        
        /* Official Header Style copied from room report */
        .header { position: fixed; top: -85px; left: 0px; right: 0px; height: 100px; }
        .header table { width: auto; margin: 0 auto; border-collapse: collapse; }
        .header .logo-ucc { padding-right: 15px; }
        .header .header-text { text-align: center; }
        .header h1 { margin: 0; font-size: 18px; color: #000; letter-spacing: 1px; }
        .report-title { margin-top: 5px; font-weight: bold; text-decoration: underline; font-size: 12px; text-transform: uppercase; }

        /* Footer Style copied from room report */
        .footer { position: fixed; bottom: -60px; left: 0px; right: 0px; height: 60px; border-top: 1px solid #ccc; padding-top: 10px; font-size: 9px; }
        .footer table { width: 100%; border-collapse: collapse; }
        .logo-caloocan img { width: 130px; }

        /* Inventory Table Styles */
        .summary-box { margin-top: 10px; margin-bottom: 15px; padding: 8px; background: #fcfcfc; border: 1px solid #eee; }
        .inventory-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .inventory-table th { background-color: #f5f5f5; color: #333; padding: 7px; text-transform: uppercase; border: 1px solid #ddd; font-size: 8px; }
        .inventory-table td { padding: 5px; border: 1px solid #ddd; vertical-align: middle; }
        
        .badge { padding: 2px 5px; border-radius: 3px; font-size: 7px; font-weight: bold; text-transform: uppercase; display: inline-block; }
        .status-available { color: #2e7d32; }
        .status-maintenance { color: #f57c00; }
        .status-condemned { color: #d32f2f; }
        
        .serial-info { font-size: 8px; line-height: 1.2; }
        .serial-mon { color: #666; }
        .serial-sys { color: #666; }
        .property-no { font-weight: bold; font-family: monospace; }
        .na-text { color: #999; font-style: italic; }
        
        .signature-section { margin-top: 50px; width: 100%; }
        .sig-box { border-top: 1.5px solid #000; text-align: center; width: 220px; margin-top: 40px; }
        .sig-container { display: inline-block; width: 45%; vertical-align: top; }
    </style>
</head>
<body>
    <div class="header">
        <table>
            <tr>
                <td class="logo-ucc">' . ($uccLogo ? '<img src="' . $uccLogo . '" style="width: 55px;">' : '') . '</td>
                <td class="header-text">
                    <h1>UNIVERSITY OF CALOOCAN CITY</h1>
                    <p style="margin:0;">INVENTORY MANAGEMENT SYSTEM</p>
                    <p class="report-title">Asset Inventory Master List</p>
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">
        <table width="100%">
            <tr>
                <td width="33%">Generated by: <strong>' . htmlspecialchars($_SESSION['full_name'] ?? 'Admin') . '</strong></td>
                <td width="34%" align="center">Date: ' . date("F d, Y") . '</td>
                <td width="33%" align="right">
                    <div class="logo-caloocan">' . ($caloocanLogo ? '<img src="' . $caloocanLogo . '">' : '') . '</div>
                </td>
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
                <th width="7%">Item #</th>
                <th width="25%">Equipment Name / Description</th>
                <th width="12%">Property #</th>
                <th width="15%">Serial Information</th>
                <th width="8%">Year</th>
                <th width="10%">Category</th>
                <th width="15%">Accountable</th>
                <th width="8%">Status</th>
            </tr>
        </thead>
        <tbody>';

        if (empty($combined_results)) {
            $html .= '<tr><td colspan="8" align="center" style="padding: 20px;">No equipment records found matching the selected criteria.</td></tr>';
        } else {
            foreach ($combined_results as $row) {
                $categoryName = ucfirst(str_replace(['_inventory', '_equipment'], '', $row['category']));
                $statusColorClass = 'status-' . strtolower($row['status']);
                
                // Extract year from purchase_date
                $yearAcquired = 'N/A';
                if (!empty($row['purchase_date'])) {
                    $yearAcquired = date('Y', strtotime($row['purchase_date']));
                }
                
                // Property Number
                $propertyNo = !empty($row['property_no']) ? htmlspecialchars($row['property_no']) : '<span class="na-text">N/A</span>';
                
                // Serial Information - Handle different cases
                $serialInfo = '';
                $isComputerPackage = ($row['category'] == 'computer_inventory' && isset($row['article']) && $row['article'] == 'Computer Package');
                
                if ($isComputerPackage) {
                    // Computer Package - Show both monitor and system serials
                    $monitorSerial = !empty($row['serial_number_monitor']) ? htmlspecialchars($row['serial_number_monitor']) : 'N/A';
                    $systemSerial = !empty($row['serial_number_system']) ? htmlspecialchars($row['serial_number_system']) : 'N/A';
                    
                    $serialInfo = '<div class="serial-info">';
                    $serialInfo .= '<span class="serial-mon">MON: ' . $monitorSerial . '</span><br>';
                    $serialInfo .= '<span class="serial-sys">SYS: ' . $systemSerial . '</span>';
                    $serialInfo .= '</div>';
                } else {
                    // Regular equipment - Show single serial number
                    $serialNumber = !empty($row['serial_number']) ? htmlspecialchars($row['serial_number']) : 'N/A';
                    $serialInfo = '<span class="serial-info">' . $serialNumber . '</span>';
                }
                
                $html .= '<tr>
                    <td align="center"><strong>' . htmlspecialchars($row['item_number']) . '</strong></td>
                    <td>' . htmlspecialchars($row['name']) . '</td>
                    <td align="center" class="property-no">' . $propertyNo . '</td>
                    <td>' . $serialInfo . '</td>
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
$options->set('isRemoteEnabled', true); 
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Opens directly in the browser tab
$dompdf->stream("Inventory_Master_List_" . date('Ymd') . ".pdf", ["Attachment" => false]);
?>