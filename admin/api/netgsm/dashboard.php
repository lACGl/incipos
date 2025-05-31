<?php
require_once '../session_manager.php'; // Otomatik eklendi
secure_session_start();

// Sayfa yüklenme süresini hesapla
$start_time = microtime(true);

// Veritabanı bağlantısı ve NetGSM API'sini dahil et
require_once '../../db_connection.php';
require_once 'netgsm_api.php';

// Yetki kontrolü
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: ../../index.php");
    exit;
}

// NetGSM API örneği oluştur
$netgsm_api = new NetGSMAPI($conn);

// İşlem mesajları
$success_message = '';
$error_message = '';

// Tekli SMS gönderimi
if (isset($_POST['send_single_sms'])) {
    $phone = isset($_POST['phone_number']) ? $_POST['phone_number'] : '';
    $message = isset($_POST['message']) ? $_POST['message'] : '';
    
    if (empty($phone) || empty($message)) {
        $error_message = "Telefon numarası ve mesaj alanları boş olamaz.";
    } else {
        try {
            $result = $netgsm_api->sendSMS($phone, $message);
            
            if ($result['success']) {
                $success_message = "SMS başarıyla gönderildi. ID: " . (isset($result['job_id']) ? $result['job_id'] : 'Bilinmiyor');
            } else {
                $error_message = "SMS gönderilemedi: " . $result['message'];
            }
        } catch (Exception $e) {
            $error_message = "SMS gönderimi sırasında hata oluştu: " . $e->getMessage();
        }
    }
}

// Toplu SMS gönderimi
if (isset($_POST['send_bulk_sms'])) {
    $customer_group = isset($_POST['customer_group']) ? $_POST['customer_group'] : 'all';
    $message = isset($_POST['bulk_message']) ? $_POST['bulk_message'] : '';
    
    if (empty($message)) {
        $error_message = "Mesaj metni boş olamaz.";
    } else {
        try {
            // Müşteri grubuna göre müşterileri al
            $customer_query = "SELECT id, ad as name, telefon as phone FROM musteriler WHERE durum = 'aktif' AND sms_aktif = 1";
            
            if ($customer_group == 'gold') {
                $customer_query .= " AND id IN (SELECT musteri_id FROM musteri_puanlar WHERE musteri_turu = 'gold')";
            } else if ($customer_group == 'platinum') {
                $customer_query .= " AND id IN (SELECT musteri_id FROM musteri_puanlar WHERE musteri_turu = 'platinum')";
            } else if ($customer_group == 'new') {
                $customer_query .= " AND kayit_tarihi >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            }
            
            $stmt = $conn->prepare($customer_query);
            $stmt->execute();
            $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($customers)) {
                $error_message = "Seçilen grupta SMS gönderilebilecek müşteri bulunamadı.";
            } else {
                $results = $netgsm_api->sendBulkSMS($customers, $message);
                $success_count = 0;
                
                foreach ($results as $result) {
                    if (isset($result['result']) && isset($result['result']['success']) && $result['result']['success']) {
                        $success_count++;
                    }
                }
                
                $success_message = "$success_count / " . count($customers) . " SMS başarıyla gönderildi.";
            }
        } catch (Exception $e) {
            $error_message = "Toplu SMS gönderimi sırasında hata oluştu: " . $e->getMessage();
        }
    }
}

// NetGSM Bakiyesini Al
try {
    $balance = $netgsm_api->getBalance();
} catch (Exception $e) {
    $error_message = "Bakiye bilgisi alınırken hata oluştu: " . $e->getMessage();
    $balance = ['success' => false, 'message' => $e->getMessage()];
}

// SMS Raporlarını Al (son 7 gün)
try {
    $reports = $netgsm_api->getReports();
} catch (Exception $e) {
    $error_message = "Raporlar alınırken hata oluştu: " . $e->getMessage();
    $reports = ['success' => false, 'message' => $e->getMessage()];
}

// Son 30 günlük SMS istatistikleri
try {
    $stats_query = "SELECT 
                      COUNT(*) as total_sms,
                      SUM(CASE WHEN yanit REGEXP '^[0-9]{10,}$' OR yanit LIKE '00%' THEN 1 ELSE 0 END) as successful_sms,
                      DATE(tarih) as sms_date
                    FROM 
                      sms_log
                    WHERE 
                      tarih >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    GROUP BY 
                      DATE(tarih)
                    ORDER BY 
                      sms_date DESC";
                      
    $stmt = $conn->prepare($stats_query);
    $stmt->execute();
    $sms_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error_message = "SMS istatistikleri alınırken hata oluştu: " . $e->getMessage();
    $sms_stats = [];
}

