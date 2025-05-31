// Fatura durumları için helper fonksiyonlar

// Durum badge CSS class'ını döndüren fonksiyon
function getStatusBadgeClass(durum) {
    const classes = {
        'bos': 'bg-gray-100 text-gray-800 border border-gray-300',
        'urun_girildi': 'bg-yellow-100 text-yellow-800 border border-yellow-300',
        'aktarim_bekliyor': 'bg-blue-100 text-blue-800 border border-blue-300',
        'kismi_aktarildi': 'bg-orange-100 text-orange-800 border border-orange-300',
        'aktarildi': 'bg-green-100 text-green-800 border border-green-300'
    };
    return classes[durum] || 'bg-gray-100 text-gray-800 border border-gray-300';
}

function getRowBackgroundClass(durum) {
    const backgroundClasses = {
        'bos': 'bg-gray-100 hover:bg-gray-200 border-l-4 border-l-gray-500',
        'urun_girildi': 'bg-yellow-100 hover:bg-yellow-200 border-l-4 border-l-yellow-500',
        'aktarim_bekliyor': 'bg-blue-100 hover:bg-blue-200 border-l-4 border-l-blue-500',
        'kismi_aktarildi': 'bg-orange-100 hover:bg-orange-200 border-l-4 border-l-orange-500',
        'aktarildi': 'bg-green-100 hover:bg-green-200 border-l-4 border-l-green-500'
    };
    return backgroundClasses[durum] || 'bg-white hover:bg-gray-50';
}

// Satır arkaplan rengini uygulayan fonksiyon - Geliştirilmiş versiyon
function applyRowBackgroundColor(row, durum) {
    if (!row || !durum) {
        console.warn('Row veya durum parametresi eksik:', {row, durum});
        return;
    }
    
    console.log('Arkaplan rengi uygulanıyor:', durum, 'Row:', row);
    
    // Eski durum class'larını temizle
    const classesToRemove = [
        'bg-gray-50', 'bg-yellow-50', 'bg-blue-50', 'bg-orange-50', 'bg-green-50',
        'hover:bg-gray-100', 'hover:bg-yellow-100', 'hover:bg-blue-100', 
        'hover:bg-orange-100', 'hover:bg-green-100',
        'border-l-4', 'border-l-gray-400', 'border-l-yellow-400', 
        'border-l-blue-400', 'border-l-orange-400', 'border-l-green-400'
    ];
    
    classesToRemove.forEach(cls => {
        row.classList.remove(cls);
    });
    
    // Yeni class'ları ekle
    const backgroundClass = getRowBackgroundClass(durum);
    const newClasses = backgroundClass.split(' ');
    
    newClasses.forEach(cls => {
        if (cls.trim()) {
            row.classList.add(cls.trim());
        }
    });
    
    console.log('Uygulanan class\'lar:', newClasses);
}

// Durum progress bar'ı oluşturan fonksiyon
function createStatusProgressBar(durum) {
    const steps = [
        { key: 'bos', label: 'Yeni', completed: false },
        { key: 'urun_girildi', label: 'Ürün Girişi', completed: false },
        { key: 'aktarim_bekliyor', label: 'Aktarım Bekliyor', completed: false },
        { key: 'kismi_aktarildi', label: 'Kısmi Aktarım', completed: false },
        { key: 'aktarildi', label: 'Tamamlandı', completed: false }
    ];
    
    // Mevcut duruma kadar olan adımları tamamlanmış olarak işaretle
    const statusOrder = ['bos', 'urun_girildi', 'aktarim_bekliyor', 'kismi_aktarildi', 'aktarildi'];
    const currentIndex = statusOrder.indexOf(durum);
    
    for (let i = 0; i <= currentIndex; i++) {
        const step = steps.find(s => s.key === statusOrder[i]);
        if (step) step.completed = true;
    }
    
    let progressHTML = '<div class="flex items-center space-x-2">';
    
    steps.forEach((step, index) => {
        const isActive = step.key === durum;
        const isCompleted = step.completed && !isActive;
        
        let stepClass = 'w-8 h-8 rounded-full flex items-center justify-center text-xs font-medium';
        if (isCompleted) {
            stepClass += ' bg-green-500 text-white';
        } else if (isActive) {
            stepClass += ' bg-blue-500 text-white';
        } else {
            stepClass += ' bg-gray-200 text-gray-500';
        }
        
        progressHTML += `
            <div class="flex flex-col items-center">
                <div class="${stepClass}">
                    ${isCompleted ? '✓' : index + 1}
                </div>
                <span class="text-xs mt-1 ${isActive ? 'font-medium' : ''}">${step.label}</span>
            </div>
        `;
        
        // Son adım değilse connector ekle
        if (index < steps.length - 1) {
            progressHTML += `
                <div class="flex-1 h-0.5 ${step.completed ? 'bg-green-500' : 'bg-gray-200'}"></div>
            `;
        }
    });
    
    progressHTML += '</div>';
    return progressHTML;
}

