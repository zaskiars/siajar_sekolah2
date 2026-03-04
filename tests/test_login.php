<?php
/**
 * Unit Test untuk Fitur Login
 * 
 * Pengujian:
 * 1. Login dengan username dan password benar
 * 2. Login dengan username salah
 * 3. Login dengan password salah
 * 4. Login dengan role salah
 */

require_once __DIR__ . '/../config/database.php';

class TestLogin {
    private $conn;
    private $hasil = [];
    private $total_test = 0;
    private $test_passed = 0;
    
    public function __construct($conn) {
        $this->conn = $conn;
        echo "=====================================\n";
        echo "     TEST FITUR LOGIN\n";
        echo "=====================================\n\n";
    }
    
    public function runTests() {
        $this->testLoginGuruBerhasil();
        $this->testLoginSiswaBerhasil();
        $this->testLoginUsernameSalah();
        $this->testLoginPasswordSalah();
        $this->testLoginRoleSalah();
        
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
    
    private function testLoginGuruBerhasil() {
        $username = 'guru1';
        $password = md5('guru123');
        $role = 'guru';
        
        $query = "SELECT * FROM users WHERE username='$username' AND password='$password' AND role='$role'";
        $result = mysqli_query($this->conn, $query);
        
        $this->assertTrue(mysqli_num_rows($result) == 1, "Login guru berhasil dengan username 'guru1' dan password 'guru123'");
    }
    
    private function testLoginSiswaBerhasil() {
        $username = 'siswa1';
        $password = md5('siswa123');
        $role = 'siswa';
        
        $query = "SELECT * FROM users WHERE username='$username' AND password='$password' AND role='$role'";
        $result = mysqli_query($this->conn, $query);
        
        $this->assertTrue(mysqli_num_rows($result) == 1, "Login siswa berhasil dengan username 'siswa1' dan password 'siswa123'");
    }
    
    private function testLoginUsernameSalah() {
        $username = 'guru_salah';
        $password = md5('guru123');
        $role = 'guru';
        
        $query = "SELECT * FROM users WHERE username='$username' AND password='$password' AND role='$role'";
        $result = mysqli_query($this->conn, $query);
        
        $this->assertTrue(mysqli_num_rows($result) == 0, "Login gagal dengan username salah");
    }
    
    private function testLoginPasswordSalah() {
        $username = 'guru1';
        $password = md5('password_salah');
        $role = 'guru';
        
        $query = "SELECT * FROM users WHERE username='$username' AND password='$password' AND role='$role'";
        $result = mysqli_query($this->conn, $query);
        
        $this->assertTrue(mysqli_num_rows($result) == 0, "Login gagal dengan password salah");
    }
    
    private function testLoginRoleSalah() {
        $username = 'guru1';
        $password = md5('guru123');
        $role = 'siswa'; // Role salah (guru login sebagai siswa)
        
        $query = "SELECT * FROM users WHERE username='$username' AND password='$password' AND role='$role'";
        $result = mysqli_query($this->conn, $query);
        
        $this->assertTrue(mysqli_num_rows($result) == 0, "Login gagal dengan role salah");
    }
    
    private function tampilkanHasil() {
        echo "\n=====================================\n";
        echo "        HASIL TEST LOGIN\n";
        echo "=====================================\n";
        echo "Total Test  : $this->total_test\n";
        echo "Passed      : $this->test_passed\n";
        echo "Failed      : " . ($this->total_test - $this->test_passed) . "\n";
        echo "=====================================\n";
    }
}

// Jalankan test
$test = new TestLogin($conn);
$test->runTests();
?>