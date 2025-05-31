<?php
// Sayfa yüklenme süresini hesapla
$start_time = microtime(true);

// Header'ı dahil et
include 'header.php';
require_once 'db_connection.php';

// Yetki kontrolü
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: index.php");
    exit;
}

// Durum göstergeleri için stil ve metin tanımlamaları
$statusColors = [
    'nakit' => 'bg-green-100 text-green-800',
    'kredi_karti' => 'bg-blue-100 text-blue-800',
    'havale' => 'bg-purple-100 text-purple-800'
];

$statusTexts = [
    'nakit' => 'Nakit',
    'kredi_karti' => 'Kredi Kartı',
    'havale' => 'Havale'
];

$islemTuruColors = [
    'satis' => 'bg-blue-100 text-blue-800',
    'iade' => 'bg-red-100 text-red-800'
];

$islemTuruTexts = [
    'satis' => 'Satış',
    'iade' => 'İade'
];

// Mağazaları çek
$magazalar_query = "SELECT * FROM magazalar ORDER BY ad";
$magazalar = $conn->query($magazalar_query)->fetchAll(PDO::FETCH_ASSOC);

// Personel listesini çek
$personel_query = "SELECT * FROM personel WHERE durum = 'aktif' ORDER BY ad";
$personeller = $conn->query($personel_query)->fetchAll(PDO::FETCH_ASSOC);

// Müşterileri çek
$musteriler_query = "SELECT * FROM musteriler WHERE durum = 'aktif' ORDER BY ad";
$musteriler = $conn->query($musteriler_query)->fetchAll(PDO::FETCH_ASSOC);

// Sayfa başına gösterilecek fatura sayısı
$items_per_page = isset($_SESSION['items_per_page']) ? $_SESSION['items_per_page'] : 50;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $items_per_page;

// Tarih filtresi için varsayılan değerler
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Diğer filtreler
$odeme_turu = isset($_GET['odeme_turu']) ? $_GET['odeme_turu'] : '';
$islem_turu = isset($_GET['islem_turu']) ? $_GET['islem_turu'] : '';
$magaza_id = isset($_GET['magaza_id']) ? (int)$_GET['magaza_id'] : 0;
$personel_id = isset($_GET['personel_id']) ? (int)$_GET['personel_id'] : 0;
$musteri_id = isset($_GET['musteri_id']) ? (int)$_GET['musteri_id'] : 0;
$search_term = isset($_GET['search_term']) ? $_GET['search_term'] : '';

// Fatura sayısını al
$count_query = "SELECT COUNT(*) as total FROM satis_faturalari WHERE DATE(fatura_tarihi) BETWEEN ? AND ?";
$count_params = [$start_date, $end_date];

// Ek filtreler için where koşulları
$where_conditions = [];
$params = [$start_date, $end_date];

if (!empty($odeme_turu)) {
    $where_conditions[] = "odeme_turu = ?";
    $params[] = $odeme_turu;
}

if (!empty($islem_turu)) {
    $where_conditions[] = "islem_turu = ?";
    $params[] = $islem_turu;
}

if ($magaza_id > 0) {
    $where_conditions[] = "magaza = ?";
    $params[] = $magaza_id;
}

if ($personel_id > 0) {
    $where_conditions[] = "personel = ?";
    $params[] = $personel_id;
}

if ($musteri_id > 0) {
    $where_conditions[] = "musteri_id = ?";
    $params[] = $musteri_id;
}

if (!empty($search_term)) {
    $where_conditions[] = "(fatura_no LIKE ? OR fatura_seri LIKE ?)";
    $params[] = "%$search_term%";
    $params[] = "%$search_term%";
}

// Where koşullarını ekle
if (!empty($where_conditions)) {
    $count_query .= " AND " . implode(" AND ", $where_conditions);
    $count_params = $params;
}

$stmt = $conn->prepare($count_query);
$stmt->execute($count_params);
$total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $items_per_page);

