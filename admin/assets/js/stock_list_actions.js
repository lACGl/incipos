import { showLoadingToast, closeLoadingToast, showErrorToast,  FormatUtils, LoadingUtils, APIUtils, DOMUtils, loadSelectOptions, initializeNewEntryModal  } from './utils.js';
import { updateTableAjax, updateStock } from './stock_list.js';


/**
 * Ürün detaylarını API'den getirir.
 */
export async function getProductDetails(id) {
    try {
        const response = await fetch(`api/get_product_details.php?id=${id}`);
        const data = await response.json();

        if (!data.success) {
            throw new Error('Ürün detayları alınamadı.');
        }

        return data.product;
    } catch (error) {
        console.error('Ürün detayları yüklenirken hata:', error);
        return null;
    }
}


/**
 * Genel yardımcı fonksiyon: Veri içeren bir satırı döner
 */
function getRowData(id) {
    const row = document.querySelector(`tr[data-product*='"id":${id}']`);
    return row ? JSON.parse(row.getAttribute('data-product') || '{}') : null;
}

/**
 * Genel yardımcı fonksiyon: Ürün detaylarını API'den alır
 */
async function fetchProductDetails(id) {
    const response = await fetch(`api/get_product_details.php?id=${id}`);
    return await response.json();
}

/**
 * Genel yardımcı fonksiyon: Popup'ı başlatır
 */
function initializePopup(id, callback) {
    const popup = document.getElementById(id);
    if (popup) {
        const form = popup.querySelector('form');
        if (form) callback(form, popup);
    }
}

/**
 * Genel yardımcı fonksiyon: Hata mesajı gösterir
 */
function showError(message) {
    console.error('Hata:', message);
    showErrorToast('Bir hata oluştu: ' + message);
}

/**
 * Ürün düzenleme modali gösteren fonksiyon
 */
export function editProduct(id, event) {
    event?.stopPropagation();
    const row = document.querySelector(`tr[data-product*='"id":${id}']`);
    if (row) {
        const productData = JSON.parse(row.dataset.product);
        showEditModal(productData);
    }
}

/**
 * Düzenleme modali gösterme fonksiyonu
 */
export async function showEditModal(productId) {
    const id = typeof productId === 'object' ? productId.id : productId;
    
    try {
        const response = await fetch(`api/get_product_details.php?id=${id}`);
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.message || 'Ürün detayları alınamadı');
        }

        const product = data.product;
        
        if (!product) {
            throw new Error('Ürün detayları bulunamadı');
        }

        // Modal oluştur
        const result = await Swal.fire({
            title: 'Ürün Düzenle',
            width: '50%',
            html: `
<div class="form-container">
    <div class="form-group">
        <label for="kod" class="form-label">Kod*</label>
        <input type="text" id="kod" name="kod" class="form-input" value="${product.kod || ''}" placeholder="Ürün Kodu">
    </div>
    <div class="form-group">
        <label for="barkod" class="form-label">Barkod*</label>
        <input type="text" id="barkod" name="barkod" class="form-input" value="${product.barkod || ''}" placeholder="Barkod">
    </div>
    <div class="form-group">
        <label for="ad" class="form-label">Ürün Adı*</label>
        <input type="text" id="ad" name="ad" class="form-input" value="${product.ad || ''}" placeholder="Ürün Adı">
    </div>
    <div class="form-group">
        <label for="web_id" class="form-label">Web ID</label>
        <input type="number" id="web_id" name="web_id" class="form-input" value="${product.web_id || ''}" placeholder="Web ID">
    </div>
    <div class="form-group">
        <label for="alis_fiyati" class="form-label">Alış Fiyatı*</label>
        <input type="number" step="0.01" id="alis_fiyati" name="alis_fiyati" class="form-input" value="${product.alis_fiyati || ''}" placeholder="0.00">
    </div>
    <div class="form-group">
        <label for="satis_fiyati" class="form-label">Satış Fiyatı*</label>
        <input type="number" step="0.01" id="satis_fiyati" name="satis_fiyati" class="form-input" value="${product.satis_fiyati || ''}" placeholder="0.00">
        <div class="kar-marji-note text-sm text-green-600 mt-1"></div>
    </div>
    <div class="form-group">
        <label for="kdv_orani" class="form-label">KDV Oranı (%)*</label>
        <select id="kdv_orani" name="kdv_orani" class="form-input">
            <option value="" ${product.kdv_orani === null ? 'selected' : ''}>Seçiniz</option>
            ${[0, 10, 20].map(oran => 
                `<option value="${oran}" ${Number(product.kdv_orani) === oran ? 'selected' : ''}>${oran}%</option>`
            ).join('')}
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
        <input type="number" id="yil" name="yil" class="form-input" value="${product.yil || new Date().getFullYear()}">
    </div>
    <div class="form-group">
        <label for="durum" class="form-label">Durum</label>
        <select id="durum" name="durum" class="form-input">
            <option value="aktif" ${product.durum === 'aktif' ? 'selected' : ''}>Aktif</option>
            <option value="pasif" ${product.durum === 'pasif' ? 'selected' : ''}>Pasif</option>
        </select>
    </div>
    <div class="form-group">
        <label for="resim" class="form-label">Resim Yükle</label>
        <input type="file" id="resim" name="resim" class="form-input">
    </div>
</div>
        `,
            showCancelButton: true,
            confirmButtonText: 'Güncelle',
            cancelButtonText: 'İptal',
            // Modal açıldığında
            didOpen: async () => {
                // Seçenekleri yükle
                await loadSelectOptions('departman', 'api/get_departmanlar.php');
                await loadSelectOptions('birim', 'api/get_birimler.php');
                await loadSelectOptions('ana_grup', 'api/get_ana_gruplar.php');
                await loadSelectOptions('alt_grup', 'api/get_alt_gruplar.php');

                // Yeni öğe ekleme modallarını başlat
                initializeNewEntryModal('departman', 'api/add_departman.php');
                initializeNewEntryModal('birim', 'api/add_birim.php');
                initializeNewEntryModal('ana_grup', 'api/add_ana_grup.php');
                initializeNewEntryModal('alt_grup', 'api/add_alt_grup.php');

                // Select öğelerini doldur ve değerleri ata
                await fillSelectAndSetValue('departman', product.departman);
                await fillSelectAndSetValue('birim', product.birim);
                await fillSelectAndSetValue('ana_grup', product.ana_grup);
                await fillSelectAndSetValue('alt_grup', product.alt_grup);
            },
            // Güncelle butonuna basıldığında
preConfirm: async () => {
    try {
        const formData = new FormData();
        formData.append('product_id', id);
        
        // Form alanlarını doğru sütun isimleriyle eşleştir
        const fieldMappings = {
            'kod': 'kod',
            'barkod': 'barkod',
            'ad': 'ad',
            'web_id': 'web_id',
            'alis_fiyati': 'alis_fiyati',
            'satis_fiyati': 'satis_fiyati',
            'kdv_orani': 'kdv_orani',
            'departman': 'departman_id',
            'birim': 'birim_id',
            'ana_grup': 'ana_grup_id',
            'alt_grup': 'alt_grup_id',
            'yil': 'yil',
            'durum': 'durum'
        };

        // Form verilerini doğru sütun isimleriyle topla
        Object.entries(fieldMappings).forEach(([formField, dbField]) => {
            const element = document.getElementById(formField);
            if (element) {
                formData.append(dbField, element.value);
            }
        });

        // Resim dosyası varsa ekle
        const resimInput = document.getElementById('resim');
        if (resimInput && resimInput.files[0]) {
            formData.append('resim', resimInput.files[0]);
        }

        // Güncelleme isteğini gönder
        const response = await fetch('api/update_product.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();
        if (!result.success) {
            throw new Error(result.message || 'Güncelleme sırasında bir hata oluştu');
        }

        return result;
    } catch (error) {
        Swal.showValidationMessage(error.message);
        return false;
    }
}
});

// Modal sonucu işle
if (result.isConfirmed) {
    await Swal.fire({
        icon: 'success',
        title: 'Başarılı!',
        text: 'Ürün başarıyla güncellendi',
        showConfirmButton: false,
        timer: 1500
    });
    // Tabloyu güncelle
    updateTableAjax();
}
} catch (error) {
    console.error('Ürün düzenleme hatası:', error);
    Swal.fire({
        icon: 'error',
        title: 'Hata!',
        text: error.message
    });
}
}
/**
 * Düzenleme modali gösterme fonksiyonu
 */
