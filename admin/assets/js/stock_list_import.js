// Excel İçe Aktarma işlemlerini ele alan JavaScript kodları

// Import modalını açan fonksiyon
window.importProducts = function() {
    // Örnek Excel şablonunu indirmek için URL
    const templateUrl = 'api/download_import_template.php';
    
    Swal.fire({
        title: 'Excel\'den Ürün İçe Aktar',
        html: `
            <div class="text-left mb-4">
                <p class="mb-2">Excel dosyasından ürünleri içe aktarabilirsiniz.</p>
                <p class="mb-2">Örnek şablonu <a href="${templateUrl}" class="text-blue-600 hover:text-blue-800 underline">buradan indirebilirsiniz</a>.</p>
                <p class="mb-2 font-semibold">Önemli:</p>
                <ul class="list-disc pl-5 text-sm">
                    <li>Zorunlu alanlar: <strong>Barkod</strong> ve <strong>Ürün Adı</strong></li>
                    <li>Web ID, Yıl ve Resim Yolu opsiyonel alanlardır</li>
                    <li>Departman, Birim, Ana Grup ve Alt Grup için ID değil isim kullanın</li>
                    <li>Sistemde olmayan isimler otomatik oluşturulacaktır</li>
                </ul>
            </div>
            <form id="importForm" class="mt-4">
                <div class="mb-3">
                    <label for="import_file" class="block text-sm font-medium text-gray-700 mb-2">
                        Excel Dosyası Seçin (.xlsx, .xls)
                    </label>
                    <input type="file" id="import_file" name="import_file" 
                           accept=".xlsx,.xls" 
                           class="block w-full text-sm text-gray-500
                                  file:mr-4 file:py-2 file:px-4
                                  file:rounded-md file:border-0
                                  file:text-sm file:font-semibold
                                  file:bg-blue-50 file:text-blue-700
                                  hover:file:bg-blue-100">
                </div>
            </form>
        `,
        showCancelButton: true,
        confirmButtonText: 'İçe Aktar',
        cancelButtonText: 'İptal',
        confirmButtonColor: '#10B981',
        width: '600px',
        preConfirm: () => {
            const fileInput = document.getElementById('import_file');
            if (!fileInput.files[0]) {
                Swal.showValidationMessage('Lütfen bir Excel dosyası seçin');
                return false;
            }
            
            return fileInput.files[0];
        }
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            uploadExcelFile(result.value);
        }
    });
};

// Excel dosyasını yükleyen fonksiyon
function uploadExcelFile(file) {
    const formData = new FormData();
    formData.append('import_file', file);
    
    // Yükleniyor göster
    Swal.fire({
        title: 'İçe aktarılıyor...',
        html: 'Lütfen bekleyin, bu işlem biraz zaman alabilir.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    fetch('api/import_products.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        Swal.close();
        
        if (data.success) {
            // İçe aktarım sonuçlarını göster
            Swal.fire({
                icon: 'success',
                title: 'İçe Aktarma Tamamlandı',
                html: generateImportResultsHTML(data),
                confirmButtonText: data.has_stock_products ? 'Stok Dağıtımına Geç' : 'Tamam',
                width: '600px'
            }).then((result) => {
                if (result.isConfirmed && data.has_stock_products) {
                    // Kullanıcı "Stok Dağıtımına Geç" butonuna bastı
                    showLocationSelectionModal(data.stock_products);
                } else {
                    // Stok olmadığı durumda veya iptal edildiğinde tabloyu yenile
                    updateTableAjax();
                }
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Hata!',
                text: data.message || 'İçe aktarma sırasında bir hata oluştu.',
                confirmButtonText: 'Tamam'
            });
        }
    })
    .catch(error => {
        Swal.close();
        Swal.fire({
            icon: 'error',
            title: 'Hata!',
            text: 'İstek sırasında bir hata oluştu: ' + error.message,
            confirmButtonText: 'Tamam'
        });
    });
}

// İçe aktarma sonuçları HTML'ini oluşturan yardımcı fonksiyon
function generateImportResultsHTML(data) {
    let errorContent = '';
    
    if (data.errors && data.errors.length > 0) {
        errorContent = `
            <div class="mt-4">
                <h3 class="font-medium mb-2">Hatalar</h3>
                <div class="bg-white p-3 rounded-md border border-red-200 max-h-60 overflow-y-auto">
                    <ul class="list-disc pl-5 text-sm text-red-700 space-y-1">
                        ${data.errors.map(error => `<li>${error}</li>`).join('')}
                    </ul>
                </div>
            </div>
        `;
    }
    
    return `
        <div class="text-left">
            <p>İçe aktarma işlemi tamamlandı.</p>
            <div class="grid grid-cols-3 gap-4 my-4">
                <div class="bg-green-50 p-3 rounded-md">
                    <h3 class="font-medium text-green-800">Başarıyla Eklenen</h3>
                    <p class="text-2xl font-bold text-green-700">${data.imported}</p>
                </div>
                <div class="bg-red-50 p-3 rounded-md">
                    <h3 class="font-medium text-red-800">Atlanan</h3>
                    <p class="text-2xl font-bold text-red-700">${data.skipped}</p>
                </div>
                <div class="bg-blue-50 p-3 rounded-md">
                    <h3 class="font-medium text-blue-800">Toplam</h3>
                    <p class="text-2xl font-bold text-blue-700">${data.imported + data.skipped}</p>
                </div>
            </div>
            ${errorContent}
            ${data.has_stock_products ? '<p class="mt-4 text-blue-600 font-medium">Excel\'de stok bilgisi olan ürünler tespit edildi. Stok dağıtımına geçmek için aşağıdaki butona tıklayınız.</p>' : ''}
        </div>
    `;
}

