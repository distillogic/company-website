<?php
declare(strict_types=1);

const CRM_ROOT = __DIR__;

function crm_config(): array
{
    static $config;
    if (is_array($config)) {
        return $config;
    }

    $path = CRM_ROOT . '/config.php';
    if (!is_file($path)) {
        http_response_code(503);
        exit('CRM configuration is missing. Create crm/config.php from config.example.php.');
    }

    $loaded = require $path;
    if (!is_array($loaded)) {
        throw new RuntimeException('Invalid CRM configuration.');
    }
    $config = $loaded;
    date_default_timezone_set((string)($config['app']['timezone'] ?? 'Europe/Athens'));
    return $config;
}

function db(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $database = crm_config()['database'];
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $database['host'],
        $database['port'],
        $database['name'],
        $database['charset'] ?? 'utf8mb4'
    );
    $pdo = new PDO($dsn, $database['user'], $database['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function crm_url(string $path = ''): string
{
    $base = rtrim((string)(crm_config()['app']['base_path'] ?? '/crm'), '/');
    return $base . ($path === '' ? '' : '/' . ltrim($path, '/'));
}

function redirect_to(string $path): never
{
    header('Location: ' . crm_url($path), true, 302);
    exit;
}

function uuid_v4(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function start_crm_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $config = crm_config();
    $days = max(1, (int)($config['app']['session_days'] ?? 14));
    session_name('distillogic_crm');
    session_set_cookie_params([
        'lifetime' => $days * 86400,
        'path' => rtrim((string)$config['app']['base_path'], '/') . '/',
        'secure' => str_starts_with((string)$config['app']['site_url'], 'https://'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function current_user(): ?array
{
    start_crm_session();
    $id = $_SESSION['user_id'] ?? null;
    if (!is_string($id) || $id === '') {
        return null;
    }
    $statement = db()->prepare('SELECT id, name, email, role, active FROM users WHERE id = ? LIMIT 1');
    $statement->execute([$id]);
    $user = $statement->fetch();
    if (!$user || !(bool)$user['active']) {
        $_SESSION = [];
        return null;
    }
    return $user;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        redirect_to('login.php');
    }
    return $user;
}

function require_roles(array $roles): array
{
    $user = require_login();
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        exit('Δεν έχετε δικαίωμα πρόσβασης σε αυτή τη λειτουργία.');
    }
    return $user;
}

function csrf_token(): string
{
    start_crm_session();
    if (!isset($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    start_crm_session();
    $provided = (string)($_POST['csrf'] ?? '');
    if ($provided === '' || !hash_equals((string)($_SESSION['csrf'] ?? ''), $provided)) {
        http_response_code(419);
        exit('Η συνεδρία έληξε. Ανανεώστε τη σελίδα και δοκιμάστε ξανά.');
    }
}

function flash(string $type, string $message): void
{
    start_crm_session();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function take_flash(): ?array
{
    start_crm_session();
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($flash) ? $flash : null;
}

function format_datetime(?string $value): string
{
    if (!$value) {
        return '—';
    }
    return (new DateTimeImmutable($value))->format('d/m/Y, H:i');
}

function role_label(string $role): string
{
    return [
        'admin' => 'Ιδιοκτήτης επιχείρησης',
        'manager' => 'Διαχειριστής',
        'technical' => 'Τεχνική υποστήριξη',
        'employee' => 'Χρήστης',
    ][$role] ?? $role;
}

function enforce_rate_limit(string $scope, int $maximum, int $windowSeconds = 900): void
{
    $address = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $key = hash('sha256', $scope . '|' . $address);
    $pdo = db();
    $statement = $pdo->prepare('SELECT attempts, window_started FROM request_limits WHERE limit_key = ? LIMIT 1');
    $statement->execute([$key]);
    $row = $statement->fetch();
    $windowStart = $row ? strtotime((string)$row['window_started']) : false;
    if ($row && $windowStart !== false && time() - $windowStart < $windowSeconds && (int)$row['attempts'] >= $maximum) {
        http_response_code(429);
        throw new RuntimeException('Πολλές προσπάθειες. Δοκιμάστε ξανά σε λίγα λεπτά.');
    }
    if (!$row || $windowStart === false || time() - $windowStart >= $windowSeconds) {
        $reset = $pdo->prepare('INSERT INTO request_limits (limit_key, attempts, window_started) VALUES (?, 1, NOW()) ON DUPLICATE KEY UPDATE attempts = 1, window_started = NOW()');
        $reset->execute([$key]);
    } else {
        $increment = $pdo->prepare('UPDATE request_limits SET attempts = attempts + 1 WHERE limit_key = ?');
        $increment->execute([$key]);
    }
}

function clear_rate_limit(string $scope): void
{
    $address = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $statement = db()->prepare('DELETE FROM request_limits WHERE limit_key = ?');
    $statement->execute([hash('sha256', $scope . '|' . $address)]);
}

function render_header(string $title, array $user): void
{
    $flash = take_flash();
    $current = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    ?>
<!doctype html>
<html lang="el" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title><?= e($title) ?> | DISTILLOGIC CRM</title>
  <link rel="stylesheet" href="<?= e(crm_url('assets/crm.css')) ?>">
</head>
<body>
<div class="app-shell">
  <aside class="sidebar">
    <a class="brand" href="<?= e(crm_url()) ?>"><span class="brand-mark">D</span><span>DISTILLOGIC<small>CRM workspace</small></span></a>
    <nav aria-label="Κύρια πλοήγηση">
      <a class="<?= $current === 'index.php' ? 'active' : '' ?>" href="<?= e(crm_url()) ?>">Επισκόπηση</a>
      <a class="<?= $current === 'communications.php' ? 'active' : '' ?>" href="<?= e(crm_url('communications.php')) ?>">Επικοινωνίες</a>
      <a class="<?= $current === 'new-communication.php' ? 'active' : '' ?>" href="<?= e(crm_url('new-communication.php')) ?>">Νέα καταχώριση</a>
      <a class="<?= $current === 'customers.php' ? 'active' : '' ?>" href="<?= e(crm_url('customers.php')) ?>">Πελάτες</a>
      <?php if (in_array($user['role'], ['admin', 'technical'], true)): ?>
        <a class="<?= $current === 'accounts.php' ? 'active' : '' ?>" href="<?= e(crm_url('accounts.php')) ?>">Λογαριασμοί</a>
      <?php endif; ?>
    </nav>
    <div class="sidebar-user">
      <strong><?= e($user['name']) ?></strong>
      <span><?= e(role_label($user['role'])) ?></span>
      <a href="<?= e(crm_url('logout.php')) ?>">Αποσύνδεση</a>
    </div>
  </aside>
  <main class="main">
    <header class="topbar"><button class="menu-button" type="button" aria-label="Μενού" data-menu>☰</button><div><span>DISTILLOGIC</span><strong><?= e($title) ?></strong></div></header>
    <div class="page">
      <?php if ($flash): ?><div class="alert <?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div><?php endif; ?>
<?php
}

function render_footer(): void
{
    ?>
    </div>
  </main>
</div>
<script>
document.querySelector('[data-menu]')?.addEventListener('click',()=>document.body.classList.toggle('menu-open'));
document.querySelectorAll('[data-confirm]').forEach(button=>button.addEventListener('click',event=>{if(!confirm(button.dataset.confirm))event.preventDefault()}));
</script>
</body>
</html>
<?php
}

function send_crm_email(string $to, string $subject, string $html): bool
{
    $mail = crm_config()['mail'] ?? [];
    $fromEmail = (string)($mail['from_email'] ?? 'info@distillogic.gr');
    $fromName = (string)($mail['from_name'] ?? 'DISTILLOGIC TECHNOLOGIES');
    $apiKey = trim((string)($mail['resend_api_key'] ?? ''));

    if ($apiKey !== '' && function_exists('curl_init')) {
        $curl = curl_init('https://api.resend.com/emails');
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $apiKey, 'Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode([
                'from' => $fromName . ' <' . $fromEmail . '>',
                'to' => [$to],
                'subject' => $subject,
                'html' => $html,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        return $status >= 200 && $status < 300;
    }

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $fromName . ' <' . $fromEmail . '>',
    ];
    return mail($to, $subject, $html, implode("\r\n", $headers));
}
