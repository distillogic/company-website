<?php
declare(strict_types=1);
// Optional additive connector. It uses existing CRM authentication and cannot
// grant CRM access, import customers or copy a password hash to the Academy.
require __DIR__.'/bootstrap.php';
$bridgePath=__DIR__.'/academy-bridge.config.php';
if(!is_file($bridgePath)){http_response_code(503);exit('Η σύνδεση Academy δεν έχει ρυθμιστεί.');}
$bridge=require $bridgePath;
$secret=(string)($bridge['shared_key']??'');
if(empty($bridge['enabled'])||strlen($secret)<64||str_starts_with($secret,'REPLACE_')){http_response_code(503);exit('Η σύνδεση Academy δεν είναι ενεργή.');}
$ssl=(!empty($_SERVER['HTTPS'])&&!in_array(strtolower((string)$_SERVER['HTTPS']),['off','0'],true))||(int)($_SERVER['SERVER_PORT']??0)===443;
if(!$ssl&&in_array($_SERVER['REMOTE_ADDR']??'',$bridge['trusted_proxy_ips']??[],true)&&($_SERVER['HTTP_X_FORWARDED_PROTO']??'')==='https')$ssl=true;
if(!$ssl){http_response_code(400);exit('Απαιτείται έγκυρη HTTPS σύνδεση.');}
$callback=(string)($bridge['academy_callback']??'');
// Fixed destination; never accept a callback URL from a request.
if($callback!=='https://academy.example.invalid/academy/sso-return.php'||($bridge['issuer']??'')!=='distillogic-crm'){http_response_code(503);exit('Ελέγξτε τη σταθερή διεύθυνση Academy στο bridge config.');}
header('Cache-Control: private, no-store');header('Referrer-Policy: no-referrer');header('X-Content-Type-Options: nosniff');header('X-Frame-Options: DENY');
// Confirmation posts to this CRM first; only the signed ticket posts to Academy.
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self' https://academy.example.invalid; frame-ancestors 'none'; base-uri 'none'");
start_crm_session();
$state=is_string($_GET['state']??null)?$_GET['state']:'';
if(!preg_match('/^[a-f0-9]{64}$/D',$state)){http_response_code(400);exit('Ξεκινήστε τη σύνδεση από την Academy.');}
// current_user checks active, access generation and existing onboarding rules.
// Do not expand the CRM partner route allowlist or change login.php redirects.
$user=current_user();$payload=$signature=null;
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    verify_csrf();
    if(!$user){http_response_code(401);exit('Η σύνδεση CRM έληξε. Ξεκινήστε ξανά.');}
    $claims=['v'=>1,'iss'=>(string)$bridge['issuer'],'aud'=>'https://academy.example.invalid/academy/sso.php','sub'=>(string)$user['id'],'email'=>strtolower(trim($user['email'])),'name'=>$user['name'],'state'=>$state,'jti'=>bin2hex(random_bytes(32)),'iat'=>time(),'exp'=>time()+90];
    $payload=rtrim(strtr(base64_encode(json_encode($claims,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)),'+/','-_'),'=');
    $signature=hash_hmac('sha256',$payload,$secret);
}
?><!doctype html><html lang="el"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Σύνδεση με Director Sales Academy</title><style>body{margin:0;background:#f0f4ef;font:16px/1.7 'Segoe UI',Arial,sans-serif;color:#153f36;display:grid;place-items:center;min-height:100vh}main{max-width:550px;background:white;border:1px solid #dce7dc;border-radius:20px;padding:32px;margin:20px}h1{font-size:28px;line-height:1.3}button,a.button{background:#164c3d;color:white;border:0;border-radius:9px;padding:13px 20px;display:inline-block;font:inherit;cursor:pointer;text-decoration:none}a{color:#165b43}small{color:#5e6f63}</style></head><body><main><small>DISTILLOGIC CRM → SALES ACADEMY</small><h1>Ο εταιρικός σου λογαριασμός, στην εκπαίδευση.</h1>
<?php if(!$user): ?><p>Συνδέσου πρώτα στο Distillogic CRM. Η σύνδεση ανοίγει σε νέα καρτέλα· μετά επέστρεψε εδώ και πάτησε «Συνέχεια».</p><p><a class="button" href="<?= e(crm_url('login.php')) ?>" target="_blank" rel="noopener">Άνοιγμα σύνδεσης CRM ↗</a></p><a href="<?= e('academy-connect.php?state='.$state) ?>">Συνέχεια μετά τη σύνδεση →</a>
<?php elseif($payload): ?><p>Επιβεβαιώθηκε ο λογαριασμός <strong><?= e($user['email']) ?></strong>. Το εισιτήριο ισχύει για 90 δευτερόλεπτα και μόνο μία χρήση.</p><form method="post" action="<?= e($callback) ?>"><input type="hidden" name="payload" value="<?= e($payload) ?>"><input type="hidden" name="signature" value="<?= e($signature) ?>"><button type="submit">Είσοδος στην Academy →</button></form>
<?php else: ?><p>Θα συνδεθείς ως <strong><?= e($user['name']) ?></strong><br><?= e($user['email']) ?></p><p>Η Academy θα λάβει μόνο το αναγνωριστικό χρήστη, το όνομα και το email σου. Δεν μεταφέρονται κωδικοί, πελάτες, έγγραφα ή οικονομικά.</p><form method="post" action="<?= e('academy-connect.php?state='.$state) ?>"><?= csrf_field() ?><button type="submit">Συνέχεια με αυτόν τον λογαριασμό</button></form><?php endif; ?><p><small>Η έξοδος από την Academy δεν αποσυνδέει αυτόματα το CRM.</small></p></main></body></html>
