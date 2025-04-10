const BASE_URL = '/';
const API_ENDPOINTS = {
    GET_INVOICE_PRODUCTS: BASE_URL + 'api/get_invoice_products.php',
    GET_MAGAZALAR: BASE_URL + 'api/get_magazalar.php',
    GET_TEDARIKCILER: BASE_URL + 'api/get_tedarikciler.php',
    SEARCH_PRODUCT: BASE_URL + 'api/search_product.php',
    TRANSFER_TO_STORE: BASE_URL + 'api/transfer_to_store.php',
    ADD_INVOICE: BASE_URL + 'api/add_invoice.php',
    GET_INVOICE_DETAILS: BASE_URL + 'api/get_invoice_details.php',
    UPDATE_INVOİCE: BASE_URL + 'api/update_invoice.php',
    ADD_MAGAZA: BASE_URL + 'api/add_magaza.php',
    ADD_TEDARIKCI: BASE_URL + 'api/tedarikci.php',
    SAVE_INVOICE_PRODUCTS: BASE_URL + 'api/save_invoice_products.php'
};

// Ürün Ekleme Modalı
window.addProducts = function(faturaId) {
    // Önce mevcut ürünleri yükle, sonra modalı aç
    loadExistingProducts(faturaId).then(() => {
        Swal.fire({
            title: 'Ürün Ekle',
            html: `
                <!-- Ürün Tablosu -->
                <div class="bg-white p-4 rounded-lg shadow mb-6">
                    <div class="mb-4">
                        <input type="text" id="barkodSearch" class="w-full px-4 py-2 border rounded-md" 
                               placeholder="Barkod okutun veya ürün adı ile arama yapın">
                    </div>
                    <div id="searchResults" class="mb-4 max-h-40 overflow-y-auto"></div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Kod</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Barkod</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Adı</th>
                                    <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Miktar</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Birim Fiyat</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">İsk1(%)</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">İsk2(%)</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">İsk3(%)</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">KDV(%)</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Toplam</th>
                                    <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">İşlem</th>
                                </tr>
                            </thead>
                            <tbody id="productTableBody">
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Özet Bilgileri -->
                <div class="bg-gray-50 p-4 rounded-lg mt-4">
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <div class="font-semibold text-gray-700 mb-2">Toplam İskonto:</div>
                            <div id="toplamIskonto" class="text-lg font-bold">₺0.00</div>
                        </div>
                        <div class="space-y-2">
                            <div id="kdvOzet"></div>
                        </div>
                        <div class="text-right">
                            <div class="font-semibold text-gray-700 mb-2">Genel Toplam:</div>
                            <div id="genelToplam" class="text-xl font-bold text-blue-600">₺0.00</div>
                        </div>
                    </div>
                </div>
            `,
            width: '99%',
            showDenyButton: true,
            showCancelButton: true,
            confirmButtonText: 'Kaydet',
            denyButtonText: 'Faturayı Bitir',
            cancelButtonText: 'İptal',
            denyButtonColor: '#10B981',
            didOpen: async () => {
                try {
                    // Local storage'dan önceki verileri yükle
                    const savedData = localStorage.getItem(`fatura_${faturaId}_products`);
                    if (savedData) {
                        window.selectedProducts = JSON.parse(savedData);
                    }

                    // Ürün arama fonksiyonunu başlat
                    initializeProductSearch(faturaId);

                    // Ürünleri tabloya yükle
                    updateProductTable();

                    // Genel toplamı güncelle
                    updateInvoiceTotal();

                    // Enter tuşu ile arama için event listener
                    document.getElementById('barkodSearch').addEventListener('keypress', function(e) {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            const searchTerm = this.value.trim();
                            if (searchTerm) {
                                searchProducts(searchTerm, faturaId);
                            }
                        }
                    });

                    console.log('Modal açıldı, mevcut ürünler:', window.selectedProducts);
                } catch (error) {
                    console.error('Modal açılırken hata:', error);
                    Swal.showValidationMessage(`Hata: ${error.message}`);
                }
            },
            willClose: () => {
                // Modal kapanmadan önce verileri local storage'a kaydet
                if (window.selectedProducts?.length) {
                    localStorage.setItem(`fatura_${faturaId}_products`, JSON.stringify(window.selectedProducts));
                }
            },
            preConfirm: async () => {
                try {
                    if (!window.selectedProducts?.length) {
                        throw new Error('Lütfen en az bir ürün ekleyin');
                    }
                    console.log('Kaydedilecek ürünler:', window.selectedProducts);
                    return await saveProducts(faturaId, false);
                } catch (error) {
                    console.error('Kaydetme hatası:', error);
                    Swal.showValidationMessage(`Hata: ${error.message}`);
                    return false;
                }
            },
            preDeny: async () => {
                try {
                    if (!window.selectedProducts?.length) {
                        throw new Error('Lütfen en az bir ürün ekleyin');
                    }
                    console.log('Fatura tamamlanıyor, ürünler:', window.selectedProducts);
                    return await saveProducts(faturaId, true);
                } catch (error) {
                    console.error('Fatura tamamlama hatası:', error);
                    Swal.showValidationMessage(`Hata: ${error.message}`);
                    return false;
                }
            }
        }).then((result) => {
            if (result.isConfirmed || result.isDenied) {
                // Başarılı işlem sonrası local storage'ı temizle
                localStorage.removeItem(`fatura_${faturaId}_products`);
                
                Swal.fire({
                    icon: 'success',
                    title: 'Başarılı!',
                    text: result.isDenied ? 'Fatura tamamlandı' : 'Fatura kaydedildi',
                    showConfirmButton: false,
                    timer: 1500
                }).then(() => {
                    location.reload();
                });
            }
        });
    }).catch(error => {
        console.error('Ürünler yüklenirken hata:', error);
        Swal.fire({
            icon: 'error',
            title: 'Hata!',
            text: 'Ürünler yüklenirken bir hata oluştu'
        });
    });
};

// Yeni ekle fonksiyonu - window objesine ekle
window.handleAddProduct = function(button) {
    const tr = button.closest('tr');
    if (!tr || !tr.dataset.product) {
        console.error('Ürün verisi bulunamadı');
        return;
    }

    try {
        const productData = JSON.parse(tr.dataset.product);
        productData.urun_id = productData.id; // id'yi urun_id olarak kopyala
        window.addToInvoiceFromSearch(productData);
    } catch (error) {
        console.error('Ürün verisi işlenirken hata:', error);
    }
};

window.addToInvoiceFromSearch = function(product) {
    if (!window.selectedProducts) {
        window.selectedProducts = [];
    }

    // Barkod'a göre kontrol et
    const existingProduct = window.selectedProducts.find(p => p.barkod === product.barkod);
    if (existingProduct) {
        // Ürün zaten ekliyse hata mesajı göster
        Swal.fire({
            icon: 'warning',
            title: 'Uyarı',
            text: 'Bu ürün zaten eklenmiş!',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000
        });
        return;
    }

    // Yeni ürünü ekle
    window.selectedProducts.push({
        id: product.id,
        urun_id: product.urun_id || product.id,
        kod: product.kod || '-',
        barkod: product.barkod,
        ad: product.ad,
        miktar: 1,
        birim_fiyat: parseFloat(product.alis_fiyati || product.satis_fiyati),
        iskonto1: 0,
        iskonto2: 0,
        iskonto3: 0,
        kdv_orani: parseFloat(product.kdv_orani || 0),
        toplam: parseFloat(product.alis_fiyati || product.satis_fiyati)
    });

    // Tabloyu güncelle
    updateProductTable();
    
    // Arama kutusunu temizle ve odaklan
    const searchInput = document.getElementById('barkodSearch');
    if (searchInput) {
        searchInput.value = '';
        searchInput.focus();
    }
};

// Bu kısım güncellenecek
window.selectedProducts = []; // Global array'i başlat

// Tablo oluşturma fonksiyonu
function createProductTable() {
    return `
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-2 text-left">Kod</th>
                <th class="px-4 py-2 text-left">Barkod</th>
                <th class="px-4 py-2 text-left">Adı</th>
                <th class="px-4 py-2 text-right">Miktarı</th>
                <th class="px-4 py-2 text-right">Birim Fiyat</th>
                <th class="px-4 py-2 text-right">İskonto1 (%)</th>
                <th class="px-4 py-2 text-right">İskonto2 (%)</th>
                <th class="px-4 py-2 text-right">İskonto3 (%)</th>
                <th class="px-4 py-2 text-right">KDV Oranı (%)</th>
                <th class="px-4 py-2 text-right">Toplam Tutar</th>
                <th class="px-4 py-2 text-center">İşlemler</th>
            </tr>
        </thead>
        <tbody id="productTableBody">
        </tbody>
    </table>
    `;
}

function openModal(modalId) {
    document.getElementById(modalId).classList.remove('hidden');
}

// Fatura silme fonksiyonu
function deleteInvoice(id) {
    Swal.fire({
        title: 'Emin misiniz?',
        text: "Bu faturayı silmek istediğinize emin misiniz?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Evet, sil!',
        cancelButtonText: 'İptal'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('api/delete_invoice.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ id: id })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Başarılı!',
                        text: 'Fatura başarıyla silindi',
                        showConfirmButton: false,
                        timer: 1500
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    throw new Error(data.message || 'Fatura silinirken bir hata oluştu');
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Hata!',
                    text: error.message
                });
            });
        }
    });
}

async function loadTedarikciOptions() {
    try {
        console.log('Tedarikçiler yükleniyor...');
        const response = await fetch('api/get_tedarikciler.php');
        const data = await response.json();
        
        const tedarikciSelect = document.querySelector('select[name="tedarikci"]');
        if (!tedarikciSelect) {
            console.warn('Tedarikçi select elementi bulunamadı');
            return;
        }

        let options = `
            <option value="">Tedarikçi Seçin</option>
            <option value="add_new" style="font-weight: 600; color: #2563eb;">+ Yeni Tedarikçi Ekle</option>
        `;

        if (data.success && data.tedarikciler) {
            data.tedarikciler.forEach(tedarikci => {
                options += `<option value="${tedarikci.id}">${tedarikci.ad}</option>`;
            });
        }

        tedarikciSelect.innerHTML = options;
        console.log('Tedarikçi listesi güncellendi');

    } catch (error) {
        console.error('Tedarikçiler yüklenirken hata:', error);
    }
}

// Event listener'ları başlat
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM yüklendi, tedarikçiler yükleniyor...');
    
    // Tedarikçi select değişikliğini dinle
    document.addEventListener('change', function(e) {
        if (e.target.matches('select[name="tedarikci"]')) {
            if (e.target.value === 'add_new') {
                e.target.value = '';
                openAddTedarikciModal();
            }
        }
    });

    // Yeni fatura ekleme modalı açıldığında
    const addInvoiceButton = document.querySelector('button[onclick="addInvoice()"]');
    if (addInvoiceButton) {
        addInvoiceButton.addEventListener('click', function() {
            console.log('Yeni fatura modalı açılıyor...');
            setTimeout(loadTedarikciOptions, 500);
        });
    }
});

// DOM yüklendiğinde çalışacak fonksiyonlar
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM yüklendi, tedarikçiler yükleniyor...'); // Debug log
    
    // İlk yüklemede tedarikçileri getir
    loadTedarikciOptions();
    
    // Tedarikçi select değişikliğini dinle
    document.addEventListener('change', function(e) {
        if (e.target.matches('select[name="tedarikci"]')) {
            console.log('Tedarikçi seçimi değişti:', e.target.value); // Debug log
            if (e.target.value === 'add_new') {
                e.target.value = ''; // Select'i sıfırla
                openAddTedarikciModal();
            }
        }
    });
    
    // Yeni fatura ekleme modalı açıldığında
    const addInvoiceButton = document.querySelector('button[onclick="addInvoice()"]');
    if (addInvoiceButton) {
        addInvoiceButton.addEventListener('click', function() {
            console.log('Yeni fatura modalı açılıyor...'); // Debug log
            setTimeout(loadTedarikciOptions, 500); // Modal açıldıktan sonra tedarikçileri yükle
        });
    }
});

async function getTedarikciListesi() {
    try {
        const response = await fetch('api/get_tedarikciler.php');
        const data = await response.json();
        
        if (!data.success) {
            throw new Error('Tedarikçiler yüklenemedi');
        }

        return data.tedarikciler;
    } catch (error) {
        console.error('Tedarikçiler alınırken hata:', error);
        throw error;
    }
}

