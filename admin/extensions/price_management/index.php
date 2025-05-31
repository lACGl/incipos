<?php
/**
 * Fiyat Yönetimi - Ana Sayfa
 * Toplu fiyat güncelleme ve fiyat yönetimi işlemleri
 */

// Session yönetimi ve yetkisiz erişim kontrolü
require_once '../../session_manager.php';

// Session kontrolü
checkUserSession();

// Veritabanı bağlantısı
require_once '../../db_connection.php';

// Sayfa başlığı
$page_title = "Fiyat Yönetimi";

// Header'ı dahil et
include '../../header.php';

// Sayfa numarası ve limit ayarları
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 10; // Her sayfada gösterilecek kayıt sayısı
$offset = ($page - 1) * $limit;

// Toplam kayıt sayısını getir
$stmt = $conn->prepare("
    SELECT COUNT(*) as total
    FROM urun_fiyat_gecmisi ufg
");
$stmt->execute();
$total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $limit);

// Son fiyat güncellemelerini sayfalı olarak getir
$stmt = $conn->prepare("
    SELECT ufg.id, ufg.urun_id, ufg.islem_tipi, ufg.eski_fiyat, ufg.yeni_fiyat, 
           ufg.aciklama, ufg.tarih, us.ad as urun_adi, us.barkod,
           CONCAT(au.kullanici_adi) as kullanici
    FROM urun_fiyat_gecmisi ufg
    LEFT JOIN urun_stok us ON ufg.urun_id = us.id
    LEFT JOIN admin_user au ON ufg.kullanici_id = au.id
    ORDER BY ufg.tarih DESC
    LIMIT :offset, :limit
");
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();
$son_fiyat_guncellemeleri = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Toplam ürün sayısını getir
$stmt = $conn->prepare("SELECT COUNT(*) as toplam FROM urun_stok WHERE durum = 'aktif'");
$stmt->execute();
$toplam_urun = $stmt->fetch(PDO::FETCH_ASSOC)['toplam'];

// Fiyatı güncellenmiş ürün sayısını getir (son 30 gün)
$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT urun_id) as toplam 
    FROM urun_fiyat_gecmisi 
    WHERE tarih >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$stmt->execute();
$guncellenmis_urun = $stmt->fetch(PDO::FETCH_ASSOC)['toplam'];

// Fiyatı değişmeyen ürün sayısını hesapla
$degismemis_urun = $toplam_urun - $guncellenmis_urun;

