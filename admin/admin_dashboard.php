<?php
// Sayfa yüklenme süresini hesapla
$start_time = microtime(true);

// Header'ı dahil et
include 'header.php';
?>

<div class="dashboard-container">
    <div class="dashboard-header">
        <div>
            <h1 class="dashboard-title">Hoş Geldiniz, <?php echo htmlspecialchars($user['kullanici_adi']); ?></h1>
            <p class="dashboard-subtitle">İnciPOS Admin Paneli</p>
        </div>
        <div class="date-time">
            <span id="current-date-time"></span>
        </div>
    </div>

    <!-- Özet Kartları -->
    <div class="dashboard-cards">
        <div class="dashboard-card card-sales">
            <div class="card-icon card-icon-blue">
                <i class="fas fa-shopping-cart"></i>
            </div>
            <div class="card-title">Günlük Satış</div>
            <div class="card-value">₺<span id="daily-sales">0.00</span></div>
            <div class="card-percent percent-up">
                <i class="fas fa-arrow-up"></i> <span id="sales-percent">0</span>% geçen haftaya göre
            </div>
        </div>
        
        <div class="dashboard-card card-orders">
            <div class="card-icon card-icon-green">
                <i class="fas fa-file-invoice"></i>
            </div>
            <div class="card-title">Sipariş Sayısı</div>
            <div class="card-value"><span id="order-count">0</span></div>
            <div class="card-percent percent-up">
                <i class="fas fa-arrow-up"></i> <span id="orders-percent">0</span>% geçen aya göre
            </div>
        </div>
        
        <div class="dashboard-card card-customers">
            <div class="card-icon card-icon-yellow">
                <i class="fas fa-users"></i>
            </div>
            <div class="card-title">Toplam Müşteri</div>
            <div class="card-value"><span id="customer-count">0</span></div>
            <div class="card-percent percent-up">
                <i class="fas fa-arrow-up"></i> <span id="customers-percent">0</span>% bu ay
            </div>
        </div>
        
        <div class="dashboard-card card-revenue">
            <div class="card-icon card-icon-purple">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="card-title">Aylık Gelir</div>
            <div class="card-value">₺<span id="monthly-revenue">0.00</span></div>
            <div class="card-percent percent-up">
                <i class="fas fa-arrow-up"></i> <span id="revenue-percent">0</span>% geçen aya göre
            </div>
        </div>
    </div>

    <!-- Grafikler -->
    <div class="chart-container">
        <div class="chart-header">
            <div class="chart-title">Satış İstatistikleri</div>
            <div class="chart-filters">
                <button class="chart-filter active" data-period="week">Haftalık</button>
                <button class="chart-filter" data-period="month">Aylık</button>
                <button class="chart-filter" data-period="year">Yıllık</button>
            </div>
        </div>
        <div id="sales-chart" style="height: 300px;"></div>
    </div>

    <!-- Hızlı Erişim Menüsü -->
    <div class="section-header">
        <h2 class="section-title">Ana Modüller</h2>
        <p class="section-subtitle">Temel işlemlerinizi yönetin</p>
    </div>

    <div class="dashboard-menu">
        <div class="menu-card" onclick="window.location.href='stock_list.php'" style="cursor: pointer;">
            <div class="menu-icon">
                <i class="fas fa-box"></i>
            </div>
            <h3 class="menu-title">Stok Yönetimi</h3>
            <p class="menu-description">Ürünlerinizi ve stok seviyelerinizi yönetin.</p>
            <div class="menu-link">
                Stok Listesine Git <i class="fas fa-chevron-right"></i>
            </div>
        </div>
        
        <div class="menu-card" onclick="window.location.href='purchase_invoices.php'" style="cursor: pointer;">
            <div class="menu-icon">
                <i class="fas fa-truck-loading"></i>
            </div>
            <h3 class="menu-title">Alış Faturaları</h3>
            <p class="menu-description">Tedarikçilerden alımlarınızı takip edin.</p>
            <div class="menu-link">
                Alış Faturalarına Git <i class="fas fa-chevron-right"></i>
            </div>
        </div>
        
        <div class="menu-card"  onclick="window.location.href='sales_invoices.php'" style="cursor: pointer;">
            <div class="menu-icon">
                <i class="fas fa-receipt"></i>
            </div>
            <h3 class="menu-title">Satış Faturaları</h3>
            <p class="menu-description">Satışlarınızı ve faturalarınızı görüntüleyin.</p>
            <div class="menu-link">
                Satış Faturalarına Git <i class="fas fa-chevron-right"></i>
            </div>
        </div>
        
        <div class="menu-card"  onclick="window.location.href='customers.php'" style="cursor: pointer;">
            <div class="menu-icon">
                <i class="fas fa-user-friends"></i>
            </div>
            <h3 class="menu-title">Müşteriler</h3>
            <p class="menu-description">Müşterilerinizi ve İnci Kartları yönetin.</p>
            <div class="menu-link">
                Müşterilere Git <i class="fas fa-chevron-right"></i>
            </div>
        </div>
        
        <div class="menu-card" onclick="window.location.href='reports.php'" style="cursor: pointer;">
            <div class="menu-icon">
                <i class="fas fa-chart-pie"></i>
            </div>
            <h3 class="menu-title">Raporlar</h3>
            <p class="menu-description">Detaylı satış ve stok raporlarını görüntüleyin.</p>
            <div class="menu-link">
                Raporlara Git <i class="fas fa-chevron-right"></i>
            </div>
        </div>
        
        <div class="menu-card" onclick="window.location.href='settings.php'" style="cursor: pointer;">
			<div class="menu-icon">
				<i class="fas fa-cog"></i>
			</div>
			<h3 class="menu-title">Ayarlar</h3>
			<p class="menu-description">Sistem ayarlarını ve kullanıcı ayarlarını yönetin.</p>
			<div class="menu-link">
				Ayarlara Git <i class="fas fa-chevron-right"></i>
			</div>
		</div>

    </div>
    
    <!-- Eklentiler Bölümü -->
	<div class="section-header mt-8">
        <h2 class="section-title">Eklentiler</h2>
        <p class="section-subtitle">Eklentiler ile işlerinizi daha kolay ve yorulmadan halledin.</p>
    </div>
    
    <div class="dashboard-menu">
        <div class="menu-card api-card" onclick="window.location.href='extensions/discount/discounts.php'" style="cursor: pointer;">
            <div class="menu-icon extension-icon">
			<i class="fas fa-percentage"></i>
            </div>
            <h3 class="menu-title">İndirim Yönetimi</h3>
            <p class="menu-description">Tek bir sayfa üzerinden indirimlerinizi yönetin.</p>
            <div class="menu-link">
                İndirim Yönetimine Git <i class="fas fa-chevron-right"></i>
            </div>
        </div>
		
		<div class="menu-card api-card" onclick="window.location.href='extensions/stock_management/critical_stock.php'" style="cursor: pointer;">
            <div class="menu-icon extension-icon">
			<i class="fas fa-alarm-exclamation"></i>
            </div>
            <h3 class="menu-title">Kritik Stok & Satın Alma Fırsatları</h3>
            <p class="menu-description">Kritik seviyenin altına düşen ve tedarik edilebilecek ürünleri tek ekrandan görüntüleyin, kolayca siparişe dönüştürün.</p>
            <div class="menu-link">
                Stok Yönetimine Git <i class="fas fa-chevron-right"></i>
            </div>
        </div>
		
		<div class="menu-card api-card" onclick="window.location.href='extensions/file_management/file_management.php'" style="cursor: pointer;">
            <div class="menu-icon extension-icon">
			<i class="fas fa-file-archive"></i>
            </div>
            <h3 class="menu-title">Dosya Yönetimi</h3>
            <p class="menu-description">Görselleri kolayca yükleyin, otomatik sıkıştırma ile hızlı ve düzenli bir arşiv oluşturun.</p>
            <div class="menu-link">
                Dosya Yönetimine Git <i class="fas fa-chevron-right"></i>
            </div>
        </div>
        
        <div class="menu-card api-card" onclick="window.location.href='extensions/price_management/'" style="cursor: pointer;">
            <div class="menu-icon extension-icon">
                <i class="fa-solid fa-plus-minus"></i>
            </div>
            <h3 class="menu-title">Fiyat Yönetimi</h3>
            <p class="menu-description">Tek bir sayfa üzerinden tüm ürünlerin fiyatlarını yönetin.</p>
            <div class="menu-link">
                Dosya Yönetimine Git <i class="fas fa-chevron-right"></i>
            </div>
        </div>
        
        <div class="menu-card api-card" onclick="window.location.href=''" style="cursor: pointer;">
            <div class="menu-icon extension-icon">
            <i class="fa-solid fa-print"></i>
            </div>
            <h3 class="menu-title">Etiket Yönetimi</h3>
            <p class="menu-description">Tek bir sayfa üzerinden tüm ürünlerin fiyatlarını yönetin.</p>
            <div class="menu-link">
                Dosya Yönetimine Git <i class="fas fa-chevron-right"></i>
            </div>
        </div>
    </div>
    <!-- Entegrasyonlar Bölümü -->
    <div class="section-header mt-8">
        <h2 class="section-title">Entegrasyonlar</h2>
        <p class="section-subtitle">Harici sistemler ve API bağlantıları</p>
    </div>
    
    <div class="dashboard-menu">
	
        <div class="menu-card api-card" onclick="window.location.href='api/prestashop/index.php'" style="cursor: pointer;">
            <div class="menu-icon api-icon">
                <i class="fas fa-shopping-cart"></i>
            </div>
            <h3 class="menu-title">Prestashop</h3>
            <p class="menu-description">E-ticaret sitenizi ve ürün senkronizasyonunu yönetin.</p>
            <div class="menu-link">
                Prestashop Yönetimine Git <i class="fas fa-chevron-right"></i>
            </div>
        </div>
        
		<div class="menu-card api-card" onclick="window.location.href='https://pos.incikirtasiye.com/api/birfatura/dashboard.php'" style="cursor: pointer;">
			<div class="menu-icon api-icon">
				<i class="fas fa-file-invoice-dollar"></i>
			</div>
			<h3 class="menu-title">BirFatura</h3>
			<p class="menu-description">E-fatura entegrasyonunuzu ve işlemlerinizi yönetin.</p>
			<div class="menu-link">
				BirFatura Yönetimine Git <i class="fas fa-chevron-right"></i>
			</div>
		</div>

		
		<div class="menu-card api-card"  onclick="window.location.href='api/netgsm/'" style="cursor: pointer;">
            <div class="menu-icon api-icon">
                <i class="fas fa-address-book"></i>
            </div>
            <h3 class="menu-title">NetGSM</h3>
            <p class="menu-description">SMS gönderimlerinizi yönetin ve mesaj geçmişinizi görüntüleyin.</p>
            <div class="menu-link">
                NETGsm Yönetimine Git <i class="fas fa-chevron-right"></i>
            </div>
        </div>
        
        <div class="menu-card api-card"  onclick="window.location.href='incibot/claude_chat.php/'" style="cursor: pointer;">
            <div class="menu-icon api-icon">
                <i class="fas fa-robot"></i>
            </div>
            <h3 class="menu-title">İnciBOT</h3>
            <p class="menu-description">Tüm raporları İnciBottan alın..</p>
            <div class="menu-link">
                İnciBOT Yönetimine Git <i class="fas fa-chevron-right"></i>
            </div>
        </div>
		
    </div>
