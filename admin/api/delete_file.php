<?php
session_start();
require_once '../db_connection.php';

// Yetki kontrolü
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

// JSON veriyi al
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!isset($data['path']) || empty($data['path'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Geçersiz dosya yolu']);
    exit;
}

// Dosya yolunu temizle ve kontrol et
$file_path = $data['path'];
$file_path = ltrim($file_path, '/');
$root_path = dirname(dirname(__DIR__));
$full_path = $root_path . '/' . $file_path;

// Güvenlik kontrolü - path traversal saldırılarına karşı koruma
$canonicalPath = realpath($full_path);
$rootPath = realpath($root_path);

if ($canonicalPath === false || strpos($canonicalPath, $rootPath) !== 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Geçersiz dosya yolu']);
    exit;
}

// Dosyayı sil
if (file_exists($full_path) && is_file($full_path)) {
    try {
        // Önce veritabanında bu resmi kullanan ürünleri kontrol et
        $relative_path = str_replace($root_path . '/', '', $full_path);
        
        // Resim yolunu boşalt
        $update_query = "UPDATE urun_stok SET resim_yolu = NULL WHERE resim_yolu = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->execute([$relative_path]);
        
        // Dosyayı sil
        if (unlink($full_path)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Dosya başarıyla silindi']);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Dosya silinemedi']);
        }
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Hata: ' . $e->getMessage()]);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Dosya bulunamadı']);
}
?>