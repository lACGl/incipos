<?php
session_start();
require_once 'db_connection.php';

// Kullanıcı giriş kontrolü
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: index.php");  // Giriş yapılmamışsa login sayfasına yönlendir
    exit;
}

// Sayfa yüklenme süresini hesapla
$start_time = microtime(true);

// Veritabanından aktif kullanıcı bilgilerini al
$stmt = $conn->prepare("SELECT * FROM admin_user WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header("Location: index.php");
    exit;
}

$end_time = microtime(true);
$execution_time = round($end_time - $start_time, 3);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <!-- SweetAlert2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold">Admin Paneli</h1>
                <p class="text-gray-600">Hoşgeldiniz, <?php echo htmlspecialchars($user['kullanici_adi']); ?>!</p>
            </div>
            <button onclick="showSettings()" class="bg-gray-200 p-2 rounded-full hover:bg-gray-300 transition duration-200">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            </button>
        </div>

<div id="dashboardRoot" class="mb-8"></div>

        <!-- Main Menu Cards -->
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
        </div>           

		<!-- Raporlar -->
            <div class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition duration-200">
                <h2 class="text-xl font-semibold mb-3">Raporlar</h2>
                <p class="text-gray-600 mb-4">Raporları buradan görüntüleyebilir ve yönetebilirsiniz.</p>
                <a href="reports.php" class="bg-indigo-500 text-white px-4 py-2 rounded hover:bg-indigo-600">Satış Raporları</a>
            </div>
        </div>
		
		

        <!-- Settings Modal -->
        <div id="settingsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium">Sistem Ayarları</h3>
            <button onclick="closeSettings()" class="text-gray-500 hover:text-gray-700">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <form id="settingsForm" class="space-y-4">
            <!-- Önce butonları ekleyelim -->
            <div class="flex gap-2 mb-4">
                <button type="button" onclick="addNewStore()" class="bg-blue-500 text-white px-3 py-1 rounded-md hover:bg-blue-600">
                    + Yeni Mağaza
                </button>
                <button type="button" onclick="addNewWarehouse()" class="bg-green-500 text-white px-3 py-1 rounded-md hover:bg-green-600">
                    + Yeni Depo
                </button>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Varsayılan Stok Lokasyonu
                </label>
                <div class="relative">
                    <select name="varsayilan_stok_lokasyonu" class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 rounded-md">
                        <optgroup label="Depolar">
                            <?php
                            // Depoları getir
                            $stmt = $conn->query("SELECT id, ad FROM depolar WHERE durum = 'aktif' ORDER BY ad");
                            while ($depo = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                echo sprintf(
                                    '<option value="depo_%d">%s</option>',
                                    $depo['id'],
                                    htmlspecialchars($depo['ad'])
                                );
                            }
                            ?>
                        </optgroup>
                        <optgroup label="Mağazalar">
                            <?php
                            // Mağazaları getir
                            $stmt = $conn->query("SELECT id, ad FROM magazalar ORDER BY ad");
                            while ($magaza = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                echo sprintf(
                                    '<option value="magaza_%d">%s</option>',
                                    $magaza['id'],
                                    htmlspecialchars($magaza['ad'])
                                );
                            }
                            ?>
                        </optgroup>
                    </select>
                </div>
                <p class="mt-2 text-sm text-gray-500">
                    Ürün girişlerinde varsayılan olarak hangi lokasyona stok eklenecek?
                </p>
            </div>

            <div class="flex justify-end pt-4">
                <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                    Kaydet
                </button>
            </div>
        </form>
    </div>
