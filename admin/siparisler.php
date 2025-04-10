<?php
// Sayfa yüklenme süresini hesapla
$start_time = microtime(true);

// Header'ı dahil et
include 'header.php';
require_once 'db_connection.php';
require_once 'helpers/stock_functions.php';

// Kullanıcı giriş kontrolü
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: index.php");
    exit;
}

// Mağazaları çek - Tüm mağazaları getiren sorgu
$magaza_query = "SELECT id, ad FROM magazalar ORDER BY ad";
$magazalar = $conn->query($magaza_query)->fetchAll(PDO::FETCH_ASSOC);

// Mağaza sayısını kontrol et
if (empty($magazalar)) {
    // Eğer mağaza yoksa
    $warning_message = "Dikkat: Sistemde kayıtlı mağaza bulunamadı. Lütfen önce mağaza ekleyin.";
}

// Filtreler
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$supplier_filter = isset($_GET['supplier']) ? intval($_GET['supplier']) : 0;
$date_start = isset($_GET['date_start']) ? $_GET['date_start'] : date('Y-m-d', strtotime('-30 days'));
$date_end = isset($_GET['date_end']) ? $_GET['date_end'] : date('Y-m-d');
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'tarih';
$sort_dir = isset($_GET['sort_dir']) ? $_GET['sort_dir'] : 'DESC';

// Sipariş Durumunu Güncelleme
if (isset($_POST['update_status'])) {
    $siparis_id = $_POST['siparis_id'];
    $yeni_durum = $_POST['yeni_durum'];
    $aciklama = $_POST['aciklama'] ?? '';
    
    try {
        $conn->beginTransaction();
        
        // Mevcut durumu kontrol et - aynı duruma tekrar güncelleme yapılmaması için
        $check_query = "SELECT durum FROM siparisler WHERE id = :siparis_id";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bindParam(':siparis_id', $siparis_id);
        $check_stmt->execute();
        
        $mevcut_durum = $check_stmt->fetchColumn();
        
        // Eğer durum aynıysa, güncelleme yapmadan başarılı dön
        if ($mevcut_durum == $yeni_durum) {
            $success_message = "Sipariş zaten bu durumda.";
            $conn->commit();
        } else {
            // Siparişin durumunu güncelle
            $update_query = "UPDATE siparisler SET durum = :durum";
            
            // Eğer onaylandıysa, onay tarihini ekle
            if ($yeni_durum == 'onaylandi') {
                $update_query .= ", onay_tarihi = NOW()";
            }
            
            // Eğer tamamlandıysa, teslim tarihini ekle
            if ($yeni_durum == 'tamamlandi') {
                $update_query .= ", teslim_tarihi = NOW()";
            }
            
            $update_query .= " WHERE id = :siparis_id";
            
            $stmt = $conn->prepare($update_query);
            $stmt->bindParam(':durum', $yeni_durum);
            $stmt->bindParam(':siparis_id', $siparis_id);
            $stmt->execute();
            
            // Log ekle
            $log_query = "INSERT INTO siparis_log (siparis_id, islem_tipi, aciklama, kullanici_id) 
                        VALUES (:siparis_id, 'durum_degisiklik', :aciklama, :kullanici_id)";
            $log_aciklama = "Sipariş durumu '" . $yeni_durum . "' olarak güncellendi. " . $aciklama;
            
            $stmt = $conn->prepare($log_query);
            $stmt->bindParam(':siparis_id', $siparis_id);
            $stmt->bindParam(':aciklama', $log_aciklama);
            $stmt->bindParam(':kullanici_id', $_SESSION['user_id']);
            $stmt->execute();
            
            // Eğer sipariş tamamlandıysa ve ürünler stoğa eklenecekse
            if ($yeni_durum == 'tamamlandi' && isset($_POST['add_to_stock']) && $_POST['add_to_stock'] == 1) {
                // Sipariş detaylarını al
                $detail_query = "SELECT sd.urun_id, sd.miktar, sd.birim_fiyat FROM siparis_detay sd WHERE sd.siparis_id = :siparis_id";
                $stmt = $conn->prepare($detail_query);
                $stmt->bindParam(':siparis_id', $siparis_id);
                $stmt->execute();
                $detaylar = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Depo ID'sini belirle (varsayılan: 1)
                $depo_id = isset($_POST['depo_id']) ? $_POST['depo_id'] : 1;
                
                // Her ürünü stoğa ekle
                foreach ($detaylar as $detay) {
                    $urun_id = $detay['urun_id'];
                    $miktar = $detay['miktar'];
                    $birim_fiyat = $detay['birim_fiyat'];
                    
                    // Depo stoğunu güncelle
                    updateDepoStock($urun_id, $depo_id, $miktar, $conn);
                    
                    // Stok hareketi ekle
                    $hareket_params = [
                        'urun_id' => $urun_id,
                        'miktar' => $miktar,
                        'hareket_tipi' => 'giris',
                        'aciklama' => "Sipariş #" . $siparis_id . " ile stoğa eklendi.",
                        'belge_no' => "SIP-" . $siparis_id,
                        'tarih' => date('Y-m-d H:i:s'),
                        'kullanici_id' => $_SESSION['user_id'],
                        'depo_id' => $depo_id,
                        'maliyet' => $birim_fiyat
                    ];
                    
                    addStockMovement($hareket_params, $conn);
                    
                    // Ürün fiyat geçmişini güncelle
                    $fiyat_params = [
                        'urun_id' => $urun_id,
                        'islem_tipi' => 'alis',
                        'yeni_fiyat' => $birim_fiyat,
                        'aciklama' => "Sipariş #" . $siparis_id . " ile güncellendi.",
                        'kullanici_id' => $_SESSION['user_id']
                    ];
                    
                    addPriceHistory($fiyat_params, $conn);
                }
            }
            
            $conn->commit();
            $success_message = "Sipariş durumu başarıyla güncellendi.";
        }
    } catch (PDOException $e) {
        $conn->rollBack();
        $error_message = "Sipariş durumu güncellenirken bir hata oluştu: " . $e->getMessage();
    }
}

