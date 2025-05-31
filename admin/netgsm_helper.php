<?php
/**
 * NetGSM API Veritabanı Erişim Sınıfı
 * SMS gönderimi ve diğer NetGSM işlemleri için güvenli erişim sağlar
 */

class NetGSMHelper {
    private $conn;
    private $settings = [
        'username' => null,
        'password' => null,
        'header' => null,
        'active' => true
    ];

    /**
     * NetGSM yardımcı sınıfını başlat
     * @param PDO $conn Veritabanı bağlantısı
     */
    public function __construct($conn) {
        $this->conn = $conn;
        $this->loadSettings();
    }

    /**
     * NetGSM ayarlarını veritabanından yükle
     */
    private function loadSettings() {
        try {
            $query = "SELECT anahtar, deger FROM sistem_ayarlari 
                     WHERE anahtar IN ('netgsm_username', 'netgsm_password', 'netgsm_header', 'sms_aktif')";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                switch ($row['anahtar']) {
                    case 'netgsm_username':
                        $this->settings['username'] = $row['deger'];
                        break;
                    case 'netgsm_password':
                        $this->settings['password'] = $row['deger'];
                        break;
                    case 'netgsm_header':
                        $this->settings['header'] = $row['deger'];
                        break;
                    case 'sms_aktif':
                        $this->settings['active'] = ($row['deger'] == '1');
                        break;
                }
            }
        } catch (PDOException $e) {
            error_log("NetGSM ayarları yüklenirken hata: " . $e->getMessage());
        }
    }

    /**
     * SMS gönderme işlemi
     * @param string $phoneNumber Telefon numarası
     * @param string $message SMS metni
     * @return array Gönderim sonucu
     */
    public function sendSMS($phoneNumber, $message) {
        // SMS gönderimi aktif değilse hata döndür
        if (!$this->settings['active']) {
            return [
                'success' => false,
                'message' => 'SMS gönderimi sistem tarafından devre dışı bırakılmış.'
            ];
        }
        
        // Gerekli ayarlar eksikse hata döndür
        if (!$this->settings['username'] || !$this->settings['password'] || !$this->settings['header']) {
            return [
                'success' => false,
                'message' => 'NetGSM ayarları eksik veya hatalı.'
            ];
        }
        
        // Telefon numarasını formatla
        $phoneNumber = $this->formatPhoneNumber($phoneNumber);
        $filter = '0'; // Ticari olmayan bilgilendirme mesajı
        
        try {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => 'https://api.netgsm.com.tr/sms/send/get',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => [
                    'usercode' => $this->settings['username'],
                    'password' => $this->settings['password'],
                    'gsmno' => $phoneNumber,
                    'message' => $message,
                    'msgheader' => $this->settings['header'],
                    'filter' => $filter
                ],
            ]);
            
            $response = curl_exec($curl);
            $err = curl_error($curl);
            curl_close($curl);
            
            // SMS log tablosuna kaydet
            $this->logSMS($phoneNumber, $message, $response);
            
            // Doğrulama log tablosuna kaydet (eğer doğrulama kodu içeriyorsa)
            if (strpos($message, 'doğrulama kodu') !== false) {
                $this->logVerification($phoneNumber, $message);
            }
            
            if ($err) {
                return [
                    'success' => false,
                    'message' => "Bağlantı hatası: $err"
                ];
            }
            
            // Yanıt başarılı mı kontrol et
            if (preg_match('/^[0-9]{10,}$/', $response) || strpos($response, '00') === 0) {
                return [
                    'success' => true,
                    'message' => 'SMS başarıyla gönderildi',
                    'job_id' => $response
                ];
            } else {
                return [
                    'success' => false,
                    'message' => $this->getErrorMessage($response),
                    'error_code' => $response
                ];
            }
        } catch (Exception $e) {
            error_log("SMS gönderirken hata: " . $e->getMessage());
            return [
                'success' => false,
                'message' => "Sistem hatası: " . $e->getMessage()
            ];
        }
    }
    
    /**
     * Doğrulama kodu SMS'i gönder
     * @param string $phoneNumber Telefon numarası
     * @param string $code Doğrulama kodu
     * @return array Gönderim sonucu
     */
    public function sendVerificationSMS($phoneNumber, $code) {
        $message = "İnciPOS doğrulama kodunuz: $code. Bu kod 180 saniye geçerlidir.";
        return $this->sendSMS($phoneNumber, $message);
    }
    
    /**
     * Telefon numarasını formatla
     * @param string $phoneNumber Telefon numarası
     * @return string Formatlanmış telefon numarası
     */
    private function formatPhoneNumber($phoneNumber) {
        // Tüm alfanumerik olmayan karakterleri kaldır
        $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        // Başında 0 varsa kaldır
        if (substr($phoneNumber, 0, 1) === '0') {
            $phoneNumber = substr($phoneNumber, 1);
        }
        
        // 10 haneli numara kontrolü (5XX XXX XX XX)
        if (strlen($phoneNumber) == 10) {
            // Başına Türkiye ülke kodu ekle
            $phoneNumber = '90' . $phoneNumber;
        } 
        // Zaten formatlanmış ise (90 ile başlıyorsa) bırak
        else if (strlen($phoneNumber) == 12 && substr($phoneNumber, 0, 2) === '90') {
            // Değişiklik yapma
        } 
        // Diğer durumlarda, 90 ile başladığından emin ol
        else if (substr($phoneNumber, 0, 2) !== '90') {
            $phoneNumber = '90' . $phoneNumber;
        }
        
        return $phoneNumber;
    }
    
    /**
     * SMS gönderimini veritabanına logla
     * @param string $phoneNumber Telefon numarası
     * @param string $message SMS metni
     * @param string $response API yanıtı
     */
    private function logSMS($phoneNumber, $message, $response) {
        try {
            // Kullanıcı bilgilerini belirle
            $kullanici_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1; // Default admin ID
            $kullanici_tipi = isset($_SESSION['yetki']) && $_SESSION['yetki'] == 'admin' ? 'admin' : 'personel';
            
            // Yeni SQL sorgusunda kullanici_tipi alanını kullan
            $query = "INSERT INTO sms_log (telefon, mesaj, yanit, tarih, kullanici_id, kullanici_tipi) 
                     VALUES (:telefon, :mesaj, :yanit, NOW(), :kullanici_id, :kullanici_tipi)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':telefon', $phoneNumber, PDO::PARAM_STR);
            $stmt->bindParam(':mesaj', $message, PDO::PARAM_STR);
            $stmt->bindParam(':yanit', $response, PDO::PARAM_STR);
            $stmt->bindParam(':kullanici_id', $kullanici_id, PDO::PARAM_INT);
            $stmt->bindParam(':kullanici_tipi', $kullanici_tipi, PDO::PARAM_STR);
            $stmt->execute();
            
        } catch (Exception $e) {
            error_log("SMS log kaydı oluşturulurken hata: " . $e->getMessage());
        }
    }
    
    /**
     * Doğrulama SMS'i için ayrı bir log kaydı oluştur
     * @param string $phoneNumber Telefon numarası
     * @param string $message SMS metni
     */
    private function logVerification($phoneNumber, $message) {
        try {
            // Doğrulama kodunu mesajdan çıkar
            preg_match('/doğrulama kodunuz: ([0-9]+)/', $message, $matches);
            $code = $matches[1] ?? '';
            
            if (!empty($code)) {
                // Kullanıcı bilgilerini belirle
                $kullanici_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;
                $kullanici_tipi = isset($_SESSION['yetki']) && $_SESSION['yetki'] == 'admin' ? 'admin' : 'personel';
                $ip_adresi = $_SERVER['REMOTE_ADDR'] ?? '';
                
                // Doğrulama log tablosuna kaydet (bu tablo oluşturulmuş olmalı)
                // SQL: CREATE TABLE IF NOT EXISTS `sms_dogrulama_log` ...
                if ($this->checkTableExists('sms_dogrulama_log')) {
                    $query = "INSERT INTO sms_dogrulama_log 
                             (kullanici_id, kullanici_tipi, telefon, dogrulama_kodu, ip_adresi) 
                             VALUES (:kullanici_id, :kullanici_tipi, :telefon, :dogrulama_kodu, :ip_adresi)";
                    
                    $stmt = $this->conn->prepare($query);
                    $stmt->bindParam(':kullanici_id', $kullanici_id, PDO::PARAM_INT);
                    $stmt->bindParam(':kullanici_tipi', $kullanici_tipi, PDO::PARAM_STR);
                    $stmt->bindParam(':telefon', $phoneNumber, PDO::PARAM_STR);
                    $stmt->bindParam(':dogrulama_kodu', $code, PDO::PARAM_STR);
                    $stmt->bindParam(':ip_adresi', $ip_adresi, PDO::PARAM_STR);
                    $stmt->execute();
                }
            }
        } catch (Exception $e) {
            error_log("Doğrulama log kaydı oluşturulurken hata: " . $e->getMessage());
        }
    }
    
    /**
     * Tablonun var olup olmadığını kontrol et
     * @param string $tableName Tablo adı
     * @return bool Tablo varsa true, yoksa false
     */
    private function checkTableExists($tableName) {
        try {
            $stmt = $this->conn->prepare("SHOW TABLES LIKE :tableName");
            $stmt->bindParam(':tableName', $tableName, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("Tablo kontrolü sırasında hata: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Hata kodlarını anlamlı mesajlara çevir
     * @param string $errorCode Hata kodu
     * @return string Hata mesajı
     */
    private function getErrorMessage($errorCode) {
        switch ($errorCode) {
            case '01':
                return 'NetGSM kredi yetersiz.';
            case '02':
                return 'NetGSM mesaj başlığı hatalı.';
            case '03':
                return 'NetGSM mesaj metni eksik veya hatalı.';
            case '04':
                return 'NetGSM telefon numarası geçersiz.';
            case '05':
                return 'NetGSM SMS gönderilemedi.';
            case '30':
                return 'NetGSM kullanıcı adı/şifre hatalı veya API erişim izni yok.';
            default:
                return "NetGSM bilinmeyen hata: $errorCode";
        }
    }
}