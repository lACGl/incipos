<?php
// backup_auth.php - Google Drive yetkilendirme
session_start();

// Composer autoload
require_once 'vendor/autoload.php';

try {
    // Google Client sınıfını başlat
    $client = new Google\Client();
    $client->setApplicationName('Incipos Backup System');
    $client->setScopes('https://www.googleapis.com/auth/drive');
    $client->setAuthConfig('client_secret_206180220854-i9pc6jle33v84rh2rgec0q0jllgj8sff.apps.googleusercontent.com.json');
    $client->setAccessType('offline');
    $client->setPrompt('select_account consent');
    $client->setRedirectUri('http://localhost/incipos/backup_auth.php');

    // Yetkilendirme işlemi
    if (isset($_GET['code'])) {
        $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
        $client->setAccessToken($token);
        
        // Token'ı bir dosyaya kaydet
        if (!file_exists(dirname(__FILE__) . '/backup_token.json')) {
            file_put_contents('backup_token.json', json_encode($client->getAccessToken()));
        }
        
        // Başarılı mesajı göster
        echo '<h3>Yetkilendirme başarılı!</h3>';
        echo '<p>Artık Google Drive\'a yedek alabilirsiniz.</p>';
        echo '<p><a href="index.php">Ana Sayfaya Dön</a> | <a href="backup_system.php">Yedekleme İşlemini Başlat</a></p>';
        
    } else {
        // Yetkilendirme URL'sini oluştur
        $authUrl = $client->createAuthUrl();
        // Kullanıcıyı yetkilendirme sayfasına yönlendir
        header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
    }
} catch (Exception $e) {
    echo '<h3>Hata oluştu</h3>';
    echo '<p>' . $e->getMessage() . '</p>';
    
    // PHP sürüm ve uzantı bilgilerini göster
    echo '<h4>PHP Bilgileri:</h4>';
    echo 'PHP Sürümü: ' . phpversion() . '<br>';
    echo '<h4>Gerekli Uzantılar:</h4>';
    echo 'JSON: ' . (extension_loaded('json') ? 'Yüklü' : 'YÜKLENMEMİŞ!') . '<br>';
    echo 'cURL: ' . (extension_loaded('curl') ? 'Yüklü' : 'YÜKLENMEMİŞ!') . '<br>';
    echo 'OpenSSL: ' . (extension_loaded('openssl') ? 'Yüklü' : 'YÜKLENMEMİŞ!') . '<br>';
}
?>