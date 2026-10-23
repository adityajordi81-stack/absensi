<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

$page = (string) ($_GET['page'] ?? (currentUser() ? 'dashboard' : 'login'));
$allowedPages = ['login', 'dashboard', 'attendance', 'employees', 'leave', 'reports', 'profile'];
if (!in_array($page, $allowedPages, true)) {
    $page = 'dashboard';
}

try {
    $pdo = db();
    $user = currentUser();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verifyCsrf();
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'login') {
            $email = trim((string) ($_POST['email'] ?? ''));
            $statement = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
            $statement->execute([$email]);
            $account = $statement->fetch();
            if ($account && password_verify((string) ($_POST['password'] ?? ''), $account['password'])) {
                session_regenerate_id(true);
                unset($account['password']);
                $_SESSION['user'] = $account;
                redirect('index.php?page=dashboard');
            }
            flash('Email atau kata sandi tidak cocok.', 'error');
            redirect('index.php?page=login');
        }

        $user = requireLogin();
        if ($action === 'logout') {
            $_SESSION = [];
            session_destroy();
            redirect('index.php?page=login');
        }

        if ($action === 'check_in') {
            $now = date('H:i:s');
            $status = $now > WORK_START ? 'late' : 'present';
            try {
                $statement = $pdo->prepare('INSERT INTO attendance (user_id, attendance_date, check_in, status) VALUES (?, CURDATE(), CURTIME(), ?)');
                $statement->execute([$user['id'], $status]);
                flash($status === 'late' ? 'Absensi masuk tercatat. Anda datang setelah pukul 08.00.' : 'Absensi masuk berhasil dicatat.');
            } catch (PDOException $exception) {
                flash('Absensi masuk hari ini sudah tercatat.', 'error');
            }
            redirect('index.php?page=dashboard');
        }

        if ($action === 'check_out') {
            $statement = $pdo->prepare('UPDATE attendance SET check_out = CURTIME() WHERE user_id = ? AND attendance_date = CURDATE() AND check_in IS NOT NULL AND check_out IS NULL');
            $statement->execute([$user['id']]);
            flash($statement->rowCount() ? 'Absensi pulang berhasil dicatat.' : 'Belum ada absensi masuk atau absensi pulang sudah tercatat.', $statement->rowCount() ? 'success' : 'error');
            redirect('index.php?page=dashboard');
        }

        if ($action === 'save_employee') {
            requireAdmin();
            $id = (int) ($_POST['id'] ?? 0);
            $code = strtoupper(trim((string) ($_POST['employee_code'] ?? '')));
            $name = trim((string) ($_POST['name'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $department = trim((string) ($_POST['department'] ?? ''));
            $position = trim((string) ($_POST['position'] ?? ''));
            $role = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'employee';
            $password = (string) ($_POST['password'] ?? '');

            if ($code === '' || $name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                flash('Kode, nama, dan email valid wajib diisi.', 'error');
                redirect('index.php?page=employees' . ($id ? '&edit=' . $id : ''));
            }
            try {
                if ($id) {
                    if ($password !== '' && strlen($password) < 8) {
                        flash('Kata sandi baru minimal 8 karakter.', 'error');
                        redirect('index.php?page=employees&edit=' . $id);
                    }
                    $sql = 'UPDATE users SET employee_code=?, name=?, email=?, department=?, position=?, role=?';
                    $values = [$code, $name, $email, $department, $position, $role];
                    if ($password !== '') {
                        $sql .= ', password=?';
                        $values[] = password_hash($password, PASSWORD_DEFAULT);
                    }
                    $sql .= ' WHERE id=?';
                    $values[] = $id;
                    $pdo->prepare($sql)->execute($values);
                    flash('Data karyawan berhasil diperbarui.');
                } else {
                    if (strlen($password) < 8) {
                        flash('Kata sandi minimal 8 karakter.', 'error');
                        redirect('index.php?page=employees');
                    }
                    $statement = $pdo->prepare('INSERT INTO users (employee_code, name, email, password, role, department, position) VALUES (?, ?, ?, ?, ?, ?, ?)');
                    $statement->execute([$code, $name, $email, password_hash($password, PASSWORD_DEFAULT), $role, $department, $position]);
                    flash('Karyawan baru berhasil ditambahkan.');
                }
            } catch (PDOException $exception) {
                flash('Kode karyawan atau email sudah digunakan.', 'error');
            }
            redirect('index.php?page=employees');
        }

        if ($action === 'delete_employee') {
            requireAdmin();
            $id = (int) ($_POST['id'] ?? 0);
            if ($id === (int) $user['id']) {
                flash('Akun yang sedang digunakan tidak dapat dihapus.', 'error');
            } else {
                $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
                flash('Akun karyawan berhasil dihapus.');
            }
            redirect('index.php?page=employees');
        }

        if ($action === 'submit_leave') {
            $start = (string) ($_POST['start_date'] ?? '');
            $end = (string) ($_POST['end_date'] ?? '');
            $reason = trim((string) ($_POST['reason'] ?? ''));
            if (!$start || !$end || $end < $start || $reason === '') {
                flash('Tanggal dan alasan izin harus diisi dengan benar.', 'error');
            } else {
                $statement = $pdo->prepare('INSERT INTO leave_requests (user_id, start_date, end_date, reason) VALUES (?, ?, ?, ?)');
                $statement->execute([$user['id'], $start, $end, $reason]);
                flash('Pengajuan izin berhasil dikirim.');
            }
            redirect('index.php?page=leave');
        }

        if ($action === 'review_leave') {
            requireAdmin();
            $decision = ($_POST['decision'] ?? '') === 'approved' ? 'approved' : 'rejected';
            $statement = $pdo->prepare("UPDATE leave_requests SET status=?, reviewed_by=?, reviewed_at=NOW() WHERE id=? AND status='pending'");
            $statement->execute([$decision, $user['id'], (int) ($_POST['id'] ?? 0)]);
            flash($statement->rowCount() ? 'Pengajuan izin telah ' . statusLabel($decision) . '.' : 'Pengajuan sudah diproses sebelumnya.', $statement->rowCount() ? 'success' : 'error');
            redirect('index.php?page=leave');
        }

        if ($action === 'update_profile') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $department = trim((string) ($_POST['department'] ?? ''));
            $position = trim((string) ($_POST['position'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            if ($name === '' || ($password !== '' && strlen($password) < 8)) {
                flash('Nama wajib diisi dan kata sandi baru minimal 8 karakter.', 'error');
            } else {
                if ($password !== '') {
                    $pdo->prepare('UPDATE users SET name=?, department=?, position=?, password=? WHERE id=?')->execute([$name, $department, $position, password_hash($password, PASSWORD_DEFAULT), $user['id']]);
                } else {
                    $pdo->prepare('UPDATE users SET name=?, department=?, position=? WHERE id=?')->execute([$name, $department, $position, $user['id']]);
                }
                $_SESSION['user']['name'] = $name;
                $_SESSION['user']['department'] = $department;
                $_SESSION['user']['position'] = $position;
                flash('Profil berhasil diperbarui.');
            }
            redirect('index.php?page=profile');
        }
    }

    if ($page !== 'login') {
        $user = requireLogin();
    } elseif (currentUser()) {
        redirect('index.php?page=dashboard');
    }

    if ($page === 'login') {
        $notice = pullFlash();
        ?>
        <!doctype html><html lang="id"><head>
            <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Masuk · <?= e(APP_NAME) ?></title>
            <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
            <link rel="stylesheet" href="assets/app.css">
        </head><body class="auth-page"><main class="auth-layout">
            <section class="auth-story"><a class="brand brand-light" href="index.php"><span class="brand-mark">R</span><span>ruang<span class="brand-soft">hadir</span></span></a>
                <div class="story-copy"><span class="eyebrow">WORK, IN GOOD RHYTHM</span><h1>Hari yang baik<br>dimulai dari hadir.</h1><p>Kelola kehadiran tim, catat waktumu, dan jaga semua tetap selaras.</p></div>
                <div class="story-foot">RUANGHADIR <span>·</span> WORKPLACE, IN SYNC</div>
            </section>
            <section class="auth-panel"><div class="auth-form-wrap"><span class="eyebrow">SELAMAT DATANG KEMBALI</span><h2>Masuk ke akunmu</h2><p class="muted">Lanjutkan aktivitas kerja hari ini.</p>
                <?php if ($notice): ?><div class="alert alert-<?= e($notice['type']) ?>"><?= e($notice['message']) ?></div><?php endif; ?>
                <form method="post" class="form-stack"><?= csrfField() ?><input type="hidden" name="action" value="login">
                    <label>Email<input name="email" type="email" required autofocus autocomplete="username" placeholder="nama@perusahaan.id"></label>
                    <label>Kata sandi<input name="password" type="password" required autocomplete="current-password" placeholder="Masukkan kata sandi"></label>
                    <button class="button button-primary button-wide" type="submit">Masuk <span aria-hidden="true">→</span></button>
                </form><p class="auth-note">Belum memasang aplikasi? <a href="install.php">Mulai instalasi</a></p>
            </div></section>
        </main></body></html>
        <?php
        exit;
    }

    $isAdmin = $user['role'] === 'admin';
    $today = date('Y-m-d');
    $todayStatement = $pdo->prepare('SELECT * FROM attendance WHERE user_id=? AND attendance_date=CURDATE()');
    $todayStatement->execute([$user['id']]);
    $todayAttendance = $todayStatement->fetch() ?: null;
    $flashMessage = pullFlash();

    if ($page === 'reports' && $isAdmin && isset($_GET['export'])) {
        $from = $_GET['from'] ?? date('Y-m-01');
        $to = $_GET['to'] ?? $today;
        $statement = $pdo->prepare('SELECT u.employee_code, u.name, u.department, a.attendance_date, a.check_in, a.check_out, a.status FROM attendance a JOIN users u ON u.id=a.user_id WHERE a.attendance_date BETWEEN ? AND ? ORDER BY a.attendance_date DESC, u.name');
        $statement->execute([$from, $to]);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="laporan-absensi-' . $from . '-sampai-' . $to . '.csv"');
        $output = fopen('php://output', 'w');
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, ['Kode', 'Nama', 'Departemen', 'Tanggal', 'Masuk', 'Pulang', 'Status'], ';');
        foreach ($statement as $row) {
            fputcsv($output, [$row['employee_code'], $row['name'], $row['department'], $row['attendance_date'], $row['check_in'], $row['check_out'], statusLabel($row['status'])], ';');
        }
        fclose($output);
        exit;
    }

    $pageTitles = [
        'dashboard' => ['Ringkasan', 'Pantau ritme kerja tim hari ini.'],
        'attendance' => ['Catatan kehadiran', 'Riwayat kehadiran yang tercatat.'],
        'employees' => ['Data karyawan', 'Kelola anggota dan akses tim.'],
        'leave' => ['Izin & cuti', 'Pengajuan dan keputusan izin tim.'],
        'reports' => ['Laporan', 'Rekap kehadiran untuk rentang tanggal.'],
        'profile' => ['Profil saya', 'Kelola informasi akun dan keamanan.'],
    ];
    [$title, $subtitle] = $pageTitles[$page] ?? $pageTitles['dashboard'];

    $todayTotal = $presentTotal = $lateTotal = $pendingLeaves = 0;
    $recentAttendance = $attendanceRows = $employees = $leaveRows = [];
    $editEmployee = null;
    $filterDate = (string) ($_GET['date'] ?? $today);
    $from = (string) ($_GET['from'] ?? date('Y-m-01'));
    $to = (string) ($_GET['to'] ?? $today);

    if ($page === 'dashboard') {
        if ($isAdmin) {
            $todayTotal = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE role=\'employee\'')->fetchColumn();
            $presentTotal = (int) $pdo->query("SELECT COUNT(*) FROM attendance WHERE attendance_date=CURDATE()")->fetchColumn();
            $lateTotal = (int) $pdo->query("SELECT COUNT(*) FROM attendance WHERE attendance_date=CURDATE() AND status='late'")->fetchColumn();
            $pendingLeaves = (int) $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status='pending'")->fetchColumn();
            $recentAttendance = $pdo->query('SELECT a.*, u.name, u.employee_code, u.department FROM attendance a JOIN users u ON u.id=a.user_id WHERE a.attendance_date=CURDATE() ORDER BY a.check_in DESC LIMIT 8')->fetchAll();
        } else {
            $statement = $pdo->prepare('SELECT * FROM attendance WHERE user_id=? ORDER BY attendance_date DESC LIMIT 5');
            $statement->execute([$user['id']]);
            $recentAttendance = $statement->fetchAll();
        }
    }

    if ($page === 'attendance') {
        if ($isAdmin) {
            $statement = $pdo->prepare('SELECT a.*, u.name, u.employee_code, u.department FROM attendance a JOIN users u ON u.id=a.user_id WHERE a.attendance_date=? ORDER BY u.name');
            $statement->execute([$filterDate]);
        } else {
            $statement = $pdo->prepare('SELECT * FROM attendance WHERE user_id=? ORDER BY attendance_date DESC LIMIT 60');
            $statement->execute([$user['id']]);
        }
        $attendanceRows = $statement->fetchAll();
    }

    if ($page === 'employees' && $isAdmin) {
        $employees = $pdo->query('SELECT id, employee_code, name, email, role, department, position, created_at FROM users ORDER BY role, name')->fetchAll();
        if (isset($_GET['edit'])) {
            $statement = $pdo->prepare('SELECT id, employee_code, name, email, role, department, position FROM users WHERE id=?');
            $statement->execute([(int) $_GET['edit']]);
            $editEmployee = $statement->fetch() ?: null;
        }
    }

    if ($page === 'leave') {
        if ($isAdmin) {
            $leaveRows = $pdo->query('SELECT l.*, u.name, u.employee_code FROM leave_requests l JOIN users u ON u.id=l.user_id ORDER BY l.created_at DESC')->fetchAll();
            $pendingLeaves = (int) $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status='pending'")->fetchColumn();
        } else {
            $statement = $pdo->prepare('SELECT * FROM leave_requests WHERE user_id=? ORDER BY created_at DESC');
            $statement->execute([$user['id']]);
            $leaveRows = $statement->fetchAll();
        }
    }

    if ($page === 'reports' && $isAdmin) {
        $statement = $pdo->prepare('SELECT u.employee_code, u.name, u.department, a.attendance_date, a.check_in, a.check_out, a.status FROM attendance a JOIN users u ON u.id=a.user_id WHERE a.attendance_date BETWEEN ? AND ? ORDER BY a.attendance_date DESC, u.name');
        $statement->execute([$from, $to]);
        $attendanceRows = $statement->fetchAll();
    }

    $navItems = [
        ['dashboard', 'Ikhtisar', '◫'],
        ['attendance', 'Kehadiran', '◷'],
        ['leave', 'Izin & cuti', '▤'],
    ];
    if ($isAdmin) {
        $navItems[] = ['employees', 'Karyawan', '♧'];
        $navItems[] = ['reports', 'Laporan', '▥'];
    }
    ?>
    <!doctype html><html lang="id"><head>
        <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= e($title) ?> · <?= e(APP_NAME) ?></title>
        <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="assets/app.css"><script src="assets/app.js" defer></script>
    </head><body class="app-page">
        <aside class="sidebar" id="sidebar">
            <a class="brand" href="index.php?page=dashboard"><span class="brand-mark">R</span><span>ruang<span class="brand-soft">hadir</span></span></a>
            <div class="workspace-label">WORKSPACE</div><div class="workspace"><span class="workspace-dot"></span><span><strong>Tim utama</strong><small>Workspace aktif</small></span><span class="workspace-menu">⌄</span></div>
            <nav class="main-nav" aria-label="Navigasi utama"><span class="nav-label">MENU</span>
                <?php foreach ($navItems as [$navPage, $navLabel, $navIcon]): ?>
                    <a class="nav-link <?= $page === $navPage ? 'active' : '' ?>" href="index.php?page=<?= e($navPage) ?>"><span class="nav-icon"><?= e($navIcon) ?></span><span><?= e($navLabel) ?></span><?php if ($navPage === 'leave' && $pendingLeaves > 0 && $isAdmin): ?><span class="nav-count"><?= $pendingLeaves ?></span><?php endif; ?></a>
                <?php endforeach; ?>
            </nav>
            <div class="sidebar-bottom"><div class="sidebar-help"><span class="help-icon">?</span><div><strong>Butuh bantuan?</strong><small>Hubungi admin tim kamu</small></div></div>
                <a class="user-mini" href="index.php?page=profile"><span class="avatar"><?= e(strtoupper(substr($user['name'], 0, 1))) ?></span><span class="user-mini-copy"><strong><?= e($user['name']) ?></strong><small><?= $isAdmin ? 'Administrator' : e($user['position'] ?: 'Karyawan') ?></small></span><span class="more">···</span></a>
            </div>
        </aside>
        <div class="main-shell">
            <header class="topbar"><button class="icon-button menu-toggle" data-menu-toggle aria-label="Buka menu">☰</button><div class="breadcrumb">RuangHadir <span>/</span> <strong><?= e($title) ?></strong></div><div class="topbar-right"><span class="top-date"><span class="online-dot"></span><?= e(date('l, d M Y')) ?></span><a class="top-avatar" href="index.php?page=profile" title="Profil <?= e($user['name']) ?>"><?= e(strtoupper(substr($user['name'], 0, 1))) ?></a></div></header>
            <main class="content"><div class="page-heading"><div><span class="eyebrow"><?= $isAdmin ? 'PANEL ADMIN' : 'AREA KARYAWAN' ?></span><h1><?= e($title) ?></h1><p><?= e($subtitle) ?></p></div><?php if ($page === 'dashboard'): ?><div class="today-chip"><span class="calendar-icon">▦</span><span><small>HARI INI</small><strong><?= e(date('d M Y')) ?></strong></span></div><?php endif; ?></div>
                <?php if ($flashMessage): ?><div class="alert alert-<?= e($flashMessage['type']) ?> dismissible"><?= e($flashMessage['message']) ?><button type="button" data-dismiss aria-label="Tutup">×</button></div><?php endif; ?>

                <?php if ($page === 'dashboard' && $isAdmin): ?>
                <section class="metric-grid">
                    <article class="metric-card metric-ink"><div class="metric-top"><span>Total karyawan</span><span class="metric-icon">♧</span></div><strong class="metric-value"><?= $todayTotal ?></strong><span class="metric-note">anggota aktif dalam tim</span><span class="metric-spark spark-a"></span><span class="metric-spark spark-b"></span></article>
                    <article class="metric-card"><div class="metric-top"><span>Hadir hari ini</span><span class="metric-icon icon-green">↗</span></div><strong class="metric-value"><?= $presentTotal ?><small> / <?= $todayTotal ?></small></strong><span class="metric-note">termasuk <?= $lateTotal ?> terlambat</span><div class="metric-progress"><i style="width:<?= $todayTotal ? min(100, (int) round($presentTotal / $todayTotal * 100)) : 0 ?>%"></i></div></article>
                    <article class="metric-card"><div class="metric-top"><span>Belum tercatat</span><span class="metric-icon icon-amber">◷</span></div><strong class="metric-value"><?= max(0, $todayTotal - $presentTotal) ?></strong><span class="metric-note">belum melakukan absensi masuk</span><span class="metric-inline-dot"></span></article>
                    <article class="metric-card"><div class="metric-top"><span>Pengajuan izin</span><span class="metric-icon icon-coral">▤</span></div><strong class="metric-value"><?= $pendingLeaves ?></strong><span class="metric-note">menunggu keputusan admin</span><?php if ($pendingLeaves): ?><a class="metric-link" href="index.php?page=leave">Tinjau pengajuan <span>→</span></a><?php endif; ?></article>
                </section>
                <section class="panel attendance-panel"><div class="panel-heading"><div><h2>Aktivitas kehadiran</h2><p>Pencatatan masuk tim untuk hari ini</p></div><a class="text-link" href="index.php?page=attendance&date=<?= e($today) ?>">Lihat semua <span>→</span></a></div>
                    <?php if ($recentAttendance): ?><div class="table-wrap"><table><thead><tr><th>KARYAWAN</th><th>DEPARTEMEN</th><th>JAM MASUK</th><th>JAM PULANG</th><th>STATUS</th></tr></thead><tbody><?php foreach ($recentAttendance as $row): ?><tr><td><div class="person-cell"><span class="avatar avatar-small"><?= e(strtoupper(substr($row['name'], 0, 1))) ?></span><span><strong><?= e($row['name']) ?></strong><small><?= e($row['employee_code']) ?></small></span></div></td><td><?= e($row['department'] ?: '—') ?></td><td class="time-cell"><?= e(formatTime($row['check_in'])) ?></td><td class="time-cell"><?= e(formatTime($row['check_out'])) ?></td><td><span class="<?= e(statusClass($row['status'])) ?>"><i></i><?= e(statusLabel($row['status'])) ?></span></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><div class="empty-state"><span class="empty-symbol">◷</span><strong>Belum ada catatan hari ini</strong><p>Catatan absensi masuk akan muncul di sini.</p></div><?php endif; ?>
                </section>

                <?php elseif ($page === 'dashboard'): ?>
                <section class="employee-welcome"><div class="welcome-content"><span class="eyebrow">HARI KERJA YANG BAIK</span><h2>Halo, <?= e(explode(' ', $user['name'])[0]) ?> <span>✳</span></h2><p>Catat kehadiranmu dan mulai hari dengan ritme yang baik.</p><div class="welcome-meta"><span>◷ &nbsp; Jam kerja dimulai 08.00</span><span>⌖ &nbsp; <?= e($user['department'] ?: 'Tim utama') ?></span></div></div><div class="welcome-art" aria-hidden="true"><div class="sun-disc"></div><div class="art-line art-line-one"></div><div class="art-line art-line-two"></div><div class="art-stem"></div></div></section>
                <section class="checkin-layout"><article class="panel checkin-panel"><div class="panel-heading"><div><span class="eyebrow">ABSENSI HARI INI</span><h2><?= e(date('l, d F Y')) ?></h2></div><span class="live-indicator"><i></i> LIVE</span></div><div class="clock-display" data-clock><?= e(date('H:i:s')) ?></div><div class="checkin-status"><?php if (!$todayAttendance): ?><span class="status status-muted"><i></i>Belum absen masuk</span><?php elseif (!$todayAttendance['check_out']): ?><span class="<?= e(statusClass($todayAttendance['status'])) ?>"><i></i>Masuk <?= e(formatTime($todayAttendance['check_in'])) ?></span><?php else: ?><span class="status status-green"><i></i>Hari kerja selesai</span><?php endif; ?></div><div class="checkin-actions"><?php if (!$todayAttendance): ?><form method="post"><?= csrfField() ?><input type="hidden" name="action" value="check_in"><button class="button button-primary button-checkin" type="submit"><span>↗</span> Absen masuk</button></form><?php elseif (!$todayAttendance['check_out']): ?><form method="post"><?= csrfField() ?><input type="hidden" name="action" value="check_out"><button class="button button-dark button-checkin" type="submit"><span>↙</span> Absen pulang</button></form><?php else: ?><button class="button button-disabled button-checkin" disabled>Absensi hari ini selesai</button><?php endif; ?></div><p class="checkin-footnote">Pastikan waktu perangkatmu sudah sesuai.</p></article>
                    <article class="panel today-info"><div class="panel-heading"><div><span class="eyebrow">RINGKASAN</span><h2>Hari ini</h2></div><span class="summary-icon">✳</span></div><div class="summary-row"><span>Waktu masuk</span><strong><?= e(formatTime($todayAttendance['check_in'] ?? null)) ?></strong></div><div class="summary-row"><span>Waktu pulang</span><strong><?= e(formatTime($todayAttendance['check_out'] ?? null)) ?></strong></div><div class="summary-row"><span>Status</span><?php if ($todayAttendance): ?><span class="<?= e(statusClass($todayAttendance['status'])) ?>"><i></i><?= e(statusLabel($todayAttendance['status'])) ?></span><?php else: ?><span class="status status-muted"><i></i>Belum tercatat</span><?php endif; ?></div><a class="summary-link" href="index.php?page=attendance">Lihat riwayat kehadiran <span>→</span></a></article></section>
                    <section class="panel attendance-panel"><div class="panel-heading"><div><h2>Riwayat terbaru</h2><p>Lima catatan kehadiran terakhirmu</p></div><a class="text-link" href="index.php?page=attendance">Semua riwayat <span>→</span></a></div><?php if ($recentAttendance): ?><div class="table-wrap"><table><thead><tr><th>TANGGAL</th><th>JAM MASUK</th><th>JAM PULANG</th><th>STATUS</th></tr></thead><tbody><?php foreach ($recentAttendance as $row): ?><tr><td class="date-cell"><?= e(formatDate($row['attendance_date'])) ?></td><td class="time-cell"><?= e(formatTime($row['check_in'])) ?></td><td class="time-cell"><?= e(formatTime($row['check_out'])) ?></td><td><span class="<?= e(statusClass($row['status'])) ?>"><i></i><?= e(statusLabel($row['status'])) ?></span></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><div class="empty-state"><span class="empty-symbol">◷</span><strong>Belum ada riwayat</strong><p>Mulai dengan mencatat kehadiran hari ini.</p></div><?php endif; ?></section>

                <?php elseif ($page === 'attendance'): ?>
                <section class="panel"><div class="panel-heading"><div><h2><?= $isAdmin ? 'Kehadiran tim' : 'Riwayat saya' ?></h2><p><?= $isAdmin ? 'Pilih tanggal untuk meninjau catatan tim.' : 'Semua catatan kehadiranmu.' ?></p></div><?php if ($isAdmin): ?><form class="inline-filter" method="get"><input type="hidden" name="page" value="attendance"><input type="date" name="date" value="<?= e($filterDate) ?>"><button class="button button-small" type="submit">Tampilkan</button></form><?php endif; ?></div>
                    <?php if ($attendanceRows): ?><div class="table-wrap"><table><thead><tr><?php if ($isAdmin): ?><th>KARYAWAN</th><?php endif; ?><th>TANGGAL</th><?php if ($isAdmin): ?><th>DEPARTEMEN</th><?php endif; ?><th>MASUK</th><th>PULANG</th><th>STATUS</th></tr></thead><tbody><?php foreach ($attendanceRows as $row): ?><tr><?php if ($isAdmin): ?><td><div class="person-cell"><span class="avatar avatar-small"><?= e(strtoupper(substr($row['name'], 0, 1))) ?></span><span><strong><?= e($row['name']) ?></strong><small><?= e($row['employee_code']) ?></small></span></div><?php endif; ?><td class="date-cell"><?= e(formatDate($row['attendance_date'])) ?></td><?php if ($isAdmin): ?><td><?= e($row['department'] ?: '—') ?></td><?php endif; ?><td class="time-cell"><?= e(formatTime($row['check_in'])) ?></td><td class="time-cell"><?= e(formatTime($row['check_out'])) ?></td><td><span class="<?= e(statusClass($row['status'])) ?>"><i></i><?= e(statusLabel($row['status'])) ?></span></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><div class="empty-state"><span class="empty-symbol">◷</span><strong>Belum ada catatan absensi</strong><p><?= $isAdmin ? 'Tidak ada kehadiran untuk tanggal ini.' : 'Catatanmu akan muncul setelah melakukan absensi.' ?></p></div><?php endif; ?></section>

                <?php elseif ($page === 'employees' && $isAdmin): ?>
                <section class="employee-admin-grid"><article class="panel employee-list-panel"><div class="panel-heading"><div><h2>Daftar karyawan</h2><p><?= count($employees) ?> akun terdaftar</p></div><a class="button button-primary button-small" href="index.php?page=employees#employee-form">＋ <span>Tambah</span></a></div><div class="table-wrap"><table><thead><tr><th>KARYAWAN</th><th>PERAN</th><th>DEPARTEMEN</th><th></th></tr></thead><tbody><?php foreach ($employees as $employee): ?><tr><td><div class="person-cell"><span class="avatar avatar-small"><?= e(strtoupper(substr($employee['name'], 0, 1))) ?></span><span><strong><?= e($employee['name']) ?></strong><small><?= e($employee['employee_code']) ?> · <?= e($employee['email']) ?></small></span></div></td><td><span class="role-label <?= $employee['role'] === 'admin' ? 'role-admin' : '' ?>"><?= $employee['role'] === 'admin' ? 'Admin' : 'Karyawan' ?></span></td><td><?= e($employee['department'] ?: '—') ?></td><td><div class="row-actions"><a class="icon-button" href="index.php?page=employees&edit=<?= (int) $employee['id'] ?>#employee-form" title="Edit">✎</a><?php if ((int) $employee['id'] !== (int) $user['id']): ?><form method="post" data-confirm="Hapus akun <?= e($employee['name']) ?>? Riwayat terkait juga akan terhapus."><?= csrfField() ?><input type="hidden" name="action" value="delete_employee"><input type="hidden" name="id" value="<?= (int) $employee['id'] ?>"><button class="icon-button danger-icon" title="Hapus" type="submit">×</button></form><?php endif; ?></div></td></tr><?php endforeach; ?></tbody></table></div></article>
                    <article class="panel form-panel" id="employee-form"><div class="panel-heading"><div><h2><?= $editEmployee ? 'Edit karyawan' : 'Tambah karyawan' ?></h2><p><?= $editEmployee ? 'Perbarui informasi akun.' : 'Buat akun akses baru.' ?></p></div></div><form method="post" class="form-stack compact-form"><?= csrfField() ?><input type="hidden" name="action" value="save_employee"><input type="hidden" name="id" value="<?= (int) ($editEmployee['id'] ?? 0) ?>"><label>Kode karyawan<input name="employee_code" required placeholder="KRY-001" value="<?= e($editEmployee['employee_code'] ?? '') ?>"></label><label>Nama lengkap<input name="name" required placeholder="Nama karyawan" value="<?= e($editEmployee['name'] ?? '') ?>"></label><label>Email<input name="email" type="email" required placeholder="nama@perusahaan.id" value="<?= e($editEmployee['email'] ?? '') ?>"></label><div class="form-row"><label>Departemen<input name="department" placeholder="Operasional" value="<?= e($editEmployee['department'] ?? '') ?>"></label><label>Jabatan<input name="position" placeholder="Staf" value="<?= e($editEmployee['position'] ?? '') ?>"></label></div><label>Peran<select name="role"><option value="employee" <?= ($editEmployee['role'] ?? '') !== 'admin' ? 'selected' : '' ?>>Karyawan</option><option value="admin" <?= ($editEmployee['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option></select></label><label>Kata sandi <?= $editEmployee ? '<small class="field-hint">Kosongkan jika tidak diubah</small>' : '' ?><input name="password" type="password" <?= $editEmployee ? '' : 'required' ?> minlength="8" placeholder="Minimal 8 karakter" autocomplete="new-password"></label><div class="form-actions"><?php if ($editEmployee): ?><a class="button button-quiet" href="index.php?page=employees#employee-form">Batal</a><?php endif; ?><button class="button button-primary" type="submit"><?= $editEmployee ? 'Simpan perubahan' : 'Buat akun' ?></button></div></form></article></section>

                <?php elseif ($page === 'leave'): ?>
                <section class="leave-layout"><?php if (!$isAdmin): ?><article class="panel leave-form-panel"><div class="panel-heading"><div><h2>Ajukan izin atau cuti</h2><p>Pengajuanmu akan ditinjau oleh admin.</p></div><span class="leave-illustration">▧</span></div><form method="post" class="form-stack compact-form"><?= csrfField() ?><input type="hidden" name="action" value="submit_leave"><div class="form-row"><label>Tanggal mulai<input type="date" name="start_date" min="<?= e($today) ?>" required></label><label>Tanggal selesai<input type="date" name="end_date" min="<?= e($today) ?>" required></label></div><label>Alasan<textarea name="reason" rows="4" required placeholder="Ceritakan alasan pengajuanmu..."></textarea></label><button class="button button-primary" type="submit">Kirim pengajuan <span>→</span></button></form></article><?php endif; ?>
                    <article class="panel leave-list-panel"><div class="panel-heading"><div><h2><?= $isAdmin ? 'Pengajuan tim' : 'Pengajuan saya' ?></h2><p><?= $isAdmin ? 'Tinjau dan kelola pengajuan izin.' : 'Pantau status pengajuan izin dan cutimu.' ?></p></div><?php if ($isAdmin && $pendingLeaves): ?><span class="status status-amber"><i></i><?= $pendingLeaves ?> menunggu</span><?php endif; ?></div><?php if ($leaveRows): ?><div class="leave-list"><?php foreach ($leaveRows as $leave): ?><article class="leave-item"><div class="leave-item-top"><div><?php if ($isAdmin): ?><strong><?= e($leave['name']) ?></strong><small class="leave-code"><?= e($leave['employee_code']) ?></small><?php endif; ?><div class="leave-dates"><?= e(formatDate($leave['start_date'])) ?> <span>→</span> <?= e(formatDate($leave['end_date'])) ?></div></div><span class="<?= e(statusClass($leave['status'])) ?>"><i></i><?= e(statusLabel($leave['status'])) ?></span></div><p><?= nl2br(e($leave['reason'])) ?></p><div class="leave-item-foot"><small>Diajukan <?= e(formatDate($leave['created_at'])) ?></small><?php if ($isAdmin && $leave['status'] === 'pending'): ?><form method="post" class="review-actions"><?= csrfField() ?><input type="hidden" name="action" value="review_leave"><input type="hidden" name="id" value="<?= (int) $leave['id'] ?>"><button class="button button-approve" name="decision" value="approved" type="submit">Setujui</button><button class="button button-reject" name="decision" value="rejected" type="submit">Tolak</button></form><?php endif; ?></div></article><?php endforeach; ?></div><?php else: ?><div class="empty-state"><span class="empty-symbol">▤</span><strong>Belum ada pengajuan</strong><p><?= $isAdmin ? 'Pengajuan dari tim akan tampil di sini.' : 'Pengajuan yang kamu kirim akan tampil di sini.' ?></p></div><?php endif; ?></article></section>

                <?php elseif ($page === 'reports' && $isAdmin): ?>
                <section class="panel report-panel"><div class="panel-heading"><div><h2>Rekap kehadiran</h2><p>Filter periode dan unduh laporan CSV.</p></div></div><form method="get" class="report-filter"><input type="hidden" name="page" value="reports"><label>Dari tanggal<input type="date" name="from" value="<?= e($from) ?>" required></label><span class="filter-arrow">→</span><label>Sampai tanggal<input type="date" name="to" value="<?= e($to) ?>" required></label><button class="button button-dark" type="submit">Tampilkan</button><a class="button button-outline" href="index.php?page=reports&export=1&from=<?= e($from) ?>&to=<?= e($to) ?>">↓ <span>Unduh CSV</span></a></form><div class="report-summary"><span><strong><?= count($attendanceRows) ?></strong> catatan kehadiran</span><span>Periode <strong><?= e(formatDate($from)) ?> – <?= e(formatDate($to)) ?></strong></span></div><?php if ($attendanceRows): ?><div class="table-wrap"><table><thead><tr><th>KARYAWAN</th><th>DEPARTEMEN</th><th>TANGGAL</th><th>MASUK</th><th>PULANG</th><th>STATUS</th></tr></thead><tbody><?php foreach ($attendanceRows as $row): ?><tr><td><div class="person-cell"><span class="avatar avatar-small"><?= e(strtoupper(substr($row['name'], 0, 1))) ?></span><span><strong><?= e($row['name']) ?></strong><small><?= e($row['employee_code']) ?></small></span></div></td><td><?= e($row['department'] ?: '—') ?></td><td class="date-cell"><?= e(formatDate($row['attendance_date'])) ?></td><td class="time-cell"><?= e(formatTime($row['check_in'])) ?></td><td class="time-cell"><?= e(formatTime($row['check_out'])) ?></td><td><span class="<?= e(statusClass($row['status'])) ?>"><i></i><?= e(statusLabel($row['status'])) ?></span></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><div class="empty-state"><span class="empty-symbol">▥</span><strong>Tidak ada data di periode ini</strong><p>Coba pilih rentang tanggal yang lain.</p></div><?php endif; ?></section>

                <?php elseif ($page === 'profile'): ?>
                <section class="profile-layout"><article class="profile-summary"><span class="profile-avatar"><?= e(strtoupper(substr($user['name'], 0, 1))) ?></span><h2><?= e($user['name']) ?></h2><p><?= e($user['position'] ?: ($isAdmin ? 'Administrator' : 'Karyawan')) ?></p><span class="role-label <?= $isAdmin ? 'role-admin' : '' ?>"><?= $isAdmin ? 'Admin' : 'Karyawan' ?></span><div class="profile-divider"></div><div class="profile-detail"><span>Kode karyawan</span><strong><?= e($user['employee_code']) ?></strong></div><div class="profile-detail"><span>Departemen</span><strong><?= e($user['department'] ?: '—') ?></strong></div><div class="profile-detail"><span>Email</span><strong><?= e($user['email']) ?></strong></div></article><article class="panel profile-form"><div class="panel-heading"><div><h2>Informasi pribadi</h2><p>Perbarui detail dan kata sandi akunmu.</p></div></div><form method="post" class="form-stack"><?= csrfField() ?><input type="hidden" name="action" value="update_profile"><label>Nama lengkap<input name="name" required value="<?= e($user['name']) ?>"></label><div class="form-row"><label>Departemen<input name="department" value="<?= e($user['department']) ?>"></label><label>Jabatan<input name="position" value="<?= e($user['position']) ?>"></label></div><label>Email<input type="email" value="<?= e($user['email']) ?>" disabled><small class="field-hint">Hubungi admin untuk mengganti email.</small></label><div class="form-divider"></div><label>Kata sandi baru<input type="password" name="password" minlength="8" autocomplete="new-password" placeholder="Kosongkan jika tidak diganti"></label><div class="form-actions"><button class="button button-primary" type="submit">Simpan perubahan</button></div></form><form method="post" class="logout-form"><?= csrfField() ?><input type="hidden" name="action" value="logout"><button class="button button-quiet" type="submit">Keluar dari akun <span>↗</span></button></form></article></section>
                <?php endif; ?>
                <footer class="app-footer"><span>© <?= date('Y') ?> RuangHadir</span><span>WORKPLACE, IN SYNC <i>✳</i></span></footer>
            </main>
        </div>
        <div class="mobile-scrim" data-menu-close></div>
    </body></html>
    <?php
} catch (Throwable $exception) {
    http_response_code(500);
    ?>
    <!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Aplikasi belum siap · RuangHadir</title><link rel="stylesheet" href="assets/app.css"></head><body class="error-page"><main class="error-box"><span class="brand-mark">R</span><span class="eyebrow">PENYIAPAN APLIKASI</span><h1>Aplikasi belum siap.</h1><p>Pastikan MySQL di XAMPP sudah aktif, lalu jalankan instalasi awal. Detail: <?= e($exception->getMessage()) ?></p><a class="button button-primary" href="install.php">Buka halaman instalasi <span>→</span></a></main></body></html>
    <?php
}