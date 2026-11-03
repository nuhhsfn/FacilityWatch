<?php
declare(strict_types=1);

function api_out(bool $ok, $data, string $msg, int $code = 200, ?array $meta = null): never {
    http_response_code($code);
    $r = ['success' => $ok, 'data' => $data, 'message' => $msg];
    if ($meta) $r['meta'] = $meta;
    echo json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function api_dispatch(string $m, string $path): void {
    header('Content-Type: application/json; charset=utf-8');
    $t0 = microtime(true);
    // Middleware: request logging
    register_shutdown_function(function () use ($m, $path, $t0) {
        @file_put_contents(__DIR__ . '/../storage/requests.log',
            sprintf("%s %s %s %d %dms\n", date('c'), $m, $path, http_response_code(), (microtime(true) - $t0) * 1000), FILE_APPEND);
    });

    $in = in_array($m, ['GET', 'DELETE'], true) ? [] :
        (str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'json') ? (json_decode(file_get_contents('php://input') ?: '[]', true) ?: []) : $_POST);
    $admin = function () { if (!is_admin()) throw new ApiError('Akses admin diperlukan. Login via POST /api/login', 401); };
    $list = fn(array $r) => ['data' => $r['items'], 'meta' => $r['meta']];

    $routes = [
        ['POST', '#^/api/register$#', function () use ($in) {
            user_register(trim((string)($in['username'] ?? '')), (string)($in['password'] ?? ''));
            if (!authenticate(trim((string)$in['username']), (string)$in['password']))
                throw new ApiError('Akun berhasil dibuat, tetapi login gagal.', 500);
            return ['message' => 'Akun pengguna berhasil dibuat', 'code' => 201];
        }],
        ['POST', '#^/api/login$#', function () use ($in) {
            if (authenticate((string)($in['username'] ?? ''), (string)($in['password'] ?? '')))
                return ['data' => ['role' => $_SESSION['role']], 'message' => 'Login berhasil'];
            throw new ApiError('Username atau password salah', 401);
        }],
        // Rooms
        ['GET', '#^/api/rooms$#', fn() => $list(rooms_list($_GET))],
        ['GET', '#^/api/rooms/(\d+)$#', fn(int $id) => ['data' => room_get($id)]],
        ['POST', '#^/api/rooms$#', function () use ($admin, $in) { $admin(); return ['data' => ['id' => room_save(null, $in)], 'message' => 'Ruangan dibuat', 'code' => 201]; }],
        ['PUT', '#^/api/rooms/(\d+)$#', function (int $id) use ($admin, $in) { $admin(); room_save($id, $in); return ['data' => room_get($id), 'message' => 'Ruangan diperbarui']; }],
        ['DELETE', '#^/api/rooms/(\d+)$#', function (int $id) use ($admin) { $admin(); delete_row('rooms', $id); return ['message' => 'Ruangan dihapus']; }],
        // Assets
        ['GET', '#^/api/assets$#', fn() => $list(assets_list($_GET))],
        ['GET', '#^/api/assets/(\d+)$#', fn(int $id) => ['data' => asset_get($id)]],
        ['POST', '#^/api/assets$#', function () use ($admin, $in) { $admin(); return ['data' => ['id' => asset_save(null, $in)], 'message' => 'Aset dibuat', 'code' => 201]; }],
        ['PUT', '#^/api/assets/(\d+)$#', function (int $id) use ($admin, $in) { $admin(); asset_save($id, $in); return ['data' => asset_get($id), 'message' => 'Aset diperbarui']; }],
        ['DELETE', '#^/api/assets/(\d+)$#', function (int $id) use ($admin) { $admin(); delete_row('facility_assets', $id); return ['message' => 'Aset dihapus']; }],
        // Tickets (pelaporan terbuka untuk warga sekolah)
        ['GET', '#^/api/tickets$#', fn() => $list(tickets_list($_GET))],
        ['GET', '#^/api/tickets/(\d+)$#', fn(int $id) => ['data' => ticket_get($id)]],
        ['POST', '#^/api/tickets$#', function () use ($in) { $id = ticket_create($in); return ['data' => ticket_get($id), 'message' => 'Tiket dibuat', 'code' => 201]; }],
        ['PUT', '#^/api/tickets/(\d+)$#', function (int $id) use ($admin, $in) { $admin(); ticket_update($id, $in); return ['data' => ticket_get($id), 'message' => 'Tiket diperbarui']; }],
        ['DELETE', '#^/api/tickets/(\d+)$#', function (int $id) use ($admin) { $admin(); delete_row('maintenance_tickets', $id); return ['message' => 'Tiket dihapus']; }],
        // Dashboard
        ['GET', '#^/api/dashboard/stats$#', fn() => ['data' => dashboard_stats()]],
    ];

    try {
        foreach ($routes as [$rm, $re, $fn]) {
            if ($rm === $m && preg_match($re, rtrim($path, '/'), $mm)) {
                $r = $fn(...array_map('intval', array_slice($mm, 1)));
                api_out(true, $r['data'] ?? null, $r['message'] ?? 'OK', $r['code'] ?? 200, $r['meta'] ?? null);
            }
        }
        throw new ApiError('Endpoint tidak ditemukan', 404);
    } catch (ApiError $e) {
        api_out(false, $e->errors ? ['errors' => $e->errors] : null, $e->getMessage(), $e->status);
    } catch (Throwable $e) {
        error_log($e->getMessage());
        api_out(false, null, 'Terjadi kesalahan pada server', 500);
    }
}
