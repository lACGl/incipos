<?php
/**
 * Barkod Yöneticisi Ayarlar Sayfası - Tamamen Yeniden Yazılmış
 */

// Session ve veritabanı kontrolü
if (!isset($conn)) {
    require_once '../../../session_manager.php';
    checkUserSession();
    require_once '../../../db_connection.php';
}

// Fonksiyonları dahil et
require_once 'includes/functions.php';

// Ayarları kaydetme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    try {
        $settings_to_save = [
            'etiket_genislik' => max(50, min(200, intval($_POST['etiket_genislik']))),
            'etiket_yukseklik' => max(20, min(150, intval($_POST['etiket_yukseklik']))),
            'kenar_boslugu' => max(0, min(10, floatval($_POST['kenar_boslugu']))),
            'qr_boyut' => max(100, min(500, intval($_POST['qr_boyut']))),
            'barkod_yukseklik' => max(30, min(120, intval($_POST['barkod_yukseklik']))),
            'default_code_type' => in_array($_POST['default_code_type'], ['qr', 'ean13', 'code128', 'code39', 'ean8', 'upca']) ? $_POST['default_code_type'] : 'qr',
            'etiket_sablonu' => in_array($_POST['etiket_sablonu'], ['1', '2']) ? intval($_POST['etiket_sablonu']) : 1,
            'firma_adi' => trim(substr($_POST['firma_adi'], 0, 50)),
            'printer_type' => in_array($_POST['printer_type'], ['browser', 'thermal', 'escpos']) ? $_POST['printer_type'] : 'browser',
            'printer_language' => in_array($_POST['printer_language'], ['ZPL', 'EPL']) ? $_POST['printer_language'] : 'ZPL',
            'yazici_ip' => trim($_POST['yazici_ip']),
            'yazici_port' => max(1, min(65535, intval($_POST['yazici_port']))),
            'auto_print' => isset($_POST['auto_print']) ? 1 : 0,
            'show_preview' => isset($_POST['show_preview']) ? 1 : 0
        ];
        
        foreach ($settings_to_save as $key => $value) {
            barcode_update_setting($key, $value);
        }
        
        $_SESSION['barcode_success'] = 'Ayarlar başarıyla kaydedildi!';
        //header('Location: ' . $_SERVER['REQUEST_URI']);
        //exit;
        
    } catch (Exception $e) {
        $_SESSION['barcode_error'] = 'Ayarlar kaydedilirken hata oluştu: ' . $e->getMessage();
    }
}

