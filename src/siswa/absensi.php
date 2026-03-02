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
$bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : (int)date('m');
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');

if ($bulan < 1 || $bulan > 12) {
    $bulan = (int)date('m');
}

// Nama bulan Indonesia
$nama_bulan = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

// Ambil data absensi
$query = "SELECT * FROM absensi 
          WHERE siswa_id = $user_id 
          AND MONTH(tanggal) = $bulan 
          AND YEAR(tanggal) = $tahun 
          ORDER BY tanggal DESC";
$result = mysqli_query($conn, $query);

// Statistik
$stat = ['hadir' => 0, 'sakit' => 0, 'izin' => 0, 'alpha' => 0];
$total_hari = 0;
while ($row = mysqli_fetch_assoc($result)) {
    $stat[$row['status']]++;
    $total_hari++;
}
mysqli_data_seek($result, 0);

$persen_hadir = $total_hari > 0 ? round(($stat['hadir'] / $total_hari) * 100, 2) : 0;

// Data untuk kalender
$query_kalender = "SELECT DAY(tanggal) as tgl, status, keterangan 
                   FROM absensi 
                   WHERE siswa_id = $user_id 
                   AND MONTH(tanggal) = $bulan 
                   AND YEAR(tanggal) = $tahun";
$result_kalender = mysqli_query($conn, $query_kalender);

$data_kalender = [];
while ($row = mysqli_fetch_assoc($result_kalender)) {
    $data_kalender[$row['tgl']] = $row;
}

// Fungsi bantu
function getStatusBadge($status)
{
    $badge = [
        'hadir' => 'success',
        'sakit' => 'warning',
        'izin' => 'info',
        'alpha' => 'danger'
    ];
    return $badge[$status] ?? 'secondary';
}

