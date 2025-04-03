<?php
/**
 * Stok işlemleri için yardımcı fonksiyonlar
 * Bu dosya stok işlemleri sırasında kullanılan ortak fonksiyonları içerir
 */

/**
 * Ürünün toplam stok miktarını hesaplar ve günceller
 * @param int $urun_id Ürün ID
 * @param PDO $conn Veritabanı bağlantısı
 * @return bool İşlem başarılı mı?
 */
function updateProductTotalStock($urun_id, $conn) {
    try {
        // Ürün bilgilerini al
        $product_query = "SELECT * FROM urun_stok WHERE id = :id";
        $product_stmt = $conn->prepare($product_query);
        $product_stmt->bindParam(':id', $urun_id, PDO::PARAM_INT);
        $product_stmt->execute();
        
        $product = $product_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) {
            return false;
        }
        
        // Depo stok miktarını hesapla
        $depo_query = "SELECT COALESCE(SUM(stok_miktari), 0) as depo_stok FROM depo_stok WHERE urun_id = :urun_id";
        $depo_stmt = $conn->prepare($depo_query);
        $depo_stmt->bindParam(':urun_id', $urun_id, PDO::PARAM_INT);
        $depo_stmt->execute();
        
        $depo_stok = $depo_stmt->fetch(PDO::FETCH_ASSOC)['depo_stok'] ?? 0;
        
        // Mağaza stok miktarını hesapla
        $magaza_query = "SELECT COALESCE(SUM(stok_miktari), 0) as magaza_stok FROM magaza_stok WHERE barkod = :barkod";
        $magaza_stmt = $conn->prepare($magaza_query);
        $magaza_stmt->bindParam(':barkod', $product['barkod'], PDO::PARAM_STR);
        $magaza_stmt->execute();
        
        $magaza_stok = $magaza_stmt->fetch(PDO::FETCH_ASSOC)['magaza_stok'] ?? 0;
        
        // Toplam stok miktarını hesapla
        $total_stock = $depo_stok + $magaza_stok;
        
        // Ürün tablosunu güncelle
        $update_query = "UPDATE urun_stok SET stok_miktari = :stok_miktari WHERE id = :id";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bindParam(':stok_miktari', $total_stock, PDO::PARAM_INT);
        $update_stmt->bindParam(':id', $urun_id, PDO::PARAM_INT);
        
        return $update_stmt->execute();
    } catch (PDOException $e) {
        error_log("Stok güncelleme hatası: " . $e->getMessage());
        return false;
    }
}

/**
 * Stok hareketi ekler
 * @param array $params Hareket parametreleri
 * @param PDO $conn Veritabanı bağlantısı
 * @return bool İşlem başarılı mı?
 */
function addStockMovement($params, $conn) {
    try {
        $required_fields = ['urun_id', 'miktar', 'hareket_tipi', 'tarih', 'kullanici_id'];
        
        foreach ($required_fields as $field) {
            if (!isset($params[$field])) {
                throw new Exception("$field alanı gereklidir");
            }
        }
        
        $query = "INSERT INTO stok_hareketleri (
                    urun_id, miktar, hareket_tipi, aciklama, belge_no, 
                    tarih, kullanici_id, magaza_id, depo_id, maliyet, satis_fiyati
                  ) VALUES (
                    :urun_id, :miktar, :hareket_tipi, :aciklama, :belge_no, 
                    :tarih, :kullanici_id, :magaza_id, :depo_id, :maliyet, :satis_fiyati
                  )";
                  
        $stmt = $conn->prepare($query);
        
        // Zorunlu alanları bind et
        $stmt->bindParam(':urun_id', $params['urun_id'], PDO::PARAM_INT);
        $stmt->bindParam(':miktar', $params['miktar'], PDO::PARAM_STR);
        $stmt->bindParam(':hareket_tipi', $params['hareket_tipi'], PDO::PARAM_STR);
        $stmt->bindParam(':tarih', $params['tarih']);
        $stmt->bindParam(':kullanici_id', $params['kullanici_id'], PDO::PARAM_INT);
        
        // Opsiyonel alanları bind et
        $aciklama = isset($params['aciklama']) ? $params['aciklama'] : NULL;
        $belge_no = isset($params['belge_no']) ? $params['belge_no'] : NULL;
        $magaza_id = isset($params['magaza_id']) ? $params['magaza_id'] : NULL;
        $depo_id = isset($params['depo_id']) ? $params['depo_id'] : NULL;
        $maliyet = isset($params['maliyet']) ? $params['maliyet'] : NULL;
        $satis_fiyati = isset($params['satis_fiyati']) ? $params['satis_fiyati'] : NULL;
        
        $stmt->bindParam(':aciklama', $aciklama, PDO::PARAM_STR);
        $stmt->bindParam(':belge_no', $belge_no, PDO::PARAM_STR);
        $stmt->bindParam(':magaza_id', $magaza_id, PDO::PARAM_INT);
        $stmt->bindParam(':depo_id', $depo_id, PDO::PARAM_INT);
        $stmt->bindParam(':maliyet', $maliyet, PDO::PARAM_STR);
        $stmt->bindParam(':satis_fiyati', $satis_fiyati, PDO::PARAM_STR);
        
        $result = $stmt->execute();
        
        if ($result) {
            // Stok hareketi eklendikten sonra toplam stoku güncelle
            updateProductTotalStock($params['urun_id'], $conn);
        }
        
        return $result;
    } catch (Exception $e) {
        error_log("Stok hareketi ekleme hatası: " . $e->getMessage());
        return false;
    }
}

