<?php
$base_url = "/"; 
include 'header.php';
require_once 'db_connection.php';

// Sayfa yüklenme süresini hesapla
$start_time = microtime(true);

// Yetki kontrolü
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: index.php");
    exit;
}

// Durum göstergeleri için stil ve metin tanımlamaları
$statusColors = [
    'bos' => 'bg-red-100 text-red-800',
    'urun_girildi' => 'bg-yellow-100 text-yellow-800',
    'kismi_aktarildi' => 'bg-blue-100 text-blue-800', 
    'aktarildi' => 'bg-green-100 text-green-800'
];

$statusTexts = [
    'bos' => 'Yeni Fatura',
    'urun_girildi' => 'Aktarım Bekliyor',
    'kismi_aktarildi' => 'Kısmi Aktarıldı',
    'aktarildi' => 'Tamamlandı'
];

// Tedarikçileri çek
$tedarikciler_query = "SELECT * FROM tedarikciler ORDER BY ad";
$tedarikciler = $conn->query($tedarikciler_query)->fetchAll(PDO::FETCH_ASSOC);

// Mağazaları çek
$magazalar_query = "SELECT * FROM magazalar ORDER BY ad";
$magazalar = $conn->query($magazalar_query)->fetchAll(PDO::FETCH_ASSOC);

// Sayfa başına gösterilecek fatura sayısı
$items_per_page = isset($_SESSION['items_per_page']) ? $_SESSION['items_per_page'] : 50;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $items_per_page;

// Tarih filtresi için varsayılan değerler
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Fatura sayısını al
$count_query = "SELECT COUNT(*) as total FROM alis_faturalari WHERE fatura_tarihi BETWEEN ? AND ?";
$stmt = $conn->prepare($count_query);
$stmt->execute([$start_date, $end_date]);
$total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $items_per_page);

// Faturaları çek
$query = "
SELECT 
    af.*,
    t.ad as tedarikci_adi, 
    m.ad as magaza_adi,
    COUNT(afd.id) as urun_sayisi,
    SUM(CASE WHEN afa.id IS NOT NULL THEN afd.miktar ELSE 0 END) as aktarilan_miktar,
    SUM(afd.miktar) as toplam_miktar
FROM 
    alis_faturalari af
    LEFT JOIN tedarikciler t ON af.tedarikci = t.id
    LEFT JOIN magazalar m ON af.magaza = m.id
    LEFT JOIN alis_fatura_detay afd ON af.id = afd.fatura_id
    LEFT JOIN alis_fatura_detay_aktarim afa ON afd.id = afa.fatura_id
WHERE 
    af.fatura_tarihi BETWEEN ? AND ?
GROUP BY 
    af.id
ORDER BY 
    af.fatura_tarihi DESC, af.id DESC
    LIMIT ? 
    OFFSET ?
";

