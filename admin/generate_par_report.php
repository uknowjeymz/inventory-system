<?php
require_once '../vendor/autoload.php';
require_once '../config/database.php';
use Dompdf\Dompdf;
use Dompdf\Options;

// GET DATA FROM MODAL POST
$from_name = $_POST['from_name'] ?? '';
$from_pos  = $_POST['from_pos']  ?? '';
$by_name   = $_POST['by_name']   ?? '';
$by_pos    = $_POST['by_pos']    ?? '';
$report_date = $_POST['report_date'] ?? date('Y-m-d');
$par_source = $_POST['par_source'] ?? 'all';
$selected_items = $_POST['selected_items'] ?? '';
$accountable_person = $_POST['par_accountable_person'] ?? '';

$database = new Database();
$db = $database->getConnection();

// Parse selected items into a structured array for each table
$selected_by_table = [
    'computer_inventory' => [],
    'kitchen_equipment' => [],
    'office_equipment' => [],
    'lab_equipment' => [],
    'general_equipment' => []
];

if ($par_source === 'selected' && !empty($selected_items)) {
    // Parse selected items (format: "type:id,type:id,...")
    $items_array = explode(',', $selected_items);
    
    foreach ($items_array as $item) {
        if (empty($item) || strpos($item, ':') === false) continue;
        list($type, $id) = explode(':', $item);
        
        // Map type to table
        $table_map = [
            'computer' => 'computer_inventory',
            'computer_lab' => 'computer_inventory',
            'kitchen' => 'kitchen_equipment',
            'office' => 'office_equipment',
            'lab' => 'lab_equipment',
            'regular_lab' => 'lab_equipment',
            'general' => 'general_equipment'
        ];
        
        $table = $table_map[$type] ?? null;
        if ($table && isset($selected_by_table[$table])) {
            $selected_by_table[$table][] = intval($id);
        }
    }
}

/**
 * Build individual queries for each table with proper WHERE clauses
 */
$queries = [];

// Computer Inventory - ADDED unit field
$computer_where = ["(is_condemned = 0 OR is_condemned IS NULL)"];

if ($par_source === 'selected') {
    if (!empty($selected_by_table['computer_inventory'])) {
        $ids = implode(',', $selected_by_table['computer_inventory']);
        $computer_where[] = "id IN ($ids)";
    } else {
        // If no computer items selected, add a condition that returns no results
        $computer_where[] = "1=0";
    }
} elseif ($par_source === 'accountable' && !empty($accountable_person)) {
    $computer_where[] = "remarks = " . $db->quote($accountable_person);
}
// For 'all' source, no additional conditions needed

$computer_where_string = implode(' AND ', $computer_where);

if ($par_source !== 'selected' || !empty($selected_by_table['computer_inventory'])) {
    $queries[] = "
        SELECT 
            computer_set_description as description, 
            property_no as real_property_no, 
            cost as amount, 
            unit,  -- Added unit field
            article, 
            'Computer' as cat, 
            serial_number_monitor as sn_m, 
            serial_number_system as sn_s,
            remarks as accountable_person
        FROM computer_inventory 
        WHERE $computer_where_string";
}

// Kitchen Equipment - ADDED unit field
$kitchen_where = ["(is_condemned = 0 OR is_condemned IS NULL)"];

if ($par_source === 'selected') {
    if (!empty($selected_by_table['kitchen_equipment'])) {
        $ids = implode(',', $selected_by_table['kitchen_equipment']);
        $kitchen_where[] = "id IN ($ids)";
    } else {
        $kitchen_where[] = "1=0";
    }
} elseif ($par_source === 'accountable' && !empty($accountable_person)) {
    $kitchen_where[] = "remarks = " . $db->quote($accountable_person);
}

$kitchen_where_string = implode(' AND ', $kitchen_where);

if ($par_source !== 'selected' || !empty($selected_by_table['kitchen_equipment'])) {
    $queries[] = "
        SELECT 
            equipment_name as description, 
            property_no as real_property_no, 
            cost as amount, 
            unit,  -- Added unit field
            NULL as article, 
            'Kitchen' as cat, 
            NULL as sn_m, 
            NULL as sn_s,
            remarks as accountable_person
        FROM kitchen_equipment 
        WHERE $kitchen_where_string";
}

// Office Equipment - ADDED unit field
$office_where = ["(is_condemned = 0 OR is_condemned IS NULL)"];

if ($par_source === 'selected') {
    if (!empty($selected_by_table['office_equipment'])) {
        $ids = implode(',', $selected_by_table['office_equipment']);
        $office_where[] = "id IN ($ids)";
    } else {
        $office_where[] = "1=0";
    }
} elseif ($par_source === 'accountable' && !empty($accountable_person)) {
    $office_where[] = "remarks = " . $db->quote($accountable_person);
}

