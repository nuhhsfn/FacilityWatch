<?php
declare(strict_types=1);

const BADGES = [
    'Baik' => 'bg-green-100 text-green-800', 'Perlu Perbaikan' => 'bg-yellow-100 text-yellow-800', 'Rusak' => 'bg-red-100 text-red-800',
    'Dilaporkan' => 'bg-blue-100 text-blue-800', 'Ditinjau' => 'bg-purple-100 text-purple-800', 'Dalam Perbaikan' => 'bg-orange-100 text-orange-800',
    'Selesai' => 'bg-green-100 text-green-800', 'Ditolak' => 'bg-gray-200 text-gray-700',
    'Rendah' => 'bg-slate-100 text-slate-700', 'Sedang' => 'bg-blue-100 text-blue-800', 'Tinggi' => 'bg-orange-100 text-orange-800', 'Darurat' => 'bg-red-100 text-red-800',
];
const CARDS = ['bg-[#f6c9cb]', 'bg-[#f9dfc0]', 'bg-[#d9d4fb]', 'bg-[#bdf0dc]']; // pink, peach, lilac, mint
const INPUT = 'w-full rounded-xl border border-[#eadfd7] bg-white px-3 py-2.5 text-sm focus:border-[#17132b] focus:outline-none focus:ring-2 focus:ring-[#bdf0dc]';
const BTN = 'inline-flex items-center justify-center gap-1 rounded-full bg-[#17132b] px-5 py-2.5 text-sm font-medium text-white transition hover:bg-[#2c2550]';
const BTN2 = 'inline-flex items-center justify-center rounded-full border border-[#eadfd7] bg-white px-4 py-2.5 text-sm font-medium transition hover:bg-[#f3e9e2]';
const BOX = 'rounded-2xl bg-white p-5';

function stats(): array { static $s = null; return $s ??= dashboard_stats(); }
function g(string $k): string { return is_string($_GET[$k] ?? null) ? $_GET[$k] : ''; }

function icon(string $n, string $cls = 'h-5 w-5'): string {
    static $p = [
        'grid' => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>',
        'home' => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
        'box' => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>',
        'tool' => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
        'plus' => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/>',
        'in' => '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/>',
        'out' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
    ];
    return '<svg class="' . $cls . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $p[$n] . '</svg>';
}
function badge(string $v): string {
    return '<span class="inline-flex whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium ' . (BADGES[$v] ?? 'bg-slate-100') . '">' . e($v) . '</span>';
}
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf()) . '">'; }
function opts(array $values, ?string $sel, string $blank = ''): string {
    $h = $blank !== '' ? '<option value="">' . e($blank) . '</option>' : '';
    foreach ($values as $v) $h .= '<option value="' . e($v) . '"' . ((string)$v === (string)$sel ? ' selected' : '') . '>' . e($v) . '</option>';
    return $h;
}
function qs(array $over): string {
    return '?' . http_build_query(array_filter(array_merge($_GET, $over), fn($v) => $v !== '' && $v !== null));
}
function empty_state(string $msg): void {
    echo '<div class="rounded-2xl bg-white p-10 text-center text-slate-500">' . e($msg) . '</div>';
}
function pager(array $m): void {
    if ($m['total_pages'] <= 1) return;
    echo '<div class="mt-4 flex flex-wrap items-center justify-between gap-2 text-sm text-slate-600"><span>Halaman ' . $m['page'] . ' dari ' . $m['total_pages'] . ' · ' . $m['total'] . ' data</span><div class="flex gap-2">';
    if ($m['page'] > 1) echo '<a class="' . BTN2 . '" href="' . e(qs(['page' => $m['page'] - 1])) . '">‹ Sebelumnya</a>';
    if ($m['page'] < $m['total_pages']) echo '<a class="' . BTN2 . '" href="' . e(qs(['page' => $m['page'] + 1])) . '">Berikutnya ›</a>';
    echo '</div></div>';
}
/** Chip filter berbentuk pil (seperti desain referensi). */
function chips(array $values, string $param, string $all): string {
    $h = '<div class="chips no-scrollbar mb-3 flex gap-2 overflow-x-auto pb-1">';
    foreach (['' => $all] + array_combine($values, $values) as $v => $label) {
        $on = (string)$v === g($param);
        $h .= '<a href="' . e(qs([$param => $v, 'page' => ''])) . '" class="' . ($on ? 'is-active bg-[#17132b] text-white' : 'bg-[#f3e9e2] hover:bg-[#eadfd7]')
            . ' shrink-0 rounded-full px-4 py-2 text-sm font-medium transition">' . e($label) . '</a>';
    }
    return $h . '</div>';
}
/** Form filter yang auto-submit (lihat app.js). $keep = parameter chip yang dipertahankan. */
function fform(string $inner, array $keep = []): string {
    $h = '<form data-filter class="mb-4 flex flex-wrap items-center gap-2">';
    foreach ($keep as $k) if (g($k) !== '') $h .= '<input type="hidden" name="' . e($k) . '" value="' . e(g($k)) . '">';
    return $h . $inner . '<noscript><button class="' . BTN2 . '">Terapkan</button></noscript></form>';
}
function search_box(string $ph = 'Cari…'): string {
    return '<input type="search" name="search" value="' . e(g('search')) . '" placeholder="' . e($ph) . '" class="' . INPUT . ' sm:max-w-xs">';
}

