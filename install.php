<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

$error = '';
$installed = false;

try {
    $server = db(true);
    $server->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo = db();
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        employee_code VARCHAR(30) NOT NULL UNIQUE,
        name VARCHAR(120) NOT NULL,
        email VARCHAR(160) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin','employee') NOT NULL DEFAULT 'employee',
        department VARCHAR(100) NOT NULL DEFAULT '',
        position VARCHAR(100) NOT NULL DEFAULT '',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS attendance (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        attendance_date DATE NOT NULL,
        check_in TIME NULL,
        check_out TIME NULL,
        status ENUM('present','late') NOT NULL DEFAULT 'present',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY one_attendance_per_day (user_id, attendance_date),
        CONSTRAINT fk_attendance_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS leave_requests (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        reason TEXT NOT NULL,
        status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
        reviewed_by INT UNSIGNED NULL,
        reviewed_at DATETIME NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_leave_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_leave_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $installed = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
    if ($installed && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('index.php?page=login');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verifyCsrf();
        if ($installed) {
            $error = 'Admin sudah dibuat. Silakan masuk melalui halaman login.';
        } else {
            $name = trim((string) ($_POST['name'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
                $error = 'Isi nama dan email yang valid. Kata sandi minimal 8 karakter.';
            } else {
                $statement = $pdo->prepare('INSERT INTO users (employee_code, name, email, password, role, position) VALUES (?, ?, ?, ?, \'admin\', \'Administrator\')');
                $statement->execute(['ADM-001', $name, $email, password_hash($password, PASSWORD_DEFAULT)]);
                flash('Instalasi berhasil. Masuk dengan akun admin yang baru dibuat.');
                redirect('index.php?page=login');
            }
        }
    }
} catch (Throwable $exception) {
    $error = 'Database belum dapat dihubungkan. Pastikan MySQL XAMPP aktif dan pengaturan di config.php sesuai. Detail: ' . $exception->getMessage();
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Instalasi · RuangHadir</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/app.css">
</head>
<body class="auth-page">
    <main class="auth-layout">
        <section class="auth-story">
            <a class="brand brand-light" href="install.php"><span class="brand-mark">R</span><span>ruang<span class="brand-soft">hadir</span></span></a>
            <div class="story-copy"><span class="eyebrow">MULAI DARI SINI</span><h1>Ruang kerja,<br>lebih tertata.</h1><p>Buat akun administrator untuk mulai mengelola kehadiran dan tim Anda.</p></div>
            <div class="story-foot">PENCATATAN KEHADIRAN, TANPA RIBET.</div>
        </section>
        <section class="auth-panel"><div class="auth-form-wrap">
            <span class="eyebrow">PENGATURAN AWAL</span><h2>Pasang RuangHadir</h2><p class="muted">Buat akun admin pertama. Database akan disiapkan otomatis.</p>
            <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
            <?php if (!$installed || $_SERVER['REQUEST_METHOD'] === 'POST'): ?>
            <form method="post" class="form-stack">
                <?= csrfField() ?>
                <label>Nama administrator<input name="name" required autocomplete="name" placeholder="Nama lengkap"></label>
                <label>Email<input type="email" name="email" required autocomplete="email" placeholder="nama@perusahaan.id"></label>
                <label>Kata sandi<input type="password" name="password" required minlength="8" autocomplete="new-password" placeholder="Minimal 8 karakter"></label>
                <button class="button button-primary button-wide" type="submit">Buat akun admin <span aria-hidden="true">→</span></button>
            </form>
            <?php else: ?><div class="alert alert-success">Akun admin sudah tersedia. Installer terkunci.</div><a class="button button-primary button-wide" href="index.php?page=login">Lanjut ke login</a><?php endif; ?>
            <p class="auth-note">Konfigurasi database ada di <strong>config.php</strong>.</p>
        </div></section>
    </main>
</body>
</html>