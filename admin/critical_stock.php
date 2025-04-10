<?php
// Mevcut sayfanın ilk kısmı aynı kalacak, sadece en çok satılan ürünleri çeken ek bir sorgu ekleyeceğiz

// Sayfa yüklenme süresini hesapla
$start_time = microtime(true);

// Header'ı dahil et
include 'header.php';
require_once 'db_connection.php';
require_once 'stock_functions.php';

// Kullanıcı giriş kontrolü
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: index.php");
    exit;
}

// Kritik stok seviyesini ayarlardan al (varsayılan: 10)
$critical_level_query = "SELECT deger FROM sistem_ayarlari WHERE anahtar = 'kritik_stok_seviyesi'";
$stmt = $conn->prepare($critical_level_query);
$stmt->execute();
$critical_level = $stmt->fetchColumn();
if (!$critical_level) {
    $critical_level = 10; // Varsayılan değer
}

// Filtreler
$department_filter = isset($_GET['department']) ? intval($_GET['department']) : 0;
$supplier_filter = isset($_GET['supplier']) ? intval($_GET['supplier']) : 0;
$stock_status = isset($_GET['stock_status']) ? $_GET['stock_status'] : 'all';
$order_by = isset($_GET['order_by']) ? $_GET['order_by'] : 'stok_miktari';
$order_direction = isset($_GET['order_direction']) ? $_GET['order_direction'] : 'ASC';
$date_range = isset($_GET['date_range']) ? $_GET['date_range'] : '30'; // Varsayılan 30 gün

// En çok satılan ürünleri almak için sorgu
$sales_days = intval($date_range);
if ($sales_days <= 0) $sales_days = 30; // Güvenlik kontrolü

$top_selling_query = "SELECT 
                        us.id,
                        us.barkod,
                        us.kod,
                        us.ad,
                        us.alis_fiyati,
                        us.satis_fiyati,
                        COALESCE((SELECT SUM(ds.stok_miktari) FROM depo_stok ds WHERE ds.urun_id = us.id), 0) +
                        COALESCE((SELECT SUM(ms.stok_miktari) FROM magaza_stok ms WHERE ms.barkod = us.barkod), 0) as stok_miktari,
                        d.ad as departman,
                        ag.ad as ana_grup,
                        alg.ad as alt_grup,
                        b.ad as birim,
                        SUM(sfd.miktar) as total_sold,
                        COUNT(DISTINCT sf.id) as order_count,
                        (SELECT t.ad FROM tedarikciler t 
                         INNER JOIN alis_fatura_detay afd ON afd.urun_id = us.id 
                         INNER JOIN alis_faturalari af ON afd.fatura_id = af.id 
                         WHERE af.tedarikci = t.id 
                         ORDER BY af.fatura_tarihi DESC LIMIT 1) as son_tedarikci,
                        (SELECT tedarikci FROM alis_faturalari WHERE id = 
                            (SELECT fatura_id FROM alis_fatura_detay WHERE urun_id = us.id ORDER BY id DESC LIMIT 1)
                        ) as tedarikci_id,
                        (SELECT MAX(af.fatura_tarihi) FROM alis_faturalari af 
                         INNER JOIN alis_fatura_detay afd ON af.id = afd.fatura_id 
                         WHERE afd.urun_id = us.id) as son_alis_tarihi
                      FROM satis_fatura_detay sfd
                      JOIN satis_faturalari sf ON sfd.fatura_id = sf.id
                      JOIN urun_stok us ON sfd.urun_id = us.id
                      LEFT JOIN departmanlar d ON us.departman_id = d.id
                      LEFT JOIN ana_gruplar ag ON us.ana_grup_id = ag.id
                      LEFT JOIN alt_gruplar alg ON us.alt_grup_id = alg.id
                      LEFT JOIN birimler b ON us.birim_id = b.id
                      WHERE sf.fatura_tarihi >= DATE_SUB(CURDATE(), INTERVAL {$sales_days} DAY)
                      AND sf.islem_turu = 'satis'
                      AND us.durum = 'aktif'";

// Departman filtresi uygulanıyor
if ($department_filter > 0) {
    $top_selling_query .= " AND us.departman_id = $department_filter";
}

$top_selling_query .= " GROUP BY us.id
                        ORDER BY total_sold DESC
                        LIMIT 20";

$stmt = $conn->prepare($top_selling_query);
$stmt->execute();
$top_selling_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Kritik stok ürünlerini alma kısmı...
// Mevcut sayfanın aynı sorgusu ile devam eder

$query = "SELECT 
            us.id,
            us.barkod,
            us.kod,
            us.ad,
            us.alis_fiyati,
            us.satis_fiyati,
            COALESCE((SELECT SUM(ds.stok_miktari) FROM depo_stok ds WHERE ds.urun_id = us.id), 0) +
            COALESCE((SELECT SUM(ms.stok_miktari) FROM magaza_stok ms WHERE ms.barkod = us.barkod), 0) as stok_miktari,
            d.ad as departman,
            ag.ad as ana_grup,
            alg.ad as alt_grup,
            b.ad as birim,
            (SELECT t.ad FROM tedarikciler t 
             INNER JOIN alis_fatura_detay afd ON afd.urun_id = us.id 
             INNER JOIN alis_faturalari af ON afd.fatura_id = af.id 
             WHERE af.tedarikci = t.id 
             ORDER BY af.fatura_tarihi DESC LIMIT 1) as son_tedarikci,
            (SELECT tedarikci FROM alis_faturalari WHERE id = 
                (SELECT fatura_id FROM alis_fatura_detay WHERE urun_id = us.id ORDER BY id DESC LIMIT 1)
            ) as tedarikci_id,
            (SELECT MAX(af.fatura_tarihi) FROM alis_faturalari af 
             INNER JOIN alis_fatura_detay afd ON af.id = afd.fatura_id 
             WHERE afd.urun_id = us.id) as son_alis_tarihi,
            (SELECT AVG(afd.birim_fiyat) FROM alis_fatura_detay afd 
             WHERE afd.urun_id = us.id
             ORDER BY afd.id DESC LIMIT 3) as ortalama_alis_fiyati
          FROM urun_stok us
          LEFT JOIN departmanlar d ON us.departman_id = d.id
          LEFT JOIN ana_gruplar ag ON us.ana_grup_id = ag.id
          LEFT JOIN alt_gruplar alg ON us.alt_grup_id = alg.id
          LEFT JOIN birimler b ON us.birim_id = b.id
          WHERE us.durum = 'aktif'";

