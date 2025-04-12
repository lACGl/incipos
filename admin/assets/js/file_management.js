document.addEventListener('DOMContentLoaded', function() {
	
	// URL parametrelerini kontrol et - eğer path parametresi varsa dosya gezgini tabını aç
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('path')) {
        showTab('file-browser');
    }
    // Tab menüsü işlemleri
    initTabSystem();
    
    // Kalite slider'ı
    initQualitySlider();
    
    // Ürün seçimi ve barkod ilişkisi
    initProductSelection();
    
    // Modal işlemleri
    initModalActions();
    
    // Klasör oluşturma
    initFolderCreation();
});

/**
 * Tab sistemi işlemlerini başlatır
 */
function initTabSystem() {
    // Sayfadaki tab linklerini seç
    const tabLinks = document.querySelectorAll('a[href^="#"]');
    
    // Her bir tab linkine tıklama olayı ekle
    tabLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Tab ID'sini al
            const tabId = this.getAttribute('href').substring(1);
            
            // Ilgili tab'ı göster
            showTab(tabId);
        });
    });
}

/**
 * Belirtilen tab'ı gösterir, diğerlerini gizler
 * @param {string} tabId - Gösterilecek tab ID'si
 */
function showTab(tabId) {
    // Tüm tab içeriklerini gizle
    document.querySelectorAll('.tab-content').forEach(function(tab) {
        tab.classList.add('hidden');
    });
    
    // Tüm tab linklerini pasif yap
    document.querySelectorAll('a[href^="#"]').forEach(function(link) {
        link.classList.remove('text-blue-600', 'border-blue-600');
        link.classList.add('text-gray-500', 'border-transparent');
    });
    
    // Seçili tab içeriğini göster
    document.getElementById(tabId + '-content').classList.remove('hidden');
    
    // Seçili tab linkini aktif yap
    document.querySelector('a[href="#' + tabId + '"]').classList.remove('text-gray-500', 'border-transparent');
    document.querySelector('a[href="#' + tabId + '"]').classList.add('text-blue-600', 'border-blue-600');
}

/**
 * Resim kalitesi slider işlemlerini başlatır
 */
function initQualitySlider() {
    const qualitySlider = document.getElementById('image_quality');
    const qualityValue = document.getElementById('quality_value');
    
    if (qualitySlider && qualityValue) {
        qualitySlider.addEventListener('input', function() {
            qualityValue.textContent = this.value + '%';
        });
    }
}

/**
 * Ürün seçimi ve barkod ilişkisini yönetir
 */
function initProductSelection() {
    const productSelect = document.getElementById('product_id');
    const barcodeInput = document.getElementById('product_barcode');
    
    if (productSelect && barcodeInput) {
        productSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption.value) {
                barcodeInput.value = selectedOption.getAttribute('data-barcode') || '';
            } else {
                barcodeInput.value = '';
            }
        });
    }
}

/**
 * Modal işlemlerini başlatır
 */
function initModalActions() {
    // Resim önizleme modali
    initImagePreviewModal();
    
    // PDF önizleme modali
    initPdfPreviewModal();
    
    // Modal kapatma işlemi
    initModalClose();
    
    // Dosya indirme işlemi
    initFileDownload();
    
    // Dosya silme işlemi
    initFileDelete();
}

/**
 * Resim önizleme modal işlemlerini başlatır
 */
function initImagePreviewModal() {
    const imageItems = document.querySelectorAll('.image-preview');
    const previewModal = document.getElementById('previewModal');
    const previewTitle = document.getElementById('previewTitle');
    const previewContent = document.getElementById('previewContent');
    const downloadBtn = document.getElementById('downloadFileBtn');
    const deleteBtn = document.getElementById('deleteFileBtn');
    
    if (imageItems && previewModal && previewTitle && previewContent && downloadBtn && deleteBtn) {
        imageItems.forEach(item => {
            item.addEventListener('click', function() {
                const path = this.getAttribute('data-path');
                const fileName = this.querySelector('.file-name').textContent;
                
                previewTitle.textContent = fileName;
                previewContent.innerHTML = `<img src="${path}" class="max-h-[70vh] max-w-full">`;
                downloadBtn.setAttribute('data-path', path);
                deleteBtn.setAttribute('data-path', path);
                
                previewModal.classList.remove('hidden');
            });
        });
    }
}

