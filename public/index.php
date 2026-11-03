<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

$title = 'Pantau fasilitas sekolah';
$active = 'home';
require __DIR__ . '/../includes/header.php';

$logo = function (int $s = 24, string $fill = '#fff'): string {
    $out = '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 28 28" aria-hidden="true">';
    foreach ([[14,4],[7,7],[21,7],[4,14],[24,14],[7,21],[21,21],[14,24],[14,14]] as [$x, $y]) {
        $out .= '<circle cx="' . $x . '" cy="' . $y . '" r="2" fill="' . $fill . '"/>';
    }
    return $out . '</svg>';
};
?>

<!-- HERO -->
<section class="hero">
  <div class="float" style="left:9%;top:100px"><?= avatar('Rina Wulandari') ?></div>
  <div class="float" style="left:4%;top:190px"><?= avatar('Joko Santoso') ?></div>
  <div class="float" style="left:15%;top:175px"><?= avatar('Hendra K') ?></div>
  <div class="float" style="left:2%;top:290px"><div class="bubble">Proyektor sudah diperbaiki 🛠️</div></div>
  <div class="float" style="left:12%;top:370px"><?= avatar('Dimas Pratama', 'lg') ?></div>

  <div class="float" style="right:11%;top:100px"><?= avatar('Sari Dewi') ?></div>
  <div class="float" style="right:4%;top:185px"><?= avatar('Andi Saputra', 'lg') ?></div>
  <div class="float" style="right:2%;top:290px"><div class="bubble">Laporan diterima 👍</div></div>
  <div class="float" style="right:10%;top:370px"><?= avatar('Bu Maya', 'lg') ?></div>

  <div class="hero-inner">
    <div class="pill-tag"><b>F</b> Dipercaya warga sekolah</div>
    <h1>Dibangun sesuai cara sekolah Anda bekerja</h1>
    <p class="lead">Pantau ruangan dan aset, terima laporan kerusakan, dan lacak perbaikan sampai tuntas. Tanpa kertas, tanpa grup chat yang berantakan.</p>
    <div class="hero-cta">
      <a class="btn btn-dark" href="/ticket_new.php">Laporkan kerusakan <small>· 1 menit</small></a>
      <a class="btn" href="/dashboard.php">Lihat dashboard</a>
    </div>
  </div>
</section>

<!-- SHOWCASE -->
<div class="container">
  <div class="showcase">
    <div class="showcase-grid">
      <div class="mini-card">
        <?php foreach ([
            ['Rina Wulandari', 'Proyektor LCD', 'AST-003'],
            ['Dimas Pratama', 'Kursi Siswa', 'AST-002'],
            ['Hendra K', 'Router Wi-Fi', 'AST-009'],
            ['Sari Dewi', 'AC Split 2 PK', 'AST-004'],
        ] as [$n, $a, $c]): ?>
          <div class="row">
            <?= avatar($n) ?>
            <div class="grow"><b><?= e($n) ?></b><small><?= e($a) ?></small></div>
            <span class="code"><?= e($c) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="mini-card">
        <div class="muted" style="font-size:13px;margin-bottom:6px">Tiket selesai bulan ini</div>
        <div class="stat-big">87% <span class="chip-up">↑ 24%</span></div>
        <div class="row" style="margin-top:14px"><div class="grow"><b>Dilaporkan</b><small>24 tiket</small></div></div>
        <div class="row"><div class="grow"><b>Dalam perbaikan</b><small>9 tiket</small></div></div>
        <div class="row"><div class="grow"><b>Selesai</b><small>61 tiket</small></div></div>
      </div>
      <div class="mini-card">
        <div class="row"><span class="avatar" style="--h:0;background:#eee"></span><div class="grow"><div class="skel" style="width:60%;margin:0"></div></div></div>
        <div class="skel" style="width:90%"></div><div class="skel" style="width:70%"></div><div class="skel" style="width:40%"></div>
        <div class="skel" style="width:85%;margin-top:26px"></div><div class="skel" style="width:55%"></div>
      </div>
      <div class="glass-strip"></div><div class="glass-dots"></div>
      <div class="logo-orb"><?= $logo(26) ?></div>
    </div>
  </div>
</div>

<!-- 3 FITUR -->
<section class="section">
  <div class="container features">
    <div><div class="icon-dot">🏫</div><h4>Data ruangan &amp; aset rapi</h4><p>Setiap ruangan punya daftar aset lengkap dengan kategori, jumlah, dan kondisi terkini.</p></div>
    <div><div class="icon-dot">🛡️</div><h4>Lapor tanpa ribet</h4><p>Guru, siswa, dan staf cukup mengisi satu formulir singkat, lengkap dengan foto kerusakan.</p></div>
    <div><div class="icon-dot">🚀</div><h4>Lacak sampai selesai</h4><p>Status tiket transparan dari dilaporkan hingga diperbaiki. Kondisi aset ikut diperbarui otomatis.</p></div>
  </div>
</section>

<!-- AKURASI -->
<section class="section" style="padding-top:20px">
  <div class="container center">
    <h2 class="section-title">Lihat dengan jelas<br>apa yang perlu diperbaiki</h2>
    <p class="section-sub">Data kondisi aset yang selalu terbaru membuat petugas sarpras tahu persis di mana harus mulai bekerja.</p>
    <div class="accuracy" style="text-align:left">
      <div>
        <h3>Prioritaskan berdasarkan data. Jangan menebak, ketahui persis apa yang mendesak.</h3>
        <div class="metric"><div class="n">98%</div><div><b>Aset terdata</b><span>Kode unik, kategori, lokasi ruangan</span></div></div>
        <div class="metric"><div class="n">24%</div><div><b>Butuh tindak lanjut</b><span>Kondisi rusak atau perlu perbaikan</span></div></div>
      </div>
      <div class="tile-board" aria-hidden="true">
        <?php foreach (['🪑','❄️','💡','🖥️','🚰','📽️','📶','🧰','🪟','🔌','🚪','📚'] as $i => $emo): ?>
          <div class="tile<?= $i === 5 ? ' hot' : '' ?>"><?= $i === 5 ? $logo(30) : $emo ?></div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<!-- ALUR -->
