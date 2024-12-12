<?php
session_start();
require_once '../db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    die(json_encode(['success' => false, 'message' => 'Yetkisiz erişim']));
}

try {
    // POST verilerini al
    $fatura_seri = $_POST['fatura_seri'] ?? null;
    $fatura_no = $_POST['fatura_no'] ?? null;
    $tedarikci = $_POST['tedarikci'] ?? null;
    $fatura_tarihi = $_POST['fatura_tarihi'] ?? null;
    $aciklama = $_POST['aciklama'] ?? '';

    // Boş alan kontrolü
    if (empty($fatura_seri) || empty($fatura_no) || empty($tedarikci) || empty($fatura_tarihi)) {
        throw new Exception('Lütfen tüm zorunlu alanları doldurun');
    }

    // Fatura numarası benzersizlik kontrolü
    $stmt = $conn->prepare("SELECT COUNT(*) FROM alis_faturalari WHERE fatura_seri = ? AND fatura_no = ?");
    $stmt->execute([$fatura_seri, $fatura_no]);
    if ($stmt->fetchColumn() > 0) {
        throw new Exception('Bu fatura numarası zaten kullanımda');
    }

    // Faturayı ekle
    $sql = "INSERT INTO alis_faturalari (
        fatura_seri, fatura_no, tedarikci, fatura_tarihi,
        aciklama, durum, kayit_tarihi, kullanici_id
    ) VALUES (?, ?, ?, ?, ?, 'bos', NOW(), ?)";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        $fatura_seri,
        $fatura_no,
        $tedarikci,
        $fatura_tarihi,
        $aciklama,
        $_SESSION['user_id'] ?? null
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Fatura başarıyla oluşturuldu',
        'invoice_id' => $conn->lastInsertId()
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}