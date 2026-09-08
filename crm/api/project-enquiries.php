<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function api_answer(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function field(string $name, int $max, bool $required = true): string
{
    $value = trim((string)($_POST[$name] ?? ''));
    if (($required && $value === '') || mb_strlen($value) > $max) {
        throw new InvalidArgumentException('Το πεδίο ' . $name . ' δεν είναι έγκυρο.');
    }
    return $value;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_answer(405, ['ok' => false, 'error' => 'Method not allowed.']);
}

$origin = strtolower((string)($_SERVER['HTTP_ORIGIN'] ?? ''));
$allowedOrigins = ['https://distillogic.gr', 'https://www.distillogic.gr'];
if ($origin !== '' && !in_array(rtrim($origin, '/'), $allowedOrigins, true)) {
    api_answer(403, ['ok' => false, 'error' => 'Η προέλευση του αιτήματος δεν επιτρέπεται.']);
}
if (trim((string)($_POST['website'] ?? '')) !== '') {
    api_answer(202, ['ok' => true, 'stored' => true, 'reference' => 'DL-' . strtoupper(bin2hex(random_bytes(4))), 'confirmationEmailSent' => false]);
}

$emailForFailure = filter_var((string)($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: null;
try {
    enforce_rate_limit('website-enquiry', 20);
    if ((string)($_POST['privacyConsent'] ?? '') !== 'yes') {
        throw new InvalidArgumentException('Απαιτείται συγκατάθεση επεξεργασίας δεδομένων.');
    }
    $data = [
        'name' => field('name', 120), 'company' => field('company', 255),
        'email' => field('email', 255), 'phone' => field('phone', 30, false),
        'country' => field('country', 100), 'service' => field('service', 80),
        'projectTitle' => field('projectTitle', 180), 'requirement' => field('requirement', 6000),
        'technologies' => field('technologies', 1000, false), 'projectStage' => field('projectStage', 80),
        'engagement' => field('engagement', 80), 'timeline' => field('timeline', 80),
        'requestNda' => strtolower(field('requestNda', 10, false)) === 'yes',
        'language' => in_array(($_POST['language'] ?? 'en'), ['en','el'], true) ? $_POST['language'] : 'en',
        'sourcePage' => field('sourcePage', 300, false),
    ];
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Το email δεν είναι έγκυρο.');
    }
    $allowed = ['pdf','doc','docx','xls','xlsx'];
    $allowedMimes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        // Some servers identify modern Office containers by their ZIP envelope.
        'application/zip',
    ];
    $files = [];
    if (isset($_FILES['documents'])) {
        $names = (array)$_FILES['documents']['name'];
        foreach ($names as $index => $original) {
            $error = (int)((array)$_FILES['documents']['error'])[$index];
            if ($error === UPLOAD_ERR_NO_FILE) continue;
            $size = (int)((array)$_FILES['documents']['size'])[$index];
            $temp = (string)((array)$_FILES['documents']['tmp_name'])[$index];
            $extension = strtolower(pathinfo((string)$original, PATHINFO_EXTENSION));
            if ($error !== UPLOAD_ERR_OK || $size > 10 * 1024 * 1024 || !in_array($extension, $allowed, true)) {
                throw new InvalidArgumentException('Μη επιτρεπτό ή υπερβολικά μεγάλο συνημμένο αρχείο.');
            }
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temp) ?: 'application/octet-stream';
            if (!in_array($mime, $allowedMimes, true)) {
                throw new InvalidArgumentException('Το περιεχόμενο του συνημμένου δεν είναι επιτρεπτό.');
            }
            $files[] = ['original' => basename((string)$original), 'temp' => $temp, 'size' => $size, 'mime' => $mime];
        }
        if (count($files) > 5 || array_sum(array_column($files, 'size')) > 25 * 1024 * 1024) {
            throw new InvalidArgumentException('Τα συνημμένα υπερβαίνουν τα επιτρεπτά όρια.');
        }
    }

    $reference = 'DL-' . strtoupper(bin2hex(random_bytes(4)));
    $communicationId = uuid_v4();
    $enquiryId = uuid_v4();
    $pdo = db();
    $pdo->beginTransaction();
    $nameKey = mb_strtolower($data['company']);
    $companyQuery = $pdo->prepare('SELECT id FROM companies WHERE name_key = ? AND deleted_at IS NULL LIMIT 1');
    $companyQuery->execute([$nameKey]);
    $companyId = $companyQuery->fetchColumn();
    if (!$companyId) {
        $insertCompany = $pdo->prepare('INSERT INTO companies (name, name_key, email, phone) VALUES (?, ?, ?, ?)');
        $insertCompany->execute([$data['company'], $nameKey, $data['email'], $data['phone'] ?: null]);
        $companyId = $pdo->lastInsertId();
    }
    $insertCommunication = $pdo->prepare("INSERT INTO communications (id, company_id, source, status, contact_name, contact_role) VALUES (?, ?, 'website', 'new', ?, ?)");
    $insertCommunication->execute([$communicationId, $companyId, $data['name'], 'Αίτημα έργου ιστοσελίδας']);
    $brief = $pdo->prepare('INSERT INTO communication_project_briefs (communication_id, country, service, project_title, requirement, technologies, project_stage, engagement, timeline, request_nda) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $brief->execute([$communicationId, $data['country'], $data['service'], $data['projectTitle'], $data['requirement'], $data['technologies'] ?: null, $data['projectStage'], $data['engagement'], $data['timeline'], $data['requestNda'] ? 1 : 0]);
    $ipHash = hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? '') . '|' . $reference);
    $enquiry = $pdo->prepare('INSERT INTO website_enquiries (id, reference, communication_id, business_email, language, source_page, submitted_at, request_ip_hash) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)');
    $enquiry->execute([$enquiryId, $reference, $communicationId, $data['email'], $data['language'], $data['sourcePage'] ?: null, $ipHash]);

    $document = $pdo->prepare('INSERT INTO website_enquiry_documents (id, website_enquiry_id, file_name, mime_type, size_bytes, content) VALUES (?, ?, ?, ?, ?, ?)');
    foreach ($files as $file) {
        $content = file_get_contents($file['temp']);
        if ($content === false) throw new RuntimeException('Document storage failed.');
        $document->execute([uuid_v4(), $enquiryId, $file['original'], $file['mime'], $file['size'], $content]);
    }
    $recipients = $pdo->query("SELECT id FROM users WHERE active = 1 AND role IN ('admin','technical')")->fetchAll();
    $notification = $pdo->prepare('INSERT INTO notifications (id, user_id, title, body, action_url) VALUES (?, ?, ?, ?, ?)');
    foreach ($recipients as $recipient) {
        $notification->execute([uuid_v4(), $recipient['id'], 'Νέο αίτημα έργου από την ιστοσελίδα', $data['company'] . ' — ' . $data['projectTitle'], '/crm/communication.php?id=' . $communicationId]);
    }
    $pdo->commit();

    $summary = '<h2>Η αίτησή σας υποβλήθηκε επιτυχώς</h2><p>Κωδικός: <strong>' . e($reference) . '</strong></p><p><strong>Εταιρεία:</strong> ' . e($data['company']) . '<br><strong>Έργο:</strong> ' . e($data['projectTitle']) . '<br><strong>Υπηρεσία:</strong> ' . e($data['service']) . '<br><strong>Χρονοδιάγραμμα:</strong> ' . e($data['timeline']) . '</p><p>' . nl2br(e($data['requirement'])) . '</p>';
    $sent = send_crm_email($data['email'], 'Επιβεβαίωση αιτήματος ' . $reference, $summary);
    clear_rate_limit('website-enquiry');
    api_answer(201, ['ok' => true, 'stored' => true, 'reference' => $reference, 'confirmationEmailSent' => $sent]);
} catch (InvalidArgumentException $exception) {
    api_answer(400, ['ok' => false, 'error' => $exception->getMessage(), 'failureEmailSent' => false]);
} catch (Throwable $exception) {
    if (http_response_code() === 429) {
        api_answer(429, ['ok' => false, 'error' => $exception->getMessage(), 'failureEmailSent' => false]);
    }
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Website enquiry failed: ' . $exception->getMessage());
    $failureSent = $emailForFailure ? send_crm_email($emailForFailure, 'Αποτυχία υποβολής αιτήματος', '<p>Η αίτησή σας δεν καταχωρίστηκε. Παρακαλούμε δοκιμάστε ξανά ή επικοινωνήστε στο info@distillogic.gr.</p>') : false;
    api_answer(500, ['ok' => false, 'error' => 'Η αίτηση δεν καταχωρίστηκε. Παρακαλούμε δοκιμάστε ξανά.', 'failureEmailSent' => $failureSent]);
}
