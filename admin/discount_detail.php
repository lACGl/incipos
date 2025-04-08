<?php
// Header'ı dahil et
include 'header.php';
require_once 'db_connection.php';

// Kullanıcı giriş kontrolü
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: index.php");
    exit;
}

// İndirim ID'sini al
$discount_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// İndirim bilgilerini çek
$query = "SELECT * FROM indirimler WHERE id = :id";
$stmt = $conn->prepare($query);
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
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İndirim Detayları - <?php echo htmlspecialchars($discount['ad']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <!-- Başlık -->
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold"><?php echo htmlspecialchars($discount['ad']); ?> - İndirim Detayları</h1>
                <p class="text-gray-600">İndirim bilgileri ve ürünler</p>
            </div>
            <a href="discounts.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">
                İndirimlere Dön
            </a>
        </div>
        
        <!-- İndirim bilgileri kartı -->
        <div class="bg-white p-6 rounded-lg shadow mb-6">
            <!-- İndirim bilgileri... -->
        </div>
        
        <!-- Ürün arama ve ekleme formu -->
        <div class="bg-white p-6 rounded-lg shadow mb-6">
            <h2 class="text-lg font-semibold mb-4">İndirime Ürün Ekle</h2>
            <form id="addProductsForm" method="POST" action="add_products_to_discount.php">
                <input type="hidden" name="discount_id" value="<?php echo $discount_id; ?>">
                
                <!-- Arama kutusu -->
                <div class="mb-4">
                    <label for="productSearch" class="block text-sm font-medium text-gray-700 mb-1">Ürün Ara</label>
                    <input type="text" id="productSearch" placeholder="Barkod veya ürün adı ile ara..." 
                           class="w-full px-4 py-2 border rounded focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <!-- Arama sonuçları -->
                <div id="searchResults" class="border rounded-md mb-4 max-h-60 overflow-y-auto">
                    <div class="p-3 text-gray-500 text-center">Arama yapmak için en az 3 karakter girin</div>
                </div>
                
                <!-- Seçili ürünler -->
                <div id="selectedProducts" class="mb-4">
                    <!-- JavaScript ile doldurulacak -->
                </div>
                
                <div class="flex justify-end">
                    <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded" disabled>
                        Seçili Ürünleri Ekle
                    </button>
                </div>
            </form>
        </div>
        
        <!-- İndirimli ürünler listesi -->
        <div class="bg-white p-6 rounded-lg shadow">
            <h2 class="text-lg font-semibold mb-4">İndirimli Ürünler (<?php echo count($discount_details); ?>)</h2>
            
            <?php if (empty($discount_details)): ?>
                <p class="text-gray-500 text-center py-4">Bu indirime henüz ürün eklenmemiş.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ürün</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Barkod</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Normal Fiyat</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">İndirimli Fiyat</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">İndirim Oranı</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">İşlemler</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($discount_details as $detail): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php echo htmlspecialchars($detail['urun_adi']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php echo htmlspecialchars($detail['barkod']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right">
                                        ₺<?php echo number_format($detail['eski_fiyat'], 2, ',', '.'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right">
                                        ₺<?php echo number_format($detail['indirimli_fiyat'], 2, ',', '.'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right">
                                        <?php 
                                        $discount_percent = 100 - (($detail['indirimli_fiyat'] / $detail['eski_fiyat']) * 100);
                                        echo '%' . number_format($discount_percent, 2, ',', '.');
                                        ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <button onclick="removeProductFromDiscount(<?php echo $detail['id']; ?>)" 
                                                class="text-red-600 hover:text-red-900">
                                            Kaldır
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>