<?php
class ProductManager {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function deleteMultipleProducts($ids) {
        try {
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

            // Diğer ilişkili tablolardan sil
            $tables = [
                'depo_stok' => 'urun_id',
                'stok_hareketleri' => 'urun_id',
                'alis_fatura_detay' => 'urun_id',
                'alis_fatura_detay_aktarim' => 'urun_id',
                'satis_fatura_detay' => 'urun_id',
                'urun_fiyat_gecmisi' => 'urun_id'
            ];

            foreach ($tables as $table => $column) {
                $stmt = $this->conn->prepare("DELETE FROM $table WHERE $column IN ($placeholders)");
                $stmt->execute($ids);
            }

            // Ana ürünleri sil
            $stmt = $this->conn->prepare("DELETE FROM urun_stok WHERE id IN ($placeholders)");
            $stmt->execute($ids);

            $this->conn->commit();
            return true;

        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }
}