// AJAX istekleri için
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json');
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_preview':
                try {
                    // Önizleme için gerekli değerleri al
                    $preview_settings = [
                        'etiket_genislik' => max(50, min(200, intval($_POST['etiket_genislik']))),
                        'etiket_yukseklik' => max(20, min(150, intval($_POST['etiket_yukseklik']))),
                        'kenar_boslugu' => max(0, min(10, floatval($_POST['kenar_boslugu']))),
                        'firma_adi' => trim(substr($_POST['firma_adi'], 0, 50)),
                        'default_code_type' => $_POST['default_code_type'],
                        'etiket_sablonu' => intval($_POST['etiket_sablonu']),
                        'qr_boyut' => max(100, min(500, intval($_POST['qr_boyut']))),
                        'barkod_yukseklik' => max(30, min(120, intval($_POST['barkod_yukseklik'])))
                    ];
                    
                    // Örnek ürün için önizleme HTML'i oluştur
                    $example_product = [
                        'id' => 0,
                        'ad' => 'Örnek Ürün Adı',
                        'barkod' => '8690123456789',
                        'satis_fiyati' => 24.99
                    ];
                    
                    // Barkod görüntüsünü oluştur
                    $code_image = './?page=extensions&ext=tag_management&action=ajax&op=barcode_image&code=' . 
                                 urlencode($example_product['barkod']) . 
                                 '&type=' . $preview_settings['default_code_type'] . 
                                 '&t=' . time(); // Cache önlemek için timestamp ekle
                    
                    // HTML oluştur
                    $html = '
                    <div class="label-content template-' . $preview_settings['etiket_sablonu'] . '">
                        <div class="product-name">' . htmlspecialchars($example_product['ad']) . '</div>
                        
                        <div class="bottom-section">
                            <div class="barcode-area">
                                <img src="' . $code_image . '" alt="Barkod" class="barcode-img">
                            </div>
                            
                            <div class="price-section">
                                <div class="price">' . number_format($example_product['satis_fiyati'], 2) . ' ₺</div>
                                <div class="barcode-number">' . formatBarcodeWithBold($example_product['barkod']) . '</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="side-text side-left">' . htmlspecialchars($preview_settings['firma_adi']) . '</div>
                    <div class="side-text side-right">' . htmlspecialchars($preview_settings['firma_adi']) . '</div>';
                    
                    // Stil bilgilerini gönder
                    $style = [
                        'width' => $preview_settings['etiket_genislik'] . 'mm',
                        'height' => $preview_settings['etiket_yukseklik'] . 'mm',
                        'padding' => $preview_settings['kenar_boslugu'] . 'mm',
                        'contentWidth' => ($preview_settings['etiket_genislik'] - ($preview_settings['kenar_boslugu'] * 2)) . 'mm',
                        'contentHeight' => ($preview_settings['etiket_yukseklik'] - ($preview_settings['kenar_boslugu'] * 2)) . 'mm',
                    ];
                    
                    echo json_encode([
                        'success' => true, 
                        'html' => $html,
                        'style' => $style,
                        'dimensions' => $preview_settings['etiket_genislik'] . 'mm × ' . $preview_settings['etiket_yukseklik'] . 'mm',
                        'padding' => $preview_settings['kenar_boslugu'] . 'mm'
                    ]);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'error' => 'Önizleme oluşturulurken hata: ' . $e->getMessage()]);
                }
                exit;
                
            case 'test_print':
                // Test yazdırma
                $printer_type = $_POST['printer_type'];
                $printer_ip = trim($_POST['printer_ip']);
                $printer_port = intval($_POST['printer_port']);
                $printer_language = $_POST['printer_language'] ?? 'ZPL';
                
                if ($printer_type === 'browser') {
                    echo json_encode(['success' => true, 'url' => './?page=extensions&ext=tag_management&action=print_test']);
                } else {
                    // Ağ yazıcısına test yazdırma
                    if (empty($printer_ip)) {
                        echo json_encode(['success' => false, 'error' => 'Yazıcı IP adresi gerekli!']);
                        exit;
                    }
                    
                    try {
                        $socket = @fsockopen($printer_ip, $printer_port, $errno, $errstr, 5);
                        
                        if (!$socket) {
                            echo json_encode(['success' => false, 'error' => "Yazıcıya bağlanılamadı: $errstr ($errno)"]);
                            exit;
                        }
                        
                        // Test etiketi gönder
                        if ($printer_type === 'thermal') {
                            if ($printer_language === 'ZPL') {
                                $test_command = "^XA^FO50,50^A0N,30,30^FDTest Etiketi^FS^FO50,100^A0N,20,20^FD" . date('Y-m-d H:i:s') . "^FS^XZ";
                            } else { // EPL
                                $test_command = "N\nS4\nD15,12,0\nTDN\nA50,50,0,3,1,1,N,\"Test Etiketi\"\nA50,100,0,3,1,1,N,\"" . date('Y-m-d H:i:s') . "\"\nP1\n";
                            }
                        } else { // ESC/POS
                            $test_command = "\x1B\x40" . // Initialize
                                          "Test Etiketi\n" .
                                          date('Y-m-d H:i:s') . "\n\n" .
                                          "\x1D\x56\x00"; // Cut paper
                        }
                        
                        $result = fwrite($socket, $test_command);
                        fclose($socket);
                        
                        if ($result === false) {
                            echo json_encode(['success' => false, 'error' => 'Yazıcıya komut gönderilemedi.']);
                        } else {
                            echo json_encode(['success' => true, 'message' => 'Test yazdırması başarıyla gönderildi!']);
                        }
                        
                    } catch (Exception $e) {
                        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                    }
                }
                exit;
        }
    }
    
    echo json_encode(['success' => false, 'error' => 'Geçersiz istek']);
    exit;
}

