<?php
/**
 * Veritabanı Entegrasyonlu Claude API
 * Bu dosya, Anthropic Claude API'sini İnciPOS veritabanı ile entegre eder
 */

// Hata raporlama
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Veritabanı bağlantısı
require_once '../db_connection.php';
require_once '../session_manager.php';
require_once 'db_proxy.php'; // Veritabanı proxy sınıfı

class ClaudeAPI {
    private $apiKey;
    private $model;
    private $apiEndpoint = "https://api.anthropic.com/v1/messages";
    private $db;
    private $dbProxy;
    private $systemPrompt;
    
    public function __construct($db_connection, $api_key = null, $model = "claude-3-haiku-20240307") {
        global $conn;
        
        // Veritabanı bağlantısını ayarla
        if (!$conn && $db_connection) {
            $this->db = $db_connection;
            error_log("Veritabanı bağlantısı dışarıdan sağlandı.");
        } elseif ($conn) {
            $this->db = $conn;
            error_log("Veritabanı bağlantısı global değişkenden alındı.");
        } else {
            error_log("UYARI: Veritabanı bağlantısı bulunamadı!");
            $this->db = null;
        }
        
        $this->model = $model;
        $this->dbProxy = new DBProxy($this->db);
        
        // API anahtarını ayarla
        if ($api_key) {
            $this->apiKey = $api_key;
            error_log("API anahtarı parametre olarak sağlandı.");
        } else {
            // Veritabanından al
            $api_key = $this->getSettingFromDB('claude_api_key');
            
            if ($api_key) {
                $this->apiKey = $api_key;
                error_log("API anahtarı veritabanından alındı.");
            } else {
                // Sabit API key (güvenlik için bunu değiştirin!)
                $this->apiKey = "YOUR_CLAUDE_API_KEY_HERE";
                error_log("UYARI: Varsayılan API anahtarı kullanılıyor. Lütfen gerçek bir API anahtarı ayarlayın!");
            }
        }
        
        // Sistem komutunu oluştur
        $this->prepareSystemPrompt();
    }
    
