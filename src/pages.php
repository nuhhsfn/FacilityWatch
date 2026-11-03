<?php
declare(strict_types=1);

const WRAP = 'overflow-hidden rounded-2xl bg-white';
const TBL = 'rtable min-w-full text-sm';
const TD = 'px-4 py-3';

function require_admin_page(): void {
    if (!is_admin()) { flash('err', 'Silakan login sebagai admin terlebih dahulu.'); redirect('/login'); }
}
/** Jalankan aksi POST dengan CSRF + penanganan error terpadu. */
function post_guard(callable $fn, string $okMsg): bool {
    check_csrf();
    try { $fn(); flash('ok', $okMsg); return true; }
    catch (ApiError $e) { flash('err', $e->getMessage() . ($e->errors ? ': ' . implode('; ', $e->errors) : '')); return false; }
}
function th(array $cols): string {
    return '<thead class="text-left text-xs uppercase tracking-wide text-slate-400"><tr>' . implode('', array_map(fn($c) => "<th class=\"px-4 py-3 font-medium\">$c</th>", $cols)) . '</tr></thead>';
}
function link_btn(string $href, string $text, string $cls = 'text-[#17132b]'): string {
    return '<a class="text-sm font-medium hover:underline ' . $cls . '" href="' . e(app_url($href)) . '">' . $text . '</a>';
}
function edit_btn(string $dlg, array $row): string {
    return '<button class="text-sm font-medium hover:underline" onclick="openForm(\'' . $dlg . '\',' . e(json_encode($row)) . ')">Edit</button>';
}

function page_dashboard(): void {
    $s = stats();
    head('Pantau fasilitas sekolah', 'dashboard', 'Ringkasan aset dan laporan kerusakan.');
    $cards = [['Total Aset', $s['total_assets']], ['Total Tiket', $s['total_tickets']], ['Tiket Open', $s['open_tickets']], ['Aset Rusak', $s['damaged_assets']]];
    echo '<div class="grid grid-cols-2 gap-3 lg:grid-cols-4">';
    foreach ($cards as $i => [$l, $v]) echo '<div class="rounded-2xl p-4 ' . CARDS[$i] . '"><div class="text-sm">' . $l . '</div><div class="mt-6 text-4xl font-semibold">' . $v . '</div></div>';
    echo '</div><div class="mt-4 grid gap-3 lg:grid-cols-2">'
        . '<div class="' . BOX . '"><h2 class="mb-3 font-semibold">Tiket per Status</h2><div class="h-64"><canvas id="cBar"></canvas></div></div>'
        . '<div class="' . BOX . '"><h2 class="mb-3 font-semibold">Aset per Kondisi</h2><div class="h-64"><canvas id="cPie"></canvas></div></div></div>';
    echo '<div class="mb-3 mt-6 flex items-center justify-between"><h2 class="font-semibold">5 Tiket Terbaru</h2>' . link_btn('/tickets', 'Lihat semua →') . '</div>';
    if (!$s['latest_tickets']) empty_state('Belum ada tiket.');
    else {
        echo '<div class="' . WRAP . '"><table class="' . TBL . '">' . th(['No. Tiket', 'Aset', 'Pelapor', 'Prioritas', 'Status', 'Tanggal']) . '<tbody class="divide-y divide-slate-100">';
        foreach ($s['latest_tickets'] as $t)
            echo '<tr><td class="' . TD . '">' . link_btn('/tickets/' . $t['id'], e($t['ticket_number']), 'text-indigo-600') . '</td><td class="' . TD . '">' . e($t['asset_name']) . '</td><td class="' . TD . '">' . e($t['reporter_name']) . '</td><td class="' . TD . '">' . badge($t['priority']) . '</td><td class="' . TD . '">' . badge($t['maintenance_status']) . '</td><td class="' . TD . '">' . e(date('d M Y', strtotime($t['reported_at']))) . '</td></tr>';
        echo '</tbody></table></div>';
    }
    echo '<script>window.FW=' . json_encode(['status' => $s['tickets_by_status'], 'cond' => $s['assets_by_condition']]) . ';</script>'
        . '<script defer src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>';
    foot();
}