/**
 * PDF önizleme modal işlemlerini başlatır
 */
function initPdfPreviewModal() {
    const pdfItems = document.querySelectorAll('.pdf-preview');
    const previewModal = document.getElementById('previewModal');
    const previewTitle = document.getElementById('previewTitle');
    const previewContent = document.getElementById('previewContent');
    const downloadBtn = document.getElementById('downloadFileBtn');
    const deleteBtn = document.getElementById('deleteFileBtn');
    
    if (pdfItems && previewModal && previewTitle && previewContent && downloadBtn && deleteBtn) {
        pdfItems.forEach(item => {
            item.addEventListener('click', function() {
                const path = this.getAttribute('data-path');
                const fileName = this.querySelector('.file-name').textContent;
                
                previewTitle.textContent = fileName;
                previewContent.innerHTML = `<iframe src="${path}" class="w-full h-[70vh]"></iframe>`;
                downloadBtn.setAttribute('data-path', path);
                deleteBtn.setAttribute('data-path', path);
                
                previewModal.classList.remove('hidden');
            });
        });
    }
}

/**
 * Modal kapatma işlemlerini başlatır
 */
function initModalClose() {
    const closeBtn = document.getElementById('closePreviewBtn');
    const previewModal = document.getElementById('previewModal');
    
    if (closeBtn && previewModal) {
        closeBtn.addEventListener('click', function() {
            previewModal.classList.add('hidden');
        });
    }
}

/**
 * Dosya indirme işlemlerini başlatır
 */
function initFileDownload() {
    const downloadBtn = document.getElementById('downloadFileBtn');
    
    if (downloadBtn) {
        downloadBtn.addEventListener('click', function() {
            const path = this.getAttribute('data-path');
            if (path) {
                const link = document.createElement('a');
                link.href = path;
                link.download = path.split('/').pop();
                link.click();
            }
        });
    }
}

/**
 * Dosya silme işlemlerini başlatır
 */
function initFileDelete() {
    const deleteBtn = document.getElementById('deleteFileBtn');
    const previewModal = document.getElementById('previewModal');
    
    if (deleteBtn && previewModal) {
        deleteBtn.addEventListener('click', function() {
            const path = this.getAttribute('data-path');
            if (path && confirm('Bu dosyayı silmek istediğinize emin misiniz?')) {
                fetch('api/delete_file.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ path: path })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        previewModal.classList.add('hidden');
                        // Sayfayı yenile
                        location.reload();
                    } else {
                        alert('Dosya silinemedi: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Bir hata oluştu: ' + error);
                });
            }
        });
    }
}

/**
 * Klasör oluşturma işlemlerini başlatır
 */
function initFolderCreation() {
    // Yeni klasör - resimler
    const imageFolder = document.getElementById('upload_folder');
    const newFolderContainer = document.getElementById('new_folder_container');
    
    if (imageFolder && newFolderContainer) {
        imageFolder.addEventListener('change', function() {
            if (this.value === 'new') {
                newFolderContainer.classList.remove('hidden');
            } else {
                newFolderContainer.classList.add('hidden');
            }
        });
    }
    
    // Yeni klasör - PDF
    const pdfCategory = document.getElementById('pdf_category');
    const newPdfCategoryContainer = document.getElementById('new_pdf_category_container');
    
    if (pdfCategory && newPdfCategoryContainer) {
        pdfCategory.addEventListener('change', function() {
            if (this.value === 'new') {
                newPdfCategoryContainer.classList.remove('hidden');
            } else {
                newPdfCategoryContainer.classList.add('hidden');
            }
        });
    }
    
    // Form gönderimi öncesi yeni klasör oluşturma
    const imageForm = document.querySelector('form[name="submit_image"]');
    const pdfForm = document.querySelector('form[name="submit_pdf"]');
    
    if (imageForm) {
        imageForm.addEventListener('submit', function(e) {
            if (document.getElementById('upload_folder').value === 'new') {
                e.preventDefault();
                createNewFolder('img', document.getElementById('new_folder').value, imageForm);
            }
        });
    }
    
    if (pdfForm) {
        pdfForm.addEventListener('submit', function(e) {
            if (document.getElementById('pdf_category').value === 'new') {
                e.preventDefault();
                createNewFolder('pdf', document.getElementById('new_pdf_category').value, pdfForm);
            }
        });
    }
}

