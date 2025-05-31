/**
 * İnciPOS - Ayarlar Sayfası JavaScript Fonksiyonları
 * 
 * Bu dosya, ayarlar sayfasındaki tüm etkileşim, modal ve form işlemlerini yönetir.
 */

document.addEventListener('DOMContentLoaded', function() {
    // Tab değiştirme işlevi
    initializeTabs();
    
    // Varsayılan stok lokasyonu seçeneklerini güncelle
    if (document.getElementById('default_location_type')) {
        updateLocationOptions();
        document.getElementById('default_location_type').addEventListener('change', updateLocationOptions);
    }
    
    // Modallar için kapatma düğmelerini ayarla
    setupModalClosers();
    
    // Formlar için doğrulama kurallarını ayarla
    setupFormValidations();
});

/**
 * Tab geçişlerini yönetir
 */
function initializeTabs() {
    const tabLinks = document.querySelectorAll('.tab-link');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            // URL hash'ini güncelle
            const hash = link.getAttribute('href');
            history.pushState(null, null, hash);
            
            // Aktif tab linkini değiştir
            tabLinks.forEach(l => l.classList.remove('active', 'border-blue-500', 'text-blue-600'));
            tabLinks.forEach(l => l.classList.add('border-transparent', 'text-gray-500'));
            link.classList.remove('border-transparent', 'text-gray-500');
            link.classList.add('active', 'border-blue-500', 'text-blue-600');
            
            // Tab içeriğini değiştir
            const target = link.getAttribute('href').substring(1) + '-content';
            tabContents.forEach(content => content.classList.remove('active'));
            document.getElementById(target).classList.add('active');
        });
    });
    
    // Sayfa yüklendiğinde URL hash'i varsa ilgili tabı aç
    if (window.location.hash) {
        const activeTab = document.querySelector(`.tab-link[href="${window.location.hash}"]`);
        if (activeTab) {
            activeTab.click();
        }
    }
}

/**
 * Varsayılan stok lokasyonu seçeneklerini günceller
 */
function updateLocationOptions() {
    const locationType = document.getElementById('default_location_type').value;
    const locationSelect = document.getElementById('default_location_id');
    const currentSelectedValue = locationSelect.value;
    
    // Mevcut seçenekleri temizle
    locationSelect.innerHTML = '';
    
    // Lokasyon tipine göre API çağrısı yap
    let apiUrl = '';
    if (locationType === 'depo') {
        apiUrl = 'api/get_depolar.php';
    } else {
        apiUrl = 'api/get_magazalar.php';
    }
    
    // API'den verileri getir
	fetch(apiUrl)
        .then(response => response.json())
        .then(data => {
            // Veri yapısına göre işle (depolar veya magazalar)
            const items = locationType === 'depo' ? (data.depolar || []) : (data.magazalar || []);
            
            if (!items || items.length === 0) {
                console.error('Lokasyon verisi bulunamadı. API yanıtı:', data);
                return;
            }

            // Tekrarlanan öğeleri filtrelemek için bir set kullanalım
            const uniqueIds = new Set();
            const uniqueNames = new Set();
            
            items.forEach(item => {
                // ID ve ad kombinasyonu daha önce eklenmemişse ekle
                const itemKey = `${item.id}-${item.ad}`;
                if (!uniqueNames.has(itemKey)) {
                    uniqueNames.add(itemKey);
                    
                    const optionItem = document.createElement('option');
                    optionItem.value = item.id;
                    optionItem.textContent = item.ad;
                    
                    if (item.id == currentSelectedValue) {
                        optionItem.selected = true;
                    }
                    
                    locationSelect.appendChild(optionItem);
                }
            });
        })
        .catch(error => {
            console.error('Lokasyon verileri alınırken hata oluştu:', error);
            showErrorMessage('Lokasyon verileri alınamadı. Lütfen sayfayı yenileyin.');
        });
}

/**
 * Modal kapatma düğmelerini ayarlar
 */
function setupModalClosers() {
    // Tüm close butonlarını bul ve tıklama olaylarını ekle
    document.querySelectorAll('[data-close-modal]').forEach(button => {
        const modalId = button.getAttribute('data-close-modal');
        button.addEventListener('click', () => closeModal(modalId));
    });
    
    // ESC tuşu ile modalı kapatma
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal.active').forEach(modal => {
                closeModal(modal.id);
            });
        }
    });
    
    // Modal dışına tıklandığında kapatma
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeModal(modal.id);
            }
        });
    });
}

