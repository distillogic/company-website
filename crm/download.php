<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_login();
$id = (string)($_GET['id'] ?? '');
$statement = db()->prepare('SELECT file_name, mime_type, size_bytes, content FROM website_enquiry_documents WHERE id = ? LIMIT 1');
$statement->execute([$id]);
$document = $statement->fetch();
if (!$document) {
    http_response_code(404);
    exit('Το αρχείο δεν βρέθηκε.');
}
header('Content-Type: ' . $document['mime_type']);
header('Content-Length: ' . (int)$document['size_bytes']);
header('Content-Disposition: attachment; filename*=UTF-8\'\'' . rawurlencode($document['file_name']));
header('X-Content-Type-Options: nosniff');
echo $document['content'];