$office_where_string = implode(' AND ', $office_where);

if ($par_source !== 'selected' || !empty($selected_by_table['office_equipment'])) {
    $queries[] = "
        SELECT 
            equipment_name as description, 
            property_no as real_property_no, 
            cost as amount, 
            unit,  -- Added unit field
            NULL as article, 
            'Office' as cat, 
            NULL as sn_m, 
            NULL as sn_s,
            remarks as accountable_person
        FROM office_equipment 
        WHERE $office_where_string";
}

// Lab Equipment - ADDED unit field
$lab_where = ["(is_condemned = 0 OR is_condemned IS NULL)"];

if ($par_source === 'selected') {
    if (!empty($selected_by_table['lab_equipment'])) {
        $ids = implode(',', $selected_by_table['lab_equipment']);
        $lab_where[] = "id IN ($ids)";
    } else {
        $lab_where[] = "1=0";
    }
} elseif ($par_source === 'accountable' && !empty($accountable_person)) {
    $lab_where[] = "remarks = " . $db->quote($accountable_person);
}

$lab_where_string = implode(' AND ', $lab_where);

if ($par_source !== 'selected' || !empty($selected_by_table['lab_equipment'])) {
    $queries[] = "
        SELECT 
            equipment_name as description, 
            property_no as real_property_no, 
            cost as amount, 
            unit,  -- Added unit field
            NULL as article, 
            'Lab' as cat, 
            NULL as sn_m, 
            NULL as sn_s,
            remarks as accountable_person
        FROM lab_equipment 
        WHERE $lab_where_string";
}

// General Equipment - ADDED unit field
$general_where = ["(is_condemned = 0 OR is_condemned IS NULL)"];

if ($par_source === 'selected') {
    if (!empty($selected_by_table['general_equipment'])) {
        $ids = implode(',', $selected_by_table['general_equipment']);
        $general_where[] = "id IN ($ids)";
    } else {
        $general_where[] = "1=0";
    }
} elseif ($par_source === 'accountable' && !empty($accountable_person)) {
    $general_where[] = "remarks = " . $db->quote($accountable_person);
}

$general_where_string = implode(' AND ', $general_where);

if ($par_source !== 'selected' || !empty($selected_by_table['general_equipment'])) {
    $queries[] = "
        SELECT 
            article as description, 
            property_no as real_property_no, 
            cost as amount, 
            unit,  -- Added unit field
            article, 
            'General' as cat, 
            NULL as sn_m, 
            NULL as sn_s,
            remarks as accountable_person
        FROM general_equipment 
        WHERE $general_where_string";
}

// Combine all queries with UNION
if (empty($queries)) {
    // If no queries (shouldn't happen, but just in case)
    $items = [];
} else {
    $full_query = implode(" UNION ALL ", $queries) . " ORDER BY real_property_no ASC";
    
    try {
        $stmt = $db->query($full_query);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Log error and show friendly message
        error_log("PAR Generation Error: " . $e->getMessage());
        die("Error generating report: " . $e->getMessage());
    }
}

$total_amount = 0;

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

