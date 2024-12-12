<?php
session_start();
require_once '../db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    die(json_encode(['success' => false, 'message' => 'Yetkisiz erişim']));
}

$id = $_GET['id'] ?? null;

if (!$id) {
    die(json_encode(['success' => false, 'message' => 'Fatura ID gerekli']));
}

try {
    $stmt = $conn->prepare("
        SELECT f.*, t.ad as tedarikci_adi
        FROM alis_faturalari f
        LEFT JOIN tedarikciler t ON f.tedarikci = t.id
        WHERE f.id = ?
    ");
    $stmt->execute([$id]);
    $fatura = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$fatura) {
        throw new Exception('Fatura bulunamadı');
    }

    echo json_encode([
        'success' => true,
        'fatura' => $fatura
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}