// Durum metnini döndüren fonksiyon
function getStatusText(durum) {
    const texts = {
        'bos': 'Yeni Fatura',
        'urun_girildi': 'Ürün Girildi',
        'aktarim_bekliyor': 'Aktarım Bekliyor',
        'kismi_aktarildi': 'Kısmi Aktarıldı',
        'aktarildi': 'Tamamlandı'
    };
    return texts[durum] || 'Bilinmeyen';
}

// Durum ikonunu döndüren fonksiyon
function getStatusIcon(durum) {
    const icons = {
        'bos': '📄',
        'urun_girildi': '📝',
        'aktarim_bekliyor': '⏳',
        'kismi_aktarildi': '🔄',
        'aktarildi': '✅'
    };
    return icons[durum] || '❓';
}

// Durum açıklamasını döndüren fonksiyon
function getStatusDescription(durum) {
    const descriptions = {
        'bos': 'Fatura oluşturuldu, henüz ürün eklenmedi',
        'urun_girildi': 'Ürünler eklendi, düzenlenebilir',
        'aktarim_bekliyor': 'Fatura tamamlandı, aktarım yapılabilir',
        'kismi_aktarildi': 'Ürünlerin bir kısmı aktarıldı',
        'aktarildi': 'Tüm ürünler aktarıldı, işlem tamamlandı'
    };
    return descriptions[durum] || 'Durum bilinmiyor';
}

// Duruma göre mevcut işlemleri döndüren fonksiyon
function getAvailableActions(durum) {
    const actions = {
        'bos': ['add_product', 'edit', 'delete'],
        'urun_girildi': ['add_product', 'edit', 'delete'],
        'aktarim_bekliyor': ['transfer', 'view_details'],
        'kismi_aktarildi': ['transfer', 'view_details'],
        'aktarildi': ['view_details']
    };
    return actions[durum] || ['view_details'];
}

// HTML badge oluşturan fonksiyon
function createStatusBadge(durum) {
    const badgeClass = getStatusBadgeClass(durum);
    const statusText = getStatusText(durum);
    const statusIcon = getStatusIcon(durum);
    
    return `
        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${badgeClass}">
            <span class="mr-1">${statusIcon}</span>
            ${statusText}
        </span>
    `;
}

// Fatura listesini güncelleyen fonksiyon
function updateInvoiceStatusInTable(faturaId, newStatus) {
    const row = document.querySelector(`tr[data-fatura-id="${faturaId}"]`);
    if (row) {
        // Durum badge'ini güncelle
        const statusCell = row.querySelector('.status-cell');
        if (statusCell) {
            statusCell.innerHTML = createStatusBadge(newStatus);
        }
        
        // Satır arkaplan rengini güncelle
        applyRowBackgroundColor(row, newStatus);
        
        // İşlem butonlarını güncelle
        const actionsCell = row.querySelector('.actions-cell');
        if (actionsCell) {
            actionsCell.innerHTML = createActionButtons(faturaId, newStatus);
        }
    }
}

