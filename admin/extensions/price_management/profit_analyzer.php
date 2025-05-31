<?php
/**
 * Fiyat Yönetimi - Kar Marjı Analizi
 * Ürünlerin kar marjlarının analizi ve raporlanması
 */

// Session yönetimi ve yetkisiz erişim kontrolü
require_once '../../session_manager.php';

// Session kontrolü
checkUserSession();

// Veritabanı bağlantısı
require_once '../../db_connection.php';

// Sayfa başlığı
$page_title = "Kar Marjı Analizi";

// Filtre parametreleri
$departman_id = isset($_GET['departman_id']) ? intval($_GET['departman_id']) : 0;
$ana_grup_id = isset($_GET['ana_grup_id']) ? intval($_GET['ana_grup_id']) : 0;
$alt_grup_id = isset($_GET['alt_grup_id']) ? intval($_GET['alt_grup_id']) : 0;
$min_kar = isset($_GET['min_kar']) && $_GET['min_kar'] !== '' ? floatval($_GET['min_kar']) : null;
$max_kar = isset($_GET['max_kar']) && $_GET['max_kar'] !== '' ? floatval($_GET['max_kar']) : null;
$sirala = isset($_GET['sirala']) ? $_GET['sirala'] : 'kar_desc';
$goster = isset($_GET['goster']) ? $_GET['goster'] : 'all';

// Sayfa numarası ve limit
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 25; // Her sayfada gösterilecek kayıt sayısı
$offset = ($page - 1) * $limit;

// SQL sorgusu oluştur
$sql = "
    SELECT 
        us.id, 
        us.kod,
        us.barkod, 
        us.ad, 
        us.alis_fiyati, 
        us.satis_fiyati,
        d.ad as departman_adi,
        ag.ad as ana_grup_adi,
        alg.ad as alt_grup_adi
    FROM urun_stok us
    LEFT JOIN departmanlar d ON us.departman_id = d.id
    LEFT JOIN ana_gruplar ag ON us.ana_grup_id = ag.id
    LEFT JOIN alt_gruplar alg ON us.alt_grup_id = alg.id
    WHERE us.durum = 'aktif' AND us.alis_fiyati > 0
";
$params = [];

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

// Kar marjı filtresi
if ($min_kar !== null) {
    $sql .= " AND ((us.satis_fiyati - us.alis_fiyati) / us.alis_fiyati * 100) >= :min_kar";
    $params[':min_kar'] = $min_kar;
}

if ($max_kar !== null) {
    $sql .= " AND ((us.satis_fiyati - us.alis_fiyati) / us.alis_fiyati * 100) <= :max_kar";
    $params[':max_kar'] = $max_kar;
}

// Belirli ürünleri gösterme seçeneği
if ($goster == 'low_profit') {
    $sql .= " AND ((us.satis_fiyati - us.alis_fiyati) / us.alis_fiyati * 100) < 15";
} elseif ($goster == 'negative_profit') {
    $sql .= " AND us.satis_fiyati <= us.alis_fiyati";
} elseif ($goster == 'high_profit') {
    $sql .= " AND ((us.satis_fiyati - us.alis_fiyati) / us.alis_fiyati * 100) > 50";
}

