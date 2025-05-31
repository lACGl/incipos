<?php
/**
 * Fiyat Yönetimi - Kategori Bazlı Fiyatlandırma
 * Belirli bir kategorideki tüm ürünlerin fiyatlarını topluca güncelleme
 */

// Session yönetimi ve yetkisiz erişim kontrolü
require_once '../../session_manager.php';

// Session kontrolü
checkUserSession();

// Veritabanı bağlantısı
require_once '../../db_connection.php';

// Sayfa başlığı
$page_title = "Kategori Bazlı Fiyatlandırma";

// İşlem sonucu mesajı
$message = '';
$message_type = '';
$updated_count = 0;

// Form gönderildi mi kontrol et
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    
    // Kategori filtreleri
    $departman_id = isset($_POST['departman_id']) ? intval($_POST['departman_id']) : 0;
    $ana_grup_id = isset($_POST['ana_grup_id']) ? intval($_POST['ana_grup_id']) : 0;
    $alt_grup_id = isset($_POST['alt_grup_id']) ? intval($_POST['alt_grup_id']) : 0;
    
    // Fiyat güncelleme parametreleri
    $guncelleme_turu = isset($_POST['guncelleme_turu']) ? $_POST['guncelleme_turu'] : 'yuzde_artis';
    $guncelleme_degeri = isset($_POST['guncelleme_degeri']) ? floatval($_POST['guncelleme_degeri']) : 0;
    $aciklama = isset($_POST['aciklama']) ? $_POST['aciklama'] : "Kategori bazlı fiyatlandırma";
    
    // En az bir kategori seçili mi kontrol et
    if ($departman_id == 0 && $ana_grup_id == 0 && $alt_grup_id == 0) {
        $message = "Lütfen en az bir kategori seçin.";
        $message_type = "error";
    } else {
        // SQL sorgusu oluştur
        $sql = "SELECT id, ad, satis_fiyati, alis_fiyati FROM urun_stok WHERE durum = 'aktif'";
        $params = [];
        
        // Kategori filtrelerini ekle
        if ($departman_id > 0) {
            $sql .= " AND departman_id = :departman_id";
            $params[':departman_id'] = $departman_id;
        }
        
        if ($ana_grup_id > 0) {
            $sql .= " AND ana_grup_id = :ana_grup_id";
            $params[':ana_grup_id'] = $ana_grup_id;
        }
        
        if ($alt_grup_id > 0) {
            $sql .= " AND alt_grup_id = :alt_grup_id";
            $params[':alt_grup_id'] = $alt_grup_id;
        }
        
        try {
            // Transaction başlat
            $conn->beginTransaction();
            
            // Ürünleri seç
            $stmt = $conn->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $total_products = count($products);
            
            if ($total_products == 0) {
                $message = "Seçilen kategoride ürün bulunamadı.";
                $message_type = "error";
            } else {
                // Her ürün için fiyat güncelle
                foreach ($products as $product) {
                    $urun_id = $product['id'];
                    $mevcut_fiyat = $product['satis_fiyati'];
                    $alis_fiyati = $product['alis_fiyati'];
                    $yeni_fiyat = $mevcut_fiyat;
                    
                    // Güncelleme türüne göre yeni fiyat hesapla
                    switch ($guncelleme_turu) {
                        case 'yuzde_artis':
                            $yeni_fiyat = $mevcut_fiyat * (1 + ($guncelleme_degeri / 100));
                            break;
                            
                        case 'yuzde_azalis':
                            $yeni_fiyat = $mevcut_fiyat * (1 - ($guncelleme_degeri / 100));
                            break;
                            
                        case 'sabit_artis':
                            $yeni_fiyat = $mevcut_fiyat + $guncelleme_degeri;
                            break;
                            
                        case 'sabit_azalis':
                            $yeni_fiyat = max(0, $mevcut_fiyat - $guncelleme_degeri);
                            break;
                            
                        case 'sabit_fiyat':
                            $yeni_fiyat = $guncelleme_degeri;
                            break;
                            
                        case 'kar_marji':
                            // Alış fiyatı üzerinden kar marjı hesapla
                            if ($alis_fiyati > 0) {
                                $yeni_fiyat = $alis_fiyati * (1 + ($guncelleme_degeri / 100));
                            } else {
                                // Alış fiyatı yoksa fiyatı değiştirme
                                continue;
                            }
                            break;
                    }
                    
                    // Fiyatı yuvarla (2 ondalık basamak)
                    $yeni_fiyat = round($yeni_fiyat, 2);
                    
                    // Fiyat değişmediyse güncelleme yapma
                    if ($yeni_fiyat == $mevcut_fiyat) {
                        continue;
                    }
                    
                    // Ürün fiyatını güncelle
                    $update_stmt = $conn->prepare("UPDATE urun_stok SET satis_fiyati = :yeni_fiyat WHERE id = :urun_id");
                    $update_stmt->bindParam(':yeni_fiyat', $yeni_fiyat);
                    $update_stmt->bindParam(':urun_id', $urun_id);
                    $update_stmt->execute();
                    
                    // Fiyat geçmişine kaydet
                    $log_stmt = $conn->prepare("
                        INSERT INTO urun_fiyat_gecmisi (urun_id, islem_tipi, eski_fiyat, yeni_fiyat, aciklama, kullanici_id)
                        VALUES (:urun_id, 'satis_fiyati_guncelleme', :eski_fiyat, :yeni_fiyat, :aciklama, :kullanici_id)
                    ");
                    $log_stmt->bindParam(':urun_id', $urun_id);
                    $log_stmt->bindParam(':eski_fiyat', $mevcut_fiyat);
                    $log_stmt->bindParam(':yeni_fiyat', $yeni_fiyat);
                    $log_stmt->bindParam(':aciklama', $aciklama);
                    $log_stmt->bindParam(':kullanici_id', $_SESSION['user_id']);
                    $log_stmt->execute();
                    
                    $updated_count++;
                }
                
                // İşlem başarılı
                $conn->commit();
                
                $message = "$updated_count / $total_products ürünün fiyatı başarıyla güncellendi.";
                $message_type = "success";
            }
            
        } catch (PDOException $e) {
            // Hata durumunda rollback
            $conn->rollBack();
            
            $message = "Fiyat güncelleme sırasında bir hata oluştu: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// Departmanları getir
$stmt = $conn->prepare("SELECT id, ad FROM departmanlar ORDER BY ad");
$stmt->execute();
$departmanlar = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ana grupları getir (departman seçiliyse)
$ana_gruplar = [];
if (isset($_POST['departman_id']) && $_POST['departman_id'] > 0) {
    $stmt = $conn->prepare("SELECT id, ad FROM ana_gruplar WHERE departman_id = ? ORDER BY ad");
    $stmt->execute([$_POST['departman_id']]);
    $ana_gruplar = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Alt grupları getir (ana grup seçiliyse)
$alt_gruplar = [];
if (isset($_POST['ana_grup_id']) && $_POST['ana_grup_id'] > 0) {
    $stmt = $conn->prepare("SELECT id, ad FROM alt_gruplar WHERE ana_grup_id = ? ORDER BY ad");
    $stmt->execute([$_POST['ana_grup_id']]);
    $alt_gruplar = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Header'ı dahil et
include '../../header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold"><?php echo $page_title; ?></h1>
        <a href="index.php" class="bg-gray-500 hover:bg-gray-600 text-white py-2 px-4 rounded">Geri Dön</a>
    </div>
    
    <?php if (!empty($message)): ?>
        <div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700'; ?>">
            <p><?php echo $message; ?></p>
        </div>
    <?php endif; ?>
    
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-lg font-semibold mb-4 border-b pb-2">Kategori Seçimi ve Fiyat Ayarları</h2>
        
        <form method="post" id="categoryPricingForm">
            <!-- Kategori Seçimi -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div>
                    <label for="departman_id" class="block text-sm font-medium text-gray-700 mb-1">Departman</label>
                    <select id="departman_id" name="departman_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                        <option value="0">Seçiniz...</option>
                        <?php foreach ($departmanlar as $dept): ?>
                            <option value="<?php echo $dept['id']; ?>" <?php echo (isset($_POST['departman_id']) && $_POST['departman_id'] == $dept['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept['ad']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label for="ana_grup_id" class="block text-sm font-medium text-gray-700 mb-1">Ana Grup</label>
                    <select id="ana_grup_id" name="ana_grup_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50" <?php echo empty($ana_gruplar) ? 'disabled' : ''; ?>>
                        <option value="0">Seçiniz...</option>
                        <?php foreach ($ana_gruplar as $grup): ?>
                            <option value="<?php echo $grup['id']; ?>" <?php echo (isset($_POST['ana_grup_id']) && $_POST['ana_grup_id'] == $grup['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($grup['ad']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label for="alt_grup_id" class="block text-sm font-medium text-gray-700 mb-1">Alt Grup</label>
                    <select id="alt_grup_id" name="alt_grup_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50" <?php echo empty($alt_gruplar) ? 'disabled' : ''; ?>>
                        <option value="0">Seçiniz...</option>
                        <?php foreach ($alt_gruplar as $grup): ?>
                            <option value="<?php echo $grup['id']; ?>" <?php echo (isset($_POST['alt_grup_id']) && $_POST['alt_grup_id'] == $grup['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($grup['ad']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <!-- Fiyat Güncelleme Ayarları -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <label for="guncelleme_turu" class="block text-sm font-medium text-gray-700 mb-1">Fiyatlandırma Yöntemi</label>
                    <select id="guncelleme_turu" name="guncelleme_turu" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                        <option value="yuzde_artis" <?php echo (isset($_POST['guncelleme_turu']) && $_POST['guncelleme_turu'] == 'yuzde_artis') ? 'selected' : ''; ?>>
                            Yüzde Artış (%)
                        </option>
                        <option value="yuzde_azalis" <?php echo (isset($_POST['guncelleme_turu']) && $_POST['guncelleme_turu'] == 'yuzde_azalis') ? 'selected' : ''; ?>>
                            Yüzde Azalış (%)
                        </option>
                        <option value="sabit_artis" <?php echo (isset($_POST['guncelleme_turu']) && $_POST['guncelleme_turu'] == 'sabit_artis') ? 'selected' : ''; ?>>
                            Sabit Tutar Artış (₺)
                        </option>
                        <option value="sabit_azalis" <?php echo (isset($_POST['guncelleme_turu']) && $_POST['guncelleme_turu'] == 'sabit_azalis') ? 'selected' : ''; ?>>
                            Sabit Tutar Azalış (₺)
                        </option>
                        <option value="sabit_fiyat" <?php echo (isset($_POST['guncelleme_turu']) && $_POST['guncelleme_turu'] == 'sabit_fiyat') ? 'selected' : ''; ?>>
                            Sabit Fiyat (₺)
                        </option>
                        <option value="kar_marji" <?php echo (isset($_POST['guncelleme_turu']) && $_POST['guncelleme_turu'] == 'kar_marji') ? 'selected' : ''; ?>>
                            Alış Fiyatı Üzerinden Kar Marjı (%)
                        </option>
                    </select>
                </div>
                
                <div>
                    <label for="guncelleme_degeri" class="block text-sm font-medium text-gray-700 mb-1">Değer</label>
                    <input type="number" id="guncelleme_degeri" name="guncelleme_degeri" step="0.01" min="0" required
                           value="<?php echo isset($_POST['guncelleme_degeri']) ? htmlspecialchars($_POST['guncelleme_degeri']) : ''; ?>" 
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                </div>
            </div>
            
            <!-- Açıklama -->
            <div class="mb-6">
                <label for="aciklama" class="block text-sm font-medium text-gray-700 mb-1">Açıklama</label>
                <textarea id="aciklama" name="aciklama" rows="2" 
                          class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"><?php echo isset($_POST['aciklama']) ? htmlspecialchars($_POST['aciklama']) : 'Kategori bazlı fiyatlandırma'; ?></textarea>
            </div>
            
            <!-- Uyarı -->
            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
                <div class="flex">
                    <div>
                        <p class="text-yellow-700">
                            <strong>Dikkat:</strong> Bu işlem, seçtiğiniz kategorideki tüm ürünlerin fiyatlarını güncelleyecektir. 
                            İşlem geri alınamaz. Lütfen seçimlerinizi dikkatle yapın.
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- İşlem Butonları -->
            <div class="flex justify-between">
                <button id="previewButton" type="button" class="bg-gray-500 hover:bg-gray-600 text-white py-2 px-4 rounded-md transition">
                    Ön İzleme
                </button>
                
                <button type="submit" name="submit" class="bg-blue-500 hover:bg-blue-600 text-white py-2 px-6 rounded-md transition">
                    Fiyatları Güncelle
                </button>
            </div>
        </form>
    </div>
    
    <!-- Ön İzleme Alanı -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-lg font-semibold mb-4 border-b pb-2">Etkilenecek Ürünler</h2>
        
        <div id="previewArea" class="min-h-[100px] flex items-center justify-center text-gray-500">
            <p>Filtre seçimlerinizi yaptıktan sonra etkilenecek ürünleri görmek için "Ön İzleme" butonuna tıklayın.</p>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Ana grupları otomatik olarak yükle (sayfa yüklendiğinde veya departman değiştiğinde)
    function loadAnaGruplar(departmanId) {
        if (!departmanId || departmanId == 0) return;
        
        const anaGrupSelect = document.getElementById('ana_grup_id');
        const altGrupSelect = document.getElementById('alt_grup_id');
        
        // Ana grup seçimini sıfırla
        anaGrupSelect.innerHTML = '<option value="0">Seçiniz...</option>';
        anaGrupSelect.disabled = true;
        
        // Alt grup seçimini sıfırla
        altGrupSelect.innerHTML = '<option value="0">Seçiniz...</option>';
        altGrupSelect.disabled = true;
        
        // Ana grupları getir
        fetch(`/admin/api/get_ana_gruplar.php?departman_id=${departmanId}`)
            .then(response => response.json())
            .then(data => {
                console.log("Ana Grup API yanıtı:", data);
                
                // Veriyi dizi olarak işleme
                let anaGruplar = data;
                
                // Eğer data bir dizi değilse ve veri nesnesi içinde ise
                if (!Array.isArray(data) && data && typeof data === 'object') {
                    // Farklı API formatlarını kontrol et
                    if (Array.isArray(data.data)) {
                        anaGruplar = data.data;
                    } else if (data.records && Array.isArray(data.records)) {
                        anaGruplar = data.records;
                    } else if (data.results && Array.isArray(data.results)) {
                        anaGruplar = data.results;
                    } else if (data.items && Array.isArray(data.items)) {
                        anaGruplar = data.items;
                    } else {
                        // Nesnenin ilk dizi değerini al
                        for (const key in data) {
                            if (Array.isArray(data[key])) {
                                anaGruplar = data[key];
                                break;
                            }
                        }
                    }
                }
                
                if (Array.isArray(anaGruplar) && anaGruplar.length > 0) {
                    anaGruplar.forEach(group => {
                        // ID ve AD alanlarını kontrol et (büyük/küçük harf duyarlılığı)
                        const id = group.id || group.ID || group.Id;
                        const ad = group.ad || group.AD || group.Ad || group.name || group.NAME || group.Name;
                        
                        if (id && ad) {
                            const option = document.createElement('option');
                            option.value = id;
                            option.textContent = ad;
                            anaGrupSelect.appendChild(option);
                        }
                    });
                    anaGrupSelect.disabled = false;
                } else {
                    console.log('Ana grup verisi boş geldi veya hatalı format:', anaGruplar);
                }
            })
            .catch(error => {
                console.error('Ana grup verisi alınamadı:', error);
                anaGrupSelect.disabled = true;
            });
    }
    
    // Departman değiştiğinde hemen ana grupları getir
    document.getElementById('departman_id').addEventListener('change', function() {
        loadAnaGruplar(this.value);
    });
    
    // Alt grupları otomatik olarak yükle (ana grup değiştiğinde)
    function loadAltGruplar(anaGrupId) {
        if (!anaGrupId || anaGrupId == 0) return;
        
        const altGrupSelect = document.getElementById('alt_grup_id');
        
        // Alt grup seçimini sıfırla
        altGrupSelect.innerHTML = '<option value="0">Seçiniz...</option>';
        altGrupSelect.disabled = true;
        
        // Alt grupları getir
        fetch(`/admin/api/get_alt_gruplar.php?ana_grup_id=${anaGrupId}`)
            .then(response => response.json())
            .then(data => {
                console.log("Alt Grup API yanıtı:", data);
                
                // Veriyi dizi olarak işleme
                let altGruplar = data;
                
                // Eğer data bir dizi değilse ve veri nesnesi içinde ise
                if (!Array.isArray(data) && data && typeof data === 'object') {
                    // Farklı API formatlarını kontrol et
                    if (Array.isArray(data.data)) {
                        altGruplar = data.data;
                    } else if (data.records && Array.isArray(data.records)) {
                        altGruplar = data.records;
                    } else if (data.results && Array.isArray(data.results)) {
                        altGruplar = data.results;
                    } else if (data.items && Array.isArray(data.items)) {
                        altGruplar = data.items;
                    } else {
                        // Nesnenin ilk dizi değerini al
                        for (const key in data) {
                            if (Array.isArray(data[key])) {
                                altGruplar = data[key];
                                break;
                            }
                        }
                    }
                }
                
                if (Array.isArray(altGruplar) && altGruplar.length > 0) {
                    altGruplar.forEach(group => {
                        // ID ve AD alanlarını kontrol et (büyük/küçük harf duyarlılığı)
                        const id = group.id || group.ID || group.Id;
                        const ad = group.ad || group.AD || group.Ad || group.name || group.NAME || group.Name;
                        
                        if (id && ad) {
                            const option = document.createElement('option');
                            option.value = id;
                            option.textContent = ad;
                            altGrupSelect.appendChild(option);
                        }
                    });
                    altGrupSelect.disabled = false;
                } else {
                    console.log('Alt grup verisi boş geldi veya hatalı format:', altGruplar);
                }
            })
            .catch(error => {
                console.error('Alt grup verisi alınamadı:', error);
                altGrupSelect.disabled = true;
            });
    }
    
    // Ana grup değiştiğinde hemen alt grupları getir
    document.getElementById('ana_grup_id').addEventListener('change', function() {
        loadAltGruplar(this.value);
    });
    
    // Sayfa ilk yüklendiğinde mevcut departman ve ana grup seçili ise otomatik yükle
    const initialDepartman = document.getElementById('departman_id').value;
    if (initialDepartman && initialDepartman > 0) {
        loadAnaGruplar(initialDepartman);
        
        // Departman yüklendikten kısa bir süre sonra ana grup için alt grupları yükle
        setTimeout(() => {
            const initialAnaGrup = document.getElementById('ana_grup_id').value;
            if (initialAnaGrup && initialAnaGrup > 0) {
                loadAltGruplar(initialAnaGrup);
            }
        }, 500);
    }
    
    // Ön İzleme butonuna tıklandığında
    document.getElementById('previewButton').addEventListener('click', function() {
        const previewArea = document.getElementById('previewArea');
        previewArea.innerHTML = '<div class="text-center"><div class="animate-spin rounded-full h-10 w-10 border-b-2 border-blue-500 mx-auto"></div><p class="mt-2">Yükleniyor...</p></div>';
        
        // Form verilerini al
        const formData = new FormData(document.getElementById('categoryPricingForm'));
        formData.append('preview', '1'); // Ön izleme isteği olduğunu belirt
        
        // AJAX isteği gönder
        fetch('preview_category_pricing.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            previewArea.innerHTML = data;
        })
        .catch(error => {
            previewArea.innerHTML = '<p class="text-red-500">Ön izleme yüklenirken bir hata oluştu. Lütfen tekrar deneyin.</p>';
            console.error('Ön izleme hatası:', error);
        });
    });
});
</script>

<?php include '../../footer.php'; ?>