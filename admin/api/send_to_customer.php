<?php
// Header'ları ayarla
header('Content-Type: application/json');

// Veritabanı bağlantısını dahil et
require_once '../db_connection.php';

// PHPMailer için gerekli dosyalar
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$docRoot = $_SERVER['DOCUMENT_ROOT'];
require_once $docRoot . '/vendor/phpmailer/phpmailer/src/Exception.php';
require_once $docRoot . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once $docRoot . '/vendor/phpmailer/phpmailer/src/SMTP.php';

// NetGSM SMS Gönderme Fonksiyonu (Yeni REST API ile)
function sendSms($phoneNumber, $message) {
    $userCode = '4526060578'; // Netgsm kullanıcı kodu
    $password = 'M1-43nvE';    // Netgsm şifresi
    $msgHeader = 'INCIKIRTSYE'; // Mesaj başlığı
    
    // REST API format
    $data = [
        "msgheader" => $msgHeader,
        "messages" => [
            [
                "msg" => $message,
                "no" => $phoneNumber
            ]
        ],
        "encoding" => "TR",
        "iysfilter" => "0" // Bilgilendirme amaçlı SMS
    ];
    
    // API URL'si
    $url = "https://api.netgsm.com.tr/sms/rest/v2/send";
    
    // cURL başlat
    $ch = curl_init();
    
    // cURL ayarları
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode($userCode . ':' . $password)
    ]);
    
    // API isteğini gönder
    $response = curl_exec($ch);
    
    // Hata kontrolü
    if (curl_errno($ch)) {
        error_log('NetGSM SMS gönderim hatası: ' . curl_error($ch));
        curl_close($ch);
        return false;
    }
    
    curl_close($ch);
    
    // Yanıtı JSON olarak parse et
    $responseData = json_decode($response, true);
    
    // Başarı kontrolü
    if (isset($responseData['jobid']) && !empty($responseData['jobid'])) {
        return $responseData['jobid']; // Başarılı gönderimde jobid döndür
    } else {
        error_log('NetGSM SMS gönderim yanıtı: ' . $response);
        return false;
    }
}

function sendEmail($to, $subject, $body) {
    $mail = new PHPMailer(true);
    
    try {
        // Debug için (sorunu tespit ettikten sonra kaldırabilirsiniz)
        $mail->SMTPDebug = 0;
        
        // SMTP ayarları
        $mail->isSMTP();
        $mail->Host       = 'mail.incikirtasiye.com'; // Prestashop'ta çalışan sunucu
        $mail->SMTPAuth   = true;
        $mail->Username   = 'muhasebe@incikirtasiye.com'; // Prestashop'ta çalışan kullanıcı adı
        $mail->Password   = ',m~a%UzeCokd'; // Prestashop'ta kullandığınız şifreyi girin
        $mail->SMTPSecure = ''; // Şifreleme yok (Hiçbiri seçeneği)
        $mail->Port       = 587;
        
        // SSL doğrulamasını devre dışı bırak (önemli!)
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Gönderici ve alıcı
        $mail->setFrom('muhasebe@incikirtasiye.com', 'İnci Kırtasiye');
        $mail->addAddress($to);
        
        // İçerik
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body);
        $mail->CharSet = 'UTF-8';
        
        // Gönder
        $mail->send();
        error_log('Mail gönderimi başarılı: ' . $to);
        return true;
    } catch (Exception $e) {
        error_log('Mail gönderim hatası: ' . $mail->ErrorInfo);
        return false;
    }
}

// JSON verilerini al
$input = json_decode(file_get_contents('php://input'), true);

// Gerekli alan kontrolü
if (!isset($input['invoice_id']) || empty($input['invoice_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Fatura ID eksik'
    ]);
    exit;
}

// Değişkenleri ata
$invoice_id = intval($input['invoice_id']);
$customer_id = isset($input['customer_id']) ? intval($input['customer_id']) : null;
$method = $input['method'] ?? 'email';
$email = $input['email'] ?? '';
$phone = $input['phone'] ?? '';
$message = $input['message'] ?? '';