/**
 * Form doğrulama kurallarını ayarlar
 */
function setupFormValidations() {
    // Mağaza formu
    const storeForm = document.getElementById('storeForm');
    if (storeForm) {
        storeForm.addEventListener('submit', function(e) {
            const nameField = document.getElementById('store_name');
            if (!nameField.value.trim()) {
                e.preventDefault();
                showFieldError(nameField, 'Mağaza adı gereklidir');
            }
        });
    }
    
    // Depo formu
    const warehouseForm = document.getElementById('warehouseForm');
    if (warehouseForm) {
        warehouseForm.addEventListener('submit', function(e) {
            const nameField = document.getElementById('warehouse_name');
            if (!nameField.value.trim()) {
                e.preventDefault();
                showFieldError(nameField, 'Depo adı gereklidir');
            }
        });
    }
    
    // Personel formu
    const staffForm = document.getElementById('staffForm');
    if (staffForm) {
        staffForm.addEventListener('submit', function(e) {
            const nameField = document.getElementById('staff_name');
            const usernameField = document.getElementById('staff_username');
            const passwordField = document.getElementById('staff_password');
            
            let hasError = false;
            
            if (!nameField.value.trim()) {
                showFieldError(nameField, 'Personel adı gereklidir');
                hasError = true;
            }
            
            if (!usernameField.value.trim()) {
                showFieldError(usernameField, 'Kullanıcı adı gereklidir');
                hasError = true;
            }
            
            // Eğer bu yeni personel ekleme formuysa şifre zorunlu
            if (staffForm.getAttribute('data-form-type') === 'add' && !passwordField.value) {
                showFieldError(passwordField, 'Şifre gereklidir');
                hasError = true;
            }
            
            if (hasError) {
                e.preventDefault();
            }
        });
    }
    
    // Puan ayarları formu
    const pointSettingsForm = document.getElementById('pointSettingsForm');
    if (pointSettingsForm) {
        pointSettingsForm.addEventListener('submit', function(e) {
            // Tüm oran alanlarının 0-100 arasında olduğunu kontrol et
            const rateFields = document.querySelectorAll('input[name$="_point_rate"]');
            let hasError = false;
            
            rateFields.forEach(field => {
                const value = parseFloat(field.value);
                if (isNaN(value) || value < 0 || value > 100) {
                    showFieldError(field, 'Puan oranı 0-100 arasında olmalıdır');
                    hasError = true;
                }
            });
            
            if (hasError) {
                e.preventDefault();
            }
        });
    }
}

/**
 * Mağaza ekleme modalını gösterir
 */
function showAddStoreModal() {
    const modal = document.getElementById('addStoreModal');
    if (!modal) {
        console.error("Modal bulunamadı: addStoreModal");
        showErrorMessage("Modal bulunamadı. Lütfen sayfayı yenileyin.");
        return;
    }
    
    modal.classList.remove('hidden');
    const nameField = document.getElementById('store_name');
    if (nameField) {
        nameField.focus();
    }
}

/**
 * Mağaza düzenleme modalını gösterir
 * @param {number} storeId - Düzenlenecek mağazanın ID'si
 */