const fillSelectAndSetValue = async (selectId, value) => {
    const select = document.getElementById(selectId);

    if (!select) {
        console.error(`${selectId} için select öğesi bulunamadı.`);
        return;
    }


    try {
        const endpoints = {
            departman: 'api/get_departmanlar.php',
            birim: 'api/get_birimler.php',
            ana_grup: 'api/get_ana_gruplar.php',
            alt_grup: 'api/get_alt_gruplar.php'
        };

        const response = await fetch(endpoints[selectId]);
        const data = await response.json();

        if (!data.success) {
            throw new Error(`${selectId} için API isteği başarısız.`);
        }


        // Seçenekleri temizle ve yeniden oluştur
        select.innerHTML = `
            <option value="">Seçiniz</option>
            <option value="add_new" class="font-semibold text-blue-600">+ Yeni Ekle</option>
        `;

        let matchedId = null;

        data.data.forEach(item => {
            select.add(new Option(item.ad, String(item.id)));

            // `ad` ile eşleştirme yap
            if (String(item.ad) === String(value)) {
                matchedId = String(item.id);
            }
        });

        // Eşleşen değeri ata, eşleşme yoksa boş bırak
        if (matchedId) {
            select.value = matchedId;
        } else {
            select.value = '';
            console.warn(`${selectId} için eşleşen değer bulunamadı, değer atanmadı.`);
        }

    } catch (error) {
        console.error(`${selectId} yüklenirken hata oluştu:`, error);
        select.innerHTML = `<option value="">Hata oluştu</option>`;
    }
};

function createInput(id, label, value = '', type = 'text', placeholder = '') {
    return `
        <div class="form-group">
            <label for="${id}" class="form-label">${label}</label>
            <input type="${type}" id="${id}" class="form-input" value="${value}" placeholder="${placeholder}">
        </div>
    `;
}

function createSelect(id, label, options = [], selectedValue = '') {
    const optionsHtml = options.length 
        ? options.map(opt => `<option value="${opt}" ${opt == selectedValue ? 'selected' : ''}>${opt}%</option>`).join('')
        : '<option value="">Seçiniz</option><option value="add_new">+ Yeni Ekle</option>';

    return `
        <div class="form-group">
            <label for="${id}" class="form-label">${label}</label>
            <select id="${id}" class="form-input">
                ${optionsHtml}
            </select>
        </div>
    `;
}

/**
 * Formu ürün verisiyle dolduran yardımcı fonksiyon
 */
function fillFormWithProductData(form, data) {
    const productData = data.product;
    
    form.querySelectorAll('input, select').forEach(input => {
        const fieldName = input.name;
        if (!fieldName) return;
        
        if (input.tagName === 'SELECT') {
            fillSelectOptionsForField(input, fieldName, data, productData);
        } else if (productData[fieldName] !== undefined) {
            input.value = productData[fieldName] || '';
        }
    });
    
    initializeKarMarji(form);
}

/**
 * Kar marjı hesaplama ve gösterme fonksiyonunu başlatır
 */