// Sipariş İptal Etme
if (isset($_POST['cancel_order'])) {
    $siparis_id = $_POST['siparis_id'];
    $iptal_sebebi = $_POST['iptal_sebebi'] ?? 'Belirtilmedi';
    
    try {
        $conn->beginTransaction();
        
        // Mevcut durumu kontrol et
        $check_query = "SELECT durum FROM siparisler WHERE id = :siparis_id";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bindParam(':siparis_id', $siparis_id);
        $check_stmt->execute();
        
        $mevcut_durum = $check_stmt->fetchColumn();
        
        // Eğer zaten iptal durumundaysa işlem yapma
        if ($mevcut_durum == 'iptal') {
            $success_message = "Sipariş zaten iptal edilmiş.";
            $conn->commit();
        } else {
            // Siparişin durumunu güncelle
            $update_query = "UPDATE siparisler SET durum = 'iptal' WHERE id = :siparis_id";
            $stmt = $conn->prepare($update_query);
            $stmt->bindParam(':siparis_id', $siparis_id);
            $stmt->execute();
            
            // Log ekle
            $log_query = "INSERT INTO siparis_log (siparis_id, islem_tipi, aciklama, kullanici_id) 
                        VALUES (:siparis_id, 'iptal', :aciklama, :kullanici_id)";
            $log_aciklama = "Sipariş iptal edildi. Sebep: " . $iptal_sebebi;
            
            $stmt = $conn->prepare($log_query);
            $stmt->bindParam(':siparis_id', $siparis_id);
            $stmt->bindParam(':aciklama', $log_aciklama);
            $stmt->bindParam(':kullanici_id', $_SESSION['user_id']);
            $stmt->execute();
            
            $conn->commit();
            $success_message = "Sipariş başarıyla iptal edildi.";
        }
    } catch (PDOException $e) {
        $conn->rollBack();
        $error_message = "Sipariş iptal edilirken bir hata oluştu: " . $e->getMessage();
    }
}

// Sipariş Silme
if (isset($_POST['delete_order'])) {
    $siparis_id = $_POST['siparis_id'];
    
    try {
        $conn->beginTransaction();
        
        // Sipariş loglarını sil
        $log_delete_query = "DELETE FROM siparis_log WHERE siparis_id = :siparis_id";
        $stmt = $conn->prepare($log_delete_query);
        $stmt->bindParam(':siparis_id', $siparis_id);
        $stmt->execute();
        
        // Sipariş detaylarını sil
        $detail_delete_query = "DELETE FROM siparis_detay WHERE siparis_id = :siparis_id";
        $stmt = $conn->prepare($detail_delete_query);
        $stmt->bindParam(':siparis_id', $siparis_id);
        $stmt->execute();
        
        // Siparişi sil
        $order_delete_query = "DELETE FROM siparisler WHERE id = :siparis_id";
        $stmt = $conn->prepare($order_delete_query);
        $stmt->bindParam(':siparis_id', $siparis_id);
        $stmt->execute();
        
        $conn->commit();
        $success_message = "Sipariş başarıyla silindi.";
    } catch (PDOException $e) {
        $conn->rollBack();
        $error_message = "Sipariş silinirken bir hata oluştu: " . $e->getMessage();
    }
}

// Tedarikçileri çek
$supplier_query = "SELECT id, ad FROM tedarikciler ORDER BY ad";
$suppliers = $conn->query($supplier_query)->fetchAll(PDO::FETCH_ASSOC);

// Depoları çek
$warehouse_query = "SELECT id, ad FROM depolar WHERE durum = 'aktif' ORDER BY ad";
$warehouses = $conn->query($warehouse_query)->fetchAll(PDO::FETCH_ASSOC);

// Sayfalama için değişkenler
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
$offset = ($page - 1) * $per_page;

// WHERE koşulları oluştur
$where_conditions = [];
$params = [];

if ($status_filter != 'all') {
    $where_conditions[] = "s.durum = :durum";
    $params[':durum'] = $status_filter;
}

