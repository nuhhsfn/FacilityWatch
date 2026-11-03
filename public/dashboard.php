<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

$pdo = db();

$totalAssets  = (int)$pdo->query('SELECT COUNT(*) FROM facility_assets')->fetchColumn();
$totalTickets = (int)$pdo->query('SELECT COUNT(*) FROM maintenance_tickets')->fetchColumn();
$openTickets  = (int)$pdo->query("SELECT COUNT(*) FROM maintenance_tickets WHERE maintenance_status IN ('Dilaporkan','Ditinjau','Dalam Perbaikan')")->fetchColumn();
$brokenAssets = (int)$pdo->query("SELECT COUNT(*) FROM facility_assets WHERE `condition` = 'Rusak'")->fetchColumn();

$byStatus = array_fill_keys(STATUSES, 0);
foreach ($pdo->query('SELECT maintenance_status s, COUNT(*) c FROM maintenance_tickets GROUP BY maintenance_status') as $r) {
    $byStatus[$r['s']] = (int)$r['c'];
}
$byCondition = array_fill_keys(CONDITIONS, 0);
foreach ($pdo->query('SELECT `condition` k, COUNT(*) c FROM facility_assets GROUP BY `condition`') as $r) {
    $byCondition[$r['k']] = (int)$r['c'];
}

$recent = $pdo->query(
    'SELECT t.*, a.asset_name, a.asset_code FROM maintenance_tickets t
     JOIN facility_assets a ON a.id = t.facility_asset_id
     ORDER BY t.reported_at DESC, t.id DESC LIMIT 5'
)->fetchAll();

$statusColors = ['Dilaporkan' => '#2e90fa', 'Ditinjau' => '#7a5af8', 'Dalam Perbaikan' => '#f79009', 'Selesai' => '#12b76a', 'Ditolak' => '#98a2b3'];
$condColors   = ['Baik' => '#12b76a', 'Perlu Perbaikan' => '#fdb022', 'Rusak' => '#f04438'];
$maxBar = max(1, ...array_values($byStatus));

// conic-gradient untuk pie
$stops = []; $acc = 0.0; $sum = max(1, array_sum($byCondition));
foreach ($byCondition as $k => $v) {
    $from = $acc / $sum * 100; $acc += $v; $to = $acc / $sum * 100;
    $stops[] = $condColors[$k] . ' ' . round($from, 2) . '% ' . round($to, 2) . '%';
}
$pie = array_sum($byCondition) ? implode(',', $stops) : '#eee 0 100%';

$title = 'Dashboard';
$active = 'dashboard';
$bodyClass = 'app';
require __DIR__ . '/../includes/header.php';
?>
<main class="container page">
  <div class="page-head">
    <div><h1>Dashboard</h1><p>Ringkasan kondisi sarana &amp; prasarana sekolah.</p></div>
    <div class="actions"><a class="btn btn-dark" href="/tickets/new.php">+ Laporkan kerusakan</a></div>
  </div>

  <section class="grid grid-4">
    <div class="card stat"><div class="label"><span class="ico">📦</span>Total Aset</div><div class="value"><?= $totalAssets ?></div><div class="hint">item terdata</div></div>
    <div class="card stat"><div class="label"><span class="ico">🎫</span>Total Tiket</div><div class="value"><?= $totalTickets ?></div><div class="hint">sejak awal</div></div>
    <div class="card stat"><div class="label"><span class="ico">🔔</span>Tiket Open</div><div class="value"><?= $openTickets ?></div><div class="hint">belum selesai</div></div>
    <div class="card stat"><div class="label"><span class="ico">⚠️</span>Aset Rusak</div><div class="value"><?= $brokenAssets ?></div><div class="hint">perlu penanganan</div></div>
  </section>

  <section class="grid grid-2" style="margin-top:16px">
    <div class="card">
      <h3>Tiket per status</h3>
      <div class="bars">
        <?php foreach ($byStatus as $s => $n): ?>
          <div class="bar">
            <span class="n"><?= $n ?></span>
            <div class="col" style="height:<?= round($n / $maxBar * 140) ?>px;background:<?= $statusColors[$s] ?>"></div>
            <span class="l"><?= e($s) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="card">
      <h3>Aset per kondisi</h3>
      <div class="pie-wrap">
        <div class="pie" style="background:conic-gradient(<?= e($pie) ?>)">
          <div class="pie-center"><div><b><?= $totalAssets ?></b><span>aset</span></div></div>
        </div>
        <div class="legend">
          <?php foreach ($byCondition as $k => $v): ?>
            <div><i style="background:<?= $condColors[$k] ?>"></i><?= e($k) ?> · <b><?= $v ?></b></div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </section>

  <section style="margin-top:16px">
    <div class="page-head" style="margin:22px 0 12px">
      <h3 style="font-size:18px">Tiket terbaru</h3>
      <a class="link" href="/tickets.php" style="font-size:13px">Lihat semua →</a>
    </div>
    <div class="table-wrap">
      <?php if (!$recent): ?>
        <div class="empty"><div class="big">🎫</div><b>Belum ada tiket</b>Laporan kerusakan akan muncul di sini.</div>
      <?php else: ?>
      <table>
        <thead><tr><th>No. Tiket</th><th>Aset</th><th>Pelapor</th><th>Prioritas</th><th>Status</th><th>Tanggal</th></tr></thead>
        <tbody>
        <?php foreach ($recent as $t): ?>
          <tr>
            <td><a class="link" href="/ticket.php?id=<?= $t['id'] ?>"><?= e($t['ticket_number']) ?></a></td>
            <td><?= e($t['asset_name']) ?><span class="sub"><?= e($t['asset_code']) ?></span></td>
            <td><?= e($t['reporter_name']) ?><span class="sub"><?= e($t['reporter_role']) ?></span></td>
            <td><?= priority_badge($t['priority']) ?></td>
            <td><?= status_badge($t['maintenance_status']) ?></td>
            <td><?= e(format_date($t['reported_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