async function updateTedarikciSelect(selectedId = null) {
    try {
        const response = await fetch('api/get_tedarikciler.php');
        const data = await response.json();

        if (!data.success) {
            throw new Error('Tedarikçi listesi güncellenemedi');
        }

        const tedarikciSelect = document.querySelector('select[name="tedarikci"]');
        if (tedarikciSelect) {
            tedarikciSelect.innerHTML = `
                <option value="">Tedarikçi Seçin</option>
                <option value="add_new" class="font-semibold text-blue-600">+ Yeni Tedarikçi Ekle</option>
                ${data.tedarikciler.map(t => 
                    `<option value="${t.id}" ${t.id === selectedId ? 'selected' : ''}>${t.ad}</option>`
                ).join('')}
            `;
        }
    } catch (error) {
        console.error('Tedarikçi select güncellenirken hata:', error);
    }
}

function openAddTedarikciModal() {
    Swal.fire({
        title: 'Yeni Tedarikçi Ekle',
        html: `
            <form id="addTedarikciForm" class="text-left">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Tedarikçi Adı*</label>
                    <input type="text" name="ad" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Telefon*</label>
                    <input type="tel" name="telefon" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Adres*</label>
                    <textarea name="adres" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required></textarea>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Şehir*</label>
                    <input type="text" name="sehir" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">E-posta</label>
                    <input type="email" name="eposta" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                </div>
            </form>
        `,
        showCancelButton: true,
        confirmButtonText: 'Kaydet',
        cancelButtonText: 'İptal',
        preConfirm: async () => {
            const form = document.getElementById('addTedarikciForm');
            const formData = new FormData(form);

            try {
                const response = await fetch('api/add_tedarikci.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.message || 'Tedarikçi eklenirken bir hata oluştu');
                }

                return data;
            } catch (error) {
                Swal.showValidationMessage(error.message);
            }
        }
    }).then(async (result) => {
        if (result.isConfirmed) {
            try {
                // Yeni tedarikçiyi seçili hale getirerek listeyi güncelle
                await updateTedarikciSelect(result.value.tedarikci_id);

                // Başarı mesajı
                Swal.fire({
                    icon: 'success',
                    title: 'Başarılı!',
                    text: 'Tedarikçi başarıyla eklendi',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000
                });
            } catch (error) {
                console.error('Tedarikçi güncellenirken hata oluştu:', error);
            }
        }
    });
}

// Sayfa yüklendiğinde ve modal açıldığında tedarikçileri yükle
document.addEventListener('DOMContentLoaded', function() {
	loadTedarikciOptions();
    // Tedarikçi seçim değişikliğini dinle
    document.addEventListener('change', function(e) {
        if (e.target.matches('select[name="tedarikci"]')) {
            if (e.target.value === 'add_new') {
                e.target.value = '';
                openAddTedarikciModal();
            }
        }
    });
});

// Tedarikçi seçeneklerini yükleyen yeni fonksiyon
document.addEventListener('DOMContentLoaded', async function() {
    // Tedarikçi select elementini bul
    const tedarikciSelect = document.querySelector('select[name="tedarikci"]');
    if (tedarikciSelect) {
        // Tedarikçileri yükle
        const options = await getTedarikciOptions();
        tedarikciSelect.innerHTML = '<option value="">Tedarikçi Seçin</option>' + options;
    }
});

async function handleAddInvoice(event) {
    event.preventDefault();
    
    // Form verilerini al
    const form = event.target;
    const formData = new FormData(form);

    // Zorunlu alanları kontrol et
    const faturaSeri = formData.get('fatura_seri');
    const faturaNo = formData.get('fatura_no');
    const tedarikci = formData.get('tedarikci');
    const faturaTarihi = formData.get('fatura_tarihi');

    // Boş alan kontrolü
    if (!faturaSeri || !faturaNo || !tedarikci || !faturaTarihi) {
        Swal.fire({
            icon: 'error',
            title: 'Hata!',
            text: 'Lütfen tüm zorunlu alanları doldurun.',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000
        });
        return;
    }

    try {
        // Submit butonunu devre dışı bırak
        const submitButton = form.querySelector('button[type="submit"]');
        submitButton.disabled = true;
        submitButton.textContent = 'Oluşturuluyor...';

        const response = await fetch(API_ENDPOINTS.ADD_INVOICE, {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            // Başarılı mesajı göster
            Swal.fire({
                icon: 'success',
                title: 'Başarılı!',
                text: 'Fatura başarıyla oluşturuldu',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000
            });

            // Modalı kapat ve sayfayı yenile
            closeModal('addInvoiceModal');
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {
            throw new Error(result.message || 'Bir hata oluştu');
        }
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Hata!',
            text: error.message,
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000
        });
    } finally {
        // Submit butonunu tekrar aktif et
        const submitButton = form.querySelector('button[type="submit"]');
        submitButton.disabled = false;
        submitButton.textContent = 'Fatura Oluştur';
    }
}

// Sayfa yüklendiğinde event listener'ları ekleyelim
document.addEventListener('DOMContentLoaded', function() {
    // ESC tuşu ile modalları kapatma
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal').forEach(modal => {
                modal.classList.add('hidden');
            });
        }
    });

    // Modal dışına tıklayınca kapatma
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.add('hidden');
            }
        });
    });
});

// Tedarikci ayarları
async function getTedarikciOptions() {
    try {
        const response = await fetch(API_ENDPOINTS.GET_TEDARIKCILER);
        const data = await response.json();
        
        if (data.success && data.tedarikciler) {
            return data.tedarikciler.map(tedarikci => 
                `<option value="${tedarikci.id}">${tedarikci.ad}</option>`
            ).join('');
        }
        return '<option value="">Tedarikçi bulunamadı</option>';
    } catch (error) {
        console.error('Tedarikçiler yüklenirken hata:', error);
        return '<option value="">Hata oluştu</option>';
    }
}

// Enter tuşu ile arama yapma
document.getElementById('barkodSearch').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        searchProduct();
    }
});

function displaySearchResults(products) {
    // Önce mevcut seçili ürünlerin barkodlarını al
    const selectedBarkods = window.selectedProducts ? window.selectedProducts.map(p => p.barkod) : [];
    
    // Arama sonuçlarından seçili ürünleri filtrele
    const filteredProducts = products.filter(product => 
        !selectedBarkods.includes(product.barkod) // barkod'a göre kontrol
    );

    const searchResults = document.getElementById('searchResults');
    if (!searchResults) return;

    // Eğer filtreleme sonrası hiç ürün kalmadıysa
    if (filteredProducts.length === 0) {
        searchResults.innerHTML = '<div class="text-center py-4 text-gray-500">Uygun ürün bulunamadı</div>';
        return;
    }

    let html = `
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">KOD</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">BARKOD</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">ÜRÜN ADI</th>
                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">STOK</th>
                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">FİYAT</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                ${filteredProducts.map(product => `
                    <tr class="hover:bg-gray-50" data-product='${JSON.stringify(product)}'>
                        <td class="px-3 py-2 text-sm">${product.kod || '-'}</td>
                        <td class="px-3 py-2 text-sm">${product.barkod}</td>
                        <td class="px-3 py-2 text-sm">${product.ad}</td>
                        <td class="px-3 py-2 text-sm text-right">${product.stok_miktari}</td>
                        <td class="px-3 py-2 text-sm text-right">₺${Number(product.satis_fiyati).toFixed(2)}</td>
                        <td class="px-3 py-2 text-sm text-right">
                            <button type="button" 
                                    onclick="window.handleAddProduct(this)"
                                    class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-xs">
                                Ekle
                            </button>
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;

    searchResults.innerHTML = html;
}
function fixDecimal(value) {
    if (typeof value === 'string') {
        return parseFloat(value.replace(',', '.'));
    }
    return value;
}

// Ürünleri kaydetme fonksiyonu
async function saveProducts(faturaId, isComplete) {
    try {
        if (!window.selectedProducts || window.selectedProducts.length === 0) {
            throw new Error('Lütfen faturaya ürün ekleyin');
        }

        // Sayısal değerleri düzelt
        const fixedProducts = window.selectedProducts.map(product => ({
            ...product,
            miktar: fixDecimal(product.miktar),
            birim_fiyat: fixDecimal(product.birim_fiyat),
            urun_id : product.urun_id || product.id,
            iskonto1: fixDecimal(product.iskonto1),
            iskonto2: fixDecimal(product.iskonto2),
            iskonto3: fixDecimal(product.iskonto3),
            kdv_orani: fixDecimal(product.kdv_orani),
            toplam: fixDecimal(product.toplam)
        }));

        const response = await fetch('api/save_invoice_products.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                fatura_id: faturaId,
                products: fixedProducts,
                is_complete: isComplete
            })
        });

        const data = await response.json();

        if (!data.success) {
            throw new Error(data.message || 'Bir hata oluştu');
        }

        return data;
    } catch (error) {
        Swal.showValidationMessage(error.message);
        return false;
    }
}

// Form submit işleyicisi
document.getElementById('addProductForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    saveProducts();
});

// addToInvoice fonksiyonunu güncelle
function addToInvoice(product) {
    if (!window.selectedProducts) {
        window.selectedProducts = [];
    }

    // Ürün zaten ekli mi kontrol et
    const existingProduct = window.selectedProducts.find(p => p.id === product.id);
    if (existingProduct) {
        existingProduct.miktar += 1;
        existingProduct.toplam = existingProduct.miktar * existingProduct.birim_fiyat;
    } else {
        window.selectedProducts.push({
            id: product.id,
            barkod: product.barkod,
            urun_id:product.urun_id,
            ad: product.ad,
            miktar: 1,
            birim_fiyat: parseFloat(product.alis_fiyati || product.satis_fiyati),
            kdv_orani: parseFloat(product.kdv_orani),
            toplam: parseFloat(product.alis_fiyati || product.satis_fiyati)
        });
    }

    // Tabloyu güncelle
    updateProductTable();
    
    // Arama kutusunu temizle ve odaklan
    const searchInput = document.getElementById('barkodSearch');
    if (searchInput) {
        searchInput.value = '';
        searchInput.focus();
    }
}

// Ürün tablosunu güncelleme fonksiyonu
function updateProductTable() {
    const tableBody = document.getElementById('productTableBody');
    if (!tableBody) return;

    // Clear existing rows
    tableBody.innerHTML = window.selectedProducts.map((product, index) => `
        <tr>
            <td class="px-4 py-2">${product.kod || '-'}</td>
            <td class="px-4 py-2">${product.barkod || '-'}</td>
            <td class="px-4 py-2">${product.ad || '-'}</td>
            <td class="px-4 py-2">
                <input type="number" 
                       class="miktar border rounded px-2 py-1 w-20" 
                       value="${product.miktar || 1}" 
                       min="1" 
                       step="1" 
                       onchange="updateRowTotal(this)">
            </td>
            <td class="px-4 py-2">
                <input type="number" 
                       class="birim-fiyat border rounded px-2 py-1 w-24" 
                       value="${product.birim_fiyat || 0}" 
                       min="0.01" 
                       step="0.01" 
                       onchange="updateRowTotal(this)">
            </td>
            <td class="px-4 py-2">
                <div class="flex flex-col">
                    <input type="number" 
                           class="iskonto1 border rounded px-2 py-1 w-16" 
                           value="${product.iskonto1 || 0}" 
                           min="0" 
                           max="100" 
                           step="1" 
                           onchange="updateRowTotal(this)"
                           oninput="this.value = Math.floor(this.value)">
                    <div class="iskonto1-tutar text-xs text-green-600 mt-1">₺0.00</div>
                </div>
            </td>
            <td class="px-4 py-2">
                <div class="flex flex-col">
                    <input type="number" 
                           class="iskonto2 border rounded px-2 py-1 w-16" 
                           value="${product.iskonto2 || 0}" 
                           min="0" 
                           max="100" 
                           step="1" 
                           onchange="updateRowTotal(this)"
                           oninput="this.value = Math.floor(this.value)">
                    <div class="iskonto2-tutar text-xs text-green-600 mt-1">₺0.00</div>
                </div>
            </td>
            <td class="px-4 py-2">
                <div class="flex flex-col">
                    <input type="number" 
                           class="iskonto3 border rounded px-2 py-1 w-16" 
                           value="${product.iskonto3 || 0}" 
                           min="0" 
                           max="100" 
                           step="1" 
                           onchange="updateRowTotal(this)"
                           oninput="this.value = Math.floor(this.value)">
                    <div class="iskonto3-tutar text-xs text-green-600 mt-1">₺0.00</div>
                </div>
            </td>
            <td class="px-4 py-2">
                <div class="flex flex-col">
                    <select class="kdv-orani border rounded px-2 py-1 w-16" onchange="updateRowTotal(this)">
                        <option value="0" ${product.kdv_orani === 0 ? 'selected' : ''}>0</option>
                        <option value="10" ${product.kdv_orani === 10 ? 'selected' : ''}>10</option>
                        <option value="20" ${product.kdv_orani === 20 ? 'selected' : ''}>20</option>
                    </select>
                    <div class="kdv-tutar text-xs text-green-600 mt-1">₺0.00</div>
                </div>
            </td>
            <td class="px-4 py-2 text-right toplam-tutar">₺${(product.miktar * product.birim_fiyat).toFixed(2)}</td>
            <td class="px-4 py-2">
                <button onclick="removeProduct(this)" class="text-red-600 hover:text-red-800">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </button>
            </td>
        </tr>
    `).join('');

    // Tablodan sonra toplam
    const table = tableBody.closest('table');
    const existingTfoot = table.querySelector('tfoot');
    if (existingTfoot) {
        existingTfoot.remove();
    }

    // Toplamları güncelle
    updateInvoiceTotal();
}