if ($supplier_filter > 0) {
    $where_conditions[] = "s.tedarikci_id = :tedarikci_id";
    $params[':tedarikci_id'] = $supplier_filter;
}

$where_conditions[] = "s.tarih BETWEEN :tarih_baslangic AND :tarih_bitis";
$params[':tarih_baslangic'] = $date_start . ' 00:00:00';
$params[':tarih_bitis'] = $date_end . ' 23:59:59';

$where_clause = !empty($where_conditions) ? " WHERE " . implode(" AND ", $where_conditions) : "";

// Toplam kayıt sayısını hesapla
$count_query = "SELECT COUNT(*) FROM siparisler s $where_clause";
$stmt = $conn->prepare($count_query);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->execute();
$total_records = $stmt->fetchColumn();
$total_pages = ceil($total_records / $per_page);

// Siparişleri çek
$query = "SELECT s.*, t.ad AS tedarikci_adi, 
          (SELECT COUNT(*) FROM siparis_detay sd WHERE sd.siparis_id = s.id) AS urun_sayisi,
          (SELECT SUM(sd.miktar * sd.birim_fiyat) FROM siparis_detay sd WHERE sd.siparis_id = s.id) AS toplam_tutar
          FROM siparisler s
          LEFT JOIN tedarikciler t ON s.tedarikci_id = t.id
          $where_clause
          ORDER BY s.$sort_by $sort_dir
          LIMIT :offset, :per_page";

$stmt = $conn->prepare($query);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
$stmt->execute();
$siparisler = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Durum metinleri ve renkleri için yardımcı diziler
$durum_metinleri = [
    'beklemede' => 'Beklemede',
    'onaylandi' => 'Onaylandı',
    'iptal' => 'İptal Edildi',
    'tamamlandi' => 'Tamamlandı'
];

$durum_renkleri = [
    'beklemede' => 'bg-yellow-100 text-yellow-800',
    'onaylandi' => 'bg-blue-100 text-blue-800',
    'iptal' => 'bg-red-100 text-red-800',
    'tamamlandi' => 'bg-green-100 text-green-800'
];

