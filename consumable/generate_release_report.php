<?php
session_start();
date_default_timezone_set('Asia/Manila');

// Check if user is logged in (any authenticated user can generate reports)
if (!isset($_SESSION['user_id'])) {
    die("Unauthorized access - Please login first");
}

require_once '../vendor/autoload.php';
require_once '../config/database.php';
use Dompdf\Dompdf;
use Dompdf\Options;

$database = new Database();
$db = $database->getConnection();

// Get user info to check if they have access to this specific report
$user_id = $_SESSION['user_id'];
$user_query = $db->prepare("SELECT id, full_name, role FROM users WHERE id = ?");
$user_query->execute([$user_id]);
$current_user = $user_query->fetch(PDO::FETCH_ASSOC);

if (!$current_user) {
    die("User not found");
}

// --- Get group_id parameter ---
$group_id = $_GET['group_id'] ?? null;

if (!$group_id) {
    // If no specific group, show all approved groups summary (admin only)
    if ($current_user['role'] !== 'admin') {
        die("Unauthorized access - Only administrators can view summary reports");
    }
    
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
} else {
    // Individual Group Report - Check if user has access to this group
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
    
    // Check authorization: Allow if user is admin OR if the request belongs to the user
    if ($current_user['role'] !== 'admin' && $group['employee'] !== $current_user['full_name']) {
        die("Unauthorized access - You can only view your own requests");
    }
    
    // Get items for this group
    $items_query = "SELECT ri.*, c.item_name, c.unit 
                    FROM request_items ri
                    JOIN consumables c ON ri.consumable_id = c.id
                    WHERE ri.group_id = ?
                    ORDER BY ri.id ASC";
    $items_stmt = $db->prepare($items_query);
    $items_stmt->execute([$group_id]);
    $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $report_type = 'individual';
}

// If no data found
if (($report_type == 'individual' && empty($items)) || ($report_type == 'summary' && empty($all_items))) {
    die("No records found for this report.");
}

