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
// PROSES TAMBAH TUGAS
// =====================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'tambah') {
        $mapel_id = mysqli_real_escape_string($conn, $_POST['mapel_id']);
        $judul = mysqli_real_escape_string($conn, $_POST['judul']);
        $deskripsi = mysqli_real_escape_string($conn, $_POST['deskripsi']);
        $tanggal_deadline = mysqli_real_escape_string($conn, $_POST['tanggal_deadline']);
        
        // Validasi tanggal deadline tidak boleh kurang dari hari ini
        if (strtotime($tanggal_deadline) < strtotime(date('Y-m-d'))) {
            $error = "Tanggal deadline tidak boleh kurang dari hari ini!";
        } else {
            $query = "INSERT INTO tugas (mapel_id, judul, deskripsi, tanggal_deadline, created_by) 
                      VALUES ('$mapel_id', '$judul', '$deskripsi', '$tanggal_deadline', '$guru_id')";
            
            if (mysqli_query($conn, $query)) {
                $message = "Tugas berhasil ditambahkan! (ID: " . mysqli_insert_id($conn) . ")";
            } else {
                $error = "Gagal menambahkan tugas: " . mysqli_error($conn);
            }
        }
    }
    
    // =====================================================
    // PROSES EDIT TUGAS
    // =====================================================
    elseif ($_POST['action'] == 'edit') {
        $id = mysqli_real_escape_string($conn, $_POST['id']);
        $mapel_id = mysqli_real_escape_string($conn, $_POST['mapel_id']);
        $judul = mysqli_real_escape_string($conn, $_POST['judul']);
        $deskripsi = mysqli_real_escape_string($conn, $_POST['deskripsi']);
        $tanggal_deadline = mysqli_real_escape_string($conn, $_POST['tanggal_deadline']);
        
        $query = "UPDATE tugas SET 
                  mapel_id = '$mapel_id',
                  judul = '$judul',
                  deskripsi = '$deskripsi',
                  tanggal_deadline = '$tanggal_deadline'
                  WHERE id = $id AND created_by = $guru_id";
        
        if (mysqli_query($conn, $query)) {
            $message = "Tugas berhasil diupdate!";
        } else {
            $error = "Gagal mengupdate tugas: " . mysqli_error($conn);
        }
    }
}

// =====================================================
// PROSES HAPUS TUGAS
// =====================================================
if (isset($_GET['hapus'])) {
    $id = mysqli_real_escape_string($conn, $_GET['hapus']);
    
    // Hapus juga pengumpulan tugas terkait
    mysqli_query($conn, "DELETE FROM pengumpulan_tugas WHERE tugas_id = $id");
    
    $query = "DELETE FROM tugas WHERE id = $id AND created_by = $guru_id";
    
    if (mysqli_query($conn, $query)) {
        $message = "Tugas berhasil dihapus!";
    } else {
        $error = "Gagal menghapus tugas: " . mysqli_error($conn);
    }
}

// =====================================================
// AMBIL DATA MATA PELAJARAN
// =====================================================
$query_mapel = "SELECT * FROM mata_pelajaran WHERE guru_id = $guru_id ORDER BY nama_mapel";
$result_mapel = mysqli_query($conn, $query_mapel);

// =====================================================
// FILTER
// =====================================================
$status = isset($_GET['status']) ? $_GET['status'] : 'semua';
$mapel_filter = isset($_GET['mapel']) ? $_GET['mapel'] : '';

// =====================================================
// AMBIL DATA TUGAS
// =====================================================
$query_tugas = "SELECT 
                    t.*, 
                    mp.nama_mapel,
                    DATEDIFF(t.tanggal_deadline, CURDATE()) as sisa_hari,
                    (SELECT COUNT(*) FROM pengumpulan_tugas WHERE tugas_id = t.id) as total_siswa,
                    (SELECT COUNT(*) FROM pengumpulan_tugas WHERE tugas_id = t.id AND status = 'sudah') as sudah_kumpul,
                    (SELECT COUNT(*) FROM pengumpulan_tugas WHERE tugas_id = t.id AND status = 'dinilai') as sudah_dinilai,
                    (SELECT AVG(nilai) FROM pengumpulan_tugas WHERE tugas_id = t.id AND nilai IS NOT NULL) as rata_rata_nilai
                FROM tugas t
                JOIN mata_pelajaran mp ON t.mapel_id = mp.id
                WHERE t.created_by = $guru_id";

