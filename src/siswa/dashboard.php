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
$nama = $_SESSION['nama'];
$kelas = $_SESSION['kelas'];

// Rata-rata nilai
$query_rata = "SELECT AVG(nilai) as rata_rata FROM nilai WHERE siswa_id = $user_id";
$result_rata = mysqli_query($conn, $query_rata);
$rata_nilai = round(mysqli_fetch_assoc($result_rata)['rata_rata'] ?: 0, 2);

// Nilai per mapel untuk grafik
$query_nilai_mapel = "SELECT mp.nama_mapel, AVG(n.nilai) as rata_rata
                      FROM nilai n 
                      JOIN mata_pelajaran mp ON n.mapel_id = mp.id
                      WHERE n.siswa_id = $user_id
                      GROUP BY mp.id, mp.nama_mapel 
                      ORDER BY rata_rata DESC 
                      LIMIT 5";
$result_nilai_mapel = mysqli_query($conn, $query_nilai_mapel);
$mapel_labels = [];
$nilai_data = [];
while ($row = mysqli_fetch_assoc($result_nilai_mapel)) {
    $mapel_labels[] = $row['nama_mapel'];
    $nilai_data[] = round($row['rata_rata'], 2);
}

// Nilai terbaru
$query_nilai_terbaru = "SELECT mp.nama_mapel, n.tugas, n.nilai, DATE_FORMAT(n.created_at, '%d/%m/%Y') as tanggal
                        FROM nilai n 
                        JOIN mata_pelajaran mp ON n.mapel_id = mp.id
                        WHERE n.siswa_id = $user_id 
                        ORDER BY n.created_at DESC 
                        LIMIT 5";
$result_nilai_terbaru = mysqli_query($conn, $query_nilai_terbaru);

// Absensi bulan ini
$bulan_ini = date('m');
$tahun_ini = date('Y');
$query_absensi = "SELECT status, COUNT(*) as jumlah 
                  FROM absensi 
                  WHERE siswa_id = $user_id 
                  AND MONTH(tanggal) = $bulan_ini 
                  AND YEAR(tanggal) = $tahun_ini
                  GROUP BY status";
$result_absensi = mysqli_query($conn, $query_absensi);
$absensi = ['hadir' => 0, 'sakit' => 0, 'izin' => 0, 'alpha' => 0];
while ($row = mysqli_fetch_assoc($result_absensi)) {
    $absensi[$row['status']] = $row['jumlah'];
}
$total_hari = array_sum($absensi);
$persen_hadir = $total_hari > 0 ? round(($absensi['hadir'] / $total_hari) * 100, 2) : 0;

// Jadwal ujian terdekat
$query_jadwal = "SELECT j.*, mp.nama_mapel, DAYNAME(j.tanggal_ujian) as hari_inggris,
                        DATEDIFF(j.tanggal_ujian, CURDATE()) as sisa_hari
                 FROM jadwal_ujian j 
                 JOIN mata_pelajaran mp ON j.mapel_id = mp.id
                 WHERE j.tanggal_ujian >= CURDATE() 
                 ORDER BY j.tanggal_ujian ASC 
                 LIMIT 3";
$result_jadwal = mysqli_query($conn, $query_jadwal);

// Tugas mendatang
$query_tugas = "SELECT t.*, mp.nama_mapel, DATEDIFF(t.tanggal_deadline, CURDATE()) as sisa_hari,
                       pt.status as status_pengumpulan
                FROM tugas t 
                JOIN mata_pelajaran mp ON t.mapel_id = mp.id
                LEFT JOIN pengumpulan_tugas pt ON t.id = pt.tugas_id AND pt.siswa_id = $user_id
                WHERE t.tanggal_deadline >= CURDATE() 
                ORDER BY t.tanggal_deadline ASC 
                LIMIT 3";
$result_tugas = mysqli_query($conn, $query_tugas);

// Nilai tertinggi/terendah
$query_tertinggi = "SELECT MAX(nilai) as tertinggi FROM nilai WHERE siswa_id = $user_id";
$result_tertinggi = mysqli_query($conn, $query_tertinggi);
$nilai_tertinggi = mysqli_fetch_assoc($result_tertinggi)['tertinggi'] ?: 0;
$query_terendah = "SELECT MIN(nilai) as terendah FROM nilai WHERE siswa_id = $user_id";
$result_terendah = mysqli_query($conn, $query_terendah);
$nilai_terendah = mysqli_fetch_assoc($result_terendah)['terendah'] ?: 0;

