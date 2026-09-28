<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';$user=require_login();
if(!can_manage_accounts($user)){http_response_code(403);exit('Δεν έχετε πρόσβαση.');}
personnel_schema();$id=(string)($_GET['id']??'');$errors=[];
try{$document=personnel_document($id);}catch(InvalidArgumentException $error){http_response_code(404);exit('Το έγγραφο δεν βρέθηκε.');}
$returnPath='personnel-signing.php?id='.urlencode($id);
if(!$document['approved_at']||!$document['worker_pdf']||$document['finalized_at']||!personnel_document_current($document)){redirect_to($returnPath);}
$data=json_decode($document['snapshot'],true,512,JSON_THROW_ON_ERROR);
$approvalDate=(new DateTimeImmutable($document['approved_at']))->format('d/m/Y');
$snapshot=hash('sha256',personnel_binding($document).'|'.$document['approved_at']);
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    try{
        personnel_reauth($user,$_POST);
        if(($_POST['reviewed']??'')!=='1'||!hash_equals($snapshot,(string)($_POST['snapshot']??'')))throw new InvalidArgumentException('Ελέγξτε τη νέα προεπισκόπηση.');
        $bytes=personnel_pdf_upload('final_pdf');$pdo=db();$pdo->beginTransaction();
        $lock=$pdo->prepare('SELECT id FROM users WHERE id=? FOR UPDATE');$lock->execute([$document['user_id']]);
        $fresh=personnel_document($id,true);
        if(!$fresh['approved_at']||$fresh['finalized_at']||!personnel_document_current($fresh)||!hash_equals($snapshot,hash('sha256',personnel_binding($fresh).'|'.$fresh['approved_at'])))throw new InvalidArgumentException('Το έγγραφο άλλαξε ή ολοκληρώθηκε ήδη.');
        $hash=hash('sha256',$bytes);
        if(hash_equals((string)$fresh['worker_hash'],$hash))throw new InvalidArgumentException('Η τελική έκδοση δεν μπορεί να είναι ίδια με το αρχικό υπογεγραμμένο PDF.');
        $pdo->prepare('UPDATE personnel_documents SET final_pdf=?,final_hash=?,finalized_at=NOW() WHERE id=?')->execute([$bytes,$hash,$id]);
        personnel_audit($user,$fresh['user_id'],$id,'final_pdf_reviewed','SHA-256 '.$hash);
        $pdo->commit();flash('success','Το τελικό υπογεγραμμένο PDF αποθηκεύτηκε. Η πρόσβαση ενεργοποιείται χωριστά από την καρτέλα.');redirect_to($returnPath);
    }catch(InvalidArgumentException $error){if(db()->inTransaction())db()->rollBack();$errors[]=$error->getMessage();}
    catch(Throwable $error){if(db()->inTransaction())db()->rollBack();error_log('Personnel finalize: '.get_class($error));$errors[]='Η αποθήκευση απέτυχε. Ανανεώστε τη σελίδα.';}
}
$asset = static function (string $name): string {
    $bytes = file_get_contents(__DIR__ . '/private-assets/signatures/' . $name);
    if ($bytes === false) { throw new RuntimeException('Company signing asset missing'); }
    return 'data:image/png;base64,' . base64_encode($bytes);
};
$options = [
    'source' => crm_url('personnel-download.php?file=worker&id=' . urlencode($id)),
    'signature' => $asset('distillogic-signature.png'),
    'stamp' => $asset('distillogic-stamp.png'),
    'date' => $approvalDate,
];
header('Cache-Control: private, no-store');
render_header('Τελική υπογραφή ' . 'Προσωπικού', $user);
?>
<link rel="stylesheet" href="<?= e(crm_url('assets/document-placement.css?v=20260914')) ?>">
<div class="page-heading"><div><h1>Τελική υπογραφή <?= e('Προσωπικού') ?></h1><p><?= e($data['name']) ?> · <?= e($document['reference']) ?></p></div><a class="button" href="<?= e(crm_url($returnPath)) ?>">Πίσω</a></div>
<?php if ($errors): ?><div class="alert error"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
<section class="card placement-panel">
  <h2>Υπογραφή και σφραγίδα DISTILLOGIC</h2>
  <p>Ελέγξτε τα επιλεγμένα πλαίσια στην πλευρά της DISTILLOGIC. Για διόρθωση, επιλέξτε ένα πεδίο και σχεδιάστε το πλαίσιό του μέσα στην αντίστοιχη διακεκομμένη περιοχή. Για την ημερομηνία επιλέξτε τον χώρο πάνω από τη γραμμή.</p>
  <p>Ημερομηνία CEO approval: <strong><?= e($approvalDate) ?></strong></p>
  <div class="placement-tools">
    <label>Σελίδα <select id="placement-page" disabled></select></label>
    <button type="button" class="button" data-field="signature">1. Υπογραφή</button>
    <button type="button" class="button" data-field="date">2. Ημερομηνία</button>
    <button type="button" class="button" data-field="stamp">3. Σφραγίδα</button>
    <button type="button" class="button" id="placement-auto" disabled>Εντοπισμός πεδίων</button>
  </div>
  <p id="placement-status" role="status" aria-live="polite">Φόρτωση του PDF του προσώπου…</p>
  <div id="placement-stage"><canvas id="placement-source"></canvas><div id="placement-overlay" aria-label="Επιλογή περιοχών υπογραφής"></div></div>
  <button type="button" class="button primary" id="placement-preview" disabled>Προεπισκόπηση τελικού PDF</button>
</section>
<section class="card placement-panel" id="placement-review" hidden>
  <h2>Έλεγχος τελικής έκδοσης</h2>
  <p>Αυτή είναι η πραγματική σελίδα του παραγόμενου PDF. Ελέγξτε ότι η υπογραφή, η ημερομηνία και η σφραγίδα βρίσκονται μέσα στα πεδία και ότι όλες οι πληροφορίες διαβάζονται.</p>
  <canvas id="placement-result"></canvas>
  <label class="placement-confirm"><input type="checkbox" id="placement-reviewed"> Έλεγξα τη θέση και την αναγνωσιμότητα των τριών πεδίων.</label>
  <button type="button" class="button primary" id="placement-save" disabled>Αποθήκευση τελικής έκδοσης</button>
</section>
<label class="card form-section">Ο δικός σας κωδικός για αποθήκευση<input type="password" name="actor_password" form="final-upload" autocomplete="current-password" required></label>
<form id="final-upload" method="post" enctype="multipart/form-data" hidden>
  <?= csrf_field() ?><input type="hidden" name="snapshot" value="<?= e($snapshot) ?>"><input type="hidden" name="reviewed" value="1"><input type="file" name="final_pdf" id="final-pdf">
</form>
<script type="application/json" id="placement-options"><?= json_encode($options, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) ?></script>
<script src="<?= e(crm_url('assets/vendor/pdf-lib.min.js')) ?>"></script>
<script type="module" src="<?= e(crm_url('assets/document-placement.js?v=20260915-personnel')) ?>"></script>
<?php render_footer(); ?>
