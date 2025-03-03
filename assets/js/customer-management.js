// customer-management.js
document.addEventListener('DOMContentLoaded', function() {
    // Yeni Müşteri Ekle butonu
    document.getElementById('addCustomerBtn')?.addEventListener('click', addCustomer);
    
    // Arama input'u için event listener
    document.getElementById('customerSearch')?.addEventListener('input', debounce(searchCustomers, 500));
    
    // Filtrele butonu
    document.getElementById('filterBtn')?.addEventListener('click', filterCustomers);
    
    // Müşteri satırlarına tıklama event listener'ı
    initializeCustomerRowListeners();
});

// Ürün listesinin globalde tutulması
if (typeof window.creditProducts === 'undefined') {
    window.creditProducts = [];
}

// Global ürün listesi
window.creditProducts = [];

// Ürün kaldırma fonksiyonu 
window.removeCreditProduct = function(index) {
    console.log("removeCreditProduct çağrıldı, index:", index);
    window.creditProducts.splice(index, 1);
    renderCreditProductList();
    
    // İndirim tutarı, toplam tutardan büyükse, indirimi toplam tutara eşitle
    const discountInput = document.getElementById('discountAmount');
    const totalAmountInput = document.getElementById('totalAmount');
    
    if (discountInput && totalAmountInput) {
        let subtotal = 0;
        window.creditProducts.forEach(product => {
            subtotal += parseFloat(product.tutar);
        });
        
        const discountAmount = parseFloat(discountInput.value) || 0;
        if (discountAmount > subtotal) {
            discountInput.value = subtotal.toFixed(2);
            totalAmountInput.value = "0.00";
        }
    }
};

// Debounce fonksiyonu - çok sık arama yapılmasını engeller
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Tüm müşterileri seç/kaldır
function toggleAllCustomers(checkbox) {
    document.getElementsByName('selected_customers[]').forEach(item => {
        item.checked = checkbox.checked;
    });
}

// Yeni Müşteri Ekle
function addCustomer() {
    Swal.fire({
        title: 'Yeni Müşteri Ekle',
        html: `
            <form id="addCustomerForm" class="text-left">
                <div class="grid grid-cols-2 gap-4">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Ad*</label>
                        <input type="text" id="ad" name="ad" class="w-full px-3 py-2 border rounded-md" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Soyad*</label>
                        <input type="text" id="soyad" name="soyad" class="w-full px-3 py-2 border rounded-md" required>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Telefon*</label>
                    <input type="tel" id="telefon" name="telefon" class="w-full px-3 py-2 border rounded-md" 
                           placeholder="05xxxxxxxxx" required>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">E-posta</label>
                    <input type="email" id="email" name="email" class="w-full px-3 py-2 border rounded-md">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Adres</label>
                    <textarea id="adres" name="adres" rows="3" class="w-full px-3 py-2 border rounded-md"></textarea>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Barkod / Müşteri Kart No</label>
                    <input type="text" id="barkod" name="barkod" class="w-full px-3 py-2 border rounded-md">
                    <div class="text-xs text-gray-500 mt-1">Boş bırakıldığında otomatik oluşturulur</div>
                </div>
                <div class="mb-4">
                    <label class="flex items-center">
                        <input type="checkbox" id="sms_aktif" name="sms_aktif" class="form-checkbox" checked>
                        <span class="ml-2 text-sm text-gray-700">SMS Bildirimleri Aktif</span>
                    </label>
                </div>
            </form>
        `,
        showCancelButton: true,
        confirmButtonText: 'Kaydet',
        cancelButtonText: 'İptal',
        preConfirm: () => {
            const form = document.getElementById('addCustomerForm');
            if (!form.checkValidity()) {
                form.reportValidity();
                return false;
            }
            
            const formData = new FormData(form);
            return fetch('api/add_customer.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    throw new Error(data.message || 'Müşteri eklenirken bir hata oluştu');
                }
                return data;
            })
            .catch(error => {
                Swal.showValidationMessage(error.message);
                return false;
            });
        }
    }).then(result => {
        if (result.isConfirmed) {
            Swal.fire({
                icon: 'success',
                title: 'Başarılı!',
                text: 'Müşteri başarıyla eklendi',
                timer: 1500
            }).then(() => {
                window.location.reload();
            });
        }
    });
}

// Müşteri Düzenle
function editCustomer(id) {
    fetch(`api/get_customer.php?id=${id}`)
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            throw new Error(data.message || 'Müşteri bilgileri alınamadı');
        }
        
        const customer = data.customer;
        
        Swal.fire({
            title: 'Müşteri Düzenle',
            html: `
                <form id="editCustomerForm" class="text-left">
                    <input type="hidden" name="id" value="${customer.id}">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Ad*</label>
                            <input type="text" id="ad" name="ad" class="w-full px-3 py-2 border rounded-md" 
                                   value="${customer.ad}" required>
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Soyad*</label>
                            <input type="text" id="soyad" name="soyad" class="w-full px-3 py-2 border rounded-md" 
                                   value="${customer.soyad}" required>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Telefon*</label>
                        <input type="tel" id="telefon" name="telefon" class="w-full px-3 py-2 border rounded-md" 
                               value="${customer.telefon}" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">E-posta</label>
                        <input type="email" id="email" name="email" class="w-full px-3 py-2 border rounded-md" 
                               value="${customer.email || ''}">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Adres</label>
                        <textarea id="adres" name="adres" rows="3" class="w-full px-3 py-2 border rounded-md">${customer.adres || ''}</textarea>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Barkod / Müşteri Kart No</label>
                        <input type="text" id="barkod" name="barkod" class="w-full px-3 py-2 border rounded-md" 
                               value="${customer.barkod || ''}">
                    </div>
                    <div class="mb-4">
                        <label class="flex items-center">
                            <input type="checkbox" id="sms_aktif" name="sms_aktif" class="form-checkbox" 
                                   ${customer.sms_aktif == 1 ? 'checked' : ''}>
                            <span class="ml-2 text-sm text-gray-700">SMS Bildirimleri Aktif</span>
                        </label>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Durum</label>
                        <select id="durum" name="durum" class="w-full px-3 py-2 border rounded-md">
                            <option value="aktif" ${customer.durum === 'aktif' ? 'selected' : ''}>Aktif</option>
                            <option value="pasif" ${customer.durum === 'pasif' ? 'selected' : ''}>Pasif</option>
                        </select>
                    </div>
                </form>
            `,
            showCancelButton: true,
            confirmButtonText: 'Güncelle',
            cancelButtonText: 'İptal',
            preConfirm: () => {
                const form = document.getElementById('editCustomerForm');
                if (!form.checkValidity()) {
                    form.reportValidity();
                    return false;
                }
                
                const formData = new FormData(form);
                return fetch('api/update_customer.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        throw new Error(data.message || 'Müşteri güncellenirken bir hata oluştu');
                    }
                    return data;
                })
                .catch(error => {
                    Swal.showValidationMessage(error.message);
                    return false;
                });
            }
        }).then(result => {
            if (result.isConfirmed) {
                Swal.fire({
                    icon: 'success',
                    title: 'Başarılı!',
                    text: 'Müşteri başarıyla güncellendi',
                    timer: 1500
                }).then(() => {
                    window.location.reload();
                });
            }
        });
    })
    .catch(error => {
        Swal.fire({
            icon: 'error',
            title: 'Hata!',
            text: error.message
        });
    });
}

