window.selectedProducts = [];
let currentStockOrder = 'DESC';
let isModalOpening = false;
const BASE_URL = '/'; 

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


window.showDetails = function(id, event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    
    // Ürün datasını satırdan al
    const row = document.querySelector(`tr[data-product*='"id":${id}']`);
    if (row) {
        try {
            const productData = JSON.parse(row.getAttribute('data-product'));
            console.log('Raw product data:', productData); // Debug için
            showDetailsModal(productData);
        } catch (error) {
            console.error('Product data parsing error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Hata!',
                text: 'Ürün bilgileri alınamadı.'
            });
        }
    }
}




function generateProductHtml(product) {
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
        </div>
    `;
}


// Event handler fonksiyonları
function handleCheckboxChange() {
    updateTableAjax();
}

function handleItemsPerPageChange() {
    // Her sayfa değişiminde 1. sayfaya dön
    updateTableAjax(1);
}


function handleSearchInput() {
    const searchInput = document.querySelector('.search-box input[name="search_term"]');
    if (!searchInput) return;

    // Önceki timeout'u temizle
    clearTimeout(searchTimeout);

    // Yeni timeout oluştur
    searchTimeout = setTimeout(() => {
        const form = document.getElementById('itemsForm');
        if (!form) return;

        // Loading durumunu göster
        Swal.fire({
            title: 'Aranıyor...',
            text: 'Lütfen bekleyin',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        const formData = new FormData(form);
        
        fetch('get_table_data.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (!data.table) {
                throw new Error('Geçersiz yanıt formatı');
            }

            // Tablo container'ı güncelle
            const tableContainer = document.getElementById('tableContainer');
            if (tableContainer) {
                tableContainer.innerHTML = data.table;
            }

            // Pagination güncelle
            const paginationContainer = document.querySelector('.pagination');
            if (paginationContainer && data.pagination) {
                paginationContainer.innerHTML = data.pagination;
            }

            // Event listener'ları yeniden ekle
            initializeEventListeners();
            initializePaginationEvents();

            // Loading'i kapat
            Swal.close();
        })
        .catch(error => {
            console.error('Arama hatası:', error);
            Swal.fire({
                icon: 'error',
                title: 'Hata!',
                text: 'Arama sırasında bir hata oluştu.',
                timer: 2000,
                showConfirmButton: false
            });
        });
    }, 500);
}





function fillSelectOptions(selectElement, options, selectedValue = '') {
    if (!selectElement) return;
    
    const optionsHtml = `
        <option value="">Seçiniz</option>
        ${options.map(option => `
            <option value="${option.id}" ${option.id == selectedValue ? 'selected' : ''}>
                ${SecurityUtils.sanitizeHTML(option.ad)}
            </option>
        `).join('')}
    `;
    DOMUtils.setHTML(selectElement, optionsHtml);
}

// Event handler'ları sıfırla ve yeniden başlat
function reinitializeEventHandlers() {
    // Tüm event listener'ları temizle
    document.querySelectorAll('.product-row, button').forEach(el => {
        const newEl = el.cloneNode(true);
        el.parentNode.replaceChild(newEl, el);
    });

    // Event listener'ları yeniden ekle
    initializeEventListeners();
    initializeProductRowEvents();
    initializePaginationEvents();
}


// Sayfa başına gösterilecek ürün sayısını güncelleme
function updateItemsPerPage(value) {
    const formData = new FormData();
    formData.append('items_per_page', value);
    
    fetch('update_session.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateTableAjax(1);
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

// Pagination için event listener'ları initialize et
function initializePaginationEvents() {
    document.querySelectorAll('.pagination a').forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const page = this.getAttribute('data-page');
            if (page) {
                updateTableAjax(parseInt(page));
            }
        });
    });
}
// Event Listeners
document.addEventListener('DOMContentLoaded', function() {
    // Sayfa yüklendiğinde ilk tabloyu çek
    updateTableAjax(1);

    // Items per page değişikliğini dinle
    const itemsPerPageSelect = document.querySelector('select[name="items_per_page"]');
    if (itemsPerPageSelect) {
        itemsPerPageSelect.addEventListener('change', function() {
            // Loading göstergesi
            Swal.fire({
                title: 'Yükleniyor...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            const formData = new FormData();
            formData.append('items_per_page', this.value);
            formData.append('page', '1');

            fetch('get_table_data.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    throw new Error(data.error);
                }

                // Tablo HTML'ini güncelle
                const tableContainer = document.getElementById('tableContainer');
                if (tableContainer) {
                    tableContainer.innerHTML = data.table;
                }

                // Event listener'ları yeniden ekle
                initializeEventListeners();
                initializeProductRowEvents();
                initializePaginationEvents();

                // Loading popup'ı kapat
                Swal.close();
            })
            .catch(error => {
                console.error('Error:', error);
                // Hata durumunda da popup'ı kapat
                Swal.close();
                
                // Hata mesajını göster
                Swal.fire({
                    icon: 'error',
                    title: 'Hata!',
                    text: error.message
                });
            });
        });
    }
	
    // Arama inputunu dinle
    const searchInput = document.querySelector('.search-box input[name="search_term"]');
    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                // Loading göstergesi
                Swal.fire({
                    title: 'Aranıyor...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                updateTableAjax(1); // Aramada 1. sayfaya dön
            }, 500); // 500ms debounce
        });
    }

    // Form submit eventi
    const form = document.getElementById('itemsForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            // Loading göstergesi
            Swal.fire({
                title: 'Yükleniyor...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            updateTableAjax(1).finally(() => {
                Swal.close();
            });
        });
    }

    // Checkbox'ları resetle
    const selectAllCheckbox = document.getElementById('selectAll');
    if (selectAllCheckbox) {
        selectAllCheckbox.checked = false;
    }

    // İlk yüklemede event listener'ları ekle
    initializeEventListeners();
    initializeProductRowEvents();
    initializePaginationEvents();
});

// Stok güncelleme fonksiyonu
function updateStock(id, amount, operation, location, magazaId = null) {
    const row = document.querySelector(`tr[data-product*='"id":${id}']`);
    if (row) {
        row.style.opacity = '0.5';
    }

    fetch('update_stock.php', {
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
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: `Stok ${operation === 'add' ? 'ekleme' : 'çıkarma'} işlemi başarılı`,
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
                title: data.message || 'Stok güncelleme başarısız',
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


// Ürünfiyat geçmişini yükleyen yardımcı fonksiyon
function loadProductHistory(productId, baseHtml) {
    fetch(`api/get_product_history.php?id=${productId}`)
        .then(response => response.json())
        .then(data => {
            console.log('Product history data:', data); // Debug için

            if (!data.success) {
                console.warn('No history data available');
                return;
            }

            let extraHtml = '';

            // Fiyat geçmişi varsa ekle
            if (data.fiyat_gecmisi && data.fiyat_gecmisi.length > 0) {
                extraHtml += generatePriceHistoryHtml(data.fiyat_gecmisi);
            }

            // Stok hareketleri varsa ekle
            if (data.stok_hareketleri && data.stok_hareketleri.length > 0) {
                extraHtml += generateStockMovementsHtml(data.stok_hareketleri);
            }

            // Eğer ek bilgi varsa modalı güncelle
            if (extraHtml) {
                Swal.update({
                    html: baseHtml + extraHtml
                });
            }
        })
        .catch(error => {
            console.error('History loading error:', error);
            Swal.fire({
                icon: 'error',
                toast: true,
                position: 'top-end',
                text: 'Geçmiş bilgileri yüklenirken bir hata oluştu',
                showConfirmButton: false,
                timer: 3000
            });
        });
}


// Popup'ı kapatma fonksiyonu
function closePopup() {
    const popup = document.getElementById('updatePopup');
    if (popup) {
        popup.style.display = 'none';
    }
    
    // Form varsa resetle
    const form = popup?.querySelector('form');
    if (form) {
        form.reset();
    }
}

// Popup dışına tıklamada kapatma
document.addEventListener('click', function(e) {
    const popup = document.getElementById('updatePopup');
    if (popup && e.target === popup) {
        closePopup();
    }
});

// ESC tuşu ile kapatma
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closePopup();
    }
});

function initializeEventListeners() {
    // 1. Sortable başlıklar için listener'lar
    initializeSortableHeaders();

    // 2. Checkbox değişikliklerini dinle
    initializeCheckboxListeners();
    
    // 3. Table row click event'i
    initializeRowClickListeners();

    // 4. Select elementleri için event listener'lar
    initializeSelectListeners();

    // 5. Buton olaylarını dinle (YENİ)
    initializeButtonListeners();
	
	console.log('Event listener\'lar başlatılıyor...');
    initializeButtonListeners();
}

function initializeButtonListeners() {
    // Edit butonları
    document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const id = btn.getAttribute('data-id');
            editProduct(id, e);
        });
    });

    // Stok butonları
    document.querySelectorAll('.stock-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const id = btn.getAttribute('data-id');
            addStock(id, e);
        });
    });

    // Detay butonları 
    document.querySelectorAll('.details-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const id = btn.getAttribute('data-id');
            showDetails(id, e);
        });
    });

    // Silme butonları
    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const id = btn.getAttribute('data-id');
            deleteProduct(id, e);
        });
    });

    // Stok detay butonları
    document.querySelectorAll('.stock-details-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const id = btn.getAttribute('data-id');
            showStockDetails(id, e);
        });
    });

    // Fiyat geçmişi butonları
    document.querySelectorAll('.price-history-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const id = btn.getAttribute('data-id');
            showPriceHistory(id, e);
        });
    });

    // Transfer butonları
    document.querySelectorAll('.transfer-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const id = btn.getAttribute('data-id');
            transferProduct(id, e);
        });
    });
}
// Alt fonksiyonlar
function initializeSortableHeaders() {
    const headers = document.querySelectorAll('th[data-sortable]');
    headers.forEach(header => {
        // Event listener'ı ekle
        header.addEventListener('click', () => {
            const column = header.dataset.column;
            sortTable(column);
        });

        // Sıralama ikonunu ekle
        if (!header.querySelector('.sort-icon')) {
            const icon = document.createElement('span');
            icon.className = 'sort-icon ml-1 text-gray-400';
            icon.textContent = header.dataset.column === currentSortColumn ? 
                (currentSortOrder === 'ASC' ? '↑' : '↓') : '↕';
            header.appendChild(icon);
        }

        // Cursor pointer ekle
        header.style.cursor = 'pointer';
    });
}

function initializeCheckboxListeners() {
    document.querySelectorAll('input[name="columns[]"]').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            // Debug için log
            console.log('Checkbox changed:', this.value, 'Checked:', this.checked);
            
            // Form verilerini al
            const form = document.getElementById('itemsForm');
            if (!form) return;
            
            // Loading göster
            Swal.fire({
                title: 'Yükleniyor...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Form verilerini gönder
            const formData = new FormData(form);
            
            fetch('get_table_data.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    throw new Error(data.message || 'Veriler alınamadı');
                }

                // Tabloyu güncelle
                const tableContainer = document.getElementById('tableContainer');
                if (tableContainer) {
                    tableContainer.innerHTML = data.table;
                }

                // Event listener'ları yeniden ekle
                initializeEventListeners();
                
                // Loading'i kapat
                Swal.close();
            })
            .catch(error => {
                console.error('Filtreleme hatası:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Hata!',
                    text: 'Filtreleme sırasında bir hata oluştu',
                    timer: 2000,
                    showConfirmButton: false
                });
            });
        });
    });
}

function initializeRowClickListeners() {
    document.querySelectorAll('.product-row').forEach(function(row) {
        row.addEventListener('click', function(e) {
            if (e.target.tagName.toLowerCase() === 'button' || 
                e.target.closest('button')) {
                return; // Butonlara tıklandığında row click'i engelle
            }
            const product = JSON.parse(this.dataset.product);
            showEditModal(product);
        });
    });
}

function initializeSelectListeners() {
    ['departman', 'birim', 'ana_grup', 'alt_grup'].forEach(id => {
        const select = document.getElementById(id);
        
    });
}

// Form submit handler
document.addEventListener('DOMContentLoaded', function() {
    const updateForm = document.querySelector('#updatePopup form');
    if (updateForm) {
        updateForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitButton = this.querySelector('button[type="submit"]');
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = 'Güncelleniyor...';
            }
            
            fetch('update_product.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closePopup();
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: 'Ürün başarıyla güncellendi',
                        showConfirmButton: false,
                        timer: 3000
                    });
                    updateTableAjax();
                } else {
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'error',
                        title: data.message || 'Güncelleme başarısız',
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
            })
            .finally(() => {
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = 'Güncelle';
                }
            });
        });
    }



// Form validasyonu
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('#updatePopup form');
    if (form) {
        form.querySelectorAll('input').forEach(input => {
            if (input.hasAttribute('required')) {
                input.addEventListener('invalid', function(e) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'warning',
                        title: 'Uyarı',
                        text: `${input.name} alanı boş bırakılamaz!`,
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000
                    });
                });
            }
            
            // Sayısal alanlar için validasyon
            if (input.type === 'number') {
                input.addEventListener('input', function(e) {
                    if (input.value < 0) {
                        input.value = 0;
                    }
                });
            }
        });
    }
});

function toggleFilters() {
  const panel = document.getElementById('filtersPanel');
  panel.classList.toggle('open');
}

function initializeSearch() {
    const searchInput = document.querySelector('.search-box input[type="text"]');
    if (!searchInput) return;

    // Loading göstergesi için element
    const loadingIndicator = document.createElement('div');
    loadingIndicator.className = 'loading-indicator hidden';
    loadingIndicator.innerHTML = `
        <div class="flex items-center justify-center">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
            <span class="ml-2">Aranıyor...</span>
        </div>
    `;
    searchInput.parentNode.appendChild(loadingIndicator);

    // Debounce edilmiş arama fonksiyonu
    const debouncedSearch = debounce(async (value) => {
        const tableContainer = document.getElementById('tableContainer');
        if (!tableContainer) return;

        try {
            // Loading göstergesini göster
            loadingIndicator.classList.remove('hidden');
            tableContainer.style.opacity = '0.5';

            const form = document.getElementById('itemsForm');
            if (!form) return;

            const formData = new FormData(form);
            formData.append('search_term', value);
            formData.append('page', '1');

            const response = await fetch('get_table_data.php', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error('Network response was not ok');
            }

            const data = await response.json();

            if (data.error) {
                throw new Error(data.error);
            }

            // Tabloyu güncelle
            tableContainer.innerHTML = data.table;
            tableContainer.style.opacity = '1';

            // Pagination'ı güncelle
            const paginationContainer = document.querySelector('.pagination');
            if (paginationContainer && data.pagination) {
                paginationContainer.innerHTML = data.pagination;
            }

            // Event listener'ları yeniden ekle
            initializeEventListeners();
            initializeProductRowEvents();
            initializePaginationEvents();

        } catch (error) {
            console.error('Arama hatası:', error);
            // Hata mesajını göster
            Swal.fire({
                icon: 'error',
                title: 'Arama Hatası',
                text: 'Arama yapılırken bir hata oluştu. Lütfen tekrar deneyin.',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000
            });
        } finally {
            // Loading göstergesini gizle
            loadingIndicator.classList.add('hidden');
            tableContainer.style.opacity = '1';
        }
    }, 500); // 500ms debounce süresi

    // Input event listener
    searchInput.addEventListener('input', (e) => {
        const searchTerm = e.target.value.trim();
        debouncedSearch(searchTerm);
    });
}

document.addEventListener('DOMContentLoaded', async function() {
    try {
        await updateTableAjax(1);
        
        // Search inputu için event listener
        const searchInput = document.querySelector('.search-box input[name="search_term"]');
        if (searchInput) {
            let timeout;
            searchInput.addEventListener('input', function() {
                clearTimeout(timeout);
                timeout = setTimeout(() => {
                    updateTableAjax(1);
                }, 500);
            });
        }
    } catch (error) {
        console.error('Initialization error:', error);
    }
});

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

// Select elementlerini doldurma fonksiyonu
function fillOptions(elementId, options) {
    const select = document.getElementById(elementId);
    if (!select) {
        console.error(`${elementId} elementi bulunamadı`);
        return;
    }

    // Mevcut seçimi koru
    const currentValue = select.value;
    
    // Select'i temizle ve seçenekleri ekle
    select.innerHTML = `
        <option value="">Seçiniz</option>
        ${options.map(option => `
            <option value="${option.id}">${option.ad}</option>
        `).join('')}
    `;
    
    // Önceki seçimi geri yükle
    if (currentValue) {
        select.value = currentValue;
    }
}

// Seçenek yükleme fonksiyonu
async function loadOptions() {
    try {
        const endpoints = {
            departman: 'api/get_departmanlar.php',
            birim: 'api/get_birimler.php',
            ana_grup: 'api/get_ana_gruplar.php',
            alt_grup: 'api/get_alt_gruplar.php'
        };

        for (const [key, url] of Object.entries(endpoints)) {
            const response = await fetch(url);
            const data = await response.json();
            
            if (data.success) {
                fillOptions(key, data.data);
            }
        }

        // Event listener'ları yeniden ekle
        initializeEventListeners();

        return true;
    } catch (error) {
        console.error('Seçenekler yüklenirken hata:', error);
        return false;
    }
}


// initializeForm fonksiyonunun tanımını ekleyelim
function initializeForm() {
    // Form elementlerini initialize et
    const form = document.querySelector('#addProductForm');
    if (form) {
        // Input validasyonları
        form.querySelectorAll('input').forEach(input => {
            if (input.hasAttribute('required')) {
                input.addEventListener('invalid', function(e) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'warning',
                        title: 'Uyarı',
                        text: `${input.name} alanı boş bırakılamaz!`,
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000
                    });
                });
            }
            
            // Sayısal alanlar için validasyon
            if (input.type === 'number') {
                input.addEventListener('input', function(e) {
                    if (input.value < 0) {
                        input.value = 0;
                    }
                });
            }
        });

        // Fiyat alanları için kar marjı hesaplama
        const alisFiyatiInput = form.querySelector('#alis_fiyati');
        const satisFiyatiInput = form.querySelector('#satis_fiyati');
        if (alisFiyatiInput && satisFiyatiInput) {
            alisFiyatiInput.addEventListener('input', updateKarMarji);
            satisFiyatiInput.addEventListener('input', updateKarMarji);
        }
    }

    // Select elementleri için event listener'ları ekle
    ['departman', 'birim', 'ana_grup', 'alt_grup'].forEach(id => {
        const select = document.getElementById(id);
        if (select) {
            select.addEventListener('change', handleOptionChange);
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
   // Arama inputu listener'ı
   const searchInput = document.querySelector('.search-box input[name="search_term"]');
   if (searchInput) {
       searchInput.addEventListener('input', handleSearchInput);
   }
   
   // Tüm initializationlar
   initializeSearch();
   initializeEventListeners(); 
   initializeProductRowEvents();
   initializePaginationEvents();
   initializeForm();
   
   // İlk tablo yüklemesi
   updateTableAjax(1);
});


function validatePrices() {
    const alisFiyatiInput = document.querySelector('input[name="alis_fiyati"]');
    const satisFiyatiInput = document.querySelector('input[name="satis_fiyati"]');
    
    if (!alisFiyatiInput || !satisFiyatiInput) return;
    
    const alisFiyati = parseFloat(alisFiyatiInput.value) || 0;
    const satisFiyati = parseFloat(satisFiyatiInput.value) || 0;
    
    if (alisFiyati >= satisFiyati && satisFiyati > 0) {
        satisFiyatiInput.setCustomValidity('Alış fiyatı satış fiyatından büyük olamaz');
        return false;
    } else {
        satisFiyatiInput.setCustomValidity('');
        return true;
    }
}

// Kar marjı hesaplama
function updateKarMarji() {
   const alisFiyatiInput = document.querySelector('#alis_fiyati');
   const satisFiyatiInput = document.querySelector('#satis_fiyati');
   const karMarjiNote = document.querySelector('#kar_marji_note');
   
   if (!alisFiyatiInput || !satisFiyatiInput || !karMarjiNote) return;
   
   const alisFiyati = parseFloat(alisFiyatiInput.value) || 0;
   const satisFiyati = parseFloat(satisFiyatiInput.value) || 0;
   
   if (alisFiyati > 0 && satisFiyati > 0) {
       const karMarji = ((satisFiyati - alisFiyati) / alisFiyati) * 100;
       const karTutari = satisFiyati - alisFiyati;
       karMarjiNote.textContent = `Kar Marjı: %${karMarji.toFixed(2)} (${karTutari.toFixed(2)}₺)`;
       karMarjiNote.className = 'mt-1 text-sm text-green-600';
   } else {
       karMarjiNote.textContent = '';
   }
}


function toggleAllCheckboxes(source) {
    const checkboxes = document.getElementsByName('selected_products[]');
    for(let checkbox of checkboxes) {
        checkbox.checked = source.checked;
    }
}



async function filterByDepartment() {
    try {
        const response = await fetch('api/get_departmanlar.php');
        const result = await response.json();
        
        if (!result.success) {
            throw new Error('Departmanlar yüklenemedi');
        }

        const { value: departmanId } = await Swal.fire({
            title: 'Departman Filtrele',
            html: `
                <select id="departmentFilter" class="swal2-input">
                    <option value="">Tümü</option>
                    ${result.data.map(dept => 
                        `<option value="${dept.id}">${dept.ad}</option>`
                    ).join('')}
                </select>
            `,
            showCancelButton: true,
            confirmButtonText: 'Filtrele',
            cancelButtonText: 'İptal',
            preConfirm: () => {
                return document.getElementById('departmentFilter').value;
            }
        });

        if (departmanId !== undefined) {
            const formData = new FormData();
            if (departmanId) {
                formData.append('departman_id', departmanId);
            }
            
            // Mevcut seçili sütunları ekle
            document.querySelectorAll('input[name="columns[]"]:checked').forEach(checkbox => {
                formData.append('columns[]', checkbox.value);
            });

            // Debug için
            console.log('Gönderilen formData:', {
                departman_id: departmanId,
                columns: Array.from(document.querySelectorAll('input[name="columns[]"]:checked')).map(cb => cb.value)
            });

            // Loading göster
            Swal.fire({
                title: 'Yükleniyor...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            const response = await fetch('get_table_data.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                const tableContainer = document.getElementById('tableContainer');
                if (tableContainer) {
                    tableContainer.innerHTML = data.table;
                }
                
                // Event listener'ları yeniden ekle
                initializeEventListeners();
                
                Swal.close();
            } else {
                throw new Error(data.message || 'Filtreleme başarısız oldu');
            }
        }
    } catch (error) {
        console.error('Filtreleme hatası:', error);
        Swal.fire({
            icon: 'error',
            title: 'Hata!',
            text: 'Filtreleme sırasında bir hata oluştu'
        });
    }
}

async function filterByAnaGrup() {
    try {
        const response = await fetch('api/get_ana_gruplar.php');
        const result = await response.json();
        
        if (!result.success) {
            throw new Error('Ana gruplar yüklenemedi');
        }

        const { value: anaGrupId } = await Swal.fire({
            title: 'Ana Grup Filtrele',
            html: `
                <select id="anaGrupFilter" class="swal2-input">
                    <option value="">Tümü</option>
                    ${result.data.map(grup => 
                        `<option value="${grup.id}">${grup.ad}</option>`
                    ).join('')}
                </select>
            `,
            showCancelButton: true,
            confirmButtonText: 'Filtrele',
            cancelButtonText: 'İptal',
            preConfirm: () => {
                return document.getElementById('anaGrupFilter').value;
            }
        });

        if (anaGrupId !== undefined) {
            const formData = new FormData();
            if (anaGrupId) {
                formData.append('ana_grup_id', anaGrupId);
            }
            
            // Mevcut seçili sütunları ekle
            document.querySelectorAll('input[name="columns[]"]:checked').forEach(checkbox => {
                formData.append('columns[]', checkbox.value);
            });

            // Loading göster
            Swal.fire({
                title: 'Yükleniyor...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Tabloyu güncelle
            const response = await fetch('get_table_data.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                const tableContainer = document.getElementById('tableContainer');
                if (tableContainer) {
                    tableContainer.innerHTML = data.table;
                }
                
                // Event listener'ları yeniden ekle
                initializeEventListeners();
                
                // Loading'i kapat
                Swal.close();
            } else {
                throw new Error(data.message || 'Filtreleme başarısız oldu');
            }
        }
    } catch (error) {
        console.error('Filtreleme hatası:', error);
        Swal.fire({
            icon: 'error',
            title: 'Hata!',
            text: 'Filtreleme sırasında bir hata oluştu'
        });
    }
}

function initializeProductRowEvents() {
    //console.log('Initializing row events'); // Debug için

    // 1. Önceki event listener'ları temizle
    cleanupOldEventListeners();

    // 2. Buton event listener'larını ayarla
    initializeButtonEventListeners();

    // 3. Satır click event'lerini ayarla
    initializeRowClickEvents();
}

// Yardımcı fonksiyonlar
function cleanupOldEventListeners() {
    document.querySelectorAll('.product-row').forEach(row => {
        const newRow = row.cloneNode(true);
        if(row.parentNode) {
            row.parentNode.replaceChild(newRow, row);
        }
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
        '.transfer-btn': transferProduct
    };

    Object.entries(buttonHandlers).forEach(([selector, handler]) => {
        document.querySelectorAll(selector).forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const id = this.getAttribute('data-id');
                handler(id, e);
            });
        });
    });
}

function initializeRowClickEvents() {
    document.querySelectorAll('.product-row').forEach(row => {
        row.addEventListener('click', function(e) {
            // Buton, checkbox veya ilk hücreye tıklanırsa işlemi durdur
            if (e.target.closest('button') || 
                e.target.closest('input[type="checkbox"]') || 
                e.target.closest('td:first-child')) {
                return;
            }

            try {
                const productData = JSON.parse(this.dataset.product || '{}');
                if (productData.id) {
                    showEditModal(productData);
                }
            } catch (error) {
                console.error('Error parsing product data:', error);
            }
        });
    });
}

function updateTableAjax(page = 1, additionalData = null) {
    const form = document.getElementById('itemsForm');
    if (!form) return;

    const formData = new FormData(form);
    formData.append('page', page);

    // Ek verileri ekle (örn. departman_id veya ana_grup_id)
    if (additionalData instanceof FormData) {
        additionalData.forEach((value, key) => {
            formData.append(key, value);
        });
    } else if (additionalData) {
        Object.keys(additionalData).forEach(key => {
            formData.append(key, additionalData[key]);
        });
    }

    // Loading göster
    const tableContainer = document.getElementById('tableContainer');
    if (tableContainer) {
        tableContainer.style.opacity = '0.5';
    }

    Swal.fire({
        title: 'Yükleniyor...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    fetch('get_table_data.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            document.getElementById('tableContainer').innerHTML = data.table;
            initializeEventListeners(); 
        }

        if (tableContainer) {
            tableContainer.innerHTML = data.table;
            tableContainer.style.opacity = '1';
        }

        // Pagination'ı güncelle
        const paginationContainer = document.querySelector('.pagination');
        if (paginationContainer && data.pagination) {
            paginationContainer.innerHTML = data.pagination;
        }

        
        // Total products sayısını güncelle
        const totalProductsElement = document.getElementById('total-products');
        if (totalProductsElement && data.total_products) {
            totalProductsElement.textContent = data.total_products;
        }

        Swal.close();
    })
    .catch(error => {
        console.error('Tablo güncelleme hatası:', error);
        if (tableContainer) {
            tableContainer.style.opacity = '1';
        }
        
        Swal.fire({
            icon: 'error',
            title: 'Hata!',
            text: 'Tablo güncellenirken bir hata oluştu',
            timer: 2000,
            showConfirmButton: false
        });
    });
}
// Yardımcı Fonksiyonlar
function initializeFormData(page, departmentId) {
    const form = document.getElementById('itemsForm');
    if (!form) {
        console.error('Form bulunamadı');
        return {};
    }

    const formData = new FormData(form);
    formData.append('page', page);

    if (departmentId) {
        formData.append('departman_id', departmentId);
    }

    // Arama terimini ekle
    const searchInput = document.querySelector('.search-box input[name="search_term"]');
    const searchTerm = searchInput ? searchInput.value : '';
    formData.append('search_term', searchTerm);

    const tableContainer = document.getElementById('tableContainer');
    if (!tableContainer) {
        console.error('Tablo container bulunamadı');
        return {};
    }

    return { formData, tableContainer };
}

function showLoadingEffects(tableContainer) {
    tableContainer.style.opacity = '0.5';
    Swal.fire({
        title: 'Yükleniyor...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
}

async function makeApiRequest(formData) {
    try {
        const data = await APIUtils.fetchAPI('get_table_data.php', {
            method: 'POST',
            body: formData
        });

        if (data.error) {
            throw new Error(data.error);
        }

        return data;
    } catch (error) {
        ErrorHandler.handleError(error, 'makeApiRequest');
        throw error;
    }
}

function handleSuccessResponse(data, tableContainer) {
    // Tablo HTML'ini güncelle
    tableContainer.innerHTML = data.table;
    
    // Toplam ürün sayısını güncelle
    updateTotalProducts(data.total_records);
    
    // Pagination HTML'ini güncelle
    updatePagination(data.pagination);
    
    // Event listener'ları yeniden ekle
    reinitializeEventListeners();
    
    // Loading efektlerini kaldır
    removeLoadingEffects(tableContainer);
    
    return data;
}

function updateTotalProducts(totalRecords) {
    const totalProductsElement = document.getElementById('total-products');
    if (totalProductsElement && totalRecords) {
        totalProductsElement.textContent = new Intl.NumberFormat('tr-TR').format(totalRecords);
    }
}

function updatePagination(paginationHtml) {
    const paginationContainer = document.querySelector('.pagination');
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
    const selectAllCheckbox = document.getElementById('selectAll');
    if (selectAllCheckbox) {
        selectAllCheckbox.checked = false;
    }
}

function removeLoadingEffects(tableContainer) {
    tableContainer.style.opacity = '1';
    Swal.close();
}

function handleErrorResponse(error, tableContainer) {
    console.error('Error:', error);
    
    removeLoadingEffects(tableContainer);
    
    Swal.fire({
        icon: 'error',
        title: 'Hata!',
        text: 'Veriler yüklenirken bir hata oluştu: ' + error.message,
        confirmButtonText: 'Tamam'
    });
    
    throw error;
}

function cleanupAfterRequest() {
    const buttons = document.querySelectorAll('button[type="submit"]');
    buttons.forEach(button => {
        button.disabled = false;
    });
}

const SecurityUtils = {
    sanitizeHTML(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
}

const ErrorHandler = {
    ERROR_MESSAGES: {
        NETWORK: 'Ağ bağlantısı hatası',
        SERVER: 'Sunucu hatası',
        VALIDATION: 'Doğrulama hatası',
        UNAUTHORIZED: 'Yetkisiz erişim',
        DEFAULT: 'Beklenmeyen bir hata oluştu'
    },

    handleError(error, context) {
        console.error(`Error in ${context}:`, error);
        this.logError(error, context);
        this.showUserFriendlyError(error);
    },

    logError(error, context) {
        // Hata loglama mantığı buraya eklenebilir
        console.warn(`[${context}] Error logged:`, error);
    },

    showUserFriendlyError(error) {
        Swal.fire({
            icon: 'error',
            title: 'Hata!',
            text: this.getErrorMessage(error),
            toast: true,
            position: 'top-end',
            timer: 3000,
            showConfirmButton: false
        });
    },

    getErrorMessage(error) {
        // HTTP hata kodlarını kontrol et
        if (error?.status) {
            switch (error.status) {
                case 401:
                    return this.ERROR_MESSAGES.UNAUTHORIZED;
                case 403:
                    return this.ERROR_MESSAGES.UNAUTHORIZED;
                case 404:
                    return 'İstenen kaynak bulunamadı';
                case 500:
                    return this.ERROR_MESSAGES.SERVER;
            }
        }

        // Özel hata mesajlarını kontrol et
        if (error?.type) {
            return this.ERROR_MESSAGES[error.type] || error.message;
        }

        // Standart hata tipleri
        if (typeof error === 'string') {
            return error;
        }
        if (error instanceof Error) {
            return error.message;
        }
        if (error?.message) {
            return error.message;
        }

        // Varsayılan mesaj
        return this.ERROR_MESSAGES.DEFAULT;
    }
};

// Merkezi event yönetimi
const EventManager = {
    addListeners() {
        document.addEventListener('click', this.handleClick.bind(this));
        // Delegation kullanarak tek bir listener ile yönetim
    },
    handleClick(e) {
        const target = e.target;
        if (target.matches('.edit-btn')) this.handleEdit(e);
        if (target.matches('.delete-btn')) this.handleDelete(e);
    }
}