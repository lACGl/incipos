// stock_list.js

import { ValidationUtils, DOMUtils, StorageUtils, showToast ,showErrorToast, showSuccessToast, debounce, showLoadingToast, closeLoadingToast, fetchData, loadSelectOptions } from './utils.js';
import { editProduct, deleteProduct, showPriceHistory, transferProduct, initializeKarMarji, showEditModal, getProductDetails, showDetailsModal } from './stock_list_actions.js';
import { addProduct } from './stock_list_process.js';
window.addProduct = addProduct;
window.toggleAllCheckboxes = toggleAllCheckboxes;
window.toggleFilters = toggleFilters;
window.updateItemsPerPage = updateItemsPerPage;

window.selectedProducts = [];
let currentStockOrder = 'DESC';
let searchTimeout;
let isRequestPending = false;

const BASE_URL = '/';

// Ortak DOM seçiciler
const tableContainer = document.getElementById('tableContainer');
const itemsForm = document.getElementById('itemsForm');
const paginationContainer = document.querySelector('.pagination');
const itemsPerPageSelect = document.querySelector('select[name="items_per_page"]');
const searchInput = document.querySelector('.search-box input[name="search_term"]');
const selectAllCheckbox = document.getElementById('selectAll');

// Sayfa yüklendiğinde yapılacak işlemler
function initializePage() {
    updateTableAjax(1);

    if (itemsPerPageSelect) {
        itemsPerPageSelect.addEventListener('change', handleItemsPerPageChange);
    }

    if (searchInput) {
        searchInput.addEventListener('input', debounceSearch);
    }

    if (itemsForm) {
        itemsForm.addEventListener('submit', handleFormSubmit);
    }

    if (selectAllCheckbox) {
        selectAllCheckbox.checked = false;
    }

    initializeEventListeners();
    initializeProductRowEvents();
    initializePaginationEvents();
}

function initializeEventListeners() {
    // Örnek event listener'lar
    document.querySelectorAll('.delete-btn').forEach(button => {
        button.addEventListener('click', (e) => {
            e.preventDefault();
            const id = button.getAttribute('data-id');
            deleteProduct(id);
        });
    });

    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', (e) => {
            e.preventDefault();
            const id = button.getAttribute('data-id');
            editProduct(id);
        });
    });
}

// Stok sıralama fonksiyonu
export 
function sortTableByStock() {
    // Sıralama yönünü değiştir
    currentStockOrder = currentStockOrder === 'ASC' ? 'DESC' : 'ASC';

    // Form verilerini al
    const formData = new FormData();
    
    // Mevcut form verilerini ekle
    const existingForm = document.getElementById('itemsForm');
    if (existingForm) {
        new FormData(existingForm).forEach((value, key) => {
            formData.append(key, value);
        });
    }
    
    // Sıralama parametrelerini ekle
    formData.append('sort_column', 'stok_miktari');
    formData.append('sort_order', currentStockOrder);

    // Loading göster
    const loadingToast = Swal.fire({
        title: 'Sıralanıyor...',
        allowOutsideClick: false,
        showConfirmButton: false,
        willOpen: () => {
            Swal.showLoading();
        }
    });

    // API isteği
    fetch('get_table_data.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Tabloyu güncelle
            const tableContainer = document.getElementById('tableContainer');
            if (tableContainer) {
                tableContainer.innerHTML = data.table;
            }
            
            // Sıralama ikonunu güncelle
            const icon = document.querySelector('.stok-header .sort-icon');
            if (icon) {
                icon.textContent = currentStockOrder === 'ASC' ? '↑' : '↓';
            }
            
            // Event listener'ları yeniden ekle
            initializeEventListeners();
            
            loadingToast.close();
        } else {
            throw new Error(data.message || 'Sıralama işlemi başarısız oldu');
        }
    })
    .catch(error => {
        console.error('Sıralama hatası:', error);
        loadingToast.close();
        Swal.fire({
            icon: 'error',
            title: 'Hata!',
            text: 'Sıralama işlemi sırasında bir hata oluştu',
            timer: 2000,
            showConfirmButton: false
        });
    });
}

