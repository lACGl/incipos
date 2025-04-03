import { editProduct, deleteProduct, showPriceHistory, transferProduct, initializeKarMarji } from './stock_list_actions.js';
import { updateTableAjax } from './stock_list.js';
import { initializeNewEntryModal, loadSelectOptions } from './utils.js';


export const StockListProcessModule = {
    addProduct,
    deleteSelected,
    activateSelected,
    deactivateSelected,
    exportSelected
};
/**
 * Seçili ürünleri almak için genel yardımcı fonksiyon
 */
function getSelectedProductIds() {
    // Debug için console log ekleyelim
    const checkboxes = document.querySelectorAll('input[name="selected_products[]"]:checked');
    console.log('Seçili checkboxlar:', checkboxes);
    
    const ids = Array.from(checkboxes).map(checkbox => checkbox.value);
    console.log('Seçili IDler:', ids);
    
    return ids;
}

/**
 * Uyarı göstermek için genel yardımcı fonksiyon
 */
function showWarningToast(message) {
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'warning',
        title: message,
        showConfirmButton: false,
        timer: 3000
    });
}

/**
 * Başarı mesajı göstermek için genel yardımcı fonksiyon
 */
function showSuccessToast(message) {
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title: message,
        showConfirmButton: false,
        timer: 3000
    });
}

/**
 * Hata mesajı göstermek için genel yardımcı fonksiyon
 */
function showErrorToast(message) {
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'error',
        title: message,
        showConfirmButton: false,
        timer: 3000
    });
}

/**
 * API çağrısı yapan genel yardımcı fonksiyon
 */
