<?php
/**
 * API Test Sayfası
 * Ana grup ve alt grup API çağrılarını test etmek için kullanılır
 */

// Session yönetimi ve yetkisiz erişim kontrolü
require_once '../../session_manager.php';
checkUserSession();

// Header'ı dahil et
include '../../header.php';
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">API Test Sayfası</h1>
    
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-lg font-semibold mb-4">Ana Grup API Testi</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label for="departman_id" class="block text-sm font-medium text-gray-700 mb-1">Departman ID</label>
                <input type="number" id="departman_id" min="1" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
            </div>
            
            <div class="flex items-end">
                <button id="testAnaGrupApi" class="bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded-md transition">
                    Test Et
                </button>
            </div>
        </div>
        
        <div id="anaGrupResult" class="mt-4 p-4 bg-gray-100 rounded-lg">
            <p class="text-gray-500">Sonuçlar burada görüntülenecek...</p>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-lg font-semibold mb-4">Alt Grup API Testi</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label for="ana_grup_id" class="block text-sm font-medium text-gray-700 mb-1">Ana Grup ID</label>
                <input type="number" id="ana_grup_id" min="1" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
            </div>
            
            <div class="flex items-end">
                <button id="testAltGrupApi" class="bg-green-500 hover:bg-green-600 text-white py-2 px-4 rounded-md transition">
                    Test Et
                </button>
            </div>
        </div>
        
        <div id="altGrupResult" class="mt-4 p-4 bg-gray-100 rounded-lg">
            <p class="text-gray-500">Sonuçlar burada görüntülenecek...</p>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Ana Grup API Testi
    document.getElementById('testAnaGrupApi').addEventListener('click', function() {
        const departmanId = document.getElementById('departman_id').value;
        const resultDiv = document.getElementById('anaGrupResult');
        
        if (!departmanId || departmanId < 1) {
            resultDiv.innerHTML = '<p class="text-red-500">Lütfen geçerli bir Departman ID girin.</p>';
            return;
        }
        
        resultDiv.innerHTML = '<div class="text-center"><div class="animate-spin rounded-full h-10 w-10 border-b-2 border-blue-500 mx-auto"></div><p class="mt-2">Yükleniyor...</p></div>';
        
        fetch(`/admin/api/get_ana_gruplar.php?departman_id=${departmanId}`)
            .then(response => {
                const contentType = response.headers.get("content-type");
                if (contentType && contentType.indexOf("application/json") !== -1) {
                    return response.json();
                } else {
                    return response.text().then(text => {
                        throw new Error(`API JSON döndürmedi: ${text}`);
                    });
                }
            })
            .then(data => {
                console.log('API Yanıtı:', data);
                
                // API yanıtının ayrıntılı yapısını gösteren tablo
                let html = '<div class="mb-4 p-4 bg-green-50 rounded">';
                html += '<h3 class="font-semibold text-green-800 mb-2">API Yanıt Detayları:</h3>';
                html += `<p><strong>Veri Tipi:</strong> ${typeof data}</p>`;
                
                if (typeof data === 'object') {
                    html += `<p><strong>Dizi mi?:</strong> ${Array.isArray(data) ? 'Evet' : 'Hayır'}</p>`;
                    html += `<p><strong>JSON:</strong> <pre class="mt-2 p-2 bg-gray-100 rounded">${JSON.stringify(data, null, 2)}</pre></p>`;
                } else {
                    html += `<p><strong>Değer:</strong> ${data}</p>`;
                }
                html += '</div>';
                
                if (!data) {
                    html += '<p class="text-red-500">API boş yanıt döndü.</p>';
                    resultDiv.innerHTML = html;
                    return;
                }
                
                // Veri tipine göre işlem
                if (Array.isArray(data)) {
                    if (data.length === 0) {
                        html += '<p class="text-yellow-500">Bu departmana ait ana grup bulunamadı.</p>';
                    } else {
                        html += '<div class="overflow-x-auto">';
                        html += '<table class="min-w-full divide-y divide-gray-200">';
                        html += '<thead class="bg-gray-50"><tr>';
                        html += '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>';
                        html += '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ad</th>';
                        html += '</tr></thead>';
                        html += '<tbody class="bg-white divide-y divide-gray-200">';
                        
                        data.forEach(grup => {
                            html += '<tr>';
                            html += `<td class="px-6 py-4 whitespace-nowrap">${grup.id}</td>`;
                            html += `<td class="px-6 py-4 whitespace-nowrap">${grup.ad}</td>`;
                            html += '</tr>';
                        });
                        
                        html += '</tbody></table></div>';
                    }
                } else {
                    html += '<p class="text-red-500">API yanıtı beklenen formatta değil. Yanıt bir dizi olmalıdır.</p>';
                }
                
                resultDiv.innerHTML = html;
            })
            .catch(error => {
                console.error('API hatası:', error);
                resultDiv.innerHTML = `<p class="text-red-500">Hata: ${error.message}</p>`;
            });
    });
    
    // Alt Grup API Testi
    document.getElementById('testAltGrupApi').addEventListener('click', function() {
        const anaGrupId = document.getElementById('ana_grup_id').value;
        const resultDiv = document.getElementById('altGrupResult');
        
        if (!anaGrupId || anaGrupId < 1) {
            resultDiv.innerHTML = '<p class="text-red-500">Lütfen geçerli bir Ana Grup ID girin.</p>';
            return;
        }
        
        resultDiv.innerHTML = '<div class="text-center"><div class="animate-spin rounded-full h-10 w-10 border-b-2 border-green-500 mx-auto"></div><p class="mt-2">Yükleniyor...</p></div>';
        
        fetch(`/admin/api/get_alt_gruplar.php?ana_grup_id=${anaGrupId}`)
            .then(response => {
                const contentType = response.headers.get("content-type");
                if (contentType && contentType.indexOf("application/json") !== -1) {
                    return response.json();
                } else {
                    return response.text().then(text => {
                        throw new Error(`API JSON döndürmedi: ${text}`);
                    });
                }
            })
            .then(data => {
                console.log('API Yanıtı Alt Grup:', data);
                
                // API yanıtının ayrıntılı yapısını gösteren tablo
                let html = '<div class="mb-4 p-4 bg-green-50 rounded">';
                html += '<h3 class="font-semibold text-green-800 mb-2">API Yanıt Detayları:</h3>';
                html += `<p><strong>Veri Tipi:</strong> ${typeof data}</p>`;
                
                if (typeof data === 'object') {
                    html += `<p><strong>Dizi mi?:</strong> ${Array.isArray(data) ? 'Evet' : 'Hayır'}</p>`;
                    html += `<p><strong>JSON:</strong> <pre class="mt-2 p-2 bg-gray-100 rounded">${JSON.stringify(data, null, 2)}</pre></p>`;
                } else {
                    html += `<p><strong>Değer:</strong> ${data}</p>`;
                }
                html += '</div>';
                
                if (!data) {
                    html += '<p class="text-red-500">API boş yanıt döndü.</p>';
                    resultDiv.innerHTML = html;
                    return;
                }
                
                // Veri tipine göre işlem
                if (Array.isArray(data)) {
                    if (data.length === 0) {
                        html += '<p class="text-yellow-500">Bu ana gruba ait alt grup bulunamadı.</p>';
                    } else {
                        html += '<div class="overflow-x-auto">';
                        html += '<table class="min-w-full divide-y divide-gray-200">';
                        html += '<thead class="bg-gray-50"><tr>';
                        html += '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>';
                        html += '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ad</th>';
                        html += '</tr></thead>';
                        html += '<tbody class="bg-white divide-y divide-gray-200">';
                        
                        data.forEach(grup => {
                            html += '<tr>';
                            html += `<td class="px-6 py-4 whitespace-nowrap">${grup.id}</td>`;
                            html += `<td class="px-6 py-4 whitespace-nowrap">${grup.ad}</td>`;
                            html += '</tr>';
                        });
                        
                        html += '</tbody></table></div>';
                    }
                } else {
                    html += '<p class="text-red-500">API yanıtı beklenen formatta değil. Yanıt bir dizi olmalıdır.</p>';
                }
                
                resultDiv.innerHTML = html;
            })
            .catch(error => {
                console.error('API hatası:', error);
                resultDiv.innerHTML = `<p class="text-red-500">Hata: ${error.message}</p>`;
            });
    });
});
</script>

<?php include '../../footer.php'; ?>