/**
 * Yeni klasör oluşturma API'sini çağırır
 * @param {string} parentPath - Üst klasör yolu
 * @param {string} folderName - Yeni klasör adı
 * @param {HTMLFormElement} form - Gönderilecek form
 */
function createNewFolder(parentPath, folderName, form) {
    if (!folderName) {
        alert('Lütfen klasör adı girin.');
        return;
    }
    
    fetch('api/create_directory.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            parent_path: parentPath,
            folder_name: folderName
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (parentPath === 'img') {
                // Yeni klasörü select listesine ekle
                const selectElement = document.getElementById('upload_folder');
                const newOption = document.createElement('option');
                newOption.value = folderName;
                newOption.textContent = folderName;
                
                // Yeni seçeneği "Yeni Klasör" seçeneğinden önce ekle
                const newFolderOption = selectElement.querySelector('option[value="new"]');
                selectElement.insertBefore(newOption, newFolderOption);
                
                // Yeni klasörü seç
                selectElement.value = folderName;
                
                // Yeni klasör alanını gizle
                document.getElementById('new_folder_container').classList.add('hidden');
            } else if (parentPath === 'pdf') {
                // Yeni kategoriyi select listesine ekle
                const selectElement = document.getElementById('pdf_category');
                const newOption = document.createElement('option');
                newOption.value = folderName;
                newOption.textContent = folderName;
                
                // Yeni seçeneği "Yeni Kategori" seçeneğinden önce ekle
                const newCategoryOption = selectElement.querySelector('option[value="new"]');
                selectElement.insertBefore(newOption, newCategoryOption);
                
                // Yeni kategoriyi seç
                selectElement.value = folderName;
                
                // Yeni kategori alanını gizle
                document.getElementById('new_pdf_category_container').classList.add('hidden');
            }
            
            // Formu gönder
            form.submit();
        } else {
            alert('Klasör oluşturma hatası: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Hata:', error);
        alert('Klasör oluşturulurken bir hata oluştu.');
    });
}document.addEventListener('DOMContentLoaded', function() {
    // Tab menüsü işlemleri
    initTabSystem();
    
    // Kalite slider'ı
    initQualitySlider();
    
    // Ürün seçimi ve barkod ilişkisi
    initProductSelection();
    
    // Modal işlemleri
    initModalActions();
    
    // Klasör oluşturma
    initFolderCreation();
    
    // Toplu dosya seçimi
    initBulkFileSelection();
    
    // Klasör bilgilerini yükle
    loadFolderInfo();
});

/**
 * Tab sistemi işlemlerini başlatır
 */
function initTabSystem() {
    // Sayfadaki tab linklerini seç
    const tabLinks = document.querySelectorAll('a[href^="#"]');
    
    // Her bir tab linkine tıklama olayı ekle
    tabLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Tab ID'sini al
            const tabId = this.getAttribute('href').substring(1);
            
            // Ilgili tab'ı göster
            showTab(tabId);
        });
    });
}

/**
 * Belirtilen tab'ı gösterir, diğerlerini gizler
 * @param {string} tabId - Gösterilecek tab ID'si
 */
function showTab(tabId) {
    // Tüm tab içeriklerini gizle
    document.querySelectorAll('.tab-content').forEach(function(tab) {
        tab.classList.add('hidden');
    });
    
    // Tüm tab linklerini pasif yap
    document.querySelectorAll('a[href^="#"]').forEach(function(link) {
        link.classList.remove('text-blue-600', 'border-blue-600');
        link.classList.add('text-gray-500', 'border-transparent');
    });
    
    // Seçili tab içeriğini göster
    document.getElementById(tabId + '-content').classList.remove('hidden');
    
    // Seçili tab linkini aktif yap
    document.querySelector('a[href="#' + tabId + '"]').classList.remove('text-gray-500', 'border-transparent');
    document.querySelector('a[href="#' + tabId + '"]').classList.add('text-blue-600', 'border-blue-600');
}

/**
 * Resim kalitesi slider işlemlerini başlatır
 */
