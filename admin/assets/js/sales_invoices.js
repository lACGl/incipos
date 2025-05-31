/**
 * Satış Faturaları sayfası için JavaScript işlevleri
 */

document.addEventListener('DOMContentLoaded', function() {
    // Tüm faturaları seç/bırak
    initSelectAllCheckbox();
    
    // Tarih filtrelerini son 30 güne ayarla
    initDateFilters();
    
    // Satış raporunu yükle
    initSalesReport();
});

/**
 * Tüm faturaları seç/bırak checkbox işlemlerini başlatır
 */
function initSelectAllCheckbox() {
    const selectAllCheckbox = document.getElementById('selectAll');
    
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const invoiceCheckboxes = document.querySelectorAll('.invoice-checkbox');
            invoiceCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            
            // Toplu işlem butonlarının durumunu güncelle
            updateBulkActionButtons();
        });
        
        // Her fatura checkbox'ı değiştiğinde kontrol et
        const invoiceCheckboxes = document.querySelectorAll('.invoice-checkbox');
        invoiceCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                updateSelectAllCheckbox();
                updateBulkActionButtons();
            });
        });
    }
}

/**
 * "Tümünü Seç" checkbox'ının durumunu günceller
 */
function updateSelectAllCheckbox() {
    const selectAllCheckbox = document.getElementById('selectAll');
    const invoiceCheckboxes = document.querySelectorAll('.invoice-checkbox');
    
    if (selectAllCheckbox && invoiceCheckboxes.length > 0) {
        // Tüm checkbox'ların seçili olup olmadığını kontrol et
        const allChecked = Array.from(invoiceCheckboxes).every(checkbox => checkbox.checked);
        const anyChecked = Array.from(invoiceCheckboxes).some(checkbox => checkbox.checked);
        
        selectAllCheckbox.checked = allChecked;
        selectAllCheckbox.indeterminate = anyChecked && !allChecked;
    }
}

/**
 * Toplu işlem butonlarının durumunu günceller
 */
function updateBulkActionButtons() {
    const selectedInvoices = getSelectedInvoices();
    const bulkButtons = document.querySelectorAll('.bulk-action-btn');
    
    bulkButtons.forEach(button => {
        if (selectedInvoices.length > 0) {
            button.classList.remove('opacity-50', 'cursor-not-allowed');
            button.disabled = false;
        } else {
            button.classList.add('opacity-50', 'cursor-not-allowed');
            button.disabled = true;
        }
    });
}

/**
 * Tarih filtrelerini başlatır
 */
function initDateFilters() {
    const startDateInput = document.querySelector('input[name="start_date"]');
    const endDateInput = document.querySelector('input[name="end_date"]');
    
    if (startDateInput && endDateInput) {
        // Varsayılan tarih değerleri belirtilmemişse, son 30 günü ayarla
        if (!startDateInput.value) {
            const thirtyDaysAgo = new Date();
            thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
            startDateInput.value = formatDate(thirtyDaysAgo);
        }
        
        if (!endDateInput.value) {
            const today = new Date();
            endDateInput.value = formatDate(today);
        }
        
        // Tarih değiştiğinde filtreleri uygula
        startDateInput.addEventListener('change', function() {
            if (this.value > endDateInput.value) {
                endDateInput.value = this.value;
            }
        });
        
        endDateInput.addEventListener('change', function() {
            if (this.value < startDateInput.value) {
                startDateInput.value = this.value;
            }
        });
    }
}

/**
 * Tarihi yyyy-mm-dd formatına dönüştürür
 * @param {Date} date Dönüştürülecek tarih
 * @returns {string} yyyy-mm-dd formatında tarih
 */
function formatDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    
    return `${year}-${month}-${day}`;
}

/**
 * Satış raporu grafiklerini başlatır
 */
function initSalesReport() {
    // Bu fonksiyon, gerekirse Chart.js gibi bir kütüphane kullanılarak satış grafikleri oluşturmak için kullanılabilir
    // Şu an için boş bırakılmıştır
}

