<?php
// Debug için hataları göster
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Header ve veritabanı bağlantısını dahil et
include 'header.php';
require_once 'db_connection.php';

// Kullanıcı giriş kontrolü
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: index.php");
    exit;
}

// İndirim ID parametresini al
$discount_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// İndirim bilgilerini çek
$discount_query = "SELECT * FROM indirimler WHERE id = :id";
$stmt = $conn->prepare($discount_query);
$stmt->bindParam(':id', $discount_id);
$stmt->execute();
$discount = $stmt->fetch(PDO::FETCH_ASSOC);

// İndirim bulunamadıysa ana sayfaya yönlendir
if (!$discount) {
    header("Location: discounts.php");
    exit;
}

// İndirim detaylarını çek
$details_query = "SELECT id.*, us.ad as urun_adi, us.barkod, us.satis_fiyati  
                FROM indirim_detay id
                JOIN urun_stok us ON id.urun_id = us.id
                WHERE id.indirim_id = :indirim_id";
$stmt = $conn->prepare($details_query);
$stmt->bindParam(':indirim_id', $discount_id);
$stmt->execute();
$discount_details = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Form gönderildiğinde
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Debug için POST verilerini kontrol et
    error_log("POST verileri: " . print_r($_POST, true));
    
    // İndirime ürün ekleme işlemi
    if (isset($_POST['product_ids']) && is_array($_POST['product_ids']) && !empty($_POST['product_ids'])) {
        $added_count = 0;
        $skipped_count = 0;
        
        try {
            $conn->beginTransaction();
            
            foreach ($_POST['product_ids'] as $product_id) {
                // Ürün bilgilerini al
                $product_query = "SELECT id, satis_fiyati, indirimli_fiyat FROM urun_stok WHERE id = :id";
                $stmt = $conn->prepare($product_query);
                $stmt->bindParam(':id', $product_id);
                $stmt->execute();
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$product) {
                    continue;
                }
                
                // Ürün zaten indirimli mi kontrol et
                if (!is_null($product['indirimli_fiyat'])) {
                    $skipped_count++;
                    continue;
                }
                
                $original_price = $product['satis_fiyati'];
                
                // İndirimli fiyat hesapla
                if ($discount['indirim_turu'] === 'yuzde') {
                    $discounted_price = $original_price * (1 - ($discount['indirim_degeri'] / 100));
                } else {
                    $discounted_price = $original_price - $discount['indirim_degeri'];
                    $discounted_price = max(0, $discounted_price); // Negatif olamaz
                }
                
                // İndirim detayını ekle
                $detail_insert = "INSERT INTO indirim_detay (indirim_id, urun_id, eski_fiyat, indirimli_fiyat)
                                VALUES (:indirim_id, :urun_id, :eski_fiyat, :indirimli_fiyat)";
                $stmt = $conn->prepare($detail_insert);
                $stmt->bindParam(':indirim_id', $discount_id);
                $stmt->bindParam(':urun_id', $product_id);
                $stmt->bindParam(':eski_fiyat', $original_price);
                $stmt->bindParam(':indirimli_fiyat', $discounted_price);
                $stmt->execute();
                
                // Ürün tablosundaki indirimli fiyatı güncelle
                $update_product = "UPDATE urun_stok SET 
                                indirimli_fiyat = :indirimli_fiyat,
                                indirim_baslangic_tarihi = :baslangic_tarihi,
                                indirim_bitis_tarihi = :bitis_tarihi
                                WHERE id = :id";
                $stmt = $conn->prepare($update_product);
                $stmt->bindParam(':indirimli_fiyat', $discounted_price);
                $stmt->bindParam(':baslangic_tarihi', $discount['baslangic_tarihi']);
                $stmt->bindParam(':bitis_tarihi', $discount['bitis_tarihi']);
                $stmt->bindParam(':id', $product_id);
                $stmt->execute();
                
                $added_count++;
            }
            
            $conn->commit();
            
            // Mesaj göster
            $success_message = "$added_count ürün indirime eklendi.";
            if ($skipped_count > 0) {
                $_SESSION['warning_message'] = "$skipped_count ürün halihazırda indirimli olduğu için atlandı.";
            }
            
            // İndirim detaylarını tekrar çek
            $stmt = $conn->prepare($details_query);
            $stmt->bindParam(':indirim_id', $discount_id);
            $stmt->execute();
            $discount_details = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $conn->rollBack();
            $error_message = "Ürünler eklenirken bir hata oluştu: " . $e->getMessage();
        }
    }
    
    // Ürün kaldırma işlemi
    if (isset($_POST['remove_product']) && isset($_POST['detail_id'])) {
        $detail_id = $_POST['detail_id'];
        
        try {
            $conn->beginTransaction();
            
            // İndirim detayını bul
            $detail_query = "SELECT id.*, id.urun_id 
                            FROM indirim_detay id
                            WHERE id.id = :id AND id.indirim_id = :indirim_id";
            $stmt = $conn->prepare($detail_query);
            $stmt->bindParam(':id', $detail_id);
            $stmt->bindParam(':indirim_id', $discount_id);
            $stmt->execute();
            $detail = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($detail) {
                // Ürünün indirimli fiyatını kaldır
                $update_product = "UPDATE urun_stok SET 
                                indirimli_fiyat = NULL,
                                indirim_baslangic_tarihi = NULL,
                                indirim_bitis_tarihi = NULL
                                WHERE id = :id";
                $stmt = $conn->prepare($update_product);
                $stmt->bindParam(':id', $detail['urun_id']);
                $stmt->execute();
                
                // İndirim detayını sil
                $delete_detail = "DELETE FROM indirim_detay WHERE id = :id";
                $stmt = $conn->prepare($delete_detail);
                $stmt->bindParam(':id', $detail_id);
                $stmt->execute();
                
                $success_message = "Ürün indirimden kaldırıldı.";
            }
            
            $conn->commit();
            
            // İndirim detaylarını yeni duruma göre tekrar çek
            $stmt = $conn->prepare($details_query);
            $stmt->bindParam(':indirim_id', $discount_id);
            $stmt->execute();
            $discount_details = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $conn->rollBack();
            $error_message = "Ürün kaldırılırken bir hata oluştu: " . $e->getMessage();
        }
    }
}
?>

