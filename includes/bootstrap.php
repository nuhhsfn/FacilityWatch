<?php
declare(strict_types=1);

/**
 * Bootstrap: session, koneksi PDO, helper umum, auth, CSRF, flash, upload.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

date_default_timezone_set('Asia/Jakarta');

$GLOBALS['config'] = require __DIR__ . '/../config/config.php';

function config(string $key, $default = null)
{
    $value = $GLOBALS['config'];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = config('db');
        $dsn = "mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset={$c['charset']}";
        try {
            $pdo = new PDO($dsn, $c['user'], $c['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // true: memungkinkan satu named parameter (mis. :q) dipakai berulang pada query pencarian
                PDO::ATTR_EMULATE_PREPARES   => true,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            exit('<h1>Koneksi database gagal</h1><p>Periksa config/config.php dan pastikan database sudah diimpor.</p>');
        }
    }
    return $pdo;
}

/* ---------- Helper umum ---------- */

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function redirect(string $to): never
{
    header('Location: ' . $to);
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function input(string $key, $default = '')
{
    $v = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $v;
}

function format_date(?string $date, bool $withTime = false): string
{
    if (!$date) {
        return '-';
    }
    $months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $t = strtotime($date);
    $out = date('j', $t) . ' ' . $months[(int)date('n', $t) - 1] . ' ' . date('Y', $t);
    return $withTime ? $out . ', ' . date('H:i', $t) : $out;
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $s = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $s .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $s ?: '?';
}

function avatar(string $name, string $class = ''): string
{
    $hues = [250, 160, 20, 200, 330, 40];
    $h = $hues[abs(crc32($name)) % count($hues)];
    return '<span class="avatar ' . e($class) . '" style="--h:' . $h . '">' . e(initials($name)) . '</span>';
}

/* ---------- Flash message ---------- */

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function pull_flash(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/* ---------- CSRF ---------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $sent = $_POST['_csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        exit('Sesi kedaluwarsa. Silakan muat ulang halaman.');
    }
}

/* ---------- Auth ---------- */

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_admin(): bool
{
    return current_user() !== null;
}

function require_admin(): void
{
    if (!is_admin()) {
        flash('error', 'Anda harus masuk sebagai admin untuk aksi tersebut.');
        redirect('/login.php');
    }
}

/* ---------- Badge ---------- */

function badge(string $label, string $color): string
{
    return '<span class="badge badge-' . e($color) . '"><i></i>' . e($label) . '</span>';
}

function condition_badge(string $c): string
{
    $map = ['Baik' => 'green', 'Perlu Perbaikan' => 'yellow', 'Rusak' => 'red'];
    return badge($c, $map[$c] ?? 'gray');
}

function status_badge(string $s): string
{
    $map = ['Dilaporkan' => 'blue', 'Ditinjau' => 'purple', 'Dalam Perbaikan' => 'orange', 'Selesai' => 'green', 'Ditolak' => 'gray'];
    return badge($s, $map[$s] ?? 'gray');
}

function priority_badge(string $p): string
{
    $map = ['Rendah' => 'gray', 'Sedang' => 'blue', 'Tinggi' => 'orange', 'Darurat' => 'red'];
    return badge($p, $map[$p] ?? 'gray');
}

/* ---------- Pagination ---------- */

function paginate(int $total, int $perPage = 0): array
{
    $perPage = $perPage ?: (int)config('per_page', 10);
    $pages   = max(1, (int)ceil($total / $perPage));
    $page    = min(max(1, (int)($_GET['page'] ?? 1)), $pages);
    return ['total' => $total, 'page' => $page, 'pages' => $pages, 'per_page' => $perPage, 'offset' => ($page - 1) * $perPage];
}

function render_pagination(array $p): string
{
    if ($p['pages'] <= 1) {
        return '';
    }
    $link = function (int $page, string $label, bool $active = false, bool $disabled = false): string {
        if ($disabled) {
            return '<span class="pg disabled">' . $label . '</span>';
        }
        $q = $_GET;
        $q['page'] = $page;
        return '<a class="pg' . ($active ? ' active' : '') . '" href="?' . e(http_build_query($q)) . '">' . $label . '</a>';
    };
    $html = '<nav class="pagination">';
    $html .= $link($p['page'] - 1, '&larr;', false, $p['page'] <= 1);
    for ($i = 1; $i <= $p['pages']; $i++) {
        $html .= $link($i, (string)$i, $i === $p['page']);
    }
    $html .= $link($p['page'] + 1, '&rarr;', false, $p['page'] >= $p['pages']);
    return $html . '<span class="pg-info">' . $p['total'] . ' data</span></nav>';
}

/* ---------- Upload gambar ---------- */

/**
 * Simpan gambar upload. Return URL publik, null bila tidak ada file,
 * atau melempar RuntimeException bila file tidak valid.
 */
function upload_image(string $field): ?string
{
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload gagal, coba lagi.');
    }
    if ($f['size'] > (int)config('upload_max')) {
        throw new RuntimeException('Ukuran foto maksimal 3 MB.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Format foto harus JPG, PNG, atau WebP.');
    }
    $dir = config('upload_dir');
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $name = bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) {
        throw new RuntimeException('Gagal menyimpan foto.');
    }
    return config('upload_url') . '/' . $name;
}

/* ---------- Nomor tiket: MTC-YYYYMMDD-XXX ---------- */

function generate_ticket_number(PDO $pdo): string
{
    $prefix = 'MTC-' . date('Ymd') . '-';
    $stmt = $pdo->prepare('SELECT ticket_number FROM maintenance_tickets WHERE ticket_number LIKE ? ORDER BY ticket_number DESC LIMIT 1 FOR UPDATE');
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetchColumn();
    $seq = $last ? ((int)substr((string)$last, -3)) + 1 : 1;
    return $prefix . str_pad((string)$seq, 3, '0', STR_PAD_LEFT);
}

/* ---------- Konstanta enum ---------- */

const CATEGORIES = ['Furniture', 'Elektronik', 'AC', 'Sanitasi', 'Peralatan', 'Lainnya'];
const CONDITIONS = ['Baik', 'Perlu Perbaikan', 'Rusak'];
const ROLES      = ['Guru', 'Siswa', 'Staf', 'Lainnya'];
const PRIORITIES = ['Rendah', 'Sedang', 'Tinggi', 'Darurat'];
const STATUSES   = ['Dilaporkan', 'Ditinjau', 'Dalam Perbaikan', 'Selesai', 'Ditolak'];
