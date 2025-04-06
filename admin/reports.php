<?php
// Sayfa yüklenme süresini hesapla
$start_time = microtime(true);

// Header'ı dahil et
include 'header.php';
require_once 'db_connection.php';
require_once 'stock_functions.php';

// Yetki kontrolü
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: index.php");
    exit;
}

// Varsayılan tarih aralığı
$current_date = date('Y-m-d');
$last_month = date('Y-m-d', strtotime('-30 days'));

// Filtreleri al
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : $last_month;
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : $current_date;
$magaza_id = isset($_GET['magaza']) ? intval($_GET['magaza']) : null;

// Özet istatistikleri çek
$total_sales_query = "
    SELECT 
        COALESCE(SUM(net_tutar), 0) as total_sales,
        COUNT(*) as total_invoices,
        COALESCE(SUM(net_tutar) / COUNT(*), 0) as avg_basket
    FROM 
        satis_faturalari
    WHERE 
        DATE(fatura_tarihi) BETWEEN :start_date AND :end_date
        " . ($magaza_id ? " AND magaza = :magaza_id" : "");

$stmt = $conn->prepare($total_sales_query);
$stmt->bindParam(':start_date', $start_date);
$stmt->bindParam(':end_date', $end_date);
if ($magaza_id) {
    $stmt->bindParam(':magaza_id', $magaza_id);
}
$stmt->execute();
$sales_summary = $stmt->fetch(PDO::FETCH_ASSOC);

// İade oranını hesapla
$returns_query = "
    SELECT 
        COUNT(*) as return_count
    FROM 
        satis_faturalari
    WHERE 
        DATE(fatura_tarihi) BETWEEN :start_date AND :end_date
        AND islem_turu = 'iade'
        " . ($magaza_id ? " AND magaza = :magaza_id" : "");

$stmt = $conn->prepare($returns_query);
$stmt->bindParam(':start_date', $start_date);
$stmt->bindParam(':end_date', $end_date);
if ($magaza_id) {
    $stmt->bindParam(':magaza_id', $magaza_id);
}
$stmt->execute();
$return_data = $stmt->fetch(PDO::FETCH_ASSOC);

// Toplam ürün sayısını çek
$total_products_query = "
    SELECT 
        SUM(sd.miktar) as total_products
    FROM 
        satis_fatura_detay sd
    JOIN 
        satis_faturalari sf ON sd.fatura_id = sf.id
    WHERE 
        DATE(sf.fatura_tarihi) BETWEEN :start_date AND :end_date
        " . ($magaza_id ? " AND sf.magaza = :magaza_id" : "");

$stmt = $conn->prepare($total_products_query);
$stmt->bindParam(':start_date', $start_date);
$stmt->bindParam(':end_date', $end_date);
if ($magaza_id) {
    $stmt->bindParam(':magaza_id', $magaza_id);
}
$stmt->execute();
$product_data = $stmt->fetch(PDO::FETCH_ASSOC);

// İade oranını hesapla
$return_rate = 0;
if ($sales_summary['total_invoices'] > 0) {
    $return_rate = ($return_data['return_count'] / $sales_summary['total_invoices']) * 100;
}

// Günlük satış grafiği için veri çek
$daily_sales_query = "
    SELECT 
        DATE(fatura_tarihi) as sale_date,
        SUM(net_tutar) as daily_total
    FROM 
        satis_faturalari
    WHERE 
        DATE(fatura_tarihi) BETWEEN :start_date AND :end_date
        AND islem_turu = 'satis'
        " . ($magaza_id ? " AND magaza = :magaza_id" : "") . "
    GROUP BY 
        DATE(fatura_tarihi)
    ORDER BY 
        sale_date";

$stmt = $conn->prepare($daily_sales_query);
$stmt->bindParam(':start_date', $start_date);
$stmt->bindParam(':end_date', $end_date);
if ($magaza_id) {
    $stmt->bindParam(':magaza_id', $magaza_id);
}
$stmt->execute();
$daily_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Kategori bazlı satış grafiği için veri çek
$category_sales_query = "
    SELECT 
        d.ad as departman,
        SUM(sf.net_tutar) as total_sales
    FROM 
        satis_faturalari sf
    JOIN 
        satis_fatura_detay sfd ON sf.id = sfd.fatura_id
    JOIN 
        urun_stok us ON sfd.urun_id = us.id
    JOIN 
        departmanlar d ON us.departman_id = d.id
    WHERE 
        DATE(sf.fatura_tarihi) BETWEEN :start_date AND :end_date
        AND sf.islem_turu = 'satis'
        " . ($magaza_id ? " AND sf.magaza = :magaza_id" : "") . "
    GROUP BY 
        d.id
    ORDER BY 
        total_sales DESC
    LIMIT 10";