// İşlem butonlarını oluşturan fonksiyon
function createActionButtons(faturaId, durum) {
    const availableActions = getAvailableActions(durum);
    let buttonsHTML = '';
    
    availableActions.forEach(action => {
        switch(action) {
            case 'add_product':
                buttonsHTML += `
                    <button onclick="addProducts(${faturaId})" 
                            class="text-blue-600 hover:text-blue-800 mr-2 p-1 rounded transition-colors" 
                            title="Ürün Ekle">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                    </button>
                `;
                break;
            case 'transfer':
                buttonsHTML += `
                    <button onclick="transferToStore(${faturaId})" 
                            class="text-green-600 hover:text-green-800 mr-2 p-1 rounded transition-colors" 
                            title="Aktarım Yap">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                        </svg>
                    </button>
                `;
                break;
            case 'view_details':
                buttonsHTML += `
                    <button onclick="showInvoiceDetails(${faturaId})" 
                            class="text-purple-600 hover:text-purple-800 mr-2 p-1 rounded transition-colors" 
                            title="Detayları Görüntüle">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                    </button>
                `;
                break;
            case 'edit':
                buttonsHTML += `
                    <button onclick="editInvoice(${faturaId})" 
                            class="text-yellow-600 hover:text-yellow-800 mr-2 p-1 rounded transition-colors" 
                            title="Düzenle">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                    </button>
                `;
                break;
            case 'delete':
                buttonsHTML += `
                    <button onclick="deleteInvoice(${faturaId})" 
                            class="text-red-600 hover:text-red-800 mr-2 p-1 rounded transition-colors" 
                            title="Sil">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                `;
                break;
        }
    });
    
    return buttonsHTML;
}

// Geliştirilmiş applyAllRowBackgrounds fonksiyonu
function applyAllRowBackgrounds() {
    console.log('🎨 Tüm satırlara arkaplan renkleri uygulanıyor...');
    
    // Tüm fatura satırlarını bul
    const rows = document.querySelectorAll('tbody tr[data-durum]');
    
    console.log(`Toplam ${rows.length} satır bulundu`);
    
    rows.forEach((row, index) => {
        const durum = row.getAttribute('data-durum');
        console.log(`Satır ${index + 1}: durum = ${durum}`);
        
        if (durum) {
            applyRowBackgroundColor(row, durum);
        } else {
            console.warn(`Satır ${index + 1} için durum bulunamadı`);
        }
    });
    
    console.log('✅ Arkaplan renkleri uygulandı');
}

// Sayfa yüklendiğinde tüm durum badge'lerini güncelle
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Status Helper yükleniyor...');
    
    // Tablodaki tüm durum hücrelerini bul ve güncelle
    const statusCells = document.querySelectorAll('.status-cell[data-status]');
    console.log(`${statusCells.length} durum hücresi bulundu`);
    
    statusCells.forEach(cell => {
        const status = cell.getAttribute('data-status');
        if (status) {
            cell.innerHTML = createStatusBadge(status);
        }
    });
    
    // Tablodaki tüm işlem hücrelerini bul ve güncelle
    const actionCells = document.querySelectorAll('.actions-cell[data-fatura-id][data-status]');
    console.log(`${actionCells.length} işlem hücresi bulundu`);
    
    actionCells.forEach(cell => {
        const faturaId = cell.getAttribute('data-fatura-id');
        const status = cell.getAttribute('data-status');
        if (faturaId && status) {
            cell.innerHTML = createActionButtons(faturaId, status);
        }
    });
    
    console.log('🎨 Status Helper yüklendi - Arkaplan renkleri uygulanıyor...');
    
    // ✨ Tüm satırlara arkaplan renklerini uygula
    applyAllRowBackgrounds();
});

