<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

$pdo = db();
$id = (int)input('id');

$stmt = $pdo->prepare('SELECT * FROM rooms WHERE id = ?');
$stmt->execute([$id]);
$room = $stmt->fetch();
if (!$room) {
    http_response_code(404);
    flash('error', 'Ruangan tidak ditemukan.');
    redirect('/rooms.php');
}

$stmt = $pdo->prepare(
    "SELECT a.*, (SELECT COUNT(*) FROM maintenance_tickets t WHERE t.facility_asset_id = a.id
                  AND t.maintenance_status IN ('Dilaporkan','Ditinjau','Dalam Perbaikan')) AS open_tickets
     FROM facility_assets a WHERE a.room_id = ? ORDER BY a.asset_name"
);
$stmt->execute([$id]);
$assets = $stmt->fetchAll();

$title = $room['room_name'];
$active = 'rooms';
$bodyClass = 'app';
require __DIR__ . '/../includes/header.php';
?>
<main class="container page">
  <a class="back" href="/rooms.php">← Semua ruangan</a>
  <div class="page-head">
    <div><h1><?= e($room['room_name']) ?></h1><p><?= e($room['room_code']) ?> · <?= e($room['building']) ?>, lantai <?= (int)$room['floor'] ?></p></div>
    <div class="actions">
      <?php if (is_admin()): ?><a class="btn" href="/assets.php?room_id=<?= $id ?>&new=1">+ Tambah aset</a><?php endif; ?>
      <a class="btn btn-dark" href="/ticket_new.php?room_id=<?= $id ?>">Laporkan kerusakan</a>
    </div>
  </div>

  <div class="card" style="margin-bottom:16px">
    <dl class="kv" style="margin:0">
      <dt>Kode ruangan</dt><dd><?= e($room['room_code']) ?></dd>
      <dt>Gedung / lantai</dt><dd><?= e($room['building']) ?> · lantai <?= (int)$room['floor'] ?></dd>
      <dt>Jumlah aset</dt><dd><?= count($assets) ?> jenis</dd>
      <dt>Deskripsi</dt><dd><?= $room['description'] ? nl2br(e($room['description'])) : '<span class="muted">-</span>' ?></dd>
    </dl>
  </div>

  <h3 style="font-size:18px;margin:26px 0 12px">Aset dalam ruangan</h3>
  <div class="table-wrap">
    <?php if (!$assets): ?>
      <div class="empty"><div class="big">📦</div><b>Belum ada aset</b>Ruangan ini belum memiliki aset terdata.</div>
    <?php else: ?>
    <table>
      <thead><tr><th>Kode</th><th>Nama</th><th>Kategori</th><th>Jumlah</th><th>Kondisi</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($assets as $a): ?>
        <tr>
          <td><?= e($a['asset_code']) ?></td>
          <td><a class="link" href="/asset.php?id=<?= $a['id'] ?>"><?= e($a['asset_name']) ?></a>
            <?php if ($a['open_tickets']): ?><span class="sub"><?= (int)$a['open_tickets'] ?> tiket aktif</span><?php endif; ?></td>
          <td><?= e($a['category']) ?></td>
          <td><?= (int)$a['quantity'] ?></td>
          <td><?= condition_badge($a['condition']) ?></td>
          <td class="actions-col"><a class="btn btn-sm" href="/ticket_new.php?asset_id=<?= $a['id'] ?>">Laporkan</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