function page_rooms(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_admin_page();
        $id = (int)($_POST['_id'] ?? 0) ?: null;
        if (($_POST['_action'] ?? '') === 'delete') post_guard(fn() => delete_row('rooms', (int)$id), 'Ruangan dihapus');
        else post_guard(fn() => room_save($id, $_POST), 'Ruangan disimpan');
        redirect('/rooms');
    }
    $r = rooms_list($_GET);
    head('Daftar Ruangan', 'rooms', 'Pilih ruangan untuk melihat aset di dalamnya.');
    echo chips(buildings(), 'building', 'Semua gedung');
    echo fform(search_box('Cari kode / nama ruangan…') . (is_admin() ? '<button type="button" class="' . BTN . ' sm:ml-auto" onclick="openForm(\'dlgRoom\')">+ Tambah Ruangan</button>' : ''), ['building']);
    if (!$r['items']) empty_state('Tidak ada ruangan ditemukan.');
    else {
        echo '<div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">';
        foreach ($r['items'] as $i => $x) {
            echo '<div class="flex min-h-[10rem] flex-col justify-between rounded-2xl p-4 ' . CARDS[$i % 4] . '">'
                . '<div class="flex items-start justify-between gap-2"><span class="rounded-full bg-white/70 px-2.5 py-0.5 text-xs font-medium">' . e($x['room_code']) . '</span><span class="text-xs">' . e($x['building']) . ' · Lt. ' . $x['floor'] . '</span></div>'
                . '<a href="' . e(app_url('/rooms/' . $x['id'])) . '" class="mt-4 text-lg font-semibold leading-snug hover:underline">' . e($x['room_name']) . '</a>'
                . '<div class="mt-4 flex items-center justify-between text-sm"><span>' . $x['asset_count'] . ' aset</span><span class="flex items-center gap-3">'
                . link_btn('/rooms/' . $x['id'], 'Detail');
            if (is_admin()) echo edit_btn('dlgRoom', $x) . delete_form((int)$x['id'], 'Hapus ruangan ini?');
            echo '</span></div></div>';
        }
        echo '</div>';
        pager($r['meta']);
    }
    dialog('dlgRoom', 'Ruangan',
        field('Kode ruangan', '<input name="room_code" required maxlength="20" class="' . INPUT . '">')
        . field('Nama ruangan', '<input name="room_name" required maxlength="100" class="' . INPUT . '">')
        . '<div class="grid grid-cols-2 gap-3">' . field('Lantai', '<input type="number" name="floor" min="0" value="1" required class="' . INPUT . '">')
        . field('Gedung', '<input name="building" required maxlength="100" class="' . INPUT . '">') . '</div>'
        . field('Deskripsi', '<textarea name="description" rows="3" class="' . INPUT . '"></textarea>'));
    foot();
}

function page_room_detail(int $id): void {
    $r = room_get($id);
    head($r['room_name'], 'rooms', $r['room_code'] . ' · Lantai ' . $r['floor'] . ' · ' . $r['building']);
    echo '<div class="' . BOX . ' text-sm">' . e($r['description'] ?: 'Tidak ada deskripsi.') . '</div>';
    echo '<div class="mb-3 mt-6 flex items-center justify-between gap-2"><h2 class="font-semibold">Aset di ruangan ini (' . count($r['assets']) . ')</h2>';
    if (is_admin()) echo '<a class="' . BTN . '" href="' . e(app_url('/assets?room_id=' . $id . '&add=1')) . '">+ Tambah Aset</a>';
    echo '</div>';
    if (!$r['assets']) empty_state('Belum ada aset di ruangan ini.');
    else {
        echo '<div class="' . WRAP . '"><table class="' . TBL . '">' . th(['Kode', 'Nama', 'Kategori', 'Jumlah', 'Kondisi', 'Aksi']) . '<tbody class="divide-y divide-slate-100">';
        foreach ($r['assets'] as $a)
            echo '<tr><td class="' . TD . '">' . e($a['asset_code']) . '</td><td class="' . TD . '">' . e($a['asset_name']) . '</td><td class="' . TD . '">' . e($a['category']) . '</td><td class="' . TD . '">' . $a['quantity'] . '</td><td class="' . TD . '">' . badge($a['condition']) . '</td><td class="' . TD . '">' . link_btn('/tickets/new?asset_id=' . $a['id'], 'Laporkan kerusakan', 'text-orange-600') . '</td></tr>';
        echo '</tbody></table></div>';
    }
    foot();
}

