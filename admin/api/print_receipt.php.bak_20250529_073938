<?php
session_start();
require_once '../db_connection.php';

// Yetki kontrolü
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: index.php");
    exit;
}

// Fatura ID'sini al
$invoiceId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$invoiceId) {
    echo "Geçersiz fatura ID";
    exit;
}

try {
    // Fatura bilgilerini getir
    $stmt = $conn->prepare("
        SELECT 
            sf.*,
            m.ad as magaza_adi,
            p.ad as personel_adi,
            CONCAT(mu.ad, ' ', mu.soyad) as musteri_adi,
            mu.telefon as musteri_telefon
        FROM satis_faturalari sf
        LEFT JOIN magazalar m ON sf.magaza = m.id
        LEFT JOIN personel p ON sf.personel = p.id
        LEFT JOIN musteriler mu ON sf.musteri_id = mu.id
        WHERE sf.id = ?
    ");
    $stmt->execute([$invoiceId]);
    $fatura = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$fatura) {
        echo "Fatura bulunamadı";
        exit;
    }
    
    // Fatura detaylarını getir
    $stmt = $conn->prepare("
        SELECT 
            sfd.*,
            us.kod,
            us.barkod,
            us.ad as urun_adi
        FROM satis_fatura_detay sfd
        JOIN urun_stok us ON sfd.urun_id = us.id
        WHERE sfd.fatura_id = ?
    ");
    $stmt->execute([$invoiceId]);
    $fatura_detay = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Kullanılan puan bilgisi
    $stmt = $conn->prepare("
        SELECT harcanan_puan 
        FROM puan_harcama 
        WHERE fatura_id = ?
    ");
    $stmt->execute([$invoiceId]);
    $kullanilan_puan = $stmt->fetchColumn() ?: 0;
    
    // Kazanılan puan bilgisi
    $stmt = $conn->prepare("
        SELECT kazanilan_puan 
        FROM puan_kazanma 
        WHERE fatura_id = ?
    ");
    $stmt->execute([$invoiceId]);
    $kazanilan_puan = $stmt->fetchColumn() ?: 0;
    
} catch (PDOException $e) {
    echo "Veritabanı hatası: " . $e->getMessage();
    exit;
}

// Ödeme türünü Türkçeleştir
$odeme_turu_tr = [
    'nakit' => 'Nakit',
    'kredi_karti' => 'Kredi Kartı',
    'borc' => 'Borç'
][$fatura['odeme_turu']] ?? $fatura['odeme_turu'];

// İşlem türünü Türkçeleştir
$islem_turu_tr = [
    'satis' => 'Satış',
    'iade' => 'İade'
][$fatura['islem_turu']] ?? $fatura['islem_turu'];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiş #<?= $fatura['fatura_no'] ?></title>
    <style>
        @media print {
            body {
                font-family: 'Courier New', monospace;
                font-size: 10pt;
                line-height: 1.3;
                width: 80mm;
                margin: 0;
                padding: 5mm;
            }
            
            .header {
                text-align: center;
                margin-bottom: 10px;
            }
            
            .store-name {
                font-size: 14pt;
                font-weight: bold;
            }
            
            .divider {
                border-bottom: 1px dashed #000;
                margin: 5px 0;
            }
            
            .invoice-info {
                margin-bottom: 10px;
            }
            
            .invoice-info div {
                margin-bottom: 3px;
            }
            
            .items {
                width: 100%;
                border-collapse: collapse;
            }
            
            .items th {
                text-align: left;
                border-bottom: 1px solid #000;
                padding-bottom: 2px;
            }
            
            .items td {
                padding: 5px;
            }
            
            .item-total {
                text-align: right;
            }
            
            .totals {
                margin-top: 10px;
                text-align: right;
            }
            
            .totals div {
                margin-bottom: 3px;
            }
            
            .total-line {
                font-weight: bold;
            }
            
            .footer {
                margin-top: 15px;
                text-align: center;
                font-size: 9pt;
            }
            
            .barcode {
                text-align: center;
                margin: 10px 0;
            }
            
            .print-button {
                display: none;
            }
        }
        
        /* Ekran görünümü için */
        @media screen {
            body {
                font-family: 'Courier New', monospace;
                font-size: 12pt;
                line-height: 1.3;
                width: 80mm;
                margin: 20px auto;
                padding: 10px;
                border: 1px solid #ccc;
                box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            }
            
            .print-button {
                display: block;
                margin: 20px auto;
                padding: 10px 20px;
                background-color: #007bff;
                color: white;
                border: none;
                border-radius: 5px;
                cursor: pointer;
                font-size: 14px;
            }
            
            .print-button:hover {
                background-color: #0056b3;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="store-name">İnci Kırtasiye - <?= htmlspecialchars($fatura['magaza_adi']) ?></div>
        <div>İnciPos</div>
    </div>
    
    <div class="divider"></div>
    
    <div class="invoice-info">
        <div><strong>Fiş No:</strong> <?= htmlspecialchars($fatura['fatura_seri'] . $fatura['fatura_no']) ?></div>
        <div><strong>Tarih:</strong> <?= date('d.m.Y H:i', strtotime($fatura['fatura_tarihi'])) ?></div>
        <div><strong>Kasiyer:</strong> <?= htmlspecialchars($fatura['personel_adi']) ?></div>
        <?php if ($fatura['musteri_adi']): ?>
        <div><strong>Müşteri:</strong> <?= htmlspecialchars($fatura['musteri_adi']) ?></div>
        <?php endif; ?>
        <div><strong>İşlem:</strong> <?= $islem_turu_tr ?></div>
    </div>
    
    <div class="divider"></div>
    
    <table class="items">
        <thead>
            <tr>
                <th>Ürün</th>
                <th>Miktar</th>
                <th>Fiyat</th>
                <th>Toplam</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($fatura_detay as $item): ?>
            <tr>
                <td><?= htmlspecialchars($item['urun_adi']) ?></td>
                <td><?= $item['miktar'] ?></td>
                <td><?= number_format($item['birim_fiyat'], 2, ',', '.') ?></td>
                <td class="item-total"><?= number_format($item['toplam_tutar'], 2, ',', '.') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <div class="divider"></div>
    
    <div class="totals">
        <div><strong>Ara Toplam:</strong> <?= number_format($fatura['toplam_tutar'] + $fatura['indirim_tutari'], 2, ',', '.') ?> TL</div>
        <?php if ($fatura['indirim_tutari'] > 0): ?>
        <div><strong>İndirim:</strong> <?= number_format($fatura['indirim_tutari'], 2, ',', '.') ?> TL</div>
        <?php endif; ?>
        <?php if ($kullanilan_puan > 0): ?>
        <div><strong>Kullanılan Puan:</strong> <?= number_format($kullanilan_puan, 2, ',', '.') ?> Puan</div>
        <?php endif; ?>
        <div class="total-line"><strong>Genel Toplam:</strong> <?= number_format($fatura['toplam_tutar'], 2, ',', '.') ?> TL</div>
        <div><strong>Ödeme Türü:</strong> <?= $odeme_turu_tr ?></div>
        <?php if ($fatura['odeme_turu'] === 'kredi_karti' && $fatura['kredi_karti_banka']): ?>
        <div><strong>Banka:</strong> <?= htmlspecialchars($fatura['kredi_karti_banka']) ?></div>
        <?php endif; ?>
    </div>
    
    <?php if ($kazanilan_puan > 0): ?>
    <div class="divider"></div>
    <div style="text-align: center; margin-top: 5px;">
        <strong>Bu alışverişten kazandığınız puan:</strong><br>
        <?= number_format($kazanilan_puan, 2, ',', '.') ?> Puan
    </div>
    <?php endif; ?>
    
    <div class="divider"></div>
    
    <div class="footer">
        Bizi tercih ettiğiniz için teşekkür ederiz.<br>
        <?= date('d.m.Y H:i:s') ?>
    </div>
    
    <div class="barcode">
        *<?= $fatura['fatura_no'] ?>*
    </div>
    
    <button class="print-button" onclick="window.print()">Yazdır</button>
    
    <script>
        // Sayfa yüklendiğinde otomatik yazdırma diyaloğu aç
        window.onload = function() {
            // Yazdırma işlemini 500ms geciktir (sayfanın tam yüklenmesi için)
            setTimeout(function() {
                window.print();
            }, 500);
        };
    </script>
</body>
</html>