$stmt = $conn->prepare($query);
$stmt->bindValue(1, $start_date, PDO::PARAM_STR);
$stmt->bindValue(2, $end_date, PDO::PARAM_STR);
$stmt->bindValue(3, $items_per_page, PDO::PARAM_INT);
$stmt->bindValue(4, $offset, PDO::PARAM_INT);
$stmt->execute();
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Alış Faturaları</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <!-- Üst Başlık ve Butonlar -->
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold">Alış Faturaları</h1>
                <p class="text-sm text-gray-600">Toplam <?php echo $total_records; ?> fatura</p>
            </div>
            <button onclick="addInvoice()" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                <svg class="w-5 h-5 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                </svg>
                Yeni Fatura Ekle
            </button>
        </div>

        <!-- Filtreler -->
        <div class="bg-white p-4 rounded-lg shadow mb-6">
            <form id="filterForm" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Başlangıç Tarihi</label>
                    <input type="date" name="start_date" value="<?php echo $start_date; ?>" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Bitiş Tarihi</label>
                    <input type="date" name="end_date" value="<?php echo $end_date; ?>" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Durum</label>
                    <select name="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">Tümü</option>
                        <?php foreach ($statusTexts as $key => $text): ?>
                            <option value="<?php echo $key; ?>"><?php echo $text; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                        Filtrele
                    </button>
                </div>
            </form>
        </div>

        <!-- Fatura Listesi -->
        <div class="bg-white shadow-md rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
    <thead class="bg-gray-50">
        <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fatura No</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tedarikçi</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tarih</th>
            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Tutar</th>
            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Durum</th>
            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">İşlemler</th>
        </tr>
    </thead>
    <tbody class="divide-y divide-gray-200">
        <?php foreach ($invoices as $invoice): ?>
    <tr class="invoice-row" data-durum="<?php echo $invoice['durum']; ?>" data-fatura-id="<?php echo $invoice['id']; ?>">
        <td class="px-6 py-4 whitespace-nowrap">
            <?php echo htmlspecialchars($invoice['fatura_seri'] . $invoice['fatura_no']); ?>
        </td>
        <td class="px-6 py-4 whitespace-nowrap">
            <?php echo htmlspecialchars($invoice['tedarikci_adi']); ?>
        </td>
        <td class="px-6 py-4 whitespace-nowrap">
            <?php echo date('d.m.Y', strtotime($invoice['fatura_tarihi'])); ?>
        </td>
        <td class="px-6 py-4 text-right whitespace-nowrap">
            <?php echo number_format($invoice['toplam_tutar'], 2, ',', '.') . ' ₺'; ?>
        </td>
        <td class="px-6 py-4 text-center whitespace-nowrap">
            <span class="px-2 py-1 text-xs rounded-full <?php 
                $statusColors = [
                    'bos' => 'bg-gray-200 text-gray-800',
                    'urun_girildi' => 'bg-yellow-200 text-yellow-800',
                    'aktarim_bekliyor' => 'bg-blue-200 text-blue-800',
                    'kismi_aktarildi' => 'bg-orange-200 text-orange-800',
                    'aktarildi' => 'bg-green-200 text-green-800'
                ];
                echo $statusColors[$invoice['durum']] ?? 'bg-gray-100 text-gray-800';
            ?>">
                <?php 
                    $statusTexts = [
                        'bos' => '📋 Yeni',
                        'urun_girildi' => '📝 Ürün Girildi',
                        'aktarim_bekliyor' => '⏳ Aktarım Bekliyor',
                        'kismi_aktarildi' => '🔄 Kısmi Aktarıldı',
                        'aktarildi' => '✅ Tamamlandı'
                    ];
                    echo $statusTexts[$invoice['durum']] ?? 'Bilinmeyen';
                ?>
            </span>
        </td>
        <td class="px-6 py-4 text-center whitespace-nowrap">
            <div class="flex items-center justify-center space-x-2">
                <!-- ÜRÜN EKLE BUTONU -->
                <?php if (in_array($invoice['durum'], ['bos', 'urun_girildi'])): ?>
                    <button type="button"
                        onclick="window.addProducts(<?php echo $invoice['id']; ?>)" 
                        class="text-blue-600 hover:text-blue-900 p-1 rounded transition-colors" 
                        title="Ürün Ekle">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                    </button>
                <?php else: ?>
                    <button class="text-gray-400 cursor-not-allowed p-1" 
                            title="Bu fatura durumunda ürün eklenemez"
                            disabled>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                    </button>
                <?php endif; ?>

                <!-- AKTARIM BUTONU -->
                <?php if (in_array($invoice['durum'], ['aktarim_bekliyor', 'kismi_aktarildi'])): ?>
                    <button onclick="transferToStore(<?php echo $invoice['id']; ?>)" 
                            class="text-green-600 hover:text-green-900 p-1 rounded transition-colors" 
                            title="Mağazaya Aktar">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                        </svg>
                    </button>
                <?php else: ?>
                    <button class="text-gray-400 cursor-not-allowed p-1" 
                            title="Bu fatura için aktarım yapılamaz"
                            disabled>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                        </svg>
                    </button>
                <?php endif; ?>

                <!-- DETAYLAR BUTONU -->
                <button onclick="showInvoiceDetails(<?php echo $invoice['id']; ?>)" 
                        class="text-purple-600 hover:text-purple-900 p-1 rounded transition-colors" 
                        title="Detaylar">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                </button>

                <!-- DÜZENLE BUTONU -->
                <?php if ($invoice['durum'] !== 'aktarildi'): ?>
                    <button type="button" 
                        onclick="editInvoice(<?php echo $invoice['id']; ?>)" 
                        class="text-yellow-600 hover:text-yellow-900 p-1 rounded transition-colors" 
                        title="Fatura Düzenle">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                    </button>
                <?php else: ?>
                    <button class="text-gray-400 cursor-not-allowed p-1" 
                            title="Tamamlanmış fatura düzenlenemez"
                            disabled>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                    </button>
                <?php endif; ?>

                <!-- SİL BUTONU -->
                <?php if (in_array($invoice['durum'], ['bos', 'urun_girildi'])): ?>
                    <button onclick="deleteInvoice(<?php echo $invoice['id']; ?>)" 
                        class="text-red-600 hover:text-red-800 p-1 rounded transition-colors" 
                        title="Faturayı Sil">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                <?php else: ?>
                    <button class="text-gray-400 cursor-not-allowed p-1" 
                            title="Bu durumda fatura silinemez"
                            disabled>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                <?php endif; ?>
            </div>
        </td>
    </tr>
