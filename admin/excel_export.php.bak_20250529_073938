<?php
session_start();
require_once 'db_connection.php';
require_once 'vendor/autoload.php'; // PhpSpreadsheet kütüphanesini dahil et

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Kullanıcı oturum kontrolü
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: index.php");
    exit;
}

// Formdan gelen parametreleri al
$start_date = isset($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_POST['end_date']) ? $_POST['end_date'] : date('Y-m-d');
$magaza_id = isset($_POST['magaza_id']) && !empty($_POST['magaza_id']) ? intval($_POST['magaza_id']) : null;

// Veritabanından satış verilerini çek
$sql = "
    SELECT 
        DATE_FORMAT(sf.fatura_tarihi, '%d.%m.%Y') as tarih,
        sf.fatura_no,
        m.ad as magaza_adi,
        us.ad as urun_adi,
        sfd.miktar,
        sfd.birim_fiyat,
        sfd.toplam_tutar
    FROM 
        satis_faturalari sf
    JOIN 
        satis_fatura_detay sfd ON sf.id = sfd.fatura_id
    JOIN 
        urun_stok us ON sfd.urun_id = us.id
    JOIN 
        magazalar m ON sf.magaza = m.id
    WHERE 
        DATE(sf.fatura_tarihi) BETWEEN :start_date AND :end_date
";

if ($magaza_id) {
    $sql .= " AND sf.magaza = :magaza_id";
}

$sql .= " ORDER BY sf.fatura_tarihi DESC";

$stmt = $conn->prepare($sql);
$stmt->bindParam(':start_date', $start_date);
$stmt->bindParam(':end_date', $end_date);

if ($magaza_id) {
    $stmt->bindParam(':magaza_id', $magaza_id);
}

$stmt->execute();
$sales_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Mağaza adını al (filtre için)
$magaza_adi = "Tüm Mağazalar";
if ($magaza_id) {
    $magaza_query = "SELECT ad FROM magazalar WHERE id = :id";
    $stmt = $conn->prepare($magaza_query);
    $stmt->bindParam(':id', $magaza_id);
    $stmt->execute();
    $magaza = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($magaza) {
        $magaza_adi = $magaza['ad'];
    }
}

// Toplam satış tutarını hesapla
$total_sales = 0;
foreach ($sales_data as $sale) {
    $total_sales += $sale['toplam_tutar'];
}

// Yeni bir Excel çalışma kitabı oluştur
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Satış Raporu');

// Stil tanımlamaları
$headerStyle = [
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF'],
    ],
    'fill' => [
        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
        'startColor' => ['rgb' => '4472C4']
    ],
    'alignment' => [
        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
    ],
];

$titleStyle = [
    'font' => [
        'bold' => true,
        'size' => 14,
    ],
    'alignment' => [
        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
    ],
];

$centerStyle = [
    'alignment' => [
        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
    ],
];

$rightStyle = [
    'alignment' => [
        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT,
    ],
];

$borderStyle = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
            'color' => ['rgb' => '000000'],
        ],
    ],
];

$totalStyle = [
    'font' => [
        'bold' => true,
    ],
    'fill' => [
        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'E2EFDA']
    ],
];

// Başlık ekle
$sheet->setCellValue('A1', 'İnciPOS Satış Raporu');
$sheet->mergeCells('A1:G1');
$sheet->getStyle('A1')->applyFromArray($titleStyle);

// Rapor bilgilerini ekle
$sheet->setCellValue('A2', 'Tarih Aralığı:');
$sheet->setCellValue('B2', date('d.m.Y', strtotime($start_date)) . ' - ' . date('d.m.Y', strtotime($end_date)));
$sheet->setCellValue('A3', 'Mağaza:');
$sheet->setCellValue('B3', $magaza_adi);
$sheet->setCellValue('A4', 'Toplam Satış:');
$sheet->setCellValue('B4', number_format($total_sales, 2, ',', '.') . ' ₺');

// Kolon başlıklarını ayarla
$sheet->setCellValue('A6', 'Tarih');
$sheet->setCellValue('B6', 'Fatura No');
$sheet->setCellValue('C6', 'Mağaza');
$sheet->setCellValue('D6', 'Ürün');
$sheet->setCellValue('E6', 'Miktar');
$sheet->setCellValue('F6', 'Birim Fiyat');
$sheet->setCellValue('G6', 'Toplam');

// Başlık stilini uygula
$sheet->getStyle('A6:G6')->applyFromArray($headerStyle);

// Verileri doldur
$row = 7;
foreach ($sales_data as $data) {
    $sheet->setCellValue('A' . $row, $data['tarih']);
    $sheet->setCellValue('B' . $row, $data['fatura_no']);
    $sheet->setCellValue('C' . $row, $data['magaza_adi']);
    $sheet->setCellValue('D' . $row, $data['urun_adi']);
    $sheet->setCellValue('E' . $row, $data['miktar']);
    $sheet->setCellValue('F' . $row, number_format($data['birim_fiyat'], 2, ',', '.') . ' ₺');
    $sheet->setCellValue('G' . $row, number_format($data['toplam_tutar'], 2, ',', '.') . ' ₺');
    
    $row++;
}

// Toplam satır ekle
$lastRow = $row;
$sheet->setCellValue('A' . $lastRow, 'TOPLAM');
$sheet->mergeCells('A' . $lastRow . ':F' . $lastRow);
$sheet->setCellValue('G' . $lastRow, number_format($total_sales, 2, ',', '.') . ' ₺');
$sheet->getStyle('A' . $lastRow . ':G' . $lastRow)->applyFromArray($totalStyle);

// Sütun genişliklerini ayarla
$sheet->getColumnDimension('A')->setWidth(15);
$sheet->getColumnDimension('B')->setWidth(15);
$sheet->getColumnDimension('C')->setWidth(20);
$sheet->getColumnDimension('D')->setWidth(40);
$sheet->getColumnDimension('E')->setWidth(10);
$sheet->getColumnDimension('F')->setWidth(15);
$sheet->getColumnDimension('G')->setWidth(15);

// Stil ayarlamaları
$sheet->getStyle('A7:A' . ($lastRow-1))->applyFromArray($centerStyle);
$sheet->getStyle('B7:B' . ($lastRow-1))->applyFromArray($centerStyle);
$sheet->getStyle('E7:E' . ($lastRow-1))->applyFromArray($centerStyle);
$sheet->getStyle('F7:G' . ($lastRow))->applyFromArray($rightStyle);
$sheet->getStyle('A6:G' . $lastRow)->applyFromArray($borderStyle);
$sheet->getStyle('A' . $lastRow)->applyFromArray($centerStyle);

// Excel dosyasını oluştur ve indir
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="InciPOS_Satis_Raporu_' . date('d-m-Y') . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;