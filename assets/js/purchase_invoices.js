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
function openModal(modalId) {
    document.getElementById(modalId).classList.remove('hidden');
}

// Modal açma fonksiyonu
function addProducts(faturaId) {
    document.getElementById('productFaturaId').value = faturaId;
    window.selectedProducts = []; // Global değişkeni kullan
    updateProductTable();
    openModal('addProductModal');
}

// Modal kapatma fonksiyonu
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('hidden');
    }
}

// Tedarikçi seçeneklerini yükle
async function loadTedarikciOptions() {
    try {
        console.log('Tedarikçiler yükleniyor...');
        const response = await fetch(API_ENDPOINTS.GET_TEDARIKCILER);
        const data = await response.json();
        
        const tedarikciSelect = document.querySelector('select[name="tedarikci"]');
        if (!tedarikciSelect) {
            console.error('Tedarikçi select elementi bulunamadı');
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

        // Doğrudan innerHTML ile güncelle
        tedarikciSelect.innerHTML = options;

        console.log('Tedarikçi listesi güncellendi');

    } catch (error) {
        console.error('Tedarikçiler yüklenirken hata:', error);
        const tedarikciSelect = document.querySelector('select[name="tedarikci"]');
        if (tedarikciSelect) {
            tedarikciSelect.innerHTML = '<option value="">Tedarikçiler yüklenemedi</option>';
        }
    }
}

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

// get_tedarikciler.php API'sini kontrol eden test fonksiyonu
async function testTedarikciAPI() {
    try {
        const response = await fetch(API_ENDPOINTS.GET_TEDARIKCILER);
        const data = await response.json();
        console.log('API Test Sonucu:', data);
        return data;
    } catch (error) {
        console.error('API Test Hatası:', error);
        return null;
    }
}

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

// Fatura ekleme fonksiyonu
async function addInvoice() {
	    // Önce mevcut modalı kapat
    const activeModal = document.querySelector('.modal');
    if (activeModal) {
        activeModal.remove(); // Alternatif: activeModal.style.display = 'none';
    }
    try {
        // Önce tedarikçileri yükle
        const tedarikcilerResponse = await fetch('api/get_tedarikciler.php');
        const tedarikcilerData = await tedarikcilerResponse.json();

        if (!tedarikcilerData.success) {
            throw new Error('Tedarikçiler yüklenemedi');
        }

        // Tedarikçi seçeneklerini oluştur
        const tedarikciOptions = `
            <option value="">Tedarikçi Seçin</option>
            <option value="add_new" class="font-semibold text-blue-600">+ Yeni Tedarikçi Ekle</option>
            ${tedarikcilerData.tedarikciler.map(t => 
                `<option value="${t.id}">${t.ad}</option>`
            ).join('')}
        `;

        // Modal'ı oluştur
        const modalContent = `
            <div id="addInvoiceModal" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                <div class="modal-content bg-white p-6 rounded-lg shadow-lg w-full max-w-xl">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-bold">Yeni Fatura Ekle</h2>
                        <button onclick="closeModal('addInvoiceModal')" class="text-gray-500 hover:text-gray-700">×</button>
                    </div>

                    <form id="addInvoiceForm" onsubmit="handleAddInvoice(event)">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Fatura Seri*</label>
                                <input type="text" name="fatura_seri" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Fatura No*</label>
                                <input type="text" name="fatura_no" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            </div>
                        </div>

                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700">Tedarikçi*</label>
                            <select name="tedarikci" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                ${tedarikciOptions}
                            </select>
                        </div>

                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700">Fatura Tarihi*</label>
                            <input type="date" name="fatura_tarihi" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        </div>

                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700">Açıklama</label>
                            <textarea name="aciklama" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"></textarea>
                        </div>

                        <div class="mt-6 flex justify-end space-x-3">
                            <button type="button" onclick="closeModal('addInvoiceModal')" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700">
                                İptal
                            </button>
                            <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600">
                                Fatura Oluştur
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        `;

        // Modal'ı sayfaya ekle
        document.body.insertAdjacentHTML('beforeend', modalContent);

        // Tedarikçi seçimi değişikliğini dinle
        const tedarikciSelect = document.querySelector('select[name="tedarikci"]');
        if (tedarikciSelect) {
            tedarikciSelect.addEventListener('change', function(e) {
                if (e.target.value === 'add_new') {
                    e.target.value = ''; // Select'i sıfırla
                    openAddTedarikciModal();
                }
            });
        }

    } catch (error) {
        console.error('Modal açılırken hata:', error);
        Swal.fire({
            icon: 'error',
            title: 'Hata!',
            text: 'Fatura ekleme modalı açılırken bir hata oluştu: ' + error.message
        });
    }
}

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


// Ürün arama fonksiyonu
function searchProduct() {
    const searchInput = document.getElementById('barkodSearch');
    const searchTerm = searchInput.value.trim();
    
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

    fetch(`${API_ENDPOINTS.SEARCH_PRODUCT}?term=${encodeURIComponent(searchTerm)}`)
        .then(response => response.json())
        .then(result => {
            if (result.success && result.products.length > 0) {
                displaySearchResults(result.products);
            } else {
                Swal.fire({
                    icon: 'warning',
                    title: 'Ürün Bulunamadı',
                    text: 'Aradığınız kriterlere uygun ürün bulunamadı.'
                });
            }
        })
        .catch(error => {
            console.error('Arama hatası:', error);
            Swal.fire({
                icon: 'error',
                title: 'Hata!',
                text: 'Ürün arama sırasında bir hata oluştu.'
            });
        });
}

// Enter tuşu ile arama yapma
document.getElementById('barkodSearch').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        searchProduct();
    }
});
// Arama sonuçlarını görüntüleme fonksiyonu
function displaySearchResults(products) {
    const searchResults = document.getElementById('searchResults');
    if (!searchResults) return;

    if (!products || products.length === 0) {
        searchResults.innerHTML = '<div class="p-4 text-center text-gray-500">Ürün bulunamadı</div>';
        return;
    }

    let html = `
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Barkod</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Ürün Adı</th>
                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Stok</th>
                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Fiyat</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                ${products.map(product => `
                    <tr class="hover:bg-gray-50 cursor-pointer" onclick="addToInvoice(${JSON.stringify(product).replace(/"/g, '&quot;')})">
                        <td class="px-3 py-2 text-sm">${product.barkod}</td>
                        <td class="px-3 py-2 text-sm">${product.ad}</td>
                        <td class="px-3 py-2 text-sm text-right">${product.stok_miktari}</td>
                        <td class="px-3 py-2 text-sm text-right">₺${parseFloat(product.satis_fiyati).toFixed(2)}</td>
                        <td class="px-3 py-2 text-sm text-right">
                            <button class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-xs">
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

