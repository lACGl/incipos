<?php
$base_url = "/"; 
session_start();
require_once 'db_connection.php';

// Yetki kontrolü
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: index.php");
    exit;
}
// Diğer sorgulardan önce tedarikçileri çekelim
$tedarikciler_query = "SELECT * FROM tedarikciler ORDER BY ad";
$tedarikciler = $conn->query($tedarikciler_query)->fetchAll(PDO::FETCH_ASSOC);

// Mağazaları da çekelim
$magazalar_query = "SELECT * FROM magazalar ORDER BY ad";
$magazalar = $conn->query($magazalar_query)->fetchAll(PDO::FETCH_ASSOC);

// Sayfa başına gösterilecek fatura sayısı
$items_per_page = isset($_SESSION['items_per_page']) ? $_SESSION['items_per_page'] : 50;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $items_per_page;

// Fatura sayısını al
$count_query = "SELECT COUNT(*) as total FROM alis_faturalari";
$total_records = $conn->query($count_query)->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $items_per_page);

// Faturaları çek
$query = "
    SELECT 
        af.*,
        t.ad as tedarikci_adi,
        m.ad as magaza_adi,
        COUNT(afd.id) as urun_sayisi
    FROM 
        alis_faturalari af
        LEFT JOIN tedarikciler t ON af.tedarikci = t.id
        LEFT JOIN magazalar m ON af.magaza = m.id
        LEFT JOIN alis_fatura_detay afd ON af.id = afd.fatura_id
    GROUP BY 
        af.id
    ORDER BY 
        af.fatura_tarihi DESC, af.id DESC
    LIMIT :offset, :limit
";

$stmt = $conn->prepare($query);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', $items_per_page, PDO::PARAM_INT);
$stmt->execute();
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Alış Faturaları</title>
	<link rel="stylesheet" href="/assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">Alış Faturaları</h1>
            <button onclick="addInvoice()" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
    Yeni Fatura Ekle
</button>
        </div>

        <!-- Fatura Listesi -->
        <div class="bg-white shadow-md rounded my-6">
            <table class="min-w-full">
                <thead>
                    <tr class="bg-gray-200 text-gray-700 uppercase text-sm leading-normal">
                        <th class="py-3 px-6 text-left">Fatura No</th>
                        <th class="py-3 px-6 text-left">Tedarikçi</th>
                        <th class="py-3 px-6 text-left">Tarih</th>
                        <th class="py-3 px-6 text-right">Tutar</th>
                        <th class="py-3 px-6 text-center">Durum</th>
                        <th class="py-3 px-6 text-center">Mağaza</th>
                        <th class="py-3 px-6 text-center">İşlemler</th>
                    </tr>
                </thead>
                <tbody class="text-gray-600 text-sm">
                    <?php foreach ($invoices as $invoice): ?>
                        <?php
                        // Durum renklerini belirle
                        $statusColor = match($invoice['durum']) {
                            'bos' => 'bg-red-100',
                            'urun_girildi' => 'bg-blue-100',
                            'aktarildi' => 'bg-green-100',
                            default => ''
                        };
                        
                        $statusText = match($invoice['durum']) {
                            'bos' => 'Ürün Girilmemiş',
                            'urun_girildi' => 'Aktarım Bekliyor',
                            'aktarildi' => 'Tamamlandı',
                            default => 'Bilinmiyor'
                        };
                        ?>
                        <tr class="border-b border-gray-200 hover:bg-gray-50 <?php echo $statusColor; ?>">
                            <td class="py-3 px-6 text-left">
                                <?php echo htmlspecialchars($invoice['fatura_seri'] . $invoice['fatura_no']); ?>
                            </td>
                            <td class="py-3 px-6 text-left">
                                <?php echo htmlspecialchars($invoice['tedarikci_adi']); ?>
                            </td>
                            <td class="py-3 px-6 text-left">
                                <?php echo date('d.m.Y', strtotime($invoice['fatura_tarihi'])); ?>
                            </td>
                            <td class="py-3 px-6 text-right">
                                <?php echo number_format($invoice['toplam_tutar'], 2, ',', '.') . ' ₺'; ?>
                            </td>
                            <td class="py-3 px-6 text-center">
                                <span class="px-3 py-1 rounded-full text-xs">
                                    <?php echo $statusText; ?>
                                </span>
                            </td>
                            <td class="py-3 px-6 text-center">
                                <?php echo htmlspecialchars($invoice['magaza_adi'] ?? '-'); ?>
                            </td>
                            <td class="py-3 px-6 text-center">
                                <div class="flex item-center justify-center">
                                    <!-- Ürün Girişi Butonu -->
                                    <?php if ($invoice['durum'] !== 'aktarildi'): ?>
                                        <button onclick="addProducts(<?php echo $invoice['id']; ?>)" 
                                                class="transform hover:scale-110 mx-1" 
                                                title="Ürün Girişi">
                                            <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                            </svg>
                                        </button>
                                    <?php endif; ?>

                                    <!-- Mağazaya Aktarım Butonu -->
                                            <?php if ($invoice['durum'] === 'urun_girildi' || $invoice['durum'] === 'kismi_aktarildi'): ?>
            <button onclick="transferToStore(<?php echo $invoice['id']; ?>)"
                    class="transform hover:scale-110 mx-1"
                    title="Mağazaya Aktar">
                <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                </svg>
            </button>
        <?php endif; ?>

                                    <!-- Düzenleme Butonu -->
                                    <?php if ($invoice['durum'] === 'bos'): ?>
            <button onclick="editInvoice(<?php echo $invoice['id']; ?>)"
                    class="transform hover:scale-110 mx-1"
                    title="Düzenle">
                <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                </svg>
            </button>
        <?php endif; ?>

                                    <!-- Detay Butonu -->
                                    <button onclick="showInvoiceDetails(<?php echo $invoice['id']; ?>)"
                class="transform hover:scale-110 mx-1"
                title="Detaylar">
            <svg class="w-5 h-5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
            </svg>
        </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
			<!-- Fatura Ekleme Modalı -->
