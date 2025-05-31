<?php
/**
 * Barkod Toplu Yazdırma Sayfası
 */

// Session ve veritabanı kontrolü - doğrudan erişim için
if (!isset($conn)) {
    // Session yönetimi ve yetkisiz erişim kontrolü
    require_once '../../../session_manager.php';
    // Session kontrolü
    checkUserSession();
    // Veritabanı bağlantısı
    require_once '../../../db_connection.php';
}

// Eğer toplu yazdırma formundan geliyorsa ve ürün ID'leri seçilmişse
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_ids']) && !empty($_POST['product_ids'])) {
    $product_ids = $_POST['product_ids'];
    $quantities = isset($_POST['quantity']) ? $_POST['quantity'] : [];
    $template = isset($_POST['template']) ? intval($_POST['template']) : 1;
    
    // Ürün ID'leri doğru mu kontrol et
    if (empty($product_ids)) {
        echo '<div class="alert alert-danger">Lütfen en az bir ürün seçin!</div>';
        exit;
    }
    
    // Debug için
    error_log("Seçilen ürün ID'leri: " . print_r($product_ids, true));
    error_log("Seçilen adetler: " . print_r($quantities, true));
    
    // Yazdırma URL'sini oluştur
    $print_url = './?page=extensions&ext=tag_management&action=print_multiple&template=' . $template;
    
    // Ürün ID'lerini ve adetleri ekle
    foreach ($product_ids as $id) {
        $qty = isset($quantities[$id]) ? max(1, min(100, intval($quantities[$id]))) : 1;
        $print_url .= '&products[' . $id . ']=' . $qty;
    }
    
    // Debug için 
    error_log("Oluşturulan yazdırma URL'i: " . $print_url);
    
    // Yazdırma sayfasına yönlendir
    header('Location: ' . $print_url);
    exit;
}

// Arama terimi
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Ürünleri getir
$products = barcode_get_products($search);

// Sadece barkodu olanları filtrele
$filtered_products = array_filter($products, function($product) {
    return !empty($product['barkod']);
});

// Mevcut şablonları tanımla
$templates = [
    1 => 'Standart Dikey Yerleşim',
    2 => 'Yatay Yerleşim'
];

// Varsayılan şablonu al
$default_template = intval(barcode_get_setting('etiket_sablonu', 1));
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Toplu Etiket Yazdırma</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="../../index.php">Ana Sayfa</a></li>
                    <li class="breadcrumb-item"><a href="./?page=extensions&ext=tag_management">Barkod Yöneticisi</a></li>
                    <li class="breadcrumb-item active">Toplu Etiket Yazdırma</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h3 class="card-title">Etiket Yazdırılacak Ürünler</h3>
                    
                    <div class="card-tools">
                        <form method="get" class="form-inline" id="search-form">
                            <input type="hidden" name="page" value="extensions">
                            <input type="hidden" name="ext" value="tag_management">
                            <input type="hidden" name="action" value="print_batch">
                            <div class="input-group input-group-sm" style="width: 250px;">
                                <input type="text" name="search" class="form-control float-right" placeholder="Ara..." value="<?= htmlspecialchars($search) ?>">
                                <div class="input-group-append">
                                    <button type="submit" class="btn btn-default">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <form method="post" id="batch-print-form">
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Etiket Şablonu</label>
                                <select name="template" class="form-control">
                                    <?php foreach($templates as $id => $name): ?>
                                    <option value="<?= $id ?>" <?= ($id == $default_template) ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="btn-group mt-4">
                                <button type="button" id="select_all" class="btn btn-default">Tümünü Seç</button>
                                <button type="button" id="unselect_all" class="btn btn-default">Hiçbirini Seçme</button>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (empty($filtered_products)): ?>
                    <div class="alert alert-info">
                        <i class="icon fas fa-info"></i> Barkodu tanımlanmış ürün bulunamadı.
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th width="50">Seç</th>
                                    <th>Ürün Kodu</th>
                                    <th>Ürün Adı</th>
                                    <th>Barkod</th>
                                    <th>Fiyat</th>
                                    <th width="100">Adet</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($filtered_products as $product): ?>
                                <tr>
                                    <td>
                                        <div class="form-check">
                                            <input type="checkbox" name="product_ids[]" value="<?= $product['id'] ?>" class="product-checkbox form-check-input" id="product_<?= $product['id'] ?>">
                                            <label class="form-check-label" for="product_<?= $product['id'] ?>"></label>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($product['kod']) ?></td>
                                    <td><?= htmlspecialchars($product['ad']) ?></td>
                                    <td><?= htmlspecialchars($product['barkod']) ?></td>
                                    <td><?= number_format($product['satis_fiyati'], 2) ?> ₺</td>
                                    <td>
                                        <input type="number" name="quantity[<?= $product['id'] ?>]" value="1" min="1" max="100" class="form-control quantity-input">
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="card-footer">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong>Toplam Etiket Sayısı: <span id="total-labels">0</span></strong>
                        </div>
                        
                        <div>
                            <a href="./?page=extensions&ext=tag_management" class="btn btn-default">İptal</a>
                            <button type="submit" class="btn btn-primary" <?= empty($filtered_products) ? 'disabled' : '' ?>>
                                <i class="fas fa-print"></i> Etiketleri Yazdır
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</section>

<!-- Form submit öncesi ek doğrulama için JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Batch print form validasyonu
    const batchPrintForm = document.getElementById('batch-print-form');
    if (batchPrintForm) {
        batchPrintForm.addEventListener('submit', function(e) {
            const checkboxes = document.querySelectorAll('.product-checkbox:checked');
            if (checkboxes.length === 0) {
                e.preventDefault();
                alert('Lütfen en az bir ürün seçin!');
                return false;
            }
            // Submit öncesi URL'i konsola yazdır (debug için)
            console.log('Form submitting...');
            return true;
        });
    }
});
</script>