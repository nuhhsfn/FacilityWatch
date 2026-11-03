<?php
declare(strict_types=1);
// Lapisan data + aturan bisnis. Dipakai bersama oleh REST API dan halaman web.

/** Terjemahkan error MySQL menjadi ApiError yang ramah. */
function guard(callable $fn) {
    try { return $fn(); }
    catch (PDOException $e) {
        throw match ($e->errorInfo[1] ?? 0) {
            1062 => new ApiError('Kode/nomor sudah digunakan', 422),
            1451 => new ApiError('Data masih dipakai data lain sehingga tidak dapat dihapus', 409),
            1452 => new ApiError('Referensi data (ruangan/aset) tidak valid', 422),
            default => $e,
        };
    }
}

function paginate(string $from, string $select, array $p, string $order, array $q): array {
    $page = max(1, (int)($q['page'] ?? 1));
    $per = min(100, max(1, (int)($q['per_page'] ?? 10)));
    $st = db()->prepare("SELECT COUNT(*) FROM $from"); $st->execute($p);
    $total = (int)$st->fetchColumn();
    $off = ($page - 1) * $per;
    $st = db()->prepare("SELECT $select FROM $from ORDER BY $order LIMIT $per OFFSET $off"); $st->execute($p);
    return ['items' => $st->fetchAll(), 'meta' => ['page' => $page, 'per_page' => $per, 'total' => $total, 'total_pages' => max(1, (int)ceil($total / $per))]];
}

function save_row(string $table, ?int $id, array $d): int {
    $cols = array_keys($d);
    if ($id) {
        $set = implode(',', array_map(fn($c) => "`$c`=:$c", $cols));
        db()->prepare("UPDATE `$table` SET $set WHERE id=:__id")->execute($d + ['__id' => $id]);
        return $id;
    }
    db()->prepare("INSERT INTO `$table` (`" . implode('`,`', $cols) . "`) VALUES (:" . implode(',:', $cols) . ")")->execute($d);
    return (int)db()->lastInsertId();
}

function delete_row(string $table, int $id): void {
    $st = guard(function () use ($table, $id) { $s = db()->prepare("DELETE FROM `$table` WHERE id=?"); $s->execute([$id]); return $s; });
    if ($st->rowCount() === 0) throw new ApiError('Data tidak ditemukan', 404);
}

// ---------- USERS ----------
function user_register(string $username, string $password): void {
    if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username))
        throw new ApiError('Username harus 3-50 karakter dan hanya boleh berisi huruf, angka, titik, garis bawah, atau tanda hubung.', 422);
    if (strcasecmp($username, (string)$GLOBALS['cfg']['admin_user']) === 0)
        throw new ApiError('Username tersebut sudah dicadangkan untuk admin.', 409);
    if (mb_strlen($password) < 8)
        throw new ApiError('Password minimal 8 karakter.', 422);

    try {
        $st = db()->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, 'user')");
        $st->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? 0) === 1062) throw new ApiError('Username sudah digunakan.', 409);
        throw $e;
    }
}

// ---------- ROOMS ----------
function rooms_list(array $q): array {
    $w = ['1=1']; $p = [];
    if (!empty($q['search'])) { $w[] = '(r.room_name LIKE :s1 OR r.room_code LIKE :s2)'; $p[':s1'] = $p[':s2'] = '%' . $q['search'] . '%'; }
    if (!empty($q['building'])) { $w[] = 'r.building = :b'; $p[':b'] = $q['building']; }
    return paginate('rooms r WHERE ' . implode(' AND ', $w),
        'r.*, (SELECT COUNT(*) FROM facility_assets a WHERE a.room_id = r.id) AS asset_count', $p, 'r.building, r.room_code', $q);
}
function buildings(): array { return db()->query('SELECT DISTINCT building FROM rooms ORDER BY building')->fetchAll(PDO::FETCH_COLUMN); }
function room_get(int $id): array {
    $st = db()->prepare('SELECT * FROM rooms WHERE id=?'); $st->execute([$id]);
    $r = $st->fetch() ?: throw new ApiError('Ruangan tidak ditemukan', 404);
    $st = db()->prepare('SELECT * FROM facility_assets WHERE room_id=? ORDER BY asset_name'); $st->execute([$id]);
    $r['assets'] = $st->fetchAll();
    return $r;
}
function room_save(?int $id, array $in): int {
    [$d, $e] = validate($in, ['room_code' => 'required|max:20', 'room_name' => 'required|max:100', 'floor' => 'required|int|min:0',
        'building' => 'required|max:100', 'description' => '']);
    if ($e) throw new ApiError('Validasi gagal', 422, $e);
    if ($id) room_get($id);
    return guard(fn() => save_row('rooms', $id, $d));
}

