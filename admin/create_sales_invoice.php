<?php
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

// Ödeme türleri
$payment_methods = [
    'nakit' => 'Nakit',
    'kredi_karti' => 'Kredi Kartı',
    'havale' => 'Havale'
];

// Bankalar
$banks = [
    'Ziraat',
    'İş Bankası',
    'Garanti',
    'Yapı Kredi',
    'Akbank',
    'Vakıfbank',
    'QNB',
    'Halkbank',
    'Denizbank',
    'TEB',
    'Şekerbank',
    'ING',
    'HSBC'
];

// Mağazaları çek
$magazalar_query = "SELECT * FROM magazalar ORDER BY ad";
$magazalar = $conn->query($magazalar_query)->fetchAll(PDO::FETCH_ASSOC);

// Personelleri çek
$personeller_query = "SELECT * FROM personel WHERE durum = 'aktif' ORDER BY ad";
$personeller = $conn->query($personeller_query)->fetchAll(PDO::FETCH_ASSOC);

// Müşterileri çek
$musteriler_query = "SELECT * FROM musteriler WHERE durum = 'aktif' ORDER BY ad";
$musteriler = $conn->query($musteriler_query)->fetchAll(PDO::FETCH_ASSOC);

// Ürünleri getir (ilk 20 aktif ürün)
$products_query = "
    SELECT 
        id, 
        kod, 
        barkod, 
        ad, 
        satis_fiyati, 
        indirimli_fiyat, 
        kdv_orani,
        COALESCE((SELECT SUM(ds.stok_miktari) FROM depo_stok ds WHERE ds.urun_id = urun_stok.id), 0) +
        COALESCE((SELECT SUM(ms.stok_miktari) FROM magaza_stok ms WHERE ms.barkod = urun_stok.barkod), 0) as stok_miktari
    FROM 
        urun_stok 
    WHERE 
        durum = 'aktif'
    ORDER BY 
        id DESC
    LIMIT 20
";
$products = $conn->query($products_query)->fetchAll(PDO::FETCH_ASSOC);