function page_assets(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_admin_page();
        $id = (int)($_POST['_id'] ?? 0) ?: null;
        if (($_POST['_action'] ?? '') === 'delete') post_guard(fn() => delete_row('facility_assets', (int)$id), 'Aset dihapus');
        else post_guard(fn() => asset_save($id, $_POST), 'Aset disimpan');
        redirect('/assets');
    }
    $r = assets_list($_GET);
    $rooms = rooms_list(['per_page' => 100])['items'];
    $roomOpts = fn(string $sel, string $blank) => ($blank ? '<option value="">' . $blank . '</option>' : '') . implode('', array_map(
        fn($x) => '<option value="' . $x['id'] . '"' . ((string)$x['id'] === $sel ? ' selected' : '') . '>' . e($x['room_code'] . ' · ' . $x['room_name']) . '</option>', $rooms));
    head('Daftar Aset', 'assets', 'Semua fasilitas dan kondisinya saat ini.');
    echo chips(CATEGORIES, 'category', 'Semua kategori');
    echo fform(search_box('Cari kode / nama aset…')
        . '<select name="room_id" class="' . INPUT . ' sm:max-w-[200px]">' . $roomOpts(g('room_id'), 'Semua ruangan') . '</select>'
        . '<select name="condition" class="' . INPUT . ' sm:max-w-[170px]">' . opts(CONDITIONS, g('condition'), 'Semua kondisi') . '</select>'
        . (is_admin() ? '<button type="button" class="' . BTN . ' sm:ml-auto" onclick="openForm(\'dlgAsset\')">+ Tambah Aset</button>' : ''), ['category']);
    if (!$r['items']) empty_state('Tidak ada aset ditemukan.');
    else {
        echo '<div class="' . WRAP . '"><table class="' . TBL . '">' . th(['Kode', 'Nama', 'Kategori', 'Ruangan', 'Kondisi', 'Aksi']) . '<tbody class="divide-y divide-slate-100">';
        foreach ($r['items'] as $a) {
            echo '<tr><td class="' . TD . '">' . e($a['asset_code']) . '</td><td class="' . TD . '">' . e($a['asset_name']) . ' <span class="text-slate-400">×' . $a['quantity'] . '</span></td><td class="' . TD . '">' . e($a['category']) . '</td><td class="' . TD . '">' . link_btn('/rooms/' . $a['room_id'], e($a['room_code']), 'text-indigo-600') . '</td><td class="' . TD . '">' . badge($a['condition']) . '</td><td class="' . TD . ' space-x-3">' . link_btn('/tickets/new?asset_id=' . $a['id'], 'Laporkan', 'text-orange-600');
            if (is_admin()) echo edit_btn('dlgAsset', $a) . delete_form((int)$a['id'], 'Hapus aset ini?');
            echo '</td></tr>';
        }
        echo '</tbody></table></div>';
        pager($r['meta']);
    }
    dialog('dlgAsset', 'Aset',
        field('Kode aset', '<input name="asset_code" required maxlength="50" class="' . INPUT . '">')
        . field('Nama aset', '<input name="asset_name" required maxlength="150" class="' . INPUT . '">')
        . '<div class="grid grid-cols-2 gap-3">' . field('Kategori', '<select name="category" class="' . INPUT . '">' . opts(CATEGORIES, '') . '</select>')
        . field('Kondisi', '<select name="condition" class="' . INPUT . '">' . opts(CONDITIONS, '') . '</select>') . '</div>'
        . '<div class="grid grid-cols-2 gap-3">' . field('Ruangan', '<select name="room_id" required class="' . INPUT . '">' . $roomOpts(g('room_id'), '') . '</select>')
        . field('Jumlah', '<input type="number" name="quantity" min="1" value="1" required class="' . INPUT . '">') . '</div>'
        . field('Foto (opsional, maks 2 MB)', '<input type="file" name="image" accept="image/*" class="' . INPUT . '">')
        . field('Catatan', '<textarea name="notes" rows="2" class="' . INPUT . '"></textarea>'), true);
    if (is_admin() && g('add') !== '') echo '<div data-autoopen="dlgAsset"></div>';
    foot();
}

