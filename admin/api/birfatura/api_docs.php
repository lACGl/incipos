<?php
/**
 * Birfatura API Dokümantasyonu
 */

// Yardımcı fonksiyonları dahil et
require_once __DIR__ . '/helpers.php';

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Birfatura API Dokümantasyonu</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f8f9fa; color: #212529; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .header { background-color: #343a40; color: white; padding: 20px; margin-bottom: 20px; border-radius: 5px; }
        .header h1 { margin: 0; }
        .section { background-color: white; padding: 20px; margin-bottom: 20px; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .endpoint { border-left: 4px solid #007bff; padding-left: 15px; margin-bottom: 30px; }
        .method { display: inline-block; padding: 5px 10px; border-radius: 4px; font-weight: bold; margin-right: 10px; }
        .post { background-color: #28a745; color: white; }
        .get { background-color: #17a2b8; color: white; }
        .url { background-color: #f8f9fa; padding: 5px 10px; border-radius: 4px; font-family: monospace; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { border: 1px solid #dee2e6; padding: 8px; text-align: left; }
        th { background-color: #e9ecef; }
        code { background-color: #f8f9fa; padding: 2px 5px; border-radius: 3px; font-family: monospace; }
        pre { background-color: #f8f9fa; padding: 15px; border-radius: 4px; overflow: auto; }
        .nav { display: flex; flex-wrap: wrap; margin-bottom: 20px; background-color: #e9ecef; padding: 10px; border-radius: 5px; }
        .nav a { margin-right: 15px; margin-bottom: 5px; color: #495057; text-decoration: none; padding: 8px 12px; }
        .nav a:hover { color: #fff; background-color: #007bff; border-radius: 3px; }
        .required { color: #dc3545; }
        .optional { color: #6c757d; }
        .btn { display: inline-block; padding: 8px 16px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px; }
        .btn:hover { background-color: #0069d9; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Birfatura API Dokümantasyonu</h1>
            <p>API Versiyon: 1.0</p>
        </div>
        
        <div class="nav">
            <a href="#overview">Genel Bakış</a>
            <a href="#authentication">Kimlik Doğrulama</a>
            <a href="#orderStatus">Sipariş Durumları</a>
            <a href="#paymentMethods">Ödeme Yöntemleri</a>
            <a href="#orders">Siparişler</a>
            <a href="#orderCargoUpdate">Kargo Güncelleme</a>
            <a href="#invoiceLinkUpdate">Fatura Link Güncelleme</a>
            <a href="#stockUpdate">Stok Güncelleme</a>
            <a href="dashboard.php">Dashboard</a>
        </div>
        
        <div id="overview" class="section">
            <h2>Genel Bakış</h2>
            <p>Bu dokümantasyon, İnci Kırtasiye POS sistemi ile Birfatura sistemi arasındaki entegrasyon API'sini açıklamaktadır. API, siparişlerin senkronizasyonu, stok güncellemeleri ve fatura bağlantıları gibi işlemleri destekler.</p>
            
            <h3>Temel Endpoint</h3>
            <p>Tüm API istekleri aşağıdaki temel URL'ye yapılır:</p>
            <pre>https://pos.incikirtasiye.com/admin/api/birfatura/</pre>
            
            <h3>Yanıt Formatı</h3>
            <p>Tüm API yanıtları JSON formatındadır. Başarılı yanıtlarda genellikle bir <code>success</code> alanı bulunur ve değeri <code>true</code> olarak döner.</p>
            
            <pre>{
    "success": true,
    "message": "İşlem başarılı"
}</pre>
            
            <h3>Hata Kodları</h3>
            <table>
                <thead>
                    <tr>
                        <th>HTTP Kodu</th>
                        <th>Açıklama</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>200</td>
                        <td>Başarılı istek</td>
                    </tr>
                    <tr>
                        <td>400</td>
                        <td>Geçersiz istek veya eksik parametreler</td>
                    </tr>
                    <tr>
                        <td>401</td>
                        <td>Kimlik doğrulama hatası</td>
                    </tr>
                    <tr>
                        <td>404</td>
                        <td>Kaynak bulunamadı</td>
                    </tr>
                    <tr>
                        <td>405</td>
                        <td>İzin verilmeyen metod</td>
                    </tr>
                    <tr>
                        <td>500</td>
                        <td>Sunucu hatası</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div id="authentication" class="section">
            <h2>Kimlik Doğrulama</h2>
            <p>API isteklerinde kimlik doğrulama için token tabanlı bir yapı kullanılmaktadır. Her istekte, HTTP başlığında token bilgisi gönderilmelidir.</p>
            
            <h3>Token Gönderimi</h3>
            <p>Token'ı aşağıdaki iki yöntemden biriyle gönderebilirsiniz:</p>
            
            <h4>1. Authorization Header</h4>
            <pre>Authorization: Bearer {token}</pre>
            
            <h4>2. Token Header</h4>
            <pre>Token: {token}</pre>
            
            <p><strong>Not:</strong> API token güvenli tutulmalı ve düzenli olarak değiştirilmelidir.</p>
        </div>
        
        <div id="orderStatus" class="section">
            <h2>Sipariş Durumları</h2>
            
            <div class="endpoint">
                <h3>
                    <span class="method post">POST</span>
                    <span class="url">/orderStatus.php</span>
                </h3>
                
                <p>Sistemdeki sipariş durumlarını listeler.</p>
                
                <h4>İstek</h4>
                <p>Bu endpoint boş bir POST isteği gerektirir. Herhangi bir parametre gerekmez.</p>
                
                <h4>Yanıt</h4>
                <pre>{
    "OrderStatus": [
        {
            "Id": 1,
            "Value": "Onaylandı"
        }
    ]
}</pre>
            </div>
        </div>
        
        <div id="paymentMethods" class="section">
            <h2>Ödeme Yöntemleri</h2>
            
            <div class="endpoint">
                <h3>
                    <span class="method post">POST</span>
                    <span class="url">/paymentMethods.php</span>
                </h3>
                
                <p>Sistemdeki ödeme yöntemlerini listeler.</p>
                
                <h4>İstek</h4>
                <p>Bu endpoint boş bir POST isteği gerektirir. Herhangi bir parametre gerekmez.</p>
                
                <h4>Yanıt</h4>
                <pre>{
    "PaymentMethods": [
        {
            "Id": 1,
            "Value": "Nakit"
        },
        {
            "Id": 2,
            "Value": "Kredi Kartı"
        }
    ]
}</pre>
            </div>
        </div>
        
        <div id="orders" class="section">
            <h2>Siparişler</h2>
            
            <div class="endpoint">
                <h3>
                    <span class="method post">POST</span>
                    <span class="url">/orders.php</span>
                </h3>
                
                <p>Belirli bir tarih aralığındaki siparişleri listeler.</p>
                
                <h4>İstek Parametreleri</h4>
                <table>
                    <thead>
                        <tr>
                            <th>Parametre</th>
                            <th>Tip</th>
                            <th>Zorunlu</th>
                            <th>Açıklama</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>startDateTime</td>
                            <td>string</td>
                            <td><span class="optional">Hayır</span></td>
                            <td>Başlangıç tarihi (Format: dd.mm.yyyy HH:ii:ss)</td>
                        </tr>
                        <tr>
                            <td>endDateTime</td>
                            <td>string</td>
                            <td><span class="optional">Hayır</span></td>
                            <td>Bitiş tarihi (Format: dd.mm.yyyy HH:ii:ss)</td>
                        </tr>
                        <tr>
                            <td>orderStatusId</td>
                            <td>integer</td>
                            <td><span class="optional">Hayır</span></td>
                            <td>Sipariş durumu ID'si</td>
                        </tr>
                    </tbody>
                </table>
                
                <h4>Örnek İstek</h4>
                <pre>{
    "startDateTime": "01.04.2023 00:00:00",
    "endDateTime": "30.04.2023 23:59:59",
    "orderStatusId": 1
}</pre>
                
                <h4>Yanıt</h4>
                <p>Yanıt, siparişlerin detaylı bir listesini içerir. Aşağıda kısaltılmış bir örnek verilmiştir:</p>
                <pre>{
    "Orders": [
        {
            "OrderId": "1",
            "OrderCode": "INV0000000001",
            "OrderDate": "01.04.2023 12:30:45",
            "InvoiceTypeId": 1,
            "InvoiceDate": "01.04.2023",
            "InvoiceExplanation": "POS Satışı",
            "EInvoiceProfileId": 1,
            "EInvoiceId": "0",
            "ETTN": "0",
            "CustomerId": 1,
            "BillingName": "Ahmet Yılmaz",
            "BillingAddress": "Örnek Mah. Test Sok. No:1",
            "BillingTown": "Beşiktaş",
            "BillingCity": "İstanbul",
            "BillingMobilePhone": "5551234567",
            "BillingPhone": "",
            "TaxOffice": "Beşiktaş",
            "TaxNo": "11111111111",
            "SSNTCNo": "11111111111",
            "Email": "ahmet@example.com",
            "ShippingId": 1,
            "ShippingName": "Ahmet Yılmaz",
            "ShippingAddress": "Örnek Mah. Test Sok. No:1",
            "ShippingTown": "Beşiktaş",
            "ShippingCity": "İstanbul",
            "ShippingCountry": "Türkiye",
            "ShippingZipCode": "00000",
            "ShippingPhone": "5551234567",
            "ShipCompany": "Mağaza Teslim",
            "CargoCampaignCode": "0",
            "SalesChannelWebSite": "www.incikirtasiye.com",
            "PaymentTypeId": 2,
            "PaymentType": "Kredi Kartı",
            "Currency": "TL",
            "CurrencyRate": 1,
            "TotalPaidTaxExcluding": 84.75,
            "TotalPaidTaxIncluding": 100,
            "ProductsTotalTaxExcluding": 84.75,
            "ProductsTotalTaxIncluding": 100,
            "OrderDetails": [
                {
                    "ProductId": "123",
                    "ProductCode": "K123",
                    "Barcode": "8697123456789",
                    "ProductBrand": "",
                    "ProductName": "Kırmızı Kalem",
                    "ProductNote": "",
                    "ProductImage": "",
                    "Variants": [],
                    "ProductQuantityType": "Adet",
                    "ProductQuantity": 2,
                    "VatRate": 18,
                    "ProductUnitPriceTaxExcluding": 42.37,
                    "ProductUnitPriceTaxIncluding": 50
                }
            ]
        }
    ]
}</pre>
            </div>
        </div>
        
        <div id="orderCargoUpdate" class="section">
            <h2>Kargo Güncelleme</h2>
            
            <div class="endpoint">
                <h3>
                    <span class="method post">POST</span>
                    <span class="url">/orderCargoUpdate.php</span>
                </h3>
                
                <p>Bir siparişin kargo bilgilerini günceller.</p>
                
                <h4>İstek Parametreleri</h4>
                <table>
                    <thead>
                        <tr>
                            <th>Parametre</th>
                            <th>Tip</th>
                            <th>Zorunlu</th>
                            <th>Açıklama</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>orderId</td>
                            <td>integer</td>
                            <td><span class="required">Evet</span></td>
                            <td>Sipariş ID'si</td>
                        </tr>
                        <tr>
                            <td>orderStatusId</td>
                            <td>integer</td>
                            <td><span class="optional">Hayır</span></td>
                            <td>Sipariş durumu ID'si</td>
                        </tr>
                        <tr>
                            <td>cargoTrackingCode</td>
                            <td>string</td>
                            <td><span class="optional">Hayır</span></td>
                            <td>Kargo takip kodu</td>
                        </tr>
                        <tr>
                            <td>cargoCompany</td>
                            <td>string</td>
                            <td><span class="optional">Hayır</span></td>
                            <td>Kargo şirketi adı</td>
                        </tr>
                    </tbody>
                </table>
                
                <h4>Örnek İstek</h4>
                <pre>{
    "orderId": 1,
    "orderStatusId": 1,
    "cargoTrackingCode": "123456789",
    "cargoCompany": "Aras Kargo"
}</pre>
                
                <h4>Yanıt</h4>
                <pre>{
    "success": true,
    "message": "Sipariş kargo bilgileri güncellendi"
}</pre>
            </div>
        </div>
        
        <div id="invoiceLinkUpdate" class="section">
            <h2>Fatura Link Güncelleme</h2>
            
            <div class="endpoint">
                <h3>
                    <span class="method post">POST</span>
                    <span class="url">/invoiceLinkUpdate.php</span>
                </h3>
                
                <p>Bir siparişin fatura bağlantısını günceller.</p>
                
                <h4>İstek Parametreleri</h4>
                <table>
                    <thead>
                        <tr>
                            <th>Parametre</th>
                            <th>Tip</th>
                            <th>Zorunlu</th>
                            <th>Açıklama</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>orderId</td>
                            <td>integer</td>
                            <td><span class="required">Evet</span></td>
                            <td>Sipariş ID'si</td>
                        </tr>
                        <tr>
                            <td>faturaUrl</td>
                            <td>string</td>
                            <td><span class="required">Evet</span></td>
                            <td>Fatura dosyasının URL'si</td>
                        </tr>
                        <tr>
                            <td>faturaNo</td>
                            <td>string</td>
                            <td><span class="optional">Hayır</span></td>
                            <td>Fatura numarası</td>
                        </tr>
                        <tr>
                            <td>faturaTarihi</td>
                            <td>string</td>
                            <td><span class="optional">Hayır</span></td>
                            <td>Fatura tarihi (Format: yyyy-mm-dd)</td>
                        </tr>
                    </tbody>
                </table>
                
                <h4>Örnek İstek</h4>
                <pre>{
    "orderId": 1,
    "faturaUrl": "https://example.com/faturalar/F12345.pdf",
    "faturaNo": "F12345",
    "faturaTarihi": "2023-04-15"
}</pre>
                
                <h4>Yanıt</h4>
                <pre>{
    "success": true,
    "message": "Fatura linki güncellendi"
}</pre>
            </div>
        </div>
        
        <div id="stockUpdate" class="section">
            <h2>Stok Güncelleme</h2>
            
            <div class="endpoint">
                <h3>
                    <span class="method post">POST</span>
                    <span class="url">/stockUpdate.php</span>
                </h3>
                
                <p>Bir ürünün stok miktarını günceller.</p>
                
                <h4>İstek Parametreleri</h4>
                <table>
                    <thead>
                        <tr>
                            <th>Parametre</th>
                            <th>Tip</th>
                            <th>Zorunlu</th>
                            <th>Açıklama</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>productId</td>
                            <td>integer</td>
                            <td><span class="optional">Koşullu</span></td>
                            <td>Ürün ID'si (productId veya barcode gerekli)</td>
                        </tr>
                        <tr>
                            <td>barcode</td>
                            <td>string</td>
                            <td><span class="optional">Koşullu</span></td>
                            <td>Ürün barkodu (productId veya barcode gerekli)</td>
                        </tr>
                        <tr>
                            <td>quantity</td>
                            <td>number</td>
                            <td><span class="required">Evet</span></td>
                            <td>Stok miktarı</td>
                        </tr>
                        <tr>
                            <td>updateType</td>
                            <td>string</td>
                            <td><span class="optional">Hayır</span></td>
                            <td>Güncelleme tipi: "set" (varsayılan), "add" veya "subtract"</td>
                        </tr>
                        <tr>
                            <td>warehouseId</td>
                            <td>integer</td>
                            <td><span class="optional">Hayır</span></td>
                            <td>Depo ID'si (belirtilmezse ana depo kullanılır)</td>
                        </tr>
                    </tbody>
                </table>
                
                <h4>Örnek İstek</h4>
                <pre>{
    "productId": 123,
    "quantity": 10,
    "updateType": "set"
}</pre>
                
                <h4>Yanıt</h4>
                <pre>{
    "success": true,
    "message": "Ürün stok bilgileri güncellendi",
    "data": {
        "productId": 123,
        "barcode": "8697123456789",
        "productName": "Kırmızı Kalem",
        "previousStock": 5,
        "currentStock": 10,
        "updateType": "set"
    }
}</pre>
            </div>
        </div>
        
        <div class="section">
            <h2>Test ve Geliştirme</h2>
            <p>API'yi test etmek ve izlemek için aşağıdaki araçları kullanabilirsiniz:</p>
            
            <p><a href="dashboard.php" class="btn">Dashboard'a Git</a></p>
            
            <h3>İstek Örnekleri</h3>
            <p>cURL kullanarak API'yi test etmek için:</p>
            
            <pre>curl -X POST \
  https://pos.incikirtasiye.com/admin/api/birfatura/orders.php \
  -H 'Content-Type: application/json' \
  -H 'Token: {token}' \
  -d '{
    "startDateTime": "01.04.2023 00:00:00",
    "endDateTime": "30.04.2023 23:59:59"
}'</pre>
        </div>
    </div>
</body>
</html>