// ---------- ASSETS ----------
function assets_list(array $q): array {
    $w = ['1=1']; $p = [];
    if (!empty($q['room_id']))   { $w[] = 'a.room_id = :r'; $p[':r'] = (int)$q['room_id']; }
    if (!empty($q['condition'])) { $w[] = 'a.`condition` = :c'; $p[':c'] = $q['condition']; }
    if (!empty($q['category']))  { $w[] = 'a.category = :cat'; $p[':cat'] = $q['category']; }
    if (!empty($q['search']))    { $w[] = '(a.asset_name LIKE :s1 OR a.asset_code LIKE :s2)'; $p[':s1'] = $p[':s2'] = '%' . $q['search'] . '%'; }
    return paginate('facility_assets a JOIN rooms r ON r.id = a.room_id WHERE ' . implode(' AND ', $w),
        'a.*, r.room_name, r.room_code', $p, 'r.room_code, a.asset_name', $q);
}
function asset_get(int $id): array {
    $st = db()->prepare('SELECT a.*, r.room_name, r.room_code FROM facility_assets a JOIN rooms r ON r.id=a.room_id WHERE a.id=?');
    $st->execute([$id]);
    $a = $st->fetch() ?: throw new ApiError('Aset tidak ditemukan', 404);
    $st = db()->prepare('SELECT * FROM maintenance_tickets WHERE facility_asset_id=? ORDER BY reported_at DESC'); $st->execute([$id]);
    $a['tickets'] = $st->fetchAll();
    return $a;
}
function asset_save(?int $id, array $in): int {
    [$d, $e] = validate($in, ['asset_code' => 'required|max:50', 'asset_name' => 'required|max:150',
        'category' => 'required|in:' . implode(',', CATEGORIES), 'room_id' => 'required|int',
        'condition' => 'required|in:' . implode(',', CONDITIONS), 'quantity' => 'required|int|min:1', 'notes' => '']);
    if ($e) throw new ApiError('Validasi gagal', 422, $e);
    if ($img = save_upload('image')) $d['image_url'] = $img;
    if ($id) asset_get($id);
    return guard(fn() => save_row('facility_assets', $id, $d));
}

