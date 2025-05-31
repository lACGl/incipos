<?php
/**
 * Fiyat Yönetimi - Yardımcı Fonksiyonlar
 * Toplu fiyat güncelleme ve fiyat yönetimi işlemleri için gerekli fonksiyonlar
 */

/**
 * Ürün fiyatını günceller ve log kaydı oluşturur
 * 
 * @param PDO $conn Veritabanı bağlantısı
 * @param int $urun_id Ürün ID
 * @param float $yeni_fiyat Yeni fiyat
 * @param string $islem_tipi İşlem tipi (alis, satis_fiyati_guncelleme)
 * @param string $aciklama Açıklama
 * @param int $kullanici_id Kullanıcı ID
 * @param int|null $fatura_id Fatura ID (varsa)
 * @return bool Başarılı mı?
 */
function updateProductPrice($conn, $urun_id, $yeni_fiyat, $islem_tipi = 'satis_fiyati_guncelleme', $aciklama = '', $kullanici_id = null, $fatura_id = null) {
    try {
        // Transaction başlat
        $conn->beginTransaction();
        
        // Mevcut fiyatı al
        $stmt = $conn->prepare("SELECT satis_fiyati FROM urun_stok WHERE id = :urun_id");
        $stmt->bindParam(':urun_id', $urun_id, PDO::PARAM_INT);
        $stmt->execute();
        $urun = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$urun) {
            // Ürün bulunamadı
            return false;
        }
        
        $eski_fiyat = $urun['satis_fiyati'];
        
        // Fiyat aynıysa güncelleme yapma
        if (floatval($eski_fiyat) == floatval($yeni_fiyat)) {
            return true; // Değişiklik yok, başarılı kabul et
        }
        
        // Fiyat güncelle
        $stmt = $conn->prepare("UPDATE urun_stok SET satis_fiyati = :yeni_fiyat WHERE id = :urun_id");
        $stmt->bindParam(':yeni_fiyat', $yeni_fiyat, PDO::PARAM_STR);
        $stmt->bindParam(':urun_id', $urun_id, PDO::PARAM_INT);
        $stmt->execute();
        
        // Log kaydı oluştur
        $stmt = $conn->prepare("
            INSERT INTO urun_fiyat_gecmisi (urun_id, islem_tipi, eski_fiyat, yeni_fiyat, fatura_id, aciklama, kullanici_id)
            VALUES (:urun_id, :islem_tipi, :eski_fiyat, :yeni_fiyat, :fatura_id, :aciklama, :kullanici_id)
        ");
        $stmt->bindParam(':urun_id', $urun_id, PDO::PARAM_INT);
        $stmt->bindParam(':islem_tipi', $islem_tipi, PDO::PARAM_STR);
        $stmt->bindParam(':eski_fiyat', $eski_fiyat, PDO::PARAM_STR);
        $stmt->bindParam(':yeni_fiyat', $yeni_fiyat, PDO::PARAM_STR);
        $stmt->bindParam(':fatura_id', $fatura_id, PDO::PARAM_INT);
        $stmt->bindParam(':aciklama', $aciklama, PDO::PARAM_STR);
        $stmt->bindParam(':kullanici_id', $kullanici_id, PDO::PARAM_INT);
        $stmt->execute();
        
        // Transaction tamamla
        $conn->commit();
        return true;
    } catch (PDOException $e) {
        // Hata durumunda rollback
        $conn->rollBack();
        error_log("Fiyat güncelleme hatası: " . $e->getMessage());
        return false;
    }
}

/**
 * Toplu fiyat güncellemesi yapar
 * 
 * @param PDO $conn Veritabanı bağlantısı
 * @param array $params Güncelleme parametreleri
 * @param int $kullanici_id Kullanıcı ID
 * @return array Sonuç bilgileri
 */
