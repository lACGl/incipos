<?php
// Hata raporlamasını etkinleştir
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Veritabanı bağlantısını dahil et
require_once 'db_connection.php';

// Fonksiyonlar
function showError($title, $message) {
    echo '<!DOCTYPE html>
    <html lang="tr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . $title . '</title>
        <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    </head>
    <body class="bg-gray-100 p-4">
        <div class="max-w-lg mx-auto bg-white shadow-lg rounded-lg overflow-hidden">
            <div class="bg-red-600 text-white p-4">
                <h1 class="text-xl font-bold">' . $title . '</h1>
            </div>
            <div class="p-4">
                <p class="text-gray-700">' . $message . '</p>
            </div>
            <div class="p-4 bg-gray-50 text-center text-xs text-gray-500">
                <p>© ' . date('Y') . ' İnciPOS - Tüm Hakları Saklıdır</p>
            </div>
        </div>
    </body>
    </html>';
    exit;
}

// Token kontrolü
if (!isset($_GET['token']) || empty($_GET['token'])) {
    showError('Geçersiz İstek', 'Geçerli bir erişim tokeni belirtilmedi.');
}

$token = $_GET['token'];

try {
    // Veritabanı bağlantısının kontrolü
    if (!$conn) {
        throw new Exception("Veritabanı bağlantısı kurulamadı.");
    }
    
    // Token geçerliliğini kontrol et
    $token_query = "
        SELECT t.*, sf.* 
        FROM fatura_erisim_token t
        JOIN satis_faturalari sf ON t.fatura_id = sf.id
        WHERE t.token = ? AND t.son_gecerlilik >= NOW()
        LIMIT 1
    ";
    
    $stmt = $conn->prepare($token_query);
    $stmt->execute([$token]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        // Token doğrudan kontrol
        $check_token = "SELECT * FROM fatura_erisim_token WHERE token = ?";
        $check_stmt = $conn->prepare($check_token);
        $check_stmt->execute([$token]);
        $token_exists = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($token_exists) {
            if (strtotime($token_exists['son_gecerlilik']) < time()) {
                showError('Süresi Dolmuş Token', 'Bu erişim linki süresi dolmuş. Lütfen yeni bir link talep edin.');
            } else {
                // Token var ama fatura bulunamadı, direkt faturayı alalım
                $invoice_query = "SELECT * FROM satis_faturalari WHERE id = ?";
                $inv_stmt = $conn->prepare($invoice_query);
                $inv_stmt->execute([$token_exists['fatura_id']]);
                $invoice_exists = $inv_stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$invoice_exists) {
                    showError('Fiş Bulunamadı', 'Bu token ile ilişkili fiş bulunamadı.');
                } else {
                    $result = array_merge($token_exists, $invoice_exists);
                }
            }
        } else {
            showError('Geçersiz Token', 'Bu erişim linki geçersiz. Lütfen doğru linki kullandığınızdan emin olun.');
        }
    }
    
    // Token kullanım sayısını artır
    $usage_query = "UPDATE fatura_erisim_token SET kullanim_sayisi = IFNULL(kullanim_sayisi, 0) + 1 WHERE token = ?";
    $usage_stmt = $conn->prepare($usage_query);
    $usage_stmt->execute([$token]);
    
    // Fatura bilgilerini al
    $invoice_id = $result['fatura_id'];
    $invoice = $result;
    
    // Fatura detaylarını al
    $details_query = "
        SELECT 
            sfd.*,
            IFNULL(us.ad, 'Ürün Bulunamadı') as urun_adi,
            IFNULL(us.barkod, '') as barkod
        FROM 
            satis_fatura_detay sfd
            LEFT JOIN urun_stok us ON sfd.urun_id = us.id
        WHERE 
            sfd.fatura_id = ?
    ";
    
    $stmt = $conn->prepare($details_query);
    $stmt->execute([$invoice_id]);
    $details = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($details)) {
        // Fatura detayları boş, ama devam edelim - belki sistem hatalıdır
        $details = [];
    }
    
    // Firma bilgilerini al 
    $company = null;
    try {
        $company_query = "SELECT * FROM firma_bilgileri LIMIT 1";
        $company_stmt = $conn->prepare($company_query);
        $company_stmt->execute();
        $company = $company_stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Firma bilgileri tablosu olmayabilir, hatayı yok sayalım
        $company = null;
    }
    
    // Mağaza bilgilerini al
    $store = null;
    try {
        if (isset($invoice['magaza'])) {
            $store_query = "SELECT * FROM magazalar WHERE id = ?";
            $store_stmt = $conn->prepare($store_query);
            $store_stmt->execute([$invoice['magaza']]);
            $store = $store_stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        // Mağaza bilgileri alınamadı, hatayı yok sayalım
        $store = null;
    }
    
    // Müşteri bilgilerini al
    $customer = null;
    try {
        if (isset($invoice['musteri_id']) && $invoice['musteri_id']) {
            $customer_query = "SELECT * FROM musteriler WHERE id = ?";
            $customer_stmt = $conn->prepare($customer_query);
            $customer_stmt->execute([$invoice['musteri_id']]);
            $customer = $customer_stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        // Müşteri bilgileri alınamadı, hatayı yok sayalım
        $customer = null;
    }
    
} catch (Exception $e) {
    // Hata durumunda sadece genel bir hata mesajı gösterelim
    showError('Sistem Hatası', 'Fiş bilgileri alınırken bir hata oluştu. Lütfen daha sonra tekrar deneyin.');
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Satış Fişi #<?php echo isset($invoice['fatura_seri']) ? $invoice['fatura_seri'] . $invoice['fatura_no'] : 'Detay'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 p-4">
    <div class="max-w-3xl mx-auto bg-white shadow-lg rounded-lg overflow-hidden">
        <!-- Fatura Başlığı -->
        <div class="bg-blue-600 text-white p-4">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-bold">
                    <?php echo isset($invoice['islem_turu']) && $invoice['islem_turu'] == 'iade' ? 'İADE FİŞİ' : 'SATIŞ FİŞİ'; ?>
                </h1>
                <div class="text-right">
                    <p class="text-sm">Fiş No: <strong><?php echo isset($invoice['fatura_seri']) ? $invoice['fatura_seri'] . $invoice['fatura_no'] : ''; ?></strong></p>
                    <p class="text-sm">Tarih: <?php echo isset($invoice['fatura_tarihi']) ? date('d.m.Y H:i', strtotime($invoice['fatura_tarihi'])) : ''; ?></p>
                </div>
            </div>
        </div>
        
        <!-- Firma ve Müşteri Bilgileri -->
        <div class="p-4 border-b">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Firma Bilgileri -->
                <div>
                    <h3 class="font-semibold text-gray-700 mb-2">Firma Bilgileri</h3>
                    <?php if ($company || $store): ?>
                    <p class="text-gray-600">İnci Kırtasiye - <?php echo $company && isset($company['firma_unvan']) ? $company['firma_unvan'] : ($store && isset($store['ad']) ? $store['ad'] : 'İnciPOS'); ?> Şube</p>
                    <p class="text-gray-600"><?php echo $store && isset($store['adres']) ? $store['adres'] : ''; ?></p>
                    <p class="text-gray-600"><?php echo $store && isset($store['telefon']) ? $store['telefon'] : ''; ?></p>
                    <?php if ($company && isset($company['vergi_no']) && !empty($company['vergi_no'])): ?>
                    <p class="text-gray-600">Vergi No: <?php echo $company['vergi_no']; ?></p>
                    <?php endif; ?>
                    <?php else: ?>
                    <p class="text-gray-600">İnciPOS</p>
                    <?php endif; ?>
                </div>
                
                <!-- Müşteri Bilgileri -->
                <div>
                    <h3 class="font-semibold text-gray-700 mb-2">Müşteri Bilgileri</h3>
                    <?php if ($customer): ?>
                    <p class="text-gray-600"><?php echo isset($customer['ad']) && isset($customer['soyad']) ? $customer['ad'] . ' ' . $customer['soyad'] : ''; ?></p>
                    <?php if (isset($customer['adres']) && !empty($customer['adres'])): ?>
                    <p class="text-gray-600"><?php echo $customer['adres']; ?></p>
                    <?php endif; ?>
                    <?php if (isset($customer['telefon']) && !empty($customer['telefon'])): ?>
                    <p class="text-gray-600"><?php echo $customer['telefon']; ?></p>
                    <?php endif; ?>
                    <?php else: ?>
                    <p class="text-gray-600">Bireysel Müşteri</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Fatura Detayları -->
        <div class="p-4">
            <table class="min-w-full divide-y divide-gray-200">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Ürün</th>
                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Miktar</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Birim Fiyat</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">KDV %</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">İndirim %</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Toplam</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($details)): ?>
                    <tr>
                        <td colspan="6" class="px-3 py-4 text-center text-sm text-gray-500">
                            Fiş detayları bulunamadı.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($details as $item): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900">
                            <div><?php echo isset($item['urun_adi']) ? $item['urun_adi'] : 'Ürün Adı Bilinmiyor'; ?></div>
                            <div class="text-xs text-gray-500"><?php echo isset($item['barkod']) ? $item['barkod'] : ''; ?></div>
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap text-sm text-center text-gray-500">
                            <?php echo isset($item['miktar']) ? $item['miktar'] : ''; ?>
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap text-sm text-right text-gray-500">
                            <?php echo isset($item['birim_fiyat']) ? number_format($item['birim_fiyat'], 2, ',', '.') . ' ₺' : ''; ?>
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap text-sm text-right text-gray-500">
                            <?php echo isset($item['kdv_orani']) ? '%' . number_format($item['kdv_orani'], 0) : ''; ?>
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap text-sm text-right text-gray-500">
                            <?php echo isset($item['indirim_orani']) ? '%' . number_format($item['indirim_orani'], 0) : ''; ?>
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap text-sm text-right text-gray-500">
                            <?php echo isset($item['toplam_tutar']) ? number_format($item['toplam_tutar'], 2, ',', '.') . ' ₺' : ''; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Fatura Toplamlar -->
        <div class="p-4 border-t">
            <div class="flex justify-end">
                <div class="w-64">
                    <div class="flex justify-between py-1 text-sm">
                        <div class="text-gray-600">Toplam:</div>
                        <div class="font-medium"><?php echo isset($invoice['toplam_tutar']) ? number_format($invoice['toplam_tutar'], 2, ',', '.') . ' ₺' : ''; ?></div>
                    </div>
                    <div class="flex justify-between py-1 text-sm">
                        <div class="text-gray-600">İndirim:</div>
                        <div class="font-medium"><?php echo isset($invoice['indirim_tutari']) ? number_format($invoice['indirim_tutari'], 2, ',', '.') . ' ₺' : ''; ?></div>
                    </div>
                    <div class="flex justify-between py-1 text-sm">
                        <div class="text-gray-600">KDV:</div>
                        <div class="font-medium"><?php echo isset($invoice['kdv_tutari']) ? number_format($invoice['kdv_tutari'], 2, ',', '.') . ' ₺' : ''; ?></div>
                    </div>
                    <div class="flex justify-between pt-2 border-t mt-2">
                        <div class="text-gray-900 font-bold">Genel Toplam:</div>
                        <div class="text-xl font-bold text-blue-600"><?php echo isset($invoice['net_tutar']) ? number_format($invoice['net_tutar'], 2, ',', '.') . ' ₺' : ''; ?></div>
                    </div>
                </div>
            </div>
        </div>
        
<!-- Fatura Notları -->
<?php 
if (isset($invoice['aciklama']) && !empty($invoice['aciklama'])) {
    echo htmlspecialchars($invoice['aciklama']);
} else {
    // Ödeme türüne göre not göster
    if($invoice['odeme_turu'] == 'borc') {
        // Eğer ödeme türü borç ise
        echo "Açık Hesap - Ödenmedi";
    } else {
        // Diğer tüm durumlarda ödeme tamamlandı göster
        echo "POS üzerinden satış - Ödeme Tamamlandı: " . number_format($invoice['net_tutar'], 2, ',', '.') . " TL";
    }
}
?>
        <!-- Footer -->
        <div class="p-4 bg-gray-50 text-center text-xs text-gray-500">
            <p>Bu satış fişi <?php echo date('d.m.Y H:i'); ?> tarihinde oluşturulmuştur.</p>
            <p>© <?php echo date('Y'); ?> İnciPOS - Tüm Hakları Saklıdır.</p>
            <p>**MALİ DEĞERİ YOKTUR**</p>
        </div>
    </div>
    
    <!-- Yazdırma Butonu -->
    <div class="max-w-3xl mx-auto mt-4 text-center">
        <button onclick="window.print()" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
            <svg class="inline-block w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
            </svg>
            Fişi Yazdır
        </button>
    </div>
    
    <!-- Yazdırma Stili -->
    <style type="text/css" media="print">
        @page {
            size: auto;
            margin: 10mm;
        }
        body {
            background-color: #fff;
            margin: 0;
            padding: 0;
        }
        .max-w-3xl {
            max-width: 100%;
        }
        button {
            display: none;
        }
    </style>
</body>
</html>