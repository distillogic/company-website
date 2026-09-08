<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
start_crm_session();
if (current_user()) {
    redirect_to('');
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        enforce_rate_limit('login', 10);
    } catch (RuntimeException $exception) {
        http_response_code(429);
        $error = $exception->getMessage();
    }
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    if ($error === null) {
        $statement = db()->prepare('SELECT id, password_hash, active FROM users WHERE email = ? LIMIT 1');
        $statement->execute([$email]);
        $account = $statement->fetch();
        if ($account && (bool)$account['active'] && password_verify($password, $account['password_hash'])) {
            clear_rate_limit('login');
            session_regenerate_id(true);
            $_SESSION['user_id'] = $account['id'];
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
            redirect_to('');
        }
        usleep(250000);
        $error = 'Λανθασμένο email ή κωδικός πρόσβασης.';
    }
}
?>
<!doctype html>
<html lang="el">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="robots" content="noindex,nofollow"><title>Σύνδεση | DISTILLOGIC CRM</title>
  <link rel="stylesheet" href="<?= e(crm_url('assets/crm.css')) ?>">
</head>
<body class="login-page"><main class="login-layout">
  <section class="login-visual">
    <div class="login-grid"></div>
    <div class="login-visual-content">
      <div class="brand login-brand">
        <span class="brand-mark"><svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M20.27 7.27 21 8v8l-5 5H8l-5-5V8l5-5h8l.73.73-3.77 3.77H10L7.5 10v4l2.5 2.5h4l2.5-2.5v-2.96l3.77-3.77Z"/><path class="logo-stone" d="m16.9 5.2 1.9 1.9-1.9 1.9L15 7.1l1.9-1.9Z"/></svg></span>
        <span>Distillogic CRM<small>Technology Delivery Operations</small></span>
      </div>
      <div class="login-statement"><span>Commercial Intelligence</span><h1>Relationships, opportunities and delivery in one workspace.</h1><p>Διαχειριστείτε εταιρείες, project enquiries, επικοινωνίες και επόμενες ενέργειες με σαφή εικόνα σε κάθε στάδιο.</p></div>
      <div class="login-tags"><span>Companies</span><span>Project enquiries</span><span>Follow-ups</span><span>Delivery visibility</span></div>
    </div>
  </section>
  <section class="login-form-side"><div class="login-form-wrap">
    <div class="login-heading"><span>Secure access</span><h2>Καλώς ήρθατε.</h2><p>Συνδεθείτε για να συνεχίσετε στο εταιρικό CRM.</p></div>
    <div class="auth-card">
      <?php if ($error): ?><div class="alert error" role="alert"><?= e($error) ?></div><?php endif; ?>
      <form method="post" class="stack">
        <?= csrf_field() ?>
        <label>Email<input type="email" name="email" required autofocus autocomplete="username"></label>
        <label>Κωδικός πρόσβασης<input type="password" name="password" required autocomplete="current-password"></label>
        <button class="button primary" type="submit">Σύνδεση</button>
      </form>
    </div>
    <p class="access-note">Η πρόσβαση παρέχεται αποκλειστικά από τον διαχειριστή του συστήματος.</p>
  </div></section>
</main></body></html>
