<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

function verifyCsrf(): void
{
    if (!hash_equals(csrfToken(), (string) ($_POST['csrf_token'] ?? ''))) {
        http_response_code(419);
        exit('Sesi formulir kedaluwarsa. Muat ulang halaman dan coba lagi.');
    }
}

function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function pullFlash(): ?array
{
    $message = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $message;
}

function currentUser(): ?array
{
    return $_SESSION['user'] ?? null;
}

function requireLogin(): array
{
    $user = currentUser();
    if (!$user) {
        redirect('index.php?page=login');
    }
    return $user;
}

function requireAdmin(): void
{
    if ((currentUser()['role'] ?? '') !== 'admin') {
        flash('Akses tersebut hanya tersedia untuk admin.', 'error');
        redirect('index.php?page=dashboard');
    }
}

function formatDate(?string $date): string
{
    if (!$date) {
        return '-';
    }
    return date('d M Y', strtotime($date));
}

function formatTime(?string $time): string
{
    return $time ? date('H:i', strtotime($time)) : '-';
}

function statusLabel(string $status): string
{
    return match ($status) {
        'present' => 'Hadir',
        'late' => 'Terlambat',
        'pending' => 'Menunggu',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
        default => ucfirst($status),
    };
}

function statusClass(string $status): string
{
    return match ($status) {
        'present', 'approved' => 'status status-green',
        'late', 'pending' => 'status status-amber',
        'rejected', 'absent' => 'status status-red',
        default => 'status status-muted',
    };
}