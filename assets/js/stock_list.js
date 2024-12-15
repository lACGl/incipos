// stock_list.js

import { ValidationUtils, DOMUtils, StorageUtils, showErrorToast, showSuccessToast, debounce, showLoadingToast, closeLoadingToast, fetchData, loadSelectOptions } from './utils.js';
import { editProduct, deleteProduct, showPriceHistory, transferProduct, initializeKarMarji, showEditModal, getProductDetails, showDetailsModal } from './stock_list_actions.js';
import { addProduct } from './stock_list_process.js';
window.addProduct = addProduct;
window.toggleAllCheckboxes = toggleAllCheckboxes;
window.toggleFilters = toggleFilters;
window.updateItemsPerPage = updateItemsPerPage;

window.selectedProducts = [];
let currentStockOrder = 'DESC';
let searchTimeout;
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
export function sortTableByStock() {
    currentStockOrder = currentStockOrder === 'ASC' ? 'DESC' : 'ASC';
    const formData = new FormData(itemsForm);
    formData.append('sort_column', 'stok_miktari');
    formData.append('sort_order', currentStockOrder);

    showLoadingToast('Sıralanıyor...');

    fetchData('get_table_data.php', {
        method: 'POST',
        body: formData,
    })
        .then(data => {
            if (data.success) {
                tableContainer.innerHTML = data.table;
                updateSortIcon();
                initializeEventListeners();
            } else {
                throw new Error(data.message || 'Sıralama işlemi başarısız oldu');
            }
        })
        .catch(error => showErrorToast('Sıralama işlemi sırasında bir hata oluştu'))
        .finally(closeLoadingToast);
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
    document.getElementById('filtersPanel')?.classList.toggle('open');
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
    if (!searchInput) return;

    const loadingIndicator = document.createElement('div');
    loadingIndicator.className = 'loading-indicator hidden';
    loadingIndicator.innerHTML = `
        <div class="flex items-center justify-center">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
            <span class="ml-2">Aranıyor...</span>
        </div>`;
    searchInput.parentNode.appendChild(loadingIndicator);

    const debouncedSearch = debounce(async value => {
        if (!tableContainer || !itemsForm) return;

        try {
            loadingIndicator.classList.remove('hidden');
            tableContainer.style.opacity = '0.5';
            const formData = new FormData(itemsForm);
            formData.append('search_term', value);
            formData.append('page', '1');

            const data = await fetchData('get_table_data.php', { method: 'POST', body: formData });
            tableContainer.innerHTML = data.table;
            if (paginationContainer && data.pagination) paginationContainer.innerHTML = data.pagination;
            initializeEventListeners();
            initializeProductRowEvents();
            initializePaginationEvents();
        } catch (error) {
            showErrorToast('Arama yapılırken bir hata oluştu.');
        } finally {
            loadingIndicator.classList.add('hidden');
            tableContainer.style.opacity = '1';
        }
    }, 500);

    searchInput.addEventListener('input', e => debouncedSearch(e.target.value.trim()));
}


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

function filterByDepartment() {
    filterBy('api/get_departmanlar.php', 'Departman', 'departmentFilter', 'departman_id');
}

function filterByAnaGrup() {
    filterBy('api/get_ana_gruplar.php', 'Ana Grup', 'anaGrupFilter', 'ana_grup_id');
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
    if (!itemsForm) return;

    const formData = new FormData(itemsForm);
    formData.append('page', page);

    if (additionalData) {
        if (additionalData instanceof FormData) {
            additionalData.forEach((value, key) => formData.append(key, value));
        } else {
            Object.entries(additionalData).forEach(([key, value]) => formData.append(key, value));
        }
    }

    try {
        // Loading başlangıcı - sadece toast göster, opacity değiştirme
        showLoadingToast('Yükleniyor...');
        
        const data = await fetchData('get_table_data.php', { 
            method: 'POST', 
            body: formData 
        });

        // Başarılı response
        if (data.success) {
            tableContainer.innerHTML = data.table;
            updatePagination(data.pagination);
            updateTotalProducts(data.total_products);
            reinitializeEventListeners();
        } else {
            throw new Error(data.message || 'Veriler yüklenirken bir hata oluştu');
        }

    } catch (error) {
        console.error('Error:', error);
        showErrorToast(error.message);
    } finally {
        // Loading bitişi - her durumda toast'u kapat
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

// Sayfa yüklendiğinde başlat
document.addEventListener('DOMContentLoaded', () => {
    initializePage();
    initializePopupCloseListeners();
    initializeFormValidation();
    initializeSearch();
	initializeColumnSelection();
	initializeProductEventListeners();
});