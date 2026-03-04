<?php
/**
 * Runner untuk menjalankan semua unit test
 * 
 * Jalankan dengan: php tests/run_all_tests.php
 */

echo "\n";
echo "╔════════════════════════════════════════════════╗\n";
echo "║        MENJALANKAN SEMUA UNIT TEST            ║\n";
echo "║           SIAJAR Sekolah                       ║\n";
echo "╚════════════════════════════════════════════════╝\n";
echo "\n";

// Test Database
require_once 'test_database.php';
echo "\n";

// Test Login
require_once 'test_login.php';
echo "\n";

// Test Nilai
require_once 'test_nilai.php';
echo "\n";

// Test Absensi
require_once 'test_absensi.php';
echo "\n";

// Test Jadwal
require_once 'test_jadwal.php';
echo "\n";

echo "╔════════════════════════════════════════════════╗\n";
echo "║           SEMUA TEST SELESAI                   ║\n";
echo "╚════════════════════════════════════════════════╝\n";
echo "\n";
?>