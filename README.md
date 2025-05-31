# İnciPOS - Kırtasiye Pos Yönetim Sistemi

İnciPOS, küçük ve orta ölçekli kırtasiye işletmeleri için geliştirilmiş, satış takibi, stok yönetimi ve müşteri ilişkileri yönetimi fonksiyonlarını bir arada sunan kapsamlı bir POS (Point of Sale) sistemidir.

## Özellikler

- 📝 Satış ve fatura yönetimi
- 📦 Stok ve envanter takibi
- 👥 Müşteri veritabanı ve CRM
- 📊 Gelir-gider raporları
- 📱 SMS bildirim entegrasyonu (NetGSM)
- 🔐 Güvenli çok kullanıcılı oturum yönetimi
- 📈 Satış istatistikleri ve grafikleri

## Kurulum

### Sistem Gereksinimleri

- PHP 8.2 veya üzeri
- MySQL 5.7 veya üzeri
- Apache/Nginx web sunucusu
- SSL sertifikası (canlı ortamda güvenlik için)

### 1. Dosyaları Sunucuya Yükleme

1. Bu depodan tüm dosyaları indirin
2. Dosyaları web sunucunuzun kök dizinine (public_html, www veya htdocs) yükleyin
3. Dosya izinlerini ayarlayın:
   ```bash
   chmod 755 -R /path/to/incipos
   chmod 777 -R /path/to/incipos/uploads
   chmod 777 -R /path/to/incipos/temp
   ```

### 2. Veritabanı Kurulumu

1. MySQL veritabanı oluşturun
2. `incipos_db.sql` dosyasını içe aktarın:
   ```bash
   mysql -u kullanici_adi -p veritabani_adi < incipos_db.sql
   ```
   veya phpMyAdmin üzerinden SQL dosyasını içe aktarın

### 3. Yapılandırma

1. `config.php.example` dosyasını `config.php` olarak kopyalayın
2. Veritabanı bağlantı bilgilerinizi `config.php` dosyasında düzenleyin:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'veritabani_adi');
   define('DB_USER', 'kullanici_adi');
   define('DB_PASS', 'sifre');
   ```

### 4. NetGSM SMS Entegrasyonu

NetGSM servisi kullanarak SMS gönderimi yapabilmek için aşağıdaki adımları izleyin:

1. NetGSM hesabınızı oluşturun (https://www.netgsm.com.tr)
2. Yönetim panelinde "Sistem Ayarları" bölümüne gidin
3. NetGSM API bilgilerinizi girin:
   - NetGSM Kullanıcı Adı/Kodu
   - NetGSM Şifresi
   - NetGSM Mesaj Başlığı (header)

> **Not:** NetGSM ayarları veritabanında `sistem_ayarlari` tablosunda saklanır. Hiç bir hassas bilgi doğrudan kod içinde tutulmaz.

## Dosya Yapısı

```
incipos/
├── admin/                 # Yönetim paneli dosyaları
├── api/                   # API dosyaları (mobil uygulama için)
├── assets/                # CSS, JS ve resim dosyaları
│   ├── css/               # Stil dosyaları
│   ├── js/                # JavaScript dosyaları
│   └── img/               # Görseller
├── includes/              # PHP kütüphane ve yardımcı dosyaları
│   ├── classes/           # Sınıf dosyaları
│   ├── functions/         # Fonksiyon dosyaları
│   └── netgsm/            # NetGSM entegrasyon dosyaları
├── uploads/               # Yüklenen dosyaların tutulduğu dizin
├── vendor/                # Composer bağımlılıkları
├── config.php             # Yapılandırma dosyası
├── db_connection.php      # Veritabanı bağlantı dosyası
├── index.php              # Ana sayfa
├── README.md              # Bu dosya
└── incipos_db.sql         # Veritabanı şeması
```

## Sık Karşılaşılan Sorunlar

1. **Boş sayfa hataları:** PHP hata ayıklama modunu açarak sorunları inceleyebilirsiniz:
   ```php
   // index.php dosyasının en başına ekleyin
   error_reporting(E_ALL);
   ini_set('display_errors', 1);
   ```

2. **Veritabanı bağlantı hataları:** Veritabanı bilgilerinizi doğrulayın ve MySQL servisinin çalıştığından emin olun.

3. **SMS gönderim sorunları:** NetGSM bakiyenizi ve API bilgilerinizin doğruluğunu kontrol edin.

## Destek

Sorunlar ve özellik istekleri için GitHub Issues üzerinden bildirim açabilir veya aşağıdaki iletişim kanallarından bize ulaşabilirsiniz:

## Lisans

Copyright (c) 2025 İnciPOS

Bu yazılım, ticari kullanım hariç olmak üzere kişisel ve eğitim amaçlı kullanıma açıktır. Ticari kullanım için lisans alınması gerekmektedir.