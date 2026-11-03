<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

$pdo = db();
$id = (int)input('id');

$load = function () use ($pdo, $id) {
    $s = $pdo->prepare(
        'SELECT t.*, a.asset_name, a.asset_code, a.`condition` AS asset_condition, a.room_id, r.room_name, r.room_code
         FROM maintenance_tickets t
         JOIN facility_assets a ON a.id = t.facility_asset_id
         JOIN rooms r ON r.id = a.room_id WHERE t.id = ?'
    );
    $s->execute([$id]);
    return $s->fetch();
};

$ticket = $load();
if (!$ticket) {
    http_response_code(404);
    flash('error', 'Tiket tidak ditemukan.');
    redirect('/tickets.php');
}

/* ---------- Update status (admin / teknisi) ---------- */
if (is_post()) {
    csrf_verify();
    require_admin();

    $status   = (string)input('maintenance_status');
    $assigned = (string)input('assigned_to') ?: null;
    $notes    = (string)input('resolution_notes') ?: null;
    $priority = (string)input('priority');

    $errors = [];
    if (!in_array($status, STATUSES, true))     $errors[] = 'Status tidak valid.';
    if (!in_array($priority, PRIORITIES, true)) $errors[] = 'Prioritas tidak valid.';
    if ($status === 'Dalam Perbaikan' && !$assigned) $errors[] = 'Isi nama teknisi sebelum status "Dalam Perbaikan".';
    if (in_array($status, ['Selesai', 'Ditolak'], true) && !$notes) $errors[] = 'Catatan resolusi wajib diisi untuk status ini.';

    if ($errors) {
        flash('error', implode(' ', $errors));
        redirect('/ticket.php?id=' . $id);
    }

    $pdo->beginTransaction();
    try {
        $resolvedAt = $status === 'Selesai'
            ? ($ticket['resolved_at'] ?: date('Y-m-d H:i:s'))
            : null;
        $pdo->prepare(
            'UPDATE maintenance_tickets SET maintenance_status=?, assigned_to=?, resolution_notes=?, priority=?, resolved_at=? WHERE id=?'
        )->execute([$status, $assigned, $notes, $priority, $resolvedAt, $id]);

        // Logika bonus: tiket selesai -> kondisi aset otomatis "Baik"
        if ($status === 'Selesai' && $ticket['maintenance_status'] !== 'Selesai') {
            $pdo->prepare("UPDATE facility_assets SET `condition` = 'Baik' WHERE id = ?")->execute([$ticket['facility_asset_id']]);
        }
        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        throw $ex;
    }
    flash('success', 'Tiket diperbarui' . ($status === 'Selesai' ? ' dan kondisi aset dikembalikan ke "Baik".' : '.'));
    redirect('/ticket.php?id=' . $id);
}

/* ---------- Timeline ---------- */
$flow = ['Dilaporkan', 'Ditinjau', 'Dalam Perbaikan', 'Selesai'];
$cur  = $ticket['maintenance_status'];
$rejected = $cur === 'Ditolak';
$curIdx = array_search($cur, $flow, true);
$times = ['Dilaporkan' => $ticket['reported_at'], 'Selesai' => $ticket['resolved_at']];

