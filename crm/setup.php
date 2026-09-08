<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$error = null;
$installed = false;
try {
    $check = db()->query("SELECT setting_value FROM crm_settings WHERE setting_key = 'installed_at'");
    $installed = (bool)$check->fetchColumn();
} catch (Throwable) {
    $installed = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$installed) {
    $token = (string)($_POST['setup_token'] ?? '');
    $expected = (string)(crm_config()['app']['setup_token'] ?? '');
    $passwords = [
        'melas@distillogic.gr' => (string)($_POST['melas_password'] ?? ''),
        'sophianos@distillogic.gr' => (string)($_POST['sophianos_password'] ?? ''),
        'support@distillogic.gr' => (string)($_POST['support_password'] ?? ''),
    ];

    if ($expected === '' || str_starts_with($expected, 'REPLACE_') || !hash_equals($expected, $token)) {
        $error = 'Το setup token δεν είναι σωστό.';
    } elseif (array_filter($passwords, static fn(string $password): bool => strlen($password) < 12)) {
        $error = 'Κάθε κωδικός πρέπει να έχει τουλάχιστον 12 χαρακτήρες.';
    } else {
        try {
            $pdo = db();
            foreach (require __DIR__ . '/schema.php' as $statement) {
                $pdo->exec($statement);
            }
            // MariaDB commits DDL automatically. Start the data transaction only
            // after every CREATE TABLE statement has completed.
            $pdo->beginTransaction();
            $accounts = [
                ['Melas', 'melas@distillogic.gr', 'admin'],
                ['Sophianos', 'sophianos@distillogic.gr', 'manager'],
                ['Support', 'support@distillogic.gr', 'technical'],
            ];
            $insert = $pdo->prepare(
                'INSERT INTO users (id, name, email, role, active, password_hash)
                 VALUES (?, ?, ?, ?, 1, ?)
                 ON DUPLICATE KEY UPDATE name = VALUES(name), role = VALUES(role), active = 1, password_hash = VALUES(password_hash)'
            );
            foreach ($accounts as [$name, $email, $role]) {
                $insert->execute([uuid_v4(), $name, $email, $role, password_hash($passwords[$email], PASSWORD_DEFAULT)]);
            }
            $setting = $pdo->prepare(
                "INSERT INTO crm_settings (setting_key, setting_value) VALUES ('installed_at', ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
            );
            $setting->execute([(new DateTimeImmutable())->format(DATE_ATOM)]);
            $pdo->commit();
            $installed = true;
        } catch (Throwable $exception) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('CRM setup failed: ' . $exception->getMessage());
            $error = 'Η εγκατάσταση δεν ολοκληρώθηκε. Ελέγξτε τα στοιχεία της βάσης.';
        }
    }
}
?>
<!doctype html>
<html lang="el">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="robots" content="noindex,nofollow"><title>CRM Setup | DISTILLOGIC</title>
  <link rel="stylesheet" href="<?= e(crm_url('assets/crm.css')) ?>">
</head>
<body class="auth-page"><main class="auth-card setup-card">
  <div class="brand auth-brand"><span class="brand-mark">D</span><span>DISTILLOGIC<small>CRM setup</small></span></div>
  <?php if ($installed): ?>
    <h1>Η εγκατάσταση ολοκληρώθηκε</h1>
    <p>Οι πίνακες και οι τρεις λογαριασμοί δημιουργήθηκαν.</p>
    <a class="button primary" href="<?= e(crm_url('login.php')) ?>">Μετάβαση στη σύνδεση</a>
  <?php else: ?>
    <h1>Αρχική εγκατάσταση</h1>
    <p>Η διαδικασία εκτελείται μία φορά και δημιουργεί τη δομή της MariaDB.</p>
    <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="stack">
      <label>Setup token<input type="password" name="setup_token" required autocomplete="off"></label>
      <label>Κωδικός Melas<input type="password" name="melas_password" required minlength="12" autocomplete="new-password"></label>
      <label>Κωδικός Sophianos<input type="password" name="sophianos_password" required minlength="12" autocomplete="new-password"></label>
      <label>Κωδικός Support<input type="password" name="support_password" required minlength="12" autocomplete="new-password"></label>
      <button class="button primary" type="submit">Εγκατάσταση CRM</button>
    </form>
  <?php endif; ?>
</main></body></html>