<!-- Sayfa içeriği -->
<div class="container mx-auto px-4 py-8">
    <!-- Başlık ve butonlar -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold"><?php echo htmlspecialchars($discount['ad']); ?> - İndirim Detayları</h1>
            <p class="text-gray-600">İndirim bilgileri ve ürünler</p>
        </div>
        <a href="discounts.php" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
            İndirimlere Dön
        </a>
    </div>

    <!-- Mesajlar -->
    <?php if (isset($success_message)): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
            <p><?php echo $success_message; ?></p>
        </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
            <p><?php echo $error_message; ?></p>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['warning_message'])): ?>
        <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6" role="alert">
            <p><?php echo $_SESSION['warning_message']; ?></p>
        </div>
        <?php unset($_SESSION['warning_message']); ?>
    <?php endif; ?>

    <!-- İndirim bilgileri kartı -->
    <div class="bg-white shadow-md rounded-lg p-6 mb-6">
        <h2 class="text-lg font-semibold mb-4">İndirim Bilgileri</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <p class="text-sm text-gray-500">İndirim Türü</p>
                <p class="font-medium">
                    <?php 
                    echo $discount['indirim_turu'] === 'yuzde' 
                        ? 'Yüzde (%)' 
                        : 'Tutar (₺)';
                    ?>
                </p>
            </div>
            
            <div>
                <p class="text-sm text-gray-500">İndirim Değeri</p>
                <p class="font-medium">
                    <?php 
                    if ($discount['indirim_turu'] === 'yuzde') {
                        echo '%' . number_format($discount['indirim_degeri'], 2, ',', '.');
                    } else {
                        echo '₺' . number_format($discount['indirim_degeri'], 2, ',', '.'); 
                    }
                    ?>
                </p>
            </div>
            
            <div>
                <p class="text-sm text-gray-500">Geçerlilik Tarihi</p>
                <p class="font-medium">
                    <?php 
                    echo date('d.m.Y', strtotime($discount['baslangic_tarihi'])) . ' - ' 
                         . date('d.m.Y', strtotime($discount['bitis_tarihi']));
                    ?>
                </p>
            </div>
            
            <div>
                <p class="text-sm text-gray-500">Uygulama Kapsamı</p>
                <p class="font-medium">
                    <?php 
                    switch ($discount['uygulama_turu']) {
                        case 'tum':
                            echo 'Tüm Ürünler';
                            break;
                        case 'secili':
                            echo 'Seçili Ürünler';
                            break;
                        case 'departman':
                            $dept_id = $discount['filtre_degeri'];
                            $dept_query = "SELECT ad FROM departmanlar WHERE id = :id";
                            $stmt = $conn->prepare($dept_query);
                            $stmt->bindParam(':id', $dept_id);
                            $stmt->execute();
                            $dept_name = $stmt->fetchColumn();
                            echo 'Departman: ' . htmlspecialchars($dept_name);
                            break;
                        case 'ana_grup':
                            $group_id = $discount['filtre_degeri'];
                            $group_query = "SELECT ad FROM ana_gruplar WHERE id = :id";
                            $stmt = $conn->prepare($group_query);
                            $stmt->bindParam(':id', $group_id);
                            $stmt->execute();
                            $group_name = $stmt->fetchColumn();
                            echo 'Ana Grup: ' . htmlspecialchars($group_name);
                            break;
                    }
                    ?>
                </p>
            </div>
            
            <div>
                <p class="text-sm text-gray-500">Durum</p>
                <p class="font-medium">
                    <span class="px-2 py-1 text-xs rounded-full 
                        <?php echo $discount['durum'] === 'aktif' 
                            ? 'bg-green-100 text-green-800' 
                            : 'bg-red-100 text-red-800'; ?>">
                        <?php echo ucfirst($discount['durum']); ?>
                    </span>
                </p>
            </div>
            
            <div>
                <p class="text-sm text-gray-500">Açıklama</p>
                <p class="font-medium">
                    <?php echo !empty($discount['aciklama']) 
                        ? htmlspecialchars($discount['aciklama'])
                        : 'Açıklama yok'; ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Ürün arama ve ekleme formu -->
    <div class="bg-white shadow-md rounded-lg p-6 mb-6">
        <h2 class="text-lg font-semibold mb-4">İndirime Ürün Ekle</h2>
        
        <form id="addProductsForm" method="POST" action="discount_detail.php?id=<?php echo $discount_id; ?>">
            <!-- Ürün arama kutusu -->
            <div class="mb-4">
                <label for="productSearch" class="block text-sm font-medium text-gray-700 mb-1">
                    Ürün Ara
                </label>
                <input type="text" 
                       id="productSearch" 
                       placeholder="En az 3 karakter girerek ürün adı, kodu veya barkod ile arayın" 
                       class="w-full px-4 py-2 border rounded-md shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
            </div>
            
            <!-- Arama sonuçları -->
            <div id="searchResults" class="border rounded-md mt-2 mb-4 max-h-60 overflow-y-auto">
                <div class="p-3 text-gray-500 text-center">Arama yapmak için en az 3 karakter girin</div>
            </div>
            
            <!-- Seçilen ürünler -->
            <div id="selectedProducts" class="mb-4">
                <!-- JavaScript ile doldurulacak -->
            </div>
            
            <!-- Gönder butonu -->
            <div class="flex justify-end">
                <button type="submit" id="submitProductsBtn" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded" disabled>
                    Seçili Ürünleri Ekle
                </button>
            </div>
        </form>
    </div>

    <!-- İndirimli ürün listesi -->
    <div class="bg-white shadow-md rounded-lg p-6">
        <h2 class="text-lg font-semibold mb-4">
            İndirimli Ürünler (<?php echo count($discount_details); ?>)
        </h2>
        
        <?php if (empty($discount_details)): ?>
            <div class="py-4 text-center text-gray-500">
                <p>Bu indirime henüz ürün eklenmemiş.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Ürün Adı
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Barkod
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Normal Fiyat
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                İndirimli Fiyat
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                İndirim Oranı
                            </th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                İşlemler
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($discount_details as $detail): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php echo htmlspecialchars($detail['urun_adi']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php echo htmlspecialchars($detail['barkod'] ?? 'Belirtilmemiş'); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    ₺<?php echo number_format($detail['eski_fiyat'], 2, ',', '.'); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    ₺<?php echo number_format($detail['indirimli_fiyat'], 2, ',', '.'); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <?php 
                                    if ($detail['eski_fiyat'] > 0) {
                                        $discount_percent = 100 - (($detail['indirimli_fiyat'] / $detail['eski_fiyat']) * 100);
                                        echo '%' . number_format($discount_percent, 2, ',', '.');
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <form method="POST" action="discount_detail.php?id=<?php echo $discount_id; ?>" 
                                          class="inline-block">
                                        <input type="hidden" name="remove_product" value="1">
                                        <input type="hidden" name="detail_id" value="<?php echo $detail['id']; ?>">
                                        <button type="submit" 
                                                class="text-red-600 hover:text-red-900"
                                                onclick="return confirm('Bu ürünü indirimden kaldırmak istediğinize emin misiniz?');">
                                            Kaldır
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- JavaScript -->
<script>
    // Ürün arama işlemi
    let searchTimeout;
    const productSearch = document.getElementById('productSearch');
    const searchResults = document.getElementById('searchResults');
    const selectedProducts = document.getElementById('selectedProducts');
    let selectedProductIds = [];
    
    document.addEventListener('DOMContentLoaded', function() {
        console.log("DOM yüklendi, arama elementleri:", {
            productSearch: !!productSearch,
            searchResults: !!searchResults,
            selectedProducts: !!selectedProducts
        });
        
        if (productSearch) {
            productSearch.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                
                const query = this.value.trim();
                if (query.length < 3) {
                    searchResults.innerHTML = '<div class="p-3 text-gray-500 text-center">En az 3 karakter girin</div>';
                    return;
                }
                
                searchTimeout = setTimeout(() => {
                    searchProducts(query);
                }, 500);
            });
        }
    });
    
    // Ürün arama fonksiyonu
    function searchProducts(query) {
        console.log("Ürün aranıyor:", query);
        searchResults.innerHTML = '<div class="p-3 text-gray-500 text-center">Aranıyor...</div>';
        
        fetch('api/search_products_discount.php?q=' + encodeURIComponent(query))
            .then(response => {
                console.log("API yanıtı:", response);
                return response.json();
            })
            .then(data => {
                console.log("Bulunan ürünler:", data);
                if (data.length === 0) {
                    searchResults.innerHTML = '<div class="p-3 text-gray-500 text-center">Sonuç bulunamadı</div>';
                    return;
                }
                
                let html = '<div class="grid gap-2">';
                data.forEach(product => {
                    // Zaten seçilmiş ürünleri kontrol et
                    const isSelected = selectedProductIds.includes(product.id.toString());
                    const isInDiscount = <?php echo json_encode(array_column($discount_details, 'urun_id')); ?>.includes(product.id.toString());
                    
                    if (!isInDiscount) {
                        html += `
                        <div class="flex items-center justify-between border-b p-2 hover:bg-gray-50">
                            <div class="flex items-center">
                                <input type="checkbox" id="product-${product.id}" 
                                       ${isSelected ? 'checked' : ''} 
                                       class="product-checkbox mr-2 h-4 w-4 rounded border-gray-300" 
                                       onchange="toggleProduct(${product.id}, '${product.ad.replace(/'/g, "\\'")}', ${product.satis_fiyati})" />
                                <label for="product-${product.id}" class="cursor-pointer">
                                    <div><strong>${product.ad}</strong></div>
                                    <div class="text-xs text-gray-600">Barkod: ${product.barkod || 'Yok'} | Fiyat: ₺${product.satis_fiyati}</div>
                                </label>
                            </div>
                        </div>
                        `;
                    }
                });
                html += '</div>';
                
                searchResults.innerHTML = html;
            })
            .catch(error => {
                console.error('Arama hatası:', error);
                searchResults.innerHTML = '<div class="p-3 text-red-500 text-center">Arama sırasında bir hata oluştu</div>';
            });
    }
    
    // Ürün seçme fonksiyonu
    function toggleProduct(productId, productName, productPrice) {
        console.log("Ürün seçme/kaldırma:", productId, productName, productPrice);
        const productIdStr = productId.toString();
        const checkbox = document.getElementById('product-' + productId);
        
        if (checkbox && checkbox.checked) {
            // Ürün seçildi
            if (!selectedProductIds.includes(productIdStr)) {
                selectedProductIds.push(productIdStr);
                
                // Gizli input ekle
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'product_ids[]';
                input.value = productId;
                input.id = 'selected-product-' + productId;
                selectedProducts.appendChild(input);
                
                // Seçilen ürün bilgisini göster
                const productInfo = document.createElement('div');
                productInfo.id = 'product-info-' + productId;
                productInfo.className = 'flex justify-between items-center p-2 bg-blue-50 rounded mb-2';
                productInfo.innerHTML = `
                    <div>
                        <div class="font-medium">${productName}</div>
                        <div class="text-xs text-gray-600">Normal Fiyat: ₺${productPrice.toFixed(2)}</div>
                    </div>
                    <button type="button" class="text-red-600 hover:text-red-900" onclick="removeProduct(${productId})">
                        Kaldır
                    </button>
                `;
                selectedProducts.appendChild(productInfo);
            }
        } else {
            // Ürün seçimi kaldırıldı
            removeProduct(productId);
        }
        
        // Seçili ürün sayısını güncelle
        updateSelectedCount();
    }
    
    // Ürün kaldırma fonksiyonu
    function removeProduct(productId) {
        console.log("Ürün kaldırılıyor:", productId);
        const productIdStr = productId.toString();
        const index = selectedProductIds.indexOf(productIdStr);
        
        if (index !== -1) {
            selectedProductIds.splice(index, 1);
            
            // Gizli input'u kaldır
            const input = document.getElementById('selected-product-' + productId);
            if (input) input.remove();
            
            // Ürün bilgisini kaldır
            const productInfo = document.getElementById('product-info-' + productId);
            if (productInfo) productInfo.remove();
            
            // Checkbox'ı işaretsiz yap
            const checkbox = document.getElementById('product-' + productId);
            if (checkbox) checkbox.checked = false;
        }
        
        // Seçili ürün sayısını güncelle
        updateSelectedCount();
    }
    
    // Seçili ürün sayısını güncelle
    function updateSelectedCount() {
        const count = selectedProductIds.length;
        const submitButton = document.getElementById('submitProductsBtn');
        
        if (count > 0) {
            submitButton.textContent = `${count} Ürünü Ekle`;
            submitButton.disabled = false;
        } else {
            submitButton.textContent = `Seçili Ürünleri Ekle`;
            submitButton.disabled = true;
        }
        
        console.log("Seçili ürün sayısı güncellendi:", count);
    }
</script>

<?php
// Footer'ı dahil et
include 'footer.php';
?>