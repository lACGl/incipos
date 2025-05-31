<?php
session_start();
require_once '../db_connection.php';

// Yetki kontrolü
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

try {
    // POST verileri kontrolü
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Geçersiz istek metodu');
    }

    // ID kontrolü
    if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
        throw new Exception('Geçersiz müşteri ID');
    }

    $customer_id = (int)$_POST['id'];

    // Zorunlu alanları kontrol et
    $required_fields = ['ad', 'soyad', 'telefon'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception($field . ' alanı zorunludur');
        }
    }

    // Telefon numarası formatını kontrol et (sadece rakamlardan oluşmalı)
    $telefon = preg_replace('/[^0-9]/', '', $_POST['telefon']);
    if (strlen($telefon) !== 10 && strlen($telefon) !== 11) {
        throw new Exception('Geçerli bir telefon numarası giriniz (10 veya 11 haneli)');
    }

    // Telefon numarası benzersiz mi kontrol et (aynı müşteri hariç)
    $stmt = $conn->prepare("SELECT id FROM musteriler WHERE telefon = ? AND id != ?");
    $stmt->execute([$telefon, $customer_id]);
    if ($stmt->fetch()) {
        throw new Exception('Bu telefon numarası ile kayıtlı başka bir müşteri var');
    }

    // Barkod/müşteri kart numarası kontrol et
    $barkod = !empty($_POST['barkod']) ? $_POST['barkod'] : null;

    // Barkod belirtilmişse benzersiz olduğunu kontrol et (aynı müşteri hariç)
    if ($barkod) {
        $stmt = $conn->prepare("SELECT id FROM musteriler WHERE barkod = ? AND id != ?");
        $stmt->execute([$barkod, $customer_id]);
        if ($stmt->fetch()) {
            throw new Exception('Bu barkod/kart numarası ile kayıtlı başka bir müşteri var');
        }
    }

    // Diğer alanları al
    $ad = trim($_POST['ad']);
    $soyad = trim($_POST['soyad']);
    $email = !empty($_POST['email']) ? trim($_POST['email']) : null;
    $email = !empty($_POST['email']) ? trim($_POST['email']) : null;
    $sms_aktif = isset($_POST['sms_aktif']) ? 1 : 0;
    $durum = isset($_POST['durum']) && in_array($_POST['durum'], ['aktif', 'pasif']) ? $_POST['durum'] : 'aktif';

    // Veritabanında güncelle
    $stmt = $conn->prepare("
        UPDATE musteriler 
        SET ad = ?, soyad = ?, telefon = ?, email = ?, email = ?, barkod = ?, sms_aktif = ?, durum = ?
        WHERE id = ?
    ");
    $stmt->execute([$ad, $soyad, $telefon, $email, $email, $barkod, $sms_aktif, $durum, $customer_id]);

    // Başarılı yanıt
    echo json_encode([
        'success' => true, 
        'message' => 'Müşteri başarıyla güncellendi',
        'customer_id' => $customer_id
    ]);

} catch (PDOException $e) {
    // Veritabanı hatası
    error_log('PDO Hatası: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Veritabanı hatası: ' . $e->getMessage()]);
} catch (Exception $e) {
    // Genel hata
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}