/**
 * POS (Point of Sale) Satış Sistemi
 * ---------------------------------
 * Kasiyerler için satış işlemleri ve müşteri yönetimi
 */

// Global değişkenler
let sepet = []; // Sepetteki ürünler
let seciliMusteri = null; // Seçili müşteri bilgileri
let bekleyenFisler = []; // Bekletilen fişler listesi
let seciliOdemeYontemi = null; // Seçili ödeme yöntemi
let indirimTuru = 'yuzde'; // İndirim türü: 'yuzde' veya 'tutar'
let indirimSekli = 'genel'; // İndirim şekli: 'genel' veya 'urun'
let kullanilacakPuan = 0; // Kullanılacak puan miktarı
let islemTuru = 'satis'; // İşlem türü: 'satis' veya 'iade'
let fisNo = ''; // Fiş numarası
let yeniBorc_urunler = [];
// Raporlar ile ilgili değişkenler
let reportCurrentPage = 1;
let reportTotalPages = 1;
let reportSalesData = [];


// Sayfa yüklendiğinde
$(document).ready(function() {
    // Fiş numarası oluştur
    fisNo = generateFisNo();
    $('#fisNo').val(fisNo);
	
	    // Kasiyer ve mağaza seçimlerini boş olarak ayarla
    $('#kasiyer').val('');
    $('#magaza').val('');
    
    // Barkod input'una fokusla
    $('#barkodInput').focus();
    
    // Event listener'ları başlat
    initEventListeners();
    
    // Kısayol ürünleri yükle
    loadKisayolUrunler();
    
    // Local storage'dan bekleyen fişleri yükle
    loadBekleyenFisler();
	
	updateDateTime();
    
    // Her 30 saniyede bir tarih ve saati güncelle
    setInterval(updateDateTime, 30000);
	
	    // Mevcut kodlara ek olarak, müşteri bilgi kutusuna kapatma butonu ekle
    const closeButton = $('<button>', {
    'class': 'text-red-500 hover:text-red-700',
    'id': 'btnMusteriKapat',
    'html': '<i class="fas fa-times"></i>'
});

    // Müşteri bilgi kutusunu pozisyon relative yap ki kapatma butonu düzgün konumlandırılsın
    $('#seciliMusteri').addClass('relative');
    
    // Kapatma butonunu müşteri bilgi kutusuna ekle
    $('#seciliMusteri').append(closeButton);
    
    // Müşteri kapatma butonuna click event ekle
    $(document).on('click', '#btnMusteriKapat', function() {
        clearCustomer();
    });

   const style = `
        #seciliMusteri.relative {
            position: relative;
            padding-right: 10px; /* Sağ kenar boşluğunu azaltalım */
        }
        
        #btnMusteriKapat {
            position: absolute;
            top: 8px;
            right: 8px;
            padding: 4px;
            border-radius: 50%;
            transition: all 0.2s;
            font-size: 14px;
            line-height: 1;
            background: rgba(255, 255, 255, 0.7);
        }
        
        #btnMusteriKapat:hover {
            background-color: rgba(220, 38, 38, 0.2);
        }
		
		#musteriPuan{
			padding-right:10px;
		}
    `;
    
    // Stili head'e ekle
    $('head').append($('<style>').html(style));
});

/**
 * Event listener'ları başlat
 */
function initEventListeners() {
    // Barkod okutma/arama (Enter tuşu)
    $('#barkodInput').on('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const barkod = $(this).val().trim();
            if (barkod) {
                getUrunByBarkod(barkod);
                $(this).val('').focus();
            }
        }
    });
    
    // Ürün arama butonu
    $('#btnUrunAra').on('click', function() {
        const barkod = $('#barkodInput').val().trim();
        if (barkod) {
            getUrunByBarkod(barkod);
            $('#barkodInput').val('').focus();
        } else {
            // Barkod yoksa stok modalını aç
            openStokModal();
        }
    });
    
    // Stok görüntüleme butonu
    $('#btnStokGor').on('click', function() {
        openStokModal();
    });
    
    // Ürün kısayol butonları
    $(document).on('click', '.urun-kisayol', function() {
        const urunId = $(this).data('id');
        if (urunId) {
            getUrunById(urunId);
        }
    });
	
	
	// Sayfa yüklendiğinde çalışacak kod
$(document).ready(function() {
    console.log("Basit ürün tekrarı kontrolü yükleniyor...");
    
    // Stil ekleyin
    $("<style>")
        .prop("type", "text/css")
        .html(`
            .sepet-row-highlight {
                background-color: #ffeeba !important;
            }
            .sepet-row-blink {
                animation: highlight-blink 1s ease-in-out 3;
            }
            @keyframes highlight-blink {
                0% { background-color: #ffeeba; }
                50% { background-color: #ffe066; }
                100% { background-color: #ffeeba; }
            }
        `)
        .appendTo("head");
    
    // Orijinal addToCart fonksiyonunu yedekle
    var originalAddToCart = window.addToCart;
    
    // addToCart fonksiyonunu yeniden tanımla
    window.addToCart = function(urun) {
        console.log("Ürün tekrarı kontrolü ile addToCart çağrıldı:", urun.ad);
        
        // Ürün sepette var mı kontrol et
        var existingIndex = sepet.findIndex(function(item) { 
            return item.id === urun.id; 
        });
        
        if (existingIndex !== -1) {
            // Ürün sepette varsa uyarı göster
            showToast(urun.ad + ' zaten sepette bulunuyor!', 'warning');
            
            // Sepet satırını vurgula
            highlightCartRow(existingIndex);
            
            return;
        }
        
        // Ürün sepette yoksa orijinal fonksiyonu çağır
        originalAddToCart(urun);
    };
    
    console.log("Basit ürün tekrarı kontrolü yüklendi!");
});

	// Sayfa tamamen yüklendikten sonra çalışacak kod
$(document).ready(function() {
    console.log("Document Ready - Kısayol düzeltmesi yükleniyor...");
    
    // ÖNEMLİ: Tüm kısayol event listener'larını kaldır
    $(document).off('click', '.urun-kisayol');
    $('.urun-kisayol').off('click');
    
    // TEK bir event listener tanımla
    $(document).on('click', '.urun-kisayol', function(e) {
        // Öncelikle olayın yayılmasını durdur
        e.preventDefault();
        e.stopPropagation();
        
        console.log("Kısayol tıklama olayı - TEK");
        
        // Ürün ID'sini al
        const urunId = $(this).data('id');
        console.log("Tıklanan ürün ID:", urunId);
        
        if (urunId) {
            // Ürünü getir ve sepete ekle
            getUrunByIdFixed(urunId);
        }
        
        // Olayı burada durdur
        return false;
    });
    
    // Orijinal loadKisayolUrunler fonksiyonunu override et
    window.originalLoadKisayolUrunler = window.loadKisayolUrunler;
    window.loadKisayolUrunler = function() {
        console.log("Düzeltilmiş loadKisayolUrunler çağrıldı");
        
        // Yükleniyor göstergesini göster
        $('#urunKisayolContainer').html(`
            <div class="col-span-full text-center py-4">
                <i class="fas fa-sync fa-spin text-gray-400"></i>
                <div class="text-sm text-gray-500 mt-1">Kısayollar yükleniyor...</div>
            </div>
        `);

        // API'den kısayol ürünlerini getir
        $.ajax({
            url: 'admin/api/get_product_shortcuts.php',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                console.log("Kısayol yanıtı:", response);
                if (response.success) {
                    renderProductShortcutsFixed(response.shortcuts || []);
                } else {
                    console.error('Kısayol ürünleri alınamadı:', response.message);
                    renderProductShortcutsFixed([]);
                }
            },
            error: function(xhr, status, error) {
                console.error('Kısayol ürünleri alınırken bir hata oluştu:', status, error);
                console.log('XHR yanıtı:', xhr.responseText);
                renderProductShortcutsFixed([]);
            },
            timeout: 10000
        });
    };
    
    console.log("Kısayol düzeltmesi yüklendi!");
});

    // Modal Kapatma Butonları
$('.modal-close').on('click', function() {
    closeAllModals();
    // Barkod inputuna odaklan
    setTimeout(function() {
        $('#barkodInput').focus();
    }, 100);
});
	
    // Sepette miktar artırma/azaltma
    $(document).on('click', '.btn-miktar-azalt', function() {
        const index = $(this).data('index');
        updateCartItemQuantity(index, -1);
    });
    
    $(document).on('click', '.btn-miktar-artir', function() {
        const index = $(this).data('index');
        updateCartItemQuantity(index, 1);
    });
    
    // Sepetten ürün çıkartma
    $(document).on('click', '.btn-urun-cikar', function() {
        const index = $(this).data('index');
        removeFromCart(index);
    });
    
    // Sepette miktar yazarak değiştirme
    $(document).on('change', '.urun-miktar-input', function() {
        const index = $(this).data('index');
        const value = parseInt($(this).val());
        
        if (!isNaN(value) && value > 0) {
            sepet[index].miktar = value;
            sepet[index].toplam = calculateItemTotal(sepet[index]);
            updateSepetUI();
            updateSepetTotals();
        } else {
            // Geçersiz değer girilirse eski değere geri dön
            $(this).val(sepet[index].miktar);
        }
    });
	
	$(document).ready(function() {
    // Kısayol ürünü arama işlevini bağla
    $(document).on('input', '#shortcutProductSearch, [placeholder="Ürün adı, barkod veya kodu ile ara..."]', function() {
        const term = $(this).val().trim();
        if (term.length >= 2) {
            searchProductsForShortcut(term);
        }
    });
    
    // Arama input'una enter tuşuna basınca arama yapma özelliği ekle
    $(document).on('keydown', '#shortcutProductSearch, [placeholder="Ürün adı, barkod veya kodu ile ara..."]', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const term = $(this).val().trim();
            if (term.length >= 2) {
                searchProductsForShortcut(term);
            }
        }
    });
});
    
    // Fiş İptal Butonu
    $('#btnFisIptal').on('click', function() {
        if (sepet.length > 0) {
            if (confirm('Fiş iptal edilecek. Emin misiniz?')) {
                resetSepet();
                showToast('Fiş iptal edildi', 'warning');
            }
        } else {
            showToast('İptal edilecek fiş bulunamadı', 'error');
        }
    });
	
	$(document).ready(function() {
    // Raporlar butonuna tıklama
    $(document).on('click', '#btnRaporlar', function() {
        openRaporlarModal();
    });
    
    // Filtrele butonuna tıklama
    $(document).on('click', '#btnRaporFiltrele', function() {
        reportCurrentPage = 1; // Sayfa numarasını sıfırla
        loadReportData();
    });
    
    // Sayfalama butonlarına tıklama
    $(document).on('click', '#prevPageBtn', function() {
        if (!$(this).prop('disabled')) {
            prevReportPage();
        }
    });
    
    $(document).on('click', '#nextPageBtn', function() {
        if (!$(this).prop('disabled')) {
            nextReportPage();
        }
    });
});
    
    // Müşteri Seçme Butonu
    $('#btnMusteriSecim').on('click', function() {
        openMusteriSecModal();
    });
    
    // Müşteri arama alanı
    $('#musteriAra').on('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            openMusteriSecModal();
        }
    });
    
    // Modal Kapatma Butonları
    $('.modal-close').on('click', function() {
        closeAllModals();
    });
    
    // Müşteri arama inputu (modal içinde)
    $('#musteriAraInput').on('input', function() {
        const term = $(this).val().trim();
        if (term.length >= 2) {
            searchCustomers(term);
        }
    });
    
    // Stok arama inputu (modal içinde)
    $('#stokAraInput').on('input', function() {
        const term = $(this).val().trim();
        if (term.length >= 2) {
            searchProducts(term);
        }
    });
    
    // Ürün seçme (stok modal içinde)
    $(document).on('dblclick', '.urun-sec-row', function() {
        const urunId = $(this).data('id');
        getUrunById(urunId);
        closeAllModals();
    });
    
    // Müşteri seçme (müşteri modal içinde)
    $(document).on('click', '.btn-musteri-sec', function() {
        const musteriId = $(this).data('id');
        selectCustomer(musteriId);
    });
    
    // Yeni müşteri butonu
    $('#btnYeniMusteri').on('click', function() {
        closeAllModals();
        openYeniMusteriModal();
    });
    
    // Yeni müşteri formu submit
    $('#yeniMusteriForm').on('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        addNewCustomer(formData);
    });
    
    // İndirim butonu
    $('#btnIndirim').on('click', function() {
        if (sepet.length > 0) {
            openIndirimModal();
        } else {
            showToast('Sepette ürün bulunmamaktadır', 'error');
        }
    });
    
    // İndirim türü seçme
    $('.indirim-turu').on('click', function() {
        $('.indirim-turu').removeClass('bg-blue-500 text-white').addClass('bg-gray-200 text-gray-700');
        $(this).removeClass('bg-gray-200 text-gray-700').addClass('bg-blue-500 text-white');
        
        if ($(this).attr('id') === 'indirimTuruYuzde') {
            indirimTuru = 'yuzde';
            $('#indirimAciklama').text('Yüzde olarak indirim değeri giriniz (0-100)');
        } else {
            indirimTuru = 'tutar';
            $('#indirimAciklama').text('Tutar olarak indirim değeri giriniz (TL)');
        }
    });
    
    // İndirim şekli seçme
    $('.indirim-sekli').on('click', function() {
        $('.indirim-sekli').removeClass('bg-blue-500 text-white').addClass('bg-gray-200 text-gray-700');
        $(this).removeClass('bg-gray-200 text-gray-700').addClass('bg-blue-500 text-white');
        
        if ($(this).attr('id') === 'indirimSekilGenel') {
            indirimSekli = 'genel';
            $('#indirimUrunSec').addClass('hidden');
        } else {
            indirimSekli = 'urun';
            $('#indirimUrunSec').removeClass('hidden');
            updateIndirimUrunSelect();
        }
    });
    
    // İndirim uygulama butonu
    $('#btnIndirimUygula').on('click', function() {
        const deger = parseFloat($('#indirimDegeri').val());
        
        if (isNaN(deger) || deger <= 0) {
            showToast('Geçerli bir indirim değeri giriniz', 'error');
            return;
        }
        
        if (indirimTuru === 'yuzde' && deger > 100) {
            showToast('Yüzde indirim 100\'den büyük olamaz', 'error');
            return;
        }
        
        applyDiscount(deger);
        closeAllModals();
    });
    
    // Bekletme butonu
    $('#btnBeklet').on('click', function() {
        if (sepet.length > 0) {
            openBekletModal();
        } else {
            showToast('Bekletilecek ürün bulunmamaktadır', 'error');
        }
    });
    
    // Fişi beklet butonu
    $('#btnFisiBeklet').on('click', function() {
        if (sepet.length > 0) {
            const not = $('#bekletmeNotu').val().trim();
            saveFisToBekleyen(not);
            closeAllModals();
            resetSepet();
            showToast('Fiş bekletildi', 'success');
        }
    });
	
	$(document).on('click', '#btnBekleyenFisler', function() {
    showBekleyenFisler();
});

// Bekleyen fiş yükleme butonu
$(document).on('click', '.btn-fis-yukle', function() {
    const fisId = $(this).data('id');
    
    // Mevcut sepeti kontrol et
    if (sepet.length > 0) {
        if (!confirm('Mevcut sepet temizlenecek. Devam etmek istiyor musunuz?')) {
            return;
        }
    }
    
    // Fiş yükle
    const loaded = loadBekleyenFis(fisId);
    
    if (loaded) {
        // Modalı kapat
        $('#bekleyenFislerModal').addClass('hidden');
    }
});

// Bekleyen fiş silme butonu
$(document).on('click', '.btn-fis-sil', function() {
    const fisId = $(this).data('id');
    
    if (confirm('Bu bekleyen fişi silmek istediğinize emin misiniz?')) {
        // Bekleyen fişi sil
        removeBekleyenFis(fisId);
        
        // Listeyi güncelle
        showBekleyenFisler();
        
        showToast('Bekleyen fiş silindi');
    }
});
    
    // Ayarlar butonu
    $('#btnAyarlar').on('click', function() {
        openAyarlarModal();
    });
    
    // Ödeme alma butonu
    $('#btnOdemeAl').on('click', function() {
        if (sepet.length > 0) {
            openOdemeModal();
        } else {
            showToast('Sepette ürün bulunmamaktadır', 'error');
        }
    });
	
	
	
	// Müşteri düzenleme butonu click event'i (modal içindeki her satır için)
$(document).on('click', '.btn-musteri-duzenle', function(e) {
    e.preventDefault();
    const musteriId = $(this).data('id');
    
    // Müşteri seçim modalını kapatıp düzenleme modalını açalım
    $('#musteriSecModal').addClass('hidden');
    openMusteriDuzenleModal(musteriId);
});
	
	// Müşteri borç detayı görüntüleme butonu
$('#btnMusteriBorcDetay').on('click', function() {
    if (seciliMusteri) {
        openMusteriBorcModal(seciliMusteri.id);
    } else {
        showToast('Önce müşteri seçmelisiniz', 'warning');
    }
});

// Yeni borç ekle butonu için
$(document).on('click', '#btnYeniBorc', function() {
    if (seciliMusteri) {
        openYeniBorcModal();
    } else {
        showToast('Önce müşteri seçmelisiniz', 'warning');
    }
});

// İndirim tutarı değişince toplam tutarı güncelle
$(document).on('input', '#yeniBorc_indirim', function() {
    updateYeniBorcToplam();
});


// Borç ödeme butonu (müşteri borç modalında)
$(document).on('click', '.btn-borc-ode', function() {
    const borcId = $(this).data('id');
    const kalanTutar = parseFloat($(this).data('kalan') || 0);
    openBorcOdemeModal(borcId, kalanTutar);
});