// Sayfa yüklenme süresini hesapla
$end_time = microtime(true);
$execution_time = round($end_time - $start_time, 3);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Siparişler - İnciPOS</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <!-- Başlık ve Butonlar -->
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold">Siparişler</h1>
                <p class="text-gray-600">Tedarikçilerden siparişleri yönetin</p>
            </div>
            
            <a href="critical_stock.php" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                </svg>
                Yeni Sipariş Oluştur
            </a>
        </div>

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

        <!-- Filtreler -->
        <div class="bg-white shadow-md rounded-lg p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4">Filtreler</h2>
            <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Durum</label>
                    <select id="status" name="status" class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 rounded-md">
                        <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>Tüm Durumlar</option>
                        <option value="beklemede" <?php echo $status_filter == 'beklemede' ? 'selected' : ''; ?>>Beklemede</option>
                        <option value="onaylandi" <?php echo $status_filter == 'onaylandi' ? 'selected' : ''; ?>>Onaylandı</option>
                        <option value="tamamlandi" <?php echo $status_filter == 'tamamlandi' ? 'selected' : ''; ?>>Tamamlandı</option>
                        <option value="iptal" <?php echo $status_filter == 'iptal' ? 'selected' : ''; ?>>İptal Edildi</option>
                    </select>
                </div>

                <div>
                    <label for="supplier" class="block text-sm font-medium text-gray-700 mb-1">Tedarikçi</label>
                    <select id="supplier" name="supplier" class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 rounded-md">
                        <option value="0">Tüm Tedarikçiler</option>
                        <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?php echo $supplier['id']; ?>" <?php echo $supplier_filter == $supplier['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($supplier['ad']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="date_start" class="block text-sm font-medium text-gray-700 mb-1">Başlangıç Tarihi</label>
                    <input type="date" id="date_start" name="date_start" value="<?php echo $date_start; ?>" class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 rounded-md">
                </div>

                <div>
                    <label for="date_end" class="block text-sm font-medium text-gray-700 mb-1">Bitiş Tarihi</label>
                    <input type="date" id="date_end" name="date_end" value="<?php echo $date_end; ?>" class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 rounded-md">
                </div>

                <div>
                    <label for="sort_by" class="block text-sm font-medium text-gray-700 mb-1">Sıralama</label>
                    <div class="flex space-x-2">
                        <select id="sort_by" name="sort_by" class="block w-2/3 pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 rounded-md">
                            <option value="tarih" <?php echo $sort_by == 'tarih' ? 'selected' : ''; ?>>Tarih</option>
                            <option value="id" <?php echo $sort_by == 'id' ? 'selected' : ''; ?>>Sipariş No</option>
                            <option value="tedarikci_id" <?php echo $sort_by == 'tedarikci_id' ? 'selected' : ''; ?>>Tedarikçi</option>
                        </select>
                        <select id="sort_dir" name="sort_dir" class="block w-1/3 pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 rounded-md">
                            <option value="ASC" <?php echo $sort_dir == 'ASC' ? 'selected' : ''; ?>>Artan</option>
                            <option value="DESC" <?php echo $sort_dir == 'DESC' ? 'selected' : ''; ?>>Azalan</option>
                        </select>
                    </div>
                </div>

                <div class="md:col-span-5 flex justify-end">
                    <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline-block mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                        </svg>
                        Filtrele
                    </button>
                </div>
            </form>
        </div>

        <!-- Siparişler Tablosu -->
        <div class="bg-white shadow-md rounded-lg overflow-hidden mb-6">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sipariş No</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tedarikçi</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tarih</th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Ürün Sayısı</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Toplam Tutar</th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Durum</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">İşlemler</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($siparisler)): ?>
                        <tr>
                            <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                                Kriterlere uygun sipariş bulunamadı.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($siparisler as $siparis): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    #<?php echo $siparis['id']; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo htmlspecialchars($siparis['tedarikci_adi']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('d.m.Y H:i', strtotime($siparis['tarih'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                    <?php echo $siparis['urun_sayisi']; ?> ürün
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                    ₺<?php echo number_format($siparis['toplam_tutar'] ?? 0, 2, ',', '.'); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $durum_renkleri[$siparis['durum']]; ?>">
                                        <?php echo $durum_metinleri[$siparis['durum']]; ?>
                                    </span>
                                </td>
							<td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
								<button onclick="showOrderDetails(<?php echo $siparis['id']; ?>)" class="text-indigo-600 hover:text-indigo-900 mr-2">
									Detaylar
								</button>
								
								<?php if ($siparis['durum'] == 'beklemede'): ?>
									<button onclick="updateOrderStatus(<?php echo $siparis['id']; ?>, 'onaylandi')" class="text-blue-600 hover:text-blue-900 mr-2">
										Onayla
									</button>
									<button onclick="cancelOrder(<?php echo $siparis['id']; ?>)" class="text-red-600 hover:text-red-900 mr-2">
										İptal Et
									</button>
									<button onclick="generateQuotePDF(<?php echo $siparis['id']; ?>)" class="text-green-600 hover:text-green-900">
										<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline-block mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
											<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
										</svg>
										Teklif Formu
									</button>
								<?php elseif ($siparis['durum'] == 'onaylandi'): ?>
									<button onclick="updateOrderStatus(<?php echo $siparis['id']; ?>, 'tamamlandi')" class="text-green-600 hover:text-green-900 mr-2">
										Tamamla
									</button>
								<?php elseif ($siparis['durum'] == 'tamamlandi'): ?>
									<button onclick="convertToInvoice(<?php echo $siparis['id']; ?>)" class="text-purple-600 hover:text-purple-900 mr-2">
										<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline-block" fill="none" viewBox="0 0 24 24" stroke="currentColor">
											<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
										</svg>
										Faturaya Dönüştür
									</button>
								<?php endif; ?>
							</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Sayfalama -->
        <?php if ($total_pages > 1): ?>
            <div class="flex justify-between items-center">
                <div class="text-sm text-gray-500">
                    Toplam <?php echo $total_records; ?> sipariş
                </div>
                <div class="flex space-x-1">
                    <?php if ($page > 1): ?>
                        <a href="?page=1&status=<?php echo $status_filter; ?>&supplier=<?php echo $supplier_filter; ?>&date_start=<?php echo $date_start; ?>&date_end=<?php echo $date_end; ?>&sort_by=<?php echo $sort_by; ?>&sort_dir=<?php echo $sort_dir; ?>&per_page=<?php echo $per_page; ?>" class="px-3 py-1 bg-gray-200 text-gray-600 rounded hover:bg-gray-300">İlk</a>
                        <a href="?page=<?php echo $page - 1; ?>&status=<?php echo $status_filter; ?>&supplier=<?php echo $supplier_filter; ?>&date_start=<?php echo $date_start; ?>&date_end=<?php echo $date_end; ?>&sort_by=<?php echo $sort_by; ?>&sort_dir=<?php echo $sort_dir; ?>&per_page=<?php echo $per_page; ?>" class="px-3 py-1 bg-gray-200 text-gray-600 rounded hover:bg-gray-300">Önceki</a>
                    <?php endif; ?>
                    
                    <?php
                    $range = 2;
                    $start_page = max(1, $page - $range);
                    $end_page = min($total_pages, $page + $range);
                    
                    for ($i = $start_page; $i <= $end_page; $i++):
                        $active_class = ($i == $page) ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-600 hover:bg-gray-300';
                    ?>
                        <a href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&supplier=<?php echo $supplier_filter; ?>&date_start=<?php echo $date_start; ?>&date_end=<?php echo $date_end; ?>&sort_by=<?php echo $sort_by; ?>&sort_dir=<?php echo $sort_dir; ?>&per_page=<?php echo $per_page; ?>" class="px-3 py-1 <?php echo $active_class; ?> rounded">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&status=<?php echo $status_filter; ?>&supplier=<?php echo $supplier_filter; ?>&date_start=<?php echo $date_start; ?>&date_end=<?php echo $date_end; ?>&sort_by=<?php echo $sort_by; ?>&sort_dir=<?php echo $sort_dir; ?>&per_page=<?php echo $per_page; ?>" class="px-3 py-1 bg-gray-200 text-gray-600 rounded hover:bg-gray-300">Sonraki</a>
                        <a href="?page=<?php echo $total_pages; ?>&status=<?php echo $status_filter; ?>&supplier=<?php echo $supplier_filter; ?>&date_start=<?php echo $date_start; ?>&date_end=<?php echo $date_end; ?>&sort_by=<?php echo $sort_by; ?>&sort_dir=<?php echo $sort_dir; ?>&per_page=<?php echo $per_page; ?>" class="px-3 py-1 bg-gray-200 text-gray-600 rounded hover:bg-gray-300">Son</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Sipariş Detay Modal -->
    <div id="orderDetailModal" style="z-index:10000" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-lg shadow-lg w-full max-w-4xl">
            <div class="flex justify-between items-center px-6 py-4 border-b">
                <h3 class="text-lg font-semibold">Sipariş Detayları</h3>
                <button id="closeOrderDetailBtn" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div id="orderDetailContent" class="p-6">
                <!-- Detaylar AJAX ile yüklenecek -->
                <div class="flex justify-center">
                    <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-blue-500"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sipariş Durum Güncelleme Modal -->
	<div id="updateStatusModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
		<div class="bg-white rounded-lg shadow-lg w-full max-w-md">
			<div class="flex justify-between items-center px-6 py-4 border-b">
				<h3 class="text-lg font-semibold">Sipariş Durumunu Güncelle</h3>
				<button id="closeUpdateStatusBtn" class="text-gray-500 hover:text-gray-700">
					<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
					</svg>
				</button>
			</div>
			<form action="" method="POST" id="updateStatusForm" class="p-6">
				<input type="hidden" id="siparis_id" name="siparis_id">
				<input type="hidden" id="yeni_durum" name="yeni_durum">
				
				<div class="mb-4">
					<label for="aciklama" class="block text-sm font-medium text-gray-700 mb-1">Açıklama (Opsiyonel)</label>
					<textarea id="aciklama" name="aciklama" rows="3" class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 rounded-md"></textarea>
				</div>
				
				<div id="stockOptionsContainer" class="mb-4 hidden">
					<div class="flex items-start">
						<div class="flex items-center h-5">
							<input id="add_to_stock" name="add_to_stock" type="checkbox" value="1" class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
						</div>
						<div class="ml-3 text-sm">
							<label for="add_to_stock" class="font-medium text-gray-700">Ürünleri stoğa ekle</label>
							<p class="text-gray-500">Bu seçeneği işaretlerseniz, siparişteki tüm ürünler seçili depo veya mağazaya eklenecektir.</p>
						</div>
					</div>
					
					<!-- Stok Ekleme Yeri Seçimi -->
					<div class="mt-3">
						<label class="block text-sm font-medium text-gray-700 mb-2">Stok Ekleme Yeri</label>
						<div class="flex space-x-4">
							<div class="flex items-center">
								<input type="radio" id="stok_yer_depo" name="stok_yer" value="depo" checked 
									  class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300" 
									  onchange="toggleStokYeriSecim('depo')">
								<label for="stok_yer_depo" class="ml-2 block text-sm text-gray-700">Depo</label>
							</div>
							<div class="flex items-center">
								<input type="radio" id="stok_yer_magaza" name="stok_yer" value="magaza" 
									  class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300"
									  onchange="toggleStokYeriSecim('magaza')">
								<label for="stok_yer_magaza" class="ml-2 block text-sm text-gray-700">Mağaza</label>
							</div>
						</div>
					</div>
					
					<!-- Depo Seçimi -->
					<div class="mt-3" id="depoSecimContainer">
						<label for="depo_id" class="block text-sm font-medium text-gray-700 mb-1">Depo Seçimi</label>
						<select id="depo_id" name="depo_id" class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 rounded-md">
							<?php foreach ($warehouses as $warehouse): ?>
								<option value="<?php echo $warehouse['id']; ?>">
									<?php echo htmlspecialchars($warehouse['ad']); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					
					<!-- Mağaza Seçimi Bölümü -->
					<div class="mt-3" id="magazaSecimContainer">
						<label for="magaza_id" class="block text-sm font-medium text-gray-700 mb-1">Mağaza Seçimi</label>
						<select id="magaza_id" name="magaza_id" class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 rounded-md">
							<?php if (empty($magazalar)): ?>
								<option value="" disabled selected>Mağaza bulunamadı</option>
							<?php else: ?>
								<?php foreach ($magazalar as $magaza): ?>
									<option value="<?php echo $magaza['id']; ?>">
										<?php echo htmlspecialchars($magaza['ad']); ?>
									</option>
								<?php endforeach; ?>
							<?php endif; ?>
						</select>
						<?php if (empty($magazalar)): ?>
							<p class="text-red-500 text-xs mt-1">Sistemde kayıtlı mağaza bulunamadı. Lütfen önce mağaza ekleyin.</p>
						<?php endif; ?>
					</div>
				</div>
				
				<div class="flex justify-end">
					<button type="button" id="cancelUpdateStatusBtn" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md mr-2">
						İptal
					</button>
					<button type="submit" name="update_status" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md">
						Güncelle
					</button>
				</div>
			</form>
		</div>
	</div>

    <!-- Sipariş İptal Modal -->
    <div id="cancelOrderModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-lg shadow-lg w-full max-w-md">
            <div class="flex justify-between items-center px-6 py-4 border-b">
                <h3 class="text-lg font-semibold">Siparişi İptal Et</h3>
                <button id="closeCancelOrderBtn" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <form action="" method="POST" class="p-6">
                <input type="hidden" id="cancel_siparis_id" name="siparis_id">
                
                <div class="mb-4">
                    <label for="iptal_sebebi" class="block text-sm font-medium text-gray-700 mb-1">İptal Sebebi</label>
                    <textarea id="iptal_sebebi" name="iptal_sebebi" rows="3" class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 rounded-md" required></textarea>
                </div>
                
                <div class="flex justify-end">
                    <button type="button" id="cancelCancelOrderBtn" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md mr-2">
                        Vazgeç
                    </button>
                    <button type="submit" name="cancel_order" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-md">
                        Siparişi İptal Et
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Detaylar Modalı
            const orderDetailModal = document.getElementById('orderDetailModal');
            const closeOrderDetailBtn = document.getElementById('closeOrderDetailBtn');
            
            if (closeOrderDetailBtn) {
                closeOrderDetailBtn.addEventListener('click', function() {
                    orderDetailModal.classList.add('hidden');
                });
            }
            
            // Durum Güncelleme Modalı
            const updateStatusModal = document.getElementById('updateStatusModal');
            const closeUpdateStatusBtn = document.getElementById('closeUpdateStatusBtn');
            const cancelUpdateStatusBtn = document.getElementById('cancelUpdateStatusBtn');
            
            if (closeUpdateStatusBtn) {
                closeUpdateStatusBtn.addEventListener('click', function() {
                    updateStatusModal.classList.add('hidden');
                });
            }
            
            if (cancelUpdateStatusBtn) {
                cancelUpdateStatusBtn.addEventListener('click', function() {
                    updateStatusModal.classList.add('hidden');
                });
            }
            
            // Sipariş İptal Modalı
            const cancelOrderModal = document.getElementById('cancelOrderModal');
            const closeCancelOrderBtn = document.getElementById('closeCancelOrderBtn');
            const cancelCancelOrderBtn = document.getElementById('cancelCancelOrderBtn');
            
            if (closeCancelOrderBtn) {
                closeCancelOrderBtn.addEventListener('click', function() {
                    cancelOrderModal.classList.add('hidden');
                });
            }
            
            if (cancelCancelOrderBtn) {
                cancelCancelOrderBtn.addEventListener('click', function() {
                    cancelOrderModal.classList.add('hidden');
                });
            }
            
            // Stoğa ekle checkbox'ı değiştiğinde
            const addToStockCheckbox = document.getElementById('add_to_stock');
            const warehouseSelector = document.getElementById('warehouseSelector');
            
            if (addToStockCheckbox) {
                addToStockCheckbox.addEventListener('change', function() {
                    if (this.checked) {
                        warehouseSelector.classList.remove('hidden');
                    } else {
                        warehouseSelector.classList.add('hidden');
                    }
                });
            }
			
			    // Başlangıçta radio butonlarına göre doğru container'ı göster
				const depoRadio = document.getElementById('stok_yer_depo');
				const magazaRadio = document.getElementById('stok_yer_magaza');
				
				if (depoRadio && depoRadio.checked) {
					toggleStokYeriSecim('depo');
				} else if (magazaRadio && magazaRadio.checked) {
					toggleStokYeriSecim('magaza');
				}
				
				// Radio butonlarına event listener'lar ekle
				if (depoRadio) {
					depoRadio.addEventListener('change', function() {
						if (this.checked) toggleStokYeriSecim('depo');
					});
				}
				
				if (magazaRadio) {
					magazaRadio.addEventListener('change', function() {
						if (this.checked) toggleStokYeriSecim('magaza');
					});
				}
        });
        
        // Sipariş detaylarını görüntüle
        function showOrderDetails(orderId) {
            const modal = document.getElementById('orderDetailModal');
            const contentDiv = document.getElementById('orderDetailContent');
            
            modal.classList.remove('hidden');
            contentDiv.innerHTML = '<div class="flex justify-center"><div class="animate-spin rounded-full h-10 w-10 border-b-2 border-blue-500"></div></div>';
            
            // AJAX ile sipariş detaylarını al
            fetch('api/get_purchase_order_details.php?id=' + orderId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let html = `
                            <div class="mb-6">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <h4 class="text-sm font-medium text-gray-500">Sipariş No</h4>
                                        <p class="text-base">#${data.order.id}</p>
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-medium text-gray-500">Tedarikçi</h4>
                                        <p class="text-base">${data.order.tedarikci_adi}</p>
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-medium text-gray-500">Tarih</h4>
                                        <p class="text-base">${data.order.tarih}</p>
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-medium text-gray-500">Durum</h4>
                                        <p class="text-base">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${data.order.durum_renk}">
                                                ${data.order.durum_text}
                                            </span>
                                        </p>
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-medium text-gray-500">Oluşturan</h4>
                                        <p class="text-base">${data.order.kullanici_adi || 'Bilinmiyor'}</p>
                                    </div>
                                </div>
                                ${data.order.notlar ? `
                                <div class="mt-4">
                                    <h4 class="text-sm font-medium text-gray-500">Notlar</h4>
                                    <p class="text-base">${data.order.notlar}</p>
                                </div>` : ''}
                            </div>
                            
                            <div class="border-t pt-4">
                                <h4 class="text-base font-medium mb-3">Sipariş Ürünleri</h4>
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ürün</th>
                                            <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Miktar</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Birim Fiyat</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Toplam</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">`;
                        
                        let totalAmount = 0;
                        data.items.forEach(item => {
                            const itemTotal = item.miktar * item.birim_fiyat;
                            totalAmount += itemTotal;
                            
                            html += `
                                <tr>
                                    <td class="px-4 py-2 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">${item.urun_adi}</div>
                                        <div class="text-xs text-gray-500">${item.urun_kodu || ''}</div>
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500 text-center">
                                        ${item.miktar}
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500 text-right">
                                        ₺${parseFloat(item.birim_fiyat).toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900 text-right">
                                        ₺${itemTotal.toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}
                                    </td>
                                </tr>`;
                        });
                        
                        html += `
                                    </tbody>
                                    <tfoot>
                                        <tr class="bg-gray-50">
                                            <td colspan="3" class="px-4 py-2 whitespace-nowrap text-sm font-medium text-gray-900 text-right">
                                                Toplam:
                                            </td>
                                            <td class="px-4 py-2 whitespace-nowrap text-sm font-bold text-gray-900 text-right">
                                                ₺${totalAmount.toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>`;
                        
                        if (data.logs && data.logs.length > 0) {
                            html += `
                                <div class="border-t pt-4 mt-4">
                                    <h4 class="text-base font-medium mb-3">Sipariş Geçmişi</h4>
                                    <div class="space-y-3">`;
                            
                            data.logs.forEach(log => {
                                html += `
                                    <div class="flex items-start">
                                        <div class="flex-shrink-0">
                                            <div class="h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center">
                                                <svg class="h-5 w-5 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                            </div>
                                        </div>
                                        <div class="ml-3">
                                            <div class="text-sm text-gray-500">${log.tarih}</div>
                                            <div class="text-sm">${log.aciklama}</div>
                                            ${log.kullanici_adi ? `<div class="text-xs text-gray-500">İşlemi yapan: ${log.kullanici_adi}</div>` : ''}
                                        </div>
                                    </div>`;
                            });
                            
                            html += `
                                    </div>
                                </div>`;
                        }
                        
                        contentDiv.innerHTML = html;
                    } else {
                        contentDiv.innerHTML = `<div class="text-center text-red-500">${data.message || 'Sipariş detayları alınamadı.'}</div>`;
                    }
                })
                .catch(error => {
                    console.error('Sipariş detayları alınırken bir hata oluştu:', error);
                    contentDiv.innerHTML = '<div class="text-center text-red-500">Bir hata oluştu. Lütfen tekrar deneyin.</div>';
                });
        }
        
        // Sipariş durumunu güncelle
        function updateOrderStatus(orderId, newStatus) {
            const modal = document.getElementById('updateStatusModal');
            document.getElementById('siparis_id').value = orderId;
            document.getElementById('yeni_durum').value = newStatus;
            
            // Eğer tamamlanıyor ise stok seçeneklerini göster
            const stockOptionsContainer = document.getElementById('stockOptionsContainer');
            if (newStatus === 'tamamlandi') {
                stockOptionsContainer.classList.remove('hidden');
            } else {
                stockOptionsContainer.classList.add('hidden');
            }
            
            modal.classList.remove('hidden');
        }
        
        // Siparişi iptal et
        function cancelOrder(orderId) {
            const modal = document.getElementById('cancelOrderModal');
            document.getElementById('cancel_siparis_id').value = orderId;
            modal.classList.remove('hidden');
        }
        
        // Siparişi faturaya dönüştürme fonksiyonu
        function convertToInvoice(siparisId) {
            Swal.fire({
                title: 'Faturaya Dönüştür',
                html: `
                    <form id="convertToInvoiceForm" class="text-left">
                        <input type="hidden" name="siparis_id" value="${siparisId}">
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700">Fatura Seri*</label>
                            <input type="text" name="fatura_seri" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700">Fatura No*</label>
                            <input type="text" name="fatura_no" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700">Fatura Tarihi*</label>
                            <input type="date" name="fatura_tarihi" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" value="${new Date().toISOString().split('T')[0]}" required>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700">İrsaliye No</label>
                            <input type="text" name="irsaliye_no" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700">İrsaliye Tarihi</label>
                            <input type="date" name="irsaliye_tarihi" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700">Açıklama</label>
                            <textarea name="aciklama" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"></textarea>
                        </div>
                    </form>
                `,
                width: '600px',
                showCancelButton: true,
                confirmButtonText: 'Fatura Oluştur',
                cancelButtonText: 'İptal',
                preConfirm: () => {
                    const form = document.getElementById('convertToInvoiceForm');
                    
                    // Zorunlu alanları kontrol et
                    if (!form.fatura_seri.value || !form.fatura_no.value || !form.fatura_tarihi.value) {
                        Swal.showValidationMessage('Lütfen zorunlu alanları doldurun');
                        return false;
                    }
                    
                    // Form verilerini topla
                    const formData = new FormData(form);
                    return { formData };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const { formData } = result.value;
                    
                    // Faturaya dönüştürme API'sini çağır
                    fetch('api/convert_to_invoice.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Başarılı!',
                                text: data.message || 'Sipariş başarıyla faturaya dönüştürüldü.',
                                showConfirmButton: false,
                                timer: 2000
                            }).then(() => {
                                // Başarılı işlemden sonra sayfayı yenile
                                window.location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Hata!',
                                text: data.message || 'Faturaya dönüştürme işlemi sırasında bir hata oluştu.'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Faturaya dönüştürme hatası:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Hata!',
                            text: 'Sunucu ile iletişim sırasında bir hata oluştu.'
                        });
                    });
                }
            });
        }
		
		    // Stok ekleme yeri seçimine göre görünüm değiştir
			function toggleStokYeriSecim(yer) {
				const depoContainer = document.getElementById('depoSecimContainer');
				const magazaContainer = document.getElementById('magazaSecimContainer');
				
				if (yer === 'depo') {
					// Depo seçildiğinde:
					// - Depo seçim kutusunu göster (hidden sınıfını kaldır)
					// - Mağaza seçim kutusunu gizle (hidden sınıfını ekle)
					depoContainer.classList.remove('hidden');
					magazaContainer.classList.add('hidden');
					
					// Logla (hata ayıklama için)
					console.log('Depo seçildi, depo container gösteriliyor.');
				} else {
					// Mağaza seçildiğinde:
					// - Depo seçim kutusunu gizle (hidden sınıfını ekle)
					// - Mağaza seçim kutusunu göster (hidden sınıfını kaldır)
					depoContainer.classList.add('hidden');
					magazaContainer.classList.remove('hidden');
					
					// Logla (hata ayıklama için)
					console.log('Mağaza seçildi, mağaza container gösteriliyor.');
					
					// Mağaza seçimi kontrolü
					const magazaSelect = document.getElementById('magaza_id');
					if (magazaSelect && magazaSelect.options.length === 0) {
						console.warn('Mağaza seçimi için hiç seçenek yok!');
					} else if (magazaSelect) {
						console.log('Mağaza seçimi için ' + magazaSelect.options.length + ' adet seçenek var.');
					}
				}
			}
			// Teklif formu oluşturma ve indirme fonksiyonu