// Fatura kaydet
if (isset($_POST['save_invoice'])) {
    try {
        $conn->beginTransaction();
        
        // Fatura başlığı
        $fatura_seri = $_POST['fatura_seri'];
        $fatura_no = $_POST['fatura_no'];
        $magaza_id = $_POST['magaza_id'];
        $personel_id = $_POST['personel_id'];
        $musteri_id = !empty($_POST['musteri_id']) ? $_POST['musteri_id'] : null;
        $fatura_tarihi = $_POST['fatura_tarihi'];
        $odeme_turu = $_POST['odeme_turu'];
        $aciklama = $_POST['aciklama'];
        $kredi_karti_banka = ($odeme_turu == 'kredi_karti' && isset($_POST['kredi_karti_banka'])) ? $_POST['kredi_karti_banka'] : null;
        
        // Fatura detayları
        $urunler = isset($_POST['urun_id']) ? $_POST['urun_id'] : [];
        $miktarlar = isset($_POST['miktar']) ? $_POST['miktar'] : [];
        $birim_fiyatlar = isset($_POST['birim_fiyat']) ? $_POST['birim_fiyat'] : [];
        $kdv_oranlari = isset($_POST['kdv_orani']) ? $_POST['kdv_orani'] : [];
        $indirim_oranlari = isset($_POST['indirim_orani']) ? $_POST['indirim_orani'] : [];
        $toplam_tutarlar = isset($_POST['toplam_tutar']) ? $_POST['toplam_tutar'] : [];
        
        // Genel toplamlar
        $toplam_tutar = 0;
        $kdv_tutari = 0;
        $indirim_tutari = 0;
        $net_tutar = 0;
        
        // Fatura toplamlarını hesapla
        for ($i = 0; $i < count($urunler); $i++) {
            if (empty($urunler[$i])) continue;
            
            $toplam_tutar += floatval($toplam_tutarlar[$i]);
            
            // KDV hesapla
            $kdv_orani = floatval($kdv_oranlari[$i]);
            $birim_fiyat = floatval($birim_fiyatlar[$i]);
            $miktar = intval($miktarlar[$i]);
            $indirim_orani = floatval($indirim_oranlari[$i]);
            
            // KDV ve indirim hesaplamaları
            $tutar_kdvsiz = $birim_fiyat * $miktar;
            $indirim_tutari_item = $tutar_kdvsiz * ($indirim_orani / 100);
            $indirim_tutari += $indirim_tutari_item;
            $tutar_indirimli = $tutar_kdvsiz - $indirim_tutari_item;
            $kdv_tutari_item = $tutar_indirimli * ($kdv_orani / 100);
            $kdv_tutari += $kdv_tutari_item;
        }
        
        $net_tutar = $toplam_tutar - $indirim_tutari + $kdv_tutari;
        
        // Fatura tablosuna kaydet
        $invoice_query = "
            INSERT INTO satis_faturalari (
                fatura_turu, magaza, fatura_seri, fatura_no, fatura_tarihi, 
                toplam_tutar, personel, kdv_tutari, indirim_tutari, net_tutar, 
                odeme_turu, islem_turu, aciklama, kredi_karti_banka, musteri_id
            ) VALUES (
                'standart', ?, ?, ?, ?, 
                ?, ?, ?, ?, ?, 
                ?, 'satis', ?, ?, ?
            )
        ";
        
        $stmt = $conn->prepare($invoice_query);
        $stmt->execute([
            $magaza_id, $fatura_seri, $fatura_no, $fatura_tarihi,
            $toplam_tutar, $personel_id, $kdv_tutari, $indirim_tutari, $net_tutar,
            $odeme_turu, $aciklama, $kredi_karti_banka, $musteri_id
        ]);
        
        $invoice_id = $conn->lastInsertId();
        
        // Fatura detaylarını kaydet
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
        
        for ($i = 0; $i < count($urunler); $i++) {
            if (empty($urunler[$i])) continue;
            
            $stmt->execute([
                $invoice_id,
                $urunler[$i],
                $miktarlar[$i],
                $birim_fiyatlar[$i],
                $kdv_oranlari[$i],
                $indirim_oranlari[$i],
                $toplam_tutarlar[$i]
            ]);
            
            // Stok hareketleri tablosuna kaydet
            $stock_query = "
                INSERT INTO stok_hareketleri (
                    urun_id, miktar, hareket_tipi, 
                    aciklama, belge_no, tarih, 
                    kullanici_id, magaza_id, satis_fiyati
                ) VALUES (
                    ?, ?, 'cikis', 
                    'Satış faturası', ?, ?, 
                    ?, ?, ?
                )
            ";
            
            $stmt_stock = $conn->prepare($stock_query);
            $stmt_stock->execute([
                $urunler[$i],
                $miktarlar[$i],
                $fatura_seri . $fatura_no,
                $fatura_tarihi,
                $_SESSION['user_id'],
                $magaza_id,
                $birim_fiyatlar[$i]
            ]);
            
            // Mağaza stoğunu güncelle
            $check_stock_query = "
                SELECT ms.id, ms.stok_miktari, us.barkod 
                FROM magaza_stok ms 
                JOIN urun_stok us ON ms.barkod = us.barkod 
                WHERE us.id = ? AND ms.magaza_id = ?
            ";
            
            $stmt_check = $conn->prepare($check_stock_query);
            $stmt_check->execute([$urunler[$i], $magaza_id]);
            $stock_row = $stmt_check->fetch(PDO::FETCH_ASSOC);
            
            if ($stock_row) {
                // Stok güncelle
                $new_stock = $stock_row['stok_miktari'] - $miktarlar[$i];
                $new_stock = max(0, $new_stock); // Negatif stok olmaması için
                
                $update_stock_query = "
                    UPDATE magaza_stok 
                    SET stok_miktari = ?, son_guncelleme = NOW() 
                    WHERE id = ?
                ";
                
                $stmt_update = $conn->prepare($update_stock_query);
                $stmt_update->execute([$new_stock, $stock_row['id']]);
            } else {
                // Ürünün barkodunu al
                $get_product_query = "SELECT barkod FROM urun_stok WHERE id = ?";
                $stmt_product = $conn->prepare($get_product_query);
                $stmt_product->execute([$urunler[$i]]);
                $product = $stmt_product->fetch(PDO::FETCH_ASSOC);
                
                if ($product) {
                    // Yeni stok kaydı ekle (negatif stok olarak)
                    $insert_stock_query = "
                        INSERT INTO magaza_stok (
                            barkod, magaza_id, stok_miktari, satis_fiyati, son_guncelleme
                        ) VALUES (
                            ?, ?, 0, ?, NOW()
                        )
                    ";
                    
                    $stmt_insert = $conn->prepare($insert_stock_query);
                    $stmt_insert->execute([
                        $product['barkod'],
                        $magaza_id,
                        $birim_fiyatlar[$i]
                    ]);
                }
            }
        }
        
        // Müşteri puanı işlemleri
        if ($musteri_id) {
            // Müşteri puan bilgilerini al
            $puan_query = "SELECT * FROM musteri_puanlar WHERE musteri_id = ?";
            $stmt_puan = $conn->prepare($puan_query);
            $stmt_puan->execute([$musteri_id]);
            $musteri_puan = $stmt_puan->fetch(PDO::FETCH_ASSOC);
            
            if ($musteri_puan) {
                // Kazanılan puanı hesapla
                $puan_oran = $musteri_puan['puan_oran'] ?: 1; // Varsayılan %1 puan
                $kazanilan_puan = $net_tutar * ($puan_oran / 100);
                
                // Puan kazanma kaydı
                $puan_kazanma_query = "
                    INSERT INTO puan_kazanma (
                        fatura_id, musteri_id, kazanilan_puan, odeme_tutari, tarih
                    ) VALUES (
                        ?, ?, ?, ?, NOW()
                    )
                ";
                
                $stmt_puan_kazanma = $conn->prepare($puan_kazanma_query);
                $stmt_puan_kazanma->execute([
                    $invoice_id,
                    $musteri_id,
                    $kazanilan_puan,
                    $net_tutar
                ]);
                
                // Müşteri puan bakiyesini güncelle
                $update_puan_query = "
                    UPDATE musteri_puanlar 
                    SET puan_bakiye = puan_bakiye + ?, son_alisveris_tarihi = NOW() 
                    WHERE musteri_id = ?
                ";
                
                $stmt_update_puan = $conn->prepare($update_puan_query);
                $stmt_update_puan->execute([$kazanilan_puan, $musteri_id]);
            }
        }
        
        $conn->commit();
        
        // Başarılı mesajı
        $success_message = "Fatura başarıyla kaydedildi. Fatura No: " . $fatura_seri . $fatura_no;
        
        // Fatura detay sayfasına yönlendir
        header("Location: sales_invoices.php?success=1&message=" . urlencode($success_message));
        exit;
        
    } catch (Exception $e) {
        $conn->rollBack();
        $error_message = "Fatura kaydedilirken bir hata oluştu: " . $e->getMessage();
    }
}

