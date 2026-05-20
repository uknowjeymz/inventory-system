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

$room_id = $_POST['room_id'] ?? 0;

// 1. Fetch Room and Category Info
$room_stmt = $db->prepare("SELECT l.*, lt.type_name, u.full_name as manager 
                            FROM locations l 
                            LEFT JOIN location_types lt ON l.location_type_id = lt.id 
                            LEFT JOIN users u ON l.facilitator_id = u.id 
                            WHERE l.id = ?");
$room_stmt->execute([$room_id]);
$room = $room_stmt->fetch(PDO::FETCH_ASSOC);

if (!$room) die("Room record not found.");

// 2. Fetch Equipment with Package Detection
$equipment_list = [];
$tables = [
    'computer_inventory' => 'Computer',
    'kitchen_equipment' => 'Kitchen',
    'office_equipment' => 'Office',
    'lab_equipment' => 'Lab',
    'general_equipment' => 'General'
];

foreach ($tables as $table => $label) {
    // Dynamic column detection
    $name_col = ($table === 'computer_inventory') ? 'computer_set_description' : (($table === 'general_equipment') ? 'article' : 'equipment_name');
    $sn_col = ($table === 'general_equipment') ? 'property_no' : 'serial_number';
    
    // We must select 'article' explicitly for the Package Serial logic to work
    $article_col = ($table === 'computer_inventory') ? "article" : "NULL as article";

    $extra_cols = ($table === 'computer_inventory') ? ", device_type, serial_number_monitor, serial_number_system" : ", NULL as device_type, NULL as serial_number_monitor, NULL as serial_number_system";
    
    // Updated query includes $article_col
    $query = "SELECT '$label' as cat_label, item_number, $name_col as item_name, $sn_col as sn, status, condition_status, remarks, $article_col $extra_cols 
              FROM $table WHERE location_id = ? AND (is_condemned = 0 OR is_condemned IS NULL OR is_condemned = FALSE)";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$room_id]);
    $equipment_list = array_merge($equipment_list, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

$html = '
<html>
<head>
    <style>
        @page { margin: 100px 25px; }
        body { font-family: "Helvetica", sans-serif; font-size: 10px; color: #333; line-height: 1.4; }
        .header { position: fixed; top: -85px; left: 0px; right: 0px; height: 100px; }
        .header table { width: auto; margin: 0 auto; border-collapse: collapse; }
        .header .logo-ucc { padding-right: 15px; }
        .header .header-text { text-align: center; }
        .header h1 { margin: 0; font-size: 18px; color: #000; letter-spacing: 1px; }
        .report-title { margin-top: 5px; font-weight: bold; text-decoration: underline; font-size: 12px; text-transform: uppercase; }
        .footer { position: fixed; bottom: -60px; left: 0px; right: 0px; height: 60px; border-top: 1px solid #ccc; padding-top: 10px; font-size: 9px; }
        .logo-caloocan img { width: 130px; }
        .summary-box { margin-top: 10px; margin-bottom: 15px; padding: 12px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 5px; }
        .inventory-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .inventory-table th { background-color: #f5f5f5; color: #333; padding: 8px; text-transform: uppercase; border: 1px solid #ddd; font-size: 8px; }
        .inventory-table td { padding: 7px; border: 1px solid #ddd; vertical-align: middle; }
        .package-details { font-size: 8px; color: #555; margin-top: 4px; padding-top: 4px; border-top: 1px dotted #ccc; }
        .status-available { color: #2e7d32; font-weight: bold; }
        .status-maintenance { color: #f57c00; font-weight: bold; }
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
                    <p class="report-title">Room Inventory Report</p>
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">
        <table width="100%">
            <tr>
                <td width="33%">Generated by: <strong>' . htmlspecialchars($_SESSION['full_name'] ?? 'Admin') . '</strong></td>
                <td width="34%" align="center">Ref: ROOM-' . $room_id . '-' . date('Ymd') . '</td>
                <td width="33%" align="right"><div class="logo-caloocan">' . ($caloocanLogo ? '<img src="' . $caloocanLogo . '">' : '') . '</div></td>
            </tr>
        </table>
    </div>

    <div class="summary-box">
        <table width="100%" style="border:none;">
            <tr>
                <td style="border:none;"><strong>LOCATION/ROOM:</strong> <span style="font-size: 12px;">' . htmlspecialchars($room['location_name']) . '</span></td>
                <td style="border:none;" align="right"><strong>DATE:</strong> ' . date("F d, Y") . '</td>
            </tr>
            <tr>
                <td style="border:none;"><strong>ROOM MANAGER:</strong> ' . htmlspecialchars($room['manager'] ?? 'None Assigned') . '</td>
                <td style="border:none;" align="right"><strong>CATEGORY:</strong> ' . htmlspecialchars($room['type_name']) . '</td>
            </tr>
        </table>
    </div>

    <table class="inventory-table">
        <thead>
            <tr>
                <th width="8%">Cat.</th>
                <th width="12%">Item #</th>
                <th width="35%">Description / Model</th>
                <th width="20%">Primary Serial / Property #</th>
                <th width="15%">Accountable</th>
                <th width="10%">Status</th>
            </tr>
        </thead>
        <tbody>';

if (empty($equipment_list)) {
    $html .= '<tr><td colspan="6" align="center" style="padding: 30px;">No active equipment found in this location.</td></tr>';
} else {
    foreach ($equipment_list as $e) {
        $statusStyle = 'status-' . strtolower($e['status']);
        
        // Start Item Info
        $html .= '<tr>
            <td align="center">' . $e['cat_label'] . '</td>
            <td align="center"><strong>' . htmlspecialchars($e['item_number']) . '</strong></td>
            <td>
                <div>' . htmlspecialchars($e['item_name']) . '</div>';
        
        // FIXED LOGIC: Check 'article' instead of 'device_type'
        // In your DB, 'Computer Package' is stored in the 'article' column.
        if ($e['cat_label'] === 'Computer' && $e['article'] === 'Computer Package') {
            $html .= '<div class="package-details">
                        <strong>Monitor SN:</strong> ' . htmlspecialchars($e['serial_number_monitor'] ?: 'N/A') . '<br>
                        <strong>System SN:</strong> ' . htmlspecialchars($e['serial_number_system'] ?: 'N/A') . '
                    </div>';
        }

        $html .= '</td>
            <td align="center"><code>' . htmlspecialchars($e['sn'] ?: 'N/A') . '</code></td>
            <td>' . htmlspecialchars($e['remarks'] ?: 'Room Facilitator') . '</td>
            <td align="center" class="' . $statusStyle . '">' . strtoupper($e['status']) . '</td>
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
                <strong>' . htmlspecialchars($room['manager'] ?? 'Room Facilitator') . '</strong><br>
                <small>Custodian / Facilitator</small>
            </div>
        </div>
        <div class="sig-container" style="float: right;">
            <div style="height: 40px;"></div>
            <div class="sig-box">
                <strong>' . htmlspecialchars($_SESSION['full_name'] ?? 'Authorized Admin') . '</strong><br>
                <small>Verified by Inventory Dept.</small>
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
$dompdf->stream("Inventory_Report_" . str_replace(' ', '_', $room['location_name']) . ".pdf", ["Attachment" => false]);

echo $dompdf->output(); 
exit;