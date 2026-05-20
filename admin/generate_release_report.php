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

// --- Get group_id parameter ---
$group_id = $_GET['group_id'] ?? null;

if (!$group_id) {
    // If no specific group, show all approved groups summary
    $query = "SELECT rg.*, 
              COUNT(ri.id) as total_items,
              SUM(ri.quantity) as total_quantity
              FROM request_groups rg
              LEFT JOIN request_items ri ON rg.id = ri.group_id
              WHERE rg.status = 'Approved'
              GROUP BY rg.id
              ORDER BY rg.request_date DESC";
    $stmt = $db->query($query);
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all items for summary
    $items_query = "SELECT ri.*, c.item_name, c.unit, rg.group_code, rg.employee, rg.office, rg.request_date
                    FROM request_items ri
                    JOIN consumables c ON ri.consumable_id = c.id
                    JOIN request_groups rg ON ri.group_id = rg.id
                    WHERE ri.status = 'Approved'
                    ORDER BY rg.request_date DESC, ri.id ASC";
    $items_stmt = $db->query($items_query);
    $all_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $report_type = 'summary';
    $items = []; // Initialize items as empty array for summary mode
} else {
    // Individual Group Report
    $query = "SELECT rg.*, 
              COUNT(ri.id) as total_items,
              SUM(ri.quantity) as total_quantity
              FROM request_groups rg
              LEFT JOIN request_items ri ON rg.id = ri.group_id
              WHERE rg.id = ?
              GROUP BY rg.id";
    $stmt = $db->prepare($query);
    $stmt->execute([$group_id]);
    $group = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$group) {
        die("Request group not found.");
    }
    
    // Get items for this group
    $items_query = "SELECT ri.*, c.item_name, c.unit 
                    FROM request_items ri
                    JOIN consumables c ON ri.consumable_id = c.id
                    WHERE ri.group_id = ?
                    ORDER BY ri.id ASC";
    $items_stmt = $db->prepare($items_query);
    $items_stmt->execute([$group_id]);
    $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC); // Define $items here
    
    $report_type = 'individual';
}

// If no data found
if (($report_type == 'individual' && empty($items)) || ($report_type == 'summary' && empty($all_items))) {
    die("No records found for this report.");
}

// Convert logo to base64 for embedding in PDF
$logo_path = '../assets/UCC_Logo.png';
$logo_base64 = '';
if (file_exists($logo_path)) {
    $logo_data = file_get_contents($logo_path);
    $logo_base64 = 'data:image/png;base64,' . base64_encode($logo_data);
}

// Calculate how many rows can fit in one page
$total_items = count($items);
$max_rows_per_page = 10; // Slightly reduced to accommodate larger text

