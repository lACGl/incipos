<?php
require_once 'session_manager.php'; // Otomatik eklendi
secure_session_start();
require_once 'db_connection.php';

// Yetki kontrolü
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: index.php");
    exit;
}

// ID parametresini kontrol et
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "Geçersiz fatura ID";
    exit;
}

$invoice_id = (int)$_GET['id'];

try {
    // Fatura bilgilerini al
    $invoice_query = "
        SELECT 
            sf.*,
            m.ad as magaza_adi,
            m.adres as magaza_adres,
            m.telefon as magaza_telefon,
            p.ad as personel_adi,
            mus.ad as musteri_adi,
            mus.soyad as musteri_soyad,
            mus.telefon as musteri_telefon
        FROM 
            satis_faturalari sf
            LEFT JOIN magazalar m ON sf.magaza = m.id
            LEFT JOIN personel p ON sf.personel = p.id
            LEFT JOIN musteriler mus ON sf.musteri_id = mus.id
        WHERE 
            sf.id = ?
    ";
    
    $stmt = $conn->prepare($invoice_query);
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$invoice) {
        echo "Fatura bulunamadı";
        exit;
    }
    
    // Fatura detaylarını al
    $details_query = "
        SELECT 
            sfd.*,
            us.ad as urun_adi,
            us.barkod,
            us.kod
        FROM 
            satis_fatura_detay sfd
            LEFT JOIN urun_stok us ON sfd.urun_id = us.id
        WHERE 
            sfd.fatura_id = ?
    ";
    
    $stmt = $conn->prepare($details_query);
    $stmt->execute([$invoice_id]);
    $details = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    echo "Veritabanı hatası: " . $e->getMessage();
    exit;
}

// Ödeme türleri metinleri
$payment_methods = [
    'nakit' => 'Nakit',
    'kredi_karti' => 'Kredi Kartı',
    'havale' => 'Havale'
];

