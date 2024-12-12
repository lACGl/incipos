
// Ürün Ekle
function addProduct() {
   Swal.fire({
       title: 'Yeni Ürün Ekle',
       html: `
           <form id="addProductForm" class="space-y-4 text-left">
               <div class="grid grid-cols-2 gap-4">
                   <div>
                       <label class="block text-sm font-medium text-gray-700">Kod*</label>
                       <input type="text" id="kod" class="swal2-input" placeholder="Ürün Kodu" required>
                   </div>
                   <div>
                       <label class="block text-sm font-medium text-gray-700">Barkod*</label>
                       <input type="number" id="barkod" class="swal2-input" placeholder="Barkod" required>
                   </div>
                   <div>
                       <label class="block text-sm font-medium text-gray-700">Ürün Adı*</label>
                       <input type="text" id="ad" class="swal2-input" placeholder="Ürün Adı" required>
                   </div>
                   <div>
                       <label class="block text-sm font-medium text-gray-700">Web ID</label>
                       <input type="number" id="web_id" class="swal2-input" placeholder="Web ID">
                   </div>
                   <div>
                       <label class="block text-sm font-medium text-gray-700">Yıl</label>
                       <input type="number" id="yil" class="swal2-input" value="${new Date().getFullYear()}">
                   </div>
                   <div>
                       <label class="block text-sm font-medium text-gray-700">KDV Oranı (%)*</label>
                       <select id="kdv_orani" name="kdv_orani" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
    <option value="">KDV Oranı Seçin</option>
    <option value="0">0%</option>
    <option value="1">1%</option>
    <option value="8">8%</option>
    <option value="10">10%</option>
    <option value="18">18%</option>
    <option value="20">20%</option>
</select>
                   </div>
                   <div>
                       <label class="block text-sm font-medium text-gray-700">Alış Fiyatı*</label>
                       <input type="number" id="alis_fiyati" class="swal2-input" step="0.01" placeholder="0.00" required 
                              onchange="validatePrices(); updateKarMarji();">
                   </div>
                   <div>
                       <label class="block text-sm font-medium text-gray-700">Satış Fiyatı*</label>
                       <input type="number" id="satis_fiyati" class="swal2-input" step="0.01" min="0" placeholder="0.00" required 
                              onchange="validatePrices(); updateKarMarji();">
                       <p id="kar_marji_note" class="mt-1 text-sm text-green-600"></p>
                   </div>
                   <div>
                       <label class="block text-sm font-medium text-gray-700">Miktar</label>
                       <input type="number" id="stok_miktari" class="swal2-input" value="0" min="0" step="0.01">
                   </div>
                   <div>
                       <label class="block text-sm font-medium text-gray-700">İndirimli Fiyat</label>
                       <input type="number" id="indirimli_fiyat" class="swal2-input" step="0.01" placeholder="0.00">
                   </div>
                   <div>
    <label class="block text-sm font-medium text-gray-700">Departman</label>
    <select id="departman" class="swal2-input">
        <option value="">Seçiniz</option>
    </select>
</div>
<div>
    <label class="block text-sm font-medium text-gray-700">Birim</label>
    <select id="birim" class="swal2-input">
        <option value="">Seçiniz</option>
    </select>
</div>
<div>
    <label class="block text-sm font-medium text-gray-700">Ana Grup</label>
    <select id="ana_grup" class="swal2-input">
        <option value="">Seçiniz</option>
    </select>
</div>
<div>
    <label class="block text-sm font-medium text-gray-700">Alt Grup</label>
    <select id="alt_grup" class="swal2-input">
        <option value="">Seçiniz</option>
    </select>
</div>
                   <div>
                       <label class="block text-sm font-medium text-gray-700">Resim</label>
                       <div class="flex space-x-2">
                           <input type="file" id="resim" class="swal2-input" accept="image/*">
                       </div>
                   </div>
                   <div>
                       <label class="block text-sm font-medium text-gray-700">İndirim Başlangıç</label>
                       <input type="date" id="indirim_baslangic_tarihi" class="swal2-input">
                   </div>
                   <div>
                       <label class="block text-sm font-medium text-gray-700">İndirim Bitiş</label>
                       <input type="date" id="indirim_bitis_tarihi" class="swal2-input">
                   </div>
                   <div>
                       <label class="block text-sm font-medium text-gray-700">Durum</label>
                       <select id="durum" class="swal2-input">
                           <option value="aktif" selected>Aktif</option>
                           <option value="pasif">Pasif</option>
                       </select>
                   </div>
               </div>
           </form>
       `,
       showCancelButton: true,
       confirmButtonText: 'Ekle',
       cancelButtonText: 'İptal',
       width: '800px',
       didOpen: async () => {
           console.log('Modal açıldı');
           
           // Seçenekleri yükle
           await loadOptions();
           
           // Kar marjı hesaplama için event listener'lar
           const alisFiyati = document.getElementById('alis_fiyati');
           const satisFiyati = document.getElementById('satis_fiyati');
           if (alisFiyati && satisFiyati) {
               alisFiyati.addEventListener('input', updateKarMarji);
               satisFiyati.addEventListener('input', updateKarMarji);
           }

           // Form alanlarının debug kontrolü
           console.log('Form alanları:', {
               kod: document.getElementById('kod'),
               barkod: document.getElementById('barkod'),
               ad: document.getElementById('ad'),
               alis_fiyati: alisFiyati,
               satis_fiyati: satisFiyati,
               kdv_orani: document.getElementById('kdv_orani'),
               departman: document.getElementById('departman'),
               birim: document.getElementById('birim'),
               ana_grup: document.getElementById('ana_grup'),
               alt_grup: document.getElementById('alt_grup')
           });
       },
       preConfirm: () => {
           const formData = new FormData();
           const form = Swal.getPopup().querySelector('#addProductForm');

           console.log('Form bulundu:', form !== null);

           // Tüm form verilerini topla
           const formElements = form.querySelectorAll('input, select');
           formElements.forEach(element => {
               const value = element.value.trim();
               console.log(`${element.id}: ${value}`);
               formData.append(element.id, value);
           });

           // Zorunlu alanları kontrol et
           const required = ['kod', 'barkod', 'ad', 'kdv_orani', 'alis_fiyati', 'satis_fiyati'];
           for (const field of required) {
               const value = formData.get(field);
               console.log(`Checking ${field}: ${value}`);
               if (!value) {
                   Swal.showValidationMessage(`${field} alanı zorunludur`);
                   return false;
               }
           }

           // Fiyat kontrolü
           const alisFiyati = parseFloat(formData.get('alis_fiyati'));
           const satisFiyati = parseFloat(formData.get('satis_fiyati'));
           if (alisFiyati >= satisFiyati) {
               Swal.showValidationMessage('Alış fiyatı satış fiyatından büyük olamaz');
               return false;
           }

           return formData;
       }
   }).then(async (result) => {
       if (result.isConfirmed && result.value) {
           try {
               const response = await fetch('add_product.php', {
                   method: 'POST',
                   body: result.value
               });
               
               const data = await response.json();
               
               if (data.success) {
                   Swal.fire({
                       icon: 'success',
                       title: 'Başarılı!',
                       text: data.message,
                       showConfirmButton: false,
                       timer: 1500
                   }).then(() => {
                       updateTableAjax();
                   });
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
       }
   });
}


// Seçili Ürünleri Sil
function deleteSelected() {
    const selectedRows = document.querySelectorAll('input[name="selected_products[]"]:checked');
    
    if (selectedRows.length === 0) {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'warning',
            title: 'Lütfen silmek istediğiniz ürünleri seçin',
            showConfirmButton: false,
            timer: 3000
        });
        return;
    }

    const ids = Array.from(selectedRows).map(row => row.value);
    
    fetch('delete_multiple_products.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ ids: ids })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: `${selectedRows.length} ürün başarıyla silindi`,
                showConfirmButton: false,
                timer: 3000
            });
            updateTableAjax();
        } else {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'error',
                title: data.message || 'Silme işlemi sırasında bir hata oluştu',
                showConfirmButton: false,
                timer: 3000
            });
        }
    })
    .catch(error => {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'error',
            title: 'Bir hata oluştu',
            text: error.message,
            showConfirmButton: false,
            timer: 3000
        });
    });
}