try {
    // Fatura bilgilerini al
    $query = "SELECT sf.*, m.ad as magaza_adi, mus.ad as musteri_adi, mus.soyad as musteri_soyad 
             FROM satis_faturalari sf
             LEFT JOIN magazalar m ON sf.magaza = m.id
             LEFT JOIN musteriler mus ON sf.musteri_id = mus.id
             WHERE sf.id = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$invoice) {
        echo json_encode([
            'success' => false,
            'message' => 'Fatura bulunamadı'
        ]);
        exit;
    }
    
    // Gönderim işlemi 
    if ($method === 'email' && !empty($email)) {
        // Benzersiz token oluştur (e-posta için)
        $token = md5(uniqid() . $invoice_id . time() . 'email');
        
        // Tokeni veritabanına kaydet
        $token_query = "INSERT INTO fatura_erisim_token (fatura_id, token, olusturma_tarihi, son_gecerlilik) 
                      VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY))";
        $token_stmt = $conn->prepare($token_query);
        $token_stmt->execute([$invoice_id, $token]);
        
        // Erişim linkini oluştur
        $base_url = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'];
        $access_link = $base_url . "/admin/view_public_invoice.php?token=" . $token;
        
        // Müşteri adı
        $customer_name = !empty($invoice['musteri_adi']) ? $invoice['musteri_adi'] . ' ' . $invoice['musteri_soyad'] : 'Sayın Müşterimiz';
        
        // E-posta gönderme işlemi
        $subject = 'Fiş Bilgileriniz: ' . $invoice['fatura_seri'] . $invoice['fatura_no'];
        
        // HTML formatında e-posta içeriği
        $body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { text-align: center; margin-bottom: 20px; }
                .btn { display: inline-block; padding: 10px 15px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 4px; }
                .footer { margin-top: 30px; font-size: 12px; color: #777; }
                .invoice-details { margin: 20px 0; padding: 15px; background-color: #f8f9fa; border-radius: 4px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>İnci Kırtasiye</h2>
                </div>
                
                <p>{$customer_name},</p>
                
                <p>İnci Kırtasiye {$invoice['magaza_adi']} şubesinden yapmış olduğunuz alışverişe ait fiş bilgileriniz aşağıdaki linkte yer almaktadır.</p>
                
                <div style='text-align: center; margin: 25px 0;'>
                    <a href='{$access_link}' class='btn'>Fişi Görüntüle</a>
                </div>";
        
        if (!empty($message)) {
            $body .= "<p><strong>Notumuz:</strong> " . htmlspecialchars($message) . "</p>";
        }
        
        $body .= "
                <div class='invoice-details'>
                    <p><strong>Fiş No:</strong> {$invoice['fatura_seri']}{$invoice['fatura_no']}</p>
                    <p><strong>Tarih:</strong> " . date('d.m.Y', strtotime($invoice['fatura_tarihi'])) . "</p>
                    <p><strong>Toplam Tutar:</strong> " . number_format($invoice['net_tutar'], 2, ',', '.') . " TL</p>
                </div>
                
                <div class='footer'>
                    <p>Saygılarımızla,<br>İnci Kırtasiye</p>
                    <b>**MALİ DEĞERİ YOKTUR**</b>
                </div>
            </div>
        </body>
        </html>";
        
        // E-posta gönder
        $emailSent = sendEmail($email, $subject, $body);
        
        // Log kaydı
        $log_query = "INSERT INTO iletisim_log (tur, alici, konu, icerik, fatura_id, musteri_id, tarih, erisim_link) 
                      VALUES ('email', ?, ?, ?, ?, ?, NOW(), ?)";
        $log_stmt = $conn->prepare($log_query);
        $log_stmt->execute([$email, $subject, $body, $invoice_id, $customer_id, $access_link]);
        
        if ($emailSent) {
            echo json_encode([
                'success' => true,
                'message' => 'Fatura e-posta ile gönderildi',
                'link' => $access_link
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'E-posta gönderilirken bir hata oluştu. Lütfen tekrar deneyin.',
                'link' => $access_link
            ]);
        }
    } 
    else if ($method === 'sms' && !empty($phone)) {
        // Benzersiz token oluştur (SMS için)
        $token = md5(uniqid() . $invoice_id . time() . 'sms');
        
        // Tokeni veritabanına kaydet
        $token_query = "INSERT INTO fatura_erisim_token (fatura_id, token, olusturma_tarihi, son_gecerlilik) 
                      VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY))";
        $token_stmt = $conn->prepare($token_query);
        $token_stmt->execute([$invoice_id, $token]);
        
        // Erişim linkini oluştur
        $base_url = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'];
        $access_link = $base_url . "/admin/view_public_invoice.php?token=" . $token;
        
        // SMS metnini hazırla
        $sms_text = "Fatura: " . $invoice['fatura_seri'] . $invoice['fatura_no'];
        
        if (!empty($message)) {
            $sms_text .= " - " . $message;
        }
        
        // Kısa SMS için link ekle
        $sms_text .= " Goruntuleme linki: " . $access_link;
        
        // NetGSM ile SMS gönder
        $phoneNumber = preg_replace('/[^0-9]/', '', $phone); // Sadece rakamları al
        
        // Türkiye telefon numarası formatı kontrolü
        if (strlen($phoneNumber) === 10) { // 5XXXXXXXXX formatı
            $phoneNumber = "90" . $phoneNumber; // Başına 90 ekle
        } else if (strlen($phoneNumber) === 11 && substr($phoneNumber, 0, 1) === "0") { // 05XXXXXXXXX formatı
            $phoneNumber = "9" . $phoneNumber; // 0 yerine 9 koy
        } else if (strlen($phoneNumber) < 10) {
            echo json_encode([
                'success' => false,
                'message' => 'Geçersiz telefon numarası formatı'
            ]);
            exit;
        }
        
        // SMS gönder
        $smsResponse = sendSms($phoneNumber, $sms_text);
        
        // NetGSM yanıtını kontrol et
        if ($smsResponse !== false) {
            // Job ID'yi içeriğe ekle
            $content_with_jobid = $sms_text . " (JobID: " . $smsResponse . ")";
            
            // Log kaydı - mevcut tablo yapısına göre
            $log_query = "INSERT INTO iletisim_log (tur, alici, icerik, fatura_id, musteri_id, tarih, erisim_link) 
                        VALUES ('sms', ?, ?, ?, ?, NOW(), ?)";
            $log_stmt = $conn->prepare($log_query);
            $log_stmt->execute([$phone, $content_with_jobid, $invoice_id, $customer_id, $access_link]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Fatura SMS ile gönderildi',
                'link' => $access_link,
                'sms_jobid' => $smsResponse
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'SMS gönderiminde hata oluştu',
                'link' => $access_link
            ]);
        }
    } 
    else {
        echo json_encode([
            'success' => false,
            'message' => 'Geçersiz gönderim yöntemi veya eksik iletişim bilgisi'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Bir hata oluştu: ' . $e->getMessage()
    ]);
}
?>