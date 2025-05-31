<?php
require_once 'session_manager.php'; // Otomatik eklendi
// İlk olarak, SESSION'ı başlatalım
secure_session_start();

// ÜRÜN RESMİ YÜKLEME İŞLEMİ
if (isset($_POST['submit_image']) && $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['images']['name'][0])) {
    // Ana dizin (root) yolunu al
$root_path = dirname(dirname(dirname(__DIR__))); // incipos dizinine çıkmak için
    $upload_dir = $root_path . '/files/img/';
    
    // Veritabanı bağlantısını yükle
    require_once '../../db_connection.php';
    
    // Resim kalitesi ayarı
    $quality = isset($_POST['image_quality']) ? (int)$_POST['image_quality'] : 80;
    
    // Resim yükleme klasörü
    $target_dir = isset($_POST['upload_folder']) ? $_POST['upload_folder'] : 'products';
    $full_target_dir = $upload_dir . $target_dir . '/';
    
    // Klasör yoksa oluştur
    if (!file_exists($full_target_dir)) {
        mkdir($full_target_dir, 0755, true);
    }
    
    // Dosya sayısını kontrol et
    $total_files = count($_FILES['images']['name']);
    $uploaded_count = 0;
    $error_count = 0;
    
    for ($i = 0; $i < $total_files; $i++) {
        if ($_FILES['images']['error'][$i] == 0) {
            $temp_file = $_FILES['images']['tmp_name'][$i];
            $original_filename = $_FILES['images']['name'][$i];
            $file_ext = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
            
            // İzin verilen uzantılar
            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (in_array($file_ext, $allowed_ext)) {
                // Dosya adından barkod/kod bilgisini çıkar - dosya adı direkt olarak barkod olmalı
                $filename_without_ext = pathinfo($original_filename, PATHINFO_FILENAME);
                $barkod = $filename_without_ext;  // Dosya adı barkod olarak kullanılacak
                
                // Veritabanında bu barkod/kod ile ürün ara
                $search_query = "SELECT id, resim_yolu FROM urun_stok WHERE barkod = ? OR kod = ? LIMIT 1";
                $search_stmt = $conn->prepare($search_query);
                $search_stmt->execute([$barkod, $barkod]);
                $found_product = $search_stmt->fetch(PDO::FETCH_ASSOC);
                
				$target_file = $full_target_dir . $barkod . '.jpg';
				if (file_exists($target_file)) {
					$_SESSION['error_message'] = "Hata: Bu ürüne ($barkod) ait zaten bir resim mevcut. Önce mevcut resmi silmelisiniz.";
					$error_count++;
					continue;
				}
                
                // Ürünün zaten bir resmi var mı kontrol et
                if (!empty($found_product['resim_yolu'])) {
                    $_SESSION['error_message'] = "Hata: Bu ürüne ($barkod) ait zaten bir resim mevcut. Önce mevcut resmi silmelisiniz.";
                    $error_count++;
                    continue;
                }
                
                // Resim işleme ve sıkıştırma
                $image_info = getimagesize($temp_file);
                
                if ($image_info !== false) {
                    switch ($image_info[2]) {
                        case IMAGETYPE_JPEG:
                            $image = imagecreatefromjpeg($temp_file);
                            break;
                        case IMAGETYPE_PNG:
                            $image = imagecreatefrompng($temp_file);
                            imagealphablending($image, true);
                            imagesavealpha($image, true);
                            break;
                        case IMAGETYPE_GIF:
                            $image = imagecreatefromgif($temp_file);
                            break;
                        case IMAGETYPE_WEBP:
                            $image = imagecreatefromwebp($temp_file);
                            break;
                        default:
                            $image = false;
                    }
                    
                    if ($image !== false) {
                        // Yeniden boyutlandırma (opsiyonel)
                        $max_width = isset($_POST['max_width']) ? (int)$_POST['max_width'] : 1200;
                        $max_height = isset($_POST['max_height']) ? (int)$_POST['max_height'] : 1200;
                        
                        $width = imagesx($image);
                        $height = imagesy($image);
                        
                        // Eğer boyut sınırlarını aşıyorsa yeniden boyutlandır
                        if ($width > $max_width || $height > $max_height) {
                            $ratio = $width / $height;
                            
                            if ($width > $height) {
                                $new_width = $max_width;
                                $new_height = $max_width / $ratio;
                            } else {
                                $new_height = $max_height;
                                $new_width = $max_height * $ratio;
                            }
                            
                            $new_image = imagecreatetruecolor($new_width, $new_height);
                            
                            // PNG için şeffaflığı koru (beyaz arka plan ile dönüştürme için)
                            if ($image_info[2] == IMAGETYPE_PNG) {
                                // Beyaz arka plan oluştur (şeffaflık yerine)
                                $white = imagecolorallocate($new_image, 255, 255, 255);
                                imagefilledrectangle($new_image, 0, 0, $new_width, $new_height, $white);
                            }
                            
                            imagecopyresampled($new_image, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
                            $image = $new_image;
                        }
                        
                        // Her zaman JPG olarak ve barkod.jpg olarak kaydet
                        $new_filename = $barkod . '.jpg';
                        $target_file = $full_target_dir . $new_filename;
                        
                        // JPG olarak kaydet
						if (imagejpeg($image, $target_file, $quality)) {
							imagedestroy($image);
							$uploaded_count++;
							
							// Resim yolunu veritabanında güncelle
							$relative_path = 'files/img/' . $target_dir . '/' . $new_filename;
							
							$update_query = "UPDATE urun_stok SET resim_yolu = ? WHERE id = ?";
							$update_stmt = $conn->prepare($update_query);
							$update_stmt->execute([$relative_path, $found_product['id']]);
                            
                            if ($uploaded_count == 1) {
                                $_SESSION['success_message'] = "Resim başarıyla yüklendi ve ürün ($barkod) ile ilişkilendirildi.";
                            } else {
                                $_SESSION['success_message'] = "$uploaded_count resim başarıyla yüklendi ve ilgili ürünlerle ilişkilendirildi.";
                            }
                        } else {
                            $_SESSION['error_message'] = "Resim kaydedilirken bir hata oluştu.";
                            $error_count++;
                        }
                    } else {
                        $_SESSION['error_message'] = "Resim işlenemedi.";
                        $error_count++;
                    }
                } else {
                    $_SESSION['error_message'] = "Geçersiz resim formatı: " . $original_filename;
                    $error_count++;
                }
            } else {
                $_SESSION['error_message'] = "İzin verilmeyen dosya türü: " . $original_filename;
                $error_count++;
            }
        } else {
            $_SESSION['error_message'] = "Dosya yüklenirken hata oluştu: " . $_FILES['images']['error'][$i];
            $error_count++;
        }
    }
    
    // Hata ve başarı mesajlarını birleştir
    if ($error_count > 0 && $uploaded_count > 0) {
        $_SESSION['error_message'] .= " Ancak $uploaded_count resim başarıyla yüklendi.";
    }
    
    // Yönlendirmeyi yap
    $redirect_url = "file_management.php";
    if (isset($_GET['path'])) {
        $redirect_url .= "?path=" . urlencode($_GET['path']);
    }
    
    header("Location: $redirect_url");
    exit;
}

// PDF YÜKLEME İŞLEMİ
if (isset($_POST['submit_pdf'])) {
    // Ana dizin (root) yolunu al
$root_path = dirname(dirname(dirname(__DIR__))); // incipos dizinine çıkmak için
    $pdf_dir = $root_path . '/files/pdf/';
    
    $pdf_category = isset($_POST['pdf_category']) ? $_POST['pdf_category'] : 'invoice';
    $full_pdf_dir = $pdf_dir . $pdf_category . '/';
    
    // Klasör yoksa oluştur
    if (!file_exists($full_pdf_dir)) {
        mkdir($full_pdf_dir, 0755, true);
    }
    
    // Dosyayı yükle
    $pdf_file = $_FILES['pdf_file'];
    
    if ($pdf_file['error'] == 0) {
        $pdf_filename = basename($pdf_file['name']);
        $pdf_ext = strtolower(pathinfo($pdf_filename, PATHINFO_EXTENSION));
        
        // Sadece PDF dosyalarına izin ver
        if ($pdf_ext == 'pdf') {
            // Orijinal dosya adını koru
            $target_pdf = $full_pdf_dir . $pdf_filename;
            
            if (move_uploaded_file($pdf_file['tmp_name'], $target_pdf)) {
                // Mesajı session'a kaydet
                $_SESSION['success_message'] = "PDF dosyası başarıyla yüklendi.";
            } else {
                $_SESSION['error_message'] = "PDF dosyası yüklenirken bir hata oluştu.";
            }
        } else {
            $_SESSION['error_message'] = "Sadece PDF dosyaları yüklenebilir.";
        }
    } else {
        $_SESSION['error_message'] = "PDF dosyası yüklenirken bir hata oluştu: " . $pdf_file['error'];
    }
    
    // Yönlendirmeyi yap
    $redirect_url = "file_management.php";
    if (isset($_GET['path'])) {
        $redirect_url .= "?path=" . urlencode($_GET['path']);
    }
    
    header("Location: $redirect_url");
    exit;
}

// Sayfa yüklenme süresini hesapla
$start_time = microtime(true);

// Ana dizin (root) yolunu al
$root_path = dirname(dirname(dirname(__DIR__))); // incipos dizinine çıkmak için
$upload_dir = $root_path . '/files/img/';
$pdf_dir = $root_path . '/files/pdf/';
$temp_dir = $root_path . '/files/temp/';

// Klasör yapısını kontrol et ve gerekirse oluştur
$directories = [$upload_dir, $pdf_dir, $temp_dir];
foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Hata ve bilgilendirme mesajları
$message = '';
$error = '';

// Session'dan mesajları al
if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    unset($_SESSION['success_message']); // Mesajı session'dan kaldır
}

