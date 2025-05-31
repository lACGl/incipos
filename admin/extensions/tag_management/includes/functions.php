<?php
/**
 * Barkod Yöneticisi Fonksiyonlar
 */

// Session ve veritabanı kontrolü - doğrudan erişim için
if (!isset($conn)) {
    // Session yönetimi ve yetkisiz erişim kontrolü
    require_once '../../session_manager.php';
    // Session kontrolü
    checkUserSession();
    // Veritabanı bağlantısı
    require_once '../../db_connection.php';
}

/**
 * Eklentinin kurulu olup olmadığını kontrol eder
 * 
 * @return bool Kurulu ise true, değilse false
 */
function barcode_check_installed() {
    global $conn;
    
    try {
        $stmt = $conn->query("SHOW TABLES LIKE 'barcode_settings'");
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log('Barkod kurulum kontrolü hatası: ' . $e->getMessage());
        return false;
    }
}

/**
 * Eklentiyi kurar
 * 
 * @return bool Kurulum başarılı ise true, değilse false
 */
function barcode_install() {
    global $conn;
    
    try {
        // Ayarlar tablosunu oluştur
        $conn->exec("CREATE TABLE IF NOT EXISTS `barcode_settings` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `anahtar` varchar(50) NOT NULL,
          `deger` text DEFAULT NULL,
          `aciklama` varchar(255) DEFAULT NULL,
          `guncelleme_tarihi` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `anahtar` (`anahtar`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        
        // Log tablosunu oluştur
        $conn->exec("CREATE TABLE IF NOT EXISTS `barcode_log` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `urun_id` int(11) NOT NULL,
          `islem_tipi` enum('olusturma','yazdirma','guncelleme') NOT NULL,
          `aciklama` text DEFAULT NULL,
          `kullanici_id` int(11) DEFAULT NULL,
          `kullanici_tipi` enum('admin','personel') DEFAULT NULL,
          `ip_adresi` varchar(45) DEFAULT NULL,
          `islem_tarihi` datetime DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `urun_id` (`urun_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        
        // Varsayılan ayarları ekle
        $default_settings = [
            ['etiket_genislik', '100', 'Etiket genişliği (mm)'],
            ['etiket_yukseklik', '38', 'Etiket yüksekliği (mm)'],
            ['kenar_boslugu', '2', 'Etiket kenar boşluğu (mm)'],
            ['barkod_yukseklik', '60', 'Barkod yüksekliği (px)'],
            ['qr_boyut', '200', 'QR kod boyutu (px)'],
            ['yazici_port', '9100', 'Barkod yazıcı varsayılan port numarası'],
            ['yazici_ip', '', 'Barkod yazıcı IP adresi (boşsa doğrudan tarayıcı yazdırması)'],
            ['etiket_sablonu', '1', 'Varsayılan etiket şablonu (1-2)'],
            ['firma_adi', 'İnci Kırtasiye', 'Etiketlerde görünecek firma adı'],
            ['default_code_type', 'qr', 'Varsayılan kod tipi (qr, ean13, code128 vb.)'],
            ['printer_type', 'browser', 'Yazıcı tipi (browser, thermal, escpos)'],
            ['printer_language', 'ZPL', 'Termal yazıcı dili (ZPL, EPL)'],
            ['auto_print', '0', 'Önizlemeden sonra otomatik yazdır (1/0)'],
            ['show_preview', '1', 'Yazdırma öncesi önizleme göster (1/0)']
        ];
        
        $insert_stmt = $conn->prepare("INSERT INTO `barcode_settings` (`anahtar`, `deger`, `aciklama`) VALUES (?, ?, ?)");
        
        foreach ($default_settings as $setting) {
            try {
                $insert_stmt->execute($setting);
            } catch (PDOException $e) {
                // Muhtemelen zaten var, devam et
            }
        }
        
        return true;
    } catch (PDOException $e) {
        error_log('Barkod Yöneticisi kurulum hatası: ' . $e->getMessage());
        return false;
    }
}

/**
 * Ayarı getirir
 * 
 * @param string $key Ayar anahtarı
 * @param mixed $default Ayar bulunamazsa varsayılan değer
 * @return mixed Ayar değeri veya varsayılan değer
 */
function barcode_get_setting($key, $default = null) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("SELECT deger FROM barcode_settings WHERE anahtar = ?");
        $stmt->execute([$key]);
        
        if ($stmt->rowCount() > 0) {
            return $stmt->fetchColumn();
        }
    } catch (PDOException $e) {
        error_log('Barkod ayarı getirme hatası: ' . $e->getMessage());
    }
    
    return $default;
}

/**
 * Ayarı günceller
 * 
 * @param string $key Ayar anahtarı
 * @param mixed $value Ayar değeri
 * @return bool Güncelleme başarılı ise true, değilse false
 */
function barcode_update_setting($key, $value) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("INSERT INTO barcode_settings (anahtar, deger) VALUES (?, ?)
                              ON DUPLICATE KEY UPDATE deger = ?");
        return $stmt->execute([$key, $value, $value]);
    } catch (PDOException $e) {
        error_log('Barkod ayarı güncelleme hatası: ' . $e->getMessage());
        return false;
    }
}

/**
 * Barkod veya QR kod görüntüsü oluşturur
 * 
 * @param string $data Kod içeriği
 * @param string $type Kod tipi ('qr', 'ean13', 'code128' vs.)
 * @param int $size Boyut (QR için) veya yükseklik (barkod için)
 * @return string Görüntünün URL'si
 */
function barcode_or_qr_image($data, $type = 'qr', $size = null) {
    if (empty($data)) {
        return '';
    }
    
    // Boyut değerleri ayarlardan alınır
    if ($size === null) {
        if (strtolower($type) === 'qr') {
            $size = intval(barcode_get_setting('qr_boyut', 200));
        } else {
            $size = intval(barcode_get_setting('barkod_yukseklik', 60));
        }
    }
    
    // AJAX URL'i oluştur
    $url = './?page=extensions&ext=tag_management&action=ajax&op=barcode_image&code=' . urlencode($data) . '&type=' . $type;
    
    // Eğer boyut belirtilmişse, URL'e ekle
    if ($size !== null) {
        $url .= '&size=' . intval($size);
    }
    
    // Cache önlemek için timestamp ekle
    $url .= '&t=' . time();
    
    return $url;
}

/**
 * Benzersiz bir barkod/kod oluşturur
 * 
 * @param string $type Kod tipi (qr, EAN13, CODE128, UPC, vb.)
 * @param string $prefix Barkod ön eki (ülke kodu vb.)
 * @return string Benzersiz kod
 */
function barcode_generate_unique($type = 'qr', $prefix = '869') {
    global $conn;
    
    // Ayarlardan varsayılan kod tipini al
    if (empty($type)) {
        $type = barcode_get_setting('default_code_type', 'qr');
    }
    
    // QR kod için daha karmaşık bir içerik oluştur
    if (strtolower($type) === 'qr') {
        do {
            // QR kod içeriğini oluştur (URL veya ürün bilgisi formatı)
            $timestamp = time();
            $random = mt_rand(100000, 999999);
            $barcode = "INCI:" . $prefix . $timestamp . $random;
            
            // Veritabanında bu kodun kullanılıp kullanılmadığını kontrol et
            $stmt = $conn->prepare("SELECT COUNT(*) FROM urun_stok WHERE barkod = ?");
            $stmt->execute([$barcode]);
            $exists = $stmt->fetchColumn();
            
        } while ($exists > 0);
        
        return $barcode;
    }
    
    // Barkod tipleri için
    switch (strtoupper($type)) {
        case 'EAN13':
            do {
                // Türkiye başlangıç kodu + rastgele 9 haneli sayı
                $barcode = $prefix . str_pad(mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
                
                // EAN-13 kontrol hanesi hesapla
                $sum = 0;
                for ($i = 0; $i < 12; $i++) {
                    $sum += intval($barcode[$i]) * ($i % 2 == 0 ? 1 : 3);
                }
                $checkdigit = (10 - ($sum % 10)) % 10;
                $barcode .= $checkdigit;
                
                // Veritabanı kontrolü
                $stmt = $conn->prepare("SELECT COUNT(*) FROM urun_stok WHERE barkod = ?");
                $stmt->execute([$barcode]);
                $exists = $stmt->fetchColumn();
                
            } while ($exists > 0);
            break;
            
        case 'CODE128':
            do {
                // Rastgele alfanümerik kod (16 karakter)
                $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
                $barcode = $prefix;
                
                for ($i = 0; $i < 16 - strlen($prefix); $i++) {
                    $barcode .= $chars[mt_rand(0, strlen($chars) - 1)];
                }
                
                // Veritabanı kontrolü
                $stmt = $conn->prepare("SELECT COUNT(*) FROM urun_stok WHERE barkod = ?");
                $stmt->execute([$barcode]);
                $exists = $stmt->fetchColumn();
                
            } while ($exists > 0);
            break;
            
        case 'UPC':
        case 'UPCA':
            do {
                // UPC-A formatı (12 basamak)
                $barcode = str_pad(mt_rand(0, 99999999999), 11, '0', STR_PAD_LEFT);
                
                // UPC-A kontrol hanesi hesapla
                $sum = 0;
                for ($i = 0; $i < 11; $i++) {
                    $sum += intval($barcode[$i]) * ($i % 2 == 0 ? 3 : 1);
                }
                $checkdigit = (10 - ($sum % 10)) % 10;
                $barcode .= $checkdigit;
                
                // Veritabanı kontrolü
                $stmt = $conn->prepare("SELECT COUNT(*) FROM urun_stok WHERE barkod = ?");
                $stmt->execute([$barcode]);
                $exists = $stmt->fetchColumn();
                
            } while ($exists > 0);
            break;
            
        case 'EAN8':
            do {
                // EAN-8 formatı (8 basamak)
                $barcode = str_pad(mt_rand(0, 9999999), 7, '0', STR_PAD_LEFT);
                
                // EAN-8 kontrol hanesi hesapla
                $sum = 0;
                for ($i = 0; $i < 7; $i++) {
                    $sum += intval($barcode[$i]) * ($i % 2 ? 1 : 3);
                }
                $checkdigit = (10 - ($sum % 10)) % 10;
                $barcode .= $checkdigit;
                
                // Veritabanı kontrolü
                $stmt = $conn->prepare("SELECT COUNT(*) FROM urun_stok WHERE barkod = ?");
                $stmt->execute([$barcode]);
                $exists = $stmt->fetchColumn();
                
            } while ($exists > 0);
            break;
            
        case 'CODE39':
            do {
                // CODE39 formatı (10 karakter)
                $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ-. $/+%';
                $barcode = '';
                
                for ($i = 0; $i < 10; $i++) {
                    $barcode .= $chars[mt_rand(0, strlen($chars) - 1)];
                }
                
                // Veritabanı kontrolü
                $stmt = $conn->prepare("SELECT COUNT(*) FROM urun_stok WHERE barkod = ?");
                $stmt->execute([$barcode]);
                $exists = $stmt->fetchColumn();
                
            } while ($exists > 0);
            break;
            
        default:
            // Tanınmayan tip için QR kod oluştur
            return barcode_generate_unique('qr', $prefix);
    }
    
    return $barcode;
}

/**
 * Barkod oluşturma/güncelleme işlemi yapar
 * 
 * @param int $urun_id Ürün ID
 * @param string $barcode Barkod değeri (boşsa otomatik oluşturur)
 * @param string $code_type Kod tipi (qr, ean13, code128 vs.)
 * @return bool|string İşlem başarılı ise true/barkod, değilse false
 */
function barcode_save($urun_id, $barcode = '', $code_type = '') {
    global $conn;
    
    if (empty($code_type)) {
        $code_type = barcode_get_setting('default_code_type', 'qr');
    }
    
    if (empty($barcode)) {
        $barcode = barcode_generate_unique($code_type);
    }
    
    try {
        $stmt = $conn->prepare("UPDATE urun_stok SET barkod = ? WHERE id = ?");
        $result = $stmt->execute([$barcode, $urun_id]);
        
        if ($result) {
            // Log kaydı oluştur
            $kullanici_id = isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : null;
            $ip_address = $_SERVER['REMOTE_ADDR'];
            
            $log_stmt = $conn->prepare("INSERT INTO barcode_log (urun_id, islem_tipi, aciklama, kullanici_id, kullanici_tipi, ip_adresi) 
                                     VALUES (?, ?, ?, ?, 'admin', ?)");
            $log_stmt->execute([
                $urun_id, 
                empty($_POST['barcode']) ? 'olusturma' : 'guncelleme', 
                'Kod ' . (empty($_POST['barcode']) ? 'otomatik oluşturuldu' : 'güncellendi') . ' (' . $code_type . ')', 
                $kullanici_id,
                $ip_address
            ]);
            
            return $barcode;
        }
        
        return false;
    } catch (PDOException $e) {
        error_log('Barkod kaydetme hatası: ' . $e->getMessage());
        return false;
    }
}

/**
 * Barkod yazdırma işlemini loglar
 * 
 * @param int $urun_id Ürün ID
 * @param int $adet Yazdırılan etiket adedi
 * @return bool İşlem başarılı ise true, değilse false
 */
function barcode_log_print($urun_id, $adet = 1) {
    global $conn;
    
    try {
        $kullanici_id = isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : null;
        $ip_address = $_SERVER['REMOTE_ADDR'];
        
        $log_stmt = $conn->prepare("INSERT INTO barcode_log (urun_id, islem_tipi, aciklama, kullanici_id, kullanici_tipi, ip_adresi) 
                                 VALUES (?, 'yazdirma', ?, ?, 'admin', ?)");
        return $log_stmt->execute([
            $urun_id, 
            $adet . ' adet etiket yazdırıldı', 
            $kullanici_id,
            $ip_address
        ]);
    } catch (PDOException $e) {
        error_log('Barkod yazdırma log hatası: ' . $e->getMessage());
        return false;
    }
}

/**
 * Barkodu olmayan ürünler için toplu barkod oluşturur
 * 
 * @return int Barkod oluşturulan ürün sayısı
 */
function barcode_generate_bulk() {
    global $conn;
    
    $count = 0;
    
    try {
        // Barkodu olmayan ürünleri al
        $stmt = $conn->query("SELECT id FROM urun_stok WHERE (barkod IS NULL OR barkod = '') AND durum = 'aktif'");
        $urunler = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Varsayılan kod tipini al
        $code_type = barcode_get_setting('default_code_type', 'qr');
        
        foreach ($urunler as $urun) {
            $barcode = barcode_save($urun['id'], '', $code_type);
            
            if ($barcode) {
                $count++;
            }
        }
    } catch (PDOException $e) {
        error_log('Toplu barkod oluşturma hatası: ' . $e->getMessage());
    }
    
    return $count;
}

/**
 * Ürün bilgilerini ID'ye göre getirir
 * 
 * @param int $urun_id Ürün ID
 * @return array|bool Ürün bilgileri veya false
 */
function barcode_get_product($urun_id) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("SELECT * FROM urun_stok WHERE id = ?");
        $stmt->execute([$urun_id]);
        
        if ($stmt->rowCount() > 0) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        error_log('Ürün bilgisi getirme hatası: ' . $e->getMessage());
    }
    
    return false;
}

/**
 * Barkodlu ürünleri getirir
 * 
 * @param string $search Arama terimi
 * @param int $limit Sonuç limiti
 * @return array Ürünler
 */
function barcode_get_products($search = '', $limit = 50) {
    global $conn;
    
    try {
        $sql = "SELECT u.*, 
                IFNULL(d.ad, '') as departman_adi, 
                IFNULL(b.ad, '') as birim_adi, 
                IFNULL(ag.ad, '') as ana_grup_adi, 
                IFNULL(altg.ad, '') as alt_grup_adi 
                FROM urun_stok u 
                LEFT JOIN departmanlar d ON u.departman_id = d.id 
                LEFT JOIN birimler b ON u.birim_id = b.id 
                LEFT JOIN ana_gruplar ag ON u.ana_grup_id = ag.id 
                LEFT JOIN alt_gruplar altg ON u.alt_grup_id = altg.id 
                WHERE u.durum = 'aktif'";
        
        // Eğer arama terimi boşsa, son eklenen ürünleri getir
        if (empty($search)) {
            $sql .= " ORDER BY u.id DESC LIMIT " . intval($limit);
            
            $stmt = $conn->prepare($sql);
            $stmt->execute();
        } else {
            // Arama terimi varsa, buna göre filtrele
            $sql .= " AND (u.ad LIKE ? OR u.kod LIKE ? OR u.barkod LIKE ?)";
            $sql .= " ORDER BY u.ad ASC LIMIT " . intval($limit);
            
            $search_term = '%' . $search . '%';
            
            $stmt = $conn->prepare($sql);
            $stmt->bindValue(1, $search_term, PDO::PARAM_STR);
            $stmt->bindValue(2, $search_term, PDO::PARAM_STR);
            $stmt->bindValue(3, $search_term, PDO::PARAM_STR);
            $stmt->execute();
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Ürünleri getirme hatası: ' . $e->getMessage());
        return [];
    }
}

/**
 * Ağ yazıcısına test yazdırma işlemi gerçekleştirir
 * 
 * @param string $ip Yazıcı IP adresi
 * @param int $port Yazıcı port numarası
 * @param string $type Yazıcı tipi (thermal, escpos)
 * @param string $language Termal yazıcı dili (ZPL, EPL)
 * @return array İşlem sonucu ['success' => bool, 'message' => string]
 */
function barcode_test_network_printer($ip, $port, $type = 'thermal', $language = 'ZPL') {
    try {
        $socket = @fsockopen($ip, $port, $errno, $errstr, 5);
        
        if (!$socket) {
            return [
                'success' => false,
                'message' => "Yazıcıya bağlanılamadı: $errstr ($errno)"
            ];
        }
        
        // Test etiketi gönder
        if ($type === 'thermal') {
            if ($language === 'ZPL') {
                // ZPL komutları
                $test_command = "^XA^FO50,50^A0N,30,30^FDTest Etiketi^FS^FO50,100^A0N,20,20^FD" . date('Y-m-d H:i:s') . "^FS^XZ";
            } else {
                // EPL komutları
                $test_command = "N\nS4\nD15,12,0\nTDN\nA50,50,0,3,1,1,N,\"Test Etiketi\"\nA50,100,0,3,1,1,N,\"" . date('Y-m-d H:i:s') . "\"\nP1\n";
            }
        } else {
            // ESC/POS komutları
            $test_command = "\x1B\x40" . // Initialize
                           "Test Etiketi\n" .
                           date('Y-m-d H:i:s') . "\n\n" .
                           "\x1D\x56\x00"; // Cut paper
        }
        
        $result = fwrite($socket, $test_command);
        fclose($socket);
        
        if ($result === false) {
            return [
                'success' => false,
                'message' => 'Yazıcıya komut gönderilemedi.'
            ];
        }
        
        return [
            'success' => true,
            'message' => 'Test yazdırması başarıyla gönderildi!'
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Hata: ' . $e->getMessage()
        ];
    }
}

/**
 * Barkodun son 6 hanesini koyu yapmak için HTML formatı
 * 
 * @param string $barcode Barkod değeri
 * @return string HTML formatlanmış barkod
 */
function formatBarcodeWithBold($barcode) {
    if (strlen($barcode) > 6) {
        $first_part = substr($barcode, 0, -6);
        $last_part = substr($barcode, -6);
        return htmlspecialchars($first_part) . '<span class="last-digits">' . htmlspecialchars($last_part) . '</span>';
    }
    return htmlspecialchars($barcode);
}