// Son gönderilen SMS'ler
try {
    $recent_sms_query = "SELECT 
                            sl.id, sl.telefon, LEFT(sl.mesaj, 50) as mesaj_preview, 
                            sl.yanit, sl.tarih
                         FROM 
                            sms_log sl
                         ORDER BY 
                            sl.tarih DESC
                         LIMIT 10";
                         
    $stmt = $conn->prepare($recent_sms_query);
    $stmt->execute();
    $recent_sms = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Telefon numaralarına göre müşteri adlarını eşleştir
    if (!empty($recent_sms)) {
        $customer_query = "SELECT telefon, CONCAT(ad, ' ', soyad) as tam_ad FROM musteriler";
        $stmt = $conn->prepare($customer_query);
        $stmt->execute();
        $customers = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        foreach ($recent_sms as &$sms) {
            $sms['musteri_adi'] = 'Bilinmeyen';
            $formatted_phone = preg_replace('/[^0-9]/', '', $sms['telefon']);
            
            foreach ($customers as $phone => $name) {
                $formatted_customer_phone = preg_replace('/[^0-9]/', '', $phone);
                if (!empty($formatted_customer_phone) && !empty($formatted_phone) &&
                    (strpos($formatted_phone, $formatted_customer_phone) !== false || 
                     strpos($formatted_customer_phone, $formatted_phone) !== false)) {
                    $sms['musteri_adi'] = $name;
                    break;
                }
            }
        }
    }
} catch (Exception $e) {
    $error_message = "Son SMS'ler alınırken hata oluştu: " . $e->getMessage();
    $recent_sms = [];
}

// Sayfa yüklenme süresini hesapla
$end_time = microtime(true);
$execution_time = round($end_time - $start_time, 3);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NetGSM Yönetimi - İnciPOS</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold">NetGSM SMS Yönetimi</h1>
                <p class="text-gray-600">SMS gönderimi ve raporlama sayfası</p>
            </div>
            <a href="../../admin_dashboard.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                Geri Dön
            </a>
        </div>
        
        <?php if ($success_message): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                <p><?php echo htmlspecialchars($success_message); ?></p>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                <p><?php echo htmlspecialchars($error_message); ?></p>
            </div>
        <?php endif; ?>
        
        <!-- Bakiye Bilgisi -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <h2 class="text-lg font-semibold mb-2">NetGSM Bakiye Bilgisi</h2>
    <?php if (isset($balance['success']) && $balance['success']): ?>
        <div class="flex flex-col md:flex-row md:space-x-8">
            <?php if (isset($balance['details']) && is_array($balance['details'])): ?>
                <!-- stip=1 formatı için detaylı bakiye bilgisi -->
                <?php foreach ($balance['details'] as $item): ?>
                    <div class="mb-4 md:mb-0">
                        <span class="text-gray-600"><?php echo htmlspecialchars($item['name']); ?>:</span>
                        <span class="font-bold"><?php echo htmlspecialchars(number_format((float)$item['amount'], 0, ',', '.')); ?> adet</span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <!-- stip=2 formatı için basit bakiye bilgisi -->
                <div class="mb-4 md:mb-0">
                    <span class="text-gray-600">Kredi Bakiyesi:</span>
                    <span class="font-bold"><?php echo isset($balance['credit']) ? htmlspecialchars(number_format((float)$balance['credit'], 2, ',', '.')) : 'Bilinmiyor'; ?> ₺</span>
                </div>
                <div class="mb-4 md:mb-0">
                    <span class="text-gray-600">SMS Birim Adedi:</span>
                    <span class="font-bold"><?php echo isset($balance['unit_credit']) ? htmlspecialchars(number_format((float)$balance['unit_credit'], 0, ',', '.')) : 'Bilinmiyor'; ?> adet</span>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <p class="text-red-600">Bakiye bilgisi alınamadı: <?php echo isset($balance['message']) ? htmlspecialchars($balance['message']) : 'Bilinmeyen hata'; ?></p>
        <p class="text-gray-600 mt-2">Raw response: <?php echo isset($balance['raw_response']) ? htmlspecialchars($balance['raw_response']) : 'Yok'; ?></p>
    <?php endif; ?>
