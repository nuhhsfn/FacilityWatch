<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

$pdo = db();

/* ---------- Aksi tulis (admin) ---------- */
if (is_post()) {
    csrf_verify();
    require_admin();
    $action = (string)input('action');

    try {
        if ($action === 'delete') {
            $pdo->prepare('DELETE FROM rooms WHERE id = ?')->execute([(int)input('id')]);
            flash('success', 'Ruangan dihapus.');
        } else {
            $data = [
                'room_code'   => strtoupper((string)input('room_code')),
                'room_name'   => (string)input('room_name'),
                'floor'       => (int)input('floor', 1),
                'building'    => (string)input('building'),
                'description' => (string)input('description') ?: null,
            ];
            if ($data['room_code'] === '' || $data['room_name'] === '' || $data['building'] === '') {
                throw new InvalidArgumentException('Kode, nama, dan gedung wajib diisi.');
            }
            if ($action === 'create') {
                $pdo->prepare('INSERT INTO rooms (room_code, room_name, floor, building, description) VALUES (:room_code,:room_name,:floor,:building,:description)')->execute($data);
                flash('success', 'Ruangan ditambahkan.');
            } elseif ($action === 'update') {
                $data['id'] = (int)input('id');
                $pdo->prepare('UPDATE rooms SET room_code=:room_code, room_name=:room_name, floor=:floor, building=:building, description=:description WHERE id=:id')->execute($data);
                flash('success', 'Ruangan diperbarui.');
            }
        }
    } catch (InvalidArgumentException $ex) {
        flash('error', $ex->getMessage());
    } catch (PDOException $ex) {
        if ($ex->getCode() === '23000') {
            flash('error', $action === 'delete'
                ? 'Ruangan tidak bisa dihapus karena masih memiliki aset.'
                : 'Kode ruangan sudah dipakai.');
        } else {
            throw $ex;
        }
    }
    redirect('/rooms.php');
}

/* ---------- Daftar ---------- */
$q        = (string)input('q');
$building = (string)input('building');

$where = []; $params = [];
if ($q !== '') {
    $where[] = '(r.room_code LIKE :q OR r.room_name LIKE :q)';
    $params[':q'] = "%$q%";
}
if ($building !== '') {
    $where[] = 'r.building = :b';
    $params[':b'] = $building;
}
$w = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$cnt = $pdo->prepare("SELECT COUNT(*) FROM rooms r $w");
$cnt->execute($params);
$pg = paginate((int)$cnt->fetchColumn());

$stmt = $pdo->prepare(
    "SELECT r.*, (SELECT COUNT(*) FROM facility_assets a WHERE a.room_id = r.id) AS asset_count
     FROM rooms r $w ORDER BY r.building, r.floor, r.room_code
     LIMIT {$pg['per_page']} OFFSET {$pg['offset']}"
);
$stmt->execute($params);
$rooms = $stmt->fetchAll();
$buildings = $pdo->query('SELECT DISTINCT building FROM rooms ORDER BY building')->fetchAll(PDO::FETCH_COLUMN);

$title = 'Ruangan';
$active = 'rooms';
$bodyClass = 'app';
require __DIR__ . '/../includes/header.php';
?>
<main class="container page">
  <div class="page-head">
    <div><h1>Ruangan</h1><p>Daftar ruangan beserta jumlah aset di dalamnya.</p></div>
    <?php if (is_admin()): ?>
      <div class="actions"><button class="btn btn-dark" data-modal-open="room-modal" data-auto>+ Tambah ruangan</button></div>
    <?php endif; ?>
  </div>

  <div class="toolbar">
    <form method="get">
      <input class="input search" type="search" name="q" placeholder="Cari kode atau nama ruangan…" value="<?= e($q) ?>">
      <select class="input" name="building" onchange="this.form.submit()">
        <option value="">Semua gedung</option>
        <?php foreach ($buildings as $b): ?><option <?= $b === $building ? 'selected' : '' ?>><?= e($b) ?></option><?php endforeach; ?>
      </select>
      <button class="btn" type="submit">Cari</button>
    </form>
  </div>

  <div class="table-wrap">
    <?php if (!$rooms): ?>
      <div class="empty"><div class="big">🏫</div><b>Ruangan tidak ditemukan</b>Coba ubah kata kunci atau filter.</div>
    <?php else: ?>
    <table>
      <thead><tr><th>Kode</th><th>Nama</th><th>Lantai</th><th>Gedung</th><th>Jumlah Aset</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rooms as $r): ?>
        <tr>
          <td><b style="font-weight:500"><?= e($r['room_code']) ?></b></td>
          <td><a class="link" href="/room.php?id=<?= $r['id'] ?>"><?= e($r['room_name']) ?></a></td>
          <td><?= (int)$r['floor'] ?></td>
          <td><?= e($r['building']) ?></td>
          <td><?= (int)$r['asset_count'] ?></td>
          <td class="actions-col">
            <a class="btn btn-sm" href="/room.php?id=<?= $r['id'] ?>">Detail</a>
            <?php if (is_admin()): ?>
              <button class="btn btn-sm" data-modal-open="room-modal" data-edit="<?= e(json_encode($r)) ?>">Edit</button>
              <form method="post" data-confirm="Hapus ruangan <?= e($r['room_name']) ?>?">
                <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>">
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

<?php if (is_admin()): ?>
<div class="modal" id="room-modal" role="dialog" aria-modal="true">
  <div class="modal-box">
    <div class="modal-head"><h3 data-modal-title data-create="Tambah ruangan" data-edit="Edit ruangan">Tambah ruangan</h3><button class="modal-x" data-modal-close type="button" aria-label="Tutup">×</button></div>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="create"><input type="hidden" name="id" value="">
      <div class="form-grid">
        <div class="field"><label>Kode ruangan *</label><input class="input" name="room_code" maxlength="20" required></div>
        <div class="field"><label>Nama ruangan *</label><input class="input" name="room_name" maxlength="100" required></div>
        <div class="field"><label>Lantai</label><input class="input" type="number" name="floor" value="1" min="0"></div>
        <div class="field"><label>Gedung *</label><input class="input" name="building" maxlength="100" required list="building-list"><datalist id="building-list"><?php foreach ($buildings as $b): ?><option value="<?= e($b) ?>"><?php endforeach; ?></datalist></div>
      </div>
      <div class="field"><label>Deskripsi</label><textarea class="input" name="description"></textarea></div>
      <div class="modal-foot"><button class="btn" type="button" data-modal-close>Batal</button><button class="btn btn-dark" type="submit">Simpan</button></div>
    </form>
  </div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
