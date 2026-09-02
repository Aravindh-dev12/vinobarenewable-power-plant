<?php
declare(strict_types=1);
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
ini_set('display_errors', '0'); ini_set('log_errors', '1'); error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/plant_config.php';

function sendJson(int $code, array $body): void { http_response_code($code); echo json_encode($body, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); exit; }
function requestData(): array {
    $raw = file_get_contents('php://input');
    if ($raw !== false && trim($raw) !== '') { $j=json_decode($raw,true); if(is_array($j)) return $j; }
    return $_POST;
}
function bearerToken(): string {
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (function_exists('getallheaders')) { $h=getallheaders(); $auth=$h['Authorization']??$h['authorization']??$auth; }
    return preg_match('/Bearer\s+(\S+)/i',$auth,$m) ? $m[1] : '';
}
function normalizeAndPersistPlant(mysqli $conn, int $id, string $plant): string {
    $new = normalize_plant_id($plant);
    if (is_valid_plant_id($new) && $new !== $plant) {
        $st=$conn->prepare('UPDATE users SET plant_id=? WHERE id=?');
        if($st){$st->bind_param('si',$new,$id);$st->execute();$st->close();}
    }
    return is_valid_plant_id($new) ? $new : $plant;
}
function userByToken(mysqli $conn, string $token): ?array {
    if ($token==='') return null;
    $st=$conn->prepare('SELECT id,email,role,plant_id,auth_token FROM users WHERE auth_token=? LIMIT 1');
    $st->bind_param('s',$token);$st->execute();$r=$st->get_result();$u=$r&&$r->num_rows?$r->fetch_assoc():null;$st->close();
    if($u){$u['plant_id']=normalizeAndPersistPlant($conn,(int)$u['id'],(string)$u['plant_id']);}
    return $u;
}

try {
    $conn->set_charset('utf8mb4');
    $action=(string)($_GET['action']??''); $data=requestData();

    if($action==='login'){
        $email=trim((string)($data['email']??''));$password=(string)($data['password']??'');
        if($email===''||$password==='')sendJson(400,['status'=>'error','message'=>'Email and password are required.']);
        if(!filter_var($email,FILTER_VALIDATE_EMAIL))sendJson(422,['status'=>'error','message'=>'Enter a valid email address.']);
        $st=$conn->prepare('SELECT id,email,password,role,plant_id FROM users WHERE email=? LIMIT 1');
        $st->bind_param('s',$email);$st->execute();$r=$st->get_result();$u=$r&&$r->num_rows?$r->fetch_assoc():null;$st->close();
        if(!$u)sendJson(401,['status'=>'error','message'=>'Invalid email or password.']);
        $stored=(string)$u['password'];$info=password_get_info($stored);$hashed=($info['algoName']??'unknown')!=='unknown';
        $ok=$hashed?password_verify($password,$stored):hash_equals($stored,$password);
        if(!$ok)sendJson(401,['status'=>'error','message'=>'Invalid email or password.']);
        if(!$hashed){$hash=password_hash($password,PASSWORD_DEFAULT);$ps=$conn->prepare('UPDATE users SET password=? WHERE id=?');$id=(int)$u['id'];$ps->bind_param('si',$hash,$id);$ps->execute();$ps->close();}
        $plant=normalizeAndPersistPlant($conn,(int)$u['id'],(string)$u['plant_id']);
        if(($u['role']??'user')!=='admin'&&!is_valid_plant_id($plant))$plant='vinoba-1';
        $token=bin2hex(random_bytes(32));$ts=$conn->prepare('UPDATE users SET auth_token=? WHERE id=?');$id=(int)$u['id'];$ts->bind_param('si',$token,$id);$ts->execute();$ts->close();
        sendJson(200,['status'=>'success','token'=>$token,'user'=>['email'=>$u['email'],'role'=>$u['role'],'plant_id'=>$plant]]);
    }

    if($action==='get_user'){
        $u=userByToken($conn,bearerToken()); if(!$u)sendJson(401,['status'=>'error','message'=>'Invalid or expired session.']);
        sendJson(200,['status'=>'success','user'=>['email'=>$u['email'],'role'=>$u['role'],'plant_id'=>$u['plant_id']]]);
    }

    if($action==='add_user'){
        $cur=userByToken($conn,bearerToken()); if(!$cur||$cur['role']!=='admin')sendJson(403,['status'=>'error','message'=>'Administrator access is required.']);
        $email=trim((string)($data['email']??''));$password=(string)($data['password']??'');$role=trim((string)($data['role']??'user'));$plant=normalize_plant_id((string)($data['plant_id']??''));
        if($email===''||$password==='')sendJson(400,['status'=>'error','message'=>'Email and password are required.']);
        if(!filter_var($email,FILTER_VALIDATE_EMAIL))sendJson(422,['status'=>'error','message'=>'Enter a valid email address.']);
        if(strlen($password)<8)sendJson(422,['status'=>'error','message'=>'Password must contain at least 8 characters.']);
        if(!in_array($role,['admin','user'],true))sendJson(422,['status'=>'error','message'=>'Invalid user role.']);
        if($role!=='admin'&&!is_valid_plant_id($plant))sendJson(422,['status'=>'error','message'=>'Select a valid plant.']);
        if($role==='admin'&&!is_valid_plant_id($plant))$plant='';
        $cs=$conn->prepare('SELECT id FROM users WHERE email=? LIMIT 1');$cs->bind_param('s',$email);$cs->execute();$cr=$cs->get_result();if($cr&&$cr->num_rows){$cs->close();sendJson(409,['status'=>'error','message'=>'An account with this email already exists.']);}$cs->close();
        $hash=password_hash($password,PASSWORD_DEFAULT);$ins=$conn->prepare('INSERT INTO users (email,password,role,plant_id) VALUES (?,?,?,?)');$ins->bind_param('ssss',$email,$hash,$role,$plant);$ins->execute();$ins->close();
        sendJson(201,['status'=>'success','message'=>'User created successfully.']);
    }

    sendJson(404,['status'=>'error','message'=>'Unknown API action.']);
} catch(Throwable $e){error_log('[api] '.$e->getMessage());sendJson(500,['status'=>'error','message'=>'An internal server error occurred.']);}