// Stok durumu filtresi uygulanıyor
if ($stock_status == 'critical') {
    $query .= " AND (
        COALESCE((SELECT SUM(ds.stok_miktari) FROM depo_stok ds WHERE ds.urun_id = us.id), 0) +
        COALESCE((SELECT SUM(ms.stok_miktari) FROM magaza_stok ms WHERE ms.barkod = us.barkod), 0)
    ) <= $critical_level AND (
        COALESCE((SELECT SUM(ds.stok_miktari) FROM depo_stok ds WHERE ds.urun_id = us.id), 0) +
        COALESCE((SELECT SUM(ms.stok_miktari) FROM magaza_stok ms WHERE ms.barkod = us.barkod), 0)
    ) > 0";
} elseif ($stock_status == 'out') {
    $query .= " AND (
        COALESCE((SELECT SUM(ds.stok_miktari) FROM depo_stok ds WHERE ds.urun_id = us.id), 0) +
        COALESCE((SELECT SUM(ms.stok_miktari) FROM magaza_stok ms WHERE ms.barkod = us.barkod), 0)
    ) = 0";
} elseif ($stock_status == 'low') {
    $query .= " AND (
        COALESCE((SELECT SUM(ds.stok_miktari) FROM depo_stok ds WHERE ds.urun_id = us.id), 0) +
        COALESCE((SELECT SUM(ms.stok_miktari) FROM magaza_stok ms WHERE ms.barkod = us.barkod), 0)
    ) <= " . ($critical_level * 2) . " AND (
        COALESCE((SELECT SUM(ds.stok_miktari) FROM depo_stok ds WHERE ds.urun_id = us.id), 0) +
        COALESCE((SELECT SUM(ms.stok_miktari) FROM magaza_stok ms WHERE ms.barkod = us.barkod), 0)
    ) > $critical_level";
}

// Departman filtresi uygulanıyor
if ($department_filter > 0) {
    $query .= " AND us.departman_id = $department_filter";
}

// Tedarikçi filtresi uygulanıyor
if ($supplier_filter > 0) {
    $query .= " AND EXISTS (
        SELECT 1 FROM alis_fatura_detay afd 
        JOIN alis_faturalari af ON afd.fatura_id = af.id
        WHERE afd.urun_id = us.id AND af.tedarikci = $supplier_filter
    )";
}

// Sıralama uygulanıyor
$query .= " ORDER BY ";
if ($order_by == 'stok_miktari') {
    $query .= "(
        COALESCE((SELECT SUM(ds.stok_miktari) FROM depo_stok ds WHERE ds.urun_id = us.id), 0) +
        COALESCE((SELECT SUM(ms.stok_miktari) FROM magaza_stok ms WHERE ms.barkod = us.barkod), 0)
    )";
} elseif ($order_by == 'alis_fiyati') {
    $query .= "us.alis_fiyati";
} elseif ($order_by == 'son_alis_tarihi') {
    $query .= "(SELECT MAX(af.fatura_tarihi) FROM alis_faturalari af 
             INNER JOIN alis_fatura_detay afd ON af.id = afd.fatura_id 
             WHERE afd.urun_id = us.id)";
} else {
    $query .= "us.ad";
}
$query .= " $order_direction";

// Sorguyu çalıştır
$stmt = $conn->prepare($query);
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Tedarikçileri al
$supplier_query = "SELECT id, ad FROM tedarikciler ORDER BY ad";
$suppliers = $conn->query($supplier_query)->fetchAll(PDO::FETCH_ASSOC);

// Departmanları al
$department_query = "SELECT id, ad FROM departmanlar ORDER BY ad";
$departments = $conn->query($department_query)->fetchAll(PDO::FETCH_ASSOC);

// Sepette kaç ürün olduğunu sorgula
$order_basket_count = 0;
if (isset($_SESSION['purchase_order_basket']) && is_array($_SESSION['purchase_order_basket'])) {
    $order_basket_count = count($_SESSION['purchase_order_basket']);
}

// Sipariş oluşturma işlemi
if (isset($_POST['create_order'])) {
    if (!empty($_SESSION['purchase_order_basket'])) {
        $supplier_id = $_POST['supplier_id'];
        $notes = $_POST['notes'];
        
        // Siparişi kaydet
        $order_query = "INSERT INTO siparisler (
            tedarikci_id, 
            tarih, 
            durum, 
            notlar, 
            kullanici_id
        ) VALUES (
            :tedarikci_id,
            NOW(),
            'beklemede',
            :notlar,
            :kullanici_id
        )";
        
        $stmt = $conn->prepare($order_query);
        $stmt->bindParam(':tedarikci_id', $supplier_id);
        $stmt->bindParam(':notlar', $notes);
        $stmt->bindParam(':kullanici_id', $_SESSION['user_id']);
        $stmt->execute();
        
        $order_id = $conn->lastInsertId();
        
        // Sipariş detaylarını kaydet
        foreach ($_SESSION['purchase_order_basket'] as $product) {
            $detail_query = "INSERT INTO siparis_detay (
                siparis_id,
                urun_id,
                miktar,
                birim_fiyat
            ) VALUES (
                :siparis_id,
                :urun_id,
                :miktar,
                :birim_fiyat
            )";
            
            $stmt = $conn->prepare($detail_query);
            $stmt->bindParam(':siparis_id', $order_id);
            $stmt->bindParam(':urun_id', $product['id']);
            $stmt->bindParam(':miktar', $product['quantity']);
            $stmt->bindParam(':birim_fiyat', $product['price']);
            $stmt->execute();
        }
        
        // Sepeti temizle
        unset($_SESSION['purchase_order_basket']);
        
        // Başarı mesajı
        $success_message = "Sipariş başarıyla oluşturuldu. Sipariş No: " . $order_id;
    } else {
        $error_message = "Sepet boş! Sipariş oluşturulamadı.";
    }
}