function getStatusBadge($status)
{
    $badge = [
        'hadir' => 'success',
        'sakit' => 'warning',
        'izin' => 'info',
        'alpha' => 'danger',
        'belum' => 'secondary',
        'sudah' => 'success',
        'dinilai' => 'primary'
    ];
    return $badge[$status] ?? 'secondary';
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Siswa - SIAJAR Sekolah</title>
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
            content: '👨‍🎓';
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
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
            height: 100%;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .stat-card .icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
        }

        .stat-card .icon i {
            font-size: 1.8rem;
        }

        .stat-card .label {
            color: #666;
            font-size: 0.9rem;
        }

        .stat-card .value {
            font-size: 2rem;
            font-weight: 700;
            color: #333;
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

        .list-item {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            transition: all 0.3s;
        }

        .list-item:hover {
            background: #f8f9fa;
        }

        .list-item .title {
            font-weight: 600;
            color: #333;
        }

        .badge {
            padding: 5px 10px;
            border-radius: 5px;
            font-weight: 500;
        }

        .progress {
            height: 8px;
            border-radius: 4px;
        }

        .quick-menu {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }

        .quick-item {
            background: white;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s;
            text-decoration: none;
            color: inherit;
        }

        .quick-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .quick-item i {
            font-size: 2rem;
            color: #667eea;
            margin-bottom: 10px;
        }

        .quick-item:hover i {
            color: white;
        }

        .grafik-container {
            height: 300px;
            position: relative;
        }

        .badge-today {
            background: #dc3545;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
        }

        .today-item {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg">
        <div class="container-fluid">
            <a class="navbar-brand" href="#"><i class="fas fa-school"></i> SIAJAR Sekolah</a>
            <div class="navbar-nav ms-auto">
                <span class="nav-link"><i class="fas fa-user-circle"></i> <?php echo $nama; ?></span>
                <span class="nav-link"><i class="fas fa-users"></i> <?php echo $kelas; ?></span>
                <a class="nav-link" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Keluar</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <div class="col-md-3 col-lg-2 px-0">
                <div class="sidebar">
                    <ul class="sidebar-menu">
                        <li><a href="dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a></li>
                        <li><a href="nilai.php"><i class="fas fa-book"></i> Nilai & Tugas</a></li>
                        <li><a href="absensi.php"><i class="fas fa-calendar-check"></i> Absensi</a></li>
                        <li><a href="jadwal.php"><i class="fas fa-clock"></i> Jadwal Ujian</a></li>
                        <li><a href="rapor.php"><i class="fas fa-file-alt"></i> Rapor</a></li>
                    </ul>
                </div>
            </div>

            <div class="col-md-9 col-lg-10">
                <div class="main-content">
                    <div class="welcome-card">
                        <h1>Selamat Datang, <?php echo $nama; ?>! 👋</h1>
                        <p>Semangat belajar dan raih prestasi terbaikmu hari ini.</p>
                        <div class="info">
                            <div class="info-item"><i class="fas fa-user-graduate"></i> Kelas: <?php echo $kelas; ?>
                            </div>
                            <div class="info-item"><i class="fas fa-calendar"></i> <?php echo date('d F Y'); ?></div>
                        </div>
                    </div>

                    <div class="quick-menu">
                        <a href="nilai.php" class="quick-item"><i class="fas fa-book"></i><span>Nilai</span></a>
                        <a href="absensi.php" class="quick-item"><i class="fas fa-calendar-check"></i><span>Absensi</span></a>
                        <a href="jadwal.php" class="quick-item"><i class="fas fa-clock"></i><span>Jadwal</span></a>
                        <a href="rapor.php" class="quick-item"><i class="fas fa-file-alt"></i><span>Rapor</span></a>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="icon" style="background:#e3f2fd;">
                                    <i class="fas fa-star" style="color:#1976d2;"></i>
                                </div>
                                <div class="label">Rata-rata Nilai</div>
                                <div class="value"><?php echo $rata_nilai; ?></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="icon" style="background:#e8f5e9;">
                                    <i class="fas fa-trophy" style="color:#388e3c;"></i>
                                </div>
                                <div class="label">Nilai Tertinggi</div>
                                <div class="value"><?php echo $nilai_tertinggi; ?></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="icon" style="background:#fff3e0;">
                                    <i class="fas fa-calendar-check" style="color:#f57c00;"></i>
                                </div>
                                <div class="label">Kehadiran</div>
                                <div class="value"><?php echo $persen_hadir; ?><span class="unit">%</span></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="icon" style="background:#fce4ec;">
                                    <i class="fas fa-tasks" style="color:#c2185b;"></i>
                                </div>
                                <div class="label">Total Tugas</div>
                                <div class="value"><?php echo mysqli_num_rows($result_tugas); ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-7">
                            <div class="card">
                                <div class="card-header"><i class="fas fa-chart-line"></i> Grafik Perkembangan Nilai
                                </div>
                                <div class="card-body">
                                    <div class="grafik-container">
                                        <canvas id="chartNilai"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="card">
                                <div class="card-header"><i class="fas fa-chart-pie"></i> Absensi Bulan Ini</div>
                                <div class="card-body">
                                    <div class="grafik-container" style="height:200px;">
                                        <canvas id="chartAbsensi"></canvas>
                                    </div>
                                    <div class="mt-3">
                                        <div class="d-flex justify-content-between">
                                            <span><i class="fas fa-circle text-success"></i> Hadir</span>
                                            <span><?php echo $absensi['hadir']; ?> hari (<?php echo $persen_hadir; ?>
                                                %)</span>
                                        </div>
                                        <div class="progress">
                                            <div class="progress-bar bg-success"
                                                style="width: <?php echo $persen_hadir; ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-4">
                        <div class="col-md-4">
                            <div class="card h-100">
                                <div class="card-header d-flex justify-content-between">
                                    <span><i class="fas fa-calendar-alt"></i> Jadwal Ujian Terdekat</span>
                                    <a href="jadwal.php" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
                                </div>
                                <div class="card-body p-0">
                                    <?php if (mysqli_num_rows($result_jadwal) > 0): ?>
                                        <?php while ($j = mysqli_fetch_assoc($result_jadwal)):
                                            $is_today = ($j['sisa_hari'] == 0);
                                        ?>
                                            <div class="list-item <?php echo $is_today ? 'today-item' : ''; ?>">
                                                <div class="d-flex justify-content-between">
                                                    <div>
                                                        <h6 class="mb-1"><?php echo $j['nama_mapel']; ?></h6>
                                                        <small><?php echo date('d/m/Y', strtotime($j['tanggal_ujian'])); ?></small>
                                                    </div>
                                                    <?php if ($is_today): ?>
                                                        <span class="badge-today">Hari Ini</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-primary">H-<?php echo $j['sisa_hari']; ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <p class="text-center py-3">Tidak ada jadwal</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card h-100">
                                <div class="card-header d-flex justify-content-between">
                                    <span><i class="fas fa-tasks"></i> Tugas Mendatang</span>
                                    <a href="nilai.php" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
                                </div>
                                <div class="card-body p-0">
                                    <?php if (mysqli_num_rows($result_tugas) > 0): ?>
                                        <?php while ($t = mysqli_fetch_assoc($result_tugas)): ?>
                                            <div class="list-item">
                                                <div>
                                                    <h6 class="mb-1"><?php echo $t['judul']; ?></h6>
                                                    <small><?php echo $t['nama_mapel']; ?> -
                                                        <?php echo date('d/m/Y', strtotime($t['tanggal_deadline'])); ?></small>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <p class="text-center py-3">Tidak ada tugas</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card h-100">
                                <div class="card-header d-flex justify-content-between">
                                    <span><i class="fas fa-history"></i> Nilai Terbaru</span>
                                    <a href="nilai.php" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
                                </div>
                                <div class="card-body p-0">
                                    <?php if (mysqli_num_rows($result_nilai_terbaru) > 0): ?>
                                        <?php while ($n = mysqli_fetch_assoc($result_nilai_terbaru)): ?>
                                            <div class="list-item d-flex justify-content-between">
                                                <div>
                                                    <h6 class="mb-1"><?php echo $n['nama_mapel']; ?></h6>
                                                    <small><?php echo $n['tugas']; ?></small>
                                                </div>
                                                <span class="badge bg-<?php echo $n['nilai'] >= 90 ? 'success' : ($n['nilai'] >= 80 ? 'info' : ($n['nilai'] >= 70 ? 'warning' : 'danger')); ?>">
                                                    <?php echo $n['nilai']; ?>
                                                </span>
                                            </div>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <p class="text-center py-3">Belum ada nilai</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        new Chart(document.getElementById('chartNilai'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($mapel_labels); ?>,
                datasets: [{
                    label: 'Rata-rata Nilai',
                    data: <?php echo json_encode($nilai_data); ?>,
                    backgroundColor: 'rgba(102,126,234,0.7)'
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

        new Chart(document.getElementById('chartAbsensi'), {
            type: 'doughnut',
            data: {
                labels: ['Hadir', 'Sakit', 'Izin', 'Alpha'],
                datasets: [{
                    data: [<?php echo $absensi['hadir']; ?>, <?php echo $absensi['sakit']; ?>, <?php echo $absensi['izin']; ?>, <?php echo $absensi['alpha']; ?>],
                    backgroundColor: ['#28a745', '#ffc107', '#17a2b8', '#dc3545']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                cutout: '70%'
            }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>