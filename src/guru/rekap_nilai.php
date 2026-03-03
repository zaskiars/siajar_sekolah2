<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'guru') {
    header("Location: ../login.php");
    exit();
}

$guru_id = $_SESSION['user_id'];

// =====================================================
// FILTER
// =====================================================
$kelas = isset($_GET['kelas']) ? $_GET['kelas'] : '';
$mapel_id = isset($_GET['mapel_id']) ? $_GET['mapel_id'] : '';
$semester = isset($_GET['semester']) ? $_GET['semester'] : 'Ganjil';
$tahun_ajaran = isset($_GET['tahun_ajaran']) ? $_GET['tahun_ajaran'] : '2024/2025';

// =====================================================
// AMBIL DATA UNTUK FILTER
// =====================================================

// Ambil daftar mata pelajaran guru
$query_mapel = "SELECT * FROM mata_pelajaran WHERE guru_id = $guru_id ORDER BY nama_mapel";
$result_mapel = mysqli_query($conn, $query_mapel);

// Ambil daftar kelas unik dari siswa yang pernah dinilai
$query_kelas = "SELECT DISTINCT u.kelas 
                FROM users u 
                JOIN nilai n ON u.id = n.siswa_id 
                JOIN mata_pelajaran mp ON n.mapel_id = mp.id 
                WHERE mp.guru_id = $guru_id AND u.kelas IS NOT NULL
                ORDER BY u.kelas";
$result_kelas = mysqli_query($conn, $query_kelas);

// =====================================================
// QUERY REKAP NILAI - DIPERBAIKI (NO DUPLIKAT)
// =====================================================
$query_rekap = "SELECT 
                    u.id as siswa_id,
                    u.nama_lengkap,
                    u.kelas,
                    mp.id as mapel_id,
                    mp.nama_mapel,
                    MAX(CASE WHEN n.tugas = 'UTS' THEN n.nilai END) as nilai_uts,
                    MAX(CASE WHEN n.tugas = 'UAS' THEN n.nilai END) as nilai_uas,
                    AVG(n.nilai) as rata_rata,
                    COUNT(n.id) as jumlah_tugas
                FROM users u
                JOIN mata_pelajaran mp ON mp.guru_id = $guru_id
                LEFT JOIN nilai n ON u.id = n.siswa_id 
                    AND n.mapel_id = mp.id 
                    AND n.semester = '$semester' 
                    AND n.tahun_ajaran = '$tahun_ajaran'
                WHERE u.role = 'siswa'";

if ($kelas) {
    $query_rekap .= " AND u.kelas = '$kelas'";
}

if ($mapel_id) {
    $query_rekap .= " AND mp.id = '$mapel_id'";
}

$query_rekap .= " GROUP BY u.id, u.nama_lengkap, u.kelas, mp.id, mp.nama_mapel
                  ORDER BY u.kelas, u.nama_lengkap, mp.nama_mapel";

$result_rekap = mysqli_query($conn, $query_rekap);

// =====================================================
// HITUNG STATISTIK
// =====================================================

// Rata-rata total
$query_rata_total = "SELECT AVG(nilai) as total 
                     FROM nilai n 
                     JOIN mata_pelajaran mp ON n.mapel_id = mp.id 
                     WHERE mp.guru_id = $guru_id 
                     AND n.semester = '$semester' 
                     AND n.tahun_ajaran = '$tahun_ajaran'";
$result_rata_total = mysqli_query($conn, $query_rata_total);
$rata_total = round(mysqli_fetch_assoc($result_rata_total)['total'] ?: 0, 2);

// Nilai tertinggi
$query_tertinggi = "SELECT MAX(nilai) as tertinggi 
                    FROM nilai n 
                    JOIN mata_pelajaran mp ON n.mapel_id = mp.id 
                    WHERE mp.guru_id = $guru_id 
                    AND n.semester = '$semester' 
                    AND n.tahun_ajaran = '$tahun_ajaran'";
$result_tertinggi = mysqli_query($conn, $query_tertinggi);
$nilai_tertinggi = mysqli_fetch_assoc($result_tertinggi)['tertinggi'] ?: 0;

