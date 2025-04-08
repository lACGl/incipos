<?php
session_start();
require_once 'db_connection.php';

// Yetki kontrolü
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    // Sütun ayarları
    $selected_columns = $_POST['columns'] ?? $_SESSION['selected_columns'] ?? ['barkod', 'ad'];
    $_SESSION['selected_columns'] = $selected_columns;

    // Temel sütunları ekle
    if (!in_array('id', $selected_columns)) $selected_columns[] = 'id';
    if (!in_array('durum', $selected_columns)) $selected_columns[] = 'durum';

    // Görünür sütunları filtrele
     $visible_columns = array_filter($selected_columns, fn($col) => !in_array($col, ['id', 'durum']));

    // Sayfalama ayarları
    $items_per_page = (int)($_POST['items_per_page'] ?? $_SESSION['items_per_page'] ?? 50);
    $_SESSION['items_per_page'] = $items_per_page;
    $page = max(1, (int)($_POST['page'] ?? 1));

    // Sıralama ayarları
    $sort_column = $_POST['sort_column'] ?? 'id';
    $sort_order = $_POST['sort_order'] ?? 'DESC';

    // WHERE koşulları ve parametreler
    [$where_clause, $params] = buildWhereClause($_POST);

    // Toplam kayıt sayısı
    $total_records = getTotalRecords($conn, $where_clause, $params);
    
    // Sayfalama hesaplamaları
    $total_pages = max(1, ceil($total_records / $items_per_page));
    $page = min($page, $total_pages);
    $offset = ($page - 1) * $items_per_page;

    // Ana sorgu için sütunları hazırla
    $columns_str = prepareColumns($selected_columns);

    // Ana sorgu
    $query = buildMainQuery($columns_str, $where_clause, $sort_column, $sort_order);
    $query .= " LIMIT :limit OFFSET :offset";

    // Sorguyu çalıştır
    $rows = executeQuery($conn, $query, array_merge($params, [
        ':limit' => $items_per_page,
        ':offset' => $offset
    ]));

    // HTML çıktısını oluştur
    $table_html = generateTableHtml($rows, $visible_columns);
    $pagination_html = $total_pages > 1 ? generatePaginationHtml($page, $total_pages) : '';

    // Yanıt
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
    logError('PDO Hatası: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Veritabanı hatası oluştu'
    ]);
}

// Yardımcı fonksiyonlar
function buildWhereClause($data) {
    $conditions = [];
    $params = [];

    // Arama filtresi
    if ($search_term = trim($data['search_term'] ?? '')) {
        $conditions[] = "(us.barkod LIKE :search_term OR us.ad LIKE :search_term OR us.kod LIKE :search_term)";
        $params[':search_term'] = "%$search_term%";
    }

    // Departman filtresi
    if (!empty($data['departman_id'])) {
        $conditions[] = "us.departman_id = :departman_id";
        $params[':departman_id'] = $data['departman_id'];
    }

    // Ana Grup filtresi
    if (!empty($data['ana_grup_id'])) {
        $conditions[] = "us.ana_grup_id = :ana_grup_id";
        $params[':ana_grup_id'] = $data['ana_grup_id'];
    }

    // Stok durumu filtresi
    if (!empty($data['stock_status'])) {
        $conditions[] = buildStockStatusCondition($data['stock_status']);
    }

    // Son hareket tarihi filtresi
    if (!empty($data['last_movement_date'])) {
        $conditions[] = "us.id IN (SELECT urun_id FROM stok_hareketleri WHERE DATE(tarih) >= :last_movement_date)";
        $params[':last_movement_date'] = $data['last_movement_date'];
    }

    $where_clause = $conditions ? " WHERE " . implode(' AND ', $conditions) : '';
    return [$where_clause, $params];
}

function buildStockStatusCondition($status) {
    return match ($status) {
        'out' => "us.stok_miktari = 0",
        'low' => "us.stok_miktari > 0 AND us.stok_miktari <= 10",
        'normal' => "us.stok_miktari > 10",
        default => "1=1"
    };
}