// Departmana göre filtreleme fonksiyonu
async function filterByDepartment() {
    try {
        // Departmanları API'dan al
        const response = await fetch('api/get_departmanlar.php');
        const result = await response.json();
        
        if (!result.success) {
            throw new Error('Departmanlar yüklenemedi');
        }

        // Swal ile filtreleme modalını göster
        const { value: selectedId } = await Swal.fire({
            title: 'Departmana Göre Filtrele',
            html: `
                <select id="departmentFilter" class="swal2-input">
                    <option value="">Tümü</option>
                    ${result.data.map(dept => `
                        <option value="${dept.id}">${dept.ad}</option>
                    `).join('')}
                </select>
            `,
            showCancelButton: true,
            confirmButtonText: 'Filtrele',
            cancelButtonText: 'İptal',
            preConfirm: () => {
                const select = document.getElementById('departmentFilter');
                return select.value;
            }
        });

        if (selectedId) {
            // Form verilerini hazırla
            const formData = new FormData();
            formData.append('departman_id', selectedId);
            
            // Seçili sütunları ekle
            document.querySelectorAll('input[name="columns[]"]:checked').forEach(checkbox => {
                formData.append('columns[]', checkbox.value);
            });

            // Loading göster
            showLoadingToast('Filtreleniyor...');

            // API isteği gönder
            const data = await fetch('get_table_data.php', {
                method: 'POST',
                body: formData
            }).then(res => res.json());

            if (data.success) {
                // Tablo içeriğini güncelle
                document.getElementById('tableContainer').innerHTML = data.table;
                
                // Toplam ürün sayısını güncelle
                if (data.total_products) {
                    document.getElementById('total-products').textContent = data.total_products;
                }

                // Event listener'ları yeniden bağla
                initializeEventListeners();
                initializeProductRowEvents();
                initializePaginationEvents();

                closeLoadingToast();
                showSuccessToast('Filtreleme tamamlandı');
            } else {
                throw new Error(data.message || 'Filtreleme işlemi başarısız oldu');
            }
        }
    } catch (error) {
        console.error('Filtreleme hatası:', error);
        closeLoadingToast();
        showErrorToast(error.message);
    }
}

async function filterByAnaGrup() {
    try {
        // Ana grupları API'dan al
        const response = await fetch('api/get_ana_gruplar.php');
        const result = await response.json();

        if (!result.success) {
            throw new Error('Ana gruplar yüklenemedi');
        }

        // Swal ile filtreleme modalını göster
        const { value: selectedId } = await Swal.fire({
            title: 'Ana Gruba Göre Filtrele',
            html: `
                <select id="anaGrupFilter" class="swal2-input">
                    <option value="">Tümü</option>
                    ${result.data.map(anaGrup => `
                        <option value="${anaGrup.id}">${anaGrup.ad}</option>
                    `).join('')}
                </select>
            `,
            showCancelButton: true,
            confirmButtonText: 'Filtrele',
            cancelButtonText: 'İptal',
            preConfirm: () => {
                return document.getElementById('anaGrupFilter').value;
            }
        });

        if (selectedId !== undefined) {
            // Form verilerini hazırla
            const formData = new FormData();
            
            // Seçili ana grup ID'sini ekle
            if (selectedId) {
                formData.append('ana_grup_id', selectedId);
            }

            // Seçili sütunları ekle
            document.querySelectorAll('input[name="columns[]"]:checked').forEach(checkbox => {
                formData.append('columns[]', checkbox.value);
            });

            // Loading göster
            showLoadingToast('Filtreleniyor...');

            // API isteği gönder
            const data = await fetch('get_table_data.php', {
                method: 'POST',
                body: formData
            }).then(res => res.json());

            if (data.success) {
                // Tablo içeriğini güncelle
                document.getElementById('tableContainer').innerHTML = data.table;

                // Toplam ürün sayısını güncelle
                if (data.total_products) {
                    document.getElementById('total-products').textContent = data.total_products;
                }

                // Event listener'ları yeniden bağla
                initializeEventListeners();
                initializeProductRowEvents();
                initializePaginationEvents();

                closeLoadingToast();
                showSuccessToast('Filtreleme tamamlandı');
            } else {
                throw new Error(data.message || 'Filtreleme işlemi başarısız oldu');
            }
        }
    } catch (error) {
        console.error('Filtreleme hatası:', error);
        closeLoadingToast();
        showErrorToast(error.message);
    }
}

