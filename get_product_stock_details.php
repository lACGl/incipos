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

    // Stok hareketlerini al
    $stmt = $conn->prepare("
        SELECT 
            sh.tarih,
            sh.hareket_tipi,
            sh.miktar,
            sh.maliyet as alis_fiyati,
            sh.satis_fiyati,
            sh.aciklama,
            CASE 
                WHEN sh.depo_id IS NOT NULL THEN 'Depo'
                WHEN sh.magaza_id IS NOT NULL THEN m.ad
                ELSE 'Bilinmiyor'
            END as magaza
        FROM stok_hareketleri sh
        LEFT JOIN magazalar m ON m.id = sh.magaza_id
        WHERE sh.urun_id = ?
        ORDER BY sh.tarih DESC
    ");
    $stmt->execute([$id]);
    $movements = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Depo stoklarını al
    $stmt = $conn->prepare("
        SELECT 
            ds.stok_miktari as depo_stok,
            ds.son_guncelleme
        FROM depo_stok ds
        WHERE ds.urun_id = ? AND ds.depo_id = 1
    ");
    $stmt->execute([$id]);
    $depo_stok = $stmt->fetch(PDO::FETCH_ASSOC);

    // Mağaza stoklarını al
    $stmt = $conn->prepare("
        SELECT 
            ms.stok_miktari,
            ms.satis_fiyati,
            m.ad as magaza_adi
        FROM urun_stok us
        LEFT JOIN magaza_stok ms ON ms.barkod = us.barkod
        LEFT JOIN magazalar m ON m.id = ms.magaza_id
        WHERE us.id = ?
    ");
    $stmt->execute([$id]);
    $magaza_stoklari = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Sonuçları formatla ve döndür
    $formatted_movements = array_map(function($movement) {
        return [
            'Tarih' => date('d.m.Y H:i:s', strtotime($movement['tarih'])),
            'İşlem' => $movement['hareket_tipi'] == 'giris' ? 'Giriş' : 'Çıkış',
            'Miktar' => $movement['miktar'],
            'Alış Fiyatı' => $movement['alis_fiyati'] ? number_format($movement['alis_fiyati'], 2) : '-',
            'Satış Fiyatı' => $movement['satis_fiyati'] ? number_format($movement['satis_fiyati'], 2) : '-',
            'Mağaza' => $movement['magaza'],
            'Açıklama' => $movement['aciklama']
        ];
    }, $movements);

    echo json_encode([
        'success' => true,
        'movements' => $formatted_movements,
        'depo_stok' => $depo_stok,
        'magaza_stoklari' => $magaza_stoklari
    ]);

} catch (Exception $e) {
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
}