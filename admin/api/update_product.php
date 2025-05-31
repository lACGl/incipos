<?php
require_once '../session_manager.php'; // Otomatik eklendi
secure_session_start();
require_once '../db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $product_id = $_POST['product_id'] ?? null;
    
    if (!$product_id) {
        echo json_encode(['success' => false, 'message' => 'Ürün ID bulunamadı']);
        exit;
    }

    try {
        $conn->beginTransaction();

        // Eski fiyatları ve barkodu al
        $stmt = $conn->prepare("SELECT alis_fiyati, satis_fiyati, indirimli_fiyat, barkod FROM urun_stok WHERE id = ?");
        $stmt->execute([$product_id]);
        $old_data = $stmt->fetch(PDO::FETCH_ASSOC);

        // Güncellenecek alanlar
        $fields_to_update = [];
        $params = ['id' => $product_id];

        // Tüm gönderilen alanları kontrol et
        foreach ($_POST as $key => $value) {
            if ($key !== 'product_id') {
                if ($value === '') {
                    $fields_to_update[] = "$key = NULL";
                } else {
                    $fields_to_update[] = "$key = :$key";
                    $params[$key] = $value;
                }
            }
        }

        // Barkod güncelleniyorsa, resim yolunu da güncelle
        if (isset($params['barkod']) && $params['barkod'] !== $old_data['barkod']) {
            $fields_to_update[] = "resim_yolu = :resim_yolu";
            $params['resim_yolu'] = 'files/img/products/' . $params['barkod'] . '_1.jpg';
        }
        // Barkod güncellenmediyse ve resim yolu boşsa otomatik doldur
        else {
            // Mevcut resim yolunu kontrol et
            $check_stmt = $conn->prepare("SELECT resim_yolu FROM urun_stok WHERE id = ?");
            $check_stmt->execute([$product_id]);
            $current_path = $check_stmt->fetchColumn();
            
            if (empty($current_path)) {
                $fields_to_update[] = "resim_yolu = :resim_yolu";
                $params['resim_yolu'] = 'files/img/products/' . $old_data['barkod'] . '_1.jpg';
            }
        }

        if (empty($fields_to_update)) {
            throw new Exception('Güncellenecek alan bulunamadı');
        }

        // SQL sorgusunu hazırla
        $sql = "UPDATE urun_stok SET " . implode(", ", $fields_to_update) . " WHERE id = :id";
        
        // Sorguyu çalıştır
        $stmt = $conn->prepare($sql);
        $result = $stmt->execute($params);

        if (!$result) {
            throw new Exception('Güncelleme işlemi başarısız oldu');
        }

        // Alış fiyatı değişikliğini izle
        if (isset($params['alis_fiyati']) && (float)$params['alis_fiyati'] !== (float)$old_data['alis_fiyati']) {
            $stmt = $conn->prepare("
                INSERT INTO urun_fiyat_gecmisi (
                    urun_id,
                    islem_tipi,
                    eski_fiyat,
                    yeni_fiyat,
                    aciklama,
                    kullanici_id,
                    tarih
                ) VALUES (?, 'alis_fiyati_guncelleme', ?, ?, 'Manuel alış fiyatı güncellemesi', ?, NOW())
            ");
            $stmt->execute([
                $product_id,
                $old_data['alis_fiyati'],
                $params['alis_fiyati'],
                $_SESSION['user_id'] ?? null
            ]);
        }

        // Satış fiyatı değişikliğini izle
        if (isset($params['satis_fiyati']) && (float)$params['satis_fiyati'] !== (float)$old_data['satis_fiyati']) {
            $stmt = $conn->prepare("
                INSERT INTO urun_fiyat_gecmisi (
                    urun_id,
                    islem_tipi,
                    eski_fiyat,
                    yeni_fiyat,
                    aciklama,
                    kullanici_id,
                    tarih
                ) VALUES (?, 'satis_fiyati_guncelleme', ?, ?, 'Manuel Satış fiyatı güncellemesi', ?, NOW())
            ");
            $stmt->execute([
                $product_id,
                $old_data['satis_fiyati'],
                $params['satis_fiyati'],
                $_SESSION['user_id'] ?? null
            ]);
        }

        // İndirimli fiyat değişikliğini izle
        if (isset($params['indirimli_fiyat'])) {
            $new_discount_price = $params['indirimli_fiyat'] === '' ? null : $params['indirimli_fiyat'];
            if ($new_discount_price != $old_data['indirimli_fiyat']) {
                $stmt = $conn->prepare("
                    INSERT INTO urun_fiyat_gecmisi (
                        urun_id,
                        islem_tipi,
                        eski_fiyat,
                        yeni_fiyat,
                        aciklama,
                        kullanici_id,
                        tarih
                    ) VALUES (?, 'indirimli_fiyat_guncelleme', ?, ?, 'Manuel indirimli fiyat güncellemesi', ?, NOW())
                ");
                $stmt->execute([
                    $product_id,
                    $old_data['indirimli_fiyat'],
                    $new_discount_price,
                    $_SESSION['user_id'] ?? null
                ]);
            }
        }

        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Ürün başarıyla güncellendi'
        ]);

    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        echo json_encode([
            'success' => false,
            'message' => 'Güncelleme sırasında bir hata oluştu: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Geçersiz istek metodu'
    ]);
}