function page_tickets(): void {
    $r = tickets_list($_GET);
    head('Daftar Tiket', 'tickets', 'Pantau laporan kerusakan dari awal sampai selesai.');
    echo chips(STATUSES, 'status', 'Semua status');
    echo fform(search_box('Cari tiket / pelapor / aset…')
        . '<select name="priority" class="' . INPUT . ' sm:max-w-[170px]">' . opts(PRIORITIES, g('priority'), 'Semua prioritas') . '</select>'
        . '<a class="' . BTN . ' sm:ml-auto" href="' . e(app_url('/tickets/new')) . '">+ Buat Tiket Baru</a>', ['status']);
    if (!$r['items']) empty_state('Tidak ada tiket ditemukan.');
    else {
        echo '<div class="' . WRAP . '"><table class="' . TBL . '">' . th(['No. Tiket', 'Aset', 'Pelapor', 'Prioritas', 'Status', 'Tanggal', 'Aksi']) . '<tbody class="divide-y divide-slate-100">';
        foreach ($r['items'] as $t)
            echo '<tr><td class="' . TD . '">' . e($t['ticket_number']) . '</td><td class="' . TD . '">' . e($t['asset_name']) . '</td><td class="' . TD . '">' . e($t['reporter_name']) . ' <span class="text-slate-400">(' . e($t['reporter_role']) . ')</span></td><td class="' . TD . '">' . badge($t['priority']) . '</td><td class="' . TD . '">' . badge($t['maintenance_status']) . '</td><td class="' . TD . '">' . e(date('d M Y', strtotime($t['reported_at']))) . '</td><td class="' . TD . '">' . link_btn('/tickets/' . $t['id'], 'Detail', 'text-indigo-600') . '</td></tr>';
        echo '</tbody></table></div>';
        pager($r['meta']);
    }
    foot();
}

function page_ticket_new(): void {
    $old = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : [
        'facility_asset_id' => g('asset_id'),
        'reporter_name' => is_user() ? current_username() : '',
        'reporter_role' => 'Guru',
        'priority' => 'Sedang',
    ];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = 0;
        if (post_guard(function () use (&$id) { $id = ticket_create($_POST); }, 'Laporan terkirim. Terima kasih!')) redirect('/tickets/' . $id);
    }
    $byRoom = [];
    foreach (assets_list(['per_page' => 100])['items'] as $a) $byRoom[$a['room_code'] . ' · ' . $a['room_name']][] = $a;
    head('Laporkan Kerusakan', 'tickets', 'Isi form singkat ini, tim perawatan akan menindaklanjuti.');
    $sel = '<input type="search" data-select-search="#assetSel" placeholder="Ketik untuk mencari aset…" class="' . INPUT . ' mb-2">'
        . '<select id="assetSel" name="facility_asset_id" required class="' . INPUT . '"><option value="">— Pilih aset —</option>';
    foreach ($byRoom as $room => $list) {
        $sel .= '<optgroup label="' . e($room) . '">';
        foreach ($list as $a) $sel .= '<option value="' . $a['id'] . '"' . ((string)$a['id'] === (string)($old['facility_asset_id'] ?? '') ? ' selected' : '') . '>' . e($a['asset_name'] . ' (' . $a['asset_code'] . ')') . '</option>';
        $sel .= '</optgroup>';
    }
    echo '<form method="post" enctype="multipart/form-data" class="max-w-2xl space-y-4 ' . BOX . '">' . csrf_field()
        . field('Aset yang bermasalah', $sel . '</select>')
        . '<div class="grid gap-4 sm:grid-cols-2">' . field('Nama pelapor', '<input name="reporter_name" required maxlength="100" value="' . e($old['reporter_name'] ?? '') . '" class="' . INPUT . '">')
        . field('Peran', '<select name="reporter_role" class="' . INPUT . '">' . opts(ROLES, $old['reporter_role'] ?? '') . '</select>') . '</div>'
        . field('Apa masalahnya?', '<textarea name="issue_description" rows="4" required data-counter="#cnt" class="' . INPUT . '">' . e($old['issue_description'] ?? '') . '</textarea><div id="cnt" class="mt-1 text-xs text-slate-400"></div>')
        . field('Seberapa mendesak?', '<select name="priority" class="' . INPUT . '">' . opts(PRIORITIES, $old['priority'] ?? '') . '</select>')
        . field('Foto kerusakan (opsional, maks 2 MB)', '<input type="file" name="image" accept="image/*" data-preview="#prev" class="' . INPUT . '"><img id="prev" alt="Pratinjau" class="mt-2 hidden max-h-40 rounded-xl">')
        . '<div class="flex justify-end gap-2"><a class="' . BTN2 . '" href="' . e(app_url('/tickets')) . '">Batal</a><button class="' . BTN . '">Kirim Laporan</button></div></form>';
    foot();
}

