<?php
/**
 * Fiyat Yönetimi - Fiyat Geçmişi Raporu
 * Ürünlerin fiyat değişim geçmişini gösteren rapor
 */

// Session yönetimi ve yetkisiz erişim kontrolü
require_once '../../session_manager.php';

// Session kontrolü
checkUserSession();

// Veritabanı bağlantısı
require_once '../../db_connection.php';

// Sayfa başlığı
$page_title = "Fiyat Geçmişi Raporu";

// Filtre parametreleri
$baslangic_tarihi = isset($_GET['baslangic_tarihi']) ? $_GET['baslangic_tarihi'] : date('Y-m-d', strtotime('-30 days'));
$bitis_tarihi = isset($_GET['bitis_tarihi']) ? $_GET['bitis_tarihi'] : date('Y-m-d');
$departman_id = isset($_GET['departman_id']) ? intval($_GET['departman_id']) : 0;
$ana_grup_id = isset($_GET['ana_grup_id']) ? intval($_GET['ana_grup_id']) : 0;
$alt_grup_id = isset($_GET['alt_grup_id']) ? intval($_GET['alt_grup_id']) : 0;
$islem_tipi = isset($_GET['islem_tipi']) ? $_GET['islem_tipi'] : '';
$urun_adi = isset($_GET['urun_adi']) ? trim($_GET['urun_adi']) : '';
$kullanici_id = isset($_GET['kullanici_id']) ? intval($_GET['kullanici_id']) : 0;
$degisim_tipi = isset($_GET['degisim_tipi']) ? $_GET['degisim_tipi'] : '';
$sirala = isset($_GET['sirala']) ? $_GET['sirala'] : 'tarih_desc';

// Sayfa numarası ve limit
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 25; // Her sayfada gösterilecek kayıt sayısı
$offset = ($page - 1) * $limit;

// SQL sorgusu oluştur
$sql = "
    SELECT 
        ufg.id, 
        ufg.urun_id, 
        ufg.islem_tipi, 
        ufg.eski_fiyat, 
        ufg.yeni_fiyat, 
        ufg.aciklama, 
        ufg.tarih, 
        us.ad as urun_adi, 
        us.barkod,
        us.departman_id,
        us.ana_grup_id,
        us.alt_grup_id,
        CONCAT(au.kullanici_adi) as kullanici,
        ufg.kullanici_id
    FROM urun_fiyat_gecmisi ufg
    LEFT JOIN urun_stok us ON ufg.urun_id = us.id
    LEFT JOIN admin_user au ON ufg.kullanici_id = au.id
    WHERE ufg.tarih BETWEEN :baslangic_tarihi AND :bitis_tarihi
";
$params = [
    ':baslangic_tarihi' => $baslangic_tarihi . ' 00:00:00',
    ':bitis_tarihi' => $bitis_tarihi . ' 23:59:59'
];

// Filtre koşullarını ekle
if ($departman_id > 0) {
    $sql .= " AND us.departman_id = :departman_id";
    $params[':departman_id'] = $departman_id;
}

if ($ana_grup_id > 0) {
    $sql .= " AND us.ana_grup_id = :ana_grup_id";
    $params[':ana_grup_id'] = $ana_grup_id;
}

if ($alt_grup_id > 0) {
    $sql .= " AND us.alt_grup_id = :alt_grup_id";
    $params[':alt_grup_id'] = $alt_grup_id;
}

if (!empty($islem_tipi)) {
    $sql .= " AND ufg.islem_tipi = :islem_tipi";
    $params[':islem_tipi'] = $islem_tipi;
}

if (!empty($urun_adi)) {
    $sql .= " AND (us.ad LIKE :urun_adi OR us.barkod LIKE :urun_barkod)";
    $params[':urun_adi'] = '%' . $urun_adi . '%';
    $params[':urun_barkod'] = '%' . $urun_adi . '%';
}

if ($kullanici_id > 0) {
    $sql .= " AND ufg.kullanici_id = :kullanici_id";
    $params[':kullanici_id'] = $kullanici_id;
}