<div id="addInvoiceModal" class="modal hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="modal-content bg-white p-6 rounded-lg shadow-lg w-full max-w-2xl">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold">Yeni Fatura Ekle</h2>
            <button onclick="closeModal('addInvoiceModal')" class="text-gray-500 hover:text-gray-700">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <form id="addInvoiceForm" onsubmit="handleAddInvoice(event)">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Fatura Seri</label>
                    <input type="text" name="fatura_seri" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Fatura No</label>
                    <input type="text" name="fatura_no" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200" required>
                </div>
                <div>
    <label class="block text-sm font-medium text-gray-700">Tedarikçi*</label>
    <select name="tedarikci" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200" required>
        <option value="">Tedarikçi Seçin</option>
        <?php foreach ($tedarikciler as $tedarikci): ?>
            <option value="<?php echo htmlspecialchars($tedarikci['id']); ?>">
                <?php echo htmlspecialchars($tedarikci['ad']); ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Fatura Tarihi</label>
                    <input type="date" name="fatura_tarihi" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Mağaza</label>
                    <select name="magaza" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200" required>
                        <?php foreach ($magazalar as $magaza): ?>
                            <option value="<?php echo $magaza['id']; ?>"><?php echo htmlspecialchars($magaza['ad']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700">Açıklama</label>
                    <textarea name="aciklama" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200"></textarea>
                </div>
            </div>
            <div class="mt-4 flex justify-end">
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    Fatura Oluştur
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Ürün Ekleme Modalı -->
    <!-- Ürün Ekleme Modalı -->
    <div id="addProductModal" class="modal hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="modal-content bg-white p-6 rounded-lg shadow-lg w-full max-w-4xl">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold">Faturaya Ürün Ekle</h2>
                <button onclick="closeModal('addProductModal')" class="text-gray-500 hover:text-gray-700">×</button>
            </div>
            <form id="addProductForm">
                <input type="hidden" name="fatura_id" id="productFaturaId">
                
                <!-- Arama bölümü -->
                <div class="mb-4">
                    <div class="flex space-x-2">
                        <input type="text" 
                               id="barkodSearch" 
                               class="flex-1 px-4 py-2 border rounded-lg" 
                               placeholder="Barkod okutun veya ürün adı ile arama yapın...">
                        <button type="button" 
                                onclick="searchProduct()"
                                class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg">
                            Ürün Ara
                        </button>
                    </div>
                    <!-- Arama sonuçları için div -->
                    <div id="searchResults" class="mt-4 bg-white rounded-lg shadow border max-h-60 overflow-y-auto"></div>
                </div>

                <!-- Seçilen ürünler tablosu -->
                <div class="mt-4">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-4 py-2 text-left">ÜRÜN</th>
                                <th class="px-4 py-2 text-center">MİKTAR</th>
                                <th class="px-4 py-2 text-right">BİRİM FİYAT</th>
                                <th class="px-4 py-2 text-right">TOPLAM</th>
                                <th class="px-4 py-2 text-center">İŞLEM</th>
                            </tr>
                        </thead>
                        <tbody id="productTableBody">
                            <!-- Ürünler buraya eklenecek -->
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" class="px-4 py-2 text-right font-bold">Genel Toplam:</td>
                                <td id="genelToplam" class="px-4 py-2 text-right font-bold">₺0.00</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Modal footer -->
                <div class="mt-4 flex justify-end space-x-2">
                    <button type="button" 
                            onclick="closeModal('addProductModal')"
                            class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">
                        Kapat
                    </button>
                    <button type="button"
        onclick="saveProducts()"
        class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded">
    Kaydet
</button>
                </div>
            </form>
        </div>
    </div>

<!-- Mağaza Aktarım Modalı -->
<div id="transferModal" class="modal hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="modal-content bg-white p-6 rounded-lg shadow-lg w-full max-w-2xl">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold">Mağazaya Aktar</h2>
            <!-- Transfer butonu örneği -->
<button onclick="transferToStore('<?php echo $fatura_id; ?>')" 
        class="transform hover:scale-110 mx-1"
        title="Mağazaya Aktar"
        data-fatura-id="<?php echo $fatura_id; ?>">
    <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
              d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
    </svg>
</button>
        </div>
        <form id="transferForm" onsubmit="handleTransfer(event)">
            <input type="hidden" name="fatura_id" id="transferFaturaId">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700">Mağaza Seçin</label>
                <select name="magaza_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200" required>
                    <?php foreach ($magazalar as $magaza): ?>
                        <option value="<?php echo $magaza['id']; ?>"><?php echo htmlspecialchars($magaza['ad']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div id="transferProductList" class="mb-4">
                <!-- Ürün listesi burada dinamik olarak gösterilecek -->
            </div>
            <div class="flex justify-end space-x-4">
                <button type="button" onclick="closeModal('transferModal')" 
                        class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                    İptal
                </button>
                <button type="submit" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                    Aktarımı Tamamla
                </button>
            </div>
        </form>
    </div>
</div>
        </div>

        <!-- Pagination -->
        <div class="pagination flex justify-center mt-6">
            <?php if ($total_pages > 1): ?>
                <div class="flex space-x-2">
                    <?php if ($page > 1): ?>
                        <a href="?page=1" class="px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300">İlk</a>
                        <a href="?page=<?php echo $page-1; ?>" class="px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300">Önceki</a>
                    <?php endif; ?>
                    
                    <?php
                    $start = max(1, $page - 2);
                    $end = min($total_pages, $page + 2);
                    
                    for ($i = $start; $i <= $end; $i++):
                        $active = $i === $page ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-800';
                    ?>
                        <a href="?page=<?php echo $i; ?>" 
                           class="px-4 py-2 <?php echo $active; ?> rounded hover:bg-gray-300">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page+1; ?>" class="px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300">Sonraki</a>
                        <a href="?page=<?php echo $total_pages; ?>" class="px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300">Son</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
<script>
const BASE_URL = '<?php echo $base_url; ?>';

document.addEventListener('DOMContentLoaded', async function() {
    // Tedarikçi select elementini bul
    const tedarikciSelect = document.querySelector('select[name="tedarikci"]');
    if (tedarikciSelect) {
        // Tedarikçileri yükle
        const options = await getTedarikciOptions();
        tedarikciSelect.innerHTML = '<option value="">Tedarikçi Seçin</option>' + options;
    }
});    
</script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="assets/js/purchase_invoices.js"></script>
<script src="assets/js/main.js"></script>
</body>
</html>