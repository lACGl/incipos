<?php
/**
 * Kullanıcı giriş/çıkış/aktivite log kayıt fonksiyonu
 * Bu fonksiyon sistemdeki tüm kullanıcı aktivitelerini kaydetmek için kullanılır
 * 
 * @param PDO $conn Veritabanı bağlantısı
 * @param int $kullaniciId Kullanıcı ID
 * @param string $kullaniciTipi Kullanıcı tipi (admin/personel)
 * @param string $islemTipi İşlem tipi (giris/cikis/dogrulama/basarisiz)
 * @param string $detay İşlem detayları (opsiyonel)
 * @return bool İşlem başarılı mı?
 */
function logUserActivity($conn, $kullaniciId, $kullaniciTipi, $islemTipi, $detay = null) {
    try {
        // Tablo var mı kontrol et
        $stmt = $conn->prepare("SHOW TABLES LIKE 'kullanici_giris_log'");
        $stmt->execute();
        if ($stmt->rowCount() == 0) {
            // Tablo yoksa oluştur
            createUserLogTable($conn);
        }
        
        $stmt = $conn->prepare("
            INSERT INTO kullanici_giris_log 
            (kullanici_id, kullanici_tipi, islem_tipi, ip_adresi, tarayici_bilgisi, detay) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            $kullaniciId,
            $kullaniciTipi,
            $islemTipi,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $detay
        ]);
        
        return $result;
    } catch (PDOException $e) {
        error_log("Kullanıcı giriş log kaydı oluşturulurken hata: " . $e->getMessage());
        return false;
    }
}

/**
 * Kullanıcı log tablosunu oluşturma fonksiyonu
 * Tablo yoksa otomatik olarak oluşturur
 * 
 * @param PDO $conn Veritabanı bağlantısı
 * @return bool İşlem başarılı mı?
 */
