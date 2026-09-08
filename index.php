<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
$user = require_login();

$totals = db()->query(
    "SELECT COUNT(*) AS communications,
            COUNT(DISTINCT company_id) AS companies,
            SUM(source = 'website') AS website,
            SUM(source = 'telephone') AS telephone,
            SUM(assigned_user_id IS NULL) AS unassigned,
            SUM(created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS last_30_days,
            SUM(next_action_at IS NOT NULL AND next_action_at < NOW() AND status NOT IN ('won','lost','archived')) AS overdue,
            SUM(next_action_at >= NOW() AND status NOT IN ('won','lost','archived')) AS upcoming
       FROM communications
      WHERE deleted_at IS NULL AND status <> 'archived'"
)->fetch() ?: [];

$recent = db()->query(
    "SELECT c.id, c.source, c.outcome, c.interest_level, c.contact_name, c.created_at,
            co.name AS company_name, COALESCE(u.name, 'Μη ανατεθειμένο') AS user_name
       FROM communications c
       JOIN companies co ON co.id = c.company_id
       LEFT JOIN users u ON u.id = c.assigned_user_id
      WHERE c.deleted_at IS NULL
      ORDER BY c.created_at DESC LIMIT 6"
)->fetchAll();

$followUps = db()->query(
    "SELECT c.id, c.next_action, c.next_action_at, co.name AS company_name,
            (c.next_action_at < NOW()) AS overdue
       FROM communications c JOIN companies co ON co.id = c.company_id
      WHERE c.deleted_at IS NULL AND c.next_action_at IS NOT NULL
        AND c.status NOT IN ('won','lost','archived')
      ORDER BY c.next_action_at ASC LIMIT 6"
)->fetchAll();

$activityRows = db()->query(
    "SELECT DATE(created_at) AS activity_date, COUNT(*) AS activity_count
       FROM communications
      WHERE deleted_at IS NULL AND created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
      GROUP BY DATE(created_at)"
)->fetchAll();
$activityMap = [];
foreach ($activityRows as $row) {
    $activityMap[$row['activity_date']] = (int)$row['activity_count'];
}
$activity = [];
for ($daysAgo = 13; $daysAgo >= 0; $daysAgo--) {
    $date = (new DateTimeImmutable("-{$daysAgo} days"))->format('Y-m-d');
    $activity[] = ['date' => $date, 'count' => $activityMap[$date] ?? 0];
}
$maxActivity = max(1, ...array_column($activity, 'count'));

$outcomeRows = db()->query(
    "SELECT COALESCE(outcome, 'unset') AS outcome_key, COUNT(*) AS outcome_count
       FROM communications
      WHERE deleted_at IS NULL AND status <> 'archived'
      GROUP BY outcome"
)->fetchAll();
$outcomeCounts = [];
foreach ($outcomeRows as $row) {
    $outcomeCounts[$row['outcome_key']] = (int)$row['outcome_count'];
}
$outcomeLabels = [
    'interested' => 'Ενδιαφέρεται',
    'callback' => 'Επανάκληση',
    'no_answer' => 'Δεν απάντησε',
    'not_interested' => 'Δεν ενδιαφέρεται',
    'unset' => 'Χωρίς αποτέλεσμα',
];
$communicationTotal = max(1, (int)($totals['communications'] ?? 0));

render_header('Dashboard', $user);
?>
<div class="page-heading"><div><h1>Dashboard</h1><p>Συνολική εικόνα εταιρειών, επικοινωνιών και εκκρεμοτήτων.</p></div><a class="button primary" href="<?= e(crm_url('new-communication.php')) ?>">Νέα καταχώριση</a></div>

<section class="grid stats stats-six">
  <article class="card stat"><span>Επικοινωνίες</span><strong><?= (int)($totals['communications'] ?? 0) ?></strong><small><?= (int)($totals['last_30_days'] ?? 0) ?> τις τελευταίες 30 ημέρες</small></article>
  <article class="card stat"><span>Αιτήματα ιστοσελίδας</span><strong><?= (int)($totals['website'] ?? 0) ?></strong><small>αυτόματες υποβολές έργων</small></article>
  <article class="card stat"><span>Τηλεφωνικές</span><strong><?= (int)($totals['telephone'] ?? 0) ?></strong><small>χειροκίνητες καταχωρίσεις</small></article>
  <article class="card stat"><span>Χωρίς ανάθεση</span><strong><?= (int)($totals['unassigned'] ?? 0) ?></strong><small><?= (int)($totals['unassigned'] ?? 0) > 0 ? 'χρειάζονται υπεύθυνο' : 'όλα έχουν ανατεθεί' ?></small></article>
  <article class="card stat"><span>Εταιρείες σε επαφή</span><strong><?= (int)($totals['companies'] ?? 0) ?></strong><small>με τουλάχιστον μία καταγραφή</small></article>
  <article class="card stat"><span>Εκκρεμείς υπενθυμίσεις</span><strong><?= (int)($totals['overdue'] ?? 0) + (int)($totals['upcoming'] ?? 0) ?></strong><small class="<?= (int)($totals['overdue'] ?? 0) > 0 ? 'negative-text' : '' ?>"><?= (int)($totals['overdue'] ?? 0) ?> εκπρόθεσμες</small></article>
</section>

<section class="dashboard-panels dashboard-top">
  <article class="card report-card panel-wide"><header><h2>Δραστηριότητα</h2><span>τελευταίες 14 ημέρες</span></header><div class="bar-chart" role="img" aria-label="Επικοινωνίες τις τελευταίες 14 ημέρες">
    <?php foreach ($activity as $day): ?><div class="bar-day" title="<?= e($day['date']) ?> · <?= $day['count'] ?>"><i style="height:<?= $day['count'] > 0 ? max(8, (int)round(($day['count'] / $maxActivity) * 100)) : 2 ?>%"></i><small><?= e((new DateTimeImmutable($day['date']))->format('d')) ?></small></div><?php endforeach; ?>
  </div></article>
  <article class="card report-card"><header><h2>Αποτελέσματα</h2><span>σύνολο <?= (int)($totals['communications'] ?? 0) ?></span></header><ul class="outcome-list">
    <?php foreach ($outcomeLabels as $key => $label): $count = $outcomeCounts[$key] ?? 0; if ($key === 'unset' && $count === 0) continue; $share = (int)round(($count / $communicationTotal) * 100); ?><li><div><span><i class="tone-<?= e($key) ?>"></i><?= e($label) ?></span><b><?= $count ?> · <?= $share ?>%</b></div><em><i class="tone-<?= e($key) ?>" style="width:<?= $share ?>%"></i></em></li><?php endforeach; ?>
  </ul></article>
</section>

<section class="dashboard-panels dashboard-bottom">
  <article class="card report-card"><header><h2>Επόμενες ενέργειες</h2><?php if ((int)($totals['overdue'] ?? 0) > 0): ?><span class="negative-text"><?= (int)$totals['overdue'] ?> εκπρόθεσμες</span><?php endif; ?></header>
    <?php if (!$followUps): ?><p class="report-empty">Δεν υπάρχουν προγραμματισμένες υπενθυμίσεις.</p><?php else: ?><ul class="report-list"><?php foreach ($followUps as $item): ?><li><a href="<?= e(crm_url('communication.php?id=' . urlencode($item['id']))) ?>"><span><strong><?= e($item['company_name']) ?></strong><small><?= e($item['next_action'] ?: 'Χωρίς περιγραφή ενέργειας') ?></small></span><span><time><?= e(format_datetime($item['next_action_at'])) ?></time><small class="<?= $item['overdue'] ? 'negative-text' : '' ?>"><?= $item['overdue'] ? 'Εκπρόθεσμη' : 'Προγραμματισμένη' ?></small></span></a></li><?php endforeach; ?></ul><?php endif; ?>
  </article>
  <article class="card report-card panel-wide"><header><h2>Πρόσφατες επικοινωνίες</h2></header>
    <?php if (!$recent): ?><p class="report-empty">Δεν έχει καταγραφεί ακόμη καμία επικοινωνία.</p><?php else: ?><ul class="report-list"><?php foreach ($recent as $item): ?><li><a href="<?= e(crm_url('communication.php?id=' . urlencode($item['id']))) ?>"><span><strong><?= e($item['company_name']) ?></strong><small><?= e(($item['contact_name'] ?: 'Χωρίς επαφή') . ' · ' . $item['user_name']) ?></small></span><span><small><?= e($outcomeLabels[$item['outcome'] ?: 'unset']) ?><?= $item['interest_level'] ? ' · ' . (int)$item['interest_level'] . '/5' : '' ?></small><time><?= e(format_datetime($item['created_at'])) ?></time></span></a></li><?php endforeach; ?></ul><?php endif; ?>
  </article>
</section>
<?php render_footer(); ?>
