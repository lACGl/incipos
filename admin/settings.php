<?php
// Sayfa yüklenme süresini hesapla
$start_time = microtime(true);

// Header'ı dahil et
include 'header.php';
require_once 'db_connection.php';
require_once 'stock_functions.php';

// Kullanıcı giriş kontrolü
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: index.php");
    exit;
}

// Admin yetkisi kontrolü
$user_id = $_SESSION['user_id'];
$admin_check_query = "SELECT * FROM admin_user WHERE id = :id";
$stmt = $conn->prepare($admin_check_query);
$stmt->bindParam(':id', $user_id);
$stmt->execute();
$admin_user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin_user) {
    header("Location: admin_dashboard.php");
    exit;
}

// İşlem mesajları için değişkenler
$success_message = '';
$error_message = '';

// MAĞAZA İŞLEMLERİ
if (isset($_POST['add_store'])) {
    $store_name = $_POST['store_name'];
    $store_address = $_POST['store_address'];
    $store_phone = $_POST['store_phone'];
    $store_mobile = $_POST['store_mobile']; // Yeni eklenen cep telefonu
    
    try {
        $stmt = $conn->prepare("INSERT INTO magazalar (ad, adres, telefon, cep_telefon) VALUES (:ad, :adres, :telefon, :cep_telefon)");
        $stmt->bindParam(':ad', $store_name);
        $stmt->bindParam(':adres', $store_address);
        $stmt->bindParam(':telefon', $store_phone);
        $stmt->bindParam(':cep_telefon', $store_mobile); // Yeni eklenen cep telefonu
        $stmt->execute();
        
        $success_message = "Mağaza başarıyla eklendi.";
    } catch (PDOException $e) {
        $error_message = "Mağaza eklenirken hata oluştu: " . $e->getMessage();
    }
}

if (isset($_POST['update_store'])) {
    $store_id = $_POST['store_id'];
    $store_name = $_POST['store_name'];
    $store_address = $_POST['store_address'];
    $store_phone = $_POST['store_phone'];
    $store_mobile = $_POST['store_mobile']; // Yeni eklenen cep telefonu
    
    try {
        $stmt = $conn->prepare("UPDATE magazalar SET ad = :ad, adres = :adres, telefon = :telefon, cep_telefon = :cep_telefon WHERE id = :id");
        $stmt->bindParam(':ad', $store_name);
        $stmt->bindParam(':adres', $store_address);
        $stmt->bindParam(':telefon', $store_phone);
        $stmt->bindParam(':cep_telefon', $store_mobile); // Yeni eklenen cep telefonu
        $stmt->bindParam(':id', $store_id);
        $stmt->execute();
        
        $success_message = "Mağaza başarıyla güncellendi.";
    } catch (PDOException $e) {
        $error_message = "Mağaza güncellenirken hata oluştu: " . $e->getMessage();
    }
}

if (isset($_POST['delete_store'])) {
    $store_id = $_POST['store_id'];
    
    try {
        // İlişkili kayıtları kontrol et
        $check_query = "SELECT COUNT(*) as count FROM magaza_stok WHERE magaza_id = :id";
        $stmt = $conn->prepare($check_query);
        $stmt->bindParam(':id', $store_id);
        $stmt->execute();
        $has_records = $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
        
        if ($has_records) {
            $error_message = "Bu mağazaya ait stok kayıtları bulunduğu için silinemez!";
        } else {
            $stmt = $conn->prepare("DELETE FROM magazalar WHERE id = :id");
            $stmt->bindParam(':id', $store_id);
            $stmt->execute();
            
            $success_message = "Mağaza başarıyla silindi.";
        }
    } catch (PDOException $e) {
        $error_message = "Mağaza silinirken hata oluştu: " . $e->getMessage();
    }
}

// DEPO İŞLEMLERİ
if (isset($_POST['add_warehouse'])) {
    $warehouse_name = $_POST['warehouse_name'];
    $warehouse_code = $_POST['warehouse_code'];
    $warehouse_address = $_POST['warehouse_address'];
    $warehouse_phone = $_POST['warehouse_phone'];
    $warehouse_type = $_POST['warehouse_type'];
    
    try {
        $stmt = $conn->prepare("INSERT INTO depolar (ad, kod, adres, telefon, depo_tipi) VALUES (:ad, :kod, :adres, :telefon, :depo_tipi)");
        $stmt->bindParam(':ad', $warehouse_name);
        $stmt->bindParam(':kod', $warehouse_code);
        $stmt->bindParam(':adres', $warehouse_address);
        $stmt->bindParam(':telefon', $warehouse_phone);
        $stmt->bindParam(':depo_tipi', $warehouse_type);
        $stmt->execute();
        
        $success_message = "Depo başarıyla eklendi.";
    } catch (PDOException $e) {
        $error_message = "Depo eklenirken hata oluştu: " . $e->getMessage();
    }
}