// Sıralama ikonunu güncelle
function updateSortIcon() {
    const icon = document.querySelector('.stok-header .sort-icon');
    if (icon) {
        icon.textContent = currentStockOrder === 'ASC' ? '↑' : '↓';
    }
}

// Ürün detaylarını gösterme fonksiyonu
window.showDetails = async function (id, event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }

    const productData = await getProductDetails(id);

    if (productData) {
        showDetailsModal(productData);
    }
};

// Ürün HTML oluşturma fonksiyonu
function generateProductHtml(product) {
    const fields = [
        { label: 'Barkod', value: product.barkod || '-' },
        { label: 'Ürün Adı', value: product.ad || '-' },
        { label: 'Stok', value: product.stok_miktari || '0' },
        { label: 'Satış Fiyatı', value: `₺${parseFloat(product.satis_fiyati || 0).toFixed(2)}` },
        { label: 'KDV Oranı', value: `%${product.kdv_orani || '0'}` },
        { label: 'Departman', value: product.departman || '-' },
        { label: 'Kategori', value: product.ana_grup || '-' },
        { label: 'Alt Kategori', value: product.alt_grup || '-' },
    ];

    return `
        <div class="text-left">
            <div class="grid grid-cols-2 gap-4">
                ${fields.map(field => `
                    <div class="font-bold">${field.label}:</div>
                    <div>${field.value}</div>
                `).join('')}
            </div>
        </div>
    `;
}

// Event handler fonksiyonları
function handleCheckboxChange() {
    updateTableAjax();
}

function handleItemsPerPageChange() {
    updateTableAjax(1);
}

// Arama inputu için debounce
function handleSearchInput() {
    const searchInput = document.querySelector('.search-box input[name="search_term"]');
    if (!searchInput) return;

    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        showLoadingToast('Aranıyor...');
        const formData = new FormData(itemsForm);

        fetchData('get_table_data.php', {
            method: 'POST',
            body: formData,
        })
            .then(data => {
                tableContainer.innerHTML = data.table;
                if (paginationContainer && data.pagination) {
                    paginationContainer.innerHTML = data.pagination;
                }
                initializeEventListeners();
                initializePaginationEvents();
            })
            .catch(() => showErrorToast('Arama sırasında bir hata oluştu'))
            .finally(closeLoadingToast);
    }, 500);
}


// Event handler'ları sıfırla ve yeniden başlat
function reinitializeEventHandlers() {
    document.querySelectorAll('.product-row, button').forEach(el => {
        const newEl = el.cloneNode(true);
        el.parentNode.replaceChild(newEl, el);
    });
    initializeEventListeners();
    initializeProductRowEvents();
    initializePaginationEvents();
}

// Sayfa başına gösterilecek ürün sayısını güncelleme
export function updateItemsPerPage(value) {
    const formData = new FormData();
    formData.append('items_per_page', value);

    fetchData('update_session.php', {
        method: 'POST',
        body: formData,
    })
        .then(data => {
            if (data.success) {
                updateTableAjax(1);
            }
        })
        .catch(() => showErrorToast('Sayfa başına ürün sayısı güncellenirken bir hata oluştu'));
}

// Pagination için event listener'ları initialize et
function initializePaginationEvents() {
    document.querySelectorAll('.pagination a').forEach(link => {
        link.addEventListener('click', e => {
            e.preventDefault();
            const page = link.getAttribute('data-page');
            if (page) {
                updateTableAjax(parseInt(page));
            }
        });
    });
}

// Arama işlemi için debounce
function debounceSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        showLoadingToast('Aranıyor...');
        updateTableAjax(1);
    }, 500);
}

// Form submit işlemi
function handleFormSubmit(e) {
    e.preventDefault();
    showLoadingToast('Yükleniyor...');
    updateTableAjax(1).finally(closeLoadingToast);
}

