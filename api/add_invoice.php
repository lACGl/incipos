<?php
session_start();
require_once '../db_connection.php';

header('Content-Type: application/json');

// Hata ayıklama için
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    die(json_encode(['success' => false, 'message' => 'Yetkisiz erişim']));
}

try {
    // Gelen veriyi al (hem JSON hem de POST desteği)
    $input = json_decode(file_get_contents('php://input'), true);
    
    // JSON veya POST verilerinden al
    $fatura_seri = $input['fatura_seri'] ?? $_POST['fatura_seri'] ?? null;
    $fatura_no = $input['fatura_no'] ?? $_POST['fatura_no'] ?? null;
    $tedarikci = $input['tedarikci'] ?? $_POST['tedarikci'] ?? null;
    $fatura_tarihi = $input['fatura_tarihi'] ?? $_POST['fatura_tarihi'] ?? null;
    $aciklama = $input['aciklama'] ?? $_POST['aciklama'] ?? '';

    // Debug için gelen veriyi logla
    error_log('Gelen veri: ' . print_r([
        'input' => $input,
        'post' => $_POST,
        'fatura_seri' => $fatura_seri,
        'fatura_no' => $fatura_no,
        'tedarikci' => $tedarikci,
        'fatura_tarihi' => $fatura_tarihi
    ], true));

    // Boş alan kontrolü
    if (empty($fatura_seri) || empty($fatura_no) || empty($tedarikci) || empty($fatura_tarihi)) {
        throw new Exception('Lütfen tüm zorunlu alanları doldurun');
    }

    // Tedarikçi ID'sinin geçerli olduğunu kontrol et
    $stmt = $conn->prepare("SELECT COUNT(*) FROM tedarikciler WHERE id = ?");
    $stmt->execute([$tedarikci]);
    if ($stmt->fetchColumn() == 0) {
        throw new Exception('Geçersiz tedarikçi seçimi');
    }

    // Fatura numarası benzersizlik kontrolü
    $stmt = $conn->prepare("SELECT COUNT(*) FROM alis_faturalari WHERE fatura_seri = ? AND fatura_no = ?");
    $stmt->execute([$fatura_seri, $fatura_no]);
    if ($stmt->fetchColumn() > 0) {
        throw new Exception('Bu fatura numarası zaten kullanımda');
    }

    // Fatura tarihini kontrol et
    $faturaTarihi = DateTime::createFromFormat('Y-m-d', $fatura_tarihi);
    if (!$faturaTarihi) {
        throw new Exception('Geçersiz fatura tarihi formatı');
    }

    $conn->beginTransaction();

    // Faturayı ekle
    $sql = "INSERT INTO alis_faturalari (
        fatura_seri, 
        fatura_no, 
        tedarikci, 
        fatura_tarihi,
        aciklama, 
        durum, 
        kayit_tarihi, 
        kullanici_id,
        toplam_tutar,
        kdv_tutari,
        net_tutar
    ) VALUES (
        ?, ?, ?, ?, ?, 
        'bos', 
        NOW(), 
        ?,
        0.00,
        0.00,
        0.00
    )";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        $fatura_seri,
        $fatura_no,
        $tedarikci,
        $fatura_tarihi,
        $aciklama,
        $_SESSION['user_id'] ?? null
    ]);

    $fatura_id = $conn->lastInsertId();

    // Log kaydı ekle
    $log_sql = "INSERT INTO alis_fatura_log (
        fatura_id, 
        islem_tipi, 
        aciklama, 
        kullanici_id, 
        tarih
    ) VALUES (?, 'olusturma', 'Fatura oluşturuldu', ?, NOW())";

    $stmt = $conn->prepare($log_sql);
    $stmt->execute([$fatura_id, $_SESSION['user_id'] ?? null]);

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Fatura başarıyla oluşturuldu',
        'invoice_id' => $fatura_id
    ]);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    error_log('Fatura ekleme hatası: ' . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}