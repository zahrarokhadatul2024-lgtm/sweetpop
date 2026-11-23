<?php
mysqli_report(MYSQLI_REPORT_OFF);

$servername = "localhost";
$username   = "root";
$dbname     = "sweetpop";

// Coba koneksi tanpa password (default Laragon), fallback ke '1234'
$con = @mysqli_connect($servername, $username, "", $dbname);
if (!$con) {
    $con = @mysqli_connect($servername, $username, "1234", $dbname);
}

if (!$con) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}
?>