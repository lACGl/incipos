// Grafik referansları
let dailySalesChart = null;
let categoryChart = null;

// Sayfa yüklendiğinde
document.addEventListener('DOMContentLoaded', function() {
    // Varsayılan tarih aralığını ayarla
    const today = new Date();
    const thirtyDaysAgo = new Date(today);
    thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);

    document.querySelector('input[name="start_date"]').value = formatDate(thirtyDaysAgo);
    document.querySelector('input[name="end_date"]').value = formatDate(today);

    // Form submit olayını dinle
    document.getElementById('reportFilters').addEventListener('submit', function(e) {
        e.preventDefault();
        fetchReportData();
    });

    // İlk yüklemede raporu getir
    fetchReportData();
});

// Rapor verilerini getir
async function fetchReportData() {
    try {
        const form = document.getElementById('reportFilters');
        const formData = new FormData(form);
        const queryString = new URLSearchParams(formData).toString();

        const response = await fetch(`api/get_sales_report.php?${queryString}`);
        const data = await response.json();

        if (!data.success) {
            throw new Error(data.message || 'Veri alınırken bir hata oluştu');
        }

        updateSummaryCards(data.summary);
        updateSalesTable(data.sales);
        updateCharts(data);

    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Hata!',
            text: error.message
        });
    }
}

// Özet kartları güncelle
function updateSummaryCards(summary) {
    document.getElementById('totalSales').textContent = 
        `₺${parseFloat(summary.toplam_satis || 0).toLocaleString()}`;
    
    document.getElementById('totalProducts').textContent = 
        summary.toplam_urun.toLocaleString();
    
    const avgBasket = summary.toplam_satis / summary.toplam_fatura;
    document.getElementById('avgBasket').textContent = 
        `₺${avgBasket.toLocaleString(undefined, {maximumFractionDigits: 2})}`;
    
    const returnRate = (summary.iade_sayisi / summary.toplam_fatura) * 100;
    document.getElementById('returnRate').textContent = 
        `%${returnRate.toFixed(2)}`;
}