// Stok güncelleme fonksiyonu
export async function updateStock(id, amount, operation, location, magazaId = null) {
    try {
        const response = await fetch('api/update_stock.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                id: id,
                amount: amount,
                operation: operation,
                location: location,
                magaza_id: magazaId
            })
        });

        const data = await response.json();

        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Başarılı!',
                text: `Stok ${operation === 'add' ? 'ekleme' : 'çıkarma'} işlemi başarıyla tamamlandı`,
                showConfirmButton: false,
                timer: 1500
            }).then(() => {
                // Tabloyu güncelle
                updateTableAjax();
            });
        } else {
            throw new Error(data.message || 'Stok güncellenirken bir hata oluştu');
        }
    } catch (error) {
        console.error('Stok güncelleme hatası:', error);
        Swal.fire({
            icon: 'error',
            title: 'Hata!',
            text: error.message
        });
    }
}

// Ürün fiyat geçmişini yükleyen yardımcı fonksiyon
function loadProductHistory(productId, baseHtml) {
    fetchData(`api/get_product_history.php?id=${productId}`)
        .then(data => {
            console.log('Product history data:', data); // Debug için

            if (!data.success) {
                console.warn('No history data available');
                return;
            }

            let extraHtml = '';

            // Fiyat geçmişi varsa ekle
            if (data.fiyat_gecmisi?.length > 0) {
                extraHtml += generatePriceHistoryHtml(data.fiyat_gecmisi);
            }

            // Stok hareketleri varsa ekle
            if (data.stok_hareketleri?.length > 0) {
                extraHtml += generateStockMovementsHtml(data.stok_hareketleri);
            }

            if (extraHtml) {
                Swal.update({ html: baseHtml + extraHtml });
            }
        })
        .catch(() => showErrorToast('Geçmiş bilgileri yüklenirken bir hata oluştu'));
}

// Popup kapatma fonksiyonu
function closePopup() {
    const popup = document.getElementById('updatePopup');
    if (popup) {
        popup.style.display = 'none';
        popup.querySelector('form')?.reset();
    }
}

// Satır tıklama event listener'ı
function initializeRowClickListeners() {
    document.querySelectorAll('.product-row').forEach(row => {
        row.addEventListener('click', e => {
            if (e.target.tagName.toLowerCase() === 'button' || e.target.closest('button')) return;
            const product = JSON.parse(row.dataset.product);
            showEditModal(product);
        });
    });
}

// Select element event listener'ları
function initializeSelectListeners() {
    ['departman', 'birim', 'ana_grup', 'alt_grup'].forEach(id => {
        const select = document.getElementById(id);
        if (select) {
            loadSelectOptions(id).then(() => {
                const selectedValue = select.getAttribute('data-selected-value');
                if (selectedValue) {
                    select.value = selectedValue;
                }
            });
        }
    });
}


// Form validasyonu
function initializeFormValidation() {
    const form = document.querySelector('#updatePopup form');
    if (form) {
        form.querySelectorAll('input').forEach(input => {
            if (input.hasAttribute('required')) {
                input.addEventListener('invalid', e => {
                    e.preventDefault();
                    showErrorToast(`${input.name} alanı boş bırakılamaz!`);
                });
            }

            if (input.type === 'number') {
                input.addEventListener('input', () => {
                    if (input.value < 0) input.value = 0;
                });
            }
        });
    }
}

// Popup kapatma işlemleri
function initializePopupCloseListeners() {
    document.addEventListener('click', e => {
        if (e.target === document.getElementById('updatePopup')) closePopup();
    });

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closePopup();
    });
}

// Filtre panelini aç/kapat
export function toggleFilters() {
    const filtersPanel = document.getElementById('filtersPanel');
    if (filtersPanel) {
        filtersPanel.classList.toggle('open');
    }
}
window.toggleActions = function(event) {
    event.preventDefault();
    event.stopPropagation();
    
    const menu = document.getElementById('actionsMenu');
    menu.classList.toggle('open');
    
    // Menü dışına tıklandığında menüyü kapat
    document.addEventListener('click', function closeMenu(e) {
        if (!menu.contains(e.target) && !e.target.matches('.actions-button')) {
            menu.classList.remove('open');
            document.removeEventListener('click', closeMenu);
        }
    });
};

