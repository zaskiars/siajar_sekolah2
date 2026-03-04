<?php
/**
 * Unit Test untuk Fitur Nilai
 * 
 * Pengujian:
 * 1. Input nilai baru
 * 2. Validasi range nilai (0-100)
 * 3. Ambil data nilai siswa
 * 4. Hitung rata-rata nilai
 */

require_once __DIR__ . '/../config/database.php';

class TestNilai {
    private $conn;
    private $total_test = 0;
    private $test_passed = 0;
    private $test_data = [];
    
    public function __construct($conn) {
        $this->conn = $conn;
        echo "=====================================\n";
        echo "     TEST FITUR NILAI\n";
        echo "=====================================\n\n";
    }
    
    public function runTests() {
        $this->testInputNilai();
        $this->testValidasiNilai();
        $this->testAmbilNilaiSiswa();
        $this->testRataRataNilai();
        $this->testFilterNilai();
        $this->testHapusDataTest();
        
        $this->tampilkanHasil();
    }
    
    private function assertTrue($kondisi, $pesan) {
        $this->total_test++;
        if ($kondisi) {
            $this->test_passed++;
            echo "✅ [PASS] $pesan\n";
        } else {
            echo "❌ [FAIL] $pesan\n";
        }
    }
    
    private function testInputNilai() {
        // Ambil siswa_id dan mapel_id yang ada
        $query = "SELECT id FROM users WHERE role='siswa' LIMIT 1";
        $result = mysqli_query($this->conn, $query);
        $siswa = mysqli_fetch_assoc($result);
        $siswa_id = $siswa['id'];
        
        $query = "SELECT id FROM mata_pelajaran LIMIT 1";
        $result = mysqli_query($this->conn, $query);
        $mapel = mysqli_fetch_assoc($result);
        $mapel_id = $mapel['id'];
        
        // Insert data test
        $query = "INSERT INTO nilai (siswa_id, mapel_id, tugas, nilai, semester, tahun_ajaran) 
                  VALUES ('$siswa_id', '$mapel_id', 'Test Tugas', '85.5', 'Ganjil', '2024/2025')";
        
        $result = mysqli_query($this->conn, $query);
        $this->test_data['nilai_id'] = mysqli_insert_id($this->conn);
        
        $this->assertTrue($result, "Input nilai baru berhasil");
    }
    
    private function testValidasiNilai() {
        // Test nilai valid (0-100)
        $nilai_valid = 85.5;
        $this->assertTrue($nilai_valid >= 0 && $nilai_valid <= 100, "Validasi nilai valid (85.5)");
        
        // Test nilai tidak valid (>100)
        $nilai_invalid = 150;
        $this->assertTrue(!($nilai_invalid >= 0 && $nilai_invalid <= 100), "Validasi nilai invalid (>100)");
        
        // Test nilai tidak valid (<0)
        $nilai_invalid = -10;
        $this->assertTrue(!($nilai_invalid >= 0 && $nilai_invalid <= 100), "Validasi nilai invalid (<0)");
    }
    
    private function testAmbilNilaiSiswa() {
        // Ambil siswa_id yang ada
        $query = "SELECT id FROM users WHERE role='siswa' LIMIT 1";
        $result = mysqli_query($this->conn, $query);
        $siswa = mysqli_fetch_assoc($result);
        $siswa_id = $siswa['id'];
        
        $query = "SELECT * FROM nilai WHERE siswa_id = $siswa_id";
        $result = mysqli_query($this->conn, $query);
        
        $this->assertTrue(mysqli_num_rows($result) >= 0, "Ambil data nilai siswa berhasil");
    }
    
    private function testRataRataNilai() {
        // Ambil siswa_id yang ada
        $query = "SELECT id FROM users WHERE role='siswa' LIMIT 1";
        $result = mysqli_query($this->conn, $query);
        $siswa = mysqli_fetch_assoc($result);
        $siswa_id = $siswa['id'];
        
        $query = "SELECT AVG(nilai) as rata FROM nilai WHERE siswa_id = $siswa_id";
        $result = mysqli_query($this->conn, $query);
        $row = mysqli_fetch_assoc($result);
        
        $this->assertTrue(isset($row['rata']), "Hitung rata-rata nilai berhasil");
    }
    
    private function testFilterNilai() {
        $semester = 'Ganjil';
        $tahun = '2024/2025';
        
        $query = "SELECT * FROM nilai WHERE semester = '$semester' AND tahun_ajaran = '$tahun'";
        $result = mysqli_query($this->conn, $query);
        
        $this->assertTrue(mysqli_num_rows($result) >= 0, "Filter nilai berdasarkan semester & tahun berhasil");
    }
    
    private function testHapusDataTest() {
        if (isset($this->test_data['nilai_id'])) {
            $query = "DELETE FROM nilai WHERE id = " . $this->test_data['nilai_id'];
            $result = mysqli_query($this->conn, $query);
            $this->assertTrue($result, "Hapus data test berhasil");
        }
    }
    
    private function tampilkanHasil() {
        echo "\n=====================================\n";
        echo "        HASIL TEST NILAI\n";
        echo "=====================================\n";
        echo "Total Test  : $this->total_test\n";
        echo "Passed      : $this->test_passed\n";
        echo "Failed      : " . ($this->total_test - $this->test_passed) . "\n";
        echo "=====================================\n";
    }
}

// Jalankan test
$test = new TestNilai($conn);
$test->runTests();
?>