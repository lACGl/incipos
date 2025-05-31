<?php
class ProductManager {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    public function deleteMultipleProducts($ids) {
        try {
            // İlişki hatalarını önlemek için transaction başlat
            $this->conn->beginTransaction();
            
            // Barkodları al
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';
            $stmt = $this->conn->prepare("SELECT barkod FROM urun_stok WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            $barkodlar = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // İlişkili kayıtları sil
            if (!empty($barkodlar)) {
                $barkod_placeholders = str_repeat('?,', count($barkodlar) - 1) . '?';
                $stmt = $this->conn->prepare("DELETE FROM magaza_stok WHERE barkod IN ($barkod_placeholders)");
                $stmt->execute($barkodlar);
            }
            
            // Diğer ilişkili tablolardan sil - veritabanı yapısına göre genişletilmiş liste
            $tables = [
                'depo_stok' => 'urun_id',
                'stok_hareketleri' => 'urun_id',
                'alis_fatura_detay' => 'urun_id',
                'alis_fatura_detay_aktarim' => 'urun_id',
                'satis_fatura_detay' => 'urun_id',
                'urun_fiyat_gecmisi' => 'urun_id',
                'indirim_detay' => 'urun_id',
                'siparis_detay' => 'urun_id',
                'musteri_borc_detaylar' => 'urun_id',
                'fiyat_alarmlari' => 'urun_id'
            ];
            
            foreach ($tables as $table => $column) {
                // İlgili tablonun varlığını kontrol et
                $checkTableQuery = "SHOW TABLES LIKE '$table'";
                $tableExists = $this->conn->query($checkTableQuery)->rowCount() > 0;
                
                if ($tableExists) {
                    try {
                        $stmt = $this->conn->prepare("DELETE FROM $table WHERE $column IN ($placeholders)");
                        $stmt->execute($ids);
                    } catch (Exception $e) {
                        // Tablo varsa ancak ilişki varsa ya da sütun hatalıysa, hatayı kaydet ama devam et
                        error_log("$table tablosunda silme hatası: " . $e->getMessage());
                    }
                }
            }
            
            // Ana ürünleri sil
            $stmt = $this->conn->prepare("DELETE FROM urun_stok WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            
            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            
            // İlişki hatası özel mesajı
            if (strpos($e->getMessage(), 'foreign key constraint fails') !== false) {
                throw new Exception('Bu ürünler başka tablolarda kullanımda olduğu için silinemiyor. Lütfen önce ilişkili kayıtları kaldırın.');
            }
            
            throw $e;
        }
    }
}