// Borç ödeme formu submit
$('#btnBorcOdemeYap').on('click', function() {
    const borcId = $('#borcOdeme_borcId').val();
    const kalanTutar = parseFloat($(this).data('kalan') || 0);
    
    // Form verilerini oluştur
    const formData = {
        borc_id: parseInt(borcId),
        odeme_tutari: parseFloat($('#borcOdeme_tutar').val()),
        odeme_tarihi: $('#borcOdeme_tarih').val(),
        odeme_yontemi: $('#borcOdeme_yontem').val(),
        aciklama: $('#borcOdeme_aciklama').val()
    };
    
    // Ödeme tutarı kontrolü
    if (isNaN(formData.odeme_tutari) || formData.odeme_tutari <= 0) {
        showToast('Geçerli bir ödeme tutarı giriniz', 'error');
        return;
    }
    
    if (formData.odeme_tutari > kalanTutar) {
        showToast('Ödeme tutarı kalan borçtan fazla olamaz', 'error');
        return;
    }
    
    // Ödeme işlemini yap
    submitBorcOdeme(formData);
});

$(document).on('click', '.btn-select-shortcut-product', function() {
    const productId = $(this).data('id');
    
    // Her iki kaynaktan da pozisyon bilgisini alalım (güvenlik için)
    const position = $('#productSelectionModal').data('position');
    const hiddenPosition = $('#hidden-position-input').val();
    
    console.log("Seçilen ürün ID:", productId);
    console.log("Modal pozisyon data attribute:", position);
    console.log("Hidden input pozisyon:", hiddenPosition);
    
    // En güvenilir pozisyon bilgisini kullan
    const finalPosition = position !== undefined ? position : parseInt(hiddenPosition);
    
    // Ürün seçme işlemini çağır
    selectProductForShortcut(productId, finalPosition);
});

$(document).on('click', '#btnGecmisSiparisler', function() {
    openGecmisSiparislerModal();
});

$(document).on('click', '#selectAllOrders', function() {
    $('.siparis-checkbox').prop('checked', $(this).prop('checked'));
});

$(document).on('click', '#btnSiparisleriEkle', function() {
    addSelectedOrdersToDebt();
});

// Detay butonuna tıklama olayını ayrıca dinleyin
$(document).on('click', '.btn-siparis-detay', function(e) {
    e.preventDefault();
    const siparisId = $(this).data('id');
    openSiparisDetayModal(siparisId);
});

$(document).ready(function() {
    // Modalların z-index değerlerini ayarla
    $('#raporlarModal').css('z-index', '50');
    $('#siparisDetayModal').css('z-index', '60');
    
    // Detay butonlarına tıklama olayı
    $(document).on('click', '.btn-siparis-detay', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const siparisId = $(this).data('id');
        viewSaleDetails(siparisId);
    });
    
    // Yazdır butonlarına tıklama olayı
    $(document).on('click', '.btn-siparis-yazdir', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const siparisId = $(this).data('id');
        printReceipt(siparisId);
    });
});

// Ürün seç butonuna tıklandığında gerçekleşecek işlem
$(document).on('click', '.btn-select-shortcut-product', function() {
    const productId = $(this).data('id');
    // Position değerini modal'dan al
    const position = $('#productSelectionModal').data('position');
    
    // Ürün seçme işlemini çağır
    selectProductForShortcut(productId, position);
    
    // YALNIZCA ürün seçim modalını kapat, diğer modalları kapatma
    $('#productSelectionModal').addClass('hidden');
});

// Kapat butonuna tıklandığında sadece ürün seçim modalını kapat
$('#productSelectionModal .modal-close, #productSelectionModal button.kapat').on('click', function() {
    $('#productSelectionModal').addClass('hidden');
});

// Ürün kısayol butonları için event listener düzeltmesi
$(document).ready(function() {
    // Önce mevcut event listener'ları temizle (çift tıklama sorununu önlemek için)
    $(document).off('click', '.urun-kisayol');
    
    // Yeni event listener ekle (bir kez)
    $(document).on('click', '.urun-kisayol', function(e) {
        // Event'in yayılmasını durdur (event bubbling'i önlemek için)
        e.stopPropagation();
        
        // Ürün ID'sini al
        const urunId = $(this).data('id');
        console.log('Kısayola tıklandı: Ürün ID = ' + urunId); // Debug için log
        
        if (urunId) {
            // Ürünü bir kez getir ve sepete ekle
            getUrunById(urunId);
        }
        
        // Tıklamayı burada durdur
        return false;
    });
});

// Detay butonuna tıklama olayı
$(document).on('click', '.btn-siparis-detay', function(e) {
    e.preventDefault();
    const siparisId = $(this).data('id');
    openSiparisDetayModal(siparisId);
});

// Kapat butonlarına tıklama olayı
$(document).on('click', '.modal-close, #siparisDetayModal button.kapat', function() {
    $('#siparisDetayModal').addClass('hidden');
});

// Özel olarak Sipariş Detayları modalındaki Kapat düğmesi için
$(document).on('click', '#siparisDetayModal .kapat', function() {
    $('#siparisDetayModal').addClass('hidden');
});

// "Seçilen Siparişleri Ekle" butonu için event handler
$(document).on('click', '#btnSiparisleriEkle, .modal-close, button.kapat', function() {
    closeAllModals();
});
    
// Ödeme modalında borç seçildiğinde kontrol edelim
$('.odeme-yontemi').on('click', function() {
    const yontem = $(this).data('yontem');
    seciliOdemeYontemi = yontem;
    
    // Tüm ödeme alanlarını gizle
    $('.odeme-alani').addClass('hidden');
    
    // Butonları normal göster, seçili olanı vurgula
    $('.odeme-yontemi').removeClass('bg-green-600').addClass('bg-blue-500');
    $(this).removeClass('bg-blue-500').addClass('bg-green-600');
    
    // Seçili ödeme yöntemine göre alanı göster
    if (yontem === 'nakit') {
        $('#nakitOdeme').removeClass('hidden');
        $('#alinanTutar').val('').focus();
    } else if (yontem === 'kredi_karti') {
        $('#krediKartiOdeme').removeClass('hidden');
    } else if (yontem === 'borc') {
        $('#borcEkle').removeClass('hidden');
        
        // Müşteri seçili değilse uyarı ver
        if (!seciliMusteri) {
            $('#borcMusteri').html('<div class="text-red-600">Borç eklemek için müşteri seçmelisiniz!</div>');
            showToast('Borç eklemek için önce müşteri seçmelisiniz', 'warning');
        } else {
            $('#borcMusteri').html(`
                <div class="text-blue-800 font-medium">
                    <div>${seciliMusteri.ad} ${seciliMusteri.soyad} müşterisine borç olarak kaydedilecek</div>
                    <div class="text-sm mt-1">Telefon: ${seciliMusteri.telefon || "Belirtilmemiş"}</div>
                </div>
            `);
            
            // Müşterinin mevcut borçlarını al
            getMusteriBorc(seciliMusteri.id);
        }
    }
});

    
    // Nakit ödeme için tutar girişi
    $('#alinanTutar').on('input', function() {
        const alinan = parseFloat($(this).val()) || 0;
        const odenecek = getOdenecekTutar();
        
        if (alinan >= odenecek) {
            const paraUstu = alinan - odenecek;
            $('#paraUstu').text(formatPrice(paraUstu));
        } else {
            $('#paraUstu').text('0,00 ₺');
        }
    });
    
/**
 * Ödeme tamamlama butonunda borç kontrolü
 */
$('#btnOdemeTamamla').on('click', function() {
	    // Kasiyer ve mağaza kontrolü
    if (!$('#kasiyer').val() || !$('#magaza').val()) {
        showToast('Lütfen kasiyer ve mağaza seçimi yapın', 'error');
        return;
    }
    if (!seciliOdemeYontemi) {
        showToast('Lütfen ödeme yöntemi seçiniz', 'error');
        return;
    }
    
    const odenecek = getOdenecekTutar();
    
    if (seciliOdemeYontemi === 'nakit') {
        // Nakit kontrolünü kaldır - alınan tutar girilmese bile işleme devam et
        const alinan = parseFloat($('#alinanTutar').val()) || odenecek; // Eğer değer girilmezse, ödenecek tutarı varsay
        
        // Para üstü negatif olmamalı, sadece bilgilendirme amaçlı kontrol
        if (alinan < odenecek) {
            if(!confirm('Girilen tutar ödenecek tutardan az. Devam etmek istiyor musunuz?')) {
                return;
            }
        }
    } else if (seciliOdemeYontemi === 'borc') {
        // Müşteri seçili değilse borç eklenemez
        if (!seciliMusteri) {
            showToast('Borç eklemek için müşteri seçmelisiniz', 'error');
            return;
        }
        
        // Borç onayı soralım
        if (!confirm(`${seciliMusteri.ad} ${seciliMusteri.soyad} adlı müşteriye ${formatPrice(odenecek)} tutarında borç eklenecek. Onaylıyor musunuz?`)) {
            return;
        }
    }
    
    // Ödeme işlemini tamamla
    completeSale();
});
    
    // Puan kullanma butonu
    $('#btnPuanKullan').on('click', function() {
        const puan = parseFloat($('#kullanilacakPuan').val()) || 0;
        if (puan > 0) {
            if (seciliMusteri && seciliMusteri.puan_bakiye >= puan) {
                kullanilacakPuan = puan;
                $('#puanKullanim').addClass('hidden');
                updateSepetTotals();
                showToast(`${puan} puan kullanıldı`);
            } else {
                showToast('Yetersiz puan bakiyesi', 'error');
            }
        }
    });
    
    // İşlem türü değişikliği
    $('#islemTuru').on('change', function() {
        islemTuru = $(this).val();
        // İade işlemi için farklı görsel stil
        if (islemTuru === 'iade') {
            $('body').addClass('iade-mode');
        } else {
            $('body').removeClass('iade-mode');
        }
    });
	
	    // Büyük miktarda artırma butonları
    $(document).on('click', '.btn-miktar-x10', function() {
        const index = $(this).data('index');
        updateCartItemQuantity(index, 10); // 10 adet ekle
    });
    
    $(document).on('click', '.btn-miktar-x100', function() {
        const index = $(this).data('index');
        updateCartItemQuantity(index, 100); // 100 adet ekle
    });
    
    // Ürün miktarını doğrudan yazarak değiştirme (geliştirilmiş)
    $(document).on('change', '.urun-miktar-input', function() {
        const index = $(this).data('index');
        const newQuantity = parseInt($(this).val());
        
        if (!isNaN(newQuantity) && newQuantity > 0) {
            // Mevcut miktarı al
            const oldQuantity = sepet[index].miktar;
            
            // Miktarı direkt olarak ayarla
            sepet[index].miktar = newQuantity;
            sepet[index].toplam = calculateItemTotal(sepet[index]);
            
            updateSepetUI();
            updateSepetTotals();
            
            // Bildirim göster
            const change = newQuantity - oldQuantity;
            if (change > 0) {
                showToast(`${sepet[index].ad} miktarı ${change} adet artırıldı`);
            } else if (change < 0) {
                showToast(`${sepet[index].ad} miktarı ${Math.abs(change)} adet azaltıldı`);
            }
        } else {
            // Geçersiz değer girilirse eski değere geri dön
            $(this).val(sepet[index].miktar);
            showToast('Geçersiz miktar', 'error');
        }
    });
    
    // Miktar inputunda Enter tuşuna basıldığında odağı barkod girişine geri al
    $(document).on('keydown', '.urun-miktar-input', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            $('#barkodInput').focus();
        }
    });
}

function getUrunByIdFixed(id) {
    console.log("DÜZELTİLMİŞ getUrunById çağrıldı:", id);
    
    $.ajax({
        url: 'admin/api/get_product_details.php',
        type: 'GET',
        data: { id: id },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.product) {
                console.log("Ürün bilgileri alındı:", response.product.ad);
                addToCartFixed(response.product);
            } else {
                showToast('Ürün bulunamadı', 'error');
            }
        },
        error: function() {
            showToast('Ürün getirme sırasında bir hata oluştu', 'error');
        }
    });
}

// Düzeltilmiş renderProductShortcuts fonksiyonu
function renderProductShortcutsFixed(shortcuts) {
    console.log("DÜZELTİLMİŞ renderProductShortcuts çağrıldı, ürün sayısı:", shortcuts.length);
    
    // Kısayol container'ını temizle
    const container = $('#urunKisayolContainer');
    container.empty();
    
    // Hiç kısayol yoksa bilgi mesajı göster
    if (shortcuts.length === 0) {
        container.html(`
            <div class="col-span-full text-center py-2">
                <div class="text-sm text-gray-500">Kısayol ürünü bulunmamaktadır.</div>
                <div class="text-xs text-gray-400 mt-1">Ayarlar → Kısayollar menüsünden ekleyebilirsiniz.</div>
            </div>
        `);
        return;
    }
    
    // Kısayol butonlarını oluştur
    for (let i = 0; i < Math.max(shortcuts.length, 12); i++) {
        // Bu pozisyonda bir kısayol var mı bul
        const shortcut = shortcuts.find(s => s.position === i);
        
        // HTML oluştur
        if (shortcut && shortcut.product) {
            container.append(`
                <button class="urun-kisayol bg-blue-100 hover:bg-blue-200 text-blue-800 py-2 px-3 rounded text-sm" data-id="${shortcut.product_id}">
                    <div class="font-medium truncate">${shortcut.product.ad}</div>
                    <div class="text-xs">${formatPrice(shortcut.product.satis_fiyati)}</div>
                </button>
            `);
        } else if (i < 12) { // Sadece ilk 12 pozisyon için boş buton göster
            // Boş buton
            container.append(`
                <button class="bg-gray-100 text-gray-400 py-2 px-3 rounded text-sm" disabled>
                    <i class="fas fa-plus-circle"></i>
                </button>
            `);
        }
    }
    
    console.log("Kısayol butonları oluşturuldu.");
}

// Hemen kısayolları yeniden yükle
setTimeout(function() {
    console.log("Kısayollar yeniden yükleniyor...");
    window.loadKisayolUrunler();
}, 1000);

/**
 * Sepet satırını vurgulama
 */
function highlightCartRow(index) {
    setTimeout(function() {
        // İlgili satırı bul
        var $rows = $("#sepetListesi tr");
        if ($rows.length > index) {
            var $row = $rows.eq(index);
            
            // Vurgula
            $row.addClass("sepet-row-highlight sepet-row-blink");
            
            // Görünür olduğundan emin ol
            $row[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
            
            // Animasyonu temizle
            setTimeout(function() {
                $row.removeClass("sepet-row-blink");
                
                // Bir süre sonra vurguyu da kaldır
                setTimeout(function() {
                    $row.removeClass("sepet-row-highlight");
                }, 2000);
            }, 3000);
        }
    }, 100);
}

// Düzeltilmiş addToCart fonksiyonu
function addToCartFixed(urun) {
    console.log("DÜZELTİLMİŞ addToCart çağrıldı:", urun.ad);
    
    // Ürün sepette var mı kontrol et
    const existingIndex = sepet.findIndex(item => item.id === urun.id);
    
    if (existingIndex !== -1) {
        // Ürün sepette varsa miktarını artır (tam olarak 1 adet)
        console.log("Ürün sepette bulundu. Miktar artırılıyor:", sepet[existingIndex].miktar, "→", sepet[existingIndex].miktar + 1);
        sepet[existingIndex].miktar += 1;
        sepet[existingIndex].toplam = calculateItemTotal(sepet[existingIndex]);
    } else {
        // Ürün sepette yoksa ekle
        console.log("Ürün sepette bulunamadı. Yeni ürün ekleniyor.");
        sepet.push({
            id: urun.id,
            barkod: urun.barkod,
            kod: urun.kod,
            ad: urun.ad,
            miktar: 1, // Miktar kesinlikle 1 olarak ayarlanıyor
            birim_fiyat: parseFloat(urun.satis_fiyati),
            indirim: 0,
            indirim_turu: null,
            toplam: parseFloat(urun.satis_fiyati),
            kdv_orani: parseFloat(urun.kdv_orani)
        });
    }
    
    // Sepeti güncelle
    updateSepetUI();
    updateSepetTotals();
    
    // Bildirim göster
    showToast(`${urun.ad} sepete eklendi`);
    
    // Barkod alanına fokusla
    $('#barkodInput').focus();
}


/**
 * Müşterinin mevcut borçlarını getir
 * @param {number} musteriId - Müşteri ID
 */
function getMusteriBorc(musteriId) {
    $.ajax({
        url: 'admin/api/get_customer_credits.php',
        type: 'GET',
        data: { id: musteriId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Özet borç bilgilerini göster
                let borc_html = '';
                
                if (response.summary && response.summary.odenmemis_borc > 0) {
                    borc_html = `
                        <div class="mt-2 p-2 bg-red-50 border border-red-200 rounded">
                            <div class="text-red-700"><strong>Mevcut Borç:</strong> ${formatPrice(response.summary.odenmemis_borc)}</div>
                            <div class="text-xs text-red-600">Bu satış sonrası toplam borç ${formatPrice(parseFloat(response.summary.odenmemis_borc) + getOdenecekTutar())} olacaktır</div>
                        </div>
                    `;
                } else {
                    borc_html = `
                        <div class="mt-2 p-2 bg-green-50 border border-green-200 rounded">
                            <div class="text-green-700">Müşterinin mevcut borcu bulunmamaktadır</div>
                        </div>
                    `;
                }
                
                // Borç bilgilerini göster
                $('#borcMusteri').append(borc_html);
                
            } else {
                console.error('Müşteri borç bilgileri alınamadı');
            }
        },
        error: function() {
            console.error('Müşteri borç bilgileri alınırken bir hata oluştu');
        }
    });
}

/**
 * Müşteri borçlarını görüntüleme butonu
 * Müşteri seçim ekranına eklenebilir
 */
function viewCustomerCredits(musteriId) {
    // Müşteri borçlarını görüntüle
    $.ajax({
        url: 'admin/api/get_customer_credits.php',
        type: 'GET',
        data: { id: musteriId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Borç detaylarını içeren bir modal açabiliriz
                openCustomerCreditsModal(response.customer, response.credits, response.summary);
            } else {
                showToast('Müşteri borç bilgileri alınamadı', 'error');
            }
        },
        error: function() {
            showToast('Müşteri borç bilgileri alınırken bir hata oluştu', 'error');
        }
    });
}

/**
 * Müşteri borçları modalı
 */