// --- Generate HTML Report (same as before) ---
$html = '
<html>
<head>
    <style>
        @page { margin: 20px; }
        body { 
            font-family: "Helvetica", sans-serif; 
            font-size: 10px; 
            color: #000; 
            margin: 0; 
            padding: 0; 
            line-height: 1.3;
        }
        .main-container { 
            width: 100%; 
            border: 1px solid #000; 
        }
        .header-table { 
            width: 100%; 
            border-collapse: collapse; 
            border-bottom: 1px solid #000; 
        }
        .header-table td { 
            padding: 10px; 
            text-align: center; 
        }
        .header-title { 
            font-size: 16px; 
            font-weight: bold; 
            margin: 0; 
            text-transform: uppercase;
        }
        .header-subtitle { 
            font-size: 12px; 
            margin: 2px 0; 
        }
        .header-reference {
            font-size: 14px;
            font-weight: bold;
            margin: 5px 0;
            color: #0d6efd;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            border: 1px solid #000;
        }
        .info-table td {
            padding: 5px 10px;
            border: 1px solid #000;
        }
        .info-label {
            font-weight: bold;
            background: #f2f2f2;
            width: 20%;
        }
        .data-table { 
            width: 100%; 
            border-collapse: collapse; 
        }
        .data-table th { 
            border-bottom: 1px solid #000; 
            border-right: 1px solid #000; 
            padding: 8px; 
            background: #f2f2f2; 
            text-align: center; 
            font-weight: bold;
            text-transform: uppercase;
            font-size: 9px;
        }
        .data-table td { 
            border-bottom: 1px solid #000; 
            border-right: 1px solid #000; 
            padding: 8px; 
            vertical-align: middle; 
        }
        .data-table th:last-child, .data-table td:last-child { 
            border-right: 0; 
        }
        .footer-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 20px;
        }
        .footer-table td { 
            border-right: 1px solid #000; 
            width: 33.33%; 
            padding: 10px; 
            vertical-align: top; 
        }
        .footer-table td:last-child { 
            border-right: 0; 
        }
        .sig-container { 
            text-align: center; 
            margin-top: 30px; 
        }
        .sig-name { 
            font-weight: bold; 
            text-decoration: underline; 
            text-transform: uppercase; 
            font-size: 11px;
        }
        .sig-title { 
            display: block; 
            font-size: 8px; 
            margin-top: 2px; 
            color: #666;
        }
        .sig-line {
            border-top: 1px solid #000;
            margin: 5px 20px 2px 20px;
            padding-top: 5px;
        }
        .summary-box {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 10px;
            margin-bottom: 15px;
        }
        .summary-title {
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .total-row {
            background: #e9ecef;
            font-weight: bold;
        }
        .page-break {
            page-break-after: always;
        }
        .status-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-approved {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
    </style>
</head>
<body>';

if ($report_type == 'individual') {
    // INDIVIDUAL GROUP REPORT
    $html .= '
    <div class="main-container">
        <table class="header-table">
            <tr>
                <td>
                    <div class="header-title">UNIVERSITY OF CALOOCAN CITY</div>
                    <div class="header-subtitle">GENERAL SERVICES OFFICE</div>
                    <div class="header-reference">RELEASE REPORT: ' . htmlspecialchars($group['group_code']) . '</div>
                </td>
            </tr>
        </table>
        
        <table class="info-table">
            <tr>
                <td class="info-label">Recipient:</td>
                <td><strong>' . htmlspecialchars($group['employee']) . '</strong></td>
                <td class="info-label">Office:</td>
                <td>' . htmlspecialchars($group['office']) . '</td>
            </tr>
            <tr>
                <td class="info-label">Request Date:</td>
                <td>' . date('F d, Y', strtotime($group['request_date'])) . '</td>
                <td class="info-label">Total Items:</td>
                <td>' . $group['total_items'] . '</td>
            </tr>
            <tr>
                <td class="info-label">Approved By:</td>
                <td>' . htmlspecialchars($group['approved_by']) . '</td>
                <td class="info-label">Supply Officer:</td>
                <td>' . htmlspecialchars($group['supply_officer']) . '</td>
            </tr>
        </table>
        
        <table class="data-table">
            <thead>
                <tr>
                    <th width="5%">No.</th>
                    <th width="40%">Item Description</th>
                    <th width="15%">Quantity</th>
                    <th width="15%">Unit</th>
                    <th width="25%">Purpose/Remarks</th>
                </tr>
            </thead>
            <tbody>';
            
            $count = 1;
            $total_qty = 0;
            foreach($items as $item) {
                $total_qty += $item['quantity'];
                
                $html .= '<tr>
                    <td align="center">' . $count++ . '</td>
                    <td>' . htmlspecialchars($item['item_name']) . '</td>
                    <td align="center">' . $item['quantity'] . '</td>
                    <td align="center">' . htmlspecialchars($item['unit']) . '</td>
                    <td>' . htmlspecialchars($item['description'] ?: '—') . '</td>
                </tr>';
            }
            
            // Fill empty rows to make the form look balanced (total 15 rows)
            for($i = count($items); $i < 15; $i++) {
                $html .= '<tr>
                    <td align="center">' . ($i + 1) . '</td>
                    <td></td><td></td><td></td><td></td>
                </tr>';
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
                    Requested by:
                    <div class="sig-container">
                        <div style="height:25px;"></div>
                        <span class="sig-name">' . htmlspecialchars($group['employee']) . '</span>
                        <div class="sig-line">Signature Over Printed Name</div>
                    </div>
                </td>
                <td>
                    Approved by:
                    <div class="sig-container">
                        <div style="height:25px;"></div>
                        <span class="sig-name">' . htmlspecialchars($group['approved_by']) . '</span>
                        <span class="sig-title">AVP for Administration</span>
                    </div>
                </td>
                <td>
                    Released by:
                    <div class="sig-container">
                        <div style="height:25px;"></div>
                        <span class="sig-name">' . htmlspecialchars($group['supply_officer']) . '</span>
                        <span class="sig-title">Supply Officer</span>
                    </div>
                </td>
            </tr>
            <tr>
                <td colspan="3">
                    Received by:
                    <div class="sig-container">
                        <div style="height:25px;"></div>
                        <span class="sig-name">' . htmlspecialchars($group['employee']) . '</span>
                        <div class="sig-line">Signature Over Printed Name</div>
                    </div>
                </td>
            </tr>
        </table>
    </div>';
    
} else {
    // SUMMARY REPORT - ALL APPROVED REQUESTS (Admin only)
    $html .= '
    <div class="main-container">
        <table class="header-table">
            <tr>
                <td>
                    <div class="header-title">UNIVERSITY OF CALOOCAN CITY</div>
                    <div class="header-subtitle">GENERAL SERVICES OFFICE</div>
                    <div class="header-reference">MASTER RELEASE REPORT</div>
                    <div style="font-size: 10px; margin-top: 5px;">As of ' . date('F d, Y') . '</div>
                </td>
            </tr>
        </table>';
    
    foreach ($groups as $group_idx => $group) {
        if ($group_idx > 0) {
            $html .= '<div class="page-break"></div>';
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
        <div style="margin-top: 20px;">
            <table class="info-table">
                <tr>
                    <td class="info-label">Reference:</td>
                    <td><strong>' . htmlspecialchars($group['group_code']) . '</strong></td>
                    <td class="info-label">Recipient:</td>
                    <td>' . htmlspecialchars($group['employee']) . '</td>
                </tr>
                <tr>
                    <td class="info-label">Office:</td>
                    <td>' . htmlspecialchars($group['office']) . '</td>
                    <td class="info-label">Date:</td>
                    <td>' . date('F d, Y', strtotime($group['request_date'])) . '</td>
                </tr>
            </table>
            
            <table class="data-table">
                <thead>
                    <tr>
                        <th width="5%">#</th>
                        <th width="45%">Item Description</th>
                        <th width="15%">Quantity</th>
                        <th width="10%">Unit</th>
                        <th width="25%">Purpose</th>
                    </tr>
                </thead>
                <tbody>';
                
                $count = 1;
                $group_total = 0;
                foreach($items as $item) {
                    $group_total += $item['quantity'];
                    $html .= '<tr>
                        <td align="center">' . $count++ . '</td>
                        <td>' . htmlspecialchars($item['item_name']) . '</td>
                        <td align="center">' . $item['quantity'] . '</td>
                        <td align="center">' . htmlspecialchars($item['unit']) . '</td>
                        <td>' . htmlspecialchars($item['description'] ?: '—') . '</td>
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
    <div style="margin-top: 30px;">
        <table class="info-table">
            <tr>
                <td class="text-right"><strong>GRAND TOTAL ITEMS RELEASED:</strong></td>
                <td width="15%" class="text-center"><strong>' . $grand_total . '</strong></td>
            </tr>
        </table>
    </div>
    
    <table class="footer-table">
        <tr>
            <td>
                Prepared by:
                <div class="sig-container">
                    <div style="height:25px;"></div>
                    <span class="sig-name">MARVIN Z. GERVACIO</span>
                    <span class="sig-title">Supply Officer</span>
                </div>
            </td>
            <td>
                Noted by:
                <div class="sig-container">
                    <div style="height:25px;"></div>
                    <span class="sig-name">REYNALDO H. CARANDANG JR.</span>
                    <span class="sig-title">AVP for Administration</span>
                </div>
            </td>
            <td>
                Received by:
                <div class="sig-container">
                    <div style="height:25px;"></div>
                    <span class="sig-name">____________________</span>
                    <div class="sig-line">Signature Over Printed Name</div>
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