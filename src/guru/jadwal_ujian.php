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
$message = '';
$error = '';

// =====================================================
// PROSES TAMBAH JADWAL
// =====================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'tambah') {
        $mapel_id = mysqli_real_escape_string($conn, $_POST['mapel_id']);
        $tanggal_ujian = mysqli_real_escape_string($conn, $_POST['tanggal_ujian']);
        $waktu_mulai = mysqli_real_escape_string($conn, $_POST['waktu_mulai']);
        $waktu_selesai = mysqli_real_escape_string($conn, $_POST['waktu_selesai']);
        $ruangan = mysqli_real_escape_string($conn, $_POST['ruangan']);
        $keterangan = mysqli_real_escape_string($conn, $_POST['keterangan']);
        
        // Cek bentrok ruangan
        $check = "SELECT * FROM jadwal_ujian 
                  WHERE ruangan = '$ruangan' 
                  AND tanggal_ujian = '$tanggal_ujian' 
                  AND (
                      (waktu_mulai <= '$waktu_mulai' AND waktu_selesai > '$waktu_mulai')
                      OR (waktu_mulai < '$waktu_selesai' AND waktu_selesai >= '$waktu_selesai')
                      OR (waktu_mulai >= '$waktu_mulai' AND waktu_selesai <= '$waktu_selesai')
                  )";
        $result_check = mysqli_query($conn, $check);
        
        if (mysqli_num_rows($result_check) > 0) {
            $error = "Ruangan sudah digunakan pada waktu tersebut!";
        } else {
            $query = "INSERT INTO jadwal_ujian (mapel_id, tanggal_ujian, waktu_mulai, waktu_selesai, ruangan, keterangan) 
                      VALUES ('$mapel_id', '$tanggal_ujian', '$waktu_mulai', '$waktu_selesai', '$ruangan', '$keterangan')";
            
            if (mysqli_query($conn, $query)) {
                $message = "Jadwal ujian berhasil ditambahkan! (ID: " . mysqli_insert_id($conn) . ")";
            } else {
                $error = "Gagal menambahkan jadwal: " . mysqli_error($conn);
            }
        }
    } elseif ($_POST['action'] == 'edit') {
        $id = mysqli_real_escape_string($conn, $_POST['id']);
        $mapel_id = mysqli_real_escape_string($conn, $_POST['mapel_id']);
        $tanggal_ujian = mysqli_real_escape_string($conn, $_POST['tanggal_ujian']);
        $waktu_mulai = mysqli_real_escape_string($conn, $_POST['waktu_mulai']);
        $waktu_selesai = mysqli_real_escape_string($conn, $_POST['waktu_selesai']);
        $ruangan = mysqli_real_escape_string($conn, $_POST['ruangan']);
        $keterangan = mysqli_real_escape_string($conn, $_POST['keterangan']);
        
        $query = "UPDATE jadwal_ujian SET 
                  mapel_id = '$mapel_id',
                  tanggal_ujian = '$tanggal_ujian',
                  waktu_mulai = '$waktu_mulai',
                  waktu_selesai = '$waktu_selesai',
                  ruangan = '$ruangan',
                  keterangan = '$keterangan'
                  WHERE id = $id";
        
        if (mysqli_query($conn, $query)) {
            $message = "Jadwal ujian berhasil diupdate!";
        } else {
            $error = "Gagal mengupdate jadwal: " . mysqli_error($conn);
        }
    }
}

// =====================================================
// PROSES HAPUS JADWAL
// =====================================================
if (isset($_GET['hapus'])) {
    $id = mysqli_real_escape_string($conn, $_GET['hapus']);
    $query = "DELETE FROM jadwal_ujian WHERE id = $id";
    
    if (mysqli_query($conn, $query)) {
        $message = "Jadwal ujian berhasil dihapus!";
    } else {
        $error = "Gagal menghapus jadwal: " . mysqli_error($conn);
    }
}

// =====================================================
// FILTER UNTUK KALENDER
// =====================================================
$bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : (int)date('m');
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');

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
// AMBIL DATA MATA PELAJARAN
// =====================================================
$query_mapel = "SELECT * FROM mata_pelajaran WHERE guru_id = $guru_id ORDER BY nama_mapel";
$result_mapel = mysqli_query($conn, $query_mapel);

