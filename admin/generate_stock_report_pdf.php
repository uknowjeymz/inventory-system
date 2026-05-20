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

// Get filter parameters
$stock_filter = $_GET['stock_filter'] ?? 'all';
$low_threshold = (int)($_GET['low'] ?? 10);
$critical_threshold = (int)($_GET['critical'] ?? 5);
$sort_by = $_GET['sort'] ?? 'item_name';
$category = $_GET['category'] ?? 'all';

// Build query based on filters
$query = "SELECT * FROM consumables WHERE 1=1";

// Category filter
if ($category !== 'all') {
    $query .= " AND category = " . $db->quote($category);
}

// Stock status filter
if ($stock_filter === 'available') {
    $query .= " AND quantity > $low_threshold";
} elseif ($stock_filter === 'low') {
    $query .= " AND quantity <= $low_threshold AND quantity > $critical_threshold";
} elseif ($stock_filter === 'critical') {
    $query .= " AND quantity <= $critical_threshold";
}

// Sorting
switch($sort_by) {
    case 'category':
        $query .= " ORDER BY category, item_name ASC";
        break;
    case 'quantity_asc':
        $query .= " ORDER BY quantity ASC, item_name ASC";
        break;
    case 'quantity_desc':
        $query .= " ORDER BY quantity DESC, item_name ASC";
        break;
    default:
        $query .= " ORDER BY item_name ASC";
}

$stmt = $db->query($query);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$total_items = count($items);
$low_count = 0;
$critical_count = 0;
$available_count = 0;

foreach ($items as $item) {
    if ($item['quantity'] <= $critical_threshold) {
        $critical_count++;
    } elseif ($item['quantity'] <= $low_threshold) {
        $low_count++;
    } else {
        $available_count++;
    }
}

// Function to handle local images
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

// Build filter description
$filter_desc = '';
if ($category !== 'all') {
    $filter_desc .= "Category: " . htmlspecialchars($category) . " | ";
}
if ($stock_filter === 'available') {
    $filter_desc .= "Available Stock (> $low_threshold)";
} elseif ($stock_filter === 'low') {
    $filter_desc .= "Low Stock (≤ $low_threshold)";
} elseif ($stock_filter === 'critical') {
    $filter_desc .= "Critical Stock (≤ $critical_threshold)";
} else {
    $filter_desc .= "All Items";
}