// Faturaları çek
$query = "
SELECT 
    sf.*,
    t.ad as tedarikci_adi, 
    m.ad as magaza_adi,
    p.ad as personel_adi,
    mus.ad as musteri_adi,
    mus.soyad as musteri_soyad
FROM 
    satis_faturalari sf
    LEFT JOIN magazalar m ON sf.magaza = m.id
    LEFT JOIN personel p ON sf.personel = p.id
    LEFT JOIN musteriler mus ON sf.musteri_id = mus.id
    LEFT JOIN tedarikciler t ON t.id = 1 /* Dummy join for compatibility */
WHERE 
    DATE(sf.fatura_tarihi) BETWEEN ? AND ?
";

// Ek filtreler
if (!empty($where_conditions)) {
    $query .= " AND " . implode(" AND ", $where_conditions);
}

$query .= "
ORDER BY 
    sf.fatura_tarihi DESC, sf.id DESC
    LIMIT ? 
    OFFSET ?
";

$stmt = $conn->prepare($query);
foreach ($params as $i => $param) {
    $stmt->bindValue($i + 1, $param);
}
$stmt->bindValue(count($params) + 1, $items_per_page, PDO::PARAM_INT);
$stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
$stmt->execute();
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// İstatistik verileri
// Toplam satış tutarı
$total_sales_query = "
    SELECT 
        SUM(net_tutar) as total_sales,
        COUNT(*) as total_invoices
    FROM 
        satis_faturalari 
    WHERE 
        DATE(fatura_tarihi) BETWEEN ? AND ?
        AND islem_turu = 'satis'
";
// Ek filtreler
if (!empty($where_conditions)) {
    $total_sales_query .= " AND " . implode(" AND ", $where_conditions);
}

$stmt = $conn->prepare($total_sales_query);
$stmt->execute($count_params);
$sales_data = $stmt->fetch(PDO::FETCH_ASSOC);
$total_sales = $sales_data['total_sales'] ?? 0;

// Toplam iade tutarı
$total_returns_query = "
    SELECT 
        SUM(net_tutar) as total_returns,
        COUNT(*) as total_returns_count
    FROM 
        satis_faturalari 
    WHERE 
        DATE(fatura_tarihi) BETWEEN ? AND ?
        AND islem_turu = 'iade'
";
// Ek filtreler
if (!empty($where_conditions)) {
    $total_returns_query .= " AND " . implode(" AND ", $where_conditions);
}

$stmt = $conn->prepare($total_returns_query);
$stmt->execute($count_params);
$returns_data = $stmt->fetch(PDO::FETCH_ASSOC);
$total_returns = $returns_data['total_returns'] ?? 0;
$returns_count = $returns_data['total_returns_count'] ?? 0;

// Ödeme türlerine göre dağılım
$payment_types_query = "
    SELECT 
        odeme_turu,
        COUNT(*) as count,
        SUM(net_tutar) as total
    FROM 
        satis_faturalari 
    WHERE 
        DATE(fatura_tarihi) BETWEEN ? AND ?
";
// Ek filtreler
if (!empty($where_conditions)) {
    $payment_types_query .= " AND " . implode(" AND ", $where_conditions);
}
$payment_types_query .= " GROUP BY odeme_turu";