$stmt = $conn->prepare($category_sales_query);
$stmt->bindParam(':start_date', $start_date);
$stmt->bindParam(':end_date', $end_date);
if ($magaza_id) {
    $stmt->bindParam(':magaza_id', $magaza_id);
}
$stmt->execute();
$category_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Detaylı satış tablosu için veri çek
$sales_detail_query = "
    SELECT 
        DATE_FORMAT(sf.fatura_tarihi, '%d.%m.%Y') as formatted_date,
        sf.fatura_no,
        m.ad as magaza_adi,
        us.ad as urun_adi,
        sfd.miktar,
        sfd.birim_fiyat,
        sfd.toplam_tutar
    FROM 
        satis_faturalari sf
    JOIN 
        satis_fatura_detay sfd ON sf.id = sfd.fatura_id
    JOIN 
        urun_stok us ON sfd.urun_id = us.id
    JOIN 
        magazalar m ON sf.magaza = m.id
    WHERE 
        DATE(sf.fatura_tarihi) BETWEEN :start_date AND :end_date
        " . ($magaza_id ? " AND sf.magaza = :magaza_id" : "") . "
    ORDER BY 
        sf.fatura_tarihi DESC
    LIMIT 50";

$stmt = $conn->prepare($sales_detail_query);
$stmt->bindParam(':start_date', $start_date);
$stmt->bindParam(':end_date', $end_date);
if ($magaza_id) {
    $stmt->bindParam(':magaza_id', $magaza_id);
}
$stmt->execute();
$sales_detail = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Sayfa yüklenme süresini hesapla
$end_time = microtime(true);
$execution_time = round($end_time - $start_time, 3);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Satış Raporları</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold">Satış Raporları</h1>
                <p class="text-gray-600">Detaylı satış analizi ve raporlama</p>
            </div>
            <a href="admin_dashboard.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                Dashboard'a Dön
            </a>
        </div>

        <!-- Filtreler -->
        <div class="bg-white p-6 rounded-lg shadow mb-6">
            <form id="reportFilters" class="grid grid-cols-1 md:grid-cols-4 gap-4" method="GET">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Başlangıç Tarihi</label>
                    <input type="date" name="start_date" value="<?php echo $start_date; ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Bitiş Tarihi</label>
                    <input type="date" name="end_date" value="<?php echo $end_date; ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Mağaza</label>
                    <select name="magaza" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">Tüm Mağazalar</option>
                        <?php
                        $stmt = $conn->query("SELECT id, ad FROM magazalar ORDER BY ad");
                        while ($magaza = $stmt->fetch()) {
                            $selected = ($magaza_id == $magaza['id']) ? 'selected' : '';
                            echo "<option value='{$magaza['id']}' {$selected}>{$magaza['ad']}</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 w-full">
                        Raporu Getir
                    </button>
                </div>
            </form>
        </div>

        <!-- Özet Kartlar -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white p-6 rounded-lg shadow">
                <h3 class="text-sm text-gray-500">Toplam Satış</h3>
                <p class="text-2xl font-bold">₺<?php echo number_format($sales_summary['total_sales'], 2, ',', '.'); ?></p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow">
                <h3 class="text-sm text-gray-500">Toplam Ürün Adedi</h3>
                <p class="text-2xl font-bold"><?php echo number_format($product_data['total_products'] ?? 0, 0, ',', '.'); ?></p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow">
                <h3 class="text-sm text-gray-500">Ortalama Sepet</h3>
                <p class="text-2xl font-bold">₺<?php echo number_format($sales_summary['avg_basket'], 2, ',', '.'); ?></p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow">
                <h3 class="text-sm text-gray-500">İade Oranı</h3>
                <p class="text-2xl font-bold">%<?php echo number_format($return_rate, 2, ',', '.'); ?></p>
            </div>
        </div>

        <!-- Grafikler -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <!-- Günlük Satış Grafiği -->
            <div class="bg-white p-6 rounded-lg shadow">
                <h3 class="text-lg font-semibold mb-4">Günlük Satış Grafiği</h3>
                <canvas id="dailySalesChart"></canvas>
            </div>
            <!-- Kategori Dağılımı -->
            <div class="bg-white p-6 rounded-lg shadow">
                <h3 class="text-lg font-semibold mb-4">Kategori Dağılımı</h3>
                <canvas id="categoryChart"></canvas>
            </div>
        </div>

        <!-- Detaylı Tablo -->
        <div class="bg-white p-6 rounded-lg shadow overflow-x-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold">Satış Detayları</h3>
                <form action="excel_export.php" method="post" class="m-0">
                    <input type="hidden" name="start_date" value="<?php echo $start_date; ?>">
                    <input type="hidden" name="end_date" value="<?php echo $end_date; ?>">
                    <input type="hidden" name="magaza_id" value="<?php echo $magaza_id; ?>">
                    <button type="submit" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                        Excel'e Aktar
                    </button>
                </form>
            </div>
            <table class="min-w-full divide-y divide-gray-200" id="salesTable">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tarih</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fatura No</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mağaza</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ürün</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Miktar</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Birim Fiyat</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Toplam</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($sales_detail as $row): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap"><?php echo $row['formatted_date']; ?></td>
                        <td class="px-6 py-4 whitespace-nowrap"><?php echo $row['fatura_no']; ?></td>
                        <td class="px-6 py-4 whitespace-nowrap"><?php echo $row['magaza_adi']; ?></td>
                        <td class="px-6 py-4 whitespace-nowrap"><?php echo $row['urun_adi']; ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-right"><?php echo number_format($row['miktar'], 0, ',', '.'); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-right">₺<?php echo number_format($row['birim_fiyat'], 2, ',', '.'); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-right">₺<?php echo number_format($row['toplam_tutar'], 2, ',', '.'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($sales_detail)): ?>
                    <tr>
                        <td colspan="7" class="px-6 py-4 text-center text-gray-500">Bu kriterlere uygun satış kaydı bulunamadı.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Günlük Satış Grafiği
            var dailySalesCtx = document.getElementById('dailySalesChart').getContext('2d');
            var dailySalesChart = new Chart(dailySalesCtx, {
                type: 'line',
                data: {
                    labels: [
                        <?php 
                        foreach ($daily_sales as $day) {
                            echo "'" . date('d.m.Y', strtotime($day['sale_date'])) . "',";
                        }
                        ?>
                    ],
                    datasets: [{
                        label: 'Günlük Satış',
                        data: [
                            <?php 
                            foreach ($daily_sales as $day) {
                                echo $day['daily_total'] . ",";
                            }
                            ?>
                        ],
                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '₺' + value.toLocaleString('tr-TR');
                                }
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return '₺' + context.raw.toLocaleString('tr-TR');
                                }
                            }
                        }
                    }
                }
            });

            // Kategori Dağılımı Grafiği
            var categoryCtx = document.getElementById('categoryChart').getContext('2d');
            var categoryChart = new Chart(categoryCtx, {
                type: 'pie',
                data: {
                    labels: [
                        <?php 
                        foreach ($category_sales as $category) {
                            echo "'" . $category['departman'] . "',";
                        }
                        ?>
                    ],
                    datasets: [{
                        data: [
                            <?php 
                            foreach ($category_sales as $category) {
                                echo $category['total_sales'] . ",";
                            }
                            ?>
                        ],
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.2)',
                            'rgba(54, 162, 235, 0.2)',
                            'rgba(255, 206, 86, 0.2)',
                            'rgba(75, 192, 192, 0.2)',
                            'rgba(153, 102, 255, 0.2)',
                            'rgba(255, 159, 64, 0.2)',
                            'rgba(255, 99, 132, 0.2)',
                            'rgba(54, 162, 235, 0.2)',
                            'rgba(255, 206, 86, 0.2)',
                            'rgba(75, 192, 192, 0.2)'
                        ],
                        borderColor: [
                            'rgba(255, 99, 132, 1)',
                            'rgba(54, 162, 235, 1)',
                            'rgba(255, 206, 86, 1)',
                            'rgba(75, 192, 192, 1)',
                            'rgba(153, 102, 255, 1)',
                            'rgba(255, 159, 64, 1)',
                            'rgba(255, 99, 132, 1)',
                            'rgba(54, 162, 235, 1)',
                            'rgba(255, 206, 86, 1)',
                            'rgba(75, 192, 192, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.label + ': ₺' + context.raw.toLocaleString('tr-TR');
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>

<?php
// Sayfa özel scriptleri
$page_scripts = '
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
';

// Footer'ı dahil et
include 'footer.php';
?>