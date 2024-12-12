<?php
session_start();
require_once 'db_connection.php';
require 'vendor/autoload.php'; // PhpSpreadsheet için

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Yetki kontrolü
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    die('Yetkisiz erişim');
}

try {
    // Seçili ürünleri al
    $ids = isset($_GET['ids']) ? explode(',', $_GET['ids']) : [];
    
    if (!empty($ids)) {
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        $stmt = $conn->prepare("SELECT * FROM urun_stok WHERE id IN ($placeholders)");
        $stmt->execute($ids);
    } else {
        $stmt = $conn->query("SELECT * FROM urun_stok");
    }

    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Excel dosyası oluştur
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Başlıkları ekle
    $columns = array_keys($products[0]);
    $col = 1;
    foreach ($columns as $column) {
        $sheet->setCellValueByColumnAndRow($col, 1, $column);
        $col++;
    }

    // Verileri ekle
    $row = 2;
    foreach ($products as $product) {
        $col = 1;
        foreach ($product as $value) {
            $sheet->setCellValueByColumnAndRow($col, $row, $value);
            $col++;
        }
        $row++;
    }

    // Başlıkları kalın yap
    $sheet->getStyle('1:1')->getFont()->setBold(true);

    // Sütun genişliklerini otomatik ayarla
    foreach (range('A', $sheet->getHighestColumn()) as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Dosyayı indir
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="urun_listesi_' . date('Y-m-d_H-i-s') . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');

} catch (Exception $e) {
    die("Hata: " . $e->getMessage());
}