</div>

        <!-- Footer -->
        <footer class="mt-8 text-center text-gray-600 text-sm">
            <p>İstek <?php echo $execution_time; ?> saniyede tamamlandı.</p>
            <p>© 2024 İnciPos Admin Paneli</p>
        </footer>
    </div>

    <script>
    function showSettings() {
        document.getElementById('settingsModal').classList.remove('hidden');
    }

    function closeSettings() {
        document.getElementById('settingsModal').classList.add('hidden');
    }

    // Settings form submit
    document.getElementById('settingsForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const selectElement = this.querySelector('select[name="varsayilan_stok_lokasyonu"]');
        const selectedValue = selectElement.value;

        // Yeni mağaza/depo ekleme işlemleri
        if (selectedValue === 'add_store') {
            addNewStore();
            return;
        } else if (selectedValue === 'add_warehouse') {
            addNewWarehouse();
            return;
        }

        try {
            const response = await fetch('api/update_settings.php', {
                method: 'POST',
                body: new FormData(this)
            });

            const data = await response.json();

            if (data.success) {
                showToast('success', 'Ayarlar başarıyla kaydedildi');
                closeSettings();
            } else {
                throw new Error(data.message || 'Bir hata oluştu');
            }
        } catch (error) {
            showToast('error', error.message);
        }
    });

    function addNewStore() {
        Swal.fire({
            title: 'Yeni Mağaza Ekle',
            html: `
                <form id="addStoreForm" class="text-left">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Mağaza Adı*</label>
                        <input type="text" name="ad" class="mt-1 block w-full rounded-md border-gray-300" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Adres*</label>
                        <textarea name="adres" class="mt-1 block w-full rounded-md border-gray-300" required></textarea>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Telefon*</label>
                        <input type="tel" name="telefon" class="mt-1 block w-full rounded-md border-gray-300" required>
                    </div>
                </form>
            `,
            showCancelButton: true,
            confirmButtonText: 'Ekle',
            cancelButtonText: 'İptal',
            preConfirm: async () => {
                const form = document.getElementById('addStoreForm');
                try {
                    const response = await fetch('api/add_magaza.php', {
                        method: 'POST',
                        body: new FormData(form)
                    });
                    const data = await response.json();
                    if (!data.success) throw new Error(data.message);
                    return data;
                } catch (error) {
                    Swal.showValidationMessage(error.message);
                }
            }
        }).then((result) => {
            if (result.isConfirmed) {
                showToast('success', 'Mağaza başarıyla eklendi');
                location.reload(); // Sayfayı yenile
            }
        });
    }

    function addNewWarehouse() {
        Swal.fire({
            title: 'Yeni Depo Ekle',
            html: `
                <form id="addWarehouseForm" class="text-left">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Depo Adı*</label>
                        <input type="text" name="ad" class="mt-1 block w-full rounded-md border-gray-300" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Depo Kodu*</label>
                        <input type="text" name="kod" class="mt-1 block w-full rounded-md border-gray-300" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Adres</label>
                        <textarea name="adres" class="mt-1 block w-full rounded-md border-gray-300"></textarea>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Telefon</label>
                        <input type="tel" name="telefon" class="mt-1 block w-full rounded-md border-gray-300">
                    </div>
                </form>
            `,
            showCancelButton: true,
            confirmButtonText: 'Ekle',
            cancelButtonText: 'İptal',
            preConfirm: async () => {
                const form = document.getElementById('addWarehouseForm');
                try {
                    const response = await fetch('api/add_depo.php', {method: 'POST',
                        body: new FormData(form)
                    });
                    const data = await response.json();
                    if (!data.success) throw new Error(data.message);
                    return data;
                } catch (error) {
                    Swal.showValidationMessage(error.message);
                }
            }
        }).then((result) => {
            if (result.isConfirmed) {
                showToast('success', 'Depo başarıyla eklendi');
                location.reload(); // Sayfayı yenile
            }
        });
    }

    // Seçilen lokasyonu kaydet
    async function saveLocation() {
        try {
            const selectElement = document.querySelector('select[name="varsayilan_stok_lokasyonu"]');
            const selectedValue = selectElement.value;
            
            const response = await fetch('api/update_settings.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    varsayilan_stok_lokasyonu: selectedValue
                })
            });

            const data = await response.json();
            
            if (data.success) {
                showToast('success', 'Varsayılan lokasyon başarıyla güncellendi');
                closeSettings();
            } else {
                throw new Error(data.message || 'Ayarlar güncellenirken bir hata oluştu');
            }
        } catch (error) {
            showToast('error', error.message);
        }
    }

    // Modal dışına tıklamada kapatma
    document.getElementById('settingsModal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closeSettings();
        }
    });

    // ESC tuşu ile kapatma
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !document.getElementById('settingsModal').classList.contains('hidden')) {
            closeSettings();
        }
    });

    function showToast(icon, title) {
        Swal.fire({
            icon: icon,
            title: title,
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });
    }

    // Sayfa yüklendiğinde mevcut ayarları getir
    document.addEventListener('DOMContentLoaded', async function() {
        try {
            const response = await fetch('api/get_settings.php');
            const data = await response.json();
            
            if (data.success) {
                const selectElement = document.querySelector('select[name="varsayilan_stok_lokasyonu"]');
                if (selectElement && data.settings?.varsayilan_stok_lokasyonu) {
                    selectElement.value = data.settings.varsayilan_stok_lokasyonu;
                }
            }
        } catch (error) {
            console.error('Ayarlar yüklenirken hata:', error);
        }
    });
    </script>
	
