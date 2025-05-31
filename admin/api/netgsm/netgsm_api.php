<?php
/**
 * NetGSM API Helper Functions - Tam Düzeltilmiş Versiyon
 * SMS gönderim ve bakiye sorgu sorunları giderildi
 */

class NetGSMAPI {
    private $username;
    private $password;
    private $msgHeader;
    private $conn;

    /**
     * Initialize NetGSM API with credentials
     * @param PDO $conn Database connection
     */
    public function __construct($conn) {
        // Get credentials from system settings
        $this->conn = $conn;
        $this->loadCredentials();
    }

    /**
     * Load NetGSM credentials from system settings
     */
    private function loadCredentials() {
        try {
            $query = "SELECT deger FROM sistem_ayarlari WHERE anahtar = :anahtar";
            
            // Get username
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(':anahtar', 'netgsm_username', PDO::PARAM_STR);
            $stmt->execute();
            $this->username = $stmt->fetchColumn() ?: '4526060578'; // Default or from DB
            
            // Get password
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(':anahtar', 'netgsm_password', PDO::PARAM_STR);
            $stmt->execute();
            $this->password = $stmt->fetchColumn() ?: 'M1-43nvE'; // Default or from DB
            
            // Get message header
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(':anahtar', 'netgsm_header', PDO::PARAM_STR);
            $stmt->execute();
            $this->msgHeader = $stmt->fetchColumn() ?: 'INCIKIRTSYE'; // Default or from DB
            
        } catch (PDOException $e) {
            error_log("NetGSM credential loading error: " . $e->getMessage());
            // Use defaults if DB access fails
            $this->username = '4526060578';
            $this->password = 'M1-43nvE';
            $this->msgHeader = 'INCIKIRTSYE';
        }
    }