// Sepete ürün ekleme işlemi
if (isset($_POST['add_to_basket'])) {
    $product_id = $_POST['product_id'];
    $quantity = $_POST['quantity'];
    $price = $_POST['price'];
    $product_name = $_POST['product_name'];
    
    if (!isset($_SESSION['purchase_order_basket'])) {
        $_SESSION['purchase_order_basket'] = [];
    }
    
    // Ürün sepette varsa sadece miktarını güncelle
    $found = false;
    foreach ($_SESSION['purchase_order_basket'] as $key => $item) {
        if ($item['id'] == $product_id) {
            $_SESSION['purchase_order_basket'][$key]['quantity'] += $quantity;
            $found = true;
            break;
        }
    }
    
    // Ürün sepette yoksa ekle
    if (!$found) {
        $_SESSION['purchase_order_basket'][] = [
            'id' => $product_id,
            'name' => $product_name,
            'quantity' => $quantity,
            'price' => $price
        ];
    }
    
    $success_message = "Ürün sepete eklendi.";
    
    // Sepetteki ürün sayısını güncelle
    $order_basket_count = count($_SESSION['purchase_order_basket']);
}

// Sepetten ürün çıkarma işlemi
if (isset($_GET['remove_from_basket'])) {
    $product_id = $_GET['remove_from_basket'];
    
    if (isset($_SESSION['purchase_order_basket'])) {
        foreach ($_SESSION['purchase_order_basket'] as $key => $item) {
            if ($item['id'] == $product_id) {
                unset($_SESSION['purchase_order_basket'][$key]);
                break;
            }
        }
        
        // Dizinin anahtarlarını yeniden indeksle
        $_SESSION['purchase_order_basket'] = array_values($_SESSION['purchase_order_basket']);
        
        // Sepetteki ürün sayısını güncelle
        $order_basket_count = count($_SESSION['purchase_order_basket']);
    }
}

// Sepeti temizleme işlemi - en başta, çıktı göndermeden önce yap
if (isset($_GET['clear_basket'])) {
    unset($_SESSION['purchase_order_basket']);
    $order_basket_count = 0;
    
    // Header kullanmak yerine JavaScript ile yönlendirme yapacağız
    $redirect_after_clear = true;
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
    <title>Kritik Stok & Satın Alma Fırsatları - İnciPOS</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
	<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background-color: #EF4444;
            color: white;
            border-radius: 50%;
            width: 22px;
            height: 22px;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .recommendation-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: #F59E0B;
            color: white;
            border-radius: 4px;
            padding: 2px 6px;
            font-size: 10px;
            font-weight: bold;
        }
        
        .product-card {
            transition: all 0.3s ease;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
		}	
		.badge {
			position: absolute;
			top: -8px;
			right: -8px;
			background-color: #EF4444;
			color: white;
			border-radius: 50%;
			width: 22px;
			height: 22px;
			font-size: 12px;
			display: flex;
			align-items: center;
			justify-content: center;
		}
    </style>