function openCustomerCreditsModal(customer, credits, summary) {
    // Modal içeriğini oluştur
    let modalContent = `
        <div class="fixed inset-0 bg-gray-600 bg-opacity-75 flex items-center justify-center z-50" id="customerCreditsModal">
            <div class="bg-white rounded-lg shadow-xl w-11/12 md:w-3/4 lg:w-1/2 max-h-screen overflow-y-auto">
                <div class="flex justify-between items-center p-4 border-b">
                    <h3 class="text-lg font-bold">Müşteri Borçları: ${customer.ad} ${customer.soyad}</h3>
                    <button class="modal-close text-gray-500 hover:text-gray-700" onclick="closeCustomerCreditsModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="p-4">
                    <div class="mb-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-blue-50 p-3 rounded border border-blue-100">
                            <div class="text-sm text-blue-700">Toplam Borç</div>
                            <div class="text-lg font-bold">${formatPrice(summary.toplam_borc || 0)}</div>
                        </div>
                        <div class="bg-green-50 p-3 rounded border border-green-100">
                            <div class="text-sm text-green-700">Toplam Ödeme</div>
                            <div class="text-lg font-bold">${formatPrice(summary.toplam_odeme || 0)}</div>
                        </div>
                        <div class="bg-red-50 p-3 rounded border border-red-100">
                            <div class="text-sm text-red-700">Kalan Borç</div>
                            <div class="text-lg font-bold">${formatPrice(summary.odenmemis_borc || 0)}</div>
                        </div>
                    </div>
    `;
    
    // Borç listesi
    if (credits && credits.length > 0) {
        modalContent += `
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tarih</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Fiş No</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Mağaza</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Tutar</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Ödenen</th>
                            <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Durum</th>
                            <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">İşlem</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
        `;
        
        credits.forEach(credit => {
            const odenen = parseFloat(credit.odenen_tutar || 0);
const toplam = parseFloat(credit.toplam_tutar || 0);
const indirim = parseFloat(credit.indirim_tutari || 0);
const netTutar = toplam - indirim; // Net tutarı hesapla (indirim düşürülerek)
const kalan = netTutar - odenen;
            const tarih = new Date(credit.borc_tarihi).toLocaleDateString('tr-TR');
            
            modalContent += `
                <tr>
                    <td class="px-3 py-2">${tarih}</td>
                    <td class="px-3 py-2">${credit.fiş_no || '-'}</td>
                    <td class="px-3 py-2">${credit.magaza_adi || '-'}</td>
                    <td class="px-3 py-2 text-right">${formatPrice(toplam)}</td>
                    <td class="px-3 py-2 text-right">${formatPrice(odenen)}</td>
                    <td class="px-3 py-2 text-center">
                        ${credit.odendi_mi == 1 ? 
                            '<span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs">Ödendi</span>' : 
                            '<span class="px-2 py-1 bg-red-100 text-red-800 rounded-full text-xs">Ödenmedi</span>'
                        }
                    </td>
                    <td class="px-3 py-2 text-center">
                        ${credit.odendi_mi == 0 ? 
                            `<button onclick="openPaymentModal(${credit.borc_id}, ${kalan})" class="bg-blue-500 hover:bg-blue-600 text-white py-1 px-2 rounded text-xs">
                                Ödeme Al
                            </button>` : 
                            '-'
                        }
                    </td>
                </tr>
            `;
        });
        
        modalContent += `
                    </tbody>
                </table>
            </div>
        `;
    } else {
        modalContent += `
            <div class="py-4 text-center text-gray-500">
                Bu müşteriye ait borç kaydı bulunamadı
            </div>
        `;
    }
    
    // Modal kapanış
    modalContent += `
                </div>
                <div class="p-4 border-t flex justify-end">
                    <button class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded" onclick="closeCustomerCreditsModal()">
                        Kapat
                    </button>
                </div>
            </div>
        </div>
    `;
    
    // Modal'ı sayfaya ekle
    $('body').append(modalContent);
}

/**
 * Müşteri borçları modalını kapat
 */
function closeCustomerCreditsModal() {
    $('#customerCreditsModal').remove();
}

/**
 * Müşteri borçları modalını kapat
 */
function closeCustomerCreditsModal() {
    $('#customerCreditsModal').remove();
}

/**
 * Ödeme alma modalı
 */