function prepareColumns($columns) {
    return implode(', ', array_map(function($col) {
        return match ($col) {
            'departman_id' => 'd.ad as departman',
            'ana_grup_id' => 'ag.ad as ana_grup',
            'alt_grup_id' => 'alg.ad as alt_grup',
            'birim_id' => 'b.ad as birim',
            'stok_miktari' => '(COALESCE((SELECT SUM(ds.stok_miktari) FROM depo_stok ds WHERE ds.urun_id = us.id), 0) +
                               COALESCE((SELECT SUM(ms.stok_miktari) FROM magaza_stok ms WHERE ms.barkod = us.barkod), 0)) as stok_miktari',
            default => "us.$col"
        };
    }, array_filter($columns)));
}

function buildMainQuery($columns_str, $where_clause, $sort_column, $sort_order) {
    return "SELECT 
            $columns_str,
            d.ad as departman,
            ag.ad as ana_grup,
            alg.ad as alt_grup,
            b.ad as birim,
            (COALESCE((SELECT SUM(ds.stok_miktari) FROM depo_stok ds WHERE ds.urun_id = us.id), 0) +
             COALESCE((SELECT SUM(ms.stok_miktari) FROM magaza_stok ms WHERE ms.barkod = us.barkod), 0)) as stok_miktari
        FROM urun_stok us
        LEFT JOIN departmanlar d ON us.departman_id = d.id
        LEFT JOIN ana_gruplar ag ON us.ana_grup_id = ag.id
        LEFT JOIN alt_gruplar alg ON us.alt_grup_id = alg.id
        LEFT JOIN birimler b ON us.birim_id = b.id
        $where_clause
        GROUP BY us.id
        ORDER BY 
            CASE us.durum 
                WHEN 'aktif' THEN 1 
                WHEN 'pasif' THEN 2 
                ELSE 3 
            END, 
            us.$sort_column $sort_order";
}