function getStatusIcon($status)
{
    $icon = [
        'hadir' => 'fa-check-circle',
        'sakit' => 'fa-hospital',
        'izin' => 'fa-envelope',
        'alpha' => 'fa-times-circle'
    ];
    return $icon[$status] ?? 'fa-circle';
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Absensi - SIAJAR Sekolah</title>
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
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            text-align: center;
            transition: all 0.3s;
            height: 100%;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .stat-card i {
            font-size: 2rem;
            margin-bottom: 10px;
        }

        .stat-card.hadir i {
            color: #28a745;
        }

        .stat-card.sakit i {
            color: #ffc107;
        }

        .stat-card.izin i {
            color: #17a2b8;
        }

        .stat-card.alpha i {
            color: #dc3545;
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
        }

        .card-header i {
            color: #667eea;
            margin-right: 10px;
        }

        .filter-card {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }

        .progress {
            height: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 8px;
            margin-top: 15px;
        }

        .calendar-header {
            text-align: center;
            font-weight: 600;
            color: #667eea;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .calendar-day {
            aspect-ratio: 1;
            background: #f8f9fa;
            border-radius: 10px;
            padding: 8px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid transparent;
            position: relative;
        }

        .calendar-day:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .calendar-day.hadir {
            background: #d4edda;
            color: #155724;
            border-color: #28a745;
        }

        .calendar-day.sakit {
            background: #fff3cd;
            color: #856404;
            border-color: #ffc107;
        }

        .calendar-day.izin {
            background: #d1ecf1;
            color: #0c5460;
            border-color: #17a2b8;
        }

        .calendar-day.alpha {
            background: #f8d7da;
            color: #721c24;
            border-color: #dc3545;
        }

        .calendar-day .tanggal {
            font-size: 1.2rem;
            font-weight: 600;
        }

        .calendar-day .keterangan {
            font-size: 0.7rem;
            text-align: center;
        }

        .calendar-day.today {
            border: 3px solid #667eea !important;
            background: #e8f0fe !important;
        }

        .calendar-day.today .tanggal {
            color: #667eea;
        }

        .calendar-day.today .today-label {
            position: absolute;
            bottom: 2px;
            left: 0;
            right: 0;
            font-size: 0.6rem;
            background: #667eea;
            color: white;
            padding: 2px;
            border-radius: 0 0 8px 8px;
        }

        .calendar-legend {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.9rem;
        }

        .legend-color {
            width: 16px;
            height: 16px;
            border-radius: 4px;
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
                        <li><a href="nilai.php"><i class="fas fa-book"></i> Nilai & Tugas</a></li>
                        <li><a href="absensi.php" class="active"><i class="fas fa-calendar-check"></i> Absensi</a>
                        </li>
                        <li><a href="jadwal.php"><i class="fas fa-clock"></i> Jadwal Ujian</a></li>
                        <li><a href="rapor.php"><i class="fas fa-file-alt"></i> Rapor</a></li>
                    </ul>
                </div>
            </div>

            <div class="col-md-9 col-lg-10">
                <div class="main-content">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="mb-0"><i class="fas fa-calendar-check text-primary me-2"></i> Absensi Siswa</h4>
                        <span class="badge bg-primary"><?php echo $nama_bulan[$bulan]; ?> <?php echo $tahun; ?></span>
                    </div>

                    <!-- Filter -->
                    <div class="filter-card">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Bulan</label>
                                <select name="bulan" class="form-control">
                                    <?php for ($i = 1; $i <= 12; $i++): ?>
                                        <option value="<?php echo $i; ?>" <?php echo $bulan == $i ? 'selected' : ''; ?>>
                                            <?php echo $nama_bulan[$i]; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Tahun</label>
                                <select name="tahun" class="form-control">
                                    <?php for ($i = date('Y'); $i >= 2020; $i--): ?>
                                        <option value="<?php echo $i; ?>" <?php echo $tahun == $i ? 'selected' : ''; ?>>
                                            <?php echo $i; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search"></i> Tampilkan
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Statistik -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="stat-card hadir">
                                <i class="fas fa-check-circle"></i>
                                <h3><?php echo $stat['hadir']; ?></h3>
                                <p>Hadir</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card sakit">
                                <i class="fas fa-hospital"></i>
                                <h3><?php echo $stat['sakit']; ?></h3>
                                <p>Sakit</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card izin">
                                <i class="fas fa-envelope"></i>
                                <h3><?php echo $stat['izin']; ?></h3>
                                <p>Izin</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card alpha">
                                <i class="fas fa-times-circle"></i>
                                <h3><?php echo $stat['alpha']; ?></h3>
                                <p>Alpha</p>
                            </div>
                        </div>
                    </div>

                    <!-- Progress -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2">
                                <h6 class="mb-0">Tingkat Kehadiran</h6>
                                <span class="badge bg-primary"><?php echo $persen_hadir; ?>%</span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-success" style="width: <?php echo $persen_hadir; ?>%"></div>
                            </div>
                            <small class="text-muted"><?php echo $stat['hadir']; ?> hadir dari <?php echo $total_hari; ?>
                                hari</small>
                        </div>
                    </div>

                    <!-- Grafik Komposisi -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-chart-pie"></i> Komposisi Kehadiran - <?php echo $nama_bulan[$bulan]; ?>
                            <?php echo $tahun; ?>
                        </div>
                        <div class="card-body">
                            <div style="height: 250px;">
                                <canvas id="chartKehadiran"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Kalender -->
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-calendar-alt text-primary me-2"></i>
                                Kalender Kehadiran - <?php echo $nama_bulan[$bulan]; ?> <?php echo $tahun; ?>
                            </h5>
                            <div>
                                <a href="?bulan=<?php echo max(1, $bulan - 1); ?>&tahun=<?php echo $tahun; ?>"
                                    class="btn btn-sm btn-outline-primary me-1">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                                <a href="?bulan=<?php echo min(12, $bulan + 1); ?>&tahun=<?php echo $tahun; ?>"
                                    class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="calendar-grid">
                                <div class="calendar-header">Sen</div>
                                <div class="calendar-header">Sel</div>
                                <div class="calendar-header">Rab</div>
                                <div class="calendar-header">Kam</div>
                                <div class="calendar-header">Jum</div>
                                <div class="calendar-header">Sab</div>
                                <div class="calendar-header">Min</div>

                                <?php
                                $hari_pertama = strtotime("$tahun-$bulan-01");
                                $hari_dalam_bulan = date('t', $hari_pertama);
                                $mulai_hari = date('N', $hari_pertama) - 1;
                                $hari_ini = date('j');
                                $bulan_ini = date('m');
                                $tahun_ini = date('Y');

                                for ($i = 0; $i < $mulai_hari; $i++) {
                                    echo '<div class="calendar-day" style="background: transparent; border: none;"></div>';
                                }

                                for ($tanggal = 1; $tanggal <= $hari_dalam_bulan; $tanggal++) {
                                    $status = isset($data_kalender[$tanggal]) ? $data_kalender[$tanggal]['status'] : '';
                                    $is_today = ($tanggal == $hari_ini && $bulan == $bulan_ini && $tahun == $tahun_ini);
                                    $today_class = $is_today ? 'today' : '';

                                    echo '<div class="calendar-day ' . $status . ' ' . $today_class . '">';
                                    echo '<div class="tanggal">' . $tanggal . '</div>';
                                    if ($status) {
                                        echo '<div class="keterangan">' . ucfirst($status) . '</div>';
                                    }
                                    if ($is_today && !$status) {
                                        echo '<div class="today-label">Hari Ini</div>';
                                    }
                                    echo '</div>';
                                }
                                ?>
                            </div>

                            <!-- Legend -->
                            <div class="calendar-legend">
                                <div class="legend-item">
                                    <span class="legend-color" style="background: #d4edda; border: 2px solid #28a745;"></span>
                                    <span>Hadir</span>
                                </div>
                                <div class="legend-item">
                                    <span class="legend-color" style="background: #fff3cd; border: 2px solid #ffc107;"></span>
                                    <span>Sakit</span>
                                </div>
                                <div class="legend-item">
                                    <span class="legend-color" style="background: #d1ecf1; border: 2px solid #17a2b8;"></span>
                                    <span>Izin</span>
                                </div>
                                <div class="legend-item">
                                    <span class="legend-color" style="background: #f8d7da; border: 2px solid #dc3545;"></span>
                                    <span>Alpha</span>
                                </div>
                                <div class="legend-item">
                                    <span class="legend-color" style="background: #f8f9fa; border: 3px solid #667eea;"></span>
                                    <span>Hari Ini</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Riwayat -->
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-history"></i> Riwayat Absensi - <?php echo $nama_bulan[$bulan]; ?>
                            <?php echo $tahun; ?>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Tanggal</th>
                                            <th>Hari</th>
                                            <th>Status</th>
                                            <th>Keterangan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (mysqli_num_rows($result) > 0): ?>
                                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                                <tr>
                                                    <td><?php echo date('d/m/Y', strtotime($row['tanggal'])); ?></td>
                                                    <td><?php echo getHariIndonesia(date('l', strtotime($row['tanggal']))); ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php echo getStatusBadge($row['status']); ?>">
                                                            <i class="fas <?php echo getStatusIcon($row['status']); ?> me-1"></i>
                                                            <?php echo ucfirst($row['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo $row['keterangan'] ?: '-'; ?></td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="4" class="text-center py-4">
                                                    <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                                    <p class="text-muted">Tidak ada data absensi</p>
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
        new Chart(document.getElementById('chartKehadiran'), {
            type: 'doughnut',
            data: {
                labels: ['Hadir', 'Sakit', 'Izin', 'Alpha'],
                datasets: [{
                    data: [
                        <?php echo $stat['hadir']; ?>,
                        <?php echo $stat['sakit']; ?>,
                        <?php echo $stat['izin']; ?>,
                        <?php echo $stat['alpha']; ?>
                    ],
                    backgroundColor: ['#28a745', '#ffc107', '#17a2b8', '#dc3545'],
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
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>