function openPaymentModal(borcId, kalanTutar) {
    // Modal içeriğini oluştur
    let modalContent = `
        <div class="fixed inset-0 bg-gray-600 bg-opacity-75 flex items-center justify-center z-50" id="paymentModal">
            <div class="bg-white rounded-lg shadow-xl w-11/12 md:w-1/2 lg:w-1/3 max-h-screen overflow-y-auto">
                <div class="flex justify-between items-center p-4 border-b">
                    <h3 class="text-lg font-bold">Borç Ödeme</h3>
                    <button class="modal-close text-gray-500 hover:text-gray-700" onclick="closePaymentModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="p-4">
                    <div class="mb-4">
                        <div class="text-lg font-bold mb-2">Kalan Borç: ${formatPrice(kalanTutar)}</div>
                        <form id="odemeForm">
                            <input type="hidden" name="borc_id" value="${borcId}">
                            
                            <div class="mb-3">
                                <label class="block text-sm font-medium text-gray-700">Ödeme Tutarı *</label>
                                <input type="number" name="odeme_tutari" step="0.01" max="${kalanTutar}" required class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            
                            <div class="mb-3">
                                <label class="block text-sm font-medium text-gray-700">Ödeme Tarihi</label>
                                <input type="date" name="odeme_tarihi" value="${new Date().toISOString().split('T')[0]}" required class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            
                            <div class="mb-3">
                                <label class="block text-sm font-medium text-gray-700">Ödeme Yöntemi</label>
                                <select name="odeme_yontemi" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                    <option value="nakit">Nakit</option>
                                    <option value="kredi_karti">Kredi Kartı</option>
                                    <option value="havale">Havale/EFT</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="block text-sm font-medium text-gray-700">Açıklama</label>
                                <textarea name="aciklama" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" rows="2"></textarea>
                            </div>
                            
                            <div class="flex justify-end gap-2 mt-4">
                                <button type="button" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded" onclick="closePaymentModal()">
                                    İptal
                                </button>
                                <button type="submit" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded">
                                    Ödeme Al
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Modal'ı sayfaya ekle
    $('body').append(modalContent);
    
    // Form submit
    $('#odemeForm').on('submit', function(e) {
        e.preventDefault();
        
        // Form verilerini al
        const formData = {
            borc_id: parseInt($('input[name="borc_id"]').val()),
            odeme_tutari: parseFloat($('input[name="odeme_tutari"]').val()),
            odeme_tarihi: $('input[name="odeme_tarihi"]').val(),
            odeme_yontemi: $('select[name="odeme_yontemi"]').val(),
            aciklama: $('textarea[name="aciklama"]').val()
        };
        
        // Ödeme tutarını kontrol et
        if (formData.odeme_tutari <= 0 || formData.odeme_tutari > kalanTutar) {
            showToast('Geçersiz ödeme tutarı', 'error');
            return;
        }
        
        // Ödeme işlemini gerçekleştir
        $.ajax({
            url: 'admin/api/add_payment.php',
            type: 'POST',
            data: JSON.stringify(formData),
            contentType: 'application/json',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    closePaymentModal();
                    closeCustomerCreditsModal();
                    showToast('Ödeme başarıyla kaydedildi', 'success');
                    
                    // Eğer aktif müşteri olarak seçili ise, bilgileri güncelle
                    if (seciliMusteri && seciliMusteri.id) {
                        selectCustomer(seciliMusteri.id);
                    }
                } else {
                    showToast(response.message || 'Ödeme kaydedilirken bir hata oluştu', 'error');
                }
            },
            error: function() {
                showToast('Ödeme kaydedilirken bir hata oluştu', 'error');
            }
        });
    });
}

/**
 * Ödeme modalını kapat
 */
function closePaymentModal() {
    $('#paymentModal').remove();
}

/**
 * Barkod ile ürün arama
 * @param {string} barkod - Ürün barkodu veya kodu
 */
function getUrunByBarkod(barkod) {
    $.ajax({
        url: 'admin/api/search_product.php',
        type: 'GET',
        data: { term: barkod },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.products && response.products.length > 0) {
                const urun = response.products[0];
                addToCart(urun);
            } else {
                showToast('Ürün bulunamadı', 'error');
            }
        },
        error: function() {
            showToast('Ürün arama sırasında bir hata oluştu', 'error');
        }
    });
}

/**
 * ID ile ürün getir
 * @param {number} id - Ürün ID
 */
function getUrunById(id) {
    console.log('getUrunById çağrıldı: ID = ' + id); // Debug için log
    
    // Fonksiyonun yalnızca bir kez çağrıldığından emin olmak için
    $.ajax({
        url: 'admin/api/get_product_details.php',
        type: 'GET',
        data: { id: id },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.product) {
                // Ürünü sepete ekle (yalnızca bir kez)
                addToCart(response.product);
            } else {
                showToast('Ürün bulunamadı', 'error');
            }
        },
        error: function() {
            showToast('Ürün getirme sırasında bir hata oluştu', 'error');
        }
    });
}

/**
 * Tarih ve saat bilgisini günceller
 */
function updateDateTime() {
    const now = new Date();
    const formattedDate = now.toLocaleDateString('tr-TR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
    
    $('#tarih').val(formattedDate);
}

/**
 * Sepete ürün ekle
 * @param {Object} urun - Ürün bilgileri
 */
function addToCart(urun) {
    // Ürün sepette var mı kontrol et
    const existingIndex = sepet.findIndex(item => item.id === urun.id);
    
    if (existingIndex !== -1) {
        // Ürün sepette varsa miktarını artır
        sepet[existingIndex].miktar += 1;
        sepet[existingIndex].toplam = calculateItemTotal(sepet[existingIndex]);
    } else {
        // Ürün sepette yoksa ekle
        sepet.push({
            id: urun.id,
            barkod: urun.barkod,
            kod: urun.kod,
            ad: urun.ad,
            miktar: 1,
            birim_fiyat: parseFloat(urun.satis_fiyati),
            indirim: 0,
            indirim_turu: null,
            toplam: parseFloat(urun.satis_fiyati),
            kdv_orani: parseFloat(urun.kdv_orani)
        });
    }
    
    // Sepeti güncelle
    updateSepetUI();
    updateSepetTotals();
    
    // Bildirim göster
    showToast(`${urun.ad} sepete eklendi`);
    
    // Barkod alanına fokusla
    $('#barkodInput').focus();
}

/**
 * Sepetten ürün çıkart
 * @param {number} index - Ürünün sepetteki indeksi
 */
function removeFromCart(index) {
    if (index >= 0 && index < sepet.length) {
        const urun = sepet[index];
        sepet.splice(index, 1);
        updateSepetUI();
        updateSepetTotals();
        showToast(`${urun.ad} sepetten çıkarıldı`);
    }
}

/**
 * Sepet öğesinin miktarını güncelle
 * @param {number} index - Öğenin sepetteki indeksi
 * @param {number} change - Miktar değişim miktarı (+1 veya -1)
 */
function updateCartItemQuantity(index, change) {
    if (index >= 0 && index < sepet.length) {
        let newQuantity = sepet[index].miktar + change;
        if (newQuantity <= 0) {
            // Miktar 0 veya altına düşerse, ürünü sepetten çıkar
            removeFromCart(index);
        } else {
            sepet[index].miktar = newQuantity;
            sepet[index].toplam = calculateItemTotal(sepet[index]);
            updateSepetUI();
            updateSepetTotals();
        }
    }
}

/**
 * Ürün toplam fiyatını hesapla (indirimler dahil)
 * @param {Object} item - Sepet öğesi
 * @returns {number} - Toplam fiyat
 */
function calculateItemTotal(item) {
    let total = item.birim_fiyat * item.miktar;
    
    // İndirim varsa uygula
    if (item.indirim > 0) {
        if (item.indirim_turu === 'yuzde') {
            total = total * (1 - (item.indirim / 100));
        } else if (item.indirim_turu === 'tutar') {
            total = total - item.indirim;
        }
    }
    
    return parseFloat(total.toFixed(2));
}

/**
 * Miktar Girişi için HTML eklemesi
 */
function updateSepetUI() {
    let sepetHTML = '';
    
    if (sepet.length === 0) {
        sepetHTML = '<tr><td colspan="6" class="px-3 py-2 text-center text-gray-500">Sepette ürün bulunmamaktadır</td></tr>';
    } else {
        sepet.forEach((item, index) => {
            sepetHTML += `
            <tr>
                <td class="px-3 py-2">
                    <div class="font-medium">${item.ad}</div>
                    <div class="text-xs text-gray-500">${item.barkod || item.kod}</div>
                </td>
                <td class="px-3 py-2 text-center">
                    <div class="flex items-center justify-center">
                        <button class="btn-miktar-azalt bg-red-100 hover:bg-red-200 text-red-800 px-2 py-1 rounded" data-index="${index}">-</button>
                        <input type="text" class="urun-miktar-input w-16 mx-1 text-center border border-gray-300 rounded" value="${item.miktar}" data-index="${index}">
                        <button class="btn-miktar-artir bg-green-100 hover:bg-green-200 text-green-800 px-2 py-1 rounded" data-index="${index}">+</button>
                        <div class="ml-1">
                            <button class="btn-miktar-x10 bg-blue-100 hover:bg-blue-200 text-blue-800 px-2 py-1 rounded text-xs" data-index="${index}">+10</button>
                            <button class="btn-miktar-x100 bg-blue-100 hover:bg-blue-200 text-blue-800 px-2 py-1 rounded text-xs" data-index="${index}">+100</button>
                        </div>
                    </div>
                </td>
                <td class="px-3 py-2 text-right">${formatPrice(item.birim_fiyat)}</td>
                <td class="px-3 py-2 text-right">
                    ${item.indirim > 0 ? 
                        (item.indirim_turu === 'yuzde' ? 
                            `%${item.indirim.toFixed(2)}` : 
                            formatPrice(item.indirim)
                        ) : 
                        '-'
                    }
                </td>
                <td class="px-3 py-2 text-right">${formatPrice(item.toplam)}</td>
                <td class="px-3 py-2 text-center">
                    <button class="btn-urun-cikar text-red-500 hover:text-red-700" data-index="${index}">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </td>
            </tr>`;
        });
    }
    
    $('#sepetListesi').html(sepetHTML);
}

/**
 * Sepet toplamlarını güncelle
 */
function updateSepetTotals() {
    // Toplam ürün sayısı
    const urunSayisi = sepet.reduce((total, item) => total + item.miktar, 0);
    
    // Ara toplam (indirimler hariç)
    const araToplam = sepet.reduce((total, item) => total + (item.birim_fiyat * item.miktar), 0);
    
    // Toplam indirim
    const toplamIndirim = araToplam - sepet.reduce((total, item) => total + item.toplam, 0);
    
    // Genel toplam (indirimler dahil)
    let genelToplam = sepet.reduce((total, item) => total + item.toplam, 0);
    
    // Müşteri puanı kullanılıyorsa
    if (kullanilacakPuan > 0) {
        genelToplam = Math.max(0, genelToplam - kullanilacakPuan);
    }
    
    // Değerleri UI'a yaz
    $('#urunSayisi').text(urunSayisi);
    $('#araToplam').text(formatPrice(araToplam));
    $('#toplamIndirim').text(formatPrice(toplamIndirim));
    $('#genelToplam').text(formatPrice(genelToplam));
    
// Kazanılacak puanı hesapla (yüzde olarak ve küsüratlı)
if (seciliMusteri) {
    const puan_oran = (seciliMusteri.puan_oran || 1) / 100;
    // Math.floor kaldırıldı, küsüratlı hesaplama:
    const kazanilacakPuan = genelToplam * puan_oran;
    // Küsüratlı değeri 2 basamak olarak formatla
    $('#kazanilacakPuan').text(`${kazanilacakPuan.toFixed(2)} Puan`);
} else {
    $('#kazanilacakPuan').text('0.00 Puan');
}
}

/**
 * İndirim uygula
 * @param {number} deger - İndirim değeri
 */
function applyDiscount(deger) {
    if (indirimSekli === 'genel') {
        // Tüm sepete indirim uygula
        const araToplam = sepet.reduce((total, item) => total + (item.birim_fiyat * item.miktar), 0);
        
        sepet.forEach((item, index) => {
            if (indirimTuru === 'yuzde') {
                // Yüzde indirim
                item.indirim = deger;
                item.indirim_turu = 'yuzde';
            } else {
                // Tutar indirim (oransal dağıtım)
                const payOrani = (item.birim_fiyat * item.miktar) / araToplam;
                item.indirim = deger * payOrani;
                item.indirim_turu = 'tutar';
            }
            
            item.toplam = calculateItemTotal(item);
        });
        
        showToast(`Tüm sepete ${indirimTuru === 'yuzde' ? '%' + deger : formatPrice(deger)} indirim uygulandı`);
    } else {
        // Seçili ürüne indirim uygula
        const selectedUrunIndex = parseInt($('#indirimUrunSelect').val());
        
        if (isNaN(selectedUrunIndex) || selectedUrunIndex < 0 || selectedUrunIndex >= sepet.length) {
            showToast('Geçerli bir ürün seçiniz', 'error');
            return;
        }
        
        const item = sepet[selectedUrunIndex];
        
        if (indirimTuru === 'yuzde') {
            // Yüzde indirim
            item.indirim = deger;
            item.indirim_turu = 'yuzde';
        } else {
            // Tutar indirim
            if (deger > (item.birim_fiyat * item.miktar)) {
                showToast('İndirim tutarı ürün tutarından büyük olamaz', 'error');
                return;
            }
            
            item.indirim = deger;
            item.indirim_turu = 'tutar';
        }
        
        item.toplam = calculateItemTotal(item);
        
        showToast(`"${item.ad}" ürününe ${indirimTuru === 'yuzde' ? '%' + deger : formatPrice(deger)} indirim uygulandı`);
    }
    
    updateSepetUI();
    updateSepetTotals();
}

/**
 * İndirim modalındaki ürün seçim listesini güncelle
 */
function updateIndirimUrunSelect() {
    let options = '';
    
    sepet.forEach((item, index) => {
        options += `<option value="${index}">${item.ad} (${formatPrice(item.birim_fiyat)} x ${item.miktar})</option>`;
    });
    
    $('#indirimUrunSelect').html(options);
}

/**
 * Müşteri ara
 * @param {string} term - Arama terimi
 */
function searchCustomers(term) {
    $.ajax({
        url: 'admin/api/get_customers_with_debts.php',
        type: 'GET',
        data: { 
            search_term: term,
            limit: 50
        },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.customers) {
                renderCustomerList(response.customers);
            } else {
                $('#musteriListesi').html('<tr><td colspan="5" class="px-3 py-3 text-center text-gray-500">Müşteri bulunamadı</td></tr>');
            }
        },
        error: function() {
            showToast('Müşteri arama sırasında bir hata oluştu', 'error');
        }
    });
}

/**
 * Müşteri listesini render et
 * @param {Array} customers - Müşteriler listesi
 */
function renderCustomerList(customers) {
    let html = '';
    
    if (customers.length === 0) {
        html = '<tr><td colspan="5" class="px-3 py-3 text-center text-gray-500">Müşteri bulunamadı</td></tr>';
    } else {
        customers.forEach(customer => {
            html += `
            <tr>
                <td class="px-3 py-2">
                    <div class="font-medium">${customer.ad} ${customer.soyad}</div>
                </td>
                <td class="px-3 py-2">${customer.telefon}</td>
                <td class="px-3 py-2 text-right">${customer.puan_bakiye ? customer.puan_bakiye : '0'} Puan</td>
                <td class="px-3 py-2 text-right">
                    <span class="${parseFloat(customer.toplam_borc) > 0 ? 'text-red-600 font-medium' : 'text-green-600'}">${formatPrice(customer.toplam_borc)}</span>
                </td>
                <td class="px-3 py-2 text-center flex space-x-1 justify-center">
                    <button class="btn-musteri-sec bg-blue-500 hover:bg-blue-600 text-white py-1 px-2 rounded text-xs" data-id="${customer.id}">
                        Seç
                    </button>
                    <button class="btn-musteri-duzenle bg-yellow-500 hover:bg-yellow-600 text-white py-1 px-2 rounded text-xs" data-id="${customer.id}">
                        <i class="fas fa-edit"></i>
                    </button>
                </td>
            </tr>`;
        });
    }
    
    $('#musteriListesi').html(html);
}

/**
 * Müşteri seç
 * @param {number} musteriId - Müşteri ID
 */
function selectCustomer(musteriId) {
    $.ajax({
        url: 'admin/api/get_customer.php',
        type: 'GET',
        data: { id: musteriId },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.customer) {
                seciliMusteri = response.customer;
                
                // Müşteri bilgilerini göster
                $('#seciliMusteri').removeClass('hidden');
                $('#musteriAdSoyad').text(`${seciliMusteri.ad} ${seciliMusteri.soyad}`);
                $('#musteriTelefon').text(seciliMusteri.telefon);
                $('#musteriPuan').text(`${seciliMusteri.puan_bakiye || 0} Puan`);
                
                // Puan kullanım panelini göster
                if (seciliMusteri.puan_bakiye > 0) {
                    $('#puanKullanim').removeClass('hidden');
                    $('#mevcutPuan').text(`${seciliMusteri.puan_bakiye} Puan`);
                    $('#kullanilacakPuan').val('');
                } else {
                    $('#puanKullanim').addClass('hidden');
                }
                
                // Sepet toplamlarını güncelle (kazanılacak puan için)
                updateSepetTotals();
                
                // Müşteri borç bilgisini güncelle - BU SATIRI EKLEYİN
                updateMusteriBorcBilgisi(musteriId);
                
                // Modalı kapat
                closeAllModals();
                
                showToast(`${seciliMusteri.ad} ${seciliMusteri.soyad} seçildi`);
            } else {
                showToast('Müşteri bilgileri alınamadı', 'error');
            }
        },
        error: function() {
            showToast('Müşteri seçme sırasında bir hata oluştu', 'error');
        }
    });
}

/**
 * Yeni müşteri ekle
 * @param {FormData} formData - Form verileri
 */
function addNewCustomer(formData) {
    $.ajax({
        url: 'admin/api/add_customer.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Yeni eklenen müşteriyi seç
                selectCustomer(response.customer_id);
                
                // Modalı kapat
                closeAllModals();
                
                // Formu temizle
                $('#yeniMusteriForm')[0].reset();
                
                showToast('Müşteri başarıyla eklendi');
            } else {
                showToast(response.message || 'Müşteri eklenirken bir hata oluştu', 'error');
            }
        },
        error: function() {
            showToast('Müşteri eklenirken bir hata oluştu', 'error');
        }
    });
}

/**
 * Ürün ara
 * @param {string} term - Arama terimi
 */
function searchProducts(term) {
    $.ajax({
        url: 'admin/api/search_product.php',
        type: 'GET',
        data: { term: term },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.products) {
                renderProductList(response.products);
            } else {
                $('#stokListesi').html('<tr><td colspan="5" class="px-3 py-3 text-center text-gray-500">Ürün bulunamadı</td></tr>');
            }
        },
        error: function() {
            showToast('Ürün arama sırasında bir hata oluştu', 'error');
        }
    });
}

/**
 * Ürün listesini render et
 * @param {Array} products - Ürünler listesi
 */
function renderProductList(products) {
    let html = '';
    
    if (products.length === 0) {
        html = '<tr><td colspan="5" class="px-3 py-3 text-center text-gray-500">Ürün bulunamadı</td></tr>';
    } else {
        products.forEach(product => {
            html += `
            <tr class="urun-sec-row hover:bg-blue-50 cursor-pointer" data-id="${product.id}">
                <td class="px-3 py-2">${product.barkod || ''}</td>
                <td class="px-3 py-2">
                    <div class="font-medium">${product.ad}</div>
                    <div class="text-xs text-gray-500">${product.kod || ''}</div>
                </td>
                <td class="px-3 py-2 text-center">${product.stok_miktari || 0}</td>
                <td class="px-3 py-2 text-right">${formatPrice(product.satis_fiyati)}</td>
                <td class="px-3 py-2 text-center">
                    <button class="bg-blue-500 hover:bg-blue-600 text-white py-1 px-2 rounded text-xs" onclick="getUrunById(${product.id})">
                        Ekle
                    </button>
                </td>
            </tr>`;
        });
    }
    
    $('#stokListesi').html(html);
}

/**
 * Kısayol ürünleri yükle
 */
function loadKisayolUrunler() {
    // Yükleniyor göstergesini göster
    $('#urunKisayolContainer').html(`
        <div class="col-span-full text-center py-4">
            <i class="fas fa-sync fa-spin text-gray-400"></i>
            <div class="text-sm text-gray-500 mt-1">Kısayollar yükleniyor...</div>
        </div>
    `);

    // API'den kısayol ürünlerini getir
    $.ajax({
        url: 'admin/api/get_product_shortcuts.php',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            console.log("Kısayol yanıtı:", response); // Debug için log
            if (response.success) {
                renderProductShortcuts(response.shortcuts || []);
            } else {
                console.error('Kısayol ürünleri alınamadı:', response.message);
                // Hata durumunda boş bir grid göster
                renderProductShortcuts([]);
            }
        },
        error: function(xhr, status, error) {
            console.error('Kısayol ürünleri alınırken bir hata oluştu:', status, error);
            console.log('XHR yanıtı:', xhr.responseText);
            
            // Hata durumunda boş bir grid göster
            renderProductShortcuts([]);
            
            // Geçici çözüm olarak sabit kısayollar gösterilebilir
            /*
            const dummyShortcuts = [
                { position: 0, product_id: 1, product: { ad: "Örnek Ürün 1", satis_fiyati: 10.50 } },
                { position: 1, product_id: 2, product: { ad: "Örnek Ürün 2", satis_fiyati: 15.75 } }
            ];
            renderProductShortcuts(dummyShortcuts);
            */
        },
        timeout: 10000 // 10 saniye timeout ekle
    });
}

/**
 * Kısayol ürünlerini göster
 * @param {Array} shortcuts - Kısayol ürünleri listesi
 */
function renderProductShortcuts(shortcuts) {
    console.log("renderProductShortcuts çalıştı, ürün sayısı:", shortcuts.length); // Debug için log
    
    // Kısayol container'ını temizle
    const container = $('#urunKisayolContainer');
    container.empty();
    
    // Hiç kısayol yoksa bilgi mesajı göster
    if (shortcuts.length === 0) {
        container.html(`
            <div class="col-span-full text-center py-2">
                <div class="text-sm text-gray-500">Kısayol ürünü bulunmamaktadır.</div>
                <div class="text-xs text-gray-400 mt-1">Ayarlar → Kısayollar menüsünden ekleyebilirsiniz.</div>
            </div>
        `);
        return;
    }
    
    // Kısayol butonlarını oluştur
    for (let i = 0; i < Math.max(shortcuts.length, 12); i++) {
        // Bu pozisyonda bir kısayol var mı bul
        const shortcut = shortcuts.find(s => s.position === i);
        
        // HTML oluştur
        if (shortcut && shortcut.product) {
            container.append(`
                <button class="urun-kisayol bg-blue-100 hover:bg-blue-200 text-blue-800 py-2 px-3 rounded text-sm" data-id="${shortcut.product_id}">
                    <div class="font-medium truncate">${shortcut.product.ad}</div>
                    <div class="text-xs">${formatPrice(shortcut.product.satis_fiyati)}</div>
                </button>
            `);
        } else if (i < 12) { // Sadece ilk 12 pozisyon için boş buton göster
            // Boş buton
            container.append(`
                <button class="bg-gray-100 text-gray-400 py-2 px-3 rounded text-sm" disabled>
                    <i class="fas fa-plus-circle"></i>
                </button>
            `);
        }
    }
    
    // Kısayol butonlarına tıklama olayı ekle
    $('.urun-kisayol').on('click', function() {
        const urunId = $(this).data('id');
        if (urunId) {
            getUrunById(urunId);
        }
    });
}


/**
 * Fiş numarası oluştur
 * @returns {string} - Fiş numarası
 */
function generateFisNo() {
    const date = new Date();
    const year = date.getFullYear().toString().slice(-2);
    const month = (date.getMonth() + 1).toString().padStart(2, '0');
    const day = date.getDate().toString().padStart(2, '0');
    const random = Math.floor(Math.random() * 10000).toString().padStart(4, '0');
    
    return `${year}${month}${day}-${random}`;
}

/**
 * Fişi bekleyen olarak kaydet
 * @param {string} not - Bekletme notu
 */
function saveFisToBekleyen(not) {
    const bekleyenFis = {
        id: new Date().getTime(),
        fisNo: fisNo,
        tarih: new Date().toISOString(),
        musteri: seciliMusteri,
        sepet: sepet,
        islemTuru: islemTuru,
        kullanilacakPuan: kullanilacakPuan,
        not: not
    };
    
    // Bekleyen fişler listesine ekle
    bekleyenFisler.push(bekleyenFis);
    
    // Local storage'a kaydet
    localStorage.setItem('bekleyenFisler', JSON.stringify(bekleyenFisler));
    
    // Sepeti temizle ve yeni fiş oluştur
    resetSepet();
}

/**
 * Bekleyen fişleri yükle
 */
function loadBekleyenFisler() {
    const saved = localStorage.getItem('bekleyenFisler');
    if (saved) {
        bekleyenFisler = JSON.parse(saved);
    }
}

/**
 * Bekleyen fişi yükle
 * @param {number} id - Fiş ID
 */
function loadBekleyenFis(id) {
    const fis = bekleyenFisler.find(f => f.id === id);
    
    if (fis) {
        // Mevcut sepeti temizle
        resetSepet(false);
        
        // Fiş bilgilerini yükle
        fisNo = fis.fisNo;
        $('#fisNo').val(fisNo);
        
        islemTuru = fis.islemTuru;
        $('#islemTuru').val(islemTuru);
        
        sepet = JSON.parse(JSON.stringify(fis.sepet)); // Deep copy
        
        // Müşteri varsa seç
        if (fis.musteri) {
            seciliMusteri = fis.musteri;
            $('#seciliMusteri').removeClass('hidden');
            $('#musteriAdSoyad').text(`${seciliMusteri.ad} ${seciliMusteri.soyad}`);
            $('#musteriTelefon').text(seciliMusteri.telefon);
            $('#musteriPuan').text(`${seciliMusteri.puan_bakiye || 0} Puan`);
            
            // Puan kullanımı
            kullanilacakPuan = fis.kullanilacakPuan || 0;
            if (kullanilacakPuan > 0 && seciliMusteri.puan_bakiye >= kullanilacakPuan) {
                $('#puanKullanim').removeClass('hidden');
                $('#mevcutPuan').text(`${seciliMusteri.puan_bakiye} Puan`);
                $('#kullanilacakPuan').val(kullanilacakPuan);
            }
        }
        
        // UI güncelle
        updateSepetUI();
        updateSepetTotals();
        
        // Bekleyen fişler listesinden çıkar
        removeBekleyenFis(id);
        
        showToast('Bekleyen fiş yüklendi');
        
        return true;
    }
    
    return false;
}

/**
 * Bekleyen fişi sil
 * @param {number} id - Fiş ID
 */
function removeBekleyenFis(id) {
    bekleyenFisler = bekleyenFisler.filter(f => f.id !== id);
    localStorage.setItem('bekleyenFisler', JSON.stringify(bekleyenFisler));
}

/**
 * Sepeti sıfırla
 * @param {boolean} newFisNo - Yeni fiş numarası oluşturulsun mu
 */
function resetSepet(newFisNo = true) {
    // Sepeti temizle
    sepet = [];
    updateSepetUI();
    updateSepetTotals();
    
    // Müşteri bilgilerini temizle
    seciliMusteri = null;
    $('#seciliMusteri').addClass('hidden');
    $('#puanKullanim').addClass('hidden');
    kullanilacakPuan = 0;
	
	// Tarih ve saati güncelle
    updateDateTime();
    
    // İşlem türünü satışa çevir
    islemTuru = 'satis';
    $('#islemTuru').val('satis');
    $('body').removeClass('iade-mode');
    
    // Yeni fiş numarası
    if (newFisNo) {
        fisNo = generateFisNo();
        $('#fisNo').val(fisNo);
    }
    
    // Barkod input'una fokusla
    $('#barkodInput').focus();
}

/**
 * Satışı tamamla
 */
function completeSale() {
	    // Kasiyer ve mağaza kontrolü
    if (!$('#kasiyer').val() || !$('#magaza').val()) {
        showToast('Lütfen kasiyer ve mağaza seçimi yapın', 'error');
        return;
    }

    // Satış verileri hazırla
    const saleData = {
        fisNo: fisNo,
        islemTuru: islemTuru,
        magazaId: $('#magaza').val(),
        kasiyerId: $('#kasiyer').val(),
        musteriId: seciliMusteri ? seciliMusteri.id : null,
        odemeYontemi: seciliOdemeYontemi,
        kullanilacakPuan: kullanilacakPuan,
        sepet: sepet,
        genelToplam: getOdenecekTutar()
    };
    
    // Ödeme yöntemine göre ek bilgileri ekle
    if (seciliOdemeYontemi === 'nakit') {
        saleData.alinanTutar = parseFloat($('#alinanTutar').val()) || 0;
        saleData.paraUstu = saleData.alinanTutar - saleData.genelToplam;
    } else if (seciliOdemeYontemi === 'kredi_karti') {
        saleData.banka = $('#banka').val();
        saleData.taksit = $('#taksitSayisi').val();
    }
    
    // AJAX ile satışı kaydet - SADECE TEK BİR AJAX ÇAĞRISI OLMALI
    $.ajax({
        url: 'admin/api/create_sale.php',
        type: 'POST',
        data: JSON.stringify(saleData),
        contentType: 'application/json',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Fişi yazdır
                if (confirm('Satış başarıyla kaydedildi. Fiş yazdırılsın mı?')) {
                    printReceipt(response.invoiceId);
                }
                
                // Sepeti temizle ve yeni fiş oluştur
                resetSepet();
                
                // Modalı kapat
                closeAllModals();
                
                // Tarih ve saati güncelle
                updateDateTime();
                
                showToast('Satış başarıyla tamamlandı');
            } else {
                showToast(response.message || 'Satış kaydedilirken bir hata oluştu', 'error');
            }
        },
        error: function() {
            showToast('Satış kaydedilirken bir hata oluştu', 'error');
        }
    });
}

/**
 * Müşteri borç modalını aç
 * @param {number} musteriId - Müşteri ID
 */
function openMusteriBorcModal(musteriId) {
    // Müşteri borçlarını getir
    $.ajax({
        url: 'admin/api/get_customer_credits.php',
        type: 'GET',
        data: { id: musteriId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Doğru değerleri hesaplayalım
                const toplamBorc = parseFloat(response.summary.toplam_borc_net || response.summary.toplam_borc || 0);
                const toplamOdeme = parseFloat(response.summary.toplam_odeme || 0);
                
                // Kalan borç değerini doğru hesapla: toplam borç - toplam ödeme
                const kalanBorc = toplamBorc - toplamOdeme;
                
                // Borç özet bilgilerini doğru değerlerle göster
                $('#toplamBorc').text(formatPrice(toplamBorc));
                $('#toplamOdeme').text(formatPrice(toplamOdeme));
                $('#kalanBorc').text(formatPrice(kalanBorc));
                
                // Borç listesini temizle
                $('#musteriBorcListesi').empty();
                
                // Borç listesini oluştur
                if (response.credits && response.credits.length > 0) {
                    response.credits.forEach(credit => {
                        const odenen = parseFloat(credit.odenen_tutar || 0);
                        const toplam = parseFloat(credit.toplam_tutar || 0);
                        const indirim = parseFloat(credit.indirim_tutari || 0);
                        const netTutar = toplam - indirim; // Net tutarı hesapla
                        const kalan = netTutar - odenen;
                        
                        const tarih = new Date(credit.borc_tarihi).toLocaleDateString('tr-TR');
                        
                        // Burada netTutar değerini kullanarak borç bilgilerini göster
                        let row = `
                        <tr>
                            <td class="px-3 py-2">${tarih}</td>
                            <td class="px-3 py-2">${credit.fiş_no || '-'}</td>
                            <td class="px-3 py-2">${credit.magaza_adi || '-'}</td>
                            <td class="px-3 py-2 text-right">${formatPrice(netTutar)}</td> <!-- Burada netTutar kullanılacak -->
                            <td class="px-3 py-2 text-right">${formatPrice(odenen)}</td>
                            <td class="px-3 py-2 text-center">
                                ${credit.odendi_mi == 1 ? 
                                    '<span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs">Ödendi</span>' : 
                                    '<span class="px-2 py-1 bg-red-100 text-red-800 rounded-full text-xs">Ödenmedi</span>'
                                }
                            </td>
                            <td class="px-3 py-2 text-center">
                                ${credit.odendi_mi == 0 ? 
                                    `<button class="btn-borc-ode bg-blue-500 hover:bg-blue-600 text-white py-1 px-2 rounded text-xs" data-id="${credit.borc_id}" data-kalan="${kalan}">
                                        Ödeme Al
                                    </button>` : 
                                    '-'
                                }
                            </td>
                        </tr>`;
                        
                        $('#musteriBorcListesi').append(row);
                    });
                } else {
                    $('#musteriBorcListesi').html(`
                        <tr>
                            <td colspan="7" class="px-3 py-4 text-center text-gray-500">
                                Bu müşteriye ait borç kaydı bulunamadı
                            </td>
                        </tr>
                    `);
                }
                
                // Modalı aç
                $('#musteriBorcModal').removeClass('hidden');
            } else {
                showToast('Müşteri borç bilgileri alınamadı', 'error');
            }
        },
        error: function() {
            showToast('Müşteri borç bilgileri alınırken bir hata oluştu', 'error');
        }
    });
}

/**
 * Borç ödeme modalını aç
 * @param {number} borcId - Borç ID
 * @param {number} kalanTutar - Kalan borç tutarı
 */
function openBorcOdemeModal(borcId, kalanTutar) {
    // Borç detaylarını getir
    $.ajax({
        url: 'admin/api/get_credit_details.php',
        type: 'GET',
        data: { id: borcId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const credit = response.credit;
                const indirimTutari = parseFloat(credit.indirim_tutari || 0);
                const toplam = parseFloat(credit.toplam_tutar || 0);
                const netTutar = toplam - indirimTutari;
                
                // Borç bilgilerini doldur
                $('#borcOdemeBilgileri').html(`
                    <div class="font-medium">${credit.musteri_adi} ${credit.musteri_soyadi}</div>
                    <div class="text-sm">Fiş No: ${credit.fiş_no || '-'}</div>
                    <div class="text-sm">Tarih: ${new Date(credit.borc_tarihi).toLocaleDateString('tr-TR')}</div>
                    <div class="text-sm mt-2">
                        <span class="font-medium">Toplam Borç:</span> 
                        <span class="text-blue-600">${formatPrice(credit.toplam_tutar)}</span>
                    </div>
                    <div class="text-sm">
                        <span class="font-medium">İndirim Tutarı:</span>
                        <span class="text-green-600">${formatPrice(indirimTutari)}</span>
                    </div>
                    <div class="text-sm">
                        <span class="font-medium">Ödenen Tutar:</span>
                        <span class="text-green-600">${formatPrice(response.credit.odenen_tutar || 0)}</span>
                    </div>
                    <div class="text-sm">
                        <span class="font-medium">Kalan Borç:</span>
                        <span class="text-red-600">${formatPrice(kalanTutar)}</span>
                    </div>
                `);
                
                // Form değerlerini ayarla
                $('#borcOdeme_borcId').val(borcId);
                $('#borcOdeme_tutar').val(kalanTutar).attr('max', kalanTutar);
                $('#borcOdeme_tarih').val(new Date().toISOString().split('T')[0]); // Bugünün tarihi
                $('#borcOdeme_aciklama').val('');
                
                // Kalan tutar bilgisini butona ekle
                $('#btnBorcOdemeYap').data('kalan', kalanTutar);
                
                // Modalı aç
                $('#borcOdemeModal').removeClass('hidden');
            } else {
                showToast('Borç detayları alınamadı', 'error');
            }
        },
        error: function() {
            showToast('Borç detayları alınırken bir hata oluştu', 'error');
        }
    });
}

/**
 * Borç ödeme işlemini gönder
 * @param {Object} formData - Form verileri
 */
function submitBorcOdeme(formData) {
    $.ajax({
        url: 'admin/api/add_payment.php',
        type: 'POST',
        data: JSON.stringify(formData),
        contentType: 'application/json',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Modalı kapat
                $('#borcOdemeModal').addClass('hidden');
                
                // Borç modalını yenile - burada güncelleme yapılıyor
                if (seciliMusteri) {
                    openMusteriBorcModal(seciliMusteri.id);
                }
                
                showToast('Ödeme başarıyla kaydedildi', 'success');
                
                // Müşteri seçiliyse borç bilgisini güncelle
                if (seciliMusteri) {
                    updateMusteriBorcBilgisi(seciliMusteri.id);
                }
            } else {
                showToast(response.message || 'Ödeme kaydedilirken bir hata oluştu', 'error');
            }
        },
        error: function() {
            showToast('Ödeme kaydedilirken bir hata oluştu', 'error');
        }
    });
}

/**
 * Yeni borç toplam tutarını güncelleme fonksiyonu
 */
function updateYeniBorcToplam() {
    let toplamTutar = 0;
    
    // Tüm ürünlerin toplamını hesapla
    yeniBorc_urunler.forEach(urun => {
        toplamTutar += urun.toplam;
    });
    
    // İndirim tutarını çıkar
    const indirimTutari = parseFloat($('#yeniBorc_indirim').val()) || 0;
    if (indirimTutari > 0) {
        toplamTutar = Math.max(0, toplamTutar - indirimTutari);
    }
    
    // Toplam tutarı güncelle
    $('#yeniBorc_toplamTutar').text(formatPrice(toplamTutar));
}

/**
 * Yeni borç modali açma fonksiyonu
 */
function openYeniBorcModal() {
    // Tarihi bugün olarak ayarla
    const today = new Date().toISOString().split('T')[0];
    $('#yeniBorc_tarih').val(today);
    
    // Müşteri bilgisi
    $('#yeniBorc_musteriId').val(seciliMusteri.id);
    $('#yeniBorc_musteriAdi').text(`${seciliMusteri.ad} ${seciliMusteri.soyad}`);
    
    // Mağaza seçeneklerini yükle
    $('#yeniBorc_magaza').empty();
	
    
    // Mağaza verilerini API'den getir
    $.ajax({
        url: 'admin/api/get_magazalar.php',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            console.log("Mağaza verileri:", response); // Debug için
            if (response.success && response.magazalar) {
                // Tüm mağazaları dropdown'a ekle
                $.each(response.magazalar, function(i, magaza) {
                    $('#yeniBorc_magaza').append(`<option value="${magaza.id}">${magaza.ad}</option>`);
                });
                
                // Şu anki seçili mağazayı varsayılan olarak ayarla
                const currentStoreId = $('#magaza').val();
                if (currentStoreId) {
                    $('#yeniBorc_magaza').val(currentStoreId);
                }
            } else {
                // Eğer mağaza verisi gelmezse bir varsayılan seçenek ekle
                $('#yeniBorc_magaza').append('<option value="">Mağaza seçiniz</option>');
                console.error("Mağaza verileri alınamadı:", response);
            }
        },
        error: function(xhr, status, error) {
            console.error("Mağaza verileri getirilirken hata:", error);
            // Hata durumunda bir varsayılan seçenek ekle
            $('#yeniBorc_magaza').append('<option value="">Mağaza seçiniz</option>');
        }
    });
    
    // Ürün listesini temizle
    yeniBorc_urunler = [];
    $('#yeniBorc_urunListesi').empty();
    $('#yeniBorc_toplamTutar').text('0,00 ₺');
    
    // İndirim tutarını sıfırla
    $('#yeniBorc_indirim').val(0);
    
    // Modalı aç
    $('#yeniBorcModal').removeClass('hidden');
}

// Yeni borç ekleme formunda ürün ekle butonu
$(document).on('click', '#btnYeniBorc_urunEkle', function() {
    openUrunAramaModal();
});

/**
 * Ürün arama modalını aç
 */
function openUrunAramaModal() {
    // Inputu temizle ve modalı aç
    $('#urunAramaInput').val('');
    $('#urunAramaListesi').empty();
    $('#urunAramaModal').removeClass('hidden');
    
    // Input'a focus
    setTimeout(() => {
        $('#urunAramaInput').focus();
    }, 100);
}

// Ürün arama inputu
$(document).on('input', '#urunAramaInput', function() {
    const term = $(this).val().trim();
    if (term.length >= 2) {
        searchProductsForNewDebt(term);
    }
});

/**
 * Yeni borç için ürün arama
 */
function searchProductsForNewDebt(term) {
    $.ajax({
        url: 'admin/api/search_product.php',
        type: 'GET',
        data: { term: term },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.products && response.products.length > 0) {
                renderProductsForNewDebt(response.products);
            } else {
                $('#urunAramaListesi').html('<tr><td colspan="5" class="text-center py-4 text-gray-500">Ürün bulunamadı</td></tr>');
            }
        },
        error: function() {
            showToast('Ürün arama sırasında bir hata oluştu', 'error');
        }
    });
}

/**
 * Yeni borç için ürün listesini göster
 */
function renderProductsForNewDebt(products) {
    let html = '';
    
    products.forEach(product => {
        html += `
        <tr class="hover:bg-blue-50">
            <td class="px-3 py-2">${product.barkod || ''}</td>
            <td class="px-3 py-2">
                <div class="font-medium">${product.ad}</div>
                <div class="text-xs text-gray-500">${product.kod || ''}</div>
            </td>
            <td class="px-3 py-2 text-center">${product.stok_miktari || 0}</td>
            <td class="px-3 py-2 text-right">${formatPrice(product.satis_fiyati)}</td>
            <td class="px-3 py-2 text-center">
                <button class="btn-urun-sec-borc bg-blue-500 hover:bg-blue-600 text-white py-1 px-2 rounded text-xs" data-id="${product.id}">
                    Seç
                </button>
            </td>
        </tr>`;
    });
    
    $('#urunAramaListesi').html(html);
}

// Ürün seçme butonu
$(document).on('click', '.btn-urun-sec-borc', function() {
    const urunId = $(this).data('id');
    openUrunDetayModal(urunId);
});

/**
 * Ürün detay modalını aç
 */
function openUrunDetayModal(urunId) {
    // Ürün bilgilerini getir
    $.ajax({
        url: 'admin/api/get_product_details.php',
        type: 'GET',
        data: { id: urunId },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.product) {
                const urun = response.product;
                
                // Ürün bilgilerini forma doldur
                $('#urunDetay_id').val(urun.id);
                $('#urunDetay_ad').text(urun.ad);
                $('#urunDetay_miktar').val(1);
                $('#urunDetay_fiyat').val(urun.satis_fiyati);
                $('#urunDetay_toplam').text(formatPrice(urun.satis_fiyati));
                
                // Ürün arama modalını kapat ve detay modalını aç
                $('#urunAramaModal').addClass('hidden');
                $('#urunDetayModal').removeClass('hidden');
                
                // Miktar inputuna focus
                setTimeout(() => {
                    $('#urunDetay_miktar').focus().select();
                }, 100);
            } else {
                showToast('Ürün bilgileri alınamadı', 'error');
            }
        },
        error: function() {
            showToast('Ürün bilgileri alınırken bir hata oluştu', 'error');
        }
    });
}

// Miktar ve fiyat değiştiğinde toplam hesapla
$(document).on('input', '#urunDetay_miktar, #urunDetay_fiyat', function() {
    const miktar = parseInt($('#urunDetay_miktar').val()) || 0;
    const fiyat = parseFloat($('#urunDetay_fiyat').val()) || 0;
    const toplam = miktar * fiyat;
    
    $('#urunDetay_toplam').text(formatPrice(toplam));
});

// Ürün ekleme butonu
$(document).on('click', '#btnUrunDetayEkle', function() {
    const urunId = $('#urunDetay_id').val();
    const urunAd = $('#urunDetay_ad').text();
    const miktar = parseInt($('#urunDetay_miktar').val()) || 0;
    const fiyat = parseFloat($('#urunDetay_fiyat').val()) || 0;
    const toplam = miktar * fiyat;
    
    if (miktar <= 0 || fiyat <= 0) {
        showToast('Geçerli miktar ve fiyat giriniz', 'error');
        return;
    }
    
    // Ürünü borç listesine ekle
    addUrunToYeniBorc({
        id: urunId,
        ad: urunAd,
        miktar: miktar,
        birim_fiyat: fiyat,
        toplam: toplam
    });
    
    // Modalı kapat
    $('#urunDetayModal').addClass('hidden');
});

/**
 * Borç listesine ürün ekle
 */
function addUrunToYeniBorc(urun) {
    // Ürün listesine ekle
    yeniBorc_urunler.push(urun);
    
    // UI'ı güncelle
    updateYeniBorcUI();
}

/**
 * Borç listesinden ürün çıkar
 */
function removeUrunFromYeniBorc(index) {
    yeniBorc_urunler.splice(index, 1);
    updateYeniBorcUI();
}

/**
 * Borç listesi UI güncellemesi
 */
function updateYeniBorcUI() {
    let html = '';
    let toplamTutar = 0;
    
    if (yeniBorc_urunler.length === 0) {
        html = '<tr><td colspan="5" class="text-center py-4 text-gray-500">Ürün eklenmedi</td></tr>';
    } else {
        yeniBorc_urunler.forEach((urun, index) => {
            html += `
            <tr>
                <td class="px-3 py-2">${urun.ad}</td>
                <td class="px-3 py-2 text-center">${urun.miktar}</td>
                <td class="px-3 py-2 text-right">${formatPrice(urun.birim_fiyat)}</td>
                <td class="px-3 py-2 text-right">${formatPrice(urun.toplam)}</td>
                <td class="px-3 py-2 text-center">
                    <button class="btn-yeniborc-urun-cikar text-red-500 hover:text-red-700" data-index="${index}">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </td>
            </tr>`;
            
            toplamTutar += urun.toplam;
        });
    }
    
    $('#yeniBorc_urunListesi').html(html);
    
    // Toplam tutarı güncelleyen fonksiyonu çağır
    updateYeniBorcToplam();
}

// Ürün çıkarma butonu
$(document).on('click', '.btn-yeniborc-urun-cikar', function() {
    const index = $(this).data('index');
    removeUrunFromYeniBorc(index);
});

// Yeni borç ekleme butonu
$(document).on('click', '#btnYeniBorcEkle', function() {
    if (yeniBorc_urunler.length === 0) {
        showToast('Lütfen en az bir ürün ekleyin', 'error');
        return;
    }
    
    const musteriId = $('#yeniBorc_musteriId').val();
    const borc_tarihi = $('#yeniBorc_tarih').val();
    const magaza_id = $('#yeniBorc_magaza').val();
    const fis_no = $('#yeniBorc_fisNo').val();
    const indirim_tutari = parseFloat($('#yeniBorc_indirim').val()) || 0;
    
    // Toplam tutarı hesapla
    let toplam_tutar = 0;
    yeniBorc_urunler.forEach(urun => {
        toplam_tutar += urun.toplam;
    });
    
    // Borç verilerini hazırla
    const borcData = {
        musteri_id: musteriId,
        borc_tarihi: borc_tarihi,
        magaza_id: magaza_id,
        fis_no: fis_no,
        toplam_tutar: toplam_tutar,
        indirim_tutari: indirim_tutari,
        urunler: yeniBorc_urunler
    };
    
    // Borç kaydını gönder
$.ajax({
    url: 'admin/api/add_credit.php',
    type: 'POST',
    data: JSON.stringify(borcData),
    contentType: 'application/json',
    dataType: 'json',
    success: function(response) {
        console.log("API Yanıtı (success):", response); // Ayrıntılı debugging
        
        // Yanıt başarılı mı kontrol et
        if (response.success === true) { // Strict kontrolle true değerini karşılaştırın
            console.log("Başarılı yanıt alındı, modal kapatılıyor");
            
            // Modalı kapat
            $('#yeniBorcModal').addClass('hidden');
            
            // Borc listesini temizle
            yeniBorc_urunler = [];
            
            // Başarılı mesajı göster
            showToast('Borç başarıyla eklendi', 'success');
            
            // Müşteri borç bilgilerini yenile
            if (seciliMusteri && seciliMusteri.id) {
                updateMusteriBorcBilgisi(seciliMusteri.id);
                
                // Borç listesini güncelle
                openMusteriBorcModal(seciliMusteri.id);
            }
        } else {
            console.error("API success döndü ama response.success değeri true değil:", response);
            showToast(response.message || 'İşlem sırasında beklenmeyen bir yanıt alındı', 'error');
        }
    },
    error: function(xhr, status, error) {
        console.error("AJAX hatası:", status, error);
        console.log("XHR yanıtı:", xhr.responseText);
        try {
            var jsonResponse = JSON.parse(xhr.responseText);
            showToast(jsonResponse.message || 'Borç eklenirken bir hata oluştu', 'error');
        } catch(e) {
            showToast('Borç eklenirken bir hata oluştu: ' + error, 'error');
        }
    }
});
});

/**
 * Müşteri seçildiğinde borç bilgilerini güncelle
 * @param {number} musteriId - Müşteri ID 
 */
function updateMusteriBorcBilgisi(musteriId) {
    $.ajax({
        url: 'admin/api/get_customer_credits.php',
        type: 'GET',
        data: { id: musteriId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Doğru değerleri hesapla
                const toplamBorc = parseFloat(response.summary.toplam_borc_net || response.summary.toplam_borc || 0);
                const toplamOdeme = parseFloat(response.summary.toplam_odeme || 0);
                
                // Kalan borç = Toplam Borç - Toplam Ödeme
                const kalanBorc = toplamBorc - toplamOdeme;
                
                // Kalan borç bilgisini göster (toplam borç değil)
                $('#musteriBorcDurumu').text(formatPrice(kalanBorc));
                
                // Borç varsa kırmızı, yoksa yeşil göster
                if (kalanBorc > 0) {
                    $('#musteriBorcDurumu').removeClass('text-green-600').addClass('text-red-600');
                } else {
                    $('#musteriBorcDurumu').removeClass('text-red-600').addClass('text-green-600');
                }
            }
        }
    });
}

// Geçmiş siparişleri getir modal
function openGecmisSiparislerModal() {
    const musteriId = $('#yeniBorc_musteriId').val();
    
    if (!musteriId) {
        showToast('Önce müşteri seçmelisiniz', 'error');
        return;
    }
    
    // Müşterinin geçmiş siparişlerini getir
    $.ajax({
        url: 'admin/api/get_customer_history.php',
        type: 'GET',
        data: { id: musteriId },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.orders) {
                renderCustomerOrders(response.orders);
                $('#gecmisSiparislerModal').removeClass('hidden');
            } else {
                showToast('Müşteri sipariş geçmişi alınamadı', 'error');
            }
        },
        error: function() {
            showToast('Müşteri sipariş geçmişi alınırken bir hata oluştu', 'error');
        }
    });
}

// Müşteri siparişlerini listele
function renderCustomerOrders(orders) {
    let html = '';
    
    if (orders.length === 0) {
        html = '<tr><td colspan="6" class="px-3 py-4 text-center text-gray-500">Bu müşteriye ait sipariş bulunamadı</td></tr>';
    } else {
        orders.forEach(order => {
            // Borç olarak kaydedilmiş siparişleri filtrele
            if (order.islem_turu === 'satis') {
                const tarih = new Date(order.fatura_tarihi).toLocaleDateString('tr-TR');
                html += `
                <tr>
                    <td class="px-3 py-2 text-center">
                        <input type="checkbox" class="siparis-checkbox form-checkbox h-4 w-4 text-blue-600 rounded" 
                               data-id="${order.id}" 
                               data-tutar="${order.toplam_tutar}" 
                               data-fisno="${order.fatura_no}">
                    </td>
                    <td class="px-3 py-2">${tarih}</td>
                    <td class="px-3 py-2">${order.fatura_seri || ''} ${order.fatura_no || ''}</td>
                    <td class="px-3 py-2">${order.magaza_adi || '-'}</td>
                    <td class="px-3 py-2 text-right">${formatPrice(order.toplam_tutar)}</td>
                    <td class="px-3 py-2 text-center">
                        <button class="btn-siparis-detay bg-blue-500 hover:bg-blue-600 text-white py-1 px-2 rounded text-xs" data-id="${order.id}">
                            <i class="fas fa-eye mr-1"></i> Detay
                        </button>
                    </td>
                </tr>`;
            }
        });
    }
    
    $('#gecmisSiparisListesi').html(html);
}

/**
 * Sipariş detaylarını göster
 * @param {number} siparisId - Sipariş ID
 */
function openSiparisDetayModal(siparisId) {
    // Sipariş detaylarını getir
    $.ajax({
        url: 'admin/api/get_order_details.php',
        type: 'GET',
        data: { id: siparisId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const order = response.order || {};
                const items = response.items || [];
                
                // Sipariş başlık bilgilerini göster
                const faturaSeri = order.fatura_seri || '';
                const faturaNo = order.fatura_no || '';
                const faturaTarihi = order.fatura_tarihi ? new Date(order.fatura_tarihi).toLocaleDateString('tr-TR') : '-';
                const magazaAdi = order.magaza_adi || '-';
                const toplamTutar = parseFloat(order.toplam_tutar) || 0;
                const indirimTutari = parseFloat(order.indirim_tutari) || 0;
                const netTutar = toplamTutar - indirimTutari;
                
                // Sipariş üst bilgilerini göster
                $('#siparisDetayBilgileri').html(`
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <div class="text-sm"><span class="font-medium">Fiş No:</span> ${faturaSeri} ${faturaNo}</div>
                            <div class="text-sm"><span class="font-medium">Tarih:</span> ${faturaTarihi}</div>
                            <div class="text-sm"><span class="font-medium">Mağaza:</span> ${magazaAdi}</div>
                        </div>
                        <div>
                            <div class="text-sm"><span class="font-medium">Toplam Tutar:</span> ${formatPrice(toplamTutar)}</div>
                            <div class="text-sm"><span class="font-medium">İndirim:</span> ${formatPrice(indirimTutari)}</div>
                            <div class="text-sm"><span class="font-medium">Net Tutar:</span> ${formatPrice(netTutar)}</div>
                        </div>
                    </div>
                `);
                
                // Sipariş kalemlerini göster
                let itemsHtml = '';
                
                if (items.length === 0) {
                    itemsHtml = '<tr><td colspan="4" class="px-3 py-4 text-center text-gray-500">Bu siparişe ait ürün detayı bulunamadı</td></tr>';
                } else {
                    items.forEach(item => {
                        const urunAdi = item.urun_adi || '-';
                        const miktar = item.miktar || 0;
                        const birimFiyat = parseFloat(item.birim_fiyat) || 0;
                        const toplamTutar = parseFloat(item.toplam_tutar) || 0;
                        
                        itemsHtml += `
                        <tr>
                            <td class="px-3 py-2">${urunAdi}</td>
                            <td class="px-3 py-2 text-center">${miktar}</td>
                            <td class="px-3 py-2 text-right">${formatPrice(birimFiyat)}</td>
                            <td class="px-3 py-2 text-right">${formatPrice(toplamTutar)}</td>
                        </tr>`;
                    });
                }
                
                $('#siparisDetayListesi').html(itemsHtml);
                
                // Modalı aç
                $('#siparisDetayModal').removeClass('hidden');
            } else {
                showToast('Sipariş detayları alınamadı', 'error');
            }
        },
        error: function() {
            showToast('Sipariş detayları alınırken bir hata oluştu', 'error');
        }
    });
}

// Seçilen siparişleri borç listesine ekle
function addSelectedOrdersToDebt() {
    const selectedOrders = $('.siparis-checkbox:checked');
    
    if (selectedOrders.length === 0) {
        showToast('Lütfen en az bir sipariş seçin', 'warning');
        return;
    }

    // Seçilen siparişlerin toplam değerini hesapla
    let totalAmount = 0;
    const orderIds = [];
    const orderDetails = [];

    selectedOrders.each(function() {
        const siparisId = $(this).data('id');
        const tutar = parseFloat($(this).data('tutar'));
        const fisNo = $(this).data('fisno');
        
        totalAmount += tutar;
        orderIds.push(siparisId);
        
        // Detaylar için bilgileri topla
        orderDetails.push({
            id: siparisId,
            fisNo: fisNo,
            tutar: tutar
        });
    });

    // Seçilen siparişlerin borç kaydına dönüştürülmesini onayla
    if (confirm(`Seçilen ${selectedOrders.length} adet siparişi borç olarak güncellemek istediğinize emin misiniz?`)) {
        // 1. Borç kaydı oluştur
        createDebtFromOrders(orderDetails);
        
        // 2. Orijinal siparişleri borç olarak güncelle
        updateOrdersAsDebt(orderIds);
    }
	
	// Tüm modalları kapat
    closeAllModals();

}

// Siparişlerden borç kaydı oluştur
function createDebtFromOrders(orderDetails) {
    const musteriId = seciliMusteri.id;
    const today = new Date().toISOString().split('T')[0];
    const magazaId = $('#yeniBorc_magaza').val() || $('#magaza').val();
    
    // Her sipariş için yeni borç kaydı oluştur
    orderDetails.forEach(order => {
        // Borç verilerini hazırla
        const borcData = {
            musteri_id: musteriId,
            borc_tarihi: today,
            magaza_id: magazaId,
            fis_no: order.fisNo,
            toplam_tutar: order.tutar,
            indirim_tutari: 0,
            urunler: [{
                id: null, // Özel borç kalemi
                ad: `Sipariş #${order.fisNo} - Borç olarak aktarıldı`,
                miktar: 1,
                birim_fiyat: order.tutar,
                toplam: order.tutar
            }],
            siparis_id: order.id // İlişkilendirme için sipariş ID'si
        };
        
        // Borç kaydını oluştur
        $.ajax({
            url: 'admin/api/add_credit.php',
            type: 'POST',
            data: JSON.stringify(borcData),
            contentType: 'application/json',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    console.log(`Sipariş #${order.fisNo} için borç kaydı oluşturuldu`);
                } else {
                    showToast(`Sipariş #${order.fisNo} için borç kaydı oluşturulamadı: ${response.message}`, 'error');
                }
            },
            error: function() {
                showToast(`Sipariş #${order.fisNo} için borç kaydı oluşturulamadı`, 'error');
            }
        });
    });
    
    // İşlem başarılı mesajı
    showToast(`${orderDetails.length} adet sipariş borç kaydına dönüştürüldü`, 'success');
}

