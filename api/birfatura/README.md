# Birfatura API Entegrasyonu - İnciPOS

İnciPOS ile Birfatura sistemleri arasındaki entegrasyon için gerekli API dosyalarını içerir. Bu entegrasyon POS satışlarının otomatik olarak Birfatura'ya aktarılmasını sağlar.

## Kurulum

### 1. Dosyaları Yükleme

Tüm API dosyalarını web sunucusunda `/admin/api/birfatura/` dizinine yükleyin:

- `index.php` - Ana yönlendirme dosyası
- `helpers.php` - Yardımcı fonksiyonlar
- `orders.php` - Sipariş listesini döndüren endpoint
- `orderStatus.php` - Sipariş durumlarını döndüren endpoint
- `paymentMethods.php` - Ödeme yöntemlerini döndüren endpoint  
- `orderCargoUpdate.php` - Kargo bilgilerini güncelleyen endpoint
- `invoiceLinkUpdate.php` - Fatura linkini güncelleyen endpoint
- `stockUpdate.php` - Stok bilgilerini güncelleyen endpoint
- `statistics.php` - İstatistik fonksiyonları
- `dashboard.php` - API yönetim paneli

### 2. Dizin İzinleri

```bash
chmod 755 /path/to/admin/api/birfatura
chmod 777 /path/to/admin/api/birfatura/api_log.txt
```

### 3. API Token Ayarı

Tüm endpointler içinde aynı token değeri kullanılmıştır. Güvenlik için bu değeri değiştirmeniz önerilir:

```php
// Her dosya içindeki bu satırı güvenli bir token ile değiştirin
$expected_token = '';
```

### 4. Veritabanı Bağlantısı

`orders.php` ve diğer veritabanı erişimi gerektiren dosyalarda bağlantı bilgilerini güncelleyin:

```php
$dbname = '';
$username = '';
$password = '';
```

### 5. Dashboard Erişimi

API yönetim paneline erişmek için:
```
https://domain.com/admin/api/birfatura/dashboard.php
```

## Kullanım

### Birfatura Ayarları

Birfatura sisteminde entegrasyon için aşağıdaki ayarları yapın:

1. Birfatura kontrol panelinden "Entegrasyonlar" menüsüne gidin
2. "Özel Entegrasyon" seçeneğini seçin
3. Aşağıdaki endpoint URL'lerini girin:

| Endpoint | URL |
|----------|-----|
| Sipariş Durumları | https://domain.com/admin/api/birfatura/orderStatus.php |
| Ödeme Yöntemleri | https://domain.com/admin/api/birfatura/paymentMethods.php |
| Siparişler | https://domain.com/admin/api/birfatura/orders.php |
| Kargo Bilgisi Güncelleme | https://domain.com/admin/api/birfatura/orderCargoUpdate.php |
| Fatura Link Güncelleme | https://domain.com/admin/api/birfatura/invoiceLinkUpdate.php |
| Stok Güncelleme | https://domain.com/admin/api/birfatura/stockUpdate.php |

4. API token: `KeHxXtvWK6QovGL` (veya değiştirdiyseniz yeni değeri)
5. Senkronizasyon periyodu: 1 saat (önerilen)

### API Testi

API entegrasyonunun düzgün çalışıp çalışmadığını kontrol etmek için dashboard'u kullanabilirsiniz:

1. Dashboard'a erişin
2. "Sipariş Durumlarını Getir" gibi test butonlarına tıklayarak istekleri test edin
3. API loglarını kontrol edin

## Sorun Giderme

1. **Boş Yanıt Hatası**
   - JSON formatı hatası olabilir. `api_log.txt` dosyasını kontrol edin.
   - `helpers.php` dosyasında `cleanDataForJson` fonksiyonu Unicode karakterleri temizler.

2. **Kimlik Doğrulama Hatası (401)**
   - Token değerini kontrol edin. Birfatura'da tanımladığınız token ile endpoint dosyalarındaki `$expected_token` değeri aynı olmalıdır.
   - Request header'ında token'ın doğru formatta gönderildiğinden emin olun: `Token: ` veya `Authorization: Bearer `

3. **Sipariş Verisi Aktarılmıyor**
   - `orders.php` dosyasında SQL sorgusunu kontrol edin. Filtreleme kriterleri siparişlerinizi kapsıyor olmalıdır.
   - Veri formatı dönüşümlerinde hata olabilir. Özellikle tarih formatlarını kontrol edin.

4. **HTTP Metodu Hatası (405)**
   - Tüm istekler POST metodu ile yapılmalıdır. GET istekleri kabul edilmez.

5. **CORS Hatası**
   - Farklı domain'den API'ye erişirken CORS hatası alırsanız, geliştirici bu satırları `index.php` dosyasına ekleyebilir:
   ```php
   header('Access-Control-Allow-Origin: *');
   header('Access-Control-Allow-Methods: POST, OPTIONS');
   header('Access-Control-Allow-Headers: Content-Type, Authorization, Token');
   ```

## Logging

Tüm API istekleri ve yanıtları `api_log.txt` dosyasına kaydedilir. Bu dosya hata ayıklama için çok önemlidir.

Log dosyası çok büyüdüğünde periyodik olarak yedekleyip temizlemek gerekebilir:

```bash
mv /path/to/admin/api/birfatura/api_log.txt /path/to/admin/api/birfatura/api_log_$(date +"%Y%m%d").txt
touch /path/to/admin/api/birfatura/api_log.txt
chmod 777 /path/to/admin/api/birfatura/api_log.txt
```