// Build HTML for PDF
$html = '
<html>
<head>
    <style>
        @page { margin: 0.5in; }
        body { font-family: "Times New Roman", Times, serif; font-size: 11px; color: #000; line-height: 1.1; }
        .header { text-align: center; margin-bottom: 5px; }
        .header h1 { font-size: 14px; margin: 0; font-weight: normal; }
        .header .agency-wrapper { border-top: 1px solid #000; border-bottom: 1px solid #000; padding: 2px 0; margin: 5px auto; width: 50%; }
        .header .agency { font-size: 13px; font-weight: normal; margin: 0; text-transform: uppercase; }
        .main-table { width: 100%; border: 1px solid black; border-collapse: collapse; }
        .main-table th, .main-table td { border: 1px solid black; padding: 4px; }
        .main-table th { font-size: 10px; font-weight: normal; text-align: center; height: 30px; vertical-align: middle; }
        .footer-table { width: 100%; border: 1px solid black; border-collapse: collapse; margin-top: -1px; }
        .footer-table td { border: 1px solid black; width: 50%; vertical-align: top; padding: 0; }
        .sig-container { padding: 5px 8px 5px 8px; font-size: 10px; }
        .sig-row { text-align: center; padding: 5px 0; }
        .black-line { border-bottom: 2px solid black !important; }
        .sig-label { font-size: 9px; display: block; }
        .sig-value { font-weight: normal; font-size: 11px; display: block; margin-bottom: 2px; }
        .total-cell { text-align: center; font-weight: bold; font-size: 11px; }
        .peso { font-family: "DejaVu Sans", sans-serif; }
        .sn-detail { font-size: 8px; color: #333; display: block; margin-top: 2px; }
        .info-badge { background: #f0f0f0; padding: 5px 10px; border-radius: 4px; margin-bottom: 10px; font-size: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>PROPERTY ACKNOWLEDGEMENT RECEIPT</h1>
        <div class="agency-wrapper">
            <p class="agency">CALOOCAN CITY GOVERNMENT</p>
        </div>
        <div style="font-size: 11px;">LGU</div>
    </div>';

// Add filter info
if ($par_source === 'accountable' && !empty($accountable_person)) {
    $html .= '<div class="info-badge">Accountable Person: <strong>' . htmlspecialchars($accountable_person) . '</strong></div>';
} elseif ($par_source === 'selected') {
    $html .= '<div class="info-badge">Showing <strong>' . count($items) . '</strong> selected item(s)</div>';
}

$html .= '
    <table class="main-table">
        <thead>
            <tr>
                <th width="7%">QTY</th>
                <th width="7%">UNIT</th>
                <th width="41%">DESCRIPTION</th>
                <th width="15%">ESTIMATED<br>USEFUL LIFE</th>
                <th width="15%">PROPERTY<br>NO.</th>
                <th width="15%">AMOUNT</th>
            </tr>
        </thead>
        <tbody>';

foreach ($items as $row) {
    $cost = $row['amount'] ?? 0;
    $total_amount += $cost;
    
    $desc = htmlspecialchars($row['description']);
    if ($row['cat'] === 'Computer' && !empty($row['sn_m']) && !empty($row['sn_s'])) {
        $desc .= '<span class="sn-detail">Mntr: '.($row['sn_m']?:'N/A').' | Sys: '.($row['sn_s']?:'N/A').'</span>';
    }

    // Get unit from database, fallback to 'unit' if not set
    $unit = !empty($row['unit']) ? formatUnit($row['unit']) : 'unit';

    $html .= '<tr>
        <td align="center">1</td>
        <td align="center">' . htmlspecialchars($unit) . '</td>
        <td>' . $desc . '</td>
        <td align="center">---</td>
        <td align="center">' . htmlspecialchars($row['real_property_no'] ?? 'N/A') . '</td>
        <td align="right"><span class="peso">₱</span> ' . number_format($cost, 2) . '</td>
    </tr>';
}

// Add empty rows to maintain format (PAR forms typically have 15-20 rows)
$row_count = count($items);
$target_rows = 15;
$remaining = max(0, $target_rows - $row_count);

for ($i = 0; $i < $remaining; $i++) {
    $html .= '<tr><td height="22">&nbsp;</td><td></td><td></td><td></td><td></td><td></td></tr>';
}

$html .= '
            <tr>
                <td colspan="4" class="total-cell" style="text-align: right; padding-right: 10px;">TOTAL</td>
                <td class="total-cell"></td>
                <td align="right" style="font-weight: bold;"><span class="peso">₱</span> ' . number_format($total_amount, 2) . '</td>
            </tr>
        </tbody>
    </table>

    <table class="footer-table">
        <tr>
            <td>
                <div class="sig-container">Received from:</div>
                <div class="sig-row black-line">
                    <span class="sig-value">' . htmlspecialchars($from_name) . '</span>
                    <span class="sig-label">Name</span>
                </div>
                <div class="sig-row black-line">
                    <span class="sig-value">' . htmlspecialchars($from_pos) . '</span>
                    <span class="sig-label">Position</span>
                </div>
                <div class="sig-row">
                    <span class="sig-value">' . date("F d, Y", strtotime($report_date)) . '</span>
                    <span class="sig-label">Date</span>
                </div>
            </td>
            <td>
                <div class="sig-container">Received by:</div>
                <div class="sig-row black-line">
                    <span class="sig-value">' . htmlspecialchars($by_name) . '</span>
                    <span class="sig-label">Name</span>
                </div>
                <div class="sig-row black-line">
                    <span class="sig-value">' . htmlspecialchars($by_pos) . '</span>
                    <span class="sig-label">Position</span>
                </div>
                <div class="sig-row">
                    <span class="sig-value">' . date("F d, Y", strtotime($report_date)) . '</span>
                    <span class="sig-label">Date</span>
                </div>
            </td>
        </tr>
    </table>
</body>
</html>';

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'Times-Roman');
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = "PAR_Report_";
if ($par_source === 'accountable' && !empty($accountable_person)) {
    $filename .= str_replace(' ', '_', $accountable_person) . "_";
} elseif ($par_source === 'selected') {
    $filename .= "Selected_" . count($items) . "items_";
}
$filename .= date('Ymd') . ".pdf";

$dompdf->stream($filename, ["Attachment" => false]);
?>