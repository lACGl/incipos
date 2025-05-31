<?php
/**
 * Barkod Yöneticisi Ana Sayfa
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

// Arama terimi
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Ürünleri getir
$products = barcode_get_products($search);
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Barkod Yöneticisi</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="../../index.php">Ana Sayfa</a></li>
                    <li class="breadcrumb-item active">Barkod Yöneticisi</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
        <?php
        // Bildirimler
        if (isset($_SESSION['barcode_success'])) {
            echo '<div class="alert alert-success alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                    <h5><i class="icon fas fa-check"></i> Başarılı!</h5>
                    ' . $_SESSION['barcode_success'] . '
                  </div>';
            unset($_SESSION['barcode_success']);
        }
        
        if (isset($_SESSION['barcode_error'])) {
            echo '<div class="alert alert-danger alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                    <h5><i class="icon fas fa-ban"></i> Hata!</h5>
                    ' . $_SESSION['barcode_error'] . '
                  </div>';
            unset($_SESSION['barcode_error']);
        }
        
        if (isset($_SESSION['barcode_info'])) {
            echo '<div class="alert alert-info alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                    <h5><i class="icon fas fa-info"></i> Bilgi!</h5>
                    ' . $_SESSION['barcode_info'] . '
                  </div>';
            unset($_SESSION['barcode_info']);
        }
        ?>
        
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h3 class="card-title">Ürün Barkodları</h3>
                            
                            <div class="card-tools">
                                <form method="get" class="form-inline" id="search-form">
                                    <input type="hidden" name="page" value="extensions">
                                    <input type="hidden" name="ext" value="tag_management">
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
                    
                    <div class="card-body">
                        <?php if (empty($search)): ?>
                        <div class="alert alert-info">
                            <i class="icon fas fa-info-circle"></i> Son eklenen 50 ürün listeleniyor. Daha fazla ürün görmek için arama yapabilirsiniz.
                        </div>
                        <?php endif; ?>
                        
                        <div class="barcode-actions mb-3">
                            <div class="btn-group">
                                <a href="./?page=extensions&ext=tag_management&action=print_batch" class="btn btn-primary">
                                    <i class="fas fa-print"></i> Toplu Etiket Yazdır
                                </a>
                                <a href="./?page=extensions&ext=tag_management&action=settings" class="btn btn-info">
                                    <i class="fas fa-cog"></i> Ayarlar
                                </a>
                            </div>
                            
                            <form method="post" class="d-inline ml-2">
                                <input type="hidden" name="barcode_action" value="generate_bulk">
                                <button type="submit" class="btn btn-warning">
                                    <i class="fas fa-magic"></i> Barkodu Olmayan Ürünler İçin Otomatik Oluştur
                                </button>
                            </form>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped" id="barcode-table">
                                <thead>
                                    <tr>
                                        <th>Ürün Kodu</th>
                                        <th>Ürün Adı</th>
                                        <th>Barkod</th>
                                        <th>Barkod Görseli</th>
                                        <th>Fiyat</th>
                                        <th>Stok</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($product['kod']) ?></td>
                                        <td><?= htmlspecialchars($product['ad']) ?></td>
                                        <td>
                                            <?php if (!empty($product['barkod'])): ?>
                                                <?= htmlspecialchars($product['barkod']) ?>
                                            <?php else: ?>
                                                <span class="badge badge-danger">Barkod Yok</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($product['barkod'])): ?>
                                                <img src="./?page=extensions&ext=tag_management&action=ajax&op=barcode_image&code=<?= urlencode($product['barkod']) ?>" alt="Barkod">
                                            <?php else: ?>
                                                <span class="text-muted">Görsel Yok</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= number_format($product['satis_fiyati'], 2) ?> ₺</td>
                                        <td><?= $product['stok_miktari'] ?></td>
                                        <td>
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" 
                                                        data-target="#barcodeModal" 
                                                        data-id="<?= $product['id'] ?>" 
                                                        data-name="<?= htmlspecialchars($product['ad']) ?>" 
                                                        data-barcode="<?= htmlspecialchars($product['barkod']) ?>">
                                                    <i class="fas fa-edit"></i> Barkod Düzenle
                                                </button>
                                                
                                                <?php if (!empty($product['barkod'])): ?>
                                                <a href="./?page=extensions&ext=tag_management&action=print_single&id=<?= $product['id'] ?>" 
                                                   class="btn btn-sm btn-success" target="_blank">
                                                    <i class="fas fa-print"></i> Etiket Yazdır
                                                </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Barkod Düzenleme Modal -->
<div class="modal fade" id="barcodeModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Barkod Düzenle</h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="barcode_action" value="save">
                    <input type="hidden" name="urun_id" id="urun_id">
                    
                    <div class="form-group">
                        <label for="urun_adi">Ürün Adı</label>
                        <input type="text" class="form-control" id="urun_adi" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="barcode_type">Barkod Tipi</label>
                        <select class="form-control" name="barcode_type" id="barcode_type">
                            <option value="EAN13">EAN-13 (Standart 13 Haneli)</option>
                            <option value="EAN8">EAN-8 (Kısa 8 Haneli)</option>
                            <option value="CODE128">Code 128 (Alfanümerik)</option>
                            <option value="CODE39">Code 39 (Alfanümerik)</option>
                            <option value="UPCA">UPC-A (12 Haneli)</option>
                            <option value="UPCE">UPC-E (8 Haneli)</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="barcode">Barkod</label>
                        <input type="text" class="form-control" name="barcode" id="barcode" maxlength="13">
                        <small class="form-text text-muted">
                            Boş bırakırsanız, sistem otomatik barkod oluşturacaktır.
                        </small>
                    </div>
                    
                    <div class="barcode-preview">
                        <img src="" id="barcode_preview" style="display: none;">
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-default" data-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Sayfanın alt kısmındaki formun görünümünü iyileştirmek için ekstra CSS -->
<style>
/* Ana sayfa için ek stil düzeltmeleri */
.container-fluid {
    padding: 0 15px;
}

.card {
    margin-bottom: 20px;
    box-shadow: 0 0 1px rgba(0,0,0,.125), 0 1px 3px rgba(0,0,0,.2);
    border-radius: 0.25rem;
    background-color: #fff;
}

.card-header {
    background-color: rgba(0,0,0,.03);
    border-bottom: 1px solid rgba(0,0,0,.125);
    padding: 0.75rem 1.25rem;
}

.card-body {
    padding: 1.25rem;
}

.table {
    width: 100%;
    margin-bottom: 1rem;
    color: #212529;
    border-collapse: collapse;
}

.barcode-preview {
    text-align: center;
    margin: 15px 0;
    padding: 10px;
    border: 1px solid #eee;
    border-radius: 4px;
}

.barcode-preview img {
    max-width: 100%;
    height: auto;
}

/* Barkod düzenleme formu için stil */
#barkodDuzenleFormu {
    background-color: #f9f9f9;
    padding: 20px;
    border-radius: 5px;
    margin-top: 20px;
    border: 1px solid #ddd;
}

#barkodDuzenleFormu label {
    font-weight: bold;
    margin-bottom: 5px;
    display: block;
}

#barkodDuzenleFormu input[type="text"] {
    width: 100%;
    padding: 8px;
    margin-bottom: 10px;
    border: 1px solid #ccc;
    border-radius: 4px;
}

#barkodDuzenleFormu button {
    background-color: #007bff;
    color: white;
    border: none;
    padding: 10px 15px;
    border-radius: 4px;
    cursor: pointer;
}

#barkodDuzenleFormu button:hover {
    background-color: #0069d9;
}
</style>