// Müşteri sil
function deleteCustomer(id) {
    Swal.fire({
        title: 'Emin misiniz?',
        text: "Bu müşteriyi silmek istediğinize emin misiniz? Bu işlem geri alınamaz!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Evet, sil!',
        cancelButtonText: 'İptal'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('api/delete_customer.php', {
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
                        text: 'Müşteri başarıyla silindi',
                        timer: 1500
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    throw new Error(data.message || 'Müşteri silinirken bir hata oluştu');
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

// Puan işlemleri görüntüle
function viewCustomerPoints(id) {
    fetch(`api/get_customer_points.php?id=${id}`)
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            throw new Error(data.message || 'Müşteri puan bilgileri alınamadı');
        }
        
        const customer = data.customer;
        const pointsHistory = data.points_history || [];
        
        Swal.fire({
            title: `${customer.ad} ${customer.soyad} - Puan İşlemleri`,
            html: `
                <div class="text-left mb-6">
                    <div class="flex justify-between mb-4">
                        <div>
                            <p class="text-sm text-gray-600">Müşteri No</p>
                            <p class="font-semibold">${customer.barkod || '-'}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Müşteri Türü</p>
                            <p class="font-semibold">${customer.musteri_turu || 'Standart'}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Puan Oranı</p>
                            <p class="font-semibold">%${customer.puan_oran || '1.00'}</p>
                        </div>
                    </div>
                    
                    <div class="bg-green-50 p-4 rounded-lg mb-4">
                        <p class="text-sm text-green-600">Toplam Puan</p>
                        <p class="text-2xl font-bold text-green-800">${parseFloat(customer.puan_bakiye || 0).toFixed(2)} Puan</p>
                    </div>
                    
                    <div class="mt-4">
                        <h3 class="font-semibold mb-2">Manuel Puan İşlemleri</h3>
                        <div class="flex gap-2">
                            <button onclick="addPoints(${id})" class="bg-green-500 text-white px-3 py-1 rounded hover:bg-green-600">
                                <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                Puan Ekle
                            </button>
                            <button onclick="usePoints(${id})" class="bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600">
                                <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                                Puan Kullan
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="mt-6">
                    <h3 class="font-semibold mb-2 text-left">Puan Geçmişi</h3>
                    <div class="max-h-64 overflow-y-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tarih</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">İşlem</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Puan</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Açıklama</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                ${pointsHistory.length > 0 ? 
                                  pointsHistory.map(history => `
                                    <tr>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm">${formatDate(history.tarih)}</td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm">
                                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full ${history.islem_tipi === 'kazanma' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
                                                ${history.islem_tipi === 'kazanma' ? 'Kazanılan' : 'Harcanan'}
                                            </span>
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm text-right font-semibold ${history.islem_tipi === 'kazanma' ? 'text-green-600' : 'text-red-600'}">
                                            ${history.islem_tipi === 'kazanma' ? '+' : '-'}${parseFloat(history.islem_tipi === 'kazanma' ? history.kazanilan_puan : history.harcanan_puan).toFixed(2)}
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm">${history.aciklama || (history.islem_tipi === 'kazanma' ? 'Alışveriş puanı' : 'Puan kullanımı')}</td>
                                    </tr>
                                  `).join('') : 
                                  '<tr><td colspan="4" class="px-4 py-2 text-center text-sm text-gray-500">Puan geçmişi bulunamadı</td></tr>'
                                }
                            </tbody>
                        </table>
                    </div>
                </div>
            `,
            width: 800,
            showConfirmButton: false,
            showCloseButton: true
        });
    })
    .catch(error => {
        Swal.fire({
            icon: 'error',
            title: 'Hata!',
            text: error.message
        });
    });
}

// Puan ekle
function addPoints(customerId) {
    Swal.fire({
        title: 'Puan Ekle',
        html: `
            <form id="addPointsForm" class="text-left">
                <input type="hidden" name="customer_id" value="${customerId}">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Puan Miktarı*</label>
                    <input type="number" id="points" name="points" step="0.01" min="0.01" class="w-full px-3 py-2 border rounded-md" required>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Açıklama</label>
                    <textarea id="description" name="description" rows="2" class="w-full px-3 py-2 border rounded-md"></textarea>
                </div>
            </form>
        `,
        showCancelButton: true,
        confirmButtonText: 'Ekle',
        cancelButtonText: 'İptal',
        preConfirm: () => {
            const form = document.getElementById('addPointsForm');
            if (!form.checkValidity()) {
                form.reportValidity();
                return false;
            }
            
            const formData = new FormData(form);
            return fetch('api/add_customer_points.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    throw new Error(data.message || 'Puan eklenirken bir hata oluştu');
                }
                return data;
            })
            .catch(error => {
                Swal.showValidationMessage(error.message);
                return false;
            });
        }
    }).then(result => {
        if (result.isConfirmed) {
            Swal.fire({
                icon: 'success',
                title: 'Başarılı!',
                text: 'Puanlar başarıyla eklendi',
                timer: 1500
            }).then(() => {
                viewCustomerPoints(customerId);
            });
        }
    });
}

// Puan kullan
function usePoints(customerId) {
    // Önce müşteri bilgilerini al
    fetch(`api/get_customer_points.php?id=${customerId}`)
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            throw new Error(data.message || 'Müşteri puan bilgileri alınamadı');
        }
        
        const customer = data.customer;
        const availablePoints = parseFloat(customer.puan_bakiye || 0).toFixed(2);
        
        Swal.fire({
            title: 'Puan Kullan',
            html: `
                <form id="usePointsForm" class="text-left">
                    <input type="hidden" name="customer_id" value="${customerId}">
                    <div class="mb-2">
                        <p class="text-sm text-gray-600">Mevcut Puan</p>
                        <p class="font-semibold text-green-600">${availablePoints} Puan</p>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Kullanılacak Puan*</label>
                        <input type="number" id="points" name="points" step="0.01" min="0.01" max="${availablePoints}" 
                               class="w-full px-3 py-2 border rounded-md" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Açıklama</label>
                        <textarea id="description" name="description" rows="2" class="w-full px-3 py-2 border rounded-md"></textarea>
                    </div>
                </form>
            `,
            showCancelButton: true,
            confirmButtonText: 'Kullan',
            cancelButtonText: 'İptal',
            preConfirm: () => {
                const form = document.getElementById('usePointsForm');
                if (!form.checkValidity()) {
                    form.reportValidity();
                    return false;
                }
                
                const points = parseFloat(form.elements.points.value);
                if (points > parseFloat(availablePoints)) {
                    Swal.showValidationMessage('Kullanılacak puan miktarı mevcut puandan fazla olamaz');
                    return false;
                }
                
                const formData = new FormData(form);
                return fetch('api/use_customer_points.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        throw new Error(data.message || 'Puan kullanılırken bir hata oluştu');
                    }
                    return data;
                })
                .catch(error => {
                    Swal.showValidationMessage(error.message);
                    return false;
                });
            }
        }).then(result => {
            if (result.isConfirmed) {
                Swal.fire({
                    icon: 'success',
                    title: 'Başarılı!',
                    text: 'Puanlar başarıyla kullanıldı',
                    timer: 1500
                }).then(() => {
                    viewCustomerPoints(customerId);
                });
            }
        });
    })
    .catch(error => {
        Swal.fire({
            icon: 'error',
            title: 'Hata!',
            text: error.message
        });
    });
}

// Müşteri işlem geçmişini görüntüle
// Müşteri işlem geçmişini görüntüle
function showCustomerHistory(id) {
    fetch(`api/get_customer_history.php?id=${id}`)
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            throw new Error(data.message || 'Müşteri işlem geçmişi alınamadı');
        }
        
        const customer = data.customer;
        const orders = data.orders || [];
        
        Swal.fire({
            title: `${customer.ad} ${customer.soyad} - İşlem Geçmişi`,
            html: `
                <div class="text-left mb-4">
                    <p class="text-sm text-gray-600">Müşteri No: ${customer.barkod || '-'}</p>
                    <p class="text-sm text-gray-600">Kayıt Tarihi: ${formatDate(customer.kayit_tarihi)}</p>
                </div>
                
                <div class="mt-4">
                    <h3 class="font-semibold mb-2 text-left">Alışveriş Geçmişi</h3>
                    <div class="max-h-96 overflow-y-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tarih</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Fatura No</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Tutar</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Kazanılan Puan</th>
                                    <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Detay</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                ${orders.length > 0 ? 
                                  orders.map(order => `
                                    <tr>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm">${formatDate(order.fatura_tarihi)}</td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm">${order.fatura_seri}${order.fatura_no}</td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm text-right">₺${parseFloat(order.toplam_tutar).toFixed(2)}</td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm text-right text-green-600">+${parseFloat(order.kazanilan_puan || 0).toFixed(2)}</td>
                                        <td class="px-4 py-2 whitespace-nowrap text-center">
                                            <button onclick="viewInvoiceDetails(${order.id})" class="text-blue-600 hover:text-blue-800">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                          d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                          d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                </svg>
                                            </button>
                                        </td>
                                    </tr>
                                  `).join('') : 
                                  '<tr><td colspan="5" class="px-4 py-2 text-center text-sm text-gray-500">Alışveriş geçmişi bulunamadı</td></tr>'
                                }
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="mt-6">
                    <h3 class="font-semibold mb-2 text-left">Puan Hareketleri</h3>
                    <div class="max-h-64 overflow-y-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tarih</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">İşlem</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Puan</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Açıklama</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                ${data.pointHistory && data.pointHistory.length > 0 ? 
                                  data.pointHistory.map(point => `
                                    <tr>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm">${formatDate(point.tarih)}</td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm">
                                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                ${point.islem_tipi === 'kazanma' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
                                                ${point.islem_tipi === 'kazanma' ? 'Kazanılan' : 'Harcanan'}
                                            </span>
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm text-right 
                                            ${point.islem_tipi === 'kazanma' ? 'text-green-600' : 'text-red-600'} font-semibold">
                                            ${point.islem_tipi === 'kazanma' ? '+' : '-'}${parseFloat(point.islem_tipi === 'kazanma' ? point.kazanilan_puan : point.harcanan_puan).toFixed(2)}
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm">${point.aciklama || ''}</td>
                                    </tr>
                                  `).join('') : 
                                  '<tr><td colspan="4" class="px-4 py-2 text-center text-sm text-gray-500">Puan hareketi bulunamadı</td></tr>'
                                }
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="mt-4 flex justify-between">
                    <div class="bg-green-50 p-3 rounded-lg">
                        <p class="text-sm text-green-700">Toplam Alışveriş</p>
                        <p class="text-xl font-bold text-green-800">₺${data.totalSpent ? parseFloat(data.totalSpent).toFixed(2) : '0.00'}</p>
                    </div>
                    <div class="bg-blue-50 p-3 rounded-lg">
                        <p class="text-sm text-blue-700">Toplam Kazanılan Puan</p>
                        <p class="text-xl font-bold text-blue-800">${data.totalPointsEarned ? parseFloat(data.totalPointsEarned).toFixed(2) : '0.00'}</p>
                    </div>
                    <div class="bg-purple-50 p-3 rounded-lg">
                        <p class="text-sm text-purple-700">Toplam Harcanan Puan</p>
                        <p class="text-xl font-bold text-purple-800">${data.totalPointsSpent ? parseFloat(data.totalPointsSpent).toFixed(2) : '0.00'}</p>
                    </div>
                </div>
            `,
            width: 900,
            showConfirmButton: false,
            showCloseButton: true
        });
    })
    .catch(error => {
        Swal.fire({
            icon: 'error',
            title: 'Hata!',
            text: error.message
        });
    });
}