function executeQuery($conn, $query, $params) {
    $stmt = $conn->prepare($query);
    foreach ($params as $key => $value) {
        $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
        $stmt->bindValue($key, $value, $type);
    }
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function generateTableHtml($rows, $visible_columns) {
    $table = '<table class="table"><thead>' . generateTableHeader($visible_columns) . '</thead><tbody>';
    
    if (!empty($rows)) {
        foreach ($rows as $row) {
            $table .= generateTableRow($row, $visible_columns);
        }
    } else {
        $table .= '<tr><td colspan="' . (count($visible_columns) + 2) . '">Kayıt bulunamadı.</td></tr>';
    }
    
    return $table . '</tbody></table>';
}

function generateTableHeader($visible_columns) {
    // Türkçe sütun başlıkları için eşleştirme dizisi
    $column_display_names = [
        'kod' => 'Ürün Kodu',
        'barkod' => 'Barkod',
        'ad' => 'Ürün Adı',
        'satis_fiyati' => 'Satış Fiyatı',
        'alis_fiyati' => 'Alış Fiyatı',
        'stok_miktari' => 'Stok',
        'indirimli_fiyat' => 'İndirimli Fiyat',
        'kdv_orani' => 'KDV Oranı',
        'web_id' => 'Web ID',
        'yil' => 'Yıl',
        'kayit_tarihi' => 'Kayıt Tarihi',
        'departman' => 'Departman',
        'birim' => 'Birim',
        'ana_grup' => 'Ana Grup',
        'alt_grup' => 'Alt Grup'
    ];

    $header = '<tr><th class="w-4">
        <input type="checkbox" id="selectAll" class="form-checkbox h-4 w-4 text-blue-600 rounded border-gray-300"
               onclick="toggleAllCheckboxes(this)">
    </th>';

    foreach ($visible_columns as $column) {
        // Eşleştirme dizisinden Türkçe başlığı al, yoksa varsayılan değeri kullan
        $display_name = $column_display_names[$column] ?? ucfirst(str_replace('_', ' ', $column));
        
        if ($column === 'stok_miktari') {
            $header .= '<th style="cursor:pointer;" class="stok-header" onclick="sortTableByStock()">' 
                . htmlspecialchars($display_name) 
                . ' <span class="sort-icon">↕</span></th>';
        }
        else if ($column === 'departman_id') {
            $header .= '<th style="cursor:pointer;" class="stok-header" onclick="filterByDepartment()">'
                . 'Departman'
                . ' <svg class="w-4 h-4 inline-block ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                  </svg></th>';
        }
        else if ($column === 'ana_grup_id') {
            $header .= '<th style="cursor:pointer;" class="stok-header" onclick="filterByAnaGrup()">'
                . 'Ana Grup'
                . ' <svg class="w-4 h-4 inline-block ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                  </svg></th>';
        }
        else {
            $header .= '<th>' . htmlspecialchars($display_name) . '</th>';
        }
    }
    
    $header .= '<th>Eylemler</th></tr>';
    return $header;
}

function generateTableRow($row, $visible_columns) {
    $status = $row['durum'] ?? null;
    $rowClass = 'product-row' . ($status === 'pasif' ? ' bg-gray-100' : '');
    
    $html = "<tr class='{$rowClass}' data-status='{$status}' data-product='" . htmlspecialchars(json_encode($row)) . "'>";
    
    // Checkbox
    $html .= '<td class="w-4">
        <input type="checkbox" 
               name="selected_products[]" 
               value="'.$row['id'].'" 
               onclick="event.stopPropagation()" 
               class="form-checkbox h-4 w-4 text-blue-600 rounded border-gray-300">
    </td>';

    // Sütunlar
    foreach ($visible_columns as $column) {
        $value = formatCellValue($row, $column);
        $html .= '<td>' . $value . '</td>';
    }

    // Eylem butonları
    $html .= '<td class="px-6 py-4 whitespace-nowrap">' . getActionButtons($row['id']) . '</td></tr>';
    
    return $html;
}

function formatCellValue($row, $column) {
    if ($column === 'ad') {
        $value = htmlspecialchars($row[$column]);
        if (isset($row['durum'])) {
            $status_badge = $row['durum'] === 'pasif' ? 
                '<span class="status-badge passive">Pasif</span>' : 
                '<span class="status-badge active">Aktif</span>';
            $value .= $status_badge;
        }
        return $value;
    }
    
    if (in_array($column, ['satis_fiyati', 'indirimli_fiyat', 'alis_fiyati'])) {
        return '₺' . number_format($row[$column], 2);
    }
    
    if ($column === 'stok_miktari') {
        $class = (int)$row[$column] < 10 ? 'text-red-600' : 'text-green-600';
        return "<span class='$class'>" . $row[$column] . "</span>";
    }
    
    if (in_array($column, ['departman', 'ana_grup', 'alt_grup', 'birim'])) {
        return htmlspecialchars($row[$column] ?? '-');
    }
    
    if (in_array($column, ['departman_id', 'birim_id', 'ana_grup_id', 'alt_grup_id'])) {
        $mapping = [
            'departman_id' => 'departman',
            'birim_id' => 'birim',
            'ana_grup_id' => 'ana_grup',
            'alt_grup_id' => 'alt_grup'
        ];
        return htmlspecialchars($row[$mapping[$column]] ?? '-');
    }
    
    return htmlspecialchars($row[$column] ?? '');
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
        </div>
    </div>';
}

function generatePaginationHtml($page, $total_pages) {
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

function getTotalRecords($conn, $where_clause, $params) {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM urun_stok us" . $where_clause);
    foreach ($params as $key => $value) {
        $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
        $stmt->bindValue($key, $value, $type);
    }
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
}

function logError($message) {
    error_log($message);
}
?>