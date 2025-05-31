/**
 * Barkod Yöneticisi JavaScript - jQuery'siz
 */

document.addEventListener('DOMContentLoaded', function() {
    // DataTable yerine basit tablo filtresi
    initSimpleTableFilter();
    
    // Barkod Modal işlemleri
    initBarcodeModal();
    
    // Barkod alanı değişiklik izleme
    initBarcodePreview();
    
    // Toplu yazdırma form elemenleri
    initBatchPrinting();
    
    // Arama formu davranışı
    initSearchForm();
    
    // Yazdırma sayfası kontrolü
    initPrintPage();
    
    // QR kod ve barkod tipi seçimi
    initCodeTypeSelection();
});

/**
 * Basit tablo filtresi ekler
 */
function initSimpleTableFilter() {
    const tableFilter = document.getElementById('table-filter');
    const barcodeTable = document.getElementById('barcode-table');
    
    if (tableFilter && barcodeTable) {
        tableFilter.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = barcodeTable.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
}

/**
 * Barkod modal işlemlerini başlatır
 */
function initBarcodeModal() {
    // Modal açma butonları
    const modalButtons = document.querySelectorAll('[data-toggle="modal"]');
    const modal = document.getElementById('barcodeModal');
    
    if (modalButtons.length && modal) {
        modalButtons.forEach(button => {
            button.addEventListener('click', function() {
                // Data özelliklerini al
                const urunId = this.getAttribute('data-id');
                const urunAdi = this.getAttribute('data-name');
                const barkod = this.getAttribute('data-barcode');
                
                // Modal alanlarını doldur
                modal.querySelector('#urun_id').value = urunId;
                modal.querySelector('#urun_adi').value = urunAdi;
                modal.querySelector('#barcode').value = barkod || '';
                
                // QR kod tipini seç
                const codeTypeSelect = modal.querySelector('#code_type');
                if (codeTypeSelect) {
                    codeTypeSelect.value = 'qr'; // Varsayılan olarak QR kod
                }
                
                // Eğer barkod varsa, önizleme göster
                const previewImg = modal.querySelector('#barcode_preview');
                if (previewImg && barkod) {
                    const codeType = codeTypeSelect ? codeTypeSelect.value : 'qr';
                    previewImg.src = `./?page=extensions&ext=tag_management&action=ajax&op=barcode_image&code=${encodeURIComponent(barkod)}&type=${codeType}`;
                    previewImg.style.display = 'block';
                } else if (previewImg) {
                    previewImg.style.display = 'none';
                }
                
                // Modalı göster
                modal.classList.add('show');
                modal.style.display = 'block';
                document.body.classList.add('modal-open');
                
                // Modal overlay
                const overlay = document.createElement('div');
                overlay.className = 'modal-backdrop fade show';
                document.body.appendChild(overlay);
            });
        });
        
        // Modal kapatma
        const closeButtons = modal.querySelectorAll('[data-dismiss="modal"]');
        closeButtons.forEach(button => {
            button.addEventListener('click', function() {
                closeModal(modal);
            });
        });
        
        // ESC tuşu ile kapatma
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal.classList.contains('show')) {
                closeModal(modal);
            }
        });
        
        // Modal dışına tıklama ile kapatma
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal') && e.target.classList.contains('show')) {
                closeModal(e.target);
            }
        });
    }
}

/**
 * Modal kapatma fonksiyonu
 */
function closeModal(modal) {
    modal.classList.remove('show');
    modal.style.display = 'none';
    document.body.classList.remove('modal-open');
    
    // Overlay'ı kaldır
    const overlay = document.querySelector('.modal-backdrop');
    if (overlay) {
        overlay.parentNode.removeChild(overlay);
    }
}

/**
 * Barkod önizleme özelliğini başlatır
 */
function initBarcodePreview() {
    const barcodeInput = document.getElementById('barcode');
    const codeTypeSelect = document.getElementById('code_type');
    
    if (barcodeInput) {
        barcodeInput.addEventListener('input', updateBarcodePreview);
        
        if (codeTypeSelect) {
            codeTypeSelect.addEventListener('change', updateBarcodePreview);
        }
    }
}

/**
 * Barkod önizlemeyi günceller
 */
