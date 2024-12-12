<?php
session_start();
require_once 'db_connection.php';

// Kullanıcı giriş kontrolü
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
   header('Content-Type: application/json');
   echo json_encode(['error' => 'Unauthorized']);
   exit;
}

try {
   // POST verilerini al
   $selected_columns = isset($_POST['columns']) ? $_POST['columns'] : (isset($_SESSION['selected_columns']) ? $_SESSION['selected_columns'] : ['barkod', 'ad']);

   $items_per_page = isset($_SESSION['items_per_page']) ? (int)$_SESSION['items_per_page'] : 50;
   $sort_column = $_POST['sort_column'] ?? 'id';
   $sort_order = $_POST['sort_order'] ?? 'DESC';

   if (isset($_POST['items_per_page'])) {
       $items_per_page = (int)$_POST['items_per_page'];
       $_SESSION['items_per_page'] = $items_per_page;
   }

   $page = max(1, (int)($_POST['page'] ?? 1));
   $search_term = $_POST['search_term'] ?? '';

   // ID ve durum sütunlarını ekle
   if (!in_array('id', $selected_columns)) $selected_columns[] = 'id';
   if (!in_array('durum', $selected_columns)) $selected_columns[] = 'durum';

   // Görünür sütunları filtrele
   $visible_columns = array_filter($selected_columns, function($col) {
       return $col !== 'id' && $col !== 'durum';
   });

   // WHERE koşullarını oluştur
   $where_conditions = array();
   $params = array();

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

   // Arama filtresi
   if (!empty($search_term)) {
       $where_conditions[] = "(us.barkod LIKE :search_term OR us.ad LIKE :search_term)";
       $params[':search_term'] = '%' . $search_term . '%';
   }

   // WHERE clause'u birleştir
   $where_clause = '';
   if (!empty($where_conditions)) {
       $where_clause = " WHERE " . implode(' AND ', $where_conditions);
   }

   // Toplam kayıt sayısı
   $count_query = "SELECT COUNT(*) as total FROM urun_stok us" . $where_clause;
   $stmt = $conn->prepare($count_query);
   foreach ($params as $key => $value) {
       $stmt->bindValue($key, $value);
   }
   $stmt->execute();
   $total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

   // Sayfalama
   $total_pages = max(1, ceil($total_records / $items_per_page));
   $page = min($page, $total_pages);
   $offset = max(0, ($page - 1) * $items_per_page);

   // Sütunları hazırla
   $columns_str = implode(', ', array_map(function($col) {
       if (in_array($col, ['departman', 'ana_grup', 'alt_grup', 'birim'])) {
           return null;
       }
       return "us." . $col;
   }, array_filter($selected_columns)));

   // Ana sorgu
   $query = "SELECT 
       $columns_str,
       d.ad as departman,
       ag.ad as ana_grup,
       alg.ad as alt_grup,
       b.ad as birim
   FROM urun_stok us
   LEFT JOIN departmanlar d ON us.departman_id = d.id
   LEFT JOIN ana_gruplar ag ON us.ana_grup_id = ag.id
   LEFT JOIN alt_gruplar alg ON us.alt_grup_id = alg.id
   LEFT JOIN birimler b ON us.birim_id = b.id"
   . $where_clause;

   // Sıralama
   $query .= " ORDER BY 
       CASE us.durum 
           WHEN 'aktif' THEN 1 
           WHEN 'pasif' THEN 2 
           ELSE 3 
       END, 
       us." . $sort_column . " " . $sort_order;

   // Limit ve Offset
   $query .= " LIMIT :limit OFFSET :offset";

   // Debug için sorgu ve parametreleri logla
   error_log("SQL Query: " . $query);
   error_log("Parameters: " . print_r($params, true));

   $stmt = $conn->prepare($query);
   foreach ($params as $key => $value) {
       $stmt->bindValue($key, $value);
   }
   $stmt->bindValue(':limit', $items_per_page, PDO::PARAM_INT);
   $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
   $stmt->execute();
   $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
   
   // Tablo HTML hazırla
   $table_html = '<table class="table"><thead><tr>';
   $table_html .= '<th class="w-4">
       <input type="checkbox" id="selectAll" class="form-checkbox h-4 w-4 text-blue-600 rounded border-gray-300"
              onclick="toggleAllCheckboxes(this)">
   </th>';

   // Tablo başlıkları  
   foreach ($visible_columns as $column) {
       if ($column === 'stok_miktari') {
           $table_html .= '<th style="cursor:pointer;" class="stok-header" onclick="sortTableByStock()">' 
               . htmlspecialchars($column) 
               . ' <span class="sort-icon">↕</span></th>';
       }
       else if ($column === 'departman_id') {
           $table_html .= '<th style="cursor:pointer;" class="stok-header" onclick="filterByDepartment()">'
               . 'Departman'
               . ' <svg class="w-4 h-4 inline-block ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                   <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                 </svg></th>';
       }
	   else if ($column === 'ana_grup_id') {
    $table_html .= '<th style="cursor:pointer;" class="stok-header" onclick="filterByAnaGrup()">'
        . 'Ana Grup'
        . ' <svg class="w-4 h-4 inline-block ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
          </svg></th>';
}
       else {
           $table_html .= '<th>' . htmlspecialchars($column) . '</th>';
       }
   }
   
   $table_html .= '<th>Eylemler</th></tr></thead><tbody>';

   // Tablo içeriği
   if (count($rows) > 0) {
       foreach ($rows as $row) {
           $status = isset($row['durum']) ? $row['durum'] : null;
           $rowClass = 'product-row' . ($status === 'pasif' ? ' bg-gray-100' : '');
           
           $table_html .= "<tr class='{$rowClass}' data-status='{$status}' data-product='" . htmlspecialchars(json_encode($row)) . "'>";
           
           // Checkbox
           $table_html .= '<td class="w-4">
               <input type="checkbox" 
                      name="selected_products[]" 
                      value="'.$row['id'].'" 
                      onclick="event.stopPropagation()" 
                      class="form-checkbox h-4 w-4 text-blue-600 rounded border-gray-300">
           </td>';

           // Sütunlar
           foreach ($visible_columns as $column) {
               if ($column === 'ad') {
                   $value = htmlspecialchars($row[$column]);
                   if (isset($row['durum'])) {
                       $status_badge = $row['durum'] === 'pasif' ? 
                           '<span class="status-badge passive">Pasif</span>' : 
                           '<span class="status-badge active">Aktif</span>';
                       $value .= $status_badge;
                   }
               } elseif ($column === 'satis_fiyati' || $column === 'indirimli_fiyat' || $column === 'alis_fiyati') {
                   $value = '₺' . number_format($row[$column], 2);
               } elseif ($column === 'stok_miktari') {
                   $class = (int)$row[$column] < 10 ? 'text-red-600' : 'text-green-600';
                   $value = "<span class='$class'>" . $row[$column] . "</span>";
               } elseif (in_array($column, ['departman', 'ana_grup', 'alt_grup', 'birim'])) {
                   $value = htmlspecialchars($row[$column] ?? '-');
               } elseif ($column === 'departman_id') {
                   $value = htmlspecialchars($row['departman'] ?? '-');
               } elseif ($column === 'birim_id') {
                   $value = htmlspecialchars($row['birim'] ?? '-');
               } elseif ($column === 'ana_grup_id') {
                   $value = htmlspecialchars($row['ana_grup'] ?? '-');
               } elseif ($column === 'alt_grup_id') {
                   $value = htmlspecialchars($row['alt_grup'] ?? '-');
               } else {
                   $value = htmlspecialchars($row[$column] ?? '');
               }
               $table_html .= '<td>' . $value . '</td>';
           }

           // Eylem butonları
           $table_html .= '<td class="px-6 py-4 whitespace-nowrap">
               <div class="flex items-center space-x-2">
                   <button class="edit-btn text-blue-600 hover:text-blue-800 tooltip"
                           data-id="'.$row['id'].'"
                           data-tooltip="Düzenle">
                       <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                           <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                 d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                       </svg>
                   </button>

                   <button class="stock-btn text-green-600 hover:text-green-800 tooltip"
                           data-id="'.$row['id'].'"
                           data-tooltip="Stok Ekle/Çıkar">
                       <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                           <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                 d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                       </svg>
                   </button>

                   <button class="details-btn text-gray-600 hover:text-gray-800 tooltip"
                           data-id="'.$row['id'].'"
                           data-tooltip="Detayları Göster">
                       <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                           <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                 d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                           <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                 d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                       </svg>
                   </button>

                   <button class="delete-btn text-red-600 hover:text-red-800 tooltip"
                           data-id="'.$row['id'].'"
                           data-tooltip="Sil">
                       <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                           <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                 d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                       </svg>
                   </button>

                   <button class="stock-details-btn text-purple-600 hover:text-purple-800 tooltip"
                           data-id="'.$row['id'].'"
                           data-tooltip="Stok Detayları">
                       <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                           <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                 d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                       </svg>
                   </button>

                   <button class="price-history-btn text-indigo-600 hover:text-indigo-800 tooltip"
                           data-id="'.$row['id'].'"
                           data-tooltip="Fiyat Detayları">
                       <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                           <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                 d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                       </svg>
                   </button>

                   <button class="transfer-btn text-yellow-600 hover:text-yellow-800 tooltip"
                           data-id="'.$row['id'].'"
                           data-tooltip="Transfer">
                       <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                           <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                 d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                       </svg>
                   </button>
               </div>
           </td>';
           
           $table_html .= '</tr>';
       }
   } else {
       $table_html .= '<tr><td colspan="' . (count($visible_columns) + 2) . '">Kayıt bulunamadı.</td></tr>';
   }
   
   $table_html .= '</tbody></table>';

   // Sayfalama HTML
   $pagination_html = '';
   if ($total_pages > 1) {
       $pagination_html = getPaginationHtml($page, $total_pages);
   }

   // JSON yanıtı
   echo json_encode([
       'success' => true,
       'table' => $table_html,
       'pagination' => $pagination_html,
       'total_records' => $total_records,
       'current_page' => $page,
       'total_pages' => $total_pages,
       'total_products' => number_format($total_records, 0, ',', '.') 
   ]);

} catch (PDOException $e) {
   error_log('PDO Hatası: ' . $e->getMessage());
   echo json_encode([
       'success' => false,
       'message' => 'Veritabanı hatası oluştu',
       'debug' => [
           'sql_error' => $e->getMessage(),
           'query' => $query ?? null,
           'params' => [
               'limit' => $items_per_page ?? null,
               'offset' => $offset ?? null
           ]
       ]
   ]);
}