function page_ticket_detail(int $id): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_admin_page();
        if (($_POST['_action'] ?? '') === 'delete') { if (post_guard(fn() => delete_row('maintenance_tickets', $id), 'Tiket dihapus')) redirect('/tickets'); }
        else post_guard(fn() => ticket_update($id, $_POST), 'Tiket diperbarui');
        redirect('/tickets/' . $id);
    }
    $t = ticket_get($id);
    head($t['ticket_number'], 'tickets', $t['asset_name'] . ' · ' . $t['room_name']);
    $row = fn(string $k, string $v) => '<div><dt class="text-slate-400">' . $k . '</dt><dd class="font-medium">' . $v . '</dd></div>';
    echo '<div class="grid gap-4 lg:grid-cols-3"><div class="space-y-4 lg:col-span-2">';
    echo '<div class="' . BOX . '"><div class="mb-4 flex flex-wrap gap-2">' . badge($t['maintenance_status']) . badge($t['priority']) . '</div><dl class="grid gap-3 text-sm sm:grid-cols-2">'
        . $row('Aset', e($t['asset_name']) . ' (' . e($t['asset_code']) . ')') . $row('Ruangan', e($t['room_code'] . ' · ' . $t['room_name']))
        . $row('Pelapor', e($t['reporter_name']) . ' (' . e($t['reporter_role']) . ')') . $row('Dilaporkan', e(date('d M Y H:i', strtotime($t['reported_at']))))
        . $row('Teknisi', e($t['assigned_to'] ?: '—')) . $row('Selesai', $t['resolved_at'] ? e(date('d M Y H:i', strtotime($t['resolved_at']))) : '—')
        . '</dl><h3 class="mt-5 text-sm text-slate-400">Deskripsi masalah</h3><p class="mt-1 whitespace-pre-line text-sm">' . e($t['issue_description']) . '</p>';
    if ($t['image_url']) echo '<img src="' . e(app_url($t['image_url'])) . '" alt="Foto kerusakan" class="mt-4 max-h-64 rounded-xl">';
    if ($t['resolution_notes']) echo '<h3 class="mt-5 text-sm text-slate-400">Catatan penyelesaian</h3><p class="mt-1 whitespace-pre-line text-sm">' . e($t['resolution_notes']) . '</p>';
    echo '</div>';
    $flow = ['Dilaporkan', 'Ditinjau', 'Dalam Perbaikan', 'Selesai'];
    $cur = array_search($t['maintenance_status'], $flow, true);
    echo '<div class="' . BOX . '"><h2 class="mb-4 font-semibold">Perjalanan Tiket</h2><ol class="flex flex-col gap-4 sm:flex-row sm:justify-between">';
    foreach ($flow as $i => $s) {
        $done = $cur !== false && $i <= $cur;
        echo '<li class="flex items-center gap-2 text-sm"><span class="flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold ' . ($done ? 'bg-[#17132b] text-white' : 'bg-[#f3e9e2] text-slate-400') . '">' . ($done ? '✓' : $i + 1) . '</span><span class="' . ($done ? 'font-medium' : 'text-slate-400') . '">' . $s . '</span></li>';
    }
    echo '</ol>' . ($t['maintenance_status'] === 'Ditolak' ? '<p class="mt-4 text-sm text-slate-600">Tiket ini <b>ditolak</b> (lihat catatan di atas).</p>' : '') . '</div></div><div>';
    if (is_admin()) {
        echo '<form method="post" class="space-y-3 rounded-2xl bg-[#f3e9e2] p-5"><h2 class="font-semibold">Update Tiket</h2>' . csrf_field()
            . field('Status', '<select name="maintenance_status" class="' . INPUT . '">' . opts(STATUSES, $t['maintenance_status']) . '</select>')
            . field('Teknisi / petugas', '<input name="assigned_to" maxlength="100" value="' . e($t['assigned_to'] ?? '') . '" class="' . INPUT . '">')
            . field('Prioritas', '<select name="priority" class="' . INPUT . '">' . opts(PRIORITIES, $t['priority']) . '</select>')
            . field('Catatan resolusi', '<textarea name="resolution_notes" rows="4" class="' . INPUT . '">' . e($t['resolution_notes'] ?? '') . '</textarea>')
            . '<p class="text-xs text-slate-500">Status “Selesai” otomatis mengubah kondisi aset menjadi Baik.</p><button class="' . BTN . ' w-full">Simpan Perubahan</button></form>'
            . '<div class="mt-3 text-right">' . delete_form($id, 'Hapus tiket ini?') . '</div>';
    } else echo '<div class="' . BOX . ' text-sm text-slate-500">Hanya admin/teknisi yang dapat mengubah status. ' . link_btn('/login', 'Login', 'text-indigo-600') . '</div>';
    echo '</div></div>';
    foot();
}

