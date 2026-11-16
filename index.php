<?php
$koneksi = mysqli_connect("localhost", "root", "", "jakarta");

if (!$koneksi) {
    die("Koneksi gagal: " . mysqli_connect_error());
}
mysqli_set_charset($koneksi, "utf8");

/* ===== Proses Edit & Delete (dipanggil lewat fetch/AJAX) ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $aksi = isset($_POST['aksi']) ? $_POST['aksi'] : '';
    $id   = isset($_POST['id']) ? (int) $_POST['id'] : 0;

    if ($aksi === 'edit') {
        $nama = trim($_POST['nama_objek']);
        $jenis = trim($_POST['jenis']);
        $lat  = (float) $_POST['latitude'];
        $lng  = (float) $_POST['longitude'];
        $kel  = trim($_POST['kelurahan']);
        $kec  = trim($_POST['kecamatan']);
        $kota = trim($_POST['kota']);

        $stmt = mysqli_prepare($koneksi,
            "UPDATE bank_sampah_baru
             SET NAMOBJ=?, JENIS_1=?, LATITUDE=?, LONGITUDE=?, WADMKD=?, WADMKC=?, WADMKK=?
             WHERE OBJECTID=?");
        mysqli_stmt_bind_param($stmt, "ssddsssi", $nama, $jenis, $lat, $lng, $kel, $kec, $kota, $id);
        $ok = mysqli_stmt_execute($stmt);
        echo json_encode(['sukses' => $ok, 'pesan' => $ok ? 'Data berhasil diubah' : mysqli_error($koneksi)]);
    } elseif ($aksi === 'hapus') {
        $stmt = mysqli_prepare($koneksi, "DELETE FROM bank_sampah_baru WHERE OBJECTID=?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        $ok = mysqli_stmt_execute($stmt);
        echo json_encode(['sukses' => $ok, 'pesan' => $ok ? 'Data berhasil dihapus' : mysqli_error($koneksi)]);
    } else {
        echo json_encode(['sukses' => false, 'pesan' => 'Aksi tidak dikenal']);
    }
    exit;
}

$query    = mysqli_query($koneksi, "SELECT * FROM bank_sampah_baru");
$dataBank = [];

while ($row = mysqli_fetch_assoc($query)) {
    $dataBank[] = [
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
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Latihan Web GIS</title>

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.5.1/dist/leaflet.css">

    <style>
        html,
        body {
            height: 100%;
            width: 100%;
            margin: 0;
        }

        body {
            display: flex;
            flex-direction: column;
            font-family: Arial, sans-serif;
        }

        #map {
            flex: 1;
            min-height: 0;
            width: 100%;
        }

        .info-box {
            background: white;
            padding: 6px 10px;
            border-radius: 4px;
            box-shadow: 0 0 6px rgba(0, 0, 0, .3);
            font: 12px Arial, sans-serif;
            line-height: 18px;
        }

        /* Popup atribut marker */
        table.atribut {
            border-collapse: collapse;
            font: 12px Arial, sans-serif;
        }

        table.atribut th {
            text-align: left;
            padding: 3px 8px 3px 0;
            color: #555;
            white-space: nowrap;
        }

        table.atribut td {
            padding: 3px 0;
        }

        /* Panel tabel (bawah) */
        #panel {
            height: 40%;
            display: flex;
            flex-direction: column;
            background: white;
            border-top: 2px solid #1565c0;
            position: relative;
            z-index: 1000;
        }

        #panel.tutup {
            height: auto;
        }

        #panel.tutup #tabel-wrap {
            display: none;
        }

        #panel-kepala {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 14px;
            border-bottom: 1px solid #ddd;
        }

        #panel-kepala h3 {
            margin: 0;
            font-size: 15px;
            flex: 1;
        }

        #cari {
            padding: 6px;
            width: 260px;
        }

        #panel.tutup #cari {
            display: none;
        }

        #toggle {
            border: 0;
            background: #1565c0;
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
        }

        #tabel-wrap {
            flex: 1;
            overflow: auto;
        }

        #tabel-data {
            border-collapse: collapse;
            width: 100%;
            font-size: 13px;
        }

        #tabel-data th,
        #tabel-data td {
            border: 1px solid #ccc;
            padding: 6px 8px;
            text-align: left;
        }

        #tabel-data th {
            background: #1565c0;
            color: white;
            position: sticky;
            top: 0;
        }

        #tabel-data tbody tr {
            cursor: pointer;
        }

        #tabel-data tbody tr:nth-child(even) {
            background: #f3f6fb;
        }

        #tabel-data tbody tr:hover {
            background: #e3f2fd;
        }

        #tabel-data tbody tr.terpilih {
            background: #ffe0b2;
        }

        td.aksi {
            white-space: nowrap;
        }

        .btn-edit,
        .btn-hapus {
            border: 0;
            color: white;
            padding: 4px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }

        .btn-edit {
            background: #f9a825;
        }

        .btn-hapus {
            background: #c62828;
        }

        /* Form edit (popup kecil) */
        #modal-edit {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 3000;
            background: rgba(0, 0, 0, .5);
            align-items: center;
            justify-content: center;
        }

        #modal-edit.buka {
            display: flex;
        }

        #form-edit {
            background: white;
            width: 90%;
            max-width: 420px;
            border-radius: 6px;
            padding: 16px 18px;
        }

        #form-edit h3 {
            margin: 0 0 12px;
        }

        #form-edit label {
            display: block;
            font-size: 12px;
            color: #555;
            margin-top: 8px;
        }

        #form-edit input {
            width: 100%;
            box-sizing: border-box;
            padding: 6px;
            margin-top: 2px;
        }

        #form-edit .tombol {
            margin-top: 14px;
            text-align: right;
        }

        #form-edit .tombol button {
            border: 0;
            padding: 7px 14px;
            border-radius: 4px;
            cursor: pointer;
            color: white;
        }

        #btn-simpan {
            background: #1565c0;
        }

        #btn-batal {
            background: #757575;
        }
    </style>