</div>

        <!-- SMS Gönderme Formları -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <!-- Tekli SMS Gönderimi -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold mb-4">Tekli SMS Gönderimi</h2>
                <form method="POST" action="">
                    <div class="mb-4">
                        <label for="phone_number" class="block text-sm font-medium text-gray-700 mb-1">
                            Telefon Numarası
                        </label>
                        <input type="text" id="phone_number" name="phone_number" placeholder="05XX XXX XX XX" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div class="mb-4">
                        <label for="message" class="block text-sm font-medium text-gray-700 mb-1">
                            Mesaj <span class="text-sm text-gray-500">(maks. 160 karakter)</span>
                        </label>
                        <textarea id="message" name="message" rows="4" maxlength="160" 
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"></textarea>
                        <div class="text-right text-xs text-gray-500">
                            <span id="charCount">0</span>/160 karakter
                        </div>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" name="send_single_sms" 
                                class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                            SMS Gönder
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Toplu SMS Gönderimi -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold mb-4">Toplu SMS Gönderimi</h2>
                <form method="POST" action="">
                    <div class="mb-4">
                        <label for="customer_group" class="block text-sm font-medium text-gray-700 mb-1">
                            Müşteri Grubu
                        </label>
                        <select id="customer_group" name="customer_group" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <option value="all">Tüm Müşteriler</option>
                            <option value="gold">Gold Müşteriler</option>
                            <option value="platinum">Platinum Müşteriler</option>
                            <option value="new">Yeni Müşteriler (Son 30 gün)</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label for="bulk_message" class="block text-sm font-medium text-gray-700 mb-1">
                            Mesaj <span class="text-sm text-gray-500">(maks. 160 karakter)</span>
                        </label>
                        <textarea id="bulk_message" name="bulk_message" rows="4" maxlength="160" 
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"></textarea>
                        <div class="text-right text-xs text-gray-500">
                            <span id="bulkCharCount">0</span>/160 karakter
                        </div>
                        <p class="text-sm text-gray-500 mt-1">
                            İsim için {name} yazabilirsiniz. Örn: Sayın {name}, indirim kampanyamız...
                        </p>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" name="send_bulk_sms" 
                                class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                            Toplu SMS Gönder
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- SMS İstatistikleri ve Son Gönderimler -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <!-- SMS İstatistikleri -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold mb-4">SMS İstatistikleri (Son 30 Gün)</h2>
                <canvas id="smsChart" height="300"></canvas>
            </div>
            
            <!-- Son SMS Gönderileri -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold mb-4">Son SMS Gönderileri</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Telefon
                                </th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Müşteri
                                </th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Mesaj
                                </th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Tarih
                                </th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Durum
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($recent_sms)): ?>
                                <tr>
                                    <td colspan="5" class="px-4 py-2 text-center text-sm text-gray-500">
                                        Henüz SMS gönderimi yapılmamış.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_sms as $sms): ?>
                                    <tr>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm">
                                            <?php echo htmlspecialchars($sms['telefon']); ?>
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm">
                                            <?php echo htmlspecialchars($sms['musteri_adi'] ?: 'Bilinmeyen'); ?>
                                        </td>
                                        <td class="px-4 py-2 text-sm">
                                            <?php echo htmlspecialchars($sms['mesaj_preview']); ?>
                                            <?php if (strlen($sms['mesaj_preview']) >= 50): ?>
                                                ...
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm">
                                            <?php echo date('d.m.Y H:i', strtotime($sms['tarih'])); ?>
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm">
                                            <?php 
                                                if (preg_match('/^[0-9]{10,}$/', $sms['yanit']) || strpos($sms['yanit'], '00') === 0) {
                                                    echo '<span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">Başarılı</span>';
                                                } else {
                                                    echo '<span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">Başarısız</span>';
                                                }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Şablon Mesajları -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4">Şablon Mesajlar</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="border p-4 rounded">
                    <h3 class="font-medium mb-2">Kampanya Bilgilendirme</h3>
                    <p class="text-sm text-gray-600 mb-3">
                        Değerli müşterimiz, [KAMPANYA ADI] kapsamında %[ORAN] indirim [TARİH] tarihine kadar geçerlidir. İnci Kırtasiye
                    </p>
                    <button type="button" class="use-template text-blue-600 hover:text-blue-800 text-sm" 
                            data-template="Değerli müşterimiz, [KAMPANYA ADI] kapsamında %[ORAN] indirim [TARİH] tarihine kadar geçerlidir. İnci Kırtasiye">
                        Şablonu Kullan
                    </button>
                </div>
                
                <div class="border p-4 rounded">
                    <h3 class="font-medium mb-2">Doğum Günü Tebrik</h3>
                    <p class="text-sm text-gray-600 mb-3">
                        Sevgili {name}, doğum gününüzü en içten dileklerimizle kutlarız. Bu özel gününüzde mağazamızda %10 indirim sizleri bekliyor. İnci Kırtasiye
                    </p>
                    <button type="button" class="use-template text-blue-600 hover:text-blue-800 text-sm" 
                            data-template="Sevgili {name}, doğum gününüzü en içten dileklerimizle kutlarız. Bu özel gününüzde mağazamızda %10 indirim sizleri bekliyor. İnci Kırtasiye">
                        Şablonu Kullan
                    </button>
                </div>
                
                <div class="border p-4 rounded">
                    <h3 class="font-medium mb-2">Sipariş Bilgilendirme</h3>
                    <p class="text-sm text-gray-600 mb-3">
                        Sayın {name}, siparişiniz hazırlandı. Mağazamızdan teslim alabilirsiniz. Siparişno: [SİPARİŞNO]. İnci Kırtasiye
                    </p>
                    <button type="button" class="use-template text-blue-600 hover:text-blue-800 text-sm" 
                            data-template="Sayın {name}, siparişiniz hazırlandı. Mağazamızdan teslim alabilirsiniz. Siparişno: [SİPARİŞNO]. İnci Kırtasiye">
                        Şablonu Kullan
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Geliştirici bilgisi ve süre -->
        <div class="text-xs text-gray-500 text-right mt-4">
            Sayfa oluşturma süresi: <?php echo $execution_time; ?> sn.
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Karakter sayacı
        const messageInput = document.getElementById('message');
        const charCount = document.getElementById('charCount');
        
        if (messageInput && charCount) {
            messageInput.addEventListener('input', function() {
                charCount.textContent = this.value.length;
            });
        }
        
        const bulkMessageInput = document.getElementById('bulk_message');
        const bulkCharCount = document.getElementById('bulkCharCount');
        
        if (bulkMessageInput && bulkCharCount) {
            bulkMessageInput.addEventListener('input', function() {
                bulkCharCount.textContent = this.value.length;
            });
        }
        
        // Şablon kullanımı
        const templateButtons = document.querySelectorAll('.use-template');
        
        templateButtons.forEach(button => {
            button.addEventListener('click', function() {
                const template = this.getAttribute('data-template');
                
                // Hangi form alanına ekleneceğini belirle (ilk açık olan forma)
                if (document.getElementById('message').value === '') {
                    document.getElementById('message').value = template;
                    document.getElementById('charCount').textContent = template.length;
                } else {
                    document.getElementById('bulk_message').value = template;
                    document.getElementById('bulkCharCount').textContent = template.length;
                }
            });
        });
        
        // SMS grafiği
        const ctx = document.getElementById('smsChart');
        
        if (ctx) {
            try {
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: [
                            <?php 
                            if (!empty($sms_stats)) {
                                foreach (array_reverse($sms_stats) as $stat) {
                                    echo "'" . date('d.m.Y', strtotime($stat['sms_date'])) . "',";
                                }
                            }
                            ?>
                        ],
                        datasets: [
                            {
                                label: 'Başarılı SMS',
                                data: [
                                    <?php 
                                    if (!empty($sms_stats)) {
                                        foreach (array_reverse($sms_stats) as $stat) {
                                            echo (int)$stat['successful_sms'] . ",";
                                        }
                                    }
                                    ?>
                                ],
                                backgroundColor: 'rgba(34, 197, 94, 0.2)',
                                borderColor: 'rgba(34, 197, 94, 1)',
                                borderWidth: 1
                            },
                            {
                                label: 'Toplam SMS',
                                data: [
                                    <?php 
                                    if (!empty($sms_stats)) {
                                        foreach (array_reverse($sms_stats) as $stat) {
                                            echo (int)$stat['total_sms'] . ",";
                                        }
                                    }
                                    ?>
                                ],
                                backgroundColor: 'rgba(59, 130, 246, 0.2)',
                                borderColor: 'rgba(59, 130, 246, 1)',
                                borderWidth: 1
                            }
                        ]
                    },
                    options: {
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    precision: 0
                                }
                            }
                        }
                    }
                });
            } catch (e) {
                console.error("Grafik oluşturma hatası:", e);
                document.getElementById('smsChart').parentNode.innerHTML = '<p class="text-red-500">Grafik yüklenirken bir hata oluştu.</p>';
            }
        }
    });
    </script>
</body>
</html>