// Aktif alarm sayısını getir (eğer tablo varsa)
$aktif_alarmlar = 0;
try {
    // Önce tablo var mı kontrol et
    $check_table = $conn->prepare("SHOW TABLES LIKE 'fiyat_alarmlari'");
    $check_table->execute();
    
    if ($check_table->rowCount() > 0) {
        // Tablo varsa alarm sayısını getir
        $stmt = $conn->prepare("
            SELECT COUNT(*) as toplam 
            FROM fiyat_alarmlari 
            WHERE durum = 'aktif'
        ");
        $stmt->execute();
        $aktif_alarmlar = $stmt->fetch(PDO::FETCH_ASSOC)['toplam'];
    }
} catch (PDOException $e) {
    // Hata durumunda sessizce devam et
    error_log("Fiyat alarmları tablosu erişim hatası: " . $e->getMessage());
}
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6"><?php echo $page_title; ?></h1>
    
    <!-- Bilgi Kartları -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <!-- Toplam Ürün Sayısı -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold text-gray-700 mb-2">Toplam Ürün</h2>
            <p class="text-3xl font-bold text-blue-600"><?php echo number_format($toplam_urun); ?></p>
            <p class="text-sm text-gray-500 mt-2">Sistemde kayıtlı aktif ürün sayısı</p>
        </div>
        
        <!-- Son 30 Günde Fiyatı Güncellenen Ürün Sayısı -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold text-gray-700 mb-2">Fiyatı Güncellenen</h2>
            <p class="text-3xl font-bold text-green-600"><?php echo number_format($guncellenmis_urun); ?></p>
            <p class="text-sm text-gray-500 mt-2">Son 30 günde fiyatı değişen ürün sayısı</p>
        </div>
        
        <!-- Fiyatı Değişmeyen Ürün Sayısı -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold text-gray-700 mb-2">Fiyatı Değişmeyen</h2>
            <p class="text-3xl font-bold text-yellow-600"><?php echo number_format($degismemis_urun); ?></p>
            <p class="text-sm text-gray-500 mt-2">Son 30 günde fiyatı değişmeyen ürün sayısı</p>
        </div>
        
        <!-- Aktif Alarm Sayısı -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold text-gray-700 mb-2">Aktif Alarmlar</h2>
            <p class="text-3xl font-bold text-purple-600"><?php echo number_format($aktif_alarmlar); ?></p>
            <p class="text-sm text-gray-500 mt-2">Toplam aktif fiyat alarmı sayısı</p>
        </div>
    </div>
    
    <!-- İşlem Butonları -->
    <div class="bg-white rounded-lg shadow p-6 mb-8">
        <h2 class="text-lg font-semibold mb-4">Hızlı İşlemler</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <a href="bulk_update.php" class="bg-blue-500 hover:bg-blue-600 text-white py-3 px-4 rounded-lg text-center transition flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M5 4a1 1 0 00-2 0v7.268a2 2 0 000 3.464V16a1 1 0 102 0v-1.268a2 2 0 000-3.464V4zM11 4a1 1 0 10-2 0v1.268a2 2 0 000 3.464V16a1 1 0 102 0V8.732a2 2 0 000-3.464V4zM16 3a1 1 0 011 1v7.268a2 2 0 010 3.464V16a1 1 0 11-2 0v-1.268a2 2 0 010-3.464V4a1 1 0 011-1z" />
                </svg>
                Toplu Fiyat Güncelleme
            </a>
            <a href="category_pricing.php" class="bg-green-500 hover:bg-green-600 text-white py-3 px-4 rounded-lg text-center transition flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5zM5 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5zM11 5a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V5zM11 13a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                </svg>
                Kategori Bazlı Fiyatlandırma
            </a>
            <a href="profit_analyzer.php" class="bg-indigo-500 hover:bg-indigo-600 text-white py-3 px-4 rounded-lg text-center transition flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z" />
                </svg>
                Kar Marjı Analizi
            </a>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
            <a href="price_history.php" class="bg-purple-500 hover:bg-purple-600 text-white py-3 px-4 rounded-lg text-center transition flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                </svg>
                Fiyat Geçmişi Raporu
            </a>
            <a href="../../stock_list.php" class="bg-gray-500 hover:bg-gray-600 text-white py-3 px-4 rounded-lg text-center transition flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M8 5a1 1 0 100 2h5.586l-1.293 1.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L13.586 5H8zM12 15a1 1 0 100-2H6.414l1.293-1.293a1 1 0 10-1.414-1.414l-3 3a1 1 0 000 1.414l3 3a1 1 0 001.414-1.414L6.414 15H12z" />
                </svg>
                Stok Yönetimine Dön
            </a>
        </div>
    </div>
    
    <!-- Son Fiyat Güncellemeleri Tablosu -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="p-6 border-b flex justify-between items-center">
            <h2 class="text-lg font-semibold">Son Fiyat Güncellemeleri</h2>
            <a href="price_history.php" class="text-blue-600 hover:text-blue-800 text-sm">Tüm geçmişi görüntüle →</a>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ürün</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Barkod</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">İşlem Türü</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Eski Fiyat</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Yeni Fiyat</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Değişim %</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tarih</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">İşlem Yapan</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($son_fiyat_guncellemeleri)): ?>
                        <tr>
                            <td colspan="8" class="px-6 py-4 text-center text-gray-500">Henüz fiyat güncellemesi yapılmamış.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($son_fiyat_guncellemeleri as $guncelleme): ?>
                            <?php 
                                // Fiyat değişim yüzdesi hesapla
                                $degisim_yuzdesi = 0;
                                if ($guncelleme['eski_fiyat'] > 0) {
                                    $degisim_yuzdesi = (($guncelleme['yeni_fiyat'] - $guncelleme['eski_fiyat']) / $guncelleme['eski_fiyat']) * 100;
                                }
                                
                                // İşlem tipini Türkçe'ye çevir
                                $islem_tipi = $guncelleme['islem_tipi'] == 'alis' ? 'Alış Fiyat Güncelleme' : 'Satış Fiyat Güncelleme';
                            ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <a href="../../stock_details.php?id=<?php echo $guncelleme['urun_id']; ?>" class="text-blue-600 hover:text-blue-900">
                                        <?php echo htmlspecialchars($guncelleme['urun_adi']); ?>
                                    </a>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($guncelleme['barkod']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap"><?php echo $islem_tipi; ?></td>
                                <td class="px-6 py-4 whitespace-nowrap"><?php echo number_format($guncelleme['eski_fiyat'], 2, ',', '.') . ' ₺'; ?></td>
                                <td class="px-6 py-4 whitespace-nowrap"><?php echo number_format($guncelleme['yeni_fiyat'], 2, ',', '.') . ' ₺'; ?></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="<?php echo $degisim_yuzdesi > 0 ? 'text-green-600' : ($degisim_yuzdesi < 0 ? 'text-red-600' : 'text-gray-600'); ?>">
                                        <?php 
                                            echo $degisim_yuzdesi > 0 ? '+' : '';
                                            echo number_format($degisim_yuzdesi, 2, ',', '.') . '%'; 
                                        ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap"><?php echo date('d.m.Y H:i', strtotime($guncelleme['tarih'])); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($guncelleme['kullanici']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t flex justify-end">
            <!-- Pagination -->
            <?php if($total_pages > 1): ?>
            <div class="flex space-x-1">
                <?php if($page > 1): ?>
                <a href="?page=<?php echo $page-1; ?>" class="px-3 py-1 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">«</a>
                <?php endif; ?>
                
                <?php
                // Pagination sayfa linklerini oluştur
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                for($i = $start_page; $i <= $end_page; $i++): 
                ?>
                    <a href="?page=<?php echo $i; ?>" class="px-3 py-1 <?php echo $i == $page ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700'; ?> rounded hover:bg-gray-300">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <?php if($page < $total_pages): ?>
                <a href="?page=<?php echo $page+1; ?>" class="px-3 py-1 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">»</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Burada gerekirse JavaScript kodları eklenebilir
});
</script>

<?php include '../../footer.php'; ?>