if ($mapel_filter) {
    $query_tugas .= " AND t.mapel_id = '$mapel_filter'";
}

if ($status == 'aktif') {
    $query_tugas .= " AND t.tanggal_deadline >= CURDATE()";
} elseif ($status == 'terlambat') {
    $query_tugas .= " AND t.tanggal_deadline < CURDATE()";
}

$query_tugas .= " ORDER BY 
                    CASE WHEN t.tanggal_deadline >= CURDATE() THEN 0 ELSE 1 END,
                    t.tanggal_deadline ASC";

$result_tugas = mysqli_query($conn, $query_tugas);

// =====================================================
// STATISTIK TUGAS
// =====================================================
$query_stat = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN tanggal_deadline >= CURDATE() THEN 1 ELSE 0 END) as aktif,
                SUM(CASE WHEN tanggal_deadline < CURDATE() THEN 1 ELSE 0 END) as terlambat
               FROM tugas 
               WHERE created_by = $guru_id";
$result_stat = mysqli_query($conn, $query_stat);
$stat = mysqli_fetch_assoc($result_stat);

// =====================================================
// AMBIL DATA UNTUK EDIT VIA AJAX
// =====================================================
if (isset($_GET['get_tugas'])) {
    $id = mysqli_real_escape_string($conn, $_GET['get_tugas']);
    $query = "SELECT * FROM tugas WHERE id = $id AND created_by = $guru_id";
    $result = mysqli_query($conn, $query);
    
    if (mysqli_num_rows($result) > 0) {
        echo json_encode(mysqli_fetch_assoc($result));
    } else {
        echo json_encode(['error' => 'Tugas tidak ditemukan']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Tugas - SIAJAR Sekolah</title>
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
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }

        .tugas-item {
            background: white;
            border-left: 5px solid #667eea;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
        }

        .tugas-item:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.2);
        }

        .tugas-item.terlambat {
            border-left-color: #dc3545;
            background: #fff8f8;
        }

        .tugas-item.hari-ini {
            border-left-color: #ffc107;
            background: #fff3cd;
        }

        .tugas-item .mapel-badge {
            background: #e3f2fd;
            color: #1976d2;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 10px;
        }

        .tugas-item .judul {
            font-size: 1.2rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
        }

        .tugas-item .deskripsi {
            color: #666;
            margin-bottom: 15px;
            font-size: 0.95rem;
        }

        .tugas-item .deadline {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }

        .tugas-item .deadline i {
            color: #667eea;
            margin-right: 5px;
        }

        .tugas-item .deadline.warning {
            color: #dc3545;
            font-weight: 600;
        }

        .progress-tugas {
            margin-top: 15px;
        }

        .progress-tugas .progress {
            height: 8px;
            border-radius: 4px;
        }

        .badge-aktif {
            background: #28a745;
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
        }

        .badge-terlambat {
            background: #dc3545;
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
        }

        .badge-hari-ini {
            background: #ffc107;
            color: #333;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
        }

        .badge-secondary {
            background: #6c757d;
            color: white;
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

        .today-badge {
            background: #dc3545;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            margin-left: 5px;
        }

        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #667eea;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
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
                        <li><a href="rekap_nilai.php"><i class="fas fa-chart-line"></i> Rekap Nilai</a></li>
                        <li><a href="kelola_tugas.php" class="active"><i class="fas fa-tasks"></i> Kelola Tugas</a></li>
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
                            <h4 class="mb-1"><i class="fas fa-tasks text-primary"></i> Kelola Tugas</h4>
                            <p class="text-muted mb-0">Buat dan kelola tugas untuk siswa</p>
                        </div>
                        <button class="btn-tambah" data-bs-toggle="modal" data-bs-target="#modalTambahTugas">
                            <i class="fas fa-plus"></i> Tambah Tugas
                        </button>
                    </div>

                    <!-- Pesan Notifikasi -->
                    <?php if ($message): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle"></i> <?php echo $message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Info Box -->
                    <div class="info-box">
                        <i class="fas fa-info-circle text-primary me-2"></i>
                        <strong>Informasi:</strong> Tugas dengan deadline hari ini akan ditandai dengan warna kuning.
                        Tugas terlambat ditandai dengan warna merah.
                    </div>

                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="stat-card">
                                <i class="fas fa-tasks"></i>
                                <div class="value"><?php echo $stat['total'] ?: 0; ?></div>
                                <div class="text-muted">Total Tugas</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-card">
                                <i class="fas fa-clock text-success"></i>
                                <div class="value"><?php echo $stat['aktif'] ?: 0; ?></div>
                                <div class="text-muted">Tugas Aktif</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-card">
                                <i class="fas fa-exclamation-triangle text-danger"></i>
                                <div class="value"><?php echo $stat['terlambat'] ?: 0; ?></div>
                                <div class="text-muted">Tugas Terlambat</div>
                            </div>
                        </div>
                    </div>

                    <!-- Filter Card -->
                    <div class="filter-card">
                        <form method="GET" class="row g-3">
                            <div class="col-md-5">
                                <label class="form-label">Mata Pelajaran</label>
                                <select name="mapel" class="form-control">
                                    <option value="">Semua Mapel</option>
                                    <?php
                                    mysqli_data_seek($result_mapel, 0);
                                    while ($row = mysqli_fetch_assoc($result_mapel)):
                                    ?>
                                        <option value="<?php echo $row['id']; ?>" <?php echo $mapel_filter == $row['id'] ? 'selected' : ''; ?>>
                                            <?php echo $row['nama_mapel']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-control">
                                    <option value="semua" <?php echo $status == 'semua' ? 'selected' : ''; ?>>Semua Tugas</option>
                                    <option value="aktif" <?php echo $status == 'aktif' ? 'selected' : ''; ?>>Tugas Aktif</option>
                                    <option value="terlambat" <?php echo $status == 'terlambat' ? 'selected' : ''; ?>>Tugas Terlambat</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-filter"></i> Filter
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Daftar Tugas -->
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-list"></i> Daftar Tugas
                        </div>
                        <div class="card-body">
                            <?php if (mysqli_num_rows($result_tugas) > 0): ?>
                                <?php while ($tugas = mysqli_fetch_assoc($result_tugas)):
                                    $sisa_hari = $tugas['sisa_hari'];
                                    $kelas_tugas = '';
                                    if ($sisa_hari < 0) {
                                        $kelas_tugas = 'terlambat';
                                    } elseif ($sisa_hari == 0) {
                                        $kelas_tugas = 'hari-ini';
                                    }
                                    $persentase = $tugas['total_siswa'] > 0 ? round(($tugas['sudah_kumpul'] / $tugas['total_siswa']) * 100) : 0;
                                ?>
                                    <div class="tugas-item <?php echo $kelas_tugas; ?>">
                                        <div class="row">
                                            <div class="col-md-8">
                                                <span class="mapel-badge">
                                                    <i class="fas fa-book"></i> <?php echo $tugas['nama_mapel']; ?>
                                                </span>
                                                <h5 class="judul"><?php echo $tugas['judul']; ?></h5>
                                                <p class="deskripsi"><?php echo nl2br(htmlspecialchars($tugas['deskripsi'])); ?></p>

                                                <div class="deadline <?php echo ($sisa_hari <= 3 && $sisa_hari > 0) ? 'warning' : ''; ?>">
                                                    <i class="far fa-calendar-alt"></i>
                                                    Deadline: <?php echo date('d/m/Y', strtotime($tugas['tanggal_deadline'])); ?>
                                                    <?php if ($sisa_hari > 0): ?>
                                                        <span class="badge bg-<?php echo $sisa_hari <= 3 ? 'warning' : 'primary'; ?> ms-2">
                                                            <?php echo $sisa_hari; ?> hari lagi
                                                        </span>
                                                    <?php elseif ($sisa_hari == 0): ?>
                                                        <span class="badge bg-warning text-dark ms-2">Deadline Hari Ini</span>
                                                        <span class="today-badge">Hari Ini</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger ms-2">
                                                            Terlambat <?php echo abs($sisa_hari); ?> hari
                                                        </span>
                                                    <?php endif; ?>
                                                </div>

                                                <div class="progress-tugas">
                                                    <div class="d-flex justify-content-between mb-1">
                                                        <small class="text-muted">Pengumpulan</small>
                                                        <small class="text-muted">
                                                            <?php echo $tugas['sudah_kumpul']; ?>/<?php echo $tugas['total_siswa']; ?> siswa
                                                            (<?php echo $tugas['sudah_dinilai']; ?> dinilai)
                                                            <?php if ($tugas['rata_rata_nilai']): ?>
                                                                | Rata-rata: <?php echo round($tugas['rata_rata_nilai'], 2); ?>
                                                            <?php endif; ?>
                                                        </small>
                                                    </div>
                                                    <div class="progress">
                                                        <div class="progress-bar bg-success" style="width: <?php echo $persentase; ?>%"></div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4 text-end">
                                                <?php if ($sisa_hari > 0): ?>
                                                    <span class="badge-aktif">Aktif</span>
                                                <?php elseif ($sisa_hari == 0): ?>
                                                    <span class="badge-hari-ini">Deadline Hari Ini</span>
                                                <?php else: ?>
                                                    <span class="badge-terlambat">Terlambat</span>
                                                <?php endif; ?>

                                                <div class="mt-3">
                                                    <button class="btn btn-sm btn-outline-primary btn-action me-2"
                                                        onclick="editTugas(<?php echo $tugas['id']; ?>)"
                                                        title="Edit Tugas">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <a href="?hapus=<?php echo $tugas['id']; ?>&mapel=<?php echo $mapel_filter; ?>&status=<?php echo $status; ?>"
                                                        class="btn btn-sm btn-outline-danger btn-action"
                                                        onclick="return confirm('Yakin ingin menghapus tugas ini? Semua pengumpulan tugas ini juga akan dihapus.')"
                                                        title="Hapus Tugas">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                                    <h5 class="text-muted">Belum ada tugas</h5>
                                    <p class="text-muted">Klik tombol "Tambah Tugas" untuk membuat tugas baru</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Tambah Tugas -->
    <div class="modal fade" id="modalTambahTugas" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle"></i> Tambah Tugas Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="tambah">

                        <div class="mb-3">
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

                        <div class="mb-3">
                            <label class="form-label">Judul Tugas</label>
                            <input type="text" name="judul" class="form-control" required
                                placeholder="Contoh: Tugas 1: Persamaan Kuadrat">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Deskripsi Tugas</label>
                            <textarea name="deskripsi" class="form-control" rows="4" required
                                placeholder="Jelaskan detail tugas, nomor soal, halaman buku, dll."></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Tanggal Deadline</label>
                            <input type="date" name="tanggal_deadline" class="form-control"
                                required min="<?php echo date('Y-m-d'); ?>"
                                value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>">
                            <small class="text-muted">Minimal tanggal: <?php echo date('d/m/Y'); ?></small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Simpan Tugas
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Edit Tugas -->
    <div class="modal fade" id="modalEditTugas" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Tugas</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="formEditTugas">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" id="edit_id">

                        <div class="mb-3">
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

                        <div class="mb-3">
                            <label class="form-label">Judul Tugas</label>
                            <input type="text" name="judul" id="edit_judul" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Deskripsi Tugas</label>
                            <textarea name="deskripsi" id="edit_deskripsi" class="form-control" rows="4" required></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Tanggal Deadline</label>
                            <input type="date" name="tanggal_deadline" id="edit_deadline" class="form-control" required
                                min="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Tugas
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Fungsi untuk edit tugas via AJAX
        function editTugas(id) {
            fetch('kelola_tugas.php?get_tugas=' + id)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert(data.error);
                        return;
                    }

                    document.getElementById('edit_id').value = data.id;
                    document.getElementById('edit_mapel_id').value = data.mapel_id;
                    document.getElementById('edit_judul').value = data.judul;
                    document.getElementById('edit_deskripsi').value = data.deskripsi;
                    document.getElementById('edit_deadline').value = data.tanggal_deadline;

                    var modal = new bootstrap.Modal(document.getElementById('modalEditTugas'));
                    modal.show();
                })
                .catch(error => {
                    alert('Gagal mengambil data tugas: ' + error);
                });
        }

        // Validasi form tambah tugas
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                if (this.querySelector('input[name="tanggal_deadline"]')) {
                    const deadline = new Date(this.querySelector('input[name="tanggal_deadline"]').value);
                    const today = new Date();
                    today.setHours(0, 0, 0, 0);

                    if (deadline < today) {
                        e.preventDefault();
                        alert('Tanggal deadline tidak boleh kurang dari hari ini!');
                    }
                }
            });
        });
    </script>
</body>

</html>