async function saveProducts() {
    const faturaId = document.getElementById('productFaturaId').value;
    
    if (!faturaId) {
        Swal.fire({
            icon: 'error',
            title: 'Hata!',
            text: 'Fatura ID bulunamadı'
        });
        return;
    }

    if (window.selectedProducts.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Uyarı!',
            text: 'Lütfen faturaya ürün ekleyin'
        });
        return;
    }

    try {
        const response = await fetch(API_ENDPOINTS.SAVE_INVOICE_PRODUCTS, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                fatura_id: faturaId,
                products: window.selectedProducts
            })
        });

        const data = await response.json();

        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Başarılı!',
                text: 'Ürünler başarıyla kaydedildi',
                showConfirmButton: false,
                timer: 1500
            }).then(() => {
                closeModal('addProductModal');
                location.reload();
            });
        } else {
            throw new Error(data.message || 'Bir hata oluştu');
        }
    } catch (error) {
        console.error('Kaydetme hatası:', error);
        Swal.fire({
            icon: 'error',
            title: 'Hata!',
            text: error.message
        });
    }
}

// Form submit işleyicisi
document.getElementById('addProductForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    saveProducts();
});

function addToInvoice(product) {
    // Ürün zaten ekli mi kontrol et
    const existingProduct = window.selectedProducts.find(p => p.id === product.id);
    if (existingProduct) {
        existingProduct.miktar += 1;
        existingProduct.toplam = existingProduct.miktar * existingProduct.birim_fiyat;
    } else {
        window.selectedProducts.push({
            id: product.id,
            barkod: product.barkod,
            ad: product.ad,
            miktar: 1,
            birim_fiyat: parseFloat(product.satis_fiyati),
            kdv_orani: parseFloat(product.kdv_orani),
            toplam: parseFloat(product.satis_fiyati)
        });
    }

    updateProductTable();
    
    // Arama kutusunu temizle ve sonuçları gizle
    document.getElementById('barkodSearch').value = '';
    document.getElementById('searchResults').innerHTML = '';
}

