<?php
require_once '../session_manager.php'; // Otomatik eklendi
secure_session_start();
require_once '../db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    die(json_encode(['success' => false, 'message' => 'Yetkisiz erişim']));
}

try {
    // Hem 'id' hem de 'urun_id' parametrelerini kontrol edelim
    $id = $_GET['id'] ?? ($_GET['urun_id'] ?? null);
    
    if (!$id) {
        throw new Exception('Ürün ID gerekli');
    }

    $stmt = $conn->prepare("
        SELECT 
            ufg.id,
            ufg.tarih,
            ufg.islem_tipi,
            ufg.eski_fiyat,
            ufg.yeni_fiyat,
            ufg.aciklama,
            af.fatura_seri,
            af.fatura_no,
            t.ad as tedarikci_adi,
            COALESCE(au.kullanici_adi, p.ad, 'admin') as kullanici_adi
        FROM urun_fiyat_gecmisi ufg
        LEFT JOIN alis_faturalari af ON ufg.fatura_id = af.id
        LEFT JOIN tedarikciler t ON af.tedarikci = t.id
        LEFT JOIN admin_user au ON ufg.kullanici_id = au.id
        LEFT JOIN personel p ON ufg.kullanici_id = p.id
        WHERE ufg.urun_id = ?
        ORDER BY ufg.tarih DESC, ufg.id DESC
    ");
    $stmt->execute([$id]);
    $price_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Her kayıt için işlem tipini düzenle ve fiyatları kontrol et
    foreach ($price_history as &$record) {
        switch($record['islem_tipi']) {
            case 'alis_fiyati_guncelleme':
            case 'alis':
                $record['islem_tipi'] = 'Alış Fiyatı Güncelleme';
                break;
            case 'satis_fiyati_guncelleme':
                $record['islem_tipi'] = 'Satış Fiyatı Güncelleme';
                break;
            case 'indirimli_fiyat_guncelleme':
                $record['islem_tipi'] = 'İndirimli Fiyat Güncelleme';
                break;
            default:
                if (empty($record['islem_tipi'])) {
                    // Açıklama içeriğine göre işlem tipini belirle
                    if (stripos($record['aciklama'], 'alış') !== false) {
                        $record['islem_tipi'] = 'Alış Fiyatı Güncelleme';
                    } elseif (stripos($record['aciklama'], 'satış') !== false) {
                        $record['islem_tipi'] = 'Satış Fiyatı Güncelleme';
                    } elseif (stripos($record['aciklama'], 'indirimli') !== false) {
                        $record['islem_tipi'] = 'İndirimli Fiyat Güncelleme';
                    }
                }
        }

        // Fiyatları float olarak düzenle
        $record['eski_fiyat'] = $record['eski_fiyat'] !== null ? (float)$record['eski_fiyat'] : null;
        $record['yeni_fiyat'] = $record['yeni_fiyat'] !== null ? (float)$record['yeni_fiyat'] : null;

        // Kullanıcı bilgisini ekle
        if (!empty($record['kullanici_adi'])) {
            $record['aciklama'] .= "\nKullanıcı: " . $record['kullanici_adi'];
        }
    }

    echo json_encode([
        'success' => true,
        'price_history' => $price_history
    ]);

} catch (Exception $e) {
    error_log('Fiyat geçmişi hatası: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}