// Miktar güncelleme
window.updateQuantity = function(index, value) {
    const quantity = parseInt(value) || 1;
    if (quantity < 1) return;

    window.selectedProducts[index].miktar = quantity;
    window.selectedProducts[index].toplam = quantity * window.selectedProducts[index].birim_fiyat;
    updateProductTable();
};

// Fiyat güncelleme
window.updatePrice = function(index, value) {
    const price = parseFloat(value) || 0;
    if (price < 0) return;

    window.selectedProducts[index].birim_fiyat = price;
    window.selectedProducts[index].toplam = price * window.selectedProducts[index].miktar;
    updateProductTable();
};

// Ürün silme
window.removeProduct = function(index) {
    window.selectedProducts.splice(index, 1);
    updateProductTable();
};

// Event Listeners
document.addEventListener('DOMContentLoaded', function() {
    // Enter tuşu ile arama
    const searchInput = document.getElementById('barkodSearch');
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                searchProduct();
            }
        });
    }

    // Modal dışına tıklayınca kapatma
    window.onclick = function(event) {
        if (event.target.matches('.modal')) {
            event.target.classList.add('hidden');
        }
    }
});


async function loadExistingProducts(faturaId) {
    try {
        const response = await fetch(`${API_ENDPOINTS.GET_INVOICE_PRODUCTS}?id=${faturaId}`);
        const result = await response.json();
        
        if (result.success) {
            window.selectedProducts = result.products.map(product => ({
                ...product,
                miktar: parseFloat(product.miktar) || 1,
                birim_fiyat: parseFloat(product.birim_fiyat) || 0,
                iskonto1: parseFloat(product.iskonto1) || 0,
                iskonto2: parseFloat(product.iskonto2) || 0,
                iskonto3: parseFloat(product.iskonto3) || 0,
                kdv_orani: parseFloat(product.kdv_orani) || 0,
                toplam: parseFloat(product.toplam_tutar) || 0
            }));
            
            // Debug için
            console.log('Yüklenen ürünler:', window.selectedProducts);
        }
    } catch (error) {
        console.error('Ürünler yüklenirken hata:', error);
    }
}

// handleTransfer fonksiyonunu güncelle
async function handleTransfer(formData, faturaId) {
   try {
       // faturaId hem formData'dan hem de parametre olarak alınabilir
       const fatura_id = formData.get('fatura_id') || faturaId;
       const magaza_id = formData.get('magaza_id');

       console.log('Transfer Values:', { fatura_id, magaza_id });

       if (!fatura_id) {
           throw new Error('Fatura ID bulunamadı');
       }

       if (!magaza_id) {
           throw new Error('Lütfen mağaza seçin');
       }

       // Seçili ürünleri ve transfer miktarlarını al
       const selectedProducts = window.transferProducts
           .filter(p => p.selected)
           .map(p => {
               const row = document.querySelector(`tr[data-index="${window.transferProducts.indexOf(p)}"]`);
               if (!row) return null;

               const transferInput = row.querySelector('input[data-field="transfer_miktar"]');
               const satisFiyatiInput = row.querySelector('input[data-field="satis_fiyati"]');
               const birimFiyatInput = row.querySelector('input[data-field="birim_fiyat"]');
               const karOraniInput = row.querySelector('input[data-field="kar_orani"]');

               return {
                   urun_id: p.urun_id,
                   barkod: p.barkod,
                   ad: p.ad,
                   selected: true,
                   miktar: parseFloat(p.miktar),
                   aktarilan_miktar: parseFloat(p.aktarilan_miktar || 0),
                   kalan_miktar: parseFloat(p.kalan_miktar),
                   transfer_miktar: parseFloat(transferInput?.value || 0),
                   birim_fiyat: parseFloat(birimFiyatInput?.value || p.birim_fiyat || 0),
                   satis_fiyati: parseFloat(satisFiyatiInput?.value || 0),
                   kar_orani: parseFloat(karOraniInput?.value || 0)
               };
           })
           .filter(p => p !== null);

       if (selectedProducts.length === 0) {
           throw new Error('Lütfen en az bir ürün seçin');
       }

       // Miktar kontrolü
       for (const product of selectedProducts) {
           if (!product.transfer_miktar || product.transfer_miktar <= 0) {
               throw new Error(`${product.ad} için geçerli bir miktar girin.`);
           }
           
           if (product.transfer_miktar > product.kalan_miktar) {
               throw new Error(`${product.ad} için aktarılmak istenen miktar (${product.transfer_miktar}) kalan miktardan (${product.kalan_miktar}) fazla olamaz.`);
           }
       }

       // Loading göster
       Swal.fire({
           title: 'Aktarım yapılıyor...',
           allowOutsideClick: false,
           didOpen: () => {
               Swal.showLoading();
           }
       });

       // Request verisi hazırla
       const requestData = {
           fatura_id: fatura_id,
           magaza_id: magaza_id,
           products: selectedProducts
       };

       console.log('Request Data:', requestData);

       const response = await fetch(API_ENDPOINTS.TRANSFER_TO_STORE, {
           method: 'POST',
           headers: {
               'Content-Type': 'application/json',
           },
           body: JSON.stringify(requestData)
       });

       const result = await response.json();

       if (!result.success) {
           throw new Error(result.message || 'Aktarım sırasında bir hata oluştu');
       }

       // Başarılı mesajı göster
       Swal.fire({
           icon: 'success',
           title: 'Başarılı!',
           text: 'Ürünler başarıyla mağazaya aktarıldı',
           showConfirmButton: false,
           timer: 1500
       }).then(() => {
           location.reload();
       });

   } catch (error) {
       console.error('Transfer error:', error);
       Swal.fire({
           icon: 'error',
           title: 'Hata!',
           text: error.message
       });
   }
}


// Mağaza seçeneklerini oluştur
async function getMagazaOptions() {
    try {
        const response = await fetch(API_ENDPOINTS.GET_MAGAZALAR);
        const data = await response.json();
        
        if (!data.success) {
            throw new Error('Mağazalar yüklenemedi');
        }

        return `
            <option value="">Mağaza Seçin</option>
            <option value="add_new" class="font-semibold text-blue-600">+ Yeni Mağaza Ekle</option>
            ${data.magazalar.map(magaza => 
                `<option value="${magaza.id}">${magaza.ad}</option>`
            ).join('')}
        `;
    } catch (error) {
        console.error('Mağaza seçenekleri yüklenirken hata:', error);
        return '<option value="">Mağazalar yüklenemedi</option>';
    }
}

// Mağaza seçimini izle
document.addEventListener('change', function(e) {
    if (e.target.matches('select[name="magaza_id"]')) {
        if (e.target.value === 'add_new') {
            e.target.value = ''; // Select'i sıfırla
            openAddMagazaModal();
        }
    }
});

// Mağaza listesini yeniden yükle
async function loadMagazaOptions() {
    try {
        const response = await fetch('api/get_magazalar.php');
        const data = await response.json();
        
        if (data.success) {
            const magazaSelects = document.querySelectorAll('select[name="magaza_id"]');
            magazaSelects.forEach(select => {
                select.innerHTML = getMagazaOptions(data.magazalar);
            });
        }
    } catch (error) {
        console.error('Mağazalar yüklenirken hata:', error);
    }
}

// Kar oranı değiştiğinde satış fiyatını hesapla
function calculateSalePrice(input, birimFiyat) {
    const karOrani = parseFloat(input.value) || 0;
    const row = input.closest('tr');
    const satisFiyatiInput = row.querySelector('input[name^="satis_fiyati_"]');
    const satisFiyati = birimFiyat * (1 + (karOrani / 100));
    satisFiyatiInput.value = satisFiyati.toFixed(2);
}

// Satış fiyatı değiştiğinde kar oranını hesapla
function calculateProfit(input, birimFiyat) {
    const satisFiyati = parseFloat(input.value) || 0;
    const row = input.closest('tr');
    const karInput = row.querySelector('input[name^="kar_"]');
    const karOrani = ((satisFiyati - birimFiyat) / birimFiyat) * 100;
    karInput.value = karOrani.toFixed(2);
}

// Transfer verilerini doğrula ve topla
function validateAndCollectTransferData() {
    const form = document.getElementById('transferForm');
    const magazaId = form.magaza_id.value;

    if (!magazaId) {
        throw new Error('Lütfen mağaza seçin');
    }

    const selectedProducts = [];
    form.querySelectorAll('input[name="selected_products[]"]:checked').forEach(checkbox => {
        const row = checkbox.closest('tr');
        const productId = checkbox.value;
        const transferMiktar = parseFloat(row.querySelector(`input[name="transfer_miktar_${productId}"]`).value);
        const satisFiyati = parseFloat(row.querySelector(`input[name="satis_fiyati_${productId}"]`).value);

        if (transferMiktar <= 0) {
            throw new Error('Geçersiz transfer miktarı');
        }

        if (satisFiyati <= 0) {
            throw new Error('Geçersiz satış fiyatı');
        }

        selectedProducts.push({
            id: productId,
            transfer_miktar: transferMiktar,
            satis_fiyati: satisFiyati
        });
    });

    if (selectedProducts.length === 0) {
        throw new Error('Lütfen en az bir ürün seçin');
    }

    return {
        fatura_id: form.fatura_id.value,
        magaza_id: magazaId,
        products: selectedProducts
    };
}

// Transfer işlemini gerçekleştir
async function submitTransfer(data) {
    try {
        const response = await fetch('api/transfer_to_store.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.message || 'Transfer sırasında bir hata oluştu');
        }

        Swal.fire({
            icon: 'success',
            title: 'Başarılı!',
            text: 'Transfer işlemi başarıyla tamamlandı',
            showConfirmButton: false,
            timer: 1500
        }).then(() => {
            location.reload();
        });

    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Hata!',
            text: error.message
        });
    }
}

// Tüm ürünleri seç/kaldır
function toggleAllProducts(checkbox) {
    const checkboxes = document.querySelectorAll('input[name="selected_products[]"]');
    checkboxes.forEach(cb => {
        cb.checked = checkbox.checked;
        const index = parseInt(cb.value);
        updateProductSelection(index, checkbox.checked);
    });
    hesaplaToplamlar();
}


// Tekil ürün seçimini güncelle
function updateProductSelection(index, selected) {
    if (window.transferProducts && window.transferProducts[index]) {
        window.transferProducts[index].selected = selected;
        hesaplaToplamlar();
    }
}

function updateProductQuantity(index, quantity) {
    try {
        if (!window.transferProducts || !window.transferProducts[index]) {
            console.warn('Ürün bulunamadı:', index);
            return;
        }

        const validQuantity = parseFloat(quantity) || 0;
        window.transferProducts[index].miktar = validQuantity;
        
        // Satırı güncelle
        updateProductRow(index);
        // Toplamları hesapla
        hesaplaToplamlar();
    } catch (error) {
        console.error('Miktar güncellenirken hata:', error);
    }
}