async function performApiRequest(url, data, successMessage) {
    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();
        
        if (result.success) {
            Swal.fire({
                icon: 'success',
                title: 'Başarılı!',
                text: successMessage,
                timer: 1500
            }).then(() => {
                window.location.reload();
            });
        } else {
            throw new Error(result.message || 'İşlem başarısız oldu');
        }
    } catch (error) {
        console.error('API Hatası:', error);
        showErrorToast(error.message);
    }
}
		
		export function addProduct(options = {}) {
    const { 
        initialBarkod = '', // Başlangıç barkod değeri
        onSave = null      // Kayıt sonrası callback
    } = options;

    // Modal HTML'i aynı kalacak
    const modalHtml = `
<div class="form-container">
    <div class="form-group">
        <label for="kod" class="form-label">Kod*</label>
        <input type="text" id="kod" name="kod" class="form-input" placeholder="Ürün Kodu">
    </div>
    <div class="form-group">
        <label for="barkod" class="form-label">Barkod*</label>
        <input type="text" id="barkod" name="barkod" class="form-input" placeholder="Barkod">
    </div>
    <div class="form-group">
        <label for="ad" class="form-label">Ürün Adı*</label>
        <input type="text" id="ad" name="ad" class="form-input" placeholder="Ürün Adı">
    </div>
    <div class="form-group">
        <label for="web_id" class="form-label">Web ID</label>
        <input type="number" id="web_id" name="web_id" class="form-input" placeholder="Web ID">
    </div>
    <div class="form-group">
        <label for="alis_fiyati" class="form-label">Alış Fiyatı*</label>
        <input type="number" step="0.01" id="alis_fiyati" name="alis_fiyati" class="form-input" placeholder="0.00">
    </div>
    <div class="form-group">
        <label for="satis_fiyati" class="form-label">Satış Fiyatı*</label>
        <input type="number" step="0.01" id="satis_fiyati" name="satis_fiyati" class="form-input" placeholder="0.00">
        <div class="kar-marji-note text-sm text-green-600 mt-1"></div>
    </div>
    <div class="form-group">
        <label for="stok_miktari" class="form-label">Miktar</label>
        <input type="number" id="stok_miktari" name="stok_miktari" class="form-input" placeholder="0">
    </div>
    <div class="form-group">
        <label for="kdv_orani" class="form-label">KDV Oranı (%)*</label>
        <select id="kdv_orani" name="kdv_orani" class="form-input">
            <option value="" selected>Seçiniz</option>
            <option value="0">0%</option>
            <option value="1">1%</option>
            <option value="8">8%</option>
            <option value="10">10%</option>
            <option value="18">18%</option>
            <option value="20">20%</option>
        </select>
    </div>
    <div class="form-group">
        <label for="departman" class="form-label">Departman</label>
        <select id="departman" name="departman" class="form-input">
            <option value="">Seçiniz</option>
            <option value="add_new">+ Yeni Ekle</option>
        </select>
    </div>
    <div class="form-group">
        <label for="birim" class="form-label">Birim</label>
        <select id="birim" name="birim" class="form-input">
            <option value="">Seçiniz</option>
            <option value="add_new">+ Yeni Ekle</option>
        </select>
    </div>
    <div class="form-group">
        <label for="ana_grup" class="form-label">Ana Grup</label>
        <select id="ana_grup" name="ana_grup" class="form-input">
            <option value="">Seçiniz</option>
            <option value="add_new">+ Yeni Ekle</option>
        </select>
    </div>
    <div class="form-group">
        <label for="alt_grup" class="form-label">Alt Grup</label>
        <select id="alt_grup" name="alt_grup" class="form-input">
            <option value="">Seçiniz</option>
            <option value="add_new">+ Yeni Ekle</option>
        </select>
    </div>
    <div class="form-group">
        <label for="yil" class="form-label">Yıl</label>
        <input type="number" id="yil" name="yil" class="form-input" value="${new Date().getFullYear()}">
    </div>
    <div class="form-group">
        <label for="durum" class="form-label">Durum</label>
        <select id="durum" name="durum" class="form-input">
            <option value="aktif">Aktif</option>
            <option value="pasif">Pasif</option>
        </select>
    </div>
    <div class="form-group">
        <label for="resim" class="form-label">Resim Yükle</label>
        <input type="file" id="resim" name="resim" class="form-input">
    </div>
</div>
        `

    Swal.fire({
        title: 'Yeni Ürün Ekle',
        html: modalHtml,
        width: '50%',
        showCancelButton: true,
        confirmButtonText: 'Kaydet',
        cancelButtonText: 'İptal',
        didOpen: () => {
            console.log('Modal açıldı');
            
            // Barkod alanını doldur
            if (initialBarkod) {
                document.getElementById('barkod').value = initialBarkod;
            }

            // Kar marjı hesaplama
            const alisFiyati = document.getElementById('alis_fiyati');
            const satisFiyati = document.getElementById('satis_fiyati');
            const karMarjiNote = document.querySelector('.kar-marji-note');

            const updateKarMarji = () => {
                const alis = parseFloat(alisFiyati.value) || 0;
                const satis = parseFloat(satisFiyati.value) || 0;
                
                if (alis > 0 && satis > 0) {
                    const karMarji = ((satis - alis) / alis) * 100;
                    const karTutari = satis - alis;
                    karMarjiNote.textContent = `Kar Marjı: %${karMarji.toFixed(2)} (${karTutari.toFixed(2)}₺)`;
                } else {
                    karMarjiNote.textContent = '';
                }
            };

            alisFiyati.addEventListener('input', updateKarMarji);
            satisFiyati.addEventListener('input', updateKarMarji);

            // Seçenekleri yükle
            loadSelectOptions('departman', 'api/get_departmanlar.php');
            loadSelectOptions('birim', 'api/get_birimler.php');
            loadSelectOptions('ana_grup', 'api/get_ana_gruplar.php');
            loadSelectOptions('alt_grup', 'api/get_alt_gruplar.php');

            // Yeni öğe ekleme modallarını başlat
            initializeNewEntryModal('departman', 'api/add_departman.php');
            initializeNewEntryModal('birim', 'api/add_birim.php');
            initializeNewEntryModal('ana_grup', 'api/add_ana_grup.php');
            initializeNewEntryModal('alt_grup', 'api/add_alt_grup.php');
        },
        preConfirm: async () => {
            try {
                // Zorunlu alan kontrolü
                const requiredFields = ['kod', 'barkod', 'ad', 'kdv_orani', 'alis_fiyati', 'satis_fiyati'];
                const missingFields = [];

                requiredFields.forEach(field => {
                    const element = document.getElementById(field);
                    if (!element || !element.value.trim()) {
                        missingFields.push(field);
                    }
                });

                if (missingFields.length > 0) {
                    Swal.showValidationMessage(`Eksik alanlar: ${missingFields.join(', ')}`);
                    return false;
                }

                // Form verilerini topla
                const formData = {
                    kod: document.getElementById('kod').value.trim(),
                    barkod: document.getElementById('barkod').value.trim(),
                    ad: document.getElementById('ad').value.trim(),
                    web_id: document.getElementById('web_id').value || null,
                    alis_fiyati: parseFloat(document.getElementById('alis_fiyati').value) || 0,
                    satis_fiyati: parseFloat(document.getElementById('satis_fiyati').value) || 0,
                    stok_miktari: parseFloat(document.getElementById('stok_miktari').value) || 0,
                    kdv_orani: document.getElementById('kdv_orani').value,
                    departman: document.getElementById('departman').value || null,
                    birim: document.getElementById('birim').value || null,
                    ana_grup: document.getElementById('ana_grup').value || null,
                    alt_grup: document.getElementById('alt_grup').value || null,
                    yil: parseInt(document.getElementById('yil').value) || new Date().getFullYear(),
                    durum: document.getElementById('durum').value
                };

                const response = await fetch('api/add_product.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(formData)
                });

                const result = await response.json();

                if (!result.success) {
                    throw new Error(result.message || 'Bir hata oluştu');
                }

                // Kayıt başarılıysa ve callback varsa çağır
                if (onSave) {
                    onSave(result);
                }

                return result;
            } catch (error) {
                Swal.showValidationMessage(error.message);
                return false;
            }
        }
    }).then((result) => {
    if (result.isConfirmed) {
        // Başarı mesajını göster
        Swal.fire({
            icon: 'success',
            title: 'Başarılı!',
            text: 'Ürün başarıyla eklendi',
            showConfirmButton: false,
            timer: 1500
        }).then(() => {
            if (onSave) {
                onSave(result.value);
            } else {
                // Normal modda tabloyu güncelle
                updateTableAjax();
            }
        });
    }
}).catch(error => {
    Swal.fire({
        icon: 'error',
        title: 'Hata!',
        text: error.message
    });
});
}

