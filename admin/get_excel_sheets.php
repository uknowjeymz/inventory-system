<?php
require_once '../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

if (isset($_FILES['file'])) {
    try {
        $file = $_FILES['file']['tmp_name'];
        $spreadsheet = IOFactory::load($file);
        echo json_encode($spreadsheet->getSheetNames());
    } catch (Exception $e) {
        echo json_encode([]);
    }
}