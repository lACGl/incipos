function editProduct(id, event) {
   if (event) {
       event.stopPropagation();
   }
   
   const row = document.querySelector(`tr[data-product*='"id":${id}']`);
   if (row) {
       const productData = JSON.parse(row.getAttribute('data-product'));
       showEditModal(productData);
   }
}



function showEditModal(product) {
    const existingModal = document.getElementById('updatePopup');
    if (existingModal) {
        existingModal.style.display = 'none';
    }
    
    const form = popup.querySelector('form');
    if (!form) return;
    
    // Ürün ID'sini gizli alana koy
    document.getElementById('productId').value = product.id;
    
    // API'den ürün detaylarını al
    fetch(`get_product_details.php?id=${product.id}`)
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                throw new Error(data.message || 'Bilinmeyen hata');
            }
            
            const productData = data.product;
            
            // Form alanlarını doldur
            form.querySelectorAll('input, select').forEach(input => {
                const fieldName = input.name;
                if (!fieldName) return; // Name attribute'u yoksa atla
                
                if (input.tagName === 'SELECT') {
                    // Select elementleri için options oluştur
                    if (fieldName === 'departman_id' && data.departmanlar) {
                        fillSelectOptions(input, data.departmanlar, productData[fieldName]);
                    } else if (fieldName === 'birim_id' && data.birimler) {
                        fillSelectOptions(input, data.birimler, productData[fieldName]);
                    } else if (fieldName === 'ana_grup_id' && data.ana_gruplar) {
                        fillSelectOptions(input, data.ana_gruplar, productData[fieldName]);
                    } else if (fieldName === 'alt_grup_id' && data.alt_gruplar) {
                        const filteredAltGruplar = data.alt_gruplar.filter(alg => 
                            alg.ana_grup_id == productData.ana_grup_id
                        );
                        fillSelectOptions(input, filteredAltGruplar, productData[fieldName]);
                    }
                } else if (productData[fieldName] !== undefined) {
                    input.value = productData[fieldName] || '';
                }
            });
            
            // Kar marjı notunu ekle
            const alisFiyatiInput = form.querySelector('input[name="alis_fiyati"]');
            const satisFiyatiInput = form.querySelector('input[name="satis_fiyati"]');
            
            if (alisFiyatiInput && satisFiyatiInput) {
                // Kar marjı notu için div ekle (eğer yoksa)
                let karMarjiNote = form.querySelector('.kar-marji-note');
                if (!karMarjiNote) {
                    karMarjiNote = document.createElement('div');
                    karMarjiNote.className = 'kar-marji-note text-sm text-green-600 mt-1';
                    satisFiyatiInput.parentNode.insertBefore(karMarjiNote, satisFiyatiInput.nextSibling);
                }
                
                // Kar marjı hesaplama fonksiyonu
                const updateKarMarji = () => {
                    const alisFiyati = parseFloat(alisFiyatiInput.value) || 0;
                    const satisFiyati = parseFloat(satisFiyatiInput.value) || 0;
                    
                    if (alisFiyati > 0 && satisFiyati > 0) {
                        const karMarji = ((satisFiyati - alisFiyati) / alisFiyati) * 100;
                        const karTutari = satisFiyati - alisFiyati;
                        karMarjiNote.textContent = `Kar Marjı: %${karMarji.toFixed(2)} (${karTutari.toFixed(2)}₺)`;
                    } else {
                        karMarjiNote.textContent = '';
                    }
                };

                // Event listener'ları ekle
                alisFiyatiInput.addEventListener('input', updateKarMarji);
                satisFiyatiInput.addEventListener('input', updateKarMarji);
                
                // İlk yüklemede kar marjını hesapla
                updateKarMarji();
            }

            // Ana grup değiştiğinde alt grupları güncelle
            const anaGrupSelect = form.querySelector('select[name="ana_grup_id"]');
            const altGrupSelect = form.querySelector('select[name="alt_grup_id"]');
            
            if (anaGrupSelect && altGrupSelect) {
                anaGrupSelect.addEventListener('change', function() {
                    const selectedAnaGrupId = this.value;
                    if (selectedAnaGrupId === 'add_new') {
                        // Yeni ana grup ekleme modalını aç
                        return;
                    }
                    const filteredAltGruplar = data.alt_gruplar.filter(alg => 
                        alg.ana_grup_id == selectedAnaGrupId
                    );
                    fillSelectOptions(altGrupSelect, filteredAltGruplar);
                });
            }
            
            // Popup'ı göster
            popup.style.display = 'flex';
        })
        .catch(error => {
            console.error('Hata:', error);
            Swal.fire({
                icon: 'error',
                title: 'Hata!',
                text: 'Ürün detayları alınırken bir hata oluştu: ' + error.message
            });
        });
}


