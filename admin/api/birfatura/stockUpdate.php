<?php
// Yardımcı fonksiyonları dahil et
if (!function_exists('sendResponse')) {
    require_once __DIR__ . '/helpers.php';
}

// API isteğini logla
if (!isset($log_file)) {
    $log_file = __DIR__ . '/api_log.txt';
}
file_put_contents($log_file, date('Y-m-d H:i:s') . " - stockUpdate endpoint çalıştırılıyor\n", FILE_APPEND);

// Token kontrolü
$token = getFlexibleToken($headers);
$expected_token = 'KeHxXtvWK6QovGL';

if ($token !== $expected_token) {
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - Token hatası. Beklenen: $expected_token, Gelen: $token\n", FILE_APPEND);
    sendResponse(['error' => 'Unauthorized access'], 401);
}

// POST metodu kontrolü
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(['error' => 'Method not allowed'], 405);
}

// JSON verisini al
$jsonData = file_get_contents('php://input');
$data = json_decode($jsonData, true);

file_put_contents($log_file, date('Y-m-d H:i:s') . " - Stok güncelleme isteği: " . $jsonData . "\n", FILE_APPEND);

// Stok fonksiyonlarını dahil et
require_once __DIR__ . '/stock_functions.php';

// Zorunlu alanları kontrol et
$productId = $data['productId'] ?? null;
$barcode = $data['barcode'] ?? null;
$quantity = $data['quantity'] ?? null;
$warehouseId = $data['warehouseId'] ?? null;

if (!$productId && !$barcode) {
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - Ürün ID veya barkod gerekli\n", FILE_APPEND);
    sendResponse(['error' => 'ProductId veya barcode parametresi gerekli'], 400);
}

if ($quantity === null) {
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - Miktar parametresi gerekli\n", FILE_APPEND);
    sendResponse(['error' => 'Quantity parametresi gerekli'], 400);
}

// Veritabanı bağlantısı
try {
    $dbname = 'incikir2_pos';
    $username = 'incikir2_posadmin';
    $password = 'vD3YjbzpPYsc';
    
    $db = new PDO('mysql:host=localhost;dbname='.$dbname, $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("SET NAMES utf8");
    
    // Ürünü sorgula
    $query = "SELECT * FROM urun_stok WHERE ";
    $params = [];
    
    if ($productId) {
        $query .= "id = ?";
        $params[] = $productId;
    } else {
        $query .= "barkod = ?";
        $params[] = $barcode;
    }
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        file_put_contents($log_file, date('Y-m-d H:i:s') . " - Ürün bulunamadı: " . ($productId ?: $barcode) . "\n", FILE_APPEND);
        sendResponse(['error' => 'Ürün bulunamadı'], 404);
    }
    
    // Stok güncelleme
    $updateType = $data['updateType'] ?? 'set'; // 'set', 'add', veya 'subtract'
    $currentStock = floatval($product['stok_miktar']);
    $newStock = $currentStock;
    
    switch ($updateType) {
        case 'set':
            $newStock = floatval($quantity);
            break;
        case 'add':
            $newStock = $currentStock + floatval($quantity);
            break;
        case 'subtract':
            $newStock = $currentStock - floatval($quantity);
            if ($newStock < 0) $newStock = 0; // Negatif stok engelleme
            break;
        default:
            file_put_contents($log_file, date('Y-m-d H:i:s') . " - Geçersiz updateType: $updateType\n", FILE_APPEND);
            sendResponse(['error' => 'Geçersiz updateType değeri'], 400);
    }
    
    // Stok güncelleme sorgusu
    $updateQuery = "UPDATE urun_stok SET stok_miktar = ?, son_guncelleme = NOW() WHERE id = ?";
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->execute([$newStock, $product['id']]);
    
    // Stok hareketi kaydet
    $logQuery = "INSERT INTO stok_hareketleri (urun_id, eski_miktar, yeni_miktar, hareket_tipi, aciklama, tarih) 
                 VALUES (?, ?, ?, ?, ?, NOW())";
    $logStmt = $db->prepare($logQuery);
    $logStmt->execute([
        $product['id'],
        $currentStock,
        $newStock,
        $updateType,
        'Birfatura API ile güncellendi'
    ]);
    
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - Stok güncellendi: Ürün ID: {$product['id']}, Eski: $currentStock, Yeni: $newStock\n", FILE_APPEND);
    
    // Başarılı yanıt
    $response = [
        "success" => true,
        "message" => "Ürün stok bilgileri güncellendi",
        "data" => [
            "productId" => $product['id'],
            "barcode" => $product['barkod'],
            "productName" => $product['ad'],
            "previousStock" => $currentStock,
            "currentStock" => $newStock,
            "updateType" => $updateType
        ]
    ];
    
    sendResponse($response);
} 
catch (PDOException $e) {
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - Veritabanı hatası: " . $e->getMessage() . "\n", FILE_APPEND);
    sendResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
}
?>