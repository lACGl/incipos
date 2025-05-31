<?php
/**
 * Kar Marjı Analizi - Excel Export
 * Kar marjı analizini Excel formatında dışa aktarır
 */

// Session yönetimi ve yetkisiz erişim kontrolü
require_once '../../session_manager.php';

// Session kontrolü
checkUserSession();

// Veritabanı bağlantısı
require_once '../../db_connection.php';

// Export isteği kontrolü
if (!isset($_GET['export']) || $_GET['export'] != '1') {
    header("Location: profit_analyzer.php");
    exit;
}

// Filtre parametreleri
$departman_id = isset($_GET['departman_id']) ? intval($_GET['departman_id']) : 0;
$ana_grup_id = isset($_GET['ana_grup_id']) ? intval($_GET['ana_grup_id']) : 0;
$alt_grup_id = isset($_GET['alt_grup_id']) ? intval($_GET['alt_grup_id']) : 0;
$min_kar = isset($_GET['min_kar']) && $_GET['min_kar'] !== '' ? floatval($_GET['min_kar']) : null;
$max_kar = isset($_GET['max_kar']) && $_GET['max_kar'] !== '' ? floatval($_GET['max_kar']) : null;
$sirala = isset($_GET['sirala']) ? $_GET['sirala'] : 'kar_desc';
$goster = isset($_GET['goster']) ? $_GET['goster'] : 'all';

// SQL sorgusu oluştur (limit olmadan)
$sql = "
    SELECT 
        us.id, 
        us.kod,
        us.barkod, 
        us.ad, 
        us.alis_fiyati, 
        us.satis_fiyati,
        d.ad as departman_adi,
        ag.ad as ana_grup_adi,
        alg.ad as alt_grup_adi
    FROM urun_stok us
    LEFT JOIN departmanlar d ON us.departman_id = d.id
    LEFT JOIN ana_gruplar ag ON us.ana_grup_id = ag.id
    LEFT JOIN alt_gruplar alg ON us.alt_grup_id = alg.id
    WHERE us.durum = 'aktif' AND us.alis_fiyati > 0
";
$params = [];

// Filtre koşullarını ekle
if ($departman_id > 0) {
    $sql .= " AND us.departman_id = :departman_id";
    $params[':departman_id'] = $departman_id;
}

if ($ana_grup_id > 0) {
    $sql .= " AND us.ana_grup_id = :ana_grup_id";
    $params[':ana_grup_id'] = $ana_grup_id;
}

if ($alt_grup_id > 0) {
    $sql .= " AND us.alt_grup_id = :alt_grup_id";
    $params[':alt_grup_id'] = $alt_grup_id;
}

// Kar marjı filtresi
if ($min_kar !== null) {
    $sql .= " AND ((us.satis_fiyati - us.alis_fiyati) / us.alis_fiyati * 100) >= :min_kar";
    $params[':min_kar'] = $min_kar;
}

if ($max_kar !== null) {
    $sql .= " AND ((us.satis_fiyati - us.alis_fiyati) / us.alis_fiyati * 100) <= :max_kar";
    $params[':max_kar'] = $max_kar;
}

// Belirli ürünleri gösterme seçeneği
if ($goster == 'low_profit') {
    $sql .= " AND ((us.satis_fiyati - us.alis_fiyati) / us.alis_fiyati * 100) < 15";
} elseif ($goster == 'negative_profit') {
    $sql .= " AND us.satis_fiyati <= us.alis_fiyati";
} elseif ($goster == 'high_profit') {
    $sql .= " AND ((us.satis_fiyati - us.alis_fiyati) / us.alis_fiyati * 100) > 50";
}

// Sıralama ekle
switch ($sirala) {
    case 'ad_asc':
        $sql .= " ORDER BY us.ad ASC";
        break;
    case 'ad_desc':
        $sql .= " ORDER BY us.ad DESC";
        break;
    case 'alis_asc':
        $sql .= " ORDER BY us.alis_fiyati ASC";
        break;
    case 'alis_desc':
        $sql .= " ORDER BY us.alis_fiyati DESC";
        break;
    case 'satis_asc':
        $sql .= " ORDER BY us.satis_fiyati ASC";
        break;
    case 'satis_desc':
        $sql .= " ORDER BY us.satis_fiyati DESC";
        break;
    case 'kar_asc':
        $sql .= " ORDER BY ((us.satis_fiyati - us.alis_fiyati) / us.alis_fiyati * 100) ASC";
        break;
    case 'kar_desc':
        $sql .= " ORDER BY ((us.satis_fiyati - us.alis_fiyati) / us.alis_fiyati * 100) DESC";
        break;
    default:
        $sql .= " ORDER BY ((us.satis_fiyati - us.alis_fiyati) / us.alis_fiyati * 100) DESC";
        break;
}

// Verileri getir (limit olmadan tüm veriler)
$stmt = $conn->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$urunler = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Excel dosya adını belirle
$dosya_adi = "Kar_Marji_Analizi_" . date('Y-m-d_H-i-s') . ".csv";

// CSV başlıklarını belirle
$basliklar = [
    'Ürün Kodu', 
    'Barkod', 
    'Ürün Adı', 
    'Departman', 
    'Ana Grup', 
    'Alt Grup', 
    'Alış Fiyatı (₺)', 
    'Satış Fiyatı (₺)', 
    'Kar Tutarı (₺)', 
    'Kar Marjı (%)',
    '%25 Kar İçin Gereken Fiyat (₺)'
];

// HTTP başlıklarını ayarla
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $dosya_adi . '"');

// Excel için utf-8 BOM eklentisi
echo "\xEF\xBB\xBF";

// CSV dosyasını oluştur
$output = fopen('php://output', 'w');

// CSV başlıklarını yaz
fputcsv($output, $basliklar);

// Verileri CSV'ye yaz
foreach ($urunler as $urun) {
    // Kar tutarı ve yüzdesi hesapla
    $kar_tutari = $urun['satis_fiyati'] - $urun['alis_fiyati'];
    $kar_yuzdesi = $urun['alis_fiyati'] > 0 ? ($kar_tutari / $urun['alis_fiyati']) * 100 : 0;
    
    // %25 kar için gereken satış fiyatı
    $optimal_fiyat = $urun['alis_fiyati'] * 1.25;
    
    // Kategori bilgisi
    $departman = $urun['departman_adi'] ?? '';
    $ana_grup = $urun['ana_grup_adi'] ?? '';
    $alt_grup = $urun['alt_grup_adi'] ?? '';
    
    // CSV satırını oluştur
    $csv_satiri = [
        $urun['kod'],
        $urun['barkod'],
        $urun['ad'],
        $departman,
        $ana_grup,
        $alt_grup,
        number_format($urun['alis_fiyati'], 2, ',', '.'),
        number_format($urun['satis_fiyati'], 2, ',', '.'),
        number_format($kar_tutari, 2, ',', '.'),
        number_format($kar_yuzdesi, 2, ',', '.'),
        number_format($optimal_fiyat, 2, ',', '.')
    ];
    
    // Satırı CSV'ye yaz
    fputcsv($output, $csv_satiri);
}

// Çıkışı kapat
fclose($output);
exit;