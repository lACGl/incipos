<?php
require_once '../session_manager.php'; // Otomatik eklendi
secure_session_start();
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

// Parametreleri kontrol et
if (!isset($data['parent_path']) || !isset($data['folder_name']) || 
    empty($data['parent_path']) || empty($data['folder_name'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Geçersiz parametreler']);
    exit;
}

// Path ve klasör adını al
$parent_path = $data['parent_path'];
$folder_name = $data['folder_name'];

// Klasör adını temizle (sadece alfanümerik, tire ve alt çizgi)
$folder_name = preg_replace('/[^a-zA-Z0-9_-]/', '', $folder_name);

// Geçerli bir klasör adı yoksa
if (empty($folder_name)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Geçersiz klasör adı']);
    exit;
}

// Root dizinini belirle
$root_path = dirname(dirname(__DIR__)); // incipos dizinine çıkmak için

// Ana dizini belirle
if ($parent_path === 'img') {
    $target_dir = $root_path . '/files/img/' . $folder_name;
} elseif ($parent_path === 'pdf') {
    $target_dir = $root_path . '/files/pdf/' . $folder_name;
} elseif ($parent_path === 'files') {
    $target_dir = $root_path . '/files/' . $folder_name;
} elseif (strpos($parent_path, 'files/') === 0) {
    $target_dir = $root_path . '/' . $parent_path . '/' . $folder_name;
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Geçersiz ana dizin']);
    exit;
}

// Klasör zaten varsa
if (file_exists($target_dir)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Klasör zaten mevcut', 'folder_name' => $folder_name]);
    exit;
}

// Klasörü oluştur
try {
    if (mkdir($target_dir, 0755, true)) {
        // İşlem başarılı log tut
        $log_query = "INSERT INTO sistem_ayarlari (anahtar, deger, aciklama) 
                     VALUES (:anahtar, :deger, :aciklama)
                     ON DUPLICATE KEY UPDATE deger = :deger, aciklama = :aciklama";
        
        $log_key = 'log_folder_create_' . date('YmdHis');
        $log_value = $parent_path . '/' . $folder_name;
        $log_desc = 'Klasör oluşturma: ' . $folder_name . ' (' . $_SESSION['kullanici_adi'] . ' tarafından)';
        
        $stmt = $conn->prepare($log_query);
        $stmt->bindParam(':anahtar', $log_key);
        $stmt->bindParam(':deger', $log_value);
        $stmt->bindParam(':aciklama', $log_desc);
        $stmt->execute();
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Klasör başarıyla oluşturuldu', 'folder_name' => $folder_name]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Klasör oluşturulamadı']);
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Hata: ' . $e->getMessage()]);
}
?>