<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'siswa') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// =====================================================
// FILTER SEMESTER DAN TAHUN AJARAN
// =====================================================
$semester = isset($_GET['semester']) ? $_GET['semester'] : 'Ganjil';
$tahun_ajaran = isset($_GET['tahun_ajaran']) ? $_GET['tahun_ajaran'] : '2024/2025';

// =====================================================
// AMBIL DATA SISWA
// =====================================================
$query_siswa = "SELECT * FROM users WHERE id = $user_id";
$result_siswa = mysqli_query($conn, $query_siswa);
$siswa = mysqli_fetch_assoc($result_siswa);

// =====================================================
// AMBIL DATA NILAI PER MATA PELAJARAN
// =====================================================
$query_nilai = "SELECT 
                    mp.id as mapel_id,
                    mp.nama_mapel,
                    AVG(CASE WHEN n.tugas = 'UTS' THEN n.nilai END) as nilai_uts,
                    AVG(CASE WHEN n.tugas = 'UAS' THEN n.nilai END) as nilai_uas,
                    AVG(n.nilai) as rata_rata,
                    COUNT(n.id) as jumlah_tugas
                FROM mata_pelajaran mp
                LEFT JOIN nilai n ON mp.id = n.mapel_id 
                    AND n.siswa_id = $user_id 
                    AND n.semester = '$semester' 
                    AND n.tahun_ajaran = '$tahun_ajaran'
                GROUP BY mp.id, mp.nama_mapel
                ORDER BY mp.nama_mapel";
$result_nilai = mysqli_query($conn, $query_nilai);

// =====================================================
// HITUNG STATISTIK
// =====================================================
$total_nilai = 0;
$jumlah_mapel = 0;
$nilai_per_mapel = [];

while ($row = mysqli_fetch_assoc($result_nilai)) {
    if ($row['rata_rata']) {
        $total_nilai += $row['rata_rata'];
        $jumlah_mapel++;
        $nilai_per_mapel[] = $row;
    }
}
mysqli_data_seek($result_nilai, 0);

$rata_total = $jumlah_mapel > 0 ? round($total_nilai / $jumlah_mapel, 2) : 0;

// Tentukan predikat total
if ($rata_total >= 90) {
    $predikat_total = 'A (Sangat Baik)';
    $class_total = 'text-success';
} elseif ($rata_total >= 80) {
    $predikat_total = 'B (Baik)';
    $class_total = 'text-primary';
} elseif ($rata_total >= 70) {
    $predikat_total = 'C (Cukup)';
    $class_total = 'text-warning';
} else {
    $predikat_total = 'D (Kurang)';
    $class_total = 'text-danger';
}

// =====================================================
// AMBIL DATA ABSENSI
// =====================================================
$query_absensi = "SELECT 
                    COUNT(CASE WHEN status = 'hadir' THEN 1 END) as hadir,
                    COUNT(CASE WHEN status = 'sakit' THEN 1 END) as sakit,
                    COUNT(CASE WHEN status = 'izin' THEN 1 END) as izin,
                    COUNT(CASE WHEN status = 'alpha' THEN 1 END) as alpha
                  FROM absensi 
                  WHERE siswa_id = $user_id";
$result_absensi = mysqli_query($conn, $query_absensi);
$absensi = mysqli_fetch_assoc($result_absensi);

$total_hari = array_sum($absensi);
$persen_hadir = $total_hari > 0 ? round(($absensi['hadir'] / $total_hari) * 100, 2) : 0;

// =====================================================
// DATA UNTUK GRAFIK PERKEMBANGAN (PER SEMESTER)
// =====================================================
$query_perkembangan = "SELECT 
                        CONCAT(semester, ' ', tahun_ajaran) as periode,
                        AVG(nilai) as rata_rata
                      FROM nilai
                      WHERE siswa_id = $user_id
                      GROUP BY semester, tahun_ajaran
                      ORDER BY tahun_ajaran, semester
                      LIMIT 4";
$result_perkembangan = mysqli_query($conn, $query_perkembangan);