function updateProductPrice(index, price) {
    const validPrice = parseFloat(price) || 0;
    const product = window.transferProducts[index];
    
    product.satis_fiyati = validPrice;
    // Kar oranını güncelle
    const karOrani = ((validPrice - product.birim_fiyat) / product.birim_fiyat * 100);
    product.kar_orani = karOrani;
    
    // Satırı güncelle
    updateProductRow(index);
	    hesaplaToplamlar(); // Satır güncellendiğinde toplamları yeniden hesapla

}

function updateProductProfit(index, profit) {
    const validProfit = parseFloat(profit) || 0;
    const product = window.transferProducts[index];
    
    product.kar_orani = validProfit;
    // Satış fiyatını güncelle
    const satisFiyati = product.birim_fiyat * (1 + validProfit / 100);
    product.satis_fiyati = satisFiyati;
    
    // Satırı güncelle
    updateProductRow(index);
	    hesaplaToplamlar(); // Satır güncellendiğinde toplamları yeniden hesapla

}

// Toplu kar oranı uygulama fonksiyonu
function uygulaTopluKar() {
    const karOrani = parseFloat(document.getElementById('topluKarOrani').value);
    if (isNaN(karOrani)) return;

    window.transferProducts.forEach((product, index) => {
        // Alış fiyatı ve kar oranından satış fiyatını hesapla
        const alisFiyati = parseFloat(product.birim_fiyat);
        const satisFiyati = alisFiyati * (1 + (karOrani / 100));
        
        product.kar_orani = karOrani;
        product.satis_fiyati = satisFiyati;

        // Input alanlarını güncelle
        const row = document.querySelector(`tr[data-index="${index}"]`);
        if (row) {
            const satisFiyatiInput = row.querySelector('input[data-field="satis_fiyati"]');
            const karOraniInput = row.querySelector('input[data-field="kar_orani"]');
            
            if (satisFiyatiInput) satisFiyatiInput.value = satisFiyati.toFixed(2);
            if (karOraniInput) karOraniInput.value = karOrani.toFixed(2);
        }
    });

    hesaplaToplamlar();
}

// Satır güncelleme fonksiyonunu düzelt
function updateProductRow(index) {
    try {
        const row = document.querySelector(`tr[data-index="${index}"]`);
        if (!row) return;

        const product = window.transferProducts[index];
        if (!product) return;

        // Miktar input
        const quantityInput = row.querySelector('td:nth-child(3) input');
        if (quantityInput) {
            quantityInput.value = product.miktar;
        }

        // Satış fiyatı input
        const priceInput = row.querySelector('td:nth-child(5) input');
        if (priceInput) {
            priceInput.value = parseFloat(product.satis_fiyati).toFixed(2);
        }

        // Kar oranı input
        const profitInput = row.querySelector('td:nth-child(6) input');
        if (profitInput) {
            profitInput.value = parseFloat(product.kar_orani).toFixed(2);
        }

        // Toplam tutar
        const totalCell = row.querySelector('td:last-child');
        if (totalCell) {
            const total = (product.miktar || 0) * (product.birim_fiyat || 0);
            totalCell.textContent = `₺${total.toFixed(2)}`;
        }
    } catch (error) {
        console.error('Satır güncellenirken hata:', error);
    }
	        hesaplaToplamlar();

}

function hesaplaToplamlar() {
    let toplamAlisTutari = 0;
    let toplamSatisTutari = 0;
    let secilenUrunSayisi = 0;

    window.transferProducts.forEach(product => {
        if (product.selected) {
            const miktar = parseFloat(product.miktar) || 0;
            const alisFiyati = parseFloat(product.birim_fiyat) || 0;
            const satisFiyati = parseFloat(product.satis_fiyati) || 0;
            
            toplamAlisTutari += miktar * alisFiyati;
            toplamSatisTutari += miktar * satisFiyati;
            secilenUrunSayisi++;
        }
    });

    const toplamKar = toplamSatisTutari - toplamAlisTutari;
    const ortalamaKarOrani = secilenUrunSayisi ? 
        ((toplamKar / toplamAlisTutari) * 100) : 0;

    // Toplam değerleri güncelle
    document.getElementById('toplamAlis').textContent = `₺${toplamAlisTutari.toFixed(2)}`;
    document.getElementById('toplamSatis').textContent = `₺${toplamSatisTutari.toFixed(2)}`;
    document.getElementById('toplamKar').textContent = `₺${toplamKar.toFixed(2)}`;
    document.getElementById('toplamKarYuzde').textContent = `%${ortalamaKarOrani.toFixed(2)}`;
}

// Event listener'ları güncelle
function initializePriceListeners() {
    document.addEventListener('input', function(e) {
        const input = e.target;
        if (!input.dataset.field) return;

        const row = input.closest('tr');
        if (!row) return;

        const index = parseInt(row.dataset.index);
        const product = window.transferProducts[index];
        if (!product) return;

        const value = parseFloat(input.value) || 0;

        switch(input.dataset.field) {
            case 'miktar':
                product.miktar = value;
                break;
            case 'birim_fiyat':
                product.birim_fiyat = value;
                // Kar oranını koruyarak satış fiyatını güncelle
                product.satis_fiyati = value * (1 + (product.kar_orani / 100));
                row.querySelector('[data-field="satis_fiyati"]').value = 
                    product.satis_fiyati.toFixed(2);
                break;
            case 'satis_fiyati':
                product.satis_fiyati = value;
                // Satış fiyatına göre kar oranını güncelle
                const karOrani = ((value - product.birim_fiyat) / product.birim_fiyat * 100);
                product.kar_orani = karOrani;
                row.querySelector('[data-field="kar_orani"]').value = karOrani.toFixed(2);
                break;
            case 'kar_orani':
                product.kar_orani = value;
                // Kar oranına göre satış fiyatını güncelle
                const satisFiyati = product.birim_fiyat * (1 + (value / 100));
                product.satis_fiyati = satisFiyati;
                row.querySelector('[data-field="satis_fiyati"]').value = 
                    satisFiyati.toFixed(2);
                break;
        }

        // Satır toplamını güncelle
        const totalCell = row.querySelector('.product-total');
        if (totalCell) {
            totalCell.textContent = `₺${(product.miktar * product.birim_fiyat).toFixed(2)}`;
        }

        hesaplaToplamlar();
    });
}

function editInvoice(id, event) {
    if (event) {
        event.stopPropagation();
    }

    Swal.fire({
        title: 'Fatura Düzenle',
        html: `
            <form id="editInvoiceForm" class="text-left">
                <input type="hidden" name="fatura_id" value="${id}">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Fatura Tipi*</label>
                        <select name="fatura_tipi" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                            <option value="satis">Satış</option>
                            <option value="iade">İade</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Fatura Seri*</label>
                        <input type="text" name="fatura_seri" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 focus:ring-opacity-50" required>
                    </div>
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Fatura No*</label>
                        <input type="text" name="fatura_no" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 focus:ring-opacity-50" required>
                    </div>
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Fatura Tarihi*</label>
                        <input type="date" name="fatura_tarihi" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 focus:ring-opacity-50" required>
                    </div>
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">İrsaliye No</label>
                        <input type="text" name="irsaliye_no" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                    </div>
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">İrsaliye Tarihi</label>
                        <input type="date" name="irsaliye_tarihi" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                    </div>
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Sipariş No</label>
                        <input type="text" name="siparis_no" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                    </div>
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Sipariş Tarihi</label>
                        <input type="date" name="siparis_tarihi" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                    </div>
                    <div class="mb-3 md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tedarikçi*</label>
                        <select name="tedarikci" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 focus:ring-opacity-50" required>
                            <option value="">Tedarikçi Seçin</option>
                            <option value="add_new" class="font-semibold text-blue-600">+ Yeni Tedarikçi Ekle</option>
                            <!-- Tedarikçiler JavaScript ile doldurulacak -->
                        </select>
                    </div>
                    <div class="mb-3 md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Açıklama</label>
                        <textarea name="aciklama" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 focus:ring-opacity-50"></textarea>
                    </div>
                </div>
            </form>
        `,
        showCancelButton: true,
        confirmButtonText: 'Güncelle',
        cancelButtonText: 'İptal',
        buttonsStyling: true,
        customClass: {
            confirmButton: 'bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded',
            cancelButton: 'bg-gray-400 hover:bg-gray-500 text-white font-bold py-2 px-4 rounded ml-2'
        },
        width: '600px',
        didOpen: async () => {
            try {
                // Fatura detaylarını getir
                const faturaResponse = await fetch(`api/get_invoice_details.php?id=${id}`);
                const faturaData = await faturaResponse.json();
                
                if (!faturaData.success) {
                    throw new Error('Fatura bilgileri alınamadı');
                }

                // Form alanlarını doldur
                const form = document.getElementById('editInvoiceForm');
                const fatura = faturaData.fatura;
                
                form.fatura_tipi.value = fatura.fatura_tipi || 'satis';
                form.fatura_seri.value = fatura.fatura_seri || '';
                form.fatura_no.value = fatura.fatura_no || '';
                form.fatura_tarihi.value = fatura.fatura_tarihi || '';
                form.irsaliye_no.value = fatura.irsaliye_no || '';
                form.irsaliye_tarihi.value = fatura.irsaliye_tarihi || '';
                form.siparis_no.value = fatura.siparis_no || '';
                form.siparis_tarihi.value = fatura.siparis_tarihi || '';
                form.aciklama.value = fatura.aciklama || '';

                // Tedarikçileri yükle
                const tedarikcilerResponse = await fetch('api/get_tedarikciler.php');
                const tedarikcilerData = await tedarikcilerResponse.json();
                
                // Tedarikçi seçeneğini doldur
                const tedarikciSelect = form.querySelector('select[name="tedarikci"]');
                if (tedarikciSelect) {
                    let tedarikciHTML = `
                        <option value="">Tedarikçi Seçin</option>
                        <option value="add_new" class="font-semibold text-blue-600">+ Yeni Tedarikçi Ekle</option>
                    `;
                    
                    if (tedarikcilerData.success && tedarikcilerData.tedarikciler) {
                        tedarikcilerData.tedarikciler.forEach(t => {
                            const selected = t.id == fatura.tedarikci ? 'selected' : '';
                            tedarikciHTML += `<option value="${t.id}" ${selected}>${t.ad}</option>`;
                        });
                    }
                    
                    tedarikciSelect.innerHTML = tedarikciHTML;
                }

                // Tedarikçi seçimi değişikliğini dinle
                tedarikciSelect.addEventListener('change', function(e) {
                    if (e.target.value === 'add_new') {
                        e.target.value = '';
                        openAddTedarikciModal();
                    }
                });
            } catch (error) {
                console.error('Veri yükleme hatası:', error);
                Swal.showValidationMessage(`Veri yükleme hatası: ${error.message}`);
            }
        },
        preConfirm: async () => {
            const form = document.getElementById('editInvoiceForm');
            const formData = new FormData(form);
            
            try {
                const response = await fetch('api/update_invoice.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (!result.success) {
                    throw new Error(result.message || 'Güncelleme sırasında bir hata oluştu');
                }
                
                return result;
            } catch (error) {
                Swal.showValidationMessage(error.message);
                return false;
            }
        }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                icon: 'success',
                title: 'Başarılı!',
                text: 'Fatura başarıyla güncellendi',
                showConfirmButton: false,
                timer: 1500
            }).then(() => {
                location.reload();
            });
        }
    });
}

// Window objesine fonksiyonu tanımla
window.editInvoice = editInvoice;


