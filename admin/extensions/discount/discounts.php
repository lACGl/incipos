<?php
// Sayfa yüklenme süresini hesapla
$start_time = microtime(true);

// Header'ı dahil et
include '../../header.php';
require_once '../../db_connection.php';

// Kullanıcı giriş kontrolü
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: index.php");
    exit;
}

// İndirim Ekleme
if (isset($_POST['add_discount'])) {
    $discount_name = $_POST['discount_name'];
    $discount_type = $_POST['discount_type'];
    $discount_value = $_POST['discount_value'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $description = $_POST['description'];
    $application_type = $_POST['application_type'];
    $filter_value = isset($_POST['filter_value']) ? $_POST['filter_value'] : null;
    
    // Filtre değerini doğru formatta sakla
    if ($application_type === 'secili' && isset($_POST['selected_products'])) {
        $filter_value = implode(',', $_POST['selected_products']);
    } elseif ($application_type === 'departman' && isset($_POST['department_id'])) {
        $filter_value = $_POST['department_id'];
    } elseif ($application_type === 'ana_grup' && isset($_POST['main_group_id'])) {
        $filter_value = $_POST['main_group_id'];
    }
    
    try {
        $stmt = $conn->prepare("INSERT INTO indirimler (ad, indirim_turu, indirim_degeri, baslangic_tarihi, bitis_tarihi, 
                                aciklama, uygulama_turu, filtre_degeri, kullanici_id) 
                                VALUES (:ad, :indirim_turu, :indirim_degeri, :baslangic_tarihi, :bitis_tarihi, 
                                :aciklama, :uygulama_turu, :filtre_degeri, :kullanici_id)");
        
        $stmt->bindParam(':ad', $discount_name);
        $stmt->bindParam(':indirim_turu', $discount_type);
        $stmt->bindParam(':indirim_degeri', $discount_value);
        $stmt->bindParam(':baslangic_tarihi', $start_date);
        $stmt->bindParam(':bitis_tarihi', $end_date);
        $stmt->bindParam(':aciklama', $description);
        $stmt->bindParam(':uygulama_turu', $application_type);
        $stmt->bindParam(':filtre_degeri', $filter_value);
        $stmt->bindParam(':kullanici_id', $_SESSION['user_id']);
        
        $stmt->execute();
        $discount_id = $conn->lastInsertId();
        
        // Eğer hemen uygulanacaksa
        if (isset($_POST['apply_immediately']) && $_POST['apply_immediately'] == 1) {
            $success = applyDiscount($conn, $discount_id, $application_type, $filter_value);
            if ($success) {
                $success_message = "İndirim başarıyla oluşturuldu ve uygulandı.";
            } else {
                $success_message = "İndirim oluşturuldu fakat uygulama sırasında hata oluştu.";
            }
        } else {
            $success_message = "İndirim başarıyla oluşturuldu.";
        }
    } catch (PDOException $e) {
        $error_message = "İndirim oluşturulurken hata oluştu: " . $e->getMessage();
    }
}

// İndirimi Aktif/Pasif Yap
if (isset($_POST['toggle_discount_status'])) {
    $discount_id = $_POST['discount_id'];
    $new_status = $_POST['new_status'];
    
    try {
        $stmt = $conn->prepare("UPDATE indirimler SET durum = :durum WHERE id = :id");
        $stmt->bindParam(':durum', $new_status);
        $stmt->bindParam(':id', $discount_id);
        $stmt->execute();
        
        // Eğer pasife alınıyorsa, indirimli fiyatları kaldır
        if ($new_status === 'pasif') {
            removeDiscountFromProducts($conn, $discount_id);
            $success_message = "İndirim pasife alındı ve ürünlerden kaldırıldı.";
        } else {
            $success_message = "İndirim durumu başarıyla güncellendi.";
        }
    } catch (PDOException $e) {
        $error_message = "İndirim durumu güncellenirken hata oluştu: " . $e->getMessage();
    }
}

// İndirimi Sil
if (isset($_POST['delete_discount'])) {
    $discount_id = $_POST['discount_id'];
    
    try {
        // Önce ürünlerden indirimi kaldır
        removeDiscountFromProducts($conn, $discount_id);
        
        // Sonra indirim detaylarını sil
        $stmt = $conn->prepare("DELETE FROM indirim_detay WHERE indirim_id = :id");
        $stmt->bindParam(':id', $discount_id);
        $stmt->execute();
        
        // En son indirimi sil
        $stmt = $conn->prepare("DELETE FROM indirimler WHERE id = :id");
        $stmt->bindParam(':id', $discount_id);
        $stmt->execute();
        
        $success_message = "İndirim başarıyla silindi.";
    } catch (PDOException $e) {
        $error_message = "İndirim silinirken hata oluştu: " . $e->getMessage();
    }
}

// İndirim Uygulama (yeni eklenen)
// İndirim Uygulama (yeni eklenen)
if (isset($_POST['apply_discount_form'])) {
    $discount_id = $_POST['discount_id'];
    $application_type = $_POST['application_type'];
    $filter_value = null;
    
    try {
        if ($application_type === 'secili' && isset($_POST['selected_products'])) {
            $filter_value = $_POST['selected_products'];
        } elseif ($application_type === 'departman' && isset($_POST['department_id'])) {
            $filter_value = $_POST['department_id'];
        } elseif ($application_type === 'ana_grup' && isset($_POST['main_group_id'])) {
            $filter_value = $_POST['main_group_id'];
        }
        
        $success = applyDiscount($conn, $discount_id, $application_type, $filter_value);
        
        if ($success) {
            $success_message = "İndirim başarıyla uygulandı.";
        } else {
            $error_message = "İndirim uygulanırken hata oluştu." . 
                (isset($_SESSION['error_detail']) ? " Detay: " . $_SESSION['error_detail'] : "");
            unset($_SESSION['error_detail']);
        }
    } catch (Exception $e) {
        $error_message = "İndirim uygulanırken beklenmeyen bir hata oluştu: " . $e->getMessage();
    }
}

// İndirimi Kaldırma (yeni eklenen)
if (isset($_POST['remove_discount_form'])) {
    $discount_id = $_POST['discount_id'];
    
    $success = removeDiscountFromProducts($conn, $discount_id);
    
    if ($success) {
        $success_message = "İndirim başarıyla kaldırıldı.";
    } else {
        $error_message = "İndirim kaldırılırken hata oluştu.";
    }
}

// İndirimleri Listele
$discounts_query = "SELECT i.*, COUNT(id.urun_id) as urun_sayisi 
                    FROM indirimler i 
                    LEFT JOIN indirim_detay id ON i.id = id.indirim_id 
                    GROUP BY i.id 
                    ORDER BY i.olusturulma_tarihi DESC";
$discounts = $conn->query($discounts_query)->fetchAll(PDO::FETCH_ASSOC);

// Departmanları Getir
$departments_query = "SELECT * FROM departmanlar ORDER BY ad";
$departments = $conn->query($departments_query)->fetchAll(PDO::FETCH_ASSOC);

// Ana Grupları Getir
$main_groups_query = "SELECT * FROM ana_gruplar ORDER BY ad";
$main_groups = $conn->query($main_groups_query)->fetchAll(PDO::FETCH_ASSOC);

// İşlem fonksiyonları

/**
 * İndirimi uygula
 * @param PDO $conn Veritabanı bağlantısı
 * @param int $discount_id İndirim ID
 * @param string $application_type Uygulama türü (tum, secili, departman, ana_grup)
 * @param mixed $filter_value Filtre değeri
 * @return bool İşlem başarılı mı?
 */
function applyDiscount($conn, $discount_id, $application_type, $filter_value) {
    try {
        // İndirim bilgilerini al
        $stmt = $conn->prepare("SELECT * FROM indirimler WHERE id = :id");
        $stmt->bindParam(':id', $discount_id);
        $stmt->execute();
        $discount = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$discount) {
            return false;
        }
        
        // Ürün ID'lerini al
        $product_ids = [];
        
        switch ($application_type) {
            case 'tum':
                // Tüm ürünleri al
                $product_query = "SELECT id FROM urun_stok WHERE durum = 'aktif'";
                $stmt = $conn->query($product_query);
                $product_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
                break;
                
            case 'secili':
                // Seçili ürünleri al
                if (is_array($filter_value)) {
                    // POST'tan gelen dizi
                    $product_ids = $filter_value;
                } elseif (is_string($filter_value) && !empty($filter_value)) {
                    // Virgülle ayrılmış ID'ler
                    $product_ids = explode(',', $filter_value);
                }
                break;
                
            case 'departman':
                // Departmana göre ürünleri al
                $product_query = "SELECT id FROM urun_stok WHERE durum = 'aktif' AND departman_id = :departman_id";
                $stmt = $conn->prepare($product_query);
                $stmt->bindParam(':departman_id', $filter_value);
                $stmt->execute();
                $product_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
                break;
                
            case 'ana_grup':
                // Ana gruba göre ürünleri al
                $product_query = "SELECT id FROM urun_stok WHERE durum = 'aktif' AND ana_grup_id = :ana_grup_id";
                $stmt = $conn->prepare($product_query);
                $stmt->bindParam(':ana_grup_id', $filter_value);
                $stmt->execute();
                $product_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
                break;
        }
        
        // Ürün ID'leri boşsa işlem yapma
        if (empty($product_ids)) {
            return false;
        }
        
        // İşlem başla
        $conn->beginTransaction();
        
        // İndirim durumunu aktif yap
        $update_discount = "UPDATE indirimler SET durum = 'aktif' WHERE id = :id";
        $stmt = $conn->prepare($update_discount);
        $stmt->bindParam(':id', $discount_id);
        $stmt->execute();
        
        // Hata ve uyarı mesajları
        $skipped_products = 0;
        $applied_count = 0;
        
        // Ürünlere indirim uygula
        foreach ($product_ids as $product_id) {
            // Ürün bilgilerini al
            $product_query = "SELECT id, ad, satis_fiyati, indirimli_fiyat FROM urun_stok WHERE id = :id";
            $stmt = $conn->prepare($product_query);
            $stmt->bindParam(':id', $product_id);
            $stmt->execute();
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($product) {
                // Ürün zaten indirimli mi kontrol et
                if (!is_null($product['indirimli_fiyat'])) {
                    // Eğer zaten bu indirime sahipse, atla
                    $check_existing = "SELECT id FROM indirim_detay 
                                      WHERE indirim_id = :indirim_id AND urun_id = :urun_id";
                    $stmt = $conn->prepare($check_existing);
                    $stmt->bindParam(':indirim_id', $discount_id);
                    $stmt->bindParam(':urun_id', $product_id);
                    $stmt->execute();
                    
                    if ($stmt->fetch()) {
                        // Bu ürüne zaten bu indirim uygulanmış
                        continue;
                    }
                    
                    // Ürün başka bir indirime sahip, atla
                    $skipped_products++;
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
                
                $applied_count++;
            }
        }
        
        $conn->commit();
        
        // Bilgi mesajı döndür
        if ($skipped_products > 0) {
            $_SESSION['warning_message'] = "$skipped_products ürün halihazırda indirimli olduğu için atlandı.";
        }
        
        return $applied_count > 0;
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        error_log("İndirim uygulama hatası: " . $e->getMessage());
        
        // Hatanın detayını görmek için
        $_SESSION['error_detail'] = $e->getMessage(); 
        return false;
    }
}

/**
 * Ürünlerden indirimi kaldır
 * @param PDO $conn Veritabanı bağlantısı
 * @param int $discount_id İndirim ID
 * @return bool İşlem başarılı mı?
 */
function removeDiscountFromProducts($conn, $discount_id) {
    try {
        // İndirim detaylarını al
        $stmt = $conn->prepare("SELECT urun_id FROM indirim_detay WHERE indirim_id = :id");
        $stmt->bindParam(':id', $discount_id);
        $stmt->execute();
        $product_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($product_ids)) {
            return true; // Kaldırılacak bir şey yok
        }
        
        $conn->beginTransaction();
        
        // Ürünlerden indirimi kaldır
        foreach ($product_ids as $product_id) {
            $update_product = "UPDATE urun_stok SET 
                            indirimli_fiyat = NULL,
                            indirim_baslangic_tarihi = NULL,
                            indirim_bitis_tarihi = NULL
                            WHERE id = :id";
            $stmt = $conn->prepare($update_product);
            $stmt->bindParam(':id', $product_id);
            $stmt->execute();
        }
        
        // İndirim detaylarını sil
        $stmt = $conn->prepare("DELETE FROM indirim_detay WHERE indirim_id = :id");
        $stmt->bindParam(':id', $discount_id);
        $stmt->execute();
        
        $conn->commit();
        return true;
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        error_log("İndirim kaldırma hatası: " . $e->getMessage());
        return false;
    }
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
    <title>İndirim Yönetimi</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <!-- Başlık ve Buton -->
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold">İndirim Yönetimi</h1>
                <p class="text-gray-600">İndirimleri oluşturun, yönetin ve uygulayın</p>
            </div>
            <button id="openAddDiscountModal" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                <svg class="w-5 h-5 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                </svg>
                Yeni İndirim Ekle
            </button>
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

        <!-- İndirimler Tablosu -->
        <div class="bg-white shadow-md rounded-lg overflow-hidden mb-6">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">İndirim Adı</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tip</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Değer</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Geçerlilik Tarihi</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Uygulama Kapsamı</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Ürün Sayısı</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Durum</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">İşlemler</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($discounts)): ?>
                        <tr>
                            <td colspan="8" class="px-6 py-4 text-center text-gray-500">Henüz indirim tanımlanmamış.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($discounts as $discount): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($discount['ad']); ?></div>
                                    <?php if (!empty($discount['aciklama'])): ?>
                                        <div class="text-xs text-gray-500"><?php echo htmlspecialchars(substr($discount['aciklama'], 0, 50)) . (strlen($discount['aciklama']) > 50 ? '...' : ''); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $discount['indirim_turu'] === 'yuzde' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800'; ?>">
                                        <?php echo $discount['indirim_turu'] === 'yuzde' ? 'Yüzde' : 'Tutar'; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php 
                                    if ($discount['indirim_turu'] === 'yuzde') {
                                        echo '%' . number_format($discount['indirim_degeri'], 2, ',', '.');
                                    } else {
                                        echo '₺' . number_format($discount['indirim_degeri'], 2, ',', '.');
                                    }
                                    ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php 
                                    echo date('d.m.Y', strtotime($discount['baslangic_tarihi'])) . ' - ' . date('d.m.Y', strtotime($discount['bitis_tarihi']));
                                    
                                    $today = date('Y-m-d');
                                    if ($today < $discount['baslangic_tarihi']) {
                                        echo ' <span class="text-xs text-yellow-600">(Gelecekte başlayacak)</span>';
                                    } elseif ($today > $discount['bitis_tarihi']) {
                                        echo ' <span class="text-xs text-red-600">(Süresi dolmuş)</span>';
                                    } else {
                                        echo ' <span class="text-xs text-green-600">(Aktif)</span>';
                                    }
                                    ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
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
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-center text-gray-500">
                                    <?php echo $discount['urun_sayisi']; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $discount['durum'] === 'aktif' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo $discount['durum'] === 'aktif' ? 'Aktif' : 'Pasif'; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <button onclick="showDiscountDetails(<?php echo $discount['id']; ?>)" class="text-indigo-600 hover:text-indigo-900 mr-2">
                                        Detaylar
                                    </button>
                                    
                                    <?php if ($discount['durum'] === 'aktif'): ?>
                                        <button onclick="showApplyDiscountModal(<?php echo $discount['id']; ?>)" class="text-green-600 hover:text-green-900 mr-2">
                                            Uygula
                                        </button>
                                        <button onclick="toggleDiscountStatus(<?php echo $discount['id']; ?>, 'pasif')" class="text-yellow-600 hover:text-yellow-900 mr-2">
                                            Pasife Al
                                        </button>
                                    <?php else: ?>
                                        <button onclick="showApplyDiscountModal(<?php echo $discount['id']; ?>)" class="text-green-600 hover:text-green-900 mr-2">
                                            Uygula
                                        </button>
                                        <button onclick="toggleDiscountStatus(<?php echo $discount['id']; ?>, 'aktif')" class="text-green-600 hover:text-green-900 mr-2">
                                            Aktifleştir
                                        </button>
                                    <?php endif; ?>
                                    
                                    <button onclick="deleteDiscount(<?php echo $discount['id']; ?>)" class="text-red-600 hover:text-red-900">
                                        Sil
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Yeni İndirim Ekleme Modal -->
    <div id="addDiscountModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-lg shadow-lg max-w-3xl w-full p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold">Yeni İndirim Ekle</h3>
                <button type="button" class="text-gray-400 hover:text-gray-600" onclick="closeAddDiscountModal()">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <form action="" method="POST" id="discountForm">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="discount_name" class="block text-sm font-medium text-gray-700 mb-1">İndirim Adı</label>
                        <input type="text" id="discount_name" name="discount_name" required
                               class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">İndirim Türü</label>
                        <div class="flex space-x-4">
                            <label class="inline-flex items-center">
							<input type="radio" name="discount_type" value="yuzde" checked
                                       class="form-radio h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-500">
                                <span class="ml-2 text-gray-700">Yüzde (%)</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="radio" name="discount_type" value="tutar"
                                       class="form-radio h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-500">
                                <span class="ml-2 text-gray-700">Tutar (₺)</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="discount_value" class="block text-sm font-medium text-gray-700 mb-1">İndirim Değeri</label>
                        <input type="number" id="discount_value" name="discount_value" min="0" step="0.01" required
                               class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        <p class="mt-1 text-xs text-gray-500" id="valueHint">Yüzde olarak giriniz (örn: 10 = %10 indirim)</p>
                    </div>

                    <div>
                        <label for="application_type" class="block text-sm font-medium text-gray-700 mb-1">Uygulama Kapsamı</label>
                        <select id="application_type" name="application_type" required onchange="showFilterOptions()"
                                class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="tum">Tüm Ürünler</option>
                            <option value="secili">Seçili Ürünler</option>
                            <option value="departman">Departman</option>
                            <option value="ana_grup">Ana Grup</option>
                        </select>
                    </div>
                </div>

                <!-- Filtre Seçenekleri (JavaScript ile gösterilecek) -->
                <div id="departmentFilter" class="mb-4 hidden">
                    <label for="department_id" class="block text-sm font-medium text-gray-700 mb-1">Departman Seçin</label>
                    <select id="department_id" name="department_id" 
                            class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        <?php foreach ($departments as $department): ?>
                            <option value="<?php echo $department['id']; ?>"><?php echo htmlspecialchars($department['ad']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="mainGroupFilter" class="mb-4 hidden">
                    <label for="main_group_id" class="block text-sm font-medium text-gray-700 mb-1">Ana Grup Seçin</label>
                    <select id="main_group_id" name="main_group_id" 
                            class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        <?php foreach ($main_groups as $group): ?>
                            <option value="<?php echo $group['id']; ?>"><?php echo htmlspecialchars($group['ad']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="selectedProductsFilter" class="mb-4 hidden">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ürün Seçin</label>
                    <p class="text-sm text-gray-500 mb-2">Önce indirim oluşturun, sonra ürünler eklenecektir.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Başlangıç Tarihi</label>
                        <input type="date" id="start_date" name="start_date" required
                               class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <div>
                        <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">Bitiş Tarihi</label>
                        <input type="date" id="end_date" name="end_date" required
                               class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>

                <div class="mb-4">
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Açıklama</label>
                    <textarea id="description" name="description" rows="3"
                              class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"></textarea>
                </div>

                <div class="mb-4">
                    <label class="flex items-center">
                        <input type="checkbox" name="apply_immediately" value="1"
                               class="form-checkbox h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-500">
                        <span class="ml-2 text-gray-700">Hemen Uygula</span>
                    </label>
                    <p class="mt-1 text-xs text-gray-500">İşaretlerseniz, indirim oluşturulduğunda otomatik olarak uygulanacaktır.</p>
                </div>

                <div class="flex justify-end">
                    <button type="button" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded mr-2" onclick="closeAddDiscountModal()">
                        İptal
                    </button>
                    <button type="submit" name="add_discount" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">
                        İndirim Oluştur
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- İndirim Uygulama Modal -->
    <div id="applyDiscountModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-lg shadow-lg max-w-2xl w-full p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold">İndirim Uygula</h3>
                <button type="button" class="text-gray-400 hover:text-gray-600" onclick="closeApplyDiscountModal()">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <form action="" method="POST" id="applyDiscountForm">
                <input type="hidden" name="apply_discount_form" value="1">
                <input type="hidden" name="discount_id" id="apply_discount_id">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Uygulama Kapsamı</label>
                    <div class="space-y-2">
                        <label class="inline-flex items-center">
                            <input type="radio" name="application_type" value="tum" checked
                                   class="form-radio h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-500"
                                   onclick="toggleApplyScope('tum')">
                            <span class="ml-2 text-gray-700">Tüm Ürünler</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" name="application_type" value="secili"
                                   class="form-radio h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-500"
                                   onclick="toggleApplyScope('secili')">
                            <span class="ml-2 text-gray-700">Seçili Ürünler</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" name="application_type" value="departman"
                                   class="form-radio h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-500"
                                   onclick="toggleApplyScope('departman')">
                            <span class="ml-2 text-gray-700">Departman</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" name="application_type" value="ana_grup"
                                   class="form-radio h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-500"
                                   onclick="toggleApplyScope('ana_grup')">
                            <span class="ml-2 text-gray-700">Ana Grup</span>
                        </label>
                    </div>
                </div>
                
                <!-- Filtre Alanları -->
                <div id="applyDepartmentFilter" class="mb-4 hidden">
                    <label for="apply_department_id" class="block text-sm font-medium text-gray-700 mb-1">Departman Seçin</label>
                    <select id="apply_department_id" name="department_id" 
                            class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        <?php foreach ($departments as $department): ?>
                            <option value="<?php echo $department['id']; ?>"><?php echo htmlspecialchars($department['ad']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div id="applyMainGroupFilter" class="mb-4 hidden">
                    <label for="apply_main_group_id" class="block text-sm font-medium text-gray-700 mb-1">Ana Grup Seçin</label>
                    <select id="apply_main_group_id" name="main_group_id" 
                            class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        <?php foreach ($main_groups as $group): ?>
                            <option value="<?php echo $group['id']; ?>"><?php echo htmlspecialchars($group['ad']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div id="applyProductsSearch" class="mb-4 hidden">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ürün Ara</label>
                    <input type="text" id="productSearchInput" placeholder="Barkod veya ürün adı ile ara..."
                           class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    
                    <div id="productSearchResults" class="mt-2 max-h-60 overflow-y-auto border rounded-md"></div>
                    
                    <div id="selectedProductsContainer" class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Seçili Ürünler (<span id="selectedProductCount">0</span>)</label>
                        <div id="selectedProductsList" class="border rounded-md p-2 min-h-[100px]"></div>
                    </div>
                </div>
                
                <div class="flex justify-end mt-6">
                    <button type="button" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded mr-2" onclick="closeApplyDiscountModal()">
                        İptal
                    </button>
                    <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">
                        İndirimi Uygula
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Toggle Status Form (hidden) -->
    <form id="toggleStatusForm" method="POST" class="hidden">
        <input type="hidden" name="toggle_discount_status" value="1">
        <input type="hidden" name="discount_id" id="toggle_discount_id">
        <input type="hidden" name="new_status" id="toggle_new_status">
    </form>

    <!-- Delete Form (hidden) -->
    <form id="deleteForm" method="POST" class="hidden">
        <input type="hidden" name="delete_discount" value="1">
        <input type="hidden" name="discount_id" id="delete_discount_id">
    </form>

    <script>
        // İndirim ekleme modal işlemleri
        function openAddDiscountModal() {
            document.getElementById('addDiscountModal').classList.remove('hidden');
        }
        
        function closeAddDiscountModal() {
            document.getElementById('addDiscountModal').classList.add('hidden');
        }
        
        document.getElementById('openAddDiscountModal').addEventListener('click', openAddDiscountModal);
        
        // Filtre seçeneklerini göster/gizle
        function showFilterOptions() {
            const applicationType = document.getElementById('application_type').value;
            
            // Tüm filtre alanlarını gizle
            document.getElementById('departmentFilter').classList.add('hidden');
            document.getElementById('mainGroupFilter').classList.add('hidden');
            document.getElementById('selectedProductsFilter').classList.add('hidden');
            
            // Seçilen filtre türüne göre ilgili alanı göster
            if (applicationType === 'departman') {
                document.getElementById('departmentFilter').classList.remove('hidden');
            } else if (applicationType === 'ana_grup') {
                document.getElementById('mainGroupFilter').classList.remove('hidden');
            } else if (applicationType === 'secili') {
                document.getElementById('selectedProductsFilter').classList.remove('hidden');
            }
        }
        
        // İndirim uygulama modal işlemleri
        function showApplyDiscountModal(discountId) {
            document.getElementById('apply_discount_id').value = discountId;
            document.getElementById('applyDiscountModal').classList.remove('hidden');
        }
        
        function closeApplyDiscountModal() {
            document.getElementById('applyDiscountModal').classList.add('hidden');
        }
        
        // Uygulama kapsamı değiştirme
        function toggleApplyScope(scope) {
            // Tüm filtre alanlarını gizle
            document.getElementById('applyDepartmentFilter').classList.add('hidden');
            document.getElementById('applyMainGroupFilter').classList.add('hidden');
            document.getElementById('applyProductsSearch').classList.add('hidden');
            
            // Seçilen kapsama göre ilgili alanı göster
            if (scope === 'departman') {
                document.getElementById('applyDepartmentFilter').classList.remove('hidden');
            } else if (scope === 'ana_grup') {
                document.getElementById('applyMainGroupFilter').classList.remove('hidden');
            } else if (scope === 'secili') {
                document.getElementById('applyProductsSearch').classList.remove('hidden');
                initProductSearch();
            }
        }
        
        // İndirim türü değiştiğinde ipucu metnini güncelle
        document.querySelectorAll('input[name="discount_type"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const valueHint = document.getElementById('valueHint');
                if (this.value === 'yuzde') {
                    valueHint.textContent = 'Yüzde olarak giriniz (örn: 10 = %10 indirim)';
                } else {
                    valueHint.textContent = 'Tutar olarak giriniz (örn: 50 = 50₺ indirim)';
                }
            });
        });
        
        // Tarih kontrolü
        document.getElementById('discountForm').addEventListener('submit', function(event) {
            const startDate = new Date(document.getElementById('start_date').value);
            const endDate = new Date(document.getElementById('end_date').value);
            
            if (endDate < startDate) {
                event.preventDefault();
                alert('Bitiş tarihi başlangıç tarihinden önce olamaz');
            }
        });
        
        // İndirim durumunu değiştir
        function toggleDiscountStatus(discountId, newStatus) {
            if (confirm('İndirim durumunu ' + (newStatus === 'aktif' ? 'aktifleştirmek' : 'pasifleştirmek') + ' istediğinize emin misiniz?')) {
                document.getElementById('toggle_discount_id').value = discountId;
                document.getElementById('toggle_new_status').value = newStatus;
                document.getElementById('toggleStatusForm').submit();
            }
        }
        
        // İndirimi sil
        function deleteDiscount(discountId) {
            if (confirm('Bu indirimi silmek istediğinize emin misiniz? Bu işlem geri alınamaz.')) {
                document.getElementById('delete_discount_id').value = discountId;
                document.getElementById('deleteForm').submit();
            }
        }
        
        // İndirim detaylarını göster
        function showDiscountDetails(discountId) {
            window.location.href = 'discount_detail.php?id=' + discountId;
        }
        
        // Ürün arama işlevi
        function initProductSearch() {
            const searchInput = document.getElementById('productSearchInput');
            const resultsContainer = document.getElementById('productSearchResults');
            const selectedProducts = [];
            
            if (!searchInput) return;
            
            let searchTimeout;
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                
                const query = this.value.trim();
                if (query.length < 3) {
                    resultsContainer.innerHTML = '<div class="p-3 text-center text-gray-500">En az 3 karakter girin</div>';
                    return;
                }
                
                searchTimeout = setTimeout(() => {
                    resultsContainer.innerHTML = '<div class="p-3 text-center text-gray-500">Aranıyor...</div>';
                    
                    fetch('../../api/search_products_discount.php?q=' + encodeURIComponent(query))
                        .then(response => response.json())
                        .then(data => {
                            if (data.length === 0) {
                                resultsContainer.innerHTML = '<div class="p-3 text-center text-gray-500">Sonuç bulunamadı</div>';
                                return;
                            }
                            
                            let html = '<div class="divide-y">';
                            data.forEach(product => {
                                const isSelected = selectedProducts.some(p => p.id === product.id);
                                
                                html += `
                                <div class="p-2 hover:bg-gray-100 flex justify-between items-center">
                                    <div>
                                        <div class="font-medium">${product.ad}</div>
                                        <div class="text-xs text-gray-600">Barkod: ${product.barkod} | Fiyat: ₺${product.satis_fiyati}</div>
                                    </div>
                                    <button type="button" 
                                        class="text-blue-500 hover:text-blue-700 px-2 py-1 text-sm"
                                        onclick="selectProduct(${product.id}, '${product.ad}', '${product.barkod}', ${product.satis_fiyati})">
                                        Seç
                                    </button>
                                </div>
                                `;
                            });
                            html += '</div>';
                            
                            resultsContainer.innerHTML = html;
                        })
                        .catch(error => {
                            console.error('Arama hatası:', error);
                            resultsContainer.innerHTML = '<div class="p-3 text-center text-red-500">Hata oluştu</div>';
                        });
                }, 500);
            });
            
            // Ürün seçme
            window.selectProduct = function(id, name, barcode, price) {
                if (!selectedProducts.some(p => p.id === id)) {
                    const product = { id, name, barcode, price };
                    selectedProducts.push(product);
                    
                    // Hidden input ekle
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'selected_products[]';
                    input.value = id;
                    document.getElementById('applyDiscountForm').appendChild(input);
                    
                    // Seçili ürünler listesini güncelle
                    updateSelectedProductsList();
                }
            };
            
            // Ürün kaldırma
            window.removeSelectedProduct = function(id) {
                const index = selectedProducts.findIndex(p => p.id === id);
                if (index !== -1) {
                    selectedProducts.splice(index, 1);
                    
                    // Hidden input'u kaldır
                    const inputs = document.querySelectorAll('input[name="selected_products[]"]');
                    inputs.forEach(input => {
                        if (input.value == id) input.remove();
                    });
                    
                    // Seçili ürünler listesini güncelle
                    updateSelectedProductsList();
                }
            };
            
            // Seçili ürünler listesini güncelle
            function updateSelectedProductsList() {
                const container = document.getElementById('selectedProductsList');
                const countElement = document.getElementById('selectedProductCount');
                
                if (container) {
                    if (selectedProducts.length === 0) {
                        container.innerHTML = '<div class="text-center text-gray-500 py-2">Henüz ürün seçilmedi</div>';
                    } else {
                        let html = '';
                        selectedProducts.forEach(product => {
                            html += `
                            <div class="flex justify-between items-center p-2 border-b">
                                <div>
                                    <div class="font-medium">${product.name}</div>
                                    <div class="text-xs text-gray-600">Barkod: ${product.barcode} | Fiyat: ₺${product.price}</div>
                                </div>
                                <button type="button" 
                                    class="text-red-500 hover:text-red-700"
                                    onclick="removeSelectedProduct(${product.id})">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                              d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                            </div>
                            `;
                        });
                        container.innerHTML = html;
                    }
                }
                
                if (countElement) {
                    countElement.textContent = selectedProducts.length;
                }
            }
        }
        
        // Sayfa yüklendiğinde filtre seçeneklerini göster
        document.addEventListener('DOMContentLoaded', function() {
            showFilterOptions();
            
            // Bugünün tarihini varsayılan olarak ayarla
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('start_date').value = today;
            
            // Bitiş tarihi olarak 30 gün sonrasını ayarla
            const endDate = new Date();
            endDate.setDate(endDate.getDate() + 30);
            document.getElementById('end_date').value = endDate.toISOString().split('T')[0];
        });
    </script>

<?php
// Sayfa özel scriptleri
$page_scripts = '
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
';

// Footer'ı dahil et
include '../../footer.php';
?> 	