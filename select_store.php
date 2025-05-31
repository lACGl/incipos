<?php
session_start();
require_once 'admin/db_connection.php';

// Kullanıcı giriş yapmamışsa giriş sayfasına yönlendir
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: login.php");
    exit;
}

// Yetki kontrolü - sadece müdür ve müdür yardımcıları için
$allowedRoles = ['mudur', 'mudur_yardimcisi'];
if (!in_array($_SESSION['yetki'], $allowedRoles)) {
    header("Location: pos.php");
    exit;
}

// Mağaza seçimi yapıldıysa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_store'])) {
    $selectedStoreId = intval($_POST['selected_store']);
    
    // Seçilen mağazanın geçerli olup olmadığını kontrol et
    $stmt = $conn->prepare("SELECT * FROM magazalar WHERE id = ?");
    $stmt->execute([$selectedStoreId]);
    $store = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($store) {
        // Mağaza bilgilerini session'a kaydet
        $_SESSION['magaza_id'] = $selectedStoreId;
        $_SESSION['magaza_adi'] = $store['ad'];
        
        // Doğrulama sayfasına yönlendir
        header("Location: verify.php");
        exit;
    } else {
        $error = 'Geçersiz mağaza seçimi.';
    }
}

// Tüm mağazaları getir
$stmt = $conn->query("SELECT * FROM magazalar ORDER BY ad");
$stores = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Son kullanılan mağazayı getir (isteğe bağlı)
$lastUsedStore = null;
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT magaza_id FROM personel WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $lastUsedStore = $result['magaza_id'] ?? null;
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İnci Kırtasiye - Mağaza Seçimi</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .store-card {
            transition: all 0.3s ease;
        }
        .store-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        .last-used {
            border: 2px solid #3B82F6;
            position: relative;
        }
        .last-used::after {
            content: 'Son Kullanılan';
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: #3B82F6;
            color: white;
            font-size: 0.7rem;
            padding: 2px 8px;
            border-radius: 10px;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="container mx-auto px-4 py-8">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-blue-700">Mağaza Seçimi</h1>
            <p class="text-gray-600">Lütfen çalışmak istediğiniz mağazayı seçin</p>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 max-w-lg mx-auto">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 max-w-4xl mx-auto">
            <?php foreach ($stores as $store): ?>
                <div class="store-card bg-white rounded-lg shadow-md overflow-hidden <?php echo ($lastUsedStore == $store['id']) ? 'last-used' : ''; ?>">
                    <div class="p-6">
                        <div class="flex items-center justify-center h-16 w-16 rounded-full bg-blue-100 text-blue-800 mx-auto mb-4">
                            <i class="fas fa-store text-2xl"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-center mb-4"><?php echo htmlspecialchars($store['ad']); ?></h3>
                        <form method="POST" action="">
                            <input type="hidden" name="selected_store" value="<?php echo $store['id']; ?>">
                            <button type="submit" class="w-full bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                                Bu Mağazada Çalış
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="text-center mt-8">
            <a href="logout.php" class="text-blue-500 hover:text-blue-700">
                <i class="fas fa-sign-out-alt mr-1"></i> Çıkış Yap
            </a>
        </div>
    </div>
</body>
</html>