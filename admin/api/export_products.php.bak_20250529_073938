<?php
session_start();
require_once '../db_connection.php';
require '../vendor/autoload.php'; // PhpSpreadsheet için

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

// Yetki kontrolü
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    die(json_encode(['success' => false, 'message' => 'Yetkisiz erişim']));
}

try {
    // Seçili ürünleri al
    $ids = isset($_GET['ids']) ? explode(',', $_GET['ids']) : [];
    
    // Sorgu için SQL hazırlama
    if (!empty($ids)) {
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        $sql = "SELECT 
                    us.*, 
                    d.ad as departman, 
                    ag.ad as ana_grup, 
                    alg.ad as alt_grup, 
                    b.ad as birim
                FROM urun_stok us
                LEFT JOIN departmanlar d ON us.departman_id = d.id
                LEFT JOIN ana_gruplar ag ON us.ana_grup_id = ag.id
                LEFT JOIN alt_gruplar alg ON us.alt_grup_id = alg.id
                LEFT JOIN birimler b ON us.birim_id = b.id
                WHERE us.id IN ($placeholders)";
                
        $stmt = $conn->prepare($sql);
        $stmt->execute($ids);
    } else {
        // Tüm ürünleri al
        $sql = "SELECT 
                    us.*, 
                    d.ad as departman, 
                    ag.ad as ana_grup, 
                    alg.ad as alt_grup, 
                    b.ad as birim
                FROM urun_stok us
                LEFT JOIN departmanlar d ON us.departman_id = d.id
                LEFT JOIN ana_gruplar ag ON us.ana_grup_id = ag.id
                LEFT JOIN alt_gruplar alg ON us.alt_grup_id = alg.id
                LEFT JOIN birimler b ON us.birim_id = b.id
                ORDER BY us.id DESC";
                
        $stmt = $conn->query($sql);
    }

    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($products)) {
        die('Hiç ürün bulunamadı');
    }

    // Excel dosyası oluştur
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle("Ürün Listesi");

    // Başlıkları ekle
    $headers = array_keys($products[0]);
    $col = 1;
    foreach ($headers as $header) {
        // Burada setCellValueByColumnAndRow yerine setCellValue kullanıyoruz
        $colLetter = Coordinate::stringFromColumnIndex($col);
        $sheet->setCellValue($colLetter . '1', $header);
        $col++;
    }

    // Verileri ekle
    $row = 2;
    foreach ($products as $product) {
        $col = 1;
        foreach ($product as $value) {
            $colLetter = Coordinate::stringFromColumnIndex($col);
            $sheet->setCellValue($colLetter . $row, $value);
            $col++;
        }
        $row++;
    }

    // Başlık stilleri
    $lastColumn = Coordinate::stringFromColumnIndex(count($headers));
    $headerRange = 'A1:' . $lastColumn . '1';
    
    $sheet->getStyle($headerRange)->getFont()->setBold(true);
    $sheet->getStyle($headerRange)->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setARGB('FF0070C0');
    $sheet->getStyle($headerRange)->getFont()->getColor()->setARGB('FFFFFFFF');

    // Sütun genişliklerini otomatik ayarla
    foreach (range('A', $lastColumn) as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Dosyayı indir
    $filename = "urun_listesi_" . date('Y-m-d_H-i-s') . ".xlsx";
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    die("Hata: " . $e->getMessage());
}