// Arama işlemi
function initializeSearch() {
    const searchInput = document.querySelector('.search-box input[name="search_term"]');
    const tableContainer = document.getElementById('tableContainer');
    if (!searchInput || !tableContainer) return;

    searchInput.addEventListener('input', debounce(async () => {
        // Her aramanın başında tüm mevcut modalları kapat
        Swal.close();
        
        try {
            // Yeni loading modalı göster
            await Swal.fire({
                title: 'Aranıyor...',
                didOpen: () => {
                    Swal.showLoading();
                },
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false
            });

            const formData = new FormData(document.getElementById('itemsForm'));
            const response = await fetch('get_table_data.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.message || 'Arama yapılırken bir hata oluştu');
            }

            // Önce loading'i kapat, sonra tabloyu güncelle
            await Swal.close();

            // Tabloyu güncelle
            tableContainer.innerHTML = data.table;
            
            // Diğer güncellemeler
            if (data.total_products) {
                document.getElementById('total-products').textContent = data.total_products;
            }
            if (data.pagination) {
                const paginationContainer = document.querySelector('.pagination');
                if (paginationContainer) {
                    paginationContainer.innerHTML = data.pagination;
                }
            }

            // Event listenerları yeniden bağla
            initializeEventListeners();
            initializeProductRowEvents();
            initializePaginationEvents();

        } catch (error) {
            console.error('Arama hatası:', error);
            // Loading modalını kapat ve hata mesajını göster
            await Swal.fire({
                icon: 'error',
                title: 'Hata!',
                text: error.message,
                timer: 2000,
                showConfirmButton: false
            });
        }
    }, 300));

    // ESC tuşuna basıldığında modalları kapat
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            Swal.close();
        }
    });
}

// Style'ı basitleştir
const style = document.createElement('style');
style.textContent = `
    .loading-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(255, 255, 255, 0.7);
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .spinner {
        padding: 20px;
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
`;
document.head.appendChild(style);



// Checkbox'ları toplu seçme/iptal etme
export function toggleAllCheckboxes(source) {
    document.getElementsByName('selected_products[]').forEach(checkbox => {
        checkbox.checked = source.checked;
    });
}

// Filtreleme fonksiyonları
async function filterBy(endpoint, title, selectId, formDataKey) {
    try {
        const response = await fetch(endpoint);
        const result = await response.json();

        if (!result.success) {
            throw new Error(`${title} yüklenemedi`);
        }

        const { value: selectedId } = await Swal.fire({
            title: `${title} Filtrele`,
            html: `
                <select id="${selectId}" class="swal2-input">
                    <option value="">Tümü</option>
                    ${result.data.map(item => `<option value="${item.id}">${item.ad}</option>`).join('')}
                </select>
            `,
            showCancelButton: true,
            confirmButtonText: 'Filtrele',
            cancelButtonText: 'İptal',
            preConfirm: () => document.getElementById(selectId).value,
        });

        if (selectedId !== undefined) {
            const formData = new FormData();
            if (selectedId) {
                formData.append(formDataKey, selectedId);
            }

            document.querySelectorAll('input[name="columns[]"]:checked').forEach(checkbox => {
                formData.append('columns[]', checkbox.value);
            });

            showLoadingToast('Yükleniyor...');
            const data = await fetchData('get_table_data.php', { method: 'POST', body: formData });

            tableContainer.innerHTML = data.table;
            initializeEventListeners();
            closeLoadingToast();
        }
    } catch (error) {
        console.error('Filtreleme hatası:', error);
        showErrorToast(`${title} sırasında bir hata oluştu`);
    }
}

// Ürün satır event'lerini başlat
function initializeProductRowEvents() {
    cleanupOldEventListeners();
    initializeButtonEventListeners();
    initializeRowClickEvents();
}

// Yardımcı fonksiyonlar
function cleanupOldEventListeners() {
    document.querySelectorAll('.product-row').forEach(row => {
        const newRow = row.cloneNode(true);
        row.parentNode?.replaceChild(newRow, row);
    });
}

