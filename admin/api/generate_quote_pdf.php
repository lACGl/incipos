<?php
require_once '../session_manager.php'; // Otomatik eklendi
secure_session_start();
require_once '../db_connection.php';

// TCPDF kütüphanesini doğrudan dahil et - belirtilen yoldan
require_once(__DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php');

header('Content-Type: application/json');

// Yetki kontrolü
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

// Sipariş ID kontrolü
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz sipariş ID']);
    exit;
}

$siparis_id = intval($_GET['id']);

try {
    // Sipariş bilgilerini al
    $order_query = "SELECT s.*, t.ad AS tedarikci_adi, t.telefon, t.eposta, t.adres
                   FROM siparisler s
                   LEFT JOIN tedarikciler t ON s.tedarikci_id = t.id
                   WHERE s.id = :siparis_id";
                   
    $stmt = $conn->prepare($order_query);
    $stmt->bindParam(':siparis_id', $siparis_id);
    $stmt->execute();
    $siparis = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$siparis) {
        echo json_encode(['success' => false, 'message' => 'Sipariş bulunamadı']);
        exit;
    }
    
    // Sipariş detaylarını al
    $detail_query = "SELECT sd.*, us.ad AS urun_adi, us.kod, us.barkod,
                    COALESCE(us.alis_fiyati, 0) AS mevcut_alis_fiyati
                    FROM siparis_detay sd
                    JOIN urun_stok us ON sd.urun_id = us.id
                    WHERE sd.siparis_id = :siparis_id";
                    
    $stmt = $conn->prepare($detail_query);
    $stmt->bindParam(':siparis_id', $siparis_id);
    $stmt->execute();
    $urunler = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($urunler)) {
        echo json_encode(['success' => false, 'message' => 'Sipariş ürünleri bulunamadı']);
        exit;
    }
    
    // Bugünün tarihini al
    $bugun = date('d.m.Y');
    
    // Şirket bilgilerini al (varsayılan değerler)
    $sirket_adi = "İnci Kırtasiye";
    $sirket_adresi = "Adres bilgisi";
    $sirket_telefon = "Telefon bilgisi";
    $sirket_email = "E-posta bilgisi";
    
    // Sistem ayarlarından şirket bilgilerini al (eğer mevcutsa)
    $settings_query = "SELECT anahtar, deger FROM sistem_ayarlari 
                      WHERE anahtar IN ('sirket_adi', 'sirket_adresi', 'sirket_telefon', 'sirket_email')";
    $settings_result = $conn->query($settings_query);
    $settings = [];

    if ($settings_result) {
        while ($row = $settings_result->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['anahtar']] = $row['deger'];
        }
    }
    
    if (!empty($settings['sirket_adi'])) $sirket_adi = $settings['sirket_adi'];
    if (!empty($settings['sirket_adresi'])) $sirket_adresi = $settings['sirket_adresi'];
    if (!empty($settings['sirket_telefon'])) $sirket_telefon = $settings['sirket_telefon'];
    if (!empty($settings['sirket_email'])) $sirket_email = $settings['sirket_email'];
    
    // TCPDF kullanarak PDF oluştur
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // PDF bilgilerini ayarla
    $pdf->SetCreator($sirket_adi);
    $pdf->SetAuthor($sirket_adi);
    $pdf->SetTitle('Teklif Formu - #' . $siparis_id);
    $pdf->SetSubject('Teklif Formu');
    
    // Varsayılan başlık ve altbilgileri kaldır
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Varsayılan font ayarla
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
    
    // Kenar boşluklarını ayarla
    $pdf->SetMargins(15, 15, 15);
    
    // Otomatik sayfa kesmesini ayarla
    $pdf->SetAutoPageBreak(TRUE, 15);
    
    // Font ölçeklendirme faktörünü ayarla
    $pdf->setFontSubsetting(true);
    
    // Font ayarla
    $pdf->SetFont('dejavusans', '', 10);
    
    // Yeni sayfa ekle
    $pdf->AddPage();
    
    // HTML içeriği oluştur
    $html = '
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
            text-align: left;
            padding: 8px;
            border: 1px solid #ddd;
        }
        td {
            padding: 8px;
            border: 1px solid #ddd;
        }
        .header {
            font-size: 20px;
            font-weight: bold;
            color: #333;
            margin-bottom: 20px;
        }
        .info {
            margin-bottom: 20px;
        }
        .info table {
            border: none;
        }
        .info td {
            border: none;
            padding: 3px;
        }
        .footer {
            margin-top: 20px;
            font-size: 12px;
            color: #777;
        }
        .right {
            text-align: right;
        }
        .center {
            text-align: center;
        }
    </style>
    
    <div class="header">
        <div style="text-align: center;">' . $sirket_adi . ' - Teklif Formu</div>
    </div>
    
    <div class="info">
        <table>
            <tr>
                <td width="50%"><strong>Sipariş No:</strong> #' . $siparis_id . '</td>
                <td width="50%"><strong>Tarih:</strong> ' . $bugun . '</td>
            </tr>
            <tr>
                <td><strong>Gönderen:</strong> ' . $sirket_adi . '</td>
                <td><strong>Telefon:</strong> ' . $sirket_telefon . '</td>
            </tr>
            <tr>
                <td><strong>E-posta:</strong> ' . $sirket_email . '</td>
                <td><strong>Adres:</strong> ' . $sirket_adresi . '</td>
            </tr>
        </table>
    </div>
    
    <div style="margin-top: 20px; margin-bottom: 10px; font-weight: bold; font-size: 14px;">Teklif Edilecek Ürünler</div>
    
    <table cellpadding="4">
        <tr>
            <th width="10%">#</th>
            <th width="15%">Kod/Barkod</th>
            <th width="35%">Ürün Adı</th>
            <th width="10%">Miktar</th>
            <th width="15%">Birim Fiyat (₺)</th>
            <th width="15%">Toplam (₺)</th>
        </tr>';
    
    $toplam_tutar = 0;
    $sira = 1;
    
    foreach ($urunler as $urun) {
        $birim_fiyat = number_format(0, 2, ',', '.'); // Fiyat teklifi için boş bırak
        $toplam = number_format(0, 2, ',', '.');
        
        $html .= '
        <tr>
            <td>' . $sira . '</td>
            <td>' . (!empty($urun['kod']) ? $urun['kod'] : $urun['barkod']) . '</td>
            <td>' . $urun['urun_adi'] . '</td>
            <td>' . $urun['miktar'] . '</td>
            <td class="right">___________</td>
            <td class="right">___________</td>
        </tr>';
        
        $sira++;
    }
    
    $html .= '
        <tr>
            <td colspan="4" class="right"><strong>Ara Toplam:</strong></td>
            <td colspan="2" class="right">___________</td>
        </tr>
        <tr>
            <td colspan="4" class="right"><strong>KDV (%):</strong></td>
            <td colspan="2" class="right">___________</td>
        </tr>
        <tr>
            <td colspan="4" class="right"><strong>Genel Toplam:</strong></td>
            <td colspan="2" class="right">___________</td>
        </tr>
    </table>
    
    <div style="margin-top: 30px;">
        <p><strong>Notlar:</strong></p>
        <p>1. Lütfen teklif formunu doldurarak en kısa sürede tarafımıza geri gönderiniz.</p>
        <p>2. Bu teklif formunda belirtilen ürünler için fiyat teklifinizi bekliyoruz.</p>
        <p>3. Teklif geçerlilik süresi lütfen belirtiniz.</p>
        <p>4. Ödeme ve teslimat koşullarını lütfen belirtiniz.</p>
    </div>
    
    <div style="margin-top: 30px;">
        <table>
            <tr>
                <td width="50%">
                    <strong>Teklif Veren:</strong><br><br>
                    İmza: _____________________<br><br>
                    İsim: ______________________<br><br>
                    Tarih: _____________________
                </td>
                <td width="50%">
                    <strong>Onaylayan:</strong><br><br>
                    İmza: _____________________<br><br>
                    İsim: ______________________<br><br>
                    Tarih: _____________________
                </td>
            </tr>
        </table>
    </div>
    
    <div class="footer" style="margin-top: 30px; text-align: center;">
        <p>' . $sirket_adi . ' - ' . $sirket_telefon . ' - ' . $sirket_email . '</p>
    </div>';
    
    // HTML içeriğini PDF'e yaz
    $pdf->writeHTML($html, true, false, true, false, '');
    
    // PDF'i sonlandır ve indir
    $pdf_file = 'teklif_formu_' . $siparis_id . '_' . date('Ymd') . '.pdf';
    
    // Kök dizini bul ve PDF dizinini oluştur
    $root_path = dirname(dirname(__DIR__));
    $pdf_dir = $root_path . '/files/pdf/orders';
    
    // Dizin yoksa oluştur
    if (!file_exists($pdf_dir)) {
        mkdir($pdf_dir, 0755, true);
    }
    
    // Dosya yolu
    $file_path = $pdf_dir . '/' . $pdf_file;
    $pdf->Output($file_path, 'F');
    
    // Web erişimi için yol
    $web_path = '../../../files/pdf/orders/' . $pdf_file;
    
    // Başarılı yanıt
    echo json_encode([
        'success' => true,
        'message' => 'Teklif formu başarıyla oluşturuldu',
        'file_name' => $pdf_file,
        'file_path' => $web_path
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Teklif formu oluşturulurken bir hata oluştu: ' . $e->getMessage()
    ]);
}
?>