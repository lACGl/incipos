<?php
include 'header.php';
require_once 'db_connection.php';

// Kullanıcı giriş kontrolü
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: index.php");
    exit;
}

// Geliştirilmiş arama fonksiyonu - API'deki gibi kelime parçalama
function createSearchCondition($search_term, &$params) {
    // Boş arama terimi kontrolü
    if (empty($search_term)) {
        return "";
    }
    
    // Arama terimini temizle
    $search_term = trim($search_term);
    
    // Kelimelere ayır ve en az 2 karakterli olanları al
    $words = explode(' ', $search_term);
    $words = array_filter($words, function($word) {
        return strlen(trim($word)) >= 2;
    });
    
    // Kelime yoksa boş dön
    if (empty($words)) {
        return "";
    }
    
    // Her kelime için ayrı bir koşul oluştur
    $conditions = [];
    foreach ($words as $index => $word) {
        $word = trim($word);
        $param_name = ":word_$index";
        $params[$param_name] = '%' . $word . '%';
        
        // Her kelime barkod, ad veya kodda geçmeli
        $conditions[] = "(us.ad LIKE $param_name OR us.barkod LIKE $param_name OR us.kod LIKE $param_name)";
    }
    
    // Tüm kelimeler için AND ile bağla (her kelime en az bir sütunda geçmeli)
    return "(" . implode(' AND ', $conditions) . ")";
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

// Filtre parametrelerini al
$departman_id = isset($_POST['departman_id']) ? $_POST['departman_id'] : '';
$ana_grup_id = isset($_POST['ana_grup_id']) ? $_POST['ana_grup_id'] : '';
$stock_status = isset($_POST['stock_status']) ? $_POST['stock_status'] : '';
$last_movement_date = isset($_POST['last_movement_date']) ? $_POST['last_movement_date'] : '';

// Toplam ürün sayısını hesapla
$total_products_query = "SELECT COUNT(*) AS total_products FROM urun_stok us";
$params = [];

// Arama filtrelerini ekle
$search_condition = createSearchCondition($search_term, $params);
if (!empty($search_condition)) {
    $total_products_query .= " WHERE " . $search_condition;
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

// İstatistik kartları için sorgular
$total_query = "SELECT COUNT(*) as total FROM urun_stok";
$total_products = $conn->query($total_query)->fetch(PDO::FETCH_ASSOC)['total'];

$in_stock_query = "SELECT COUNT(*) as total FROM urun_stok WHERE stok_miktari > 0";
$in_stock_products = $conn->query($in_stock_query)->fetch(PDO::FETCH_ASSOC)['total'];

$critical_stock_query = "SELECT COUNT(*) as total FROM urun_stok WHERE stok_miktari > 0 AND stok_miktari <= 10";
$critical_stock = $conn->query($critical_stock_query)->fetch(PDO::FETCH_ASSOC)['total'];

$out_of_stock_query = "SELECT COUNT(*) as total FROM urun_stok WHERE stok_miktari = 0";
$out_of_stock = $conn->query($out_of_stock_query)->fetch(PDO::FETCH_ASSOC)['total'];

// Geliştirilmiş createTable fonksiyonu
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

    // Arama filtresini ekle (basitleştirilmiş)
    $search_condition = createSearchCondition($search_term, $params);
    if (!empty($search_condition)) {
        $where_conditions[] = $search_condition;
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
        $param_type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
        $stmt->bindValue($key, $value, $param_type);
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
    $rowCount = 0;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $rowCount++;
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

                <button onclick='showStockDetails({$row['id']})' 
                        class='text-purple-600 hover:text-purple-800 tooltip'
                        data-tooltip='Stok Detayları'>
                    <svg class='w-5 h-5' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                        <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' 
                              d='M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'/>
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
            </div>
        </td>";
        
        $table .= "</tr>";
    }
    
    if ($rowCount === 0) {
        $table .= "<tr><td colspan='" . (count($visible_columns) + 2) . "' class='text-center py-4'>Sonuç bulunamadı.</td></tr>";
    }
    
    $table .= "</tbody></table>";

    return $table;
}

$table_html = createTable($conn, $selected_columns, $items_per_page, $offset, $search_column, $search_term);

$end_time = microtime(true);
$execution_time = round($end_time - $start_time, 3);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Stock Management</title>    
    <link rel="icon" href="data:;base64,iVBORw0KGgo=">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-minimal@5/minimal.css" rel="stylesheet">
    <style>
    /* stock_list sayfasına ekleyebileceğiniz CSS */
    .sort-icon {
        display: inline-block;
        width: 16px;
        height: 16px;
        margin-left: 5px;
        transition: transform 0.2s ease;
    }

    th[class*="-header"] {
        cursor: pointer;
        user-select: none;
    }

    th[class*="-header"]:hover {
        background-color: rgba(0, 0, 0, 0.05);
    }
    
    /* Arama sonuçlarını vurgulama için stil */
    .search-highlight {
        background-color: #FFFF00;
        font-weight: bold;
    }
    </style>
</head>
<body>
    <div class="container">
    <form id="itemsForm" method="GET">
        <div class="menu-wrapper">
            <div class="menu">
                <!-- Top Menu Buttons -->
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
                        <a href="#" onclick="activateSelected()">Seçili Ürünleri Aktife Al</a>
                        <a href="#" onclick="deactivateSelected()">Seçili Ürünleri Pasife Al</a>
                        <a href="#" onclick="importProducts()">Excel'den İçe Aktar</a>
                        <a href="#" onclick="exportSelected()">Seçili Ürünleri Dışarı Aktar</a>      
                        <a href="extensions/discount/discounts.php" onclick="window.location.href='extensions/discount/discounts.php'; return false;">İndirim Yönetimi</a>
                    </div>
                </button>
            </div>

            <!-- Filters Panel -->
            <div id="filtersPanel" class="filters-panel">
                <div class="filters-content space-y-4">
                    <!-- Full Width Search Box in Its Own Row -->
                    <div class="w-full">
                        <input type="text" 
                               name="search_term" 
                               value="<?php echo htmlspecialchars($search_term); ?>"
                               placeholder="Barkod veya ürün adı ara... (Örn: adel fon)" 
                               class="w-full px-4 py-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <p class="text-sm text-gray-500 mt-1">Birden fazla kelime ile arama yapabilirsiniz (örn: "adel fon" veya "kırmızı kalem")</p>
                    </div>

                    <!-- Three Filters in One Row -->
                    <div class="flex gap-4">
                        <!-- Stok Durumu Filter -->
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Stok Durumu</label>
                            <select name="stock_status" class="w-full h-10 rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200">
                                <option value="" class="bg-white">Tümü</option>
                                <option value="out" class="bg-red-100" <?php echo ($stock_status == 'out') ? 'selected' : ''; ?>>Stok Yok</option>
                                <option value="low" class="bg-yellow-100" <?php echo ($stock_status == 'low') ? 'selected' : ''; ?>>Kritik Stok (10 ve altı)</option>
                                <option value="normal" class="bg-blue-100" <?php echo ($stock_status == 'normal') ? 'selected' : ''; ?>>Normal Stok</option>
                            </select>
                        </div>

                        <!-- Son Güncelleme Tarihi Filter -->
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Son Güncelleme Tarihi</label>
                            <input type="date" 
                                   name="last_movement_date" 
                                   value="<?php echo htmlspecialchars($last_movement_date); ?>"
                                   class="w-full h-10 rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200">
                        </div>

                        <!-- Gösterilecek Sütunlar Dropdown -->
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Gösterilecek Sütunlar</label>
                            <button type="button" 
                                    id="columnsButton" 
                                    onclick="toggleColumns()" 
                                    class="w-full h-10 flex justify-between items-center px-4 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-gray-100 focus:ring-blue-500">
                                <span>Sütunları Seç</span>
                                <svg class="w-5 h-5 ml-2 transform transition-transform" id="columnArrow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>

                            <!-- Columns Menu -->
                            <div id="columnsMenu" class="hidden absolute right-0 mt-2 w-[800px] bg-white shadow-xl rounded-lg ring-1 ring-black ring-opacity-5 z-50">
                                <div class="grid grid-cols-4 gap-6 p-6">
                                    <!-- Temel Bilgiler -->
                                    <div class="space-y-3">
                                        <h4 class="font-medium text-gray-900 text-lg">Temel Bilgiler</h4>
                                        <div class="space-y-3">
                                            <label class="flex items-center">
                                                <input type="checkbox" name="columns[]" value="kod" class="form-checkbox h-4 w-4 text-blue-600 rounded border-gray-300" <?php echo in_array('kod', $selected_columns) ? 'checked' : ''; ?>>
                                                <span class="ml-2 text-gray-700">Ürün Kodu</span>
                                            </label>
                                            <label class="flex items-center">
                                                <input type="checkbox" name="columns[]" value="barkod" class="form-checkbox h-4 w-4 text-blue-600 rounded border-gray-300" <?php echo in_array('barkod', $selected_columns) ? 'checked' : ''; ?>>
                                                <span class="ml-2 text-gray-700">Barkod</span>
                                            </label>
                                            <label class="flex items-center">
                                                <input type="checkbox" name="columns[]" value="ad" class="form-checkbox h-4 w-4 text-blue-600 rounded border-gray-300" <?php echo in_array('ad', $selected_columns) ? 'checked' : ''; ?>>
                                                <span class="ml-2 text-gray-700">Ürün Adı</span>
                                            </label>
                                            <label class="flex items-center">
                                                <input type="checkbox" name="columns[]" value="web_id" class="form-checkbox h-4 w-4 text-blue-600 rounded border-gray-300" <?php echo in_array('web_id', $selected_columns) ? 'checked' : ''; ?>>
                                                <span class="ml-2 text-gray-700">Web ID</span>
                                            </label>
                                        </div>
                                    </div>

                                    <!-- Fiyat Bilgileri -->
                                    <div class="space-y-3">
                                        <h4 class="font-medium text-gray-900 text-lg">Fiyat Bilgileri</h4>
                                        <div class="space-y-3">
                                            <label class="flex items-center">
                                                <input type="checkbox" name="columns[]" value="alis_fiyati" class="form-checkbox h-4 w-4 text-blue-600 rounded border-gray-300" <?php echo in_array('alis_fiyati', $selected_columns) ? 'checked' : ''; ?>>
                                                <span class="ml-2 text-gray-700">Alış Fiyatı</span>
                                            </label>
                                            <label class="flex items-center">
                                                <input type="checkbox" name="columns[]" value="satis_fiyati" class="form-checkbox h-4 w-4 text-blue-600 rounded border-gray-300" <?php echo in_array('satis_fiyati', $selected_columns) ? 'checked' : ''; ?>>
                                                <span class="ml-2 text-gray-700">Satış Fiyatı</span>
                                            </label>
                                            <label class="flex items-center">
                                                <input type="checkbox" name="columns[]" value="indirimli_fiyat" class="form-checkbox h-4 w-4 text-blue-600 rounded border-gray-300" <?php echo in_array('indirimli_fiyat', $selected_columns) ? 'checked' : ''; ?>>
                                                <span class="ml-2 text-gray-700">İndirimli Fiyat</span>
                                            </label>
                                            <label class="flex items-center">
                                                <input type="checkbox" name="columns[]" value="kdv_orani" class="form-checkbox h-4 w-4 text-blue-600 rounded border-gray-300" <?php echo in_array('kdv_orani', $selected_columns) ? 'checked' : ''; ?>>
                                                <span class="ml-2 text-gray-700">KDV Oranı</span>
                                            </label>
                                        </div>
                                    </div>

                                    <!-- Stok Bilgileri -->
                                    <div class="space-y-3">
                                        <h4 class="font-medium text-gray-900 text-lg">Stok Bilgileri</h4>
                                        <div class="space-y-3">
                                            <label class="flex items-center">
                                                <input type="checkbox" name="columns[]" value="stok_miktari" class="form-checkbox h-4 w-4 text-blue-600 rounded border-gray-300" <?php echo in_array('stok_miktari', $selected_columns) ? 'checked' : ''; ?>>
                                                <span class="ml-2 text-gray-700">Stok Miktarı</span>
                                            </label>
                                            <label class="flex items-center">
                                                <input type="checkbox" name="columns[]" value="kayit_tarihi" class="form-checkbox h-4 w-4 text-blue-600 rounded border-gray-300" <?php echo in_array('kayit_tarihi', $selected_columns) ? 'checked' : ''; ?>>
                                                <span class="ml-2 text-gray-700">Kayıt Tarihi</span>
                                            </label>
                                            <label class="flex items-center">
                                                <input type="checkbox" name="columns[]" value="durum" class="form-checkbox h-4 w-4 text-blue-600 rounded border-gray-300" <?php echo in_array('durum', $selected_columns) ? 'checked' : ''; ?>>
                                                <span class="ml-2 text-gray-700">Durum</span>
                                            </label>
                                            <label class="flex items-center">
                                                <input type="checkbox" name="columns[]" value="yil" class="form-checkbox h-4 w-4 text-blue-600 rounded border-gray-300" <?php echo in_array('yil', $selected_columns) ? 'checked' : ''; ?>>
                                                <span class="ml-2 text-gray-700">Yıl</span>
                                            </label>
                                        </div>
                                    </div>

                                    <!-- Kategori Bilgileri -->
                                    <div class="space-y-3">
                                        <h4 class="font-medium text-gray-900 text-lg">Kategori Bilgileri</h4>
                                        <div class="space-y-3">
                                            <label class="flex items-center">
                                                <input type="checkbox" name="columns[]" value="departman_id" class="form-checkbox h-4 w-4 text-blue-600 rounded border-gray-300" <?php echo in_array('departman_id', $selected_columns) ? 'checked' : ''; ?>>
                                                <span class="ml-2 text-gray-700">Departman</span>
                                            </label>
                                            <label class="flex items-center">
                                                <input type="checkbox" name="columns[]" value="birim_id" class="form-checkbox h-4 w-4 text-blue-600 rounded border-gray-300" <?php echo in_array('birim_id', $selected_columns) ? 'checked' : ''; ?>>
                                                <span class="ml-2 text-gray-700">Birim</span>
                                            </label>
                                            <label class="flex items-center">
                                                <input type="checkbox" name="columns[]" value="ana_grup_id" class="form-checkbox h-4 w-4 text-blue-600 rounded border-gray-300" <?php echo in_array('ana_grup_id', $selected_columns) ? 'checked' : ''; ?>>
                                                <span class="ml-2 text-gray-700">Ana Grup</span>
                                            </label>
                                            <label class="flex items-center">
                                                <input type="checkbox" name="columns[]" value="alt_grup_id" class="form-checkbox h-4 w-4 text-blue-600 rounded border-gray-300" <?php echo in_array('alt_grup_id', $selected_columns) ? 'checked' : ''; ?>>
                                                <span class="ml-2 text-gray-700">Alt Grup</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Filtreleme Butonları -->
                    <div class="flex gap-4">
                        <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline-block mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            Ara
                        </button>
                        
                        <button type="button" id="resetFiltersBtn" onclick="resetFilters()" class="px-4 py-2 bg-gray-200 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline-block mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            Filtreleri Sıfırla
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- İstatistik Kartları -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-6 mb-6">
                <!-- Toplam Ürün -->
                <div class="bg-blue-50 p-4 rounded-lg">
                    <h3 class="text-sm font-medium text-blue-800">Toplam Ürün</h3>
                    <p class="text-2xl font-bold text-blue-900" id="total-products-count"><?php echo number_format($total_products, 0, ',', '.'); ?></p>
                </div>

                <!-- Stoklu Ürün -->
                <div class="bg-green-50 p-4 rounded-lg">
                    <h3 class="text-sm font-medium text-green-800">Stoklu Ürün</h3>
                    <p class="text-2xl font-bold text-green-900" id="in-stock-count"><?php echo number_format($in_stock_products, 0, ',', '.'); ?></p>
                </div>

                <!-- Kritik Stok -->
                <div class="bg-yellow-50 p-4 rounded-lg">
                    <h3 class="text-sm font-medium text-yellow-800">Kritik Stok</h3>
                    <p class="text-2xl font-bold text-yellow-900" id="critical-stock-count"><?php echo number_format($critical_stock, 0, ',', '.'); ?></p>
                </div>

                <!-- Stoksuz Ürün -->
                <div class="bg-red-50 p-4 rounded-lg">
                    <h3 class="text-sm font-medium text-red-800">Stoksuz Ürün</h3>
                    <p class="text-2xl font-bold text-red-900" id="out-of-stock-count"><?php echo number_format($out_of_stock, 0, ',', '.'); ?></p>
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
            <option value="250" <?php echo ($items_per_page == 250) ? 'selected' : ''; ?>>250</option>
            <option value="500" <?php echo ($items_per_page == 500) ? 'selected' : ''; ?>>500</option>
        </select>
    </div>

    <h2 class="mt-4 mb-4">
        (<span id="total-products"><?php echo number_format($total_products, 0, ',', '.'); ?></span> ürün) 
        - İstek <?php echo $execution_time; ?> saniyede tamamlandı.
    </h2>

    <!-- Table Container -->
    <div id="tableContainer" class="overflow-x-auto">
        <?php echo $table_html; ?>
    </div>

    <!-- Pagination -->
    <div class="pagination mt-4">
        <?php if ($current_page > 1): ?>
            <a href="?page=1&search_term=<?php echo urlencode($search_term); ?>" class="page-first">İlk</a>
            <a href="?page=<?php echo ($current_page - 1); ?>&search_term=<?php echo urlencode($search_term); ?>" class="page-prev">Önceki</a>
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
            <a href="?page=<?php echo $i; ?>&search_term=<?php echo urlencode($search_term); ?>" 
               class="<?php echo ($i == $current_page) ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
        <?php endfor; ?>

        <?php if ($current_page < $total_pages): ?>
            <a href="?page=<?php echo ($current_page + 1); ?>&search_term=<?php echo urlencode($search_term); ?>" class="page-next">Sonraki</a>
            <a href="?page=<?php echo $total_pages; ?>&search_term=<?php echo urlencode($search_term); ?>" class="page-last">Son</a>
        <?php endif; ?>
    </div>
</div>

<script>
// Sütun menüsünü aç/kapat
function toggleColumns() {
    const menu = document.getElementById('columnsMenu');
    const arrow = document.getElementById('columnArrow');
    
    menu.classList.toggle('hidden');
    arrow.classList.toggle('rotate-180');
}

// Tüm onay kutularını işaretle/kaldır
function toggleAllCheckboxes(source) {
    const checkboxes = document.querySelectorAll('input[name="selected_products[]"]');
    for (let i = 0; i < checkboxes.length; i++) {
        checkboxes[i].checked = source.checked;
    }
}

// Filtreleri sıfırla
function resetFilters() {
    document.querySelector('input[name="search_term"]').value = '';
    document.querySelector('select[name="stock_status"]').value = '';
    document.querySelector('input[name="last_movement_date"]').value = '';
    
    // Form submit
    document.getElementById('itemsForm').submit();
}

// Sayfa başına gösterilecek ürün sayısını güncelle
function updateItemsPerPage(value) {
    // Form oluştur ve gönder
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = window.location.href;
    
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'items_per_page';
    input.value = value;
    
    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
}

// Arama alanına odaklanma
document.addEventListener('DOMContentLoaded', function() {
    // Eğer arama değeri varsa, arama alanına odaklan
    const searchInput = document.querySelector('input[name="search_term"]');
    if (searchInput && searchInput.value) {
        // Kelimeyi parçala ve arama sonuçlarını vurgula
        const searchWords = searchInput.value.toLowerCase().split(' ').filter(word => word.length > 1);
        
        if (searchWords.length > 0) {
            // Tablo içindeki tüm hücreleri kontrol et
            const cells = document.querySelectorAll('table td:not(:first-child):not(:last-child)');
            
            cells.forEach(cell => {
                const text = cell.innerText.toLowerCase();
                
                // Her kelime için kontrol et
                searchWords.forEach(word => {
                    if (text.includes(word)) {
                        // Metni kelimeye göre vurgula
                        const regex = new RegExp(word, 'gi');
                        cell.innerHTML = cell.innerHTML.replace(regex, match => `<span class="search-highlight">${match}</span>`);
                    }
                });
            });
        }
    }
});
</script>

<?php
// Sayfa yüklenme süresini hesapla
$end_time = microtime(true);
$execution_time = round($end_time - $start_time, 3);

// Sayfa özel scriptleri
$page_scripts = '
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script type="module" src="assets/js/utils.js"></script>
    <script type="module" src="assets/js/stock_list.js"></script>
    <script type="module" src="assets/js/stock_list_process.js"></script>
    <script type="module" src="assets/js/stock_list_actions.js"></script>
    <script type="module" src="assets/js/main.js"></script>
    <script type="module" src="assets/js/stock_list_import.js"></script>
';

// Footer'ı dahil et
include 'footer.php';
?>