function editStore(storeId) {
    // AJAX ile mağaza verilerini getir
    fetch(`api/get_magaza_details.php?id=${storeId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Mağaza verileri alınamadı');
            }
            return response.json();
        })
        .then(data => {
            if (!data || typeof data !== 'object') {
                throw new Error('Geçersiz mağaza verisi alındı: ' + JSON.stringify(data));
            }
            
            // Form alanlarını doldur
            document.getElementById('edit_store_id').value = data.id;
            document.getElementById('edit_store_name').value = data.ad;
            document.getElementById('edit_store_address').value = data.adres || '';
            document.getElementById('edit_store_phone').value = data.telefon || '';
            document.getElementById('edit_store_mobile').value = data.cep_telefon || ''; // Yeni eklenen cep telefonu alanı
            
            // Modalı göster
            const modal = document.getElementById('editStoreModal');
            if (!modal) {
                throw new Error("Modal bulunamadı: editStoreModal");
            }
            
            modal.classList.remove('hidden');
            document.getElementById('edit_store_name').focus();
        })
        .catch(error => {
            console.error('Hata:', error);
            showErrorMessage('Mağaza verileri yüklenirken bir hata oluştu: ' + error.message);
        });
}

/**
 * Mağaza silme işlemini gerçekleştirir
 * @param {number} storeId - Silinecek mağazanın ID'si
 */
function deleteStore(storeId) {
    if (confirm('Bu mağazayı silmek istediğinize emin misiniz?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'store_id';
        idInput.value = storeId;
        
        const submitInput = document.createElement('input');
        submitInput.type = 'hidden';
        submitInput.name = 'delete_store';
        submitInput.value = '1';
        
        form.appendChild(idInput);
        form.appendChild(submitInput);
        document.body.appendChild(form);
        
        form.submit();
    }
}

/**
 * Depo ekleme modalını gösterir
 */
function showAddWarehouseModal() {
    const modal = document.getElementById('addWarehouseModal');
    if (!modal) {
        console.error("Modal bulunamadı: addWarehouseModal");
        showErrorMessage("Modal bulunamadı. Lütfen sayfayı yenileyin.");
        return;
    }
    
    modal.classList.remove('hidden');
    const nameField = document.getElementById('warehouse_name');
    if (nameField) {
        nameField.focus();
    }
}

/**
 * Depo düzenleme modalını gösterir
 * @param {number} warehouseId - Düzenlenecek deponun ID'si
 */
function editWarehouse(warehouseId) {
    fetch(`api/get_depo_details.php?id=${warehouseId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Depo verileri alınamadı');
            }
            return response.json();
        })
        .then(data => {
            if (!data || typeof data !== 'object') {
                throw new Error('Geçersiz depo verisi alındı: ' + JSON.stringify(data));
            }
            
            // Form alanlarını doldur
            document.getElementById('edit_warehouse_id').value = data.id;
            document.getElementById('edit_warehouse_name').value = data.ad;
            document.getElementById('edit_warehouse_code').value = data.kod || '';
            document.getElementById('edit_warehouse_address').value = data.adres || '';
            document.getElementById('edit_warehouse_phone').value = data.telefon || '';
            document.getElementById('edit_warehouse_type').value = data.depo_tipi;
            document.getElementById('edit_warehouse_status').value = data.durum;
            
            // Modalı göster
            const modal = document.getElementById('editWarehouseModal');
            if (!modal) {
                throw new Error("Modal bulunamadı: editWarehouseModal");
            }
            
            modal.classList.remove('hidden');
            document.getElementById('edit_warehouse_name').focus();
        })
        .catch(error => {
            console.error('Hata:', error);
            showErrorMessage('Depo verileri yüklenirken bir hata oluştu: ' + error.message);
        });
}

/**
 * Depo silme işlemini gerçekleştirir
 * @param {number} warehouseId - Silinecek deponun ID'si
 */
