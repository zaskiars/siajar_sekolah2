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
// PROSES INPUT ABSENSI
// =====================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tanggal = mysqli_real_escape_string($conn, $_POST['tanggal']);
    $siswa_ids = $_POST['siswa_id'];
    $statuses = $_POST['status'];
    $keterangans = $_POST['keterangan'];
    
    $success = true;
    $inserted = 0;
    $updated = 0;
    
    for ($i = 0; $i < count($siswa_ids); $i++) {
        $siswa_id = mysqli_real_escape_string($conn, $siswa_ids[$i]);
        $status = mysqli_real_escape_string($conn, $statuses[$i]);
        $keterangan = mysqli_real_escape_string($conn, $keterangans[$i]);
        
        // Cek apakah sudah ada absensi untuk siswa ini di tanggal tersebut
        $check = "SELECT id FROM absensi WHERE siswa_id = $siswa_id AND tanggal = '$tanggal'";
        $result_check = mysqli_query($conn, $check);
        
        if (mysqli_num_rows($result_check) > 0) {
            // Update
            $query = "UPDATE absensi SET status = '$status', keterangan = '$keterangan' 
                      WHERE siswa_id = $siswa_id AND tanggal = '$tanggal'";
            if (mysqli_query($conn, $query)) {
                $updated++;
            } else {
                $success = false;
                $error = "Gagal mengupdate absensi: " . mysqli_error($conn);
                break;
            }
        } else {
            // Insert
            $query = "INSERT INTO absensi (siswa_id, tanggal, status, keterangan) 
                      VALUES ('$siswa_id', '$tanggal', '$status', '$keterangan')";
            if (mysqli_query($conn, $query)) {
                $inserted++;
            } else {
                $success = false;
                $error = "Gagal menyimpan absensi: " . mysqli_error($conn);
                break;
            }
        }
    }
    
    if ($success) {
        $message = "Absensi berhasil disimpan untuk tanggal " . date('d/m/Y', strtotime($tanggal));
        $message .= " ($inserted data baru, $updated data diupdate)";
    }
}

// =====================================================
// FILTER
// =====================================================
$kelas = isset($_GET['kelas']) ? $_GET['kelas'] : '';
$tanggal = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');

// =====================================================
// AMBIL DAFTAR KELAS UNIK
// =====================================================
$query_kelas = "SELECT DISTINCT u.kelas 
                FROM users u 
                JOIN nilai n ON u.id = n.siswa_id 
                JOIN mata_pelajaran mp ON n.mapel_id = mp.id 
                WHERE mp.guru_id = $guru_id AND u.kelas IS NOT NULL
                ORDER BY u.kelas";
$result_kelas = mysqli_query($conn, $query_kelas);

// =====================================================
// AMBIL DAFTAR SISWA BERDASARKAN KELAS
// =====================================================
if ($kelas) {
    $query_siswa = "SELECT * FROM users 
                    WHERE role='siswa' AND kelas = '$kelas' 
                    ORDER BY nama_lengkap";
} else {
    // Jika kelas belum dipilih, ambil 10 siswa pertama sebagai contoh
    $query_siswa = "SELECT * FROM users 
                    WHERE role='siswa' 
                    ORDER BY kelas, nama_lengkap 
                    LIMIT 10";
}
$result_siswa = mysqli_query($conn, $query_siswa);

// =====================================================
// AMBIL DATA ABSENSI YANG SUDAH ADA
// =====================================================
$query_absensi = "SELECT * FROM absensi WHERE tanggal = '$tanggal'";
$result_absensi = mysqli_query($conn, $query_absensi);
$absensi_exists = [];
while ($row = mysqli_fetch_assoc($result_absensi)) {
    $absensi_exists[$row['siswa_id']] = $row;
}

// =====================================================
// HITUNG STATISTIK ABSENSI HARI INI
// =====================================================
$query_stat_hari = "SELECT 
                        status,
                        COUNT(*) as jumlah
                    FROM absensi 
                    WHERE tanggal = '$tanggal'
                    GROUP BY status";
$result_stat_hari = mysqli_query($conn, $query_stat_hari);
$stat_hari = ['hadir' => 0, 'sakit' => 0, 'izin' => 0, 'alpha' => 0];
while ($row = mysqli_fetch_assoc($result_stat_hari)) {
    $stat_hari[$row['status']] = $row['jumlah'];
}
$total_hari_ini = array_sum($stat_hari);

// =====================================================
// FUNGSI BANTU
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

