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

// Get transfer ID from URL
$transfer_id = $_GET['transfer_id'] ?? 0;

if (!$transfer_id) {
    die("Transfer ID is required");
}

// Fetch transfer history details
$transfer_query = "SELECT th.*, u.full_name as processor_name 
                   FROM transfer_history th
                   LEFT JOIN users u ON th.transferred_by = u.id
                   WHERE th.id = ?";
$transfer_stmt = $db->prepare($transfer_query);
$transfer_stmt->execute([$transfer_id]);
$transfer = $transfer_stmt->fetch(PDO::FETCH_ASSOC);

if (!$transfer) {
    die("Transfer record not found");
}

// Parse equipment IDs
$equipment_ids = explode(',', $transfer['equipment_ids']);
$table_map = [
    'computer' => 'computer_inventory', 
    'computer_lab' => 'computer_inventory',
    'kitchen' => 'kitchen_equipment', 
    'office' => 'office_equipment', 
    'lab' => 'lab_equipment',
    'regular_lab' => 'lab_equipment',
    'general' => 'general_equipment'
];

$target_table = $table_map[$transfer['equipment_type']] ?? 'general_equipment';

// Build query to fetch equipment details - INCLUDING unit field
$placeholders = implode(',', array_fill(0, count($equipment_ids), '?'));
$item_query = "SELECT * FROM $target_table WHERE id IN ($placeholders)";
$item_stmt = $db->prepare($item_query);
$item_stmt->execute($equipment_ids);
$items = $item_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate total amount
$total_amount = 0;
foreach ($items as $item) {
    $total_amount += floatval($item['cost'] ?? 0);
}

// Determine how many empty rows needed (optimized for one page)
$row_count = count($items);
$target_rows = 12; // Adjusted for one page with larger text
$empty_rows = max(0, $target_rows - $row_count);

// Helper function to format unit for display
function formatUnit($unit) {
    if (empty($unit)) return 'unit';
    
    switch(strtolower($unit)) {
        case 'unit': return 'unit';
        case 'box': return 'box';
        case 'pcs': return 'pcs';
        case 'lot': return 'lot';
        default: return $unit;
    }
}