// CSS stilleri (head bölümüne eklenmeli)
const statusStyles = `
/* Fatura durum arkaplan renkleri */
.invoice-row-bos {
    background-color: #f9fafb !important;
    border-left: 4px solid #9ca3af;
}

.invoice-row-bos:hover {
    background-color: #f3f4f6 !important;
}

.invoice-row-urun-girildi {
    background-color: #fffbeb !important;
    border-left: 4px solid #f59e0b;
}

.invoice-row-urun-girildi:hover {
    background-color: #fef3c7 !important;
}

.invoice-row-aktarim-bekliyor {
    background-color: #eff6ff !important;
    border-left: 4px solid #3b82f6;
}

.invoice-row-aktarim-bekliyor:hover {
    background-color: #dbeafe !important;
}

.invoice-row-kismi-aktarildi {
    background-color: #fff7ed !important;
    border-left: 4px solid #f97316;
}

.invoice-row-kismi-aktarildi:hover {
    background-color: #fed7aa !important;
}

.invoice-row-aktarildi {
    background-color: #f0fdf4 !important;
    border-left: 4px solid #10b981;
}

.invoice-row-aktarildi:hover {
    background-color: #dcfce7 !important;
}

/* Status badge stilleri */
.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.5rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 500;
    border: 1px solid;
    transition: all 0.2s ease;
}

.status-badge:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

/* Progress bar stilleri */
.status-progress {
    display: flex;
    align-items: center;
    space-x: 0.5rem;
    margin: 1rem 0;
}

.status-step {
    width: 2rem;
    height: 2rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 500;
    transition: all 0.3s ease;
}

.status-step.completed {
    background-color: #10b981;
    color: white;
    box-shadow: 0 2px 4px rgba(16, 185, 129, 0.3);
}

.status-step.active {
    background-color: #3b82f6;
    color: white;
    box-shadow: 0 2px 4px rgba(59, 130, 246, 0.3);
    animation: pulse 2s infinite;
}

.status-step.pending {
    background-color: #e5e7eb;
    color: #6b7280;
}

@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.8;
        transform: scale(1.05);
    }
}

.status-connector {
    flex: 1;
    height: 2px;
    transition: all 0.3s ease;
}

.status-connector.completed {
    background-color: #10b981;
}

.status-connector.pending {
    background-color: #e5e7eb;
}

/* Action button stilleri */
.action-button {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.5rem;
    margin: 0 0.125rem;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    text-decoration: none;
    transition: all 0.2s ease;
}

.action-button:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

/* Tooltip stilleri */
.tooltip {
    position: relative;
}

.tooltip:hover::after {
    content: attr(title);
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    background: #1f2937;
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.75rem;
    white-space: nowrap;
    z-index: 1000;
    opacity: 0;
    animation: tooltipFadeIn 0.2s ease forwards;
}

@keyframes tooltipFadeIn {
    from {
        opacity: 0;
        transform: translateX(-50%) translateY(-5px);
    }
    to {
        opacity: 1;
        transform: translateX(-50%) translateY(0);
    }
}

/* Genel tablo iyileştirmeleri */
tbody tr {
    transition: all 0.2s ease;
}

tbody tr:hover {
    transform: translateX(2px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

/* KISMI AKTARILDI için özel renkler - Turuncu tonları */
tr[data-durum="kismi_aktarildi"] {
    background-color: #fed7aa !important;
    border-left: 4px solid #ea580c !important;
}

tr[data-durum="kismi_aktarildi"]:hover {
    background-color: #fdba74 !important;
}

/* Diğer durumlar için de kesin tanımlar */
tr[data-durum="bos"] {
    background-color: #f3f4f6 !important;
    border-left: 4px solid #6b7280 !important;
}

tr[data-durum="bos"]:hover {
    background-color: #e5e7eb !important;
}

tr[data-durum="urun_girildi"] {
    background-color: #fef3c7 !important;
    border-left: 4px solid #d97706 !important;
}

tr[data-durum="urun_girildi"]:hover {
    background-color: #fde68a !important;
}

tr[data-durum="aktarim_bekliyor"] {
    background-color: #dbeafe !important;
    border-left: 4px solid #2563eb !important;
}

tr[data-durum="aktarim_bekliyor"]:hover {
    background-color: #bfdbfe !important;
}

tr[data-durum="aktarildi"] {
    background-color: #dcfce7 !important;
    border-left: 4px solid #059669 !important;
}

tr[data-durum="aktarildi"]:hover {
    background-color: #bbf7d0 !important;
}
`;

// Stilleri head'e ekle
if (document.head && !document.getElementById('status-styles')) {
    const styleElement = document.createElement('style');
    styleElement.id = 'status-styles';
    styleElement.textContent = statusStyles;
    document.head.appendChild(styleElement);
    console.log('✨ Status stilleri yüklendi');
}

// Global fonksiyonları window objesine ekle
window.getStatusBadgeClass = getStatusBadgeClass;
window.getRowBackgroundClass = getRowBackgroundClass;
window.getStatusText = getStatusText;
window.getStatusIcon = getStatusIcon;
window.getStatusDescription = getStatusDescription;
window.getAvailableActions = getAvailableActions;
window.createStatusBadge = createStatusBadge;
window.createStatusProgressBar = createStatusProgressBar;
window.updateInvoiceStatusInTable = updateInvoiceStatusInTable;
window.createActionButtons = createActionButtons;
window.applyRowBackgroundColor = applyRowBackgroundColor;
window.applyAllRowBackgrounds = applyAllRowBackgrounds;