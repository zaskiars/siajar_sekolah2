<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php'; // SUDAH INCLUDE FUNGSI getHariIndonesia()

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'guru') {
    header("Location: ../login.php");
    exit();
}

$guru_id = $_SESSION['user_id'];
$nama = $_SESSION['nama'];

// =====================================================
// STATISTIK UTAMA
// =====================================================

// Total mata pelajaran yang diajar
$query_mapel = "SELECT COUNT(*) as total FROM mata_pelajaran WHERE guru_id = $guru_id";
$result_mapel = mysqli_query($conn, $query_mapel);
$total_mapel = mysqli_fetch_assoc($result_mapel)['total'];

// Total siswa yang diajar (berdasarkan nilai yang pernah diinput)
$query_siswa = "SELECT COUNT(DISTINCT n.siswa_id) as total 
                FROM nilai n 
                JOIN mata_pelajaran mp ON n.mapel_id = mp.id 
                WHERE mp.guru_id = $guru_id";
$result_siswa = mysqli_query($conn, $query_siswa);
$total_siswa = mysqli_fetch_assoc($result_siswa)['total'];

// Total nilai yang sudah diinput
$query_nilai = "SELECT COUNT(*) as total 
                FROM nilai n 
                JOIN mata_pelajaran mp ON n.mapel_id = mp.id 
                WHERE mp.guru_id = $guru_id";
$result_nilai = mysqli_query($conn, $query_nilai);
$total_nilai = mysqli_fetch_assoc($result_nilai)['total'];

// Total tugas aktif
$query_tugas = "SELECT COUNT(*) as total 
                FROM tugas t 
                JOIN mata_pelajaran mp ON t.mapel_id = mp.id 
                WHERE mp.guru_id = $guru_id 
                AND t.tanggal_deadline >= CURDATE()";
$result_tugas = mysqli_query($conn, $query_tugas);
$total_tugas = mysqli_fetch_assoc($result_tugas)['total'];

// Rata-rata nilai keseluruhan
$query_rata = "SELECT AVG(n.nilai) as rata 
               FROM nilai n 
               JOIN mata_pelajaran mp ON n.mapel_id = mp.id 
               WHERE mp.guru_id = $guru_id";
$result_rata = mysqli_query($conn, $query_rata);
$rata_nilai = round(mysqli_fetch_assoc($result_rata)['rata'] ?: 0, 2);

// =====================================================
// GRAFIK NILAI PER KELAS
// =====================================================
$query_grafik_kelas = "SELECT 
                        u.kelas,
                        AVG(n.nilai) as rata_rata
                       FROM nilai n 
                       JOIN users u ON n.siswa_id = u.id 
                       JOIN mata_pelajaran mp ON n.mapel_id = mp.id 
                       WHERE mp.guru_id = $guru_id 
                       GROUP BY u.kelas
                       ORDER BY u.kelas";
$result_grafik_kelas = mysqli_query($conn, $query_grafik_kelas);

$kelas_labels = [];
$nilai_data = [];
while ($row = mysqli_fetch_assoc($result_grafik_kelas)) {
    $kelas_labels[] = $row['kelas'];
    $nilai_data[] = round($row['rata_rata'], 2);
}

// =====================================================
// GRAFIK DISTRIBUSI NILAI
// =====================================================
$query_distribusi = "SELECT 
                      CASE 
                        WHEN nilai >= 90 THEN 'A (90-100)'
                        WHEN nilai >= 80 THEN 'B (80-89)'
                        WHEN nilai >= 70 THEN 'C (70-79)'
                        ELSE 'D (<70)'
                      END as kategori,
                      COUNT(*) as jumlah
                     FROM nilai n
                     JOIN mata_pelajaran mp ON n.mapel_id = mp.id
                     WHERE mp.guru_id = $guru_id
                     GROUP BY kategori
                     ORDER BY kategori";
$result_distribusi = mysqli_query($conn, $query_distribusi);

$kategori_labels = [];
$jumlah_data = [];
while ($row = mysqli_fetch_assoc($result_distribusi)) {
    $kategori_labels[] = $row['kategori'];
    $jumlah_data[] = $row['jumlah'];
}