if (isset($_SESSION['error_message'])) {
    $error = $_SESSION['error_message'];
    unset($_SESSION['error_message']); // Mesajı session'dan kaldır
}

// Header'ı dahil et - mesajları okuduktan sonra
include '../../header.php';
require_once '../../db_connection.php';

// Yetki kontrolü
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: ../../index.php");
    exit;
}

// Dosya ve klasörleri listele
function listFilesAndFolders($dir, $relativePath = '') {
    $result = [];
    $items = scandir($dir);
    
    foreach ($items as $item) {
        if ($item != '.' && $item != '..' && $item != '.htaccess') {
            $path = $dir . '/' . $item;
            $relPath = $relativePath . '/' . $item;
            // Başındaki / işaretini kaldır
            $relPath = ltrim($relPath, '/');
            
            if (is_dir($path)) {
                $result[] = [
                    'name' => $item,
                    'path' => $relPath,
                    'type' => 'folder',
                    'size' => getDirSize($path),
                    'modified' => filemtime($path),
                    'items' => count(glob("$path/*")) - 2  // . ve .. hariç
                ];
            } else {
                $result[] = [
                    'name' => $item,
                    'path' => $relPath,
                    'type' => 'file',
                    'size' => filesize($path),
                    'modified' => filemtime($path),
                    'ext' => strtolower(pathinfo($item, PATHINFO_EXTENSION))
                ];
            }
        }
    }
    
    // Klasörler önce, sonra dosyalar olacak şekilde sırala
    usort($result, function($a, $b) {
        if ($a['type'] === $b['type']) {
            return strcmp($a['name'], $b['name']);
        }
        return $a['type'] === 'folder' ? -1 : 1;
    });
    
    return $result;
}