if (isset($_POST['update_warehouse'])) {
    $warehouse_id = $_POST['warehouse_id'];
    $warehouse_name = $_POST['warehouse_name'];
    $warehouse_code = $_POST['warehouse_code'];
    $warehouse_address = $_POST['warehouse_address'];
    $warehouse_phone = $_POST['warehouse_phone'];
    $warehouse_type = $_POST['warehouse_type'];
    $warehouse_status = $_POST['warehouse_status'];
    
    try {
        $stmt = $conn->prepare("UPDATE depolar SET ad = :ad, kod = :kod, adres = :adres, telefon = :telefon, depo_tipi = :depo_tipi, durum = :durum WHERE id = :id");
        $stmt->bindParam(':ad', $warehouse_name);
        $stmt->bindParam(':kod', $warehouse_code);
        $stmt->bindParam(':adres', $warehouse_address);
        $stmt->bindParam(':telefon', $warehouse_phone);
        $stmt->bindParam(':depo_tipi', $warehouse_type);
        $stmt->bindParam(':durum', $warehouse_status);
        $stmt->bindParam(':id', $warehouse_id);
        $stmt->execute();
        
        $success_message = "Depo başarıyla güncellendi.";
    } catch (PDOException $e) {
        $error_message = "Depo güncellenirken hata oluştu: " . $e->getMessage();
    }
}

if (isset($_POST['delete_warehouse'])) {
    $warehouse_id = $_POST['warehouse_id'];
    
    try {
        // İlişkili kayıtları kontrol et
        $check_query = "SELECT COUNT(*) as count FROM depo_stok WHERE depo_id = :id";
        $stmt = $conn->prepare($check_query);
        $stmt->bindParam(':id', $warehouse_id);
        $stmt->execute();
        $has_records = $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
        
        if ($has_records) {
            $error_message = "Bu depoya ait stok kayıtları bulunduğu için silinemez!";
        } else {
            $stmt = $conn->prepare("DELETE FROM depolar WHERE id = :id");
            $stmt->bindParam(':id', $warehouse_id);
            $stmt->execute();
            
            $success_message = "Depo başarıyla silindi.";
        }
    } catch (PDOException $e) {
        $error_message = "Depo silinirken hata oluştu: " . $e->getMessage();
    }
}

