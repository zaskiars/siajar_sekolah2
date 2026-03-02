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

// Filter
$semester = isset($_GET['semester']) ? $_GET['semester'] : '';
$tahun_ajaran = isset($_GET['tahun_ajaran']) ? $_GET['tahun_ajaran'] : '';

// Query nilai
$query = "SELECT mp.nama_mapel, n.tugas, n.nilai, n.semester, n.tahun_ajaran, n.created_at 
          FROM nilai n 
          JOIN mata_pelajaran mp ON n.mapel_id = mp.id 
          WHERE n.siswa_id = $user_id";

if ($semester) {
    $query .= " AND n.semester = '$semester'";
}
if ($tahun_ajaran) {
    $query .= " AND n.tahun_ajaran = '$tahun_ajaran'";
}

$query .= " ORDER BY n.created_at DESC";
$result = mysqli_query($conn, $query);

// Statistik
$query_stat = "SELECT 
                AVG(nilai) as rata_rata,
                MAX(nilai) as nilai_tertinggi,
                MIN(nilai) as nilai_terendah,
                COUNT(*) as total_tugas
               FROM nilai 
               WHERE siswa_id = $user_id";
$result_stat = mysqli_query($conn, $query_stat);
$stat = mysqli_fetch_assoc($result_stat);

$rata_rata = $stat['rata_rata'] ? round($stat['rata_rata'], 2) : 0;
$nilai_tertinggi = $stat['nilai_tertinggi'] ?: 0;
$nilai_terendah = $stat['nilai_terendah'] ?: 0;
$total_tugas = $stat['total_tugas'] ?: 0;

// Data untuk grafik
$query_chart = "SELECT mp.nama_mapel, AVG(n.nilai) as rata_rata 
                FROM nilai n 
                JOIN mata_pelajaran mp ON n.mapel_id = mp.id 
                WHERE n.siswa_id = $user_id 
                GROUP BY mp.id, mp.nama_mapel";
$result_chart = mysqli_query($conn, $query_chart);

