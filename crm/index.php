<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
$user = require_login();

$totals = db()->query(
    "SELECT COUNT(*) AS communications,
            COUNT(DISTINCT company_id) AS companies,
            SUM(source = 'website') AS website,
            SUM(source = 'telephone') AS telephone,
            SUM(next_action_at IS NOT NULL AND next_action_at < NOW()) AS overdue
       FROM communications
      WHERE deleted_at IS NULL AND status <> 'archived'"
)->fetch() ?: [];
$recent = db()->query(
    "SELECT c.id, c.source, c.status, c.contact_name, c.created_at,
            co.name AS company_name, COALESCE(u.name, 'Μη ανατεθειμένο') AS user_name
       FROM communications c
       JOIN companies co ON co.id = c.company_id
       LEFT JOIN users u ON u.id = c.assigned_user_id
      WHERE c.deleted_at IS NULL
      ORDER BY c.created_at DESC LIMIT 8"
)->fetchAll();
$followUps = db()->query(
    "SELECT c.id, c.next_action, c.next_action_at, co.name AS company_name
       FROM communications c JOIN companies co ON co.id = c.company_id
      WHERE c.deleted_at IS NULL AND c.next_action_at IS NOT NULL
        AND c.status NOT IN ('won','lost','archived')
      ORDER BY c.next_action_at ASC LIMIT 7"
)->fetchAll();

render_header('Επισκόπηση', $user);
?>
<div class="page-heading"><div><h1>Επισκόπηση επικοινωνιών</h1><p>Η τρέχουσα εικόνα των επαφών και των επόμενων ενεργειών.</p></div><a class="button primary" href="<?= e(crm_url('new-communication.php')) ?>">Νέα καταχώριση</a></div>
<section class="grid stats">
  <article class="card stat"><span>Επικοινωνίες</span><strong><?= (int)($totals['communications'] ?? 0) ?></strong></article>
  <article class="card stat"><span>Εταιρείες</span><strong><?= (int)($totals['companies'] ?? 0) ?></strong></article>
  <article class="card stat"><span>Από ιστοσελίδα</span><strong><?= (int)($totals['website'] ?? 0) ?></strong></article>
  <article class="card stat"><span>Εκκρεμείς ενέργειες</span><strong><?= (int)($totals['overdue'] ?? 0) ?></strong></article>
</section>
<section class="grid split">
  <article class="card"><h2>Πρόσφατες επικοινωνίες</h2>
    <?php if (!$recent): ?><div class="empty">Δεν υπάρχουν ακόμη επικοινωνίες.</div><?php else: ?>
    <div class="table-wrap"><table><thead><tr><th>Εταιρεία</th><th>Πηγή</th><th>Κατάσταση</th><th>Υπεύθυνος</th><th>Ημερομηνία</th></tr></thead><tbody>
      <?php foreach ($recent as $item): ?><tr><td><a href="<?= e(crm_url('communication.php?id=' . urlencode($item['id']))) ?>"><strong><?= e($item['company_name']) ?></strong></a><br><span class="muted"><?= e($item['contact_name']) ?></span></td><td><span class="badge <?= e($item['source']) ?>"><?= $item['source'] === 'website' ? 'Ιστοσελίδα' : 'Τηλέφωνο' ?></span></td><td><?= e($item['status']) ?></td><td><?= e($item['user_name']) ?></td><td><?= e(format_datetime($item['created_at'])) ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
  </article>
  <article class="card"><h2>Επόμενες ενέργειες</h2>
    <?php if (!$followUps): ?><div class="empty">Δεν υπάρχουν προγραμματισμένες ενέργειες.</div><?php else: ?>
      <div class="stack"><?php foreach ($followUps as $item): ?><a class="card" href="<?= e(crm_url('communication.php?id=' . urlencode($item['id']))) ?>"><strong><?= e($item['company_name']) ?></strong><br><span class="muted"><?= e($item['next_action']) ?> · <?= e(format_datetime($item['next_action_at'])) ?></span></a><?php endforeach; ?></div>
    <?php endif; ?>
  </article>
</section>
<?php render_footer(); ?>

