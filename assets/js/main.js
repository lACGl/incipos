let listenersInitialized = false; 
let searchTimeout;

function showSuccessToast(message) {
    Swal.fire({
        icon: 'success',
        title: message,
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        onOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    })
}

function showErrorToast(message) {
    Swal.fire({
        icon: 'error',
        title: message,
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        onOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    })
}

// Debounce fonksiyonu ekleyelim
function debounce(func, wait) {
    let timeout;
    return function(...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

// Yardımcı fonksiyonlar
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('hidden');
    }
}

// Modal kapatma fonksiyonu
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.remove();
    }
}

// Modal dışına tıklamada kapatma
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.add('hidden');
            }
        });
    });

    // ESC tuşu ile modalları kapatma
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal').forEach(modal => {
                modal.classList.add('hidden');
            });
        }
    });
});


// CSS eklemeleri
const style = document.createElement('style');
style.textContent = `
    .stock-details-modal {
        z-index: 9999 !important;
    }

    .swal2-container {
        z-index: 9000;
    }

    .stock-details-modal .swal2-modal {
        margin: 0 !important;
    }
`;
document.head.appendChild(style);

// Ayarları göster
function showSettings() {
    // Mevcut ayarları getir
    fetch('api/get_settings.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Form alanlarını doldur
                const form = document.getElementById('settingsForm');
                const select = form.querySelector('select[name="varsayilan_stok_lokasyonu"]');
                select.value = data.settings.varsayilan_stok_lokasyonu || 'depo';
            }
        })
        .catch(error => {
            console.error('Ayarlar yüklenirken hata:', error);
        });

    document.getElementById('settingsModal').classList.remove('hidden');
}

// Ayarları kapat
function closeSettings() {
    document.getElementById('settingsModal').classList.add('hidden');
}

// Form submit
document.getElementById('settingsForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('api/update_settings.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Başarılı!',
                text: 'Ayarlar kaydedildi',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000
            });
            closeSettings();
        } else {
            throw new Error(data.message || 'Bir hata oluştu');
        }
    })
    .catch(error => {
        Swal.fire({
            icon: 'error',
            title: 'Hata!',
            text: error.message,
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000
        });
    });
});