$stmt = $conn->prepare($payment_types_query);
$stmt->execute($count_params);
$payment_types = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Sayfa yüklenme süresini hesapla
$end_time = microtime(true);
$execution_time = round($end_time - $start_time, 3);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Satış Faturaları</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <!-- Üst Başlık ve Butonlar -->
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold">Satış Faturaları</h1>
                <p class="text-sm text-gray-600">Toplam <?php echo $total_records; ?> fatura</p>
            </div>
            <button onclick="addInvoice()" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                <svg class="w-5 h-5 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                </svg>
                Yeni Satış Faturası
            </button>
        </div>

        <!-- İstatistik Kartları -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <!-- Toplam Satış -->
            <div class="bg-white p-4 rounded-lg shadow">
                <h3 class="text-sm font-medium text-gray-500">Toplam Satış</h3>
                <p class="text-2xl font-bold text-blue-600"><?php echo number_format($total_sales, 2, ',', '.'); ?> ₺</p>
                <p class="text-xs text-gray-500"><?php echo $sales_data['total_invoices'] ?? 0; ?> fatura</p>
            </div>
            
            <!-- Toplam İade -->
            <div class="bg-white p-4 rounded-lg shadow">
                <h3 class="text-sm font-medium text-gray-500">Toplam İade</h3>
                <p class="text-2xl font-bold text-red-600"><?php echo number_format($total_returns, 2, ',', '.'); ?> ₺</p>
                <p class="text-xs text-gray-500"><?php echo $returns_count; ?> işlem</p>
            </div>
            
            <!-- Net Kazanç -->
            <div class="bg-white p-4 rounded-lg shadow">
                <h3 class="text-sm font-medium text-gray-500">Net Kazanç</h3>
                <p class="text-2xl font-bold text-green-600">
                    <?php echo number_format($total_sales - $total_returns, 2, ',', '.'); ?> ₺
                </p>
                <p class="text-xs text-gray-500"><?php echo ($sales_data['total_invoices'] ?? 0) + $returns_count; ?> toplam işlem</p>
            </div>
            
            <!-- Ortalama Sepet -->
            <div class="bg-white p-4 rounded-lg shadow">
                <h3 class="text-sm font-medium text-gray-500">Ortalama Sepet</h3>
                <?php 
                $avg_basket = ($sales_data['total_invoices'] > 0) 
                    ? $total_sales / $sales_data['total_invoices'] 
                    : 0; 
                ?>
                <p class="text-2xl font-bold text-purple-600"><?php echo number_format($avg_basket, 2, ',', '.'); ?> ₺</p>
                <p class="text-xs text-gray-500">Satış başına</p>
            </div>
        </div>

        <!-- Ödeme Türü Dağılımı -->
        <div class="bg-white p-4 rounded-lg shadow mb-6">
            <h3 class="text-lg font-semibold mb-4">Ödeme Türü Dağılımı</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <?php foreach ($payment_types as $payment): ?>
                    <div class="border rounded-lg p-3">
                        <div class="flex justify-between items-center">
                            <span class="font-medium">
                                <?php echo isset($statusTexts[$payment['odeme_turu']]) ? $statusTexts[$payment['odeme_turu']] : $payment['odeme_turu']; ?>
                            </span>
                            <span class="px-2 py-1 text-xs rounded-full <?php echo isset($statusColors[$payment['odeme_turu']]) ? $statusColors[$payment['odeme_turu']] : 'bg-gray-100 text-gray-800'; ?>">
                                <?php echo $payment['count']; ?> fatura
                            </span>
                        </div>
                        <p class="text-xl font-bold mt-2"><?php echo number_format($payment['total'], 2, ',', '.'); ?> ₺</p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Filtreler -->
        <div class="bg-white p-4 rounded-lg shadow mb-6">
            <form id="filterForm" method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Başlangıç Tarihi</label>
                    <input type="date" name="start_date" value="<?php echo $start_date; ?>" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Bitiş Tarihi</label>
                    <input type="date" name="end_date" value="<?php echo $end_date; ?>" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Mağaza</label>
                    <select name="magaza_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <option value="0">Tüm Mağazalar</option>
                        <?php foreach ($magazalar as $magaza): ?>
                            <option value="<?php echo $magaza['id']; ?>" <?php echo ($magaza_id == $magaza['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($magaza['ad']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Ödeme Türü</label>
                    <select name="odeme_turu" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">Tümü</option>
                        <?php foreach ($statusTexts as $value => $text): ?>
                            <option value="<?php echo $value; ?>" <?php echo ($odeme_turu == $value) ? 'selected' : ''; ?>>
                                <?php echo $text; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">İşlem Türü</label>
                    <select name="islem_turu" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">Tümü</option>
                        <?php foreach ($islemTuruTexts as $value => $text): ?>
                            <option value="<?php echo $value; ?>" <?php echo ($islem_turu == $value) ? 'selected' : ''; ?>>
                                <?php echo $text; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Personel</label>
                    <select name="personel_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <option value="0">Tüm Personel</option>
                        <?php foreach ($personeller as $personel): ?>
                            <option value="<?php echo $personel['id']; ?>" <?php echo ($personel_id == $personel['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($personel['ad']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Müşteri</label>
                    <select name="musteri_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <option value="0">Tüm Müşteriler</option>
                        <?php foreach ($musteriler as $musteri): ?>
                            <option value="<?php echo $musteri['id']; ?>" <?php echo ($musteri_id == $musteri['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($musteri['ad'] . ' ' . $musteri['soyad']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Fatura No/Seri</label>
                    <input type="text" name="search_term" value="<?php echo htmlspecialchars($search_term); ?>" 
                           placeholder="Fatura No veya Seri"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                        Filtrele
                    </button>
                </div>
                <div class="flex items-end">
                    <a href="sales_invoices.php" class="w-full bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded text-center">
                        Filtreleri Temizle
                    </a>
                </div>
            </form>
        </div>

        <!-- Toplu İşlemler -->
        <div class="bg-white p-4 rounded-lg shadow mb-6">
            <h3 class="text-lg font-semibold mb-4">Toplu İşlemler</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <button onclick="exportToExcel()" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                    <svg class="w-5 h-5 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Excel'e Aktar
                </button>
                <button onclick="printSelected()" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    <svg class="w-5 h-5 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    Seçili Faturaları Yazdır
                </button>
                <button onclick="sendToEArchive()" class="bg-purple-500 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded">
                    <svg class="w-5 h-5 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                    E-Arşive Gönder
                </button>
            </div>
        </div>

<!-- Fatura Listesi -->
<div class="bg-white shadow-md rounded-lg overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="w-4 px-2 py-2 text-left">
                        <input type="checkbox" id="selectAll" class="form-checkbox h-4 w-4 text-blue-600 rounded border-gray-300">
                    </th>
                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">Fatura No</th>
                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">Müşteri</th>
                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tarih</th>
                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">Mağaza</th>
                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">Personel</th>
                    <th class="px-2 py-2 text-right text-xs font-medium text-gray-500 uppercase">Toplam</th>
                    <th class="px-2 py-2 text-right text-xs font-medium text-gray-500 uppercase">İndirim</th>
                    <th class="px-2 py-2 text-right text-xs font-medium text-gray-500 uppercase">Net</th>
                    <th class="px-2 py-2 text-center text-xs font-medium text-gray-500 uppercase">Tür</th>
                    <th class="px-2 py-2 text-center text-xs font-medium text-gray-500 uppercase">Ödeme</th>
                    <th class="px-2 py-2 text-center text-xs font-medium text-gray-500 uppercase">İşlem</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (empty($invoices)): ?>
                    <tr>
                        <td colspan="12" class="px-2 py-3 text-center text-gray-500">
                            Belirtilen kriterlere uygun fatura bulunamadı.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($invoices as $invoice): ?>
                        <tr class="hover:bg-gray-50 <?php echo ($invoice['islem_turu'] == 'iade') ? 'bg-red-50' : 'bg-white'; ?>">
                            <td class="px-2 py-2 whitespace-nowrap">
                                <input type="checkbox" name="selected_invoices[]" value="<?php echo $invoice['id']; ?>" 
                                       class="invoice-checkbox form-checkbox h-4 w-4 text-blue-600 rounded border-gray-300">
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-xs font-medium">
                                <?php echo htmlspecialchars($invoice['fatura_seri'] . $invoice['fatura_no']); ?>
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-xs">
                                <?php 
                                if ($invoice['musteri_id']) {
                                    echo htmlspecialchars($invoice['musteri_adi'] . ' ' . $invoice['musteri_soyad']);
                                } else {
                                    echo "<span class='text-gray-400'>-</span>";
                                }
                                ?>
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-xs">
                                <?php echo date('d.m.Y H:i', strtotime($invoice['fatura_tarihi'])); ?>
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-xs">
                                <?php echo htmlspecialchars($invoice['magaza_adi']); ?>
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-xs">
                                <?php echo htmlspecialchars($invoice['personel_adi']); ?>
                            </td>
                            <td class="px-2 py-2 text-right whitespace-nowrap text-xs">
                                <?php echo number_format($invoice['toplam_tutar'], 2, ',', '.'); ?>
                            </td>
                            <td class="px-2 py-2 text-right whitespace-nowrap text-xs">
                                <?php echo number_format($invoice['indirim_tutari'], 2, ',', '.'); ?>
                            </td>
                            <td class="px-2 py-2 text-right whitespace-nowrap text-xs font-medium">
                                <?php echo number_format($invoice['net_tutar'], 2, ',', '.'); ?>
                            </td>
                            <td class="px-2 py-2 text-center whitespace-nowrap">
                                <span class="px-1.5 py-0.5 text-xs rounded-full <?php echo $islemTuruColors[$invoice['islem_turu']]; ?>">
                                    <?php echo $islemTuruTexts[$invoice['islem_turu']]; ?>
                                </span>
                            </td>
                            <td class="px-2 py-2 text-center whitespace-nowrap">
                                <span class="px-1.5 py-0.5 text-xs rounded-full <?php echo $statusColors[$invoice['odeme_turu']]; ?>">
                                    <?php echo $statusTexts[$invoice['odeme_turu']]; ?>
                                </span>
                            </td>
                            <td class="px-2 py-2 text-center whitespace-nowrap">
                                <div class="flex items-center justify-center space-x-1">
                                    <button onclick="viewInvoice(<?php echo $invoice['id']; ?>)" 
                                            class="text-blue-600 hover:text-blue-900 p-1" 
                                            title="Görüntüle">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                  d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </button>

                                    <button onclick="printInvoice(<?php echo $invoice['id']; ?>)" 
                                            class="text-gray-600 hover:text-gray-900 p-1" 
                                            title="Yazdır">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                  d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                        </svg>
                                    </button>

                                    <?php if ($invoice['islem_turu'] == 'satis'): ?>
                                    <button onclick="createReturn(<?php echo $invoice['id']; ?>)" 
                                            class="text-red-600 hover:text-red-900 p-1" 
                                            title="İade">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                  d="M16 15v-1a4 4 0 00-4-4H8m0 0l3 3m-3-3l3-3m9 14V5a2 2 0 00-2-2H6a2 2 0 00-2 2v16l4-2 4 2 4-2 4 2z"/>
                                        </svg>
                                    </button>
                                    <?php endif; ?>

                                    <button onclick="sendToCustomer(<?php echo $invoice['id']; ?>, <?php echo $invoice['musteri_id'] ? $invoice['musteri_id'] : 'null'; ?>)" 
                                            class="text-green-600 hover:text-green-900 p-1" 
                                            title="Müşteriye Gönder">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                  d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

        <!-- Sayfalama -->
        <?php if ($total_pages > 1): ?>
        <div class="mt-4 flex justify-center">
            <div class="flex space-x-2">
                <?php if ($page > 1): ?>
                    <a href="?page=1&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&odeme_turu=<?php echo $odeme_turu; ?>&islem_turu=<?php echo $islem_turu; ?>&magaza_id=<?php echo $magaza_id; ?>&personel_id=<?php echo $personel_id; ?>&musteri_id=<?php echo $musteri_id; ?>&search_term=<?php echo urlencode($search_term); ?>" 
                       class="px-4 py-2 bg-white rounded-md hover:bg-gray-50">İlk</a>
                    <a href="?page=<?php echo $page - 1; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&odeme_turu=<?php echo $odeme_turu; ?>&islem_turu=<?php echo $islem_turu; ?>&magaza_id=<?php echo $magaza_id; ?>&personel_id=<?php echo $personel_id; ?>&musteri_id=<?php echo $musteri_id; ?>&search_term=<?php echo urlencode($search_term); ?>" 
                       class="px-4 py-2 bg-white rounded-md hover:bg-gray-50">Önceki</a>
                <?php endif; ?>

                <?php
                $start = max(1, $page - 2);
                $end = min($total_pages, $page + 2);
                
                for ($i = $start; $i <= $end; $i++):
                    $active = $i === $page ? 'bg-blue-500 text-white' : 'bg-white hover:bg-gray-50';
                ?>
                    <a href="?page=<?php echo $i; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&odeme_turu=<?php echo $odeme_turu; ?>&islem_turu=<?php echo $islem_turu; ?>&magaza_id=<?php echo $magaza_id; ?>&personel_id=<?php echo $personel_id; ?>&musteri_id=<?php echo $musteri_id; ?>&search_term=<?php echo urlencode($search_term); ?>" 
                       class="px-4 py-2 <?php echo $active; ?> rounded-md">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&odeme_turu=<?php echo $odeme_turu; ?>&islem_turu=<?php echo $islem_turu; ?>&magaza_id=<?php echo $magaza_id; ?>&personel_id=<?php echo $personel_id; ?>&musteri_id=<?php echo $musteri_id; ?>&search_term=<?php echo urlencode($search_term); ?>" 
                       class="px-4 py-2 bg-white rounded-md hover:bg-gray-50">Sonraki</a>
                    <a href="?page=<?php echo $total_pages; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&odeme_turu=<?php echo $odeme_turu; ?>&islem_turu=<?php echo $islem_turu; ?>&magaza_id=<?php echo $magaza_id; ?>&personel_id=<?php echo $personel_id; ?>&musteri_id=<?php echo $musteri_id; ?>&search_term=<?php echo urlencode($search_term); ?>" 
                       class="px-4 py-2 bg-white rounded-md hover:bg-gray-50">Son</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Fatura Detay Modal -->
    <div id="invoiceDetailModal" style="z-index:10000;" class="fixed inset-0 bg-black bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-4xl shadow-lg rounded-md bg-white">
            <div class="flex justify-between items-center border-b pb-3">
                <h3 class="text-lg font-medium" id="invoiceDetailTitle">Fatura Detayları</h3>
                <button onclick="closeInvoiceDetailModal()" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div id="invoiceDetailContent" class="mt-4 max-h-[70vh] overflow-y-auto">
                <!-- Detaylar burada gösterilecek -->
                <div class="text-center py-10">
                    <svg class="animate-spin h-10 w-10 text-blue-500 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <p class="mt-2 text-gray-600">Yükleniyor...</p>
                </div>
            </div>
            <div class="mt-4 flex justify-end border-t pt-3">
                <button id="printDetailBtn" onclick="printInvoiceDetail()" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded mr-2">
                    <svg class="w-5 h-5 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    Yazdır
                </button>
                <button onclick="closeInvoiceDetailModal()" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded">
                    Kapat
                </button>
            </div>
        </div>
    </div>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- Müşteriye SMs/MAİL Gönderme -->
<script>
function sendToCustomer(invoiceId, customerId) {
    const hasCustomer = customerId !== null;
    
    // Başlangıç HTML'i
    let formHtml = `
        <div class="mb-4">
            <p class="mb-2 text-sm font-medium text-gray-700">Gönderme yöntemi seçin:</p>
            <div class="flex space-x-4">
                <label class="inline-flex items-center">
                    <input type="radio" name="sendMethod" value="email" class="form-radio h-4 w-4 text-blue-600" checked>
                    <span class="ml-2 text-sm text-gray-700">E-posta</span>
                </label>
                <label class="inline-flex items-center">
                    <input type="radio" name="sendMethod" value="sms" class="form-radio h-4 w-4 text-blue-600">
                    <span class="ml-2 text-sm text-gray-700">SMS</span>
                </label>
            </div>
        </div>
        <div id="emailInput" class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Müşteri E-posta:</label>
            <input id="customerEmail" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
        </div>
        <div id="smsInput" class="mb-4 hidden">
            <label class="block text-sm font-medium text-gray-700 mb-1">Müşteri Telefon:</label>
            <input id="customerPhone" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Mesaj (İsteğe bağlı):</label>
            <textarea id="customMessage" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md" rows="3"></textarea>
        </div>
    `;
    
    Swal.fire({
        title: 'Müşteriye Gönder',
        html: formHtml,
        showCancelButton: true,
        confirmButtonText: 'Gönder',
        cancelButtonText: 'İptal',
        confirmButtonColor: '#10B981',
        preConfirm: () => {
            const sendMethod = document.querySelector('input[name="sendMethod"]:checked').value;
            const customMessage = document.getElementById('customMessage').value;
            
            if (sendMethod === 'email') {
                const email = document.getElementById('customerEmail').value;
                if (!email) {
                    Swal.showValidationMessage('Lütfen e-posta adresi girin');
                    return false;
                }
                return { method: 'email', email, message: customMessage };
            } else {
                const phone = document.getElementById('customerPhone').value;
                if (!phone) {
                    Swal.showValidationMessage('Lütfen telefon numarası girin');
                    return false;
                }
                return { method: 'sms', phone, message: customMessage };
            }
        },
        didOpen: () => {
            // Müşteri bilgilerini almak için AJAX isteği - eğer müşteri ID varsa
            if (hasCustomer) {
                fetch('api/get_customer_info.php?id=' + customerId)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            if (data.email) {
                                document.getElementById('customerEmail').value = data.email;
                            }
                            if (data.phone) {
                                document.getElementById('customerPhone').value = data.phone;
                            }
                        }
                    });
            }
            
            // Gönderme yöntemi değiştiğinde form alanlarını güncelle
            const emailInput = document.getElementById('emailInput');
            const smsInput = document.getElementById('smsInput');
            
            document.querySelectorAll('input[name="sendMethod"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    if (this.value === 'email') {
                        emailInput.classList.remove('hidden');
                        smsInput.classList.add('hidden');
                    } else {
                        emailInput.classList.add('hidden');
                        smsInput.classList.remove('hidden');
                    }
                });
            });
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const data = result.value;
            
            // Faturayı müşteriye gönder
            fetch('api/send_to_customer.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    invoice_id: invoiceId,
                    customer_id: customerId,
                    method: data.method,
                    email: data.email || null,
                    phone: data.phone || null,
                    message: data.message
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire(
                        'Başarılı!',
                        'Fatura müşteriye gönderildi.',
                        'success'
                    );
                } else {
                    Swal.fire(
                        'Hata!',
                        data.message || 'Fatura gönderilirken bir hata oluştu.',
                        'error'
                    );
                }
            })
            .catch(error => {
                Swal.fire(
                    'Hata!',
                    'Fatura gönderilirken bir hata oluştu: ' + error.message,
                    'error'
                );
            });
        }
    });
}
</script>

<?php
// Sayfa özel scriptleri
$page_scripts = '
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="assets/js/sales_invoices.js"></script>
';

// Footer'ı dahil et
include 'footer.php';
?>
</body>
</html>