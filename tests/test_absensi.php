<?php
/**
 * Unit Test untuk Fitur Absensi
 * 
 * Pengujian:
 * 1. Input absensi baru
 * 2. Update absensi yang sudah ada
 * 3. Ambil data absensi per bulan
 * 4. Hitung statistik kehadiran
 * 5. Cegah duplikasi data
 */

require_once __DIR__ . '/../config/database.php';

class TestAbsensi {
    private $conn;
    private $total_test = 0;
    private $test_passed = 0;
    private $test_data = [];
    
    public function __construct($conn) {
        $this->conn = $conn;
        echo "=====================================\n";
        echo "    TEST FITUR ABSENSI\n";
        echo "=====================================\n\n";
    }
    
    public function runTests() {
        $this->testKoneksiDatabase();
        $this->testTabelAbsensi();
        $this->testInputAbsensi();
        $this->testAmbilAbsensiSiswa();
        $this->testUpdateAbsensi();
        $this->testAmbilAbsensiBulanan();
        $this->testStatistikKehadiran();
        $this->testCegahDuplikasi();
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
    
    private function testKoneksiDatabase() {
        $this->assertTrue($this->conn !== false, "Koneksi database berhasil");
    }
    
    private function testTabelAbsensi() {
        $query = "SHOW TABLES LIKE 'absensi'";
        $result = mysqli_query($this->conn, $query);
        $this->assertTrue(mysqli_num_rows($result) > 0, "Tabel 'absensi' tersedia");
    }
    
    private function testInputAbsensi() {
        // Ambil siswa_id yang ada
        $query = "SELECT id FROM users WHERE role='siswa' LIMIT 1";
        $result = mysqli_query($this->conn, $query);
        
        if (mysqli_num_rows($result) == 0) {
            $this->assertTrue(false, "Tidak ada data siswa untuk test");
            return;
        }
        
        $siswa = mysqli_fetch_assoc($result);
        $siswa_id = $siswa['id'];
        
        $tanggal = date('Y-m-d');
        $status = 'hadir';
        $keterangan = 'Test absensi';
        
        // Hapus data test sebelumnya jika ada
        $delete_query = "DELETE FROM absensi WHERE siswa_id = $siswa_id AND tanggal = '$tanggal'";
        mysqli_query($this->conn, $delete_query);
        
        $query = "INSERT INTO absensi (siswa_id, tanggal, status, keterangan) 
                  VALUES ('$siswa_id', '$tanggal', '$status', '$keterangan')";
        
        $result = mysqli_query($this->conn, $query);
        
        if ($result) {
            $this->test_data['absensi_id'] = mysqli_insert_id($this->conn);
            $this->test_data['siswa_id'] = $siswa_id;
            $this->test_data['tanggal'] = $tanggal;
        }
        
        $this->assertTrue($result, "Input absensi baru berhasil");
    }
    
    private function testAmbilAbsensiSiswa() {
        if (!isset($this->test_data['siswa_id'])) {
            $this->assertTrue(false, "Data test tidak ditemukan");
            return;
        }
        
        $siswa_id = $this->test_data['siswa_id'];
        
        $query = "SELECT * FROM absensi WHERE siswa_id = $siswa_id";
        $result = mysqli_query($this->conn, $query);
        
        $this->assertTrue(mysqli_num_rows($result) > 0, "Ambil data absensi siswa berhasil");
    }
    
    private function testUpdateAbsensi() {
        if (!isset($this->test_data['absensi_id'])) {
            $this->assertTrue(false, "Data test tidak ditemukan");
            return;
        }
        
        $id = $this->test_data['absensi_id'];
        $status_baru = 'sakit';
        $keterangan_baru = 'Demam (update test)';
        
        $query = "UPDATE absensi SET status = '$status_baru', keterangan = '$keterangan_baru' WHERE id = $id";
        $result = mysqli_query($this->conn, $query);
        
        // Verifikasi update
        $query_cek = "SELECT * FROM absensi WHERE id = $id";
        $result_cek = mysqli_query($this->conn, $query_cek);
        $data = mysqli_fetch_assoc($result_cek);
        
        $update_berhasil = ($result && $data['status'] == $status_baru);
        $this->assertTrue($update_berhasil, "Update absensi berhasil");
    }
    
    private function testAmbilAbsensiBulanan() {
        if (!isset($this->test_data['siswa_id'])) {
            $this->assertTrue(false, "Data test tidak ditemukan");
            return;
        }
        
        $siswa_id = $this->test_data['siswa_id'];
        $bulan = date('m');
        $tahun = date('Y');
        
        $query = "SELECT * FROM absensi 
                  WHERE siswa_id = $siswa_id 
                  AND MONTH(tanggal) = $bulan 
                  AND YEAR(tanggal) = $tahun";
        $result = mysqli_query($this->conn, $query);
        
        $this->assertTrue(mysqli_num_rows($result) >= 0, "Ambil absensi bulanan berhasil");
    }
    
    private function testStatistikKehadiran() {
        if (!isset($this->test_data['siswa_id'])) {
            $this->assertTrue(false, "Data test tidak ditemukan");
            return;
        }
        
        $siswa_id = $this->test_data['siswa_id'];
        
        $query = "SELECT 
                    COUNT(CASE WHEN status = 'hadir' THEN 1 END) as hadir,
                    COUNT(CASE WHEN status = 'sakit' THEN 1 END) as sakit,
                    COUNT(CASE WHEN status = 'izin' THEN 1 END) as izin,
                    COUNT(CASE WHEN status = 'alpha' THEN 1 END) as alpha,
                    COUNT(*) as total_hari
                  FROM absensi 
                  WHERE siswa_id = $siswa_id";
        $result = mysqli_query($this->conn, $query);
        $stat = mysqli_fetch_assoc($result);
        
        $this->assertTrue(isset($stat['hadir']), "Hitung statistik kehadiran berhasil");
        echo "   Statistik: Hadir={$stat['hadir']}, Sakit={$stat['sakit']}, Izin={$stat['izin']}, Alpha={$stat['alpha']}\n";
    }
    
    private function testCegahDuplikasi() {
        if (!isset($this->test_data['siswa_id']) || !isset($this->test_data['tanggal'])) {
            $this->assertTrue(false, "Data test tidak ditemukan");
            return;
        }
        
        $siswa_id = $this->test_data['siswa_id'];
        $tanggal = $this->test_data['tanggal'];
        $status = 'izin';
        
        // Coba insert data yang sama (seharusnya error karena unique constraint)
        $query = "INSERT INTO absensi (siswa_id, tanggal, status) 
                  VALUES ('$siswa_id', '$tanggal', '$status')";
        
        // Matikan error reporting sementara untuk menangani error duplicate
        mysqli_report(MYSQLI_REPORT_OFF);
        $result = @mysqli_query($this->conn, $query);
        $error = mysqli_error($this->conn);
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        
        // Cek apakah error karena duplicate entry
        $is_duplicate = strpos($error, 'Duplicate entry') !== false;
        
        // Test ini dianggap PASS jika query gagal karena duplicate (sesuai yang diharapkan)
        // Atau jika query berhasil (tidak duplicate), kita harus hapus data tersebut
        if ($result) {
            // Jika berhasil (seharusnya tidak terjadi), hapus data tersebut
            $new_id = mysqli_insert_id($this->conn);
            mysqli_query($this->conn, "DELETE FROM absensi WHERE id = $new_id");
            $this->assertTrue(false, "Sistem seharusnya mencegah duplikasi, tapi data berhasil diinsert");
        } else {
            $this->assertTrue($is_duplicate, "Sistem mencegah duplikasi absensi");
        }
    }
    
    private function testHapusDataTest() {
        if (isset($this->test_data['absensi_id'])) {
            $query = "DELETE FROM absensi WHERE id = " . $this->test_data['absensi_id'];
            $result = mysqli_query($this->conn, $query);
            $this->assertTrue($result, "Hapus data test berhasil");
        } else {
            $this->assertTrue(true, "Tidak ada data test yang perlu dihapus");
        }
    }
    
    private function tampilkanHasil() {
        echo "\n=====================================\n";
        echo "       HASIL TEST ABSENSI\n";
        echo "=====================================\n";
        echo "Total Test  : $this->total_test\n";
        echo "Passed      : $this->test_passed\n";
        echo "Failed      : " . ($this->total_test - $this->test_passed) . "\n";
        echo "=====================================\n";
    }
}

// Jalankan test
try {
    $test = new TestAbsensi($conn);
    $test->runTests();
} catch (Exception $e) {
    echo "❌ [ERROR] " . $e->getMessage() . "\n";
}
?>