// Fatura Ekleme Modalını Aç
function addInvoice() {
    Swal.fire({
        title: 'Yeni Fatura Ekle',
        html: `
    <form id="addInvoiceForm" class="text-left">
        <div class="grid grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-medium mb-1">Fatura Tipi*</label>
                <select id="fatura_tipi" name="fatura_tipi" class="w-full rounded-md border-gray-300">
                    <option value="satis">Satış</option>
                    <option value="iade">İade</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Fatura Seri*</label>
                <input type="text" id="fatura_seri" name="fatura_seri" class="w-full rounded-md border-gray-300">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Fatura No*</label>
                <input type="text" id="fatura_no" name="fatura_no" class="w-full rounded-md border-gray-300">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Fatura Tarihi*</label>
                <input type="date" id="fatura_tarihi" name="fatura_tarihi" class="w-full rounded-md border-gray-300">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">İrsaliye No</label>
                <input type="text" id="irsaliye_no" name="irsaliye_no" class="w-full rounded-md border-gray-300">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">İrsaliye Tarihi</label>
                <input type="date" id="irsaliye_tarihi" name="irsaliye_tarihi" class="w-full rounded-md border-gray-300">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Sipariş No</label>
                <input type="text" id="siparis_no" name="siparis_no" class="w-full rounded-md border-gray-300">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Sipariş Tarihi</label>
                <input type="date" id="siparis_tarihi" name="siparis_tarihi" class="w-full rounded-md border-gray-300">
            </div>
            <div class="col-span-2">
                <label class="block text-sm font-medium mb-1">Tedarikçi*</label>
                <select id="tedarikci" name="tedarikci" class="w-full rounded-md border-gray-300">
                    <option value="">Seçiniz</option>
                    <option value="add_new" class="font-semibold text-blue-600">+ Yeni Tedarikçi Ekle</option>
                </select>
            </div>
            <div class="col-span-2">
                <label class="block text-sm font-medium mb-1">Açıklama</label>
                <textarea id="aciklama" name="aciklama" rows="3" class="w-full rounded-md border-gray-300"></textarea>
            </div>
        </div>
    </form>
`,
        width: '800px',
        showCancelButton: true,
        confirmButtonText: 'Kaydet',
        cancelButtonText: 'İptal',
        didOpen: () => {
            // Tedarikçileri yükle
            loadTedarikciOptions();

            // Tedarikçi select değişikliğini dinle
            document.getElementById('tedarikci').addEventListener('change', function(e) {
                if (e.target.value === 'add_new') {
                    e.target.value = '';
                    openAddTedarikciModal();
                }
            });
        },
        preConfirm: () => {
            return validateAndCollectFormData();
        }
    }).then((result) => {
        if (result.isConfirmed) {
            saveInvoice(result.value);
        }
    });
}

// Yeni Tedarikçi Ekleme Modalı
function openAddTedarikciModal() {
    Swal.fire({
        title: 'Yeni Tedarikçi Ekle',
        html: `
            <form id="addTedarikciForm" class="text-left">
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">Tedarikçi Adı*</label>
                    <input type="text" id="tedarikci_ad" name="ad" class="w-full rounded-md border-gray-300" required>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">Telefon*</label>
                    <input type="tel" id="tedarikci_telefon" name="telefon" class="w-full rounded-md border-gray-300" required>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">Adres*</label>
                    <textarea id="tedarikci_adres" name="adres" rows="3" class="w-full rounded-md border-gray-300" required></textarea>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">Şehir*</label>
                    <input type="text" id="tedarikci_sehir" name="sehir" class="w-full rounded-md border-gray-300" required>
                </div>
            </form>
        `,
        showCancelButton: true,
        confirmButtonText: 'Kaydet',
        cancelButtonText: 'İptal',
        preConfirm: () => {
            return saveTedarikci();
        }
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            // Tedarikçi listesini güncelle ve yeni eklenen tedarikçiyi seç
            loadTedarikciOptions().then(() => {
                document.getElementById('tedarikci').value = result.value.id;
            });
        }
    });
}

// Form Validasyonu ve Veri Toplama
function validateAndCollectFormData() {
    const requiredFields = ['fatura_seri', 'fatura_no', 'fatura_tarihi', 'tedarikci'];
    const missingFields = [];

    requiredFields.forEach(field => {
        const element = document.getElementById(field);
        if (!element.value.trim()) {
            missingFields.push(element.previousElementSibling.textContent.replace('*', ''));
        }
    });

    if (missingFields.length > 0) {
        throw new Error(`Lütfen zorunlu alanları doldurun: ${missingFields.join(', ')}`);
    }

    const form = document.getElementById('addInvoiceForm');
    const formData = new FormData(form);
    return Object.fromEntries(formData.entries());
}

// Tedarikçi Kaydetme
async function saveTedarikci() {
    const form = document.getElementById('addTedarikciForm');
    const formData = new FormData(form);

    try {
        const response = await fetch('api/add_tedarikci.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();
        if (!data.success) {
            throw new Error(data.message || 'Tedarikçi eklenirken bir hata oluştu');
        }

        return data;

    } catch (error) {
        Swal.showValidationMessage(error.message);
        return false;
    }
}

// Fatura Kaydetme
async function saveInvoice(formData) {
    try {
        const response = await fetch('api/add_invoice.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formData)
        });

        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.message || 'Fatura kaydedilirken bir hata oluştu');
        }

        Swal.fire({
            icon: 'success',
            title: 'Başarılı!',
            text: 'Fatura başarıyla eklendi',
            timer: 1500,
            showConfirmButton: false
        }).then(() => {
            window.location.reload();
        });

    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Hata!',
            text: error.message
        });
    }
}


// KDV özetini güncelleyen fonksiyon
function updateKdvOzet(kdvTutarlari) {
    const kdvOzetElement = document.getElementById('kdvOzet');
    if (!kdvOzetElement) return;

    let html = '';
    Object.entries(kdvTutarlari)
        .filter(([_, tutar]) => tutar > 0)
        .forEach(([oran, tutar]) => {
            html += `
                <div class="mb-2">
                    <div class="text-sm text-gray-600">KDV (%${oran}):</div>
                    <div class="font-bold">₺${tutar.toLocaleString('tr-TR', { minimumFractionDigits: 2 })}</div>
                </div>
            `;
        });
    
    kdvOzetElement.innerHTML = html;
}

// Genel toplamı güncelleyen fonksiyon
function updateGenelToplam(genelToplam) {
    const genelToplamElement = document.getElementById('genelToplam');
    if (genelToplamElement) {
        genelToplamElement.textContent = `₺${genelToplam.toLocaleString('tr-TR', { minimumFractionDigits: 2 })}`;
    }
}

// İskonto toplamını güncelleyen fonksiyon
function updateToplamIskonto(iskontoToplam) {
    const toplamIskontoElement = document.getElementById('toplamIskonto');
    if (toplamIskontoElement) {
        toplamIskontoElement.textContent = `₺${iskontoToplam.toLocaleString('tr-TR', { minimumFractionDigits: 2 })}`;
    }
}

// Ürün Arama İşlevselliği
function initializeProductSearch(faturaId) {
    const searchInput = document.getElementById('barkodSearch'); 
    if (!searchInput) {
        console.error('Arama input elementi bulunamadı');
        return;
    }

    let searchTimeout;

    searchInput.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            const searchTerm = searchInput.value.trim();
            if (searchTerm.length >= 3) {
                searchProducts(searchTerm, faturaId);
            }
        }, 300);
    });

    // Barkod okuyucu için enter tuşunu dinle
    searchInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            const searchTerm = searchInput.value.trim();
            if (searchTerm) {
                searchProducts(searchTerm, faturaId);
            }
        }
    });
}

// Ürün arama fonksiyonunu güncelle
async function searchProducts(term, faturaId) {
    try {
        const response = await fetch(`api/search_product.php?term=${encodeURIComponent(term)}`);
        const data = await response.json();

        if (!data.success || data.products.length === 0) {
            // Ürün bulunamadığında
            Swal.fire({
                title: 'Ürün Bulunamadı',
                text: 'Bu barkod veya isimle eşleşen ürün bulunamadı. Yeni ürün eklemek ister misiniz?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Evet, Yeni Ürün Ekle',
                cancelButtonText: 'Hayır, İptal',
            }).then((result) => {
                if (result.isConfirmed) {
                    // stock_list_process.js'deki addProduct fonksiyonunu kullan
                    import('./stock_list_process.js')
                        .then(module => {
                            module.addProduct({
                                initialBarkod: term,
                                onSave: (newProductData) => {
                                    // Ürün başarıyla eklendiğinde, direkt faturaya ekle
                                    if (newProductData && newProductData.success) {
                                        addToInvoice(newProductData.data);
                                    }
                                }
                            });
                        });
                }
            });
            return;
        }

        // Eğer ürün bulunduysa, direkt olarak faturaya ekle
        if (data.products.length === 1) {
            addToInvoice(data.products[0]);
        } else {
            // Birden fazla ürün bulunduysa liste göster
            displaySearchResults(data.products, faturaId);
        }

    } catch (error) {
        console.error('Arama hatası:', error);
        Swal.fire({
            icon: 'error',
            title: 'Hata!',
            text: 'Arama sırasında bir hata oluştu'
        });
    }
}


async function loadInvoiceProducts(faturaId) {
    try {
        // Fatura ve ürün bilgilerini getir
        const [faturaResponse, urunlerResponse] = await Promise.all([
            fetch(`api/get_invoice_details.php?id=${faturaId}`),
            fetch(`api/get_invoice_products.php?id=${faturaId}`)
        ]);

        const faturaData = await faturaResponse.json();
        const urunlerData = await urunlerResponse.json();

        if (!faturaData.success || !urunlerData.success) {
            throw new Error('Fatura veya ürün bilgileri alınamadı');
        }

        // Ürün tablosunu doldur
        const tbody = document.getElementById('transferProductsBody');
        if (!tbody) {
            throw new Error('Ürün tablosu elementi bulunamadı');
        }

        tbody.innerHTML = urunlerData.products.map((product, index) => {
            const aktarilanMiktar = parseFloat(product.aktarilan_miktar) || 0;
            const kalanMiktar = product.miktar - aktarilanMiktar;
            const birimFiyat = parseFloat(product.birim_fiyat);
            const varsayilanSatisFiyati = birimFiyat * 1.2;

            return `
                <tr data-product-id="${product.id}">
                    <td class="px-4 py-2">
                        <input type="checkbox" 
                               name="selected_products[]" 
                               value="${product.id}" 
                               ${kalanMiktar > 0 ? 'checked' : 'disabled'}
                               class="form-checkbox h-4 w-4">
                    </td>
                    <td class="px-4 py-2">
                        ${product.ad}<br>
                        <span class="text-sm text-gray-500">${product.barkod}</span>
                        ${kalanMiktar < product.miktar ? `
                            <div class="text-xs text-gray-500 mt-1">
                                Aktarılan: ${aktarilanMiktar} / Toplam: ${product.miktar}
                            </div>
                        ` : ''}
                    </td>
                    <td class="px-4 py-2">
                        <input type="number" 
                               name="transfer_miktar_${product.id}"
                               class="w-20 text-right border rounded px-2 py-1"
                               value="${kalanMiktar}"
                               min="0.01"
                               max="${kalanMiktar}"
                               step="0.01"
                               ${kalanMiktar <= 0 ? 'disabled' : ''}>
                    </td>
                    <td class="px-4 py-2 text-right">
                        ₺${birimFiyat.toFixed(2)}
                    </td>
                    <td class="px-4 py-2">
                        <input type="number" 
                               name="satis_fiyati_${product.id}"
                               class="w-24 text-right border rounded px-2 py-1"
                               value="${varsayilanSatisFiyati.toFixed(2)}"
                               min="${birimFiyat}"
                               step="0.01"
                               onchange="calculateProfit(this, ${birimFiyat})">
                    </td>
                    <td class="px-4 py-2">
                        <input type="number" 
                               name="kar_${product.id}"
                               class="w-16 text-right border rounded px-2 py-1"
                               value="20"
                               min="0"
                               step="0.01"
                               onchange="calculateSalePrice(this, ${birimFiyat})">
                    </td>
                </tr>
            `;
        }).join('');

        return {
            fatura: faturaData.fatura,
            products: urunlerData.products
        };

    } catch (error) {
        console.error('Ürün bilgileri yüklenirken hata:', error);
        throw error;
    }
}

// Mağazaları yükleyen fonksiyon
async function loadMagazalar() {
    try {
        const response = await fetch('api/get_magazalar.php');
        const data = await response.json();
        
        if (!data.success) {
            throw new Error('Mağazalar yüklenemedi');
        }

        const magazaSelect = document.querySelector('select[name="magaza_id"]');
        if (!magazaSelect) {
            throw new Error('Mağaza seçim elementi bulunamadı');
        }

        // Mağaza seçeneklerini oluştur
        magazaSelect.innerHTML = `
            <option value="">Mağaza Seçin</option>
            <option value="add_new" class="font-semibold text-blue-600">+ Yeni Mağaza Ekle</option>
            ${data.magazalar.map(magaza => 
                `<option value="${magaza.id}">${magaza.ad}</option>`
            ).join('')}
        `;

        // Yeni mağaza ekleme seçeneği için event listener
        magazaSelect.addEventListener('change', function(e) {
            if (e.target.value === 'add_new') {
                e.target.value = ''; // Select'i sıfırla
                openAddMagazaModal();
            }
        });

    } catch (error) {
        console.error('Mağazalar yüklenirken hata:', error);
        showErrorToast('Mağazalar yüklenirken bir hata oluştu: ' + error.message);
    }
}