// Mevcut ayarları al
$settings = [
    'etiket_genislik' => barcode_get_setting('etiket_genislik', 100),
    'etiket_yukseklik' => barcode_get_setting('etiket_yukseklik', 38),
    'kenar_boslugu' => barcode_get_setting('kenar_boslugu', 2),
    'qr_boyut' => barcode_get_setting('qr_boyut', 200),
    'barkod_yukseklik' => barcode_get_setting('barkod_yukseklik', 60),
    'default_code_type' => barcode_get_setting('default_code_type', 'qr'),
    'etiket_sablonu' => barcode_get_setting('etiket_sablonu', 1),
    'firma_adi' => barcode_get_setting('firma_adi', 'İnci Kırtasiye'),
    'printer_type' => barcode_get_setting('printer_type', 'browser'),
    'printer_language' => barcode_get_setting('printer_language', 'ZPL'),
    'yazici_ip' => barcode_get_setting('yazici_ip', ''),
    'yazici_port' => barcode_get_setting('yazici_port', 9100),
    'auto_print' => barcode_get_setting('auto_print', 0),
    'show_preview' => barcode_get_setting('show_preview', 1)
];

// Şablonlar ve kod tipleri
$templates = [
    1 => 'Standart Dikey Yerleşim',
    2 => 'Yatay Yerleşim'
];

$code_types = [
    'qr' => 'QR Kod',
    'ean13' => 'EAN-13',
    'code128' => 'Code 128',
    'code39' => 'Code 39',
    'ean8' => 'EAN-8',
    'upca' => 'UPC-A'
];

