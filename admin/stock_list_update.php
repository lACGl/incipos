<?php
// İşlemler menüsüne Excel İçe Aktarma seçeneği eklemek için

// stock_list.php dosyasını oku
$stock_list_content = file_get_contents('stock_list.php');

// İşlemler menüsü içindeki son </div> etiketini bul
$search_pattern = '<div id="actionsMenu" class="actions-menu">
                    <a href="#" onclick="addProduct()">Ürün Ekle</a>
                    <a href="#" onclick="deleteSelected()">Seçili Ürünleri Sil</a>
                    <a href="#" onclick="deactivateSelected()">Seçili Ürünleri Pasife Al</a>
                    <a href="#" onclick="activateSelected()">Seçili Ürünleri Aktife Al</a>
                    <a href="#" onclick="exportSelected()">Seçili Ürünleri Dışarı Aktar</a>
                </div>';

// Yeni menü öğesini ekle
$replacement = '<div id="actionsMenu" class="actions-menu">
                    <a href="#" onclick="addProduct()">Ürün Ekle</a>
                    <a href="#" onclick="importProducts()">Excel\'den İçe Aktar</a>
                    <a href="#" onclick="deleteSelected()">Seçili Ürünleri Sil</a>
                    <a href="#" onclick="deactivateSelected()">Seçili Ürünleri Pasife Al</a>
                    <a href="#" onclick="activateSelected()">Seçili Ürünleri Aktife Al</a>
                    <a href="#" onclick="exportSelected()">Seçili Ürünleri Dışarı Aktar</a>
                </div>';

// Değişiklikleri yap
$updated_content = str_replace($search_pattern, $replacement, $stock_list_content);

// JavaScript dosyasını ekle
$search_end_pattern = '    <script type="module" src="assets/js/main.js"></script>';
$replacement_script = '    <script type="module" src="assets/js/main.js"></script>
    <script type="module" src="assets/js/stock_list_import.js"></script>';

$updated_content = str_replace($search_end_pattern, $replacement_script, $updated_content);

// Değişiklikleri kaydet
file_put_contents('stock_list.php', $updated_content);

echo "Stock List sayfası başarıyla güncellendi.";
?>