// Toplam kayıt sayısını getir
$count_sql = str_replace("SELECT 
        us.id, 
        us.kod,
        us.barkod, 
        us.ad, 
        us.alis_fiyati, 
        us.satis_fiyati,
        d.ad as departman_adi,
        ag.ad as ana_grup_adi,
        alg.ad as alt_grup_adi", "SELECT COUNT(*) as total", $sql);
$stmt = $conn->prepare($count_sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $limit);

// Sıralama ekle
switch ($sirala) {
    case 'ad_asc':
        $sql .= " ORDER BY us.ad ASC";
        break;
    case 'ad_desc':
        $sql .= " ORDER BY us.ad DESC";
        break;
    case 'alis_asc':
        $sql .= " ORDER BY us.alis_fiyati ASC";
        break;
    case 'alis_desc':
        $sql .= " ORDER BY us.alis_fiyati DESC";
        break;
    case 'satis_asc':
        $sql .= " ORDER BY us.satis_fiyati ASC";
        break;
    case 'satis_desc':
        $sql .= " ORDER BY us.satis_fiyati DESC";
        break;
    case 'kar_asc':
        $sql .= " ORDER BY ((us.satis_fiyati - us.alis_fiyati) / us.alis_fiyati * 100) ASC";
        break;
    case 'kar_desc':
        $sql .= " ORDER BY ((us.satis_fiyati - us.alis_fiyati) / us.alis_fiyati * 100) DESC";
        break;
    default:
        $sql .= " ORDER BY ((us.satis_fiyati - us.alis_fiyati) / us.alis_fiyati * 100) DESC";
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
$urunler = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
                
                <!-- Kar Marjı Filtreleri -->
                <div>
                    <label for="min_kar" class="block text-sm font-medium text-gray-700 mb-1">Minimum Kar Marjı (%)</label>
                    <input type="number" id="min_kar" name="min_kar" step="0.01" 
                           value="<?php echo $min_kar !== null ? htmlspecialchars($min_kar) : ''; ?>" 
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                </div>
                
                <div>
                    <label for="max_kar" class="block text-sm font-medium text-gray-700 mb-1">Maksimum Kar Marjı (%)</label>
                    <input type="number" id="max_kar" name="max_kar" step="0.01" 
                           value="<?php echo $max_kar !== null ? htmlspecialchars($max_kar) : ''; ?>" 
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                </div>
                
                <div>
                    <label for="goster" class="block text-sm font-medium text-gray-700 mb-1">Gösterim</label>
                    <select id="goster" name="goster" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                        <option value="all" <?php echo $goster == 'all' ? 'selected' : ''; ?>>Tüm Ürünler</option>
                        <option value="low_profit" <?php echo $goster == 'low_profit' ? 'selected' : ''; ?>>Düşük Kar Marjlı (&lt;15%)</option>
                        <option value="negative_profit" <?php echo $goster == 'negative_profit' ? 'selected' : ''; ?>>Kar Marjı Negatif</option>
                        <option value="high_profit" <?php echo $goster == 'high_profit' ? 'selected' : ''; ?>>Yüksek Kar Marjlı (&gt;50%)</option>
                    </select>
                </div>
                
                <div>
                    <label for="sirala" class="block text-sm font-medium text-gray-700 mb-1">Sıralama</label>
                    <select id="sirala" name="sirala" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                        <option value="kar_desc" <?php echo $sirala == 'kar_desc' ? 'selected' : ''; ?>>Kar Marjı (Yüksekten Düşüğe)</option>
                        <option value="kar_asc" <?php echo $sirala == 'kar_asc' ? 'selected' : ''; ?>>Kar Marjı (Düşükten Yükseğe)</option>
                        <option value="ad_asc" <?php echo $sirala == 'ad_asc' ? 'selected' : ''; ?>>Ürün Adı (A-Z)</option>
                        <option value="ad_desc" <?php echo $sirala == 'ad_desc' ? 'selected' : ''; ?>>Ürün Adı (Z-A)</option>
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
    
    <!-- Kar Marjı Hedefleme Aracı -->
    <div class="bg-white rounded-lg shadow-md p-4 mb-6">
        <h2 class="text-lg font-semibold mb-4">Kar Marjı Hedefleme</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <div class="mb-3">
                    <label for="urun_kod" class="block text-sm font-medium text-gray-700 mb-1">Ürün Kodu/Barkod</label>
                    <input type="text" id="urun_kod" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                </div>
                <div class="mb-3">
                    <label for="hedef_kar" class="block text-sm font-medium text-gray-700 mb-1">Hedef Kar Marjı (%)</label>
                    <input type="number" id="hedef_kar" value="25" min="0" max="100" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                </div>
                <div>
                    <button id="hesaplaBtn" class="bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded-md transition">Hesapla</button>
                </div>
            </div>
            <div>
                <div id="hesaplamaResult" class="p-4 bg-gray-100 rounded-md h-full flex items-center justify-center">
                    <p class="text-gray-500">Hedef kar marjına göre bir ürün için fiyat hesaplayabilirsiniz.</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Sonuç Bilgisi -->
    <div class="bg-white rounded-lg shadow-md p-4 mb-6">
        <div class="flex justify-between items-center">
            <p class="font-medium">Toplam <span class="text-blue-600"><?php echo $total_records; ?></span> ürün bulundu.</p>
            <?php if ($total_records > 0): ?>
                <a href="export_profit_data.php?<?php echo http_build_query($_GET) . '&export=1'; ?>" class="bg-green-500 hover:bg-green-600 text-white py-1 px-3 rounded-md transition text-sm">
                    Excel'e Aktar
                </a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Sadeleştirilmiş Kar Marjı Tablosu -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="text-left px-3 py-3 text-xs font-medium text-gray-500 uppercase">Ürün</th>
                        <th class="text-left px-3 py-3 text-xs font-medium text-gray-500 uppercase">Barkod</th>
                        <th class="text-right px-3 py-3 text-xs font-medium text-gray-500 uppercase">Alış Fiyatı</th>
                        <th class="text-right px-3 py-3 text-xs font-medium text-gray-500 uppercase">Satış Fiyatı</th>
                        <th class="text-right px-3 py-3 text-xs font-medium text-gray-500 uppercase">Kar Marjı</th>
                        <th class="text-right px-3 py-3 text-xs font-medium text-gray-500 uppercase">%25 Kar Fiyatı</th>
                        <th class="text-center px-3 py-3 text-xs font-medium text-gray-500 uppercase">İşlem</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($urunler)): ?>
                        <tr>
                            <td colspan="7" class="px-3 py-4 text-center text-gray-500">Kayıt bulunamadı.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($urunler as $urun): ?>
                            <?php 
                                // Kar tutarı ve yüzdesi hesapla
                                $kar_tutari = $urun['satis_fiyati'] - $urun['alis_fiyati'];
                                $kar_yuzdesi = $urun['alis_fiyati'] > 0 ? ($kar_tutari / $urun['alis_fiyati']) * 100 : 0;
                                
                                // %25 kar için gereken satış fiyatı
                                $optimal_fiyat = $urun['alis_fiyati'] * 1.25;
                                
                                // Kar marjı durumuna göre renk belirleme
                                $kar_class = 'text-gray-600';
                                if ($kar_yuzdesi < 0) {
                                    $kar_class = 'text-red-600 font-bold';
                                } elseif ($kar_yuzdesi < 10) {
                                    $kar_class = 'text-red-500';
                                } elseif ($kar_yuzdesi < 15) {
                                    $kar_class = 'text-yellow-600';
                                } elseif ($kar_yuzdesi > 50) {
                                    $kar_class = 'text-green-600';
                                } elseif ($kar_yuzdesi > 25) {
                                    $kar_class = 'text-green-500';
                                }
                            ?>
                            <tr>
                                <td class="px-3 py-2">
                                    <?php echo htmlspecialchars($urun['ad']); ?>
                                </td>
                                <td class="px-3 py-2">
                                    <?php echo htmlspecialchars($urun['kod'] ?: $urun['barkod']); ?>
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <?php echo number_format($urun['alis_fiyati'], 2, ',', '.') . ' ₺'; ?>
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <?php echo number_format($urun['satis_fiyati'], 2, ',', '.') . ' ₺'; ?>
                                </td>
                                <td class="px-3 py-2 text-right <?php echo $kar_class; ?>">
                                    <?php echo number_format($kar_yuzdesi, 2, ',', '.') . ' %'; ?>
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <?php echo number_format($optimal_fiyat, 2, ',', '.') . ' ₺'; ?>
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <button class="bg-blue-500 hover:bg-blue-600 text-white px-2 py-1 rounded text-sm update-price" 
                                            data-id="<?php echo $urun['id']; ?>" 
                                            data-price="<?php echo $optimal_fiyat; ?>">
                                        Uygula
                                    </button>
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
    
    // Kar marjı hedefleme hesaplama
    document.getElementById('hesaplaBtn').addEventListener('click', function() {
        const urunKod = document.getElementById('urun_kod').value.trim();
        const hedefKar = parseFloat(document.getElementById('hedef_kar').value);
        const resultDiv = document.getElementById('hesaplamaResult');
        
        if (!urunKod) {
            resultDiv.innerHTML = '<p class="text-red-500">Lütfen bir ürün kodu veya barkod girin.</p>';
            return;
        }
        
        if (isNaN(hedefKar) || hedefKar < 0) {
            resultDiv.innerHTML = '<p class="text-red-500">Geçerli bir kar marjı yüzdesi girin.</p>';
            return;
        }
        
        resultDiv.innerHTML = '<div class="text-center"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500 mx-auto"></div><p class="mt-2">Hesaplanıyor...</p></div>';
        
        // Ürün bilgilerini getir
        fetch('get_product_data.php?kod=' + encodeURIComponent(urunKod))
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const product = data.product;
                    const alisFiyati = parseFloat(product.alis_fiyati);
                    const satisFiyati = parseFloat(product.satis_fiyati);
                    
                    // Hedef kar için gereken fiyat
                    const hedefFiyat = alisFiyati * (1 + (hedefKar / 100));
                    
                    // Mevcut kar marjı
                    const mevcutKarMarji = ((satisFiyati - alisFiyati) / alisFiyati) * 100;
                    
                    // Sonuçları göster
                    let html = '<div class="p-4 rounded-lg border">';
                    html += `<h3 class="font-semibold text-lg mb-2">${product.ad}</h3>`;
                    html += `<p><strong>Barkod/Kod:</strong> ${product.barkod || product.kod}</p>`;
                    html += `<p><strong>Alış Fiyatı:</strong> ${alisFiyati.toFixed(2)} ₺</p>`;
                    html += `<p><strong>Mevcut Satış Fiyatı:</strong> ${satisFiyati.toFixed(2)} ₺</p>`;
                    html += `<p><strong>Mevcut Kar Marjı:</strong> ${mevcutKarMarji.toFixed(2)}%</p>`;
                    html += `<p class="mt-3"><strong>Hedef Kar Marjı (${hedefKar}%) için Gerekli Satış Fiyatı:</strong></p>`;
                    html += `<p class="text-xl font-bold text-blue-600">${hedefFiyat.toFixed(2)} ₺</p>`;
                    
                    // Fiyat güncelleme butonu
                    html += `<div class="mt-3">`;
                    html += `<button class="update-price bg-green-500 hover:bg-green-600 text-white py-1 px-3 rounded" data-id="${product.id}" data-price="${hedefFiyat.toFixed(2)}">Bu Fiyatı Uygula</button>`;
                    html += `</div>`;
                    
                    html += '</div>';
                    
                    resultDiv.innerHTML = html;
                    
                    // Fiyat güncelleme butonlarına olay dinleyici ekle
                    document.querySelectorAll('.update-price').forEach(btn => {
                        btn.addEventListener('click', updatePrice);
                    });
                } else {
                    resultDiv.innerHTML = `<p class="text-red-500">${data.message || 'Ürün bulunamadı.'}</p>`;
                }
            })
            .catch(error => {
                console.error('Ürün verisi alınamadı:', error);
                resultDiv.innerHTML = '<p class="text-red-500">Ürün verisi alınırken bir hata oluştu.</p>';
            });
    });
    
    // Tablo içindeki optimal fiyat uygula butonları
    document.querySelectorAll('.update-price').forEach(btn => {
        btn.addEventListener('click', updatePrice);
    });
    
    // Fiyat güncelleme fonksiyonu
    function updatePrice(e) {
        const urunId = this.getAttribute('data-id');
        const yeniFiyat = this.getAttribute('data-price');
        
        if (confirm(`Bu ürünün fiyatını ${yeniFiyat} ₺ olarak güncellemek istediğinize emin misiniz?`)) {
            fetch('update_product_price.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `urun_id=${urunId}&yeni_fiyat=${yeniFiyat}&aciklama=Kar marjı optimizasyonu`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Ürün fiyatı başarıyla güncellendi.');
                    window.location.reload();
                } else {
                    alert(`Hata: ${data.message || 'Fiyat güncellenemedi.'}`);
                }
            })
            .catch(error => {
                console.error('Fiyat güncelleme hatası:', error);
                alert('Fiyat güncellenirken bir hata oluştu.');
            });
        }
    }
});
</script>

<?php include '../../footer.php'; ?>