// Lokasyon seçme modalını gösteren fonksiyon
function showLocationSelectionModal(stockProducts) {
    // Depo ve mağazaları al
    Promise.all([
        fetch('api/get_depolar.php').then(res => res.json()),
        fetch('api/get_magazalar.php').then(res => res.json())
    ])
    .then(([depoData, magazaData]) => {
        const depolar = depoData.depolar || [];
        const magazalar = magazaData.magazalar || [];
        
        // Lokasyonları birleştir
        const locations = [
            { type: 'header', text: 'Depolar' },
            ...depolar.map(d => ({ id: d.id, name: d.ad || d.kod, type: 'depo' })),
            { type: 'header', text: 'Mağazalar' },
            ...magazalar.map(m => ({ id: m.id, name: m.ad, type: 'magaza' }))
        ];
        
        const locationOptions = locations.map(loc => {
            if (loc.type === 'header') {
                return `<option disabled>${loc.text}</option>`;
            }
            return `<option value="${loc.type}_${loc.id}">${loc.name}</option>`;
        }).join('');
        
        Swal.fire({
            title: 'Stok Lokasyonu Seçin',
            html: `
                <div class="text-left mb-4">
                    <p>İçe aktarılan ${stockProducts.length} ürün için stok eklenecek.</p>
                    <p class="text-sm text-gray-600">Toplam stok miktarı: ${stockProducts.reduce((sum, p) => sum + parseFloat(p.miktar), 0)}</p>
                </div>
                <div class="form-group">
                    <label for="location_select" class="block text-sm font-medium text-gray-700 mb-2">Stoklar Nereye Eklensin?</label>
                    <select id="location_select" class="w-full p-2 border border-gray-300 rounded-md">
                        <option value="">Lokasyon Seçin</option>
                        ${locationOptions}
                    </select>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Stok Ekle',
            cancelButtonText: 'İptal',
            preConfirm: () => {
                const selectElement = document.getElementById('location_select');
                if (!selectElement.value) {
                    Swal.showValidationMessage('Lütfen bir lokasyon seçin');
                    return false;
                }
                
                const [locationType, locationId] = selectElement.value.split('_');
                return {
                    lokasyon_tipi: locationType,
                    lokasyon_id: locationId
                };
            }
        }).then((result) => {
            if (result.isConfirmed) {
                updateStockLocation(result.value, stockProducts);
            } else {
                // Kullanıcı iptal ederse, yine de içe aktarma sonuçlarını göster
                Swal.fire({
                    title: 'İçe Aktarma Tamamlandı',
                    text: 'Stok kaydı eklenmedi. Ürünler stoksuz olarak eklenmiştir.',
                    icon: 'info',
                    confirmButtonText: 'Tamam'
                }).then(() => {
                    // Tabloyu yenile
                    updateTableAjax();
                });
            }
        });
    })
    .catch(error => {
        console.error('Lokasyon bilgileri alınamadı:', error);
        Swal.fire({
            icon: 'error',
            title: 'Hata!',
            text: 'Lokasyon bilgileri alınamadı: ' + error.message,
            confirmButtonText: 'Tamam'
        });
    });
}

// Stok lokasyonunu güncelleyen fonksiyon
function updateStockLocation(location, stockProducts) {
    // Yükleniyor göster
    Swal.fire({
        title: 'Stoklar Ekleniyor...',
        html: 'Lütfen bekleyin',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    // API'ye gönder
    fetch('api/update_stock_location.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            lokasyon_tipi: location.lokasyon_tipi,
            lokasyon_id: location.lokasyon_id,
            urunler: stockProducts
        })
    })
    .then(response => response.json())
    .then(data => {
        Swal.close();
        
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Stok Ekleme Tamamlandı',
                text: data.message || 'Stoklar başarıyla eklendi.',
                confirmButtonText: 'Tamam'
            }).then(() => {
                // Tabloyu yenile
                updateTableAjax();
            });
        } else {
            throw new Error(data.message || 'Stok ekleme sırasında bir hata oluştu.');
        }
    })
    .catch(error => {
        Swal.close();
        Swal.fire({
            icon: 'error',
            title: 'Hata!',
            text: error.message || 'Stok ekleme sırasında bir hata oluştu.',
            confirmButtonText: 'Tamam'
        });
    });
}