function getDirSize($dir) {
    $size = 0;
    foreach (glob(rtrim($dir, '/').'/*', GLOB_NOSORT) as $each) {
        $size += is_file($each) ? filesize($each) : getDirSize($each);
    }
    return $size;
}

function formatSize($size) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($size >= 1024 && $i < count($units) - 1) {
        $size /= 1024;
        $i++;
    }
    return round($size, 2) . ' ' . $units[$i];
}

// İlgili klasörün içeriğini listele
$current_path = isset($_GET['path']) ? $_GET['path'] : '';
$current_dir = '';

if (empty($current_path)) {
    // Eğer path parametresi yoksa veya boşsa, 'files' klasörünü aç
    $current_dir = $root_path . '/files';
    $current_path = 'files';
} elseif ($current_path === 'img') {
    $current_dir = $upload_dir;
    $current_path = 'img';
} elseif ($current_path === 'pdf') {
    $current_dir = $pdf_dir;
} elseif (strpos($current_path, 'img/') === 0) {
    $subpath = substr($current_path, 4);
    $current_dir = $upload_dir . $subpath;
} elseif (strpos($current_path, 'pdf/') === 0) {
    $subpath = substr($current_path, 4);
    $current_dir = $pdf_dir . $subpath;
} elseif ($current_path === 'files') {
    $current_dir = $root_path . '/files';
} elseif (strpos($current_path, 'files/') === 0) {
    // 'files/' ile başlayan path için doğru dizini oluştur
    $subpath = substr($current_path, 6); // 'files/' kısmını çıkar
    $current_dir = $root_path . '/files/' . $subpath;
} else {
    // Eğer path parametresi geçersizse, 'files' klasörünü aç
    $current_dir = $root_path . '/files';
    $current_path = 'files';
}

