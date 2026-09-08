<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
$user = require_login();
$source = (string)($_GET['source'] ?? 'all');
$search = trim((string)($_GET['q'] ?? ''));
$allowedSources = ['all', 'website', 'telephone'];
if (!in_array($source, $allowedSources, true)) {
    $source = 'all';
}

$conditions = ['c.deleted_at IS NULL'];
$parameters = [];
if ($source !== 'all') {
    $conditions[] = 'c.source = ?';
    $parameters[] = $source;
}
if ($search !== '') {
    $conditions[] = '(co.name LIKE ? OR c.contact_name LIKE ? OR co.email LIKE ? OR co.phone LIKE ?)';
    $term = '%' . mb_substr($search, 0, 120) . '%';
    array_push($parameters, $term, $term, $term, $term);
}
$statement = db()->prepare(
    "SELECT c.id, c.source, c.status, c.contact_name, c.outcome, c.interest_level,
            c.next_action, c.next_action_at, c.created_at, co.name AS company_name,
            COALESCE(u.name, 'Μη ανατεθειμένο') AS assigned_name
       FROM communications c
       JOIN companies co ON co.id = c.company_id
       LEFT JOIN users u ON u.id = c.assigned_user_id
      WHERE " . implode(' AND ', $conditions) . "
      ORDER BY c.created_at DESC LIMIT 200"
);
$statement->execute($parameters);
$items = $statement->fetchAll();

render_header('Επικοινωνίες', $user);
?>
<div class="page-heading"><div><h1>Επικοινωνίες</h1><p>Αιτήματα από την ιστοσελίδα και τηλεφωνικές καταχωρίσεις.</p></div><a class="button primary" href="<?= e(crm_url('new-communication.php')) ?>">Νέα τηλεφωνική επικοινωνία</a></div>
<div class="filters">
  <a class="<?= $source === 'all' ? 'active' : '' ?>" href="<?= e(crm_url('communications.php')) ?>">Όλες</a>
  <a class="<?= $source === 'website' ? 'active' : '' ?>" href="<?= e(crm_url('communications.php?source=website')) ?>">Από ιστοσελίδα</a>
  <a class="<?= $source === 'telephone' ? 'active' : '' ?>" href="<?= e(crm_url('communications.php?source=telephone')) ?>">Τηλεφωνικές</a>
</div>
<form method="get" class="card filters"><input type="search" name="q" value="<?= e($search) ?>" placeholder="Αναζήτηση εταιρείας, επαφής, email ή τηλεφώνου"><input type="hidden" name="source" value="<?= e($source) ?>"><button class="button" type="submit">Αναζήτηση</button></form>
<div class="table-wrap"><table><thead><tr><th>Εταιρεία / επαφή</th><th>Πηγή</th><th>Κατάσταση</th><th>Ενδιαφέρον</th><th>Υπεύθυνος</th><th>Επόμενη ενέργεια</th></tr></thead><tbody>
<?php if (!$items): ?><tr><td colspan="6" class="empty">Δεν βρέθηκαν επικοινωνίες.</td></tr><?php endif; ?>
<?php foreach ($items as $item): ?><tr>
  <td><a href="<?= e(crm_url('communication.php?id=' . urlencode($item['id']))) ?>"><strong><?= e($item['company_name']) ?></strong></a><br><span class="muted"><?= e($item['contact_name']) ?> · <?= e(format_datetime($item['created_at'])) ?></span></td>
  <td><span class="badge <?= e($item['source']) ?>"><?= $item['source'] === 'website' ? 'Ιστοσελίδα' : 'Τηλέφωνο' ?></span></td>
  <td><span class="badge <?= e($item['status']) ?>"><?= e($item['status']) ?></span></td>
  <td><?= $item['interest_level'] ? (int)$item['interest_level'] . '/5' : '—' ?></td><td><?= e($item['assigned_name']) ?></td>
  <td><?= e($item['next_action']) ?><br><span class="muted"><?= e(format_datetime($item['next_action_at'])) ?></span></td>
</tr><?php endforeach; ?>
</tbody></table></div>
<?php render_footer(); ?>