function updateBarcodePreview() {
    const barcodeInput = document.getElementById('barcode');
    const codeTypeSelect = document.getElementById('code_type');
    const previewImg = document.getElementById('barcode_preview');
    
    if (barcodeInput && previewImg) {
        const barcode = barcodeInput.value.trim();
        const codeType = codeTypeSelect ? codeTypeSelect.value : 'qr';
        
        if (barcode) {
            previewImg.src = `./?page=extensions&ext=tag_management&action=ajax&op=barcode_image&code=${encodeURIComponent(barcode)}&type=${codeType}`;
            previewImg.style.display = 'block';
        } else {
            previewImg.style.display = 'none';
        }
    }
}

/**
 * Toplu yazdırma sayfasındaki formları başlatır
 */
function initBatchPrinting() {
    // Tümünü Seç / Hiçbirini Seçme
    const selectAllBtn = document.getElementById('select_all');
    const unselectAllBtn = document.getElementById('unselect_all');
    
    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const checkboxes = document.querySelectorAll('.product-checkbox');
            checkboxes.forEach(function(checkbox) {
                checkbox.checked = true;
            });
            updateTotalLabels();
        });
    }
    
    if (unselectAllBtn) {
        unselectAllBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const checkboxes = document.querySelectorAll('.product-checkbox');
            checkboxes.forEach(function(checkbox) {
                checkbox.checked = false;
            });
            updateTotalLabels();
        });
    }
    
    // Toplu yazdırma formunu doğrula
    const batchPrintForm = document.getElementById('batch-print-form');
    if (batchPrintForm) {
        batchPrintForm.addEventListener('submit', function(e) {
            const checkboxes = document.querySelectorAll('.product-checkbox:checked');
            if (checkboxes.length === 0) {
                e.preventDefault();
                alert('Lütfen en az bir ürün seçin!');
                return false;
            }
            return true;
        });
    }
    
    // Adet değişikliğinde toplam etiket sayısını güncelle
    const quantityInputs = document.querySelectorAll('.quantity-input');
    const checkboxes = document.querySelectorAll('.product-checkbox');
    
    if (quantityInputs.length > 0) {
        quantityInputs.forEach(function(input) {
            input.addEventListener('change', updateTotalLabels);
        });
    }
    
    if (checkboxes.length > 0) {
        checkboxes.forEach(function(checkbox) {
            checkbox.addEventListener('change', updateTotalLabels);
        });
    }
    
    // İlk yüklemede toplam sayıyı göster
    updateTotalLabels();
}

/**
 * Toplam etiket sayısını günceller
 */
function updateTotalLabels() {
    let total = 0;
    const quantityInputs = document.querySelectorAll('.quantity-input');
    const checkboxes = document.querySelectorAll('.product-checkbox');
    const totalElement = document.getElementById('total-labels');
    
    if (quantityInputs.length > 0 && checkboxes.length > 0 && totalElement) {
        for (let i = 0; i < checkboxes.length; i++) {
            if (checkboxes[i].checked) {
                const qtyInput = document.querySelector(`.quantity-input[name="quantity[${checkboxes[i].value}]"]`);
                if (qtyInput) {
                    total += parseInt(qtyInput.value) || 0;
                }
            }
        }
        
        totalElement.textContent = total;
    }
}

/**
 * Arama formu davranışını başlatır
 */
function initSearchForm() {
    const searchForm = document.getElementById('search-form');
    if (searchForm) {
        const searchInput = searchForm.querySelector('input[name="search"]');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                if (this.value.length >= 3 || this.value.length === 0) {
                    searchForm.submit();
                }
            });
        }
    }
}

/**
 * Yazdırma sayfası kontrolünü başlatır
 */
function initPrintPage() {
    if (document.body.classList.contains('print-page')) {
        const printButton = document.querySelector('.print-btn.print');
        if (printButton) {
            printButton.addEventListener('click', function() {
                window.print();
            });
        }
        
        // Otomatik yazdırma
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('print') === 'auto') {
            setTimeout(function() {
                if (confirm('Etiketleri şimdi yazdırmak istiyor musunuz?')) {
                    window.print();
                }
            }, 500);
        }
    }
}

/**
 * QR kod veya barkod tipi seçimini başlatır
 */
function initCodeTypeSelection() {
    const codeTypeSelect = document.getElementById('code_type');
    if (codeTypeSelect) {
        codeTypeSelect.addEventListener('change', function() {
            updateBarcodePreview();
        });
    }
}