// Otomatik fatura numarası oluştur
$last_invoice_query = "
    SELECT fatura_seri, fatura_no 
    FROM satis_faturalari 
    ORDER BY id DESC 
    LIMIT 1
";
$last_invoice = $conn->query($last_invoice_query)->fetch(PDO::FETCH_ASSOC);

$new_invoice_serie = 'S';
$new_invoice_number = '000001';

if ($last_invoice) {
    $last_serie = $last_invoice['fatura_seri'];
    $last_number = (int)$last_invoice['fatura_no'];
    
    $new_invoice_serie = $last_serie;
    $new_invoice_number = str_pad($last_number + 1, 6, '0', STR_PAD_LEFT);
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Yeni Satış Faturası</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        .select2-container {
            width: 100% !important;
        }
        .select2-container--default .select2-selection--single {
            height: 38px;
            line-height: 38px;
            padding: 0 0.75rem;
            border-color: #e2e8f0;
            border-radius: 0.375rem;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 38px;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 38px;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">Yeni Satış Faturası Oluştur</h1>
            <a href="sales_invoices.php" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                <i class="fas fa-arrow-left mr-2"></i>Faturaları Görüntüle
            </a>
        </div>
        
        <?php if (isset($error_message)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                <p><?php echo $error_message; ?></p>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="invoiceForm">
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-lg font-semibold mb-4 pb-2 border-b">Fatura Bilgileri</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div>
                        <label for="fatura_seri" class="block text-sm font-medium text-gray-700 mb-1">Fatura Seri</label>
                        <input type="text" name="fatura_seri" id="fatura_seri" value="<?php echo $new_invoice_serie; ?>" 
                               class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md" 
                               required maxlength="5">
                    </div>
                    
                    <div>
                        <label for="fatura_no" class="block text-sm font-medium text-gray-700 mb-1">Fatura No</label>
                        <input type="text" name="fatura_no" id="fatura_no" value="<?php echo $new_invoice_number; ?>" 
                               class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md" 
                               required maxlength="10">
                    </div>
                    
                    <div>
                        <label for="fatura_tarihi" class="block text-sm font-medium text-gray-700 mb-1">Fatura Tarihi</label>
                        <input type="text" name="fatura_tarihi" id="fatura_tarihi" value="<?php echo date('Y-m-d H:i:s'); ?>" 
                               class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md datepicker" 
                               required>
                    </div>
                    
                    <div>
                        <label for="magaza_id" class="block text-sm font-medium text-gray-700 mb-1">Mağaza</label>
                        <select name="magaza_id" id="magaza_id" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md" required>
                            <?php foreach ($magazalar as $magaza): ?>
                                <option value="<?php echo $magaza['id']; ?>"><?php echo htmlspecialchars($magaza['ad']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="personel_id" class="block text-sm font-medium text-gray-700 mb-1">Personel</label>
                        <select name="personel_id" id="personel_id" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md" required>
                            <?php foreach ($personeller as $personel): ?>
                                <option value="<?php echo $personel['id']; ?>"><?php echo htmlspecialchars($personel['ad']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="odeme_turu" class="block text-sm font-medium text-gray-700 mb-1">Ödeme Türü</label>
                        <select name="odeme_turu" id="odeme_turu" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md" required>
                            <?php foreach ($payment_methods as $key => $value): ?>
                                <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div id="banka_container" class="hidden">
                        <label for="kredi_karti_banka" class="block text-sm font-medium text-gray-700 mb-1">Banka</label>
                        <select name="kredi_karti_banka" id="kredi_karti_banka" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                            <?php foreach ($banks as $bank): ?>
                                <option value="<?php echo $bank; ?>"><?php echo $bank; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="musteri_id" class="block text-sm font-medium text-gray-700 mb-1">Müşteri</label>
                        <select name="musteri_id" id="musteri_id" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                            <option value="">Müşteri Seçiniz</option>
                            <?php foreach ($musteriler as $musteri): ?>
                                <option value="<?php echo $musteri['id']; ?>"><?php echo htmlspecialchars($musteri['ad'] . ' ' . $musteri['soyad']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="mt-4">
                    <label for="aciklama" class="block text-sm font-medium text-gray-700 mb-1">Açıklama</label>
                    <textarea name="aciklama" id="aciklama" rows="3" 
                              class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md"></textarea>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-lg font-semibold mb-4 pb-2 border-b">Ürün Ekle</h2>
                
                <div class="mb-6">
                    <label for="barkodSearch" class="block text-sm font-medium text-gray-700 mb-1">Barkod / Ürün Adı ile Ara</label>
                    <div class="flex">
                        <input type="text" id="barkodSearch" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-l-md" 
                               placeholder="Barkod veya ürün adını yazın...">
                        <button type="button" id="searchProductBtn" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-r-md">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                
                <div id="searchResults" class="mb-4 max-h-60 overflow-y-auto hidden"></div>
                
                <table class="min-w-full divide-y divide-gray-200 border">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ürün</th>
                            <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-20">Miktar</th>
                            <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-32">Birim Fiyat</th>
                            <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-24">KDV (%)</th>
                            <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-24">İndirim (%)</th>
                            <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-32">Toplam</th>
                            <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-20">İşlem</th>
                        </tr>
                    </thead>
                    <tbody id="invoiceItems" class="bg-white divide-y divide-gray-200">
                        <tr class="empty-row">
                            <td colspan="7" class="px-4 py-4 text-center text-sm text-gray-500">
                                Henüz ürün eklenmedi. Barkod veya ürün adı ile arama yaparak ürün ekleyebilirsiniz.
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <div class="mt-6 flex justify-end">
                    <div class="w-64">
                        <div class="flex justify-between font-medium text-gray-700 py-2 border-t">
                            <span>Ara Toplam:</span>
                            <span id="subtotal">0,00 ₺</span>
                        </div>
                        <div class="flex justify-between font-medium text-gray-700 py-2 border-t">
                            <span>KDV Tutarı:</span>
                            <span id="tax">0,00 ₺</span>
                        </div>
                        <div class="flex justify-between font-medium text-gray-700 py-2 border-t">
                            <span>İndirim Tutarı:</span>
                            <span id="discount">0,00 ₺</span>
                        </div>
                        <div class="flex justify-between font-bold text-lg text-gray-900 py-2 border-t">
                            <span>Genel Toplam:</span>
                            <span id="total">0,00 ₺</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="flex justify-between items-center">
                <a href="sales_invoices.php" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                    İptal
                </a>
                <button type="submit" name="save_invoice" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    <i class="fas fa-save mr-2"></i>Faturayı Kaydet
                </button>
            </div>
        </form>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/tr.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Select2 başlat
            $('select').select2();
            
            // Flatpickr başlat
            $(".datepicker").flatpickr({
                enableTime: true,
                dateFormat: "Y-m-d H:i:S",
                time_24hr: true,
                locale: "tr",
                defaultDate: new Date()
            });
            
            // Ödeme türü değiştiğinde banka alanını göster/gizle
            $("#odeme_turu").change(function() {
                if ($(this).val() === 'kredi_karti') {
                    $("#banka_container").removeClass('hidden');
                } else {
                    $("#banka_container").addClass('hidden');
                }
            });
            
            // Ürün arama
            let searchTimeout;
            $("#barkodSearch").on('keyup', function() {
                clearTimeout(searchTimeout);
                const searchTerm = $(this).val().trim();
                
                if (searchTerm.length < 3 && !isNumeric(searchTerm)) {
                    $("#searchResults").html('').addClass('hidden');
                    return;
                }
                
                searchTimeout = setTimeout(function() {
                    searchProducts(searchTerm);
                }, 500);
            });
            
            $("#searchProductBtn").click(function() {
                const searchTerm = $("#barkodSearch").val().trim();
                searchProducts(searchTerm);
            });
            
            // Ürün ekleme ve silme işlemleri
            $(document).on('click', '.addProductBtn', function() {
                const productData = $(this).data();
                addProductToInvoice(productData);
                $("#searchResults").html('').addClass('hidden');
                $("#barkodSearch").val('').focus();
            });
            
            $(document).on('click', '.removeProductBtn', function() {
                $(this).closest('tr').remove();
                updateInvoiceTotals();
                
                // Eğer ürün kalmadıysa, boş satırı göster
                if ($("#invoiceItems tr").length === 0) {
                    $("#invoiceItems").html('<tr class="empty-row"><td colspan="7" class="px-4 py-4 text-center text-sm text-gray-500">Henüz ürün eklenmedi. Barkod veya ürün adı ile arama yaparak ürün ekleyebilirsiniz.</td></tr>');
                }
            });
            
            // Miktar, birim fiyat veya indirim değiştiğinde toplamları güncelle
            $(document).on('input', '.item-quantity, .item-price, .item-discount', function() {
                const row = $(this).closest('tr');
                calculateRowTotal(row);
                updateInvoiceTotals();
            });
            
            // Form gönderilmeden önce kontrol
            $("#invoiceForm").submit(function(e) {
                // Ürün var mı kontrol et
                if ($("#invoiceItems tr").not('.empty-row').length === 0) {
                    e.preventDefault();
                    Swal.fire({
                        title: 'Uyarı',
                        text: 'Faturaya en az bir ürün eklemelisiniz.',
                        icon: 'warning',
                        confirmButtonText: 'Tamam'
                    });
                    return false;
                }
                
                // Diğer kontroller yapılabilir
                return true;
            });
            
            // Sayfa yüklendiğinde ödeme türü kontrolünü yap
            $("#odeme_turu").trigger('change');
        });
        
        // Sayısal değer mi kontrol et
        function isNumeric(value) {
            return /^\d+$/.test(value);
        }
        
        // Ürün ara
        function searchProducts(term) {
            if (!term) return;
            
            $.ajax({
                url: 'api/search_product.php',
                type: 'GET',
                data: { term: term },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.products.length > 0) {
                        let html = '<table class="min-w-full divide-y divide-gray-200 border">';
                        html += '<thead class="bg-gray-50"><tr>';
                        html += '<th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Kod/Barkod</th>';
                        html += '<th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Ürün Adı</th>';
                        html += '<th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Fiyat</th>';
                        html += '<th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Stok</th>';
                        html += '<th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">İşlem</th>';
                        html += '</tr></thead><tbody>';
                        
                        response.products.forEach(function(product) {
                            const price = product.indirimli_fiyat > 0 ? product.indirimli_fiyat : product.satis_fiyati;
                            
                            html += '<tr class="hover:bg-gray-50">';
                            html += `<td class="px-3 py-2 text-sm">${product.kod || product.barkod}</td>`;
                            html += `<td class="px-3 py-2 text-sm">${product.ad}</td>`;
                            html += `<td class="px-3 py-2 text-sm text-right">${formatPrice(price)} ₺</td>`;
                            html += `<td class="px-3 py-2 text-sm text-right">${product.stok_miktari}</td>`;
                            html += '<td class="px-3 py-2 text-sm text-center">';
                            html += `<button type="button" class="bg-blue-500 hover:bg-blue-700 text-white text-xs py-1 px-2 rounded addProductBtn" 
                                        data-id="${product.id}" 
                                        data-ad="${product.ad}" 
                                        data-barkod="${product.barkod}" 
                                        data-kod="${product.kod || product.barkod}" 
                                        data-fiyat="${price}" 
                                        data-kdv="${product.kdv_orani || 0}">
                                        Ekle
                                    </button>`;
                            html += '</td>';
                            html += '</tr>';
                        });
                        
                        html += '</tbody></table>';
                        $("#searchResults").html(html).removeClass('hidden');
                    } else {
                        $("#searchResults").html('<div class="p-4 text-center text-red-500">Ürün bulunamadı.</div>').removeClass('hidden');
                    }
                },
                error: function() {
                    $("#searchResults").html('<div class="p-4 text-center text-red-500">Arama sırasında bir hata oluştu.</div>').removeClass('hidden');
                }
            });
        }
        
        // Ürünü faturaya ekle
        function addProductToInvoice(product) {
            // Boş satırı kaldır
            $(".empty-row").remove();
            
            // Aynı ürün daha önce eklenmiş mi?
            const existingRow = $(`#invoiceItems tr[data-id="${product.id}"]`);
            
            if (existingRow.length > 0) {
                // Miktarı artır
                const qtyInput = existingRow.find('.item-quantity');
                const currentQty = parseInt(qtyInput.val());
                qtyInput.val(currentQty + 1);
                
                // Toplamı güncelle
                calculateRowTotal(existingRow);
            } else {
                // Yeni satır ekle
                const rowHtml = `
                    <tr data-id="${product.id}">
                        <td class="px-4 py-2">
                            <div class="text-sm font-medium">${product.ad}</div>
                            <div class="text-xs text-gray-500">${product.kod}</div>
                            <input type="hidden" name="urun_id[]" value="${product.id}">
                        </td>
                        <td class="px-4 py-2">
                            <input type="number" name="miktar[]" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md text-center item-quantity" value="1" min="1" required>
                        </td>
                        <td class="px-4 py-2">
                            <input type="number" name="birim_fiyat[]" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md text-right item-price" value="${product.fiyat}" step="0.01" min="0" required>
                        </td>
                        <td class="px-4 py-2">
                            <input type="number" name="kdv_orani[]" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md text-center item-tax" value="${product.kdv}" step="0.01" min="0" required>
                        </td>
                        <td class="px-4 py-2">
                            <input type="number" name="indirim_orani[]" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md text-center item-discount" value="0" step="0.01" min="0" max="100" required>
                        </td>
                        <td class="px-4 py-2">
                            <input type="number" name="toplam_tutar[]" class="shadow-sm bg-gray-50 focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md text-right item-total" value="${product.fiyat}" step="0.01" readonly>
                        </td>
                        <td class="px-4 py-2 text-center">
                            <button type="button" class="bg-red-500 hover:bg-red-700 text-white text-xs py-1 px-2 rounded removeProductBtn">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
                
                $("#invoiceItems").append(rowHtml);
                
                // Yeni satırın toplamını hesapla
                calculateRowTotal($(`#invoiceItems tr[data-id="${product.id}"]`));
            }
            
            // Genel toplamları güncelle
            updateInvoiceTotals();
        }
        
        // Satır toplamını hesapla
        function calculateRowTotal(row) {
            const quantity = parseFloat(row.find('.item-quantity').val()) || 0;
            const price = parseFloat(row.find('.item-price').val()) || 0;
            const discount = parseFloat(row.find('.item-discount').val()) || 0;
            
            // KDV dahil toplam hesapla
            const subtotal = quantity * price;
            const discountAmount = subtotal * (discount / 100);
            const total = subtotal - discountAmount;
            
            row.find('.item-total').val(total.toFixed(2));
        }
        
        // Fatura toplamlarını güncelle
        function updateInvoiceTotals() {
            let subtotal = 0;
            let taxTotal = 0;
            let discountTotal = 0;
            let grandTotal = 0;
            
            // Her satırın toplamını hesapla
            $("#invoiceItems tr").not('.empty-row').each(function() {
                const row = $(this);
                const quantity = parseFloat(row.find('.item-quantity').val()) || 0;
                const price = parseFloat(row.find('.item-price').val()) || 0;
                const tax = parseFloat(row.find('.item-tax').val()) || 0;
                const discount = parseFloat(row.find('.item-discount').val()) || 0;
                
                const rowSubtotal = quantity * price;
                const rowDiscountAmount = rowSubtotal * (discount / 100);
                const rowAfterDiscount = rowSubtotal - rowDiscountAmount;
                const rowTaxAmount = rowAfterDiscount * (tax / 100);
                
                subtotal += rowSubtotal;
                discountTotal += rowDiscountAmount;
                taxTotal += rowTaxAmount;
            });
            
            grandTotal = subtotal - discountTotal + taxTotal;
            
            // Toplamları görüntüle
            $("#subtotal").text(formatPrice(subtotal) + " ₺");
            $("#tax").text(formatPrice(taxTotal) + " ₺");
            $("#discount").text(formatPrice(discountTotal) + " ₺");
            $("#total").text(formatPrice(grandTotal) + " ₺");
        }
        
        // Fiyatı formatla
        function formatPrice(price) {
            return price.toFixed(2).replace('.', ',');
        }
    </script>
    
<?php
// Sayfa yüklenme süresini hesapla
$end_time = microtime(true);
$execution_time = round($end_time - $start_time, 3);

// Footer'ı dahil et
include 'footer.php';
?>
</body>
</html>