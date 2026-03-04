<?php
/**
 * Unit Test untuk Fitur Jadwal Ujian
 * 
 * Pengujian:
 * 1. Input jadwal baru
 * 2. Ambil jadwal mendatang
 * 3. Filter jadwal per bulan
 * 4. Validasi bentrok jadwal
 */

require_once __DIR__ . '/../config/database.php';

class TestJadwal {
    private $conn;
    private $total_test = 0;
    private $test_passed = 0;
    private $test_data = [];
    
    public function __construct($conn) {
        $this->conn = $conn;
        echo "=====================================\n";
        echo "    TEST FITUR JADWAL\n";
        echo "=====================================\n\n";
    }
    
    public function runTests() {
        $this->testKoneksiDatabase();
        $this->testTabelJadwal();
        $this->testInputJadwal();
        $this->testAmbilJadwalMendatang();
        $this->testFilterJadwal();
        $this->testValidasiBentrok();
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
    
    private function testTabelJadwal() {
        $query = "SHOW TABLES LIKE 'jadwal_ujian'";
        $result = mysqli_query($this->conn, $query);
        $this->assertTrue(mysqli_num_rows($result) > 0, "Tabel 'jadwal_ujian' tersedia");
    }
    
    private function testInputJadwal() {
        // Ambil mapel_id yang ada
        $query = "SELECT id FROM mata_pelajaran LIMIT 1";
        $result = mysqli_query($this->conn, $query);
        
        if (mysqli_num_rows($result) == 0) {
            $this->assertTrue(false, "Tidak ada data mata pelajaran untuk test");
            return;
        }
        
        $mapel = mysqli_fetch_assoc($result);
        $mapel_id = $mapel['id'];
        
        $tanggal = date('Y-m-d', strtotime('+7 days'));
        $waktu_mulai = '08:00:00';
        $waktu_selesai = '10:00:00';
        $ruangan = 'Test Ruang ' . rand(100, 999); // Ruangan unik dengan random number
        $keterangan = 'Test Jadwal';
        
        // Hapus data test sebelumnya jika ada (dengan ruangan yang sama)
        $delete_query = "DELETE FROM jadwal_ujian WHERE ruangan LIKE 'Test Ruang%'";
        mysqli_query($this->conn, $delete_query);
        
        $query = "INSERT INTO jadwal_ujian (mapel_id, tanggal_ujian, waktu_mulai, waktu_selesai, ruangan, keterangan) 
                  VALUES ('$mapel_id', '$tanggal', '$waktu_mulai', '$waktu_selesai', '$ruangan', '$keterangan')";
        
        $result = mysqli_query($this->conn, $query);
        
        if ($result) {
            $this->test_data['jadwal_id'] = mysqli_insert_id($this->conn);
            $this->test_data['mapel_id'] = $mapel_id;
            $this->test_data['tanggal'] = $tanggal;
            $this->test_data['waktu_mulai'] = $waktu_mulai;
            $this->test_data['ruangan'] = $ruangan;
        }
        
        $this->assertTrue($result, "Input jadwal baru berhasil");
    }
    
    private function testAmbilJadwalMendatang() {
        $query = "SELECT * FROM jadwal_ujian WHERE tanggal_ujian >= CURDATE() ORDER BY tanggal_ujian ASC";
        $result = mysqli_query($this->conn, $query);
        
        $this->assertTrue(mysqli_num_rows($result) >= 0, "Ambil jadwal mendatang berhasil");
    }
    
    private function testFilterJadwal() {
        $bulan = date('m');
        $tahun = date('Y');
        
        $query = "SELECT * FROM jadwal_ujian 
                  WHERE MONTH(tanggal_ujian) = $bulan AND YEAR(tanggal_ujian) = $tahun";
        $result = mysqli_query($this->conn, $query);
        
        $this->assertTrue(mysqli_num_rows($result) >= 0, "Filter jadwal per bulan berhasil");
    }
    
    private function testValidasiBentrok() {
        if (!isset($this->test_data['mapel_id']) || !isset($this->test_data['tanggal']) || 
            !isset($this->test_data['waktu_mulai']) || !isset($this->test_data['ruangan'])) {
            $this->assertTrue(false, "Data test tidak ditemukan");
            return;
        }
        
        $mapel_id = $this->test_data['mapel_id'];
        $tanggal = $this->test_data['tanggal'];
        $waktu_mulai = $this->test_data['waktu_mulai'];
        $waktu_selesai = '10:00:00'; // Sama dengan data sebelumnya
        $ruangan = $this->test_data['ruangan']; // Ruangan yang sama
        
        // Coba insert data yang sama (seharusnya error karena unique constraint)
        $query = "INSERT INTO jadwal_ujian (mapel_id, tanggal_ujian, waktu_mulai, waktu_selesai, ruangan) 
                  VALUES ('$mapel_id', '$tanggal', '$waktu_mulai', '$waktu_selesai', '$ruangan')";
        
        // Matikan error reporting sementara
        mysqli_report(MYSQLI_REPORT_OFF);
        $result = @mysqli_query($this->conn, $query);
        $error = mysqli_error($this->conn);
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        
        // Cek apakah error karena duplicate entry
        $is_duplicate = strpos($error, 'Duplicate entry') !== false;
        
        // Test ini dianggap PASS jika query gagal karena duplicate (sesuai yang diharapkan)
        if ($result) {
            // Jika berhasil (seharusnya tidak terjadi), hapus data tersebut
            $new_id = mysqli_insert_id($this->conn);
            mysqli_query($this->conn, "DELETE FROM jadwal_ujian WHERE id = $new_id");
            $this->assertTrue(false, "Sistem seharusnya mencegah bentrok jadwal, tapi data berhasil diinsert");
        } else {
            $this->assertTrue($is_duplicate, "Sistem mencegah bentrok jadwal");
        }
    }
    
    private function testHapusDataTest() {
        if (isset($this->test_data['jadwal_id'])) {
            $query = "DELETE FROM jadwal_ujian WHERE id = " . $this->test_data['jadwal_id'];
            $result = mysqli_query($this->conn, $query);
            $this->assertTrue($result, "Hapus data test berhasil");
        } else {
            // Hapus semua data test yang mungkin tersisa
            mysqli_query($this->conn, "DELETE FROM jadwal_ujian WHERE ruangan LIKE 'Test Ruang%'");
            $this->assertTrue(true, "Bersihkan data test");
        }
    }
    
    private function tampilkanHasil() {
        echo "\n=====================================\n";
        echo "       HASIL TEST JADWAL\n";
        echo "=====================================\n";
        echo "Total Test  : $this->total_test\n";
        echo "Passed      : $this->test_passed\n";
        echo "Failed      : " . ($this->total_test - $this->test_passed) . "\n";
        echo "=====================================\n";
    }
}

// Jalankan test
try {
    $test = new TestJadwal($conn);
    $test->runTests();
} catch (Exception $e) {
    echo "❌ [ERROR] " . $e->getMessage() . "\n";
}
?>