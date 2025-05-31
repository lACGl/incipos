<?php
require_once '../session_manager.php'; // Otomatik eklendi
secure_session_start();
require_once '../db_connection.php';

// Yetki kontrolü
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

try {
    // POST verilerini kontrol et
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Geçersiz istek metodu');
    }

    // JSON verisini al
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Gerekli alanları kontrol et
    if (!isset($data['customer_id']) || !isset($data['puan_oran'])) {
        throw new Exception('Eksik veya geçersiz veri');
    }

    $customer_id = (int)$data['customer_id'];
    $puan_oran = (float)$data['puan_oran'];
    
    // Müşteri var mı kontrolü
    $stmt = $conn->prepare("SELECT id FROM musteriler WHERE id = ?");
    $stmt->execute([$customer_id]);
    if (!$stmt->fetch()) {
        throw new Exception('Müşteri bulunamadı');
    }
    
    // Müşteri puanlar tablosunda kayıt var mı kontrolü
    $stmt = $conn->prepare("SELECT id FROM musteri_puanlar WHERE musteri_id = ?");
    $stmt->execute([$customer_id]);
    $puanKaydi = $stmt->fetch();
    
    if ($puanKaydi) {
        // Mevcut kaydı güncelle
        $stmt = $conn->prepare("
            UPDATE musteri_puanlar 
            SET puan_oran = ? 
            WHERE musteri_id = ?
        ");
        $stmt->execute([$puan_oran, $customer_id]);
    } else {
        // Yeni kayıt oluştur
        $stmt = $conn->prepare("
            INSERT INTO musteri_puanlar (
                musteri_id, puan_bakiye, puan_oran, son_alisveris_tarihi, musteri_turu
            ) VALUES (
                ?, 0, ?, NULL, 'standart'
            )
        ");
        $stmt->execute([$customer_id, $puan_oran]);
    }
    
    // Başarılı yanıt
    echo json_encode([
        'success' => true, 
        'message' => 'Müşteri puan oranı başarıyla güncellendi',
        'customer_id' => $customer_id,
        'puan_oran' => $puan_oran
    ]);
    
} catch (PDOException $e) {
    // Veritabanı hatası
    error_log('PDO Hatası: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Veritabanı hatası: ' . $e->getMessage()]);
} catch (Exception $e) {
    // Genel hata
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}