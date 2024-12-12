// Para birimi formatı için utility
window.FormatUtils = {
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

// Validasyon için utility
window.ValidationUtils = {
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

// DOM manipülasyonu için utility
window.DOMUtils = {
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

// Loading işlemleri için utility
window.LoadingUtils = {
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

// API işlemleri için utility
window.APIUtils = {
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

// LocalStorage işlemleri için utility
window.StorageUtils = {
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

// Debounce utility'i window nesnesine ekle
window.debounce = (func, wait) => {
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