function createUserLogTable($conn) {
    try {
        $conn->exec("CREATE TABLE IF NOT EXISTS `kullanici_giris_log` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `kullanici_id` int(11) NOT NULL,
            `kullanici_tipi` ENUM('admin', 'personel') NOT NULL,
            `islem_tipi` ENUM('giris', 'cikis', 'dogrulama', 'basarisiz') NOT NULL,
            `ip_adresi` VARCHAR(45) DEFAULT NULL,
            `tarayici_bilgisi` VARCHAR(255) DEFAULT NULL,
            `tarih` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `detay` TEXT DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_kullanici_id` (`kullanici_id`),
            KEY `idx_kullanici_tipi` (`kullanici_tipi`),
            KEY `idx_islem_tipi` (`islem_tipi`),
            KEY `idx_tarih` (`tarih`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        return true;
    } catch (PDOException $e) {
        error_log("Kullanıcı log tablosu oluşturulurken hata: " . $e->getMessage());
        return false;
    }
}

/**
 * SMS doğrulama log kaydı oluşturma fonksiyonu
 * 
 * @param PDO $conn Veritabanı bağlantısı
 * @param int $kullaniciId Kullanıcı ID
 * @param string $kullaniciTipi Kullanıcı tipi (admin/personel)
 * @param string $telefon Telefon numarası
 * @param string $kod Doğrulama kodu
 * @param string $detay İşlem detayları (opsiyonel)
 * @return bool İşlem başarılı mı?
 */
function logSmsVerification($conn, $kullaniciId, $kullaniciTipi, $telefon, $kod, $detay = null) {
    try {
        // Tablo var mı kontrol et
        $stmt = $conn->prepare("SHOW TABLES LIKE 'sms_dogrulama_log'");
        $stmt->execute();
        if ($stmt->rowCount() == 0) {
            // Tablo yoksa oluştur
            createSmsLogTable($conn);
        }
        
        $stmt = $conn->prepare("
            INSERT INTO sms_dogrulama_log 
            (kullanici_id, kullanici_tipi, telefon, dogrulama_kodu, ip_adresi, detay) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            $kullaniciId,
            $kullaniciTipi,
            $telefon,
            $kod,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $detay
        ]);
        
        return $result;
    } catch (PDOException $e) {
        error_log("SMS doğrulama log kaydı oluşturulurken hata: " . $e->getMessage());
        return false;
    }
}

/**
 * SMS doğrulama log tablosunu oluşturma fonksiyonu
 * 
 * @param PDO $conn Veritabanı bağlantısı
 * @return bool İşlem başarılı mı?
 */
function createSmsLogTable($conn) {
    try {
        $conn->exec("CREATE TABLE IF NOT EXISTS `sms_dogrulama_log` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `kullanici_id` int(11) NOT NULL,
            `kullanici_tipi` ENUM('admin', 'personel') NOT NULL,
            `telefon` VARCHAR(20) NOT NULL,
            `dogrulama_kodu` VARCHAR(10) NOT NULL,
            `gonderim_tarihi` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `dogrulama_tarihi` DATETIME DEFAULT NULL,
            `durum` ENUM('beklemede', 'dogrulandi', 'suresi_doldu', 'basarisiz') DEFAULT 'beklemede',
            `ip_adresi` VARCHAR(45) DEFAULT NULL,
            `detay` TEXT DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_kullanici_id` (`kullanici_id`),
            KEY `idx_kullanici_tipi` (`kullanici_tipi`),
            KEY `idx_telefon` (`telefon`),
            KEY `idx_gonderim_tarihi` (`gonderim_tarihi`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        return true;
    } catch (PDOException $e) {
        error_log("SMS log tablosu oluşturulurken hata: " . $e->getMessage());
        return false;
    }
}

/**
 * SMS doğrulama durumunu güncelleme fonksiyonu
 * 
 * @param PDO $conn Veritabanı bağlantısı
 * @param int $kullaniciId Kullanıcı ID
 * @param string $kullaniciTipi Kullanıcı tipi (admin/personel)
 * @param string $durum Doğrulama durumu
 * @return bool İşlem başarılı mı?
 */
function updateSmsVerificationStatus($conn, $kullaniciId, $kullaniciTipi, $durum) {
    try {
        // Tablo var mı kontrol et
        $stmt = $conn->prepare("SHOW TABLES LIKE 'sms_dogrulama_log'");
        $stmt->execute();
        if ($stmt->rowCount() == 0) {
            // Tablo yoksa oluştur
            createSmsLogTable($conn);
            return false; // Kayıt yok, tablo yeni oluşturuldu
        }
        
        $stmt = $conn->prepare("
            UPDATE sms_dogrulama_log 
            SET durum = ?, dogrulama_tarihi = NOW()
            WHERE kullanici_id = ? AND kullanici_tipi = ? 
            ORDER BY id DESC LIMIT 1
        ");
        
        $result = $stmt->execute([
            $durum,
            $kullaniciId,
            $kullaniciTipi
        ]);
        
        return $result && $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("SMS doğrulama log kaydı güncellenirken hata: " . $e->getMessage());
        return false;
    }
}

/**
 * Giriş denemelerini kaydetme fonksiyonu
 * Başarısız giriş denemeleri ve IP adreslerini kaydeder
 * 
 * @param PDO $conn Veritabanı bağlantısı
 * @param string $username Kullanıcı adı denemesi
 * @param bool $success Giriş başarılı mı?
 * @return bool İşlem başarılı mı?
 */
function logLoginAttempt($conn, $username, $success = false) {
    try {
        // Tablo var mı kontrol et
        $stmt = $conn->prepare("SHOW TABLES LIKE 'login_attempts'");
        $stmt->execute();
        if ($stmt->rowCount() == 0) {
            // Tablo yoksa oluştur
            createLoginAttemptsTable($conn);
        }
        
        $stmt = $conn->prepare("
            INSERT INTO login_attempts 
            (ip_adresi, username_attempt, zaman, basarili) 
            VALUES (?, ?, NOW(), ?)
        ");
        
        $result = $stmt->execute([
            $_SERVER['REMOTE_ADDR'] ?? '',
            $username,
            $success ? 1 : 0
        ]);
        
        return $result;
    } catch (PDOException $e) {
        error_log("Giriş denemesi kaydedilirken hata: " . $e->getMessage());
        return false;
    }
}

/**
 * Giriş denemeleri tablosunu oluşturma fonksiyonu
 * 
 * @param PDO $conn Veritabanı bağlantısı
 * @return bool İşlem başarılı mı?
 */
function createLoginAttemptsTable($conn) {
    try {
        $conn->exec("CREATE TABLE IF NOT EXISTS `login_attempts` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `ip_adresi` varchar(45) NOT NULL,
            `username_attempt` varchar(50) DEFAULT NULL,
            `zaman` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `basarili` tinyint(1) NOT NULL DEFAULT '0',
            PRIMARY KEY (`id`),
            KEY `idx_ip_adresi` (`ip_adresi`),
            KEY `idx_zaman` (`zaman`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        return true;
    } catch (PDOException $e) {
        error_log("Giriş denemeleri tablosu oluşturulurken hata: " . $e->getMessage());
        return false;
    }
}

/**
 * Bir IP adresinin son X dakika içindeki başarısız giriş denemelerini sayar
 * Brute force koruması için kullanılır
 * 
 * @param PDO $conn Veritabanı bağlantısı
 * @param int $minutes Kontrol edilecek dakika
 * @param string $ip IP adresi (belirtilmezse mevcut IP kullanılır)
 * @return int Başarısız giriş denemesi sayısı
 */
function countFailedLoginAttempts($conn, $minutes = 60, $ip = null) {
    try {
        if ($ip === null) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        }
        
        $stmt = $conn->prepare("
            SELECT COUNT(*) AS attempt_count 
            FROM login_attempts 
            WHERE ip_adresi = ? 
            AND basarili = 0 
            AND zaman > DATE_SUB(NOW(), INTERVAL ? MINUTE)
        ");
        
        $stmt->execute([$ip, $minutes]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return (int)$result['attempt_count'];
    } catch (PDOException $e) {
        error_log("Başarısız giriş denemeleri sayılırken hata: " . $e->getMessage());
        return 0;
    }
}

/**
 * Bir IP adresini belirli bir süre için engeller
 * Brute force koruması için kullanılır
 * 
 * @param PDO $conn Veritabanı bağlantısı
 * @param string $ip IP adresi (belirtilmezse mevcut IP kullanılır)
 * @param int $banMinutes Engelleme süresi (dakika)
 * @param string $reason Engelleme sebebi
 * @param string $username İlgili kullanıcı adı (opsiyonel)
 * @return bool İşlem başarılı mı?
 */
function banIP($conn, $ip = null, $banMinutes = 60, $reason = "Çok fazla başarısız giriş denemesi", $username = null) {
    try {
        if ($ip === null) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        }
        
        // Tablo var mı kontrol et
        $stmt = $conn->prepare("SHOW TABLES LIKE 'login_ban'");
        $stmt->execute();
        if ($stmt->rowCount() == 0) {
            // Tablo yoksa oluştur
            createLoginBanTable($conn);
        }
        
        $stmt = $conn->prepare("
            INSERT INTO login_ban 
            (ip_adresi, username_attempt, ban_baslangic, ban_bitis, sebep) 
            VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? MINUTE), ?)
        ");
        
        $result = $stmt->execute([
            $ip,
            $username,
            $banMinutes,
            $reason
        ]);
        
        return $result;
    } catch (PDOException $e) {
        error_log("IP adresi engellenirken hata: " . $e->getMessage());
        return false;
    }
}

/**
 * IP engelleme tablosunu oluşturma fonksiyonu
 * 
 * @param PDO $conn Veritabanı bağlantısı
 * @return bool İşlem başarılı mı?
 */
function createLoginBanTable($conn) {
    try {
        $conn->exec("CREATE TABLE IF NOT EXISTS `login_ban` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `ip_adresi` varchar(45) NOT NULL,
            `username_attempt` varchar(50) DEFAULT NULL,
            `ban_baslangic` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `ban_bitis` datetime NOT NULL,
            `sebep` varchar(255) DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_ip_adresi` (`ip_adresi`),
            KEY `idx_ban_bitis` (`ban_bitis`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        return true;
    } catch (PDOException $e) {
        error_log("IP engelleme tablosu oluşturulurken hata: " . $e->getMessage());
        return false;
    }
}

/**
 * Bir IP adresinin engellenmiş olup olmadığını kontrol eder
 * 
 * @param PDO $conn Veritabanı bağlantısı
 * @param string $ip IP adresi (belirtilmezse mevcut IP kullanılır)
 * @return array|false Engelleme bilgileri veya false (engellenmemişse)
 */
function checkIPBanned($conn, $ip = null) {
    try {
        if ($ip === null) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        }
        
        // Tablo var mı kontrol et
        $stmt = $conn->prepare("SHOW TABLES LIKE 'login_ban'");
        $stmt->execute();
        if ($stmt->rowCount() == 0) {
            // Tablo yoksa oluştur ve engelleme yok kabul et
            createLoginBanTable($conn);
            return false;
        }
        
        $stmt = $conn->prepare("
            SELECT * FROM login_ban 
            WHERE ip_adresi = ? 
            AND ban_bitis > NOW() 
            ORDER BY ban_bitis DESC 
            LIMIT 1
        ");
        
        $stmt->execute([$ip]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return $result;
        }
        
        return false;
    } catch (PDOException $e) {
        error_log("IP engelleme kontrolü yapılırken hata: " . $e->getMessage());
        return false;
    }
}