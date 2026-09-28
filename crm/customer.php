<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/nda-template.php';
$user = require_login();
ensure_customer_workspace_schema();
$distillogicProfile = distillogic_company_profile();
$companyId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$companyId) { http_response_code(404); exit('Ο πελάτης δεν βρέθηκε.'); }

$loadCompany = static function (int $id): array {
    $statement = db()->prepare('SELECT * FROM companies WHERE id = ? AND deleted_at IS NULL LIMIT 1');
    $statement->execute([$id]);
    $company = $statement->fetch();
    if (!$company) { http_response_code(404); exit('Ο πελάτης δεν βρέθηκε.'); }
    return $company;
};
$company = $loadCompany((int)$companyId);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'update_company') {
        $name = trim(mb_substr((string)($_POST['name'] ?? ''), 0, 255));
        $email = trim(mb_substr((string)($_POST['email'] ?? ''), 0, 255));
        if ($name === '') $errors[] = 'Η σύντομη επωνυμία είναι υποχρεωτική.';
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Το email δεν είναι έγκυρο.';
        if (!$errors) {
            try {
                $statement = db()->prepare('UPDATE companies SET name=?, legal_name=?, trading_name=?, legal_form=?, name_key=?, email=?, phone=?, industry=?, website=?, address=?, city=?, country=?, postal_code=?, registration_number=?, vat_number=?, contact_name=?, contact_title=?, dpa_required=?, purchase_order_required=?, purchase_order_reference=?, purchase_order_received_at=?, notes=? WHERE id=? AND deleted_at IS NULL');
                $poReference = trim((string)($_POST['purchase_order_reference'] ?? ''));
                $poReceived = !empty($_POST['purchase_order_received']) && $poReference !== '' ? date('Y-m-d H:i:s') : null;
                $values = [$name, trim((string)($_POST['legal_name'] ?? '')) ?: null, trim((string)($_POST['trading_name'] ?? '')) ?: null, trim((string)($_POST['legal_form'] ?? '')) ?: null, mb_strtolower($name), $email ?: null, trim((string)($_POST['phone'] ?? '')) ?: null, trim((string)($_POST['industry'] ?? '')) ?: null, trim((string)($_POST['website'] ?? '')) ?: null, trim((string)($_POST['address'] ?? '')) ?: null, trim((string)($_POST['city'] ?? '')) ?: null, trim((string)($_POST['country'] ?? '')) ?: null, trim((string)($_POST['postal_code'] ?? '')) ?: null, trim((string)($_POST['registration_number'] ?? '')) ?: null, trim((string)($_POST['vat_number'] ?? '')) ?: null, trim((string)($_POST['contact_name'] ?? '')) ?: null, trim((string)($_POST['contact_title'] ?? '')) ?: null, !empty($_POST['dpa_required']) ? 1 : 0, !empty($_POST['purchase_order_required']) ? 1 : 0, $poReference ?: null, $poReceived, trim((string)($_POST['notes'] ?? '')) ?: null, $companyId];
                $statement->execute($values);
                flash('success', 'Τα στοιχεία του πελάτη αποθηκεύτηκαν.');
                redirect_to('customer.php?id=' . $companyId);
            } catch (PDOException $exception) {
                error_log('Customer update failed: ' . $exception->getMessage());
                $errors[] = $exception->getCode() === '23000' ? 'Υπάρχει ήδη πελάτης με αυτή την επωνυμία.' : 'Τα στοιχεία δεν αποθηκεύτηκαν.';
            }
        }
    }

    if ($action === 'generate_nda') {
        $effectiveDate = (string)($_POST['effective_date'] ?? '');
        $clientLegalName = trim((string)($_POST['client_legal_name'] ?? ''));
        $clientCountry = trim((string)($_POST['client_country'] ?? ''));
        $clientAddress = trim((string)($_POST['client_address'] ?? ''));
        $clientRepresentative = trim((string)($_POST['client_representative_name'] ?? ''));
        $clientTitle = trim((string)($_POST['client_representative_title'] ?? ''));
        $clientEmail = trim((string)($_POST['client_email'] ?? ''));
        $clientEmailConfirm = trim((string)($_POST['client_email_confirm'] ?? ''));
        $distillogicEmail = trim((string)($_POST['distillogic_email'] ?? ''));
        $distillogicEmailConfirm = trim((string)($_POST['distillogic_email_confirm'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $effectiveDate)) $errors[] = 'Επιλέξτε έγκυρη ημερομηνία ισχύος.';
        foreach ([[$clientLegalName, 'νομική επωνυμία'], [$clientCountry, 'χώρα'], [$clientAddress, 'έδρα'], [$clientRepresentative, 'όνομα εκπροσώπου'], [$clientTitle, 'τίτλο εκπροσώπου']] as [$value, $label]) if ($value === '') $errors[] = 'Συμπληρώστε ' . $label . ' του πελάτη.';
        if (!filter_var($clientEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'Το email εκπροσώπου του πελάτη είναι υποχρεωτικό και πρέπει να είναι έγκυρο.';
        if ($clientEmail !== '' && mb_strtolower($clientEmail) !== mb_strtolower($clientEmailConfirm)) $errors[] = 'Τα δύο email του εκπροσώπου του πελάτη δεν είναι ίδια.';
        if (!filter_var($distillogicEmail, FILTER_VALIDATE_EMAIL) || !str_ends_with(mb_strtolower($distillogicEmail), '@distillogic.gr')) $errors[] = 'Το email του υπογράφοντος πρέπει να είναι έγκυρο εταιρικό email @distillogic.gr.';
        if ($distillogicEmail !== '' && mb_strtolower($distillogicEmail) !== mb_strtolower($distillogicEmailConfirm)) $errors[] = 'Τα δύο εταιρικά email του υπογράφοντος δεν είναι ίδια.';
        if (!$errors) {
            try {
                $reference = 'DL-NDA-' . date('Ymd') . '-' . (int)$companyId . '-' . strtoupper(bin2hex(random_bytes(2)));
                $documentId = uuid_v4();
                $siteUrl = rtrim((string)(crm_config()['app']['site_url'] ?? 'https://www.distillogic.gr'), '/');
                $logoPath = dirname(__DIR__) . '/assets/logo/distillogic-logo-light.svg';
                $logoContent = is_file($logoPath) ? file_get_contents($logoPath) : false;
                $embeddedLogo = $logoContent === false ? $siteUrl . '/assets/logo/distillogic-logo-light.svg' : 'data:image/svg+xml;base64,' . base64_encode($logoContent);
                $data = [
                    'document_reference' => $reference,
                    'document_date' => date('d/m/Y'),
                    'effective_date_display' => (new DateTimeImmutable($effectiveDate))->format('d/m/Y'),
                    'logo_url' => $embeddedLogo,
                    'distillogic_legal_name' => $distillogicProfile['legal_name'],
                    'distillogic_address' => $distillogicProfile['address'],
                    'distillogic_vat' => $distillogicProfile['vat_number'],
                    'distillogic_gemi' => $distillogicProfile['gemi_number'],
                    'distillogic_email' => $distillogicEmail,
                    'distillogic_signatory_name' => trim((string)($_POST['distillogic_signatory_name'] ?? '')),
                    'distillogic_signatory_title' => trim((string)($_POST['distillogic_signatory_title'] ?? '')),
                    'client_legal_name' => $clientLegalName,
                    'client_country' => $clientCountry,
                    'client_address' => $clientAddress,
                    'client_registration' => trim((string)($_POST['client_registration'] ?? '')) ?: '—',
                    'client_representative_name' => $clientRepresentative,
                    'client_representative_title' => $clientTitle,
                    'client_email' => $clientEmail,
                ];
                foreach (['distillogic_email','distillogic_signatory_name','distillogic_signatory_title'] as $key) set_crm_setting('nda_' . $key, (string)$data[$key]);
                $html = nda_document_html($data);
                $fileName = preg_replace('/[^A-Za-z0-9._-]/', '-', $reference . '-' . $clientLegalName) . '.doc';
                $pdo = db();
                $pdo->beginTransaction();
                $insert = $pdo->prepare('INSERT INTO company_documents (id, company_id, created_by, document_type, document_reference, title, file_name, mime_type, content, data_json, effective_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $insert->execute([$documentId, $companyId, $user['id'], 'nda', $reference, 'Mutual NDA — ' . $clientLegalName, $fileName, 'application/msword; charset=UTF-8', $html, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $effectiveDate]);
                // The customer address is maintained manually, never copied back from a document.
                $update = $pdo->prepare('UPDATE companies SET legal_name=?, country=?, registration_number=?, vat_number=?, contact_name=?, contact_title=?, email=? WHERE id=?');
                $registration = trim((string)($_POST['client_registration'] ?? ''));
                $update->execute([$clientLegalName, $clientCountry, $registration ?: null, trim((string)($_POST['client_vat'] ?? '')) ?: ($company['vat_number'] ?: null), $clientRepresentative, $clientTitle, $clientEmail ?: ($company['email'] ?: null), $companyId]);
                $pdo->commit();
                flash('success', 'Το NDA δημιουργήθηκε και αποθηκεύτηκε στην καρτέλα πελάτη.');
                redirect_to('company-document.php?id=' . urlencode($documentId));
            } catch (Throwable $exception) {
                if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
                error_log('NDA generation failed: ' . $exception->getMessage());
                $errors[] = 'Το NDA δεν δημιουργήθηκε. Δοκιμάστε ξανά.';
            }
        }
    }
    $company = $loadCompany((int)$companyId);
}

$communicationsStatement = db()->prepare('SELECT id, source, status, contact_name, notes, created_at FROM communications WHERE company_id = ? AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 30');
$communicationsStatement->execute([$companyId]);
$communications = $communicationsStatement->fetchAll();
$documentsStatement = db()->prepare("SELECT id, document_reference, document_status, title, effective_date, client_signed_at, approved_at, final_signed_at, created_at FROM company_documents WHERE company_id = ? AND document_type='nda' ORDER BY created_at DESC");
$documentsStatement->execute([$companyId]);
$documents = $documentsStatement->fetchAll();
$proposalsStatement = db()->prepare("SELECT id, document_reference, document_status, title, data_json, final_signed_pdf, created_at, updated_at FROM company_documents WHERE company_id = ? AND document_type='proposal' ORDER BY updated_at DESC");
$proposalsStatement->execute([$companyId]);
$proposals = $proposalsStatement->fetchAll();
$sowsStatement = db()->prepare("SELECT id, document_reference, document_status, title, data_json, final_signed_pdf, created_at, updated_at FROM company_documents WHERE company_id = ? AND document_type='sow' ORDER BY updated_at DESC");
$sowsStatement->execute([$companyId]);
$sows = $sowsStatement->fetchAll();
$msasStatement = db()->prepare("SELECT id, document_reference, document_status, title, data_json, final_signed_pdf, created_at, updated_at FROM company_documents WHERE company_id = ? AND document_type='msa' ORDER BY updated_at DESC");
$msasStatement->execute([$companyId]);
$msas = $msasStatement->fetchAll();
$dpasStatement = db()->prepare("SELECT id, document_reference, document_status, title, data_json, final_signed_pdf, created_at, updated_at FROM company_documents WHERE company_id = ? AND document_type='dpa' ORDER BY updated_at DESC");
$dpasStatement->execute([$companyId]);
$dpas = $dpasStatement->fetchAll();
$canManageIntakes = in_array($user['role'], ['admin', 'manager'], true);
$intakes = [];
if ($canManageIntakes) {
    $intakesStatement = db()->prepare("SELECT id, document_reference, document_status, title, data_json, created_at, updated_at FROM company_documents WHERE company_id = ? AND document_type='intake' ORDER BY updated_at DESC");
    $intakesStatement->execute([$companyId]);
    $intakes = $intakesStatement->fetchAll();
}
$proposalStatusLabels = ['draft'=>'Draft','qualified'=>'BID','hold'=>'HOLD','no_bid'=>'NO-BID','client_signed'=>'Υπογεγραμμένο από πελάτη','pdf_ready'=>'PDF προς έγκριση','approval_pending'=>'Αναμονή CEO','approved_pdf'=>'Εγκεκριμένο PDF','sent'=>'Στάλθηκε','active'=>'Active','accepted'=>'Έγινε αποδεκτή','rejected'=>'Απορρίφθηκε','terminated'=>'Terminated','expired'=>'Έληξε'];
$fullAddress = implode(', ', array_filter([$company['address'], $company['postal_code'], $company['city']]));
$defaults = [
    'distillogic_email' => crm_setting('nda_distillogic_email', 'account1@example.invalid'),
    'distillogic_signatory_name' => crm_setting('nda_distillogic_signatory_name', 'Example Director'),
    'distillogic_signatory_title' => crm_setting('nda_distillogic_signatory_title', 'Managing Director'),
];
$contractFlow = company_contract_flow((int)$companyId);
$flowStages = [
    'nda' => ['number'=>'01', 'title'=>'NDA', 'description'=>'Αμοιβαία εμπιστευτικότητα', 'url'=>'#nda-documents'],
    'proposal' => ['number'=>'02', 'title'=>'Proposal', 'description'=>'Εμπορική αποδοχή', 'url'=>'proposal.php?company_id='.(int)$companyId],
    'msa' => ['number'=>'03', 'title'=>'MSA', 'description'=>'Σύμβαση-ομπρέλα', 'url'=>'msa.php?company_id='.(int)$companyId],
    'dpa' => ['number'=>'04', 'title'=>'DPA', 'description'=>$contractFlow['dpa_required'] ? 'Απαιτείται για GDPR' : 'Δεν απαιτείται', 'url'=>'dpa.php?company_id='.(int)$companyId],
    'sow' => ['number'=>'05', 'title'=>'SOW', 'description'=>'Scope, παραδοτέα και κόστος', 'url'=>'sow.php?company_id='.(int)$companyId],
    'po' => ['number'=>'06', 'title'=>'PO / Έναρξη', 'description'=>$contractFlow['po_required'] ? 'Αναμονή Purchase Order' : 'Δεν απαιτείται PO', 'url'=>'#client-data'],
];
$stageOrder = array_keys($flowStages);
$previousComplete = true;
foreach ($stageOrder as $stageKey) {
    $flowStages[$stageKey]['complete'] = !empty($contractFlow['complete'][$stageKey]);
    $flowStages[$stageKey]['available'] = $previousComplete;
    if ($stageKey === 'dpa' && !$contractFlow['dpa_required']) $flowStages[$stageKey]['available'] = true;
    if (!$flowStages[$stageKey]['complete']) $previousComplete = false;
}
render_header('Καρτέλα πελάτη', $user);
?>
<div class="page-heading"><div><p class="eyebrow">ΚΑΡΤΕΛΑ ΠΕΛΑΤΗ</p><h1><?= e($company['name']) ?></h1><p>Στοιχεία, επικοινωνίες και συμβατική πορεία σε ένα σημείο.</p></div><div class="actions"><a class="button" href="<?= e(crm_url('customers.php')) ?>">Πίσω στους πελάτες</a><?php if($canManageIntakes): ?><a class="button" href="<?= e(crm_url('client-packs.php?company_id='.(int)$companyId)) ?>">Client packs</a><?php endif; ?><a class="button primary" href="<?= e(crm_url('new-communication.php?company_id=' . (int)$companyId)) ?>">Νέα επικοινωνία</a></div></div>
<?php if ($errors): ?><div class="alert error"><?= e(implode(' ', array_unique($errors))) ?></div><?php endif; ?>
<?php foreach ($documents as $sendableDocument): ?><?php if ($sendableDocument['final_signed_at']): ?><div class="alert info"><strong>Το τελικό NDA είναι έτοιμο.</strong> <a class="button compact primary" href="<?= e(crm_url('nda-signing.php?id=' . urlencode($sendableDocument['id']) . '#send-to-parties')) ?>">Αποστολή NDA στους 2 συμβαλλομένους</a></div><?php endif; ?><?php endforeach; ?>
<div class="customer-workspace">
<section class="card customer-overview"><div class="customer-monogram"><?= e(mb_strtoupper(mb_substr($company['name'], 0, 1))) ?></div><div><h2><?= e($company['legal_name'] ?: $company['name']) ?></h2><p><?= e(implode(' · ', array_filter([$company['industry'], $company['city'], $company['country']]))) ?: 'Δεν έχουν συμπληρωθεί ακόμη στοιχεία.' ?></p></div><div class="customer-kpis"><?php if($canManageIntakes): ?><span><strong><?= count($intakes) ?></strong> Intakes</span><?php endif; ?><span><strong><?= count($communications) ?></strong> Επικοινωνίες</span><span><strong><?= count($documents) ?></strong> NDA</span><span><strong><?= count($msas) ?></strong> MSA</span><span><strong><?= count($dpas) ?></strong> DPA</span><span><strong><?= count($proposals) ?></strong> Προτάσεις</span><span><strong><?= count($sows) ?></strong> SOW</span></div></section>

<section class="card contract-flow">
  <header class="contract-flow-heading"><div><p class="eyebrow">CLIENT CONTRACT FLOW</p><h2>Συμβατική πορεία</h2><p>Κάθε στάδιο ενεργοποιείται μόνο όταν έχουν ολοκληρωθεί τα προηγούμενα.</p></div><span class="contract-ready <?= !empty($contractFlow['complete']['sow']) && !empty($contractFlow['complete']['po']) ? 'is-ready' : '' ?>"><?= !empty($contractFlow['complete']['sow']) && !empty($contractFlow['complete']['po']) ? 'Έτοιμο για έναρξη' : 'Σε εξέλιξη' ?></span></header>
  <div class="contract-flow-track">
    <?php foreach ($flowStages as $stageKey=>$stage): $latestStageDocument=$contractFlow['latest'][$stageKey]??null; $stageClass=$stage['complete']?'is-complete':($stage['available']?'is-current':'is-locked'); ?>
      <article class="contract-stage <?= e($stageClass) ?>">
        <div class="contract-stage-top"><span class="contract-stage-number"><?= e($stage['number']) ?></span><span class="contract-stage-state"><?= $stage['complete'] ? 'Ολοκληρώθηκε' : ($stage['available'] ? 'Επόμενο βήμα' : 'Κλειδωμένο') ?></span></div>
        <h3><?= e($stage['title']) ?></h3><p><?= e($stage['description']) ?></p>
        <?php if ($latestStageDocument): ?><small><?= e((string)$latestStageDocument['document_reference']) ?></small><?php endif; ?>
        <?php if ($stageKey === 'dpa' && !$contractFlow['dpa_required']): ?><span class="button compact disabled">Δεν απαιτείται</span>
        <?php elseif ($stageKey === 'po'): ?><a class="button compact <?= $stage['available']?'':'disabled' ?>" href="<?= e($stage['url']) ?>"><?= $contractFlow['po_required'] ? 'Ρύθμιση PO' : 'Έλεγχος έναρξης' ?></a>
        <?php elseif ($latestStageDocument): ?><a class="button compact <?= $stage['available']||$stage['complete']?'':'disabled' ?>" href="<?= e(crm_url(($stageKey==='nda'?'nda-signing.php':'').($stageKey==='nda'?'?id='.urlencode((string)$latestStageDocument['id']):$stageKey.'.php?id='.urlencode((string)$latestStageDocument['id'])))) ?>">Άνοιγμα</a>
        <?php else: ?><a class="button compact <?= $stage['available']?'primary':'disabled' ?>" href="<?= $stage['available'] ? e(str_starts_with($stage['url'], '#')?$stage['url']:crm_url($stage['url'])) : '#' ?>">Δημιουργία</a><?php endif; ?>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<details id="client-data" class="card workspace-section" open><summary><span><strong>Στοιχεία πελάτη</strong><small>Κοινή πηγή στοιχείων για όλα τα συμβατικά έγγραφα</small></span></summary><form method="post" class="form-section"><?= csrf_field() ?><input type="hidden" name="action" value="update_company"><div class="form-grid">
<label>Σύντομη επωνυμία *<input name="name" required value="<?= e($company['name']) ?>"></label><label>Πλήρης νομική επωνυμία<input name="legal_name" value="<?= e($company['legal_name']) ?>"></label><label>Εμπορική ονομασία<input name="trading_name" value="<?= e($company['trading_name']) ?>"></label><label>Νομική μορφή<input name="legal_form" value="<?= e($company['legal_form']) ?>"></label><label>Αριθμός μητρώου<input name="registration_number" value="<?= e($company['registration_number']) ?>"></label><label>ΑΦΜ / VAT<input name="vat_number" value="<?= e($company['vat_number']) ?>"></label><label>Email<input type="email" name="email" value="<?= e($company['email']) ?>"></label><label>Τηλέφωνο<input name="phone" value="<?= e($company['phone']) ?>"></label><label>Website<input type="url" name="website" value="<?= e($company['website']) ?>"></label><label>Κλάδος<input name="industry" value="<?= e($company['industry']) ?>"></label><label>Κύρια επαφή<input name="contact_name" value="<?= e($company['contact_name']) ?>"></label><label>Τίτλος / ρόλος<input name="contact_title" value="<?= e($company['contact_title']) ?>"></label><label class="field-full">Διεύθυνση<input name="address" value="<?= e($company['address']) ?>"></label><label>Πόλη<input name="city" value="<?= e($company['city']) ?>"></label><label>Τ.Κ.<input name="postal_code" value="<?= e($company['postal_code']) ?>"></label><label>Χώρα<input name="country" value="<?= e($company['country']) ?>"></label><label class="checkbox-row"><input type="checkbox" name="dpa_required" value="1" <?= !empty($company['dpa_required'])?'checked':'' ?>><span><strong>Απαιτείται DPA</strong><small>Ενεργοποιεί το DPA πριν από το SOW.</small></span></label><label class="checkbox-row"><input type="checkbox" name="purchase_order_required" value="1" <?= !empty($company['purchase_order_required'])?'checked':'' ?>><span><strong>Απαιτείται Purchase Order</strong><small>Μπλοκάρει την έναρξη μέχρι την παραλαβή του.</small></span></label><label>Αριθμός / reference PO<input name="purchase_order_reference" value="<?= e($company['purchase_order_reference']) ?>"></label><label class="checkbox-row"><input type="checkbox" name="purchase_order_received" value="1" <?= !empty($company['purchase_order_received_at'])?'checked':'' ?>><span><strong>Το PO παραλήφθηκε</strong><small>Σημειώνεται μόνο όταν υπάρχει reference.</small></span></label><label class="field-full">Εσωτερικές σημειώσεις<textarea name="notes"><?= e($company['notes']) ?></textarea></label></div><div class="actions"><button class="button primary" type="submit">Αποθήκευση στοιχείων</button></div></form></details>

<details class="card workspace-section" open><summary><span><strong>Ιστορικό επικοινωνιών</strong><small>Όλες οι επαφές με τον συγκεκριμένο πελάτη</small></span></summary><div class="workspace-body"><div class="record-list"><?php if (!$communications): ?><p class="empty-state">Δεν υπάρχει ακόμη επικοινωνία.</p><?php endif; ?><?php foreach ($communications as $item): ?><a href="<?= e(crm_url('communication.php?id=' . urlencode($item['id']))) ?>"><span class="status-dot status-<?= e($item['status']) ?>"></span><span><strong><?= e($item['contact_name'] ?: 'Επικοινωνία') ?></strong><small><?= $item['source'] === 'website' ? 'Ιστοσελίδα' : 'Τηλέφωνο' ?> · <?= e(status_label($item['status'])) ?></small></span><time><?= e(format_datetime($item['created_at'])) ?></time></a><?php endforeach; ?></div></div></details>

<?php if($canManageIntakes): ?><details class="card workspace-section" open><summary><span><strong>Project Intake &amp; RFP Qualification</strong><small>Management-only αξιολόγηση πριν από proposal</small></span></summary><div class="workspace-body"><div class="document-grid"><?php if(!$intakes): ?><p class="empty-state">Δεν έχει δημιουργηθεί ακόμη qualification.</p><?php endif; ?><?php foreach($intakes as $intakeRow): $intakeData=json_decode((string)$intakeRow['data_json'],true)?:[]; ?><article class="saved-document"><div class="document-mark">INT</div><div><strong><?= e($intakeData['project_name']??$intakeRow['title']) ?></strong><small><?= e($intakeRow['document_reference']) ?> · <?= e(format_datetime($intakeRow['updated_at'])) ?></small><span class="proposal-status proposal-status-<?= e($intakeRow['document_status']) ?>"><?= e($proposalStatusLabels[$intakeRow['document_status']]??$intakeRow['document_status']) ?></span></div><div class="actions"><a class="button compact" href="<?= e(crm_url('intake.php?id='.urlencode((string)$intakeRow['id']))) ?>">Άνοιγμα</a><a class="button compact" target="_blank" href="<?= e(crm_url('intake-view.php?id='.urlencode((string)$intakeRow['id']))) ?>">Προβολή</a></div></article><?php endforeach; ?></div><div class="actions"><a class="button primary" href="<?= e(crm_url('intake.php?company_id='.(int)$companyId)) ?>">+ Νέο Intake για τον πελάτη</a></div></div></details><?php endif; ?>

<details class="card workspace-section" open><summary><span><strong>Εμπορικές &amp; τεχνικές προτάσεις</strong><small>Client-specific προτάσεις και πορεία έγκρισης</small></span></summary><div class="workspace-body"><div class="document-grid"><?php if (!$proposals): ?><p class="empty-state">Δεν έχει δημιουργηθεί ακόμη πρόταση.</p><?php endif; ?><?php foreach ($proposals as $proposalRow): $proposalData=json_decode((string)$proposalRow['data_json'], true) ?: []; ?><article class="saved-document"><div class="document-mark">PRO</div><div><strong><?= e($proposalData['project_name'] ?? $proposalRow['title']) ?></strong><small><?= e($proposalRow['document_reference']) ?> · <?= e(format_datetime($proposalRow['updated_at'])) ?></small><span class="proposal-status proposal-status-<?= e($proposalRow['document_status']) ?>"><?= e($proposalStatusLabels[$proposalRow['document_status']] ?? $proposalRow['document_status']) ?></span></div><div class="actions"><a class="button compact" href="<?= e(crm_url('proposal.php?id='.urlencode((string)$proposalRow['id']))) ?>">Άνοιγμα</a><a class="button compact" target="_blank" href="<?= e(crm_url('proposal-view.php?id='.urlencode((string)$proposalRow['id']))) ?>">Προβολή</a><?php if ($proposalRow['final_signed_pdf']): ?><a class="button compact primary" href="<?= e(crm_url('proposal-download.php?id='.urlencode((string)$proposalRow['id']).'&pdf=1')) ?>">PDF</a><?php endif; ?></div></article><?php endforeach; ?></div><div class="actions"><a class="button primary" href="<?= e(crm_url('proposal.php?company_id='.(int)$companyId)) ?>">+ Νέα πρόταση για τον πελάτη</a></div></div></details>

<details class="card workspace-section" open><summary><span><strong>Statements of Work</strong><small>Scope, deliverables, timeline, acceptance και commercials</small></span></summary><div class="workspace-body"><div class="document-grid"><?php if(!$sows): ?><p class="empty-state">Δεν έχει δημιουργηθεί ακόμη SOW.</p><?php endif; ?><?php foreach($sows as $sowRow):$sowData=json_decode((string)$sowRow['data_json'],true)?:[];?><article class="saved-document"><div class="document-mark">SOW</div><div><strong><?= e($sowData['project_name']??$sowRow['title']) ?></strong><small><?= e($sowRow['document_reference']) ?> · <?= e(format_datetime($sowRow['updated_at'])) ?></small><span class="proposal-status proposal-status-<?= e($sowRow['document_status']) ?>"><?= e($proposalStatusLabels[$sowRow['document_status']]??$sowRow['document_status']) ?></span></div><div class="actions"><a class="button compact" href="<?= e(crm_url('sow.php?id='.urlencode((string)$sowRow['id']))) ?>">Άνοιγμα</a><a class="button compact" target="_blank" href="<?= e(crm_url('sow-view.php?id='.urlencode((string)$sowRow['id']))) ?>">Προβολή</a><?php if($sowRow['final_signed_pdf']): ?><a class="button compact primary" href="<?= e(crm_url('sow-download.php?id='.urlencode((string)$sowRow['id']).'&pdf=1')) ?>">PDF</a><?php endif; ?></div></article><?php endforeach; ?></div><div class="actions"><a class="button primary" href="<?= e(crm_url('sow.php?company_id='.(int)$companyId)) ?>">+ Νέο SOW για τον πελάτη</a></div></div></details>

<details class="card workspace-section" open><summary><span><strong>Master Services Agreements</strong><small>Το συμβόλαιο-ομπρέλα της συνεργασίας</small></span></summary><div class="workspace-body"><div class="document-grid"><?php if(!$msas): ?><p class="empty-state">Δεν έχει δημιουργηθεί ακόμη MSA.</p><?php endif; ?><?php foreach($msas as $msaRow): ?><article class="saved-document"><div class="document-mark">MSA</div><div><strong><?= e($msaRow['title']) ?></strong><small><?= e($msaRow['document_reference']) ?> · <?= e(format_datetime($msaRow['updated_at'])) ?></small><span class="proposal-status proposal-status-<?= e($msaRow['document_status']) ?>"><?= e($proposalStatusLabels[$msaRow['document_status']]??$msaRow['document_status']) ?></span></div><div class="actions"><a class="button compact" href="<?= e(crm_url('msa.php?id='.urlencode((string)$msaRow['id']))) ?>">Άνοιγμα</a><a class="button compact" target="_blank" href="<?= e(crm_url('msa-view.php?id='.urlencode((string)$msaRow['id']))) ?>">Προβολή</a><?php if($msaRow['final_signed_pdf']): ?><a class="button compact primary" href="<?= e(crm_url('msa-download.php?id='.urlencode((string)$msaRow['id']).'&pdf=1')) ?>">PDF</a><?php endif; ?></div></article><?php endforeach; ?></div><div class="actions"><a class="button primary" href="<?= e(crm_url('msa.php?company_id='.(int)$companyId)) ?>">+ Νέο MSA για τον πελάτη</a></div></div></details>

<details class="card workspace-section" open><summary><span><strong>Data Processing Agreements</strong><small>GDPR Controller–Processor συμφωνίες και annexes</small></span></summary><div class="workspace-body"><div class="document-grid"><?php if(!$dpas): ?><p class="empty-state">Δεν έχει δημιουργηθεί ακόμη DPA.</p><?php endif; ?><?php foreach($dpas as $dpaRow): ?><article class="saved-document"><div class="document-mark">DPA</div><div><strong><?= e($dpaRow['title']) ?></strong><small><?= e($dpaRow['document_reference']) ?> · <?= e(format_datetime($dpaRow['updated_at'])) ?></small><span class="proposal-status proposal-status-<?= e($dpaRow['document_status']) ?>"><?= e($proposalStatusLabels[$dpaRow['document_status']]??$dpaRow['document_status']) ?></span></div><div class="actions"><a class="button compact" href="<?= e(crm_url('dpa.php?id='.urlencode((string)$dpaRow['id']))) ?>">Άνοιγμα</a><a class="button compact" target="_blank" href="<?= e(crm_url('dpa-view.php?id='.urlencode((string)$dpaRow['id']))) ?>">Προβολή</a><?php if($dpaRow['final_signed_pdf']): ?><a class="button compact primary" href="<?= e(crm_url('dpa-download.php?id='.urlencode((string)$dpaRow['id']).'&pdf=1')) ?>">PDF</a><?php endif; ?></div></article><?php endforeach; ?></div><div class="actions"><a class="button primary" href="<?= e(crm_url('dpa.php?company_id='.(int)$companyId)) ?>">+ Νέο DPA για τον πελάτη</a></div></div></details>

<details class="card workspace-section" open><summary><span><strong>Έγγραφα &amp; NDA</strong><small>Δημιουργία, αποθήκευση και λήψη εγγράφων</small></span></summary><div class="workspace-body"><div class="document-grid"><?php if (!$documents): ?><p class="empty-state">Δεν έχει δημιουργηθεί ακόμη εταιρικό έγγραφο.</p><?php endif; ?><?php foreach ($documents as $doc): ?><article class="saved-document"><div class="document-mark">NDA</div><div><strong><?= e($doc['title']) ?></strong><small><?= e($doc['document_reference']) ?> · <?= e(format_datetime($doc['created_at'])) ?></small><span class="nda-status nda-status-<?= e($doc['document_status']) ?>"><?= $doc['final_signed_at'] ? 'Fully signed — TEST' : ($doc['approved_at'] ? 'CEO approved — αναμένει τελική έκδοση' : ($doc['client_signed_at'] ? 'Υπογεγραμμένο από πελάτη' : 'Αναμένει υπογραφή πελάτη')) ?></span></div><div class="actions"><a class="button compact" href="<?= e(crm_url('nda-view.php?id=' . urlencode($doc['id']))) ?>">View PDF</a><a class="button compact" href="<?= e(crm_url('company-document-download.php?id=' . urlencode($doc['id']))) ?>">Download Word</a><a class="button compact primary" href="<?= e(crm_url('nda-signing.php?id=' . urlencode($doc['id']))) ?>">Υπογραφή</a></div></article><?php endforeach; ?></div>
<div class="nda-builder">
  <header><p class="eyebrow">NDA GENERATOR</p><h2>Δημιουργία Mutual NDA</h2><p>Τα στοιχεία του πελάτη έχουν ήδη συμπληρωθεί. Πρόσθεσε όσα λείπουν και δημιούργησε το έγγραφο.</p></header>
  <form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="action" value="generate_nda">
    <h3>Στοιχεία συμφωνίας και πελάτη</h3>
    <div class="form-grid"><label>Ημερομηνία ισχύος *<input type="date" name="effective_date" required value="<?= e(date('Y-m-d')) ?>"></label><label>Πλήρης νομική επωνυμία *<input name="client_legal_name" required value="<?= e($company['legal_name'] ?: $company['name']) ?>"></label><label>Χώρα σύστασης *<input name="client_country" required value="<?= e($company['country']) ?>"></label><label>Registration / VAT number<input name="client_registration" value="<?= e($company['registration_number'] ?: $company['vat_number']) ?>"></label><label class="field-full">Καταχωρημένη έδρα *<input name="client_address" required value="<?= e($fullAddress) ?>"></label><label>Νόμιμος εκπρόσωπος *<input name="client_representative_name" required value="<?= e($company['contact_name']) ?>"></label><label>Τίτλος εκπροσώπου *<input name="client_representative_title" required value="<?= e($company['contact_title']) ?>"></label><label>Email εκπροσώπου *<input type="email" name="client_email" required value="<?= e($company['email']) ?>" autocomplete="email"></label><label>Επιβεβαίωση email εκπροσώπου *<input type="email" name="client_email_confirm" required value="" autocomplete="off" placeholder="Γράψτε ξανά το email"></label><label>ΑΦΜ / VAT (καρτέλα)<input name="client_vat" value="<?= e($company['vat_number']) ?>"></label></div>
    <h3>Εξουσιοδοτημένος υπογράφων DISTILLOGIC</h3>
    <p class="field-note">Τα σταθερά εταιρικά στοιχεία προστίθενται εσωτερικά στο NDA και δεν εμφανίζονται στη φόρμα.</p>
    <div class="form-grid"><label>Όνομα υπογράφοντος *<input name="distillogic_signatory_name" required value="<?= e($defaults['distillogic_signatory_name']) ?>"></label><label>Ιδιότητα υπογράφοντος *<input name="distillogic_signatory_title" required value="<?= e($defaults['distillogic_signatory_title']) ?>"></label><label>Εταιρικό email υπογράφοντος *<input type="email" name="distillogic_email" required value="<?= e($defaults['distillogic_email']) ?>" pattern="[^@\s]+@distillogic\.gr" autocomplete="email"></label><label>Επιβεβαίωση εταιρικού email *<input type="email" name="distillogic_email_confirm" required value="" pattern="[^@\s]+@distillogic\.gr" autocomplete="off" placeholder="Γράψτε ξανά το email"></label></div>
    <div class="actions"><button class="button primary" type="submit">Δημιουργία &amp; αποθήκευση NDA</button></div>
  </form>
</div></div></details>
</div>
<?php render_footer(); ?>
