<?php
/**
 * Fiyat Yönetimi - Kategori Bazlı Fiyatlandırma Ön İzleme
 * Kategori bazlı fiyatlandırma işlemi için ön izleme gösterir
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
$guncelleme_turu = isset($_POST['guncelleme_turu']) ? $_POST['guncelleme_turu'] : 'yuzde_artis';
$guncelleme_degeri = isset($_POST['guncelleme_degeri']) ? floatval($_POST['guncelleme_degeri']) : 0;

// En az bir kategori seçilmiş mi kontrol et
if ($departman_id == 0 && $ana_grup_id == 0 && $alt_grup_id == 0) {
    echo '<p class="text-red-500 text-center">Lütfen en az bir kategori seçin.</p>';
    exit;
}

// SQL sorgusu oluştur
$sql = "SELECT id, ad, barkod, satis_fiyati, alis_fiyati FROM urun_stok WHERE durum = 'aktif'";
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

// Sıralama ekle
$sql .= " ORDER BY ad ASC LIMIT 100"; // Performans için limitle

try {
    // Kategori adlarını getir
    $kategori_adi = "Tüm Ürünler";
    
    if ($departman_id > 0) {
        $stmt = $conn->prepare("SELECT ad FROM departmanlar WHERE id = ?");
        $stmt->execute([$departman_id]);
        $departman_adi = $stmt->fetchColumn();
        $kategori_adi = $departman_adi;
        
        if ($ana_grup_id > 0) {
            $stmt = $conn->prepare("SELECT ad FROM ana_gruplar WHERE id = ?");
            $stmt->execute([$ana_grup_id]);
            $ana_grup_adi = $stmt->fetchColumn();
            $kategori_adi .= " > " . $ana_grup_adi;
            
            if ($alt_grup_id > 0) {
                $stmt = $conn->prepare("SELECT ad FROM alt_gruplar WHERE id = ?");
                $stmt->execute([$alt_grup_id]);
                $alt_grup_adi = $stmt->fetchColumn();
                $kategori_adi .= " > " . $alt_grup_adi;
            }
        }
    }
    
    // Ürünleri seç
    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_products = count($products);
    
    if ($total_products == 0) {
        echo '<p class="text-gray-500 text-center">Seçilen kategoride ürün bulunamadı.</p>';
    } else {
        // Ürün sayısı bilgisini göster
        $count_stmt = $conn->prepare(str_replace("id, ad, barkod, satis_fiyati, alis_fiyati", "COUNT(*) as total", str_replace("LIMIT 100", "", $sql)));
        foreach ($params as $key => $value) {
            $count_stmt->bindValue($key, $value);
        }
        $count_stmt->execute();
        $total_count = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Ön izleme tablosunu oluştur
        echo '<div class="mb-4">';
        echo '<p class="font-medium">Kategori: <span class="text-blue-600">' . htmlspecialchars($kategori_adi) . '</span></p>';
        echo '<p class="font-medium">Toplam etkilenecek ürün sayısı: <span class="text-blue-600">' . $total_count . '</span></p>';
        if ($total_count > 100) {
            echo '<p class="text-sm text-gray-500">(İlk 100 ürün gösteriliyor)</p>';
        }
        echo '</div>';
        
        echo '<div class="">';
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
        
        $etkilenen_urun_sayisi = 0;
        
        foreach ($products as $product) {
            $urun_id = $product['id'];
            $mevcut_fiyat = $product['satis_fiyati'];
            $alis_fiyati = $product['alis_fiyati'];
            $yeni_fiyat = $mevcut_fiyat;
            
            // Güncelleme türüne göre yeni fiyat hesapla
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
                    
                case 'kar_marji':
                    // Alış fiyatı üzerinden kar marjı hesapla
                    if ($alis_fiyati > 0) {
                        $yeni_fiyat = $alis_fiyati * (1 + ($guncelleme_degeri / 100));
                    }
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
            
            // Eğer fiyat değiştiyse etkilenen ürün sayısını artır
            if ($yeni_fiyat != $mevcut_fiyat) {
                $etkilenen_urun_sayisi++;
            }
            
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
        
        // Özet bilgi
        echo '<div class="mt-4 p-3 bg-blue-50 rounded-lg">';
        echo '<p class="text-blue-800">Bu işlem ' . $etkilenen_urun_sayisi . ' ürünün fiyatını değiştirecektir.</p>';
        echo '</div>';
    }
    
} catch (PDOException $e) {
    echo '<p class="text-red-500 text-center">Ön izleme yüklenirken bir hata oluştu: ' . $e->getMessage() . '</p>';
}
?>