// ---------- TICKETS ----------
function tickets_list(array $q): array {
    $w = ['1=1']; $p = [];
    if (!empty($q['status']))   { $w[] = 't.maintenance_status = :st'; $p[':st'] = $q['status']; }
    if (!empty($q['priority'])) { $w[] = 't.priority = :pr'; $p[':pr'] = $q['priority']; }
    if (!empty($q['asset_id'])) { $w[] = 't.facility_asset_id = :as'; $p[':as'] = (int)$q['asset_id']; }
    if (!empty($q['search']))   { $w[] = '(t.ticket_number LIKE :s1 OR t.reporter_name LIKE :s2 OR a.asset_name LIKE :s3)'; $p[':s1'] = $p[':s2'] = $p[':s3'] = '%' . $q['search'] . '%'; }
    return paginate('maintenance_tickets t JOIN facility_assets a ON a.id = t.facility_asset_id WHERE ' . implode(' AND ', $w),
        't.*, a.asset_name, a.asset_code', $p, 't.reported_at DESC, t.id DESC', $q);
}
function ticket_get(int $id): array {
    $st = db()->prepare('SELECT t.*, a.asset_name, a.asset_code, a.`condition` AS asset_condition, r.room_name, r.room_code
        FROM maintenance_tickets t JOIN facility_assets a ON a.id=t.facility_asset_id JOIN rooms r ON r.id=a.room_id WHERE t.id=?');
    $st->execute([$id]);
    return $st->fetch() ?: throw new ApiError('Tiket tidak ditemukan', 404);
}
function ticket_create(array $in): int {
    [$d, $e] = validate($in, ['facility_asset_id' => 'required|int', 'reporter_name' => 'required|max:100',
        'reporter_role' => 'required|in:' . implode(',', ROLES), 'issue_description' => 'required|minlen:10',
        'priority' => 'required|in:' . implode(',', PRIORITIES)]);
    if ($e) throw new ApiError('Validasi gagal', 422, $e);
    $d['image_url'] = save_upload('image');
    return guard(function () use ($d) {
        $pdo = db(); $pdo->beginTransaction();
        try {
            $prefix = 'MTC-' . date('Ymd') . '-';
            $st = $pdo->prepare('SELECT COALESCE(MAX(CAST(SUBSTRING(ticket_number, -3) AS UNSIGNED)), 0) FROM maintenance_tickets WHERE ticket_number LIKE ? FOR UPDATE');
            $st->execute([$prefix . '%']);
            $d['ticket_number'] = $prefix . str_pad((string)((int)$st->fetchColumn() + 1), 3, '0', STR_PAD_LEFT);
            $d['maintenance_status'] = 'Dilaporkan';
            $d['reported_at'] = date('Y-m-d H:i:s');
            $id = save_row('maintenance_tickets', null, $d);
            $pdo->commit();
            return $id;
        } catch (Throwable $x) { $pdo->rollBack(); throw $x; }
    });
}
function ticket_update(int $id, array $in): void {
    $t = ticket_get($id);
    [$d, $e] = validate($in, ['maintenance_status' => 'required|in:' . implode(',', STATUSES), 'assigned_to' => 'max:100',
        'resolution_notes' => '', 'priority' => 'in:' . implode(',', PRIORITIES)]);
    foreach (['assigned_to', 'resolution_notes', 'priority'] as $k) $d[$k] ??= $t[$k];
    if (!$e && $d['maintenance_status'] === 'Dalam Perbaikan' && !$d['assigned_to']) $e['assigned_to'] = 'teknisi wajib diisi saat status Dalam Perbaikan';
    if (!$e && in_array($d['maintenance_status'], ['Selesai', 'Ditolak'], true) && !$d['resolution_notes']) $e['resolution_notes'] = 'catatan wajib diisi saat tiket Selesai/Ditolak';
    if ($e) throw new ApiError('Validasi gagal', 422, $e);
    $d['resolved_at'] = $d['maintenance_status'] === 'Selesai' ? ($t['resolved_at'] ?: date('Y-m-d H:i:s')) : null;
    guard(function () use ($id, $d, $t) {
        $pdo = db(); $pdo->beginTransaction();
        try {
            save_row('maintenance_tickets', $id, $d);
            // Bonus logic: tiket selesai -> kondisi aset otomatis "Baik"
            if ($d['maintenance_status'] === 'Selesai')
                $pdo->prepare("UPDATE facility_assets SET `condition`='Baik' WHERE id=?")->execute([$t['facility_asset_id']]);
            $pdo->commit();
        } catch (Throwable $x) { $pdo->rollBack(); throw $x; }
    });
}

// ---------- DASHBOARD ----------
function dashboard_stats(): array {
    $one = fn(string $s) => (int)db()->query($s)->fetchColumn();
    $map = fn(string $s, array $keys) => array_replace(array_fill_keys($keys, 0), array_map('intval', array_column(db()->query($s)->fetchAll(), 'c', 'k')));
    $byStatus = $map('SELECT maintenance_status k, COUNT(*) c FROM maintenance_tickets GROUP BY maintenance_status', STATUSES);
    $byCond = $map('SELECT `condition` k, COUNT(*) c FROM facility_assets GROUP BY `condition`', CONDITIONS);
    return [
        'total_assets' => $one('SELECT COUNT(*) FROM facility_assets'),
        'total_tickets' => $one('SELECT COUNT(*) FROM maintenance_tickets'),
        'open_tickets' => $byStatus['Dilaporkan'] + $byStatus['Ditinjau'] + $byStatus['Dalam Perbaikan'],
        'damaged_assets' => $byCond['Rusak'],
        'tickets_by_status' => $byStatus,
        'assets_by_condition' => $byCond,
        'latest_tickets' => tickets_list(['per_page' => 5])['items'],
    ];
}