// PERSONEL İŞLEMLERİ
if (isset($_POST['add_staff'])) {
    $staff_name = $_POST['staff_name'];
    $staff_no = $_POST['staff_no'];
    $staff_username = $_POST['staff_username'];
    $staff_password = $_POST['staff_password'];
    $staff_phone = $_POST['staff_phone'];
    $staff_email = $_POST['staff_email'];
    $staff_role = $_POST['staff_role'];
    $staff_store = $_POST['staff_store'];
    
    try {
        // Parola hash'leme
        $hashed_password = password_hash($staff_password, PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("INSERT INTO personel (ad, no, kullanici_adi, sifre, telefon_no, email, yetki_seviyesi, magaza_id) 
                                VALUES (:ad, :no, :kullanici_adi, :sifre, :telefon_no, :email, :yetki_seviyesi, :magaza_id)");
        $stmt->bindParam(':ad', $staff_name);
        $stmt->bindParam(':no', $staff_no);
        $stmt->bindParam(':kullanici_adi', $staff_username);
        $stmt->bindParam(':sifre', $hashed_password);
        $stmt->bindParam(':telefon_no', $staff_phone);
        $stmt->bindParam(':email', $staff_email);
        $stmt->bindParam(':yetki_seviyesi', $staff_role);
        $stmt->bindParam(':magaza_id', $staff_store);
        $stmt->execute();
        
        $success_message = "Personel başarıyla eklendi.";
    } catch (PDOException $e) {
        $error_message = "Personel eklenirken hata oluştu: " . $e->getMessage();
    }
}

if (isset($_POST['update_staff'])) {
    $staff_id = $_POST['staff_id'];
    $staff_name = $_POST['staff_name'];
    $staff_no = $_POST['staff_no'];
    $staff_username = $_POST['staff_username'];
    $staff_phone = $_POST['staff_phone'];
    $staff_email = $_POST['staff_email'];
    $staff_role = $_POST['staff_role'];
    $staff_store = $_POST['staff_store'];
    $staff_status = $_POST['staff_status'];
    
    try {
        $sql = "UPDATE personel SET ad = :ad, no = :no, kullanici_adi = :kullanici_adi, 
                telefon_no = :telefon_no, email = :email, yetki_seviyesi = :yetki_seviyesi, 
                magaza_id = :magaza_id, durum = :durum";
        
        // Eğer şifre değiştirilecekse
        $params = [
            ':ad' => $staff_name,
            ':no' => $staff_no,
            ':kullanici_adi' => $staff_username,
            ':telefon_no' => $staff_phone,
            ':email' => $staff_email,
            ':yetki_seviyesi' => $staff_role,
            ':magaza_id' => $staff_store,
            ':durum' => $staff_status,
            ':id' => $staff_id
        ];
        
        if (!empty($_POST['staff_password'])) {
            $hashed_password = password_hash($_POST['staff_password'], PASSWORD_DEFAULT);
            $sql .= ", sifre = :sifre";
            $params[':sifre'] = $hashed_password;
        }
        
        $sql .= " WHERE id = :id";
        $stmt = $conn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        
        $success_message = "Personel başarıyla güncellendi.";
    } catch (PDOException $e) {
        $error_message = "Personel güncellenirken hata oluştu: " . $e->getMessage();
    }
}

// GENEL AYARLAR
if (isset($_POST['save_general_settings'])) {
    $default_location_type = $_POST['default_location_type'];
    $default_location_id = $_POST['default_location_id'];
    $default_location = $default_location_type . '_' . $default_location_id;
    
    try {
        $stmt = $conn->prepare("
            INSERT INTO sistem_ayarlari (anahtar, deger, aciklama) 
            VALUES ('varsayilan_stok_lokasyonu', :deger, 'Varsayılan stok ekleme lokasyonu') 
            ON DUPLICATE KEY UPDATE deger = :deger
        ");
        $stmt->bindParam(':deger', $default_location);
        $stmt->execute();
        
        $success_message = "Genel ayarlar başarıyla kaydedildi.";
    } catch (PDOException $e) {
        $error_message = "Ayarlar kaydedilirken hata oluştu: " . $e->getMessage();
    }
}

// PUAN AYARLARI
if (isset($_POST['save_point_settings'])) {
    try {
        $conn->beginTransaction();
        
        // Standart müşteri ayarlarını güncelle
        $stmt = $conn->prepare("
            INSERT INTO puan_ayarlari (musteri_turu, puan_oran, min_harcama) 
            VALUES ('standart', :standart_oran, :standart_min) 
            ON DUPLICATE KEY UPDATE puan_oran = :standart_oran, min_harcama = :standart_min
        ");
        $stmt->bindParam(':standart_oran', $_POST['standart_point_rate']);
        $stmt->bindParam(':standart_min', $_POST['standart_min_spend']);
        $stmt->execute();
        
        // Gold müşteri ayarlarını güncelle
        $stmt = $conn->prepare("
            INSERT INTO puan_ayarlari (musteri_turu, puan_oran, min_harcama) 
            VALUES ('gold', :gold_oran, :gold_min) 
            ON DUPLICATE KEY UPDATE puan_oran = :gold_oran, min_harcama = :gold_min
        ");
        $stmt->bindParam(':gold_oran', $_POST['gold_point_rate']);
        $stmt->bindParam(':gold_min', $_POST['gold_min_spend']);
        $stmt->execute();
        
        // Platinum müşteri ayarlarını güncelle
        $stmt = $conn->prepare("
            INSERT INTO puan_ayarlari (musteri_turu, puan_oran, min_harcama) 
            VALUES ('platinum', :platinum_oran, :platinum_min) 
            ON DUPLICATE KEY UPDATE puan_oran = :platinum_oran, min_harcama = :platinum_min
        ");
        $stmt->bindParam(':platinum_oran', $_POST['platinum_point_rate']);
        $stmt->bindParam(':platinum_min', $_POST['platinum_min_spend']);
        $stmt->execute();
        
        $conn->commit();
        $success_message = "Müşteri puanlama ayarları başarıyla kaydedildi.";
    } catch (PDOException $e) {
        $conn->rollBack();
        $error_message = "Puanlama ayarları kaydedilirken hata oluştu: " . $e->getMessage();
    }
}

// Mevcut verileri çek
$stores_query = "SELECT * FROM magazalar ORDER BY ad";
$stores = $conn->query($stores_query)->fetchAll(PDO::FETCH_ASSOC);

$warehouses_query = "SELECT * FROM depolar ORDER BY ad";
$warehouses = $conn->query($warehouses_query)->fetchAll(PDO::FETCH_ASSOC);

$staff_query = "SELECT p.*, m.ad as magaza_adi FROM personel p 
                LEFT JOIN magazalar m ON p.magaza_id = m.id
                ORDER BY p.ad";
$staff = $conn->query($staff_query)->fetchAll(PDO::FETCH_ASSOC);

// Varsayılan stok lokasyonu ayarını al
$default_location_query = "SELECT deger FROM sistem_ayarlari WHERE anahtar = 'varsayilan_stok_lokasyonu'";
$stmt = $conn->prepare($default_location_query);
$stmt->execute();
$default_location = $stmt->fetchColumn();

// Müşteri puanlama ayarlarını al
$point_settings_query = "SELECT * FROM puan_ayarlari";
$point_settings = $conn->query($point_settings_query)->fetchAll(PDO::FETCH_ASSOC);

$point_settings_array = [];
foreach ($point_settings as $setting) {
    $point_settings_array[$setting['musteri_turu']] = $setting;
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
    <title>Sistem Ayarları</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold">Sistem Ayarları</h1>
                <p class="text-gray-600">Genel sistem yapılandırma ve yönetim sayfası</p>
            </div>
            <a href="admin_dashboard.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                Dashboard'a Dön
            </a>
        </div>

        <?php if ($success_message): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                <p><?php echo $success_message; ?></p>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                <p><?php echo $error_message; ?></p>
            </div>
        <?php endif; ?>

        <!-- Tab Menüsü -->
        <div class="mb-6">
            <div class="border-b border-gray-200">
                <nav class="-mb-px flex">
                    <a href="#general" class="tab-link active whitespace-nowrap py-4 px-4 border-b-2 border-blue-500 font-medium text-sm text-blue-600">
                        Genel Ayarlar
                    </a>
                    <a href="#stores" class="tab-link whitespace-nowrap py-4 px-4 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300">
                        Mağazalar
                    </a>
                    <a href="#warehouses" class="tab-link whitespace-nowrap py-4 px-4 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300">
                        Depolar
                    </a>
                    <a href="#staff" class="tab-link whitespace-nowrap py-4 px-4 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300">
                        Personel
                    </a>
                    <a href="#points" class="tab-link whitespace-nowrap py-4 px-4 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300">
                        Puan Ayarları
                    </a>
                </nav>
            </div>
        </div>

        <!-- Tab İçerikleri -->
        <div class="tab-content active" id="general-content">
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold mb-4">Genel Sistem Ayarları</h2>
                <form action="" method="POST">
                    <div class="mb-4">
                        <label for="default_location" class="block text-sm font-medium text-gray-700 mb-2">
                            Varsayılan Stok Lokasyonu
                        </label>
                        <p class="text-sm text-gray-500 mb-2">
                            Ürünler manuel olarak eklenirken veya toplu içe aktarım yapılırken ürünler varsayılan olarak hangi lokasyona eklensin?
                        </p>
                        
                        <?php
                        // Varsayılan lokasyonu parçala
                        $default_location_parts = explode('_', $default_location);
                        $default_location_type = $default_location_parts[0] ?? '';
                        $default_location_id = $default_location_parts[1] ?? '';
                        ?>
                        
                        <div class="flex space-x-4">
                            <div class="w-1/2">
                                <select name="default_location_type" id="default_location_type" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200" onchange="updateLocationOptions()">
                                    <option value="depo" <?php echo $default_location_type == 'depo' ? 'selected' : ''; ?>>Depo</option>
                                    <option value="magaza" <?php echo $default_location_type == 'magaza' ? 'selected' : ''; ?>>Mağaza</option>
                                </select>
                            </div>
                            <div class="w-1/2">
                                <select name="default_location_id" id="default_location_id" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200">
                                    <!-- JavaScript ile doldurulacak -->
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-6">
                        <button type="submit" name="save_general_settings" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Ayarları Kaydet
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="tab-content" id="stores-content">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-lg font-semibold">Mağaza Yönetimi</h2>
                    <button onclick="showAddStoreModal()" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded">
                        + Yeni Mağaza Ekle
                    </button>
                </div>
                
                <!-- Mağazalar Tablosu -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
    <tr>
        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
            ID
        </th>
        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
            Mağaza Adı
        </th>
        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
            Adres
        </th>
        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
            Telefon
        </th>
        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
            Cep Telefonu
        </th>
        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
            İşlemler
        </th>
    </tr>
</thead>
<tbody class="bg-white divide-y divide-gray-200">
    <?php foreach ($stores as $store): ?>
        <tr>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                <?php echo $store['id']; ?>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                <?php echo htmlspecialchars($store['ad']); ?>
            </td>
            <td class="px-6 py-4 text-sm text-gray-500">
                <?php echo htmlspecialchars($store['adres']); ?>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                <?php echo htmlspecialchars($store['telefon']); ?>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                <?php echo htmlspecialchars($store['cep_telefon']); ?>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                <button onclick="editStore(<?php echo $store['id']; ?>)" class="text-blue-600 hover:text-blue-900 mr-3">
                    Düzenle
                </button>
                <button onclick="deleteStore(<?php echo $store['id']; ?>)" class="text-red-600 hover:text-red-900">
                    Sil
                </button>
            </td>
        </tr>
    <?php endforeach; ?>
    
    <?php if (empty($stores)): ?>
        <tr>
            <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">
                Henüz mağaza eklenmemiş.
            </td>
        </tr>
    <?php endif; ?>
</tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="tab-content" id="warehouses-content">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-lg font-semibold">Depo Yönetimi</h2>
                    <button onclick="showAddWarehouseModal()" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded">
                        + Yeni Depo Ekle
                    </button>
                </div>
                
                <!-- Depolar Tablosu -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    ID
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Kod
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Depo Adı
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Adres
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Tip
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Durum
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    İşlemler
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($warehouses as $warehouse): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo $warehouse['id']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars($warehouse['kod']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($warehouse['ad']); ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        <?php echo htmlspecialchars($warehouse['adres']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php 
                                        echo $warehouse['depo_tipi'] == 'ana_depo' ? 'Ana Depo' : 'Ara Depo';
                                        ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $warehouse['durum'] === 'aktif' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                            <?php echo $warehouse['durum'] === 'aktif' ? 'Aktif' : 'Pasif'; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <button onclick="editWarehouse(<?php echo $warehouse['id']; ?>)" class="text-blue-600 hover:text-blue-900 mr-3">
                                            Düzenle
                                        </button>
                                        <button onclick="deleteWarehouse(<?php echo $warehouse['id']; ?>)" class="text-red-600 hover:text-red-900">
                                            Sil
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($warehouses)): ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">
                                        Henüz depo eklenmemiş.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="tab-content" id="staff-content">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-lg font-semibold">Personel Yönetimi</h2>
                    <button onclick="showAddStaffModal()" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded">
                        + Yeni Personel Ekle
                    </button>
                </div>
                
                <!-- Personel Tablosu -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    ID
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Adı
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Kullanıcı Adı
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Telefon
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Mağaza
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Yetki
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Durum
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    İşlemler
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($staff as $employee): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo $employee['id']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($employee['ad']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars($employee['kullanici_adi']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars($employee['telefon_no']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars($employee['magaza_adi']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php
                                        $roles = [
                                            'kasiyer' => 'Kasiyer',
                                            'mudur_yardimcisi' => 'Müdür Yardımcısı',
                                            'mudur' => 'Müdür'
                                        ];
                                        echo $roles[$employee['yetki_seviyesi']] ?? $employee['yetki_seviyesi'];
                                        ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $employee['durum'] === 'aktif' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                            <?php echo $employee['durum'] === 'aktif' ? 'Aktif' : 'Pasif'; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <button onclick="editStaff(<?php echo $employee['id']; ?>)" class="text-blue-600 hover:text-blue-900 mr-3">
                                            Düzenle
                                        </button>
                                        <button onclick="resetPassword(<?php echo $employee['id']; ?>)" class="text-yellow-600 hover:text-yellow-900 mr-3">
                                            Şifre Sıfırla
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($staff)): ?>
                                <tr>
                                    <td colspan="8" class="px-6 py-4 text-center text-sm text-gray-500">
                                        Henüz personel eklenmemiş.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="tab-content" id="points-content">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="mb-4">
                    <h2 class="text-lg font-semibold">Müşteri Puanlama Ayarları</h2>
                    <p class="text-sm text-gray-500 mt-1">
                        Müşteri segmentlerine göre puan kazanma ve harcama ayarlarını yönetin.
                    </p>
                </div>
                
                <form action="" method="POST">
                    <!-- Standart Müşteri Ayarları -->
                    <div class="mb-6 border-b pb-6">
                        <h3 class="text-md font-semibold mb-4">Standart Müşteri</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="standart_point_rate" class="block text-sm font-medium text-gray-700 mb-1">
                                    Puan Oranı (%)
                                </label>
                                <input type="number" step="0.01" min="0" max="100" name="standart_point_rate" id="standart_point_rate" 
                                    value="<?php echo $point_settings_array['standart']['puan_oran'] ?? 1; ?>" 
                                    class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                <p class="mt-1 text-xs text-gray-500">Her 1 TL için kazanılacak puan yüzdesi</p>
                            </div>
                            <div>
                                <label for="standart_min_spend" class="block text-sm font-medium text-gray-700 mb-1">
                                    Minimum Harcama (₺)
                                </label>
                                <input type="number" step="0.01" min="0" name="standart_min_spend" id="standart_min_spend" 
                                    value="<?php echo $point_settings_array['standart']['min_harcama'] ?? 0; ?>" 
                                    class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                <p class="mt-1 text-xs text-gray-500">Bu seviye için minimum harcama tutarı</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Gold Müşteri Ayarları -->
                    <div class="mb-6 border-b pb-6">
                        <h3 class="text-md font-semibold mb-4">Gold Müşteri</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="gold_point_rate" class="block text-sm font-medium text-gray-700 mb-1">
                                    Puan Oranı (%)
                                </label>
                                <input type="number" step="0.01" min="0" max="100" name="gold_point_rate" id="gold_point_rate" 
                                    value="<?php echo $point_settings_array['gold']['puan_oran'] ?? 2; ?>" 
                                    class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                <p class="mt-1 text-xs text-gray-500">Her 1 TL için kazanılacak puan yüzdesi</p>
                            </div>
                            <div>
                                <label for="gold_min_spend" class="block text-sm font-medium text-gray-700 mb-1">
                                    Minimum Harcama (₺)
                                </label>
                                <input type="number" step="0.01" min="0" name="gold_min_spend" id="gold_min_spend" 
                                    value="<?php echo $point_settings_array['gold']['min_harcama'] ?? 1000; ?>" 
                                    class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                <p class="mt-1 text-xs text-gray-500">Bu seviye için minimum harcama tutarı</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Platinum Müşteri Ayarları -->
                    <div class="mb-6">
                        <h3 class="text-md font-semibold mb-4">Platinum Müşteri</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="platinum_point_rate" class="block text-sm font-medium text-gray-700 mb-1">
                                    Puan Oranı (%)
                                </label>
                                <input type="number" step="0.01" min="0" max="100" name="platinum_point_rate" id="platinum_point_rate" 
                                    value="<?php echo $point_settings_array['platinum']['puan_oran'] ?? 3; ?>" 
                                    class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                <p class="mt-1 text-xs text-gray-500">Her 1 TL için kazanılacak puan yüzdesi</p>
                            </div>
                            <div>
                                <label for="platinum_min_spend" class="block text-sm font-medium text-gray-700 mb-1">
                                    Minimum Harcama (₺)
                                </label>
                                <input type="number" step="0.01" min="0" name="platinum_min_spend" id="platinum_min_spend" 
                                    value="<?php echo $point_settings_array['platinum']['min_harcama'] ?? 5000; ?>" 
                                    class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                <p class="mt-1 text-xs text-gray-500">Bu seviye için minimum harcama tutarı</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-6">
                        <button type="submit" name="save_point_settings" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Ayarları Kaydet
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Mağaza Ekleme Modalı -->
<div id="addStoreModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold">Yeni Mağaza Ekle</h3>
            <button type="button" onclick="closeModal('addStoreModal')" class="text-gray-400 hover:text-gray-500">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <form action="" method="POST">
            <div class="mb-4">
                <label for="store_name" class="block text-sm font-medium text-gray-700 mb-1">
                    Mağaza Adı
                </label>
                <input type="text" name="store_name" id="store_name" required 
                       class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="mb-4">
                <label for="store_address" class="block text-sm font-medium text-gray-700 mb-1">
                    Adres
                </label>
                <textarea name="store_address" id="store_address" rows="3" 
                          class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"></textarea>
            </div>
            <div class="mb-4">
                <label for="store_phone" class="block text-sm font-medium text-gray-700 mb-1">
                    Telefon
                </label>
                <input type="text" name="store_phone" id="store_phone" 
                       class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="mb-4">
                <label for="store_mobile" class="block text-sm font-medium text-gray-700 mb-1">
                    Cep Telefonu
                </label>
                <input type="text" name="store_mobile" id="store_mobile" 
                       class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                <p class="text-xs text-gray-500 mt-1">SMS doğrulama için kullanılacak numara</p>
            </div>
            <div class="mt-6">
                <button type="submit" name="add_store" class="w-full inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Mağaza Ekle
                </button>
            </div>
        </form>
    </div>
</div>

    <!-- Mağaza Düzenleme Modalı -->
<div id="editStoreModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold">Mağaza Düzenle</h3>
            <button type="button" onclick="closeModal('editStoreModal')" class="text-gray-400 hover:text-gray-500">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <form action="" method="POST">
            <input type="hidden" name="store_id" id="edit_store_id">
            <div class="mb-4">
                <label for="edit_store_name" class="block text-sm font-medium text-gray-700 mb-1">
                    Mağaza Adı
                </label>
                <input type="text" name="store_name" id="edit_store_name" required 
                       class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="mb-4">
                <label for="edit_store_address" class="block text-sm font-medium text-gray-700 mb-1">
                    Adres
                </label>
                <textarea name="store_address" id="edit_store_address" rows="3" 
                          class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"></textarea>
            </div>
            <div class="mb-4">
                <label for="edit_store_phone" class="block text-sm font-medium text-gray-700 mb-1">
                    Telefon
                </label>
                <input type="text" name="store_phone" id="edit_store_phone" 
                       class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="mb-4">
                <label for="edit_store_mobile" class="block text-sm font-medium text-gray-700 mb-1">
                    Cep Telefonu
                </label>
                <input type="text" name="store_mobile" id="edit_store_mobile" 
                       class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                <p class="text-xs text-gray-500 mt-1">SMS doğrulama için kullanılacak numara</p>
            </div>
            <div class="mt-6">
                <button type="submit" name="update_store" class="w-full inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Değişiklikleri Kaydet
                </button>
            </div>
        </form>
    </div>
</div>


<!-- Depo Ekleme Modalı -->
<div id="addWarehouseModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold">Yeni Depo Ekle</h3>
            <button type="button" data-close-modal="addWarehouseModal" class="text-gray-400 hover:text-gray-500">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <form action="" method="POST" id="warehouseForm">
            <div class="mb-4">
                <label for="warehouse_name" class="block text-sm font-medium text-gray-700 mb-1">
                    Depo Adı
                </label>
                <input type="text" name="warehouse_name" id="warehouse_name" required 
                       class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="mb-4">
                <label for="warehouse_code" class="block text-sm font-medium text-gray-700 mb-1">
                    Depo Kodu
                </label>
                <input type="text" name="warehouse_code" id="warehouse_code" 
                       class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="mb-4">
                <label for="warehouse_address" class="block text-sm font-medium text-gray-700 mb-1">
                    Adres
                </label>
                <textarea name="warehouse_address" id="warehouse_address" rows="3" 
                          class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"></textarea>
            </div>
            <div class="mb-4">
                <label for="warehouse_phone" class="block text-sm font-medium text-gray-700 mb-1">
                    Telefon
                </label>
                <input type="text" name="warehouse_phone" id="warehouse_phone" 
                       class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="mb-4">
                <label for="warehouse_type" class="block text-sm font-medium text-gray-700 mb-1">
                    Depo Tipi
                </label>
                <select name="warehouse_type" id="warehouse_type" 
                        class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    <option value="ana_depo">Ana Depo</option>
                    <option value="ara_depo" selected>Ara Depo</option>
                </select>
            </div>
            <div class="mt-6">
                <button type="submit" name="add_warehouse" class="w-full inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Depo Ekle
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Depo Düzenleme Modalı -->
<div id="editWarehouseModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold">Depo Düzenle</h3>
            <button type="button" data-close-modal="editWarehouseModal" class="text-gray-400 hover:text-gray-500">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <form action="" method="POST">
            <input type="hidden" name="warehouse_id" id="edit_warehouse_id">
            <div class="mb-4">
                <label for="edit_warehouse_name" class="block text-sm font-medium text-gray-700 mb-1">
                    Depo Adı
                </label>
                <input type="text" name="warehouse_name" id="edit_warehouse_name" required 
                       class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="mb-4">
                <label for="edit_warehouse_code" class="block text-sm font-medium text-gray-700 mb-1">
                    Depo Kodu
                </label>
                <input type="text" name="warehouse_code" id="edit_warehouse_code" 
                       class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="mb-4">
                <label for="edit_warehouse_address" class="block text-sm font-medium text-gray-700 mb-1">
                    Adres
                </label>
                <textarea name="warehouse_address" id="edit_warehouse_address" rows="3" 
                          class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"></textarea>
            </div>
            <div class="mb-4">
                <label for="edit_warehouse_phone" class="block text-sm font-medium text-gray-700 mb-1">
                    Telefon
                </label>
                <input type="text" name="warehouse_phone" id="edit_warehouse_phone" 
                       class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="mb-4">
                <label for="edit_warehouse_type" class="block text-sm font-medium text-gray-700 mb-1">
                    Depo Tipi
                </label>
                <select name="warehouse_type" id="edit_warehouse_type" 
                        class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    <option value="ana_depo">Ana Depo</option>
                    <option value="ara_depo">Ara Depo</option>
                </select>
            </div>
            <div class="mb-4">
                <label for="edit_warehouse_status" class="block text-sm font-medium text-gray-700 mb-1">
                    Durum
                </label>
                <select name="warehouse_status" id="edit_warehouse_status" 
                        class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    <option value="aktif">Aktif</option>
                    <option value="pasif">Pasif</option>
                </select>
            </div>
            <div class="mt-6">
                <button type="submit" name="update_warehouse" class="w-full inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Değişiklikleri Kaydet
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Personel Ekleme Modalı -->
<div id="addStaffModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold">Yeni Personel Ekle</h3>
            <button type="button" data-close-modal="addStaffModal" class="text-gray-400 hover:text-gray-500">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <form action="" method="POST" id="staffForm" data-form-type="add">
            <div class="mb-4">
                <label for="staff_name" class="block text-sm font-medium text-gray-700 mb-1">
                    Adı Soyadı
                </label>
                <input type="text" name="staff_name" id="staff_name" required 
                       class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="mb-4">
                <label for="staff_no" class="block text-sm font-medium text-gray-700 mb-1">
                    Personel No
                </label>
                <input type="text" name="staff_no" id="staff_no" 
                       class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="mb-4">
                <label for="staff_username" class="block text-sm font-medium text-gray-700 mb-1">
                    Kullanıcı Adı
                </label>
                <input type="text" name="staff_username" id="staff_username" required 
                       class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="mb-4">
                <label for="staff_password" class="block text-sm font-medium text-gray-700 mb-1">
                    Şifre
                </label>
                <input type="password" name="staff_password" id="staff_password" required 
                       class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="mb-4">
                <label for="staff_phone" class="block text-sm font-medium text-gray-700 mb-1">
                    Telefon
                </label>
                <input type="text" name="staff_phone" id="staff_phone" 
                       class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="mb-4">
                <label for="staff_email" class="block text-sm font-medium text-gray-700 mb-1">
                    E-posta
                </label>
                <input type="email" name="staff_email" id="staff_email" 
                       class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="mb-4">
                <label for="staff_role" class="block text-sm font-medium text-gray-700 mb-1">
                    Yetki Seviyesi
                </label>
                <select name="staff_role" id="staff_role" 
                        class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    <option value="kasiyer">Kasiyer</option>
                    <option value="mudur_yardimcisi">Müdür Yardımcısı</option>
                    <option value="mudur">Müdür</option>
                </select>
            </div>
            <div class="mb-4">
                <label for="staff_store" class="block text-sm font-medium text-gray-700 mb-1">
                    Mağaza
                </label>
                <select name="staff_store" id="staff_store" 
                        class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Seçiniz</option>
                    <?php foreach ($stores as $store): ?>
                        <option value="<?php echo $store['id']; ?>"><?php echo htmlspecialchars($store['ad']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mt-6">
                <button type="submit" name="add_staff" class="w-full inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Personel Ekle
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Personel Düzenleme Modalı -->
<div id="editStaffModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold">Personel Düzenle</h3>
            <button type="button" data-close-modal="editStaffModal" class="text-gray-400 hover:text-gray-500">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <form action="" method="POST" id="editStaffForm" data-form-type="edit">
            <input type="hidden" name="staff_id" id="edit_staff_id">
            <div class="mb-4">
                <label for="edit_staff_name" class="block text-sm font-medium text-gray-700 mb-1">
                    Adı Soyadı
                </label>
                <input type="text" name="staff_name" id="edit_staff_name" required 
                       class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="mb-4">
                <label for="edit_staff_no" class="block text-sm font-medium text-gray-700 mb-1">
                    Personel No
                </label>
                <input type="text" name="staff_no" id="edit_staff_no" 
                       class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="mb-4">
                <label for="edit_staff_username" class="block text-sm font-medium text-gray-700 mb-1">
                    Kullanıcı Adı
                </label>
                <input type="text" name="staff_username" id="edit_staff_username" required 
                       class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="mb-4">
                <label for="edit_staff_password" class="block text-sm font-medium text-gray-700 mb-1">
                    Şifre (Değiştirmek için doldurun)
                </label>
                <input type="password" name="staff_password" id="edit_staff_password" 
                       class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="mb-4">
                <label for="edit_staff_phone" class="block text-sm font-medium text-gray-700 mb-1">
                    Telefon
                </label>
                <input type="text" name="staff_phone" id="edit_staff_phone" 
                       class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="mb-4">
                <label for="edit_staff_email" class="block text-sm font-medium text-gray-700 mb-1">
                    E-posta
                </label>
                <input type="email" name="staff_email" id="edit_staff_email" 
                       class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="mb-4">
                <label for="edit_staff_role" class="block text-sm font-medium text-gray-700 mb-1">
                    Yetki Seviyesi
                </label>
                <select name="staff_role" id="edit_staff_role" 
                        class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    <option value="kasiyer">Kasiyer</option>
                    <option value="mudur_yardimcisi">Müdür Yardımcısı</option>
                    <option value="mudur">Müdür</option>
                </select>
            </div>
            <div class="mb-4">
                <label for="edit_staff_store" class="block text-sm font-medium text-gray-700 mb-1">
                    Mağaza
                </label>
                <select name="staff_store" id="edit_staff_store" 
                        class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Seçiniz</option>
                    <?php foreach ($stores as $store): ?>
                        <option value="<?php echo $store['id']; ?>"><?php echo htmlspecialchars($store['ad']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-4">
                <label for="edit_staff_status" class="block text-sm font-medium text-gray-700 mb-1">
                    Durum
                </label>
                <select name="staff_status" id="edit_staff_status" 
                        class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    <option value="aktif">Aktif</option>
                    <option value="pasif">Pasif</option>
                </select>
            </div>
            <div class="mt-6">
                <button type="submit" name="update_staff" class="w-full inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Değişiklikleri Kaydet
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Şifre Sıfırlama Modalı -->
<div id="resetPasswordModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold">Şifre Sıfırla</h3>
            <button type="button" data-close-modal="resetPasswordModal" class="text-gray-400 hover:text-gray-500">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <form action="" method="POST">
            <input type="hidden" name="reset_staff_id" id="reset_staff_id">
            <div class="mb-4">
                <p class="text-gray-700 mb-2">
                    <span id="reset_staff_name"></span> için yeni şifre belirleyin:
                </p>
                <div class="mb-4">
                    <label for="new_password" class="block text-sm font-medium text-gray-700 mb-1">
                        Yeni Şifre
                    </label>
                    <input type="password" name="new_password" id="new_password" required 
                           class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>
            <div class="mt-6">
                <button type="submit" name="reset_password" class="w-full inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Şifreyi Sıfırla
                </button>
            </div>
        </form>
    </div>
</div>
    <script>
        // Tab değiştirme
        document.addEventListener('DOMContentLoaded', function() {
            const tabLinks = document.querySelectorAll('.tab-link');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Aktif tab linkini değiştir
                    tabLinks.forEach(l => l.classList.remove('active', 'border-blue-500', 'text-blue-600'));
                    tabLinks.forEach(l => l.classList.add('border-transparent', 'text-gray-500'));
                    link.classList.remove('border-transparent', 'text-gray-500');
                    link.classList.add('active', 'border-blue-500', 'text-blue-600');
                    
                    // Tab içeriğini değiştir
                    const target = link.getAttribute('href').substring(1) + '-content';
                    tabContents.forEach(content => content.classList.remove('active'));
                    document.getElementById(target).classList.add('active');
                });
            });
            
            // Varsayılan stok lokasyonu seçeneklerini güncelle
            updateLocationOptions();
        });
        
        // Varsayılan stok lokasyonu seçeneği
        function updateLocationOptions() {
            const locationType = document.getElementById('default_location_type').value;
            const locationSelect = document.getElementById('default_location_id');
            locationSelect.innerHTML = '';
            
            if (locationType === 'depo') {
                <?php foreach ($warehouses as $warehouse): ?>
                    <?php if ($warehouse['durum'] === 'aktif'): ?>
                        const option = document.createElement('option');
                        option.value = '<?php echo $warehouse['id']; ?>';
                        option.textContent = '<?php echo htmlspecialchars($warehouse['ad']); ?>';
                        
                        <?php if ($default_location_type == 'depo' && $default_location_id == $warehouse['id']): ?>
                            option.selected = true;
                        <?php endif; ?>
                        
                        locationSelect.appendChild(option);
                    <?php endif; ?>
                <?php endforeach; ?>
            } else {
                <?php foreach ($stores as $store): ?>
                    const option = document.createElement('option');
                    option.value = '<?php echo $store['id']; ?>';
                    option.textContent = '<?php echo htmlspecialchars($store['ad']); ?>';
                    
                    <?php if ($default_location_type == 'magaza' && $default_location_id == $store['id']): ?>
                        option.selected = true;
                    <?php endif; ?>
                    
                    locationSelect.appendChild(option);
                <?php endforeach; ?>
            }
        }
        
        // Modal işlemleri
        function showAddStoreModal() {
            document.getElementById('addStoreModal').classList.remove('hidden');
        }
        
        function showAddWarehouseModal() {
            document.getElementById('addWarehouseModal').classList.remove('hidden');
        }
        
        function showAddStaffModal() {
            document.getElementById('addStaffModal').classList.remove('hidden');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
        }
        
        function editStore(storeId) {
            // AJAX ile mağaza verilerini çek
            fetch('api/get_store.php?id=' + storeId)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('edit_store_id').value = data.id;
                    document.getElementById('edit_store_name').value = data.ad;
                    document.getElementById('edit_store_address').value = data.adres;
                    document.getElementById('edit_store_phone').value = data.telefon;
                    document.getElementById('editStoreModal').classList.remove('hidden');
                })
                .catch(error => console.error('Error:', error));
        }
        
        function deleteStore(storeId) {
            if (confirm('Bu mağazayı silmek istediğinize emin misiniz?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'store_id';
                input.value = storeId;
                
                const submitBtn = document.createElement('input');
                submitBtn.type = 'hidden';
                submitBtn.name = 'delete_store';
                submitBtn.value = '1';
                
                form.appendChild(input);
                form.appendChild(submitBtn);
                document.body.appendChild(form);
                
                form.submit();
            }
        }
        
        // Benzer şekilde diğer düzenleme ve silme işlevleri...
    </script>

<?php
// Sayfa özel scriptleri
$page_scripts = '
    <script src="assets/js/settings.js"></script>
';

// Footer'ı dahil et
include 'footer.php';
?>