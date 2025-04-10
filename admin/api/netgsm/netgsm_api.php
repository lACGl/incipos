<?php
/**
 * NetGSM API Helper Functions
 * This file contains functions for interacting with NetGSM API for SMS services
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
     * Send SMS to a phone number
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
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://api.netgsm.com.tr/sms/send/get',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => array(
                    'usercode' => $this->username,
                    'password' => $this->password,
                    'gsmno' => $phoneNumber,
                    'message' => $message,
                    'msgheader' => $this->msgHeader,
                    'filter' => $filter
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
                return [
                    'success' => false,
                    'message' => "cURL Error: $err"
                ];
            }
            
            // Log the SMS activity
            $this->logSMSActivity($phoneNumber, $message, $response);
            
            // Parse and return the response
            return $this->parseResponse($response);
            
        } catch (Exception $e) {
            error_log("NetGSM SMS sending error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => "Error: " . $e->getMessage()
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
        $results = [];
        
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
            $results[] = [
                'customer_id' => $customer['id'] ?? null,
                'phone' => $customer['phone'],
                'result' => $result
            ];
        }
        
        return $results;
    }
    
    /**
     * Format phone number for NetGSM
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
        
        // If doesn't start with country code (90 for Turkey), add it
        if (substr($phoneNumber, 0, 2) !== '90') {
            $phoneNumber = '90' . $phoneNumber;
        }
        
        return $phoneNumber;
    }
    
    /**
     * Parse NetGSM API response
     * @param string $response API response
     * @return array Parsed response
     */
    private function parseResponse($response) {
        // NetGSM returns numeric codes as responses
        if (is_numeric($response)) {
            switch ($response) {
                case '00':
                    return [
                        'success' => false,
                        'message' => 'Hata: Geçersiz kullanıcı adı, şifre veya API erişim izni yok.'
                    ];
                case '01':
                    return [
                        'success' => false,
                        'message' => 'Hata: Kredi yetersiz.'
                    ];
                case '02':
                    return [
                        'success' => false,
                        'message' => 'Hata: Geçersiz SMS başlığı.'
                    ];
                default:
                    // If response is a long number, it's a successful job ID
                    if (strlen($response) > 5) {
                        return [
                            'success' => true,
                            'message' => 'SMS başarıyla gönderildi.',
                            'job_id' => $response
                        ];
                    } else {
                        return [
                            'success' => false,
                            'message' => 'Bilinmeyen hata kodu: ' . $response
                        ];
                    }
            }
        } else {
            return [
                'success' => false,
                'message' => 'Geçersiz API yanıtı: ' . $response
            ];
        }
    }
    
    /**
     * Log SMS activity to database
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
            $kullanici_id = $_SESSION['user_id'] ?? null;
            
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
     * Get SMS credit balance from NetGSM
     * @return array Balance information
     */
    public function getBalance() {
        try {
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://api.netgsm.com.tr/balance/list/get',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => array(
                    'usercode' => $this->username,
                    'password' => $this->password
                ),
            ));
            
            $response = curl_exec($curl);
            $err = curl_error($curl);
            curl_close($curl);
            
            if ($err) {
                return [
                    'success' => false,
                    'message' => "cURL Error: $err"
                ];
            }
            
            // Parse response (format: credit|unitcredit|unitprice)
            $parts = explode('|', $response);
            if (count($parts) >= 3) {
                return [
                    'success' => true,
                    'credit' => $parts[0],
                    'unit_credit' => $parts[1],
                    'unit_price' => $parts[2]
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Geçersiz bakiye yanıtı: ' . $response
                ];
            }
            
        } catch (Exception $e) {
            error_log("NetGSM balance check error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => "Error: " . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get SMS sending reports
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
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://api.netgsm.com.tr/sms/report',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => array(
                    'usercode' => $this->username,
                    'password' => $this->password,
                    'startdate' => $startDate,
                    'enddate' => $endDate,
                    'type' => '100'  // Detailed report
                ),
            ));
            
            $response = curl_exec($curl);
            $err = curl_error($curl);
            curl_close($curl);
            
            if ($err) {
                return [
                    'success' => false,
                    'message' => "cURL Error: $err"
                ];
            }
            
            // Parse response
            // Format: jobid|status|phone|sent_date|processed_date|message
            $reports = [];
            $lines = explode("\n", $response);
            
            foreach ($lines as $line) {
                if (empty($line)) continue;
                
                $parts = explode('|', $line);
                if (count($parts) >= 6) {
                    $reports[] = [
                        'job_id' => $parts[0],
                        'status' => $parts[1],
                        'phone' => $parts[2],
                        'sent_date' => $parts[3],
                        'processed_date' => $parts[4],
                        'message' => $parts[5]
                    ];
                }
            }
            
            return [
                'success' => true,
                'reports' => $reports
            ];
            
        } catch (Exception $e) {
            error_log("NetGSM report error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => "Error: " . $e->getMessage()
            ];
        }
    }
}
?>