// Nilai terendah
$query_terendah = "SELECT MIN(nilai) as terendah 
                   FROM nilai n 
                   JOIN mata_pelajaran mp ON n.mapel_id = mp.id 
                   WHERE mp.guru_id = $guru_id 
                   AND n.semester = '$semester' 
                   AND n.tahun_ajaran = '$tahun_ajaran'";
$result_terendah = mysqli_query($conn, $query_terendah);
$nilai_terendah = mysqli_fetch_assoc($result_terendah)['terendah'] ?: 0;

// Jumlah siswa (UNIK)
$query_jml_siswa = "SELECT COUNT(DISTINCT n.siswa_id) as total 
                    FROM nilai n 
                    JOIN mata_pelajaran mp ON n.mapel_id = mp.id 
                    WHERE mp.guru_id = $guru_id 
                    AND n.semester = '$semester' 
                    AND n.tahun_ajaran = '$tahun_ajaran'";
$result_jml_siswa = mysqli_query($conn, $query_jml_siswa);
$jml_siswa = mysqli_fetch_assoc($result_jml_siswa)['total'] ?: 0;

// =====================================================
// DISTRIBUSI NILAI UNTUK GRAFIK
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
                       AND n.semester = '$semester' 
                       AND n.tahun_ajaran = '$tahun_ajaran'
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
// DATA UNTUK GRAFIK PER KELAS
// =====================================================
$query_grafik_kelas = "SELECT 
                        u.kelas,
                        AVG(n.nilai) as rata_rata
                       FROM nilai n 
                       JOIN users u ON n.siswa_id = u.id 
                       JOIN mata_pelajaran mp ON n.mapel_id = mp.id 
                       WHERE mp.guru_id = $guru_id 
                         AND n.semester = '$semester' 
                         AND n.tahun_ajaran = '$tahun_ajaran'
                       GROUP BY u.kelas
                       ORDER BY u.kelas";
$result_grafik_kelas = mysqli_query($conn, $query_grafik_kelas);

$kelas_labels = [];
$nilai_per_kelas = [];
while ($row = mysqli_fetch_assoc($result_grafik_kelas)) {
    $kelas_labels[] = $row['kelas'];
    $nilai_per_kelas[] = round($row['rata_rata'], 2);
}

