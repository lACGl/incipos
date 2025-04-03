-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Anamakine: 127.0.0.1
-- Üretim Zamanı: 26 Mar 2025, 12:40:12
-- Sunucu sürümü: 10.4.32-MariaDB
-- PHP Sürümü: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Veritabanı: `incikir2_pos`
--

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `admin_user`
--

CREATE TABLE `admin_user` (
  `id` int(11) NOT NULL,
  `kullanici_adi` varchar(50) NOT NULL,
  `sifre` varchar(255) NOT NULL,
  `telefon_no` varchar(15) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `admin_user`
--

INSERT INTO `admin_user` (`id`, `kullanici_adi`, `sifre`, `telefon_no`) VALUES
(1, 'admin', '$2y$10$zQDDmeksqcPhn9Br.cGVfunnt8w8Di8jFlMYP5D1XdRTn293br3jO', '05424585252');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `alis_faturalari`
--

CREATE TABLE `alis_faturalari` (
  `id` int(11) NOT NULL,
  `fatura_tipi` enum('satis','iade') DEFAULT 'satis',
  `magaza` int(11) DEFAULT NULL,
  `fatura_seri` varchar(20) DEFAULT NULL,
  `fatura_no` varchar(20) DEFAULT NULL,
  `fatura_tarihi` date DEFAULT NULL,
  `irsaliye_no` varchar(50) DEFAULT NULL,
  `irsaliye_tarihi` date DEFAULT NULL,
  `siparis_no` varchar(50) DEFAULT NULL,
  `siparis_tarihi` date DEFAULT NULL,
  `tedarikci` int(11) DEFAULT NULL,
  `durum` enum('bos','urun_girildi','kismi_aktarildi','aktarildi') DEFAULT 'bos',
  `toplam_tutar` decimal(10,2) DEFAULT 0.00,
  `kdv_tutari` decimal(10,2) DEFAULT 0.00,
  `net_tutar` decimal(10,2) DEFAULT 0.00,
  `aciklama` text DEFAULT NULL,
  `kayit_tarihi` datetime DEFAULT current_timestamp(),
  `kullanici_id` int(11) DEFAULT NULL,
  `aktarim_tarihi` datetime DEFAULT NULL,
  `aktarilan_miktar` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `alis_faturalari`
--

INSERT INTO `alis_faturalari` (`id`, `fatura_tipi`, `magaza`, `fatura_seri`, `fatura_no`, `fatura_tarihi`, `irsaliye_no`, `irsaliye_tarihi`, `siparis_no`, `siparis_tarihi`, `tedarikci`, `durum`, `toplam_tutar`, `kdv_tutari`, `net_tutar`, `aciklama`, `kayit_tarihi`, `kullanici_id`, `aktarim_tarihi`, `aktarilan_miktar`) VALUES
(6, 'satis', NULL, 'f', '1', '2025-02-03', NULL, NULL, NULL, NULL, 7, 'bos', 7200.00, 72.00, 7128.00, '', '2025-02-05 16:38:46', 1, NULL, 0.00),
(7, 'satis', NULL, 'f', '2', '1111-01-01', NULL, NULL, NULL, NULL, 7, 'bos', 0.00, 0.00, 0.00, '', '2025-02-23 14:56:43', 1, NULL, 0.00),
(8, 'satis', NULL, 'f', '3', '2025-02-20', NULL, NULL, NULL, NULL, 7, 'kismi_aktarildi', 2076.00, 375.60, 1700.40, '', '2025-02-23 14:59:35', 1, '2025-02-26 15:01:31', 3.00),
(10, 'iade', NULL, 'f', '5', '2025-02-28', '', '0000-00-00', '', '0000-00-00', 7, 'urun_girildi', 279.50, 50.31, 229.19, '', '2025-02-27 19:06:00', 1, NULL, 0.00),
(11, 'satis', NULL, 'f', '6', '2025-03-01', NULL, NULL, NULL, NULL, 7, 'aktarildi', 360.00, 0.00, 360.00, '', '2025-03-02 13:27:31', 1, '2025-03-09 21:58:49', 1.00),
(13, 'satis', NULL, 'F', '7', '2025-03-04', NULL, NULL, NULL, NULL, 7, 'bos', 1818.00, 18.18, 1799.82, '', '2025-03-03 11:17:40', 1, NULL, 0.00);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `alis_fatura_aktarim`
--

CREATE TABLE `alis_fatura_aktarim` (
  `id` int(11) NOT NULL,
  `fatura_id` int(11) NOT NULL,
  `magaza_id` int(11) NOT NULL,
  `aktarim_tarihi` datetime DEFAULT current_timestamp(),
  `kullanici_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `alis_fatura_detay`
--

CREATE TABLE `alis_fatura_detay` (
  `id` int(11) NOT NULL,
  `fatura_id` int(11) NOT NULL,
  `urun_id` int(11) NOT NULL,
  `miktar` int(11) NOT NULL,
  `birim_fiyat` decimal(10,2) NOT NULL,
  `iskonto1` int(3) DEFAULT 0,
  `iskonto2` int(3) DEFAULT 0,
  `iskonto3` int(3) DEFAULT 0,
  `kdv_orani` int(3) DEFAULT NULL,
  `toplam_tutar` decimal(10,2) NOT NULL,
  `kayit_tarihi` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `alis_fatura_detay`
--

INSERT INTO `alis_fatura_detay` (`id`, `fatura_id`, `urun_id`, `miktar`, `birim_fiyat`, `iskonto1`, `iskonto2`, `iskonto3`, `kdv_orani`, `toplam_tutar`, `kayit_tarihi`) VALUES
(94, 8, 1064, 10, 40.00, 10, 0, 0, 10, 396.00, '2025-02-26 14:45:46'),
(95, 8, 1069, 5, 155.00, 20, 0, 0, 20, 744.00, '2025-02-26 14:45:46'),
(96, 8, 1105, 10, 78.00, 0, 0, 0, 20, 936.00, '2025-02-26 14:45:46'),
(110, 10, 1059, 1, 7.50, 0, 0, 0, 18, 7.50, '2025-02-27 19:06:30'),
(111, 10, 1062, 1, 120.00, 0, 0, 0, 18, 120.00, '2025-02-27 19:06:30'),
(112, 10, 1073, 1, 30.00, 0, 0, 0, 18, 30.00, '2025-02-27 19:06:30'),
(113, 10, 1071, 1, 122.00, 0, 0, 0, 18, 122.00, '2025-02-27 19:06:30'),
(114, 11, 1096, 1, 360.00, 0, 0, 0, 0, 360.00, '2025-03-03 11:03:43'),
(117, 6, 1113, 75, 96.00, 0, 0, 0, 1, 7200.00, '2025-03-03 20:25:43'),
(118, 13, 1096, 5, 360.00, 0, 0, 0, 1, 1818.00, '2025-03-09 21:59:31');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `alis_fatura_detay_aktarim`
--

CREATE TABLE `alis_fatura_detay_aktarim` (
  `id` int(11) NOT NULL,
  `fatura_id` int(11) NOT NULL,
  `urun_id` int(11) NOT NULL,
  `miktar` decimal(10,2) NOT NULL,
  `kalan_miktar` decimal(10,2) DEFAULT 0.00,
  `aktarim_tarihi` datetime NOT NULL,
  `magaza_id` int(11) NOT NULL,
  `depo_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `alis_fatura_detay_aktarim`
--

INSERT INTO `alis_fatura_detay_aktarim` (`id`, `fatura_id`, `urun_id`, `miktar`, `kalan_miktar`, `aktarim_tarihi`, `magaza_id`, `depo_id`) VALUES
(9, 8, 1105, 1.00, 0.00, '2025-02-26 14:51:30', 7, NULL),
(10, 8, 1105, 1.00, 0.00, '2025-02-26 14:56:02', 6, NULL),
(11, 8, 1105, 1.00, 0.00, '2025-02-26 15:01:31', 6, NULL),
(12, 11, 1096, 1.00, 0.00, '2025-03-09 21:58:49', 7, NULL);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `alis_fatura_log`
--

CREATE TABLE `alis_fatura_log` (
  `id` int(11) NOT NULL,
  `fatura_id` int(11) DEFAULT NULL,
  `islem_tipi` varchar(50) DEFAULT NULL,
  `aciklama` text DEFAULT NULL,
  `kullanici_id` int(11) DEFAULT NULL,
  `tarih` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `alis_fatura_log`
--

INSERT INTO `alis_fatura_log` (`id`, `fatura_id`, `islem_tipi`, `aciklama`, `kullanici_id`, `tarih`) VALUES
(1, 7, 'olusturma', 'Fatura oluşturuldu', 1, '2025-02-23 14:56:43'),
(2, 8, 'olusturma', 'Fatura oluşturuldu', 1, '2025-02-23 14:59:35'),
(3, 9, 'olusturma', 'Fatura oluşturuldu', 1, '2025-02-23 15:30:33'),
(4, 10, 'olusturma', 'Fatura oluşturuldu', 1, '2025-02-27 19:06:00'),
(5, 11, 'olusturma', 'Fatura oluşturuldu', 1, '2025-03-02 13:27:31'),
(6, 12, 'olusturma', 'Fatura oluşturuldu', 1, '2025-03-03 11:11:32'),
(7, 13, 'olusturma', 'Fatura oluşturuldu', 1, '2025-03-03 11:17:40');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `alt_gruplar`
--

CREATE TABLE `alt_gruplar` (
  `id` int(11) NOT NULL,
  `ad` varchar(100) NOT NULL,
  `ana_grup_id` int(11) NOT NULL,
  `kayit_tarihi` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `alt_gruplar`
--

INSERT INTO `alt_gruplar` (`id`, `ad`, `ana_grup_id`, `kayit_tarihi`) VALUES
(7, '123', 8, '2024-12-14 08:38:50'),
(8, 'Versatil', 9, '2024-12-15 15:08:00');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `ana_gruplar`
--

CREATE TABLE `ana_gruplar` (
  `id` int(11) NOT NULL,
  `ad` varchar(100) NOT NULL,
  `departman_id` int(11) DEFAULT NULL,
  `kayit_tarihi` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `ana_gruplar`
--

INSERT INTO `ana_gruplar` (`id`, `ad`, `departman_id`, `kayit_tarihi`) VALUES
(8, '1234', NULL, '2024-12-14 08:29:03'),
(9, 'Kalem', NULL, '2024-12-15 15:07:51');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `birimler`
--

CREATE TABLE `birimler` (
  `id` int(11) NOT NULL,
  `ad` varchar(50) NOT NULL,
  `kayit_tarihi` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `birimler`
--

INSERT INTO `birimler` (`id`, `ad`, `kayit_tarihi`) VALUES
(13, '1234', '2024-12-14 08:28:54'),
(14, 'Adet', '2024-12-15 15:07:45');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `departmanlar`
--

CREATE TABLE `departmanlar` (
  `id` int(11) NOT NULL,
  `ad` varchar(100) NOT NULL,
  `kayit_tarihi` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `departmanlar`
--

INSERT INTO `departmanlar` (`id`, `ad`, `kayit_tarihi`) VALUES
(9, 'test', '2024-12-14 07:57:27'),
(10, 'sasd', '2024-12-14 08:02:07'),
(11, '1234', '2024-12-14 08:03:30'),
(12, '3', '2024-12-14 08:27:38'),
(13, 'Faber', '2024-12-15 15:07:33');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `depolar`
--

CREATE TABLE `depolar` (
  `id` int(11) NOT NULL,
  `kod` varchar(50) DEFAULT NULL,
  `ad` varchar(100) DEFAULT NULL,
  `adres` varchar(255) DEFAULT NULL,
  `telefon` varchar(20) DEFAULT NULL,
  `depo_tipi` enum('ana_depo','ara_depo') DEFAULT 'ana_depo',
  `durum` enum('aktif','pasif') DEFAULT 'aktif',
  `kayit_tarihi` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `depolar`
--

INSERT INTO `depolar` (`id`, `kod`, `ad`, `adres`, `telefon`, `depo_tipi`, `durum`, `kayit_tarihi`) VALUES
(1, 'Depo1', 'Ana Depo', '', '', 'ara_depo', 'aktif', '2024-12-14 23:25:12');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `depo_stok`
--

CREATE TABLE `depo_stok` (
  `id` int(11) NOT NULL,
  `depo_id` int(11) DEFAULT NULL,
  `urun_id` int(11) NOT NULL,
  `stok_miktari` decimal(10,2) DEFAULT 0.00,
  `min_stok` decimal(10,2) DEFAULT 0.00,
  `max_stok` decimal(10,2) DEFAULT 0.00,
  `son_guncelleme` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `depo_stok`
--

INSERT INTO `depo_stok` (`id`, `depo_id`, `urun_id`, `stok_miktari`, `min_stok`, `max_stok`, `son_guncelleme`) VALUES
(33, 1, 1059, 30.00, 0.00, 0.00, '2025-01-30 10:43:55'),
(34, 1, 1106, 1.00, 0.00, 0.00, '2025-01-30 10:45:29'),
(35, 1, 1110, 1.00, 0.00, 0.00, '2025-02-26 14:39:43'),
(36, 1, 1070, 1.00, 0.00, 0.00, '2025-03-09 21:57:27');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `magazalar`
--

CREATE TABLE `magazalar` (
  `id` int(11) NOT NULL,
  `ad` varchar(100) DEFAULT NULL,
  `adres` varchar(255) DEFAULT NULL,
  `telefon` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `magazalar`
--

INSERT INTO `magazalar` (`id`, `ad`, `adres`, `telefon`) VALUES
(6, 'Merkez', 'Merkez', '1'),
(7, 'Dolunay', 'Dolunay', '2');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `magaza_stok`
--

CREATE TABLE `magaza_stok` (
  `id` int(11) NOT NULL,
  `barkod` varchar(50) DEFAULT NULL,
  `magaza_id` int(11) DEFAULT NULL,
  `stok_miktari` int(11) DEFAULT NULL,
  `satis_fiyati` decimal(10,2) DEFAULT NULL,
  `son_guncelleme` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `magaza_stok`
--

INSERT INTO `magaza_stok` (`id`, `barkod`, `magaza_id`, `stok_miktari`, `satis_fiyati`, `son_guncelleme`) VALUES
(71, '8680001047', 7, 5, 125.00, '2025-01-30 10:39:33'),
(72, '8680001001', 7, -67, 12.50, '2025-03-09 21:55:08'),
(73, '8680001001', 6, 20, 12.50, '2025-03-05 12:48:51'),
(74, '1', 7, 3, 10.00, '2025-01-30 11:08:37'),
(75, '8680001006', 6, 3, 45.00, '2025-02-24 12:12:53'),
(76, 'asdd', 6, 5, 2.00, '2025-02-25 11:06:12'),
(82, '8680001047', 7, 1, NULL, '2025-02-26 14:51:30'),
(83, '8680001047', 6, 1, NULL, '2025-02-26 14:56:02'),
(84, '8680001047', 6, 1, 129.48, '2025-02-26 15:01:31'),
(85, '8690460429217', 6, 9, 120.00, '2025-03-05 12:51:21'),
(86, '8680001038', 7, 1, 432.00, '2025-03-09 21:58:49');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `musteriler`
--

CREATE TABLE `musteriler` (
  `id` int(11) NOT NULL,
  `ad` varchar(50) NOT NULL,
  `soyad` varchar(50) NOT NULL,
  `telefon` varchar(15) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `kayit_tarihi` datetime DEFAULT current_timestamp(),
  `barkod` varchar(255) DEFAULT NULL,
  `sms_aktif` tinyint(1) DEFAULT 1,
  `durum` enum('aktif','pasif') DEFAULT 'aktif'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

--
-- Tablo döküm verisi `musteriler`
--

INSERT INTO `musteriler` (`id`, `ad`, `soyad`, `telefon`, `email`, `kayit_tarihi`, `barkod`, `sms_aktif`, `durum`) VALUES
(48, 'Şebnem', 'Alevli', '05343996183', 'alevlisebnem@gmail.com', '2024-07-10 14:19:07', 'IK10203887', 1, 'aktif'),
(50, 'Mihriban', 'Erözyürek', '05380647463', 'mihri.yildirim@outlook.com', '2024-07-10 14:21:22', 'IK10203470', 1, 'aktif'),
(51, 'Huriye', 'Çam', '05388122117', 'huriye.ege.eslem@gmail.com', '2024-07-10 14:21:49', 'IK10203943', 1, 'aktif'),
(52, 'Muzaffer', 'Kalem', '05308827772', 'muzafferkalem@gmail.com', '2024-07-10 14:22:21', 'IK10203313', 1, 'aktif'),
(53, 'Pınar', 'Cezan', '05324746813', 'pinar_pekdas@hotmail.com', '2024-07-10 14:22:51', 'IK10203206', 1, 'aktif'),
(54, 'Gülşen', 'Eni', '05312618552', 'gulsenkuslu@hotmail.com', '2024-07-10 14:23:09', 'IK10203875', 1, 'aktif'),
(55, 'Yeşim', 'Mutlu', '05399663552', 'yyesimmutluu@gmail.com', '2024-07-10 14:23:34', 'IK10203126', 1, 'aktif'),
(58, 'Büşra', 'Çanakçı', '05079772169', 'bilalnecaticanakci@gmail.com', '2024-07-25 08:26:10', 'IK10203400', 1, 'aktif'),
(59, 'Elif', 'Yama', '05302943876', 'elifyama52@icloud.com', '2024-07-25 08:27:10', 'IK10203301', 1, 'aktif'),
(60, 'Sevgi', 'Sor', '05445844079', 'sevgisor@icloud.com', '2024-07-25 08:27:46', 'IK10203621', 1, 'aktif'),
(61, 'Merve', 'Yümlü', '05314393245', 'merve.yumlu5223@gmail.com', '2024-07-25 08:28:12', 'IK10203367', 1, 'aktif'),
(62, 'Ümran', 'Yüksel', '05309477252', 'umran.ozelyuksel@gmail.com', '2024-07-25 08:28:47', 'IK10203838', 1, 'aktif'),
(63, 'Hatice', 'Öz', '05454501862', 'erhan_0252@hotmail.com', '2024-07-25 08:29:14', 'IK10203856', 1, 'aktif'),
(64, 'Hanife', 'İncesöz', '05423489629', 'a', '2024-07-25 08:32:04', 'IK10203319', 1, 'aktif'),
(65, 'Nihal', 'Çakır', '05062764421', 'cakirhannihal@gmail.com', '2024-07-25 08:32:30', 'IK10203642', 1, 'aktif'),
(66, 'ERD', 'ÇELİK', '05344520603', 'erdcelikas@gmail.com', '2024-07-26 20:19:00', 'IK10203431', 1, 'aktif'),
(67, 'Dora', 'Sırımsı', '05327115914', 'elcinsirimsi@gmail.com', '2024-07-26 20:19:39', 'IK10203243', 1, 'aktif'),
(68, 'Arzu', 'Dilci', '05304589995', 'a', '2024-07-31 16:48:38', 'IK10203888', 1, 'aktif'),
(69, 'Özlem', 'Uygun', '05427104035', 'a', '2024-07-31 16:49:25', 'IK10203152', 1, 'aktif'),
(70, 'Zeynep Nazlı', 'Sezgi', '05534587748', 'zbassezgi@gmail.com', '2024-07-31 16:50:09', 'IK10203990', 1, 'aktif'),
(71, 'Rabia', 'Çit', '05423898816', 'a', '2024-07-31 16:50:37', 'IK10203745', 1, 'aktif'),
(72, 'Nebile', 'Yeşildağ', '05439384508', 'a', '2024-08-03 09:44:01', 'IK10203332', 1, 'aktif'),
(73, 'Edanur', 'Anduz', '05386168026', 'a', '2024-08-03 09:44:28', 'IK10203865', 1, 'aktif'),
(74, 'Arzu', 'İrvasa', '05433409282', 'a', '2024-08-03 09:45:02', 'IK10203870', 1, 'aktif'),
(75, 'Saliha', 'Dereköy', '05414590358', 'a', '2024-08-03 09:45:25', 'IK10203923', 1, 'aktif'),
(76, 'Emine', 'Hasoğlu', '05466224050', 'a', '2024-08-05 15:17:22', 'IK10203174', 1, 'aktif'),
(77, 'Yasemin', 'Demirbaş', '05070383285', 'a', '2024-08-05 17:40:57', 'IK10203512', 1, 'aktif'),
(78, 'Züleyha', 'Onmuş', '05076515258', 'zuleyha@incikabi@turktelekom.com.tr', '2024-08-08 11:45:52', 'IK10203581', 1, 'aktif'),
(79, 'Özgün Gıda', 'Tic', '05324612202', 'oz_gun52@hotmail.com', '2024-08-08 13:50:52', 'IK10203379', 1, 'aktif'),
(80, 'DKC GRUP', ',', '04524240352', 'info@dkcgrup.com', '2024-08-08 13:51:31', 'IK10203819', 1, 'aktif'),
(81, 'Edagül', 'Tokaç', '05432821152', 'a', '2024-08-08 16:21:37', 'IK10203151', 1, 'aktif'),
(82, 'Medisam', 'Plaza', '05318710072', 'a', '2024-08-08 16:58:18', 'IK10203718', 1, 'aktif'),
(83, 'İclal', 'Saçıl', '05426168460', 'a', '2024-08-08 17:09:26', 'IK10203211', 1, 'aktif'),
(84, 'Dilek', 'Dikmen', '05348522303', 'a', '2024-08-08 19:39:05', 'IK10203578', 1, 'aktif'),
(85, 'Serap', 'Ada', '05342401300', 'serap_yener@hotmail.com', '2024-08-09 10:11:59', 'IK10203130', 1, 'aktif'),
(86, 'Özgür Uzun', 'Uzun', '05052981267', 'ozguruguruzun67@gmail.com', '2024-08-10 14:07:48', 'IK10203030', 1, 'aktif'),
(87, 'Hatice', 'Mutlu', '05444042899', 'haticealbuz@gmail.com', '2024-08-10 15:06:59', 'IK10203700', 1, 'aktif'),
(88, 'Tuğba', 'Yeter', '05321552416', 'a', '2024-08-10 15:17:48', 'IK10203720', 1, 'aktif'),
(89, 'Mehmet Ali', 'Serdaş', '05418129896', 'a', '2024-08-12 12:04:35', 'IK10203672', 1, 'aktif'),
(90, 'Züleyha', 'Türkoğlu', '05462706905', 'a', '2024-08-12 12:13:03', 'IK10203397', 1, 'aktif'),
(91, 'Semra', 'Kayıkkol', '05456929893', 'a', '2024-08-12 12:13:31', 'IK10203237', 1, 'aktif'),
(92, 'Erdal', 'Fındık', '05437703172', 'a', '2024-08-12 17:55:22', 'IK10203537', 1, 'aktif'),
(93, 'Eslim', 'Ceceloğlu', '05458065693', 'a', '2024-08-12 18:18:35', 'IK10203403', 1, 'aktif'),
(94, 'Mustafa', 'Yangın', '05333564355', 'a', '2024-08-12 20:03:22', 'IK10203914', 1, 'aktif'),
(95, 'Sedefnur', 'Tepe', '05452747943', 'sedefcicegi@gmail.com', '2024-08-14 09:51:43', 'IK10203077', 1, 'aktif'),
(96, 'Harika', 'Dicle', '05052667278', 'a', '2024-08-14 21:10:09', 'IK10203684', 1, 'aktif'),
(97, 'Adem', 'Pehlivan', '05325585239', 'A', '2024-08-15 19:35:09', 'IK10203746', 1, 'aktif'),
(98, 'Işılay', 'Şahin', '05455286511', 'isilaysahin@yahoo.com', '2024-08-15 19:36:58', 'IK10203935', 1, 'aktif'),
(99, 'Ayşenur', 'Yiğit', '05061581262', 'aysenurryigit@gmail.com', '2024-08-15 19:37:36', 'IK10203240', 1, 'aktif'),
(100, 'Zeynep', 'Türksüğür', '05379600268', 'a', '2024-08-16 13:55:58', 'IK10203962', 1, 'aktif'),
(101, 'Yeşim', 'Yılmaz Depe', '05340190830', 'a', '2024-08-16 16:32:27', 'IK10203037', 1, 'aktif'),
(102, 'Zafer', 'Bayram', '05379574098', 'zaferrefaz28@hotmail.com', '2024-08-16 16:51:10', 'IK10203658', 1, 'aktif'),
(103, 'MELİKE ÜLKÜ', 'AYDIN KILIÇ', '05357796237', 'AAA', '2024-08-16 17:22:56', 'IK10203936', 1, 'aktif'),
(104, 'ERGÜN', 'KÜTÜK', '05324728932', 'AA', '2024-08-16 17:27:52', 'IK10203657', 1, 'aktif'),
(105, 'Yılmaz', 'Kırlak', '05302628432', 'a', '2024-08-16 19:44:06', 'IK10203540', 1, 'aktif'),
(106, 'Hamza', 'Koçan', '05355880272', 'koc_an_52@hotmail.çom', '2024-08-16 19:51:02', 'IK10203339', 1, 'aktif'),
(107, 'Şura Tevekkül', 'Aha', '05388583799', 'a', '2024-08-17 16:15:14', 'IK10203563', 1, 'aktif'),
(108, 'Elif Eylül', 'Kır', '05393659916', 'a', '2024-08-17 19:34:53', 'IK10203791', 1, 'aktif'),
(109, 'Ümit', 'Çalışıcı', '05386272352', 'a', '2024-08-17 19:35:17', 'IK10203919', 1, 'aktif'),
(110, 'Derya', 'Hacıfettahoğlu', '05419265068', 'a', '2024-08-17 19:35:42', 'IK10203011', 1, 'aktif'),
(111, 'Elif', 'Bozkur', '05310802544', 'a', '2024-08-17 19:36:00', 'IK10203709', 1, 'aktif'),
(112, 'Burcu', 'Yuva', '05438164131', 'a', '2024-08-19 13:16:09', 'IK10203780', 1, 'aktif'),
(113, 'Esra', 'Sarıçiçek', '05348820092', 'a', '2024-08-19 13:17:14', 'IK10203653', 1, 'aktif'),
(114, 'Sergüzel', 'Anduç', '05372382759', 'a', '2024-08-19 14:30:21', 'IK10203407', 1, 'aktif'),
(115, 'Adnan', 'Sarıhan', '05447277268', 'a', '2024-08-19 16:16:32', 'IK10203522', 1, 'aktif'),
(116, 'Zehra', 'Demirkol', '05331648152', 'a', '2024-08-19 16:36:57', 'IK10203377', 1, 'aktif'),
(117, 'Sündüs', 'Alkan', '05059389114', 'a', '2024-08-19 16:37:50', 'IK10203678', 1, 'aktif'),
(118, 'Tülay', 'Aka', '05350792252', 'a', '2024-08-19 17:56:11', 'IK10203161', 1, 'aktif'),
(119, 'Ege', 'Kocagöz', '05342163030', 'a', '2024-08-19 17:56:38', 'IK10203415', 1, 'aktif'),
(120, 'elena', 'oruç', '05309478550', 'ghgyhfytr', '2024-08-20 10:43:45', 'IK10203321', 1, 'aktif'),
(121, 'Mecit', 'Aykut', '05314375351', 'a', '2024-08-20 20:06:32', 'IK10203452', 1, 'aktif'),
(122, 'SERKAN ', 'ÖZDEMİR', '05413298054', 'SAWR4EW54', '2024-08-21 12:27:47', 'IK10203270', 1, 'aktif'),
(123, ' tuğba', 'cesur', '05421028552', 'khkıyuı8', '2024-08-21 17:54:44', 'IK10203024', 1, 'aktif'),
(124, 'nülüfer', 'inanır', '05386327575', 'guytuyryt6', '2024-08-21 17:59:53', 'IK10203034', 1, 'aktif'),
(125, 'Ayşe', 'Sezgi', '05379373642', 'aysesezgi52@gmail.com', '2024-08-21 19:05:03', 'IK10203088', 1, 'aktif'),
(126, 'Zehra', 'Boztepe', '05316480501', 'zehraboztepe05@gmail.com', '2024-08-21 19:05:36', 'IK10203649', 1, 'aktif'),
(127, 'Esra', 'Sezgi', '05422452669', 'esrasezgi@outlook.com', '2024-08-21 19:06:22', 'IK10203219', 1, 'aktif'),
(128, 'sinan', 'tepe', '05434018300', 'bjhguyfr', '2024-08-22 17:52:23', 'IK10203475', 1, 'aktif'),
(129, 'Elif Yaman', 'Kara', '05436135404', 'a', '2024-08-22 19:13:42', 'IK10203247', 1, 'aktif'),
(130, 'Suna', 'Uzun', '05324451346', 'a', '2024-08-22 19:40:47', 'IK10203763', 1, 'aktif'),
(131, 'NUR', 'ŞEKER', '05523940152', 'FJILREUOIY', '2024-08-23 10:07:07', 'IK10203599', 1, 'aktif'),
(132, 'ELİF', 'ÇALI', '05385025327', 'NJKHDQIKYFW', '2024-08-23 12:25:50', 'IK10203569', 1, 'aktif'),
(133, 'nesrin', 'erkoç', '05064757698', 'bhjgjgt', '2024-08-23 16:26:58', 'IK10203813', 1, 'aktif'),
(134, 'EMİNE ', 'MOLLUOĞLU', '05056637979', 'SIUOYT', '2024-08-23 16:56:47', 'IK10203823', 1, 'aktif'),
(135, 'Çilem', 'Güney', '05344358578', 'a', '2024-08-23 18:43:17', 'IK10203166', 1, 'aktif'),
(136, 'İbrahim', 'Gariboğlu', '05435456942', 'A', '2024-08-23 19:59:17', 'IK10203694', 1, 'aktif'),
(137, 'fatma', 'bilgu', '05059338172', 'jıjgoıuegw8o', '2024-08-24 13:22:27', 'IK10203974', 1, 'aktif'),
(138, 'ATİLA', 'YAZIM', '05335207557', 'GHFGDTR', '2024-08-24 15:32:03', 'IK10203489', 1, 'aktif'),
(139, 'NADİDE', 'ÇUHADAR', '05054683653', 'HFYFYRFGF', '2024-08-24 16:19:06', 'IK10203849', 1, 'aktif'),
(140, 'AYŞE ', 'ÖZGEN GENÇ', '05056720857', 'JGYFGYRYT', '2024-08-24 17:42:11', 'IK10203418', 1, 'aktif'),
(141, 'Ümit', 'Billay', '05442771162', 'a', '2024-08-25 13:13:14', 'IK10203539', 1, 'aktif'),
(142, 'Gökhan', 'Baş', '05307075220', 'a', '2024-08-25 13:47:04', 'IK10203048', 1, 'aktif'),
(143, 'İlker', 'Ocak', '05456367460', 'a', '2024-08-25 15:51:09', 'IK10203343', 1, 'aktif'),
(147, 'Ahmet Cemre', 'Geçtinli', '05424585252', NULL, '2024-08-26 09:45:35', 'IK10203793', 1, 'aktif'),
(148, 'jülide', 'kurşun', '05376806595', 'nhky', '2024-08-26 12:25:36', 'IK10203142', 1, 'aktif'),
(149, 'zühal', 'güneş', '05418558420', 'rtsfe', '2024-08-26 12:30:03', 'IK10203950', 1, 'aktif'),
(150, 'GÜL', 'GENÇ', '05055937705', 'HGUYYRYT', '2024-08-26 13:21:08', 'IK10203468', 1, 'aktif'),
(151, 'betül', 'Şahin', '05063288880', 'jgjhguyfytfdy', '2024-08-26 13:27:20', 'IK10203815', 1, 'aktif'),
(152, 'öznur', 'çay', '05070923787', 'vghfh', '2024-08-26 14:40:37', 'IK10203942', 1, 'aktif'),
(153, 'hilal', 'çolak', '05061400941', 'cfgdgrfe45', '2024-08-26 15:14:57', 'IK10203918', 1, 'aktif'),
(154, 'umut', 'görgülü', '05343732582', 'sabdfg', '2024-08-26 15:20:11', 'IK10203175', 1, 'aktif'),
(155, 'Emirhan Mehmet', 'Çamur', '05425814668', 'a', '2024-08-26 16:02:39', 'IK10203036', 1, 'aktif'),
(156, 'feyza', 'gökbulut', '05071269752', 'goıdryjt', '2024-08-26 17:57:22', 'IK10203357', 1, 'aktif'),
(157, 'burcı', 'kayışoğlu', '05312915100', 'hgjhgt', '2024-08-27 09:10:50', 'IK10203768', 1, 'aktif'),
(158, 'yavuz', 'gezer', '05392442591', 'hgjuyt', '2024-08-27 09:11:40', 'IK10203587', 1, 'aktif'),
(159, 'murat', 'kılıç', '05354061910', 'dsaewe3', '2024-08-27 09:12:23', 'IK10203157', 1, 'aktif'),
(160, 'esra', 'şadi', '05423371451', 'suhuydte', '2024-08-27 13:54:58', 'IK10203127', 1, 'aktif'),
(161, 'Hamza', 'güvenç', '05394036402', 'ahuısy8wr7', '2024-08-27 14:01:59', 'IK10203528', 1, 'aktif'),
(162, 'NAZLI', 'YILDIZ', '05464313570', 'DOITRI8', '2024-08-27 15:50:06', 'IK10203932', 1, 'aktif'),
(163, 'MEVLÜT ', 'ABUÇKA', '05077074009', '', '2024-08-27 20:12:36', 'IK10203487', 1, 'aktif'),
(164, 'Hüsna Ünal', 'TANRIVERDİ', '05326732735', '', '2024-08-27 20:20:08', 'IK10203443', 1, 'aktif'),
(165, 'NAGEHAN ', 'YILDIRIM', '05372347403', 'GTYTUY', '2024-08-28 12:49:00', 'IK10203057', 1, 'aktif'),
(166, 'meral', 'kurt', '05443730010', 'fhtryt', '2024-08-28 12:54:02', 'IK10203055', 1, 'aktif'),
(167, 'Yunus', 'Uslu', '05522401052', '', '2024-08-28 14:31:43', 'IK10203803', 1, 'aktif'),
(168, 'Mehmet Ali', 'Saydere', '05466085229', 'm.alisaydere@gmail.com', '2024-08-28 14:48:44', 'IK10203530', 1, 'aktif'),
(169, 'sevda', 'durmuş gündüz', '05331617582', 'sdyhıu65', '2024-08-28 14:52:35', 'IK10203988', 1, 'aktif'),
(170, 'EZGİ ', 'KIZILKAYA', '05442851930', 'EI84T79', '2024-08-29 13:23:20', 'IK10203041', 1, 'aktif'),
(171, 'Derya', 'taze', '05316480425', 'ıo9ı5y80', '2024-08-30 10:38:22', 'IK10203860', 1, 'aktif'),
(172, 'hanife', 'köroğlu', '05344538071', '4084ugtdjhgt', '2024-08-30 10:39:13', 'IK10203213', 1, 'aktif'),
(173, 'kübra', 'abacı', '05423212042', 'jfgıuhırut', '2024-08-30 10:39:58', 'IK10203826', 1, 'aktif'),
(174, 'ÜMRAN', 'AL', '05348397452', 'GYJUTUY', '2024-08-30 11:38:16', 'IK10203903', 1, 'aktif'),
(175, 'neşe', 'arslan', '05366973622', 'tret', '2024-08-30 12:26:38', 'IK10203783', 1, 'aktif'),
(176, 'İsmail', 'METİN', '05549838716', '', '2024-08-30 13:47:47', 'IK10203954', 1, 'aktif'),
(177, 'Sümeyye', 'Yıldırım', '05392681552', '', '2024-08-30 13:54:39', 'IK10203866', 1, 'aktif'),
(178, 'Rukiye', 'Paklacı', '05350608397', '', '2024-08-30 14:21:45', 'IK10203703', 1, 'aktif'),
(179, 'ALEV', 'İNCEYOL', '05355052625', '', '2024-08-30 14:38:24', 'IK10203876', 1, 'aktif'),
(180, 'SELEN', 'YIĞ', '05549442152', '', '2024-08-30 15:28:11', 'IK10203664', 1, 'aktif'),
(181, 'Esra', 'Güler', '05456282512', '', '2024-08-30 15:47:57', 'IK10203261', 1, 'aktif'),
(182, 'Ali', 'Türe', '05454912462', '', '2024-08-30 16:11:29', 'IK10203201', 1, 'aktif'),
(183, 'Alparslan', 'TAK', '05385944231', '', '2024-08-30 16:15:19', 'IK10203033', 1, 'aktif'),
(184, 'Derya ', 'Dere Yayla', '05469728897', '', '2024-08-30 16:30:54', 'IK10203460', 1, 'aktif'),
(185, 'ayşegül', 'yazıcıoğlu', '05056259733', 'jhfuıdyt', '2024-08-30 18:50:34', 'IK10203503', 1, 'aktif'),
(186, 'süleyman', 'yılmaz', '05413361719', 'huıfggeyu', '2024-08-31 10:01:14', 'IK10203681', 1, 'aktif'),
(187, 'songül', 'gümüş', '05526325352', 'dsaewqe3', '2024-08-31 11:53:17', 'IK10203735', 1, 'aktif'),
(188, 'gönül', 'sarubaş', '05050693752', '', '2024-08-31 12:41:38', 'IK10203833', 1, 'aktif'),
(189, 'ebru', 'karabayır', '05415531168', 'fktohıop', '2024-08-31 14:23:18', 'IK10203877', 1, 'aktif'),
(190, 'Eren', 'Söğüt', '05445698715', '', '2024-08-31 14:59:12', 'IK10203538', 1, 'aktif'),
(191, 'Yonca', 'Tav', '05412935993', '', '2024-08-31 15:10:59', 'IK10203589', 1, 'aktif'),
(192, 'EMİNE ', 'uygun', '05304147585', 'huıyw7tr', '2024-08-31 17:35:19', 'IK10203519', 1, 'aktif'),
(193, 'gülcan', 'dırık', '05466611659', 'uhuıy', '2024-08-31 17:54:23', 'IK10203608', 1, 'aktif'),
(194, 'halime', 'karaman', '05424694181', 'gyuytr', '2024-08-31 18:02:10', 'IK10203786', 1, 'aktif'),
(195, 'tuğba', 'altuntaş', '05067875269', 'dtredt', '2024-08-31 18:39:38', 'IK10203928', 1, 'aktif'),
(196, 'Dilek', 'biçim', '05055565755', 'dggh', '2024-08-31 19:21:15', 'IK10203398', 1, 'aktif'),
(197, 'zeyyat', 'eriş', '05054220152', 'fghtyrs6a', '2024-09-01 12:03:18', 'IK10203178', 1, 'aktif'),
(198, 'cihan', 'durgun', '05364262054', 'kgıtpo4', '2024-09-01 12:08:29', 'IK10203023', 1, 'aktif'),
(199, 'ABDULLAH', 'TAŞÇI', '05449533866', 'LJRGOIUG', '2024-09-01 12:34:07', 'IK10203212', 1, 'aktif'),
(200, 'Seyhan', 'Öcel', '05445328963', '', '2024-09-01 12:48:27', 'IK10203706', 1, 'aktif'),
(201, 'aysun', 'kaya', '05054825873', 'jhıuyı', '2024-09-01 13:01:27', 'IK10203328', 1, 'aktif'),
(202, 'Halil İbrahim', 'Bahadır', '05058179818', '', '2024-09-01 13:08:00', 'IK10203094', 1, 'aktif'),
(203, 'Mehmet', 'ÇAKIR', '05359600650', '', '2024-09-01 13:43:42', 'IK10203557', 1, 'aktif'),
(204, 'büşra', 'Şahin', '05415381161', 'wıuyıuw8ey', '2024-09-01 14:19:22', 'IK10203275', 1, 'aktif'),
(205, 'Erdal', 'ÜNAL', '05413042012', 'erdalunal@yalcingida.net', '2024-09-01 14:30:25', 'IK10203821', 1, 'aktif'),
(206, 'Muhammet', 'Ünal', '05324551552', 'muhammetunal1988@hotmail.com', '2024-09-01 15:15:50', 'IK10203594', 1, 'aktif'),
(207, 'Hanife', 'YAZICI', '05358575292', '', '2024-09-01 15:59:47', 'IK10203022', 1, 'aktif'),
(208, 'Hamiye', 'Süğümlü', '05425805576', '', '2024-09-01 16:08:02', 'IK10203428', 1, 'aktif'),
(209, 'Samet', 'İKİS', '05377801522', 'Q', '2024-09-01 16:11:15', 'IK10203663', 1, 'aktif'),
(210, 'Gülden', 'ŞENEL', '05347703205', '', '2024-09-01 16:16:33', 'IK10203193', 1, 'aktif'),
(211, 'Hatice ', 'Fidan Çetinkaya', '05312375052', '', '2024-09-01 16:31:56', 'IK10203067', 1, 'aktif'),
(212, 'AYŞE', 'KARAOĞLU', '05369779034', 'GYTY76R', '2024-09-01 17:01:01', 'IK10203980', 1, 'aktif'),
(213, 'Çiğdem', 'Yıldıran', '05352830682', '', '2024-09-01 17:30:24', 'IK10203056', 1, 'aktif'),
(214, 'nilüfer', 'orbay', '05413087455', 'guytu7', '2024-09-01 17:40:54', 'IK10203450', 1, 'aktif'),
(215, 'EBRU', 'KAYAK', '05439663927', '', '2024-09-01 18:22:51', 'IK10203159', 1, 'aktif'),
(216, 'ÜMRAN ', 'UYGUN', '05444230514', '', '2024-09-01 18:32:37', 'IK10203364', 1, 'aktif'),
(217, 'arzu ', 'gür', '05384101484', 'şlpgpgır', '2024-09-02 09:53:27', 'IK10203725', 1, 'aktif'),
(218, 'MİNE', 'ESER', '05528367752', '', '2024-09-02 09:59:10', 'IK10203043', 1, 'aktif'),
(219, 'ŞURA', 'DOĞAN', '05427184751', '', '2024-09-02 10:15:56', 'IK10203124', 1, 'aktif'),
(220, 'NURTEN ', 'AKBABA', '05531362312', '', '2024-09-02 10:30:58', 'IK10203365', 1, 'aktif'),
(221, 'CANSEL', 'ONUR', '05380164834', '', '2024-09-02 10:40:58', 'IK10203741', 1, 'aktif'),
(222, 'MELTEM', 'GÜNAL', '05464253239', '', '2024-09-02 10:44:21', 'IK10203493', 1, 'aktif'),
(223, 'zehra', 'göben', '05318908862', 'fasytrq6', '2024-09-02 11:05:53', 'IK10203797', 1, 'aktif'),
(224, 'ÖZGE', 'TURGU', '05306779002', '', '2024-09-02 11:25:54', 'IK10203465', 1, 'aktif'),
(225, 'EMİNE', 'OLGUN', '05350781722', '', '2024-09-02 11:27:30', 'IK10203163', 1, 'aktif'),
(226, 'PINAR', 'YEŞİLDUMAN', '05339725267', '', '2024-09-02 11:28:01', 'IK10203080', 1, 'aktif'),
(227, 'ŞAKİR', 'SALMAN', '05412719436', '', '2024-09-02 12:08:17', 'IK10203020', 1, 'aktif'),
(228, 'ORÇUN ERTUĞRUL ', 'SARIHAN', '05525538852', '', '2024-09-02 12:16:07', 'IK10203965', 1, 'aktif'),
(229, 'ENES ', 'ATALAY', '05360697891', '', '2024-09-02 12:43:59', 'IK10203444', 1, 'aktif'),
(230, 'FATMA', 'BEKLE', '05374406407', 'KJHIYU', '2024-09-02 14:05:11', 'IK10203187', 1, 'aktif'),
(231, 'HASRET', 'UYLASI', '05412093054', '', '2024-09-02 14:16:50', 'IK10203869', 1, 'aktif'),
(232, 'HÜLYA', 'GÜNEY', '05378663248', '', '2024-09-02 14:29:05', 'IK10203685', 1, 'aktif'),
(233, 'SEZGİN', 'KÜL', '05348488961', '', '2024-09-02 14:30:59', 'IK10203997', 1, 'aktif'),
(234, 'AYFER', 'YAZAR', '05438331707', '', '2024-09-02 15:05:27', 'IK10203366', 1, 'aktif'),
(235, 'SEDA', 'AYDIN', '05324536248', '', '2024-09-02 15:10:37', 'IK10203360', 1, 'aktif'),
(236, 'ŞADUMAN', 'BAHADIR', '05464135261', '', '2024-09-02 15:16:39', 'IK10203189', 1, 'aktif'),
(237, 'HACER', 'ÖZDEN', '05383058324', '', '2024-09-02 15:34:14', 'IK10203665', 1, 'aktif'),
(238, 'BURCU', 'ÇELİK', '05378309103', '', '2024-09-02 15:41:44', 'IK10203736', 1, 'aktif'),
(239, 'YAKUP', 'KIRAT', '05439685252', 'KOEIR4P', '2024-09-02 15:56:51', 'IK10203267', 1, 'aktif'),
(240, 'NURTEN ', 'bıyık', '05434328485', 'hıuyı87', '2024-09-02 16:13:52', 'IK10203134', 1, 'aktif'),
(241, 'sümryye ', 'koca', '05352206556', 'mojkoı9', '2024-09-02 16:29:00', 'IK10203374', 1, 'aktif'),
(242, 'DİLEKCAN', 'GÜNGÖR', '05398552000', '', '2024-09-02 16:59:38', 'IK10203509', 1, 'aktif'),
(243, 'selma', 'kısacık', '05464055308', '', '2024-09-02 17:05:18', 'IK10203298', 1, 'aktif'),
(244, 'zeynep ', 'civleş', '05432446567', 'gruıt', '2024-09-02 17:25:55', 'IK10203139', 1, 'aktif'),
(245, 'ÜMRAN', 'aşandoğrul', '05437713364', 'bhjyugtu6', '2024-09-02 17:33:25', 'IK10203242', 1, 'aktif'),
(246, 'Esra', 'Turhan', '05348659589', '', '2024-09-02 17:34:59', 'IK10203047', 1, 'aktif'),
(247, 'Hülya', 'Yeşilbursa', '05438665770', '', '2024-09-02 17:49:47', 'IK10203383', 1, 'aktif'),
(248, 'Aylin', 'ÇAMURLU', '05304896652', '', '2024-09-02 18:03:37', 'IK10203764', 1, 'aktif'),
(249, 'Habibe', 'ERTÜRKMEN', '05444647389', '', '2024-09-02 18:17:34', 'IK10203426', 1, 'aktif'),
(250, 'HÜLYA ', 'MÜRTEZAOĞLU', '05452922139', '', '2024-09-02 18:33:42', 'IK10203560', 1, 'aktif'),
(251, 'Tuncay', 'ABALI', '05423909532', '', '2024-09-02 18:43:46', 'IK10203964', 1, 'aktif'),
(252, 'YASEMİN', 'KANCAN', '05423755252', '', '2024-09-02 19:30:58', 'IK10203929', 1, 'aktif'),
(253, 'Murat', 'Sundak', '05332936603', 'msundak@gmail.com', '2024-09-02 19:40:42', 'IK10203705', 1, 'aktif'),
(254, 'büşra', 'kekiloğlu', '05321558697', 'ıkyu8ıy6', '2024-09-03 09:39:52', 'IK10203204', 1, 'aktif'),
(255, 'ZEKİ', 'BOZ', '05412628192', 'NJKYIU', '2024-09-03 10:04:56', 'IK10203571', 1, 'aktif'),
(256, 'ŞENNUR', 'AYVA', '05316369068', 'XCZFGDGSXXD', '2024-09-03 10:13:44', 'IK10203727', 1, 'aktif'),
(257, 'kerime', 'inan', '05436445997', 'hgyur56er', '2024-09-03 10:17:37', 'IK10203886', 1, 'aktif'),
(258, 'Elif', 'YAKIŞIK', '05445131566', '', '2024-09-03 11:16:17', 'IK10203350', 1, 'aktif'),
(259, 'Derya', 'Gül', '05453941261', '', '2024-09-03 11:46:04', 'IK10203606', 1, 'aktif'),
(260, 'Aslıhan', 'Ay', '05343302514', '', '2024-09-03 11:54:20', 'IK10203191', 1, 'aktif'),
(261, 'Hacer', 'BATTAL', '05468554258', '', '2024-09-03 12:02:22', 'IK10203455', 1, 'aktif'),
(262, 'Sinan', 'KARGO', '05545541148', '', '2024-09-03 12:05:50', 'IK10203399', 1, 'aktif'),
(263, 'Yusuf', 'Bezmek', '05414183342', '', '2024-09-03 12:13:59', 'IK10203670', 1, 'aktif'),
(264, 'Özgür', 'Demirbaş', '05424845052', '', '2024-09-03 12:16:16', 'IK10203165', 1, 'aktif'),
(265, 'Ensar', 'TAZE', '05349866129', '', '2024-09-03 12:21:40', 'IK10203380', 1, 'aktif'),
(266, 'Ezgi', 'ARAS', '05412651809', '', '2024-09-03 12:23:16', 'IK10203330', 1, 'aktif'),
(267, 'Dilek', 'Töngelli', '05304157554', '', '2024-09-03 12:25:46', 'IK10203280', 1, 'aktif'),
(268, 'Hanife', 'BAŞ', '05465760526', '', '2024-09-03 12:29:47', 'IK10203956', 1, 'aktif'),
(269, 'SALİHA', 'AYHAN', '05437208111', '', '2024-09-03 12:48:04', 'IK10203137', 1, 'aktif'),
(270, 'Bulut', 'ODABAŞOĞLU', '05416613690', '', '2024-09-03 12:54:27', 'IK10203387', 1, 'aktif'),
(271, 'İlknur', 'KOCA', '05443718922', '', '2024-09-03 12:55:46', 'IK10203940', 1, 'aktif'),
(272, 'Ayşe', 'TÖKEÇ', '05063896452', '', '2024-09-03 13:05:09', 'IK10203891', 1, 'aktif'),
(273, 'Tarık', 'Güvenç', '05436812497', '', '2024-09-03 13:07:46', 'IK10203110', 1, 'aktif'),
(274, 'Zeynep Berra', 'BUKA', '05375581490', '', '2024-09-03 13:13:49', 'IK10203835', 1, 'aktif'),
(275, 'Merve', 'GENÇ', '05392926000', '', '2024-09-03 13:18:52', 'IK10203173', 1, 'aktif'),
(276, 'Hasret', 'DEMİREL', '05523694299', '', '2024-09-03 13:21:55', 'IK10203692', 1, 'aktif'),
(277, 'Gülsüm', 'KİRAZ', '05518761938', '', '2024-09-03 13:25:00', 'IK10203122', 1, 'aktif'),
(278, 'Savaş', 'ÖZ', '05442690439', '', '2024-09-03 14:02:27', 'IK10203753', 1, 'aktif'),
(279, 'Özel', 'ÖZDEN', '05309323045', '', '2024-09-03 14:04:39', 'IK10203679', 1, 'aktif'),
(280, 'ERDEM', 'ERİŞMİŞ', '05064811131', 'BGUYEWTR', '2024-09-03 14:12:45', 'IK10203322', 1, 'aktif'),
(281, 'NUR', 'GEVEN', '05373778489', '', '2024-09-03 14:32:18', 'IK10203680', 1, 'aktif'),
(282, 'CİHAT', 'HOCA', '05468504636', '', '2024-09-03 15:32:04', 'IK10203335', 1, 'aktif'),
(283, 'çağla', 'Sarıçiçek', '05362949425', '', '2024-09-03 15:46:28', 'IK10203038', 1, 'aktif'),
(284, 'Tuğba', 'ARPACI', '05376652723', '', '2024-09-03 15:57:30', 'IK10203716', 1, 'aktif'),
(285, 'GÜLÜŞAN', 'AYCIL', '05316650692', '', '2024-09-03 15:58:28', 'IK10203854', 1, 'aktif'),
(286, 'Çiğdem', 'KEKLİK', '05352018390', '', '2024-09-03 16:02:31', 'IK10203453', 1, 'aktif'),
(287, 'Dudu', 'Hazırcı', '05448427991', 'sezarhazirci20@gmail.com', '2024-09-03 16:19:12', 'IK10203032', 1, 'aktif'),
(288, 'Hatice', 'Öztürk', '05343881232', '', '2024-09-03 16:20:30', 'IK10203259', 1, 'aktif'),
(289, 'Ayşenur', 'KARAMANLI', '05462057081', '', '2024-09-03 16:23:16', 'IK10203117', 1, 'aktif'),
(290, 'Sema', 'ATAN', '05373364972', '', '2024-09-03 16:45:23', 'IK10203304', 1, 'aktif'),
(291, 'ZÜBEYDE', 'KÖSTEK', '05432375707', '', '2024-09-03 17:09:25', 'IK10203566', 1, 'aktif'),
(292, 'SALİH', 'ÇAVUŞOĞLU', '05458349613', '', '2024-09-03 17:11:42', 'IK10203492', 1, 'aktif'),
(293, 'ZELİHA', 'ÇALKUR', '05335010829', '', '2024-09-03 17:13:19', 'IK10203135', 1, 'aktif'),
(294, 'Meltem', 'UNUTUR', '05413144203', '', '2024-09-03 17:45:35', 'IK10203661', 1, 'aktif'),
(295, 'Ali', 'YILMAZ', '05376716359', '', '2024-09-03 17:48:20', 'IK10203754', 1, 'aktif'),
(296, 'ÖZCAN ', 'KOCAGÖZ', '05057400155', '', '2024-09-03 18:02:02', 'IK10203116', 1, 'aktif'),
(297, 'Adem', 'ÇOLAK', '05616161240', '', '2024-09-03 18:21:27', 'IK10203771', 1, 'aktif'),
(298, 'Yağmur Su', 'Bulu', '05444702152', '', '2024-09-03 18:35:21', 'IK10203345', 1, 'aktif'),
(299, 'Tolga', 'CANTÜRK', '05533979369', '', '2024-09-03 18:52:01', 'IK10203069', 1, 'aktif'),
(300, 'Emine', 'KISACIK', '05425802333', '', '2024-09-03 19:11:09', 'IK10203841', 1, 'aktif'),
(301, 'ŞEYMA', 'YILDIZ COŞKUN', '05520863240', '', '2024-09-03 19:13:12', 'IK10203390', 1, 'aktif'),
(302, 'Songül', 'BİLİŞ', '05389333238', '', '2024-09-03 19:22:28', 'IK10203368', 1, 'aktif'),
(303, 'Mehmet Akif', 'HAYTA', '05366954052', '', '2024-09-03 19:25:25', 'IK10203197', 1, 'aktif'),
(304, 'Aytaç', 'YILMAZ', '05434966013', '', '2024-09-03 19:34:27', 'IK10203464', 1, 'aktif'),
(305, 'Yahya', 'Dikici', '05302944318', '', '2024-09-03 19:37:43', 'IK10203847', 1, 'aktif'),
(306, 'Fatih', 'KOCATÜRK', '05432984900', '', '2024-09-03 19:45:41', 'IK10203210', 1, 'aktif'),
(307, 'Kübra', 'İKİSU', '05395685072', '', '2024-09-03 19:53:55', 'IK10203053', 1, 'aktif'),
(308, 'emrullah ', 'kasa', '05353418216', '', '2024-09-04 11:15:39', 'IK10203598', 1, 'aktif'),
(309, 'gülay', 'çörtük uçar', '05324989849', 'xrew', '2024-09-04 11:24:03', 'IK10203294', 1, 'aktif'),
(310, 'fatma ', 'güvenilir', '05393790882', '', '2024-09-04 11:52:54', 'IK10203223', 1, 'aktif'),
(311, 'dilan dilek', 'andıç', '05442450886', 'hgtyte', '2024-09-04 12:18:38', 'IK10203863', 1, 'aktif'),
(312, 'MERVE', 'GÜVENSOY', '05492770052', 'JHGGUUG', '2024-09-04 12:47:29', 'IK10203624', 1, 'aktif'),
(313, 'Hacı', 'USLU', '05368996055', '', '2024-09-04 13:07:37', 'IK10203790', 1, 'aktif'),
(314, 'Mehtap', 'YÜN', '05312423052', '', '2024-09-04 13:38:08', 'IK10203880', 1, 'aktif'),
(315, 'Fatma', 'BOZTEPE', '05052420375', '', '2024-09-04 13:41:50', 'IK10203507', 1, 'aktif'),
(316, 'Ufuk', 'EROĞLU', '05326783343', '', '2024-09-04 13:50:22', 'IK10203976', 1, 'aktif'),
(317, 'Hamza', 'GÜVEN', '05433149192', '', '2024-09-04 13:54:59', 'IK10203176', 1, 'aktif'),
(318, 'Havva', 'AL', '05301778284', '', '2024-09-04 14:08:32', 'IK10203944', 1, 'aktif'),
(319, 'Hacile', 'ERKUŞ', '05337241784', '', '2024-09-04 14:33:28', 'IK10203994', 1, 'aktif'),
(320, 'Burhan', 'KEKİLOĞLU', '05388649915', '', '2024-09-04 14:48:57', 'IK10203662', 1, 'aktif'),
(321, 'Metin', 'YILMAZ', '05052699082', '', '2024-09-04 14:56:03', 'IK10203593', 1, 'aktif'),
(322, 'Orhan', 'KURT', '05367170022', '', '2024-09-04 15:14:18', 'IK10203867', 1, 'aktif'),
(323, 'İlknur', 'KÖPRÜLÜ', '05439339210', '', '2024-09-04 15:38:41', 'IK10203913', 1, 'aktif'),
(324, 'SEMRA', 'SARITAŞ', '05306838081', 'UHUIFETY', '2024-09-04 15:41:03', 'IK10203920', 1, 'aktif'),
(325, 'İlknur', 'ecim', '05427953114', 'joıjuoı', '2024-09-04 16:05:48', 'IK10203959', 1, 'aktif'),
(326, 'fatma', 'yaşar', '05334182218', '', '2024-09-04 16:35:54', 'IK10203004', 1, 'aktif'),
(327, 'pınar', 'bolun', '05444500552', 'rgyrt', '2024-09-04 17:08:44', 'IK10203970', 1, 'aktif'),
(328, 'ümmühan', 'AL', '05454972614', 'ehgcfreth', '2024-09-04 17:21:57', 'IK10203052', 1, 'aktif'),
(329, 'ZEKİYE', 'DURSUN', '05352589858', 'VFTYDTR5', '2024-09-04 17:30:43', 'IK10203111', 1, 'aktif'),
(330, 'Mustafa', 'ÖVÜR', '05427858041', '', '2024-09-04 18:00:42', 'IK10203654', 1, 'aktif'),
(331, 'İbrahim', 'ÖKLÜK', '05448955255', '', '2024-09-04 18:01:36', 'IK10203508', 1, 'aktif'),
(332, 'mine', 'bayar', '05423991621', 'fddok', '2024-09-04 18:11:21', 'IK10203873', 1, 'aktif'),
(333, 'EMİNE', 'DİKİCİ ÖZBEY', '05384040152', 'FHFUIORY', '2024-09-04 18:22:17', 'IK10203481', 1, 'aktif'),
(334, 'EMRAH', 'ÇAKICI', '05374469697', 'SHCDKJYK', '2024-09-04 18:23:43', 'IK10203083', 1, 'aktif'),
(335, 'ERKAN', 'Yuva', '05432815252', 'LCSLJHS', '2024-09-04 18:38:53', 'IK10203066', 1, 'aktif'),
(336, 'GÜLAY', 'AL', '05523730121', 'MCKJLH', '2024-09-04 19:08:15', 'IK10203266', 1, 'aktif'),
(337, 'Hasan', 'KAYAŞ', '05414292009', '', '2024-09-04 19:14:44', 'IK10203645', 1, 'aktif'),
(338, 'Erdinç', 'İNAN', '05458416460', '', '2024-09-04 19:27:17', 'IK10203937', 1, 'aktif'),
(339, 'Murat', 'İNAN', '05388949284', '', '2024-09-04 19:36:43', 'IK10203949', 1, 'aktif'),
(340, 'Aslı', 'BAYAV', '05326382871', '', '2024-09-04 19:41:18', 'IK10203359', 1, 'aktif'),
(341, 'AYKUT ', 'AKDAL', '05340807749', 'DGVLJFL', '2024-09-04 20:05:15', 'IK10203799', 1, 'aktif'),
(342, 'İlknur', 'KIRCA', '05443285297', '', '2024-09-04 20:44:51', 'IK10203031', 1, 'aktif'),
(343, 'Yüksel', 'KIR', '05337466990', '', '2024-09-04 20:51:07', 'IK10203188', 1, 'aktif'),
(344, 'semanur', 'uçar', '05424148859', 'şlojıopu', '2024-09-05 09:17:14', 'IK10203911', 1, 'aktif'),
(345, 'MUSTAFA ', 'SERİ', '05357091317', '', '2024-09-05 11:00:16', 'IK10203307', 1, 'aktif'),
(346, 'BÜŞRA', 'ÖZCAN', '05385172442', '', '2024-09-05 11:17:56', 'IK10203857', 1, 'aktif'),
(347, 'ÜMMÜHAN', 'CEZAN', '05348639952', '', '2024-09-05 11:39:34', 'IK10203938', 1, 'aktif'),
(348, 'DİLAN', 'YALIN', '05313983177', '', '2024-09-05 11:40:30', 'IK10203226', 1, 'aktif'),
(349, 'sema', 'çayır', '05432455890', '', '2024-09-05 11:53:32', 'IK10203264', 1, 'aktif'),
(350, 'serdar ', 'karabayır', '05464041252', 'cs çönd', '2024-09-05 12:07:51', 'IK10203579', 1, 'aktif'),
(351, 'hakan', 'şentürk', '05444497749', '', '2024-09-05 12:10:44', 'IK10203671', 1, 'aktif'),
(352, 'ömer ', 'arslan', '05464555152', 'efswçipo', '2024-09-05 12:24:22', 'IK10203584', 1, 'aktif'),
(353, 'semiha ', 'ecim müftüoğlu', '05454173751', 'frgıot', '2024-09-05 12:30:44', 'IK10203148', 1, 'aktif'),
(354, 'eda', 'çelik', '05413098576', 'reflmj', '2024-09-05 12:34:33', 'IK10203320', 1, 'aktif'),
(355, 'MEVLÜT', 'TONBULOĞLU', '05326448450', '', '2024-09-05 12:52:00', 'IK10203477', 1, 'aktif'),
(356, 'YASEMİN', 'AKICI', '05354315442', '', '2024-09-05 13:05:57', 'IK10203613', 1, 'aktif'),
(357, 'SEMA', 'KARACA', '05387480790', '', '2024-09-05 13:07:32', 'IK10203278', 1, 'aktif'),
(358, 'EYLEM', 'YÜCETEPE', '05442404681', '', '2024-09-05 13:16:25', 'IK10203338', 1, 'aktif'),
(359, 'YUSUF', 'DİNÇ', '05456405200', 'NJKBKJGW', '2024-09-05 13:29:50', 'IK10203287', 1, 'aktif'),
(360, 'tuğba ', 'biçim', '05061296462', '', '2024-09-05 14:00:14', 'IK10203241', 1, 'aktif'),
(361, 'EZGİ', 'ADA', '05386226597', '', '2024-09-05 14:24:11', 'IK10203344', 1, 'aktif'),
(362, 'SELVİHA', 'KILIÇ', '05418824002', '', '2024-09-05 14:29:38', 'IK10203960', 1, 'aktif'),
(363, 'NURCAN', 'ŞİRİN', '05443477530', '', '2024-09-05 14:50:22', 'IK10203286', 1, 'aktif'),
(364, 'SEVGİ ', 'ÜZÜM', '05412406618', 'MKŞOJUO', '2024-09-05 15:08:17', 'IK10203459', 1, 'aktif'),
(365, 'YAKUP', 'SUCU', '05305511152', '', '2024-09-05 15:15:12', 'IK10203883', 1, 'aktif'),
(366, 'gamze', 'aksu', '05337678678', '', '2024-09-05 15:31:13', 'IK10203108', 1, 'aktif'),
(367, 'süeda', 'çolak', '05334485103', '', '2024-09-05 15:43:39', 'IK10203947', 1, 'aktif'),
(368, 'gamze', 'şen', '05444972668', '', '2024-09-05 16:22:37', 'IK10203612', 1, 'aktif'),
(369, 'EMEL', 'MUHCI', '05369799053', '', '2024-09-05 16:23:34', 'IK10203776', 1, 'aktif'),
(370, 'adem', 'azer', '05417633276', '', '2024-09-05 16:29:53', 'IK10203697', 1, 'aktif'),
(371, 'rasul', 'abça', '05364335957', '', '2024-09-05 16:37:38', 'IK10203831', 1, 'aktif'),
(373, 'EMRA ', 'AKBULUT', '05424669101', '', '2024-09-05 17:01:01', 'IK10203145', 1, 'aktif'),
(374, 'BÜŞRA', 'DOĞRUCA', '05339381829', '', '2024-09-05 17:07:32', 'IK10203061', 1, 'aktif'),
(375, 'FATMA ', 'KIR', '05397296166', '', '2024-09-05 17:21:16', 'IK10203570', 1, 'aktif'),
(376, 'SEZGİN ', 'KÜÇÜK', '05339531739', '', '2024-09-05 17:32:45', 'IK10203132', 1, 'aktif'),
(377, 'BÜŞRA', 'BAŞ YILDIRIM', '05055412467', '', '2024-09-05 17:44:23', 'IK10203081', 1, 'aktif'),
(378, 'GÜLÇİN ZAN ', 'YILDIRIM', '05417869845', '', '2024-09-05 17:55:40', 'IK10203168', 1, 'aktif'),
(379, 'ALİ', 'DODAK', '05369198266', '', '2024-09-05 18:00:44', 'IK10203577', 1, 'aktif'),
(380, 'ESMA', 'YILMAZ', '05374060152', '', '2024-09-05 18:20:54', 'IK10203411', 1, 'aktif'),
(381, 'ŞULE', 'TAŞAR', '05011600152', 'ŞŞ', '2024-09-05 18:44:57', 'IK10203086', 1, 'aktif'),
(382, 'ESRA', 'MUTLU', '05462445757', '', '2024-09-05 18:57:00', 'IK10203874', 1, 'aktif'),
(383, 'TÜRKAN', 'FIRAT', '05457869969', '', '2024-09-05 19:00:20', 'IK10203310', 1, 'aktif'),
(384, 'AYŞE', 'ŞEN', '05523870485', '', '2024-09-05 19:04:02', 'IK10203388', 1, 'aktif'),
(385, 'ESRA', 'SEÇMEZ', '05398363905', '', '2024-09-05 19:09:42', 'IK10203082', 1, 'aktif'),
(386, 'MÜCAHİDE', 'SİNE', '05374038226', '', '2024-09-05 19:32:33', 'IK10203347', 1, 'aktif'),
(387, 'SEVDA', 'ÖZCANLI', '05302453842', '', '2024-09-05 19:45:04', 'IK10203274', 1, 'aktif'),
(388, 'ASİYE', 'KOCAGÖZ', '05305435215', '', '2024-09-05 20:20:53', 'IK10203822', 1, 'aktif'),
(389, 'tuncay', 'keskin', '05454849750', '', '2024-09-05 20:28:49', 'IK10203637', 1, 'aktif'),
(390, 'ELİF', 'TATLI', '05446571179', '', '2024-09-05 20:46:00', 'IK10203765', 1, 'aktif'),
(391, 'MEHMET', 'SOLMAZ', '05065526476', '', '2024-09-05 20:55:43', 'IK10203690', 1, 'aktif'),
(392, 'BÜŞRA', 'KÖKSAL', '05393389267', '', '2024-09-05 21:10:30', 'IK10203611', 1, 'aktif'),
(393, 'NURİYE', 'ÖZCAN', '05323381923', '', '2024-09-06 10:00:39', 'IK10203778', 1, 'aktif'),
(394, 'VOLKAN ', 'ÖZCAN', '05069137409', '', '2024-09-06 10:57:29', 'IK10203660', 1, 'aktif'),
(395, 'BİLGEHAN', 'Alkan', '05071311943', '', '2024-09-06 11:17:34', 'IK10203922', 1, 'aktif'),
(396, 'ÇİĞDEM', 'AKA', '05466624343', '', '2024-09-06 11:18:53', 'IK10203392', 1, 'aktif'),
(397, 'DENİZ', 'SERİN KÜÇÜK', '05414234959', '', '2024-09-06 11:23:24', 'IK10203951', 1, 'aktif'),
(398, 'ebru', 'çoruh', '05432291718', '', '2024-09-06 12:57:43', 'IK10203186', 1, 'aktif'),
(399, 'MERVE ', 'BAYINDIR', '05446355252', '', '2024-09-06 13:12:44', 'IK10203910', 1, 'aktif'),
(400, 'FATMA ', 'ÇAKMAK', '05061253858', '', '2024-09-06 13:16:51', 'IK10203817', 1, 'aktif'),
(401, 'MURAT ', 'YILMAZER', '05394084643', '', '2024-09-06 13:18:31', 'IK10203284', 1, 'aktif'),
(402, 'Turgut', 'Alper', '05384413120', '', '2024-09-06 13:29:20', 'IK10203277', 1, 'aktif'),
(403, 'Yüksel', 'ERÖZLEN', '05342973678', '', '2024-09-06 13:30:51', 'IK10203311', 1, 'aktif'),
(404, 'aysima', 'gümüş', '05375937983', '', '2024-09-06 13:41:37', 'IK10203777', 1, 'aktif'),
(405, 'arif', 'sevindik', '05467872466', '', '2024-09-06 13:58:30', 'IK10203109', 1, 'aktif'),
(406, 'çiğdem', 'ak', '05077454050', '', '2024-09-06 13:59:30', 'IK10203007', 1, 'aktif'),
(407, 'harun', 'verim', '05366895745', '', '2024-09-06 14:13:53', 'IK10203806', 1, 'aktif'),
(408, 'Tuğba', 'çanka', '05416029775', '', '2024-09-06 14:24:53', 'IK10203707', 1, 'aktif'),
(409, 'Fatih', 'ETGÜ', '05322768558', '', '2024-09-06 14:28:52', 'IK10203848', 1, 'aktif'),
(410, 'MURAT', 'VAR', '05338146894', '', '2024-09-06 14:36:43', 'IK10203437', 1, 'aktif'),
(411, 'NARİN', 'ECE', '05322527524', '', '2024-09-06 14:42:23', 'IK10203529', 1, 'aktif'),
(412, 'Derya ', 'KURU', '05336344393', '', '2024-09-06 14:44:05', 'IK10203862', 1, 'aktif'),
(413, 'TUĞBA', 'DUMAN ÇETİN', '05360122847', '', '2024-09-06 14:45:06', 'IK10203227', 1, 'aktif'),
(414, 'FATMA ', 'GENÇAY', '05467566325', '', '2024-09-06 14:49:13', 'IK10203337', 1, 'aktif'),
(415, 'GÜLŞAH', 'ÇAKICI', '05384657060', '', '2024-09-06 14:55:59', 'IK10203601', 1, 'aktif'),
(416, 'NAZLI', 'GÜNEY', '05301552522', '', '2024-09-06 15:13:19', 'IK10203531', 1, 'aktif'),
(417, 'FATMA', 'YAZIM', '05456162480', '', '2024-09-06 15:22:44', 'IK10203222', 1, 'aktif'),
(418, 'YASEMİN', 'TOPAL CAVCI', '05467765696', '', '2024-09-06 15:37:01', 'IK10203181', 1, 'aktif'),
(419, 'MERVE ', 'AYBARLIK', '05079497724', '', '2024-09-06 15:37:53', 'IK10203820', 1, 'aktif'),
(420, 'HAVA', 'KAVCI', '05372008982', '', '2024-09-06 15:50:04', 'IK10203303', 1, 'aktif'),
(421, 'sakine', 'kartal', '05345874824', '', '2024-09-06 16:00:39', 'IK10203800', 1, 'aktif'),
(422, 'Adem', 'ONUR', '05456841350', '', '2024-09-06 16:02:30', 'IK10203655', 1, 'aktif'),
(423, 'sevim', 'hasten', '05308918952', '', '2024-09-06 16:10:18', 'IK10203098', 1, 'aktif'),
(424, 'kenan', 'ilmik', '05376988001', '', '2024-09-06 16:14:41', 'IK10203028', 1, 'aktif'),
(425, 'SEDA', 'ŞEN', '05423815756', '', '2024-09-06 16:27:37', 'IK10203099', 1, 'aktif'),
(426, 'ALEYNA', 'KARAMANOĞLU', '05364250629', '', '2024-09-06 16:33:16', 'IK10203505', 1, 'aktif'),
(427, 'MERVE NUR', 'AZABOĞLU', '05334776449', '', '2024-09-06 16:36:49', 'IK10203900', 1, 'aktif'),
(428, 'SEBAHAT', 'YEŞİLTAŞ GEDİK', '05424108615', '', '2024-09-06 16:45:07', 'IK10203547', 1, 'aktif'),
(429, 'DERYA ', 'ÖZEL', '05414152255', '', '2024-09-06 16:50:34', 'IK10203084', 1, 'aktif'),
(430, 'ZEKAİ', 'GÖRÜR', '05357988334', '', '2024-09-06 16:58:45', 'IK10203051', 1, 'aktif'),
(431, 'DİLEK ', 'İPEKÇİ', '05457738989', '', '2024-09-06 17:13:37', 'IK10203483', 1, 'aktif'),
(432, 'nurgül', 'meriç', '05064486442', '', '2024-09-06 17:30:58', 'IK10203516', 1, 'aktif'),
(433, 'TURAN', 'GÜLEN', '05368156462', '', '2024-09-06 17:36:56', 'IK10203063', 1, 'aktif'),
(434, 'Hacer', 'ÖMEROĞLU', '05053886970', '', '2024-09-06 17:49:53', 'IK10203984', 1, 'aktif'),
(435, 'Tuğba', 'ÇALIŞIR', '05380931318', '', '2024-09-06 17:52:46', 'IK10203485', 1, 'aktif'),
(436, 'zehra', 'aydoğan', '05398826011', '', '2024-09-06 17:55:40', 'IK10203244', 1, 'aktif'),
(437, 'Kübra', 'ZEREN', '05538733952', '', '2024-09-06 18:05:20', 'IK10203978', 1, 'aktif'),
(438, 'Tuçe', 'UÇAN', '05393601921', '', '2024-09-06 18:06:07', 'IK10203734', 1, 'aktif'),
(439, 'Neslihan', 'ŞAHİN', '05445080135', '', '2024-09-06 18:11:53', 'IK10203035', 1, 'aktif'),
(440, 'Elif', 'GÜNGÖR', '05434577552', '', '2024-09-06 18:22:57', 'IK10203721', 1, 'aktif'),
(441, 'Yunus', 'SÜNE', '05445826192', '', '2024-09-06 18:23:29', 'IK10203711', 1, 'aktif'),
(442, 'Ersin', 'DEMİRTÜRK', '05435624889', '', '2024-09-06 18:30:26', 'IK10203150', 1, 'aktif'),
(443, 'Turgut', 'GÜNEŞ', '05468484383', '', '2024-09-06 18:32:20', 'IK10203260', 1, 'aktif'),
(444, 'Şenay', 'ÖZDEMİR', '05379266949', '', '2024-09-06 18:39:18', 'IK10203985', 1, 'aktif'),
(445, 'Şule', 'DİNÇER', '05442031883', '', '2024-09-06 18:45:37', 'IK10203296', 1, 'aktif'),
(446, 'Sema', 'ŞİŞMAN', '05452030057', '', '2024-09-06 18:55:17', 'IK10203479', 1, 'aktif'),
(447, 'ÖZGÜR', 'TOPUZ', '05064562639', '', '2024-09-06 19:05:55', 'IK10203355', 1, 'aktif'),
(448, 'Gamze', 'GEÇTİN', '05375744235', '', '2024-09-06 19:46:33', 'IK10203107', 1, 'aktif'),
(449, 'Nagehan', 'KEPİR', '05370166886', '', '2024-09-06 20:03:36', 'IK10203044', 1, 'aktif'),
(450, 'Ceylin', 'SAYGU', '05348801517', '', '2024-09-06 20:15:26', 'IK10203288', 1, 'aktif'),
(451, 'Sefa', 'KARACI', '05414286724', '', '2024-09-06 20:21:34', 'IK10203541', 1, 'aktif'),
(452, 'Duygu', 'GÜLTEGİN', '05436805152', '', '2024-09-06 20:25:02', 'IK10203695', 1, 'aktif'),
(453, 'Ömer', 'ÜNAL', '05415028384', '', '2024-09-06 20:26:28', 'IK10203946', 1, 'aktif'),
(454, 'isa', 'kandemir', '05379582525', '', '2024-09-06 20:53:57', 'IK10203855', 1, 'aktif'),
(455, 'şefaat', 'kılıç', '05365499798', '', '2024-09-06 20:59:06', 'IK10203650', 1, 'aktif'),
(456, 'ibrahim', 'özcan', '05448022895', '', '2024-09-07 10:25:50', 'IK10203026', 1, 'aktif'),
(457, 'ELİF', 'İNAL', '05426697784', '', '2024-09-07 11:30:10', 'IK10203439', 1, 'aktif'),
(458, 'HİCRAN', 'GEZER', '05523281478', '', '2024-09-07 11:31:31', 'IK10203102', 1, 'aktif'),
(459, 'SALİHA ', 'ERDURAN', '05447490373', '', '2024-09-07 11:40:33', 'IK10203013', 1, 'aktif'),
(460, 'GÖKHAN', 'YAŞAR', '05365806849', '', '2024-09-07 11:50:26', 'IK10203788', 1, 'aktif'),
(461, 'AYTEN', 'KAPLAN', '05439100123', '', '2024-09-07 11:51:46', 'IK10203295', 1, 'aktif'),
(462, 'MELEK', 'TOPCUOĞLU', '05304594695', '', '2024-09-07 12:42:36', 'IK10203376', 1, 'aktif'),
(463, 'ŞEYDA', 'AĞLIK', '05379342544', '', '2024-09-07 12:44:04', 'IK10203402', 1, 'aktif'),
(464, 'SEDA', 'UYGUN', '05059261529', '', '2024-09-07 12:49:24', 'IK10203382', 1, 'aktif'),
(465, 'hüseyin', 'çırpan', '05071252152', '', '2024-09-07 13:20:01', 'IK10203925', 1, 'aktif'),
(466, 'metin ', 'GÜNGÖR', '05068817662', '', '2024-09-07 13:27:06', 'IK10203457', 1, 'aktif'),
(467, 'RUKİYE', 'ARSLANTÜRK', '05065641473', '', '2024-09-07 13:32:16', 'IK10203682', 1, 'aktif'),
(468, 'serdar', 'kaçak', '05423398479', '', '2024-09-07 13:48:42', 'IK10203342', 1, 'aktif'),
(469, 'Öznur', 'YÜKSEL', '05433378434', '', '2024-09-07 14:18:52', 'IK10203616', 1, 'aktif'),
(470, 'Rüstem', 'ARSLANYILMAZ', '05432972477', '', '2024-09-07 14:26:52', 'IK10203656', 1, 'aktif'),
(471, 'Mustafa', 'KULVANCI', '05340414383', '', '2024-09-07 14:28:17', 'IK10203249', 1, 'aktif'),
(472, 'Veysel', 'KÖSE', '05055928854', '', '2024-09-07 14:32:24', 'IK10203484', 1, 'aktif'),
(473, 'Yıldırım', 'ÖCAL', '05326022346', '', '2024-09-07 14:36:25', 'IK10203164', 1, 'aktif'),
(474, 'Mehmet', 'DİK', '05336225576', '', '2024-09-07 14:43:07', 'IK10203565', 1, 'aktif'),
(475, 'Şenay', 'EMİN', '05531677152', '', '2024-09-07 14:48:31', 'IK10203232', 1, 'aktif'),
(476, 'Nilüfer', 'GÖZÜDİK TÖNGEL', '05377428756', '', '2024-09-07 15:04:34', 'IK10203427', 1, 'aktif'),
(477, 'Ertuğrul', 'ERDEM', '05337095765', '', '2024-09-07 15:05:24', 'IK10203580', 1, 'aktif'),
(478, 'Sibel', 'ERDEM', '05067004325', '', '2024-09-07 15:16:12', 'IK10203761', 1, 'aktif'),
(479, 'Semra', 'ERDEMLİ', '05312936215', '', '2024-09-07 15:23:29', 'IK10203784', 1, 'aktif'),
(480, 'Şura', 'YILMAZ', '05455506941', '', '2024-09-07 15:33:11', 'IK10203039', 1, 'aktif'),
(481, 'Tuğbanur ', 'KOŞUM', '05528184052', '', '2024-09-07 15:38:29', 'IK10203643', 1, 'aktif'),
(482, 'Duygu', 'ELAY KARAR', '05433407264', '', '2024-09-07 15:41:42', 'IK10203070', 1, 'aktif'),
(483, 'Nurdan', 'KIŞI', '05536064524', '', '2024-09-07 15:46:09', 'IK10203635', 1, 'aktif'),
(484, 'Turgay', 'AVCI', '05054786882', '', '2024-09-07 15:52:37', 'IK10203986', 1, 'aktif'),
(485, 'Hüseyin', 'TEKER', '05427888171', '', '2024-09-07 15:57:38', 'IK10203269', 1, 'aktif'),
(486, 'Oya', 'AKA', '05355114559', '', '2024-09-07 15:59:36', 'IK10203882', 1, 'aktif'),
(487, 'MAHMUT ', 'BAKAR', '05445509824', '', '2024-09-07 16:24:19', 'IK10203123', 1, 'aktif'),
(488, 'MELEK ', 'ERGÖZ', '05443182262', '', '2024-09-07 16:30:54', 'IK10203065', 1, 'aktif'),
(489, 'Arzu', 'ALTUNTAŞ', '05396201234', '', '2024-09-07 16:49:30', 'IK10203533', 1, 'aktif'),
(490, 'Koncay', 'AKA', '05427945201', '', '2024-09-07 17:02:59', 'IK10203957', 1, 'aktif'),
(491, 'İlhan', 'EROL', '05510301000', '', '2024-09-07 17:05:57', 'IK10203128', 1, 'aktif'),
(492, 'Sami', 'TANRIVERDİ', '05468409084', '', '2024-09-07 17:11:03', 'IK10203762', 1, 'aktif'),
(493, 'Arzu', 'ADAŞ GÜNEŞ', '05335279023', '', '2024-09-07 17:13:09', 'IK10203590', 1, 'aktif'),
(494, 'Hamiye', 'AÇGÜL GÜLEN', '05551601652', '', '2024-09-07 17:17:58', 'IK10203143', 1, 'aktif'),
(495, 'Neşe', 'ÖZTÜRK', '05425037723', '', '2024-09-07 17:35:04', 'IK10203416', 1, 'aktif'),
(496, 'Azra', 'TONUÇ', '05368505836', '', '2024-09-07 17:43:26', 'IK10203710', 1, 'aktif'),
(497, 'Şahide', 'FEYZİOĞLU', '05363646578', '', '2024-09-07 17:44:45', 'IK10203755', 1, 'aktif'),
(498, 'Arzu', 'BİLLAY', '05419520201', 'arzubillay52@gmail.com', '2024-09-07 17:45:28', 'IK10203361', 1, 'aktif'),
(499, 'Arzu', 'IRMAK', '05432982100', '', '2024-09-07 17:52:19', 'IK10203858', 1, 'aktif'),
(500, 'Esra Nur', 'HU AÇKU', '05392443582', '', '2024-09-07 17:55:00', 'IK10203945', 1, 'aktif'),
(501, 'Zübeyde', 'ERGÜL', '05433932679', '', '2024-09-07 17:59:07', 'IK10203878', 1, 'aktif'),
(502, 'Şeyda', 'DADAŞ', '05536732325', '', '2024-09-07 18:08:22', 'IK10203183', 1, 'aktif'),
(503, 'Zekeriya', 'AZER', '05445842772', '', '2024-09-07 18:16:58', 'IK10203198', 1, 'aktif'),
(504, 'Tuncay', 'ARGIN', '05317458959', '', '2024-09-07 18:20:11', 'IK10203074', 1, 'aktif'),
(505, 'Furkan', 'ÖZDEMİR', '05321385747', '', '2024-09-07 18:24:16', 'IK10203636', 1, 'aktif'),
(506, 'Ecrin', 'EREN', '05323506238', '', '2024-09-07 18:26:29', 'IK10203812', 1, 'aktif'),
(507, 'Hikmet', 'SEVGİLİ', '05307602057', '', '2024-09-07 18:59:22', 'IK10203500', 1, 'aktif'),
(508, 'Asiye', 'UYAR', '05444956665', '', '2024-09-07 19:06:19', 'IK10203246', 1, 'aktif'),
(509, 'MERVE', 'KEZER', '05439702115', '', '2024-09-07 19:17:16', 'IK10203119', 1, 'aktif'),
(510, 'YEŞİM', 'SALMAN', '05444438717', '', '2024-09-07 19:27:11', 'IK10203218', 1, 'aktif'),
(511, 'HATİCE RAVZA', 'YEŞİLTEPE', '05523194294', '', '2024-09-07 19:33:55', 'IK10203675', 1, 'aktif'),
(512, 'ÜMİT ', 'ANT', '05377335455', '', '2024-09-07 19:42:34', 'IK10203872', 1, 'aktif'),
(513, 'HÜMEYRA', 'KIŞ', '05424661291', '', '2024-09-07 19:46:19', 'IK10203194', 1, 'aktif'),
(514, 'SEMA', 'YILDIRIM', '05444854892', '', '2024-09-07 19:53:05', 'IK10203881', 1, 'aktif'),
(515, 'DENİZ', 'MALKAÇOĞLU', '05303214120', '', '2024-09-07 20:10:27', 'IK10203691', 1, 'aktif'),
(516, 'AHMET', 'AYDIN', '05442677323', '', '2024-09-07 20:24:44', 'IK10203548', 1, 'aktif'),
(517, 'OKAN', 'KAVCI', '05423310442', '', '2024-09-07 20:30:39', 'IK10203101', 1, 'aktif'),
(518, 'MUSA', 'ER', '05424233887', '', '2024-09-07 20:59:39', 'IK10203353', 1, 'aktif'),
(519, 'ALİ ', 'AYDEMİR', '05419115781', '', '2024-09-08 09:34:42', 'IK10203446', 1, 'aktif'),
(520, 'VUSLAT', 'UYGUN', '05534742552', '', '2024-09-08 10:27:52', 'IK10203103', 1, 'aktif'),
(521, 'SERAP', 'TUTAR', '05306791044', '', '2024-09-08 11:14:09', 'IK10203733', 1, 'aktif'),
(522, 'CEREN', 'DİNDAR', '05524641346', '', '2024-09-08 11:41:54', 'IK10203058', 1, 'aktif'),
(523, 'AYSUN', 'KISACIK', '05370200841', '', '2024-09-08 11:54:18', 'IK10203939', 1, 'aktif'),
(524, 'AYŞE', 'GÜVENKAYA', '05447672053', '', '2024-09-08 12:20:44', 'IK10203480', 1, 'aktif'),
(525, 'HASAN FATİH', 'CANKURT', '05453964834', '', '2024-09-08 12:43:56', 'IK10203371', 1, 'aktif'),
(526, 'ATAKAN', 'KEFLİ', '05399626068', '', '2024-09-08 12:46:08', 'IK10203104', 1, 'aktif'),
(527, 'ALİ', 'POLAT', '05325491469', '', '2024-09-08 12:56:08', 'IK10203582', 1, 'aktif'),
(528, 'SEVİNÇ', 'ÖZEL', '05079878629', '', '2024-09-08 13:00:01', 'IK10203224', 1, 'aktif'),
(529, 'MEDİNE', 'YILDIZ', '05343088210', '', '2024-09-08 13:05:49', 'IK10203466', 1, 'aktif'),
(530, 'SONGÜL', 'BAYRAM', '05536630216', '', '2024-09-08 13:11:35', 'IK10203969', 1, 'aktif'),
(531, 'SONER', 'YILMAZ', '05555657663', '', '2024-09-08 13:20:30', 'IK10203555', 1, 'aktif'),
(532, 'ÖZLEM', 'YURTTAŞ', '05057510784', '', '2024-09-08 13:29:28', 'IK10203543', 1, 'aktif'),
(533, 'NUR', 'EMİROĞLU', '05456682091', '', '2024-09-08 13:38:19', 'IK10203185', 1, 'aktif'),
(534, 'KEREM ', 'ilmik', '05416613447', '', '2024-09-08 13:39:38', 'IK10203059', 1, 'aktif'),
(535, 'ALİ FUAT', 'SEZEN', '05433500052', '', '2024-09-08 14:05:09', 'IK10203572', 1, 'aktif'),
(536, 'Aynur', 'YAZLI', '05414794493', '', '2024-09-08 14:11:55', 'IK10203898', 1, 'aktif'),
(537, 'HARUN', 'KINALI', '05324521009', '', '2024-09-08 14:29:05', 'IK10203770', 1, 'aktif'),
(538, 'ELİF ', 'AYDIN', '05389158220', '', '2024-09-08 14:50:44', 'IK10203214', 1, 'aktif'),
(539, 'Hanife', 'DURAN', '05425476292', '', '2024-09-08 14:54:56', 'IK10203879', 1, 'aktif'),
(540, 'DAMLA', 'GÜVEN BIÇAKCI', '05424577388', '', '2024-09-08 14:58:11', 'IK10203673', 1, 'aktif'),
(541, 'Abdullah', 'İĞNECİ', '05424668527', '', '2024-09-08 15:06:46', 'IK10203404', 1, 'aktif'),
(542, 'Fatma', 'YÜCE', '05443159625', '', '2024-09-08 15:17:02', 'IK10203429', 1, 'aktif'),
(543, 'Nazan', 'ÇETİNOĞLU BAŞKAYA', '05334992227', '', '2024-09-08 15:17:46', 'IK10203432', 1, 'aktif'),
(544, 'Asuman', 'AKDERE', '05352307576', '', '2024-09-08 15:26:41', 'IK10203987', 1, 'aktif'),
(546, 'Burhan', 'ACIM', '05436622426', '', '2024-09-08 15:41:30', 'IK10203235', 1, 'aktif'),
(547, 'İrfan', 'KULAÇ', '05056628130', '', '2024-09-08 15:49:17', 'IK10203414', 1, 'aktif'),
(548, 'Yasemin', 'EGE', '05418372282', '', '2024-09-08 15:53:30', 'IK10203561', 1, 'aktif'),
(549, 'Recai', 'AK', '05062052715', '', '2024-09-08 15:55:07', 'IK10203921', 1, 'aktif'),
(550, 'Yasemin', 'KAYGIN', '05422312568', '', '2024-09-08 16:02:21', 'IK10203172', 1, 'aktif'),
(551, 'Şükran', 'SÜNBÜL', '05444490913', '', '2024-09-08 16:05:26', 'IK10203868', 1, 'aktif'),
(552, 'Hasret', 'AYGÜN', '05466913245', '', '2024-09-08 16:09:30', 'IK10203312', 1, 'aktif'),
(553, 'Kamil', 'ÇİVİCİ', '05322112208', '', '2024-09-08 16:25:08', 'IK10203712', 1, 'aktif'),
(554, 'Mevlüt', 'ÇEVİK', '05366765204', 'm.mevlut.melike52@gmail.com', '2024-09-08 16:31:12', 'IK10203200', 1, 'aktif'),
(555, 'Gökhan', 'KISACIK', '05531361519', '', '2024-09-08 16:42:17', 'IK10203495', 1, 'aktif'),
(556, 'Eda', 'ELMAS', '05424286838', '', '2024-09-08 16:52:07', 'IK10203996', 1, 'aktif'),
(557, 'Şeyma', 'ÇAVUŞ', '05397338910', '', '2024-09-08 17:00:50', 'IK10203843', 1, 'aktif'),
(558, 'Fatma', 'KÖSTEK', '05436309552', '', '2024-09-08 17:04:35', 'IK10203975', 1, 'aktif'),
(559, 'Musa', 'GEÇTİN', '05365890977', '', '2024-09-08 17:11:29', 'IK10203008', 1, 'aktif'),
(560, 'Ercan', 'ERMEK', '05541157371', '', '2024-09-08 17:14:09', 'IK10203233', 1, 'aktif'),
(561, 'Burcu', 'ÖKLÜK', '05416625866', '', '2024-09-08 17:20:08', 'IK10203625', 1, 'aktif'),
(562, 'ADEM', 'GÜNDÜZ', '05457905207', '', '2024-09-08 17:20:42', 'IK10203146', 1, 'aktif'),
(563, 'Fatih', 'YEŞİLYURT', '05347241632', '', '2024-09-08 17:26:31', 'IK10203461', 1, 'aktif'),
(564, 'Elçin', 'KÜTÜK', '05418418054', '', '2024-09-08 17:30:01', 'IK10203385', 1, 'aktif'),
(565, 'BETÜL', 'KÜÇÜK', '05436922458', '', '2024-09-08 17:44:10', 'IK10203133', 1, 'aktif'),
(566, 'TUĞBA', 'ULAŞ', '05400039898', '', '2024-09-08 17:45:38', 'IK10203941', 1, 'aktif'),
(567, 'ALİ CAN', 'ERDEMLİ', '05395059931', '', '2024-09-08 17:46:07', 'IK10203046', 1, 'aktif'),
(568, 'OSMAN', 'NAYCI', '05413181210', '', '2024-09-08 18:10:07', 'IK10203467', 1, 'aktif'),
(569, 'AYŞEGÜL ', 'SATILMIŞ', '05366751555', '', '2024-09-08 18:26:17', 'IK10203494', 1, 'aktif'),
(570, 'OKTAY', 'GEDEK', '05364141983', '', '2024-09-08 18:28:57', 'IK10203306', 1, 'aktif'),
(571, 'İLKNUR', 'TİMUR TÜRKAN', '05393531465', '', '2024-09-08 18:35:02', 'IK10203723', 1, 'aktif'),
(572, 'Hacer', 'BULACAK', '05385572723', '', '2024-09-08 18:44:52', 'IK10203744', 1, 'aktif'),
(573, 'AYSEL', 'ÇIRPAN', '05382930482', '', '2024-09-08 18:45:52', 'IK10203079', 1, 'aktif'),
(574, 'Ali Kemal', 'ÖZER', '05448385222', 'didialikemal@hotmail.com', '2024-09-08 19:09:00', 'IK10203248', 1, 'aktif'),
(575, 'İbrahim', 'ÖZGÖR', '05363231384', '', '2024-09-08 19:15:18', 'IK10203618', 1, 'aktif'),
(576, 'Sezer', 'AKDAL', '05343618259', '', '2024-09-08 19:20:04', 'IK10203901', 1, 'aktif'),
(577, 'Hayati', 'HASOĞLU', '05464522952', '', '2024-09-08 19:22:28', 'IK10203225', 1, 'aktif');
INSERT INTO `musteriler` (`id`, `ad`, `soyad`, `telefon`, `email`, `kayit_tarihi`, `barkod`, `sms_aktif`, `durum`) VALUES
(578, 'Gafur', 'AZİZİ', '05525269477', '', '2024-09-08 19:32:43', 'IK10203993', 1, 'aktif'),
(579, 'Halil Efe', 'DOLU', '05059177400', '', '2024-09-08 19:34:51', 'IK10203588', 1, 'aktif'),
(580, 'EMİNE', 'VARLIK', '05435713243', '', '2024-09-08 20:06:07', 'IK10203255', 1, 'aktif'),
(581, 'SELMA', 'ATUK SOY', '05426644020', '', '2024-09-08 20:06:48', 'IK10203646', 1, 'aktif'),
(582, 'DUYGU', 'PEHLİVAN', '05468775152', '', '2024-09-08 20:23:48', 'IK10203651', 1, 'aktif'),
(583, 'Murat', 'DOLBUN', '05360171343', '', '2024-09-08 20:37:45', 'IK10203362', 1, 'aktif'),
(584, 'SULTAN', 'ŞAL YARDIM', '05469653610', '', '2024-09-08 20:53:05', 'IK10203567', 1, 'aktif'),
(585, 'Necla', 'GEDİK', '05076673190', '', '2024-09-08 21:01:54', 'IK10203846', 1, 'aktif'),
(586, 'MUSTAFA', 'ÖNEZ', '05454094627', '', '2024-09-08 21:54:52', 'IK10203666', 1, 'aktif'),
(587, 'SEHER', 'KURU', '05437939024', '', '2024-09-08 21:57:35', 'IK10203005', 1, 'aktif'),
(588, 'faruk', 'soğan', '05317815952', '', '2024-09-09 09:08:15', 'IK10203015', 1, 'aktif'),
(589, 'hüseyin ', 'çivici', '05386532505', '', '2024-09-09 09:30:44', 'IK10203474', 1, 'aktif'),
(590, 'NESLİHAN', 'ERİŞMİŞ', '05428205090', '', '2024-09-09 09:43:09', 'IK10203609', 1, 'aktif'),
(591, 'RECEP', 'YÜMLÜ', '05434395907', '', '2024-09-09 09:45:52', 'IK10203125', 1, 'aktif'),
(592, 'İLHAMİ', 'ARTMA', '05414701998', '', '2024-09-09 10:38:13', 'IK10203738', 1, 'aktif'),
(593, 'Erdoğan', 'UĞURLU', '05353259352', '', '2024-09-09 13:46:52', 'IK10203640', 1, 'aktif'),
(594, 'SEVCAN', 'KRIMLI', '05392549952', '', '2024-09-09 14:53:18', 'IK10203297', 1, 'aktif'),
(595, 'ÜMMET ', 'GÜVEN', '05453978648', '', '2024-09-09 14:55:49', 'IK10203340', 1, 'aktif'),
(596, 'FUNDA', 'TOP', '05309533235', '', '2024-09-09 15:03:15', 'IK10203506', 1, 'aktif'),
(597, 'ÖZLEM', 'HAZIRCI', '05412950889', '', '2024-09-09 15:12:13', 'IK10203042', 1, 'aktif'),
(598, 'Sevda', 'KUM', '05412614523', '', '2024-09-09 15:42:40', 'IK10203283', 1, 'aktif'),
(599, 'Nurgül', 'ÖZKAN KARA', '05321550991', 'nrgl--ozkn@hotmail.com', '2024-09-09 15:44:46', 'IK10203795', 1, 'aktif'),
(600, 'Şule', 'BURMA', '05398622112', '', '2024-09-09 15:48:56', 'IK10203234', 1, 'aktif'),
(601, 'Şükriye', 'İREGEN', '05342797836', '', '2024-09-09 15:55:54', 'IK10203955', 1, 'aktif'),
(602, 'Kader', 'UYGUN', '05438319802', '', '2024-09-09 16:01:24', 'IK10203209', 1, 'aktif'),
(603, 'Hacer Nur', 'KIZILOT', '05452099320', '', '2024-09-09 16:11:20', 'IK10203169', 1, 'aktif'),
(604, 'Semra', 'AYVERDİ', '05316261483', '', '2024-09-09 16:16:23', 'IK10203256', 1, 'aktif'),
(605, 'Necla', 'SOYSAL SİREK', '05432941977', '', '2024-09-09 16:25:16', 'IK10203971', 1, 'aktif'),
(606, 'Fatma', 'CİVELEK', '05052684761', '', '2024-09-09 16:36:34', 'IK10203827', 1, 'aktif'),
(607, 'Mustafa', 'TOPRAKBASTI', '05556808955', 'mustafatoprakbasti@gmail.com', '2024-09-09 16:41:18', 'IK10203644', 1, 'aktif'),
(608, 'Kübra', 'KISA', '05363599388', '', '2024-09-09 16:51:33', 'IK10203336', 1, 'aktif'),
(609, 'Yasemin', 'YEŞİLDUMAN', '05467447472', '', '2024-09-09 16:52:17', 'IK10203454', 1, 'aktif'),
(610, 'Nesrin', 'TEPE', '05447679252', '', '2024-09-09 16:57:16', 'IK10203195', 1, 'aktif'),
(611, 'Evren', 'ULUS', '05323338946', '', '2024-09-09 17:03:57', 'IK10203592', 1, 'aktif'),
(612, 'Çiğdem', 'YAMAN', '05457394622', '', '2024-09-09 17:10:58', 'IK10203686', 1, 'aktif'),
(613, 'Reyhan', 'MOL', '05362471332', '', '2024-09-09 17:13:51', 'IK10203326', 1, 'aktif'),
(614, 'Çiğdem', 'ÖZCAN', '05314922052', '', '2024-09-09 17:23:47', 'IK10203804', 1, 'aktif'),
(615, 'Elif', 'YILMAZER', '05380924384', '', '2024-09-09 17:26:57', 'IK10203981', 1, 'aktif'),
(616, 'Merve', 'ÇAVUŞ', '05307484656', '', '2024-09-09 17:27:16', 'IK10203406', 1, 'aktif'),
(617, 'Emir', 'KURU', '05414725252', '', '2024-09-09 17:42:26', 'IK10203585', 1, 'aktif'),
(618, 'Zeliha', 'AKSOY', '05321131985', '', '2024-09-09 17:49:13', 'IK10203389', 1, 'aktif'),
(619, 'ÖZGE', 'DÜZGÜNEY', '05392822266', '', '2024-09-09 17:55:10', 'IK10203025', 1, 'aktif'),
(620, 'İlknur', 'ÖZÇEVİK', '05428042230', '', '2024-09-09 17:57:15', 'IK10203085', 1, 'aktif'),
(621, 'Esme', 'ARIKAN', '05537055228', '', '2024-09-09 18:01:39', 'IK10203309', 1, 'aktif'),
(622, 'Elif', 'TALAY', '05076496686', '', '2024-09-09 18:07:00', 'IK10203097', 1, 'aktif'),
(623, 'Osman', 'BAYIR', '05315025001', '', '2024-09-09 18:18:00', 'IK10203003', 1, 'aktif'),
(624, 'Ayten', 'PAT', '05434663033', '', '2024-09-09 18:23:03', 'IK10203641', 1, 'aktif'),
(625, 'Hatice', 'SAYGU', '05386107890', '', '2024-09-09 18:28:11', 'IK10203549', 1, 'aktif'),
(626, 'Necla', 'EMEN', '05467355099', '', '2024-09-09 18:34:44', 'IK10203502', 1, 'aktif'),
(627, 'Meryem', 'AKMAN', '05380598225', '', '2024-09-09 18:39:22', 'IK10203395', 1, 'aktif'),
(628, 'Habibe', 'YILDIRIM', '05315020265', '', '2024-09-09 18:41:54', 'IK10203314', 1, 'aktif'),
(629, 'İsa', 'YAKARIŞ', '05316650676', '', '2024-09-09 18:45:53', 'IK10203208', 1, 'aktif'),
(630, 'Fatma', 'ÇELİK', '05362076724', '', '2024-09-09 18:51:57', 'IK10203717', 1, 'aktif'),
(631, 'Vildan', 'HOCAOĞLU', '05442541308', '', '2024-09-09 18:54:06', 'IK10203742', 1, 'aktif'),
(632, 'Hakan', 'ŞEN', '05363313565', '', '2024-09-09 18:57:07', 'IK10203346', 1, 'aktif'),
(633, 'Bilal', 'ÖZLE', '05385802010', '', '2024-09-09 19:02:16', 'IK10203677', 1, 'aktif'),
(634, 'Emine', 'DUYMAZ', '05382287685', '', '2024-09-09 19:03:06', 'IK10203853', 1, 'aktif'),
(635, 'İremsu', 'SEÇMEZ', '05392025348', '', '2024-09-09 19:05:13', 'IK10203586', 1, 'aktif'),
(636, 'Zülkif', 'AKYAZI', '05515977317', '', '2024-09-09 19:08:25', 'IK10203447', 1, 'aktif'),
(637, 'cihan', 'coruh', '05396638789', '', '2024-09-09 19:51:04', 'IK10203893', 1, 'aktif'),
(638, 'MUHAMMET ALİ', 'AŞANDOĞRUL', '05455547445', '', '2024-09-09 20:29:47', 'IK10203060', 1, 'aktif'),
(639, 'HAKAN', 'YADİ', '05417654325', '', '2024-09-09 20:42:18', 'IK10203568', 1, 'aktif'),
(640, 'BÜŞRA', 'HEP', '05555884906', '', '2024-09-09 20:59:38', 'IK10203071', 1, 'aktif'),
(641, 'Onur', 'BEKTAŞ', '05414780540', '', '2024-09-09 21:06:49', 'IK10203253', 1, 'aktif'),
(642, 'Abdurrahman', 'KOÇAN', '05396688908', '', '2024-09-09 21:08:15', 'IK10203648', 1, 'aktif'),
(643, 'İlknur', 'İNCİ', '05357134048', '', '2024-09-09 21:11:26', 'IK10203216', 1, 'aktif'),
(644, 'Ufuk', 'ALTUN', '05432725252', '', '2024-09-09 21:12:18', 'IK10203769', 1, 'aktif'),
(645, 'Aysun', 'YELOL', '05442814902', '', '2024-09-09 21:13:19', 'IK10203698', 1, 'aktif'),
(646, 'Kübra', 'EKCE', '05414515767', '', '2024-09-09 21:14:11', 'IK10203369', 1, 'aktif'),
(647, 'Hatice', 'YEK', '05426570152', '', '2024-09-09 21:14:54', 'IK10203268', 1, 'aktif'),
(648, 'Zübeyde', 'ÇAKAN', '05459452050', '', '2024-09-09 21:15:55', 'IK10203667', 1, 'aktif'),
(649, 'Sibel', 'ORAK', '05301176040', '', '2024-09-09 21:18:03', 'IK10203542', 1, 'aktif'),
(650, 'Kübra', 'VAROL', '05443164510', '', '2024-09-09 21:25:28', 'IK10203419', 1, 'aktif'),
(651, 'Cennet', 'BERGE ÇEKME', '05456025807', '', '2024-09-09 21:27:44', 'IK10203100', 1, 'aktif'),
(652, 'Yasemin', 'BERBER', '05071580883', '', '2024-09-09 21:28:35', 'IK10203816', 1, 'aktif'),
(653, 'Tuğba', 'BEREK', '05455028603', '', '2024-09-09 21:31:06', 'IK10203231', 1, 'aktif'),
(654, 'Özcan', 'ERDEMLİ', '05398544950', '', '2024-09-09 21:36:43', 'IK10203633', 1, 'aktif'),
(655, 'Veysel', 'İSTEK', '05457859253', '', '2024-09-09 21:37:30', 'IK10203170', 1, 'aktif'),
(656, 'Ebru', 'KATI', '05549691060', '', '2024-09-09 21:38:22', 'IK10203607', 1, 'aktif'),
(657, 'Buket', 'KURU', '05448277713', '', '2024-09-09 21:39:20', 'IK10203078', 1, 'aktif'),
(658, 'Hülya', 'BEYAZ', '05398285525', '', '2024-09-09 21:40:03', 'IK10203615', 1, 'aktif'),
(659, 'Sibel', 'TEZİN', '05348455055', '', '2024-09-09 21:40:46', 'IK10203895', 1, 'aktif'),
(660, 'Selda', 'KALE', '05447630552', '', '2024-09-09 21:41:27', 'IK10203802', 1, 'aktif'),
(661, 'Aysel', 'AVKAŞ', '05335956714', '', '2024-09-09 21:45:11', 'IK10203551', 1, 'aktif'),
(662, 'Salih', 'ERDEM', '05374294172', '', '2024-09-09 21:46:01', 'IK10203363', 1, 'aktif'),
(663, 'eda', 'topçuoğlu', '05397619386', '', '2024-09-10 08:01:45', 'IK10203737', 1, 'aktif'),
(664, 'MUSA', 'YILDIZ', '05333246931', '', '2024-09-10 08:40:31', 'IK10203604', 1, 'aktif'),
(665, 'SALİH', 'GÜLMEZ', '05531198751', '', '2024-09-10 09:07:01', 'IK10203859', 1, 'aktif'),
(666, 'LAHURİ', 'ENEZ', '05446780052', '', '2024-09-10 09:19:30', 'IK10203442', 1, 'aktif'),
(667, 'SAFİYE', 'KURU', '05459785143', '', '2024-09-10 09:52:48', 'IK10203535', 1, 'aktif'),
(668, 'ADEM ', 'ADIKTI', '05368776227', '', '2024-09-10 10:05:24', 'IK10203112', 1, 'aktif'),
(669, 'AYŞENUR', 'İS', '05060622027', '', '2024-09-10 10:50:28', 'IK10203325', 1, 'aktif'),
(670, 'MELTEM', 'GÜLEŞ', '05444204902', '', '2024-09-10 11:10:22', 'IK10203630', 1, 'aktif'),
(671, 'enes', 'çekme', '05418484256', '', '2024-09-10 11:22:27', '10289928964', 1, 'aktif'),
(672, 'havva', 'çekme', '05513895206', '', '2024-09-10 11:23:50', 'IK10203517', 1, 'aktif'),
(673, 'meryem', 'evet', '05422584524', '', '2024-09-10 11:32:37', 'IK10203417', 1, 'aktif'),
(674, 'Duygu', 'BAŞ', '05309320884', '', '2024-09-10 12:33:59', 'IK10203463', 1, 'aktif'),
(675, 'SERHAT', 'PINARÖZÜ', '05443236704', '', '2024-09-10 12:44:08', 'IK10203010', 1, 'aktif'),
(676, 'aysun', 'KARAPINAR', '05532503280', '', '2024-09-10 12:49:43', 'IK10203054', 1, 'aktif'),
(677, 'GÜLCÜK', 'EYİGEL', '05372950480', '', '2024-09-10 13:00:50', 'IK10203751', 1, 'aktif'),
(678, 'EBRU', 'BALTA', '05436099833', '', '2024-09-10 13:35:00', 'IK10203113', 1, 'aktif'),
(679, 'Suat', 'ÇULLU', '05541186664', '', '2024-09-10 15:01:29', 'IK10203597', 1, 'aktif'),
(680, 'Mehmet', 'karaman', '05370361345', '', '2024-09-10 16:30:04', 'IK10203252', 1, 'aktif'),
(681, 'büşra', 'yeni', '05349502712', '', '2024-09-10 16:37:52', 'IK10203105', 1, 'aktif'),
(682, 'ömer asaf', 'tunur', '05453509834', '', '2024-09-10 16:50:33', 'IK10203966', 1, 'aktif'),
(683, 'ARZU', 'DİKİLİ', '05455505153', '', '2024-09-10 17:48:05', 'IK10203315', 1, 'aktif'),
(684, 'ZÜLEYHA ', 'ÖZGEN ', '05054788150', '', '2024-09-10 17:53:02', 'IK10203683', 1, 'aktif'),
(685, 'Canan', 'GÜR', '05303075906', '', '2024-09-11 12:16:38', 'IK05303075906', 1, 'aktif'),
(686, 'Havva', 'İNAN', '05387768707', '', '2024-09-11 12:23:04', 'IK10203064', 1, 'aktif'),
(688, 'Yasemin', 'EMİL', '05327094031', '', '2024-09-11 12:49:21', 'IK05327094031', 1, 'aktif'),
(689, 'Rabia', 'NAYCI', '05415880744', '', '2024-09-11 12:53:10', 'IK10203728', 1, 'aktif'),
(690, 'İsa', 'DÜGÜ', '05379307973', '', '2024-09-11 12:59:55', 'IK05379307973', 1, 'aktif'),
(691, 'Ayşenur', 'SİRKECİ', '05514563152', '', '2024-09-11 13:01:23', 'IK05514563152', 1, 'aktif'),
(692, 'Nuray', 'İLERİ', '05052629467', '', '2024-09-11 13:02:41', 'IK10203045', 1, 'aktif'),
(693, 'Selim', 'ATAOĞLU', '05308827808', '', '2024-09-11 13:03:39', 'IK10203773', 1, 'aktif'),
(694, 'Müslüm', 'AKYAZI', '05319577124', '', '2024-09-11 13:10:04', 'IK10203991', 1, 'aktif'),
(695, 'Emrah', 'ÇAKIL', '05434231152', '', '2024-09-11 13:14:53', 'IK10204571', 1, 'aktif'),
(696, 'Ruhi', 'AKBAŞ', '05413721122', '', '2024-09-11 13:15:51', 'IK05413721122', 1, 'aktif'),
(697, 'Hilal', 'AYDINLIK', '05432446491', '', '2024-09-11 13:17:58', 'IK05432446491', 1, 'aktif'),
(698, 'Cansel', 'YAHŞİ', '05360328788', '', '2024-09-11 13:18:57', 'IK10204522', 1, 'aktif'),
(699, 'Orhan', 'DEREBAŞI', '05424525440', '', '2024-09-11 13:19:53', 'IK05424525440', 1, 'aktif'),
(700, 'Zeki', 'ERARSLAN', '05359453687', '', '2024-09-11 13:23:13', 'IK05359453687', 1, 'aktif'),
(701, 'Sevgi', 'YILMAZ', '05389426462', '', '2024-09-11 13:28:16', 'IK05389426462', 1, 'aktif'),
(702, 'Büşra', 'ÇITIR', '05393604117', '', '2024-09-11 13:28:47', 'IK10204576', 1, 'aktif'),
(703, 'Uğur', 'AYGÜZEL', '05416465252', '', '2024-09-11 13:30:30', 'IK10203050', 1, 'aktif'),
(704, 'Nazlı', 'AÇIKYER', '05348223725', '', '2024-09-11 13:31:42', 'IK10203167', 1, 'aktif'),
(705, 'Muammer', 'KÜÇÜK', '05382559909', '', '2024-09-11 13:33:11', 'IK10203892', 1, 'aktif'),
(706, 'Havva', 'PULLUK', '05422066252', '', '2024-09-11 13:34:09', 'IK10203341', 1, 'aktif'),
(707, 'Esra', 'UYGUN', '05310318312', '', '2024-09-11 13:37:07', 'IK05310318312', 1, 'aktif'),
(708, 'Nihan', 'ÖZEN', '05522150808', '', '2024-09-11 13:38:13', 'IK10204606', 1, 'aktif'),
(709, 'Ramazan', 'KULAK', '05065156968', '', '2024-09-11 13:39:59', 'IK10204584', 1, 'aktif'),
(710, 'Samet', 'GÜVEN', '05466713696', '', '2024-09-11 13:42:51', 'IK05466713696', 1, 'aktif'),
(711, 'Sezgin', 'ÖĞME', '05427832689', '', '2024-09-11 14:26:21', 'IK05427832689', 1, 'aktif'),
(712, 'Büşra', 'HU', '05382043652', '', '2024-09-11 15:37:41', 'IK10204392', 1, 'aktif'),
(713, 'Yeliz', 'TUTUK', '05375863752', '', '2024-09-11 15:54:17', 'IK05375863752', 1, 'aktif'),
(714, 'Semine', 'GÜNEŞ', '05387992109', '', '2024-09-11 16:35:34', 'IK10204316', 1, 'aktif'),
(715, 'Bülbül', 'SARIHAN', '05345219022', '', '2024-09-11 16:43:45', 'IK05345219022', 1, 'aktif'),
(716, 'Dilek', 'TRABZON KAYA', '05325606095', '', '2024-09-11 17:58:29', 'IK10204848', 1, 'aktif'),
(717, 'Şura', 'ÇANKA', '05449333223', '', '2024-09-11 18:11:16', 'IK05449333223', 1, 'aktif'),
(718, 'zekayi', 'altuntaş', '05413785592', '', '2024-09-11 18:18:55', 'IK05413785592', 1, 'aktif'),
(719, 'GÜLSÜM', 'DAĞ', '05304979556', '', '2024-09-11 18:32:17', 'IK10204496', 1, 'aktif'),
(720, 'Mustafa', 'BAYRAMLI', '05366894402', '', '2024-09-11 20:32:32', 'IK10204583', 1, 'aktif'),
(721, 'BİROL', 'EREK', '05353051675', '', '2024-09-11 20:33:20', 'IK05353051675', 1, 'aktif'),
(722, 'Gülcan', 'AKSU', '05559673046', '', '2024-09-11 20:34:09', 'IK10204190', 1, 'aktif'),
(723, 'Mehmet', 'ESİM', '05334598628', '', '2024-09-11 20:34:47', 'IK10204171', 1, 'aktif'),
(724, 'Hüseyin', 'BAŞATLIK', '05063841518', '', '2024-09-11 20:36:01', 'IK05063841518', 1, 'aktif'),
(725, 'ELİF', 'BEYAZIT', '05307836562', '', '2024-09-12 16:14:28', 'IK05307836562', 1, 'aktif'),
(726, 'ÜMİT', 'BAHTİYAR', '05353239881', '', '2024-09-12 16:16:49', 'IK10204423', 1, 'aktif'),
(727, 'GAMZE', 'ARSLAN', '05392538021', '', '2024-09-12 18:11:17', 'IK05392538021', 1, 'aktif'),
(728, 'HAMİ', 'ÇELİKAY', '05347665816', '', '2024-09-13 10:45:56', 'IK10204217', 1, 'aktif'),
(729, 'sevim', 'seri', '05395508369', '', '2024-09-13 14:08:06', 'IK10204528', 1, 'aktif'),
(730, 'SERPİL', ' ASLANTÜRK', '05533989092', '', '2024-09-13 14:23:30', 'IK10204590', 1, 'aktif'),
(731, 'Saliha', 'KESKİN', '05055839988', '', '2024-09-13 15:04:00', 'IK10204422', 1, 'aktif'),
(732, 'Demet', 'KAYNAK', '05351018591', '', '2024-09-13 15:04:51', 'IK10204491', 1, 'aktif'),
(733, 'Öznur', 'BİLLAY', '05457632881', '', '2024-09-13 15:06:34', 'IK10204520', 1, 'aktif'),
(734, 'Dinçer', 'YENİYILDIZ', '05418735352', '', '2024-09-13 15:07:39', 'IK05418735352', 1, 'aktif'),
(735, 'Gülcihan', 'KARA', '05315775423', '', '2024-09-13 15:08:13', 'IK10204591', 1, 'aktif'),
(736, 'Merve', 'YILMAZ', '05389133332', '', '2024-09-13 15:13:57', 'IK05389133332', 1, 'aktif'),
(737, 'emine', 'abalı', '05385410409', '', '2024-09-13 17:42:03', 'IK10204592', 1, 'aktif'),
(738, 'öznur', 'güneş', '05365466752', '', '2024-09-13 18:03:23', 'IK10204593', 1, 'aktif'),
(739, 'zekiye', 'bayazıt', '05308833377', '', '2024-09-13 19:55:10', 'IK10204595', 1, 'aktif'),
(740, 'mehmet ', 'uysal', '05397382244', '', '2024-09-14 10:59:57', 'IK10204596', 1, 'aktif'),
(741, 'murat', 'özkan', '05383051507', '', '2024-09-14 11:04:53', 'IK10204597', 1, 'aktif'),
(742, 'fazlı', 'gül', '05435325669', '', '2024-09-14 12:03:58', 'IK10204598', 1, 'aktif'),
(743, 'EMİNE ', 'çoşkun', '05347975222', '', '2024-09-14 12:17:53', 'IK10204599', 1, 'aktif'),
(744, 'Elif', 'TARAK', '05458999297', '', '2024-09-14 14:26:12', 'IK10204600', 1, 'aktif'),
(745, 'BARIŞCAN', 'ŞENER', '05077795865', '', '2024-09-14 15:12:29', 'IK10204601', 1, 'aktif'),
(746, 'HANİFE', 'SOYLU', '05448704324', '', '2024-09-14 15:24:51', 'IK10204602', 1, 'aktif'),
(747, 'ezgi', 'yağcı', '05300885826', '', '2024-09-14 16:43:52', 'IK10204603', 1, 'aktif'),
(748, 'SEYHAN', 'ÖVECEK', '05307768241', '', '2024-09-14 17:18:22', 'IK10204604', 1, 'aktif'),
(749, 'KADİR', 'MİKEL', '05308827773', '', '2024-09-14 17:36:29', 'IK10204605', 1, 'aktif'),
(750, 'kübra', 'çoşkun', '05330882254', '', '2024-09-14 17:44:15', 'IK10204607', 1, 'aktif'),
(751, 'aynur', 'özgöller', '05379905462', '', '2024-09-14 18:40:40', 'IK10204608', 1, 'aktif'),
(752, 'nazlı', 'keskin', '05354779806', '', '2024-09-14 19:02:18', 'IK10204535', 1, 'aktif'),
(753, 'serap', 'samsun', '05442680505', '', '2024-09-14 19:04:28', 'IK10204534', 1, 'aktif'),
(754, 'asiye', 'özsoy', '05435402260', '', '2024-09-14 19:21:50', 'IK10204536', 1, 'aktif'),
(755, 'HATİCE', 'KAYA', '05437841541', '', '2024-09-14 19:34:51', 'IK10204537', 1, 'aktif'),
(756, 'selaattin ', 'yiğit', '05378819583', '', '2024-09-14 19:39:31', 'IK10204538', 1, 'aktif'),
(757, 'soner', 'sarıhan', '05307731920', '', '2024-09-15 11:45:56', 'IK10204569', 1, 'aktif'),
(758, 'serkan', 'ildeniz', '05424204362', '', '2024-09-15 11:53:12', 'IK10204570', 1, 'aktif'),
(759, 'asım', 'şahin', '05445200274', '', '2024-09-15 13:51:40', 'IK10204572', 1, 'aktif'),
(760, 'Eslem', 'GÜLÜM', '05426041052', '', '2024-09-15 14:32:38', 'IK10204573', 1, 'aktif'),
(761, 'Sümeyra', 'GEÇTİNLİ', '05372965631', '', '2024-09-15 14:43:22', 'IK10204575', 1, 'aktif'),
(762, 'Esma', 'SAYGILI KİBAR', '05067004765', '', '2024-09-15 15:11:22', 'IK10204577', 1, 'aktif'),
(763, 'kazım', 'öztürk', '05303867252', '', '2024-09-15 16:53:56', 'IK10204574', 1, 'aktif'),
(764, 'büşra', 'kısacık', '05378240425', '', '2024-09-15 17:01:14', 'IK10204579', 1, 'aktif'),
(765, 'Burhan', 'İPEKÇİ', '05323104192', '', '2024-09-15 18:55:41', 'IK10204581', 1, 'aktif'),
(766, 'Kerem', 'AYHAN', '05427245640', '', '2024-09-15 18:59:16', 'IK10204578', 1, 'aktif'),
(767, 'Muharrem', 'KAŞALTİ', '05434245727', '', '2024-09-15 19:17:21', 'IK10204368', 1, 'aktif'),
(768, 'Mümin', 'TİRYAKİOĞLU', '05348440452', '', '2024-09-15 19:17:54', 'IK10204580', 1, 'aktif'),
(769, 'Gülşah', 'OCAK GÜMÜŞ', '05308615880', '', '2024-09-15 19:36:18', 'IK10204582', 1, 'aktif'),
(770, 'Sedanur', 'ATİKLİK', '05445633618', '', '2024-09-15 20:31:33', 'IK10204585', 1, 'aktif'),
(771, 'DUYGU', 'GÜNEŞ', '05465743734', '', '2024-09-15 20:55:31', 'IK10204587', 1, 'aktif'),
(772, 'emrullah', 'kısacık', '05316650900', '', '2024-09-16 10:24:09', 'IK10204586', 1, 'aktif'),
(773, 'MUSTAFA', 'İNCİKABI', '05070640052', '', '2024-09-16 10:30:27', 'IK10204588', 1, 'aktif'),
(774, 'ÖZLEM', 'SEYİTOĞLU', '05336144261', '', '2024-09-16 11:05:44', 'IK10204492', 1, 'aktif'),
(775, 'çiğdem', 'sezgil', '05315889596', '', '2024-09-16 12:02:44', 'IK10204069', 1, 'aktif'),
(776, 'burcu', 'yalman', '05465695154', '', '2024-09-16 12:07:57', 'IK10204490', 1, 'aktif'),
(777, 'tülay', 'aka', '05318896810', '', '2024-09-16 12:16:45', 'IK10204068', 1, 'aktif'),
(778, 'FATİH', 'ŞENSÖZLÜ', '05344848245', '', '2024-09-16 14:22:27', 'IK10204067', 1, 'aktif'),
(779, 'yasemin', 'duman', '05544560588', '', '2024-09-16 14:43:11', 'IK10204066', 1, 'aktif'),
(780, 'Yavuz', 'DOĞAN', '05305652183', '', '2024-09-16 14:51:37', 'IK10204065', 1, 'aktif'),
(781, 'Ebru', 'BÜYÜKASLAN', '05412614540', '', '2024-09-16 14:57:12', 'IK10204064', 1, 'aktif'),
(782, 'Emre', 'ARSLAN', '05539105471', '', '2024-09-16 15:12:14', 'IK10204063', 1, 'aktif'),
(783, 'Naziye', 'GÜNEŞ', '05398453157', '', '2024-09-16 15:41:16', 'IK10204589', 1, 'aktif'),
(784, 'Şule', 'BİLİCİ', '05056354449', '', '2024-09-16 16:33:16', 'IK10204369', 1, 'aktif'),
(785, 'Mehtap', 'BAŞ', '05363010387', '', '2024-09-16 16:35:49', 'IK10204370', 1, 'aktif'),
(786, 'Rümeysa', 'YEŞİL', '05527178807', '', '2024-09-16 16:42:24', 'IK10204493', 1, 'aktif'),
(787, 'Ayşe', 'YILMAZ', '05073635219', '', '2024-09-16 17:17:40', 'IK10204495', 1, 'aktif'),
(788, 'Sinem', 'ÖZLEK', '05417873806', '', '2024-09-16 17:18:41', 'IK10204497', 1, 'aktif'),
(789, 'Büşranur', 'İRGİ', '05424108611', '', '2024-09-16 17:21:46', 'IK10204498', 1, 'aktif'),
(790, 'nADİR', 'GÜVENÇ', '05442746566', '', '2024-09-16 18:17:21', 'IK10204999', 1, 'aktif'),
(791, 'Züleyha', 'ÇELTİK', '05309228152', '', '2024-09-16 18:35:17', 'IK10204365', 1, 'aktif'),
(792, 'Emine', 'KADIOĞLU', '05072097297', '', '2024-09-16 19:53:28', 'IK10204366', 1, 'aktif'),
(793, 'Serpil', 'TONBAZ', '05459715873', '', '2024-09-16 20:01:24', 'IK10204371', 1, 'aktif'),
(794, 'Özlem', 'DEMİRCİ', '05469797929', '', '2024-09-16 20:01:49', 'IK10204372', 1, 'aktif'),
(795, 'VİLDAN', 'YEREBASMAZ', '05363851396', '', '2024-09-17 10:37:51', 'IK10204373', 1, 'aktif'),
(796, 'TUĞBA', 'GÜR', '05459092863', '', '2024-09-17 10:43:33', 'IK10204374', 1, 'aktif'),
(797, 'HATİCE', 'DIRIK', '05379186183', '', '2024-09-17 15:35:03', 'IK10204367', 1, 'aktif'),
(798, 'AHMET ASAF', 'ÇAKMAK', '05308610852', '', '2024-09-17 17:41:30', 'IK10204376', 1, 'aktif'),
(799, 'SELEN OMURA ', 'PEKBÜYÜK', '05316739052', '', '2024-09-17 17:42:10', 'IK10204375', 1, 'aktif'),
(800, 'FUNDA', 'YETER', '05419678806', '', '2024-09-17 17:46:24', 'IK10204377', 1, 'aktif'),
(801, 'emrah', 'güler', '05066432905', '', '2024-09-17 17:59:36', 'IK10204378', 1, 'aktif'),
(802, 'tülay', 'koç', '05369381560', '', '2024-09-17 18:05:49', 'IK10204379', 1, 'aktif'),
(803, 'selma', 'zerey', '05303054373', '', '2024-09-17 18:15:05', 'IK10204527', 1, 'aktif'),
(804, 'dilek', 'ertan', '05061543695', '', '2024-09-17 18:17:57', 'IK10204514', 1, 'aktif'),
(805, 'murat', 'çalık', '05455320102', '', '2024-09-17 18:22:53', 'IK10204515', 1, 'aktif'),
(806, 'belinay', 'serin', '05454021016', '', '2024-09-17 18:28:50', 'IK10204523', 1, 'aktif'),
(807, 'YÜCEL YALÇIN', 'EKİNÖZÜ', '05533977155', '', '2024-09-17 18:30:50', 'IK10204516', 1, 'aktif'),
(808, 'İBRAHİM', 'ŞAHİN', '05363697473', '', '2024-09-17 18:32:53', 'IK10204518', 1, 'aktif'),
(809, 'harun ', 'akbaba', '05415537831', '', '2024-09-17 19:19:39', 'IK10204521', 1, 'aktif'),
(810, 'Adem', 'ŞENDUR', '05436450152', '', '2024-09-17 19:34:03', 'IK10204519', 1, 'aktif'),
(811, 'zeynep', 'yüzyıl tür', '05342352361', '', '2024-09-17 20:06:36', 'IK10204524', 1, 'aktif'),
(812, 'dürdane', 'yaman', '05389804825', '', '2024-09-18 11:01:27', 'IK10204525', 1, 'aktif'),
(813, 'saide', 'demirci', '05464875237', '', '2024-09-18 11:05:36', 'IK10204526', 1, 'aktif'),
(814, 'MERVE', 'ALVER', '05342714538', '', '2024-09-18 16:13:19', 'IK10204529', 1, 'aktif'),
(815, 'CÜNEYT', 'AKYÜZ', '05434835218', '', '2024-09-18 17:05:41', 'IK10204530', 1, 'aktif'),
(816, 'NURGÜL', 'HEKİM', '05418816652', '', '2024-09-18 17:12:36', 'IK10204531', 1, 'aktif'),
(817, 'İsa', 'DİRHEMSİZ', '05417810052', '', '2024-09-18 17:42:41', 'IK10204532', 1, 'aktif'),
(818, 'özcan', 'özkaya', '05364982608', '', '2024-09-18 18:50:17', 'IK10204533', 1, 'aktif'),
(819, 'Emine', 'SAYMAZ', '05373268360', '', '2024-09-19 10:31:47', 'IK10204380', 1, 'aktif'),
(820, 'nuran', 'istekli', '05364537700', '', '2024-09-19 18:02:00', 'IK10204517', 1, 'aktif'),
(821, 'deniz', 'yıldız', '05056115931', '', '2024-09-19 18:19:24', 'IK10204271', 1, 'aktif'),
(822, 'Serkan', 'ŞEN', '05427425484', '', '2024-09-20 14:34:51', 'IK10204719', 1, 'aktif'),
(823, 'semra', 'büklü', '05439420863', '', '2024-09-20 15:06:57', 'IK10204720', 1, 'aktif'),
(824, 'AYŞE', 'ÖZCAN', '05345508679', '', '2024-09-20 17:22:42', 'IK10204384', 1, 'aktif'),
(825, 'HAYRUNİSA', 'DİNDAR', '05366155211', '', '2024-09-20 18:09:20', 'IK10204383', 1, 'aktif'),
(826, 'KEVSER', 'DİNDAR', '05374048152', '', '2024-09-20 18:21:59', 'IK10204382', 1, 'aktif'),
(827, 'SALİHA SOYSAL', 'DURGUN', '05334861230', '', '2024-09-20 20:26:39', 'IK10203864', 1, 'aktif'),
(828, 'sibel', 'koşan', '05303251852', '', '2024-09-21 14:29:12', 'IK10204381', 1, 'aktif'),
(829, 'tuğçe', 'durdu', '05551834752', '', '2024-09-21 14:42:42', 'IK10204722', 1, 'aktif'),
(830, 'GÜLHAN', 'CİRİK', '05342885873', '', '2024-09-21 15:32:17', 'IK10204170', 1, 'aktif'),
(831, 'furkan', 'oda', '05332960252', '', '2024-09-21 18:26:29', 'IK10204721', 1, 'aktif'),
(832, 'Cavit', 'ORHAN', '05366130760', '', '2024-09-21 20:23:10', 'IK10204385', 1, 'aktif'),
(833, 'Duygu', 'ÇAĞMAN', '05446860052', '', '2024-09-22 13:31:03', 'IK10204172', 1, 'aktif'),
(834, 'Gülhan', 'DIRIK', '05455790052', '', '2024-09-22 15:52:29', 'IK10204386', 1, 'aktif'),
(835, 'Oğuzhan', 'BEKTAŞOĞLU', '05422831235', '', '2024-09-22 16:48:55', 'IK10204387', 1, 'aktif'),
(836, 'Süleyman', 'GALAV', '05452304272', '', '2024-09-22 18:07:18', 'IK10204388', 1, 'aktif'),
(837, 'bayram', 'ışık', '05398924564', '', '2024-09-23 10:52:44', 'IK10204285', 1, 'aktif'),
(838, 'NİMET', 'ÇAKIL', '05455169864', '', '2024-09-23 12:26:03', 'IK10204389', 1, 'aktif'),
(839, 'Fazlı', 'EMİROĞLU', '05327674795', '', '2024-09-23 14:46:39', 'IK10204174', 1, 'aktif'),
(840, 'Birsel', 'TAĞMAK', '05318998252', '', '2024-09-23 14:56:12', 'IK10204390', 1, 'aktif'),
(841, 'lale', 'köse', '05376958856', '', '2024-09-23 16:34:09', 'IK10204173', 1, 'aktif'),
(842, 'TUĞBA', 'KUŞ', '05415787352', '', '2024-09-23 17:20:00', 'IK10204286', 1, 'aktif'),
(843, 'HAMİYE', 'TAŞ', '05449543581', '', '2024-09-24 10:10:23', 'IK10204391', 1, 'aktif'),
(844, 'merve', 'akilik', '05425491959', '', '2024-09-24 15:57:16', 'IK10204287', 1, 'aktif'),
(845, 'MUSTAFA', 'ÖKLÜK', '05444835201', '', '2024-09-24 15:58:50', 'IK10204393', 1, 'aktif'),
(846, 'Meltem', 'GÜR', '05387298935', '', '2024-09-24 18:28:52', 'IK10204288', 1, 'aktif'),
(847, 'Hacer', 'ÇELİK', '05319490026', '', '2024-09-24 19:34:55', 'IK10204291', 1, 'aktif'),
(848, 'ASLI', 'DEĞRİ', '05454177139', '', '2024-09-24 20:40:22', 'IK10204290', 1, 'aktif'),
(849, 'HACER', 'DUYMAZ', '05368270180', '', '2024-09-25 16:49:57', 'IK10204394', 1, 'aktif'),
(850, 'idris', 'güney', '05434917837', '', '2024-09-25 18:28:52', 'IK10204292', 1, 'aktif'),
(851, 'salih', 'burgaz', '05387142261', '', '2024-09-25 18:50:14', 'IK10204293', 1, 'aktif'),
(852, 'Hamide', 'ÇUN', '05418555280', '', '2024-09-25 18:53:28', 'IK10204395', 1, 'aktif'),
(853, 'volkan', 'takı', '05425453052', '', '2024-09-26 10:11:14', 'IK10204294', 1, 'aktif'),
(854, 'Arzu', 'YİĞİT', '05362715673', '', '2024-09-26 17:28:40', 'IK10204296', 1, 'aktif'),
(855, 'ELİF', 'İMZALI', '05306530292', '', '2024-09-26 19:11:47', 'IK10204411', 1, 'aktif'),
(856, 'fatma', 'ecim', '05449724197', '', '2024-09-27 13:06:32', 'IK10204412', 1, 'aktif'),
(857, 'selma', 'çolak', '05413688337', '', '2024-09-27 13:20:27', 'IK10204413', 1, 'aktif'),
(858, 'MEHMET', 'ALAN', '05462694371', '', '2024-09-27 15:17:11', 'IK10204297', 1, 'aktif'),
(859, 'ZÜLEYHA', 'ÜZÜM', '05412092954', '', '2024-09-28 14:18:01', 'IK10204414', 1, 'aktif'),
(860, 'HÜLYA', 'DURAN SERTLİK', '05416501206', '', '2024-09-28 14:18:55', 'IK10204415', 1, 'aktif'),
(861, 'şeref', 'işkil', '05426862958', '', '2024-09-28 17:41:36', 'IK10204298', 1, 'aktif'),
(862, 'ZEHRA', 'SİLAHCIOĞLU', '05377783178', '', '2024-09-30 18:10:14', 'IK10204299', 1, 'aktif'),
(863, 'BAŞAK', 'SIVAZ', '05428929265', '', '2024-09-30 18:12:10', 'IK10204416', 1, 'aktif'),
(864, 'nejla', 'aydın', '05058322896', '', '2024-09-30 18:47:56', 'IK10204417', 1, 'aktif'),
(865, 'İBRAHİM', 'İNCİ', '05313162709', '', '2024-10-01 15:38:27', 'IK10204418', 1, 'aktif'),
(866, 'BETÜL', 'KAYALIK ŞAHİN', '05064732082', '', '2024-10-02 12:25:16', 'IK10204419', 1, 'aktif'),
(867, 'tarım kredi', 'kooperatifi', '05057956210', '', '2024-10-02 17:13:54', 'IK10204420', 1, 'aktif'),
(868, 'hadiye', 'erken', '05307041929', '', '2024-10-07 12:44:45', 'IK10204421', 0, 'aktif'),
(869, 'İsmail', 'SEZGİ', '05413826405', '', '2024-10-07 19:27:23', 'IK10204424', 0, 'aktif'),
(870, 'Esra', 'ÇOPUR', '05357996411', '', '2024-10-08 17:58:00', 'IK10204175', 0, 'aktif'),
(871, 'FİKRİYE', 'ÇELEBİ', '05326761158', '', '2024-10-09 14:07:38', 'IK10204176', 0, 'aktif'),
(872, 'SELMA', 'GÖZÜDİK', '05464311174', '', '2024-10-09 16:21:05', 'IK10204177', 0, 'aktif'),
(873, 'Figen', 'BÖLEN', '05424099163', '', '2024-10-09 16:31:41', 'IK10204178', 0, 'aktif'),
(874, 'Havva', 'KIR USLU', '05362050774', '', '2024-10-09 16:57:27', 'IK10204179', 0, 'aktif'),
(875, 'ayten', 'dindar', '05464900074', '', '2024-10-10 08:46:38', 'IK10204180', 0, 'aktif'),
(876, 'sacide', 'sarıtaş', '05384365926', '', '2024-10-10 13:54:35', 'IK10204181', 0, 'aktif'),
(877, 'serpil', 'armutlu', '05388415201', '', '2024-10-10 13:56:45', 'IK10204182', 0, 'aktif'),
(878, 'ümran', 'abuçka', '05308408176', '', '2024-10-10 16:15:44', 'IK10204183', 0, 'aktif'),
(879, 'merve', 'akınca', '05535788335', '', '2024-10-10 17:26:14', 'IK10204185', 0, 'aktif'),
(880, 'Nesibe', 'TOP', '05455258652', '', '2024-10-10 17:35:03', 'IK10204184', 0, 'aktif'),
(881, 'ruhuyye', 'uyar', '05422699366', '', '2024-10-14 09:54:52', 'IK10204186', 0, 'aktif'),
(882, 'Nesrin', 'PALA', '05053339782', '', '2024-10-23 15:43:36', 'IK10204188', 0, 'aktif'),
(883, 'REYHAN', 'DOLUOĞLU', '05379771727', '', '2024-10-26 16:10:48', 'IK10204191', 0, 'aktif'),
(884, 'semine', 'Güvenç', '05360515259', '', '2024-10-28 16:14:58', 'IK10204192', 0, 'aktif'),
(885, 'HABİBE', 'KATI', '05446512492', '', '2024-10-28 19:36:16', 'IK10204189', 0, 'aktif'),
(886, 'nazmiye', 'yuvarlak', '05385054310', '', '2024-10-29 15:30:26', 'IK10204193', 0, 'aktif'),
(887, 'SEMRA ', 'ÇİMİÇ', '05302276463', '', '2024-10-29 17:29:11', 'IK10204194', 0, 'aktif'),
(888, 'ÖZLEM ', 'ÖKSÜZ', '05315114962', '', '2024-10-30 15:03:24', 'IK10204195', 0, 'aktif'),
(889, 'halise ', 'kesin', '05319424440', '', '2024-10-31 18:09:14', 'IK10204196', 0, 'aktif'),
(890, 'Fatih', 'AÇKI', '05077508161', '', '2024-10-31 19:43:59', 'IK10204197', 0, 'aktif'),
(891, 'eslemsu', 'güney', '05056442052', '', '2024-11-02 15:38:57', 'IK10204198', 0, 'aktif'),
(892, 'yunus', 'Süğümlü', '05438299201', '', '2024-11-05 18:17:36', 'IK10204199', 0, 'aktif'),
(893, 'AYŞE', 'UÇAR', '05455852878', '', '2024-11-06 12:51:45', 'IK10204200', 0, 'aktif'),
(894, 'mücahit', 'kışı', '05435297110', '', '2024-11-06 17:06:38', 'IK10204201', 0, 'aktif'),
(895, 'Hanife', 'KARŞIT', '05544610043', '', '2024-11-09 19:20:44', 'IK10204202', 0, 'aktif'),
(896, 'Emine', 'SÜZEN', '05369775550', '', '2024-11-09 19:27:54', 'IK10204203', 0, 'aktif'),
(897, 'aslan ', 'yardibi', '05435841661', '', '2024-11-11 14:57:48', '1', 0, 'aktif'),
(898, 'PINAR', 'ÖZER', '05439379648', '', '2024-11-11 15:11:58', 'IK10204205', 0, 'aktif'),
(899, 'zeynep ', 'AKMAN', '05446476967', '', '2024-11-12 12:34:16', 'IK10204206', 0, 'aktif'),
(900, 'SEVDA', 'KÖKEN', '05312772068', '', '2024-11-14 17:39:41', 'IK10204207', 0, 'aktif'),
(901, 'alya', 'TANRIVERDİ', '05557660942', '', '2024-11-20 17:01:55', 'IK10204208', 0, 'aktif'),
(902, 'ümran ', 'YEREBASMAZ', '05386948598', '', '2024-11-22 08:46:25', 'IK10204210', 0, 'aktif'),
(903, 'hamide', 'alkan', '05422585774', '', '2024-11-22 08:54:37', 'IK10204209', 0, 'aktif'),
(904, 'DİLEK', 'GEDEK', '05453883591', '', '2024-11-22 15:54:15', 'IK10204211', 0, 'aktif'),
(905, 'Özlem', 'İNAN', '05070754930', '', '2024-11-23 13:20:10', 'IK10204212', 0, 'aktif'),
(906, 'abdul kadir', 'yiğit', '05414232133', '', '2024-11-26 15:43:00', 'IK10204213', 0, 'aktif'),
(907, 'ZEHRA', 'İŞLEME', '05307973352', '', '2024-11-27 17:33:15', 'IK10204214', 0, 'aktif'),
(908, 'gökhan', 'şahan', '05417807652', '', '2024-11-30 10:55:28', 'IK10204215', 0, 'aktif'),
(909, 'MELTEM', 'YEŞİLYURT', '05336482552', '', '2024-12-03 16:12:47', 'IK10204216', 0, 'aktif'),
(910, 'tuğba', 'burma', '05435607770', '', '2024-12-04 16:07:37', 'IK10204218', 0, 'aktif'),
(911, 'dudugül', 'gedik', '05459790166', '', '2024-12-05 12:35:36', 'IK10204219', 0, 'aktif'),
(912, 'EBEBEK', '.', '05324663202', '', '2024-12-07 15:03:09', 'IK10204300', 0, 'aktif'),
(913, 'umut', 'kesim', '05434237829', '', '2024-12-16 18:20:08', 'IK10204301', 0, 'aktif'),
(914, 'sadettin', 'akdeniz', '05053995061', '', '2024-12-17 17:27:09', 'IK10204302', 0, 'aktif'),
(915, 'gülcan', 'aydın', '05436512808', '', '2024-12-17 17:42:24', 'IK10204304', 0, 'aktif'),
(916, 'havva ', 'çekik', '05444341902', '', '2024-12-23 12:57:15', 'IK10204307', 0, 'aktif'),
(917, 'ESRA', 'ÇOŞKUN BOZLAK', '05414288590', '', '2024-12-23 18:48:32', 'IK10204305', 0, 'aktif'),
(918, 'SELİN', 'ACAR', '05444350135', '', '2024-12-24 13:39:21', 'IK10204303', 0, 'aktif'),
(919, 'ŞEBNEM', 'HASANKADIOĞLU', '05453027932', '', '2024-12-24 13:40:19', 'IK10204306', 0, 'aktif'),
(920, 'FATMA', 'ŞAHİN', '05393227246', '', '2024-12-25 17:35:45', 'IK10204309', 0, 'aktif'),
(921, 'BÜŞRA ', 'ÇAKIR', '05375150386', '', '2024-12-25 17:39:46', 'IK10204308', 0, 'aktif'),
(922, 'EMRE', 'ERBİL', '05534784187', '', '2024-12-29 17:10:47', 'IK10204314', 0, 'aktif'),
(923, 'AHMET ', 'ÖZDEN', '05365937280', '', '2024-12-29 17:46:31', 'IK10204313', 0, 'aktif'),
(924, 'TUĞBA ', 'SERİ', '05304253222', '', '2024-12-30 19:25:31', 'IK10204312', 0, 'aktif'),
(925, 'ZÜBEYDE', 'gülmez', '05445794400', '', '2025-01-01 15:45:58', 'IK10204315', 0, 'aktif'),
(926, 'ZEYNEP RANA', 'ERGÜN', '05426133935', '', '2025-01-03 18:05:34', 'IK10204317', 0, 'aktif'),
(927, 'MUHAMMET ALİ', 'polat', '05313795358', '', '2025-01-08 18:26:59', 'IK10204318', 0, 'aktif'),
(928, 'SERDAR', 'KIŞI', '05300431352', '', '2025-01-11 11:08:41', 'IK10204319', 0, 'aktif'),
(929, 'MUHAMMET MİRZA', 'SOĞUKSU', '05330355200', '', '2025-01-11 14:45:21', 'IK10204323', 0, 'aktif'),
(930, 'yusuf ', 'koç', '05516791183', '', '2025-01-11 16:29:40', 'IK10204321', 0, 'aktif'),
(931, 'GÜLCAN', 'HINCIL', '05318711921', '', '2025-01-12 13:53:39', 'IK10204322', 0, 'aktif'),
(10204320, 'kayip', 'kayip', '', NULL, NULL, 'IK10204320', 0, 'aktif'),
(10204321, 'RANA', 'DURAN', '05075869946', '', '2025-01-18 16:13:09', 'IK10204324', 0, 'aktif'),
(10204322, 'EMİNE', 'UYGUN', '05550875787', '', '2025-01-18 18:36:03', 'IK10204272', 0, 'aktif'),
(10204323, 'SARP', 'ÖĞRENMİŞ', '05438980550', '', '2025-01-21 17:41:38', 'IK10204273', 0, 'aktif'),
(10204324, 'Deniz', 'KÖK', '05307338959', '', '2025-01-24 12:24:23', 'IK10204062', 0, 'aktif'),
(10204325, 'ZEYNEP', 'DUDU', '05394854372', '', '2025-01-25 11:54:41', 'IK10204061', 0, 'aktif'),
(10204326, 'HALE', 'DİRHEMSİZ', '05468521615', '', '2025-01-27 13:48:14', 'IK10204060', 0, 'aktif'),
(10204327, 'nurten ', 'demir', '05418648252', '', '2025-01-27 14:00:51', 'IK10204129', 0, 'aktif'),
(10204328, 'ümmühan', 'kuyumcu', '05346808208', '', '2025-01-27 14:06:17', 'IK10204128', 0, 'aktif'),
(10204329, 'fatma', 'baka', '05558971448', '', '2025-01-28 17:41:22', 'IK10204127', 0, 'aktif'),
(10204330, 'engin', 'erişmiş', '05394160507', '', '2025-01-29 13:27:51', 'IK10204126', 0, 'aktif'),
(10204331, 'hatice', 'güleryüz', '05435769463', '', '2025-01-31 13:41:59', 'IK10204125', 0, 'aktif'),
(10204332, 'EMİNE', 'TÜREDİ', '05414135391', '', '2025-01-31 15:17:23', 'IK10204124', 0, 'aktif'),
(10204333, 'HAVVA', 'İLMEK', '05369589774', '', '2025-01-31 18:37:12', 'IK10204123', 0, 'aktif'),
(10204334, 'eyyüp', 'özçelik', '05538395261', '', '2025-02-01 17:44:29', 'IK10204122', 0, 'aktif'),
(10204335, 'İSA', 'KARAN', '05446359969', '', '2025-02-02 13:00:30', 'IK10204121', 0, 'aktif'),
(10204336, 'SALİH', 'AKSOY', '05416459284', '', '2025-02-02 16:12:27', 'IK10204119', 0, 'aktif'),
(10204337, 'FATMA', 'ŞENGÜL', '05438354706', '', '2025-02-02 16:14:24', 'IK10204120', 0, 'aktif'),
(10204338, 'SİBEL', 'ŞAL', '05378201245', '', '2025-02-02 17:23:09', 'IK10204118', 0, 'aktif'),
(10204339, 'NURİYE', 'ERTÜRKMEN', '05421201852', '', '2025-02-02 19:29:25', 'IK10204116', 0, 'aktif'),
(10204340, 'İSA', 'SOYSAL', '05416161230', '', '2025-02-03 18:31:39', 'IK10204117', 0, 'aktif'),
(10204341, 'ADEM', 'TERZİ', '05399858714', '', '2025-02-03 19:37:05', 'IK10204115', 0, 'aktif'),
(10204342, 'SEHER', 'ÇITLAK', '05439553709', '', '2025-02-03 19:46:00', 'IK10204112', 0, 'aktif'),
(10204343, 'şaduman', 'bulacak keskin', '05442945255', '', '2025-02-04 17:15:45', 'IK10204113', 0, 'aktif'),
(10204344, 'ayfer', 'bebek', '05301587067', '', '2025-02-04 17:30:46', 'IK10204114', 0, 'aktif'),
(10204345, 'samet', 'akbulut', '05416375552', '', '2025-02-04 17:34:14', 'IK10204111', 0, 'aktif'),
(10204346, 'Önder', 'KARACA', '05413065252', '', '2025-02-04 19:24:33', 'IK10204110', 0, 'aktif'),
(10204348, 'inci', 'yıldız', '05427465352', '', '2025-02-05 14:51:36', 'IK10204279', 0, 'aktif'),
(10204349, 'burcu', 'topal coşkun', '05388448527', '', '2025-02-05 15:37:57', 'IK10204278', 0, 'aktif'),
(10204350, 'elif nisa', 'sarıhan', '05394415052', '', '2025-02-05 16:21:10', 'IK10204277', 0, 'aktif'),
(10204351, 'NEVİN', 'TALO', '05345240212', '', '2025-02-05 17:46:58', 'IK10204276', 0, 'aktif'),
(10204352, 'NESLİHAN', 'ÇİVİCİ', '05462553368', '', '2025-02-05 19:05:46', 'IK10204275', 0, 'aktif'),
(10204353, 'İDRİS', 'ÇAĞRIN', '05374538869', '', '2025-02-05 19:09:35', 'IK10204274', 0, 'aktif'),
(10204354, 'GÖKHAN', 'KÜÇÜK', '05442631820', '', '2025-02-05 19:34:38', 'IK10204740', 0, 'aktif'),
(10204355, 'EMEL', 'VAROL', '05464432235', '', '2025-02-06 15:55:02', 'IK10204741', 0, 'aktif'),
(10204356, 'adil', 'usanç', '05343317086', '', '2025-02-06 17:39:49', 'IK10204742', 0, 'aktif'),
(10204357, 'cemal', 'düşün', '05427718238', '', '2025-02-06 17:43:57', 'IK10204743', 0, 'aktif'),
(10204358, 'CEMALİYE', 'KAYA', '05077890033', '', '2025-02-09 13:12:44', 'IK10204629', 0, 'aktif'),
(10204359, 'FATİH', 'BİLGİN', '05530529052', '', '2025-02-09 15:19:05', 'IK10204630', 0, 'aktif'),
(10204360, 'RECEP', 'KUEU', '05427939024', '', '2025-02-09 19:34:17', 'IK10204631', 0, 'aktif'),
(10204361, 'HASAN BASRİ', 'ÇALIŞKANCI', '05427980020', '', '2025-02-10 15:04:07', 'IK10204632', 0, 'aktif'),
(10204362, 'ALPER', 'MEMİŞ', '05426251112', '', '2025-02-10 15:14:22', 'IK10204633', 0, 'aktif'),
(10204363, 'Şulenur', 'KARA', '05373736996', '', '2025-02-10 16:23:14', 'IK10204634', 0, 'aktif'),
(10204364, 'özkan', 'TÜRKAN', '05434915552', '', '2025-02-11 16:36:05', 'IK10204245', 0, 'aktif'),
(10204365, 'MERVE', 'AYVAZOĞLU', '05439194353', '', '2025-02-12 13:49:09', 'IK10204246', 0, 'aktif'),
(10204366, 'ÜMRAN', 'ŞAHİN', '05464522152', '', '2025-02-12 16:30:46', 'IK10204247', 0, 'aktif'),
(10204367, 'CEMİLE', 'ANAÇ', '05535364224', '', '2025-02-12 17:56:40', 'IK10204248', 0, 'aktif'),
(10204368, 'İLKER ', 'YILMAZ', '05462667252', '', '2025-02-12 18:01:11', 'IK10204249', 0, 'aktif'),
(10204369, 'SEDA', 'KIR', '05370333234', '', '2025-02-14 15:19:20', 'IK10204649', 0, 'aktif'),
(10204370, 'SEDA ', 'SATAN', '05383069623', '', '2025-02-15 13:20:38', 'IK10204647', 0, 'aktif'),
(10204371, 'RANA', 'KARAKÜTÜK', '05525520910', '', '2025-02-15 13:24:42', 'IK10204648', 0, 'aktif'),
(10204372, 'EMİNE', 'AYDIN', '05059145321', '', '2025-02-16 14:31:27', 'IK10204646', 0, 'aktif'),
(10204373, 'GÜLSÜM', 'KEBABÇI', '05454625419', '', '2025-02-16 16:02:42', 'IK10204659', 0, 'aktif'),
(10204374, 'YASİN', 'GAYDAN', '05386558946', '', '2025-02-16 16:35:30', 'IK10204657', 0, 'aktif'),
(10204375, 'ADEM ', 'ÖZGÜR', '05356482824', '', '2025-02-16 18:30:43', 'IK10204658', 0, 'aktif'),
(10204376, 'hatice', 'yağız', '05358446996', '', '2025-02-17 17:04:20', 'IK10204645', 0, 'aktif'),
(10204377, 'sinem', 'karabayrak', '05359531549', '', '2025-02-18 15:53:42', 'IK10204644', 0, 'aktif'),
(10204378, 'TEVRAT', 'GÜLEÇ', '05353727839', '', '2025-02-20 17:55:57', 'IK10204655', 0, 'aktif'),
(10204379, 'TOPRAK', 'KILIÇ', '05434475552', '', '2025-02-20 18:01:36', 'IK10204654', 0, 'aktif'),
(10204380, 'serap', 'garip', '05516417009', '', '2025-02-24 13:53:31', 'IK10204642', 0, 'aktif'),
(10204381, 'ecrin naz', 'cezan', '05057843208', '', '2025-02-24 13:55:31', 'IK10204641', 0, 'aktif'),
(10204382, 'hava', 'emre', '05386107218', '', '2025-02-24 13:57:51', 'IK10204643', 0, 'aktif'),
(10204383, 'SERKAN', 'BATIR', '05449502761', '', '2025-02-25 20:15:39', 'IK10204640', 0, 'aktif'),
(10204384, 'manara', 'çimiç', '05312680752', '', '2025-02-27 08:59:02', 'IK10204639', 0, 'aktif'),
(10204385, 'esra', 'toklucuoğlu', '05363795129', '', '2025-02-28 15:06:05', 'IK10204638', 0, 'aktif');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `musteri_borclar`
--

CREATE TABLE `musteri_borclar` (
  `borc_id` int(11) NOT NULL,
  `musteri_id` int(11) NOT NULL,
  `toplam_tutar` decimal(10,2) NOT NULL,
  `indirim_tutari` decimal(10,2) DEFAULT 0.00,
  `borc_tarihi` date NOT NULL,
  `fis_no` varchar(20) DEFAULT NULL,
  `odendi_mi` tinyint(1) DEFAULT 0,
  `olusturma_tarihi` timestamp NOT NULL DEFAULT current_timestamp(),
  `magaza_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

--
-- Tablo döküm verisi `musteri_borclar`
--

INSERT INTO `musteri_borclar` (`borc_id`, `musteri_id`, `toplam_tutar`, `indirim_tutari`, `borc_tarihi`, `fis_no`, `odendi_mi`, `olusturma_tarihi`, `magaza_id`) VALUES
(1, 10204385, 300.00, 0.00, '2025-03-02', NULL, 0, '2025-03-02 09:43:56', NULL),
(2, 147, 123.00, 0.00, '2025-03-05', '250305-9057', 1, '2025-03-05 08:51:22', 7),
(3, 147, 2450.00, 10.00, '2025-03-05', '', 0, '2025-03-05 09:21:38', 6),
(4, 147, 2450.00, 10.00, '2025-03-05', '', 0, '2025-03-05 09:21:44', 6),
(5, 147, 2450.00, 10.00, '2025-03-05', '', 0, '2025-03-05 09:21:47', 6),
(6, 147, 2450.00, 10.00, '2025-03-05', '', 0, '2025-03-05 09:22:14', 6),
(7, 147, 2450.00, 10.00, '2025-03-05', '', 0, '2025-03-05 09:22:20', 6),
(8, 147, 2450.00, 10.00, '2025-03-05', '', 1, '2025-03-05 09:22:28', 6),
(9, 147, 62.50, 10.00, '2025-03-05', '', 0, '2025-03-05 09:22:41', 6),
(10, 147, 85.00, 10.00, '2025-03-05', '', 1, '2025-03-05 09:26:17', 7),
(11, 147, 85.00, 10.00, '2025-03-05', '', 0, '2025-03-05 09:27:06', 7),
(12, 147, 85.00, 5.00, '2025-03-05', '', 1, '2025-03-05 09:28:40', 7),
(13, 147, 85.00, 5.00, '2025-03-05', '', 0, '2025-03-05 09:28:44', 7),
(14, 147, 245.00, 5.00, '2025-03-05', '', 0, '2025-03-05 09:36:34', 7),
(15, 147, 365.00, 15.00, '2025-03-06', '', 0, '2025-03-06 21:03:19', 7),
(16, 147, 187.50, 5.00, '2025-03-06', '', 0, '2025-03-06 21:12:18', 7),
(17, 147, 245.00, 45.00, '2025-03-06', '', 0, '2025-03-06 21:16:30', 7),
(18, 147, 0.50, 0.00, '2025-03-08', '250307-3522', 0, '2025-03-08 08:55:29', 7),
(19, 147, 0.50, 0.00, '2025-03-08', '250307-3522', 0, '2025-03-08 08:59:17', 7),
(20, 147, 0.50, 0.00, '2025-03-08', '250307-3522', 0, '2025-03-08 09:03:18', 7),
(21, 617, 245.00, 0.00, '2025-03-08', '', 0, '2025-03-08 11:33:35', 7);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `musteri_borc_detaylar`
--

CREATE TABLE `musteri_borc_detaylar` (
  `detay_id` int(11) NOT NULL,
  `borc_id` int(11) NOT NULL,
  `urun_adi` varchar(100) NOT NULL,
  `miktar` int(11) NOT NULL,
  `tutar` decimal(10,2) NOT NULL,
  `urun_id` int(11) DEFAULT NULL,
  `olusturma_tarihi` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

--
-- Tablo döküm verisi `musteri_borc_detaylar`
--

INSERT INTO `musteri_borc_detaylar` (`detay_id`, `borc_id`, `urun_adi`, `miktar`, `tutar`, `urun_id`, `olusturma_tarihi`) VALUES
(1, 1, 'Kalem', 2, 50.00, NULL, '2025-03-02 09:43:56'),
(2, 1, 'Silgi', 10, 250.00, NULL, '2025-03-02 09:43:56'),
(3, 2, '123', 1, 123.00, 1111, '2025-03-05 08:51:22'),
(4, 3, 'Faber Castell 9000 Dereceli Kalem Seti', 10, 0.00, NULL, '2025-03-05 09:21:38'),
(5, 4, 'Faber Castell 9000 Dereceli Kalem Seti', 10, 0.00, NULL, '2025-03-05 09:21:44'),
(6, 5, 'Faber Castell 9000 Dereceli Kalem Seti', 10, 0.00, NULL, '2025-03-05 09:21:47'),
(7, 6, 'Faber Castell 9000 Dereceli Kalem Seti', 10, 0.00, NULL, '2025-03-05 09:22:14'),
(8, 7, 'Faber Castell 9000 Dereceli Kalem Seti', 10, 0.00, NULL, '2025-03-05 09:22:20'),
(9, 8, 'Faber Castell 9000 Dereceli Kalem Seti', 10, 0.00, NULL, '2025-03-05 09:22:28'),
(10, 9, 'Faber Castell Kurşun Kalem 2B', 5, 0.00, NULL, '2025-03-05 09:22:41'),
(11, 10, 'Faber Castell Grip 2011 Versatil Kalem', 1, 0.00, NULL, '2025-03-05 09:26:17'),
(12, 11, 'Faber Castell Grip 2011 Versatil Kalem', 1, 0.00, NULL, '2025-03-05 09:27:06'),
(13, 12, 'Faber Castell Grip 2011 Versatil Kalem', 1, 0.00, NULL, '2025-03-05 09:28:40'),
(14, 13, 'Faber Castell Grip 2011 Versatil Kalem', 1, 0.00, NULL, '2025-03-05 09:28:44'),
(15, 14, 'Faber Castell 9000 Dereceli Kalem Seti', 1, 245.00, 1069, '2025-03-05 09:36:34'),
(16, 15, 'Faber Castell Grip 2011 Versatil Kalem', 1, 85.00, 1066, '2025-03-06 21:03:19'),
(17, 15, 'Faber Castell Boya Kalemi 36lı Set', 1, 280.00, 1074, '2025-03-06 21:03:19'),
(18, 16, 'Faber Castell Kurşun Kalem 2B', 15, 187.50, 1059, '2025-03-06 21:12:18'),
(19, 17, 'Faber Castell 9000 Dereceli Kalem Seti', 1, 245.00, 1069, '2025-03-06 21:16:30'),
(20, 18, 'Sipariş #250307-3522 - Borç olarak aktarıldı', 1, 0.50, NULL, '2025-03-08 08:55:29'),
(21, 19, 'Sipariş #250307-3522 - Borç olarak aktarıldı', 1, 0.50, NULL, '2025-03-08 08:59:17'),
(22, 20, 'Sipariş #250307-3522 - Borç olarak aktarıldı', 1, 0.50, NULL, '2025-03-08 09:03:18'),
(23, 21, 'Faber Castell 9000 Dereceli Kalem Seti', 1, 245.00, 1069, '2025-03-08 11:33:35');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `musteri_borc_odemeler`
--

CREATE TABLE `musteri_borc_odemeler` (
  `odeme_id` int(11) NOT NULL,
  `borc_id` int(11) NOT NULL,
  `odeme_tutari` decimal(10,2) NOT NULL,
  `odeme_tarihi` date NOT NULL,
  `odeme_yontemi` enum('nakit','kredi_karti','havale') DEFAULT 'nakit',
  `aciklama` varchar(255) DEFAULT NULL,
  `kullanici_id` int(11) DEFAULT NULL,
  `olusturma_tarihi` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

--
-- Tablo döküm verisi `musteri_borc_odemeler`
--

INSERT INTO `musteri_borc_odemeler` (`odeme_id`, `borc_id`, `odeme_tutari`, `odeme_tarihi`, `odeme_yontemi`, `aciklama`, `kullanici_id`, `olusturma_tarihi`) VALUES
(1, 1, 150.00, '2025-03-02', 'nakit', NULL, 1, '2025-03-02 09:49:04'),
(2, 2, 123.00, '2025-03-05', 'nakit', '', 1, '2025-03-05 08:51:51'),
(3, 12, 40.00, '2025-03-05', 'nakit', '', 1, '2025-03-05 09:35:10'),
(4, 3, 2440.00, '2025-03-08', 'nakit', '', 1, '2025-03-08 09:19:58'),
(5, 9, 52.50, '2025-03-08', 'nakit', '', 1, '2025-03-08 09:20:28'),
(6, 10, 75.00, '2025-03-08', 'nakit', '', 1, '2025-03-08 09:23:19'),
(7, 8, 2440.00, '2025-03-08', 'nakit', '', 1, '2025-03-08 09:23:40'),
(8, 12, 40.00, '2025-03-08', 'nakit', '', 1, '2025-03-08 11:32:49'),
(9, 21, 5.00, '2025-03-08', 'nakit', '', 1, '2025-03-08 11:33:49');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `musteri_puanlar`
--

CREATE TABLE `musteri_puanlar` (
  `id` int(11) NOT NULL,
  `musteri_id` int(11) NOT NULL,
  `puan_bakiye` decimal(10,2) DEFAULT 0.00,
  `puan_oran` decimal(5,2) DEFAULT NULL COMMENT 'TL başına kazanılan % puan',
  `son_alisveris_tarihi` datetime DEFAULT NULL,
  `musteri_turu` enum('standart','gold','platinum') DEFAULT 'standart',
  `barcode` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

--
-- Tablo döküm verisi `musteri_puanlar`
--

INSERT INTO `musteri_puanlar` (`id`, `musteri_id`, `puan_bakiye`, `puan_oran`, `son_alisveris_tarihi`, `musteri_turu`, `barcode`) VALUES
(30, 48, 0.00, 5.00, NULL, 'standart', 'IK10203887'),
(32, 50, 199.50, 5.00, '2024-10-14 10:17:06', 'standart', 'IK10203470'),
(33, 51, 0.00, 5.00, NULL, 'standart', 'IK10203943'),
(34, 52, 51.25, 5.00, '2025-02-08 17:02:35', 'standart', 'IK10203313'),
(35, 53, 0.00, 5.00, NULL, 'standart', 'IK10203206'),
(36, 54, 1184.55, 5.00, '2025-02-28 15:59:30', 'standart', 'IK10203875'),
(37, 55, 40.75, 5.00, '2024-12-30 18:46:57', 'standart', 'IK10203126'),
(40, 58, 29.75, 5.00, '2025-01-24 15:27:55', 'standart', 'IK10203400'),
(41, 59, 0.00, 5.00, NULL, 'standart', 'IK10203301'),
(42, 60, 0.00, 5.00, NULL, 'standart', 'IK10203621'),
(43, 61, 15.50, 5.00, '2025-02-08 14:38:02', 'standart', 'IK10203367'),
(44, 62, 453.40, 5.00, '2025-02-17 19:19:19', 'standart', 'IK10203838'),
(45, 63, 0.00, 5.00, NULL, 'standart', 'IK10203856'),
(46, 64, 5.50, 5.00, '2024-11-04 18:46:11', 'standart', 'IK10203319'),
(47, 65, 0.00, 5.00, NULL, 'standart', 'IK10203642'),
(48, 66, 522.00, 5.00, '2024-09-07 15:29:49', 'gold', 'IK10203431'),
(49, 67, 2.70, 5.00, '2024-09-05 14:46:40', 'standart', 'IK10203243'),
(50, 68, 0.00, 5.00, NULL, 'standart', 'IK10203888'),
(51, 69, 61.00, 5.00, '2024-09-10 19:59:12', 'standart', 'IK10203152'),
(52, 70, 50.75, 5.00, '2025-01-27 13:05:47', 'standart', 'IK10203990'),
(53, 71, 17.00, 5.00, '2024-09-17 13:50:28', 'standart', 'IK10203745'),
(54, 72, 0.00, 5.00, NULL, 'standart', 'IK10203332'),
(55, 73, 228.30, 5.00, '2024-12-21 12:45:14', 'standart', 'IK10203865'),
(56, 74, 0.00, 5.00, NULL, 'standart', 'IK10203870'),
(57, 75, 13.38, 5.00, '2025-01-22 18:43:38', 'standart', 'IK10203923'),
(58, 76, 19.83, 5.00, '2024-08-05 15:18:00', 'standart', 'IK10203174'),
(59, 77, 236.50, 5.00, '2024-09-23 14:09:06', 'standart', 'IK10203512'),
(60, 78, 0.00, 5.00, NULL, 'standart', 'IK10203581'),
(61, 79, 0.00, 5.00, NULL, 'gold', 'IK10203379'),
(62, 80, 0.70, 5.00, '2024-09-05 20:20:03', 'gold', 'IK10203819'),
(63, 81, 0.00, 5.00, NULL, 'standart', 'IK10203151'),
(64, 82, 15.90, 5.00, '2025-02-13 17:17:43', 'gold', 'IK10203718'),
(65, 83, 74.00, 5.00, '2024-08-09 08:58:44', 'standart', 'IK10203211'),
(66, 84, 30.73, 5.00, '2025-02-24 18:33:36', 'standart', 'IK10203578'),
(67, 85, 310.25, 5.00, '2024-11-04 10:48:46', 'standart', 'IK10203130'),
(68, 86, 0.00, 5.00, NULL, 'standart', 'IK10203030'),
(69, 87, 0.00, 5.00, NULL, 'standart', 'IK10203700'),
(70, 88, 48.00, 5.00, '2024-09-08 12:39:56', 'standart', 'IK10203720'),
(71, 89, 33.00, 5.00, '2025-02-07 15:24:23', 'standart', 'IK10203672'),
(72, 90, 0.00, 5.00, NULL, 'standart', 'IK10203397'),
(73, 91, 1.75, 5.00, '2024-12-17 15:43:32', 'standart', 'IK10203237'),
(74, 92, 33.00, 5.00, '2024-09-09 22:29:16', 'standart', 'IK10203537'),
(75, 93, 241.50, 5.00, '2024-09-09 15:42:52', 'standart', 'IK10203403'),
(76, 94, 27.50, 5.00, '2024-08-12 20:04:57', 'standart', 'IK10203914'),
(77, 95, 295.20, 5.00, '2025-02-05 15:21:46', 'standart', 'IK10203077'),
(78, 96, 0.00, 5.00, NULL, 'standart', 'IK10203684'),
(79, 97, 33.75, 5.00, '2024-12-05 18:49:11', 'standart', 'IK10203746'),
(80, 98, 0.00, 5.00, NULL, 'standart', 'IK10203935'),
(81, 99, 18.50, 5.00, '2025-02-27 15:52:06', 'standart', 'IK10203240'),
(82, 100, 58.25, 5.00, '2025-02-02 15:23:57', 'standart', 'IK10203962'),
(83, 101, 177.80, 5.00, '2025-02-07 08:56:11', 'standart', 'IK10203037'),
(84, 102, 42.50, 5.00, '2024-09-12 12:01:29', 'standart', 'IK10203658'),
(85, 103, 30.40, 5.00, '2024-12-17 17:52:26', 'standart', 'IK10203936'),
(86, 104, 11.00, 5.00, '2024-08-16 17:29:50', 'standart', 'IK10203657'),
(87, 105, 0.00, 5.00, NULL, 'standart', 'IK10203540'),
(88, 106, 0.50, 5.00, '2024-12-04 17:38:20', 'standart', 'IK10203339'),
(89, 107, 73.85, 5.00, '2024-09-18 17:58:15', 'standart', 'IK10203563'),
(90, 108, 140.00, 5.00, '2024-09-03 16:11:05', 'standart', 'IK10203791'),
(91, 109, 133.00, 5.00, '2024-09-03 19:31:39', 'standart', 'IK10203919'),
(92, 110, 0.00, 5.00, NULL, 'standart', 'IK10203011'),
(93, 111, 0.00, 5.00, NULL, 'standart', 'IK10203709'),
(94, 112, 34.70, 5.00, '2024-10-27 18:05:17', 'standart', 'IK10203780'),
(95, 113, 205.00, 5.00, '2024-09-06 11:10:34', 'standart', 'IK10203653'),
(96, 114, 0.00, 5.00, NULL, 'standart', 'IK10203407'),
(97, 115, 41.65, 5.00, '2024-08-19 16:19:22', 'standart', 'IK10203522'),
(98, 116, 0.00, 5.00, NULL, 'standart', 'IK10203377'),
(99, 117, 0.00, 5.00, NULL, 'standart', 'IK10203678'),
(100, 118, 0.00, 5.00, NULL, 'standart', 'IK10203161'),
(101, 119, 0.00, 5.00, NULL, 'standart', 'IK10203415'),
(102, 120, 460.75, 5.00, '2024-12-20 18:51:44', 'standart', 'IK10203321'),
(103, 121, 16.00, 5.00, '2024-08-20 20:06:38', 'standart', 'IK10203452'),
(104, 122, 22.40, 5.00, '2024-08-21 13:08:36', 'standart', 'IK10203270'),
(105, 123, 763.00, 5.00, '2024-08-27 11:41:37', 'standart', 'IK10203024'),
(106, 124, 0.00, 5.00, NULL, 'standart', 'IK10203034'),
(107, 125, 0.00, 5.00, NULL, 'standart', 'IK10203088'),
(108, 126, 200.00, 5.00, '2024-09-07 10:35:27', 'standart', 'IK10203649'),
(109, 127, 0.00, 5.00, NULL, 'standart', 'IK10203219'),
(110, 128, 19.25, 5.00, '2025-01-31 17:47:59', 'standart', 'IK10203475'),
(111, 129, 175.50, 5.00, '2024-09-05 13:18:02', 'standart', 'IK10203247'),
(112, 130, 54.00, 5.00, '2024-08-22 19:48:57', 'standart', 'IK10203763'),
(113, 131, 17.78, 5.00, '2025-02-17 15:37:07', 'standart', 'IK10203599'),
(114, 132, 18.30, 5.00, '2025-02-09 15:39:58', 'standart', 'IK10203569'),
(115, 133, 111.50, 5.00, '2024-09-10 09:28:15', 'standart', 'IK10203813'),
(116, 134, 154.90, 5.00, '2024-09-07 15:01:12', 'standart', 'IK10203823'),
(117, 135, 53.25, 5.00, '2024-11-11 10:06:01', 'standart', 'IK10203166'),
(118, 136, 1.00, 5.00, '2024-10-22 16:26:25', 'standart', 'IK10203694'),
(119, 137, 32.00, 5.00, '2024-09-21 19:35:48', 'standart', 'IK10203974'),
(120, 138, 318.50, 5.00, '2024-11-28 13:09:43', 'standart', 'IK10203489'),
(121, 139, 65.30, 5.00, '2024-10-07 18:49:56', 'standart', 'IK10203849'),
(122, 140, 97.30, 5.00, '2024-08-29 17:38:49', 'standart', 'IK10203418'),
(123, 141, 16.80, 5.00, '2025-01-17 18:42:12', 'standart', 'IK10203539'),
(124, 142, 142.25, 5.00, '2025-02-22 12:36:29', 'standart', 'IK10203048'),
(125, 143, 28.00, 5.00, '2024-08-25 15:51:51', 'standart', 'IK10203343'),
(129, 147, 1508.88, 12.00, '2025-03-09 12:58:51', 'standart', 'IK10203793'),
(130, 148, 0.00, 5.00, '2024-09-15 18:14:52', 'standart', 'IK10203142'),
(131, 149, 8.00, 5.00, '2025-02-18 19:26:42', 'standart', 'IK10203950'),
(132, 150, 26.50, 5.00, '2024-11-29 14:03:31', 'standart', 'IK10203468'),
(133, 151, 43.25, 5.00, '2024-12-29 19:11:05', 'standart', 'IK10203815'),
(134, 152, 75.50, 5.00, '2024-09-18 08:44:30', 'standart', 'IK10203942'),
(135, 153, 5.00, 5.00, '2025-01-03 11:50:49', 'standart', 'IK10203918'),
(136, 154, 0.00, 5.00, NULL, 'standart', 'IK10203175'),
(137, 155, 79.00, 5.00, '2024-08-26 16:03:19', 'standart', 'IK10203036'),
(138, 156, 109.00, 5.00, '2024-09-04 17:28:06', 'standart', 'IK10203357'),
(139, 157, 134.50, 5.00, '2024-09-12 13:43:30', 'standart', 'IK10203768'),
(140, 158, 47.00, 5.00, '2024-09-02 10:49:37', 'standart', 'IK10203587'),
(141, 159, 70.00, 5.00, '2024-09-12 19:46:55', 'standart', 'IK10203157'),
(142, 160, 23.00, 5.00, '2025-02-03 18:07:19', 'standart', 'IK10203127'),
(143, 161, 0.00, 5.00, NULL, 'standart', 'IK10203528'),
(144, 162, 23.50, 5.00, '2024-10-10 14:05:28', 'standart', 'IK10203932'),
(145, 163, 149.20, 5.00, '2024-08-27 20:19:43', 'standart', 'IK10203487'),
(146, 164, 215.50, 5.00, '2024-09-19 19:09:55', 'standart', 'IK10203443'),
(147, 165, 20.40, 5.00, '2024-09-10 12:57:22', 'standart', 'IK10203057'),
(148, 166, 240.00, 5.00, '2024-08-28 12:56:27', 'standart', 'IK10203055'),
(149, 167, 188.00, 5.00, '2024-08-28 14:37:05', 'standart', 'IK10203803'),
(150, 168, 310.00, 5.00, '2024-08-28 19:07:39', 'standart', 'IK10203530'),
(151, 169, 314.00, 5.00, '2024-08-28 14:57:06', 'standart', 'IK10203988'),
(152, 170, 10.00, 5.00, '2024-12-12 08:52:06', 'standart', 'IK10203041'),
(153, 171, 241.50, 5.00, '2024-09-10 13:05:51', 'standart', 'IK10203860'),
(154, 172, 0.00, 5.00, '2024-08-30 10:42:41', 'standart', 'IK10203213'),
(155, 173, 27.50, 5.00, '2024-09-09 20:32:23', 'standart', 'IK10203826'),
(156, 174, 0.00, 5.00, NULL, 'standart', 'IK10203903'),
(157, 175, 11.50, 5.00, '2024-08-30 12:30:35', 'standart', 'IK10203783'),
(158, 176, 8.75, 5.00, '2025-02-25 16:13:16', 'standart', 'IK10203954'),
(159, 177, 335.25, 5.00, '2024-12-17 19:19:19', 'standart', 'IK10203866'),
(160, 178, 10.00, 5.00, '2024-08-30 14:34:08', 'standart', 'IK10203703'),
(161, 179, 5.00, 5.00, '2025-01-15 12:25:26', 'standart', 'IK10203876'),
(162, 180, 279.50, 5.00, '2024-08-30 16:10:15', 'standart', 'IK10203664'),
(163, 181, 0.00, 5.00, NULL, 'standart', 'IK10203261'),
(164, 182, 0.00, 5.00, '2024-09-14 16:54:01', 'standart', 'IK10203201'),
(165, 183, 3.25, 5.00, '2024-12-17 13:17:19', 'standart', 'IK10203033'),
(166, 184, 1.00, 5.00, '2024-09-06 13:26:07', 'standart', 'IK10203460'),
(167, 185, 220.25, 5.00, '2024-10-05 16:39:54', 'standart', 'IK10203503'),
(168, 186, 45.70, 5.00, '2024-08-31 10:07:13', 'standart', 'IK10203681'),
(169, 187, 61.30, 5.00, '2024-09-10 19:49:00', 'standart', 'IK10203735'),
(170, 188, 16.20, 5.00, '2025-02-15 14:06:03', 'standart', 'IK10203833'),
(171, 189, 45.00, 5.00, '2024-08-31 14:37:13', 'standart', 'IK10203877'),
(172, 190, 230.00, 5.00, '2024-08-31 15:05:41', 'standart', 'IK10203538'),
(173, 191, 46.00, 5.00, '2025-02-14 19:07:27', 'standart', 'IK10203589'),
(174, 192, 114.50, 5.00, '2024-08-31 17:50:47', 'standart', 'IK10203519'),
(175, 193, 22.75, 5.00, '2025-01-14 16:08:40', 'standart', 'IK10203608'),
(176, 194, 75.75, 5.00, '2025-02-03 18:38:21', 'standart', 'IK10203786'),
(177, 195, 0.00, 5.00, NULL, 'standart', 'IK10203928'),
(178, 196, 148.00, 5.00, '2024-09-24 19:29:08', 'standart', 'IK10203398'),
(179, 197, 37.00, 5.00, '2025-02-06 18:58:14', 'standart', 'IK10203178'),
(180, 198, 134.00, 5.00, '2024-09-01 12:28:35', 'standart', 'IK10203023'),
(181, 199, 203.00, 5.00, '2024-09-01 12:50:25', 'standart', 'IK10203212'),
(182, 200, 22.50, 5.00, '2024-09-01 12:55:31', 'standart', 'IK10203706'),
(183, 201, 138.00, 5.00, '2024-09-01 13:03:38', 'standart', 'IK10203328'),
(184, 202, 0.00, 5.00, '2024-09-07 19:04:51', 'standart', 'IK10203094'),
(185, 203, 120.00, 5.00, '2024-09-01 13:46:06', 'standart', 'IK10203557'),
(186, 204, 51.50, 5.00, '2025-02-22 13:43:05', 'standart', 'IK10203275'),
(187, 205, 15.00, 5.00, '2024-11-12 18:00:41', 'gold', 'IK10203821'),
(188, 206, 118.80, 5.00, '2024-09-02 19:05:41', 'standart', 'IK10203594'),
(189, 207, 74.75, 5.00, '2025-01-17 16:59:07', 'standart', 'IK10203022'),
(190, 208, 39.00, 5.00, '2024-09-16 12:00:41', 'standart', 'IK10203428'),
(191, 209, 94.75, 5.00, '2025-02-02 16:52:23', 'standart', 'IK10203663'),
(192, 210, 22.50, 5.00, '2024-09-01 16:18:02', 'standart', 'IK10203193'),
(193, 211, 48.25, 5.00, '2025-02-03 16:28:06', 'standart', 'IK10203067'),
(194, 212, 59.00, 5.00, '2024-09-01 17:06:00', 'standart', 'IK10203980'),
(195, 213, 10.15, 5.00, '2024-10-19 10:37:04', 'standart', 'IK10203056'),
(196, 214, 45.25, 5.00, '2025-01-12 14:47:59', 'standart', 'IK10203450'),
(197, 215, 20.00, 5.00, '2024-09-01 18:23:17', 'standart', 'IK10203159'),
(198, 216, 200.00, 5.00, '2024-09-01 19:04:28', 'standart', 'IK10203364'),
(199, 217, 163.25, 5.00, '2024-10-09 08:41:17', 'standart', 'IK10203725'),
(200, 218, 146.60, 5.00, '2025-01-06 19:04:19', 'standart', 'IK10203043'),
(201, 219, 27.50, 5.00, '2024-12-07 16:27:30', 'standart', 'IK10203124'),
(202, 220, 80.90, 5.00, '2024-11-25 09:20:14', 'standart', 'IK10203365'),
(203, 221, 9.45, 5.00, '2025-02-03 14:49:25', 'standart', 'IK10203741'),
(204, 222, 74.25, 5.00, '2024-10-29 10:09:17', 'standart', 'IK10203493'),
(205, 223, 195.00, 5.00, '2024-11-27 11:41:02', 'standart', 'IK10203797'),
(206, 224, 0.00, 5.00, '2024-09-02 11:26:18', 'standart', 'IK10203465'),
(207, 225, 7.25, 5.00, '2025-01-20 12:14:23', 'standart', 'IK10203163'),
(208, 226, 222.00, 5.00, '2024-09-02 11:38:29', 'standart', 'IK10203080'),
(209, 227, 117.00, 5.00, '2024-09-10 12:02:55', 'standart', 'IK10203020'),
(210, 228, 4.50, 5.00, '2024-09-09 20:24:44', 'standart', 'IK10203965'),
(211, 229, 44.65, 5.00, '2024-09-07 17:04:59', 'standart', 'IK10203444'),
(212, 230, 27.50, 5.00, '2024-09-10 16:17:06', 'standart', 'IK10203187'),
(213, 231, 446.00, 5.00, '2024-09-02 14:30:21', 'standart', 'IK10203869'),
(214, 232, 0.00, 5.00, '2024-09-02 14:29:21', 'standart', 'IK10203685'),
(215, 233, 61.00, 5.00, '2024-09-02 14:36:50', 'standart', 'IK10203997'),
(216, 234, 195.50, 5.00, '2024-09-11 13:25:53', 'standart', 'IK10203366'),
(217, 235, 446.00, 5.00, '2024-09-21 18:23:09', 'standart', 'IK10203360'),
(218, 236, 462.50, 5.00, '2025-01-09 18:32:10', 'standart', 'IK10203189'),
(219, 237, 28.50, 5.00, '2024-09-02 15:38:54', 'standart', 'IK10203665'),
(220, 238, 100.00, 5.00, '2024-09-02 15:44:07', 'standart', 'IK10203736'),
(221, 239, 218.50, 5.00, '2024-09-09 18:18:36', 'standart', 'IK10203267'),
(222, 240, 10.00, 5.00, '2024-09-16 17:34:58', 'standart', 'IK10203134'),
(223, 241, 43.80, 5.00, '2024-09-02 16:29:39', 'standart', 'IK10203374'),
(224, 242, 59.00, 5.00, '2024-09-20 18:40:57', 'standart', 'IK10203509'),
(225, 243, 0.00, 5.00, '2024-09-02 17:11:19', 'standart', 'IK10203298'),
(226, 244, 39.50, 5.00, '2024-10-17 14:31:00', 'standart', 'IK10203139'),
(227, 245, 21.50, 5.00, '2024-11-18 08:41:14', 'standart', 'IK10203242'),
(228, 246, 217.00, 5.00, '2024-09-02 17:36:01', 'standart', 'IK10203047'),
(229, 247, 123.00, 5.00, '2024-09-06 17:11:16', 'standart', 'IK10203383'),
(230, 248, 93.60, 5.00, '2024-09-25 20:09:31', 'standart', 'IK10203764'),
(231, 249, 40.53, 5.00, '2025-02-27 18:01:24', 'standart', 'IK10203426'),
(232, 250, 66.00, 5.00, '2024-09-03 18:32:36', 'standart', 'IK10203560'),
(233, 251, 28.25, 5.00, '2024-10-13 13:27:27', 'standart', 'IK10203964'),
(234, 252, 17.00, 5.00, '2025-01-15 16:24:54', 'standart', 'IK10203929'),
(235, 253, 56.05, 5.00, '2025-01-25 12:57:32', 'standart', 'IK10203705'),
(236, 254, 463.75, 5.00, '2025-02-11 16:29:11', 'standart', 'IK10203204'),
(237, 255, 33.75, 5.00, '2025-02-16 18:29:48', 'standart', 'IK10203571'),
(238, 256, 0.00, 5.00, NULL, 'standart', 'IK10203727'),
(239, 257, 160.00, 5.00, '2024-09-09 20:18:23', 'standart', 'IK10203886'),
(240, 258, 220.00, 5.00, '2024-09-03 11:37:42', 'standart', 'IK10203350'),
(241, 259, 6.75, 5.00, '2025-01-14 18:54:16', 'standart', 'IK10203606'),
(242, 260, 46.90, 5.00, '2025-02-28 15:04:17', 'standart', 'IK10203191'),
(243, 261, 11.75, 5.00, '2025-01-04 15:35:19', 'standart', 'IK10203455'),
(244, 262, 0.00, 5.00, '2024-09-03 12:12:07', 'standart', 'IK10203399'),
(245, 263, 48.50, 5.00, '2024-09-03 12:15:50', 'standart', 'IK10203670'),
(246, 264, 45.00, 5.00, '2024-09-03 12:20:55', 'standart', 'IK10203165'),
(247, 265, 7.75, 5.00, '2025-02-26 14:59:28', 'standart', 'IK10203380'),
(248, 266, 211.50, 5.00, '2025-02-11 17:14:40', 'standart', 'IK10203330'),
(249, 267, 0.00, 5.00, '2024-09-03 12:38:51', 'standart', 'IK10203280'),
(250, 268, 205.00, 5.00, '2024-09-03 12:37:19', 'standart', 'IK10203956'),
(251, 269, 2.50, 5.00, '2024-12-27 14:58:39', 'standart', 'IK10203137'),
(252, 270, 144.25, 5.00, '2024-12-12 16:54:15', 'standart', 'IK10203387'),
(253, 271, 6.70, 5.00, '2025-02-19 17:45:31', 'standart', 'IK10203940'),
(254, 272, 15.00, 5.00, '2024-09-03 13:05:45', 'standart', 'IK10203891'),
(255, 273, 55.00, 5.00, '2024-09-03 13:09:37', 'standart', 'IK10203110'),
(256, 274, 18.45, 5.00, '2025-02-22 18:57:38', 'standart', 'IK10203835'),
(257, 275, 29.50, 5.00, '2024-09-03 13:19:03', 'standart', 'IK10203173'),
(258, 276, 93.00, 5.00, '2024-09-03 13:26:26', 'standart', 'IK10203692'),
(259, 277, 55.00, 5.00, '2024-09-29 17:32:56', 'standart', 'IK10203122'),
(260, 278, 105.00, 5.00, '2024-09-03 14:05:13', 'standart', 'IK10203753'),
(261, 279, 29.75, 5.00, '2025-02-22 14:52:15', 'standart', 'IK10203679'),
(262, 280, 190.00, 5.00, '2024-09-03 14:23:04', 'standart', 'IK10203322'),
(263, 281, 78.50, 5.00, '2024-09-03 14:32:44', 'standart', 'IK10203680'),
(264, 282, 28.50, 5.00, '2024-09-09 17:52:16', 'standart', 'IK10203335'),
(265, 283, 297.50, 5.00, '2024-10-14 10:13:59', 'standart', 'IK10203038'),
(266, 284, 59.00, 5.00, '2024-09-09 17:12:06', 'standart', 'IK10203716'),
(267, 285, 0.00, 5.00, NULL, 'standart', 'IK10203854'),
(268, 286, 101.00, 5.00, '2024-09-03 16:17:40', 'standart', 'IK10203453'),
(269, 287, 89.50, 5.00, '2024-09-16 17:49:43', 'standart', 'IK10203032'),
(270, 288, 2.40, 5.00, '2024-09-30 17:29:56', 'standart', 'IK10203259'),
(271, 289, 86.25, 5.00, '2024-12-14 18:16:09', 'standart', 'IK10203117'),
(272, 290, 180.00, 5.00, '2024-09-03 16:53:49', 'standart', 'IK10203304'),
(273, 291, 37.45, 5.00, '2024-10-13 15:11:09', 'standart', 'IK10203566'),
(274, 292, 80.00, 5.00, '2024-09-05 17:42:24', 'standart', 'IK10203492'),
(275, 293, 100.00, 5.00, '2024-09-03 17:26:43', 'standart', 'IK10203135'),
(276, 294, 38.75, 5.00, '2024-10-28 16:00:43', 'standart', 'IK10203661'),
(277, 295, 36.50, 5.00, '2024-09-03 17:48:31', 'standart', 'IK10203754'),
(278, 296, 9.00, 5.00, '2025-02-14 19:24:01', 'standart', 'IK10203116'),
(279, 297, 30.00, 5.00, '2024-09-03 18:24:50', 'standart', 'IK10203771'),
(280, 298, 5.35, 5.00, '2024-12-11 17:19:13', 'standart', 'IK10203345'),
(281, 299, 44.00, 5.00, '2024-09-03 18:52:23', 'standart', 'IK10203069'),
(282, 300, 318.00, 5.00, '2024-09-03 19:18:18', 'standart', 'IK10203841'),
(283, 301, 3.00, 5.00, '2025-01-01 18:50:33', 'standart', 'IK10203390'),
(284, 302, 13.00, 5.00, '2024-09-30 17:18:09', 'standart', 'IK10203368'),
(285, 303, 130.00, 5.00, '2024-09-03 19:29:01', 'standart', 'IK10203197'),
(286, 304, 34.75, 5.00, '2025-02-24 18:30:04', 'standart', 'IK10203464'),
(287, 305, 58.00, 5.00, '2024-10-07 17:44:09', 'standart', 'IK10203847'),
(288, 306, 0.00, 5.00, '2024-09-16 21:19:36', 'standart', 'IK10203210'),
(289, 307, 2.00, 5.00, '2024-09-25 19:44:57', 'standart', 'IK10203053'),
(290, 308, 260.25, 5.00, '2024-10-14 16:00:19', 'standart', 'IK10203598'),
(291, 309, 47.50, 5.00, '2024-09-17 18:51:49', 'standart', 'IK10203294'),
(292, 310, 87.00, 5.00, '2024-09-04 11:58:14', 'standart', 'IK10203223'),
(293, 311, 15.00, 5.00, '2024-12-14 16:04:57', 'standart', 'IK10203863'),
(294, 312, 219.50, 5.00, '2024-09-04 12:57:33', 'standart', 'IK10203624'),
(295, 313, 95.00, 5.00, '2024-09-04 13:12:03', 'standart', 'IK10203790'),
(296, 314, 78.48, 5.00, '2025-01-13 08:41:18', 'standart', 'IK10203880'),
(297, 315, 509.00, 5.00, '2024-09-10 09:11:14', 'standart', 'IK10203507'),
(298, 316, 160.25, 5.00, '2025-02-12 18:04:41', 'standart', 'IK10203976'),
(299, 317, 104.00, 5.00, '2024-09-10 10:06:05', 'standart', 'IK10203176'),
(300, 318, 390.00, 5.00, '2024-09-04 14:17:47', 'standart', 'IK10203944'),
(301, 319, 14.20, 5.00, '2025-02-14 13:56:49', 'standart', 'IK10203994'),
(302, 320, 35.00, 5.00, '2025-02-28 13:26:11', 'standart', 'IK10203662'),
(303, 321, 56.00, 5.00, '2024-09-04 14:57:35', 'standart', 'IK10203593'),
(304, 322, 5.00, 5.00, '2024-11-21 15:15:49', 'standart', 'IK10203867'),
(305, 323, 191.35, 5.00, '2024-11-18 16:02:31', 'standart', 'IK10203913'),
(306, 324, 0.00, 5.00, NULL, 'standart', 'IK10203920'),
(307, 325, 0.50, 5.00, '2024-09-04 17:20:14', 'standart', 'IK10203959'),
(308, 326, 152.00, 5.00, '2024-09-04 16:43:42', 'standart', 'IK10203004'),
(309, 327, 45.10, 5.00, '2024-09-04 17:09:32', 'standart', 'IK10203970'),
(310, 328, 65.00, 5.00, '2024-11-22 18:43:13', 'standart', 'IK10203052'),
(311, 329, 0.00, 5.00, '2024-09-04 17:42:36', 'standart', 'IK10203111'),
(312, 330, 24.00, 5.00, '2025-02-02 13:29:11', 'standart', 'IK10203654'),
(313, 331, 43.50, 5.00, '2024-09-26 18:20:42', 'standart', 'IK10203508'),
(314, 332, 63.00, 5.00, '2024-09-04 18:14:04', 'standart', 'IK10203873'),
(315, 333, 57.00, 5.00, '2025-01-14 13:55:40', 'standart', 'IK10203481'),
(316, 334, 59.75, 5.00, '2025-02-27 18:45:36', 'standart', 'IK10203083'),
(317, 335, 79.50, 5.00, '2024-09-04 18:41:32', 'standart', 'IK10203066'),
(318, 336, 280.00, 5.00, '2024-09-04 19:19:32', 'standart', 'IK10203266'),
(319, 337, 42.00, 5.00, '2024-09-04 19:17:29', 'standart', 'IK10203645'),
(320, 338, 4.10, 5.00, '2024-12-26 18:09:25', 'standart', 'IK10203937'),
(321, 339, 79.50, 5.00, '2024-09-04 19:39:31', 'standart', 'IK10203949'),
(322, 340, 139.00, 5.00, '2024-09-04 19:45:16', 'standart', 'IK10203359'),
(323, 341, 157.00, 5.00, '2024-09-04 20:09:09', 'standart', 'IK10203799'),
(324, 342, 269.00, 5.00, '2024-09-04 21:36:26', 'standart', 'IK10203031'),
(325, 343, 38.50, 5.00, '2024-10-03 20:34:41', 'standart', 'IK10203188'),
(326, 344, 123.00, 5.00, '2024-12-05 17:09:44', 'standart', 'IK10203911'),
(327, 345, 94.50, 5.00, '2024-09-18 17:57:26', 'standart', 'IK10203307'),
(328, 346, 133.00, 5.00, '2024-09-05 11:21:42', 'standart', 'IK10203857'),
(329, 347, 8.50, 5.00, '2025-02-07 15:45:32', 'standart', 'IK10203938'),
(330, 348, 7.25, 5.00, '2025-02-04 13:10:32', 'standart', 'IK10203226'),
(331, 349, 23.00, 5.00, '2025-01-11 14:57:28', 'standart', 'IK10203264'),
(332, 350, 375.00, 5.00, '2024-09-05 12:17:39', 'standart', 'IK10203579'),
(333, 351, 69.50, 5.00, '2024-09-05 12:14:16', 'standart', 'IK10203671'),
(334, 352, 1.40, 5.00, '2024-10-30 15:59:32', 'standart', 'IK10203584'),
(335, 353, 74.00, 5.00, '2024-09-05 12:33:41', 'standart', 'IK10203148'),
(336, 354, 42.00, 5.00, '2024-09-05 12:36:40', 'standart', 'IK10203320'),
(337, 355, 67.50, 5.00, '2024-09-07 14:50:56', 'standart', 'IK10203477'),
(338, 356, 210.00, 5.00, '2024-09-10 17:26:44', 'standart', 'IK10203613'),
(339, 357, 0.00, 5.00, '2024-09-05 13:13:32', 'standart', 'IK10203278'),
(340, 358, 80.00, 5.00, '2024-09-05 13:17:00', 'standart', 'IK10203338'),
(341, 359, 224.25, 5.00, '2025-02-18 18:29:58', 'standart', 'IK10203287'),
(342, 360, 180.00, 5.00, '2024-09-05 14:02:22', 'standart', 'IK10203241'),
(343, 361, 122.43, 5.00, '2024-12-29 18:31:12', 'standart', 'IK10203344'),
(344, 362, 0.00, 5.00, '2024-11-25 17:30:14', 'standart', 'IK10203960'),
(345, 363, 0.00, 5.00, NULL, 'standart', 'IK10203286'),
(346, 364, 60.00, 5.00, '2024-09-05 15:12:07', 'standart', 'IK10203459'),
(347, 365, 85.00, 5.00, '2024-09-05 15:20:10', 'standart', 'IK10203883'),
(348, 366, 25.00, 5.00, '2024-09-05 15:36:36', 'standart', 'IK10203108'),
(349, 367, 3.50, 5.00, '2024-11-18 18:35:53', 'standart', 'IK10203947'),
(350, 368, 12.25, 5.00, '2025-02-26 15:27:55', 'standart', 'IK10203612'),
(351, 369, 0.00, 5.00, '2024-09-05 16:27:32', 'standart', 'IK10203776'),
(352, 370, 86.00, 5.00, '2024-09-05 16:33:08', 'standart', 'IK10203697'),
(353, 371, 26.50, 5.00, '2024-10-21 14:52:46', 'standart', 'IK10203831'),
(354, 372, 0.00, 5.00, NULL, 'standart', '10203145'),
(355, 373, 150.50, 5.00, '2025-02-01 18:44:39', 'standart', 'IK10203145'),
(356, 374, 158.00, 5.00, '2024-09-05 17:12:01', 'standart', 'IK10203061'),
(357, 375, 226.35, 5.00, '2024-10-10 17:30:00', 'standart', 'IK10203570'),
(358, 376, 44.50, 5.00, '2024-09-05 17:33:49', 'standart', 'IK10203132'),
(359, 377, 32.25, 5.00, '2024-12-17 17:41:37', 'standart', 'IK10203081'),
(360, 378, 177.00, 5.00, '2024-09-05 17:58:05', 'standart', 'IK10203168'),
(361, 379, 10.00, 5.00, '2024-12-30 16:55:36', 'standart', 'IK10203577'),
(362, 380, 0.00, 5.00, NULL, 'standart', 'IK10203411'),
(363, 381, 0.00, 5.00, '2024-09-05 18:48:04', 'standart', 'IK10203086'),
(364, 382, 109.45, 5.00, '2025-02-02 17:02:01', 'standart', 'IK10203874'),
(365, 383, 9.70, 5.00, '2025-02-14 15:38:27', 'standart', 'IK10203310'),
(366, 384, 65.00, 5.00, '2024-09-05 19:06:33', 'standart', 'IK10203388'),
(367, 385, 19.50, 5.00, '2024-09-24 18:16:56', 'standart', 'IK10203082'),
(368, 386, 22.00, 5.00, '2024-12-19 13:31:32', 'standart', 'IK10203347'),
(369, 387, 165.50, 5.00, '2024-12-17 17:35:37', 'standart', 'IK10203274'),
(370, 388, 5.00, 5.00, '2024-09-05 20:25:34', 'standart', 'IK10203822'),
(371, 389, 86.40, 5.00, '2024-09-08 13:58:17', 'standart', 'IK10203637'),
(372, 390, 263.50, 5.00, '2025-01-07 14:48:25', 'standart', 'IK10203765'),
(373, 391, 113.50, 5.00, '2024-09-08 14:18:52', 'standart', 'IK10203690'),
(374, 392, 108.00, 5.00, '2024-09-16 15:20:41', 'standart', 'IK10203611'),
(375, 393, 3.50, 5.00, '2025-02-24 13:19:41', 'standart', 'IK10203778'),
(376, 394, 150.00, 5.00, '2024-09-06 11:00:31', 'standart', 'IK10203660'),
(377, 395, 67.30, 5.00, '2025-02-06 14:09:03', 'standart', 'IK10203922'),
(378, 396, 33.00, 5.00, '2024-09-29 17:02:40', 'standart', 'IK10203392'),
(379, 397, 48.25, 5.00, '2025-02-12 16:26:45', 'standart', 'IK10203951'),
(380, 398, 39.50, 5.00, '2024-12-14 17:49:24', 'standart', 'IK10203186'),
(381, 399, 208.75, 5.00, '2024-10-30 15:55:36', 'standart', 'IK10203910'),
(382, 400, 38.50, 5.00, '2024-09-06 13:20:31', 'standart', 'IK10203817'),
(383, 401, 103.50, 5.00, '2024-09-12 19:41:56', 'standart', 'IK10203284'),
(384, 402, 0.00, 5.00, NULL, 'gold', 'IK10203277'),
(385, 403, 18.25, 5.00, '2025-02-14 10:52:10', 'standart', 'IK10203311'),
(386, 404, 88.00, 5.00, '2024-09-06 13:46:04', 'standart', 'IK10203777'),
(387, 405, 160.00, 5.00, '2024-09-06 14:09:25', 'standart', 'IK10203109'),
(388, 406, 40.00, 5.00, '2024-12-04 14:49:36', 'standart', 'IK10203007'),
(389, 407, 31.00, 5.00, '2025-02-13 15:22:43', 'standart', 'IK10203806'),
(390, 408, 12.20, 5.00, '2025-02-15 15:13:58', 'standart', 'IK10203707'),
(391, 409, 334.25, 5.00, '2025-02-26 08:54:41', 'standart', 'IK10203848'),
(392, 410, 44.70, 5.00, '2024-11-20 16:34:47', 'standart', 'IK10203437'),
(393, 411, 30.00, 5.00, '2024-09-06 14:50:22', 'standart', 'IK10203529'),
(394, 412, 0.00, 5.00, '2024-09-06 14:45:32', 'standart', 'IK10203862'),
(395, 413, 348.50, 5.00, '2025-02-11 15:30:50', 'standart', 'IK10203227'),
(396, 414, 8.50, 5.00, '2024-12-03 17:25:57', 'standart', 'IK10203337'),
(397, 415, 3.50, 5.00, '2024-09-06 14:56:32', 'standart', 'IK10203601'),
(398, 416, 0.00, 5.00, '2024-09-06 15:18:02', 'standart', 'IK10203531'),
(399, 417, 21.50, 5.00, '2024-09-06 15:24:40', 'standart', 'IK10203222'),
(400, 418, 52.60, 5.00, '2024-09-06 15:37:07', 'standart', 'IK10203181'),
(401, 419, 127.00, 5.00, '2024-09-06 15:41:28', 'standart', 'IK10203820'),
(402, 420, 17.50, 5.00, '2024-09-06 15:51:58', 'standart', 'IK10203303'),
(403, 421, 164.00, 5.00, '2024-09-06 16:07:12', 'standart', 'IK10203800'),
(404, 422, 12.95, 5.00, '2025-02-12 15:50:39', 'standart', 'IK10203655'),
(405, 423, 134.00, 5.00, '2024-09-06 16:12:26', 'standart', 'IK10203098'),
(406, 424, 140.00, 5.00, '2024-09-06 16:25:58', 'standart', 'IK10203028'),
(407, 425, 23.45, 5.00, '2024-12-26 18:45:38', 'standart', 'IK10203099'),
(408, 426, 209.00, 5.00, '2024-10-29 14:09:48', 'standart', 'IK10203505'),
(409, 427, 108.50, 5.00, '2024-11-07 14:40:07', 'standart', 'IK10203900'),
(410, 428, 7.50, 5.00, '2024-09-07 16:31:23', 'standart', 'IK10203547'),
(411, 429, 12.50, 5.00, '2024-12-29 16:55:45', 'standart', 'IK10203084'),
(412, 430, 80.00, 5.00, '2024-09-06 17:01:59', 'standart', 'IK10203051'),
(413, 431, 0.00, 5.00, NULL, 'standart', 'IK10203483'),
(414, 432, 85.00, 5.00, '2024-09-06 17:34:23', 'standart', 'IK10203516'),
(415, 433, 42.45, 5.00, '2025-02-14 14:57:32', 'standart', 'IK10203063'),
(416, 434, 15.00, 5.00, '2024-09-06 17:51:02', 'standart', 'IK10203984'),
(417, 435, 37.00, 5.00, '2025-02-27 15:23:37', 'standart', 'IK10203485'),
(418, 436, 0.00, 5.00, '2024-09-06 17:56:41', 'standart', 'IK10203244'),
(419, 437, 37.90, 5.00, '2024-12-15 13:30:39', 'standart', 'IK10203978'),
(420, 438, 25.00, 5.00, '2024-09-06 18:06:30', 'standart', 'IK10203734'),
(421, 439, 62.50, 5.00, '2024-09-06 18:16:07', 'standart', 'IK10203035'),
(422, 440, 4.50, 5.00, '2024-09-06 18:26:17', 'standart', 'IK10203721'),
(423, 441, 0.00, 5.00, '2024-09-06 18:27:42', 'standart', 'IK10203711'),
(424, 442, 7.00, 5.00, '2024-09-23 15:58:31', 'standart', 'IK10203150'),
(425, 443, 6.72, 5.00, '2025-02-22 16:50:59', 'standart', 'IK10203260'),
(426, 444, 11.50, 5.00, '2024-09-06 18:40:39', 'standart', 'IK10203985'),
(427, 445, 285.00, 5.00, '2024-09-06 18:56:28', 'standart', 'IK10203296'),
(428, 446, 45.00, 5.00, '2024-09-09 17:20:23', 'standart', 'IK10203479'),
(429, 447, 3.00, 5.00, '2024-10-01 19:06:19', 'standart', 'IK10203355'),
(430, 448, 17.75, 5.00, '2024-12-24 19:34:33', 'standart', 'IK10203107'),
(431, 449, 25.00, 5.00, '2024-09-06 20:04:12', 'standart', 'IK10203044'),
(432, 450, 102.75, 5.00, '2024-11-06 16:43:26', 'standart', 'IK10203288'),
(433, 451, 4.50, 5.00, '2024-09-06 20:23:25', 'standart', 'IK10203541'),
(434, 452, 5.67, 5.00, '2025-02-15 19:14:30', 'standart', 'IK10203695'),
(435, 453, 165.00, 5.00, '2024-09-06 20:28:01', 'standart', 'IK10203946'),
(436, 454, 89.50, 5.00, '2024-09-14 13:58:35', 'standart', 'IK10203855'),
(437, 455, 85.00, 5.00, '2024-09-06 21:04:15', 'standart', 'IK10203650'),
(438, 456, 40.00, 5.00, '2024-09-07 10:28:34', 'standart', 'IK10203026'),
(439, 457, 1.75, 5.00, '2024-11-27 14:11:12', 'standart', 'IK10203439'),
(440, 458, 39.95, 5.00, '2024-12-20 10:36:14', 'standart', 'IK10203102'),
(441, 459, 9.50, 5.00, '2024-12-17 17:24:48', 'standart', 'IK10203013'),
(442, 460, 127.50, 5.00, '2024-09-11 20:14:28', 'standart', 'IK10203788'),
(443, 461, 47.50, 5.00, '2024-09-07 11:53:37', 'standart', 'IK10203295'),
(444, 462, 99.50, 5.00, '2024-09-09 17:33:00', 'standart', 'IK10203376'),
(445, 463, 85.00, 5.00, '2024-09-24 12:08:08', 'standart', 'IK10203402'),
(446, 464, 0.00, 5.00, '2024-09-10 09:03:16', 'standart', 'IK10203382'),
(447, 465, 70.00, 5.00, '2024-09-07 13:20:53', 'standart', 'IK10203925'),
(448, 466, 73.50, 5.00, '2024-12-30 14:53:28', 'standart', 'IK10203457'),
(449, 467, 7.75, 5.00, '2024-12-11 12:58:32', 'standart', 'IK10203682'),
(450, 468, 44.50, 5.00, '2025-01-04 10:24:28', 'standart', 'IK10203342'),
(451, 469, 17.50, 5.00, '2024-09-30 15:09:23', 'standart', 'IK10203616'),
(452, 470, 156.90, 5.00, '2025-02-17 18:56:21', 'standart', 'IK10203656'),
(453, 471, 16.75, 5.00, '2025-02-19 16:14:22', 'standart', 'IK10203249'),
(454, 472, 139.60, 5.00, '2025-02-09 15:06:17', 'standart', 'IK10203484'),
(455, 473, 79.00, 5.00, '2024-09-07 14:38:30', 'standart', 'IK10203164'),
(456, 474, 189.00, 5.00, '2024-09-07 14:46:14', 'standart', 'IK10203565'),
(457, 475, 0.00, 5.00, '2024-09-07 14:51:38', 'standart', 'IK10203232'),
(458, 476, 18.85, 5.00, '2025-02-17 17:54:42', 'standart', 'IK10203427'),
(459, 477, 403.00, 5.00, '2024-09-15 18:10:02', 'standart', 'IK10203580'),
(460, 478, 4.50, 5.00, '2024-12-14 18:24:10', 'standart', 'IK10203761'),
(461, 479, 0.00, 5.00, '2024-09-07 15:34:33', 'standart', 'IK10203784'),
(462, 480, 0.00, 5.00, '2024-09-07 15:37:20', 'standart', 'IK10203039'),
(463, 481, 135.00, 5.00, '2024-09-08 17:38:28', 'standart', 'IK10203643'),
(464, 482, 30.50, 5.00, '2024-11-27 18:25:49', 'standart', 'IK10203070'),
(465, 483, 17.50, 5.00, '2024-09-07 15:47:58', 'standart', 'IK10203635'),
(466, 484, 21.50, 5.00, '2024-09-07 15:53:57', 'standart', 'IK10203986'),
(467, 485, 53.00, 5.00, '2024-12-23 17:34:43', 'standart', 'IK10203269'),
(468, 486, 581.50, 5.00, '2024-09-07 16:06:53', 'standart', 'IK10203882'),
(469, 487, 10.00, 5.00, '2025-02-26 17:24:00', 'standart', 'IK10203123'),
(470, 488, 2.50, 5.00, '2024-09-07 16:34:06', 'standart', 'IK10203065'),
(471, 489, 0.00, 5.00, '2024-11-19 17:38:00', 'standart', 'IK10203533'),
(472, 490, 152.50, 5.00, '2024-09-07 17:11:43', 'standart', 'IK10203957'),
(473, 491, 107.00, 5.00, '2024-10-02 08:14:03', 'standart', 'IK10203128'),
(474, 492, 130.00, 5.00, '2024-09-07 17:22:55', 'standart', 'IK10203762'),
(475, 493, 25.00, 5.00, '2024-09-08 19:07:42', 'standart', 'IK10203590'),
(476, 494, 12.15, 5.00, '2024-12-30 14:38:57', 'standart', 'IK10203143'),
(477, 495, 90.00, 5.00, '2024-09-07 17:35:15', 'standart', 'IK10203416'),
(478, 496, 62.50, 5.00, '2024-09-07 17:47:15', 'standart', 'IK10203710'),
(479, 497, 144.25, 5.00, '2024-11-11 15:25:29', 'standart', 'IK10203755'),
(480, 498, 10.25, 5.00, '2025-02-26 17:12:02', 'standart', 'IK10203361'),
(481, 499, 169.00, 5.00, '2024-09-17 19:01:46', 'standart', 'IK10203858'),
(482, 500, 130.00, 5.00, '2024-09-07 17:57:38', 'standart', 'IK10203945'),
(483, 501, 37.50, 5.00, '2025-01-31 18:07:43', 'standart', 'IK10203878'),
(484, 502, 0.85, 5.00, '2025-01-31 15:11:41', 'standart', 'IK10203183'),
(485, 503, 100.00, 5.00, '2024-09-07 18:19:35', 'standart', 'IK10203198'),
(486, 504, 46.00, 5.00, '2024-09-17 19:16:44', 'standart', 'IK10203074'),
(487, 505, 123.50, 5.00, '2024-09-10 18:37:08', 'standart', 'IK10203636'),
(488, 506, 227.50, 5.00, '2024-09-07 18:34:04', 'standart', 'IK10203812'),
(489, 507, 5.00, 5.00, '2024-09-16 13:06:08', 'standart', 'IK10203500'),
(490, 508, 654.25, 5.00, '2024-10-26 17:13:39', 'standart', 'IK10203246'),
(491, 509, 28.30, 5.00, '2025-02-08 18:26:52', 'standart', 'IK10203119'),
(492, 510, 63.50, 5.00, '2024-09-07 19:28:09', 'standart', 'IK10203218'),
(493, 511, 42.00, 5.00, '2024-09-07 19:39:34', 'standart', 'IK10203675'),
(494, 512, 0.00, 5.00, NULL, 'standart', 'IK10203872'),
(495, 513, 62.75, 5.00, '2025-02-20 17:54:14', 'standart', 'IK10203194'),
(496, 514, 3.50, 5.00, '2024-09-18 19:44:26', 'standart', 'IK10203881'),
(497, 515, 62.50, 5.00, '2024-09-09 19:10:13', 'standart', 'IK10203691'),
(498, 516, 341.00, 5.00, '2024-09-12 15:42:17', 'standart', 'IK10203548'),
(499, 517, 17.50, 5.00, '2024-11-20 17:20:48', 'standart', 'IK10203101'),
(500, 518, 59.00, 5.00, '2024-09-07 21:03:47', 'standart', 'IK10203353'),
(501, 519, 9.75, 5.00, '2025-01-29 19:30:46', 'standart', 'IK10203446'),
(502, 520, 190.00, 5.00, '2024-09-08 10:31:59', 'standart', 'IK10203103'),
(503, 521, 244.00, 5.00, '2025-02-03 17:19:54', 'standart', 'IK10203733'),
(504, 522, 265.50, 5.00, '2024-11-26 16:24:21', 'standart', 'IK10203058'),
(505, 523, 50.00, 5.00, '2024-09-08 11:55:54', 'standart', 'IK10203939'),
(506, 524, 105.00, 5.00, '2024-09-08 12:21:35', 'standart', 'IK10203480'),
(507, 525, 12.00, 5.00, '2024-09-08 12:44:49', 'standart', 'IK10203371'),
(508, 526, 179.38, 5.00, '2025-02-26 14:56:54', 'standart', 'IK10203104'),
(509, 527, 61.00, 5.00, '2024-09-08 12:58:17', 'standart', 'IK10203582'),
(510, 528, 22.50, 5.00, '2025-02-09 16:55:54', 'standart', 'IK10203224'),
(511, 529, 9.20, 5.00, '2024-09-23 16:07:20', 'standart', 'IK10203466'),
(512, 530, 0.00, 5.00, NULL, 'standart', 'IK10203969'),
(513, 531, 67.50, 5.00, '2024-09-08 13:21:39', 'standart', 'IK10203555'),
(514, 532, 102.50, 5.00, '2025-02-15 15:55:43', 'standart', 'IK10203543'),
(515, 533, 103.00, 5.00, '2025-02-12 15:55:00', 'standart', 'IK10203185'),
(516, 534, 75.95, 5.00, '2024-12-01 15:05:02', 'standart', 'IK10203059'),
(517, 535, 74.00, 5.00, '2024-09-08 14:07:30', 'standart', 'IK10203572'),
(518, 536, 26.50, 5.00, '2024-09-08 14:12:53', 'standart', 'IK10203898'),
(519, 537, 230.00, 5.00, '2024-09-08 14:35:46', 'standart', 'IK10203770'),
(520, 538, 136.00, 5.00, '2024-09-08 14:54:58', 'standart', 'IK10203214'),
(521, 539, 160.00, 5.00, '2024-09-08 15:03:23', 'standart', 'IK10203879'),
(522, 540, 148.75, 5.00, '2024-10-10 14:42:53', 'standart', 'IK10203673'),
(523, 541, 3.50, 5.00, '2024-09-08 15:08:38', 'standart', 'IK10203404'),
(524, 542, 112.50, 5.00, '2024-11-30 17:10:22', 'standart', 'IK10203429'),
(525, 543, 57.50, 5.00, '2024-09-24 14:51:29', 'standart', 'IK10203432'),
(526, 544, 37.25, 5.00, '2024-11-02 14:12:50', 'standart', 'IK10203987'),
(528, 546, 143.00, 5.00, '2025-02-03 08:27:09', 'standart', 'IK10203235'),
(529, 547, 11.75, 5.00, '2025-02-02 19:12:08', 'standart', 'IK10203414'),
(530, 548, 4.00, 5.00, '2024-09-28 15:42:59', 'standart', 'IK10203561'),
(531, 549, 105.00, 5.00, '2024-09-08 15:57:26', 'standart', 'IK10203921'),
(532, 550, 77.40, 5.00, '2024-09-08 16:03:54', 'standart', 'IK10203172'),
(533, 551, 25.00, 5.00, '2024-09-08 16:07:23', 'standart', 'IK10203868'),
(534, 552, 107.00, 5.00, '2024-09-08 16:12:12', 'standart', 'IK10203312'),
(535, 553, 300.00, 5.00, '2024-09-08 16:30:33', 'standart', 'IK10203712'),
(536, 554, 160.00, 5.00, '2024-09-09 17:39:33', 'standart', 'IK10203200'),
(537, 555, 34.00, 5.00, '2024-09-08 16:49:58', 'standart', 'IK10203495'),
(538, 556, 330.00, 5.00, '2024-09-08 16:58:26', 'standart', 'IK10203996'),
(539, 557, 555.00, 5.00, '2024-09-08 17:13:36', 'standart', 'IK10203843'),
(540, 558, 173.00, 5.00, '2024-09-08 17:09:09', 'standart', 'IK10203975'),
(541, 559, 78.45, 5.00, '2024-10-27 17:59:10', 'standart', 'IK10203008'),
(542, 560, 290.00, 5.00, '2024-09-08 17:18:40', 'standart', 'IK10203233'),
(543, 561, 27.50, 5.00, '2024-09-21 19:46:01', 'standart', 'IK10203625'),
(544, 562, 0.50, 5.00, '2025-01-28 16:55:44', 'standart', 'IK10203146'),
(545, 563, 105.00, 5.00, '2024-09-08 17:29:54', 'standart', 'IK10203461'),
(546, 564, 100.00, 5.00, '2024-09-08 17:31:04', 'standart', 'IK10203385'),
(547, 565, 45.25, 5.00, '2025-02-08 15:16:45', 'standart', 'IK10203133'),
(548, 566, 151.00, 5.00, '2024-12-06 17:16:45', 'standart', 'IK10203941'),
(549, 567, 217.00, 5.00, '2024-09-08 17:54:00', 'standart', 'IK10203046'),
(550, 568, 74.50, 5.00, '2024-09-08 18:12:28', 'standart', 'IK10203467'),
(551, 569, 370.00, 5.00, '2024-09-11 16:51:56', 'standart', 'IK10203494'),
(552, 570, 58.15, 5.00, '2025-02-06 15:04:25', 'standart', 'IK10203306'),
(553, 571, 175.00, 5.00, '2024-09-08 18:38:52', 'standart', 'IK10203723'),
(554, 572, 15.00, 5.00, '2024-09-08 18:45:06', 'standart', 'IK10203744'),
(555, 573, 4.05, 5.00, '2025-02-06 16:19:14', 'standart', 'IK10203079'),
(556, 574, 0.00, 5.00, NULL, 'standart', 'IK10203248'),
(557, 575, 3.00, 5.00, '2024-09-08 19:16:34', 'standart', 'IK10203618'),
(558, 576, 52.50, 5.00, '2024-09-08 19:21:35', 'standart', 'IK10203901'),
(559, 577, 62.10, 5.00, '2025-01-23 13:58:06', 'standart', 'IK10203225'),
(560, 578, 0.00, 5.00, NULL, 'standart', 'IK10203993'),
(561, 579, 0.00, 5.00, NULL, 'standart', 'IK10203588'),
(562, 580, 367.50, 5.00, '2024-12-12 17:48:09', 'standart', 'IK10203255'),
(563, 581, 230.00, 5.00, '2024-09-08 20:12:54', 'standart', 'IK10203646'),
(564, 582, 16.85, 5.00, '2025-02-16 15:44:10', 'standart', 'IK10203651'),
(565, 583, 4.15, 5.00, '2025-02-04 18:32:09', 'standart', 'IK10203362'),
(566, 584, 63.50, 5.00, '2025-02-22 18:54:27', 'standart', 'IK10203567'),
(567, 585, 5.75, 5.00, '2024-12-18 10:29:37', 'standart', 'IK10203846'),
(568, 586, 40.00, 5.00, '2024-09-09 19:54:06', 'standart', 'IK10203666'),
(569, 587, 41.00, 5.00, '2024-09-08 22:00:02', 'standart', 'IK10203005'),
(570, 588, 60.00, 5.00, '2024-09-09 09:11:03', 'standart', 'IK10203015'),
(571, 589, 40.00, 5.00, '2025-01-31 15:08:00', 'standart', 'IK10203474'),
(572, 590, 43.00, 5.00, '2024-09-09 09:44:55', 'standart', 'IK10203609'),
(573, 591, 16.70, 5.00, '2025-01-04 19:23:40', 'standart', 'IK10203125'),
(574, 592, 126.25, 5.00, '2024-12-23 12:31:52', 'standart', 'IK10203738'),
(575, 593, 4.50, 5.00, '2025-02-03 10:46:39', 'standart', 'IK10203640'),
(576, 594, 56.50, 5.00, '2024-12-13 14:26:50', 'standart', 'IK10203297'),
(577, 595, 135.00, 5.00, '2024-09-09 14:58:54', 'standart', 'IK10203340'),
(578, 596, 169.00, 5.00, '2024-09-09 15:06:48', 'standart', 'IK10203506'),
(579, 597, 29.50, 5.00, '2024-09-09 15:13:26', 'standart', 'IK10203042'),
(580, 598, 44.00, 5.00, '2024-09-09 15:42:54', 'standart', 'IK10203283'),
(581, 599, 34.50, 5.00, '2025-01-27 14:14:36', 'standart', 'IK10203795'),
(582, 600, 180.00, 5.00, '2024-09-16 14:46:44', 'standart', 'IK10203234'),
(583, 601, 87.00, 5.00, '2024-09-09 15:56:04', 'standart', 'IK10203955'),
(584, 602, 146.50, 5.00, '2024-09-09 16:05:03', 'standart', 'IK10203209'),
(585, 603, 120.00, 5.00, '2024-09-09 16:14:18', 'standart', 'IK10203169'),
(586, 604, 360.00, 5.00, '2024-09-09 16:21:20', 'standart', 'IK10203256'),
(587, 605, 20.25, 5.00, '2025-02-12 16:35:46', 'standart', 'IK10203971'),
(588, 606, 48.00, 5.00, '2024-09-11 16:55:28', 'standart', 'IK10203827'),
(589, 607, 178.00, 5.00, '2024-09-09 16:48:12', 'standart', 'IK10203644'),
(590, 608, 95.00, 5.00, '2024-09-19 19:35:20', 'standart', 'IK10203336'),
(591, 609, 40.75, 5.00, '2025-02-03 17:37:56', 'standart', 'IK10203454'),
(592, 610, 48.75, 5.00, '2024-10-30 17:42:19', 'standart', 'IK10203195'),
(593, 611, 4.30, 5.00, '2025-01-11 14:13:00', 'standart', 'IK10203592'),
(594, 612, 40.00, 5.00, '2024-09-09 17:16:32', 'standart', 'IK10203686'),
(595, 613, 77.00, 5.00, '2024-09-09 17:17:54', 'standart', 'IK10203326'),
(596, 614, 56.00, 5.00, '2024-09-09 17:24:20', 'standart', 'IK10203804'),
(597, 615, 210.00, 5.00, '2024-09-09 17:31:01', 'standart', 'IK10203981'),
(598, 616, 225.00, 5.00, '2024-09-09 18:53:43', 'standart', 'IK10203406'),
(599, 617, 245.00, 5.00, '2024-09-09 17:46:17', 'standart', 'IK10203585'),
(600, 618, 150.00, 5.00, '2024-09-09 17:51:44', 'standart', 'IK10203389'),
(601, 619, 70.00, 5.00, '2024-09-09 17:59:49', 'standart', 'IK10203025'),
(602, 620, 19.00, 5.00, '2024-09-09 17:59:34', 'standart', 'IK10203085'),
(603, 621, 44.30, 5.00, '2024-12-03 12:43:27', 'standart', 'IK10203309'),
(604, 622, 1.50, 5.00, '2024-10-03 18:34:21', 'standart', 'IK10203097'),
(605, 623, 74.93, 5.00, '2024-10-26 17:35:47', 'standart', 'IK10203003'),
(606, 624, 8.00, 5.00, '2025-02-25 18:43:38', 'standart', 'IK10203641'),
(607, 625, 2.50, 5.00, '2024-09-21 15:54:08', 'standart', 'IK10203549'),
(608, 626, 100.00, 5.00, '2024-09-09 18:34:53', 'standart', 'IK10203502'),
(609, 627, 100.00, 5.00, '2024-09-09 18:39:50', 'standart', 'IK10203395'),
(610, 628, 69.50, 5.00, '2024-09-09 18:42:49', 'standart', 'IK10203314'),
(611, 629, 65.50, 5.00, '2024-09-11 18:04:50', 'standart', 'IK10203208'),
(612, 630, 197.00, 5.00, '2024-09-09 18:52:16', 'standart', 'IK10203717'),
(613, 631, 10.90, 5.00, '2025-02-13 14:57:43', 'standart', 'IK10203742'),
(614, 632, 60.00, 5.00, '2024-10-03 13:23:52', 'standart', 'IK10203346'),
(615, 633, 52.00, 5.00, '2024-09-09 19:02:26', 'standart', 'IK10203677'),
(616, 634, 174.50, 5.00, '2024-12-05 18:14:48', 'standart', 'IK10203853'),
(617, 635, 5.75, 5.00, '2024-12-14 12:24:22', 'standart', 'IK10203586'),
(618, 636, 15.00, 5.00, '2024-09-09 19:09:35', 'standart', 'IK10203447'),
(619, 637, 171.50, 5.00, '2025-02-12 16:35:28', 'standart', 'IK10203893'),
(620, 638, 41.50, 5.00, '2024-09-18 11:45:59', 'standart', 'IK10203060'),
(621, 639, 210.70, 5.00, '2025-02-14 17:59:41', 'standart', 'IK10203568'),
(622, 640, 200.00, 5.00, '2024-11-23 14:46:43', 'standart', 'IK10203071'),
(623, 641, 0.00, 5.00, NULL, 'standart', 'IK10203253'),
(624, 642, 0.00, 5.00, NULL, 'standart', 'IK10203648'),
(625, 643, 125.00, 5.00, '2024-09-09 21:11:41', 'standart', 'IK10203216'),
(626, 644, 40.50, 5.00, '2024-10-04 14:42:52', 'standart', 'IK10203769'),
(627, 645, 56.75, 5.00, '2024-10-18 08:34:49', 'standart', 'IK10203698'),
(628, 646, 23.25, 5.00, '2025-02-11 12:36:19', 'standart', 'IK10203369'),
(629, 647, 187.75, 5.00, '2025-02-04 14:37:08', 'standart', 'IK10203268'),
(630, 648, 5.75, 5.00, '2025-01-01 14:48:35', 'standart', 'IK10203667'),
(631, 649, 104.00, 5.00, '2024-09-09 21:18:22', 'standart', 'IK10203542'),
(632, 650, 241.50, 5.00, '2024-09-11 18:54:32', 'standart', 'IK10203419'),
(633, 651, 200.00, 5.00, '2024-09-09 21:27:59', 'standart', 'IK10203100'),
(634, 652, 125.00, 5.00, '2024-09-09 21:28:48', 'standart', 'IK10203816'),
(635, 653, 330.10, 5.00, '2024-10-16 18:12:38', 'standart', 'IK10203231'),
(636, 654, 0.00, 5.00, '2024-09-09 21:37:00', 'standart', 'IK10203633'),
(637, 655, 0.00, 5.00, '2024-09-09 21:37:41', 'standart', 'IK10203170'),
(638, 656, 1.50, 5.00, '2024-10-31 16:12:30', 'standart', 'IK10203607'),
(639, 657, 69.90, 5.00, '2024-11-11 18:06:34', 'standart', 'IK10203078'),
(640, 658, 35.50, 5.00, '2025-02-01 14:32:40', 'standart', 'IK10203615'),
(641, 659, 38.75, 5.00, '2024-10-17 11:02:43', 'standart', 'IK10203895'),
(642, 660, 16.00, 5.00, '2024-09-16 15:28:40', 'standart', 'IK10203802'),
(643, 661, 149.00, 5.00, '2024-09-09 21:45:20', 'standart', 'IK10203551'),
(644, 662, 97.00, 5.00, '2024-09-23 16:19:49', 'standart', 'IK10203363'),
(645, 663, 37.95, 5.00, '2024-12-11 08:26:55', 'standart', 'IK10203737'),
(646, 664, 62.50, 5.00, '2024-09-10 08:42:11', 'standart', 'IK10203604'),
(647, 665, 6.00, 5.00, '2024-10-03 16:02:40', 'standart', 'IK10203859'),
(648, 666, 80.25, 5.00, '2025-02-28 16:32:26', 'standart', 'IK10203442'),
(649, 667, 40.00, 5.00, '2024-09-10 10:01:28', 'standart', 'IK10203535'),
(650, 668, 70.00, 5.00, '2024-09-10 10:07:39', 'standart', 'IK10203112'),
(651, 669, 7.50, 5.00, '2024-09-10 18:04:07', 'standart', 'IK10203325'),
(652, 670, 0.00, 5.00, NULL, 'standart', 'IK10203630'),
(653, 671, 0.00, 5.00, NULL, 'standart', '10289928964'),
(654, 672, 13.50, 5.00, '2025-01-15 19:58:37', 'standart', 'IK10203517'),
(655, 673, 23.00, 5.00, '2024-09-15 18:27:00', 'standart', 'IK10203417'),
(656, 674, 10.50, 5.00, '2024-10-31 17:46:49', 'standart', 'IK10203463'),
(657, 675, 11.50, 5.00, '2024-09-10 12:44:36', 'standart', 'IK10203010'),
(658, 676, 34.50, 5.00, '2024-09-10 12:49:59', 'standart', 'IK10203054'),
(659, 677, 14.75, 5.00, '2025-02-03 09:14:08', 'standart', 'IK10203751'),
(660, 678, 25.50, 5.00, '2024-12-09 17:02:31', 'standart', 'IK10203113'),
(661, 679, 3.00, 5.00, '2024-09-11 17:44:23', 'standart', 'IK10203597'),
(662, 680, 26.85, 5.00, '2024-12-16 15:03:54', 'standart', 'IK10203252'),
(663, 681, 94.00, 5.00, '2024-09-10 16:40:41', 'standart', 'IK10203105'),
(664, 682, 225.00, 5.00, '2024-09-10 16:57:02', 'standart', 'IK10203966'),
(665, 683, 0.00, 5.00, NULL, 'standart', 'IK10203315'),
(666, 684, 199.95, 5.00, '2024-10-30 17:50:11', 'standart', 'IK10203683'),
(667, 685, 0.00, 5.00, NULL, 'standart', 'IK05303075906'),
(668, 686, 0.00, 5.00, NULL, 'standart', 'IK10203064'),
(670, 688, 134.50, 5.00, '2024-09-11 12:50:06', 'standart', 'IK05327094031'),
(671, 689, 12.00, 5.00, '2024-09-25 18:09:20', 'standart', 'IK10203728'),
(672, 690, 185.00, 5.00, '2024-09-11 13:00:48', 'standart', 'IK05379307973'),
(673, 691, 6.50, 5.00, '2024-09-11 13:01:50', 'standart', 'IK05514563152'),
(674, 692, 0.00, 5.00, NULL, 'standart', 'IK10203045'),
(675, 693, 0.00, 5.00, NULL, 'standart', 'IK10203773'),
(676, 694, 8.50, 5.00, '2024-12-17 10:28:45', 'standart', 'IK10203991'),
(677, 695, 137.20, 5.00, '2024-11-04 19:08:52', 'standart', 'IK10204571'),
(678, 696, 0.00, 5.00, '2024-10-23 18:41:00', 'standart', 'IK05413721122'),
(679, 697, 43.00, 5.00, '2024-09-11 13:18:16', 'standart', 'IK05432446491'),
(680, 698, 16.90, 5.00, '2025-01-04 18:46:52', 'standart', 'IK10204522'),
(681, 699, 34.00, 5.00, '2024-09-11 13:20:57', 'standart', 'IK05424525440'),
(682, 700, 85.00, 5.00, '2024-09-11 13:23:25', 'standart', 'IK05359453687'),
(683, 701, 80.00, 5.00, '2024-09-11 13:28:21', 'standart', 'IK05389426462'),
(684, 702, 75.45, 5.00, '2025-02-27 08:53:08', 'standart', 'IK10204576'),
(685, 703, 128.50, 5.00, '2024-09-11 13:31:07', 'standart', 'IK10203050'),
(686, 704, 148.50, 5.00, '2025-02-06 16:05:19', 'standart', 'IK10203167'),
(687, 705, 0.00, 5.00, NULL, 'standart', 'IK10203892'),
(688, 706, 0.00, 5.00, NULL, 'standart', 'IK10203341'),
(689, 707, 168.00, 5.00, '2024-09-11 13:37:39', 'standart', 'IK05310318312'),
(690, 708, 52.50, 5.00, '2025-02-04 12:55:55', 'standart', 'IK10204606'),
(691, 709, 63.50, 5.00, '2024-09-15 20:29:14', 'standart', 'IK10204584'),
(692, 710, 63.00, 5.00, '2024-09-11 13:43:02', 'standart', 'IK05466713696'),
(693, 711, 120.00, 5.00, '2024-09-11 14:27:39', 'standart', 'IK05427832689'),
(694, 712, 330.00, 5.00, '2024-09-11 15:41:03', 'standart', 'IK10204392'),
(695, 713, 0.00, 5.00, NULL, 'standart', 'IK05375863752'),
(696, 714, 155.00, 5.00, '2025-01-03 14:26:40', 'standart', 'IK10204316'),
(697, 715, 0.00, 5.00, NULL, 'standart', 'IK05345219022'),
(698, 716, 0.50, 5.00, '2024-09-11 17:58:40', 'standart', 'IK10204848'),
(699, 717, 34.00, 5.00, '2024-09-11 18:13:06', 'standart', 'IK05449333223'),
(700, 718, 188.00, 5.00, '2024-09-11 18:22:23', 'standart', 'IK05413785592'),
(701, 719, 5.00, 5.00, '2024-11-29 16:04:30', 'standart', 'IK10204496'),
(702, 720, 197.50, 5.00, '2025-02-13 19:18:15', 'standart', 'IK10204583'),
(703, 721, 250.00, 5.00, '2024-09-11 20:33:37', 'standart', 'IK05353051675'),
(704, 722, 16.20, 5.00, '2024-10-29 16:43:01', 'standart', 'IK10204190'),
(705, 723, 16.25, 5.00, '2024-11-19 17:08:32', 'standart', 'IK10204171'),
(706, 724, 60.00, 5.00, '2024-09-11 20:38:44', 'standart', 'IK05063841518'),
(707, 725, 4.50, 5.00, '2024-09-26 15:37:12', 'standart', 'IK05307836562'),
(708, 726, 0.50, 5.00, '2024-09-13 15:09:14', 'standart', 'IK10204423'),
(709, 727, 3.00, 5.00, '2024-09-12 18:11:50', 'standart', 'IK05392538021'),
(710, 728, 0.00, 5.00, '2024-09-13 11:05:31', 'standart', 'IK10204217'),
(711, 729, 60.00, 5.00, '2024-09-13 14:10:04', 'standart', 'IK10204528'),
(712, 730, 0.00, 5.00, '2024-09-14 19:33:31', 'standart', 'IK10204590'),
(713, 731, 85.75, 5.00, '2024-10-07 19:51:30', 'standart', 'IK10204422'),
(714, 732, 76.35, 5.00, '2024-11-14 16:49:39', 'standart', 'IK10204491'),
(715, 733, 224.70, 5.00, '2024-10-09 19:02:56', 'standart', 'IK10204520'),
(716, 734, 10.00, 5.00, '2024-09-13 15:07:52', 'standart', 'IK05418735352'),
(717, 735, 87.25, 5.00, '2025-02-22 15:48:14', 'standart', 'IK10204591'),
(718, 736, 85.00, 5.00, '2024-09-13 15:14:14', 'standart', 'IK05389133332'),
(719, 737, 15.00, 5.00, '2024-09-13 17:42:27', 'standart', 'IK10204592'),
(720, 738, 11.00, 5.00, '2024-09-13 18:04:01', 'standart', 'IK10204593'),
(721, 739, 55.00, 5.00, '2024-09-13 19:56:50', 'standart', 'IK10204595'),
(722, 740, 99.50, 5.00, '2024-09-14 11:02:06', 'standart', 'IK10204596'),
(723, 741, 4.75, 5.00, '2024-11-13 15:29:24', 'standart', 'IK10204597'),
(724, 742, 139.50, 5.00, '2024-12-21 10:55:20', 'standart', 'IK10204598'),
(725, 743, 0.00, 5.00, NULL, 'standart', 'IK10204599'),
(726, 744, 85.00, 5.00, '2024-09-14 14:29:01', 'standart', 'IK10204600'),
(727, 745, 24.82, 5.00, '2025-02-15 13:28:05', 'standart', 'IK10204601'),
(728, 746, 205.50, 5.00, '2024-09-14 15:41:29', 'standart', 'IK10204602'),
(729, 747, 31.00, 5.00, '2024-09-14 16:48:23', 'standart', 'IK10204603'),
(730, 748, 195.75, 5.00, '2024-11-28 17:30:30', 'standart', 'IK10204604'),
(731, 749, 60.50, 5.00, '2025-02-15 16:00:32', 'standart', 'IK10204605'),
(732, 750, 0.00, 5.00, '2024-09-14 17:44:40', 'standart', 'IK10204607'),
(733, 751, 96.35, 5.00, '2025-02-28 13:50:47', 'standart', 'IK10204608'),
(734, 752, 0.00, 5.00, '2024-09-14 19:03:17', 'standart', 'IK10204535'),
(735, 753, 2.35, 5.00, '2024-10-22 12:20:01', 'standart', 'IK10204534'),
(736, 754, 230.00, 5.00, '2024-09-14 19:22:09', 'standart', 'IK10204536'),
(737, 755, 20.00, 5.00, '2024-09-14 19:36:40', 'standart', 'IK10204537'),
(738, 756, 9.00, 5.00, '2024-09-14 19:39:53', 'standart', 'IK10204538'),
(739, 757, 64.00, 5.00, '2024-09-15 11:47:51', 'standart', 'IK10204569'),
(740, 758, 3.25, 5.00, '2024-12-20 11:09:59', 'standart', 'IK10204570'),
(741, 759, 148.00, 5.00, '2024-10-16 17:38:05', 'standart', 'IK10204572'),
(742, 760, 43.00, 5.00, '2024-09-15 14:34:14', 'standart', 'IK10204573'),
(743, 761, 100.00, 5.00, '2024-09-15 14:46:04', 'standart', 'IK10204575'),
(744, 762, 264.00, 5.00, '2025-02-01 13:47:55', 'standart', 'IK10204577');
INSERT INTO `musteri_puanlar` (`id`, `musteri_id`, `puan_bakiye`, `puan_oran`, `son_alisveris_tarihi`, `musteri_turu`, `barcode`) VALUES
(745, 763, 0.50, 5.00, '2024-09-23 10:39:08', 'standart', 'IK10204574'),
(746, 764, 88.50, 5.00, '2024-09-15 17:03:39', 'standart', 'IK10204579'),
(747, 765, 43.50, 5.00, '2024-09-15 18:57:53', 'standart', 'IK10204581'),
(748, 766, 51.50, 5.00, '2024-09-17 19:49:40', 'standart', 'IK10204578'),
(749, 767, 7.50, 5.00, '2024-10-07 18:37:15', 'standart', 'IK10204368'),
(750, 768, 139.00, 5.00, '2024-09-29 18:13:00', 'standart', 'IK10204580'),
(751, 769, 110.00, 5.00, '2024-09-15 19:36:30', 'standart', 'IK10204582'),
(752, 770, 0.00, 5.00, NULL, 'standart', 'IK10204585'),
(753, 771, 93.40, 5.00, '2024-12-31 09:37:09', 'standart', 'IK10204587'),
(754, 772, 190.00, 5.00, '2024-09-16 10:29:14', 'standart', 'IK10204586'),
(755, 773, 100.00, 5.00, '2024-09-16 10:38:49', 'standart', 'IK10204588'),
(756, 774, 125.50, 5.00, '2024-09-16 11:12:34', 'standart', 'IK10204492'),
(757, 775, 0.00, 5.00, '2024-09-16 12:05:43', 'standart', 'IK10204069'),
(758, 776, 66.00, 5.00, '2024-09-16 17:11:29', 'standart', 'IK10204490'),
(759, 777, 30.00, 5.00, '2024-09-16 12:18:07', 'standart', 'IK10204068'),
(760, 778, 0.00, 5.00, '2024-09-16 14:31:58', 'standart', 'IK10204067'),
(761, 779, 228.50, 5.00, '2024-09-16 14:49:13', 'standart', 'IK10204066'),
(762, 780, 50.00, 5.00, '2024-09-16 14:53:49', 'standart', 'IK10204065'),
(763, 781, 0.00, 5.00, '2024-09-16 15:02:23', 'standart', 'IK10204064'),
(764, 782, 38.50, 5.00, '2024-09-16 15:13:01', 'standart', 'IK10204063'),
(765, 783, 54.00, 5.00, '2024-10-08 08:42:15', 'standart', 'IK10204589'),
(766, 784, 43.00, 5.00, '2024-09-16 16:33:48', 'standart', 'IK10204369'),
(767, 785, 29.00, 5.00, '2024-11-07 14:50:29', 'standart', 'IK10204370'),
(768, 786, 6.90, 5.00, '2024-12-21 19:31:13', 'standart', 'IK10204493'),
(769, 787, 200.00, 5.00, '2024-09-16 17:28:25', 'standart', 'IK10204495'),
(770, 788, 375.00, 5.00, '2024-09-16 17:28:10', 'standart', 'IK10204497'),
(771, 789, 62.00, 5.00, '2024-09-16 17:22:00', 'standart', 'IK10204498'),
(772, 790, 10.50, 5.00, '2024-12-30 11:40:07', 'standart', 'IK10204999'),
(773, 791, 78.50, 5.00, '2024-09-16 18:37:09', 'standart', 'IK10204365'),
(774, 792, 6.00, 5.00, '2024-09-16 19:55:55', 'standart', 'IK10204366'),
(775, 793, 24.00, 5.00, '2024-10-01 20:13:44', 'standart', 'IK10204371'),
(776, 794, 58.00, 5.00, '2024-10-11 07:50:55', 'standart', 'IK10204372'),
(777, 795, 150.00, 5.00, '2024-09-17 10:47:34', 'standart', 'IK10204373'),
(778, 796, 72.50, 5.00, '2024-10-07 13:24:13', 'standart', 'IK10204374'),
(779, 797, 370.00, 5.00, '2024-09-17 15:44:03', 'standart', 'IK10204367'),
(780, 798, 65.00, 5.00, '2024-09-17 17:42:43', 'standart', 'IK10204376'),
(781, 799, 50.80, 5.00, '2024-09-17 17:44:43', 'standart', 'IK10204375'),
(782, 800, 84.00, 5.00, '2024-09-17 17:47:02', 'standart', 'IK10204377'),
(783, 801, 20.00, 5.00, '2024-09-17 18:00:39', 'standart', 'IK10204378'),
(784, 802, 44.50, 5.00, '2024-10-09 18:10:19', 'standart', 'IK10204379'),
(785, 803, 201.50, 5.00, '2024-10-02 18:44:06', 'standart', 'IK10204527'),
(786, 804, 64.50, 5.00, '2024-09-17 18:18:32', 'standart', 'IK10204514'),
(787, 805, 51.50, 5.00, '2024-09-17 18:24:20', 'standart', 'IK10204515'),
(788, 806, 67.45, 5.00, '2024-12-26 14:14:53', 'standart', 'IK10204523'),
(789, 807, 90.70, 5.00, '2024-12-13 13:16:10', 'standart', 'IK10204516'),
(790, 808, 59.25, 5.00, '2024-12-12 19:31:12', 'standart', 'IK10204518'),
(791, 809, 0.00, 5.00, '2024-09-17 19:28:02', 'standart', 'IK10204521'),
(792, 810, 116.25, 5.00, '2024-11-19 17:25:17', 'standart', 'IK10204519'),
(793, 811, 40.00, 5.00, '2024-09-17 20:07:23', 'standart', 'IK10204524'),
(794, 812, 75.80, 5.00, '2024-09-18 11:04:02', 'standart', 'IK10204525'),
(795, 813, 6.25, 5.00, '2025-01-17 10:43:04', 'standart', 'IK10204526'),
(796, 814, 15.50, 5.00, '2024-09-18 16:14:31', 'standart', 'IK10204529'),
(797, 815, 7.50, 5.00, '2024-09-18 17:09:10', 'standart', 'IK10204530'),
(798, 816, 116.50, 5.00, '2024-12-28 17:33:01', 'standart', 'IK10204531'),
(799, 817, 11.50, 5.00, '2024-09-18 17:43:37', 'standart', 'IK10204532'),
(800, 818, 86.00, 5.00, '2024-12-14 13:37:09', 'standart', 'IK10204533'),
(801, 819, 0.00, 5.00, NULL, 'standart', 'IK10204380'),
(802, 820, 40.00, 5.00, '2024-09-19 18:02:58', 'standart', 'IK10204517'),
(803, 821, 3.80, 5.00, '2024-12-20 15:55:40', 'standart', 'IK10204271'),
(804, 822, 0.00, 5.00, '2024-09-20 14:38:25', 'standart', 'IK10204719'),
(805, 823, 16.50, 5.00, '2025-01-29 11:50:28', 'standart', 'IK10204720'),
(806, 824, 75.00, 5.00, '2024-09-20 17:25:53', 'standart', 'IK10204384'),
(807, 825, 2.00, 5.00, '2024-09-20 18:09:39', 'standart', 'IK10204383'),
(808, 826, 72.00, 5.00, '2025-01-18 14:33:29', 'standart', 'IK10204382'),
(809, 827, 36.30, 5.00, '2025-02-24 14:46:39', 'standart', 'IK10203864'),
(810, 828, 3.50, 5.00, '2024-10-05 15:08:19', 'standart', 'IK10204381'),
(811, 829, 62.50, 5.00, '2024-09-21 14:43:01', 'standart', 'IK10204722'),
(812, 830, 4.00, 5.00, '2025-02-02 16:56:07', 'standart', 'IK10204170'),
(813, 831, 16.50, 5.00, '2024-09-21 18:27:19', 'standart', 'IK10204721'),
(814, 832, 1.85, 5.00, '2024-10-29 13:11:36', 'standart', 'IK10204385'),
(815, 833, 68.00, 5.00, '2024-09-22 13:31:54', 'standart', 'IK10204172'),
(816, 834, 0.00, 5.00, NULL, 'standart', 'IK10204386'),
(817, 835, 115.00, 5.00, '2024-09-29 12:53:26', 'standart', 'IK10204387'),
(818, 836, 57.00, 5.00, '2024-09-22 18:08:52', 'standart', 'IK10204388'),
(819, 837, 193.00, 5.00, '2024-09-23 11:12:49', 'standart', 'IK10204285'),
(820, 838, 0.00, 5.00, '2024-09-23 12:46:06', 'standart', 'IK10204389'),
(821, 839, 60.00, 5.00, '2024-09-23 14:51:36', 'standart', 'IK10204174'),
(822, 840, 1.50, 5.00, '2024-12-05 16:47:06', 'standart', 'IK10204390'),
(823, 841, 110.00, 5.00, '2024-09-23 16:39:41', 'standart', 'IK10204173'),
(824, 842, 55.00, 5.00, '2024-09-23 17:23:20', 'standart', 'IK10204286'),
(825, 843, 0.00, 5.00, NULL, 'standart', 'IK10204391'),
(826, 844, 54.75, 5.00, '2025-02-19 11:39:48', 'standart', 'IK10204287'),
(827, 845, 74.50, 5.00, '2024-09-24 15:59:22', 'standart', 'IK10204393'),
(828, 846, 103.45, 5.00, '2025-02-26 18:32:09', 'standart', 'IK10204288'),
(829, 847, 21.00, 5.00, '2025-02-01 15:29:54', 'standart', 'IK10204291'),
(830, 848, 5.00, 5.00, '2024-09-24 20:41:16', 'standart', 'IK10204290'),
(831, 849, 116.65, 5.00, '2024-11-04 15:43:47', 'standart', 'IK10204394'),
(832, 850, 47.00, 5.00, '2024-09-25 18:29:15', 'standart', 'IK10204292'),
(833, 851, 70.00, 5.00, '2024-09-25 18:51:04', 'standart', 'IK10204293'),
(834, 852, 84.00, 5.00, '2024-09-25 18:56:25', 'standart', 'IK10204395'),
(835, 853, 87.00, 5.00, '2024-09-26 10:14:58', 'standart', 'IK10204294'),
(836, 854, 39.00, 5.00, '2024-09-26 17:31:29', 'standart', 'IK10204296'),
(837, 855, 68.90, 5.00, '2024-09-26 19:12:18', 'standart', 'IK10204411'),
(838, 856, 84.95, 5.00, '2024-12-26 14:44:19', 'standart', 'IK10204412'),
(839, 857, 7.35, 5.00, '2024-11-21 12:27:13', 'standart', 'IK10204413'),
(840, 858, 42.40, 5.00, '2025-02-09 15:17:06', 'standart', 'IK10204297'),
(841, 859, 15.00, 5.00, '2024-09-28 14:18:11', 'standart', 'IK10204414'),
(842, 860, 12.00, 5.00, '2025-02-13 17:50:25', 'standart', 'IK10204415'),
(843, 861, 38.25, 5.00, '2025-02-16 15:04:18', 'standart', 'IK10204298'),
(844, 862, 36.25, 5.00, '2025-02-10 19:36:15', 'standart', 'IK10204299'),
(845, 863, 150.00, 5.00, '2024-09-30 18:12:39', 'standart', 'IK10204416'),
(846, 864, 126.35, 5.00, '2025-02-12 18:27:17', 'standart', 'IK10204417'),
(847, 865, 193.00, 5.00, '2024-10-01 15:43:47', 'standart', 'IK10204418'),
(848, 866, 282.75, 5.00, '2024-12-03 17:22:41', 'standart', 'IK10204419'),
(849, 867, 0.00, 5.00, NULL, 'standart', 'IK10204420'),
(850, 868, 66.00, 5.00, '2024-12-16 11:20:20', 'standart', 'IK10204421'),
(851, 869, 38.00, 5.00, '2025-02-13 20:31:32', 'standart', 'IK10204424'),
(852, 870, 24.00, 5.00, '2024-12-07 16:21:42', 'standart', 'IK10204175'),
(853, 871, 75.00, 5.00, '2024-10-09 14:08:20', 'standart', 'IK10204176'),
(854, 872, 70.75, 5.00, '2024-10-09 16:24:56', 'standart', 'IK10204177'),
(855, 873, 2.00, 5.00, '2024-10-09 16:32:34', 'standart', 'IK10204178'),
(856, 874, 55.50, 5.00, '2025-02-27 17:07:04', 'standart', 'IK10204179'),
(857, 875, 11.25, 5.00, '2024-10-10 08:47:18', 'standart', 'IK10204180'),
(858, 876, 14.50, 5.00, '2024-10-10 13:55:05', 'standart', 'IK10204181'),
(859, 877, 6.00, 5.00, '2024-10-10 13:57:26', 'standart', 'IK10204182'),
(860, 878, 16.25, 5.00, '2024-10-10 16:16:44', 'standart', 'IK10204183'),
(861, 879, 17.45, 5.00, '2024-10-10 17:29:31', 'standart', 'IK10204185'),
(862, 880, 7.75, 5.00, '2024-10-10 17:35:18', 'standart', 'IK10204184'),
(863, 881, 73.75, 5.00, '2024-10-14 09:59:51', 'standart', 'IK10204186'),
(864, 882, 72.35, 5.00, '2024-12-11 15:32:07', 'standart', 'IK10204188'),
(865, 883, 45.00, 5.00, '2024-12-03 16:11:31', 'standart', 'IK10204191'),
(866, 884, 18.25, 5.00, '2025-02-25 17:56:01', 'standart', 'IK10204192'),
(867, 885, 11.50, 5.00, '2024-12-12 17:11:34', 'standart', 'IK10204189'),
(868, 886, 62.45, 5.00, '2025-02-24 18:24:19', 'standart', 'IK10204193'),
(869, 887, 39.25, 5.00, '2024-10-29 17:29:50', 'standart', 'IK10204194'),
(870, 888, 28.25, 5.00, '2025-01-15 13:13:51', 'standart', 'IK10204195'),
(871, 889, 26.25, 5.00, '2025-02-01 13:52:03', 'standart', 'IK10204196'),
(872, 890, 12.50, 5.00, '2024-10-31 19:44:14', 'standart', 'IK10204197'),
(873, 891, 0.00, 5.00, NULL, 'standart', 'IK10204198'),
(874, 892, 13.25, 5.00, '2024-11-05 18:18:45', 'standart', 'IK10204199'),
(875, 893, 1.50, 5.00, '2024-11-06 12:52:17', 'standart', 'IK10204200'),
(876, 894, 19.50, 5.00, '2024-11-06 17:07:21', 'standart', 'IK10204201'),
(877, 895, 8.00, 5.00, '2024-11-09 19:21:17', 'standart', 'IK10204202'),
(878, 896, 25.00, 5.00, '2024-11-09 19:28:37', 'standart', 'IK10204203'),
(879, 897, 0.00, 5.00, NULL, 'standart', '1'),
(880, 898, 26.25, 5.00, '2025-01-18 12:57:51', 'standart', 'IK10204205'),
(881, 899, 21.25, 5.00, '2024-11-12 12:35:02', 'standart', 'IK10204206'),
(882, 900, 21.25, 5.00, '2025-01-02 18:01:17', 'standart', 'IK10204207'),
(883, 901, 23.75, 5.00, '2024-11-20 17:02:15', 'standart', 'IK10204208'),
(884, 902, 8.75, 5.00, '2024-12-04 16:29:36', 'standart', 'IK10204210'),
(885, 903, 17.25, 5.00, '2025-02-12 18:23:41', 'standart', 'IK10204209'),
(886, 904, 22.50, 5.00, '2024-11-22 15:54:38', 'standart', 'IK10204211'),
(887, 905, 28.75, 5.00, '2024-11-26 18:45:22', 'standart', 'IK10204212'),
(888, 906, 12.75, 5.00, '2024-11-26 15:43:21', 'standart', 'IK10204213'),
(889, 907, 24.00, 5.00, '2024-11-27 17:34:17', 'standart', 'IK10204214'),
(890, 908, 6.45, 5.00, '2025-02-27 16:00:15', 'standart', 'IK10204215'),
(891, 909, 18.25, 5.00, '2025-02-10 14:41:57', 'standart', 'IK10204216'),
(892, 910, 17.75, 5.00, '2024-12-19 13:49:16', 'standart', 'IK10204218'),
(893, 911, 37.00, 5.00, '2025-02-17 12:34:10', 'standart', 'IK10204219'),
(894, 912, 0.00, 5.00, NULL, 'standart', 'IK10204300'),
(895, 913, 8.45, 5.00, '2024-12-21 17:21:06', 'standart', 'IK10204301'),
(896, 914, 32.00, 5.00, '2024-12-17 17:28:07', 'standart', 'IK10204302'),
(897, 915, 15.18, 5.00, '2025-02-22 15:53:16', 'standart', 'IK10204304'),
(898, 916, 3.75, 5.00, '2025-02-03 13:44:07', 'standart', 'IK10204307'),
(899, 917, 14.75, 5.00, '2024-12-23 18:49:12', 'standart', 'IK10204305'),
(900, 918, 13.50, 5.00, '2024-12-24 13:39:35', 'standart', 'IK10204303'),
(901, 919, 62.40, 5.00, '2025-02-10 18:43:56', 'standart', 'IK10204306'),
(902, 920, 0.00, 5.00, NULL, 'standart', 'IK10204309'),
(903, 921, 12.00, 5.00, '2024-12-25 17:40:02', 'standart', 'IK10204308'),
(904, 922, 3.00, 5.00, '2024-12-29 17:19:42', 'standart', 'IK10204314'),
(905, 923, 46.95, 5.00, '2025-02-26 17:57:48', 'standart', 'IK10204313'),
(906, 924, 19.40, 5.00, '2025-02-09 16:13:20', 'standart', 'IK10204312'),
(907, 925, 44.25, 5.00, '2025-01-01 15:46:38', 'standart', 'IK10204315'),
(908, 926, 49.25, 5.00, '2025-01-15 17:45:37', 'standart', 'IK10204317'),
(909, 927, 15.00, 5.00, '2025-01-08 18:27:15', 'standart', 'IK10204318'),
(910, 928, 110.00, 5.00, '2025-01-11 11:09:31', 'standart', 'IK10204319'),
(911, 929, 128.75, 5.00, '2025-02-15 17:16:08', 'standart', 'IK10204323'),
(912, 930, 15.00, 5.00, '2025-01-11 16:29:51', 'standart', 'IK10204321'),
(913, 931, 0.00, 5.00, NULL, 'standart', 'IK10204322'),
(10204320, 10204320, 0.00, 0.00, '2025-01-21 18:32:26', 'standart', 'IK10204320'),
(10204321, 10204321, 89.75, 5.00, '2025-01-18 16:13:43', 'standart', 'IK10204324'),
(10204322, 10204322, 1.75, 5.00, '2025-01-18 18:37:33', 'standart', 'IK10204272'),
(10204323, 10204323, 12.25, 5.00, '2025-02-02 18:43:51', 'standart', 'IK10204273'),
(10204324, 10204324, 11.00, 5.00, '2025-01-24 12:24:31', 'standart', 'IK10204062'),
(10204325, 10204325, 109.00, 5.00, '2025-01-25 11:55:25', 'standart', 'IK10204061'),
(10204326, 10204326, 24.95, 5.00, '2025-01-27 13:49:09', 'standart', 'IK10204060'),
(10204327, 10204327, 37.90, 5.00, '2025-01-27 14:01:47', 'standart', 'IK10204129'),
(10204328, 10204328, 17.20, 5.00, '2025-01-27 14:06:56', 'standart', 'IK10204128'),
(10204329, 10204329, 22.50, 5.00, '2025-01-28 17:41:44', 'standart', 'IK10204127'),
(10204330, 10204330, 2.75, 5.00, '2025-01-29 13:28:16', 'standart', 'IK10204126'),
(10204331, 10204331, 21.75, 5.00, '2025-01-31 13:42:58', 'standart', 'IK10204125'),
(10204332, 10204332, 21.25, 5.00, '2025-01-31 15:18:47', 'standart', 'IK10204124'),
(10204333, 10204333, 102.00, 5.00, '2025-02-20 18:11:33', 'standart', 'IK10204123'),
(10204334, 10204334, 32.25, 5.00, '2025-02-01 17:45:19', 'standart', 'IK10204122'),
(10204335, 10204335, 36.25, 5.00, '2025-02-02 13:01:31', 'standart', 'IK10204121'),
(10204336, 10204336, 6.25, 5.00, '2025-02-02 16:13:15', 'standart', 'IK10204119'),
(10204337, 10204337, 16.75, 5.00, '2025-02-05 15:00:11', 'standart', 'IK10204120'),
(10204338, 10204338, 29.70, 5.00, '2025-02-24 14:13:34', 'standart', 'IK10204118'),
(10204339, 10204339, 31.50, 5.00, '2025-02-04 20:35:23', 'standart', 'IK10204116'),
(10204340, 10204340, 5.00, 5.00, '2025-02-03 18:32:44', 'standart', 'IK10204117'),
(10204341, 10204341, 41.25, 5.00, '2025-02-03 19:37:14', 'standart', 'IK10204115'),
(10204342, 10204342, 24.25, 5.00, '2025-02-03 19:46:39', 'standart', 'IK10204112'),
(10204343, 10204343, 32.50, 5.00, '2025-02-09 18:04:16', 'standart', 'IK10204113'),
(10204344, 10204344, 11.25, 5.00, '2025-02-04 17:30:57', 'standart', 'IK10204114'),
(10204345, 10204345, 0.25, 5.00, '2025-02-04 17:41:36', 'standart', 'IK10204111'),
(10204346, 10204346, 14.00, 5.00, '2025-02-04 19:25:07', 'standart', 'IK10204110'),
(10204348, 10204348, 11.25, 5.00, '2025-02-05 14:51:50', 'standart', 'IK10204279'),
(10204349, 10204349, 29.00, 5.00, '2025-02-05 15:38:07', 'standart', 'IK10204278'),
(10204350, 10204350, 17.25, 5.00, '2025-02-05 16:21:34', 'standart', 'IK10204277'),
(10204351, 10204351, 19.75, 5.00, '2025-02-05 17:47:23', 'standart', 'IK10204276'),
(10204352, 10204352, 22.25, 5.00, '2025-02-05 19:08:18', 'standart', 'IK10204275'),
(10204353, 10204353, 18.75, 5.00, '2025-02-05 19:10:02', 'standart', 'IK10204274'),
(10204354, 10204354, 16.00, 5.00, '2025-02-05 19:35:15', 'standart', 'IK10204740'),
(10204355, 10204355, 76.25, 5.00, '2025-02-06 15:55:29', 'standart', 'IK10204741'),
(10204356, 10204356, 27.50, 5.00, '2025-02-18 17:27:59', 'standart', 'IK10204742'),
(10204357, 10204357, 23.05, 5.00, '2025-02-20 17:44:05', 'standart', 'IK10204743'),
(10204358, 10204358, 10.50, 5.00, '2025-02-09 13:13:10', 'standart', 'IK10204629'),
(10204359, 10204359, 30.25, 5.00, '2025-02-09 15:20:31', 'standart', 'IK10204630'),
(10204360, 10204360, 10.75, 5.00, '2025-02-09 19:34:59', 'standart', 'IK10204631'),
(10204361, 10204361, 42.50, 5.00, '2025-02-25 15:04:57', 'standart', 'IK10204632'),
(10204362, 10204362, 12.45, 5.00, '2025-02-10 15:15:22', 'standart', 'IK10204633'),
(10204363, 10204363, 32.50, 5.00, '2025-02-10 16:28:38', 'standart', 'IK10204634'),
(10204364, 10204364, 36.50, 5.00, '2025-02-11 16:36:13', 'standart', 'IK10204245'),
(10204365, 10204365, 19.75, 5.00, '2025-02-12 13:49:18', 'standart', 'IK10204246'),
(10204366, 10204366, 15.75, 5.00, '2025-02-12 16:31:06', 'standart', 'IK10204247'),
(10204367, 10204367, 11.50, 5.00, '2025-02-12 17:57:07', 'standart', 'IK10204248'),
(10204368, 10204368, 6.50, 5.00, '2025-02-12 18:01:32', 'standart', 'IK10204249'),
(10204369, 10204369, 29.25, 5.00, '2025-02-14 15:20:00', 'standart', 'IK10204649'),
(10204370, 10204370, 21.50, 5.00, '2025-02-15 13:23:31', 'standart', 'IK10204647'),
(10204371, 10204371, 22.00, 5.00, '2025-02-15 13:24:58', 'standart', 'IK10204648'),
(10204372, 10204372, 126.45, 5.00, '2025-02-16 14:32:18', 'standart', 'IK10204646'),
(10204373, 10204373, 25.00, 5.00, '2025-02-16 16:03:00', 'standart', 'IK10204659'),
(10204374, 10204374, 16.25, 5.00, '2025-02-16 16:35:51', 'standart', 'IK10204657'),
(10204375, 10204375, 70.00, 5.00, '2025-02-16 18:30:55', 'standart', 'IK10204658'),
(10204376, 10204376, 0.00, 5.00, NULL, 'standart', 'IK10204645'),
(10204377, 10204377, 42.50, 5.00, '2025-02-18 15:55:25', 'standart', 'IK10204644'),
(10204378, 10204378, 12.25, 5.00, '2025-02-20 17:56:15', 'standart', 'IK10204655'),
(10204379, 10204379, 17.50, 5.00, '2025-02-20 18:01:56', 'standart', 'IK10204654'),
(10204380, 10204380, 4.50, 5.00, '2025-02-24 16:25:04', 'standart', 'IK10204642'),
(10204381, 10204381, 3.75, 5.00, '2025-02-24 13:56:47', 'standart', 'IK10204641'),
(10204382, 10204382, 18.50, 5.00, '2025-02-26 17:42:28', 'standart', 'IK10204643'),
(10204383, 10204383, 10.50, 5.00, '2025-02-28 12:46:51', 'standart', 'IK10204640'),
(10204384, 10204384, 73.00, 5.00, '2025-02-28 12:50:22', 'standart', 'IK10204639'),
(10204385, 10204385, 11.25, 5.00, '2025-02-28 15:06:18', 'standart', 'IK10204638'),
(10204386, 10204386, 7.25, 5.00, '2025-02-28 16:26:56', 'standart', 'IK10204637');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `personel`
--

CREATE TABLE `personel` (
  `id` int(11) NOT NULL,
  `ad` varchar(100) DEFAULT NULL,
  `no` varchar(20) DEFAULT NULL,
  `kullanici_adi` varchar(50) DEFAULT NULL,
  `sifre` varchar(255) DEFAULT NULL,
  `telefon_no` varchar(15) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `yetki_seviyesi` enum('kasiyer','mudur_yardimcisi','mudur') DEFAULT NULL,
  `durum` enum('aktif','pasif') DEFAULT 'aktif',
  `kayit_tarihi` datetime DEFAULT current_timestamp(),
  `son_giris` datetime DEFAULT NULL,
  `magaza_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `personel`
--

INSERT INTO `personel` (`id`, `ad`, `no`, `kullanici_adi`, `sifre`, `telefon_no`, `email`, `yetki_seviyesi`, `durum`, `kayit_tarihi`, `son_giris`, `magaza_id`) VALUES
(1, 'Ahmet Yılmaz', 'P001', 'ahmet', '$2y$10$zQDDmeksqcPhn9Br.cGVfunnt8w8Di8jFlMYP5D1XdRTn293br3jO', '5551234567', 'ahmet@example.com', 'kasiyer', 'aktif', '2024-12-20 17:18:04', NULL, 6),
(2, 'Mehmet Demir', 'P002', 'mehmet', '$2y$10$zQDDmeksqcPhn9Br.cGVfunnt8w8Di8jFlMYP5D1XdRTn293br3jO', '5551234568', 'mehmet@example.com', 'kasiyer', 'aktif', '2024-12-20 17:18:04', NULL, 7);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `puan_ayarlari`
--

CREATE TABLE `puan_ayarlari` (
  `id` int(11) NOT NULL,
  `musteri_turu` enum('standart','gold','platinum') NOT NULL,
  `puan_oran` decimal(5,2) NOT NULL COMMENT 'TL başına kazanılan puan',
  `min_harcama` decimal(10,2) DEFAULT 0.00 COMMENT 'Bu seviye için minimum harcama',
  `guncelleme_tarihi` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

--
-- Tablo döküm verisi `puan_ayarlari`
--

INSERT INTO `puan_ayarlari` (`id`, `musteri_turu`, `puan_oran`, `min_harcama`, `guncelleme_tarihi`) VALUES
(1, 'standart', 1.00, 0.00, '2025-02-28 15:20:43'),
(2, 'gold', 1.50, 5000.00, '2025-02-28 15:20:43'),
(3, 'platinum', 2.00, 10000.00, '2025-02-28 15:20:43');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `puan_harcama`
--

CREATE TABLE `puan_harcama` (
  `id` int(11) NOT NULL,
  `fatura_id` int(11) NOT NULL,
  `musteri_id` int(11) NOT NULL,
  `harcanan_puan` decimal(10,2) NOT NULL,
  `tarih` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

--
-- Tablo döküm verisi `puan_harcama`
--

INSERT INTO `puan_harcama` (`id`, `fatura_id`, `musteri_id`, `harcanan_puan`, `tarih`) VALUES
(1, 43, 147, 123.00, '2025-03-04 15:32:14'),
(4, 48, 147, 180.00, '2025-03-04 19:37:22'),
(5, 54, 147, 1262.00, '2025-03-07 00:56:40');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `puan_kazanma`
--

CREATE TABLE `puan_kazanma` (
  `id` int(11) NOT NULL,
  `fatura_id` int(11) NOT NULL,
  `musteri_id` int(11) NOT NULL,
  `kazanilan_puan` decimal(10,2) NOT NULL,
  `odeme_tutari` decimal(10,2) NOT NULL,
  `tarih` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

--
-- Tablo döküm verisi `puan_kazanma`
--

INSERT INTO `puan_kazanma` (`id`, `fatura_id`, `musteri_id`, `kazanilan_puan`, `odeme_tutari`, `tarih`) VALUES
(1, 42, 147, 615.00, 123.00, '2025-03-04 15:21:01'),
(2, 44, 147, 615.00, 123.00, '2025-03-04 15:33:32'),
(3, 45, 147, 615.00, 123.00, '2025-03-04 15:34:52'),
(6, 51, 147, 615.00, 123.00, '2025-03-05 11:51:22'),
(7, 52, 147, 550.00, 110.00, '2025-03-05 12:48:51'),
(8, 54, 147, 2.50, 0.50, '2025-03-07 00:56:40'),
(9, 56, 147, 4.85, 97.00, '2025-03-08 14:07:03'),
(10, 57, 147, 6.88, 137.50, '2025-03-08 14:08:14'),
(11, 58, 147, 1.50, 12.50, '2025-03-09 12:58:51');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `satis_faturalari`
--

CREATE TABLE `satis_faturalari` (
  `id` int(11) NOT NULL,
  `fatura_turu` varchar(50) DEFAULT NULL,
  `magaza` int(11) DEFAULT NULL,
  `fatura_seri` varchar(20) DEFAULT NULL,
  `fatura_no` varchar(20) DEFAULT NULL,
  `fatura_tarihi` datetime DEFAULT NULL,
  `toplam_tutar` decimal(10,2) DEFAULT NULL,
  `personel` int(11) DEFAULT NULL,
  `musteri` int(11) DEFAULT NULL,
  `kdv_tutari` decimal(10,2) DEFAULT NULL,
  `indirim_tutari` decimal(10,2) DEFAULT NULL,
  `net_tutar` decimal(10,2) DEFAULT NULL,
  `odeme_turu` enum('nakit','kredi_karti','havale') DEFAULT NULL,
  `islem_turu` enum('satis','iade') DEFAULT NULL,
  `aciklama` text DEFAULT NULL,
  `kredi_karti_banka` enum('Ziraat','İş Bankası','Garanti','Yapı Kredi','Akbank','Vakıfbank','QNB','Halkbank','Denizbank','TEB','Şekerbank','ING','HSBC') DEFAULT NULL,
  `musteri_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `satis_faturalari`
--

INSERT INTO `satis_faturalari` (`id`, `fatura_turu`, `magaza`, `fatura_seri`, `fatura_no`, `fatura_tarihi`, `toplam_tutar`, `personel`, `musteri`, `kdv_tutari`, `indirim_tutari`, `net_tutar`, `odeme_turu`, `islem_turu`, `aciklama`, `kredi_karti_banka`, `musteri_id`) VALUES
(1, 'perakende', 6, 'A', '0001', '2024-06-20 00:00:00', 397.50, 1, 1, 60.64, NULL, 336.86, 'nakit', 'satis', 'Açıklama 1', NULL, NULL),
(2, 'toptan', 7, 'B', '0002', '2024-06-21 00:00:00', 530.00, 2, 2, 80.85, NULL, 449.15, 'kredi_karti', 'satis', 'Açıklama 2', 'Ziraat', NULL),
(3, 'perakende', 6, 'C', '0003', '2024-06-22 00:00:00', 405.00, 1, 2, 61.78, NULL, 343.22, 'havale', 'satis', 'Açıklama 3', 'Garanti', NULL),
(4, 'toptan', 7, 'D', '0004', '2024-06-23 00:00:00', 462.50, 2, 1, 70.55, NULL, 391.95, 'nakit', 'satis', 'Açıklama 4', NULL, NULL),
(5, 'perakende', 6, 'E', '0005', '2024-06-24 00:00:00', 330.00, 1, 2, 50.34, NULL, 279.66, 'kredi_karti', 'satis', 'Açıklama 5', 'Yapı Kredi', NULL),
(42, 'perakende', 7, 'POS', '250304-7867', '2025-03-04 00:00:00', 123.00, 1, 147, 0.00, 0.00, 123.00, 'nakit', 'satis', 'POS satış sistemi', NULL, 147),
(43, 'perakende', 7, 'POS', '250304-3403', '2025-03-04 00:00:00', 0.00, 1, 147, 0.00, 0.00, 0.00, 'nakit', 'satis', 'POS satış sistemi', NULL, 147),
(44, 'perakende', 7, 'POS', '250304-1106', '2025-03-04 00:00:00', 123.00, 1, 147, 0.00, 0.00, 123.00, 'nakit', 'satis', 'POS satış sistemi', NULL, 147),
(45, 'perakende', 7, 'POS', '250304-9944', '2025-03-04 00:00:00', 123.00, 1, 147, 0.00, 0.00, 123.00, 'nakit', 'satis', 'POS satış sistemi', NULL, 147),
(48, 'perakende', 7, 'POS', '250304-2079', '2025-03-04 00:00:00', 0.00, 1, 147, 27.46, 0.00, -27.46, 'nakit', 'satis', 'POS satış sistemi', NULL, 147),
(51, 'perakende', 7, 'POS', '250305-9057', '2025-03-05 00:00:00', 123.00, 1, 147, 0.00, 0.00, 123.00, '', 'satis', 'POS satış sistemi', NULL, 147),
(52, 'perakende', 6, 'POS', '250305-1744', '2025-03-05 00:00:00', 110.00, 1, 147, 10.62, 2.50, 99.38, 'nakit', 'satis', 'POS satış sistemi', NULL, 147),
(53, 'perakende', 7, 'POS', '250305-2507', '2025-03-05 00:00:00', 120.00, 1, NULL, 10.91, 0.00, 109.09, 'nakit', 'satis', 'POS satış sistemi', NULL, NULL),
(54, 'perakende', 7, 'POS', '250307-3522', '2025-03-07 00:00:00', 0.50, 1, 147, 192.58, 0.00, -192.08, '', 'satis', 'POS satış sistemi - Borç olarak güncellenmiştir - Borç olarak güncellenmiştir - Borç olarak güncellenmiştir', NULL, 147),
(55, 'perakende', 7, 'POS', '250308-1227', '2025-03-08 00:00:00', 32.00, 1, NULL, 4.88, 0.00, 27.12, 'nakit', 'satis', 'POS satış sistemi', NULL, NULL),
(56, 'perakende', 7, 'POS', '250308-9716', '2025-03-08 00:00:00', 97.00, 1, 147, 14.80, 0.00, 82.20, 'kredi_karti', 'satis', 'POS satış sistemi', 'Ziraat', 147),
(57, 'perakende', 7, 'POS', '250308-6915', '2025-03-08 00:00:00', 137.50, 1, 147, 20.97, 0.00, 116.53, 'kredi_karti', 'satis', 'POS satış sistemi', 'Ziraat', 147),
(58, 'perakende', 7, 'POS', '250309-5814', '2025-03-09 12:58:51', 12.50, 1, 147, 1.91, 0.00, 10.59, 'nakit', 'satis', 'POS satış sistemi', NULL, 147),
(59, 'perakende', 7, 'POS', '250309-4448', '2025-03-09 13:19:14', 11.25, 1, NULL, 1.72, 1.25, 9.53, 'kredi_karti', 'satis', 'POS satış sistemi', 'Ziraat', NULL),
(60, 'perakende', 7, 'POS', '250309-9699', '2025-03-09 20:51:42', 123.00, 1, NULL, 0.00, 0.00, 123.00, 'kredi_karti', 'satis', 'POS satış sistemi', 'Ziraat', NULL),
(61, 'perakende', 7, 'POS', '250309-3047', '2025-03-09 20:56:59', 123.00, 1, NULL, 0.00, 0.00, 123.00, 'kredi_karti', 'satis', 'POS satış sistemi', 'Ziraat', NULL),
(62, 'perakende', 7, 'POS', '250309-6023', '2025-03-09 20:58:01', 123.00, 1, NULL, 0.00, 0.00, 123.00, 'kredi_karti', 'satis', 'POS satış sistemi', 'Ziraat', NULL),
(63, 'perakende', 7, 'POS', '250309-8103', '2025-03-09 21:00:37', 123.00, 1, NULL, 0.00, 0.00, 123.00, 'nakit', 'satis', 'POS satış sistemi', NULL, NULL),
(64, 'perakende', 7, 'POS', '250309-0040', '2025-03-09 21:16:43', 123.00, 1, NULL, 0.00, 0.00, 123.00, 'kredi_karti', 'satis', 'POS satış sistemi', 'Ziraat', NULL),
(65, 'perakende', 7, 'POS', '250309-1273', '2025-03-09 21:30:06', 123.00, 1, NULL, 0.00, 0.00, 123.00, 'nakit', 'satis', 'POS satış sistemi', NULL, NULL),
(66, 'perakende', 7, 'POS', '250309-7569', '2025-03-09 21:30:17', 123.00, 1, NULL, 0.00, 0.00, 123.00, 'kredi_karti', 'satis', 'POS satış sistemi', 'Ziraat', NULL),
(67, 'perakende', 7, 'POS', '250309-4472', '2025-03-09 21:34:32', 123.00, 1, NULL, 0.00, 0.00, 123.00, 'kredi_karti', 'satis', 'POS satış sistemi', 'Ziraat', NULL),
(68, 'perakende', 7, 'POS', '250309-0742', '2025-03-09 21:36:38', 97.50, 1, NULL, 14.87, 0.00, 82.63, 'kredi_karti', 'satis', 'POS satış sistemi', 'Ziraat', NULL),
(69, 'perakende', 7, 'POS', '250309-6581', '2025-03-09 21:37:07', 12.50, 1, NULL, 1.91, 0.00, 10.59, 'kredi_karti', 'satis', 'POS satış sistemi', 'Ziraat', NULL),
(70, 'perakende', 7, 'POS', '250309-1837', '2025-03-09 21:55:08', 12.50, 1, NULL, 1.91, 0.00, 10.59, 'kredi_karti', 'satis', 'POS satış sistemi', 'Ziraat', NULL);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `satis_fatura_detay`
--

CREATE TABLE `satis_fatura_detay` (
  `id` int(11) NOT NULL,
  `fatura_id` int(11) DEFAULT NULL,
  `urun_id` int(11) DEFAULT NULL,
  `miktar` int(11) DEFAULT NULL,
  `birim_fiyat` decimal(10,2) DEFAULT NULL,
  `kdv_orani` decimal(5,2) DEFAULT NULL,
  `indirim_orani` decimal(5,2) DEFAULT NULL,
  `toplam_tutar` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `satis_fatura_detay`
--

INSERT INTO `satis_fatura_detay` (`id`, `fatura_id`, `urun_id`, `miktar`, `birim_fiyat`, `kdv_orani`, `indirim_orani`, `toplam_tutar`) VALUES
(175, 42, 1111, 1, 123.00, 0.00, 0.00, 123.00),
(176, 43, 1111, 1, 123.00, 0.00, 0.00, 123.00),
(177, 44, 1111, 1, 123.00, 0.00, 0.00, 123.00),
(178, 45, 1111, 1, 123.00, 0.00, 0.00, 123.00),
(181, 48, 1062, 1, 180.00, 18.00, 0.00, 180.00),
(184, 51, 1111, 1, 123.00, 0.00, 0.00, 123.00),
(185, 52, 1059, 1, 12.50, 18.00, 20.00, 10.00),
(186, 52, 1113, 1, 100.00, 10.00, 0.00, 100.00),
(187, 53, 1113, 1, 120.00, 10.00, 0.00, 120.00),
(188, 54, 1059, 101, 12.50, 18.00, 0.00, 1262.50),
(189, 55, 1075, 1, 32.00, 18.00, 0.00, 32.00),
(190, 56, 1075, 1, 32.00, 18.00, 0.00, 32.00),
(191, 56, 1070, 1, 65.00, 18.00, 0.00, 65.00),
(192, 57, 1059, 11, 12.50, 18.00, 0.00, 137.50),
(193, 58, 1059, 1, 12.50, 18.00, 0.00, 12.50),
(194, 59, 1059, 1, 12.50, 18.00, 10.00, 11.25),
(195, 60, 1111, 1, 123.00, 0.00, 0.00, 123.00),
(196, 61, 1111, 1, 123.00, 0.00, 0.00, 123.00),
(197, 62, 1111, 1, 123.00, 0.00, 0.00, 123.00),
(198, 63, 1111, 1, 123.00, 0.00, 0.00, 123.00),
(199, 64, 1111, 1, 123.00, 0.00, 0.00, 123.00),
(200, 65, 1111, 1, 123.00, 0.00, 0.00, 123.00),
(201, 66, 1111, 1, 123.00, 0.00, 0.00, 123.00),
(202, 67, 1111, 1, 123.00, 0.00, 0.00, 123.00),
(203, 68, 1066, 1, 85.00, 18.00, 0.00, 85.00),
(204, 68, 1059, 1, 12.50, 18.00, 0.00, 12.50),
(205, 69, 1059, 1, 12.50, 18.00, 0.00, 12.50),
(206, 70, 1059, 1, 12.50, 18.00, 0.00, 12.50);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `sistem_ayarlari`
--

CREATE TABLE `sistem_ayarlari` (
  `id` int(11) NOT NULL,
  `anahtar` varchar(50) NOT NULL,
  `deger` text DEFAULT NULL,
  `aciklama` varchar(255) DEFAULT NULL,
  `guncelleme_tarihi` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `sistem_ayarlari`
--

INSERT INTO `sistem_ayarlari` (`id`, `anahtar`, `deger`, `aciklama`, `guncelleme_tarihi`) VALUES
(8, 'varsayilan_stok_lokasyonu', 'magaza_7', NULL, '2025-01-20 12:18:23'),
(10, 'urun_kisayollari', '[{\"position\":0,\"product_id\":1070,\"product\":{\"id\":1070,\"ad\":\"Tombow MONO Zero Silgi 2.3mm\",\"barkod\":\"8680001012\",\"satis_fiyati\":\"65.00\"}},{\"position\":1,\"product_id\":1111,\"product\":{\"id\":1111,\"ad\":\"123\",\"barkod\":\"868747885\",\"satis_fiyati\":\"123.00\"}},{\"position\":2,\"product_id\":1095,\"product\":{\"id\":1095,\"ad\":\"Faber Castell Polychromos 12li Set\",\"barkod\":\"8680001037\",\"satis_fiyati\":\"485.00\"}},{\"position\":3,\"product_id\":1079,\"product\":{\"id\":1079,\"ad\":\"Faber Castell Ke\\u00e7eli Kalem 24l\\u00fc Set\",\"barkod\":\"8680001021\",\"satis_fiyati\":\"220.00\"}}]', 'POS kısayol butonları', '2025-03-09 20:47:44');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `stok_hareketleri`
--

CREATE TABLE `stok_hareketleri` (
  `id` int(11) NOT NULL,
  `urun_id` int(11) NOT NULL,
  `miktar` decimal(10,2) NOT NULL,
  `hareket_tipi` enum('giris','cikis') NOT NULL,
  `aciklama` varchar(255) DEFAULT NULL,
  `belge_no` varchar(50) DEFAULT NULL,
  `tarih` datetime NOT NULL,
  `kullanici_id` int(11) DEFAULT NULL,
  `magaza_id` int(11) DEFAULT NULL,
  `depo_id` int(11) DEFAULT NULL,
  `maliyet` decimal(10,2) DEFAULT NULL,
  `satis_fiyati` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `stok_hareketleri`
--

INSERT INTO `stok_hareketleri` (`id`, `urun_id`, `miktar`, `hareket_tipi`, `aciklama`, `belge_no`, `tarih`, `kullanici_id`, `magaza_id`, `depo_id`, `maliyet`, `satis_fiyati`) VALUES
(195, 1105, 5.00, 'giris', 'Manuel stok girişi', NULL, '2025-01-30 10:39:33', 1, 7, NULL, 125.00, 125.00),
(196, 1059, 50.00, 'giris', 'Manuel stok girişi', NULL, '2025-01-30 10:43:04', 1, 7, NULL, 12.50, 12.50),
(197, 1059, 20.00, 'giris', 'Manuel stok girişi', NULL, '2025-01-30 10:43:33', 1, 6, NULL, 12.50, 12.50),
(198, 1059, 30.00, 'giris', 'Manuel stok girişi', NULL, '2025-01-30 10:43:55', 1, NULL, 1, 12.50, 12.50),
(199, 1059, 1.00, 'giris', 'Manuel stok girişi', NULL, '2025-01-30 10:44:00', 1, 6, NULL, 12.50, 12.50),
(200, 1106, 1.00, 'giris', 'İlk stok girişi', NULL, '2025-01-30 10:45:29', 1, NULL, 1, 10.00, 20.00),
(201, 1106, 3.00, 'giris', 'Manuel stok girişi', NULL, '2025-01-30 11:08:37', 1, 7, NULL, 10.00, 20.00),
(202, 1064, 3.00, 'giris', 'Manuel stok girişi', NULL, '2025-02-24 12:12:53', 1, 6, NULL, 45.00, 45.00),
(203, 1110, 5.00, 'giris', 'Manuel stok girişi', NULL, '2025-02-25 11:06:12', 1, 6, NULL, 2.00, 2.00),
(204, 1110, 1.00, 'giris', 'Manuel stok girişi', NULL, '2025-02-26 14:39:43', 1, NULL, 1, 2.00, 2.40),
(210, 1105, 1.00, 'giris', 'Faturadan mağazaya aktarım', NULL, '2025-02-26 14:51:30', 1, 7, NULL, 78.00, 128.70),
(211, 1105, 1.00, 'giris', 'Faturadan mağazaya aktarım', NULL, '2025-02-26 14:56:02', 1, 6, NULL, 78.00, 128.70),
(212, 1105, 1.00, 'giris', 'Faturadan mağazaya aktarım', NULL, '2025-02-26 15:01:31', 1, 6, NULL, 78.00, 129.48),
(217, 1111, 1.00, 'cikis', 'POS satış', '250304-7867', '2025-03-04 15:21:01', 1, 7, NULL, NULL, 123.00),
(218, 1111, 1.00, 'cikis', 'POS satış', '250304-3403', '2025-03-04 15:32:14', 1, 7, NULL, NULL, 123.00),
(219, 1111, 1.00, 'cikis', 'POS satış', '250304-1106', '2025-03-04 15:33:32', 1, 7, NULL, NULL, 123.00),
(220, 1111, 1.00, 'cikis', 'POS satış', '250304-9944', '2025-03-04 15:34:52', 1, 7, NULL, NULL, 123.00),
(223, 1062, 1.00, 'cikis', 'POS satış', '250304-2079', '2025-03-04 19:37:22', 1, 7, NULL, NULL, 180.00),
(226, 1111, 1.00, 'cikis', 'POS satış', '250305-9057', '2025-03-05 11:51:22', 1, 7, NULL, NULL, 123.00),
(227, 1113, 5.00, 'giris', 'Manuel stok girişi', NULL, '2025-03-05 12:48:41', 1, 6, NULL, 100.00, 120.00),
(228, 1059, 1.00, 'cikis', 'POS satış', '250305-1744', '2025-03-05 12:48:51', 1, 6, NULL, NULL, 12.50),
(229, 1113, 1.00, 'cikis', 'POS satış', '250305-1744', '2025-03-05 12:48:51', 1, 6, NULL, NULL, 100.00),
(230, 1113, 1.00, 'cikis', 'POS satış', '250305-2507', '2025-03-05 12:49:19', 1, 7, NULL, NULL, 120.00),
(231, 1113, 5.00, 'giris', 'Manuel stok girişi', NULL, '2025-03-05 12:51:21', 1, 6, NULL, 100.00, 120.00),
(232, 1059, 101.00, 'cikis', 'POS satış', '250307-3522', '2025-03-07 00:56:40', 1, 7, NULL, NULL, 12.50),
(233, 1075, 1.00, 'cikis', 'POS satış', '250308-1227', '2025-03-08 12:27:15', 1, 7, NULL, NULL, 32.00),
(234, 1075, 1.00, 'cikis', 'POS satış', '250308-9716', '2025-03-08 14:07:03', 1, 7, NULL, NULL, 32.00),
(235, 1070, 1.00, 'cikis', 'POS satış', '250308-9716', '2025-03-08 14:07:03', 1, 7, NULL, NULL, 65.00),
(236, 1059, 11.00, 'cikis', 'POS satış', '250308-6915', '2025-03-08 14:08:14', 1, 7, NULL, NULL, 12.50),
(237, 1059, 1.00, 'cikis', 'POS satış', '250309-5814', '2025-03-09 12:58:51', 1, 7, NULL, NULL, 12.50),
(238, 1059, 1.00, 'cikis', 'POS satış', '250309-4448', '2025-03-09 13:19:14', 1, 7, NULL, NULL, 12.50),
(239, 1111, 1.00, 'cikis', 'POS satış', '250309-9699', '2025-03-09 20:51:42', 1, 7, NULL, NULL, 123.00),
(240, 1111, 1.00, 'cikis', 'POS satış', '250309-3047', '2025-03-09 20:56:59', 1, 7, NULL, NULL, 123.00),
(241, 1111, 1.00, 'cikis', 'POS satış', '250309-6023', '2025-03-09 20:58:01', 1, 7, NULL, NULL, 123.00),
(242, 1111, 1.00, 'cikis', 'POS satış', '250309-8103', '2025-03-09 21:00:37', 1, 7, NULL, NULL, 123.00),
(243, 1111, 1.00, 'cikis', 'POS satış', '250309-0040', '2025-03-09 21:16:43', 1, 7, NULL, NULL, 123.00),
(244, 1111, 1.00, 'cikis', 'POS satış', '250309-1273', '2025-03-09 21:30:06', 1, 7, NULL, NULL, 123.00),
(245, 1111, 1.00, 'cikis', 'POS satış', '250309-7569', '2025-03-09 21:30:17', 1, 7, NULL, NULL, 123.00),
(246, 1111, 1.00, 'cikis', 'POS satış', '250309-4472', '2025-03-09 21:34:32', 1, 7, NULL, NULL, 123.00),
(247, 1066, 1.00, 'cikis', 'POS satış', '250309-0742', '2025-03-09 21:36:38', 1, 7, NULL, NULL, 85.00),
(248, 1059, 1.00, 'cikis', 'POS satış', '250309-0742', '2025-03-09 21:36:38', 1, 7, NULL, NULL, 12.50),
(249, 1059, 1.00, 'cikis', 'POS satış', '250309-6581', '2025-03-09 21:37:07', 1, 7, NULL, NULL, 12.50),
(250, 1059, 1.00, 'cikis', 'POS satış', '250309-1837', '2025-03-09 21:55:08', 1, 7, NULL, NULL, 12.50),
(251, 1070, 1.00, 'giris', 'Manuel stok girişi', NULL, '2025-03-09 21:57:27', 1, NULL, 1, 65.00, 78.00),
(252, 1096, 1.00, 'giris', 'Faturadan mağazaya aktarım', NULL, '2025-03-09 21:58:49', 1, 7, NULL, 360.00, 432.00);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `tedarikciler`
--

CREATE TABLE `tedarikciler` (
  `id` int(11) NOT NULL,
  `ad` varchar(100) DEFAULT NULL,
  `telefon` varchar(20) DEFAULT NULL,
  `adres` varchar(255) DEFAULT NULL,
  `sehir` varchar(50) DEFAULT NULL,
  `eposta` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `tedarikciler`
--

INSERT INTO `tedarikciler` (`id`, `ad`, `telefon`, `adres`, `sehir`, `eposta`) VALUES
(7, 'ORCA KIRT.ÖZEL EĞ.TURZ.İNŞ.MÜT.MÜH.NAK.TİC.LTD.ŞTİ', '4522255881', 'AKYAZI MAH.898.SOK. NO:24  \r\n ALTINORDU/ ORDU ', 'ORDU', 'orca_ordu@hotmail.com');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `urun_fiyat_gecmisi`
--

CREATE TABLE `urun_fiyat_gecmisi` (
  `id` int(11) NOT NULL,
  `urun_id` int(11) NOT NULL,
  `islem_tipi` enum('alis','satis_fiyati_guncelleme') NOT NULL,
  `eski_fiyat` decimal(10,2) DEFAULT NULL,
  `yeni_fiyat` decimal(10,2) NOT NULL,
  `fatura_id` int(11) DEFAULT NULL,
  `aciklama` text DEFAULT NULL,
  `tarih` datetime DEFAULT current_timestamp(),
  `kullanici_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `urun_fiyat_gecmisi`
--

INSERT INTO `urun_fiyat_gecmisi` (`id`, `urun_id`, `islem_tipi`, `eski_fiyat`, `yeni_fiyat`, `fatura_id`, `aciklama`, `tarih`, `kullanici_id`) VALUES
(29, 1110, 'satis_fiyati_guncelleme', 2.00, 2.40, NULL, 'Manuel stok girişinde fiyat güncellemesi', '2025-02-26 14:39:43', 1),
(35, 1105, 'satis_fiyati_guncelleme', 125.00, 128.70, 8, 'Faturadan aktarım sırasında fiyat güncelleme', '2025-02-26 14:51:30', 1),
(36, 1105, 'satis_fiyati_guncelleme', 128.70, 129.48, 8, 'Faturadan aktarım sırasında fiyat güncelleme', '2025-02-26 15:01:31', 1),
(37, 1113, 'satis_fiyati_guncelleme', 100.00, 120.00, NULL, 'Manuel stok girişinde fiyat güncellemesi', '2025-03-05 12:48:41', 1),
(38, 1113, 'satis_fiyati_guncelleme', 120.00, 120.00, NULL, 'Manuel stok girişinde fiyat güncellemesi', '2025-03-05 12:51:21', 1),
(39, 1070, 'satis_fiyati_guncelleme', 65.00, 78.00, NULL, 'Manuel stok girişinde fiyat güncellemesi', '2025-03-09 21:57:27', 1),
(40, 1096, 'satis_fiyati_guncelleme', 45.00, 432.00, 11, 'Faturadan aktarım sırasında fiyat güncelleme', '2025-03-09 21:58:49', 1);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `urun_onerileri`
--

CREATE TABLE `urun_onerileri` (
  `id` int(11) NOT NULL,
  `barkod` varchar(50) DEFAULT NULL,
  `ad` varchar(100) DEFAULT NULL,
  `onerilen_tarih` datetime DEFAULT current_timestamp(),
  `durum` enum('beklemede','eklendi','reddedildi') DEFAULT 'beklemede',
  `kullanici_id` int(11) DEFAULT NULL,
  `notlar` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `urun_stok`
--

CREATE TABLE `urun_stok` (
  `id` int(11) NOT NULL,
  `kod` varchar(50) DEFAULT NULL,
  `barkod` varchar(50) DEFAULT NULL,
  `ad` varchar(100) DEFAULT NULL,
  `web_id` varchar(50) DEFAULT NULL,
  `yil` int(11) DEFAULT NULL,
  `kdv_orani` decimal(5,2) DEFAULT NULL,
  `satis_fiyati` decimal(10,2) DEFAULT NULL,
  `alis_fiyati` decimal(10,2) DEFAULT NULL,
  `indirimli_fiyat` decimal(10,2) DEFAULT NULL,
  `stok_miktari` int(11) DEFAULT NULL,
  `kayit_tarihi` date DEFAULT NULL,
  `resim_yolu` varchar(255) DEFAULT NULL,
  `indirim_baslangic_tarihi` date DEFAULT NULL,
  `indirim_bitis_tarihi` date DEFAULT NULL,
  `durum` enum('aktif','pasif') DEFAULT 'aktif',
  `departman_id` int(11) DEFAULT NULL,
  `birim_id` int(11) DEFAULT NULL,
  `ana_grup_id` int(11) DEFAULT NULL,
  `alt_grup_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `urun_stok`
--

INSERT INTO `urun_stok` (`id`, `kod`, `barkod`, `ad`, `web_id`, `yil`, `kdv_orani`, `satis_fiyati`, `alis_fiyati`, `indirimli_fiyat`, `stok_miktari`, `kayit_tarihi`, `resim_yolu`, `indirim_baslangic_tarihi`, `indirim_bitis_tarihi`, `durum`, `departman_id`, `birim_id`, `ana_grup_id`, `alt_grup_id`) VALUES
(1059, 'KRT001', '8680001001', 'Faber Castell Kurşun Kalem 2B', '1001', 2024, 18.00, 12.50, 7.50, NULL, -17, '2024-01-30', NULL, NULL, NULL, 'aktif', 13, 14, 9, 8),
(1060, 'KRT002', '8680001002', 'Stabilo Boss Fosforlu Kalem - Sarı', '1002', 2024, 18.00, 35.00, 22.00, NULL, 80, '2024-01-30', NULL, NULL, NULL, 'aktif', 13, 14, 9, 8),
(1062, 'KRT004', '8680001004', 'Faber Castell 24lü Kuru Boya', '1004', 2024, 18.00, 180.00, 120.00, NULL, 40, '2024-01-30', NULL, NULL, NULL, 'aktif', 13, 14, 9, 8),
(1063, 'KRT005', '8680001005', 'Rotring Versatil Kalem 0.7mm', '1005', 2024, 18.00, 150.00, 95.00, NULL, 60, '2024-01-30', NULL, NULL, NULL, 'aktif', 13, 14, 9, 8),
(1064, 'KRT006', '8680001006', 'Pilot G2 Jel Kalem 0.7mm - Mavi', '1006', 2024, 18.00, 45.00, 28.00, NULL, 3, '2024-01-30', NULL, NULL, NULL, 'aktif', 13, 14, 9, 8),
(1065, 'KRT007', '8680001007', 'Uni-ball Signo Jel Kalem 0.5mm', '1007', 2024, 18.00, 42.00, 26.00, NULL, 100, '2024-01-30', NULL, NULL, NULL, 'aktif', 13, 14, 9, 8),
(1066, 'KRT008', '8680001008', 'Faber Castell Grip 2011 Versatil Kalem', '1008', 2024, 18.00, 85.00, 55.00, NULL, 45, '2024-01-30', NULL, NULL, NULL, 'aktif', 13, 14, 9, 8),
(1067, 'KRT009', '8680001009', 'Pentel Energel Kalem 0.7mm - Siyah', '1009', 2024, 18.00, 38.00, 24.00, NULL, 90, '2024-01-30', NULL, NULL, NULL, 'aktif', 13, 14, 9, 8),
(1068, 'KRT010', '8680001010', 'Stabilo Pen 68 Keçeli Kalem 10lu Set', '1010', 2024, 18.00, 175.00, 110.00, NULL, 30, '2024-01-30', NULL, NULL, NULL, 'aktif', 13, 14, 9, 8),
(1069, 'KRT011', '8680001011', 'Faber Castell 9000 Dereceli Kalem Seti', '1011', 2024, 18.00, 245.00, 155.00, NULL, 20, '2024-01-30', NULL, NULL, NULL, 'aktif', 13, 14, 9, 8),
(1070, 'KRT012', '8680001012', 'Tombow MONO Zero Silgi 2.3mm', '1012', 2024, 18.00, 78.00, 40.00, NULL, 1, '2024-01-30', NULL, NULL, NULL, 'aktif', 13, 14, 9, 8),
(1071, 'KRT013', '8680001013', 'Staedtler Triplus Fineliner 10lu Set', '1013', 2024, 18.00, 195.00, 122.00, NULL, 35, '2024-01-30', NULL, NULL, NULL, 'aktif', 13, 14, 9, 8),
(1072, 'KRT014', '8680001014', 'Rotring 600 Versatil Kalem 0.5mm', '1014', 2024, 18.00, 320.00, 200.00, NULL, 15, '2024-01-30', NULL, NULL, NULL, 'aktif', 13, 14, 9, 8),
(1073, 'KRT015', '8680001015', 'Pilot Frixion Silinebilir Kalem', '1015', 2024, 18.00, 48.00, 30.00, NULL, 85, '2024-01-30', NULL, NULL, NULL, 'aktif', 13, 14, 9, 8),
(1074, 'KRT016', '8680001016', 'Faber Castell Boya Kalemi 36lı Set', '1016', 2024, 18.00, 280.00, 175.00, NULL, 25, '2024-01-30', NULL, NULL, NULL, 'aktif', 13, 14, 9, 8),
(1075, 'KRT017', '8680001017', 'Stabilo Point 88 İnce Uçlu Kalem', '1017', 2024, 18.00, 32.00, 20.00, NULL, 110, '2024-01-30', NULL, NULL, NULL, 'aktif', 13, 14, 9, 8),
(1076, 'KRT018', '8680001018', 'Artline Supreme Metalik Marker', '1018', 2024, 18.00, 55.00, 35.00, NULL, 65, '2024-01-30', NULL, NULL, NULL, 'aktif', 13, 14, 9, 8),
(1077, 'KRT019', '8680001019', 'Pentel Touch Sign Pen', '1019', 2024, 18.00, 42.00, 26.00, NULL, 95, '2024-01-30', NULL, NULL, NULL, 'aktif', 13, 14, 9, 8),
(1078, 'KRT020', '8680001020', 'Uni Kuru Toga Mekanik Kurşun Kalem', '1020', 2024, 18.00, 75.00, 47.00, NULL, 55, '2024-01-30', NULL, NULL, NULL, 'aktif', 13, 14, 9, 8),
(1079, 'KRT021', '8680001021', 'Faber Castell Keçeli Kalem 24lü Set', '1021', 2024, 18.00, 220.00, 138.00, NULL, 30, '2024-01-30', NULL, NULL, NULL, 'aktif', 13, 14, 9, 8),
(1080, 'KRT022', '8680001022', 'Staedtler Pigment Liner 0.3mm', '1022', 2024, 18.00, 45.00, 28.00, NULL, 80, '2024-01-30', NULL, NULL, NULL, 'aktif', 13, 14, 9, 8),
(1081, 'KRT023', '8680001023', 'Rotring Rapid Pro Versatil Kalem', '1023', 2024, 18.00, 280.00, 175.00, NULL, 20, '2024-01-30', NULL, NULL, NULL, 'aktif', 13, 14, 9, 8),
(1082, 'KRT024', '8680001024', 'Pilot V5 Hi-Tecpoint Kalem', '1024', 2024, 18.00, 38.00, 24.00, NULL, 100, '2024-01-30', NULL, NULL, NULL, 'aktif', 13, 14, 9, 8),
(1083, 'KRT025', '8680001025', 'Stabilo Woody 3in1 Kalem', '1025', 2024, 18.00, 65.00, 41.00, NULL, 45, '2024-01-30', NULL, NULL, NULL, 'aktif', 13, 14, 9, 8),
(1084, 'KRT026', '8680001026', 'Faber Castell Pitt Artist Pen', '1026', 2024, 18.00, 52.00, 33.00, NULL, 75, '2024-01-30', NULL, NULL, NULL, 'aktif', 13, 14, 9, 8),
(1085, 'KRT027', '8680001027', 'Pentel Pocket Brush Pen', '1027', 2024, 18.00, 95.00, 60.00, NULL, 40, '2024-01-30', NULL, NULL, NULL, 'aktif', 13, 14, 9, 8),
(1086, 'KRT028', '8680001028', 'Uni-ball Eye Fine Roller Kalem', '1028', 2024, 18.00, 42.00, 26.00, NULL, 90, '2024-01-30', NULL, NULL, NULL, 'aktif', 13, 14, 9, 8),
(1087, 'KRT029', '8680001029', 'Staedtler Lumograph Kalem Seti', '1029', 2024, 18.00, 185.00, 116.00, NULL, 25, '2024-01-30', NULL, NULL, NULL, 'aktif', 13, 14, 9, 8),
(1088, 'KRT030', '8680001030', 'Tombow Dual Brush Pen', '1030', 2024, 18.00, 48.00, 30.00, NULL, 70, '2024-01-30', NULL, NULL, NULL, 'aktif', 13, 14, 9, 8),
(1089, 'KRT031', '8680001031', 'Faber Castell Grip Finepen', '1031', 2024, 18.00, 35.00, 22.00, NULL, 85, '2024-01-30', NULL, NULL, NULL, 'aktif', 13, 14, 9, 8),
(1090, 'KRT032', '8680001032', 'Stabilo Sensor Fineliner', '1032', 2024, 18.00, 42.00, 26.00, NULL, 95, '2024-01-30', NULL, NULL, NULL, 'aktif', 13, 14, 9, 8),
(1091, 'KRT033', '8680001033', 'Rotring Isograph 0.35mm', '1033', 2024, 18.00, 320.00, 200.00, NULL, 15, '2024-01-30', NULL, NULL, NULL, 'aktif', 13, 14, 9, 8),
(1092, 'KRT034', '8680001034', 'Pilot Parallel Pen 6.0mm', '1034', 2024, 18.00, 155.00, 97.00, NULL, 30, '2024-01-30', NULL, NULL, NULL, 'aktif', 13, 14, 9, 8),
(1093, 'KRT035', '8680001035', 'Pentel Color Brush', '1035', 2024, 18.00, 85.00, 53.00, NULL, 50, '2024-01-30', NULL, NULL, NULL, 'aktif', 13, 14, 9, 8),
(1094, 'KRT036', '8680001036', 'Staedtler Textsurfer Classic', '1036', 2024, 18.00, 28.00, 18.00, NULL, 120, '2024-01-30', NULL, NULL, NULL, 'aktif', 13, 14, 9, 8),
(1095, 'KRT037', '8680001037', 'Faber Castell Polychromos 12li Set', '1037', 2024, 18.00, 485.00, 303.00, NULL, 10, '2024-01-30', NULL, NULL, NULL, 'aktif', 13, 14, 9, 8),
(1096, 'KRT038', '8680001038', 'Uni Ball Air Micro', '1038', 2024, 18.00, 432.00, 28.00, NULL, 1, '2024-01-30', NULL, NULL, NULL, 'aktif', 13, 14, 9, 8),
(1097, 'KRT039', '8680001039', 'Tombow Fudenosuke Brush Pen', '1039', 2024, 18.00, 65.00, 41.00, NULL, 45, '2024-01-30', NULL, NULL, NULL, 'aktif', 13, 14, 9, 8),
(1098, 'KRT040', '8680001040', 'Stabilo Easy Original Kalem', '1040', 2024, 18.00, 75.00, 47.00, NULL, 60, '2024-01-30', NULL, NULL, NULL, 'aktif', 13, 14, 9, 8),
(1099, 'KRT041', '8680001041', 'Rotring 800+ Versatil Kalem', '1041', 2024, 18.00, 420.00, 263.00, NULL, 10, '2024-01-30', NULL, NULL, NULL, 'aktif', 13, 14, 9, 8),
(1100, 'KRT042', '8680001042', 'Pilot Custom 74 Dolma Kalem', '1042', 2024, 18.00, 850.00, 531.00, NULL, 5, '2024-01-30', NULL, NULL, NULL, 'aktif', 13, 14, 9, 8),
(1101, 'KRT043', '8680001043', 'Faber Castell E-Motion', '1043', 2024, 18.00, 650.00, 406.00, NULL, 8, '2024-01-30', NULL, NULL, NULL, 'aktif', 13, 14, 9, 8),
(1102, 'KRT044', '8680001044', 'Pentel Tradio Plastik Gövde', '1044', 2024, 18.00, 95.00, 59.00, NULL, 35, '2024-01-30', NULL, NULL, NULL, 'aktif', 13, 14, 9, 8),
(1103, 'KRT045', '8680001045', 'Staedtler Mars Micro 0.3mm', '1045', 2024, 18.00, 85.00, 53.00, NULL, 45, '2024-01-30', NULL, NULL, NULL, 'aktif', 13, 14, 9, 8),
(1104, 'KRT046', '8680001046', 'Uni Jetstream Edge', '1046', 2024, 18.00, 68.00, 43.00, NULL, 55, '2024-01-30', NULL, NULL, NULL, 'aktif', 13, 14, 9, 8),
(1105, 'KRT047', '8680001047', 'Tombow Zoom 707', '1047', 2024, 18.00, 129.48, 78.00, NULL, 8, '2024-01-30', NULL, NULL, NULL, 'aktif', 13, 14, 9, 8),
(1106, '1', '1', '111', NULL, 2025, 1.00, 20.00, 10.00, NULL, 4, '2025-01-30', NULL, NULL, NULL, 'aktif', NULL, NULL, NULL, NULL),
(1109, 'asd', 'asd', 'asd', NULL, 2025, 8.00, 2.00, 1.00, NULL, 0, '2025-02-12', NULL, NULL, NULL, 'aktif', NULL, NULL, NULL, NULL),
(1110, 'asdd', 'asdd', 'asd', NULL, 2025, 0.00, 2.40, 1.00, NULL, 6, '2025-02-12', NULL, NULL, NULL, 'aktif', NULL, NULL, NULL, NULL),
(1111, '123', '868747885', '123', NULL, 2025, 0.00, 123.00, 12.00, NULL, 0, '2025-02-27', NULL, NULL, NULL, 'aktif', NULL, NULL, NULL, NULL),
(1112, '12345f', '8687478853', '123', NULL, 2025, 1.00, 123.00, 12.00, NULL, 0, '2025-02-27', NULL, NULL, NULL, 'aktif', NULL, NULL, NULL, NULL),
(1113, '8690460429217', '8690460429217', 'VEGE COPİER BOND A4 FOTOKOPİ KAĞIDI', NULL, 2025, 10.00, 120.00, 50.00, NULL, 9, '2025-03-03', NULL, NULL, NULL, 'aktif', NULL, NULL, NULL, NULL);

--
-- Dökümü yapılmış tablolar için indeksler
--

--
-- Tablo için indeksler `admin_user`
--
ALTER TABLE `admin_user`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kullanici_adi` (`kullanici_adi`);

--
-- Tablo için indeksler `alis_faturalari`
--
ALTER TABLE `alis_faturalari`
  ADD PRIMARY KEY (`id`),
  ADD KEY `magaza` (`magaza`),
  ADD KEY `tedarikci` (`tedarikci`);

--
-- Tablo için indeksler `alis_fatura_aktarim`
--
ALTER TABLE `alis_fatura_aktarim`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fatura_id` (`fatura_id`),
  ADD KEY `magaza_id` (`magaza_id`);

--
-- Tablo için indeksler `alis_fatura_detay`
--
ALTER TABLE `alis_fatura_detay`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fatura_id` (`fatura_id`),
  ADD KEY `urun_id` (`urun_id`);

--
-- Tablo için indeksler `alis_fatura_detay_aktarim`
--
ALTER TABLE `alis_fatura_detay_aktarim`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fatura_id` (`fatura_id`),
  ADD KEY `urun_id` (`urun_id`),
  ADD KEY `magaza_id` (`magaza_id`);

--
-- Tablo için indeksler `alis_fatura_log`
--
ALTER TABLE `alis_fatura_log`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `alt_gruplar`
--
ALTER TABLE `alt_gruplar`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_alt_grup` (`ad`,`ana_grup_id`),
  ADD KEY `ana_grup_id` (`ana_grup_id`);

--
-- Tablo için indeksler `ana_gruplar`
--
ALTER TABLE `ana_gruplar`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ad` (`ad`),
  ADD KEY `departman_id` (`departman_id`);

--
-- Tablo için indeksler `birimler`
--
ALTER TABLE `birimler`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ad` (`ad`);

--
-- Tablo için indeksler `departmanlar`
--
ALTER TABLE `departmanlar`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ad` (`ad`);

--
-- Tablo için indeksler `depolar`
--
ALTER TABLE `depolar`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `depo_stok`
--
ALTER TABLE `depo_stok`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `depo_urun_unique` (`depo_id`,`urun_id`),
  ADD KEY `urun_id` (`urun_id`);

--
-- Tablo için indeksler `magazalar`
--
ALTER TABLE `magazalar`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id` (`id`);

--
-- Tablo için indeksler `magaza_stok`
--
ALTER TABLE `magaza_stok`
  ADD PRIMARY KEY (`id`),
  ADD KEY `barkod` (`barkod`),
  ADD KEY `magaza_id` (`magaza_id`);

--
-- Tablo için indeksler `musteriler`
--
ALTER TABLE `musteriler`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `telefon` (`telefon`),
  ADD UNIQUE KEY `barkod` (`barkod`);

--
-- Tablo için indeksler `musteri_borclar`
--
ALTER TABLE `musteri_borclar`
  ADD PRIMARY KEY (`borc_id`),
  ADD KEY `musteri_id` (`musteri_id`),
  ADD KEY `magaza_id` (`magaza_id`);

--
-- Tablo için indeksler `musteri_borc_detaylar`
--
ALTER TABLE `musteri_borc_detaylar`
  ADD PRIMARY KEY (`detay_id`),
  ADD KEY `borc_id` (`borc_id`),
  ADD KEY `urun_id` (`urun_id`);

--
-- Tablo için indeksler `musteri_borc_odemeler`
--
ALTER TABLE `musteri_borc_odemeler`
  ADD PRIMARY KEY (`odeme_id`),
  ADD KEY `borc_id` (`borc_id`),
  ADD KEY `kullanici_id` (`kullanici_id`);

--
-- Tablo için indeksler `musteri_puanlar`
--
ALTER TABLE `musteri_puanlar`
  ADD PRIMARY KEY (`id`),
  ADD KEY `musteri_id` (`musteri_id`);

--
-- Tablo için indeksler `personel`
--
ALTER TABLE `personel`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kullanici_adi` (`kullanici_adi`),
  ADD KEY `magaza_id` (`magaza_id`);

--
-- Tablo için indeksler `puan_ayarlari`
--
ALTER TABLE `puan_ayarlari`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `musteri_turu` (`musteri_turu`);

--
-- Tablo için indeksler `puan_harcama`
--
ALTER TABLE `puan_harcama`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fatura_id` (`fatura_id`),
  ADD KEY `musteri_id` (`musteri_id`);

--
-- Tablo için indeksler `puan_kazanma`
--
ALTER TABLE `puan_kazanma`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fatura_id` (`fatura_id`),
  ADD KEY `musteri_id` (`musteri_id`);

--
-- Tablo için indeksler `satis_faturalari`
--
ALTER TABLE `satis_faturalari`
  ADD PRIMARY KEY (`id`),
  ADD KEY `magaza` (`magaza`),
  ADD KEY `personel` (`personel`),
  ADD KEY `fk_satis_faturalari_musteri` (`musteri_id`);

--
-- Tablo için indeksler `satis_fatura_detay`
--
ALTER TABLE `satis_fatura_detay`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fatura_id` (`fatura_id`),
  ADD KEY `urun_id` (`urun_id`);

--
-- Tablo için indeksler `sistem_ayarlari`
--
ALTER TABLE `sistem_ayarlari`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `anahtar` (`anahtar`);

--
-- Tablo için indeksler `stok_hareketleri`
--
ALTER TABLE `stok_hareketleri`
  ADD PRIMARY KEY (`id`),
  ADD KEY `urun_id` (`urun_id`),
  ADD KEY `kullanici_id` (`kullanici_id`),
  ADD KEY `magaza_id` (`magaza_id`),
  ADD KEY `depo_id` (`depo_id`);

--
-- Tablo için indeksler `tedarikciler`
--
ALTER TABLE `tedarikciler`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `urun_fiyat_gecmisi`
--
ALTER TABLE `urun_fiyat_gecmisi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `urun_id` (`urun_id`),
  ADD KEY `fatura_id` (`fatura_id`),
  ADD KEY `kullanici_id` (`kullanici_id`);

--
-- Tablo için indeksler `urun_onerileri`
--
ALTER TABLE `urun_onerileri`
  ADD PRIMARY KEY (`id`),
  ADD KEY `kullanici_id` (`kullanici_id`);

--
-- Tablo için indeksler `urun_stok`
--
ALTER TABLE `urun_stok`
  ADD PRIMARY KEY (`id`),
  ADD KEY `barkod` (`barkod`),
  ADD KEY `departman_id` (`departman_id`),
  ADD KEY `birim_id` (`birim_id`),
  ADD KEY `ana_grup_id` (`ana_grup_id`),
  ADD KEY `alt_grup_id` (`alt_grup_id`);

--
-- Dökümü yapılmış tablolar için AUTO_INCREMENT değeri
--

--
-- Tablo için AUTO_INCREMENT değeri `admin_user`
--
ALTER TABLE `admin_user`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Tablo için AUTO_INCREMENT değeri `alis_faturalari`
--
ALTER TABLE `alis_faturalari`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Tablo için AUTO_INCREMENT değeri `alis_fatura_aktarim`
--
ALTER TABLE `alis_fatura_aktarim`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Tablo için AUTO_INCREMENT değeri `alis_fatura_detay`
--
ALTER TABLE `alis_fatura_detay`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=119;

--
-- Tablo için AUTO_INCREMENT değeri `alis_fatura_detay_aktarim`
--
ALTER TABLE `alis_fatura_detay_aktarim`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Tablo için AUTO_INCREMENT değeri `alis_fatura_log`
--
ALTER TABLE `alis_fatura_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Tablo için AUTO_INCREMENT değeri `alt_gruplar`
--
ALTER TABLE `alt_gruplar`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Tablo için AUTO_INCREMENT değeri `ana_gruplar`
--
ALTER TABLE `ana_gruplar`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Tablo için AUTO_INCREMENT değeri `birimler`
--
ALTER TABLE `birimler`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- Tablo için AUTO_INCREMENT değeri `departmanlar`
--
ALTER TABLE `departmanlar`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Tablo için AUTO_INCREMENT değeri `depolar`
--
ALTER TABLE `depolar`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Tablo için AUTO_INCREMENT değeri `depo_stok`
--
ALTER TABLE `depo_stok`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- Tablo için AUTO_INCREMENT değeri `magazalar`
--
ALTER TABLE `magazalar`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Tablo için AUTO_INCREMENT değeri `magaza_stok`
--
ALTER TABLE `magaza_stok`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=87;

--
-- Tablo için AUTO_INCREMENT değeri `musteriler`
--
ALTER TABLE `musteriler`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10204386;

--
-- Tablo için AUTO_INCREMENT değeri `musteri_borclar`
--
ALTER TABLE `musteri_borclar`
  MODIFY `borc_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- Tablo için AUTO_INCREMENT değeri `musteri_borc_detaylar`
--
ALTER TABLE `musteri_borc_detaylar`
  MODIFY `detay_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- Tablo için AUTO_INCREMENT değeri `musteri_borc_odemeler`
--
ALTER TABLE `musteri_borc_odemeler`
  MODIFY `odeme_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Tablo için AUTO_INCREMENT değeri `musteri_puanlar`
--
ALTER TABLE `musteri_puanlar`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10204387;

--
-- Tablo için AUTO_INCREMENT değeri `personel`
--
ALTER TABLE `personel`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Tablo için AUTO_INCREMENT değeri `puan_ayarlari`
--
ALTER TABLE `puan_ayarlari`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Tablo için AUTO_INCREMENT değeri `puan_harcama`
--
ALTER TABLE `puan_harcama`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Tablo için AUTO_INCREMENT değeri `puan_kazanma`
--
ALTER TABLE `puan_kazanma`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Tablo için AUTO_INCREMENT değeri `satis_faturalari`
--
ALTER TABLE `satis_faturalari`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=71;

--
-- Tablo için AUTO_INCREMENT değeri `satis_fatura_detay`
--
ALTER TABLE `satis_fatura_detay`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=207;

--
-- Tablo için AUTO_INCREMENT değeri `sistem_ayarlari`
--
ALTER TABLE `sistem_ayarlari`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Tablo için AUTO_INCREMENT değeri `stok_hareketleri`
--
ALTER TABLE `stok_hareketleri`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=253;

--
-- Tablo için AUTO_INCREMENT değeri `tedarikciler`
--
ALTER TABLE `tedarikciler`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Tablo için AUTO_INCREMENT değeri `urun_fiyat_gecmisi`
--
ALTER TABLE `urun_fiyat_gecmisi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- Tablo için AUTO_INCREMENT değeri `urun_onerileri`
--
ALTER TABLE `urun_onerileri`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `urun_stok`
--
ALTER TABLE `urun_stok`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1114;

--
-- Dökümü yapılmış tablolar için kısıtlamalar
--

--
-- Tablo kısıtlamaları `alis_faturalari`
--
ALTER TABLE `alis_faturalari`
  ADD CONSTRAINT `alis_faturalari_ibfk_1` FOREIGN KEY (`magaza`) REFERENCES `magazalar` (`id`),
  ADD CONSTRAINT `alis_faturalari_ibfk_2` FOREIGN KEY (`tedarikci`) REFERENCES `tedarikciler` (`id`);

--
-- Tablo kısıtlamaları `alis_fatura_aktarim`
--
ALTER TABLE `alis_fatura_aktarim`
  ADD CONSTRAINT `alis_fatura_aktarim_ibfk_1` FOREIGN KEY (`fatura_id`) REFERENCES `alis_faturalari` (`id`),
  ADD CONSTRAINT `alis_fatura_aktarim_ibfk_2` FOREIGN KEY (`magaza_id`) REFERENCES `magazalar` (`id`);

--
-- Tablo kısıtlamaları `alis_fatura_detay`
--
ALTER TABLE `alis_fatura_detay`
  ADD CONSTRAINT `alis_fatura_detay_ibfk_1` FOREIGN KEY (`fatura_id`) REFERENCES `alis_faturalari` (`id`),
  ADD CONSTRAINT `alis_fatura_detay_ibfk_2` FOREIGN KEY (`urun_id`) REFERENCES `urun_stok` (`id`);

--
-- Tablo kısıtlamaları `alis_fatura_detay_aktarim`
--
ALTER TABLE `alis_fatura_detay_aktarim`
  ADD CONSTRAINT `alis_fatura_detay_aktarim_ibfk_1` FOREIGN KEY (`fatura_id`) REFERENCES `alis_faturalari` (`id`),
  ADD CONSTRAINT `alis_fatura_detay_aktarim_ibfk_2` FOREIGN KEY (`urun_id`) REFERENCES `urun_stok` (`id`),
  ADD CONSTRAINT `alis_fatura_detay_aktarim_ibfk_3` FOREIGN KEY (`magaza_id`) REFERENCES `magazalar` (`id`);

--
-- Tablo kısıtlamaları `alt_gruplar`
--
ALTER TABLE `alt_gruplar`
  ADD CONSTRAINT `alt_gruplar_ibfk_1` FOREIGN KEY (`ana_grup_id`) REFERENCES `ana_gruplar` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `ana_gruplar`
--
ALTER TABLE `ana_gruplar`
  ADD CONSTRAINT `ana_gruplar_ibfk_1` FOREIGN KEY (`departman_id`) REFERENCES `departmanlar` (`id`) ON DELETE SET NULL;

--
-- Tablo kısıtlamaları `depo_stok`
--
ALTER TABLE `depo_stok`
  ADD CONSTRAINT `depo_stok_ibfk_1` FOREIGN KEY (`depo_id`) REFERENCES `depolar` (`id`),
  ADD CONSTRAINT `depo_stok_ibfk_2` FOREIGN KEY (`urun_id`) REFERENCES `urun_stok` (`id`);

--
-- Tablo kısıtlamaları `magaza_stok`
--
ALTER TABLE `magaza_stok`
  ADD CONSTRAINT `magaza_stok_ibfk_1` FOREIGN KEY (`barkod`) REFERENCES `urun_stok` (`barkod`),
  ADD CONSTRAINT `magaza_stok_ibfk_2` FOREIGN KEY (`magaza_id`) REFERENCES `magazalar` (`id`);

--
-- Tablo kısıtlamaları `musteri_borclar`
--
ALTER TABLE `musteri_borclar`
  ADD CONSTRAINT `musteri_borclar_ibfk_1` FOREIGN KEY (`musteri_id`) REFERENCES `musteriler` (`id`),
  ADD CONSTRAINT `musteri_borclar_ibfk_2` FOREIGN KEY (`magaza_id`) REFERENCES `magazalar` (`id`);

--
-- Tablo kısıtlamaları `musteri_borc_detaylar`
--
ALTER TABLE `musteri_borc_detaylar`
  ADD CONSTRAINT `musteri_borc_detaylar_ibfk_1` FOREIGN KEY (`borc_id`) REFERENCES `musteri_borclar` (`borc_id`),
  ADD CONSTRAINT `musteri_borc_detaylar_ibfk_2` FOREIGN KEY (`urun_id`) REFERENCES `urun_stok` (`id`);

--
-- Tablo kısıtlamaları `musteri_borc_odemeler`
--
ALTER TABLE `musteri_borc_odemeler`
  ADD CONSTRAINT `musteri_borc_odemeler_ibfk_1` FOREIGN KEY (`borc_id`) REFERENCES `musteri_borclar` (`borc_id`),
  ADD CONSTRAINT `musteri_borc_odemeler_ibfk_2` FOREIGN KEY (`kullanici_id`) REFERENCES `personel` (`id`);

--
-- Tablo kısıtlamaları `personel`
--
ALTER TABLE `personel`
  ADD CONSTRAINT `personel_ibfk_1` FOREIGN KEY (`magaza_id`) REFERENCES `magazalar` (`id`);

--
-- Tablo kısıtlamaları `puan_harcama`
--
ALTER TABLE `puan_harcama`
  ADD CONSTRAINT `fk_puan_harcama_fatura` FOREIGN KEY (`fatura_id`) REFERENCES `satis_faturalari` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_puan_harcama_musteri` FOREIGN KEY (`musteri_id`) REFERENCES `musteriler` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `puan_kazanma`
--
ALTER TABLE `puan_kazanma`
  ADD CONSTRAINT `fk_puan_kazanma_fatura` FOREIGN KEY (`fatura_id`) REFERENCES `satis_faturalari` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_puan_kazanma_musteri` FOREIGN KEY (`musteri_id`) REFERENCES `musteriler` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `satis_faturalari`
--
ALTER TABLE `satis_faturalari`
  ADD CONSTRAINT `fk_satis_faturalari_musteri` FOREIGN KEY (`musteri_id`) REFERENCES `musteriler` (`id`),
  ADD CONSTRAINT `satis_faturalari_ibfk_1` FOREIGN KEY (`magaza`) REFERENCES `magazalar` (`id`),
  ADD CONSTRAINT `satis_faturalari_ibfk_2` FOREIGN KEY (`personel`) REFERENCES `personel` (`id`);

--
-- Tablo kısıtlamaları `satis_fatura_detay`
--
ALTER TABLE `satis_fatura_detay`
  ADD CONSTRAINT `satis_fatura_detay_ibfk_1` FOREIGN KEY (`fatura_id`) REFERENCES `satis_faturalari` (`id`),
  ADD CONSTRAINT `satis_fatura_detay_ibfk_2` FOREIGN KEY (`urun_id`) REFERENCES `urun_stok` (`id`);

--
-- Tablo kısıtlamaları `stok_hareketleri`
--
ALTER TABLE `stok_hareketleri`
  ADD CONSTRAINT `stok_hareketleri_ibfk_1` FOREIGN KEY (`urun_id`) REFERENCES `urun_stok` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `stok_hareketleri_ibfk_2` FOREIGN KEY (`kullanici_id`) REFERENCES `admin_user` (`id`),
  ADD CONSTRAINT `stok_hareketleri_ibfk_3` FOREIGN KEY (`magaza_id`) REFERENCES `magazalar` (`id`),
  ADD CONSTRAINT `stok_hareketleri_ibfk_4` FOREIGN KEY (`depo_id`) REFERENCES `depolar` (`id`);

--
-- Tablo kısıtlamaları `urun_fiyat_gecmisi`
--
ALTER TABLE `urun_fiyat_gecmisi`
  ADD CONSTRAINT `urun_fiyat_gecmisi_ibfk_1` FOREIGN KEY (`urun_id`) REFERENCES `urun_stok` (`id`),
  ADD CONSTRAINT `urun_fiyat_gecmisi_ibfk_2` FOREIGN KEY (`fatura_id`) REFERENCES `alis_faturalari` (`id`),
  ADD CONSTRAINT `urun_fiyat_gecmisi_ibfk_3` FOREIGN KEY (`kullanici_id`) REFERENCES `admin_user` (`id`);

--
-- Tablo kısıtlamaları `urun_onerileri`
--
ALTER TABLE `urun_onerileri`
  ADD CONSTRAINT `urun_onerileri_ibfk_1` FOREIGN KEY (`kullanici_id`) REFERENCES `admin_user` (`id`);

--
-- Tablo kısıtlamaları `urun_stok`
--
ALTER TABLE `urun_stok`
  ADD CONSTRAINT `urun_stok_ibfk_1` FOREIGN KEY (`departman_id`) REFERENCES `departmanlar` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `urun_stok_ibfk_2` FOREIGN KEY (`birim_id`) REFERENCES `birimler` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `urun_stok_ibfk_3` FOREIGN KEY (`ana_grup_id`) REFERENCES `ana_gruplar` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `urun_stok_ibfk_4` FOREIGN KEY (`alt_grup_id`) REFERENCES `alt_gruplar` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
