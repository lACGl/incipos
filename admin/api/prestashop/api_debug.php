<?php
/**
 * Prestashop API Yanıt Analizi
 * 
 * Bu script, bir ürün ID'si için Prestashop API yanıtını ayrıntılı incelemek için kullanılır.
 */

// Hata ayıklama
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Config dosyasını dahil et
require_once __DIR__ . '/config.php';

// Prestashop API sınıfını dahil et
require_once __DIR__ . '/prestashopAPI.php';

// Özel API sınıfı (ham yanıtı alabilmek için)
class CustomPrestashopAPI extends PrestashopAPI {
    
    /**
     * Ham API yanıtını alır
     * 
     * @param string $resource Kaynak adı (products, stock_availables vb.)
     * @param int|null $id Kaynak ID'si (opsiyonel)
     * @param string $queryParams Ek sorgu parametreleri
     * @return array|false HTTP kodu ve yanıt içeriği
     */
    public function getRawResponse($resource, $id = null, $queryParams = '') {
        // API URL'sini oluştur
        $url = $this->shopUrl . '/api/' . $resource;
        if ($id) {
            $url .= '/' . $id;
        }
        
        // display=full parametresi ekle (detaylı veri için)
        $url .= '?display=full&output_format=JSON';
        
        // Ek sorgu parametreleri varsa ekle
        if (!empty($queryParams) && $queryParams[0] === '?') {
            $url = str_replace('?', $queryParams . '&', $url);
        }
        
        $this->log("API isteği gönderiliyor: $url");
        
        // cURL başlat
        $curl = curl_init();
        
        // cURL ayarları
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($curl, CURLOPT_USERPWD, $this->apiKey . ':');
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        // Yönlendirmeleri takip et
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_MAXREDIRS, 5);
        
        // HTTPS için SSL sertifika doğrulamasını devre dışı bırakma
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        
        // İsteği gönder
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        
        // Debug için HTTP yanıtını logla
        $this->log("API yanıtı: HTTP $httpCode");
        if ($error) {
            $this->log("cURL hatası: $error", 'ERROR');
        }
        
        curl_close($curl);
        
        // Hata kontrolü
        if ($error) {
            $this->log("API isteği başarısız: $error", 'ERROR');
            return false;
        }
        
        return [
            'http_code' => $httpCode,
            'content' => $response,
            'error' => $error
        ];
    }
}

// Ürün ID'sini al (varsayılan olarak 22)
$productId = isset($_GET['id']) ? intval($_GET['id']) : 22;

echo "<h1>Prestashop API Ürün Yanıtı Analizi</h1>";
echo "<p>İncelenen Ürün ID: $productId</p>";

// Log dosyasının yolunu belirle
$logFile = __DIR__ . '/logs/api_debug.log';
$logDir = dirname($logFile);
if (!file_exists($logDir)) {
    mkdir($logDir, 0777, true);
}

// Prestashop API örneği oluştur
$prestashop = new CustomPrestashopAPI(PS_API_KEY, PS_SHOP_URL, $logFile);

// Raw API yanıtını al
$response = $prestashop->getRawResponse('products', $productId);

// API yanıtını göster
echo "<h2>API Yanıtı</h2>";
echo "<pre>";
print_r($response);
echo "</pre>";

// Analiz sonuçları
echo "<h2>Analiz</h2>";

if ($response === false) {
    echo "<p style='color:red'>API yanıtı alınamadı. Hata oluştu.</p>";
} else {
    // HTTP kodu
    echo "<p>HTTP Durum Kodu: " . $response['http_code'] . "</p>";
    
    // Yanıt içeriği
    if (!empty($response['content'])) {
        // JSON kontrolü
        $decoded = json_decode($response['content'], true);
        if (json_last_error() === JSON_ERROR_NONE) {
            echo "<p style='color:green'>Yanıt geçerli bir JSON formatında.</p>";
            
            // Ürün bilgileri varsa
            if (isset($decoded['product'])) {
                echo "<p style='color:green'>Ürün bilgileri mevcut.</p>";
                
                // Ürün özellikleri
                echo "<h3>Ürün Özellikleri</h3>";
                echo "<ul>";
                foreach ($decoded['product'] as $key => $value) {
                    if (!is_array($value)) {
                        echo "<li><strong>$key:</strong> $value</li>";
                    }
                }
                echo "</ul>";
            } else {
                echo "<p style='color:red'>JSON yanıtında 'product' anahtarı bulunamadı.</p>";
                
                // Alternatif anahtarları kontrol et
                $possibleKeys = array_keys($decoded);
                if (!empty($possibleKeys)) {
                    echo "<p>Mevcut anahtarlar: " . implode(", ", $possibleKeys) . "</p>";
                }
            }
        } else {
            echo "<p style='color:red'>JSON çözme hatası: " . json_last_error_msg() . "</p>";
            
            // XML olabilir mi?
            if (strpos($response['content'], '<?xml') !== false) {
                echo "<p>Yanıt XML formatında olabilir. İşte ilk 500 karakter:</p>";
                echo "<pre>" . htmlspecialchars(substr($response['content'], 0, 500)) . "</pre>";
            } else {
                echo "<p>Yanıtın ilk 500 karakteri:</p>";
                echo "<pre>" . htmlspecialchars(substr($response['content'], 0, 500)) . "</pre>";
            }
        }
    } else {
        echo "<p style='color:red'>Yanıt içeriği boş.</p>";
    }
}

// Stok kaydını ayrıca kontrol et
echo "<h2>Stok Kontrolü</h2>";
$stockResponse = $prestashop->getRawResponse('stock_availables', null, '?filter[id_product]=' . $productId);
echo "<pre>";
print_r($stockResponse);
echo "</pre>";