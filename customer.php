<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/nda-template.php';
$user = require_login();
ensure_customer_workspace_schema();
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
                $statement = db()->prepare('UPDATE companies SET name=?, legal_name=?, trading_name=?, legal_form=?, name_key=?, email=?, phone=?, industry=?, website=?, address=?, city=?, country=?, postal_code=?, registration_number=?, vat_number=?, contact_name=?, contact_title=?, notes=? WHERE id=? AND deleted_at IS NULL');
                $values = [$name, trim((string)($_POST['legal_name'] ?? '')) ?: null, trim((string)($_POST['trading_name'] ?? '')) ?: null, trim((string)($_POST['legal_form'] ?? '')) ?: null, mb_strtolower($name), $email ?: null, trim((string)($_POST['phone'] ?? '')) ?: null, trim((string)($_POST['industry'] ?? '')) ?: null, trim((string)($_POST['website'] ?? '')) ?: null, trim((string)($_POST['address'] ?? '')) ?: null, trim((string)($_POST['city'] ?? '')) ?: null, trim((string)($_POST['country'] ?? '')) ?: null, trim((string)($_POST['postal_code'] ?? '')) ?: null, trim((string)($_POST['registration_number'] ?? '')) ?: null, trim((string)($_POST['vat_number'] ?? '')) ?: null, trim((string)($_POST['contact_name'] ?? '')) ?: null, trim((string)($_POST['contact_title'] ?? '')) ?: null, trim((string)($_POST['notes'] ?? '')) ?: null, $companyId];
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
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $effectiveDate)) $errors[] = 'Επιλέξτε έγκυρη ημερομηνία ισχύος.';
        foreach ([[$clientLegalName, 'νομική επωνυμία'], [$clientCountry, 'χώρα'], [$clientAddress, 'έδρα'], [$clientRepresentative, 'όνομα εκπροσώπου'], [$clientTitle, 'τίτλο εκπροσώπου']] as [$value, $label]) if ($value === '') $errors[] = 'Συμπληρώστε ' . $label . ' του πελάτη.';
        if ($clientEmail !== '' && !filter_var($clientEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'Το email εκπροσώπου δεν είναι έγκυρο.';
        if (!$errors) {
            try {
                $reference = 'DL-NDA-' . date('Ymd') . '-' . (int)$companyId . '-' . strtoupper(bin2hex(random_bytes(2)));
                $documentId = uuid_v4();
                $siteUrl = rtrim((string)(crm_config()['app']['site_url'] ?? 'https://www.distillogic.gr'), '/');
                $logoPath = dirname(__DIR__) . '/assets/logo/distillogic-logo-dark.svg';
                $logoContent = is_file($logoPath) ? file_get_contents($logoPath) : false;
                $embeddedLogo = $logoContent === false ? $siteUrl . '/assets/logo/distillogic-logo-dark.svg' : 'data:image/svg+xml;base64,' . base64_encode($logoContent);
                $data = [
                    'document_reference' => $reference,
                    'document_date' => date('d/m/Y'),
                    'effective_date_display' => (new DateTimeImmutable($effectiveDate))->format('d/m/Y'),
                    'logo_url' => $embeddedLogo,
                    'distillogic_legal_name' => trim((string)($_POST['distillogic_legal_name'] ?? '')),
                    'distillogic_address' => trim((string)($_POST['distillogic_address'] ?? '')),
                    'distillogic_vat' => trim((string)($_POST['distillogic_vat'] ?? '')),
                    'distillogic_gemi' => trim((string)($_POST['distillogic_gemi'] ?? '')),
                    'distillogic_email' => trim((string)($_POST['distillogic_email'] ?? '')),
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
                foreach (['distillogic_legal_name','distillogic_address','distillogic_vat','distillogic_gemi','distillogic_email','distillogic_signatory_name','distillogic_signatory_title'] as $key) set_crm_setting('nda_' . $key, (string)$data[$key]);
                $html = nda_document_html($data);
                $fileName = preg_replace('/[^A-Za-z0-9._-]/', '-', $reference . '-' . $clientLegalName) . '.doc';
                $pdo = db();
                $pdo->beginTransaction();
                $insert = $pdo->prepare('INSERT INTO company_documents (id, company_id, created_by, document_type, document_reference, title, file_name, mime_type, content, data_json, effective_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $insert->execute([$documentId, $companyId, $user['id'], 'nda', $reference, 'Mutual NDA — ' . $clientLegalName, $fileName, 'application/msword; charset=UTF-8', $html, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $effectiveDate]);
                $update = $pdo->prepare('UPDATE companies SET legal_name=?, country=?, address=?, registration_number=?, vat_number=?, contact_name=?, contact_title=?, email=? WHERE id=?');
                $registration = trim((string)($_POST['client_registration'] ?? ''));
                $update->execute([$clientLegalName, $clientCountry, $clientAddress, $registration ?: null, trim((string)($_POST['client_vat'] ?? '')) ?: ($company['vat_number'] ?: null), $clientRepresentative, $clientTitle, $clientEmail ?: ($company['email'] ?: null), $companyId]);
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
$documentsStatement = db()->prepare('SELECT id, document_reference, document_status, title, effective_date, client_signed_at, approved_at, created_at FROM company_documents WHERE company_id = ? ORDER BY created_at DESC');
$documentsStatement->execute([$companyId]);
$documents = $documentsStatement->fetchAll();
$fullAddress = implode(', ', array_filter([$company['address'], $company['postal_code'], $company['city']]));
$defaults = [
    'distillogic_legal_name' => crm_setting('nda_distillogic_legal_name', 'EXAMPLE COMPANY'),
    'distillogic_address' => crm_setting('nda_distillogic_address', 'Example Street 1, Patras, Greece'),
    'distillogic_vat' => crm_setting('nda_distillogic_vat'),
    'distillogic_gemi' => crm_setting('nda_distillogic_gemi'),
    'distillogic_email' => crm_setting('nda_distillogic_email', 'account1@example.invalid'),
    'distillogic_signatory_name' => crm_setting('nda_distillogic_signatory_name', 'Example Director'),
    'distillogic_signatory_title' => crm_setting('nda_distillogic_signatory_title', 'Managing Director'),
];
render_header('Καρτέλα πελάτη', $user);
?>
<div class="page-heading"><div><p class="eyebrow">ΚΑΡΤΕΛΑ ΠΕΛΑΤΗ</p><h1><?= e($company['name']) ?></h1><p>Στοιχεία, επικοινωνίες και εταιρικά έγγραφα σε ένα σημείο.</p></div><div class="actions"><a class="button" href="<?= e(crm_url('customers.php')) ?>">Πίσω στους πελάτες</a><a class="button primary" href="<?= e(crm_url('new-communication.php?company_id=' . (int)$companyId)) ?>">Νέα επικοινωνία</a></div></div>
<?php if ($errors): ?><div class="alert error"><?= e(implode(' ', array_unique($errors))) ?></div><?php endif; ?>
<div class="customer-workspace">
<section class="card customer-overview"><div class="customer-monogram"><?= e(mb_strtoupper(mb_substr($company['name'], 0, 1))) ?></div><div><h2><?= e($company['legal_name'] ?: $company['name']) ?></h2><p><?= e(implode(' · ', array_filter([$company['industry'], $company['city'], $company['country']]))) ?: 'Δεν έχουν συμπληρωθεί ακόμη στοιχεία.' ?></p></div><div class="customer-kpis"><span><strong><?= count($communications) ?></strong> Επικοινωνίες</span><span><strong><?= count($documents) ?></strong> Έγγραφα</span></div></section>

<details class="card workspace-section" open><summary><span><strong>Στοιχεία πελάτη</strong><small>Χειροκίνητη συμπλήρωση και επεξεργασία</small></span></summary><form method="post" class="form-section"><?= csrf_field() ?><input type="hidden" name="action" value="update_company"><div class="form-grid">
<label>Σύντομη επωνυμία *<input name="name" required value="<?= e($company['name']) ?>"></label><label>Πλήρης νομική επωνυμία<input name="legal_name" value="<?= e($company['legal_name']) ?>"></label><label>Εμπορική ονομασία<input name="trading_name" value="<?= e($company['trading_name']) ?>"></label><label>Νομική μορφή<input name="legal_form" value="<?= e($company['legal_form']) ?>"></label><label>Αριθμός μητρώου<input name="registration_number" value="<?= e($company['registration_number']) ?>"></label><label>ΑΦΜ / VAT<input name="vat_number" value="<?= e($company['vat_number']) ?>"></label><label>Email<input type="email" name="email" value="<?= e($company['email']) ?>"></label><label>Τηλέφωνο<input name="phone" value="<?= e($company['phone']) ?>"></label><label>Website<input type="url" name="website" value="<?= e($company['website']) ?>"></label><label>Κλάδος<input name="industry" value="<?= e($company['industry']) ?>"></label><label>Κύρια επαφή<input name="contact_name" value="<?= e($company['contact_name']) ?>"></label><label>Τίτλος / ρόλος<input name="contact_title" value="<?= e($company['contact_title']) ?>"></label><label class="field-full">Διεύθυνση<input name="address" value="<?= e($company['address']) ?>"></label><label>Πόλη<input name="city" value="<?= e($company['city']) ?>"></label><label>Τ.Κ.<input name="postal_code" value="<?= e($company['postal_code']) ?>"></label><label>Χώρα<input name="country" value="<?= e($company['country']) ?>"></label><label class="field-full">Εσωτερικές σημειώσεις<textarea name="notes"><?= e($company['notes']) ?></textarea></label></div><div class="actions"><button class="button primary" type="submit">Αποθήκευση στοιχείων</button></div></form></details>

<details class="card workspace-section" open><summary><span><strong>Ιστορικό επικοινωνιών</strong><small>Όλες οι επαφές με τον συγκεκριμένο πελάτη</small></span></summary><div class="workspace-body"><div class="record-list"><?php if (!$communications): ?><p class="empty-state">Δεν υπάρχει ακόμη επικοινωνία.</p><?php endif; ?><?php foreach ($communications as $item): ?><a href="<?= e(crm_url('communication.php?id=' . urlencode($item['id']))) ?>"><span class="status-dot status-<?= e($item['status']) ?>"></span><span><strong><?= e($item['contact_name'] ?: 'Επικοινωνία') ?></strong><small><?= $item['source'] === 'website' ? 'Ιστοσελίδα' : 'Τηλέφωνο' ?> · <?= e(status_label($item['status'])) ?></small></span><time><?= e(format_datetime($item['created_at'])) ?></time></a><?php endforeach; ?></div></div></details>

<details class="card workspace-section" open><summary><span><strong>Έγγραφα &amp; NDA</strong><small>Δημιουργία, αποθήκευση και λήψη εγγράφων</small></span></summary><div class="workspace-body"><div class="document-grid"><?php if (!$documents): ?><p class="empty-state">Δεν έχει δημιουργηθεί ακόμη εταιρικό έγγραφο.</p><?php endif; ?><?php foreach ($documents as $doc): ?><article class="saved-document"><div class="document-mark">NDA</div><div><strong><?= e($doc['title']) ?></strong><small><?= e($doc['document_reference']) ?> · <?= e(format_datetime($doc['created_at'])) ?></small><span class="nda-status nda-status-<?= e($doc['document_status']) ?>"><?= $doc['approved_at'] ? 'Έγκριση CEO επιβεβαιωμένη' : ($doc['client_signed_at'] ? 'Υπογεγραμμένο από πελάτη' : 'Αναμένει υπογραφή πελάτη') ?></span></div><div class="actions"><a class="button compact" href="<?= e(crm_url('company-document.php?id=' . urlencode($doc['id']))) ?>">Προβολή / PDF</a><a class="button compact" href="<?= e(crm_url('company-document-download.php?id=' . urlencode($doc['id']))) ?>">Download Word</a><a class="button compact primary" href="<?= e(crm_url('nda-signing.php?id=' . urlencode($doc['id']))) ?>">Υπογραφή</a></div></article><?php endforeach; ?></div>
<div class="nda-builder"><header><p class="eyebrow">NDA GENERATOR</p><h2>Δημιουργία Mutual NDA</h2><p>Τα στοιχεία του πελάτη έχουν ήδη συμπληρωθεί. Πρόσθεσε όσα λείπουν και δημιούργησε το έγγραφο.</p></header><form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="action" value="generate_nda"><h3>Στοιχεία συμφωνίας και πελάτη</h3><div class="form-grid"><label>Ημερομηνία ισχύος *<input type="date" name="effective_date" required value="<?= e(date('Y-m-d')) ?>"></label><label>Πλήρης νομική επωνυμία *<input name="client_legal_name" required value="<?= e($company['legal_name'] ?: $company['name']) ?>"></label><label>Χώρα σύστασης *<input name="client_country" required value="<?= e($company['country']) ?>"></label><label>Registration / VAT number<input name="client_registration" value="<?= e($company['registration_number'] ?: $company['vat_number']) ?>"></label><label class="field-full">Καταχωρημένη έδρα *<input name="client_address" required value="<?= e($fullAddress) ?>"></label><label>Νόμιμος εκπρόσωπος *<input name="client_representative_name" required value="<?= e($company['contact_name']) ?>"></label><label>Τίτλος εκπροσώπου *<input name="client_representative_title" required value="<?= e($company['contact_title']) ?>"></label><label>Email εκπροσώπου<input type="email" name="client_email" value="<?= e($company['email']) ?>"></label><label>ΑΦΜ / VAT (καρτέλα)<input name="client_vat" value="<?= e($company['vat_number']) ?>"></label></div><h3>Στοιχεία DISTILLOGIC</h3><p class="field-note">Αποθηκεύονται αυτόματα για το επόμενο NDA.</p><div class="form-grid"><?php foreach ([['distillogic_legal_name','Πλήρης νομική επωνυμία'],['distillogic_address','Καταχωρημένη έδρα'],['distillogic_vat','ΑΦΜ / VAT'],['distillogic_gemi','ΓΕΜΗ / Registration'],['distillogic_email','Εταιρικό email'],['distillogic_signatory_name','Όνομα υπογράφοντος'],['distillogic_signatory_title','Τίτλος υπογράφοντος']] as [$key,$label]): ?><label><?= e($label) ?><input name="<?= e($key) ?>" value="<?= e($defaults[$key]) ?>"<?= in_array($key, ['distillogic_legal_name','distillogic_address','distillogic_email','distillogic_signatory_name','distillogic_signatory_title'], true) ? ' required' : '' ?>></label><?php endforeach; ?></div><div class="actions"><button class="button primary" type="submit">Δημιουργία &amp; αποθήκευση NDA</button></div></form></div></div></details>
</div>
<?php render_footer(); ?>