function bulkPriceUpdate($conn, $params, $kullanici_id) {
    $result = [
        'success' => false,
        'total' => 0,
        'updated' => 0,
        'skipped' => 0,
        'errors' => 0,
        'message' => ''
    ];
    
    try {
        // Güncelleme filtresi oluştur
        $where_conditions = [];
        $bind_params = [];
        
        // Departman filtresi
        if (!empty($params['departman_id'])) {
            $where_conditions[] = "departman_id = :departman_id";
            $bind_params[':departman_id'] = $params['departman_id'];
        }
        
        // Ana grup filtresi
        if (!empty($params['ana_grup_id'])) {
            $where_conditions[] = "ana_grup_id = :ana_grup_id";
            $bind_params[':ana_grup_id'] = $params['ana_grup_id'];
        }
        
        // Alt grup filtresi
        if (!empty($params['alt_grup_id'])) {
            $where_conditions[] = "alt_grup_id = :alt_grup_id";
            $bind_params[':alt_grup_id'] = $params['alt_grup_id'];
        }
        
        // Fiyat aralığı filtresi
        if (!empty($params['min_fiyat'])) {
            $where_conditions[] = "satis_fiyati >= :min_fiyat";
            $bind_params[':min_fiyat'] = $params['min_fiyat'];
        }
        
        if (!empty($params['max_fiyat'])) {
            $where_conditions[] = "satis_fiyati <= :max_fiyat";
            $bind_params[':max_fiyat'] = $params['max_fiyat'];
        }
        
        // Sadece aktif ürünleri dahil et
        $where_conditions[] = "durum = 'aktif'";
        
        // Where koşulunu oluştur
        $where_clause = count($where_conditions) > 0 ? "WHERE " . implode(" AND ", $where_conditions) : "";
        
        // Etkilenecek ürünleri bul
        $query = "SELECT id, ad, satis_fiyati FROM urun_stok $where_clause";
        $stmt = $conn->prepare($query);
        
        // Parametreleri bağla
        foreach ($bind_params as $param => $value) {
            $stmt->bindValue($param, $value);
        }
        
        $stmt->execute();
        $urunler = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $result['total'] = count($urunler);
        
        if ($result['total'] == 0) {
            $result['message'] = "Seçilen kriterlere uygun ürün bulunamadı.";
            return $result;
        }
        
        // Güncelleme yöntemi
        $update_method = $params['update_method'];
        $update_value = $params['update_value'];
        $aciklama = $params['aciklama'] ?? "Toplu fiyat güncellemesi";
        
        // Ürünleri güncelle
        foreach ($urunler as $urun) {
            $urun_id = $urun['id'];
            $mevcut_fiyat = $urun['satis_fiyati'];
            $yeni_fiyat = $mevcut_fiyat;
            
            // Güncelleme yöntemine göre fiyat hesapla
            switch ($update_method) {
                case 'percentage_increase':
                    $yeni_fiyat = $mevcut_fiyat * (1 + ($update_value / 100));
                    break;
                    
                case 'percentage_decrease':
                    $yeni_fiyat = $mevcut_fiyat * (1 - ($update_value / 100));
                    break;
                    
                case 'fixed_amount_increase':
                    $yeni_fiyat = $mevcut_fiyat + $update_value;
                    break;
                    
                case 'fixed_amount_decrease':
                    $yeni_fiyat = max(0, $mevcut_fiyat - $update_value);
                    break;
                    
                case 'fixed_price':
                    $yeni_fiyat = $update_value;
                    break;
            }
            
            // Yeni fiyatı yuvarla (2 ondalık basamak)
            $yeni_fiyat = round($yeni_fiyat, 2);
            
            // Fiyat değişmediyse atla
            if ($yeni_fiyat == $mevcut_fiyat) {
                $result['skipped']++;
                continue;
            }
            
            // Fiyatı güncelle
            if (updateProductPrice($conn, $urun_id, $yeni_fiyat, 'satis_fiyati_guncelleme', $aciklama, $kullanici_id)) {
                $result['updated']++;
            } else {
                $result['errors']++;
            }
        }
        
        $result['success'] = true;
        $result['message'] = "Toplu fiyat güncellemesi tamamlandı. {$result['updated']} ürün güncellendi, {$result['skipped']} ürün değişmedi, {$result['errors']} hata oluştu.";
        
    } catch (PDOException $e) {
        $result['success'] = false;
        $result['message'] = "Toplu fiyat güncelleme işlemi sırasında bir hata oluştu: " . $e->getMessage();
        error_log("Toplu fiyat güncelleme hatası: " . $e->getMessage());
    }
    
    return $result;
}

