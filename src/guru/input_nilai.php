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
// PROSES INPUT NILAI
// =====================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $siswa_id = mysqli_real_escape_string($conn, $_POST['siswa_id']);
    $mapel_id = mysqli_real_escape_string($conn, $_POST['mapel_id']);
    $tugas = mysqli_real_escape_string($conn, $_POST['tugas']);
    $nilai = mysqli_real_escape_string($conn, $_POST['nilai']);
    $semester = mysqli_real_escape_string($conn, $_POST['semester']);
    $tahun_ajaran = mysqli_real_escape_string($conn, $_POST['tahun_ajaran']);
    $keterangan = mysqli_real_escape_string($conn, $_POST['keterangan']);
    
    // Validasi nilai (0-100)
    if ($nilai < 0 || $nilai > 100) {
        $error = "Nilai harus antara 0 - 100!";
    } else {
        $query = "INSERT INTO nilai (siswa_id, mapel_id, tugas, nilai, semester, tahun_ajaran, keterangan) 
                  VALUES ('$siswa_id', '$mapel_id', '$tugas', '$nilai', '$semester', '$tahun_ajaran', '$keterangan')";
        
        if (mysqli_query($conn, $query)) {
            $message = "Nilai berhasil disimpan! (ID: " . mysqli_insert_id($conn) . ")";
        } else {
            $error = "Gagal menyimpan nilai: " . mysqli_error($conn);
        }
    }
}

// =====================================================
// AMBIL DATA UNTUK FILTER
// =====================================================

// Ambil daftar mata pelajaran guru
$query_mapel = "SELECT * FROM mata_pelajaran WHERE guru_id = $guru_id ORDER BY nama_mapel";
$result_mapel = mysqli_query($conn, $query_mapel);

// Ambil daftar kelas unik dari siswa
$query_kelas = "SELECT DISTINCT kelas FROM users WHERE role='siswa' AND kelas IS NOT NULL ORDER BY kelas";
$result_kelas = mysqli_query($conn, $query_kelas);

// =====================================================
// FILTER UNTUK TABEL NILAI
// =====================================================
$filter_kelas = isset($_GET['filter_kelas']) ? $_GET['filter_kelas'] : '';
$filter_mapel = isset($_GET['filter_mapel']) ? $_GET['filter_mapel'] : '';
$filter_semester = isset($_GET['filter_semester']) ? $_GET['filter_semester'] : '';
$filter_tahun = isset($_GET['filter_tahun']) ? $_GET['filter_tahun'] : '';

// =====================================================
// AMBIL DATA NILAI TERBARU
// =====================================================
$query_terbaru = "SELECT 
                    n.*, 
                    u.nama_lengkap, 
                    u.kelas, 
                    mp.nama_mapel 
                  FROM nilai n 
                  JOIN users u ON n.siswa_id = u.id 
                  JOIN mata_pelajaran mp ON n.mapel_id = mp.id 
                  WHERE mp.guru_id = $guru_id";

// Tambahkan filter jika ada
if ($filter_kelas) {
    $query_terbaru .= " AND u.kelas = '$filter_kelas'";
}
if ($filter_mapel) {
    $query_terbaru .= " AND n.mapel_id = '$filter_mapel'";
}
if ($filter_semester) {
    $query_terbaru .= " AND n.semester = '$filter_semester'";
}
if ($filter_tahun) {
    $query_terbaru .= " AND n.tahun_ajaran = '$filter_tahun'";
}

$query_terbaru .= " ORDER BY n.created_at DESC LIMIT 20";
$result_terbaru = mysqli_query($conn, $query_terbaru);