// Yardımcı fonksiyon: Tarih formatla
function formatDate(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString('tr-TR', { 
        day: '2-digit', 
        month: '2-digit', 
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Yardımcı fonksiyon: Ödeme türü metni
function getPaymentTypeText(type) {
    const types = {
        'nakit': 'Nakit',
        'kredi_karti': 'Kredi Kartı',
        'havale': 'Havale/EFT'
    };
    return types[type] || type || '-';
}

// Para formatı
function formatCurrency(amount) {
    return new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY' }).format(amount);
}

// Tarih formatı
function formatDate(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString('tr-TR');
}

// Borç listesini görüntüle
function viewCustomerCredits(customerId) {
    fetch(`api/get_customer_credits.php?id=${customerId}`)
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            throw new Error(data.message || 'Müşteri borç bilgileri alınamadı');
        }
        
        const customer = data.customer;
        const credits = data.credits || [];
        const summary = data.summary || {};
        
        // Özet bilgileri
        const totalCredit = parseFloat(summary.toplam_borc || 0);
        const totalPaid = parseFloat(summary.toplam_odeme || 0);
        const unpaidCredit = parseFloat(summary.odenmemis_borc || 0);
        
        Swal.fire({
            title: `${customer.ad} ${customer.soyad} - Borç Bilgileri`,
            html: `
                <div class="text-left mb-6">
                    <div class="grid grid-cols-3 gap-4 mb-4">
                        <div class="bg-blue-50 p-3 rounded-lg">
                            <p class="text-sm text-blue-600">Toplam Borç</p>
                            <p class="text-xl font-bold text-blue-800">${formatCurrency(totalCredit)}</p>
                        </div>
                        <div class="bg-green-50 p-3 rounded-lg">
                            <p class="text-sm text-green-600">Toplam Ödeme</p>
                            <p class="text-xl font-bold text-green-800">${formatCurrency(totalPaid)}</p>
                        </div>
                        <div class="bg-red-50 p-3 rounded-lg">
                            <p class="text-sm text-red-600">Kalan Borç</p>
                            <p class="text-xl font-bold text-red-800">${formatCurrency(unpaidCredit)}</p>
                        </div>
                    </div>
                    
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-semibold">Borç Listesi</h3>
                        <button onclick="addNewCredit(${customerId})" class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm">
                            <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            Yeni Borç Ekle
                        </button>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tarih</th>
                                    <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tutar</th>
                                    <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ödenen</th>
                                    <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Durum</th>
                                    <th scope="col" class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">İşlemler</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                ${credits.length > 0 ? 
                                credits.map(credit => {
                                    const paidAmount = parseFloat(credit.odenen_tutar || 0);
                                    const totalAmount = parseFloat(credit.toplam_tutar || 0);
                                    const isPaid = credit.odendi_mi == 1;
                                    const statusClass = isPaid ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800';
                                    const statusText = isPaid ? 'Ödendi' : 'Bekliyor';
                                    
                                    return `
                                    <tr>
                                        <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900">${formatDate(credit.borc_tarihi)}</td>
                                        <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900">${formatCurrency(totalAmount)}</td>
                                        <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900">${formatCurrency(paidAmount)}</td>
                                        <td class="px-3 py-2 whitespace-nowrap text-sm">
                                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full ${statusClass}">${statusText}</span>
                                        </td>
                                        <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900 text-right">
                                            <button onclick="viewCreditDetails(${credit.borc_id})" class="text-blue-600 hover:text-blue-900 mr-2">
                                                <svg class="w-5 h-5 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                </svg>
                                            </button>
                                            ${!isPaid ? `
                                            <button onclick="addPayment(${credit.borc_id})" class="text-green-600 hover:text-green-900">
                                                <svg class="w-5 h-5 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                                                </svg>
                                            </button>` : ''}
                                        </td>
                                    </tr>
                                    `;
                                }).join('') : 
                                '<tr><td colspan="5" class="px-3 py-4 text-center text-sm text-gray-500">Bu müşteriye ait borç kaydı bulunamadı</td></tr>'}
                            </tbody>
                        </table>
                    </div>
                </div>
            `,
            width: 900,
            showConfirmButton: false,
            showCloseButton: true
        });
    })
    .catch(error => {
        Swal.fire({
            icon: 'error',
            title: 'Hata!',
            text: error.message
        });
    });
}

// Borç detaylarını görüntüle
function viewCreditDetails(creditId) {
    fetch(`api/get_credit_details.php?id=${creditId}`)
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            throw new Error(data.message || 'Borç detayları alınamadı');
        }
        
        const credit = data.credit;
        const details = data.details || [];
        const payments = data.payments || [];
        
        // Özet bilgileri
        const totalAmount = parseFloat(credit.toplam_tutar || 0);
        const totalPaid = payments.reduce((sum, payment) => sum + parseFloat(payment.odeme_tutari || 0), 0);
        const remainingAmount = totalAmount - totalPaid;
        const isPaid = credit.odendi_mi == 1;
        
        Swal.fire({
            title: `Borç Detayları - ${formatDate(credit.borc_tarihi)}`,
            html: `
                <div class="text-left mb-6">
                    <div class="mb-4">
                        <p class="text-sm text-gray-600 mb-1">Müşteri</p>
                        <p class="font-semibold">${credit.musteri_adi} ${credit.musteri_soyadi}</p>
                    </div>
                    
                    <div class="grid grid-cols-3 gap-4 mb-4">
                        <div class="bg-blue-50 p-3 rounded-lg">
                            <p class="text-sm text-blue-600">Toplam Tutar</p>
                            <p class="text-xl font-bold text-blue-800">${formatCurrency(totalAmount)}</p>
                        </div>
                        <div class="bg-green-50 p-3 rounded-lg">
                            <p class="text-sm text-green-600">Ödenen Tutar</p>
                            <p class="text-xl font-bold text-green-800">${formatCurrency(totalPaid)}</p>
                        </div>
                        <div class="bg-red-50 p-3 rounded-lg">
                            <p class="text-sm text-red-600">Kalan Tutar</p>
                            <p class="text-xl font-bold text-red-800">${formatCurrency(remainingAmount)}</p>
                        </div>
                    </div>
                    
                    <div class="flex justify-between items-center mb-2">
                        <h3 class="font-semibold">Ürün Listesi</h3>
                    </div>
                    
                    <div class="overflow-x-auto mb-6">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ürün</th>
                                    <th scope="col" class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Miktar</th>
                                    <th scope="col" class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Birim Fiyat</th>
                                    <th scope="col" class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Tutar</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                ${details.length > 0 ? 
                                details.map(detail => {
                                    const amount = parseFloat(detail.tutar || 0);
                                    const quantity = parseInt(detail.miktar || 1);
                                    const unitPrice = quantity > 0 ? amount / quantity : amount;
                                    
                                    return `
                                    <tr>
                                        <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900">${detail.urun_adi}</td>
                                        <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900 text-center">${quantity}</td>
                                        <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900 text-right">${formatCurrency(unitPrice)}</td>
                                        <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900 text-right">${formatCurrency(amount)}</td>
                                    </tr>
                                    `;
                                }).join('') : 
                                '<tr><td colspan="4" class="px-3 py-4 text-center text-sm text-gray-500">Ürün detayı bulunamadı</td></tr>'}
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="flex justify-between items-center mb-2">
                        <h3 class="font-semibold">Ödeme Geçmişi</h3>
                        ${!isPaid ? `
                        <button onclick="addPayment(${creditId})" class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-sm">
                            <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                            Ödeme Ekle
                        </button>` : ''}
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tarih</th>
                                    <th scope="col" class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Tutar</th>
                                    <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Yöntem</th>
                                    <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Açıklama</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                ${payments.length > 0 ? 
                                payments.map(payment => {
                                    const amount = parseFloat(payment.odeme_tutari || 0);
                                    let paymentMethod = 'Nakit';
                                    if (payment.odeme_yontemi === 'kredi_karti') paymentMethod = 'Kredi Kartı';
                                    else if (payment.odeme_yontemi === 'havale') paymentMethod = 'Havale/EFT';
                                    
                                    return `
                                    <tr>
                                        <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900">${formatDate(payment.odeme_tarihi)}</td>
                                        <td class="px-3 py-2 whitespace-nowrap text-sm text-green-600 font-semibold text-right">${formatCurrency(amount)}</td>
                                        <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900">${paymentMethod}</td>
                                        <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900">${payment.aciklama || '-'}</td>
                                    </tr>
                                    `;
                                }).join('') : 
                                '<tr><td colspan="4" class="px-3 py-4 text-center text-sm text-gray-500">Ödeme kaydı bulunamadı</td></tr>'}
                            </tbody>
                        </table>
                    </div>
                </div>
            `,
            width: 900,
            showConfirmButton: false,
            showCloseButton: true
        });
    })
    .catch(error => {
        Swal.fire({
            icon: 'error',
            title: 'Hata!',
            text: error.message
        });
    });
}