    /**
     * Veritabanından ayar değerini al
     */
    private function getSettingFromDB($key) {
        try {
            // Veritabanı bağlantısını kontrol et
            if (!$this->db) {
                error_log("Veritabanı bağlantısı bulunamadı!");
                return null;
            }
            
            // PDO bağlantısı mı kontrol et
            $isPDO = ($this->db instanceof PDO);
            
            if ($isPDO) {
                $stmt = $this->db->prepare("SELECT deger FROM sistem_ayarlari WHERE anahtar = :key");
                $stmt->bindParam(':key', $key);
                $stmt->execute();
                
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $value = $result ? $result['deger'] : null;
                if ($value) {
                    error_log("API anahtarı veritabanından alındı: " . substr($value, 0, 5) . "...");
                } else {
                    error_log("API anahtarı veritabanında bulunamadı!");
                }
                return $value;
            } else {
                // MySQLi
                $key = $this->db->real_escape_string($key);
                $result = $this->db->query("SELECT deger FROM sistem_ayarlari WHERE anahtar = '$key'");
                
                if ($result && $result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    $value = $row['deger'];
                    error_log("API anahtarı veritabanından alındı: " . substr($value, 0, 5) . "...");
                    return $value;
                } else {
                    error_log("API anahtarı veritabanında bulunamadı (MySQLi sorgusu)!");
                }
            }
        } catch (Exception $e) {
            error_log("ClaudeAPI - Veritabanı hatası: " . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * Sistem komutunu hazırla - POS sistemi ve veritabanı bilgileri
     */
    private function prepareSystemPrompt() {
        // Şirket bilgilerini al
        $companyName = $this->getSettingFromDB('sirket_adi') ?: 'İnci Kırtasiye';
        
        // Veritabanı şeması bilgilerini al
        $db_schema = $this->getDBSchemaInfo();
        
        // Veritabanı sorgulama yeteneklerini tanımla
        $db_capabilities = $this->getDBCapabilities();
        
        $this->systemPrompt = <<<EOT
Sen $companyName'nin POS sistemindeki yapay zeka asistanısın. Adın İnciBot.

## Veritabanı Erişimi
İnciPOS veritabanına erişimin var. Aşağıdaki veritabanı tabloları ve özellikleri hakkında bilgin var:

$db_schema

## Veritabanı Sorgu Yetenekleri
Belirli veritabanı sorgularını çalıştırabilirsin. Kullanıcı satış, stok, ciro veya müşteri verileri hakkında bir soru sorduğunda, aşağıdaki önceden tanımlanmış sorguları kullanabilirsin:

$db_capabilities

## Örnek Kullanım
1. Kullanıcı: "Bu ayın en çok satan 5 ürünü nedir?"
   Eylem: `top_selling_products` sorgusunu `period: this_month, limit: 5` parametreleriyle çalıştır

2. Kullanıcı: "Stoğu azalan ürünler hangileri?"
   Eylem: `stock_status` sorgusunu `sort: low, limit: 10` parametreleriyle çalıştır

## Görevin
Kullanıcıların satış, stok, ciro ve müşteri verileri hakkındaki sorularını yanıtlamak. Bunun için:
1. Kullanıcının sorusunu anla
2. Gerekli veritabanı sorgusunu belirle
3. Uygun parametrelerle sorguyu çalıştır
4. Sonuçları insanın anlayabileceği şekilde açıkla

Yanıtlarını kısa ve öz tut. Veritabanı verilerinin bulunmadığı durumlarda tahmin yapma, samimiyetle bilmediğini söyle.

Günün tarihi: {$this->getCurrentDate()}
EOT;
    }
    
    /**
     * Veritabanı şema bilgilerini al
     */
    private function getDBSchemaInfo() {
        $schema_info = "
- `urun_stok`: Ürün bilgileri (id, barkod, ad, satis_fiyati, stok_miktari, vb.)
- `satis_faturalari`: Satış faturaları (id, fatura_no, fatura_tarihi, toplam_tutar, personel, vb.)
- `satis_fatura_detay`: Satış fatura detayları (fatura_id, urun_id, miktar, birim_fiyat, vb.)
- `personel`: Personel bilgileri (id, ad, yetki_seviyesi, magaza_id, vb.)
- `musteriler`: Müşteri bilgileri (id, ad, soyad, telefon, email, vb.)
- `stok_hareketleri`: Stok hareketleri (urun_id, miktar, hareket_tipi, tarih, vb.)";

        return $schema_info;
    }
    
    /**
     * Veritabanı sorgulama yeteneklerini tanımla
     */
    private function getDBCapabilities() {
        $capabilities = "
1. `top_selling_products`: En çok satan ürünleri getirir
   Parametreler: 
   - period: [today, yesterday, this_week, last_week, this_month, last_month, this_year, last_year]
   - limit: Kaç ürün gösterileceği (varsayılan: 10)
   - category: Belirli bir kategori ID (opsiyonel)

2. `top_salespeople`: En çok satış yapan personeli getirir
   Parametreler:
   - period: [today, yesterday, this_week, last_week, this_month, last_month, this_year, last_year]
   - limit: Kaç personel gösterileceği (varsayılan: 5)

3. `revenue_info`: Belirli bir dönemin ciro bilgilerini getirir
   Parametreler:
   - period: [today, yesterday, this_week, last_week, this_month, last_month, this_year, last_year]

4. `stock_status`: Stok durumunu getirir
   Parametreler:
   - sort: [low, high] (varsayılan: low = en az stoku olanlar)
   - limit: Kaç ürün gösterileceği (varsayılan: 10)
   - category: Belirli bir kategori ID (opsiyonel)

5. `daily_sales`: Günlük satış bilgilerini saatlik olarak getirir
   Parametreler:
   - date: Tarih (varsayılan: bugün, format: YYYY-MM-DD)

6. `monthly_sales`: Aylık satış bilgilerini günlük olarak getirir
   Parametreler:
   - year: Yıl (varsayılan: şu anki yıl)
   - month: Ay (varsayılan: şu anki ay)

7. `customer_info`: Müşteri bilgilerini getirir
   Parametreler:
   - period: [all_time, this_month, last_month, this_year, last_year]
   - limit: Kaç müşteri gösterileceği (varsayılan: 10)";

        return $capabilities;
    }
    
    /**
     * Geçerli tarihi formatla
     */
    private function getCurrentDate() {
        return date('d.m.Y');
    }
    
    /**
     * Kullanıcı sorgusunda veritabanı sorgusu gerektirir mi kontrol et
     */
    private function needsDatabaseQuery($userMessage) {
        $dbQueryKeywords = [
            'satış', 'satiş', 'satilan', 'satan', 'satis', 'sattı', 'satılan',
            'stok', 'ürün', 'urun', 'kalem', 'defter', 'envanter', 
            'ciro', 'kazanç', 'hasılat', 'gelir', 'tutar',
            'müşteri', 'musteri', 'alışveriş',
            'kasiyer', 'personel', 'çalışan',
            // Ek anahtar kelimeler
            'en çok', 'liste', 'göster', 'rapor', 'toplam',
            'bugün', 'bu hafta', 'bu ay', 'azalan', 'tükenen'
        ];
        
        $message = mb_strtolower($userMessage, 'UTF-8');
        error_log("Kullanıcı sorgusu analiz ediliyor: $message");
        
        foreach ($dbQueryKeywords as $keyword) {
            if (mb_strpos($message, $keyword) !== false) {
                error_log("Veritabanı sorgusu gerektirecek anahtar kelime bulundu: $keyword");
                return true;
            }
        }
        
        error_log("Veritabanı sorgusu gerektiren anahtar kelime bulunamadı");
        return false;
    }
    
    /**
     * Veritabanı sorgusu gereken soruları analiz et
     */
    private function analyzeQuery($userMessage) {
    $message = mb_strtolower($userMessage, 'UTF-8');
    error_log("Sorgu analiz ediliyor: $message");
    
    // En çok satan ürünler
    if (preg_match('/(en çok|popüler|fazla) sat[ıi]lan.*?ürün/ui', $message)) {
        $period = $this->detectPeriod($message);
        $limit = $this->detectNumber($message) ?: 10;
        $category = null; // Kategori tespiti eklenebilir
        
        error_log("top_selling_products sorgusu tespit edildi. Period: $period, Limit: $limit");
        return [
            'query_type' => 'top_selling_products',
            'params' => [
                'period' => $period,
                'limit' => $limit,
                'category' => $category
            ]
        ];
    }
    
    // En çok satan personel
    if (preg_match('/(en çok|başarılı) (satan|satış yapan) (personel|kasiyer)/ui', $message)) {
        $period = $this->detectPeriod($message);
        $limit = $this->detectNumber($message) ?: 5;
        
        error_log("top_salespeople sorgusu tespit edildi. Period: $period, Limit: $limit");
        return [
            'query_type' => 'top_salespeople',
            'params' => [
                'period' => $period,
                'limit' => $limit
            ]
        ];
    }
    
    // Stok durumu
    if (preg_match('/(stok|kalan|tükenen|mevcut|biten|azalan|stokta|ürünleri)/ui', $message)) {
        $sort = preg_match('/(en çok|fazla)/ui', $message) ? 'high' : 'low';
        $limit = $this->detectNumber($message) ?: 10;
        
        error_log("stock_status sorgusu tespit edildi. Sort: $sort, Limit: $limit");
        return [
            'query_type' => 'stock_status',
            'params' => [
                'sort' => $sort,
                'limit' => $limit
            ]
        ];
    }
    
    // Ciro bilgileri
    if (preg_match('/(ciro|kazanç|satış tutar|gelir|hasılat)/ui', $message)) {
        $period = $this->detectPeriod($message);
        
        error_log("revenue_info sorgusu tespit edildi. Period: $period");
        return [
            'query_type' => 'revenue_info',
            'params' => [
                'period' => $period
            ]
        ];
    }
    
    // Günlük satışlar - bugün
    if (preg_match('/bugün(kü)? (satış|satiş|ciro)/ui', $message)) {
        error_log("daily_sales sorgusu tespit edildi. Date: " . date('Y-m-d'));
        return [
            'query_type' => 'daily_sales',
            'params' => [
                'date' => date('Y-m-d')
            ]
        ];
    }
    
    // Bu ayki en çok satılan ürünler (özel durum)
    if (preg_match('/bu ay(ki)? en çok sat[ıi]lan.*?ürün/ui', $message)) {
        $limit = $this->detectNumber($message) ?: 10;
        
        error_log("top_selling_products sorgusu tespit edildi (özel durum). Period: this_month, Limit: $limit");
        return [
            'query_type' => 'top_selling_products',
            'params' => [
                'period' => 'this_month',
                'limit' => $limit,
                'category' => null
            ]
        ];
    }
    
    // Müşteri bilgileri
    if (preg_match('/(müşteri|musteri|sadakat|puan|üye)/ui', $message)) {
        $period = $this->detectPeriod($message);
        $limit = $this->detectNumber($message) ?: 10;
        
        error_log("customer_info sorgusu tespit edildi. Period: $period, Limit: $limit");
        return [
            'query_type' => 'customer_info',
            'params' => [
                'period' => $period,
                'limit' => $limit
            ]
        ];
    }
    
    error_log("Belirli bir sorgu türü tespit edilemedi");
    return null;
}
    
    /**
     * Sorgudaki zaman periyodunu tespit et
     */
    private function detectPeriod($query) {
        $query = mb_strtolower($query, 'UTF-8');
        
        if (preg_match('/(bu ay|içinde bulunduğumuz ay)/i', $query)) {
            return 'this_month';
        } else if (preg_match('/(geçen ay|önceki ay)/i', $query)) {
            return 'last_month';
        } else if (preg_match('/(bu yıl|bu sene|' . date('Y') . ')/i', $query)) {
            return 'this_year';
        } else if (preg_match('/(geçen yıl|geçen sene|' . (date('Y')-1) . ')/i', $query)) {
            return 'last_year';
        } else if (preg_match('/bugün/i', $query)) {
            return 'today';
        } else if (preg_match('/dün/i', $query)) {
            return 'yesterday';
        } else if (preg_match('/hafta/i', $query)) {
            if (preg_match('/geçen hafta/i', $query)) {
                return 'last_week';
            } else {
                return 'this_week';
            }
        }
        
        // Varsayılan olarak bu ay
        return 'this_month';
    }
    
    /**
     * Sorgudaki sayıyı tespit et
     */
    private function detectNumber($query) {
        $query = mb_strtolower($query, 'UTF-8');
        
        // Sayısal değer ara
        if (preg_match('/\b(\d+)\b/', $query, $matches)) {
            return (int)$matches[1];
        }
        
        // Yazıyla yazılmış sayı ara
        $number_words = [
            'bir' => 1, 'iki' => 2, 'üç' => 3, 'dört' => 4, 'beş' => 5,
            'altı' => 6, 'yedi' => 7, 'sekiz' => 8, 'dokuz' => 9, 'on' => 10
        ];
        
        foreach ($number_words as $word => $number) {
            if (mb_strpos($query, $word) !== false) {
                return $number;
            }
        }
        
        return null;
    }
    
    /**
     * Veritabanı sorgusundan yanıt oluştur
     */
    private function generateResponseFromDatabaseQuery($query_type, $params, $result) {
        if (!$result || !isset($result['success']) || !$result['success']) {
            error_log("Veritabanı sorgusu başarısız: " . ($result['error'] ?? "Bilinmeyen hata"));
            return "Üzgünüm, veritabanından bilgi alırken bir sorun oluştu: " . ($result['error'] ?? "Bilinmeyen hata");
        }
        
        $data = $result['data'] ?? [];
        error_log("Veritabanı sorgusu başarılı, veri boyutu: " . count($data));
        
        // Veritabanı sonuçlarını insan okunabilir metne dönüştür
        switch ($query_type) {
            case 'top_selling_products':
                return $this->formatTopSellingProducts($data, $params);
                
            case 'top_salespeople':
                return $this->formatTopSalespeople($data, $params);
                
            case 'revenue_info':
                return $this->formatRevenueInfo($result, $params);
                
            case 'stock_status':
                return $this->formatStockStatus($data, $params);
                
            case 'daily_sales':
                return $this->formatDailySales($result, $params);
                
            case 'monthly_sales':
                return $this->formatMonthlySales($result, $params);
                
            case 'customer_info':
                return $this->formatCustomerInfo($result, $params);
                
            default:
                return "Üzgünüm, bu sorgu tipini desteklemiyorum: $query_type";
        }
    }
    
    /**
     * En çok satan ürünleri formatla
     */
    private function formatTopSellingProducts($data, $params) {
        if (empty($data)) {
            return "Belirtilen dönemde hiç ürün satışı bulunmuyor.";
        }
        
        $period = $this->getPeriodText($params['period'] ?? 'this_month');
        $response = "$period en çok satan ürünler:\n\n";
        
        foreach ($data as $index => $product) {
            $rank = $index + 1;
            $response .= "$rank. {$product['urun_adi']} - {$product['toplam_satis']} adet";
            
            if (isset($product['toplam_tutar'])) {
                $response .= " - " . $this->formatMoney($product['toplam_tutar']) . " TL";
            }
            
            $response .= "\n";
        }
        
        return $response;
    }
    
    /**
     * En çok satış yapan personeli formatla
     */
    private function formatTopSalespeople($data, $params) {
        if (empty($data)) {
            return "Belirtilen dönemde hiç satış kaydı bulunmuyor.";
        }
        
        $period = $this->getPeriodText($params['period'] ?? 'this_month');
        $response = "$period en çok satış yapan personel:\n\n";
        
        foreach ($data as $index => $staff) {
            $rank = $index + 1;
            $response .= "$rank. {$staff['personel_adi']} - {$staff['satis_adedi']} işlem - " . 
                         $this->formatMoney($staff['toplam_satis']) . " TL\n";
        }
        
        return $response;
    }
    
    /**
     * Ciro bilgilerini formatla
     */
    private function formatRevenueInfo($result, $params) {
        $data = $result['data'] ?? null;
        $payments = $result['payments'] ?? [];
        
        if (!$data) {
            return "Belirtilen dönemde hiç satış kaydı bulunmuyor.";
        }
        
        $period = $this->getPeriodText($params['period'] ?? 'this_month');
        $response = "$period ciro bilgileri:\n\n";
        
        $response .= "💰 Toplam Ciro: " . $this->formatMoney($data['toplam_ciro']) . " TL\n";
        $response .= "🧾 Toplam İşlem: {$data['fatura_sayisi']} adet\n";
        $response .= "🛒 Ortalama Sepet: " . $this->formatMoney($data['ortalama_sepet']) . " TL\n";
        
        if (!empty($payments)) {
            $response .= "\nÖdeme Türleri Dağılımı:\n";
            
            foreach ($payments as $payment) {
                $payment_type = $this->getPaymentTypeName($payment['odeme_turu']);
                $percentage = ($payment['toplam'] / $data['toplam_ciro']) * 100;
                
                $response .= "- $payment_type: " . $this->formatMoney($payment['toplam']) . 
                             " TL (%" . number_format($percentage, 1) . ")\n";
            }
        }
        
        return $response;
    }
    
    /**
     * Stok durumunu formatla
     */
    private function formatStockStatus($data, $params) {
        if (empty($data)) {
            return "Hiç ürün bulunamadı.";
        }
        
        $sort = $params['sort'] ?? 'low';
        
        if ($sort == 'low') {
            $response = "Stoğu en az kalan ürünler:\n\n";
        } else {
            $response = "Stoğu en çok olan ürünler:\n\n";
        }
        
        $critical_count = 0;
        
        foreach ($data as $index => $product) {
            $rank = $index + 1;
            
            $stock_status = $product['stok_miktari'];
            if ($stock_status <= 0) {
                $stock_text = "TÜKENDİ!";
                $critical_count++;
            } elseif ($stock_status <= 5) {
                $stock_text = "KRİTİK: $stock_status adet";
                $critical_count++;
            } else {
                $stock_text = "$stock_status adet";
            }
            
            $response .= "$rank. {$product['urun_adi']} - $stock_text - " . 
                         $this->formatMoney($product['satis_fiyati']) . " TL\n";
        }
        
        if ($sort == 'low' && $critical_count > 0) {
            $response .= "\nToplam $critical_count üründe stok durumu kritik seviyede veya tükenmiş.";
        }
        
        return $response;
    }
    
    /**
     * Günlük satışları formatla
     */
    private function formatDailySales($result, $params) {
        $data = $result['data'] ?? [];
        $total = $result['total'] ?? null;
        
        if (empty($data)) {
            return "Belirtilen günde hiç satış kaydı bulunmuyor.";
        }
        
        $date = $params['date'] ?? date('Y-m-d');
        $formatted_date = date('d.m.Y', strtotime($date));
        
        $response = "$formatted_date tarihli satış verileri:\n\n";
        
        foreach ($data as $hour) {
            $response .= "{$hour['saat']} - {$hour['islem_sayisi']} işlem - " . 
                         $this->formatMoney($hour['toplam_satis']) . " TL\n";
        }
        
        if ($total) {
            $response .= "\nToplam: {$total['toplam_islem_sayisi']} işlem - " . 
                         $this->formatMoney($total['toplam_tutar']) . " TL";
        }
        
        return $response;
    }
    
    /**
     * Aylık satışları formatla
     */
    private function formatMonthlySales($result, $params) {
        $data = $result['data'] ?? [];
        $total = $result['total'] ?? null;
        
        if (empty($data)) {
            return "Belirtilen ayda hiç satış kaydı bulunmuyor.";
        }
        
        $year = $params['year'] ?? date('Y');
        $month = $params['month'] ?? date('m');
        $month_name = $this->getMonthName($month);
        
        $response = "$year $month_name ayı satış verileri:\n\n";
        
        foreach ($data as $day) {
            $response .= "{$day['gun']} $month_name - {$day['islem_sayisi']} işlem - " . 
                         $this->formatMoney($day['toplam_satis']) . " TL\n";
        }
        
        if ($total) {
            $response .= "\nAylık Toplam: {$total['toplam_islem_sayisi']} işlem - " . 
                         $this->formatMoney($total['toplam_tutar']) . " TL";
        }
        
        return $response;
    }
    
    /**
     * Müşteri bilgilerini formatla
     */
    private function formatCustomerInfo($result, $params) {
        $data = $result['data'] ?? [];
        $total_customers = $result['total_customers'] ?? 0;
        
        if (empty($data)) {
            return "Belirtilen dönemde hiç müşteri kaydı bulunmuyor.";
        }
        
        $period = $this->getPeriodText($params['period'] ?? 'all_time');
        
        if ($period == 'tüm zamanlar') {
            $response = "En çok alışveriş yapan müşteriler:\n\n";
        } else {
            $response = "$period en çok alışveriş yapan müşteriler:\n\n";
        }
        
        foreach ($data as $index => $customer) {
            $rank = $index + 1;
            $response .= "$rank. {$customer['musteri_adi']} - {$customer['toplam_islem']} işlem - " . 
                         $this->formatMoney($customer['toplam_harcama']) . " TL\n";
        }
        
        if ($total_customers > 0) {
            $response .= "\nToplam Aktif Müşteri: $total_customers";
        }
        
        return $response;
    }
    
    
    /**
     * Dönemi insan okunabilir metne dönüştür
     */
    private function getPeriodText($period) {
        switch ($period) {
            case 'today':
                return "Bugün";
            case 'yesterday':
                return "Dün";
            case 'this_week':
                return "Bu hafta";
            case 'last_week':
                return "Geçen hafta";
            case 'this_month':
                return "Bu ay";
            case 'last_month':
                return "Geçen ay";
            case 'this_year':
                return "Bu yıl";
            case 'last_year':
                return "Geçen yıl";
            case 'all_time':
                return "Tüm zamanlar";
            default:
                // Özel ay (format: month_YYYY_MM)
                if (preg_match('/^month_(\d{4})_(\d{2})$/', $period, $matches)) {
                    $year = $matches[1];
                    $month = $matches[2];
                    $month_name = $this->getMonthName($month);
                    return "$year $month_name";
                }
                return $period;
        }
    }
    
    /**
     * Ay numarasından ay adını al
     */
    private function getMonthName($month) {
        $months = [
            '01' => 'Ocak', '02' => 'Şubat', '03' => 'Mart', '04' => 'Nisan',
            '05' => 'Mayıs', '06' => 'Haziran', '07' => 'Temmuz', '08' => 'Ağustos',
            '09' => 'Eylül', '10' => 'Ekim', '11' => 'Kasım', '12' => 'Aralık'
        ];
        
        return $months[$month] ?? $month;
    }
    
    /**
     * Ödeme türü adını düzenle
     */
    private function getPaymentTypeName($type) {
        $types = [
            'nakit' => 'Nakit',
            'kredi_karti' => 'Kredi Kartı',
            'havale' => 'Havale/EFT'
        ];
        
        return $types[$type] ?? ucfirst($type);
    }
    
    /**
     * Para birimini formatla
     */
    private function formatMoney($amount) {
        return number_format($amount, 2, ',', '.');
    }
    
    /**
     * Claude API'ye sorgu gönder
     */
    public function sendMessage($userMessage, $conversation = []) {
        // API anahtarı yoksa hata döndür
        if (empty($this->apiKey) || $this->apiKey === "YOUR_CLAUDE_API_KEY_HERE") {
            error_log("API anahtarı ayarlanmamış!");
            return [
                'success' => false,
                'response' => null,
                'error' => 'Claude API anahtarı ayarlanmamış. Lütfen API anahtarınızı sistem ayarlarına ekleyin.'
            ];
        }
        
        error_log("Kullanıcı mesajı: " . $userMessage);
        
        // Veritabanı sorgusu gerektiren bir soru mu kontrol et
        $dbQueryInfo = null;
        $dbQueryResult = null;
        
        if ($this->needsDatabaseQuery($userMessage)) {
            $dbQueryInfo = $this->analyzeQuery($userMessage);
            
            if ($dbQueryInfo) {
                try {
                    error_log("Veritabanı sorgusu hazırlanıyor: " . $dbQueryInfo['query_type'] . ", params: " . json_encode($dbQueryInfo['params']));
                    
                    // Veritabanı sorgusunu çalıştır
                    $dbQueryResult = $this->dbProxy->executeQuery(
                        $dbQueryInfo['query_type'], 
                        $dbQueryInfo['params']
                    );
                    
                    error_log("Veritabanı sorgusu çalıştırıldı, sonuç: " . (isset($dbQueryResult['success']) ? ($dbQueryResult['success'] ? 'Başarılı' : 'Başarısız') : 'Bilinmiyor'));
                    
                    // Veritabanından yanıt oluştur
                    if ($dbQueryResult && isset($dbQueryResult['success']) && $dbQueryResult['success']) {
                        $dbResponse = $this->generateResponseFromDatabaseQuery(
                            $dbQueryInfo['query_type'],
                            $dbQueryInfo['params'],
                            $dbQueryResult
                        );
                        
                        error_log("Veritabanı yanıtı oluşturuldu: " . substr($dbResponse, 0, 100) . "...");
                        
                        // Doğrudan veritabanı yanıtını kullan
                        return [
                            'success' => true,
                            'response' => $dbResponse,
                            'db_query' => true,
                            'error' => null
                        ];
                    } else {
                        error_log("Veritabanı sorgusu başarısız oldu: " . ($dbQueryResult['error'] ?? 'Bilinmeyen hata'));
                        // Hata durumunda veritabanı hatasını Claude'a bildir
                        $userMessage .= "\n\n[NOT: Veritabanı sorgusu hata verdi: " . ($dbQueryResult['error'] ?? 'Bilinmeyen hata') . "]";
                    }
                } catch (Exception $e) {
                    error_log("Veritabanı sorgusu hatası: " . $e->getMessage());
                    $userMessage .= "\n\n[NOT: Veritabanı sorgusu hata verdi: " . $e->getMessage() . "]";
                }
            } else {
                error_log("Veritabanı sorgusu gereken bir soru olmasına rağmen, belirli bir sorgu türü tespit edilemedi.");
            }
        }
        
        // Veritabanı sorgusu başarısız olduysa veya sorgu gerektirmeyen bir soru ise Claude'a sor
        // Mesaj geçmişini formatla
        $formattedMessages = [];
        
        // Önceki mesajları ekle
        foreach ($conversation as $message) {
            if (isset($message['role']) && isset($message['content'])) {
                $formattedMessages[] = [
                    'role' => $message['role'],
                    'content' => $message['content']
                ];
            }
        }
        
        // Kullanıcı mesajını ekle
        $formattedMessages[] = [
            'role' => 'user',
            'content' => $userMessage
        ];
        
        // API isteği için veriyi hazırla
        $requestData = [
            'model' => $this->model,
            'messages' => $formattedMessages,
            'system' => $this->systemPrompt,
            'max_tokens' => 1000,
            'temperature' => 0.7
        ];
        
        error_log("Claude API isteği gönderiliyor...");
        
        // API isteğini gönder
        $response = $this->makeRequest($requestData);
        
        // Yanıtı işle
        if ($response && isset($response['content'][0]['text'])) {
            error_log("Claude API yanıt verdi, ilk 100 karakter: " . substr($response['content'][0]['text'], 0, 100));
            return [
                'success' => true,
                'response' => $response['content'][0]['text'],
                'db_query' => false,
                'error' => null
            ];
        } else {
            $error = isset($response['error']) ? $response['error']['message'] : 'Bilinmeyen API hatası';
            error_log("Claude API hatası: " . $error);
            return [
                'success' => false,
                'response' => null,
                'error' => $error
            ];
        }
    }
    
    /**
     * API isteği gönder
     */
    private function makeRequest($data) {
        // Debug için istek verilerini kaydet
        error_log("Claude API Request URL: " . $this->apiEndpoint);
        
        // Hassas bilgiler olmadan istek verileri
        $logData = $data;
        if (isset($logData['system'])) {
            $logData['system'] = substr($logData['system'], 0, 100) . "..."; // Sistem komutunu kısalt
        }
        error_log("Claude API Request Data (kısmen): " . json_encode(array_slice($logData, 0, 3)));
        
        // cURL oluştur
        $ch = curl_init($this->apiEndpoint);
        
        // JSON'a çevir
        $jsonData = json_encode($data);
        if ($jsonData === false) {
            error_log("JSON encoding error: " . json_last_error_msg());
            return ['error' => ['message' => 'JSON encoding failed: ' . json_last_error_msg()]];
        }
        
        // cURL ayarları
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'x-api-key: ' . $this->apiKey,
            'anthropic-version: 2023-06-01'
        ]);
        
        // İsteği gönder
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        // cURL hata kontrolü
        if ($response === false) {
            $error = curl_error($ch);
            error_log("cURL Error: $error");
            curl_close($ch);
            return ['error' => ['message' => "cURL Error: $error"]];
        }
        
        curl_close($ch);
        
        // Debug için yanıtı kaydet
        error_log("Claude API Response Status: $status");
        if ($status != 200) {
            error_log("Hata Yanıt: $response");
        } else {
            error_log("Başarılı Yanıt (ilk 100 karakter): " . substr($response, 0, 100));
        }
        
        // JSON yanıtı çözümle
        $responseData = json_decode($response, true);
        
        // JSON hata kontrolü
        if ($responseData === null) {
            error_log("JSON Decode Error: " . json_last_error_msg() . " - Raw response: " . substr($response, 0, 1000));
            return ['error' => ['message' => 'JSON decode failed: ' . json_last_error_msg()]];
        }
        
        return $responseData;
    }
    
    /**
     * Test fonksiyonu - API'yi basit bir sorguyla test et
     */
    public function testConnection() {
        $testMessage = "Merhaba, bu bir test mesajıdır.";
        
        try {
            error_log("Claude API bağlantı testi yapılıyor...");
            $result = $this->sendMessage($testMessage);
            error_log("Claude API bağlantı testi sonucu: " . ($result['success'] ? 'Başarılı' : 'Başarısız'));
            return $result;
        } catch (Exception $e) {
            error_log("Claude API bağlantı testi hatası: " . $e->getMessage());
            return [
                'success' => false,
                'response' => null,
                'error' => "Test hatası: " . $e->getMessage()
            ];
        }
    }
}

// API olarak kullanılıyorsa
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    // CORS izinleri
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, x-api-key');
    