function head(string $title, string $active = '', string $sub = ''): void {
    $nav = ['dashboard' => ['Dashboard', 'grid'], 'rooms' => ['Ruangan', 'home'], 'assets' => ['Aset', 'box'], 'tickets' => ['Tiket', 'tool']];
    $flash = $_SESSION['flash'] ?? []; unset($_SESSION['flash']);
    try { $s = stats(); } catch (Throwable) { $s = null; }
    $link = fn(string $k, string $label, string $ico, bool $on, string $cls) =>
        '<a href="' . e(app_url($k)) . '" title="' . e($label) . '" class="flex h-11 w-11 items-center justify-center rounded-full ' . $cls . ($on ? '' : '') . '">' . icon($ico) . '</a>';
    ?><!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($title) ?> · FacilityWatch</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script><link rel="stylesheet" href="<?= e(app_url('/assets/app.css')) ?>"></head>
<body class="min-h-screen bg-gradient-to-br from-[#c8f2e1] to-[#a6e5cd] text-[#17132b] antialiased">
<div class="mx-auto max-w-[1440px] p-2 sm:p-4 lg:p-6">
 <div class="flex min-h-[calc(100vh-1rem)] gap-3 rounded-[1.75rem] bg-[#faf6f3] p-2 shadow-2xl sm:gap-4 sm:p-4 lg:min-h-[calc(100vh-3rem)]">

  <aside class="hidden w-[68px] shrink-0 flex-col items-center gap-3 rounded-2xl bg-[#f3e9e2] py-5 md:flex">
    <div class="mb-3 flex h-11 w-11 items-center justify-center rounded-2xl bg-[#17132b] text-lg text-white">F</div>
    <?php foreach ($nav as $k => [$label, $ico]) echo $link("/$k", $label, $ico, $active === $k, $active === $k ? 'bg-[#17132b] text-white' : 'bg-white text-slate-500 hover:text-[#17132b]'); ?>
    <div class="mt-auto flex flex-col gap-3">
      <?= $link('/tickets/new', 'Laporkan kerusakan', 'plus', false, 'bg-[#bdf0dc]') ?>
      <?= is_authenticated() ? $link('/logout', 'Keluar', 'out', false, 'bg-white text-slate-500') : $link('/login', 'Login Murid/Admin', 'in', false, 'bg-white text-slate-500') ?>
    </div>
  </aside>

  <div class="min-w-0 flex-1 px-1 pb-24 pt-3 md:px-4 md:pb-6">
    <h1 class="text-3xl font-semibold leading-tight sm:text-4xl md:max-w-xl"><?= e($title) ?></h1>
    <?php if ($sub): ?><p class="mt-1 text-sm text-slate-500"><?= e($sub) ?></p><?php endif; ?>
    <div class="mb-5"></div>
    <div class="fixed inset-x-3 top-3 z-50 space-y-2 sm:left-auto sm:w-80">
      <?php foreach ($flash as $f): ?>
        <div class="toast cursor-pointer rounded-2xl px-4 py-3 text-sm shadow-lg <?= $f['type'] === 'ok' ? 'bg-[#bdf0dc]' : 'bg-[#f6c9cb]' ?>"><?= e($f['msg']) ?></div>
      <?php endforeach; ?>
    </div>
<?php
    echo "\n";
    // simpan data panel kanan untuk foot()
    $GLOBALS['panel'] = $s;
}

