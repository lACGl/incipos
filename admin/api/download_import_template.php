<?php
require_once '../session_manager.php'; // Otomatik eklendi
secure_session_start();

// Kullanıcı giriş kontrolü
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: ../index.php");
    exit;
}

// Gerekli kütüphaneleri yükle
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

// Yeni bir Spreadsheet oluştur
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Ürün Listesi');

// Sütun başlıklarını ayarla
$headers = [
    'Kod', 'Barkod', 'Ürün Adı', 'Web ID', 'Alış Fiyatı', 'Satış Fiyatı', 'Stok Miktarı', 
    'KDV Oranı', 'Yıl', 'Resim Yolu', 'Departman', 'Birim', 'Ana Grup', 'Alt Grup', 'Durum'
];

// Başlıkları yaz
foreach ($headers as $colIndex => $header) {
    $column = chr(65 + $colIndex); // A, B, C, ...
    $sheet->setCellValue($column . '1', $header);
}

// Başlık satırını biçimlendir
$sheet->getStyle('A1:O1')->applyFromArray([
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF'],
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '4472C4'],
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => '000000'],
        ],
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
    ],
]);

// Örnek veriler
$sampleData = [
    ['U1001', '8680123456789', 'Örnek Ürün 1', '1001', 80, 100, 10, 18, 2023, 'products/phone1.jpg', 'Elektronik', 'Adet', 'Telefonlar', 'Akıllı Telefonlar', 'aktif'],
    ['U1002', '8680123456790', 'Örnek Ürün 2', '1002', 120, 150, 5, 8, 2024, 'products/food1.jpg', 'Mutfak', 'Kg', 'Gıda', 'Süt Ürünleri', 'aktif'],
    ['U1003', '8680123456791', 'Örnek Ürün 3', '1003', 200, 250, 8, 18, 2023, 'products/cosmetic1.jpg', 'Kozmetik', 'Adet', 'Cilt Bakım', 'Kremler', 'pasif'],
];

// Örnek verileri yaz
foreach ($sampleData as $rowIndex => $rowData) {
    foreach ($rowData as $colIndex => $value) {
        $column = chr(65 + $colIndex); // A, B, C, ...
        $sheet->setCellValue($column . ($rowIndex + 2), $value);
    }
}

// Sütun genişliklerini ayarla
$sheet->getColumnDimension('A')->setWidth(12);  // Kod
$sheet->getColumnDimension('B')->setWidth(15);  // Barkod
$sheet->getColumnDimension('C')->setWidth(30);  // Ürün Adı
$sheet->getColumnDimension('D')->setWidth(12);  // Web ID
$sheet->getColumnDimension('E')->setWidth(12);  // Alış Fiyatı
$sheet->getColumnDimension('F')->setWidth(12);  // Satış Fiyatı
$sheet->getColumnDimension('G')->setWidth(12);  // Stok Miktarı
$sheet->getColumnDimension('H')->setWidth(12);  // KDV Oranı
$sheet->getColumnDimension('I')->setWidth(10);  // Yıl
$sheet->getColumnDimension('J')->setWidth(25);  // Resim Yolu
$sheet->getColumnDimension('K')->setWidth(15);  // Departman
$sheet->getColumnDimension('L')->setWidth(12);  // Birim
$sheet->getColumnDimension('M')->setWidth(15);  // Ana Grup
$sheet->getColumnDimension('N')->setWidth(15);  // Alt Grup
$sheet->getColumnDimension('O')->setWidth(10);  // Durum

// Örnek satırları biçimlendir
$sheet->getStyle('A2:O4')->applyFromArray([
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => 'CCCCCC'],
        ],
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'F2F2F2'],
    ],
]);

// Talimatlar için yeni bir sayfa ekle
$spreadsheet->createSheet();
$spreadsheet->setActiveSheetIndex(1);
$instructionSheet = $spreadsheet->getActiveSheet();
$instructionSheet->setTitle('Talimatlar');

// Talimatları ekle
$instructions = [
    'Ürün İçe Aktarma Talimatları',
    '',
    '1. Bu şablon ürünleri toplu olarak içe aktarmak için kullanılır.',
    '2. Zorunlu alanlar: Barkod ve Ürün Adı. Bu alanlar boş bırakılamaz.',
    '3. İlişkisel veriler (Departman, Birim, Ana Grup, Alt Grup) için ID değil isim kullanın.',
    '4. Sistemde olmayan bir isim kullanırsanız, o isimde yeni bir kayıt otomatik olarak oluşturulur.',
    '5. Alt grupların doğru eşleşmesi için ilgili Ana Grup belirtilmelidir.',
    '6. Durum alanı için "aktif" veya "pasif" değerlerini kullanın. Boş bırakılırsa "aktif" kabul edilir.',
    '7. Stok Miktarı içe aktarma sırasında ana depoya eklenir.',
    '8. Alış Fiyatı ve Satış Fiyatı virgül yerine nokta ile ayrılmalıdır (örn: 10.99).',
    '9. Web ID, Yıl ve Resim Yolu opsiyonel alanlardır, gerektiğinde doldurulabilir.',
    '',
    'Sütun Açıklamaları:',
    '',
    'Kod: Ürün kodu (opsiyonel)',
    'Barkod: Ürün barkodu (zorunlu)',
    'Ürün Adı: Ürünün tam adı (zorunlu)',
    'Web ID: Web sitesinde kullanılacak ID (opsiyonel)',
    'Alış Fiyatı: Ürünün alış fiyatı',
    'Satış Fiyatı: Ürünün satış fiyatı',
    'Stok Miktarı: İçe aktarma sırasında ana depoya eklenecek stok miktarı',
    'KDV Oranı: Ürünün KDV oranı (ör: 18, 8, 1)',
    'Yıl: Ürünün üretim yılı (opsiyonel)',
    'Resim Yolu: Ürün resminin sunucudaki yolu (opsiyonel)',
    'Departman: Ürünün departmanı (ör: Elektronik, Gıda)',
    'Birim: Ürünün birimi (ör: Adet, Kg, Litre)',
    'Ana Grup: Ürünün ana grubu (ör: Telefonlar, Süt Ürünleri)',
    'Alt Grup: Ürünün alt grubu - Ana gruba bağlı olmalıdır (ör: Akıllı Telefonlar)',
    'Durum: Ürünün durumu ("aktif" veya "pasif")',
];

// Talimatları yaz ve biçimlendir
foreach ($instructions as $index => $instruction) {
    $instructionSheet->setCellValue('A' . ($index + 1), $instruction);
    
    // Başlıkları biçimlendir
    if ($index === 0 || ($index >= 12 && $instruction && !strstr($instruction, ':'))) {
        $instructionSheet->getStyle('A' . ($index + 1))->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 14,
            ],
        ]);
    }
    
    // Alt başlıkları biçimlendir
    if (strstr($instruction, ':') && $index >= 13) {
        $instructionSheet->getStyle('A' . ($index + 1))->applyFromArray([
            'font' => [
                'bold' => true,
            ],
        ]);
    }
}

// Sütun genişliğini ayarla
$instructionSheet->getColumnDimension('A')->setWidth(100);
$instructionSheet->getRowDimension(1)->setRowHeight(30);

// İlk sayfaya geri dön
$spreadsheet->setActiveSheetIndex(0);

// Dosyayı indir
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="urun_import_sablonu.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');