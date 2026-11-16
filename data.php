<?php
header('Content-Type: application/json');

$koneksi = mysqli_connect("localhost", "root", "", "jakarta");

if (!$koneksi) {
    die(json_encode(["error" => mysqli_connect_error()]));
}
mysqli_set_charset($koneksi, "utf8");

$query = mysqli_query($koneksi, "SELECT * FROM bank_sampah_baru");
$data  = [];

while ($row = mysqli_fetch_assoc($query)) {
    $data[] = [
        'id'         => (int) $row['OBJECTID'],
        'nama_objek' => $row['NAMOBJ'],
        'jenis'      => $row['JENIS_1'],
        'latitude'   => (float) $row['LATITUDE'],
        'longitude'  => (float) $row['LONGITUDE'],
        'kelurahan'  => $row['WADMKD'],
        'kecamatan'  => $row['WADMKC'],
        'kota'       => $row['WADMKK']
    ];
}

echo json_encode($data);
?>