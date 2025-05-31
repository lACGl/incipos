<?php
/**
 * Fiyat Yönetimi - Fiyat Geçmişi Excel Export
 * Fiyat geçmişi raporunu Excel olarak dışa aktarır
 */

// Session yönetimi ve yetkisiz erişim kontrolü
require_once '../../session_manager.php';

// Session kontrolü
checkUserSession();

// Veritabanı bağlantısı
require_once '../../db_connection.php';

// Export isteği kontrolü
if (!isset($_GET['export']) || $_GET['export'] != '1') {
    header("Location: price_history.php");
    exit;
}

// Filtre parametreleri
$baslangic_tarihi = isset($_GET['baslangic_tarihi']) ? $_GET['baslangic_tarihi'] : date('Y-m-d', strtotime('-30 days'));
$bitis_tarihi = isset($_GET['bitis_tarihi']) ? $_GET['bitis_tarihi'] : date('Y-m-d');
$departman_id = isset($_GET['departman_id']) ? intval($_GET['departman_id']) : 0;
$ana_grup_id = isset($_GET['ana_grup_id']) ? intval($_GET['ana_grup_id']) : 0;
$alt_grup_id = isset($_GET['alt_grup_id']) ? intval($_GET['alt_grup_id']) : 0;
$islem_tipi = isset($_GET['islem_tipi']) ? $_GET['islem_tipi'] : '';
$urun_adi = isset($_GET['urun_adi']) ? trim($_GET['urun_adi']) : '';
$kullanici_id = isset($_GET['kullanici_id']) ? intval($_GET['kullanici_id']) : 0;
$degisim_tipi = isset($_GET['degisim_tipi']) ? $_GET['degisim_tipi'] : '';
$sirala = isset($_GET['sirala']) ? $_GET['sirala'] : 'tarih_desc';

// SQL sorgusu oluştur
$sql = "
    SELECT 
        ufg.id, 
        ufg.urun_id, 
        ufg.islem_tipi, 
        ufg.eski_fiyat, 
        ufg.yeni_fiyat, 
        ufg.aciklama, 
        ufg.tarih, 
        us.ad as urun_adi, 
        us.barkod,
        us.departman_id,
        us.ana_grup_id,
        us.alt_grup_id,
        d.ad as departman_adi,
        ag.ad as ana_grup_adi,
        alg.ad as alt_grup_adi,
        CONCAT(au.kullanici_adi) as kullanici
    FROM urun_fiyat_gecmisi ufg
    LEFT JOIN urun_stok us ON ufg.urun_id = us.id
    LEFT JOIN departmanlar d ON us.departman_id = d.id
    LEFT JOIN ana_gruplar ag ON us.ana_grup_id = ag.id
    LEFT JOIN alt_gruplar alg ON us.alt_grup_id = alg.id
    LEFT JOIN admin_user au ON ufg.kullanici_id = au.id
    WHERE ufg.tarih BETWEEN :baslangic_tarihi AND :bitis_tarihi
";
$params = [
    ':baslangic_tarihi' => $baslangic_tarihi . ' 00:00:00',
    ':bitis_tarihi' => $bitis_tarihi . ' 23:59:59'
];

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

if (!empty($islem_tipi)) {
    $sql .= " AND ufg.islem_tipi = :islem_tipi";
    $params[':islem_tipi'] = $islem_tipi;
}

if (!empty($urun_adi)) {
    $sql .= " AND (us.ad LIKE :urun_adi OR us.barkod LIKE :urun_barkod)";
    $params[':urun_adi'] = '%' . $urun_adi . '%';
    $params[':urun_barkod'] = '%' . $urun_adi . '%';
}

if ($kullanici_id > 0) {
    $sql .= " AND ufg.kullanici_id = :kullanici_id";
    $params[':kullanici_id'] = $kullanici_id;
}

if ($degisim_tipi == 'artis') {
    $sql .= " AND ufg.yeni_fiyat > ufg.eski_fiyat";
} elseif ($degisim_tipi == 'azalis') {
    $sql .= " AND ufg.yeni_fiyat < ufg.eski_fiyat";
}

// Sıralama ekle
switch ($sirala) {
    case 'tarih_asc':
        $sql .= " ORDER BY ufg.tarih ASC";
        break;
    case 'tarih_desc':
        $sql .= " ORDER BY ufg.tarih DESC";
        break;
    case 'urun_adi':
        $sql .= " ORDER BY us.ad ASC";
        break;
    case 'eski_fiyat_asc':
        $sql .= " ORDER BY ufg.eski_fiyat ASC";
        break;
    case 'eski_fiyat_desc':
        $sql .= " ORDER BY ufg.eski_fiyat DESC";
        break;
    case 'yeni_fiyat_asc':
        $sql .= " ORDER BY ufg.yeni_fiyat ASC";
        break;
    case 'yeni_fiyat_desc':
        $sql .= " ORDER BY ufg.yeni_fiyat DESC";
        break;
    case 'degisim_asc':
        $sql .= " ORDER BY (ufg.yeni_fiyat - ufg.eski_fiyat) ASC";
        break;
    case 'degisim_desc':
        $sql .= " ORDER BY (ufg.yeni_fiyat - ufg.eski_fiyat) DESC";
        break;
    default:
        $sql .= " ORDER BY ufg.tarih DESC";
        break;
}

// Limit yok - tüm verileri al
// $sql .= " LIMIT 10000"; // Büyük veri setleri için sınırlama olabilir

// Verileri getir
$stmt = $conn->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$fiyat_gecmisi = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Excel dosya adını belirle
$dosya_adi = "Fiyat_Gecmisi_Raporu_" . date('Y-m-d_H-i-s') . ".csv";

// CSV başlıklarını belirle
$basliklar = [
    'Tarih', 
    'Ürün Adı', 
    'Barkod', 
    'Departman', 
    'Ana Grup', 
    'Alt Grup', 
    'İşlem Türü', 
    'Eski Fiyat (₺)', 
    'Yeni Fiyat (₺)', 
    'Değişim (%)', 
    'Kullanıcı', 
    'Açıklama'
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
foreach ($fiyat_gecmisi as $kayit) {
    // Fiyat değişim yüzdesi hesapla
    $degisim_yuzdesi = 0;
    if ($kayit['eski_fiyat'] > 0) {
        $degisim_yuzdesi = (($kayit['yeni_fiyat'] - $kayit['eski_fiyat']) / $kayit['eski_fiyat']) * 100;
    }
    
    // İşlem tipini Türkçe'ye çevir
    $islem_tipi = $kayit['islem_tipi'] == 'alis' ? 'Alış Fiyat Güncelleme' : 'Satış Fiyat Güncelleme';
    
    // CSV satırını oluştur
    $csv_satiri = [
        date('d.m.Y H:i', strtotime($kayit['tarih'])),
        $kayit['urun_adi'],
        $kayit['barkod'],
        $kayit['departman_adi'],
        $kayit['ana_grup_adi'],
        $kayit['alt_grup_adi'],
        $islem_tipi,
        number_format($kayit['eski_fiyat'], 2, ',', '.'),
        number_format($kayit['yeni_fiyat'], 2, ',', '.'),
        number_format($degisim_yuzdesi, 2, ',', '.') . '%',
        $kayit['kullanici'],
        $kayit['aciklama']
    ];
    
    // Satırı CSV'ye yaz
    fputcsv($output, $csv_satiri);
}

// Çıkışı kapat
fclose($output);
exit;