// --- Generate HTML Report ---
$html = '
<html>
<head>
    <style>
        @page { margin: 100px 22px 60px 22px; }
        body { 
            font-family: "Helvetica", sans-serif; 
            font-size: 10px; 
            color: #000; 
            margin: 0; 
            padding: 0; 
            line-height: 1.3;
        }
        
        .header { 
            position: fixed; 
            top: -85px; 
            left: 0px; 
            right: 0px; 
            height: 85px; 
        }
        .header table { 
            width: auto; 
            margin: 0 auto; 
            border-collapse: collapse; 
        }
        .header .logo-ucc { 
            padding-right: 15px; 
        }
        .header .logo-ucc img { 
            width: 55px; 
        }
        .header .header-text { 
            text-align: center; 
        }
        .header h1 { 
            margin: 0; 
            font-size: 18px; 
            color: #000; 
            letter-spacing: 1px; 
        }
        .header p { 
            margin: 0; 
            font-size: 11px;
        }
        .report-title { 
            margin-top: 5px; 
            font-weight: bold; 
            text-decoration: underline; 
            font-size: 12px; 
            text-transform: uppercase; 
        }
        
        .footer { 
            position: fixed; 
            bottom: -55px; 
            left: 0px; 
            right: 0px; 
            height: 55px; 
            border-top: 1px solid #ccc; 
            padding-top: 8px; 
            font-size: 8px;
        }
        .footer table { 
            width: 100%; 
            border-collapse: collapse; 
        }
        
        .main-container { 
            width: 100%; 
            margin-top: 15px;
        }
        
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin: 12px 0 8px 0;
            border: 1px solid #000;
            font-size: 9px;
        }
        .info-table td {
            padding: 5px 8px;
            border: 1px solid #000;
        }
        .info-label {
            font-weight: bold;
            background: #f2f2f2;
            width: 16%;
        }
        .data-table { 
            width: 100%; 
            border-collapse: collapse; 
            font-size: 9px;
        }
        .data-table th { 
            border-bottom: 1px solid #000; 
            border-right: 1px solid #000; 
            padding: 6px; 
            background: #f2f2f2; 
            text-align: center; 
            font-weight: bold;
            text-transform: uppercase;
            font-size: 9px;
        }
        .data-table td { 
            border-bottom: 1px solid #000; 
            border-right: 1px solid #000; 
            padding: 6px; 
            vertical-align: middle; 
        }
        .data-table th:last-child, .data-table td:last-child { 
            border-right: 0; 
        }
        .footer-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 12px;
            font-size: 9px;
        }
        .footer-table td { 
            border-right: 1px solid #000; 
            width: 33.33%; 
            padding: 6px; 
            vertical-align: top; 
        }
        .footer-table td:last-child { 
            border-right: 0; 
        }
        .sig-container { 
            text-align: center; 
            margin-top: 18px; 
        }
        .sig-name { 
            font-weight: bold; 
            text-decoration: underline; 
            text-transform: uppercase; 
            font-size: 9px;
        }
        .sig-title { 
            display: block; 
            font-size: 7px; 
            margin-top: 2px; 
            color: #666;
        }
        .sig-line {
            border-top: 1px solid #000;
            margin: 4px 18px 2px 18px;
            padding-top: 4px;
            font-size: 7px;
        }
        .total-row {
            background: #e9ecef;
            font-weight: bold;
        }
        .page-break {
            page-break-after: always;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .footer-note {
            text-align: right; 
            padding: 6px; 
            font-size: 7px; 
            color: #666; 
            border-top: 1px dashed #000; 
            margin-top: 6px;
        }
    </style>
</head>
<body>
    <div class="header">
        <table>
            <tr>
                <td class="logo-ucc">' . ($logo_base64 ? '<img src="' . $logo_base64 . '">' : '') . '</td>
                <td class="header-text">
                    <h1>UNIVERSITY OF CALOOCAN CITY</h1>
                    <p>CONSUMABLE MANAGEMENT SYSTEM</p>
                    <p class="report-title">OFFICIAL CONSUMABLE RELEASE REPORT</p>
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">
        <table width="100%">
            <tr>
                <td width="33%">Generated by: <strong>' . htmlspecialchars($_SESSION['full_name'] ?? 'Admin') . '</strong></td>
                <td width="34%" align="center">';
                
if ($report_type == 'individual' && isset($group)) {
    $html .= 'Ref: ' . htmlspecialchars($group['group_code']);
} else {
    $html .= 'Master Report: ' . date('Ymd');
}

$html .= '          </td>
                <td width="33%" align="right">
                    <span style="color: #666;">' . date('Y-m-d h:i A') . '</span>
                </td>
            </tr>
        </table>
    </div>';

if ($report_type == 'individual') {
    // INDIVIDUAL GROUP REPORT - OPTIMIZED FOR ONE PAGE WITH LARGER TEXT
    $display_items = array_slice($items, 0, $max_rows_per_page);
    $has_more_items = count($items) > $max_rows_per_page;
    
    $html .= '
    <div class="main-container">
        <table class="info-table">
            <tr>
                <td class="info-label">Ref No.:</td>
                <td><strong>' . htmlspecialchars($group['group_code']) . '</strong></td>
                <td class="info-label">Date:</td>
                <td>' . date('M d, Y', strtotime($group['request_date'])) . '</td>
                <td class="info-label">Total:</td>
                <td><strong>' . count($items) . ' items</strong></td>
            </tr>
            <tr>
                <td class="info-label">Recipient:</td>
                <td colspan="2"><strong>' . htmlspecialchars($group['employee']) . '</strong></td>
                <td class="info-label">Office:</td>
                <td colspan="2">' . htmlspecialchars($group['office']) . '</td>
            </tr>
            <tr>
                <td class="info-label">Approved By:</td>
                <td colspan="2">' . htmlspecialchars($group['approved_by']) . '</td>
                <td class="info-label">Supply Officer:</td>
                <td colspan="2">' . htmlspecialchars($group['supply_officer']) . '</td>
            </tr>
        </table>
        
        <table class="data-table">
            <thead>
                <tr>
                    <th width="5%">#</th>
                    <th width="40%">Item Description</th>
                    <th width="10%">Qty</th>
                    <th width="10%">Unit</th>
                    <th width="35%">Purpose/Remarks</th>
                </tr>
            </thead>
            <tbody>';
            
            $count = 1;
            $total_qty = 0;
            
            foreach($display_items as $item) {
                $total_qty += $item['quantity'];
                
                $html .= '<tr>
                    <td align="center">' . $count++ . '</td>
                    <td>' . htmlspecialchars($item['item_name']) . '</td>
                    <td align="center">' . $item['quantity'] . '</td>
                    <td align="center">' . htmlspecialchars($item['unit']) . '</td>
                    <td>' . htmlspecialchars(substr($item['description'] ?: '—', 0, 35)) . (strlen($item['description'] ?? '') > 35 ? '...' : '') . '</td>
                </tr>';
            }
            
            // If there are more items, show note
            if ($has_more_items) {
                $html .= '<tr>
                    <td colspan="5" align="center" style="color: #666; font-style: italic; padding: 4px;">
                        ... and ' . (count($items) - $max_rows_per_page) . ' more item(s). See attached sheet.
                    </td>
                </tr>';
            } else {
                // Fill remaining rows to maintain layout
                $remaining = $max_rows_per_page - count($display_items);
                for($i = 0; $i < $remaining; $i++) {
                    $html .= '<tr>
                        <td align="center">' . ($count + $i) . '</td>
                        <td></td><td></td><td></td><td></td>
                    </tr>';
                }
            }
            
            // Add total row
            $html .= '<tr class="total-row">
                <td colspan="2" align="right"><strong>TOTAL ITEMS:</strong></td>
                <td align="center"><strong>' . $total_qty . '</strong></td>
                <td colspan="2"></td>
            </tr>';

    $html .= '
            </tbody>
        </table>
        
        <table class="footer-table">
            <tr>
                <td>
                    <span style="font-size: 8px;">Requested by:</span>
                    <div class="sig-container">
                        <div style="height:18px;"></div>
                        <span class="sig-name">' . htmlspecialchars($group['employee']) . '</span>
                        <div class="sig-line">Signature</div>
                    </div>
                </td>
                <td>
                    <span style="font-size: 8px;">Approved by:</span>
                    <div class="sig-container">
                        <div style="height:18px;"></div>
                        <span class="sig-name">' . htmlspecialchars($group['approved_by']) . '</span>
                        <span class="sig-title">AVP for Administration</span>
                    </div>
                </td>
                <td>
                    <span style="font-size: 8px;">Released by:</span>
                    <div class="sig-container">
                        <div style="height:18px;"></div>
                        <span class="sig-name">' . htmlspecialchars($group['supply_officer']) . '</span>
                        <span class="sig-title">Supply Officer</span>
                    </div>
                </td>
            </tr>
            <tr>
                <td colspan="3">
                    <span style="font-size: 8px;">Received by:</span>
                    <div class="sig-container">
                        <div style="height:18px;"></div>
                        <span class="sig-name">' . htmlspecialchars($group['employee']) . '</span>
                        <div class="sig-line">Signature</div>
                    </div>
                </td>
            </tr>
        </table>
        
        <div class="footer-note">
            <em>This document serves as official receipt of inventory items transferred.</em>
        </div>
    </div>';
    
} else {
    // SUMMARY REPORT - Keep as is but with slightly larger text
    $html .= '
    <div class="main-container">';
    
    foreach ($groups as $group_idx => $group) {
        if ($group_idx > 0) {
            $html .= '<div style="margin-top: 18px; border-top: 1px solid #ccc; padding-top: 12px;"></div>';
        }
        
        // Get items for this group
        $items_query = "SELECT ri.*, c.item_name, c.unit 
                        FROM request_items ri
                        JOIN consumables c ON ri.consumable_id = c.id
                        WHERE ri.group_id = ? AND ri.status = 'Approved'
                        ORDER BY ri.id ASC";
        $items_stmt = $db->prepare($items_query);
        $items_stmt->execute([$group['id']]);
        $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $html .= '
        <div style="margin-top: 8px;">
            <table class="info-table" style="margin: 6px 0;">
                <tr>
                    <td class="info-label">Ref:</td>
                    <td><strong>' . htmlspecialchars($group['group_code']) . '</strong></td>
                    <td class="info-label">Recipient:</td>
                    <td>' . htmlspecialchars($group['employee']) . '</td>
                    <td class="info-label">Date:</td>
                    <td>' . date('M d, Y', strtotime($group['request_date'])) . '</td>
                </tr>
                <tr>
                    <td class="info-label">Office:</td>
                    <td colspan="5">' . htmlspecialchars($group['office']) . '</td>
                </tr>
            </table>
            
            <table class="data-table">
                <thead>
                    <tr>
                        <th width="5%">#</th>
                        <th width="40%">Item</th>
                        <th width="10%">Qty</th>
                        <th width="10%">Unit</th>
                        <th width="35%">Purpose</th>
                    </tr>
                </thead>
                <tbody>';
                
                $count = 1;
                $group_total = 0;
                $display_summary = array_slice($items, 0, 4); // Show 4 items per group
                
                foreach($display_summary as $item) {
                    $group_total += $item['quantity'];
                    $html .= '<tr>
                        <td align="center">' . $count++ . '</td>
                        <td>' . htmlspecialchars(substr($item['item_name'], 0, 30)) . (strlen($item['item_name']) > 30 ? '...' : '') . '</td>
                        <td align="center">' . $item['quantity'] . '</td>
                        <td align="center">' . htmlspecialchars($item['unit']) . '</td>
                        <td>' . htmlspecialchars(substr($item['description'] ?: '—', 0, 25)) . (strlen($item['description'] ?? '') > 25 ? '...' : '') . '</td>
                    </tr>';
                }
                
                if (count($items) > 4) {
                    $html .= '<tr>
                        <td colspan="5" align="center" style="color: #666; font-style: italic; padding: 3px;">
                            + ' . (count($items) - 4) . ' more item(s)
                        </td>
                    </tr>';
                }
                
                $html .= '<tr class="total-row">
                    <td colspan="2" align="right"><strong>Group Total:</strong></td>
                    <td align="center"><strong>' . $group_total . '</strong></td>
                    <td colspan="2"></td>
                </tr>';
                
        $html .= '
                </tbody>
            </table>
        </div>';
    }
    
    // Grand total for all groups
    $grand_total = 0;
    foreach ($all_items as $item) {
        $grand_total += $item['quantity'];
    }
    
    $html .= '
    <div style="margin-top: 18px;">
        <table class="info-table" style="width: 50%; margin-left: auto;">
            <tr>
                <td class="text-right"><strong>GRAND TOTAL ITEMS:</strong></td>
                <td width="20%" class="text-center"><strong>' . $grand_total . '</strong></td>
            </tr>
        </table>
    </div>
    
    <table class="footer-table">
        <tr>
            <td>
                <div class="sig-container" style="margin-top: 8px;">
                    <span class="sig-name">MARVIN Z. GERVACIO</span>
                    <span class="sig-title">Supply Officer</span>
                </div>
            </td>
            <td>
                <div class="sig-container" style="margin-top: 8px;">
                    <span class="sig-name">REYNALDO H. CARANDANG JR.</span>
                    <span class="sig-title">AVP for Administration</span>
                </div>
            </td>
            <td>
                <div class="sig-container" style="margin-top: 8px;">
                    <span class="sig-name">____________________</span>
                    <div class="sig-line">Signature</div>
                </div>
            </td>
        </tr>
    </table>';
}

$html .= '
</body>
</html>';

// Generate PDF
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'Helvetica');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Set filename based on report type
if ($report_type == 'individual' && isset($group)) {
    $filename = "Release_Report_" . $group['group_code'] . ".pdf";
} else {
    $filename = "Master_Release_Report_" . date('Ymd') . ".pdf";
}

// Stream the PDF to browser
$dompdf->stream($filename, ["Attachment" => false]);
?>