function initializeButtonEventListeners() {
    const buttonHandlers = {
        '.edit-btn': editProduct,
        '.stock-btn': addStock,
        '.details-btn': showDetails,
        '.delete-btn': deleteProduct,
        '.stock-details-btn': showStockDetails,
        '.price-history-btn': showPriceHistory,
        '.transfer-btn': transferProduct,
    };

    Object.entries(buttonHandlers).forEach(([selector, handler]) => {
        document.querySelectorAll(selector).forEach(button => {
            button.addEventListener('click', e => {
                e.preventDefault();
                e.stopPropagation();
                const id = button.getAttribute('data-id');
                handler(id, e);
            });
        });
    });
}

function initializeRowClickEvents() {
    document.querySelectorAll('.product-row').forEach(row => {
        row.addEventListener('click', e => {
            if (e.target.closest('button, input[type="checkbox"], td:first-child')) return;
            try {
                const productData = JSON.parse(row.dataset.product || '{}');
                if (productData.id) {
                    showEditModal(productData);
                }
            } catch (error) {
                console.error('Error parsing product data:', error);
            }
        });
    });
}

export async function updateTableAjax(page = 1, additionalData = null) {
    // Eğer bekleyen bir istek varsa, yeni isteği yapma
    if (isRequestPending) return;
    
    isRequestPending = true;
    
    try {
        const formData = new FormData(document.getElementById('itemsForm'));
        formData.append('page', page);

        // Additional data varsa ekle
        if (additionalData) {
            if (additionalData instanceof FormData) {
                additionalData.forEach((value, key) => formData.append(key, value));
            } else {
                Object.entries(additionalData).forEach(([key, value]) => {
                    formData.append(key, value);
                });
            }
        }

        // Stok durumu filtresi
        const stockStatus = document.querySelector('select[name="stock_status"]')?.value;
        if (stockStatus) {
            formData.append('stock_status', stockStatus);
        }

        // Son hareket tarihi filtresi
        const lastMovementDate = document.querySelector('input[name="last_movement_date"]')?.value;
        if (lastMovementDate) {
            formData.append('last_movement_date', lastMovementDate);
        }

        showLoadingToast('Yükleniyor...');

        const response = await fetch('get_table_data.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            document.getElementById('tableContainer').innerHTML = data.table;
            if (data.pagination) {
                document.querySelector('.pagination').innerHTML = data.pagination;
            }
            if (data.total_products) {
                document.getElementById('total-products').textContent = data.total_products;
            }
            
            // Event listener'ları yeniden bağla
            initializeEventListeners();
            initializeProductRowEvents();
            initializePaginationEvents();
        } else {
            throw new Error(data.message || 'Veriler yüklenirken bir hata oluştu');
        }

    } catch (error) {
        console.error('Update error:', error);
        showErrorToast(error.message);
    } finally {
        isRequestPending = false;
        closeLoadingToast();
    }
}

function showLoadingEffects(container) {
    container.style.opacity = '0.5';
    showLoadingToast('Yükleniyor...');
}

function handleSuccessResponse(data, container) {
    container.innerHTML = data.table;
    updatePagination(data.pagination);
    updateTotalProducts(data.total_products);
    reinitializeEventListeners();
    closeLoadingToast();
}

function updateTotalProducts(totalRecords) {
    const totalProductsElement = document.getElementById('total-products');
    if (totalProductsElement && totalRecords) {
        totalProductsElement.textContent = new Intl.NumberFormat('tr-TR').format(totalRecords);
    }
}

function updatePagination(paginationHtml) {
    if (paginationContainer && paginationHtml) {
        paginationContainer.innerHTML = paginationHtml;
    }
}

function reinitializeEventListeners() {
    setTimeout(() => {
        initializeEventListeners();
        initializeProductRowEvents();
        initializePaginationEvents();
        resetSelectAllCheckbox();
    }, 0);
}

function resetSelectAllCheckbox() {
    if (selectAllCheckbox) {
        selectAllCheckbox.checked = false;
    }
}

function removeLoadingEffects(container) {
    container.style.opacity = '1';
    closeLoadingToast();
}

function handleErrorResponse(error, container) {
    console.error('Error:', error);
    removeLoadingEffects(container);
    showErrorToast(`Veriler yüklenirken bir hata oluştu: ${error.message}`);
    throw error;
}


