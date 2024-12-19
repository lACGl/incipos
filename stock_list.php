<?php
session_start();
require_once 'db_connection.php';

// Kullanıcı giriş kontrolü
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: index.php");
    exit;
}

// Veritabanı sütunlarını al
$query = "SHOW COLUMNS FROM urun_stok";
$start_time = microtime(true);
$result = $conn->query($query);
$columns = [];

if ($result) {
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $columns[] = $row['Field'];
    }
}

$end_time = microtime(true);
$execution_time = round($end_time - $start_time, 3);

// Toplam ürün sayısını al
$total_products_query = "SELECT COUNT(*) AS total FROM urun_stok";
$total_products = $conn->query($total_products_query)->fetch(PDO::FETCH_ASSOC)['total'];
$count_query = "SELECT COUNT(*) as total FROM urun_stok";
$total_records = $conn->query($count_query)->fetch(PDO::FETCH_ASSOC)['total'];

// Varsayılan olarak seçili olmasını istediğimiz sütunlar
$default_columns = ['kod', 'barkod', 'ad', 'satis_fiyati', 'stok_miktari', 'durum'];

// Session'da saklanan sütunları kontrol et
if (!isset($_SESSION['selected_columns'])) {
    $_SESSION['selected_columns'] = $default_columns;
}

// POST ile gelen sütunları kontrol et ve session'ı güncelle
if (isset($_POST['columns'])) {
    $_SESSION['selected_columns'] = $_POST['columns'];
}

// Seçili sütunları session'dan al
$selected_columns = $_SESSION['selected_columns'];

// Items per page için de session kullan
if (isset($_POST['items_per_page'])) {
    $_SESSION['items_per_page'] = $_POST['items_per_page'];
}
$items_per_page = $_SESSION['items_per_page'] ?? 50;

// ID'yi her zaman ekle (eğer seçili değilse)
if (!in_array('id', $selected_columns)) {
    $selected_columns[] = 'id';
}

// Gelişmiş arama ve filtreleme parametrelerini al
$search_column = isset($_GET['search_column']) ? $_GET['search_column'] : '';
$search_term = isset($_GET['search_term']) ? $_GET['search_term'] : '';

// Toplam ürün sayısını hesapla
$total_products_query = "SELECT COUNT(*) AS total_products FROM urun_stok";
$params = [];

if (!empty($search_term)) {
    $total_products_query .= " WHERE barkod LIKE :search_term OR ad LIKE :search_term";
    $params[':search_term'] = '%' . $search_term . '%';
}

$stmt = $conn->prepare($total_products_query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, PDO::PARAM_STR);
}
$stmt->execute();
$total_products = $stmt->fetch(PDO::FETCH_ASSOC)['total_products'];

// Sayfalama için değişkenler
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$total_pages = ceil($total_products / $items_per_page);
$offset = ($current_page - 1) * $items_per_page;