function updateProductTable() {
    const tbody = document.getElementById('productTableBody');
    const genelToplamEl = document.getElementById('genelToplam');
    if (!tbody || !genelToplamEl) return;

    tbody.innerHTML = '';
    let genelToplam = 0;

    window.selectedProducts.forEach((product, index) => {
        const row = document.createElement('tr');
        const toplam = product.miktar * product.birim_fiyat;
        genelToplam += toplam;

        row.innerHTML = `
            <td class="px-4 py-2">
                ${product.barkod}<br>
                <span class="text-sm text-gray-500">${product.ad}</span>
            </td>
            <td class="px-4 py-2">
                <input type="number" 
                       value="${product.miktar}" 
                       min="1" 
                       class="w-20 text-center border rounded"
                       onchange="updateQuantity(${index}, this.value)">
            </td>
            <td class="px-4 py-2">
                <input type="number" 
                       value="${product.birim_fiyat}" 
                       step="0.01" 
                       class="w-24 text-right border rounded"
                       onchange="updatePrice(${index}, this.value)">
            </td>
            <td class="px-4 py-2 text-right">₺${toplam.toFixed(2)}</td>
            <td class="px-4 py-2 text-center">
                <button type="button" onclick="removeProduct(${index})" 
                        class="text-red-600 hover:text-red-800">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </button>
            </td>
        `;
        tbody.appendChild(row);
    });

    genelToplamEl.textContent = `₺${genelToplam.toFixed(2)}`;
}

// Miktar güncelleme
function updateQuantity(index, value) {
    const quantity = parseInt(value) || 1;
    if (quantity < 1) return;

    window.selectedProducts[index].miktar = quantity;
    window.selectedProducts[index].toplam = quantity * window.selectedProducts[index].birim_fiyat;
    updateProductTable();
}

// Fiyat güncelleme
function updatePrice(index, value) {
    const price = parseFloat(value) || 0;
    if (price < 0) return;

    window.selectedProducts[index].birim_fiyat = price;
    window.selectedProducts[index].toplam = price * window.selectedProducts[index].miktar;
    updateProductTable();
}

// Ürün silme
function removeProduct(index) {
    window.selectedProducts.splice(index, 1);
    updateProductTable();
}

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
            selectedProducts = result.products;
            updateProductTable();
        }
    } catch (error) {
        console.error('Ürünler yüklenirken hata:', error);
        Swal.fire({
            icon: 'error',
            title: 'Hata!',
            text: 'Ürünler yüklenirken bir hata oluştu.'
        });
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

// Yeni mağaza ekleme modalını aç
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
    }).then(async (result) => {
        if (result.isConfirmed) {
            // Mağaza listesini güncelle
            const magazaSelect = document.querySelector('select[name="magaza_id"]');
            if (magazaSelect) {
                const options = await getMagazaOptions();
                magazaSelect.innerHTML = options;
            }
            
            // Başarı mesajı göster
            Swal.fire({
                icon: 'success',
                title: 'Başarılı!',
                text: 'Mağaza başarıyla eklendi',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000
            });
        }
    });
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

