<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
$user = require_login();
$customers = db()->query(
    "SELECT co.id, co.name, co.email, co.phone, co.industry, co.city,
            COUNT(c.id) AS communications, MAX(c.created_at) AS last_contact
       FROM companies co
       LEFT JOIN communications c ON c.company_id = co.id AND c.deleted_at IS NULL
      WHERE co.deleted_at IS NULL
      GROUP BY co.id, co.name, co.email, co.phone, co.industry, co.city
      ORDER BY co.name ASC"
)->fetchAll();
render_header('Πελάτες', $user);
?>
<div class="page-heading"><div><h1>Πελάτες και στοιχεία επικοινωνίας</h1><p>Ενιαία λίστα εταιρειών από την ιστοσελίδα και τις τηλεφωνικές επαφές.</p></div><a class="button primary" href="<?= e(crm_url('new-communication.php')) ?>">Νέα επικοινωνία</a></div>
<div class="table-wrap"><table><thead><tr><th>Εταιρεία</th><th>Email</th><th>Τηλέφωνο</th><th>Κλάδος / πόλη</th><th>Επικοινωνίες</th><th>Τελευταία επαφή</th></tr></thead><tbody>
<?php if (!$customers): ?><tr><td colspan="6" class="empty">Δεν υπάρχουν ακόμη πελάτες.</td></tr><?php endif; ?>
<?php foreach ($customers as $company): ?><tr><td><strong><?= e($company['name']) ?></strong></td><td><?php if ($company['email']): ?><a href="mailto:<?= e($company['email']) ?>"><?= e($company['email']) ?></a><?php else: ?>—<?php endif; ?></td><td><?= e($company['phone'] ?: '—') ?></td><td><?= e(implode(' · ', array_filter([$company['industry'], $company['city']]))) ?: '—' ?></td><td><?= (int)$company['communications'] ?></td><td><?= e(format_datetime($company['last_contact'])) ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php render_footer(); ?>

