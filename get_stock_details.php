<?php

session_start();
require_once 'db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    die(json_encode(['error' => true, 'message' => 'Yetkisiz erişim']));
}

try {
    $id = $_GET['id'] ?? null;
    if (!$id) {
        throw new Exception('Ürün ID gerekli');
    }

    // Depo stokunu al
    $stmt = $conn->prepare("
        SELECT * FROM depo_stok 
        WHERE urun_id = ? AND depo_id = 1
    ");
    $stmt->execute([$id]);
    $depo_stok = $stmt->fetch(PDO::FETCH_ASSOC);

// Mağaza stoklarını getir
$stmt = $conn->prepare("
    SELECT 
        m.id as magaza_id,
        m.ad as magaza_adi,
        SUM(ms.stok_miktari) as stok_miktari,
        ms.satis_fiyati
    FROM magazalar m
    LEFT JOIN magaza_stok ms ON m.id = ms.magaza_id
    LEFT JOIN urun_stok us ON ms.barkod = us.barkod
    WHERE us.id = ?
    GROUP BY m.id, m.ad, ms.satis_fiyati
    ORDER BY m.ad
");
    $stmt->execute([$id]);
    $magaza_stoklari = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Stok hareketlerini al
$stmt = $conn->prepare("
    SELECT 
        sh.*, 
        IF(sh.depo_id IS NOT NULL, 'Ana Depo', COALESCE(m.ad, 'Bilinmiyor')) as magaza_adi,
        au.kullanici_adi
    FROM stok_hareketleri sh
    LEFT JOIN magazalar m ON m.id = sh.magaza_id
    LEFT JOIN admin_user au ON sh.kullanici_id = au.id
    WHERE sh.urun_id = ?
    ORDER BY sh.tarih DESC
");
    $stmt->execute([$id]);
    $hareketler = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Toplam depo stoku
    $depo_toplam = 0;
    if ($depo_stok) {
        $depo_toplam = floatval($depo_stok['stok_miktari']);
    }

    // Toplam mağaza stoku
    $magaza_toplam = 0;
    foreach ($magaza_stoklari as $magaza) {
        $magaza_toplam += floatval($magaza['stok_miktari'] ?? 0);
    }

    // Her hareket için tarihi formatlayalım
    foreach ($hareketler as &$hareket) {
        $hareket['tarih_formatted'] = date('d.m.Y H:i:s', strtotime($hareket['tarih']));
        
        // Fiyatları formatlayalım
        $hareket['maliyet_formatted'] = $hareket['maliyet'] ? number_format($hareket['maliyet'], 2, ',', '.') . ' ₺' : '-';
        $hareket['satis_fiyati_formatted'] = $hareket['satis_fiyati'] ? number_format($hareket['satis_fiyati'], 2, ',', '.') . ' ₺' : '-';
        
        // İşlem tipini Türkçeleştirelim
        $hareket['islem_tipi'] = $hareket['hareket_tipi'] == 'giris' ? 'Giriş' : 'Çıkış';
    }

    echo json_encode([
        'success' => true,
        'depo_stok' => [
            'stok_miktari' => $depo_toplam,
            'son_guncelleme' => $depo_stok ? $depo_stok['son_guncelleme'] : null
        ],
        'magaza_stoklari' => $magaza_stoklari,
        'hareketler' => $hareketler,
        'toplam_stok' => [
            'depo' => $depo_toplam,
            'magaza' => $magaza_toplam,
            'genel_toplam' => $depo_toplam + $magaza_toplam
        ]
    ]);

} catch (Exception $e) {
    error_log('Stok detayları hatası: ' . $e->getMessage());
    echo json_encode([
        'error' => true,
        'message' => 'Stok detayları alınırken bir hata oluştu: ' . $e->getMessage()
    ]);
}