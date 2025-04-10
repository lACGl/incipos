<?php
// Satın alma siparişlerinin detaylarını almak için API
session_start();
require_once '../db_connection.php';

// Yetki kontrolü
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

// Sipariş ID'si kontrolü
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Geçersiz sipariş ID']);
    exit;
}

$siparis_id = intval($_GET['id']);

try {
    // Sipariş bilgilerini al
    $order_query = "SELECT s.*, t.ad AS tedarikci_adi, u.kullanici_adi
                    FROM siparisler s
                    LEFT JOIN tedarikciler t ON s.tedarikci_id = t.id
                    LEFT JOIN admin_user u ON s.kullanici_id = u.id
                    WHERE s.id = :siparis_id";
                    
    $stmt = $conn->prepare($order_query);
    $stmt->bindParam(':siparis_id', $siparis_id);
    $stmt->execute();
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Sipariş bulunamadı']);
        exit;
    }
    
    // Durum metinleri ve renkleri
    $durum_metinleri = [
        'beklemede' => 'Beklemede',
        'onaylandi' => 'Onaylandı',
        'iptal' => 'İptal Edildi',
        'tamamlandi' => 'Tamamlandı'
    ];

    $durum_renkleri = [
        'beklemede' => 'bg-yellow-100 text-yellow-800',
        'onaylandi' => 'bg-blue-100 text-blue-800',
        'iptal' => 'bg-red-100 text-red-800',
        'tamamlandi' => 'bg-green-100 text-green-800'
    ];
    
    // Durum metni ve rengini ekle
    $order['durum_text'] = $durum_metinleri[$order['durum']];
    $order['durum_renk'] = $durum_renkleri[$order['durum']];
    
    // Tarihleri formatlama
    $order['tarih'] = date('d.m.Y H:i', strtotime($order['tarih']));
    if (!empty($order['onay_tarihi'])) {
        $order['onay_tarihi'] = date('d.m.Y H:i', strtotime($order['onay_tarihi']));
    }
    if (!empty($order['teslim_tarihi'])) {
        $order['teslim_tarihi'] = date('d.m.Y H:i', strtotime($order['teslim_tarihi']));
    }
    
    // Sipariş detaylarını al
    $items_query = "SELECT sd.*, us.ad AS urun_adi, us.kod AS urun_kodu, us.barkod
                    FROM siparis_detay sd
                    JOIN urun_stok us ON sd.urun_id = us.id
                    WHERE sd.siparis_id = :siparis_id";
                    
    $stmt = $conn->prepare($items_query);
    $stmt->bindParam(':siparis_id', $siparis_id);
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Sipariş log kayıtlarını al
    $logs_query = "SELECT sl.*, u.kullanici_adi
                   FROM siparis_log sl
                   LEFT JOIN admin_user u ON sl.kullanici_id = u.id
                   WHERE sl.siparis_id = :siparis_id
                   ORDER BY sl.tarih DESC";
                   
    $stmt = $conn->prepare($logs_query);
    $stmt->bindParam(':siparis_id', $siparis_id);
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Log tarihlerini formatlama
    foreach ($logs as &$log) {
        $log['tarih'] = date('d.m.Y H:i', strtotime($log['tarih']));
    }
    
    // Sonuç döndür
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'order' => $order,
        'items' => $items,
        'logs' => $logs
    ]);
    
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Veritabanı hatası: ' . $e->getMessage()]);
    exit;
}