// Yeni borç ekleme fonksiyonu
function addNewCredit(customerId) {
    console.log("addNewCredit başlatıldı, customerId:", customerId);
    
    // Ürün listesini sıfırla
    window.creditProducts = [];
    
    // Ana modal'i oluştur
    Swal.fire({
        title: 'Yeni Borç Ekle',
        html: `
            <div>
                <form id="addCreditForm" class="text-left">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Müşteri</label>
                        <input type="text" id="customerName" name="customerName" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" disabled>
                        <input type="hidden" id="customerId" name="customerId" value="${customerId}">
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Borç Tarihi</label>
                            <input type="date" id="creditDate" name="creditDate" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" value="${new Date().toISOString().split('T')[0]}" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Fiş No (Opsiyonel)</label>
                            <input type="text" id="receiptNumber" name="receiptNumber" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                        </div>
                    </div>
                    
                    <div class="mb-4" id="mainForm">
                        <div class="flex justify-between items-center mb-2">
                            <label class="block text-sm font-medium text-gray-700">Ürünler</label>
                            <button type="button" id="showAddProductForm" class="bg-blue-500 hover:bg-blue-600 text-white px-2 py-1 rounded text-xs">
                                <svg class="w-3 h-3 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                Ürün Ekle
                            </button>
                        </div>
                        
                        <div id="productsListContainer" class="border rounded-md p-3 max-h-48 overflow-y-auto">
                            <div id="emptyProductsMessage" class="text-center text-sm text-gray-500">Henüz ürün eklenmedi</div>
                            <div id="productsList"></div>
                        </div>
                    </div>
                    
                    <div id="addProductFormContainer" style="display:none" class="border rounded-md p-4 mb-4 bg-gray-50">
                        <div class="flex justify-between items-center mb-3">
                            <h3 class="text-md font-medium">Ürün Ekle</h3>
                            <button type="button" id="closeAddProductForm" class="text-gray-500 hover:text-gray-700">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                        
                        <div class="mb-3">
                            <label class="block text-sm font-medium text-gray-700">Ürün Adı</label>
                            <input type="text" id="productName" name="productName" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4 mb-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Miktar</label>
                                <input type="number" id="productQuantity" name="productQuantity" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" min="1" value="1">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Birim Fiyat</label>
                                <input type="number" id="productPrice" name="productPrice" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" min="0" step="0.01">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="block text-sm font-medium text-gray-700">Toplam Fiyat</label>
                            <input type="number" id="productTotalPrice" name="productTotalPrice" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm font-semibold" min="0" step="0.01" readonly>
                        </div>
                        
                        <div class="flex justify-end">
                            <button type="button" id="addProductButton" class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded">
                                Ürünü Ekle
                            </button>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">İndirim Tutarı</label>
                            <input type="number" id="discountAmount" name="discountAmount" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" min="0" step="0.01" value="0">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Toplam Tutar</label>
                            <input type="number" id="totalAmount" name="totalAmount" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm font-semibold text-lg" min="0" step="0.01" value="0" readonly>
                        </div>
                    </div>
                </form>
            </div>
        `,
        width: 700,
        showCancelButton: true,
        confirmButtonText: 'Kaydet',
        cancelButtonText: 'İptal',
        didOpen: () => {
            // Aynı didOpen içeriği devam eder
            console.log("Ana modal açıldı, elementleri hazırlıyorum...");
            
            // Müşteri bilgisini getir
            fetch(`api/get_customer.php?id=${customerId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const customerNameInput = document.getElementById('customerName');
                        if (customerNameInput) {
                            customerNameInput.value = `${data.customer.ad} ${data.customer.soyad}`;
                        }
                    }
                })
                .catch(error => {
                    console.error("Müşteri bilgisi alınamadı:", error);
                });
				
			// İndirim tutarı değiştiğinde toplam tutarı güncelle
const discountInput = document.getElementById('discountAmount');
const totalAmountInput = document.getElementById('totalAmount');

if (discountInput && totalAmountInput) {
    discountInput.addEventListener('input', () => {
        updateTotalWithDiscount();
    });
}

// Toplam tutarı ve indirimi hesaplayan fonksiyon
function updateTotalWithDiscount() {
    const discountAmount = parseFloat(discountInput.value) || 0;
    
    // Ürünlerin toplam tutarını hesapla
    let subtotal = 0;
    window.creditProducts.forEach(product => {
        subtotal += parseFloat(product.tutar);
    });
    
    // İndirim miktarı, ürünlerin toplam tutarından büyük olamaz
    if (discountAmount > subtotal) {
        discountInput.value = subtotal.toFixed(2);
        totalAmountInput.value = "0.00";
    } else {
        // Toplam tutardan indirimi çıkar
        totalAmountInput.value = (subtotal - discountAmount).toFixed(2);
    }
}	
            
            // Modal içindeki ürün ekleme formunu göster/gizle işlemleri
            const showAddProductFormBtn = document.getElementById('showAddProductForm');
            const closeAddProductFormBtn = document.getElementById('closeAddProductForm');
            const addProductFormContainer = document.getElementById('addProductFormContainer');
            const mainForm = document.getElementById('mainForm');
            
            if (showAddProductFormBtn && closeAddProductFormBtn && addProductFormContainer && mainForm) {
                showAddProductFormBtn.addEventListener('click', () => {
                    mainForm.style.display = 'none';
                    addProductFormContainer.style.display = 'block';
                });
                
                closeAddProductFormBtn.addEventListener('click', () => {
                    addProductFormContainer.style.display = 'none';
                    mainForm.style.display = 'block';
                });
            }
            
            // Toplam fiyat hesaplama
            const quantityInput = document.getElementById('productQuantity');
            const priceInput = document.getElementById('productPrice');
            const totalPriceInput = document.getElementById('productTotalPrice');
            
            if (quantityInput && priceInput && totalPriceInput) {
                const calculateTotal = () => {
                    const quantity = parseInt(quantityInput.value) || 0;
                    const price = parseFloat(priceInput.value) || 0;
                    totalPriceInput.value = (quantity * price).toFixed(2);
                };
                
                quantityInput.addEventListener('input', calculateTotal);
                priceInput.addEventListener('input', calculateTotal);
                calculateTotal(); // İlk hesaplama
            }
            
            // Ürün ekle butonuna listener ekle
            const addProductButton = document.getElementById('addProductButton');
            if (addProductButton) {
                addProductButton.addEventListener('click', () => {
                    // Ürün form validasyonu yapalım ama required kullanmayalım
                    const productNameInput = document.getElementById('productName');
                    const quantityInput = document.getElementById('productQuantity');
                    const priceInput = document.getElementById('productPrice');
                    const totalPriceInput = document.getElementById('productTotalPrice');
                    
                    const productName = productNameInput?.value;
                    const quantity = parseInt(quantityInput?.value || 0);
                    const unitPrice = parseFloat(priceInput?.value || 0);
                    const totalPrice = parseFloat(totalPriceInput?.value || 0);
                    
                    if (!productName || productName.trim() === '') {
                        Swal.showValidationMessage('Ürün adı girmelisiniz');
                        return;
                    }
                    
                    if (quantity <= 0) {
                        Swal.showValidationMessage('Miktar sıfırdan büyük olmalıdır');
                        return;
                    }
                    
                    if (unitPrice <= 0) {
                        Swal.showValidationMessage('Birim fiyat sıfırdan büyük olmalıdır');
                        return;
                    }
                    
                    // Ürünü ekle
                    window.creditProducts.push({
                        ad: productName,
                        miktar: quantity,
                        birim_fiyat: unitPrice,
                        tutar: totalPrice
                    });
                    
                    // Formu temizle
                    productNameInput.value = '';
                    quantityInput.value = '1';
                    priceInput.value = '';
                    totalPriceInput.value = '';
                    
                    // Ana forma dön
                    addProductFormContainer.style.display = 'none';
                    mainForm.style.display = 'block';
                    
                    // Ürün listesini güncelle
                    renderCreditProductList();
                });
            }
            
            // Ürün listesini göster
            renderCreditProductList();
        },
        preConfirm: () => {
            try {
                // Form doğrulama kontrolünü kaldırıyoruz, sadece ürün eklenmiş mi kontrol edelim
                if (window.creditProducts.length === 0) {
                    Swal.showValidationMessage('En az bir ürün eklemelisiniz');
                    return false;
                }
                
                const creditDate = document.getElementById('creditDate')?.value;
                const totalAmount = parseFloat(document.getElementById('totalAmount')?.value || 0);
                const discountAmount = parseFloat(document.getElementById('discountAmount')?.value || 0);
                const receiptNumber = document.getElementById('receiptNumber')?.value;
                
                if (!creditDate) {
                    Swal.showValidationMessage('Borç tarihi seçmelisiniz');
                    return false;
                }
                
                if (totalAmount <= 0) {
                    Swal.showValidationMessage('Toplam tutar sıfırdan büyük olmalıdır');
                    return false;
                }
                
                const data = {
                    musteri_id: customerId,
                    borc_tarihi: creditDate,
                    toplam_tutar: totalAmount,
                    indirim_tutari: discountAmount,
                    fis_no: receiptNumber || null,
                    urunler: window.creditProducts
                };
                
                return fetch('api/add_credit.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                })
                .then(response => response.json())
                .then(result => {
                    if (!result.success) {
                        throw new Error(result.message || 'Borç eklenirken bir hata oluştu');
                    }
                    return result;
                })
                .catch(error => {
                    Swal.showValidationMessage(error.message);
                    return false;
                });
            } catch (error) {
                console.error("Form doğrulama hatası:", error);
                Swal.showValidationMessage(`Hata: ${error.message}`);
                return false;
            }
        }
    }).then(result => {
        if (result.isConfirmed && result.value) {
            Swal.fire({
                icon: 'success',
                title: 'Başarılı!',
                text: 'Borç kaydı başarıyla eklendi',
                timer: 1500
            }).then(() => {
                viewCustomerCredits(customerId);
            });
        }
    });
}

// Ürün eklemek için ayrı bir modal açan fonksiyon
function openProductModal(customerId) {
    console.log("Ürün ekleme modalı açılıyor");
    
    Swal.fire({
        title: 'Ürün Ekle',
        html: `
            <form id="addProductForm" class="text-left">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Ürün Adı</label>
                    <input type="text" id="productName" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
                </div>
                
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Miktar</label>
                        <input type="number" id="quantity" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" min="1" value="1" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Birim Fiyat</label>
                        <input type="number" id="unitPrice" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" min="0" step="0.01" required>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Toplam Fiyat</label>
                    <input type="number" id="totalPrice" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm font-semibold" min="0" step="0.01" required readonly>
                </div>
            </form>
        `,
        showCancelButton: true,
        confirmButtonText: 'Ekle',
        cancelButtonText: 'İptal',
        didOpen: () => {
            console.log("Ürün modalı açıldı");
            
            // Toplam fiyat hesaplama
            const quantity = document.getElementById('quantity');
            const unitPrice = document.getElementById('unitPrice');
            const totalPrice = document.getElementById('totalPrice');
            
            const calculateTotal = () => {
                const q = parseInt(quantity.value) || 0;
                const p = parseFloat(unitPrice.value) || 0;
                totalPrice.value = (q * p).toFixed(2);
            };
            
            if (quantity && unitPrice && totalPrice) {
                quantity.addEventListener('input', calculateTotal);
                unitPrice.addEventListener('input', calculateTotal);
                calculateTotal(); // İlk hesaplama
            }
        },
        preConfirm: () => {
            const form = document.getElementById('addProductForm');
            if (!form.checkValidity()) {
                form.reportValidity();
                return false;
            }
            
            const productName = document.getElementById('productName').value;
            const quantity = parseInt(document.getElementById('quantity').value);
            const unitPrice = parseFloat(document.getElementById('unitPrice').value);
            const totalPrice = parseFloat(document.getElementById('totalPrice').value);
            
            // Validation
            if (!productName) {
                Swal.showValidationMessage('Ürün adı giriniz');
                return false;
            }
            
            if (quantity <= 0) {
                Swal.showValidationMessage('Miktar sıfırdan büyük olmalıdır');
                return false;
            }
            
            if (unitPrice <= 0) {
                Swal.showValidationMessage('Birim fiyat sıfırdan büyük olmalıdır');
                return false;
            }
            
            // Ürünü listeye ekle
            const product = {
                ad: productName,
                miktar: quantity,
                birim_fiyat: unitPrice,
                tutar: totalPrice
            };
            
            // Global ürün listesine ekle
            window.creditProducts.push(product);
            return true;
        }
    }).then((result) => {
        if (result.isConfirmed) {
            console.log("Ürün eklendi, listeyi güncelliyorum");
            renderProductList();
        }
    });
}

// Ürün listesini görüntüleyen fonksiyon
function renderCreditProductList() {
    console.log("renderCreditProductList çalıştırılıyor, ürün sayısı:", window.creditProducts.length);
    
    const productsList = document.getElementById('productsList');
    const emptyMessage = document.getElementById('emptyProductsMessage');
    const totalAmountInput = document.getElementById('totalAmount');
    const discountInput = document.getElementById('discountAmount');
    
    if (!productsList || !emptyMessage || !totalAmountInput) {
        console.error("Gerekli DOM elementleri bulunamadı:", {
            productsList: !!productsList,
            emptyMessage: !!emptyMessage,
            totalAmountInput: !!totalAmountInput
        });
        return;
    }
    
    // Ürün listesi boşsa mesajı göster
    if (window.creditProducts.length === 0) {
        emptyMessage.style.display = 'block';
        productsList.innerHTML = '';
        totalAmountInput.value = '0.00';
        return;
    }
    
    // Ürün listesi doluysa mesajı gizle ve listeyi oluştur
    emptyMessage.style.display = 'none';
    
    let html = '<div class="space-y-2 mt-3">';
    let subtotal = 0;
    
    window.creditProducts.forEach((product, index) => {
        const productTotal = parseFloat(product.tutar);
        subtotal += productTotal;
        
        html += `
        <div class="flex justify-between items-center border-b pb-2">
            <div>
                <p class="font-medium">${product.ad}</p>
                <p class="text-sm text-gray-600">${product.miktar} x ${formatCurrency(product.birim_fiyat)}</p>
            </div>
            <div class="flex items-center">
                <span class="font-medium mr-3">${formatCurrency(productTotal)}</span>
                <button type="button" onclick="removeCreditProduct(${index})" class="text-red-500 hover:text-red-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                </button>
            </div>
        </div>
        `;
    });
    
    html += '</div>';
    productsList.innerHTML = html;
    
    // İndirim miktarını da hesaba katar
    const discountAmount = parseFloat(discountInput?.value) || 0;
    const totalAmount = Math.max(0, subtotal - discountAmount);
    
    totalAmountInput.value = totalAmount.toFixed(2);
    
    console.log("Ürün listesi güncellendi, ara toplam:", subtotal, "indirim:", discountAmount, "genel toplam:", totalAmount);
}

// Ürün eklemek için ayrı modal fonksiyonu
function addProductToCredit(customerId) {
    console.log("addProductToCredit called with customerId:", customerId);
    
    Swal.fire({
        title: 'Ürün Ekle',
        html: `
            <form id="addProductForm" class="text-left">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Ürün Adı</label>
                    <input type="text" id="productName" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
                </div>
                
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Miktar</label>
                        <input type="number" id="quantity" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" min="1" value="1" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Birim Fiyat</label>
                        <input type="number" id="unitPrice" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" min="0" step="0.01" required>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Toplam Fiyat</label>
                    <input type="number" id="totalPrice" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm font-semibold" min="0" step="0.01" required readonly>
                </div>
            </form>
        `,
        showCancelButton: true,
        confirmButtonText: 'Ekle',
        cancelButtonText: 'İptal',
        didOpen: () => {
            // Daha uzun bir gecikme kullanıyoruz
            setTimeout(() => {
                try {
                    console.log("Product modal opened, checking elements");
                    
                    // Önce DOM elementlerinin var olduğunu kontrol et
                    const quantity = document.getElementById('quantity');
                    const unitPrice = document.getElementById('unitPrice');
                    const totalPrice = document.getElementById('totalPrice');
                    
                    console.log("Elements exist:", !!quantity, !!unitPrice, !!totalPrice);
                    
                    if (quantity && unitPrice && totalPrice) {
                        const calculateTotal = () => {
                            const q = parseInt(quantity.value) || 0;
                            const p = parseFloat(unitPrice.value) || 0;
                            totalPrice.value = (q * p).toFixed(2);
                        };
                        
                        // Event listenerleri ekle
                        quantity.addEventListener('input', calculateTotal);
                        unitPrice.addEventListener('input', calculateTotal);
                        
                        // İlk hesaplama
                        calculateTotal();
                    } else {
                        console.error("Ürün formu elementleri bulunamadı");
                    }
                } catch (error) {
                    console.error("Ürün ekleme modal kurulum hatası:", error);
                }
            }, 200);  // Daha uzun bir gecikme süresi
        },
        preConfirm: () => {
            try {
                const form = document.getElementById('addProductForm');
                if (!form) {
                    throw new Error('Ürün formu bulunamadı');
                }
                
                if (!form.checkValidity()) {
                    form.reportValidity();
                    return false;
                }
                
                const productName = document.getElementById('productName')?.value;
                const quantity = parseInt(document.getElementById('quantity')?.value || 0);
                const unitPrice = parseFloat(document.getElementById('unitPrice')?.value || 0);
                const totalPrice = parseFloat(document.getElementById('totalPrice')?.value || 0);
                
                if (!productName) {
                    Swal.showValidationMessage('Ürün adı girmelisiniz');
                    return false;
                }
                
                if (quantity <= 0) {
                    Swal.showValidationMessage('Miktar sıfırdan büyük olmalıdır');
                    return false;
                }
                
                if (unitPrice <= 0) {
                    Swal.showValidationMessage('Birim fiyat sıfırdan büyük olmalıdır');
                    return false;
                }
                
                const product = {
                    ad: productName,
                    miktar: quantity,
                    birim_fiyat: unitPrice,
                    tutar: totalPrice
                };
                
                // Global selectedProducts dizisine ekle
                if (!window.selectedProducts) {
                    window.selectedProducts = [];
                }
                
                window.selectedProducts.push(product);
                return true;
            } catch (error) {
                console.error("Ürün ekleme hatası:", error);
                Swal.showValidationMessage(`Hata: ${error.message}`);
                return false;
            }
        }
    }).then((result) => {
        if (result.isConfirmed) {
            console.log("Product added successfully, updating list");
            // Gecikme ekleyin
            setTimeout(() => {
                renderProductsList();
                console.log("Product list updated");
            }, 100);
        }
    });
}
    
    // Ürün satırı ekle
    function addProductRow() {
        Swal.fire({
            title: 'Ürün Ekle',
            html: `
                <form id="addProductForm" class="text-left">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Ürün Adı</label>
                        <input type="text" id="productName" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Miktar</label>
                            <input type="number" id="quantity" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" min="1" value="1" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Birim Fiyat</label>
                            <input type="number" id="unitPrice" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" min="0" step="0.01" required>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Toplam Fiyat</label>
                        <input type="number" id="totalPrice" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm font-semibold" min="0" step="0.01" required readonly>
                    </div>
                </form>
            `,
            showCancelButton: true,
            confirmButtonText: 'Ekle',
            cancelButtonText: 'İptal',
            didOpen: () => {
                // Toplam fiyat hesaplama
                const quantity = document.getElementById('quantity');
                const unitPrice = document.getElementById('unitPrice');
                const totalPrice = document.getElementById('totalPrice');
                
                const calculateTotal = () => {
                    const q = parseInt(quantity.value) || 0;
                    const p = parseFloat(unitPrice.value) || 0;
                    totalPrice.value = (q * p).toFixed(2);
                };
                
                quantity.addEventListener('input', calculateTotal);
                unitPrice.addEventListener('input', calculateTotal);
            },
            preConfirm: () => {
                const form = document.getElementById('addProductForm');
                if (!form.checkValidity()) {
                    form.reportValidity();
                    return false;
                }
                
                const product = {
                    ad: document.getElementById('productName').value,
                    miktar: parseInt(document.getElementById('quantity').value),
                    birim_fiyat: parseFloat(document.getElementById('unitPrice').value),
                    tutar: parseFloat(document.getElementById('totalPrice').value)
                };
                
                selectedProducts.push(product);
                updateProductsList();
                return true;
            }
        });
    }
    
// Ürün listesini güncelle
function updateProductsList() {
    try {
        // selectedProducts tanımlı değilse başlat
        if (typeof window.selectedProducts === 'undefined') {
            window.selectedProducts = [];
        }
        
        const container = document.getElementById('productsList');
        if (!container) {
            console.error('Products list container not found!');
            return;
        }
        
        const totalAmount = document.getElementById('totalAmount');
        
        if (window.selectedProducts.length === 0) {
            container.innerHTML = '<div class="text-center text-sm text-gray-500">Henüz ürün eklenmedi</div>';
            if (totalAmount) totalAmount.value = '0.00';
            return;
        }
        
        let html = '<div class="space-y-2">';
        let total = 0;
        
        window.selectedProducts.forEach((product, index) => {
            const productTotal = parseFloat(product.tutar) || 0;
            total += productTotal;
            
            html += `
            <div class="flex justify-between items-center border-b pb-2">
                <div>
                    <p class="font-medium">${product.ad}</p>
                    <p class="text-sm text-gray-600">${product.miktar} x ${formatCurrency(product.birim_fiyat)}</p>
                </div>
                <div class="flex items-center">
                    <span class="font-medium mr-3">${formatCurrency(productTotal)}</span>
                    <button type="button" class="text-red-500 hover:text-red-700 product-remove" data-index="${index}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                    </button>
                </div>
            </div>
            `;
        });
        
        html += '</div>';
        container.innerHTML = html;
        
        if (totalAmount) {
            totalAmount.value = total.toFixed(2);
        }
        
        // Silme düğmelerine olay dinleyicileri ekle
        const removeButtons = container.querySelectorAll('.product-remove');
        removeButtons.forEach(button => {
            button.addEventListener('click', function() {
                const index = parseInt(this.getAttribute('data-index'));
                window.selectedProducts.splice(index, 1);
                updateProductsList();
            });
        });
    } catch (error) {
        console.error("Ürün listesi güncelleme hatası:", error);
    }
}
// Ödeme ekle
function addPayment(creditId) {
    fetch(`api/get_credit_details.php?id=${creditId}`)
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            throw new Error(data.message || 'Borç detayları alınamadı');
        }
        
        const credit = data.credit;
        const payments = data.payments || [];
        
        // Toplam borç ve ödeme hesapla
        const totalAmount = parseFloat(credit.toplam_tutar || 0);
        const totalPaid = payments.reduce((sum, payment) => sum + parseFloat(payment.odeme_tutari || 0), 0);
        const remainingAmount = totalAmount - totalPaid;
        
        if (remainingAmount <= 0) {
            throw new Error('Bu borç zaten tamamen ödenmiş');
        }
        
        Swal.fire({
            title: 'Ödeme Ekle',
            html: `
                <form id="addPaymentForm" class="text-left">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Müşteri</label>
                        <input type="text" id="customerName" value="${credit.musteri_adi} ${credit.musteri_soyadi}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" disabled>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Toplam Borç</label>
                            <input type="text" value="${formatCurrency(totalAmount)}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-gray-500" disabled>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Kalan Borç</label>
                            <input type="text" value="${formatCurrency(remainingAmount)}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm font-semibold text-red-600" disabled>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Ödeme Tarihi</label>
                            <input type="date" id="paymentDate" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" value="${new Date().toISOString().split('T')[0]}" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Ödeme Yöntemi</label>
                            <select id="paymentMethod" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                <option value="nakit">Nakit</option>
                                <option value="kredi_karti">Kredi Kartı</option>
                                <option value="havale">Havale/EFT</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Ödeme Tutarı</label>
                        <input type="number" id="paymentAmount" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm font-semibold text-lg" min="0.01" max="${remainingAmount}" step="0.01" value="${remainingAmount.toFixed(2)}" required>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Açıklama (Opsiyonel)</label>
                        <textarea id="paymentDescription" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" rows="2"></textarea>
                    </div>
                </form>
            `,
            showCancelButton: true,
            confirmButtonText: 'Kaydet',
            cancelButtonText: 'İptal',
            preConfirm: () => {
                const form = document.getElementById('addPaymentForm');
                if (!form.checkValidity()) {
                    form.reportValidity();
                    return false;
                }
                
                const paymentAmount = parseFloat(document.getElementById('paymentAmount').value);
                if (paymentAmount <= 0) {
                    Swal.showValidationMessage('Ödeme tutarı sıfırdan büyük olmalıdır');
                    return false;
                }
                
                if (paymentAmount > remainingAmount) {
                    Swal.showValidationMessage(`Ödeme tutarı kalan borçtan fazla olamaz (${formatCurrency(remainingAmount)})`);
                    return false;
                }
                
                const data = {
                    borc_id: creditId,
                    odeme_tutari: paymentAmount,
                    odeme_tarihi: document.getElementById('paymentDate').value,
                    odeme_yontemi: document.getElementById('paymentMethod').value,
                    aciklama: document.getElementById('paymentDescription').value || null
                };
                
                return fetch('api/add_payment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                })
                .then(response => response.json())
                .then(result => {
                    if (!result.success) {
                        throw new Error(result.message || 'Ödeme eklenirken bir hata oluştu');
                    }
                    return result;
                })
                .catch(error => {
                    Swal.showValidationMessage(error.message);
                    return false;
                });
            }
        }).then(result => {
            if (result.isConfirmed && result.value) {
                Swal.fire({
                    icon: 'success',
                    title: 'Başarılı!',
                    text: 'Ödeme başarıyla kaydedildi',
                    timer: 1500
                }).then(() => {
                    viewCreditDetails(creditId);
                });
            }
        });
    })
    .catch(error => {
        Swal.fire({
            icon: 'error',
            title: 'Hata!',
            text: error.message
        });
    });
}

// Müşteri işlem geçmişini görüntüle
function viewCustomerHistory(id) {
    fetch(`api/get_customer_history.php?id=${id}`)
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            throw new Error(data.message || 'Müşteri işlem geçmişi alınamadı');
        }
        
        const customer = data.customer;
        const orders = data.orders || [];
        
        Swal.fire({
            title: `${customer.ad} ${customer.soyad} - İşlem Geçmişi`,
            html: `
                <div class="text-left mb-4">
                    <p class="text-sm text-gray-600">Müşteri No: ${customer.barkod || '-'}</p>
                    <p class="text-sm text-gray-600">Kayıt Tarihi: ${formatDate(customer.kayit_tarihi)}</p>
                </div>
                
                <div class="mt-4">
                    <h3 class="font-semibold mb-2 text-left">Alışveriş Geçmişi</h3>
                    <div class="max-h-96 overflow-y-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tarih</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Fatura No</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Tutar</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Kazanılan Puan</th>
                                    <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Detay</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                ${orders.length > 0 ? 
                                  orders.map(order => `
                                    <tr>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm">${formatDate(order.fatura_tarihi)}</td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm">${order.fatura_seri}${order.fatura_no}</td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm text-right">₺${parseFloat(order.toplam_tutar).toFixed(2)}</td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm text-right text-green-600">+${parseFloat(order.kazanilan_puan || 0).toFixed(2)}</td>
                                        <td class="px-4 py-2 whitespace-nowrap text-center">
                                            <button onclick="viewInvoiceDetails(${order.id})" class="text-blue-600 hover:text-blue-800">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                          d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                          d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                </svg>
                                            </button>
                                        </td>
                                    </tr>
                                  `).join('') : 
                                  '<tr><td colspan="5" class="px-4 py-2 text-center text-sm text-gray-500">Alışveriş geçmişi bulunamadı</td></tr>'
                                }
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="mt-6">
                    <h3 class="font-semibold mb-2 text-left">Puan Hareketleri</h3>
                    <div class="max-h-64 overflow-y-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tarih</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">İşlem</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Puan</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Açıklama</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                ${data.pointHistory && data.pointHistory.length > 0 ? 
                                  data.pointHistory.map(point => `
                                    <tr>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm">${formatDate(point.tarih)}</td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm">
                                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                ${point.islem_tipi === 'kazanma' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
                                                ${point.islem_tipi === 'kazanma' ? 'Kazanılan' : 'Harcanan'}
                                            </span>
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm text-right 
                                            ${point.islem_tipi === 'kazanma' ? 'text-green-600' : 'text-red-600'} font-semibold">
                                            ${point.islem_tipi === 'kazanma' ? '+' : '-'}${parseFloat(point.islem_tipi === 'kazanma' ? point.kazanilan_puan : point.harcanan_puan).toFixed(2)}
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm">${point.aciklama || ''}</td>
                                    </tr>
                                  `).join('') : 
                                  '<tr><td colspan="4" class="px-4 py-2 text-center text-sm text-gray-500">Puan hareketi bulunamadı</td></tr>'
                                }
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div id="creditHistorySection" class="mt-6">
                    <h3 class="font-semibold mb-2 text-left">Borç Geçmişi</h3>
                    <div class="text-center text-sm text-gray-500 py-4">
                        <svg class="animate-spin h-5 w-5 inline-block mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Borç geçmişi yükleniyor...
                    </div>
                </div>
                
                <div class="mt-4 flex justify-between">
                    <div class="bg-green-50 p-3 rounded-lg">
                        <p class="text-sm text-green-700">Toplam Alışveriş</p>
                        <p class="text-xl font-bold text-green-800">₺${data.totalSpent ? parseFloat(data.totalSpent).toFixed(2) : '0.00'}</p>
                    </div>
                    <div class="bg-blue-50 p-3 rounded-lg">
                        <p class="text-sm text-blue-700">Toplam Kazanılan Puan</p>
                        <p class="text-xl font-bold text-blue-800">${data.totalPointsEarned ? parseFloat(data.totalPointsEarned).toFixed(2) : '0.00'}</p>
                    </div>
                    <div class="bg-purple-50 p-3 rounded-lg">
                        <p class="text-sm text-purple-700">Toplam Harcanan Puan</p>
                        <p class="text-xl font-bold text-purple-800">${data.totalPointsSpent ? parseFloat(data.totalPointsSpent).toFixed(2) : '0.00'}</p>
                    </div>
                </div>
            `,
            width: 900,
            showConfirmButton: false,
            showCloseButton: true,
            didOpen: () => {
                // Borç geçmişini getir
                fetch(`api/get_customer_credits.php?id=${id}`)
                .then(response => response.json())
                .then(creditData => {
                    if (creditData.success) {
                        const credits = creditData.credits || [];
                        const creditHistorySection = document.getElementById('creditHistorySection');
                        
                        let creditHtml = `
                            <h3 class="font-semibold mb-2 text-left">Borç Geçmişi</h3>
                            <div class="max-h-64 overflow-y-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tarih</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Tutar</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Ödenen</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Durum</th>
                                            <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Detay</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                        `;
                        
                        if (credits.length > 0) {
                            credits.forEach(credit => {
                                const amount = parseFloat(credit.toplam_tutar || 0);
                                const paidAmount = parseFloat(credit.odenen_tutar || 0);
                                const isPaid = credit.odendi_mi == 1;
                                const statusClass = isPaid ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800';
                                const statusText = isPaid ? 'Ödendi' : 'Bekliyor';
                                
                                creditHtml += `
                                <tr>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm">${formatDate(credit.borc_tarihi)}</td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-right">${formatCurrency(amount)}</td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-right text-green-600">${formatCurrency(paidAmount)}</td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm">
                                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full ${statusClass}">
                                            ${statusText}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap text-center">
                                        <button onclick="viewCreditDetails(${credit.borc_id})" class="text-blue-600 hover:text-blue-800">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                                `;
                            });
                        } else {
                            creditHtml += '<tr><td colspan="5" class="px-4 py-2 text-center text-sm text-gray-500">Borç geçmişi bulunamadı</td></tr>';
                        }
                        
                        creditHtml += `
                                    </tbody>
                                </table>
                            </div>
                        `;
                        
                        // Borç özeti
                        const totalCredit = parseFloat(creditData.summary?.toplam_borc || 0);
                        const totalPaid = parseFloat(creditData.summary?.toplam_odeme || 0);
                        const unpaidCredit = parseFloat(creditData.summary?.odenmemis_borc || 0);
                        
                        if (totalCredit > 0) {
                            creditHtml += `
                            <div class="mt-4 flex gap-4">
                                <div class="bg-red-50 p-3 rounded-lg flex-1">
                                    <p class="text-sm text-red-700">Toplam Borç</p>
                                    <p class="text-xl font-bold text-red-800">${formatCurrency(totalCredit)}</p>
                                </div>
                                <div class="bg-green-50 p-3 rounded-lg flex-1">
                                    <p class="text-sm text-green-700">Toplam Ödeme</p>
                                    <p class="text-xl font-bold text-green-800">${formatCurrency(totalPaid)}</p>
                                </div>
                                <div class="bg-yellow-50 p-3 rounded-lg flex-1">
                                    <p class="text-sm text-yellow-700">Kalan Borç</p>
                                    <p class="text-xl font-bold text-yellow-800">${formatCurrency(unpaidCredit)}</p>
                                </div>
                            </div>
                            `;
                        }
                        
                        creditHistorySection.innerHTML = creditHtml;
                    } else {
                        document.getElementById('creditHistorySection').innerHTML = `
                            <h3 class="font-semibold mb-2 text-left">Borç Geçmişi</h3>
                            <div class="p-4 text-center text-sm text-red-500">
                                ${creditData.message || 'Borç geçmişi alınamadı'}
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    document.getElementById('creditHistorySection').innerHTML = `
                        <h3 class="font-semibold mb-2 text-left">Borç Geçmişi</h3>
                        <div class="p-4 text-center text-sm text-red-500">
                            Borç geçmişi yüklenirken bir hata oluştu: ${error.message}
                        </div>
                    `;
                });
            }
        });
    })
    .catch(error => {
        Swal.fire({
            icon: 'error',
            title: 'Hata!',
            text: error.message
        });
    });
}