// Siparişleri borç olarak güncelle
function updateOrdersAsDebt(orderIds) {
    // API'yi çağır
    $.ajax({
        url: 'admin/api/update_orders_as_debt.php',
        type: 'POST',
        data: JSON.stringify({
            order_ids: orderIds,
            customer_id: seciliMusteri.id
        }),
        contentType: 'application/json',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showToast('Siparişler borç olarak güncellendi', 'success');
                
                // Modalı kapat
                $('#gecmisSiparislerModal').addClass('hidden');
                
                // Müşteri borç bilgilerini güncelle
                if (seciliMusteri && seciliMusteri.id) {
                    updateMusteriBorcBilgisi(seciliMusteri.id);
                    
                    // Borç listesini güncelle
                    openMusteriBorcModal(seciliMusteri.id);
                }
            } else {
                showToast(response.message || 'Siparişler güncellenirken bir hata oluştu', 'error');
            }
        },
        error: function() {
            showToast('Siparişler güncellenirken bir hata oluştu', 'error');
        }
    });
}

// Müşteri düzenleme modalını açma fonksiyonu
function openMusteriDuzenleModal(musteriId) {
    // Müşteri bilgilerini getir
    $.ajax({
        url: 'admin/api/get_customer.php',
        type: 'GET',
        data: { id: musteriId },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.customer) {
                const customer = response.customer;
                
                // Form alanlarını doldur
                $('#duzenle_musteri_id').val(customer.id);
                $('#duzenle_ad').val(customer.ad);
                $('#duzenle_soyad').val(customer.soyad);
                $('#duzenle_telefon').val(customer.telefon);
                $('#duzenle_email').val(customer.email || '');
                $('#duzenle_barkod').val(customer.barkod || '');
                
                // Puan oranını doldur
                if (customer.puan_oran) {
                    $('#duzenle_puan_oran').val(customer.puan_oran);
                } else {
                    $('#duzenle_puan_oran').val(1);
                }
                
                // SMS aktifliği
                $('#duzenle_sms_aktif').prop('checked', customer.sms_aktif == 1);
                
                // Modalı aç
                $('#musteriDuzenleModal').removeClass('hidden');
            } else {
                showToast('Müşteri bilgileri alınamadı', 'error');
            }
        },
        error: function() {
            showToast('Müşteri bilgileri alınırken bir hata oluştu', 'error');
        }
    });
}

