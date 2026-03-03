<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SESSION['role'] == 'siswa') {
    header("Location: siswa/dashboard.php");
} elseif ($_SESSION['role'] == 'guru') {
    header("Location: guru/dashboard.php");
} else {
    header("Location: logout.php");
}
exit();
?>