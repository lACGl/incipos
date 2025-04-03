// Fatura HTML'den içe aktarma işlevi
function importInvoiceFromHTML(faturaId) {
    Swal.fire({
        title: 'HTML Fatura Dosyasını Yükle',
        html: `
            <div class="text-left mb-4">
                <p class="mb-2">E-fatura HTML dosyasını seçerek otomatik ürün aktarımı yapabilirsiniz.</p>
                <form id="uploadForm" class="mt-4">
                    <input type="hidden" name="fatura_id" value="${faturaId}">
                    <input type="file" id="htmlFileInput" name="html_file" 
                           class="form-control" 
                           accept=".html,.htm">
                    <div class="text-xs text-gray-500 mt-2">
                        * Sadece HTML formatında e-fatura dosyaları desteklenmektedir.
                    </div>
                </form>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'İçe Aktar',
        cancelButtonText: 'İptal',
        showLoaderOnConfirm: true,
        preConfirm: () => {
            const fileInput = document.getElementById('htmlFileInput');
            const form = document.getElementById('uploadForm');
            const formData = new FormData(form);
            
            if (!fileInput.files || fileInput.files.length === 0) {
                Swal.showValidationMessage('Lütfen bir dosya seçin');
                return false;
            }
            
            return fetch('api/import_invoice_html.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(result => {
                if (!result.success) {
                    throw new Error(result.message || 'İçe aktarma sırasında bir hata oluştu');
                }
                return result;
            })
            .catch(error => {
                Swal.showValidationMessage(`İçe aktarma hatası: ${error.message}`);
                return false;
            });
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
        if (result.isConfirmed && result.value && result.value.success) {
            Swal.fire({
                icon: 'success',
                title: 'Başarılı!',
                text: result.value.message,
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                // Sayfayı yenile
                location.reload();
            });
        }
    });
}

// Fatura sayfasına import butonu ekleyen fonksiyon
function addImportButtonToInvoicePage() {
    // Alış faturaları sayfasındaki tüm fatura satırlarını bul
    const invoiceRows = document.querySelectorAll('tr.hover\\:bg-gray-50');
    
    invoiceRows.forEach(row => {
        // Satırdaki işlemler hücresini bul
        const actionsCell = row.querySelector('td:last-child');
        
        if (actionsCell) {
            // Fatura durumunu kontrol et (sadece boş faturalara import ekle)
            const statusCell = row.querySelector('td:nth-child(5)');
            if (statusCell && statusCell.textContent.includes('Yeni Fatura')) {
                // Satırın ID'sini al (data-id attribute'u veya benzeri bir yerden)
                const invoiceIdMatch = row.innerHTML.match(/fatura_id=(\d+)/);
                if (invoiceIdMatch && invoiceIdMatch[1]) {
                    const invoiceId = invoiceIdMatch[1];
                    
                    // Import butonunu ekle
                    const importButton = document.createElement('button');
                    importButton.innerHTML = `
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"/>
                        </svg>
                    `;
                    importButton.className = 'text-purple-600 hover:text-purple-900 ml-2';
                    importButton.title = 'HTML\'den İçe Aktar';
                    importButton.onclick = (e) => {
                        e.stopPropagation();
                        importInvoiceFromHTML(invoiceId);
                    };
                    
                    // Buttonlar container'ına ekle
                    const buttonsContainer = actionsCell.querySelector('div');
                    if (buttonsContainer) {
                        buttonsContainer.appendChild(importButton);
                    }
                }
            }
        }
    });
}

// Sayfa yüklendiğinde import butonunu ekle
document.addEventListener('DOMContentLoaded', function() {
    // Butonları ekle
    addImportButtonToInvoicePage();
    
    // Ayrıca purchase_invoices.js dosyasındaki addProducts fonksiyonunu genişlet
    // Orijinal addProducts fonksiyonunu koru
    const originalAddProducts = window.addProducts;
    
    window.addProducts = function(faturaId) {
        Swal.fire({
            title: 'Ürün Ekleme Yöntemi',
            html: `
                <p class="mb-4">Ürünleri nasıl eklemek istersiniz?</p>
                <div class="flex space-x-4">
                    <button type="button" id="manualAddBtn" class="flex-1 bg-blue-500 hover:bg-blue-600 text-white px-3 py-2 rounded">
                        <i class="fas fa-edit mr-2"></i>Manuel Ekle
                    </button>
                    <button type="button" id="importHtmlBtn" class="flex-1 bg-purple-500 hover:bg-purple-600 text-white px-3 py-2 rounded">
                        <i class="fas fa-file-import mr-2"></i>HTML'den İçe Aktar
                    </button>
                </div>
            `,
            showConfirmButton: false,
            showCancelButton: true,
            cancelButtonText: 'İptal'
        }).then((result) => {
            if (result.dismiss === Swal.DismissReason.close || 
                result.dismiss === Swal.DismissReason.cancel) {
                return;
            }
        });
        
        // Manuel ekleme butonu
        document.getElementById('manualAddBtn').addEventListener('click', function() {
            Swal.close();
            originalAddProducts(faturaId);
        });
        
        // HTML'den içe aktarma butonu
        document.getElementById('importHtmlBtn').addEventListener('click', function() {
            Swal.close();
            importInvoiceFromHTML(faturaId);
        });
    };
});