// Satış tablosunu güncelle
function updateSalesTable(sales) {
    const tbody = document.getElementById('salesTableBody');
    tbody.innerHTML = '';

    sales.forEach(sale => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td class="px-6 py-4 whitespace-nowrap">
                ${formatDate(new Date(sale.fatura_tarihi))}
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                ${sale.fatura_seri}${sale.fatura_no}
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                ${sale.magaza_adi}
            </td>
            <td class="px-6 py-4">
                ${sale.urun_adi}
                <div class="text-sm text-gray-500">${sale.kategori || '-'}</div>
            </td>
            <td class="px-6 py-4 text-right whitespace-nowrap">
                ${parseFloat(sale.miktar).toLocaleString()}
            </td>
            <td class="px-6 py-4 text-right whitespace-nowrap">
                ₺${parseFloat(sale.birim_fiyat).toLocaleString()}
            </td>
            <td class="px-6 py-4 text-right whitespace-nowrap">
                ₺${parseFloat(sale.toplam_tutar).toLocaleString()}
            </td>
        `;
        tbody.appendChild(row);
    });
}

// Grafikleri güncelle
function updateCharts(data) {
    // Günlük satış grafiği
    const dailyData = processDailySalesData(data.sales);
    updateDailySalesChart(dailyData);

    // Kategori grafiği
    updateCategoryChart(data.categories);
}

// Günlük satış verilerini işle
function processDailySalesData(sales) {
    const dailyTotals = {};
    sales.forEach(sale => {
        const date = sale.fatura_tarihi.split(' ')[0];
        dailyTotals[date] = (dailyTotals[date] || 0) + parseFloat(sale.toplam_tutar);
    });

    return {
        labels: Object.keys(dailyTotals),
        values: Object.values(dailyTotals)
    };
}

// Günlük satış grafiğini güncelle
function updateDailySalesChart(data) {
    const ctx = document.getElementById('dailySalesChart').getContext('2d');
    
    if (dailySalesChart) {
        dailySalesChart.destroy();
    }

    dailySalesChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'Günlük Satış',
                data: data.values,
                borderColor: '#4f46e5',
                backgroundColor: 'rgba(79, 70, 229, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `₺${context.parsed.y.toLocaleString()}`;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '₺' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });
}

// Kategori grafiğini güncelle
function updateCategoryChart(categories) {
    const ctx = document.getElementById('categoryChart').getContext('2d');
    
    if (categoryChart) {
        categoryChart.destroy();
    }

    const colors = [
        '#4f46e5', '#7c3aed', '#db2777', '#ea580c', 
        '#16a34a', '#2563eb', '#9333ea', '#c026d3'
    ];

    categoryChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: categories.map(c => c.kategori || 'Diğer'),
            datasets: [{
                data: categories.map(c => c.toplam),
                backgroundColor: colors,
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'right',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const value = context.raw;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((value / total) * 100).toFixed(1);
                            return `₺${value.toLocaleString()} (%${percentage})`;
                        }
                    }
                }
            }
        }
    });
}

// Excel'e aktar
async function exportToExcel() {
    try {
        // SheetJS (XLSX) kütüphanesini dinamik olarak yükle
        await loadScript('https://cdn.sheetjs.com/xlsx-0.20.1/package/dist/xlsx.full.min.js');
        
        // Tablo verilerini al
        const table = document.getElementById('salesTable');
        const rows = Array.from(table.querySelectorAll('tr'));
        
        // Excel için veri yapısını oluştur
        const workbook = XLSX.utils.book_new();
        const worksheet_data = [];

        // Başlık satırı
        const headers = Array.from(rows[0].querySelectorAll('th')).map(th => th.textContent.trim());
        worksheet_data.push(headers);

        // Veri satırları
        rows.slice(1).forEach(row => {
            const cells = Array.from(row.querySelectorAll('td')).map(td => {
                let value = td.textContent.trim();
                
                // Para birimi temizleme
                if (value.includes('₺')) {
                    value = parseFloat(value.replace('₺', '').replace(/\./g, '').replace(',', '.'));
                }
                
                // Sayısal değer kontrolü
                if (!isNaN(value) && value !== '') {
                    return parseFloat(value);
                }
                
                return value;
            });
            worksheet_data.push(cells);
        });

        // Worksheet oluştur
        const worksheet = XLSX.utils.aoa_to_sheet(worksheet_data);

        // Sütun genişliklerini ayarla
        const columnWidths = headers.map(header => ({
            wch: Math.max(header.length, 12)
        }));
        worksheet['!cols'] = columnWidths;

        // Para birimi formatını ayarla
        const priceColumns = [5, 6]; // Birim Fiyat ve Toplam sütunları
        const numFmt = '#,##0.00"₺"';
        
        worksheet_data.forEach((row, rowIndex) => {
            priceColumns.forEach(colIndex => {
                const cellRef = XLSX.utils.encode_cell({ r: rowIndex, c: colIndex });
                if (worksheet[cellRef]) {
                    worksheet[cellRef].z = numFmt;
                }
            });
        });

        // Workbook'a worksheet'i ekle
        XLSX.utils.book_append_sheet(workbook, worksheet, "Satış Raporu");

        // Excel dosyasını indir
        const date = new Date().toISOString().slice(0, 10);
        XLSX.writeFile(workbook, `satis_raporu_${date}.xlsx`);

    } catch (error) {
        console.error('Excel export hatası:', error);
        Swal.fire({
            icon: 'error',
            title: 'Hata!',
            text: 'Excel dosyası oluşturulurken bir hata oluştu.'
        });
    }
}

// SheetJS kütüphanesini dinamik olarak yükleme
function loadScript(src) {
    return new Promise((resolve, reject) => {
        if (window.XLSX) {
            resolve();
            return;
        }

        const script = document.createElement('script');
        script.src = src;
        script.onload = resolve;
        script.onerror = reject;
        document.head.appendChild(script);
    });
}

// Tarih formatla
function formatDate(date) {
    return date.toISOString().split('T')[0];
}