// Yeni mağaza ekleme modalını açan fonksiyon
function openAddMagazaModal() {
    Swal.fire({
        title: 'Yeni Mağaza Ekle',
        html: `
            <form id="addMagazaForm" class="text-left">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Mağaza Adı*</label>
                    <input type="text" name="ad" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Adres*</label>
                    <textarea name="adres" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required></textarea>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Telefon*</label>
                    <input type="tel" name="telefon" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
                </div>
            </form>
        `,
        showCancelButton: true,
        confirmButtonText: 'Kaydet',
        cancelButtonText: 'İptal',
        preConfirm: async () => {
            const form = document.getElementById('addMagazaForm');
            const formData = new FormData(form);
            
            try {
                const response = await fetch('api/add_magaza.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (!data.success) {
                    throw new Error(data.message || 'Mağaza eklenirken bir hata oluştu');
                }
                
                return data;
            } catch (error) {
                Swal.showValidationMessage(error.message);
            }
        }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                icon: 'success',
                title: 'Başarılı!',
                text: 'Mağaza başarıyla eklendi',
                showConfirmButton: false,
                timer: 1500
            }).then(() => {
                // Mağaza listesini yeniden yükle
                loadMagazalar().then(() => {
                    // Yeni eklenen mağazayı seç
                    const magazaSelect = document.querySelector('select[name="magaza_id"]');
                    if (magazaSelect && result.value && result.value.magaza_id) {
                        magazaSelect.value = result.value.magaza_id;
                    }
                });
            });
        }
    });
}

function transferToStore(faturaId) {
    try {
        // Modal hazırlama
        Swal.fire({
            title: 'Stok Transfer',
            html: `
                <form id="transferForm" class="space-y-4">
                    <input type="hidden" name="fatura_id" value="${faturaId}">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Hedef Tipi*</label>
                        <select name="hedef_tipi" id="hedefTipi" class="mt-1 block w-full rounded-md border-gray-300" onchange="toggleHedefOptions()" required>
                            <option value="magaza">Mağaza</option>
                            <option value="depo">Depo</option>
                        </select>
                    </div>

                    <div class="mb-4" id="magazaSelectContainer">
                        <label class="block text-sm font-medium text-gray-700">Mağaza Seçin*</label>
                        <select name="hedef_id" id="magazaSelect" class="mt-1 block w-full rounded-md border-gray-300" required>
                            <option value="">Mağaza Seçin</option>
                            <!-- Mağazalar JavaScript ile doldurulacak -->
                        </select>
                    </div>

                    <div class="mb-4 hidden" id="depoSelectContainer">
                        <label class="block text-sm font-medium text-gray-700">Depo Seçin*</label>
                        <select name="hedef_id" id="depoSelect" class="mt-1 block w-full rounded-md border-gray-300" required disabled>
                            <option value="">Depo Seçin</option>
                            <!-- Depolar JavaScript ile doldurulacak -->
                        </select>
                    </div>

                    <div class="mb-4" id="loadingIndicator">
                        <p class="text-center">Ürünler yükleniyor...</p>
                    </div>
                    
                    <div id="productListContainer" class="hidden">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700">Toplu Kar Oranı Uygula (%)</label>
                            <div class="flex mt-1">
                                <input type="number" id="topluKarOrani" class="flex-1 rounded-l-md border-gray-300" value="20" min="0" step="0.1">
                                <button type="button" onclick="uygulaTopluKar()" class="inline-flex items-center px-3 py-2 border border-l-0 border-gray-300 bg-blue-500 text-white rounded-r-md">
                                    Uygula
                                </button>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left">
                                            <input type="checkbox" id="selectAll" checked class="form-checkbox h-4 w-4" onclick="toggleAllProducts(this)">
                                        </th>
                                        <th class="px-4 py-2 text-left">Ürün</th>
                                        <th class="px-4 py-2 text-center">Transfer Miktar</th>
                                        <th class="px-4 py-2 text-center">Alış Fiyatı</th>
                                        <th class="px-4 py-2 text-center">Satış Fiyatı</th>
                                        <th class="px-4 py-2 text-center">Kar Oranı (%)</th>
                                    </tr>
                                </thead>
                                <tbody id="productList">
                                    <!-- Ürünler JavaScript ile doldurulacak -->
                                </tbody>
                            </table>
                        </div>

                        <div class="grid grid-cols-2 gap-4 mt-4 bg-gray-50 p-4 rounded-lg">
                            <div>
                                <p class="text-sm font-medium text-gray-700">Toplam Alış Tutarı:</p>
                                <p id="toplamAlis" class="text-lg font-bold">₺0.00</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-700">Toplam Satış Tutarı:</p>
                                <p id="toplamSatis" class="text-lg font-bold">₺0.00</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-700">Toplam Kar:</p>
                                <p id="toplamKar" class="text-lg font-bold text-green-600">₺0.00</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-700">Ortalama Kar Oranı:</p>
                                <p id="toplamKarYuzde" class="text-lg font-bold text-green-600">%0.00</p>
                            </div>
                        </div>
                    </div>
                </form>
            `,
            width: '80%',
            showCancelButton: true,
            confirmButtonText: 'Aktarımı Tamamla',
            cancelButtonText: 'İptal',
            didOpen: async () => {
                try {
                    // Sayfa yüklendiğinde hedef tipi değişince çalışacak fonksiyonu tanımla
                    window.toggleHedefOptions = function() {
                        const hedefTipi = document.getElementById('hedefTipi').value;
                        const magazaContainer = document.getElementById('magazaSelectContainer');
                        const depoContainer = document.getElementById('depoSelectContainer');
                        const magazaSelect = document.getElementById('magazaSelect');
                        const depoSelect = document.getElementById('depoSelect');
                        
                        if (hedefTipi === 'magaza') {
                            magazaContainer.classList.remove('hidden');
                            depoContainer.classList.add('hidden');
                            magazaSelect.disabled = false;
                            depoSelect.disabled = true;
                        } else {
                            magazaContainer.classList.add('hidden');
                            depoContainer.classList.remove('hidden');
                            magazaSelect.disabled = true;
                            depoSelect.disabled = false;
                        }
                    };

                    // Mağazaları yükle
                    const magazaResponse = await fetch('api/get_magazalar.php');
                    const magazaData = await magazaResponse.json();
                    
                    if (!magazaData.success) {
                        throw new Error('Mağaza listesi alınamadı');
                    }

                    // Depoları yükle
                    const depoResponse = await fetch('api/get_depolar.php');
                    const depoData = await depoResponse.json();
                    
                    if (!depoData.success) {
                        throw new Error('Depo listesi alınamadı');
                    }

                    // Mağaza seçeneklerini doldur
                    const magazaSelect = document.getElementById('magazaSelect');
                    magazaData.magazalar.forEach(magaza => {
                        const option = document.createElement('option');
                        option.value = magaza.id;
                        option.textContent = magaza.ad;
                        magazaSelect.appendChild(option);
                    });

                    // Depo seçeneklerini doldur
                    const depoSelect = document.getElementById('depoSelect');
                    depoData.depolar.forEach(depo => {
                        const option = document.createElement('option');
                        option.value = depo.id;
                        option.textContent = depo.ad;
                        depoSelect.appendChild(option);
                    });

                    // Ürünleri yükle
                    const productsResponse = await fetch(`api/get_invoice_products.php?id=${faturaId}`);
                    const productsData = await productsResponse.json();
                    
                    if (!productsData.success) {
                        throw new Error('Ürün listesi alınamadı');
                    }

                    // Loading indicator'ı gizle, ürün listesini göster
                    document.getElementById('loadingIndicator').classList.add('hidden');
                    document.getElementById('productListContainer').classList.remove('hidden');

                    // Ürün listesini hazırla
                    const productList = document.getElementById('productList');
                    productList.innerHTML = '';
                    
                    if (productsData.products.length === 0) {
                        productList.innerHTML = '<tr><td colspan="6" class="text-center py-4">Bu faturada ürün bulunamadı.</td></tr>';
                        return;
                    }

                    // Her bir ürün için satır oluştur
                    window.transferProducts = productsData.products.map(product => {
                        const kalanMiktar = parseFloat(product.miktar) - parseFloat(product.aktarilan_miktar || 0);
                        const birimFiyat = parseFloat(product.birim_fiyat || 0);
                        const satisFiyati = birimFiyat * 1.2;  // Varsayılan olarak %20 kar
                        const karOrani = 20;  // Varsayılan kar oranı

                        // Ürünü transferProducts array'ine ekleyip, referansı döndür
                        return {
                            ...product,
                            urun_id: product.urun_id,
                            selected: true,  // Varsayılan olarak seçili
                            birim_fiyat: birimFiyat,
                            kalan_miktar: kalanMiktar,
                            transfer_miktar: kalanMiktar,
                            satis_fiyati: satisFiyati,
                            kar_orani: karOrani
                        };
                    });

                    // Ürünleri tabloya ekle
                    window.transferProducts.forEach((product, index) => {
                        if (product.kalan_miktar <= 0) return; // Aktarılacak miktar kalmadıysa listeden çıkar
                        
                        const row = document.createElement('tr');
                        row.setAttribute('data-index', index);
                        
                        row.innerHTML = `
                            <td class="px-4 py-2">
                                <input type="checkbox" 
                                       checked 
                                       onclick="updateProductSelection(${index}, this.checked)" 
                                       class="product-select form-checkbox h-4 w-4">
									   <td class="px-4 py-2">
                                ${product.ad}
                                <div class="text-xs text-gray-500">Barkod: ${product.barkod}</div>
                                <div class="text-xs text-gray-500">
                                    Kalan Miktar: ${product.kalan_miktar} / Toplam: ${product.miktar}
                                </div>
                            </td>
                            <td class="px-4 py-2">
                                <input type="number" 
                                       data-field="transfer_miktar"
                                       class="w-24 text-center border rounded px-2 py-1"
                                       value="${product.kalan_miktar}"
                                       min="0.01"
                                       max="${product.kalan_miktar}"
                                       step="0.01"
                                       oninput="updateProductQuantity(${index}, this.value)">
                            </td>
                            <td class="px-4 py-2 text-right">
                                ₺${product.birim_fiyat.toFixed(2)}
                            </td>
                            <td class="px-4 py-2">
                                <input type="number" 
                                       data-field="satis_fiyati"
                                       class="w-24 text-center border rounded px-2 py-1"
                                       value="${product.satis_fiyati.toFixed(2)}"
                                       min="${product.birim_fiyat}"
                                       step="0.01"
                                       oninput="updateProductPrice(${index}, this.value)">
                            </td>
                            <td class="px-4 py-2">
                                <input type="number" 
                                       data-field="kar_orani"
                                       class="w-16 text-center border rounded px-2 py-1"
                                       value="${product.kar_orani.toFixed(2)}"
                                       min="0"
                                       step="0.1"
                                       oninput="updateProductProfit(${index}, this.value)">
                            </td>
                        `;
                        
                        productList.appendChild(row);
                    });

                    // Toplu kar oranı uygulama fonksiyonu
                    window.uygulaTopluKar = function() {
                        const karOrani = parseFloat(document.getElementById('topluKarOrani').value);
                        if (isNaN(karOrani)) return;
                    
                        window.transferProducts.forEach((product, index) => {
                            // Alış fiyatı ve kar oranından satış fiyatını hesapla
                            const alisFiyati = parseFloat(product.birim_fiyat);
                            const satisFiyati = alisFiyati * (1 + (karOrani / 100));
                            
                            product.kar_orani = karOrani;
                            product.satis_fiyati = satisFiyati;
                    
                            // Input alanlarını güncelle
                            const row = document.querySelector(`tr[data-index="${index}"]`);
                            if (row) {
                                const satisFiyatiInput = row.querySelector('input[data-field="satis_fiyati"]');
                                const karOraniInput = row.querySelector('input[data-field="kar_orani"]');
                                
                                if (satisFiyatiInput) satisFiyatiInput.value = satisFiyati.toFixed(2);
                                if (karOraniInput) karOraniInput.value = karOrani.toFixed(2);
                            }
                        });
                    
                        hesaplaToplamlar();
                    };

                    // Ürün seçimi güncelleme fonksiyonu
                    window.updateProductSelection = function(index, selected) {
                        if (window.transferProducts && window.transferProducts[index]) {
                            window.transferProducts[index].selected = selected;
                            hesaplaToplamlar();
                        }
                    };

                    // Ürün miktarı güncelleme fonksiyonu
                    window.updateProductQuantity = function(index, quantity) {
                        if (window.transferProducts && window.transferProducts[index]) {
                            const validQuantity = parseFloat(quantity) || 0;
                            window.transferProducts[index].transfer_miktar = validQuantity;
                            hesaplaToplamlar();
                        }
                    };

                    // Ürün fiyatı güncelleme fonksiyonu
                    window.updateProductPrice = function(index, price) {
                        if (window.transferProducts && window.transferProducts[index]) {
                            const validPrice = parseFloat(price) || 0;
                            const product = window.transferProducts[index];
                            
                            product.satis_fiyati = validPrice;
                            // Kar oranını güncelle
                            const karOrani = ((validPrice - product.birim_fiyat) / product.birim_fiyat * 100);
                            product.kar_orani = karOrani;
                            
                            // Input alanını güncelle
                            const row = document.querySelector(`tr[data-index="${index}"]`);
                            if (row) {
                                const karOraniInput = row.querySelector('input[data-field="kar_orani"]');
                                if (karOraniInput) karOraniInput.value = karOrani.toFixed(2);
                            }
                            
                            hesaplaToplamlar();
                        }
                    };

                    // Kar oranı güncelleme fonksiyonu
                    window.updateProductProfit = function(index, profit) {
                        if (window.transferProducts && window.transferProducts[index]) {
                            const validProfit = parseFloat(profit) || 0;
                            const product = window.transferProducts[index];
                            
                            product.kar_orani = validProfit;
                            // Satış fiyatını güncelle
                            const satisFiyati = product.birim_fiyat * (1 + validProfit / 100);
                            product.satis_fiyati = satisFiyati;
                            
                            // Input alanını güncelle
                            const row = document.querySelector(`tr[data-index="${index}"]`);
                            if (row) {
                                const satisFiyatiInput = row.querySelector('input[data-field="satis_fiyati"]');
                                if (satisFiyatiInput) satisFiyatiInput.value = satisFiyati.toFixed(2);
                            }
                            
                            hesaplaToplamlar();
                        }
                    };

                    // Tüm ürünleri toplu seç/kaldır
                    window.toggleAllProducts = function(checkbox) {
                        const checkboxes = document.querySelectorAll('.product-select');
                        checkboxes.forEach((cb, index) => {
                            cb.checked = checkbox.checked;
                            updateProductSelection(index, checkbox.checked);
                        });
                    };

                    // Toplamları hesaplama fonksiyonu
                    window.hesaplaToplamlar = function() {
                        let toplamAlisTutari = 0;
                        let toplamSatisTutari = 0;
                        let secilenUrunSayisi = 0;
                        
                        window.transferProducts.forEach(product => {
                            if (product.selected) {
                                const miktar = parseFloat(product.transfer_miktar) || 0;
                                const alisFiyati = parseFloat(product.birim_fiyat) || 0;
                                const satisFiyati = parseFloat(product.satis_fiyati) || 0;
                                
                                toplamAlisTutari += miktar * alisFiyati;
                                toplamSatisTutari += miktar * satisFiyati;
                                secilenUrunSayisi++;
                            }
                        });
                        
                        const toplamKar = toplamSatisTutari - toplamAlisTutari;
                        const ortalamaKarOrani = toplamAlisTutari > 0 ? 
                            ((toplamKar / toplamAlisTutari) * 100) : 0;
                        
                        // Toplam değerleri güncelle
                        document.getElementById('toplamAlis').textContent = `₺${toplamAlisTutari.toFixed(2)}`;
                        document.getElementById('toplamSatis').textContent = `₺${toplamSatisTutari.toFixed(2)}`;
                        document.getElementById('toplamKar').textContent = `₺${toplamKar.toFixed(2)}`;
                        document.getElementById('toplamKarYuzde').textContent = `%${ortalamaKarOrani.toFixed(2)}`;
                    };
                    
                    // İlk yükleme sonrası toplamları hesapla
                    hesaplaToplamlar();

                } catch (error) {
                    console.error('Modal yükleme hatası:', error);
                    Swal.showValidationMessage(`Hata: ${error.message}`);
                }
            },
            preConfirm: async () => {
                try {
                    const hedefTipi = document.getElementById('hedefTipi').value;
                    let hedefId;
                    
                    if (hedefTipi === 'magaza') {
                        hedefId = document.getElementById('magazaSelect').value;
                    } else {
                        hedefId = document.getElementById('depoSelect').value;
                    }
                    
                    if (!hedefId) {
                        throw new Error(`Lütfen ${hedefTipi === 'magaza' ? 'mağaza' : 'depo'} seçin`);
                    }
                    
                    // Seçili ürünleri kontrol et
                    const selectedProducts = window.transferProducts.filter(p => p.selected);
                    
                    if (selectedProducts.length === 0) {
                        throw new Error('Lütfen en az bir ürün seçin');
                    }
                    
                    // Miktar kontrolü
                    for (const product of selectedProducts) {
                        if (!product.transfer_miktar || product.transfer_miktar <= 0) {
                            throw new Error(`${product.ad} için geçerli bir miktar girin`);
                        }
                        
                        if (product.transfer_miktar > product.kalan_miktar) {
                            throw new Error(`${product.ad} için aktarılacak miktar kalan miktardan fazla olamaz`);
                        }
                    }
                    
                    // Request verisi
                    const requestData = {
                        fatura_id: faturaId,
                        hedef_tipi: hedefTipi,
                        hedef_id: hedefId,
                        products: window.transferProducts // Tüm ürünleri gönder, seçili olanlar selected=true olacak
                    };
                    
                    console.log('Request Data:', requestData);
                    
                    // Loading göster
                    Swal.showLoading();
                    
                    // Aktarım işlemini yap
                    const response = await fetch('api/transfer_to_store.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(requestData)
                    });
                    
                    // Yanıtı metin olarak al
                    const responseText = await response.text();
                    console.log('API Yanıtı (Ham):', responseText);
                    
                    // JSON olarak ayrıştır
                    let result;
                    if (responseText.trim()) {
                        try {
                            result = JSON.parse(responseText);
                        } catch (e) {
                            console.error('JSON ayrıştırma hatası:', e);
                            throw new Error('Sunucu yanıtı geçersiz JSON formatında: ' + responseText.substr(0, 100) + '...');
                        }
                    } else {
                        throw new Error('Sunucu boş yanıt döndürdü');
                    }
                    
                    if (!result.success) {
                        throw new Error(result.message || 'Aktarım sırasında bir hata oluştu');
                    }
                    
                    return result;
                } catch (error) {
                    console.error('Transfer hatası:', error);
                    Swal.showValidationMessage(`Hata: ${error.message}`);
                    return false;
                }
            }
        }).then((result) => {
            if (result.isConfirmed && result.value && result.value.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Başarılı!',
                    text: result.value.message || 'Ürünler başarıyla aktarıldı',
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    // Sayfayı yenile
                    location.reload();
                });
            }
        });
    } catch (error) {
        console.error('Transfer hatası:', error);
        Swal.fire({
            icon: 'error',
            title: 'Hata!',
            text: error.message || 'Aktarım sırasında bir hata oluştu'
        });
    }
}

