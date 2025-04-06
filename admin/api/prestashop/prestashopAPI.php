<?php
/**
 * Prestashop API İletişim Sınıfı (WebID = Prestashop ID için düzeltilmiş)
 * 
 * Bu sınıf Prestashop API ile iletişim kurarak ürün, stok ve fiyat güncellemelerini yapar
 */
class PrestashopAPI {
    /**
     * @var string API anahtarı
     */
    private $apiKey;
    
    /**
     * @var string Prestashop URL'si
     */
    private $shopUrl;
    
    /**
     * @var string Log dosyası
     */
    private $logFile;
    
    /**
     * Yapıcı metod
     * 
     * @param string $apiKey API anahtarı
     * @param string $shopUrl Prestashop URL'si
     * @param string $logFile Log dosyası yolu
     */
    public function __construct($apiKey, $shopUrl, $logFile = null) {
        $this->apiKey = $apiKey;
        // URL sonunda / karakteri olup olmadığını kontrol et
        $this->shopUrl = rtrim($shopUrl, '/');
        $this->logFile = $logFile ?: __DIR__ . '/logs/prestashop_sync.log';
        
        // Log klasörünü oluştur
        $logDir = dirname($this->logFile);
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        // Log dosyasını başlat
        $this->log("PrestashopAPI başlatıldı - " . date('Y-m-d H:i:s'));
    }
    
    /**
     * Log dosyasına mesaj yazar
     * 
     * @param string $message Log mesajı
     * @param string $level Log seviyesi (INFO, ERROR, WARNING)
     * @return void
     */
    public function log($message, $level = 'INFO') {
        $date = date('Y-m-d H:i:s');
        $logMessage = "[$date] [$level] $message" . PHP_EOL;
        file_put_contents($this->logFile, $logMessage, FILE_APPEND);
    }
    
    /**
     * API isteği gönderir
     * 
     * @param string $resource Kaynak adı (products, stock_availables vb.)
     * @param string $method HTTP metodu (GET, PUT, POST, DELETE)
     * @param int $id Kaynak ID'si (opsiyonel)
     * @param mixed $data İstek verisi (opsiyonel)
     * @return mixed İstek sonucu
     */
    public function request($resource, $method = 'GET', $id = null, $data = null) {
        // API URL'sini oluştur
        $url = $this->shopUrl . '/api/' . $resource;
        if ($id) {
            $url .= '/' . $id;
        }
        
        // display=full parametresi ekle (detaylı veri için)
        $url .= '?display=full&output_format=JSON';
        
        // cURL başlat
        $curl = curl_init();
        
        // cURL ayarları
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($curl, CURLOPT_USERPWD, $this->apiKey . ':');
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        
        // Yönlendirmeleri takip et
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_MAXREDIRS, 5);
        
        // HTTPS için SSL sertifika doğrulamasını devre dışı bırakma
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        
        // İstek verisini ekle
        if ($data && ($method == 'POST' || $method == 'PUT')) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        // İsteği gönder
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        
        // Debug için HTTP yanıtını logla
        $this->log("API isteği: $url [HTTP $httpCode]");
        if ($error) {
            $this->log("cURL hatası: $error", 'ERROR');
        }
        
        curl_close($curl);
        
        // Hata kontrolü
        if ($error) {
            $this->log("API isteği başarısız: $error", 'ERROR');
            return false;
        }
        
        // HTTP kodu kontrolü
        if ($httpCode >= 400) {
            $this->log("API hatası (HTTP $httpCode): $response", 'ERROR');
            return false;
        }
        
        // Boş yanıt kontrolü
        if (empty($response)) {
            $this->log("API'den boş yanıt alındı", 'ERROR');
            return false;
        }
        