/**
 * Kategori bazlı fiyatlandırma yapar
 * 
 * @param PDO $conn Veritabanı bağlantısı
 * @param array $params Güncelleme parametreleri
 * @param int $kullanici_id Kullanıcı ID
 * @return array Sonuç bilgileri
 */
function categoryBasedPricing($conn, $params, $kullanici_id) {
    $result = [
        'success' => false,
        'total' => 0,
        'updated' => 0,
        'skipped' => 0,
        'errors' => 0,
        'message' => ''
    ];
    
    try {
        $conn->beginTransaction();
        
        // Kategori ID'leri
        $departman_id = $params['departman_id'] ?? null;
        $ana_grup_id = $params['ana_grup_id'] ?? null;
        $alt_grup_id = $params['alt_grup_id'] ?? null;
        
        // Fiyatlandırma ayrıntıları
        $update_method = $params['update_method'];
        $update_value = $params['update_value'];
        $aciklama = $params['aciklama'] ?? "Kategori bazlı fiyatlandırma";
        
        // SQL sorgusu hazırla
        $query = "SELECT id, ad, satis_fiyati FROM urun_stok WHERE durum = 'aktif'";
        $bind_params = [];
        
        if ($departman_id) {
            $query .= " AND departman_id = :departman_id";
            $bind_params[':departman_id'] = $departman_id;
        }
        
        if ($ana_grup_id) {
            $query .= " AND ana_grup_id = :ana_grup_id";
            $bind_params[':ana_grup_id'] = $ana_grup_id;
        }
        
        if ($alt_grup_id) {
            $query .= " AND alt_grup_id = :alt_grup_id";
            $bind_params[':alt_grup_id'] = $alt_grup_id;
        }
        
        $stmt = $conn->prepare($query);
        
        // Parametreleri bağla
        foreach ($bind_params as $param => $value) {
            $stmt->bindValue($param, $value);
        }
        
        $stmt->execute();
        $urunler = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $result['total'] = count($urunler);
        
        if ($result['total'] == 0) {
            $result['message'] = "Seçilen kategoride ürün bulunamadı.";
            return $result;
        }
        
        // Ürünleri güncelle
        foreach ($urunler as $urun) {
            $urun_id = $urun['id'];
            $mevcut_fiyat = $urun['satis_fiyati'];
            $yeni_fiyat = $mevcut_fiyat;
            
            // Güncelleme yöntemine göre fiyat hesapla
            switch ($update_method) {
                case 'percentage_increase':
                    $yeni_fiyat = $mevcut_fiyat * (1 + ($update_value / 100));
                    break;
                    
                case 'percentage_decrease':
                    $yeni_fiyat = $mevcut_fiyat * (1 - ($update_value / 100));
                    break;
                    
                case 'fixed_amount_increase':
                    $yeni_fiyat = $mevcut_fiyat + $update_value;
                    break;
                    
                case 'fixed_amount_decrease':
                    $yeni_fiyat = max(0, $mevcut_fiyat - $update_value);
                    break;
                    
                case 'fixed_price':
                    $yeni_fiyat = $update_value;
                    break;
                    
                case 'profit_margin':
                    // Kar marjı hesaplama
                    $alis_fiyati_stmt = $conn->prepare("SELECT alis_fiyati FROM urun_stok WHERE id = :urun_id");
                    $alis_fiyati_stmt->bindValue(':urun_id', $urun_id);
                    $alis_fiyati_stmt->execute();
                    $alis_fiyati = $alis_fiyati_stmt->fetchColumn();
                    
                    if ($alis_fiyati && $alis_fiyati > 0) {
                        // Kar marjı = (Satış Fiyatı - Alış Fiyatı) / Alış Fiyatı * 100
                        // Yeni Fiyat = Alış Fiyatı * (1 + Kar Marjı / 100)
                        $yeni_fiyat = $alis_fiyati * (1 + ($update_value / 100));
                    } else {
                        // Alış fiyatı yoksa fiyatı değiştirme
                        $result['skipped']++;
                        continue;
                    }
                    break;
            }
            
            // Yeni fiyatı yuvarla (2 ondalık basamak)
            $yeni_fiyat = round($yeni_fiyat, 2);
            
            // Fiyat değişmediyse atla
            if ($yeni_fiyat == $mevcut_fiyat) {
                $result['skipped']++;
                continue;
            }
            
            // Fiyatı güncelle
            if (updateProductPrice($conn, $urun_id, $yeni_fiyat, 'satis_fiyati_guncelleme', $aciklama, $kullanici_id)) {
                $result['updated']++;
            } else {
                $result['errors']++;
            }
        }
        
        $conn->commit();
        
        $result['success'] = true;
        $result['message'] = "Kategori bazlı fiyatlandırma tamamlandı. {$result['updated']} ürün güncellendi, {$result['skipped']} ürün değişmedi, {$result['errors']} hata oluştu.";
        
    } catch (PDOException $e) {
        $conn->rollBack();
        
        $result['success'] = false;
        $result['message'] = "Kategori bazlı fiyatlandırma sırasında bir hata oluştu: " . $e->getMessage();
        error_log("Kategori bazlı fiyatlandırma hatası: " . $e->getMessage());
    }
    
    return $result;
}