function page_login(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_csrf();
        if (authenticate(trim((string)($_POST['username'] ?? '')), (string)($_POST['password'] ?? ''))) {
            flash('ok', 'Login berhasil. Selamat datang, ' . current_username() . '.');
            redirect('/dashboard');
        }
        flash('err', 'Username atau password salah.'); redirect('/login');
    }
    head('Login', '', 'Masuk sebagai pengguna atau admin.');
    echo '<form method="post" class="max-w-sm space-y-4 ' . BOX . '">' . csrf_field()
        . field('Username', '<input name="username" required class="' . INPUT . '">') . field('Password', '<input type="password" name="password" required class="' . INPUT . '">')
        . '<button class="' . BTN . ' w-full">Masuk</button><p class="text-center text-sm text-slate-500">Belum punya akun? ' . link_btn('/register', 'Daftar sebagai pengguna', 'text-indigo-600') . '</p></form>';
    foot();
}

function page_register(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_csrf();
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        if ($password !== (string)($_POST['password_confirmation'] ?? '')) {
            flash('err', 'Konfirmasi password tidak sama.');
            redirect('/register');
        }
        try {
            user_register($username, $password);
            if (!authenticate($username, $password)) throw new ApiError('Akun berhasil dibuat, tetapi login gagal.', 500);
            flash('ok', 'Akun pengguna berhasil dibuat.');
            redirect('/dashboard');
        } catch (ApiError $e) {
            flash('err', $e->getMessage());
            redirect('/register');
        }
    }
    head('Daftar Akun Murid', '', 'Buat akun murid untuk melaporkan kerusakan.');
    echo '<form method="post" class="max-w-sm space-y-4 ' . BOX . '">' . csrf_field()
        . field('Username', '<input name="username" required minlength="3" maxlength="50" pattern="[A-Za-z0-9._-]+" autocomplete="username" class="' . INPUT . '">')
        . field('Password (minimal 8 karakter)', '<input type="password" name="password" required minlength="8" autocomplete="new-password" class="' . INPUT . '">')
        . field('Ulangi password', '<input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password" class="' . INPUT . '">')
        . '<button class="' . BTN . ' w-full">Daftar</button><p class="text-center text-sm text-slate-500">Sudah punya akun? ' . link_btn('/login', 'Login', 'text-indigo-600') . '</p></form>';
    foot();
}

function page_dispatch(string $m, string $path): void {
    $path = rtrim($path, '/') ?: '/';
    try {
        match (true) {
            $path === '/' => redirect('/dashboard'),
            $path === '/dashboard' => page_dashboard(),
            $path === '/rooms' => page_rooms(),
            (bool)preg_match('#^/rooms/(\d+)$#', $path, $x) => page_room_detail((int)$x[1]),
            $path === '/assets' => page_assets(),
            $path === '/tickets' => page_tickets(),
            $path === '/tickets/new' => page_ticket_new(),
            (bool)preg_match('#^/tickets/(\d+)$#', $path, $x) => page_ticket_detail((int)$x[1]),
            $path === '/login' => page_login(),
            $path === '/register' => page_register(),
            $path === '/logout' => (function () { $_SESSION = []; session_destroy(); redirect('/dashboard'); })(),
            default => throw new ApiError('Halaman tidak ditemukan', 404),
        };
    } catch (ApiError $e) {
        http_response_code($e->status);
        head('Oops, ' . $e->status);
        echo '<div class="' . BOX . '">' . e($e->getMessage()) . '<div class="mt-4"><a class="' . BTN . '" href="' . e(app_url('/dashboard')) . '">Ke Dashboard</a></div></div>';
        foot();
    }
}
