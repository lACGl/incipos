<?php
// Sayfa yüklenme süresini hesapla
$start_time = microtime(true);

// Header'ı dahil et
include 'header.php';
?>

<div class="container mx-auto px-4 py-8">
    <!-- Dashboard özet kartları -->
    <div id="dashboardRoot" class="mb-8"></div>

    <!-- Ana Menü Kartları -->
    <div class="grid md:grid-cols-3 gap-6 mb-8">
        <!-- Stok Listesi -->
        <div class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition duration-200">
            <h2 class="text-xl font-semibold mb-3">Stok Listesi</h2>
            <p class="text-gray-600 mb-4">Ürünlerinizi buradan görüntüleyebilir ve yönetebilirsiniz.</p>
            <a href="stock_list.php" class="text-blue-500 hover:text-blue-700 font-medium">Stok Listesine Git →</a>
        </div>

        <!-- Alış Faturaları -->
        <div class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition duration-200">
            <h2 class="text-xl font-semibold mb-3">Alış Faturaları</h2>
            <p class="text-gray-600 mb-4">Alış faturalarınızı burada görüntüleyebilir ve yönetebilirsiniz.</p>
            <a href="purchase_invoices.php" class="text-blue-500 hover:text-blue-700 font-medium">Alış Faturalarına Git →</a>
        </div>

        <!-- Satış Faturaları -->
        <div class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition duration-200">
            <h2 class="text-xl font-semibold mb-3">Satış Faturaları</h2>
            <p class="text-gray-600 mb-4">Satış faturalarınızı burada görüntüleyebilir ve yönetebilirsiniz.</p>
            <a href="sales_invoices.php" class="text-blue-500 hover:text-blue-700 font-medium">Satış Faturalarına Git →</a>
        </div>
		
		<!-- Müşteriler-->
        <div class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition duration-200">
            <h2 class="text-xl font-semibold mb-3">Müşteriler</h2>
            <p class="text-gray-600 mb-4">Müşterilerinizi ve İnci Kartları burada görüntüleyebilir ve yönetebilirsiniz.</p>
            <a href="customers.php" class="text-blue-500 hover:text-blue-700 font-medium">Müşterilere Git →</a>
        </div>

        <!-- Raporlar -->
        <div class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition duration-200">
            <h2 class="text-xl font-semibold mb-3">Raporlar</h2>
            <p class="text-gray-600 mb-4">Satış ve stok raporlarını görüntüleyebilirsiniz.</p>
            <a href="reports.php" class="bg-indigo-500 text-white px-4 py-2 rounded hover:bg-indigo-600">Raporlara Git →</a>
        </div>
    </div>
</div>


<?php
// Sayfa yüklenme süresini hesapla
$end_time = microtime(true);
$execution_time = round($end_time - $start_time, 3);

// Sayfa özel scriptleri
$page_scripts = '
<script src="https://unpkg.com/react@18/umd/react.production.min.js"></script>
<script src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js"></script>
<script src="assets/js/dashboard-settings.js"></script>
<script type="module" src="assets/js/DashboardSummary.js"></script>
';

// Footer'ı dahil et
include 'footer.php';
?>