function generateQuotePDF(siparisId) {
    // PDF oluşturmak için API çağrısı yap
    fetch('api/generate_quote_pdf.php?id=' + siparisId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // PDF başarıyla oluşturuldu, kullanıcıya dosyayı indirme seçeneği sun
                Swal.fire({
                    icon: 'success',
                    title: 'Teklif Formu Hazır',
                    text: 'Teklif formu başarıyla oluşturuldu. Şimdi indirebilirsiniz.',
                    confirmButtonText: 'İndir',
                    showCancelButton: true,
                    cancelButtonText: 'İptal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // PDF dosyasını indir
                        window.location.href = data.file_path;
                    }
                });
            } else {
                // Hata durumunda kullanıcıya bildir
                Swal.fire({
                    icon: 'error',
                    title: 'Hata!',
                    text: data.message || 'Teklif formu oluşturulurken bir hata oluştu.'
                });
            }
        })
        .catch(error => {
            console.error('Teklif formu hatası:', error);
            Swal.fire({
                icon: 'error',
                title: 'Hata!',
                text: 'Sunucu ile iletişim sırasında bir hata oluştu.'
            });
        });
}

// Sweetalert2 kütüphanesi yoksa ekleme kodu
if (typeof Swal === 'undefined') {
    console.log('SweetAlert2 yükleniyor...');
    const script = document.createElement('script');
    script.src = 'https://cdn.jsdelivr.net/npm/sweetalert2@11';
    script.async = true;
    document.head.appendChild(script);
}
    </script>

<?php
// Sayfa özel scriptleri
$page_scripts = '';

// Footer'ı dahil et
include 'footer.php';
?>