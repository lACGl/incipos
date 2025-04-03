function showSettings() {
    document.getElementById('settingsModal').classList.remove('hidden');
}

function closeSettings() {
    document.getElementById('settingsModal').classList.add('hidden');
}

async function addNewStore() {
    const { value: formValues } = await Swal.fire({
        title: 'Yeni Mağaza Ekle',
        html: `
            <form id="addStoreForm" class="text-left">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Mağaza Adı*</label>
                    <input type="text" name="ad" class="mt-1 block w-full rounded-md border-gray-300" required>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Adres*</label>
                    <textarea name="adres" class="mt-1 block w-full rounded-md border-gray-300" required></textarea>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Telefon*</label>
                    <input type="tel" name="telefon" class="mt-1 block w-full rounded-md border-gray-300" required>
                </div>
            </form>
        `,
        showCancelButton: true,
        confirmButtonText: 'Ekle',
        cancelButtonText: 'İptal',
        preConfirm: () => {
            const form = document.getElementById('addStoreForm');
            const formData = new FormData(form);
            return fetch('api/add_magaza.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) throw new Error(data.message);
                return data;
            });
        }
    });

    if (formValues) {
        await Swal.fire({
            icon: 'success',
            title: 'Başarılı!',
            text: 'Mağaza başarıyla eklendi',
            timer: 1500
        });
        location.reload();
    }
}

async function addNewWarehouse() {
    const { value: formValues } = await Swal.fire({
        title: 'Yeni Depo Ekle',
        html: `
            <form id="addWarehouseForm" class="text-left">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Depo Adı*</label>
                    <input type="text" name="ad" class="mt-1 block w-full rounded-md border-gray-300" required>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Depo Kodu*</label>
                    <input type="text" name="kod" class="mt-1 block w-full rounded-md border-gray-300" required>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Adres</label>
                    <textarea name="adres" class="mt-1 block w-full rounded-md border-gray-300"></textarea>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Telefon</label>
                    <input type="tel" name="telefon" class="mt-1 block w-full rounded-md border-gray-300">
                </div>
            </form>
        `,
        showCancelButton: true,
        confirmButtonText: 'Ekle',
        cancelButtonText: 'İptal',
        preConfirm: () => {
            const form = document.getElementById('addWarehouseForm');
            const formData = new FormData(form);
            return fetch('api/add_depo.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) throw new Error(data.message);
                return data;
            });
        }
    });

    if (formValues) {
        await Swal.fire({
            icon: 'success',
            title: 'Başarılı!',
            text: 'Depo başarıyla eklendi',
            timer: 1500
        });
        location.reload();
    }
}

// Settings form submit
document.getElementById('settingsForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    try {
        const response = await fetch('api/update_settings.php', {
            method: 'POST',
            body: new FormData(this)
        });

        const data = await response.json();

        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Başarılı!',
                text: 'Ayarlar başarıyla kaydedildi',
                timer: 1500
            });
            closeSettings();
        } else {
            throw new Error(data.message || 'Bir hata oluştu');
        }
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Hata!',
            text: error.message
        });
    }
});

// Sayfa yüklendiğinde mevcut ayarları getir
document.addEventListener('DOMContentLoaded', async function() {
    try {
        const response = await fetch('api/get_settings.php');
        const data = await response.json();
        
        if (data.success) {
            const selectElement = document.querySelector('select[name="varsayilan_stok_lokasyonu"]');
            if (selectElement && data.settings?.varsayilan_stok_lokasyonu) {
                selectElement.value = data.settings.varsayilan_stok_lokasyonu;
            }
        }
    } catch (error) {
        console.error('Ayarlar yüklenirken hata:', error);
    }
});

// Modal dışına tıklamada kapatma
document.getElementById('settingsModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeSettings();
    }
});

// ESC tuşu ile kapatma
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && !document.getElementById('settingsModal').classList.contains('hidden')) {
        closeSettings();
    }
});