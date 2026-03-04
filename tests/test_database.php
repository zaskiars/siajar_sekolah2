<?php
/**
 * Unit Test untuk Koneksi Database
 * 
 * Pengujian:
 * 1. Koneksi ke database
 * 2. Query sederhana
 * 3. Tabel-tabel yang diperlukan
 */

require_once __DIR__ . '/../config/database.php';

class TestDatabase {
    private $conn;
    private $hasil = [];
    private $total_test = 0;
    private $test_passed = 0;
    
    public function __construct($conn) {
        $this->conn = $conn;
        echo "=====================================\n";
        echo "  TEST KONEKSI DATABASE\n";
        echo "=====================================\n\n";
    }
    
    public function runTests() {
        $this->testKoneksi();
        $this->testTabelUsers();
        $this->testTabelMataPelajaran();
        $this->testTabelNilai();
        $this->testTabelAbsensi();
        $this->testTabelJadwalUjian();
        $this->testTabelTugas();
        
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
    
    private function testKoneksi() {
        $this->assertTrue($this->conn !== false, "Koneksi database berhasil");
    }
    
    private function testTabelUsers() {
        $query = "SHOW TABLES LIKE 'users'";
        $result = mysqli_query($this->conn, $query);
        $this->assertTrue(mysqli_num_rows($result) > 0, "Tabel 'users' tersedia");
    }
    
    private function testTabelMataPelajaran() {
        $query = "SHOW TABLES LIKE 'mata_pelajaran'";
        $result = mysqli_query($this->conn, $query);
        $this->assertTrue(mysqli_num_rows($result) > 0, "Tabel 'mata_pelajaran' tersedia");
    }
    
    private function testTabelNilai() {
        $query = "SHOW TABLES LIKE 'nilai'";
        $result = mysqli_query($this->conn, $query);
        $this->assertTrue(mysqli_num_rows($result) > 0, "Tabel 'nilai' tersedia");
    }
    
    private function testTabelAbsensi() {
        $query = "SHOW TABLES LIKE 'absensi'";
        $result = mysqli_query($this->conn, $query);
        $this->assertTrue(mysqli_num_rows($result) > 0, "Tabel 'absensi' tersedia");
    }
    
    private function testTabelJadwalUjian() {
        $query = "SHOW TABLES LIKE 'jadwal_ujian'";
        $result = mysqli_query($this->conn, $query);
        $this->assertTrue(mysqli_num_rows($result) > 0, "Tabel 'jadwal_ujian' tersedia");
    }
    
    private function testTabelTugas() {
        $query = "SHOW TABLES LIKE 'tugas'";
        $result = mysqli_query($this->conn, $query);
        $this->assertTrue(mysqli_num_rows($result) > 0, "Tabel 'tugas' tersedia");
    }
    
    private function tampilkanHasil() {
        echo "\n=====================================\n";
        echo "  HASIL TEST\n";
        echo "=====================================\n";
        echo "Total Test  : $this->total_test\n";
        echo "Passed      : $this->test_passed\n";
        echo "Failed      : " . ($this->total_test - $this->test_passed) . "\n";
        echo "=====================================\n";
    }
}

// Jalankan test
$test = new TestDatabase($conn);
$test->runTests();
?>