// Müşteri düzenleme formu submit
$('#musteriDuzenleForm').on('submit', function(e) {
    e.preventDefault();
    updateCustomer();
});

// Müşteri bilgilerini güncelleme fonksiyonu
function updateCustomer() {
    const musteriId = $('#duzenle_musteri_id').val();
    
    // Form verilerini topla
    const formData = new FormData();
    formData.append('id', musteriId);
    formData.append('ad', $('#duzenle_ad').val());
    formData.append('soyad', $('#duzenle_soyad').val());
    formData.append('telefon', $('#duzenle_telefon').val());
    formData.append('email', $('#duzenle_email').val());
    formData.append('barkod', $('#duzenle_barkod').val());
    formData.append('sms_aktif', $('#duzenle_sms_aktif').is(':checked') ? 1 : 0);
    
    // Puan oranını da ekle, ama ayrı bir API çağrısı ile
    const puanOran = $('#duzenle_puan_oran').val();
    
    // AJAX isteği gönder
    $.ajax({
        url: 'admin/api/update_customer.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Puan oranını ayrı bir çağrı ile güncelleyelim
                updateCustomerPoints(musteriId, puanOran, function(pointsSuccess) {
                    // Modalı kapat
                    $('#musteriDuzenleModal').addClass('hidden');
                    
                    // Başarı mesajı göster
                    showToast('Müşteri bilgileri başarıyla güncellendi');
                    
                    // Müşteri Seç modalını tekrar aç ve müşteri listesini yenile
                    openMusteriSecModal();
                    
                    // Eğer şu anda seçili olan müşteri güncellendiyse, bilgileri yenile
                    if (seciliMusteri && seciliMusteri.id == musteriId) {
                        selectCustomer(musteriId);
                    }
                });
            } else {
                showToast(response.message || 'Müşteri güncellenirken bir hata oluştu', 'error');
            }
        },
        error: function() {
            showToast('Müşteri güncellenirken bir hata oluştu', 'error');
        }
    });
}

// Müşteri puan oranını güncelleyen ayrı bir fonksiyon
function updateCustomerPoints(customerId, puanOran, callback) {
    $.ajax({
        url: 'admin/api/update_customer_points.php',
        type: 'POST',
        data: JSON.stringify({
            customer_id: customerId,
            puan_oran: puanOran
        }),
        contentType: 'application/json',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                if (typeof callback === 'function') {
                    callback(true);
                }
            } else {
                showToast('Puan oranı güncellenirken bir hata oluştu: ' + (response.message || ''), 'warning');
                if (typeof callback === 'function') {
                    callback(false);
                }
            }
        },
        error: function() {
            showToast('Puan oranı güncellenirken bir hata oluştu', 'warning');
            if (typeof callback === 'function') {
                callback(false);
            }
        }
    });
}

// 2. Müşteri temizleme fonksiyonu
function clearCustomer() {
    // Müşteri seçimi temizle
    seciliMusteri = null;
    
    // Müşteri bilgi kutusunu gizle
    $('#seciliMusteri').addClass('hidden');
    
    // Puan kullanım panelini gizle
    $('#puanKullanim').addClass('hidden');
    kullanilacakPuan = 0;
    
    // Sepet toplamlarını güncelle (puansız)
    updateSepetTotals();
    
    // LocalStorage'dan müşteri bilgilerini temizle
    localStorage.removeItem('pos_seciliMusteri');
    localStorage.removeItem('pos_kullanilacakPuan');
    
    // Bilgi mesajı göster
    showToast('Müşteri seçimi iptal edildi');
    
    // Barkod giriş alanına odaklan
    $('#barkodInput').focus();
}

// Bekleyen fişleri göster fonksiyonu
function showBekleyenFisler() {
    // Bekleyen fişler listesini temizle
    $('#bekleyenFislerListesi').empty();
    
    // Bekleyen fiş yoksa bilgi mesajı göster
    if (bekleyenFisler.length === 0) {
        $('#bekleyenFislerListesi').html(`
            <tr>
                <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                    Bekleyen fiş bulunmamaktadır
                </td>
            </tr>
        `);
    } else {
        // Bekleyen fişleri listele
        bekleyenFisler.forEach(fis => {
            const tarih = new Date(fis.tarih).toLocaleString('tr-TR');
            const urunSayisi = fis.sepet.reduce((total, item) => total + item.miktar, 0);
            const toplamTutar = fis.sepet.reduce((total, item) => total + item.toplam, 0);
            
            $('#bekleyenFislerListesi').append(`
                <tr>
                    <td class="px-6 py-4">${fis.fisNo}</td>
                    <td class="px-6 py-4">${fis.musteri ? fis.musteri.ad + ' ' + fis.musteri.soyad : '-'}</td>
                    <td class="px-6 py-4 text-center">${urunSayisi}</td>
                    <td class="px-6 py-4 text-right">${formatPrice(toplamTutar)}</td>
                    <td class="px-6 py-4 text-center">${tarih}</td>
                    <td class="px-6 py-4 text-center">
                        <button class="btn-fis-yukle bg-blue-500 hover:bg-blue-600 text-white py-1 px-3 rounded text-sm" data-id="${fis.id}">
                            <i class="fas fa-redo-alt mr-1"></i> Yükle
                        </button>
                        <button class="btn-fis-sil bg-red-500 hover:bg-red-600 text-white py-1 px-3 rounded text-sm ml-2" data-id="${fis.id}">
                            <i class="fas fa-trash-alt mr-1"></i> Sil
                        </button>
                    </td>
                </tr>
            `);
        });
    }
    
    // Bekleyen fişler modalını aç
    $('#bekleyenFislerModal').removeClass('hidden');
}

// Rapor modalını açma fonksiyonu
function openRaporlarModal() {
    // Bugün ve son 30 gün için varsayılan tarih değerlerini ayarla
    const today = new Date().toISOString().split('T')[0];
    const thirtyDaysAgo = new Date();
    thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
    const thirtyDaysAgoStr = thirtyDaysAgo.toISOString().split('T')[0];
    
    $('#report_start_date').val(thirtyDaysAgoStr);
    $('#report_end_date').val(today);
    
    // Mağaza listesini doldur
    loadMagazalarForReport();
    
    // Rapor verilerini yükle
    loadReportData();
    
    // Modalı aç
    $('#raporlarModal').removeClass('hidden');
}

// Mağaza listesini yükleme
function loadMagazalarForReport() {
    $.ajax({
        url: 'admin/api/get_magazalar.php',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                let options = '<option value="">Tüm Mağazalar</option>';
                response.magazalar.forEach(function(magaza) {
                    options += `<option value="${magaza.id}">${magaza.ad}</option>`;
                });
                $('#report_magaza').html(options);
                
                // Şu anki seçili mağazayı varsayılan olarak ayarla
                const currentMagazaId = $('#magaza').val();
                if (currentMagazaId) {
                    $('#report_magaza').val(currentMagazaId);
                }
            }
        }
    });
}