// Seçili Ürünleri Sil
export function deleteSelected() {
    const ids = getSelectedProductIds();
    console.log('Silinecek IDler:', ids); // Debug için

    if (!Array.isArray(ids) || ids.length === 0) {
        showWarningToast('Lütfen silmek istediğiniz ürünleri seçin');
        return;
    }

    performApiRequest('classes/delete_multiple_products.php', { ids }, `${ids.length} ürün başarıyla silindi`);
}

// Seçili Ürünleri Aktife Al
function activateSelected() {
    const ids = getSelectedProductIds();
    if (ids.length === 0) {
        showWarningToast('Lütfen aktife almak istediğiniz ürünleri seçin');
        return;
    }
    performApiRequest('classes/activate_products.php', { ids }, `${ids.length} ürün başarıyla aktife alındı`);
}

// Seçili Ürünleri Pasife Al
function deactivateSelected() {
    const ids = getSelectedProductIds();
    if (ids.length === 0) {
        showWarningToast('Lütfen pasife almak istediğiniz ürünleri seçin');
        return;
    }
    performApiRequest('classes/deactivate_products.php', { ids }, `${ids.length} ürün başarıyla pasife alındı`);
}

// Seçili Ürünleri Dışarı Aktar
function exportSelected() {
    const ids = getSelectedProductIds();
    if (ids.length === 0) {
        showWarningToast('Lütfen dışarı aktarmak istediğiniz ürünleri seçin');
        return;
    }
    
    Swal.fire({
        title: 'Dışarı Aktarılıyor...',
        text: 'Ürünler Excel dosyasına aktarılıyor, lütfen bekleyin.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
            // Yeni sekmede açarak indirme işlemini başlat
            window.open(`api/export_products.php?ids=${ids.join(',')}`, '_blank');
            
            // Kısa bir süre sonra modalı kapat
            setTimeout(() => {
                Swal.close();
                showSuccessToast('Excel dosyası hazırlandı');
            }, 2000);
        }
    });
}


// Legacy support için window objesine ekleme
window.StockListProcessModule = StockListProcessModule;

// Window'a direkt fonksiyonları da ekleyelim geriye dönük uyumluluk için
window.deleteSelected = deleteSelected;
window.activateSelected = activateSelected;
window.deactivateSelected = deactivateSelected;
window.exportSelected = exportSelected;