<?php
require_once '../session_manager.php'; // Otomatik eklendi
secure_session_start();
require_once '../db_connection.php';

header('Content-Type: application/json');

// Yetki kontrolü
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    die(json_encode(['success' => false, 'message' => 'Yetkisiz erişim']));
}

// POST verilerini al
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['lokasyon_tipi']) || !isset($data['lokasyon_id']) || !isset($data['urunler'])) {
    die(json_encode([
        'success' => false,
        'message' => 'Geçersiz veri formatı'
    ]));
}

$lokasyonTipi = $data['lokasyon_tipi']; // 'depo' veya 'magaza'
$lokasyonId = $data['lokasyon_id'];
$urunler = $data['urunler'];

try {
    // İşlem başlat
    $conn->beginTransaction();
    
    $successCount = 0;
    $errorCount = 0;
    $errors = [];
    
    foreach ($urunler as $urun) {
        $urunId = $urun['id'];
        $miktar = $urun['miktar'];
        
        // Miktar 0 ise atla
        if ($miktar <= 0) {
            continue;
        }
        
        try {
            if ($lokasyonTipi === 'depo') {
                // Depoda stok güncelle/ekle
                updateDepoStock($conn, $urunId, $lokasyonId, $miktar);
            } else if ($lokasyonTipi === 'magaza') {
                // Mağazada stok güncelle/ekle
                updateMagazaStock($conn, $urunId, $lokasyonId, $miktar);
            } else {
                throw new Exception("Geçersiz lokasyon tipi: " . $lokasyonTipi);
            }
            
            // Stok hareketi ekle
            $lokasyonAdi = '';
            if ($lokasyonTipi === 'depo') {
                // Depo adını al
                $stmtDepo = $conn->prepare("SELECT ad FROM depolar WHERE id = :id");
                $stmtDepo->bindParam(':id', $lokasyonId);
                $stmtDepo->execute();
                $lokasyonAdi = $stmtDepo->fetch(PDO::FETCH_COLUMN) ?: 'Ana Depo';
            } else {
                // Mağaza adını al
                $stmtMagaza = $conn->prepare("SELECT ad FROM magazalar WHERE id = :id");
                $stmtMagaza->bindParam(':id', $lokasyonId);
                $stmtMagaza->execute();
                $lokasyonAdi = $stmtMagaza->fetch(PDO::FETCH_COLUMN) ?: 'Mağaza';
            }
            
            $aciklama = 'Excel içe aktarma - ' . $lokasyonAdi;
            
            $stmt = $conn->prepare("INSERT INTO stok_hareketleri 
                                   (urun_id, miktar, hareket_tipi, aciklama, tarih, 
                                    " . ($lokasyonTipi === 'depo' ? 'depo_id' : 'magaza_id') . ")
                                   VALUES 
                                   (:urun_id, :miktar, 'giris', :aciklama, NOW(), :lokasyon_id)");
            $stmt->bindParam(':urun_id', $urunId);
            $stmt->bindParam(':miktar', $miktar);
            $stmt->bindParam(':lokasyon_id', $lokasyonId);
            $stmt->bindParam(':aciklama', $aciklama);
            $stmt->execute();
            
            $successCount++;
        } catch (Exception $e) {
            $errorCount++;
            $errors[] = "Ürün ID:" . $urunId . " için stok ekleme yapılamadı: " . $e->getMessage();
        }
    }
    
    // İşlemi tamamla
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => $successCount . ' ürün için stok kaydı eklendi.',
        'basarili' => $successCount,
        'hatali' => $errorCount,
        'errors' => $errors
    ]);
    
} catch (Exception $e) {
    // Hata durumunda geri al
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'Stok ekleme yapılamadı: ' . $e->getMessage()
    ]);
}

/**
 * Depo stoğunu güncelle
 */
function updateDepoStock($conn, $urunId, $depoId, $miktar) {
    // Stok var mı kontrol et
    $stmt = $conn->prepare("SELECT id, stok_miktari FROM depo_stok WHERE urun_id = :urun_id AND depo_id = :depo_id");
    $stmt->bindParam(':urun_id', $urunId);
    $stmt->bindParam(':depo_id', $depoId);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        // Stok kaydı varsa güncelle
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $newStock = $row['stok_miktari'] + $miktar;
        
        $stmt = $conn->prepare("UPDATE depo_stok SET stok_miktari = :stok_miktari, son_guncelleme = NOW() WHERE id = :id");
        $stmt->bindParam(':id', $row['id']);
        $stmt->bindParam(':stok_miktari', $newStock);
        $stmt->execute();
    } else {
        // Stok kaydı yoksa yeni ekle
        $stmt = $conn->prepare("INSERT INTO depo_stok (urun_id, depo_id, stok_miktari, son_guncelleme) VALUES (:urun_id, :depo_id, :stok_miktari, NOW())");
        $stmt->bindParam(':urun_id', $urunId);
        $stmt->bindParam(':depo_id', $depoId);
        $stmt->bindParam(':stok_miktari', $miktar);
        $stmt->execute();
    }
}

/**
 * Mağaza stoğunu güncelle
 */
function updateMagazaStock($conn, $urunId, $magazaId, $miktar) {
    // Ürünün barkodunu al
    $stmt = $conn->prepare("SELECT barkod, satis_fiyati FROM urun_stok WHERE id = :urun_id");
    $stmt->bindParam(':urun_id', $urunId);
    $stmt->execute();
    $urun = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$urun) {
        throw new Exception("Ürün bulunamadı (ID: $urunId)");
    }
    
    // Stok var mı kontrol et
    $stmt = $conn->prepare("SELECT id, stok_miktari FROM magaza_stok WHERE barkod = :barkod AND magaza_id = :magaza_id");
    $stmt->bindParam(':barkod', $urun['barkod']);
    $stmt->bindParam(':magaza_id', $magazaId);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        // Stok kaydı varsa güncelle
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $newStock = $row['stok_miktari'] + $miktar;
        
        $stmt = $conn->prepare("UPDATE magaza_stok SET stok_miktari = :stok_miktari, son_guncelleme = NOW() WHERE id = :id");
        $stmt->bindParam(':id', $row['id']);
        $stmt->bindParam(':stok_miktari', $newStock);
        $stmt->execute();
    } else {
        // Stok kaydı yoksa yeni ekle
        $stmt = $conn->prepare("INSERT INTO magaza_stok (barkod, magaza_id, stok_miktari, satis_fiyati, son_guncelleme) 
                                VALUES (:barkod, :magaza_id, :stok_miktari, :satis_fiyati, NOW())");
        $stmt->bindParam(':barkod', $urun['barkod']);
        $stmt->bindParam(':magaza_id', $magazaId);
        $stmt->bindParam(':stok_miktari', $miktar);
        $stmt->bindParam(':satis_fiyati', $urun['satis_fiyati']);
        $stmt->execute();
    }
}