// =====================================================
// AMBIL DAFTAR SISWA UNTUK DROPDOWN
// =====================================================
$selected_kelas = isset($_POST['kelas_filter']) ? $_POST['kelas_filter'] : (isset($_GET['kelas']) ? $_GET['kelas'] : '');
if ($selected_kelas) {
    $query_siswa = "SELECT * FROM users WHERE role='siswa' AND kelas = '$selected_kelas' ORDER BY nama_lengkap";
} else {
    $query_siswa = "SELECT * FROM users WHERE role='siswa' ORDER BY kelas, nama_lengkap LIMIT 50";
}
$result_siswa = mysqli_query($conn, $query_siswa);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Input Nilai - SIAJAR Sekolah</title>
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

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            font-weight: 500;
            color: #333;
            margin-bottom: 5px;
        }

        .form-control,
        .form-select {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 10px 15px;
            transition: all 0.3s;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            outline: none;
        }

        .btn-submit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }

        .btn-reset {
            background: #6c757d;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-reset:hover {
            background: #5a6268;
            color: white;
        }

        .table th {
            border-top: none;
            font-weight: 600;
            color: #666;
            background: #f8f9fa;
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

        .badge-danger {
            background: #dc3545;
            color: white;
        }

        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }

        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #667eea;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .spinner-border {
            width: 1rem;
            height: 1rem;
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
                        <li><a href="input_nilai.php" class="active"><i class="fas fa-edit"></i> Input Nilai</a></li>
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
                    <!-- Page Header -->
                    <div class="page-header">
                        <h4 class="mb-1"><i class="fas fa-edit text-primary"></i> Input Nilai Siswa</h4>
                        <p class="text-muted mb-0">Tambahkan nilai tugas, UTS, dan UAS untuk siswa</p>
                    </div>

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

                    <!-- Info Box -->
                    <div class="info-box">
                        <i class="fas fa-info-circle text-primary me-2"></i>
                        <strong>Petunjuk:</strong> Pilih siswa, mata pelajaran, jenis tugas, dan masukkan nilai (0-100).
                    </div>

                    <!-- Form Input Nilai -->
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-pen"></i> Form Input Nilai
                        </div>
                        <div class="card-body">
                            <form method="POST" action="" id="formNilai">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">Filter Kelas (untuk memilih siswa)</label>
                                            <select name="kelas_filter" class="form-control" onchange="this.form.submit()">
                                                <option value="">-- Semua Kelas --</option>
                                                <?php
                                                mysqli_data_seek($result_kelas, 0);
                                                while ($row = mysqli_fetch_assoc($result_kelas)):
                                                ?>
                                                    <option value="<?php echo $row['kelas']; ?>" <?php echo $selected_kelas == $row['kelas'] ? 'selected' : ''; ?>>
                                                        <?php echo $row['kelas']; ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </form>

                            <form method="POST" action="" id="formSimpanNilai">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">Siswa</label>
                                            <select name="siswa_id" class="form-control" required>
                                                <option value="">Pilih Siswa</option>
                                                <?php
                                                $current_kelas = '';
                                                mysqli_data_seek($result_siswa, 0);
                                                while ($row = mysqli_fetch_assoc($result_siswa)):
                                                    if ($current_kelas != $row['kelas']):
                                                        if ($current_kelas != '') echo '</optgroup>';
                                                        $current_kelas = $row['kelas'];
                                                        echo '<optgroup label="Kelas ' . $current_kelas . '">';
                                                    endif;
                                                ?>
                                                    <option value="<?php echo $row['id']; ?>">
                                                        <?php echo $row['nama_lengkap']; ?>
                                                    </option>
                                                <?php
                                                endwhile;
                                                if ($current_kelas != '') echo '</optgroup>';
                                                ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">Mata Pelajaran</label>
                                            <select name="mapel_id" class="form-control" required>
                                                <option value="">Pilih Mata Pelajaran</option>
                                                <?php while ($row = mysqli_fetch_assoc($result_mapel)): ?>
                                                    <option value="<?php echo $row['id']; ?>">
                                                        <?php echo $row['nama_mapel']; ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">Jenis Tugas</label>
                                            <select name="tugas" class="form-control" required>
                                                <option value="">Pilih Jenis Tugas</option>
                                                <option value="Tugas Harian">Tugas Harian</option>
                                                <option value="UTS">UTS</option>
                                                <option value="UAS">UAS</option>
                                                <option value="Praktikum">Praktikum</option>
                                                <option value="Remidi">Remidi</option>
                                                <option value="Tugas Kelompok">Tugas Kelompok</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">Nilai (0-100)</label>
                                            <input type="number" step="0.01" min="0" max="100" name="nilai" class="form-control" required placeholder="Masukkan nilai">
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-label">Semester</label>
                                            <select name="semester" class="form-control" required>
                                                <option value="">Pilih Semester</option>
                                                <option value="Ganjil">Ganjil</option>
                                                <option value="Genap">Genap</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-label">Tahun Ajaran</label>
                                            <select name="tahun_ajaran" class="form-control" required>
                                                <option value="">Pilih Tahun Ajaran</option>
                                                <option value="2023/2024">2023/2024</option>
                                                <option value="2024/2025">2024/2025</option>
                                                <option value="2025/2026">2025/2026</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-label">Keterangan (Opsional)</label>
                                            <input type="text" name="keterangan" class="form-control" placeholder="Contoh: Remedial, Tugas Tambahan">
                                        </div>
                                    </div>
                                </div>

                                <div class="text-end mt-4">
                                    <button type="reset" class="btn-reset me-2">
                                        <i class="fas fa-undo"></i> Reset
                                    </button>
                                    <button type="submit" class="btn-submit" id="btnSimpan">
                                        <i class="fas fa-save"></i> Simpan Nilai
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Filter untuk Tabel Nilai -->
                    <div class="filter-section mt-4">
                        <h6 class="mb-3"><i class="fas fa-filter text-primary me-2"></i>Filter Nilai</h6>
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <select name="filter_kelas" class="form-control">
                                    <option value="">Semua Kelas</option>
                                    <?php
                                    mysqli_data_seek($result_kelas, 0);
                                    while ($row = mysqli_fetch_assoc($result_kelas)):
                                    ?>
                                        <option value="<?php echo $row['kelas']; ?>" <?php echo $filter_kelas == $row['kelas'] ? 'selected' : ''; ?>>
                                            <?php echo $row['kelas']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select name="filter_mapel" class="form-control">
                                    <option value="">Semua Mapel</option>
                                    <?php
                                    mysqli_data_seek($result_mapel, 0);
                                    while ($row = mysqli_fetch_assoc($result_mapel)):
                                    ?>
                                        <option value="<?php echo $row['id']; ?>" <?php echo $filter_mapel == $row['id'] ? 'selected' : ''; ?>>
                                            <?php echo $row['nama_mapel']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="filter_semester" class="form-control">
                                    <option value="">Semua Semester</option>
                                    <option value="Ganjil" <?php echo $filter_semester == 'Ganjil' ? 'selected' : ''; ?>>Ganjil</option>
                                    <option value="Genap" <?php echo $filter_semester == 'Genap' ? 'selected' : ''; ?>>Genap</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="filter_tahun" class="form-control">
                                    <option value="">Semua Tahun</option>
                                    <option value="2023/2024" <?php echo $filter_tahun == '2023/2024' ? 'selected' : ''; ?>>2023/2024</option>
                                    <option value="2024/2025" <?php echo $filter_tahun == '2024/2025' ? 'selected' : ''; ?>>2024/2025</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search"></i> Filter
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Tabel Nilai Terbaru -->
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-history"></i> Riwayat Nilai Terbaru
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>Tanggal</th>
                                            <th>Siswa</th>
                                            <th>Kelas</th>
                                            <th>Mapel</th>
                                            <th>Tugas</th>
                                            <th>Nilai</th>
                                            <th>Semester</th>
                                            <th>Tahun</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (mysqli_num_rows($result_terbaru) > 0): ?>
                                            <?php
                                            $no = 1;
                                            while ($row = mysqli_fetch_assoc($result_terbaru)):
                                            ?>
                                                <tr>
                                                    <td><?php echo $no++; ?></td>
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
                                                    <td><?php echo $row['semester']; ?></td>
                                                    <td><?php echo $row['tahun_ajaran']; ?></td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="9" class="text-center py-4">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Loading state on form submit
        document.getElementById('formSimpanNilai')?.addEventListener('submit', function() {
            const btn = document.getElementById('btnSimpan');
            if (btn) {
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Menyimpan...';
                btn.disabled = true;
            }
        });

        // Auto submit form filter kelas
        document.querySelector('select[name="kelas_filter"]')?.addEventListener('change', function() {
            this.form.submit();
        });
    </script>
</body>

</html>