function initQualitySlider() {
    const qualitySlider = document.getElementById('image_quality');
    const qualityValue = document.getElementById('quality_value');
    
    if (qualitySlider && qualityValue) {
        qualitySlider.addEventListener('input', function() {
            qualityValue.textContent = this.value + '%';
        });
    }
}

/**
 * Ürün seçimi ve barkod ilişkisini yönetir
 */
function initProductSelection() {
    const productSelect = document.getElementById('product_id');
    const barcodeInput = document.getElementById('product_barcode');
    
    if (productSelect && barcodeInput) {
        productSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption.value) {
                barcodeInput.value = selectedOption.getAttribute('data-barcode') || '';
            } else {
                barcodeInput.value = '';
            }
        });
    }
}

/**
 * Modal işlemlerini başlatır
 */
function initModalActions() {
    // Resim önizleme modali
    initImagePreviewModal();
    
    // PDF önizleme modali
    initPdfPreviewModal();
    
    // Modal kapatma işlemi
    initModalClose();
    
    // Dosya indirme işlemi
    initFileDownload();
    
    // Dosya silme işlemi
    initFileDelete();
}

/**
 * Resim önizleme modal işlemlerini başlatır
 */
function initImagePreviewModal() {
    const imageItems = document.querySelectorAll('.image-preview');
    const previewModal = document.getElementById('previewModal');
    const previewTitle = document.getElementById('previewTitle');
    const previewContent = document.getElementById('previewContent');
    const downloadBtn = document.getElementById('downloadFileBtn');
    const deleteBtn = document.getElementById('deleteFileBtn');
    
    if (imageItems && previewModal && previewTitle && previewContent && downloadBtn && deleteBtn) {
        imageItems.forEach(item => {
            item.addEventListener('click', function() {
                const path = this.getAttribute('data-path');
                const fileName = this.querySelector('.file-name').textContent;
                
                previewTitle.textContent = fileName;
                previewContent.innerHTML = `<img src="${path}" class="max-h-[70vh] max-w-full">`;
                downloadBtn.setAttribute('data-path', path);
                deleteBtn.setAttribute('data-path', path);
                
                previewModal.classList.remove('hidden');
            });
        });
    }
}

/**
 * PDF önizleme modal işlemlerini başlatır
 */
function initPdfPreviewModal() {
    const pdfItems = document.querySelectorAll('.pdf-preview');
    const previewModal = document.getElementById('previewModal');
    const previewTitle = document.getElementById('previewTitle');
    const previewContent = document.getElementById('previewContent');
    const downloadBtn = document.getElementById('downloadFileBtn');
    const deleteBtn = document.getElementById('deleteFileBtn');
    
    if (pdfItems && previewModal && previewTitle && previewContent && downloadBtn && deleteBtn) {
        pdfItems.forEach(item => {
            item.addEventListener('click', function() {
                const path = this.getAttribute('data-path');
                const fileName = this.querySelector('.file-name').textContent;
                
                previewTitle.textContent = fileName;
                previewContent.innerHTML = `<iframe src="${path}" class="w-full h-[70vh]"></iframe>`;
                downloadBtn.setAttribute('data-path', path);
                deleteBtn.setAttribute('data-path', path);
                
                previewModal.classList.remove('hidden');
            });
        });
    }
}

/**
 * Modal kapatma işlemlerini başlatır
 */
function initModalClose() {
    const closeBtn = document.getElementById('closePreviewBtn');
    const previewModal = document.getElementById('previewModal');
    
    if (closeBtn && previewModal) {
        closeBtn.addEventListener('click', function() {
            previewModal.classList.add('hidden');
        });
    }
}

/**
 * Dosya indirme işlemlerini başlatır
 */
function initFileDownload() {
    const downloadBtn = document.getElementById('downloadFileBtn');
    
    if (downloadBtn) {
        downloadBtn.addEventListener('click', function() {
            const path = this.getAttribute('data-path');
            if (path) {
                const link = document.createElement('a');
                link.href = path;
                link.download = path.split('/').pop();
                link.click();
            }
        });
    }
}

/**
 * Dosya silme işlemlerini başlatır
 */
