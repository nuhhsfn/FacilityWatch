<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Jakarta');
session_start();
$GLOBALS['cfg'] = require __DIR__ . '/../config.php';

const CATEGORIES = ['Furniture', 'Elektronik', 'AC', 'Sanitasi', 'Peralatan', 'Lainnya'];
const CONDITIONS = ['Baik', 'Perlu Perbaikan', 'Rusak'];
const ROLES      = ['Guru', 'Siswa', 'Staf', 'Lainnya'];
const PRIORITIES = ['Rendah', 'Sedang', 'Tinggi', 'Darurat'];
const STATUSES   = ['Dilaporkan', 'Ditinjau', 'Dalam Perbaikan', 'Selesai', 'Ditolak'];

class ApiError extends Exception {
    public function __construct(string $m, public int $status = 400, public array $errors = []) { parent::__construct($m); }
}

function db(): PDO {
    static $pdo = null;
    if (!$pdo) {
        $c = $GLOBALS['cfg'];
        $pdo = new PDO("mysql:host={$c['db_host']};dbname={$c['db_name']};charset=utf8mb4", $c['db_user'], $c['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function app_base_path(): string {
    static $base = null;
    if ($base === null) {
        $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
        $directory = dirname($script);
        $base = $directory === '/' || $directory === '.' ? '' : $directory;
    }
    return $base;
}
function app_url(string $path): string {
    return app_base_path() . '/' . ltrim($path, '/');
}
function is_admin(): bool { return ($_SESSION['role'] ?? '') === 'admin' || !empty($_SESSION['admin']); }
function is_user(): bool { return ($_SESSION['role'] ?? '') === 'user'; }
function is_authenticated(): bool { return is_admin() || is_user(); }
function current_username(): string { return (string)($_SESSION['username'] ?? ''); }
function authenticate(string $username, string $password, ?string $requiredRole = null): bool {
    $c = $GLOBALS['cfg'];
    if ($requiredRole !== 'user' && hash_equals($c['admin_user'], $username) && hash_equals($c['admin_pass'], $password)) {
        session_regenerate_id(true);
        $_SESSION['role'] = 'admin';
        $_SESSION['username'] = $username;
        unset($_SESSION['admin']);
        return true;
    }
    if ($requiredRole === 'admin') return false;

    $st = db()->prepare('SELECT username, password_hash, role FROM users WHERE username = ?');
    $st->execute([$username]);
    $account = $st->fetch();
    if (!$account || $account['role'] !== 'user' || !password_verify($password, $account['password_hash'])) return false;

    session_regenerate_id(true);
    $_SESSION['role'] = $account['role'];
    $_SESSION['username'] = $account['username'];
    unset($_SESSION['admin']);
    return true;
}
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(16)); }
function check_csrf(): void {
    if (!hash_equals(csrf(), (string)($_POST['_csrf'] ?? ''))) { http_response_code(419); exit('CSRF token tidak valid.'); }
}
function flash(string $type, string $msg): void { $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg]; }
function redirect(string $to): never { header('Location: ' . app_url($to)); exit; }

/** Validasi sederhana. Aturan: required|int|min:N|max:N|minlen:N|in:a,b */
function validate(array $in, array $rules): array {
    $out = []; $err = [];
    foreach ($rules as $f => $rule) {
        $v = $in[$f] ?? null;
        if (is_string($v)) $v = trim($v);
        $rs = explode('|', $rule);
        if ($v === null || $v === '') {
            if (in_array('required', $rs, true)) $err[$f] = "$f wajib diisi"; else $out[$f] = null;
            continue;
        }
        foreach ($rs as $r) {
            [$n, $a] = array_pad(explode(':', $r, 2), 2, null);
            if ($n === 'int') {
                if (filter_var($v, FILTER_VALIDATE_INT) === false) { $err[$f] = "$f harus berupa angka"; continue 2; }
                $v = (int)$v;
            } elseif ($n === 'min' && (int)$v < (int)$a) { $err[$f] = "$f minimal $a"; continue 2; }
            elseif ($n === 'max' && mb_strlen((string)$v) > (int)$a) { $err[$f] = "$f maksimal $a karakter"; continue 2; }
            elseif ($n === 'minlen' && mb_strlen((string)$v) < (int)$a) { $err[$f] = "$f minimal $a karakter"; continue 2; }
            elseif ($n === 'in' && !in_array((string)$v, explode(',', $a), true)) { $err[$f] = "$f tidak valid"; continue 2; }
        }
        $out[$f] = $v;
    }
    return [$out, $err];
}

function save_upload(string $field): ?string {
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) return null;
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) throw new ApiError('Upload foto gagal', 422);
    if ($f['size'] > 2 * 1024 * 1024) throw new ApiError('Ukuran foto maksimal 2 MB', 422);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name'])] ?? null;
    if (!$ext) throw new ApiError('Format foto harus JPG, PNG, atau WEBP', 422);
    $dir = __DIR__ . '/../public/uploads';
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $name = bin2hex(random_bytes(8)) . ".$ext";
    move_uploaded_file($f['tmp_name'], "$dir/$name");
    return "/uploads/$name";
}
