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

function request_is_https(): bool
{
    $https = strtolower((string)($_SERVER['HTTPS'] ?? ''));
    if ($https !== '' && $https !== 'off' && $https !== '0') {
        return true;
    }
    if ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443) {
        return true;
    }
    $forwarded = strtolower(trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
    return $forwarded === 'https';
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
        'secure' => request_is_https(),
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

function user_initials(string $name): string
{
    $parts = preg_split('/\s+/u', trim($name)) ?: [];
    $initials = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $initials .= mb_strtoupper(mb_substr($part, 0, 1));
    }
    return $initials !== '' ? $initials : 'D';
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
    $companySection = in_array($current, ['communications.php', 'communication.php', 'new-communication.php', 'customers.php'], true);
    $accountSection = $current === 'accounts.php';
    $notificationCount = 0;
    try {
        $count = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL');
        $count->execute([$user['id']]);
        $notificationCount = (int)$count->fetchColumn();
    } catch (Throwable) {
        $notificationCount = 0;
    }
    ?>
<!doctype html>
<html lang="el" data-scheme="light">
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
    <a class="brand" href="<?= e(crm_url()) ?>">
      <span class="brand-mark"><svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M20.27 7.27 21 8v8l-5 5H8l-5-5V8l5-5h8l.73.73-3.77 3.77H10L7.5 10v4l2.5 2.5h4l2.5-2.5v-2.96l3.77-3.77Z"/><path class="logo-stone" d="m16.9 5.2 1.9 1.9-1.9 1.9L15 7.1l1.9-1.9Z"/></svg></span>
      <span>Distillogic CRM<small>Distillogic Technologies</small></span>
    </a>
    <nav aria-label="Κύρια πλοήγηση">
      <a class="nav-main <?= $current === 'index.php' ? 'active' : '' ?>" href="<?= e(crm_url()) ?>"><span class="nav-icon">⌂</span><span>Dashboard</span></a>
      <div class="nav-group <?= $companySection ? 'open' : '' ?>">
        <div class="nav-group-label"><span class="nav-icon">◇</span><span>Εταιρείες - Συνεργασίες</span><span class="chevron">⌄</span></div>
        <div class="nav-children">
          <a class="<?= $current === 'new-communication.php' ? 'active' : '' ?>" href="<?= e(crm_url('new-communication.php')) ?>">Νέα τηλεφωνική επικοινωνία</a>
          <a class="<?= in_array($current, ['communications.php', 'communication.php'], true) ? 'active' : '' ?>" href="<?= e(crm_url('communications.php')) ?>">Επικοινωνίες ανά κατηγορία</a>
          <a class="<?= $current === 'customers.php' ? 'active' : '' ?>" href="<?= e(crm_url('customers.php')) ?>">Πελάτες &amp; στοιχεία</a>
        </div>
      </div>
      <?php if (in_array($user['role'], ['admin', 'technical'], true)): ?>
        <div class="nav-group <?= $accountSection ? 'open' : '' ?>">
          <div class="nav-group-label"><span class="nav-icon">◎</span><span>Λογαριασμοί</span><span class="chevron">⌄</span></div>
          <div class="nav-children"><a class="<?= $accountSection ? 'active' : '' ?>" href="<?= e(crm_url('accounts.php')) ?>">Όλοι οι λογαριασμοί</a></div>
        </div>
      <?php endif; ?>
    </nav>
    <div class="sidebar-user">
      <span class="user-avatar"><?= e(user_initials($user['name'])) ?></span>
      <span class="user-copy"><strong><?= e($user['name']) ?></strong><small><?= e(role_label($user['role'])) ?></small></span>
    </div>
  </aside>
  <button class="sidebar-scrim" type="button" aria-label="Κλείσιμο μενού" data-menu-close></button>
  <main class="main">
    <header class="topbar">
      <button class="menu-button" type="button" aria-label="Μενού" data-menu>☰</button>
      <nav class="breadcrumbs" aria-label="Διαδρομή"><span>Workspace</span><i>/</i><strong><?= e($title) ?></strong></nav>
      <div class="topbar-actions">
        <button class="icon-button" type="button" aria-label="Αλλαγή εμφάνισης" title="Αλλαγή εμφάνισης" data-theme-toggle>◐</button>
        <a class="search-button" href="<?= e(crm_url('communications.php')) ?>" aria-label="Αναζήτηση επικοινωνιών"><span>⌕</span><span>Αναζήτηση</span><kbd>⌘K</kbd></a>
        <a class="icon-button notification-button" href="<?= e(crm_url('communications.php?source=website')) ?>" aria-label="Ειδοποιήσεις">♧<?php if ($notificationCount > 0): ?><b><?= min(99, $notificationCount) ?></b><?php endif; ?></a>
        <span class="top-account"><span class="user-avatar"><?= e(user_initials($user['name'])) ?></span><span><?= e($user['name']) ?></span></span>
        <a class="icon-button" href="<?= e(crm_url('logout.php')) ?>" aria-label="Αποσύνδεση" title="Αποσύνδεση">↪</a>
      </div>
    </header>
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
const closeMenu=()=>document.body.classList.remove('menu-open');
document.querySelector('[data-menu]')?.addEventListener('click',()=>document.body.classList.toggle('menu-open'));
document.querySelector('[data-menu-close]')?.addEventListener('click',closeMenu);
document.querySelectorAll('.sidebar a').forEach(link=>link.addEventListener('click',closeMenu));
document.querySelector('[data-theme-toggle]')?.addEventListener('click',()=>{
  const root=document.documentElement;
  const next=root.dataset.scheme==='dark'?'light':'dark';
  root.dataset.scheme=next;
  localStorage.setItem('distillogic-crm-scheme',next);
});
const savedScheme=localStorage.getItem('distillogic-crm-scheme');
if(savedScheme==='dark'||savedScheme==='light')document.documentElement.dataset.scheme=savedScheme;
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