function initFileDelete() {
    const deleteBtn = document.getElementById('deleteFileBtn');
    const previewModal = document.getElementById('previewModal');
    
    if (deleteBtn && previewModal) {
        deleteBtn.addEventListener('click', function() {
            const path = this.getAttribute('data-path');
            if (path && confirm('Bu dosyayı silmek istediğinize emin misiniz?')) {
                fetch('api/delete_file.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ path: path })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        previewModal.classList.add('hidden');
                        // Sayfayı yenile
                        location.reload();
                    } else {
                        alert('Dosya silinemedi: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Bir hata oluştu: ' + error);
                });
            }
        });
    }
}

/**
 * Toplu dosya seçimi işlemlerini başlatır
 */
function initBulkFileSelection() {
    // Toplu seçim butonunu bul
    const selectAllBtn = document.getElementById('selectAllFiles');
    const bulkActionsDiv = document.getElementById('bulkActions');
    const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
    
    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function() {
            // Tüm dosya seçim kutularının durumunu değiştir
            const checkboxes = document.querySelectorAll('.file-checkbox');
            const isChecked = this.checked;
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = isChecked;
            });
            
            // Toplu işlem butonlarını göster/gizle
            toggleBulkActions();
        });
    }
    
    // Dosya seçim kutularına olay ekle
    const checkboxes = document.querySelectorAll('.file-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            toggleBulkActions();
        });
    });
    
    // Toplu silme butonu
    if (bulkDeleteBtn) {
        bulkDeleteBtn.addEventListener('click', function() {
            const selectedFiles = getSelectedFiles();
            
            if (selectedFiles.length === 0) {
                alert('Lütfen önce dosya seçin.');
                return;
            }
            
            if (confirm(`${selectedFiles.length} dosyayı silmek istediğinize emin misiniz?`)) {
                bulkDeleteFiles(selectedFiles);
            }
        });
    }
}

/**
 * Toplu işlem butonlarını göster/gizle
 */
function toggleBulkActions() {
    const selectedFiles = getSelectedFiles();
    const bulkActionsDiv = document.getElementById('bulkActions');
    
    if (bulkActionsDiv) {
        if (selectedFiles.length > 0) {
            bulkActionsDiv.classList.remove('hidden');
            const fileCountSpan = document.getElementById('selectedFileCount');
            if (fileCountSpan) {
                fileCountSpan.textContent = selectedFiles.length;
            }
        } else {
            bulkActionsDiv.classList.add('hidden');
        }
    }
}

/**
 * Seçili dosyaların yollarını alır
 * @returns {Array} Seçili dosya yollarının dizisi
 */
function getSelectedFiles() {
    const checkboxes = document.querySelectorAll('.file-checkbox:checked');
    const selectedFiles = [];
    
    checkboxes.forEach(checkbox => {
        selectedFiles.push(checkbox.value);
    });
    
    return selectedFiles;
}

/**
 * Toplu dosya silme işlemini gerçekleştirir
 * @param {Array} files - Silinecek dosya yollarının dizisi
 */
function bulkDeleteFiles(files) {
    fetch('api/bulk_delete_files.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ files: files })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(`İşlem tamamlandı: ${data.success_count} dosya silindi, ${data.error_count} hata oluştu.`);
            // Sayfayı yenile
            location.reload();
        } else {
            alert('Dosyalar silinemedi: ' + data.message);
        }
    })
    .catch(error => {
        alert('Bir hata oluştu: ' + error);
    });
}

/**
 * Klasör bilgilerini yükler ve gösterir
 */
function loadFolderInfo() {
    const folderInfoContainer = document.getElementById('folderInfoContainer');
    
    if (folderInfoContainer) {
        // URL'den mevcut klasör yolunu al
        const urlParams = new URLSearchParams(window.location.search);
        const currentPath = urlParams.get('path') || 'img';
        
        // Klasör bilgilerini getir
        fetch(`api/get_folder_info.php?path=${currentPath}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Klasör bilgilerini göster
                    let html = `
                        <div class="bg-gray-50 p-4 rounded-lg mb-4">
                            <h3 class="text-sm font-medium text-gray-700">Klasör Bilgileri</h3>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-2 mt-2">
                                <div>
                                    <span class="text-xs text-gray-500">Toplam Dosya:</span>
                                    <span class="block text-sm font-medium">${data.total_files}</span>
                                </div>
                                <div>
                                    <span class="text-xs text-gray-500">Toplam Boyut:</span>
                                    <span class="block text-sm font-medium">${data.formatted_size}</span>
                                </div>
                                <div>
                                    <span class="text-xs text-gray-500">Resim Sayısı:</span>
                                    <span class="block text-sm font-medium">${data.image_count}</span>
                                </div>
                                <div>
                                    <span class="text-xs text-gray-500">PDF Sayısı:</span>
                                    <span class="block text-sm font-medium">${data.pdf_count}</span>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    folderInfoContainer.innerHTML = html;
                }
            })
            .catch(error => {
                console.error('Klasör bilgileri yüklenirken hata:', error);
            });
    }
}