function getActionButtons($id) {
    return '<div class="flex items-center space-x-2">
        <div class="flex items-center space-x-2">
        <button class="edit-btn text-blue-600 hover:text-blue-800 tooltip"
                data-id="'.$id.'"
                data-tooltip="Düzenle">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                      d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
            </svg>
        </button>

        <button class="stock-btn text-green-600 hover:text-green-800 tooltip"
                data-id="'.$id.'"
                data-tooltip="Stok Ekle/Çıkar">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                      d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
            </svg>
        </button>

        <button class="details-btn text-gray-600 hover:text-gray-800 tooltip"
                data-id="'.$id.'"
                data-tooltip="Detayları Göster">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                      d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
            </svg>
        </button>

        <button class="delete-btn text-red-600 hover:text-red-800 tooltip"
                data-id="'.$id.'"
                data-tooltip="Sil">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
            </svg>
        </button>

        <button class="stock-details-btn text-purple-600 hover:text-purple-800 tooltip"
                data-id="'.$id.'"
                data-tooltip="Stok Detayları">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                      d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
        </button>

        <button class="price-history-btn text-indigo-600 hover:text-indigo-800 tooltip"
                data-id="'.$id.'"
                data-tooltip="Fiyat Detayları">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                      d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </button>

        <button class="transfer-btn text-yellow-600 hover:text-yellow-800 tooltip"
                data-id="'.$id.'"
                data-tooltip="Transfer">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                      d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
            </svg>
        </button>
    </div>';
}

function getPaginationHtml($page, $total_pages) {
    $html = '<div class="pagination">';
    if ($page > 1) {
        $html .= "<a href='#' data-page='1'>&laquo; İlk</a>";
        $html .= "<a href='#' data-page='" . ($page - 1) . "'>&lsaquo; Önceki</a>";
    }
    for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++) {
        $active_class = ($i == $page) ? 'active' : '';
        $html .= "<a href='#' data-page='$i' class='$active_class'>$i</a>";
    }
    if ($page < $total_pages) {
        $html .= "<a href='#' data-page='" . ($page + 1) . "'>Sonraki &rsaquo;</a>";
        $html .= "<a href='#' data-page='$total_pages'>Son &raquo;</a>";
    }
    $html .= '</div>';
    return $html;
}
?>