$mapel_labels = [];
$nilai_data = [];
while ($row = mysqli_fetch_assoc($result_chart)) {
    $mapel_labels[] = $row['nama_mapel'];
    $nilai_data[] = round($row['rata_rata'], 2);
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nilai & Tugas - SIAJAR Sekolah</title>
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
            font-family: 'Segoe UI', sans-serif;
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

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
            transition: all 0.3s;
            text-align: center;
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

        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
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

        .badge-excellent {
            background: #28a745;
            color: white;
        }

        .badge-good {
            background: #17a2b8;
            color: white;
        }

        .badge-enough {
            background: #ffc107;
            color: #333;
        }

        .badge-poor {
            background: #dc3545;
            color: white;
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg">
        <div class="container-fluid">
            <a class="navbar-brand" href="#"><i class="fas fa-school"></i> SIAJAR Sekolah</a>
            <div class="navbar-nav ms-auto">
                <span class="nav-link"><i class="fas fa-user-circle"></i> <?php echo $_SESSION['nama']; ?></span>
                <a class="nav-link" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Keluar</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <div class="col-md-3 col-lg-2 px-0">
                <div class="sidebar">
                    <ul class="sidebar-menu">
                        <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                        <li><a href="nilai.php" class="active"><i class="fas fa-book"></i> Nilai & Tugas</a></li>
                        <li><a href="absensi.php"><i class="fas fa-calendar-check"></i> Absensi</a></li>
                        <li><a href="jadwal.php"><i class="fas fa-clock"></i> Jadwal Ujian</a></li>
                        <li><a href="rapor.php"><i class="fas fa-file-alt"></i> Rapor</a></li>
                    </ul>
                </div>
            </div>

            <div class="col-md-9 col-lg-10">
                <div class="main-content">
                    <!-- Statistics -->
                    <div class="row">
                        <div class="col-md-3">
                            <div class="stat-card">
                                <i class="fas fa-calculator"></i>
                                <h3><?php echo $rata_rata; ?></h3>
                                <p class="text-muted">Rata-rata Nilai</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <i class="fas fa-trophy"></i>
                                <h3><?php echo $nilai_tertinggi; ?></h3>
                                <p class="text-muted">Nilai Tertinggi</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <i class="fas fa-exclamation-triangle"></i>
                                <h3><?php echo $nilai_terendah; ?></h3>
                                <p class="text-muted">Nilai Terendah</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <i class="fas fa-tasks"></i>
                                <h3><?php echo $total_tugas; ?></h3>
                                <p class="text-muted">Total Tugas</p>
                            </div>
                        </div>
                    </div>

                    <!-- Filter -->
                    <div class="filter-section">
                        <form method="GET" class="row">
                            <div class="col-md-4">
                                <label class="form-label">Semester</label>
                                <select name="semester" class="form-control">
                                    <option value="">Semua Semester</option>
                                    <option value="Ganjil" <?php echo $semester == 'Ganjil' ? 'selected' : ''; ?>>Ganjil
                                    </option>
                                    <option value="Genap" <?php echo $semester == 'Genap' ? 'selected' : ''; ?>>Genap
                                    </option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Tahun Ajaran</label>
                                <select name="tahun_ajaran" class="form-control">
                                    <option value="">Semua Tahun</option>
                                    <option value="2023/2024" <?php echo $tahun_ajaran == '2023/2024' ? 'selected' : ''; ?>>2023/2024</option>
                                    <option value="2024/2025" <?php echo $tahun_ajaran == '2024/2025' ? 'selected' : ''; ?>>2024/2025</option>
                                </select>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-filter"></i> Filter
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Grafik -->
                    <?php if (!empty($mapel_labels)): ?>
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-chart-bar"></i> Grafik Nilai per Mata Pelajaran
                            </div>
                            <div class="card-body">
                                <canvas id="chartNilaiMapel" style="height: 300px;"></canvas>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Tabel Nilai -->
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-table"></i> Daftar Nilai dan Tugas
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>Mata Pelajaran</th>
                                            <th>Jenis Tugas</th>
                                            <th>Nilai</th>
                                            <th>Predikat</th>
                                            <th>Semester</th>
                                            <th>Tahun Ajaran</th>
                                            <th>Tanggal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (mysqli_num_rows($result) > 0): ?>
                                            <?php
                                            $no = 1;
                                            while ($row = mysqli_fetch_assoc($result)):
                                                $nilai = $row['nilai'];
                                                if ($nilai >= 90) {
                                                    $predikat = 'A (Sangat Baik)';
                                                    $badge_class = 'badge-excellent';
                                                } elseif ($nilai >= 80) {
                                                    $predikat = 'B (Baik)';
                                                    $badge_class = 'badge-good';
                                                } elseif ($nilai >= 70) {
                                                    $predikat = 'C (Cukup)';
                                                    $badge_class = 'badge-enough';
                                                } else {
                                                    $predikat = 'D (Kurang)';
                                                    $badge_class = 'badge-poor';
                                                }
                                            ?>
                                                <tr>
                                                    <td><?php echo $no++; ?></td>
                                                    <td><strong><?php echo $row['nama_mapel']; ?></strong></td>
                                                    <td><?php echo $row['tugas']; ?></td>
                                                    <td>
                                                        <span class="badge <?php echo $badge_class; ?>">
                                                            <?php echo $nilai; ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo $predikat; ?></td>
                                                    <td><?php echo $row['semester']; ?></td>
                                                    <td><?php echo $row['tahun_ajaran']; ?></td>
                                                    <td><?php echo date('d/m/Y', strtotime($row['created_at'])); ?></td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="8" class="text-center py-4">
                                                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                                    <p class="text-muted">Belum ada data nilai</p>
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

    <?php if (!empty($mapel_labels)): ?>
        <script>
            new Chart(document.getElementById('chartNilaiMapel'), {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($mapel_labels); ?>,
                    datasets: [{
                        label: 'Rata-rata Nilai',
                        data: <?php echo json_encode($nilai_data); ?>,
                        backgroundColor: 'rgba(102, 126, 234, 0.7)',
                        borderColor: '#667eea',
                        borderWidth: 1
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
                    }
                }
            });
        </script>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>