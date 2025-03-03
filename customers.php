<?php
// Sayfa yüklenme süresini hesapla
$start_time = microtime(true);

// Header'ı dahil et
include 'header.php';
require_once 'db_connection.php';

// Kullanıcı giriş kontrolü
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: index.php");
    exit;
}

// Toplam müşteri sayısını al
$total_query = "SELECT COUNT(*) as total FROM musteriler";
$total_customers = $conn->query($total_query)->fetch(PDO::FETCH_ASSOC)['total'];

// Aktif müşteri sayısını al
$active_query = "SELECT COUNT(*) as total FROM musteriler WHERE durum = 'aktif'";
$active_customers = $conn->query($active_query)->fetch(PDO::FETCH_ASSOC)['total'];

// Pasif müşteri sayısını al
$inactive_query = "SELECT COUNT(*) as total FROM musteriler WHERE durum = 'pasif'";
$inactive_customers = $conn->query($inactive_query)->fetch(PDO::FETCH_ASSOC)['total'];

// Son 30 günde eklenen müşteri sayısını al
$new_customers_query = "SELECT COUNT(*) as total FROM musteriler WHERE kayit_tarihi >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
$new_customers = $conn->query($new_customers_query)->fetch(PDO::FETCH_ASSOC)['total'];

// Varsayılan olarak seçili olmasını istediğimiz sütunlar
$default_columns = ['barkod', 'ad', 'soyad', 'telefon', 'email', 'kayit_tarihi', 'durum'];

// Session'da saklanan sütunları kontrol et
if (!isset($_SESSION['customer_selected_columns'])) {
    $_SESSION['customer_selected_columns'] = $default_columns;
}

// POST ile gelen sütunları kontrol et ve session'ı güncelle
if (isset($_POST['columns'])) {
    $_SESSION['customer_selected_columns'] = $_POST['columns'];
}

// Seçili sütunları session'dan al
$selected_columns = $_SESSION['customer_selected_columns'];

// Items per page için de session kullan
if (isset($_POST['items_per_page'])) {
    $_SESSION['customer_items_per_page'] = $_POST['items_per_page'];
}
$items_per_page = $_SESSION['customer_items_per_page'] ?? 50;

// ID'yi her zaman ekle (eğer seçili değilse)
if (!in_array('id', $selected_columns)) {
    $selected_columns[] = 'id';
}

// Gelişmiş arama ve filtreleme parametrelerini al
$search_column = isset($_GET['search_column']) ? $_GET['search_column'] : '';
$search_term = isset($_GET['search_term']) ? $_GET['search_term'] : '';

// Toplam ürün sayısını hesapla
$total_customers_query = "SELECT COUNT(*) AS total_customers FROM musteriler";
$params = [];

if (!empty($search_term)) {
    $total_customers_query .= " WHERE barkod LIKE :search_term OR ad LIKE :search_term OR soyad LIKE :search_term OR telefon LIKE :search_term";
    $params[':search_term'] = '%' . $search_term . '%';
}

$stmt = $conn->prepare($total_customers_query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, PDO::PARAM_STR);
}
$stmt->execute();
$total_customers = $stmt->fetch(PDO::FETCH_ASSOC)['total_customers'];

// Sayfalama için değişkenler
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$total_pages = ceil($total_customers / $items_per_page);
$offset = ($current_page - 1) * $items_per_page;