// Buton tıklamalarında event propagation'ı durdur
window.editProduct = function(id, event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    
    const row = document.querySelector(`tr[data-product*='"id":${id}']`);
    if (row) {
        try {
            const productData = JSON.parse(row.getAttribute('data-product') || '{}');
            if (productData.id) {
                showEditModal(productData);
            }
        } catch (error) {
            console.error('Error parsing product data:', error);
        }
    }
}


    // Düzenleme modalını gösterme fonksiyonu
    window.showEditModal = function(product) {
        const popup = document.getElementById('updatePopup');
        if (!popup) return;
        
        const form = popup.querySelector('form');
        if (!form) return;
        
        // Ürün ID'sini gizli alana koy
        document.getElementById('productId').value = product.id;
        
        // API'den ürün detaylarını al
        fetch(`get_product_details.php?id=${product.id}`)
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    throw new Error(data.message || 'Bilinmeyen hata');
                }
                
                const productData = data.product;
                
                // Form alanlarını doldur
                form.querySelectorAll('input, select').forEach(input => {
                    const fieldName = input.name;
                    if (!fieldName) return;
                    
                    if (input.tagName === 'SELECT') {
                        if (fieldName === 'departman_id' && data.departmanlar) {
                            fillSelectOptions(input, data.departmanlar, productData[fieldName]);
                        } else if (fieldName === 'birim_id' && data.birimler) {
                            fillSelectOptions(input, data.birimler, productData[fieldName]);
                        } else if (fieldName === 'ana_grup_id' && data.ana_gruplar) {
                            fillSelectOptions(input, data.ana_gruplar, productData[fieldName]);
                        } else if (fieldName === 'alt_grup_id' && data.alt_gruplar) {
                            const filteredAltGruplar = data.alt_gruplar.filter(alg => 
                                alg.ana_grup_id == productData.ana_grup_id
                            );
                            fillSelectOptions(input, filteredAltGruplar, productData[fieldName]);
                        }
                    } else if (productData[fieldName] !== undefined) {
                        input.value = productData[fieldName] || '';
                    }
                });
                
                // Kar marjı notunu ekle
                const alisFiyatiInput = form.querySelector('input[name="alis_fiyati"]');
                const satisFiyatiInput = form.querySelector('input[name="satis_fiyati"]');
                
                if (alisFiyatiInput && satisFiyatiInput) {
                    let karMarjiNote = form.querySelector('.kar-marji-note');
                    if (!karMarjiNote) {
                        karMarjiNote = document.createElement('div');
                        karMarjiNote.className = 'kar-marji-note text-sm text-green-600 mt-1';
                        satisFiyatiInput.parentNode.insertBefore(karMarjiNote, satisFiyatiInput.nextSibling);
                    }
                    
                    const updateKarMarji = () => {
                        const alisFiyati = parseFloat(alisFiyatiInput.value) || 0;
                        const satisFiyati = parseFloat(satisFiyatiInput.value) || 0;
                        
                        if (alisFiyati > 0 && satisFiyati > 0) {
                            const karMarji = ((satisFiyati - alisFiyati) / alisFiyati) * 100;
                            const karTutari = satisFiyati - alisFiyati;
                            karMarjiNote.textContent = `Kar Marjı: %${karMarji.toFixed(2)} (${karTutari.toFixed(2)}₺)`;
                        } else {
                            karMarjiNote.textContent = '';
                        }
                    };

                    // Event listener'ları ekle
                    alisFiyatiInput.addEventListener('input', updateKarMarji);
                    satisFiyatiInput.addEventListener('input', updateKarMarji);
                    
                    // İlk yüklemede kar marjını hesapla
                    updateKarMarji();

                    // Fiyat validasyonu
                    const validatePrices = () => {
                        const alisFiyati = parseFloat(alisFiyatiInput.value) || 0;
                        const satisFiyati = parseFloat(satisFiyatiInput.value) || 0;
                        if (alisFiyati >= satisFiyati && satisFiyati > 0) {
                            satisFiyatiInput.setCustomValidity('Alış fiyatı satış fiyatından büyük olamaz');
                            return false;
                        } else {
                            satisFiyatiInput.setCustomValidity('');
                            return true;
                        }
                    };

                    alisFiyatiInput.addEventListener('change', validatePrices);
                    satisFiyatiInput.addEventListener('change', validatePrices);
                }

                // Ana grup değiştiğinde alt grupları güncelle
                const anaGrupSelect = form.querySelector('select[name="ana_grup_id"]');
                const altGrupSelect = form.querySelector('select[name="alt_grup_id"]');
                
                if (anaGrupSelect && altGrupSelect) {
                    anaGrupSelect.addEventListener('change', function() {
                        const selectedAnaGrupId = this.value;
                        if (selectedAnaGrupId === 'add_new') {
                            return;
                        }
                        const filteredAltGruplar = data.alt_gruplar.filter(alg => 
                            alg.ana_grup_id == selectedAnaGrupId
                        );
                        fillSelectOptions(altGrupSelect, filteredAltGruplar);
                    });
                }
                
                // Popup'ı göster
                popup.style.display = 'flex';
            })
            .catch(error => {
                console.error('Hata:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Hata!',
                    text: 'Ürün detayları alınırken bir hata oluştu: ' + error.message
                });
            });
    }
});