</head>

<body>
    <script src="https://unpkg.com/leaflet@1.5.1/dist/leaflet.js"></script>

    <div id="map"></div>

    <!-- Panel tabel, tampil bersamaan dengan peta -->
    <div id="panel">
        <div id="panel-kepala">
            <h3>Tabel Atribut Bank Sampah (Total: <span id="total"><?php echo count($dataBank); ?></span>)</h3>
            <input type="text" id="cari" placeholder="Cari nama, jenis, kecamatan...">
            <button id="toggle">Sembunyikan ▼</button>
        </div>
        <div id="tabel-wrap">
            <table id="tabel-data">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nama Objek</th>
                        <th>Jenis</th>
                        <th>Latitude</th>
                        <th>Longitude</th>
                        <th>Kelurahan</th>
                        <th>Kecamatan</th>
                        <th>Kota</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dataBank as $d) { ?>
                        <tr data-id="<?php echo $d['id']; ?>">
                            <td><?php echo $d['id']; ?></td>
                            <td data-k="nama_objek"><?php echo htmlspecialchars($d['nama_objek']); ?></td>
                            <td data-k="jenis"><?php echo htmlspecialchars($d['jenis']); ?></td>
                            <td data-k="latitude"><?php echo $d['latitude']; ?></td>
                            <td data-k="longitude"><?php echo $d['longitude']; ?></td>
                            <td data-k="kelurahan"><?php echo htmlspecialchars($d['kelurahan']); ?></td>
                            <td data-k="kecamatan"><?php echo htmlspecialchars($d['kecamatan']); ?></td>
                            <td data-k="kota"><?php echo htmlspecialchars($d['kota']); ?></td>
                            <td class="aksi">
                                <button class="btn-edit" data-id="<?php echo $d['id']; ?>">Edit</button>
                                <button class="btn-hapus" data-id="<?php echo $d['id']; ?>">Delete</button>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Form edit -->
    <div id="modal-edit">
        <div id="form-edit">
            <h3>Edit Data Bank Sampah</h3>
            <input type="hidden" id="e-id">
            <label>Nama Objek<input type="text" id="e-nama_objek"></label>
            <label>Jenis<input type="text" id="e-jenis"></label>
            <label>Latitude<input type="text" id="e-latitude"></label>
            <label>Longitude<input type="text" id="e-longitude"></label>
            <label>Kelurahan<input type="text" id="e-kelurahan"></label>
            <label>Kecamatan<input type="text" id="e-kecamatan"></label>
            <label>Kota<input type="text" id="e-kota"></label>
            <div class="tombol">
                <button id="btn-batal">Batal</button>
                <button id="btn-simpan">Simpan</button>
            </div>
        </div>
    </div>

    <script>
        var map = L.map('map').setView([-6.2, 106.85], 11);
        var markerById = {};

        /* Data dari PHP */
        var semuaData = <?php echo json_encode($dataBank, JSON_HEX_TAG | JSON_HEX_AMP); ?>;

        /* Basemap */
        var basemap1 = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: 'Tiles &copy; Esri'
        });
        var basemap2 = L.tileLayer('https://{s}.google.com/vt/lyrs=m&x={x}&y={y}&z={z}', {
            maxZoom: 20,
            subdomains: ['mt0', 'mt1', 'mt2', 'mt3'],
            attribution: 'Map data &copy; Google'
        });
        var basemap3 = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors'
        });
        basemap1.addTo(map);

        /* Layer bank sampah */
        var layerBankSampah = L.layerGroup().addTo(map);

        function esc(t) {
            if (t === null || t === undefined || t === '') {
                return '-';
            }
            return String(t).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }

        function baris(k, v) {
            return '<tr><th>' + k + '</th><td>: ' + esc(v) + '</td></tr>';
        }

        function popupAtribut(d) {
            return '<table class="atribut">' +
                baris('ID', d.id) + baris('Nama Objek', d.nama_objek) + baris('Jenis', d.jenis) +
                baris('Latitude', d.latitude) + baris('Longitude', d.longitude) +
                baris('Kelurahan', d.kelurahan) + baris('Kecamatan', d.kecamatan) + baris('Kota', d.kota) +
                '</table>';
        }

        function cariData(id) {
            return semuaData.filter(function(x) {
                return x.id === id;
            })[0];
        }

        function barisTabel(id) {
            return document.querySelector('#tabel-data tbody tr[data-id="' + id + '"]');
        }

        /* Sorot baris tabel yang dipilih */
        function pilihBaris(id, gulir) {
            document.querySelectorAll('#tabel-data tbody tr.terpilih').forEach(function(tr) {
                tr.classList.remove('terpilih');
            });
            var tr = barisTabel(id);
            if (tr) {
                tr.classList.add('terpilih');
                if (gulir) {
                    tr.scrollIntoView({
                        block: 'center'
                    });
                }
            }
        }

        /* Titik dari data PHP */
        var titik = [];

        semuaData.forEach(function(d) {
            var posisi = [d.latitude, d.longitude];
            titik.push(posisi);

            var marker = L.circleMarker(posisi, {
                radius: 8,
                color: '#222',
                weight: 1,
                fillColor: '#ff9800',
                fillOpacity: 0.9
            });
            marker.bindPopup(popupAtribut(d));
            marker.bindTooltip(d.nama_objek);
            marker.on('click', function() {
                pilihBaris(d.id, true);
            });
            marker.addTo(layerBankSampah);
            markerById[d.id] = marker;
        });

        if (titik.length > 0) {
            map.fitBounds(L.latLngBounds(titik).pad(0.2));
        }

        /* Pengaturan layer */
        L.control.layers({
            "Esri Imagery": basemap1,
            "Google Street": basemap2,
            "OpenStreetMap": basemap3
        }, {
            "Bank Sampah": layerBankSampah
        }, {
            collapsed: false
        }).addTo(map);

        /* Scale bar */
        L.control.scale({
            maxWidth: 150,
            position: 'bottomright'
        }).addTo(map);

        /* Koordinat kursor */
        var Koordinat = L.Control.extend({
            options: {
                position: 'bottomleft'
            },
            onAdd: function() {
                this._div = L.DomUtil.create('div', 'info-box');
                this._div.innerHTML = 'Arahkan kursor ke peta';
                return this._div;
            }
        });
        var koordinat = new Koordinat().addTo(map);
        map.on('mousemove', function(e) {
            koordinat._div.innerHTML = 'Lat: ' + e.latlng.lat.toFixed(6) + '<br>Long: ' + e.latlng.lng.toFixed(6);
        });

        /* Pencarian pada tabel */
        document.getElementById('cari').addEventListener('input', function() {
            var kata = this.value.toLowerCase();
            document.querySelectorAll('#tabel-data tbody tr').forEach(function(tr) {
                tr.style.display = tr.textContent.toLowerCase().indexOf(kata) === -1 ? 'none' : '';
            });
        });

        /* Klik baris = zoom ke titik di peta */
        function lihatDiPeta(id) {
            var d = cariData(id);
            if (!d) return;
            pilihBaris(id, false);
            map.setView([d.latitude, d.longitude], 16);
            markerById[id].openPopup();
        }

        document.querySelectorAll('#tabel-data tbody tr').forEach(function(tr) {
            tr.addEventListener('click', function() {
                lihatDiPeta(parseInt(tr.getAttribute('data-id'), 10));
            });
        });

        /* Tampilkan / sembunyikan panel tabel */
        document.getElementById('toggle').addEventListener('click', function() {
            var panel = document.getElementById('panel');
            panel.classList.toggle('tutup');
            this.innerHTML = panel.classList.contains('tutup') ? 'Tampilkan ▲' : 'Sembunyikan ▼';
            setTimeout(function() {
                map.invalidateSize();
            }, 50);
        });

        /* ===== Kirim aksi ke server ===== */
        function kirim(data) {
            var fd = new FormData();
            for (var k in data) {
                fd.append(k, data[k]);
            }
            return fetch(window.location.pathname, {
                method: 'POST',
                body: fd
            }).then(function(r) {
                return r.json();
            });
        }

        var kolom = ['nama_objek', 'jenis', 'latitude', 'longitude', 'kelurahan', 'kecamatan', 'kota'];

        /* ===== EDIT ===== */
        function bukaEdit(id) {
            var d = cariData(id);
            document.getElementById('e-id').value = id;
            kolom.forEach(function(k) {
                document.getElementById('e-' + k).value = d[k] === null ? '' : d[k];
            });
            document.getElementById('modal-edit').classList.add('buka');
        }

        function tutupEdit() {
            document.getElementById('modal-edit').classList.remove('buka');
        }

        document.getElementById('btn-batal').addEventListener('click', tutupEdit);

        document.getElementById('btn-simpan').addEventListener('click', function() {
            var id = parseInt(document.getElementById('e-id').value, 10);
            var data = {
                aksi: 'edit',
                id: id
            };
            kolom.forEach(function(k) {
                data[k] = document.getElementById('e-' + k).value;
            });

            var lat = parseFloat(data.latitude),
                lng = parseFloat(data.longitude);
            if (isNaN(lat) || isNaN(lng)) {
                alert('Latitude dan Longitude harus berupa angka.');
                return;
            }

            kirim(data).then(function(res) {
                if (!res.sukses) {
                    alert('Gagal menyimpan: ' + res.pesan);
                    return;
                }
                /* Perbarui data, baris tabel, dan marker */
                var d = cariData(id);
                kolom.forEach(function(k) {
                    d[k] = data[k];
                });
                d.latitude = lat;
                d.longitude = lng;

                var tr = barisTabel(id);
                kolom.forEach(function(k) {
                    tr.querySelector('[data-k="' + k + '"]').textContent = d[k];
                });

                var m = markerById[id];
                m.setLatLng([lat, lng]);
                m.setPopupContent(popupAtribut(d));
                m.setTooltipContent(d.nama_objek);

                tutupEdit();
                alert(res.pesan);
            }).catch(function() {
                alert('Terjadi kesalahan koneksi ke server.');
            });
        });

        /* ===== DELETE ===== */
        function hapusData(id) {
            var d = cariData(id);
            if (!confirm('Hapus data "' + d.nama_objek + '"?')) return;

            kirim({
                aksi: 'hapus',
                id: id
            }).then(function(res) {
                if (!res.sukses) {
                    alert('Gagal menghapus: ' + res.pesan);
                    return;
                }
                layerBankSampah.removeLayer(markerById[id]);
                delete markerById[id];
                semuaData = semuaData.filter(function(x) {
                    return x.id !== id;
                });
                barisTabel(id).remove();
                document.getElementById('total').textContent = semuaData.length;
                alert(res.pesan);
            }).catch(function() {
                alert('Terjadi kesalahan koneksi ke server.');
            });
        }

        /* Tombol Edit & Delete di tabel */
        document.querySelectorAll('.btn-edit').forEach(function(b) {
            b.addEventListener('click', function(e) {
                e.stopPropagation();
                bukaEdit(parseInt(b.getAttribute('data-id'), 10));
            });
        });
        document.querySelectorAll('.btn-hapus').forEach(function(b) {
            b.addEventListener('click', function(e) {
                e.stopPropagation();
                hapusData(parseInt(b.getAttribute('data-id'), 10));
            });
        });
    </script>
</body>

</html>