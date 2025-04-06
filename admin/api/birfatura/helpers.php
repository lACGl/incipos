<?php
// Ortak yardımcı fonksiyonlar
if (!function_exists('getAllHeaders')) {
    function getAllHeaders() {
        if (function_exists('apache_request_headers')) {
            return apache_request_headers();
        }
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $header = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $headers[$header] = $value;
            }
        }
        return $headers;
    }
}

if (!function_exists('getFlexibleToken')) {
    function getFlexibleToken($headers) {
        if (isset($headers['Authorization']) && !empty($headers['Authorization'])) {
            $auth = trim($headers['Authorization']);
            if (preg_match('/Bearer\s+(\S+)/i', $auth, $matches)) {
                return $matches[1];
            }
            return $auth;
        }
        if (isset($headers['Token']) && !empty($headers['Token'])) {
            return trim($headers['Token']);
        }
        return null;
    }
}

if (!function_exists('sendResponse')) {
    function sendResponse($data, $statusCode = 200) {
        global $log_file;
        
        // JSON dönüşümünde hataları kontrol et
        $json_output = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            // JSON encode hatası varsa logla
            file_put_contents($log_file, date('Y-m-d H:i:s') . " - JSON encode hatası: " . json_last_error_msg() . "\n", FILE_APPEND);
            
            // Hataya neden olan değerleri belirle ve temizle
            // Belirli anahtar değerlerde sorun olabilir, onları kontrol edelim
            $cleanData = cleanDataForJson($data);
            $json_output = json_encode($cleanData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            
            // Hala hata varsa, boş bir yanıt dön
            if (json_last_error() !== JSON_ERROR_NONE) {
                $json_output = json_encode(["error" => "JSON encoding error: " . json_last_error_msg()]);
            }
        }
        
        file_put_contents($log_file, date('Y-m-d H:i:s') . " - Gönderilen yanıt: " . $json_output . "\n", FILE_APPEND);
        
        if (function_exists('ob_clean')) {
            ob_clean(); // Önceki çıktıları temizle
        }
        
        http_response_code($statusCode);
        header('Content-Type: application/json');
        
        // XML hatalarını önlemek için ekstra header ekleyelim
        header('X-Content-Type-Options: nosniff');
        
        echo $json_output;
        
        file_put_contents($log_file, date('Y-m-d H:i:s') . " - Çıkış yapıldı.\n", FILE_APPEND);
        exit;
    }
}

// JSON encode edilemeyecek verileri temizlemek için fonksiyon
if (!function_exists('cleanDataForJson')) {
    function cleanDataForJson($data) {
        if (is_array($data)) {
            $cleanData = [];
            foreach ($data as $key => $value) {
                $cleanData[$key] = cleanDataForJson($value);
            }
            return $cleanData;
        } elseif (is_object($data)) {
            $vars = get_object_vars($data);
            $cleanVars = [];
            foreach ($vars as $key => $value) {
                $cleanVars[$key] = cleanDataForJson($value);
            }
            return (object) $cleanVars;
        } elseif (is_string($data)) {
            // UTF-8 olmayan karakterleri temizle
            $data = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x80-\x9F]/u', '', $data);
            
            // UTF-8 uyumluluğunu kontrol et
            if (!mb_check_encoding($data, 'UTF-8')) {
                // UTF-8 olmayan string'i UTF-8'e dönüştür veya temizle
                $data = mb_convert_encoding($data, 'UTF-8', 'auto');
                if (!$data) {
                    return "";
                }
            }
            return $data;
        } else {
            return $data;
        }
    }
}
?>