/**
 * Ürün fiyat geçmişi ekler
 * @param array $params Fiyat parametreleri
 * @param PDO $conn Veritabanı bağlantısı
 * @return bool İşlem başarılı mı?
 */
function addPriceHistory($params, $conn) {
    try {
        $required_fields = ['urun_id', 'islem_tipi', 'yeni_fiyat'];
        
        foreach ($required_fields as $field) {
            if (!isset($params[$field])) {
                throw new Exception("$field alanı gereklidir");
            }
        }
        
        $query = "INSERT INTO urun_fiyat_gecmisi (
                    urun_id, islem_tipi, eski_fiyat, yeni_fiyat, 
                    fatura_id, aciklama, tarih, kullanici_id
                  ) VALUES (
                    :urun_id, :islem_tipi, :eski_fiyat, :yeni_fiyat, 
                    :fatura_id, :aciklama, NOW(), :kullanici_id
                  )";
                  
        $stmt = $conn->prepare($query);
        
        // Zorunlu alanları bind et
        $stmt->bindParam(':urun_id', $params['urun_id'], PDO::PARAM_INT);
        $stmt->bindParam(':islem_tipi', $params['islem_tipi'], PDO::PARAM_STR);
        $stmt->bindParam(':yeni_fiyat', $params['yeni_fiyat'], PDO::PARAM_STR);
        
        // Opsiyonel alanları bind et
        $eski_fiyat = isset($params['eski_fiyat']) ? $params['eski_fiyat'] : NULL;
        $fatura_id = isset($params['fatura_id']) ? $params['fatura_id'] : NULL;
        $aciklama = isset($params['aciklama']) ? $params['aciklama'] : NULL;
        $kullanici_id = isset($params['kullanici_id']) ? $params['kullanici_id'] : NULL;
        
        $stmt->bindParam(':eski_fiyat', $eski_fiyat, PDO::PARAM_STR);
        $stmt->bindParam(':fatura_id', $fatura_id, PDO::PARAM_INT);
        $stmt->bindParam(':aciklama', $aciklama, PDO::PARAM_STR);
        $stmt->bindParam(':kullanici_id', $kullanici_id, PDO::PARAM_INT);
        
        return $stmt->execute();
    } catch (Exception $e) {
        error_log("Fiyat geçmişi ekleme hatası: " . $e->getMessage());
        return false;
    }
}

/**
 * Depo stoğunu günceller
 * @param int $urun_id Ürün ID
 * @param int $depo_id Depo ID
 * @param float $miktar Miktar (pozitif: ekle, negatif: çıkar)
 * @param PDO $conn Veritabanı bağlantısı
 * @return bool İşlem başarılı mı?
 */
function updateDepoStock($urun_id, $depo_id, $miktar, $conn) {
    try {
        // Önce depo stok kaydı var mı kontrol et
        $check_query = "SELECT * FROM depo_stok WHERE urun_id = :urun_id AND depo_id = :depo_id";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bindParam(':urun_id', $urun_id, PDO::PARAM_INT);
        $check_stmt->bindParam(':depo_id', $depo_id, PDO::PARAM_INT);
        $check_stmt->execute();
        
        $depo_stok = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($depo_stok) {
            // Kayıt varsa güncelle
            $new_quantity = $depo_stok['stok_miktari'] + $miktar;
            
            // Stok 0'ın altına düşemez
            if ($new_quantity < 0) {
                return false;
            }
            
            $update_query = "UPDATE depo_stok 
                             SET stok_miktari = :stok_miktari, son_guncelleme = NOW() 
                             WHERE urun_id = :urun_id AND depo_id = :depo_id";
            
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bindParam(':stok_miktari', $new_quantity, PDO::PARAM_STR);
            $update_stmt->bindParam(':urun_id', $urun_id, PDO::PARAM_INT);
            $update_stmt->bindParam(':depo_id', $depo_id, PDO::PARAM_INT);
            
            $result = $update_stmt->execute();
        } else {
            // Kayıt yoksa ve miktar pozitifse ekle
            if ($miktar <= 0) {
                return false;
            }
            
            $insert_query = "INSERT INTO depo_stok (depo_id, urun_id, stok_miktari, son_guncelleme) 
                             VALUES (:depo_id, :urun_id, :stok_miktari, NOW())";
            
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bindParam(':depo_id', $depo_id, PDO::PARAM_INT);
            $insert_stmt->bindParam(':urun_id', $urun_id, PDO::PARAM_INT);
            $insert_stmt->bindParam(':stok_miktari', $miktar, PDO::PARAM_STR);
            
            $result = $insert_stmt->execute();
        }
        
        if ($result) {
            // Stok güncellendikten sonra ürün tablosundaki toplam stoku güncelle
            updateProductTotalStock($urun_id, $conn);
        }
        
        return $result;
    } catch (Exception $e) {
        error_log("Depo stok güncelleme hatası: " . $e->getMessage());
        return false;
    }
}

