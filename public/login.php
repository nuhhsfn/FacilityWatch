<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

if (is_admin()) {
    redirect('/dashboard.php');
}

$error = '';
if (is_post()) {
    csrf_verify();
    $username = (string)input('username');
    $password = (string)($_POST['password'] ?? '');

    $stmt = db()->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user'] = ['id' => $user['id'], 'username' => $user['username'], 'full_name' => $user['full_name'], 'role' => $user['role']];
        flash('success', 'Selamat datang, ' . $user['full_name'] . '!');
        redirect('/dashboard.php');
    }
    $error = 'Username atau password salah.';
}

$title = 'Masuk Admin';
$bodyClass = 'app';
require __DIR__ . '/../includes/header.php';
?>
<main class="container page">
  <div class="card auth-box">
    <h1 style="font-size:28px;margin-bottom:6px">Masuk Admin</h1>
    <p class="muted" style="margin-bottom:22px">Kelola ruangan, aset, dan tiket pemeliharaan.</p>
    <?php if ($error): ?><div class="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post" autocomplete="on">
      <?= csrf_field() ?>
      <div class="field"><label for="username">Username</label><input class="input" id="username" name="username" required autofocus value="<?= e(input('username')) ?>"></div>
      <div class="field"><label for="password">Password</label><input class="input" id="password" type="password" name="password" required></div>
      <button class="btn btn-dark" style="width:100%" type="submit">Masuk</button>
    </form>
  </div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
