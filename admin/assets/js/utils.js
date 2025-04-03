// Para birimi ve tarih formatlama işlemleri
export const FormatUtils = {
    formatCurrency: (amount) => {
        return new Intl.NumberFormat('tr-TR', {
            style: 'currency',
            currency: 'TRY'
        }).format(amount);
    },
    formatDate: (dateString) => {
        return new Date(dateString).toLocaleString('tr-TR');
    },
    formatNumber: (number) => {
        return new Intl.NumberFormat('tr-TR').format(number);
    }
};

// utils.js
export function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.style.display = 'none';
}

// Validasyon işlemleri
export const ValidationUtils = {
    validatePrices: (alisFiyati, satisFiyati) => {
        alisFiyati = parseFloat(alisFiyati) || 0;
        satisFiyati = parseFloat(satisFiyati) || 0;
        return {
            isValid: satisFiyati > alisFiyati,
            message: satisFiyati <= alisFiyati ? 'Satış fiyatı alış fiyatından büyük olmalıdır' : ''
        };
    },
    validateInput: (value, type = 'text') => {
        switch(type) {
            case 'number':
                return !isNaN(value) && value !== '';
            case 'email':
                return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
            case 'phone':
                return /^[0-9]{10}$/.test(value.replace(/[^0-9]/g, ''));
            default:
                return value.trim() !== '';
        }
    },
    validateForm: (formData, requiredFields) => {
        const errors = {};
        requiredFields.forEach(field => {
            if (!formData.get(field)) {
                errors[field] = 'Bu alan zorunludur';
            }
        });
        return {
            isValid: Object.keys(errors).length === 0,
            errors
        };
    }
};

// DOM manipülasyonu
export const DOMUtils = {
    setHTML: (element, html) => {
        element.textContent = '';
        const template = document.createElement('template');
        template.innerHTML = html.trim();
        element.appendChild(template.content);
    },
    createElement: (tag, attributes = {}, innerHTML = '') => {
        const element = document.createElement(tag);
        Object.entries(attributes).forEach(([key, value]) => {
            element.setAttribute(key, value);
        });
        element.innerHTML = innerHTML;
        return element;
    },
    fillSelectOptions: (selectElement, options, selectedValue = '') => {
        if (!selectElement) return;
        
        const defaultOptions = `
            <option value="">Seçiniz</option>
            <option value="add_new" class="font-semibold text-blue-600">+ Yeni Ekle</option>
        `;

        const optionsHtml = options.map(option => `
            <option value="${option.id}" ${option.id == selectedValue ? 'selected' : ''}>
                ${option.ad}
            </option>
        `).join('');

        DOMUtils.setHTML(selectElement, defaultOptions + optionsHtml);
    }
};

// Loading işlemleri
export const LoadingUtils = {
    showLoading: (element, message = 'Yükleniyor...') => {
        element.style.opacity = '0.5';
        return Swal.fire({
            title: message,
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
    },
    hideLoading: (element) => {
        element.style.opacity = '1';
        Swal.close();
    }
};

// API işlemleri
export const APIUtils = {
    async fetchAPI(url, options = {}) {
        try {
            const response = await fetch(url, {
                ...options,
                headers: {
                    'Content-Type': 'application/json',
                    ...options.headers
                }
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'Bir hata oluştu');
            }

            return data;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    },
    formDataToJSON(formData) {
        const object = {};
        formData.forEach((value, key) => {
            object[key] = value;
        });
        return object;
    }
};

// LocalStorage işlemleri
export const StorageUtils = {
    set: (key, value) => {
        try {
            localStorage.setItem(key, JSON.stringify(value));
            return true;
        } catch (error) {
            console.error('Storage Error:', error);
            return false;
        }
    },
    get: (key, defaultValue = null) => {
        try {
            const item = localStorage.getItem(key);
            return item ? JSON.parse(item) : defaultValue;
        } catch (error) {
            console.error('Storage Error:', error);
            return defaultValue;
        }
    },
    remove: (key) => {
        try {
            localStorage.removeItem(key);
            return true;
        } catch (error) {
            console.error('Storage Error:', error);
            return false;
        }
    }
};

// Debounce utility
export const debounce = (func, wait) => {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
};

// utils.js
export function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.style.display = 'block';
}

// utils.js'e eklenecek
export function showToast(type, message) {
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: type,
        title: message,
        showConfirmButton: false,
        timer: 3000
    });
}

export function showErrorToast(message) {
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'error',
        title: message,
        showConfirmButton: false,
        timer: 3000
    });
}

export function showSuccessToast(message) {
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title: message,
        showConfirmButton: false,
        timer: 3000
    });
}

export function closeLoadingToast() {
    Swal.close();
}

