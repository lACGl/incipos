<?php
// backup_system.php - Incipos yedekleme sistemi (PHP tabanlı yedekleme) - Güncellenmiş versiyon
session_start();
set_time_limit(1800); // 30 dakikalık zaman sınırı (tam yedek için süreyi artırdık)
ini_set('memory_limit', '1024M'); // Bellek limitini 1GB'a çıkar

// Başlangıç zamanını kaydet
$start_time = microtime(true);

// Hata raporlama
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Çıktı tamponlama başlat
ob_start();

// Gerekli dosyaları dahil et
require_once 'admin/db_connection.php';
require_once 'vendor/autoload.php';

// Tarih ve zaman bilgisi
$date = date('Y-m-d_H-i-s');
$backup_name = 'incipos_backup_' . $date;

// Yedek klasörü oluştur
$backup_dir = __DIR__ . '/backups';
if (!file_exists($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

// Veritabanı bağlantı bilgileri - db_connection.php içerisinden değişken isimlerini kontrol edin
global $servername, $username, $password, $dbname;

// Veritabanı bağlantı bilgilerini bulamazsa manuel girin
if (!isset($servername) || !isset($username) || !isset($password) || !isset($dbname)) {
    // Manuel veritabanı ayarları
    $db_host = 'localhost';
    $db_user = 'incikir2_posadmin';
    $db_pass = 'vD3YjbzpPYsc'; // gerçek şifreyi yazın
    $db_name = 'incikir2_pos';
} else {
    // db_connection.php'den al
    $db_host = $servername;
    $db_user = $username;
    $db_pass = $password;
    $db_name = $dbname;
}

echo '<h2>Incipos Yedekleme Sistemi</h2>';
echo '<p>Başlangıç zamanı: ' . date('Y-m-d H:i:s') . '</p>';

// Veritabanı yedeği
echo "<p>Veritabanı yedekleniyor...</p>";
echo "<p><small>Veritabanı bilgileri: Host=$db_host, User=$db_user, DB=$db_name</small></p>";

$db_backup_file = $backup_dir . '/' . $backup_name . '_db.sql';
$db_result = php_database_backup($db_host, $db_user, $db_pass, $db_name, $db_backup_file);

// Dosya yedeği
echo "<p>Dosyalar yedekleniyor...</p>";
$files_backup_file = $backup_dir . '/' . $backup_name . '_files.zip';
$files_result = create_zip(__DIR__, $files_backup_file);

// İki yedeği birleştir
echo "<p>Yedekler birleştiriliyor...</p>";
$full_backup_file = $backup_dir . '/' . $backup_name . '.zip';
$zip = new ZipArchive();
if ($zip->open($full_backup_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
    $zip->addFile($db_backup_file, basename($db_backup_file));
    $zip->addFile($files_backup_file, basename($files_backup_file));
    $zip->close();
}

// Google Drive'a yükle
echo "<p>Google Drive'a yükleniyor...</p>";
$drive_id = upload_to_drive($full_backup_file, $backup_name . '.zip');

// Geçici dosyaları temizle
if (file_exists($db_backup_file)) unlink($db_backup_file);
if (file_exists($files_backup_file)) unlink($files_backup_file);
// Son yedeği de silmek istiyorsanız: unlink($full_backup_file);

// Eski yedekleri temizle (3 günden eski)
echo "<p>Eski yedekler temizleniyor...</p>";
$cleaned = cleanup_all_backups(3);

// Bitiş zamanı ve toplam süre hesaplama
$end_time = microtime(true);
$execution_time = $end_time - $start_time;

// Süreyi formatlama - Tüm float-to-int dönüşümlerini explicit yapalım
$total_seconds = floor($execution_time);
$hours = floor($total_seconds / 3600);
$minutes = floor(($total_seconds % 3600) / 60);
$seconds = floor($total_seconds % 60);
$time_format = sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);

// Sonuçları göster
echo "<h3>Yedekleme İşlemi Sonuçları</h3>";
echo "<ul>";
echo "<li>Veritabanı Yedeği: " . ($db_result ? "<span style='color:green'>Başarılı</span>" : "<span style='color:red'>Başarısız</span>") . "</li>";
echo "<li>Dosya Yedeği: " . ($files_result ? "<span style='color:green'>Başarılı</span>" : "<span style='color:red'>Başarısız</span>") . "</li>";
echo "<li>Google Drive'a Yükleme: " . ($drive_id ? "<span style='color:green'>Başarılı (ID: $drive_id)</span>" : "<span style='color:red'>Başarısız</span>") . "</li>";
echo "<li>Temizlenen Eski Yedek Sayısı: Drive: {$cleaned['drive']}, Yerel: {$cleaned['local']}</li>";
echo "<li>Yedek Dosyası: " . $backup_name . ".zip</li>";
echo "<li>Toplam Yedekleme Süresi: <strong>" . $time_format . "</strong> (Saat:Dakika:Saniye)</li>";
echo "</ul>";
echo '<p>Başlangıç: ' . date('Y-m-d H:i:s', (int)$start_time) . '</p>';
echo '<p>Bitiş: ' . date('Y-m-d H:i:s', (int)$end_time) . '</p>';
echo "<p><a href='index.php'>Ana Sayfaya Dön</a></p>";

/**
 * PHP ile veritabanını yedekle
 */
function php_database_backup($host, $user, $pass, $name, $backup_file) {
    try {
        // PDO bağlantısı oluştur
        $dsn = "mysql:host=$host;dbname=$name;charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        $pdo = new PDO($dsn, $user, $pass, $options);
        
        // Başlangıç sql
        $output = "-- Incipos Database Backup\n";
        $output .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $output .= "-- Host: $host\n";
        $output .= "-- Database: $name\n\n";
        $output .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
        
        // Tüm tabloları al
        $tables = [];
        $stmt = $pdo->query("SHOW TABLES");
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }
        
        echo "<p>Toplam " . count($tables) . " tablo yedeklenecek</p>";
        
        // Her tablo için
        foreach ($tables as $table) {
            echo "<p>- Tablo yedekleniyor: $table</p>";
            
            // CREATE TABLE ifadesi
            $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
            $row = $stmt->fetch(PDO::FETCH_NUM);
            $output .= "DROP TABLE IF EXISTS `$table`;\n";
            $output .= $row[1] . ";\n\n";
            
            // Tablo verilerini al
            $stmt = $pdo->query("SELECT * FROM `$table`");
            $count = $stmt->rowCount();
            
            if ($count > 0) {
                $rows = $stmt->fetchAll();
                foreach ($rows as $row) {
                    $fields = implode("`, `", array_keys($row));
                    $values = array_values($row);
                    
                    // Değerleri SQL için düzenle
                    foreach ($values as &$value) {
                        if ($value === null) {
                            $value = "NULL";
                        } else {
                            $value = $pdo->quote($value);
                        }
                    }
                    
                    $values_str = implode(", ", $values);
                    $output .= "INSERT INTO `$table` (`$fields`) VALUES ($values_str);\n";
                }
            }
            $output .= "\n";
        }
        
        $output .= "SET FOREIGN_KEY_CHECKS=1;\n";
        
        // Dosyaya yaz
        file_put_contents($backup_file, $output);
        echo "<p>Veritabanı yedeği tamamlandı. Boyut: " . round(filesize($backup_file) / 1024, 2) . " KB</p>";
        return true;
    } catch (PDOException $e) {
        echo "<p style='color:red'>Veritabanı yedekleme hatası: " . $e->getMessage() . "</p>";
        return false;
    }
}

/**
 * Dosyaları sıkıştır - TAM YEDEKLEME
 */
function create_zip($source_dir, $backup_file) {
    try {
        $zip = new ZipArchive();
        if ($zip->open($backup_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            $source_dir = realpath($source_dir);
            
            if (is_dir($source_dir)) {
                $iterator = new RecursiveDirectoryIterator($source_dir);
                $iterator->setFlags(RecursiveDirectoryIterator::SKIP_DOTS);
                $files = new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::SELF_FIRST);
                
                $file_count = 0;
                $skip_count = 0;
                
                // Sadece mevcut yedek dosyalarını ve geçici dosyaları atla
                $skip_patterns = [
                    '/\/backups\/incipos_backup_.*\.zip$/i',  // Yedek dosyaları
                    '/\/tmp\/.*$/i',                          // Geçici dosyalar
                    '/\.log$/i',                              // Log dosyaları
                    '/\/logs\/.*$/i',                         // Log klasörleri
                    '/\.git\/.*$/i',                          // Git dosyaları
                    '/\.DS_Store$/i',                         // MacOS sistem dosyaları
                    '/thumbs\.db$/i',                         // Windows önizleme dosyaları
                ];
                
                foreach ($files as $file) {
                    $file = realpath($file);
                    
                    if (is_dir($file)) {
                        continue;
                    }
                    
                    // Sadece belirli dosyaları atla
                    $skip = false;
                    foreach ($skip_patterns as $pattern) {
                        if (preg_match($pattern, $file)) {
                            $skip = true;
                            $skip_count++;
                            break;
                        }
                    }
                    
                    if ($skip) {
                        continue;
                    }
                    
                    $file_path = str_replace($source_dir . DIRECTORY_SEPARATOR, '', $file);
                    
                    if ($zip->addFile($file, $file_path)) {
                        $file_count++;
                        
                        // Her 500 dosyada bir ilerleme raporu göster
                        if ($file_count % 500 == 0) {
                            echo "<p>Devam ediyor... $file_count dosya arşivlendi.</p>";
                            // Çıktı tamponunu temizle ve gönder
                            if (ob_get_level() > 0) {
                                ob_flush();
                                flush();
                            }
                        }
                    }
                }
                
                $zip->close();
                echo "<p>Dosya yedeği tamamlandı. $file_count dosya yedeklendi, $skip_count dosya atlandı. Boyut: " . round(filesize($backup_file) / 1024 / 1024, 2) . " MB</p>";
                return true;
            }
        }
        return false;
    } catch (Exception $e) {
        echo "<p style='color:red'>Dosya yedekleme hatası: " . $e->getMessage() . "</p>";
        return false;
    }
}

/**
 * Google Drive'a yükle - Güvenli versiyon
 */
function upload_to_drive($file_path, $file_name) {
    try {
        // Google Client sınıfını başlat
        $client = new Google\Client();
        $client->setApplicationName('Incipos Backup System');
        $client->setScopes('https://www.googleapis.com/auth/drive');
        $client->setAuthConfig('client_secret_206180220854-i9pc6jle33v84rh2rgec0q0jllgj8sff.apps.googleusercontent.com.json');
        $client->setAccessType('offline');
        
        // Token'ı yükle
        if (!file_exists('backup_token.json')) {
            echo "<p style='color:red'>Token dosyası bulunamadı! <a href='backup_auth.php'>Yetkilendirme gerekli</a></p>";
            return false;
        }
        
        $accessToken = json_decode(file_get_contents('backup_token.json'), true);
        $client->setAccessToken($accessToken);
        
        // Token süresi dolduysa yenile
        if ($client->isAccessTokenExpired()) {
            if ($client->getRefreshToken()) {
                try {
                    $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
                    file_put_contents('backup_token.json', json_encode($client->getAccessToken()));
                    echo "<p>Token yenilendi.</p>";
                } catch (Exception $e) {
                    echo "<p style='color:red'>Token yenileme hatası: " . $e->getMessage() . "</p>";
                    echo "<p><a href='backup_auth.php'>Yeniden yetkilendirme gerekli</a></p>";
                    return false;
                }
            } else {
                echo "<p style='color:red'>Refresh token bulunamadı. <a href='backup_auth.php'>Yeniden yetkilendirme gerekli</a></p>";
                return false;
            }
        }
        
        // Drive servisini başlat
        $service = new Google\Service\Drive($client);
        
        // Dosya boyutunu kontrol et
        $file_size = filesize($file_path);
        echo "<p>Yüklenecek dosya boyutu: " . round($file_size / 1024 / 1024, 2) . " MB</p>";
        
        // Büyük dosyalar için chunk yükleme kullan (5MB üzeri)
        if ($file_size > 5 * 1024 * 1024) {
            echo "<p>Büyük dosya tespit edildi, chunk yükleme kullanılıyor...</p>";
            
            $fileMetadata = new Google\Service\Drive\DriveFile([
                'name' => $file_name,
                'description' => 'Incipos Yedek: ' . date('Y-m-d H:i:s')
            ]);
            
            // Chunk yükleme için client ile
            $chunkSizeBytes = 1 * 1024 * 1024; // 1MB
            
            // Çok büyük dosyalar için chunk sayısını artır
            if ($file_size > 100 * 1024 * 1024) { // 100MB üzeri
                $chunkSizeBytes = 5 * 1024 * 1024; // 5MB
            }
            
            // Chunk ile yükleme
            $client->setDefer(true);
            $request = $service->files->create($fileMetadata);
            
            // Medya yükleme
            $media = new Google\Http\MediaFileUpload(
                $client,
                $request,
                'application/zip',
                null,
                true,
                $chunkSizeBytes
            );
            $media->setFileSize($file_size);
            
            // Dosyayı aç
            $handle = fopen($file_path, "rb");
            $status = false;
            $uploaded = 0;
            
            // Chunk'lar halinde yükle
            while (!$status && !feof($handle)) {
                $chunk = fread($handle, $chunkSizeBytes);
                $status = $media->nextChunk($chunk);
                $uploaded += strlen($chunk);
                
                // İlerleme göster
                $percent = round(($uploaded / $file_size) * 100);
                echo "<p>Yükleniyor: %$percent (" . round($uploaded / 1024 / 1024, 2) . "MB / " . round($file_size / 1024 / 1024, 2) . "MB)</p>";
                // Çıktı tamponunu temizle ve gönder
                if (ob_get_level() > 0) {
                    ob_flush();
                    flush();
                }
            }
            
            fclose($handle);
            $client->setDefer(false);
            
            if ($status) {
                echo "<p>Dosya başarıyla Google Drive'a yüklendi.</p>";
                return $status->id;
            } else {
                echo "<p style='color:red'>Google Drive yükleme tamamlanamadı.</p>";
                return false;
            }
        } else {
            // Küçük dosyalar için normal yükleme
            $fileMetadata = new Google\Service\Drive\DriveFile([
                'name' => $file_name,
                'description' => 'Incipos Yedek: ' . date('Y-m-d H:i:s')
            ]);
            
            $content = file_get_contents($file_path);
            
            $file = $service->files->create($fileMetadata, [
                'data' => $content,
                'mimeType' => 'application/zip',
                'uploadType' => 'multipart',
                'fields' => 'id'
            ]);
            
            echo "<p>Dosya başarıyla Google Drive'a yüklendi.</p>";
            return $file->id;
        }
        
    } catch (Exception $e) {
        echo "<p style='color:red'>Google Drive yükleme hatası: " . $e->getMessage() . "</p>";
        return false;
    }
}

/**
 * Eski yedekleri hem Google Drive'dan hem de yerel backups klasöründen temizler
 * @param int $days Kaç günden eski yedeklerin silineceği
 * @return array Silinen dosya sayıları ['drive' => x, 'local' => y]
 */
function cleanup_all_backups($days = 3) {
    $result = [
        'drive' => 0,
        'local' => 0
    ];
    
    // 1. Yerel backups klasöründeki eski dosyaları temizle
    $result['local'] = cleanup_local_backups($days);
    
    // 2. Google Drive'daki eski dosyaları temizle
    $result['drive'] = cleanup_drive_backups($days);
    
    return $result;
}

/**
 * Yerel backups klasöründeki eski yedekleri temizler
 * @param int $days Kaç günden eski yedeklerin silineceği
 * @return int Silinen dosya sayısı
 */
function cleanup_local_backups($days = 3) {
    try {
        echo "<p><strong>Yerel backups klasöründeki eski yedekler temizleniyor ($days günden eski)...</strong></p>";
        
        // Backup klasörünün yolunu belirle
        $backup_dir = __DIR__ . '/backups';
        
        // Backup klasörü yoksa çık
        if (!file_exists($backup_dir) || !is_dir($backup_dir)) {
            echo "<p style='color:orange'>Yerel backup klasörü bulunamadı.</p>";
            return 0;
        }
        
        // Kesme tarihini hesapla (X gün öncesi)
        $cutoff_time = time() - ($days * 24 * 60 * 60);
        
        // Klasördeki tüm dosyaları al
        $files = scandir($backup_dir);
        
        $count = 0;
        foreach ($files as $file) {
            if ($file == '.' || $file == '..') {
                continue; // . ve .. klasörlerini atla
            }
            
            $file_path = $backup_dir . '/' . $file;
            
            // Klasörse atla
            if (is_dir($file_path)) {
                continue;
            }
            
            // Dosya oluşturulma/değiştirilme zamanını kontrol et
            $file_time = filemtime($file_path);
            
            // Dosya X günden eskiyse sil
            if ($file_time < $cutoff_time) {
                if (unlink($file_path)) {
                    echo "<p>Yerel dosya silindi: $file</p>";
                    $count++;
                } else {
                    echo "<p style='color:red'>Yerel dosya silinemedi: $file</p>";
                }
            }
        }
        
        echo "<p>Toplam $count yerel dosya silindi.</p>";
        return $count;
        
    } catch (Exception $e) {
        echo "<p style='color:red'>Yerel dosya temizleme hatası: " . $e->getMessage() . "</p>";
        return 0;
    }
}

/**
 * Google Drive'daki eski yedekleri temizler
 * @param int $days Kaç günden eski yedeklerin silineceği
 * @return int Silinen dosya sayısı
 */
function cleanup_drive_backups($days = 3) {
    try {
        echo "<p><strong>Google Drive'daki eski yedekler temizleniyor ($days günden eski)...</strong></p>";
        
        // Google Client sınıfını başlat
        $client = new Google\Client();
        $client->setApplicationName('Incipos Backup System');
        $client->setScopes(['https://www.googleapis.com/auth/drive.file', 'https://www.googleapis.com/auth/drive']);
        $client->setAuthConfig('client_secret_206180220854-i9pc6jle33v84rh2rgec0q0jllgj8sff.apps.googleusercontent.com.json');
        $client->setAccessType('offline');
        
        // Token'ı yükle
        if (!file_exists('backup_token.json')) {
            echo "<p style='color:orange'>Token dosyası bulunamadı, Drive temizleme işlemi atlandı.</p>";
            return 0;
        }
        
        $accessToken = json_decode(file_get_contents('backup_token.json'), true);
        $client->setAccessToken($accessToken);
        
        // Token süresi dolduysa yenile
        if ($client->isAccessTokenExpired()) {
            echo "<p>Token süresi dolmuş, yenileniyor...</p>";
            
            if ($client->getRefreshToken()) {
                try {
                    $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
                    file_put_contents('backup_token.json', json_encode($client->getAccessToken()));
                    echo "<p>Token başarıyla yenilendi.</p>";
                } catch (Exception $e) {
                    echo "<p style='color:red'>Token yenileme hatası: " . $e->getMessage() . "</p>";
                    echo "<p><a href='backup_auth.php'>Lütfen yeniden yetkilendirme yapın</a></p>";
                    return 0;
                }
            } else {
                echo "<p style='color:red'>Refresh token bulunamadı. <a href='backup_auth.php'>Lütfen yeniden yetkilendirme yapın</a></p>";
                return 0;
            }
        }
        
        // Drive servisini başlat
        $service = new Google\Service\Drive($client);
        
        // X gün önceki tarihi hesapla
        $cutoff_date = new DateTime();
        $cutoff_date->sub(new DateInterval('P' . $days . 'D'));
        $cutoff_date_str = $cutoff_date->format('c'); // RFC 3339 formatı
        
        echo "<p>Kesme tarihi: " . $cutoff_date->format('Y-m-d H:i:s') . " - Bu tarihten önce oluşturulan dosyalar silinecek</p>";
        
        // incipos_backup ile başlayan dosyaları bul
        $query = "name contains 'incipos_backup_' and createdTime < '$cutoff_date_str' and trashed=false";
        $optParams = array(
            'q' => $query,
            'fields' => 'files(id, name, createdTime)',
            'pageSize' => 100
        );
        
        $results = $service->files->listFiles($optParams);
        $files = $results->getFiles();
        
        $total_files = count($files);
        echo "<p>Toplam $total_files Drive dosyası silinecek.</p>";
        
        // Dosyaları sil
        $count = 0;
        foreach ($files as $file) {
            try {
                $file_name = $file->getName();
                $file_id = $file->getId();
                $file_date = new DateTime($file->getCreatedTime());
                
                echo "<p>Drive dosyası siliniyor: $file_name (Oluşturulma: " . $file_date->format('Y-m-d H:i:s') . ")</p>";
                
                $service->files->delete($file_id);
                echo "<p style='color:green'>Drive dosyası başarıyla silindi: $file_name</p>";
                $count++;
                
                // API sınırlamalarını aşmamak için kısa bekleme
                usleep(250000); // 0.25 saniye
                
            } catch (Exception $e) {
                echo "<p style='color:red'>Drive dosyası silinirken hata: " . $e->getMessage() . "</p>";
            }
        }
        
        echo "<p>Toplam $count Drive dosyası silindi.</p>";
        return $count;
        
    } catch (Exception $e) {
        echo "<p style='color:red'>Drive temizleme hatası: " . $e->getMessage() . "</p>";
        return 0;
    }
}
?>