function initializeColumnSelection() {
    // Sütun seçimi form elementlerini dinle
    document.querySelectorAll('input[name="columns[]"]').forEach(checkbox => {
        checkbox.addEventListener('change', async () => {
            try {
                showLoadingToast('Tablo güncelleniyor...');
                
                // Form verilerini topla
                const formData = new FormData(document.getElementById('itemsForm'));
                
                // AJAX isteği yap
                const response = await fetch('get_table_data.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Tablo içeriğini güncelle
                    document.getElementById('tableContainer').innerHTML = data.table;
                    // Event listener'ları yeniden ekle
                    initializeEventListeners();
                } else {
                    throw new Error(data.message || 'Tablo güncellenirken bir hata oluştu');
                }
            } catch (error) {
                showErrorToast(error.message);
            } finally {
                closeLoadingToast();
            }
        });
    });
}

// Event listener'ları initialize eden fonksiyon
export function initializeProductEventListeners() {
    // Düzenleme butonu için listener
    document.querySelectorAll('button[onclick*="editProduct"]').forEach(button => {
        button.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const id = button.getAttribute('onclick').match(/\d+/)[0];
            const productRow = document.querySelector(`tr[data-product*='"id":${id}']`);
            if (productRow) {
                const productData = JSON.parse(productRow.dataset.product);
                showEditModal(productData);
            }
        });
    });

    // Ürün satırı için listener
    document.querySelectorAll('.product-row').forEach(row => {
        row.addEventListener('click', (e) => {
            if (e.target.tagName.toLowerCase() !== 'button' && !e.target.closest('button')) {
                const productData = JSON.parse(row.dataset.product);
                showEditModal(productData);
            }
        });
    });
}

// Filtre değerlerini sıfırlama fonksiyonu
function resetFilters() {
    const stockStatusSelect = document.querySelector('select[name="stock_status"]');
    const lastMovementDate = document.querySelector('input[name="last_movement_date"]');
    
    if (stockStatusSelect) stockStatusSelect.value = '';
    if (lastMovementDate) lastMovementDate.value = '';
    
    updateTableAjax(1);
}

// Aktif filtreleri gösterme fonksiyonu
function showActiveFilters() {
    const filters = [];
    
    const stockStatus = document.querySelector('select[name="stock_status"]')?.value;
    if (stockStatus) {
        const stockStatusText = {
            'out': 'Stok Yok',
            'low': 'Kritik Stok',
            'normal': 'Normal Stok'
        }[stockStatus];
        filters.push(`Stok Durumu: ${stockStatusText}`);
    }

    const lastMovementDate = document.querySelector('input[name="last_movement_date"]')?.value;
    if (lastMovementDate) {
        filters.push(`Son Hareket: ${lastMovementDate}`);
    }

    if (filters.length > 0) {
        showToast('info', `Aktif Filtreler: ${filters.join(', ')}`);
    }
}

// stock_list.js içindeki event listener kısmına eklenecek
document.addEventListener('DOMContentLoaded', function() {
    // Mevcut event listener'lar...

    // Stok durumu değişikliğini dinle
    const stockStatusSelect = document.querySelector('select[name="stock_status"]');
    if (stockStatusSelect) {
        stockStatusSelect.addEventListener('change', function() {
            // Sayfa 1'e dön ve tabloyu güncelle
            updateTableAjax(1);
        });
    }

    // Son hareket tarihi değişikliğini dinle
    const lastMovementDate = document.querySelector('input[name="last_movement_date"]');
    if (lastMovementDate) {
        lastMovementDate.addEventListener('change', function() {
            // Sayfa 1'e dön ve tabloyu güncelle
            updateTableAjax(1);
        });
    }
});


// Sayfa yüklendiğinde başlat
document.addEventListener('DOMContentLoaded', () => {
    initializePage();
    initializePopupCloseListeners();
    initializeFormValidation();
    initializeSearch();
	initializeColumnSelection();
	initializeProductEventListeners();
	
	    // Filtre butonuna event listener ekle
    const toggleFiltersBtn = document.getElementById('toggleFiltersBtn');
    if (toggleFiltersBtn) {
        toggleFiltersBtn.addEventListener('click', toggleFilters);
    }
});

window.sortTableByStock = sortTableByStock;
window.filterByDepartment = filterByDepartment;
window.filterByAnaGrup = filterByAnaGrup;