<?php
// Hata raporlamasını etkinleştir
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Sayfa yüklenme süresini hesapla
$start_time = microtime(true);

// Header'ı dahil et
include 'header.php';
require_once 'db_connection.php';

// Yetki kontrolü
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: index.php");
    exit;
}

// Fatura ID parametresi gerekli
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">';
    echo '<p><strong>Hata:</strong> Geçersiz fatura ID.</p>';
    echo '</div>';
    echo '<div class="mb-6">';
    echo '<a href="sales_invoices.php" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">';
    echo '<i class="fas fa-arrow-left mr-2"></i>Faturalara Dön';
    echo '</a>';
    echo '</div>';
    include 'footer.php';
    exit;
}

$fatura_id = intval($_GET['id']);

// Orijinal faturayı al
$invoice_query = "
    SELECT 
        sf.*,
        m.ad as magaza_adi,
        p.ad as personel_adi,
        mus.id as musteri_id,
        mus.ad as musteri_adi,
        mus.soyad as musteri_soyad
    FROM 
        satis_faturalari sf
        LEFT JOIN magazalar m ON sf.magaza = m.id
        LEFT JOIN personel p ON sf.personel = p.id
        LEFT JOIN musteriler mus ON sf.musteri_id = mus.id
    WHERE 
        sf.id = ? AND sf.islem_turu = 'satis'
";

$stmt = $conn->prepare($invoice_query);
$stmt->execute([$fatura_id]);
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$invoice) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">';
    echo '<p><strong>Hata:</strong> Belirtilen fatura bulunamadı veya bu fatura bir satış faturası değil.</p>';
    echo '</div>';
    echo '<div class="mb-6">';
    echo '<a href="sales_invoices.php" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">';
    echo '<i class="fas fa-arrow-left mr-2"></i>Faturalara Dön';
    echo '</a>';
    echo '</div>';
    include 'footer.php';
    exit;
}

// Fatura detaylarını al
$details_query = "
    SELECT 
        sfd.*,
        us.ad as urun_adi,
        us.barkod
    FROM 
        satis_fatura_detay sfd
        LEFT JOIN urun_stok us ON sfd.urun_id = us.id
    WHERE 
        sfd.fatura_id = ?
";

$stmt = $conn->prepare($details_query);
$stmt->execute([$fatura_id]);
$details = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Önceki iade miktarlarını kontrol et
$previous_returns_query = "
    SELECT 
        sfd.urun_id, 
        SUM(sfd.miktar) as toplam_iade
    FROM 
        satis_fatura_detay sfd
        JOIN satis_faturalari sf ON sfd.fatura_id = sf.id
    WHERE 
        sf.islem_turu = 'iade' AND
        sf.iliskili_fatura_id = ?
    GROUP BY 
        sfd.urun_id
";

$stmt = $conn->prepare($previous_returns_query);
$stmt->execute([$fatura_id]);
$previous_returns = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $previous_returns[$row['urun_id']] = $row['toplam_iade'];
}