// İşlem türleri metinleri
$transaction_types = [
    'satis' => 'Satış',
    'iade' => 'İade'
];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Fatura Yazdır: <?php echo $invoice['fatura_seri'] . $invoice['fatura_no']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            margin: 0;
            padding: 20px;
            background-color: white;
        }
        
        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
            border: 1px solid #e2e8f0;
            padding: 30px;
            background-color: white;
        }
        
        .invoice-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 20px;
        }
        
        .invoice-title {
            font-size: 24px;
            font-weight: bold;
            color: #1a202c;
            margin-bottom: 5px;
        }
        
        .invoice-subtitle {
            font-size: 14px;
            color: #4a5568;
        }
        
        .invoice-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        
        .invoice-info-group {
            width: 48%;
        }
        
        .invoice-info-heading {
            font-weight: bold;
            margin-bottom: 10px;
            color: #4a5568;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 5px;
        }
        
        .invoice-detail {
            margin-bottom: 5px;
            font-size: 14px;
        }
        
        .invoice-detail-label {
            font-weight: bold;
            display: inline-block;
            width: 120px;
        }
        
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .invoice-table th {
            text-align: left;
            padding: 12px 10px;
            background-color: #f7fafc;
            border-bottom: 2px solid #cbd5e0;
            font-size: 12px;
            text-transform: uppercase;
            color: #4a5568;
        }
        
        .invoice-table td {
            padding: 12px 10px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
        }
        
        .invoice-table .text-right {
            text-align: right;
        }
        
        .invoice-totals {
            margin-top: 30px;
            display: flex;
            justify-content: flex-end;
        }
        
        .invoice-totals-table {
            width: 300px;
        }
        
        .invoice-total-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .invoice-total-row.grand-total {
            font-weight: bold;
            font-size: 16px;
            border-top: 2px solid #4a5568;
            border-bottom: none;
            padding-top: 12px;
        }
        
        .invoice-notes {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }
        
        .invoice-notes-title {
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .invoice-footer {
            margin-top: 50px;
            text-align: center;
            font-size: 12px;
            color: #718096;
        }
        
        @media print {
            body {
                padding: 0;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .invoice-container {
                max-width: 100%;
                border: none;
                padding: 20px;
            }
            
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body class="bg-gray-100" onload="setTimeout(function() { window.print(); }, 500)">
    <div class="no-print mb-4 flex justify-end max-w-4xl mx-auto">
        <button onclick="window.print()" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
            Yazdır
        </button>
        <button onclick="window.close()" class="ml-2 bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
            Kapat
        </button>
    </div>
    
    <div class="invoice-container">
        <!-- Fatura Başlık -->
        <div class="invoice-header">
            <div>
                <div class="invoice-title">İnciPOS</div>
                <div class="invoice-subtitle">İnci Kırtasiye</div>
                <div class="invoice-subtitle">
					<?php echo $invoice['magaza_adi']; ?> Şube<br>
                    <?php echo $invoice['magaza_adres']; ?><br>
                    Tel: <?php echo $invoice['magaza_telefon']; ?>
                </div>
            </div>
            <div class="text-right">
                <div class="invoice-title"><?php echo $transaction_types[$invoice['islem_turu']] ?? 'Fatura'; ?></div>
                <div class="invoice-subtitle">Satış No: <?php echo $invoice['fatura_seri'] . $invoice['fatura_no']; ?></div>
                <div class="invoice-subtitle">Tarih: <?php echo date('d.m.Y H:i', strtotime($invoice['fatura_tarihi'])); ?></div>
            </div>
        </div>
        
        <!-- Fatura Bilgileri -->
        <div class="invoice-info">
            <div class="invoice-info-group">
                <div class="invoice-info-heading">Mağaza Bilgileri</div>
                <div class="invoice-detail">
                    <span class="invoice-detail-label">Mağaza:</span> <?php echo $invoice['magaza_adi']; ?> Şube
                </div>
                <div class="invoice-detail">
                    <span class="invoice-detail-label">Personel:</span> <?php echo $invoice['personel_adi']; ?>
                </div>
                <div class="invoice-detail">
                    <span class="invoice-detail-label">Ödeme Türü:</span> <?php echo $payment_methods[$invoice['odeme_turu']] ?? $invoice['odeme_turu']; ?>
                </div>
                <?php if ($invoice['kredi_karti_banka']): ?>
                <div class="invoice-detail">
                    <span class="invoice-detail-label">Banka:</span> <?php echo $invoice['kredi_karti_banka']; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if ($invoice['musteri_id']): ?>
            <div class="invoice-info-group">
                <div class="invoice-info-heading">Müşteri Bilgileri</div>
                <div class="invoice-detail">
                    <span class="invoice-detail-label">Ad Soyad:</span> <?php echo $invoice['musteri_adi'] . ' ' . $invoice['musteri_soyad']; ?>
                </div>
                <div class="invoice-detail">
                    <span class="invoice-detail-label">Telefon:</span> <?php echo $invoice['musteri_telefon']; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Fatura Kalemleri -->
        <table class="invoice-table">
            <thead>
                <tr>
                    <th width="10%">Kod</th>
                    <th width="40%">Ürün</th>
                    <th width="10%" class="text-right">Miktar</th>
                    <th width="15%" class="text-right">Birim Fiyat</th>
                    <th width="10%" class="text-right">KDV</th>
                    <th width="15%" class="text-right">Toplam</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($details as $item): ?>
                <tr>
                    <td><?php echo $item['kod'] ?? $item['barkod']; ?></td>
                    <td><?php echo $item['urun_adi']; ?></td>
                    <td class="text-right"><?php echo $item['miktar']; ?></td>
                    <td class="text-right"><?php echo number_format($item['birim_fiyat'], 2, ',', '.'); ?> ₺</td>
                    <td class="text-right">%<?php echo number_format($item['kdv_orani'], 0); ?></td>
                    <td class="text-right"><?php echo number_format($item['toplam_tutar'], 2, ',', '.'); ?> ₺</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- Fatura Toplamları -->
        <div class="invoice-totals">
            <div class="invoice-totals-table">
                <div class="invoice-total-row">
                    <div>Ara Toplam:</div>
                    <div><?php echo number_format($invoice['toplam_tutar'], 2, ',', '.'); ?> ₺</div>
                </div>
                <div class="invoice-total-row">
                    <div>KDV Tutarı:</div>
                    <div><?php echo number_format($invoice['kdv_tutari'], 2, ',', '.'); ?> ₺</div>
                </div>
                <div class="invoice-total-row">
                    <div>İndirim:</div>
                    <div><?php echo number_format($invoice['indirim_tutari'], 2, ',', '.'); ?> ₺</div>
                </div>
                <div class="invoice-total-row grand-total">
                    <div>GENEL TOPLAM:</div>
                    <div><?php echo number_format($invoice['net_tutar'], 2, ',', '.'); ?> ₺</div>
                </div>
            </div>
        </div>
        
        <?php if ($invoice['aciklama']): ?>
        <!-- Fatura Notları -->
        <div class="invoice-notes">
            <div class="invoice-notes-title">Notlar:</div>
            <div><?php echo nl2br($invoice['aciklama']); ?></div>
        </div>
        <?php endif; ?>
        
        <!-- Fatura Altbilgi -->
        <div class="invoice-footer">
            <p>Bu bir İnciPOS satış çıktısıdır. Teşekkür ederiz.</p>
            <p>***MALİ DEĞERİ YOKTUR***</p>
            <p>Yazdırılma Tarihi: <?php echo date('d.m.Y H:i:s'); ?></p>
        </div>
    </div>
</body>
</html>