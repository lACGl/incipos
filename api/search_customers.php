<?php
session_start();
require_once '../db_connection.php';

// Yetki kontrolü
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

// Arama ve filtreleme parametrelerini al
$search_term = isset($_GET['search_term']) ? trim($_GET['search_term']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Varsayılan olarak gösterilecek sütunlar
$default_columns = ['barkod', 'ad', 'soyad', 'telefon', 'email', 'kayit_tarihi', 'durum'];

// Session'da saklanan sütunları kontrol et
if (isset($_SESSION['customer_selected_columns'])) {
    $selected_columns = $_SESSION['customer_selected_columns'];
} else {
    $selected_columns = $default_columns;
}

// ID'yi her zaman ekle (eğer seçili değilse)
if (!in_array('id', $selected_columns)) {
    $selected_columns[] = 'id';
}

// WHERE koşullarını oluştur
$where_conditions = [];
$params = [];

// Arama terimi kontrolü
if (!empty($search_term)) {
    $where_conditions[] = "(m.barkod LIKE :search_term OR m.ad LIKE :search_term OR m.soyad LIKE :search_term OR m.telefon LIKE :search_term)";
    $params[':search_term'] = '%' . $search_term . '%';
}

// Durum filtresi
if (!empty($status)) {
    $where_conditions[] = "m.durum = :status";
    $params[':status'] = $status;
}

// WHERE clause'u birleştir
$where_clause = !empty($where_conditions) ? " WHERE " . implode(' AND ', $where_conditions) : '';

// Toplam kayıt sayısını hesapla
$count_query = "SELECT COUNT(*) as total FROM musteriler m $where_clause";
$count_stmt = $conn->prepare($count_query);
foreach ($params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}
$count_stmt->execute();
$total_customers = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_customers / $limit);

// Görüntülenen sütunları oluştur - sadece id dışındaki sütunlar görüntülenecek
$visible_columns = array_filter($selected_columns, function($col) {
    return $col !== 'id';
});

// SQL için sütunları oluştur
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
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}

$stmt->execute();

// API çağrısı mı yoksa doğrudan HTML mi istendiğini kontrol et
// Content-Type header kontrol edilebilir
$is_api_call = isset($_GET['format']) && $_GET['format'] === 'json';

if ($is_api_call) {
    // JSON formatında sonuçları döndür
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'customers' => $customers,
        'total' => $total_customers,
        'pages' => $total_pages,
        'current_page' => $page
    ]);
    exit;
}

// HTML tablosu oluştur
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

// Sonuç var mı kontrol et
if ($stmt->rowCount() > 0) {
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

                <button onclick='viewCustomerCredits({$row['id']})' 
                        class='text-purple-600 hover:text-purple-800 tooltip'
                        data-tooltip='Borç Yönetimi'>
                    <svg class='w-5 h-5' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                        <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' 
                              d='M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z'/>
                    </svg>
                </button>

                <button onclick='showCustomerHistory({$row['id']})' 
                        class='text-indigo-600 hover:text-indigo-800 tooltip'
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
} else {
    // Sonuç yoksa bilgi mesajı
    $table .= "<tr><td colspan='" . (count($visible_columns) + 3) . "' class='px-6 py-4 text-center text-gray-500'>Arama kriterlerine uygun müşteri bulunamadı.</td></tr>";
}

$table .= "</tbody></table>";

// Sayfalama HTML'i oluştur
$pagination = '';
if ($total_pages > 1) {
    $pagination .= '<div class="flex justify-between items-center mt-4">';
    $pagination .= '<div class="text-gray-600">Toplam ' . number_format($total_customers, 0, ',', '.') . ' müşteri</div>';
    $pagination .= '<div class="flex space-x-1">';
    
    // İlk ve Önceki sayfalar
    if($page > 1) {
        $pagination .= '<a href="?page=1&search_term=' . urlencode($search_term) . '&status=' . urlencode($status) . '" class="px-3 py-1 bg-gray-200 text-gray-600 rounded hover:bg-gray-300">İlk</a>';
        $pagination .= '<a href="?page=' . ($page-1) . '&search_term=' . urlencode($search_term) . '&status=' . urlencode($status) . '" class="px-3 py-1 bg-gray-200 text-gray-600 rounded hover:bg-gray-300">Önceki</a>';
    }
    
    // Sayfa numaraları
    $start_page = max(1, $page - 2);
    $end_page = min($total_pages, $page + 2);
    
    for($i = $start_page; $i <= $end_page; $i++) {
        $active_class = ($i == $page) ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-600 hover:bg-gray-300';
        $pagination .= '<a href="?page=' . $i . '&search_term=' . urlencode($search_term) . '&status=' . urlencode($status) . '" class="px-3 py-1 ' . $active_class . ' rounded">' . $i . '</a>';
    }
    
    // Sonraki ve Son sayfalar
    if($page < $total_pages) {
        $pagination .= '<a href="?page=' . ($page+1) . '&search_term=' . urlencode($search_term) . '&status=' . urlencode($status) . '" class="px-3 py-1 bg-gray-200 text-gray-600 rounded hover:bg-gray-300">Sonraki</a>';
        $pagination .= '<a href="?page=' . $total_pages . '&search_term=' . urlencode($search_term) . '&status=' . urlencode($status) . '" class="px-3 py-1 bg-gray-200 text-gray-600 rounded hover:bg-gray-300">Son</a>';
    }
    
    $pagination .= '</div>';
    $pagination .= '</div>';
}

// Hem tablo hem sayfalamayı döndür
echo $table . $pagination;