// İade faturası oluştur
if (isset($_POST['create_return'])) {
    try {
        // PDO hata modunu ayarla
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Transaction başlat
        $conn->beginTransaction();
        
        // İade edilen ürünler
        $urun_ids = isset($_POST['urun_id']) ? $_POST['urun_id'] : [];
        $miktarlar = isset($_POST['iade_miktar']) ? $_POST['iade_miktar'] : [];
        $aciklama = isset($_POST['aciklama']) ? $_POST['aciklama'] : "İade işlemi: " . $invoice['fatura_seri'] . $invoice['fatura_no'];
        
        // Orijinal fatura bilgileri
        $fatura_seri = "I" . $invoice['fatura_seri']; // İade faturası serisi I ile başlar
        $magaza_id = $invoice['magaza'];
        $personel_id = $invoice['personel'];
        $musteri_id = $invoice['musteri_id'];
        $odeme_turu = $invoice['odeme_turu'];
        $kredi_karti_banka = $invoice['kredi_karti_banka'];
        
        // İade fatura numarası oluştur
        $return_no_query = "
            SELECT MAX(CAST(fatura_no AS UNSIGNED)) as max_no
            FROM satis_faturalari
            WHERE fatura_seri = ?
        ";
        $stmt = $conn->prepare($return_no_query);
        $stmt->execute([$fatura_seri]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $new_return_no = 1;
        if ($result && $result['max_no']) {
            $new_return_no = $result['max_no'] + 1;
        }
        
        $fatura_no = str_pad($new_return_no, 6, '0', STR_PAD_LEFT);
        
        // Toplamları hesapla
        $toplam_tutar = 0;
        $kdv_tutari = 0;
        $indirim_tutari = 0;
        
        // İade edilecek ürünlerin bilgilerini al
        $return_details = [];
        foreach ($urun_ids as $key => $urun_id) {
            $iade_miktar = intval($miktarlar[$key]);
            if ($iade_miktar <= 0) continue;
            
            // Orijinal ürün bilgisini bul
            $original_item = null;
            foreach ($details as $detail) {
                if ($detail['urun_id'] == $urun_id) {
                    $original_item = $detail;
                    break;
                }
            }
            
            if ($original_item) {
                $birim_fiyat = $original_item['birim_fiyat'];
                $kdv_orani = $original_item['kdv_orani'];
                $indirim_orani = $original_item['indirim_orani'] ?? 0;
                
                // Toplam tutarı hesapla
                $item_total = $birim_fiyat * $iade_miktar;
                $item_indirim = $item_total * ($indirim_orani / 100);
                $item_tutar_indirimli = $item_total - $item_indirim;
                $item_kdv = $item_tutar_indirimli * ($kdv_orani / 100);
                
                $toplam_tutar += $item_total;
                $indirim_tutari += $item_indirim;
                $kdv_tutari += $item_kdv;
                
                $return_details[] = [
                    'urun_id' => $urun_id,
                    'miktar' => $iade_miktar,
                    'birim_fiyat' => $birim_fiyat,
                    'kdv_orani' => $kdv_orani,
                    'indirim_orani' => $indirim_orani,
                    'toplam_tutar' => $item_total
                ];
            }
        }
        
        // Net tutarı hesapla
        $net_tutar = $toplam_tutar - $indirim_tutari + $kdv_tutari;
        
        // Eğer iade edilecek ürün yoksa
        if (empty($return_details)) {
            throw new Exception("En az bir ürün için iade miktarı girmelisiniz.");
        }
        
        // İade faturasını oluştur
        $insert_query = "
            INSERT INTO satis_faturalari (
                fatura_turu, magaza, fatura_seri, fatura_no, fatura_tarihi, 
                toplam_tutar, personel, kdv_tutari, indirim_tutari, net_tutar, 
                odeme_turu, islem_turu, aciklama, kredi_karti_banka, musteri_id, iliskili_fatura_id
            ) VALUES (
                'standart', ?, ?, ?, NOW(), 
                ?, ?, ?, ?, ?, 
                ?, 'iade', ?, ?, ?, ?
            )
        ";
        
        $stmt = $conn->prepare($insert_query);
        $stmt->execute([
            $magaza_id, $fatura_seri, $fatura_no,
            $toplam_tutar, $personel_id, $kdv_tutari, $indirim_tutari, $net_tutar,
            $odeme_turu, $aciklama, $kredi_karti_banka, $musteri_id, $fatura_id
        ]);
        
        $return_invoice_id = $conn->lastInsertId();
        
        // İade fatura detaylarını kaydet
        $detail_query = "
            INSERT INTO satis_fatura_detay (
                fatura_id, urun_id, miktar, birim_fiyat, 
                kdv_orani, indirim_orani, toplam_tutar
            ) VALUES (
                ?, ?, ?, ?,
                ?, ?, ?
            )
        ";
        
        $stmt = $conn->prepare($detail_query);
        
        foreach ($return_details as $item) {
            $stmt->execute([
                $return_invoice_id,
                $item['urun_id'],
                $item['miktar'],
                $item['birim_fiyat'],
                $item['kdv_orani'],
                $item['indirim_orani'],
                $item['toplam_tutar']
            ]);
            
            // Stok hareketleri tablosuna iade kaydı ekle
            $stock_query = "
                INSERT INTO stok_hareketleri (
                    urun_id, miktar, hareket_tipi, 
                    aciklama, belge_no, tarih, 
                    kullanici_id, magaza_id, satis_fiyati
                ) VALUES (
                    ?, ?, 'giris', 
                    ?, ?, NOW(), 
                    ?, ?, ?
                )
            ";
            
            $stmt_stock = $conn->prepare($stock_query);
            $stmt_stock->execute([
                $item['urun_id'],
                $item['miktar'],
                "İade işlemi: " . $invoice['fatura_seri'] . $invoice['fatura_no'],
                $fatura_seri . $fatura_no,
                $_SESSION['user_id'],
                $magaza_id,
                $item['birim_fiyat']
            ]);
            
            // Mağaza stoğunu güncelle
            $check_stock_query = "
                SELECT ms.id, ms.stok_miktari, us.barkod 
                FROM magaza_stok ms 
                JOIN urun_stok us ON ms.barkod = us.barkod 
                WHERE us.id = ? AND ms.magaza_id = ?
            ";
            
            $stmt_check = $conn->prepare($check_stock_query);
            $stmt_check->execute([$item['urun_id'], $magaza_id]);
            $stock_row = $stmt_check->fetch(PDO::FETCH_ASSOC);
            
            if ($stock_row) {
                // Stok güncelle
                $new_stock = $stock_row['stok_miktari'] + $item['miktar'];
                
                $update_stock_query = "
                    UPDATE magaza_stok 
                    SET stok_miktari = ?, son_guncelleme = NOW() 
                    WHERE id = ?
                ";
                
                $stmt_update = $conn->prepare($update_stock_query);
                $stmt_update->execute([$new_stock, $stock_row['id']]);
            } else {
                // Ürünün barkodunu al
                $get_product_query = "SELECT barkod, satis_fiyati FROM urun_stok WHERE id = ?";
                $stmt_product = $conn->prepare($get_product_query);
                $stmt_product->execute([$item['urun_id']]);
                $product = $stmt_product->fetch(PDO::FETCH_ASSOC);
                
                if ($product) {
                    // Yeni stok kaydı ekle
                    $insert_stock_query = "
                        INSERT INTO magaza_stok (
                            barkod, magaza_id, stok_miktari, satis_fiyati, son_guncelleme
                        ) VALUES (
                            ?, ?, ?, ?, NOW()
                        )
                    ";
                    
                    $stmt_insert = $conn->prepare($insert_stock_query);
                    $stmt_insert->execute([
                        $product['barkod'],
                        $magaza_id,
                        $item['miktar'],
                        $product['satis_fiyati']
                    ]);
                }
            }
        }
        
        // Müşteri puanı işlemleri - iade ediliyorsa puan da iade edilmeli
        if ($musteri_id) {
            // Kazanılan puanı bul
            $puan_query = "
                SELECT pk.* 
                FROM puan_kazanma pk
                WHERE pk.fatura_id = ?
            ";
            
            $stmt_puan = $conn->prepare($puan_query);
            $stmt_puan->execute([$fatura_id]);
            $puan_rows = $stmt_puan->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($puan_rows)) {
                foreach ($puan_rows as $puan_row) {
                    // Toplam faturadan iade yüzdesini hesapla
                    $original_total = $invoice['net_tutar'];
                    $return_total = $net_tutar;
                    $return_ratio = $return_total / $original_total;
                    
                    $iade_puan = $puan_row['kazanilan_puan'] * $return_ratio;
                    
                    // Puan iade kaydı
                    $puan_iade_query = "
                        INSERT INTO puan_harcama (
                            fatura_id, musteri_id, harcanan_puan, tarih
                        ) VALUES (
                            ?, ?, ?, NOW()
                        )
                    ";
                    
                    $stmt_puan_iade = $conn->prepare($puan_iade_query);
                    $stmt_puan_iade->execute([
                        $return_invoice_id,
                        $musteri_id,
                        $iade_puan
                    ]);
                    
                    // Müşteri puan bakiyesini güncelle
                    $update_puan_query = "
                        UPDATE musteri_puanlar 
                        SET puan_bakiye = puan_bakiye - ?
                        WHERE musteri_id = ?
                    ";
                    
                    $stmt_update_puan = $conn->prepare($update_puan_query);
                    $stmt_update_puan->execute([$iade_puan, $musteri_id]);
                }
            }
        }
        
        $conn->commit();
        
        // Başarılı mesajı
        $success_message = "İade faturası başarıyla oluşturuldu. Fatura No: " . $fatura_seri . $fatura_no;
        
        // Başarılı mesajı göster ve yönlendir
        echo '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">';
        echo '<p><strong>Başarılı:</strong> ' . $success_message . '</p>';
        echo '</div>';
        
        echo '<script>
            setTimeout(function() {
                window.location.href = "sales_invoices.php?success=1&message=' . urlencode($success_message) . '";
            }, 3000);
        </script>';
        
    } catch (Exception $e) {
        $conn->rollBack();
        $error_message = "İade faturası oluşturulurken bir hata oluştu: " . $e->getMessage();
        
        echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">';
        echo '<p><strong>Hata:</strong> ' . $error_message . '</p>';
        echo '<p><strong>Hata Kodu:</strong> ' . $e->getCode() . '</p>';
        echo '<p><strong>Hata Ayrıntıları:</strong></p>';
        echo '<pre>' . $e->getTraceAsString() . '</pre>';
        echo '</div>';
    }
}