/**
 * Seçili faturaları getirir
 * @returns {Array} Seçili fatura ID'lerinin dizisi
 */
function getSelectedInvoices() {
    const checkboxes = document.querySelectorAll('.invoice-checkbox:checked');
    return Array.from(checkboxes).map(checkbox => checkbox.value);
}

/**
 * Fatura detaylarını görüntüler
 * @param {number} invoiceId Fatura ID
 */
function viewInvoice(invoiceId) {
    document.getElementById('invoiceDetailModal').classList.remove('hidden');
    document.getElementById('invoiceDetailTitle').textContent = 'Fatura Detayları Yükleniyor...';
    document.getElementById('invoiceDetailContent').innerHTML = `
        <div class="text-center py-10">
            <svg class="animate-spin h-10 w-10 text-blue-500 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <p class="mt-2 text-gray-600">Yükleniyor...</p>
        </div>
    `;

    // Fatura detaylarını getir
    fetch('api/get_sales_invoice_details.php?id=' + invoiceId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Fatura başlık bilgileri
                const invoice = data.invoice;
                const details = data.details;
                
                document.getElementById('invoiceDetailTitle').textContent = 'Fatura: ' + invoice.fatura_seri + invoice.fatura_no;
                
                // Fatura detay içeriğini oluştur
                let content = `
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <div>
                            <h4 class="text-sm font-medium text-gray-500">Fatura Bilgileri</h4>
                            <div class="mt-2">
                                <p><span class="font-medium">Fatura No:</span> ${invoice.fatura_seri}${invoice.fatura_no}</p>
                                <p><span class="font-medium">Tarih:</span> ${new Date(invoice.fatura_tarihi).toLocaleString('tr-TR')}</p>
                                <p><span class="font-medium">İşlem Türü:</span> ${invoice.islem_turu === 'satis' ? 'Satış' : 'İade'}</p>
                                <p><span class="font-medium">Ödeme Türü:</span> ${invoice.odeme_turu === 'nakit' ? 'Nakit' : invoice.odeme_turu === 'kredi_karti' ? 'Kredi Kartı' : 'Havale'}</p>
                                ${invoice.kredi_karti_banka ? `<p><span class="font-medium">Banka:</span> ${invoice.kredi_karti_banka}</p>` : ''}
                            </div>
                        </div>
                        <div>
                            <h4 class="text-sm font-medium text-gray-500">Müşteri & Mağaza Bilgileri</h4>
                            <div class="mt-2">
                                ${invoice.musteri_adi ? `<p><span class="font-medium">Müşteri:</span> ${invoice.musteri_adi} ${invoice.musteri_soyad}</p>` : ''}
                                <p><span class="font-medium">Mağaza:</span> ${invoice.magaza_adi}</p>
                                <p><span class="font-medium">Personel:</span> ${invoice.personel_adi}</p>
                            </div>
                        </div>
                    </div>
                    
                    <h4 class="text-sm font-medium text-gray-500 mb-2">Fatura Kalemleri</h4>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ürün</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Miktar</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Birim Fiyat</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">KDV Oranı</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">İndirim</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Toplam</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                `;
                
                // Fatura kalemlerini ekle
                details.forEach(item => {
                    content += `
                        <tr>
                            <td class="px-4 py-2 whitespace-nowrap">${item.urun_adi}</td>
                            <td class="px-4 py-2 text-right whitespace-nowrap">${item.miktar}</td>
                            <td class="px-4 py-2 text-right whitespace-nowrap">${parseFloat(item.birim_fiyat).toLocaleString('tr-TR', {minimumFractionDigits: 2})} ₺</td>
                            <td class="px-4 py-2 text-right whitespace-nowrap">%${parseFloat(item.kdv_orani).toLocaleString('tr-TR')}</td>
                            <td class="px-4 py-2 text-right whitespace-nowrap">%${parseFloat(item.indirim_orani || 0).toLocaleString('tr-TR')}</td>
                            <td class="px-4 py-2 text-right whitespace-nowrap font-medium">${parseFloat(item.toplam_tutar).toLocaleString('tr-TR', {minimumFractionDigits: 2})} ₺</td>
                        </tr>
                    `;
                });
                
                // Toplam alanları ekle
                content += `
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="flex justify-end mt-4">
                        <div class="w-64">
                            <div class="flex justify-between py-2 border-t">
                                <span class="font-medium">Ara Toplam:</span>
                                <span>${parseFloat(invoice.toplam_tutar).toLocaleString('tr-TR', {minimumFractionDigits: 2})} ₺</span>
                            </div>
                            <div class="flex justify-between py-2 border-t">
                                <span class="font-medium">KDV Tutarı:</span>
                                <span>${parseFloat(invoice.kdv_tutari).toLocaleString('tr-TR', {minimumFractionDigits: 2})} ₺</span>
                            </div>
                            <div class="flex justify-between py-2 border-t">
                                <span class="font-medium">İndirim Tutarı:</span>
                                <span>${parseFloat(invoice.indirim_tutari).toLocaleString('tr-TR', {minimumFractionDigits: 2})} ₺</span>
                            </div>
                            <div class="flex justify-between py-2 border-t text-lg font-bold">
                                <span>Genel Toplam:</span>
                                <span>${parseFloat(invoice.net_tutar).toLocaleString('tr-TR', {minimumFractionDigits: 2})} ₺</span>
                            </div>
                        </div>
                    </div>
                `;
                
                // Fatura notları
                if (invoice.aciklama) {
                    content += `
                        <div class="mt-6 border-t pt-4">
                            <h4 class="text-sm font-medium text-gray-500 mb-2">Notlar</h4>
                            <p class="text-gray-700">${invoice.aciklama}</p>
                        </div>
                    `;
                }
                
                document.getElementById('invoiceDetailContent').innerHTML = content;
            } else {
                document.getElementById('invoiceDetailContent').innerHTML = `
                    <div class="text-center py-10">
                        <svg class="h-10 w-10 text-red-500 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p class="mt-2 text-gray-600">Fatura detayları yüklenirken bir hata oluştu.</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Hata:', error);
            document.getElementById('invoiceDetailContent').innerHTML = `
                <div class="text-center py-10">
                    <svg class="h-10 w-10 text-red-500 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <p class="mt-2 text-gray-600">Bir hata oluştu: ${error.message}</p>
                </div>
            `;
        });
}

/**
 * Fatura detay modalını kapatır
 */
function closeInvoiceDetailModal() {
    document.getElementById('invoiceDetailModal').classList.add('hidden');
}

/**
 * Faturayı yazdırır
 * @param {number} invoiceId Fatura ID
 */
function printInvoice(invoiceId) {
    Swal.fire({
        title: 'Fatura Yazdırılıyor',
        text: 'Fatura yazdırma penceresi açılıyor...',
        icon: 'info',
        timer: 2000,
        showConfirmButton: false
    });
    
    // Yazdırma sayfasını açma
    window.open('print_invoice.php?id=' + invoiceId, '_blank');
}

/**
 * Fatura detaylarını yazdırır
 */
function printInvoiceDetail() {
    const printContent = document.getElementById('invoiceDetailContent').innerHTML;
    const printWindow = window.open('', '_blank');
    
    printWindow.document.write(`
        <html>
        <head>
            <title>Fatura Yazdır</title>
            <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
            <style>
                body { padding: 20px; }
                @media print {
                    body { padding: 0; }
                    button { display: none !important; }
                }
            </style>
        </head>
        <body class="bg-white">
            <div class="max-w-4xl mx-auto">
                <div class="flex justify-between items-center mb-6">
                    <h1 class="text-2xl font-bold">İnciPOS</h1>
                    <div class="text-right">
                        <h2 class="text-xl font-semibold">${document.getElementById('invoiceDetailTitle').textContent}</h2>
                        <p class="text-gray-600">Tarih: ${new Date().toLocaleDateString('tr-TR')}</p>
                    </div>
                </div>
                
                <div class="mb-6 border-b pb-4"></div>
                
                ${printContent}
                
                <div class="mt-8 text-center text-gray-500">
                    <p>Bu bir İnciPOS fatura çıktısıdır.</p>
                </div>
            </div>
            
            <script>
                window.onload = function() {
                    window.print();
                    setTimeout(function() { window.close(); }, 500);
                }
            </script>
        </body>
        </html>
    `);
    
    printWindow.document.close();
}

/**
 * Seçili faturaları yazdırır
 */
function printSelected() {
    const selectedInvoices = getSelectedInvoices();
    
    if (selectedInvoices.length === 0) {
        Swal.fire({
            title: 'Uyarı',
            text: 'Lütfen yazdırmak için en az bir fatura seçin.',
            icon: 'warning',
            confirmButtonText: 'Tamam'
        });
        return;
    }
    
    Swal.fire({
        title: 'Faturalar Yazdırılıyor',
        text: `${selectedInvoices.length} adet fatura yazdırılacak. Onaylıyor musunuz?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Evet',
        cancelButtonText: 'İptal'
    }).then((result) => {
        if (result.isConfirmed) {
            // Her fatura için yazdırma penceresi aç
            selectedInvoices.forEach((invoiceId, index) => {
                setTimeout(() => {
                    window.open('print_invoice.php?id=' + invoiceId, '_blank');
                }, index * 500); // Tarayıcının pop-up engelleme sorununu önlemek için gecikme
            });
        }
    });
}