// Build HTML for PDF - OPTIMIZED FOR ONE PAGE
$html = '
<html>
<head>
    <style>
        @page { 
            margin: 0.4in; 
            size: letter;
        }
        body { 
            font-family: "Times New Roman", Times, serif; 
            font-size: 10px; 
            color: #000; 
            line-height: 1.2;
        }
        .header { 
            text-align: center; 
            margin-bottom: 8px; 
        }
        .header h1 { 
            font-size: 18px; 
            margin: 0; 
            font-weight: bold;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .header .agency-wrapper { 
            border-top: 1px solid #000; 
            border-bottom: 1px solid #000; 
            padding: 2px 0; 
            margin: 5px auto; 
            width: 55%; 
        }
        .header .agency { 
            font-size: 12px; 
            font-weight: normal; 
            margin: 0; 
            text-transform: uppercase; 
        }
        
        /* Transfer Info Section - Balanced Size */
        .transfer-info { 
            background: #f0f7f0; 
            padding: 8px 10px; 
            margin-bottom: 10px; 
            border-left: 5px solid #2E7D32; 
            border-radius: 0 5px 5px 0; 
        }
        .transfer-info table { 
            width: 100%; 
            border-collapse: collapse; 
        }
        .transfer-info td { 
            padding: 4px 4px; 
            vertical-align: top; 
        }
        .transfer-info .label { 
            font-weight: bold; 
            color: #1B5E20; 
            width: 100px; 
            font-size: 10px; 
        }
        .transfer-info .value { 
            font-weight: 600; 
            color: #000; 
            font-size: 11px; 
        }
        .transfer-info .badge { 
            background: #2E7D32; 
            color: white; 
            padding: 3px 8px; 
            border-radius: 15px; 
            font-size: 10px; 
            font-weight: bold; 
            display: inline-block; 
            margin-bottom: 3px;
        }
        
        /* Main Table - Optimized */
        .main-table { 
            width: 100%; 
            border: 1px solid black; 
            border-collapse: collapse; 
            margin-top: 5px; 
        }
        .main-table th, .main-table td { 
            border: 1px solid black; 
            padding: 5px; 
        }
        .main-table th { 
            font-size: 10px; 
            font-weight: bold; 
            text-align: center; 
            height: 28px; 
            vertical-align: middle; 
            background: #e8e8e8; 
        }
        .main-table td { 
            font-size: 10px; 
            vertical-align: top; 
        }
        
        /* Footer Table - Optimized */
        .footer-table { 
            width: 100%; 
            border: 1px solid black; 
            border-collapse: collapse; 
            margin-top: -1px; 
        }
        .footer-table td { 
            border: 1px solid black; 
            width: 50%; 
            vertical-align: top; 
            padding: 0; 
        }
        .sig-container { 
            padding: 6px 8px; 
            font-size: 10px; 
        }
        .sig-row { 
            text-align: center; 
            padding: 6px 0; 
        }
        .black-line { 
            border-bottom: 2px solid black !important; 
            margin-top: 4px; 
        }
        .sig-label { 
            font-size: 8px; 
            display: block; 
            color: #555; 
        }
        .sig-value { 
            font-weight: bold; 
            font-size: 11px; 
            display: block; 
            margin-bottom: 2px; 
        }
        .total-cell { 
            text-align: center; 
            font-weight: bold; 
            font-size: 11px; 
        }
        .peso { 
            font-family: "DejaVu Sans", sans-serif; 
        }
        .sn-detail { 
            font-size: 8px; 
            color: #555; 
            display: block; 
            margin-top: 2px; 
            line-height: 1.2;
        }
        .amount-cell { 
            font-size: 11px; 
            font-weight: bold; 
        }
        .footer-note {
            margin-top: 8px;
            font-size: 7px;
            text-align: center;
            color: #666;
            border-top: 1px dashed #ccc;
            padding-top: 4px;
        }
        .page-name {
            font-size: 14px;
            font-weight: bold;
            text-align: center;
            margin-top: 5px;
            margin-bottom: 5px;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>PROPERTY ACKNOWLEDGEMENT RECEIPT</h1>
        <div class="agency-wrapper">
            <p class="agency">CALOOCAN CITY GOVERNMENT</p>
        </div>
        <div style="font-size: 9px;">LGU</div>
    </div>

    <div class="page-name">PROPERTY ACKNOWLEDGEMENT RECEIPT</div>';

// Add transfer information
$html .= '
    <div class="transfer-info">
        <div style="margin-bottom: 3px;">
            <span class="badge">TRANSFER BATCH #TRNS-' . str_pad($transfer['id'], 5, "0", STR_PAD_LEFT) . '</span>
        </div>
        <table width="100%">
            <tr>
                <td class="label">Transfer Date:</td>
                <td class="value"><strong>' . date('F d, Y', strtotime($transfer['transfer_date'])) . '</strong></td>
                <td class="label">From Campus:</td>
                <td class="value" style="color: #D32F2F;"><strong>' . htmlspecialchars($transfer['from_campus']) . '</strong></td>
            </tr>
            <tr>
                <td class="label">Previous Accountable:</td>
                <td class="value">' . htmlspecialchars($transfer['previous_accountable'] ?? 'N/A') . '</td>
                <td class="label">To Campus:</td>
                <td class="value" style="color: #2E7D32;"><strong>' . htmlspecialchars($transfer['to_campus']) . '</strong></td>
            </tr>
            <tr>
                <td class="label">New Accountable:</td>
                <td class="value" colspan="3"><strong style="font-size: 12px;">' . htmlspecialchars($transfer['new_accountable']) . '</strong></td>
            </tr>
        </table>
    </div>

    <table class="main-table">
        <thead>
            <tr>
                <th width="6%">QTY</th>
                <th width="6%">UNIT</th>
                <th width="39%">DESCRIPTION</th>
                <th width="12%">ESTIMATED<br>USEFUL LIFE</th>
                <th width="18%">PROPERTY NO.</th>
                <th width="19%">AMOUNT</th>
            </tr>
        </thead>
        <tbody>';

foreach ($items as $item) {
    $cost = floatval($item['cost'] ?? 0);
    
    // Get description based on equipment type
    $desc = '';
    if (isset($item['computer_set_description'])) {
        $desc = '<strong>' . htmlspecialchars($item['computer_set_description']) . '</strong>';
        // Add serial numbers for computers
        if (!empty($item['serial_number_monitor']) || !empty($item['serial_number_system'])) {
            $desc .= '<span class="sn-detail">';
            $desc .= 'MON: ' . htmlspecialchars($item['serial_number_monitor'] ?: 'N/A') . ' | ';
            $desc .= 'SYS: ' . htmlspecialchars($item['serial_number_system'] ?: 'N/A');
            $desc .= '</span>';
        }
    } elseif (isset($item['equipment_name'])) {
        $desc = '<strong>' . htmlspecialchars($item['equipment_name']) . '</strong>';
    } elseif (isset($item['article'])) {
        $desc = '<strong>' . htmlspecialchars($item['article']) . '</strong>';
    } else {
        $desc = '---';
    }
    
    // Get property number
    $prop_no = !empty($item['property_no']) ? htmlspecialchars($item['property_no']) : 'N/A';
    
    // Get unit from database, fallback to 'unit' if not set
    $unit = !empty($item['unit']) ? formatUnit($item['unit']) : 'unit';
    
    $html .= '<tr>
        <td align="center">1</td>
        <td align="center">' . htmlspecialchars($unit) . '</td>
        <td>' . $desc . '</td>
        <td align="center">---</td>
        <td align="center"><strong>' . $prop_no . '</strong></td>
        <td align="right" class="amount-cell"><span class="peso">₱</span> ' . number_format($cost, 2) . '</td>
    </tr>';
}

// Add empty rows to fill page
for ($i = 0; $i < $empty_rows; $i++) {
    $html .= '<tr><td height="20">&nbsp;</td><td></td><td></td><td></td><td></td><td></td></tr>';
}

$html .= '
            <tr style="background: #f5f5f5;">
                <td colspan="4" class="total-cell" style="text-align: right; padding-right: 10px;">TOTAL</td>
                <td class="total-cell"></td>
                <td align="right" style="font-weight: bold; font-size: 12px;"><span class="peso">₱</span> ' . number_format($total_amount, 2) . '</td>
            </tr>
        </tbody>
    </table>

    <table class="footer-table">
        <tr>
            <td>
                <div class="sig-container">
                    <strong>RECEIVED FROM:</strong>
                </div>
                <div class="sig-row black-line">
                    <span class="sig-value">' . htmlspecialchars($transfer['previous_accountable'] ?? 'N/A') . '</span>
                    <span class="sig-label">Name of Transferor / Previous Accountable Person</span>
                </div>
                <div class="sig-row black-line">
                    <span class="sig-value">' . htmlspecialchars($transfer['from_campus']) . '</span>
                    <span class="sig-label">Campus / Office</span>
                </div>
                <div class="sig-row">
                    <span class="sig-value">' . date('F d, Y', strtotime($transfer['transfer_date'])) . '</span>
                    <span class="sig-label">Date</span>
                </div>
            </td>
            <td>
                <div class="sig-container">
                    <strong>RECEIVED BY:</strong>
                </div>
                <div class="sig-row black-line">
                    <span class="sig-value">' . htmlspecialchars($transfer['new_accountable']) . '</span>
                    <span class="sig-label">Name of Receiver / New Accountable Person</span>
                </div>
                <div class="sig-row black-line">
                    <span class="sig-value">' . htmlspecialchars($transfer['to_campus']) . '</span>
                    <span class="sig-label">Campus / Office</span>
                </div>
                <div class="sig-row">
                    <span class="sig-value">' . date('F d, Y', strtotime($transfer['transfer_date'])) . '</span>
                    <span class="sig-label">Date</span>
                </div>
            </td>
        </tr>
    </table>
    
    <div class="footer-note">
        PAR generated from Transfer #' . str_pad($transfer['id'], 5, "0", STR_PAD_LEFT) . ' | Processed by: ' . htmlspecialchars($transfer['processor_name'] ?? 'Admin') . ' | ' . date('Y-m-d') . '
    </div>
</body>
</html>';

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'Times-Roman');
$options->set('isPhpEnabled', true);
$options->set('isJavascriptEnabled', false);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('letter', 'portrait');
$dompdf->render();

$filename = "PAR_Transfer_" . str_pad($transfer['id'], 5, "0", STR_PAD_LEFT) . "_" . date('Ymd') . ".pdf";

$dompdf->stream($filename, ["Attachment" => false]);
?>