// createTable fonksiyonu
function createCustomerTable($conn, $selected_columns, $items_per_page, $offset, $search_column, $search_term) {
    if (empty($selected_columns)) {
        return "<p>Gösterilecek sütun seçilmedi.</p>";
    }

    $visible_columns = array_filter($selected_columns, function($col) {
        return $col !== 'id';
    });

    // WHERE koşullarını oluştur
    $where_conditions = [];
    $params = [];

    // Arama filtresi
    if (!empty($search_term)) {
        $where_conditions[] = "(m.barkod LIKE :search_term OR m.ad LIKE :search_term OR m.soyad LIKE :search_term OR m.telefon LIKE :search_term)";
        $params[':search_term'] = '%' . $search_term . '%';
    }

    // WHERE clause'u birleştir
    $where_clause = !empty($where_conditions) ? " WHERE " . implode(' AND ', $where_conditions) : '';

    // Sütunları al
    $columns_str = implode(', ', array_map(function($col) {
        return "m.$col";
    }, $selected_columns));

    // Puanları da getir
    $columns_str .= ", mp.puan_bakiye, mp.puan_oran, mp.musteri_turu";

    // Ana sorgu
    $query = "
        SELECT $columns_str
        FROM musteriler m
        LEFT JOIN musteri_puanlar mp ON m.id = mp.musteri_id
        $where_clause
        ORDER BY m.id DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $conn->prepare($query);
    $stmt->bindValue(':limit', (int)$items_per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    
    $stmt->execute();

    // Tablo HTML'ini oluştur
    $table = "<table class='min-w-full divide-y divide-gray-200'>";
    $table .= "<thead class='bg-gray-50'><tr>";
    $table .= '<th class="w-4">
        <input type="checkbox" 
               id="selectAll" 
               class="form-checkbox h-4 w-4 text-blue-600 rounded border-gray-300"
               onclick="toggleAllCustomers(this)">
    </th>';

    foreach ($visible_columns as $column) {
        $table .= "<th class='px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider'>"
                 . ucfirst(str_replace('_', ' ', $column)) 
                 . "</th>";
    }
    
    // Puan sütununu ekle
    $table .= "<th class='px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider'>Puan Bakiye</th>";
    $table .= "<th class='px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider'>Eylemler</th>";
    $table .= "</tr></thead>";
    
    $table .= "<tbody class='bg-white divide-y divide-gray-200'>";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $table .= "<tr class='customer-row hover:bg-gray-50' data-customer='" . htmlspecialchars(json_encode($row)) . "'>";
        $table .= '<td class="w-4 px-6 py-4">
            <input type="checkbox" 
                   name="selected_customers[]" 
                   value="'.$row['id'].'" 
                   class="form-checkbox h-4 w-4 text-blue-600 rounded border-gray-300">
        </td>';

        foreach ($visible_columns as $column) {
            $value = $row[$column] ?? '';
            
            if ($column === 'durum') {
                $statusClass = $value === 'aktif' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
                $value = '<span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full '.$statusClass.'">'.$value.'</span>';
            } elseif ($column === 'kayit_tarihi' && !empty($value)) {
                $value = date('d.m.Y H:i', strtotime($value));
            }
            
            $table .= "<td class='px-6 py-4 whitespace-nowrap'>" . $value . "</td>";
        }

        // Puan bakiyesi
        $table .= "<td class='px-6 py-4 whitespace-nowrap'>" . 
                 '<span class="text-green-600 font-semibold">' . 
                 number_format($row['puan_bakiye'] ?? 0, 2, ',', '.') . 
                 ' Puan</span>' .
                 "</td>";

        // Eylem butonları
        $table .= "<td class='px-6 py-4 whitespace-nowrap'>
            <div class='flex items-center space-x-2'>
                <button onclick='editCustomer({$row['id']})' 
                        class='text-blue-600 hover:text-blue-800 tooltip'
                        data-tooltip='Düzenle'>
                    <svg class='w-5 h-5' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                        <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' 
                              d='M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z'/>
                    </svg>
                </button>

                <button onclick='viewCustomerPoints({$row['id']})' 
                        class='text-green-600 hover:text-green-800 tooltip'
                        data-tooltip='Puan İşlemleri'>
                    <svg class='w-5 h-5' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                        <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' 
                              d='M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'/>
                    </svg>
                </button>

                <button onclick='showCustomerHistory({$row['id']})' 
                        class='text-purple-600 hover:text-purple-800 tooltip'
                        data-tooltip='İşlem Geçmişi'>
                    <svg class='w-5 h-5' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                        <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' 
                              d='M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'/>
                    </svg>
                </button>

                <button onclick='deleteCustomer({$row['id']})' 
                        class='text-red-600 hover:text-red-800 tooltip'
                        data-tooltip='Sil'>
                    <svg class='w-5 h-5' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                        <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' 
                              d='M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16'/>
                    </svg>
                </button>
            </div>
        </td>";
        
        $table .= "</tr>";
    }
    $table .= "</tbody></table>";

    return $table;
}

$table_html = createCustomerTable($conn, $selected_columns, $items_per_page, $offset, $search_column, $search_term);