// Eğer klasör yoksa oluştur
if (!file_exists($current_dir)) {
    mkdir($current_dir, 0755, true);
}

// Dosya ve klasörleri listele
$files_and_folders = listFilesAndFolders($current_dir, $current_path);

// Ürünleri listele (resim yükleme için dropdown)
$products_query = "SELECT id, barkod, kod, ad FROM urun_stok ORDER BY ad";
$products = $conn->query($products_query)->fetchAll(PDO::FETCH_ASSOC);

// Mevcut klasörleri listele
$img_folders = [];
$pdf_folders = [];

// Resim klasörlerini listele
$img_items = scandir($upload_dir);
foreach ($img_items as $item) {
    if ($item != '.' && $item != '..' && $item != '.htaccess' && is_dir($upload_dir . '/' . $item)) {
        $img_folders[] = $item;
    }
}

// PDF klasörlerini listele
$pdf_items = scandir($pdf_dir);
foreach ($pdf_items as $item) {
    if ($item != '.' && $item != '..' && $item != '.htaccess' && is_dir($pdf_dir . '/' . $item)) {
        $pdf_folders[] = $item;
    }
}

// Klasör yoksa varsayılan klasörleri oluştur
if (empty($img_folders)) {
    $default_img_folders = ['products', 'banners', 'logos', 'gallery'];
    foreach ($default_img_folders as $folder) {
        $folder_path = $upload_dir . '/' . $folder;
        if (!file_exists($folder_path)) {
            mkdir($folder_path, 0755, true);
        }
        $img_folders[] = $folder;
    }
}

if (empty($pdf_folders)) {
    $default_pdf_folders = ['invoice', 'orders', 'catalogs', 'documents'];
    foreach ($default_pdf_folders as $folder) {
        $folder_path = $pdf_dir . '/' . $folder;
        if (!file_exists($folder_path)) {
            mkdir($folder_path, 0755, true);
        }
        $pdf_folders[] = $folder;
    }
}

// breadcrumb oluştur
$breadcrumb_parts = [];
if (!empty($current_path)) {
    $paths = explode('/', $current_path);
    $cumulative_path = '';
    
    foreach ($paths as $index => $part) {
        $cumulative_path .= ($index > 0 ? '/' : '') . $part;
        $breadcrumb_parts[] = [
            'name' => $part,
            'path' => $cumulative_path
        ];
    }
}

