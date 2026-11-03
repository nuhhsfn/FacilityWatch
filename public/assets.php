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
            $pdo->prepare('DELETE FROM facility_assets WHERE id = ?')->execute([(int)input('id')]);
            flash('success', 'Aset dihapus.');
        } else {
            $data = [
                'asset_code' => strtoupper((string)input('asset_code')),
                'asset_name' => (string)input('asset_name'),
                'category'   => (string)input('category'),
                'room_id'    => (int)input('room_id'),
                'condition'  => (string)input('condition'),
                'quantity'   => max(1, (int)input('quantity', 1)),
                'notes'      => (string)input('notes') ?: null,
            ];
            if ($data['asset_code'] === '' || $data['asset_name'] === '' || !$data['room_id']) {
                throw new InvalidArgumentException('Kode, nama, dan ruangan wajib diisi.');
            }
            if (!in_array($data['category'], CATEGORIES, true) || !in_array($data['condition'], CONDITIONS, true)) {
                throw new InvalidArgumentException('Kategori atau kondisi tidak valid.');
            }
            $image = upload_image('image');

            if ($action === 'create') {
                $data['image_url'] = $image;
                $pdo->prepare('INSERT INTO facility_assets (asset_code, asset_name, category, room_id, `condition`, quantity, notes, image_url)
                               VALUES (:asset_code,:asset_name,:category,:room_id,:condition,:quantity,:notes,:image_url)')->execute($data);
                flash('success', 'Aset ditambahkan.');
            } elseif ($action === 'update') {
                $data['id'] = (int)input('id');
                $sql = 'UPDATE facility_assets SET asset_code=:asset_code, asset_name=:asset_name, category=:category, room_id=:room_id,
                        `condition`=:condition, quantity=:quantity, notes=:notes';
                if ($image) {
                    $sql .= ', image_url=:image_url';
                    $data['image_url'] = $image;
                }
                $pdo->prepare($sql . ' WHERE id=:id')->execute($data);
                flash('success', 'Aset diperbarui.');
            }
        }
    } catch (PDOException $ex) {
        // PDOException turunan RuntimeException, jadi harus ditangkap lebih dulu agar pesan SQL tidak bocor
        if ($ex->getCode() === '23000') {
            flash('error', $action === 'delete'
                ? 'Aset tidak bisa dihapus karena masih memiliki riwayat tiket.'
                : 'Kode aset sudah dipakai.');
        } else {
            throw $ex;
        }
    } catch (InvalidArgumentException | RuntimeException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect('/assets.php');
}

/* ---------- Daftar ---------- */
$q = (string)input('q'); $roomId = (int)input('room_id'); $cat = (string)input('category'); $cond = (string)input('condition');

$where = []; $params = [];
if ($q !== '')      { $where[] = '(a.asset_code LIKE :q OR a.asset_name LIKE :q)'; $params[':q'] = "%$q%"; }
if ($roomId)        { $where[] = 'a.room_id = :room'; $params[':room'] = $roomId; }
if (in_array($cat, CATEGORIES, true))   { $where[] = 'a.category = :cat'; $params[':cat'] = $cat; }
if (in_array($cond, CONDITIONS, true))  { $where[] = 'a.`condition` = :cond'; $params[':cond'] = $cond; }
$w = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$cnt = $pdo->prepare("SELECT COUNT(*) FROM facility_assets a $w");
$cnt->execute($params);
$pg = paginate((int)$cnt->fetchColumn());

$stmt = $pdo->prepare(
    "SELECT a.*, r.room_name, r.room_code FROM facility_assets a
     JOIN rooms r ON r.id = a.room_id $w
     ORDER BY a.asset_code LIMIT {$pg['per_page']} OFFSET {$pg['offset']}"
);
$stmt->execute($params);
$assets = $stmt->fetchAll();
$rooms = $pdo->query('SELECT id, room_code, room_name FROM rooms ORDER BY room_code')->fetchAll();

