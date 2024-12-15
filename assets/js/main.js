// main.js

import { showSuccessToast, showErrorToast, debounce, openModal, closeModal, initializeModalEvents } from './utils.js';


let listenersInitialized = false;
let searchTimeout;

// Ayarları gösterme ve kapatma fonksiyonları
function showSettings() {
    fetch('api/get_settings.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const form = document.getElementById('settingsForm');
                const select = form.querySelector('select[name="varsayilan_stok_lokasyonu"]');
                select.value = data.settings.varsayilan_stok_lokasyonu || 'depo';
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

// Örnek arama callback fonksiyonu
function handleSearch() {
    console.log('Arama yapılıyor...');
    // Buraya arama işlemlerini ekleyebilirsiniz
}

// Başlatma fonksiyonu
function initializeMain() {
    if (listenersInitialized) return;

    initializeModalEvents();
    initializeSettingsForm();
    initializeSearch('#searchInput', handleSearch);

    listenersInitialized = true;
}

// Sayfa yüklendiğinde çalıştır
document.addEventListener('DOMContentLoaded', initializeMain);