        // JSON yanıtını çöz
        $decodedResponse = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log("JSON çözme hatası: " . json_last_error_msg() . " Yanıt: " . substr($response, 0, 500), 'ERROR');
            return false;
        }
        
        return $decodedResponse;
    }
    
    /**
     * Prestashop'ta ürün olup olmadığını kontrol eder (ID ile)
     * 
     * @param int $productId Prestashop ürün ID'si
     * @return bool Ürün var mı?
     */
    public function productExists($productId) {
        $productData = $this->request('products', 'GET', $productId);
        return ($productData && isset($productData['product']));
    }
    
    /**
     * Ürün fiyatını günceller
     * 
     * @param int $productId Ürün ID'si
     * @param float $price Yeni fiyat
     * @return bool Başarılı mı?
     */
    public function updateProductPrice($productId, $price) {
        // Önce mevcut ürün bilgilerini al
        $productData = $this->request('products', 'GET', $productId);
        
        if (!$productData || !isset($productData['product'])) {
            $this->log("Ürün bilgileri alınamadı: $productId", 'ERROR');
            return false;
        }
        
        // Sadece fiyat bilgisini güncelle
        $updatedData = [
            'product' => [
                'id' => $productId,
                'price' => $price
            ]
        ];
        
        // API'ye güncelleme isteği gönder
        $response = $this->request('products', 'PUT', $productId, $updatedData);
        
        if (!$response) {
            $this->log("Fiyat güncellenemedi: $productId", 'ERROR');
            return false;
        }
        
        $this->log("Fiyat güncellendi: $productId -> $price");
        return true;
    }
    
    /**
     * Ürün stoğunu günceller
     * 
     * @param int $productId Ürün ID'si
     * @param int $quantity Yeni stok miktarı
     * @return bool Başarılı mı?
     */
    public function updateProductStock($productId, $quantity) {
        // Önce ilgili stok kaydını bul
        $productData = $this->request('products', 'GET', $productId);
        
        if (!$productData || !isset($productData['product'])) {
            $this->log("Ürün bilgileri alınamadı: $productId", 'ERROR');
            return false;
        }
        
        // Stok kaydı ID'sini al
        $stockId = $productData['product']['associations']['stock_availables'][0]['id'];
        
        if (!$stockId) {
            $this->log("Stok kaydı bulunamadı: $productId", 'ERROR');
            return false;
        }
        
        // Stok güncelleme verisi
        $stockData = [
            'stock_available' => [
                'id' => $stockId,
                'quantity' => $quantity
            ]
        ];
        
        // API'ye stok güncelleme isteği gönder
        $response = $this->request('stock_availables', 'PUT', $stockId, $stockData);
        
        if (!$response) {
            $this->log("Stok güncellenemedi: $productId (StockID: $stockId)", 'ERROR');
            return false;
        }
        
        $this->log("Stok güncellendi: $productId -> $quantity");
        return true;
    }
    
    /**
     * Ürünün hem fiyatını hem stoğunu günceller
     * 
     * @param int $productId Prestashop ürün ID'si
     * @param float $price Yeni fiyat
     * @param int $quantity Yeni stok miktarı
     * @return bool Başarılı mı?
     */
    public function updateProduct($productId, $price, $quantity) {
        // Ürünü doğrula
        if (!$this->productExists($productId)) {
            $this->log("Ürün bulunamadı, güncelleme yapılamadı: $productId", 'ERROR');
            return false;
        }
        
        // Fiyat ve stok güncelle
        $priceUpdated = $this->updateProductPrice($productId, $price);
        $stockUpdated = $this->updateProductStock($productId, $quantity);
        
        // Her iki güncelleme de başarılı mı?
        return $priceUpdated && $stockUpdated;
    }
    
    /**
     * Kombinasyonlu ürünleri kontrol eder ve günceller
     * 
     * @param int $productId Prestashop ürün ID'si
     * @param float $price Yeni fiyat
     * @param int $quantity Yeni stok miktarı
     * @return bool Başarılı mı?
     */
    public function handleCombinationProducts($productId, $price, $quantity) {
        // Ürün bilgilerini al
        $productData = $this->request('products', 'GET', $productId);
        
        if (!$productData || !isset($productData['product'])) {
            $this->log("Ürün bilgileri alınamadı: $productId", 'ERROR');
            return false;
        }
        
        // Kombinasyonları kontrol et
        if (isset($productData['product']['associations']['combinations']) && count($productData['product']['associations']['combinations']) > 0) {
            $this->log("Kombinasyonlu ürün tespit edildi: $productId", 'INFO');
            
            // Bu ürün için fiyat güncellemesi
            $priceUpdated = $this->updateProductPrice($productId, $price);
            
            // Tüm kombinasyonların stoklarını topla ve güncelle
            $totalStock = 0;
            foreach ($productData['product']['associations']['combinations'] as $combination) {
                $combinationId = $combination['id'];
                $combinationData = $this->request('combinations', 'GET', $combinationId);
                
                if (!$combinationData || !isset($combinationData['combination'])) {
                    continue;
                }
                
                // Kombinasyon için stok güncelle
                $stockId = $combinationData['combination']['associations']['stock_availables'][0]['id'];
                
                // Stok güncelleme
                $stockData = [
                    'stock_available' => [
                        'id' => $stockId,
                        'quantity' => $quantity
                    ]
                ];
                
                $response = $this->request('stock_availables', 'PUT', $stockId, $stockData);
                $totalStock += $quantity;
            }
            
            $this->log("Kombinasyonlu ürün stoğu güncellendi: $productId -> Toplam: $totalStock");
            return $priceUpdated;
        } else {
            // Normal ürün güncelleme
            return $this->updateProduct($productId, $price, $quantity);
        }
    }
}