<?php endforeach; ?>

    </tbody>
</table>
        </div>

        <!-- Sayfalama -->
        <?php if ($total_pages > 1): ?>
        <div class="mt-4 flex justify-center">
            <div class="flex space-x-2">
                <?php if ($page > 1): ?>
                    <a href="?page=1&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" 
                       class="px-4 py-2 bg-white rounded-md hover:bg-gray-50">İlk</a>
                    <a href="?page=<?php echo $page - 1; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" 
                       class="px-4 py-2 bg-white rounded-md hover:bg-gray-50">Önceki</a>
                <?php endif; ?>

                <?php
                $start = max(1, $page - 2);
                $end = min($total_pages, $page + 2);
                
                for ($i = $start; $i <= $end; $i++):
                    $active = $i === $page ? 'bg-blue-500 text-white' : 'bg-white hover:bg-gray-50';
                ?>
                    <a href="?page=<?php echo $i; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" 
                       class="px-4 py-2 <?php echo $active; ?> rounded-md">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" 
                       class="px-4 py-2 bg-white rounded-md hover:bg-gray-50">Sonraki</a>
                    <a href="?page=<?php echo $total_pages; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" 
                       class="px-4 py-2 bg-white rounded-md hover:bg-gray-50">Son</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

<script>
    // Global fonksiyonları tanımla
    window.selectedProducts = [];
    window.handleAddProduct = function(button) {
        const tr = button.closest('tr');
        const productData = JSON.parse(tr.dataset.product);
        window.addToInvoiceFromSearch(productData);
    };

    // Yeni ürün ekle
    window.selectedProducts.push({
        id: product.id,
        kod: product.kod || '-',
        barkod: product.barkod,
        ad: product.ad,
        miktar: 1,
        birim_fiyat: parseFloat(product.alis_fiyati || product.satis_fiyati),
        kdv_orani: parseFloat(product.kdv_orani || 0),
        toplam: parseFloat(product.alis_fiyati || product.satis_fiyati),
        iskonto1: 0,
        iskonto2: 0,
        iskonto3: 0
    });

    // Ürünü arama sonuçlarından kaldır
    removeFromSearchResults(product.id);
    
    updateProductTable();
    
    const searchInput = document.getElementById('barkodSearch');
    if (searchInput) {
        searchInput.focus();
    }
};

// Arama sonuçlarından ürünü kaldıran yeni fonksiyon
function removeFromSearchResults(productId) {
    const searchResults = document.getElementById('searchResults');
    if (searchResults) {
        const productRow = searchResults.querySelector(`tr[data-product*='"id":${productId}']`);
        if (productRow) {
            productRow.remove();
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Tüm durum hücrelerini güncelle
    const statusCells = document.querySelectorAll('.status-cell[data-status]');
    statusCells.forEach(cell => {
        const status = cell.getAttribute('data-status');
        if (status && window.createStatusBadge) {
            cell.innerHTML = window.createStatusBadge(status);
        }
    });
    
    // Tüm işlem hücrelerini güncelle
    const actionCells = document.querySelectorAll('.actions-cell[data-fatura-id][data-status]');
    actionCells.forEach(cell => {
        const faturaId = cell.getAttribute('data-fatura-id');
        const status = cell.getAttribute('data-status');
        if (faturaId && status && window.createActionButtons) {
            cell.innerHTML = window.createActionButtons(faturaId, status);
        }
    });
});
</script>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="assets/js/purchase_invoices.js"></script>

<?php
// Sayfa yüklenme süresini hesapla
$end_time = microtime(true);
$execution_time = round($end_time - $start_time, 3);

// Sayfa özel scriptleri
$page_scripts = '
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script type="module" src="assets/js/main.js"></script>
<script type="module" src="assets/js/stock_list.js"></script>
<script src="assets/js/invoice_import.js"></script>
<script src="assets/js/status_helper.js"></script>
';

// Footer'ı dahil et
include 'footer.php';
?>

</body>
</html>