export function initializeKarMarji(form) {
    const alisFiyatiInput = form.querySelector('#alis_fiyati');
    const satisFiyatiInput = form.querySelector('#satis_fiyati');
    const karMarjiNote = form.querySelector('.kar-marji-note');

    if (!alisFiyatiInput || !satisFiyatiInput || !karMarjiNote) {
        console.error("Kar marjı için gerekli inputlar veya not alanı bulunamadı.");
        return;
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

    alisFiyatiInput.addEventListener('input', updateKarMarji);
    satisFiyatiInput.addEventListener('input', updateKarMarji);
    updateKarMarji();
}


// addStock fonksiyonu
window.addStock = async function(id, event) {
    event?.stopPropagation();

    // Önceki popup'ları kapat
    document.getElementById('updatePopup') && (document.getElementById('updatePopup').style.display = 'none');

    try {
        // Mağazaları ve depoları al
        const [magazalarResponse, depolarResponse] = await Promise.all([
            fetch('api/get_magazalar.php'),
            fetch('api/get_depolar.php')
        ]);

        const magazalarData = await magazalarResponse.json();
        const depolarData = await depolarResponse.json();

        if (!magazalarData.success || !depolarData.success) {
            throw new Error('Lokasyon bilgileri alınamadı');
        }

        // Tüm lokasyonları birleştir
        const lokasyonlar = [
            // Önce depoları ekle
            ...depolarData.depolar.map(depo => ({
                id: `depo_${depo.id}`,
                ad: `${depo.ad} (Depo)`
            })),
            // Sonra mağazaları ekle
            ...magazalarData.magazalar.map(magaza => ({
                id: `magaza_${magaza.id}`,
                ad: magaza.ad
            }))
        ];

        const result = await Swal.fire({
            title: 'Stok Güncelle',
html: `
    <div class="grid grid-cols-1 gap-4">
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Lokasyon</label>
            <select id="stockLocation" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                <option value="">Lokasyon Seçin</option>
                <optgroup label="Depolar" class="font-bold">
                    ${depolarData.depolar.map(depo => 
                        `<option value="depo_${depo.id}">${depo.ad}</option>`
                    ).join('')}
                </optgroup>
                <optgroup label="Mağazalar" class="font-bold">
                    ${magazalarData.magazalar.map(magaza => 
                        `<option value="magaza_${magaza.id}">${magaza.ad}</option>`
                    ).join('')}
                </optgroup>
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
                const amount = document.getElementById('stockAmount').value;

                if (!location) {
                    Swal.showValidationMessage('Lütfen lokasyon seçin');
                    return false;
                }
                if (!amount || amount <= 0) {
                    Swal.showValidationMessage('Lütfen geçerli bir miktar girin');
                    return false;
                }

                return {
                    location,
                    amount,
                    operation: document.getElementById('stockOperation').value
                };
            }
        });

        if (result.isConfirmed) {
            const [locationType, locationId] = result.value.location.split('_');
            updateStock(
                id,
                result.value.amount,
                result.value.operation,
                locationType,
                locationType === 'depo' ? null : locationId
            );
        }

    } catch (error) {
        console.error('Veri yükleme hatası:', error);
        Swal.fire({
            icon: 'error',
            title: 'Hata!',
            text: 'Lokasyon bilgileri yüklenirken bir hata oluştu.'
        });
    }
};

/**
 * Detay modalını gösteren fonksiyon
 */
export function showDetailsModal(product) {
    const detailsHtml = generateProductDetailsHtml(product);

    Swal.fire({
        title: product.ad,
        html: detailsHtml,
        width: '900px',
        showConfirmButton: false,
        showCloseButton: true
    });

    fetchProductHistory(product.id, detailsHtml);
}

/**
 * Tekil detay satırı oluşturan yardımcı fonksiyon
 */
function generateDetailRow(label, value) {
    return `
        <div class="font-bold">${label}:</div>
        <div>${value || '-'}</div>
    `;
}

/**
 * İndirimli fiyat bölümünü oluşturan yardımcı fonksiyon
 */
function generateDiscountSection(product) {
    return `
        <div class="mt-4 p-2 bg-yellow-50 rounded">
            <div class="font-bold">İndirimli Fiyat:</div>
            <div>₺${parseFloat(product.indirimli_fiyat).toFixed(2)}</div>
            <div class="text-sm text-gray-600">
                İndirim Tarihleri: ${product.indirim_baslangic_tarihi || '-'} - ${product.indirim_bitis_tarihi || '-'}
            </div>
        </div>
    `;
}

/**
 * Ürün geçmişini getirip mevcut modal içeriğine ekleyen fonksiyon
 */
function fetchProductHistory(productId, initialHtml) {
    fetch(`api/get_product_history.php?id=${productId}`)
        .then(response => response.json())
        .then(data => {
            if (!data.success) return;

            let extraHtml = '';
            if (data.fiyat_gecmisi?.length) {
                extraHtml += generatePriceHistoryHtml(data.fiyat_gecmisi);
            }
            if (data.stok_hareketleri?.length) {
                extraHtml += generateStockHistoryHtml(data.stok_hareketleri);
            }

            if (extraHtml) {
                Swal.update({ html: initialHtml + extraHtml });
            }
        })
        .catch(error => {
            console.error('Tarihçe yüklenirken hata:', error);
        });
}

/**
 * Fiyat geçmişi HTML'ini oluşturan yardımcı fonksiyon
 */
function generatePriceHistoryHtml(priceHistory) {
    return `
        <div class="mt-4 border-t pt-4">
            <h3 class="font-bold mb-2">Fiyat Geçmişi</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr class="bg-gray-50">
                            ${generateTableHeaders(['Tarih', 'İşlem', 'Eski Fiyat', 'Yeni Fiyat', 'Detay'])}
                        </tr>
                    </thead>
                    <tbody>
                        ${priceHistory.map(fiyat => `
                            <tr class="border-b">
                                <td class="px-2 py-1 text-xs">${new Date(fiyat.tarih).toLocaleString('tr-TR')}</td>
                                <td class="px-2 py-1 text-xs">${fiyat.islem_tipi === 'alis' ? 'Alış' : 'Güncelleme'}</td>
                                <td class="px-2 py-1 text-xs text-right">${fiyat.eski_fiyat ? `₺${parseFloat(fiyat.eski_fiyat).toFixed(2)}` : '-'}</td>
                                <td class="px-2 py-1 text-xs text-right">₺${parseFloat(fiyat.yeni_fiyat).toFixed(2)}</td>
                                <td class="px-2 py-1 text-xs">${generateDetailInfo(fiyat)}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        </div>
    `;
}

/**
 * Stok hareketleri HTML'ini oluşturan yardımcı fonksiyon
 */
function generateStockHistoryHtml(stockMovements) {
    return `
        <div class="mt-4 border-t pt-4">
            <h3 class="font-bold mb-2">Stok Hareketleri</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr class="bg-gray-50">
                            ${generateTableHeaders(['Tarih', 'İşlem', 'Miktar', 'Alış Fiyatı', 'Satış Fiyatı', 'Mağaza', 'Açıklama'])}
                        </tr>
                    </thead>
                    <tbody>
                        ${stockMovements.map(hareket => `
                            <tr class="border-b">
                                <td class="px-2 py-1 text-xs">${new Date(hareket.tarih).toLocaleString('tr-TR')}</td>
                                <td class="px-2 py-1 text-xs">${hareket.hareket_tipi === 'giris' ? '<span class="text-green-600">Giriş</span>' : '<span class="text-red-600">Çıkış</span>'}</td>
                                <td class="px-2 py-1 text-xs text-right">${parseFloat(hareket.miktar).toFixed(2)}</td>
                                <td class="px-2 py-1 text-xs text-right">${hareket.maliyet ? `₺${parseFloat(hareket.maliyet).toFixed(2)}` : '-'}</td>
                                <td class="px-2 py-1 text-xs text-right">${hareket.satis_fiyati ? `₺${parseFloat(hareket.satis_fiyati).toFixed(2)}` : '-'}</td>
                                <td class="px-2 py-1 text-xs">${hareket.magaza_adi}</td>
                                <td class="px-2 py-1 text-xs">${hareket.aciklama}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        </div>
    `;
}

/**
 * Detay bilgisi oluşturan yardımcı fonksiyon
 */
function generateDetailInfo(fiyat) {
    return `
        ${fiyat.fatura_seri ? `Fatura: ${fiyat.fatura_seri}${fiyat.fatura_no} (${fiyat.tedarikci_adi})` : ''}
        ${fiyat.kullanici_adi ? `<br>Kullanıcı: ${fiyat.kullanici_adi}` : ''}
    `;
}
/**
 * Detay modalını gösteren fonksiyon
 */
async function showDetails(id) {
    try {
        const [details, stock] = await fetchProductAndStockDetails(id);

        if (!details.success) {
            throw new Error('Ürün detayları alınamadı');
        }

        const product = details.product;
        const html = generateProductDetailsHtml(product, stock);

        Swal.fire({
            title: product.ad,
            html: html,
            width: '1000px',
            showCloseButton: true,
            showConfirmButton: false
        });
    } catch (error) {
        handleFetchError(error, 'Ürün detayları yüklenirken bir hata oluştu.');
    }
}

/**
 * Ürün ve stok detaylarını getiren yardımcı fonksiyon
 */
async function fetchProductAndStockDetails(id) {
    const [detailsResponse, stockResponse] = await Promise.all([
        fetch(`get_product_details.php?id=${id}`),
        fetch(`get_product_stock_details.php?id=${id}`)
    ]);

    return [await detailsResponse.json(), await stockResponse.json()];
}


/**
 * Ürün detaylarını ve stok bilgilerini içeren HTML oluşturan fonksiyon
 */
export function generateProductDetailsHtml(product, stock) {
	    stock = stock || { hareketler: [] };

    return `
        <div class="text-left space-y-4">
            <div class="grid grid-cols-2 gap-4">
                ${generateDetailRow('Barkod', product.barkod)}
                ${generateDetailRow('Ürün Adı', product.ad)}
                ${generateDetailRow('Stok', product.stok_miktari || '0')}
                ${generateDetailRow('Alış Fiyatı', `${parseFloat(product.alis_fiyati).toFixed(2)}₺`)}
                ${generateDetailRow('Satış Fiyatı', `${parseFloat(product.satis_fiyati).toFixed(2)}₺`)}
                ${generateDetailRow('KDV Oranı', `%${product.kdv_orani}`)}
                ${generateDetailRow('Departman', product.departman)}
                ${generateDetailRow('Ana Grup', product.ana_grup)}
                ${generateDetailRow('Alt Grup', product.alt_grup)}
            </div>
<!-- ${generateStockMovementsHtml(stock.hareketler)}
            ${generateStockDistributionHtml(stock)} -->
        </div>
    `;
}

/**
 * Stok hareketlerini gösteren HTML oluşturan yardımcı fonksiyon
 */
export function generateStockMovementsHtml(hareketler) {
	    hareketler = hareketler || [];


    return `
        <div class="mt-6">
            <h3 class="text-lg font-medium mb-3">Stok Hareketleri</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        ${generateTableHeaders(['Tarih', 'İşlem', 'Miktar', 'Alış Fiyatı', 'Satış Fiyatı', 'Lokasyon', 'Açıklama'])}
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        ${hareketler?.map(hareket => `
                            <tr>
                                <td class="px-4 py-2 text-sm">${new Date(hareket.tarih).toLocaleString('tr-TR')}</td>
                                <td class="px-4 py-2 text-center">
                                    <span class="px-2 py-1 text-xs rounded-full ${hareket.hareket_tipi === 'giris' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
                                        ${hareket.hareket_tipi === 'giris' ? 'Giriş' : 'Çıkış'}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-right">${hareket.miktar}</td>
                                <td class="px-4 py-2 text-right">${hareket.maliyet ? `${parseFloat(hareket.maliyet).toFixed(2)}₺` : '-'}</td>
                                <td class="px-4 py-2 text-right">${hareket.satis_fiyati ? `${parseFloat(hareket.satis_fiyati).toFixed(2)}₺` : '-'}</td>
                                <td class="px-4 py-2">${hareket.magaza_adi || 'Ana Depo'}</td>
                                <td class="px-4 py-2">${hareket.aciklama || '-'}</td>
                            </tr>
                        `).join('') || `<tr><td colspan="7" class="px-4 py-2 text-center text-gray-500">Stok hareketi bulunamadı</td></tr>`}
                    </tbody>
                </table>
            </div>
        </div>
    `;
}

/**
 * Stok dağılımını gösteren HTML oluşturan yardımcı fonksiyon
 */
function generateStockDistributionHtml(stock) {
    return `
        <div class="mt-6">
            <h3 class="text-lg font-medium mb-3">Stok Dağılımı</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left">Lokasyon</th>
                            <th class="px-4 py-2 text-right">Miktar</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        ${stock.distribution?.map(item => `
                            <tr>
                                <td class="px-4 py-2">${item.lokasyon || 'Ana Depo'}</td>
                                <td class="px-4 py-2 text-right">${item.miktar || 0}</td>
                            </tr>
                        `).join('') || `<tr><td colspan="2" class="px-4 py-2 text-center text-gray-500">Stok dağılımı bulunamadı</td></tr>`}
                    </tbody>
                </table>
            </div>
        </div>
    `;
}


/**
 * Tablo başlıklarını oluşturan yardımcı fonksiyon
 */
function generateTableHeaders(headers) {
    return headers.map(header => `<th class="px-4 py-2 text-xs font-medium text-gray-500 uppercase">${header}</th>`).join('');
}

/**
 * Hata işleme fonksiyonu
 */
function handleFetchError(error, message) {
    console.error(message, error);
    Swal.fire({
        icon: 'error',
        title: 'Hata!',
        text: message
    });
}
/**
 * Stok detaylarını gösteren fonksiyon
 */
window.showStockDetails = async function(id, event) {
    event?.preventDefault();
    event?.stopPropagation();

    closeUpdatePopup();

    try {
        // İki API'yi paralel olarak çağır
        const [productData, stockData] = await Promise.all([
            fetchProductDetails(id),
            fetchStockDetails(id)
        ]);

        console.log("Ürün Detayları:", productData); // Debug için
        console.log("Stok Detayları:", stockData);   // Debug için

        if (!productData.success || !stockData.success) {
            throw new Error('Veriler alınamadı');
        }

        await Swal.fire({
            title: 'Stok Detayları',
            html: `
                <div class="text-left space-y-6">
                    ${generateProductInfoHtml(productData.product)}
                    ${generateDepotStockHtml(stockData.depo_stok)}
                    ${generateStoreStocksHtml(stockData.magaza_stoklari)}
                    ${generateTotalStockHtml(stockData.toplam_stok)}
                    ${generateStockMovementsHtml(stockData.hareketler)}
                </div>
            `,
            width: '60%',
            showCloseButton: true,
            showConfirmButton: false,
            customClass: { container: 'stock-details-modal' },
            stopKeydownPropagation: true
        });

    } catch (error) {
        handleFetchError(error, 'Detaylar alınırken bir hata oluştu.');
    }
};

/**
 * Stok detaylarını getiren yardımcı fonksiyon
 */
async function fetchStockDetails(id) {
    const response = await fetch(`api/get_stock_details.php?id=${id}`);
    return await response.json();
}

/**
 * Modalı kapatan yardımcı fonksiyon
 */
function closeUpdatePopup() {
    const updatePopup = document.getElementById('updatePopup');
    if (updatePopup) {
        updatePopup.style.display = 'none';
    }
}

/**
 * Stok detayları HTML'sini oluşturan yardımcı fonksiyon
 */
function generateStockDetailsHtml(data) {
    return `
        <div class="text-left space-y-6" onclick="event.stopPropagation()">
            ${generateDepotStockHtml(data.depo_stok)}
            ${generateStoreStocksHtml(data.magaza_stoklari, data.toplam_stok.magaza)}
            ${generateTotalStockHtml(data.toplam_stok.genel_toplam)}
        </div>
    `;
}

function generateProductInfoHtml(product) {
    return `
        <div class="bg-gray-50 p-4 rounded-lg">
            <h3 class="font-bold text-lg mb-2">Ürün Bilgileri</h3>
            <div class="grid grid-cols-2 gap-2">
                <div class="font-semibold">Barkod:</div>
                <div>${product.barkod || '-'}</div>
                <div class="font-semibold">Ürün Adı:</div>
                <div>${product.ad || '-'}</div>
                <div class="font-semibold">KDV Oranı:</div>
                <div>%${product.kdv_orani || '0'}</div>
            </div>
        </div>
    `;
}

/**
 * Depo stok bilgisi HTML'sini oluşturan yardımcı fonksiyon
 */
function generateDepotStockHtml(depoStok) {
    return `
        <div class="bg-blue-50 p-4 rounded-lg">
            <h3 class="font-bold text-lg mb-2">Depo Stok</h3>
            <div class="grid grid-cols-2 gap-2">
                <div class="font-semibold">Miktar:</div>
                <div>${depoStok?.stok_miktari || 0}</div>
                <div class="font-semibold">Son Güncelleme:</div>
                <div>${depoStok?.son_guncelleme ? new Date(depoStok.son_guncelleme).toLocaleString('tr-TR') : '-'}</div>
            </div>
        </div>
    `;
}


/**
 * Mağaza stok bilgisi HTML'sini oluşturan yardımcı fonksiyon
 */
function generateStoreStocksHtml(magazaStoklari) {
    // Tarihe göre azalan sırada sırala (en yeni güncelleme üstte)
    const sortedMagazaStoklari = Array.isArray(magazaStoklari) 
        ? magazaStoklari.sort((a, b) => {
            const dateA = new Date(a.son_guncelleme || 0);
            const dateB = new Date(b.son_guncelleme || 0);
            return dateB - dateA;
        }) 
        : [];

    return `
        <div class="bg-green-50 p-4 rounded-lg">
            <h3 class="font-bold text-lg mb-2">Mağaza Stokları</h3>
            <table class="min-w-full">
                <thead>
                    <tr>
                        <th class="px-4 py-2 text-left">Mağaza</th>
                        <th class="px-4 py-2 text-right">Stok</th>
                        <th class="px-4 py-2 text-right">Satış Fiyatı</th>
                        <th class="px-4 py-2 text-right">Son Güncelleme</th>
                    </tr>
                </thead>
                <tbody>
                    ${sortedMagazaStoklari.length ? sortedMagazaStoklari.map(magaza => `
                        <tr>
                            <td class="px-4 py-2">${magaza.magaza_adi || '-'}</td>
                            <td class="px-4 py-2 text-right">${magaza.stok_miktari || 0}</td>
                            <td class="px-4 py-2 text-right">₺${parseFloat(magaza.satis_fiyati || 0).toFixed(2)}</td>
                            <td class="px-4 py-2 text-right">${
                                magaza.son_guncelleme ? 
                                new Date(magaza.son_guncelleme).toLocaleString('tr-TR') : 
                                '-'
                            }</td>
                        </tr>
                    `).join('') : '<tr><td colspan="4" class="text-center">Mağaza stoku bulunmuyor</td></tr>'}
                </tbody>
            </table>
        </div>
    `;
}
/**
 * Genel toplam stok bilgisi HTML'sini oluşturan yardımcı fonksiyon
 */
function generateTotalStockHtml(totalStock) {
    return `
        <div class="bg-purple-50 p-4 rounded-lg">
            <h3 class="font-bold text-lg mb-2">Genel Toplam</h3>
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <div class="font-semibold">Depo:</div>
                    <div>${totalStock.depo || 0}</div>
                </div>
                <div>
                    <div class="font-semibold">Mağaza:</div>
                    <div>${totalStock.magaza || 0}</div>
                </div>
                <div>
                    <div class="font-semibold">Genel Toplam:</div>
                    <div>${totalStock.genel_toplam || 0}</div>
                </div>
            </div>
        </div>
    `;
}

/**
 * Modal event listener'larını ekleyen yardımcı fonksiyon
 */
function attachModalEventListeners() {
    const modalContent = document.querySelector('.stock-details-modal');
    modalContent?.addEventListener('click', (e) => e.stopPropagation());

    ['departman', 'birim', 'ana_grup', 'alt_grup'].forEach(id => {
        document.getElementById(id)?.addEventListener('change', handleOptionChange);
    });
}

/**
 * Fiyat geçmişi detaylarını gösteren fonksiyon
 */
export async function showPriceHistory(id, event) {
    event?.preventDefault();
    event?.stopPropagation();

    try {
        const data = await fetchData(`api/get_price_history.php?id=${id}`);

        Swal.fire({
            title: 'Fiyat Geçmişi',
            html: generatePriceHistoryTable(data.price_history),
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
        handleFetchError(error, 'Fiyat geçmişi alınırken bir hata oluştu.');
    }
}

/**
 * Stok transfer modalını gösteren fonksiyon
 */
export async function transferProduct(id, event) {
    event?.preventDefault();
    event?.stopPropagation();

    try {
        // Ürün detaylarını al
        const productResponse = await fetch(`api/get_product_details.php?id=${id}`);
        const productData = await productResponse.json();
        
        if (!productData.success || !productData.product) {
            throw new Error('Ürün bilgileri alınamadı');
        }

        const product = productData.product;

        // Mağaza ve depo bilgilerini al
        const [magazalarResponse, depolarResponse] = await Promise.all([
            fetch('api/get_magazalar.php'),
            fetch('api/get_depolar.php')
        ]);

        const magazalarData = await magazalarResponse.json();
        const depolarData = await depolarResponse.json();

        if (!magazalarData.success || !depolarData.success) {
            throw new Error('Lokasyon bilgileri alınamadı');
        }

        // Transfer modalını göster
        const result = await Swal.fire({
            title: 'Stok Transfer',
            html: `
                <div class="grid grid-cols-1 gap-4">
                    <div class="mb-2">
                        <p class="text-sm text-gray-600">Ürün: <span class="font-medium">${product.ad}</span></p>
                        <p class="text-sm text-gray-600">Barkod: ${product.barkod}</p>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Kaynak</label>
                        <select id="sourceLocation" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            <option value="">Kaynak Seçin</option>
                            <optgroup label="Depolar" class="font-bold">
                                ${depolarData.depolar.map(depo => 
                                    `<option value="depo_${depo.id}" data-name="${depo.ad}">${depo.ad}</option>`
                                ).join('')}
                            </optgroup>
                            <optgroup label="Mağazalar" class="font-bold">
                                ${magazalarData.magazalar.map(magaza => 
                                    `<option value="magaza_${magaza.id}" data-name="${magaza.ad}">${magaza.ad}</option>`
                                ).join('')}
                            </optgroup>
                        </select>
                        <div id="sourceStockInfo" class="mt-1 text-sm text-green-600 hidden">
                            Mevcut stok: <span id="sourceStockAmount">0</span>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Hedef</label>
                        <select id="targetLocation" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            <option value="">Hedef Seçin</option>
                            <optgroup label="Depolar" class="font-bold">
                                ${depolarData.depolar.map(depo => 
                                    `<option value="depo_${depo.id}" data-name="${depo.ad}">${depo.ad}</option>`
                                ).join('')}
                            </optgroup>
                            <optgroup label="Mağazalar" class="font-bold">
                                ${magazalarData.magazalar.map(magaza => 
                                    `<option value="magaza_${magaza.id}" data-name="${magaza.ad}">${magaza.ad}</option>`
                                ).join('')}
                            </optgroup>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Miktar</label>
                        <input type="number" id="transferAmount" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" min="0.01" step="0.01" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Açıklama</label>
                        <textarea id="transferNote" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" rows="2" placeholder="Opsiyonel açıklama..."></textarea>
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Transfer Et',
            cancelButtonText: 'İptal',
            didOpen: () => {
                // Kaynak lokasyon değiştiğinde stok bilgisini al
                document.getElementById('sourceLocation').addEventListener('change', async (e) => {
                    const sourceValue = e.target.value;
                    if (!sourceValue) {
                        document.getElementById('sourceStockInfo').classList.add('hidden');
                        return;
                    }
                    
                    // Lokasyon bilgilerini ayır
                    const [locationType, locationId] = sourceValue.split('_');
                    
                    try {
                        // Stok bilgisini al
                        const stockResponse = await fetch(`api/get_stock_amount.php?product_id=${id}&location_type=${locationType}&location_id=${locationId}`);
                        const stockData = await stockResponse.json();
                        
                        if (stockData.success) {
                            const stockAmount = stockData.stock_amount || 0;
                            document.getElementById('sourceStockAmount').textContent = stockAmount;
                            document.getElementById('sourceStockInfo').classList.remove('hidden');
                            
                            // Miktar inputuna max değer ata
                            document.getElementById('transferAmount').setAttribute('max', stockAmount);
                        }
                    } catch (error) {
                        console.error('Stok bilgisi alınamadı:', error);
                    }
                });
                
                // Lokasyonlar değiştiğinde kontrol yap
                const validateLocations = () => {
                    const source = document.getElementById('sourceLocation').value;
                    const target = document.getElementById('targetLocation').value;
                    
                    if (source && target && source === target) {
                        Swal.showValidationMessage('Kaynak ve hedef aynı olamaz');
                    } else {
                        Swal.resetValidationMessage();
                    }
                };
                
                document.getElementById('sourceLocation').addEventListener('change', validateLocations);
                document.getElementById('targetLocation').addEventListener('change', validateLocations);
            },
            preConfirm: () => {
                // Form verilerini doğrula ve hazırla
                const sourceSelect = document.getElementById('sourceLocation');
                const targetSelect = document.getElementById('targetLocation');
                const amount = document.getElementById('transferAmount').value;
                const note = document.getElementById('transferNote').value;
                
                // Validasyon
                if (!sourceSelect.value) {
                    Swal.showValidationMessage('Lütfen kaynak seçin');
                    return false;
                }
                
                if (!targetSelect.value) {
                    Swal.showValidationMessage('Lütfen hedef seçin');
                    return false;
                }
                
                if (sourceSelect.value === targetSelect.value) {
                    Swal.showValidationMessage('Kaynak ve hedef aynı olamaz');
                    return false;
                }
                
                if (!amount || parseFloat(amount) <= 0) {
                    Swal.showValidationMessage('Lütfen geçerli bir miktar girin');
                    return false;
                }
                
                const maxAmount = document.getElementById('transferAmount').getAttribute('max');
                if (maxAmount && parseFloat(amount) > parseFloat(maxAmount)) {
                    Swal.showValidationMessage(`Miktar, mevcut stok miktarından (${maxAmount}) fazla olamaz`);
                    return false;
                }
                
                // Lokasyon bilgilerini ayır
                const [sourceType, sourceId] = sourceSelect.value.split('_');
                const [targetType, targetId] = targetSelect.value.split('_');
                
                // Form verilerini hazırla
                return {
                    urun_id: id,
                    kaynak_tip: sourceType,
                    kaynak_id: sourceId,
                    hedef_tip: targetType, 
                    hedef_id: targetId,
                    miktar: parseFloat(amount),
                    aciklama: note
                };
            }
        });
        
        if (result.isConfirmed && result.value) {
            // Transfer işlemini gerçekleştir
            const transferResponse = await fetch('api/transfer_stock.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(result.value)
            });
            
            const transferData = await transferResponse.json();
            
            if (transferData.success) {
                // Başarılı işlem
                Swal.fire({
                    icon: 'success',
                    title: 'Başarılı!',
                    text: transferData.message || 'Stok transferi başarıyla tamamlandı',
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    // Tabloyu güncelle
                    updateTableAjax();
                });
            } else {
                // Hata durumu
                throw new Error(transferData.message || 'Transfer işlemi sırasında bir hata oluştu');
            }
        }
    } catch (error) {
        console.error('Transfer hatası:', error);
        Swal.fire({
            icon: 'error',
            title: 'Hata!',
            text: error.message
        });
    }
}

// Transfer verilerini doğrulama ve hazırlama
function validateAndPrepareTransferData(productId) {
    const sourceSelect = document.getElementById('sourceLocation');
    const targetSelect = document.getElementById('targetLocation');
    const amountInput = document.getElementById('transferAmount');
    const noteInput = document.getElementById('transferNote');
    
    // Lokasyonları kontrol et
    if (!validateLocations()) {
        return false;
    }
    
    const amount = parseFloat(amountInput.value);
    const maxAmount = parseFloat(amountInput.getAttribute('max') || 0);
    
    // Miktarı kontrol et
    if (!amount || amount <= 0) {
        Swal.showValidationMessage('Lütfen geçerli bir miktar girin');
        return false;
    }
    
    if (amount > maxAmount) {
        Swal.showValidationMessage(`Girilen miktar mevcut stoktan (${maxAmount}) fazla olamaz!`);
        return false;
    }
    
    // Kaynak ve hedef bilgilerini ayır
    const [sourceType, sourceId] = sourceSelect.value.split('_');
    const [targetType, targetId] = targetSelect.value.split('_');
    
    // Transfer verilerini hazırla
    return {
        product_id: productId,
        source_type: sourceType,
        source_id: sourceId,
        target_type: targetType,
        target_id: targetId,
        amount: amount,
        note: noteInput.value
    };
}

async function updateAvailableStock(productId, sourceLocation, stockData) {
    const availableStockInfo = document.getElementById('availableStockInfo');
    const availableStockAmount = document.getElementById('availableStockAmount');
    
    if (!sourceLocation) {
        availableStockInfo.style.display = 'none';
        return;
    }

    const [locationType, locationId] = sourceLocation.split('_');
    let stockAmount = 0;

    try {
        // Stok verileri daha önce alındıysa kullan, yoksa API'den al
        if (stockData) {
            if (locationType === 'depo') {
                stockAmount = stockData.depo_stok?.stok_miktari || 0;
            } else {
                const magazaStok = stockData.magaza_stoklari?.find(
                    item => item.magaza_id === locationId
                );
                stockAmount = magazaStok?.stok_miktari || 0;
            }
        } else {
            // API'den stok bilgisini al
            const response = await fetch(`api/get_location_stock.php?product_id=${productId}&location_type=${locationType}&location_id=${locationId}`);
            const data = await response.json();
            
            if (data.success) {
                stockAmount = data.stock_amount || 0;
            }
        }

        // Stok bilgisini göster
        availableStockAmount.textContent = stockAmount;
        availableStockInfo.style.display = 'block';
        
        // Miktar input alanını güncelle
        const amountInput = document.getElementById('transferAmount');
        amountInput.setAttribute('max', stockAmount);
        
        // Miktar değiştiğinde kontrol yap
        amountInput.addEventListener('input', () => {
            validateTransferAmount(amountInput, stockAmount);
        });
    } catch (error) {
        console.error('Stok bilgisi alınamadı:', error);
        availableStockInfo.style.display = 'none';
    }
}

function validateTransferAmount(input, maxAmount) {
    const amount = parseFloat(input.value) || 0;
    const errorElement = document.getElementById('amountError');
    
    if (amount <= 0) {
        errorElement.textContent = 'Miktar 0\'dan büyük olmalıdır!';
        errorElement.style.display = 'block';
        return false;
    } else if (amount > maxAmount) {
        errorElement.textContent = `Girilen miktar mevcut stoktan (${maxAmount}) fazla olamaz!`;
        errorElement.style.display = 'block';
        return false;
    } else {
        errorElement.style.display = 'none';
        return true;
    }
}

/**
 * API'den veri getiren yardımcı fonksiyon
 */
async function fetchData(url) {
    const response = await fetch(url);
    const data = await response.json();
    if (!data.success) {
        throw new Error(data.message || 'Veri yüklenemedi');
    }
    return data;
}

/**
 * Fiyat geçmişi tablosu oluşturan yardımcı fonksiyon
 */
function generatePriceHistoryTable(priceHistory) {
    return `
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
                    ${priceHistory.map(item => `
                        <tr class="border-b hover:bg-gray-50">
                            <td class="px-4 py-2">${new Date(item.tarih).toLocaleString('tr-TR')}</td>
                            <td class="px-4 py-2 text-blue-600">${item.islem_tipi || '-'}</td>
                            <td class="px-4 py-2 text-right">${formatCurrency(item.eski_fiyat)}</td>
                            <td class="px-4 py-2 text-right">${formatCurrency(item.yeni_fiyat)}</td>
                            <td class="px-4 py-2">${item.aciklama || '-'}</td>
                        </tr>
                    `).join('') || '<tr><td colspan="5" class="text-center text-gray-500">Fiyat geçmişi bulunamadı</td></tr>'}
                </tbody>
            </table>
        </div>
    `;
}

/**
 * Transfer formunu oluşturan yardımcı fonksiyon
 */
function generateTransferForm(id, magazalarData, depolarData, product) {
    return `
        <div class="space-y-4">
            <div>
                <h3 class="text-gray-700 font-semibold mb-2">Ürün: ${product.ad || ''}</h3>
                <p class="text-sm text-gray-500">Barkod: ${product.barkod || ''}</p>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Kaynak</label>
                <select id="sourceLocation" class="w-full p-2 border border-gray-300 rounded-md shadow-sm">
                    <option value="">Kaynak Seçin</option>
                    <optgroup label="Depolar">
                        ${depolarData.depolar.map(depo => `<option value="depo_${depo.id}">${depo.ad}</option>`).join('')}
                    </optgroup>
                    <optgroup label="Mağazalar">
                        ${magazalarData.magazalar.map(magaza => `<option value="magaza_${magaza.id}">${magaza.ad}</option>`).join('')}
                    </optgroup>
                </select>
                <div id="availableStockInfo" class="mt-1 text-sm text-green-600 hidden">
                    Mevcut Stok: <span id="availableStockAmount">0</span>
                </div>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Hedef</label>
                <select id="targetLocation" class="w-full p-2 border border-gray-300 rounded-md shadow-sm">
                    <option value="">Hedef Seçin</option>
                    <optgroup label="Depolar">
                        ${depolarData.depolar.map(depo => `<option value="depo_${depo.id}">${depo.ad}</option>`).join('')}
                    </optgroup>
                    <optgroup label="Mağazalar">
                        ${magazalarData.magazalar.map(magaza => `<option value="magaza_${magaza.id}">${magaza.ad}</option>`).join('')}
                    </optgroup>
                </select>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Miktar</label>
                <input type="number" id="transferAmount" class="w-full p-2 border border-gray-300 rounded-md shadow-sm" min="0.01" step="0.01" required>
                <div id="amountError" class="mt-1 text-sm text-red-600 hidden">
                    Girilen miktar mevcut stoktan fazla olamaz!
                </div>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Açıklama</label>
                <textarea id="transferNote" class="w-full p-2 border border-gray-300 rounded-md shadow-sm" rows="2"></textarea>
            </div>
        </div>
    `;
}

/**
 * Lokasyon select elementini oluşturan yardımcı fonksiyon
 */
function generateLocationSelect(label, id, magazalarData, depolarData) {
    return `
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">${label}</label>
            <select name="${id}" id="${id}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
                <option value="">${label} Seçin</option>
                <optgroup label="Depolar">
                    ${depolarData.depolar.map(depo => `<option value="depo_${depo.id}">${depo.ad}</option>`).join('')}
                </optgroup>
                <optgroup label="Mağazalar">
                    ${magazalarData.magazalar.map(magaza => `<option value="magaza_${magaza.id}">${magaza.ad}</option>`).join('')}
                </optgroup>
            </select>
        </div>
    `;
}

/**
 * Transfer formu event listener'larını ekleyen yardımcı fonksiyon
 */
function attachTransferFormEventListeners() {
    const sourceSelect = document.getElementById('sourceLocation');
    const targetSelect = document.getElementById('targetLocation');

    sourceSelect.addEventListener('change', validateLocations);
    targetSelect.addEventListener('change', validateLocations);
}

/**
 * Lokasyon seçimlerini doğrulayan yardımcı fonksiyon
 */
function validateLocations() {
    const sourceSelect = document.getElementById('sourceLocation');
    const targetSelect = document.getElementById('targetLocation');
    
    if (!sourceSelect.value) {
        Swal.showValidationMessage('Lütfen bir kaynak seçin');
        return false;
    }
    
    if (!targetSelect.value) {
        Swal.showValidationMessage('Lütfen bir hedef seçin');
        return false;
    }
    
    if (sourceSelect.value === targetSelect.value) {
        Swal.showValidationMessage('Kaynak ve hedef aynı olamaz');
        return false;
    }
    
    Swal.resetValidationMessage();
    return true;
}

/**
 * Transfer formunu gönderme işlemini yapan yardımcı fonksiyon
 */
function handleTransferFormSubmission() {
    const form = document.getElementById('transferForm');
    const formData = new FormData(form);

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

/**
 * Fiyatı formatlayan yardımcı fonksiyon
 */
function formatCurrency(price) {
    return price !== null ? `${parseFloat(price).toFixed(2)} ₺` : '-';
}

export function deleteProduct(id) {
    Swal.fire({
        title: 'Emin misiniz?',
        text: "Bu ürünü silmek istediğinize emin misiniz?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Evet, sil!',
        cancelButtonText: 'İptal'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('api/delete_product.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ id: id })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Başarılı!',
                        text: 'Ürün başarıyla silindi',
                        showConfirmButton: false,
                        timer: 1500
                    }).then(() => {
                        location.reload(); // Sayfayı yenile
                    });
                } else {
                    throw new Error(data.message || 'Silme işlemi başarısız oldu');
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Hata!',
                    text: error.message
                });
            });
        }
    });
}