<?php
session_start();
require_once 'db_connection.php';

// Yetki kontrolü
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: index.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Satış Raporları</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            <form id="reportFilters" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Başlangıç Tarihi</label>
                    <input type="date" name="start_date" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Bitiş Tarihi</label>
                    <input type="date" name="end_date" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Mağaza</label>
                    <select name="magaza" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">Tüm Mağazalar</option>
                        <?php
                        $stmt = $conn->query("SELECT id, ad FROM magazalar ORDER BY ad");
                        while ($magaza = $stmt->fetch()) {
                            echo "<option value='{$magaza['id']}'>{$magaza['ad']}</option>";
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
                <p class="text-2xl font-bold" id="totalSales">₺0</p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow">
                <h3 class="text-sm text-gray-500">Toplam Ürün Adedi</h3>
                <p class="text-2xl font-bold" id="totalProducts">0</p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow">
                <h3 class="text-sm text-gray-500">Ortalama Sepet</h3>
                <p class="text-2xl font-bold" id="avgBasket">₺0</p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow">
                <h3 class="text-sm text-gray-500">İade Oranı</h3>
                <p class="text-2xl font-bold" id="returnRate">%0</p>
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
                <button onclick="exportToExcel()" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                    Excel'e Aktar
                </button>
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
                <tbody class="bg-white divide-y divide-gray-200" id="salesTableBody">
                    <!-- JavaScript ile doldurulacak -->
                </tbody>
            </table>
        </div>
    </div>

    <script src="/assets/js/reports.js"></script>
</body>
</html>