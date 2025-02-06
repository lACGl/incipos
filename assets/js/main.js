import { showSuccessToast, showErrorToast, debounce, openModal, closeModal, initializeModalEvents } from './utils.js';

window.showSettings = showSettings;
window.closeSettings = closeSettings;

let listenersInitialized = false;
let searchTimeout;

// Ayarları gösterme ve kapatma fonksiyonları
function showSettings() {
    fetch('api/get_settings.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const form = document.getElementById('settingsForm');
                if (form) {
                    const select = form.querySelector('select[name="varsayilan_stok_lokasyonu"]');
                    if (select) {
                        select.value = data.settings.varsayilan_stok_lokasyonu || 'depo';
                    }
                }
            }
        })
        .catch(error => {
            console.error('Ayarlar yüklenirken hata:', error);
            showErrorToast('Ayarlar yüklenirken bir hata oluştu.');
        });
    openModal('settingsModal');
}

function closeSettings() {
    closeModal('settingsModal');
}

// Ayarları kaydetme işlemi
function initializeSettingsForm() {
    const settingsForm = document.getElementById('settingsForm');
    if (settingsForm) {
        settingsForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            fetch('api/update_settings.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccessToast('Ayarlar kaydedildi.');
                    closeSettings();
                } else {
                    throw new Error(data.message || 'Bir hata oluştu');
                }
            })
            .catch(error => {
                showErrorToast(error.message);
            });
        });
    }
}

// Arama işlemi (Debounce ile)
function initializeSearch(inputSelector, callback, delay = 500) {
    const searchInput = document.querySelector(inputSelector);
    if (searchInput) {
        searchInput.addEventListener('input', debounce(callback, delay));
    }
}


// Başlatma fonksiyonu
function initializeMain() {
    if (listenersInitialized) return;

    initializeModalEvents();
    initializeSettingsForm();

    listenersInitialized = true;
}

// Sayfa yüklendiğinde çalıştır
document.addEventListener('DOMContentLoaded', initializeMain);