// Rapor verilerini yükleme
function loadReportData() {
    const startDate = $('#report_start_date').val();
    const endDate = $('#report_end_date').val();
    const magazaId = $('#report_magaza').val();
    const page = reportCurrentPage;
    
    // Yükleniyor göstergesi
    $('#satislarTable tbody').html('<tr><td colspan="7" class="text-center py-4"><i class="fas fa-spinner fa-spin mr-2"></i> Yükleniyor...</td></tr>');
    
    $.ajax({
        url: 'admin/api/get_sales_report.php',
        type: 'GET',
        data: {
            start_date: startDate,
            end_date: endDate,
            magaza: magazaId,
            page: page
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Özet bilgileri doldur
                updateReportSummary(response.summary);
                
                // Satış listesini doldur
                reportSalesData = response.sales;
                renderSalesTable(reportSalesData);
                
                // Sayfalama bilgilerini güncelle
                updatePagination(page, response.total_pages, response.total_records);
                
                reportTotalPages = response.total_pages;
            } else {
                showToast(response.message || 'Rapor verileri alınamadı', 'error');
                $('#satislarTable tbody').html('<tr><td colspan="7" class="text-center py-4 text-red-500">Veri alınamadı</td></tr>');
            }
        },
        error: function() {
            showToast('Rapor verileri alınırken bir hata oluştu', 'error');
            $('#satislarTable tbody').html('<tr><td colspan="7" class="text-center py-4 text-red-500">Bağlantı hatası</td></tr>');
        }
    });
}

// Özet bilgilerini güncelleme
function updateReportSummary(summary) {
    $('#report_toplam_satis').text(summary.toplam_satis || 0);
    $('#report_toplam_ciro').text(formatPrice(summary.toplam_ciro || 0));
    $('#report_toplam_iade').text(formatPrice(summary.toplam_iade || 0));
    
    // Net kazanç hesapla (Ciro - İade)
    const netKazanc = (parseFloat(summary.toplam_ciro) || 0) - (parseFloat(summary.toplam_iade) || 0);
    $('#report_net_kazanc').text(formatPrice(netKazanc));
    
    // Ödeme türleri
    $('#report_nakit').text(formatPrice(summary.nakit_toplam || 0));
    $('#report_kredi_karti').text(formatPrice(summary.kart_toplam || 0));
    $('#report_borc').text(formatPrice(summary.borc_toplam || 0));
}

// Satış tablosunu oluşturma
function renderSalesTable(sales) {
    if (!sales || sales.length === 0) {
        $('#satislarTable tbody').html('<tr><td colspan="7" class="text-center py-4 text-gray-500">Bu tarih aralığında satış kaydı bulunamadı</td></tr>');
        return;
    }
    
    let html = '';
    
    sales.forEach(function(sale) {
        const tarih = new Date(sale.fatura_tarihi).toLocaleString('tr-TR');
        const islemTuru = sale.islem_turu === 'satis' ? 
            '<span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs">Satış</span>' : 
            '<span class="px-2 py-1 bg-red-100 text-red-800 rounded-full text-xs">İade</span>';
        
        html += `
        <tr>
            <td class="px-3 py-2">${tarih}</td>
            <td class="px-3 py-2">${sale.fatura_seri || ''} ${sale.fatura_no || ''}</td>
            <td class="px-3 py-2">${sale.musteri_adi || '-'}</td>
            <td class="px-3 py-2">${sale.magaza_adi || '-'}</td>
            <td class="px-3 py-2">${islemTuru}</td>
            <td class="px-3 py-2 text-right">${formatPrice(sale.toplam_tutar)}</td>
            <td class="px-3 py-2 text-center">
                <button class="bg-blue-500 hover:bg-blue-600 text-white py-1 px-2 rounded text-xs mr-1" 
                        onclick="viewSaleDetails(${sale.id})">
                    <i class="fas fa-eye"></i> Detay
                </button>
                <button class="bg-green-500 hover:bg-green-600 text-white py-1 px-2 rounded text-xs"
                        onclick="printReceipt(${sale.id})">
                    <i class="fas fa-print"></i> Yazdır
                </button>
            </td>
        </tr>`;
    });
    
    $('#satislarTable tbody').html(html);
}

// Sayfalama bilgilerini güncelleme
function updatePagination(currentPage, totalPages, totalRecords) {
    $('#currentPage').text(currentPage);
    $('#resultInfo').text(`Toplam ${totalRecords} kayıt arasından ${(currentPage - 1) * 20 + 1} - ${Math.min(currentPage * 20, totalRecords)} arası gösteriliyor`);
    
    // Sayfa düğmelerini aktifleştir/devre dışı bırak
    $('#prevPageBtn').prop('disabled', currentPage <= 1);
    $('#nextPageBtn').prop('disabled', currentPage >= totalPages);
}

// Önceki sayfaya gitme
function prevReportPage() {
    if (reportCurrentPage > 1) {
        reportCurrentPage--;
        loadReportData();
    }
}

// Sonraki sayfaya gitme
function nextReportPage() {
    if (reportCurrentPage < reportTotalPages) {
        reportCurrentPage++;
        loadReportData();
    }
}

/**
 * Satış detaylarını görüntüle
 * @param {number} saleId - Satış ID
 */
function viewSaleDetails(saleId) {
    // Reports modalını hidden class ile gizle (display:none yerine)
    $('#raporlarModal').addClass('hidden');
    
    // Sipariş detaylarını getiren AJAX çağrısını yap
    $.ajax({
        url: 'admin/api/get_order_details.php',
        type: 'GET',
        data: { id: saleId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const order = response.order || {};
                const items = response.items || [];
                
                // Sipariş başlık bilgilerini göster
                const faturaSeri = order.fatura_seri || '';
                const faturaNo = order.fatura_no || '';
                const faturaTarihi = order.fatura_tarihi ? new Date(order.fatura_tarihi).toLocaleString('tr-TR') : '-';
                const magazaAdi = order.magaza_adi || '-';
                const toplamTutar = parseFloat(order.toplam_tutar) || 0;
                const indirimTutari = parseFloat(order.indirim_tutari) || 0;
                const netTutar = toplamTutar - indirimTutari;
                
                // Puan kullanım bilgilerini getir
                let puanBilgisi = '';
                if (response.puan_bilgileri) {
                    const kullanilanPuan = parseFloat(response.puan_bilgileri.harcanan_puan) || 0;
                    const kazanilanPuan = parseFloat(response.puan_bilgileri.kazanilan_puan) || 0;
                    
                    if (kullanilanPuan > 0) {
                        puanBilgisi = `
                        <div class="text-sm"><span class="font-medium">Kullanılan Puan:</span> <span class="text-red-600">${kullanilanPuan.toFixed(2)} Puan</span></div>
                        `;
                    }
                    
                    if (kazanilanPuan > 0) {
                        puanBilgisi += `
                        <div class="text-sm"><span class="font-medium">Kazanılan Puan:</span> <span class="text-green-600">${kazanilanPuan.toFixed(2)} Puan</span></div>
                        `;
                    }
                }
                
                // Sipariş üst bilgilerini göster
                $('#siparisDetayBilgileri').html(`
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <div class="text-sm"><span class="font-medium">Fiş No:</span> ${faturaSeri} ${faturaNo}</div>
                            <div class="text-sm"><span class="font-medium">Tarih:</span> ${faturaTarihi}</div>
                            <div class="text-sm"><span class="font-medium">Mağaza:</span> ${magazaAdi}</div>
                            ${order.musteri_id ? `<div class="text-sm"><span class="font-medium">Müşteri:</span> ${order.musteri_adi || '-'}</div>` : ''}
                        </div>
                        <div>
                            <div class="text-sm"><span class="font-medium">Toplam Tutar:</span> ${formatPrice(toplamTutar)}</div>
                            <div class="text-sm"><span class="font-medium">İndirim:</span> ${formatPrice(indirimTutari)}</div>
                            ${puanBilgisi}
                            <div class="text-sm"><span class="font-medium">Net Tutar:</span> ${formatPrice(netTutar)}</div>
                        </div>
                    </div>
                `);
                
                // Sipariş kalemlerini göster
                let itemsHtml = '';
                
                if (items.length === 0) {
                    itemsHtml = '<tr><td colspan="4" class="px-3 py-4 text-center text-gray-500">Bu siparişe ait ürün detayı bulunamadı</td></tr>';
                } else {
                    items.forEach(item => {
                        const urunAdi = item.urun_adi || '-';
                        const miktar = item.miktar || 0;
                        const birimFiyat = parseFloat(item.birim_fiyat) || 0;
                        const toplamTutar = parseFloat(item.toplam_tutar) || 0;
                        
                        itemsHtml += `
                        <tr>
                            <td class="px-3 py-2">${urunAdi}</td>
                            <td class="px-3 py-2 text-center">${miktar}</td>
                            <td class="px-3 py-2 text-right">${formatPrice(birimFiyat)}</td>
                            <td class="px-3 py-2 text-right">${formatPrice(toplamTutar)}</td>
                        </tr>`;
                    });
                }
                
                $('#siparisDetayListesi').html(itemsHtml);
                
                // Detay modalını göster
                $('#siparisDetayModal').removeClass('hidden');
                
                // Detay modal kapatma işlemini düzenle
                $('.kapat, .modal-close', '#siparisDetayModal').off('click').on('click', function() {
                    $('#siparisDetayModal').addClass('hidden');
                    // Raporlar modalını tekrar göster (aynı class yöntemiyle)
                    $('#raporlarModal').removeClass('hidden');
                });
            } else {
                showToast('Sipariş detayları alınamadı', 'error');
                // Hata durumunda raporlar modalını geri göster
                $('#raporlarModal').removeClass('hidden');
            }
        },
        error: function() {
            showToast('Sipariş detayları alınırken bir hata oluştu', 'error');
            // Hata durumunda raporlar modalını geri göster
            $('#raporlarModal').removeClass('hidden');
        }
    });
}

/**
 * Fiş yazdır
 * @param {number} invoiceId - Fatura ID
 */
function printReceipt(invoiceId) {
    // Fiş yazdırma işlemi (opsiyonel)
    // Basit bir çözüm: Yazdırma sayfası aç
    const printWindow = window.open(`admin/api/print_receipt.php?id=${invoiceId}`, '_blank');
    
    if (printWindow) {
        printWindow.addEventListener('load', function() {
            printWindow.print();
            // Yazdırma işlemi tamamlandığında sayfayı kapatma
            // Not: Bazı browserlar buna izin vermeyebilir
            // printWindow.close();
        });
    } else {
        showToast('Yazdırma penceresi açılamadı. Popup engelleyici olabilir.', 'warning');
    }
}

/**
 * Ödenecek tutarı hesapla
 * @returns {number} - Ödenecek tutar
 */
function getOdenecekTutar() {
    // Genel toplam (indirimler dahil)
    let genelToplam = sepet.reduce((total, item) => total + item.toplam, 0);
    
    // Müşteri puanı kullanılıyorsa
    if (kullanilacakPuan > 0) {
        genelToplam = Math.max(0, genelToplam - kullanilacakPuan);
    }
    
    return genelToplam;
}

/**
 * Fiyat formatla
 * @param {number} price - Fiyat
 * @returns {string} - Formatlanmış fiyat
 */
function formatPrice(price) {
    return parseFloat(price).toLocaleString('tr-TR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }) + ' ₺';
}

/**
 * Toast bildirimi göster
 * @param {string} message - Mesaj
 * @param {string} type - Bildirim tipi: 'success', 'error', 'warning', 'info'
 */
function showToast(message, type = 'success') {
    // Toast rengini belirle
    let bgColor;
    let textColor = 'text-white';
    
    switch (type) {
        case 'error':
            bgColor = 'bg-red-500';
            break;
        case 'warning':
            bgColor = 'bg-yellow-500';
            break;
        case 'info':
            bgColor = 'bg-blue-500';
            break;
        default:
            bgColor = 'bg-green-500';
    }
    
    // Toast elementini güncelle
    $('#toastBildirim').removeClass('bg-green-500 bg-red-500 bg-yellow-500 bg-blue-500').addClass(bgColor);
    $('#toastMesaj').text(message);
    
    // Toast'ı göster
    $('#toastBildirim').removeClass('translate-y-20 opacity-0').addClass('translate-y-0 opacity-100');
    
    // 3 saniye sonra gizle
    setTimeout(function() {
        $('#toastBildirim').removeClass('translate-y-0 opacity-100').addClass('translate-y-20 opacity-0');
    }, 3000);
}

/**
 * Müşteri seçim modalını aç
 */
function openMusteriSecModal() {
    // Modalı aç
    $('#musteriSecModal').removeClass('hidden');
    
    // Input'a fokusla
    $('#musteriAraInput').val('').focus();
}

/**
 * Stok modalını aç
 */
function openStokModal() {
    // Modalı aç
    $('#stokModal').removeClass('hidden');
    
    // Input'a fokusla
    $('#stokAraInput').val('').focus();
}

/**
 * Yeni müşteri modalını aç
 */
function openYeniMusteriModal() {
    // Formu temizle
    $('#yeniMusteriForm')[0].reset();
    
    // Modalı aç
    $('#yeniMusteriModal').removeClass('hidden');
}

/**
 * İndirim modalını aç
 */
function openIndirimModal() {
    // İndirim değerini temizle
    $('#indirimDegeri').val('');
    
    // İndirim ürün listesini güncelle
    if (indirimSekli === 'urun') {
        updateIndirimUrunSelect();
    }
    
    // Modalı aç
    $('#indirimModal').removeClass('hidden');
}

/**
 * Bekletme modalını aç
 */
function openBekletModal() {
    // Notu temizle
    $('#bekletmeNotu').val('');
    
    // Modalı aç
    $('#bekletModal').removeClass('hidden');
}

/**
 * Ayarlar modalını aç
 */
function openAyarlarModal() {
    // Kısayol ürünlerini getir
    loadProductShortcuts();
    
    // Modalı aç
    $('#ayarlarModal').removeClass('hidden');
}

// Kısayol ürünlerini yükleme
function loadProductShortcuts() {
    // Mevcut kısayolları getir
    $.ajax({
        url: 'admin/api/get_product_shortcuts.php',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                renderShortcutSettings(response.shortcuts || []);
            } else {
                showToast(response.message || 'Kısayol ürünleri alınamadı', 'error');
            }
        },
        error: function() {
            showToast('Kısayol ürünleri alınırken bir hata oluştu', 'error');
        }
    });
}

// Kısayol ayarları UI'ını oluştur
function renderShortcutSettings(shortcuts) {
    console.log("Kısayollar render ediliyor:", shortcuts);
    // Kısayol editörünü temizle
    $('#urunKisayollari').empty();
    
    // 24 adet kısayol butonu oluştur (6x4 grid)
    for (let i = 0; i < 24; i++) {
        // Bu pozisyonda bir kısayol var mı bul
        const shortcut = shortcuts.find(s => s.position === i);
        
        // Butonun HTML'ini oluştur
        let buttonHtml = `
            <div class="shortcut-item p-2 border rounded bg-gray-50 relative" data-position="${i}">
                <div class="shortcut-content ${shortcut ? 'has-product' : ''}">
                    ${shortcut ? 
                        `<div class="text-sm font-medium">${shortcut.product.ad}</div>
                         <div class="text-xs text-gray-500">${formatPrice(shortcut.product.satis_fiyati)}</div>` 
                        : 
                        `<div class="text-center text-gray-400">
                            <i class="fas fa-plus-circle"></i>
                            <div class="text-xs">Boş</div>
                         </div>`
                    }
                </div>
                <div class="absolute top-1 right-1">
                    ${shortcut ? 
                        `<button type="button" class="btn-remove-shortcut text-red-500 hover:text-red-700 text-xs" data-position="${i}">
                            <i class="fas fa-times-circle"></i>
                         </button>` : ''
                    }
                </div>
            </div>
        `;
        
        $('#urunKisayollari').append(buttonHtml);
    }
    
    // Tüm event listener'ları temizle ve yeniden ekle
    $(document).off('click', '.shortcut-item');
    $(document).off('click', '.btn-remove-shortcut');
    
    // Kısayol öğeleri için tıklama olayı
    $(document).on('click', '.shortcut-item', function(e) {
        // Silme butonuna tıklandıysa, event bubbling'i engelle
        if ($(e.target).closest('.btn-remove-shortcut').length) {
            return;
        }
        
        // Pozisyonu al
        const position = $(this).data('position');
        console.log("Kısayol öğesine tıklandı, Pozisyon:", position);
        
        // Ürün seçme modalını aç
        openProductSelectionModal(position);
    });
    
    // Silme butonları için tıklama olayı
    $(document).on('click', '.btn-remove-shortcut', function(e) {
        e.stopPropagation(); // Event bubbling'i durdur
        const position = $(this).data('position');
        console.log("Silme butonuna tıklandı, Pozisyon:", position);
        removeShortcut(position);
    });
    
    // Kaydet butonu ekle
    if ($('#btnSaveShortcuts').length === 0) {
        $('#urunKisayollari').after(`
            <div class="mt-4 flex justify-center">
                <button id="btnSaveShortcuts" class="bg-green-500 hover:bg-green-600 text-white py-2 px-4 rounded">
                    <i class="fas fa-save mr-2"></i> Kısayolları Kaydet
                </button>
            </div>
        `);
        
        // Kaydet butonuna tıklama olayı ekle
        $('#btnSaveShortcuts').off('click').on('click', saveShortcuts);
    }
}

// Kısayol ürünü seçme modalı
function openProductSelectionModal(position) {
	
	    console.log("Ürün seçim modalı açılıyor, pozisyon:", position); // Debug log
    
    // Seçilen pozisyonu data-position attribute olarak sakla
    $('#productSelectionModal').data('position', position);
    
    // Ayrıca bir gizli input'a da kaydedelim (alternatif yöntem)
    if ($('#hidden-position-input').length === 0) {
        $('body').append('<input type="hidden" id="hidden-position-input" value="' + position + '">');
    } else {
        $('#hidden-position-input').val(position);
    }
    // Modal HTML'i oluştur (eğer yoksa)
    if ($('#productSelectionModal').length === 0) {
        $('body').append(`
            <div id="productSelectionModal" class="fixed inset-0 bg-gray-600 bg-opacity-75 hidden flex items-center justify-center z-50">
                <div class="bg-white rounded-lg shadow-xl w-11/12 md:w-3/4 lg:w-1/2 max-h-screen overflow-y-auto">
                    <div class="flex justify-between items-center p-4 border-b">
                        <h3 class="text-lg font-bold">Kısayol Ürünü Seç</h3>
                        <button class="modal-close text-gray-500 hover:text-gray-700">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="p-4">
                        <div class="mb-4">
                            <input type="text" id="shortcutProductSearch" placeholder="Ürün adı, barkod veya kodu ile ara..." class="w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Barkod</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Ürün Adı</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Stok</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Fiyat</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">İşlem</th>
                                    </tr>
                                </thead>
                                <tbody id="shortcutProductList">
                                    <!-- JavaScript ile doldurulacak -->
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4 flex justify-end">
                            <button class="modal-close bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded">
                                Kapat
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `);
        
        // Ürün arama alanına olay ekle
        $('#shortcutProductSearch').on('input', function() {
            const term = $(this).val().trim();
            if (term.length >= 2) {
                searchProductsForShortcut(term);
            }
        });
        
        // Modal kapatma butonuna olay ekle
        $('#productSelectionModal .modal-close').on('click', function() {
            $('#productSelectionModal').addClass('hidden');
        });
    }
    
    // Seçilen pozisyonu sakla
    $('#productSelectionModal').data('position', position);
    
    // Arama alanını temizle ve odakla
    $('#shortcutProductSearch').val('').focus();
    $('#shortcutProductList').empty();
    
    // Modalı göster
    $('#productSelectionModal').removeClass('hidden');
}