/**
 * Mağaza stoğunu günceller
 * @param string $barkod Ürün barkodu
 * @param int $magaza_id Mağaza ID
 * @param float $miktar Miktar (pozitif: ekle, negatif: çıkar)
 * @param PDO $conn Veritabanı bağlantısı
 * @return bool İşlem başarılı mı?
 */
function updateMagazaStock($barkod, $magaza_id, $miktar, $conn) {
    try {
        // Önce ürün ID'sini bul
        $product_query = "SELECT id FROM urun_stok WHERE barkod = :barkod";
        $product_stmt = $conn->prepare($product_query);
        $product_stmt->bindParam(':barkod', $barkod, PDO::PARAM_STR);
        $product_stmt->execute();
        
        $product = $product_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) {
            return false;
        }
        
        $urun_id = $product['id'];
        
        // Mağaza stok kaydı var mı kontrol et
        $check_query = "SELECT * FROM magaza_stok WHERE barkod = :barkod AND magaza_id = :magaza_id";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bindParam(':barkod', $barkod, PDO::PARAM_STR);
        $check_stmt->bindParam(':magaza_id', $magaza_id, PDO::PARAM_INT);
        $check_stmt->execute();
        
        $magaza_stok = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($magaza_stok) {
            // Kayıt varsa güncelle
            $new_quantity = $magaza_stok['stok_miktari'] + $miktar;
            
            // Stok 0'ın altına düşemez
            if ($new_quantity < 0) {
                return false;
            }
            
            $update_query = "UPDATE magaza_stok 
                             SET stok_miktari = :stok_miktari, son_guncelleme = NOW() 
                             WHERE barkod = :barkod AND magaza_id = :magaza_id";
            
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bindParam(':stok_miktari', $new_quantity, PDO::PARAM_INT);
            $update_stmt->bindParam(':barkod', $barkod, PDO::PARAM_STR);
            $update_stmt->bindParam(':magaza_id', $magaza_id, PDO::PARAM_INT);
            
            $result = $update_stmt->execute();
        } else {
            // Kayıt yoksa ve miktar pozitifse ekle
            if ($miktar <= 0) {
                return false;
            }
            
            // Ürünün satış fiyatını al
            $price_query = "SELECT satis_fiyati FROM urun_stok WHERE barkod = :barkod";
            $price_stmt = $conn->prepare($price_query);
            $price_stmt->bindParam(':barkod', $barkod, PDO::PARAM_STR);
            $price_stmt->execute();
            
            $price_data = $price_stmt->fetch(PDO::FETCH_ASSOC);
            $satis_fiyati = $price_data['satis_fiyati'] ?? 0;
            
            $insert_query = "INSERT INTO magaza_stok (barkod, magaza_id, stok_miktari, satis_fiyati, son_guncelleme) 
                             VALUES (:barkod, :magaza_id, :stok_miktari, :satis_fiyati, NOW())";
            
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bindParam(':barkod', $barkod, PDO::PARAM_STR);
            $insert_stmt->bindParam(':magaza_id', $magaza_id, PDO::PARAM_INT);
            $insert_stmt->bindParam(':stok_miktari', $miktar, PDO::PARAM_INT);
            $insert_stmt->bindParam(':satis_fiyati', $satis_fiyati, PDO::PARAM_STR);
            
            $result = $insert_stmt->execute();
        }
        
        if ($result) {
            // Stok güncellendikten sonra ürün tablosundaki toplam stoku güncelle
            updateProductTotalStock($urun_id, $conn);
        }
        
        return $result;
    } catch (Exception $e) {
        error_log("Mağaza stok güncelleme hatası: " . $e->getMessage());
        return false;
    }
}