// Borç tahsilatı raporu sayfasına eklenmek üzere
function getUnpaidCreditsReport() {
    fetch('api/get_unpaid_credits_report.php')
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            throw new Error(data.message || 'Rapor alınamadı');
        }
        
        const credits = data.credits || [];
        const summary = data.summary || {};
        
        // Rapor HTML'ini oluştur
        const reportContainer = document.getElementById('unpaidCreditsReport');
        if (!reportContainer) return;
        
        // Toplam tutarlar
        const totalAmount = parseFloat(summary.toplam_borc || 0);
        const totalPaid = parseFloat(summary.toplam_odeme || 0);
        const remainingAmount = parseFloat(summary.kalan_borc || 0);
        
        let html = `
        <div class="mb-6">
            <div class="grid grid-cols-3 gap-4 mb-4">
                <div class="bg-blue-50 p-4 rounded-lg">
                    <h3 class="text-sm font-medium text-blue-800">Toplam Borç</h3>
                    <p class="text-2xl font-bold text-blue-900">${formatCurrency(totalAmount)}</p>
                </div>
                <div class="bg-green-50 p-4 rounded-lg">
                    <h3 class="text-sm font-medium text-green-800">Toplam Tahsilat</h3>
                    <p class="text-2xl font-bold text-green-900">${formatCurrency(totalPaid)}</p>
                </div>
                <div class="bg-red-50 p-4 rounded-lg">
                    <h3 class="text-sm font-medium text-red-800">Kalan Tahsilat</h3>
                    <p class="text-2xl font-bold text-red-900">${formatCurrency(remainingAmount)}</p>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Müşteri</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tarih</th>
                            <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Toplam Borç</th>
                            <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ödenen</th>
                            <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Kalan</th>
                            <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
        `;
        
        if (credits.length > 0) {
            credits.forEach(credit => {
                const amount = parseFloat(credit.toplam_tutar || 0);
                const paidAmount = parseFloat(credit.odenen_tutar || 0);
                const remainingAmount = amount - paidAmount;
                
                html += `
                <tr>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900">${credit.musteri_adi} ${credit.musteri_soyadi}</div>
                        <div class="text-xs text-gray-500">${credit.telefon || '-'}</div>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">${formatDate(credit.borc_tarihi)}</td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-right">${formatCurrency(amount)}</td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-right text-green-600">${formatCurrency(paidAmount)}</td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-right text-red-600 font-semibold">${formatCurrency(remainingAmount)}</td>
                    <td class="px-4 py-3 whitespace-nowrap text-center">
                        <button onclick="viewCreditDetails(${credit.borc_id})" class="text-blue-600 hover:text-blue-800 mx-1">
                            <svg class="w-5 h-5 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                        <button onclick="addPayment(${credit.borc_id})" class="text-green-600 hover:text-green-800 mx-1">
                            <svg class="w-5 h-5 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                        </button>
                        <button onclick="viewCustomerCredits(${credit.musteri_id})" class="text-purple-600 hover:text-purple-800 mx-1">
                            <svg class="w-5 h-5 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </button>
                    </td>
                </tr>
                `;
            });
        } else {
            html += `<tr><td colspan="6" class="px-4 py-4 text-center text-sm text-gray-500">Ödenmemiş borç kaydı bulunamadı</td></tr>`;
        }
        
        html += `
                    </tbody>
                </table>
            </div>
        </div>
        `;
        
        reportContainer.innerHTML = html;
    })
    .catch(error => {
        console.error('Tahsilat raporu alınamadı:', error);
        const reportContainer = document.getElementById('unpaidCreditsReport');
        if (reportContainer) {
            reportContainer.innerHTML = `
            <div class="bg-red-50 p-4 rounded-lg mb-6">
                <p class="text-red-600">${error.message || 'Tahsilat raporu alınamadı'}</p>
            </div>
            `;
        }
    });
}