// =====================================================
// HITUNG TOTAL DATA DI DATABASE
// =====================================================
$count_query = "SELECT COUNT(*) as total FROM jadwal_ujian j 
                JOIN mata_pelajaran mp ON j.mapel_id = mp.id 
                WHERE mp.guru_id = $guru_id";
$count_result = mysqli_query($conn, $count_query);
$count_data = mysqli_fetch_assoc($count_result);
$total_database = $count_data['total'];

// =====================================================
// AMBIL DATA JADWAL MENDATANG (UNTUK CARD)
// =====================================================
$query_mendatang = "SELECT 
                    j.*, 
                    mp.nama_mapel,
                    DAYNAME(j.tanggal_ujian) as hari_inggris,
                    DATEDIFF(j.tanggal_ujian, CURDATE()) as sisa_hari
                    FROM jadwal_ujian j 
                    JOIN mata_pelajaran mp ON j.mapel_id = mp.id 
                    WHERE mp.guru_id = $guru_id
                    AND j.tanggal_ujian >= CURDATE()
                    ORDER BY j.tanggal_ujian ASC, j.waktu_mulai ASC
                    LIMIT 5";
$result_mendatang = mysqli_query($conn, $query_mendatang);

// =====================================================
// AMBIL SEMUA DATA JADWAL (UNTUK TABEL)
// =====================================================
$query_semua = "SELECT 
                j.*, 
                mp.nama_mapel,
                DAYNAME(j.tanggal_ujian) as hari_inggris,
                DATEDIFF(j.tanggal_ujian, CURDATE()) as sisa_hari
                FROM jadwal_ujian j 
                JOIN mata_pelajaran mp ON j.mapel_id = mp.id 
                WHERE mp.guru_id = $guru_id
                ORDER BY j.tanggal_ujian DESC, j.waktu_mulai DESC";
$result_semua = mysqli_query($conn, $query_semua);

// =====================================================
// STATISTIK
// =====================================================
$query_stat = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN tanggal_ujian >= CURDATE() THEN 1 ELSE 0 END) as mendatang,
                SUM(CASE WHEN tanggal_ujian < CURDATE() THEN 1 ELSE 0 END) as terlewat
                FROM jadwal_ujian j 
                JOIN mata_pelajaran mp ON j.mapel_id = mp.id 
                WHERE mp.guru_id = $guru_id";
$result_stat = mysqli_query($conn, $query_stat);
$stat = mysqli_fetch_assoc($result_stat);

// =====================================================
// DATA KALENDER
// =====================================================
$query_kalender = "SELECT 
                    DAY(j.tanggal_ujian) as tgl,
                    COUNT(*) as jumlah,
                    GROUP_CONCAT(mp.nama_mapel SEPARATOR ', ') as mapel
                    FROM jadwal_ujian j 
                    JOIN mata_pelajaran mp ON j.mapel_id = mp.id 
                    WHERE mp.guru_id = $guru_id 
                    AND MONTH(j.tanggal_ujian) = $bulan 
                    AND YEAR(j.tanggal_ujian) = $tahun
                    GROUP BY DAY(j.tanggal_ujian)";
$result_kalender = mysqli_query($conn, $query_kalender);

$data_kalender = [];
while ($row = mysqli_fetch_assoc($result_kalender)) {
    $data_kalender[$row['tgl']] = $row;
}