// Seçili Ürünleri Aktife Al
function activateSelected() {
    const selectedRows = document.querySelectorAll('input[name="selected_products[]"]:checked');
    
    if (selectedRows.length === 0) {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'warning',
            title: 'Lütfen aktife almak istediğiniz ürünleri seçin',
            showConfirmButton: false,
            timer: 3000
        });
        return;
    }

    const ids = Array.from(selectedRows).map(row => row.value);
    
    fetch('activate_products.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ ids: ids })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: `${selectedRows.length} ürün başarıyla aktif duruma alındı`,
                showConfirmButton: false,
                timer: 3000
            });
            updateTableAjax();
        } else {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'error',
                title: data.message || 'İşlem sırasında bir hata oluştu',
                showConfirmButton: false,
                timer: 3000
            });
        }
    })
    .catch(error => {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'error',
            title: 'Bir hata oluştu',
            text: error.message,
            showConfirmButton: false,
            timer: 3000
        });
    });
}

/// Seçili Ürünleri Pasife Al
function deactivateSelected() {
    const selectedRows = document.querySelectorAll('input[name="selected_products[]"]:checked');
    
    if (selectedRows.length === 0) {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'warning',
            title: 'Lütfen pasife almak istediğiniz ürünleri seçin',
            showConfirmButton: false,
            timer: 3000
        });
        return;
    }

    const ids = Array.from(selectedRows).map(row => row.value);
    
    fetch('deactivate_products.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ ids: ids })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: `${selectedRows.length} ürün başarıyla pasife alındı`,
                showConfirmButton: false,
                timer: 3000
            });
            updateTableAjax();
        } else {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'error',
                title: data.message || 'İşlem sırasında bir hata oluştu',
                showConfirmButton: false,
                timer: 3000
            });
        }
    })
    .catch(error => {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'error',
            title: 'Bir hata oluştu',
            text: error.message,
            showConfirmButton: false,
            timer: 3000
        });
    });
}

// Seçili Ürünleri Dışarı Aktar
function exportSelected() {
    const selectedRows = document.querySelectorAll('input[name="selected_products[]"]:checked');
    
    if (selectedRows.length === 0) {
        Swal.fire('Uyarı', 'Lütfen dışarı aktarmak istediğiniz ürünleri seçin.', 'warning');
        return;
    }

    const ids = Array.from(selectedRows).map(row => row.value);
    
    // Excel indirme işlemi için yeni bir pencere aç
    window.location.href = `export_products.php?ids=${ids.join(',')}`;
}