    // OPTIONS isteği için erken dönüş
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        http_response_code(200);
        exit;
    }
    
    // POST metodunu kontrol et
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Yalnızca POST metodu destekleniyor']);
        exit;
    }
    
    try {
        // İsteği al
        $rawInput = file_get_contents('php://input');
        
        // PHP input'undan JSON veriyi al
        if (!empty($rawInput)) {
            $data = json_decode($rawInput, true);
            
            // JSON decode hatası kontrolü
            if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('JSON parse error: ' . json_last_error_msg() . ' - Raw input: ' . substr($rawInput, 0, 100));
            }
        } 
        // Veya normal POST verisinden al
        else {
            $data = $_POST;
        }
        
        // Mesaj kontrolü
        $message = isset($data['message']) ? $data['message'] : null;
        $conversation = isset($data['conversation']) ? $data['conversation'] : [];
        
        if (!$message) {
            throw new Exception('Mesaj parametresi eksik');
        }
        
        global $conn;
        
        // Claude API'yi başlat
        $claude = new ClaudeAPI($conn);
        
        // Test için API kullanılabilirliğini kontrol et (isteğe bağlı)
        $testResponse = null;
        if (isset($data['test']) && $data['test'] === true) {
            $testResponse = $claude->testConnection();
            echo json_encode($testResponse);
            exit;
        }
        
        // Normal mesaj işleme
        $response = $claude->sendMessage($message, $conversation);
        
        // Yanıtı JSON olarak döndür
        echo json_encode($response);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}