// addStock fonksiyonu
window.addStock = function(id, event) {
    if (event) {
        event.stopPropagation();
    }
    
    // Önceki popup'ları kapat
    const existingPopup = document.getElementById('updatePopup');
    if (existingPopup) {
        existingPopup.style.display = 'none';
    }

    // Mağazaları al
    fetch('api/get_magazalar.php')
        .then(response => response.json())
        .then(magazalarData => {
            let magazaOptions = `
                <option value="">Lokasyon Seçin</option>
                <option value="depo">Ana Depo</option>
                ${magazalarData.magazalar.map(magaza => 
                    `<option value="${magaza.id}">${magaza.ad}</option>`
                ).join('')}
            `;

            Swal.fire({
                title: 'Stok Güncelle',
                html: `
                    <div class="grid grid-cols-1 gap-4">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700">Lokasyon</label>
                            <select id="stockLocation" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                ${magazaOptions}
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700">Miktar</label>
                            <input type="number" id="stockAmount" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" min="0.01" step="0.01" required>
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700">İşlem</label>
                            <select id="stockOperation" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                <option value="add">Ekle (+)</option>
                                <option value="remove">Çıkar (-)</option>
                            </select>
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Güncelle',
                cancelButtonText: 'İptal',
                preConfirm: () => {
                    const location = document.getElementById('stockLocation').value;
                    if (!location) {
                        Swal.showValidationMessage('Lütfen lokasyon seçin');
                        return false;
                    }
                    const amount = document.getElementById('stockAmount').value;
                    if (!amount || amount <= 0) {
                        Swal.showValidationMessage('Lütfen geçerli bir miktar girin');
                        return false;
                    }
                    return {
                        location: location,
                        amount: amount,
                        operation: document.getElementById('stockOperation').value
                    };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const magazaId = result.value.location === 'depo' ? null : result.value.location;
                    updateStock(
                        id, 
                        result.value.amount, 
                        result.value.operation, 
                        result.value.location === 'depo' ? 'depo' : 'magaza',
                        magazaId
                    );
                }
            });
        })
        .catch(error => {
            console.error('Veri yükleme hatası:', error);
            Swal.fire({
                icon: 'error',
                title: 'Hata!',
                text: 'Veriler yüklenirken bir hata oluştu.'
            });
        });
}


// Detay gösterme fonksiyonu
function showDetailsModal(product) {
    let detailsHtml = `
        <div class="text-left">
            <div class="grid grid-cols-2 gap-4">
                <div class="font-bold">Barkod:</div>
                <div>${product.barkod || '-'}</div>
                
                <div class="font-bold">Ürün Adı:</div>
                <div>${product.ad || '-'}</div>
                
                <div class="font-bold">Stok:</div>
                <div>${product.stok_miktari || '0'}</div>
                
                <div class="font-bold">Satış Fiyatı:</div>
                <div>₺${parseFloat(product.satis_fiyati || 0).toFixed(2)}</div>
                
                <div class="font-bold">KDV Oranı:</div>
                <div>%${product.kdv_orani || '0'}</div>
                
                <div class="font-bold">Departman:</div>
                <div>${product.departman || '-'}</div>
                
                <div class="font-bold">Kategori:</div>
                <div>${product.ana_grup || '-'}</div>
                
                <div class="font-bold">Alt Kategori:</div>
                <div>${product.alt_grup || '-'}</div>
            </div>
            ${product.indirimli_fiyat ? `
                <div class="mt-4 p-2 bg-yellow-50 rounded">
                    <div class="font-bold">İndirimli Fiyat:</div>
                    <div>₺${parseFloat(product.indirimli_fiyat).toFixed(2)}</div>
                    <div class="text-sm text-gray-600">
                        İndirim Tarihleri: ${product.indirim_baslangic_tarihi || '-'} - ${product.indirim_bitis_tarihi || '-'}
                    </div>
                </div>
            ` : ''}
        </div>
    `;
	
    // Modal'ı göster
    let modal = Swal.fire({
        title: product.ad,
        html: detailsHtml,
        width: '900px',
        showConfirmButton: false,
        showCloseButton: true
    });

    // Ek bilgileri getir
    fetch(`api/get_product_history.php?id=${product.id}`)
        .then(response => response.json())
        .then(data => {
            if (!data.success) return;

            let extraHtml = '';

            // Fiyat geçmişi varsa ekle
            if (data.fiyat_gecmisi && data.fiyat_gecmisi.length > 0) {
                extraHtml += `
                    <div class="mt-4 border-t pt-4">
                        <h3 class="font-bold mb-2">Fiyat Geçmişi</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="px-2 py-1 text-left text-xs">Tarih</th>
                                        <th class="px-2 py-1 text-left text-xs">İşlem</th>
                                        <th class="px-2 py-1 text-right text-xs">Eski Fiyat</th>
                                        <th class="px-2 py-1 text-right text-xs">Yeni Fiyat</th>
                                        <th class="px-2 py-1 text-left text-xs">Detay</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${data.fiyat_gecmisi.map(fiyat => `
                                        <tr class="border-b">
                                            <td class="px-2 py-1 text-xs">${new Date(fiyat.tarih).toLocaleString('tr-TR')}</td>
                                            <td class="px-2 py-1 text-xs">${fiyat.islem_tipi === 'alis' ? 'Alış' : 'Güncelleme'}</td>
                                            <td class="px-2 py-1 text-xs text-right">${fiyat.eski_fiyat ? '₺' + parseFloat(fiyat.eski_fiyat).toFixed(2) : '-'}</td>
                                            <td class="px-2 py-1 text-xs text-right">₺${parseFloat(fiyat.yeni_fiyat).toFixed(2)}</td>
                                            <td class="px-2 py-1 text-xs">
                                                ${fiyat.fatura_seri ? `Fatura: ${fiyat.fatura_seri}${fiyat.fatura_no} (${fiyat.tedarikci_adi})` : ''}
                                                ${fiyat.kullanici_adi ? `<br>Kullanıcı: ${fiyat.kullanici_adi}` : ''}
                                            </td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    </div>
                `;
            }

            // Stok hareketleri varsa ekle
if (data.stok_hareketleri && data.stok_hareketleri.length > 0) {
    extraHtml += `
        <div class="mt-4 border-t pt-4">
            <h3 class="font-bold mb-2">Stok Hareketleri</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-2 py-1 text-left text-xs">Tarih</th>
                            <th class="px-2 py-1 text-left text-xs">İşlem</th>
                            <th class="px-2 py-1 text-right text-xs">Miktar</th>
                            <th class="px-2 py-1 text-right text-xs">Alış Fiyatı</th>
                            <th class="px-2 py-1 text-right text-xs">Satış Fiyatı</th>
                            <th class="px-2 py-1 text-left text-xs">Mağaza</th>
                            <th class="px-2 py-1 text-left text-xs">Açıklama</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${data.stok_hareketleri.map(hareket => `
                            <tr class="border-b">
                                <td class="px-2 py-1 text-xs">${new Date(hareket.tarih).toLocaleString('tr-TR')}</td>
                                <td class="px-2 py-1 text-xs">
                                    ${hareket.hareket_tipi === 'giris' ? 
                                        '<span class="text-green-600">Giriş</span>' : 
                                        '<span class="text-red-600">Çıkış</span>'}
                                </td>
                                <td class="px-2 py-1 text-xs text-right">${parseFloat(hareket.miktar).toFixed(2)}</td>
                                <td class="px-2 py-1 text-xs text-right">${hareket.maliyet ? '₺' + parseFloat(hareket.maliyet).toFixed(2) : '-'}</td>
                                <td class="px-2 py-1 text-xs text-right">${hareket.satis_fiyati ? '₺' + parseFloat(hareket.satis_fiyati).toFixed(2) : '-'}</td>
                                <td class="px-2 py-1 text-xs">${hareket.magaza_adi}</td>
                                <td class="px-2 py-1 text-xs">
                                    ${hareket.aciklama}
                                    ${hareket.kullanici_adi ? `<br>Kullanıcı: ${hareket.kullanici_adi}` : ''}
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        </div>
    `;
}
            if (extraHtml) {
                // Mevcut modal içeriğine ek bilgileri ekle
                Swal.update({
                    html: detailsHtml + extraHtml
                });
            }
        })
        .catch(error => console.error('Tarihçe yüklenirken hata:', error));
}

// Buton event handler'ları
window.showDetails = function(id, event) {
    if (event) {
        event.stopPropagation();
    }
    const row = document.querySelector(`tr[data-product*='"id":${id}']`);
    if (row) {
        const productData = JSON.parse(row.getAttribute('data-product'));
        showDetailsModal(productData);
    }
}

// Detay modalı gösterme fonksiyonu
async function showDetails(id) {
    try {
        const [detailsResponse, stockResponse] = await Promise.all([
            fetch(`get_product_details.php?id=${id}`),
            fetch(`get_product_stock_details.php?id=${id}`)
        ]);

        const details = await detailsResponse.json();
        const stock = await stockResponse.json();
        
        // Debug için
        console.log('Stok detayları:', stock);


        if (!details.success) {
            throw new Error('Ürün detayları alınamadı');
        }

        const product = details.product;

        let html = `
            <div class="text-left space-y-4">
                <!-- Temel Bilgiler -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="font-medium">Barkod:</div>
                    <div>${product.barkod || '-'}</div>
                    
                    <div class="font-medium">Ürün Adı:</div>
                    <div>${product.ad || '-'}</div>
                    
                    <div class="font-medium">Stok:</div>
                    <div>${product.stok_miktari || '0'}</div>
                    
                    <div class="font-medium">Alış Fiyatı:</div>
                    <div>${parseFloat(product.alis_fiyati).toFixed(2)}₺</div>
					
                    <div class="font-medium">Satış Fiyatı:</div>
                    <div>${parseFloat(product.satis_fiyati).toFixed(2)}₺</div>
                    
                    <div class="font-medium">KDV Oranı:</div>
                    <div>%${product.kdv_orani}</div>
                    
                    <div class="font-medium">Departman:</div>
                    <div>${product.departman || '-'}</div>
                    
                    <div class="font-medium">Ana Grup:</div>
                    <div>${product.ana_grup || '-'}</div>
                    
                    <div class="font-medium">Alt Grup:</div>
                    <div>${product.alt_grup || '-'}</div>
                </div>

                <!-- Stok Hareketleri -->
                <div class="mt-6">
                    <h3 class="text-lg font-medium mb-3">Stok Hareketleri</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tarih</th>
                                    <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">İşlem</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Miktar</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Alış Fiyatı</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Satış Fiyatı</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Lokasyon</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Açıklama</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                ${stock.hareketler?.map(hareket => `
                                    <tr>
                                        <td class="px-4 py-2 text-sm">${new Date(hareket.tarih).toLocaleString('tr-TR')}</td>
                                        <td class="px-4 py-2 text-center">
                                            <span class="px-2 py-1 text-xs rounded-full ${hareket.hareket_tipi === 'giris' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
                                                ${hareket.hareket_tipi === 'giris' ? 'Giriş' : 'Çıkış'}
                                            </span>
                                        </td>
                                        <td class="px-4 py-2 text-right">${hareket.miktar}</td>
                                        <td class="px-4 py-2 text-right">${hareket.maliyet ? parseFloat(hareket.maliyet).toFixed(2) + '₺' : '-'}</td>
                                        <td class="px-4 py-2 text-right">${hareket.satis_fiyati ? parseFloat(hareket.satis_fiyati).toFixed(2) + '₺' : '-'}</td>
                                        <td class="px-4 py-2">${hareket.magaza_adi || 'Ana Depo'}</td>
                                        <td class="px-4 py-2">${hareket.aciklama || '-'}</td>
                                    </tr>
                                `).join('') || `<tr><td colspan="7" class="px-4 py-2 text-center text-gray-500">Stok hareketi bulunamadı</td></tr>`}
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Depo ve Mağaza Stokları -->
                <div class="mt-6">
                    <h3 class="text-lg font-medium mb-3">Stok Dağılımı</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-blue-50 p-4 rounded">
                            <h4 class="font-medium mb-2">Depo Stok</h4>
                            <p>${stock.depo_stok?.stok_miktari || 0}</p>
                        </div>
                        <div class="bg-green-50 p-4 rounded">
                            <h4 class="font-medium mb-2">Mağaza Stokları</h4>
                            ${stock.magaza_stoklari?.map(magaza => `
                                <div class="flex justify-between">
                                    <span>${magaza.magaza_adi}:</span>
                                    <span>${magaza.stok_miktari || 0}</span>
                                </div>
                            `).join('') || 'Mağaza stoku bulunamadı'}
                        </div>
                    </div>
                </div>
            </div>
        `;

        Swal.fire({
            title: product.ad,
            html: html,
            width: '1000px',
            showCloseButton: true,
            showConfirmButton: false
        });

    } catch (error) {
        console.error('Detay görüntüleme hatası:', error);
        Swal.fire({
            icon: 'error',
            title: 'Hata!',
            text: 'Ürün detayları yüklenirken bir hata oluştu.'
        });
    }
}



window.deleteProduct = function(id, event) {
    if (event) {
        event.stopPropagation();
    }
    Swal.fire({
        title: 'Emin misiniz?',
        text: "Bu işlem geri alınamaz!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Evet, sil!',
        cancelButtonText: 'İptal'
    }).then((result) => {
        if (result.isConfirmed) {
            deleteProductAction(id);
        }
    });
}
// Silme fonksiyonu
function deleteProductAction(id) {
    const row = document.querySelector(`tr[data-product*='"id":${id}']`);
    if (row) {
        row.style.opacity = '0.5';
    }

    fetch('delete_product.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            id: id
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Ürün başarıyla silindi',
                showConfirmButton: false,
                timer: 3000
            });
            updateTableAjax();
        } else {
            if (row) {
                row.style.opacity = '1';
            }
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'error',
                title: data.message || 'Silme işlemi başarısız',
                showConfirmButton: false,
                timer: 3000
            });
        }
    })
    .catch(error => {
        if (row) {
            row.style.opacity = '1';
        }
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


window.showStockDetails = async function(id, event) {
    event?.preventDefault();
    event?.stopPropagation();
    
    // Düzenleme modalını kapat
    const updatePopup = document.getElementById('updatePopup');
    if (updatePopup) {
        updatePopup.style.display = 'none';
    }

    try {
        const response = await fetch(`get_stock_details.php?id=${id}`);
        const data = await response.json();

        if (!data.success) {
            throw new Error(data.message || 'Stok detayları alınamadı');
        }

        // Mevcut tüm SweetAlert modallarını kapat
        Swal.close();

        // Yeni modalı göster
        await Swal.fire({
            title: 'Stok Detayları',
            html: `
                <div class="text-left space-y-6" onclick="event.stopPropagation()">
                    <!-- Depo Stok -->
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <h3 class="font-bold text-lg mb-2">Depo Stok</h3>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="font-semibold">Miktar:</div>
                            <div>${data.depo_stok.stok_miktari || 0}</div>
                            <div class="font-semibold">Son Güncelleme:</div>
                            <div>${data.depo_stok.son_guncelleme ? new Date(data.depo_stok.son_guncelleme).toLocaleString('tr-TR') : '-'}</div>
                        </div>
                    </div>

                    <!-- Mağaza Stokları -->
                    <div class="bg-green-50 p-4 rounded-lg">
                        <h3 class="font-bold text-lg mb-2">Mağaza Stokları</h3>
                        <table class="min-w-full">
                            <thead>
                                <tr>
                                    <th class="text-left">Mağaza</th>
                                    <th class="text-right">Stok</th>
                                    <th class="text-right">Satış Fiyatı</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${data.magaza_stoklari.map(magaza => `
                                    <tr>
                                        <td>${magaza.magaza_adi || '-'}</td>
                                        <td class="text-right">${magaza.stok_miktari || 0}</td>
                                        <td class="text-right">₺${parseFloat(magaza.satis_fiyati || 0).toFixed(2)}</td>
                                    </tr>
                                `).join('') || '<tr><td colspan="3" class="text-center">Mağaza stoku bulunmuyor</td></tr>'}
                            </tbody>
                            <tfoot>
                                <tr class="font-bold">
                                    <td>Toplam</td>
                                    <td class="text-right">${data.toplam_stok.magaza}</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- Genel Toplam -->
                    <div class="bg-purple-50 p-4 rounded-lg">
                        <h3 class="font-bold text-lg mb-2">Genel Toplam</h3>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="font-semibold">Toplam Stok:</div>
                            <div>${data.toplam_stok.genel_toplam}</div>
                        </div>
                    </div>
                </div>
            `,
            width: '800px',
            showCloseButton: true,
            showConfirmButton: false,
            customClass: {
                container: 'stock-details-modal'
            },
            stopKeydownPropagation: true,
            didOpen: () => {
                // Modal açıldığında event listener ekle
                const modalContent = document.querySelector('.stock-details-modal');
                if (modalContent) {
                    modalContent.addEventListener('click', (e) => {
                        e.stopPropagation();
                    });
                }
				    // Select elementlerine event listener ekle
    ['departman', 'birim', 'ana_grup', 'alt_grup'].forEach(id => {
        const select = document.getElementById(id);
        if (select) {
            select.addEventListener('change', handleOptionChange);
        }
    });
            }
        });
        
        return false;
		
    await loadOptions();

    } catch (error) {
        console.error('Stok detayları hatası:', error);
        Swal.fire({
            icon: 'error',
            title: 'Hata!',
            text: 'Stok detayları alınırken bir hata oluştu.'
        });
    }
};


// Fiyat geçmişi HTML'ini oluşturan yardımcı fonksiyon
function generatePriceHistoryHtml(priceHistory) {
    return `
        <div class="mt-4 border-t pt-4">
            <h3 class="font-bold mb-2">Fiyat Geçmişi</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-2 py-1 text-left text-xs">Tarih</th>
                            <th class="px-2 py-1 text-left text-xs">İşlem</th>
                            <th class="px-2 py-1 text-right text-xs">Eski Fiyat</th>
                            <th class="px-2 py-1 text-right text-xs">Yeni Fiyat</th>
                            <th class="px-2 py-1 text-left text-xs">Detay</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${priceHistory.map(fiyat => `
                            <tr class="border-b">
                                <td class="px-2 py-1 text-xs">${new Date(fiyat.tarih).toLocaleString('tr-TR')}</td>
                                <td class="px-2 py-1 text-xs">${fiyat.islem_tipi === 'alis' ? 'Alış' : 'Güncelleme'}</td>
                                <td class="px-2 py-1 text-xs text-right">${fiyat.eski_fiyat ? '₺' + parseFloat(fiyat.eski_fiyat).toFixed(2) : '-'}</td>
                                <td class="px-2 py-1 text-xs text-right">₺${parseFloat(fiyat.yeni_fiyat).toFixed(2)}</td>
                                <td class="px-2 py-1 text-xs">
                                    ${fiyat.fatura_seri ? `Fatura: ${fiyat.fatura_seri}${fiyat.fatura_no} (${fiyat.tedarikci_adi})` : ''}
                                    ${fiyat.kullanici_adi ? `<br>Kullanıcı: ${fiyat.kullanici_adi}` : ''}
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        </div>
    `;
}

// Stok hareketleri HTML'ini oluşturan yardımcı fonksiyon
function generateStockMovementsHtml(stockMovements) {
    return `
        <div class="mt-4 border-t pt-4">
            <h3 class="font-bold mb-2">Stok Hareketleri</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-2 py-1 text-left text-xs">Tarih</th>
                            <th class="px-2 py-1 text-left text-xs">İşlem</th>
                            <th class="px-2 py-1 text-right text-xs">Miktar</th>
                            <th class="px-2 py-1 text-right text-xs">Alış Fiyatı</th>
                            <th class="px-2 py-1 text-right text-xs">Satış Fiyatı</th>
                            <th class="px-2 py-1 text-left text-xs">Mağaza/Depo</th>
                            <th class="px-2 py-1 text-left text-xs">Açıklama</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${stockMovements.map(hareket => `
                            <tr class="border-b">
                                <td class="px-2 py-1 text-xs">${new Date(hareket.tarih).toLocaleString('tr-TR')}</td>
                                <td class="px-2 py-1 text-xs">
                                    ${hareket.hareket_tipi === 'giris' ? 
                                        '<span class="text-green-600">Giriş</span>' : 
                                        '<span class="text-red-600">Çıkış</span>'}
                                </td>
                                <td class="px-2 py-1 text-xs text-right">${parseFloat(hareket.miktar).toFixed(2)}</td>
                                <td class="px-2 py-1 text-xs text-right">${hareket.maliyet ? '₺' + parseFloat(hareket.maliyet).toFixed(2) : '-'}</td>
                                <td class="px-2 py-1 text-xs text-right">${hareket.satis_fiyati ? '₺' + parseFloat(hareket.satis_fiyati).toFixed(2) : '-'}</td>
                                <td class="px-2 py-1 text-xs">${hareket.magaza_adi}</td>
                                <td class="px-2 py-1 text-xs">
                                    ${hareket.aciklama}
                                    ${hareket.kullanici_adi ? `<br>Kullanıcı: ${hareket.kullanici_adi}` : ''}
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        </div>
    `;
}

function generateDetailsHtml(product) {
    console.log('Generating HTML for product:', product); // Debug için

    return `
        <div class="text-left">
            <div class="grid grid-cols-2 gap-4">
                <div class="font-bold">Barkod:</div>
                <div>${product.barkod || '-'}</div>
                
                <div class="font-bold">Ürün Adı:</div>
                <div>${product.ad || '-'}</div>
                
                <div class="font-bold">Stok:</div>
                <div>${product.stok_miktari || '0'}</div>
                
                <div class="font-bold">Satış Fiyatı:</div>
                <div>₺${parseFloat(product.satis_fiyati || 0).toFixed(2)}</div>
                
                <div class="font-bold">KDV Oranı:</div>
                <div>%${product.kdv_orani || '0'}</div>
                
                <div class="font-bold">Departman:</div>
                <div>${product.departman || '-'}</div>
                
                <div class="font-bold">Kategori:</div>
                <div>${product.ana_grup || '-'}</div>
                
                <div class="font-bold">Alt Kategori:</div>
                <div>${product.alt_grup || '-'}</div>
            </div>
            ${product.indirimli_fiyat ? `
                <div class="mt-4 p-2 bg-yellow-50 rounded">
                    <div class="font-bold">İndirimli Fiyat:</div>
                    <div>₺${parseFloat(product.indirimli_fiyat).toFixed(2)}</div>
                    <div class="text-sm text-gray-600">
                        İndirim Tarihleri: ${product.indirim_baslangic_tarihi || '-'} - ${product.indirim_bitis_tarihi || '-'}
                    </div>
                </div>
            ` : ''}
        </div>
    `;
}


async function showPriceHistory(id, event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }

    try {
        const response = await fetch(`api/get_price_history.php?id=${id}`);
        const data = await response.json();

        if (!data.success) {
            throw new Error(data.message || 'Fiyat geçmişi alınamadı');
        }

        Swal.fire({
            title: 'Fiyat Geçmişi',
            html: `
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-4 py-2 text-left">Tarih</th>
                                <th class="px-4 py-2 text-left">İşlem Tipi</th>
                                <th class="px-4 py-2 text-right">Eski Fiyat</th>
                                <th class="px-4 py-2 text-right">Yeni Fiyat</th>
                                <th class="px-4 py-2 text-left">Açıklama</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${data.price_history.map(item => {
                                // Fiyatları formatlayacak yardımcı fonksiyon
                                const formatPrice = (price) => {
                                    return price !== null ? `${parseFloat(price).toFixed(2)} ₺` : '-';
                                };

                                return `
                                    <tr class="border-b hover:bg-gray-50">
                                        <td class="px-4 py-2">
                                            ${new Date(item.tarih).toLocaleString('tr-TR')}
                                        </td>
                                        <td class="px-4 py-2 text-blue-600">
                                            ${item.islem_tipi || '-'}
                                        </td>
                                        <td class="px-4 py-2 text-right font-medium ${parseFloat(item.eski_fiyat) > parseFloat(item.yeni_fiyat) ? 'text-red-500' : ''}">
                                            ${formatPrice(item.eski_fiyat)}
                                        </td>
                                        <td class="px-4 py-2 text-right font-medium ${parseFloat(item.yeni_fiyat) > parseFloat(item.eski_fiyat) ? 'text-green-500' : ''}">
                                            ${formatPrice(item.yeni_fiyat)}
                                        </td>
                                        <td class="px-4 py-2">
                                            ${item.aciklama || '-'}
                                            ${item.fatura_seri ? 
                                                `<br><span class="text-xs text-gray-500">
                                                    Fatura: ${item.fatura_seri}${item.fatura_no} 
                                                    ${item.tedarikci_adi ? `(${item.tedarikci_adi})` : ''}
                                                </span>` 
                                                : ''
                                            }
                                            ${item.kullanici_adi ? 
                                                `<br><span class="text-xs text-gray-500">
                                                    Kullanıcı: ${item.kullanici_adi}
                                                </span>` 
                                                : ''
                                            }
                                        </td>
                                    </tr>
                                `;
                            }).join('')}
                            ${data.price_history.length === 0 ? `
                                <tr>
                                    <td colspan="5" class="px-4 py-2 text-center text-gray-500">
                                        Fiyat geçmişi bulunamadı
                                    </td>
                                </tr>
                            ` : ''}
                        </tbody>
                    </table>
                </div>
            `,
            width: '900px',
            padding: '1.5rem',
            confirmButtonText: 'Kapat',
            showCloseButton: true,
            customClass: {
                container: 'price-history-modal',
                popup: 'price-history-popup'
            }
        });

    } catch (error) {
        console.error('Fiyat geçmişi hatası:', error);
        Swal.fire({
            icon: 'error',
            title: 'Hata!',
            text: error.message
        });
    }
}


// Aktarım modalını göster
function showTransferModal(invoiceId, products) {
    Swal.fire({
        title: 'Mağazaya Aktar',
        html: `
            <form id="transferForm">
                <input type="hidden" name="fatura_id" value="${invoiceId}">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Mağaza Seçin</label>
                    <select name="magaza_id" class="mt-1 block w-full rounded-md border-gray-300" required>
                        ${getMagazaOptions()}
                    </select>
                </div>
                <div class="mt-4">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-4 py-2">Ürün</th>
                                <th class="px-4 py-2">Miktar</th>
                                <th class="px-4 py-2">Birim Fiyat</th>
                                <th class="px-4 py-2">Toplam</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${products.map(product => `
                                <tr>
                                    <td class="px-4 py-2">${product.ad}</td>
                                    <td class="px-4 py-2">${product.miktar}</td>
                                    <td class="px-4 py-2">${formatCurrency(product.birim_fiyat)}</td>
                                    <td class="px-4 py-2">${formatCurrency(product.miktar * product.birim_fiyat)}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </form>
        `,
        showCancelButton: true,
        confirmButtonText: 'Aktarımı Tamamla',
        cancelButtonText: 'İptal',
        width: '800px'
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.getElementById('transferForm');
            handleTransfer(new FormData(form));
        }
    });
}



function transferProduct(id, event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }

    // Mağaza ve depoları getir
    Promise.all([
        fetch('api/get_magazalar.php').then(res => res.json()),
        fetch('api/get_depolar.php').then(res => res.json())
    ])
    .then(([magazalarData, depolarData]) => {
        if (!magazalarData.success || !depolarData.success) {
            throw new Error('Veri yüklenemedi');
        }

        let sourceSelect, targetSelect; // Select elementlerini referans olarak tutacak değişkenler

        Swal.fire({
            title: 'Stok Transfer',
            html: `
                <form id="transferForm" class="space-y-4">
                    <input type="hidden" name="urun_id" value="${id}">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Kaynak</label>
                        <select name="source_location" id="sourceLocation" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
                            <option value="">Kaynak Seçin</option>
                            <optgroup label="Depolar">
                                ${depolarData.depolar.map(depo => 
                                    `<option value="depo_${depo.id}">${depo.ad}</option>`
                                ).join('')}
                            </optgroup>
                            <optgroup label="Mağazalar">
                                ${magazalarData.magazalar.map(magaza => 
                                    `<option value="magaza_${magaza.id}">${magaza.ad}</option>`
                                ).join('')}
                            </optgroup>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Hedef</label>
                        <select name="target_location" id="targetLocation" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
                            <option value="">Hedef Seçin</option>
                            <optgroup label="Depolar">
                                ${depolarData.depolar.map(depo => 
                                    `<option value="depo_${depo.id}">${depo.ad}</option>`
                                ).join('')}
                            </optgroup>
                            <optgroup label="Mağazalar">
                                ${magazalarData.magazalar.map(magaza => 
                                    `<option value="magaza_${magaza.id}">${magaza.ad}</option>`
                                ).join('')}
                            </optgroup>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Miktar</label>
                        <input type="number" name="miktar" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" 
                               min="0.01" step="0.01" required>
                    </div>
                </form>
            `,
            didOpen: () => {
                // Select elementlerini al
                sourceSelect = document.getElementById('sourceLocation');
                targetSelect = document.getElementById('targetLocation');

                // Select değişikliklerini dinle
                sourceSelect.addEventListener('change', validateLocations);
                targetSelect.addEventListener('change', validateLocations);

                function validateLocations() {
                    if (sourceSelect.value && targetSelect.value) {
                        if (sourceSelect.value === targetSelect.value) {
                            Swal.showValidationMessage('Kaynak ve hedef aynı olamaz');
                        } else {
                            Swal.resetValidationMessage();
                        }
                    }
                }
            },
            showCancelButton: true,
            confirmButtonText: 'Transfer Et',
            cancelButtonText: 'İptal',
            showLoaderOnConfirm: true,
            preConfirm: () => {
                const form = document.getElementById('transferForm');
                const formData = new FormData(form);

                // Kaynak ve hedef kontrolü
                if (formData.get('source_location') === formData.get('target_location')) {
                    Swal.showValidationMessage('Kaynak ve hedef aynı olamaz');
                    return false;
                }

                return fetch('api/transfer_stock.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        throw new Error(data.message || 'Transfer sırasında bir hata oluştu');
                    }
                    return data;
                });
            }
        }).then((result) => {
            if (result.isConfirmed) {
                // Başarı bildirimi
                Swal.fire({
                    icon: 'success',
                    title: 'Transfer Başarılı!',
                    text: 'Stok transferi başarıyla tamamlandı',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true,
                    didOpen: (toast) => {
                        toast.addEventListener('mouseenter', Swal.stopTimer)
                        toast.addEventListener('mouseleave', Swal.resumeTimer)
                    }
                });
                
                // Tabloyu güncelle
                updateTableAjax();
            }
        }).catch(error => {
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
}