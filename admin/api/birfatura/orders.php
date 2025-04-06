<?php
// Yardımcı fonksiyonları dahil et
if (!function_exists('sendResponse')) {
    require_once __DIR__ . '/helpers.php';
}

// API isteğini logla
if (!isset($log_file)) {
    $log_file = __DIR__ . '/api_log.txt';
}
file_put_contents($log_file, date('Y-m-d H:i:s') . " - orders endpoint çalıştırılıyor\n", FILE_APPEND);

// Token kontrolü
$token = getFlexibleToken($headers);
$expected_token = 'KeHxXtvWK6QovGL';

if ($token !== $expected_token) {
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - Token hatası. Beklenen: $expected_token, Gelen: $token\n", FILE_APPEND);
    sendResponse(['error' => 'Unauthorized access'], 401);
}

// POST metodu kontrolü
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(['error' => 'Method not allowed'], 405);
}

// JSON verisini al
$jsonData = file_get_contents('php://input');
$data = json_decode($jsonData, true);

// Tarih parametreleri
$startDateTime = $data['startDateTime'] ?? null;
$endDateTime = $data['endDateTime'] ?? null;
$statusId = $data['orderStatusId'] ?? null;

// Türkçe tarih formatını MySQL formatına dönüştür
if ($startDateTime) {
    $startDate = DateTime::createFromFormat('d.m.Y H:i:s', $startDateTime);
    if ($startDate) {
        $startDateTime = $startDate->format('Y-m-d H:i:s');
    } else {
        $startDateTime = null;
    }
}

if ($endDateTime) {
    $endDate = DateTime::createFromFormat('d.m.Y H:i:s', $endDateTime);
    if ($endDate) {
        $endDateTime = $endDate->format('Y-m-d H:i:s');
    } else {
        $endDateTime = null;
    }
}

file_put_contents($log_file, date('Y-m-d H:i:s') . " - Tarih aralığı: " . ($startDateTime ?? 'null') . " - " . ($endDateTime ?? 'null') . ", Durum: " . ($statusId ?? 'null') . "\n", FILE_APPEND);

