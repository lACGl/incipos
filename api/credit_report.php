<?php
include 'header.php';
require_once 'db_connection.php';

// Kullanıcı giriş kontrolü
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: index.php");
    exit;
}

// Mağazaları getir
$stmt = $conn->query("SELECT id, ad FROM magazalar ORDER BY ad");
$magazalar = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Son 30 günlük varsayılan tarih aralığı
$defaultDateFrom = date('Y-m-d', strtotime('-30 days'));
$defaultDateTo = date('Y-m-d');
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Borç Tahsilatı Raporu</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdeliv