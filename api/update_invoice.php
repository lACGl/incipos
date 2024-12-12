<?php
session_start();
require_once '../db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    die(json_encode(['success' => false, 'message' => 'Yetkisiz erişim']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'Geçersiz istek metodu']));
}

try {
    $fatura_id = $_POST['fatura_id'] ?? null;
    $fatura_seri = $_POST['fatura_seri'] ?? null;
    $fatura_no = $_POST['fatura_no'] ?? null;
    $tedarikci = $_POST['tedarikci'] ?? null;
    $fatura_tarihi = $_POST['fatura_tarihi'] ?? null;
    $aciklama = $_POST['aciklama'] ?? '';

    if (!$fatura_id || !$fatura_seri || !$fatura_no || !$tedarikci || !$fatura_tarihi) {
        throw new Exception('Zorunlu alanları doldurun');
    }

    // Fatura numarası benzersizlik kontrolü (kendi ID'si hariç)
    $stmt = $conn->prepare("
        SELECT COUNT(*) FROM alis_faturalari 
        WHERE (fatura_seri = ? AND fatura_no = ?) 
        AND id != ?
    ");
    $stmt->execute([$fatura_seri, $fatura_no, $fatura_id]);
    if ($stmt->fetchColumn() > 0) {
        throw new Exception('Bu fatura numarası zaten kullanımda');
    }

    // Faturayı güncelle
    $stmt = $conn->prepare("
        UPDATE alis_faturalari 
        SET fatura_seri = ?,
            fatura_no = ?,
            tedarikci = ?,
            fatura_tarihi = ?,
            aciklama = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $fatura_seri,
        $fatura_no,
        $tedarikci,
        $fatura_tarihi,
        $aciklama,
        $fatura_id
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Fatura başarıyla güncellendi'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}