<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
$user = require_roles(['admin', 'technical']);
$accounts = db()->query('SELECT id, name, email, role, active, created_at FROM users ORDER BY created_at ASC')->fetchAll();
render_header('Λογαριασμοί', $user);
?>
<div class="page-heading"><div><h1>Λογαριασμοί CRM</h1><p>Οι χρήστες που έχουν πρόσβαση στο εσωτερικό σύστημα.</p></div></div>
<div class="table-wrap"><table><thead><tr><th>Χρήστης</th><th>Email</th><th>Ρόλος</th><th>Κατάσταση</th><th>Δημιουργήθηκε</th></tr></thead><tbody>
<?php foreach ($accounts as $account): ?><tr><td><strong><?= e($account['name']) ?></strong></td><td><?= e($account['email']) ?></td><td><?= e(role_label($account['role'])) ?></td><td><span class="badge <?= $account['active'] ? 'won' : 'lost' ?>"><?= $account['active'] ? 'Ενεργός' : 'Αποκλεισμένος' ?></span></td><td><?= e(format_datetime($account['created_at'])) ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php render_footer(); ?>