    /**
     * Send SMS to a phone number - Düzeltilmiş versiyon
     * @param string $phoneNumber Recipient phone number
     * @param string $message Message text
     * @return array Response status and details
     */
    public function sendSMS($phoneNumber, $message) {
        // Format phone number (remove leading zero and add country code if needed)
        $phoneNumber = $this->formatPhoneNumber($phoneNumber);
        
        // Set commercial flag to 0 (non-commercial informational SMS)
        $filter = '0';
        
        try {
            // NetGSM doğrudan POST parametreleri bekler, API dizisi olarak göndermiyoruz
            $postFields = array(
                'usercode' => $this->username,
                'password' => $this->password,
                'gsmno' => $phoneNumber,
                'message' => $message,
                'msgheader' => $this->msgHeader,
                'filter' => $filter
            );
            
            // Debug: API çağrı parametrelerini logla
            error_log("NetGSM API Parametreleri: " . print_r($postFields, true));
            
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://api.netgsm.com.tr/sms/send/get',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => false, // SSL sertifika doğrulamasını devre dışı bırak
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => http_build_query($postFields), // HTTP yöntemi ile uyumlu şekilde URL-encoded gönder
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/x-www-form-urlencoded',
                ),
            ));
            
            $response = curl_exec($curl);
            $err = curl_error($curl);
            $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);
            
            // Tam API yanıtını logla
            error_log("NetGSM API yanıtı: " . $response);
            error_log("NetGSM HTTP kodu: " . $http_code);
            
            if ($err) {
                error_log("NetGSM cURL Error: " . $err);
                return array(
                    'success' => false,
                    'message' => "cURL Error: $err",
                    'debug' => array(
                        'url' => 'https://api.netgsm.com.tr/sms/send/get',
                        'params' => $postFields,
                        'error' => $err
                    )
                );
            }
            
            // Log the SMS activity
            $this->logSMSActivity($phoneNumber, $message, $response);
            
            // Parse and return the response
            return $this->parseResponse($response);
            
        } catch (Exception $e) {
            error_log("NetGSM SMS sending error: " . $e->getMessage());
            return array(
                'success' => false,
                'message' => "Error: " . $e->getMessage(),
                'debug' => array(
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                )
            );
        }
    }
    
    /**
     * Format phone number for NetGSM - Düzeltilmiş versiyon
     * @param string $phoneNumber Original phone number
     * @return string Formatted phone number
     */
    private function formatPhoneNumber($phoneNumber) {
        // Remove any non-numeric characters
        $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        // If starts with 0, remove it
        if (substr($phoneNumber, 0, 1) === '0') {
            $phoneNumber = substr($phoneNumber, 1);
        }
        
        // NetGSM için gerekli: Numaranın 10 haneli olduğunu ve Türkiye ülke kodu olmadığını kontrol et
        if (strlen($phoneNumber) == 10) {
            // Eğer 10 haneli bir numara ise (5XX XXX XX XX), başına 90 ekle
            $phoneNumber = '90' . $phoneNumber;
        } else if (strlen($phoneNumber) == 11 && substr($phoneNumber, 0, 1) === '9') {
            // Eğer 11 haneli ve 9 ile başlıyorsa, 0 ekle (yanlış formatlanmış olabilir)
            $phoneNumber = '9' . $phoneNumber;
        } else if (strlen($phoneNumber) == 12 && substr($phoneNumber, 0, 2) === '90') {
            // Zaten doğru formatta (90 5XX XXX XX XX)
            // Değişiklik yapma
        } else {
            // Diğer durumlarda, 90 ile başladığından emin ol
            if (substr($phoneNumber, 0, 2) !== '90') {
                $phoneNumber = '90' . $phoneNumber;
            }
        }
        
        error_log("Formatlanmış telefon: " . $phoneNumber); // Debug için
        return $phoneNumber;
    }
    
    /**
     * Parse NetGSM API response - Düzeltilmiş versiyon
     * @param string $response API response
     * @return array Parsed response
     */
    private function parseResponse($response) {
        // NetGSM yanıtını temizle (boşluk, satır sonu vb.)
        $response = trim($response);
        
        // Yanıt boş ise
        if (empty($response)) {
            return array(
                'success' => false,
                'message' => 'Boş API yanıtı alındı.',
                'raw_response' => $response
            );
        }
        
        // "00" ile başlayan yanıtlar başarılı gönderimleri gösterir
        // Format genellikle "00 {işlem_id}" şeklindedir
        if (strpos($response, '00') === 0) {
            $parts = explode(' ', $response);
            $jobId = isset($parts[1]) ? $parts[1] : 'Bilinmiyor';
            
            return array(
                'success' => true,
                'message' => 'SMS başarıyla gönderildi.',
                'job_id' => $jobId,
                'raw_response' => $response
            );
        }
        
        // Tek başına "00" da başarı kodu olabilir
        if ($response === '00') {
            return array(
                'success' => true,
                'message' => 'SMS başarıyla gönderildi.',
                'job_id' => 'Bilinmiyor',
                'raw_response' => $response
            );
        }
        
        // Başarılı gönderimlerde NetGSM genellikle 10+ haneli sayısal bir işlem ID'si döndürür
        if (preg_match('/^\d{10,}$/', $response)) {
            return array(
                'success' => true,
                'message' => 'SMS başarıyla gönderildi.',
                'job_id' => $response,
                'raw_response' => $response
            );
        }
        
        // Hata kodlarını işle
        switch ($response) {
            case '01':
                return array(
                    'success' => false,
                    'message' => 'Hata: Kredi yetersiz.',
                    'raw_response' => $response
                );
            case '02':
                return array(
                    'success' => false,
                    'message' => 'Hata: Geçersiz SMS başlığı.',
                    'raw_response' => $response
                );
            case '03':
                return array(
                    'success' => false,
                    'message' => 'Hata: Mesaj metni eksik veya geçersiz.',
                    'raw_response' => $response
                );
            case '04':
                return array(
                    'success' => false,
                    'message' => 'Hata: GSM numarası geçersiz.',
                    'raw_response' => $response
                );
            case '05':
                return array(
                    'success' => false,
                    'message' => 'Hata: SMS gönderilemedi.',
                    'raw_response' => $response
                );
            case '30':
                return array(
                    'success' => false,
                    'message' => 'Hata: Geçersiz kullanıcı adı veya API erişim izni.',
                    'raw_response' => $response
                );
            default:
                // Diğer yanıtları da kontrol et
                if (strpos($response, 'Error') !== false || strpos($response, 'Hata') !== false) {
                    return array(
                        'success' => false,
                        'message' => 'API Hatası: ' . $response,
                        'raw_response' => $response
                    );
                }
                
                // API yanıtı anlaşılamadıysa
                return array(
                    'success' => false,
                    'message' => 'Bilinmeyen API yanıtı: ' . $response,
                    'raw_response' => $response
                );
        }
    }
    
    /**
     * Log SMS activity to database - Düzeltilmiş versiyon
     * @param string $phoneNumber Recipient phone number
     * @param string $message SMS message
     * @param string $response API response
     */
    private function logSMSActivity($phoneNumber, $message, $response) {
        try {
            $query = "INSERT INTO sms_log (
                        telefon, mesaj, yanit, tarih, kullanici_id
                      ) VALUES (
                        :telefon, :mesaj, :yanit, NOW(), :kullanici_id
                      )";
                      
            $stmt = $this->conn->prepare($query);
            $telefon = $phoneNumber;
            $mesaj = $message;
            $yanit = $response;
            $kullanici_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
            
            $stmt->bindParam(':telefon', $telefon, PDO::PARAM_STR);
            $stmt->bindParam(':mesaj', $mesaj, PDO::PARAM_STR);
            $stmt->bindParam(':yanit', $yanit, PDO::PARAM_STR);
            $stmt->bindParam(':kullanici_id', $kullanici_id, PDO::PARAM_INT);
            $stmt->execute();
            
        } catch (Exception $e) {
            error_log("SMS log error: " . $e->getMessage());
        }
    }
    
    /**
     * Get SMS credit balance from NetGSM - XML formatında düzeltilmiş versiyon
     * @return array Balance information
     */
    public function getBalance() {
    try {
        // JSON formatında istek hazırla
        $postData = json_encode([
            'usercode' => $this->username,
            'password' => $this->password,
            'stip' => 1, // stip=1 olarak değiştirildi (daha detaylı bakiye bilgisi için)
            'appkey' => 'xxxx'
        ]);
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.netgsm.com.tr/balance',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
            ],
        ]);
        
        $response = curl_exec($curl);
        $err = curl_error($curl);
        $httpStatus = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        // Debug için yanıtı logla
        error_log("NetGSM Bakiye API yanıtı: " . $response);
        error_log("NetGSM Bakiye API HTTP kodu: " . $httpStatus);
        
        if ($err) {
            return [
                'success' => false,
                'message' => "cURL Error: $err",
                'raw_response' => null
            ];
        }
        
        // JSON yanıtını parse et
        $data = json_decode($response, true);
        
        // stip=1 formatı için (balance dizisi içinde bakiye bilgileri)
        if ($data && isset($data['balance']) && is_array($data['balance'])) {
            $results = [];
            $totalSmsCount = 0;
            $balance_name = "";
            
            foreach ($data['balance'] as $item) {
                if (isset($item['amount']) && isset($item['balance_name'])) {
                    $amount = $item['amount'];
                    $name = $item['balance_name'];
                    
                    $results[] = [
                        'amount' => $amount,
                        'name' => $name
                    ];
                    
                    // SMS adedini bul
                    if (strpos($name, 'SMS') !== false) {
                        $totalSmsCount = (int)$amount;
                        $balance_name = $name;
                    }
                }
            }
            
            return [
                'success' => true,
                'credit' => $totalSmsCount, // Kredi bakiyesi
                'unit_credit' => $totalSmsCount, // Birim adedi
                'unit_price' => 'Bilinmiyor', // Birim fiyatı
                'details' => $results,
                'raw_response' => $response,
                'balance_name' => $balance_name
            ];
        }
        
        // stip=2 formatı için (code ve balance ile gelen yanıt)
        else if ($data && isset($data['code']) && $data['code'] === '00' && isset($data['balance'])) {
            return [
                'success' => true,
                'credit' => str_replace(',', '.', $data['balance']), // Virgülü noktaya çevir
                'unit_credit' => str_replace(',', '.', $data['balance']), // Aynı değeri kullan
                'unit_price' => 'Bilinmiyor',
                'raw_response' => $response
            ];
        }
        
        // Diğer formatlar veya hata durumları
        return [
            'success' => false,
            'message' => 'Geçersiz bakiye yanıtı formatı: ' . $response,
            'raw_response' => $response
        ];
            
    } catch (Exception $e) {
        error_log("NetGSM balance check error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => "Error: " . $e->getMessage(),
            'raw_response' => null
        ];
    }
}
    
    /**
     * Send verification code SMS
     * @param string $phoneNumber Recipient phone number
     * @param string $code Verification code
     * @return array Response status and details
     */
    public function sendVerificationCode($phoneNumber, $code) {
        $message = "İnciPOS doğrulama kodunuz: $code. Bu kod 15 dakika geçerlidir.";
        return $this->sendSMS($phoneNumber, $message);
    }
    
    /**
     * Send notification about discount or campaign
     * @param string $phoneNumber Recipient phone number
     * @param string $discountName Discount name
     * @param string $discountRate Discount rate or amount
     * @param string $endDate End date of discount
     * @return array Response status and details
     */
    public function sendDiscountNotification($phoneNumber, $discountName, $discountRate, $endDate) {
        $message = "Değerli müşterimiz, $discountName kapsamında %$discountRate indirim $endDate tarihine kadar geçerlidir. İnci Kırtasiye";
        return $this->sendSMS($phoneNumber, $message);
    }
    
    /**
     * Send customer birthday message
     * @param string $phoneNumber Recipient phone number
     * @param string $customerName Customer name
     * @return array Response status and details
     */
    public function sendBirthdayMessage($phoneNumber, $customerName) {
        $message = "Sevgili $customerName, doğum gününüzü en içten dileklerimizle kutlarız. Bu özel gününüzde mağazamızda %10 indirim sizleri bekliyor. İnci Kırtasiye";
        return $this->sendSMS($phoneNumber, $message);
    }
    
    /**
     * Send bulk SMS to multiple customers
     * @param array $customers Array of customer objects with phone and name
     * @param string $message Message template (use {name} for customer name)
     * @return array Results for each customer
     */
    public function sendBulkSMS($customers, $message) {
        $results = array();
        
        foreach ($customers as $customer) {
            if (empty($customer['phone'])) {
                continue;
            }
            
            // Replace {name} placeholder with customer name if exists
            $personalizedMessage = $message;
            if (isset($customer['name'])) {
                $personalizedMessage = str_replace('{name}', $customer['name'], $message);
            }
            
            $result = $this->sendSMS($customer['phone'], $personalizedMessage);
            $results[] = array(
                'customer_id' => isset($customer['id']) ? $customer['id'] : null,
                'phone' => $customer['phone'],
                'result' => $result
            );
        }
        
        return $results;
    }
    
    /**
     * Get SMS sending reports - Düzeltilmiş versiyon
     * @param string $startDate Start date (DDMMYYYY)
     * @param string $endDate End date (DDMMYYYY)
     * @return array Report data
     */
    public function getReports($startDate = null, $endDate = null) {
        // If dates not provided, use past week
        if (!$startDate) {
            $startDate = date('dmY', strtotime('-7 days'));
        }
        if (!$endDate) {
            $endDate = date('dmY');
        }
        
        try {
            $postFields = array(
                'usercode' => $this->username,
                'password' => $this->password,
                'startdate' => $startDate,
                'enddate' => $endDate,
                'type' => '100'  // Detailed report
            );
            
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://api.netgsm.com.tr/sms/report',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => http_build_query($postFields),
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/x-www-form-urlencoded',
                ),
            ));
            
            $response = curl_exec($curl);
            $err = curl_error($curl);
            curl_close($curl);
            
            // Debug için yanıtı logla
            error_log("NetGSM Report API yanıtı: " . $response);
            
            if ($err) {
                return array(
                    'success' => false,
                    'message' => "cURL Error: $err",
                    'raw_response' => null
                );
            }
            
            // Parse response
            // Format: jobid|status|phone|sent_date|processed_date|message
            $reports = array();
            $lines = explode("\n", $response);
            
            foreach ($lines as $line) {
                if (empty($line)) continue;
                
                $parts = explode('|', $line);
                if (count($parts) >= 6) {
                    $reports[] = array(
                        'job_id' => $parts[0],
                        'status' => $parts[1],
                        'phone' => $parts[2],
                        'sent_date' => $parts[3],
                        'processed_date' => $parts[4],
                        'message' => $parts[5]
                    );
                }
            }
            
            return array(
                'success' => true,
                'reports' => $reports,
                'raw_response' => $response
            );
            
        } catch (Exception $e) {
            error_log("NetGSM report error: " . $e->getMessage());
            return array(
                'success' => false,
                'message' => "Error: " . $e->getMessage(),
                'raw_response' => null
            );
        }
    }
}
?>