async function showInvoiceDetails(faturaId) {
    try {
        // Fatura detaylarını ve ürünleri getir
        const [detailsResponse, productsResponse] = await Promise.all([
            fetch(`api/get_invoice_details.php?id=${faturaId}`),
            fetch(`api/get_invoice_products.php?id=${faturaId}`)
        ]);

        const detailsData = await detailsResponse.json();
        const productsData = await productsResponse.json();

        if (!detailsData.success || !productsData.success) {
            throw new Error('Fatura detayları alınamadı');
        }

        const fatura = detailsData.fatura;
        const products = productsData.products;

        // Fatura durumu için stil ve metin
        const statusStyles = {
            'bos': 'bg-red-100 text-red-800',
            'urun_girildi': 'bg-yellow-100 text-yellow-800',
            'kismi_aktarildi': 'bg-blue-100 text-blue-800',
            'aktarildi': 'bg-green-100 text-green-800'
        };

        const statusTexts = {
            'bos': 'Yeni Fatura',
            'urun_girildi': 'Aktarım Bekliyor',
            'kismi_aktarildi': 'Kısmi Aktarıldı',
            'aktarildi': 'Tamamlandı'
        };

        // Ürün tablosu HTML'i
        const productsHtml = products.length ? `
            <div class="mt-6">
                <h3 class="text-lg font-medium mb-3">Ürünler</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Barkod</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Ürün</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Miktar</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Birim Fiyat</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">KDV</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Toplam</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            ${products.map(product => `
                                <tr>
                                    <td class="px-4 py-2 text-sm">${product.barkod}</td>
                                    <td class="px-4 py-2">
                                        ${product.ad}
                                        ${product.aktarilan_miktar ? `
                                            <div class="text-xs text-gray-500 mt-1">
                                                Aktarılan: ${product.aktarilan_miktar} / ${product.miktar}
                                            </div>
                                        ` : ''}
                                    </td>
                                    <td class="px-4 py-2 text-right">${parseFloat(product.miktar).toFixed(2)}</td>
                                    <td class="px-4 py-2 text-right">₺${parseFloat(product.birim_fiyat).toFixed(2)}</td>
                                    <td class="px-4 py-2 text-right">%${parseFloat(product.kdv_orani).toFixed(0)}</td>
                                    <td class="px-4 py-2 text-right">₺${parseFloat(product.toplam_tutar).toFixed(2)}</td>
                                </tr>
                            `).join('')}
                            <tr class="bg-gray-50 font-medium">
                                <td colspan="5" class="px-4 py-2 text-right">Toplam:</td>
                                <td class="px-4 py-2 text-right">₺${products.reduce((sum, p) => sum + parseFloat(p.toplam_tutar), 0).toFixed(2)}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        ` : '<div class="mt-4 text-center text-gray-500">Bu faturada henüz ürün bulunmuyor.</div>';

        // Detay modalını göster
        Swal.fire({
            title: 'Fatura Detayları',
            html: `
                <div class="text-left">
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <div>
                            <div class="mb-4">
                                <span class="font-medium">Fatura No:</span>
                                <span class="ml-2">${fatura.fatura_seri}${fatura.fatura_no}</span>
                            </div>
                            <div class="mb-4">
                                <span class="font-medium">Tedarikçi:</span>
                                <span class="ml-2">${fatura.tedarikci_adi}</span>
                            </div>
                            <div class="mb-4">
                                <span class="font-medium">Fatura Tarihi:</span>
                                <span class="ml-2">${new Date(fatura.fatura_tarihi).toLocaleDateString('tr-TR')}</span>
                            </div>
                            ${fatura.irsaliye_no ? `
                                <div class="mb-4">
                                    <span class="font-medium">İrsaliye No:</span>
                                    <span class="ml-2">${fatura.irsaliye_no}</span>
                                </div>
                            ` : ''}
                            ${fatura.irsaliye_tarihi ? `
                                <div class="mb-4">
                                    <span class="font-medium">İrsaliye Tarihi:</span>
                                    <span class="ml-2">${new Date(fatura.irsaliye_tarihi).toLocaleDateString('tr-TR')}</span>
                                </div>
                            ` : ''}
                        </div>
                        <div>
                            <div class="mb-4">
                                <span class="font-medium">Durum:</span>
                                <span class="ml-2 px-2 py-1 text-xs rounded-full ${statusStyles[fatura.durum]}">
                                    ${statusTexts[fatura.durum]}
                                </span>
                            </div>
                            <div class="mb-4">
                                <span class="font-medium">Kayıt Tarihi:</span>
                                <span class="ml-2">${new Date(fatura.kayit_tarihi).toLocaleString('tr-TR')}</span>
                            </div>
                            ${fatura.aktarim_tarihi ? `
                                <div class="mb-4">
                                    <span class="font-medium">Son Aktarım:</span>
                                    <span class="ml-2">${new Date(fatura.aktarim_tarihi).toLocaleString('tr-TR')}</span>
                                </div>
                            ` : ''}
                            ${fatura.aciklama ? `
                                <div class="mb-4">
                                    <span class="font-medium">Açıklama:</span>
                                    <div class="mt-1 text-sm text-gray-600">${fatura.aciklama}</div>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                    ${productsHtml}
                </div>
            `,
            width: '900px',
            showCloseButton: true,
            showConfirmButton: false,
            customClass: {
                container: 'invoice-details-modal'
            }
        });

    } catch (error) {
        console.error('Fatura detayları gösterilirken hata:', error);
        Swal.fire({
            icon: 'error',
            title: 'Hata!',
            text: error.message
        });
    }
}

// Kar marjı hesaplama
function initializeKarMarji() {
    const alisFiyati = document.getElementById('alis_fiyati');
    const satisFiyati = document.getElementById('satis_fiyati');
    const karMarjiNote = document.querySelector('.kar-marji-note');

    const hesaplaKarMarji = () => {
        const alis = parseFloat(alisFiyati.value) || 0;
        const satis = parseFloat(satisFiyati.value) || 0;
        
        if (alis > 0 && satis > 0) {
            const marj = ((satis - alis) / alis) * 100;
            const kar = satis - alis;
            karMarjiNote.textContent = `Kar Marjı: %${marj.toFixed(2)} (${kar.toFixed(2)}₺)`;
        } else {
            karMarjiNote.textContent = '';
        }
    };

    alisFiyati.addEventListener('input', hesaplaKarMarji);
    satisFiyati.addEventListener('input', hesaplaKarMarji);
}

// Ana grup değişikliğinde alt grupları güncelle
async function handleAnaGrupChange(event) {
    const anaGrupId = event.target.value;
    const altGrupSelect = document.getElementById('alt_grup');
    
    if (!altGrupSelect) return;

    if (anaGrupId === 'add_new') {
        event.target.value = '';
        return;
    }

    try {
        const response = await fetch(`api/get_alt_gruplar.php?ana_grup_id=${anaGrupId}`);
        const data = await response.json();

        if (!data.success) {
            throw new Error('Alt gruplar alınamadı');
        }

        // Alt grup seçeneklerini güncelle
        altGrupSelect.innerHTML = `
            <option value="">Seçiniz</option>
            <option value="add_new">+ Yeni Ekle</option>
            ${data.data.map(alt => `
                <option value="${alt.id}">${alt.ad}</option>
            `).join('')}
        `;

    } catch (error) {
        console.error('Alt gruplar yüklenirken hata:', error);
        Swal.fire({
            icon: 'error',
            title: 'Hata!',
            text: 'Alt gruplar yüklenirken bir hata oluştu'
        });
    }
}


// Event listener'ları başlat
document.addEventListener('DOMContentLoaded', function() {
    // Ürün arama input'unu bul
    const searchInput = document.getElementById('barkodSearch');
    if (searchInput) {
        // Enter tuşuna basıldığında arama yap
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const searchTerm = this.value.trim();
                if (searchTerm) {
                    searchProduct();
                }
            }
        });

        // Input değiştiğinde arama yap (isteğe bağlı)
        searchInput.addEventListener('input', debounce(function() {
            const searchTerm = this.value.trim();
            if (searchTerm.length >= 3) {
                searchProduct();
            }
        }, 500));
    }

    // Arama butonu varsa ona da event listener ekle
    const searchButton = document.getElementById('searchButton');
    if (searchButton) {
        searchButton.addEventListener('click', function(e) {
            e.preventDefault();
            searchProduct();
        });
    }
});

// Enter tuşu ile arama fonksiyonu
function searchProduct() {
    const searchInput = document.getElementById('barkodSearch');
    const searchTerm = searchInput.value.trim();
    const faturaId = document.getElementById('productFaturaId').value;
    
    if (!searchTerm) {
        Swal.fire({
            icon: 'warning',
            text: 'Lütfen arama terimi girin',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000
        });
        return;
    }

    searchProducts(searchTerm, faturaId);
}

// Debounce fonksiyonu (çok sık arama yapılmasını engeller)
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func.apply(this, args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function createProductRow(product) {
    return `
        <tr>
            <td class="px-4 py-2">${product.kod || '-'}</td>
            <td class="px-4 py-2">${product.barkod}</td>
            <td class="px-4 py-2">${product.ad}</td>
            <td class="px-4 py-2">
                <input type="number" class="miktar w-20 px-2 py-1 border rounded text-center" 
                       value="1" min="0.01" step="0.01" onchange="updateRowTotal(this)">
            </td>
            <td class="px-4 py-2">
                <input type="number" class="birim-fiyat w-24 px-2 py-1 border rounded text-right" 
                       value="${product.alis_fiyati}" min="0.01" step="0.01" onchange="updateRowTotal(this)">
            </td>
            <td class="px-4 py-2">
                <input type="number" class="iskonto1 w-16 px-2 py-1 border rounded text-right" 
                       value="0" min="0" max="100" step="0.01" onchange="updateRowTotal(this)">
            </td>
            <td class="px-4 py-2">
                <input type="number" class="iskonto2 w-16 px-2 py-1 border rounded text-right" 
                       value="0" min="0" max="100" step="0.01" onchange="updateRowTotal(this)">
            </td>
            <td class="px-4 py-2">
                <input type="number" class="iskonto3 w-16 px-2 py-1 border rounded text-right" 
                       value="0" min="0" max="100" step="0.01" onchange="updateRowTotal(this)">
            </td>
            <td class="px-4 py-2">
                <select class="kdv-orani w-16 px-2 py-1 border rounded text-right" onchange="updateRowTotal(this)">
                    <option value="0" ${product.kdv_orani == 0 ? 'selected' : ''}>0</option>
                    <option value="10" ${product.kdv_orani == 10 ? 'selected' : ''}>10</option>
                    <option value="20" ${product.kdv_orani == 20 ? 'selected' : ''}>20</option>
                </select>
            </td>
            <td class="px-4 py-2 text-right toplam-tutar">₺0.00</td>
            <td class="px-4 py-2 text-center">
                <button onclick="removeProduct(this)" class="text-red-600 hover:text-red-800">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                </button>
            </td>
        </tr>
    `;
}

// Ürün silme fonksiyonu
function removeProduct(button) {
    const row = button.closest('tr');
    if (row) {
        const barkod = row.querySelector('td:nth-child(2)').textContent;
        // selectedProducts array'inden ürünü kaldır
        window.selectedProducts = window.selectedProducts.filter(p => p.barkod !== barkod);
        // Satırı DOM'dan kaldır
        row.remove();
        // Genel toplamı güncelle
        updateInvoiceTotal();
    }
}

// Hesap formülü
function updateRowTotal(element) {
    const row = element.closest('tr');
    
    // Değerleri al ve sayısal değerlere çevir
    const miktar = parseFloat(row.querySelector('.miktar').value) || 0;
    const birimFiyat = parseFloat(row.querySelector('.birim-fiyat').value) || 0;
    const iskonto1 = parseFloat(row.querySelector('.iskonto1').value) || 0;
    const iskonto2 = parseFloat(row.querySelector('.iskonto2').value) || 0;
    const iskonto3 = parseFloat(row.querySelector('.iskonto3').value) || 0;
    const kdvOrani = parseFloat(row.querySelector('.kdv-orani').value) || 0;
	
	    // Adet için 1'den küçük değer kontrolü
    if (miktar < 1) {
        row.querySelector('.miktar').value = 1;
        return updateRowTotal(element);
    }

    // İskontolar için 0-100 arası kontrol
    if (iskonto1 < 0 || iskonto1 > 100 || 
        iskonto2 < 0 || iskonto2 > 100 || 
        iskonto3 < 0 || iskonto3 > 100) {
        
        row.querySelector('.iskonto1').value = Math.min(100, Math.max(0, iskonto1));
        row.querySelector('.iskonto2').value = Math.min(100, Math.max(0, iskonto2));
        row.querySelector('.iskonto3').value = Math.min(100, Math.max(0, iskonto3));
        return updateRowTotal(element);
    }

    // Ara toplam hesaplama (miktar * birim fiyat)
    let araToplam = miktar * birimFiyat;

    // İskonto hesaplamaları
    const iskonto1Tutar = araToplam * (iskonto1 / 100);
    araToplam -= iskonto1Tutar;

    const iskonto2Tutar = araToplam * (iskonto2 / 100);
    araToplam -= iskonto2Tutar;

    const iskonto3Tutar = araToplam * (iskonto3 / 100);
    araToplam -= iskonto3Tutar;

    // KDV hesaplama
    const kdvTutar = araToplam * (kdvOrani / 100);
    const toplamTutar = araToplam + kdvTutar;

    // İskonto ve KDV tutarlarını göster
    row.querySelector('.iskonto1-tutar').textContent = `₺${iskonto1Tutar.toFixed(2)}`;
    row.querySelector('.iskonto2-tutar').textContent = `₺${iskonto2Tutar.toFixed(2)}`;
    row.querySelector('.iskonto3-tutar').textContent = `₺${iskonto3Tutar.toFixed(2)}`;
    row.querySelector('.kdv-tutar').textContent = `₺${kdvTutar.toFixed(2)}`;

    // Toplam tutarı göster
    const toplamHucresi = row.querySelector('.toplam-tutar');
    if (toplamHucresi) {
        toplamHucresi.textContent = `₺${toplamTutar.toFixed(2)}`;
    }

    // Ürün verilerini güncelle
    const barkod = row.querySelector('td:nth-child(2)').textContent;
    const productIndex = window.selectedProducts.findIndex(p => p.barkod === barkod);
    if (productIndex !== -1) {
        window.selectedProducts[productIndex] = {
            ...window.selectedProducts[productIndex],
            miktar: miktar,
            birim_fiyat: birimFiyat,
            iskonto1: iskonto1,
            iskonto2: iskonto2,
            iskonto3: iskonto3,
            kdv_orani: kdvOrani,
            toplam: toplamTutar
        };
    }

    // Genel toplamı güncelle
    updateInvoiceTotal();
}

function updateInvoiceTotal() {
    let genelToplam = 0;
    let toplamIskonto = 0;
    let kdvTutarlari = {
        '0': 0,
        '1': 0,
        '8': 0,
        '10': 0,
        '18': 0,
        '20': 0
    };

    window.selectedProducts.forEach(product => {
        const miktar = parseFloat(product.miktar) || 0;
        const birimFiyat = parseFloat(product.birim_fiyat) || 0;
        let araToplam = miktar * birimFiyat;

        // İskonto hesaplamaları
        const iskonto1 = (araToplam * (parseFloat(product.iskonto1) || 0)) / 100;
        araToplam -= iskonto1;
        
        const iskonto2 = (araToplam * (parseFloat(product.iskonto2) || 0)) / 100;
        araToplam -= iskonto2;
        
        const iskonto3 = (araToplam * (parseFloat(product.iskonto3) || 0)) / 100;
        araToplam -= iskonto3;

        toplamIskonto += iskonto1 + iskonto2 + iskonto3;

        // KDV hesaplama
        const kdvOrani = parseFloat(product.kdv_orani) || 0;
        const kdvTutari = (araToplam * kdvOrani) / 100;
        kdvTutarlari[kdvOrani.toString()] += kdvTutari;

        genelToplam += araToplam + kdvTutari;
    });

    // KDV özetini güncelle
    const kdvOzetElement = document.getElementById('kdvOzet');
    if (kdvOzetElement) {
        let kdvHtml = '';
        Object.entries(kdvTutarlari)
            .filter(([_, tutar]) => tutar > 0)
            .forEach(([oran, tutar]) => {
                kdvHtml += `
                    <div class="mb-2">
                        <div class="text-sm text-gray-600">KDV (%${oran}):</div>
                        <div class="font-bold">₺${tutar.toLocaleString('tr-TR', { minimumFractionDigits: 2 })}</div>
                    </div>
                `;
            });
        kdvOzetElement.innerHTML = kdvHtml;
    }

    // Toplam İskontoyu güncelle
    const toplamIskontoElement = document.getElementById('toplamIskonto');
    if (toplamIskontoElement) {
        toplamIskontoElement.textContent = `₺${toplamIskonto.toLocaleString('tr-TR', { minimumFractionDigits: 2 })}`;
    }

    // Genel Toplamı güncelle
    const genelToplamElement = document.getElementById('genelToplam');
    if (genelToplamElement) {
        genelToplamElement.textContent = `₺${genelToplam.toLocaleString('tr-TR', { minimumFractionDigits: 2 })}`;
    }

    return genelToplam;
}