function getStatusIcon($status) {
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
    <title>Input Absensi - SIAJAR Sekolah</title>
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

        .filter-card {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            text-align: center;
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .stat-card i {
            font-size: 2rem;
            margin-bottom: 10px;
        }

        .stat-card .value {
            font-size: 2rem;
            font-weight: 700;
            color: #333;
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

        .btn-simpan {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-simpan:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }

        .table-absensi th {
            background: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 5px;
            color: white;
            font-size: 0.9rem;
            display: inline-block;
        }

        .status-hadir {
            background: #28a745;
        }

        .status-sakit {
            background: #ffc107;
            color: #333;
        }

        .status-izin {
            background: #17a2b8;
        }

        .status-alpha {
            background: #dc3545;
        }

        .form-select.status-select {
            border-radius: 20px;
            padding: 5px 10px;
            font-size: 0.9rem;
        }

        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #667eea;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .today-badge {
            background: #dc3545;
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            margin-left: 5px;
        }

        .progress {
            height: 8px;
            border-radius: 4px;
        }

        .btn-quick {
            margin: 2px;
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
                        <li><a href="input_absensi.php" class="active"><i class="fas fa-calendar-check"></i> Input Absensi</a></li>
                        <li><a href="rekap_nilai.php"><i class="fas fa-chart-line"></i> Rekap Nilai</a></li>
                        <li><a href="kelola_tugas.php"><i class="fas fa-tasks"></i> Kelola Tugas</a></li>
                        <li><a href="jadwal_ujian.php"><i class="fas fa-clock"></i> Jadwal Ujian</a></li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="main-content">
                    <!-- Page Header -->
                    <div class="page-header">
                        <h4 class="mb-1"><i class="fas fa-calendar-check text-primary"></i> Input Absensi Siswa</h4>
                        <p class="text-muted mb-0">Catat kehadiran siswa per kelas</p>
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

                    <!-- Info Tanggal -->
                    <div class="info-box">
                        <i class="fas fa-info-circle text-primary me-2"></i>
                        <strong>Tanggal:</strong> <?php echo date('d/m/Y', strtotime($tanggal)); ?>
                        <?php if ($tanggal == date('Y-m-d')): ?>
                            <span class="today-badge">Hari Ini</span>
                        <?php endif; ?>
                    </div>

                    <!-- Statistik Hari Ini -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="stat-card hadir">
                                <i class="fas fa-check-circle"></i>
                                <div class="value"><?php echo $stat_hari['hadir']; ?></div>
                                <div class="text-muted">Hadir</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card sakit">
                                <i class="fas fa-hospital"></i>
                                <div class="value"><?php echo $stat_hari['sakit']; ?></div>
                                <div class="text-muted">Sakit</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card izin">
                                <i class="fas fa-envelope"></i>
                                <div class="value"><?php echo $stat_hari['izin']; ?></div>
                                <div class="text-muted">Izin</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card alpha">
                                <i class="fas fa-times-circle"></i>
                                <div class="value"><?php echo $stat_hari['alpha']; ?></div>
                                <div class="text-muted">Alpha</div>
                            </div>
                        </div>
                    </div>

                    <!-- Filter Card -->
                    <div class="filter-card">
                        <form method="GET" class="row">
                            <div class="col-md-5">
                                <label class="form-label">Pilih Kelas</label>
                                <select name="kelas" class="form-control" required>
                                    <option value="">-- Pilih Kelas --</option>
                                    <?php while ($row = mysqli_fetch_assoc($result_kelas)): ?>
                                        <option value="<?php echo $row['kelas']; ?>" <?php echo $kelas == $row['kelas'] ? 'selected' : ''; ?>>
                                            <?php echo $row['kelas']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Tanggal</label>
                                <input type="date" name="tanggal" class="form-control" value="<?php echo $tanggal; ?>" max="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search"></i> Tampilkan
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Form Absensi -->
                    <?php if ($kelas): ?>
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-check-circle"></i> Input Absensi Kelas <?php echo $kelas; ?> - Tanggal <?php echo date('d/m/Y', strtotime($tanggal)); ?>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="" id="formAbsensi">
                                    <input type="hidden" name="tanggal" value="<?php echo $tanggal; ?>">

                                    <div class="table-responsive">
                                        <table class="table table-bordered table-absensi">
                                            <thead>
                                                <tr>
                                                    <th width="5%">No</th>
                                                    <th width="30%">Nama Siswa</th>
                                                    <th width="20%">Status</th>
                                                    <th width="45%">Keterangan</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                if (mysqli_num_rows($result_siswa) > 0):
                                                    $no = 1;
                                                    while ($siswa = mysqli_fetch_assoc($result_siswa)):
                                                        $absensi = isset($absensi_exists[$siswa['id']]) ? $absensi_exists[$siswa['id']] : null;
                                                ?>
                                                        <tr>
                                                            <td class="text-center"><?php echo $no++; ?></td>
                                                            <td>
                                                                <?php echo $siswa['nama_lengkap']; ?>
                                                                <input type="hidden" name="siswa_id[]" value="<?php echo $siswa['id']; ?>">
                                                            </td>
                                                            <td>
                                                                <select name="status[]" class="form-control form-select status-select" required>
                                                                    <option value="hadir" <?php echo $absensi && $absensi['status'] == 'hadir' ? 'selected' : ''; ?>>Hadir</option>
                                                                    <option value="sakit" <?php echo $absensi && $absensi['status'] == 'sakit' ? 'selected' : ''; ?>>Sakit</option>
                                                                    <option value="izin" <?php echo $absensi && $absensi['status'] == 'izin' ? 'selected' : ''; ?>>Izin</option>
                                                                    <option value="alpha" <?php echo $absensi && $absensi['status'] == 'alpha' ? 'selected' : ''; ?>>Alpha</option>
                                                                </select>
                                                            </td>
                                                            <td>
                                                                <input type="text" name="keterangan[]" class="form-control" placeholder="Keterangan (jika ada)" value="<?php echo $absensi ? htmlspecialchars($absensi['keterangan']) : ''; ?>">
                                                            </td>
                                                        </tr>
                                                    <?php endwhile; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="4" class="text-center py-4">
                                                            <i class="fas fa-users-slash fa-3x text-muted mb-3"></i>
                                                            <p class="text-muted">Tidak ada siswa di kelas <?php echo $kelas; ?></p>
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>

                                    <?php if (mysqli_num_rows($result_siswa) > 0): ?>
                                        <div class="text-end mt-4">
                                            <button type="reset" class="btn btn-secondary me-2">
                                                <i class="fas fa-undo"></i> Reset
                                            </button>
                                            <button type="submit" class="btn-simpan" id="btnSimpan">
                                                <i class="fas fa-save"></i> Simpan Absensi
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>

                        <!-- Tombol Cepat -->
                        <div class="row mt-3">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-body">
                                        <h6 class="mb-3"><i class="fas fa-bolt text-primary me-2"></i>Aksi Cepat</h6>
                                        <div class="d-flex gap-2 flex-wrap">
                                            <button type="button" class="btn btn-outline-success btn-sm btn-quick" onclick="setAllStatus('hadir')">
                                                <i class="fas fa-check-circle"></i> Semua Hadir
                                            </button>
                                            <button type="button" class="btn btn-outline-warning btn-sm btn-quick" onclick="setAllStatus('sakit')">
                                                <i class="fas fa-hospital"></i> Semua Sakit
                                            </button>
                                            <button type="button" class="btn btn-outline-info btn-sm btn-quick" onclick="setAllStatus('izin')">
                                                <i class="fas fa-envelope"></i> Semua Izin
                                            </button>
                                            <button type="button" class="btn btn-outline-danger btn-sm btn-quick" onclick="setAllStatus('alpha')">
                                                <i class="fas fa-times-circle"></i> Semua Alpha
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary btn-sm btn-quick" onclick="clearKeterangan()">
                                                <i class="fas fa-eraser"></i> Hapus Semua Keterangan
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    <?php else: ?>
                        <div class="card">
                            <div class="card-body text-center py-5">
                                <i class="fas fa-hand-point-left fa-4x text-muted mb-3"></i>
                                <h5 class="text-muted">Pilih kelas terlebih dahulu</h5>
                                <p class="text-muted">Silakan pilih kelas dari form filter di atas</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Fungsi untuk mengatur semua status
        function setAllStatus(status) {
            const selects = document.querySelectorAll('select[name="status[]"]');
            selects.forEach(select => {
                select.value = status;
            });
        }

        // Fungsi untuk menghapus semua keterangan
        function clearKeterangan() {
            const inputs = document.querySelectorAll('input[name="keterangan[]"]');
            inputs.forEach(input => {
                input.value = '';
            });
        }

        // Loading state on form submit
        document.getElementById('formAbsensi')?.addEventListener('submit', function() {
            const btn = document.getElementById('btnSimpan');
            if (btn) {
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Menyimpan...';
                btn.disabled = true;
            }
        });

        // Auto-hide alert after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                if (alert.classList.contains('show')) {
                    alert.classList.remove('show');
                    alert.classList.add('fade');
                }
            });
        }, 5000);
    </script>
</body>

</html>