// stock_list.php içinde createTable fonksiyonunu güncelle
function createTable($conn, $selected_columns, $items_per_page, $offset, $search_column, $search_term) {
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
        $where_conditions[] = "(us.barkod LIKE :search_term OR us.ad LIKE :search_term OR us.kod LIKE :search_term)";
        $params[':search_term'] = '%' . $search_term . '%';
    }

    // Departman filtresi
    if (isset($_POST['departman_id']) && !empty($_POST['departman_id'])) {
        $where_conditions[] = "us.departman_id = :departman_id";
        $params[':departman_id'] = $_POST['departman_id'];
    }

    // Ana Grup filtresi
    if (isset($_POST['ana_grup_id']) && !empty($_POST['ana_grup_id'])) {
        $where_conditions[] = "us.ana_grup_id = :ana_grup_id";
        $params[':ana_grup_id'] = $_POST['ana_grup_id'];
    }

    // Stok durumu filtresi
    if (isset($_POST['stock_status']) && !empty($_POST['stock_status'])) {
        switch ($_POST['stock_status']) {
            case 'out':
                $where_conditions[] = "us.stok_miktari = 0";
                break;
            case 'low':
                $where_conditions[] = "us.stok_miktari > 0 AND us.stok_miktari <= 10";
                break;
            case 'normal':
                $where_conditions[] = "us.stok_miktari > 10";
                break;
        }
    }

    // Son hareket tarihi filtresi
    if (isset($_POST['last_movement_date']) && !empty($_POST['last_movement_date'])) {
        $where_conditions[] = "us.id IN (
            SELECT urun_id 
            FROM stok_hareketleri 
            WHERE DATE(tarih) >= :last_movement_date
        )";
        $params[':last_movement_date'] = $_POST['last_movement_date'];
    }

    // WHERE clause'u birleştir
    $where_clause = !empty($where_conditions) ? " WHERE " . implode(' AND ', $where_conditions) : '';

    // Ana sorgu
    $query = "SELECT us.* FROM urun_stok us" . $where_clause;
    $query .= " ORDER BY us.id DESC LIMIT :limit OFFSET :offset";

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
               onclick="toggleAllCheckboxes(this)">
    </th>';

    foreach ($visible_columns as $column) {
        $table .= "<th class='px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider'>"
                 . htmlspecialchars($column) 
                 . "</th>";
    }
    $table .= "<th class='px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider'>Eylemler</th>";
    $table .= "</tr></thead>";
    
    $table .= "<tbody class='bg-white divide-y divide-gray-200'>";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $table .= "<tr class='product-row hover:bg-gray-50' data-product='" . htmlspecialchars(json_encode($row, JSON_HEX_APOS | JSON_HEX_QUOT)) . "'>";
        $table .= '<td class="w-4 px-6 py-4">
            <input type="checkbox" 
                   name="selected_products[]" 
                   value="'.$row['id'].'" 
                   class="form-checkbox h-4 w-4 text-blue-600 rounded border-gray-300">
        </td>';

        foreach ($visible_columns as $column) {
            $value = $row[$column];
            if ($column === 'satis_fiyati' || $column === 'indirimli_fiyat') {
                $value = '₺' . number_format($value, 2);
            } elseif ($column === 'stok_miktari') {
                $class = (int)$value < 10 ? 'text-red-600' : 'text-green-600';
                $value = "<span class='$class'>" . $value . "</span>";
            }
            $table .= "<td class='px-6 py-4 whitespace-nowrap'>" . $value . "</td>";
        }

        // Eylem butonları
        $table .= "<td class='px-6 py-4 whitespace-nowrap'>
            <div class='flex items-center space-x-2'>
                <button onclick='editProduct({$row['id']})' 
                        class='text-blue-600 hover:text-blue-800 tooltip'
                        data-tooltip='Düzenle'>
                    <svg class='w-5 h-5' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                        <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' 
                              d='M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z'/>
                    </svg>
                </button>

                <button onclick='addStock({$row['id']})' 
                        class='text-green-600 hover:text-green-800 tooltip'
                        data-tooltip='Stok Ekle/Çıkar'>
                    <svg class='w-5 h-5' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                        <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' 
                              d='M12 6v6m0 0v6m0-6h6m-6 0H6'/>
                    </svg>
                </button>

                <button onclick='showDetails({$row['id']})' 
                        class='text-gray-600 hover:text-gray-800 tooltip'
                        data-tooltip='Detayları Göster'>
                    <svg class='w-5 h-5' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                        <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' 
                              d='M15 12a3 3 0 11-6 0 3 3 0 016 0z'/>
                        <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' 
                              d='M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z'/>
                    </svg>
                </button>

                <button onclick='deleteProduct({$row['id']})' 
                        class='text-red-600 hover:text-red-800 tooltip'
                        data-tooltip='Sil'>
                    <svg class='w-5 h-5' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                        <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' 
                              d='M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16'/>
                    </svg>
                </button>

                <button onclick='showStockDetails({$row['id']})' 
                        class='text-purple-600 hover:text-purple-800 tooltip'
                        data-tooltip='Stok Detayları'>
                    <svg class='w-5 h-5' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                        <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' 
                              d='M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'/>
                    </svg>
                </button>
            </div>
        </td>";
        
        $table .= "</tr>";
    }
    $table .= "</tbody></table>";

    return $table;
}

$table_html = createTable($conn, $selected_columns, $items_per_page, $offset, $search_column, $search_term);
?>