// =====================================================
// NILAI TERBARU
// =====================================================
$query_terbaru = "SELECT 
                    n.*, 
                    u.nama_lengkap, 
                    u.kelas, 
                    mp.nama_mapel 
                  FROM nilai n 
                  JOIN users u ON n.siswa_id = u.id 
                  JOIN mata_pelajaran mp ON n.mapel_id = mp.id 
                  WHERE mp.guru_id = $guru_id 
                  ORDER BY n.created_at DESC 
                  LIMIT 10";
$result_terbaru = mysqli_query($conn, $query_terbaru);

// =====================================================
// TUGAS TERDEKAT
// =====================================================
$query_tugas_terdekat = "SELECT 
                           t.*, 
                           mp.nama_mapel,
                           DATEDIFF(t.tanggal_deadline, CURDATE()) as sisa_hari
                         FROM tugas t
                         JOIN mata_pelajaran mp ON t.mapel_id = mp.id
                         WHERE mp.guru_id = $guru_id 
                           AND t.tanggal_deadline >= CURDATE()
                         ORDER BY t.tanggal_deadline ASC
                         LIMIT 5";
$result_tugas_terdekat = mysqli_query($conn, $query_tugas_terdekat);

// =====================================================
// JADWAL UJIAN MENDATANG
// =====================================================
$query_jadwal = "SELECT 
                   j.*, 
                   mp.nama_mapel,
                   DAYNAME(j.tanggal_ujian) as hari_inggris,
                   DATEDIFF(j.tanggal_ujian, CURDATE()) as sisa_hari
                 FROM jadwal_ujian j
                 JOIN mata_pelajaran mp ON j.mapel_id = mp.id
                 WHERE mp.guru_id = $guru_id 
                   AND j.tanggal_ujian >= CURDATE()
                 ORDER BY j.tanggal_ujian ASC
                 LIMIT 5";
$result_jadwal = mysqli_query($conn, $query_jadwal);

// =====================================================
// ABSENSI HARI INI
// =====================================================
$query_absensi_hari = "SELECT 
                         a.status,
                         COUNT(*) as jumlah
                       FROM absensi a
                       JOIN users u ON a.siswa_id = u.id
                       WHERE a.tanggal = CURDATE()
                         AND u.kelas IN (
                           SELECT DISTINCT u2.kelas 
                           FROM users u2 
                           JOIN nilai n ON u2.id = n.siswa_id 
                           JOIN mata_pelajaran mp ON n.mapel_id = mp.id 
                           WHERE mp.guru_id = $guru_id
                         )
                       GROUP BY a.status";
$result_absensi_hari = mysqli_query($conn, $query_absensi_hari);

$absensi_hari = ['hadir' => 0, 'sakit' => 0, 'izin' => 0, 'alpha' => 0];
while ($row = mysqli_fetch_assoc($result_absensi_hari)) {
    $absensi_hari[$row['status']] = $row['jumlah'];
}
$total_absensi_hari = array_sum($absensi_hari);

// =====================================================
// KELAS YANG DIAJAR
// =====================================================
$query_kelas = "SELECT DISTINCT 
                  u.kelas,
                  COUNT(DISTINCT u.id) as jumlah_siswa
                FROM users u
                JOIN nilai n ON u.id = n.siswa_id
                JOIN mata_pelajaran mp ON n.mapel_id = mp.id
                WHERE mp.guru_id = $guru_id 
                  AND u.kelas IS NOT NULL
                GROUP BY u.kelas
                ORDER BY u.kelas";
$result_kelas = mysqli_query($conn, $query_kelas);

// =====================================================
// FUNGSI BANTU - HANYA SATU FUNGSI (getStatusBadge) 
// KARENA getHariIndonesia SUDAH ADA DI DATABASE.PHP
// =====================================================
function getStatusBadge($status) {
    $badge = [
        'hadir' => 'success',
        'sakit' => 'warning',
        'izin' => 'info',
        'alpha' => 'danger'
    ];
    return $badge[$status] ?? 'secondary';
}