$html = '
<html>
<head>
    <style>
        @page { margin: 100px 25px; }
        body { font-family: "Helvetica", sans-serif; font-size: 11px; color: #333; line-height: 1.4; }
        
        /* Official Header Style */
        .header { position: fixed; top: -85px; left: 0px; right: 0px; height: 100px; }
        .header table { width: auto; margin: 0 auto; border-collapse: collapse; }
        .header .logo-ucc { padding-right: 15px; }
        .header .header-text { text-align: center; }
        .header h1 { margin: 0; font-size: 18px; color: #000; letter-spacing: 1px; }
        .report-title { margin-top: 5px; font-weight: bold; text-decoration: underline; font-size: 12px; text-transform: uppercase; color: #0d6efd; }
        .filter-info { font-size: 9px; color: #666; margin-top: 3px; }

        /* Footer Style */
        .footer { position: fixed; bottom: -60px; left: 0px; right: 0px; height: 60px; border-top: 1px solid #ccc; padding-top: 10px; }
        .footer table { width: 100%; border-collapse: collapse; }
        .logo-caloocan img { width: 130px; }

        /* Stats Box */
        .stats-box { 
            background: #f8f9fa; 
            border: 1px solid #dee2e6; 
            border-radius: 5px; 
            padding: 10px; 
            margin: 15px 0;
            font-size: 10px;
        }
        .stats-box table { width: 100%; }
        .stats-box td { padding: 3px; }

        /* Table Style */
        .stock-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .stock-table th { background: #0d6efd; color: #fff; padding: 8px; text-align: left; text-transform: uppercase; font-size: 10px; border: 1px solid #0d6efd; }
        .stock-table td { padding: 8px; border: 1px solid #ddd; }
        .stock-table tr:nth-child(even) { background-color: #f8f9fa; }
        
        .status-critical { 
            background-color: #f8d7da !important;
            color: #721c24 !important;
            font-weight: bold;
        }
        .status-low { 
            background-color: #fff3cd !important;
            color: #856404 !important;
        }
        .status-available { 
            background-color: #d4edda !important;
            color: #155724 !important;
        }

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
                    <p style="margin:0;">CONSUMABLE INVENTORY SYSTEM</p>
                    <p class="report-title">Stock Status Report</p>
                    <div class="filter-info">Filter: ' . $filter_desc . '</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">
        <table width="100%">
            <tr>
                <td width="290%" align="center">Generated: ' . date("M d, Y - h:i A") . ' | Total Items: ' . $total_items . '</td>
                <td width="33%" align="right">
                    <div class="logo-caloocan">' . ($caloocanLogo ? '<img src="' . $caloocanLogo . '">' : '') . '</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="stats-box">
        <table>
            <tr>
                <td><strong>Summary:</strong></td>
                <td>Total Items: ' . $total_items . '</td>
                <td>Available: ' . $available_count . '</td>
                <td>Low Stock: ' . $low_count . '</td>
                <td>Critical: ' . $critical_count . '</td>
            </tr>
        </table>
    </div>

    <table class="stock-table">
        <thead>
            <tr>
                <th width="12%">ID Code</th>
                <th width="25%">Item Name</th>
                <th width="18%">Category</th>
                <th width="15%">Brand</th>
                <th width="15%" style="text-align: center;">Quantity Level</th>
                <th width="15%" style="text-align: center;">Status</th>
            </tr>
        </thead>
        <tbody>';

        if (empty($items)) {
            $html .= '<tr><td colspan="6" style="text-align: center; padding: 20px;">No items found matching the selected filters.</td></tr>';
        } else {
            foreach ($items as $item) {
                // Determine status class
                if ($item['quantity'] <= $critical_threshold) {
                    $status_class = 'status-critical';
                    $status_text = 'CRITICAL';
                } elseif ($item['quantity'] <= $low_threshold) {
                    $status_class = 'status-low';
                    $status_text = 'LOW STOCK';
                } else {
                    $status_class = 'status-available';
                    $status_text = 'Available';
                }
                
                $html .= '<tr class="' . $status_class . '">
                    <td>' . htmlspecialchars($item['identification'] ?: 'N/A') . '</td>
                    <td><strong>' . htmlspecialchars($item['item_name']) . '</strong></td>
                    <td>' . htmlspecialchars($item['category'] ?: '-') . '</td>
                    <td>' . htmlspecialchars($item['brand'] ?: '-') . '</td>
                    <td align="center"><strong>' . $item['quantity'] . '</strong> ' . htmlspecialchars($item['unit']) . '</td>
                    <td align="center"><strong>' . $status_text . '</strong></td>
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
                <small>Generated By</small>
            </div>
        </div>
        <div class="sig-container" style="float: right;">
            <div style="height: 40px;"></div>
            <div class="sig-box">
                <strong>MARVIN Z. GERVACIO</strong><br>
                <small>Supply Officer</small>
            </div>
        </div>
    </div>
    
    <div style="text-align: center; margin-top: 20px; font-size: 8px; color: #999;">
        <i>Low Stock: ≤ ' . $low_threshold . ' units | Critical: ≤ ' . $critical_threshold . ' units</i>
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

// Create filename with filter info
$filename = "Stock_Report_" . date('Y-m-d');
if ($category !== 'all') $filename .= "_" . preg_replace('/[^a-zA-Z0-9]/', '', $category);
if ($stock_filter !== 'all') $filename .= "_" . $stock_filter;
$filename .= ".pdf";

$dompdf->stream($filename, ["Attachment" => false]);