<!-- Sayfanın en altına script'leri ekleyin -->
<script src="https://unpkg.com/react@18/umd/react.production.min.js"></script>
<script src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js"></script>

<script>
function DashboardSummary() {
  const [summaryData, setSummaryData] = React.useState({
    totalProducts: 0,
    totalValue: 0,
    lowStock: 0,
    dailyTotal: 0
  });

  React.useEffect(() => {
    fetchDashboardData();
    const interval = setInterval(fetchDashboardData, 300000);
    return () => clearInterval(interval);
  }, []);

  async function fetchDashboardData() {
    try {
      const response = await fetch('api/get_dashboard_summary.php');
      const data = await response.json();
      if (data.success) {
        setSummaryData(data.summary);
      }
    } catch (error) {
      console.error('Dashboard verisi alınırken hata:', error);
    }
  }

  return React.createElement('div', { className: 'space-y-6' }, [
    // Özet Kartlar
    React.createElement('div', { className: 'grid grid-cols-1 md:grid-cols-4 gap-4', key: 'summary-cards' }, [
      // Toplam Ürün Kartı
      React.createElement('div', { className: 'bg-white p-6 rounded-lg shadow hover:shadow-md transition-shadow', key: 'products' },
        React.createElement('div', { className: 'flex items-center justify-between' }, [
          React.createElement('div', { key: 'info' }, [
            React.createElement('p', { className: 'text-gray-500 text-sm', key: 'label' }, 'Toplam Ürün'),
            React.createElement('p', { className: 'text-2xl font-bold', key: 'value' }, summaryData.totalProducts)
          ])
        ])
      ),

      // Stok Değeri Kartı
      React.createElement('div', { className: 'bg-white p-6 rounded-lg shadow hover:shadow-md transition-shadow', key: 'value' },
        React.createElement('div', { className: 'flex items-center justify-between' }, [
          React.createElement('div', { key: 'info' }, [
            React.createElement('p', { className: 'text-gray-500 text-sm', key: 'label' }, 'Stok Değeri'),
            React.createElement('p', { className: 'text-2xl font-bold', key: 'value' }, 
              `₺${summaryData.totalValue.toLocaleString()}`)
          ])
        ])
      ),

      // Günlük Satış Kartı
      React.createElement('div', { className: 'bg-white p-6 rounded-lg shadow hover:shadow-md transition-shadow', key: 'daily-sales' },
        React.createElement('div', { className: 'flex items-center justify-between' }, [
          React.createElement('div', { key: 'info' }, [
            React.createElement('p', { className: 'text-gray-500 text-sm', key: 'label' }, 'Günlük Satış'),
            React.createElement('p', { className: 'text-2xl font-bold', key: 'value' }, 
              `₺${(summaryData.dailyTotal || 0).toLocaleString()}`)
          ])
        ])
      ),

      // Düşük Stok Kartı
      React.createElement('div', { className: 'bg-white p-6 rounded-lg shadow hover:shadow-md transition-shadow', key: 'low-stock' },
        React.createElement('div', { className: 'flex items-center justify-between' }, [
          React.createElement('div', { key: 'info' }, [
            React.createElement('p', { className: 'text-gray-500 text-sm', key: 'label' }, 'Düşük Stok'),
            React.createElement('p', { className: 'text-2xl font-bold', key: 'value' }, summaryData.lowStock)
          ])
        ])
      )
    ])
  ]);
}

// Render dashboard
const rootNode = document.getElementById('dashboardRoot');
const root = ReactDOM.createRoot(rootNode);
root.render(React.createElement(DashboardSummary));
</script>