// Sayfa yüklenme süresini hesapla
$end_time = microtime(true);
$execution_time = round($end_time - $start_time, 3);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dosya Yönetimi - İnciPOS</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .file-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 1rem;
        }
        .file-item {
            position: relative;  /* Checkbox pozisyonu için */
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            padding: 0.5rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        .file-item:hover {
            background-color: #f3f4f6;
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .file-icon {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }
        .folder-icon { color: #f59e0b; }
        .image-icon { color: #3b82f6; }
        .pdf-icon { color: #ef4444; }
        .file-name {
            font-size: 0.875rem;
            text-align: center;
            word-break: break-word;
            max-width: 100%;
        }
        .breadcrumb {
            display: flex;
            flex-wrap: wrap;
            padding: 0.5rem 1rem;
            margin-bottom: 1rem;
            background-color: #f9fafb;
            border-radius: 0.375rem;
        }
        .breadcrumb-item a {
            color: #6b7280;
            text-decoration: none;
        }
        .breadcrumb-item a:hover {
            color: #4b5563;
            text-decoration: underline;
        }
        .breadcrumb-item.active {
            color: #374151;
            font-weight: 500;
        }
        .breadcrumb-separator {
            padding: 0 0.5rem;
            color: #9ca3af;
        }
        
        /* Seçili dosyalar için stil */
        .file-item.selected {
            background-color: #e5f1ff;
            border-color: #3b82f6;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold">Dosya Yönetimi</h1>
                <p class="text-gray-600">Resim ve PDF dosyalarını yükleyin ve yönetin</p>
            </div>
            <a href="../../admin_dashboard.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                Dashboard'a Dön
            </a>
        </div>

        <?php if (!empty($message)): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                <p><?php echo $message; ?></p>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                <p><?php echo $error; ?></p>
            </div>
        <?php endif; ?>

        <!-- Tab Menüsü -->
        <div class="mb-6">
            <div class="border-b border-gray-200">
                <ul class="flex -mb-px">
                    <li class="mr-1">
                        <a href="#image-upload" class="inline-block py-2 px-4 text-sm font-medium text-center text-blue-600 border-b-2 border-blue-600 rounded-t-lg active" aria-current="page" onclick="showTab('image-upload')">
                            <i class="fas fa-image mr-2"></i>Resim Yükleme
                        </a>
                    </li>
                    <li class="mr-1">
                        <a href="#pdf-upload" class="inline-block py-2 px-4 text-sm font-medium text-center text-gray-500 border-b-2 border-transparent rounded-t-lg hover:text-gray-600 hover:border-gray-300" onclick="showTab('pdf-upload')">
                            <i class="fas fa-file-pdf mr-2"></i>PDF Yükleme
                        </a>
                    </li>
                    <li class="mr-1">
                        <a href="#file-browser" class="inline-block py-2 px-4 text-sm font-medium text-center text-gray-500 border-b-2 border-transparent rounded-t-lg hover:text-gray-600 hover:border-gray-300" onclick="showTab('file-browser')">
                            <i class="fas fa-folder-open mr-2"></i>Dosya Gezgini
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Tab İçerikleri -->
        <div class="tab-content active" id="image-upload-content">
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold mb-4">Ürün Resmi Yükleme</h2>
                <div class="bg-blue-50 border-l-4 border-blue-500 text-blue-700 p-4 mb-6" role="alert">
                    <p><i class="fas fa-info-circle mr-2"></i> Önemli: Her ürünün sadece bir resmi olabilir. Dosya ismi ürünün barkodu olmalıdır (örn: 8697236101544.jpg). Yüklediğiniz resimler otomatik olarak JPG formatına dönüştürülecektir. Eğer bir ürüne ait zaten bir resim varsa, yeni resim yüklemek için önce mevcut resmi silmelisiniz.</p>
                </div>
                <form action="" method="POST" enctype="multipart/form-data">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="images" class="block text-sm font-medium text-gray-700 mb-2">Resimler</label>
                            <input type="file" id="images" name="images[]" multiple accept="image/*" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" required>
                            <p class="mt-1 text-sm text-gray-500">JPG, PNG, GIF veya WEBP formatında. En fazla 10MB.</p>
                        </div>
                        
                        <div>
                            <label for="upload_folder" class="block text-sm font-medium text-gray-700 mb-2">Yükleme Klasörü</label>
                            <select id="upload_folder" name="upload_folder" class="block w-full mt-1 rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200" required>
                                <?php foreach ($img_folders as $folder): ?>
                                    <option value="<?php echo $folder; ?>" <?php echo $folder === 'products' ? 'selected' : ''; ?>><?php echo $folder; ?></option>
                                <?php endforeach; ?>
                                <option value="new">Yeni Klasör Oluştur...</option>
                            </select>
                        </div>
                        
                        <div id="new_folder_container" class="hidden">
                            <label for="new_folder" class="block text-sm font-medium text-gray-700 mb-2">Yeni Klasör Adı</label>
                            <input type="text" id="new_folder" name="new_folder" class="block w-full mt-1 rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200">
                            <p class="mt-1 text-sm text-gray-500">Sadece harf, sayı ve alt çizgi kullanın.</p>
                        </div>
                        
                        <div>
                            <label for="image_quality" class="block text-sm font-medium text-gray-700 mb-2">Resim Kalitesi</label>
                            <div class="flex items-center">
                                <input type="range" id="image_quality" name="image_quality" min="10" max="100" value="30" class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer">
                                <span id="quality_value" class="ml-2 text-sm font-medium">30%</span>
                            </div>
                            <p class="mt-1 text-sm text-gray-500">Düşük kalite=küçük dosya boyutu, yüksek kalite=büyük dosya boyutu</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Resim Boyutlandırma</label>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label for="max_width" class="block text-xs text-gray-500">Maksimum Genişlik (px)</label>
                                    <input type="number" id="max_width" name="max_width" value="1200" min="100" max="5000" class="block w-full mt-1 rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200">
                                </div>
                                <div>
                                    <label for="max_height" class="block text-xs text-gray-500">Maksimum Yükseklik (px)</label>
                                    <input type="number" id="max_height" name="max_height" value="1200" min="100" max="5000" class="block w-full mt-1 rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200">
                                </div>
                            </div>
                            <p class="mt-1 text-sm text-gray-500">Boyutları aşan resimler otomatik küçültülür (oran korunur)</p>
                        </div>
                    </div>
                    
                    <div class="mt-6">
                        <button type="submit" name="submit_image" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <i class="fas fa-upload mr-2"></i>Resimleri Yükle
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="tab-content hidden" id="pdf-upload-content">
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold mb-4">PDF Yükleme</h2>
                <form action="" method="POST" enctype="multipart/form-data">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="pdf_file" class="block text-sm font-medium text-gray-700 mb-2">PDF Dosyası</label>
                            <input type="file" id="pdf_file" name="pdf_file" accept="application/pdf" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-red-50 file:text-red-700 hover:file:bg-red-100" required>
                            <p class="mt-1 text-sm text-gray-500">Sadece PDF formatında. En fazla 10MB.</p>
                        </div>
                        
                        <div>
                            <label for="pdf_category" class="block text-sm font-medium text-gray-700 mb-2">Kategori</label>
                            <select id="pdf_category" name="pdf_category" class="block w-full mt-1 rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200" required>
                                <?php foreach ($pdf_folders as $folder): ?>
                                    <option value="<?php echo $folder; ?>" <?php echo $folder === 'invoice' ? 'selected' : ''; ?>><?php echo $folder; ?></option>
                                <?php endforeach; ?>
                                <option value="new">Yeni Kategori Oluştur...</option>
                            </select>
                        </div>
                        
                        <div id="new_pdf_category_container" class="hidden">
                            <label for="new_pdf_category" class="block text-sm font-medium text-gray-700 mb-2">Yeni Kategori Adı</label>
                            <input type="text" id="new_pdf_category" name="new_pdf_category" class="block w-full mt-1 rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200">
                            <p class="mt-1 text-sm text-gray-500">Sadece harf, sayı ve alt çizgi kullanın.</p>
                        </div>
                    </div>
                    
                    <div class="mt-6">
                        <button type="submit" name="submit_pdf" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                            <i class="fas fa-file-upload mr-2"></i>PDF Yükle
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="tab-content hidden" id="file-browser-content">
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold mb-4">Dosya Gezgini</h2>
                
                <!-- Klasör Bilgileri Alanı -->
                <div id="folderInfoContainer" class="mb-4">
                    <!-- Klasör bilgileri JavaScript ile doldurulacak -->
                </div>
                
                <!-- Breadcrumb -->
                <nav class="breadcrumb" aria-label="breadcrumb">
                    <ol class="flex flex-wrap">
                        <li class="breadcrumb-item">
                            <a href="?path=files">Ana Dizin</a>
                        </li>
                        
                        <?php foreach ($breadcrumb_parts as $index => $part): ?>
                            <li class="breadcrumb-separator">/</li>
                            <li class="breadcrumb-item <?php echo ($index == count($breadcrumb_parts) - 1) ? 'active' : ''; ?>">
                                <?php if ($index < count($breadcrumb_parts) - 1): ?>
                                    <a href="?path=<?php echo htmlspecialchars($part['path']); ?>"><?php echo htmlspecialchars($part['name']); ?></a>
                                <?php else: ?>
                                    <?php echo htmlspecialchars($part['name']); ?>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </nav>

                <!-- Toplu İşlem Butonları -->
                <div id="bulkActions" class="bg-gray-100 p-4 rounded-lg mb-6 hidden">
                    <div class="flex justify-between items-center">
                        <div>
                            <span class="text-sm font-medium text-gray-700">
                                <span id="selectedFileCount">0</span> dosya seçildi
                            </span>
                        </div>
                        <div class="flex space-x-2">
                            <button type="button" id="bulkDeleteBtn" class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded-md text-sm">
                                <i class="fas fa-trash-alt mr-1"></i> Seçili Dosyaları Sil
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Dosya Listesi -->
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold">Dosya Listesi</h3>
                    <div class="flex items-center">
                        <input type="checkbox" id="selectAllFiles" class="form-checkbox h-4 w-4 text-blue-600 rounded border-gray-300 mr-2">
                        <label for="selectAllFiles" class="text-sm text-gray-700">Tümünü Seç</label>
                    </div>
                </div>
                
                <div class="mt-6">
                    <?php if (empty($files_and_folders)): ?>
                        <div class="text-center py-8 text-gray-500">
                            <i class="fas fa-folder-open text-4xl mb-3"></i>
                            <p>Bu klasör boş</p>
                        </div>
                    <?php else: ?>
                        <div class="file-grid">
                            <?php foreach ($files_and_folders as $item): ?>
                                <?php if ($item['type'] === 'folder'): ?>
                                    <div class="file-item" onclick="location.href='?path=<?php echo htmlspecialchars($item['path']); ?>'">
                                        <div class="file-icon folder-icon">
                                            <i class="fas fa-folder"></i>
                                        </div>
                                        <div class="file-name"><?php echo htmlspecialchars($item['name']); ?></div>
                                        <div class="text-xs text-gray-500"><?php echo $item['items']; ?> öğe</div>
                                        <div class="text-xs text-gray-500"><?php echo formatSize($item['size']); ?></div>
                                    </div>
                                <?php else: ?>
                                    <?php 
                                    $icon_class = '';
                                    $preview_class = '';
                                    
                                    if (in_array($item['ext'], ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                                        $icon_class = 'image-icon';
                                        $icon = '<i class="fas fa-image"></i>';
                                        $preview_class = 'image-preview';
                                        $preview_path = '/incipos/files/img/' . substr($item['path'], 4);
                                    } elseif ($item['ext'] === 'pdf') {
                                        $icon_class = 'pdf-icon';
                                        $icon = '<i class="fas fa-file-pdf"></i>';
                                        $preview_class = 'pdf-preview';
                                        $preview_path = '/files/pdf/' . substr($item['path'], 4);
                                    } else {
                                        $icon_class = 'text-gray-500';
                                        $icon = '<i class="fas fa-file"></i>';
                                        $preview_class = '';
                                        $preview_path = '';
                                    }
                                    ?>
                                    
                                    <div class="file-item <?php echo $preview_class; ?>" data-path="<?php echo htmlspecialchars($preview_path); ?>">
                                        <!-- Dosya Seçim Kutusu -->
                                        <div class="absolute top-2 left-2">
                                            <input type="checkbox" 
                                                class="file-checkbox form-checkbox h-4 w-4 text-blue-600 rounded border-gray-300" 
                                                onclick="event.stopPropagation();" 
                                                value="<?php echo htmlspecialchars($preview_path); ?>">
                                        </div>
                                        
                                        <div class="file-icon <?php echo $icon_class; ?>">
                                            <?php echo $icon; ?>
                                        </div>
                                        <div class="file-name"><?php echo htmlspecialchars($item['name']); ?></div>
                                        <div class="text-xs text-gray-500"><?php echo formatSize($item['size']); ?></div>
                                        <div class="text-xs text-gray-500"><?php echo date('d.m.Y', $item['modified']); ?></div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Dosya Önizleme Modal -->
        <div id="previewModal"  class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center hidden z-50">
            <div class="bg-white rounded-lg shadow-lg w-full max-w-4xl">
                <div class="flex justify-between items-center px-6 py-4 border-b">
                    <h3 class="text-lg font-semibold" id="previewTitle">Dosya Önizleme</h3>
                    <button id="closePreviewBtn" class="text-gray-500 hover:text-gray-700">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <div id="previewContent" style="height:500px" class="p-6 flex justify-center">
                    <!-- Önizleme içeriği JavaScript ile eklenecek -->
                </div>
                <div class="px-6 py-4 border-t flex justify-between">
                    <button id="downloadFileBtn" class="inline-flex items-center px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600">
                        <i class="fas fa-download mr-2"></i>İndir
                    </button>
                    <button id="deleteFileBtn" class="inline-flex items-center px-4 py-2 bg-red-500 text-white rounded-md hover:bg-red-600">
                        <i class="fas fa-trash-alt mr-2"></i>Sil
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Sayfa özel JavaScript -->
    <script src="../../assets/js/file_management.js"></script>

<?php
// Footer'ı dahil et
include '../../footer.php';
?>