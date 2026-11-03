<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

$pdo = db();

if (is_post()) {
    csrf_verify();
    require_admin();
    if (input('action') === 'delete') {
        $pdo->prepare('DELETE FROM maintenance_tickets WHERE id = ?')->execute([(int)input('id')]);
        flash('success', 'Tiket dihapus.');
    }
    redirect('/tickets.php');
}

$q = (string)input('q'); $status = (string)input('status'); $priority = (string)input('priority'); $assetId = (int)input('asset_id');

$where = []; $params = [];
if ($q !== '') {
    $where[] = '(t.ticket_number LIKE :q OR t.reporter_name LIKE :q OR t.issue_description LIKE :q OR a.asset_name LIKE :q)';
    $params[':q'] = "%$q%";
}
if (in_array($status, STATUSES, true))     { $where[] = 't.maintenance_status = :s'; $params[':s'] = $status; }
if (in_array($priority, PRIORITIES, true)) { $where[] = 't.priority = :p'; $params[':p'] = $priority; }
if ($assetId)                              { $where[] = 't.facility_asset_id = :a'; $params[':a'] = $assetId; }
$w = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$cnt = $pdo->prepare("SELECT COUNT(*) FROM maintenance_tickets t JOIN facility_assets a ON a.id = t.facility_asset_id $w");
$cnt->execute($params);
$pg = paginate((int)$cnt->fetchColumn());

$stmt = $pdo->prepare(
    "SELECT t.*, a.asset_name, a.asset_code FROM maintenance_tickets t
     JOIN facility_assets a ON a.id = t.facility_asset_id $w
     ORDER BY t.reported_at DESC, t.id DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}"
);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

$title = 'Tiket';
$active = 'tickets';
$bodyClass = 'app';
require __DIR__ . '/../includes/header.php';
?>
<main class="container page">
  <div class="page-head">
    <div><h1>Tiket pemeliharaan</h1><p>Pantau laporan kerusakan dan status perbaikannya.</p></div>
    <div class="actions"><a class="btn btn-dark" href="/ticket_new.php">+ Buat tiket baru</a></div>
  </div>

  <div class="toolbar">
    <form method="get">
      <?php if ($assetId): ?><input type="hidden" name="asset_id" value="<?= $assetId ?>"><?php endif; ?>
      <input class="input search" type="search" name="q" placeholder="Cari nomor tiket, pelapor, aset…" value="<?= e($q) ?>">
      <select class="input" name="status" onchange="this.form.submit()">
        <option value="">Semua status</option>
        <?php foreach (STATUSES as $s): ?><option <?= $s === $status ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
      </select>
      <select class="input" name="priority" onchange="this.form.submit()">
        <option value="">Semua prioritas</option>
        <?php foreach (PRIORITIES as $p): ?><option <?= $p === $priority ? 'selected' : '' ?>><?= e($p) ?></option><?php endforeach; ?>
      </select>
      <button class="btn" type="submit">Cari</button>
    </form>
  </div>

  <div class="table-wrap">
    <?php if (!$tickets): ?>
      <div class="empty"><div class="big">🎫</div><b>Tiket tidak ditemukan</b>Belum ada laporan yang cocok dengan filter.</div>
    <?php else: ?>
    <table>
      <thead><tr><th>No. Tiket</th><th>Aset</th><th>Pelapor</th><th>Prioritas</th><th>Status</th><th>Tanggal</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($tickets as $t): ?>
        <tr>
          <td><a class="link" href="/ticket.php?id=<?= $t['id'] ?>"><?= e($t['ticket_number']) ?></a></td>
          <td><?= e($t['asset_name']) ?><span class="sub"><?= e($t['asset_code']) ?></span></td>
          <td><?= e($t['reporter_name']) ?><span class="sub"><?= e($t['reporter_role']) ?></span></td>
          <td><?= priority_badge($t['priority']) ?></td>
          <td><?= status_badge($t['maintenance_status']) ?></td>
          <td><?= e(format_date($t['reported_at'])) ?></td>
          <td class="actions-col">
            <a class="btn btn-sm" href="/ticket.php?id=<?= $t['id'] ?>">Detail</a>
            <?php if (is_admin()): ?>
              <form method="post" data-confirm="Hapus tiket <?= e($t['ticket_number']) ?>?">
                <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $t['id'] ?>">
                <button class="btn btn-sm btn-danger" type="submit">Hapus</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
  <?= render_pagination($pg) ?>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