// Veritabanı bağlantısı
try {
    $dbname = 'incikir2_pos';
    $username = 'incikir2_posadmin';
    $password = 'vD3YjbzpPYsc';
    
    $db = new PDO('mysql:host=localhost;dbname='.$dbname, $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("SET NAMES utf8");
    
    // Satış faturalarını sorgula - Müşteri bilgilerini içerecek şekilde
    $query = "SELECT sf.*, m.ad as musteri_ad, m.soyad as musteri_soyad, m.telefon as musteri_telefon, 
              m.email as musteri_email, mg.ad as magaza_ad, mg.adres as magaza_adres, 
              mg.telefon as magaza_telefon
              FROM satis_faturalari sf
              LEFT JOIN musteriler m ON sf.musteri_id = m.id
              LEFT JOIN magazalar mg ON sf.magaza = mg.id
              WHERE sf.islem_turu = 'satis'";
    $params = [];
    
    // Tarih filtreleri ekle
    if ($startDateTime) {
        $query .= " AND sf.fatura_tarihi >= ?";
        $params[] = $startDateTime;
    }
    
    if ($endDateTime) {
        $query .= " AND sf.fatura_tarihi <= ?";
        $params[] = $endDateTime;
    }
    
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - SQL sorgusu: " . $query . "\n", FILE_APPEND);
    
    // Sorguyu çalıştır
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - Bulunan sipariş sayısı: " . count($orders) . "\n", FILE_APPEND);
    
    // Varsayılan değerler
    $defaults = [
        'company_name' => 'İnci Kırtasiye',
        'address' => 'Mağaza Adresi',
        'town' => 'Beşiktaş',
        'city' => 'İstanbul',
        'mobile_phone' => '5551234567',
        'phone' => '02121234567',
        'tax_office' => 'Beşiktaş',
        'tax_no' => '11111111111',
        'ssn' => '11111111111',
        'email' => 'info@incikirtasiye.com',
        'zip_code' => '00000',
        'country' => 'Türkiye'
    ];
    
    // Siparişleri formatla
    $formattedOrders = [];
    
    foreach ($orders as $order) {
        // Sipariş detaylarını al
        $detailQuery = "SELECT sfd.*, us.ad as urun_adi, us.barkod, us.kdv_orani 
                       FROM satis_fatura_detay sfd
                       LEFT JOIN urun_stok us ON sfd.urun_id = us.id
                       WHERE sfd.fatura_id = ?";
        $detailStmt = $db->prepare($detailQuery);
        $detailStmt->execute([$order['id']]);
        $details = $detailStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Ürünleri formatla
        $products = [];
        $totalTaxExcluding = 0;
        $totalTaxIncluding = 0;
        
        foreach ($details as $detail) {
            $kdvOrani = isset($detail['kdv_orani']) ? floatval($detail['kdv_orani']) : 20; // Varsayılan KDV oranı
            $unitPriceTaxExcluding = round(($detail['birim_fiyat'] ?? 0) / (1 + ($kdvOrani / 100)), 4);
            $totalPriceTaxExcluding = round(($detail['toplam_tutar'] ?? 0) / (1 + ($kdvOrani / 100)), 4);
            $unitPriceTaxIncluding = floatval($detail['birim_fiyat'] ?? 0);
            $totalPriceTaxIncluding = floatval($detail['toplam_tutar'] ?? 0);
            
            $totalTaxExcluding += $totalPriceTaxExcluding;
            $totalTaxIncluding += $totalPriceTaxIncluding;
            
            // Güvenli string değerler oluştur
            $productName = $detail['urun_adi'] ?? 'Ürün';
            $productName = htmlspecialchars(strip_tags($productName), ENT_QUOTES, 'UTF-8');
            
            $barcodeValue = $detail['barkod'] ?? 'URUN' . rand(1000, 9999);
            $barcodeValue = htmlspecialchars(strip_tags($barcodeValue), ENT_QUOTES, 'UTF-8');
            
            $products[] = [
                "ProductId" => strval($detail['urun_id'] ?? '0'),
                "ProductCode" => $barcodeValue,
                "Barcode" => $barcodeValue,
                "ProductBrand" => "",
                "ProductName" => $productName,
                "ProductNote" => "",
                "ProductImage" => "",
                "Variants" => [],
                "ProductQuantityType" => "Adet",
                "ProductQuantity" => intval($detail['miktar'] ?? 1),
                "VatRate" => floatval($kdvOrani),
                "ProductUnitPriceTaxExcluding" => $unitPriceTaxExcluding,
                "ProductUnitPriceTaxIncluding" => $unitPriceTaxIncluding,
                "CommissionUnitTaxExcluding" => 0,
                "CommissionUnitTaxIncluding" => 0,
                "DiscountUnitTaxExcluding" => 0,
                "DiscountUnitTaxIncluding" => 0,
                "ExtraFeesUnit" => []
            ];
        }
        
        // Tarih formatını Birfatura'nın beklediği şekle dönüştür
        $orderDate = new DateTime($order['fatura_tarihi']);
        $orderDateFormatted = $orderDate->format('d.m.Y H:i:s');
        $invoiceDateFormatted = $orderDate->format('d.m.Y');
        
        // Sipariş ID'si için format oluştur
        $orderCode = "INV" . str_pad($order['id'], 10, "0", STR_PAD_LEFT);
        
        // Müşteri bilgilerini hazırla (veritabanı yapısına göre)
        $customerName = '';
        $customerEmail = '';
        $customerPhone = '';
        
        if (!empty($order['musteri_id']) && !empty($order['musteri_ad'])) {
            // Müşteri adı ve soyadını birleştir
            $customerName = trim($order['musteri_ad'] . ' ' . ($order['musteri_soyad'] ?? ''));
            $customerEmail = $order['musteri_email'] ?? $defaults['email'];
            $customerPhone = $order['musteri_telefon'] ?? $defaults['mobile_phone'];
        } else {
            // Varsayılan değerleri kullan
            $customerName = $defaults['company_name'];
            $customerEmail = $defaults['email'];
            $customerPhone = $defaults['mobile_phone'];
        }
        
        // Mağaza bilgilerini hazırla
        $storeName = $order['magaza_ad'] ?? $defaults['company_name'];
        $storeAddress = $order['magaza_adres'] ?? $defaults['address'];
        $storePhone = $order['magaza_telefon'] ?? $defaults['phone'];
        
        // Sipariş formatı - Birfatura'nın beklediği formatta tüm zorunlu alanlarla
        $formattedOrder = [
            "OrderId" => strval($order['id']),
            "OrderCode" => $orderCode,
            "OrderDate" => $orderDateFormatted,
            "InvoiceTypeId" => 1,
            "InvoiceDate" => $invoiceDateFormatted,
            "InvoiceExplanation" => "POS Satışı",
            "EInvoiceProfileId" => 1,
            "EInvoiceId" => "0",
            "ETTN" => "0",
            "CustomerId" => intval($order['musteri_id'] ?? 0),
            "BillingName" => $customerName,
            "BillingAddress" => $storeAddress, // Adres alanı müşteri tablosunda yok, mağaza adresini kullanıyoruz
            "BillingTown" => $defaults['town'], // İlçe alanı yok
            "BillingCity" => $defaults['city'], // İl alanı yok
            "BillingMobilePhone" => $customerPhone,
            "BillingPhone" => "",
            "TaxOffice" => $defaults['tax_office'], // Vergi dairesi alanı yok
            "TaxNo" => $defaults['tax_no'], // Vergi no alanı yok
            "SSNTCNo" => $defaults['ssn'], // TC no alanı yok
            "Email" => $customerEmail,
            "ShippingId" => intval($order['musteri_id'] ?? 0),
            "ShippingName" => $customerName,
            "ShippingAddress" => $storeAddress, // Adres alanı müşteri tablosunda yok
            "ShippingTown" => $defaults['town'], // İlçe alanı yok
            "ShippingCity" => $defaults['city'], // İl alanı yok
            "ShippingCountry" => $defaults['country'],
            "ShippingZipCode" => $defaults['zip_code'],
            "ShippingPhone" => $customerPhone,
            "ShipCompany" => "Mağaza Teslim",
            "CargoCampaignCode" => "0",
            "SalesChannelWebSite" => "www.incikirtasiye.com",
            "PaymentTypeId" => ($order['odeme_turu'] == 'nakit') ? 1 : 2,
            "PaymentType" => ($order['odeme_turu'] == 'nakit') ? "Nakit" : "Kredi Kartı",
            "Currency" => "TL",
            "CurrencyRate" => 1,
            "TotalPaidTaxExcluding" => $totalTaxExcluding,
            "TotalPaidTaxIncluding" => $totalTaxIncluding,
            "ProductsTotalTaxExcluding" => $totalTaxExcluding,
            "ProductsTotalTaxIncluding" => $totalTaxIncluding,
            "CommissionTotalTaxExcluding" => 0,
            "CommissionTotalTaxIncluding" => 0,
            "ShippingChargeTotalTaxExcluding" => 0,
            "ShippingChargeTotalTaxIncluding" => 0,
            "PayingAtTheDoorChargeTotalTaxExcluding" => 0,
            "PayingAtTheDoorChargeTotalTaxIncluding" => 0,
            "DiscountTotalTaxExcluding" => 0,
            "DiscountTotalTaxIncluding" => 0,
            "InstallmentChargeTotalTaxExcluding" => 0,
            "InstallmentChargeTotalTaxIncluding" => 0,
            "BankTransferDiscountTotalTaxExcluding" => 0,
            "BankTransferDiscountTotalTaxIncluding" => 0,
            "ExtraFees" => [],
            "OrderDetails" => $products
        ];
        
        $formattedOrders[] = $formattedOrder;
    }
    
    // Yanıt formatı - Doğru JSON formatını kullanmalıyız
    $response = [
        "Orders" => empty($formattedOrders) ? [] : $formattedOrders
    ];
    
    // Debug için yanıt formatını kontrol et
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - Yanıt formatı kontrol ediliyor\n", FILE_APPEND);
    $jsonOutput = json_encode($response, JSON_UNESCAPED_UNICODE);
    $jsonError = json_last_error();
    if ($jsonError) {
        file_put_contents($log_file, date('Y-m-d H:i:s') . " - JSON encode hatası: " . json_last_error_msg() . "\n", FILE_APPEND);
        // Hata durumunda boş yanıt
        sendResponse(["Orders" => []], 500);
        exit;
    }
    
    // Yanıt gönder
    sendResponse($response);
} 
catch (PDOException $e) {
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - Veritabanı hatası: " . $e->getMessage() . "\n", FILE_APPEND);
    
    // Hata durumunda boş siparişler dizisi dön
    sendResponse(["Orders" => []]);
}
?>