// Sayfa yüklenme süresini hesapla
$end_time = microtime(true);
$execution_time = round($end_time - $start_time, 3);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Müşteri Yönetimi</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
</head>
<body>
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-2xl font-bold mb-6">Müşteri Yönetimi</h1>

        <!-- İstatistik Kartları -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <!-- Toplam Müşteri -->
            <div class="bg-blue-50 p-4 rounded-lg">
                <h3 class="text-sm font-medium text-blue-800">Toplam Müşteri</h3>
                <p class="text-2xl font-bold text-blue-900"><?php echo number_format($total_customers, 0, ',', '.'); ?></p>
            </div>

            <!-- Aktif Müşteri -->
            <div class="bg-green-50 p-4 rounded-lg">
                <h3 class="text-sm font-medium text-green-800">Aktif Müşteri</h3>
                <p class="text-2xl font-bold text-green-900"><?php echo number_format($active_customers, 0, ',', '.'); ?></p>
            </div>

            <!-- Pasif Müşteri -->
            <div class="bg-red-50 p-4 rounded-lg">
                <h3 class="text-sm font-medium text-red-800">Pasif Müşteri</h3>
                <p class="text-2xl font-bold text-red-900"><?php echo number_format($inactive_customers, 0, ',', '.'); ?></p>
            </div>

            <!-- Yeni Müşteri (Son 30 Gün) -->
            <div class="bg-yellow-50 p-4 rounded-lg">
                <h3 class="text-sm font-medium text-yellow-800">Yeni Müşteri (Son 30 Gün)</h3>
                <p class="text-2xl font-bold text-yellow-900"><?php echo number_format($new_customers, 0, ',', '.'); ?></p>
            </div>
        </div>

        <!-- Filtreleme ve Arama -->
        <div class="bg-white p-4 rounded-lg shadow mb-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-semibold">Müşteri Listesi</h2>
                <button id="addCustomerBtn" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded">
                    <svg class="w-5 h-5 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Yeni Müşteri Ekle
                </button>
            </div>

            <div class="flex flex-col md:flex-row gap-4">
                <div class="md:w-2/3">
                    <input type="text" id="customerSearch" placeholder="Müşteri adı, soyadı veya telefon numarası ile ara..." 
                           class="w-full px-4 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div class="md:w-1/3 flex gap-2">
                    <select id="customerStatus" class="w-full px-4 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Tüm Durumlar</option>
                        <option value="aktif">Aktif</option>
                        <option value="pasif">Pasif</option>
                    </select>
                    
                    <button id="filterBtn" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">Filtrele</button>
                </div>
            </div>
        </div>

        <!-- Müşteri Tablosu -->
        <div id="customerTableContainer" class="bg-white rounded-lg shadow overflow-hidden mb-6">
            <?php echo $table_html; ?>
        </div>

        <!-- Sayfalama -->
        <div class="flex justify-between items-center">
            <div class="text-gray-600">
                Toplam <?php echo number_format($total_customers, 0, ',', '.'); ?> müşteri
            </div>
            <div class="flex space-x-1">
                <?php if($current_page > 1): ?>
                    <a href="?page=1" class="px-3 py-1 bg-gray-200 text-gray-600 rounded hover:bg-gray-300">İlk</a>
                    <a href="?page=<?php echo $current_page-1; ?>" class="px-3 py-1 bg-gray-200 text-gray-600 rounded hover:bg-gray-300">Önceki</a>
                <?php endif; ?>
                
                <?php
                $start_page = max(1, $current_page - 2);
                $end_page = min($total_pages, $current_page + 2);
                
                for($i = $start_page; $i <= $end_page; $i++):
                    $active_class = ($i == $current_page) ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-600 hover:bg-gray-300';
                ?>
                    <a href="?page=<?php echo $i; ?>" class="px-3 py-1 <?php echo $active_class; ?> rounded"><?php echo $i; ?></a>
                <?php endfor; ?>
                
                <?php if($current_page < $total_pages): ?>
                    <a href="?page=<?php echo $current_page+1; ?>" class="px-3 py-1 bg-gray-200 text-gray-600 rounded hover:bg-gray-300">Sonraki</a>
                    <a href="?page=<?php echo $total_pages; ?>" class="px-3 py-1 bg-gray-200 text-gray-600 rounded hover:bg-gray-300">Son</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="/assets/js/customer-management.js"></script>
    
    <?php
    // Sayfa özel scriptleri
    $page_scripts = '
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script src="/assets/js/customer-management.js"></script>
    ';

    // Footer'ı dahil et
    include 'footer.php';
    ?>
</body>
</html>