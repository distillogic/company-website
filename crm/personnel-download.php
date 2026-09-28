<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';$user=require_login();
if(!can_manage_accounts($user)){http_response_code(403);exit('Δεν έχετε πρόσβαση.');}
personnel_schema();
try{$doc=personnel_document((string)($_GET['id']??''));}catch(InvalidArgumentException $error){http_response_code(404);exit('Το αρχείο δεν βρέθηκε.');}
$type=(string)($_GET['file']??'final');
if(!in_array($type,['worker','final'],true)||!$doc[$type.'_pdf']){http_response_code(404);exit('Το αρχείο δεν είναι διαθέσιμο.');}
header('Content-Type: application/pdf');header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store');
header('Content-Disposition: attachment; filename="'.preg_replace('/[^A-Za-z0-9-]/','-',$doc['reference']).'-'.$type.'.pdf"');
echo $doc[$type.'_pdf'];
