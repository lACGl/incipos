<?php
/**
 * Fiyat Yönetimi - Toplu Fiyat Güncelleme Ön İzleme
 * Toplu fiyat güncellemesi için etkilenecek ürünleri ve yeni fiyatları gösterir
 */

// Session yönetimi ve yetkisiz erişim kontrolü
require_once '../../session_manager.php';

// Session kontrolü
checkUserSession();

// Veritabanı bağlantısı
require_once '../../db_connection.php';

// AJAX isteği kontrolü
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['preview'])) {
    echo '<p class="text-red-500">Geçersiz istek!</p>';
    exit;
}

// Parametreleri al
$departman_id = isset($_POST['departman_id']) ? intval($_POST['departman_id']) : 0;
$ana_grup_id = isset($_POST['ana_grup_id']) ? intval($_POST['ana_grup_id']) : 0;
$alt_grup_id = isset($_POST['alt_grup_id']) ? intval($_POST['alt_grup_id']) : 0;
$min_fiyat = isset($_POST['min_fiyat']) && $_POST['min_fiyat'] !== '' ? floatval($_POST['min_fiyat']) : null;
$max_fiyat = isset($_POST['max_fiyat']) && $_POST['max_fiyat'] !== '' ? floatval($_POST['max_fiyat']) : null;
$guncelleme_turu = isset($_POST['guncelleme_turu']) ? $_POST['guncelleme_turu'] : 'yuzde_artis';
$guncelleme_degeri = isset($_POST['guncelleme_degeri']) ? floatval($_POST['guncelleme_degeri']) : 0;

// SQL sorgusu oluştur
$sql = "SELECT id, ad, barkod, satis_fiyati FROM urun_stok WHERE durum = 'aktif'";
$params = [];

// Kategori filtrelerini ekle
if ($departman_id > 0) {
    $sql .= " AND departman_id = :departman_id";
    $params[':departman_id'] = $departman_id;
}

if ($ana_grup_id > 0) {
    $sql .= " AND ana_grup_id = :ana_grup_id";
    $params[':ana_grup_id'] = $ana_grup_id;
}

if ($alt_grup_id > 0) {
    $sql .= " AND alt_grup_id = :alt_grup_id";
    $params[':alt_grup_id'] = $alt_grup_id;
}

// Fiyat aralığı filtrelerini ekle
if ($min_fiyat !== null) {
    $sql .= " AND satis_fiyati >= :min_fiyat";
    $params[':min_fiyat'] = $min_fiyat;
}

if ($max_fiyat !== null) {
    $sql .= " AND satis_fiyati <= :max_fiyat";
    $params[':max_fiyat'] = $max_fiyat;
}

// Sıralama ekle
$sql .= " ORDER BY ad ASC LIMIT 100"; // Performans için limitle

try {
    // Ürünleri seç
    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_products = count($products);
    
    if ($total_products == 0) {
        echo '<p class="text-gray-500 text-center">Seçilen kriterlere uygun ürün bulunamadı.</p>';
    } else {
        // Ürün sayısı bilgisini göster
        $count_stmt = $conn->prepare(str_replace("id, ad, barkod, satis_fiyati", "COUNT(*) as total", str_replace("LIMIT 100", "", $sql)));
        foreach ($params as $key => $value) {
            $count_stmt->bindValue($key, $value);
        }
        $count_stmt->execute();
        $total_count = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Ön izleme tablosunu oluştur
        echo '<div class="mb-2">';
        echo '<p class="font-medium">Toplam etkilenecek ürün sayısı: <span class="text-blue-600">' . $total_count . '</span></p>';
        if ($total_count > 100) {
            echo '<p class="text-sm text-gray-500">(İlk 100 ürün gösteriliyor)</p>';
        }
        echo '</div>';
        
        echo '<div class="overflow-x-auto">';
        echo '<table class="min-w-full divide-y divide-gray-200">';
        echo '<thead class="bg-gray-50">';
        echo '<tr>';
        echo '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ürün</th>';
        echo '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Barkod</th>';
        echo '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mevcut Fiyat</th>';
        echo '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Yeni Fiyat</th>';
        echo '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Değişim</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody class="bg-white divide-y divide-gray-200">';
        
        foreach ($products as $product) {
            // Yeni fiyatı hesapla
            $mevcut_fiyat = $product['satis_fiyati'];
            $yeni_fiyat = $mevcut_fiyat;
            
            switch ($guncelleme_turu) {
                case 'yuzde_artis':
                    $yeni_fiyat = $mevcut_fiyat * (1 + ($guncelleme_degeri / 100));
                    break;
                    
                case 'yuzde_azalis':
                    $yeni_fiyat = $mevcut_fiyat * (1 - ($guncelleme_degeri / 100));
                    break;
                    
                case 'sabit_artis':
                    $yeni_fiyat = $mevcut_fiyat + $guncelleme_degeri;
                    break;
                    
                case 'sabit_azalis':
                    $yeni_fiyat = max(0, $mevcut_fiyat - $guncelleme_degeri);
                    break;
                    
                case 'sabit_fiyat':
                    $yeni_fiyat = $guncelleme_degeri;
                    break;
            }
            
            // Fiyatı yuvarla (2 ondalık basamak)
            $yeni_fiyat = round($yeni_fiyat, 2);
            
            // Değişim hesapla
            $degisim = 0;
            if ($mevcut_fiyat > 0) {
                $degisim = (($yeni_fiyat - $mevcut_fiyat) / $mevcut_fiyat) * 100;
            }
            
            // Fiyat değişmediyse çizgili göster
            $row_class = $yeni_fiyat == $mevcut_fiyat ? 'text-gray-400 line-through' : '';
            
            echo '<tr class="' . $row_class . '">';
            echo '<td class="px-6 py-4 whitespace-nowrap">' . htmlspecialchars($product['ad']) . '</td>';
            echo '<td class="px-6 py-4 whitespace-nowrap">' . htmlspecialchars($product['barkod']) . '</td>';
            echo '<td class="px-6 py-4 whitespace-nowrap">' . number_format($mevcut_fiyat, 2, ',', '.') . ' ₺</td>';
            echo '<td class="px-6 py-4 whitespace-nowrap">' . number_format($yeni_fiyat, 2, ',', '.') . ' ₺</td>';
            
            $degisim_class = $degisim > 0 ? 'text-green-600' : ($degisim < 0 ? 'text-red-600' : 'text-gray-500');
            $degisim_sign = $degisim > 0 ? '+' : '';
            
            echo '<td class="px-6 py-4 whitespace-nowrap ' . $degisim_class . '">' . $degisim_sign . number_format($degisim, 2, ',', '.') . '%</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }
    
} catch (PDOException $e) {
    echo '<p class="text-red-500 text-center">Ön izleme yüklenirken bir hata oluştu: ' . $e->getMessage() . '</p>';
}
?>