async function transferToStore(faturaId) {
    try {
        // Önce ürünleri ve aktarım detaylarını al
        const response = await fetch(`${API_ENDPOINTS.GET_INVOICE_PRODUCTS}?id=${faturaId}`);
        const result = await response.json();
        
        if (!result.success) {
            throw new Error('Ürün bilgileri alınamadı');
        }

        // Her ürün için kalan miktarları hesapla 
        const products = result.products.map(product => {
            const aktarilanMiktar = parseFloat(product.aktarilan_miktar) || 0;
            const toplamMiktar = parseFloat(product.miktar) || 0;
            const kalanMiktar = toplamMiktar - aktarilanMiktar;
            
            return {
                ...product,
                selected: kalanMiktar > 0,
                kalan_miktar: kalanMiktar,
                aktarilan_miktar: aktarilanMiktar,
                transfer_miktar: kalanMiktar,
                satis_fiyati: parseFloat(product.birim_fiyat) * 1.2,
                kar_orani: 20
            };
        });

        // Aktarılacak ürün kalmadıysa uyarı ver
        if (products.every(p => p.kalan_miktar <= 0)) {
            Swal.fire({
                icon: 'warning',
                title: 'Aktarılacak Ürün Kalmadı',
                text: 'Bu faturadaki tüm ürünler mağazalara aktarılmış.'
            });
            return;
        }

        // Mağazaları al
        const magazaResponse = await fetch(`${API_ENDPOINTS.GET_MAGAZALAR}`);
        const magazaData = await magazaResponse.json();

        if (!magazaData.success) {
            throw new Error('Mağaza listesi alınamadı');
        }

        // Mağaza seçeneklerini oluştur
        const magazaOptions = `
            <option value="">Mağaza Seçin</option>
            <option value="add_new" class="font-semibold text-blue-600">+ Yeni Mağaza Ekle</option>
            ${magazaData.magazalar.map(magaza => 
                `<option value="${magaza.id}">${magaza.ad}</option>`
            ).join('')}
        `;

        window.transferProducts = products; // Global olarak sakla

        Swal.fire({
            title: 'Mağazaya Aktar',
            html: `
                <form id="transferForm" class="space-y-4">
                    <input type="hidden" id="fatura_id" name="fatura_id" value="${faturaId}">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Mağaza Seçin*</label>
                        <select name="magaza_id" id="magaza_id" class="w-full px-3 py-2 border rounded-lg" required>
                            ${magazaOptions}
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Toplu Kar Oranı Uygula</label>
                        <div class="flex space-x-2">
                            <input type="number" id="topluKarOrani" class="w-24 px-3 py-2 border rounded-lg" value="20">
                            <button type="button" onclick="uygulaTopluKar()" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                                Uygula
                            </button>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 w-8">
                                        <input type="checkbox" 
                                               id="selectAll" 
                                               checked
                                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                               onchange="toggleAllProducts(this)">
                                    </th>
                                    <th class="px-4 py-2 text-left">Ürün</th>
                                    <th class="px-4 py-2 text-right">Miktar</th>
                                    <th class="px-4 py-2 text-right">Alış Fiyatı</th>
                                    <th class="px-4 py-2 text-right">Satış Fiyatı</th>
                                    <th class="px-4 py-2 text-right">Kar %</th>
                                    <th class="px-4 py-2 text-right">Toplam</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${products.map((product, index) => `
                                    <tr class="border-t" data-index="${index}">
                                        <td class="px-4 py-2">
                                            <input type="checkbox" 
                                                   name="selected_products[]" 
                                                   value="${index}"
                                                   ${product.kalan_miktar > 0 ? 'checked' : 'disabled'}
                                                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                   onchange="updateProductSelection(${index}, this.checked)">
                                        </td>
                                        <td class="px-4 py-2">
                                            ${product.ad}<br>
                                            <span class="text-sm text-gray-500">${product.barkod}</span>
                                            <div class="text-xs text-gray-500 mt-1">
                                                Aktarılan: ${product.aktarilan_miktar} / Toplam: ${product.miktar}
                                            </div>
                                        </td>
                                        <td class="px-4 py-2">
                                            <input type="number" 
                                                   class="w-20 px-2 py-1 text-right border rounded" 
                                                   value="${product.transfer_miktar}"
                                                   max="${product.kalan_miktar}"
                                                   data-field="transfer_miktar"
                                                   ${product.kalan_miktar <= 0 ? 'disabled' : ''}
                                                   min="0.01"
                                                   step="0.01">
                                        </td>
                                        <td class="px-4 py-2">
                                            <input type="number" 
                                                   class="w-24 px-2 py-1 text-right border rounded" 
                                                   value="${parseFloat(product.birim_fiyat).toFixed(2)}"
                                                   data-field="birim_fiyat"
                                                   readonly
                                                   step="0.01">
                                        </td>
                                        <td class="px-4 py-2">
                                            <input type="number" 
                                                   class="w-24 px-2 py-1 text-right border rounded" 
                                                   value="${product.satis_fiyati.toFixed(2)}"
                                                   data-field="satis_fiyati"
                                                   min="0.01"
                                                   step="0.01">
                                        </td>
                                        <td class="px-4 py-2">
                                            <input type="number" 
                                                   class="w-20 px-2 py-1 text-right border rounded" 
                                                   value="${product.kar_orani}"
                                                   data-field="kar_orani"
                                                   step="0.01">
                                        </td>
                                        <td class="px-4 py-2 text-right product-total">
                                            ₺${(product.transfer_miktar * parseFloat(product.birim_fiyat)).toFixed(2)}
                                        </td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                    
                    <div id="toplamlarBolumu" class="mt-4 bg-gray-50 p-4 rounded-lg">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="text-sm">
                                <div class="font-medium text-gray-700">Toplam Alış Tutarı:</div>
                                <div id="toplamAlis" class="text-lg font-bold">₺0.00</div>
                            </div>
                            <div class="text-sm">
                                <div class="font-medium text-gray-700">Toplam Satış Tutarı:</div>
                                <div id="toplamSatis" class="text-lg font-bold text-blue-600">₺0.00</div>
                            </div>
                            <div class="text-sm">
                                <div class="font-medium text-gray-700">Toplam Kar:</div>
                                <div id="toplamKar" class="text-lg font-bold text-green-600">₺0.00</div>
                            </div>
                            <div class="text-sm">
                                <div class="font-medium text-gray-700">Ortalama Kar Oranı:</div>
                                <div id="toplamKarYuzde" class="text-lg font-bold text-purple-600">%0.00</div>
                            </div>
                        </div>
                        <div class="mt-4 pt-4 border-t">
                            <div class="font-medium text-gray-700 mb-2">Aktarım Durumu:</div>
                            <div class="grid grid-cols-3 gap-4 text-sm">
                                <div class="text-blue-600">
                                    Toplam: ${products.reduce((acc, p) => acc + (parseFloat(p.miktar) || 0), 0).toFixed(2)}
                                </div>
                                <div class="text-green-600">
                                    Aktarılan: ${products.reduce((acc, p) => acc + (parseFloat(p.aktarilan_miktar) || 0), 0).toFixed(2)}
                                </div>
                                <div class="text-orange-600">
                                    Kalan: ${products.reduce((acc, p) => acc + (parseFloat(p.kalan_miktar) || 0), 0).toFixed(2)}
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            `,
            showCancelButton: true,
            confirmButtonText: 'Aktarımı Tamamla',
            cancelButtonText: 'İptal',
            width: '900px',
            didOpen: () => {
                // Event listener'ları başlat
                initializePriceListeners();
                hesaplaToplamlar();

                // Mağaza seçimi değişikliğini dinle
                const magazaSelect = document.querySelector('select[name="magaza_id"]');
                if (magazaSelect) {
                    magazaSelect.addEventListener('change', function(e) {
                        if (e.target.value === 'add_new') {
                            e.target.value = '';
                            openAddMagazaModal();
                        }
                    });
                }

                // Toplu kar oranı input'u için event listener
                const topluKarInput = document.getElementById('topluKarOrani');
                if (topluKarInput) {
                    topluKarInput.addEventListener('keypress', function(e) {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            uygulaTopluKar();
                        }
                    });
                }
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.getElementById('transferForm');
                const formData = new FormData(form);
                console.log('Form Data Values:', {
                    fatura_id: formData.get('fatura_id'),
                    magaza_id: formData.get('magaza_id')
                });
                handleTransfer(formData, faturaId);
            }
        });

    } catch (error) {
        console.error('Transfer işlemi sırasında hata:', error);
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
                <div class="grid grid-cols-1 gap-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Fatura Seri*</label>
                            <input type="text" name="fatura_seri" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Fatura No*</label>
                            <input type="text" name="fatura_no" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Tedarikçi*</label>
                        <select name="tedarikci" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
                            <option value="">Tedarikçi Seçin</option>
                            <option value="add_new" class="font-semibold text-blue-600">+ Yeni Tedarikçi Ekle</option>
                            <!-- Tedarikçiler JavaScript ile doldurulacak -->
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Fatura Tarihi*</label>
                        <input type="date" name="fatura_tarihi" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Açıklama</label>
                        <textarea name="aciklama" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"></textarea>
                    </div>
                </div>
            </form>
        `,
        showCancelButton: true,
        confirmButtonText: 'Güncelle',
        cancelButtonText: 'İptal',
        width: '600px',
        didOpen: async () => {
            try {
                // Tedarikçileri yükle
                const tedarikcilerResponse = await fetch('api/get_tedarikciler.php');
                const tedarikcilerData = await tedarikcilerResponse.json();
                
                // Fatura detaylarını yükle
                const faturaResponse = await fetch(`api/get_invoice_details.php?id=${id}`);
                const faturaData = await faturaResponse.json();
                
                if (!faturaData.success) {
                    throw new Error('Fatura bilgileri alınamadı');
                }

                // Form alanlarını doldur
                const form = document.getElementById('editInvoiceForm');
                form.fatura_seri.value = faturaData.fatura.fatura_seri;
                form.fatura_no.value = faturaData.fatura.fatura_no;
                form.fatura_tarihi.value = faturaData.fatura.fatura_tarihi;
                form.aciklama.value = faturaData.fatura.aciklama || '';

                // Tedarikçi seçeneklerini doldur ve seçili olanı işaretle
                const tedarikciSelect = form.tedarikci;
                tedarikciSelect.innerHTML = `
                    <option value="">Tedarikçi Seçin</option>
                    <option value="add_new" class="font-semibold text-blue-600">+ Yeni Tedarikçi Ekle</option>
                    ${tedarikcilerData.tedarikciler.map(t => 
                        `<option value="${t.id}" ${t.id == faturaData.fatura.tedarikci ? 'selected' : ''}>
                            ${t.ad}
                        </option>`
                    ).join('')}
                `;

                // Tedarikçi seçimi değişikliğini dinle
                tedarikciSelect.addEventListener('change', function(e) {
                    if (e.target.value === 'add_new') {
                        e.target.value = ''; // Select'i sıfırla
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


function addProducts(faturaId) {
    document.getElementById('productFaturaId').value = faturaId;
    loadExistingProducts(faturaId);
    openModal('addProductModal');
}