// Search customers function
function searchCustomers() {
    const searchTerm = document.getElementById('customerSearch').value.trim();
    const statusFilter = document.getElementById('customerStatus').value;
    
    // Only proceed if we have something to search for
    if (searchTerm.length < 2 && !statusFilter) {
        return; // Don't search with very short terms unless a status filter is applied
    }
    
    // Show loading indicator
    const tableContainer = document.getElementById('customerTableContainer');
    tableContainer.innerHTML = `
        <div class="flex justify-center items-center p-8">
            <svg class="animate-spin h-8 w-8 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span class="ml-2 text-gray-600">Aranıyor...</span>
        </div>
    `;
    
    // Prepare the request URL
    let url = 'api/search_customers.php?';
    const params = new URLSearchParams();
    
    if (searchTerm) {
        params.append('search_term', searchTerm);
    }
    
    if (statusFilter) {
        params.append('status', statusFilter);
    }
    
    // Get the current page size from session if available
    const pageSize = window.customerPageSize || 50;
    params.append('limit', pageSize);
    
    // Make the AJAX request
    fetch(url + params.toString())
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.text();
        })
        .then(html => {
            tableContainer.innerHTML = html;
            // Re-initialize customer row listeners for the new table content
            initializeCustomerRowListeners();
        })
        .catch(error => {
            console.error('Search error:', error);
            tableContainer.innerHTML = `
                <div class="p-8 text-center">
                    <div class="text-red-500 mb-2">Arama sırasında bir hata oluştu</div>
                    <div class="text-gray-600">${error.message}</div>
                </div>
            `;
        });
}