// =====================================================
// EKSPOR KE EXCEL
// =====================================================
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    header("Content-type: application/vnd-ms-excel");
    header("Content-Disposition: attachment; filename=rekap_nilai_$semester_$tahun_ajaran.xls");
    
    echo "<table border='1'>";
    echo "<tr>
            <th>No</th>
            <th>Nama Siswa</th>
            <th>Kelas</th>
            <th>Mata Pelajaran</th>
            <th>Nilai UTS</th>
            <th>Nilai UAS</th>
            <th>Rata-rata</th>
            <th>Predikat</th>
          </tr>";
    
    $no = 1;
    mysqli_data_seek($result_rekap, 0);
    while ($row = mysqli_fetch_assoc($result_rekap)) {
        $rata = $row['rata_rata'] ? round($row['rata_rata'], 2) : 0;
        if ($rata >= 90) $predikat = 'A';
        elseif ($rata >= 80) $predikat = 'B';
        elseif ($rata >= 70) $predikat = 'C';
        else $predikat = 'D';
        
        echo "<tr>
                <td>$no</td>
                <td>{$row['nama_lengkap']}</td>
                <td>{$row['kelas']}</td>
                <td>{$row['nama_mapel']}</td>
                <td>" . ($row['nilai_uts'] ? round($row['nilai_uts'], 2) : '-') . "</td>
                <td>" . ($row['nilai_uas'] ? round($row['nilai_uas'], 2) : '-') . "</td>
                <td>" . ($rata ?: '-') . "</td>
                <td>$predikat</td>
              </tr>";
        $no++;
    }
    echo "</table>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekap Nilai - SIAJAR Sekolah</title>
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

        .page-header {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
        }

        .filter-card {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
            text-align: center;
            height: 100%;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .stat-card .label {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }

        .stat-card .value {
            font-size: 2rem;
            font-weight: 700;
            color: #667eea;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
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

        .btn-export {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-export:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
            color: white;
        }

        .table th {
            background: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
        }

        .badge {
            padding: 5px 10px;
            border-radius: 5px;
            font-weight: 500;
        }

        .badge-a {
            background: #28a745;
            color: white;
        }

        .badge-b {
            background: #17a2b8;
            color: white;
        }

        .badge-c {
            background: #ffc107;
            color: #333;
        }

        .badge-d {
            background: #dc3545;
            color: white;
        }

        .grafik-container {
            height: 300px;
            position: relative;
        }

        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #667eea;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .table-responsive {
            overflow-x: auto;
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
                    <i class="fas fa-user-circle"></i> <?php echo $_SESSION['nama']; ?> (Guru)
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
                        <li><a href="input_nilai.php"><i class="fas fa-edit"></i> Input Nilai</a></li>
                        <li><a href="input_absensi.php"><i class="fas fa-calendar-check"></i> Input Absensi</a></li>
                        <li><a href="rekap_nilai.php" class="active"><i class="fas fa-chart-line"></i> Rekap Nilai</a></li>
                        <li><a href="kelola_tugas.php"><i class="fas fa-tasks"></i> Kelola Tugas</a></li>
                        <li><a href="jadwal_ujian.php"><i class="fas fa-clock"></i> Jadwal Ujian</a></li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="main-content">
                    <!-- Page Header -->
                    <div class="page-header d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-1"><i class="fas fa-chart-line text-primary"></i> Rekap Nilai</h4>
                            <p class="text-muted mb-0">Lihat dan analisis nilai siswa per semester</p>
                        </div>
                        <a href="?export=excel&kelas=<?php echo $kelas; ?>&mapel_id=<?php echo $mapel_id; ?>&semester=<?php echo $semester; ?>&tahun_ajaran=<?php echo $tahun_ajaran; ?>" class="btn-export">
                            <i class="fas fa-file-excel"></i> Export Excel
                        </a>
                    </div>

                    <!-- Info Box -->
                    <div class="info-box">
                        <i class="fas fa-info-circle text-primary me-2"></i>
                        <strong>Periode:</strong> Semester <?php echo $semester; ?> Tahun Ajaran <?php echo $tahun_ajaran; ?>
                    </div>

                    <!-- Filter Card -->
                    <div class="filter-card">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Kelas</label>
                                <select name="kelas" class="form-control">
                                    <option value="">Semua Kelas</option>
                                    <?php while ($row = mysqli_fetch_assoc($result_kelas)): ?>
                                        <option value="<?php echo $row['kelas']; ?>" <?php echo $kelas == $row['kelas'] ? 'selected' : ''; ?>>
                                            <?php echo $row['kelas']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Mata Pelajaran</label>
                                <select name="mapel_id" class="form-control">
                                    <option value="">Semua Mapel</option>
                                    <?php
                                    mysqli_data_seek($result_mapel, 0);
                                    while ($row = mysqli_fetch_assoc($result_mapel)):
                                    ?>
                                        <option value="<?php echo $row['id']; ?>" <?php echo $mapel_id == $row['id'] ? 'selected' : ''; ?>>
                                            <?php echo $row['nama_mapel']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Semester</label>
                                <select name="semester" class="form-control">
                                    <option value="Ganjil" <?php echo $semester == 'Ganjil' ? 'selected' : ''; ?>>Ganjil</option>
                                    <option value="Genap" <?php echo $semester == 'Genap' ? 'selected' : ''; ?>>Genap</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Tahun Ajaran</label>
                                <select name="tahun_ajaran" class="form-control">
                                    <option value="2023/2024" <?php echo $tahun_ajaran == '2023/2024' ? 'selected' : ''; ?>>2023/2024</option>
                                    <option value="2024/2025" <?php echo $tahun_ajaran == '2024/2025' ? 'selected' : ''; ?>>2024/2025</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-filter"></i> Tampilkan
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="label">Rata-rata Nilai</div>
                                <div class="value"><?php echo $rata_total; ?></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="label">Nilai Tertinggi</div>
                                <div class="value"><?php echo $nilai_tertinggi; ?></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="label">Nilai Terendah</div>
                                <div class="value"><?php echo $nilai_terendah; ?></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="label">Jumlah Siswa</div>
                                <div class="value"><?php echo $jml_siswa; ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Grafik Row -->
                    <div class="row mb-4">
                        <!-- Grafik Rata-rata per Kelas -->
                        <div class="col-md-7">
                            <div class="card">
                                <div class="card-header">
                                    <i class="fas fa-chart-bar"></i> Rata-rata Nilai per Kelas
                                </div>
                                <div class="card-body">
                                    <div class="grafik-container">
                                        <canvas id="chartKelas"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Grafik Distribusi Nilai -->
                        <div class="col-md-5">
                            <div class="card">
                                <div class="card-header">
                                    <i class="fas fa-chart-pie"></i> Distribusi Nilai
                                </div>
                                <div class="card-body">
                                    <div class="grafik-container">
                                        <canvas id="chartDistribusi"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabel Rekap Nilai -->
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-table"></i> Rekap Nilai Siswa
                            <span class="badge bg-primary ms-2">Semester <?php echo $semester; ?> <?php echo $tahun_ajaran; ?></span>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>Nama Siswa</th>
                                            <th>Kelas</th>
                                            <th>Mata Pelajaran</th>
                                            <th>UTS</th>
                                            <th>UAS</th>
                                            <th>Rata-rata</th>
                                            <th>Predikat</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        if (mysqli_num_rows($result_rekap) > 0):
                                            $no = 1;
                                            while ($row = mysqli_fetch_assoc($result_rekap)):
                                                $rata = $row['rata_rata'] ? round($row['rata_rata'], 2) : 0;
                                                if ($rata >= 90) {
                                                    $predikat = 'A';
                                                    $badge = 'badge-a';
                                                } elseif ($rata >= 80) {
                                                    $predikat = 'B';
                                                    $badge = 'badge-b';
                                                } elseif ($rata >= 70) {
                                                    $predikat = 'C';
                                                    $badge = 'badge-c';
                                                } else {
                                                    $predikat = 'D';
                                                    $badge = 'badge-d';
                                                }
                                        ?>
                                                <tr>
                                                    <td><?php echo $no++; ?></td>
                                                    <td><?php echo $row['nama_lengkap']; ?></td>
                                                    <td><?php echo $row['kelas']; ?></td>
                                                    <td><?php echo $row['nama_mapel']; ?></td>
                                                    <td class="text-center"><?php echo $row['nilai_uts'] ? round($row['nilai_uts'], 2) : '-'; ?></td>
                                                    <td class="text-center"><?php echo $row['nilai_uas'] ? round($row['nilai_uas'], 2) : '-'; ?></td>
                                                    <td class="text-center"><strong><?php echo $rata ?: '-'; ?></strong></td>
                                                    <td class="text-center">
                                                        <?php if ($rata > 0): ?>
                                                            <span class="badge <?php echo $badge; ?>"><?php echo $predikat; ?></span>
                                                        <?php else: ?>
                                                            -
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="8" class="text-center py-5">
                                                    <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                                                    <h5 class="text-muted">Belum ada data nilai</h5>
                                                    <p class="text-muted">Silakan pilih filter lain atau input nilai terlebih dahulu</p>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Grafik Rata-rata per Kelas
        <?php if (!empty($kelas_labels)): ?>
            new Chart(document.getElementById('chartKelas'), {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($kelas_labels); ?>,
                    datasets: [{
                        label: 'Rata-rata Nilai',
                        data: <?php echo json_encode($nilai_per_kelas); ?>,
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
        <?php endif; ?>

        // Grafik Distribusi Nilai
        <?php if (!empty($kategori_labels)): ?>
            new Chart(document.getElementById('chartDistribusi'), {
                type: 'doughnut',
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
                    },
                    cutout: '60%'
                }
            });
        <?php endif; ?>
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>