// Önizleme için ürün
$preview_product = null;
try {
    $stmt = $conn->query("SELECT * FROM urun_stok WHERE barkod IS NOT NULL AND barkod != '' ORDER BY id DESC LIMIT 1");
    if ($stmt->rowCount() > 0) {
        $preview_product = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log('Önizleme ürünü alma hatası: ' . $e->getMessage());
}

if (!$preview_product) {
    $preview_product = [
        'id' => 0,
        'ad' => 'Örnek Ürün Adı Test Uzun İsim',
        'barkod' => '8690123456789',
        'satis_fiyati' => 24.99
    ];
}
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Barkod Yazıcı Ayarları</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="../../index.php">Ana Sayfa</a></li>
                    <li class="breadcrumb-item"><a href="./?page=extensions&ext=tag_management">Barkod Yöneticisi</a></li>
                    <li class="breadcrumb-item active">Ayarlar</li>
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
        ?>
        
        <form method="post" id="settings-form">
            <input type="hidden" name="save_settings" value="1">
            
            <div class="row">
                <!-- SOL KOLON - AYARLAR -->
                <div class="col-lg-6">
                    <!-- ETİKET BOYUT AYARLARI -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-ruler"></i> Etiket Boyut Ayarları</h3>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="etiket_genislik">Genişlik (mm)</label>
                                        <input type="number" class="form-control preview-trigger" id="etiket_genislik" name="etiket_genislik" 
                                               value="<?= $settings['etiket_genislik'] ?>" min="50" max="200" step="1" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="etiket_yukseklik">Yükseklik (mm)</label>
                                        <input type="number" class="form-control preview-trigger" id="etiket_yukseklik" name="etiket_yukseklik" 
                                               value="<?= $settings['etiket_yukseklik'] ?>" min="20" max="150" step="1" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="kenar_boslugu">Kenar Boşluğu (mm)</label>
                                        <input type="number" class="form-control preview-trigger" id="kenar_boslugu" name="kenar_boslugu" 
                                               value="<?= $settings['kenar_boslugu'] ?>" min="0" max="10" step="0.5" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="alert alert-info">
                                <small><i class="fas fa-info-circle"></i> Standart termal etiket boyutu: 100mm x 38mm</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- GÖRÜNÜM AYARLARI -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-palette"></i> Görünüm Ayarları</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label for="etiket_sablonu">Etiket Şablonu</label>
                                <select class="form-control preview-trigger" id="etiket_sablonu" name="etiket_sablonu">
                                    <?php foreach($templates as $id => $name): ?>
                                    <option value="<?= $id ?>" <?= ($id == $settings['etiket_sablonu']) ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="default_code_type">Kod Tipi</label>
                                <select class="form-control preview-trigger" id="default_code_type" name="default_code_type">
                                    <?php foreach($code_types as $type => $name): ?>
                                    <option value="<?= $type ?>" <?= ($type == $settings['default_code_type']) ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="firma_adi">Firma Adı (Yan Yazı)</label>
                                <input type="text" class="form-control preview-trigger" id="firma_adi" name="firma_adi" 
                                       value="<?= htmlspecialchars($settings['firma_adi']) ?>" maxlength="50">
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="qr_boyut">QR Kod Boyutu (px)</label>
                                        <input type="number" class="form-control preview-trigger" id="qr_boyut" name="qr_boyut">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="barkod_yukseklik">Barkod Yüksekliği (px)</label>
                                        <input type="number" class="form-control preview-trigger" id="barkod_yukseklik" name="barkod_yukseklik" 
                                               value="<?= $settings['barkod_yukseklik'] ?>" min="30" max="120" step="5">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- YAZICI AYARLARI -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-print"></i> Yazıcı Ayarları</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label for="printer_type">Yazıcı Tipi</label>
                                <select class="form-control" id="printer_type" name="printer_type">
                                    <option value="browser" <?= ($settings['printer_type'] == 'browser') ? 'selected' : '' ?>>Tarayıcı Üzerinden</option>
                                    <option value="thermal" <?= ($settings['printer_type'] == 'thermal') ? 'selected' : '' ?>>Termal Yazıcı (ZPL/EPL)</option>
                                    <option value="escpos" <?= ($settings['printer_type'] == 'escpos') ? 'selected' : '' ?>>ESC/POS Yazıcı</option>
                                </select>
                            </div>
                            
                            <div id="network-printer-settings" style="display: <?= ($settings['printer_type'] != 'browser') ? 'block' : 'none' ?>;">
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="form-group">
                                            <label for="yazici_ip">Yazıcı IP Adresi</label>
                                            <input type="text" class="form-control" id="yazici_ip" name="yazici_ip" 
                                                   value="<?= htmlspecialchars($settings['yazici_ip']) ?>" placeholder="192.168.1.100">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="yazici_port">Port</label>
                                            <input type="number" class="form-control" id="yazici_port" name="yazici_port" 
                                                   value="<?= $settings['yazici_port'] ?>" min="1" max="65535">
                                        </div>
                                    </div>
                                </div>
                                
                                <div id="thermal-printer-settings" style="display: <?= ($settings['printer_type'] == 'thermal') ? 'block' : 'none' ?>;">
                                    <div class="form-group">
                                        <label for="printer_language">Yazıcı Dili</label>
                                        <select class="form-control" id="printer_language" name="printer_language">
                                            <option value="ZPL" <?= ($settings['printer_language'] == 'ZPL') ? 'selected' : '' ?>>ZPL (Zebra)</option>
                                            <option value="EPL" <?= ($settings['printer_language'] == 'EPL') ? 'selected' : '' ?>>EPL (Eltron)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="show_preview" name="show_preview" <?= $settings['show_preview'] ? 'checked' : '' ?>>
                                    <label class="custom-control-label" for="show_preview">Yazdırma öncesi önizleme göster</label>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="auto_print" name="auto_print" <?= $settings['auto_print'] ? 'checked' : '' ?>>
                                    <label class="custom-control-label" for="auto_print">Önizlemeden sonra otomatik yazdır</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- SAĞ KOLON - ÖNİZLEME -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-eye"></i> Canlı Önizleme</h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" id="refresh-preview">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body text-center">
                            <div class="preview-container">
                                <div id="label-preview" class="label-preview">
                                    <div class="label-content template-<?= $settings['etiket_sablonu'] ?>">
                                        <div class="product-name"><?= htmlspecialchars($preview_product['ad']) ?></div>
                                        
                                        <div class="bottom-section">
                                            <div class="barcode-area">
                                                <?php 
                                                $code_image = './?page=extensions&ext=tag_management&action=ajax&op=barcode_image&code=' . 
                                                            urlencode($preview_product['barkod']) . 
                                                            '&type=' . $settings['default_code_type'];
                                                ?>
                                                <img src="<?= $code_image ?>" alt="Barkod" class="barcode-img" id="preview-barcode">
                                            </div>
                                            
                                            <div class="price-section">
                                                <div class="price"><?= number_format($preview_product['satis_fiyati'], 2) ?> ₺</div>
                                                <div class="barcode-number"><?= formatBarcodeWithBold($preview_product['barkod']) ?></div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="side-text side-left"><?= htmlspecialchars($settings['firma_adi']) ?></div>
                                    <div class="side-text side-right"><?= htmlspecialchars($settings['firma_adi']) ?></div>
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <small class="text-muted">
                                    Boyut: <span id="preview-dimensions"><?= $settings['etiket_genislik'] ?>mm × <?= $settings['etiket_yukseklik'] ?>mm</span>
                                    <br>Kenar Boşluğu: <span id="preview-padding"><?= $settings['kenar_boslugu'] ?>mm</span>
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- TEST YAZDIRMA -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-vial"></i> Test İşlemleri</h3>
                        </div>
                        <div class="card-body">
                            <div class="btn-group btn-block">
                                <button type="button" class="btn btn-info" id="test-print-btn">
                                    <i class="fas fa-print"></i> Test Yazdırması
                                </button>
                                <button type="button" class="btn btn-success" id="preview-print-btn">
                                    <i class="fas fa-eye"></i> Tam Önizleme
                                </button>
                            </div>
                            
                            <div id="test-result" class="mt-3" style="display: none;">
                                <!-- Test sonuçları burada görünecek -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- KAYDET BUTONU -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-footer">
                            <div class="d-flex justify-content-between">
                                <a href="./?page=extensions&ext=tag_management" class="btn btn-default">
                                    <i class="fas fa-arrow-left"></i> Geri Dön
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Ayarları Kaydet
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</section>

<style>
/* Single print CSS'inden uyarlanmış önizleme stilleri */
.preview-container {
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
    border: 2px dashed #dee2e6;
    margin: 10px 0;
    display: flex;
    justify-content: center;
}

.label-preview {
    width: <?= $settings['etiket_genislik'] ?>mm;
    height: <?= $settings['etiket_yukseklik'] ?>mm;
    margin: 0 auto;
    background: white;
    padding: <?= $settings['kenar_boslugu'] ?>mm;
    box-sizing: border-box;
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    border-radius: 4px;
    position: relative;
    overflow: visible;
    transition: all 0.3s ease;
}

.label-content {
    width: <?= $settings['etiket_genislik'] - ($settings['kenar_boslugu'] * 2) ?>mm;
    height: <?= $settings['etiket_yukseklik'] - ($settings['kenar_boslugu'] * 2) ?>mm;
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
    padding: 1mm;
    box-sizing: border-box;
    font-family: Arial, sans-serif;
}

/* Ürün adı */
.product-name {
    font-size: 10pt;
    font-weight: bold;
    text-align: center;
    padding: 0px 10px;
    line-height: 1.2;
    max-height: 2.4em;
    overflow: hidden;
    flex-shrink: 0;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    white-space: normal;
}

/* Alt kısım */
.bottom-section {
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: flex-start;
    margin-top: auto;
    height: 25mm;
}

.barcode-area {
    width: 45%;
    display: flex;
    justify-content: flex-end;
    align-items: center;
}

.barcode-img {
    width: 18mm;
    height: 18mm;
    border: 2px solid black;
    margin: 0;
}

.price-section {
    width: 50%;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
}

.price {
    font-size: 24pt;
    font-weight: bold;
    text-align: center;
    color: #000;
    margin: 0;
}

.barcode-number {
    font-size: 8pt;
    font-weight: normal;
    text-align: center;
    color: #333;
    margin-top: 1px;
    line-height: 1;
}

.barcode-number .last-digits {
    font-weight: 600;
    color: #222;
}

/* Yan yazılar */
.side-text {
    position: absolute;
    font-size: 10pt;
    font-weight: bold;
    color: #333;
    writing-mode: vertical-lr;
    text-orientation: mixed;
    background: white;
    border-left: 1px solid black;
    border-right: 1px solid black;
    display: flex;
    align-items: center;
    justify-content: center;
    padding-right: 2px;
    height: 150%;
}

.side-left {
    left: 0.5mm;
    top: 50%;
    transform: translateY(-50%);
}

.side-right {
    right: 0.5mm;
    top: 50%;
    transform: translateY(-50%);
}

/* Template 2 - Yatay Yerleşim */
.template-2 .label-content {
    flex-direction: row;
    flex-wrap: wrap;
}

.template-2 .product-name {
    width: 100%;
    margin-bottom: 2mm;
}

.template-2 .bottom-section {
    height: auto;
    width: 100%;
}

.template-2 .barcode-area {
    width: 40%;
    justify-content: center;
}

.template-2 .price-section {
    width: 60%;
}

/* Animasyonlar */
.label-preview.updating {
    transform: scale(1.02);
    box-shadow: 0 6px 20px rgba(0,123,255,0.3);
}

@keyframes pulse {
    0% { box-shadow: 0 4px 8px rgba(0,0,0,0.15); }
    50% { box-shadow: 0 6px 20px rgba(0,123,255,0.4); }
    100% { box-shadow: 0 4px 8px rgba(0,0,0,0.15); }
}

.label-preview.pulse {
    animation: pulse 1s ease-in-out;
}

/* Test sonuçları */
#test-result {
    padding: 10px;
    border-radius: 4px;
}

