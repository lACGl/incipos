<?php
require_once '../session_manager.php'; // Otomatik eklendi
secure_session_start();
require_once '../db_connection.php';

// Hata ayıklama için günlük kaydı ekleyelim
error_log('Bulk delete called - Raw data: ' . file_get_contents('php://input'));

// Yetki kontrolü
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

// JSON veriyi al
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Dosya yolu listesini kontrol et
if (!isset($data['files']) || !is_array($data['files']) || empty($data['files'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Geçersiz dosya listesi']);
    exit;
}

$files = $data['files'];
$root_path = dirname(dirname(__DIR__));
$results = [];
$success_count = 0;
$error_count = 0;

// Her dosya için silme işlemi yap
foreach ($files as $file_path) {
    // Dosya yolunu düzelt - /incipos prefix'ini kaldır
    $file_path = preg_replace('/^\/incipos\//', '', $file_path);
    $file_path = ltrim($file_path, '/');
    $full_path = $root_path . '/' . $file_path;
    
    // Hata ayıklama kaydı
    error_log("Processing file: {$file_path}");
    error_log("Full path: {$full_path}");
    
    // Güvenlik kontrolü - path traversal saldırılarına karşı koruma
    $canonicalPath = realpath($full_path);
    $rootPath = realpath($root_path);
    
    error_log("Canonical path: " . ($canonicalPath ?: 'false'));
    error_log("Root path: {$rootPath}");
    
    if ($canonicalPath === false || strpos($canonicalPath, $rootPath) !== 0) {
        $results[] = [
            'path' => $file_path,
            'success' => false,
            'message' => 'Geçersiz dosya yolu'
        ];
        $error_count++;
        continue;
    }
    
    // Dosya kontrolü
    if (!file_exists($full_path) || !is_file($full_path)) {
        $results[] = [
            'path' => $file_path,
            'success' => false,
            'message' => 'Dosya bulunamadı: ' . $full_path
        ];
        $error_count++;
        continue;
    }
    
    try {
        // Önce veritabanında bu resmi kullanan ürünleri kontrol et
        $relative_path = str_replace($root_path . '/', '', $full_path);
        
        // Hata ayıklama
        error_log("DB relative path: {$relative_path}");
        
        // Resim yolunu boşalt
        $update_query = "UPDATE urun_stok SET resim_yolu = NULL WHERE resim_yolu = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->execute([$relative_path]);
        
        // Dosya silme izinleri kontrol et
        if (!is_writable($full_path)) {
            $results[] = [
                'path' => $file_path,
                'success' => false,
                'message' => 'Dosya yazılabilir değil (izin hatası)'
            ];
            $error_count++;
            continue;
        }
        
        // Dosyayı sil
        if (unlink($full_path)) {
            $results[] = [
                'path' => $file_path,
                'success' => true,
                'message' => 'Dosya başarıyla silindi'
            ];
            $success_count++;
        } else {
            $results[] = [
                'path' => $file_path,
                'success' => false,
                'message' => 'Dosya silinemedi: ' . error_get_last()['message']
            ];
            $error_count++;
        }
    } catch (Exception $e) {
        $results[] = [
            'path' => $file_path,
            'success' => false,
            'message' => 'Hata: ' . $e->getMessage()
        ];
        $error_count++;
    }
}

// Sonuçları döndür
header('Content-Type: application/json');
echo json_encode([
    'success' => ($success_count > 0),
    'total' => count($files),
    'success_count' => $success_count,
    'error_count' => $error_count,
    'results' => $results
]);
?>