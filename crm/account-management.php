<?php
declare(strict_types=1);
// Included only after authentication. Never return personnel information directly.
if(!defined('CRM_ROOT')){http_response_code(403);exit;}
function account_is_owner_email(string $email):bool {
    return in_array(strtolower(trim($email)),['account2@example.invalid','account3@example.invalid'],true);
}
function ensure_account_management_schema():void {
    personnel_schema();
    account_session_generation('');
    db()->exec("CREATE TABLE IF NOT EXISTS user_lifecycle_events (id CHAR(36) PRIMARY KEY, actor_id CHAR(36) NOT NULL, subject_id CHAR(36) NOT NULL, action VARCHAR(40) NOT NULL, reason TEXT NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX subject_events(subject_id,created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    db()->exec("CREATE TABLE IF NOT EXISTS user_work_profiles (user_id CHAR(36) PRIMARY KEY, details LONGTEXT NULL, deleted_at DATETIME NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, CONSTRAINT work_profile_user_fk FOREIGN KEY(user_id) REFERENCES users(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    db()->exec("CREATE TABLE IF NOT EXISTS user_management_audit (id CHAR(36) PRIMARY KEY, actor_id CHAR(36) NOT NULL, subject_id CHAR(36) NOT NULL, action VARCHAR(40) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
function account_work_fields():array {
    return ['employee_code'=>['Κωδικός συνεργάτη','text',60], 'job_title'=>['Θέση / ειδικότητα','text',150], 'department'=>['Τμήμα / ομάδα','text',150], 'reports_to'=>['Προϊστάμενος','text',150], 'work_phone'=>['Επαγγελματικό τηλέφωνο','tel',60], 'engagement_type'=>['Σχέση συνεργασίας (εργαζόμενος / εξωτερικός συνεργάτης)','text',100], 'start_date'=>['Ημερομηνία έναρξης','date',10], 'end_date'=>['Ημερομηνία λήξης (αν υπάρχει)','date',10], 'work_location'=>['Τόπος / μοντέλο εργασίας','text',150], 'work_schedule'=>['Ωράριο / ζώνη ώρας','text',150], 'skills'=>['Δεξιότητες / πιστοποιήσεις','textarea',2000], 'equipment'=>['Εταιρικός εξοπλισμός / αριθμοί παγίων','textarea',2000], 'notes'=>['Επαγγελματικές σημειώσεις','textarea',2000]];
}
function account_apply_change(array $actor,?array $target,bool $isNew,string $action,array $input):void {
    if(!can_manage_accounts($actor))throw new InvalidArgumentException('Δεν έχετε δικαίωμα διαχείρισης χρηστών.');
    if(!in_array($action,['save','deactivate','archive','reactivate','restore'],true))throw new InvalidArgumentException('Μη έγκυρη ενέργεια.');
    if($isNew&&$action!=='save')throw new InvalidArgumentException('Μη έγκυρη ενέργεια.');
    // New accounts use the authenticated owner session + CSRF; existing changes require re-authentication.
    if(!$isNew){
    start_crm_session();
    if((int)($_SESSION['account_auth_block_until']??0)>time())throw new InvalidArgumentException('Περιμένετε πέντε λεπτά πριν δοκιμάσετε ξανά.');
    $check=db()->prepare('SELECT password_hash FROM users WHERE id=? AND active=1');$check->execute([$actor['id']]);
    if(!password_verify((string)($input['actor_password']??''),(string)$check->fetchColumn())){
        $_SESSION['account_auth_failures']=(int)($_SESSION['account_auth_failures']??0)+1;
        if($_SESSION['account_auth_failures']>=5){$_SESSION['account_auth_block_until']=time()+300;$_SESSION['account_auth_failures']=0;}
        throw new InvalidArgumentException('Ο δικός σας κωδικός δεν είναι σωστός.');
    }
    $_SESSION['account_auth_failures']=0;
    }
    $pdo=db();$pdo->beginTransaction();
    try {
        // Serialise management changes, including protection of privileged identities.
        $lock=$pdo->prepare('SELECT id,email,active FROM users WHERE id=? FOR UPDATE');$lock->execute([$actor['id']]);
        $freshActor=$lock->fetch();if(!$freshActor||!can_manage_accounts($freshActor))throw new InvalidArgumentException('Η πρόσβασή σας άλλαξε.');
        if(!$isNew){
            $lock=$pdo->prepare('SELECT * FROM users WHERE id=? FOR UPDATE');$lock->execute([$target['id']]);$target=$lock->fetch();
            if(!$target)throw new InvalidArgumentException('Ο χρήστης δεν βρέθηκε.');
        }
        $id=$isNew?uuid_v4():(string)$target['id'];
        $protected=!$isNew&&account_is_owner_email($target['email']);
        if($action!=='save'){
            if($protected||$id===$actor['id'])throw new InvalidArgumentException('Δεν επιτρέπεται διαγραφή ή απενεργοποίηση λογαριασμού διοίκησης ή του δικού σας λογαριασμού.');
            if(strcasecmp(trim((string)($input['confirm_email']??'')),$target['email'])!==0)throw new InvalidArgumentException('Πληκτρολογήστε σωστά το email του χρήστη.');
            $reason=trim((string)($input['reason']??''));
            if(mb_strlen($reason)<5||mb_strlen($reason)>2000)throw new InvalidArgumentException('Συμπληρώστε αιτιολογία 5–2000 χαρακτήρων.');
            $q=$pdo->prepare('SELECT deleted_at FROM user_work_profiles WHERE user_id=?');$q->execute([$id]);$archived=(bool)$q->fetchColumn();
            if(($archived&&$action!=='restore')||(!$archived&&$action==='restore')||($action==='reactivate'&&$target['active'])||($action==='deactivate'&&!$target['active']))throw new InvalidArgumentException('Η κατάσταση έχει αλλάξει. Ανανεώστε την καρτέλα.');
            $onboarding=personnel_record($id);
            if(personnel_signing_required_for_access()&&$action==='reactivate'&&$onboarding){
                if(!personnel_complete($onboarding))throw new InvalidArgumentException('Πρέπει να ολοκληρωθούν η σύμβαση και το συμφωνητικό εμπιστευτικότητας με υπογραφές και των δύο μερών.');
                $pdo->prepare('UPDATE personnel_onboarding SET released_at=NOW(),released_by=? WHERE user_id=?')->execute([$actor['id'],$id]);
            }
            if($onboarding&&in_array($action,['archive','restore','deactivate'],true)){
                $pdo->prepare('UPDATE personnel_onboarding SET released_at=NULL,released_by=NULL,onboarding_cycle=onboarding_cycle+? WHERE user_id=?')->execute([$action==='restore'?1:0,$id]);
            }
            $pdo->prepare('UPDATE users SET active=? WHERE id=?')->execute([$action==='reactivate'?1:0,$id]);
            $pdo->prepare('INSERT INTO user_work_profiles(user_id,deleted_at) VALUES(?,?) ON DUPLICATE KEY UPDATE deleted_at=VALUES(deleted_at)')->execute([$id,$action==='archive'?date('Y-m-d H:i:s'):null]);
            $pdo->prepare('INSERT INTO user_access_state(user_id,generation) VALUES(?,1) ON DUPLICATE KEY UPDATE generation=generation+1')->execute([$id]);
            $pdo->prepare('INSERT INTO user_lifecycle_events(id,actor_id,subject_id,action,reason) VALUES(?,?,?,?,?)')->execute([uuid_v4(),$actor['id'],$id,$action,$reason]);
            if($action==='archive')personnel_audit($actor,$id,null,'departure_pending','Αποχώρηση: απαιτείται έλεγχος και ολοκλήρωση πρωτοκόλλου παράδοσης / παραλαβής. Η πρόσβαση έχει διακοπεί ανεξάρτητα από την υπογραφή.');
        }else{
            $name=trim((string)($input['name']??''));$email=strtolower(trim((string)($input['email']??'')));$role=(string)($input['role']??'employee');$active=isset($input['active'])?1:0;
            // Access changes must use the reasoned, audited lifecycle actions.
            if(!$isNew)$active=(int)$target['active'];
            if($isNew){
                $active=1;$role='employee';
            }
            if($protected){$email=$target['email'];$role=$target['role'];$active=1;}
            elseif(account_is_owner_email($email))throw new InvalidArgumentException('Αυτό το email διοίκησης είναι δεσμευμένο.');
            if($name===''||mb_strlen($name)>150||strlen($email)>320||!filter_var($email,FILTER_VALIDATE_EMAIL)||!in_array($role,['employee','manager','technical','admin'],true))throw new InvalidArgumentException('Ελέγξτε όνομα, email και ρόλο CRM.');
            if(!$isNew){$q=$pdo->prepare('SELECT deleted_at FROM user_work_profiles WHERE user_id=?');$q->execute([$id]);if($q->fetchColumn())throw new InvalidArgumentException('Επαναφέρετε πρώτα την αρχειοθετημένη καρτέλα (παραμένει ανενεργή).');}
            $password=(string)($input['password']??'');
            if(personnel_signing_required_for_access()&&!$isNew&&!$protected&&($name!==$target['name']||$email!==$target['email'])&&personnel_record($id)){
                $active=0;
                $pdo->prepare('UPDATE personnel_onboarding SET onboarding_cycle=onboarding_cycle+1,released_at=NULL,released_by=NULL WHERE user_id=?')->execute([$id]);
                $pdo->prepare('INSERT INTO user_access_state(user_id,generation) VALUES(?,1) ON DUPLICATE KEY UPDATE generation=generation+1')->execute([$id]);
                personnel_audit($actor,$id,null,'identity_changed','New signing cycle required');
            }
            if($isNew||$password!==''){
                if($protected&&$id!==$actor['id'])throw new InvalidArgumentException('Ο άλλος λογαριασμός διοίκησης αλλάζει μόνος του τον κωδικό του.');
                if(strlen($password)<12||strlen($password)>72)throw new InvalidArgumentException('Ο νέος κωδικός πρέπει να έχει 12–72 bytes.');
                if(!$isNew&&$password!==(string)($input['password_confirm']??''))throw new InvalidArgumentException('Ο κωδικός δεν συμφωνεί με την επιβεβαίωση.');
            }
            $details=[];
            foreach(account_work_fields() as $key=>[$label,$type,$limit]){
                $value=trim((string)($input[$key]??''));if(mb_strlen($value)>$limit)throw new InvalidArgumentException('Υπερβολικά μεγάλο πεδίο: '.$label);
                if($type==='date'&&$value!==''){$date=DateTimeImmutable::createFromFormat('!Y-m-d',$value);if(!$date||$date->format('Y-m-d')!==$value)throw new InvalidArgumentException('Μη έγκυρη ημερομηνία: '.$label);}
                $details[$key]=$value;
            }
            if($details['start_date']&&$details['end_date']&&$details['end_date']<$details['start_date'])throw new InvalidArgumentException('Η λήξη δεν μπορεί να προηγείται της έναρξης.');
            if($isNew)$pdo->prepare('INSERT INTO users(id,name,email,role,active,password_hash) VALUES(?,?,?,?,?,?)')->execute([$id,$name,$email,$role,$active,password_hash($password,PASSWORD_DEFAULT)]);
            else{
                $pdo->prepare('UPDATE users SET name=?,email=?,role=?,active=? WHERE id=?')->execute([$name,$email,$role,$active,$id]);
                if($password!=='')$pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($password,PASSWORD_DEFAULT),$id]);
            }
            $pdo->prepare('INSERT INTO user_work_profiles(user_id,details) VALUES(?,?) ON DUPLICATE KEY UPDATE details=VALUES(details)')->execute([$id,json_encode($details,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
        }
        $pdo->prepare('INSERT INTO user_management_audit(id,actor_id,subject_id,action) VALUES(?,?,?,?)')->execute([uuid_v4(),$actor['id'],$id,$isNew?'create':$action]);
        $pdo->commit();
        $GLOBALS['activity_account_change'] = ['id'=>$id,'new'=>$isNew];
    }catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw $error;}
}