/**
 * Klasör oluşturma işlemlerini başlatır
 */
function initFolderCreation() {
    // Yeni klasör - resimler
    const imageFolder = document.getElementById('upload_folder');
    const newFolderContainer = document.getElementById('new_folder_container');
    
    if (imageFolder && newFolderContainer) {
        imageFolder.addEventListener('change', function() {
            if (this.value === 'new') {
                newFolderContainer.classList.remove('hidden');
            } else {
                newFolderContainer.classList.add('hidden');
            }
        });
    }
    
    // Yeni klasör - PDF
    const pdfCategory = document.getElementById('pdf_category');
    const newPdfCategoryContainer = document.getElementById('new_pdf_category_container');
    
    if (pdfCategory && newPdfCategoryContainer) {
        pdfCategory.addEventListener('change', function() {
            if (this.value === 'new') {
                newPdfCategoryContainer.classList.remove('hidden');
            } else {
                newPdfCategoryContainer.classList.add('hidden');
            }
        });
    }
    
    // Form gönderimi öncesi yeni klasör oluşturma
    const imageForm = document.querySelector('form[name="submit_image"]');
    const pdfForm = document.querySelector('form[name="submit_pdf"]');
    
    if (imageForm) {
        imageForm.addEventListener('submit', function(e) {
            if (document.getElementById('upload_folder').value === 'new') {
                e.preventDefault();
                createNewFolder('img', document.getElementById('new_folder').value, imageForm);
            }
        });
    }
    
    if (pdfForm) {
        pdfForm.addEventListener('submit', function(e) {
            if (document.getElementById('pdf_category').value === 'new') {
                e.preventDefault();
                createNewFolder('pdf', document.getElementById('new_pdf_category').value, pdfForm);
            }
        });
    }
}

/**
 * Yeni klasör oluşturma API'sini çağırır
 * @param {string} parentPath - Üst klasör yolu
 * @param {string} folderName - Yeni klasör adı
 * @param {HTMLFormElement} form - Gönderilecek form
 */
function createNewFolder(parentPath, folderName, form) {
    if (!folderName) {
        alert('Lütfen klasör adı girin.');
        return;
    }
    
    fetch('api/create_directory.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            parent_path: parentPath,
            folder_name: folderName
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (parentPath === 'img') {
                // Yeni klasörü select listesine ekle
                const selectElement = document.getElementById('upload_folder');
                const newOption = document.createElement('option');
                newOption.value = folderName;
                newOption.textContent = folderName;
                
                // Yeni seçeneği "Yeni Klasör" seçeneğinden önce ekle
                const newFolderOption = selectElement.querySelector('option[value="new"]');
                selectElement.insertBefore(newOption, newFolderOption);
                
                // Yeni klasörü seç
                selectElement.value = folderName;
                
                // Yeni klasör alanını gizle
                document.getElementById('new_folder_container').classList.add('hidden');
            } else if (parentPath === 'pdf') {
                // Yeni kategoriyi select listesine ekle
                const selectElement = document.getElementById('pdf_category');
                const newOption = document.createElement('option');
                newOption.value = folderName;
                newOption.textContent = folderName;
                
                // Yeni seçeneği "Yeni Kategori" seçeneğinden önce ekle
                const newCategoryOption = selectElement.querySelector('option[value="new"]');
                selectElement.insertBefore(newOption, newCategoryOption);
                
                // Yeni kategoriyi seç
                selectElement.value = folderName;
                
                // Yeni kategori alanını gizle
                document.getElementById('new_pdf_category_container').classList.add('hidden');
            }
            
            // Formu gönder
            form.submit();
        } else {
            alert('Klasör oluşturma hatası: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Hata:', error);
        alert('Klasör oluşturulurken bir hata oluştu.');
    });
}