// Kısayol için ürün arama
function searchProductsForShortcut(term) {
    // Arama yapılırken yükleniyor göstergesi
    $('#shortcutProductList, .urun-listesi').html(`
        <tr>
            <td colspan="5" class="text-center py-4">
                <i class="fas fa-spinner fa-spin text-blue-500"></i>
                <div class="mt-2 text-gray-500">Ürünler aranıyor...</div>
            </td>
        </tr>
    `);
    
    // API'ye istek gönder
    $.ajax({
        url: 'admin/api/search_product.php',
        type: 'GET',
        data: { term: term },
        dataType: 'json',
        success: function(response) {
            console.log("Ürün arama sonuçları:", response); // Debug için log
            
            if (response.success && response.products && response.products.length > 0) {
                renderProductsForShortcut(response.products);
            } else {
                $('#shortcutProductList, .urun-listesi').html(`
                    <tr>
                        <td colspan="5" class="text-center py-4 text-gray-500">
                            Ürün bulunamadı
                        </td>
                    </tr>
                `);
            }
        },
        error: function(xhr, status, error) {
            console.error('Ürün arama hatası:', status, error);
            console.log('XHR yanıtı:', xhr.responseText);
            
            $('#shortcutProductList, .urun-listesi').html(`
                <tr>
                    <td colspan="5" class="text-center py-4 text-red-500">
                        Arama sırasında bir hata oluştu
                    </td>
                </tr>
            `);
        }
    });
}

/**
 * Kısayol için ürün listesini göster
 * @param {Array} products - Ürün listesi
 */
function renderProductsForShortcut(products) {
    let html = '';
    
    products.forEach(product => {
        html += `
        <tr class="hover:bg-blue-50">
            <td class="px-3 py-2">${product.barkod || '-'}</td>
            <td class="px-3 py-2">
                <div class="font-medium">${product.ad}</div>
                <div class="text-xs text-gray-500">${product.kod || '-'}</div>
            </td>
            <td class="px-3 py-2 text-center">${product.stok_miktari || 0}</td>
            <td class="px-3 py-2 text-right">${formatPrice(product.satis_fiyati)}</td>
            <td class="px-3 py-2 text-center">
                <button class="btn-select-shortcut-product bg-blue-500 hover:bg-blue-600 text-white py-1 px-2 rounded text-xs" data-id="${product.id}" data-position="${$('#kisayolPosition').val() || 0}">
                    Seç
                </button>
            </td>
        </tr>`;
    });
    
    // Sonuçları tabloya ekle
    $('#shortcutProductList, .urun-listesi').html(html);
    
    // Ürün seçme butonlarına tıklama olayı ekle
    $('.btn-select-shortcut-product').off('click').on('click', function() {
        const productId = $(this).data('id');
        const position = $(this).data('position');
        
        // Ürün seçme işlemini çağır
        selectProductForShortcut(productId, position);
        
        // Modalı kapat (HTML yapınıza göre değişebilir)
        $('#kisayolUrunuSecModal').addClass('hidden');
    });
}

/**
 * Kısayol için ürün seç
 * @param {number} productId - Ürün ID
 * @param {number} position - Kısayol pozisyonu
 */
function selectProductForShortcut(productId, position) {
	    console.log("Ürün kısayol için seçiliyor - Ürün ID:", productId, "Pozisyon:", position);
    
    // Pozisyon değeri kontrolü
    if (position === undefined || position === null) {
        showToast('Kısayol pozisyonu belirlenemedi', 'error');
        return;
    }
    
    // Ürün bilgilerini getir
    $.ajax({
        url: 'admin/api/get_product_details.php',
        type: 'GET',
        data: { id: productId },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.product) {
                // Geçici olarak kısayolları saklayacağımız değişken
                let shortcuts = window.productShortcuts || [];
                
                // Bu ürün herhangi bir kısayolda var mı kontrol et
                const existingShortcut = shortcuts.find(s => Number(s.product_id) === Number(productId));
                
                if (existingShortcut) {
                    // Ürün zaten bir kısayolda var
                    const existingPosition = existingShortcut.position;
                    
                    // Kullanıcıya bilgi ver ve işlemi iptal et
                    showToast(`Bu ürün zaten ${existingPosition + 1}. pozisyonda bulunuyor`, 'warning');
                    highlightShortcut(existingPosition);
                    return;
                }
                
                // Seçilen pozisyonda zaten bir ürün var mı?
                const existingAtPosition = shortcuts.find(s => s.position === position);
                
                if (existingAtPosition) {
                    // Pozisyonda zaten bir ürün var, kullanıcıya sor
                    if (!confirm(`${position + 1}. kısayolda zaten bir ürün var. Değiştirmek istediğinize emin misiniz?`)) {
                        return; // Kullanıcı iptal etti
                    }
                }
                
                // Varolan kısayolu bul ve kaldır (bu pozisyondaki mevcut kısayolu değiştirmek için)
                shortcuts = shortcuts.filter(s => s.position !== position);
                
                // Yeni kısayolu ekle
                shortcuts.push({
                    position: position,
                    product_id: productId,
                    product: {
                        id: productId,
                        ad: response.product.ad,
                        barkod: response.product.barkod,
                        satis_fiyati: response.product.satis_fiyati
                    }
                });
                
                // Kısayolları sakla
                window.productShortcuts = shortcuts;
                
                // Kısayol butonunu güncelle
                updateShortcutButton(position, response.product);
                
                // Bilgi mesajı göster
                showToast('Ürün kısayola eklendi', 'success');
                
                // Ürün seçme modalını kapat
                $('#productSelectionModal').addClass('hidden');
            } else {
                showToast('Ürün bilgileri alınamadı', 'error');
            }
        },
        error: function() {
            showToast('Ürün bilgileri alınırken bir hata oluştu', 'error');
        }
    });
}

function highlightShortcut(position) {
    const $shortcut = $(`.shortcut-item[data-position="${position}"]`);
    
    if ($shortcut.length) {
        // Geçici vurgulama stilleri
        $shortcut.addClass('highlight-shortcut');
        
        // Görünür olduğundan emin ol
        $shortcut[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
        
        // Bir süre sonra vurguyu kaldır
        setTimeout(function() {
            $shortcut.removeClass('highlight-shortcut');
        }, 3000);
    }
}

/**
 * Kısayol butonunu güncelle
 * @param {number} position - Pozisyon
 * @param {Object} product - Ürün verileri
 */
function updateShortcutButton(position, product) {
    // Position'a göre ilgili butonu bul ve güncelle
    $(`.shortcut-item[data-position="${position}"] .shortcut-content`).html(`
        <div class="text-sm font-medium">${product.ad}</div>
        <div class="text-xs text-gray-500">${formatPrice(product.satis_fiyati)}</div>
    `).addClass('has-product');
    
    // Silme butonu ekle
    if ($(`.shortcut-item[data-position="${position}"] .btn-remove-shortcut`).length === 0) {
        $(`.shortcut-item[data-position="${position}"] .absolute`).html(`
            <button type="button" class="btn-remove-shortcut text-red-500 hover:text-red-700 text-xs" data-position="${position}">
                <i class="fas fa-times-circle"></i>
            </button>
        `);
    }
}


// Kısayolu kaldır
function removeShortcut(position) {
    // Geçici olarak kısayolları saklayacağımız değişken
    let shortcuts = window.productShortcuts || [];
    
    // Kısayolu kaldır
    shortcuts = shortcuts.filter(s => s.position !== position);
    
    // Kısayolları sakla
    window.productShortcuts = shortcuts;
    
    // Kısayol ayarlarını yenile
    renderShortcutSettings(shortcuts);
    
    // Bilgi mesajı göster
    showToast('Kısayol kaldırıldı');
}

// Kısayolları kaydet
function saveShortcuts() {
    // Kısayolları al
    const shortcuts = window.productShortcuts || [];
    
    // API'ye gönder
    $.ajax({
        url: 'admin/api/update_product_shortcuts.php',
        type: 'POST',
        data: JSON.stringify({ shortcuts: shortcuts }),
        contentType: 'application/json',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Ayarlar modalını kapat
                $('#ayarlarModal').addClass('hidden');
                
                // Ana ekrandaki kısayolları yenile
                loadKisayolUrunler();
                
                // Bilgi mesajı göster
                showToast(response.message || 'Kısayollar başarıyla kaydedildi');
            } else {
                showToast(response.message || 'Kısayollar kaydedilemedi', 'error');
            }
        },
        error: function() {
            showToast('Kısayollar kaydedilirken bir hata oluştu', 'error');
        }
    });
}

/**
 * Ödeme modalını aç
 */
function openOdemeModal() {
    // Toplamları güncelle
    const genelToplam = sepet.reduce((total, item) => total + item.toplam, 0);
    const odenecekTutar = getOdenecekTutar();
    
    $('#odemeToplam').text(formatPrice(genelToplam));
    $('#odemeKullanilanPuan').text(kullanilacakPuan > 0 ? `${kullanilacakPuan} Puan` : '0 Puan');
    $('#odemeOdenecek').text(formatPrice(odenecekTutar));
    
    // Ödeme yöntemlerini sıfırla
    seciliOdemeYontemi = null;
    $('.odeme-yontemi').removeClass('bg-green-600').addClass('bg-blue-500');
    $('.odeme-alani').addClass('hidden');
    
    // Modalı aç
    $('#odemeModal').removeClass('hidden');
}

/**
 * Ürün sepete ekle
 * @param {Object} product - Ürün nesnesi
 * @param {number} quantity - Miktar (opsiyonel, varsayılan: 1)
 */
function addProductToCart(product, quantity = 1) {
    if (!product || !product.id) {
        console.error("Geçersiz ürün bilgisi");
        return;
    }
    
    // Miktar kontrolü
    quantity = parseInt(quantity);
    if (isNaN(quantity) || quantity <= 0) {
        quantity = 1;
    }
    
    // Fiyat hesaplama - önce indirim kontrolü yap
    let price = parseFloat(product.satis_fiyati);
    let originalPrice = parseFloat(product.satis_fiyati);
    let discountRate = 0;
    let isDiscounted = false;
    
    // İndirim kontrolü
    const now = new Date();
    let startDate = null;
    let endDate = null;
    
    if (product.indirim_baslangic_tarihi) {
        startDate = new Date(product.indirim_baslangic_tarihi);
    }
    
    if (product.indirim_bitis_tarihi) {
        endDate = new Date(product.indirim_bitis_tarihi);
    }
    
    // İndirimli fiyat ve tarih kontrolü
    if (product.indirimli_fiyat && 
        startDate && endDate && 
        now >= startDate && now <= endDate) {
        
        price = parseFloat(product.indirimli_fiyat);
        isDiscounted = true;
        
        // İndirim oranını hesapla (yüzde olarak)
        if (originalPrice > 0) {
            discountRate = ((originalPrice - price) / originalPrice) * 100;
        }
    }
    
    // Mevcut sepeti kontrol et
    let cartItems = getCartItems();
    
    // Bu ürün zaten sepette mi kontrol et
    const existingItemIndex = cartItems.findIndex(item => item.id === product.id);
    
    if (existingItemIndex !== -1) {
        // Ürün zaten sepette, miktarı artır
        cartItems[existingItemIndex].quantity += quantity;
    } else {
        // Yeni ürün ekle
        cartItems.push({
            id: product.id,
            barkod: product.barkod,
            ad: product.ad,
            birim_fiyat: price,
            original_price: originalPrice,
            quantity: quantity,
            is_discounted: isDiscounted,
            discount_rate: discountRate,
            total: price * quantity
        });
    }
    
    // Sepeti güncelle
    updateCart(cartItems);
    
    // Sepet arayüzünü güncelle
    refreshCartUI();
    
    // Kullanıcıya bildir
    showNotification("Ürün sepete eklendi", "success");
}

/**
 * Sepet öğelerini al
 * @returns {Array} Sepet öğeleri
 */
function getCartItems() {
    // localStorage'dan veya session'dan sepeti al
    const cartJSON = localStorage.getItem('cart');
    return cartJSON ? JSON.parse(cartJSON) : [];
}

/**
 * Sepeti güncelle
 * @param {Array} items - Sepet öğeleri
 */
function updateCart(items) {
    // Sepeti localStorage'a veya session'a kaydet
    localStorage.setItem('cart', JSON.stringify(items));
    
    // Toplam hesaplama
    calculateCartTotals();
}

/**
 * Sepet toplamlarını hesapla
 */
function calculateCartTotals() {
    const cartItems = getCartItems();
    
    let subtotal = 0;
    let discount = 0;
    let total = 0;
    let itemCount = 0;
    
    cartItems.forEach(item => {
        const itemTotal = item.birim_fiyat * item.quantity;
        subtotal += item.original_price * item.quantity;
        
        if (item.is_discounted) {
            const itemDiscount = (item.original_price - item.birim_fiyat) * item.quantity;
            discount += itemDiscount;
        }
        
        total += itemTotal;
        itemCount += item.quantity;
    });
    
    // Toplam değerleri güncelle
    updateTotalDisplay(subtotal, discount, total, itemCount);
}

/**
 * Sepet UI'ını güncelle
 */
function refreshCartUI() {
    const cartItems = getCartItems();
    const cartTableBody = document.getElementById('cartTableBody');
    
    if (!cartTableBody) return;
    
    // Tabloyu temizle
    cartTableBody.innerHTML = '';
    
    // Boş sepet kontrolü
    if (cartItems.length === 0) {
        cartTableBody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-4">
                    Sepetinizde ürün bulunmamaktadır.
                    <br><small>Ürünleri barkod okutarak veya arayarak ekleyebilirsiniz.</small>
                </td>
            </tr>
        `;
        return;
    }
    
    // Sepetteki her ürün için satır ekle
    cartItems.forEach((item, index) => {
        const row = document.createElement('tr');
        
        // İndirimli ürün için stil
        if (item.is_discounted) {
            row.classList.add('bg-green-50');
        }
        
        row.innerHTML = `
            <td class="px-6 py-4">${item.ad}</td>
            <td class="px-6 py-4 text-center">
                <div class="flex items-center justify-center">
                    <button onclick="decreaseQuantity(${index})" class="text-red-500 px-2">-</button>
                    <input type="number" min="1" value="${item.quantity}" 
                           onchange="updateItemQuantity(${index}, this.value)"
                           class="w-16 text-center border rounded p-1">
                    <button onclick="increaseQuantity(${index})" class="text-green-500 px-2">+</button>
                </div>
            </td>
            <td class="px-6 py-4 text-right">
                ${item.is_discounted ? 
                  `<span class="line-through text-gray-500">${formatCurrency(item.original_price)}</span><br>
                   <span class="text-green-600 font-semibold">${formatCurrency(item.birim_fiyat)}</span>
                   <span class="text-xs text-green-600 ml-1">(%${Math.round(item.discount_rate)})</span>` : 
                  formatCurrency(item.birim_fiyat)}
            </td>
            <td class="px-6 py-4 text-right font-semibold">${formatCurrency(item.birim_fiyat * item.quantity)}</td>
            <td class="px-6 py-4 text-center">
                <button onclick="removeCartItem(${index})" class="text-red-600 hover:text-red-800">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </button>
            </td>
        `;
        
        cartTableBody.appendChild(row);
    });
}

/**
 * Toplam bilgilerini güncelle
 */
function updateTotalDisplay(subtotal, discount, total, itemCount) {
    // Toplam ürün sayısını güncelle
    const totalItemsElement = document.getElementById('totalItems');
    if (totalItemsElement) {
        totalItemsElement.textContent = itemCount;
    }
    
    // Ara toplam değerini güncelle
    const subtotalElement = document.getElementById('cartSubtotal');
    if (subtotalElement) {
        subtotalElement.textContent = formatCurrency(subtotal);
    }
    
    // İndirim toplamını güncelle
    const discountElement = document.getElementById('cartDiscount');
    if (discountElement) {
        discountElement.textContent = formatCurrency(discount);
        
        // İndirim varsa göster, yoksa gizle
        const discountRow = document.getElementById('discountRow');
        if (discountRow) {
            discountRow.style.display = discount > 0 ? 'table-row' : 'none';
        }
    }
    
    // Genel toplam değerini güncelle
    const totalElement = document.getElementById('cartTotal');
    if (totalElement) {
        totalElement.textContent = formatCurrency(total);
    }
}

/**
 * Bildirim göster
 */
function showNotification(message, type = 'info') {
    // SweetAlert2 veya benzeri bir kütüphane ile bildirim göster
    if (typeof Swal !== 'undefined') {
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });
        
        Toast.fire({
            icon: type,
            title: message
        });
    } else {
        // Swal yoksa basit bir bildirim göster
        alert(message);
    }
}

/**
 * Tüm modalları kapat
 */
function closeAllModals() {
    $('#musteriSecModal, #stokModal, #yeniMusteriModal, #indirimModal, #bekletModal, #ayarlarModal, #odemeModal, #musteriBorcModal, #borcOdemeModal, #musteriBorcModal, #yeniBorcModal, #urunAramaModal, #urunDetayModal, #gecmisSiparislerModal, #siparisDetayModal, #musteriDuzenleModal, #raporlarModal, #productSelectionModal, #bekleyenFislerModal').addClass('hidden');
}