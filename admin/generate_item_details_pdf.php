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

$id = $_GET['id'] ?? 0;
$type = $_GET['type'] ?? '';

// 1. Fetch Item Details
$table_name = ($type === 'computer_lab' || $type === 'computer') ? 'computer_inventory' : 
              (($type === 'kitchen') ? 'kitchen_equipment' : 
              (($type === 'office') ? 'office_equipment' : 
              (($type === 'regular_lab') ? 'lab_equipment' : 'general_equipment')));

// Use * to ensure we get type-specific columns like 'property_no' or 'calibration_date'
$stmt = $db->prepare("SELECT e.*, l.location_name FROM {$table_name} e LEFT JOIN locations l ON e.location_id = l.id WHERE e.id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item) die("Item not found.");

// 2. Fetch CAMPUS TRANSFER History from transfer_history table
$transfer_stmt = $db->prepare("SELECT * FROM transfer_history 
                               WHERE equipment_ids LIKE ? 
                               ORDER BY transfer_date DESC");
$transfer_stmt->execute(['%' . $id . '%']);
$transfers = $transfer_stmt->fetchAll(PDO::FETCH_ASSOC);

$html = '
<html>
<head>
    <style>
        body { font-family: "Helvetica", sans-serif; font-size: 10px; color: #333; line-height: 1.2; }
        .header { text-align: center; border-bottom: 2px solid #008543; padding-bottom: 10px; margin-bottom: 15px; }
        .section-title { background: #f0f0f0; padding: 4px 8px; font-weight: bold; border-left: 4px solid #008543; margin: 10px 0 5px 0; text-transform: uppercase; font-size: 11px; }
        .info-table, .history-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .info-table td, .history-table td, .history-table th { padding: 6px; border: 1px solid #ddd; }
        .label { font-weight: bold; background: #fafafa; width: 35%; }
        .history-table th { background: #008543; color: white; text-align: left; }
        .badge-released { color: #2e7d32; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <h2 style="margin:0;">UNIVERSITY OF CALOOCAN CITY</h2>
        <p style="margin:0; font-weight: bold; color: #666;">Individual Equipment Life-Cycle Report</p>
    </div>

    <div class="section-title">Current Specifications</div>
    <table class="info-table">
        <tr><td class="label">Property Number</td><td>' . htmlspecialchars($item['property_no'] ?? $item['item_number']) . '</td></tr>
        <tr><td class="label">Item Description</td><td>' . htmlspecialchars($item['computer_set_description'] ?? $item['equipment_name'] ?? $item['article']) . '</td></tr>
        <tr><td class="label">Serial Monitor</td><td>' . htmlspecialchars($item['serial_number_monitor'] ?? 'N/A') . '</td></tr>
        <tr><td class="label">Serial System</td><td>' . htmlspecialchars($item['serial_number_system'] ?? 'N/A') . '</td></tr>
        <tr><td class="label">Current Assigned Room</td><td style="background: #fff3cd;"><strong>' . htmlspecialchars($item['location_name'] ?? 'Storage/Unassigned') . '</strong></td></tr>
        <tr><td class="label">Accountable Person</td><td>' . htmlspecialchars($item['remarks'] ?? 'None') . '</td></tr>
    </table>';

// SECTION: CAMPUS TRANSFER HISTORY (from transfer_history table)
$html .= '<div class="section-title">Campus Transfer History</div>';
if (!empty($transfers)) {
    $html .= '<table class="history-table">
        <thead>
            <tr>
                <th>Transfer Date</th>
                <th>From Campus</th>
                <th>To Campus</th>
                <th>Previous Accountable</th>
                <th>New Accountable</th>
            </tr>
        </thead>
        <tbody>';
    foreach ($transfers as $transfer) {
        $html .= '<tr>
            <td>' . date("M d, Y", strtotime($transfer['transfer_date'])) . '</td>
            <td><span style="color: #D32F2F;">' . htmlspecialchars($transfer['from_campus']) . '</span></td>
            <td><span style="color: #2E7D32;">' . htmlspecialchars($transfer['to_campus']) . '</span></td>
            <td>' . htmlspecialchars($transfer['previous_accountable'] ?? 'N/A') . '</td>
            <td><strong>' . htmlspecialchars($transfer['new_accountable']) . '</strong></td>
        </tr>';
    }
    $html .= '</tbody></table>';
} else {
    $html .= '<p style="color: #888;"><i>No campus transfers recorded for this item.</i></p>';
}

// SECTION: ROOM ASSIGNMENT HISTORY (from assignment_history table)
$assign_stmt = $db->prepare("SELECT ah.*, l.location_name 
                             FROM assignment_history ah 
                             INNER JOIN locations l ON ah.location_id = l.id 
                             WHERE ah.computer_id = ? 
                             ORDER BY ah.assigned_date DESC");
$assign_stmt->execute([$id]);
$assignments = $assign_stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($assignments)) {
    $html .= '<div class="section-title">Room Assignment History</div>
    <table class="history-table">
        <thead>
            <tr>
                <th>Date Assigned</th>
                <th>Room / Location</th>
                <th>Status</th>
                <th>Notes</th>
            </tr>
        </thead>
        <tbody>';
    foreach ($assignments as $a) {
        $html .= '<tr>
            <td>' . date("M d, Y - h:i A", strtotime($a['assigned_date'])) . '</td>
            <td><strong>' . htmlspecialchars($a['location_name']) . '</strong></td>
            <td>' . ucfirst($a['status']) . '</td>
            <td>' . htmlspecialchars($a['notes'] ?? '') . '</td>
        </tr>';
    }
    $html .= '</tbody></table>';
}

$html .= '
    <div style="margin-top: 30px; text-align: right; color: #777;">
        <p>This document is an electronically generated record from the UCC IMS.<br>
        Date Generated: ' . date("F d, Y - h:i A") . '</p>
    </div>
</body>
</html>';

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("Item_Report_" . $id . ".pdf", ["Attachment" => false]);
?>