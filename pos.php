<?php
session_start();
require_once 'admin/db_connection.php';

// Kullanıcı giriş kontrolü
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: login.php");
    exit;
}

// Doğrulama kontrolü
if (!isset($_SESSION['verified']) || !$_SESSION['verified']) {
    header("Location: verify.php");
    exit;
}

// Mağaza seçilmemiş ise mağaza seçim sayfasına yönlendir
if (!isset($_SESSION['magaza_id'])) {
    header("Location: select_store.php");
    exit;
}

// Kullanıcı bilgilerini al
$currentUserId = $_SESSION['user_id'];
$currentMagazaId = $_SESSION['magaza_id'];
$currentUserYetki = $_SESSION['yetki'];

// Mağazaları getir - Yetkiye göre filtrele
if ($currentUserYetki == 'kasiyer') {
    // Kasiyer ise sadece atandığı mağazayı görsün (kendi mağazasını)
    $stmt = $conn->prepare("
        SELECT id, ad 
        FROM magazalar m
        WHERE id = ?
        ORDER BY ad
    ");
    $stmt->execute([$currentMagazaId]);
} else {
    // Müdür veya üst yetki ise tüm mağazaları görsün
    $stmt = $conn->query("SELECT id, ad FROM magazalar ORDER BY ad");
}
$magazalar = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Kasiyerleri getir - Yetkiye göre filtrele
if ($currentUserYetki == 'kasiyer') {
    // Kasiyer ise sadece kendi mağazasındaki aktif personeli görsün
    $stmt = $conn->prepare("
        SELECT id, ad FROM personel 
        WHERE magaza_id = ? AND durum = 'aktif'
        ORDER BY ad
    ");
    $stmt->execute([$currentMagazaId]);
} else {
    // Müdür veya üst yetki ise tüm aktif personeli görsün
    $stmt = $conn->prepare("
        SELECT id, ad FROM personel 
        WHERE durum = 'aktif'
        ORDER BY ad
    ");
    $stmt->execute();
}
$kasiyerler = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Satış Ekranı - POS</title>
    <link rel="stylesheet" href="admin/assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div style="padding-top:.2rem!important;" class="container mx-auto px-4 py-2">
        <div class="container mx-auto px-4 py-1">
    <!-- Mağaza bilgisi -->
    <div class="bg-white rounded-lg shadow-md p-2 mb-2">
        <div class="flex justify-between items-center">
            <div>
                <span class="text-gray-600">Mağaza:</span>
                <span class="font-bold"><?php echo htmlspecialchars($_SESSION['magaza_adi']); ?> Şube</span>
            </div>
            <?php if (in_array($_SESSION['yetki'], ['mudur', 'mudur_yardimcisi'])): ?>
                <a href="select_store.php" class="bg-blue-500 hover:bg-blue-700 text-white text-sm py-1 px-3 rounded">
                    <i class="fas fa-exchange-alt mr-1"></i> Mağaza Değiştir
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>
        <!-- POS HEADER -->
        <div class="bg-white rounded-lg shadow-md p-4 mb-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- İşlem Bilgileri -->
                <div class="md:col-span-1">
                    <div class="mb-2">
                        <label class="block text-sm font-medium text-gray-700">İşlem Türü</label>
                        <select id="islemTuru" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <option value="satis">Satış</option>
                            <option value="iade">İade</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="block text-sm font-medium text-gray-700">Kasiyer</label>
                        <select id="kasiyer" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <?php foreach ($kasiyerler as $kasiyer): ?>
                                <option value="<?= $kasiyer['id'] ?>"><?= htmlspecialchars($kasiyer['ad']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <!-- Müşteri Bilgileri -->
                <div class="md:col-span-1">
                    <div class="mb-2">
                        <label class="block text-sm font-medium text-gray-700">Müşteri</label>
                        <div class="relative">
                            <input type="text" id="musteriAra" placeholder="Müşteri ara..." class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <button id="btnMusteriSec" class="absolute right-2 top-1/2 transform -translate-y-1/2 bg-blue-500 text-white p-1 rounded">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                    <div id="seciliMusteri" class="hidden">
    <div class="bg-blue-50 p-2 rounded border border-blue-200">
        <div class="flex justify-between">
            <span id="musteriAdSoyad" class="font-medium"></span>
            <span id="musteriPuan" class="text-green-600 font-medium"></span>
        </div>
        <div class="text-sm text-gray-600" id="musteriTelefon"></div>
        <!-- Borç Bilgisi Eklendi -->
        <div class="mt-1 text-sm flex justify-between">
            <span>Toplam Borç:</span>
            <span id="musteriBorcDurumu" class="font-medium text-red-600">0,00 ₺</span>
        </div>
        <!-- Borç Detay Butonu Eklendi -->
        <div class="mt-1 text-right">
            <button id="btnMusteriBorcDetay" class="text-xs bg-blue-100 hover:bg-blue-200 text-blue-800 py-1 px-2 rounded">
                <i class="fas fa-list-ul mr-1"></i> Borç Detayı Gör
            </button>
        </div>
    </div>
</div>
                </div>
                
                <!-- Fiş Bilgileri -->
                <div class="md:col-span-1">
                    <div class="grid grid-cols-2 gap-2">
                        <div class="mb-2">
                            <label class="block text-sm font-medium text-gray-700">Fiş No</label>
                            <input type="text" id="fisNo" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" readonly>
                        </div>
                        <div class="mb-2">
                            <label class="block text-sm font-medium text-gray-700">Tarih</label>
                            <input type="text" id="tarih" value="<?= date('d.m.Y H:i') ?>" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" readonly>
                        </div>
                        <div class="mb-2">
                            <label class="block text-sm font-medium text-gray-700">Mağaza</label>
                            <?php if ($currentUserYetki == 'kasiyer'): ?>
                                <select id="magaza" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 bg-gray-100" disabled>
                                    <?php foreach ($magazalar as $magaza): ?>
                                        <option value="<?= $magaza['id'] ?>" <?= ($magaza['id'] == $currentMagazaId) ? 'selected' : '' ?>><?= htmlspecialchars($magaza['ad']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="hidden" name="magaza_id" value="<?= $currentMagazaId ?>">
                            <?php else: ?>
                                <select id="magaza" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                    <?php foreach ($magazalar as $magaza): ?>
                                        <option value="<?= $magaza['id'] ?>" <?= ($magaza['id'] == $currentMagazaId) ? 'selected' : '' ?>><?= htmlspecialchars($magaza['ad']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- POS MAIN CONTENT -->
        <div class="flex flex-col md:flex-row gap-4">
            <!-- Ürün Arama ve Sepet -->
            <div class="md:w-3/4">
                <div class="bg-white rounded-lg shadow-md p-4 mb-4">
                    <div class="flex flex-wrap gap-2 mb-4">
                        <div class="flex-grow">
                            <div class="relative">
                                <input type="text" id="barkodInput" placeholder="Barkod/Ürün kodu okutun veya arayın..." class="block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                <button id="btnUrunAra" class="absolute right-2 top-1/2 transform -translate-y-1/2 bg-blue-500 text-white p-1 rounded">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                        <button id="btnStokGor" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                            <i class="fas fa-boxes"></i> Stok Gör
                        </button>
                    </div>
                    
                    <!-- Ürün Kısayolları -->
					<div class="grid grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-2 mb-4" id="urunKisayolContainer">
						<!-- Kısayol butonları JavaScript ile dinamik olarak eklenecek -->
						<div class="urun-kisayol-placeholder text-center py-2">
							<i class="fas fa-sync fa-spin text-gray-400"></i>
							<div class="text-xs text-gray-500 mt-1">Yükleniyor...</div>
						</div>
					</div>
                    
                    <!-- Sepet Tablosu -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Ürün</th>
                                    <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Miktar</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Birim Fiyat</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">İndirim</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Toplam</th>
                                    <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">İşlem</th>
                                </tr>
                            </thead>
                            <tbody id="sepetListesi" class="bg-white divide-y divide-gray-200">
                                <!-- Sepet ürünleri JavaScript ile eklenecek -->
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Sepet Toplamları -->
                <div class="bg-white rounded-lg shadow-md p-4">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="text-center">
                            <div class="text-gray-500 text-sm">Toplam Ürün</div>
                            <div id="urunSayisi" class="text-xl font-bold">0</div>
                        </div>
                        <div class="text-center">
                            <div class="text-gray-500 text-sm">Ara Toplam</div>
                            <div id="araToplam" class="text-xl font-bold">0,00 ₺</div>
                        </div>
                        <div class="text-center">
                            <div class="text-gray-500 text-sm">Toplam İndirim</div>
                            <div id="toplamIndirim" class="text-xl font-bold text-red-600">0,00 ₺</div>
                        </div>
                        <div class="text-center">
                            <div class="text-gray-500 text-sm">Genel Toplam</div>
                            <div id="genelToplam" class="text-xl font-bold text-green-600">0,00 ₺</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- İşlem Butonları -->
            <div class="md:w-1/4">
                <div class="bg-white rounded-lg shadow-md p-4 mb-4">
                    <div class="grid grid-cols-2 gap-2">
                        <button id="btnFisIptal" class="w-full bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded flex flex-col items-center">
                            <i class="fas fa-times-circle text-xl"></i>
                            <span class="text-sm mt-1">Fiş İptal</span>
                        </button>
                        <button id="btnMusteriSecim" class="w-full bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded flex flex-col items-center">
                            <i class="fas fa-user text-xl"></i>
                            <span class="text-sm mt-1">Müşteri Seç</span>
                        </button>
                        <button id="btnIndirim" class="w-full bg-purple-500 hover:bg-purple-600 text-white font-bold py-2 px-4 rounded flex flex-col items-center">
                            <i class="fas fa-percent text-xl"></i>
                            <span class="text-sm mt-1">İndirim</span>
                        </button>
                        <button id="btnOdemeAl" class="w-full bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded flex flex-col items-center">
                            <i class="fas fa-cash-register text-xl"></i>
                            <span class="text-sm mt-1">Ödeme Al</span>
                        </button>
                        <button id="btnBeklet" class="w-full bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2 px-4 rounded flex flex-col items-center">
                            <i class="fas fa-pause-circle text-xl"></i>
                            <span class="text-sm mt-1">Beklet</span>
                        </button>
						<button id="btnBekleyenFisler" class="w-full bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2 px-4 rounded flex flex-col items-center">
							<i class="fas fa-clipboard-list text-xl"></i>
							<span class="text-sm mt-1">Bekleyen Fişler</span>
						</button>
                        <button id="btnAyarlar" class="w-full bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded flex flex-col items-center">
                            <i class="fas fa-cog text-xl"></i>
                            <span class="text-sm mt-1">Ayarlar</span>
                        </button>
						
						<button id="btnRaporlar" class="w-full bg-indigo-500 hover:bg-indigo-600 text-white font-bold py-2 px-4 rounded flex flex-col items-center">
							<i class="fas fa-chart-bar text-xl"></i>
							<span class="text-sm mt-1">Raporlar</span>
						</button>
                    </div>
                </div>
                
                <!-- Puan Kullanımı -->
                <div id="puanKullanim" class="bg-white rounded-lg shadow-md p-4 mb-4 hidden">
                    <h3 class="font-bold text-lg mb-2">Puan İşlemleri</h3>
                    <div class="mb-2">
                        <label class="block text-sm font-medium text-gray-700">Mevcut Puan</label>
                        <div id="mevcutPuan" class="py-2 px-3 bg-gray-100 rounded">0 Puan</div>
                    </div>
                    <div class="mb-2">
                        <label class="block text-sm font-medium text-gray-700">Kullanılacak Puan</label>
                        <input type="number" id="kullanilacakPuan" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <button id="btnPuanKullan" class="w-full bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded">
                        Puan Kullan
                    </button>
                </div>
                
                <!-- Kazanılacak Puan Bilgisi -->
                <div class="bg-white rounded-lg shadow-md p-4">
                    <h3 class="font-bold text-lg mb-2">Bu Alışverişten</h3>
                    <div class="flex justify-between">
                        <span class="text-gray-700">Kazanılacak Puan:</span>
                        <span id="kazanilacakPuan" class="font-bold text-green-600">0 Puan</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- MODALS -->
    
    <!-- Müşteri Seçim Modal -->
    <div id="musteriSecModal" class="fixed inset-0 bg-gray-600 bg-opacity-75 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl w-11/12 md:w-3/4 lg:w-1/2 max-h-screen overflow-y-auto">
            <div class="flex justify-between items-center p-4 border-b">
                <h3 class="text-lg font-bold">Müşteri Seç</h3>
                <button class="modal-close text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-4">
                <div class="mb-4">
                    <input type="text" id="musteriAraInput" placeholder="Müşteri adı veya telefon ile ara..." class="w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
    <tr>
        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Ad Soyad</th>
        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Telefon</th>
        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Puan</th>
        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Borç</th>
        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">İşlem</th>
    </tr>
</thead>
                        <tbody id="musteriListesi" class="bg-white divide-y divide-gray-200">
                            <!-- JavaScript ile doldurulacak -->
                        </tbody>
                    </table>
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button id="btnYeniMusteri" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded">
                        Yeni Müşteri
                    </button>
                    <button class="modal-close bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded">
                        Kapat
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Ödeme Modal -->
    <div id="odemeModal" class="fixed inset-0 bg-gray-600 bg-opacity-75 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl w-11/12 md:w-3/4 lg:w-1/2 max-h-screen overflow-y-auto">
            <div class="flex justify-between items-center p-4 border-b">
                <h3 class="text-lg font-bold">Ödeme</h3>
                <button class="modal-close text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-4">
                <div class="mb-4 bg-gray-100 p-4 rounded">
                    <div class="flex justify-between mb-2">
                        <span class="font-medium">Toplam Tutar:</span>
                        <span id="odemeToplam" class="font-bold text-xl">0,00 ₺</span>
                    </div>
                    <div class="flex justify-between mb-2">
                        <span class="font-medium">Kullanılan Puan:</span>
                        <span id="odemeKullanilanPuan" class="text-red-600">0 Puan</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="font-medium">Ödenecek Tutar:</span>
                        <span id="odemeOdenecek" class="font-bold text-green-600 text-xl">0,00 ₺</span>
                    </div>
                </div>
                
                <div class="mb-4">
                    <h4 class="font-bold mb-2">Ödeme Yöntemi</h4>
                    <div class="grid grid-cols-3 gap-2">
                        <button class="odeme-yontemi bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded" data-yontem="nakit">
                            <i class="fas fa-money-bill-wave mr-1"></i> Nakit
                        </button>
                        <button class="odeme-yontemi bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded" data-yontem="kredi_karti">
                            <i class="fas fa-credit-card mr-1"></i> Kredi Kartı
                        </button>
                        <button class="odeme-yontemi bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded" data-yontem="borc">
                            <i class="fas fa-bookmark mr-1"></i> Borç Ekle
                        </button>
                    </div>
                </div>
                
                <!-- Nakit ödeme alanı -->
                <div id="nakitOdeme" class="odeme-alani mb-4 hidden">
                    <div class="mb-2">
                        <label class="block text-sm font-medium text-gray-700">Alınan Tutar</label>
                        <input type="number" id="alinanTutar" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div class="mb-2">
                        <label class="block text-sm font-medium text-gray-700">Para Üstü</label>
                        <div id="paraUstu" class="py-2 px-3 bg-gray-100 rounded font-bold text-xl">0,00 ₺</div>
                    </div>
                </div>
                
                <!-- Kredi kartı ödeme alanı -->
                <div id="krediKartiOdeme" class="odeme-alani mb-4 hidden">
                    <div class="mb-2">
                        <label class="block text-sm font-medium text-gray-700">Banka</label>
                        <select id="banka" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <option value="Ziraat">Ziraat Bankası</option>
                            <option value="İş Bankası">İş Bankası</option>
                            <option value="Garanti">Garanti Bankası</option>
                            <option value="Yapı Kredi">Yapı Kredi</option>
                            <option value="Akbank">Akbank</option>
                            <!-- Diğer bankalar... -->
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="block text-sm font-medium text-gray-700">Taksit Sayısı</label>
                        <select id="taksitSayisi" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <option value="1">Tek Çekim</option>
                            <option value="3">3 Taksit</option>
                            <option value="6">6 Taksit</option>
                            <option value="9">9 Taksit</option>
                            <option value="12">12 Taksit</option>
                        </select>
                    </div>
                </div>
                
                <!-- Borç ekleme alanı -->
                <div id="borcEkle" class="odeme-alani mb-4 hidden">
                    <div class="mb-2">
                        <div class="text-red-600 font-bold mb-2">DİKKAT: Bu işlem müşteri hesabına borç olarak kaydedilecektir.</div>
                        <div id="borcMusteri" class="mb-2 py-2 px-3 bg-blue-50 rounded border border-blue-200">
                            Müşteri seçilmedi
                        </div>
                    </div>
                </div>
                
                <div class="mt-4 flex justify-end gap-2">
                    <button class="modal-close bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded">
                        İptal
                    </button>
                    <button id="btnOdemeTamamla" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded">
                        Ödemeyi Tamamla
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Stok Görüntüleme Modal -->
    <div id="stokModal" class="fixed inset-0 bg-gray-600 bg-opacity-75 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl w-11/12 md:w-3/4 lg:w-3/4 max-h-screen overflow-y-auto">
            <div class="flex justify-between items-center p-4 border-b">
                <h3 class="text-lg font-bold">Stok Listesi</h3>
                <button class="modal-close text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-4">
                <div class="mb-4">
                    <input type="text" id="stokAraInput" placeholder="Ürün adı, barkod veya kodu ile ara..." class="w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Barkod</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Ürün Adı</th>
                                <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Stok</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Fiyat</th>
                                <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">İşlem</th>
                            </tr>
                        </thead>
                        <tbody id="stokListesi" class="bg-white divide-y divide-gray-200">
                            <!-- JavaScript ile doldurulacak -->
                        </tbody>
                    </table>
                </div>
                <div class="mt-4 flex justify-end">
                    <button class="modal-close bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded">
                        Kapat
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Ayarlar Modal -->
<div id="ayarlarModal" class="fixed inset-0 bg-gray-600 bg-opacity-75 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl w-11/12 md:w-3/4 lg:w-2/3 max-h-screen overflow-y-auto">
        <div class="flex justify-between items-center p-4 border-b">
            <h3 class="text-lg font-bold">Ayarlar</h3>
            <button class="modal-close text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-4">
            <div class="mb-4">
                <h4 class="font-bold mb-4">Ürün Kısayolları</h4>
                <p class="text-sm text-gray-500 mb-4">Kısayollara eklemek için boş butonlara tıklayın, kaldırmak için X işaretini kullanın.</p>
                <div id="urunKisayollari" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-2">
                    <!-- JavaScript ile doldurulacak -->
                    <div class="text-center py-4">
                        <i class="fas fa-sync fa-spin text-gray-400"></i>
                        <div class="text-sm text-gray-500 mt-1">Yükleniyor...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Ürün Seçme Modal (Kısayollar için) -->
<div id="productSelectionModal" class="fixed inset-0 bg-gray-600 bg-opacity-75 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl w-11/12 md:w-3/4 lg:w-1/2 max-h-screen overflow-y-auto">
        <div class="flex justify-between items-center p-4 border-b">
            <h3 class="text-lg font-bold">Kısayol Ürünü Seç</h3>
            <button class="modal-close text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-4">
            <div class="mb-4">
                <input type="text" id="shortcutProductSearch" placeholder="Ürün adı, barkod veya kodu ile ara..." class="w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Barkod</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Ürün Adı</th>
                            <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Stok</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Fiyat</th>
                            <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">İşlem</th>
                        </tr>
                    </thead>
                    <tbody id="shortcutProductList">
                        <!-- JavaScript ile doldurulacak -->
                    </tbody>
                </table>
            </div>
            <div class="mt-4 flex justify-end">
                <button class="modal-close bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded">
                    Kapat
                </button>
            </div>
        </div>
    </div>
</div>
    
    <!-- Yeni Müşteri Modal -->
    <div id="yeniMusteriModal" class="fixed inset-0 bg-gray-600 bg-opacity-75 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl w-11/12 md:w-1/2 lg:w-1/3 max-h-screen overflow-y-auto">
            <div class="flex justify-between items-center p-4 border-b">
                <h3 class="text-lg font-bold">Yeni Müşteri Ekle</h3>
                <button class="modal-close text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-4">
                <form id="yeniMusteriForm">
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-gray-700">Ad *</label>
                        <input type="text" name="ad" required class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-gray-700">Soyad *</label>
                        <input type="text" name="soyad" required class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-gray-700">Telefon *</label>
                        <input type="text" name="telefon" required pattern="[0-9]{10,11}" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        <div class="text-xs text-gray-500 mt-1">10 veya 11 haneli telefon numarası yazınız (örn: 5321234567 veya 05321234567)</div>
                    </div>
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-gray-700">E-posta</label>
                        <input type="email" name="email" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-gray-700">Kart No (Opsiyonel)</label>
                        <input type="text" name="barkod" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div class="mb-3 flex items-center">
                        <input type="checkbox" name="sms_aktif" id="sms_aktif" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded" checked>
                        <label for="sms_aktif" class="ml-2 block text-sm text-gray-700">SMS bildirimleri etkin</label>
                    </div>
                    <div class="mt-4 flex justify-end gap-2">
                        <button type="button" class="modal-close bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded">
                            İptal
                        </button>
                        <button type="submit" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded">
                            Kaydet
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- İndirim Modal -->
    <div id="indirimModal" class="fixed inset-0 bg-gray-600 bg-opacity-75 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl w-11/12 md:w-1/2 lg:w-1/3 max-h-screen overflow-y-auto">
            <div class="flex justify-between items-center p-4 border-b">
                <h3 class="text-lg font-bold">İndirim Uygula</h3>
                <button class="modal-close text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-4">
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700">İndirim Türü</label>
                    <div class="grid grid-cols-2 gap-2 mt-1">
                        <button id="indirimTuruYuzde" class="indirim-turu bg-blue-500 text-white font-bold py-2 px-4 rounded active">
                            % Yüzde
                        </button>
                        <button id="indirimTuruTutar" class="indirim-turu bg-gray-200 text-gray-700 font-bold py-2 px-4 rounded">
                            ₺ Tutar
                        </button>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700">İndirim Değeri</label>
                    <input type="number" id="indirimDegeri" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    <div id="indirimAciklama" class="text-xs text-gray-500 mt-1">Yüzde olarak indirim değeri giriniz (0-100)</div>
                </div>
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700">Uygulama Şekli</label>
                    <div class="grid grid-cols-2 gap-2 mt-1">
                        <button id="indirimSekilGenel" class="indirim-sekli bg-blue-500 text-white font-bold py-2 px-4 rounded active">
                            Tüm Sepete
                        </button>
                        <button id="indirimSekilUrun" class="indirim-sekli bg-gray-200 text-gray-700 font-bold py-2 px-4 rounded">
                            Seçili Ürüne
                        </button>
                    </div>
                </div>
                <div id="indirimUrunSec" class="mb-3 hidden">
                    <label class="block text-sm font-medium text-gray-700">Ürün Seçimi</label>
                    <select id="indirimUrunSelect" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        <!-- JavaScript ile doldurulacak -->
                    </select>
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button class="modal-close bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded">
                        İptal
                    </button>
                    <button id="btnIndirimUygula" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded">
                        İndirimi Uygula
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Fiş Bekletme Modal -->
    <div id="bekletModal" class="fixed inset-0 bg-gray-600 bg-opacity-75 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl w-11/12 md:w-1/2 lg:w-1/3 max-h-screen overflow-y-auto">
            <div class="flex justify-between items-center p-4 border-b">
                <h3 class="text-lg font-bold">Fişi Beklet</h3>
                <button class="modal-close text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-4">
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700">Not (Opsiyonel)</label>
                    <textarea id="bekletmeNotu" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" rows="3" placeholder="Bekletme için not yazabilirsiniz..."></textarea>
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button class="modal-close bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded">
                        İptal
                    </button>
                    <button id="btnFisiBeklet" class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2 px-4 rounded">
                        Fişi Beklet
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bekleyen Fişler Modal -->
    <div id="bekleyenFislerModal" class="fixed inset-0 bg-gray-600 bg-opacity-75 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl w-11/12 md:w-3/4 lg:w-1/2 max-h-screen overflow-y-auto">
            <div class="flex justify-between items-center p-4 border-b">
                <h3 class="text-lg font-bold">Bekleyen Fişler</h3>
                <button class="modal-close text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-4">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Fiş No</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Müşteri</th>
                                <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Ürün Sayısı</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Toplam</th>
                                <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Tarih</th>
                                <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">İşlem</th>
                            </tr>
                        </thead>
                        <tbody id="bekleyenFislerListesi" class="bg-white divide-y divide-gray-200">
                            <!-- JavaScript ile doldurulacak -->
                        </tbody>
                    </table>
                </div>
                <div class="mt-4 flex justify-end">
                    <button class="modal-close bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded">
                        Kapat
                    </button>
                </div>
            </div>
        </div>
    </div>
	
	<!-- Müşteri Borçları Modal -->
<div id="musteriBorcModal" class="fixed inset-0 bg-gray-600 bg-opacity-75 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl w-11/12 md:w-3/4 lg:w-2/3 max-h-screen overflow-y-auto">
        <div class="flex justify-between items-center p-4 border-b">
            <h3 class="text-lg font-bold">Müşteri Borçları</h3>
            <button class="modal-close text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-4">
            <div id="musteriBorcOzeti" class="mb-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-blue-50 p-3 rounded border border-blue-100">
                    <div class="text-sm text-blue-700">Toplam Borç</div>
                    <div id="toplamBorc" class="text-lg font-bold">0,00 ₺</div>
                </div>
                <div class="bg-green-50 p-3 rounded border border-green-100">
                    <div class="text-sm text-green-700">Toplam Ödeme</div>
                    <div id="toplamOdeme" class="text-lg font-bold">0,00 ₺</div>
                </div>
                <div class="bg-red-50 p-3 rounded border border-red-100">
                    <div class="text-sm text-red-700">Kalan Borç</div>
                    <div id="kalanBorc" class="text-lg font-bold">0,00 ₺</div>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tarih</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Fiş No</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Mağaza</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Tutar</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Ödenen</th>
                            <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Durum</th>
                            <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">İşlem</th>
                        </tr>
                    </thead>
                    <tbody id="musteriBorcListesi" class="bg-white divide-y divide-gray-200">
                        <!-- JavaScript ile doldurulacak -->
                    </tbody>
                </table>
            </div>
            
            <div class="mt-4 flex justify-end gap-2">
                <button id="btnYeniBorc" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">
                    <i class="fas fa-plus-circle mr-1"></i> Yeni Borç Ekle
                </button>
                <button class="modal-close bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded">
                    Kapat
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Yeni Borç Modal -->
<div id="yeniBorcModal" class="fixed inset-0 bg-gray-600 bg-opacity-75 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl w-11/12 md:w-3/4 lg:w-1/2 max-h-screen overflow-y-auto">
        <div class="flex justify-between items-center p-4 border-b">
            <h3 class="text-lg font-bold">Yeni Borç Ekle</h3>
            <button class="modal-close text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-4">
            <input type="hidden" id="yeniBorc_musteriId">
            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700">Müşteri</label>
                <div id="yeniBorc_musteriAdi" class="py-2 px-3 bg-blue-50 rounded"></div>
            </div>
            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700">Borç Tarihi</label>
                <input type="date" id="yeniBorc_tarih" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700">Mağaza</label>
                <select id="yeniBorc_magaza" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    <!-- JavaScript ile doldurulacak -->
                </select>
            </div>
            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700">Fiş No (Opsiyonel)</label>
                <input type="text" id="yeniBorc_fisNo" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="mb-3">
                <div class="flex justify-between items-center mb-2">
                    <label class="block text-sm font-medium text-gray-700">Ürünler</label>
					  <div>
        <button id="btnGecmisSiparisler" class="bg-purple-500 hover:bg-purple-600 text-white py-1 px-3 rounded text-sm mr-2">
            <i class="fas fa-history mr-1"></i> Geçmiş Siparişlerden Ekle
        </button>
                    <button id="btnYeniBorc_urunEkle" class="bg-blue-500 hover:bg-blue-600 text-white py-1 px-3 rounded text-sm">
                        <i class="fas fa-plus mr-1"></i> Ürün Ekle
                    </button>
					</div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Ürün</th>
                                <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Miktar</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Birim Fiyat</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Toplam</th>
                                <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">İşlem</th>
                            </tr>
                        </thead>
                        <tbody id="yeniBorc_urunListesi">
                            <!-- JavaScript ile doldurulacak -->
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700">İndirim Tutarı</label>
                <input type="number" id="yeniBorc_indirim" value="0" min="0" step="0.01" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="font-bold text-lg text-right mb-3">
                Toplam: <span id="yeniBorc_toplamTutar">0,00 ₺</span>
            </div>
            <div class="mt-4 flex justify-end gap-2">
                <button class="modal-close bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded">
                    İptal
                </button>
                <button id="btnYeniBorcEkle" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded">
                    Borç Ekle
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Geçmiş Siparişler Modal -->
<div id="gecmisSiparislerModal" class="fixed inset-0 bg-gray-600 bg-opacity-75 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl w-11/12 md:w-3/4 lg:w-2/3 max-h-screen overflow-y-auto">
        <div class="flex justify-between items-center p-4 border-b">
            <h3 class="text-lg font-bold">Geçmiş Siparişler</h3>
            <button class="modal-close text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-4">
            <div class="mb-4">
                <p class="text-sm text-gray-500">Borç olarak eklemek istediğiniz siparişleri seçin.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-center w-12">
                                <input type="checkbox" id="selectAllOrders" class="form-checkbox h-4 w-4 text-blue-600 rounded">
                            </th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tarih</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Fiş No</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Mağaza</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Tutar</th>
                            <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Detay</th>
                        </tr>
                    </thead>
                    <tbody id="gecmisSiparisListesi" class="bg-white divide-y divide-gray-200">
                        <!-- JavaScript ile doldurulacak -->
                    </tbody>
                </table>
            </div>
            <div class="mt-4 flex justify-end gap-2">
                <button class="modal-close bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded">
                    İptal
                </button>
                <button id="btnSiparisleriEkle" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">
                    Seçilen Siparişleri Ekle
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Sipariş Detay Modal -->
<div id="siparisDetayModal" class="fixed inset-0 bg-gray-600 bg-opacity-75 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl w-11/12 md:w-1/2 max-h-screen overflow-y-auto">
        <div class="flex justify-between items-center p-4 border-b">
            <h3 class="text-lg font-bold">Sipariş Detayları</h3>
            <button class="modal-close text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-4">
            <div id="siparisDetayBilgileri" class="mb-4 p-3 bg-gray-50 rounded border border-gray-200">
                <!-- JavaScript ile doldurulacak -->
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Ürün</th>
                            <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Miktar</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Birim Fiyat</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Toplam</th>
                        </tr>
                    </thead>
                    <tbody id="siparisDetayListesi" class="bg-white divide-y divide-gray-200">
                        <!-- JavaScript ile doldurulacak -->
                    </tbody>
                </table>
            </div>
            <div class="mt-4 flex justify-end">
                <button class="modal-close bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded">
                    Kapat
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Ürün Arama Modal -->
<div id="urunAramaModal" class="fixed inset-0 bg-gray-600 bg-opacity-75 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl w-11/12 md:w-3/4 lg:w-1/2 max-h-screen overflow-y-auto">
        <div class="flex justify-between items-center p-4 border-b">
            <h3 class="text-lg font-bold">Ürün Ara</h3>
            <button class="modal-close text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-4">
            <div class="mb-4">
                <input type="text" id="urunAramaInput" placeholder="Ürün adı, barkod veya kodu ile ara..." class="w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Barkod</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Ürün Adı</th>
                            <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Stok</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Fiyat</th>
                            <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">İşlem</th>
                        </tr>
                    </thead>
                    <tbody id="urunAramaListesi">
                        <!-- JavaScript ile doldurulacak -->
                    </tbody>
                </table>
            </div>
            <div class="mt-4 flex justify-end">
                <button class="modal-close bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded">
                    Kapat
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Ürün Detay Modal -->
<div id="urunDetayModal" class="fixed inset-0 bg-gray-600 bg-opacity-75 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl w-11/12 md:w-1/3 max-h-screen overflow-y-auto">
        <div class="flex justify-between items-center p-4 border-b">
            <h3 class="text-lg font-bold">Ürün Detayları</h3>
            <button class="modal-close text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-4">
            <input type="hidden" id="urunDetay_id">
            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700">Ürün Adı</label>
                <div id="urunDetay_ad" class="py-2 px-3 bg-gray-100 rounded font-medium"></div>
            </div>
            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700">Miktar</label>
                <input type="number" id="urunDetay_miktar" min="1" value="1" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700">Birim Fiyat</label>
                <input type="number" id="urunDetay_fiyat" min="0" step="0.01" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700">Toplam</label>
                <div id="urunDetay_toplam" class="py-2 px-3 bg-gray-100 rounded font-bold text-right"></div>
            </div>
            <div class="mt-4 flex justify-end gap-2">
                <button class="modal-close bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded">
                    İptal
                </button>
                <button id="btnUrunDetayEkle" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded">
                    Ekle
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Borç Ödeme Modal -->
<div id="borcOdemeModal" class="fixed inset-0 bg-gray-600 bg-opacity-75 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl w-11/12 md:w-1/2 lg:w-1/3 max-h-screen overflow-y-auto">
        <div class="flex justify-between items-center p-4 border-b">
            <h3 class="text-lg font-bold">Borç Ödeme</h3>
            <button class="modal-close text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-4">
            <div class="mb-4">
                <div id="borcOdemeBilgileri" class="mb-4 p-3 bg-gray-50 rounded border border-gray-200">
                    <!-- JavaScript ile doldurulacak borç bilgileri -->
                </div>
                
                <form id="borcOdemeForm">
                    <input type="hidden" id="borcOdeme_borcId" name="borc_id">
                    
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-gray-700">Ödeme Tutarı *</label>
                        <input type="number" id="borcOdeme_tutar" name="odeme_tutari" step="0.01" required class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-gray-700">Ödeme Tarihi</label>
                        <input type="date" id="borcOdeme_tarih" name="odeme_tarihi" required class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-gray-700">Ödeme Yöntemi</label>
                        <select id="borcOdeme_yontem" name="odeme_yontemi" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <option value="nakit">Nakit</option>
                            <option value="kredi_karti">Kredi Kartı</option>
                            <option value="havale">Havale/EFT</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-gray-700">Açıklama</label>
                        <textarea id="borcOdeme_aciklama" name="aciklama" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" rows="2"></textarea>
                    </div>
                </form>
            </div>
            
            <div class="flex justify-end gap-2">
                <button class="modal-close bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded">
                    İptal
                </button>
                <button id="btnBorcOdemeYap" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded">
                    Ödeme Yap
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Müşteri Düzenleme Modal -->
<div id="musteriDuzenleModal" class="fixed inset-0 bg-gray-600 bg-opacity-75 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl w-11/12 md:w-1/2 lg:w-1/3 max-h-screen overflow-y-auto">
        <div class="flex justify-between items-center p-4 border-b">
            <h3 class="text-lg font-bold">Müşteri Bilgilerini Düzenle</h3>
            <button class="modal-close text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-4">
            <form id="musteriDuzenleForm">
                <input type="hidden" id="duzenle_musteri_id">
                
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700">Ad *</label>
                    <input type="text" id="duzenle_ad" name="ad" required class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700">Soyad *</label>
                    <input type="text" id="duzenle_soyad" name="soyad" required class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700">Telefon *</label>
                    <input type="text" id="duzenle_telefon" name="telefon" required pattern="[0-9]{10,11}" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    <div class="text-xs text-gray-500 mt-1">10 veya 11 haneli telefon numarası (örn: 05321234567)</div>
                </div>
                
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700">E-posta</label>
                    <input type="email" id="duzenle_email" name="email" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700">Kart No (Barkod)</label>
                    <input type="text" id="duzenle_barkod" name="barkod" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700">Puan Oranı (%)</label>
                    <input type="number" id="duzenle_puan_oran" name="puan_oran" min="0" step="0.01" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    <div class="text-xs text-gray-500 mt-1">Müşterinin her 100 TL'si için kazanacağı puan (örn: 5 yazılırsa 100 TL'de 5 puan)</div>
                </div>
                
                <div class="mb-3 flex items-center">
                    <input type="checkbox" id="duzenle_sms_aktif" name="sms_aktif" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                    <label for="duzenle_sms_aktif" class="ml-2 block text-sm text-gray-700">SMS bildirimleri etkin</label>
                </div>
                
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" class="modal-close bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded">
                        İptal
                    </button>
                    <button type="submit" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded">
                        Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- Raporlar Modal -->
<div id="raporlarModal" class="fixed inset-0 bg-gray-600 bg-opacity-75 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl w-11/12 md:w-3/4 lg:w-4/5 max-h-screen overflow-y-auto">
        <div class="flex justify-between items-center p-4 border-b">
            <h3 class="text-lg font-bold">Satış Raporları</h3>
            <button id="raporModalKapat" class="modal-close text-gray-500 hover:text-gray-700">
			<i class="fas fa-times"></i>
			</button>
        </div>
        <div class="p-4">
            <!-- Tarih Filtresi -->
            <div class="mb-4 bg-gray-50 p-4 rounded-lg border">
                <h4 class="font-bold mb-2">Tarih Aralığı</h4>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Başlangıç Tarihi</label>
                        <input type="date" id="report_start_date" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Bitiş Tarihi</label>
                        <input type="date" id="report_end_date" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Mağaza</label>
                        <select id="report_magaza" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Tüm Mağazalar</option>
                            <!-- JavaScript ile doldurulacak -->
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button id="btnRaporFiltrele" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded w-full">
                            <i class="fas fa-filter mr-1"></i> Filtrele
                        </button>
                    </div>
                </div>
            </div>

            <!-- Satış Özeti -->
            <div class="mb-4">
                <h4 class="font-bold mb-2">Özet Bilgiler</h4>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div class="bg-blue-50 p-3 rounded border border-blue-100">
                        <div class="text-sm text-blue-700">Toplam Satış Adedi</div>
                        <div id="report_toplam_satis" class="text-lg font-bold">0</div>
                    </div>
                    <div class="bg-green-50 p-3 rounded border border-green-100">
                        <div class="text-sm text-green-700">Toplam Ciro</div>
                        <div id="report_toplam_ciro" class="text-lg font-bold">0,00 ₺</div>
                    </div>
                    <div class="bg-red-50 p-3 rounded border border-red-100">
                        <div class="text-sm text-red-700">İade Tutarı</div>
                        <div id="report_toplam_iade" class="text-lg font-bold">0,00 ₺</div>
                    </div>
                    <div class="bg-yellow-50 p-3 rounded border border-yellow-100">
                        <div class="text-sm text-yellow-700">Net Kazanç</div>
                        <div id="report_net_kazanc" class="text-lg font-bold">0,00 ₺</div>
                    </div>
                </div>
            </div>

            <!-- Ödeme Türleri Tablosu -->
            <div class="mb-4">
                <h4 class="font-bold mb-2">Ödeme Türleri</h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div class="bg-gray-50 p-3 rounded border">
                        <div class="text-sm text-gray-700">Nakit</div>
                        <div id="report_nakit" class="text-lg font-bold">0,00 ₺</div>
                    </div>
                    <div class="bg-gray-50 p-3 rounded border">
                        <div class="text-sm text-gray-700">Kredi Kartı</div>
                        <div id="report_kredi_karti" class="text-lg font-bold">0,00 ₺</div>
                    </div>
                    <div class="bg-gray-50 p-3 rounded border">
                        <div class="text-sm text-gray-700">Borç</div>
                        <div id="report_borc" class="text-lg font-bold">0,00 ₺</div>
                    </div>
                </div>
            </div>

            <!-- Geçmiş Satışlar Tablosu -->
            <div class="mb-4">
                <h4 class="font-bold mb-2">Geçmiş Satışlar</h4>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200" id="satislarTable">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tarih</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fiş No</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Müşteri</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Mağaza</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tip</th>
                                <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">Tutar</th>
                                <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase">İşlem</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <!-- JavaScript ile doldurulacak -->
                        </tbody>
                    </table>
                </div>
                <div id="satislarPagination" class="mt-4 flex justify-between items-center">
                    <div class="text-sm text-gray-700">
                        <span id="resultInfo">0 sonuç gösteriliyor</span>
                    </div>
                    <div class="flex space-x-1">
                        <button id="prevPageBtn" class="px-3 py-1 bg-gray-200 text-gray-600 rounded hover:bg-gray-300 disabled:opacity-50">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <span id="currentPage" class="px-3 py-1 bg-blue-500 text-white rounded">1</span>
                        <button id="nextPageBtn" class="px-3 py-1 bg-gray-200 text-gray-600 rounded hover:bg-gray-300 disabled:opacity-50">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
    
    <!-- İşlem Bildirimi Toast -->
    <div id="toastBildirim" class="fixed right-4 bottom-4 bg-green-500 text-white px-4 py-2 rounded shadow-lg transform transition-all duration-300 translate-y-20 opacity-0">
        <div class="flex items-center">
            <i class="fas fa-check-circle mr-2"></i>
            <span id="toastMesaj"></span>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lodash.js/4.17.21/lodash.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script src="admin/assets/js/pos-system.js"></script>
    <script>
$(document).ready(function() {
    // Fiş numarası oluştur
    fisNo = generateFisNo();
    $('#fisNo').val(fisNo);
    
    // Mağaza seçimi
    var magazaId = "<?php echo $currentMagazaId; ?>";
    if (magazaId) {
        $('#magaza').val(magazaId);
        
        // Mağaza değiştirilemesin (kasiyerler için)
        <?php if ($_SESSION['yetki'] == 'kasiyer'): ?>
        $('#magaza').prop('disabled', true);
        $('#magaza').addClass('bg-gray-100');
        <?php endif; ?>
    }
    
    // Kasiyer seçimi
    var userId = "<?php echo $currentUserId; ?>";
    var userYetki = "<?php echo $currentUserYetki; ?>";
    
    if (userYetki === 'kasiyer') {
        $('#kasiyer').val(userId);
    }
    
    // Barkod input'una fokusla
    $('#barkodInput').focus();
});
</script>
</body>
</html>