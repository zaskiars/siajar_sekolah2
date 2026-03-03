<?php
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'siajar_sekolah';

$conn = mysqli_connect($host, $username, $password, $database);

if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// Set timezone
date_default_timezone_set('Asia/Jakarta');

// Fungsi untuk mengecek login
function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

// Fungsi untuk mengecek role
function isSiswa()
{
    return isset($_SESSION['role']) && $_SESSION['role'] == 'siswa';
}

function isGuru()
{
    return isset($_SESSION['role']) && $_SESSION['role'] == 'guru';
}

// Fungsi untuk mendapatkan nama bulan dalam bahasa Indonesia
function getBulanIndonesia($bulan)
{
    $bulan_array = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    return $bulan_array[(int)$bulan] ?? 'Januari';
}

// Fungsi untuk mendapatkan hari dalam bahasa Indonesia
function getHariIndonesia($hari_inggris)
{
    $hari_array = [
        'Monday' => 'Senin',
        'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu',
        'Thursday' => 'Kamis',
        'Friday' => 'Jumat',
        'Saturday' => 'Sabtu',
        'Sunday' => 'Minggu'
    ];
    return $hari_array[$hari_inggris] ?? $hari_inggris;
}

// Fungsi untuk format tanggal Indonesia
function tglIndonesia($tanggal)
{
    $bulan = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];

    $tgl = date('j', strtotime($tanggal));
    $bln = date('n', strtotime($tanggal));
    $thn = date('Y', strtotime($tanggal));

    return $tgl . ' ' . ($bulan[(int)$bln] ?? '') . ' ' . $thn;
}

// Fungsi untuk mengecek apakah tanggal adalah hari ini
function isToday($tanggal)
{
    return date('Y-m-d', strtotime($tanggal)) == date('Y-m-d');
}

// Fungsi untuk mendapatkan class hari ini
function getTodayClass($tanggal)
{
    return isToday($tanggal) ? 'today' : '';
}
?>