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
// FILTER - CASTING INTEGER
// =====================================================
$bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : (int)date('m');
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');

// Validasi bulan
if ($bulan < 1 || $bulan > 12) {
    $bulan = (int)date('m');
}

// =====================================================
// NAMA BULAN INDONESIA
// =====================================================
$nama_bulan = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

// =====================================================
// AMBIL DATA JADWAL UJIAN
// =====================================================
$query_jadwal = "SELECT DISTINCT
                    j.id,
                    j.tanggal_ujian,
                    j.waktu_mulai,
                    j.waktu_selesai,
                    j.ruangan,
                    mp.nama_mapel,
                    DAYNAME(j.tanggal_ujian) as hari_inggris,
                    DATEDIFF(j.tanggal_ujian, CURDATE()) as sisa_hari
                FROM jadwal_ujian j
                INNER JOIN mata_pelajaran mp ON j.mapel_id = mp.id
                WHERE j.tanggal_ujian >= CURDATE()
                GROUP BY j.id, j.tanggal_ujian, j.waktu_mulai, j.waktu_selesai, j.ruangan, mp.nama_mapel
                ORDER BY j.tanggal_ujian ASC, j.waktu_mulai ASC";
$result_jadwal = mysqli_query($conn, $query_jadwal);

if (!$result_jadwal) {
    die("Error query: " . mysqli_error($conn));
}

$jumlah_jadwal = mysqli_num_rows($result_jadwal);

// =====================================================
// DATA UNTUK KALENDER
// =====================================================
$query_kalender = "SELECT 
                    DAY(j.tanggal_ujian) as tgl,
                    COUNT(DISTINCT j.id) as jumlah,
                    GROUP_CONCAT(DISTINCT mp.nama_mapel SEPARATOR ', ') as mapel
                   FROM jadwal_ujian j 
                   INNER JOIN mata_pelajaran mp ON j.mapel_id = mp.id 
                   WHERE MONTH(j.tanggal_ujian) = $bulan 
                   AND YEAR(j.tanggal_ujian) = $tahun
                   GROUP BY DAY(j.tanggal_ujian)";
$result_kalender = mysqli_query($conn, $query_kalender);

if (!$result_kalender) {
    die("Error query kalender: " . mysqli_error($conn));
}