// Ödeme türleri
$payment_methods = [
    'nakit' => 'Nakit',
    'kredi_karti' => 'Kredi Kartı',
    'havale' => 'Havale'
];

// İşlem türleri
$transaction_types = [
    'satis' => 'Satış',
    'iade' => 'İade'
];

// Sayfa yüklenme süresini hesapla
$end_time = microtime(true);
$execution_time = round($end_time - $start_time, 3);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>İade Faturası Oluştur</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">İade Faturası Oluştur</h1>
            <a href="sales_invoices.php" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                <i class="fas fa-arrow-left mr-2"></i>Faturaları Görüntüle
            </a>
        </div>
        
        <?php if (isset($error_message)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                <p><?php echo $error_message; ?></p>
            </div>
        <?php endif; ?>

        <!-- Orijinal Fatura Bilgileri -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4 pb-2 border-b">Orijinal Fatura Bilgileri</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <p class="text-sm font-medium text-gray-500 mb-1">Fatura No:</p>
                    <p class="font-semibold"><?php echo $invoice['fatura_seri'] . $invoice['fatura_no']; ?></p>
                </div>
                
                <div>
                    <p class="text-sm font-medium text-gray-500 mb-1">Tarih:</p>
                    <p><?php echo date('d.m.Y H:i', strtotime($invoice['fatura_tarihi'])); ?></p>
                </div>
                
                <div>
                    <p class="text-sm font-medium text-gray-500 mb-1">Toplam Tutar:</p>
                    <p class="font-semibold"><?php echo number_format($invoice['net_tutar'], 2, ',', '.'); ?> ₺</p>
                </div>
                
                <div>
                    <p class="text-sm font-medium text-gray-500 mb-1">Mağaza:</p>
                    <p><?php echo $invoice['magaza_adi']; ?></p>
                </div>
                
                <div>
                    <p class="text-sm font-medium text-gray-500 mb-1">Personel:</p>
                    <p><?php echo $invoice['personel_adi']; ?></p>
                </div>
                
                <div>
                    <p class="text-sm font-medium text-gray-500 mb-1">Ödeme Türü:</p>
                    <p><?php echo $payment_methods[$invoice['odeme_turu']] ?? $invoice['odeme_turu']; ?></p>
                </div>
                
                <?php if ($invoice['musteri_id']): ?>
                <div>
                    <p class="text-sm font-medium text-gray-500 mb-1">Müşteri:</p>
                    <p><?php echo $invoice['musteri_adi'] . ' ' . $invoice['musteri_soyad']; ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- İade Formu -->
        <form method="POST" action="" id="returnForm">
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-lg font-semibold mb-4 pb-2 border-b">İade Edilecek Ürünler</h2>
                
                <div class="mb-4 bg-yellow-50 border-l-4 border-yellow-500 text-yellow-700 p-4">
                    <p><i class="fas fa-info-circle mr-2"></i>İade etmek istediğiniz ürünlerin miktarlarını girin. Orijinal faturadaki miktarın üzerinde iade yapılamaz.</p>
                </div>
                
                <table class="min-w-full divide-y divide-gray-200 border">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ürün</th>
                            <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-24">Orijinal Miktar</th>
                            <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-24">Önceki İadeler</th>
                            <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-24">Kalan Miktar</th>
                            <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-24">İade Miktarı</th>
                            <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-36">Birim Fiyat</th>
                            <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-36">Toplam</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($details as $detail): ?>
                            <?php 
                            // Önceki iade miktarı
                            $previous_return = isset($previous_returns[$detail['urun_id']]) ? $previous_returns[$detail['urun_id']] : 0;
                            // Kalan miktar
                            $remaining_qty = $detail['miktar'] - $previous_return;
                            ?>
                            <tr>
                                <td class="px-4 py-4">
                                    <div class="text-sm font-medium text-gray-900"><?php echo $detail['urun_adi']; ?></div>
                                    <div class="text-xs text-gray-500"><?php echo $detail['barkod']; ?></div>
                                    <input type="hidden" name="urun_id[]" value="<?php echo $detail['urun_id']; ?>">
                                </td>
                                <td class="px-4 py-4 text-center text-sm">
                                    <?php echo $detail['miktar']; ?>
                                </td>
                                <td class="px-4 py-4 text-center text-sm">
                                    <?php echo $previous_return; ?>
                                </td>
                                <td class="px-4 py-4 text-center text-sm">
                                    <?php echo $remaining_qty; ?>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <input type="number" name="iade_miktar[]" 
                                           class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md text-center iade-miktar" 
                                           value="0" min="0" max="<?php echo $remaining_qty; ?>" 
                                           data-price="<?php echo $detail['birim_fiyat']; ?>"
                                           data-max="<?php echo $remaining_qty; ?>"
                                           <?php echo ($remaining_qty <= 0) ? 'disabled' : ''; ?>>
                                </td>
                                <td class="px-4 py-4 text-center text-sm">
                                    <?php echo number_format($detail['birim_fiyat'], 2, ',', '.'); ?> ₺
                                </td>
                                <td class="px-4 py-4 text-center text-sm item-total">
                                    0,00 ₺
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="mt-6 flex justify-end">
                    <div class="w-64">
                        <div class="flex justify-between font-medium text-gray-700 py-2 border-t">
                            <span>Toplam İade Tutarı:</span>
                            <span id="totalReturn">0,00 ₺</span>
                        </div>
                    </div>
                </div>
                
                <div class="mt-6">
                    <label for="aciklama" class="block text-sm font-medium text-gray-700 mb-1">İade Açıklaması (İsteğe Bağlı)</label>
                    <textarea name="aciklama" id="aciklama" rows="3" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md"
                              placeholder="İade sebebi veya diğer notlar..."><?php echo "İade işlemi: " . $invoice['fatura_seri'] . $invoice['fatura_no']; ?></textarea>
                </div>
            </div>
            
            <div class="flex justify-between items-center">
                <a href="sales_invoices.php" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                    İptal
                </a>
                <button type="submit" name="create_return" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">
                    <i class="fas fa-undo mr-2"></i>İade Faturası Oluştur
                </button>
            </div>
        </form>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // İade miktarları değiştiğinde toplamları güncelle
            const iadeMiktarInputs = document.querySelectorAll('.iade-miktar');
            iadeMiktarInputs.forEach(input => {
                input.addEventListener('input', function() {
                    updateTotals();
                    
                    // Maksimum değer kontrolü
                    const max = parseInt(this.getAttribute('data-max'));
                    if (parseInt(this.value) > max) {
                        this.value = max;
                    }
                });
            });