export async function fetchData(url, options = {}) {
    try {
        const response = await fetch(url, {
            ...options,
            headers: {
                'Content-Type': 'application/json',
                ...options.headers
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`);
        }

        return await response.json();
    } catch (error) {
        console.error('Fetch Error:', error);
        throw error;
    }
}

export function showLoadingToast(message = 'Yükleniyor...') {
    Swal.fire({
        title: message,
        allowOutsideClick: false,
        showConfirmButton: false,
        willOpen: () => {
            Swal.showLoading();
        }
    });
}
/**
 * "Yeni Ekle" seçeneği için modal açar ve yeni giriş ekler
 * @param {string} selectId - Select öğesinin ID'si
 */
export function initializeNewEntryModal(selectId, apiUrl) {
    document.getElementById(selectId).addEventListener('change', (event) => {
        if (event.target.value === 'add_new') {
            let additionalInput = '';

            // Eğer alt_grup ekleniyorsa, ana grup seçimi için bir select ekleyelim
            if (selectId === 'alt_grup') {
                additionalInput = `
                    <p class="text-sm text-gray-500 mb-2">Lütfen bir ana grup seçin:</p>
                    <select id="modal_ana_grup" class="swal2-input">
                        <option value="">Seçiniz</option>
                    </select>
                `;
            }

            Swal.fire({
                title: `Yeni ${selectId} Ekle`,
                html: `
                    ${additionalInput}
                    <input id="new_entry_name" class="swal2-input" placeholder="Yeni ${selectId} adını girin">
                `,
                showCancelButton: true,
                confirmButtonText: 'Kaydet',
                cancelButtonText: 'İptal',
                didOpen: () => {
                    if (selectId === 'alt_grup') {
                        // Alt grup için ana grup seçeneklerini yüklerken + Yeni Ekle seçeneğini kaldır
                        fetch('api/get_ana_gruplar.php')
                            .then(response => response.json())
                            .then(data => {
                                const select = document.getElementById('modal_ana_grup');
                                select.innerHTML = '<option value="">Seçiniz</option>';
                                data.data.forEach(item => {
                                    const option = document.createElement('option');
                                    option.value = item.id;
                                    option.textContent = item.ad;
                                    select.appendChild(option);
                                });
                            })
                            .catch(error => console.error('Error loading ana_grup:', error));
                    }
                },
                preConfirm: () => {
                    const newEntry = document.getElementById('new_entry_name').value;
                    const anaGrupId = selectId === 'alt_grup' ? document.getElementById('modal_ana_grup').value : null;

                    if (!newEntry) {
                        Swal.showValidationMessage('Bu alan zorunludur');
                        return false;
                    }

                    if (selectId === 'alt_grup' && !anaGrupId) {
                        Swal.showValidationMessage('Bir ana grup seçmelisiniz');
                        return false;
                    }

                    return fetch(apiUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ 
                            ad: newEntry, 
                            ana_grup_id: anaGrupId 
                        }),
                    })
                    .then(response => response.json())
                    .then(result => {
                        if (result.success) {
                            Swal.fire('Başarılı!', `${selectId} başarıyla eklendi`, 'success');
                            loadSelectOptions(selectId, `api/get_${selectId}s.php`);
                        } else {
                            throw new Error(result.message || 'Bir hata oluştu');
                        }
                    })
                    .catch(error => Swal.fire('Hata!', error.message, 'error'));
                },
            });
        }
    });
}


/**
 * Veritabanından select öğesine seçenekleri yükler
 * @param {string} selectId - Select öğesinin ID'si
 * @param {string} apiUrl - API URL'si (veritabanından veri çekecek PHP dosyası)
 */
export function loadSelectOptions(selectId, apiUrl = null) {
    // Eğer apiUrl belirtilmediyse, selectId'ye göre otomatik oluştur
    if (!apiUrl) {
        let fileName = `get_${selectId}.php`;

        // Özel dosya adlarını belirle
        if (selectId === 'departman') {
            fileName = 'get_departmanlar.php';
        } else if (selectId === 'birim') {
            fileName = 'get_birimler.php';
        } else if (selectId === 'ana_grup') {
            fileName = 'get_ana_gruplar.php';
        } else if (selectId === 'alt_grup') {
            fileName = 'get_alt_gruplar.php';
        } else {
            // Varsayılan dosya adı formatı
            fileName = `get_${selectId}.php`;
        }
    }

    fetch(apiUrl)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            // 'data' alanını kontrol ederek doğru veriye erişim sağlayalım
            if (!data.success || !Array.isArray(data.data)) {
                throw new Error('Geçersiz veri formatı');
            }

            const select = document.getElementById(selectId);
            select.innerHTML = ''; // Önce tüm mevcut seçenekleri temizle

            // Seçiniz seçeneğini ekle ve varsayılan olarak seçili yap
            const defaultOption = document.createElement('option');
            defaultOption.value = '';
            defaultOption.textContent = 'Seçiniz';
            defaultOption.selected = true;
            select.appendChild(defaultOption);

            // + Yeni Ekle seçeneğini ekle
            const newOption = document.createElement('option');
            newOption.value = 'add_new';
            newOption.textContent = '+ Yeni Ekle';
            newOption.className = 'new-entry-option'; // Stil için sınıf atandı
            select.appendChild(newOption);

            // Diğer seçenekleri ekle
            data.data.forEach(item => {
                const option = document.createElement('option');
                option.value = item.id;
                option.textContent = item.ad;
                select.appendChild(option);
            });
        })
        .catch(error => console.error(`Error loading ${selectId}:`, error));
}