#test-result.success {
    background-color: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

#test-result.error {
    background-color: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}

/* Responsive */
@media (max-width: 768px) {
    .label-preview {
        transform: scale(0.8);
    }
    
    .preview-container {
        padding: 10px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Önizleme elemanları
    const labelPreview = document.getElementById('label-preview');
    const previewBarcode = document.getElementById('preview-barcode');
    const previewDimensions = document.getElementById('preview-dimensions');
    const previewPadding = document.getElementById('preview-padding');
    
    // Form elemanları
    const previewTriggers = document.querySelectorAll('.preview-trigger');
    const printerType = document.getElementById('printer_type');
    const networkSettings = document.getElementById('network-printer-settings');
    const thermalSettings = document.getElementById('thermal-printer-settings');
    const testPrintBtn = document.getElementById('test-print-btn');
    const previewPrintBtn = document.getElementById('preview-print-btn');
    const refreshBtn = document.getElementById('refresh-preview');
    const testResult = document.getElementById('test-result');
    
    // Önizleme güncelleme fonksiyonu
    function updatePreview() {
        // Animasyon ekle
        labelPreview.classList.add('updating');
        
        // AJAX ile önizleme iste
        const xhr = new XMLHttpRequest();
        xhr.open('POST', './?page=extensions&ext=tag_management&action=settings&ajax=1', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        
                        if (response.success) {
                            // HTML içeriğini güncelle
                            labelPreview.innerHTML = response.html;
                            
                            // Boyutları güncelle
                            labelPreview.style.width = response.style.width;
                            labelPreview.style.height = response.style.height;
                            labelPreview.style.padding = response.style.padding;
                            
                            // Boyut bilgilerini güncelle
                            previewDimensions.textContent = response.dimensions;
                            previewPadding.textContent = response.padding;
                            
                            // Pulse efekti
                            labelPreview.classList.add('pulse');
                            setTimeout(() => labelPreview.classList.remove('pulse'), 1000);
                        } else {
                            console.error('Önizleme hatası:', response.error);
                        }
                    } catch (e) {
                        console.error('Önizleme yanıtı işlenirken hata:', e);
                    }
                } else {
                    console.error('Sunucu hatası:', xhr.status);
                }
                
                // Animasyonu kaldır
                setTimeout(() => labelPreview.classList.remove('updating'), 300);
            }
        };
        
        // Formdaki tüm değerleri al
        const formData = new FormData(document.getElementById('settings-form'));
        formData.append('action', 'update_preview');
        
        // FormData'yı URLSearchParams'a dönüştür
        const params = new URLSearchParams();
        for (const pair of formData.entries()) {
            params.append(pair[0], pair[1]);
        }
        
        xhr.send(params.toString());
    }
    
    // Yazıcı tipi değişikliği
    function updatePrinterSettings() {
        const type = printerType.value;
        
        if (type === 'browser') {
            networkSettings.style.display = 'none';
            thermalSettings.style.display = 'none';
        } else if (type === 'thermal') {
            networkSettings.style.display = 'block';
            thermalSettings.style.display = 'block';
        } else if (type === 'escpos') {
            networkSettings.style.display = 'block';
            thermalSettings.style.display = 'none';
        }
    }
    
    // Test sonucu gösterme
    function showTestResult(success, message) {
        testResult.style.display = 'block';
        testResult.className = success ? 'success' : 'error';
        testResult.innerHTML = '<i class="fas fa-' + (success ? 'check' : 'times') + '"></i> ' + message;
        
        setTimeout(() => {
            testResult.style.display = 'none';
        }, 5000);
    }
    
    // Test yazdırma
    function testPrint() {
        const type = printerType.value;
        const ip = document.getElementById('yazici_ip').value;
        const port = document.getElementById('yazici_port').value;
        const language = document.getElementById('printer_language')?.value || 'ZPL';
        
        testPrintBtn.disabled = true;
        testPrintBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Test Ediliyor...';
        
        if (type === 'browser') {
            // Tarayıcı yazdırma
            const testWindow = window.open('./?page=extensions&ext=tag_management&action=print_test', '_blank');
            showTestResult(true, 'Test yazdırma sayfası açıldı.');
            
            testPrintBtn.disabled = false;
            testPrintBtn.innerHTML = '<i class="fas fa-print"></i> Test Yazdırması';
        } else {
            // Ağ yazıcısı
            if (!ip.trim()) {
                showTestResult(false, 'Yazıcı IP adresi gerekli!');
                testPrintBtn.disabled = false;
                testPrintBtn.innerHTML = '<i class="fas fa-print"></i> Test Yazdırması';
                return;
            }
            
            // AJAX ile test et
            const xhr = new XMLHttpRequest();
            xhr.open('POST', './?page=extensions&ext=tag_management&action=settings&ajax=1', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    testPrintBtn.disabled = false;
                    testPrintBtn.innerHTML = '<i class="fas fa-print"></i> Test Yazdırması';
                    
                    if (xhr.status === 200) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            showTestResult(response.success, response.message || response.error);
                            
                            if (response.success && response.url) {
                                window.open(response.url, '_blank');
                            }
                        } catch (e) {
                            showTestResult(false, 'Yanıt işlenirken hata oluştu.');
                        }
                    } else {
                        showTestResult(false, 'Sunucu hatası: ' + xhr.status);
                    }
                }
            };
            
            const params = 'action=test_print&printer_type=' + encodeURIComponent(type) + 
                          '&printer_ip=' + encodeURIComponent(ip) + 
                          '&printer_port=' + encodeURIComponent(port) + 
                          '&printer_language=' + encodeURIComponent(language);
            xhr.send(params);
        }
    }
    
    // Tam önizleme
    function fullPreview() {
        const url = './?page=extensions&ext=tag_management&action=print_test&preview=1';
        window.open(url, '_blank', 'width=800,height=600,scrollbars=yes');
    }
    
    // Event listeners
    previewTriggers.forEach(element => {
        element.addEventListener('input', updatePreview);
        element.addEventListener('change', updatePreview);
    });
    
    printerType.addEventListener('change', updatePrinterSettings);
    testPrintBtn.addEventListener('click', testPrint);
    previewPrintBtn.addEventListener('click', fullPreview);
    refreshBtn.addEventListener('click', updatePreview);
    
    // Form submit öncesi validation
    document.getElementById('settings-form').addEventListener('submit', function(e) {
        const genislik = parseInt(document.getElementById('etiket_genislik').value);
        const yukseklik = parseInt(document.getElementById('etiket_yukseklik').value);
        const bosluk = parseFloat(document.getElementById('kenar_boslugu').value);
        
        if (genislik < 50 || genislik > 200) {
            e.preventDefault();
            alert('Etiket genişliği 50-200mm arasında olmalıdır.');
            return false;
        }
        
        if (yukseklik < 20 || yukseklik > 150) {
            e.preventDefault();
            alert('Etiket yüksekliği 20-150mm arasında olmalıdır.');
            return false;
        }
        
        if (bosluk < 0 || bosluk > 10) {
            e.preventDefault();
            alert('Kenar boşluğu 0-10mm arasında olmalıdır.');
            return false;
        }
        
        // Ağ yazıcısı seçilmişse IP kontrolü
        const type = printerType.value;
        if (type !== 'browser') {
            const ip = document.getElementById('yazici_ip').value.trim();
            if (!ip) {
                e.preventDefault();
                alert('Ağ yazıcısı için IP adresi gereklidir.');
                return false;
            }
            
            // Basit IP formatı kontrolü
            const ipRegex = /^(\d{1,3}\.){3}\d{1,3}$/;
            if (!ipRegex.test(ip)) {
                e.preventDefault();
                alert('Geçerli bir IP adresi girin.');
                return false;
            }
        }
        
        return true;
    });
    
    // Sayfa yüklendiğinde yazıcı ayarlarını güncelle
    updatePrinterSettings();
    
    // Klavye kısayolları
    document.addEventListener('keydown', function(e) {
        // Ctrl+S ile kaydet
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            document.getElementById('settings-form').submit();
        }
        
        // F5 ile önizleme yenile
        if (e.key === 'F5') {
            e.preventDefault();
            updatePreview();
        }
    });
    
    console.log('Barkod ayarlar sayfası yüklendi. Önizleme aktif.');
});
</script>