// Initialize customer row click events
function initializeCustomerRowListeners() {
    document.querySelectorAll('.customer-row').forEach(row => {
        row.addEventListener('click', function(e) {
            // Don't trigger if clicking on a button or checkbox
            if (e.target.closest('button') || e.target.closest('input[type="checkbox"]')) {
                return;
            }
            
            const customerData = JSON.parse(this.getAttribute('data-customer'));
            showCustomerDetails(customerData);
        });
    });
}

// Show customer details in a modal
function showCustomerDetails(customer) {
    // Format customer display data
    const puanBakiye = parseFloat(customer.puan_bakiye || 0).toFixed(2);
    const kayitTarihi = customer.kayit_tarihi ? new Date(customer.kayit_tarihi).toLocaleDateString('tr-TR') : '-';
    const durum = customer.durum === 'aktif' ? 
        '<span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">Aktif</span>' : 
        '<span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">Pasif</span>';
    
    Swal.fire({
        title: `${customer.ad} ${customer.soyad}`,
        html: `
            <div class="text-left">
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <p class="text-sm text-gray-600">Müşteri ID</p>
                        <p class="font-semibold">${customer.id}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Kart No / Barkod</p>
                        <p class="font-semibold">${customer.barkod || '-'}</p>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <p class="text-sm text-gray-600">Telefon</p>
                        <p class="font-semibold">${customer.telefon || '-'}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">E-posta</p>
                        <p class="font-semibold">${customer.email || '-'}</p>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <p class="text-sm text-gray-600">Puan Bakiyesi</p>
                        <p class="font-semibold text-green-600">${puanBakiye} Puan</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Kayıt Tarihi</p>
                        <p class="font-semibold">${kayitTarihi}</p>
                    </div>
                </div>
                
                <div class="mb-4">
                    <p class="text-sm text-gray-600">Durum</p>
                    <p class="mt-1">${durum}</p>
                </div>
            </div>
        `,
        showCloseButton: true,
        showConfirmButton: false,
        footer: `
            <div class="flex justify-between w-full">
                <button onclick="viewCustomerPoints(${customer.id})" class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded">
                    Puan İşlemleri
                </button>
                <button onclick="showCustomerHistory(${customer.id})" class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded">
                    İşlem Geçmişi
                </button>
                <button onclick="viewCustomerCredits(${customer.id})" class="bg-purple-500 hover:bg-purple-600 text-white px-3 py-1 rounded">
                    Borç Bilgileri
                </button>
            </div>
        `
    });
}