/**
 * Fiyat değişim istatistiklerini getirir
 * 
 * @param PDO $conn Veritabanı bağlantısı
 * @param string $baslangic_tarihi Başlangıç tarihi (Y-m-d)
 * @param string $bitis_tarihi Bitiş tarihi (Y-m-d)
 * @return array İstatistik bilgileri
 */
function getPriceChangeStatistics($conn, $baslangic_tarihi, $bitis_tarihi) {
    $stats = [
        'toplam_guncelleme' => 0,
        'artis_sayisi' => 0,
        'azalis_sayisi' => 0,
        'ortalama_artis_yuzdesi' => 0,
        'ortalama_azalis_yuzdesi' => 0,
        'en_yuksek_artis' => 0,
        'en_yuksek_azalis' => 0,
        'en_cok_guncellenen_urunler' => [],
        'en_yuksek_artis_urunler' => [],
        'en_yuksek_azalis_urunler' => []
    ];
    
    try {
        // Toplam güncelleme sayısı
        $stmt = $conn->prepare("
            SELECT COUNT(*) as toplam
            FROM urun_fiyat_gecmisi
            WHERE tarih BETWEEN :baslangic AND :bitis
            AND islem_tipi = 'satis_fiyati_guncelleme'
        ");
        $stmt->bindParam(':baslangic', $baslangic_tarihi);
        $stmt->bindParam(':bitis', $bitis_tarihi);
        $stmt->execute();
        $stats['toplam_guncelleme'] = $stmt->fetchColumn();
        
        // Artış ve azalış sayıları
        $stmt = $conn->prepare("
            SELECT 
                SUM(CASE WHEN yeni_fiyat > eski_fiyat THEN 1 ELSE 0 END) as artis_sayisi,
                SUM(CASE WHEN yeni_fiyat < eski_fiyat THEN 1 ELSE 0 END) as azalis_sayisi,
                AVG(CASE WHEN yeni_fiyat > eski_fiyat THEN ((yeni_fiyat - eski_fiyat) / eski_fiyat * 100) ELSE NULL END) as ortalama_artis,
                AVG(CASE WHEN yeni_fiyat < eski_fiyat THEN ((eski_fiyat - yeni_fiyat) / eski_fiyat * 100) ELSE NULL END) as ortalama_azalis,
                MAX(CASE WHEN yeni_fiyat > eski_fiyat THEN ((yeni_fiyat - eski_fiyat) / eski_fiyat * 100) ELSE 0 END) as max_artis,
                MAX(CASE WHEN yeni_fiyat < eski_fiyat THEN ((eski_fiyat - yeni_fiyat) / eski_fiyat * 100) ELSE 0 END) as max_azalis
            FROM urun_fiyat_gecmisi
            WHERE tarih BETWEEN :baslangic AND :bitis
            AND islem_tipi = 'satis_fiyati_guncelleme'
            AND eski_fiyat > 0
        ");
        $stmt->bindParam(':baslangic', $baslangic_tarihi);
        $stmt->bindParam(':bitis', $bitis_tarihi);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stats['artis_sayisi'] = intval($result['artis_sayisi']);
        $stats['azalis_sayisi'] = intval($result['azalis_sayisi']);
        $stats['ortalama_artis_yuzdesi'] = round($result['ortalama_artis'], 2);
        $stats['ortalama_azalis_yuzdesi'] = round($result['ortalama_azalis'], 2);
        $stats['en_yuksek_artis'] = round($result['max_artis'], 2);
        $stats['en_yuksek_azalis'] = round($result['max_azalis'], 2);
        
        // En çok güncellenen ürünler
        $stmt = $conn->prepare("
            SELECT 
                ufg.urun_id,
                us.ad as urun_adi,
                us.barkod,
                COUNT(*) as guncelleme_sayisi
            FROM urun_fiyat_gecmisi ufg
            JOIN urun_stok us ON ufg.urun_id = us.id
            WHERE ufg.tarih BETWEEN :baslangic AND :bitis
            AND ufg.islem_tipi = 'satis_fiyati_guncelleme'
            GROUP BY ufg.urun_id
            ORDER BY guncelleme_sayisi DESC
            LIMIT 5
        ");
        $stmt->bindParam(':baslangic', $baslangic_tarihi);
        $stmt->bindParam(':bitis', $bitis_tarihi);
        $stmt->execute();
        $stats['en_cok_guncellenen_urunler'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // En yüksek artış olan ürünler
        $stmt = $conn->prepare("
            SELECT 
                ufg.urun_id,
                us.ad as urun_adi,
                us.barkod,
                ufg.eski_fiyat,
                ufg.yeni_fiyat,
                ((ufg.yeni_fiyat - ufg.eski_fiyat) / ufg.eski_fiyat * 100) as artis_yuzdesi,
                ufg.tarih
            FROM urun_fiyat_gecmisi ufg
            JOIN urun_stok us ON ufg.urun_id = us.id
            WHERE ufg.tarih BETWEEN :baslangic AND :bitis
            AND ufg.islem_tipi = 'satis_fiyati_guncelleme'
            AND ufg.yeni_fiyat > ufg.eski_fiyat
            AND ufg.eski_fiyat > 0
            ORDER BY artis_yuzdesi DESC
            LIMIT 5
        ");
        $stmt->bindParam(':baslangic', $baslangic_tarihi);
        $stmt->bindParam(':bitis', $bitis_tarihi);
        $stmt->execute();
        $stats['en_yuksek_artis_urunler'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // En yüksek azalış olan ürünler
        $stmt = $conn->prepare("
            SELECT 
                ufg.urun_id,
                us.ad as urun_adi,
                us.barkod,
                ufg.eski_fiyat,
                ufg.yeni_fiyat,
                ((ufg.eski_fiyat - ufg.yeni_fiyat) / ufg.eski_fiyat * 100) as azalis_yuzdesi,
                ufg.tarih
            FROM urun_fiyat_gecmisi ufg
            JOIN urun_stok us ON ufg.urun_id = us.id
            WHERE ufg.tarih BETWEEN :baslangic AND :bitis
            AND ufg.islem_tipi = 'satis_fiyati_guncelleme'
            AND ufg.yeni_fiyat < ufg.eski_fiyat
            AND ufg.eski_fiyat > 0
            ORDER BY azalis_yuzdesi DESC
            LIMIT 5
        ");
        $stmt->bindParam(':baslangic', $baslangic_tarihi);
        $stmt->bindParam(':bitis', $bitis_tarihi);
        $stmt->execute();
        $stats['en_yuksek_azalis_urunler'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Fiyat değişim istatistikleri hatası: " . $e->getMessage());
    }
    
    return $stats;
}

/**
 * Ürünün fiyat geçmişini getirir
 * 
 * @param PDO $conn Veritabanı bağlantısı
 * @param int $urun_id Ürün ID
 * @param int $limit Kayıt limiti
 * @return array Fiyat geçmişi
 */
function getProductPriceHistory($conn, $urun_id, $limit = 10) {
    $history = [];
    
    try {
        $stmt = $conn->prepare("
            SELECT 
                ufg.*,
                CONCAT(au.kullanici_adi) as kullanici
            FROM urun_fiyat_gecmisi ufg
            LEFT JOIN admin_user au ON ufg.kullanici_id = au.id
            WHERE ufg.urun_id = :urun_id
            ORDER BY ufg.tarih DESC
            LIMIT :limit
        ");
        $stmt->bindParam(':urun_id', $urun_id, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Ürün fiyat geçmişi hatası: " . $e->getMessage());
    }
    
    return $history;
}