</div>

<style>
/* Ek stiller */
.section-header {
    margin-bottom: 1.5rem;
}

.section-title {
    font-size: 1.5rem;
    font-weight: bold;
    color: #333;
}

.section-subtitle {
    color: #666;
    font-size: 0.9rem;
}

.mt-8 {
    margin-top: 2rem;
}

.api-card {
    border-left: 4px solid #4f46e5;
}

.api-icon {
    color:navy;
}
</style>

<?php
// Sayfa yüklenme süresini hesapla
$end_time = microtime(true);
$execution_time = round($end_time - $start_time, 3);

// Sayfa özel scriptleri
$page_scripts = '
<script src="assets/js/dashboard-settings.js"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/js/all.min.js"></script>
<script>
    // Tarih ve saat gösterimi
    function updateDateTime() {
        const now = new Date();
        const options = { 
            weekday: "long", 
            year: "numeric", 
            month: "long", 
            day: "numeric",
            hour: "2-digit",
            minute: "2-digit"
        };
        document.getElementById("current-date-time").textContent = now.toLocaleDateString("tr-TR", options);
    }
    
    updateDateTime();
    setInterval(updateDateTime, 60000);
    
    // Dashboard verileri
    fetch("api/get_dashboard_summary.php")
        .then(response => response.json())
        .then(data => {
            // Özet kartları güncelleme
            document.getElementById("daily-sales").textContent = data.dailySales.toLocaleString("tr-TR");
            document.getElementById("order-count").textContent = data.orderCount.toLocaleString("tr-TR");
            document.getElementById("customer-count").textContent = data.customerCount.toLocaleString("tr-TR");
            document.getElementById("monthly-revenue").textContent = data.monthlyRevenue.toLocaleString("tr-TR");
            
            document.getElementById("sales-percent").textContent = data.salesGrowth;
            document.getElementById("orders-percent").textContent = data.ordersGrowth;
            document.getElementById("customers-percent").textContent = data.customersGrowth;
            document.getElementById("revenue-percent").textContent = data.revenueGrowth;
            
            // Büyüme oranlarına göre sınıf ekleme - Null kontrolü ile geliştirilmiş
            if (data.salesGrowth < 0) {
                const salesCard = document.querySelector(".card-sales .card-percent");
                const salesIcon = document.querySelector(".card-sales .card-percent i");
                
                if (salesCard) {
                    salesCard.classList.remove("percent-up");
                    salesCard.classList.add("percent-down");
                }
                
                if (salesIcon) {
                    salesIcon.classList.remove("fa-arrow-up");
                    salesIcon.classList.add("fa-arrow-down");
                }
            }
            
            if (data.ordersGrowth < 0) {
                const ordersCard = document.querySelector(".card-orders .card-percent");
                const ordersIcon = document.querySelector(".card-orders .card-percent i");
                
                if (ordersCard) {
                    ordersCard.classList.remove("percent-up");
                    ordersCard.classList.add("percent-down");
                }
                
                if (ordersIcon) {
                    ordersIcon.classList.remove("fa-arrow-up");
                    ordersIcon.classList.add("fa-arrow-down");
                }
            }
            
            if (data.customersGrowth < 0) {
                const customersCard = document.querySelector(".card-customers .card-percent");
                const customersIcon = document.querySelector(".card-customers .card-percent i");
                
                if (customersCard) {
                    customersCard.classList.remove("percent-up");
                    customersCard.classList.add("percent-down");
                }
                
                if (customersIcon) {
                    customersIcon.classList.remove("fa-arrow-up");
                    customersIcon.classList.add("fa-arrow-down");
                }
            }
            
            if (data.revenueGrowth < 0) {
                const revenueCard = document.querySelector(".card-revenue .card-percent");
                const revenueIcon = document.querySelector(".card-revenue .card-percent i");
                
                if (revenueCard) {
                    revenueCard.classList.remove("percent-up");
                    revenueCard.classList.add("percent-down");
                }
                
                if (revenueIcon) {
                    revenueIcon.classList.remove("fa-arrow-up");
                    revenueIcon.classList.add("fa-arrow-down");
                }
            }
            
            // Grafik oluşturma
            if (data.salesChartData) {
                initChart(data.salesChartData);
            }
        })
        .catch(error => console.error("Veri yüklenirken hata oluştu:", error));
    
    // Satış grafiği
    function initChart(chartData) {
        if (!chartData || !chartData.values || !chartData.labels) {
            console.error("Geçersiz grafik verisi");
            return;
        }
        
        var options = {
            series: [{
                name: "Satışlar",
                data: chartData.values
            }],
            chart: {
                height: 300,
                type: "area",
                toolbar: {
                    show: false
                },
                fontFamily: "Segoe UI, Tahoma, Geneva, Verdana, sans-serif"
            },
            colors: ["#3b82f6"],
            dataLabels: {
                enabled: false
            },
            stroke: {
                curve: "smooth",
                width: 2
            },
            fill: {
                type: "gradient",
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.7,
                    opacityTo: 0.2,
                    stops: [0, 90, 100]
                }
            },
            xaxis: {
                categories: chartData.labels,
                tooltip: {
                    enabled: false
                }
            },
            yaxis: {
                labels: {
                    formatter: function (value) {
                        return "₺" + value.toLocaleString("tr-TR");
                    }
                }
            },
            tooltip: {
                y: {
                    formatter: function (value) {
                        return "₺" + value.toLocaleString("tr-TR");
                    }
                }
            }
        };

        var chart = new ApexCharts(document.querySelector("#sales-chart"), options);
        chart.render();
        
        // Grafik filtresi değiştiğinde
        document.querySelectorAll(".chart-filter").forEach(function(button) {
            button.addEventListener("click", function() {
                // Aktif sınıfı tüm filtrelerden kaldır
                document.querySelectorAll(".chart-filter").forEach(function(btn) {
                    btn.classList.remove("active");
                });
                
                // Tıklanan butona aktif sınıfını ekle
                this.classList.add("active");
                
                // Yeni veri yükle
                const period = this.getAttribute("data-period");
                fetch(`api/get_sales_chart.php?period=${period}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data && data.labels && data.values) {
                            chart.updateOptions({
                                xaxis: {
                                    categories: data.labels
                                }
                            });
                            chart.updateSeries([{
                                name: "Satışlar",
                                data: data.values
                            }]);
                        }
                    })
                    .catch(error => console.error("Grafik verisi yüklenirken hata oluştu:", error));
            });
        });
    }
</script>
';

// Footer'ı dahil et
include 'footer.php';
?>