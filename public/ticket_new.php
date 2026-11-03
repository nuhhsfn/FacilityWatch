<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

$pdo = db();
$errors = [];
$old = [
    'facility_asset_id' => (int)input('asset_id'),
    'reporter_name'     => '',
    'reporter_role'     => 'Guru',
    'issue_description' => '',
    'priority'          => 'Sedang',
];
$roomFilter = (int)input('room_id');

if (is_post()) {
    csrf_verify();
    $old = [
        'facility_asset_id' => (int)input('facility_asset_id'),
        'reporter_name'     => (string)input('reporter_name'),
        'reporter_role'     => (string)input('reporter_role'),
        'issue_description' => (string)input('issue_description'),
        'priority'          => (string)input('priority'),
    ];

    $exists = $pdo->prepare('SELECT COUNT(*) FROM facility_assets WHERE id = ?');
    $exists->execute([$old['facility_asset_id']]);
    if (!$exists->fetchColumn())                                  $errors['facility_asset_id'] = 'Pilih aset yang dilaporkan.';
    if (mb_strlen($old['reporter_name']) < 2)                     $errors['reporter_name'] = 'Nama pelapor minimal 2 karakter.';
    if (!in_array($old['reporter_role'], ROLES, true))            $errors['reporter_role'] = 'Peran tidak valid.';
    if (mb_strlen($old['issue_description']) < 10)                $errors['issue_description'] = 'Jelaskan masalah minimal 10 karakter.';
    if (!in_array($old['priority'], PRIORITIES, true))            $errors['priority'] = 'Prioritas tidak valid.';

    $image = null;
    if (!$errors) {
        try {
            $image = upload_image('image');
        } catch (RuntimeException $ex) {
            $errors['image'] = $ex->getMessage();
        }
    }

    if (!$errors) {
        $pdo->beginTransaction();
        try {
            $number = generate_ticket_number($pdo);
            $pdo->prepare(
                'INSERT INTO maintenance_tickets (ticket_number, facility_asset_id, reporter_name, reporter_role, issue_description, priority, image_url)
                 VALUES (?,?,?,?,?,?,?)'
            )->execute([$number, $old['facility_asset_id'], $old['reporter_name'], $old['reporter_role'], $old['issue_description'], $old['priority'], $image]);
            $newId = (int)$pdo->lastInsertId();
            $pdo->commit();
        } catch (Throwable $ex) {
            $pdo->rollBack();
            throw $ex;
        }
        flash('success', "Laporan diterima. Nomor tiket Anda: $number");
        redirect('/ticket.php?id=' . $newId);
    }
}

$assets = $pdo->query(
    'SELECT a.id, a.asset_code, a.asset_name, r.room_name, r.room_code, r.id AS rid
     FROM facility_assets a JOIN rooms r ON r.id = a.room_id ORDER BY r.room_code, a.asset_name'
)->fetchAll();
$grouped = [];
foreach ($assets as $a) {
    $grouped[$a['room_code'] . ' · ' . $a['room_name']][] = $a;
}

$title = 'Buat Tiket Baru';
$active = 'tickets';
$bodyClass = 'app';
require __DIR__ . '/../includes/header.php';

$err = fn(string $k) => isset($errors[$k]) ? '<div class="hint" style="color:#b42318">' . e($errors[$k]) . '</div>' : '';
?>
<main class="container page">
  <a class="back" href="/tickets.php">← Semua tiket</a>
  <div class="page-head"><div><h1>Laporkan kerusakan</h1><p>Isi formulir singkat ini. Petugas akan segera meninjau laporan Anda.</p></div></div>

  <div class="card" style="max-width:720px">
    <form method="post" enctype="multipart/form-data" novalidate>
      <?= csrf_field() ?>
      <div class="field">
        <label for="asset-filter">Aset yang bermasalah *</label>
        <input class="input" id="asset-filter" type="search" placeholder="Ketik untuk menyaring aset…" data-asset-filter style="margin-bottom:8px">
        <select class="input" name="facility_asset_id" data-asset-select required>
          <option value="">Pilih aset</option>
          <?php foreach ($grouped as $roomLabel => $items): ?>
            <optgroup label="<?= e($roomLabel) ?>">
              <?php foreach ($items as $a): ?>
                <option value="<?= $a['id'] ?>" <?= (int)$old['facility_asset_id'] === (int)$a['id'] ? 'selected' : '' ?>><?= e($a['asset_code'] . ' · ' . $a['asset_name']) ?></option>
              <?php endforeach; ?>
            </optgroup>
          <?php endforeach; ?>
        </select>
        <?= $err('facility_asset_id') ?>
      </div>

      <div class="form-grid">
        <div class="field"><label for="rn">Nama pelapor *</label><input class="input" id="rn" name="reporter_name" maxlength="100" required value="<?= e($old['reporter_name']) ?>"><?= $err('reporter_name') ?></div>
        <div class="field"><label for="rr">Peran *</label>
          <select class="input" id="rr" name="reporter_role"><?php foreach (ROLES as $r): ?><option <?= $r === $old['reporter_role'] ? 'selected' : '' ?>><?= e($r) ?></option><?php endforeach; ?></select><?= $err('reporter_role') ?></div>
      </div>

      <div class="field"><label for="desc">Deskripsi masalah *</label>
        <textarea class="input" id="desc" name="issue_description" required placeholder="Apa yang rusak? Sejak kapan? Apa dampaknya?"><?= e($old['issue_description']) ?></textarea><?= $err('issue_description') ?></div>

      <div class="form-grid">
        <div class="field"><label for="pr">Prioritas *</label>
          <select class="input" id="pr" name="priority"><?php foreach (PRIORITIES as $p): ?><option <?= $p === $old['priority'] ? 'selected' : '' ?>><?= e($p) ?></option><?php endforeach; ?></select>
          <div class="hint">Darurat = membahayakan keselamatan atau mengganggu kegiatan belajar.</div><?= $err('priority') ?></div>
        <div class="field"><label for="img">Foto kerusakan (opsional)</label>
          <input class="input" id="img" type="file" name="image" accept="image/jpeg,image/png,image/webp" data-preview="img-prev"><?= $err('image') ?>
          <img id="img-prev" class="photo" hidden alt="Pratinjau" style="margin-top:8px;max-height:140px"></div>
      </div>

      <div class="modal-foot" style="justify-content:flex-start;margin-top:12px">
        <button class="btn btn-dark" type="submit">Kirim laporan</button>
        <a class="btn" href="/tickets.php">Batal</a>
      </div>
    </form>
  </div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