if ($degisim_tipi == 'artis') {
    $sql .= " AND ufg.yeni_fiyat > ufg.eski_fiyat";
} elseif ($degisim_tipi == 'azalis') {
    $sql .= " AND ufg.yeni_fiyat < ufg.eski_fiyat";
}

// Toplam kayıt sayısını getir
$count_sql = str_replace("SELECT 
        ufg.id, 
        ufg.urun_id, 
        ufg.islem_tipi, 
        ufg.eski_fiyat, 
        ufg.yeni_fiyat, 
        ufg.aciklama, 
        ufg.tarih, 
        us.ad as urun_adi, 
        us.barkod,
        us.departman_id,
        us.ana_grup_id,
        us.alt_grup_id,
        CONCAT(au.kullanici_adi) as kullanici,
        ufg.kullanici_id", "SELECT COUNT(*) as total", $sql);
$stmt = $conn->prepare($count_sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $limit);

// Sıralama ekle
switch ($sirala) {
    case 'tarih_asc':
        $sql .= " ORDER BY ufg.tarih ASC";
        break;
    case 'tarih_desc':
        $sql .= " ORDER BY ufg.tarih DESC";
        break;
    case 'urun_adi':
        $sql .= " ORDER BY us.ad ASC";
        break;
    case 'eski_fiyat_asc':
        $sql .= " ORDER BY ufg.eski_fiyat ASC";
        break;
    case 'eski_fiyat_desc':
        $sql .= " ORDER BY ufg.eski_fiyat DESC";
        break;
    case 'yeni_fiyat_asc':
        $sql .= " ORDER BY ufg.yeni_fiyat ASC";
        break;
    case 'yeni_fiyat_desc':
        $sql .= " ORDER BY ufg.yeni_fiyat DESC";
        break;
    case 'degisim_asc':
        $sql .= " ORDER BY (ufg.yeni_fiyat - ufg.eski_fiyat) ASC";
        break;
    case 'degisim_desc':
        $sql .= " ORDER BY (ufg.yeni_fiyat - ufg.eski_fiyat) DESC";
        break;
    default:
        $sql .= " ORDER BY ufg.tarih DESC";
        break;
}

// Limit ekle
$sql .= " LIMIT :offset, :limit";
$params[':offset'] = $offset;
$params[':limit'] = $limit;

// Verileri getir
$stmt = $conn->prepare($sql);
foreach ($params as $key => $value) {
    if ($key == ':offset' || $key == ':limit') {
        $stmt->bindValue($key, $value, PDO::PARAM_INT);
    } else {
        $stmt->bindValue($key, $value);
    }
}
$stmt->execute();
$fiyat_gecmisi = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Departmanları getir
$stmt = $conn->prepare("SELECT id, ad FROM departmanlar ORDER BY ad");
$stmt->execute();
$departmanlar = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ana grupları getir (departman seçiliyse)
$ana_gruplar = [];
if ($departman_id > 0) {
    $stmt = $conn->prepare("SELECT id, ad FROM ana_gruplar WHERE departman_id = ? ORDER BY ad");
    $stmt->execute([$departman_id]);
    $ana_gruplar = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Alt grupları getir (ana grup seçiliyse)
$alt_gruplar = [];
if ($ana_grup_id > 0) {
    $stmt = $conn->prepare("SELECT id, ad FROM alt_gruplar WHERE ana_grup_id = ? ORDER BY ad");
    $stmt->execute([$ana_grup_id]);
    $alt_gruplar = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Kullanıcıları getir
$stmt = $conn->prepare("SELECT id, kullanici_adi FROM admin_user ORDER BY kullanici_adi");
$stmt->execute();
$kullanicilar = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Header'ı dahil et
include '../../header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold"><?php echo $page_title; ?></h1>
        <a href="index.php" class="bg-gray-500 hover:bg-gray-600 text-white py-2 px-4 rounded">Geri Dön</a>
    </div>
    
    <!-- Filtre Formu -->
    <div class="bg-white rounded-lg shadow-md p-4 mb-6">
        <form method="get" id="filterForm" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <!-- Tarih Aralığı -->
                <div>
                    <label for="baslangic_tarihi" class="block text-sm font-medium text-gray-700 mb-1">Başlangıç Tarihi</label>
                    <input type="date" id="baslangic_tarihi" name="baslangic_tarihi" value="<?php echo $baslangic_tarihi; ?>" 
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                </div>
                
                <div>
                    <label for="bitis_tarihi" class="block text-sm font-medium text-gray-700 mb-1">Bitiş Tarihi</label>
                    <input type="date" id="bitis_tarihi" name="bitis_tarihi" value="<?php echo $bitis_tarihi; ?>" 
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                </div>
                
                <!-- Kategori Filtreleri -->
                <div>
                    <label for="departman_id" class="block text-sm font-medium text-gray-700 mb-1">Departman</label>
                    <select id="departman_id" name="departman_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                        <option value="0">Tüm Departmanlar</option>
                        <?php foreach ($departmanlar as $dept): ?>
                            <option value="<?php echo $dept['id']; ?>" <?php echo $departman_id == $dept['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept['ad']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label for="ana_grup_id" class="block text-sm font-medium text-gray-700 mb-1">Ana Grup</label>
                    <select id="ana_grup_id" name="ana_grup_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50" <?php echo empty($ana_gruplar) ? 'disabled' : ''; ?>>
                        <option value="0">Tüm Ana Gruplar</option>
                        <?php foreach ($ana_gruplar as $grup): ?>
                            <option value="<?php echo $grup['id']; ?>" <?php echo $ana_grup_id == $grup['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($grup['ad']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label for="alt_grup_id" class="block text-sm font-medium text-gray-700 mb-1">Alt Grup</label>
                    <select id="alt_grup_id" name="alt_grup_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50" <?php echo empty($alt_gruplar) ? 'disabled' : ''; ?>>
                        <option value="0">Tüm Alt Gruplar</option>
                        <?php foreach ($alt_gruplar as $grup): ?>
                            <option value="<?php echo $grup['id']; ?>" <?php echo $alt_grup_id == $grup['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($grup['ad']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Diğer Filtreler -->
                <div>
                    <label for="islem_tipi" class="block text-sm font-medium text-gray-700 mb-1">İşlem Tipi</label>
                    <select id="islem_tipi" name="islem_tipi" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                        <option value="">Tüm İşlemler</option>
                        <option value="alis" <?php echo $islem_tipi == 'alis' ? 'selected' : ''; ?>>Alış Fiyat Güncelleme</option>
                        <option value="satis_fiyati_guncelleme" <?php echo $islem_tipi == 'satis_fiyati_guncelleme' ? 'selected' : ''; ?>>Satış Fiyat Güncelleme</option>
                    </select>
                </div>
                
                <div>
                    <label for="urun_adi" class="block text-sm font-medium text-gray-700 mb-1">Ürün Adı/Barkod</label>
                    <input type="text" id="urun_adi" name="urun_adi" value="<?php echo htmlspecialchars($urun_adi); ?>" 
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50" placeholder="Ürün adı veya barkod ara...">
                </div>
                
                <div>
                    <label for="kullanici_id" class="block text-sm font-medium text-gray-700 mb-1">Kullanıcı</label>
                    <select id="kullanici_id" name="kullanici_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                        <option value="0">Tüm Kullanıcılar</option>
                        <?php foreach ($kullanicilar as $user): ?>
                            <option value="<?php echo $user['id']; ?>" <?php echo $kullanici_id == $user['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['kullanici_adi']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label for="degisim_tipi" class="block text-sm font-medium text-gray-700 mb-1">Değişim Türü</label>
                    <select id="degisim_tipi" name="degisim_tipi" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                        <option value="">Tümü</option>
                        <option value="artis" <?php echo $degisim_tipi == 'artis' ? 'selected' : ''; ?>>Fiyat Artışları</option>
                        <option value="azalis" <?php echo $degisim_tipi == 'azalis' ? 'selected' : ''; ?>>Fiyat Azalışları</option>
                    </select>
                </div>
                
                <div>
                    <label for="sirala" class="block text-sm font-medium text-gray-700 mb-1">Sıralama</label>
                    <select id="sirala" name="sirala" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                        <option value="tarih_desc" <?php echo $sirala == 'tarih_desc' ? 'selected' : ''; ?>>Tarih (Yeniden Eskiye)</option>
                        <option value="tarih_asc" <?php echo $sirala == 'tarih_asc' ? 'selected' : ''; ?>>Tarih (Eskiden Yeniye)</option>
                        <option value="urun_adi" <?php echo $sirala == 'urun_adi' ? 'selected' : ''; ?>>Ürün Adı</option>
                        <option value="eski_fiyat_asc" <?php echo $sirala == 'eski_fiyat_asc' ? 'selected' : ''; ?>>Eski Fiyat (Düşükten Yükseğe)</option>
                        <option value="eski_fiyat_desc" <?php echo $sirala == 'eski_fiyat_desc' ? 'selected' : ''; ?>>Eski Fiyat (Yüksekten Düşüğe)</option>
                        <option value="yeni_fiyat_asc" <?php echo $sirala == 'yeni_fiyat_asc' ? 'selected' : ''; ?>>Yeni Fiyat (Düşükten Yükseğe)</option>
                        <option value="yeni_fiyat_desc" <?php echo $sirala == 'yeni_fiyat_desc' ? 'selected' : ''; ?>>Yeni Fiyat (Yüksekten Düşüğe)</option>
                        <option value="degisim_asc" <?php echo $sirala == 'degisim_asc' ? 'selected' : ''; ?>>Değişim (Düşükten Yükseğe)</option>
                        <option value="degisim_desc" <?php echo $sirala == 'degisim_desc' ? 'selected' : ''; ?>>Değişim (Yüksekten Düşüğe)</option>
                    </select>
                </div>
            </div>
            
            <div class="flex justify-end">
                <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded-md transition">
                    Filtrele
                </button>
            </div>
        </form>
    </div>
    
    <!-- Sonuç Bilgisi -->
    <div class="bg-white rounded-lg shadow-md p-4 mb-6">
        <div class="flex justify-between items-center">
            <p class="font-medium">Toplam <span class="text-blue-600"><?php echo $total_records; ?></span> kayıt bulundu.</p>
            <?php if ($total_records > 0): ?>
                <a href="export_price_history.php?<?php echo http_build_query($_GET) . '&export=1'; ?>" class="bg-green-500 hover:bg-green-600 text-white py-1 px-3 rounded-md transition text-sm">
                    Excel'e Aktar
                </a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Fiyat Geçmişi Tablosu -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tarih</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ürün</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Barkod</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">İşlem Türü</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Eski Fiyat</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Yeni Fiyat</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Değişim %</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kullanıcı</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Açıklama</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($fiyat_gecmisi)): ?>
                        <tr>
                            <td colspan="9" class="px-6 py-4 text-center text-gray-500">Kayıt bulunamadı.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($fiyat_gecmisi as $kayit): ?>
                            <?php 
                                // Fiyat değişim yüzdesi hesapla
                                $degisim_yuzdesi = 0;
                                if ($kayit['eski_fiyat'] > 0) {
                                    $degisim_yuzdesi = (($kayit['yeni_fiyat'] - $kayit['eski_fiyat']) / $kayit['eski_fiyat']) * 100;
                                }
                                
                                // İşlem tipini Türkçe'ye çevir
                                $islem_tipi = $kayit['islem_tipi'] == 'alis' ? 'Alış Fiyat Güncelleme' : 'Satış Fiyat Güncelleme';
                            ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php echo date('d.m.Y H:i', strtotime($kayit['tarih'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php echo htmlspecialchars($kayit['urun_adi']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($kayit['barkod']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap"><?php echo $islem_tipi; ?></td>
                                <td class="px-6 py-4 whitespace-nowrap"><?php echo number_format($kayit['eski_fiyat'], 2, ',', '.') . ' ₺'; ?></td>
                                <td class="px-6 py-4 whitespace-nowrap"><?php echo number_format($kayit['yeni_fiyat'], 2, ',', '.') . ' ₺'; ?></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="<?php echo $degisim_yuzdesi > 0 ? 'text-green-600' : ($degisim_yuzdesi < 0 ? 'text-red-600' : 'text-gray-600'); ?>">
                                        <?php 
                                            echo $degisim_yuzdesi > 0 ? '+' : '';
                                            echo number_format($degisim_yuzdesi, 2, ',', '.') . '%'; 
                                        ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($kayit['kullanici']); ?></td>
                                <td class="px-6 py-4">
                                    <div class="max-w-xs truncate" title="<?php echo htmlspecialchars($kayit['aciklama']); ?>">
                                        <?php echo htmlspecialchars($kayit['aciklama']); ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($total_pages > 1): ?>
        <div class="p-4 border-t flex justify-center">
            <div class="flex space-x-1">
                <?php if($page > 1): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page-1])); ?>" class="px-3 py-1 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">«</a>
                <?php endif; ?>
                
                <?php
                // Pagination sayfa linklerini oluştur
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                for($i = $start_page; $i <= $end_page; $i++): 
                ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" class="px-3 py-1 <?php echo $i == $page ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700'; ?> rounded hover:bg-gray-300">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <?php if($page < $total_pages): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page+1])); ?>" class="px-3 py-1 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">»</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Departman değiştiğinde ana grupları getir
    document.getElementById('departman_id').addEventListener('change', function() {
        const departmanId = this.value;
        const anaGrupSelect = document.getElementById('ana_grup_id');
        const altGrupSelect = document.getElementById('alt_grup_id');
        
        // Ana grup seçimini sıfırla
        anaGrupSelect.innerHTML = '<option value="0">Tüm Ana Gruplar</option>';
        anaGrupSelect.disabled = departmanId == 0;
        
        // Alt grup seçimini sıfırla
        altGrupSelect.innerHTML = '<option value="0">Tüm Alt Gruplar</option>';
        altGrupSelect.disabled = true;
        
        if (departmanId > 0) {
            fetch('/admin/api/get_ana_gruplar.php?departman_id=' + departmanId)
                .then(function(response) {
                    return response.json();
                })
                .then(function(data) {
                    // API'den gelen data kontrolü
                    let anaGruplar = [];
                    
                    // API yanıt formatını kontrol et
                    if (data && typeof data === 'object') {
                        if (Array.isArray(data)) {
                            anaGruplar = data;
                        } else if (data.data && Array.isArray(data.data)) {
                            anaGruplar = data.data;
                        }
                    }
                    
                    if (anaGruplar.length > 0) {
                        anaGruplar.forEach(function(group) {
                            const option = document.createElement('option');
                            option.value = group.id;
                            option.textContent = group.ad;
                            anaGrupSelect.appendChild(option);
                        });
                        anaGrupSelect.disabled = false;
                    }
                })
                .catch(function(error) {
                    console.error('Ana grup verisi alınamadı:', error);
                });
        }
    });
    
    // Ana grup değiştiğinde alt grupları getir
    document.getElementById('ana_grup_id').addEventListener('change', function() {
        const anaGrupId = this.value;
        const altGrupSelect = document.getElementById('alt_grup_id');
        
        // Alt grup seçimini sıfırla
        altGrupSelect.innerHTML = '<option value="0">Tüm Alt Gruplar</option>';
        altGrupSelect.disabled = anaGrupId == 0;
        
        if (anaGrupId > 0) {
            fetch('/admin/api/get_alt_gruplar.php?ana_grup_id=' + anaGrupId)
                .then(function(response) {
                    return response.json();
                })
                .then(function(data) {
                    // API'den gelen data kontrolü
                    let altGruplar = [];
                    
                    // API yanıt formatını kontrol et
                    if (data && typeof data === 'object') {
                        if (Array.isArray(data)) {
                            altGruplar = data;
                        } else if (data.data && Array.isArray(data.data)) {
                            altGruplar = data.data;
                        }
                    }
                    
                    if (altGruplar.length > 0) {
                        altGruplar.forEach(function(group) {
                            const option = document.createElement('option');
                            option.value = group.id;
                            option.textContent = group.ad;
                            altGrupSelect.appendChild(option);
                        });
                        altGrupSelect.disabled = false;
                    }
                })
                .catch(function(error) {
                    console.error('Alt grup verisi alınamadı:', error);
                });
        }
    });
});
</script>

<?php include '../../footer.php'; ?>