$title = $ticket['ticket_number'];
$active = 'tickets';
$bodyClass = 'app';
require __DIR__ . '/../includes/header.php';
?>
<main class="container page">
  <a class="back" href="/tickets.php">← Semua tiket</a>
  <div class="page-head">
    <div><h1><?= e($ticket['ticket_number']) ?></h1><p>Dilaporkan <?= e(format_date($ticket['reported_at'], true)) ?> oleh <?= e($ticket['reporter_name']) ?></p></div>
    <div class="actions"><?= priority_badge($ticket['priority']) ?> <?= status_badge($cur) ?></div>
  </div>

  <div class="grid" style="grid-template-columns: 1.4fr 1fr; align-items:start">
    <div class="grid">
      <div class="card">
        <h3>Informasi tiket</h3>
        <dl class="kv" style="margin:0">
          <dt>Aset</dt><dd><a class="link" href="/asset.php?id=<?= $ticket['facility_asset_id'] ?>"><?= e($ticket['asset_name']) ?></a> (<?= e($ticket['asset_code']) ?>)</dd>
          <dt>Ruangan</dt><dd><a class="link" href="/room.php?id=<?= $ticket['room_id'] ?>"><?= e($ticket['room_name']) ?></a></dd>
          <dt>Kondisi aset</dt><dd><?= condition_badge($ticket['asset_condition']) ?></dd>
          <dt>Pelapor</dt><dd><?= avatar($ticket['reporter_name']) ?> <?= e($ticket['reporter_name']) ?> <span class="muted">· <?= e($ticket['reporter_role']) ?></span></dd>
          <dt>Deskripsi</dt><dd><?= nl2br(e($ticket['issue_description'])) ?></dd>
          <dt>Teknisi</dt><dd><?= $ticket['assigned_to'] ? e($ticket['assigned_to']) : '<span class="muted">Belum ditugaskan</span>' ?></dd>
          <dt>Selesai pada</dt><dd><?= e(format_date($ticket['resolved_at'], true)) ?></dd>
          <dt>Resolusi</dt><dd><?= $ticket['resolution_notes'] ? nl2br(e($ticket['resolution_notes'])) : '<span class="muted">-</span>' ?></dd>
        </dl>
        <?php if ($ticket['image_url']): ?>
          <h3 style="margin:22px 0 10px">Foto kerusakan</h3>
          <img class="photo" src="<?= e($ticket['image_url']) ?>" alt="Foto kerusakan">
        <?php endif; ?>
      </div>

      <?php if (is_admin()): ?>
      <div class="card">
        <h3>Perbarui tiket</h3>
        <form method="post">
          <?= csrf_field() ?>
          <div class="form-grid">
            <div class="field"><label>Status</label>
              <select class="input" name="maintenance_status"><?php foreach (STATUSES as $s): ?><option <?= $s === $cur ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>Prioritas</label>
              <select class="input" name="priority"><?php foreach (PRIORITIES as $p): ?><option <?= $p === $ticket['priority'] ? 'selected' : '' ?>><?= e($p) ?></option><?php endforeach; ?></select></div>
          </div>
          <div class="field"><label>Ditugaskan ke (teknisi)</label><input class="input" name="assigned_to" maxlength="100" value="<?= e($ticket['assigned_to']) ?>" placeholder="Nama teknisi / petugas"></div>
          <div class="field"><label>Catatan resolusi</label><textarea class="input" name="resolution_notes" placeholder="Apa yang dilakukan untuk memperbaiki?"><?= e($ticket['resolution_notes']) ?></textarea>
            <div class="hint">Wajib untuk status Selesai/Ditolak. Saat Selesai, kondisi aset otomatis menjadi “Baik”.</div></div>
          <button class="btn btn-dark" type="submit">Simpan perubahan</button>
        </form>
      </div>
      <?php endif; ?>
    </div>

    <div class="card">
      <h3>Status perbaikan</h3>
      <ul class="timeline">
        <?php foreach ($flow as $i => $step):
            $cls = '';
            if (!$rejected && $curIdx !== false) {
                $cls = $i < $curIdx ? 'done' : ($i === $curIdx ? ($cur === 'Selesai' ? 'done' : 'current') : '');
            } elseif ($rejected && $i === 0) {
                $cls = 'done';
            }
        ?>
          <li class="<?= $cls ?>">
            <span class="dot"><?= $cls === 'done' ? '✓' : '' ?></span>
            <b><?= e($step) ?></b>
            <small><?= isset($times[$step]) && $times[$step] && $cls ? e(format_date($times[$step], true)) : ($cls === 'current' ? 'Sedang berlangsung' : 'Menunggu') ?></small>
          </li>
        <?php endforeach; ?>
        <?php if ($rejected): ?>
          <li class="rejected"><span class="dot">✕</span><b>Ditolak</b><small><?= e($ticket['resolution_notes'] ?: 'Tiket tidak dilanjutkan.') ?></small></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</main>
<style>@media (max-width:960px){ main > .grid[style*="1.4fr"]{ grid-template-columns:1fr !important; } }</style>
<?php require __DIR__ . '/../includes/footer.php'; ?>