<!-- Rest of the HTML remains the same -->
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Stock Management</title>    
    <link rel="icon" href="data:;base64,iVBORw0KGgo=">
    <link rel="stylesheet" href="/assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-minimal@5/minimal.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <form id="itemsForm">
            <div class="menu-wrapper">
                <div class="menu">
                    <button type="button" class="filters-button" id="toggleFiltersBtn">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                        </svg>
                        Filtreler
                    </button>
                    <button type="button" class="actions-button" onclick="toggleActions(event)">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12.1 2.7c0 0-2.9 0-4 1.1L3.7 8.2c-.8.8-.8 2 0 2.8l9.3 9.3c.8.8 2 .8 2.8 0l4.4-4.4c1.1-1.1 1.1-4 1.1-4l-9.2-9.2z"/>
                            <circle cx="15" cy="9" r="1"/>
                        </svg>
                        İşlemler
                        <div id="actionsMenu" class="actions-menu">
                            <a href="#" onclick="addProduct()">Ürün Ekle</a>
                            <a href="#" onclick="deleteSelected()">Seçili Ürünleri Sil</a>
                            <a href="#" onclick="deactivateSelected()">Seçili Ürünleri Pasife Al</a>
                            <a href="#" onclick="activateSelected()">Seçili Ürünleri Aktife Al</a>
                            <a href="#" onclick="exportSelected()">Seçili Ürünleri Dışarı Aktar</a>
                        </div>
                    </button>
                </div>

                <div id="filtersPanel" class="filters-panel">
                    <div class="filters-content">
                        <!-- Search Box -->
                        <div class="search-box relative">
                            <input type="text" 
                                   name="search_term" 
                                   placeholder="Barkod veya ürün adı ara..." 
                                   class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>

                        <div class="flex space-x-4 mb-4">
                            <!-- Stok Durumu Filtresi -->
                            <div class="w-1/3">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Stok Durumu</label>
                                <select name="stock_status" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200">
                                    <option value="" class="bg-white">Tümü</option>
                                    <option value="out" class="bg-red-100">Stok Yok</option>
                                    <option value="low" class="bg-yellow-100">Kritik Stok (10 ve altı)</option>
                                    <option value="normal" class="bg-blue-100">Normal Stok</option>
                                </select>
                            </div>

                            <!-- Son Hareket Tarihi Filtresi -->
                            <div class="w-1/3">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Son Güncelleme Tarihi</label>
                                <input type="date" 
                                       name="last_movement_date" 
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200">
                            </div>
                        </div>

                        <!-- Columns Selection -->
                        <div class="filters-title mt-4 mb-2">Gösterilecek Sütunlar</div>
                        <div class="flex flex-wrap gap-4">
                            <?php foreach ($columns as $column): ?>
                                <?php if ($column !== 'id'): ?>
                                    <label class="flex items-center gap-2 min-w-[150px] hover:bg-gray-100 p-2 rounded">
                                        <input type="checkbox" 
                                               id="col_<?php echo $column; ?>"
                                               name="columns[]" 
                                               value="<?php echo htmlspecialchars($column); ?>"
                                               class="form-checkbox h-4 w-4 text-blue-600 rounded border-gray-300"
                                               <?php echo in_array($column, $selected_columns) ? 'checked' : ''; ?>>
                                        <span class="text-sm text-gray-700">
                                            <?php echo htmlspecialchars($column); ?>
                                        </span>
                                    </label>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <div class="items-per-page-section mt-4">
            <h3>Gösterilecek Ürün Sayısı:</h3>
            <select name="items_per_page" id="itemsPerPage" onchange="updateItemsPerPage(this.value)" 
                    class="ml-2 rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200">
                <option value="5" <?php echo ($items_per_page == 5) ? 'selected' : ''; ?>>5</option>
                <option value="10" <?php echo ($items_per_page == 10) ? 'selected' : ''; ?>>10</option>
                <option value="20" <?php echo ($items_per_page == 20) ? 'selected' : ''; ?>>20</option>
                <option value="50" <?php echo ($items_per_page == 50) ? 'selected' : ''; ?>>50</option>
                <option value="100" <?php echo ($items_per_page == 100) ? 'selected' : ''; ?>>100</option>
                <option value="200" <?php echo ($items_per_page == 200) ? 'selected' : ''; ?>>200</option>
                <option value="500" <?php echo ($items_per_page == 500) ? 'selected' : ''; ?>>500</option>
                <option value="1000" <?php echo ($items_per_page == 1000) ? 'selected' : ''; ?>>1000</option>
            </select>
        </div>

        <h2 class="mt-4 mb-4">
            (<span id="total-products"><?php echo number_format($total_records, 0, ',', '.'); ?></span> ürün) 
            - İstek <?php echo $execution_time; ?> saniyede tamamlandı.
        </h2>

        <!-- Table Container -->
        <div id="tableContainer" class="overflow-x-auto">
            <?php echo $table_html; ?>
        </div>

        <!-- Pagination -->
        <div class="pagination mt-4">
            <?php if ($current_page > 1): ?>
                <a href="?page=1&search_term=<?php echo $search_term; ?>" class="page-first">İlk</a>
                <a href="?page=<?php echo ($current_page - 1); ?>&search_term=<?php echo $search_term; ?>" class="page-prev">Önceki</a>
            <?php endif; ?>

            <?php
            $maxPages = 3;
            $halfMax = floor($maxPages / 2);
            
            if ($total_pages <= $maxPages) {
                $start = 1;
                $end = $total_pages;
            } elseif ($current_page <= $halfMax) {
                $start = 1;
                $end = $maxPages;
            } elseif ($current_page > ($total_pages - $halfMax)) {
                $start = $total_pages - $maxPages + 1;
                $end = $total_pages;
            } else {
                $start = $current_page - $halfMax;
                $end = $current_page + $halfMax;
            }

            for ($i = $start; $i <= $end; $i++): ?>
                <a href="?page=<?php echo $i; ?>&search_term=<?php echo $search_term; ?>" 
                   class="<?php echo ($i == $current_page) ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>

            <?php if ($current_page < $total_pages): ?>
                <a href="?page=<?php echo ($current_page + 1); ?>&search_term=<?php echo $search_term; ?>" class="page-next">Sonraki</a>
                <a href="?page=<?php echo $total_pages; ?>&search_term=<?php echo $search_term; ?>" class="page-last">Son</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer -->
    <footer class="mt-8 text-center text-gray-600 text-sm">
        <p>© 2024 İnciPos Admin Paneli</p>
    </footer>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script type="module" src="/assets/js/utils.js"></script>
    <script type="module" src="/assets/js/stock_list.js"></script>
    <script type="module" src="/assets/js/stock_list_process.js"></script>
    <script type="module" src="/assets/js/stock_list_actions.js"></script>
    <script type="module" src="/assets/js/main.js"></script>
</body>
</html>