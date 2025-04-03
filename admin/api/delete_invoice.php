<?php
session_start();
require_once '../db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    die(json_encode(['success' => false, 'message' => 'Yetkisiz erişim']));
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? null;

    if (!$id) {
        throw new Exception('Fatura ID gerekli');
    }

    $conn->beginTransaction();

    // Önce fatura detaylarını sil
    $stmt = $conn->prepare("DELETE FROM alis_fatura_detay WHERE fatura_id = ?");
    $stmt->execute([$id]);

    // Aktarım detaylarını sil
    $stmt = $conn->prepare("DELETE FROM alis_fatura_detay_aktarim WHERE fatura_id = ?");
    $stmt->execute([$id]);

    // Ana faturayı sil
    $stmt = $conn->prepare("DELETE FROM alis_faturalari WHERE id = ?");
    $stmt->execute([$id]);

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Fatura başarıyla silindi'
    ]);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}