</head>
<body>
    <div class="container mx-auto px-4 py-8">
        <!-- Başlık ve Butonlar -->
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold">Kritik Stok & Satın Alma Fırsatları</h1>
                <p class="text-gray-600">Stok seviyesi düşük ürünleri görüntüleyin ve sipariş oluşturun</p>
            </div>
            
            <!-- Sepet butonu -->
            <div class="flex items-center gap-4">
				<div class="relative">
					<button id="showBasketBtn" class="bg-indigo-500 hover:bg-indigo-600 text-white px-4 py-2 rounded-md flex items-center">
						<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
						</svg>
						Sipariş Sepeti
						<?php if ($order_basket_count > 0): ?>
							<span id="basketCount" class="badge"><?php echo $order_basket_count; ?></span>
						<?php else: ?>
							<span id="basketCount" class="badge hidden">0</span>
						<?php endif; ?>
					</button>
				</div>
				
				<a href="siparisler.php" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md flex items-center">
					<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
					</svg>
					Siparişler
				</a>
			</div>
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
            <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-4">
                <div>
                    <label for="stock_status" class="block text-sm font-medium text-gray-700 mb-1">Stok Durumu</label>
                    <select id="stock_status" name="stock_status" class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 rounded-md">
                        <option value="all" <?php echo $stock_status == 'all' ? 'selected' : ''; ?>>Tüm Ürünler</option>
                        <option value="critical" <?php echo $stock_status == 'critical' ? 'selected' : ''; ?>>Kritik Seviye (1-<?php echo $critical_level; ?>)</option>
                        <option value="out" <?php echo $stock_status == 'out' ? 'selected' : ''; ?>>Tükenenler (0)</option>
                        <option value="low" <?php echo $stock_status == 'low' ? 'selected' : ''; ?>>Düşük Seviye (<?php echo $critical_level+1; ?>-<?php echo $critical_level*2; ?>)</option>
                    </select>
                </div>

                <div>
                    <label for="department" class="block text-sm font-medium text-gray-700 mb-1">Departman</label>
                    <select id="department" name="department" class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 rounded-md">
                        <option value="0">Tüm Departmanlar</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['id']; ?>" <?php echo $department_filter == $dept['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept['ad']); ?>
                            </option>
                        <?php endforeach; ?>
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
                    <label for="date_range" class="block text-sm font-medium text-gray-700 mb-1">Satış Verileri</label>
                    <select id="date_range" name="date_range" class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 rounded-md">
                        <option value="7" <?php echo $date_range == '7' ? 'selected' : ''; ?>>Son 7 Gün</option>
                        <option value="30" <?php echo $date_range == '30' ? 'selected' : ''; ?>>Son 30 Gün</option>
                        <option value="90" <?php echo $date_range == '90' ? 'selected' : ''; ?>>Son 3 Ay</option>
                        <option value="180" <?php echo $date_range == '180' ? 'selected' : ''; ?>>Son 6 Ay</option>
                        <option value="365" <?php echo $date_range == '365' ? 'selected' : ''; ?>>Son 1 Yıl</option>
                    </select>
                </div>

                <div>
                    <label for="order_by" class="block text-sm font-medium text-gray-700 mb-1">Sıralama</label>
                    <select id="order_by" name="order_by" class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 rounded-md">
                        <option value="stok_miktari" <?php echo $order_by == 'stok_miktari' ? 'selected' : ''; ?>>Stok Miktarı</option>
                        <option value="ad" <?php echo $order_by == 'ad' ? 'selected' : ''; ?>>Ürün Adı</option>
                        <option value="alis_fiyati" <?php echo $order_by == 'alis_fiyati' ? 'selected' : ''; ?>>Alış Fiyatı</option>
                        <option value="son_alis_tarihi" <?php echo $order_by == 'son_alis_tarihi' ? 'selected' : ''; ?>>Son Alış Tarihi</option>
                    </select>
                </div>

                <div>
                    <label for="order_direction" class="block text-sm font-medium text-gray-700 mb-1">Yön</label>
                    <select id="order_direction" name="order_direction" class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 rounded-md">
                        <option value="ASC" <?php echo $order_direction == 'ASC' ? 'selected' : ''; ?>>Artan</option>
                        <option value="DESC" <?php echo $order_direction == 'DESC' ? 'selected' : ''; ?>>Azalan</option>
                    </select>
                </div>

                <div class="md:col-span-6 flex justify-end">
                    <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline-block mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                        </svg>
                        Filtrele
                    </button>
                </div>
            </form>
        </div>

        <!-- En Çok Satan Ürünler -->
        <div class="bg-white shadow-md rounded-lg p-6 mb-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-semibold">En Çok Satan Ürünler (Son <?php echo $sales_days; ?> Gün)</h2>
                <span class="text-sm text-gray-500">Satış verileri baz alınarak önerilen ürünler</span>
            </div>
            
								<?php if (empty($top_selling_products)): ?>
									<div class="text-center p-4 bg-gray-50 rounded-md">
										<p class="text-gray-500">Bu kriterlere göre satış verisi bulunamadı.</p>
									</div>
								<?php else: ?>
									<div class="grid grid-cols-1 md:grid-cols-5 gap-4">
						<?php foreach ($top_selling_products as $index => $product): ?>
							<div class="bg-white border rounded-lg shadow-sm overflow-hidden product-card relative">
								<?php if ($index < 5): ?>
									<span class="recommendation-badge">
										<?php echo $index === 0 ? 'En Çok Satan' : 'Top ' . ($index + 1); ?>
									</span>
								<?php endif; ?>
								
								<div class="p-4">
									<h3 class="text-sm font-medium text-gray-900 truncate mb-1" title="<?php echo htmlspecialchars($product['ad']); ?>">
										<?php echo htmlspecialchars($product['ad']); ?>
									</h3>
									<p class="text-xs text-gray-500 mb-2">
										<?php echo htmlspecialchars($product['departman'] ?? 'Belirtilmemiş'); ?> 
										<?php if (!empty($product['ana_grup'])): ?>
											- <?php echo htmlspecialchars($product['ana_grup']); ?>
										<?php endif; ?>
									</p>
									
									<div class="flex justify-between items-center mb-2">
										<div>
											<span class="text-xs text-gray-500">Stok:</span>
											<span class="<?php echo $product['stok_miktari'] <= $critical_level ? 'text-red-600 font-bold' : 'text-green-600'; ?>">
												<?php echo number_format($product['stok_miktari'], 0, ',', '.'); ?>
											</span>
										</div>
										<div>
											<span class="text-xs text-gray-500">Satılan:</span>
											<span class="text-blue-600 font-medium">
												<?php echo number_format($product['total_sold'], 0, ',', '.'); ?>
											</span>
										</div>
									</div>
									
									<!-- Fiyat bilgileri -->
									<div class="grid grid-cols-2 gap-2 mb-3">
										<div class="text-center bg-gray-50 p-1 rounded">
											<span class="text-xs text-gray-500 block">Alış Fiyatı:</span>
											<span class="text-sm font-medium text-gray-800">
												₺<?php echo number_format($product['alis_fiyati'], 2, ',', '.'); ?>
											</span>
										</div>
										<div class="text-center bg-gray-50 p-1 rounded">
											<span class="text-xs text-gray-500 block">Satış Fiyatı:</span>
											<span class="text-sm font-medium text-green-600">
												₺<?php echo number_format($product['satis_fiyati'], 2, ',', '.'); ?>
											</span>
										</div>
									</div>
									
									<div class="mt-3 flex space-x-2">
										<!-- Hızlı Sepete Ekleme Butonu -->
										<button class="quick-add-btn w-1/2 bg-green-500 hover:bg-green-600 text-white px-2 py-1 rounded-md text-sm"
												data-id="<?php echo $product['id']; ?>"
												data-name="<?php echo htmlspecialchars($product['ad']); ?>"
												data-price="<?php echo $product['alis_fiyati']; ?>"
												data-sales-price="<?php echo $product['satis_fiyati']; ?>">
											<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline-block" fill="none" viewBox="0 0 24 24" stroke="currentColor">
												<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
											</svg>
											Ekle
										</button>
										
										<!-- Detaylı Ekleme Butonu -->
										<button class="add-to-order-btn w-1/2 bg-blue-500 hover:bg-blue-600 text-white px-2 py-1 rounded-md text-sm"
												data-id="<?php echo $product['id']; ?>"
												data-name="<?php echo htmlspecialchars($product['ad']); ?>"
												data-price="<?php echo $product['alis_fiyati']; ?>"
												data-sales-price="<?php echo $product['satis_fiyati']; ?>">
											<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline-block" fill="none" viewBox="0 0 24 24" stroke="currentColor">
												<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
											</svg>
											Detaylı
										</button>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Kritik Stok Ürünleri -->
        <div class="bg-white shadow-md rounded-lg overflow-hidden mb-6">
            <div class="px-6 py-4 border-b">
                <h2 class="text-lg font-semibold">Kritik Stok Ürünleri</h2>
            </div>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ürün</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Departman</th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Stok</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Alış Fiyatı</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Son Tedarikçi</th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Son Alış</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">İşlemler</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                                Kriterlere uygun ürün bulunamadı.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($products as $product): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($product['ad']); ?>
                                                <?php 
                                                // En çok satan ürünlerde varsa işaretle
                                                $is_top_seller = false;
                                                foreach ($top_selling_products as $index => $top_product) {
                                                    if ($top_product['id'] == $product['id'] && $index < 10) {
                                                        $is_top_seller = true;
                                                        break;
                                                    }
                                                }
                                                
                                                if ($is_top_seller):
                                                ?>
                                                <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                    Çok Satan
                                                </span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                <?php 
                                                echo 'Kod: ' . htmlspecialchars($product['kod'] ?? '-'); 
                                                echo ' | Barkod: ' . htmlspecialchars($product['barkod'] ?? '-');
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?php echo htmlspecialchars($product['departman'] ?? 'Belirtilmemiş'); ?>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        <?php 
                                        echo htmlspecialchars($product['ana_grup'] ?? '-');
                                        if (!empty($product['alt_grup'])) {
                                            echo ' > ' . htmlspecialchars($product['alt_grup']);
                                        }
                                        ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <?php 
                                    $stock_class = 'text-green-600';
                                    if ($product['stok_miktari'] <= $critical_level && $product['stok_miktari'] > 0) {
                                        $stock_class = 'text-orange-600 font-bold';
                                    } elseif ($product['stok_miktari'] == 0) {
                                        $stock_class = 'text-red-600 font-bold';
                                    }
                                    ?>
                                    <span class="<?php echo $stock_class; ?>">
                                        <?php echo number_format($product['stok_miktari'], 0, ',', '.'); ?>
                                    </span>
                                    <span class="text-xs text-gray-500 block">
                                        <?php echo htmlspecialchars($product['birim'] ?? 'Adet'); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <div class="text-sm text-gray-900">
                                        ₺<?php echo number_format($product['alis_fiyati'], 2, ',', '.'); ?>
                                    </div>
                                    <?php if (!empty($product['ortalama_alis_fiyati'])): ?>
                                        <div class="text-xs text-gray-500">
                                            Ort: ₺<?php echo number_format($product['ortalama_alis_fiyati'], 2, ',', '.'); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?php echo htmlspecialchars($product['son_tedarikci'] ?? 'Belirtilmemiş'); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <div class="text-sm text-gray-900">
                                        <?php 
                                        if (!empty($product['son_alis_tarihi'])) {
                                            echo date('d.m.Y', strtotime($product['son_alis_tarihi']));
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <button class="add-to-order-btn bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded-md text-sm mr-2"
											data-id="<?php echo $product['id']; ?>"
											data-name="<?php echo htmlspecialchars($product['ad']); ?>"
											data-price="<?php echo $product['alis_fiyati']; ?>"
											data-sales-price="<?php echo $product['satis_fiyati']; ?>">
										<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline-block" fill="none" viewBox="0 0 24 24" stroke="currentColor">
											<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
										</svg>
										Ekle
									</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Sepet Modal -->
<div id="basketModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-4xl">
        <div class="flex justify-between items-center px-6 py-4 border-b">
            <h3 class="text-lg font-semibold">Sipariş Sepeti</h3>
            <button id="closeBasketBtn" class="text-gray-500 hover:text-gray-700">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <div id="basketModalContent">
            <!-- Sepet içeriği JavaScript ile dinamik olarak güncellenecek -->
            <div class="flex justify-center p-6">
                <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-blue-500"></div>
            </div>
        </div>
    </div>
</div>

    <!-- Sipariş Oluşturma Modal -->
    <div id="createOrderModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-lg shadow-lg w-full max-w-md">
            <div class="flex justify-between items-center px-6 py-4 border-b">
                <h3 class="text-lg font-semibold">Sipariş Oluştur</h3>
                <button id="closeOrderModalBtn" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <form action="" method="POST" class="p-6">
                <div class="mb-4">
                    <label for="supplier_id" class="block text-sm font-medium text-gray-700 mb-1">Tedarikçi</label>
                    <select id="supplier_id" name="supplier_id" class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 rounded-md" required>
                        <option value="">Tedarikçi Seçin</option>
                        <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?php echo $supplier['id']; ?>">
                                <?php echo htmlspecialchars($supplier['ad']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notlar</label>
                    <textarea id="notes" name="notes" rows="3" class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 rounded-md"></textarea>
                </div>
                
                <div class="flex justify-end">
                    <button type="button" id="cancelOrderBtn" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md mr-2">
                        İptal
                    </button>
                    <button type="submit" name="create_order" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-md">
                        Siparişi Oluştur
                    </button>
                </div>
            </form>
        </div>
    </div>

		<!-- Ürün Ekleme Modal -->
		<div id="addProductModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-md">
        <div class="flex justify-between items-center px-6 py-4 border-b">
            <h3 class="text-lg font-semibold">Ürünü Sepete Ekle</h3>
            <button id="closeAddProductBtn" class="text-gray-500 hover:text-gray-700">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <form name="add_to_basket" method="POST" class="p-6">
            <input type="hidden" id="product_id" name="product_id">
            <input type="hidden" id="product_name" name="product_name">
            <input type="hidden" id="sales_price" name="sales_price" value="0">
            
            <div class="mb-4">
                <label for="product_display" class="block text-sm font-medium text-gray-700 mb-1">Ürün</label>
                <input type="text" id="product_display" class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 bg-gray-100 rounded-md" readonly>
            </div>
            
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label for="price" class="block text-sm font-medium text-gray-700 mb-1">Alış Fiyatı (₺)</label>
                    <input type="number" id="price" name="price" step="0.01" min="0" class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 rounded-md" required>
                </div>
                
                <div>
                    <label for="display_sales_price" class="block text-sm font-medium text-gray-700 mb-1">Satış Fiyatı (₺)</label>
                    <input type="number" id="display_sales_price" step="0.01" min="0" class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 rounded-md">
                </div>
            </div>
            
            <div class="mb-4">
                <label for="quantity" class="block text-sm font-medium text-gray-700 mb-1">Miktar</label>
                <input type="number" id="quantity" name="quantity" min="1" value="1" class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 rounded-md" required>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Toplam Tutar</label>
                <div id="total_amount" class="text-lg font-bold text-green-600">₺0,00</div>
            </div>
            
            <div class="mb-4 p-3 bg-gray-50 rounded-md">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-700">Alış Tutarı:</span>
                    <span id="purchase_total" class="font-medium">₺0,00</span>
                </div>
                <div class="flex items-center justify-between text-sm mt-1">
                    <span class="text-gray-700">Satış Tutarı:</span>
                    <span id="sales_total" class="font-medium text-green-600">₺0,00</span>
                </div>
                <div class="flex items-center justify-between text-sm mt-1 pt-1 border-t">
                    <span class="text-gray-700 font-medium">Kâr Marjı:</span>
                    <span id="profit_margin" class="font-medium text-blue-600">%0</span>
                </div>
            </div>
            
            <div class="flex justify-end">
                <button type="button" id="cancelAddProductBtn" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md mr-2">
                    İptal
                </button>
                <button type="submit" name="add_to_basket" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-md">
                    Sepete Ekle
                </button>
            </div>
        </form>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Modaller ve Butonlar
    const basketModal = document.getElementById('basketModal');
    const showBasketBtn = document.getElementById('showBasketBtn');
    const closeBasketBtn = document.getElementById('closeBasketBtn');
    const closeBasketBtn2 = document.getElementById('closeBasketBtn2');
    const createOrderModal = document.getElementById('createOrderModal');
    const createOrderBtn = document.getElementById('createOrderBtn');
    const closeOrderModalBtn = document.getElementById('closeOrderModalBtn');
    const cancelOrderBtn = document.getElementById('cancelOrderBtn');
    const addProductModal = document.getElementById('addProductModal');
    const closeAddProductBtn = document.getElementById('closeAddProductBtn');
    const cancelAddProductBtn = document.getElementById('cancelAddProductBtn');
    
    // Sepet Modalı Event Listener'ları
    if (showBasketBtn) {
        showBasketBtn.addEventListener('click', function() {
            // Sepet içeriğini güncelle
            fetch('api/get_basket.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateBasketContent(data.basket);
                    }
                });
            
            basketModal.classList.remove('hidden');
        });
    }
    
    if (closeBasketBtn) {
        closeBasketBtn.addEventListener('click', function() {
            basketModal.classList.add('hidden');
        });
    }
    
    if (closeBasketBtn2) {
        closeBasketBtn2.addEventListener('click', function() {
            basketModal.classList.add('hidden');
        });
    }
    
    // Sipariş Oluşturma Modalı Event Listener'ları
    if (createOrderBtn) {
        createOrderBtn.addEventListener('click', function() {
            basketModal.classList.add('hidden');
            createOrderModal.classList.remove('hidden');
        });
    }
    
    if (closeOrderModalBtn) {
        closeOrderModalBtn.addEventListener('click', function() {
            createOrderModal.classList.add('hidden');
            basketModal.classList.remove('hidden');
        });
    }
    
    if (cancelOrderBtn) {
        cancelOrderBtn.addEventListener('click', function() {
            createOrderModal.classList.add('hidden');
            basketModal.classList.remove('hidden');
        });
    }
    
    // Ürün Ekleme Modalı Event Listener'ları
    const addToOrderBtns = document.querySelectorAll('.add-to-order-btn');
    addToOrderBtns.forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.getAttribute('data-id');
            const productName = this.getAttribute('data-name');
            const productPrice = this.getAttribute('data-price');
            const salesPrice = this.getAttribute('data-sales-price') || this.closest('tr')?.querySelector('.sales-price')?.textContent || '0';
            
            console.log("Ürün Ekle Butonuna Tıklandı:", { 
                productId, 
                productName, 
                productPrice, 
                salesPrice 
            });
            
            document.getElementById('product_id').value = productId;
            document.getElementById('product_name').value = productName;
            document.getElementById('product_display').value = productName;
            document.getElementById('price').value = productPrice;
            
            // Satış fiyatını da ekle
            if (document.getElementById('sales_price')) {
                document.getElementById('sales_price').value = salesPrice.replace('₺', '').replace(',', '.').trim();
            }
            
            if (document.getElementById('display_sales_price')) {
                document.getElementById('display_sales_price').value = salesPrice.replace('₺', '').replace(',', '.').trim();
            }
            
            document.getElementById('quantity').value = 1;
            
            // Hesaplamaları güncelle
            if (typeof updateCalculations === 'function') {
                updateCalculations();
            } else if (typeof updateTotalAmount === 'function') {
                updateTotalAmount();
            }
            
            addProductModal.classList.remove('hidden');
        });
    });
    
    if (closeAddProductBtn) {
        closeAddProductBtn.addEventListener('click', function() {
            addProductModal.classList.add('hidden');
        });
    }
    
    if (cancelAddProductBtn) {
        cancelAddProductBtn.addEventListener('click', function() {
            addProductModal.classList.add('hidden');
        });
    }
    
    // Fiyat ve Miktar Hesaplama
    const priceInput = document.getElementById('price');
    const quantityInput = document.getElementById('quantity');
    const totalAmountDiv = document.getElementById('total_amount');
    const salesPriceInput = document.getElementById('sales_price');
    const displaySalesPriceInput = document.getElementById('display_sales_price');
    const purchaseTotalSpan = document.getElementById('purchase_total');
    const salesTotalSpan = document.getElementById('sales_total');
    const profitMarginSpan = document.getElementById('profit_margin');
    
    // Kapsamlı hesaplama fonksiyonu
    function updateCalculations() {
        if (priceInput && quantityInput && totalAmountDiv) {
            const price = parseFloat(priceInput.value) || 0;
            const quantity = parseInt(quantityInput.value) || 0;
            const salesPrice = parseFloat(salesPriceInput ? salesPriceInput.value : 0) || 0;
            
            console.log("Hesaplama için değerler:", { price, quantity, salesPrice });
            
            // Alış toplamı
            const purchaseTotal = price * quantity;
            totalAmountDiv.textContent = '₺' + purchaseTotal.toLocaleString('tr-TR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
            
            // Alış tutarı
            if (purchaseTotalSpan) {
                purchaseTotalSpan.textContent = '₺' + purchaseTotal.toLocaleString('tr-TR', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }
            
            // Satış fiyatı ve toplamı
            if (salesPriceInput && displaySalesPriceInput) {
                displaySalesPriceInput.value = salesPriceInput.value;
            }
            
            if (salesTotalSpan && salesPriceInput) {
                const salesTotal = salesPrice * quantity;
                salesTotalSpan.textContent = '₺' + salesTotal.toLocaleString('tr-TR', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
                
                // Kâr marjı hesaplama
                if (profitMarginSpan && purchaseTotal > 0) {
                    const profit = salesTotal - purchaseTotal;
                    const margin = (profit / purchaseTotal) * 100;
                    profitMarginSpan.textContent = '%' + margin.toFixed(2);
                    
                    // Kâr marjına göre renk değiştirme
                    if (margin < 0) {
                        profitMarginSpan.className = 'font-medium text-red-600';
                    } else if (margin < 10) {
                        profitMarginSpan.className = 'font-medium text-yellow-600';
                    } else {
                        profitMarginSpan.className = 'font-medium text-green-600';
                    }
                }
            }
        }
    }
    
    // Eski fonksiyon - geriye dönük uyumluluk için
    function updateTotalAmount() {
        if (priceInput && quantityInput && totalAmountDiv) {
            const price = parseFloat(priceInput.value) || 0;
            const quantity = parseInt(quantityInput.value) || 0;
            const total = price * quantity;
            
            totalAmountDiv.textContent = '₺' + total.toLocaleString('tr-TR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
            
            // Yeni hesaplama fonksiyonu varsa çağır
            if (typeof updateCalculations === 'function' && updateCalculations !== updateTotalAmount) {
                updateCalculations();
            }
        }
    }
    
    // Fiyat ve miktar değişim event listener'ları
    if (priceInput) {
        priceInput.addEventListener('input', function() {
            if (typeof updateCalculations === 'function') {
                updateCalculations();
            } else {
                updateTotalAmount();
            }
        });
    }
    
    if (quantityInput) {
        quantityInput.addEventListener('input', function() {
            if (typeof updateCalculations === 'function') {
                updateCalculations();
            } else {
                updateTotalAmount();
            }
        });
    }
    
    if (salesPriceInput) {
        salesPriceInput.addEventListener('input', function() {
            if (typeof updateCalculations === 'function') {
                updateCalculations();
            }
        });
    }
    
    // Hızlı Ekle Butonları
    const quickAddButtons = document.querySelectorAll('.quick-add-btn');
    quickAddButtons.forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.getAttribute('data-id');
            const productName = this.getAttribute('data-name');
            const productPrice = this.getAttribute('data-price');
            const salesPrice = this.getAttribute('data-sales-price') || '0';
            
            console.log("Hızlı Ekle Butonuna Tıklandı:", { 
                productId, 
                productName, 
                productPrice, 
                salesPrice 
            });
            
            // Varsayılan miktar 1
            const quantity = 1;
            
            // Sepete ekle
            quickAddToBasket(productId, productName, productPrice, quantity, salesPrice);
        });
    });
    
    // Form submit olayı
    const addToBasketForm = document.querySelector('form[name="add_to_basket"]');
    if (addToBasketForm) {
        addToBasketForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const productId = document.getElementById('product_id').value;
            const productName = document.getElementById('product_name').value;
            const price = document.getElementById('price').value;
            const quantity = document.getElementById('quantity').value;
            const salesPrice = document.getElementById('sales_price')?.value || '0';
            
            quickAddToBasket(productId, productName, price, quantity, salesPrice);
            
            // Modalı kapat
            if (addProductModal) {
                addProductModal.classList.add('hidden');
            }
        });
    }
    
    // Sayfa yüklendiğinde sepet sayısını güncelle
    fetch('api/get_basket.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const basketCount = document.querySelector('#basketCount');
                if (basketCount) {
                    basketCount.textContent = data.basket_count;
                    if (data.basket_count > 0) {
                        basketCount.classList.remove('hidden');
                        basketCount.parentElement.classList.remove('hidden');
                    } else {
                        basketCount.classList.add('hidden');
                    }
                }
            }
        });
    
    // Sepete hızlı ekleme fonksiyonu
    function quickAddToBasket(productId, productName, productPrice, quantity, salesPrice) {
        console.log("Sepete eklenecek veriler:", {
            product_id: productId,
            product_name: productName,
            price: productPrice,
            sales_price: salesPrice,
            quantity: quantity
        });
        
        // AJAX ile sepete ekleme isteği
        fetch('api/add_to_basket.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                product_id: productId,
                product_name: productName,
                price: productPrice,
                sales_price: salesPrice,
                quantity: quantity
            })
        })
        .then(response => response.json())
        .then(data => {
            console.log("API yanıtı:", data);
            
            if (data.success) {
                // Sepet sayısını güncelle
                const basketCount = document.querySelector('#basketCount');
                if (basketCount) {
                    basketCount.textContent = data.basket_count;
                    basketCount.classList.remove('hidden');
                    basketCount.parentElement.classList.remove('hidden');
                }
                
                // Sepet içeriğini dinamik olarak güncelle
                updateBasketContent(data.basket);
                
                // Başarı mesajı göster
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Ürün Eklendi!',
                        text: productName + ' sepete eklendi.',
                        icon: 'success',
                        timer: 1500,
                        showConfirmButton: false
                    });
                } else {
                    alert(productName + ' sepete eklendi.');
                }
            } else {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Hata!',
                        text: data.message || 'Ürün sepete eklenirken bir hata oluştu.',
                        icon: 'error'
                    });
                } else {
                    alert('Hata: ' + (data.message || 'Ürün sepete eklenirken bir hata oluştu.'));
                }
            }
        })
        .catch(error => {
            console.error('Sepete ekleme hatası:', error);
            
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Hata!',
                    text: 'Bir hata oluştu. Lütfen tekrar deneyin.',
                    icon: 'error'
                });
            } else {
                alert('Hata: Bir hata oluştu. Lütfen tekrar deneyin.');
            }
        });
    }
    
    // Sepet içeriğini dinamik olarak güncelleme fonksiyonu
    function updateBasketContent(basketItems) {
        const basketContent = document.querySelector('#basketModalContent');
        if (!basketContent) return;
        
        if (!basketItems || basketItems.length === 0) {
            // Sepet boşsa
            basketContent.innerHTML = `
                <div class="p-6 text-center">
                    <p class="text-gray-500 mb-4">Sepetinizde ürün bulunmamaktadır.</p>
                    <button id="closeBasketBtn2" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md">
                        Alışverişe Devam Et
                    </button>
                </div>
            `;
            
            // Buton için event listener ekle
            const closeBtn = basketContent.querySelector('#closeBasketBtn2');
            if (closeBtn) {
                closeBtn.addEventListener('click', function() {
                    document.getElementById('basketModal').classList.add('hidden');
                });
            }
            
            return;
        }
        
        // Sepet dolu ise içerik oluştur
        let totalAmount = 0;
        let tableHTML = `
            <div class="p-6">
                <table class="min-w-full divide-y divide-gray-200 mb-4">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ürün</th>
                            <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Miktar</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Son Alış Fiyatı</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Toplam</th>
                            <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">İşlem</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
        `;
        
        // Sepet öğelerini tabloya ekle
        basketItems.forEach(item => {
            const itemTotal = item.quantity * item.price;
            totalAmount += itemTotal;
            
            tableHTML += `
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap">
                        ${item.name}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                        ${item.quantity.toLocaleString('tr-TR')}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right">
                        ₺${parseFloat(item.price).toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right font-medium">
                        ₺${itemTotal.toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                        <a href="javascript:void(0)" class="remove-item text-red-600 hover:text-red-900" data-id="${item.id}">Kaldır</a>
                    </td>
                </tr>
            `;
        });
        
        // Toplam tutarı ve butonları ekle
        tableHTML += `
                    </tbody>
                    <tfoot>
                        <tr class="bg-gray-50">
                            <td colspan="3" class="px-6 py-4 whitespace-nowrap text-right font-medium">Toplam:</td>
                            <td class="px-6 py-4 whitespace-nowrap text-right font-bold">
                                ₺${totalAmount.toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
                
                <div class="flex justify-between">
                    <a href="javascript:void(0)" id="clearBasketBtn" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-md">
                        Sepeti Temizle
                    </a>
                    
                    <button id="createOrderBtn" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-md">
                        Sipariş Oluştur
                    </button>
                </div>
            </div>
        `;
        
        // HTML'i güncelle
        basketContent.innerHTML = tableHTML;
        
        // Event listener'ları ekle
        const clearBasketBtn = basketContent.querySelector('#clearBasketBtn');
        if (clearBasketBtn) {
            clearBasketBtn.addEventListener('click', function() {
                // API ile sepeti temizle
                fetch('api/clear_basket.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Sepeti boş olarak güncelle
                            updateBasketContent([]);
                            // Sepet sayacını güncelle
                            const basketCount = document.querySelector('#basketCount');
                            if (basketCount) {
                                basketCount.textContent = "0";
                                basketCount.classList.add('hidden');
                            }
                            
                            // Başarı mesajı göster
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    title: 'Sepet Temizlendi',
                                    icon: 'success',
                                    timer: 1500,
                                    showConfirmButton: false
                                });
                            }
                        }
                    });
            });
        }
        
        // Sipariş oluşturma butonuna event listener ekle
        const createOrderBtn = basketContent.querySelector('#createOrderBtn');
        if (createOrderBtn) {
            createOrderBtn.addEventListener('click', function() {
                // Sepet modalını gizle, sipariş modalını göster
                document.getElementById('basketModal').classList.add('hidden');
                document.getElementById('createOrderModal').classList.remove('hidden');
            });
        }
        
        // Ürün kaldırma butonlarına event listener ekle
        const removeButtons = basketContent.querySelectorAll('.remove-item');
        removeButtons.forEach(button => {
            button.addEventListener('click', function() {
                const productId = this.getAttribute('data-id');
                
                // API ile ürünü sepetten kaldır
                fetch(`api/remove_from_basket.php?id=${productId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Güncel sepet verilerini al ve sepeti güncelle
                            updateBasketContent(data.basket);
                            
                            // Sepet sayacını güncelle
                            const basketCount = document.querySelector('#basketCount');
                            if (basketCount) {
                                basketCount.textContent = data.basket_count;
                                if (data.basket_count === 0) {
                                    basketCount.classList.add('hidden');
                                }
                            }
                        }
                    });
            });
        });
    }
});
</script>
<?php if (isset($redirect_after_clear)): ?>
<script>
    // URL'den clear_basket parametresini temizle
    window.history.replaceState({}, document.title, "critical_stock.php");
</script>
<?php endif; ?>
</body>
</html>