// =====================================================
// AMBIL DATA UNTUK EDIT VIA AJAX
// =====================================================
if (isset($_GET['get_jadwal'])) {
    $id = mysqli_real_escape_string($conn, $_GET['get_jadwal']);
    $query = "SELECT j.* FROM jadwal_ujian j 
              JOIN mata_pelajaran mp ON j.mapel_id = mp.id 
              WHERE j.id = $id AND mp.guru_id = $guru_id";
    $result = mysqli_query($conn, $query);
    
    if (mysqli_num_rows($result) > 0) {
        echo json_encode(mysqli_fetch_assoc($result));
    } else {
        echo json_encode(['error' => 'Jadwal tidak ditemukan']);
    }
    exit;
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
            color: #667eea;
            margin-bottom: 10px;
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

        .btn-tambah {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            transition: all 0.3s;
        }

        .btn-tambah:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }

        .filter-card {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }

        .list-item {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            transition: all 0.3s;
        }

        .list-item:last-child {
            border-bottom: none;
        }

        .list-item:hover {
            background: #f8f9fa;
        }

        .list-item .mapel {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .list-item .tanggal {
            font-size: 0.9rem;
            color: #666;
        }

        .list-item .waktu {
            font-size: 0.85rem;
            color: #999;
        }

        .list-item.today-item {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
        }

        .badge-today {
            background: #dc3545;
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            margin-left: 5px;
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

        .calendar-day.has-jadwal {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .calendar-day .tanggal {
            font-size: 1.2rem;
            font-weight: 600;
        }

        .calendar-day .count {
            font-size: 0.7rem;
            opacity: 0.9;
        }

        .calendar-day.today {
            border: 3px solid #667eea !important;
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
        }

        .badge.has-jadwal {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 5px 10px;
        }

        .badge-mendatang {
            background: #28a745;
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
        }

        .badge-terlewat {
            background: #dc3545;
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
        }

        .badge-hari {
            background: #e3f2fd;
            color: #1976d2;
            padding: 5px 10px;
            border-radius: 15px;
        }

        .table th {
            background: #f8f9fa;
            font-weight: 600;
        }

        .table-warning {
            background-color: #fff3cd !important;
        }

        .btn-action {
            padding: 5px 10px;
            border-radius: 5px;
            transition: all 0.3s;
        }

        .btn-action:hover {
            transform: scale(1.1);
        }

        .modal-content {
            border-radius: 15px;
            border: none;
        }

        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0;
        }

        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }

        .h-100 {
            height: 100% !important;
        }

        .info-box {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg">
        <div class="container-fluid">
            <a class="navbar-brand" href="#"><i class="fas fa-school"></i> SIAJAR Sekolah</a>
            <div class="navbar-nav ms-auto">
                <span class="nav-link"><i class="fas fa-user-circle"></i> <?php echo $_SESSION['nama']; ?>
                    (Guru)</span>
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
                        <li><a href="input_nilai.php"><i class="fas fa-edit"></i> Input Nilai</a></li>
                        <li><a href="input_absensi.php"><i class="fas fa-calendar-check"></i> Input Absensi</a></li>
                        <li><a href="rekap_nilai.php"><i class="fas fa-chart-line"></i> Rekap Nilai</a></li>
                        <li><a href="kelola_tugas.php"><i class="fas fa-tasks"></i> Kelola Tugas</a></li>
                        <li><a href="jadwal_ujian.php" class="active"><i class="fas fa-clock"></i> Jadwal Ujian</a>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="col-md-9 col-lg-10">
                <div class="main-content">
                    <!-- Pesan Notifikasi -->
                    <?php if ($message): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle"></i> <?php echo $message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Page Header -->
                    <div class="page-header d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-1"><i class="fas fa-calendar-alt text-primary"></i> Jadwal Ujian</h4>
                            <p class="text-muted mb-0">Kelola jadwal ujian untuk mata pelajaran yang Anda ajar</p>
                        </div>
                        <button class="btn-tambah" data-bs-toggle="modal" data-bs-target="#modalTambahJadwal">
                            <i class="fas fa-plus"></i> Tambah Jadwal
                        </button>
                    </div>

                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="stat-card">
                                <i class="fas fa-calendar-alt"></i>
                                <div class="value"><?php echo $stat['total'] ?: 0; ?></div>
                                <div class="text-muted">Total Jadwal</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-card">
                                <i class="fas fa-hourglass-half text-success"></i>
                                <div class="value"><?php echo $stat['mendatang'] ?: 0; ?></div>
                                <div class="text-muted">Jadwal Mendatang</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-card">
                                <i class="fas fa-history text-danger"></i>
                                <div class="value"><?php echo $stat['terlewat'] ?: 0; ?></div>
                                <div class="text-muted">Jadwal Terlewat</div>
                            </div>
                        </div>
                    </div>

                    <!-- Info Box -->
                    <div class="info-box">
                        <i class="fas fa-database text-primary me-2"></i>
                        <strong>Total data di database:</strong> <?php echo $total_database; ?> jadwal
                        | <strong>Guru ID:</strong> <?php echo $guru_id; ?>
                        | <strong>Status:</strong> 
                        <?php if ($total_database > 0): ?>
                            <span class="text-success">Data tersedia ✓</span>
                        <?php else: ?>
                            <span class="text-danger">Belum ada data ✗</span>
                        <?php endif; ?>
                    </div>

                    <div class="row">
                        <!-- Kolom Kiri: Jadwal Mendatang -->
                        <div class="col-md-5">
                            <div class="card h-100">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-clock text-primary"></i> Jadwal Ujian Mendatang</span>
                                    <span class="badge bg-primary"><?php echo mysqli_num_rows($result_mendatang); ?>
                                        Jadwal</span>
                                </div>
                                <div class="card-body p-0">
                                    <?php if (mysqli_num_rows($result_mendatang) > 0): ?>
                                        <?php while ($row = mysqli_fetch_assoc($result_mendatang)):
                                            $is_today = ($row['sisa_hari'] == 0);
                                            $hari = getHariIndonesia($row['hari_inggris']);
                                        ?>
                                            <div class="list-item <?php echo $is_today ? 'today-item' : ''; ?>">
                                                <div class="d-flex justify-content-between">
                                                    <div>
                                                        <div class="mapel">
                                                            <?php echo $row['nama_mapel']; ?>
                                                            <?php if ($is_today): ?>
                                                                <span class="badge-today">Hari Ini</span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="tanggal">
                                                            <i class="far fa-calendar me-1"></i>
                                                            <?php echo date('d/m/Y', strtotime($row['tanggal_ujian'])); ?>
                                                            (<?php echo $hari; ?>)
                                                        </div>
                                                        <div class="waktu">
                                                            <i class="far fa-clock me-1"></i>
                                                            <?php echo substr($row['waktu_mulai'], 0, 5); ?> -
                                                            <?php echo substr($row['waktu_selesai'], 0, 5); ?>
                                                            <i class="fas fa-door-open ms-2 me-1"></i>
                                                            <?php echo $row['ruangan']; ?>
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <span class="badge bg-<?php
                                                                                if ($is_today) echo 'danger';
                                                                                elseif ($row['sisa_hari'] <= 3) echo 'warning text-dark';
                                                                                else echo 'success';
                                                                                ?>">
                                                            <?php
                                                            if ($is_today) echo 'Hari ini';
                                                            elseif ($row['sisa_hari'] == 1) echo 'Besok';
                                                            else echo $row['sisa_hari'] . ' hari lagi';
                                                            ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <div class="text-center py-5">
                                            <i class="fas fa-calendar-check fa-4x text-muted mb-3"></i>
                                            <h6 class="text-muted">Belum ada jadwal mendatang</h6>
                                            <p class="text-muted small">Klik "Tambah Jadwal" untuk membuat jadwal baru
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Kolom Kanan: Filter dan Kalender -->
                        <div class="col-md-7">
                            <div class="filter-card">
                                <h6 class="mb-3"><i class="fas fa-filter text-primary me-2"></i>Filter Kalender</h6>
                                <form method="GET" class="row g-3">
                                    <div class="col-md-5">
                                        <label class="form-label">Bulan</label>
                                        <select name="bulan" class="form-control">
                                            <?php for ($i = 1; $i <= 12; $i++): ?>
                                                <option value="<?php echo $i; ?>" <?php echo $bulan == $i ? 'selected' : ''; ?>>
                                                    <?php echo $nama_bulan[$i]; ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label">Tahun</label>
                                        <select name="tahun" class="form-control">
                                            <?php for ($i = date('Y'); $i <= date('Y') + 2; $i++): ?>
                                                <option value="<?php echo $i; ?>" <?php echo $tahun == $i ? 'selected' : ''; ?>>
                                                    <?php echo $i; ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2 d-flex align-items-end">
                                        <button type="submit" class="btn btn-primary w-100">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <!-- Kalender -->
                            <div class="card">
                                <div class="card-header">
                                    <i class="fas fa-calendar"></i> Kalender - <?php echo $nama_bulan[$bulan]; ?>
                                    <?php echo $tahun; ?>
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
                                            $has_jadwal = isset($data_kalender[$tanggal]);
                                            $is_today = ($tanggal == $hari_ini && $bulan == $bulan_ini && $tahun == $tahun_ini);
                                            $today_class = $is_today ? 'today' : '';

                                            echo '<div class="calendar-day ' . ($has_jadwal ? 'has-jadwal' : '') . ' ' . $today_class . '">';
                                            echo '<div class="tanggal">' . $tanggal . '</div>';
                                            if ($has_jadwal) {
                                                echo '<div class="count">' . $data_kalender[$tanggal]['jumlah'] . '</div>';
                                            }
                                            if ($is_today) {
                                                echo '<div class="today-label">Hari Ini</div>';
                                            }
                                            echo '</div>';
                                        }
                                        ?>
                                    </div>
                                    <div class="mt-3 text-center">
                                        <span class="badge bg-primary me-2">Hari Ini</span>
                                        <span class="badge has-jadwal me-2">Ada Jadwal</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- DAFTAR SEMUA JADWAL (TABEL) -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <i class="fas fa-list"></i> Daftar Semua Jadwal Ujian
                            <?php if ($total_database > 0): ?>
                                <span class="badge bg-primary ms-2"><?php echo $total_database; ?> Jadwal</span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>Mata Pelajaran</th>
                                            <th>Tanggal</th>
                                            <th>Hari</th>
                                            <th>Waktu</th>
                                            <th>Ruangan</th>
                                            <th>Keterangan</th>
                                            <th>Status</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($total_database > 0): ?>
                                            <?php
                                            $no = 1;
                                            while ($row = mysqli_fetch_assoc($result_semua)):
                                                $tanggal = strtotime($row['tanggal_ujian']);
                                                $hari = getHariIndonesia($row['hari_inggris']);
                                                $is_today = (date('Y-m-d', $tanggal) == date('Y-m-d'));
                                                $row_class = $is_today ? 'table-warning' : '';
                                            ?>
                                                <tr class="<?php echo $row_class; ?>">
                                                    <td><?php echo $no++; ?></td>
                                                    <td><strong><?php echo $row['nama_mapel']; ?></strong></td>
                                                    <td>
                                                        <?php echo date('d/m/Y', $tanggal); ?>
                                                        <?php if ($is_today): ?>
                                                            <span class="badge bg-danger ms-1">Hari Ini</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><span class="badge bg-info text-dark"><?php echo $hari; ?></span>
                                                    </td>
                                                    <td><?php echo substr($row['waktu_mulai'], 0, 5); ?> -
                                                        <?php echo substr($row['waktu_selesai'], 0, 5); ?></td>
                                                    <td><?php echo $row['ruangan']; ?></td>
                                                    <td><?php echo $row['keterangan'] ?: '-'; ?></td>
                                                    <td>
                                                        <?php if ($row['sisa_hari'] > 0): ?>
                                                            <span class="badge bg-success"><?php echo $row['sisa_hari']; ?>
                                                                hari lagi</span>
                                                        <?php elseif ($row['sisa_hari'] == 0): ?>
                                                            <span class="badge bg-warning text-dark">Hari ini</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">Terlewat</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-primary btn-action me-1"
                                                            onclick="editJadwal(<?php echo $row['id']; ?>)"
                                                            title="Edit Jadwal">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <a href="?hapus=<?php echo $row['id']; ?>&bulan=<?php echo $bulan; ?>&tahun=<?php echo $tahun; ?>"
                                                            class="btn btn-sm btn-outline-danger btn-action"
                                                            onclick="return confirm('Yakin ingin menghapus jadwal ini?')"
                                                            title="Hapus Jadwal">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="9" class="text-center py-5">
                                                    <i class="fas fa-calendar-times fa-4x text-muted mb-3"></i>
                                                    <h5 class="text-muted">Belum ada jadwal ujian</h5>
                                                    <p class="text-muted">Klik tombol "Tambah Jadwal" untuk membuat jadwal
                                                        baru</p>
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

    <!-- Modal Tambah Jadwal -->
    <div class="modal fade" id="modalTambahJadwal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle"></i> Tambah Jadwal Ujian</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="tambah">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Mata Pelajaran</label>
                                <select name="mapel_id" class="form-control" required>
                                    <option value="">Pilih Mata Pelajaran</option>
                                    <?php
                                    mysqli_data_seek($result_mapel, 0);
                                    while ($row = mysqli_fetch_assoc($result_mapel)):
                                    ?>
                                        <option value="<?php echo $row['id']; ?>"><?php echo $row['nama_mapel']; ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tanggal Ujian</label>
                                <input type="date" name="tanggal_ujian" class="form-control" required
                                    min="<?php echo date('Y-m-d'); ?>"
                                    value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Waktu Mulai</label>
                                <input type="time" name="waktu_mulai" class="form-control" required value="08:00">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Waktu Selesai</label>
                                <input type="time" name="waktu_selesai" class="form-control" required value="10:00">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Ruangan</label>
                                <input type="text" name="ruangan" class="form-control" required
                                    placeholder="Contoh: Ruang 101">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Keterangan (Opsional)</label>
                            <textarea name="keterangan" class="form-control" rows="2"
                                placeholder="Contoh: UTS Semester Ganjil 2024/2025"></textarea>
                        </div>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> Pastikan tidak ada bentrok jadwal dengan ruangan yang
                            sama.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Simpan Jadwal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Edit Jadwal -->
    <div class="modal fade" id="modalEditJadwal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Jadwal Ujian</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="formEditJadwal">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" id="edit_id">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Mata Pelajaran</label>
                                <select name="mapel_id" id="edit_mapel_id" class="form-control" required>
                                    <option value="">Pilih Mata Pelajaran</option>
                                    <?php
                                    mysqli_data_seek($result_mapel, 0);
                                    while ($row = mysqli_fetch_assoc($result_mapel)):
                                    ?>
                                        <option value="<?php echo $row['id']; ?>"><?php echo $row['nama_mapel']; ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tanggal Ujian</label>
                                <input type="date" name="tanggal_ujian" id="edit_tanggal" class="form-control" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Waktu Mulai</label>
                                <input type="time" name="waktu_mulai" id="edit_mulai" class="form-control" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Waktu Selesai</label>
                                <input type="time" name="waktu_selesai" id="edit_selesai" class="form-control" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Ruangan</label>
                                <input type="text" name="ruangan" id="edit_ruangan" class="form-control" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Keterangan</label>
                            <textarea name="keterangan" id="edit_keterangan" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Jadwal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function editJadwal(id) {
            fetch('jadwal_ujian.php?get_jadwal=' + id)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert(data.error);
                        return;
                    }

                    document.getElementById('edit_id').value = data.id;
                    document.getElementById('edit_mapel_id').value = data.mapel_id;
                    document.getElementById('edit_tanggal').value = data.tanggal_ujian;
                    document.getElementById('edit_mulai').value = data.waktu_mulai;
                    document.getElementById('edit_selesai').value = data.waktu_selesai;
                    document.getElementById('edit_ruangan').value = data.ruangan;
                    document.getElementById('edit_keterangan').value = data.keterangan || '';

                    var modal = new bootstrap.Modal(document.getElementById('modalEditJadwal'));
                    modal.show();
                })
                .catch(error => {
                    alert('Gagal mengambil data jadwal');
                });
        }

        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function (e) {
                if (this.querySelector('input[name="waktu_mulai"]') && this.querySelector('input[name="waktu_selesai"]')) {
                    const mulai = this.querySelector('input[name="waktu_mulai"]').value;
                    const selesai = this.querySelector('input[name="waktu_selesai"]').value;

                    if (mulai >= selesai) {
                        e.preventDefault();
                        alert('Waktu selesai harus setelah waktu mulai!');
                    }
                }
            });
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>