// getHariIndonesia TIDAK PERLU DIDEKLARASI DI SINI
// SUDAH ADA DI CONFIG/DATABASE.PHP
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Guru - SIAJAR Sekolah</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f4f7fc;
        }

        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 1rem 2rem;
        }

        .navbar-brand,
        .nav-link {
            color: white !important;
        }

        .sidebar {
            background: white;
            min-height: calc(100vh - 76px);
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
        }

        .sidebar-menu li {
            margin-bottom: 5px;
        }

        .sidebar-menu a {
            display: block;
            padding: 12px 15px;
            color: #333;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .sidebar-menu i {
            margin-right: 10px;
            width: 20px;
        }

        .main-content {
            padding: 30px;
        }

        .welcome-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }

        .welcome-card::after {
            content: '👨‍🏫';
            font-size: 8rem;
            position: absolute;
            right: 20px;
            bottom: -20px;
            opacity: 0.2;
            transform: rotate(-10deg);
        }

        .welcome-card h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .welcome-card .info {
            display: flex;
            gap: 20px;
            margin-top: 15px;
        }

        .welcome-card .info-item {
            background: rgba(255, 255, 255, 0.2);
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .stat-card i {
            font-size: 2.5rem;
            color: #667eea;
            margin-bottom: 15px;
        }

        .stat-card h3 {
            font-size: 2rem;
            font-weight: 700;
            margin: 10px 0;
        }

        .stat-card .label {
            color: #666;
            font-size: 0.9rem;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
            transition: all 0.3s;
        }

        .card:hover {
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            background: white;
            border-bottom: 2px solid #f0f0f0;
            padding: 20px;
            font-weight: 600;
            border-radius: 15px 15px 0 0 !important;
        }

        .card-header i {
            color: #667eea;
            margin-right: 10px;
        }

        .quick-action {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            transition: all 0.3s;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
            height: 100%;
        }

        .quick-action:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .quick-action i {
            font-size: 2rem;
            color: #667eea;
            margin-bottom: 10px;
        }

        .quick-action:hover i {
            color: white;
        }

        .table th {
            border-top: none;
            font-weight: 600;
            color: #666;
        }

        .badge {
            padding: 5px 10px;
            border-radius: 5px;
            font-weight: 500;
        }

        .badge-success {
            background: #28a745;
            color: white;
        }

        .badge-warning {
            background: #ffc107;
            color: #333;
        }

        .badge-info {
            background: #17a2b8;
            color: white;
        }

        .badge-danger {
            background: #dc3545;
            color: white;
        }

        .badge-primary {
            background: #667eea;
            color: white;
        }

        .badge-secondary {
            background: #6c757d;
            color: white;
        }

        .kelas-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 15px;
            border-bottom: 1px solid #f0f0f0;
        }

        .kelas-item:last-child {
            border-bottom: none;
        }

        .btn-kelas {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 5px 15px;
            border-radius: 5px;
            font-size: 0.9rem;
            transition: all 0.3s;
            text-decoration: none;
        }

        .btn-kelas:hover {
            transform: scale(1.05);
            color: white;
        }

        .activity-item {
            display: flex;
            align-items: start;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 35px;
            height: 35px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
        }

        .activity-icon.nilai {
            background: #e3f2fd;
            color: #1976d2;
        }

        .activity-icon.tugas {
            background: #e8f5e9;
            color: #388e3c;
        }

        .activity-icon.absensi {
            background: #fff3e0;
            color: #f57c00;
        }

        .progress {
            height: 8px;
            border-radius: 4px;
        }

        .info-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
        }

        .today-badge {
            background: #dc3545;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            margin-left: 5px;
        }

        .jadwal-item {
            padding: 12px 15px;
            border-bottom: 1px solid #f0f0f0;
        }

        .jadwal-item:last-child {
            border-bottom: none;
        }

        .jadwal-item.today {
            background-color: #fff3cd;
        }

        .text-today {
            color: #dc3545;
            font-weight: 600;
        }
    </style>
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-school"></i> SIAJAR Sekolah
            </a>
            <div class="navbar-nav ms-auto">
                <span class="nav-link">
                    <i class="fas fa-user-circle"></i> <?php echo $nama; ?> (Guru)
                </span>
                <a class="nav-link" href="../logout.php">
                    <i class="fas fa-sign-out-alt"></i> Keluar
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0">
                <div class="sidebar">
                    <ul class="sidebar-menu">
                        <li><a href="dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a></li>
                        <li><a href="input_nilai.php"><i class="fas fa-edit"></i> Input Nilai</a></li>
                        <li><a href="input_absensi.php"><i class="fas fa-calendar-check"></i> Input Absensi</a></li>
                        <li><a href="rekap_nilai.php"><i class="fas fa-chart-line"></i> Rekap Nilai</a></li>
                        <li><a href="kelola_tugas.php"><i class="fas fa-tasks"></i> Kelola Tugas</a></li>
                        <li><a href="jadwal_ujian.php"><i class="fas fa-clock"></i> Jadwal Ujian</a></li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="main-content">
                    <!-- Welcome Card -->
                    <div class="welcome-card">
                        <h1>Selamat Datang, Bapak/Ibu <?php echo $nama; ?>! 👋</h1>
                        <p>Kelola pembelajaran dengan mudah dan pantau perkembangan siswa.</p>
                        <div class="info">
                            <div class="info-item">
                                <i class="fas fa-book"></i> <?php echo $total_mapel; ?> Mata Pelajaran
                            </div>
                            <div class="info-item">
                                <i class="fas fa-users"></i> <?php echo $total_siswa; ?> Siswa
                            </div>
                            <div class="info-item">
                                <i class="fas fa-calendar"></i> <?php echo date('d F Y'); ?>
                            </div>
                        </div>
                    </div>

                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="stat-card text-center">
                                <i class="fas fa-book"></i>
                                <h3><?php echo $total_mapel; ?></h3>
                                <div class="label">Mata Pelajaran</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card text-center">
                                <i class="fas fa-users"></i>
                                <h3><?php echo $total_siswa; ?></h3>
                                <div class="label">Total Siswa</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card text-center">
                                <i class="fas fa-star"></i>
                                <h3><?php echo $total_nilai; ?></h3>
                                <div class="label">Nilai Terinput</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card text-center">
                                <i class="fas fa-tasks"></i>
                                <h3><?php echo $total_tugas; ?></h3>
                                <div class="label">Tugas Aktif</div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <a href="input_nilai.php" class="quick-action">
                                <i class="fas fa-plus-circle"></i>
                                <h6>Input Nilai</h6>
                                <small>Tambah nilai siswa</small>
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="input_absensi.php" class="quick-action">
                                <i class="fas fa-check-circle"></i>
                                <h6>Input Absensi</h6>
                                <small>Catat kehadiran</small>
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="kelola_tugas.php" class="quick-action">
                                <i class="fas fa-file-alt"></i>
                                <h6>Buat Tugas</h6>
                                <small>Tambah tugas baru</small>
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="jadwal_ujian.php" class="quick-action">
                                <i class="fas fa-clock"></i>
                                <h6>Jadwal Ujian</h6>
                                <small>Atur jadwal ujian</small>
                            </a>
                        </div>
                    </div>

                    <!-- Charts Row -->
                    <div class="row">
                        <div class="col-md-7">
                            <div class="card">
                                <div class="card-header">
                                    <i class="fas fa-chart-bar"></i> Rata-rata Nilai per Kelas
                                </div>
                                <div class="card-body">
                                    <canvas id="chartKelas" style="height: 300px;"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="card">
                                <div class="card-header">
                                    <i class="fas fa-chart-pie"></i> Distribusi Nilai
                                </div>
                                <div class="card-body">
                                    <canvas id="chartDistribusi" style="height: 300px;"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Info Row -->
                    <div class="row mt-4">
                        <!-- Kelas yang Diajar -->
                        <div class="col-md-4">
                            <div class="card h-100">
                                <div class="card-header">
                                    <i class="fas fa-door-open"></i> Kelas yang Diajar
                                </div>
                                <div class="card-body">
                                    <?php if (mysqli_num_rows($result_kelas) > 0): ?>
                                        <?php while ($kelas = mysqli_fetch_assoc($result_kelas)): ?>
                                            <div class="kelas-item">
                                                <span>
                                                    <i class="fas fa-users"></i> Kelas <?php echo $kelas['kelas']; ?>
                                                    <span class="badge bg-secondary ms-2"><?php echo $kelas['jumlah_siswa']; ?> siswa</span>
                                                </span>
                                                <a href="rekap_nilai.php?kelas=<?php echo urlencode($kelas['kelas']); ?>" class="btn-kelas">
                                                    <i class="fas fa-arrow-right"></i>
                                                </a>
                                            </div>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <p class="text-muted text-center mb-0">Belum ada kelas</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Tugas Mendatang -->
                        <div class="col-md-4">
                            <div class="card h-100">
                                <div class="card-header">
                                    <i class="fas fa-clock"></i> Tugas Mendatang
                                </div>
                                <div class="card-body p-0">
                                    <?php if (mysqli_num_rows($result_tugas_terdekat) > 0): ?>
                                        <?php while ($tugas = mysqli_fetch_assoc($result_tugas_terdekat)):
                                            $is_today = ($tugas['sisa_hari'] == 0);
                                        ?>
                                            <div class="activity-item px-3 <?php echo $is_today ? 'jadwal-item today' : ''; ?>">
                                                <div class="activity-icon tugas">
                                                    <i class="fas fa-file-alt"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="d-flex justify-content-between">
                                                        <strong><?php echo $tugas['judul']; ?></strong>
                                                        <?php if ($is_today): ?>
                                                            <span class="badge bg-danger">Hari Ini</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-<?php echo $tugas['sisa_hari'] <= 3 ? 'warning' : 'primary'; ?>">
                                                                <?php echo $tugas['sisa_hari']; ?> hari
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <small class="text-muted">
                                                        <?php echo $tugas['nama_mapel']; ?> • 
                                                        Deadline: <?php echo date('d/m/Y', strtotime($tugas['tanggal_deadline'])); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <p class="text-muted text-center py-4">Tidak ada tugas mendatang</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Jadwal Ujian Mendatang -->
                        <div class="col-md-4">
                            <div class="card h-100">
                                <div class="card-header">
                                    <i class="fas fa-calendar-alt"></i> Jadwal Ujian Mendatang
                                </div>
                                <div class="card-body p-0">
                                    <?php if (mysqli_num_rows($result_jadwal) > 0): ?>
                                        <?php while ($jadwal = mysqli_fetch_assoc($result_jadwal)):
                                            $is_today = ($jadwal['sisa_hari'] == 0);
                                            $hari = getHariIndonesia($jadwal['hari_inggris']); // PANGGIL FUNGSI DARI DATABASE.PHP
                                        ?>
                                            <div class="jadwal-item px-3 <?php echo $is_today ? 'today' : ''; ?>">
                                                <div class="d-flex justify-content-between">
                                                    <div>
                                                        <strong><?php echo $jadwal['nama_mapel']; ?></strong>
                                                        <?php if ($is_today): ?>
                                                            <span class="today-badge">Hari Ini</span>
                                                        <?php endif; ?>
                                                        <div>
                                                            <small class="text-muted">
                                                                <i class="far fa-calendar"></i> <?php echo date('d/m/Y', strtotime($jadwal['tanggal_ujian'])); ?> (<?php echo $hari; ?>)
                                                            </small>
                                                        </div>
                                                        <div>
                                                            <small class="text-muted">
                                                                <i class="far fa-clock"></i> <?php echo substr($jadwal['waktu_mulai'], 0, 5); ?> - <?php echo substr($jadwal['waktu_selesai'], 0, 5); ?>
                                                                <i class="fas fa-door-open ms-2"></i> <?php echo $jadwal['ruangan']; ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                    <?php if (!$is_today): ?>
                                                        <span class="badge bg-<?php echo $jadwal['sisa_hari'] <= 3 ? 'warning' : 'primary'; ?>">
                                                            H-<?php echo $jadwal['sisa_hari']; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <p class="text-muted text-center py-4">Tidak ada jadwal ujian</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Nilai Terbaru & Absensi Hari Ini -->
                    <div class="row mt-4">
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header">
                                    <i class="fas fa-history"></i> Nilai Terbaru
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Tanggal</th>
                                                    <th>Siswa</th>
                                                    <th>Kelas</th>
                                                    <th>Mapel</th>
                                                    <th>Tugas</th>
                                                    <th>Nilai</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (mysqli_num_rows($result_terbaru) > 0): ?>
                                                    <?php while ($row = mysqli_fetch_assoc($result_terbaru)): ?>
                                                        <tr>
                                                            <td><?php echo date('d/m/Y H:i', strtotime($row['created_at'])); ?></td>
                                                            <td><?php echo $row['nama_lengkap']; ?></td>
                                                            <td><?php echo $row['kelas']; ?></td>
                                                            <td><?php echo $row['nama_mapel']; ?></td>
                                                            <td><?php echo $row['tugas']; ?></td>
                                                            <td>
                                                                <span class="badge bg-<?php echo $row['nilai'] >= 75 ? 'success' : 'danger'; ?>">
                                                                    <?php echo $row['nilai']; ?>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    <?php endwhile; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="6" class="text-center py-4">
                                                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                                            <p class="text-muted">Belum ada nilai</p>
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <i class="fas fa-calendar-check"></i> Absensi Hari Ini (<?php echo date('d/m/Y'); ?>)
                                </div>
                                <div class="card-body">
                                    <div class="text-center mb-4">
                                        <h1 class="display-4 text-primary"><?php echo $total_absensi_hari; ?></h1>
                                        <p class="text-muted">Total Siswa</p>
                                    </div>

                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span><i class="fas fa-check-circle text-success"></i> Hadir</span>
                                            <span><?php echo $absensi_hari['hadir']; ?> siswa</span>
                                        </div>
                                        <div class="progress">
                                            <div class="progress-bar bg-success" style="width: <?php echo $total_absensi_hari > 0 ? ($absensi_hari['hadir'] / $total_absensi_hari) * 100 : 0; ?>%"></div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span><i class="fas fa-hospital text-warning"></i> Sakit</span>
                                            <span><?php echo $absensi_hari['sakit']; ?> siswa</span>
                                        </div>
                                        <div class="progress">
                                            <div class="progress-bar bg-warning" style="width: <?php echo $total_absensi_hari > 0 ? ($absensi_hari['sakit'] / $total_absensi_hari) * 100 : 0; ?>%"></div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span><i class="fas fa-envelope text-info"></i> Izin</span>
                                            <span><?php echo $absensi_hari['izin']; ?> siswa</span>
                                        </div>
                                        <div class="progress">
                                            <div class="progress-bar bg-info" style="width: <?php echo $total_absensi_hari > 0 ? ($absensi_hari['izin'] / $total_absensi_hari) * 100 : 0; ?>%"></div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span><i class="fas fa-times-circle text-danger"></i> Alpha</span>
                                            <span><?php echo $absensi_hari['alpha']; ?> siswa</span>
                                        </div>
                                        <div class="progress">
                                            <div class="progress-bar bg-danger" style="width: <?php echo $total_absensi_hari > 0 ? ($absensi_hari['alpha'] / $total_absensi_hari) * 100 : 0; ?>%"></div>
                                        </div>
                                    </div>

                                    <div class="text-center mt-4">
                                        <a href="input_absensi.php" class="btn btn-primary btn-sm">
                                            <i class="fas fa-edit"></i> Input Absensi
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <!-- Info Card Rata-rata Nilai -->
                            <div class="info-card mt-4">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="mb-1">Rata-rata Nilai</h5>
                                        <h2 class="mb-0"><?php echo $rata_nilai; ?></h2>
                                    </div>
                                    <i class="fas fa-chart-line fa-3x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Chart Rata-rata per Kelas
        new Chart(document.getElementById('chartKelas'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($kelas_labels); ?>,
                datasets: [{
                    label: 'Rata-rata Nilai',
                    data: <?php echo json_encode($nilai_data); ?>,
                    backgroundColor: 'rgba(102, 126, 234, 0.7)',
                    borderColor: '#667eea',
                    borderWidth: 2,
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

        // Chart Distribusi Nilai
        new Chart(document.getElementById('chartDistribusi'), {
            type: 'pie',
            data: {
                labels: <?php echo json_encode($kategori_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($jumlah_data); ?>,
                    backgroundColor: ['#28a745', '#17a2b8', '#ffc107', '#dc3545'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>