$periode_labels = [];
$perkembangan_data = [];
while ($row = mysqli_fetch_assoc($result_perkembangan)) {
    $periode_labels[] = $row['periode'];
    $perkembangan_data[] = round($row['rata_rata'], 2);
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapor - SIAJAR Sekolah</title>
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
        }

        .main-content {
            padding: 30px;
        }

        .filter-card {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }

        .btn-print {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-print:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }

        .rapor-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            padding: 40px;
            margin-bottom: 30px;
        }

        .rapor-header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 3px double #667eea;
        }

        .rapor-header h1 {
            color: #667eea;
            font-weight: 700;
            font-size: 2.5rem;
            margin-bottom: 5px;
        }

        .rapor-header h3 {
            color: #666;
            font-size: 1.2rem;
        }

        .info-siswa {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            border-left: 5px solid #667eea;
        }

        .info-siswa table {
            width: 100%;
        }

        .info-siswa td {
            padding: 8px 0;
        }

        .info-siswa .label {
            font-weight: 600;
            color: #666;
            width: 150px;
        }

        .info-siswa .value {
            color: #333;
            font-weight: 500;
        }

        .summary-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            text-align: center;
        }

        .summary-card h2 {
            font-size: 2.5rem;
            font-weight: 700;
        }

        .table-rapor {
            margin-bottom: 30px;
        }

        .table-rapor th {
            background: #667eea;
            color: white;
            font-weight: 600;
            border: none;
            padding: 12px;
        }

        .table-rapor td {
            padding: 12px;
            vertical-align: middle;
        }

        .table-rapor tbody tr:hover {
            background: #f8f9fa;
        }

        .predikat-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .predikat-a {
            background: #28a745;
            color: white;
        }

        .predikat-b {
            background: #17a2b8;
            color: white;
        }

        .predikat-c {
            background: #ffc107;
            color: #333;
        }

        .predikat-d {
            background: #dc3545;
            color: white;
        }

        .footer-rapor {
            margin-top: 40px;
            padding-top: 30px;
            border-top: 2px dashed #dee2e6;
        }

        .ttd {
            margin-top: 50px;
        }

        .ttd .wali-kelas {
            text-align: center;
            width: 200px;
        }

        .grafik-container {
            height: 250px;
            margin-bottom: 30px;
        }

        @media print {
            .navbar,
            .sidebar,
            .btn-print,
            .filter-card,
            form,
            footer {
                display: none !important;
            }

            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }

            .col-md-9,
            .col-lg-10 {
                width: 100% !important;
                flex: 0 0 100% !important;
                max-width: 100% !important;
            }

            .rapor-container {
                box-shadow: none !important;
                padding: 20px !important;
            }
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
                    <i class="fas fa-user-circle"></i> <?php echo $_SESSION['nama']; ?>
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
                        <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                        <li><a href="nilai.php"><i class="fas fa-book"></i> Nilai & Tugas</a></li>
                        <li><a href="absensi.php"><i class="fas fa-calendar-check"></i> Absensi</a></li>
                        <li><a href="jadwal.php"><i class="fas fa-clock"></i> Jadwal Ujian</a></li>
                        <li><a href="rapor.php" class="active"><i class="fas fa-file-alt"></i> Rapor</a></li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="main-content">
                    <!-- Tombol Print dan Filter -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="mb-0"><i class="fas fa-file-alt text-primary me-2"></i> Rapor Siswa</h4>
                        <button class="btn-print" onclick="window.print()">
                            <i class="fas fa-print"></i> Cetak Rapor
                        </button>
                    </div>

                    <!-- Filter Semester -->
                    <div class="filter-card">
                        <form method="GET" class="row">
                            <div class="col-md-5">
                                <label class="form-label">Semester</label>
                                <select name="semester" class="form-control">
                                    <option value="Ganjil" <?php echo $semester == 'Ganjil' ? 'selected' : ''; ?>>Ganjil</option>
                                    <option value="Genap" <?php echo $semester == 'Genap' ? 'selected' : ''; ?>>Genap</option>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Tahun Ajaran</label>
                                <select name="tahun_ajaran" class="form-control">
                                    <option value="2023/2024" <?php echo $tahun_ajaran == '2023/2024' ? 'selected' : ''; ?>>2023/2024</option>
                                    <option value="2024/2025" <?php echo $tahun_ajaran == '2024/2025' ? 'selected' : ''; ?>>2024/2025</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search"></i> Tampilkan
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Ringkasan Nilai -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="summary-card">
                                <p class="mb-1">Rata-rata Nilai</p>
                                <h2><?php echo $rata_total; ?></h2>
                                <p class="mb-0 <?php echo $class_total; ?>"><?php echo $predikat_total; ?></p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="summary-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                                <p class="mb-1">Kehadiran</p>
                                <h2><?php echo $persen_hadir; ?>%</h2>
                                <p class="mb-0">Hadir: <?php echo $absensi['hadir']; ?> hari</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="summary-card" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                                <p class="mb-1">Mata Pelajaran</p>
                                <h2><?php echo $jumlah_mapel; ?></h2>
                                <p class="mb-0">Dengan nilai</p>
                            </div>
                        </div>
                    </div>

                    <!-- Grafik Perkembangan Nilai -->
                    <?php if (!empty($periode_labels)): ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-chart-line text-primary me-2"></i> Perkembangan Nilai per Semester
                            </div>
                            <div class="card-body">
                                <div class="grafik-container">
                                    <canvas id="chartPerkembangan"></canvas>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Rapor Container -->
                    <div class="rapor-container" id="rapor">
                        <!-- Header Rapor -->
                        <div class="rapor-header">
                            <h1>SIAJAR SEKOLAH</h1>
                            <h3>LAPORAN HASIL BELAJAR SISWA</h3>
                            <p>Semester <?php echo $semester; ?> - Tahun Ajaran <?php echo $tahun_ajaran; ?></p>
                        </div>

                        <!-- Identitas Siswa -->
                        <div class="info-siswa">
                            <table>
                                <tr>
                                    <td class="label">Nama Siswa</td>
                                    <td class="value">: <?php echo $siswa['nama_lengkap']; ?></td>
                                </tr>
                                <tr>
                                    <td class="label">NIS/NISN</td>
                                    <td class="value">: <?php echo $siswa['username']; ?></td>
                                </tr>
                                <tr>
                                    <td class="label">Kelas</td>
                                    <td class="value">: <?php echo $siswa['kelas']; ?></td>
                                </tr>
                                <tr>
                                    <td class="label">Semester</td>
                                    <td class="value">: <?php echo $semester; ?></td>
                                </tr>
                                <tr>
                                    <td class="label">Tahun Ajaran</td>
                                    <td class="value">: <?php echo $tahun_ajaran; ?></td>
                                </tr>
                            </table>
                        </div>

                        <!-- Daftar Nilai -->
                        <table class="table table-bordered table-rapor">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Mata Pelajaran</th>
                                    <th>UTS</th>
                                    <th>UAS</th>
                                    <th>Rata-rata</th>
                                    <th>Predikat</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $no = 1;
                                $total = 0;
                                $count = 0;

                                while ($row = mysqli_fetch_assoc($result_nilai)):
                                    $rata = $row['rata_rata'] ? round($row['rata_rata'], 2) : 0;
                                    if ($rata > 0) {
                                        $total += $rata;
                                        $count++;

                                        if ($rata >= 90) {
                                            $predikat = 'A';
                                            $badge_class = 'predikat-a';
                                        } elseif ($rata >= 80) {
                                            $predikat = 'B';
                                            $badge_class = 'predikat-b';
                                        } elseif ($rata >= 70) {
                                            $predikat = 'C';
                                            $badge_class = 'predikat-c';
                                        } else {
                                            $predikat = 'D';
                                            $badge_class = 'predikat-d';
                                        }
                                    } else {
                                        $predikat = '-';
                                        $badge_class = 'bg-secondary';
                                    }
                                ?>
                                    <tr>
                                        <td class="text-center"><?php echo $no++; ?></td>
                                        <td><?php echo $row['nama_mapel']; ?></td>
                                        <td class="text-center"><?php echo $row['nilai_uts'] ? round($row['nilai_uts'], 2) : '-'; ?></td>
                                        <td class="text-center"><?php echo $row['nilai_uas'] ? round($row['nilai_uas'], 2) : '-'; ?></td>
                                        <td class="text-center"><strong><?php echo $rata ?: '-'; ?></strong></td>
                                        <td class="text-center">
                                            <?php if ($rata > 0): ?>
                                                <span class="predikat-badge <?php echo $badge_class; ?>">
                                                    <?php echo $predikat; ?>
                                                </span>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                            <tfoot class="table-secondary">
                                <tr>
                                    <th colspan="4" class="text-end">RATA-RATA TOTAL</th>
                                    <th class="text-center"><?php echo $count > 0 ? round($total / $count, 2) : 0; ?></th>
                                    <th></th>
                                </tr>
                            </tfoot>
                        </table>

                        <!-- Ringkasan Absensi -->
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th colspan="2" class="bg-info text-white">RINGKASAN ABSENSI</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>Hadir</td>
                                            <td><?php echo $absensi['hadir']; ?> hari</td>
                                        </tr>
                                        <tr>
                                            <td>Sakit</td>
                                            <td><?php echo $absensi['sakit']; ?> hari</td>
                                        </tr>
                                        <tr>
                                            <td>Izin</td>
                                            <td><?php echo $absensi['izin']; ?> hari</td>
                                        </tr>
                                        <tr>
                                            <td>Alpha</td>
                                            <td><?php echo $absensi['alpha']; ?> hari</td>
                                        </tr>
                                        <tr class="table-primary">
                                            <th>Total Hari</th>
                                            <th><?php echo $total_hari; ?> hari</th>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <div class="card h-100">
                                    <div class="card-body d-flex align-items-center justify-content-center">
                                        <div class="text-center">
                                            <h3 class="text-primary">Tingkat Kehadiran</h3>
                                            <h1 class="display-1 fw-bold text-success"><?php echo $persen_hadir; ?>%</h1>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Catatan Wali Kelas -->
                        <div class="mt-4">
                            <h6 class="text-primary">Catatan Wali Kelas:</h6>
                            <p class="border p-3 rounded">
                                <?php
                                if ($rata_total >= 85) {
                                    echo "Pertahankan prestasimu! Tingkatkan terus kemampuan belajarmu. Selalu jaga kesehatan dan terus berprestasi.";
                                } elseif ($rata_total >= 70) {
                                    echo "Tingkatkan lagi belajarnya, terutama mata pelajaran yang nilainya masih kurang. Jangan ragu untuk bertanya kepada guru.";
                                } else {
                                    echo "Perlu bimbingan dan belajar lebih giat lagi. Konsultasikan dengan guru BK dan orang tua untuk meningkatkan prestasi.";
                                }
                                ?>
                            </p>
                        </div>

                        <!-- Tanda Tangan -->
                        <div class="footer-rapor">
                            <div class="row">
                                <div class="col-md-4">
                                    <p>Mengetahui,<br>Kepala Sekolah</p>
                                    <br><br>
                                    <p><strong>Drs. H. Ahmad Syarif, M.Pd.</strong><br>NIP. 196512311990031012</p>
                                </div>
                                <div class="col-md-4">
                                    <p>Wali Kelas</p>
                                    <br><br>
                                    <p><strong>Dra. Siti Aminah</strong><br>NIP. 197801012005012001</p>
                                </div>
                                <div class="col-md-4 text-end">
                                    <p>Jakarta, <?php echo date('d F Y'); ?></p>
                                    <br><br>
                                    <p>Siswa,</p>
                                    <br>
                                    <p><strong><?php echo $siswa['nama_lengkap']; ?></strong></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($periode_labels)): ?>
        <script>
            new Chart(document.getElementById('chartPerkembangan'), {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($periode_labels); ?>,
                    datasets: [{
                        label: 'Rata-rata Nilai',
                        data: <?php echo json_encode($perkembangan_data); ?>,
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        borderWidth: 3,
                        pointBackgroundColor: '#667eea',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            grid: {
                                drawBorder: false
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        </script>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>