/**
 * Excel'e aktarır
 */
function exportToExcel() {
    const selectedInvoices = getSelectedInvoices();
    const filterParams = new URLSearchParams(window.location.search);
    
    let url = 'excel_export_sales.php';
    
    // Eğer fatura seçildiyse sadece seçili faturaları dışa aktar
    if (selectedInvoices.length > 0) {
        url += '?selected=' + selectedInvoices.join(',');
    } 
    // Aksi halde filtreye göre dışa aktar
    else {
        url += '?' + filterParams.toString();
    }
    
    window.location.href = url;
}

/**
 * E-Arşive gönderir
 */
function sendToEArchive() {
    const selectedInvoices = getSelectedInvoices();
    
    if (selectedInvoices.length === 0) {
        Swal.fire({
            title: 'Uyarı',
            text: 'Lütfen e-arşive göndermek için en az bir fatura seçin.',
            icon: 'warning',
            confirmButtonText: 'Tamam'
        });
        return;
    }
    
    Swal.fire({
        title: 'E-Arşiv İşlemi',
        text: `${selectedInvoices.length} adet fatura e-arşive gönderilecek. Onaylıyor musunuz?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Evet',
        cancelButtonText: 'İptal'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'İşlem Başlatıldı',
                text: 'E-Arşiv işlemi başlatıldı, bu işlem biraz zaman alabilir.',
                icon: 'info',
                timer: 2000,
                showConfirmButton: false
            });
            
            // E-arşive gönderme API'sini çağır
            fetch('api/send_to_earchive.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ invoice_ids: selectedInvoices }),
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'Başarılı',
                        text: data.message || 'Faturalar başarıyla e-arşive gönderildi.',
                        icon: 'success',
                        confirmButtonText: 'Tamam'
                    });
                } else {
                    Swal.fire({
                        title: 'Hata',
                        text: data.message || 'E-Arşive gönderilirken bir hata oluştu.',
                        icon: 'error',
                        confirmButtonText: 'Tamam'
                    });
                }
            })
            .catch(error => {
                console.error('Hata:', error);
                Swal.fire({
                    title: 'Hata',
                    text: 'Beklenmeyen bir hata oluştu.',
                    icon: 'error',
                    confirmButtonText: 'Tamam'
                });
            });
        }
    });
}

/**
 * Tekli e-fatura gönderir
 * @param {number} invoiceId Fatura ID
 */
function sendToEInvoice(invoiceId) {
    Swal.fire({
        title: 'E-Fatura Gönderimi',
        text: 'Bu faturayı e-fatura olarak göndermek istiyor musunuz?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Evet',
        cancelButtonText: 'İptal'
    }).then((result) => {
        if (result.isConfirmed) {
            // E-fatura gönderim API'sini çağır
            fetch('api/send_to_einvoice.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ invoice_id: invoiceId }),
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'Başarılı',
                        text: data.message || 'Fatura başarıyla e-fatura olarak gönderildi.',
                        icon: 'success',
                        confirmButtonText: 'Tamam'
                    });
                } else {
                    Swal.fire({
                        title: 'Hata',
                        text: data.message || 'E-Fatura gönderilirken bir hata oluştu.',
                        icon: 'error',
                        confirmButtonText: 'Tamam'
                    });
                }
            })
            .catch(error => {
                console.error('Hata:', error);
                Swal.fire({
                    title: 'Hata',
                    text: 'Beklenmeyen bir hata oluştu.',
                    icon: 'error',
                    confirmButtonText: 'Tamam'
                });
            });
        }
    });
}

/**
 * Yeni satış faturası oluşturur
 */
function addInvoice() {
    window.location.href = 'create_sales_invoice.php';
}

/**
 * İade faturası oluşturur
 * @param {number} invoiceId Fatura ID
 */
function createReturn(invoiceId) {
    Swal.fire({
        title: 'İade İşlemi',
        text: 'Bu fatura için iade işlemi oluşturmak istiyor musunuz?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Evet',
        cancelButtonText: 'İptal'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'create_return_invoice.php?id=' + invoiceId;
        }
    });
}

    // Tüm faturaları seç/bırak
    document.getElementById('selectAll').addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.invoice-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
    });

    // Fatura detaylarını görüntüle
    function viewInvoice(invoiceId) {
        document.getElementById('invoiceDetailModal').classList.remove('hidden');
        document.getElementById('invoiceDetailTitle').textContent = 'Fatura Detayları Yükleniyor...';
        document.getElementById('invoiceDetailContent').innerHTML = `
            <div class="text-center py-10">
                <svg class="animate-spin h-10 w-10 text-blue-500 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <p class="mt-2 text-gray-600">Yükleniyor...</p>
            </div>
        `;

        // Fatura detaylarını getir
        fetch('api/get_sales_invoice_details.php?id=' + invoiceId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Fatura başlık bilgileri
                    const invoice = data.invoice;
                    const details = data.details;
                    
                    document.getElementById('invoiceDetailTitle').textContent = 'Fatura: ' + invoice.fatura_seri + invoice.fatura_no;
                    
                    // Fatura detay içeriğini oluştur
                    let content = `
                        <div class="grid grid-cols-2 gap-4 mb-6">
                            <div>
                                <h4 class="text-sm font-medium text-gray-500">Fatura Bilgileri</h4>
                                <div class="mt-2">
                                    <p><span class="font-medium">Fatura No:</span> ${invoice.fatura_seri}${invoice.fatura_no}</p>
                                    <p><span class="font-medium">Tarih:</span> ${new Date(invoice.fatura_tarihi).toLocaleString('tr-TR')}</p>
                                    <p><span class="font-medium">İşlem Türü:</span> ${invoice.islem_turu === 'satis' ? 'Satış' : 'İade'}</p>
                                    <p><span class="font-medium">Ödeme Türü:</span> ${invoice.odeme_turu === 'nakit' ? 'Nakit' : invoice.odeme_turu === 'kredi_karti' ? 'Kredi Kartı' : 'Havale'}</p>
                                    ${invoice.kredi_karti_banka ? `<p><span class="font-medium">Banka:</span> ${invoice.kredi_karti_banka}</p>` : ''}
                                </div>
                            </div>
                            <div>
                                <h4 class="text-sm font-medium text-gray-500">Müşteri & Mağaza Bilgileri</h4>
                                <div class="mt-2">
                                    ${invoice.musteri_adi ? `<p><span class="font-medium">Müşteri:</span> ${invoice.musteri_adi} ${invoice.musteri_soyad}</p>` : ''}
                                    <p><span class="font-medium">Mağaza:</span> ${invoice.magaza_adi}</p>
                                    <p><span class="font-medium">Personel:</span> ${invoice.personel_adi}</p>
                                </div>
                            </div>
                        </div>
                        
                        <h4 class="text-sm font-medium text-gray-500 mb-2">Fatura Kalemleri</h4>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ürün</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Miktar</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Birim Fiyat</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">KDV Oranı</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">İndirim</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Toplam</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                    `;
                    
                    // Fatura kalemlerini ekle
                    details.forEach(item => {
                        content += `
                            <tr>
                                <td class="px-4 py-2 whitespace-nowrap">${item.urun_adi}</td>
                                <td class="px-4 py-2 text-right whitespace-nowrap">${item.miktar}</td>
                                <td class="px-4 py-2 text-right whitespace-nowrap">${parseFloat(item.birim_fiyat).toLocaleString('tr-TR', {minimumFractionDigits: 2})} ₺</td>
                                <td class="px-4 py-2 text-right whitespace-nowrap">%${parseFloat(item.kdv_orani).toLocaleString('tr-TR')}</td>
                                <td class="px-4 py-2 text-right whitespace-nowrap">%${parseFloat(item.indirim_orani || 0).toLocaleString('tr-TR')}</td>
                                <td class="px-4 py-2 text-right whitespace-nowrap font-medium">${parseFloat(item.toplam_tutar).toLocaleString('tr-TR', {minimumFractionDigits: 2})} ₺</td>
                            </tr>
                        `;
                    });
                    
                    // Toplam alanları ekle
                    content += `
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="flex justify-end mt-4">
                            <div class="w-64">
                                <div class="flex justify-between py-2 border-t">
                                    <span class="font-medium">Ara Toplam:</span>
                                    <span>${parseFloat(invoice.toplam_tutar).toLocaleString('tr-TR', {minimumFractionDigits: 2})} ₺</span>
                                </div>
                                <div class="flex justify-between py-2 border-t">
                                    <span class="font-medium">KDV Tutarı:</span>
                                    <span>${parseFloat(invoice.kdv_tutari).toLocaleString('tr-TR', {minimumFractionDigits: 2})} ₺</span>
                                </div>
                                <div class="flex justify-between py-2 border-t">
                                    <span class="font-medium">İndirim Tutarı:</span>
                                    <span>${parseFloat(invoice.indirim_tutari).toLocaleString('tr-TR', {minimumFractionDigits: 2})} ₺</span>
                                </div>
                                <div class="flex justify-between py-2 border-t text-lg font-bold">
                                    <span>Genel Toplam:</span>
                                    <span>${parseFloat(invoice.net_tutar).toLocaleString('tr-TR', {minimumFractionDigits: 2})} ₺</span>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    // Fatura notları
                    if (invoice.aciklama) {
                        content += `
                            <div class="mt-6 border-t pt-4">
                                <h4 class="text-sm font-medium text-gray-500 mb-2">Notlar</h4>
                                <p class="text-gray-700">${invoice.aciklama}</p>
                            </div>
                        `;
                    }
                    
                    document.getElementById('invoiceDetailContent').innerHTML = content;
                } else {
                    document.getElementById('invoiceDetailContent').innerHTML = `
                        <div class="text-center py-10">
                            <svg class="h-10 w-10 text-red-500 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <p class="mt-2 text-gray-600">Fatura detayları yüklenirken bir hata oluştu.</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Hata:', error);
                document.getElementById('invoiceDetailContent').innerHTML = `
                    <div class="text-center py-10">
                        <svg class="h-10 w-10 text-red-500 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p class="mt-2 text-gray-600">Bir hata oluştu: ${error.message}</p>
                    </div>
                `;
            });
    }

    // Fatura detay modalını kapat
    function closeInvoiceDetailModal() {
        document.getElementById('invoiceDetailModal').classList.add('hidden');
    }

    // Faturayı yazdır
    function printInvoice(invoiceId) {
        Swal.fire({
            title: 'Fatura Yazdırılıyor',
            text: 'Fatura yazdırma penceresi açılıyor...',
            icon: 'info',
            timer: 2000,
            showConfirmButton: false
        });
        
        // Yazdırma sayfasını açma
        window.open('print_invoice.php?id=' + invoiceId, '_blank');
    }

    // Fatura detaylarını yazdır
    function printInvoiceDetail() {
        const printContent = document.getElementById('invoiceDetailContent').innerHTML;
        const printWindow = window.open('', '_blank');
        
        printWindow.document.write(`
            <html>
            <head>
                <title>Fatura Yazdır</title>
                <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
                <style>
                    body { padding: 20px; }
                    @media print {
                        body { padding: 0; }
                        button { display: none !important; }
                    }
                </style>
            </head>
            <body class="bg-white">
                <div class="max-w-4xl mx-auto">
                    <div class="flex justify-between items-center mb-6">
                        <h1 class="text-2xl font-bold">İnciPOS</h1>
                        <div class="text-right">
                            <h2 class="text-xl font-semibold">${document.getElementById('invoiceDetailTitle').textContent}</h2>
                            <p class="text-gray-600">Tarih: ${new Date().toLocaleDateString('tr-TR')}</p>
                        </div>
                    </div>
                    
                    <div class="mb-6 border-b pb-4"></div>
                    
                    ${printContent}
                    
                    <div class="mt-8 text-center text-gray-500">
                        <p>Bu bir İnciPOS fatura çıktısıdır.</p>
                    </div>
                </div>
                
                <script>
                    window.onload = function() {
                        window.print();
                        setTimeout(function() { window.close(); }, 500);
                    }
                </script>
            </body>
            </html>
        `);
        
        printWindow.document.close();
    }

    // Seçili faturaları yazdır
    function printSelected() {
        const selectedInvoices = getSelectedInvoices();
        
        if (selectedInvoices.length === 0) {
            Swal.fire({
                title: 'Uyarı',
                text: 'Lütfen yazdırmak için en az bir fatura seçin.',
                icon: 'warning',
                confirmButtonText: 'Tamam'
            });
            return;
        }
        
        Swal.fire({
            title: 'Faturalar Yazdırılıyor',
            text: `${selectedInvoices.length} adet fatura yazdırılacak. Onaylıyor musunuz?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Evet',
            cancelButtonText: 'İptal'
        }).then((result) => {
            if (result.isConfirmed) {
                // Her fatura için yazdırma penceresi aç
                selectedInvoices.forEach(invoiceId => {
                    setTimeout(() => {
                        window.open('print_invoice.php?id=' + invoiceId, '_blank');
                    }, 500);
                });
            }
        });
    }

    // Excel'e aktar
    function exportToExcel() {
        const selectedInvoices = getSelectedInvoices();
        const filterParams = new URLSearchParams(window.location.search);
        
        let url = 'excel_export_sales.php';
        
        // Eğer fatura seçildiyse sadece seçili faturaları dışa aktar
        if (selectedInvoices.length > 0) {
            url += '?selected=' + selectedInvoices.join(',');
        } 
        // Aksi halde filtreye göre dışa aktar
        else {
            url += '?' + filterParams.toString();
        }
        
        window.location.href = url;
    }

    // E-Arşive gönder
    function sendToEArchive() {
        const selectedInvoices = getSelectedInvoices();
        
        if (selectedInvoices.length === 0) {
            Swal.fire({
                title: 'Uyarı',
                text: 'Lütfen e-arşive göndermek için en az bir fatura seçin.',
                icon: 'warning',
                confirmButtonText: 'Tamam'
            });
            return;
        }
        
        Swal.fire({
            title: 'E-Arşiv İşlemi',
            text: `${selectedInvoices.length} adet fatura e-arşive gönderilecek. Onaylıyor musunuz?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Evet',
            cancelButtonText: 'İptal'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'İşlem Başlatıldı',
                    text: 'E-Arşiv işlemi başlatıldı, bu işlem biraz zaman alabilir.',
                    icon: 'info',
                    timer: 2000,
                    showConfirmButton: false
                });
                
                // Burada e-arşive gönderme API'sini çağırabilirsiniz
                fetch('api/send_to_earchive.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ invoice_ids: selectedInvoices }),
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            title: 'Başarılı',
                            text: data.message || 'Faturalar başarıyla e-arşive gönderildi.',
                            icon: 'success',
                            confirmButtonText: 'Tamam'
                        });
                    } else {
                        Swal.fire({
                            title: 'Hata',
                            text: data.message || 'E-Arşive gönderilirken bir hata oluştu.',
                            icon: 'error',
                            confirmButtonText: 'Tamam'
                        });
                    }
                })
                .catch(error => {
                    console.error('Hata:', error);
                    Swal.fire({
                        title: 'Hata',
                        text: 'Beklenmeyen bir hata oluştu.',
                        icon: 'error',
                        confirmButtonText: 'Tamam'
                    });
                });
            }
        });
    }

    // Tekli e-fatura gönderme
    function sendToEInvoice(invoiceId) {
        Swal.fire({
            title: 'E-Fatura Gönderimi',
            text: 'Bu faturayı e-fatura olarak göndermek istiyor musunuz?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Evet',
            cancelButtonText: 'İptal'
        }).then((result) => {
            if (result.isConfirmed) {
                // E-fatura gönderim API'sini çağır
                fetch('api/send_to_einvoice.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ invoice_id: invoiceId }),
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            title: 'Başarılı',
                            text: data.message || 'Fatura başarıyla e-fatura olarak gönderildi.',
                            icon: 'success',
                            confirmButtonText: 'Tamam'
                        });
                    } else {
                        Swal.fire({
                            title: 'Hata',
                            text: data.message || 'E-Fatura gönderilirken bir hata oluştu.',
                            icon: 'error',
                            confirmButtonText: 'Tamam'
                        });
                    }
                })
                .catch(error => {
                    console.error('Hata:', error);
                    Swal.fire({
                        title: 'Hata',
                        text: 'Beklenmeyen bir hata oluştu.',
                        icon: 'error',
                        confirmButtonText: 'Tamam'
                    });
                });
            }
        });
    }

    // Seçili faturaları al
    function getSelectedInvoices() {
        const checkboxes = document.querySelectorAll('.invoice-checkbox:checked');
        const selectedInvoices = Array.from(checkboxes).map(checkbox => checkbox.value);
        return selectedInvoices;
    }

    // Yeni satış faturası oluştur
    function addInvoice() {
        window.location.href = 'create_sales_invoice.php';
    }

    // İade faturası oluştur
    function createReturn(invoiceId) {
        Swal.fire({
            title: 'İade İşlemi',
            text: 'Bu fatura için iade işlemi oluşturmak istiyor musunuz?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Evet',
            cancelButtonText: 'İptal'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'create_return_invoice.php?id=' + invoiceId;
            }
        });
    }

    // Sayfayı her 5 dakikada bir yenile
    setTimeout(function() {
        location.reload();
    }, 5 * 60 * 1000); // 5 dakika