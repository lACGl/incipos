import { useEffect, useState } from 'react';

export default function DashboardSummary() {
  const [summaryData, setSummaryData] = useState({
    totalProducts: 0,
    totalValue: 0,
    dailySales: [],
    lowStock: 0,
    dailyTotal: 0
  });

  async function fetchDashboardData() {
    try {
      const response = await fetch('api/get_dashboard_summary.php');
      const data = await response.json();
      if (data.success) {
        setSummaryData(data.summary);
      }
    } catch (error) {
      console.error('Dashboard verisi alınırken hata:', error);
    }
  }

  useEffect(() => {
    fetchDashboardData();
    const interval = setInterval(fetchDashboardData, 300000);
    return () => clearInterval(interval);
  }, []);

  return React.createElement('div', { className: 'space-y-6' }, [
    // Özet Kartlar
    React.createElement('div', { className: 'grid grid-cols-1 md:grid-cols-4 gap-4', key: 'summary-cards' }, [
      // Toplam Ürün Kartı
      React.createElement('div', { className: 'bg-white p-6 rounded-lg shadow hover:shadow-md transition-shadow', key: 'products' },
        React.createElement('div', { className: 'flex items-center justify-between' }, [
          React.createElement('div', { key: 'info' }, [
            React.createElement('p', { className: 'text-gray-500 text-sm', key: 'label' }, 'Toplam Ürün'),
            React.createElement('p', { className: 'text-2xl font-bold', key: 'value' }, summaryData.totalProducts)
          ]),
          React.createElement('div', { className: 'text-blue-500', key: 'icon', dangerouslySetInnerHTML: { __html: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.91 8.84L8.56 2.23a1.93 1.93 0 0 0-1.81 0L3.1 4.13a2.12 2.12 0 0 0-.05 3.69l12.22 6.93a2 2 0 0 0 1.94 0L21 12.51a2.12 2.12 0 0 0-.09-3.67Z"></path><path d="m3.09 8.84 12.35-6.61a1.93 1.93 0 0 1 1.81 0l3.65 1.9a2.12 2.12 0 0 1 .1 3.69L8.73 14.75a2 2 0 0 1-1.94 0L3 12.51a2.12 2.12 0 0 1 .09-3.67Z"></path></svg>' }})
        ])
      ),

      // Stok Değeri Kartı
      React.createElement('div', { className: 'bg-white p-6 rounded-lg shadow hover:shadow-md transition-shadow', key: 'value' },
        React.createElement('div', { className: 'flex items-center justify-between' }, [
          React.createElement('div', { key: 'info' }, [
            React.createElement('p', { className: 'text-gray-500 text-sm', key: 'label' }, 'Stok Değeri'),
            React.createElement('p', { className: 'text-2xl font-bold', key: 'value' }, `₺${summaryData.totalValue.toLocaleString()}`)
          ]),
          React.createElement('div', { className: 'text-green-500', key: 'icon', dangerouslySetInnerHTML: { __html: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>' }})
        ])
      ),

      // Günlük Satış Kartı
      React.createElement('div', { className: 'bg-white p-6 rounded-lg shadow hover:shadow-md transition-shadow', key: 'daily-sales' },
        React.createElement('div', { className: 'flex items-center justify-between' }, [
          React.createElement('div', { key: 'info' }, [
            React.createElement('p', { className: 'text-gray-500 text-sm', key: 'label' }, 'Günlük Satış'),
            React.createElement('p', { className: 'text-2xl font-bold', key: 'value' }, `₺${summaryData.dailyTotal.toLocaleString()}`)
          ]),
          React.createElement('div', { className: 'text-purple-500', key: 'icon', dangerouslySetInnerHTML: { __html: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m23 6-9.5 9.5-5-5L1 18"></path><path d="M17 6h6v6"></path></svg>' }})
        ])
      ),

      // Düşük Stok Kartı
      React.createElement('div', { className: 'bg-white p-6 rounded-lg shadow hover:shadow-md transition-shadow', key: 'low-stock' },
        React.createElement('div', { className: 'flex items-center justify-between' }, [
          React.createElement('div', { key: 'info' }, [
            React.createElement('p', { className: 'text-gray-500 text-sm', key: 'label' }, 'Düşük Stok'),
            React.createElement('p', { className: 'text-2xl font-bold', key: 'value' }, summaryData.lowStock)
          ]),
          React.createElement('div', { className: 'text-red-500', key: 'icon', dangerouslySetInnerHTML: { __html: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>' }})
        ])
      )
    ]),

    // Grafik Bölümü
    React.createElement('div', { className: 'bg-white p-6 rounded-lg shadow', key: 'chart' }, [
      React.createElement('h3', { className: 'text-lg font-semibold mb-4', key: 'chart-title' }, 'Bugünkü Mağaza Satışları'),
      React.createElement('div', { className: 'h-80', key: 'chart-container' },
        React.createElement(ResponsiveContainer, { width: '100%', height: '100%' },
          React.createElement(BarChart, { data: summaryData.dailySales }, [
            React.createElement(CartesianGrid, { strokeDasharray: '3 3', key: 'grid' }),
            React.createElement(XAxis, { dataKey: 'name', key: 'xaxis' }),
            React.createElement(YAxis, { key: 'yaxis' }),
            React.createElement(Tooltip, { 
              formatter: value => `₺${value.toLocaleString()}`,
              key: 'tooltip'
            }),
            React.createElement(Legend, { key: 'legend' }),
            React.createElement(Bar, { 
              dataKey: 'sales',
              name: 'Satış (₺)',
              fill: '#4f46e5',
              key: 'bar'
            })
          ])
        )
      )
    ])
  ]);
}