// Filtreleme işlemi için filterCustomers fonksiyonu
function filterCustomers() {
    // Filtre değerlerini al
    const searchTerm = document.getElementById('customerSearch').value.trim();
    const statusFilter = document.getElementById('customerStatus').value;
    
    // Filtre değerlerini URL parametrelerine çevir
    const urlParams = new URLSearchParams(window.location.search);
    
    // Arama terimini ekle
    if (searchTerm) {
        urlParams.set('search_term', searchTerm);
    } else {
        urlParams.delete('search_term');
    }
    
    // Durum filtresini ekle
    if (statusFilter) {
        urlParams.set('status', statusFilter);
    } else {
        urlParams.delete('status');
    }
    
    // Sayfa numarasını 1'e ayarla (filtre değiştiğinde ilk sayfadan başla)
    urlParams.set('page', '1');
    
    // Yeni URL oluştur ve sayfayı yönlendir
    const newUrl = window.location.pathname + '?' + urlParams.toString();
    window.location.href = newUrl;
}

// Replace the current updateProductsList function with this one
function updateProductsList() {
    const container = document.getElementById('productsList');
    // First check if the container exists before trying to set its innerHTML
    if (!container) {
        console.error('Products list container not found');
        return; // Exit the function early if container doesn't exist
    }
    
    const totalAmount = document.getElementById('totalAmount');
    
    if (selectedProducts.length === 0) {
        container.innerHTML = '<div class="text-center text-sm text-gray-500">Henüz ürün eklenmedi</div>';
        if (totalAmount) totalAmount.value = '0.00';
        return;
    }
    
    let html = '<div class="space-y-2">';
    let total = 0;
    
    selectedProducts.forEach((product, index) => {
        total += parseFloat(product.tutar);
        html += `
        <div class="flex justify-between items-center border-b pb-2">
            <div>
                <p class="font-medium">${product.ad}</p>
                <p class="text-sm text-gray-600">${product.miktar} x ${formatCurrency(product.birim_fiyat)}</p>
            </div>
            <div class="flex items-center">
                <span class="font-medium mr-3">${formatCurrency(product.tutar)}</span>
                <button type="button" onclick="removeProduct(${index})" class="text-red-500 hover:text-red-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                </button>
            </div>
        </div>
        `;
    });
    
    html += '</div>';
    container.innerHTML = html;
    
    // Also check if totalAmount exists
    if (totalAmount) totalAmount.value = total.toFixed(2);
    
    // Ürün silme fonksiyonu
    window.removeProduct = function(index) {
        selectedProducts.splice(index, 1);
        updateProductsList();
    };
}

// Add event listeners when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Store the page size to use in search queries
    const itemsPerPageSelect = document.querySelector('select[name="items_per_page"]');
    if (itemsPerPageSelect) {
        window.customerPageSize = itemsPerPageSelect.value;
        
        // Update when changed
        itemsPerPageSelect.addEventListener('change', function() {
            window.customerPageSize = this.value;
        });
    }
    
    // Initialize search with debounce
    const searchInput = document.getElementById('customerSearch');
    if (searchInput) {
        searchInput.addEventListener('input', debounce(searchCustomers, 500));
        
        // Also search when Enter key is pressed
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchCustomers();
            }
        });
    }
    
    // Status filter change
    const statusFilter = document.getElementById('customerStatus');
    if (statusFilter) {
        statusFilter.addEventListener('change', searchCustomers);
    }
    
    // Filter button click
    const filterBtn = document.getElementById('filterBtn');
    if (filterBtn) {
        filterBtn.addEventListener('click', searchCustomers);
    }
    
    // Initialize row listeners for initial table load
    initializeCustomerRowListeners();
});

// Sayfa yüklendiğinde çalıştırılacak fonksiyonlar
document.addEventListener('DOMContentLoaded', function() {
    // Tahsilat raporu sayfası için
    if (document.getElementById('unpaidCreditsReport')) {
        getUnpaidCreditsReport();
    }
});


// Müşteri kartı menüsüne borç yönetim butonunu ekle
document.addEventListener('DOMContentLoaded', function() {
    // Mevcut müşteri detay sayfasındaki işlem butonları container'ını bul
    const customerActionButtons = document.querySelectorAll('.customer-row');
    
    // Her müşteri satırına borç yönetimi butonu ekle
    customerActionButtons.forEach(row => {
        // Müşteri ID'sini al
        const customerData = JSON.parse(row.getAttribute('data-customer') || '{}');
        const customerId = customerData.id;
        
        if (customerId) {
            // Eylemler sütununu bul
            const actionsCell = row.querySelector('td:last-child div.flex');
            
            if (actionsCell) {
                // Check if debt management button already exists
                const existingButton = actionsCell.querySelector('[data-tooltip="Borç Yönetimi"]');
                if (existingButton) return; // Skip if button already exists
                
                // Borç yönetimi butonu oluştur
                const creditButton = document.createElement('button');
                creditButton.className = 'text-purple-600 hover:text-purple-800 tooltip';
                creditButton.setAttribute('data-tooltip', 'Borç Yönetimi');
                creditButton.innerHTML = `
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                        d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                `;
                creditButton.onclick = function(e) {
                    e.stopPropagation();
                    viewCustomerCredits(customerId);
                };
                
                // Butonu ekle
                actionsCell.appendChild(creditButton);
            }
        }
    });
});