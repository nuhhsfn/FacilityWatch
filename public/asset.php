<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

$pdo = db();
$id = (int)input('id');

$stmt = $pdo->prepare('SELECT a.*, r.room_name, r.room_code, r.building FROM facility_assets a JOIN rooms r ON r.id = a.room_id WHERE a.id = ?');
$stmt->execute([$id]);
$asset = $stmt->fetch();
if (!$asset) {
    http_response_code(404);
    flash('error', 'Aset tidak ditemukan.');
    redirect('/assets.php');
}

$stmt = $pdo->prepare('SELECT * FROM maintenance_tickets WHERE facility_asset_id = ? ORDER BY reported_at DESC, id DESC');
$stmt->execute([$id]);
$tickets = $stmt->fetchAll();

$title = $asset['asset_name'];
$active = 'assets';
$bodyClass = 'app';
require __DIR__ . '/../includes/header.php';
?>
<main class="container page">
  <a class="back" href="/assets.php">← Semua aset</a>
  <div class="page-head">
    <div><h1><?= e($asset['asset_name']) ?></h1><p><?= e($asset['asset_code']) ?> · <?= e($asset['category']) ?></p></div>
    <div class="actions"><a class="btn btn-dark" href="/ticket_new.php?asset_id=<?= $id ?>">Laporkan kerusakan</a></div>
  </div>

  <div class="grid grid-2">
    <div class="card">
      <h3>Informasi aset</h3>
      <dl class="kv" style="margin:0">
        <dt>Kondisi</dt><dd><?= condition_badge($asset['condition']) ?></dd>
        <dt>Ruangan</dt><dd><a class="link" href="/room.php?id=<?= $asset['room_id'] ?>"><?= e($asset['room_name']) ?></a> (<?= e($asset['room_code']) ?>)</dd>
        <dt>Gedung</dt><dd><?= e($asset['building']) ?></dd>
        <dt>Jumlah</dt><dd><?= (int)$asset['quantity'] ?></dd>
        <dt>Catatan</dt><dd><?= $asset['notes'] ? nl2br(e($asset['notes'])) : '<span class="muted">-</span>' ?></dd>
        <dt>Diperbarui</dt><dd><?= e(format_date($asset['updated_at'], true)) ?></dd>
      </dl>
    </div>
    <div class="card">
      <h3>Foto</h3>
      <?php if ($asset['image_url']): ?>
        <img class="photo" src="<?= e($asset['image_url']) ?>" alt="Foto <?= e($asset['asset_name']) ?>">
      <?php else: ?>
        <div class="empty" style="padding:30px"><div class="big">🖼️</div>Belum ada foto.</div>
      <?php endif; ?>
    </div>
  </div>

  <h3 style="font-size:18px;margin:28px 0 12px">Riwayat tiket</h3>
  <div class="table-wrap">
    <?php if (!$tickets): ?>
      <div class="empty"><div class="big">✅</div><b>Belum ada riwayat</b>Aset ini belum pernah dilaporkan bermasalah.</div>
    <?php else: ?>
    <table>
      <thead><tr><th>No. Tiket</th><th>Masalah</th><th>Prioritas</th><th>Status</th><th>Tanggal</th></tr></thead>
      <tbody>
      <?php foreach ($tickets as $t): ?>
        <tr>
          <td><a class="link" href="/ticket.php?id=<?= $t['id'] ?>"><?= e($t['ticket_number']) ?></a></td>
          <td style="max-width:320px"><?= e(mb_strimwidth($t['issue_description'], 0, 90, '…')) ?></td>
          <td><?= priority_badge($t['priority']) ?></td>
          <td><?= status_badge($t['maintenance_status']) ?></td>
          <td><?= e(format_date($t['reported_at'])) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