function foot(): void {
    $s = $GLOBALS['panel'] ?? null; ?>
  </div>

  <aside class="hidden w-72 shrink-0 rounded-2xl bg-[#f3e9e2] p-4 xl:block">
    <div class="text-center">
      <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-[#d9d4fb] text-xl font-semibold"><?= is_admin() ? 'A' : (is_user() ? 'M' : 'W') ?></div>
      <div class="mt-2 font-medium"><?= is_authenticated() ? e(current_username()) : 'Warga Sekolah' ?></div>
      <div class="text-xs text-slate-500"><?= is_admin() ? 'Admin · ' . link_btn('/logout', 'Keluar') : (is_user() ? 'Murid · ' . link_btn('/logout', 'Keluar') : link_btn('/login', 'Login Murid/Admin') . ' · ' . link_btn('/register', 'Daftar sebagai murid')) ?></div>
    </div>
    <?php if ($s): ?>
    <div class="mt-4 grid grid-cols-2 gap-2 text-center">
      <div class="rounded-2xl bg-[#f9dfc0] p-3"><div class="text-2xl font-semibold"><?= $s['open_tickets'] ?></div><div class="text-xs">Tiket open</div></div>
      <div class="rounded-2xl bg-[#f6c9cb] p-3"><div class="text-2xl font-semibold"><?= $s['damaged_assets'] ?></div><div class="text-xs">Aset rusak</div></div>
    </div>
    <a href="<?= e(app_url('/tickets/new')) ?>" class="<?= BTN ?> mt-3 w-full">+ Laporkan Kerusakan</a>
    <div class="mb-2 mt-5 text-xs font-medium text-slate-500">Tiket terbaru</div>
    <div class="space-y-2">
      <?php foreach (array_slice($s['latest_tickets'], 0, 3) as $i => $t): ?>
        <a href="<?= e(app_url('/tickets/' . $t['id'])) ?>" class="block rounded-2xl p-3 text-sm <?= CARDS[$i % 4] ?>">
          <div class="font-medium"><?= e($t['asset_name']) ?></div>
          <div class="mt-1 flex items-center justify-between text-xs"><span><?= e($t['ticket_number']) ?></span><?= badge($t['maintenance_status']) ?></div>
        </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </aside>
 </div>
</div>

<nav id="bnav" class="fixed inset-x-3 bottom-3 z-40 flex justify-around rounded-full bg-[#17132b] p-2 text-white shadow-xl md:hidden">
  <?php foreach (['dashboard' => 'grid', 'rooms' => 'home', 'assets' => 'box', 'tickets' => 'tool', 'tickets/new' => 'plus'] as $k => $ico): ?>
    <a href="<?= e(app_url('/' . $k)) ?>" class="flex h-11 w-11 items-center justify-center rounded-full hover:bg-white/10"><?= icon($ico) ?></a>
  <?php endforeach; ?>
  <a href="<?= e(app_url(is_authenticated() ? '/logout' : '/login')) ?>" class="flex h-11 w-11 items-center justify-center rounded-full hover:bg-white/10"><?= icon(is_authenticated() ? 'out' : 'in') ?></a>
</nav>
<script defer src="<?= e(app_url('/assets/app.js')) ?>"></script>
</body></html>
<?php }

/** Dialog modal generik; $fields = HTML isi form. */
function dialog(string $id, string $name, string $fields, bool $multipart = false): void { ?>
<dialog id="<?= $id ?>" data-name="<?= e($name) ?>" class="m-auto max-h-[90vh] w-[calc(100%-1.5rem)] max-w-lg overflow-y-auto rounded-3xl bg-[#faf6f3] p-0 backdrop:bg-black/40">
  <form method="post" class="space-y-3 p-6" <?= $multipart ? 'enctype="multipart/form-data"' : '' ?>>
    <h2 class="text-xl font-semibold" data-title></h2>
    <?= csrf_field() ?><input type="hidden" name="_id" value=""><input type="hidden" name="_action" value="save">
    <?= $fields ?>
    <div class="flex justify-end gap-2 pt-2">
      <button type="button" class="<?= BTN2 ?>" onclick="this.closest('dialog').close()">Batal</button>
      <button class="<?= BTN ?>">Simpan</button>
    </div>
  </form>
</dialog>
<?php }

function field(string $label, string $input): string {
    return '<label class="block text-sm font-medium">' . e($label) . '<div class="mt-1 font-normal">' . $input . '</div></label>';
}
function delete_form(int $id, string $confirm): string {
    return '<form method="post" class="inline" onsubmit="return confirm(\'' . e($confirm) . '\')">' . csrf_field()
        . '<input type="hidden" name="_action" value="delete"><input type="hidden" name="_id" value="' . $id . '">'
        . '<button class="text-sm text-red-600 hover:underline">Hapus</button></form>';
}