$title = 'Aset';
$active = 'assets';
$bodyClass = 'app';
require __DIR__ . '/../includes/header.php';
?>
<main class="container page">
  <div class="page-head">
    <div><h1>Aset &amp; Fasilitas</h1><p>Seluruh aset sekolah beserta kondisi terkini.</p></div>
    <?php if (is_admin()): ?>
      <div class="actions"><button class="btn btn-dark" data-modal-open="asset-modal" data-auto>+ Tambah aset</button></div>
    <?php endif; ?>
  </div>

  <div class="toolbar">
    <form method="get">
      <input class="input search" type="search" name="q" placeholder="Cari kode atau nama aset…" value="<?= e($q) ?>">
      <select class="input" name="room_id" onchange="this.form.submit()">
        <option value="">Semua ruangan</option>
        <?php foreach ($rooms as $r): ?><option value="<?= $r['id'] ?>" <?= $roomId === (int)$r['id'] ? 'selected' : '' ?>><?= e($r['room_code'] . ' · ' . $r['room_name']) ?></option><?php endforeach; ?>
      </select>
      <select class="input" name="category" onchange="this.form.submit()">
        <option value="">Semua kategori</option>
        <?php foreach (CATEGORIES as $c): ?><option <?= $c === $cat ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
      </select>
      <select class="input" name="condition" onchange="this.form.submit()">
        <option value="">Semua kondisi</option>
        <?php foreach (CONDITIONS as $c): ?><option <?= $c === $cond ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
      </select>
      <button class="btn" type="submit">Cari</button>
    </form>
  </div>

  <div class="table-wrap">
    <?php if (!$assets): ?>
      <div class="empty"><div class="big">📦</div><b>Aset tidak ditemukan</b>Coba ubah kata kunci atau filter.</div>
    <?php else: ?>
    <table>
      <thead><tr><th>Kode</th><th>Nama</th><th>Kategori</th><th>Ruangan</th><th>Kondisi</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($assets as $a): ?>
        <tr>
          <td><?= e($a['asset_code']) ?></td>
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              <?php if ($a['image_url']): ?><img class="thumb" src="<?= e($a['image_url']) ?>" alt=""><?php endif; ?>
              <div><a class="link" href="/asset.php?id=<?= $a['id'] ?>"><?= e($a['asset_name']) ?></a><span class="sub">Jumlah: <?= (int)$a['quantity'] ?></span></div>
            </div>
          </td>
          <td><?= e($a['category']) ?></td>
          <td><a href="/room.php?id=<?= $a['room_id'] ?>"><?= e($a['room_name']) ?></a><span class="sub"><?= e($a['room_code']) ?></span></td>
          <td><?= condition_badge($a['condition']) ?></td>
          <td class="actions-col">
            <a class="btn btn-sm" href="/ticket_new.php?asset_id=<?= $a['id'] ?>">Laporkan</a>
            <?php if (is_admin()): ?>
              <button class="btn btn-sm" data-modal-open="asset-modal" data-edit="<?= e(json_encode($a)) ?>">Edit</button>
              <form method="post" data-confirm="Hapus aset <?= e($a['asset_name']) ?>?">
                <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $a['id'] ?>">
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
<div class="modal" id="asset-modal" role="dialog" aria-modal="true">
  <div class="modal-box">
    <div class="modal-head"><h3 data-modal-title data-create="Tambah aset" data-edit="Edit aset">Tambah aset</h3><button class="modal-x" data-modal-close type="button" aria-label="Tutup">×</button></div>
    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?><input type="hidden" name="action" value="create"><input type="hidden" name="id" value="">
      <div class="form-grid">
        <div class="field"><label>Kode aset *</label><input class="input" name="asset_code" maxlength="50" required></div>
        <div class="field"><label>Nama aset *</label><input class="input" name="asset_name" maxlength="150" required></div>
        <div class="field"><label>Kategori</label><select class="input" name="category"><?php foreach (CATEGORIES as $c): ?><option><?= e($c) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label>Ruangan *</label>
          <select class="input" name="room_id" required>
            <option value="">Pilih ruangan</option>
            <?php foreach ($rooms as $r): ?><option value="<?= $r['id'] ?>" <?= $roomId === (int)$r['id'] ? 'selected' : '' ?>><?= e($r['room_code'] . ' · ' . $r['room_name']) ?></option><?php endforeach; ?>
          </select></div>
        <div class="field"><label>Kondisi</label><select class="input" name="condition"><?php foreach (CONDITIONS as $c): ?><option><?= e($c) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label>Jumlah</label><input class="input" type="number" min="1" name="quantity" value="1"></div>
      </div>
      <div class="field"><label>Foto (opsional)</label><input class="input" type="file" name="image" accept="image/jpeg,image/png,image/webp"><div class="hint">JPG/PNG/WebP, maks 3 MB.</div></div>
      <div class="field"><label>Catatan</label><textarea class="input" name="notes"></textarea></div>
      <div class="modal-foot"><button class="btn" type="button" data-modal-close>Batal</button><button class="btn btn-dark" type="submit">Simpan</button></div>
    </form>
  </div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