<section class="section" id="alur">
  <div class="container center">
    <h2 class="section-title">Satu alur sederhana<br>untuk semua perbaikan</h2>
    <p class="section-sub">Prosesnya berputar pada siklus yang jelas: temukan masalah, tinjau, lalu tuntaskan perbaikan.</p>
    <div class="flow">
      <div class="flow-card">
        <div class="flow-visual">
          <div class="chip"><?= avatar('Rina') ?> Rina melaporkan proyektor rusak</div>
          <div class="chip"><?= avatar('Dimas') ?> Dimas melaporkan kursi patah</div>
        </div>
        <h4>Laporkan kerusakan</h4><p>Pilih aset, jelaskan masalah, unggah foto. Selesai dalam hitungan menit.</p>
        <a href="/ticket_new.php">Buat tiket →</a>
      </div>
      <div class="flow-card">
        <div class="flow-visual">
          <div class="chip"><?= status_badge('Ditinjau') ?> Router Wi-Fi · Darurat</div>
          <div class="chip"><?= status_badge('Dilaporkan') ?> Kran wastafel · Tinggi</div>
          <div class="chip"><?= status_badge('Ditolak') ?> Lampu toilet · Duplikat</div>
        </div>
        <h4>Tinjau &amp; tugaskan</h4><p>Admin menilai prioritas dan menugaskan teknisi yang tepat.</p>
        <a href="/tickets.php">Lihat tiket →</a>
      </div>
      <div class="flow-card">
        <div class="flow-visual">
          <div class="chip"><?= status_badge('Dalam Perbaikan') ?> Pak Joko sedang menangani</div>
          <div class="chip"><?= status_badge('Selesai') ?> AC sudah dingin kembali</div>
        </div>
        <h4>Perbaiki &amp; tutup</h4><p>Catatan resolusi tersimpan dan kondisi aset otomatis kembali “Baik”.</p>
        <a href="/dashboard.php">Pantau progres →</a>
      </div>
    </div>
  </div>
</section>

<!-- TESTIMONI -->
<section class="section" style="padding-top:20px">
  <div class="container center">
    <h2 class="section-title">Rencanakan perawatan dan<br>lacak pekerjaan dengan mudah</h2>
    <p class="section-sub">Kelola pemeliharaan dengan alat yang membantu menyusun prioritas dan berkolaborasi.</p>
    <div class="testimonial">
      <div class="left">
        <div style="font-weight:500">✱ SMA Nusantara</div>
        <blockquote>“Sejak memakai FacilityWatch, laporan kerusakan tidak lagi hilang di grup WhatsApp. Kami tahu apa yang sedang dikerjakan dan siapa yang menanganinya.”</blockquote>
        <div class="nums">
          <div><b>120+</b><span>Aset terpantau</span></div>
          <div><b>340+</b><span>Tiket diselesaikan</span></div>
        </div>
      </div>
      <div class="right">
        <div><?= avatar('Budi Hartono', 'lg') ?><br><b>Budi Hartono</b><div class="muted" style="font-size:12px">Waka Sarana &amp; Prasarana</div></div>
      </div>
    </div>
  </div>
</section>

<!-- CARA KERJA -->
<section class="section">
  <div class="container center">
    <h2 class="section-title">Begini cara kerjanya</h2>
    <p class="section-sub">Tiga langkah mudah untuk mulai memantau fasilitas sekolah Anda.</p>
    <div class="steps">
      <div class="step"><div class="icon-dot">📥</div><small>Langkah 1</small><h4>Impor data ruangan &amp; aset</h4><p>Admin menambahkan ruangan dan aset beserta kondisinya.</p></div>
      <div class="step"><div class="icon-dot">👥</div><small>Langkah 2</small><h4>Warga sekolah ikut melapor</h4><p>Guru, siswa, dan staf melaporkan masalah langsung dari ponsel.</p></div>
      <div class="step"><div class="icon-dot">📊</div><small>Langkah 3</small><h4>Pantau lewat dashboard</h4><p>Lihat statistik tiket dan kondisi aset secara real-time.</p></div>
    </div>
  </div>
</section>

<!-- CTA -->
<section class="section" style="padding-top:0">
  <div class="container">
    <div class="cta">
      <div class="visual">
        <div class="todo"><span class="ck"></span><div>Ganti lampu toilet putra<small>Hari ini · Tugas</small></div></div>
        <div class="todo"><span class="ck done"></span><div>Servis AC kelas X IPA 1<small>Selesai · 31 Okt</small></div></div>
        <div class="todo"><span class="ck done"></span><div>Cek router lab komputer<small>Selesai · 30 Okt</small></div></div>
      </div>
      <div class="text">
        <h2>Ruang kerja pemeliharaan sekolah Anda</h2>
        <p>Rencanakan tugas, tetapkan penanggung jawab, dan lacak kemajuan dalam satu platform sederhana.</p>
        <div class="hero-cta" style="justify-content:flex-start">
          <a class="btn btn-dark" href="/ticket_new.php">Laporkan kerusakan</a>
          <a class="btn" href="/login.php">Masuk Admin</a>
        </div>
        <div class="ratings"><span>★ 4.9 Kepuasan pengguna</span><span>✔ Gratis &amp; open source</span></div>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