function deleteWarehouse(warehouseId) {
    if (confirm('Bu depoyu silmek istediğinize emin misiniz?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'warehouse_id';
        idInput.value = warehouseId;
        
        const submitInput = document.createElement('input');
        submitInput.type = 'hidden';
        submitInput.name = 'delete_warehouse';
        submitInput.value = '1';
        
        form.appendChild(idInput);
        form.appendChild(submitInput);
        document.body.appendChild(form);
        
        form.submit();
    }
}

/**
 * Personel ekleme modalını gösterir
 */
function showAddStaffModal() {
    const modal = document.getElementById('addStaffModal');
    if (!modal) {
        console.error("Modal bulunamadı: addStaffModal");
        showErrorMessage("Modal bulunamadı. Lütfen sayfayı yenileyin.");
        return;
    }
    
    modal.classList.remove('hidden');
    const nameField = document.getElementById('staff_name');
    if (nameField) {
        nameField.focus();
    }
}

/**
 * Personel düzenleme modalını gösterir
 * @param {number} staffId - Düzenlenecek personelin ID'si
 */
function editStaff(staffId) {
    fetch(`api/get_personel_details.php?id=${staffId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Personel verileri alınamadı');
            }
            return response.json();
        })
        .then(data => {
            if (!data || typeof data !== 'object') {
                throw new Error('Geçersiz personel verisi alındı: ' + JSON.stringify(data));
            }
            
            // Form alanlarını doldur
            document.getElementById('edit_staff_id').value = data.id;
            document.getElementById('edit_staff_name').value = data.ad;
            document.getElementById('edit_staff_no').value = data.no || '';
            document.getElementById('edit_staff_username').value = data.kullanici_adi;
            document.getElementById('edit_staff_phone').value = data.telefon_no || '';
            document.getElementById('edit_staff_email').value = data.email || '';
            document.getElementById('edit_staff_role').value = data.yetki_seviyesi;
            document.getElementById('edit_staff_store').value = data.magaza_id || '';
            document.getElementById('edit_staff_status').value = data.durum;
            
            // Şifre alanını temizle (güvenlik için)
            document.getElementById('edit_staff_password').value = '';
            
            // Modalı göster
            const modal = document.getElementById('editStaffModal');
            if (!modal) {
                throw new Error("Modal bulunamadı: editStaffModal");
            }
            
            modal.classList.remove('hidden');
            document.getElementById('edit_staff_name').focus();
        })
        .catch(error => {
            console.error('Hata:', error);
            showErrorMessage('Personel verileri yüklenirken bir hata oluştu: ' + error.message);
        });
}

/**
 * Personel şifre sıfırlama modalını gösterir
 * @param {number} staffId - Şifresi sıfırlanacak personelin ID'si
 */
function resetPassword(staffId) {
    // Personel bilgilerini getir
    fetch(`api/get_personel_details.php?id=${staffId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Personel verileri alınamadı');
            }
            return response.json();
        })
        .then(data => {
            if (!data || typeof data !== 'object') {
                throw new Error('Geçersiz personel verisi alındı: ' + JSON.stringify(data));
            }
            
            document.getElementById('reset_staff_id').value = data.id;
            document.getElementById('reset_staff_name').textContent = data.ad;
            
            // Modalı göster
            const modal = document.getElementById('resetPasswordModal');
            if (!modal) {
                throw new Error("Modal bulunamadı: resetPasswordModal");
            }
            
            modal.classList.remove('hidden');
            document.getElementById('new_password').focus();
        })
        .catch(error => {
            console.error('Hata:', error);
            showErrorMessage('Personel verileri yüklenirken bir hata oluştu: ' + error.message);
        });
}

/**
 * Modalı kapatır
 * @param {string} modalId - Kapatılacak modalın ID'si
 */
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('hidden');
    } else {
        console.error('Kapatılacak modal bulunamadı:', modalId);
    }
}

/**
 * Form alanı için hata mesajı gösterir
 * @param {HTMLElement} field - Hata gösterilecek form alanı
 * @param {string} message - Hata mesajı
 */
function showFieldError(field, message) {
    // Varsa önceki hata mesajını kaldır
    const existingError = field.parentNode.querySelector('.error-message');
    if (existingError) {
        existingError.remove();
    }
    
    // Hata mesajı elementi oluştur
    const errorDiv = document.createElement('div');
    errorDiv.className = 'error-message text-red-500 text-sm mt-1';
    errorDiv.textContent = message;
    
    // Form alanına hata sınıfı ekle
    field.classList.add('border-red-500');
    
    // Hata mesajını ekle
    field.parentNode.appendChild(errorDiv);
    
    // Alanı odakla
    field.focus();
}

/**
 * Genel hata mesajı gösterir
 * @param {string} message - Hata mesajı
 */
function showErrorMessage(message) {
    console.error("Hata:", message);
    
    // SweetAlert2 yüklü ise kullan, değilse normal alert kullan
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'error',
            title: 'Hata',
            text: message,
            confirmButtonText: 'Tamam'
        });
    } else {
        alert("Hata: " + message);
    }
}

/**
 * Başarı mesajı gösterir
 * @param {string} message - Başarı mesajı
 */
function showSuccessMessage(message) {
    // SweetAlert2 yüklü ise kullan, değilse normal alert kullan
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'success',
            title: 'Başarılı',
            text: message,
            confirmButtonText: 'Tamam'
        });
    } else {
        alert("Başarılı: " + message);
    }
}