$data_kalender = [];
while($row = mysqli_fetch_assoc($result_kalender)) {
    $data_kalender[$row['tgl']] = $row;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jadwal Ujian - SIAJAR Sekolah</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f4f7fc;
            overflow-x: hidden;
        }
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 1rem 2rem;
        }
        .navbar-brand, .nav-link { color: white !important; }
        .sidebar {
            background: white;
            min-height: calc(100vh - 76px);
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            padding: 20px;
        }
        .sidebar-menu { list-style: none; padding: 0; }
        .sidebar-menu li { margin-bottom: 5px; }
        .sidebar-menu a {
            display: block;
            padding: 12px 15px;
            color: #333;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s;
        }
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .sidebar-menu i { margin-right: 10px; }
        .main-content { padding: 30px; }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        .card-header {
            background: white;
            border-bottom: 2px solid #f0f0f0;
            padding: 20px;
            font-weight: 600;
            border-radius: 15px 15px 0 0 !important;
        }
        .card-header i { color: #667eea; margin-right: 10px; }
        
        .filter-card {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        
        /* ===== KALENDER GRID ===== */
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
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .calendar-day.has-jadwal {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .calendar-day .tanggal { font-size: 1.2rem; font-weight: 600; }
        .calendar-day .count { font-size: 0.7rem; opacity: 0.9; }
        
        /* ===== PENANDA HARI INI ===== */
        .calendar-day.today {
            border: 3px solid #667eea !important;
            font-weight: bold;
            position: relative;
            background: #e8f0fe !important;
        }
        .calendar-day.today .tanggal {
            color: #667eea;
            font-weight: 700;
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
            text-align: center;
        }
        .calendar-day.has-jadwal.today {
            background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%) !important;
            color: white;
            border-color: #ffc107 !important;
        }
        .calendar-day.has-jadwal.today .tanggal {
            color: white;
        }
        .calendar-day.has-jadwal.today .today-label {
            background: #ff9800;
        }
        
        /* Badge untuk legend */
        .badge.has-jadwal {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 5px 10px;
        }
        .badge-today {
            background: #dc3545;
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 500;
            margin-left: 5px;
        }
        
        .jadwal-item {
            padding: 15px;
            transition: all 0.3s;
            border-bottom: 1px solid #f0f0f0;
        }
        .jadwal-item:hover {
            background: #f8f9fa;
        }
        .jadwal-item:last-child {
            border-bottom: none;
        }
        .jadwal-item.today-item {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
        }
        
        .badge {
            padding: 5px 10px;
            border-radius: 5px;
            font-weight: 500;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.4);
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
                        <li><a href="absensi.php"><i class="fas fa-calendar-check"></i> Absensi</a></li>
                        <li><a href="jadwal.php" class="active"><i class="fas fa-clock"></i> Jadwal Ujian</a></li>
                        <li><a href="rapor.php"><i class="fas fa-file-alt"></i> Rapor</a></li>
                    </ul>
                </div>
            </div>

            <div class="col-md-9 col-lg-10">
                <div class="main-content">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="mb-0"><i class="fas fa-calendar-alt text-primary me-2"></i> Jadwal Ujian</h4>
                        <span class="badge bg-primary"><?php echo $jumlah_jadwal; ?> Jadwal Mendatang</span>
                    </div>

                    <div class="filter-card">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Bulan</label>
                                <select name="bulan" class="form-control">
                                    <?php for($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo $bulan == $i ? 'selected' : ''; ?>>
                                        <?php echo $nama_bulan[$i]; ?>
                                    </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Tahun</label>
                                <select name="tahun" class="form-control">
                                    <?php for($i = date('Y'); $i <= date('Y')+2; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo $tahun == $i ? 'selected' : ''; ?>>
                                        <?php echo $i; ?>
                                    </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-filter"></i> Tampilkan
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- KALENDER DENGAN PENANDA HARI INI -->
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-calendar"></i> Kalender Ujian - <?php echo $nama_bulan[$bulan]; ?> <?php echo $tahun; ?>
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
                                
                                for($i = 0; $i < $mulai_hari; $i++) {
                                    echo '<div class="calendar-day" style="background: transparent; border: none;"></div>';
                                }
                                
                                for($tanggal = 1; $tanggal <= $hari_dalam_bulan; $tanggal++) {
                                    $has_jadwal = isset($data_kalender[$tanggal]);
                                    $jadwal_info = $has_jadwal ? $data_kalender[$tanggal] : null;
                                    $is_today = ($tanggal == $hari_ini && $bulan == $bulan_ini && $tahun == $tahun_ini);
                                    $today_class = $is_today ? 'today' : '';
                                    
                                    echo '<div class="calendar-day ' . ($has_jadwal ? 'has-jadwal' : '') . ' ' . $today_class . '">';
                                    echo '<div class="tanggal">' . $tanggal . '</div>';
                                    if($has_jadwal) {
                                        echo '<div class="count">' . $jadwal_info['jumlah'] . ' ujian</div>';
                                    }
                                    if($is_today) {
                                        echo '<div class="today-label">Hari Ini</div>';
                                    }
                                    echo '</div>';
                                }
                                ?>
                            </div>
                            
                            <!-- LEGEND -->
                            <div class="mt-3 text-center">
                                <span class="badge bg-primary me-2">Hari Ini</span>
                                <span class="badge has-jadwal me-2">Ada Jadwal</span>
                            </div>
                        </div>
                    </div>

                    <!-- DAFTAR JADWAL DENGAN PENANDA HARI INI -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <i class="fas fa-list"></i> Daftar Jadwal Ujian Mendatang
                        </div>
                        <div class="card-body p-0">
                            <?php if($jumlah_jadwal > 0): ?>
                                <?php 
                                $last_date = '';
                                while($row = mysqli_fetch_assoc($result_jadwal)): 
                                    $tanggal = strtotime($row['tanggal_ujian']);
                                    $hari = getHariIndonesia($row['hari_inggris']);
                                    $tanggal_str = date('Y-m-d', $tanggal);
                                    $is_today = ($tanggal_str == date('Y-m-d'));
                                    $today_class = $is_today ? 'today-item' : '';
                                    
                                    if($last_date != $tanggal_str):
                                        if($last_date != ''):
                                            echo '<hr class="my-2">';
                                        endif;
                                        $last_date = $tanggal_str;
                                    endif;
                                ?>
                                <div class="jadwal-item <?php echo $today_class; ?>">
                                    <div class="row align-items-center">
                                        <div class="col-md-2">
                                            <div class="fw-bold text-primary"><?php echo $hari; ?></div>
                                            <div><?php echo date('d/m/Y', $tanggal); ?>
                                                <?php if($is_today): ?>
                                                    <span class="badge-today">Hari Ini</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <h6 class="mb-0 fw-bold <?php echo $is_today ? 'text-danger' : ''; ?>">
                                                <?php echo $row['nama_mapel']; ?>
                                            </h6>
                                        </div>
                                        <div class="col-md-3">
                                            <i class="far fa-clock text-muted me-1"></i>
                                            <?php echo substr($row['waktu_mulai'], 0, 5); ?> - <?php echo substr($row['waktu_selesai'], 0, 5); ?>
                                        </div>
                                        <div class="col-md-2">
                                            <i class="fas fa-door-open text-muted me-1"></i>
                                            <?php echo $row['ruangan']; ?>
                                        </div>
                                        <div class="col-md-2 text-end">
                                            <?php if($row['sisa_hari'] > 0): ?>
                                                <span class="badge bg-<?php echo $row['sisa_hari'] <= 3 ? 'warning' : 'primary'; ?>">
                                                    <?php echo $row['sisa_hari']; ?> hari
                                                </span>
                                            <?php elseif($row['sisa_hari'] == 0): ?>
                                                <span class="badge bg-danger">Hari ini</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-calendar-times fa-4x text-muted mb-3"></i>
                                    <h5 class="text-muted">Tidak ada jadwal ujian</h5>
                                    <p class="text-muted">Belum ada jadwal ujian untuk periode ini</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>